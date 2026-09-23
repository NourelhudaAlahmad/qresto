import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/qresto/button';
import GuestLayout from '@/layouts/guest-layout';
import { formatMoney } from '@/lib/formatMoney';
import type { Money } from '@/types';

type Restaurant = {
    id: number;
    name: string;
    currency: string;
};

type Table = {
    id: number;
    number: string;
};

type CartLineAddon = {
    id: number;
    label: string;
    price_delta: Money;
};

type CartLine = {
    id: number;
    name: string;
    variant: string | null;
    qty: number;
    unit_price: Money;
    variant_price_delta: Money;
    line_total: Money;
    note: string | null;
    addons: CartLineAddon[];
};

type Cart = {
    id: number | null;
    lines: CartLine[];
    subtotal: Money;
};

type Translations = {
    title: string;
    empty: string;
    back_to_menu: string;
    subtotal: string;
    quantity: string;
    note: string;
};

type Props = {
    restaurant: Restaurant;
    table: Table;
    cart: Cart;
    translations: Translations;
};

export default function CartPage({ restaurant, cart, translations }: Props) {
    return (
        <GuestLayout>
            <Head title={`${translations.title} · ${restaurant.name}`} />

            <div className="mx-auto min-h-dvh w-full max-w-[486px] bg-white shadow-sm">
                <header className="border-border-default border-b px-5 py-5">
                    <div className="flex items-center gap-3">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => window.history.back()}
                            className="h-10 w-10 shrink-0 rounded-full p-0"
                            aria-label={translations.back_to_menu}
                        >
                            <BackIcon />
                        </Button>

                        <h1 className="text-xl font-semibold">
                            {translations.title}
                        </h1>
                    </div>
                </header>

                <main className="space-y-6 px-5 py-6">
                    {cart.lines.length === 0 ? (
                        <section className="py-12 text-center">
                            <p className="text-text-secondary text-sm">
                                {translations.empty}
                            </p>

                            <Button asChild className="mt-5">
                                <Link href="/menu">
                                    {translations.back_to_menu}
                                </Link>
                            </Button>
                        </section>
                    ) : (
                        <>
                            <div className="space-y-4">
                                {cart.lines.map((line) => (
                                    <article
                                        key={line.id}
                                        className="border-border-default rounded-md border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0 flex-1">
                                                <h2 className="font-medium">
                                                    {line.name}
                                                </h2>

                                                {line.variant ? (
                                                    <p className="text-text-secondary mt-1 text-sm">
                                                        {line.variant}
                                                    </p>
                                                ) : null}
                                            </div>

                                            <span
                                                dir="ltr"
                                                className="shrink-0 font-mono font-semibold tabular-nums"
                                            >
                                                {formatMoney(line.line_total)}
                                            </span>
                                        </div>

                                        {line.addons.length > 0 ? (
                                            <div className="mt-3 space-y-1">
                                                {line.addons.map((addon) => (
                                                    <div
                                                        key={addon.id}
                                                        className="text-text-secondary flex items-center justify-between gap-3 text-sm"
                                                    >
                                                        <span>
                                                            {addon.label}
                                                        </span>

                                                        <span
                                                            dir="ltr"
                                                            className="font-mono tabular-nums"
                                                        >
                                                            +
                                                            {formatMoney(
                                                                addon.price_delta,
                                                            )}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}

                                        <div className="text-text-secondary mt-3 text-sm">
                                            {translations.quantity}:{' '}
                                            <span
                                                dir="ltr"
                                                className="font-mono tabular-nums"
                                            >
                                                {line.qty}
                                            </span>
                                        </div>

                                        {line.note ? (
                                            <div className="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                                <span className="font-medium">
                                                    {translations.note}:
                                                </span>{' '}
                                                {line.note}
                                            </div>
                                        ) : null}
                                    </article>
                                ))}
                            </div>

                            <section className="border-border-default border-t pt-5">
                                <div className="flex items-center justify-between gap-4">
                                    <span className="font-medium">
                                        {translations.subtotal}
                                    </span>

                                    <span
                                        dir="ltr"
                                        className="font-mono text-lg font-semibold tabular-nums"
                                    >
                                        {formatMoney(cart.subtotal)}
                                    </span>
                                </div>
                            </section>

                            <Button asChild variant="ghost" className="w-full">
                                <Link href="/menu">
                                    {translations.back_to_menu}
                                </Link>
                            </Button>
                        </>
                    )}
                </main>
            </div>
        </GuestLayout>
    );
}

function BackIcon() {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            className="h-5 w-5"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="m15 18-6-6 6-6" />
        </svg>
    );
}
