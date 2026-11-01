import { Head, router } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/qresto/button';
import { Input } from '@/components/qresto/input';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Icon } from '@/components/ui/icon';
import GuestLayout from '@/layouts/guest-layout';
import { fill, useI18n } from '@/lib/i18n';

type TableProps = {
    restaurant: {
        name: string | null;
    };
    table: {
        id: number;
        number: string;
        seats: number;
    };
    assigned_waiter: {
        name: string;
        initials: string | null;
    } | null;
    qr_token: string;
};

const FIRST_NAME_STORAGE_KEY = 'qresto_guest_first_name';
const READ_DELAY_MS = 1100;

function getSavedFirstName(): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return window.localStorage.getItem(FIRST_NAME_STORAGE_KEY) ?? '';
}

export default function Table({
    restaurant,
    table,
    assigned_waiter,
    qr_token,
}: TableProps) {
    const { guest: g } = useI18n();
    const [isReading, setIsReading] = useState(true);
    const [firstName, setFirstName] = useState(getSavedFirstName);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setIsReading(false);
        }, READ_DELAY_MS);

        return () => window.clearTimeout(timer);
    }, []);

    const waiterFirstName = assigned_waiter?.name
        ? assigned_waiter.name.split(' ')[0]
        : null;

    // Arabic counts take different forms for 1, 2, 3–10 and 11+.
    const coverText =
        table.seats === 1
            ? g.table.covers_one
            : table.seats === 2
              ? g.table.covers_two
              : fill(
                    table.seats <= 10
                        ? g.table.covers_few
                        : g.table.covers_many,
                    { count: table.seats },
                );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const trimmedName = firstName.trim();

        if (!trimmedName) {
            return;
        }

        window.localStorage.setItem(FIRST_NAME_STORAGE_KEY, trimmedName);

        router.post(
            `/t/${encodeURIComponent(qr_token)}/confirm`,
            {
                first_name: trimmedName,
            },
            {
                preserveScroll: true,
                onStart: () => setIsSubmitting(true),
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    const openTablePicker = () => {
        router.visit(`/t/${encodeURIComponent(qr_token)}/tables`);
    };

    if (isReading) {
        return (
            <GuestLayout>
                <Head title={g.table.reading_head} />

                <main className="flex min-h-[100dvh] items-center justify-center px-[var(--gutter-mobile)]">
                    <div
                        className="text-herb-700 text-body flex items-center gap-2 font-semibold"
                        role="status"
                        aria-live="polite"
                    >
                        <Icon iconNode={CircleCheck} className="size-4" />
                        <span>{g.table.reading}</span>
                    </div>
                </main>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title={fill(g.table.head, { table: table.number })} />

            <form
                onSubmit={handleSubmit}
                className="flex min-h-[100dvh] flex-col"
            >
                <main className="flex flex-1 flex-col px-[var(--gutter-mobile)] pb-6 pt-8">
                    <div className="text-herb-700 text-body flex items-center gap-2 font-semibold">
                        <Icon iconNode={CircleCheck} className="size-4" />
                        <span>{g.table.read}</span>
                    </div>

                    <section className="mt-8">
                        <p className="text-micro tracking-eyebrow text-clay-600 font-semibold uppercase">
                            {restaurant.name}
                        </p>

                        <div className="my-3 font-mono text-[96px] font-semibold tabular-nums leading-none tracking-[-0.04em]">
                            {table.number}
                        </div>

                        <h1 className="font-display text-display-3 tracking-display font-semibold">
                            {fill(g.table.you_are_at, { table: table.number })}
                        </h1>

                        <p className="text-body-lg text-text-secondary mt-3 leading-relaxed">
                            {coverText}
                            {waiterFirstName
                                ? `. ${fill(g.table.waiter_tonight, { name: waiterFirstName })}`
                                : '.'}
                        </p>
                    </section>

                    <label className="mt-8 flex flex-col gap-1.5">
                        <span className="text-label text-text-secondary font-semibold">
                            {g.table.first_name_label}
                        </span>

                        <Input
                            type="text"
                            value={firstName}
                            onChange={(event) =>
                                setFirstName(event.target.value)
                            }
                            autoComplete="given-name"
                            maxLength={50}
                            required
                            className="text-body-lg h-[54px]"
                            aria-label={g.table.first_name}
                        />

                        <span className="text-caption text-text-tertiary">
                            {g.table.first_name_hint}
                        </span>
                    </label>
                </main>

                <StickyDock className="flex-col gap-2">
                    <Button
                        type="submit"
                        size="lg"
                        className="w-full"
                        disabled={isSubmitting || !firstName.trim()}
                    >
                        {isSubmitting ? g.table.opening : g.table.open_menu}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full"
                        onClick={openTablePicker}
                        disabled={isSubmitting}
                    >
                        {g.table.wrong_table}
                    </Button>
                </StickyDock>
            </form>
        </GuestLayout>
    );
}
