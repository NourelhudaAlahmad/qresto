import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ProgressTrail } from './progress-trail';

describe('ProgressTrail', () => {
    const items = [
        {
            title: 'Order placed',
            time: '19:42',
            detail: 'Sent to the pass',
            state: 'done' as const,
        },
        {
            title: 'In the kitchen',
            time: '19:44',
            detail: 'Kofta on the grill',
            state: 'current' as const,
        },
        {
            title: 'Ready at the pass',
            time: '~19:58',
            detail: 'Nadia collects it',
            state: 'upcoming' as const,
        },
        {
            title: 'At your table',
            detail: 'Enjoy',
            state: 'upcoming' as const,
        },
    ];

    it('renders all trail items', () => {
        const { getByText } = render(
            <ProgressTrail items={items} />,
        );

        expect(getByText('Order placed')).toBeInTheDocument();
        expect(getByText('In the kitchen')).toBeInTheDocument();
        expect(getByText('Ready at the pass')).toBeInTheDocument();
        expect(getByText('At your table')).toBeInTheDocument();

        expect(getByText('19:42')).toBeInTheDocument();
        expect(getByText('~19:58')).toBeInTheDocument();
    });

    it('applies the correct state colors', () => {
        const { getByText } = render(
            <ProgressTrail items={items} />,
        );

        expect(getByText('Order placed')).toHaveClass(
            'text-[var(--text-primary)]',
        );

        expect(getByText('In the kitchen')).toHaveClass(
            'text-[var(--text-primary)]',
        );

        expect(getByText('Ready at the pass')).toHaveClass(
            'text-[var(--text-tertiary)]',
        );
    });
});