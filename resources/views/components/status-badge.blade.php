@props(['status'])

@php
$styles = [
'pending_payment' => 'bg-yellow-100 text-yellow-800',
'processing' => 'bg-petal/50 text-ink',
'ready_for_pickup' => 'bg-sage/40 text-ink',
'shipped' => 'bg-sage/40 text-ink',
'completed' => 'bg-moss text-white',
'cancelled' => 'bg-red-100 text-red-700',
];
@endphp

<span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $styles[$status] ?? 'bg-gray-200 text-gray-700' }}">
    {{ ucfirst(str_replace('_', ' ', $status)) }}
</span>
