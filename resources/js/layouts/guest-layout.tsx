import { useLayoutEffect, useRef } from 'react';
import type { PropsWithChildren } from 'react';

import { LocaleSync } from '@/components/locale-sync';
import { checkScreen } from '@/lib/non-negotiables';

/**
 * The in-restaurant guest screens, mobile-first rather than mobile-only
 * (#60). The design draws them at 390 × 812, but that is an artboard, not
 * a limit: guests also open their table link on a tablet, and staff test
 * it on a laptop.
 *
 * On a phone the screen is the page, exactly as before: full width, full
 * height, the browser scrolls it.
 *
 * Wider, it stays a single column at a readable measure (40rem), centred
 * on the same page background, with a hairline either side so headers
 * and docks end somewhere deliberate. No device frame, no clipping: the
 * page scrolls normally, and sticky headers and docks stick to the
 * viewport. Screens that compose for a wide viewport pass `wide` (#61).
 */
type GuestLayoutProps = PropsWithChildren<{
    /**
     * Screens with a composition for wide viewports (#61) open the column
     * to 72rem from lg; the rest keep the 40rem reading column.
     */
    wide?: boolean;
}>;

export default function GuestLayout({
    children,
    wide = false,
}: GuestLayoutProps) {
    const frame = useRef<HTMLDivElement>(null);

    // #36, rule 01: one primary button per guest screen.
    useLayoutEffect(() => checkScreen(frame.current));

    return (
        <div className="min-h-[100dvh] bg-[var(--surface-page)]">
            <LocaleSync />

            {/* clip, not hidden: hidden would make this a scroll container
                and the sticky headers and docks would stop sticking. */}
            <div
                data-guest-shell
                data-guest-width={wide ? 'wide' : 'reading'}
                className={`relative mx-auto w-full bg-[var(--surface-page)] sm:max-w-[40rem] sm:overflow-x-clip sm:border-x sm:border-[var(--border-subtle)] ${wide ? 'lg:max-w-[72rem]' : ''}`}
            >
                <div
                    ref={frame}
                    data-guest-frame
                    className="flex min-h-[100dvh] flex-col"
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
