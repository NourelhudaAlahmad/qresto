import { Head, usePage } from '@inertiajs/react';
import { useCallback } from 'react';

import { LiveIndicator } from '@/components/qresto/LiveIndicator';
import { StatusTransition } from '@/components/qresto/StatusTransition';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { useLiveAlertMute } from '@/hooks/useLiveAlertMute';
import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';
import { dashboard } from '@/routes';

type LiveOrder = {
    id: number;
    code: string;
    status: string;
    table?: {
        id: number;
        name?: string;
    } | null;
    assigned_user?: {
        id: number;
        name: string;
    } | null;
};

type LiveServiceRequest = {
    id: number;
    kind: 'water' | 'bread' | 'bill' | 'waiter';
    status: string;
    requested_at: string;
    table?: {
        id: number;
        number: string | number;
        state: string;
    } | null;
};

type DashboardProps = {
    liveOrders: {
        items: LiveOrder[];
        version: string;
    };
    liveServiceRequests: {
        items: LiveServiceRequest[];
        version: string;
    };
};

type PageProps = {
    qresto: {
        live: {
            waiter_interval: number;
        };
    };
};

const requestLabels: Record<LiveServiceRequest['kind'], string> = {
    water: 'Water',
    bread: 'Bread',
    bill: 'Bill requested',
    waiter: 'Call waiter',
};

export default function Dashboard({
    liveOrders,
    liveServiceRequests,
}: DashboardProps) {
    const { qresto } = usePage<PageProps>().props;

    const { isPaused } = useLiveData(qresto.live.waiter_interval, {
        only: ['liveOrders', 'liveServiceRequests'],
        version: liveOrders.version,
    });

    const { muted, toggleMute } = useLiveAlertMute();

    const getOrderKey = useCallback((order: LiveOrder) => order.id, []);

    useNewItemAlert(liveOrders.items, getOrderKey, {
        muted,
    });

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Dashboard</h1>

                    <div className="flex items-center gap-2">
                        <LiveIndicator isPaused={isPaused} />

                        <button
                            type="button"
                            onClick={toggleMute}
                            className="rounded-full border px-3 py-1 text-sm"
                            aria-pressed={muted}
                        >
                            {muted ? 'Unmute alerts' : 'Mute alerts'}
                        </button>
                    </div>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="dark:border-sidebar-border absolute relative inset-0 aspect-video size-full overflow-hidden rounded-xl border stroke-neutral-900/20" />
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="border-sidebar-border/70 rounded-xl border p-6">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold">
                                Live Orders
                            </h2>

                            <p className="text-muted-foreground text-sm">
                                {liveOrders.items.length} active orders
                            </p>
                        </div>

                        <div className="space-y-3">
                            {liveOrders.items.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No live orders.
                                </p>
                            ) : (
                                liveOrders.items.map((order) => (
                                    <div
                                        key={order.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">
                                                {order.code}
                                            </span>

                                            <StatusTransition
                                                status={order.status}
                                            >
                                                <span className="text-muted-foreground text-sm">
                                                    {order.status}
                                                </span>
                                            </StatusTransition>
                                        </div>

                                        {order.table?.name && (
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {order.table.name}
                                            </p>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="border-sidebar-border/70 rounded-xl border p-6">
                        <div className="mb-4">
                            <h2 className="text-lg font-semibold">
                                Service Requests
                            </h2>

                            <p className="text-muted-foreground text-sm">
                                {liveServiceRequests.items.length} pending
                                requests
                            </p>
                        </div>

                        <div className="space-y-3">
                            {liveServiceRequests.items.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No pending service requests.
                                </p>
                            ) : (
                                liveServiceRequests.items.map((request) => (
                                    <div
                                        key={request.id}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="font-medium">
                                                    {
                                                        requestLabels[
                                                            request.kind
                                                        ]
                                                    }
                                                </p>

                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    Table{' '}
                                                    {request.table?.number ??
                                                        '—'}
                                                </p>
                                            </div>

                                            <span className="text-muted-foreground text-sm">
                                                {request.status}
                                            </span>
                                        </div>

                                        {request.kind === 'bill' && (
                                            <p className="mt-2 text-sm font-medium text-orange-700 dark:text-orange-300">
                                                Bill requested
                                            </p>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
