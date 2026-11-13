import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { OrderTicket } from '@/components/qresto/order-ticket';
import { showToast } from '@/components/qresto/toast';
import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';
import { formatMinutes } from '@/lib/elapsed';
import { fill, useI18n } from '@/lib/i18n';
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
    const {
        staff: { runner: t },
    } = useI18n();
    const [collected, setCollected] = useState<Set<number>>(new Set());
    const visible = tickets.filter((ticket) => !collected.has(ticket.id));

    useLiveData(pollIntervalMs, { only: ['tickets', 'now'] });

    useNewItemAlert(tickets, (ticket) => ticket.id, {
        title: t.new_ticket,
    });

    const tableLabel = (ticket: Ticket) =>
        ticket.table ? fill(t.table, { table: ticket.table }) : t.takeaway;

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
                        title: fill(t.collected_toast, { code: ticket.code }),
                    }),
                onError: (errors) => {
                    setCollected((current) => {
                        const next = new Set(current);
                        next.delete(ticket.id);

                        return next;
                    });
                    showToast({
                        type: 'error',
                        title: t.not_collected,
                        description: errors.order ?? Object.values(errors)[0],
                    });
                },
            },
        );
    }

    return (
        <>
            <Head title={t.head} />

            <section
                data-surface="runner"
                aria-label={t.head}
                className="text-body rounded-[var(--radius-xl)] bg-[var(--ink-950)] p-6 text-[var(--ink-25)]"
            >
                <header className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <h2 className="font-display text-title-1 font-semibold">
                        {t.head}
                    </h2>
                    <p className="text-body-lg font-mono text-[var(--ink-400)]">
                        {fill(
                            visible.length === 1
                                ? t.plates_one
                                : t.plates_other,
                            { count: visible.length },
                        )}
                    </p>
                </header>

                {visible.length === 0 ? (
                    <p className="font-display text-title-1 py-24 text-center text-[var(--ink-400)]">
                        {t.empty}
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
                                    table={tableLabel(ticket)}
                                    guest={ticket.guest ?? t.guest}
                                    status={ticket.status as OrderStatus}
                                    items={ticket.lines.map((line) => ({
                                        qty: `${line.qty}×`,
                                        name: line.name,
                                        note: line.note ?? undefined,
                                    }))}
                                    paid={false}
                                    total=""
                                    advanceLabel={t.collected}
                                    advanceAriaLabel={fill(t.collected_aria, {
                                        code: ticket.code,
                                        table: tableLabel(ticket),
                                    })}
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
