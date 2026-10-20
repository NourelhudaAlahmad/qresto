import { act, fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CallWaiterSheet } from '@/components/qresto/call-waiter-sheet';

const { postMock, toastMock } = vi.hoisted(() => ({
    postMock: vi.fn<(...args: unknown[]) => void>(),
    toastMock: vi.fn<(...args: unknown[]) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { post: postMock },
}));

vi.mock('@/components/qresto/toast', () => ({
    showToast: toastMock,
}));

vi.mock('@/components/qresto/responsive-overlay', () => ({
    ResponsiveOverlay: ({
        open,
        title,
        children,
    }: {
        open: boolean;
        title: ReactNode;
        children: ReactNode;
    }) =>
        open ? (
            <section role="dialog" aria-label="Call waiter">
                <h2>{title}</h2>
                {children}
            </section>
        ) : null,
}));

beforeEach(() => {
    vi.clearAllMocks();
});

describe('CallWaiterSheet', () => {
    it('displays all four request kinds when open', () => {
        render(<CallWaiterSheet open onOpenChange={vi.fn()} />);

        expect(screen.getByRole('button', { name: /water/i })).toBeEnabled();
        expect(screen.getByRole('button', { name: /bread/i })).toBeEnabled();
        expect(screen.getByRole('button', { name: /the bill/i })).toBeEnabled();
        expect(screen.getByRole('button', { name: /waiter/i })).toBeEnabled();
    });

    it('hides the content when closed', () => {
        render(<CallWaiterSheet open={false} onOpenChange={vi.fn()} />);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('disables kinds with pending requests', () => {
        render(
            <CallWaiterSheet
                open
                onOpenChange={vi.fn()}
                openRequests={['water', 'bill']}
            />,
        );

        expect(screen.getByRole('button', { name: /water/i })).toBeDisabled();
        expect(
            screen.getByRole('button', { name: /the bill/i }),
        ).toBeDisabled();
        expect(screen.getAllByText('Request pending')).toHaveLength(2);
        expect(screen.getByRole('button', { name: /bread/i })).toBeEnabled();
        expect(postMock).not.toHaveBeenCalled();
    });

    it('sends a bill request and shows the confirmation after success', () => {
        const onOpenChange = vi.fn();
        render(<CallWaiterSheet open onOpenChange={onOpenChange} />);

        fireEvent.click(screen.getByRole('button', { name: /the bill/i }));

        expect(postMock).toHaveBeenCalledWith(
            '/service-requests',
            { kind: 'bill' },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(toastMock).not.toHaveBeenCalled();

        const callbacks = postMock.mock.calls[0][2] as {
            onSuccess: () => void;
            onFinish: () => void;
        };
        act(() => {
            callbacks.onSuccess();
            callbacks.onFinish();
        });

        expect(onOpenChange).toHaveBeenCalledWith(false);
        expect(toastMock).toHaveBeenCalledWith({
            type: 'success',
            title: 'Request sent',
            description: 'Your bill has been requested.',
        });
    });

    it('prevents another submission while the first one is pending', () => {
        render(<CallWaiterSheet open onOpenChange={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: /water/i }));
        expect(postMock).toHaveBeenCalledTimes(1);
        expect(screen.getByRole('button', { name: /bread/i })).toBeDisabled();

        const callbacks = postMock.mock.calls[0][2] as {
            onSuccess: () => void;
            onFinish: () => void;
        };
        act(() => {
            callbacks.onSuccess();
            callbacks.onFinish();
        });

        expect(toastMock).toHaveBeenCalledWith({
            type: 'success',
            title: 'Request sent',
            description: 'Your waiter has been notified.',
        });
    });

    it('shows an error toast and re-enables requests after failure', () => {
        render(<CallWaiterSheet open onOpenChange={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: /waiter/i }));

        const callbacks = postMock.mock.calls[0][2] as {
            onError: () => void;
            onFinish: () => void;
        };
        act(() => {
            callbacks.onError();
            callbacks.onFinish();
        });

        expect(toastMock).toHaveBeenCalledWith({
            type: 'error',
            title: 'Could not send request',
            description: 'Please try again.',
        });
        expect(screen.getByRole('button', { name: /waiter/i })).toBeEnabled();
    });
});
