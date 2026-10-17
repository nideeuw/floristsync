<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;

class ExpirePendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();

        $this->member = User::factory()->create([
            'role_id' => $memberRole->id,
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

    public function test_settled_order_is_paid_through_the_webhook_path(): void
    {
        $tx = $this->createPendingOrder(Transaction::TYPE_ONLINE, 61);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($tx) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($tx->payment->midtrans_order_id)
                ->andReturn([
                    'order_id' => $tx->payment->midtrans_order_id,
                    'transaction_status' => 'settlement',
                    'fraud_status' => null,
                    'payment_type' => 'qris',
                    'gross_amount' => (string) $tx->total,
                ]);
        });

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $tx->refresh();
        $this->assertSame(Transaction::PROCESSING, $tx->status);
        $this->assertSame('settlement', $tx->payment->payment_status);
        $this->assertNotNull($tx->payment->payment_date);

        // A paid order keeps its reserved stock.
        $this->assertSame(8, $this->product->fresh()->stock);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'title' => 'Payment received',
            'type' => 'order_status',
        ]);
    }

    public function test_expired_order_is_cancelled_and_stock_returned_once(): void
    {
        $tx = $this->createPendingOrder(Transaction::TYPE_ONLINE, 61);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($tx) {
            $mock->shouldReceive('getStatus')->andReturn([
                'order_id' => $tx->payment->midtrans_order_id,
                'transaction_status' => 'expire',
                'fraud_status' => null,
                'payment_type' => 'qris',
                'gross_amount' => (string) $tx->total,
            ]);

            $mock->shouldNotReceive('cancelTransaction');
        });

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $tx->refresh();
        $this->assertSame(Transaction::CANCELLED, $tx->status);
        $this->assertSame('expire', $tx->payment->payment_status);
        $this->assertSame(10, $this->product->fresh()->stock);

        $this->assertDatabaseHas('stocks', [
            'product_id' => $this->product->id,
            'quantity_change' => 2,
            'change_type' => 'cancel',
        ]);

        // Running the command again must not return the stock twice.
        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_recent_pending_order_is_left_untouched(): void
    {
        $tx = $this->createPendingOrder(Transaction::TYPE_ONLINE, 10);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getStatus');
        });

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $tx->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $tx->status);
        $this->assertSame('pending', $tx->payment->payment_status);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_pos_pending_order_is_excluded(): void
    {
        $tx = $this->createPendingOrder(Transaction::TYPE_POS, 61);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('getStatus');
        });

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $tx->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $tx->status);
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_one_failing_order_does_not_stop_the_others(): void
    {
        $failing = $this->createPendingOrder(Transaction::TYPE_ONLINE, 61);
        $settled = $this->createPendingOrder(Transaction::TYPE_ONLINE, 61);

        Log::spy();

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($failing, $settled) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($failing->payment->midtrans_order_id)
                ->andThrow(new DomainException('Midtrans is not reachable right now.'));

            $mock->shouldReceive('getStatus')
                ->once()
                ->with($settled->payment->midtrans_order_id)
                ->andReturn([
                    'order_id' => $settled->payment->midtrans_order_id,
                    'transaction_status' => 'settlement',
                    'fraud_status' => null,
                    'payment_type' => 'qris',
                    'gross_amount' => (string) $settled->total,
                ]);
        });

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $failing->refresh();
        $settled->refresh();

        $this->assertSame(Transaction::PENDING_PAYMENT, $failing->status);
        $this->assertSame(Transaction::PROCESSING, $settled->status);

        // The failure is logged, and both orders keep their reserved stock.
        $this->assertSame(6, $this->product->fresh()->stock);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, "order #{$failing->id} failed"));
    }

    public function test_command_is_scheduled_every_five_minutes(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('orders:expire-pending')
            ->assertSuccessful();
    }

    private function createPendingOrder(string $type, int $ageMinutes): Transaction
    {
        $tx = Transaction::create([
            'user_id' => $type === Transaction::TYPE_ONLINE ? $this->member->id : null,
            'cashier_id' => $type === Transaction::TYPE_POS ? $this->cashierId() : null,
            'transaction_type' => $type,
            'status' => Transaction::PENDING_PAYMENT,
            'total' => $this->product->price * 2,
            'created_on' => now()->subMinutes($ageMinutes),
        ]);

        $tx->details()->create([
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => $this->product->price,
            'subtotal' => $this->product->price * 2,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'midtrans_order_id' => ($type === Transaction::TYPE_POS ? 'FS-POS-' : 'FS-').$tx->id.'-TEST01',
            'payment_status' => 'pending',
            'amount' => $tx->total,
        ]);

        Product::whereKey($this->product->id)->decrement('stock', 2);

        return $tx->load('payment');
    }

    private function cashierId(): int
    {
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();

        return User::factory()->create([
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ])->id;
    }
}
