import { Head, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Check,
    CreditCard,
    Mail,
    Printer,
    Split,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { PriceSummary } from '@/components/qresto/price-summary';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Skeleton } from '@/components/qresto/skeleton';
import { StatusPill } from '@/components/qresto/status-pill';
import { TableTile, tableStateLabels } from '@/components/qresto/table-tile';
import { showToast } from '@/components/qresto/toast';
import { Tooltip } from '@/components/qresto/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import { useLiveData } from '@/hooks/useLiveData';
import type { OrderStatus, TableState } from '@/types';

type PaymentMethod = 'cash' | 'pos';

type FloorTable = {
    id: number;
    number: string | number;
    state: TableState;
    seats: number;
    party_size: number;
    seated_at: string | null;
    open_bill_minor: number;
    open_order_count: number;
    pending_requests: number;
    has_bill_request: boolean;
    currency: string;
    waiter?: { id: number; name: string } | null;
};

type BillLine = {
    id: number;
    name: string;
    qty: number;
    unit_price_minor: number;
    line_total_minor: number;
    note: string | null;
};

type BillPayment = {
    id: number;
    method: string;
    status: string;
    amount_minor: number;
    taken_by?: number | null;
    paid_at?: string | null;
};

type BillOrder = {
    id: number;
    status: OrderStatus;
    currency: string;
    is_paid: boolean;
    lines: BillLine[];
    payments: BillPayment[];
    total_minor?: number;
    paid_minor?: number;
    balance_minor: number;
};

type ServiceRequest = {
    id: number;
    kind: string;
    status: string;
    requested_at: string | null;
};

type TableDetail = {
    table: {
        id: number;
        number: string | number;
        state: TableState;
        seats: number;
        party_size: number;
        seated_at: string | null;
    };
    orders: BillOrder[];
    open_bill_minor: number;
    currency: string;
    service_requests: ServiceRequest[];
};

type Props = {
    tables: FloorTable[];
};

type Busy = 'free' | 'seat' | `pay-${number}` | `ack-${number}` | null;

type StateFilter = 'all' | TableState;

const STATES: TableState[] = ['free', 'seated', 'ordered', 'bill'];

// Legend dots use the occupancy tokens' foreground colours.
const STATE_DOT: Record<TableState, string> = {
    free: 'var(--table-free-fg)',
    seated: 'var(--table-seated-fg)',
    ordered: 'var(--table-ordered-fg)',
    bill: 'var(--table-bill-fg)',
};

const REQUEST_LABELS: Record<string, string> = {
    water: 'Water',
    bread: 'Bread',
    bill: 'Bill',
    waiter: 'Waiter',
};

function money(minor: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currency || 'USD',
        }).format(minor / 100);
    } catch {
        return `${(minor / 100).toFixed(2)} ${currency}`;
    }
}

function amountInput(minor: number): string {
    return (minor / 100).toFixed(2);
}

function parseAmountMinor(value: string): number | null {
    const normalized = value.trim();

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');
    const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));

    if (!Number.isSafeInteger(minor) || minor <= 0) {
        return null;
    }

    return minor;
}

function minutesSince(timestamp: string | null, now: number): number | null {
    if (!timestamp || now === 0) {
        return null;
    }

    const value = Date.parse(timestamp);

    return Number.isFinite(value)
        ? Math.max(0, Math.floor((now - value) / 60000))
        : null;
}

function tableNumber(number: string | number): string {
    const raw = String(number);

    return /^\d$/.test(raw) ? raw.padStart(2, '0') : raw;
}

