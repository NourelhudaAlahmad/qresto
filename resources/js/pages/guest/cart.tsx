import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Button } from '@/components/qresto/button';
import { Card } from '@/components/qresto/card';
import { EmptyState } from '@/components/qresto/empty-state';
import { PriceSummary } from '@/components/qresto/price-summary';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Textarea } from '@/components/qresto/textarea';
import { showToast } from '@/components/qresto/toast';
import GuestLayout from '@/layouts/guest-layout';
import { formatMoney } from '@/lib/formatMoney';
import type { Money } from '@/types/order';

type Restaurant = {
    id: number;
    name: string;
    currency: string;
};

type Table = {
    id: number;
    number: string;
};

type Guest = {
    name: string | null;
    first_name: string | null;
};

type CartLineAddon = {
    id: number;
    label: string;
    price_delta: Money;
};

type CartLine = {
    id: number;
    menu_item_id: number;
    name: string;
    variant: string | null;
    qty: number;
    unit_price: Money;
    configured_unit_price: Money;
    variant_price_delta: Money;
    line_total: Money;
    note: string | null;
    photo_url: string | null;
    addons: CartLineAddon[];
};

type Cart = {
    id: number | null;
    lines: CartLine[];
    note: string | null;
    subtotal: Money;
    service_pct: string;
    service_amount: Money;
    total: Money;
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
    guest: Guest;
    cart: Cart;
    undo_window_seconds: number;
    translations: Translations;
};

type RemoveResponse = {
    removed: boolean;
    line_id: number;
    undo_token: string;
    undo_expires_at: string;
    undo_window_seconds: number;
};

