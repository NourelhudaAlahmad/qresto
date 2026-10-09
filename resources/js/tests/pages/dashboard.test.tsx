import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';

import Dashboard from '@/pages/dashboard';

vi.mock('@/hooks/useLiveData', () => ({
    useLiveData: vi.fn(() => ({
        isPaused: false,
        pause: vi.fn(),
        resume: vi.fn(),
    })),
}));

vi.mock('@/hooks/useNewItemAlert', () => ({
    useNewItemAlert: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    usePage: () => ({
        props: {
            qresto: {
                live: {
                    waiter_interval: 5000,
                },
            },
        },
    }),
}));

vi.mock('@/routes', () => ({
    dashboard: () => '/dashboard',
}));

vi.mock('@/components/ui/placeholder-pattern', () => ({
    PlaceholderPattern: () => <div data-testid="placeholder-pattern" />,
}));

const emptyServiceRequests = () => ({
    items: [],
    version: 'none:0',
});

describe('Dashboard', () => {
    it('renders the live orders count', () => {
        render(
            <Dashboard
                liveOrders={{
                    items: [
                        {
                            id: 1,
                            code: 'A-1001',
                            status: 'placed',
                        },
                        {
                            id: 2,
                            code: 'A-1002',
                            status: 'ready',
                        },
                    ],
                    version: '2026-09-13 12:00:00.000000:2',
                }}
                liveServiceRequests={emptyServiceRequests()}
            />,
        );

        expect(screen.getByText('2 active orders')).toBeInTheDocument();
    });

    it('renders live orders', () => {
        render(
            <Dashboard
                liveOrders={{
                    items: [
                        {
                            id: 1,
                            code: 'A-1001',
                            status: 'placed',
                        },
                    ],
                    version: '2026-09-13 12:00:00.000000:1',
                }}
                liveServiceRequests={emptyServiceRequests()}
            />,
        );

        expect(screen.getByText('A-1001')).toBeInTheDocument();
        expect(screen.getByText('placed')).toBeInTheDocument();
    });

    it('polls orders and service requests every 5 seconds', () => {
        const liveOrders = {
            items: [],
            version: '2026-09-13 12:00:00.000000:0',
        };

        render(
            <Dashboard
                liveOrders={liveOrders}
                liveServiceRequests={emptyServiceRequests()}
            />,
        );

        expect(useLiveData).toHaveBeenLastCalledWith(5000, {
            only: ['liveOrders', 'liveServiceRequests'],
            version: liveOrders.version,
        });
    });

    it('tracks new orders by id and passes the mute state', () => {
        const items = [
            {
                id: 1,
                code: 'A-1001',
                status: 'placed',
            },
            {
                id: 2,
                code: 'A-1002',
                status: 'ready',
            },
        ];

        render(
            <Dashboard
                liveOrders={{
                    items,
                    version: '2026-09-13 12:00:00.000000:2',
                }}
                liveServiceRequests={emptyServiceRequests()}
            />,
        );

        expect(useNewItemAlert).toHaveBeenLastCalledWith(
            items,
            expect.any(Function),
            {
                muted: false,
            },
        );
    });

    it('renders pending service requests and table numbers', () => {
        render(
            <Dashboard
                liveOrders={{
                    items: [],
                    version: 'none:0|2026-10-09 10:00:00.000000:2',
                }}
                liveServiceRequests={{
                    items: [
                        {
                            id: 1,
                            kind: 'water',
                            status: 'pending',
                            requested_at: '2026-10-09T10:00:00',
                            table: {
                                id: 12,
                                number: '12',
                                state: 'seated',
                            },
                        },
                        {
                            id: 2,
                            kind: 'bill',
                            status: 'pending',
                            requested_at: '2026-10-09T10:00:00',
                            table: {
                                id: 13,
                                number: '13',
                                state: 'bill_requested',
                            },
                        },
                    ],
                    version: '2026-10-09 10:00:00.000000:2',
                }}
            />,
        );

        expect(screen.getByText('2 pending requests')).toBeInTheDocument();
        expect(screen.getByText('Water')).toBeInTheDocument();
        expect(screen.getByText('Table 12')).toBeInTheDocument();
        expect(screen.getByText('Table 13')).toBeInTheDocument();
        expect(screen.getAllByText('Bill requested')).toHaveLength(2);
    });

    it('renders an empty service requests state', () => {
        render(
            <Dashboard
                liveOrders={{
                    items: [],
                    version: 'none:0',
                }}
                liveServiceRequests={emptyServiceRequests()}
            />,
        );

        expect(
            screen.getByText('No pending service requests.'),
        ).toBeInTheDocument();
    });
});
