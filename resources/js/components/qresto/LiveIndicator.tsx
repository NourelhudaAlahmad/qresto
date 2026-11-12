import { useMinutesSince } from '@/hooks/useLiveConnection';
import { formatMinutes } from '@/lib/elapsed';
import { fill, useI18n } from '@/lib/i18n';

export type LiveStatus = 'live' | 'paused' | 'offline';

type LiveIndicatorProps = {
    /** Preferred: the connection status from useConnectionStatus(). */
    status?: LiveStatus;
    /** Older call sites: true reads as "paused". */
    isPaused?: boolean;
    /**
     * When the data on screen was last confirmed (ms since the epoch).
     * Offline, the pill says how stale it is (#58).
     */
    lastUpdatedAt?: number;
};

const DOTS: Record<LiveStatus, string> = {
    live: 'bg-[var(--herb-500)]',
    paused: 'bg-[var(--ink-400)]',
    offline: 'bg-[var(--berry-500)]',
};

/** "updated just now" / "updated 4 min ago", in the page language. */
export function useUpdatedAgo(lastUpdatedAt: number): string {
    const { staff: t } = useI18n();
    const minutes = useMinutesSince(lastUpdatedAt);

    return minutes < 1
        ? t.live.updated_just_now
        : fill(t.live.updated_ago, { time: formatMinutes(minutes) });
}

export function LiveIndicator({
    status,
    isPaused = false,
    lastUpdatedAt,
}: LiveIndicatorProps) {
    const { staff: t } = useI18n();
    const current: LiveStatus = status ?? (isPaused ? 'paused' : 'live');
    // A stable fallback: the text is only shown with a real timestamp.
    const updatedAgo = useUpdatedAgo(lastUpdatedAt ?? 0);
    const showStaleness = current === 'offline' && lastUpdatedAt !== undefined;

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

            <span>{t.live[current]}</span>

            {showStaleness && (
                <span data-testid="live-staleness" className="font-normal">
                    · {updatedAgo}
                </span>
            )}
        </div>
    );
}
