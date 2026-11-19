<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const TABS = [
        'sales' => 'Sales',
        'products' => 'Products',
        'stock' => 'Stock',
        'waste' => 'Waste',
        'cancellations' => 'Cancellations',
        'shifts' => 'Shifts',
    ];

    /** Numbers for the dashboard cards. */
    public function today(): array
    {
        $row = DB::table('view_sales_daily')
            ->where('day', now()->toDateString())
            ->selectRaw('COALESCE(SUM(orders), 0) as orders, COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(shipping), 0) as shipping')
            ->first();

        return [
            'revenue' => (int) $row->revenue,
            'shipping' => (int) $row->shipping,
            'orders' => (int) $row->orders,
            'to_process' => Transaction::where('status', Transaction::PROCESSING)->count(),
            'low_stock' => Product::active()->lowStock()->count(),
        ];
    }

    public function summary(Carbon $from, Carbon $to, ?string $type = null): array
    {
        $rows = DB::table('view_sales_daily')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->when($type, fn ($q, $v) => $q->where('transaction_type', $v))
            ->selectRaw('transaction_type, SUM(orders) as orders, SUM(revenue) as revenue, SUM(shipping) as shipping')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');

        $orders = (int) $rows->sum('orders');
        $revenue = (int) $rows->sum('revenue');

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'average' => $orders > 0 ? intdiv($revenue, $orders) : 0,
            'pos_revenue' => (int) ($rows->get(Transaction::TYPE_POS)?->revenue ?? 0),
            'online_revenue' => (int) ($rows->get(Transaction::TYPE_ONLINE)?->revenue ?? 0),
            'shipping' => (int) $rows->sum('shipping'),
        ];
    }

    public function topProducts(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return DB::table('view_product_sales')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('product_id as id, product_name, SUM(total_qty) as total_qty, SUM(total_revenue) as total_revenue')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();
    }

    public function recentOnlineOrders(int $limit = 6): Collection
    {
        return Transaction::with('payment', 'user:id,name')
            ->where('transaction_type', Transaction::TYPE_ONLINE)
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function cancelledCount(Carbon $from, Carbon $to): int
    {
        return Transaction::where('status', Transaction::CANCELLED)
            ->whereBetween('created_on', [$from, $to])
            ->count();
    }

    public function paymentMethodShare(Carbon $from, Carbon $to): Collection
    {
        return Transaction::successful()
            ->whereBetween(DB::raw($this->reportDaySql('transactions')), [$from, $to])
            ->with('payment')
            ->get()
            ->groupBy(fn (Transaction $transaction) => strtoupper(str_replace('_', ' ', $transaction->payment?->payment_method ?? 'unknown')))
            ->map(fn (Collection $rows, string $label) => [
                'label' => $label,
                'orders' => $rows->count(),
                'revenue' => (int) $rows->sum(fn (Transaction $transaction) => (int) $transaction->total - (int) ($transaction->shipping_cost ?? 0)),
            ])
            ->sortByDesc('orders')
            ->values();
    }

    /**
     * The day a transaction counts on in reports — online orders use the
     * latest payment's payment_date, POS uses created_on. Must stay in sync
     * with the same expression inside view_sales_daily / view_product_sales
     * (migration 2026_11_19_000002). $alias is the transactions table alias.
     *
     * Timestamps are `timestamp without time zone` written in the app
     * timezone (Asia/Jakarta), so DATE() already groups on the Jakarta day.
     */
    private function reportDaySql(string $alias): string
    {
        return "CASE WHEN {$alias}.transaction_type = 'online'
            THEN COALESCE((
                SELECT pm.payment_date FROM payments pm
                WHERE pm.transaction_id = {$alias}.id
                ORDER BY pm.id DESC LIMIT 1
            ), {$alias}.created_on)
            ELSE {$alias}.created_on END";
    }

    public function ownerDashboard(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();
        $summary = $this->summary($from, $to);

        return [
            'days' => $days,
            'cancelled' => $this->cancelledCount($from, $to),
            'shiftsWithDifference' => DB::table('shifts')
                ->whereBetween('opened_at', [$from, $to])
                ->whereNotNull('cash_difference')
                ->where('cash_difference', '!=', 0)
                ->count(),
            'channels' => [
                'labels' => ['POS Kiosk', 'Web E-Commerce'],
                'data' => [$summary['pos_revenue'], $summary['online_revenue']],
            ],
            'paymentMethods' => $this->paymentMethodShare($from, $to),
            'topProducts' => $this->topProducts($from, $to, 5),
            'recentOrders' => $this->recentOnlineOrders(),
        ];
    }

    // ------------------------------------------------------------------
    // Reports page (tabs). Filters: from, to (Carbon), type, category, low.
    // ------------------------------------------------------------------

    public static function columns(string $tab): array
    {
        return match ($tab) {
            'products' => [
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'category_name', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'total_qty', 'label' => 'Sold', 'type' => 'int'],
                ['key' => 'total_revenue', 'label' => 'Revenue', 'type' => 'money'],
            ],
            'stock' => [
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'category_name', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'stock', 'label' => 'Stock', 'type' => 'int'],
                ['key' => 'stock_status', 'label' => 'Status', 'type' => 'text'],
            ],
            'waste' => [
                ['key' => 'month', 'label' => 'Month', 'type' => 'month'],
                ['key' => 'product_name', 'label' => 'Product', 'type' => 'text'],
                ['key' => 'category_name', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'quantity_lost', 'label' => 'Units Lost', 'type' => 'int'],
                ['key' => 'value_lost', 'label' => 'Value Lost', 'type' => 'money'],
            ],
            'cancellations' => [
                ['key' => 'order_number', 'label' => 'Order', 'type' => 'text'],
                ['key' => 'transaction_type', 'label' => 'Type', 'type' => 'channel'],
                ['key' => 'cancel_reason', 'label' => 'Reason', 'type' => 'text'],
                ['key' => 'cancelled_by_name', 'label' => 'Cancelled by', 'type' => 'text'],
                ['key' => 'cancelled_time', 'label' => 'Cancelled at', 'type' => 'datetime'],
                ['key' => 'total', 'label' => 'Value', 'type' => 'money'],
            ],
            'shifts' => [
                ['key' => 'id', 'label' => 'Shift #', 'type' => 'int'],
                ['key' => 'cashier_name', 'label' => 'Cashier', 'type' => 'text'],
                ['key' => 'opened_at', 'label' => 'Opened', 'type' => 'datetime'],
                ['key' => 'closed_at', 'label' => 'Closed', 'type' => 'datetime'],
                ['key' => 'opening_cash', 'label' => 'Opening', 'type' => 'money'],
                ['key' => 'closing_cash_counted', 'label' => 'Counted', 'type' => 'money'],
                ['key' => 'cash_difference', 'label' => 'Difference', 'type' => 'difference'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
            ],
            default => [
                ['key' => 'day', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'orders', 'label' => 'Orders', 'type' => 'int'],
                ['key' => 'pos_revenue', 'label' => 'POS', 'type' => 'money'],
                ['key' => 'online_revenue', 'label' => 'Online', 'type' => 'money'],
                ['key' => 'shipping', 'label' => 'Shipping', 'type' => 'money'],
                ['key' => 'revenue', 'label' => 'Total', 'type' => 'money'],
            ],
        };
    }

    public static function format(string $type, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($type) {
            'money' => rupiah((int) $value),
            'difference' => $value === null ? '-' : ((int) $value === 0 ? 'Exact' : ((int) $value < 0 ? 'Short by '.rupiah(abs((int) $value)) : 'Over by '.rupiah((int) $value))),
            'int' => number_format((int) $value, 0, ',', '.'),
            'month' => Carbon::parse($value)->format('M Y'),
            'date' => Carbon::parse($value)->format('d M Y'),
            'datetime' => Carbon::parse($value)->format('d M Y, H:i'),
            'channel' => $value === Transaction::TYPE_POS ? 'POS' : 'Online',
            default => (string) $value,
        };
    }

    public function query(string $tab, array $f): QueryBuilder
    {
        return match ($tab) {
            'products' => $this->productsQuery($f),
            'stock' => $this->stockQuery($f),
            'waste' => $this->wasteQuery($f),
            'cancellations' => $this->cancellationsQuery($f),
            'shifts' => $this->shiftsQuery($f),
            default => $this->salesQuery($f),
        };
    }

    public function cards(string $tab, array $f, QueryBuilder $query): array
    {
        $count = fn ($n) => number_format((int) $n, 0, ',', '.');

        if ($tab === 'shifts') {
            $a = $this->aggregate($query, 'COUNT(*) as n, '
                .'COALESCE(SUM(CASE WHEN cash_difference IS NOT NULL AND cash_difference != 0 THEN 1 ELSE 0 END), 0) as with_diff, '
                .'COALESCE(SUM(CASE WHEN cash_difference < 0 THEN ABS(cash_difference) ELSE 0 END), 0) as total_shortage, '
                .'COALESCE(SUM(CASE WHEN cash_difference > 0 THEN cash_difference ELSE 0 END), 0) as total_overage');

            return [
                ['label' => 'Total shifts', 'value' => $count($a->n), 'hint' => null],
                ['label' => 'Shifts with difference', 'value' => $count($a->with_diff), 'hint' => 'Cash discrepancy recorded'],
                ['label' => 'Total shortage', 'value' => rupiah((int) $a->total_shortage), 'hint' => 'Drawer cash short'],
                ['label' => 'Total overage', 'value' => rupiah((int) $a->total_overage), 'hint' => 'Drawer cash over'],
            ];
        }

        if ($tab === 'products') {
            $a = $this->aggregate($query, 'COUNT(*) as n, COALESCE(SUM(total_qty), 0) as qty, COALESCE(SUM(total_revenue), 0) as revenue');

            return [
                ['label' => 'Products sold', 'value' => $count($a->n), 'hint' => null],
                ['label' => 'Units sold', 'value' => $count($a->qty), 'hint' => null],
                ['label' => 'Revenue', 'value' => rupiah((int) $a->revenue), 'hint' => null],
            ];
        }

        if ($tab === 'stock') {
            $a = $this->aggregate($query, "COUNT(*) as n, COALESCE(SUM(CASE WHEN stock_status = 'Low stock' THEN 1 ELSE 0 END), 0) as low, COALESCE(SUM(CASE WHEN stock_status = 'Out of stock' THEN 1 ELSE 0 END), 0) as out_of_stock");

            return [
                ['label' => 'Products', 'value' => $count($a->n), 'hint' => null],
                ['label' => 'Low stock', 'value' => $count($a->low), 'hint' => 'At or below '.(int) config('floristsync.low_stock_threshold')],
                ['label' => 'Out of stock', 'value' => $count($a->out_of_stock), 'hint' => null],
            ];
        }

        if ($tab === 'waste') {
            $a = $this->aggregate($query, 'COUNT(*) as n, COALESCE(SUM(quantity_lost), 0) as qty, COALESCE(SUM(value_lost), 0) as val');

            return [
                ['label' => 'Products with waste', 'value' => $count($a->n), 'hint' => null],
                ['label' => 'Units lost', 'value' => $count($a->qty), 'hint' => 'Wilted and damaged'],
                ['label' => 'Total value lost', 'value' => rupiah((int) $a->val), 'hint' => null],
            ];
        }

        if ($tab === 'cancellations') {
            $a = $this->aggregate($query, 'COUNT(*) as n, COALESCE(SUM(total), 0) as value');

            return [
                ['label' => 'Cancelled orders', 'value' => $count($a->n), 'hint' => null],
                ['label' => 'Cancelled value', 'value' => rupiah((int) $a->value), 'hint' => null],
            ];
        }

        $s = $this->summary($f['from'], $f['to'], $f['type'] ?? null);

        return [
            ['label' => 'Revenue', 'value' => rupiah($s['revenue']), 'hint' => 'Shipping collected: '.rupiah($s['shipping'])],
            ['label' => 'Orders', 'value' => $count($s['orders']), 'hint' => null],
            ['label' => 'Average order value', 'value' => rupiah($s['average']), 'hint' => null],
            ['label' => 'POS vs online', 'value' => rupiah($s['pos_revenue']), 'hint' => 'Online: '.rupiah($s['online_revenue'])],
        ];
    }

    private function aggregate(QueryBuilder $query, string $select): object
    {
        return DB::query()
            ->fromSub((clone $query)->reorder(), 'x')
            ->selectRaw($select)
            ->first();
    }

    /** Source: view_sales_daily */
    private function salesQuery(array $f): QueryBuilder
    {
        return DB::table('view_sales_daily')
            ->whereBetween('day', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('transaction_type', $v))
            ->selectRaw(
                'day, SUM(orders) as orders, '
                .'SUM(CASE WHEN transaction_type = ? THEN revenue ELSE 0 END) as pos_revenue, '
                .'SUM(CASE WHEN transaction_type = ? THEN revenue ELSE 0 END) as online_revenue, '
                .'SUM(shipping) as shipping, '
                .'SUM(revenue) as revenue',
                [Transaction::TYPE_POS, Transaction::TYPE_ONLINE]
            )
            ->groupBy('day')
            ->orderByDesc('day');
    }

    /** Source: view_product_sales */
    private function productsQuery(array $f): QueryBuilder
    {
        return DB::table('view_product_sales')
            ->whereBetween('day', [$f['from']->toDateString(), $f['to']->toDateString()])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('transaction_type', $v))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->selectRaw('product_id as id, product_name, category_name, SUM(total_qty) as total_qty, SUM(total_revenue) as total_revenue')
            ->groupBy('product_id', 'product_name', 'category_name')
            ->orderByDesc('total_qty')
            ->orderBy('product_name');
    }

    /** Source: view_stock_report */
    private function stockQuery(array $f): QueryBuilder
    {
        $threshold = (int) config('floristsync.low_stock_threshold');

        return DB::table('view_stock_report')
            ->where('is_active', true)
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(! empty($f['low']), fn ($q) => $q->where('stock', '<=', $threshold))
            ->selectRaw("product_id as id, product_name, category_name, stock, CASE WHEN stock <= 0 THEN 'Out of stock' WHEN stock <= {$threshold} THEN 'Low stock' ELSE 'In stock' END as stock_status")
            ->orderBy('stock')
            ->orderBy('product_name');
    }

    public function waste(Carbon $from, Carbon $to, ?int $categoryId = null): Collection
    {
        return $this->wasteQuery(['from' => $from, 'to' => $to, 'category' => $categoryId])->get();
    }

    /** Source: view_stock_waste */
    public function wasteQuery(array $f): QueryBuilder
    {
        $fromMonth = isset($f['from']) ? $f['from']->format('Y-m-01') : null;
        $toMonth = isset($f['to']) ? $f['to']->endOfMonth()->format('Y-m-d') : null;

        return DB::table('view_stock_waste')
            ->when($fromMonth && $toMonth, fn ($q) => $q->whereBetween('month', [$fromMonth, $toMonth]))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->select('month', 'product_id as id', 'product_name', 'category_name', 'quantity_lost', 'value_lost')
            ->orderByDesc('month')
            ->orderByDesc('value_lost')
            ->orderBy('product_name');
    }

    /** Source: view_cancellation */
    private function cancellationsQuery(array $f): QueryBuilder
    {
        return DB::table('view_cancellation')
            ->whereBetween('cancelled_time', [$f['from'], $f['to']])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('transaction_type', $v))
            ->select('id', 'order_number', 'transaction_type', 'cancel_reason', 'cancelled_by_name', 'cancelled_time', 'total')
            ->orderByDesc('cancelled_time');
    }

    /** Source: shifts table joined with users */
    private function shiftsQuery(array $f): QueryBuilder
    {
        return DB::table('shifts as s')
            ->join('users as u', 'u.id', '=', 's.cashier_id')
            ->whereBetween('s.opened_at', [$f['from'], $f['to']])
            ->when(! empty($f['difference_only']), function ($q) {
                $q->whereNotNull('s.cash_difference')
                    ->where('s.cash_difference', '!=', 0);
            })
            ->selectRaw(
                's.id, u.name as cashier_name, s.opened_at, s.closed_at, '
                .'s.opening_cash, s.closing_cash_counted, s.cash_difference, '
                .'CASE '
                ."  WHEN s.closed_at IS NULL THEN 'Open' "
                ."  WHEN s.cash_difference IS NULL THEN 'Closed (uncounted)' "
                ."  WHEN s.cash_difference = 0 THEN 'Balanced' "
                ."  WHEN s.cash_difference < 0 THEN 'Shortage' "
                ."  ELSE 'Overage' "
                .'END as status'
            )
            ->orderByDesc('s.opened_at');
    }
}
