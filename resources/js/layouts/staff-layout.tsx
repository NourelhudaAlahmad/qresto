import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    Circle,
    LayoutGrid,
    LogOut,
    MoreHorizontal,
    UtensilsCrossed,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

type StaffNavItem = {
    id: string;
    title: string;
    href: string;
    badge?: number | null;
};

type StaffShellData = {
    nav?: StaffNavItem[];
    capabilities?: string[];
    staffOpenOrders?: number | null;
    staffUnreadNotifications?: number | null;

    auth?: {
        user?: {
            name: string;
            email: string;
            avatar?: string;
        } | null;
    };

    staffLayout?: {
        roleLabel?: string;
        restaurantName?: string;
        pageTitle?: string;
        pageSubtitle?: string;
        unreadNotifications?: number;
    };
};

const POLL_INTERVAL_MS = 15_000;

const NAV_DOT_COLORS: Record<string, string> = {
    waiter: 'bg-saffron-500',
    floor: 'bg-teal-500',
    manager: 'bg-clay-500',
    reports: 'bg-teal-700',
    admin: 'bg-clay-700',
    menu: 'bg-saffron-700',
    kds: 'bg-clay-500',
};

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatCount(count: number): string {
    return count > 99 ? '99+' : String(count);
}

function NavLink({
    item,
    active,
    badgeCount,
    mobile = false,
}: {
    item: StaffNavItem;
    active: boolean;
    badgeCount?: number | null;
    mobile?: boolean;
}) {
    const showBadge = badgeCount != null && badgeCount > 0;

    const dotColor = NAV_DOT_COLORS[item.id] ?? 'bg-clay-500';

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={
                mobile
                    ? `relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-1 py-2 text-[11px] transition-colors ${
                          active
                              ? 'text-clay-700'
                              : 'text-text-tertiary hover:text-text-primary'
                      }`
                   : `flex min-h-11 items-center justify-between rounded-lg px-3 text-sm transition-colors ${
      active
          ? 'bg-white/15 !text-white font-semibold'
          : '!text-white/85 hover:!text-white hover:bg-white/10'
  }`
            }
        >
            {mobile ? (
                <>
                    <span className="relative">
                        <LayoutGrid className="size-5" aria-hidden="true" />

                        {showBadge && (
                            <span className="bg-clay-100 text-clay-900 absolute -end-3 -top-2 rounded-full px-1 text-[10px] font-semibold">
                                {formatCount(badgeCount)}
                            </span>
                        )}
                    </span>

                    <span className="max-w-full truncate">{item.title}</span>
                </>
            ) : (
                <>
                    <span className="flex min-w-0 items-center gap-3">
                        <Circle
                            className={`size-2 shrink-0 rounded-full ${dotColor} fill-current text-transparent`}
                            aria-hidden="true"
                        />

                        <span className="truncate">{item.title}</span>
                    </span>

                    {showBadge && (
                        <span className="bg-clay-100 text-clay-900 rounded-full px-2 py-0.5 text-xs font-semibold">
                            {formatCount(badgeCount)}
                        </span>
                    )}
                </>
            )}
        </Link>
    );
}

