<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestockController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        return view('admin.stock.restock', [
            'products' => Product::active()->orderBy('product_name')
                ->get(['id', 'product_name', 'stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $this->inventory->add($product, (int) $data['quantity'], 'in', $data['notes'] ?: 'Restock');

        return back()->with('success', "Stock added to {$product->product_name}.");
    }
}
