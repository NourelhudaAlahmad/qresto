import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import MenuManagement, {
    lastSaved,
    photoProblem,
} from '@/pages/staff/admin/menu';

const post = vi.fn();
const put = vi.fn();
const patch = vi.fn();
const listeners: Record<string, (event: unknown) => void> = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: {
        post: (...args: unknown[]) => post(...args),
        put: (...args: unknown[]) => put(...args),
        patch: (...args: unknown[]) => patch(...args),
        delete: vi.fn(),
        on: (event: string, callback: (event: unknown) => void) => {
            listeners[event] = callback;

            return () => delete listeners[event];
        },
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

const dish = (id: number, name: string, extra = {}) => ({
    id,
    menu_category_id: 2,
    name,
    description: 'Off the coals',
    price: '18.00',
    price_formatted: '$18.00',
    prep_minutes: 14,
    available: true,
    chef_flag: false,
    dietary_tags: [],
    photo: null,
    thumb: null,
    sold_today: 6,
    allergens: [],
    variants: [],
    addons: [],
    updated_at: '2026-10-29T18:42:00Z',
    updated_by: 'Yusuf Aziz',
    ...extra,
});

const props = {
    currency: 'USD',
    categories: [
        { id: 1, name: 'Small plates', dish_count: 0 },
        { id: 2, name: 'From the grill', dish_count: 2 },
    ],
    dishes: [dish(10, 'Lamb kofta'), dish(11, 'Whole sea bass')],
    allergens: [{ id: 1, name: 'Sesame' }],
    photoRules: { min_width: 1200, ratio: '4:3' },
};

beforeEach(() => {
    post.mockReset();
    put.mockReset();
    patch.mockReset();
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

function openGrill() {
    fireEvent.click(screen.getByRole('button', { name: /From the grill/ }));
}

describe('Menu management (#31)', () => {
    it('lays out three panels on desktop', () => {
        render(<MenuManagement {...props} />);

        expect(
            screen.getByRole('navigation', { name: 'Categories' }),
        ).toBeInTheDocument();
        expect(screen.getByText('drag to reorder')).toBeInTheDocument();
        expect(screen.getByText('Pick a dish to edit')).toBeInTheDocument();
    });

    it('saves availability immediately, with no Save press', () => {
        render(<MenuManagement {...props} />);
        openGrill();

        fireEvent.click(
            screen.getByRole('switch', { name: 'Whole sea bass available' }),
        );

        expect(patch).toHaveBeenCalledWith(
            '/app/menu/items/11/availability',
            { available: false },
            expect.objectContaining({ only: ['dishes', 'categories'] }),
        );
        expect(put).not.toHaveBeenCalled();
        expect(post).not.toHaveBeenCalled();
    });

    it('everything else waits for Save', () => {
        render(<MenuManagement {...props} />);
        openGrill();
        fireEvent.click(screen.getByRole('button', { name: 'Lamb kofta' }));

        fireEvent.change(screen.getByLabelText('Price'), {
            target: { value: '19.50' },
        });

        expect(post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/app/menu/items/10',
            expect.objectContaining({ price: '19.50', _method: 'put' }),
            expect.any(Object),
        );
    });

    it('warns before leaving an unsaved edit, and stays if told to', () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        render(<MenuManagement {...props} />);
        openGrill();
        fireEvent.click(screen.getByRole('button', { name: 'Lamb kofta' }));
        fireEvent.change(screen.getByLabelText('Price'), {
            target: { value: '19.50' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Whole sea bass' }));

        expect(confirm).toHaveBeenCalled();
        expect(screen.getByLabelText('Price')).toHaveValue('19.50');

        // An Inertia navigation is stopped the same way.
        const event = {
            detail: { visit: { method: 'get', only: [] } },
            preventDefault: vi.fn(),
        };
        listeners.before?.(event);

        expect(event.preventDefault).toHaveBeenCalled();
    });

    it('shows who saved last, and when', () => {
        expect(lastSaved(dish(1, 'x'))).toMatch(
            /^Last saved \d\d:\d\d by Yusuf Aziz$/,
        );
        expect(lastSaved(null)).toBeNull();
    });

    it('checks the 4:3, 1200px photo rule before uploading', () => {
        expect(photoProblem(1600, 1200, 1200)).toBeNull();
        expect(photoProblem(800, 600, 1200)).toContain('1200px wide');
        expect(photoProblem(1600, 900, 1200)).toContain('4:3');
    });

    it('closes the editor after creating a dish, so Save cannot duplicate it', () => {
        post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess?: () => void },
            ) => options?.onSuccess?.(),
        );

        render(<MenuManagement {...props} />);
        openGrill();
        fireEvent.click(screen.getByRole('button', { name: /New dish/ }));
        fireEvent.change(screen.getByLabelText('Name'), {
            target: { value: 'Mixed grill' },
        });
        fireEvent.change(screen.getByLabelText('Price'), {
            target: { value: '32.00' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/app/menu/items',
            expect.objectContaining({ name: 'Mixed grill' }),
            expect.any(Object),
        );
        expect(screen.queryByRole('button', { name: 'Save' })).toBeNull();
        expect(screen.getByText('Pick a dish to edit')).toBeInTheDocument();
    });

    it('puts delete behind a dialog', () => {
        render(<MenuManagement {...props} />);
        openGrill();
        fireEvent.click(screen.getByRole('button', { name: 'Lamb kofta' }));

        fireEvent.click(
            screen.getByRole('button', { name: 'Delete Lamb kofta' }),
        );

        expect(screen.getByRole('dialog').textContent).toContain(
            'Past orders keep its name and price',
        );
    });
});
