import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/qresto/button';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { showToast } from '@/components/qresto/toast';
import { useI18n } from '@/lib/i18n';

type RequestKind = 'water' | 'bread' | 'bill' | 'waiter';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    openRequests?: RequestKind[];
};

const kinds: RequestKind[] = ['water', 'bread', 'bill', 'waiter'];

export function CallWaiterSheet({
    open,
    onOpenChange,
    openRequests = [],
}: Props) {
    const { guest: g } = useI18n();
    const [submitting, setSubmitting] = useState<RequestKind | null>(null);
    const pendingKinds = new Set(openRequests);
    const options = kinds.map((kind) => ({
        kind,
        label: g.call[kind],
        description: g.call[`${kind}_hint` as const],
    }));

    function submitRequest(kind: RequestKind) {
        if (submitting || pendingKinds.has(kind)) {
            return;
        }

        setSubmitting(kind);
        router.post(
            '/service-requests',
            { kind },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                    showToast({
                        type: 'success',
                        title: g.call.sent,
                        description:
                            kind === 'bill'
                                ? g.call.bill_requested
                                : g.call.waiter_notified,
                    });
                },
                onError: () => {
                    showToast({
                        type: 'error',
                        title: g.call.failed,
                        description: g.call.try_again,
                    });
                },
                onFinish: () => setSubmitting(null),
            },
        );
    }

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={onOpenChange}
            title={g.call.title}
        >
            <div className="space-y-3">
                <p className="text-body text-[var(--text-secondary)]">
                    {g.call.body}
                </p>
                {options.map((option) => {
                    const pending = pendingKinds.has(option.kind);

                    return (
                        <Button
                            key={option.kind}
                            type="button"
                            variant="outline"
                            fullWidth
                            disabled={pending || submitting !== null}
                            onClick={() => submitRequest(option.kind)}
                            className="h-auto min-h-16 justify-between rounded-xl px-4 py-3"
                        >
                            <span className="flex flex-col items-start gap-1 text-start">
                                <span className="font-semibold">
                                    {option.label}
                                </span>
                                <span className="text-caption font-normal">
                                    {pending
                                        ? g.call.pending
                                        : option.description}
                                </span>
                            </span>
                        </Button>
                    );
                })}
            </div>
        </ResponsiveOverlay>
    );
}
