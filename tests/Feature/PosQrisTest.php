<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PosQrisTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Role $cashierRole;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();

        $this->cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Flowers',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Sunflower',
            'price' => 35000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_pos_transaction_creates_pending_payment_for_qris(): void
    {
        $service = app(TransactionService::class);

        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 2],
        ], $this->cashier->id, 'qris');

        $this->assertSame(Transaction::PENDING_PAYMENT, $tx->status);
        $this->assertSame('qris', $tx->payment->payment_method);
        $this->assertSame('pending', $tx->payment->payment_status);
        $this->assertNotNull($tx->payment->midtrans_order_id);
        $this->assertStringStartsWith('FS-POS-', $tx->payment->midtrans_order_id);
        $this->assertSame(70000, $tx->total);
        $this->assertNull($tx->payment->payment_date);

        // Stock is reserved
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_pos_transaction_creates_completed_immediately_for_cash_and_debit(): void
    {
        $service = app(TransactionService::class);

        $cashTx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 1],
        ], $this->cashier->id, 'cash', 50000);

        $this->assertSame(Transaction::COMPLETED, $cashTx->status);
        $this->assertSame('settlement', $cashTx->payment->payment_status);

        $debitTx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 1],
        ], $this->cashier->id, 'debit');

        $this->assertSame(Transaction::COMPLETED, $debitTx->status);
        $this->assertSame('settlement', $debitTx->payment->payment_status);
    }

    public function test_only_owning_cashier_can_access_qris_endpoints(): void
    {
        $service = app(TransactionService::class);
        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 1],
        ], $this->cashier->id, 'qris');

        $otherCashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'is_active' => true,
        ]);

        // Other cashier tries to store QRIS
        $response = $this->actingAs($otherCashier)->postJson("/api/pos/transactions/{$tx->id}/qris");
        $response->assertStatus(403);

        // Other cashier tries to poll QRIS status
        $response = $this->actingAs($otherCashier)->getJson("/api/pos/transactions/{$tx->id}/qris");
        $response->assertStatus(403);
    }

    public function test_qris_store_generates_qr_code_via_midtrans(): void
    {
        $service = app(TransactionService::class);
        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 1],
        ], $this->cashier->id, 'qris');

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($tx) {
            $mock->shouldReceive('chargeQris')
                ->once()
                ->withArgs(function ($arg) use ($tx) {
                    return $arg->id === $tx->id;
                })
                ->andReturn([
                    'status_code' => '201',
                    'transaction_status' => 'pending',
                    'actions' => [
                        [
                            'name' => 'generate-qr-code',
                            'method' => 'GET',
                            'url' => 'https://api.sandbox.midtrans.com/v2/qris/test-123/qr-code',
                        ],
                    ],
                    'qr_string' => '00020101021226620014...',
                ]);
        });

        $response = $this->actingAs($this->cashier)->postJson("/api/pos/transactions/{$tx->id}/qris");

        $response->assertOk();
        $response->assertJson([
            'message' => 'QRIS code generated.',
            'data' => [
                'qr_url' => 'https://api.sandbox.midtrans.com/v2/qris/test-123/qr-code',
                'qr_string' => '00020101021226620014...',
                'order_id' => $tx->payment->midtrans_order_id,
                'gross_amount' => 35000,
            ],
        ]);

        $tx->payment->refresh();
        $this->assertSame('https://api.sandbox.midtrans.com/v2/qris/test-123/qr-code', $tx->payment->snap_token);
    }

    public function test_qris_polling_completes_transaction_without_admin_notification(): void
    {
        $service = app(TransactionService::class);
        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 1],
        ], $this->cashier->id, 'qris');

        $notifications = $this->spy(NotificationService::class);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($tx) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($tx->payment->midtrans_order_id)
                ->andReturn([
                    'order_id' => $tx->payment->midtrans_order_id,
                    'transaction_status' => 'settlement',
                    'gross_amount' => (string) $tx->total,
                    'payment_type' => 'qris',
                ]);
        });

        $response = $this->actingAs($this->cashier)->getJson("/api/pos/transactions/{$tx->id}/qris");

        $response->assertOk();
        $response->assertJson([
            'status' => 'settlement',
            'transaction_status' => Transaction::COMPLETED,
            'is_completed' => true,
        ]);

        $tx->refresh();
        $this->assertSame(Transaction::COMPLETED, $tx->status);
        $this->assertSame('settlement', $tx->payment->payment_status);
        $this->assertNotNull($tx->payment->payment_date);

        // Crucial requirement: No admin order notification for POS sales
        $notifications->shouldNotHaveReceived('notifyRoles');
    }

    public function test_expired_or_failed_qris_cancels_transaction_and_restores_stock(): void
    {
        $initialStock = $this->product->fresh()->stock; // 10
        $service = app(TransactionService::class);

        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 2],
        ], $this->cashier->id, 'qris');

        $this->assertSame($initialStock - 2, $this->product->fresh()->stock);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($tx) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($tx->payment->midtrans_order_id)
                ->andReturn([
                    'order_id' => $tx->payment->midtrans_order_id,
                    'transaction_status' => 'expire',
                    'gross_amount' => (string) $tx->total,
                    'payment_type' => 'qris',
                ]);

            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $response = $this->actingAs($this->cashier)->getJson("/api/pos/transactions/{$tx->id}/qris");

        $response->assertOk();
        $response->assertJson([
            'status' => 'expire',
            'transaction_status' => Transaction::CANCELLED,
            'is_cancelled' => true,
        ]);

        $tx->refresh();
        $this->assertSame(Transaction::CANCELLED, $tx->status);
        $this->assertSame('expire', $tx->payment->payment_status);

        // Stock restored
        $this->assertSame($initialStock, $this->product->fresh()->stock);
    }

    public function test_cashier_can_cancel_pending_qris_transaction(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $service = app(TransactionService::class);

        $tx = $service->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => 3],
        ], $this->cashier->id, 'qris');

        $this->assertSame($initialStock - 3, $this->product->fresh()->stock);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $response = $this->actingAs($this->cashier)->deleteJson("/api/pos/transactions/{$tx->id}/qris");

        $response->assertOk();
        $response->assertJson(['message' => 'Transaction cancelled.']);

        $tx->refresh();
        $this->assertSame(Transaction::CANCELLED, $tx->status);

        // Stock restored back
        $this->assertSame($initialStock, $this->product->fresh()->stock);
    }
}
