import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { useLiveAlertMute } from './useLiveAlertMute';

describe('useLiveAlertMute', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('starts unmuted when no preference is stored', () => {
        const { result } = renderHook(() => useLiveAlertMute());

        expect(result.current.muted).toBe(false);
    });

    it('loads the stored mute preference', () => {
        window.localStorage.setItem('qresto_live_alerts_muted', 'true');

        const { result } = renderHook(() => useLiveAlertMute());

        expect(result.current.muted).toBe(true);
    });

    it('toggles and persists the mute preference', () => {
        const { result } = renderHook(() => useLiveAlertMute());

        act(() => {
            result.current.toggleMute();
        });

        expect(result.current.muted).toBe(true);
        expect(window.localStorage.getItem('qresto_live_alerts_muted')).toBe(
            'true',
        );

        act(() => {
            result.current.toggleMute();
        });

        expect(result.current.muted).toBe(false);
        expect(window.localStorage.getItem('qresto_live_alerts_muted')).toBe(
            'false',
        );
    });
});
