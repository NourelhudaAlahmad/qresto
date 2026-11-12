/**
 * Whether the live data on screen can be trusted (#58).
 *
 * Every poll (useLiveData) and every Inertia visit reports here, so the
 * topbar pill, the pass banner and the guest status screen all tell the
 * same truth: live, or how long ago the last good answer arrived.
 */
export type LiveConnectionState = {
    /** Requests in a row that failed to get an answer. 0 when healthy. */
    failures: number;
    /** When the data on screen was last confirmed, in ms since the epoch. */
    lastSuccessAt: number;
    /** navigator.onLine: a fast path for the obvious case, never the only one. */
    browserOnline: boolean;
};

type Listener = () => void;

const listeners = new Set<Listener>();

function initialState(): LiveConnectionState {
    return {
        failures: 0,
        // The page itself was rendered from fresh data.
        lastSuccessAt: Date.now(),
        browserOnline:
            typeof navigator === 'undefined' ? true : navigator.onLine,
    };
}

let state: LiveConnectionState = initialState();

function update(next: Partial<LiveConnectionState>): void {
    state = { ...state, ...next };
    listeners.forEach((listener) => listener());
}

export function subscribeLiveConnection(listener: Listener): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function liveConnectionSnapshot(): LiveConnectionState {
    return state;
}

export function recordLiveSuccess(at: number = Date.now()): void {
    update({ failures: 0, lastSuccessAt: at, browserOnline: true });
}

export function recordLiveFailure(): void {
    update({ failures: state.failures + 1 });
}

export function recordBrowserOnline(online: boolean): void {
    if (online !== state.browserOnline) {
        update({ browserOnline: online });
    }
}

/** Disconnected: the last request failed, or the device has no network. */
export function isDisconnected(connection: LiveConnectionState): boolean {
    return connection.failures > 0 || !connection.browserOnline;
}

/**
 * The wait before the next poll: the normal interval while healthy, then
 * doubling with each failure in a row, capped, so a down server is not
 * hit every few seconds by every open screen.
 */
export const MAX_BACKOFF_MS = 60_000;

export function backoffDelay(intervalMs: number, failures: number): number {
    if (failures <= 0) {
        return intervalMs;
    }

    return Math.min(
        intervalMs * 2 ** failures,
        Math.max(MAX_BACKOFF_MS, intervalMs),
    );
}

/** Tests only: back to a fresh page. */
export function resetLiveConnection(): void {
    state = initialState();
    listeners.forEach((listener) => listener());
}
