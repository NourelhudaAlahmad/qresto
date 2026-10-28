import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import type { LiveStatus } from '@/components/qresto/LiveIndicator';

/**
 * What the "Live" pill in the staff topbar should say.
 *
 * - offline: the browser reports no network, or the last request failed to
 *   reach the server. Cleared by the next successful response.
 * - paused: the tab is in the background, so polling has stopped.
 * - live: otherwise.
 */
export function useConnectionStatus(): LiveStatus {
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const [visible, setVisible] = useState(() =>
        typeof document === 'undefined' ? true : !document.hidden,
    );
    const [requestFailed, setRequestFailed] = useState(false);

    useEffect(() => {
        const goOnline = () => setOnline(true);
        const goOffline = () => setOnline(false);
        const onVisibility = () => setVisible(!document.hidden);

        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        document.addEventListener('visibilitychange', onVisibility);

        const stopNetworkError = router.on('networkError', () =>
            setRequestFailed(true),
        );
        const stopSuccess = router.on('success', () => setRequestFailed(false));

        return () => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
            document.removeEventListener('visibilitychange', onVisibility);
            stopNetworkError();
            stopSuccess();
        };
    }, []);

    if (!online || requestFailed) {
        return 'offline';
    }

    return visible ? 'live' : 'paused';
}
