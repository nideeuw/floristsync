@props(['notifications', 'unread' => 0, 'prefix' => ''])

@php
    $icons = [
        'new_order' => 'shopping-bag',
        'order_status' => 'truck',
        'low_stock' => 'package',
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
                <li @class(['flex items-start gap-3 px-5 py-4', 'bg-sage/20' => ! $notification->is_read])>
                    <x-ui.icon :name="$icons[$notification->type] ?? 'bell'" class="mt-0.5 h-5 w-5 shrink-0 text-moss" />

                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ $notification->title }}</div>
                        <p class="text-sm text-ink/70">{{ $notification->message }}</p>
                        @if ($notification->created_on)
                            <p class="mt-1 text-xs text-ink/50">
                                {{ \Illuminate\Support\Carbon::parse($notification->created_on)->diffForHumans() }}
                            </p>
                        @endif
                    </div>

                    @unless ($notification->is_read)
                        <form method="POST" action="{{ route($prefix . 'notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')
                            <button class="shrink-0 text-xs font-semibold text-moss hover:underline">Mark as read</button>
                        </form>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
@endif
