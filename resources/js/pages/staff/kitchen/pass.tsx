import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

import {
    KitchenScreen,
    KitchenScreenBody,
    KitchenScreenEmpty,
    KitchenSignOut,
} from '@/components/qresto/kitchen-screen';
import { useUpdatedAgo } from '@/components/qresto/LiveIndicator';
import { OfflineBanner } from '@/components/qresto/offline-banner';
import { OrderTicket } from '@/components/qresto/order-ticket';
import { showToast } from '@/components/qresto/toast';
import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';
import { formatMinutes } from '@/lib/elapsed';
import { fill, useI18n } from '@/lib/i18n';
import type { OrderStatus } from '@/types';

type Line = {
    qty: number;
    name: string;
    note: string | null;
    station: string | null;
};

type Ticket = {
    id: number;
    code: string;
    status: 'pending' | 'preparing';
    table: string | null;
    guest: string | null;
    fired_at: string | null;
    elapsed_minutes: number;
    lines: Line[];
};

type Props = {
    station: string | null;
    stations: string[];
    tickets: Ticket[];
    sla: { warn_minutes: number; late_minutes: number };
    pollIntervalMs: number;
    heartbeatIntervalMs: number;
    now: number;
    signedInAs?: string;
};

type PassCopy = ReturnType<typeof useI18n>['staff']['pass'];

/** The next state, read out to screen readers; the button says "Bump". */
function bumpTo(status: Ticket['status'], t: PassCopy): string {
    return status === 'pending' ? t.bump_to_pending : t.bump_to_preparing;
}

/** "4 tickets · 11 items · longest 14 min", over what is on screen. */
export function aggregates(tickets: Ticket[]): {
    tickets: number;
    items: number;
    longest: number;
} {
    return {
        tickets: tickets.length,
        items: tickets.reduce(
            (sum, ticket) =>
                sum + ticket.lines.reduce((n, line) => n + line.qty, 0),
            0,
        ),
        longest: tickets.reduce(
            (max, ticket) => Math.max(max, ticket.elapsed_minutes),
            0,
        ),
    };
}

function plural(count: number, one: string, other: string): string {
    return fill(count === 1 ? one : other, { count });
}

function readCookie(name: string): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

/**
 * The kitchen pass (#33): dark, large type, touch-bumped, viewed from two
 * metres behind a hot line. Nothing is smaller than 15px and nothing needs
 * hover. A full-screen wall display (#64).
 */
