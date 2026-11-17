import en from '@/i18n/en.json';
import { fill } from '@/types/checkout';

/** The shared time words (lang/{locale}/common.php) these formatters use. */
export type ElapsedCopy = Pick<
    typeof en.common,
    'just_now' | 'minutes' | 'hour_unit' | 'minute_unit' | 'ago'
>;

/**
 * Elapsed time as staff read it: "14 min" under an hour, "19h 27m" after,
 * in the page language ("14 د", "19س 27د"). One formatter for the kanban,
 * the order list and the manager overview, so a ticket never reads
 * "1167 min" in one place and "19h 27m" in another.
 *
 * Pass the page's `common` copy from useI18n(); English is the fallback.
 */
export function formatMinutes(
    minutes: number | null | undefined,
    copy: ElapsedCopy = en.common,
): string {
    const total = Math.max(0, Math.floor(minutes ?? 0));

    if (total < 60) {
        return fill(copy.minutes, { count: total });
    }

    return `${Math.floor(total / 60)}${copy.hour_unit} ${total % 60}${copy.minute_unit}`;
}

/**
 * How long ago a timestamp was: "just now", "4 min ago", "2h 10m ago".
 * Shared by the notification bell and the system page's "last seen".
 */
export function formatAgo(
    iso: string,
    now: number = Date.now(),
    copy: ElapsedCopy = en.common,
): string {
    const minutes = Math.max(0, Math.floor((now - Date.parse(iso)) / 60_000));

    return minutes < 1
        ? copy.just_now
        : fill(copy.ago, { time: formatMinutes(minutes, copy) });
}
