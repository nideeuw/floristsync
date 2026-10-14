@props(['href', 'user'])

@php
    $count = $user->appNotifications()->where('is_read', false)->count();
@endphp

<a href="{{ $href }}" class="relative rounded-xl p-2 text-ink/70 transition-colors hover:bg-cream/60 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sage" aria-label="Notifications, {{ $count }} unread">
    <x-ui.icon name="bell" class="h-5 w-5" />
    @if ($count > 0)
        <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-moss px-1 text-[10px] font-bold text-white">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</a>
