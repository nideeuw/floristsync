@props(['title' => null, 'subtitle' => null])

@php
    $user = auth()->user();
    $canNotify = $user && app(\App\Services\MenuPermissionService::class)
        ->can($user->role_id, 'admin.notifications', 'view');

    $sidebarMenus = ($menuTree ?? collect())->reject(fn ($menu) => $menu->url_link === 'admin.notifications')->values();
    $home = $user ? route(app(\App\Services\AreaHomeService::class)->routeFor($user)) : route('admin.login');
@endphp

<x-panel-layout :title="$title" :subtitle="$subtitle" :actions="$actions ?? null"
    :menus="$sidebarMenus" :user="$user"
    :home="$home"
    :logout="route('admin.logout')"
    :bell="$canNotify ? route('admin.notifications') : null">
    {{ $slot }}
</x-panel-layout>
