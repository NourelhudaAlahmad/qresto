import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MealPage from '@/pages/guest/meal';

const visit = vi.fn();
const post = vi.fn();
const setData = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        visit: (...args: unknown[]) => visit(...args),
    },

    useForm: (initialData: Record<string, unknown>) => ({
        data: initialData,
        setData: (...args: unknown[]) => setData(...args),
        post: (...args: unknown[]) => post(...args),
        processing: false,
        errors: {},
    }),

    usePage: () => ({
        props: {
            locale: 'en',
            dir: 'ltr',
        },
    }),
}));

const translations = {
    back_to_menu: 'Back to menu',
    chef_pick: 'Chef pick',
    prep_time: ':minutes min',
    sold_out_message: 'This item is currently unavailable.',
    contains: 'Contains',
    no_allergens: 'No listed allergens',
    allergens_nearby: 'May contain',
    choose_size: 'Choose size',
    no_size_options: 'No size options',
    add_on: 'Add-ons',
    optional: 'Optional',
    sold_out: 'Sold out',
    no_addons: 'No add-ons',
    kitchen_note: 'Kitchen note',
    note_placeholder: 'Add a note',
    note_promise: 'We will do our best to follow your request.',
    add_to_order: 'Add to order',
};

const baseProps = {
    restaurant: {
        id: 1,
        name: 'Al Bustan',
        currency: 'USD',
    },

    table: {
        id: 1,
        number: '12',
    },

    item: {
        id: 10,
        name: 'Lamb kofta',
        description: 'Grilled lamb kofta with herbs.',
        category: 'Mains',
        price: {
            amount: 1800,
            currency: 'USD',
            formatted: '$18.00',
        },
        photo: null,
        prep_minutes: 15,
        available: true,
        tags: [],
        flag: false,
        variants: [],
        addons: [],
        allergens: [],
    },

    translations,
};

describe('Guest meal page', () => {
    beforeEach(() => {
        visit.mockClear();
        post.mockClear();
        setData.mockClear();
    });

    it('returns to the menu from the back button', () => {
        render(<MealPage {...baseProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Back to menu',
            }),
        );

        expect(visit).toHaveBeenCalledWith('/menu');
    });

    it('adds the meal to the cart', () => {
        render(<MealPage {...baseProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: /add to order/i,
            }),
        );

        expect(post).toHaveBeenCalledWith('/cart/lines', {
            preserveScroll: true,
        });
    });

    it('disables ordering when the meal is sold out', () => {
        render(
            <MealPage
                {...baseProps}
                item={{
                    ...baseProps.item,
                    available: false,
                }}
            />,
        );

        const addButton = screen.getByRole('button', {
            name: /add to order/i,
        });

        expect(addButton).toBeDisabled();

        fireEvent.click(addButton);

        expect(post).not.toHaveBeenCalled();
    });
});
