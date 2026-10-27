import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Kanban from '@/pages/staff/orders/kanban';
import type { Ticket } from '@/pages/staff/orders/list';

const patch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            auth: { user: { id: 7 } },
            qresto: {
                sla: { warn_minutes: 14, late_minutes: 25 },
                live: { waiter_interval: 5000 },
            },
        },
    }),
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: {
        patch: (...args: unknown[]) => patch(...args),
        post: vi.fn(),
        reload: vi.fn(),
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

const minutesAgo = (minutes: number) =>
    new Date(Date.now() - minutes * 60_000).toISOString();

const orders: Ticket[] = [
    {
        id: 1,
        code: 'A-1044',
        status: 'pending',
        table: '04',
        guest_name: 'Dana',
        placed_at: minutesAgo(2),
        total_minor: 3100,
        currency: 'USD',
        paid: false,
        lines: [
            { id: 1, qty: 2, name: 'Grilled flatbread', note: 'well charred' },
        ],
    },
    {
        id: 2,
        code: 'A-1043',
        status: 'preparing',
        table: '12',
        guest_name: 'Rami',
        placed_at: minutesAgo(14),
        total_minor: 5435,
        currency: 'USD',
        paid: true,
        lines: [{ id: 2, qty: 2, name: 'Lamb kofta', note: 'sumac onions' }],
    },
    {
        id: 3,
        code: 'A-1041',
        status: 'served',
        table: '07',
        guest_name: 'Yara',
        placed_at: minutesAgo(26),
        total_minor: 9220,
        currency: 'USD',
        paid: false,
        lines: [],
    },
];

function mount() {
    return render(
        <Kanban
            orders={orders}
            canAdvance
            canTakePayment
            warnMinutes={14}
            lateMinutes={25}
        />,
    );
}

beforeEach(() => {
    window.localStorage.clear();
    patch.mockClear();
});
afterEach(cleanup);

describe('Waiter kanban on the design components', () => {
    it('renders every order as an OrderTicket in its column', () => {
        mount();

        const preparing = screen.getByRole('region', { name: 'Preparing' });

        expect(within(preparing).getByText('#A-1043')).toBeInTheDocument();
        expect(document.querySelectorAll('[data-elapsed-level]').length).toBe(
            3,
        );
    });

    it('carries the design verbs and the SLA colours', () => {
        mount();

        expect(
            screen.getByRole('button', { name: 'Start cooking' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Mark ready' }),
        ).toBeInTheDocument();

        expect(
            document.querySelector('[data-status="preparing"]'),
        ).toHaveAttribute('data-elapsed-level', 'warn');
        expect(
            document.querySelector('[data-status="served"]'),
        ).toHaveAttribute('data-elapsed-level', 'late');
    });

    it('bakes guest notes into the ticket in saffron', () => {
        mount();

        expect(screen.getByText('sumac onions')).toHaveClass(
            'text-[var(--saffron-700)]',
        );
    });

    it('advances optimistically through the shared endpoint', () => {
        mount();

        fireEvent.click(screen.getByRole('button', { name: 'Start cooking' }));

        expect(patch).toHaveBeenCalledWith(
            '/app/orders/1/advance',
            {},
            expect.objectContaining({ only: ['orders'] }),
        );
        expect(
            within(screen.getByRole('region', { name: 'Preparing' })).getByText(
                '#A-1044',
            ),
        ).toBeInTheDocument();
    });

    it('points served tickets at the floor map for payment', () => {
        mount();

        expect(
            screen.getByText('Take payment from the floor map'),
        ).toBeInTheDocument();
    });
});
