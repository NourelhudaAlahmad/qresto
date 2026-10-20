<x-mail::message>
# Your QRestO Receipt

**Restaurant:** {{ $order->restaurant?->name ?? 'Restaurant' }}

**Order:** #{{ $order->id }}

**Table:** {{ $order->table?->number ?? '-' }}

**Date:** {{ $order->created_at?->format('d M Y, H:i') }}

@php
    $currency = $order->currency ?? $order->restaurant?->currency ?? 'TRY';
    $formatMoney = static fn (int $minor): string =>
        number_format($minor / 100, 2, '.', ',') . ' ' . $currency;

    $payments = $order->payments->filter(
        fn ($payment) => $payment->status === 'paid'
    );

    $paidMinor = (int) $payments->sum(
        fn ($payment) => $payment->amount->amount()
    );

    $totalMinor = $order->total->amount();
    $balanceMinor = max(0, $totalMinor - $paidMinor);
@endphp

## Order details

<x-mail::table>
| Item | Qty | Amount |
| :--- | ---: | ---: |
@foreach ($order->lines as $line)
| {{ $line->name_snapshot }} | {{ $line->qty }} | {{ $formatMoney($line->line_total->amount()) }} |
@endforeach
</x-mail::table>

**Subtotal:** {{ $formatMoney($order->subtotal->amount()) }}

**Order total:** {{ $formatMoney($totalMinor) }}

## Payment summary

@forelse ($payments as $payment)
- {{ strtoupper((string) $payment->method) }}: {{ $formatMoney($payment->amount->amount()) }}
@empty
No payments received yet.
@endforelse

**Amount paid:** {{ $formatMoney($paidMinor) }}

**Balance due:** {{ $formatMoney($balanceMinor) }}

Thank you for visiting!

QRestO
</x-mail::message>
