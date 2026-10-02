import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import Table from '@/pages/guest/table';

const post = vi.fn();
const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        post: (...args: unknown[]) => post(...args),
        visit: (...args: unknown[]) => visit(...args),
    },

    usePage: () => ({
        props: {
            locale: 'en',
            dir: 'ltr',
        },
    }),
}));
const tableProps = {
    restaurant: {
        name: 'Al Bustan',
    },
    table: {
        id: 12,
        number: '12',
        seats: 4,
    },
    assigned_waiter: {
        name: 'Nadia Rahman',
        initials: 'NR',
    },
    qr_token: 'table-12-valid-token',
};

function renderTable() {
    render(<Table {...tableProps} />);

    act(() => {
        vi.advanceTimersByTime(1100);
    });
}

describe('Guest table page', () => {
    beforeEach(() => {
        vi.useFakeTimers();

        post.mockClear();
        visit.mockClear();

        window.localStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the scanned table details and assigned waiter', () => {
        renderTable();

        expect(screen.getByText('QR code read')).toBeInTheDocument();

        expect(
            screen.getByRole('heading', {
                name: "You're at Table 12",
            }),
        ).toBeInTheDocument();

        expect(screen.getByText('Al Bustan')).toBeInTheDocument();

        expect(
            screen.getByText(
                'Four covers. Nadia is looking after this table tonight.',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: /open the menu/i,
            }),
        ).toBeDisabled();
    });

    it('stores the first name and confirms the table session', () => {
        renderTable();

        const firstNameInput = screen.getByRole('textbox', {
            name: /first name/i,
        });

        fireEvent.change(firstNameInput, {
            target: {
                value: '  Rami  ',
            },
        });

        fireEvent.click(
            screen.getByRole('button', {
                name: /open the menu/i,
            }),
        );

        expect(window.localStorage.getItem('qresto_guest_first_name')).toBe(
            'Rami',
        );

        expect(post).toHaveBeenCalledWith(
            '/t/table-12-valid-token/confirm',
            {
                first_name: 'Rami',
            },
            expect.objectContaining({
                preserveScroll: true,
                onStart: expect.any(Function),
                onFinish: expect.any(Function),
            }),
        );
    });

    it('opens the table picker when the guest selects the wrong table', () => {
        renderTable();

        fireEvent.click(
            screen.getByRole('button', {
                name: /wrong table/i,
            }),
        );

        expect(visit).toHaveBeenCalledWith('/t/table-12-valid-token/tables');
    });

    it('restores a previously saved first name', () => {
        window.localStorage.setItem('qresto_guest_first_name', 'Layla');

        renderTable();

        expect(
            screen.getByRole('textbox', {
                name: /first name/i,
            }),
        ).toHaveValue('Layla');

        expect(
            screen.getByRole('button', {
                name: /open the menu/i,
            }),
        ).toBeEnabled();
    });
});
