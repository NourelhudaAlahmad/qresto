import { router } from '@inertiajs/react';
import { Ellipsis, Plus, TrendingDown, TrendingUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Avatar } from '@/components/qresto/avatar';
import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { Drawer } from '@/components/qresto/drawer';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Stat } from '@/components/qresto/stat';
import { StatusPill } from '@/components/qresto/status-pill';
import { showToast } from '@/components/qresto/toast';
import { useLiveData } from '@/hooks/useLiveData';
import { formatMinutes } from '@/lib/elapsed';
import { activeLocale, formatMinor } from '@/lib/formatMoney';
import { fill, useI18n } from '@/lib/i18n';
import type { SharedTranslations } from '@/lib/i18n';
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
    email: string;
    role: string;
    role_label: string;
    job_title: string | null;
    initials: string | null;
    active: boolean;
    deactivated_at: string | null;
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
    staffRoles?: string[];
    now: number;
    currency: string;
    warnMinutes: number;
    lateMinutes: number;
    pollIntervalMs: number;
};

const LIVE_PROPS = ['stats', 'orders', 'staff', 'now'];

const DEFAULT_ROLES = ['waiter', 'runner', 'cashier', 'kitchen'];

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

const ageLabel = formatMinutes;

type RoleCopy = SharedTranslations['staff']['roles'];

/** Pick the `_one` or `_other` form and fill in the count. */
function plural(
    count: number,
    one: string,
    many: string,
    replacements: Record<string, string | number> = {},
): string {
    return fill(count === 1 ? one : many, { ...replacements, count });
}

function percentChange(current: number, previous: number): number | null {
    return previous > 0
        ? Math.round(((current - previous) / previous) * 100)
        : null;
}

function tableName(table: Order['table'], template: string): string {
    if (table === null || table === '') {
        return '—';
    }

    const raw = String(table);

    return /^\d+$/.test(raw)
        ? fill(template, { table: raw.padStart(2, '0') })
        : raw;
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
    role: string;
    tables: string[];
    send_invite: boolean;
};

type FormErrors = Partial<Record<keyof WaiterForm | 'member', string>>;

const FORM_ID = 'staff-member-form';

/** The role in the page's language; an unknown role is shown capitalised. */
function roleLabel(role: string, roles: RoleCopy): string {
    return (
        roles[role as keyof RoleCopy] ??
        role.charAt(0).toUpperCase() + role.slice(1)
    );
}

