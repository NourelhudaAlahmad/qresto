import { ChevronDown, Ellipsis, X } from 'lucide-react';
import { useMemo, useState } from 'react';

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

function minutesOld(placedAt: string | null, now: number): number {
    return placedAt
        ? Math.max(0, Math.floor((now - Date.parse(placedAt)) / 60000))
        : 0;
}

function money(minor: number, currency: string): string {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency: currency || 'TRY',
    }).format(minor / 100);
}

function actionLabel(status: OrderStatus): string | null {
    return {
        placed: 'Accept order',
        pending: 'Start cooking',
        preparing: 'Mark ready',
        ready: 'Mark served',
        served: null,
    }[status];
}

function statusLabel(status: OrderStatus): string {
    return statusGroup(status) === 'new'
        ? 'New'
        : status.charAt(0).toUpperCase() + status.slice(1);
}

function statusClasses(status: OrderStatus): string {
    switch (statusGroup(status)) {
        case 'new':
            return 'bg-saffron-50 text-saffron-700';
        case 'preparing':
            return 'bg-teal-50 text-teal-700';
        case 'ready':
            return 'bg-clay-50 text-clay-700';
        case 'served':
            return 'bg-success-bg text-success-fg';
    }
}

function Age({
    order,
    now,
    warnMinutes,
    lateMinutes,
}: Pick<Props, 'now' | 'warnMinutes' | 'lateMinutes'> & { order: Ticket }) {
    const age = minutesOld(order.placed_at, now);
    const color =
        age >= lateMinutes
            ? 'text-berry-500'
            : age >= warnMinutes
              ? 'text-saffron-700'
              : 'text-text-tertiary';

    return (
        <span className={`whitespace-nowrap font-mono ${color}`}>
            {age} min
        </span>
    );
}

