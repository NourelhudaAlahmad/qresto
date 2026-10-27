import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ResponsiveOverlay } from './responsive-overlay';

describe('ResponsiveOverlay', () => {
    it('renders the dialog content when open', () => {
        const { getByText } = render(
            <ResponsiveOverlay
                open={true}
                onOpenChange={vi.fn()}
                title="Confirm"
            >
                {' '}
                <p>Are you sure?</p>{' '}
            </ResponsiveOverlay>,
        );

        expect(getByText('Confirm')).toBeInTheDocument();
        expect(getByText('Are you sure?')).toBeInTheDocument();
    });

    it('uses the responsive dialog/sheet classes', () => {
        const { getByText } = render(
            <ResponsiveOverlay
                open={true}
                onOpenChange={vi.fn()}
                title="Confirm"
            >
                <p>Are you sure?</p>
            </ResponsiveOverlay>,
        );

        const content = getByText('Confirm').closest('[role="dialog"]');

        // Centred with inset-x-0 + mx-auto rather than start-1/2 and a
        // physical translate, so it stays centred in RTL.
        expect(content).toHaveClass('inset-x-0');
        expect(content).toHaveClass('mx-auto');
        expect(content).toHaveClass('top-1/2');
        expect(content).not.toHaveClass('-translate-x-1/2');
        expect(content).toHaveClass('max-[767px]:bottom-0');
    });
});