export default function StaffLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();

    const data = props as typeof props & StaffShellData;

    const nav = data.nav ?? [];
    const user = data.auth?.user;
    const shell = data.staffLayout;

    const [moreOpen, setMoreOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const canSeeAllOrders =
        data.capabilities?.includes('see_all_orders') ?? false;

    const openOrders =
        canSeeAllOrders && typeof data.staffOpenOrders === 'number'
            ? data.staffOpenOrders
            : null;

    const unreadNotifications =
        data.staffUnreadNotifications ?? shell?.unreadNotifications ?? 0;

    // Refresh only the two live counters every 15 seconds.
    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({
                only: ['staffOpenOrders', 'staffUnreadNotifications'],
            });
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, []);

    const currentPath = url.split('?')[0].split('#')[0];

    const active = (href: string): boolean =>
        currentPath === href || currentPath.startsWith(`${href}/`);

    const badgeFor = (item: StaffNavItem): number | null => {
        if (item.href === '/app/orders' || item.href === '/app/pass') {
            return openOrders;
        }

        return null;
    };

    // Keep at most four bottom-bar slots, including More when needed.
    const visibleTabCount = nav.length > 4 ? 3 : 4;
    const primaryNav = nav.slice(0, visibleTabCount);
    const overflowNav = nav.slice(visibleTabCount);

    return (
        <div className="bg-surface-page font-ui text-text-primary min-h-[100dvh]">
            {/* Desktop sidebar */}
            <aside className="bg-ink-800 text-text-inverse fixed inset-y-0 start-0 hidden w-[248px] flex-col min-[900px]:flex">
                <div className="border-b border-white/10 px-5 py-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-2 text-xl font-bold tracking-tight">
                            <UtensilsCrossed
                                className="text-clay-500 size-5"
                                aria-hidden="true"
                            />

                            <span>
                                <span className="text-clay-500">Q</span>
                                Resto
                            </span>
                        </div>

                        <span className="text-text-inverse/70 rounded-full border border-white/15 px-2 py-0.5 text-[10px]">
                            {shell?.roleLabel ?? 'Staff'}
                        </span>
                    </div>
                </div>

                <nav
                    aria-label="Staff navigation"
                    className="flex-1 space-y-1 overflow-y-auto p-3"
                >
                    {nav.map((item) => (
                        <NavLink
                            key={item.id}
                            item={item}
                            active={active(item.href)}
                            badgeCount={badgeFor(item)}
                        />
                    ))}
                </nav>

                {/* Desktop user footer */}
                <div className="flex items-center gap-3 border-t border-white/10 p-4">
                    <span className="text-text-inverse flex size-9 shrink-0 items-center justify-center rounded-full bg-teal-700 text-xs font-semibold">
                        {initials(user?.name ?? 'Staff')}
                    </span>

                    <div className="min-w-0 flex-1">
 <p className="truncate text-sm font-semibold !text-white">
                            {user?.name ?? 'Staff'}
                        </p>

                        <p className="text-text-inverse/70 truncate text-xs">
                            {shell?.restaurantName ?? ''}
                        </p>
                    </div>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        aria-label="Log out"
                        title="Log out"
                        className="text-text-inverse/70 shrink-0 rounded-lg p-2 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <LogOut className="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </aside>

            {/* Main application area */}
            <div className="flex min-h-[100dvh] flex-col min-[900px]:ps-[248px]">
                {/* Topbar */}
                <header className="border-border-subtle bg-surface-card relative z-30 flex h-16 items-center justify-between gap-3 border-b px-4 min-[900px]:px-6">
                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-lg font-semibold">
                            {shell?.pageTitle ?? 'Staff'}
                        </h1>

                        <p className="text-text-secondary truncate text-xs">
                            {shell?.pageSubtitle ?? ''}
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                        {/* Live indicator */}
                        <span className="text-success-fg hidden items-center gap-2 text-xs font-medium sm:flex">
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-teal-500 opacity-40" />
                                <span className="relative inline-flex size-2 rounded-full bg-teal-700" />
                            </span>
                            Live
                        </span>

                        {/* Real open orders */}
                        {openOrders !== null && (
                            <span
                                aria-label={`Open orders: ${openOrders}`}
                                className="bg-clay-100 text-clay-700 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                            >
                                <span className="hidden sm:inline">
                                    Open orders
                                </span>

                                <span className="sm:hidden">Orders</span>

                                <strong>{formatCount(openOrders)}</strong>
                            </span>
                        )}

                        {/* Real unread notifications */}
                        <div
                            className="relative"
                            role="status"
                            aria-label={`Unread notifications: ${unreadNotifications}`}
                        >
                            <Bell className="size-5" aria-hidden="true" />

                            {unreadNotifications > 0 && (
                                <span className="bg-clay-100 text-clay-900 absolute -end-2 -top-2 rounded-full px-1 text-[10px] font-semibold">
                                    {formatCount(unreadNotifications)}
                                </span>
                            )}
                        </div>

                        {/* User avatar and menu */}
                        <div className="relative">
                            <button
                                type="button"
                                aria-label="User menu"
                                aria-expanded={userMenuOpen}
                                aria-controls="staff-user-menu"
                                onClick={() => {
                                    setUserMenuOpen((open) => !open);
                                    setMoreOpen(false);
                                }}
                                className="bg-clay-100 text-clay-700 hover:bg-clay-100 flex size-9 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                            >
                                {initials(user?.name ?? 'Staff')}
                            </button>

                            {userMenuOpen && (
                                <div
                                    id="staff-user-menu"
                                    className="border-border-subtle bg-surface-card absolute end-0 top-12 z-50 w-56 rounded-xl border p-2 shadow-xl"
                                >
                                    <div className="border-border-subtle border-b px-3 py-2">
                                        <p className="truncate text-sm font-semibold">
                                            {user?.name ?? 'Staff'}
                                        </p>

                                        <p className="text-text-secondary truncate text-xs">
                                            {user?.email ?? ''}
                                        </p>
                                    </div>

                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="text-text-link hover:bg-surface-page mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm"
                                    >
                                        <LogOut
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Log out
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Staff page content */}
                <main className="mx-auto w-full max-w-[1280px] flex-1 p-4 pb-[calc(5rem+env(safe-area-inset-bottom))] min-[900px]:p-6">
                    {children}
                </main>
            </div>

            {/* Mobile bottom tab bar */}
            {createPortal(
            <nav
                aria-label="Mobile staff navigation"
                className="border-border-subtle bg-surface-card fixed inset-x-0 bottom-0 z-[70] flex min-h-16 w-full items-center justify-around border-t px-2 pb-[env(safe-area-inset-bottom)] min-[900px]:hidden"
            >
                {primaryNav.map((item) => (
                    <NavLink
                        key={item.id}
                        item={item}
                        active={active(item.href)}
                        badgeCount={badgeFor(item)}
                        mobile
                    />
                ))}

                {overflowNav.length > 0 && (
                    <button
                        type="button"
                        aria-label="More navigation"
                        aria-expanded={moreOpen}
                        aria-controls="staff-more-nav"
                        onClick={() => {
                            setMoreOpen((open) => !open);
                            setUserMenuOpen(false);
                        }}
                        className="text-text-secondary hover:text-text-primary flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-lg px-1 py-2 text-[11px]"
                    >
                        <MoreHorizontal className="size-5" aria-hidden="true" />
                        More
                    </button>
                )}
            </nav>,
            document.body
            )}

            {/* Mobile More sheet */}
            {moreOpen && overflowNav.length > 0 && (
                <>
                    <button
                        type="button"
                        aria-label="Close more navigation"
                        onClick={() => setMoreOpen(false)}
                        className="fixed inset-0 z-40 bg-black/20 min-[900px]:hidden"
                    />

                    <div
                        id="staff-more-nav"
                        role="dialog"
                        aria-label="More navigation"
                        className="border-border-subtle bg-surface-card fixed inset-x-3 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-50 rounded-xl border p-3 shadow-xl min-[900px]:hidden"
                    >
                        <div className="mb-2 flex items-center justify-between px-2">
                            <h2 className="text-sm font-semibold">More</h2>

                            <button
                                type="button"
                                aria-label="Close"
                                onClick={() => setMoreOpen(false)}
                                className="text-text-secondary hover:bg-surface-page rounded-lg p-1"
                            >
                                <X className="size-5" aria-hidden="true" />
                            </button>
                        </div>

                        <nav className="flex flex-col gap-1">
                            {overflowNav.map((item) => {
                                const badge = badgeFor(item);

                                return (
                                    <Link
                                        key={item.id}
                                        href={item.href}
                                        aria-current={
                                            active(item.href)
                                                ? 'page'
                                                : undefined
                                        }
                                        onClick={() => setMoreOpen(false)}
                                        className="hover:bg-surface-page flex items-center justify-between rounded-lg px-4 py-3 text-sm"
                                    >
                                        <span>{item.title}</span>

                                        {badge !== null && badge > 0 && (
                                            <span className="bg-clay-100 text-clay-700 rounded-full px-2 py-0.5 text-xs font-semibold">
                                                {formatCount(badge)}
                                            </span>
                                        )}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>
                </>
            )}
        </div>
    );
}
