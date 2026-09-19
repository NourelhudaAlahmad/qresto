import { Head, router } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/qresto/button';
import { Input } from '@/components/qresto/input';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Icon } from '@/components/ui/icon';
import GuestLayout from '@/layouts/guest-layout';

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

function getCoverText(seats: number): string {
    const words: Record<number, string> = {
        1: 'One',
        2: 'Two',
        3: 'Three',
        4: 'Four',
        5: 'Five',
        6: 'Six',
        7: 'Seven',
        8: 'Eight',
        9: 'Nine',
        10: 'Ten',
        11: 'Eleven',
        12: 'Twelve',
    };

    if (seats === 1) {
        return 'One cover';
    }

    return `${words[seats] ?? seats} covers`;
}

export default function Table({
    restaurant,
    table,
    assigned_waiter,
    qr_token,
}: TableProps) {
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

    const coverText = getCoverText(table.seats);

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
                <Head title="Reading table" />

                <main
                    dir="ltr"
                    className="flex min-h-[100dvh] items-center justify-center px-[var(--gutter-mobile)]"
                >
                    <div
                        className="text-herb-700 flex items-center gap-2 text-sm font-semibold"
                        role="status"
                        aria-live="polite"
                    >
                        <Icon iconNode={CircleCheck} className="size-4" />
                        <span>Reading QR code…</span>
                    </div>
                </main>
            </GuestLayout>
        );
    }

    return (
        <GuestLayout>
            <Head title={`Table ${table.number}`} />

            <form
                dir="ltr"
                onSubmit={handleSubmit}
                className="flex min-h-[100dvh] flex-col"
            >
                <main className="flex flex-1 flex-col px-[var(--gutter-mobile)] pb-6 pt-8">
                    <div className="text-herb-700 flex items-center gap-2 text-sm font-semibold">
                        <Icon iconNode={CircleCheck} className="size-4" />
                        <span>QR code read</span>
                    </div>

                    <section className="mt-8">
                        <p className="text-micro tracking-eyebrow text-clay-600 font-semibold uppercase">
                            {restaurant.name}
                        </p>

                        <div className="my-3 font-mono text-[96px] font-semibold tabular-nums leading-none tracking-[-0.04em]">
                            {table.number}
                        </div>

                        <h1 className="font-display text-display-3 tracking-display font-semibold">
                            You're at Table {table.number}
                        </h1>

                        <p className="text-body-lg text-text-secondary mt-3 leading-relaxed">
                            {coverText}
                            {waiterFirstName
                                ? `. ${waiterFirstName} is looking after this table tonight.`
                                : '.'}
                        </p>
                    </section>

                    <label className="mt-8 flex flex-col gap-1.5">
                        <span className="text-label text-text-secondary font-semibold">
                            First name, so we can call the order
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
                            aria-label="First name"
                        />

                        <span className="text-caption text-text-tertiary">
                            No account, no phone number. The table is the
                            session.
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
                        {isSubmitting ? 'Opening…' : 'Open the menu'}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full"
                        onClick={openTablePicker}
                        disabled={isSubmitting}
                    >
                        Wrong table?
                    </Button>
                </StickyDock>
            </form>
        </GuestLayout>
    );
}
