<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class MidtransController extends Controller
{
    public function notification(MidtransService $midtrans, TransactionService $transactions): JsonResponse
    {
        $transactions->updatePaymentStatus($midtrans->readNotification());

        return response()->json(['message' => 'OK']);
    }
}
