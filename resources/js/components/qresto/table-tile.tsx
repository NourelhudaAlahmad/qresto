import type { ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import type { TableState } from '@/types';

type TableTileProps = {
    table: string;
    state: TableState;
    /** Seat line, e.g. "4 seats". */
    seats?: ReactNode;
    /** Bill total and elapsed time, e.g. "$54.35 · 14 min". */
    meta?: ReactNode;
    /** Who has the table tonight, e.g. "Omar F.". */
    assignee?: ReactNode;
    /** A bill request pulses so the table is findable across the room. */
    pulse?: boolean;
    selected?: boolean;
    onClick?: () => void;
    ariaLabel?: string;
};

const stateStyles: Record<TableState, string> = {
    free: 'bg-[var(--table-free-bg)] text-[var(--table-free-fg)] border-[var(--border-subtle)]',
    seated: 'bg-[var(--table-seated-bg)] text-[var(--table-seated-fg)] border-[var(--teal-100)]',
    ordered:
        'bg-[var(--table-ordered-bg)] text-[var(--table-ordered-fg)] border-[var(--saffron-100)]',
    bill: 'bg-[var(--table-bill-bg)] text-[var(--table-bill-fg)] border-[var(--clay-200)]',
};

export const tableStateLabels: Record<TableState, string> = {
    free: 'Free',
    seated: 'Seated',
    ordered: 'Ordered',
    bill: 'Bill requested',
};

export function TableTile({
    table,
    state,
    seats,
    meta,
    assignee,
    pulse = false,
    selected = false,
    onClick,
    ariaLabel,
}: TableTileProps) {
    const className = `flex min-h-24 w-full flex-col items-start gap-1 rounded-[var(--radius-lg)] border p-3 text-start ${stateStyles[state]} ${
        selected
            ? 'ring-[3px] ring-[var(--border-focus-color)] ring-offset-0'
            : ''
    } ${pulse ? 'animate-pulse motion-reduce:animate-none' : ''}`;

    const content = (
        <>
            <div className="flex w-full items-baseline justify-between gap-2">
                <Numeric className="text-title-2">{table}</Numeric>

                {seats !== undefined && (
                    <span className="text-micro opacity-80">{seats}</span>
                )}
            </div>

            <div className="text-micro flex w-full min-w-0 items-baseline gap-1">
                <span className="font-semibold">{tableStateLabels[state]}</span>

                {assignee !== undefined && (
                    <span className="min-w-0 truncate opacity-80">
                        · {assignee}
                    </span>
                )}
            </div>

            {meta !== undefined && (
                <div className="text-caption mt-auto">{meta}</div>
            )}
        </>
    );

    if (onClick) {
        return (
            <button
                type="button"
                className={`${className} outline-none transition-transform duration-[var(--duration-fast)] focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)] active:scale-[var(--press-scale)]`}
                data-state={state}
                aria-pressed={selected}
                aria-label={ariaLabel}
                onClick={onClick}
            >
                {content}
            </button>
        );
    }

    return (
        <div className={className} data-state={state}>
            {content}
        </div>
    );
}