export default function Pass({
    station,
    stations,
    tickets,
    sla,
    pollIntervalMs,
    heartbeatIntervalMs,
    signedInAs,
}: Props) {
    const {
        staff: { pass: t },
    } = useI18n();
    // Bumped tickets leave at once; a refused bump brings them back.
    const [bumped, setBumped] = useState<Set<number>>(new Set());
    const visible = tickets.filter((ticket) => !bumped.has(ticket.id));
    const busy = useRef(false);

    const {
        staff: { live: liveCopy },
    } = useI18n();
    const { isDisconnected, connection } = useLiveData(pollIntervalMs, {
        only: ['tickets', 'now'],
    });
    const updatedAgo = useUpdatedAgo(connection.lastSuccessAt);

    useNewItemAlert(tickets, (ticket) => ticket.id, {
        title: t.new_ticket,
    });

    // Tell system health (#30) the pass screen is up while it is open.
    useEffect(() => {
        const beat = () =>
            void fetch('/app/kds/heartbeat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
                },
            }).catch(() => undefined);

        beat();
        const timer = window.setInterval(beat, heartbeatIntervalMs);

        return () => window.clearInterval(timer);
    }, [heartbeatIntervalMs]);

    const bump = useCallback(
        (ticket: Ticket) => {
            if (busy.current) {
                return;
            }

            busy.current = true;
            setBumped((current) => new Set(current).add(ticket.id));

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
                            title: fill(
                                ticket.status === 'pending'
                                    ? t.bumped_cooking
                                    : t.bumped_ready,
                                { code: ticket.code },
                            ),
                        }),
                    onError: (errors) => {
                        setBumped((current) => {
                            const next = new Set(current);
                            next.delete(ticket.id);

                            return next;
                        });
                        showToast({
                            type: 'error',
                            title: t.not_bumped,
                            description:
                                errors.order ?? Object.values(errors)[0],
                        });
                    },
                    onFinish: () => {
                        busy.current = false;
                    },
                },
            );
        },
        [t],
    );

    // Keypad at the pass: 1–9 bumps the nth ticket on screen.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }

            const n = Number.parseInt(event.key, 10);

            if (n >= 1 && n <= 9 && visible[n - 1]) {
                event.preventDefault();
                bump(visible[n - 1]);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [visible, bump]);

    const totals = aggregates(visible);

    const level = (minutes: number) =>
        minutes >= sla.late_minutes
            ? 'late'
            : minutes >= sla.warn_minutes
              ? 'warn'
              : 'normal';

    return (
        <>
            <Head title={t.head} />

            {/* A wall display with no staff layout (#33): the shell is the
                page, dark to the edges and a full viewport tall (#64). */}
            <KitchenScreen
                surface="pass"
                label={t.head}
                connection={isDisconnected ? 'offline' : 'live'}
            >
                {/* A frozen board looks exactly like a quiet one; say so from
                    across the kitchen (#58). */}
                {isDisconnected && (
                    <OfflineBanner
                        tone="pass"
                        title={liveCopy.pass_offline}
                        detail={`${updatedAgo} · ${liveCopy.reconnecting}`}
                    />
                )}

                {/* Header: live counts, the longest wait, the bump hint. */}
                <header className="flex flex-wrap items-center gap-x-4 gap-y-3">
                    <h2 className="font-display text-title-1 font-semibold">
                        {isDisconnected ? t.heading_offline : t.heading_live}
                    </h2>
                    <p
                        data-testid="pass-totals"
                        className="text-body font-mono text-[var(--ink-400)]"
                    >
                        {[
                            plural(
                                totals.tickets,
                                t.tickets_one,
                                t.tickets_other,
                            ),
                            plural(totals.items, t.items_one, t.items_other),
                            fill(t.longest, {
                                time: formatMinutes(totals.longest),
                            }),
                        ].join(' · ')}
                    </p>

                    <span className="flex-1" />

                    <p className="text-body rounded-[var(--radius-pill)] border border-[var(--alpha-paper-08)] px-4 py-2 font-semibold">
                        {t.bump_hint}
                    </p>

                    <KitchenSignOut signedInAs={signedInAs} />
                </header>

                {/* Station filter: one station's work only. Touch-sized. */}
                <nav
                    aria-label={t.stations}
                    className="mt-4 flex flex-wrap gap-2"
                >
                    {[null, ...stations].map((value) => {
                        const active = station === value;

                        return (
                            <button
                                key={value ?? 'all'}
                                type="button"
                                aria-pressed={active}
                                onClick={() =>
                                    router.get(
                                        '/app/pass',
                                        value ? { station: value } : {},
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                                className={`text-body min-h-12 rounded-[var(--radius-pill)] border px-5 font-semibold capitalize ${
                                    active
                                        ? 'border-[var(--ink-25)] bg-[var(--ink-25)] text-[var(--ink-950)]'
                                        : 'border-[var(--alpha-paper-08)] bg-transparent text-[var(--ink-300)]'
                                }`}
                            >
                                {value ?? t.all_stations}
                            </button>
                        );
                    })}
                </nav>

                <KitchenScreenBody className="mt-5">
                    {visible.length === 0 ? (
                        <KitchenScreenEmpty>{t.empty}</KitchenScreenEmpty>
                    ) : (
                        // Two across on a tablet, three at 1100, four on a
                        // 27-inch pass screen; the grid takes the height the
                        // header leaves, so the footer sits at the bottom.
                        <ol
                            data-pass-grid
                            className="grid flex-1 grid-cols-1 content-start items-start gap-5 min-[720px]:grid-cols-2 min-[1100px]:grid-cols-3 min-[1600px]:grid-cols-4"
                        >
                            {visible.map((ticket, index) => (
                                <li
                                    key={ticket.id}
                                    data-ticket={ticket.code}
                                    data-key={index < 9 ? index + 1 : undefined}
                                    className="animate-[qr-rise_var(--dur-base)_var(--ease-enter)] motion-reduce:animate-none"
                                >
                                    <OrderTicket
                                        variant="dark"
                                        size="pass"
                                        code={ticket.code}
                                        elapsed={formatMinutes(
                                            ticket.elapsed_minutes,
                                        )}
                                        elapsedLevel={level(
                                            ticket.elapsed_minutes,
                                        )}
                                        table={
                                            ticket.table
                                                ? fill(t.table, {
                                                      table: ticket.table,
                                                  })
                                                : t.takeaway
                                        }
                                        guest={ticket.guest ?? t.guest}
                                        status={ticket.status as OrderStatus}
                                        items={ticket.lines.map((line) => ({
                                            qty: `${line.qty}×`,
                                            name: line.name,
                                            note: line.note ?? undefined,
                                        }))}
                                        paid={false}
                                        total=""
                                        advanceLabel={t.bump}
                                        advanceAriaLabel={fill(
                                            index < 9
                                                ? t.bump_aria_key
                                                : t.bump_aria,
                                            {
                                                code: ticket.code,
                                                next: bumpTo(ticket.status, t),
                                                key: index + 1,
                                            },
                                        )}
                                        onAdvance={() => bump(ticket)}
                                    />
                                </li>
                            ))}
                        </ol>
                    )}
                </KitchenScreenBody>

                <footer className="text-body mt-6 flex flex-col gap-1 border-t border-[var(--alpha-paper-08)] pt-4 text-[var(--ink-400)]">
                    <p>{t.footer_notes}</p>
                    <p>{t.footer_runner}</p>
                    <p>{t.footer_cashier}</p>
                </footer>
            </KitchenScreen>
        </>
    );
}
