import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StatusTransition } from './StatusTransition';

describe('StatusTransition', () => {
    it('renders its children', () => {
        render(
            <StatusTransition status="pending">
                <span>Order pending</span>
            </StatusTransition>,
        );

        expect(screen.getByText('Order pending')).toBeInTheDocument();
    });

    it('uses the status as the transition key', () => {
        const { container, rerender } = render(
            <StatusTransition status="pending">
                <span>Pending</span>
            </StatusTransition>,
        );

        const firstElement = container.firstElementChild;

        rerender(
            <StatusTransition status="ready">
                <span>Ready</span>
            </StatusTransition>,
        );

        const secondElement = container.firstElementChild;

        expect(firstElement).not.toBe(secondElement);
        expect(screen.getByText('Ready')).toBeInTheDocument();
    });

    it('applies the fade transition classes', () => {
        render(
            <StatusTransition status="pending">
                <span>Order</span>
            </StatusTransition>,
        );

        expect(screen.getByText('Order').parentElement).toHaveClass(
            'animate-in',
            'fade-in',
            'duration-300',
        );
    });
});
