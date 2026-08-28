import type { ReactNode } from 'react';

type DataTableColumn<T> = {
    key: keyof T;
    label: ReactNode;
    numeric?: boolean;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    rows: T[];
    total?: ReactNode;
    className?: string;
};

export function DataTable<T extends Record<string, ReactNode>>({
    columns,
    rows,
    total,
    className = '',
}: DataTableProps<T>) {
    return (
        <div
            className={`overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-card)] ${className}`}
        >
            <div className="overflow-x-auto">
                <div className="min-w-[640px]">
                    <div
                        className="grid gap-3 border-b border-[var(--border-subtle)] bg-[var(--surface-sunken)] px-5 py-2 text-label font-semibold text-[var(--text-secondary)]"
                        style={{
                            gridTemplateColumns: columns
                                .map(() => 'minmax(0, 1fr)')
                                .join(' '),
                        }}
                    >
                        {columns.map((column) => (
                            <span
                                key={String(column.key)}
                                className={
                                    column.numeric
                                        ? 'text-right'
                                        : 'text-left'
                                }
                            >
                                {column.label}
                            </span>
                        ))}
                    </div>

                    {rows.map((row, rowIndex) => (
                        <div
                            key={rowIndex}
                            className="grid items-center gap-3 border-b border-[var(--border-subtle)] px-5 py-3 text-body"
                            style={{
                                gridTemplateColumns: columns
                                    .map(() => 'minmax(0, 1fr)')
                                    .join(' '),
                            }}
                        >
                            {columns.map((column) => (
                                <span
                                    key={String(column.key)}
                                    className={
                                        column.numeric
                                            ? 'text-right font-mono'
                                            : 'text-left'
                                    }
                                >
                                    {row[column.key]}
                                </span>
                            ))}
                        </div>
                    ))}

                    {total !== undefined && (
                        <div
                            className="grid items-center gap-3 bg-[var(--surface-sunken)] px-5 py-4 font-mono text-body font-medium"
                            style={{
                                gridTemplateColumns: columns
                                    .map(() => 'minmax(0, 1fr)')
                                    .join(' '),
                            }}
                        >
                            <span className="font-ui font-semibold">
                                Total
                            </span>

                            <span>{total}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}