import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Cashier from '@/pages/staff/cashier';
import type { Ticket } from '@/pages/staff/orders/list';

// The floor module checks the viewport when it loads.
vi.hoisted(() => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
            addListener: () => undefined,
            removeListener: () => undefined,
            dispatchEvent: () => false,
        }),
    });
});

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { auth: { user: { id: 9 } } } }),
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: {
        post: vi.fn(),
        patch: vi.fn(),
        reload: vi.fn(),
        on: () => () => undefined,
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

const order = (
    id: number,
    code: string,
    status: Ticket['status'],
    total: number,
    tableId: number,
) => ({
    id,
    code,
    status,
    table: String(tableId).padStart(2, '0'),
    table_id: tableId,
    guest_name: 'Guest',
    placed_at: new Date(Date.now() - 20 * 60_000).toISOString(),
    total_minor: total,
    currency: 'USD',
    paid: false,
    lines: [],
});

const props = {
    orders: [
        order(41, 'A-1041', 'served', 9220, 7),
        order(44, 'A-1044', 'pending', 3100, 4),
    ],
    canTakePayment: true,
    warnMinutes: 14,
    lateMinutes: 25,
};

beforeEach(() => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({ ok: true, json: async () => null }),
    );
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('Cashier view (#34)', () => {
    it('puts the outstanding total first', () => {
        render(<Cashier {...props} />);

        expect(screen.getByText('$123.20')).toBeInTheDocument();
        expect(screen.getByText('2 unpaid orders')).toBeInTheDocument();
    });

    it('reuses the S4 order list, with no kitchen actions', () => {
        render(<Cashier {...props} />);

        expect(screen.getByRole('table')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', {
                name: /Accept order|Start cooking|Mark ready/,
            }),
        ).toBeNull();
    });

    it('opens the floor checkout for a served order', () => {
        render(<Cashier {...props} />);

        fireEvent.click(
            within(screen.getByRole('table')).getByRole('button', {
                name: '#A-1041',
            }),
        );
        fireEvent.click(screen.getByRole('button', { name: 'Take payment' }));

        expect(fetch).toHaveBeenCalledWith('/app/tables/7', expect.any(Object));
        expect(
            screen.getByRole('dialog', { name: /Table 07 · A-1041/ }),
        ).toBeInTheDocument();
    });

    it('offers no payment on an order the kitchen has not served', () => {
        render(<Cashier {...props} />);

        fireEvent.click(
            within(screen.getByRole('table')).getByRole('button', {
                name: '#A-1044',
            }),
        );

        expect(
            screen.queryByRole('button', { name: 'Take payment' }),
        ).toBeNull();
    });
});
