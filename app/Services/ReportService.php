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
        'cancellations' => 'Cancellations',
    ];

    /** Numbers for the dashboard cards. */
    public function today(): array
    {
        $row = Transaction::successful()
            ->where('created_on', '>=', now()->startOfDay())
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        return [
            'revenue' => (int) $row->revenue,
            'orders' => (int) $row->orders,
            'to_process' => Transaction::where('status', Transaction::PROCESSING)->count(),
            'low_stock' => Product::active()->lowStock()->count(),
        ];
    }

    public function summary(Carbon $from, Carbon $to, ?string $type = null): array
    {
        $rows = Transaction::successful()
            ->whereBetween('created_on', [$from, $to])
            ->when($type, fn ($q, $v) => $q->where('transaction_type', $v))
            ->selectRaw('transaction_type, COUNT(*) as orders, SUM(total) as revenue')
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
        ];
    }

    public function topProducts(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return DB::table('transaction_details as d')
            ->join('transactions as t', 't.id', '=', 'd.transaction_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED])
            ->whereBetween('t.created_on', [$from, $to])
            ->selectRaw('p.id, p.product_name, SUM(d.qty) as total_qty, SUM(d.subtotal) as total_revenue')
            ->groupBy('p.id', 'p.product_name')
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
            ->whereBetween('created_on', [$from, $to])
            ->with('payment')
            ->get()
            ->groupBy(fn (Transaction $transaction) => strtoupper(str_replace('_', ' ', $transaction->payment?->payment_method ?? 'unknown')))
            ->map(fn (Collection $rows, string $label) => [
                'label' => $label,
                'orders' => $rows->count(),
                'revenue' => (int) $rows->sum('total'),
            ])
            ->sortByDesc('orders')
            ->values();
    }

    public function ownerDashboard(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();
        $summary = $this->summary($from, $to);

        return [
            'days' => $days,
            'cancelled' => $this->cancelledCount($from, $to),
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
            'cancellations' => [
                ['key' => 'order_number', 'label' => 'Order', 'type' => 'text'],
                ['key' => 'transaction_type', 'label' => 'Type', 'type' => 'channel'],
                ['key' => 'cancel_reason', 'label' => 'Reason', 'type' => 'text'],
                ['key' => 'cancelled_by_name', 'label' => 'Cancelled by', 'type' => 'text'],
                ['key' => 'cancelled_time', 'label' => 'Cancelled at', 'type' => 'datetime'],
                ['key' => 'total', 'label' => 'Value', 'type' => 'money'],
            ],
            default => [
                ['key' => 'day', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'orders', 'label' => 'Orders', 'type' => 'int'],
                ['key' => 'pos_revenue', 'label' => 'POS', 'type' => 'money'],
                ['key' => 'online_revenue', 'label' => 'Online', 'type' => 'money'],
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
            'int' => number_format((int) $value, 0, ',', '.'),
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
            'cancellations' => $this->cancellationsQuery($f),
            default => $this->salesQuery($f),
        };
    }

    public function cards(string $tab, array $f, QueryBuilder $query): array
    {
        $count = fn ($n) => number_format((int) $n, 0, ',', '.');

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
                ['label' => 'Low stock', 'value' => $count($a->low), 'hint' => 'At or below ' . (int) config('floristsync.low_stock_threshold')],
                ['label' => 'Out of stock', 'value' => $count($a->out_of_stock), 'hint' => null],
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
            ['label' => 'Revenue', 'value' => rupiah($s['revenue']), 'hint' => null],
            ['label' => 'Orders', 'value' => $count($s['orders']), 'hint' => null],
            ['label' => 'Average order value', 'value' => rupiah($s['average']), 'hint' => null],
            ['label' => 'POS vs online', 'value' => rupiah($s['pos_revenue']), 'hint' => 'Online: ' . rupiah($s['online_revenue'])],
        ];
    }

    private function aggregate(QueryBuilder $query, string $select): object
    {
        return DB::query()
            ->fromSub((clone $query)->reorder(), 'x')
            ->selectRaw($select)
            ->first();
    }

    private function salesQuery(array $f): QueryBuilder
    {
        $pos = Transaction::TYPE_POS;
        $online = Transaction::TYPE_ONLINE;

        return DB::table('transactions as t')
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED])
            ->whereBetween('t.created_on', [$f['from'], $f['to']])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('t.transaction_type', $v))
            ->selectRaw("DATE(t.created_on) as day, COUNT(*) as orders, COALESCE(SUM(CASE WHEN t.transaction_type = '{$pos}' THEN t.total ELSE 0 END), 0) as pos_revenue, COALESCE(SUM(CASE WHEN t.transaction_type = '{$online}' THEN t.total ELSE 0 END), 0) as online_revenue, COALESCE(SUM(t.total), 0) as revenue")
            ->groupBy('day')
            ->orderByDesc('day');
    }

    private function productsQuery(array $f): QueryBuilder
    {
        return DB::table('transaction_details as d')
            ->join('transactions as t', 't.id', '=', 'd.transaction_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED])
            ->whereBetween('t.created_on', [$f['from'], $f['to']])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('t.transaction_type', $v))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('p.category_id', $v))
            ->selectRaw('p.id, p.product_name, c.category_name, SUM(d.qty) as total_qty, SUM(d.subtotal) as total_revenue')
            ->groupBy('p.id', 'p.product_name', 'c.category_name')
            ->orderByDesc('total_qty')
            ->orderBy('p.product_name');
    }

    private function stockQuery(array $f): QueryBuilder
    {
        $threshold = (int) config('floristsync.low_stock_threshold');

        return DB::table('products as p')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_active', true)
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('p.category_id', $v))
            ->when(! empty($f['low']), fn ($q) => $q->where('p.stock', '<=', $threshold))
            ->selectRaw("p.id, p.product_name, c.category_name, p.stock, CASE WHEN p.stock <= 0 THEN 'Out of stock' WHEN p.stock <= {$threshold} THEN 'Low stock' ELSE 'In stock' END as stock_status")
            ->orderBy('p.stock')
            ->orderBy('p.product_name');
    }

    private function cancellationsQuery(array $f): QueryBuilder
    {
        return DB::table('transactions as t')
            ->leftJoin('users as u', 'u.id', '=', 't.cancelled_by')
            ->where('t.status', Transaction::CANCELLED)
            ->whereRaw('COALESCE(t.cancelled_at, t.created_on) BETWEEN ? AND ?', [$f['from'], $f['to']])
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('t.transaction_type', $v))
            ->selectRaw("t.id, COALESCE((SELECT pm.midtrans_order_id FROM payments pm WHERE pm.transaction_id = t.id ORDER BY pm.id DESC LIMIT 1), CONCAT('POS-', LPAD(t.id, 6, '0'))) as order_number, t.transaction_type, t.cancel_reason, u.name as cancelled_by_name, COALESCE(t.cancelled_at, t.created_on) as cancelled_time, t.total")
            ->orderByDesc('cancelled_time');
    }
}
