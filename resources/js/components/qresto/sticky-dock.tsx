import { useLayoutEffect, useRef } from 'react';
import type { ReactNode } from 'react';

import { checkDock } from '@/lib/non-negotiables';

type StickyDockProps = {
    children: ReactNode;
    className?: string;
};

/**
 * The guest phone's glass dock (#36, rule 01): translucent paper with a
 * blur, holding at most one primary action at 54px and full width.
 */
export function StickyDock({ children, className = '' }: StickyDockProps) {
    const dock = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => checkDock(dock.current));

    return (
        <div
            ref={dock}
            data-dock
            className={`sticky bottom-0 z-[15] shrink-0 border-t border-[var(--border-subtle)] bg-[var(--alpha-paper-88)] px-4 py-3 backdrop-blur-[12px] ${className}`}
        >
            {children}
        </div>
    );
}
