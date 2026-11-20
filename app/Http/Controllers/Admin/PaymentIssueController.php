<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Transaction;
use App\Services\PaymentIssueService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentIssueController extends Controller
{
    public function __construct(private PaymentIssueService $issues) {}

    public function index(Request $request): View
    {
        return view('admin.orders.payment-issues', [
            'sections' => $this->issues->sections(),
            // The owner only reads this page.
            'canAct' => $request->user()->hasRole(Role::ADMIN_ORDER),
        ]);
    }

    public function recheck(Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->transaction_type === Transaction::TYPE_ONLINE, 404);

        try {
            $message = $this->issues->recheck($transaction);
        } catch (DomainException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
