import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import CheckoutPage from '@/pages/guest/checkout';

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: {
        post: (...args: unknown[]) => post(...args),
        delete: vi.fn(),
        visit: vi.fn(),
    },
    usePage: () => ({ props: { errors: {} } }),
}));

// A stand-in for Stripe.js: the page only talks to it through these calls.
type ElementMock = {
    type: string;
    options: Record<string, unknown>;
    mount: ReturnType<typeof vi.fn>;
    on: ReturnType<typeof vi.fn>;
    destroy: ReturnType<typeof vi.fn>;
};

const created: ElementMock[] = [];
const createPaymentMethod = vi.fn();
const handleNextAction = vi.fn();
const walletShow = vi.fn();
let walletSupport: { applePay: boolean; googlePay: boolean } | null = null;
let walletHandler: ((event: unknown) => void) | undefined;

const stripeMock = {
    elements: vi.fn(() => ({
        create: (type: string, options: Record<string, unknown>) => {
            const element: ElementMock = {
                type,
                options,
                mount: vi.fn(),
                on: vi.fn(),
                destroy: vi.fn(),
            };
            created.push(element);

            return element;
        },
    })),
    createPaymentMethod: (...args: unknown[]) => createPaymentMethod(...args),
    handleNextAction: (...args: unknown[]) => handleNextAction(...args),
    paymentRequest: vi.fn(() => ({
        on: (_name: string, handler: (event: unknown) => void) => {
            walletHandler = handler;
        },
        canMakePayment: () => Promise.resolve(walletSupport),
        show: walletShow,
        update: vi.fn(),
    })),
};

vi.mock('@stripe/stripe-js', () => ({
    loadStripe: vi.fn(() => Promise.resolve(stripeMock)),
}));

const money = (amount: number) => ({
    amount,
    currency: 'USD',
    formatted: `$${(amount / 100).toFixed(2)}`,
});

const props = {
    restaurant: { id: 1, name: 'Al Bustan', currency: 'USD' },
    table: { id: 1, number: '12' },
    cart: {
        id: 1,
        subtotal: money(5000),
        service_pct: '12.5',
        service_amount: money(625),
        discount_amount: money(0),
        total: money(5625),
    },
    payment_methods: ['card', 'wallet', 'cash', 'pos'],
    tip_presets: [0, 10, 12.5, 15],
    promo: { code: null, discount: money(0) },
    gateway: {
        intent_id: null as string | null,
        status: null as string | null,
        '3ds_required': false,
        order_id: null as number | null,
        payment_id: null as number | null,
        client_secret: null as string | null,
        driver: 'stripe',
        publishable_key: 'pk_test_public',
        country: 'US',
    },
};

beforeEach(() => {
    created.length = 0;
    post.mockReset();
    createPaymentMethod.mockReset();
    handleNextAction.mockReset();
    walletShow.mockReset();
    walletSupport = null;
    walletHandler = undefined;
});

afterEach(() => vi.clearAllTimers());

async function renderStripe(overrides: Partial<typeof props> = {}) {
    const view = render(<CheckoutPage {...props} {...overrides} />);

    await waitFor(() => expect(created.length).toBeGreaterThan(0));

    return view;
}

const form = () => document.querySelector('form') as HTMLFormElement;

