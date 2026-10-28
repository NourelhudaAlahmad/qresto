export type LiveStatus = 'live' | 'paused' | 'offline';

type LiveIndicatorProps = {
    /** Preferred: the connection status from useConnectionStatus(). */
    status?: LiveStatus;
    /** Older call sites: true reads as "paused". */
    isPaused?: boolean;
};

const LABELS: Record<LiveStatus, string> = {
    live: 'Live',
    paused: 'Paused',
    offline: 'Offline',
};

const DOTS: Record<LiveStatus, string> = {
    live: 'bg-[var(--herb-500)]',
    paused: 'bg-[var(--ink-400)]',
    offline: 'bg-[var(--berry-500)]',
};

export function LiveIndicator({
    status,
    isPaused = false,
}: LiveIndicatorProps) {
    const current: LiveStatus = status ?? (isPaused ? 'paused' : 'live');

    return (
        <div
            className={`text-label inline-flex items-center gap-2 rounded-full border px-3 py-1 ${
                current === 'offline'
                    ? 'border-[var(--berry-500)] text-[var(--berry-500)]'
                    : 'border-[var(--border-default)] text-[var(--text-primary)]'
            }`}
            role="status"
            aria-live="polite"
            data-status={current}
        >
            <span className="relative flex size-2" aria-hidden="true">
                {current === 'live' && (
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-[var(--herb-500)] opacity-75 motion-reduce:animate-none" />
                )}

                <span
                    className={`relative inline-flex size-2 rounded-full ${DOTS[current]}`}
                />
            </span>

            <span>{LABELS[current]}</span>
        </div>
    );
}
