import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Clock3, ImageIcon, X } from 'lucide-react';
import { useMemo } from 'react';

import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { Radio, RadioGroup } from '@/components/qresto/radio';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Tag } from '@/components/qresto/tag';
import { Textarea } from '@/components/qresto/textarea';
import GuestLayout from '@/layouts/guest-layout';

type Money = {
    amount: number;
    currency: string;
    formatted: string;
};

type Variant = {
    id: number;
    label: string;
    price_delta: Money;
    is_default: boolean;
};

type Addon = {
    id: number;
    label: string;
    price_delta: Money;
    available: boolean;
};

type Allergen = {
    id: number;
    name: string;
    may_contain: boolean;
};

type Meal = {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
    price: Money;
    photo: string | null;
    prep_minutes: number | null;
    available: boolean;
    tags: string[];
    flag: boolean;
    variants: Variant[];
    addons: Addon[];
    allergens: Allergen[];
};

type MealPageProps = {
    restaurant: {
        id: number;
        name: string;
        currency: string;
    };
    table: {
        id: number;
        number: string | number;
    };
    item: Meal;
};

type CartLineForm = {
    menu_item_id: number;
    variant_id: number | null;
    addon_ids: number[];
    qty: number;
    note: string;
};

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount / 100);
}

function formatDelta(money: Money): string {
    if (money.amount === 0) {
        return money.formatted;
    }

    return `+${formatMoney(money.amount, money.currency)}`;
}

