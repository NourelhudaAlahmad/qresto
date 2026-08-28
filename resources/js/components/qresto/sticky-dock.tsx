import type { ReactNode } from 'react';

type StickyDockProps = {
    children: ReactNode;
    className?: string;
};

export function StickyDock({ children, className = '' }: StickyDockProps) {
    return (
        <div
            className={`sticky bottom-0 z-[15] border-t border-[var(--border-subtle)] bg-[var(--alpha-paper-88)] px-3 py-3 backdrop-blur-[12px] ${className}`}
        >
            {children}
        </div>
    );
}
