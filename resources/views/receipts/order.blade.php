<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Receipt #{{ $order->id }} — QRestO</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Noto+Sans+Arabic:wght@300..800&family=Outfit:wght@300..700&display=swap"
        rel="stylesheet"
    >

    @vite('resources/css/app.css')

    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 3mm;
            }

            html,
            body {
                width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #000 !important;
            }

            .receipt-paper {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }

            .no-print {
                display: none !important;
            }

            .receipt-section {
                break-inside: avoid;
            }

            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body class="min-h-screen bg-surface-page font-ui text-text-primary">

@php
    $currency = $order->currency
        ?? $order->restaurant?->currency
        ?? 'TRY';

    $formatMoney = static fn (int $minor): string =>
        number_format($minor / 100, 2, '.', ',') . ' ' . $currency;

    $methodLabels = [
        'cash' => 'Cash',
        'pos' => 'Card at table',
        'card' => 'Card',
        'wallet' => 'Wallet',
    ];
@endphp

<main class="mx-auto w-full max-w-lg px-4 py-8 sm:py-12">

    <div class="receipt-paper overflow-hidden rounded-2xl border border-border-default bg-surface-card shadow-card">

        {{-- Header --}}
        <header class="border-b border-border-subtle px-6 py-7 text-center">

            <div class="mb-3 inline-flex items-center rounded-full bg-surface-brand-soft px-4 py-2 font-display text-sm font-bold tracking-wide text-text-brand">
                QRestO
            </div>

            <h1 class="font-display text-2xl font-semibold tracking-tight text-text-primary">
                {{ $order->restaurant?->name ?? 'Restaurant' }}
            </h1>

            <p class="mt-2 text-sm text-text-secondary">
                Order receipt
            </p>

            <span class="mt-4 inline-flex rounded-full px-3 py-1 text-xs font-semibold
                {{ $balanceMinor === 0
                    ? 'bg-status-paid-bg text-status-paid-fg'
                    : 'bg-warning-bg text-warning-fg' }}">
                {{ $balanceMinor === 0 ? 'Paid in full' : 'Payment pending' }}
            </span>

        </header>

        {{-- Order details --}}
        <section class="receipt-section px-6 py-5">

            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-text-secondary">Order number</span>
                <span class="qr-numeric font-semibold">#{{ $order->id }}</span>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                <span class="text-text-secondary">Table</span>
                <span class="qr-numeric font-semibold">{{ $table->number }}</span>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                <span class="text-text-secondary">Date</span>
                <span class="qr-numeric text-right">
                    {{ $order->created_at?->format('d M Y, H:i') }}
                </span>
            </div>

        </section>

        {{-- Ordered items --}}
        <section class="border-t border-dashed border-border-default px-6 py-6">

            <h2 class="mb-5 font-display text-lg font-semibold">
                Order details
            </h2>

            <div class="space-y-5">

                @forelse ($order->lines as $line)
                    <div class="receipt-section flex items-start justify-between gap-4">

                        <div class="min-w-0 flex-1">

                            <p class="text-sm font-semibold text-text-primary">
                                <span class="qr-numeric">{{ $line->qty }}×</span>
                                {{ $line->name_snapshot }}
                            </p>

                            {{-- #36, rule 04: guest notes echo in saffron everywhere. --}}
                            @if ($line->note)
                                <p class="mt-1 text-caption leading-relaxed font-medium text-[var(--warning-fg)]" data-guest-note>
                                    {{ $line->note }}
                                </p>
                            @endif

                        </div>

                        <span class="qr-numeric shrink-0 text-sm font-semibold">
                            {{ $formatMoney($line->line_total->amount()) }}
                        </span>

                    </div>
                @empty
                    <p class="text-sm text-text-secondary">
                        No items available.
                    </p>
                @endforelse

            </div>

        </section>

{{-- Total --}}
<section class="receipt-section border-t border-dashed border-border-default px-6 py-6">

    <div class="space-y-3 text-sm">

        <div class="flex items-center justify-between gap-4">
            <span class="text-text-secondary">Subtotal</span>
            <span class="qr-numeric font-medium">
                {{ $formatMoney($order->subtotal->amount()) }}
            </span>
        </div>

        @if ($order->service_amount->amount() > 0)
            <div class="flex items-center justify-between gap-4">
                <span class="text-text-secondary">
                    Service charge ({{ (float) $order->service_pct }}%)
                </span>
                <span class="qr-numeric font-medium">
                    {{ $formatMoney($order->service_amount->amount()) }}
                </span>
            </div>
        @endif

        @if ($order->tip_amount->amount() > 0)
            <div class="flex items-center justify-between gap-4">
                <span class="text-text-secondary">Tip</span>
                <span class="qr-numeric font-medium">
                    {{ $formatMoney($order->tip_amount->amount()) }}
                </span>
            </div>
        @endif

        @if ($order->discount_amount->amount() > 0)
            <div class="flex items-center justify-between gap-4">
                <span class="text-text-secondary">Discount</span>
                <span class="qr-numeric font-medium">
                    -{{ $formatMoney($order->discount_amount->amount()) }}
                </span>
            </div>
        @endif

    </div>

    <div class="mt-5 flex items-center justify-between gap-4 border-t border-border-default pt-5">
        <span class="font-display text-lg font-semibold">
            Order total
        </span>

        <span class="qr-numeric text-xl font-bold text-text-brand">
            {{ $formatMoney($totalMinor) }}
        </span>
    </div>

</section>


        {{-- Split payments --}}
        <section class="border-t border-border-subtle bg-surface-sunken px-6 py-6">

            <h2 class="mb-5 font-display text-lg font-semibold">
                Payment summary
            </h2>

            <div class="space-y-4">

                @forelse ($payments as $payment)
                    @php
                        $method = $payment->method instanceof \BackedEnum
                            ? $payment->method->value
                            : (string) $payment->method;
                    @endphp

                    <div class="receipt-section flex items-start justify-between gap-3">

                        <div>
                            <p class="text-sm font-medium">
                                {{ $methodLabels[$method] ?? ucfirst($method) }}
                            </p>

                            @if ($payment->paid_at)
                                <p class="qr-numeric mt-1 text-xs text-text-secondary">
                                    {{ $payment->paid_at->format('d M Y, H:i') }}
                                </p>
                            @endif
                        </div>

                        <span class="qr-numeric text-sm font-semibold">
                            {{ $formatMoney($payment->amount->amount()) }}
                        </span>

                    </div>

                @empty
                    <p class="text-sm text-text-secondary">
                        No payments received yet.
                    </p>
                @endforelse

            </div>

            <div class="mt-5 border-t border-border-default pt-5">

                <div class="flex justify-between gap-3 text-sm">
                    <span class="text-text-secondary">
                        Amount paid
                    </span>

                    <span class="qr-numeric font-semibold">
                        {{ $formatMoney($paidMinor) }}
                    </span>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl px-4 py-4
                    {{ $balanceMinor === 0
                        ? 'bg-status-paid-bg text-status-paid-fg'
                        : 'bg-warning-bg text-warning-fg' }}">

                    <span class="text-sm font-semibold">
                        {{ $balanceMinor === 0 ? 'Payment complete' : 'Balance due' }}
                    </span>

                    <span class="qr-numeric font-bold">
                        {{ $formatMoney($balanceMinor) }}
                    </span>

                </div>

            </div>

        </section>

        {{-- Footer --}}
        <footer class="border-t border-border-subtle px-6 py-7 text-center">

            <p class="font-display text-base font-semibold">
                Thank you for visiting!
            </p>

            <p class="mt-2 text-xs text-text-secondary">
                Powered by QRestO
            </p>

        </footer>

    </div>

    {{-- Print button --}}
    <div class="no-print mt-6">

        <button
            type="button"
            onclick="window.print()"
            class="w-full cursor-pointer rounded-xl bg-action-primary px-6 py-4 text-sm font-semibold text-text-on-brand shadow-rest transition-colors hover:bg-action-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action-primary"
        >
            Print receipt
        </button>

        <p class="mt-3 text-center text-xs text-text-secondary">
            Use your browser's print dialog to print or save the receipt.
        </p>

    </div>

</main>

</body>
</html>
