import type { TableState } from '@/types';

type TableTileProps = {
    table: string;
    state: TableState;
};

const stateStyles: Record<TableState, string> = {
    free: 'bg-[var(--table-free-bg)] text-[var(--table-free-fg)] border-[var(--border-subtle)]',
    seated: 'bg-[var(--table-seated-bg)] text-[var(--table-seated-fg)] border-[var(--teal-100)]',
    ordered: 'bg-[var(--table-ordered-bg)] text-[var(--table-ordered-fg)] border-[var(--saffron-100)]',
    bill: 'bg-[var(--table-bill-bg)] text-[var(--table-bill-fg)] border-[var(--clay-200)]',
};

const stateLabels: Record<TableState, string> = {
    free: 'Free',
    seated: 'Seated',
    ordered: 'Ordered',
    bill: 'Bill requested',
};

export function TableTile({ table, state }: TableTileProps) {
    return (
        <div
            className={`min-h-24 rounded-[var(--radius-lg)] border p-3 ${stateStyles[state]}`}
            data-state={state}
        >
            <div className="font-mono text-title-2">{table}</div>
            <div className="text-micro">
                {stateLabels[state]}
            </div>
        </div>
    );
}