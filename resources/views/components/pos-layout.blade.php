@props(['title' => 'POS'])

@php
    $user = auth()->user();
    $home = $user ? route('cashier.pos') : route('cashier.login');
@endphp

<x-panel-layout :title="$title" :menus="$menuTree ?? collect()" :user="$user"
    :home="$home" :logout="route('cashier.logout')"
    :brand="config('app.name') . ' POS'" :suffix="config('app.name') . ' POS'" :fixed="true">
    {{ $slot }}
</x-panel-layout>
