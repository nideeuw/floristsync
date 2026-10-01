<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    /** Polled by the POS page to refresh stock without reloading. */
    public function products(): JsonResponse
    {
        return response()->json([
            'categories' => Category::active()->orderBy('category_name')->get(['id', 'category_name']),
            'data' => Product::available()->orderBy('product_name')
                ->get(['id', 'category_id', 'product_name', 'price', 'image', 'stock']),
        ]);
    }

    public function store(Request $request, TransactionService $service): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['required', 'in:cash,qris,debit'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $paid = $data['paid_amount'] ?? 0;

        try {
            $transaction = $service->createPosTransaction(
                $data['items'], $request->user()->id, $data['payment_method'], $paid
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Transaction successful.',
            'data' => [
                'id' => $transaction->id,
                'total' => $transaction->total,
                'change' => max(0, $paid - $transaction->total),
                'receipt_url' => route('cashier.receipt', $transaction),
            ],
        ], 201);
    }
}
