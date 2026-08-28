import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { PriceSummary } from './price-summary';

describe('PriceSummary', () => {
    it('renders the required totals', () => {
        const { getByText } = render(
            <PriceSummary
                subtotal="9.00"
                service="54.35"
                total="154.00"
            />,
        );

        expect(getByText('Subtotal')).toBeInTheDocument();
        expect(getByText('9.00')).toBeInTheDocument();
        expect(getByText('Service')).toBeInTheDocument();
        expect(getByText('54.35')).toBeInTheDocument();
        expect(getByText('Total')).toBeInTheDocument();
        expect(getByText('154.00')).toBeInTheDocument();
    });

    it('renders optional rows only when provided', () => {
        const { queryByText, getByText } = render(
            <PriceSummary
                subtotal="100.00"
                discount="-10.00"
                tip="5.00"
                split="47.50"
                total="95.00"
            />,
        );

        expect(queryByText('Service')).not.toBeInTheDocument();
        expect(getByText('Discount')).toBeInTheDocument();
        expect(getByText('Tip')).toBeInTheDocument();
        expect(getByText('Split')).toBeInTheDocument();
    });
});