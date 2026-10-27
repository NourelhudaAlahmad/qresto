import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type TabBarItem = {
    label: ReactNode;
    icon?: ReactNode;
    badge?: ReactNode;
    active?: boolean;
    disabled?: boolean;
    onClick?: () => void;
    ariaLabel?: string;
};

type TabBarProps = {
    items: TabBarItem[];
    className?: string;
    ariaLabel?: string;
};

export function TabBar({ items, className = '', ariaLabel }: TabBarProps) {
    return (
        <nav
            aria-label={ariaLabel}
            className={cn(
                'sticky bottom-0 z-[14] flex h-[var(--tabbar-h)] border-t border-[var(--border-subtle)] bg-[var(--surface-card)] pb-2',
                className,
            )}
        >
            {items.map((item, index) => (
                <button
                    key={index}
                    type="button"
                    onClick={item.onClick}
                    disabled={item.disabled}
                    aria-label={item.ariaLabel}
                    aria-current={item.active ? 'page' : undefined}
                    className={`relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 border-none bg-transparent disabled:cursor-not-allowed disabled:opacity-40 ${
                        item.active
                            ? 'text-[var(--clay-600)]'
                            : 'text-[var(--text-tertiary)]'
                    }`}
                >
                    {item.icon !== undefined && (
                        <span className="relative">
                            {item.icon}

                            {item.badge !== undefined && (
                                <span
                                    key={String(item.badge)}
                                    className="cart-badge-bump absolute -end-2.5 -top-1.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-[var(--clay-500)] px-1 font-mono text-[10px] text-white"
                                >
                                    {item.badge}
                                </span>
                            )}
                        </span>
                    )}

                    <span className="text-micro max-w-full truncate font-medium">
                        {item.label}
                    </span>
                </button>
            ))}
        </nav>
    );
}
