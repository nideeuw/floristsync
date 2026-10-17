@php
    $keep = \Illuminate\Support\Arr::only($params, ['from', 'to', 'type', 'category']);
    $exportParams = $params;
    $isStock = $tab === 'stock';

    $presets = [
        '7 days' => [now()->subDays(6), now()],
        '30 days' => [now()->subDays(29), now()],
        '90 days' => [now()->subDays(89), now()],
        'This month' => [now()->startOfMonth(), now()],
    ];
@endphp

<x-staff-layout title="Reports" subtitle="Sales, products, stock and cancellations">
    <x-slot:actions>
        <a href="{{ route('admin.reports.export.pdf', $exportParams) }}" class="btn-outline">Export PDF</a>
        <a href="{{ route('admin.reports.export.excel', $exportParams) }}" class="btn-outline">Export Excel</a>
    </x-slot:actions>

    @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Tabs --}}
    <nav class="mb-4 flex gap-1 overflow-x-auto border-b border-blush" aria-label="Report tabs">
        @foreach ($tabs as $key => $label)
        <a href="{{ route('admin.reports', ['tab' => $key] + $keep) }}"
            @class([
                'whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition',
                'border-moss text-moss' => $tab === $key,
                'border-transparent text-ink/60 hover:text-moss' => $tab !== $key,
            ])
            @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.reports') }}" class="card mb-4 space-y-3 p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">

        @unless ($isStock)
        <div class="flex flex-wrap gap-2">
            @foreach ($presets as $label => [$a, $b])
            <a href="{{ route('admin.reports', array_merge($params, ['from' => $a->format('Y-m-d'), 'to' => $b->format('Y-m-d')])) }}"
                class="{{ $from->isSameDay($a) && $to->isSameDay($b) ? 'btn-primary' : 'btn-outline' }} !py-1.5">{{ $label }}</a>
            @endforeach
        </div>
        @endunless

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @unless ($isStock)
            <div>
                <label class="mb-1 block text-xs text-gray-500">From</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">To</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input">
            </div>
            @endunless

            @unless ($isStock)
            @if ($tab !== 'stock')
            <div>
                <label class="mb-1 block text-xs text-gray-500">Type</label>
                <select name="type" class="input">
                    <option value="">POS and online</option>
                    <option value="pos" @selected($type === 'pos')>POS (in store)</option>
                    <option value="online" @selected($type === 'online')>Online</option>
                </select>
            </div>
            @endif
            @endunless

            @if (in_array($tab, ['products', 'stock'], true))
            <div>
                <label class="mb-1 block text-xs text-gray-500">Category</label>
                <select name="category" class="input">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((int) $category === $cat->id)>{{ $cat->category_name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if ($isStock)
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" name="low" value="1" class="accent-moss" @checked($low)>
                Low stock only
            </label>
            @endif

            <div class="flex items-end gap-2">
                <button class="btn-primary">Apply</button>
                <a href="{{ route('admin.reports', ['tab' => $tab]) }}" class="btn-outline">Reset</a>
            </div>
        </div>

        @if ($isStock)
        <p class="text-xs text-gray-500">Stock shows the current levels. The date range does not apply to this tab.</p>
        @endif
    </form>

    {{-- Summary --}}
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
        <x-stat-card :label="$card['label']" :value="$card['value']" :hint="$card['hint']" />
        @endforeach
    </div>

    {{-- Table --}}
    <x-ui.table class="[&_table]:min-w-[44rem]">
        <x-slot:head>
            @foreach ($columns as $column)
            <th class="{{ in_array($column['type'], ['int', 'money'], true) ? 'text-right' : '' }}">{{ $column['label'] }}</th>
            @endforeach
        </x-slot:head>

        <x-slot:body>
            @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                <td @class([
                    'text-right' => in_array($column['type'], ['int', 'money'], true),
                    'whitespace-nowrap' => in_array($column['type'], ['int', 'money', 'date', 'datetime'], true),
                    'max-w-xs' => $column['key'] === 'cancel_reason',
                ])>{{ \App\Services\ReportService::format($column['type'], $row->{$column['key']} ?? null) }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columns) }}" class="!py-8 text-center text-gray-500">No data for these filters.</td>
            </tr>
            @endforelse
        </x-slot:body>

        <x-slot:footer>{{ $rows->links('components.ui.pagination', ['sizes' => true, 'sizeOptions' => [10, 25, 50, 100]]) }}</x-slot:footer>
    </x-ui.table>
</x-staff-layout>
