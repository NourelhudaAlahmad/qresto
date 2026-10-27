import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Stat } from '@/components/qresto/stat';
import { showToast } from '@/components/qresto/toast';
import { Tooltip } from '@/components/qresto/tooltip';

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
    key: string;
    label: string;
    status: 'ok' | 'warning' | 'down' | 'test' | 'unknown';
    detail: string;
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

// The design's MATRIX shows these four columns, in this order.
const DESIGN_ROLES = ['waiter', 'kitchen', 'manager', 'admin'];

const HEALTH_DOT: Record<Health['status'], string> = {
    ok: 'var(--herb-500)',
    test: 'var(--herb-500)',
    warning: 'var(--saffron-500)',
    down: 'var(--berry-500)',
    unknown: 'var(--ink-300)',
};

// Shared with the matrix header so the columns line up (design: 62px).
const MATRIX_GRID =
    'grid grid-cols-[minmax(176px,1.4fr)_repeat(4,62px)] gap-2 px-5';

function money(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(minor / 100);
    } catch {
        return `${Math.round(minor / 100)} ${currency}`;
    }
}

function currencySymbol(currency: string): string {
    try {
        return (
            new Intl.NumberFormat('en', {
                style: 'currency',
                currency,
                currencyDisplay: 'narrowSymbol',
            })
                .formatToParts(0)
                .find((part) => part.type === 'currency')?.value ?? ''
        );
    } catch {
        return '';
    }
}

