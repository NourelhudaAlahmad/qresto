import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import type { LiveStatus } from '@/components/qresto/LiveIndicator';
import { useLiveConnection } from '@/hooks/useLiveConnection';
import {
    isDisconnected,
    recordBrowserOnline,
    recordLiveFailure,
    recordLiveSuccess,
} from '@/lib/liveConnection';

/**
 * What the "Live" pill in the staff topbar should say.
 *
 * - offline: the device reports no network, or the last request failed,
 *   whether it never reached the server or the server answered with an
 *   error (#58). Cleared by the next successful response.
 * - paused: the tab is in the background, so polling has stopped.
 * - live: otherwise.
 */
export function useConnectionStatus(): LiveStatus {
    const connection = useLiveConnection();
    const [visible, setVisible] = useState(() =>
        typeof document === 'undefined' ? true : !document.hidden,
    );

    useEffect(() => {
        const goOnline = () => recordBrowserOnline(true);
        const goOffline = () => recordBrowserOnline(false);
        const onVisibility = () => setVisible(!document.hidden);

        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        document.addEventListener('visibilitychange', onVisibility);

        // Any visit counts, not only polls: a bump that cannot reach the
        // server means the screen is not live either.
        const stopNetworkError = router.on('networkError', () =>
            recordLiveFailure(),
        );
        const stopSuccess = router.on('success', () => recordLiveSuccess());

        return () => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
            document.removeEventListener('visibilitychange', onVisibility);
            stopNetworkError();
            stopSuccess();
        };
    }, []);

    if (isDisconnected(connection)) {
        return 'offline';
    }

    return visible ? 'live' : 'paused';
}
