import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Pass, { aggregates } from '@/pages/staff/kitchen/pass';

const post = vi.fn();
const get = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: {
        post: (...args: unknown[]) => post(...args),
        get: (...args: unknown[]) => get(...args),
        reload: vi.fn(),
        on: () => () => undefined,
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

const ticket = (
    id: number,
    code: string,
    status: 'pending' | 'preparing',
    minutes: number,
    lines: { qty: number; name: string; note?: string }[],
) => ({
    id,
    code,
    status,
    table: String(id).padStart(2, '0'),
    guest: 'Guest',
    fired_at: null,
    elapsed_minutes: minutes,
    lines: lines.map((line) => ({ note: null, station: 'grill', ...line })),
});

const props = {
    station: null,
    stations: ['grill', 'cold', 'sweet'],
    tickets: [
        ticket(12, '#A-1043', 'preparing', 14, [
            { qty: 2, name: 'Lamb kofta', note: 'sumac onions' },
            { qty: 1, name: 'Charred aubergine', note: 'no tahini' },
            { qty: 2, name: 'Mint lemonade' },
        ]),
        ticket(2, '#A-1045', 'preparing', 9, [
            { qty: 1, name: 'Whole sea bass', note: 'no chilli' },
            { qty: 1, name: 'Burnt-butter rice' },
        ]),
        ticket(4, '#A-1044', 'pending', 2, [
            { qty: 1, name: 'Chicken musakhan' },
            { qty: 2, name: 'Grilled flatbread', note: 'well charred' },
        ]),
        ticket(11, '#A-1046', 'pending', 1, [{ qty: 1, name: 'Fattoush' }]),
    ],
    sla: { warn_minutes: 14, late_minutes: 25 },
    pollIntervalMs: 4000,
    heartbeatIntervalMs: 30000,
    now: Date.now(),
};

beforeEach(() => {
    post.mockReset();
    get.mockReset();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('Kitchen pass (#33)', () => {
    it('heads the screen with live counts that match the tickets shown', () => {
        render(<Pass {...props} />);

        expect(aggregates(props.tickets)).toEqual({
            tickets: 4,
            items: 11,
            longest: 14,
        });
        expect(screen.getByTestId('pass-totals').textContent).toBe(
            '4 tickets · 11 items · longest 14 min',
        );
        expect(
            screen.getByText('Bump with the touch bar or the keypad'),
        ).toBeInTheDocument();
    });

    it('is a dark screen with nothing smaller than 15px', () => {
        const { container } = render(<Pass {...props} />);

        expect(container.querySelector('[data-surface="pass"]')).toHaveClass(
            'bg-[var(--ink-950)]',
        );
        // 12px caption, 11px micro and 13px label never appear on the pass.
        expect(
            container.querySelectorAll(
                '.text-caption, .text-micro, .text-label',
            ),
        ).toHaveLength(0);
        expect(container.querySelectorAll('[data-size="pass"]')).toHaveLength(
            4,
        );
    });

    it('shows items at 17px, quantities in clay and notes in saffron', () => {
        render(<Pass {...props} />);

        const kofta = screen.getByText('Lamb kofta').parentElement;

        expect(kofta).toHaveClass('text-title-3');
        expect(kofta?.firstElementChild).toHaveClass('text-[var(--clay-400)]');
        expect(screen.getByText('no tahini')).toHaveClass(
            'text-[var(--saffron-300)]',
            'text-body',
        );
    });

    it("bumps with the design's clay pill, which still names the next state", () => {
        render(<Pass {...props} />);

        const bump = screen.getByRole('button', {
            name: 'Bump #A-1044: start cooking (key 3)',
        });

        expect(bump).toHaveTextContent('Bump');
        expect(bump).toHaveClass(
            'bg-[var(--action-primary)]',
            'rounded-[var(--radius-pill)]',
            'w-full',
        );
        expect(
            screen.getByRole('button', { name: /Bump #A-1043: mark ready/ }),
        ).toBeInTheDocument();
    });

    it('outlines a ticket past its SLA, as the design does', () => {
        const { container } = render(<Pass {...props} />);

        expect(
            container.querySelector('[data-elapsed-level="warn"]'),
        ).toHaveClass('!border-[var(--saffron-500)]');
    });

    it('bumps a ticket off the grid at once, and brings it back if refused', () => {
        let finish: ((errors?: Record<string, string>) => void) | undefined;
        post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: {
                    onError?: (e: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                finish = (errors) => {
                    if (errors) {
                        options.onError?.(errors);
                    }

                    options.onFinish?.();
                };
            },
        );

        render(<Pass {...props} />);
        fireEvent.click(screen.getByRole('button', { name: /Bump #A-1044/ }));

        expect(post).toHaveBeenCalledWith(
            '/app/kds/tickets/4/bump',
            {},
            expect.objectContaining({ only: ['tickets', 'now'] }),
        );
        expect(screen.queryByText('#A-1044')).toBeNull();

        act(() => finish?.({ order: '#A-1044 is no longer on the pass.' }));

        expect(screen.getByText('#A-1044')).toBeInTheDocument();
    });

    it('bumps the nth ticket from the keypad', () => {
        render(<Pass {...props} />);

        // The first ticket answers to key 1…
        expect(
            screen.getByRole('button', { name: /Bump #A-1043.*key 1/ }),
        ).toBeInTheDocument();

        fireEvent.keyDown(window, { key: '1' });

        // …and leaves the grid when it is pressed.
        expect(post).toHaveBeenCalledWith(
            '/app/kds/tickets/12/bump',
            {},
            expect.any(Object),
        );
        expect(screen.queryByText('#A-1043')).toBeNull();
    });

    it('colours the elapsed time by the SLA', () => {
        const { container } = render(<Pass {...props} />);

        expect(
            container.querySelector('[data-elapsed-level="warn"]'),
        ).not.toBeNull();
    });

    it('filters to one station with big, touch-sized chips', () => {
        render(<Pass {...props} />);

        const grill = screen.getByRole('button', { name: 'grill' });

        expect(grill).toHaveClass('min-h-12');

        fireEvent.click(grill);

        expect(get).toHaveBeenCalledWith(
            '/app/pass',
            { station: 'grill' },
            expect.any(Object),
        );
    });

    it('reports itself to system health', () => {
        render(<Pass {...props} />);

        expect(fetch).toHaveBeenCalledWith(
            '/app/kds/heartbeat',
            expect.objectContaining({ method: 'POST' }),
        );
    });
});
