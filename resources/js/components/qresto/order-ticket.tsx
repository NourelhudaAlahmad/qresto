import type { OrderStatus } from '@/types';

type OrderTicketItem = {
    qty: string;
    name: string;
    note?: string;
};

type OrderTicketProps = {
    code: string;
    elapsed: string;
    table: string;
    guest: string;
    status: OrderStatus;
    items: OrderTicketItem[];
    paid: boolean;
    total: string;
    variant?: 'light' | 'dark';
    onAdvance?: () => void;
    onOverflow?: () => void;
};
const variantStyles = {
    light: {
        card: 'bg-[var(--surface-card)] border-[var(--border-subtle)] text-[var(--text-primary)]',
        divider: 'border-[var(--border-subtle)]',
        code: 'text-[var(--text-primary)]',
        elapsed: 'text-[var(--text-secondary)]',
    },
    dark: {
        card: 'bg-[var(--ink-950)] border-[var(--alpha-paper-08)] text-[var(--ink-25)]',
        divider: 'border-[var(--alpha-paper-08)]',
        code: 'text-[var(--ink-0)]',
        elapsed: 'text-[var(--ink-400)]',
    },
};
export function OrderTicket({
    code,
    elapsed,
    table,
    guest,
    status,
    items,
    paid,
    total,
    variant = 'light',
    onAdvance,
    onOverflow,
}: OrderTicketProps) {
    const styles = variantStyles[variant];

    return (
        <div
            className={`overflow-hidden rounded-[var(--radius-lg)] border ${styles.card}`}
        >
            <div
                className={`flex items-center gap-2 border-b px-3 pb-2 pt-3 ${styles.divider}`}
            >
                <span
                    className={`font-mono text-title-3 font-medium ${styles.code}`}
                >
                    {code}
                </span>

                <span className="flex-1" />

                <span
                    className={`font-mono text-caption ${styles.elapsed}`}
                >
                    {elapsed}
                </span>
            </div>
            <div
                className={`flex items-center gap-1.5 px-3 text-caption ${
                    variant === 'dark'
                        ? 'text-[var(--ink-400)]'
                        : 'text-[var(--text-secondary)]'
                }`}
            >
                <span aria-hidden="true">▦</span>
                <span>
                    {table} · {guest}
                </span>
            </div>
            <div className="flex flex-col gap-1 px-3 pb-3 pt-2">
                {items.map((item, index) => (
                    <div key={`${item.name}-${index}`}>
                        <div className="flex gap-2 text-body">
                            <span className="font-mono text-[var(--text-tertiary)]">
                                {item.qty}
                            </span>

                            <span className="min-w-0 flex-1">
                                {item.name}
                            </span>
                        </div>

                        {item.note && (
                            <span className="ml-[26px] inline-block text-caption text-[var(--saffron-300)]">
                                {item.note}
                            </span>
                        )}
                    </div>
                ))}
            </div>
            <div
                className={`flex items-center gap-2 border-t px-3 py-2 ${
                    variant === 'dark'
                        ? 'border-[var(--alpha-paper-08)] bg-[var(--alpha-paper-04)]'
                        : 'border-[var(--border-subtle)] bg-[var(--surface-sunken)]'
                }`}
            >
                <span
                    className={`inline-flex h-6 items-center rounded-full px-2.5 text-micro font-semibold ${
                        paid
                            ? 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]'
                            : 'bg-[var(--status-pending-bg)] text-[var(--status-pending-fg)]'
                    }`}
                >
                    {paid ? 'Paid' : 'Unpaid'}
                </span>

                <span className="flex-1" />

                <span className="font-mono text-body font-medium">
                    {total}
                </span>
            </div>
            <div className="flex gap-2 p-3">
                <button
                    type="button"
                    onClick={onAdvance}
                    className="h-11 flex-1 rounded-full bg-[var(--action-primary)] px-3 text-body font-semibold text-[var(--text-on-brand)]"
                >
                    Advance status
                </button>

                <button
                    type="button"
                    onClick={onOverflow}
                    aria-label="More actions"
                    className={`h-11 w-11 shrink-0 rounded-full border ${
                        variant === 'dark'
                            ? 'border-[var(--alpha-paper-08)] bg-[var(--ink-950)] text-[var(--ink-25)]'
                            : 'border-[var(--border-default)] bg-[var(--surface-card)] text-[var(--text-secondary)]'
                    }`}
                >
                    ⋮
                </button>
            </div>
        </div>
    );
}