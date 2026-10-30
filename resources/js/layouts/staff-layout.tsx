import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChefHat,
    ClipboardList,
    LayoutDashboard,
    LayoutGrid,
    LogOut,
    MoreHorizontal,
    Settings2,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

import { Avatar } from '@/components/qresto/avatar';
import { Badge } from '@/components/qresto/badge';
import { LiveIndicator } from '@/components/qresto/LiveIndicator';
import type { LiveStatus } from '@/components/qresto/LiveIndicator';
import { NotificationBell } from '@/components/qresto/notification-bell';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { TabBar } from '@/components/qresto/tab-bar';
import { useConnectionStatus } from '@/hooks/useConnectionStatus';
import { useLiveData } from '@/hooks/useLiveData';

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

const COUNTER_PROPS = ['staffOpenOrders', 'staffUnreadNotifications'];

const COUNTER_INTERVAL_MS = 15_000;

// The design's NAV colour dots, per item id.
const NAV_DOTS: Record<string, string> = {
    manager: 'var(--clay-400)',
    admin: 'var(--clay-400)',
    kds: 'var(--clay-400)',
    waiter: 'var(--saffron-300)',
    menu: 'var(--saffron-300)',
    floor: 'var(--teal-300)',
    reports: 'var(--herb-300)',
};

