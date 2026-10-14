<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderMilestoneService;
use Illuminate\View\View;

class OrderMilestoneController extends Controller
{
    public function __construct(private OrderMilestoneService $service) {}

    public function index(): View
    {
        return view('admin.orders.milestone', [
            'columns' => $this->service->board(),
        ]);
    }
}