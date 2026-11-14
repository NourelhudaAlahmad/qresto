import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { fill, useI18n } from '@/lib/i18n';

/**
 * The full-screen shell for the kitchen's wall displays, the pass and the
 * runner (#64). They render with no staff layout (#33), so the shell owns
 * the page: dark to the edges, at least one viewport tall, no rounded card
 * floating on a white page. The body grows into the height left under the
 * header, so a quiet board still fills the screen.
 */
export function KitchenScreen({
    surface,
    label,
    connection,
    children,
}: {
    surface: 'pass' | 'runner';
    label: string;
    connection?: 'live' | 'offline';
    children: ReactNode;
}) {
    return (
        <section
            data-surface={surface}
            data-connection={connection}
            aria-label={label}
            className="text-body flex min-h-[100dvh] w-full flex-col bg-[var(--ink-950)] p-6 text-[var(--ink-25)]"
        >
            {children}
        </section>
    );
}

/** The part of the screen that takes the height the header leaves. */
export function KitchenScreenBody({
    className = '',
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`flex flex-1 flex-col ${className}`}>{children}</div>
    );
}

/** "Nothing on the pass", centred in the screen rather than a short card. */
export function KitchenScreenEmpty({ children }: { children: ReactNode }) {
    return (
        <div
            data-kitchen-empty
            className="flex flex-1 items-center justify-center"
        >
            <p className="font-display text-title-1 py-24 text-center text-[var(--ink-400)]">
                {children}
            </p>
        </div>
    );
}

/**
 * Shared equipment: who is signed in, and a way out that a stray tap
 * mid-service cannot take (#52). Kept to the header, away from the bump
 * controls.
 */
export function KitchenSignOut({ signedInAs }: { signedInAs?: string }) {
    const {
        staff: { pass: t },
    } = useI18n();
    const [confirming, setConfirming] = useState(false);

    return (
        <div className="flex flex-wrap items-center gap-3 border-s border-[var(--alpha-paper-08)] ps-4">
            {signedInAs && (
                <p className="text-body text-[var(--ink-400)]">
                    {fill(t.signed_in_as, { name: signedInAs })}
                </p>
            )}

            {confirming ? (
                <div
                    role="group"
                    aria-label={t.confirm}
                    className="flex flex-wrap items-center gap-3"
                >
                    <p className="text-body">
                        <span className="font-semibold">{t.confirm}</span>{' '}
                        <span className="text-[var(--ink-400)]">
                            {t.confirm_hint}
                        </span>
                    </p>
                    <button
                        type="button"
                        autoFocus
                        onClick={() => setConfirming(false)}
                        className="text-body min-h-11 min-w-11 rounded-[var(--radius-pill)] border border-[var(--alpha-paper-08)] px-5 font-semibold"
                    >
                        {t.stay}
                    </button>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="text-body min-h-11 min-w-11 rounded-[var(--radius-pill)] bg-[var(--danger-solid)] px-5 font-semibold text-[var(--ink-25)]"
                    >
                        {t.sign_out}
                    </button>
                </div>
            ) : (
                <button
                    type="button"
                    onClick={() => setConfirming(true)}
                    className="text-body min-h-11 min-w-11 rounded-[var(--radius-pill)] border border-[var(--alpha-paper-08)] px-5 font-semibold"
                >
                    {t.sign_out}
                </button>
            )}
        </div>
    );
}
