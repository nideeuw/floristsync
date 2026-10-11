<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersPlaceholder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use RendersPlaceholder;

    public function index(Request $request): View
    {
        return $this->placeholder($request);
    }
}
