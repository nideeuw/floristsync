<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Concerns\RendersPlaceholder;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CancelTransactionController extends Controller
{
    use RendersPlaceholder;

    public function index(Request $request): View
    {
        return $this->placeholder($request);
    }

    public function store(Request $request, Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }
}
