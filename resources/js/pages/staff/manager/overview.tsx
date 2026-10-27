import { router } from '@inertiajs/react';
import * as Dialog from '@radix-ui/react-dialog';
import { Pencil, Plus, TrendingDown, TrendingUp, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
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
    tables?: string[];
    now: number;
    currency: string;
    warnMinutes: number;
    lateMinutes: number;
    pollIntervalMs: number;
};

type ToastState = {
    title: string;
    sub?: string;
    tone: 'ok' | 'error';
} | null;

const LIVE_PROPS = ['stats', 'orders', 'staff', 'now'];

const ROLE_OPTIONS = ['Waiter', 'Runner', 'Cashier', 'Kitchen'] as const;

// Full class names so Tailwind can see them.
const STATUS_PILL: Record<string, string> = {
    placed: 'bg-[var(--status-placed-bg)] text-[var(--status-placed-fg)]',
    pending: 'bg-[var(--status-pending-bg)] text-[var(--status-pending-fg)]',
    preparing:
        'bg-[var(--status-preparing-bg)] text-[var(--status-preparing-fg)]',
    ready: 'bg-[var(--status-ready-bg)] text-[var(--status-ready-fg)]',
    served: 'bg-[var(--status-served-bg)] text-[var(--status-served-fg)]',
    paid: 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]',
    cancelled:
        'bg-[var(--status-cancelled-bg)] text-[var(--status-cancelled-fg)]',
};

const AVATAR_TONES = [
    'bg-teal-100 text-teal-700',
    'bg-clay-100 text-clay-700',
    'bg-ink-100 text-ink-700',
];

const ORDER_GRID =
    'grid grid-cols-[84px_minmax(0,1fr)_88px_80px] gap-3 px-5 min-[720px]:grid-cols-[90px_minmax(0,1fr)_96px_92px_74px]';

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

