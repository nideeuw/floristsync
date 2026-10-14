<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales report</title>
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
        .low { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $storeName }}</h1>
        <p class="meta">Sales report &middot; {{ $from->format('d M Y') }} - {{ $to->format('d M Y') }} (last {{ $days }} days)</p>
        <p class="meta">Generated {{ now()->format('d M Y H:i') }}</p>
    </div>

    <h2>Revenue summary</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="right">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Revenue</td><td class="right">{{ rupiah($summary['revenue']) }}</td></tr>
            <tr><td>Orders</td><td class="right">{{ $summary['orders'] }}</td></tr>
            <tr><td>Average order value</td><td class="right">{{ rupiah($summary['average']) }}</td></tr>
            <tr><td>POS revenue</td><td class="right">{{ rupiah($summary['pos_revenue']) }}</td></tr>
            <tr><td>Online revenue</td><td class="right">{{ rupiah($summary['online_revenue']) }}</td></tr>
        </tbody>
    </table>

    <h2>Best selling products</h2>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Sold</th>
                <th class="right">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($topProducts as $row)
            <tr>
                <td>{{ $row->product_name }}</td>
                <td>{{ $row->total_qty }}</td>
                <td class="right">{{ rupiah($row->total_revenue) }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="empty">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Low stock (at or below {{ config('floristsync.low_stock_threshold') }})</h2>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lowStock as $product)
            <tr>
                <td>{{ $product->product_name }}</td>
                <td class="low">{{ $product->stock }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="empty">All products have enough stock.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