function TicketOverlay({
    order,
    onClose,
    onAdvance,
    onVoid,
    canAdvance,
    canTakePayment,
    busy,
    now,
    warnMinutes,
    lateMinutes,
}: Omit<Props, 'orders' | 'userId'> & { order: Ticket; onClose: () => void }) {
    const action = canAdvance ? actionLabel(order.status) : null;
    const canVoid =
        canAdvance && ['placed', 'pending', 'preparing'].includes(order.status);

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            role="presentation"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="list-ticket-title"
                className="bg-surface-card flex max-h-[90dvh] w-full flex-col overflow-hidden rounded-t-2xl shadow-xl sm:max-w-lg sm:rounded-2xl"
            >
                <div className="border-border-subtle flex items-start justify-between gap-3 border-b p-5">
                    <div className="min-w-0">
                        <h2
                            id="list-ticket-title"
                            className="text-text-primary font-mono text-lg font-semibold"
                        >
                            {order.code.startsWith('#')
                                ? order.code
                                : `#${order.code}`}
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Table {order.table ?? '—'} ·{' '}
                            {order.guest_name || 'Guest'}
                        </p>
                    </div>
                    <button
                        aria-label="Close ticket"
                        type="button"
                        onClick={onClose}
                        className="border-border-subtle text-text-secondary flex size-11 shrink-0 items-center justify-center rounded-full border"
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <div className="space-y-4 overflow-y-auto p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <span
                            className={`rounded-full px-3 py-1 text-sm font-medium ${statusClasses(order.status)}`}
                        >
                            {statusLabel(order.status)}
                        </span>
                        <Age
                            order={order}
                            now={now}
                            warnMinutes={warnMinutes}
                            lateMinutes={lateMinutes}
                        />
                    </div>
                    <div className="space-y-3">
                        {order.lines.map((line) => (
                            <div key={line.id}>
                                <p className="text-text-primary break-words text-sm">
                                    {line.qty}× {line.name}
                                </p>
                                {line.note && (
                                    <p className="bg-saffron-50 text-saffron-700 mt-1 rounded-md p-2 text-sm">
                                        {line.note}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="border-border-subtle flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                        <span
                            className={
                                order.paid
                                    ? 'text-success-fg'
                                    : 'text-saffron-700'
                            }
                        >
                            {order.paid ? 'Paid' : 'Unpaid'}
                        </span>
                        <strong className="text-text-primary whitespace-nowrap font-mono">
                            {money(order.total_minor, order.currency)}
                        </strong>
                    </div>
                </div>
                <div className="border-border-subtle bg-surface-card flex flex-wrap gap-2 border-t p-4">
                    {action ? (
                        <button
                            type="button"
                            disabled={busy !== null}
                            onClick={() => {
                                onAdvance(order);
                                onClose();
                            }}
                            className="bg-clay-500 min-h-11 min-w-0 flex-1 rounded-lg px-3 py-2 text-sm font-semibold !text-white disabled:opacity-50"
                        >
                            {action}
                        </button>
                    ) : (
                        <span className="text-text-secondary flex min-h-11 flex-1 items-center text-sm">
                            {order.status === 'served' && canTakePayment
                                ? 'Payment handled in S5'
                                : 'No action available'}
                        </span>
                    )}
                    {canVoid && (
                        <button
                            type="button"
                            disabled={busy !== null}
                            onClick={() => {
                                onVoid(order);
                                onClose();
                            }}
                            className="border-berry-500 text-berry-500 min-h-11 rounded-lg border px-4 text-sm font-semibold disabled:opacity-50"
                        >
                            Void order
                        </button>
                    )}
                </div>
            </div>
        </div>
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
    const [selected, setSelected] = useState<Ticket | null>(null);
    const visible = useMemo(
        () => sortOrders(filterOrders(orders, filter), sort),
        [orders, filter, sort],
    );

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
        <section className="min-w-0 space-y-4" aria-label="Order list">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div
                    className="flex max-w-full flex-wrap gap-2"
                    role="group"
                    aria-label="Filter order status"
                >
                    {FILTERS.map((item) => {
                        const count = filterOrders(orders, item.value).length;

                        return (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={filter === item.value}
                                onClick={() => chooseFilter(item.value)}
                                className={`min-h-11 rounded-full px-3 text-sm font-medium ${filter === item.value ? 'bg-ink-900 !text-white' : 'border-border-subtle bg-surface-card text-text-secondary border'}`}
                            >
                                {item.label} ({count})
                            </button>
                        );
                    })}
                </div>
                <label className="text-text-secondary flex min-h-11 items-center gap-2 text-sm">
                    Sort by
                    <span className="relative">
                        <select
                            aria-label="Sort orders"
                            value={sort}
                            onChange={(event) =>
                                chooseSort(event.target.value as SortKey)
                            }
                            className="border-border-subtle bg-surface-card text-text-primary min-h-11 appearance-none rounded-lg border py-2 pe-9 ps-3"
                        >
                            <option value="age">Age (oldest first)</option>
                            <option value="table">Table</option>
                            <option value="total">Total (highest first)</option>
                        </select>
                        <ChevronDown className="text-text-tertiary pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2" />
                    </span>
                </label>
            </div>

            {visible.length === 0 ? (
                <div className="border-border-subtle bg-surface-card text-text-tertiary rounded-xl border border-dashed p-8 text-center text-sm">
                    Nothing here right now
                </div>
            ) : (
                <>
                    <div className="border-border-subtle bg-surface-card hidden min-w-0 overflow-hidden rounded-xl border min-[720px]:block">
                        <table className="w-full table-fixed border-collapse text-sm">
                            <thead className="bg-surface-page text-text-secondary">
                                <tr className="border-border-subtle border-b text-start">
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
                                        className="border-border-subtle hover:bg-surface-page cursor-pointer border-b last:border-0"
                                        onClick={() => setSelected(order)}
                                    >
                                        <td className="text-text-primary break-words p-3 font-mono">
                                            <button
                                                type="button"
                                                className="min-h-11 text-start"
                                                onClick={() =>
                                                    setSelected(order)
                                                }
                                            >
                                                {order.code.startsWith('#')
                                                    ? order.code
                                                    : `#${order.code}`}
                                            </button>
                                        </td>
                                        <td className="text-text-secondary break-words p-3">
                                            {order.table ?? '—'} ·{' '}
                                            {order.guest_name || 'Guest'}
                                        </td>
                                        <td className="p-3">
                                            <span
                                                className={`inline-block rounded-full px-2 py-1 text-xs ${statusClasses(order.status)}`}
                                            >
                                                {statusLabel(order.status)}
                                            </span>
                                        </td>
                                        <td className="text-text-primary whitespace-nowrap p-3 text-end font-mono">
                                            {money(
                                                order.total_minor,
                                                order.currency,
                                            )}
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
                                onClick={() => setSelected(order)}
                                className="border-border-subtle bg-surface-card min-w-0 rounded-xl border p-4 text-start shadow-sm"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <strong className="text-text-primary font-mono">
                                        {order.code.startsWith('#')
                                            ? order.code
                                            : `#${order.code}`}
                                    </strong>
                                    <span
                                        className={`rounded-full px-2 py-1 text-xs ${statusClasses(order.status)}`}
                                    >
                                        {statusLabel(order.status)}
                                    </span>
                                </div>
                                <dl className="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2 text-sm">
                                    <dt className="text-text-tertiary">
                                        Table · Guest
                                    </dt>
                                    <dd className="text-text-primary min-w-0 break-words">
                                        {order.table ?? '—'} ·{' '}
                                        {order.guest_name || 'Guest'}
                                    </dd>
                                    <dt className="text-text-tertiary">
                                        Total
                                    </dt>
                                    <dd className="text-text-primary min-w-0 break-words font-mono">
                                        {money(
                                            order.total_minor,
                                            order.currency,
                                        )}
                                    </dd>
                                    <dt className="text-text-tertiary">Age</dt>
                                    <dd>
                                        <Age
                                            order={order}
                                            now={now}
                                            warnMinutes={warnMinutes}
                                            lateMinutes={lateMinutes}
                                        />
                                    </dd>
                                </dl>
                                <span className="text-clay-500 mt-3 flex min-h-11 items-center gap-1 text-sm font-medium">
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
                    order={
                        orders.find((order) => order.id === selected.id) ??
                        selected
                    }
                    onClose={() => setSelected(null)}
                />
            )}
        </section>
    );
}
