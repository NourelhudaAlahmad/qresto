import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StickyDock } from './sticky-dock';

describe('StickyDock', () => {
    it('renders its content', () => {
        const { getByText } = render(
            <StickyDock>
                <button type="button">View order · 3 items</button>
            </StickyDock>,
        );

        expect(
            getByText('View order · 3 items'),
        ).toBeInTheDocument();
    });

    it('uses the sticky bottom dock layout', () => {
        const { getByText } = render(
            <StickyDock>
                <button type="button">View order</button>
            </StickyDock>,
        );

        const dock = getByText('View order').parentElement;

        expect(dock).toHaveClass('sticky');
        expect(dock).toHaveClass('bottom-0');
        expect(dock).toHaveClass('z-[15]');
        expect(dock).toHaveClass('backdrop-blur-[12px]');
    });
});