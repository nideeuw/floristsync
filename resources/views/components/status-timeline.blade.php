@props(['transaction'])

@php
$history = \App\Models\AuditLog::statusHistory($transaction->id);
$label = fn (?string $status) => $status ? ucfirst(str_replace('_', ' ', $status)) : '-';
@endphp

<div class="card p-5 text-sm">
    <div class="mb-3 font-semibold">Status history</div>

    @if ($history->isEmpty())
    <p class="text-gray-500">No status changes recorded yet.</p>
    @else
    <ol class="space-y-3 border-l-2 border-blush pl-4">
        @foreach ($history as $log)
        <li>
            <div class="font-semibold">{{ $label($log->old_values['status'] ?? null) }} &rarr; {{ $label($log->new_values['status'] ?? null) }}</div>
            <div class="text-xs text-gray-500">{{ $log->created_on?->format('d M Y, H:i') }} &middot; {{ $log->actorLabel() }}</div>
        </li>
        @endforeach
    </ol>
    @endif
</div>
