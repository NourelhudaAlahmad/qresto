import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Stat } from '@/components/qresto/stat';
import { showToast } from '@/components/qresto/toast';
import { Tooltip } from '@/components/qresto/tooltip';
import { useLiveData } from '@/hooks/useLiveData';
import { formatAgo } from '@/lib/elapsed';
import { activeLocale, currencySymbol, formatMinor } from '@/lib/formatMoney';
import { fill, useI18n } from '@/lib/i18n';
import type { SharedTranslations } from '@/lib/i18n';

type Copy = SharedTranslations['staff']['admin_system'];

type Cell = { granted: boolean; locked: boolean; reason: string | null };

type MatrixRow = {
    capability: string;
    label: string;
    cells: Record<string, Cell>;
};

type Matrix = {
    roles: { key: string; label: string }[];
    rows: MatrixRow[];
};

type Audit = {
    id: number;
    actor: string;
    role: string;
    capability: string;
    granted: boolean;
    at: string;
};

type Summary = {
    revenue: {
        mtd_minor: number;
        settled_minor: number;
        refunds_minor: number;
        fees_minor: number;
        net_of_fees_minor: number;
    };
    payment_split: {
        app_minor: number;
        cash_minor: number;
        pos_minor: number;
        app_share: number;
    };
    menu: { live_count: number; sold_out: number; scheduled: number };
    failed_payments: { count: number; recovered: number };
};

type Health = {
    id: number;
    kind: 'gateway' | 'kds' | 'printer';
    name: string;
    /** Derived on the server from the heartbeat age (#30). */
    health: 'operational' | 'degraded' | 'failing' | 'unknown';
    detail: string | null;
    last_heartbeat_at: string | null;
};

type Props = {
    summary: Summary;
    matrix: Matrix;
    audits: Audit[];
    restaurant: {
        name: string;
        service_charge_pct: number;
        currency: string;
        languages: string[];
        tables_count: number;
    };
    health: Health[];
    currencyCode: string;
};

const RELOAD = ['matrix', 'audits'];

// The design's MATRIX draws waiter, kitchen, manager and admin; runner and
// cashier join them so all six roles ("Six roles.") can be configured.
// Least to most privileged, the design's four keeping their order.
const MATRIX_ROLES = [
    'waiter',
    'runner',
    'cashier',
    'kitchen',
    'manager',
    'admin',
];

const HEALTH_DOT: Record<Health['health'], string> = {
    operational: 'var(--herb-500)',
    degraded: 'var(--saffron-500)',
    failing: 'var(--berry-500)',
    unknown: 'var(--ink-300)',
};

const HEALTH_TEXT: Record<Health['health'], string> = {
    operational: 'text-[var(--text-primary)]',
    degraded: 'text-[var(--saffron-700)]',
    failing: 'text-[var(--berry-500)] font-semibold',
    unknown: 'text-[var(--text-tertiary)]',
};

/** "just now", "4 min ago", "2h 10m ago", or the page's word for never. */
export function lastSeen(
    iso: string | null,
    now: number,
    never: string,
): string {
    if (iso === null) {
        return never;
    }

    return formatAgo(iso, now);
}

/** What the row says, before "last seen". */
export function statusText(
    item: Pick<Health, 'health' | 'detail' | 'last_heartbeat_at'>,
    t: Pick<
        Copy,
        | 'operational'
        | 'degraded'
        | 'failing'
        | 'no_heartbeat'
        | 'stopped_reporting'
    >,
): string {
    switch (item.health) {
        case 'operational':
            return t.operational;
        case 'unknown':
            return item.last_heartbeat_at === null
                ? t.no_heartbeat
                : t.stopped_reporting;
        default:
            return item.detail ?? t[item.health];
    }
}

// Shared with the matrix header so the columns line up (design: 62px).
const MATRIX_GRID =
    'grid grid-cols-[minmax(176px,1.4fr)_repeat(6,62px)] gap-2 px-5';

