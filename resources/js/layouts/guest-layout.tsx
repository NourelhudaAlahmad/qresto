import { LocaleSync } from '@/components/locale-sync';
import type { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-[100dvh] bg-surface-page text-text-primary">
            <LocaleSync />

            <div className="mx-auto min-h-[100dvh] w-full max-w-[390px] overflow-x-hidden pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)]">
                {children}
            </div>
        </div>
    );
}