function WaiterFields({
    editing,
    roles,
    tableOptions,
    errors,
    onSubmit,
}: {
    editing: TeamMember | null;
    roles: string[];
    tableOptions: string[];
    errors: FormErrors;
    onSubmit: (form: WaiterForm) => void;
}) {
    const {
        staff: { overview: t, roles: roleCopy },
    } = useI18n();

    // Mounted fresh on every open (Radix unmounts closed content).
    const [form, setForm] = useState<WaiterForm>(() => ({
        name: editing?.name ?? '',
        email: editing?.email ?? '',
        role: editing?.role ?? roles[0] ?? 'waiter',
        tables: editing?.tables ?? [],
        send_invite: editing === null,
    }));
    const [localErrors, setLocalErrors] = useState<FormErrors>({});

    // Server errors win over the quick client-side checks.
    const shown = { ...localErrors, ...errors };

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

        const next: FormErrors = {};

        if (form.name.trim() === '') {
            next.name = t.name_required;
        }

        if (!editing && !/^\S+@\S+\.\S+$/.test(form.email.trim())) {
            next.email = t.email_invalid;
        }

        setLocalErrors(next);

        if (Object.keys(next).length === 0) {
            onSubmit({
                ...form,
                name: form.name.trim(),
                email: form.email.trim(),
            });
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
                <Label htmlFor="staff-name">{t.full_name}</Label>
                <Input
                    id="staff-name"
                    value={form.name}
                    placeholder={t.name_placeholder}
                    autoComplete="off"
                    aria-invalid={shown.name ? true : undefined}
                    onChange={(event) =>
                        setForm({ ...form, name: event.target.value })
                    }
                />
                {shown.name && (
                    <span className="text-caption text-[var(--berry-500)]">
                        {shown.name}
                    </span>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="staff-email">{t.work_email}</Label>
                <Input
                    id="staff-email"
                    type="email"
                    value={form.email}
                    placeholder="name@albustan.co"
                    autoComplete="off"
                    dir="ltr"
                    readOnly={editing !== null}
                    aria-invalid={shown.email ? true : undefined}
                    onChange={(event) =>
                        setForm({ ...form, email: event.target.value })
                    }
                />
                {shown.email && (
                    <span className="text-caption text-[var(--berry-500)]">
                        {shown.email}
                    </span>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="text-label font-medium text-[var(--text-primary)]">
                    {t.role}
                </span>
                <div
                    role="group"
                    aria-label={t.role}
                    className="flex flex-wrap gap-2"
                >
                    {roles.map((role) => (
                        <ChipFilter
                            key={role}
                            type="segment"
                            active={form.role === role}
                            onClick={() => setForm({ ...form, role })}
                        >
                            {roleLabel(role, roleCopy)}
                        </ChipFilter>
                    ))}
                </div>
                {shown.role && (
                    <span className="text-caption text-[var(--berry-500)]">
                        {shown.role}
                    </span>
                )}
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="text-label font-medium text-[var(--text-primary)]">
                    {t.tables_tonight}
                </span>

                {tableOptions.length === 0 ? (
                    <p className="text-caption text-[var(--text-tertiary)]">
                        {t.no_tables_set_up}
                    </p>
                ) : (
                    <div
                        role="group"
                        aria-label={t.tables_tonight}
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

                <span className="text-caption text-[var(--text-tertiary)]">
                    {t.table_moves}
                </span>

                {shown.tables && (
                    <span className="text-caption text-[var(--berry-500)]">
                        {shown.tables}
                    </span>
                )}
            </div>

            {!editing && (
                <div className="-ms-3 flex items-center gap-1">
                    <Checkbox
                        id="staff-send-invite"
                        checked={form.send_invite}
                        onCheckedChange={(checked) =>
                            setForm({ ...form, send_invite: checked === true })
                        }
                    />
                    <Label htmlFor="staff-send-invite" className="font-normal">
                        {t.send_invite}
                    </Label>
                </div>
            )}
        </form>
    );
}

function MemberMenu({
    member,
    onEdit,
    onInvite,
    onDeactivate,
    onReactivate,
}: {
    member: TeamMember;
    onEdit: () => void;
    onInvite: () => void;
    onDeactivate: () => void;
    onReactivate: () => void;
}) {
    const {
        staff: { overview: t },
    } = useI18n();
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !ref.current?.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    const item = (label: string, action: () => void, danger = false) => (
        <button
            type="button"
            role="menuitem"
            onClick={() => {
                setOpen(false);
                action();
            }}
            className={`text-label min-h-11 w-full rounded-[var(--radius-sm)] px-3 text-start font-semibold hover:bg-[var(--action-ghost-hover)] ${
                danger
                    ? 'text-[var(--berry-500)]'
                    : 'text-[var(--text-primary)]'
            }`}
        >
            {label}
        </button>
    );

    return (
        <div ref={ref} className="relative shrink-0">
            <button
                type="button"
                aria-label={fill(t.actions_for, { name: member.name })}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                className="grid size-11 place-items-center rounded-full text-[var(--text-tertiary)] hover:bg-[var(--action-ghost-hover)]"
            >
                <Ellipsis className="size-5" aria-hidden="true" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute end-0 top-12 z-20 min-w-52 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-1 shadow-[var(--shadow-pop)]"
                >
                    {member.active ? (
                        <>
                            {item(t.edit_reassign, onEdit)}
                            {item(t.send_link, onInvite)}
                            {item(t.deactivate, onDeactivate, true)}
                        </>
                    ) : (
                        item(t.reactivate, onReactivate)
                    )}
                </div>
            )}
        </div>
    );
}

export default function ManagerOverview({
    stats,
    orders,
    staff,
    waiters,
    tables = [],
    staffRoles = DEFAULT_ROLES,
    now,
    currency,
    warnMinutes,
    lateMinutes,
    pollIntervalMs,
}: Props) {
    const {
        staff: { overview: t, roles },
    } = useI18n();
    const [busyOrderId, setBusyOrderId] = useState<number | null>(null);
    const [reassigning, setReassigning] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState<TeamMember | null>(null);
    const [formErrors, setFormErrors] = useState<FormErrors>({});
    const [saving, setSaving] = useState(false);
    const [deactivating, setDeactivating] = useState<TeamMember | null>(null);
    const [deactivateError, setDeactivateError] = useState<string | null>(null);
    const [busyMemberId, setBusyMemberId] = useState<number | null>(null);

    useLiveData(pollIntervalMs, { only: LIVE_PROPS });

    const weekday = new Intl.DateTimeFormat(activeLocale(), {
        weekday: 'long',
    }).format(new Date(now));

    const coversChange = percentChange(stats.covers, stats.covers_last_week);
    const salesChange = percentChange(
        stats.net_sales_minor,
        stats.net_sales_last_week_minor,
    );
    const openBills = plural(
        stats.open_bills,
        t.open_bills_one,
        t.open_bills_other,
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
                        title: t.assignment_updated,
                        description: order.code,
                    }),
                onError: (errors) =>
                    showToast({
                        type: 'error',
                        title: t.reassign_failed,
                        description:
                            errors.assigned_user_id ??
                            errors.order ??
                            t.try_again,
                    }),
                onFinish: () => setBusyOrderId(null),
            },
        );
    }

    function openDrawer(member: TeamMember | null) {
        setEditing(member);
        setFormErrors({});
        setDrawerOpen(true);
    }

    // Every staff action redirects back; reload only what changed so the
    // team list updates in place and the drawer never navigates away.
    const staffVisit = {
        preserveScroll: true,
        preserveState: true,
        only: [...LIVE_PROPS, 'waiters', 'flash'],
    };

    function saveMember(form: WaiterForm) {
        if (saving) {
            return;
        }

        const payload = {
            name: form.name,
            role: form.role,
            tables: form.tables,
        };

        const options = {
            ...staffVisit,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onError: (errors: Record<string, string>) =>
                setFormErrors(errors as FormErrors),
            onSuccess: () => {
                setDrawerOpen(false);
                setFormErrors({});
                showToast(
                    editing
                        ? {
                              type: 'success',
                              title: fill(t.updated, { name: form.name }),
                          }
                        : {
                              type: 'success',
                              title: fill(t.added, { name: form.name }),
                              description: form.send_invite
                                  ? t.link_sent
                                  : undefined,
                          },
                );
            },
        };

        if (editing) {
            router.patch(`/app/staff/${editing.id}`, payload, options);
        } else {
            router.post(
                '/app/staff',
                {
                    ...payload,
                    email: form.email,
                    send_invite: form.send_invite,
                },
                options,
            );
        }
    }

    function memberAction(
        member: TeamMember,
        action: 'deactivate' | 'reactivate' | 'invite',
        success: string,
        onError?: (message: string) => void,
    ) {
        if (busyMemberId !== null) {
            return;
        }

        router.post(
            `/app/staff/${member.id}/${action}`,
            {},
            {
                ...staffVisit,
                onStart: () => setBusyMemberId(member.id),
                onFinish: () => setBusyMemberId(null),
                onSuccess: () => {
                    if (action === 'deactivate') {
                        setDeactivating(null);
                    }

                    showToast({ type: 'success', title: success });
                },
                onError: (errors) => {
                    const message =
                        errors.member ??
                        Object.values(errors)[0] ??
                        t.try_again;

                    if (onError) {
                        onError(message);
                    } else {
                        showToast({
                            type: 'error',
                            title: t.action_failed,
                            description: message,
                        });
                    }
                },
            },
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <section
                aria-label={t.service}
                className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 min-[1100px]:grid-cols-4"
            >
                <Stat
                    label={t.covers}
                    value={stats.covers.toLocaleString(activeLocale())}
                    hintTone={
                        coversChange === null
                            ? 'default'
                            : coversChange >= 0
                              ? 'positive'
                              : 'negative'
                    }
                    hint={
                        coversChange === null ? (
                            t.covers_hint
                        ) : (
                            <Trend
                                change={coversChange}
                                label={fill(t.vs_last, { weekday })}
                            />
                        )
                    }
                />

                <Stat
                    label={t.net_sales}
                    value={formatMinor(stats.net_sales_minor, currency, {
                        digits: 0,
                    })}
                    hintTone={
                        salesChange !== null && salesChange < 0
                            ? 'negative'
                            : 'default'
                    }
                    hint={fill(t.avg_per_cover, {
                        amount: formatMinor(
                            stats.avg_per_cover_minor,
                            currency,
                        ),
                    })}
                />

                <Stat
                    label={t.kitchen_median}
                    value={
                        stats.median_prep_minutes === null
                            ? '—'
                            : `${Math.round(stats.median_prep_minutes)}m`
                    }
                    hintTone={
                        stats.late_orders_count > 0 ? 'warning' : 'default'
                    }
                    hint={plural(
                        stats.late_orders_count,
                        t.late_one,
                        t.late_other,
                        { minutes: lateMinutes },
                    )}
                />

                <Stat
                    label={t.unpaid}
                    value={formatMinor(stats.unpaid_total_minor, currency, {
                        digits: 0,
                    })}
                    hint={
                        stats.oldest_bill_minutes === null
                            ? openBills
                            : fill(t.oldest_bill, {
                                  bills: openBills,
                                  age: ageLabel(stats.oldest_bill_minutes),
                              })
                    }
                />
            </section>

            <div className="grid grid-cols-1 items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <Panel
                    title={t.active_orders}
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
                                {reassigning ? t.done : t.reassign}
                            </Button>
                        </>
                    }
                >
                    <div
                        className={`${ORDER_GRID} text-label border-b border-[var(--border-subtle)] bg-[var(--surface-sunken)] py-2 font-semibold text-[var(--text-secondary)]`}
                    >
                        <span>{t.col_order}</span>
                        <span>{t.col_table_waiter}</span>
                        <span>{t.col_status}</span>
                        <span className="text-end">{t.col_total}</span>
                        <span className="hidden text-end min-[720px]:block">
                            {t.col_age}
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
                                {tableName(order.table, t.table)} ·{' '}
                                {reassigning ? (
                                    <>
                                        <label
                                            className="sr-only"
                                            htmlFor={`waiter-${order.id}`}
                                        >
                                            {fill(t.assign_waiter, {
                                                code: order.code,
                                            })}
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
                                            className="text-caption h-11 max-w-full rounded-[var(--radius-pill)] border border-[var(--border-default)] bg-[var(--surface-card)] px-3 outline-none focus-visible:border-[var(--border-focus-color)] disabled:opacity-50"
                                        >
                                            {order.assigned_waiter === null && (
                                                <option value="">
                                                    {t.unassigned}
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
                                            : t.unassigned}
                                    </span>
                                )}
                            </span>

                            <span>
                                <StatusPill status={order.status} />
                            </span>

                            <Numeric className="text-body text-end font-mono">
                                {formatMinor(
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
                        <EmptyState headline={t.no_active_orders} />
                    )}
                </Panel>

                <Panel
                    title={t.floor_team}
                    aside={
                        <Button
                            type="button"
                            variant="primary"
                            size="sm"
                            onClick={() => openDrawer(null)}
                        >
                            <Plus size={15} strokeWidth={2.5} aria-hidden />
                            {t.add_waiter}
                        </Button>
                    }
                >
                    {staff.map((member, index) => {
                        const meta = [
                            roleLabel(member.role, roles),
                            member.tables.length
                                ? fill(
                                      member.tables.length === 1
                                          ? t.tables_one
                                          : t.tables_other,
                                      { tables: member.tables.join(', ') },
                                  )
                                : t.no_tables,
                            plural(
                                member.order_count,
                                t.orders_one,
                                t.orders_other,
                            ),
                        ].join(' · ');

                        const chip = !member.active
                            ? {
                                  label: t.deactivated,
                                  className:
                                      'bg-[var(--danger-bg)] text-[var(--danger-fg)]',
                              }
                            : member.on_shift
                              ? {
                                    label: t.on_shift,
                                    className:
                                        'bg-[var(--success-bg)] text-[var(--success-fg)]',
                                }
                              : {
                                    label: member.shift
                                        ? fill(t.off_at, {
                                              time: member.shift.ends_at,
                                          })
                                        : t.off_shift,
                                    className:
                                        'bg-[var(--ink-100)] text-[var(--text-tertiary)]',
                                };

                        return (
                            <div
                                key={member.id}
                                aria-busy={busyMemberId === member.id}
                                className={`flex items-center gap-3 border-b border-[var(--border-subtle)] px-5 py-3 ${
                                    member.active ? '' : 'opacity-60'
                                }`}
                            >
                                <Avatar
                                    initials={initialsOf(member)}
                                    tone={
                                        member.active
                                            ? AVATAR_TONES[
                                                  index % AVATAR_TONES.length
                                              ]
                                            : 'ink'
                                    }
                                />

                                <button
                                    type="button"
                                    disabled={!member.active}
                                    onClick={() => openDrawer(member)}
                                    className="min-w-0 flex-1 text-start disabled:cursor-default"
                                >
                                    <span className="text-body block font-medium">
                                        {member.name}
                                    </span>
                                    <span className="text-micro block truncate font-mono text-[var(--text-tertiary)]">
                                        {meta}
                                    </span>
                                </button>

                                <span
                                    className={`text-micro inline-flex h-6 items-center whitespace-nowrap rounded-[var(--radius-pill)] px-2.5 font-semibold ${chip.className}`}
                                >
                                    {chip.label}
                                </span>

                                <MemberMenu
                                    member={member}
                                    onEdit={() => openDrawer(member)}
                                    onInvite={() =>
                                        memberAction(
                                            member,
                                            'invite',
                                            fill(t.link_sent_to, {
                                                name: member.name,
                                            }),
                                        )
                                    }
                                    onDeactivate={() => {
                                        setDeactivateError(null);
                                        setDeactivating(member);
                                    }}
                                    onReactivate={() =>
                                        memberAction(
                                            member,
                                            'reactivate',
                                            fill(t.reactivated, {
                                                name: member.name,
                                            }),
                                        )
                                    }
                                />
                            </div>
                        );
                    })}

                    {staff.length === 0 && <EmptyState headline={t.no_team} />}

                    <p className="text-caption px-5 py-3 text-[var(--text-tertiary)]">
                        {t.delete_note}
                    </p>
                </Panel>
            </div>

            <Drawer
                open={drawerOpen}
                onOpenChange={(open) => {
                    if (!saving) {
                        setDrawerOpen(open);
                    }
                }}
                title={
                    editing
                        ? fill(t.edit_title, { name: editing.name })
                        : t.add_title
                }
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            disabled={saving}
                            onClick={() => setDrawerOpen(false)}
                        >
                            {t.cancel}
                        </Button>
                        <Button
                            type="submit"
                            form={FORM_ID}
                            variant="primary"
                            className="flex-1"
                            loading={saving}
                        >
                            {editing ? t.save : t.add_to_shift}
                        </Button>
                    </>
                }
            >
                <WaiterFields
                    editing={editing}
                    roles={staffRoles}
                    tableOptions={tables}
                    errors={formErrors}
                    onSubmit={saveMember}
                />
            </Drawer>

            {deactivating && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open && busyMemberId === null) {
                            setDeactivating(null);
                        }
                    }}
                    title={fill(t.deactivate_title, {
                        name: deactivating.name,
                    })}
                    description={t.deactivate_body}
                >
                    <div className="flex flex-col gap-3">
                        {deactivateError && (
                            <p
                                role="alert"
                                className="text-body rounded-[var(--radius-md)] bg-[var(--danger-bg)] px-3 py-2 text-[var(--danger-fg)]"
                            >
                                {deactivateError}
                            </p>
                        )}

                        <div className="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                disabled={busyMemberId !== null}
                                onClick={() => setDeactivating(null)}
                            >
                                {t.keep_active}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                className="flex-1"
                                loading={busyMemberId === deactivating.id}
                                onClick={() =>
                                    memberAction(
                                        deactivating,
                                        'deactivate',
                                        fill(t.deactivated_toast, {
                                            name: deactivating.name,
                                        }),
                                        setDeactivateError,
                                    )
                                }
                            >
                                {t.deactivate}
                            </Button>
                        </div>
                    </div>
                </ResponsiveOverlay>
            )}
        </div>
    );
}

// Opt in to the design-spec manager shell subtitle (service date).
ManagerOverview.layout = { variant: 'console' };
