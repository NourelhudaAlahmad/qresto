import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Menu from '@/pages/guest/menu';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        visit: (...args: unknown[]) => visit(...args),
    },

    usePage: () => ({
        props: {
            locale: 'en',
            dir: 'ltr',
        },
    }),
}));

vi.mock('@/components/qresto/menu-item-card', () => ({
    MenuItemCard: ({
        name,
        onClick,
    }: {
        name: string;
        onClick?: () => void;
    }) => (
        <button type="button" onClick={onClick}>
            {name}
        </button>
    ),
}));

vi.mock('@/components/qresto/tab-bar', () => ({
    TabBar: ({
        items,
    }: {
        items: Array<{
            label: ReactNode;
            badge?: ReactNode;
            disabled?: boolean;
            onClick?: () => void;
        }>;
    }) => (
        <nav aria-label="Guest navigation">
            {items.map((item, index) => (
                <button
                    key={index}
                    type="button"
                    onClick={item.onClick}
                    disabled={item.disabled}
                >
                    {item.label}
                </button>
            ))}
        </nav>
    ),
}));

const menuProps = {
    restaurant: {
        id: 1,
        name: 'Al Bustan',
        currency: 'USD',
        phone: '+966 11 123 4567',
        closes_today_at: '22:30',
    },

    table: {
        id: 12,
        number: '12',
    },

    session: {
        id: 1,
        active: true,
    },

    open_requests: [],
    latest_order_id: null,
    restore_guest_tab: false,

    categories: [
        {
            id: 1,
            name: 'Mains',
            sort: 1,
            items: [
                {
                    id: 10,
                    name: 'Lamb kofta',
                    description: 'Charcoal grilled lamb.',
                    price: {
                        amount: 1800,
                        currency: 'USD',
                        formatted: '$18.00',
                    },
                    photo: null,
                    tags: ['Halal'],
                    allergens: [
                        {
                            id: 1,
                            name: 'Dairy',
                            may_contain: false,
                        },
                    ],
                    available: true,
                    prep_minutes: 15,
                    flag: "Chef's pick",
                },
            ],
        },
        {
            id: 2,
            name: 'Sides',
            sort: 2,
            items: [
                {
                    id: 20,
                    name: 'Charred aubergine',
                    description: 'Smoked aubergine with herbs.',
                    price: {
                        amount: 1250,
                        currency: 'USD',
                        formatted: '$12.50',
                    },
                    photo: null,
                    tags: ['Vegan'],
                    allergens: [],
                    available: true,
                    prep_minutes: 8,
                    flag: null,
                },
            ],
        },
    ],

    cart: {
        count: 2,
        subtotal: {
            amount: 3050,
            currency: 'USD',
            formatted: '$30.50',
        },
    },
};

