<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\ProductImageService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private AuditService $audit,
        private ProductImageService $images,
    ) {}

    public function index(Request $request): View
    {
        $products = Product::withStock()->with('category')
            ->when(filled($request->query('q')), fn ($q) => $q->where('product_name', 'ilike', '%'.escape_like($request->query('q')).'%'))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
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

        try {
            $image = $request->hasFile('image') ? $this->images->store($request->file('image')) : null;
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $product = Product::create([
            'category_id' => $data['category_id'],
            'product_name' => $data['product_name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'status' => $data['status'],
            'is_active' => $request->boolean('is_active'),
            'image' => $image,
            // stock is stored in the stocks table (see InventoryService)
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

        $oldImage = $product->image;
        $replaced = false;

        if ($request->hasFile('image')) {
            try {
                $attributes['image'] = $this->images->store($request->file('image'));
                $replaced = true;
            } catch (DomainException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

        $before = $product->only(['price', 'is_active']);

        $product->update($attributes);

        // Delete the old file only after the new one is saved on the row.
        if ($replaced) {
            $this->images->delete($oldImage);
        }

        $this->audit->logChanges('product.updated', 'product', $product->id, $before, $product->only(['price', 'is_active']));

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

        $this->audit->log('stock.adjusted', 'product', $product->id, null, ['change' => (int) $data['change'], 'notes' => $data['notes'] ?: 'Manual adjustment']);

        return back()->with('success', 'Stock updated.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $before = $product->only(['is_active']);

        $product->update(['is_active' => ! $product->is_active]);

        $this->audit->logChanges('product.updated', 'product', $product->id, $before, $product->only(['is_active']));

        return back()->with('success', $product->is_active ? 'Product activated.' : 'Product deactivated.');
    }
}
