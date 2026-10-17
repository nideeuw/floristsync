<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        return view('admin.stock.adjust', [
            'products' => Product::active()->orderBy('product_name')
                ->get(['id', 'product_name', 'stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'change' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'notes' => ['required', 'string', 'max:200'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        try {
            $this->inventory->adjust($product, (int) $data['change'], $data['notes']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Stock adjusted for {$product->product_name}.");
    }
}
