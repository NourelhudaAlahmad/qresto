import type { PropsWithChildren } from 'react';
import { LocaleSync } from '@/components/locale-sync';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-[100dvh] bg-[#ebe7e1]">
            <LocaleSync />

            <div className="mx-auto flex min-h-[100dvh] w-full max-w-[390px] flex-col overflow-hidden rounded-[32px] bg-[var(--surface-page)] shadow-[0_24px_60px_rgba(26,23,18,0.18)] sm:my-6 sm:min-h-[calc(100dvh-48px)]">
                {children}
            </div>
        </div>
    );
}
