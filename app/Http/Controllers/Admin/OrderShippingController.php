<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderShippingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderShippingController extends Controller
{
    public function __construct(private OrderShippingService $service) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.orders.shipping', [
            'orders'  => $this->service->paginate($status),
            'counts'  => $this->service->counts(),
            'current' => in_array($status, OrderShippingService::STATUSES, true) ? $status : '',
        ]);
    }
}