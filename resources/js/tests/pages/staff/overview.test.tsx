import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ManagerOverview from '@/pages/staff/manager/overview';

type VisitOptions = {
    onStart?: () => void;
    onFinish?: () => void;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    only?: string[];
};

// Radix checkbox measures itself; jsdom has no ResizeObserver.
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

const post = vi.fn();
const patch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    router: {
        post: (...args: unknown[]) => post(...args),
        patch: (...args: unknown[]) => patch(...args),
        reload: vi.fn(),
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

const member = (overrides: Record<string, unknown>) => ({
    id: 1,
    name: 'Nadia Rahman',
    email: 'nadia@albustan.co',
    role: 'waiter',
    role_label: 'Waiter',
    job_title: 'Waiter',
    initials: 'NR',
    active: true,
    deactivated_at: null,
    tables: ['04', '12'],
    shift: { starts_at: '17:00', ends_at: '23:00' },
    order_count: 2,
    on_shift: true,
    ...overrides,
});

const props = {
    stats: {
        covers: 18,
        covers_last_week: 16,
        net_sales_minor: 20970,
        net_sales_last_week_minor: 20000,
        avg_per_cover_minor: 1165,
        avg_per_cover_last_week_minor: 1250,
        median_prep_minutes: 12,
        late_orders_count: 1,
        unpaid_total_minor: 12320,
        open_bills: 2,
        oldest_bill_minutes: 26,
    },
    orders: [],
    staff: [
        member({}),
        member({
            id: 3,
            name: 'Lin Wu',
            email: 'lin@albustan.co',
            initials: 'LW',
            active: false,
            deactivated_at: '2026-10-27T10:00:00Z',
            tables: [],
            on_shift: false,
        }),
    ],
    waiters: [{ id: 1, name: 'Nadia Rahman' }],
    tables: ['01', '02', '04', '07', '09', '12'],
    staffRoles: ['waiter', 'runner', 'cashier', 'kitchen'],
    now: Date.parse('2026-10-27T18:00:00Z'),
    currency: 'USD',
    warnMinutes: 14,
    lateMinutes: 25,
    pollIntervalMs: 8000,
};

beforeEach(() => {
    post.mockReset();
    patch.mockReset();
});
afterEach(cleanup);

describe('Manager overview staff management (#27)', () => {
    it('marks deactivated people and offers only reactivation', () => {
        render(<ManagerOverview {...props} />);

        expect(screen.getByText('Deactivated')).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'Actions for Lin Wu' }),
        );

        const menu = screen.getByRole('menu');

        expect(
            within(menu).getByRole('menuitem', { name: 'Reactivate' }),
        ).toBeInTheDocument();
        expect(
            within(menu).queryByRole('menuitem', { name: 'Deactivate' }),
        ).not.toBeInTheDocument();
    });

    it('posts a new waiter from the drawer without navigating away', () => {
        render(<ManagerOverview {...props} />);

        fireEvent.click(screen.getByRole('button', { name: /Add waiter/ }));

        fireEvent.change(screen.getByLabelText('Full name'), {
            target: { value: 'Omar Faruk' },
        });
        fireEvent.change(screen.getByLabelText('Work email'), {
            target: { value: 'omar@albustan.co' },
        });
        fireEvent.click(screen.getByRole('button', { name: '04' }));
        fireEvent.click(screen.getByRole('button', { name: '12' }));
        fireEvent.click(screen.getByRole('button', { name: 'Add to shift' }));

        expect(post).toHaveBeenCalledWith(
            '/app/staff',
            {
                name: 'Omar Faruk',
                role: 'waiter',
                tables: ['04', '12'],
                email: 'omar@albustan.co',
                send_invite: true,
            },
            expect.objectContaining({
                preserveState: true,
                only: expect.arrayContaining(['staff']),
            }),
        );
    });

    it('offers the set-password link as a checkbox, ticked by default', () => {
        render(<ManagerOverview {...props} />);

        fireEvent.click(screen.getByRole('button', { name: /Add waiter/ }));

        const invite = screen.getByRole('checkbox', {
            name: 'Send a set-password link now',
        });

        expect(invite).toHaveAttribute('aria-checked', 'true');

        fireEvent.click(invite);
        fireEvent.change(screen.getByLabelText('Full name'), {
            target: { value: 'Omar Faruk' },
        });
        fireEvent.change(screen.getByLabelText('Work email'), {
            target: { value: 'omar@albustan.co' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Add to shift' }));

        expect(post).toHaveBeenCalledWith(
            '/app/staff',
            expect.objectContaining({ send_invite: false }),
            expect.any(Object),
        );
    });

    it('shows server validation inline and keeps the drawer open', () => {
        post.mockImplementation(
            (_url: string, _data: unknown, options: VisitOptions) =>
                options.onError?.({
                    role: 'You can add waiters, runners, cashiers and kitchen staff only.',
                }),
        );

        render(<ManagerOverview {...props} />);

        fireEvent.click(screen.getByRole('button', { name: /Add waiter/ }));
        fireEvent.change(screen.getByLabelText('Full name'), {
            target: { value: 'Omar Faruk' },
        });
        fireEvent.change(screen.getByLabelText('Work email'), {
            target: { value: 'omar@albustan.co' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Add to shift' }));

        expect(
            screen.getByText(
                'You can add waiters, runners, cashiers and kitchen staff only.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add to shift' }),
        ).toBeInTheDocument();
    });

    it("edits through PATCH with the person's current tables preselected", () => {
        render(<ManagerOverview {...props} />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Actions for Nadia Rahman' }),
        );
        fireEvent.click(
            screen.getByRole('menuitem', { name: 'Edit and reassign tables' }),
        );
        fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));

        expect(patch).toHaveBeenCalledWith(
            '/app/staff/1',
            { name: 'Nadia Rahman', role: 'waiter', tables: ['04', '12'] },
            expect.any(Object),
        );
    });

    it('deactivates behind a dialog that restates the retention rule', () => {
        post.mockImplementation(
            (_url: string, _data: unknown, options: VisitOptions) =>
                options.onError?.({
                    member: 'Nadia Rahman still has 2 open orders. Reassign them first.',
                }),
        );

        render(<ManagerOverview {...props} />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Actions for Nadia Rahman' }),
        );
        fireEvent.click(screen.getByRole('menuitem', { name: 'Deactivate' }));

        const dialog = screen.getByRole('dialog');

        expect(dialog.textContent).toContain('order history stays');
        expect(dialog.textContent).toContain('back to the pool');

        fireEvent.click(
            within(dialog).getByRole('button', { name: 'Deactivate' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/app/staff/1/deactivate',
            {},
            expect.any(Object),
        );
        expect(within(dialog).getByRole('alert').textContent).toContain(
            'Reassign them first',
        );
    });
});
