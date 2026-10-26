import { router } from '@inertiajs/react';
import { Activity, Clock3, RefreshCw, Users, Wallet } from 'lucide-react';
import { useState } from 'react';
import { useLiveData } from '@/hooks/useLiveData';

type Stats = Record<string, number | null | undefined>;

type Waiter = {
    id: number;
    name: string;
    initials?: string | null;
};

type Order = {
    id: number;
    code: string;
    status: string;
    table: string | number | null;
    guest_name: string | null;
    placed_at: string | null;
    age_minutes: number;
    total_minor: number;
    currency: string;
    paid: boolean;
    assigned_user_id?: number | null;
    assigned_waiter?:
        | string
        | {
              id?: number;
              name?: string;
          }
        | null;
};

type ShiftDetails = {
    starts_at?: string | null;
    ends_at?: string | null;
    shift_start?: string | null;
    shift_end?: string | null;
};

type TeamMember = {
    id: number;
    name: string;
    role?: string | null;
    job_title?: string | null;
    initials?: string | null;
    tables?: (string | number | { number?: string | number })[] | null;
    shift_start?: string | null;
    shift_end?: string | null;
    shift_starts_at?: string | null;
    shift_ends_at?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    shift?: ShiftDetails | null;
    order_count: number;
    on_shift: boolean;
};

type Props = {
    stats: Stats;
    orders: Order[];
    staff: TeamMember[];
    waiters: Waiter[];
    now: number;
    currency: string;
    warnMinutes: number;
    lateMinutes: number;
    pollIntervalMs: number;
};

const LIVE_PROPS = ['stats', 'orders', 'staff', 'now'];

function stat(stats: Stats, ...keys: string[]): number | null {
    for (const key of keys) {
        if (key in stats) {
            const value = stats[key];

            if (typeof value === 'number') {
                return value;
            }

            if (value === null) {
                return null;
            }
        }
    }

    return null;
}

function money(amount: number | null | undefined, currency: string): string {
    if (amount == null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'TRY',
            maximumFractionDigits: 2,
        }).format(amount / 100);
    } catch {
        return `${(amount / 100).toFixed(2)} ${currency || 'TRY'}`;
    }
}

function ageLabel(minutes: number | null | undefined): string {
    const age = Math.max(0, minutes ?? 0);

    if (age < 60) {
        return `${age} min`;
    }

    return `${Math.floor(age / 60)}h ${age % 60}m`;
}

function ageColor(
    minutes: number,
    warnMinutes: number,
    lateMinutes: number,
): string {
    if (minutes >= lateMinutes) {
        return 'text-berry-500';
    }

    if (minutes >= warnMinutes) {
        return 'text-saffron-700';
    }

    return 'text-text-tertiary';
}

