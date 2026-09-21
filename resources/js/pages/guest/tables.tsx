import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Icon } from '@/components/ui/icon';
import GuestLayout from '@/layouts/guest-layout';

type Table = {
    id: number;
    number: string;
    seats: number;
    state: string;
    url?: string;
};

type TablesProps = {
    current_table_id: number;
    tables: Table[];
};

export default function Tables({ current_table_id, tables }: TablesProps) {
    const closePicker = () => {
        window.history.back();
    };

    const selectTable = (table: Table) => {
        if (!table.url || table.id === current_table_id) {
            return;
        }

        router.visit(table.url);
    };

    return (
        <GuestLayout>
            <Head title="Choose your table" />

            <main className="min-h-[100dvh]" />

            <ResponsiveOverlay
                open
                dir="ltr"
                onOpenChange={(open) => {
                    if (!open) {
                        closePicker();
                    }
                }}
                title={
                    <span className="font-display text-display-3 tracking-display text-text-primary font-semibold">
                        Choose your table
                    </span>
                }
            >
                <div className="max-h-[70dvh] overflow-y-auto">
                    <div className="mb-5">
                        <p className="text-micro tracking-eyebrow text-clay-600 font-semibold uppercase">
                            Wrong table?
                        </p>

                        <p className="text-body-lg text-text-secondary mt-2">
                            Pick the number printed on your table.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {tables.map((table) => {
                            const isCurrent = table.id === current_table_id;

                            return (
                                <button
                                    key={table.id}
                                    type="button"
                                    disabled={isCurrent || !table.url}
                                    onClick={() => selectTable(table)}
                                    className="border-border-subtle bg-surface-card focus-visible:ring-border-focus-color/30 flex min-h-[112px] flex-col items-start justify-between rounded-[var(--radius-lg)] border p-4 text-left outline-none transition-[background-color,border-color,transform,opacity] duration-[var(--duration-fast)] focus-visible:ring-[3px] active:scale-[var(--press-scale)] disabled:cursor-default disabled:opacity-60 disabled:active:scale-100"
                                >
                                    <div className="flex w-full items-start justify-between gap-2">
                                        <span className="text-text-primary font-mono text-[36px] font-semibold tabular-nums leading-none">
                                            {table.number}
                                        </span>

                                        {isCurrent && (
                                            <span
                                                className="text-herb-700"
                                                aria-label="Current table"
                                            >
                                                <Icon
                                                    iconNode={Check}
                                                    className="size-4"
                                                />
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-4">
                                        <p className="text-text-primary text-sm font-semibold">
                                            Table {table.number}
                                        </p>

                                        <p className="text-caption text-text-secondary mt-0.5">
                                            {table.seats}{' '}
                                            {table.seats === 1
                                                ? 'seat'
                                                : 'seats'}
                                            {isCurrent
                                                ? ' · Current table'
                                                : ''}
                                        </p>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>
            </ResponsiveOverlay>
        </GuestLayout>
    );
}
