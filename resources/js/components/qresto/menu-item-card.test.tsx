import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { MenuItemCard } from './menu-item-card';

describe('MenuItemCard', () => {
    it('calls onClick when the item is available', () => {
        const onClick = vi.fn();

        render(
            <MenuItemCard
                name="Lamb kofta"
                price="$18.00"
                available
                onClick={onClick}
            />,
        );

        screen.getByRole('button', { name: 'Lamb kofta' }).click();

        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('disables sold out items', () => {
        const onClick = vi.fn();

        render(
            <MenuItemCard
                name="Charred aubergine"
                price="$12.00"
                available={false}
                onClick={onClick}
            />,
        );

        const button = screen.getByRole('button', {
            name: 'Charred aubergine',
        });

        expect(button).toBeDisabled();
        expect(screen.getByText('Sold out')).toBeInTheDocument();

        button.click();

        expect(onClick).not.toHaveBeenCalled();
    });
});
