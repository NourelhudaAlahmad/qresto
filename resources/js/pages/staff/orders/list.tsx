import { ChevronDown, Ellipsis } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { EmptyState } from '@/components/qresto/empty-state';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { StatusPill } from '@/components/qresto/status-pill';
import { formatMinutes } from '@/lib/elapsed';

export type OrderStatus =
    'placed' | 'pending' | 'preparing' | 'ready' | 'served';
export type StatusFilter = 'all' | 'new' | 'preparing' | 'ready' | 'served';
export type SortKey = 'age' | 'table' | 'total';
export type Ticket = {
    id: number;
    code: string;
    status: OrderStatus;
    table: string | number | null;
    guest_name: string | null;
    placed_at: string | null;
    total_minor: number;
    currency: string;
    paid: boolean;
    /** The table's id, so a cashier can open its checkout (#34). */
    table_id?: number | null;
    lines: { id: number; qty: number; name: string; note: string | null }[];
};

type Props = {
    orders: Ticket[];
    now: number;
    warnMinutes: number;
    lateMinutes: number;
    canAdvance: boolean;
    canTakePayment: boolean;
    busy: number | null;
    userId: number | string;
    onAdvance: (order: Ticket) => void;
    onVoid: (order: Ticket) => void;
    /**
     * When set, a served order gets a "Take payment" action here instead of
     * a pointer to the floor map. The cashier view (#34) opens the floor's
     * checkout with it.
     */
    onTakePayment?: (order: Ticket) => void;
};

const FILTERS: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'new', label: 'New' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'ready', label: 'Ready' },
    { value: 'served', label: 'Served' },
];

export function statusGroup(status: OrderStatus): Exclude<StatusFilter, 'all'> {
    return status === 'placed' || status === 'pending' ? 'new' : status;
}

export function sortOrders(orders: Ticket[], key: SortKey): Ticket[] {
    return [...orders].sort((a, b) => {
        if (key === 'total') {
            return b.total_minor - a.total_minor || a.id - b.id;
        }

        if (key === 'table') {
            return (
                String(a.table ?? '').localeCompare(
                    String(b.table ?? ''),
                    undefined,
                    {
                        numeric: true,
                    },
                ) || a.id - b.id
            );
        }

        // Oldest first. Missing placement dates come last.
        const aTime = a.placed_at
            ? Date.parse(a.placed_at)
            : Number.MAX_SAFE_INTEGER;
        const bTime = b.placed_at
            ? Date.parse(b.placed_at)
            : Number.MAX_SAFE_INTEGER;

        return aTime - bTime || a.id - b.id;
    });
}

export function filterOrders(orders: Ticket[], filter: StatusFilter): Ticket[] {
    return filter === 'all'
        ? orders
        : orders.filter((order) => statusGroup(order.status) === filter);
}

export function minutesOld(placedAt: string | null, now: number): number {
    return placedAt
        ? Math.max(0, Math.floor((now - Date.parse(placedAt)) / 60000))
        : 0;
}

export function elapsedLevel(
    minutes: number,
    warnMinutes: number,
    lateMinutes: number,
): 'normal' | 'warn' | 'late' {
    if (minutes >= lateMinutes) {
        return 'late';
    }

    if (minutes >= warnMinutes) {
        return 'warn';
    }

    return 'normal';
}

export function money(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'USD',
        }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

export function orderCode(code: string): string {
    return code.startsWith('#') ? code : `#${code}`;
}

export function actionLabel(status: OrderStatus): string | null {
    return {
        placed: 'Accept order',
        pending: 'Start cooking',
        preparing: 'Mark ready',
        ready: 'Mark served',
        served: null,
    }[status];
}

export function canVoidStatus(status: OrderStatus): boolean {
    return ['placed', 'pending', 'preparing'].includes(status);
}

const AGE_COLORS = {
    normal: 'text-[var(--text-tertiary)]',
    warn: 'text-[var(--saffron-700)] font-semibold',
    late: 'text-[var(--berry-500)] font-semibold',
} as const;

function Age({
    order,
    now,
    warnMinutes,
    lateMinutes,
}: Pick<Props, 'now' | 'warnMinutes' | 'lateMinutes'> & { order: Ticket }) {
    const age = minutesOld(order.placed_at, now);

    return (
        <Numeric
            className={`whitespace-nowrap font-mono ${
                AGE_COLORS[elapsedLevel(age, warnMinutes, lateMinutes)]
            }`}
        >
            {formatMinutes(age)}
        </Numeric>
    );
}

