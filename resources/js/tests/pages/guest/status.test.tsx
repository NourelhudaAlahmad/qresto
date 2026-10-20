import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Status from '@/pages/guest/status';

const useLiveDataMock = vi.fn();

vi.mock('@/hooks/useLiveData', () => ({
    useLiveData: (...args: unknown[]) => useLiveDataMock(...args),
}));

vi.mock('@/components/locale-sync', () => ({
    LocaleSync: () => null,
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: ReactNode;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

const money = (
    amount: number,
): {
    amount: number;
    currency: string;
    formatted: string;
} => ({
    amount,
    currency: 'USD',
    formatted: `$${(amount / 100).toFixed(2)}`,
});

const baseProps = {
    order: {
        id: 1,
        code: 'A12',
        placed_at: '2026-10-04T18:00:00+03:00',
        currency: 'USD',
        table_number: '12',
    },
    assigned_waiter: {
        id: 7,
        name: 'Ahmad Saleh',
        initials: 'AS',
    },
    lines: [
        {
            id: 1,
            name: 'Lamb Kofta',
            qty: 2,
            total: money(3600),
        },
    ],
    receipt: {
        subtotal: money(3600),
        service_amount: money(0),
        tip_amount: money(0),
        discount_amount: money(0),
        total: money(3600),
    },
    live: {
        status: 'preparing' as const,
        stage: 'preparing' as const,
        open_requests: [],
        assigned_waiter: {
            id: 7,
            name: 'Ahmad Saleh',
            initials: 'AS',
        },
        events: [
            {
                status: 'placed',
                at: '2026-10-04T18:00:00+03:00',
                actor: null,
            },
            {
                status: 'preparing',
                at: '2026-10-04T18:05:00+03:00',
                actor: 'Ahmad Saleh',
            },
        ],
        eta_minutes: {
            min: 12,
            max: 17,
        },
        paid: false,
        payment_method: 'cash',
    },
    qresto: {
        live: {
            guest_status_interval: 10000,
        },
    },
};

beforeEach(() => {
    useLiveDataMock.mockClear();
});

describe('Guest order status', () => {
    it('renders the event trail in order with recorded times', () => {
        render(<Status {...baseProps} />);

        const placed = screen.getByText('Order placed');
        const preparing = screen.getByText('In the kitchen');
        const ready = screen.getByText('Ready at the pass');
        const served = screen.getByText('At your table');

        expect(
            placed.compareDocumentPosition(preparing) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        expect(
            preparing.compareDocumentPosition(ready) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        expect(
            ready.compareDocumentPosition(served) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        expect(
            screen.getByText(/Ahmad Saleh · ~12–17 min/),
        ).toBeInTheDocument();

        expect(screen.getAllByText(/12–17/).length).toBeGreaterThan(0);
    });

    it('shows unpaid cash payment state', () => {
        render(<Status {...baseProps} />);
        expect(screen.getByText('Unpaid · cash')).toBeInTheDocument();
    });

    it('shows paid card payment state', () => {
        render(
            <Status
                {...baseProps}
                live={{
                    ...baseProps.live,
                    status: 'paid',
                    stage: 'served',
                    eta_minutes: null,
                    paid: true,
                    payment_method: 'card',
                }}
            />,
        );

        expect(screen.getByText('Paid · card')).toBeInTheDocument();
    });

    it('shows the kitchen eta as a range and never a countdown', () => {
        render(<Status {...baseProps} />);

        expect(screen.getAllByText(/12–17/).length).toBeGreaterThan(0);
        expect(screen.queryByText(/countdown/i)).not.toBeInTheDocument();
    });

    it('removes the eta once the order is served', () => {
        render(
            <Status
                {...baseProps}
                live={{
                    ...baseProps.live,
                    status: 'served',
                    stage: 'served',
                    eta_minutes: null,
                }}
            />,
        );

        expect(screen.queryByText(/12–17/)).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'At your table',
            }),
        ).toBeInTheDocument();
    });

    it('shows the waiter from the live prop', () => {
        render(<Status {...baseProps} />);
        expect(
            screen.getByText('Ahmad Saleh is on your table'),
        ).toBeInTheDocument();
    });

    it('reflects a waiter change from the next live update', () => {
        const { rerender } = render(<Status {...baseProps} />);

        expect(
            screen.getByText('Ahmad Saleh is on your table'),
        ).toBeInTheDocument();

        rerender(
            <Status
                {...baseProps}
                live={{
                    ...baseProps.live,
                    assigned_waiter: {
                        id: 9,
                        name: 'Nadia Rahman',
                        initials: 'NR',
                    },
                }}
            />,
        );

        expect(
            screen.getByText('Nadia Rahman is on your table'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Ahmad Saleh is on your table'),
        ).not.toBeInTheDocument();
    });

    it('hides the waiter card when no waiter is assigned', () => {
        render(
            <Status
                {...baseProps}
                live={{
                    ...baseProps.live,
                    assigned_waiter: null,
                }}
            />,
        );

        expect(screen.queryByText(/is on your table/)).not.toBeInTheDocument();
    });

    it('polls only the volatile live prop at the configured interval', () => {
        render(<Status {...baseProps} />);

        expect(useLiveDataMock).toHaveBeenCalledWith(10000, {
            only: ['live'],
        });
    });

    it('returns to the menu when order more is selected', () => {
        render(<Status {...baseProps} />);

        const link = screen.getByRole('link', {
            name: 'Order more',
        });

        expect(link).toHaveAttribute('href', '/menu');
    });
});
