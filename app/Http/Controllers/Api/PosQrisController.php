<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\PosQrisService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QRIS payment of a POS transaction. Cashier only.
 */
class PosQrisController extends Controller
{
    /**
     * Create the QRIS code for a pending POS transaction.
     */
    public function store(Request $request, Transaction $transaction, PosQrisService $qris): JsonResponse
    {
        $this->assertBelongsToCashier($request, $transaction);

        if ($transaction->status !== Transaction::PENDING_PAYMENT) {
            return response()->json([
                'message' => 'Only pending transactions can generate a QRIS code.',
            ], 422);
        }

        try {
            $qrData = $qris->createQr($transaction);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'QRIS code generated.',
            'data' => $qrData,
        ]);
    }

    /**
     * Poll the payment status of the QRIS payment.
     */
    public function show(Request $request, Transaction $transaction, PosQrisService $qris): JsonResponse
    {
        $this->assertBelongsToCashier($request, $transaction);

        try {
            $status = $qris->status($transaction);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $transaction->refresh();

        $isCompleted = $transaction->status === Transaction::COMPLETED;
        $isCancelled = $transaction->status === Transaction::CANCELLED;
        $receiptUrl = $isCompleted ? route('cashier.receipt', $transaction) : null;

        return response()->json([
            'status' => $status,
            'transaction_status' => $transaction->status,
            'is_completed' => $isCompleted,
            'is_cancelled' => $isCancelled,
            'receipt_url' => $receiptUrl,
            'data' => [
                'status' => $status,
                'transaction_status' => $transaction->status,
                'is_completed' => $isCompleted,
                'is_cancelled' => $isCancelled,
                'receipt_url' => $receiptUrl,
            ],
        ]);
    }

    /**
     * Cancel the pending QRIS payment.
     */
    public function destroy(Request $request, Transaction $transaction, PosQrisService $qris): JsonResponse
    {
        $this->assertBelongsToCashier($request, $transaction);

        if ($transaction->status === Transaction::PENDING_PAYMENT) {
            $qris->cancel($transaction);
        }

        return response()->json([
            'message' => 'Transaction cancelled.',
        ]);
    }

    private function assertBelongsToCashier(Request $request, Transaction $transaction): void
    {
        abort_unless(
            $transaction->transaction_type === Transaction::TYPE_POS
                && (int) $transaction->cashier_id === (int) $request->user()->id,
            403,
            'You are not authorized to manage this transaction.'
        );
    }
}
