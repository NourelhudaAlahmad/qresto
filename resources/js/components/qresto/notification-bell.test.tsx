import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ago, NotificationBell } from './notification-bell';

const post = vi.fn();
const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => post(...args),
        visit: (...args: unknown[]) => visit(...args),
    },
}));

const notifications = [
    {
        id: 'a1',
        type: 'integration_failing',
        title: 'System health',
        message: 'Receipt printer, pass is failing: out of paper',
        url: '/app/system',
        read: false,
        created_at: new Date(Date.now() - 4 * 60_000).toISOString(),
    },
    {
        id: 'b2',
        type: 'order_voided',
        title: 'Order voided',
        message: 'Order #A-1044 was voided.',
        url: '/app/orders',
        read: true,
        created_at: new Date(Date.now() - 130 * 60_000).toISOString(),
    },
];

function respond(body: unknown, ok = true) {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok,
            status: ok ? 200 : 500,
            json: async () => body,
        }),
    );
}

beforeEach(() => {
    post.mockReset();
    visit.mockReset();
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('NotificationBell', () => {
    it('shows the unread count on a real button', () => {
        render(<NotificationBell unread={3} />);

        expect(
            screen.getByRole('button', { name: 'Notifications, 3 unread' }),
        ).toHaveAttribute('aria-expanded', 'false');
    });

    it('opens the list and loads it fresh', async () => {
        respond({ notifications, unread: 1 });

        render(<NotificationBell unread={1} />);
        fireEvent.click(screen.getByRole('button', { name: /Notifications/ }));

        expect(
            await screen.findByText('out of paper', { exact: false }),
        ).toBeInTheDocument();
        expect(screen.getByText('4 min ago')).toBeInTheDocument();
        expect(screen.getByText('2h 10m ago')).toBeInTheDocument();
        expect(document.querySelectorAll('[data-unread]')).toHaveLength(1);
        expect(fetch).toHaveBeenCalledWith(
            '/app/notifications',
            expect.objectContaining({ credentials: 'same-origin' }),
        );
    });

    it('marks an unread one read, then goes where it points', async () => {
        respond({ notifications, unread: 1 });
        post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess?: () => void },
            ) => options.onSuccess?.(),
        );

        render(<NotificationBell unread={1} />);
        fireEvent.click(screen.getByRole('button', { name: /Notifications/ }));
        fireEvent.click(
            await screen.findByRole('button', { name: /System health/ }),
        );

        expect(post).toHaveBeenCalledWith(
            '/app/notifications/a1/read',
            {},
            expect.objectContaining({ only: ['staffUnreadNotifications'] }),
        );
        expect(visit).toHaveBeenCalledWith('/app/system');
    });

    it('goes straight there for one already read', async () => {
        respond({ notifications, unread: 1 });

        render(<NotificationBell unread={1} />);
        fireEvent.click(screen.getByRole('button', { name: /Notifications/ }));
        fireEvent.click(
            await screen.findByRole('button', { name: /Order voided/ }),
        );

        expect(post).not.toHaveBeenCalled();
        expect(visit).toHaveBeenCalledWith('/app/orders');
    });

    it('marks all read', async () => {
        respond({ notifications, unread: 1 });

        render(<NotificationBell unread={1} />);
        fireEvent.click(screen.getByRole('button', { name: /Notifications/ }));
        fireEvent.click(
            await screen.findByRole('button', { name: 'Mark all read' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/app/notifications/read-all',
            {},
            expect.any(Object),
        );
        expect(document.querySelectorAll('[data-unread]')).toHaveLength(0);
    });

    it('says when there is nothing, and offers a retry when loading fails', async () => {
        respond({ notifications: [], unread: 0 });

        const { unmount } = render(<NotificationBell unread={0} />);
        fireEvent.click(screen.getByRole('button', { name: 'Notifications' }));

        expect(
            await screen.findByText('You’re all caught up.'),
        ).toBeInTheDocument();

        unmount();
        respond({}, false);

        render(<NotificationBell unread={0} />);
        fireEvent.click(screen.getByRole('button', { name: 'Notifications' }));

        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Try again' }),
            ).toBeInTheDocument(),
        );
    });

    it('reads time the way staff say it', () => {
        const now = Date.parse('2026-10-29T20:00:00Z');

        expect(ago('2026-10-29T19:59:30Z', now)).toBe('just now');
        expect(ago('2026-10-29T19:56:00Z', now)).toBe('4 min ago');
        expect(ago(null, now)).toBe('');
    });
});
