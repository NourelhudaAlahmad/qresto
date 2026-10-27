import { router, usePage } from '@inertiajs/react';
import { BellOff, BellRing, Ellipsis, LayoutGrid, List } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/qresto/button';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { EmptyState } from '@/components/qresto/empty-state';
import { Label } from '@/components/qresto/label';
import { OrderTicket } from '@/components/qresto/order-ticket';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Textarea } from '@/components/qresto/textarea';
import { showToast } from '@/components/qresto/toast';
import { useElapsed } from '@/hooks/useElapsed';
import { useLiveAlertMute } from '@/hooks/useLiveAlertMute';
import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';

import OrderList, {
    actionLabel,
    canVoidStatus,
    minutesOld,
    money,
    orderCode,
    statusGroup,
} from './list';
import type { OrderStatus, Ticket } from './list';

type Column = 'new' | 'preparing' | 'ready' | 'served';

type Props = {
    orders: Ticket[];
    canAdvance: boolean;
    canTakePayment: boolean;
    warnMinutes: number;
    lateMinutes: number;
};

type SharedProps = {
    auth: { user: { id: number | string } };
    qresto?: { live?: { waiter_interval?: number } };
};

// The design's COLUMNS: label, colour and the verb that moves a ticket on.
const COLUMNS: { id: Column; title: string; color: string }[] = [
    { id: 'new', title: 'New', color: 'var(--saffron-500)' },
    { id: 'preparing', title: 'Preparing', color: 'var(--teal-500)' },
    { id: 'ready', title: 'Ready', color: 'var(--clay-500)' },
    { id: 'served', title: 'Served', color: 'var(--herb-500)' },
];

const COLUMN_COLOR: Record<Column, string> = Object.fromEntries(
    COLUMNS.map((column) => [column.id, column.color]),
) as Record<Column, string>;

const LIVE_PROPS = ['orders'];

function nextStatus(status: OrderStatus): OrderStatus | null {
    return (
        (
            {
                placed: 'pending',
                pending: 'preparing',
                preparing: 'ready',
                ready: 'served',
                served: null,
            } as const
        )[status] ?? null
    );
}

function TicketMenu({
    order,
    canVoid,
    onVoid,
}: {
    order: Ticket;
    canVoid: boolean;
    onVoid: (order: Ticket) => void;
}) {
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

    return (
        <div ref={ref} className="relative shrink-0">
            <button
                type="button"
                aria-label={`More actions for ${orderCode(order.code)}`}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                className="grid size-11 place-items-center rounded-full border border-[var(--border-default)] bg-[var(--surface-card)] text-[var(--text-secondary)] hover:bg-[var(--action-ghost-hover)]"
            >
                <Ellipsis className="size-5" aria-hidden="true" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute bottom-12 end-0 z-20 min-w-44 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-1 shadow-[var(--shadow-pop)]"
                >
                    <button
                        type="button"
                        role="menuitem"
                        disabled={!canVoid}
                        onClick={() => {
                            setOpen(false);
                            onVoid(order);
                        }}
                        className="text-label min-h-11 w-full rounded-[var(--radius-sm)] px-3 text-start font-semibold text-[var(--berry-500)] hover:bg-[var(--action-ghost-hover)] disabled:opacity-40"
                    >
                        Void order
                    </button>
                </div>
            )}
        </div>
    );
}

/**
 * One kanban card. A component of its own (not defined inside the page) so
 * it keeps its state, and its open menu, across polls and clock ticks.
 */
function KanbanTicket({
    order,
    column,
    canAdvance,
    canTakePayment,
    busy,
    onAdvance,
    onVoid,
}: {
    order: Ticket;
    column: Column;
    canAdvance: boolean;
    canTakePayment: boolean;
    busy: boolean;
    onAdvance: (order: Ticket) => void;
    onVoid: (order: Ticket) => void;
}) {
    // Ages between polls without a round trip, with the shared SLA config.
    const { minutes, level } = useElapsed(order.placed_at);
    const verb = actionLabel(order.status);
    const advance = canAdvance && verb !== null;

    return (
        <OrderTicket
            code={orderCode(order.code)}
            elapsed={`${minutes} min`}
            elapsedLevel={level}
            accentColor={COLUMN_COLOR[column]}
            table={`Table ${order.table ?? '—'}`}
            guest={order.guest_name || 'Guest'}
            status={order.status}
            items={order.lines.map((line) => ({
                qty: `${line.qty}×`,
                name: line.name,
                note: line.note ?? undefined,
            }))}
            paid={order.paid}
            total={money(order.total_minor, order.currency)}
            advanceLabel={verb ?? undefined}
            advancing={busy}
            onAdvance={advance ? () => onAdvance(order) : undefined}
            footerNote={
                order.status === 'served'
                    ? canTakePayment
                        ? 'Take payment from the floor map'
                        : 'Waiting for payment'
                    : 'No action available'
            }
            overflow={
                <TicketMenu
                    order={order}
                    canVoid={canAdvance && canVoidStatus(order.status)}
                    onVoid={onVoid}
                />
            }
        />
    );
}

