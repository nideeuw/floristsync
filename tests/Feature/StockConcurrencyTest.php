<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\MidtransService;
use App\Services\PosCancellationService;
use App\Services\TransactionService;
use Database\Factories\ProductFactory;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Support\InteractsWithSeededRoles;
use Tests\Support\InteractsWithTwoPostgresConnections;
use Tests\TestCase;

/**
 * M4: interleavings between two buyers simulated with sequential calls on two
 * PostgreSQL connections (PHPUnit cannot run true parallel requests).
 * Uses DatabaseMigrations so committed data is visible to both connections.
 */
class StockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithSeededRoles;
    use InteractsWithTwoPostgresConnections;

    private User $member;

    private User $otherMember;

    private User $cashier;

    private Product $product;

    private MockInterface $midtrans;

    protected function setUp(): void
    {
        $this->skipUnlessPostgres();

        // Before parent::setUp() so the held-transaction cleanup runs before
        // DatabaseMigrations' migrate:rollback (callbacks are FIFO).
        $this->registerSecondaryCleanup();

        parent::setUp();

        $this->twoConnectionsSetUp();

        $this->seedRolesAndMenus();

        $this->member = $this->userForRole(Role::MEMBER);
        $this->otherMember = $this->userForRole(Role::MEMBER);
        $this->cashier = $this->userForRole(Role::CASHIER);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Sunflower Bouquet',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->midtrans = $this->mock(MidtransService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
        });
    }

    // --- The last item, bought online and at POS at the same time. ---

    public function test_last_item_sold_at_pos_then_online_one_winner_clean(): void
    {
        ProductFactory::setStock($this->product, 1);

        // Connection A: the POS sale takes the last item and commits.
        $this->onSecondary(fn () => $this->posSale(1));

        $this->assertSame(0, $this->viewStock());

        // Connection B: the online buyer is refused cleanly, not served twice.
        try {
            $this->createPendingOnlineOrder(1);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, $this->viewStock());
        $this->assertStockInvariants();
    }

    public function test_last_item_race_is_blocked_while_the_pos_sale_holds_the_lock(): void
    {
        ProductFactory::setStock($this->product, 1);

        // Connection A holds an uncommitted POS sale: the product row is
        // locked and the stock reduction is not visible yet.
        $this->holdSecondary(fn () => $this->posSale(1));

        // Connection B tries to buy the same last item while those locks are held.
        $this->setLockTimeout(400);

        try {
            $this->createPendingOnlineOrder(1);
            $this->fail('The online sale was not blocked by the held POS locks.');
        } catch (QueryException $e) {
            // Waiting on the product row lock timed out: the two sales can
            // never run at the same time.
        } finally {
            $this->setLockTimeout(null);
        }

        // Nothing from the blocked attempt was committed.
        $this->assertSame(0, Transaction::count());

        $this->commitSecondary();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, $this->viewStock());

        // Once the winner committed, the loser fails cleanly with no side effects.
        try {
            $this->createPendingOnlineOrder(1);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, $this->viewStock());
        $this->assertStockInvariants();
    }

    // --- The same member double-clicking pay. ---

    public function test_double_clicked_checkout_creates_one_order_with_one_stock_decrease(): void
    {
        ProductFactory::setStock($this->product, 2);

        $this->addToCart(2);
        $this->checkout()->assertStatus(303);

        $this->assertSame(0, $this->viewStock());

        // The second click racing from another connection finds the stock gone.
        $this->onSecondary(function (): void {
            try {
                $this->createPendingOnlineOrder(2);
                $this->fail('Expected DomainException was not thrown.');
            } catch (DomainException $e) {
                $this->assertStringContainsString('Insufficient stock', $e->getMessage());
            }
        });

        // The browser's own second POST finds the cart already emptied.
        $this->checkout()->assertRedirect(route('cart'));

        $this->assertSame(1, Transaction::where('user_id', $this->member->id)->count());
        $this->assertSame(0, $this->viewStock());

        $out = Stock::where('product_id', $this->product->id)
            ->where('change_type', 'out')
            ->get();

        $this->assertCount(1, $out);
        $this->assertSame(2, -(int) $out->first()->quantity_change);

        $this->assertStockInvariants();
    }

    public function test_double_clicking_pay_now_does_not_touch_stock_again(): void
    {
        ProductFactory::setStock($this->product, 10);

        $transaction = $this->createPendingOnlineOrder(2);

        $this->assertSame(8, $this->viewStock());
        $outBefore = $this->ledgerSum('out');

        $this->midtrans->shouldReceive('getStatus')->andReturn([
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ]);

        // First click, processed on a second connection: order is paid.
        $this->onSecondary(fn () => app(TransactionService::class)->resumePayment($transaction->refresh()));

        $transaction->refresh();

        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertNotNull($transaction->payment->payment_date);

        // Paying never changes stock: it was only taken when the order was created.
        $this->assertSame(8, $this->viewStock());
        $this->assertSame($outBefore, $this->ledgerSum('out'));

        // Second click of the same Pay button: refused, stock still untouched.
        try {
            app(TransactionService::class)->resumePayment($transaction->refresh());
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no longer be paid', $e->getMessage());
        }

        $this->assertSame(1, Transaction::count());
        $this->assertSame(8, $this->viewStock());
        $this->assertSame($outBefore, $this->ledgerSum('out'));
        $this->assertStockInvariants();
    }

    // --- A cart item that changed after it was added. ---

    public function test_cart_item_deactivated_after_being_added_is_rejected(): void
    {
        ProductFactory::setStock($this->product, 10);

        $this->addToCart(1);

        $this->product->update(['is_active' => false]);

        $this->checkout()->assertRedirect(route('cart'))->assertSessionHas('error');

        $this->assertSame(0, Transaction::where('user_id', $this->member->id)->count());
        $this->assertSame(10, $this->viewStock());
        $this->assertSame(0, $this->ledgerSum('out'));
        $this->assertStockInvariants();
    }

    public function test_cart_item_sold_out_after_being_added_is_rejected(): void
    {
        ProductFactory::setStock($this->product, 5);

        $this->addToCart(3);

        // Another buyer on a second connection takes every remaining item.
        $this->onSecondary(fn () => $this->createPendingOnlineOrder(5, $this->otherMember));

        $this->assertSame(0, $this->viewStock());

        // The member's checkout is refused cleanly.
        $this->checkout()->assertRedirect(route('cart'))->assertSessionHas('error');

        // A stale read that still had the old quantity is refused at the stock check.
        try {
            $this->createPendingOnlineOrder(3);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(0, Transaction::where('user_id', $this->member->id)->count());
        $this->assertSame(0, $this->viewStock());
        $this->assertSame(-5, $this->ledgerSum('out'));
        $this->assertStockInvariants();
    }

    // --- A late webhook after the order already expired. ---

    public function test_late_settlement_webhook_after_expire_returns_stock_only_once(): void
    {
        $admin = $this->userForRole(Role::ADMIN_ORDER);

        ProductFactory::setStock($this->product, 10);

        $transaction = $this->createPendingOnlineOrder(2);

        $payload = fn (string $status): array => [
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => $status,
            'gross_amount' => (string) $transaction->payment->amount,
        ];

        // The order expires first: the stock comes back exactly once.
        app(TransactionService::class)->updatePaymentStatus($payload('expire'));

        $this->assertSame(Transaction::CANCELLED, $transaction->refresh()->status);
        $this->assertSame(10, $this->viewStock());
        $this->assertSame(1, $this->cancelLedgerCount());

        // The payment then settles late, processed on a second connection.
        $this->midtrans->shouldReceive('getStatus')->andReturn([
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ]);
        $this->midtrans->shouldReceive('refundTransaction')->andReturn([
            'status_code' => '200',
            'transaction_status' => 'refund',
        ]);

        $this->onSecondary(fn () => app(TransactionService::class)->updatePaymentStatus(array_merge(
            $payload('settlement'),
            ['fraud_status' => null, 'payment_type' => 'gopay'],
        )));

        $transaction->refresh();

        // The stock was returned by the expire and never again by the late payment.
        $this->assertSame(Transaction::CANCELLED, $transaction->status);
        $this->assertSame(10, $this->viewStock());
        $this->assertSame(1, $this->cancelLedgerCount());
        $this->assertSame(-2, $this->ledgerSum('out'));
        $this->assertSame('refund', $transaction->payment->payment_status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Late payment refunded',
        ]);

        $this->assertStockInvariants();
    }

    // --- Cancelling a POS sale that was already cancelled. ---

    public function test_pos_sale_cancelled_twice_returns_stock_only_once(): void
    {
        ProductFactory::setStock($this->product, 10);

        $transaction = $this->posSale(3);

        $this->assertSame(7, $this->viewStock());

        // First cancel runs on a second connection and commits.
        $this->onSecondary(fn () => app(PosCancellationService::class)
            ->cancel($transaction, $this->cashier, 'Customer returned items', 'cash'));

        $this->assertSame(10, $this->viewStock());
        $this->assertSame(1, $this->cancelLedgerCount());

        // The second cancel carries the stale pre-cancel instance: refused,
        // and the stock is not returned a second time.
        try {
            app(PosCancellationService::class)->cancel($transaction, $this->cashier, 'Again', 'cash');
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been cancelled', $e->getMessage());
        }

        $this->assertSame(Transaction::CANCELLED, $transaction->refresh()->status);
        $this->assertSame(10, $this->viewStock());
        $this->assertSame(1, $this->cancelLedgerCount());
        $this->assertStockInvariants();
    }

    // --- Stock never goes below zero. ---

    public function test_stock_never_goes_below_zero_when_operations_interleave(): void
    {
        ProductFactory::setStock($this->product, 3);

        // Connection A: a POS sale takes two of the three items.
        $this->onSecondary(fn () => $this->posSale(2));

        $this->assertSame(1, $this->viewStock());
        $this->assertStockInvariants();

        // An adjustment that would push the stock below zero is refused.
        try {
            app(InventoryService::class)->adjust($this->product, -5, 'Too much');
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot go below zero', $e->getMessage());
        }

        $this->assertSame(1, $this->viewStock());

        // An online sale for more than is left is refused.
        try {
            $this->createPendingOnlineOrder(2);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(1, $this->viewStock());

        // The last item is sold from the other connection.
        $this->onSecondary(fn () => $this->createPendingOnlineOrder(1));

        $this->assertSame(0, $this->viewStock());
        $this->assertStockInvariants();

        // Once at zero, every further decrease is refused.
        try {
            $this->createPendingOnlineOrder(1);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        try {
            app(InventoryService::class)->adjust($this->product, -1, 'Below zero');
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cannot go below zero', $e->getMessage());
        }

        $this->assertSame(0, $this->viewStock());
        $this->assertSame(0, $this->ledgerSum());
        $this->assertSame(2, Transaction::count());
        $this->assertStockInvariants();
    }

    // --- Helpers ---

    private function posSale(int $qty): Transaction
    {
        return app(TransactionService::class)->createPosTransaction([
            ['product_id' => $this->product->id, 'qty' => $qty],
        ], $this->cashier->id, 'cash', $this->product->price * $qty);
    }

    private function createPendingOnlineOrder(int $qty, ?User $user = null): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => $qty]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            ($user ?? $this->member)->id,
        );
    }

    private function addToCart(int $qty): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => $qty])
            ->assertRedirect();
    }

    private function checkout(): TestResponse
    {
        return $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
        ]);
    }

    private function viewStock(): int
    {
        return (int) $this->product->fresh()->stock;
    }

    private function ledgerSum(?string $changeType = null): int
    {
        $query = Stock::where('product_id', $this->product->id);

        if ($changeType !== null) {
            $query->where('change_type', $changeType);
        }

        return (int) $query->sum('quantity_change');
    }

    private function cancelLedgerCount(): int
    {
        return Stock::where('product_id', $this->product->id)
            ->where('change_type', 'cancel')
            ->count();
    }

    private function assertStockInvariants(): void
    {
        $view = $this->viewStock();

        $this->assertGreaterThanOrEqual(0, $view, 'Stock went below zero.');
        $this->assertSame($view, $this->ledgerSum(), 'view_stock does not match the stock ledger.');
    }
}