export default function Meal({ restaurant, table, item }: MealPageProps) {
    const defaultVariant =
        item.variants.find((variant) => variant.is_default) ??
        item.variants[0] ??
        null;

    const { data, setData, post, processing, errors } = useForm<CartLineForm>({
        menu_item_id: item.id,
        variant_id: defaultVariant?.id ?? null,
        addon_ids: [],
        qty: 1,
        note: '',
    });

    const selectedVariant = useMemo(
        () =>
            item.variants.find((variant) => variant.id === data.variant_id) ??
            null,
        [data.variant_id, item.variants],
    );

    const selectedAddons = useMemo(
        () => item.addons.filter((addon) => data.addon_ids.includes(addon.id)),
        [data.addon_ids, item.addons],
    );

    const unitTotal = useMemo(() => {
        const variantAmount = selectedVariant?.price_delta.amount ?? 0;

        const addonsAmount = selectedAddons.reduce(
            (total, addon) => total + addon.price_delta.amount,
            0,
        );

        return item.price.amount + variantAmount + addonsAmount;
    }, [item.price.amount, selectedAddons, selectedVariant]);

    const total = unitTotal * data.qty;

    const containsAllergens = item.allergens.filter(
        (allergen) => !allergen.may_contain,
    );

    const mayContainAllergens = item.allergens.filter(
        (allergen) => allergen.may_contain,
    );

    const toggleAddon = (addonId: number, checked: boolean) => {
        if (checked) {
            setData(
                'addon_ids',
                Array.from(new Set([...data.addon_ids, addonId])),
            );

            return;
        }

        setData(
            'addon_ids',
            data.addon_ids.filter((id) => id !== addonId),
        );
    };

    const submit = () => {
        if (!item.available || processing) {
            return;
        }

        post('/cart/lines', {
            preserveScroll: true,
            onSuccess: () => {
                router.visit('/cart');
            },
        });
    };

    const heroEyebrow = [item.category, item.flag ? "Chef's pick" : null]
        .filter(Boolean)
        .join(' · ');

    return (
        <GuestLayout>
            <Head title={`${item.name} · ${restaurant.name}`} />

            <div className="flex h-[100dvh] min-h-0 flex-col overflow-hidden bg-[var(--surface-page)]">
                <main className="min-h-0 flex-1 overflow-y-auto overscroll-contain [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {/* HERO */}
                    <section className="relative h-[300px] shrink-0 overflow-hidden bg-[var(--clay-100)]">
                        {item.photo ? (
                            <img
                                src={item.photo}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-[var(--clay-200)] to-[var(--clay-700)]">
                                <ImageIcon
                                    aria-hidden="true"
                                    className="size-12 text-white/70"
                                />
                            </div>
                        )}

                        <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/80" />

                        <Button
                            type="button"
                            variant="ghost"
                            aria-label="Back to menu"
                            onClick={() => router.visit('/menu')}
                            className="absolute start-3 top-3 z-10 size-10 rounded-full bg-white/90 p-0 text-[var(--text-primary)] shadow-sm backdrop-blur-sm hover:bg-white hover:text-[var(--text-primary)]"
                        >
                            <X className="size-5" />
                        </Button>

                        <div className="absolute inset-x-0 bottom-0 px-5 pb-6 text-white">
                            {heroEyebrow && (
                                <p className="text-micro mb-2 font-semibold tracking-[0.08em] text-white/80">
                                    {heroEyebrow}
                                </p>
                            )}

                            <h1 className="font-display text-[34px] font-semibold leading-[1.05] tracking-[-0.04em]">
                                {item.name}
                            </h1>
                        </div>
                    </section>

                    <div className="px-5 pb-8 pt-6">
                        {/* PRICE + PREP */}
                        <div className="flex items-baseline justify-between gap-4">
                            <span className="font-mono text-[22px] font-semibold tabular-nums text-[var(--text-primary)]">
                                {item.price.formatted}
                            </span>

                            {item.prep_minutes !== null && (
                                <span className="flex items-center gap-1.5 text-sm text-[var(--text-tertiary)]">
                                    <Clock3
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                    />
                                    <span>
                                        {item.prep_minutes} min from the grill
                                    </span>
                                </span>
                            )}
                        </div>

                        {/* DESCRIPTION */}
                        {item.description && (
                            <p className="mt-4 text-[17px] leading-7 text-[var(--text-secondary)]">
                                {item.description}
                            </p>
                        )}

                        {/* DIETARY TAGS */}
                        {item.tags.length > 0 && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {item.tags.map((tag) => (
                                    <Tag key={tag}>{tag}</Tag>
                                ))}
                            </div>
                        )}

                        {!item.available && (
                            <div className="mt-5 rounded-[var(--radius-container)] bg-[var(--clay-50)] px-4 py-3 text-sm font-medium text-[var(--clay-700)]">
                                This meal is currently sold out.
                            </div>
                        )}

                        {/* CONTAINS */}
                        <section className="mt-7">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Contains
                            </h2>

                            <div className="mt-2 flex flex-wrap gap-2">
                                {containsAllergens.map((allergen) => (
                                    <Tag key={allergen.id}>{allergen.name}</Tag>
                                ))}

                                {item.tags.map((tag) => (
                                    <Tag key={`diet-${tag}`}>{tag}</Tag>
                                ))}

                                {containsAllergens.length === 0 &&
                                    item.tags.length === 0 && (
                                        <span className="text-caption text-[var(--text-tertiary)]">
                                            No listed allergens
                                        </span>
                                    )}

                                {mayContainAllergens.map((allergen) => (
                                    <Tag key={`may-${allergen.id}`}>
                                        {allergen.name}
                                    </Tag>
                                ))}
                            </div>

                            {mayContainAllergens.length > 0 && (
                                <div className="mt-3 inline-flex items-center gap-2 rounded-[var(--radius-control)] bg-[var(--saffron-50)] px-3 py-2 text-[var(--saffron-800)]">
                                    <AlertTriangle
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                    />

                                    <span className="text-caption font-medium">
                                        Cooked with allergens nearby
                                    </span>
                                </div>
                            )}
                        </section>

                        {/* SIZE */}
                        <section className="mt-7 border-t border-[var(--border-subtle)] pt-6">
                            <h2 className="font-display text-lg font-semibold tracking-[-0.02em] text-[var(--text-primary)]">
                                Choose a size
                            </h2>

                            {item.variants.length > 0 ? (
                                <RadioGroup
                                    className="mt-3 gap-2"
                                    value={
                                        data.variant_id !== null
                                            ? String(data.variant_id)
                                            : undefined
                                    }
                                    onValueChange={(value) =>
                                        setData('variant_id', Number(value))
                                    }
                                >
                                    {item.variants.map((variant) => {
                                        const selected =
                                            data.variant_id === variant.id;

                                        return (
                                            <label
                                                key={variant.id}
                                                className={`flex min-h-[52px] cursor-pointer items-center gap-3 rounded-[var(--radius-container)] border px-4 transition-colors ${
                                                    selected
                                                        ? 'border-[var(--clay-500)] bg-[var(--clay-50)]'
                                                        : 'border-[var(--border-default)] bg-[var(--surface-raised)]'
                                                }`}
                                            >
                                                <Radio
                                                    value={String(variant.id)}
                                                    disabled={!item.available}
                                                />

                                                <span className="min-w-0 flex-1 text-[15px] font-medium text-[var(--text-primary)]">
                                                    {variant.label}
                                                </span>

                                                <span className="shrink-0 font-mono text-sm tabular-nums text-[var(--text-secondary)]">
                                                    {variant.price_delta
                                                        .amount === 0
                                                        ? item.price.formatted
                                                        : formatDelta(
                                                              variant.price_delta,
                                                          )}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </RadioGroup>
                            ) : (
                                <div className="mt-3 flex min-h-[52px] items-center rounded-[var(--radius-container)] border border-[var(--border-default)] bg-[var(--surface-raised)] px-4">
                                    <span className="min-w-0 flex-1 text-[15px] font-medium text-[var(--text-primary)]">
                                        Regular
                                    </span>

                                    <span className="shrink-0 font-mono text-sm tabular-nums text-[var(--text-secondary)]">
                                        {item.price.formatted}
                                    </span>
                                </div>
                            )}

                            {errors.variant_id && (
                                <p className="mt-2 text-sm text-[var(--danger)]">
                                    {errors.variant_id}
                                </p>
                            )}
                        </section>

                        {/* ADD-ONS */}
                        <section className="mt-7 border-t border-[var(--border-subtle)] pt-6">
                            <div className="flex items-baseline gap-2">
                                <h2 className="font-display text-lg font-semibold tracking-[-0.02em] text-[var(--text-primary)]">
                                    Add on
                                </h2>

                                <span className="text-micro text-[var(--text-tertiary)]">
                                    optional
                                </span>
                            </div>

                            {item.addons.length > 0 ? (
                                <div className="mt-3 space-y-2">
                                    {item.addons.map((addon) => {
                                        const checked = data.addon_ids.includes(
                                            addon.id,
                                        );

                                        return (
                                            <label
                                                key={addon.id}
                                                className={`flex min-h-[52px] items-center gap-3 rounded-[var(--radius-container)] border px-4 transition-colors ${
                                                    checked && addon.available
                                                        ? 'border-[var(--clay-500)] bg-[var(--clay-50)]'
                                                        : 'border-[var(--border-default)] bg-[var(--surface-raised)]'
                                                } ${
                                                    addon.available
                                                        ? 'cursor-pointer'
                                                        : 'cursor-not-allowed opacity-50'
                                                }`}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={
                                                        !addon.available ||
                                                        !item.available
                                                    }
                                                    onCheckedChange={(value) =>
                                                        toggleAddon(
                                                            addon.id,
                                                            value === true,
                                                        )
                                                    }
                                                />

                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-[15px] font-medium text-[var(--text-primary)]">
                                                        {addon.label}
                                                    </span>

                                                    {!addon.available && (
                                                        <span className="text-micro mt-0.5 block text-[var(--text-tertiary)]">
                                                            Sold out
                                                        </span>
                                                    )}
                                                </span>

                                                <span className="shrink-0 font-mono text-sm tabular-nums text-[var(--text-secondary)]">
                                                    {formatDelta(
                                                        addon.price_delta,
                                                    )}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="mt-3 flex min-h-[52px] items-center rounded-[var(--radius-container)] border border-[var(--border-subtle)] bg-[var(--surface-raised)] px-4">
                                    <span className="text-[15px] text-[var(--text-tertiary)]">
                                        No add-ons available for this meal
                                    </span>
                                </div>
                            )}

                            {errors.addon_ids && (
                                <p className="mt-2 text-sm text-[var(--danger)]">
                                    {errors.addon_ids}
                                </p>
                            )}
                        </section>

                        {/* KITCHEN NOTE */}
                        <section className="mt-7 border-t border-[var(--border-subtle)] pt-6">
                            <div className="flex items-baseline gap-2">
                                <h2 className="font-display text-lg font-semibold tracking-[-0.02em] text-[var(--text-primary)]">
                                    A note for the kitchen
                                </h2>

                                <span className="text-micro text-[var(--text-tertiary)]">
                                    optional
                                </span>
                            </div>

                            <Textarea
                                value={data.note}
                                onChange={(event) =>
                                    setData('note', event.target.value)
                                }
                                maxLength={500}
                                disabled={!item.available || processing}
                                placeholder="e.g. no onions, sauce on the side"
                                className="mt-3 min-h-[96px]"
                            />

                            <div className="mt-2 flex items-start justify-between gap-3">
                                <p className="text-caption leading-relaxed text-[var(--saffron-700)]">
                                    Notes reach the pass and the waiter, in
                                    saffron, so nobody misses them.
                                </p>

                                <span className="shrink-0 font-mono text-xs tabular-nums text-[var(--text-tertiary)]">
                                    {data.note.length}/500
                                </span>
                            </div>

                            {errors.note && (
                                <p className="mt-2 text-sm text-[var(--danger)]">
                                    {errors.note}
                                </p>
                            )}

                            {errors.menu_item_id && (
                                <p className="mt-3 text-sm text-[var(--danger)]">
                                    {errors.menu_item_id}
                                </p>
                            )}
                        </section>

                        <p className="mt-6 text-center text-xs text-[var(--text-tertiary)]">
                            Table {table.number} · {restaurant.name}
                        </p>
                    </div>
                </main>

                {/* ADD TO ORDER DOCK */}
                <StickyDock className="z-30">
                    <div className="flex items-center gap-3">
                        <QuantityStepper
                            value={data.qty}
                            min={1}
                            max={99}
                            onChange={(value) => setData('qty', value)}
                            disabled={!item.available || processing}
                            className="shrink-0"
                        />

                        <Button
                            type="button"
                            size="lg"
                            fullWidth
                            loading={processing}
                            disabled={!item.available}
                            onClick={submit}
                            className="min-w-0 flex-1"
                        >
                            <span>Add to order</span>

                            <span aria-hidden="true" className="opacity-60">
                                ·
                            </span>

                            <span className="font-mono tabular-nums">
                                {formatMoney(total, restaurant.currency)}
                            </span>
                        </Button>
                    </div>
                </StickyDock>
            </div>
        </GuestLayout>
    );
}
