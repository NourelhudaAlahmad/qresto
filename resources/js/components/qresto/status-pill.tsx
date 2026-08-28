import type { OrderStatus } from '@/types';

type StatusPillProps = {
    status: OrderStatus;
};

const statusStyles: Record<OrderStatus, string> = {
    placed: 'bg-[var(--status-placed-bg)] text-[var(--status-placed-fg)]',
    pending: 'bg-[var(--status-pending-bg)] text-[var(--status-pending-fg)]',
    preparing:
        'bg-[var(--status-preparing-bg)] text-[var(--status-preparing-fg)]',
    ready: 'bg-[var(--status-ready-bg)] text-[var(--status-ready-fg)]',
    served: 'bg-[var(--status-served-bg)] text-[var(--status-served-fg)]',
    paid: 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]',
    cancelled:
        'bg-[var(--status-cancelled-bg)] text-[var(--status-cancelled-fg)]',
};

const labels: Record<OrderStatus, string> = {
    placed: 'Placed',
    pending: 'Pending',
    preparing: 'Preparing',
    ready: 'Ready',
    served: 'Served',
    paid: 'Paid',
    cancelled: 'Cancelled',
};

export function StatusPill({ status }: StatusPillProps) {
    return (
        <span
            className={`text-caption inline-flex h-7 items-center gap-1.5 rounded-full px-3 font-semibold ${statusStyles[status]}`}
            data-status={status}
        >
            <span
                aria-hidden="true"
                className="h-1.5 w-1.5 rounded-full bg-current"
            />
            {labels[status]}
        </span>
    );
}
