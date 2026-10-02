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
            onClick?: () => void;
        }>;
    }) => (
        <nav aria-label="Guest navigation">
            {items.map((item, index) => (
                <button key={index} type="button" onClick={item.onClick}>
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
    },

    table: {
        id: 12,
        number: '12',
    },

    session: {
        active: true,
    },

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

    it('opens the cart from the Order tab', () => {
        render(<Menu {...menuProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Order',
            }),
        );

        expect(visit).toHaveBeenCalledWith('/cart');
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
});
