@props(['title' => 'POS'])

<x-panel-layout :title="$title" :menus="$menuTree" :user="auth()->user()"
    :home="route('cashier.pos')" :logout="route('cashier.logout')"
    :brand="config('app.name') . ' POS'" :suffix="config('app.name') . ' POS'" :fixed="true">
    {{ $slot }}
</x-panel-layout>
