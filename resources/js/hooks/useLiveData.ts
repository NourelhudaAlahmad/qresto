import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { useLiveConnection } from '@/hooks/useLiveConnection';
import {
    backoffDelay,
    isDisconnected,
    recordBrowserOnline,
    recordLiveFailure,
    recordLiveSuccess,
} from '@/lib/liveConnection';
import type { LiveConnectionState } from '@/lib/liveConnection';

type LiveDataOptions = {
    only: string[];
    version?: string;
};

type LiveDataReturn = {
    isPaused: boolean;
    /** The last poll failed, or the device has no network (#58). */
    isDisconnected: boolean;
    /** Failures in a row and when the data was last confirmed. */
    connection: LiveConnectionState;
    pause: () => void;
    resume: () => void;
};

/**
 * Poll the page's live props with a partial reload (#11), telling the
 * truth about it (#58): every outcome is recorded, a failure backs the
 * next poll off (doubling, capped at a minute), and the first success
 * after that returns to the normal interval.
 *
 * Polls run one at a time: the next is scheduled when the last finishes.
 */
export function useLiveData(
    intervalMs: number,
    options: LiveDataOptions,
): LiveDataReturn {
    const [isPaused, setIsPaused] = useState(false);
    const connection = useLiveConnection();

    // Callers usually pass `{ only: ['orders'] }` inline, which is a new
    // array on every render. Key on the contents so the poller stays put.
    const onlyKey = options.only.join('|');
    const only = useMemo(
        () => (onlyKey === '' ? [] : onlyKey.split('|')),
        [onlyKey],
    );

    // The loop reads the latest options without restarting.
    const latest = useRef({ only, version: options.version, intervalMs });

    useEffect(() => {
        latest.current = { only, version: options.version, intervalMs };
    }, [only, options.version, intervalMs]);

    // The timer always runs the current poll function.
    const pollRef = useRef<() => void>(() => undefined);
    const timer = useRef<number | null>(null);
    const inFlight = useRef(false);
    const paused = useRef(false);
    const failures = useRef(0);
    const unmounted = useRef(false);

    const clearTimer = useCallback(() => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }
    }, []);

    const schedule = useCallback(
        (delayMs: number) => {
            clearTimer();

            if (paused.current || unmounted.current) {
                return;
            }

            timer.current = window.setTimeout(() => pollRef.current(), delayMs);
        },
        [clearTimer],
    );

    const poll = useCallback(() => {
        clearTimer();

        if (inFlight.current || paused.current || unmounted.current) {
            return;
        }

        inFlight.current = true;

        const succeeded = () => {
            failures.current = 0;
            recordLiveSuccess();
        };

        const failed = () => {
            failures.current += 1;
            recordLiveFailure();
        };

        router.reload({
            only: latest.current.only,
            data: latest.current.version
                ? { version: latest.current.version }
                : {},
            onSuccess: succeeded,
            // 204: the server says nothing changed, which is an answer.
            // Anything else is a failed poll: say so on the indicator
            // rather than throwing an error dialog over a live screen.
            onHttpException: (response: { status: number }) => {
                if (response.status === 204) {
                    succeeded();
                } else {
                    failed();
                }

                return false;
            },
            onNetworkError: () => {
                failed();

                return false;
            },
            // Fires once for every outcome, a cancel included.
            onFinish: () => {
                inFlight.current = false;
                schedule(
                    backoffDelay(latest.current.intervalMs, failures.current),
                );
            },
        });
    }, [clearTimer, schedule]);

    useEffect(() => {
        pollRef.current = poll;
    }, [poll]);

    const start = useCallback(() => {
        paused.current = false;
        setIsPaused(false);
    }, []);

    const stop = useCallback(() => {
        paused.current = true;
        clearTimer();
        setIsPaused(true);
    }, [clearTimer]);

    // First poll one interval after mount; the page arrived fresh.
    useEffect(() => {
        unmounted.current = false;
        schedule(latest.current.intervalMs);

        return () => {
            unmounted.current = true;
            clearTimer();
        };
    }, [clearTimer, schedule]);

    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden) {
                stop();

                return;
            }

            start();
            poll();
        };

        // The browser knows it lost the network before any request does.
        // Coming back, try at once instead of waiting out the backoff.
        const handleOffline = () => recordBrowserOnline(false);
        const handleOnline = () => {
            recordBrowserOnline(true);

            if (!paused.current) {
                poll();
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, [poll, start, stop]);

    const pause = useCallback(() => stop(), [stop]);

    const resume = useCallback(() => {
        start();
        schedule(latest.current.intervalMs);
    }, [schedule, start]);

    return {
        isPaused,
        isDisconnected: isDisconnected(connection),
        connection,
        pause,
        resume,
    };
}
