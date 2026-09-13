import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useLiveData } from '@/hooks/useLiveData';
import { useNewItemAlert } from '@/hooks/useNewItemAlert';

import Dashboard from './dashboard';

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
}));

vi.mock('@/routes', () => ({
    dashboard: () => '/dashboard',
}));

vi.mock('@/components/ui/placeholder-pattern', () => ({
    PlaceholderPattern: () => <div data-testid="placeholder-pattern" />,
}));

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
            />,
        );

        expect(screen.getByText('A-1001')).toBeInTheDocument();
        expect(screen.getByText('placed')).toBeInTheDocument();
    });

    it('polls live orders every 5 seconds with the current version', () => {
        const liveOrders = {
            items: [],
            version: '2026-09-13 12:00:00.000000:0',
        };

        render(<Dashboard liveOrders={liveOrders} />);

        expect(useLiveData).toHaveBeenLastCalledWith(5000, {
            only: ['liveOrders'],
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
});
