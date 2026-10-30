import type {
    PaymentRequest,
    PaymentRequestPaymentMethodEvent,
    Stripe,
    StripeCardCvcElement,
    StripeCardExpiryElement,
    StripeCardNumberElement,
    StripeElementStyle,
} from '@stripe/stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useRef,
    useState,
} from 'react';

/*
 * #35: Stripe in the guest checkout. Card details are typed into Stripe's
 * own iframes and turned into a PaymentMethod in the browser, so only a
 * pm_… id ever reaches QResto. The iframes are dressed in QResto tokens,
 * so the fields look like every other input.
 */

const loaders = new Map<string, Promise<Stripe | null>>();

/** One Stripe.js instance per publishable key. */
export function getStripe(publishableKey: string): Promise<Stripe | null> {
    if (!loaders.has(publishableKey)) {
        loaders.set(publishableKey, loadStripe(publishableKey));
    }

    return loaders.get(publishableKey)!;
}

export function useStripe(publishableKey: string | null | undefined) {
    const [stripe, setStripe] = useState<Stripe | null>(null);

    useEffect(() => {
        if (!publishableKey) {
            return;
        }

        let active = true;

        void getStripe(publishableKey).then((instance) => {
            if (active) {
                setStripe(instance);
            }
        });

        return () => {
            active = false;
        };
    }, [publishableKey]);

    return stripe;
}

/** A CSS colour (oklch, var(), …) as the hex/rgba Stripe's iframe accepts. */
function plainColour(value: string, fallback: string): string {
    if (typeof document === 'undefined' || value === '') {
        return fallback;
    }

    const context = document.createElement('canvas').getContext('2d');

    if (!context) {
        return fallback;
    }

    context.fillStyle = fallback;
    context.fillStyle = value;

    return String(context.fillStyle);
}

function token(name: string): string {
    return getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
}

/** Element styles from the design tokens: QResto type, colour and size. */
export function tokenStyle(): StripeElementStyle {
    return {
        base: {
            fontFamily: token('--font-ui') || 'system-ui, sans-serif',
            fontSize: '15px',
            fontWeight: '400',
            lineHeight: '24px',
            color: plainColour(token('--text-primary'), '#1d1a17'),
            iconColor: plainColour(token('--text-secondary'), '#5f574e'),
            '::placeholder': {
                color: plainColour(token('--text-tertiary'), '#8c847a'),
            },
        },
        invalid: {
            color: plainColour(token('--berry-500'), '#b23a48'),
            iconColor: plainColour(token('--berry-500'), '#b23a48'),
        },
    };
}

/** The same Google Fonts stylesheet the page uses, for inside the iframe. */
function fontSources(): { cssSrc: string }[] {
    const link = document.querySelector<HTMLLinkElement>(
        'link[rel="stylesheet"][href*="fonts.googleapis.com"]',
    );

    return link ? [{ cssSrc: link.href }] : [];
}

export type CardField = 'card_number' | 'card_expiry' | 'card_cvc';

export type TokeniseResult =
    { paymentMethodId: string } | { field: CardField | null; message: string };

export type StripeCardFieldsHandle = {
    tokenise: () => Promise<TokeniseResult>;
};

type Props = {
    stripe: Stripe | null;
    errors: Partial<Record<CardField, string>>;
    restaurantName: string;
};

/**
 * Card number, expiry and CVC as Stripe Elements in QResto inputs. Same
 * labels, layout and error lines as the fields they replace.
 */
