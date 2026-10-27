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
    it.each([
        ['light', 'text-[var(--saffron-700)]'],
        ['dark', 'text-[var(--saffron-300)]'],
    ] as const)(
        'renders guest notes in saffron for %s variant',
        (variant, saffronClass) => {
            const { getByText } = render(
                <OrderTicket {...ticketProps} variant={variant} />,
            );

            expect(getByText('sumac onions')).toHaveClass(saffronClass);
            expect(getByText('no tahini')).toHaveClass(saffronClass);
        },
    );

    it('carries the design verb, SLA colour and status edge', () => {
        const { getByRole, getByText, container } = render(
            <OrderTicket
                {...ticketProps}
                advanceLabel="Mark ready"
                elapsedLevel="late"
                accentColor="var(--teal-500)"
            />,
        );

        expect(getByRole('button', { name: 'Mark ready' })).toBeInTheDocument();
        expect(getByText('14 min')).toHaveClass('!text-[var(--berry-500)]');
        expect(container.firstElementChild).toHaveStyle({
            borderInlineStartColor: 'var(--teal-500)',
        });
    });

    it('shows the footer note when there is no next step', () => {
        const { getByText, queryByRole } = render(
            <OrderTicket
                {...ticketProps}
                onAdvance={undefined}
                footerNote="Waiting for payment"
            />,
        );

        expect(getByText('Waiting for payment')).toBeInTheDocument();
        expect(
            queryByRole('button', { name: /advance/i }),
        ).not.toBeInTheDocument();
    });

    it('renders the order information', () => {
        const { getByText } = render(<OrderTicket {...ticketProps} />);

        expect(getByText('#A-1043')).toBeInTheDocument();
        expect(getByText('14 min')).toBeInTheDocument();
        expect(
            getByText(
                (_, element) => element?.textContent === 'Table 12 · Rami',
            ),
        ).toBeInTheDocument();
        expect(getByText('$54.35')).toBeInTheDocument();
        expect(getByText('Paid')).toBeInTheDocument();
    });
});
