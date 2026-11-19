<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class MidtransController extends Controller
{
    public function notification(MidtransService $midtrans, TransactionService $transactions): JsonResponse
    {
        try {
            $notification = $midtrans->readNotification();
        } catch (DomainException $e) {
            Log::warning('Midtrans notification rejected: '.$e->getMessage());

            return response()->json(['message' => 'Invalid notification'], 403);
        } catch (InvalidArgumentException $e) {
            Log::warning('Midtrans notification malformed: '.$e->getMessage());

            return response()->json(['message' => 'Malformed notification'], 400);
        } catch (Throwable $e) {
            // Midtrans or the network failed. 503 makes Midtrans send the notification again.
            Log::error('Midtrans notification could not be verified: '.$e->getMessage());

            return response()->json(['message' => 'Notification could not be verified right now'], 503);
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
