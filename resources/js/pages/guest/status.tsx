import { Head, Link, router } from '@inertiajs/react';
import { CircleHelp, ClipboardList, Menu as MenuIcon } from 'lucide-react';
import { useState } from 'react';

import { Numeric } from '@/components/numeric';
import { Avatar } from '@/components/qresto/avatar';
import { Button } from '@/components/qresto/button';
import { CallWaiterSheet } from '@/components/qresto/call-waiter-sheet';
import { Card } from '@/components/qresto/card';
import { HelpPanel } from '@/components/qresto/help-panel';
import { OfflineBanner } from '@/components/qresto/offline-banner';
import { ProgressTrail } from '@/components/qresto/progress-trail';
import { StatusPill } from '@/components/qresto/status-pill';
import { StatusTransition } from '@/components/qresto/StatusTransition';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { TabBar } from '@/components/qresto/tab-bar';
import { useMinutesSince } from '@/hooks/useLiveConnection';
import { useLiveData } from '@/hooks/useLiveData';
import GuestLayout from '@/layouts/guest-layout';
import { formatMinutes } from '@/lib/elapsed';
import { formatMoney } from '@/lib/formatMoney';
import { fill, useI18n } from '@/lib/i18n';
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

type ServiceRequestKind = 'water' | 'bread' | 'bill' | 'waiter';

type SittingRound = {
    id: number;
    code: string;
    status: OrderStatus;
    total: Money;
    paid: boolean;
    current: boolean;
};

// Every round this table has ordered, and what it still owes (#50).
type Sitting = {
    rounds: SittingRound[];
    due: Money;
};

type LiveStatus = {
    open_requests: ServiceRequestKind[];
    status: OrderStatus;
    stage: GuestStage;
    events: TrailEvent[];
    eta_minutes: EtaRange | null;
    paid: boolean;
    payment_method: string | null;
    assigned_waiter: Waiter | null;
    sitting?: Sitting;
};

type Session = {
    id: number;
    active: boolean;
};

type Props = {
    restaurant?: { phone?: string | null };
    order: Order;
    assigned_waiter: Waiter | null;
    lines: OrderLine[];
    receipt: Receipt;
    live: LiveStatus;

    // Will be supplied by OrderStatusController.
    session?: Session;
    cart_count?: number;

    qresto: {
        live: {
            guest_status_interval: number;
        };
    };
};

const LIVE_ONLY = ['live'];

const stages = ['placed', 'preparing', 'ready', 'served'] as const;