export default function Kanban({
    orders,
    canAdvance,
    canTakePayment,
    warnMinutes,
    lateMinutes,
}: Props) {
    const { auth, qresto } = usePage().props as unknown as SharedProps;
    const userId = auth.user.id;
    const viewKey = `qresto:waiter:${userId}:view`;

    // Optimistic copy of the live collection; every poll replaces it.
    const [tickets, setTickets] = useState(orders);
    const [now, setNow] = useState(() => Date.now());
    const [filter, setFilter] = useState<Column>('new');
    const [view, setView] = useState<'kanban' | 'list'>(() => {
        try {
            return localStorage.getItem(viewKey) === 'list' ? 'list' : 'kanban';
        } catch {
            return 'kanban';
        }
    });
    const [busy, setBusy] = useState<number | null>(null);
    const [voidOrder, setVoidOrder] = useState<Ticket | null>(null);
    const [reason, setReason] = useState('');
    const [error, setError] = useState('');

    const { muted, toggleMute } = useLiveAlertMute();

    useLiveData(qresto?.live?.waiter_interval ?? 5000, {
        only: LIVE_PROPS,
    });

    // New orders arrive as a toast plus a sound, never as a modal.
    useNewItemAlert(orders, (order) => order.id, {
        title: 'New order',
        description: 'A new order just reached your board.',
        muted,
    });

    // Every poll replaces the optimistic copy (React's "adjust state when a
    // prop changes" pattern, without an extra effect pass).
    const [syncedOrders, setSyncedOrders] = useState(orders);

    if (syncedOrders !== orders) {
        setSyncedOrders(orders);
        setTickets(orders);
    }

    // The summary line's "oldest" ages between polls too.
    useEffect(() => {
        const tick = window.setInterval(() => setNow(Date.now()), 30_000);

        return () => window.clearInterval(tick);
    }, []);

    const grouped = (column: Column) =>
        tickets.filter((order) => statusGroup(order.status) === column);

    const kitchenCount = tickets.filter(
        (order) => order.status === 'pending' || order.status === 'preparing',
    ).length;

    const oldest = tickets.length
        ? Math.max(...tickets.map((order) => minutesOld(order.placed_at, now)))
        : 0;

    function setViewMode(mode: 'kanban' | 'list'): void {
        setView(mode);

        try {
            localStorage.setItem(viewKey, mode);
        } catch {
            /* Optional preference. */
        }
    }

    function advance(order: Ticket): void {
        const next = nextStatus(order.status);

        if (!next || busy !== null || !canAdvance) {
            return;
        }

        const previous = tickets;

        setTickets((current) =>
            current.map((ticket) =>
                ticket.id === order.id ? { ...ticket, status: next } : ticket,
            ),
        );
        setBusy(order.id);

        router.patch(
            `/app/orders/${order.id}/advance`,
            {},
            {
                preserveScroll: true,
                only: LIVE_PROPS,
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: `${orderCode(order.code)} updated`,
                    }),
                onError: () => {
                    setTickets(previous);
                    showToast({
                        type: 'error',
                        title: 'Could not update order',
                        description: 'The board has been restored.',
                    });
                },
                onFinish: () => setBusy(null),
            },
        );
    }

    function closeVoid(): void {
        setVoidOrder(null);
        setReason('');
        setError('');
    }

    function voidTicket(): void {
        if (!voidOrder || reason.trim().length < 3 || busy !== null) {
            return;
        }

        const target = voidOrder;
        const previous = tickets;

        setBusy(target.id);
        setTickets((current) =>
            current.filter((order) => order.id !== target.id),
        );

        router.post(
            `/app/orders/${target.id}/void`,
            { reason },
            {
                preserveScroll: true,
                only: LIVE_PROPS,
                onSuccess: () => {
                    showToast({
                        type: 'success',
                        title: `${orderCode(target.code)} voided`,
                        description: 'The kitchen has been told.',
                    });
                    closeVoid();
                },
                onError: (errors) => {
                    setTickets(previous);
                    setError(
                        errors.reason ??
                            errors.order ??
                            'Could not void order.',
                    );
                },
                onFinish: () => setBusy(null),
            },
        );
    }

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <div
                    className="flex items-center gap-2"
                    role="group"
                    aria-label="Order view"
                >
                    <ChipFilter
                        type="segment"
                        active={view === 'kanban'}
                        onClick={() => setViewMode('kanban')}
                    >
                        <LayoutGrid className="me-1.5 size-4" aria-hidden />
                        Kanban
                    </ChipFilter>
                    <ChipFilter
                        type="segment"
                        active={view === 'list'}
                        onClick={() => setViewMode('list')}
                    >
                        <List className="me-1.5 size-4" aria-hidden />
                        List
                    </ChipFilter>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <p className="text-caption max-w-full break-words font-mono text-[var(--text-tertiary)]">
                        {tickets.length} open · {kitchenCount} waiting on the
                        kitchen · oldest {oldest} min
                    </p>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-pressed={muted}
                        aria-label={
                            muted
                                ? 'Turn new-order sound on'
                                : 'Mute new-order sound'
                        }
                        onClick={toggleMute}
                    >
                        {muted ? (
                            <BellOff className="size-4" aria-hidden />
                        ) : (
                            <BellRing className="size-4" aria-hidden />
                        )}
                    </Button>
                </div>
            </div>

            {view === 'list' ? (
                <OrderList
                    orders={tickets}
                    now={now}
                    warnMinutes={warnMinutes}
                    lateMinutes={lateMinutes}
                    canAdvance={canAdvance}
                    canTakePayment={canTakePayment}
                    busy={busy}
                    userId={userId}
                    onAdvance={advance}
                    onVoid={setVoidOrder}
                />
            ) : (
                <>
                    {/* Phone: one status-filtered column of full-width cards. */}
                    <div
                        className="flex gap-2 overflow-x-auto pb-1 min-[900px]:hidden"
                        role="group"
                        aria-label="Filter by order status"
                    >
                        {COLUMNS.map((column) => (
                            <ChipFilter
                                key={column.id}
                                type="segment"
                                active={filter === column.id}
                                onClick={() => setFilter(column.id)}
                            >
                                {`${column.title} (${grouped(column.id).length})`}
                            </ChipFilter>
                        ))}
                    </div>

                    <div className="grid min-w-0 grid-cols-1 gap-4 min-[900px]:grid-cols-2 min-[1280px]:grid-cols-4">
                        {COLUMNS.map((column) => {
                            const columnOrders = grouped(column.id);

                            return (
                                <section
                                    key={column.id}
                                    aria-label={column.title}
                                    className={`${
                                        filter !== column.id
                                            ? 'hidden min-[900px]:flex'
                                            : 'flex'
                                    } min-w-0 flex-col gap-3`}
                                >
                                    <div className="flex items-center gap-2 border-b border-[var(--border-subtle)] pb-3">
                                        <span
                                            aria-hidden="true"
                                            className="size-2.5 rounded-full"
                                            style={{
                                                backgroundColor: column.color,
                                            }}
                                        />
                                        <h2 className="text-label font-semibold text-[var(--text-primary)]">
                                            {column.title}
                                        </h2>
                                        <span className="text-caption font-mono text-[var(--text-tertiary)]">
                                            {columnOrders.length}
                                        </span>
                                    </div>

                                    {columnOrders.length ? (
                                        columnOrders.map((order) => (
                                            <KanbanTicket
                                                key={order.id}
                                                order={order}
                                                column={column.id}
                                                canAdvance={canAdvance}
                                                canTakePayment={canTakePayment}
                                                busy={busy === order.id}
                                                onAdvance={advance}
                                                onVoid={setVoidOrder}
                                            />
                                        ))
                                    ) : (
                                        <div className="rounded-[var(--radius-lg)] border border-dashed border-[var(--border-default)] bg-[var(--surface-card)]">
                                            <EmptyState
                                                headline="Nothing here right now"
                                                className="py-6"
                                            />
                                        </div>
                                    )}
                                </section>
                            );
                        })}
                    </div>
                </>
            )}

            {voidOrder && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            closeVoid();
                        }
                    }}
                    title={`Void ${orderCode(voidOrder.code)}?`}
                    description="This cancels the order and tells the kitchen. It cannot be undone."
                >
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="void-reason">Reason for voiding</Label>

                        <Textarea
                            id="void-reason"
                            value={reason}
                            onChange={(event) => {
                                setReason(event.target.value);
                                setError('');
                            }}
                            rows={3}
                            maxLength={500}
                            placeholder="Enter a reason (required)"
                            aria-invalid={error ? true : undefined}
                        />

                        {error && (
                            <p
                                className="text-caption text-[var(--berry-500)]"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}

                        <div className="mt-3 flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                onClick={closeVoid}
                            >
                                Keep order
                            </Button>

                            <Button
                                type="button"
                                variant="destructive"
                                className="flex-1"
                                loading={busy === voidOrder.id}
                                disabled={reason.trim().length < 3}
                                onClick={voidTicket}
                            >
                                Void order
                            </Button>
                        </div>
                    </div>
                </ResponsiveOverlay>
            )}
        </div>
    );
}
