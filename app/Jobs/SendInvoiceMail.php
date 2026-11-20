<?php

namespace App\Jobs;

use App\Mail\OrderInvoiceMail;
use App\Models\Role;
use App\Models\Transaction;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the invoice of a paid online order to its member. Dispatched with afterResponse(), so the webhook is
 * answered first and no queue worker is needed. It never throws: a mail problem must not break the payment flow.
 */
class SendInvoiceMail
{
    use Dispatchable;

    private const PAID_STATUSES = [
        Transaction::PROCESSING,
        Transaction::READY_FOR_PICKUP,
        Transaction::SHIPPED,
        Transaction::COMPLETED,
    ];

    /** @param bool $force true for the "Resend invoice" button: ignores the profile toggle and the sent marker */
    public function __construct(public int $transactionId, public bool $force = false) {}

    public function handle(): void
    {
        $transaction = Transaction::with('user.role', 'details.product', 'payment')->find($this->transactionId);

        if (! $this->shouldSend($transaction)) {
            return;
        }

        if (! $this->force) {
            if (! $transaction->user->email_invoice) {
                return;
            }

            // One email per order: whoever sets the column first sends it, any later call stops here.
            $claimed = Transaction::whereKey($transaction->id)
                ->whereNull('invoice_emailed_at')
                ->update(['invoice_emailed_at' => now()]);

            if ($claimed === 0) {
                return;
            }
        }

        try {
            Mail::to($transaction->user->email)->send(new OrderInvoiceMail($transaction));

            if ($this->force) {
                Transaction::whereKey($transaction->id)->whereNull('invoice_emailed_at')->update(['invoice_emailed_at' => now()]);
            }
        } catch (Throwable $e) {
            // The mail relay can fail or run out of its daily quota (shared with password reset).
            if (! $this->force) {
                Transaction::whereKey($transaction->id)->update(['invoice_emailed_at' => null]);
            }

            Log::warning("Invoice email for order {$transaction->order_number} was skipped: {$e->getMessage()}");
        }
    }

    private function shouldSend(?Transaction $transaction): bool
    {
        return $transaction !== null
            && $transaction->transaction_type === Transaction::TYPE_ONLINE
            && in_array($transaction->status, self::PAID_STATUSES, true)
            && $transaction->user !== null
            && $transaction->user->is_active
            && $transaction->user->hasRole(Role::MEMBER)
            && filled($transaction->user->email);
    }
}