function getCookie(name: string): string | null {
    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`));

    if (!cookie) {
        return null;
    }

    const value = cookie.substring(name.length + 1);

    return decodeURIComponent(value);
}

async function requestJson<T>(
    url: string,
    method: 'PATCH' | 'POST',
    body: Record<string, unknown>,
): Promise<T> {
    const xsrfToken = getCookie('XSRF-TOKEN');

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        const error = new Error(
            `Request failed with status ${response.status}`,
        );

        Object.assign(error, {
            status: response.status,
        });

        throw error;
    }

    return (await response.json()) as T;
}

function errorStatus(error: unknown): number | null {
    if (
        typeof error === 'object' &&
        error !== null &&
        'status' in error &&
        typeof error.status === 'number'
    ) {
        return error.status;
    }

    return null;
}

function servicePercentage(servicePct: string): string {
    const percentage = Number(servicePct);

    if (Number.isNaN(percentage)) {
        return servicePct;
    }

    return percentage.toLocaleString();
}

export default function CartPage({
    table,
    guest,
    cart,
    undo_window_seconds,
    translations,
}: Props) {
    const [tableNote, setTableNote] = useState(cart.note ?? '');

    const [busyLineId, setBusyLineId] = useState<number | null>(null);

    const [savingNote, setSavingNote] = useState(false);

    const itemCount = cart.lines.reduce((total, line) => total + line.qty, 0);

    const reloadCart = () => {
        router.reload({
            only: ['cart'],
        });
    };

    const restoreLine = async (lineId: number, undoToken: string) => {
        try {
            setBusyLineId(lineId);

            await requestJson(`/cart/lines/${lineId}/restore`, 'POST', {
                undo_token: undoToken,
            });

            reloadCart();

            showToast({
                type: 'success',
                title: 'Item restored',
            });
        } catch (error: unknown) {
            if (errorStatus(error) === 410) {
                showToast({
                    type: 'error',
                    title: 'Undo expired',
                    description: 'This item can no longer be restored.',
                });

                return;
            }

            showToast({
                type: 'error',
                title: 'Could not restore item',
                description: 'Please try again.',
            });
        } finally {
            setBusyLineId(null);
        }
    };

    const removeLine = async (line: CartLine) => {
        if (busyLineId !== null) {
            return;
        }

        try {
            setBusyLineId(line.id);

            const response = await requestJson<RemoveResponse>(
                `/cart/lines/${line.id}`,
                'PATCH',
                {
                    qty: 0,
                },
            );

            const windowSeconds =
                response.undo_window_seconds ?? undo_window_seconds;

            showToast({
                type: 'success',
                title: `${line.name} removed`,
                description: `Undo available for ${windowSeconds} seconds.`,
                duration: windowSeconds * 1000,
                action: {
                    label: 'Undo',
                    onClick: () => {
                        void restoreLine(line.id, response.undo_token);
                    },
                },
            });

            reloadCart();
        } catch {
            showToast({
                type: 'error',
                title: 'Could not remove item',
                description: 'Please try again.',
            });
        } finally {
            setBusyLineId(null);
        }
    };

    const updateQuantity = async (line: CartLine, nextQty: number) => {
        if (busyLineId !== null) {
            return;
        }

        if (nextQty === line.qty) {
            return;
        }

        if (nextQty <= 0) {
            await removeLine(line);

            return;
        }

        try {
            setBusyLineId(line.id);

            await requestJson(`/cart/lines/${line.id}`, 'PATCH', {
                qty: nextQty,
            });

            reloadCart();
        } catch {
            showToast({
                type: 'error',
                title: 'Could not update quantity',
                description: 'Please try again.',
            });
        } finally {
            setBusyLineId(null);
        }
    };

    const saveTableNote = () => {
        const nextNote = tableNote.trim();
        const currentNote = (cart.note ?? '').trim();

        if (nextNote === currentNote) {
            return;
        }

        setSavingNote(true);

        router.patch(
            '/cart/note',
            {
                note: nextNote === '' ? null : nextNote,
            },
            {
                preserveScroll: true,
                preserveState: true,

                onSuccess: () => {
                    showToast({
                        type: 'success',
                        title: 'Table note saved',
                    });
                },

                onError: () => {
                    showToast({
                        type: 'error',
                        title: 'Could not save note',
                        description: 'Please try again.',
                    });
                },

                onFinish: () => {
                    setSavingNote(false);
                },
            },
        );
    };

    const goToPayment = () => {
        showToast({
            type: 'success',
            title: 'Payment is coming next',
            description: 'Payment methods are outside this cart task.',
        });
    };

    return (
        <GuestLayout>
            <Head title={translations.title} />

            <div className="flex min-h-dvh flex-col bg-[var(--surface-page)]">
                <header className="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-[var(--border-subtle)] bg-[var(--alpha-paper-88)] px-[var(--gutter-mobile)] backdrop-blur-xl">
                    <Link
                        href="/menu"
                        aria-label="Back to menu"
                        className="-ms-2 grid size-9 shrink-0 place-items-center rounded-full text-[var(--text-primary)] transition-colors hover:bg-[var(--action-ghost-hover)]"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            width="20"
                            height="20"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            aria-hidden="true"
                        >
                            <path d="m15 18-6-6 6-6" />
                        </svg>
                    </Link>

                    <div className="min-w-0 flex-1">
                        <div className="text-[length:var(--text-title-3)] font-[var(--font-display)] font-[var(--weight-semibold)]">
                            Your order
                        </div>

                        <div className="truncate text-[length:var(--text-micro)] font-[var(--font-mono)] text-[var(--text-tertiary)]">
                            Table {table.number}
                            {guest.first_name ? ` · ${guest.first_name}` : ''}
                        </div>
                    </div>

                    <span className="shrink-0 text-[length:var(--text-caption)] font-[var(--font-mono)] text-[var(--text-tertiary)]">
                        {itemCount} {itemCount === 1 ? 'item' : 'items'}
                    </span>
                </header>

                {cart.lines.length === 0 ? (
                    <main className="flex flex-1 flex-col items-center justify-center gap-5 px-[var(--gutter-mobile)] py-10">
                        <EmptyState
                            headline={translations.empty}
                            body="Your order is empty. Add something from the menu when you're ready."
                            className="w-full"
                        />

                        <Button asChild size="lg" fullWidth>
                            <Link href="/menu">
                                {translations.back_to_menu}
                            </Link>
                        </Button>
                    </main>
                ) : (
                    <>
                        <main className="flex flex-1 flex-col gap-3 px-[var(--gutter-mobile)] py-4 pb-6">
                            {cart.lines.map((line) => {
                                const lineBusy = busyLineId === line.id;

                                return (
                                    <Card
                                        key={line.id}
                                        className="flex gap-3 p-3 sm:p-3"
                                    >
                                        <div className="size-16 shrink-0 overflow-hidden rounded-[var(--radius-md)] bg-[var(--surface-sunken)]">
                                            {line.photo_url ? (
                                                <img
                                                    src={line.photo_url}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="grid size-full place-items-center text-[var(--text-tertiary)]">
                                                    <svg
                                                        viewBox="0 0 24 24"
                                                        width="22"
                                                        height="22"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        aria-hidden="true"
                                                    >
                                                        <path d="M3 3h18v18H3z" />

                                                        <path d="m3 16 5-5 4 4 3-3 6 6" />

                                                        <circle
                                                            cx="8.5"
                                                            cy="8.5"
                                                            r="1.5"
                                                        />
                                                    </svg>
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex min-w-0 flex-1 flex-col gap-1">
                                            <div className="flex items-baseline gap-2">
                                                <span className="min-w-0 flex-1 text-[length:var(--text-title-3)] font-[var(--font-display)] font-[var(--weight-semibold)]">
                                                    {line.name}
                                                </span>

                                                <span className="shrink-0 text-[length:var(--text-body)] font-[var(--font-mono)] font-medium">
                                                    {formatMoney(
                                                        line.line_total,
                                                    )}
                                                </span>
                                            </div>

                                            {(line.variant ||
                                                line.addons.length > 0) && (
                                                <div className="text-[length:var(--text-caption)] leading-snug text-[var(--text-secondary)]">
                                                    {line.variant && (
                                                        <span>
                                                            {line.variant}
                                                        </span>
                                                    )}

                                                    {line.variant &&
                                                        line.addons.length >
                                                            0 && (
                                                            <span> · </span>
                                                        )}

                                                    {line.addons.map(
                                                        (addon, index) => (
                                                            <span
                                                                key={addon.id}
                                                            >
                                                                {index > 0 &&
                                                                    ', '}

                                                                {addon.label}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            {line.note && (
                                                <span className="text-[length:var(--text-caption)] font-medium text-[var(--warning-fg)]">
                                                    {line.note}
                                                </span>
                                            )}

                                            <div className="mt-0.5 flex items-center gap-3">
                                                <QuantityStepper
                                                    value={line.qty}
                                                    min={0}
                                                    max={99}
                                                    size="sm"
                                                    disabled={lineBusy}
                                                    onChange={(nextQty) => {
                                                        void updateQuantity(
                                                            line,
                                                            nextQty,
                                                        );
                                                    }}
                                                />

                                                <span className="flex-1" />

                                                <button
                                                    type="button"
                                                    disabled={lineBusy}
                                                    onClick={() => {
                                                        void removeLine(line);
                                                    }}
                                                    className="rounded-[var(--radius-sm)] px-2 py-2 text-[length:var(--text-caption)] text-[var(--text-tertiary)] transition-colors hover:text-[var(--danger-fg)] disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })}

                            <Link
                                href="/menu"
                                className="flex h-11 items-center justify-center rounded-[var(--radius-pill)] border border-dashed border-[var(--border-strong)] bg-transparent px-4 text-[length:var(--text-body)] font-[var(--weight-medium)] text-[var(--text-secondary)] transition-colors hover:border-[var(--clay-400)] hover:text-[var(--clay-600)]"
                            >
                                Add something else
                            </Link>

                            <label className="mt-2 flex flex-col gap-1.5">
                                <span className="text-[length:var(--text-label)] font-[var(--weight-semibold)]">
                                    Anything for the table?
                                </span>

                                <Textarea
                                    rows={2}
                                    maxLength={500}
                                    value={tableNote}
                                    disabled={savingNote}
                                    placeholder="One birthday candle for the knafeh"
                                    className="resize-none"
                                    onChange={(event) => {
                                        setTableNote(event.target.value);
                                    }}
                                    onBlur={saveTableNote}
                                />
                            </label>

                            <PriceSummary
                                subtotal={formatMoney(cart.subtotal)}
                                service={`${servicePercentage(
                                    cart.service_pct,
                                )}% · ${formatMoney(cart.service_amount)}`}
                                total={formatMoney(cart.total)}
                            />
                        </main>

                        <StickyDock>
                            <Button
                                type="button"
                                size="lg"
                                fullWidth
                                onClick={goToPayment}
                            >
                                Choose how to pay
                            </Button>
                        </StickyDock>
                    </>
                )}
            </div>
        </GuestLayout>
    );
}
