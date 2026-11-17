import { Head } from '@inertiajs/react';
import { Check, QrCode } from 'lucide-react';
import { useState } from 'react';

import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Icon } from '@/components/ui/icon';
import GuestLayout from '@/layouts/guest-layout';
import { fill, useI18n } from '@/lib/i18n';

type Table = {
    id: number;
    number: string;
    seats: number;
};

type TablesProps = {
    current_table_id: number;
    tables: Table[];
};

export default function Tables({ current_table_id, tables }: TablesProps) {
    const { guest: g } = useI18n();
    const [chosen, setChosen] = useState<Table | null>(null);

    const closePicker = () => {
        window.history.back();
    };

    /**
     * Only the code printed on a table proves the guest is sitting there,
     * so choosing another table explains how to move instead of moving.
     */
    const selectTable = (table: Table) => {
        if (table.id === current_table_id) {
            return;
        }

        setChosen(table);
    };

    return (
        <GuestLayout>
            <Head title={g.tables.title} />

            <main className="min-h-[100dvh]" />

            <ResponsiveOverlay
                open
                onOpenChange={(open) => {
                    if (!open) {
                        closePicker();
                    }
                }}
                title={
                    <span className="font-display text-display-3 tracking-display text-text-primary font-semibold">
                        {g.tables.title}
                    </span>
                }
            >
                <div className="max-h-[70dvh] overflow-y-auto">
                    <div className="mb-5">
                        <p className="text-micro tracking-eyebrow text-clay-600 font-semibold uppercase">
                            {g.tables.wrong_table}
                        </p>

                        <p className="text-body-lg text-text-secondary mt-2">
                            {g.tables.hint}
                        </p>
                    </div>

                    {chosen && (
                        <p
                            role="status"
                            data-scan-to-move
                            className="border-border-subtle bg-surface-sunken text-body text-text-primary mb-5 flex items-center gap-3 rounded-[var(--radius-lg)] border p-4"
                        >
                            <Icon
                                iconNode={QrCode}
                                className="text-clay-600 size-5 shrink-0"
                            />
                            {fill(g.tables.scan_to_move, {
                                table: chosen.number,
                            })}
                        </p>
                    )}

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {tables.map((table) => {
                            const isCurrent = table.id === current_table_id;

                            return (
                                <button
                                    key={table.id}
                                    type="button"
                                    disabled={isCurrent}
                                    aria-pressed={chosen?.id === table.id}
                                    onClick={() => selectTable(table)}
                                    className="border-border-subtle bg-surface-card focus-visible:ring-border-focus-color/30 duration-fast flex min-h-[112px] flex-col items-start justify-between rounded-[var(--radius-lg)] border p-4 text-start outline-none transition-[background-color,border-color,transform,opacity] focus-visible:ring-[3px] active:scale-[var(--press-scale)] disabled:cursor-default disabled:opacity-60 disabled:active:scale-100"
                                >
                                    <div className="flex w-full items-start justify-between gap-2">
                                        <span className="text-text-primary font-mono text-[36px] font-semibold tabular-nums leading-none">
                                            {table.number}
                                        </span>

                                        {isCurrent && (
                                            <span
                                                className="text-herb-700"
                                                aria-label={g.tables.current}
                                            >
                                                <Icon
                                                    iconNode={Check}
                                                    className="size-4"
                                                />
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-4">
                                        <p className="text-text-primary text-body font-semibold">
                                            {fill(g.tables.table, {
                                                table: table.number,
                                            })}
                                        </p>

                                        <p className="text-caption text-text-secondary mt-0.5">
                                            {table.seats === 1
                                                ? g.tables.seats_one
                                                : fill(g.tables.seats_other, {
                                                      count: table.seats,
                                                  })}
                                            {isCurrent
                                                ? ` · ${g.tables.current}`
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
