import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import RestaurantSettings, {
    serviceExample,
} from '@/pages/staff/admin/restaurant';

const post = vi.fn();
const put = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: {
        post: (...args: unknown[]) => post(...args),
        put: (...args: unknown[]) => put(...args),
        patch: vi.fn(),
    },
}));

vi.mock('@/components/qresto/toast', () => ({ showToast: vi.fn() }));

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

const table = (id: number, number: string, extra = {}) => ({
    id,
    number,
    seats: 4,
    sort_order: id,
    qr_url: `http://qresto.test/t/token-${id}`,
    busy: false,
    unpaid: false,
    ...extra,
});

const props = {
    restaurant: {
        name: 'Al Bustan',
        tagline: 'Charcoal, citrus, and a long table.',
        cuisine: 'Modern Levantine',
        address: '14 Marsh Lane, Downtown',
        phone: '+1 (212) 555 0148',
        timezone: 'UTC',
        currency: 'USD',
        service_charge_pct: 12.5,
        default_locale: 'en',
        supported_locales: ['en', 'ar'],
    },
    hours: [1, 2, 3, 4, 5, 6, 0].map((day) => ({
        day_of_week: day,
        closed: day === 0,
        opens_at: day === 0 ? null : '17:00',
        closes_at: day === 0 ? null : '23:00',
    })),
    tables: [table(1, '01'), table(9, '09', { busy: true }), table(12, '12')],
    options: {
        currencies: ['USD', 'EUR'],
        locales: [
            { code: 'en', label: 'English' },
            { code: 'ar', label: 'العربية' },
        ],
        timezones: ['UTC', 'Europe/Istanbul'],
    },
};

beforeEach(() => {
    post.mockReset();
    put.mockReset();
});
afterEach(cleanup);

describe('Restaurant settings (#29)', () => {
    it('shows what the service charge does to a $50 bill, live', () => {
        expect(serviceExample(12.5, 'USD')).toBe(
            'On a $50.00 bill: $6.25 service, $56.25 total',
        );

        render(<RestaurantSettings {...props} />);

        fireEvent.change(screen.getByLabelText('Service charge %'), {
            target: { value: '10' },
        });

        expect(screen.getByTestId('service-example').textContent).toBe(
            'On a $50.00 bill: $5.00 service, $55.00 total',
        );
    });

    it('lists seven days, Monday first, with closed days switched off', () => {
        render(<RestaurantSettings {...props} />);

        expect(
            screen.getByRole('switch', { name: 'Monday open' }),
        ).toHaveAttribute('aria-checked', 'true');
        expect(
            screen.getByRole('switch', { name: 'Sunday open' }),
        ).toHaveAttribute('aria-checked', 'false');
    });

    it('warns before reissuing that the existing sticker stops working', () => {
        render(<RestaurantSettings {...props} />);

        fireEvent.click(
            screen.getByRole('button', { name: 'Reissue Table 12 QR code' }),
        );

        const dialog = screen.getByRole('dialog');

        expect(dialog.textContent).toContain('stops working immediately');

        fireEvent.click(screen.getByRole('button', { name: 'Reissue code' }));

        expect(post).toHaveBeenCalledWith(
            '/app/settings/tables/12/reissue',
            {},
            expect.objectContaining({ only: ['tables'] }),
        );
    });

    it('will not retire a table with guests seated', () => {
        render(<RestaurantSettings {...props} />);

        expect(
            screen.getByRole('button', { name: 'Retire Table 09' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Retire Table 01' }),
        ).toBeEnabled();
    });

    it('links each table to its own printable card, and all to the sheet', () => {
        render(<RestaurantSettings {...props} />);

        expect(
            screen.getByRole('link', { name: 'Print Table 12 QR code' }),
        ).toHaveAttribute('href', '/app/settings/tables/qr-sheet?table=12');
        expect(
            screen.getByRole('link', { name: /Print all QR codes/ }),
        ).toHaveAttribute('href', '/app/settings/tables/qr-sheet');
    });
});
