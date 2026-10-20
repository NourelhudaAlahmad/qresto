import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { Input } from '@/components/qresto/input';
import { PriceSummary } from '@/components/qresto/price-summary';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { RadioCard, RadioGroup } from '@/components/qresto/radio-card';
import { StickyDock } from '@/components/qresto/sticky-dock';
import GuestLayout from '@/layouts/guest-layout';
import { formatMoney } from '@/lib/formatMoney';
import type { Money } from '@/types/order';

type PaymentMethod = 'card' | 'cash' | 'pos';

type Restaurant = {
    id: number;
    name: string;
    currency: string;
};

type Table = {
    id: number;
    number: string;
};

type Cart = {
    id: number;
    subtotal: Money;
    service_pct: string;
    service_amount: Money;
    discount_amount: Money;
    total: Money;
};

type Promo = {
    code: string | null;
    discount: Money;
};

type Gateway = {
    intent_id: string | null;
    status: string | null;
    '3ds_required': boolean;
    order_id?: number | null;
    payment_id?: number | null;
    client_secret?: string | null;
};

type Props = {
    restaurant: Restaurant;
    table: Table;
    cart: Cart;
    payment_methods: string[];
    tip_presets: number[];
    promo: Promo;
    gateway: Gateway;
};

type PageProps = {
    errors?: Record<string, string>;
};

function moneyFromMinor(minor: number, currency: string): Money {
    return {
        amount: minor,
        currency,
        formatted: new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(minor / 100),
    };
}

function moneyMinor(money: Money): number {
    return Number(money.amount);
}

function generateIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `checkout-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function formatCardNumber(value: string): string {
    return value
        .replace(/\D/g, '')
        .slice(0, 16)
        .replace(/(\d{4})(?=\d)/g, '$1 ');
}

function formatExpiry(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 4);

    if (digits.length <= 2) {
        return digits;
    }

    return `${digits.slice(0, 2)} / ${digits.slice(2)}`;
}

function BackIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5"
        >
            <path
                d="M15 18l-6-6 6-6"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function ShieldIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5"
        >
            <path
                d="M12 3l7 3v5c0 4.7-2.9 8.3-7 10-4.1-1.7-7-5.3-7-10V6l7-3z"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinejoin="round"
            />
            <path
                d="M9 12l2 2 4-4"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function CardIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5"
        >
            <rect
                x="3"
                y="5"
                width="18"
                height="14"
                rx="2"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <path d="M3 9h18" stroke="currentColor" strokeWidth="1.7" />
        </svg>
    );
}

function CashIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5"
        >
            <rect
                x="3"
                y="6"
                width="18"
                height="12"
                rx="2"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <circle
                cx="12"
                cy="12"
                r="2.5"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <path
                d="M6 9c1.1 0 2-.9 2-2M18 9c-1.1 0-2-.9-2-2M6 15c1.1 0 2 .9 2 2M18 15c-1.1 0-2 .9-2 2"
                stroke="currentColor"
                strokeWidth="1.4"
            />
        </svg>
    );
}

function TerminalIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-5"
        >
            <rect
                x="6"
                y="3"
                width="12"
                height="18"
                rx="2.5"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <path
                d="M9 7h6M9 11h1M12 11h1M15 11h1M9 14h1M12 14h1M15 14h1M9 17h7"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
        </svg>
    );
}

function LockIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-4"
        >
            <rect
                x="5"
                y="10"
                width="14"
                height="10"
                rx="2"
                stroke="currentColor"
                strokeWidth="1.7"
            />
            <path
                d="M8 10V7a4 4 0 018 0v3"
                stroke="currentColor"
                strokeWidth="1.7"
            />
        </svg>
    );
}

function PercentIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            className="size-4"
        >
            <path
                d="M7 17L17 7"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
            />
            <circle
                cx="7.5"
                cy="7.5"
                r="2"
                stroke="currentColor"
                strokeWidth="1.5"
            />
            <circle
                cx="16.5"
                cy="16.5"
                r="2"
                stroke="currentColor"
                strokeWidth="1.5"
            />
        </svg>
    );
}

export default function CheckoutPage({
    restaurant,
    table,
    cart,
    payment_methods,
    tip_presets,
    promo,
    gateway,
}: Props) {
    const page = usePage<PageProps>();
    const errors = page.props.errors ?? {};

    const availableMethods = useMemo(
        () => new Set(payment_methods),
        [payment_methods],
    );

    const initialMethod: PaymentMethod = availableMethods.has('card')
        ? 'card'
        : availableMethods.has('cash')
          ? 'cash'
          : 'pos';

    const [method, setMethod] = useState<PaymentMethod>(initialMethod);

    const [tipPct, setTipPct] = useState<number>(
        tip_presets.includes(0) ? 0 : (tip_presets[0] ?? 0),
    );

    const [splitEnabled, setSplitEnabled] = useState(false);
    const [splitWays, setSplitWays] = useState(2);

    const [promoOpen, setPromoOpen] = useState(Boolean(promo.code));
    const [promoCode, setPromoCode] = useState(promo.code ?? '');

    const [cardNumber, setCardNumber] = useState('');
    const [cardExpiry, setCardExpiry] = useState('');
    const [cardCvc, setCardCvc] = useState('');

    const [submitting, setSubmitting] = useState(false);
    const [promoBusy, setPromoBusy] = useState(false);
    const [confirming3ds, setConfirming3ds] = useState(false);

    const [idempotencyKey, setIdempotencyKey] = useState(
        generateIdempotencyKey,
    );

    const cvcRef = useRef<HTMLInputElement>(null);

    const subtotalMinor = moneyMinor(cart.subtotal);
    const serviceMinor = moneyMinor(cart.service_amount);
    const discountMinor = moneyMinor(cart.discount_amount);

    const tipMinor = Math.round(subtotalMinor * (tipPct / 100));

    const totalMinor = Math.max(
        0,
        subtotalMinor + serviceMinor - discountMinor + tipMinor,
    );

    const shareMinor = splitEnabled
        ? Math.ceil(totalMinor / splitWays)
        : totalMinor;

    const totalMoney = moneyFromMinor(totalMinor, restaurant.currency);
    const tipMoney = moneyFromMinor(tipMinor, restaurant.currency);
    const shareMoney = moneyFromMinor(shareMinor, restaurant.currency);

    const isDeferred = method === 'cash' || method === 'pos';

    const requires3ds =
        gateway['3ds_required'] &&
        gateway.order_id !== null &&
        gateway.order_id !== undefined;

    useEffect(() => {
        if (errors.card_cvc) {
            cvcRef.current?.focus();
        }
    }, [errors.card_cvc]);

    const submitCheckout = (event: FormEvent) => {
        event.preventDefault();

        if (submitting || requires3ds) {
            return;
        }

        setSubmitting(true);

        router.post(
            '/checkout',
            {
                method,
                tip_pct: tipPct,
                split_ways: splitEnabled ? splitWays : 1,
                idempotency_key: idempotencyKey,
                card_number:
                    method === 'card' ? cardNumber.replace(/\s/g, '') : null,
                card_expiry: method === 'card' ? cardExpiry : null,
                card_cvc: method === 'card' ? cardCvc : null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    setSubmitting(false);
                },
                onSuccess: () => {
                    setSubmitting(false);
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const applyPromo = () => {
        const code = promoCode.trim();

        if (!code || promoBusy) {
            return;
        }

        setPromoBusy(true);

        router.post(
            '/checkout/promo',
            {
                promo_code: code,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setPromoBusy(false);
                },
            },
        );
    };

    const removePromo = () => {
        if (promoBusy) {
            return;
        }

        setPromoBusy(true);

        router.delete('/checkout/promo', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setPromoCode('');
            },
            onFinish: () => {
                setPromoBusy(false);
            },
        });
    };

    const confirm3ds = () => {
        if (!gateway.order_id || confirming3ds) {
            return;
        }

        setConfirming3ds(true);

        router.post(
            '/checkout/confirm',
            {
                order_id: gateway.order_id,
                confirmed: true,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setConfirming3ds(false);
                },
            },
        );
    };

    const selectMethod = (value: string) => {
        if (value === 'card' || value === 'cash' || value === 'pos') {
            setMethod(value);
            setIdempotencyKey(generateIdempotencyKey());
        }
    };

    const ctaLabel = isDeferred
        ? `Send to the kitchen · ${formatMoney(totalMoney)}`
        : `Pay ${formatMoney(totalMoney)}`;

    const footnote = isDeferred
        ? 'You settle up with Nadia at the end of the meal.'
        : 'Charged once. 3-D Secure may ask for your bank’s check.';

    const serviceLabel = `Service ${Number(cart.service_pct)}%`;

    const tipLabel = tipPct === 0 ? 'Tip' : `Tip ${tipPct}%`;

    return (
        <GuestLayout>
            <Head title="Payment" />

            <div
                dir="ltr"
                className="flex min-h-dvh flex-col bg-[var(--surface-page)]"
            >
                <form
                    onSubmit={submitCheckout}
                    className="flex min-h-dvh flex-col"
                >
                    <div className="flex-1">
                        <header className="border-border-subtle bg-surface-page sticky top-0 z-20 shrink-0 border-b">
                            <div className="mx-auto flex w-full max-w-md items-center gap-3 px-5 py-4">
                                <button
                                    type="button"
                                    aria-label="Go back"
                                    onClick={() => router.visit('/cart')}
                                    className="text-text-primary hover:bg-action-ghost-hover -ms-2 flex size-11 shrink-0 items-center justify-center rounded-full transition-colors"
                                >
                                    <BackIcon />
                                </button>

                                <div className="min-w-0 flex-1">
                                    <h1 className="text-title-2 text-text-primary font-semibold">
                                        Payment
                                    </h1>

                                    <p className="text-caption text-text-secondary mt-0.5">
                                        Secured by Stripe · 3-D Secure
                                    </p>
                                </div>

                                <div
                                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--herb-50)] text-[var(--herb-700)]"
                                    aria-hidden="true"
                                >
                                    <ShieldIcon />
                                </div>
                            </div>
                        </header>

                        <main className="mx-auto flex w-full max-w-md flex-col gap-5 px-5 py-5 pb-8">
                            {requires3ds ? (
                                <Card className="flex flex-col gap-4 p-5">
                                    <div className="flex items-start gap-3">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--herb-50)] text-[var(--herb-700)]">
                                            <ShieldIcon />
                                        </div>

                                        <div>
                                            <h2 className="text-label text-text-primary font-semibold">
                                                Verify your payment
                                            </h2>

                                            <p className="text-body text-text-secondary mt-1">
                                                Your bank requires an additional
                                                authentication step before the
                                                payment can be completed.
                                            </p>
                                        </div>
                                    </div>

                                    {errors.payment ? (
                                        <p
                                            role="alert"
                                            className="text-caption text-danger"
                                        >
                                            {errors.payment}
                                        </p>
                                    ) : null}

                                    <Button
                                        type="button"
                                        fullWidth
                                        size="lg"
                                        loading={confirming3ds}
                                        onClick={confirm3ds}
                                    >
                                        Complete authentication
                                    </Button>
                                </Card>
                            ) : (
                                <>
                                    <section className="grid grid-cols-2 gap-2">
                                        <Button
                                            type="button"
                                            disabled
                                            fullWidth
                                            title="Apple Pay will be available in K3."
                                            className="h-12 bg-black text-white opacity-100 disabled:bg-black disabled:text-white disabled:opacity-100"
                                        >
                                            <span className="text-body-lg font-semibold">
                                                Apple Pay
                                            </span>
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled
                                            fullWidth
                                            title="Google Pay will be available in K3."
                                            className="h-12 opacity-100 disabled:opacity-100"
                                        >
                                            <span className="text-body font-semibold">
                                                Google Pay
                                            </span>
                                        </Button>
                                    </section>

                                    <div className="flex items-center gap-3">
                                        <span className="bg-border-subtle h-px flex-1" />

                                        <span className="text-caption text-text-tertiary whitespace-nowrap">
                                            or choose a method
                                        </span>

                                        <span className="bg-border-subtle h-px flex-1" />
                                    </div>

                                    <section>
                                        <RadioGroup
                                            value={method}
                                            onValueChange={selectMethod}
                                            className="flex flex-col gap-2.5"
                                        >
                                            {availableMethods.has('card') ? (
                                                <div className="relative">
                                                    <RadioCard
                                                        value="card"
                                                        title="Pay now by card"
                                                        description="Order goes to the kitchen the moment it clears"
                                                        className="min-h-[72px] pe-14"
                                                    />

                                                    <span
                                                        aria-hidden="true"
                                                        className="text-text-secondary pointer-events-none absolute end-4 top-1/2 -translate-y-1/2"
                                                    >
                                                        <CardIcon />
                                                    </span>
                                                </div>
                                            ) : null}

                                            {method === 'card' &&
                                            availableMethods.has('card') ? (
                                                <div className="border-border-subtle bg-surface-subtle -mt-1 rounded-md border p-4">
                                                    <div className="flex flex-col gap-3">
                                                        <label className="flex flex-col gap-1.5">
                                                            <span className="text-caption text-text-secondary">
                                                                Card number
                                                            </span>

                                                            <div className="relative">
                                                                <Input
                                                                    value={
                                                                        cardNumber
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setCardNumber(
                                                                            formatCardNumber(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        )
                                                                    }
                                                                    inputMode="numeric"
                                                                    autoComplete="cc-number"
                                                                    placeholder="4242 4242 4242 4242"
                                                                    className="pe-16"
                                                                    aria-invalid={
                                                                        errors.card_number
                                                                            ? true
                                                                            : undefined
                                                                    }
                                                                />

                                                                <span className="text-caption text-text-secondary pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 font-semibold">
                                                                    VISA
                                                                </span>
                                                            </div>

                                                            {errors.card_number ? (
                                                                <span
                                                                    role="alert"
                                                                    className="text-caption text-danger"
                                                                >
                                                                    {
                                                                        errors.card_number
                                                                    }
                                                                </span>
                                                            ) : null}
                                                        </label>

                                                        <div className="grid grid-cols-2 gap-3">
                                                            <label className="flex flex-col gap-1.5">
                                                                <span className="text-caption text-text-secondary">
                                                                    Expiry
                                                                </span>

                                                                <Input
                                                                    value={
                                                                        cardExpiry
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setCardExpiry(
                                                                            formatExpiry(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        )
                                                                    }
                                                                    inputMode="numeric"
                                                                    autoComplete="cc-exp"
                                                                    placeholder="09 / 28"
                                                                    aria-invalid={
                                                                        errors.card_expiry
                                                                            ? true
                                                                            : undefined
                                                                    }
                                                                />

                                                                {errors.card_expiry ? (
                                                                    <span
                                                                        role="alert"
                                                                        className="text-caption text-danger"
                                                                    >
                                                                        {
                                                                            errors.card_expiry
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </label>

                                                            <label className="flex flex-col gap-1.5">
                                                                <span className="text-caption text-text-secondary">
                                                                    CVC
                                                                </span>

                                                                <Input
                                                                    ref={cvcRef}
                                                                    value={
                                                                        cardCvc
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setCardCvc(
                                                                            event.target.value
                                                                                .replace(
                                                                                    /\D/g,
                                                                                    '',
                                                                                )
                                                                                .slice(
                                                                                    0,
                                                                                    4,
                                                                                ),
                                                                        )
                                                                    }
                                                                    inputMode="numeric"
                                                                    autoComplete="cc-csc"
                                                                    placeholder="123"
                                                                    aria-invalid={
                                                                        errors.card_cvc
                                                                            ? true
                                                                            : undefined
                                                                    }
                                                                />

                                                                {errors.card_cvc ? (
                                                                    <span
                                                                        role="alert"
                                                                        className="text-caption text-danger"
                                                                    >
                                                                        {
                                                                            errors.card_cvc
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </label>
                                                        </div>

                                                        <div className="text-caption text-text-tertiary flex items-center gap-2 pt-1">
                                                            <LockIcon />

                                                            <span>
                                                                Card details
                                                                never touch{' '}
                                                                {
                                                                    restaurant.name
                                                                }
                                                                's servers.
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            ) : null}

                                            {availableMethods.has('cash') ? (
                                                <div className="relative">
                                                    <RadioCard
                                                        value="cash"
                                                        title="Cash to the waiter"
                                                        description="Nadia brings the bill when you ask"
                                                        className="min-h-[72px] pe-14"
                                                    />

                                                    <span
                                                        aria-hidden="true"
                                                        className="text-text-secondary pointer-events-none absolute end-4 top-1/2 -translate-y-1/2"
                                                    >
                                                        <CashIcon />
                                                    </span>
                                                </div>
                                            ) : null}

                                            {availableMethods.has('pos') ? (
                                                <div className="relative">
                                                    <RadioCard
                                                        value="pos"
                                                        title="Card at the table"
                                                        description="Waiter brings the terminal to you"
                                                        className="min-h-[72px] pe-14"
                                                    />

                                                    <span
                                                        aria-hidden="true"
                                                        className="text-text-secondary pointer-events-none absolute end-4 top-1/2 -translate-y-1/2"
                                                    >
                                                        <TerminalIcon />
                                                    </span>
                                                </div>
                                            ) : null}
                                        </RadioGroup>
                                    </section>

                                    {isDeferred ? (
                                        <div className="rounded-md border border-[var(--saffron-300)] bg-[var(--saffron-50)] px-4 py-3">
                                            <p className="text-label text-text-primary font-semibold">
                                                Pay after ordering
                                            </p>

                                            <p className="text-caption text-text-secondary mt-1 leading-relaxed">
                                                The kitchen starts cooking
                                                straight away. You settle up at
                                                the end — the waiter sees this
                                                order as unpaid until then.
                                            </p>
                                        </div>
                                    ) : null}

                                    <div className="bg-border-subtle h-px" />

                                    <section className="flex flex-col gap-3">
                                        <div className="flex items-baseline justify-between gap-3">
                                            <h2 className="text-label text-text-primary font-semibold">
                                                Tip the floor team
                                            </h2>

                                            <span className="text-caption text-text-tertiary">
                                                shared equally
                                            </span>
                                        </div>

                                        <div className="grid grid-cols-4 gap-2">
                                            {tip_presets.map((preset) => (
                                                <ChipFilter
                                                    key={preset}
                                                    active={tipPct === preset}
                                                    onClick={() =>
                                                        setTipPct(preset)
                                                    }
                                                >
                                                    {preset === 0
                                                        ? 'None'
                                                        : `${preset}%`}
                                                </ChipFilter>
                                            ))}
                                        </div>
                                    </section>

                                    <section className="grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            aria-expanded={promoOpen}
                                            onClick={() =>
                                                setPromoOpen(
                                                    (current) => !current,
                                                )
                                            }
                                            className="border-border-default bg-surface-card text-label text-text-primary hover:bg-action-ghost-hover flex min-h-11 items-center justify-center gap-2 rounded-full border border-dashed px-3 font-medium transition-colors"
                                        >
                                            <PercentIcon />

                                            <span>
                                                {promo.code
                                                    ? promo.code
                                                    : 'Promo code'}
                                            </span>
                                        </button>

                                        <button
                                            type="button"
                                            aria-pressed={splitEnabled}
                                            onClick={() =>
                                                setSplitEnabled(
                                                    (current) => !current,
                                                )
                                            }
                                            className={`text-label flex min-h-11 items-center justify-center rounded-full border px-3 font-medium transition-colors ${
                                                splitEnabled
                                                    ? 'border-transparent bg-[var(--ink-900)] text-white'
                                                    : 'border-border-default bg-surface-card text-text-primary hover:bg-action-ghost-hover'
                                            }`}
                                        >
                                            Split the bill
                                        </button>
                                    </section>

                                    {promoOpen ? (
                                        <Card className="flex flex-col gap-3 p-4">
                                            {promo.code ? (
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="text-label text-text-primary truncate font-semibold">
                                                            {promo.code}
                                                        </p>

                                                        <p className="text-caption text-text-secondary mt-0.5">
                                                            Discount applied
                                                        </p>
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        loading={promoBusy}
                                                        onClick={removePromo}
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="flex gap-2">
                                                    <Input
                                                        value={promoCode}
                                                        onChange={(event) =>
                                                            setPromoCode(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Enter code"
                                                        aria-label="Promo code"
                                                        aria-invalid={
                                                            errors.promo_code
                                                                ? true
                                                                : undefined
                                                        }
                                                    />

                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        loading={promoBusy}
                                                        disabled={
                                                            !promoCode.trim()
                                                        }
                                                        onClick={applyPromo}
                                                    >
                                                        Apply
                                                    </Button>
                                                </div>
                                            )}

                                            {errors.promo_code ? (
                                                <p
                                                    role="alert"
                                                    className="text-caption text-danger"
                                                >
                                                    {errors.promo_code}
                                                </p>
                                            ) : null}
                                        </Card>
                                    ) : null}

                                    {splitEnabled ? (
                                        <Card className="flex flex-col gap-4 p-4">
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <h2 className="text-label text-text-primary font-semibold">
                                                        Split evenly
                                                    </h2>

                                                    <p className="text-caption text-text-secondary mt-1">
                                                        Split evenly between 2–8
                                                        people.
                                                    </p>
                                                </div>

                                                <QuantityStepper
                                                    value={splitWays}
                                                    min={2}
                                                    max={8}
                                                    size="sm"
                                                    onChange={setSplitWays}
                                                />
                                            </div>

                                            <div className="border-border-subtle flex items-center justify-between border-t pt-3">
                                                <span className="text-body text-text-secondary">
                                                    Your share
                                                </span>

                                                <strong className="text-title-3 text-text-primary">
                                                    {formatMoney(shareMoney)}
                                                </strong>
                                            </div>

                                            <p className="text-caption text-text-tertiary leading-relaxed">
                                                Everyone at Table {table.number}{' '}
                                                gets a link. Unpaid shares stay
                                                on the waiter's checkout screen.
                                            </p>
                                        </Card>
                                    ) : null}

                                    <Card className="p-4">
                                        <PriceSummary
                                            subtotal={formatMoney(
                                                cart.subtotal,
                                            )}
                                            service={formatMoney(
                                                cart.service_amount,
                                            )}
                                            serviceLabel={serviceLabel}
                                            discount={
                                                discountMinor > 0
                                                    ? formatMoney(
                                                          cart.discount_amount,
                                                      )
                                                    : undefined
                                            }
                                            tip={formatMoney(tipMoney)}
                                            tipLabel={tipLabel}
                                            split={
                                                splitEnabled
                                                    ? `${splitWays} ways · ${formatMoney(
                                                          shareMoney,
                                                      )} each`
                                                    : undefined
                                            }
                                            total={formatMoney(totalMoney)}
                                        />
                                    </Card>

                                    {errors.cart ? (
                                        <p
                                            role="alert"
                                            className="text-caption text-danger"
                                        >
                                            {errors.cart}
                                        </p>
                                    ) : null}

                                    {errors.method ? (
                                        <p
                                            role="alert"
                                            className="text-caption text-danger"
                                        >
                                            {errors.method}
                                        </p>
                                    ) : null}
                                </>
                            )}
                        </main>
                    </div>

                    {!requires3ds ? (
                        <StickyDock className="px-5">
                            <div className="mx-auto flex w-full max-w-md flex-col gap-2">
                                <Button
                                    type="submit"
                                    size="lg"
                                    fullWidth
                                    loading={submitting}
                                >
                                    {ctaLabel}
                                </Button>

                                <p className="text-caption text-text-tertiary px-2 text-center">
                                    {footnote}
                                </p>
                            </div>
                        </StickyDock>
                    ) : null}
                </form>
            </div>
        </GuestLayout>
    );
}
