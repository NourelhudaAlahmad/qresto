import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Kanban, { COLUMN_PAGE_SIZE } from '@/pages/staff/orders/kanban';
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
    it('sums the board up in the shared copy', () => {
        mount();

        // Three open tickets, two still with the kitchen, the oldest 26 min.
        expect(
            screen.getByText(
                '3 open · 2 waiting on the kitchen · oldest 26 min',
            ),
        ).toBeInTheDocument();
    });

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

describe('column headings stay on screen (#65)', () => {
    it('pins every heading, with its dot, label and count, under the topbar', () => {
        mount();

        for (const [name, count] of [
            ['New', '1'],
            ['Preparing', '1'],
            ['Ready', '0'],
            ['Served', '1'],
        ]) {
            const column = screen.getByRole('region', { name });
            const header = column.querySelector('[data-column-header]');

            // The heading is the column's first child, so it pins for as
            // long as the column is on screen.
            expect(column.firstElementChild).toBe(header);
            expect(header).toHaveClass(
                'sticky',
                'top-[var(--topbar-h)]',
                'z-10',
                // Solid: cards pass behind it without bleeding through.
                'bg-[var(--surface-page)]',
            );
            expect(
                header?.querySelector('[aria-hidden="true"]'),
            ).not.toBeNull();
            expect(
                within(header as HTMLElement).getByRole('heading'),
            ).toHaveTextContent(name);
            expect(header).toHaveTextContent(count);
        }
    });
});

describe('long columns render in batches (#66)', () => {
    const served = (count: number, from = 100): Ticket[] =>
        Array.from({ length: count }, (_, index) => ({
            id: from + index,
            code: `S-${from + index}`,
            status: 'served' as const,
            table: '07',
            guest_name: 'Yara',
            placed_at: minutesAgo(40 + index),
            total_minor: 1000,
            currency: 'USD',
            paid: false,
            lines: [],
        }));

    const props = {
        canAdvance: true,
        canTakePayment: true,
        warnMinutes: 14,
        lateMinutes: 25,
    };

    const servedColumn = () => screen.getByRole('region', { name: 'Served' });
    const rendered = () =>
        servedColumn().querySelectorAll('[data-elapsed-level]').length;

    it('sizes the first batch per column, Served smallest', () => {
        expect(COLUMN_PAGE_SIZE.served).toBe(10);
        expect(COLUMN_PAGE_SIZE.preparing).toBeGreaterThan(
            COLUMN_PAGE_SIZE.served,
        );
    });

    it('draws only the first batch, and the header keeps the true total', () => {
        render(<Kanban {...props} orders={served(13)} />);

        expect(rendered()).toBe(10);
        expect(
            servedColumn().querySelector('[data-column-header]'),
        ).toHaveTextContent('13');
        expect(
            within(servedColumn()).getByRole('button', {
                name: 'Show 3 more',
            }),
        ).toBeInTheDocument();
    });

    it('brings in the rest, and the header still reads 13', () => {
        render(<Kanban {...props} orders={served(13)} />);

        fireEvent.click(
            within(servedColumn()).getByRole('button', { name: 'Show 3 more' }),
        );

        expect(rendered()).toBe(13);
        expect(servedColumn().querySelector('[data-load-more]')).toBeNull();
        expect(
            servedColumn().querySelector('[data-column-header]'),
        ).toHaveTextContent('13');
    });

    it('loads the next batch as the end of the column scrolls into view', () => {
        const observers: {
            callback: IntersectionObserverCallback;
            target?: Element;
        }[] = [];

        vi.stubGlobal(
            'IntersectionObserver',
            class {
                constructor(public callback: IntersectionObserverCallback) {
                    observers.push(this);
                }

                observe(target: Element) {
                    (this as unknown as { target: Element }).target = target;
                }

                disconnect() {}
            },
        );

        render(<Kanban {...props} orders={served(25)} />);
        expect(rendered()).toBe(10);

        const observer = observers.at(-1)!;
        act(() =>
            observer.callback(
                [
                    {
                        isIntersecting: true,
                        target: observer.target,
                    } as IntersectionObserverEntry,
                ],
                observer as unknown as IntersectionObserver,
            ),
        );

        expect(rendered()).toBe(20);

        vi.unstubAllGlobals();
    });

    it('keeps the loaded tickets when a poll brings new orders', () => {
        const { rerender } = render(<Kanban {...props} orders={served(13)} />);

        fireEvent.click(
            within(servedColumn()).getByRole('button', { name: 'Show 3 more' }),
        );
        expect(rendered()).toBe(13);

        // A poll: a fresh array, one more ticket served.
        rerender(<Kanban {...props} orders={served(14)} />);

        expect(rendered()).toBe(14);
        expect(
            servedColumn().querySelector('[data-column-header]'),
        ).toHaveTextContent('14');
    });

    it('leaves a column with fewer than a batch unchanged', () => {
        render(<Kanban {...props} orders={served(9)} />);

        expect(rendered()).toBe(9);
        expect(servedColumn().querySelector('[data-load-more]')).toBeNull();
    });
});
