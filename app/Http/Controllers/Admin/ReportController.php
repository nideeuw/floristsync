<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports, InventoryService $inventory): View
    {
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        return view('admin.reports', [
            'days' => $days,
            'summary' => $reports->summary($from, $to),
            'topProducts' => $reports->topProducts($from, $to, 10),
            'lowStock' => $inventory->lowStock(),
        ]);
    }
}
