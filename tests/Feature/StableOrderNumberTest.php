<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class StableOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::ADMIN_ORDER)->value('id'),
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Lily Bundle',
            'price' => 100000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
            $mock->shouldReceive('getStatus')->andReturn(['transaction_status' => 'pending']);
            $mock->shouldReceive('cancelTransaction')->andReturnNull();
            $mock->shouldReceive('chargeCoreApi')->andReturn([
                'actions' => [
                    ['name' => 'generate-qr-code', 'url' => 'https://qr.test/code'],
                    ['name' => 'deeplink-redirect', 'url' => 'https://app.test/pay'],
                ],
                'qr_string' => 'raw-qr',
            ]);
        });
    }

    private function createOrder(): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    private function charge(Transaction $transaction, string $method): void
    {
        $this->actingAs($this->member)
            ->postJson(route('api.customer.orders.charge', $transaction), ['method' => $method])
            ->assertOk();
    }

    /** The member picks qris, then gopay (2 attempts), then optionally shopeepay (3 attempts). */
    private function switchMethods(Transaction $transaction, int $attempts): array
    {
        foreach (['qris', 'gopay', 'shopeepay'] as $i => $method) {
            if ($i >= $attempts) {
                break;
            }

            $this->charge($transaction, $method);
        }

        return $transaction->refresh()->payment->attempts()->orderBy('id')->pluck('midtrans_order_id')->all();
    }

    private function settlement(string $orderId, Transaction $transaction): array
    {
        return [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ];
    }

    public function test_order_number_is_set_at_creation_and_stored(): void
    {
        $transaction = $this->createOrder();

        $this->assertMatchesRegularExpression('/^FS-\d{8}-\d{6}$/', $transaction->order_number);
        $this->assertSame(
            $transaction->order_number,
            DB::table('transactions')->where('id', $transaction->id)->value('order_number')
        );
        $this->assertSame(
            'FS-'.$transaction->created_on->format('Ymd').'-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT),
            $transaction->order_number
        );

        $this->assertDatabaseHas('payment_attempts', [
            'payment_id' => $transaction->payment->id,
            'midtrans_order_id' => $transaction->payment->midtrans_order_id,
        ]);
    }

    public function test_pos_sale_gets_a_pos_number(): void
    {
        $sale = Transaction::factory()->create([
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::COMPLETED,
        ]);

        $this->assertSame('POS-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT), $sale->fresh()->order_number);
    }

    public function test_order_number_does_not_change_after_switching_method_twice(): void
    {
        $transaction = $this->createOrder();
        $number = $transaction->order_number;

        $ids = $this->switchMethods($transaction, 3);

        $this->assertCount(3, $ids);
        $this->assertCount(3, array_unique($ids));
        $this->assertSame($number, $transaction->fresh()->order_number);
        $this->assertSame($ids[2], $transaction->fresh()->payment->midtrans_order_id);

        $statuses = $transaction->payment->attempts()->orderBy('id')->pluck('status')->all();
        $this->assertSame(['cancel', 'cancel', 'pending'], $statuses);
    }

    public function test_cancelling_the_charge_keeps_the_number_and_records_an_attempt(): void
    {
        $transaction = $this->createOrder();
        $number = $transaction->order_number;

        $this->charge($transaction, 'qris');

        $this->actingAs($this->member)
            ->postJson(route('api.customer.orders.cancel', $transaction))
            ->assertOk();

        $this->assertSame($number, $transaction->fresh()->order_number);
        $this->assertSame(2, $transaction->payment->attempts()->count());
    }

    public function test_webhook_for_an_old_attempt_reaches_the_right_order(): void
    {
        $other = $this->createOrder();
        $transaction = $this->createOrder();
        $ids = $this->switchMethods($transaction, 2);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($transaction, $ids) {
            $mock->shouldReceive('readNotification')->andReturn($this->settlement($ids[0], $transaction));
            // The newer invoice is closed, so the order cannot be paid twice.
            $mock->shouldReceive('cancelInvoice')->once()->with($ids[1])->andReturnNull();
        });

        $this->postJson('/api/midtrans/notification', [])->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertSame($ids[0], $transaction->payment->midtrans_order_id);
        $this->assertSame('settlement', $transaction->payment->payment_status);
        $this->assertNotNull($transaction->payment->payment_date);

        $this->assertSame(
            ['settlement', 'cancel'],
            $transaction->payment->attempts()->orderBy('id')->pluck('status')->all()
        );

        $this->assertSame(Transaction::PENDING_PAYMENT, $other->fresh()->status);
    }

    public function test_cancelled_or_expired_old_attempt_does_not_cancel_the_order(): void
    {
        $transaction = $this->createOrder();
        $ids = $this->switchMethods($transaction, 2);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($transaction, $ids) {
            $mock->shouldReceive('readNotification')->andReturn(
                array_merge($this->settlement($ids[0], $transaction), ['transaction_status' => 'cancel'])
            );
        });

        $this->postJson('/api/midtrans/notification', [])->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $transaction->status);
        $this->assertSame($ids[1], $transaction->payment->midtrans_order_id);
        $this->assertSame('cancel', $transaction->payment->attempts()->orderBy('id')->first()->status);
    }

    public function test_old_attempt_paid_after_the_order_was_cancelled_is_refunded(): void
    {
        $transaction = $this->createOrder();
        $ids = $this->switchMethods($transaction, 2);

        app(TransactionService::class)->cancel($transaction);

        $amount = (string) $transaction->payment->amount;

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($transaction, $ids, $amount) {
            $mock->shouldReceive('readNotification')->andReturn($this->settlement($ids[0], $transaction));
            $mock->shouldReceive('getStatus')->with($ids[0])->andReturn([
                'transaction_status' => 'settlement',
                'payment_type' => 'gopay',
                'gross_amount' => $amount,
            ]);
            // The refund must go to the attempt that was really paid.
            $mock->shouldReceive('refundTransaction')->once()
                ->with($ids[0], \Mockery::type('array'))
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        $this->postJson('/api/midtrans/notification', [])->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame($ids[0], $transaction->payment->midtrans_order_id);
        $this->assertSame('refund', $transaction->payment->payment_status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Late payment refunded',
        ]);
    }

    public function test_paying_two_attempts_notifies_admin_to_refund_the_extra_one(): void
    {
        $transaction = $this->createOrder();
        $ids = $this->switchMethods($transaction, 2);

        $service = app(TransactionService::class);
        $service->updatePaymentStatus($this->settlement($ids[1], $transaction));
        $service->updatePaymentStatus($this->settlement($ids[0], $transaction));

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertSame($ids[1], $transaction->payment->midtrans_order_id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Duplicate payment needs a refund',
        ]);
    }

    public function test_old_links_with_the_previous_format_still_resolve_after_the_backfill(): void
    {
        $transaction = $this->createOrder();
        $oldId = 'FS-'.$transaction->id.'-OLD123';

        // Make the order look like a row from before this feature.
        DB::table('transactions')->where('id', $transaction->id)->update(['order_number' => null]);
        DB::table('payment_attempts')->where('payment_id', $transaction->payment->id)->delete();
        Payment::whereKey($transaction->payment->id)->update(['midtrans_order_id' => $oldId]);

        (require database_path('migrations/2026_11_19_000001_add_order_number_to_transactions_table.php'))->backfill();
        (require database_path('migrations/2026_11_19_000002_create_payment_attempts_table.php'))->backfill();

        $transaction->refresh();
        $expected = 'FS-'.$transaction->created_on->format('Ymd').'-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);

        $this->assertSame($expected, $transaction->order_number);
        $this->assertSame($transaction->id, Transaction::whereOrderNumber($oldId)->value('id'));
        $this->assertDatabaseHas('payment_attempts', ['midtrans_order_id' => $oldId]);

        $this->actingAs($this->member)->get(route('track', ['order' => $oldId]))->assertOk()->assertSee($expected);
        $this->actingAs($this->member)->get(route('track.invoice', $oldId))->assertOk()->assertSee($expected);
    }

    public function test_admin_can_search_by_the_new_number_and_by_an_old_midtrans_id(): void
    {
        $transaction = $this->createOrder();
        $ids = $this->switchMethods($transaction, 2);
        $number = $transaction->fresh()->order_number;

        $this->actingAs($this->admin)->get(route('admin.orders', ['q' => $number]))
            ->assertOk()->assertSee($number);

        $this->actingAs($this->admin)->get(route('admin.orders', ['q' => $ids[0]]))
            ->assertOk()->assertSee($number);
    }

    public function test_cancellation_view_uses_the_stable_number(): void
    {
        $transaction = $this->createOrder();
        $this->switchMethods($transaction, 2);

        app(TransactionService::class)->cancel($transaction);

        $number = $transaction->fresh()->order_number;
        $viewNumber = DB::table('view_cancellation')->where('id', $transaction->id)->value('order_number');

        $this->assertSame($number, $viewNumber);
        $this->assertNotSame($transaction->fresh()->payment->midtrans_order_id, $viewNumber);
    }
}
