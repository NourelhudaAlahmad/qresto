import { router, usePage } from '@inertiajs/react';
import { BellRing, Ellipsis, LayoutGrid, List, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/qresto/button';
import OrderList from './list';
type OrderStatus = 'placed' | 'pending' | 'preparing' | 'ready' | 'served';
type Column = 'new' | 'preparing' | 'ready' | 'served';
type Ticket = {
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
    canAdvance: boolean;
    canTakePayment: boolean;
    warnMinutes: number;
    lateMinutes: number;
};
const COLUMNS: { id: Column; title: string; dot: string; border: string }[] = [
    {
        id: 'new',
        title: 'New',
        dot: 'bg-saffron-500',
        border: 'border-saffron-500',
    },
    {
        id: 'preparing',
        title: 'Preparing',
        dot: 'bg-teal-600',
        border: 'border-teal-600',
    },
    {
        id: 'ready',
        title: 'Ready',
        dot: 'bg-clay-500',
        border: 'border-clay-500',
    },
    {
        id: 'served',
        title: 'Served',
        dot: 'bg-success-fg',
        border: 'border-success-fg',
    },
];
function columnOf(status: OrderStatus): Column {
    return status === 'placed' || status === 'pending' ? 'new' : status;
}
function elapsed(placedAt: string | null, now: number): number {
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
function nextStatus(status: OrderStatus): OrderStatus | null {
    if (status === 'placed') {
        return 'pending';
    }

    if (status === 'pending') {
        return 'preparing';
    }

    if (status === 'preparing') {
        return 'ready';
    }

    if (status === 'ready') {
        return 'served';
    }

    return null;
}
function verb(status: OrderStatus): string {
    return {
        placed: 'Accept order',
        pending: 'Start cooking',
        preparing: 'Mark ready',
        ready: 'Mark served',
        served: 'Take payment',
    }[status];
}
function playAlert(): void {
    try {
        const AudioContextCtor = window.AudioContext;
        const audio = new AudioContextCtor();
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 680;
        gain.gain.value = 0.06;
        oscillator.connect(gain);
        gain.connect(audio.destination);
        oscillator.start();
        oscillator.stop(audio.currentTime + 0.16);
        oscillator.onended = () => {
            void audio.close();
        };
    } catch {
        // Audio playback may be blocked until the user interacts with the page.
    }
}
export default function Kanban({
    orders,
    canAdvance,
    canTakePayment,
    warnMinutes,
    lateMinutes,
}: Props) {
    const { auth } = usePage().props as unknown as {
        auth: { user: { id: number | string } };
    };
    const userId = auth.user.id;
    const viewKey = `qresto:waiter:${userId}:view`;
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
    const [toast, setToast] = useState<string | null>(null);
    const knownIds = useRef(new Set(orders.map((order) => order.id)));
    const polling = useRef(false);
    useEffect(() => {
        const tick = window.setInterval(() => setNow(Date.now()), 15_000);
        const poll = window.setInterval(() => {
            if (document.visibilityState !== 'visible' || polling.current) {
                return;
            }

            polling.current = true;
            router.reload({
                only: ['orders'],
                onSuccess: (page) => {
                    const incomingOrders = (
                        page.props as unknown as { orders?: Ticket[] }
                    ).orders;

                    if (!incomingOrders) {
                        return;
                    }

                    const newlyAdded = incomingOrders.filter(
                        (order) => !knownIds.current.has(order.id),
                    );

                    if (newlyAdded.length) {
                        setToast(
                            `${newlyAdded.length} new ${newlyAdded.length === 1 ? 'order' : 'orders'} received`,
                        );
                        playAlert();
                    }

                    incomingOrders.forEach((order) =>
                        knownIds.current.add(order.id),
                    );
                    setTickets(incomingOrders);
                },
                onFinish: () => {
                    polling.current = false;
                },
            });
        }, 5_000);

        return () => {
            window.clearInterval(tick);
            window.clearInterval(poll);
        };
    }, []);
    useEffect(() => {
        if (!toast) {
            return;
        }

        const timeout = window.setTimeout(() => setToast(null), 4500);

        return () => window.clearTimeout(timeout);
    }, [toast]);
    const grouped = (column: Column) =>
        tickets.filter((order) => columnOf(order.status) === column);
    const kitchenCount = tickets.filter(
        (order) => order.status === 'pending' || order.status === 'preparing',
    ).length;
    const oldest = tickets.length
        ? Math.max(...tickets.map((order) => elapsed(order.placed_at, now)))
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
            current.map((t) =>
                t.id === order.id ? { ...t, status: next } : t,
            ),
        );
        setBusy(order.id);
        router.patch(
            `/app/orders/${order.id}/advance`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setToast(`${order.code} updated`),
                onError: () => {
                    setTickets(previous);
                    setToast('Could not update order.');
                },
                onFinish: () => setBusy(null),
            },
        );
    }
    function voidTicket(): void {
        if (!voidOrder || reason.trim().length < 3 || busy !== null) {
            return;
        }

        const previous = tickets;
        setBusy(voidOrder.id);
        setTickets((current) =>
            current.filter((order) => order.id !== voidOrder.id),
        );
        router.post(
            `/app/orders/${voidOrder.id}/void`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setToast(`${voidOrder.code} voided`);
                    setVoidOrder(null);
                    setReason('');
                    setError('');
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
    function OrderTicket({ order, column }: { order: Ticket; column: Column }) {
      
        const minutes = elapsed(order.placed_at, now);
        const timeColor =
            minutes >= lateMinutes
                ? 'text-berry-500'
                : minutes >= warnMinutes
                  ? 'text-saffron-700'
                  : 'text-text-tertiary';
        const [menuOpen, setMenuOpen] = useState(false);
        const next = nextStatus(order.status);

        return (
            <article
className="bg-surface-card shadow-card border-border-subtle min-w-0 overflow-hidden rounded-xl border border-s-[3px]"
style={{
    borderInlineStartColor:
        column === 'new'
            ? '#E5A000'
            : column === 'preparing'
              ? '#009E91'
              : column === 'ready'
                ? '#C85A32'
                : '#39865B',
}}    
            >
                <div className="space-y-3 p-4">
                    <div className="flex items-start justify-between gap-2">
                        <strong className="text-body text-text-primary min-w-0 shrink-0 whitespace-nowrap font-mono">
                            {order.code.startsWith('#')
                                ? order.code
                                : `#${order.code}`}
                        </strong>
                        <span
                            className={`text-caption shrink-0 whitespace-nowrap font-mono ${timeColor}`}
                        >
                            {minutes} min
                        </span>
                    </div>
                    <p className="text-label text-text-secondary break-words">
                        ▦ Table {order.table ?? '—'} ·{' '}
                        {order.guest_name || 'Guest'}
                    </p>
                    <div className="space-y-2">
                        {order.lines.map((line) => (
                            <div key={line.id}>
                                <p className="text-body text-text-primary break-words">
                                    <span className="text-text-tertiary">
                                        {line.qty}×
                                    </span>{' '}
                                    {line.name}
                                </p>
                                {line.note && (
                                    <p className="bg-saffron-50 text-saffron-700 text-caption ms-6 mt-1 break-words rounded-md px-2 py-1">
                                        {line.note}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
                <div className="bg-surface-page border-border-subtle flex flex-wrap items-center justify-between gap-x-2 gap-y-1 border-y px-4 py-2">
                    <span
                        className={`text-caption rounded-full px-2 py-1 font-medium ${order.paid ? 'bg-success-bg text-success-fg' : 'bg-saffron-50 text-saffron-700'}`}
                    >
                        {order.paid ? 'Paid' : 'Unpaid'}
                    </span>
                    <span className="text-body text-text-primary min-w-0 shrink-0 whitespace-nowrap font-mono">
                        {money(order.total_minor, order.currency)}
                    </span>
                </div>
                <div className="flex min-w-0 items-stretch gap-2 p-3">
                    {next && canAdvance ? (
                        <Button
                            type="button"
                            variant="primary"
                            fullWidth
                            className="min-h-11 min-w-0 flex-1 whitespace-normal break-normal px-2 py-2 text-center leading-snug"
                            loading={busy === order.id}
                            onClick={() => advance(order)}
                        >
                            {verb(order.status)}
                        </Button>
                    ) : (
                        <span className="text-caption text-text-secondary flex min-h-11 min-w-0 flex-1 items-center break-words">
                            {order.status === 'served'
                                ? canTakePayment
                                    ? 'Payment handled in S5'
                                    : 'Waiting for payment'
                                : 'No action available'}
                        </span>
                    )}
                    <div className="relative shrink-0">
                        <button
                            type="button"
                            aria-label={`More actions for ${order.code}`}
                            aria-expanded={menuOpen}
                            className="border-border-subtle text-text-secondary flex size-11 shrink-0 items-center justify-center rounded-full border"
                            onClick={() => setMenuOpen(!menuOpen)}
                        >
                            <Ellipsis className="size-5" />
                        </button>
                        {menuOpen && (
                            <div className="bg-surface-card border-border-subtle absolute bottom-12 end-0 z-20 min-w-40 rounded-lg border p-1 shadow-lg">
                                <button
                                    type="button"
                                    disabled={
                                        !canAdvance ||
                                        ![
                                            'placed',
                                            'pending',
                                            'preparing',
                                        ].includes(order.status)
                                    }
                                    className="text-berry-500 hover:bg-surface-page text-label min-h-11 w-full rounded-md px-3 text-start disabled:opacity-40"
                                    onClick={() => {
                                        setVoidOrder(order);
                                        setMenuOpen(false);
                                    }}
                                >
                                    Void order
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </article>
        );
    }

    return (
        <div className="min-w-0 space-y-4">
            <div className="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <div
                    className="flex items-center gap-2"
                    role="group"
                    aria-label="Order view"
                >
                    <button
                        type="button"
                        onClick={() => setViewMode('kanban')}
                        className={`text-label flex min-h-11 items-center gap-2 rounded-full px-4 font-semibold ${view === 'kanban' ? 'bg-ink-900 !text-white' : 'bg-surface-card text-text-secondary border-border-subtle border'}`}
                    >
                        <LayoutGrid className="size-4" />
                        Kanban
                    </button>
                    <button
                        type="button"
                        onClick={() => setViewMode('list')}
                        className={`text-label flex min-h-11 items-center gap-2 rounded-full px-4 font-semibold ${view === 'list' ? 'bg-ink-900 !text-white' : 'bg-surface-card text-text-secondary border-border-subtle border'}`}
                    >
                        <List className="size-4" />
                        List
                    </button>
                </div>
                <p className="text-caption text-text-tertiary max-w-full break-words font-mono">
                    {tickets.length} open · {kitchenCount} waiting on the
                    kitchen · oldest {oldest} min
                </p>
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
                    <div
                        className="flex gap-2 overflow-x-auto pb-1 min-[900px]:hidden"
                        aria-label="Filter by order status"
                    >
                        {COLUMNS.map((column) => (
                            <button
                                key={column.id}
                                type="button"
                                onClick={() => setFilter(column.id)}
                                aria-pressed={filter === column.id}
                                className={`text-label min-h-11 shrink-0 rounded-full px-4 ${filter === column.id ? 'bg-ink-900 !text-white' : 'border-border-subtle bg-surface-card text-text-secondary border'}`}
                            >
                                {column.title} ({grouped(column.id).length})
                            </button>
                        ))}
                    </div>
                    <div className="grid min-w-0 grid-cols-1 gap-4 min-[900px]:grid-cols-2 min-[1500px]:grid-cols-4">
                        {COLUMNS.map((column) => (
                            <section
                                key={column.id}
                                className={`${filter !== column.id ? 'hidden min-[900px]:block' : ''} min-w-0 space-y-3`}
                            >
                                <div className="border-border-subtle flex items-center gap-2 border-b pb-3">
                                    <span
                                        className={`size-3 rounded-full ${column.dot}`}
                                    />
                                    <h2 className="text-label text-text-primary font-semibold">
                                        {column.title}
                                    </h2>
                                    <span className="text-caption text-text-tertiary">
                                        {grouped(column.id).length}
                                    </span>
                                </div>
                                {grouped(column.id).length ? (
                                    grouped(column.id).map((order) => (
                                        <OrderTicket
                                            key={order.id}
                                            order={order}
                                            column={column.id}
                                        />
                                    ))
                                ) : (
                                    <div className="bg-surface-card border-border-subtle text-caption text-text-tertiary rounded-xl border border-dashed p-6 text-center">
                                        Nothing here right now
                                    </div>
                                )}
                            </section>
                        ))}
                    </div>
                </>
            )}
            {toast && (
                <div
                    role="status"
                    className="bg-ink-900 fixed end-4 top-20 z-50 flex max-w-sm items-center gap-2 rounded-xl px-4 py-3 !text-white shadow-lg"
                >
                    <BellRing className="size-4" />
                    {toast}
                    <button
                        type="button"
                        onClick={() => setToast(null)}
                        aria-label="Dismiss notification"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            )}
            {voidOrder && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    role="presentation"
                >
                    <div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="void-title"
                        className="bg-surface-card w-full max-w-md rounded-2xl p-6 shadow-xl"
                    >
                        <h2
                            id="void-title"
                            className="font-display text-title-1 text-text-primary font-semibold"
                        >
                            Void {voidOrder.code}?
                        </h2>
                        <p className="text-body text-text-secondary mt-2">
                            This action cancels the order. Tell the kitchen why.
                        </p>
                        <label
                            htmlFor="void-reason"
                            className="text-label text-text-primary mt-5 block"
                        >
                            Reason for voiding
                        </label>
                        <textarea
                            id="void-reason"
                            value={reason}
                            onChange={(event) => {
                                setReason(event.target.value);
                                setError('');
                            }}
                            rows={3}
                            maxLength={500}
                            className="border-border-subtle bg-surface-card text-text-primary mt-2 w-full rounded-lg border p-3"
                            placeholder="Enter a reason (required)"
                        />
                        {error && (
                            <p
                                className="text-berry-500 text-caption mt-2"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}
                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                className="border-border-subtle text-label min-h-11 flex-1 rounded-lg border"
                                onClick={() => {
                                    setVoidOrder(null);
                                    setReason('');
                                    setError('');
                                }}
                            >
                                Keep order
                            </button>
                            <button
                                type="button"
                                disabled={
                                    reason.trim().length < 3 || busy !== null
                                }
                                onClick={voidTicket}
                                className="bg-berry-500 text-label min-h-11 flex-1 rounded-lg font-semibold text-white disabled:opacity-50"
                            >
                                Void order
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
