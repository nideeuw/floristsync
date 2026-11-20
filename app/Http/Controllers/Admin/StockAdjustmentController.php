<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Services\AuditService;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private InventoryService $inventory, private AuditService $audit) {}

    public function index(Request $request): View
    {
        return view('admin.stock.adjust', [
            'products' => Product::active()->withStock()->orderBy('product_name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'change' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => [
                Rule::requiredIf(fn () => (int) $request->input('change') < 0),
                'nullable',
                Rule::in(array_keys(Stock::REASONS)),
            ],
            'notes' => ['required', 'string', 'max:200'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        try {
            $this->inventory->adjust($product, (int) $data['change'], $data['notes'], $data['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('stock.adjusted', 'product', $product->id, null, ['change' => (int) $data['change'], 'reason' => $data['reason'] ?? null, 'notes' => $data['notes']]);

        return back()->with('success', "Stock adjusted for {$product->product_name}.");
    }
}
