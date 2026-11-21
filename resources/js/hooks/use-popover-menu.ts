import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';

/**
 * A small "⋯" action menu (role="menu") that works from the keyboard:
 * opening it moves focus to the first item, arrow keys, Home and End move
 * between items, Escape closes it and hands focus back to its button, and
 * Tab or a click outside closes it.
 */
export function usePopoverMenu() {
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        menuItems(menuRef.current)[0]?.focus();

        const closeOutside = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', closeOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    const onMenuKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>) => {
        const items = menuItems(menuRef.current);

        if (event.key === 'Tab') {
            setOpen(false);

            return;
        }

        if (items.length === 0) {
            return;
        }

        const current = items.indexOf(document.activeElement as HTMLElement);
        const target = {
            ArrowDown: (current + 1) % items.length,
            ArrowUp: (current - 1 + items.length) % items.length,
            Home: 0,
            End: items.length - 1,
        }[event.key];

        if (target !== undefined) {
            event.preventDefault();
            items[target]?.focus();
        }
    };

    return { open, setOpen, containerRef, triggerRef, menuRef, onMenuKeyDown };
}

function menuItems(menu: HTMLElement | null): HTMLElement[] {
    return Array.from(
        menu?.querySelectorAll<HTMLElement>(
            '[role="menuitem"]:not([disabled])',
        ) ?? [],
    );
}
