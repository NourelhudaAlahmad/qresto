import { router, usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

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

    const requestOptions = useCallback(
        () => ({
            only: options.only,
            data: options.version
                ? {
                      version: options.version,
                  }
                : {},
            onHttpException: (response: { status: number }) =>
                response.status === 204 ? false : undefined,
        }),
        [options.only, options.version],
    );

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
            stop();
        };
    }, [requestOptions, start, stop]);

    const pause = () => {
        stop();
        setIsPaused(true);
    };

    const resume = () => {
        start();
        setIsPaused(false);
    };

    return {
        isPaused,
        pause,
        resume,
    };
}
