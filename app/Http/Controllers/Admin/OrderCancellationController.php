<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersPlaceholder;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderCancellationController extends Controller
{
    use RendersPlaceholder;

    public function index(Request $request): View
    {
        return $this->placeholder($request);
    }

    public function approve(Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }

    public function reject(Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }
}