describe('Checkout on Stripe (#35)', () => {
    it('mounts Stripe card fields in QResto inputs instead of raw card inputs', async () => {
        await renderStripe();

        expect(created.map((element) => element.type)).toEqual([
            'cardNumber',
            'cardExpiry',
            'cardCvc',
        ]);
        expect(document.querySelector('[autocomplete="cc-number"]')).toBeNull();
        expect(
            document.querySelector('[data-field="card_number"]'),
        ).toHaveClass('qr-stripe-field');
        expect(
            screen.getByText('Card details never touch Al Bustan’s servers.', {
                exact: false,
            }),
        ).toBeInTheDocument();
    });

    it('styles the iframes from QResto tokens with the QResto focus ring', async () => {
        await renderStripe();

        const options = created[0].options as {
            style: { base: { fontSize: string; fontFamily: string } };
            classes: Record<string, string>;
        };

        expect(options.style.base.fontSize).toBe('15px');
        // No Link button inside the field.
        expect(created[0].options).toMatchObject({ disableLink: true });
        expect(options.style.base.fontFamily).not.toBe('');
        expect(options.classes).toEqual({
            base: 'qr-stripe-field',
            focus: 'qr-stripe-field--focus',
            invalid: 'qr-stripe-field--invalid',
        });
    });

    it('sends only a PaymentMethod id, never card details', async () => {
        createPaymentMethod.mockResolvedValue({
            paymentMethod: { id: 'pm_card_visa' },
        });

        await renderStripe();
        await act(async () => {
            fireEvent.submit(form());
        });

        expect(createPaymentMethod).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'card' }),
        );

        const [url, data] = post.mock.calls[0];

        expect(url).toBe('/checkout');
        expect(data).toMatchObject({
            method: 'card',
            payment_method: 'pm_card_visa',
        });
        expect(data).not.toHaveProperty('card_number');
        expect(data).not.toHaveProperty('card_cvc');
    });

    it('shows Stripe\u2019s card problem inline and does not submit', async () => {
        createPaymentMethod.mockResolvedValue({
            error: {
                code: 'invalid_cvc',
                message: 'Your card’s security code is incomplete.',
            },
        });

        await renderStripe();
        await act(async () => {
            fireEvent.submit(form());
        });

        expect(post).not.toHaveBeenCalled();
        expect(
            screen.getByText('Your card’s security code is incomplete.'),
        ).toBeInTheDocument();
    });

    it('runs 3-D Secure in the page, then confirms', async () => {
        handleNextAction.mockResolvedValue({});

        render(
            <CheckoutPage
                {...props}
                gateway={{
                    ...props.gateway,
                    '3ds_required': true,
                    order_id: 77,
                    payment_id: 9,
                    client_secret: 'pi_3ds_secret_abc',
                }}
            />,
        );

        await waitFor(() =>
            expect(handleNextAction).toHaveBeenCalledWith({
                clientSecret: 'pi_3ds_secret_abc',
            }),
        );
        await waitFor(() =>
            expect(post).toHaveBeenCalledWith(
                '/checkout/confirm',
                { order_id: 77, confirmed: true },
                expect.any(Object),
            ),
        );
    });

    it('stays on the page with the bank\u2019s message when 3-D Secure fails', async () => {
        handleNextAction.mockResolvedValue({
            error: { message: 'We are unable to authenticate your payment.' },
        });

        render(
            <CheckoutPage
                {...props}
                gateway={{
                    ...props.gateway,
                    '3ds_required': true,
                    order_id: 77,
                    client_secret: 'pi_3ds_secret_abc',
                }}
            />,
        );

        expect(
            await screen.findByText(
                'We are unable to authenticate your payment.',
            ),
        ).toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('offers Apple Pay only where the device supports it', async () => {
        walletSupport = { applePay: true, googlePay: false };

        await renderStripe();

        const applePay = await screen.findByRole('button', {
            name: 'Apple Pay',
        });

        expect(screen.queryByRole('button', { name: 'Google Pay' })).toBeNull();

        fireEvent.click(applePay);
        expect(walletShow).toHaveBeenCalled();

        // The wallet hands back a PaymentMethod, paid like a card.
        const complete = vi.fn();

        act(() =>
            walletHandler?.({ paymentMethod: { id: 'pm_wallet' }, complete }),
        );

        expect(post).toHaveBeenCalledWith(
            '/checkout',
            expect.objectContaining({
                method: 'wallet',
                payment_method: 'pm_wallet',
            }),
            expect.any(Object),
        );
    });

    it('hides wallet buttons entirely when none is available', async () => {
        walletSupport = null;

        await renderStripe();
        await act(async () => undefined);

        expect(screen.queryByRole('button', { name: 'Apple Pay' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Google Pay' })).toBeNull();
    });
});
