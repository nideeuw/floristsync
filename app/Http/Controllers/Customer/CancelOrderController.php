<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class CancelOrderController extends Controller
{
    public function store(Request $request, Transaction $transaction): never
    {
        abort(501, 'Not implemented yet.');
    }
}
