import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Tables from '@/pages/guest/tables';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        visit: (...args: unknown[]) => visit(...args),
    },

    usePage: () => ({
        props: {
            locale: 'en',
            dir: 'ltr',
        },
    }),
}));

vi.mock('@/components/qresto/responsive-overlay', () => ({
    ResponsiveOverlay: ({
        children,
        title,
        onOpenChange,
    }: {
        children: React.ReactNode;
        title: React.ReactNode;
        onOpenChange: (open: boolean) => void;
    }) => (
        <div role="dialog">
            <div>{title}</div>

            <button type="button" onClick={() => onOpenChange(false)}>
                Close picker
            </button>

            {children}
        </div>
    ),
}));

const tables = [
    {
        id: 11,
        number: '11',
        seats: 2,
    },
    {
        id: 12,
        number: '12',
        seats: 4,
    },
    {
        id: 13,
        number: '13',
        seats: 1,
    },
];

describe('Guest tables picker', () => {
    beforeEach(() => {
        visit.mockClear();

        vi.spyOn(window.history, 'back').mockImplementation(() => undefined);
    });

    it('renders all available tables and marks the current table', () => {
        render(<Tables current_table_id={12} tables={tables} />);

        expect(screen.getByText('Choose your table')).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: /table 11/i,
            }),
        ).toBeEnabled();

        expect(
            screen.getByRole('button', {
                name: /table 12/i,
            }),
        ).toBeDisabled();

        expect(screen.getByLabelText(/current table/i)).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: /table 13/i,
            }),
        ).toBeEnabled();
    });

    it('asks the guest to scan the chosen table instead of moving them', () => {
        render(<Tables current_table_id={12} tables={tables} />);

        expect(screen.queryByRole('status')).not.toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', {
                name: /table 11/i,
            }),
        );

        expect(visit).not.toHaveBeenCalled();
        expect(screen.getByRole('status')).toHaveTextContent(
            'Scan the QR code on table 11 to move there.',
        );
        expect(
            screen.getByRole('button', { name: /table 11/i }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    it('does nothing when the current table is selected', () => {
        render(<Tables current_table_id={12} tables={tables} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: /table 12/i,
            }),
        );

        expect(visit).not.toHaveBeenCalled();
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('goes back when the picker is closed', () => {
        render(<Tables current_table_id={12} tables={tables} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: /close picker/i,
            }),
        );

        expect(window.history.back).toHaveBeenCalledOnce();
    });
});
