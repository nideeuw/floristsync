<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\CustomerPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CustomerPaymentController extends Controller
{
    public function charge(Request $request, Transaction $transaction, CustomerPaymentService $paymentService): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:qris,gopay,shopeepay,card,credit_card'],
            'token_id' => ['required_if:method,card,credit_card', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $data = $paymentService->charge($transaction, $validated['method'], $request->user(), $validated);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function status(Request $request, Transaction $transaction, CustomerPaymentService $paymentService): JsonResponse
    {
        try {
            $data = $paymentService->checkStatus($transaction, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($data);
    }

    public function cancel(Request $request, Transaction $transaction, CustomerPaymentService $paymentService): JsonResponse
    {
        try {
            $paymentService->cancelCharge($transaction, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment charge cancelled.',
        ]);
    }
}
