@props(['notifications', 'unread' => 0, 'prefix' => ''])

@php
    $icons = [
        'new_order' => 'shopping-bag',
        'order_status' => 'truck',
        'low_stock' => 'package',
    ];
    $labels = [
        'new_order' => 'New order',
        'order_status' => 'Order update',
        'low_stock' => 'Low stock',
    ];
    $actions = [
        'new_order' => 'View order',
        'order_status' => 'View order',
        'low_stock' => 'View stock',
    ];
@endphp

@if ($notifications->isEmpty())
    <x-ui.empty-state icon="bell" title="No notifications yet"
        description="New updates will show up here." />
@else
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-blush px-5 py-3">
            <span class="text-sm text-ink/70">{{ $unread }} unread</span>

            @if ($unread > 0)
                <form method="POST" action="{{ route($prefix . 'notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button class="text-sm font-semibold text-moss hover:underline">Mark all as read</button>
                </form>
            @endif
        </div>

        <ul class="divide-y divide-blush">
            @foreach ($notifications as $notification)
                @php
                    $isUnread = ! $notification->is_read;
                    $link = trim((string) $notification->link);
                    $hasLink = $link !== ''
                        && str_starts_with($link, '/')
                        && ! str_starts_with($link, '//')
                        && ! str_starts_with($link, '/\\');
                    $createdAt = $notification->created_on
                        ? \Illuminate\Support\Carbon::parse($notification->created_on)
                        : null;
                    $actionLabel = match (true) {
                        str_contains($link, 'order-cancellations') => 'Review request',
                        str_contains($link, '/orders/') => 'View order',
                        str_contains($link, '/track') => 'View order',
                        str_contains($link, '/stock') => 'View stock',
                        default => $actions[$notification->type] ?? 'Open',
                    };
                    $readUrl = route($prefix . 'notifications.read', $notification->id);
                @endphp

                <li x-data="{
                        open: false,
                        unread: {{ $isUnread ? 'true' : 'false' }},
                        markRead() {
                            if (! this.unread) return;
                            this.unread = false;
                            fetch(@js($readUrl), {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': @js(csrf_token()),
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            }).catch(() => { this.unread = true; });
                        },
                        toggle() {
                            this.open = ! this.open;
                            if (this.open) this.markRead();
                        },
                    }"
                    :class="unread ? 'bg-sage/20' : ''" class="px-5 py-4">
                    <div class="flex items-start gap-3">
                        <x-ui.icon :name="$icons[$notification->type] ?? 'bell'" class="mt-0.5 h-5 w-5 shrink-0 text-moss" />

                        <button type="button" @click="toggle()" :aria-expanded="open"
                            class="flex min-w-0 flex-1 items-start justify-between gap-3 text-left">
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ $notification->title }}</span>
                                <span class="block text-sm text-ink/70">{{ $notification->message }}</span>
                                @if ($createdAt)
                                    <span class="mt-1 block text-xs text-ink/50">{{ $createdAt->diffForHumans() }}</span>
                                @endif
                            </span>
                            <span class="mt-1 shrink-0 text-ink/40 transition-transform" :class="open ? 'rotate-180' : ''">
                                <x-ui.icon name="chevron-down" class="h-4 w-4" />
                            </span>
                        </button>

                        @if ($isUnread)
                            <button type="button" x-show="unread" @click="markRead()"
                                class="shrink-0 text-xs font-semibold text-moss hover:underline">Mark as read</button>
                        @endif
                    </div>

                    <div x-show="open" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="ml-8 mt-3 space-y-3 rounded-2xl bg-cream px-4 py-3 text-sm">
                        <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-1">
                            <dt class="text-ink/60">Type</dt>
                            <dd class="font-medium">{{ $labels[$notification->type] ?? 'Notification' }}</dd>

                            @if ($createdAt)
                                <dt class="text-ink/60">Received</dt>
                                <dd class="font-medium">{{ $createdAt->format('d M Y H:i') }}</dd>
                            @endif

                            <dt class="text-ink/60">Status</dt>
                            <dd class="font-medium" x-text="unread ? 'Unread' : 'Read'">{{ $isUnread ? 'Unread' : 'Read' }}</dd>
                        </dl>

                        @if ($hasLink)
                            <a href="{{ $link }}" @click="markRead()" class="btn-primary px-4 py-2 text-sm">{{ $actionLabel }}</a>
                        @else
                            <p class="text-ink/60">No page is linked to this notification.</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endif
