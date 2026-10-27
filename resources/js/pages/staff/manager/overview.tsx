import { router } from '@inertiajs/react';
import { Pencil, Plus, TrendingDown, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Avatar } from '@/components/qresto/avatar';
import { Button } from '@/components/qresto/button';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { Drawer } from '@/components/qresto/drawer';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { Stat } from '@/components/qresto/stat';
import { StatusPill } from '@/components/qresto/status-pill';
import { Switch } from '@/components/qresto/switch';
import { showToast } from '@/components/qresto/toast';
import { useLiveData } from '@/hooks/useLiveData';
import type { OrderStatus } from '@/types';

type Stats = {
    covers: number;
    covers_last_week: number;
    net_sales_minor: number;
    net_sales_last_week_minor: number;
    avg_per_cover_minor: number;
    avg_per_cover_last_week_minor: number;
    median_prep_minutes: number | null;
    late_orders_count: number;
    unpaid_total_minor: number;
    open_bills: number;
    oldest_bill_minutes: number | null;
};

type Waiter = {
    id: number;
    name: string;
};

type Order = {
    id: number;
    code: string;
    status: OrderStatus;
    table: string | number | null;
    guest_name: string | null;
    placed_at: string | null;
    age_minutes: number;
    total_minor: number;
    currency: string;
    paid: boolean;
    assigned_waiter: { id: number; name: string } | null;
};

