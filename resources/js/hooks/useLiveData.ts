import { usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

    const { start, stop } = usePoll(
        intervalMs,
        () => ({
            only: options.only,
            data: options.version
                ? {
                      version: options.version,
                  }
                : {},
        }),
        {
            autoStart: true,
            mode: 'rest',
        },
    );

    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden) {
                stop();
                setIsPaused(true);

                return;
            }

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
    }, [start, stop]);

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
