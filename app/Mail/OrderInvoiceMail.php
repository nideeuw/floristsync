<?php

namespace App\Mail;

use App\Models\Transaction;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The invoice of a paid online order. The invoice itself is the body, the link opens the printable page. */
class OrderInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Transaction $transaction) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your invoice for order '.$this->transaction->order_number);
    }

    public function content(): Content
    {
        $settings = app(SettingService::class);
        $transaction = $this->transaction->loadMissing('details.product', 'payment', 'user');

        return new Content(
            markdown: 'emails.order-invoice',
            with: [
                'transaction' => $transaction,
                'storeName' => $settings->get('store_name', config('app.name', 'FloristSync')),
                'storeAddress' => $settings->get('address', config('floristsync.store_address')),
                'storePhone' => $settings->get('phone', config('floristsync.phone')),
                'itemsTotal' => (int) $transaction->details->sum('subtotal'),
                'shipping' => (int) ($transaction->shipping_cost ?? 0),
                'invoiceUrl' => route('track.invoice', $transaction->order_number),
                'profileUrl' => route('profile.edit'),
            ],
        );
    }
}
