<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ReportService $reports): View
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
