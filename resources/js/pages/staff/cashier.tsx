import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Stat } from '@/components/qresto/stat';
import { useLiveData } from '@/hooks/useLiveData';
import { useTableCheckout } from '@/pages/staff/floor';
import OrderList, { money } from '@/pages/staff/orders/list';
import type { Ticket } from '@/pages/staff/orders/list';

type Props = {
    orders: Ticket[];
    canTakePayment: boolean;
    warnMinutes: number;
    lateMinutes: number;
};

/**
 * The cashier's view (#34): "the same tickets with the bill total and a
 * Take payment action". The S4 order list scoped to unpaid orders, and the
 * floor's own checkout to settle them. No new table, ticket or endpoint.
 */
export default function Cashier({
    orders,
    canTakePayment,
    warnMinutes,
    lateMinutes,
}: Props) {
    const { qresto, auth } = usePage().props as unknown as {
        qresto?: { live?: { floor_interval?: number } };
        auth?: { user?: { id: number } | null };
    };
    const interval = qresto?.live?.floor_interval ?? 8000;
    const { isPaused } = useLiveData(interval, { only: ['orders'] });
    const [now] = useState(() => Date.now());
    const [paying, setPaying] = useState<Ticket | null>(null);

    const { select, close, checkout } = useTableCheckout({
        canPay: canTakePayment,
        canManageTable: false,
        interval,
        isPaused,
        onChanged: () => router.reload({ only: ['orders'] }),
    });

    const outstanding = orders.reduce(
        (sum, order) => sum + order.total_minor,
        0,
    );
    const currency = orders[0]?.currency ?? 'USD';
    const toSettle = orders.filter((order) => order.status === 'served');

    function takePayment(order: Ticket) {
        if (order.table_id === null || order.table_id === undefined) {
            return;
        }

        setPaying(order);
        select(order.table_id);
    }

    return (
        <>
            <Head title="Unpaid bills" />

            <div className="flex flex-col gap-4">
                {/* The total is the point of this screen. */}
                <section
                    aria-label="Outstanding"
                    className="grid grid-cols-1 gap-4 min-[560px]:grid-cols-2"
                >
                    <Stat
                        tone="inverse"
                        className="p-5"
                        label="Outstanding"
                        value={money(outstanding, currency)}
                        hint={`${orders.length} unpaid ${
                            orders.length === 1 ? 'order' : 'orders'
                        }`}
                    />
                    <Stat
                        className="p-5"
                        label="Ready to settle"
                        value={String(toSettle.length)}
                        hint="served and waiting for payment"
                    />
                </section>

                <OrderList
                    orders={orders}
                    now={now}
                    warnMinutes={warnMinutes}
                    lateMinutes={lateMinutes}
                    canAdvance={false}
                    canTakePayment={canTakePayment}
                    busy={null}
                    userId={auth?.user?.id ?? 'cashier'}
                    onAdvance={() => undefined}
                    onVoid={() => undefined}
                    onTakePayment={takePayment}
                />
            </div>

            <ResponsiveOverlay
                open={paying !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPaying(null);
                        close();
                    }
                }}
                title={
                    paying
                        ? `Table ${paying.table ?? ''} · ${paying.code}`
                        : 'Take payment'
                }
                description="Settle with the floor's checkout."
            >
                {checkout}
            </ResponsiveOverlay>
        </>
    );
}
