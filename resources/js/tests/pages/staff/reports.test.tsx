import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Reports, { methodLabel } from '@/pages/staff/reports';

const get = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: (...args: unknown[]) => get(...args) },
}));

const props = {
    range: { start: '2026-10-01', end: '2026-10-24', preset: 'month' as const },
    currency: 'USD',
    rows: [
        {
            date: '2026-10-20',
            waiter: 'Nadia Rahman',
            covers: 18,
            orders: 7,
            net_minor: 46473,
            tips_minor: 4415,
            method: 'pos',
        },
        {
            date: '2026-10-24',
            waiter: 'Omar Faruk',
            covers: 20,
            orders: 8,
            net_minor: 49070,
            tips_minor: 3463,
            method: 'card',
        },
    ],
    totals: {
        gross_minor: 98399,
        service_minor: 10933,
        refunds_minor: 2856,
        tips_minor: 7878,
        net_minor: 95543,
        covers: 38,
        orders: 15,
    },
    series: [
        { date: '2026-10-20', net_minor: 46473, peak: false },
        { date: '2026-10-24', net_minor: 49070, peak: true },
    ],
};

beforeEach(() => get.mockReset());
afterEach(cleanup);

describe('Reports (#32)', () => {
    it('right-aligns every number in mono and left-aligns text', () => {
        render(<Reports {...props} />);

        const table = screen.getByRole('table');
        const headers = [...table.querySelectorAll('thead th')];
        const numeric = ['Orders', 'Net', 'Tips'];

        headers.forEach((th) => {
            if (numeric.includes(th.textContent ?? '')) {
                expect(th).toHaveClass('text-end', 'font-mono', 'tabular-nums');
            } else {
                expect(th).toHaveClass('text-start');
            }
        });

        const firstRow = table.querySelectorAll('tbody tr')[0].children;

        expect(firstRow[0]).toHaveClass('text-start'); // date
        expect(firstRow[3]).toHaveClass('text-end', 'font-mono'); // net
        expect(firstRow[3].textContent).toBe('$464.73');
    });

    it('keeps a bold total row that matches the totals', () => {
        render(<Reports {...props} />);

        const total = screen.getByRole('table').querySelector('tfoot');

        expect(total).toHaveClass('sticky', 'bottom-0', 'font-bold');
        expect(total?.querySelector('[data-total="net"]')?.textContent).toBe(
            '$955.43',
        );
    });

    it('shows refunds as a negative in berry', () => {
        render(<Reports {...props} />);

        const refunded = screen.getByText('−$28.56');

        expect(refunded).toHaveClass('text-[var(--danger-fg)]');
    });

    it('calls out the peak day', () => {
        render(<Reports {...props} />);

        expect(document.querySelectorAll('[data-peak]')).toHaveLength(1);
        expect(screen.getByRole('img').getAttribute('aria-label')).toContain(
            'peak on Sat 24 Oct',
        );
    });

    it('becomes a card stack below 720px with label–value pairs', () => {
        render(<Reports {...props} />);

        const stack = document.querySelector('ul.min-\\[720px\\]\\:hidden');

        expect(stack).not.toBeNull();
        expect(stack?.textContent).toContain('Net');
        expect(stack?.textContent).toContain('$464.73');
        expect(screen.getByRole('table').closest('div')).toHaveClass(
            'hidden',
            'min-[720px]:block',
        );
    });

    it('exports the range that is on screen', () => {
        render(<Reports {...props} />);

        expect(screen.getByRole('link', { name: /CSV/ })).toHaveAttribute(
            'href',
            '/app/reports/export.csv?preset=month',
        );
        expect(screen.getByRole('link', { name: /PDF/ })).toHaveAttribute(
            'href',
            '/app/reports/export.pdf?preset=month',
        );
    });

    it('refuses a custom range that ends before it starts', () => {
        render(<Reports {...props} />);

        fireEvent.change(screen.getByLabelText('From'), {
            target: { value: '2026-10-20' },
        });
        fireEvent.change(screen.getByLabelText('To'), {
            target: { value: '2026-10-10' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Apply' }));

        expect(get).not.toHaveBeenCalled();
        expect(screen.getByRole('alert').textContent).toContain(
            'on or after the start date',
        );
    });

    it('labels payment methods the same way as the CSV and PDF', () => {
        expect(methodLabel('pos')).toBe('POS');
        expect(methodLabel('card')).toBe('Card');
        expect(methodLabel(null)).toBe('');
    });
});
