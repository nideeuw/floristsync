<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('customer.home', [
            'categories' => Category::active()
                ->withCount(['products' => fn ($q) => $q->available()])
                ->orderBy('category_name')
                ->get(),
            'featured' => Product::with('category')->available()->latest('id')->take(8)->get(),
        ]);
    }
}
