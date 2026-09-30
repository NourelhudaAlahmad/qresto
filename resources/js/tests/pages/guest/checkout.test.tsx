import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CheckoutPage from '@/pages/guest/checkout';

class ResizeObserverMock {
    observe() {}
    unobserve() {}
    disconnect() {}
}

vi.stubGlobal('ResizeObserver', ResizeObserverMock);

const post = vi.fn();
const destroy = vi.fn();

let currentSharedProps: {
    errors: Record<string, string>;
} = {
    errors: {},
};

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        post: (...args: unknown[]) => post(...args),
        delete: (...args: unknown[]) => destroy(...args),
    },

    usePage: () => ({
        props: currentSharedProps,
    }),
}));

const checkoutProps = {
    restaurant: {
        id: 1,
        name: 'Al Bustan',
        currency: 'USD',
    },

    table: {
        id: 1,
        number: '12',
    },

    cart: {
        id: 1,

        subtotal: {
            amount: 5000,
            currency: 'USD',
            formatted: '$50.00',
        },

        service_pct: '12.5',

        service_amount: {
            amount: 625,
            currency: 'USD',
            formatted: '$6.25',
        },

        discount_amount: {
            amount: 0,
            currency: 'USD',
            formatted: '$0.00',
        },

        total: {
            amount: 5625,
            currency: 'USD',
            formatted: '$56.25',
        },
    },

    payment_methods: ['card', 'cash', 'pos'],

    tip_presets: [0, 10, 12.5, 15],

    promo: {
        code: null,
        discount: {
            amount: 0,
            currency: 'USD',
            formatted: '$0.00',
        },
    },

    gateway: {
        intent_id: null as string | null,
        status: null as string | null,
        '3ds_required': false,
        order_id: null as number | null,
        payment_id: null as number | null,
        client_secret: null as string | null,
    },
};

function renderCheckout(overrides: Partial<typeof checkoutProps> = {}) {
    return render(<CheckoutPage {...checkoutProps} {...overrides} />);
}

function getPaymentForm(): HTMLFormElement {
    const button = screen.getByRole('button', {
        name: /^Pay /i,
    });

    const form = button.closest('form');

    if (!(form instanceof HTMLFormElement)) {
        throw new Error('Checkout form was not found.');
    }

    return form;
}

