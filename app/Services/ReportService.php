<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
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

    public function summary(Carbon $from, Carbon $to): array
    {
        $rows = Transaction::successful()
            ->whereBetween('created_on', [$from, $to])
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
}
