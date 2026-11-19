<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\PosCancellationService;
use App\Services\ShiftService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $ownerRole = Role::where('role_name', Role::OWNER)->firstOrFail();

        $this->cashier = User::factory()->create([
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Flowers',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Carnation',
            'price' => 50000,
            'stock' => 50,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_expected_cash_calculation_with_cash_sale_and_cancelled_cash_sale(): void
    {
        $shiftService = app(ShiftService::class);
        $cancelService = app(PosCancellationService::class);

        // Shift opens with 100,000 opening cash
        $shift = $shiftService->open($this->cashier, 100000);
        $this->assertSame(100000, $shift->opening_cash);

        // Cash sale 1: 50,000 (remains completed)
        $tx1 = $this->createTransaction($this->cashier, $shift, 'cash', 50000);

        // Cash sale 2: 30,000 (completed, then cancelled)
        $tx2 = $this->createTransaction($this->cashier, $shift, 'cash', 30000);

        // Cancel tx2 with cash refund
        $cancelService->cancel($tx2, $this->cashier, 'Wrong item ordered', 'cash');
        $this->assertSame(Transaction::CANCELLED, $tx2->fresh()->status);
        $this->assertSame('cash', $tx2->fresh()->refund_method);

        // Reconciliation: Expected = opening (100k) + cash sales (80k) - cancelled cash (30k) = 150,000
        $recon = $shiftService->getCashReconciliation($shift);

        $this->assertSame(100000, $recon['opening_cash']);
        $this->assertSame(80000, $recon['cash_sales']);
        $this->assertSame(30000, $recon['cancelled_cash_sales']);
        $this->assertSame(150000, $recon['expected_cash']);

        // Closing with exact count: 150,000
        $shiftService->close($shift, 150000);
        $shift->refresh();

        $this->assertFalse($shift->isOpen());
        $this->assertSame(150000, $shift->closing_cash_counted);
        $this->assertSame(0, $shift->cash_difference);
    }

    public function test_expected_cash_calculation_with_cash_shortage_and_overage(): void
    {
        $shiftService = app(ShiftService::class);

        $shift = $shiftService->open($this->cashier, 100000);
        $this->createTransaction($this->cashier, $shift, 'cash', 50000);

        // Expected is 150,000. Counted is 140,000 (short by 10,000)
        $shiftService->close($shift, 140000);
        $shift->refresh();

        $this->assertSame(140000, $shift->closing_cash_counted);
        $this->assertSame(-10000, $shift->cash_difference);
    }

    public function test_qris_and_debit_are_excluded_from_expected_cash(): void
    {
        $shiftService = app(ShiftService::class);
        $cancelService = app(PosCancellationService::class);

        $shift = $shiftService->open($this->cashier, 50000);

        // Cash sale: 40,000
        $this->createTransaction($this->cashier, $shift, 'cash', 40000);

        // QRIS sale: 70,000
        $this->createTransaction($this->cashier, $shift, 'qris', 70000);

        // Debit sale: 60,000
        $this->createTransaction($this->cashier, $shift, 'debit', 60000);

        // Cancelled QRIS sale: 35,000
        $qrisCancel = $this->createTransaction($this->cashier, $shift, 'qris', 35000);
        $cancelService->cancel($qrisCancel, $this->cashier, 'QRIS cancel test', 'qris_manual');

        // Expected cash = 50,000 opening + 40,000 cash = 90,000 (QRIS and debit excluded)
        $recon = $shiftService->getCashReconciliation($shift);

        $this->assertSame(50000, $recon['opening_cash']);
        $this->assertSame(40000, $recon['cash_sales']);
        $this->assertSame(0, $recon['cancelled_cash_sales']);
        $this->assertSame(90000, $recon['expected_cash']);

        $shiftService->close($shift, 90000);
        $shift->refresh();

        $this->assertSame(0, $shift->cash_difference);
    }

    public function test_shift_closed_without_a_count_still_closes(): void
    {
        $shiftService = app(ShiftService::class);

        $shift = $shiftService->open($this->cashier, 25000);
        $this->createTransaction($this->cashier, $shift, 'cash', 50000);

        // Close without passing a count
        $shiftService->close($shift);
        $shift->refresh();

        $this->assertFalse($shift->isOpen());
        $this->assertNotNull($shift->closed_at);
        $this->assertNull($shift->closing_cash_counted);
        $this->assertNull($shift->cash_difference);
    }

    public function test_shift_opens_automatically_on_login_with_default_zero_opening_cash(): void
    {
        $response = $this->post(route('cashier.login.store'), [
            'email' => $this->cashier->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('cashier.pos'));

        $shiftService = app(ShiftService::class);
        $shift = $shiftService->current($this->cashier);

        $this->assertNotNull($shift);
        $this->assertTrue($shift->isOpen());
        $this->assertSame(0, $shift->opening_cash);
    }

    public function test_cashier_can_set_opening_cash_from_pos_api(): void
    {
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->open($this->cashier, 0);

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/shift/opening-cash', [
            'opening_cash' => 200000,
        ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Opening cash updated.',
            'opening_cash' => 200000,
        ]);

        $this->assertSame(200000, $shift->fresh()->opening_cash);
    }

    public function test_cashier_can_close_shift_via_history_post_route(): void
    {
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->open($this->cashier, 100000);
        $this->createTransaction($this->cashier, $shift, 'cash', 50000);

        $response = $this->actingAs($this->cashier)->post(route('cashier.shift.close'), [
            'closing_cash_counted' => 150000,
        ]);

        $response->assertRedirect(route('cashier.history'));

        $shift->refresh();
        $this->assertFalse($shift->isOpen());
        $this->assertSame(150000, $shift->closing_cash_counted);
        $this->assertSame(0, $shift->cash_difference);
    }

    public function test_owner_can_see_shifts_with_difference_in_reports(): void
    {
        $shiftService = app(ShiftService::class);

        // Shift 1: Exact match (difference = 0)
        $shift1 = $shiftService->open($this->cashier, 50000);
        $this->createTransaction($this->cashier, $shift1, 'cash', 50000);
        $shiftService->close($shift1, 100000);

        // Create a 2nd cashier for shift 2
        $cashier2 = User::factory()->create([
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);

        // Shift 2: Short by 20,000 (difference = -20000)
        $shift2 = $shiftService->open($cashier2, 100000);
        $this->createTransaction($cashier2, $shift2, 'cash', 50000);
        $shiftService->close($shift2, 130000); // Expected 150k, counted 130k -> diff = -20k

        $response = $this->actingAs($this->owner)->get(route('admin.reports', [
            'tab' => 'shifts',
            'difference_only' => 1,
        ]));

        $response->assertOk();
        $response->assertSee($cashier2->name);
        $response->assertSee('Short by Rp 20.000');
        $response->assertSee('Shortage');
        $response->assertDontSee($this->cashier->name);
        $response->assertDontSee('Balanced');
    }

    private function createTransaction(User $cashier, Shift $shift, string $method, int $amount): Transaction
    {
        $tx = Transaction::create([
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::COMPLETED,
            'total' => $amount,
            'created_on' => now(),
        ]);

        TransactionDetail::create([
            'transaction_id' => $tx->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => $amount,
            'subtotal' => $amount,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'payment_method' => $method,
            'payment_status' => 'settlement',
            'amount' => $amount,
            'payment_date' => now(),
        ]);

        return $tx;
    }
}