describe('Guest checkout page', () => {
    beforeEach(() => {
        post.mockClear();
        destroy.mockClear();

        currentSharedProps = {
            errors: {},
        };
    });

    it('renders wallet buttons and the three payment methods', () => {
        renderCheckout();

        expect(
            screen.getByRole('button', {
                name: /Apple Pay/i,
            }),
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: /Google Pay/i,
            }),
        ).toBeDisabled();

        expect(screen.getByText('Pay now by card')).toBeInTheDocument();

        expect(screen.getByText('Cash to the waiter')).toBeInTheDocument();

        expect(screen.getByText('Card at the table')).toBeInTheDocument();
    });

    it('renders the inline card form for card payment', () => {
        renderCheckout();

        expect(screen.getByText('Card number')).toBeInTheDocument();

        expect(screen.getByText('Expiry')).toBeInTheDocument();

        expect(screen.getByText('CVC')).toBeInTheDocument();

        expect(
            screen.getByText("Card details never touch Al Bustan's servers."),
        ).toBeInTheDocument();
    });

    it('updates the total when a tip is selected without moving summary rows', () => {
        renderCheckout();

        const subtotal = screen.getByText('Subtotal');
        const service = screen.getByText('Service 12.5%');
        const tip = screen.getByText('Tip');
        const total = screen.getByText('Total');

        expect(subtotal.compareDocumentPosition(service)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );

        expect(service.compareDocumentPosition(tip)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );

        expect(tip.compareDocumentPosition(total)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: '10%',
            }),
        );

        expect(screen.getByText('Tip 10%')).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: /Pay \$61\.25/i,
            }),
        ).toBeInTheDocument();

        const updatedSubtotal = screen.getByText('Subtotal');
        const updatedService = screen.getByText('Service 12.5%');
        const updatedTip = screen.getByText('Tip 10%');
        const updatedTotal = screen.getByText('Total');

        expect(updatedSubtotal.compareDocumentPosition(updatedService)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );

        expect(updatedService.compareDocumentPosition(updatedTip)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );

        expect(updatedTip.compareDocumentPosition(updatedTotal)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING,
        );
    });

    it('shows the deferred-payment banner and changes the CTA for cash', () => {
        renderCheckout();

        fireEvent.click(screen.getByText('Cash to the waiter'));

        expect(screen.getByText('Pay after ordering')).toBeInTheDocument();

        expect(
            screen.getByText(/waiter sees this order as unpaid/i),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: /Send to the kitchen.*\$56\.25/i,
            }),
        ).toBeInTheDocument();

        expect(
            screen.getByText(
                'You settle up with Nadia at the end of the meal.',
            ),
        ).toBeInTheDocument();
    });

    it('shows the deferred-payment banner for card at the table', () => {
        renderCheckout();

        fireEvent.click(screen.getByText('Card at the table'));

        expect(screen.getByText('Pay after ordering')).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: /Send to the kitchen.*\$56\.25/i,
            }),
        ).toBeInTheDocument();
    });

    it('opens an even split and shows the current share', () => {
        renderCheckout();

        fireEvent.click(
            screen.getByRole('button', {
                name: /Split the bill/i,
            }),
        );

        expect(screen.getByText('Split evenly')).toBeInTheDocument();

        expect(screen.getByText('Your share')).toBeInTheDocument();

        expect(screen.getByText('$28.13')).toBeInTheDocument();

        expect(
            screen.getByText(/Everyone at Table 12 gets a link/i),
        ).toBeInTheDocument();

        expect(screen.getByText(/Unpaid shares stay on/i)).toBeInTheDocument();
    });

    it('opens the promo panel and posts the promo code', () => {
        renderCheckout();

        fireEvent.click(
            screen.getByRole('button', {
                name: /Promo code/i,
            }),
        );

        const input = screen.getByRole('textbox', {
            name: /Promo code/i,
        });

        fireEvent.change(input, {
            target: {
                value: 'SAVE10',
            },
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: /Apply/i,
            }),
        );

        expect(post).toHaveBeenCalledWith(
            '/checkout/promo',
            {
                promo_code: 'SAVE10',
            },
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
            }),
        );
    });

    it('submits card details inline with the checkout request', () => {
        renderCheckout();

        fireEvent.change(screen.getByPlaceholderText('4242 4242 4242 4242'), {
            target: {
                value: '4242424242424242',
            },
        });

        fireEvent.change(screen.getByPlaceholderText('09 / 28'), {
            target: {
                value: '0928',
            },
        });

        fireEvent.change(screen.getByPlaceholderText('123'), {
            target: {
                value: '123',
            },
        });

        fireEvent.submit(getPaymentForm());

        expect(post).toHaveBeenCalledTimes(1);

        expect(post).toHaveBeenCalledWith(
            '/checkout',
            expect.objectContaining({
                method: 'card',
                tip_pct: 0,
                split_ways: 1,
                card_number: '4242424242424242',
                card_expiry: '09 / 28',
                card_cvc: '123',
                idempotency_key: expect.any(String),
            }),
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
            }),
        );
    });

    it('preserves card values and focuses CVC when a decline error is returned', () => {
        currentSharedProps = {
            errors: {
                card_cvc: 'Your card was declined.',
            },
        };

        renderCheckout();

        const cardNumber = screen.getByPlaceholderText('4242 4242 4242 4242');
        const cvc = screen.getByPlaceholderText('123');

        fireEvent.change(cardNumber, {
            target: {
                value: '4000000000000002',
            },
        });

        fireEvent.change(cvc, {
            target: {
                value: '123',
            },
        });

        expect(cardNumber).toHaveValue('4000 0000 0000 0002');

        expect(cvc).toHaveValue('123');

        expect(cvc).toHaveFocus();

        expect(screen.getByText('Your card was declined.')).toBeInTheDocument();
    });

    it('renders 3-D Secure inside the checkout page and confirms it inline', () => {
        renderCheckout({
            gateway: {
                ...checkoutProps.gateway,
                '3ds_required': true,
                order_id: 77,
            },
        });

        expect(screen.getByText('Verify your payment')).toBeInTheDocument();

        expect(screen.queryByText('Pay now by card')).not.toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: /Complete authentication/i,
            }),
        );

        expect(post).toHaveBeenCalledWith(
            '/checkout/confirm',
            {
                order_id: 77,
                confirmed: true,
            },
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
    });

    it('submits split ways with the checkout request', () => {
        renderCheckout();

        fireEvent.click(
            screen.getByRole('button', {
                name: /Split the bill/i,
            }),
        );

        const splitPanel = screen.getByText('Split evenly').closest('div');

        expect(splitPanel).not.toBeNull();

        const plusButtons = screen
            .getAllByRole('button')
            .filter(
                (button) =>
                    button
                        .getAttribute('aria-label')
                        ?.toLowerCase()
                        .includes('increase') ?? false,
            );

        if (plusButtons.length > 0) {
            fireEvent.click(plusButtons[0]);
        } else {
            const panel =
                screen.getByText('Split evenly').parentElement?.parentElement;

            if (panel) {
                const buttons = within(panel).getAllByRole('button');
                const incrementButton = buttons.at(-1);

                if (incrementButton) {
                    fireEvent.click(incrementButton);
                }
            }
        }

        fireEvent.submit(getPaymentForm());

        expect(post).toHaveBeenCalledWith(
            '/checkout',
            expect.objectContaining({
                split_ways: 3,
            }),
            expect.any(Object),
        );
    });
});
