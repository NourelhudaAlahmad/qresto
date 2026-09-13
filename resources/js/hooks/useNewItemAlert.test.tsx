import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { showToast } from '@/components/qresto/toast';

import { useNewItemAlert } from './useNewItemAlert';

vi.mock('@/components/qresto/toast', () => ({
    showToast: vi.fn(),
}));

describe('useNewItemAlert', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('does not alert for the initial collection', () => {
        renderHook(() =>
            useNewItemAlert([{ id: 1 }, { id: 2 }], (item) => item.id),
        );

        expect(showToast).not.toHaveBeenCalled();
    });

    it('alerts once when a new item appears', () => {
        const { rerender } = renderHook(
            ({ items }) => useNewItemAlert(items, (item) => item.id),
            {
                initialProps: {
                    items: [{ id: 1 }],
                },
            },
        );

        rerender({
            items: [{ id: 1 }, { id: 2 }],
        });

        expect(showToast).toHaveBeenCalledTimes(1);
        expect(showToast).toHaveBeenCalledWith({
            type: 'info',
            title: 'New order',
            description: 'A new order has been received.',
        });
    });

    it('does not alert again for an already seen item', () => {
        const { rerender } = renderHook(
            ({ items }) => useNewItemAlert(items, (item) => item.id),
            {
                initialProps: {
                    items: [{ id: 1 }],
                },
            },
        );

        rerender({
            items: [{ id: 1 }, { id: 2 }],
        });

        rerender({
            items: [{ id: 1 }, { id: 2 }],
        });

        expect(showToast).toHaveBeenCalledTimes(1);
    });

    it('shows the toast but does not play sound when muted', () => {
        const originalMatchMedia = window.matchMedia;

        window.matchMedia = vi.fn().mockReturnValue({
            matches: false,
            media: '(prefers-reduced-motion: reduce)',
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        });

        const AudioContextMock = vi.fn();

        Object.defineProperty(window, 'AudioContext', {
            configurable: true,
            value: AudioContextMock,
        });

        const { rerender } = renderHook(
            ({ items }) =>
                useNewItemAlert(items, (item) => item.id, { muted: true }),
            {
                initialProps: {
                    items: [{ id: 1 }],
                },
            },
        );

        rerender({
            items: [{ id: 1 }, { id: 2 }],
        });

        expect(showToast).toHaveBeenCalledTimes(1);
        expect(AudioContextMock).not.toHaveBeenCalled();

        window.matchMedia = originalMatchMedia;
    });

    it('does not play the alert sound when reduced motion is preferred', () => {
        const originalMatchMedia = window.matchMedia;

        window.matchMedia = vi.fn().mockReturnValue({
            matches: true,
            media: '(prefers-reduced-motion: reduce)',
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        });

        const AudioContextMock = vi.fn();

        Object.defineProperty(window, 'AudioContext', {
            configurable: true,
            value: AudioContextMock,
        });

        const { rerender } = renderHook(
            ({ items }) => useNewItemAlert(items, (item) => item.id),
            {
                initialProps: {
                    items: [{ id: 1 }],
                },
            },
        );

        rerender({
            items: [{ id: 1 }, { id: 2 }],
        });

        expect(showToast).toHaveBeenCalledTimes(1);
        expect(AudioContextMock).not.toHaveBeenCalled();

        window.matchMedia = originalMatchMedia;
    });
});
