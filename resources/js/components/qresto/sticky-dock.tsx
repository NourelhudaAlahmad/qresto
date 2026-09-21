import type { ReactNode } from 'react';

type StickyDockProps = {
    children: ReactNode;
    className?: string;
};

export function StickyDock({ children, className = '' }: StickyDockProps) {
    return (
        <div
            className={`shrink-0 border-t border-[var(--border-subtle)] bg-[var(--surface-page)] px-4 py-3 ${className} `}
        >
            {children}
        </div>
    );
}
