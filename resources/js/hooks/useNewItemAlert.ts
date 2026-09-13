import { useEffect, useRef } from 'react';

import { showToast } from '@/components/qresto/toast';

type NewItemAlertOptions = {
    title?: string;
    description?: string;
    muted?: boolean;
};

function playAlertSound(): void {
    try {
        const AudioContext =
            window.AudioContext ||
            (
                window as typeof window & {
                    webkitAudioContext?: typeof window.AudioContext;
                }
            ).webkitAudioContext;

        if (!AudioContext) {
            return;
        }

        const audioContext = new AudioContext();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.connect(gain);
        gain.connect(audioContext.destination);

        oscillator.frequency.value = 880;
        oscillator.type = 'sine';

        gain.gain.setValueAtTime(0.08, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(
            0.001,
            audioContext.currentTime + 0.2,
        );

        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.2);
    } catch {
        // Audio is optional; the toast should still appear.
    }
}

export function useNewItemAlert<T>(
    collection: T[],
    keyFn: (item: T) => string | number,
    options: NewItemAlertOptions = {},
): void {
    const {
        title = 'New order',
        description = 'A new order has been received.',
        muted = false,
    } = options;

    const seenKeys = useRef<Set<string | number>>(new Set());
    const initialized = useRef(false);

    useEffect(() => {
        const currentKeys = new Set(collection.map(keyFn));

        if (!initialized.current) {
            seenKeys.current = currentKeys;
            initialized.current = true;

            return;
        }

        const newItems = collection.filter(
            (item) => !seenKeys.current.has(keyFn(item)),
        );

        seenKeys.current = currentKeys;

        if (newItems.length === 0) {
            return;
        }

        showToast({
            type: 'info',
            title,
            description,
        });

        if (muted) {
            return;
        }

        const reducedMotion =
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
            false;

        if (reducedMotion) {
            return;
        }

        playAlertSound();
    }, [collection, keyFn, title, description, muted]);
}
