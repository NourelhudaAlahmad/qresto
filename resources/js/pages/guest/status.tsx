import { Head, Link } from '@inertiajs/react';

import { Avatar } from '@/components/qresto/avatar';
import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { ProgressTrail } from '@/components/qresto/progress-trail';
import { StatusPill } from '@/components/qresto/status-pill';
import { StatusTransition } from '@/components/qresto/StatusTransition';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { useLiveData } from '@/hooks/useLiveData';
import GuestLayout from '@/layouts/guest-layout';
import { formatMoney } from '@/lib/formatMoney';
import type { Money, OrderStatus } from '@/types';

type GuestStage = 'placed' | 'preparing' | 'ready' | 'served' | 'cancelled';

type Order = {
    id: number;
    code: string;
    placed_at: string | null;
    currency: string;
    table_number?: string | null;
};

type Waiter = {
    id: number;
    name: string;
    initials: string;
};

type OrderLine = {
    id: number;
    name: string;
    qty: number;
    total: Money;
};

type Receipt = {
    subtotal: Money;
    service_amount: Money;
    tip_amount: Money;
    discount_amount: Money;
    total: Money;
};

type TrailEvent = {
    status: string;
    at: string | null;
    actor: string | null;
};

type EtaRange = {
    min: number;
    max: number;
};

type LiveStatus = {
    status: OrderStatus;
    stage: GuestStage;
    events: TrailEvent[];
    eta_minutes: EtaRange | null;
    paid: boolean;
    payment_method: string | null;
    assigned_waiter: Waiter | null;
};

type Props = {
    order: Order;
    assigned_waiter: Waiter | null;
    lines: OrderLine[];
    receipt: Receipt;
    live: LiveStatus;
    qresto: {
        live: {
            guest_status_interval: number;
        };
    };
};

const LIVE_ONLY = ['live'];

const stages = ['placed', 'preparing', 'ready', 'served'] as const;

const stageLabels = {
    placed: 'Order placed',
    preparing: 'In the kitchen',
    ready: 'Ready at the pass',
    served: 'At your table',
} as const;

const stageCopy: Record<
    GuestStage,
    {
        title: string;
        description: string;
    }
> = {
    placed: {
        title: 'Order received',
        description: 'Your order has been sent to the kitchen.',
    },

    preparing: {
        title: 'Cooking now',
        description:
            'Your food is being prepared. We’ll bring it over as soon as it is ready.',
    },

    ready: {
        title: 'Ready at the pass',
        description:
            'Your order is ready and will be brought to your table shortly.',
    },

    served: {
        title: 'At your table',
        description: 'Enjoy — ask for anything you need.',
    },

    cancelled: {
        title: 'Order cancelled',
        description: 'Please speak with a member of staff.',
    },
};

