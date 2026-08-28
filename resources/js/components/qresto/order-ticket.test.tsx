import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { OrderTicket } from './order-ticket';

const ticketProps = {
    code: '#A-1043',
    elapsed: '14 min',
    table: 'Table 12',
    guest: 'Rami',
    status: 'preparing' as const,
    items: [
        {
            qty: '2×',
            name: 'Lamb kofta',
            note: 'sumac onions',
        },
        {
            qty: '1×',
            name: 'Charred aubergine',
            note: 'no tahini',
        },
    ],
    paid: true,
    total: '$54.35',
    onAdvance: vi.fn(),
    onOverflow: vi.fn(),
};

describe('OrderTicket', () => {
    it.each(['light', 'dark'] as const)(
        'renders guest notes in saffron for %s variant',
        (variant) => {
            const { getByText } = render(
                <OrderTicket
                    {...ticketProps}
                    variant={variant}
                />,
            );

            expect(getByText('sumac onions')).toHaveClass(
                'text-[var(--saffron-300)]',
            );

            expect(getByText('no tahini')).toHaveClass(
                'text-[var(--saffron-300)]',
            );
        },
    );

    it('renders the order information', () => {
        const { getByText } = render(
            <OrderTicket {...ticketProps} />,
        );

        expect(getByText('#A-1043')).toBeInTheDocument();
        expect(getByText('14 min')).toBeInTheDocument();
        expect(getByText('Table 12 · Rami')).toBeInTheDocument();
        expect(getByText('$54.35')).toBeInTheDocument();
        expect(getByText('Paid')).toBeInTheDocument();
    });
});