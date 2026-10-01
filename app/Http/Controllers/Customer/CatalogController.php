<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->query('sort', 'newest');

        $products = Product::with('category')
            ->available()
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where('product_name', 'like', "%{$v}%"))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('product_name'))
            ->when(! in_array($sort, ['price_asc', 'price_desc', 'name'], true), fn ($q) => $q->latest('id'))
            ->paginate(12)
            ->withQueryString();

        return view('customer.catalog', [
            'products' => $products,
            'categories' => Category::active()->orderBy('category_name')->get(['id', 'category_name']),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->status === Product::STATUS_AVAILABLE, 404);

        $product->load('category');

        return view('customer.show', [
            'product' => $product,
            'related' => Product::with('category')
                ->available()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->take(4)
                ->get(),
        ]);
    }
}
