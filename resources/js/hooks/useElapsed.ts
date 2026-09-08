import { useEffect, useMemo, useState } from 'react';

export type ElapsedLevel = 'normal' | 'warn' | 'late';

export type ElapsedThresholds = {
    warnMinutes: number;
    lateMinutes: number;
};

type UseElapsedResult = {
    minutes: number;
    level: ElapsedLevel;
};

export function useElapsed(
    since: string | Date | null | undefined,
    thresholds: ElapsedThresholds,
): UseElapsedResult {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 60_000);

        return () => {
            window.clearInterval(interval);
        };
    }, []);

    return useMemo(() => {
        if (!since) {
            return {
                minutes: 0,
                level: 'normal',
            };
        }

        const startedAt =
            since instanceof Date
                ? since.getTime()
                : new Date(since).getTime();

        if (Number.isNaN(startedAt)) {
            return {
                minutes: 0,
                level: 'normal',
            };
        }

        const minutes = Math.max(
            0,
            Math.floor((now - startedAt) / 60000),
        );

        let level: ElapsedLevel = 'normal';

        if (minutes >= thresholds.lateMinutes) {
            level = 'late';
        } else if (minutes >= thresholds.warnMinutes) {
            level = 'warn';
        }

        return {
            minutes,
            level,
        };
    }, [since, now, thresholds.warnMinutes, thresholds.lateMinutes]);
}