describe('Guest menu page', () => {
    beforeEach(() => {
        visit.mockClear();
        window.localStorage.clear();
    });

    it('shows the real kitchen closing time, and nothing when closed today', () => {
        const { unmount } = render(<Menu {...menuProps} />);

        expect(screen.getByText(/Kitchen closes/).textContent).toBe(
            'Kitchen closes 22:30',
        );

        unmount();

        render(
            <Menu
                {...menuProps}
                restaurant={{ ...menuProps.restaurant, closes_today_at: null }}
            />,
        );

        expect(screen.queryByText(/Kitchen closes/)).not.toBeInTheDocument();
    });

    it('opens a menu item', () => {
        render(<Menu {...menuProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Lamb kofta',
            }),
        );

        expect(visit).toHaveBeenCalledWith('/menu/10');
    });

    it('opens the cart from the view order button', () => {
        render(<Menu {...menuProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: /view order/i,
            }),
        );

        expect(visit).toHaveBeenCalledWith('/cart');
    });

    it('opens the cart from the Order tab and saves the last tab', () => {
        render(<Menu {...menuProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Order',
            }),
        );

        expect(visit).toHaveBeenCalledWith('/cart');

        expect(window.localStorage.getItem('qresto_last_tab_1')).toBe('order');
    });

    it('filters menu items by search', () => {
        render(<Menu {...menuProps} />);

        fireEvent.change(
            screen.getByRole('searchbox', {
                name: /search the menu/i,
            }),
            {
                target: {
                    value: 'aubergine',
                },
            },
        );

        expect(
            screen.queryByRole('button', {
                name: 'Lamb kofta',
            }),
        ).not.toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: 'Charred aubergine',
            }),
        ).toBeInTheDocument();
    });

    it('shows the empty state when no menu items match', () => {
        render(<Menu {...menuProps} />);

        fireEvent.change(
            screen.getByRole('searchbox', {
                name: /search the menu/i,
            }),
            {
                target: {
                    value: 'pizza',
                },
            },
        );

        expect(screen.getByText('Nothing found')).toBeInTheDocument();
    });

    it('disables Help when the table session is inactive', () => {
        render(<Menu {...menuProps} session={{ id: 1, active: false }} />);

        expect(screen.getByRole('button', { name: 'Help' })).toBeDisabled();
    });

    it('disables Status when there is no order', () => {
        render(<Menu {...menuProps} />);

        expect(screen.getByRole('button', { name: 'Status' })).toBeDisabled();
    });

    it('opens the latest order status and saves the last tab', () => {
        render(<Menu {...menuProps} latest_order_id={42} />);

        const statusButton = screen.getByRole('button', {
            name: 'Status',
        });

        expect(statusButton).toBeEnabled();

        fireEvent.click(statusButton);

        expect(visit).toHaveBeenCalledWith('/orders/42/status');

        expect(window.localStorage.getItem('qresto_last_tab_1')).toBe('status');
    });

    it('restores the Order tab after QR confirmation', () => {
        window.localStorage.setItem('qresto_last_tab_1', 'order');

        render(<Menu {...menuProps} restore_guest_tab={true} />);

        expect(visit).toHaveBeenCalledWith('/cart');
    });

    it('restores the Status tab after QR confirmation', () => {
        window.localStorage.setItem('qresto_last_tab_1', 'status');

        render(
            <Menu
                {...menuProps}
                restore_guest_tab={true}
                latest_order_id={42}
            />,
        );

        expect(visit).toHaveBeenCalledWith('/orders/42/status');
    });

    it('does not restore the last tab when the session is inactive', () => {
        window.localStorage.setItem('qresto_last_tab_1', 'order');

        render(
            <Menu
                {...menuProps}
                session={{ id: 1, active: false }}
                restore_guest_tab={true}
            />,
        );

        expect(visit).not.toHaveBeenCalled();
    });

    it('does not restore the last tab on normal menu navigation', () => {
        window.localStorage.setItem('qresto_last_tab_1', 'status');

        render(
            <Menu
                {...menuProps}
                restore_guest_tab={false}
                latest_order_id={42}
            />,
        );

        expect(visit).not.toHaveBeenCalled();
    });

    it('does not restore Status when there is no order', () => {
        window.localStorage.setItem('qresto_last_tab_1', 'status');

        render(
            <Menu
                {...menuProps}
                restore_guest_tab={true}
                latest_order_id={null}
            />,
        );

        expect(visit).not.toHaveBeenCalled();
    });
    it('opens Help with allergen guidance, restaurant phone and waiter assistance', () => {
        render(<Menu {...menuProps} />);

        fireEvent.click(screen.getByRole('button', { name: 'Help' }));

        expect(
            screen.getByRole('dialog', { name: 'Help' }),
        ).toBeInTheDocument();

        expect(screen.getByText('Allergen information')).toBeInTheDocument();

        expect(
            screen.getByRole('link', { name: menuProps.restaurant.phone }),
        ).toHaveAttribute('href', `tel:${menuProps.restaurant.phone}`);

        expect(
            screen.getByRole('button', { name: 'English' }),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', { name: 'العربية' }),
        ).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Call waiter' }));

        expect(
            screen.getByRole('dialog', { name: 'How can we help?' }),
        ).toBeInTheDocument();
    });
});