const NAV_ICONS: Record<string, typeof LayoutGrid> = {
    manager: LayoutDashboard,
    admin: Settings2,
    kds: ChefHat,
    waiter: ClipboardList,
    menu: UtensilsCrossed,
    floor: LayoutGrid,
    reports: BarChart3,
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

function serviceDateLabel(now?: number): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
    }).formatToParts(now ? new Date(now) : new Date());

    const pick = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${pick('weekday')} ${pick('day')} ${pick('month')}`;
}

function SidebarLink({
    item,
    active,
    badge,
}: {
    item: StaffNavItem;
    active: boolean;
    badge: number | null;
}) {
    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={`text-body flex min-h-11 w-full items-center gap-3 rounded-[var(--radius-md)] px-3 transition-colors duration-[var(--duration-fast)] ${
                active
                    ? 'bg-[var(--ink-800)] font-semibold !text-[var(--ink-25)]'
                    : 'hover:bg-[var(--ink-800)]/60 !text-[var(--ink-300)] hover:!text-[var(--ink-25)]'
            }`}
        >
            <span
                aria-hidden="true"
                className={`size-2 shrink-0 rounded-full ${
                    active ? 'opacity-100' : 'opacity-55'
                }`}
                style={{
                    backgroundColor: NAV_DOTS[item.id] ?? 'var(--ink-500)',
                }}
            />

            <span className="flex-1 truncate text-start">{item.title}</span>

            {badge !== null && badge > 0 && (
                <Badge className="h-5">{formatCount(badge)}</Badge>
            )}
        </Link>
    );
}

function Sidebar({
    nav,
    isActive,
    badgeFor,
    roleLabel,
    restaurantName,
    userName,
}: {
    nav: StaffNavItem[];
    isActive: (href: string) => boolean;
    badgeFor: (item: StaffNavItem) => number | null;
    roleLabel: string;
    restaurantName: string;
    userName: string;
}) {
    return (
        <aside className="fixed inset-y-0 start-0 hidden w-[var(--sidebar-w)] flex-col bg-[var(--ink-900)] px-3 pb-4 pt-5 min-[900px]:flex">
            <div className="flex items-center gap-2 px-3 pb-5">
                <span className="font-display inline-flex items-baseline text-[19px] font-bold tracking-[-0.04em] text-[var(--ink-25)]">
                    <span className="text-[var(--clay-300)]">Q</span>
                    <span>Resto</span>
                </span>

                <span className="text-micro rounded-[var(--radius-pill)] border border-[var(--ink-700)] px-[7px] py-0.5 text-[var(--ink-500)]">
                    {roleLabel}
                </span>
            </div>

            <nav
                aria-label="Staff navigation"
                className="flex flex-1 flex-col gap-0.5 overflow-y-auto"
            >
                {nav.map((item) => (
                    <SidebarLink
                        key={item.id}
                        item={item}
                        active={isActive(item.href)}
                        badge={badgeFor(item)}
                    />
                ))}
            </nav>

            <div className="flex items-center gap-2 border-t border-[var(--ink-800)] p-3">
                <Avatar initials={initials(userName)} size="sm" tone="teal" />

                <span className="min-w-0 flex-1">
                    <span className="text-caption block truncate text-[var(--ink-200)]">
                        {userName}
                    </span>
                    <span className="text-micro block truncate text-[var(--ink-500)]">
                        {restaurantName}
                    </span>
                </span>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    aria-label="Log out"
                    title="Log out"
                    className="grid size-11 shrink-0 place-items-center rounded-full text-[var(--ink-500)] transition-colors hover:text-[var(--ink-25)]"
                >
                    <LogOut className="size-4" aria-hidden="true" />
                </Link>
            </div>
        </aside>
    );
}

function Topbar({
    liveStatus,
    title,
    subtitle,
    unreadNotifications,
    userName,
    userEmail,
}: {
    liveStatus: LiveStatus;
    title: string;
    subtitle: string;
    unreadNotifications: number;
    userName: string;
    userEmail: string;
}) {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <header className="sticky top-0 z-20 flex min-h-[var(--topbar-h)] items-center gap-3 border-b border-[var(--border-subtle)] bg-[var(--surface-glass)] px-4 backdrop-blur-[12px] min-[900px]:gap-4 min-[900px]:px-6">
            <div className="min-w-0 flex-1">
                <h1 className="font-display text-title-1 truncate font-semibold tracking-[var(--tracking-title)]">
                    {title}
                </h1>

                {subtitle && (
                    <p className="text-caption truncate text-[var(--text-tertiary)]">
                        {subtitle}
                    </p>
                )}
            </div>

            <span className="hidden sm:inline-flex">
                <LiveIndicator status={liveStatus} />
            </span>

            <NotificationBell unread={unreadNotifications} />

            <div className="relative shrink-0">
                <button
                    type="button"
                    aria-label="User menu"
                    aria-expanded={menuOpen}
                    aria-controls="staff-user-menu"
                    onClick={() => setMenuOpen((open) => !open)}
                    className="grid size-11 place-items-center rounded-full"
                >
                    <Avatar initials={initials(userName)} />
                </button>

                {menuOpen && (
                    <div
                        id="staff-user-menu"
                        className="absolute end-0 top-12 z-50 w-56 rounded-[var(--radius-md)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-2 shadow-[var(--shadow-pop)]"
                    >
                        <div className="border-b border-[var(--border-subtle)] px-3 py-2">
                            <p className="text-body truncate font-semibold">
                                {userName}
                            </p>
                            <p className="text-caption truncate text-[var(--text-secondary)]">
                                {userEmail}
                            </p>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="text-body mt-1 flex min-h-11 w-full items-center gap-2 rounded-[var(--radius-sm)] px-3 text-start text-[var(--text-link)] hover:bg-[var(--action-ghost-hover)]"
                        >
                            <LogOut className="size-4" aria-hidden="true" />
                            Log out
                        </Link>
                    </div>
                )}
            </div>
        </header>
    );
}

/**
 * One shell for every staff role and width (design: the isApp frame).
 * Sidebar ≥900px; below it the sidebar becomes a bottom tab bar with at
 * most four slots, and anything else moves into a More sheet.
 *
 * Pages may set `Page.layout = { variant: 'console' }` to replace the
 * server subtitle with the service date, as the manager overview does.
 */
export default function StaffLayout({
    children,
    variant = 'default',
    now,
}: {
    children: ReactNode;
    variant?: 'default' | 'console';
    now?: number;
}) {
    const { url, props } = usePage();
    const data = props as typeof props & StaffShellData;

    const nav = data.nav ?? [];
    const user = data.auth?.user;
    const shell = data.staffLayout;
    const userName = user?.name ?? 'Staff';

    const [moreOpen, setMoreOpen] = useState(false);

    const canSeeAllOrders =
        data.capabilities?.includes('see_all_orders') ?? false;

    const openOrders =
        canSeeAllOrders && typeof data.staffOpenOrders === 'number'
            ? data.staffOpenOrders
            : null;

    const unreadNotifications =
        data.staffUnreadNotifications ?? shell?.unreadNotifications ?? 0;

    const liveStatus = useConnectionStatus();

    // The two shell counters refresh on their own slow cadence.
    useLiveData(COUNTER_INTERVAL_MS, { only: COUNTER_PROPS });

    const currentPath = url.split('?')[0].split('#')[0];

    const isActive = (href: string): boolean =>
        currentPath === href || currentPath.startsWith(`${href}/`);

    const badgeFor = (item: StaffNavItem): number | null =>
        item.href === '/app/orders' || item.href === '/app/pass'
            ? openOrders
            : null;

    // At most four bottom-bar slots, including More when needed.
    const visibleTabCount = nav.length > 4 ? 3 : 4;
    const primaryNav = nav.slice(0, visibleTabCount);
    const overflowNav = nav.slice(visibleTabCount);
    const overflowActive = overflowNav.some((item) => isActive(item.href));

    const tabIcon = (item: StaffNavItem) => {
        const Icon = NAV_ICONS[item.id] ?? LayoutGrid;

        return <Icon className="size-5" aria-hidden="true" />;
    };

    const tabItems = [
        ...primaryNav.map((item) => {
            const badge = badgeFor(item);

            return {
                label: item.title,
                icon: tabIcon(item),
                badge:
                    badge !== null && badge > 0
                        ? formatCount(badge)
                        : undefined,
                active: isActive(item.href),
                onClick: () => router.visit(item.href),
            };
        }),
        ...(overflowNav.length > 0
            ? [
                  {
                      label: 'More',
                      icon: (
                          <MoreHorizontal
                              className="size-5"
                              aria-hidden="true"
                          />
                      ),
                      active: overflowActive,
                      ariaLabel: 'More navigation',
                      onClick: () => setMoreOpen(true),
                  },
              ]
            : []),
    ];

    const subtitle =
        variant === 'console'
            ? `${serviceDateLabel(now)} · service in progress`
            : (shell?.pageSubtitle ?? '');

    return (
        <div className="font-ui min-h-[100dvh] bg-[var(--surface-page)] text-[var(--text-primary)]">
            <Sidebar
                nav={nav}
                isActive={isActive}
                badgeFor={badgeFor}
                roleLabel={shell?.roleLabel ?? 'Staff'}
                restaurantName={shell?.restaurantName ?? ''}
                userName={userName}
            />

            <div className="flex min-h-[100dvh] flex-col min-[900px]:ps-[var(--sidebar-w)]">
                <Topbar
                    liveStatus={liveStatus}
                    title={shell?.pageTitle ?? 'Staff'}
                    subtitle={subtitle}
                    unreadNotifications={unreadNotifications}
                    userName={userName}
                    userEmail={user?.email ?? ''}
                />

                <main className="mx-auto w-full max-w-[1280px] flex-1 p-4 pb-[calc(var(--tabbar-h)+var(--space-4)+env(safe-area-inset-bottom))] min-[900px]:p-6 min-[900px]:pb-10">
                    {children}
                </main>
            </div>

            {nav.length > 0 &&
                createPortal(
                    <TabBar
                        ariaLabel="Mobile staff navigation"
                        items={tabItems}
                        className="fixed inset-x-0 bottom-0 z-[70] h-auto min-h-[var(--tabbar-h)] pb-[env(safe-area-inset-bottom)] min-[900px]:hidden"
                    />,
                    document.body,
                )}

            <ResponsiveOverlay
                open={moreOpen && overflowNav.length > 0}
                onOpenChange={setMoreOpen}
                title="More"
            >
                <nav
                    aria-label="More navigation"
                    className="flex flex-col gap-1"
                >
                    {overflowNav.map((item) => {
                        const badge = badgeFor(item);

                        return (
                            <Link
                                key={item.id}
                                href={item.href}
                                aria-current={
                                    isActive(item.href) ? 'page' : undefined
                                }
                                onClick={() => setMoreOpen(false)}
                                className="text-body flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-3 hover:bg-[var(--action-ghost-hover)]"
                            >
                                {tabIcon(item)}
                                <span className="flex-1">{item.title}</span>
                                {badge !== null && badge > 0 && (
                                    <Badge className="h-5">
                                        {formatCount(badge)}
                                    </Badge>
                                )}
                            </Link>
                        );
                    })}
                </nav>
            </ResponsiveOverlay>
        </div>
    );
}
