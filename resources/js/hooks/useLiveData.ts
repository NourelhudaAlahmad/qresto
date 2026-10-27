import { router, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';

type LiveDataOptions = {
    only: string[];
    version?: string;
};

type LiveDataReturn = {
    isPaused: boolean;
    pause: () => void;
    resume: () => void;
};

export function useLiveData(
    intervalMs: number,
    options: LiveDataOptions,
): LiveDataReturn {
    const [isPaused, setIsPaused] = useState(false);

    // Callers usually pass `{ only: ['orders'] }` inline, which is a new
    // array on every render. Key on the contents so the request options and
    // the visibility listener stay stable across renders.
    const onlyKey = options.only.join('|');
    const only = useMemo(
        () => (onlyKey === '' ? [] : onlyKey.split('|')),
        [onlyKey],
    );

    const requestOptions = useCallback(
        () => ({
            only,
            data: options.version
                ? {
                      version: options.version,
                  }
                : {},
            onHttpException: (response: { status: number }) =>
                response.status === 204 ? false : undefined,
        }),
        [only, options.version],
    );

    // usePoll reads the latest function on every tick, so the poller itself
    // is created once.
    const { start, stop } = usePoll(intervalMs, requestOptions, {
        autoStart: true,
        mode: 'rest',
    });

    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden) {
                stop();
                setIsPaused(true);

                return;
            }

            router.reload(requestOptions());

            start();
            setIsPaused(false);
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, [requestOptions, start, stop]);

    // Stop only when the screen unmounts, never on a re-render. Before this,
    // a re-render with a new `only` array stopped the poller for good.
    useEffect(() => () => stop(), [stop]);

    const pause = useCallback(() => {
        stop();
        setIsPaused(true);
    }, [stop]);

    const resume = useCallback(() => {
        start();
        setIsPaused(false);
    }, [start]);

    return {
        isPaused,
        pause,
        resume,
    };
}
