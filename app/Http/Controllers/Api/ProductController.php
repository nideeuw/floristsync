<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category:id,category_name')
            ->available()
            ->when($request->query('category_id'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where('product_name', 'like', "%{$v}%"))
            ->orderBy('product_name')
            ->paginate(min($request->integer('per_page', 12), 50));

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active && $product->status === Product::STATUS_AVAILABLE, 404);

        return response()->json(['data' => $product->load('category:id,category_name')]);
    }
}
