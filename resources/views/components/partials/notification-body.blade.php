<div class="font-semibold">{{ $notification->title }}</div>
<p class="text-sm text-ink/70">{{ $notification->message }}</p>
@if ($notification->created_on)
    <p class="mt-1 text-xs text-ink/50">
        {{ \Illuminate\Support\Carbon::parse($notification->created_on)->diffForHumans() }}
    </p>
@endif
