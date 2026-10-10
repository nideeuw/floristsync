@if (session('success'))
<div class="mb-4 rounded-xl bg-sage/30 px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

@if (session('error'))
<div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
@endif

@if ($errors->any())
<div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">
    <ul class="list-inside list-disc">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
