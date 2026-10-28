import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useConnectionStatus } from './useConnectionStatus';

const listeners: Record<string, () => void> = {};

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (event: string, callback: () => void) => {
            listeners[event] = callback;

            return () => delete listeners[event];
        },
    },
}));

function setHidden(hidden: boolean) {
    Object.defineProperty(document, 'hidden', {
        configurable: true,
        get: () => hidden,
    });
    document.dispatchEvent(new Event('visibilitychange'));
}

describe('useConnectionStatus', () => {
    beforeEach(() => {
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            get: () => true,
        });
        Object.defineProperty(document, 'hidden', {
            configurable: true,
            get: () => false,
        });
    });

    it('is live while online and visible', () => {
        const { result } = renderHook(() => useConnectionStatus());

        expect(result.current).toBe('live');
    });

    it('is paused while the tab is in the background', () => {
        const { result } = renderHook(() => useConnectionStatus());

        act(() => setHidden(true));
        expect(result.current).toBe('paused');

        act(() => setHidden(false));
        expect(result.current).toBe('live');
    });

    it('is offline when the browser loses the network', () => {
        const { result } = renderHook(() => useConnectionStatus());

        act(() => {
            window.dispatchEvent(new Event('offline'));
        });
        expect(result.current).toBe('offline');

        act(() => {
            window.dispatchEvent(new Event('online'));
        });
        expect(result.current).toBe('live');
    });

    it('is offline after a failed request until the next success', () => {
        const { result } = renderHook(() => useConnectionStatus());

        act(() => listeners.networkError?.());
        expect(result.current).toBe('offline');

        act(() => listeners.success?.());
        expect(result.current).toBe('live');
    });
});
