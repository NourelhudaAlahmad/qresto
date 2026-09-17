import type { PropsWithChildren } from 'react';
import { LocaleSync } from '@/components/locale-sync';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="bg-surface-page text-text-primary min-h-[100dvh]">
            <LocaleSync />

            <div className="mx-auto min-h-[100dvh] w-full max-w-[390px] overflow-x-hidden pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)]">
                {children}
            </div>
        </div>
    );
}
