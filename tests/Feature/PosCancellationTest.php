<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\PosCancellationService;
use App\Services\ShiftService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCancellationTest extends TestCase
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
            'product_name' => 'Red Rose',
            'price' => 50000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_view_cancel_page_with_todays_pos_transactions(): void
    {
        $todayTx = $this->createPosTransaction($this->cashier, 2, Transaction::COMPLETED);

        $otherCashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'is_active' => true,
        ]);
        $otherCashierTx = $this->createPosTransaction($otherCashier, 1, Transaction::COMPLETED);

        $onlineTx = Transaction::create([
            'user_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::COMPLETED,
            'total' => 100000,
            'created_on' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->get(route('cashier.cancel'));

        $response->assertOk();
        $response->assertSee($todayTx->order_number);
        $response->assertDontSee($otherCashierTx->order_number);
        $response->assertDontSee($onlineTx->order_number);
    }

    public function test_service_cancels_completed_pos_transaction_and_restores_stock(): void
    {
        $initialStock = $this->product->fresh()->stock; // 10
        $tx = $this->createPosTransaction($this->cashier, 3, Transaction::COMPLETED);

        // createPosTransaction reduced stock by 3, so stock should be 7
        $this->assertSame($initialStock - 3, $this->product->fresh()->stock);

        $service = app(PosCancellationService::class);
        $result = $service->cancel($tx, $this->cashier, 'Customer returned items');

        $this->assertSame(Transaction::CANCELLED, $result->status);
        $this->assertSame('Customer returned items', $result->cancel_reason);
        $this->assertSame($this->cashier->id, $result->cancelled_by);
        $this->assertNotNull($result->cancelled_at);

        // Stock restored back to initialStock (10)
        $this->assertSame($initialStock, $this->product->fresh()->stock);

        // Check stock log
        $this->assertDatabaseHas('stocks', [
            'product_id' => $this->product->id,
            'quantity_change' => 3,
            'change_type' => 'cancel',
        ]);
    }

    public function test_service_rejects_non_pos_transaction(): void
    {
        $onlineTx = Transaction::create([
            'user_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::COMPLETED,
            'total' => 50000,
            'created_on' => now(),
        ]);

        $service = app(PosCancellationService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only POS transactions can be cancelled.');

        $service->cancel($onlineTx, $this->cashier, 'Wrong transaction');
    }

    public function test_cashier_cannot_cancel_sale_of_another_cashier(): void
    {
        $otherCashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'is_active' => true,
        ]);
        $otherTx = $this->createPosTransaction($otherCashier, 1, Transaction::COMPLETED);

        $response = $this->actingAs($this->cashier)->post(route('cashier.cancel.store', $otherTx), [
            'reason' => 'Attempt to cancel colleague sale',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You can only cancel your own transactions.');
        $this->assertSame(Transaction::COMPLETED, $otherTx->fresh()->status);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You can only cancel your own transactions.');

        app(PosCancellationService::class)->cancel($otherTx, $this->cashier, 'Attempt to cancel colleague sale');
    }

    public function test_service_rejects_non_completed_transaction(): void
    {
        $pendingTx = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::PENDING_PAYMENT,
            'total' => 50000,
            'created_on' => now(),
        ]);

        $service = app(PosCancellationService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only completed POS transactions can be cancelled.');

        $service->cancel($pendingTx, $this->cashier, 'Wrong status');
    }

    public function test_service_rejects_already_cancelled_transaction(): void
    {
        $tx = $this->createPosTransaction($this->cashier, 1, Transaction::CANCELLED);

        $service = app(PosCancellationService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This transaction has already been cancelled.');

        $service->cancel($tx, $this->cashier, 'Already cancelled');
    }

    public function test_service_requires_non_empty_reason(): void
    {
        $tx = $this->createPosTransaction($this->cashier, 1, Transaction::COMPLETED);

        $service = app(PosCancellationService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cancellation reason is required.');

        $service->cancel($tx, $this->cashier, '   ');
    }

    public function test_controller_store_cancels_transaction_and_redirects(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $tx = $this->createPosTransaction($this->cashier, 2, Transaction::COMPLETED);

        $response = $this->actingAs($this->cashier)->post(route('cashier.cancel.store', $tx), [
            'reason' => 'Customer changed payment method',
        ]);

        $response->assertRedirect(route('cashier.cancel'));
        $response->assertSessionHas('success');

        $tx->refresh();
        $this->assertSame(Transaction::CANCELLED, $tx->status);
        $this->assertSame('Customer changed payment method', $tx->cancel_reason);
        $this->assertSame($this->cashier->id, $tx->cancelled_by);
        $this->assertNotNull($tx->cancelled_at);

        // Stock returned
        $this->assertSame($initialStock, $this->product->fresh()->stock);
    }

    public function test_controller_store_validates_required_reason(): void
    {
        $tx = $this->createPosTransaction($this->cashier, 1, Transaction::COMPLETED);

        $response = $this->actingAs($this->cashier)->post(route('cashier.cancel.store', $tx), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(Transaction::COMPLETED, $tx->fresh()->status);
    }

    public function test_cancelled_transaction_does_not_count_towards_shift_revenue(): void
    {
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->open($this->cashier);

        $tx1 = $this->createPosTransaction($this->cashier, 2, Transaction::COMPLETED, $shift); // 100,000
        $tx2 = $this->createPosTransaction($this->cashier, 1, Transaction::COMPLETED, $shift); // 50,000

        // Initially 2 orders, 150,000 revenue
        $summaryBefore = $shiftService->getShiftSummary($shift, $this->cashier);
        $this->assertSame(2, (int) $summaryBefore['summary']->orders);
        $this->assertSame(150000, (int) $summaryBefore['summary']->revenue);

        // Cancel tx1
        app(PosCancellationService::class)->cancel($tx1, $this->cashier, 'Refunded');

        // After cancellation: only 1 successful order, 50,000 revenue
        $summaryAfter = $shiftService->getShiftSummary($shift, $this->cashier);
        $this->assertSame(1, (int) $summaryAfter['summary']->orders);
        $this->assertSame(50000, (int) $summaryAfter['summary']->revenue);
        $this->assertSame(50000, (int) ($summaryAfter['byMethod']->get('cash')?->total ?? 0));
    }

    private function createPosTransaction(User $cashier, int $qty, string $status, ?Shift $shift = null): Transaction
    {
        $total = $this->product->price * $qty;

        $tx = Transaction::create([
            'cashier_id' => $cashier->id,
            'shift_id' => $shift?->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => $status,
            'total' => $total,
            'created_on' => now(),
        ]);

        TransactionDetail::create([
            'transaction_id' => $tx->id,
            'product_id' => $this->product->id,
            'qty' => $qty,
            'unit_price' => $this->product->price,
            'subtotal' => $total,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'payment_method' => 'cash',
            'payment_status' => 'settlement',
            'amount' => $total,
            'payment_date' => now(),
        ]);

        if ($status === Transaction::COMPLETED) {
            $this->product->decrement('stock', $qty);
        }

        return $tx;
    }
}
