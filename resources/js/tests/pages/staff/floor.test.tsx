import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import Floor from '@/pages/staff/floor';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            qresto: { live: { floor_interval: 8000 } },
            capabilities: ['see_own_tables', 'take_payment'],
        },
    }),
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: { post: vi.fn(), patch: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

vi.mock('@/hooks/use-mobile', () => ({ useIsMobile: () => false }));

const table = (
    id: number,
    state: 'free' | 'seated' | 'ordered' | 'bill',
    extra: Record<string, unknown> = {},
) => ({
    id,
    number: String(id).padStart(2, '0'),
    state,
    seats: 4,
    party_size: 0,
    seated_at: null,
    open_bill_minor: 0,
    open_order_count: 0,
    pending_requests: 0,
    has_bill_request: false,
    currency: 'USD',
    ...extra,
});

const tables = [
    table(1, 'free'),
    table(2, 'seated'),
    table(4, 'ordered', {
        open_bill_minor: 3100,
        waiter: { id: 9, name: 'Omar Faruk' },
    }),
    table(7, 'bill', { open_bill_minor: 9220, has_bill_request: true }),
];

afterEach(cleanup);

describe('Floor map on the design components', () => {
    it('renders one TableTile per table with the occupancy state', () => {
        render(<Floor tables={tables} />);

        expect(document.querySelectorAll('[data-state]').length).toBe(4);
        expect(document.querySelector('[data-state="bill"]')).toHaveClass(
            'animate-pulse',
        );
        expect(screen.getByText('$92.20')).toBeInTheDocument();
    });

    it('shows who has each table tonight', () => {
        render(<Floor tables={tables} />);

        expect(screen.getByText('· Omar F.')).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: 'Open table 04, Ordered, Omar Faruk',
            }),
        ).toBeInTheDocument();
    });

    it('legend chips carry live counts and filter the grid locally', () => {
        render(<Floor tables={tables} />);

        fireEvent.click(
            screen.getByRole('button', { name: /Bill requested \(1\)/ }),
        );

        expect(document.querySelectorAll('[data-state]').length).toBe(1);
    });
});