// "Omar Faruk" → "Omar F."
function shortName(name: string): string {
    const parts = name.trim().split(/\s+/);

    return parts.length < 2
        ? name
        : `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
}

function firstError(errors: Record<string, string>, fallback: string): string {
    return Object.values(errors)[0] ?? fallback;
}

function paidMinorOf(order: BillOrder): number {
    return (
        order.paid_minor ??
        order.payments
            .filter((payment) => payment.status === 'paid')
            .reduce((total, payment) => total + payment.amount_minor, 0)
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-3 border-t border-[var(--border-subtle)] pt-4">
            <h3 className="text-label font-semibold text-[var(--text-secondary)]">
                {title}
            </h3>
            {children}
        </section>
    );
}

function EmailReceiptForm({
    tableId,
    orderId,
}: {
    tableId: number;
    orderId: number;
}) {
    const [email, setEmail] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const send = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (sending || !email.trim()) {
            return;
        }

        setSending(true);
        setError(null);

        router.post(
            `/app/tables/${tableId}/orders/${orderId}/receipt/email`,
            { email: email.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setEmail('');
                    showToast({
                        type: 'success',
                        title: 'Receipt emailed',
                        description: 'It is on its way to the guest.',
                    });
                },
                onError: (errors) =>
                    setError(
                        errors.email ??
                            firstError(errors, 'Unable to email receipt.'),
                    ),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <form onSubmit={send} className="flex flex-col gap-1.5">
            <Label htmlFor={`receipt-email-${orderId}`}>Email receipt</Label>

            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    id={`receipt-email-${orderId}`}
                    type="email"
                    autoComplete="email"
                    required
                    maxLength={254}
                    value={email}
                    placeholder="guest@example.com"
                    disabled={sending}
                    aria-invalid={error ? true : undefined}
                    onChange={(event) => {
                        setEmail(event.target.value);
                        setError(null);
                    }}
                />

                <Button
                    type="submit"
                    variant="outline"
                    loading={sending}
                    disabled={!email.trim()}
                >
                    <Mail className="size-4" aria-hidden />
                    Send
                </Button>
            </div>

            {error && (
                <p
                    role="alert"
                    className="text-caption text-[var(--berry-500)]"
                >
                    {error}
                </p>
            )}
        </form>
    );
}

function OrderBill({
    order,
    tableId,
    busy,
    canPay,
    onPayment,
}: {
    order: BillOrder;
    tableId: number;
    busy: Busy;
    canPay: boolean;
    onPayment: (
        order: BillOrder,
        method: PaymentMethod,
        amountMinor: number,
        splitWays: number,
    ) => void;
}) {
    const [splitOpen, setSplitOpen] = useState(false);
    const [splitWays, setSplitWays] = useState(1);
    const [amount, setAmount] = useState(() =>
        amountInput(order.balance_minor),
    );

    const paidMinor = paidMinorOf(order);
    const totalMinor = order.total_minor ?? paidMinor + order.balance_minor;
    const linesMinor = order.lines.reduce(
        (sum, line) => sum + line.line_total_minor,
        0,
    );
    const serviceMinor = Math.max(0, totalMinor - linesMinor);

    // Keep the default amount in step with the live balance.
    const lastBalance = useRef(order.balance_minor);

    useEffect(() => {
        if (lastBalance.current !== order.balance_minor) {
            lastBalance.current = order.balance_minor;
            setAmount(
                amountInput(
                    splitWays > 1
                        ? Math.min(
                              order.balance_minor,
                              Math.ceil(totalMinor / splitWays),
                          )
                        : order.balance_minor,
                ),
            );
        }
    }, [order.balance_minor, splitWays, totalMinor]);

    const pendingOnline = order.payments.find(
        (payment) =>
            payment.status === 'pending' &&
            !['cash', 'pos'].includes(payment.method),
    );

    const enteredMinor = parseAmountMinor(amount);
    const validAmount =
        enteredMinor !== null && enteredMinor <= order.balance_minor;
    const served = order.status === 'served';
    const paying = busy === `pay-${order.id}`;
    const disabled =
        !canPay ||
        !served ||
        !validAmount ||
        order.balance_minor <= 0 ||
        pendingOnline !== undefined ||
        busy !== null;

    const pay = (method: PaymentMethod) => {
        if (enteredMinor !== null) {
            onPayment(order, method, enteredMinor, splitWays);
        }
    };

    return (
        <div className="flex flex-col gap-4 rounded-[var(--radius-lg)] border border-[var(--border-subtle)] p-4">
            <div className="flex items-center justify-between gap-3">
                <Numeric className="text-title-3 font-medium">
                    #{order.id}
                </Numeric>
                <StatusPill status={order.status} />
            </div>

            <ul className="flex flex-col gap-2">
                {order.lines.map((line) => (
                    <li key={line.id} className="text-body flex gap-3">
                        <Numeric className="text-[var(--text-tertiary)]">
                            {line.qty}×
                        </Numeric>

                        <div className="min-w-0 flex-1">
                            <span className="break-words">{line.name}</span>

                            {line.note && (
                                <span className="text-caption mt-1 block w-fit break-words rounded-[var(--radius-sm)] bg-[var(--saffron-50)] px-2 py-0.5 text-[var(--saffron-700)]">
                                    {line.note}
                                </span>
                            )}
                        </div>

                        <Numeric>
                            {money(line.line_total_minor, order.currency)}
                        </Numeric>
                    </li>
                ))}
            </ul>

            <PriceSummary
                subtotal={money(linesMinor, order.currency)}
                service={
                    serviceMinor > 0
                        ? money(serviceMinor, order.currency)
                        : undefined
                }
                total={money(totalMinor, order.currency)}
                afterTotal={[
                    ...(paidMinor > 0
                        ? [
                              {
                                  label: 'Paid',
                                  value: money(paidMinor, order.currency),
                                  tone: 'positive' as const,
                              },
                          ]
                        : []),
                    {
                        label: 'Due',
                        value: money(order.balance_minor, order.currency),
                        tone: 'warning' as const,
                    },
                ]}
            />

            {!served && (
                <p className="text-caption rounded-[var(--radius-md)] bg-[var(--warning-bg)] px-3 py-2 text-[var(--warning-fg)]">
                    Payment opens once the order is served.
                </p>
            )}

            {pendingOnline && (
                <p className="text-caption rounded-[var(--radius-md)] bg-[var(--warning-bg)] px-3 py-2 text-[var(--warning-fg)]">
                    An online payment is still pending on this order. Resolve it
                    before collecting at the table.
                </p>
            )}

            {served && order.balance_minor > 0 && canPay && (
                <div className="flex flex-col gap-3">
                    <div className="flex flex-col gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor={`amount-${order.id}`}>
                                Amount to collect
                            </Label>

                            <button
                                type="button"
                                className="text-label font-semibold text-[var(--text-link)] hover:underline"
                                onClick={() => {
                                    setSplitWays(1);
                                    setAmount(amountInput(order.balance_minor));
                                }}
                            >
                                Whole balance
                            </button>
                        </div>

                        <div className="flex items-center gap-2">
                            <Input
                                id={`amount-${order.id}`}
                                inputMode="decimal"
                                value={amount}
                                dir="ltr"
                                className="font-mono"
                                disabled={busy !== null}
                                aria-invalid={
                                    !validAmount && amount.trim() !== ''
                                        ? true
                                        : undefined
                                }
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                            />
                            <span className="text-label font-mono text-[var(--text-tertiary)]">
                                {order.currency}
                            </span>
                        </div>

                        {!validAmount && amount.trim() !== '' && (
                            <p className="text-caption text-[var(--berry-500)]">
                                Enter more than zero and no more than the
                                balance due.
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <Button
                            type="button"
                            variant="primary"
                            loading={paying}
                            disabled={disabled}
                            onClick={() => pay('pos')}
                        >
                            <CreditCard className="size-4" aria-hidden />
                            Take card at table
                        </Button>

                        <Button
                            type="button"
                            variant="secondary"
                            loading={paying}
                            disabled={disabled}
                            onClick={() => pay('cash')}
                        >
                            <Banknote className="size-4" aria-hidden />
                            Mark cash received
                        </Button>
                    </div>
                </div>
            )}

            <Button
                type="button"
                variant="outline"
                aria-expanded={splitOpen}
                onClick={() => setSplitOpen((open) => !open)}
            >
                <Split className="size-4" aria-hidden />
                Split · print · email
            </Button>

            {splitOpen && (
                <div className="flex flex-col gap-4 rounded-[var(--radius-md)] bg-[var(--surface-sunken)] p-3">
                    {served && order.balance_minor > 0 && canPay && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor={`split-${order.id}`}>
                                Split between
                            </Label>

                            <div
                                id={`split-${order.id}`}
                                role="group"
                                className="flex flex-wrap gap-2"
                            >
                                {[1, 2, 3, 4, 5, 6].map((ways) => (
                                    <ChipFilter
                                        key={ways}
                                        type="segment"
                                        active={splitWays === ways}
                                        onClick={() => {
                                            setSplitWays(ways);
                                            setAmount(
                                                amountInput(
                                                    Math.min(
                                                        order.balance_minor,
                                                        Math.ceil(
                                                            totalMinor / ways,
                                                        ),
                                                    ),
                                                ),
                                            );
                                        }}
                                    >
                                        {ways === 1
                                            ? 'One bill'
                                            : `${ways} ways`}
                                    </ChipFilter>
                                ))}
                            </div>

                            {splitWays > 1 && (
                                <p className="text-caption text-[var(--text-secondary)]">
                                    Each share is{' '}
                                    <Numeric>
                                        {money(
                                            Math.ceil(totalMinor / splitWays),
                                            order.currency,
                                        )}
                                    </Numeric>
                                    . Collect one share at a time above.
                                </p>
                            )}
                        </div>
                    )}

                    <Button asChild variant="outline">
                        <a
                            href={`/app/tables/${tableId}/orders/${order.id}/receipt`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Printer className="size-4" aria-hidden />
                            Print receipt
                        </a>
                    </Button>

                    <EmailReceiptForm tableId={tableId} orderId={order.id} />
                </div>
            )}
        </div>
    );
}

export function TableCheckout({
    detail,
    loading,
    busy,
    canPay,
    canManageTable = true,
    partySize,
    onPartySizeChange,
    onSeat,
    onFree,
    onAcknowledge,
    onPayment,
}: {
    detail: TableDetail | null;
    loading: boolean;
    busy: Busy;
    canPay: boolean;
    /** Seat, free and acknowledge. The cashier (#34) only settles. */
    canManageTable?: boolean;
    partySize: string;
    onPartySizeChange: (value: string) => void;
    onSeat: () => void;
    onFree: () => void;
    onAcknowledge: (requestId: number) => void;
    onPayment: (
        order: BillOrder,
        method: PaymentMethod,
        amountMinor: number,
        splitWays: number,
    ) => void;
}) {
    if (!detail) {
        return loading ? (
            <div className="flex flex-col gap-3" aria-busy="true">
                <Skeleton width="33%" height="24px" />
                <Skeleton height="96px" />
                <Skeleton height="44px" />
            </div>
        ) : null;
    }

    const unpaid = detail.orders.length > 0;
    const freeDisabled =
        busy !== null || unpaid || detail.table.state === 'free';

    const freeButton = (
        <Button
            type="button"
            variant="outline"
            fullWidth
            loading={busy === 'free'}
            disabled={freeDisabled}
            onClick={onFree}
        >
            Free the table
        </Button>
    );

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span className="text-body flex items-center gap-1.5 text-[var(--text-secondary)]">
                    <Users className="size-4" aria-hidden />
                    <Numeric>
                        {detail.table.party_size} / {detail.table.seats}
                    </Numeric>{' '}
                    guests
                </span>

                <span className="text-body text-[var(--text-secondary)]">
                    Due{' '}
                    <Numeric className="font-semibold text-[var(--text-primary)]">
                        {money(detail.open_bill_minor, detail.currency)}
                    </Numeric>
                </span>
            </div>

            {canManageTable && detail.table.state === 'free' && (
                <Section title="Seat guests">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="floor-party-size">
                            Party size (max {detail.table.seats})
                        </Label>
                        <Input
                            id="floor-party-size"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={detail.table.seats}
                            step={1}
                            value={partySize}
                            disabled={busy !== null}
                            onChange={(event) =>
                                onPartySizeChange(event.target.value)
                            }
                        />
                    </div>

                    <Button
                        type="button"
                        variant="primary"
                        fullWidth
                        loading={busy === 'seat'}
                        disabled={busy !== null || detail.table.seats < 1}
                        onClick={onSeat}
                    >
                        <Users className="size-4" aria-hidden />
                        Seat guests
                    </Button>
                </Section>
            )}

            {canManageTable && detail.service_requests.length > 0 && (
                <Section title="Waiting on you">
                    {detail.service_requests.map((request) => (
                        <div
                            key={request.id}
                            className="flex items-center justify-between gap-3 rounded-[var(--radius-md)] border border-[var(--clay-200)] bg-[var(--surface-brand-soft)] p-3"
                        >
                            <span className="text-body font-semibold text-[var(--text-brand)]">
                                {REQUEST_LABELS[request.kind] ?? request.kind}
                            </span>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                loading={busy === `ack-${request.id}`}
                                disabled={busy !== null}
                                onClick={() => onAcknowledge(request.id)}
                            >
                                <Check className="size-4" aria-hidden />
                                Acknowledge
                            </Button>
                        </div>
                    ))}
                </Section>
            )}

            <Section title="Open bill">
                {unpaid ? (
                    detail.orders.map((order) => (
                        <OrderBill
                            key={order.id}
                            order={order}
                            tableId={detail.table.id}
                            busy={busy}
                            canPay={canPay}
                            onPayment={onPayment}
                        />
                    ))
                ) : (
                    <p className="text-body text-[var(--text-tertiary)]">
                        Nothing left to pay at this table.
                    </p>
                )}
            </Section>

            {canManageTable && (
                <div className="border-t border-[var(--border-subtle)] pt-4">
                    {unpaid ? (
                        <Tooltip content="Settle every order before freeing the table.">
                            {/* A disabled button swallows hover, so wrap it. */}
                            <span tabIndex={0} className="block">
                                {freeButton}
                            </span>
                        </Tooltip>
                    ) : (
                        freeButton
                    )}

                    {unpaid && (
                        <p className="text-caption mt-2 text-center text-[var(--text-tertiary)]">
                            A table cannot be freed while a bill is unpaid.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * The floor's checkout, as a hook: load a table's bill, seat, free,
 * acknowledge a request and take payment, all through the floor's own
 * endpoints. The floor map uses it, and so does the cashier view (#34),
 * so there is one checkout and one settle action.
 */
export function useTableCheckout({
    canPay,
    canManageTable = true,
    interval,
    isPaused,
    onChanged,
}: {
    canPay: boolean;
    canManageTable?: boolean;
    interval: number;
    isPaused: boolean;
    onChanged: () => void;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [detail, setDetail] = useState<TableDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState<Busy>(null);
    const [partySize, setPartySize] = useState('1');

    const selectedIdRef = useRef<number | null>(null);
    const requestRef = useRef(0);

    const loadDetail = useCallback(async (id: number, showLoader = true) => {
        const requestId = ++requestRef.current;

        if (showLoader) {
            setLoading(true);
        }

        try {
            const response = await fetch(`/app/tables/${id}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Unable to load table (${response.status}).`);
            }

            const data: TableDetail = await response.json();

            if (
                selectedIdRef.current === id &&
                requestRef.current === requestId
            ) {
                setDetail(data);
            }
        } catch (cause) {
            if (showLoader && selectedIdRef.current === id) {
                showToast({
                    type: 'error',
                    title: 'Could not open the table',
                    description:
                        cause instanceof Error ? cause.message : undefined,
                });
            }
        } finally {
            if (showLoader && requestRef.current === requestId) {
                setLoading(false);
            }
        }
    }, []);

    const selectTable = (id: number) => {
        selectedIdRef.current = id;
        setSelectedId(id);
        setDetail(null);
        setPartySize('1');
        void loadDetail(id);
    };

    const closeDetail = () => {
        requestRef.current += 1;
        selectedIdRef.current = null;
        setSelectedId(null);
        setDetail(null);
        setLoading(false);
    };

    // The open bill follows the floor's polling rhythm.
    useEffect(() => {
        if (selectedId === null || isPaused) {
            return;
        }

        const timer = window.setInterval(
            () => void loadDetail(selectedId, false),
            interval,
        );

        return () => window.clearInterval(timer);
    }, [selectedId, isPaused, interval, loadDetail]);

    const afterAction = () => {
        onChanged();

        if (selectedIdRef.current !== null) {
            void loadDetail(selectedIdRef.current, false);
        }
    };

    const post = (
        url: string,
        data: Record<string, string | number>,
        next: Exclude<Busy, null>,
        success: string,
    ) => {
        setBusy(next);

        router.post(url, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showToast({ type: 'success', title: success });
                afterAction();
            },
            onError: (errors) =>
                showToast({
                    type: 'error',
                    title: 'That did not go through',
                    description: firstError(
                        errors,
                        'Unable to complete action.',
                    ),
                }),
            onFinish: () => setBusy(null),
        });
    };

    const seat = () => {
        if (selectedId === null || !detail || busy !== null) {
            return;
        }

        const count = Number(partySize);

        if (
            !Number.isInteger(count) ||
            count < 1 ||
            count > detail.table.seats
        ) {
            showToast({
                type: 'error',
                title: `Enter a party size between 1 and ${detail.table.seats}`,
            });

            return;
        }

        post(
            `/app/tables/${selectedId}/seat`,
            { party_size: count },
            'seat',
            `Table ${tableNumber(detail.table.number)} seated`,
        );
    };

    const free = () => {
        if (selectedId === null || !detail || busy !== null) {
            return;
        }

        // The server refuses while anything is unpaid; its reason is toasted.
        post(
            `/app/tables/${selectedId}/free`,
            {},
            'free',
            `Table ${tableNumber(detail.table.number)} is free`,
        );
    };

    const acknowledge = (requestId: number) => {
        if (busy !== null) {
            return;
        }

        setBusy(`ack-${requestId}`);

        router.patch(
            `/service-requests/${requestId}/acknowledge`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: afterAction,
                onError: (errors) =>
                    showToast({
                        type: 'error',
                        title: 'Could not acknowledge',
                        description: firstError(errors, 'Please try again.'),
                    }),
                onFinish: () => setBusy(null),
            },
        );
    };

    const pay = (
        order: BillOrder,
        method: PaymentMethod,
        amountMinor: number,
        splitWays: number,
    ) => {
        if (selectedId === null || busy !== null) {
            return;
        }

        post(
            `/app/tables/${selectedId}/payments`,
            {
                order_id: order.id,
                method,
                amount_minor: amountMinor,
                split_ways: splitWays,
            },
            `pay-${order.id}`,
            `${money(amountMinor, order.currency)} ${
                method === 'cash' ? 'cash received' : 'taken by card'
            }`,
        );
    };

    const checkout = (
        <TableCheckout
            detail={detail}
            loading={loading}
            busy={busy}
            canPay={canPay}
            canManageTable={canManageTable}
            partySize={partySize}
            onPartySizeChange={setPartySize}
            onSeat={seat}
            onFree={free}
            onAcknowledge={acknowledge}
            onPayment={pay}
        />
    );

    return {
        selectedId,
        select: selectTable,
        close: closeDetail,
        checkout,
        detail,
    };
}

export default function Floor({ tables = [] }: Props) {
    const { qresto, capabilities } = usePage().props as unknown as {
        qresto?: { live?: { floor_interval?: number } };
        capabilities?: string[];
    };
    const interval = qresto?.live?.floor_interval ?? 8000;
    const canPay = capabilities?.includes('take_payment') ?? false;
    const seesWholeFloor = capabilities?.includes('see_all_orders') ?? false;

    const isMobile = useIsMobile();
    const { isPaused } = useLiveData(interval, { only: ['tables'] });

    const [filter, setFilter] = useState<StateFilter>('all');
    const [now, setNow] = useState(0);

    const {
        selectedId,
        select: selectTable,
        close: closeDetail,
        checkout,
    } = useTableCheckout({
        canPay,
        interval,
        isPaused,
        onChanged: () => router.reload({ only: ['tables'] }),
    });
    const counts = useMemo(
        () =>
            Object.fromEntries(
                STATES.map((state) => [
                    state,
                    tables.filter((table) => table.state === state).length,
                ]),
            ) as Record<TableState, number>,
        [tables],
    );

    const visibleTables =
        filter === 'all'
            ? tables
            : tables.filter((table) => table.state === filter);

    const selectedTable = tables.find((table) => table.id === selectedId);

    // Elapsed times on the tiles age between polls.
    useEffect(() => {
        const update = () => setNow(Date.now());
        const first = window.setTimeout(update, 0);
        const tick = window.setInterval(update, 30_000);

        return () => {
            window.clearTimeout(first);
            window.clearInterval(tick);
        };
    }, []);

    const selectedTitle = selectedTable
        ? `Table ${tableNumber(selectedTable.number)}`
        : 'Table';

    return (
        <>
            <Head title="Floor" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div
                        className="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filter tables by state"
                    >
                        <ChipFilter
                            type="segment"
                            active={filter === 'all'}
                            onClick={() => setFilter('all')}
                        >
                            {`All (${tables.length})`}
                        </ChipFilter>

                        {STATES.map((state) => (
                            <ChipFilter
                                key={state}
                                type="segment"
                                active={filter === state}
                                onClick={() => setFilter(state)}
                            >
                                <span
                                    aria-hidden="true"
                                    className="me-1.5 size-2 rounded-full"
                                    style={{
                                        backgroundColor: STATE_DOT[state],
                                    }}
                                />
                                {`${tableStateLabels[state]} (${counts[state]})`}
                            </ChipFilter>
                        ))}
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-caption text-[var(--text-tertiary)]">
                            Tap a table to open its bill
                        </span>
                    </div>
                </div>

                {tables.length === 0 ? (
                    <Card>
                        {/* A waiter sees only tonight's own tables. */}
                        <EmptyState
                            headline={
                                seesWholeFloor
                                    ? 'No tables yet'
                                    : 'No tables assigned to you tonight'
                            }
                            body={
                                seesWholeFloor
                                    ? 'Tables appear here once they are set up for this restaurant.'
                                    : 'Your manager assigns tables when they add you to the shift.'
                            }
                        />
                    </Card>
                ) : (
                    <section
                        aria-label="Restaurant tables"
                        className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4"
                    >
                        {visibleTables.map((table) => {
                            const minutes = minutesSince(table.seated_at, now);
                            const meta = [
                                table.open_bill_minor > 0
                                    ? money(
                                          table.open_bill_minor,
                                          table.currency,
                                      )
                                    : null,
                                minutes !== null ? `${minutes} min` : null,
                            ]
                                .filter(Boolean)
                                .join(' · ');

                            return (
                                <TableTile
                                    key={table.id}
                                    table={tableNumber(table.number)}
                                    state={table.state}
                                    seats={`${table.seats} seats`}
                                    assignee={
                                        table.waiter
                                            ? shortName(table.waiter.name)
                                            : undefined
                                    }
                                    meta={
                                        meta || table.pending_requests > 0 ? (
                                            <span className="flex flex-col gap-0.5">
                                                {meta && (
                                                    <Numeric>{meta}</Numeric>
                                                )}
                                                {table.pending_requests > 0 && (
                                                    <span className="font-semibold">
                                                        {table.pending_requests}{' '}
                                                        {table.pending_requests ===
                                                        1
                                                            ? 'request'
                                                            : 'requests'}
                                                    </span>
                                                )}
                                            </span>
                                        ) : undefined
                                    }
                                    pulse={
                                        table.state === 'bill' ||
                                        table.has_bill_request
                                    }
                                    selected={selectedId === table.id}
                                    ariaLabel={`Open table ${tableNumber(table.number)}, ${tableStateLabels[table.state]}${
                                        table.waiter
                                            ? `, ${table.waiter.name}`
                                            : ''
                                    }`}
                                    onClick={() => selectTable(table.id)}
                                />
                            );
                        })}
                    </section>
                )}

                {/* Desktop and tablet: the checkout card sits below the grid. */}
                {selectedId !== null && !isMobile && (
                    <Card aria-label={`${selectedTitle} checkout`}>
                        <div className="mb-4 flex items-center gap-3">
                            <h2 className="font-display text-title-2 flex-1 font-semibold">
                                <Numeric>{selectedTitle}</Numeric>
                            </h2>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={closeDetail}
                            >
                                Close
                            </Button>
                        </div>

                        {checkout}
                    </Card>
                )}
            </div>

            {/* Phone: a bottom sheet so the thumb reaches the actions. */}
            {selectedId !== null && isMobile && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            closeDetail();
                        }
                    }}
                    title={selectedTitle}
                >
                    {checkout}
                </ResponsiveOverlay>
            )}
        </>
    );
}
