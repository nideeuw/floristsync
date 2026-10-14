@php
    $wrapper = match ($layout) {
        'pos-layout' => 'h-[calc(100vh-3.5rem)] overflow-y-auto p-6',
        'public-layout' => 'mx-auto max-w-3xl px-4 py-8 md:py-12',
        default => '',
    };
@endphp

<x-dynamic-component :component="$layout" title="Notifications">
    <div class="{{ $wrapper }}">
        @if ($layout === 'public-layout')
            <x-page-header eyebrow="Stay updated" title="Notifications" class="mb-8" />
        @elseif ($layout !== 'staff-layout')
            <h1 class="mb-6 text-3xl font-bold">Notifications</h1>
        @endif

        <x-notification-list :notifications="$notifications" :unread="$unread" :prefix="$prefix" />
    </div>
</x-dynamic-component>
