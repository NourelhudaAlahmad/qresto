<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report · {{ $restaurant->name }}</title>
    {{--
        Print-styled report for PDF export (#32). Numbers right-aligned in a
        monospaced face with tabular figures; text left-aligned. Colours
        mirror the design tokens: ink-900 #1D1A17, ink-500 #8C847A,
        border #E5E0D8, berry-500 #B23A48.
    --}}
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #1D1A17; }
        h1 { font-size: 15pt; margin: 0 0 2mm; }
        .meta { color: #8C847A; margin-bottom: 6mm; }
        .cards { width: 100%; margin-bottom: 6mm; border-collapse: separate; border-spacing: 3mm 0; }
        .cards td { border: 1px solid #E5E0D8; padding: 3mm; width: 33%; }
        .cards .label { color: #8C847A; font-size: 8pt; }
        .cards .value { font-family: "DejaVu Sans Mono", monospace; font-size: 13pt; margin-top: 1mm; }
        .negative { color: #B23A48; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { text-align: left; font-size: 8pt; color: #8C847A; border-bottom: 1px solid #E5E0D8; padding: 2mm 1.5mm; }
        table.rows td { border-bottom: 1px solid #E5E0D8; padding: 1.6mm 1.5mm; }
        .num, table.rows th.num { text-align: right; font-family: "DejaVu Sans Mono", monospace; }
        tr.total td { font-weight: bold; border-top: 1.5px solid #1D1A17; border-bottom: none; }
    </style>
</head>
<body>
    <h1>{{ $restaurant->name }} · Sales report</h1>
    <div class="meta">
        {{ $range['start']->format('j M Y') }} – {{ $range['end']->format('j M Y') }}
        · all waiters · {{ $restaurant->currency }}
        · generated {{ $generatedAt->format('j M Y H:i') }}
    </div>

    <table class="cards">
        <tr>
            <td><div class="label">Gross</div><div class="value">{{ $money($totals['gross_minor']) }}</div></td>
            <td><div class="label">Service charge</div><div class="value">{{ $money($totals['service_minor']) }}</div></td>
            <td><div class="label">Refunded</div><div class="value negative">{{ $totals['refunds_minor'] > 0 ? '−' : '' }}{{ $money($totals['refunds_minor']) }}</div></td>
        </tr>
    </table>

    <table class="rows">
        <thead>
            <tr>
                <th>Date</th>
                <th>Waiter</th>
                <th class="num">Covers</th>
                <th class="num">Orders</th>
                <th class="num">Net</th>
                <th class="num">Tips</th>
                <th>Method</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['date'])->format('D j M') }}</td>
                    <td>{{ $row['waiter'] }}</td>
                    <td class="num">{{ $row['covers'] }}</td>
                    <td class="num">{{ $row['orders'] }}</td>
                    <td class="num">{{ $money($row['net_minor']) }}</td>
                    <td class="num">{{ $money($row['tips_minor']) }}</td>
                    <td>{{ \App\Services\Reports\SalesReport::methodLabel($row['method']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No settled sales in this range.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total</td>
                <td></td>
                <td class="num">{{ $totals['covers'] }}</td>
                <td class="num">{{ $totals['orders'] }}</td>
                <td class="num">{{ $money($totals['net_minor']) }}</td>
                <td class="num">{{ $money($totals['tips_minor']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
