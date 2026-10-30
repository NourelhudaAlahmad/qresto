import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { OrderTicket } from '@/components/qresto/order-ticket';
import { showToast } from '@/components/qresto/toast';
import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';
import { formatMinutes } from '@/lib/elapsed';
import type { OrderStatus } from '@/types';

type Ticket = {
    id: number;
    code: string;
    status: 'ready';
    table: string | null;
    guest: string | null;
    elapsed_minutes: number;
    lines: { qty: number; name: string; note: string | null }[];
};

type Props = {
    tickets: Ticket[];
    pollIntervalMs: number;
};

/**
 * The runner's view (#34): "this grid filtered to Ready, one column, 60px
 * type". The same KDS collection and the same bump endpoint as the pass;
 * the runner's one action, Collected, takes a plate from ready to served.
 */
export default function Runner({ tickets, pollIntervalMs }: Props) {
    const [collected, setCollected] = useState<Set<number>>(new Set());
    const visible = tickets.filter((ticket) => !collected.has(ticket.id));

    useLiveData(pollIntervalMs, { only: ['tickets', 'now'] });

    useNewItemAlert(tickets, (ticket) => ticket.id, {
        title: 'Ready to collect',
    });

    function collect(ticket: Ticket) {
        setCollected((current) => new Set(current).add(ticket.id));

        router.post(
            `/app/kds/tickets/${ticket.id}/bump`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['tickets', 'now'],
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: `${ticket.code} collected`,
                    }),
                onError: (errors) => {
                    setCollected((current) => {
                        const next = new Set(current);
                        next.delete(ticket.id);

                        return next;
                    });
                    showToast({
                        type: 'error',
                        title: 'Not collected',
                        description: errors.order ?? Object.values(errors)[0],
                    });
                },
            },
        );
    }

    return (
        <>
            <Head title="Ready to collect" />

            <section
                data-surface="runner"
                aria-label="Ready to collect"
                className="text-body rounded-[var(--radius-xl)] bg-[var(--ink-950)] p-6 text-[var(--ink-25)]"
            >
                <header className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <h2 className="font-display text-title-1 font-semibold">
                        Ready to collect
                    </h2>
                    <p className="text-body-lg font-mono text-[var(--ink-400)]">
                        {visible.length}{' '}
                        {visible.length === 1 ? 'plate' : 'plates'}
                    </p>
                </header>

                {visible.length === 0 ? (
                    <p className="font-display text-title-1 py-24 text-center text-[var(--ink-400)]">
                        Nothing waiting at the pass.
                    </p>
                ) : (
                    // One column, so it reads across a room.
                    <ol className="mx-auto mt-5 flex max-w-[880px] flex-col gap-5">
                        {visible.map((ticket) => (
                            <li
                                key={ticket.id}
                                data-ticket={ticket.code}
                                className="animate-[qr-rise_var(--dur-base)_var(--ease-enter)] motion-reduce:animate-none"
                            >
                                <OrderTicket
                                    variant="dark"
                                    size="runner"
                                    code={ticket.code}
                                    elapsed={formatMinutes(
                                        ticket.elapsed_minutes,
                                    )}
                                    table={
                                        ticket.table
                                            ? `Table ${ticket.table}`
                                            : 'Takeaway'
                                    }
                                    guest={ticket.guest ?? 'Guest'}
                                    status={ticket.status as OrderStatus}
                                    items={ticket.lines.map((line) => ({
                                        qty: `${line.qty}×`,
                                        name: line.name,
                                        note: line.note ?? undefined,
                                    }))}
                                    paid={false}
                                    total=""
                                    advanceLabel="Collected"
                                    advanceAriaLabel={`Collected ${ticket.code} for Table ${ticket.table ?? ''}`.trim()}
                                    onAdvance={() => collect(ticket)}
                                />
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </>
    );
}
