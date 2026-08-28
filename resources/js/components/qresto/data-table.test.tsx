import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DataTable } from './data-table';

describe('DataTable', () => {
    const columns = [
        { key: 'date' as const, label: 'Date' },
        { key: 'orders' as const, label: 'Orders', numeric: true },
        { key: 'net' as const, label: 'Net', numeric: true },
    ];

    const rows = [
        {
            date: '23 Aug',
            orders: '22',
            net: '$2,418.00',
        },
        {
            date: '22 Aug',
            orders: '19',
            net: '$2,104.20',
        },
    ];

    it('renders headers and rows', () => {
        const { getByText } = render(
            <DataTable columns={columns} rows={rows} />,
        );

        expect(getByText('Date')).toBeInTheDocument();
        expect(getByText('Orders')).toBeInTheDocument();
        expect(getByText('Net')).toBeInTheDocument();
        expect(getByText('23 Aug')).toBeInTheDocument();
        expect(getByText('$2,418.00')).toBeInTheDocument();
    });

    it('right-aligns numeric columns with mono font', () => {
        const { getByText } = render(
            <DataTable columns={columns} rows={rows} />,
        );

        expect(getByText('22')).toHaveClass('text-right', 'font-mono');
        expect(getByText('$2,418.00')).toHaveClass(
            'text-right',
            'font-mono',
        );
    });

    it('renders the optional total row', () => {
        const { getByText } = render(
            <DataTable
                columns={columns}
                rows={rows}
                total="$4,522.20"
            />,
        );

        expect(getByText('Total')).toBeInTheDocument();
        expect(getByText('$4,522.20')).toBeInTheDocument();
    });
});