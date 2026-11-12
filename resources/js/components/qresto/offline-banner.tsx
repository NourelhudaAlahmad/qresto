import { WifiOff } from 'lucide-react';

type OfflineBannerProps = {
    title: string;
    detail: string;
    /**
     * pass: read from across a kitchen, so it fills the width in berry.
     * guest: a calm notice on a phone at the table.
     */
    tone: 'pass' | 'guest';
};

/**
 * Says plainly that the data on screen is not live and how stale it is
 * (#58). Rendered only while disconnected.
 */
export function OfflineBanner({ title, detail, tone }: OfflineBannerProps) {
    if (tone === 'pass') {
        return (
            <div
                role="alert"
                data-testid="offline-banner"
                data-tone="pass"
                className="mb-5 flex items-center gap-4 rounded-[var(--radius-lg)] bg-[var(--berry-500)] px-6 py-5 text-[var(--ink-25)]"
            >
                <WifiOff className="size-10 shrink-0" aria-hidden="true" />
                <div className="min-w-0">
                    <p className="font-display text-title-1 font-semibold">
                        {title}
                    </p>
                    <p className="text-body font-mono">{detail}</p>
                </div>
            </div>
        );
    }

    return (
        <div
            role="alert"
            data-testid="offline-banner"
            data-tone="guest"
            className="flex items-start gap-3 rounded-[var(--radius-md)] border border-[var(--berry-500)] bg-[var(--surface-card)] px-4 py-3 text-[var(--text-primary)]"
        >
            <WifiOff
                className="mt-0.5 size-5 shrink-0 text-[var(--berry-500)]"
                aria-hidden="true"
            />
            <div className="min-w-0">
                <p className="text-body font-semibold">{title}</p>
                <p className="text-caption text-[var(--text-secondary)]">
                    {detail}
                </p>
            </div>
        </div>
    );
}
