import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminSystem from '@/pages/staff/admin/system';

type VisitOptions = {
    onError?: (errors: Record<string, string>) => void;
    onSuccess?: () => void;
    onFinish?: () => void;
    only?: string[];
};

const patch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { patch: (...args: unknown[]) => patch(...args) },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

const cell = (granted: boolean, reason: string | null = null) => ({
    granted,
    locked: reason !== null,
    reason,
});

const props = {
    summary: {
        revenue: {
            mtd_minor: 6900000,
            settled_minor: 7000000,
            refunds_minor: 100000,
            fees_minor: 76000,
            net_of_fees_minor: 6824000,
        },
        payment_split: {
            app_minor: 4970000,
            cash_minor: 1500000,
            pos_minor: 530000,
            app_share: 71,
        },
        menu: { live_count: 42, sold_out: 3, scheduled: 1 },
        failed_payments: { count: 2, recovered: 2 },
    },
    matrix: {
        roles: [
            { key: 'waiter', label: 'Waiter' },
            { key: 'runner', label: 'Runner' },
            { key: 'cashier', label: 'Cashier' },
            { key: 'kitchen', label: 'Kitchen' },
            { key: 'manager', label: 'Manager' },
            { key: 'admin', label: 'Admin' },
        ],
        rows: [
            {
                capability: 'edit_menu_prices',
                label: 'Edit menu & prices',
                cells: {
                    waiter: cell(false),
                    runner: cell(false),
                    cashier: cell(false),
                    kitchen: cell(false),
                    manager: cell(false),
                    admin: cell(
                        true,
                        'You cannot remove a capability from your own role.',
                    ),
                },
            },
        ],
    },
    audits: [
        {
            id: 1,
            actor: 'Yusuf Aziz',
            role: 'Manager',
            capability: 'Financial reports · export',
            granted: true,
            at: '2026-10-27T17:02:00Z',
        },
    ],
    restaurant: {
        name: 'Al Bustan',
        service_charge_pct: 12.5,
        currency: 'USD',
        languages: ['English', 'العربية'],
        tables_count: 12,
    },
    health: [
        {
            key: 'gateway',
            label: 'Payment gateway',
            status: 'test' as const,
            detail: 'test driver',
        },
    ],
    currencyCode: 'USD',
};

beforeEach(() => patch.mockReset());
afterEach(cleanup);

describe('Super admin dashboard (#28)', () => {
    it('leads with month-to-date revenue on an inverse card', () => {
        render(<AdminSystem {...props} />);

        const card = screen.getByText('Month to date').closest('[data-tone]');

        expect(card).toHaveAttribute('data-tone', 'inverse');
        expect(card?.textContent).toContain('$68,240');
        expect(
            screen.getByRole('progressbar', { name: 'Paid in app' }),
        ).toHaveAttribute('aria-valuenow', '71');
    });

    it('pins the capability column and scrolls the grid sideways', () => {
        render(<AdminSystem {...props} />);

        const region = screen.getByRole('region', {
            name: 'Permission matrix',
        });

        expect(region).toHaveClass('overflow-x-auto');
        expect(
            screen.getByRole('rowheader', { name: 'Edit menu & prices' }),
        ).toHaveClass('sticky', 'start-0');
    });

    it('toggles optimistically and rolls back on a refusal', () => {
        patch.mockImplementation((...args: unknown[]) => {
            const options = args[2] as VisitOptions | undefined;

            options?.onError?.({ capability: 'Nope.' });
            options?.onFinish?.();
        });

        render(<AdminSystem {...props} />);

        const toggle = screen.getByRole('switch', {
            name: 'Edit menu & prices for Manager',
        });

        expect(toggle).toHaveAttribute('aria-checked', 'false');

        fireEvent.click(toggle);

        expect(patch).toHaveBeenCalledWith(
            '/app/system/permissions',
            { role: 'manager', capability: 'edit_menu_prices', granted: true },
            expect.objectContaining({ only: ['matrix', 'audits'] }),
        );
        expect(toggle).toHaveAttribute('aria-checked', 'false');
    });

    it('draws cells as the design does and locks the ones you may not revoke', () => {
        render(<AdminSystem {...props} />);

        const admin = screen.getByRole('switch', {
            name: 'Edit menu & prices for Admin',
        });
        const manager = screen.getByRole('switch', {
            name: 'Edit menu & prices for Manager',
        });

        expect(admin).toHaveTextContent('✓');
        expect(admin).toHaveClass('text-[var(--herb-500)]');
        expect(admin).toHaveAttribute('aria-disabled', 'true');
        expect(manager).toHaveTextContent('—');
        expect(manager).toHaveClass('text-[var(--ink-300)]');

        fireEvent.click(admin);

        expect(patch).not.toHaveBeenCalled();
    });

    it("shows the design's four columns: Waiter, Kitchen, Manager, Admin", () => {
        render(<AdminSystem {...props} />);

        expect(
            screen.getAllByRole('columnheader').map((th) => th.textContent),
        ).toEqual(['Capability', 'Waiter', 'Kitchen', 'Manager', 'Admin']);
    });

    it('restates the next-sign-in rule and lists recent changes', () => {
        render(<AdminSystem {...props} />);

        expect(document.body.textContent).toContain(
            'appears in that role’s sidebar on their next sign-in',
        );
        expect(screen.getByText('Yusuf Aziz')).toBeInTheDocument();
    });
});
