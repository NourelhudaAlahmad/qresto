import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { usePopoverMenu } from '@/hooks/use-popover-menu';

function Menu() {
    const { open, setOpen, containerRef, triggerRef, menuRef, onMenuKeyDown } =
        usePopoverMenu();

    return (
        <div ref={containerRef}>
            <button
                ref={triggerRef}
                type="button"
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
            >
                Actions
            </button>

            {open && (
                <div ref={menuRef} role="menu" onKeyDown={onMenuKeyDown}>
                    <button type="button" role="menuitem">
                        Edit
                    </button>
                    <button type="button" role="menuitem" disabled>
                        Archive
                    </button>
                    <button type="button" role="menuitem">
                        Delete
                    </button>
                </div>
            )}
        </div>
    );
}

describe('usePopoverMenu', () => {
    it('moves focus into the menu and between items with the arrow keys', () => {
        render(<Menu />);

        fireEvent.click(screen.getByRole('button', { name: 'Actions' }));

        const edit = screen.getByRole('menuitem', { name: 'Edit' });
        const remove = screen.getByRole('menuitem', { name: 'Delete' });
        const menu = screen.getByRole('menu');

        expect(document.activeElement).toBe(edit);

        // The disabled item is skipped.
        fireEvent.keyDown(menu, { key: 'ArrowDown' });
        expect(document.activeElement).toBe(remove);

        fireEvent.keyDown(menu, { key: 'ArrowDown' });
        expect(document.activeElement).toBe(edit);

        fireEvent.keyDown(menu, { key: 'End' });
        expect(document.activeElement).toBe(remove);

        fireEvent.keyDown(menu, { key: 'Home' });
        expect(document.activeElement).toBe(edit);
    });

    it('closes on Escape and hands focus back to its button', () => {
        render(<Menu />);

        const trigger = screen.getByRole('button', { name: 'Actions' });

        fireEvent.click(trigger);
        fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });

        expect(screen.queryByRole('menu')).toBeNull();
        expect(document.activeElement).toBe(trigger);
    });
});
