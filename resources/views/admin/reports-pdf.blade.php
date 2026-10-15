<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #2b2b2b; }
        .head { border-bottom: 2px solid #9CAD8C; padding-bottom: 10px; }
        h1 { margin: 0; font-size: 20px; color: #7E8C69; }
        h2 { margin: 22px 0 6px; font-size: 13px; color: #7E8C69; }
        .meta { margin: 4px 0 0; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #F1C8CB; text-align: left; font-size: 10px; text-transform: uppercase; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ECE0DA; }
        .right { text-align: right; }
        .empty { padding: 14px 8px; text-align: center; color: #888; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $storeName }}</h1>
        <p class="meta">{{ $title }} &middot; {{ $period }}</p>
        @if (count($notes))
        <p class="meta">Filters: {{ implode(', ', $notes) }}</p>
        @endif
        <p class="meta">Generated {{ now()->format('d M Y H:i') }}</p>
    </div>

    <h2>Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="right">Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cards as $card)
            <tr>
                <td>{{ $card['label'] }}@if ($card['hint']) <span style="color:#888">({{ $card['hint'] }})</span>@endif</td>
                <td class="right">{{ $card['value'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ $title }}</h2>
    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                <th class="{{ in_array($column['type'], ['int', 'money'], true) ? 'right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $column)
                <td class="{{ in_array($column['type'], ['int', 'money'], true) ? 'right' : '' }}">{{ \App\Services\ReportService::format($column['type'], $row->{$column['key']} ?? null) }}</td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($columns) }}" class="empty">No data for these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