function formatTime(value: string | null): string | undefined {
    if (!value) {
        return undefined;
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function paymentMethodLabel(method: string | null): string {
    if (!method) {
        return '—';
    }

    return method.toLowerCase();
}

export default function Status({ order, lines, receipt, live, qresto }: Props) {
    useLiveData(qresto.live.guest_status_interval, {
        only: LIVE_ONLY,
    });

    const copy = stageCopy[live.stage];

    const currentIndex = stages.indexOf(live.stage as (typeof stages)[number]);

    const eventFor = (stage: string) =>
        live.events.find((event) => event.status === stage);

    const trailItems = stages.map((stage, index) => {
        const event = eventFor(stage);

        const state: 'done' | 'current' | 'upcoming' =
            currentIndex > index
                ? 'done'
                : currentIndex === index
                  ? 'current'
                  : 'upcoming';

        const eta =
            stage === 'preparing' && state === 'current' && live.eta_minutes
                ? `~${live.eta_minutes.min}–${live.eta_minutes.max} min`
                : undefined;

        const actor =
            event?.actor && event.actor !== 'system' ? event.actor : undefined;

        let detail: string | undefined;

        if (stage === 'placed') {
            detail = live.payment_method
                ? `Sent to the kitchen · ${live.paid ? 'paid' : 'unpaid'} by ${paymentMethodLabel(live.payment_method)}`
                : 'Sent to the kitchen';
        }

        if (stage === 'preparing' && state === 'current') {
            detail = actor && eta ? `${actor} · ${eta}` : (actor ?? eta);
        }

        if (stage === 'ready' && state === 'upcoming' && live.eta_minutes) {
            detail = `Estimated in ~${live.eta_minutes.min}–${live.eta_minutes.max} min`;
        }

        if (stage === 'served') {
            detail = 'Enjoy — ask for anything you need';
        }

        return {
            title: stageLabels[stage],
            time: formatTime(event?.at ?? null),
            detail,
            state,
        };
    });

    const paymentLabel = paymentMethodLabel(live.payment_method);

    const receiptStateLabel = live.paid ? 'Paid' : 'Unpaid';

    return (
        <GuestLayout>
            <Head title={`Order ${order.code}`} />

            <div className="flex h-[100dvh] min-h-0 flex-col bg-[var(--surface-page)] sm:h-[calc(100dvh-48px)]">
                <header className="shrink-0 border-b border-[var(--border-subtle)] bg-[var(--surface-card)] px-5 pb-4 pt-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="font-display text-body font-semibold text-[var(--text-primary)]">
                                Your order
                            </p>

                            <p className="text-caption mt-1 font-mono text-[var(--text-tertiary)]">
                                <span dir="ltr">{order.code}</span>

                                {order.table_number
                                    ? ` · Table ${order.table_number}`
                                    : ''}
                            </p>
                        </div>

                        <div className="[&_[aria-hidden='true']]:animate-pulse">
                            <StatusPill status={live.status} />
                        </div>
                    </div>
                </header>

                <main className="min-h-0 flex-1 overflow-y-auto">
                    <section className="px-5 pb-6 pt-7">
                        <h1 className="font-display max-w-[320px] text-[40px] font-semibold leading-[1.02] tracking-[-0.045em] text-[var(--text-primary)]">
                            <StatusTransition status={live.status}>
                                {copy.title}
                            </StatusTransition>
                        </h1>

                        <p className="text-body mt-3 max-w-[335px] leading-7 text-[var(--text-secondary)]">
                            <StatusTransition status={live.status}>
                                {copy.description}
                            </StatusTransition>
                        </p>
                    </section>

                    <section className="px-5 pb-6">
                        <ProgressTrail items={trailItems} />
                    </section>

                    <div className="space-y-5 px-4 pb-6">
                        {live.assigned_waiter && (
                            <Card>
                                <div className="flex items-center gap-3">
                                    <Avatar
                                        initials={live.assigned_waiter.initials}
                                        size="md"
                                    />

                                    <div className="min-w-0 flex-1">
                                        <p className="font-display text-body truncate font-semibold text-[var(--text-primary)]">
                                            {live.assigned_waiter.name} is on
                                            your table
                                        </p>

                                        <p className="text-caption mt-1 leading-5 text-[var(--text-tertiary)]">
                                            Tap to ask for water, bread, or the
                                            bill
                                        </p>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="md"
                                        disabled
                                        className="h-11 min-w-16 rounded-full"
                                    >
                                        Call
                                    </Button>
                                </div>
                            </Card>
                        )}

                        <Card>
                            <div className="flex items-center justify-between gap-3">
                                <p className="font-display text-body font-semibold text-[var(--text-primary)]">
                                    Receipt
                                </p>

                                <span
                                    className={`text-caption inline-flex h-7 items-center rounded-full px-3 font-semibold ${
                                        live.paid
                                            ? 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]'
                                            : 'bg-[var(--status-preparing-bg)] text-[var(--status-preparing-fg)]'
                                    }`}
                                >
                                    {receiptStateLabel}
                                    {' · '}
                                    {paymentLabel}
                                </span>
                            </div>

                            <div className="mt-5 space-y-3">
                                {lines.map((line) => (
                                    <div
                                        key={line.id}
                                        className="flex items-baseline justify-between gap-4"
                                    >
                                        <p className="text-body min-w-0 flex-1 text-[var(--text-secondary)]">
                                            <span
                                                dir="ltr"
                                                className="me-2 font-mono text-[var(--text-tertiary)]"
                                            >
                                                {line.qty}×
                                            </span>

                                            {line.name}
                                        </p>

                                        <span
                                            dir="ltr"
                                            className="text-body shrink-0 font-mono tabular-nums text-[var(--text-primary)]"
                                        >
                                            {formatMoney(line.total)}
                                        </span>
                                    </div>
                                ))}
                            </div>

                            <div className="my-5 h-px bg-[var(--border-subtle)]" />

                            {Number(receipt.service_amount.amount) > 0 && (
                                <div className="mb-2 flex justify-between gap-4">
                                    <span className="text-caption text-[var(--text-tertiary)]">
                                        Service
                                    </span>

                                    <span
                                        dir="ltr"
                                        className="text-caption font-mono tabular-nums text-[var(--text-secondary)]"
                                    >
                                        {formatMoney(receipt.service_amount)}
                                    </span>
                                </div>
                            )}

                            {Number(receipt.tip_amount.amount) > 0 && (
                                <div className="mb-2 flex justify-between gap-4">
                                    <span className="text-caption text-[var(--text-tertiary)]">
                                        Tip
                                    </span>

                                    <span
                                        dir="ltr"
                                        className="text-caption font-mono tabular-nums text-[var(--text-secondary)]"
                                    >
                                        {formatMoney(receipt.tip_amount)}
                                    </span>
                                </div>
                            )}

                            {Number(receipt.discount_amount.amount) > 0 && (
                                <div className="mb-2 flex justify-between gap-4">
                                    <span className="text-caption text-[var(--text-tertiary)]">
                                        Discount
                                    </span>

                                    <span
                                        dir="ltr"
                                        className="text-caption font-mono tabular-nums text-[var(--text-secondary)]"
                                    >
                                        −{formatMoney(receipt.discount_amount)}
                                    </span>
                                </div>
                            )}

                            <div className="mt-4 flex items-baseline justify-between gap-4">
                                <span className="font-display text-[22px] font-semibold text-[var(--text-primary)]">
                                    {receiptStateLabel}
                                </span>

                                <span
                                    dir="ltr"
                                    className="font-display text-[24px] font-semibold tabular-nums text-[var(--text-primary)]"
                                >
                                    {formatMoney(receipt.total)}
                                </span>
                            </div>
                        </Card>
                    </div>
                </main>

                <StickyDock>
                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="lg"
                            fullWidth
                            className="rounded-full"
                        >
                            <Link href="/menu">Order more</Link>
                        </Button>

                        <Button
                            type="button"
                            size="lg"
                            fullWidth
                            disabled
                            className="rounded-full"
                        >
                            Advance status
                        </Button>
                    </div>
                </StickyDock>
            </div>
        </GuestLayout>
    );
}
