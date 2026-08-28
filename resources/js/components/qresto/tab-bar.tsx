import type { ReactNode } from 'react';

type TabBarItem = {
    label: ReactNode;
    icon?: ReactNode;
    badge?: ReactNode;
    active?: boolean;
    onClick?: () => void;
};

type TabBarProps = {
    items: TabBarItem[];
    className?: string;
};

export function TabBar({ items, className = '' }: TabBarProps) {
    return (
        <nav
            className={`sticky bottom-0 z-[14] flex h-[var(--tabbar-h)] border-t border-[var(--border-subtle)] bg-[var(--surface-card)] pb-2 ${className}`}
        >
            {items.map((item, index) => (
                <button
                    key={index}
                    type="button"
                    onClick={item.onClick}
                    className={`relative flex flex-1 flex-col items-center justify-center gap-1 border-none bg-transparent ${
                        item.active
                            ? 'text-[var(--clay-600)]'
                            : 'text-[var(--text-tertiary)]'
                    }`}
                >
                    {item.icon !== undefined && (
                        <span className="relative">
                            {item.icon}

                            {item.badge !== undefined && (
                                <span className="absolute -right-2.5 -top-1.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-[var(--clay-500)] px-1 font-mono text-[10px] text-white">
                                    {item.badge}
                                </span>
                            )}
                        </span>
                    )}

                    <span className="text-micro font-medium">{item.label}</span>
                </button>
            ))}
        </nav>
    );
}
