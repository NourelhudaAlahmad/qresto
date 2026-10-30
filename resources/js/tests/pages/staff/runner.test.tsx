import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Runner from '@/pages/staff/kitchen/runner';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: {
        post: (...args: unknown[]) => post(...args),
        reload: vi.fn(),
        on: () => () => undefined,
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

const props = {
    tickets: [
        {
            id: 42,
            code: '#A-1042',
            status: 'ready' as const,
            table: '02',
            guest: 'Iris',
            elapsed_minutes: 9,
            lines: [
                { qty: 1, name: 'Fattoush', note: null },
                { qty: 1, name: 'Cardamom coffee', note: null },
            ],
        },
    ],
    pollIntervalMs: 4000,
};

beforeEach(() => post.mockReset());
afterEach(cleanup);

describe('Runner view (#34)', () => {
    it('reads across a room: one column, the code and table at 60px', () => {
        const { container } = render(<Runner {...props} />);

        expect(container.querySelector('ol')).toHaveClass('flex-col');
        expect(screen.getByText('#A-1042')).toHaveClass('text-[3.75rem]');
        expect(screen.getByText('Table 02').closest('div')).toHaveClass(
            'text-[3.75rem]',
        );
        expect(container.querySelector('[data-size="runner"]')).not.toBeNull();
    });

    it('has one action per ticket, Collected, through the pass bump', () => {
        render(<Runner {...props} />);

        const collected = screen.getByRole('button', {
            name: /Collected #A-1042/,
        });

        expect(collected).toHaveTextContent('Collected');

        fireEvent.click(collected);

        expect(post).toHaveBeenCalledWith(
            '/app/kds/tickets/42/bump',
            {},
            expect.objectContaining({ only: ['tickets', 'now'] }),
        );
        expect(screen.queryByText('#A-1042')).toBeNull();
    });

    it('says when nothing is waiting', () => {
        render(<Runner {...props} tickets={[]} />);

        expect(
            screen.getByText('Nothing waiting at the pass.'),
        ).toBeInTheDocument();
    });
});
