<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRevenueTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reports;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(ReportService::class);
        $this->category = Category::factory()->create();
    }

    public function test_dashboard_revenue_equals_sum_of_product_revenue_for_the_same_period(): void
    {
        $a = $this->product('A', 100000);
        $b = $this->product('B', 50000);

        // POS sale: 1 x A, no shipping.
        $this->order(Transaction::TYPE_POS, Transaction::COMPLETED, [[$a, 1]], null,
            Carbon::parse('2026-05-10 10:00:00'), Carbon::parse('2026-05-10 10:00:00'));

        // Online delivery: 1 x A + 1 x B (150.000) + 20.000 shipping.
        $this->order(Transaction::TYPE_ONLINE, Transaction::PROCESSING, [[$a, 1], [$b, 1]], 20000,
            Carbon::parse('2026-05-10 20:00:00'), Carbon::parse('2026-05-10 20:05:00'));

        $from = Carbon::parse('2026-05-01');
        $to = Carbon::parse('2026-05-31 23:59:59');

        $summary = $this->reports->summary($from, $to);
        $viewSales = (int) DB::table('view_sales_daily')
            ->whereBetween('day', ['2026-05-01', '2026-05-31'])->sum('revenue');
        $viewProducts = (int) DB::table('view_product_sales')
            ->whereBetween('day', ['2026-05-01', '2026-05-31'])->sum('total_revenue');

        // Revenue never includes the shipping passed on to the courier...
        $this->assertSame(250000, $summary['revenue']);
        $this->assertSame(250000, $viewSales);
        $this->assertSame(250000, $viewProducts);

        // ...but the collected shipping is reported separately.
        $this->assertSame(20000, $summary['shipping']);
        $this->assertSame(20000, (int) DB::table('view_sales_daily')
            ->whereBetween('day', ['2026-05-01', '2026-05-31'])->sum('shipping'));

        $f = ['from' => $from, 'to' => $to, 'type' => null];
        $rows = $this->reports->query('sales', $f)->get();
        $this->assertSame(250000, (int) $rows->sum('revenue'));
        $this->assertSame(20000, (int) $rows->sum('shipping'));

        $cards = $this->reports->cards('sales', $f, $this->reports->query('sales', $f));
        $this->assertSame(rupiah(250000), $cards[0]['value']);
        $this->assertStringContainsString(rupiah(20000), (string) $cards[0]['hint']);
    }

    public function test_cancelled_and_pending_payment_orders_are_excluded(): void
    {
        $a = $this->product('A', 100000);

        $range = [Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31 23:59:59')];
        $days = ['2026-05-01', '2026-05-31'];

        $this->order(Transaction::TYPE_POS, Transaction::COMPLETED, [[$a, 1]], null,
            Carbon::parse('2026-05-10 10:00:00'), Carbon::parse('2026-05-10 10:00:00'));

        // Excluded even though its payment_date sits inside the range.
        $this->order(Transaction::TYPE_ONLINE, Transaction::CANCELLED, [[$a, 2]], 5000,
            Carbon::parse('2026-05-11 11:00:00'), Carbon::parse('2026-05-11 11:30:00'));

        // Excluded even though its payment_date sits inside the range.
        $this->order(Transaction::TYPE_ONLINE, Transaction::PENDING_PAYMENT, [[$a, 3]], 7000,
            Carbon::parse('2026-05-12 12:00:00'), Carbon::parse('2026-05-12 12:30:00'));

        $summary = $this->reports->summary($range[0], $range[1]);

        $this->assertSame(100000, $summary['revenue']);
        $this->assertSame(1, $summary['orders']);
        $this->assertSame(100000, (int) DB::table('view_sales_daily')
            ->whereBetween('day', $days)->sum('revenue'));
        $this->assertSame(100000, (int) DB::table('view_product_sales')
            ->whereBetween('day', $days)->sum('total_revenue'));
    }

    public function test_order_paid_at_2350_appears_on_the_right_day(): void
    {
        $a = $this->product('A', 100000);

        // Paid at 23:50 on the 15th: counts on the 15th.
        $this->order(Transaction::TYPE_ONLINE, Transaction::PROCESSING, [[$a, 1]], null,
            Carbon::parse('2026-01-15 23:50:00'), Carbon::parse('2026-01-15 23:50:00'));

        // Created late on the 16th but paid just after midnight: the payment
        // day (17th) wins over the creation day, and the Jakarta day wins
        // over a UTC grouping (00:10 Jakarta is still the 16th in UTC).
        $this->order(Transaction::TYPE_ONLINE, Transaction::PROCESSING, [[$a, 1]], null,
            Carbon::parse('2026-01-16 23:50:00'), Carbon::parse('2026-01-17 00:10:00'));

        $salesDays = DB::table('view_sales_daily')
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->pluck('revenue', 'day');

        $this->assertSame(100000, (int) $salesDays['2026-01-15']);
        $this->assertSame(100000, (int) $salesDays['2026-01-17']);
        $this->assertArrayNotHasKey('2026-01-16', $salesDays->toArray());

        $productDays = DB::table('view_product_sales')
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->pluck('total_revenue', 'day');

        $this->assertSame(100000, (int) $productDays['2026-01-15']);
        $this->assertSame(100000, (int) $productDays['2026-01-17']);
        $this->assertArrayNotHasKey('2026-01-16', $productDays->toArray());

        // The summary date filter follows the same report day.
        $this->assertSame(100000, $this->reports->summary(
            Carbon::parse('2026-01-15'), Carbon::parse('2026-01-15 23:59:59')
        )['revenue']);
        $this->assertSame(0, $this->reports->summary(
            Carbon::parse('2026-01-16'), Carbon::parse('2026-01-16 23:59:59')
        )['revenue']);
        $this->assertSame(100000, $this->reports->summary(
            Carbon::parse('2026-01-17'), Carbon::parse('2026-01-17 23:59:59')
        )['revenue']);
    }

    private function product(string $name, int $price): Product
    {
        return Product::factory()->create([
            'category_id' => $this->category->id,
            'product_name' => $name,
            'price' => $price,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    /** @param array<array{Product, int}> $items */
    private function order(
        string $type,
        string $status,
        array $items,
        ?int $shipping,
        Carbon $createdOn,
        ?Carbon $paidAt,
    ): Transaction {
        $subtotal = 0;
        foreach ($items as [$product, $qty]) {
            $subtotal += $product->price * $qty;
        }

        $transaction = Transaction::create([
            'transaction_type' => $type,
            'status' => $status,
            'shipping_cost' => $shipping,
            'total' => $subtotal + ($shipping ?? 0),
            'created_on' => $createdOn,
        ]);

        foreach ($items as [$product, $qty]) {
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_price' => $product->price,
                'subtotal' => $product->price * $qty,
            ]);
        }

        Payment::create([
            'transaction_id' => $transaction->id,
            'payment_method' => $type === Transaction::TYPE_POS ? 'cash' : 'gopay',
            'payment_status' => $status === Transaction::PENDING_PAYMENT ? 'pending' : 'settlement',
            'amount' => $transaction->total,
            'payment_date' => $paidAt,
        ]);

        return $transaction;
    }
}
