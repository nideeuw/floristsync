@props(['title' => null, 'subtitle' => null])

@php
    $user = auth()->user();
    $canNotify = app(\App\Services\MenuPermissionService::class)
        ->can($user->role_id, 'admin.notifications', 'view');

    $sidebarMenus = $menuTree->reject(fn ($menu) => $menu->url_link === 'admin.notifications')->values();
@endphp

<x-panel-layout :title="$title" :subtitle="$subtitle" :actions="$actions ?? null"
    :menus="$sidebarMenus" :user="$user"
    :home="route(app(\App\Services\AreaHomeService::class)->routeFor($user))"
    :logout="route('admin.logout')"
    :bell="$canNotify ? route('admin.notifications') : null">
    {{ $slot }}
</x-panel-layout>
