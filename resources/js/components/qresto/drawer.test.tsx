import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Drawer } from './drawer';

describe('Drawer', () => {
    it('renders the title and content when open', () => {
        const { getByText } = render(
            <Drawer open={true} onOpenChange={vi.fn()} title="Add waiter">
                <p>Waiter form</p>
            </Drawer>,
        );

        expect(getByText('Add waiter')).toBeInTheDocument();
        expect(getByText('Waiter form')).toBeInTheDocument();
    });

    it('uses the right-side drawer layout', () => {
        const { getByText } = render(
            <Drawer open={true} onOpenChange={vi.fn()} title="Add waiter">
                <p>Waiter form</p>
            </Drawer>,
        );

        const content = getByText('Add waiter').closest('[role="dialog"]');

        expect(content).toHaveClass('right-0');
        expect(content).toHaveClass('top-0');
        expect(content).toHaveClass('h-full');
    });
});
