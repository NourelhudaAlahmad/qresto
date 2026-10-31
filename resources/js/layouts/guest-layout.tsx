import { useLayoutEffect, useRef } from 'react';
import type { PropsWithChildren } from 'react';

import { LocaleSync } from '@/components/locale-sync';
import { checkScreen } from '@/lib/non-negotiables';

/**
 * The guest app is mobile-only (design: 390 × 812).
 *
 * On a phone it is simply the page: full screen, the browser scrolls it.
 *
 * On a wider screen it is drawn as the design canvas draws it: a fixed
 * phone shell (rounded, clipped) holding a screen that scrolls on its own
 * with the scrollbar hidden, as on a real phone. The page itself never
 * scrolls, so there is only ever one scroll area and it is the phone.
 */
export default function GuestLayout({ children }: PropsWithChildren) {
    const frame = useRef<HTMLDivElement>(null);

    // #36, rule 01: one primary button per guest screen.
    useLayoutEffect(() => checkScreen(frame.current));

    return (
        <div className="min-h-[100dvh] bg-[var(--ink-100)] sm:flex sm:h-[100dvh] sm:items-center sm:justify-center sm:overflow-hidden sm:p-6">
            <LocaleSync />

            <div
                data-guest-shell
                className="relative mx-auto w-full max-w-[390px] bg-[var(--surface-page)] sm:h-[min(812px,100%)] sm:overflow-hidden sm:rounded-[38px] sm:border sm:border-[var(--ink-200)] sm:shadow-[0_2px_6px_rgba(66,60,53,0.10),0_32px_64px_rgba(66,60,53,0.22)]"
            >
                <div
                    ref={frame}
                    data-guest-frame
                    className="flex min-h-[100dvh] flex-col sm:absolute sm:inset-0 sm:min-h-0 sm:overflow-y-auto sm:overflow-x-hidden sm:overscroll-contain sm:[scrollbar-width:none] sm:[&::-webkit-scrollbar]:hidden"
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
