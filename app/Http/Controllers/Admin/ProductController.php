<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $products = Product::with('category')
            ->when($request->query('q'), fn($q, $v) => $q->where('product_name', 'like', "%{$v}%"))
            ->when($request->query('category'), fn($q, $v) => $q->where('category_id', $v))
            ->when($request->query('status'), fn($q, $v) => $q->where('status', $v))
            ->orderBy('product_name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('category_name')->get(['id', 'category_name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'categories' => Category::active()->orderBy('category_name')->get(['id', 'category_name']),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = Product::create([
            'category_id' => $data['category_id'],
            'product_name' => $data['product_name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'status' => $data['status'],
            'is_active' => $request->boolean('is_active'),
            'image' => $request->file('image')?->store('products', 'public'),
            'stock' => 0,
        ]);

        if (($data['stock'] ?? 0) > 0) {
            $this->inventory->add($product, (int) $data['stock'], 'in', 'Initial stock');
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::active()->orderBy('category_name')->get(['id', 'category_name']),
            'history' => $product->stocks()->latest('id')->take(10)->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        $attributes = [
            'category_id' => $data['category_id'],
            'product_name' => $data['product_name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'status' => $data['status'],
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $attributes['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($attributes);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'change' => ['required', 'integer', 'between:-100000,100000'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->inventory->adjust($product, (int) $data['change'], $data['notes'] ?: 'Manual adjustment');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stock updated.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active ? 'Product activated.' : 'Product deactivated.');
    }
}
