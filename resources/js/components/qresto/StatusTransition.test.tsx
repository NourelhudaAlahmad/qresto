import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { StatusTransition } from './StatusTransition';

describe('StatusTransition', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the current status', () => {
        render(
            <StatusTransition status="pending">
                <span>Pending</span>
            </StatusTransition>,
        );

        expect(screen.getByText('Pending')).toBeInTheDocument();
    });

    it('cross-fades the previous and current status', () => {
        vi.useFakeTimers();

        const { rerender } = render(
            <StatusTransition status="pending">
                <span>Pending</span>
            </StatusTransition>,
        );

        rerender(
            <StatusTransition status="ready">
                <span>Ready</span>
            </StatusTransition>,
        );

        const outgoing = screen.getByText('Pending').parentElement;
        const incoming = screen.getByText('Ready').parentElement;

        expect(outgoing).toHaveAttribute('data-transition', 'outgoing');
        expect(outgoing).toHaveAttribute('data-status', 'pending');
        expect(outgoing).toHaveClass('animate-out', 'fade-out');

        expect(incoming).toHaveAttribute('data-transition', 'incoming');
        expect(incoming).toHaveAttribute('data-status', 'ready');
        expect(incoming).toHaveClass('animate-in', 'fade-in');

        act(() => {
            vi.advanceTimersByTime(200);
        });

        expect(screen.queryByText('Pending')).not.toBeInTheDocument();
        expect(screen.getByText('Ready')).toBeInTheDocument();
    });

    it('uses the design-system motion tokens', () => {
        render(
            <StatusTransition status="pending">
                <span>Pending</span>
            </StatusTransition>,
        );

        const incoming = screen.getByText('Pending').parentElement;

        expect(incoming).toHaveStyle({
            animationDuration: 'var(--dur-base)',
            animationTimingFunction: 'var(--ease-standard)',
        });
    });

    it('respects reduced-motion preferences', () => {
        render(
            <StatusTransition status="pending">
                <span>Pending</span>
            </StatusTransition>,
        );

        expect(screen.getByText('Pending').parentElement).toHaveClass(
            'motion-reduce:animate-none',
        );
    });
});