function TicketOverlay({
    order,
    onClose,
    onAdvance,
    onVoid,
    onTakePayment,
    canAdvance,
    canTakePayment,
    busy,
    now,
    warnMinutes,
    lateMinutes,
}: Omit<Props, 'orders' | 'userId'> & {
    order: Ticket;
    onClose: () => void;
}) {
    const action = canAdvance ? actionLabel(order.status) : null;
    const canVoid = canAdvance && canVoidStatus(order.status);

    return (
        <ResponsiveOverlay
            open
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
            title={<Numeric>{orderCode(order.code)}</Numeric>}
            description={`Table ${order.table ?? '—'} · ${order.guest_name || 'Guest'}`}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <StatusPill status={order.status} />

                    <Age
                        order={order}
                        now={now}
                        warnMinutes={warnMinutes}
                        lateMinutes={lateMinutes}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    {order.lines.map((line) => (
                        <div key={line.id}>
                            <p className="text-body break-words text-[var(--text-primary)]">
                                <Numeric className="text-[var(--text-tertiary)]">
                                    {line.qty}×
                                </Numeric>{' '}
                                {line.name}
                            </p>

                            {line.note && (
                                <p className="text-caption ms-6 mt-1 inline-block break-words rounded-[var(--radius-sm)] bg-[var(--saffron-50)] px-2 py-0.5 text-[var(--saffron-700)]">
                                    {line.note}
                                </p>
                            )}
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--border-subtle)] pt-3">
                    <span
                        className={`text-micro inline-flex h-6 items-center rounded-full px-2.5 font-semibold ${
                            order.paid
                                ? 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]'
                                : 'bg-[var(--status-pending-bg)] text-[var(--status-pending-fg)]'
                        }`}
                    >
                        {order.paid ? 'Paid' : 'Unpaid'}
                    </span>

                    <Numeric className="text-title-3 font-semibold">
                        {money(order.total_minor, order.currency)}
                    </Numeric>
                </div>

                <div className="flex flex-wrap gap-2 border-t border-[var(--border-subtle)] pt-3">
                    {!action &&
                    order.status === 'served' &&
                    canTakePayment &&
                    onTakePayment ? (
                        <Button
                            type="button"
                            variant="primary"
                            className="min-w-0 flex-1"
                            disabled={busy !== null}
                            onClick={() => {
                                onTakePayment(order);
                                onClose();
                            }}
                        >
                            Take payment
                        </Button>
                    ) : action ? (
                        <Button
                            type="button"
                            variant="primary"
                            className="min-w-0 flex-1"
                            disabled={busy !== null}
                            onClick={() => {
                                onAdvance(order);
                                onClose();
                            }}
                        >
                            {action}
                        </Button>
                    ) : (
                        <span className="text-caption flex min-h-11 flex-1 items-center text-[var(--text-secondary)]">
                            {order.status === 'served' && canTakePayment
                                ? 'Take payment from the floor map'
                                : 'No action available'}
                        </span>
                    )}

                    {canVoid && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-[var(--berry-500)] text-[var(--berry-500)]"
                            disabled={busy !== null}
                            onClick={() => {
                                onVoid(order);
                                onClose();
                            }}
                        >
                            Void order
                        </Button>
                    )}
                </div>
            </div>
        </ResponsiveOverlay>
    );
}

