import { router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/qresto/badge';
import { formatMinutes } from '@/lib/elapsed';

export type StaffNotification = {
    id: string;
    type: string;
    title: string;
    message: string;
    url: string | null;
    read: boolean;
    created_at: string | null;
};

const REFRESH_COUNT = {
    preserveScroll: true,
    preserveState: true,
    only: ['staffUnreadNotifications'],
};

/** "just now", "4 min ago", "2h 10m ago" */
export function ago(iso: string | null, now = Date.now()): string {
    if (!iso) {
        return '';
    }

    const minutes = Math.max(0, Math.floor((now - Date.parse(iso)) / 60_000));

    return minutes < 1 ? 'just now' : `${formatMinutes(minutes)} ago`;
}

function count(n: number): string {
    return n > 99 ? '99+' : String(n);
}

/**
 * The topbar bell. Shows the unread count, and opens the person's own
 * notifications: a system-health alert, a voided order, a locked account.
 * Opening a notification marks it read and goes where it points.
 */
export function NotificationBell({ unread }: { unread: number }) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<StaffNotification[] | null>(null);
    const [failed, setFailed] = useState(false);
    const panel = useRef<HTMLDivElement>(null);

    const load = useCallback(async () => {
        setFailed(false);

        try {
            const response = await fetch('/app/notifications', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const data = (await response.json()) as {
                notifications: StaffNotification[];
            };

            setItems(data.notifications);
        } catch {
            setFailed(true);
        }
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !panel.current?.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    function openItem(item: StaffNotification) {
        const go = () => {
            setOpen(false);

            if (item.url) {
                router.visit(item.url);
            }
        };

        if (item.read) {
            go();

            return;
        }

        setItems((current) =>
            (current ?? []).map((other) =>
                other.id === item.id ? { ...other, read: true } : other,
            ),
        );

        router.post(
            `/app/notifications/${item.id}/read`,
            {},
            {
                ...REFRESH_COUNT,
                onSuccess: go,
            },
        );
    }

    function readAll() {
        setItems((current) =>
            (current ?? []).map((item) => ({ ...item, read: true })),
        );

        router.post('/app/notifications/read-all', {}, REFRESH_COUNT);
    }

    return (
        <div ref={panel} className="relative shrink-0">
            <button
                type="button"
                aria-label={
                    unread > 0
                        ? `Notifications, ${unread} unread`
                        : 'Notifications'
                }
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => {
                    // Load fresh every time the list opens.
                    if (!open) {
                        void load();
                    }

                    setOpen(!open);
                }}
                className="relative grid size-11 place-items-center rounded-full border border-[var(--border-default)] bg-[var(--surface-card)] outline-none hover:bg-[var(--action-ghost-hover)] focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)]"
            >
                <Bell
                    className="size-[19px] text-[var(--ink-800)]"
                    aria-hidden="true"
                />

                {unread > 0 && (
                    <Badge className="absolute -end-0.5 -top-0.5 h-[18px]">
                        {count(unread)}
                    </Badge>
                )}
            </button>

            {open && (
                <div
                    role="dialog"
                    aria-label="Notifications"
                    className="absolute end-0 top-12 z-50 flex max-h-[min(480px,70dvh)] w-[min(360px,calc(100vw-32px))] flex-col overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-pop)]"
                >
                    <div className="flex items-center gap-2 border-b border-[var(--border-subtle)] px-4 py-3">
                        <h2 className="font-display text-title-3 flex-1 font-semibold">
                            Notifications
                        </h2>

                        {items?.some((item) => !item.read) && (
                            <button
                                type="button"
                                onClick={readAll}
                                className="text-label flex min-h-9 items-center gap-1 rounded-[var(--radius-sm)] px-2 font-semibold text-[var(--text-link)] hover:bg-[var(--action-ghost-hover)]"
                            >
                                <CheckCheck className="size-4" aria-hidden />
                                Mark all read
                            </button>
                        )}
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {failed ? (
                            <p className="text-body p-4 text-[var(--text-secondary)]">
                                Couldn’t load notifications.{' '}
                                <button
                                    type="button"
                                    className="font-semibold text-[var(--text-link)]"
                                    onClick={() => void load()}
                                >
                                    Try again
                                </button>
                            </p>
                        ) : items === null ? (
                            <p className="text-body p-4 text-[var(--text-tertiary)]">
                                Loading…
                            </p>
                        ) : items.length === 0 ? (
                            <p className="text-body p-6 text-center text-[var(--text-tertiary)]">
                                You’re all caught up.
                            </p>
                        ) : (
                            <ul>
                                {items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="border-b border-[var(--border-subtle)] last:border-0"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => openItem(item)}
                                            data-unread={
                                                !item.read || undefined
                                            }
                                            className={`flex w-full gap-3 px-4 py-3 text-start hover:bg-[var(--action-ghost-hover)] ${
                                                item.read
                                                    ? ''
                                                    : 'bg-[var(--clay-50)]'
                                            }`}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={`mt-1.5 size-2 shrink-0 rounded-full ${
                                                    item.read
                                                        ? 'bg-transparent'
                                                        : 'bg-[var(--clay-500)]'
                                                }`}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="text-label block font-semibold">
                                                    {item.title}
                                                    {!item.read && (
                                                        <span className="sr-only">
                                                            {' '}
                                                            (unread)
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="text-body block text-[var(--text-secondary)]">
                                                    {item.message}
                                                </span>
                                                <span className="text-micro mt-0.5 block font-mono text-[var(--text-tertiary)]">
                                                    {ago(item.created_at)}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
