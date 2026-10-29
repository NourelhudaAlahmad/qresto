import { Head, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { useState } from 'react';

import { Numeric } from '@/components/numeric';
import { Badge } from '@/components/qresto/badge';
import { Button } from '@/components/qresto/button';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Stat } from '@/components/qresto/stat';

type Preset = 'today' | 'week' | '3weeks' | 'month' | 'custom';

type Row = {
    date: string;
    waiter: string;
    covers: number;
    orders: number;
    net_minor: number;
    tips_minor: number;
    method: string | null;
};

type Props = {
    range: { start: string; end: string; preset: Preset };
    currency: string;
    rows: Row[];
    totals: {
        gross_minor: number;
        service_minor: number;
        refunds_minor: number;
        tips_minor: number;
        net_minor: number;
        covers: number;
        orders: number;
    };
    series: { date: string; net_minor: number; peak: boolean }[];
};

const PRESETS: { value: Exclude<Preset, 'custom'>; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: 'week', label: 'This week' },
    { value: '3weeks', label: '3 weeks' },
    { value: 'month', label: 'This month' },
];

// Numbers: right-aligned mono with tabular figures. Text: left, UI type.
const NUM = 'text-end font-mono tabular-nums';
const TEXT = 'text-start';

export function money(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency,
        }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

