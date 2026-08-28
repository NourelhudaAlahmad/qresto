import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { EmptyState } from './empty-state';

describe('EmptyState', () => {
    it('renders headline and body', () => {
        const { getByText } = render(
            <EmptyState
                headline="No orders yet"
                body="New orders will appear here."
            />,
        );

        expect(getByText('No orders yet')).toBeInTheDocument();
        expect(getByText('New orders will appear here.')).toBeInTheDocument();
    });

    it('renders the icon when provided', () => {
        const { getByText } = render(
            <EmptyState
                icon={<span>○</span>}
                headline="Nothing here"
            />,
        );

        expect(getByText('○')).toBeInTheDocument();
        expect(getByText('Nothing here')).toBeInTheDocument();
    });
});