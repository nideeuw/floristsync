<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockHistoryController extends Controller
{
    private const TYPES = ['in', 'out', 'cancel', 'adjustment'];

    public function index(Request $request): View
    {
        $stocks = Stock::with(['product', 'creator'])
            ->when($request->query('product'), fn($q, $v) => $q->where('product_id', $v))
            ->when($request->query('type'), fn($q, $v) => $q->where('change_type', $v))
            ->when($request->query('date_from'), fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->query('date_to'), fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->latest('date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.stock.history', [
            'stocks' => $stocks,
            'products' => Product::active()->orderBy('product_name')->get(['id', 'product_name']),
            'types' => self::TYPES,
        ]);
    }
}
