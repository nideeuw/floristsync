<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Transaction;
use App\Services\SettingService;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function index(Request $request): View
    {
        $cashier = $request->user();
        $shift = $this->shifts->current($cashier);
        $data = $this->shifts->getShiftSummary($shift, $cashier);

        return view('cashier.history', array_merge($data, ['shift' => $shift]));
    }

    public function receipt(Request $request, Transaction $transaction, SettingService $settings): View
    {
        abort_unless($request->user()?->hasRole(Role::CASHIER), 403, 'Only cashiers can access receipts.');

        abort_unless($transaction->transaction_type === Transaction::TYPE_POS, 404);

        $transaction->loadMissing('details.product', 'payment', 'cashier', 'user');

        $payment = $transaction->payment;
        $isValidPayment = $transaction->status === Transaction::COMPLETED
            && $payment !== null
            && $payment->payment_status === 'settlement';

        $message = null;
        if (! $isValidPayment) {
            if ($transaction->status === Transaction::CANCELLED || $payment?->payment_status === 'cancel') {
                $message = 'This sale was cancelled. Receipt cannot be printed.';
            } else {
                $message = 'This sale is pending payment. Receipt cannot be printed until payment is settled.';
            }
        }

        return view('cashier.receipt', [
            'transaction' => $transaction,
            'isValid' => $isValidPayment,
            'message' => $message,
            'storeName' => $settings->get('store_name', config('app.name', 'FloristSync')),
            'storeAddress' => $settings->get('address'),
            'storePhone' => $settings->get('phone'),
        ]);
    }
}