function auditTime(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
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
    roleLabel,
    capabilityLabel,
    cell,
    pending,
    onToggle,
}: {
    roleLabel: string;
    capabilityLabel: string;
    cell: Cell;
    pending: boolean;
    onToggle: (granted: boolean) => void;
}) {
    const button = (
        <button
            type="button"
            role="switch"
            aria-checked={cell.granted}
            aria-label={`${capabilityLabel} for ${roleLabel}`}
            aria-disabled={cell.locked || undefined}
            aria-busy={pending || undefined}
            disabled={pending}
            onClick={() => {
                if (!cell.locked) {
                    onToggle(!cell.granted);
                }
            }}
            className={`mx-auto grid size-11 place-items-center rounded-[var(--radius-md)] font-mono text-[18px] outline-none transition-colors duration-[var(--duration-fast)] focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)] ${
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
    // Optimistic overrides keyed "role:capability"; the server's next
    // matrix replaces them.
    const [overrides, setOverrides] = useState<Record<string, boolean>>({});
    const [pending, setPending] = useState<string | null>(null);
    const [syncedMatrix, setSyncedMatrix] = useState(matrix);

    if (syncedMatrix !== matrix) {
        setSyncedMatrix(matrix);
        setOverrides({});
    }

    const roles = DESIGN_ROLES.map((key) =>
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
                        title: `${row.label} ${granted ? 'granted to' : 'removed from'} ${roleLabel}`,
                        description: 'Lands on their next sign-in.',
                    }),
                onError: (errors) => {
                    setOverrides((current) => {
                        const next = { ...current };
                        delete next[key];

                        return next;
                    });

                    showToast({
                        type: 'error',
                        title: 'Permission not changed',
                        description:
                            errors.capability ??
                            Object.values(errors)[0] ??
                            'Please try again.',
                    });
                },
                onFinish: () => setPending(null),
            },
        );
    }

    const { revenue, payment_split: split, menu, failed_payments } = summary;

    const failedHint =
        failed_payments.count === 0
            ? 'none this month'
            : failed_payments.count === failed_payments.recovered
              ? 'all recovered at the table'
              : `${failed_payments.recovered} of ${failed_payments.count} recovered`;

    const details: [string, ReactNode][] = [
        ['Name', restaurant.name],
        [
            'Service charge',
            <Numeric key="sc" className="font-mono">
                {restaurant.service_charge_pct}%
            </Numeric>,
        ],
        [
            'Currency',
            <Numeric key="cur" className="font-mono">
                {restaurant.currency} {currencySymbol(restaurant.currency)}
            </Numeric>,
        ],
        ['Languages', restaurant.languages.join(' · ')],
        [
            'Tables · QR codes',
            <Numeric key="qr" className="font-mono">
                {restaurant.tables_count} · {restaurant.tables_count} printed
            </Numeric>,
        ],
    ];

    return (
        <>
            <Head title="System" />

            <div className="flex flex-col gap-4">
                <section
                    aria-label="This month"
                    className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 min-[1100px]:grid-cols-[1.5fr_1fr_1fr_1fr]"
                >
                    <Stat
                        tone="inverse"
                        className="p-5"
                        label="Month to date"
                        value={money(revenue.net_of_fees_minor, currencyCode)}
                        hint="net of refunds and gateway fees"
                    />

                    <Stat
                        className="p-5"
                        label="Paid in app"
                        value={`${split.app_share}%`}
                        hint={
                            <span
                                role="progressbar"
                                aria-label="Paid in app"
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
                        label="Menu items live"
                        value={String(menu.live_count)}
                        hint={`${menu.sold_out} sold out · ${menu.scheduled} scheduled`}
                    />

                    <Stat
                        className="p-5"
                        label="Failed payments"
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
                                    Roles &amp; permissions
                                </PanelTitle>
                                <p className="text-caption mt-0.5 text-[var(--text-tertiary)]">
                                    Six roles. A permission granted here appears
                                    in that role’s sidebar on their next
                                    sign-in.
                                </p>
                            </div>

                            {/* Pinned first column, sideways scroll: never a reflow. */}
                            <div
                                role="region"
                                aria-label="Permission matrix"
                                tabIndex={0}
                                className="overflow-x-auto"
                            >
                                <div role="table" className="min-w-[480px]">
                                    <div
                                        role="row"
                                        className={`${MATRIX_GRID} text-label border-b border-[var(--border-subtle)] bg-[var(--surface-sunken)] py-2 font-semibold text-[var(--text-secondary)]`}
                                    >
                                        <span
                                            role="columnheader"
                                            className="sticky start-0 z-[1] -ms-5 bg-[var(--surface-sunken)] ps-5"
                                        >
                                            Capability
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
                                            className={`${MATRIX_GRID} text-body items-center border-b border-[var(--border-subtle)] py-1.5 last:border-0`}
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
                                                            roleLabel={
                                                                role.label
                                                            }
                                                            capabilityLabel={
                                                                row.label
                                                            }
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
                                Recent changes
                            </PanelTitle>

                            {audits.length === 0 ? (
                                <p className="text-body text-[var(--text-tertiary)]">
                                    No permission changes yet.
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
                                                {audit.granted
                                                    ? 'granted'
                                                    : 'removed'}{' '}
                                                {audit.capability}{' '}
                                                {audit.granted ? 'to' : 'from'}{' '}
                                                {audit.role}
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
                            <PanelTitle>Restaurant</PanelTitle>

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

                            <button
                                type="button"
                                onClick={() =>
                                    showToast({
                                        type: 'info',
                                        title: 'Restaurant settings are next',
                                        description:
                                            'Editing details and reprinting QR codes arrive with A2.',
                                    })
                                }
                                className="text-body mt-4 h-11 w-full rounded-[var(--radius-pill)] border border-[var(--border-default)] bg-[var(--surface-card)] font-semibold transition-colors duration-[var(--duration-fast)] hover:bg-[var(--ink-50)]"
                            >
                                Edit details · reprint QR codes
                            </button>
                        </Panel>

                        <Panel>
                            <PanelTitle className="mb-3">
                                System health
                            </PanelTitle>

                            <ul className="text-body flex flex-col gap-2">
                                {health.map((item) => (
                                    <li
                                        key={item.key}
                                        className="flex items-center gap-2"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="size-2 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    HEALTH_DOT[item.status],
                                            }}
                                        />
                                        <span className="flex-1 text-[var(--text-secondary)]">
                                            {item.label}
                                        </span>
                                        <span className="text-caption font-mono">
                                            {item.detail}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>
                </div>
            </div>
        </>
    );
}
