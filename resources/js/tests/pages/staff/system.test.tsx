import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminSystem, { lastSeen } from '@/pages/staff/admin/system';

type VisitOptions = {
    onError?: (errors: Record<string, string>) => void;
    onSuccess?: () => void;
    onFinish?: () => void;
    only?: string[];
};

const patch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePoll: () => ({ start: vi.fn(), stop: vi.fn(), polling: true }),
    Link: ({
        href,
        children,
        className,
    }: {
        href: string;
        children: React.ReactNode;
        className?: string;
    }) => (
        <a href={href} className={className}>
            {children}
        </a>
    ),
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
            id: 1,
            kind: 'gateway' as const,
            name: 'Payment gateway',
            health: 'operational' as const,
            detail: 'operational · test driver',
            last_heartbeat_at: new Date(Date.now() - 20_000).toISOString(),
        },
        {
            id: 2,
            kind: 'kds' as const,
            name: 'Kitchen display',
            health: 'unknown' as const,
            detail: null,
            last_heartbeat_at: new Date(Date.now() - 9 * 60_000).toISOString(),
        },
        {
            id: 3,
            kind: 'printer' as const,
            name: 'Receipt printer, pass',
            health: 'failing' as const,
            detail: 'out of paper',
            last_heartbeat_at: null,
        },
    ],
    currencyCode: 'USD',
};

beforeEach(() => patch.mockReset());
afterEach(cleanup);

describe('lastSeen', () => {
    it('reads like a person would say it', () => {
        const now = Date.parse('2026-10-28T20:00:00Z');

        expect(lastSeen(null, now)).toBe('never');
        expect(lastSeen('2026-10-28T19:59:40Z', now)).toBe('just now');
        expect(lastSeen('2026-10-28T19:56:00Z', now)).toBe('4 min ago');
        expect(lastSeen('2026-10-28T17:50:00Z', now)).toBe('2h 10m ago');
    });
});

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

    it('links the restaurant card to the settings screen', () => {
        render(<AdminSystem {...props} />);

        expect(
            screen.getByRole('link', {
                name: 'Edit details · reprint QR codes',
            }),
        ).toHaveAttribute('href', '/app/settings/restaurant');
    });

    it('derives each health row and says when it was last seen', () => {
        render(<AdminSystem {...props} />);

        const rows = document.querySelectorAll('[data-health]');

        expect([...rows].map((row) => row.getAttribute('data-health'))).toEqual(
            ['operational', 'unknown', 'failing'],
        );
        expect(rows[0].textContent).toContain('last seen just now');
        expect(rows[1].textContent).toContain('stopped reporting');
        expect(rows[1].textContent).toContain('last seen 9 min ago');
        expect(rows[2].textContent).toContain('out of paper');
        expect(rows[2].textContent).toContain('last seen never');
        expect(rows[2]).toHaveClass('bg-[var(--danger-bg)]');
    });

    it("shows all six roles, the design's four in their order", () => {
        render(<AdminSystem {...props} />);

        expect(
            screen.getAllByRole('columnheader').map((th) => th.textContent),
        ).toEqual([
            'Capability',
            'Waiter',
            'Runner',
            'Cashier',
            'Kitchen',
            'Manager',
            'Admin',
        ]);
    });

    it('restates the next-sign-in rule and lists recent changes', () => {
        render(<AdminSystem {...props} />);

        expect(document.body.textContent).toContain(
            'appears in that role’s sidebar on their next sign-in',
        );
        expect(screen.getByText('Yusuf Aziz')).toBeInTheDocument();
    });
});
