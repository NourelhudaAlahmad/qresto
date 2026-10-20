import { router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/qresto/button';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { showToast } from '@/components/qresto/toast';

type RequestKind = 'water' | 'bread' | 'bill' | 'waiter';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    openRequests?: RequestKind[];
};

const options: { kind: RequestKind; label: string; description: string }[] = [
    { kind: 'water', label: 'Water', description: 'Ask for water' },
    { kind: 'bread', label: 'Bread', description: 'Ask for more bread' },
    { kind: 'bill', label: 'The bill', description: 'Request your bill' },
    { kind: 'waiter', label: 'Waiter', description: 'Ask for assistance' },
];

export function CallWaiterSheet({
    open,
    onOpenChange,
    openRequests = [],
}: Props) {
    const [submitting, setSubmitting] = useState<RequestKind | null>(null);
    const pendingKinds = new Set(openRequests);

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
                        title: 'Request sent',
                        description:
                            kind === 'bill'
                                ? 'Your bill has been requested.'
                                : 'Your waiter has been notified.',
                    });
                },
                onError: () => {
                    showToast({
                        type: 'error',
                        title: 'Could not send request',
                        description: 'Please try again.',
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
            title="How can we help?"
        >
            <div className="space-y-3">
                <p className="text-body text-[var(--text-secondary)]">
                    Choose what you need and we’ll notify your waiter.
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
                                        ? 'Request pending'
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