export default function OrderList(props: Props) {
    const { orders, userId, now, warnMinutes, lateMinutes } = props;
    const filterKey = `qresto:waiter:${userId}:list-filter`;
    const sortKey = `qresto:waiter:${userId}:list-sort`;
    const [filter, setFilter] = useState<StatusFilter>(() => {
        try {
            const stored = localStorage.getItem(filterKey);

            return FILTERS.some((item) => item.value === stored)
                ? (stored as StatusFilter)
                : 'all';
        } catch {
            return 'all';
        }
    });
    const [sort, setSort] = useState<SortKey>(() => {
        try {
            const stored = localStorage.getItem(sortKey);

            return stored === 'table' || stored === 'total' ? stored : 'age';
        } catch {
            return 'age';
        }
    });
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const visible = useMemo(
        () => sortOrders(filterOrders(orders, filter), sort),
        [orders, filter, sort],
    );
    // Follow the live collection so the sheet shows the latest status.
    const selected =
        selectedId === null
            ? null
            : (orders.find((order) => order.id === selectedId) ?? null);

    function chooseFilter(value: StatusFilter): void {
        setFilter(value);

        try {
            localStorage.setItem(filterKey, value);
        } catch {
            /* Optional preference. */
        }
    }

    function chooseSort(value: SortKey): void {
        setSort(value);

        try {
            localStorage.setItem(sortKey, value);
        } catch {
            /* Optional preference. */
        }
    }

    return (
        <section
            className="flex min-w-0 flex-col gap-4"
            aria-label="Order list"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div
                    className="flex max-w-full flex-wrap gap-2"
                    role="group"
                    aria-label="Filter order status"
                >
                    {FILTERS.map((item) => (
                        <ChipFilter
                            key={item.value}
                            type="segment"
                            active={filter === item.value}
                            onClick={() => chooseFilter(item.value)}
                        >
                            {`${item.label} (${filterOrders(orders, item.value).length})`}
                        </ChipFilter>
                    ))}
                </div>

                <label className="text-label flex min-h-11 items-center gap-2 text-[var(--text-secondary)]">
                    Sort by
                    <span className="relative">
                        <select
                            aria-label="Sort orders"
                            value={sort}
                            onChange={(event) =>
                                chooseSort(event.target.value as SortKey)
                            }
                            className="text-label min-h-11 appearance-none rounded-[var(--radius-md)] border border-[var(--border-default)] bg-[var(--surface-card)] py-2 pe-9 ps-3 text-[var(--text-primary)] outline-none focus-visible:border-[var(--border-focus-color)] focus-visible:shadow-[var(--ring-focus)]"
                        >
                            <option value="age">Age (oldest first)</option>
                            <option value="table">Table</option>
                            <option value="total">Total (highest first)</option>
                        </select>
                        <ChevronDown className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-[var(--text-tertiary)]" />
                    </span>
                </label>
            </div>

            {visible.length === 0 ? (
                <div className="rounded-[var(--radius-lg)] border border-dashed border-[var(--border-default)] bg-[var(--surface-card)]">
                    <EmptyState headline="Nothing here right now" />
                </div>
            ) : (
                <>
                    <div className="hidden min-w-0 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-card)] min-[720px]:block">
                        <table className="text-body w-full table-fixed border-collapse">
                            <thead className="text-label bg-[var(--surface-sunken)] text-[var(--text-secondary)]">
                                <tr className="border-b border-[var(--border-subtle)]">
                                    <th
                                        scope="col"
                                        className="w-[18%] p-3 text-start font-semibold"
                                    >
                                        Order
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[24%] p-3 text-start font-semibold"
                                    >
                                        Table · Guest
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[18%] p-3 text-start font-semibold"
                                    >
                                        Status
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[22%] p-3 text-end font-semibold"
                                    >
                                        Total
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[18%] p-3 text-end font-semibold"
                                    >
                                        Age
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((order) => (
                                    <tr
                                        key={order.id}
                                        className="cursor-pointer border-b border-[var(--border-subtle)] last:border-0 hover:bg-[var(--action-ghost-hover)]"
                                        onClick={() => setSelectedId(order.id)}
                                    >
                                        <td className="break-words p-3 font-mono text-[var(--text-primary)]">
                                            <button
                                                type="button"
                                                className="min-h-11 text-start"
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setSelectedId(order.id);
                                                }}
                                            >
                                                {orderCode(order.code)}
                                            </button>
                                        </td>
                                        <td className="break-words p-3 text-[var(--text-secondary)]">
                                            <Numeric>
                                                {order.table ?? '—'}
                                            </Numeric>{' '}
                                            · {order.guest_name || 'Guest'}
                                        </td>
                                        <td className="p-3">
                                            <StatusPill status={order.status} />
                                        </td>
                                        <td className="whitespace-nowrap p-3 text-end">
                                            <Numeric className="font-mono text-[var(--text-primary)]">
                                                {money(
                                                    order.total_minor,
                                                    order.currency,
                                                )}
                                            </Numeric>
                                        </td>
                                        <td className="p-3 text-end">
                                            <Age
                                                order={order}
                                                now={now}
                                                warnMinutes={warnMinutes}
                                                lateMinutes={lateMinutes}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid min-w-0 gap-3 min-[720px]:hidden">
                        {visible.map((order) => (
                            <button
                                type="button"
                                key={order.id}
                                onClick={() => setSelectedId(order.id)}
                                className="min-w-0 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-4 text-start shadow-[var(--shadow-card)] transition-transform duration-[var(--duration-fast)] active:scale-[var(--press-scale)]"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Numeric className="text-title-3 font-medium text-[var(--text-primary)]">
                                        {orderCode(order.code)}
                                    </Numeric>
                                    <StatusPill status={order.status} />
                                </div>
                                <dl className="text-body mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2">
                                    <dt className="text-[var(--text-tertiary)]">
                                        Table · Guest
                                    </dt>
                                    <dd className="min-w-0 break-words text-[var(--text-primary)]">
                                        <Numeric>{order.table ?? '—'}</Numeric>{' '}
                                        · {order.guest_name || 'Guest'}
                                    </dd>
                                    <dt className="text-[var(--text-tertiary)]">
                                        Total
                                    </dt>
                                    <dd className="min-w-0">
                                        <Numeric className="font-mono text-[var(--text-primary)]">
                                            {money(
                                                order.total_minor,
                                                order.currency,
                                            )}
                                        </Numeric>
                                    </dd>
                                    <dt className="text-[var(--text-tertiary)]">
                                        Age
                                    </dt>
                                    <dd>
                                        <Age
                                            order={order}
                                            now={now}
                                            warnMinutes={warnMinutes}
                                            lateMinutes={lateMinutes}
                                        />
                                    </dd>
                                </dl>
                                <span className="text-label mt-3 flex min-h-11 items-center gap-1 font-semibold text-[var(--text-brand)]">
                                    Open ticket <Ellipsis className="size-4" />
                                </span>
                            </button>
                        ))}
                    </div>
                </>
            )}

            {selected && (
                <TicketOverlay
                    {...props}
                    order={selected}
                    onClose={() => setSelectedId(null)}
                />
            )}
        </section>
    );
}
