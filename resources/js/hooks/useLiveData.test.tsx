import { router } from '@inertiajs/react';
import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { MAX_BACKOFF_MS, resetLiveConnection } from '@/lib/liveConnection';

import { useLiveData } from './useLiveData';

vi.mock('@inertiajs/react', () => ({
    router: {
        reload: vi.fn(),
    },
}));

type ReloadOptions = {
    only: string[];
    data: Record<string, string>;
    onSuccess: () => void;
    onHttpException: (response: { status: number }) => boolean | void;
    onNetworkError: (error: Error) => boolean | void;
    onFinish: () => void;
};

const reload = vi.mocked(router.reload);

function lastReload(): ReloadOptions {
    const calls = reload.mock.calls;

    return calls[calls.length - 1][0] as unknown as ReloadOptions;
}

/** Answer the poll in flight the way Inertia would. */
function answer(outcome: 'success' | 204 | 500 | 'network'): boolean | void {
    const options = lastReload();
    let returned: boolean | void = undefined;

    act(() => {
        if (outcome === 'success') {
            options.onSuccess();
        } else if (outcome === 'network') {
            returned = options.onNetworkError(new Error('offline'));
        } else {
            returned = options.onHttpException({ status: outcome });
        }

        options.onFinish();
    });

    return returned;
}

function advance(ms: number): void {
    act(() => {
        vi.advanceTimersByTime(ms);
    });
}

function setHidden(hidden: boolean): void {
    Object.defineProperty(document, 'hidden', {
        configurable: true,
        get: () => hidden,
    });

    act(() => {
        document.dispatchEvent(new Event('visibilitychange'));
    });
}

beforeEach(() => {
    vi.useFakeTimers();
    reload.mockReset();
    resetLiveConnection();
    Object.defineProperty(document, 'hidden', {
        configurable: true,
        get: () => false,
    });
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useLiveData polling', () => {
    it('polls the live props one interval after the page loads', () => {
        renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        advance(4999);
        expect(reload).not.toHaveBeenCalled();

        advance(1);
        expect(reload).toHaveBeenCalledTimes(1);
        expect(lastReload().only).toEqual(['liveOrders']);
        expect(lastReload().data).toEqual({
            version: '2026-09-13 12:00:00.000000:2',
        });
    });

    it('waits for a poll to finish before scheduling the next', () => {
        renderHook(() => useLiveData(5000, { only: ['orders'] }));

        advance(5000);
        advance(60_000);
        expect(reload).toHaveBeenCalledTimes(1);

        answer('success');
        advance(5000);
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('treats a 204 as an answer: nothing changed, still live', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['orders'] }),
        );

        advance(5000);

        expect(answer(204)).toBe(false);
        expect(result.current.isDisconnected).toBe(false);
    });
});

describe('useLiveData failures (#58)', () => {
    it('goes disconnected on a server error without an error dialog', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['orders'] }),
        );

        advance(5000);

        // false stops Inertia's dialog over a live screen.
        expect(answer(500)).toBe(false);
        expect(result.current.isDisconnected).toBe(true);
        expect(result.current.connection.failures).toBe(1);
    });

    it('goes disconnected when the request never reaches the server', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['orders'] }),
        );

        advance(5000);

        expect(answer('network')).toBe(false);
        expect(result.current.isDisconnected).toBe(true);
    });

    it('backs off with each failure in a row, capped at a minute', () => {
        renderHook(() => useLiveData(4000, { only: ['tickets'] }));

        advance(4000);
        expect(reload).toHaveBeenCalledTimes(1);

        // 8s, 16s, 32s, then the cap.
        for (const [index, wait] of [
            8000,
            16_000,
            32_000,
            MAX_BACKOFF_MS,
            MAX_BACKOFF_MS,
        ].entries()) {
            answer('network');

            advance(wait - 1);
            expect(reload).toHaveBeenCalledTimes(index + 1);

            advance(1);
            expect(reload).toHaveBeenCalledTimes(index + 2);
        }
    });

    it('recovers on its own: a success clears the state and resets the interval', () => {
        const { result } = renderHook(() =>
            useLiveData(4000, { only: ['tickets'] }),
        );

        advance(4000);
        answer(500);
        advance(8000);
        answer(500);
        expect(result.current.connection.failures).toBe(2);

        advance(16_000);
        const before = Date.now();
        answer('success');

        expect(result.current.isDisconnected).toBe(false);
        expect(result.current.connection.failures).toBe(0);
        expect(result.current.connection.lastSuccessAt).toBeGreaterThanOrEqual(
            before,
        );

        const polls = reload.mock.calls.length;
        advance(4000);
        expect(reload).toHaveBeenCalledTimes(polls + 1);
    });

    it('keeps the time of the last good answer while failing', () => {
        const { result } = renderHook(() =>
            useLiveData(4000, { only: ['tickets'] }),
        );

        advance(4000);
        answer('success');
        const lastGood = result.current.connection.lastSuccessAt;

        advance(4000);
        answer('network');
        advance(8000);
        answer('network');

        expect(result.current.connection.lastSuccessAt).toBe(lastGood);
    });

    it('trusts the browser when it says the network is gone, and retries the moment it is back', () => {
        const { result } = renderHook(() =>
            useLiveData(4000, { only: ['tickets'] }),
        );

        act(() => {
            window.dispatchEvent(new Event('offline'));
        });
        expect(result.current.isDisconnected).toBe(true);
        expect(reload).not.toHaveBeenCalled();

        act(() => {
            window.dispatchEvent(new Event('online'));
        });
        expect(reload).toHaveBeenCalledTimes(1);
        expect(result.current.isDisconnected).toBe(false);
    });
});

describe('useLiveData visibility and control', () => {
    it('stops polling while the tab is hidden', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['liveOrders'] }),
        );

        setHidden(true);
        advance(60_000);

        expect(reload).not.toHaveBeenCalled();
        expect(result.current.isPaused).toBe(true);
    });

    it('reloads immediately when the tab becomes visible again', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['liveOrders'] }),
        );

        setHidden(true);
        setHidden(false);

        expect(reload).toHaveBeenCalledTimes(1);
        expect(result.current.isPaused).toBe(false);
    });

    it('can be paused and resumed by hand', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, { only: ['liveOrders'] }),
        );

        act(() => result.current.pause());
        advance(20_000);
        expect(reload).not.toHaveBeenCalled();
        expect(result.current.isPaused).toBe(true);

        act(() => result.current.resume());
        advance(5000);
        expect(reload).toHaveBeenCalledTimes(1);
        expect(result.current.isPaused).toBe(false);
    });

    it('keeps one poller when re-rendered with a new but equal only array', () => {
        const { rerender } = renderHook(
            ({ only }: { only: string[] }) => useLiveData(5000, { only }),
            { initialProps: { only: ['orders'] } },
        );

        rerender({ only: ['orders'] });
        rerender({ only: ['orders'] });
        advance(5000);

        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('stops polling on unmount', () => {
        const { unmount } = renderHook(() =>
            useLiveData(5000, { only: ['orders'] }),
        );

        unmount();
        advance(60_000);

        expect(reload).not.toHaveBeenCalled();
    });
});
