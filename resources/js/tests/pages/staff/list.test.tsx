import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import type { ComponentProps } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import OrderList, { filterOrders, sortOrders } from '@/pages/staff/orders/list';
import type { Ticket } from '@/pages/staff/orders/list';

const orders: Ticket[] = [
    {
        id: 1,
        code: 'A-1',
        status: 'placed',
        table: '10',
        guest_name: 'Ada',
        placed_at: '2026-10-15T11:00:00Z',
        total_minor: 1200,
        currency: 'TRY',
        paid: false,
        lines: [{ id: 101, qty: 1, name: 'Soup', note: null }],
    },
    {
        id: 2,
        code: 'A-2',
        status: 'pending',
        table: '2',
        guest_name: 'Ben',
        placed_at: '2026-10-15T09:00:00Z',
        total_minor: 3500,
        currency: 'TRY',
        paid: false,
        lines: [{ id: 102, qty: 1, name: 'Pasta', note: 'No pepper' }],
    },
    {
        id: 3,
        code: 'A-3',
        status: 'preparing',
        table: '7',
        guest_name: 'Cam',
        placed_at: '2026-10-15T10:00:00Z',
        total_minor: 700,
        currency: 'TRY',
        paid: false,
        lines: [],
    },
    {
        id: 4,
        code: 'A-4',
        status: 'ready',
        table: '3',
        guest_name: 'Dan',
        placed_at: '2026-10-15T12:00:00Z',
        total_minor: 9900,
        currency: 'TRY',
        paid: false,
        lines: [],
    },
    {
        id: 5,
        code: 'A-5',
        status: 'served',
        table: '1',
        guest_name: 'Eve',
        placed_at: '2026-10-15T08:00:00Z',
        total_minor: 5500,
        currency: 'TRY',
        paid: true,
        lines: [],
    },
];

function mount(overrides: Partial<ComponentProps<typeof OrderList>> = {}) {
    const onAdvance = vi.fn();
    const onVoid = vi.fn();
    const props = {
        orders,
        now: Date.parse('2026-10-15T12:30:00Z'),
        warnMinutes: 15,
        lateMinutes: 25,
        canAdvance: true,
        canTakePayment: false,
        busy: null,
        userId: 42,
        onAdvance,
        onVoid,
        ...overrides,
    };
    const view = render(<OrderList {...props} />);

    return { ...view, onAdvance, onVoid };
}

function tableCodes(): string[] {
    const table = screen.getByRole('table');

    return within(table)
        .getAllByRole('row')
        .slice(1)
        .map(
            (row) => within(row).getByRole('button').textContent?.trim() ?? '',
        );
}

beforeEach(() => window.localStorage.clear());
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Issue #24 waiter order list', () => {
    it('shows the same five orders in both table and phone card markup, oldest first', () => {
        mount();
        expect(tableCodes()).toEqual(['#A-5', '#A-2', '#A-3', '#A-1', '#A-4']);
        const table = screen.getByRole('table');
        const cards = document.querySelector('.min-\\[720px\\]\\:hidden');
        expect(cards).not.toBeNull();

        for (const order of orders) {
            expect(
                within(table).getByRole('button', { name: `#${order.code}` }),
            ).toBeTruthy();
            expect(cards?.textContent).toContain(`#${order.code}`);
        }
    });

    it('groups placed and pending into New; filters locally without a fetch', () => {
        const fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        mount();
        expect(screen.getByRole('button', { name: 'New (2)' })).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'New (2)' }));
        expect(tableCodes()).toEqual(['#A-2', '#A-1']);
        expect(fetchSpy).not.toHaveBeenCalled();
        expect(
            window.localStorage.getItem('qresto:waiter:42:list-filter'),
        ).toBe('new');
        expect(filterOrders(orders, 'preparing').map((o) => o.id)).toEqual([3]);
    });

    it('sorts age, table numerically and total descending without network requests', () => {
        const fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        mount();
        fireEvent.change(
            screen.getByRole('combobox', { name: 'Sort orders' }),
            { target: { value: 'table' } },
        );
        expect(tableCodes()).toEqual(['#A-5', '#A-2', '#A-4', '#A-3', '#A-1']);
        fireEvent.change(
            screen.getByRole('combobox', { name: 'Sort orders' }),
            { target: { value: 'total' } },
        );
        expect(tableCodes()).toEqual(['#A-4', '#A-5', '#A-2', '#A-1', '#A-3']);
        expect(window.localStorage.getItem('qresto:waiter:42:list-sort')).toBe(
            'total',
        );
        expect(fetchSpy).not.toHaveBeenCalled();
        expect(sortOrders(orders, 'age').map((o) => o.id)).toEqual([
            5, 2, 3, 1, 4,
        ]);
    });

    it('restores saved filters/sort for one user without leaking to another', () => {
        window.localStorage.setItem('qresto:waiter:42:list-sort', 'total');
        window.localStorage.setItem('qresto:waiter:42:list-filter', 'ready');
        const view = mount();
        expect(tableCodes()).toEqual(['#A-4']);
        expect(
            (
                screen.getByRole('combobox', {
                    name: 'Sort orders',
                }) as HTMLSelectElement
            ).value,
        ).toBe('total');
        view.unmount();
        mount({ userId: 43 });
        expect(tableCodes()).toHaveLength(5);
        expect(
            (
                screen.getByRole('combobox', {
                    name: 'Sort orders',
                }) as HTMLSelectElement
            ).value,
        ).toBe('age');
    });

    it('has responsive breakpoint markup: desktop table and phone card stack', () => {
        mount();
        const desktop = screen.getByRole('table').parentElement;
        expect(desktop?.className).toContain('min-[720px]:block');
        expect(desktop?.className).toContain('hidden');
        const phone = document.querySelector('.min-\\[720px\\]\\:hidden');
        expect(phone?.className).toContain('grid');
        expect(phone?.textContent).toContain('Table · Guest');
        expect(phone?.textContent).toContain('Total');
        expect(phone?.textContent).toContain('Age');
    });

    it('opens the ticket and calls the same advance callback', () => {
        const { onAdvance } = mount();
        fireEvent.click(
            within(screen.getByRole('table')).getByRole('button', {
                name: '#A-1',
            }),
        );
        expect(screen.getByRole('dialog')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: 'Accept order' }));
        expect(onAdvance).toHaveBeenCalledWith(orders[0]);
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('opens void from the ticket and delegates to existing void flow', () => {
        const { onVoid } = mount();
        fireEvent.click(
            within(screen.getByRole('table')).getByRole('button', {
                name: '#A-2',
            }),
        );
        fireEvent.click(screen.getByRole('button', { name: 'Void order' }));
        expect(onVoid).toHaveBeenCalledWith(orders[1]);
    });
});
