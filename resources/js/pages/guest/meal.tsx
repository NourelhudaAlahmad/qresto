import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { RadioCard, RadioGroup } from '@/components/qresto/radio-card';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { Tag } from '@/components/qresto/tag';
import { Textarea } from '@/components/qresto/textarea';
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

type Translations = {
    back_to_menu: string;
    chef_pick: string;
    prep_time: string;
    sold_out_message: string;
    contains: string;
    no_allergens: string;
    allergens_nearby: string;
    choose_size: string;
    no_size_options: string;
    add_on: string;
    optional: string;
    sold_out: string;
    no_addons: string;
    kitchen_note: string;
    note_placeholder: string;
    note_promise: string;
    add_to_order: string;
};

type Props = {
    restaurant: Restaurant;
    table: Table;
    item: Meal;
    translations: Translations;
};

function money(amount: number, currency: string): Money {
    return {
        amount,
        currency,
        formatted: '',
    };
}

function formatDelta(value: Money): string {
    if (value.amount === 0) {
        return formatMoney(value);
    }

    const prefix = value.amount > 0 ? '+' : '';

    return `${prefix}${formatMoney(value)}`;
}

export default function MealPage({ restaurant, item, translations }: Props) {
    const defaultVariant =
        item.variants.find((variant) => variant.is_default) ??
        item.variants[0] ??
        null;

    const { data, setData, post, processing, errors } = useForm({
        menu_item_id: item.id,
        menu_item_variant_id: defaultVariant?.id ?? null,
        addon_ids: [] as number[],
        qty: 1,
        note: '',
    });

    const selectedVariant =
        item.variants.find(
            (variant) => variant.id === data.menu_item_variant_id,
        ) ?? null;

    const selectedAddons = item.addons.filter((addon) =>
        data.addon_ids.includes(addon.id),
    );

    const total = useMemo(() => {
        const variantDelta = selectedVariant?.price_delta.amount ?? 0;

        const addonsTotal = selectedAddons.reduce(
            (sum, addon) => sum + addon.price_delta.amount,
            0,
        );

        return (item.price.amount + variantDelta + addonsTotal) * data.qty;
    }, [data.qty, item.price.amount, selectedAddons, selectedVariant]);

    const containsAllergens = item.allergens.filter(
        (allergen) => !allergen.may_contain,
    );

    const mayContainAllergens = item.allergens.filter(
        (allergen) => allergen.may_contain,
    );

    const heroEyebrow = [
        item.category,
        item.flag ? translations.chef_pick : null,
    ]
        .filter(Boolean)
        .join(' · ');

    const prepText =
        item.prep_minutes !== null
            ? translations.prep_time.replace(
                  ':minutes',
                  String(item.prep_minutes),
              )
            : null;

    const toggleAddon = (addon: Addon): void => {
        if (!addon.available || !item.available) {
            return;
        }

        if (data.addon_ids.includes(addon.id)) {
            setData(
                'addon_ids',
                data.addon_ids.filter((id) => id !== addon.id),
            );

            return;
        }

        setData('addon_ids', [...data.addon_ids, addon.id]);
    };

    const submit = (): void => {
        if (!item.available || processing) {
            return;
        }

        post('/cart/lines', {
            preserveScroll: true,
        });
    };

    return (
        <GuestLayout>
            <Head title={`${item.name} · ${restaurant.name}`} />

            <div className="mx-auto flex min-h-dvh w-full max-w-[486px] flex-col overflow-hidden bg-white shadow-sm">
                <main className="min-h-0 flex-1 overflow-y-auto">
                    <section className="relative h-[300px] overflow-hidden bg-stone-200">
                        {item.photo ? (
                            <img
                                src={item.photo}
                                alt={item.name}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center bg-gradient-to-br from-stone-200 via-stone-100 to-amber-100">
                                <span className="text-text-secondary text-xs font-medium">
                                    {item.name}
                                </span>
                            </div>
                        )}

                        <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-black/5" />

                        <Button
                            type="button"
                            variant="ghost"
                            aria-label={translations.back_to_menu}
                            onClick={() => window.history.back()}
                            className="absolute start-3 top-3 h-10 w-10 rounded-full bg-white/95 p-0 text-stone-900 shadow-sm hover:bg-white"
                        >
                            <CloseIcon />
                        </Button>

                        <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                            {heroEyebrow ? (
                                <p className="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-white/80">
                                    {heroEyebrow}
                                </p>
                            ) : null}

                            <h1 className="font-display text-3xl font-semibold leading-tight tracking-tight">
                                {item.name}
                            </h1>
                        </div>
                    </section>

                    <div className="space-y-6 px-5 py-6">
                        <section className="space-y-4">
                            <div className="flex items-baseline justify-between gap-4">
                                <span
                                    dir="ltr"
                                    className="font-mono text-2xl font-semibold tabular-nums"
                                >
                                    {formatMoney(item.price)}
                                </span>

                                {prepText ? (
                                    <span className="text-text-secondary text-xs">
                                        {prepText}
                                    </span>
                                ) : null}
                            </div>

                            {item.description ? (
                                <p className="text-text-secondary text-[17px] leading-7">
                                    {item.description}
                                </p>
                            ) : null}

                            {!item.available ? (
                                <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                    {translations.sold_out_message}
                                </div>
                            ) : null}

                            <div className="space-y-3">
                                <p className="text-label text-text-primary font-medium">
                                    {translations.contains}
                                </p>

                                {containsAllergens.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                        {containsAllergens.map((allergen) => (
                                            <Tag key={allergen.id}>
                                                {allergen.name}
                                            </Tag>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-text-secondary text-sm">
                                        {translations.no_allergens}
                                    </p>
                                )}

                                {mayContainAllergens.length > 0 ? (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                                        <span className="font-medium">
                                            {translations.allergens_nearby}:
                                        </span>{' '}
                                        {mayContainAllergens
                                            .map((allergen) => allergen.name)
                                            .join(', ')}
                                    </div>
                                ) : null}
                            </div>
                        </section>

                        <Divider />

                        <section className="space-y-3">
                            <h2 className="text-lg font-semibold">
                                {translations.choose_size}
                            </h2>

                            {item.variants.length > 0 ? (
                                <RadioGroup
                                    value={
                                        data.menu_item_variant_id !== null
                                            ? String(data.menu_item_variant_id)
                                            : undefined
                                    }
                                    onValueChange={(value) =>
                                        setData(
                                            'menu_item_variant_id',
                                            Number(value),
                                        )
                                    }
                                    className="space-y-2"
                                >
                                    {item.variants.map((variant) => (
                                        <RadioCard
                                            key={variant.id}
                                            value={String(variant.id)}
                                            disabled={!item.available}
                                            title={variant.label}
                                            description={formatDelta(
                                                variant.price_delta,
                                            )}
                                        />
                                    ))}
                                </RadioGroup>
                            ) : (
                                <div className="border-border-default bg-surface-card flex min-h-14 items-center rounded-md border px-4">
                                    <span className="text-text-secondary text-sm">
                                        {translations.no_size_options}
                                    </span>
                                </div>
                            )}

                            {errors.menu_item_variant_id ? (
                                <p className="text-sm text-red-600">
                                    {errors.menu_item_variant_id}
                                </p>
                            ) : null}
                        </section>

                        <Divider />

                        <section className="space-y-3">
                            <div className="flex items-baseline gap-2">
                                <h2 className="text-lg font-semibold">
                                    {translations.add_on}
                                </h2>

                                <span className="text-text-secondary text-xs">
                                    {translations.optional}
                                </span>
                            </div>

                            {item.addons.length > 0 ? (
                                <div className="space-y-2">
                                    {item.addons.map((addon) => {
                                        const checked = data.addon_ids.includes(
                                            addon.id,
                                        );

                                        return (
                                            <label
                                                key={addon.id}
                                                className={[
                                                    'border-border-default bg-surface-card flex min-h-14 items-center gap-3 rounded-md border px-4',
                                                    addon.available &&
                                                    item.available
                                                        ? 'cursor-pointer'
                                                        : 'cursor-not-allowed opacity-60',
                                                    checked
                                                        ? 'border-border-brand bg-surface-brand-soft'
                                                        : '',
                                                ].join(' ')}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={
                                                        !addon.available ||
                                                        !item.available
                                                    }
                                                    onCheckedChange={() =>
                                                        toggleAddon(addon)
                                                    }
                                                />

                                                <span className="min-w-0 flex-1">
                                                    <span className="text-label text-text-primary block font-medium">
                                                        {addon.label}
                                                    </span>

                                                    {!addon.available ? (
                                                        <span className="text-caption text-text-secondary mt-0.5 block">
                                                            {
                                                                translations.sold_out
                                                            }
                                                        </span>
                                                    ) : null}
                                                </span>

                                                <span
                                                    dir="ltr"
                                                    className="font-mono text-sm tabular-nums"
                                                >
                                                    {formatDelta(
                                                        addon.price_delta,
                                                    )}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div className="border-border-default bg-surface-card flex min-h-14 items-center rounded-md border px-4">
                                    <span className="text-text-secondary text-sm">
                                        {translations.no_addons}
                                    </span>
                                </div>
                            )}

                            {errors.addon_ids ? (
                                <p className="text-sm text-red-600">
                                    {errors.addon_ids}
                                </p>
                            ) : null}
                        </section>

                        <Divider />

                        <section className="space-y-3">
                            <h2 className="text-lg font-semibold">
                                {translations.kitchen_note}
                            </h2>

                            <Textarea
                                value={data.note}
                                disabled={!item.available}
                                maxLength={500}
                                placeholder={translations.note_placeholder}
                                onChange={(event) =>
                                    setData('note', event.target.value)
                                }
                                className="min-h-24 resize-none"
                            />

                            {errors.note ? (
                                <p className="text-sm text-red-600">
                                    {errors.note}
                                </p>
                            ) : null}

                            <p className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                                {translations.note_promise}
                            </p>
                        </section>

                        {errors.menu_item_id ? (
                            <p className="text-sm text-red-600">
                                {errors.menu_item_id}
                            </p>
                        ) : null}

                        {errors.qty ? (
                            <p className="text-sm text-red-600">{errors.qty}</p>
                        ) : null}
                    </div>
                </main>

                <StickyDock>
                    <div className="flex w-full items-center gap-3">
                        <QuantityStepper
                            value={data.qty}
                            min={1}
                            max={99}
                            disabled={!item.available || processing}
                            onChange={(qty) => setData('qty', qty)}
                        />

                        <Button
                            type="button"
                            size="lg"
                            disabled={!item.available || processing}
                            onClick={submit}
                            className="min-w-0 flex-1"
                        >
                            <span>{translations.add_to_order}</span>

                            <span dir="ltr" className="font-mono tabular-nums">
                                ·{' '}
                                {formatMoney(money(total, restaurant.currency))}
                            </span>
                        </Button>
                    </div>
                </StickyDock>
            </div>
        </GuestLayout>
    );
}

function Divider() {
    return <div className="bg-border-default h-px w-full" />;
}

function CloseIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className="h-5 w-5"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
        >
            <path d="M6 6l12 12" />
            <path d="M18 6L6 18" />
        </svg>
    );
}
