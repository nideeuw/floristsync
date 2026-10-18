<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class LatePaymentRefundTest extends TestCase
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

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Rose Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function cancelledOrder(): Transaction
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $service = app(TransactionService::class);

        $transaction = $service->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 2]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );

        $service->cancel($transaction);

        return $transaction->refresh();
    }

    private function settlementPayload(Transaction $transaction): array
    {
        return [
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ];
    }

    private function remote(string $status): array
    {
        return [
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'payment_type' => 'gopay',
            'gross_amount' => '200000.00',
        ];
    }

    public function test_order_paid_after_cancel_is_refunded_and_admin_is_notified(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $transaction = $this->cancelledOrder();
        $payload = $this->settlementPayload($transaction);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        app(TransactionService::class)->updatePaymentStatus($payload);

        $transaction->refresh();

        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame('refund', $transaction->payment->payment_status);
        $this->assertSame($initialStock, $this->product->fresh()->stock);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Late payment refunded',
        ]);
    }

    public function test_refund_failure_does_not_fail_the_webhook(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $transaction = $this->cancelledOrder();
        $payload = $this->settlementPayload($transaction);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('readNotification')->andReturn($payload);
            $mock->shouldReceive('getStatus')->once()->andReturn($this->remote('settlement'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andThrow(new DomainException('Midtrans rejected the refund.'));
        });

        $this->postJson('/api/midtrans/notification', [])->assertOk();

        $transaction->refresh();

        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame($initialStock, $this->product->fresh()->stock);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin->id,
            'title' => 'Late payment needs a refund',
        ]);
    }

    public function test_resent_webhook_does_not_notify_twice(): void
    {
        $transaction = $this->cancelledOrder();
        $payload = $this->settlementPayload($transaction);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->twice()
                ->andReturn($this->remote('settlement'), $this->remote('refund'));
            $mock->shouldReceive('refundTransaction')->once()
                ->andReturn(['status_code' => '200', 'transaction_status' => 'refund']);
        });

        $service = app(TransactionService::class);
        $service->updatePaymentStatus($payload);
        $service->updatePaymentStatus($payload);

        $notifications = DB::table('notifications')->where('title', 'Late payment refunded');

        // Every admin_order user gets the notification once, even after the webhook is resent.
        $this->assertSame(0, (clone $notifications)->select('user_id')->groupBy('user_id')->havingRaw('count(*) > 1')->get()->count());
        $this->assertSame(1, (clone $notifications)->where('user_id', $this->admin->id)->count());
        $this->assertSame('refund', $transaction->refresh()->payment->payment_status);
    }
}