import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MealPage from '@/pages/guest/meal';
import { expectNoCriticalA11y } from '@/tests/a11y';

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
    add_on: 'Add-ons',
    optional: 'Optional',
    sold_out: 'Sold out',
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

describe('option sections (#62)', () => {
    const usd = (amount: number) => ({
        amount,
        currency: 'USD',
        formatted: `$${(amount / 100).toFixed(2)}`,
    });

    const variants = [
        { id: 1, label: 'Single', price_delta: usd(0), is_default: true },
        { id: 2, label: 'Sharing', price_delta: usd(1400), is_default: false },
    ];

    const addons = [
        { id: 5, label: 'Extra bread', price_delta: usd(200), available: true },
    ];

    const withOptions = (options: {
        variants?: typeof variants;
        addons?: typeof addons;
    }) => ({ ...baseProps, item: { ...baseProps.item, ...options } });

    // The thin rules between the screen's sections.
    const rules = (container: HTMLElement) =>
        container.querySelectorAll('.bg-border-default.h-px');

    it('shows neither section, nor an empty box, for a dish with no options', () => {
        const { container } = render(<MealPage {...baseProps} />);

        expect(screen.queryByText('Choose size')).toBeNull();
        expect(screen.queryByText('Add-ons')).toBeNull();
        expect(screen.queryByText(/No size options|No add-ons/)).toBeNull();
        // Only the rule before the kitchen note is left: no orphan.
        expect(rules(container)).toHaveLength(1);
        expect(screen.getByText('Kitchen note')).toBeInTheDocument();
    });

    it('shows only the size section for a dish with sizes and no add-ons', () => {
        const { container } = render(
            <MealPage {...withOptions({ variants })} />,
        );

        expect(screen.getByText('Choose size')).toBeInTheDocument();
        expect(screen.getByText('Sharing')).toBeInTheDocument();
        expect(screen.queryByText('Add-ons')).toBeNull();
        expect(rules(container)).toHaveLength(2);
    });

    it('shows both sections for a dish with sizes and add-ons', () => {
        const { container } = render(
            <MealPage {...withOptions({ variants, addons })} />,
        );

        expect(screen.getByText('Choose size')).toBeInTheDocument();
        expect(screen.getByText('Add-ons')).toBeInTheDocument();
        expect(screen.getByText('Extra bread')).toBeInTheDocument();
        expect(rules(container)).toHaveLength(3);
    });
});

describe('accessibility (#36)', () => {
    it('has no critical axe violations', async () => {
        const { container } = render(<MealPage {...baseProps} />);

        await expectNoCriticalA11y(container);
    });
});
