import { router, usePoll } from '@inertiajs/react';
import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useLiveData } from './useLiveData';

vi.mock('@inertiajs/react', () => ({
    usePoll: vi.fn(),
    router: {
        reload: vi.fn(),
    },
}));

describe('useLiveData', () => {
    const start = vi.fn();
    const stop = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(usePoll).mockReturnValue({
            start,
            stop,
            polling: false,
        });
    });

    it('starts polling with the interval, live props, version and rest mode', () => {
        renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        const call = vi.mocked(usePoll).mock.calls[0];

        expect(call[0]).toBe(5000);
        expect(call[1]).toBeTypeOf('function');

        const requestOptions = (
            call[1] as () => {
                only: string[];
                data: {
                    version: string;
                };
                onHttpException: (response: {
                    status: number;
                }) => false | undefined;
            }
        )();

        expect(requestOptions).toEqual({
            only: ['liveOrders'],
            data: {
                version: '2026-09-13 12:00:00.000000:2',
            },
            onHttpException: expect.any(Function),
        });

        expect(requestOptions.onHttpException({ status: 204 })).toBe(false);
        expect(requestOptions.onHttpException({ status: 500 })).toBeUndefined();

        expect(call[2]).toEqual({
            autoStart: true,
            mode: 'rest',
        });
    });

    it('pauses polling when the tab becomes hidden', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        Object.defineProperty(document, 'hidden', {
            configurable: true,
            value: true,
        });

        act(() => {
            document.dispatchEvent(new Event('visibilitychange'));
        });

        expect(stop).toHaveBeenCalled();
        expect(result.current.isPaused).toBe(true);
    });

    it('does not start polling when the tab becomes hidden', () => {
        renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        Object.defineProperty(document, 'hidden', {
            configurable: true,
            value: true,
        });

        act(() => {
            document.dispatchEvent(new Event('visibilitychange'));
        });

        expect(stop).toHaveBeenCalled();
        expect(start).not.toHaveBeenCalled();
    });

    it('allows polling to be paused and resumed manually', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        act(() => {
            result.current.pause();
        });

        expect(stop).toHaveBeenCalled();
        expect(result.current.isPaused).toBe(true);

        act(() => {
            result.current.resume();
        });

        expect(start).toHaveBeenCalled();
        expect(result.current.isPaused).toBe(false);
    });

    it('reloads immediately when the tab becomes visible', () => {
        const { result } = renderHook(() =>
            useLiveData(5000, {
                only: ['liveOrders'],
                version: '2026-09-13 12:00:00.000000:2',
            }),
        );

        Object.defineProperty(document, 'hidden', {
            configurable: true,
            value: false,
        });

        act(() => {
            document.dispatchEvent(new Event('visibilitychange'));
        });

        expect(router.reload).toHaveBeenCalledWith({
            only: ['liveOrders'],
            data: {
                version: '2026-09-13 12:00:00.000000:2',
            },
            onHttpException: expect.any(Function),
        });

        expect(start).toHaveBeenCalled();
        expect(result.current.isPaused).toBe(false);
    });
});
