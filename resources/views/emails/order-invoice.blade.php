<x-mail::message>
# Thank you for your order

Hello {{ $transaction->user?->name }}, we received your payment. This is your invoice.

**{{ $storeName }}**
@if ($storeAddress)
{{ $storeAddress }}
@endif
@if ($storePhone)
Phone: {{ $storePhone }}
@endif

**Order number:** {{ $transaction->order_number }}
**Date:** {{ \Carbon\Carbon::parse($transaction->created_on)->format('d M Y, H:i') }}
**Payment method:** {{ strtoupper(str_replace('_', ' ', $transaction->payment?->payment_method ?? '-')) }}

<x-mail::table>
| Product | Qty | Price | Subtotal |
| :------ | :-: | ----: | -------: |
@foreach ($transaction->details as $detail)
| {{ str_replace('|', '/', $detail->product?->product_name ?? 'Product') }} | {{ $detail->qty }} | {{ rupiah($detail->unit_price) }} | {{ rupiah($detail->subtotal) }} |
@endforeach
</x-mail::table>

**Items:** {{ rupiah($itemsTotal) }}
**Shipping:** {{ $shipping > 0 ? rupiah($shipping) : 'Free' }}
**Total:** {{ rupiah($transaction->total) }}

@if ($transaction->isDelivery())
**Delivery by {{ strtoupper($transaction->courier) }}:** {{ str_replace("\n", ', ', (string) $transaction->shipping_address) }}
@else
**Pickup in store.** We will tell you when your order is ready.
@endif

<x-mail::button :url="$invoiceUrl">
View invoice
</x-mail::button>

You can turn off invoice emails in your [profile]({{ $profileUrl }}).

Thanks,<br>
{{ $storeName }}
</x-mail::message>
