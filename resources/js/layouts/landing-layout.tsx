import { useLayoutEffect, useRef } from 'react';
import type { PropsWithChildren } from 'react';

import { LocaleSync } from '@/components/locale-sync';
import { checkScreen } from '@/lib/non-negotiables';

/**
 * The public landing page shell (#53).
 *
 * Unlike the in-restaurant flow — which is reached by pointing a phone camera
 * at a table sticker and stays inside the 390 × 812 phone frame — the landing
 * page at `/` and `/r/{slug}` is the public front door, opened on whatever
 * device the guest holds. It uses the full document scroll and width at every
 * breakpoint while keeping the #36 single-primary-action guard.
 */
export default function LandingLayout({ children }: PropsWithChildren) {
    const screen = useRef<HTMLDivElement>(null);

    // #36, rule 01: one primary button per guest screen.
    useLayoutEffect(() => checkScreen(screen.current));

    return (
        <div
            ref={screen}
            data-landing-shell
            className="font-ui flex min-h-[100dvh] w-full flex-col overflow-x-hidden bg-[var(--surface-page)] text-[var(--text-primary)]"
        >
            <LocaleSync />

            {children}
        </div>
    );
}
