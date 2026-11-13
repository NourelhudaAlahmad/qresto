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
import { activeLocale, formatMinor } from '@/lib/formatMoney';
import type { SharedTranslations } from '@/lib/i18n';
import { fill, useI18n } from '@/lib/i18n';

type ReportsCopy = SharedTranslations['staff']['reports'];

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

const PRESETS: {
    value: Exclude<Preset, 'custom'>;
    label: keyof ReportsCopy;
}[] = [
    { value: 'today', label: 'today' },
    { value: 'week', label: 'week' },
    { value: '3weeks', label: 'three_weeks' },
    { value: 'month', label: 'month' },
];

// Numbers: right-aligned mono with tabular figures. Text: left, UI type.
const NUM = 'text-end font-mono tabular-nums';
const TEXT = 'text-start';

function day(date: string, style: 'long' | 'short' = 'long'): string {
    return new Intl.DateTimeFormat(activeLocale(), {
        weekday: 'short',
        day: 'numeric',
        ...(style === 'long' ? { month: 'short' } : {}),
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

/** Payment method in the page language; in English, the CSV and PDF labels. */
export function methodLabel(
    method: string | null,
    labels: Record<string, string>,
): string {
    return method ? (labels[method] ?? method) : '';
}

/** "1 cover" or ":count covers", picked by count. */
function countLabel(one: string, other: string, count: number): string {
    return count === 1 ? one : fill(other, { count });
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
    const {
        staff: { reports: t },
    } = useI18n();
    const max = Math.max(1, ...series.map((point) => point.net_minor));
    const peak = series.find((point) => point.peak);

    return (
        <figure className="min-w-0">
            <figcaption className="text-label mb-3 flex flex-wrap items-baseline gap-x-3 font-semibold text-[var(--text-secondary)]">
                {t.sales_by_day}
                {peak && (
                    <span className="text-caption font-normal text-[var(--text-tertiary)]">
                        {fill(t.peak_on, { day: day(peak.date) })} ·{' '}
                        <Numeric className="font-mono">
                            {formatMinor(peak.net_minor, currency)}
                        </Numeric>
                    </span>
                )}
            </figcaption>

            <div
                role="img"
                aria-label={
                    peak
                        ? fill(t.sales_by_day_peak, { day: day(peak.date) })
                        : t.sales_by_day
                }
                className="flex h-40 items-end gap-[3px]"
            >
                {series.map((point) => (
                    <div
                        key={point.date}
                        data-peak={point.peak || undefined}
                        title={`${day(point.date)}: ${formatMinor(point.net_minor, currency)}`}
                        className="relative flex h-full min-w-0 flex-1 flex-col justify-end"
                    >
                        {point.peak && (
                            <span className="text-micro absolute -top-5 start-1/2 -translate-x-1/2 whitespace-nowrap font-semibold text-[var(--clay-600)] rtl:translate-x-1/2">
                                {t.peak}
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
    const {
        staff: { reports: t },
    } = useI18n();
    const covers = (count: number) =>
        countLabel(t.covers_one, t.covers_other, count);
    const orders = (count: number) =>
        countLabel(t.orders_one, t.orders_other, count);
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
            setError(t.end_before_start);

            return;
        }

        load({ preset: 'custom', start, end });
    }

    const qs = query(range);

    return (
        <>
            <Head title={t.head} />

            <div className="flex flex-col gap-4">
                {/* Filter bar: presets, custom range, CSV and PDF export. */}
                <section
                    aria-label={t.filters}
                    className="flex flex-wrap items-end gap-3 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 shadow-[var(--shadow-rest)]"
                >
                    <div
                        role="group"
                        aria-label={t.range}
                        className="flex flex-wrap gap-2"
                    >
                        {PRESETS.map((preset) => (
                            <ChipFilter
                                key={preset.value}
                                type="segment"
                                active={range.preset === preset.value}
                                onClick={() => load({ preset: preset.value })}
                            >
                                {t[preset.label] as string}
                            </ChipFilter>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-end gap-2">
                        <label className="text-caption flex flex-col gap-1 text-[var(--text-tertiary)]">
                            {t.from}
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
                            {t.to}
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
                            {t.apply}
                        </Button>
                    </div>

                    <span className="flex-1" />

                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a href={`/app/reports/export.csv?${qs}`}>
                                <Download className="size-4" aria-hidden />
                                {t.export_csv}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={`/app/reports/export.pdf?${qs}`}>
                                <FileText className="size-4" aria-hidden />
                                {t.export_pdf}
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
                        aria-label={t.totals}
                        className="grid grid-cols-1 gap-3 min-[560px]:grid-cols-3 min-[1100px]:grid-cols-1"
                    >
                        <Stat
                            label={t.gross}
                            value={formatMinor(totals.gross_minor, currency)}
                            hint={`${orders(totals.orders)} · ${covers(totals.covers)}`}
                        />
                        <Stat
                            label={t.service_charge}
                            value={formatMinor(totals.service_minor, currency)}
                        />
                        <Stat
                            label={t.refunded}
                            valueTone={
                                totals.refunds_minor > 0 ? 'danger' : 'default'
                            }
                            value={
                                totals.refunds_minor > 0
                                    ? `−${formatMinor(totals.refunds_minor, currency)}`
                                    : formatMinor(0, currency)
                            }
                        />
                    </section>
                </div>

                <section
                    aria-label={t.sales_table}
                    className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-rest)]"
                >
                    {rows.length === 0 ? (
                        <EmptyState
                            headline={t.empty_headline}
                            body={t.empty_body}
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
                                                {t.date}
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${TEXT}`}
                                            >
                                                {t.waiter_covers}
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                {t.orders}
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                {t.net}
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${NUM}`}
                                            >
                                                {t.tips}
                                            </th>
                                            <th
                                                scope="col"
                                                className={`p-3 font-semibold ${TEXT}`}
                                            >
                                                {t.method}
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
                                                        · {covers(row.covers)}
                                                    </span>
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {row.orders}
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {formatMinor(
                                                        row.net_minor,
                                                        currency,
                                                    )}
                                                </td>
                                                <td className={`p-3 ${NUM}`}>
                                                    {formatMinor(
                                                        row.tips_minor,
                                                        currency,
                                                    )}
                                                </td>
                                                <td className={`p-3 ${TEXT}`}>
                                                    {row.method && (
                                                        <Badge>
                                                            {methodLabel(
                                                                row.method,
                                                                t.methods,
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
                                                {t.total}
                                            </th>
                                            <td
                                                className={`p-3 ${TEXT} font-mono`}
                                            >
                                                {covers(totals.covers)}
                                            </td>
                                            <td className={`p-3 ${NUM}`}>
                                                {totals.orders}
                                            </td>
                                            <td
                                                className={`p-3 ${NUM}`}
                                                data-total="net"
                                            >
                                                {formatMinor(
                                                    totals.net_minor,
                                                    currency,
                                                )}
                                            </td>
                                            <td className={`p-3 ${NUM}`}>
                                                {formatMinor(
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
                                                {t.date}
                                            </dt>
                                            <dd className={TEXT}>
                                                {day(row.date)}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                {t.waiter}
                                            </dt>
                                            <dd className={TEXT}>
                                                {row.waiter} ·{' '}
                                                {covers(row.covers)}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                {t.orders}
                                            </dt>
                                            <dd className={NUM}>
                                                {row.orders}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                {t.net}
                                            </dt>
                                            <dd className={NUM}>
                                                {formatMinor(
                                                    row.net_minor,
                                                    currency,
                                                )}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                {t.tips}
                                            </dt>
                                            <dd className={NUM}>
                                                {formatMinor(
                                                    row.tips_minor,
                                                    currency,
                                                )}
                                            </dd>
                                            <dt className="text-[var(--text-tertiary)]">
                                                {t.method}
                                            </dt>
                                            <dd className={TEXT}>
                                                {methodLabel(
                                                    row.method,
                                                    t.methods,
                                                ) || '—'}
                                            </dd>
                                        </dl>
                                    </li>
                                ))}
                                <li className="sticky bottom-0 bg-[var(--surface-card)] p-4 font-bold">
                                    <dl className="text-body grid grid-cols-[auto_minmax(0,1fr)] gap-x-4">
                                        <dt>{t.total_net}</dt>
                                        <dd className={NUM}>
                                            {formatMinor(
                                                totals.net_minor,
                                                currency,
                                            )}
                                        </dd>
                                        <dt>{t.total_tips}</dt>
                                        <dd className={NUM}>
                                            {formatMinor(
                                                totals.tips_minor,
                                                currency,
                                            )}
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
