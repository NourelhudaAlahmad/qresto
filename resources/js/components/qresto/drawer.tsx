import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';

type DrawerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    children: ReactNode;
};

export function Drawer({ open, onOpenChange, title, children }: DrawerProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-[var(--surface-scrim)]" />

                <Dialog.Content className="fixed end-0 top-0 z-50 h-full w-[min(420px,100vw)] border-s border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-card)] outline-none">
                    <Dialog.Title className="font-display text-title-2 font-semibold">
                        {title}
                    </Dialog.Title>

                    <div className="mt-4">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
