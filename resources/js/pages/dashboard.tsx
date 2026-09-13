import { Head } from '@inertiajs/react';

import { LiveIndicator } from '@/components/qresto/LiveIndicator';
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

type DashboardProps = {
    liveOrders: {
        items: LiveOrder[];
        version: string;
    };
};

export default function Dashboard({ liveOrders }: DashboardProps) {
    const { isPaused } = useLiveData(5000, {
        only: ['liveOrders'],
        version: liveOrders.version,
    });

    const { muted, toggleMute } = useLiveAlertMute();

    useNewItemAlert(liveOrders.items, (order) => order.id, {
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
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border p-6 md:min-h-min">
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold">Live Orders</h2>

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

                                        <span className="text-muted-foreground text-sm">
                                            {order.status}
                                        </span>
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
