import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { TabBar } from './tab-bar';

describe('TabBar', () => {
    it('renders all tabs', () => {
        const { getByText } = render(
            <TabBar
                items={[
                    { label: 'Menu' },
                    {
                        label: 'Order',
                        badge: 3,
                        icon: <span>🛍</span>,
                    },
                    { label: 'Status' },
                ]}
            />,
        );

        expect(getByText('Menu')).toBeInTheDocument();
        expect(getByText('Order')).toBeInTheDocument();
        expect(getByText('Status')).toBeInTheDocument();
        expect(getByText('3')).toBeInTheDocument();
    });

    it('marks the active tab with the active color', () => {
        const { getByText } = render(
            <TabBar
                items={[{ label: 'Menu', active: true }, { label: 'Order' }]}
            />,
        );

        expect(getByText('Menu').parentElement).toHaveClass(
            'text-[var(--clay-600)]',
        );

        expect(getByText('Order').parentElement).toHaveClass(
            'text-[var(--text-tertiary)]',
        );
    });

    it('calls onClick when a tab is clicked', () => {
        const onClick = vi.fn();

        const { getByRole } = render(
            <TabBar items={[{ label: 'Menu', onClick }]} />,
        );

        getByRole('button', { name: 'Menu' }).click();

        expect(onClick).toHaveBeenCalledTimes(1);
    });
});
