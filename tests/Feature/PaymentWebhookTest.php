<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Peony Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function createPendingOrder(int $qty = 2): Transaction
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => $qty]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    public function test_settlement_webhook_is_idempotent(): void
    {
        $transaction = $this->createPendingOrder();
        $payment = $transaction->payment;

        $payload = [
            'order_id' => $payment->midtrans_order_id,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $payment->amount,
        ];

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('readNotification')->andReturn($payload)->twice();
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $notifications = $this->spy(NotificationService::class);

        $this->postJson('/api/midtrans/notification', [])->assertOk();
        $this->postJson('/api/midtrans/notification', [])->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertSame('settlement', $transaction->payment->payment_status);
        $this->assertNotNull($transaction->payment->payment_date);

        $notifications->shouldHaveReceived('notifyRoles')->once();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $transaction = $this->createPendingOrder();

        // Use the real MidtransService so the actual signature check runs.
        $this->app->instance(MidtransService::class, new MidtransService());

        config(['services.midtrans.server_key' => 'test-server-key']);

        $response = $this->postJson('/api/midtrans/notification', [
            'order_id' => $transaction->payment->midtrans_order_id,
            'status_code' => '200',
            'gross_amount' => (string) $transaction->payment->amount,
            'transaction_status' => 'settlement',
            'signature_key' => 'invalid-signature',
        ]);

        $response->assertStatus(403);

        $transaction->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $transaction->status);
        $this->assertNull($transaction->payment->payment_date);
    }

    public function test_expire_returns_stock_only_once(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $transaction = $this->createPendingOrder(2);

        $this->assertSame($initialStock - 2, $this->product->fresh()->stock);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $payload = [
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => 'expire',
            'gross_amount' => (string) $transaction->payment->amount,
        ];

        $service = app(TransactionService::class);
        $service->updatePaymentStatus($payload);
        $service->updatePaymentStatus($payload);

        $transaction->refresh();
        $this->assertSame(Transaction::CANCELLED, $transaction->status);

        $this->assertSame($initialStock, $this->product->fresh()->stock);

        $cancelLogs = Stock::where('product_id', $this->product->id)
            ->where('change_type', 'cancel')
            ->get();
        $this->assertCount(1, $cancelLogs);
        $this->assertSame(2, (int) $cancelLogs->first()->quantity_change);
    }

    public function test_settlement_rejects_amount_mismatch(): void
    {
        $transaction = $this->createPendingOrder();
        $payment = $transaction->payment;

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('readNotification')->andReturn([
                'order_id' => $payment->midtrans_order_id,
                'transaction_status' => 'settlement',
                'fraud_status' => null,
                'payment_type' => 'gopay',
                'gross_amount' => '999999',
            ]);
        });

        $this->postJson('/api/midtrans/notification', [])->assertStatus(422);

        $transaction->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $transaction->status);
        $this->assertNull($transaction->payment->payment_date);
    }
}