function auditTime(iso: string): string {
    return new Intl.DateTimeFormat(activeLocale(), {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function Panel({ children }: { children: ReactNode }) {
    return (
        <section className="min-w-0 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-rest)]">
            {children}
        </section>
    );
}

function PanelTitle({
    children,
    className = 'mb-4',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <h2 className={`font-display text-title-2 font-semibold ${className}`}>
            {children}
        </h2>
    );
}

/**
 * A matrix cell drawn as the design draws it (✓ in herb, — in ink-300),
 * but a real switch: click to grant or revoke.
 */
function MatrixCell({
    label,
    cell,
    pending,
    onToggle,
}: {
    label: string;
    cell: Cell;
    pending: boolean;
    onToggle: (granted: boolean) => void;
}) {
    const button = (
        <button
            type="button"
            role="switch"
            aria-checked={cell.granted}
            aria-label={label}
            aria-disabled={cell.locked || undefined}
            aria-busy={pending || undefined}
            disabled={pending}
            onClick={() => {
                if (!cell.locked) {
                    onToggle(!cell.granted);
                }
            }}
            className={`touch-target duration-fast mx-auto grid h-10 w-11 place-items-center rounded-[var(--radius-md)] font-mono text-[18px] outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)] ${
                cell.granted
                    ? 'text-[var(--herb-500)]'
                    : 'text-[var(--ink-300)]'
            } ${
                cell.locked
                    ? 'cursor-not-allowed'
                    : 'cursor-pointer hover:bg-[var(--action-ghost-hover)]'
            } ${pending ? 'opacity-50' : ''}`}
        >
            {cell.granted ? '✓' : '—'}
        </button>
    );

    return cell.locked ? (
        <Tooltip content={cell.reason}>{button}</Tooltip>
    ) : (
        button
    );
}

export default function AdminSystem({
    summary,
    matrix,
    audits,
    restaurant,
    health,
    currencyCode,
}: Props) {
    const {
        staff: { admin_system: t, pages },
    } = useI18n();

    // Optimistic overrides keyed "role:capability"; the server's next
    // matrix replaces them.
    const [overrides, setOverrides] = useState<Record<string, boolean>>({});
    const [pending, setPending] = useState<string | null>(null);
    const [syncedMatrix, setSyncedMatrix] = useState(matrix);
    const [now, setNow] = useState(() => Date.now());

    // The health card is live (#30); "last seen" ages between polls.
    useLiveData(30_000, { only: ['health'] });

    useEffect(() => {
        const tick = window.setInterval(() => setNow(Date.now()), 30_000);

        return () => window.clearInterval(tick);
    }, []);

    if (syncedMatrix !== matrix) {
        setSyncedMatrix(matrix);
        setOverrides({});
    }

    const roles = MATRIX_ROLES.map((key) =>
        matrix.roles.find((role) => role.key === key),
    ).filter((role): role is Matrix['roles'][number] => role !== undefined);

    const cellFor = (role: string, row: MatrixRow): Cell => {
        const key = `${role}:${row.capability}`;
        const cell = row.cells[role];

        return key in overrides ? { ...cell, granted: overrides[key] } : cell;
    };

    function toggle(
        role: string,
        roleLabel: string,
        row: MatrixRow,
        granted: boolean,
    ) {
        const key = `${role}:${row.capability}`;

        if (pending !== null) {
            return;
        }

        setOverrides((current) => ({ ...current, [key]: granted }));
        setPending(key);

        router.patch(
            '/app/system/permissions',
            { role, capability: row.capability, granted },
            {
                preserveScroll: true,
                preserveState: true,
                only: RELOAD,
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: fill(
                            granted ? t.granted_toast : t.removed_toast,
                            { capability: row.label, role: roleLabel },
                        ),
                        description: t.next_sign_in,
                    }),
                onError: (errors) => {
                    setOverrides((current) => {
                        const next = { ...current };
                        delete next[key];

                        return next;
                    });

                    showToast({
                        type: 'error',
                        title: t.not_changed,
                        description:
                            errors.capability ??
                            Object.values(errors)[0] ??
                            t.try_again,
                    });
                },
                onFinish: () => setPending(null),
            },
        );
    }

    const { revenue, payment_split: split, menu, failed_payments } = summary;

    const failedHint =
        failed_payments.count === 0
            ? t.failed_none
            : failed_payments.count === failed_payments.recovered
              ? t.failed_all_recovered
              : fill(t.failed_recovered, {
                    recovered: failed_payments.recovered,
                    count: failed_payments.count,
                });

    const details: [string, ReactNode][] = [
        [t.name, restaurant.name],
        [
            t.service_charge,
            <Numeric key="sc" className="font-mono">
                {restaurant.service_charge_pct}%
            </Numeric>,
        ],
        [
            t.currency,
            <Numeric key="cur" className="font-mono">
                {restaurant.currency} {currencySymbol(restaurant.currency)}
            </Numeric>,
        ],
        [t.languages, restaurant.languages.join(' · ')],
        [
            t.tables_qr,
            <Numeric key="qr" className="font-mono">
                {fill(t.tables_printed, {
                    count: restaurant.tables_count,
                    printed: restaurant.tables_count,
                })}
            </Numeric>,
        ],
    ];

    return (
        <>
            <Head title={pages.system} />

            <div className="flex flex-col gap-4">
                <section
                    aria-label={t.this_month}
                    className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 min-[1100px]:grid-cols-[1.5fr_1fr_1fr_1fr]"
                >
                    <Stat
                        tone="inverse"
                        className="p-5"
                        label={t.month_to_date}
                        value={formatMinor(
                            revenue.net_of_fees_minor,
                            currencyCode,
                            { digits: 0 },
                        )}
                        hint={t.net_hint}
                    />

                    <Stat
                        className="p-5"
                        label={t.paid_in_app}
                        value={`${split.app_share}%`}
                        hint={
                            <span
                                role="progressbar"
                                aria-label={t.paid_in_app}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={split.app_share}
                                className="mt-2 block h-2 w-full overflow-hidden rounded-[var(--radius-pill)] bg-[var(--ink-100)]"
                            >
                                <span
                                    className="block h-full bg-[var(--clay-500)]"
                                    style={{ width: `${split.app_share}%` }}
                                />
                            </span>
                        }
                    />

                    <Stat
                        className="p-5"
                        label={t.menu_live}
                        value={String(menu.live_count)}
                        hint={fill(t.menu_hint, {
                            sold_out: menu.sold_out,
                            scheduled: menu.scheduled,
                        })}
                    />

                    <Stat
                        className="p-5"
                        label={t.failed_payments}
                        value={String(failed_payments.count)}
                        valueTone={
                            failed_payments.count > 0 ? 'danger' : 'default'
                        }
                        hint={failedHint}
                    />
                </section>

                <div className="grid grid-cols-1 items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                    <div className="flex min-w-0 flex-col gap-4">
                        <section className="min-w-0 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-card)]">
                            <div className="border-b border-[var(--border-subtle)] px-5 py-4">
                                <PanelTitle className="">
                                    {t.roles_permissions}
                                </PanelTitle>
                                <p className="text-caption mt-0.5 text-[var(--text-tertiary)]">
                                    {t.roles_intro}
                                </p>
                            </div>

                            {/* Pinned first column, sideways scroll: never a reflow. */}
                            <div
                                role="region"
                                aria-label={t.matrix}
                                tabIndex={0}
                                className="overflow-x-auto"
                            >
                                <div role="table" className="min-w-[620px]">
                                    <div
                                        role="row"
                                        className={`${MATRIX_GRID} text-label border-b border-[var(--border-subtle)] bg-[var(--surface-sunken)] py-2 font-semibold text-[var(--text-secondary)]`}
                                    >
                                        <span
                                            role="columnheader"
                                            className="sticky start-0 z-[1] -ms-5 bg-[var(--surface-sunken)] ps-5"
                                        >
                                            {t.capability}
                                        </span>
                                        {roles.map((role) => (
                                            <span
                                                key={role.key}
                                                role="columnheader"
                                                className="text-center"
                                            >
                                                {role.label}
                                            </span>
                                        ))}
                                    </div>

                                    {matrix.rows.map((row) => (
                                        <div
                                            key={row.capability}
                                            role="row"
                                            className={`${MATRIX_GRID} text-body items-center border-b border-[var(--border-subtle)] py-[3px] last:border-0`}
                                        >
                                            <span
                                                role="rowheader"
                                                className="sticky start-0 z-[1] -ms-5 bg-[var(--surface-card)] ps-5"
                                            >
                                                {row.label}
                                            </span>
                                            {roles.map((role) => {
                                                const key = `${role.key}:${row.capability}`;

                                                return (
                                                    <span
                                                        key={role.key}
                                                        role="cell"
                                                    >
                                                        <MatrixCell
                                                            label={fill(
                                                                t.cell_label,
                                                                {
                                                                    capability:
                                                                        row.label,
                                                                    role: role.label,
                                                                },
                                                            )}
                                                            cell={cellFor(
                                                                role.key,
                                                                row,
                                                            )}
                                                            pending={
                                                                pending === key
                                                            }
                                                            onToggle={(
                                                                granted,
                                                            ) =>
                                                                toggle(
                                                                    role.key,
                                                                    role.label,
                                                                    row,
                                                                    granted,
                                                                )
                                                            }
                                                        />
                                                    </span>
                                                );
                                            })}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>

                        <Panel>
                            <PanelTitle className="mb-3">
                                {t.recent_changes}
                            </PanelTitle>

                            {audits.length === 0 ? (
                                <p className="text-body text-[var(--text-tertiary)]">
                                    {t.no_changes}
                                </p>
                            ) : (
                                <ul className="text-body flex flex-col gap-2">
                                    {audits.map((audit) => (
                                        <li
                                            key={audit.id}
                                            className="flex flex-wrap items-baseline gap-x-2"
                                        >
                                            <span className="font-medium">
                                                {audit.actor}
                                            </span>
                                            <span className="flex-1 text-[var(--text-secondary)]">
                                                {fill(
                                                    audit.granted
                                                        ? t.audit_granted
                                                        : t.audit_removed,
                                                    {
                                                        capability:
                                                            audit.capability,
                                                        role: audit.role,
                                                    },
                                                )}
                                            </span>
                                            <Numeric className="text-caption font-mono text-[var(--text-tertiary)]">
                                                {auditTime(audit.at)}
                                            </Numeric>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>
                    </div>

                    <div className="flex min-w-0 flex-col gap-4">
                        <Panel>
                            <PanelTitle>{t.restaurant}</PanelTitle>

                            <dl className="flex flex-col gap-3">
                                {details.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="text-body flex items-baseline gap-3"
                                    >
                                        <dt className="flex-1 text-[var(--text-secondary)]">
                                            {label}
                                        </dt>
                                        <dd className="text-end">{value}</dd>
                                    </div>
                                ))}
                            </dl>

                            <Link
                                href="/app/settings/restaurant"
                                className="text-body duration-fast mt-4 flex h-11 w-full items-center justify-center rounded-[var(--radius-pill)] border border-[var(--border-default)] bg-[var(--surface-card)] font-semibold transition-colors hover:bg-[var(--ink-50)]"
                            >
                                {t.edit_details}
                            </Link>
                        </Panel>

                        <Panel>
                            <PanelTitle className="mb-3">
                                {t.system_health}
                            </PanelTitle>

                            <ul className="text-body flex flex-col">
                                {health.map((item) => (
                                    <li
                                        key={item.id}
                                        data-health={item.health}
                                        className={`flex items-center gap-2 border-b border-[var(--border-subtle)] py-2 last:border-0 ${
                                            item.health === 'failing'
                                                ? '-mx-2 rounded-[var(--radius-sm)] bg-[var(--danger-bg)] px-2'
                                                : item.health === 'degraded'
                                                  ? '-mx-2 rounded-[var(--radius-sm)] bg-[var(--warning-bg)] px-2'
                                                  : ''
                                        }`}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="size-2 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    HEALTH_DOT[item.health],
                                            }}
                                        />
                                        <span className="min-w-0 flex-1 truncate text-[var(--text-secondary)]">
                                            {item.name}
                                        </span>
                                        <Tooltip
                                            content={item.detail ?? t.no_detail}
                                        >
                                            <span
                                                tabIndex={0}
                                                className="flex flex-col items-end text-end"
                                            >
                                                <span
                                                    className={`text-caption font-mono ${HEALTH_TEXT[item.health]}`}
                                                >
                                                    {statusText(item, t)}
                                                </span>
                                                <span className="text-micro text-[var(--text-tertiary)]">
                                                    {fill(t.last_seen, {
                                                        time: lastSeen(
                                                            item.last_heartbeat_at,
                                                            now,
                                                            t.never,
                                                        ),
                                                    })}
                                                </span>
                                            </span>
                                        </Tooltip>
                                    </li>
                                ))}

                                {health.length === 0 && (
                                    <li className="text-body text-[var(--text-tertiary)]">
                                        {t.nothing_connected}
                                    </li>
                                )}
                            </ul>
                        </Panel>
                    </div>
                </div>
            </div>
        </>
    );
}
