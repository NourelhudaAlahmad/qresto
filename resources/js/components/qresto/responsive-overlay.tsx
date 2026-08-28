import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';

type ResponsiveOverlayProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    children: ReactNode;
};

export function ResponsiveOverlay({
    open,
    onOpenChange,
    title,
    children,
}: ResponsiveOverlayProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-[var(--surface-scrim)]" />

                <Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[min(520px,calc(100vw-32px))] -translate-x-1/2 -translate-y-1/2 rounded-[var(--radius-lg)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-card)] outline-none max-[767px]:bottom-0 max-[767px]:left-0 max-[767px]:top-auto max-[767px]:w-full max-[767px]:max-w-none max-[767px]:translate-x-0 max-[767px]:translate-y-0 max-[767px]:rounded-b-none max-[767px]:rounded-t-[var(--radius-lg)] max-[767px]:p-4">
                    <Dialog.Title className="font-display text-title-2 font-semibold">
                        {title}
                    </Dialog.Title>

                    <div className="mt-4">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
