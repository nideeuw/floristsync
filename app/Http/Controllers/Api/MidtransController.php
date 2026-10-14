<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function notification(MidtransService $midtrans, TransactionService $transactions): JsonResponse
    {
        try {
            $notification = $midtrans->readNotification();
        } catch (DomainException $e) {
            Log::warning('Midtrans notification rejected: '.$e->getMessage());

            return response()->json(['message' => 'Invalid notification'], 403);
        }

        try {
            $transactions->updatePaymentStatus($notification);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Order not found'], 404);
        } catch (DomainException $e) {
            Log::error('Midtrans notification failed: '.$e->getMessage());

            return response()->json(['message' => 'Notification does not match the order'], 422);
        }

        return response()->json(['message' => 'OK']);
    }
}
