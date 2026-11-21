/**
 * localStorage that never throws.
 *
 * Reading or writing it throws when the browser blocks site data (Safari
 * with storage blocked, some in-app webviews, a full quota). What is kept
 * here is only a convenience (a remembered name, tab or view), so a page
 * must keep working without it: a read gives null, a write does nothing.
 */
export function readStorage(key: string): string | null {
    try {
        return typeof window === 'undefined'
            ? null
            : window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

export function writeStorage(key: string, value: string): void {
    try {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Not remembered this time; nothing else depends on it.
    }
}