function day(date: string, style: 'long' | 'short' = 'long'): string {
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        ...(style === 'long' ? { month: 'short' } : {}),
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

/** Same labels as the CSV and PDF. */
export function methodLabel(method: string | null): string {
    const labels: Record<string, string> = {
        pos: 'POS',
        card: 'Card',
        wallet: 'Wallet',
        cash: 'Cash',
    };

    return method ? (labels[method] ?? method) : '';
}

/** "Nadia Rahman" → "Nadia R." */
function shortName(name: string): string {
    const parts = name.trim().split(/\s+/);

    return parts.length < 2
        ? name
        : `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
}

function query(range: Props['range']): string {
    const params = new URLSearchParams({ preset: range.preset });

    if (range.preset === 'custom') {
        params.set('start', range.start);
        params.set('end', range.end);
    }

    return params.toString();
}

function SalesChart({
    series,
    currency,
}: {
    series: Props['series'];
    currency: string;
}) {
    const max = Math.max(1, ...series.map((point) => point.net_minor));
    const peak = series.find((point) => point.peak);

    return (
        <figure className="min-w-0">
            <figcaption className="text-label mb-3 flex flex-wrap items-baseline gap-x-3 font-semibold text-[var(--text-secondary)]">
                Sales by day
                {peak && (
                    <span className="text-caption font-normal text-[var(--text-tertiary)]">
                        Peak {day(peak.date)} ·{' '}
                        <Numeric className="font-mono">
                            {money(peak.net_minor, currency)}
                        </Numeric>
                    </span>
                )}
            </figcaption>

            <div
                role="img"
                aria-label={`Sales by day${peak ? `, peak on ${day(peak.date)}` : ''}`}
                className="flex h-40 items-end gap-[3px]"
            >
                {series.map((point) => (
                    <div
                        key={point.date}
                        data-peak={point.peak || undefined}
                        title={`${day(point.date)}: ${money(point.net_minor, currency)}`}
                        className="relative flex h-full min-w-0 flex-1 flex-col justify-end"
                    >
                        {point.peak && (
                            <span className="text-micro absolute -top-5 start-1/2 -translate-x-1/2 whitespace-nowrap font-semibold text-[var(--clay-600)] rtl:translate-x-1/2">
                                Peak
                            </span>
                        )}
                        <span
                            className={`block rounded-t-[var(--radius-xs)] ${
                                point.peak
                                    ? 'bg-[var(--clay-500)]'
                                    : 'bg-[var(--ink-200)]'
                            }`}
                            style={{
                                height: `${Math.max(2, (point.net_minor / max) * 100)}%`,
                            }}
                        />
                    </div>
                ))}
            </div>

            {series.length > 1 && (
                <div className="text-micro mt-1 flex justify-between font-mono text-[var(--text-tertiary)]">
                    <span>{day(series[0].date, 'short')}</span>
                    <span>{day(series[series.length - 1].date, 'short')}</span>
                </div>
            )}
        </figure>
    );
}

export default function Reports({
    range,
    currency,
    rows,
    totals,
    series,
}: Props) {
    const [start, setStart] = useState(range.start);
    const [end, setEnd] = useState(range.end);
    const [error, setError] = useState<string | null>(null);

    function load(next: Record<string, string>) {
        router.get('/app/reports', next, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => setError(Object.values(errors)[0] ?? null),
            onSuccess: () => setError(null),
        });
    }

    function applyCustom() {
        if (end < start) {
            setError('The end date must be on or after the start date.');

            return;
        }

        load({ preset: 'custom', start, end });
    }

    const qs = query(range);

    return (
        <>
            <Head title="Reports" />

            <div className="flex flex-col gap-4">
                {/* Filter bar: presets, custom range, CSV and PDF export. */}
                <section
                    aria-label="Filters"
                    className="flex flex-wrap items-end gap-3 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 shadow-[var(--shadow-rest)]"
                >
                    <div
                        role="group"
                        aria-label="Range"
                        className="flex flex-wrap gap-2"
                    >
                        {PRESETS.map((preset) => (
                            <ChipFilter
                                key={preset.value}
                                type="segment"
                                active={range.preset === preset.value}
                                onClick={() => load({ preset: preset.value })}
                            >
                                {preset.label}
                            </ChipFilter>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-caption flex flex-col gap-1 text-[var(--text-tertiary)]">
                            From
                            <Input
                                type="date"
                                dir="ltr"
                                className="w-40 font-mono"
                                value={start}
                                onChange={(event) =>
                                    setStart(event.target.value)
                                }
                            />
                        </label>
                        <label className="text-caption flex flex-col gap-1 text-[var(--text-tertiary)]">
                            To
                            <Input
                                type="date"
                                dir="ltr"
                                className="w-40 font-mono"
                                value={end}
                                aria-invalid={error ? true : undefined}
                                onChange={(event) => setEnd(event.target.value)}
                            />
                        </label>
                        <Button
                            type="button"
                            variant={
                                range.preset === 'custom'
                                    ? 'primary'
                                    : 'outline'
                            }
                            onClick={applyCustom}
                        >
                            Apply
                        </Button>
                    </div>

                    <span className="flex-1" />

                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a href={`/app/reports/export.csv?${qs}`}>
                                <Download className="size-4" aria-hidden />
                                CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={`/app/reports/export.pdf?${qs}`}>
                                <FileText className="size-4" aria-hidden />
                                PDF
                            </a>
                        </Button>
                    </div>

                    {error && (
                        <p
                            role="alert"
                            className="text-caption w-full text-[var(--berry-500)]"
                        >
                            {error}
                        </p>
                    )}
                </section>

                <div className="grid grid-cols-1 gap-4 min-[1100px]:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                    <section className="rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-rest)]">
                        <SalesChart series={series} currency={currency} />
                    </section>

                    {/* Three totals to anchor. */}
                    <section
                        aria-label="Totals"
                        className="grid grid-cols-1 gap-3 min-[560px]:grid-cols-3 min-[1100px]:grid-cols-1"
                    >
                        <Stat
                            label="Gross"
                            value={money(totals.gross_minor, currency)}
                            hint={`${totals.orders} orders · ${totals.covers} covers`}
                        />
                        <Stat
                            label="Service charge"
                            value={money(totals.service_minor, currency)}
                        />
                        <Stat
                            label="Refunded"
                            valueTone={
                                totals.refunds_minor > 0 ? 'danger' : 'default'
                            }
                            value={
                                totals.refunds_minor > 0
                                    ? `−${money(totals.refunds_minor, currency)}`
                                    : money(0, currency)
                            }
                        />
                    </section>
                </div>

                <section
                    aria-label="Sales by day and waiter"
                    className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-rest)]"
                >
                    {rows.length === 0 ? (
                        <EmptyState
                            headline="No settled sales in this range"
                            body="Pick another range above."
                        />
                    ) : (
                        <>
                            {/* ≥720px: one dense table, sticky total row. */}
                            <div className="hidden max-h-[560px] overflow-auto min-[720px]:block">
                                <table className="text-body w-full border-collapse">
                                    <thead className="text-label sticky top-0 bg-[var(--surface-sunken)] text-[var(--text-secondary)]">
                                        <tr>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${TEXT}`}
                                            >
                                                Date
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${TEXT}`}
                                            >
                                                Waiter · covers
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                Orders
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                Net
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                Tips
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${TEXT}`}
                                            >
                                                Method
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={`${row.date}-${row.waiter}`}
                                                className="border-b border-[var(--border-subtle)]"
                                            >
                                                <td className={`p-3 ${TEXT}`}>
                                                    {day(row.date)}
                                                </td>
                                                <td className={`p-3 ${TEXT}`}>
                                                    {shortName(row.waiter)}{' '}
                                                    <span className="text-[var(--text-tertiary)]">
                                                        ·{' '}
                                                        <Numeric>
                                                            {row.covers}
                                                        </Numeric>{' '}
                                                        covers
                                                    </span>
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {row.orders}
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {money(
                                                        row.net_minor,
                                                        currency,
                                                    )}
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {money(
                                                        row.tips_minor,
                                                        currency,
                                                    )}
                                                </td>
                                                <td className={`p-3 ${TEXT}`}>
                                                    {row.method && (
                                                        <Badge>
                                                            {methodLabel(
                                                                row.method,
                                                            )}
                                                        </Badge>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="sticky bottom-0 bg-[var(--surface-card)] font-bold">
                                        <tr className="border-t-2 border-[var(--ink-900)]">
                                            <th
                                                scope="row"
                                                className={`p-3 ${TEXT}`}
                                            >
                                                Total
                                            </th>
                                            <td
                                                className={`p-3 ${TEXT} font-mono`}
                                            >
                                                <Numeric>
                                                    {totals.covers}
                                                </Numeric>{' '}
                                                covers
                                            </td>
                                            <td className={`p-3 ${NUM}`}>
                                                {totals.orders}
                                            </td>
                                            <td
                                                className={`p-3 ${NUM}`}
                                                data-total="net"
                                            >
                                                {money(
                                                    totals.net_minor,
                                                    currency,
                                                )}
                                            </td>
                                            <td className={`p-3 ${NUM}`}>
                                                {money(
                                                    totals.tips_minor,
                                                    currency,
                                                )}
                                            </td>
                                            <td />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {/* <720px: a stack of cards, label–value pairs kept. */}
                            <ul className="flex flex-col min-[720px]:hidden">
                                {rows.map((row) => (
                                    <li
                                        key={`${row.date}-${row.waiter}`}
                                        className="border-b border-[var(--border-subtle)] p-4"
                                    >
                                        <dl className="text-body grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1">
                                            <dt className="text-[var(--text-tertiary)]">
                                                Date
                                            </dt>
                                            <dd className={TEXT}>
                                                {day(row.date)}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                Waiter
                                            </dt>
                                            <dd className={TEXT}>
                                                {row.waiter} · {row.covers}{' '}
                                                covers
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                Orders
                                            </dt>
                                            <dd className={NUM}>
                                                {row.orders}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                Net
                                            </dt>
                                            <dd className={NUM}>
                                                {money(row.net_minor, currency)}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                Tips
                                            </dt>
                                            <dd className={NUM}>
                                                {money(
                                                    row.tips_minor,
                                                    currency,
                                                )}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                Method
                                            </dt>
                                            <dd className={TEXT}>
                                                {methodLabel(row.method) || '—'}
                                            </dd>
                                        </dl>
                                    </li>
                                ))}
                                <li className="sticky bottom-0 bg-[var(--surface-card)] p-4 font-bold">
                                    <dl className="text-body grid grid-cols-[auto_minmax(0,1fr)] gap-x-4">
                                        <dt>Total net</dt>
                                        <dd className={NUM}>
                                            {money(totals.net_minor, currency)}
                                        </dd>
                                        <dt>Total tips</dt>
                                        <dd className={NUM}>
                                            {money(totals.tips_minor, currency)}
                                        </dd>
                                    </dl>
                                </li>
                            </ul>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