export const StripeCardFields = forwardRef<StripeCardFieldsHandle, Props>(
    function StripeCardFields({ stripe, errors, restaurantName }, ref) {
        const numberHost = useRef<HTMLDivElement>(null);
        const expiryHost = useRef<HTMLDivElement>(null);
        const cvcHost = useRef<HTMLDivElement>(null);
        const elements = useRef<{
            number: StripeCardNumberElement;
            expiry: StripeCardExpiryElement;
            cvc: StripeCardCvcElement;
        } | null>(null);
        const [live, setLive] = useState<Partial<Record<CardField, string>>>(
            {},
        );

        useEffect(() => {
            if (
                !stripe ||
                !numberHost.current ||
                !expiryHost.current ||
                !cvcHost.current
            ) {
                return;
            }

            const group = stripe.elements({ fonts: fontSources() });
            const options = {
                style: tokenStyle(),
                classes: {
                    base: 'qr-stripe-field',
                    focus: 'qr-stripe-field--focus',
                    invalid: 'qr-stripe-field--invalid',
                },
            };

            const number = group.create('cardNumber', {
                ...options,
                showIcon: true,
                // No "Save with Link" button inside the field: it must look
                // like any other QResto input.
                disableLink: true,
                placeholder: '4242 4242 4242 4242',
            });
            const expiry = group.create('cardExpiry', {
                ...options,
                placeholder: '09 / 28',
            });
            const cvc = group.create('cardCvc', {
                ...options,
                placeholder: '123',
            });

            const track =
                (field: CardField) =>
                (event: { error?: { message: string } }) =>
                    setLive((current) => ({
                        ...current,
                        [field]: event.error?.message,
                    }));

            number.on('change', track('card_number'));
            expiry.on('change', track('card_expiry'));
            cvc.on('change', track('card_cvc'));

            number.mount(numberHost.current);
            expiry.mount(expiryHost.current);
            cvc.mount(cvcHost.current);

            elements.current = { number, expiry, cvc };

            return () => {
                number.destroy();
                expiry.destroy();
                cvc.destroy();
                elements.current = null;
            };
        }, [stripe]);

        useImperativeHandle(
            ref,
            () => ({
                tokenise: async () => {
                    if (!stripe || !elements.current) {
                        return {
                            field: null,
                            message:
                                'Card payments are still loading. Try again in a moment.',
                        };
                    }

                    const { paymentMethod, error } =
                        await stripe.createPaymentMethod({
                            type: 'card',
                            card: elements.current.number,
                        });

                    if (error || !paymentMethod) {
                        const code = error?.code ?? '';

                        return {
                            field: code.includes('expiry')
                                ? 'card_expiry'
                                : code.includes('cvc')
                                  ? 'card_cvc'
                                  : 'card_number',
                            message:
                                error?.message ?? 'Check your card details.',
                        };
                    }

                    return { paymentMethodId: paymentMethod.id };
                },
            }),
            [stripe],
        );

        const message = (field: CardField) => live[field] ?? errors[field];

        const errorLine = (field: CardField) =>
            message(field) ? (
                <span role="alert" className="text-caption text-danger">
                    {message(field)}
                </span>
            ) : null;

        return (
            <div className="flex flex-col gap-3" data-gateway="stripe">
                <div className="flex flex-col gap-1.5">
                    <span className="text-caption text-text-secondary">
                        Card number
                    </span>
                    <div
                        ref={numberHost}
                        className="qr-stripe-field"
                        data-field="card_number"
                    />
                    {errorLine('card_number')}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div className="flex flex-col gap-1.5">
                        <span className="text-caption text-text-secondary">
                            Expiry
                        </span>
                        <div
                            ref={expiryHost}
                            className="qr-stripe-field"
                            data-field="card_expiry"
                        />
                        {errorLine('card_expiry')}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <span className="text-caption text-text-secondary">
                            CVC
                        </span>
                        <div
                            ref={cvcHost}
                            className="qr-stripe-field"
                            data-field="card_cvc"
                        />
                        {errorLine('card_cvc')}
                    </div>
                </div>

                <p className="text-caption text-text-tertiary pt-1">
                    Card details never touch {restaurantName}’s servers.
                </p>
            </div>
        );
    },
);

type WalletOptions = {
    country: string;
    currency: string;
    label: string;
    amountMinor: number;
    onPaymentMethod: (event: PaymentRequestPaymentMethodEvent) => void;
};

/**
 * Apple Pay / Google Pay through the Payment Request API. Each button is
 * offered only when this browser and device can actually pay with it.
 */
export function useWallets(
    stripe: Stripe | null,
    { country, currency, label, amountMinor, onPaymentMethod }: WalletOptions,
) {
    const [request, setRequest] = useState<PaymentRequest | null>(null);
    const [available, setAvailable] = useState({
        applePay: false,
        googlePay: false,
    });
    const handler = useRef(onPaymentMethod);
    const amount = useRef(amountMinor);

    useEffect(() => {
        handler.current = onPaymentMethod;
    }, [onPaymentMethod]);

    useEffect(() => {
        if (!stripe) {
            return;
        }

        const paymentRequest = stripe.paymentRequest({
            country,
            currency: currency.toLowerCase(),
            total: { label, amount: amount.current },
            requestPayerName: false,
            requestPayerEmail: false,
        });

        paymentRequest.on('paymentmethod', (event) => handler.current(event));

        let active = true;

        void paymentRequest.canMakePayment().then((result) => {
            if (!active) {
                return;
            }

            setRequest(paymentRequest);
            setAvailable({
                applePay: Boolean(result?.applePay),
                googlePay: Boolean(result?.googlePay),
            });
        });

        return () => {
            active = false;
        };
    }, [stripe, country, currency, label]);

    // The tip and split change the total; keep the sheet in step.
    useEffect(() => {
        amount.current = amountMinor;
        request?.update({ total: { label, amount: amountMinor } });
    }, [request, label, amountMinor]);

    return {
        ...available,
        show: () => request?.show(),
    };
}
