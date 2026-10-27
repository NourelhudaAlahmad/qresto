import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';

type ResponsiveOverlayProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    children: ReactNode;
    dir?: 'ltr' | 'rtl';
    description?: ReactNode;
};

export function ResponsiveOverlay({
    open,
    onOpenChange,
    title,
    children,
    dir,
    description,
}: ResponsiveOverlayProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-[var(--surface-scrim)]" />

                <Dialog.Content
                    {...(description === undefined
                        ? { 'aria-describedby': undefined }
                        : {})}
                    dir={dir}
                    className="fixed inset-x-0 top-1/2 z-50 mx-auto max-h-[90dvh] w-[min(520px,calc(100vw-32px))] -translate-y-1/2 overflow-y-auto rounded-[var(--radius-lg)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-card)] outline-none max-[767px]:bottom-0 max-[767px]:top-auto max-[767px]:w-full max-[767px]:max-w-none max-[767px]:translate-y-0 max-[767px]:rounded-b-none max-[767px]:rounded-t-[var(--radius-lg)] max-[767px]:p-4 max-[767px]:pb-[calc(var(--space-4)+env(safe-area-inset-bottom))]"
                >
                    <Dialog.Title className="font-display text-title-2 font-semibold">
                        {title}
                    </Dialog.Title>

                    {description !== undefined && (
                        <Dialog.Description className="text-body mt-1 text-[var(--text-secondary)]">
                            {description}
                        </Dialog.Description>
                    )}

                    <div className="mt-4">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
