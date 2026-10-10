@props(['action'])

<form method="POST" action="{{ $action }}">
    @csrf
    <button type="submit" {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <x-ui.icon name="log-out" class="h-4 w-4" />
        Log out
    </button>
</form>
