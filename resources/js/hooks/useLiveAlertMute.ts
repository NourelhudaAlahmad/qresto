import { useState } from 'react';

const STORAGE_KEY = 'qresto_live_alerts_muted';

export function useLiveAlertMute() {
    const [muted, setMuted] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.localStorage.getItem(STORAGE_KEY) === 'true';
    });

    const toggleMute = () => {
        setMuted((current) => {
            const next = !current;

            window.localStorage.setItem(STORAGE_KEY, String(next));

            return next;
        });
    };

    return {
        muted,
        toggleMute,
    };
}
