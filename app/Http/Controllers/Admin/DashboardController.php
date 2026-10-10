<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Transaction;
use App\Services\DashboardService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports, DashboardService $dashboard): View
    {
        $user = $request->user();

        return match (true) {
            $user->hasRole(Role::OWNER) => $this->owner($reports),
            $user->hasRole(Role::ADMIN_ORDER) => view('admin.dashboards.order', $dashboard->orderSummary()),
            default => view('admin.dashboards.inventory', $dashboard->inventorySummary()),
        };
    }

    private function owner(ReportService $reports): View
    {
        return view('admin.dashboard', [
            'today' => $reports->today(),
            'recentOrders' => Transaction::with('payment', 'user:id,name')
                ->where('transaction_type', Transaction::TYPE_ONLINE)
                ->latest('id')
                ->take(6)
                ->get(),
        ]);
    }
}
