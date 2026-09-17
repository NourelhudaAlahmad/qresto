import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

type StatusTransitionProps = {
    status: string | number;
    children: ReactNode;
};

type TransitionItem = {
    status: string | number;
    children: ReactNode;
};

export function StatusTransition({ status, children }: StatusTransitionProps) {
    const previousStatus = useRef(status);
    const previousChildren = useRef(children);
    const [outgoing, setOutgoing] = useState<TransitionItem | null>(null);

    useEffect(() => {
        if (previousStatus.current === status) {
            previousChildren.current = children;

            return;
        }

        setOutgoing({
            status: previousStatus.current,
            children: previousChildren.current,
        });

        previousStatus.current = status;
        previousChildren.current = children;
    }, [status, children]);

    useEffect(() => {
        if (!outgoing) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setOutgoing(null);
        }, 200);

        return () => window.clearTimeout(timeout);
    }, [outgoing]);

    return (
        <span className="relative inline-grid">
            {outgoing && (
                <span
                    data-status={String(outgoing.status)}
                    data-transition="outgoing"
                    aria-hidden="true"
                    className="animate-out fade-out col-start-1 row-start-1 motion-reduce:animate-none"
                    style={{
                        animationDuration: 'var(--dur-base)',
                        animationTimingFunction: 'var(--ease-standard)',
                    }}
                >
                    {outgoing.children}
                </span>
            )}

            <span
                key={String(status)}
                data-status={String(status)}
                data-transition="incoming"
                className="animate-in fade-in col-start-1 row-start-1 motion-reduce:animate-none"
                style={{
                    animationDuration: 'var(--dur-base)',
                    animationTimingFunction: 'var(--ease-standard)',
                }}
            >
                {children}
            </span>
        </span>
    );
}
