import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';

type DrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    children: ReactNode;
    description?: ReactNode;
    /** Pinned under the scrolling body: Cancel and the primary action. */
    footer?: ReactNode;
};

/**
 * Right-hand drawer (inline-end in RTL): add-waiter on the manager overview
 * and the menu editor on tablets. The body scrolls; header and footer stay.
 */
export function Drawer({
    open,
    onOpenChange,
    title,
    children,
    description,
    footer,
}: DrawerProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 fixed inset-0 z-[80] bg-[var(--surface-scrim)] motion-reduce:animate-none" />

                <Dialog.Content
                    {...(description === undefined
                        ? { 'aria-describedby': undefined }
                        : {})}
                    className="data-[state=open]:animate-in data-[state=open]:slide-in-from-right data-[state=closed]:animate-out data-[state=closed]:slide-out-to-right rtl:data-[state=open]:slide-in-from-left rtl:data-[state=closed]:slide-out-to-left fixed end-0 top-0 z-[81] flex h-full w-[min(420px,100vw)] flex-col border-s border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-pop)] outline-none duration-200 motion-reduce:animate-none"
                >
                    <div className="flex items-center gap-3 border-b border-[var(--border-subtle)] p-5">
                        <Dialog.Title className="font-display text-title-2 flex-1 font-semibold">
                            {title}
                        </Dialog.Title>

                        <Dialog.Close
                            aria-label="Close"
                            className="grid size-11 shrink-0 place-items-center rounded-full text-[var(--text-secondary)] hover:bg-[var(--action-ghost-hover)]"
                        >
                            <X className="size-[19px]" aria-hidden="true" />
                        </Dialog.Close>
                    </div>

                    {description !== undefined && (
                        <Dialog.Description className="text-body px-5 pt-4 text-[var(--text-secondary)]">
                            {description}
                        </Dialog.Description>
                    )}

                    <div className="min-h-0 flex-1 overflow-y-auto p-5">
                        {children}
                    </div>

                    {footer !== undefined && (
                        <div className="flex gap-2 border-t border-[var(--border-subtle)] p-5 pb-[calc(var(--space-5,20px)+env(safe-area-inset-bottom))]">
                            {footer}
                        </div>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
