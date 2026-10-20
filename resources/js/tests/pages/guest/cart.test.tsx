import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CartPage from '@/pages/guest/cart';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: ReactNode;
        [key: string]: unknown;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),

    router: {
        visit: (...args: unknown[]) => visit(...args),
        reload: vi.fn(),
        patch: vi.fn(),
    },

    usePage: () => ({
        props: {
            locale: 'en',
            dir: 'ltr',
        },
    }),
}));

const money = (amount: number, formatted: string) => ({
    amount,
    currency: 'USD',
    formatted,
});

const cartProps = {
    restaurant: {
        id: 1,
        name: 'Al Bustan',
        currency: 'USD',
    },

    table: {
        id: 1,
        number: '12',
    },

    guest: {
        name: 'Rami',
        first_name: 'Rami',
    },

    session: {
        id: 1,
        active: true,
    },

    latest_order_id: null,

    open_requests: [],

    cart: {
        id: 1,
        note: null,

        lines: [
            {
                id: 1,
                menu_item_id: 10,
                name: 'Lamb kofta',
                variant: null,
                qty: 1,
                unit_price: money(1800, '$18.00'),
                configured_unit_price: money(1800, '$18.00'),
                variant_price_delta: money(0, '$0.00'),
                line_total: money(1800, '$18.00'),
                note: null,
                photo_url: null,
                addons: [],
            },
        ],

        subtotal: money(1800, '$18.00'),
        service_pct: '10',
        service_amount: money(180, '$1.80'),
        total: money(1980, '$19.80'),
    },

    undo_window_seconds: 30,

    translations: {
        title: 'Your order',
        empty: 'Your order is empty',
        back_to_menu: 'Back to menu',
        subtotal: 'Subtotal',
        quantity: 'Quantity',
        note: 'Note',
    },
};

describe('Guest cart page', () => {
    beforeEach(() => {
        visit.mockClear();
        window.localStorage.clear();
    });

    it('opens checkout from choose how to pay', () => {
        render(<CartPage {...cartProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Choose how to pay',
            }),
        );

        expect(visit).toHaveBeenCalledWith('/checkout');
    });

    it('links back to the menu', () => {
        render(<CartPage {...cartProps} />);

        expect(
            screen.getByRole('link', {
                name: 'Back to menu',
            }),
        ).toHaveAttribute('href', '/menu');
    });

    it('links to the menu to add another item', () => {
        render(<CartPage {...cartProps} />);

        expect(
            screen.getByRole('link', {
                name: 'Add something else',
            }),
        ).toHaveAttribute('href', '/menu');
    });

    it('shows the four navigation tabs with Order active', () => {
        render(<CartPage {...cartProps} />);

        const navigation = screen.getByRole('navigation');

        expect(navigation).toBeInTheDocument();

        expect(
            screen.getByRole('button', { name: 'Menu' }),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', { name: /Order/ }),
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: /Status/ })).toBeDisabled();

        expect(screen.getByRole('button', { name: 'Help' })).toBeEnabled();
    });

    it('navigates to menu and saves the last tab', () => {
        render(<CartPage {...cartProps} />);

        fireEvent.click(screen.getByRole('button', { name: 'Menu' }));

        expect(visit).toHaveBeenCalledWith('/menu');

        expect(window.localStorage.getItem('qresto_last_tab_1')).toBe('menu');
    });

    it('navigates to the latest order status when available', () => {
        render(<CartPage {...cartProps} latest_order_id={42} />);

        fireEvent.click(screen.getByRole('button', { name: /Status/ }));

        expect(visit).toHaveBeenCalledWith('/orders/42/status');

        expect(window.localStorage.getItem('qresto_last_tab_1')).toBe('status');
    });

    it('disables Status and Help when the session is inactive', () => {
        render(
            <CartPage
                {...cartProps}
                session={{
                    id: 1,
                    active: false,
                }}
                latest_order_id={42}
            />,
        );

        expect(screen.getByRole('button', { name: /Status/ })).toBeDisabled();

        expect(screen.getByRole('button', { name: 'Help' })).toBeDisabled();
    });
});
