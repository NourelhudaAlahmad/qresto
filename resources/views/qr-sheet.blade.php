<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR codes · {{ $restaurant->name }}</title>
    {{--
        Print sheet for table stickers (#29). A4, 3 × 4 cards of 63.5 × 67.7 mm,
        a common adhesive-label layout. Colours mirror the design tokens:
        ink-900 #1D1A17, clay-500 #C65D3B, ink-500 #8C847A, border #E5E0D8.
    --}}
    <style>
        @page { size: A4; margin: 10mm 7mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #1D1A17;
            background: #F5F2ED;
        }

        .toolbar {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 24px; border-bottom: 1px solid #E5E0D8; background: #fff;
        }
        .toolbar h1 { flex: 1; margin: 0; font-size: 17px; }
        .toolbar button {
            height: 44px; padding: 0 20px; border: 0; border-radius: 999px;
            background: #C65D3B; color: #fff; font: inherit; font-weight: 600; cursor: pointer;
        }
        .toolbar p { margin: 0; color: #8C847A; font-size: 13px; }

        .sheet {
            display: grid;
            grid-template-columns: repeat(3, 63.5mm);
            grid-auto-rows: 67.7mm;
            gap: 0 2.5mm;
            justify-content: center;
            padding: 10mm 0;
        }

        .card {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 2mm; padding: 3mm;
            border: 1px dashed #E5E0D8; background: #fff;
            break-inside: avoid; page-break-inside: avoid;
        }
        .card .name { font-size: 9pt; font-weight: 700; letter-spacing: -0.02em; }
        .card .name span { color: #C65D3B; }
        .card .qr svg { display: block; width: 38mm; height: 38mm; }
        .card .table { font-size: 15pt; font-weight: 700; font-variant-numeric: tabular-nums; }
        .card .hint { font-size: 7pt; color: #8C847A; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .card { border-color: transparent; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>{{ $restaurant->name }} · {{ $cards->count() }} {{ \Illuminate\Support\Str::plural('card', $cards->count()) }}</h1>
        <p>A4, 3 × 4 labels. Print at 100% scale.</p>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <main class="sheet">
        @foreach ($cards as $card)
            @php([$first, $rest] = array_pad(explode(' ', trim($restaurant->name), 2), 2, null))
            <section class="card" data-table="{{ $card['number'] }}" data-url="{{ $card['url'] }}">
                <div class="name">
                    @if ($rest)
                        <span>{{ $first }}</span> {{ $rest }}
                    @else
                        {{ $first }}
                    @endif
                </div>
                <div class="qr" aria-label="QR code for table {{ $card['number'] }}">{!! $card['svg'] !!}</div>
                <div class="table">Table {{ $card['number'] }}</div>
                <div class="hint">Scan to see the menu and order</div>
            </section>
        @endforeach
    </main>
</body>
</html>
