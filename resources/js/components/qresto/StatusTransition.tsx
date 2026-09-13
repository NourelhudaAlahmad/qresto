import type { ReactNode } from 'react';

type StatusTransitionProps = {
    status: string | number;
    children: ReactNode;
};

export function StatusTransition({ status, children }: StatusTransitionProps) {
    return (
        <div key={String(status)} className="animate-in fade-in duration-300">
            {children}
        </div>
    );
}