function formatTime(value: string | null): string | undefined {
    if (!value) {
        return undefined;
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export default function Status({
    restaurant,
    order,
    lines,
    receipt,
    live,
    qresto,
    session,
    cart_count = 0,
}: Props) {
    const { guest: g } = useI18n();
    const [helpOpen, setHelpOpen] = useState(false);
    const [callSheetOpen, setCallSheetOpen] = useState(false);

    const { isDisconnected, connection } = useLiveData(
        qresto.live.guest_status_interval,
        { only: LIVE_ONLY },
    );
    const minutesStale = useMinutesSince(connection.lastSuccessAt);

    const saveLastTab = (tab: 'menu' | 'order' | 'status') => {
        if (session?.id == null) {
            return;
        }

        try {
            window.localStorage.setItem(`qresto_last_tab_${session.id}`, tab);
        } catch {
            // Navigation should continue when local storage is unavailable.
        }
    };

    const goToMenu = () => {
        saveLastTab('menu');
        router.visit('/menu');
    };

    const goToCart = () => {
        saveLastTab('order');
        router.visit('/cart');
    };

    const stageLabels = {
        placed: g.status.stage_placed,
        preparing: g.status.stage_preparing,
        ready: g.status.stage_ready,
        served: g.status.stage_served,
    } as const;

    const stageCopy: Record<
        GuestStage,
        { title: string; description: string }
    > = {
        placed: {
            title: g.status.placed_title,
            description: g.status.placed_body,
        },
        preparing: {
            title: g.status.preparing_title,
            description: g.status.preparing_body,
        },
        ready: {
            title: g.status.ready_title,
            description: g.status.ready_body,
        },
        served: {
            title: g.status.served_title,
            description: g.status.served_body,
        },
        cancelled: {
            title: g.status.cancelled_title,
            description: g.status.cancelled_body,
        },
    };

    const paymentMethodLabel = (method: string | null): string => {
        if (!method) {
            return '—';
        }

        const key = `method_${method.toLowerCase()}` as keyof typeof g.status;

        return g.status[key] ?? method.toLowerCase();
    };

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
                ? fill(g.status.eta, {
                      min: live.eta_minutes.min,
                      max: live.eta_minutes.max,
                  })
                : undefined;

        const actor =
            event?.actor && event.actor !== 'system' ? event.actor : undefined;

        let detail: string | undefined;

        if (stage === 'placed') {
            detail = live.payment_method
                ? fill(live.paid ? g.status.sent_paid : g.status.sent_unpaid, {
                      method: paymentMethodLabel(live.payment_method),
                  })
                : g.status.sent;
        }

        if (stage === 'preparing' && state === 'current') {
            detail = actor && eta ? `${actor} · ${eta}` : (actor ?? eta);
        }

        if (stage === 'ready' && state === 'upcoming' && live.eta_minutes) {
            detail = fill(g.status.estimated, {
                min: live.eta_minutes.min,
                max: live.eta_minutes.max,
            });
        }

        if (stage === 'served') {
            detail = g.status.enjoy;
        }

        return {
            title: stageLabels[stage],
            time: formatTime(event?.at ?? null),
            detail,
            state,
        };
    });

    const paymentLabel = paymentMethodLabel(live.payment_method);

    const receiptStateLabel = live.paid ? g.status.paid : g.status.unpaid;
    const sitting = live.sitting;

    return (
        <GuestLayout wide>
            <Head title={fill(g.status.head, { code: order.code })} />

            <div className="flex h-[100dvh] min-h-0 flex-col bg-[var(--surface-page)]">
                <header className="shrink-0 border-b border-[var(--border-subtle)] bg-[var(--surface-card)] px-5 pb-4 pt-4 lg:px-[var(--gutter-desktop)]">
                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="font-display text-body font-semibold text-[var(--text-primary)]">
                                {g.status.your_order}
                            </p>

                            <p className="text-caption mt-1 font-mono text-[var(--text-tertiary)]">
                                <span dir="ltr">{order.code}</span>

                                {order.table_number
                                    ? ` · ${fill(g.status.table, { table: order.table_number })}`
                                    : ''}
                            </p>
                        </div>

                        <div className="[&_[aria-hidden='true']]:animate-pulse">
                            <StatusPill status={live.status} />
                        </div>
                    </div>
                </header>

                <main className="min-h-0 flex-1 overflow-y-auto lg:grid lg:grid-cols-2 lg:content-start lg:items-start lg:gap-x-8 lg:px-[var(--gutter-desktop)] lg:py-8">
                    {/* Wide (#61): where the order is on one side, the waiter and
                        the receipt on the other. The wrapper is display: contents
                        below lg, so a phone keeps its single column. */}
                    {/* Left open on the table through the meal, on a phone
                        moving in and out of signal (#58). */}
                    {isDisconnected && (
                        <div className="px-5 pt-4 lg:col-span-2 lg:px-0 lg:pb-6 lg:pt-0">
                            <OfflineBanner
                                tone="guest"
                                title={g.status.offline_title}
                                detail={
                                    minutesStale < 1
                                        ? g.status.offline_just_now
                                        : fill(g.status.offline_ago, {
                                              time: formatMinutes(minutesStale),
                                          })
                                }
                            />
                        </div>
                    )}

                    <div className="contents lg:block">
                        <section className="px-5 pb-6 pt-7 lg:px-0 lg:pt-0">
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

                        <section className="px-5 pb-6 lg:px-0">
                            <ProgressTrail items={trailItems} />
                        </section>
                    </div>

                    <div className="space-y-5 px-4 pb-6 lg:px-0">
                        <Card>
                            <div className="flex items-center gap-3">
                                {live.assigned_waiter && (
                                    <Avatar
                                        initials={live.assigned_waiter.initials}
                                        size="md"
                                    />
                                )}

                                <div className="min-w-0 flex-1">
                                    <p className="font-display text-body truncate font-semibold text-[var(--text-primary)]">
                                        {live.assigned_waiter
                                            ? fill(g.status.waiter_on_table, {
                                                  name: live.assigned_waiter
                                                      .name,
                                              })
                                            : g.status.need_anything}
                                    </p>

                                    <p className="text-caption mt-1 leading-5 text-[var(--text-tertiary)]">
                                        {g.status.call_hint}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="md"
                                    onClick={() => setCallSheetOpen(true)}
                                    className="h-11 min-w-16 rounded-full"
                                >
                                    {g.status.call}
                                </Button>
                            </div>
                        </Card>

                        <Card>
                            <div className="flex items-center justify-between gap-3">
                                <p className="font-display text-body font-semibold text-[var(--text-primary)]">
                                    {g.status.receipt}
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
                                        {g.status.service}
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
                                        {g.status.tip}
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
                                        {g.status.discount}
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

                        {sitting && sitting.rounds.length > 1 && (
                            <Card className="space-y-3">
                                <div className="flex items-baseline justify-between gap-3">
                                    <h2 className="font-display text-title-2 font-semibold text-[var(--text-primary)]">
                                        {g.status.sitting_title}
                                    </h2>
                                    <span className="text-caption text-[var(--text-secondary)]">
                                        {fill(g.status.sitting_rounds, {
                                            count: sitting.rounds.length,
                                        })}
                                    </span>
                                </div>

                                <ul className="divide-y divide-[var(--border-subtle)]">
                                    {sitting.rounds.map((round) => (
                                        <li
                                            key={round.id}
                                            className="flex min-h-11 items-center justify-between gap-3 py-2"
                                        >
                                            <span className="flex min-w-0 items-center gap-2">
                                                {round.current ? (
                                                    <span className="font-medium text-[var(--text-primary)]">
                                                        <Numeric>
                                                            {round.code}
                                                        </Numeric>
                                                        {' · '}
                                                        {g.status.this_round}
                                                    </span>
                                                ) : (
                                                    <Link
                                                        href={`/orders/${round.id}/status`}
                                                        className="font-medium text-[var(--text-link)] underline-offset-2 hover:underline"
                                                    >
                                                        <Numeric>
                                                            {round.code}
                                                        </Numeric>
                                                    </Link>
                                                )}
                                                <StatusPill
                                                    status={round.status}
                                                />
                                            </span>

                                            <span className="text-body text-[var(--text-secondary)]">
                                                <Numeric>
                                                    {formatMoney(round.total)}
                                                </Numeric>
                                                {round.paid
                                                    ? ` · ${g.status.round_paid}`
                                                    : ''}
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                <div className="flex items-center justify-between gap-3 border-t border-[var(--border-subtle)] pt-3">
                                    <span className="font-semibold text-[var(--text-primary)]">
                                        {Number(sitting.due.amount) > 0
                                            ? g.status.still_owes
                                            : g.status.all_paid}
                                    </span>
                                    {Number(sitting.due.amount) > 0 && (
                                        <span className="font-display text-title-2 font-semibold text-[var(--text-primary)]">
                                            <Numeric>
                                                {formatMoney(sitting.due)}
                                            </Numeric>
                                        </span>
                                    )}
                                </div>
                            </Card>
                        )}
                    </div>
                </main>

                {/* The canvas's "Advance status" only steps the prototype's
                    timeline for reviewers; guests never move their own order. */}
                <StickyDock className="lg:px-[var(--gutter-desktop)]">
                    <div className="lg:ms-auto lg:w-[calc(50%-1rem)]">
                        <Button
                            asChild
                            variant="outline"
                            size="lg"
                            fullWidth
                            className="rounded-full"
                        >
                            <Link href="/menu">{g.status.order_more}</Link>
                        </Button>
                    </div>
                </StickyDock>

                <TabBar
                    items={[
                        {
                            label: g.nav.menu,
                            icon: <MenuIcon className="size-5" />,
                            onClick: goToMenu,
                        },
                        {
                            label: g.nav.order,
                            icon: <ClipboardList className="size-5" />,
                            badge: cart_count > 0 ? cart_count : undefined,
                            onClick: goToCart,
                        },
                        {
                            label: g.nav.status,
                            icon: (
                                <span className="font-mono text-base leading-none">
                                    02
                                </span>
                            ),
                            active: true,
                            onClick: () => {
                                saveLastTab('status');
                            },
                        },
                        {
                            label: g.nav.help,
                            icon: <CircleHelp className="size-5" />,
                            onClick: () => setHelpOpen(true),
                        },
                    ]}
                />
            </div>

            <HelpPanel
                open={helpOpen}
                onOpenChange={setHelpOpen}
                restaurantPhone={restaurant?.phone}
                onCallWaiter={() => setCallSheetOpen(true)}
            />

            <CallWaiterSheet
                open={callSheetOpen}
                onOpenChange={setCallSheetOpen}
                openRequests={live.open_requests ?? []}
            />
        </GuestLayout>
    );
}
