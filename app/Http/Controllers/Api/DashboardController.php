<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Chart.js-ready: POS vs online revenue per day. */
    public function revenue(Request $request): JsonResponse
    {
        $days = min(max($request->integer('days', 7), 1), 90);
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = Transaction::successful()
            ->where('created_on', '>=', $start)
            ->selectRaw('DATE(created_on) as sales_date, transaction_type, SUM(total) as revenue')
            ->groupBy('sales_date', 'transaction_type')
            ->get();

        $labels = $pos = $online = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $labels[] = $date;
            $pos[] = (int) $rows->where('sales_date', $date)->where('transaction_type', Transaction::TYPE_POS)->sum('revenue');
            $online[] = (int) $rows->where('sales_date', $date)->where('transaction_type', Transaction::TYPE_ONLINE)->sum('revenue');
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['label' => 'POS Kiosk', 'data' => $pos],
                ['label' => 'Web E-Commerce', 'data' => $online],
            ],
            'total' => array_sum($pos) + array_sum($online),
        ]);
    }

    public function topCategories(): JsonResponse
    {
        $rows = DB::table('transaction_details as d')
            ->join('transactions as t', 't.id', '=', 'd.transaction_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNotIn('t.status', [Transaction::PENDING_PAYMENT, Transaction::CANCELLED])
            ->selectRaw('c.category_name as label, SUM(d.qty) as total_qty')
            ->groupBy('c.category_name')
            ->orderByDesc('total_qty')
            ->get();

        return response()->json([
            'labels' => $rows->pluck('label'),
            'data' => $rows->pluck('total_qty')->map(fn ($v) => (int) $v),
        ]);
    }

    public function lowStock(InventoryService $inventory): JsonResponse
    {
        return response()->json([
            'threshold' => config('floristsync.low_stock_threshold'),
            'data' => $inventory->lowStock(),
        ]);
    }
}
