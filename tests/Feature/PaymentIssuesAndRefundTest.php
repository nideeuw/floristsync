<?php

namespace Tests\Feature;

use App\Exceptions\RefundFailedException;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\OrderCancellationService;
use App\Services\PaymentReversalService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentIssuesAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = $this->userWithRole(Role::MEMBER);
        $this->admin = $this->userWithRole(Role::ADMIN_ORDER);
        $this->owner = $this->userWithRole(Role::OWNER);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Rose Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('role_name', $role)->value('id'),
            'is_active' => true,
        ]);
    }

    /** An unpaid order of 100000. */
    private function createOrder(): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    /** An order that was paid with GoPay and is being processed. */
    private function paidOrder(): Transaction
    {
        $transaction = $this->createOrder();

        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::PROCESSING]);
        DB::table('payments')->where('transaction_id', $transaction->id)->update([
            'payment_status' => 'settlement',
            'payment_method' => 'gopay',
            'payment_date' => now(),
        ]);

        return $transaction->refresh();
    }

    private function remote(string $status, array $extra = []): array
    {
        return array_merge([
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'payment_type' => 'gopay',
            'gross_amount' => '100000.00',
        ], $extra);
    }

    private function payment(Transaction $transaction): Payment
    {
        return Payment::where('transaction_id', $transaction->id)->firstOrFail();
    }

    public function test_every_list_category_shows_the_right_orders(): void
    {
        $recent = $this->createOrder();

        $pending = $this->createOrder();
        DB::table('transactions')->where('id', $pending->id)->update(['created_on' => now()->subHours(2)]);

        $latePaid = $this->createOrder();
        DB::table('transactions')->where('id', $latePaid->id)->update(['status' => Transaction::CANCELLED]);
        DB::table('payments')->where('transaction_id', $latePaid->id)->update(['payment_status' => 'settlement']);

        $failed = $this->paidOrder();
        DB::table('payments')->where('transaction_id', $failed->id)->update(['refund_status' => 'failed']);

        $unconfirmed = $this->paidOrder();
        DB::table('payments')->where('transaction_id', $unconfirmed->id)->update([
            'payment_status' => 'capture',
            'payment_date' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)->get(route('admin.orders.payment-issues'))
            ->assertOk()
            ->assertViewHas('sections', function (array $s) use ($recent, $pending, $latePaid, $failed, $unconfirmed) {
                return $s['pending']->pluck('id')->all() === [$pending->id]
                    && ! $s['pending']->pluck('id')->contains($recent->id)
                    && $s['late_paid']->pluck('id')->all() === [$latePaid->id]
                    && $s['failed_refunds']->pluck('id')->all() === [$failed->id]
                    && $s['unconfirmed']->pluck('id')->all() === [$unconfirmed->id];
            });
    }

    public function test_owner_can_read_the_list_but_cannot_recheck(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->owner)->get(route('admin.orders.payment-issues'))->assertOk();
        $this->actingAs($this->owner)->post(route('admin.orders.payment-issues.recheck', $order))->assertForbidden();
    }

    public function test_recheck_applies_a_settled_status_only_once(): void
    {
        $transaction = $this->createOrder();

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->twice()->andReturn($this->remote('settlement'));
        });

        $this->actingAs($this->admin);

        $this->post(route('admin.orders.payment-issues.recheck', $transaction))->assertSessionHas('success');
        $this->post(route('admin.orders.payment-issues.recheck', $transaction))->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertSame('settlement', $transaction->payment->payment_status);
        $this->assertNotNull($transaction->payment->payment_date);

        $this->assertSame(1, DB::table('audit_logs')
            ->where('action', 'order.status_changed')->where('entity_id', $transaction->id)->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('title', 'New paid order')->where('user_id', $this->admin->id)->count());
    }

    public function test_successful_refund_is_recorded_and_the_member_sees_it(): void
    {
        $transaction = $this->paidOrder();
        $stock = $this->product->fresh()->stock;

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund', 'refund_amount' => '100000.00']);
        });

        app(OrderCancellationService::class)->cancel($transaction, $this->admin, 'Out of stock');

        $transaction->refresh();
        $payment = $this->payment($transaction);

        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame($stock + 1, $this->product->fresh()->stock);
        $this->assertSame('refund', $payment->payment_status);
        $this->assertSame('success', $payment->refund_status);
        $this->assertSame(100000, (int) $payment->refund_amount);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame('RF-'.$payment->midtrans_order_id, $payment->refund_key);

        $this->actingAs($this->member)->get(route('track', ['order' => $transaction->order_number]))
            ->assertOk()
            ->assertSee('Refunded');
    }

    public function test_partial_refund_keeps_the_amount_midtrans_returned(): void
    {
        $transaction = $this->paidOrder();

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'partial_refund', 'refund_amount' => '40000.00']);
        });

        app(PaymentReversalService::class)->refund($transaction);

        $payment = $this->payment($transaction);
        $this->assertSame('partial_refund', $payment->payment_status);
        $this->assertSame('success', $payment->refund_status);
        $this->assertSame(40000, (int) $payment->refund_amount);
    }

    public function test_duplicate_refund_is_treated_as_the_same_refund(): void
    {
        $transaction = $this->paidOrder();

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->twice()->andReturn(
                $this->remote('settlement'),
                $this->remote('refund', ['refund_amount' => '100000.00']),
            );
            $mock->shouldReceive('refundTransaction')->once()
                ->andThrow(new DomainException('Duplicate refund ID (RF-123).'));
        });

        app(PaymentReversalService::class)->refund($transaction);

        $payment = $this->payment($transaction);
        $this->assertSame('refund', $payment->payment_status);
        $this->assertSame('success', $payment->refund_status);
        $this->assertSame(100000, (int) $payment->refund_amount);
    }

    public function test_rejected_refund_keeps_the_order_and_the_stock_and_can_be_retried(): void
    {
        $transaction = $this->paidOrder();
        $stock = $this->product->fresh()->stock;

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andThrow(new DomainException('Midtrans rejected the refund.'));
        });

        try {
            app(OrderCancellationService::class)->cancel($transaction, $this->admin, 'Out of stock');
            $this->fail('A RefundFailedException was expected.');
        } catch (RefundFailedException $e) {
            $this->assertSame('Midtrans rejected the refund.', $e->getMessage());
        }

        $transaction->refresh();
        $payment = $this->payment($transaction);

        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertSame($stock, $this->product->fresh()->stock);
        $this->assertSame('settlement', $payment->payment_status);
        $this->assertSame('failed', $payment->refund_status);
        $this->assertNull($payment->refunded_at);

        // The same cancellation works once Midtrans accepts the refund.
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        app(OrderCancellationService::class)->cancel($transaction, $this->admin, 'Out of stock');

        $this->assertSame(Transaction::CANCELLED, $transaction->fresh()->status);
        $this->assertSame($stock + 1, $this->product->fresh()->stock);
        $this->assertSame('success', $this->payment($transaction)->refund_status);
    }

    public function test_failed_refund_of_a_cancelled_order_can_be_retried_from_the_cancellations_page(): void
    {
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::CANCELLED]);
        DB::table('payments')->where('transaction_id', $transaction->id)->update([
            'payment_status' => 'settlement',
            'payment_method' => 'gopay',
            'refund_status' => 'failed',
        ]);

        $this->actingAs($this->admin)->get(route('admin.orders.cancellations'))
            ->assertOk()
            ->assertSee('Retry refund');

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.cancellations.retry-refund', $transaction))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame('success', $this->payment($transaction)->refund_status);
        $this->assertSame('refund', $this->payment($transaction)->payment_status);
    }

    public function test_payment_issues_menu_is_for_admin_order_and_owner_only(): void
    {
        $menu = \App\Models\Menu::where('url_link', 'admin.orders.payment-issues')->firstOrFail();

        $roles = \App\Models\MenuPermission::where('menu_id', $menu->id)->with('role')->get()
            ->pluck('role.role_name')->sort()->values()->all();

        $this->assertSame([Role::ADMIN_ORDER, Role::OWNER], $roles);
    }
}
