import { useEffect, useState, useSyncExternalStore } from 'react';

import {
    liveConnectionSnapshot,
    subscribeLiveConnection,
} from '@/lib/liveConnection';
import type { LiveConnectionState } from '@/lib/liveConnection';

/** The shared live-connection state (#58), re-rendering on every change. */
export function useLiveConnection(): LiveConnectionState {
    return useSyncExternalStore(
        subscribeLiveConnection,
        liveConnectionSnapshot,
        liveConnectionSnapshot,
    );
}

/**
 * Whole minutes since the data was last confirmed, refreshed every
 * fifteen seconds so "updated 4 min ago" keeps counting while nothing
 * else on the screen changes.
 */
export function useMinutesSince(at: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 15_000);

        return () => window.clearInterval(timer);
    }, []);

    // A success newer than the last tick reads as zero, not negative.
    return Math.max(0, Math.floor((now - at) / 60_000));
}
