import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    Check,
    ChevronRight,
    Clock3,
    CreditCard,
    LoaderCircle,
    RefreshCw,
    Users,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
type TableState = 'free' | 'seated' | 'ordered' | 'bill';
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
    status: string;
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
type Action = {
    type: 'free' | 'seat' | 'payment' | 'acknowledge';
    id: number;
} | null;
const STATE_LABELS: Record<TableState, string> = {
    free: 'Free',
    seated: 'Seated',
    ordered: 'Ordered',
    bill: 'Bill requested',
};
const STATE_STYLES: Record<TableState, string> = {
    free: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    seated: 'border-sky-200 bg-sky-50 text-sky-900',
    ordered: 'border-amber-200 bg-amber-50 text-amber-900',
    bill: 'border-orange-300 bg-orange-50 text-orange-950',
};
const DOT_STYLES: Record<TableState, string> = {
    free: 'bg-emerald-500',
    seated: 'bg-sky-500',
    ordered: 'bg-amber-500',
    bill: 'bg-orange-600',
};
function money(minor: number, currency = 'TRY'): string {
    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency,
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
function seatedDuration(seatedAt: string | null, now: number): string | null {
    if (!seatedAt || now === 0) {
        return null;
    }

    const timestamp = Date.parse(seatedAt);

    if (!Number.isFinite(timestamp)) {
        return null;
    }

    const minutes = Math.max(0, Math.floor((now - timestamp) / 60000));
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    return hours > 0 ? `${hours}h ${remaining}m` : `${minutes}m`;
}
function readableKind(kind: string): string {
    return kind
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
function FloorTile({
    table,
    now,
    onClick,
}: {
    table: FloorTable;
    now: number;
    onClick: () => void;
}) {
    const duration = seatedDuration(table.seated_at, now);
    const billRequested = table.state === 'bill' || table.has_bill_request;

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={`Open table ${table.number}`}
            className={[
                'relative flex min-h-44 flex-col rounded-2xl border p-4',
                'text-left shadow-sm transition',
                'hover:-translate-y-0.5 hover:shadow-md',
                'focus-visible:outline-2 focus-visible:outline-offset-2',
                'focus-visible:outline-slate-900',
                STATE_STYLES[table.state],
                billRequested ? 'ring-2 ring-orange-300' : '',
            ].join(' ')}
        >
            <div className="flex w-full items-start justify-between gap-2">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide opacity-70">
                        Table
                    </p>
                    <p className="mt-1 text-3xl font-bold">{table.number}</p>
                </div>
                <ChevronRight size={18} className="opacity-60" />
            </div>
            <div className="mt-3 flex items-center gap-2 text-xs font-semibold">
                <span
                    className={[
                        'h-2.5 w-2.5 rounded-full',
                        DOT_STYLES[table.state],
                        billRequested ? 'animate-pulse' : '',
                    ].join(' ')}
                />
                {STATE_LABELS[table.state]}
            </div>
            <div className="mt-auto space-y-1 pt-3 text-xs">
                <div className="flex items-center gap-1.5">
                    <Users size={13} />
                    <span>
                        {table.party_size || 0} / {table.seats} guests
                    </span>
                </div>
                {duration && (
                    <div className="flex items-center gap-1.5">
                        <Clock3 size={13} />
                        <span>{duration} seated</span>
                    </div>
                )}
                {table.open_bill_minor > 0 && (
                    <p className="pt-1 text-sm font-bold">
                        {money(table.open_bill_minor, table.currency)}
                    </p>
                )}
                {table.pending_requests > 0 && (
                    <p className="font-semibold">
                        {table.pending_requests} pending request
                        {table.pending_requests === 1 ? '' : 's'}
                    </p>
                )}
            </div>
        </button>
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
    const [sent, setSent] = useState(false);
    const [emailError, setEmailError] = useState<string | null>(null);

    const sendReceipt = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (sending || !email.trim()) {
            return;
        }

        setSending(true);
        setSent(false);
        setEmailError(null);

        router.post(
            `/app/tables/${tableId}/orders/${orderId}/receipt/email`,
            { email: email.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSent(true);
                },
                onError: (errors) => {
                    setEmailError(
                        errors.email ??
                            Object.values(errors)[0] ??
                            'Unable to email receipt.',
                    );
                },
                onFinish: () => {
                    setSending(false);
                },
            },
        );
    };

    return (
        <form onSubmit={sendReceipt} className="space-y-2">
            <label
                htmlFor={`receipt-email-${orderId}`}
                className="block text-sm font-medium"
            >
                Email receipt
            </label>

            <div className="flex flex-col gap-2 sm:flex-row">
                <input
                    id={`receipt-email-${orderId}`}
                    type="email"
                    autoComplete="email"
                    required
                    maxLength={254}
                    value={email}
                    onChange={(event) => {
                        setEmail(event.target.value);
                        setSent(false);
                        setEmailError(null);
                    }}
                    placeholder="guest@example.com"
                    disabled={sending}
                    className="min-h-11 min-w-0 flex-1 rounded-xl border px-3 py-2 text-sm"
                />

                <button
                    type="submit"
                    disabled={sending || !email.trim()}
                    className="bg-action-primary min-h-11 rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {sending ? 'Sending...' : 'Email Receipt'}
                </button>
            </div>

            {emailError && (
                <p role="alert" className="text-sm text-red-600">
                    {emailError}
                </p>
            )}

            {sent && (
                <p role="status" className="text-sm text-green-700">
                    Receipt email queued successfully.
                </p>
            )}
        </form>
    );
}
function PaymentSection({
    order,
    action,
    amount,
    onAmountChange,
    onPayment,
    splitWays,
    onSplitWaysChange,
}: {
    order: BillOrder;
    action: Action;
    amount: string;
    onAmountChange: (value: string) => void;
    onPayment: (method: PaymentMethod) => void;
    splitWays: number;
    onSplitWaysChange: (value: number) => void;
}) {
    const paidPayments = order.payments.filter(
        (payment) => payment.status === 'paid',
    );
    const paidMinor =
        order.paid_minor ??
        paidPayments.reduce(
            (total, payment) => total + payment.amount_minor,
            0,
        );
    const totalMinor = order.total_minor ?? paidMinor + order.balance_minor;
    const pendingPayment = order.payments.find(
        (payment) => payment.status === 'pending',
    );
    const canPay =
        order.status === 'served' && order.balance_minor > 0 && action === null;
    const enteredMinor = parseAmountMinor(amount);
    const isValidAmount =
        enteredMinor !== null && enteredMinor <= order.balance_minor;

    return (
        <section className="space-y-4 border-t border-slate-200 pt-4">
            <h4 className="text-sm font-bold text-slate-950">
                Payment & split bill
            </h4>
            <div className="grid grid-cols-3 gap-2 text-center">
                <div className="rounded-xl bg-slate-50 p-3">
                    <p className="text-xs text-slate-500">Total</p>
                    <p className="mt-1 text-sm font-bold text-slate-900">
                        {money(totalMinor, order.currency)}
                    </p>
                </div>
                <div className="rounded-xl bg-emerald-50 p-3">
                    <p className="text-xs text-emerald-700">Paid</p>
                    <p className="mt-1 text-sm font-bold text-emerald-900">
                        {money(paidMinor, order.currency)}
                    </p>
                </div>
                <div className="rounded-xl bg-amber-50 p-3">
                    <p className="text-xs text-amber-700">Remaining</p>
                    <p className="mt-1 text-sm font-bold text-amber-900">
                        {money(order.balance_minor, order.currency)}
                    </p>
                </div>
            </div>
            {paidPayments.length > 0 && (
                <div className="space-y-2">
                    <p className="text-xs font-semibold text-slate-600">
                        Payments received
                    </p>
                    {paidPayments.map((payment) => (
                        <div
                            key={payment.id}
                            className="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm"
                        >
                            <span className="flex items-center gap-2 font-medium text-emerald-900">
                                <Check size={15} />
                                {payment.method.toUpperCase()}
                            </span>
                            <span className="font-bold text-emerald-900">
                                {money(payment.amount_minor, order.currency)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
            {order.status === 'served' && order.balance_minor > 0 && (
                <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <label
                            htmlFor={`payment-amount-${order.id}`}
                            className="text-sm font-semibold text-slate-900"
                        >
                            Payment amount
                        </label>
                        <button
                            type="button"
                            disabled={action !== null}
                            onClick={() =>
                                onAmountChange(amountInput(order.balance_minor))
                            }
                            className="text-xs font-semibold text-sky-700 hover:underline disabled:opacity-50"
                        >
                            Pay remaining
                        </button>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 p-3">
                        <label
                            htmlFor={`split-ways-${order.id}`}
                            className="text-sm font-semibold text-slate-900"
                        >
                            Split between
                        </label>
                        <select
                            id={`split-ways-${order.id}`}
                            value={splitWays}
                            disabled={action !== null}
                            onChange={(event) => {
                                const ways = Number(event.target.value);
                                onSplitWaysChange(ways);
                                onAmountChange(
                                    amountInput(
                                        Math.min(
                                            order.balance_minor,
                                            Math.ceil(totalMinor / ways),
                                        ),
                                    ),
                                );
                            }}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-950"
                        >
                            {Array.from(
                                { length: 8 },
                                (_, index) => index + 1,
                            ).map((ways) => (
                                <option key={ways} value={ways}>
                                    {ways} {ways === 1 ? 'person' : 'people'}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            id={`payment-amount-${order.id}`}
                            type="text"
                            inputMode="decimal"
                            value={amount}
                            disabled={action !== null}
                            onChange={(event) =>
                                onAmountChange(event.target.value)
                            }
                            placeholder="0.00"
                            className="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-base font-semibold text-slate-950 focus-visible:outline-2 focus-visible:outline-sky-600 disabled:opacity-50"
                        />
                        <span className="text-sm font-semibold text-slate-600">
                            {order.currency}
                        </span>
                    </div>
                    {!isValidAmount && amount.trim() !== '' && (
                        <p className="text-xs text-red-700">
                            Enter an amount greater than zero and no more than
                            the remaining balance.
                        </p>
                    )}
                    {pendingPayment &&
                        !['cash', 'pos'].includes(pendingPayment.method) && (
                            <p className="text-xs text-amber-700">
                                This order has a pending online payment. Its
                                status must be resolved before collecting
                                another payment.
                            </p>
                        )}
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            disabled={
                                !canPay ||
                                !isValidAmount ||
                                (pendingPayment !== undefined &&
                                    pendingPayment.method !== 'cash')
                            }
                            onClick={() => onPayment('cash')}
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-3 py-3 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {action?.type === 'payment' &&
                            action.id === order.id ? (
                                <LoaderCircle
                                    size={17}
                                    className="animate-spin"
                                />
                            ) : (
                                <Banknote size={17} />
                            )}
                            Collect Cash
                        </button>
                        <button
                            type="button"
                            disabled={
                                !canPay ||
                                !isValidAmount ||
                                (pendingPayment !== undefined &&
                                    pendingPayment.method !== 'pos')
                            }
                            onClick={() => onPayment('pos')}
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-3 text-sm font-bold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {action?.type === 'payment' &&
                            action.id === order.id ? (
                                <LoaderCircle
                                    size={17}
                                    className="animate-spin"
                                />
                            ) : (
                                <CreditCard size={17} />
                            )}
                            Collect POS
                        </button>
                    </div>
                    <p className="text-xs text-slate-500">
                        You can collect part of the bill now and settle the
                        remaining balance later.
                    </p>
                </div>
            )}
            {order.status !== 'served' && (
                <p className="text-xs text-amber-700">
                    Payment becomes available after the order is served.
                </p>
            )}
        </section>
    );
}
export default function Floor({ tables = [] }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [detail, setDetail] = useState<TableDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [action, setAction] = useState<Action>(null);
    const [error, setError] = useState<string | null>(null);
    const [now, setNow] = useState(0);
    const [partySize, setPartySize] = useState('1');
    const [paymentAmounts, setPaymentAmounts] = useState<
        Record<number, string>
    >({});
    const [splitWays, setSplitWays] = useState<Record<number, number>>({});
    const selectedIdRef = useRef<number | null>(null);
    const detailRequestRef = useRef(0);
    const selectedTable = useMemo(
        () => tables.find((table) => table.id === selectedId),
        [tables, selectedId],
    );
    const counts = useMemo(
        () => ({
            free: tables.filter((table) => table.state === 'free').length,
            seated: tables.filter((table) => table.state === 'seated').length,
            ordered: tables.filter((table) => table.state === 'ordered').length,
            bill: tables.filter((table) => table.state === 'bill').length,
        }),
        [tables],
    );
    const occupied = tables.length - counts.free;
    const loadDetail = useCallback(async (id: number, showLoader = true) => {
        const requestId = ++detailRequestRef.current;

        if (showLoader) {
            setLoading(true);
        }

        try {
            const response = await fetch(`/app/tables/${id}`, {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Unable to load table (${response.status}).`);
            }

            const data: TableDetail = await response.json();

            if (
                selectedIdRef.current !== id ||
                detailRequestRef.current !== requestId
            ) {
                return;
            }

            setDetail(data);
            setError(null);
        } catch (cause) {
            if (
                selectedIdRef.current === id &&
                detailRequestRef.current === requestId &&
                showLoader
            ) {
                setError(
                    cause instanceof Error
                        ? cause.message
                        : 'Unable to load table.',
                );
            }
        } finally {
            if (
                showLoader &&
                selectedIdRef.current === id &&
                detailRequestRef.current === requestId
            ) {
                setLoading(false);
            }
        }
    }, []);
    const selectTable = (id: number) => {
        selectedIdRef.current = id;
        setSelectedId(id);
        setPartySize('1');
        setPaymentAmounts({});
        setDetail(null);
        setLoading(true);
        setError(null);
        void loadDetail(id);
    };
    const closeDetail = () => {
        detailRequestRef.current += 1;
        selectedIdRef.current = null;
        setSelectedId(null);
        setDetail(null);
        setLoading(false);
        setError(null);
        setPaymentAmounts({});
    };
    const refreshFloor = useCallback(() => {
        router.reload({
            only: ['tables'],
        });
    }, []);
    useEffect(() => {
        const updateTime = () => {
            setNow(Date.now());
        };
        const initial = window.setTimeout(updateTime, 0);
        const interval = window.setInterval(updateTime, 60000);

        return () => {
            window.clearTimeout(initial);
            window.clearInterval(interval);
        };
    }, []);
    useEffect(() => {
        const interval = window.setInterval(() => {
            refreshFloor();
        }, 8000);

        return () => {
            window.clearInterval(interval);
        };
    }, [refreshFloor]);
    useEffect(() => {
        if (selectedId === null) {
            return;
        }

        const interval = window.setInterval(() => {
            void loadDetail(selectedId, false);
        }, 8000);

        return () => {
            window.clearInterval(interval);
        };
    }, [selectedId, loadDetail]);
    useEffect(() => {
        if (selectedId === null) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                closeDetail();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [selectedId]);
    const performAction = (
        url: string,
        data: Record<string, string | number>,
        nextAction: Exclude<Action, null>,
    ) => {
        setAction(nextAction);
        setError(null);
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => {
                refreshFloor();
                const currentId = selectedIdRef.current;

                if (currentId !== null) {
                    void loadDetail(currentId);
                }
            },
            onError: (errors) => {
                setError(
                    Object.values(errors)[0] ?? 'Unable to complete action.',
                );
            },
            onFinish: () => {
                setAction(null);
            },
        });
    };
    const acknowledge = (requestId: number) => {
        if (action !== null) {
            return;
        }

        setAction({
            type: 'acknowledge',
            id: requestId,
        });
        setError(null);
        router.patch(
            `/service-requests/${requestId}/acknowledge`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    refreshFloor();
                    const currentId = selectedIdRef.current;

                    if (currentId !== null) {
                        void loadDetail(currentId);
                    }
                },
                onError: (errors) => {
                    setError(
                        Object.values(errors)[0] ??
                            'Could not acknowledge request.',
                    );
                },
                onFinish: () => {
                    setAction(null);
                },
            },
        );
    };
    const seatTable = () => {
        if (selectedId === null || action !== null || !detail) {
            return;
        }

        if (detail.table.state !== 'free') {
            setError('Only free tables can be seated.');

            return;
        }

        const count = Number(partySize);

        if (
            partySize.trim() === '' ||
            !Number.isInteger(count) ||
            count < 1 ||
            count > detail.table.seats
        ) {
            setError(`Enter a party size between 1 and ${detail.table.seats}.`);

            return;
        }

        performAction(
            `/app/tables/${selectedId}/seat`,
            { party_size: count },
            { type: 'seat', id: selectedId },
        );
    };
    const freeTable = () => {
        if (selectedId === null || action !== null) {
            return;
        }

        if (!window.confirm('Free this table and close its active session?')) {
            return;
        }

        performAction(
            `/app/tables/${selectedId}/free`,
            {},
            { type: 'free', id: selectedId },
        );
    };
    const settlePayment = (order: BillOrder, method: PaymentMethod) => {
        if (selectedId === null || action !== null) {
            return;
        }

        if (order.status !== 'served') {
            setError('The order must be served before payment.');

            return;
        }

        const amountText =
            paymentAmounts[order.id] ?? amountInput(order.balance_minor);
        const amountMinor = parseAmountMinor(amountText);

        if (amountMinor === null || amountMinor > order.balance_minor) {
            setError(
                'Enter a valid amount not exceeding the remaining balance.',
            );

            return;
        }

        const pendingPayment = order.payments.find(
            (payment) => payment.status === 'pending',
        );

        if (pendingPayment !== undefined && pendingPayment.method !== method) {
            setError('Payment method does not match the pending payment.');

            return;
        }

        if (
            !window.confirm(
                `Collect ${money(amountMinor, order.currency)} via ${method.toUpperCase()} for order #${order.id}?`,
            )
        ) {
            return;
        }

        performAction(
            `/app/tables/${selectedId}/payments`,
            {
                order_id: order.id,
                method,
                amount_minor: amountMinor,
                split_ways: splitWays[order.id] ?? 1,
            },
            {
                type: 'payment',
                id: order.id,
            },
        );
        setPaymentAmounts((current) => {
            const updated = { ...current };
            const ways = splitWays[order.id] ?? 1;

            if (ways > 1) {
                const totalMinor =
                    order.total_minor ??
                    order.balance_minor + (order.paid_minor ?? 0);
                const shareMinor = Math.ceil(totalMinor / ways);
                const nextBalance = Math.max(
                    0,
                    order.balance_minor - amountMinor,
                );

                if (nextBalance > 0) {
                    updated[order.id] = amountInput(
                        Math.min(shareMinor, nextBalance),
                    );
                } else {
                    delete updated[order.id];
                }
            } else {
                delete updated[order.id];
            }

            return updated;
        });
    };

    return (
        <>
            <Head title="Floor Map" />
            <main className="mx-auto w-full max-w-7xl space-y-6 px-4 pt-6 pb-32 sm:px-6 lg:px-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-slate-500">
                            Restaurant operations
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">
                            Floor Map
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Live table occupancy and checkout
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={refreshFloor}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >
                        <RefreshCw size={16} />
                        Refresh
                    </button>
                </header>
                <section
                    aria-label="Table status legend"
                    className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
                >
                    {[
                        {
                            label: 'Total tables',
                            value: tables.length,
                            dot: 'bg-slate-600',
                        },
                        {
                            label: 'Free',
                            value: counts.free,
                            dot: DOT_STYLES.free,
                        },
                        {
                            label: 'Seated',
                            value: counts.seated,
                            dot: DOT_STYLES.seated,
                        },
                        {
                            label: 'Ordered',
                            value: counts.ordered,
                            dot: DOT_STYLES.ordered,
                        },
                        {
                            label: 'Bill requested',
                            value: counts.bill,
                            dot: DOT_STYLES.bill,
                        },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <span
                                    className={`h-2 w-2 rounded-full ${stat.dot}`}
                                />
                                {stat.label}
                            </div>
                            <p className="mt-2 text-2xl font-bold text-slate-950">
                                {stat.value}
                            </p>
                        </div>
                    ))}
                </section>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-medium text-slate-600">
                        {occupied} occupied of {tables.length} tables
                    </p>
                    <p className="flex items-center gap-1.5 text-xs text-slate-500">
                        <RefreshCw size={13} />
                        Auto-refresh every 8 seconds
                    </p>
                </div>
                {tables.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                        <h2 className="font-semibold text-slate-900">
                            No tables yet
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Tables will appear here when they are configured for
                            this restaurant.
                        </p>
                    </div>
                ) : (
                    <section
                        aria-label="Restaurant tables"
                        className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4"
                    >
                        {tables.map((table) => (
                            <FloorTile
                                key={table.id}
                                table={table}
                                now={now}
                                onClick={() => selectTable(table.id)}
                            />
                        ))}
                    </section>
                )}
            </main>
            {selectedId !== null && (
                <div className="fixed inset-0 z-[60] flex items-end justify-center md:items-stretch md:justify-end">
                    <button
                        type="button"
                        aria-label="Close table details"
                        onClick={closeDetail}
                        className="absolute inset-0 bg-slate-950/50"
                    />
                    <section
                        role="dialog"
                        aria-modal="true"
                        aria-label={`Table ${selectedTable?.number ?? ''} details`}
                        className="relative z-10 h-[90dvh] max-h-[90dvh] w-full overflow-y-auto overscroll-contain touch-pan-y rounded-t-3xl bg-white shadow-2xl md:max-h-none md:max-w-lg md:rounded-none"
                    >
                        <div className="mx-auto mt-3 h-1.5 w-12 rounded-full bg-slate-200 md:hidden" />
                        <div className="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
                            <div>
                                <p className="text-xs font-medium text-slate-500">
                                    Table details
                                </p>
                                <h2 className="text-xl font-bold text-slate-950">
                                    Table {selectedTable?.number}
                                </h2>
                            </div>
                            <button
                                type="button"
                                aria-label="Close"
                                onClick={closeDetail}
                                className="rounded-lg p-2 hover:bg-slate-100"
                            >
                                <X size={20} />
                            </button>
                        </div>
                        <div className="space-y-5 p-5 pb-[calc(7rem+env(safe-area-inset-bottom))]">
                            {loading && !detail && (
                                <div className="flex items-center gap-2 py-8 text-sm text-slate-500">
                                    <LoaderCircle
                                        size={18}
                                        className="animate-spin"
                                    />
                                    Loading table details...
                                </div>
                            )}
                            {error && (
                                <div
                                    role="alert"
                                    className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                                >
                                    {error}
                                </div>
                            )}
                            {detail && (
                                <>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-xl bg-slate-50 p-3">
                                            <p className="text-xs text-slate-500">
                                                Guests
                                            </p>
                                            <p className="mt-1 font-bold">
                                                {detail.table.party_size} /{' '}
                                                {detail.table.seats}
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-slate-50 p-3">
                                            <p className="text-xs text-slate-500">
                                                Open bill
                                            </p>
                                            <p className="mt-1 font-bold">
                                                {money(
                                                    detail.open_bill_minor,
                                                    detail.currency,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    {detail.table.state === 'free' && (
                                        <section className="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                            <h3 className="font-bold text-emerald-950">
                                                Seat guests
                                            </h3>
                                            <p className="text-sm text-emerald-800">
                                                Choose a party size for this
                                                free table.
                                            </p>
                                            <label
                                                htmlFor="floor-party-size"
                                                className="block text-sm font-semibold text-emerald-950"
                                            >
                                                Party size (max{' '}
                                                {detail.table.seats})
                                            </label>
                                            <input
                                                id="floor-party-size"
                                                type="number"
                                                inputMode="numeric"
                                                min={1}
                                                max={detail.table.seats}
                                                step={1}
                                                value={partySize}
                                                disabled={action !== null}
                                                onChange={(event) =>
                                                    setPartySize(
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full rounded-xl border border-emerald-300 bg-white px-4 py-3 text-base text-slate-950 focus-visible:outline-2 focus-visible:outline-emerald-600 disabled:opacity-50"
                                            />
                                            <button
                                                type="button"
                                                onClick={seatTable}
                                                disabled={
                                                    action !== null ||
                                                    loading ||
                                                    detail.table.seats < 1
                                                }
                                                className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {action?.type === 'seat' ? (
                                                    <LoaderCircle
                                                        size={17}
                                                        className="animate-spin"
                                                    />
                                                ) : (
                                                    <Users size={17} />
                                                )}
                                                {action?.type === 'seat'
                                                    ? 'Seating guests...'
                                                    : 'Seat guests'}
                                            </button>
                                        </section>
                                    )}
                                    <section className="space-y-3">
                                        <h3 className="font-bold text-slate-950">
                                            Pending service requests
                                        </h3>
                                        {detail.service_requests.length ===
                                        0 ? (
                                            <p className="text-sm text-slate-500">
                                                No pending requests.
                                            </p>
                                        ) : (
                                            detail.service_requests.map(
                                                (request) => (
                                                    <div
                                                        key={request.id}
                                                        className="flex items-center justify-between gap-3 rounded-xl border border-orange-200 bg-orange-50 p-3"
                                                    >
                                                        <span className="text-sm font-semibold text-orange-950">
                                                            {readableKind(
                                                                request.kind,
                                                            )}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                action !== null
                                                            }
                                                            onClick={() =>
                                                                acknowledge(
                                                                    request.id,
                                                                )
                                                            }
                                                            className="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-2 text-xs font-bold text-orange-900 disabled:opacity-50"
                                                        >
                                                            <Check size={14} />
                                                            Acknowledge
                                                        </button>
                                                    </div>
                                                ),
                                            )
                                        )}
                                    </section>
                                    <section className="space-y-3">
                                        <h3 className="font-bold text-slate-950">
                                            Unpaid orders
                                        </h3>
                                        {detail.orders.length === 0 && (
                                            <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                                                No unpaid orders for this table.
                                            </p>
                                        )}
                                        {detail.orders.map((order) => (
                                            <div
                                                key={order.id}
                                                className="overflow-hidden rounded-2xl border border-slate-200"
                                            >
                                                <div className="flex justify-between bg-slate-50 p-4">
                                                    <div>
                                                        <p className="font-bold text-slate-950">
                                                            Order #{order.id}
                                                        </p>
                                                        <p className="text-xs capitalize text-slate-500">
                                                            {order.status}
                                                        </p>
                                                    </div>
                                                    <p className="font-bold text-slate-950">
                                                        {money(
                                                            order.balance_minor,
                                                            order.currency,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="space-y-3 p-4">
                                                    {order.lines.map((line) => (
                                                        <div
                                                            key={line.id}
                                                            className="flex justify-between gap-3 text-sm"
                                                        >
                                                            <div>
                                                                <p className="font-medium text-slate-900">
                                                                    {line.qty} ×{' '}
                                                                    {line.name}
                                                                </p>
                                                                {line.note && (
                                                                    <p className="mt-0.5 text-xs text-slate-500">
                                                                        Note:{' '}
                                                                        {
                                                                            line.note
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <span className="shrink-0 font-semibold">
                                                                {money(
                                                                    line.line_total_minor,
                                                                    order.currency,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ))}
                                                    <a
                                                        href={`/app/tables/${detail.table.id}/orders/${order.id}/receipt`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="border-border-brand bg-surface-brand-soft text-text-brand hover:bg-action-ghost-hover inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition-colors"
                                                    >
                                                        Print Receipt
                                                    </a>
                                                    <EmailReceiptForm
                                                        tableId={
                                                            detail.table.id
                                                        }
                                                        orderId={order.id}
                                                    />
                                                    <PaymentSection
                                                        order={order}
                                                        splitWays={
                                                            splitWays[
                                                                order.id
                                                            ] ?? 1
                                                        }
                                                        onSplitWaysChange={(
                                                            ways,
                                                        ) =>
                                                            setSplitWays(
                                                                (current) => ({
                                                                    ...current,
                                                                    [order.id]:
                                                                        ways,
                                                                }),
                                                            )
                                                        }
                                                        action={action}
                                                        amount={
                                                            paymentAmounts[
                                                                order.id
                                                            ] ??
                                                            amountInput(
                                                                order.balance_minor,
                                                            )
                                                        }
                                                        onAmountChange={(
                                                            value,
                                                        ) =>
                                                            setPaymentAmounts(
                                                                (current) => ({
                                                                    ...current,
                                                                    [order.id]:
                                                                        value,
                                                                }),
                                                            )
                                                        }
                                                        onPayment={(method) =>
                                                            settlePayment(
                                                                order,
                                                                method,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </section>
                                </>
                            )}
                        </div>
                        <div className="border-t border-slate-200 bg-white p-4">
                            <button
                                type="button"
                                disabled={
                                    action !== null ||
                                    loading ||
                                    !detail ||
                                    detail.orders.length > 0 ||
                                    detail.table.state === 'free'
                                }
                                onClick={freeTable}
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {action?.type === 'free'
                                    ? 'Freeing table...'
                                    : 'Free table'}
                            </button>
                            {detail && detail.orders.length > 0 && (
                                <p className="mt-2 text-center text-xs text-slate-500">
                                    All unpaid orders must be settled before
                                    freeing the table.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            )}
        </>
    );
}
