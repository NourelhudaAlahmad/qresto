import type { ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
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
    /** The design's verb for the next step: Start cooking, Mark ready… */
    advanceLabel?: string;
    advancing?: boolean;
    /** Shown instead of the advance button when there is no next step. */
    footerNote?: ReactNode;
    /** Replaces the default overflow button, e.g. with a menu. */
    overflow?: ReactNode;
    /** SLA level; saffron at the warn threshold, berry at the late one. */
    elapsedLevel?: 'normal' | 'warn' | 'late';
    /** CSS colour for the 3px status edge on the inline-start side. */
    accentColor?: string;
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

const elapsedLevelStyles = {
    normal: '',
    warn: '!text-[var(--saffron-700)] font-semibold',
    late: '!text-[var(--berry-500)] font-semibold',
} as const;

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
    advanceLabel,
    advancing = false,
    footerNote,
    overflow,
    elapsedLevel = 'normal',
    accentColor,
}: OrderTicketProps) {
    const styles = variantStyles[variant];

    return (
        <div
            className={`overflow-hidden rounded-[var(--radius-lg)] border ${
                accentColor ? 'border-s-[3px]' : ''
            } ${styles.card}`}
            style={
                accentColor
                    ? { borderInlineStartColor: accentColor }
                    : undefined
            }
            data-status={status}
            data-elapsed-level={elapsedLevel}
        >
            <div
                className={`flex items-center gap-2 border-b px-3 pb-2 pt-3 ${styles.divider}`}
            >
                <Numeric className={`text-title-3 font-medium ${styles.code}`}>
                    {code}
                </Numeric>

                <span className="flex-1" />

                <Numeric
                    className={`text-caption ${styles.elapsed} ${elapsedLevelStyles[elapsedLevel]}`}
                >
                    {elapsed}
                </Numeric>
            </div>

            <div
                className={`text-caption flex items-center gap-1.5 px-3 pt-2 ${
                    variant === 'dark'
                        ? 'text-[var(--ink-400)]'
                        : 'text-[var(--text-secondary)]'
                }`}
            >
                <span aria-hidden="true">▦</span>

                <span>
                    <Numeric>{table}</Numeric> · {guest}
                </span>
            </div>

            <div className="flex flex-col gap-1 px-3 pb-3 pt-2">
                {items.map((item, index) => (
                    <div key={`${item.name}-${index}`}>
                        <div className="text-body flex gap-2">
                            <Numeric className="text-[var(--text-tertiary)]">
                                {item.qty}
                            </Numeric>

                            <span className="min-w-0 flex-1 break-words">
                                {item.name}
                            </span>
                        </div>

                        {item.note && (
                            <span
                                className={`text-caption ms-[26px] inline-block break-words ${
                                    variant === 'dark'
                                        ? 'text-[var(--saffron-300)]'
                                        : 'rounded-[var(--radius-sm)] bg-[var(--saffron-50)] px-2 py-0.5 text-[var(--saffron-700)]'
                                }`}
                            >
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
                    className={`text-micro inline-flex h-6 items-center rounded-full px-2.5 font-semibold ${
                        paid
                            ? 'bg-[var(--status-paid-bg)] text-[var(--status-paid-fg)]'
                            : 'bg-[var(--status-pending-bg)] text-[var(--status-pending-fg)]'
                    }`}
                >
                    {paid ? 'Paid' : 'Unpaid'}
                </span>

                <span className="flex-1" />

                <Numeric className="text-body font-medium">{total}</Numeric>
            </div>

            <div className="flex items-stretch gap-2 p-3">
                {onAdvance ? (
                    <button
                        type="button"
                        onClick={onAdvance}
                        disabled={advancing}
                        aria-busy={advancing || undefined}
                        aria-label={
                            advanceLabel
                                ? undefined
                                : `Advance order status from ${status}`
                        }
                        className="text-body min-h-11 flex-1 rounded-full bg-[var(--action-primary)] px-3 py-2 font-semibold leading-snug text-[var(--text-on-brand)] transition-transform duration-[var(--duration-fast)] hover:bg-[var(--action-primary-hover)] active:scale-[var(--press-scale)] disabled:opacity-60"
                    >
                        {advanceLabel ?? 'Advance status'}
                    </button>
                ) : (
                    <span className="text-caption flex min-h-11 flex-1 items-center text-[var(--text-secondary)]">
                        {footerNote}
                    </span>
                )}

                {overflow ?? (
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
                )}
            </div>
        </div>
    );
}
