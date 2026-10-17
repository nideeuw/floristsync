<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::with('category')
            ->when($request->query('q'), fn($q, $v) => $q->where('product_name', 'like', "%{$v}%"))
            ->when($request->query('category'), fn($q, $v) => $q->where('category_id', $v))
            ->orderBy('stock')
            ->paginate(10)
            ->withQueryString();

        return view('admin.stock.index', [
            'products' => $products,
            'categories' => Category::orderBy('category_name')->get(['id', 'category_name']),
        ]);
    }
}