function titleCase(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function tableName(table: Order['table']): string {
    if (table == null || table === '') {
        return '—';
    }

    return typeof table === 'number' ? `Table ${table}` : table;
}

function waiterName(order: Order): string {
    if (typeof order.assigned_waiter === 'string') {
        return order.assigned_waiter;
    }

    return order.assigned_waiter?.name ?? 'Unassigned';
}

function assignedWaiterId(order: Order, waiters: Waiter[]): string {
    if (order.assigned_user_id != null) {
        return String(order.assigned_user_id);
    }

    if (
        typeof order.assigned_waiter === 'object' &&
        order.assigned_waiter?.id != null
    ) {
        return String(order.assigned_waiter.id);
    }

    const name = waiterName(order);

    return String(waiters.find((waiter) => waiter.name === name)?.id ?? '');
}

function clockTime(value?: string | null): string {
    if (!value) {
        return '';
    }

    // Already formatted as HH:mm.
    if (/^\d{2}:\d{2}$/.test(value)) {
        return value;
    }

    // Support timestamps containing more than three fractional digits.
    const normalizedValue = value.replace(
        /(\.\d{3})\d+(?=Z|[+-]\d{2}:?\d{2}$)/,
        '$1',
    );

    const date = new Date(normalizedValue);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

function shiftStart(member: TeamMember): string {
    return clockTime(
        member.shift_start ??
            member.shift_starts_at ??
            member.starts_at ??
            member.shift?.shift_start ??
            member.shift?.starts_at,
    );
}

function shiftEnd(member: TeamMember): string {
    return clockTime(
        member.shift_end ??
            member.shift_ends_at ??
            member.ends_at ??
            member.shift?.shift_end ??
            member.shift?.ends_at,
    );
}

function StatCard({
    label,
    value,
    detail,
    icon,
}: {
    label: string;
    value: string;
    detail: string;
    icon: React.ReactNode;
}) {
    return (
        <section className="bg-surface-card border-border-subtle min-w-0 rounded-[14px] border p-4 sm:p-5">
            <div className="flex items-center justify-between gap-2">
                <p className="text-text-secondary text-sm">{label}</p>
                <span className="text-text-tertiary">{icon}</span>
            </div>

            <p
                className="text-text-primary mt-4 truncate text-2xl font-semibold tracking-tight sm:text-3xl"
                dir="ltr"
                data-numeric
            >
                {value}
            </p>

            <p className="text-text-tertiary mt-2 text-xs leading-5">
                {detail}
            </p>
        </section>
    );
}

export default function ManagerOverview({
    stats,
    orders,
    staff,
    waiters,
    currency,
    warnMinutes,
    lateMinutes,
    pollIntervalMs,
}: Props) {
    const [busyOrderId, setBusyOrderId] = useState<number | null>(null);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');

    const { isPaused, resume } = useLiveData(pollIntervalMs, {
        only: LIVE_PROPS,
    });

    const covers = stat(stats, 'covers', 'cover_count', 'covers_count');
    const netSales = stat(stats, 'net_sales_minor', 'netSalesMinor');

    const averagePerCover = stat(
        stats,
        'avg_per_cover_minor',
        'average_per_cover_minor',
        'avgPerCoverMinor',
        'averagePerCoverMinor',
    );

    const lastWeekAverage = stat(
        stats,
        'avg_per_cover_last_week_minor',
        'last_week_avg_per_cover_minor',
        'last_week_average_per_cover_minor',
        'avgPerCoverLastWeekMinor',
        'lastWeekAveragePerCoverMinor',
    );

    const kitchenMedian = stat(
        stats,
        'median_prep_minutes',
        'kitchen_median_minutes',
        'median_kitchen_minutes',
        'kitchenMedianMinutes',
    );

    const lateOrders = stat(
        stats,
        'late_orders_count',
        'late_orders',
        'late_order_count',
        'lateOrders',
        'lateOrderCount',
    );

    const unpaid = stat(
        stats,
        'unpaid_total_minor',
        'unpaid_at_table_minor',
        'unpaidAtTableMinor',
    );

    const openBills = stat(
        stats,
        'open_bills',
        'open_bill_count',
        'openBills',
        'openBillCount',
    );

    const oldestBill = stat(
        stats,
        'oldest_bill_minutes',
        'oldest_bill_age_minutes',
        'oldestBillMinutes',
    );

    function assignOrder(order: Order, waiterId: string) {
        if (!waiterId || busyOrderId !== null) {
            return;
        }

        if (waiterId === assignedWaiterId(order, waiters)) {
            return;
        }

        setError('');
        setNotice('');

        router.patch(
            `/app/orders/${order.id}/assign`,
            { assigned_user_id: Number(waiterId) },
            {
                preserveScroll: true,

                onStart: () => {
                    setBusyOrderId(order.id);
                },

                onSuccess: () => {
                    setNotice(`Assignment updated for ${order.code}.`);
                },

                onError: (errors) => {
                    setError(
                        errors.assigned_user_id ??
                            'Could not reassign this order. Please try again.',
                    );
                },

                onFinish: () => {
                    setBusyOrderId(null);
                },
            },
        );
    }

    return (
        <main className="bg-surface-page text-text-primary min-h-screen p-4 sm:p-6 lg:p-8">
            <div className="mx-auto max-w-[1600px] space-y-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-text-tertiary text-xs font-medium uppercase tracking-[0.16em]">
                            Restaurant operations
                        </p>

                        <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                            Manager Overview
                        </h1>

                        <p className="text-text-secondary mt-2 text-sm">
                            Monitor service performance and your floor team.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => {
                            resume();
                            router.reload({ only: LIVE_PROPS });
                        }}
                        className="border-border-subtle bg-surface-card hover:bg-surface-page inline-flex items-center gap-2 rounded-full border px-4 py-2.5 text-sm font-medium transition"
                    >
                        <RefreshCw
                            size={15}
                            className={isPaused ? '' : 'animate-spin'}
                        />
                        {isPaused ? 'Resume live updates' : 'Refresh dashboard'}
                    </button>
                </header>

                {(notice || error) && (
                    <div
                        role="status"
                        className={`rounded-xl border px-4 py-3 text-sm ${
                            error
                                ? 'border-berry-500/30 text-berry-500'
                                : 'border-border-subtle text-text-secondary'
                        }`}
                    >
                        {error || notice}

                        <button
                            type="button"
                            onClick={() => {
                                setNotice('');
                                setError('');
                            }}
                            className="ml-3 underline underline-offset-4"
                        >
                            Dismiss
                        </button>
                    </div>
                )}

                <section
                    aria-label="Today's service statistics"
                    className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4"
                >
                    <StatCard
                        label="Covers today"
                        value={covers == null ? '—' : covers.toLocaleString()}
                        detail={
                            averagePerCover == null
                                ? 'Guests served today'
                                : `Avg / cover ${money(averagePerCover, currency)}${
                                      lastWeekAverage == null
                                          ? ''
                                          : ` · Last week ${money(lastWeekAverage, currency)}`
                                  }`
                        }
                        icon={<Users size={18} />}
                    />

                    <StatCard
                        label="Net sales"
                        value={money(netSales, currency)}
                        detail="For the current service day"
                        icon={<Wallet size={18} />}
                    />

                    <StatCard
                        label="Kitchen median"
                        value={
                            kitchenMedian == null
                                ? '—'
                                : `${kitchenMedian.toFixed(1)} min`
                        }
                        detail={`${lateOrders ?? 0} active order(s) over ${lateMinutes} min`}
                        icon={<Activity size={18} />}
                    />

                    <StatCard
                        label="Unpaid at tables"
                        value={money(unpaid, currency)}
                        detail={`${openBills ?? 0} open bill(s) · Oldest ${
                            oldestBill == null ? '—' : ageLabel(oldestBill)
                        }`}
                        icon={<Clock3 size={18} />}
                    />
                </section>

                <div className="grid grid-cols-1 gap-6 min-[1100px]:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)]">
                    <section className="bg-surface-card border-border-subtle min-w-0 overflow-hidden rounded-[14px] border">
                        <div className="border-border-subtle flex flex-wrap items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                            <div>
                                <h2 className="font-semibold">Active orders</h2>

                                <p className="text-text-tertiary mt-1 text-xs">
                                    All waiters · Oldest orders first
                                </p>
                            </div>

                            <span className="bg-surface-page rounded-full px-3 py-1 text-xs font-medium">
                                {orders.length} active
                            </span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-left text-sm">
                                <thead className="text-text-tertiary bg-surface-page/60 text-xs">
                                    <tr>
                                        <th className="px-4 py-3 font-medium sm:px-5">
                                            Order
                                        </th>

                                        <th className="px-3 py-3 font-medium">
                                            Status
                                        </th>

                                        <th className="px-3 py-3 font-medium">
                                            Total
                                        </th>

                                        <th className="hidden px-3 py-3 font-medium sm:table-cell">
                                            Age
                                        </th>

                                        <th className="px-3 py-3 font-medium">
                                            Waiter
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-border-subtle divide-y">
                                    {orders.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-4 py-4 sm:px-5">
                                                <p className="font-mono text-sm font-medium">
                                                    {order.code.startsWith('#')
                                                        ? order.code
                                                        : `#${order.code}`}
                                                </p>

                                                <p className="text-text-tertiary mt-1 text-xs">
                                                    {tableName(order.table)}
                                                    {order.guest_name
                                                        ? ` · ${order.guest_name}`
                                                        : ''}
                                                </p>
                                            </td>

                                            <td className="px-3 py-4">
                                                <span className="bg-surface-page text-text-secondary inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs">
                                                    {titleCase(order.status)}
                                                </span>
                                            </td>

                                            <td className="whitespace-nowrap px-3 py-4">
                                                {money(
                                                    order.total_minor,
                                                    order.currency || currency,
                                                )}
                                            </td>

                                            <td className="hidden px-3 py-4 sm:table-cell">
                                                <span
                                                    className={`font-mono text-xs ${ageColor(
                                                        order.age_minutes,
                                                        warnMinutes,
                                                        lateMinutes,
                                                    )}`}
                                                    dir="ltr"
                                                    data-numeric
                                                >
                                                    {ageLabel(
                                                        order.age_minutes,
                                                    )}
                                                </span>
                                            </td>

                                            <td className="px-3 py-4">
                                                <label
                                                    className="sr-only"
                                                    htmlFor={`waiter-${order.id}`}
                                                >
                                                    Assign waiter for{' '}
                                                    {order.code}
                                                </label>

                                                <select
                                                    id={`waiter-${order.id}`}
                                                    value={assignedWaiterId(
                                                        order,
                                                        waiters,
                                                    )}
                                                    disabled={
                                                        busyOrderId !== null ||
                                                        waiters.length === 0
                                                    }
                                                    onChange={(event) =>
                                                        assignOrder(
                                                            order,
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="border-border-subtle bg-surface-card max-w-[150px] rounded-full border px-2 py-2 text-xs disabled:opacity-50"
                                                >
                                                    <option value="">
                                                        {waiterName(order)}
                                                    </option>

                                                    {waiters.map((waiter) => (
                                                        <option
                                                            key={waiter.id}
                                                            value={String(
                                                                waiter.id,
                                                            )}
                                                        >
                                                            {waiter.name}
                                                        </option>
                                                    ))}
                                                </select>

                                                {busyOrderId === order.id && (
                                                    <span className="text-text-tertiary mt-1 block text-[10px]">
                                                        Saving…
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}

                                    {orders.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="text-text-tertiary px-5 py-12 text-center"
                                            >
                                                <Activity
                                                    size={24}
                                                    className="mx-auto mb-3 opacity-50"
                                                />
                                                No active orders right now.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="bg-surface-card border-border-subtle min-w-0 overflow-hidden rounded-[14px] border">
                        <div className="border-border-subtle flex items-center justify-between border-b px-4 py-4 sm:px-5">
                            <div>
                                <h2 className="font-semibold">Floor team</h2>

                                <p className="text-text-tertiary mt-1 text-xs">
                                    Shift coverage and workload
                                </p>
                            </div>

                            <Users size={18} className="text-text-tertiary" />
                        </div>

                        <div className="divide-border-subtle divide-y">
                            {staff.map((member) => {
                                const start = shiftStart(member);
                                const end = shiftEnd(member);

                                const tables = (member.tables ?? [])
                                    .map((table) => {
                                        if (
                                            typeof table === 'object' &&
                                            table !== null
                                        ) {
                                            return table.number == null
                                                ? ''
                                                : String(table.number);
                                        }

                                        return String(table);
                                    })
                                    .filter(Boolean);

                                return (
                                    <article
                                        key={member.id}
                                        className="flex items-start gap-3 px-4 py-4 sm:px-5"
                                    >
                                        <div className="bg-surface-page flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                                            {member.initials ||
                                                member.name
                                                    .split(/\s+/)
                                                    .slice(0, 2)
                                                    .map((part) => part[0])
                                                    .join('')
                                                    .toUpperCase()}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="truncate text-sm font-medium">
                                                    {member.name}
                                                </p>

                                                <span className="text-text-tertiary text-[10px]">
                                                    {member.job_title ||
                                                        member.role ||
                                                        'Staff'}
                                                </span>
                                            </div>

                                            <p className="text-text-tertiary mt-1 text-xs">
                                                {tables.length
                                                    ? `Tables ${tables.join(', ')}`
                                                    : 'No tables assigned'}
                                            </p>

                                            <p className="text-text-tertiary mt-1 text-xs">
                                                {start && end
                                                    ? `${start}–${end}`
                                                    : 'Shift time unavailable'}
                                                {' · '}
                                                {member.order_count} active
                                                order(s)
                                            </p>
                                        </div>

                                        <span
                                            className={`mt-1 size-2 shrink-0 rounded-full ${
                                                member.on_shift
                                                    ? 'bg-success-fg'
                                                    : 'bg-border-subtle'
                                            }`}
                                            title={
                                                member.on_shift
                                                    ? 'On shift'
                                                    : 'Off shift'
                                            }
                                        />
                                    </article>
                                );
                            })}

                            {staff.length === 0 && (
                                <p className="text-text-tertiary px-5 py-12 text-center text-sm">
                                    No floor team members found.
                                </p>
                            )}
                        </div>
                    </section>
                </div>

                <footer className="text-text-tertiary flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span>
                        {isPaused
                            ? 'Live updates paused while this tab is inactive.'
                            : 'Dashboard refreshes automatically.'}
                    </span>

                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className={`size-1.5 rounded-full ${
                                isPaused ? 'bg-border-subtle' : 'bg-success-fg'
                            }`}
                        />
                        {isPaused ? 'Paused' : 'Live'}
                    </span>
                </footer>
            </div>
        </main>
    );
}
