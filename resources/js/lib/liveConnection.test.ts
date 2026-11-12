import { beforeEach, describe, expect, it } from 'vitest';

import {
    backoffDelay,
    isDisconnected,
    liveConnectionSnapshot,
    MAX_BACKOFF_MS,
    recordBrowserOnline,
    recordLiveFailure,
    recordLiveSuccess,
    resetLiveConnection,
} from './liveConnection';

beforeEach(() => resetLiveConnection());

describe('backoffDelay', () => {
    it('is the interval while healthy, then doubles per failure', () => {
        expect(backoffDelay(4000, 0)).toBe(4000);
        expect(backoffDelay(4000, 1)).toBe(8000);
        expect(backoffDelay(4000, 2)).toBe(16_000);
        expect(backoffDelay(4000, 3)).toBe(32_000);
    });

    it('never waits longer than a minute, or less than the interval', () => {
        expect(backoffDelay(4000, 10)).toBe(MAX_BACKOFF_MS);
        expect(backoffDelay(120_000, 3)).toBe(120_000);
    });
});

describe('the live connection', () => {
    it('starts connected, as of the page load', () => {
        expect(isDisconnected(liveConnectionSnapshot())).toBe(false);
    });

    it('counts failures in a row and clears them on a success', () => {
        recordLiveFailure();
        recordLiveFailure();
        expect(liveConnectionSnapshot().failures).toBe(2);
        expect(isDisconnected(liveConnectionSnapshot())).toBe(true);

        recordLiveSuccess(1234);
        expect(liveConnectionSnapshot()).toMatchObject({
            failures: 0,
            lastSuccessAt: 1234,
        });
        expect(isDisconnected(liveConnectionSnapshot())).toBe(false);
    });

    it('is disconnected while the browser has no network', () => {
        recordBrowserOnline(false);
        expect(isDisconnected(liveConnectionSnapshot())).toBe(true);

        recordBrowserOnline(true);
        expect(isDisconnected(liveConnectionSnapshot())).toBe(false);
    });
});