type TeamMember = {
    id: number;
    name: string;
    role: string | null;
    job_title: string | null;
    initials: string | null;
    tables: string[];
    shift: { starts_at: string; ends_at: string } | null;
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

const LIVE_PROPS = ['stats', 'orders', 'staff', 'now'];

const ROLE_OPTIONS = ['Waiter', 'Runner', 'Cashier', 'Kitchen'] as const;

const AVATAR_TONES = ['teal', 'clay', 'ink'] as const;

// Order age is the only coloured column (design: SPEC.manager).
const AGE_COLORS = {
    normal: 'text-[var(--text-tertiary)]',
    warn: 'text-[var(--saffron-700)] font-semibold',
    late: 'text-[var(--berry-500)] font-semibold',
} as const;

// Status and total survive on a phone; the age column drops first.
const ORDER_GRID =
    'grid grid-cols-[84px_minmax(0,1fr)_auto_80px] items-center gap-3 px-5 min-[720px]:grid-cols-[90px_minmax(0,1fr)_112px_92px_74px]';

function money(minor: number | null, currency: string, digits = 2): string {
    if (minor === null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'USD',
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(digits)} ${currency}`;
    }
}

function ageLabel(minutes: number | null): string {
    const age = Math.max(0, minutes ?? 0);

    return age < 60 ? `${age} min` : `${Math.floor(age / 60)}h ${age % 60}m`;
}

function plural(count: number, one: string, many: string): string {
    return `${count} ${count === 1 ? one : many}`;
}

function percentChange(current: number, previous: number): number | null {
    return previous > 0
        ? Math.round(((current - previous) / previous) * 100)
        : null;
}

function tableName(table: Order['table']): string {
    if (table === null || table === '') {
        return '—';
    }

    const raw = String(table);

    return /^\d+$/.test(raw) ? `Table ${raw.padStart(2, '0')}` : raw;
}

// "Nadia Rahman" → "Nadia R."
function shortName(name: string): string {
    const parts = name.trim().split(/\s+/);

    return parts.length < 2
        ? name
        : `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
}

function initialsOf(member: TeamMember): string {
    return (
        member.initials ||
        member.name
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase()
    );
}

function Trend({ change, label }: { change: number; label: string }) {
    return (
        <>
            {change >= 0 ? (
                <TrendingUp size={13} aria-hidden="true" />
            ) : (
                <TrendingDown size={13} aria-hidden="true" />
            )}
            <Numeric>
                {change >= 0 ? '+' : ''}
                {change}%
            </Numeric>{' '}
            {label}
        </>
    );
}

function Panel({
    title,
    aside,
    children,
}: {
    title: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="min-w-0 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-card)]">
            <div className="flex flex-wrap items-center gap-3 border-b border-[var(--border-subtle)] px-5 py-4">
                <h2 className="font-display text-title-2 flex-1 font-semibold tracking-[var(--tracking-title)]">
                    {title}
                </h2>
                {aside}
            </div>
            {children}
        </section>
    );
}

type WaiterForm = {
    name: string;
    email: string;
    role: (typeof ROLE_OPTIONS)[number];
    tables: string[];
    sendLink: boolean;
};

const EMPTY_FORM: WaiterForm = {
    name: '',
    email: '',
    role: 'Waiter',
    tables: [],
    sendLink: true,
};

const FORM_ID = 'add-waiter-form';

function WaiterFields({
    editing,
    tableOptions,
    onSubmit,
}: {
    editing: TeamMember | null;
    tableOptions: string[];
    onSubmit: (form: WaiterForm) => void;
}) {
    // Mounted fresh on every open (Radix unmounts closed content).
    const [form, setForm] = useState<WaiterForm>(() =>
        editing
            ? {
                  ...EMPTY_FORM,
                  name: editing.name,
                  tables: editing.tables,
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
            next.name = 'Enter the full name.';
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
            id={FORM_ID}
            onSubmit={submit}
            noValidate
            className="flex flex-col gap-5"
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="waiter-name">Full name</Label>
                <Input
                    id="waiter-name"
                    value={form.name}
                    placeholder="Omar Faruk"
                    autoComplete="off"
                    aria-invalid={errors.name ? true : undefined}
                    onChange={(event) =>
                        setForm({ ...form, name: event.target.value })
                    }
                />
                {errors.name && (
                    <span className="text-caption text-[var(--berry-500)]">
                        {errors.name}
                    </span>
                )}
            </div>

            {!editing && (
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="waiter-email">Work email</Label>
                    <Input
                        id="waiter-email"
                        type="email"
                        value={form.email}
                        placeholder="name@albustan.co"
                        autoComplete="off"
                        dir="ltr"
                        aria-invalid={errors.email ? true : undefined}
                        onChange={(event) =>
                            setForm({ ...form, email: event.target.value })
                        }
                    />
                    {errors.email && (
                        <span className="text-caption text-[var(--berry-500)]">
                            {errors.email}
                        </span>
                    )}
                </div>
            )}

            <div className="flex flex-col gap-1.5">
                <span className="text-label font-medium text-[var(--text-primary)]">
                    Role
                </span>
                <div
                    role="group"
                    aria-label="Role"
                    className="flex flex-wrap gap-2"
                >
                    {ROLE_OPTIONS.map((role) => (
                        <ChipFilter
                            key={role}
                            type="segment"
                            active={form.role === role}
                            onClick={() => setForm({ ...form, role })}
                        >
                            {role}
                        </ChipFilter>
                    ))}
                </div>
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="text-label font-medium text-[var(--text-primary)]">
                    Tables tonight
                </span>

                {tableOptions.length === 0 ? (
                    <p className="text-caption text-[var(--text-tertiary)]">
                        No tables set up yet.
                    </p>
                ) : (
                    <div
                        role="group"
                        aria-label="Tables tonight"
                        className="grid grid-cols-4 gap-2"
                    >
                        {tableOptions.map((table) => (
                            <ChipFilter
                                key={table}
                                type="category"
                                active={form.tables.includes(table)}
                                onClick={() => toggleTable(table)}
                            >
                                <Numeric>{table}</Numeric>
                            </ChipFilter>
                        ))}
                    </div>
                )}
            </div>

            {!editing && (
                <div className="flex items-center justify-between gap-3">
                    <Label htmlFor="waiter-send-link" className="font-normal">
                        Send a set-password link now
                    </Label>
                    <Switch
                        id="waiter-send-link"
                        checked={form.sendLink}
                        onCheckedChange={(checked) =>
                            setForm({ ...form, sendLink: checked })
                        }
                    />
                </div>
            )}
        </form>
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
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState<TeamMember | null>(null);

    useLiveData(pollIntervalMs, { only: LIVE_PROPS });

    const weekday = new Intl.DateTimeFormat('en', { weekday: 'long' }).format(
        new Date(now),
    );

    const coversChange = percentChange(stats.covers, stats.covers_last_week);
    const salesChange = percentChange(
        stats.net_sales_minor,
        stats.net_sales_last_week_minor,
    );

    function ageLevel(minutes: number): keyof typeof AGE_COLORS {
        if (minutes >= lateMinutes) {
            return 'late';
        }

        return minutes >= warnMinutes ? 'warn' : 'normal';
    }

    function assignOrder(order: Order, waiterId: string) {
        if (
            !waiterId ||
            busyOrderId !== null ||
            Number(waiterId) === order.assigned_waiter?.id
        ) {
            return;
        }

        router.patch(
            `/app/orders/${order.id}/assign`,
            { assigned_user_id: Number(waiterId) },
            {
                preserveScroll: true,
                only: LIVE_PROPS,
                onStart: () => setBusyOrderId(order.id),
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: 'Assignment updated',
                        description: order.code,
                    }),
                onError: (errors) =>
                    showToast({
                        type: 'error',
                        title: 'Could not reassign',
                        description:
                            errors.assigned_user_id ??
                            errors.order ??
                            'Please try again.',
                    }),
                onFinish: () => setBusyOrderId(null),
            },
        );
    }

    function openDrawer(member: TeamMember | null) {
        setEditing(member);
        setDrawerOpen(true);
    }

    // There is no staff endpoint yet (#27). Replace this with router.post
    // when StaffController lands; the drawer must never navigate away.
    function saveWaiter(form: WaiterForm) {
        setDrawerOpen(false);

        showToast(
            editing
                ? { type: 'success', title: 'Waiter updated' }
                : {
                      type: 'success',
                      title: `${form.name} added to tonight`,
                      description: form.sendLink
                          ? 'Set-password link sent'
                          : undefined,
                  },
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <section
                aria-label="Tonight's service"
                className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 min-[1100px]:grid-cols-4"
            >
                <Stat
                    label="Covers tonight"
                    value={stats.covers.toLocaleString('en')}
                    hintTone={
                        coversChange === null
                            ? 'default'
                            : coversChange >= 0
                              ? 'positive'
                              : 'negative'
                    }
                    hint={
                        coversChange === null ? (
                            'Guests seated tonight'
                        ) : (
                            <Trend
                                change={coversChange}
                                label={`vs last ${weekday}`}
                            />
                        )
                    }
                />

                <Stat
                    label="Net sales"
                    value={money(stats.net_sales_minor, currency, 0)}
                    hintTone={
                        salesChange !== null && salesChange < 0
                            ? 'negative'
                            : 'default'
                    }
                    hint={`${money(stats.avg_per_cover_minor, currency)} average per cover`}
                />

                <Stat
                    label="Kitchen time, median"
                    value={
                        stats.median_prep_minutes === null
                            ? '—'
                            : `${Math.round(stats.median_prep_minutes)}m`
                    }
                    hintTone={
                        stats.late_orders_count > 0 ? 'warning' : 'default'
                    }
                    hint={`${plural(stats.late_orders_count, 'order', 'orders')} over ${lateMinutes} min`}
                />

                <Stat
                    label="Unpaid at table"
                    value={money(stats.unpaid_total_minor, currency, 0)}
                    hint={`${plural(stats.open_bills, 'open bill', 'open bills')}${
                        stats.oldest_bill_minutes === null
                            ? ''
                            : `, oldest ${ageLabel(stats.oldest_bill_minutes)}`
                    }`}
                />
            </section>

            <div className="grid grid-cols-1 items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <Panel
                    title="All active orders"
                    aside={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                aria-pressed={reassigning}
                                disabled={waiters.length === 0}
                                onClick={() => setReassigning((on) => !on)}
                            >
                                {reassigning ? 'Done' : 'Reassign'}
                            </Button>
                        </>
                    }
                >
                    <div
                        className={`${ORDER_GRID} text-label border-b border-[var(--border-subtle)] bg-[var(--surface-sunken)] py-2 font-semibold text-[var(--text-secondary)]`}
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
                            className={`${ORDER_GRID} border-b border-[var(--border-subtle)] py-3 last:border-0`}
                        >
                            <Numeric className="text-body font-mono">
                                {order.code.startsWith('#')
                                    ? order.code
                                    : `#${order.code}`}
                            </Numeric>

                            <span className="text-body min-w-0">
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
                                            value={String(
                                                order.assigned_waiter?.id ?? '',
                                            )}
                                            disabled={busyOrderId !== null}
                                            onChange={(event) =>
                                                assignOrder(
                                                    order,
                                                    event.target.value,
                                                )
                                            }
                                            className="text-caption h-8 max-w-full rounded-[var(--radius-pill)] border border-[var(--border-default)] bg-[var(--surface-card)] px-2 outline-none focus-visible:border-[var(--border-focus-color)] disabled:opacity-50"
                                        >
                                            {order.assigned_waiter === null && (
                                                <option value="">
                                                    Unassigned
                                                </option>
                                            )}
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
                                    <span className="text-[var(--text-secondary)]">
                                        {order.assigned_waiter
                                            ? shortName(
                                                  order.assigned_waiter.name,
                                              )
                                            : 'Unassigned'}
                                    </span>
                                )}
                            </span>

                            <span>
                                <StatusPill status={order.status} />
                            </span>

                            <Numeric className="text-body text-end font-mono">
                                {money(
                                    order.total_minor,
                                    order.currency || currency,
                                )}
                            </Numeric>

                            <Numeric
                                className={`text-body hidden text-end font-mono min-[720px]:block ${
                                    AGE_COLORS[ageLevel(order.age_minutes)]
                                }`}
                            >
                                {ageLabel(order.age_minutes)}
                            </Numeric>
                        </div>
                    ))}

                    {orders.length === 0 && (
                        <EmptyState headline="No active orders right now" />
                    )}
                </Panel>

                <Panel
                    title="Floor team"
                    aside={
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            onClick={() => openDrawer(null)}
                        >
                            <Plus size={15} strokeWidth={2.5} aria-hidden />
                            Add waiter
                        </Button>
                    }
                >
                    {staff.map((member, index) => {
                        const meta = [
                            member.job_title || member.role || 'Staff',
                            member.tables.length
                                ? `${member.tables.length === 1 ? 'table' : 'tables'} ${member.tables.join(', ')}`
                                : 'no tables',
                            plural(member.order_count, 'order', 'orders'),
                        ].join(' · ');

                        return (
                            <div
                                key={member.id}
                                className="flex items-center gap-3 border-b border-[var(--border-subtle)] px-5 py-3"
                            >
                                <Avatar
                                    initials={initialsOf(member)}
                                    tone={
                                        AVATAR_TONES[
                                            index % AVATAR_TONES.length
                                        ]
                                    }
                                />

                                <div className="min-w-0 flex-1">
                                    <div className="text-body font-medium">
                                        {member.name}
                                    </div>
                                    <div className="text-micro truncate font-mono text-[var(--text-tertiary)]">
                                        {meta}
                                    </div>
                                </div>

                                <span
                                    className={`text-micro inline-flex h-6 items-center whitespace-nowrap rounded-[var(--radius-pill)] px-2.5 font-semibold ${
                                        member.on_shift
                                            ? 'bg-[var(--success-bg)] text-[var(--success-fg)]'
                                            : 'bg-[var(--ink-100)] text-[var(--text-tertiary)]'
                                    }`}
                                >
                                    {member.on_shift
                                        ? 'On shift'
                                        : member.shift
                                          ? `Off at ${member.shift.ends_at}`
                                          : 'Off shift'}
                                </span>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-label={`Edit ${member.name}`}
                                    onClick={() => openDrawer(member)}
                                >
                                    <Pencil
                                        size={16}
                                        className="text-[var(--text-tertiary)]"
                                        aria-hidden
                                    />
                                </Button>
                            </div>
                        );
                    })}

                    {staff.length === 0 && (
                        <EmptyState headline="No floor team yet" />
                    )}

                    <p className="text-caption px-5 py-3 text-[var(--text-tertiary)]">
                        Deleting a waiter keeps their order history and releases
                        their tables to the pool.
                    </p>
                </Panel>
            </div>

            <Drawer
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                title={editing ? `Edit ${editing.name}` : 'Add a waiter'}
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={() => setDrawerOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form={FORM_ID}
                            variant="primary"
                            className="flex-1"
                        >
                            {editing ? 'Save changes' : 'Add to shift'}
                        </Button>
                    </>
                }
            >
                <WaiterFields
                    editing={editing}
                    tableOptions={tables}
                    onSubmit={saveWaiter}
                />
            </Drawer>
        </div>
    );
}

// Opt in to the design-spec manager shell subtitle (service date).
ManagerOverview.layout = { variant: 'console' };
