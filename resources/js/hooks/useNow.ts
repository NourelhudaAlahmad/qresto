import { useEffect, useState } from 'react';

/**
 * The current time, refreshed every `intervalMs`, so ages on a screen keep
 * counting between polls instead of freezing at the moment it opened.
 */
export function useNow(intervalMs = 15_000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), intervalMs);

        return () => window.clearInterval(timer);
    }, [intervalMs]);

    return now;
}
