<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersPlaceholder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestockController extends Controller
{
    use RendersPlaceholder;

    public function index(Request $request): View
    {
        return $this->placeholder($request);
    }

    public function store(Request $request): never
    {
        abort(501, 'Not implemented yet.');
    }
}