function money(
    amount: number | null | undefined,
    currency: string,
    fractionDigits = 2,
): string {
    if (amount == null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'TRY',
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        }).format(amount / 100);
    } catch {
        return `${(amount / 100).toFixed(fractionDigits)} ${currency || 'TRY'}`;
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

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

function tableName(table: Order['table']): string {
    if (table == null || table === '') {
        return '—';
    }

    const raw = String(table);

    return /^\d+$/.test(raw) ? `Table ${raw.padStart(2, '0')}` : raw;
}

function waiterName(order: Order): string {
    if (typeof order.assigned_waiter === 'string') {
        return order.assigned_waiter;
    }

    return order.assigned_waiter?.name ?? 'Unassigned';
}

// "Nadia Rahman" → "Nadia R."
function shortName(name: string): string {
    const parts = name.trim().split(/\s+/);

    if (parts.length < 2) {
        return name;
    }

    return `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
}

function initialsOf(member: TeamMember): string {
    return (
        member.initials ||
        member.name
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase()
    );
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

function shiftEnd(member: TeamMember): string {
    return clockTime(
        member.shift_end ??
            member.shift_ends_at ??
            member.ends_at ??
            member.shift?.shift_end ??
            member.shift?.ends_at,
    );
}

function tableNumbers(member: TeamMember): string[] {
    return (member.tables ?? [])
        .map((table) => {
            if (typeof table === 'object' && table !== null) {
                return table.number == null ? '' : String(table.number);
            }

            return String(table);
        })
        .filter(Boolean);
}

function StatCard({
    label,
    value,
    hint,
    tone = 'secondary',
}: {
    label: string;
    value: string;
    hint: React.ReactNode;
    tone?: 'secondary' | 'positive' | 'negative' | 'warning';
}) {
    const toneClass = {
        secondary: 'text-text-secondary',
        positive: 'text-herb-700',
        negative: 'text-berry-500',
        warning: 'text-saffron-700',
    }[tone];

    return (
        <div className="border-border-subtle bg-surface-card min-w-0 rounded-[var(--radius-lg)] border p-4 shadow-[var(--shadow-rest)]">
            <div className="text-text-tertiary text-[length:var(--text-caption)]">
                {label}
            </div>

            <div
                className="truncate font-mono text-[length:var(--text-display-3)] leading-[1.1]"
                dir="ltr"
                data-numeric
            >
                {value}
            </div>

            <div
                className={`flex items-center gap-1 text-[length:var(--text-caption)] ${toneClass}`}
            >
                {hint}
            </div>
        </div>
    );
}

function CardHeading({
    children,
    aside,
}: {
    children: React.ReactNode;
    aside?: React.ReactNode;
}) {
    return (
        <div className="border-border-subtle flex items-baseline gap-3 border-b px-5 py-4">
            <h2 className="font-display text-[length:var(--text-title-2)] font-semibold">
                {children}
            </h2>

            {aside}
        </div>
    );
}

const fieldClass =
    'h-11 w-full rounded-[var(--radius-md)] border border-border-default bg-surface-card px-[14px] text-[length:var(--text-body)] outline-none placeholder:text-text-tertiary focus-visible:rounded-[var(--radius-md)] focus-visible:border-[var(--border-focus-color)] focus-visible:shadow-[var(--ring-focus)]';

type DrawerForm = {
    name: string;
    email: string;
    role: (typeof ROLE_OPTIONS)[number];
    tables: string[];
    sendLink: boolean;
};

const EMPTY_FORM: DrawerForm = {
    name: '',
    email: '',
    role: 'Waiter',
    tables: [],
    sendLink: true,
};

function WaiterForm({
    editing,
    tableOptions,
    onClose,
    onSubmit,
}: {
    editing: TeamMember | null;
    tableOptions: string[];
    onClose: () => void;
    onSubmit: (form: DrawerForm) => void;
}) {
    const [form, setForm] = useState<DrawerForm>(() =>
        editing
            ? {
                  ...EMPTY_FORM,
                  name: editing.name,
                  tables: tableNumbers(editing),
                  sendLink: false,
              }
            : EMPTY_FORM,
    );

    const [errors, setErrors] = useState<{ name?: string; email?: string }>({});

    function toggleTable(table: string) {
        setForm((current) => ({
            ...current,
            tables: current.tables.includes(table)
                ? current.tables.filter((value) => value !== table)
                : [...current.tables, table],
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const next: { name?: string; email?: string } = {};

        if (form.name.trim() === '') {
            next.name = 'Enter the waiter’s full name.';
        }

        if (!editing && !/^\S+@\S+\.\S+$/.test(form.email.trim())) {
            next.email = 'Enter a work email, like name@albustan.co.';
        }

        setErrors(next);

        if (Object.keys(next).length === 0) {
            onSubmit(form);
        }
    }

    return (
        <form
            onSubmit={submit}
            noValidate
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="border-border-subtle flex items-center gap-3 border-b p-5">
                <Dialog.Title className="font-display flex-1 text-[length:var(--text-title-2)] font-semibold">
                    {editing ? 'Edit waiter' : 'Add a waiter'}
                </Dialog.Title>

                <Dialog.Close
                    aria-label="Close"
                    className="hover:bg-action-ghost-hover grid size-11 place-items-center rounded-full"
                >
                    <X
                        className="text-ink-800 size-[19px]"
                        aria-hidden="true"
                    />
                </Dialog.Close>
            </div>

            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5">
                <label className="flex flex-col gap-1.5">
                    <span className="text-text-secondary text-[length:var(--text-label)] font-semibold">
                        Full name
                    </span>

                    <input
                        type="text"
                        value={form.name}
                        placeholder="Omar Faruk"
                        autoComplete="off"
                        aria-invalid={errors.name ? true : undefined}
                        onChange={(event) =>
                            setForm({ ...form, name: event.target.value })
                        }
                        className={fieldClass}
                    />

                    {errors.name && (
                        <span className="text-berry-500 text-[length:var(--text-caption)]">
                            {errors.name}
                        </span>
                    )}
                </label>

                <label className="flex flex-col gap-1.5">
                    <span className="text-text-secondary text-[length:var(--text-label)] font-semibold">
                        Work email
                    </span>

                    <input
                        type="email"
                        value={form.email}
                        placeholder="name@albustan.co"
                        autoComplete="off"
                        aria-invalid={errors.email ? true : undefined}
                        onChange={(event) =>
                            setForm({ ...form, email: event.target.value })
                        }
                        className={fieldClass}
                    />

                    {errors.email && (
                        <span className="text-berry-500 text-[length:var(--text-caption)]">
                            {errors.email}
                        </span>
                    )}
                </label>

                <div role="radiogroup" aria-label="Role">
                    <div className="text-text-secondary mb-1.5 text-[length:var(--text-label)] font-semibold">
                        Role
                    </div>

                    <div className="flex flex-wrap gap-1.5">
                        {ROLE_OPTIONS.map((role) => {
                            const selected = form.role === role;

                            return (
                                <button
                                    key={role}
                                    type="button"
                                    role="radio"
                                    aria-checked={selected}
                                    onClick={() => setForm({ ...form, role })}
                                    className={`inline-flex h-9 items-center rounded-[var(--radius-pill)] px-[14px] text-[length:var(--text-label)] ${
                                        selected
                                            ? 'bg-ink-900 text-ink-25 font-semibold'
                                            : 'border-border-default text-text-secondary hover:bg-ink-50 border'
                                    }`}
                                >
                                    {role}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div>
                    <div className="text-text-secondary mb-1.5 text-[length:var(--text-label)] font-semibold">
                        Tables tonight
                    </div>

                    {tableOptions.length === 0 ? (
                        <p className="text-text-tertiary text-[length:var(--text-caption)]">
                            No tables set up yet.
                        </p>
                    ) : (
                        <div className="grid grid-cols-4 gap-1.5">
                            {tableOptions.map((table) => {
                                const selected = form.tables.includes(table);

                                return (
                                    <button
                                        key={table}
                                        type="button"
                                        aria-pressed={selected}
                                        onClick={() => toggleTable(table)}
                                        className={`grid h-11 place-items-center rounded-[var(--radius-md)] font-mono ${
                                            selected
                                                ? 'bg-clay-500 text-white'
                                                : 'border-border-default text-text-secondary hover:bg-ink-50 border'
                                        }`}
                                    >
                                        {table}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {!editing && (
                    <button
                        type="button"
                        role="switch"
                        aria-checked={form.sendLink}
                        onClick={() =>
                            setForm({ ...form, sendLink: !form.sendLink })
                        }
                        className="text-text-secondary flex items-center gap-3 text-start text-[length:var(--text-body)]"
                    >
                        <span
                            aria-hidden="true"
                            className={`relative h-6 w-10 shrink-0 rounded-[var(--radius-pill)] transition-colors ${
                                form.sendLink ? 'bg-herb-500' : 'bg-ink-200'
                            }`}
                        >
                            <span
                                className={`absolute left-[3px] top-[3px] size-[18px] rounded-full bg-white transition-transform ${
                                    form.sendLink
                                        ? 'translate-x-4'
                                        : 'translate-x-0'
                                }`}
                            />
                        </span>
                        Send a set-password link now
                    </button>
                )}
            </div>

            <div className="border-border-subtle flex gap-2 border-t p-5">
                <button
                    type="button"
                    onClick={onClose}
                    className="border-border-default bg-surface-card hover:bg-ink-50 h-11 flex-1 rounded-[var(--radius-pill)] border text-[length:var(--text-body)] font-semibold"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    className="bg-action-primary hover:bg-action-primary-hover h-11 flex-1 rounded-[var(--radius-pill)] text-[length:var(--text-body)] font-semibold text-white"
                >
                    {editing ? 'Save changes' : 'Add to shift'}
                </button>
            </div>
        </form>
    );
}

function WaiterDrawer({
    open,
    editing,
    tableOptions,
    onClose,
    onSubmit,
}: {
    open: boolean;
    editing: TeamMember | null;
    tableOptions: string[];
    onClose: () => void;
    onSubmit: (form: DrawerForm) => void;
}) {
    return (
        <Dialog.Root
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 fixed inset-0 z-[80] bg-[var(--surface-scrim)] motion-reduce:animate-none" />

                <Dialog.Content
                    aria-describedby={undefined}
                    className="bg-surface-card data-[state=closed]:animate-out data-[state=closed]:slide-out-to-right data-[state=open]:animate-in data-[state=open]:slide-in-from-right fixed inset-y-0 end-0 z-[81] flex w-[380px] max-w-full flex-col shadow-[var(--shadow-pop)] outline-none duration-200 motion-reduce:animate-none"
                >
                    {/* Content unmounts when closed, so the form starts fresh on every open. */}
                    <WaiterForm
                        editing={editing}
                        tableOptions={tableOptions}
                        onClose={onClose}
                        onSubmit={onSubmit}
                    />
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

export default function ManagerOverview({
    stats,
    orders,
    staff,
    waiters,
    tables = [],
    now,
    currency,
    warnMinutes,
    lateMinutes,
    pollIntervalMs,
}: Props) {
    const [busyOrderId, setBusyOrderId] = useState<number | null>(null);
    const [reassigning, setReassigning] = useState(false);
    const [toast, setToast] = useState<ToastState>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState<TeamMember | null>(null);

    const { isPaused, resume } = useLiveData(pollIntervalMs, {
        only: LIVE_PROPS,
    });

    useEffect(() => {
        if (!toast) {
            return;
        }

        const timer = window.setTimeout(() => setToast(null), 4200);

        return () => window.clearTimeout(timer);
    }, [toast]);

    const covers = stat(stats, 'covers', 'cover_count', 'covers_count');

    const coversLastWeek = stat(stats, 'covers_last_week', 'last_week_covers');

    const netSales = stat(stats, 'net_sales_minor', 'netSalesMinor');

    const averagePerCover = stat(
        stats,
        'avg_per_cover_minor',
        'average_per_cover_minor',
        'avgPerCoverMinor',
        'averagePerCoverMinor',
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

    // Covers trend against the same weekday last week.
    const weekday = new Intl.DateTimeFormat('en', { weekday: 'long' }).format(
        new Date(now),
    );

    const coversDelta =
        covers != null && coversLastWeek != null && coversLastWeek > 0
            ? Math.round(((covers - coversLastWeek) / coversLastWeek) * 100)
            : null;

    function assignOrder(order: Order, waiterId: string) {
        if (!waiterId || busyOrderId !== null) {
            return;
        }

        if (waiterId === assignedWaiterId(order, waiters)) {
            return;
        }

        setToast(null);

        router.patch(
            `/app/orders/${order.id}/assign`,
            { assigned_user_id: Number(waiterId) },
            {
                preserveScroll: true,

                onStart: () => {
                    setBusyOrderId(order.id);
                },

                onSuccess: () => {
                    setToast({
                        tone: 'ok',
                        title: 'Assignment updated',
                        sub: order.code,
                    });
                },

                onError: (errors) => {
                    setToast({
                        tone: 'error',
                        title: 'Could not reassign',
                        sub:
                            errors.assigned_user_id ??
                            errors.order ??
                            'Please try again.',
                    });
                },

                onFinish: () => {
                    setBusyOrderId(null);
                },
            },
        );
    }

    function openDrawer(member: TeamMember | null) {
        setEditing(member);
        setDrawerOpen(true);
    }

    // The drawer is design-only for now: there is no endpoint that creates or
    // updates staff yet. Replace this with router.post(...) when it exists.
    function saveWaiter(form: { sendLink: boolean }) {
        setDrawerOpen(false);

        setToast(
            editing
                ? { tone: 'ok', title: 'Waiter updated', sub: 'Tables saved' }
                : {
                      tone: 'ok',
                      title: 'Waiter added to tonight',
                      sub: form.sendLink
                          ? 'Set-password link sent'
                          : 'No set-password link sent',
                  },
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {isPaused && (
                <div
                    role="status"
                    className="border-border-subtle bg-surface-card text-text-secondary flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-md)] border px-4 py-2.5 text-[length:var(--text-label)]"
                >
                    Live updates paused while this tab is inactive.
                    <button
                        type="button"
                        onClick={() => {
                            resume();
                            router.reload({ only: LIVE_PROPS });
                        }}
                        className="text-text-link font-semibold underline underline-offset-4"
                    >
                        Resume live updates
                    </button>
                </div>
            )}

            <section
                aria-label="Tonight's service statistics"
                className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 min-[1100px]:grid-cols-4"
            >
                <StatCard
                    label="Covers tonight"
                    value={covers == null ? '—' : covers.toLocaleString()}
                    tone={
                        coversDelta == null
                            ? 'secondary'
                            : coversDelta >= 0
                              ? 'positive'
                              : 'negative'
                    }
                    hint={
                        coversDelta == null ? (
                            'Guests seated tonight'
                        ) : (
                            <>
                                {coversDelta >= 0 ? (
                                    <TrendingUp size={13} aria-hidden="true" />
                                ) : (
                                    <TrendingDown
                                        size={13}
                                        aria-hidden="true"
                                    />
                                )}
                                {coversDelta >= 0 ? '+' : ''}
                                {coversDelta}% vs last {weekday}
                            </>
                        )
                    }
                />

                <StatCard
                    label="Net sales"
                    value={money(netSales, currency, 0)}
                    hint={
                        averagePerCover == null
                            ? 'For the current service day'
                            : `${money(averagePerCover, currency)} average per cover`
                    }
                />

                <StatCard
                    label="Kitchen time, median"
                    value={
                        kitchenMedian == null
                            ? '—'
                            : `${Math.round(kitchenMedian)}m`
                    }
                    tone="warning"
                    hint={`${plural(lateOrders ?? 0, 'order', 'orders')} over ${lateMinutes} min`}
                />

                <StatCard
                    label="Unpaid at table"
                    value={money(unpaid, currency, 0)}
                    hint={`${plural(openBills ?? 0, 'open bill', 'open bills')}${
                        oldestBill == null
                            ? ''
                            : `, oldest ${ageLabel(oldestBill)}`
                    }`}
                />
            </section>

            <div className="grid grid-cols-1 items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <section className="border-border-subtle bg-surface-card min-w-0 overflow-hidden rounded-[var(--radius-lg)] border shadow-[var(--shadow-card)]">
                    <CardHeading
                        aside={
                            <>
                                <span className="text-text-tertiary font-mono text-[length:var(--text-caption)]">
                                    live
                                </span>

                                <span className="flex-1" />

                                <button
                                    type="button"
                                    aria-pressed={reassigning}
                                    disabled={waiters.length === 0}
                                    onClick={() => setReassigning((on) => !on)}
                                    className="border-border-default bg-surface-card hover:bg-ink-50 h-[34px] rounded-[var(--radius-pill)] border px-[14px] text-[length:var(--text-label)] font-semibold disabled:opacity-50"
                                >
                                    {reassigning ? 'Done' : 'Reassign'}
                                </button>
                            </>
                        }
                    >
                        All active orders
                    </CardHeading>

                    <div
                        className={`${ORDER_GRID} bg-surface-sunken border-border-subtle text-text-secondary border-b py-2 text-[length:var(--text-label)] font-semibold`}
                    >
                        <span>Order</span>
                        <span>Table · waiter</span>
                        <span>Status</span>
                        <span className="text-end">Total</span>
                        <span className="hidden text-end min-[720px]:block">
                            Age
                        </span>
                    </div>

                    {orders.map((order) => (
                        <div
                            key={order.id}
                            className={`${ORDER_GRID} border-border-subtle items-center border-b py-3`}
                        >
                            <span className="font-mono text-[length:var(--text-body)]">
                                {order.code.startsWith('#')
                                    ? order.code
                                    : `#${order.code}`}
                            </span>

                            <span className="min-w-0 text-[length:var(--text-body)]">
                                {tableName(order.table)} ·{' '}
                                {reassigning ? (
                                    <>
                                        <label
                                            className="sr-only"
                                            htmlFor={`waiter-${order.id}`}
                                        >
                                            Assign waiter for {order.code}
                                        </label>

                                        <select
                                            id={`waiter-${order.id}`}
                                            value={assignedWaiterId(
                                                order,
                                                waiters,
                                            )}
                                            disabled={busyOrderId !== null}
                                            onChange={(event) =>
                                                assignOrder(
                                                    order,
                                                    event.target.value,
                                                )
                                            }
                                            className="border-border-default bg-surface-card h-8 max-w-full rounded-[var(--radius-pill)] border px-2 text-[length:var(--text-caption)] disabled:opacity-50"
                                        >
                                            <option value="">
                                                {waiterName(order)}
                                            </option>

                                            {waiters.map((waiter) => (
                                                <option
                                                    key={waiter.id}
                                                    value={String(waiter.id)}
                                                >
                                                    {waiter.name}
                                                </option>
                                            ))}
                                        </select>
                                    </>
                                ) : (
                                    <span className="text-text-secondary">
                                        {shortName(waiterName(order))}
                                    </span>
                                )}
                            </span>

                            <span
                                className={`inline-flex h-6 w-fit items-center justify-center rounded-[var(--radius-pill)] px-2.5 text-[length:var(--text-micro)] font-semibold capitalize ${
                                    STATUS_PILL[order.status] ??
                                    STATUS_PILL.placed
                                }`}
                            >
                                {order.status.replaceAll('_', ' ')}
                            </span>

                            <span
                                className="text-end font-mono text-[length:var(--text-body)]"
                                dir="ltr"
                                data-numeric
                            >
                                {money(
                                    order.total_minor,
                                    order.currency || currency,
                                )}
                            </span>

                            <span
                                className={`hidden text-end font-mono text-[length:var(--text-body)] min-[720px]:block ${ageColor(
                                    order.age_minutes,
                                    warnMinutes,
                                    lateMinutes,
                                )}`}
                                dir="ltr"
                                data-numeric
                            >
                                {ageLabel(order.age_minutes)}
                            </span>
                        </div>
                    ))}

                    {orders.length === 0 && (
                        <p className="text-text-tertiary px-5 py-12 text-center text-[length:var(--text-body)]">
                            No active orders right now.
                        </p>
                    )}
                </section>

                <section className="border-border-subtle bg-surface-card min-w-0 overflow-hidden rounded-[var(--radius-lg)] border shadow-[var(--shadow-card)]">
                    <CardHeading
                        aside={
                            <>
                                <span className="flex-1" />

                                <button
                                    type="button"
                                    onClick={() => openDrawer(null)}
                                    className="bg-action-primary hover:bg-action-primary-hover inline-flex h-[34px] items-center gap-1.5 rounded-[var(--radius-pill)] px-[14px] text-[length:var(--text-label)] font-semibold text-white"
                                >
                                    <Plus
                                        size={15}
                                        strokeWidth={2.5}
                                        aria-hidden="true"
                                    />
                                    Add waiter
                                </button>
                            </>
                        }
                    >
                        Floor team
                    </CardHeading>

                    {staff.map((member, index) => {
                        const memberTables = tableNumbers(member);
                        const end = shiftEnd(member);

                        const meta = [
                            member.job_title || member.role || 'Staff',
                            memberTables.length
                                ? `${memberTables.length === 1 ? 'table' : 'tables'} ${memberTables.join(', ')}`
                                : 'no tables',
                            plural(member.order_count, 'order', 'orders'),
                        ].join(' · ');

                        return (
                            <div
                                key={member.id}
                                className="border-border-subtle flex items-center gap-3 border-b px-5 py-3"
                            >
                                <span
                                    className={`font-display grid size-9 shrink-0 place-items-center rounded-full text-[length:var(--text-caption)] font-semibold ${
                                        AVATAR_TONES[
                                            index % AVATAR_TONES.length
                                        ]
                                    }`}
                                >
                                    {initialsOf(member)}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <div className="text-[length:var(--text-body)] font-medium">
                                        {member.name}
                                    </div>

                                    <div className="text-text-tertiary font-mono text-[length:var(--text-micro)]">
                                        {meta}
                                    </div>
                                </div>

                                <span
                                    className={`inline-flex h-6 items-center whitespace-nowrap rounded-[var(--radius-pill)] px-2.5 text-[length:var(--text-micro)] font-semibold ${
                                        member.on_shift
                                            ? 'bg-success-bg text-success-fg'
                                            : 'bg-ink-100 text-text-tertiary'
                                    }`}
                                >
                                    {member.on_shift
                                        ? 'On shift'
                                        : end
                                          ? `Off at ${end}`
                                          : 'Off shift'}
                                </span>

                                <button
                                    type="button"
                                    aria-label={`Edit ${member.name}`}
                                    onClick={() => openDrawer(member)}
                                    className="hover:bg-action-ghost-hover grid size-11 place-items-center rounded-full"
                                >
                                    <Pencil
                                        size={16}
                                        className="text-text-tertiary"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        );
                    })}

                    {staff.length === 0 && (
                        <p className="text-text-tertiary border-border-subtle border-b px-5 py-12 text-center text-[length:var(--text-body)]">
                            No floor team members found.
                        </p>
                    )}

                    <p className="text-text-tertiary px-5 py-3 text-[length:var(--text-caption)]">
                        Deleting a waiter keeps their order history and releases
                        their tables to the pool.
                    </p>
                </section>
            </div>

            <WaiterDrawer
                open={drawerOpen}
                editing={editing}
                tableOptions={tables}
                onClose={() => setDrawerOpen(false)}
                onSubmit={saveWaiter}
            />

            {toast && (
                <div
                    role="status"
                    className="bg-ink-900 fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] end-5 z-[90] flex max-w-[340px] animate-[qr-rise_var(--dur-base)_var(--ease-standard)] items-center gap-3 rounded-[var(--radius-md)] px-4 py-3 shadow-[var(--shadow-pop)] motion-reduce:animate-none min-[900px]:bottom-5"
                >
                    <span
                        aria-hidden="true"
                        className={`size-2 shrink-0 rounded-full ${
                            toast.tone === 'error'
                                ? 'bg-berry-300'
                                : 'bg-clay-300'
                        }`}
                    />

                    <div className="min-w-0 flex-1">
                        <div className="text-ink-25 text-[length:var(--text-body)] font-medium">
                            {toast.title}
                        </div>

                        {toast.sub && (
                            <div className="text-ink-400 font-mono text-[length:var(--text-caption)]">
                                {toast.sub}
                            </div>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={() => setToast(null)}
                        className="text-clay-300 text-[length:var(--text-label)] font-semibold"
                    >
                        Dismiss
                    </button>
                </div>
            )}
        </div>
    );
}

// Opt in to the design-spec manager shell (sidebar, live pill, bell).
// Other staff pages keep the default StaffLayout.
ManagerOverview.layout = { variant: 'console' };
