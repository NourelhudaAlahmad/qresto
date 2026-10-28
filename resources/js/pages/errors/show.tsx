import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/qresto/button';

type Props = {
    status: 403 | 404 | 429 | 500 | 503;
    message?: string | null;
    home: string;
    homeLabel: string;
};

const COPY: Record<Props['status'], { title: string; body: string }> = {
    403: {
        title: 'This isn’t open to you',
        body: 'Your account doesn’t have access to this screen. If you think it should, ask your manager.',
    },
    404: {
        title: 'We couldn’t find that',
        body: 'The page may have moved, or the link is out of date.',
    },
    429: {
        title: 'Slow down a moment',
        body: 'Too many requests in a short time. Try again in a minute.',
    },
    500: {
        title: 'Something went wrong on our side',
        body: 'It isn’t anything you did. Try again in a moment.',
    },
    503: {
        title: 'Back in a moment',
        body: 'QResto is being updated. This usually takes a minute.',
    },
};

/**
 * One designed page for every error status, on the design system's
 * surfaces and type scale, with a way back to the person's own screen.
 */
export default function ErrorShow({ status, message, home, homeLabel }: Props) {
    const copy = COPY[status] ?? COPY[500];

    return (
        <>
            <Head title={copy.title} />

            <main className="flex min-h-[100dvh] items-center justify-center bg-[var(--surface-page)] px-[var(--gutter-mobile)] py-10">
                <div className="flex w-full max-w-[420px] flex-col items-center text-center">
                    <span className="font-display text-[19px] font-bold tracking-[-0.04em]">
                        <span className="text-[var(--clay-500)]">Q</span>
                        <span className="text-[var(--text-primary)]">
                            Resto
                        </span>
                    </span>

                    <p
                        dir="ltr"
                        className="text-display-1 mt-10 font-mono font-medium text-[var(--clay-500)]"
                    >
                        {status}
                    </p>

                    <h1 className="font-display text-title-1 mt-4 font-semibold tracking-[var(--tracking-title)]">
                        {copy.title}
                    </h1>

                    <p className="text-body-lg mt-2 text-[var(--text-secondary)]">
                        {message ?? copy.body}
                    </p>

                    <div className="mt-8 flex w-full flex-col gap-3">
                        <Button asChild variant="primary" size="lg" fullWidth>
                            <Link href={home}>{homeLabel}</Link>
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="lg"
                            fullWidth
                            onClick={() => window.history.back()}
                        >
                            Go back
                        </Button>
                    </div>
                </div>
            </main>
        </>
    );
}
