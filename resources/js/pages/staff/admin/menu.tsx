import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    GripVertical,
    ImagePlus,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent, ReactNode } from 'react';

import { Numeric } from '@/components/numeric';
import { Button } from '@/components/qresto/button';
import { Checkbox } from '@/components/qresto/checkbox';
import { Drawer } from '@/components/qresto/drawer';
import { EmptyState } from '@/components/qresto/empty-state';
import { Input } from '@/components/qresto/input';
import { Label } from '@/components/qresto/label';
import { ResponsiveOverlay } from '@/components/qresto/responsive-overlay';
import { Switch } from '@/components/qresto/switch';
import { Tag } from '@/components/qresto/tag';
import { Textarea } from '@/components/qresto/textarea';
import { showToast } from '@/components/qresto/toast';

type Category = { id: number; name: string; dish_count: number };

type AllergenTag = { id?: number; name: string; may_contain: boolean };
type Variant = {
    id?: number;
    label: string;
    price_delta: string;
    is_default: boolean;
};
type Addon = {
    id?: number;
    label: string;
    price_delta: string;
    is_available: boolean;
};

type Dish = {
    id: number;
    menu_category_id: number;
    name: string;
    description: string | null;
    price: string;
    price_formatted: string;
    prep_minutes: number | null;
    available: boolean;
    chef_flag: boolean;
    dietary_tags: string[];
    photo: string | null;
    thumb: string | null;
    sold_today: number;
    allergens: AllergenTag[];
    variants: Variant[];
    addons: Addon[];
    updated_at: string | null;
    updated_by: string | null;
};

type Props = {
    currency: string;
    categories: Category[];
    dishes: Dish[];
    allergens: { id: number; name: string }[];
    photoRules: { min_width: number; ratio: string };
};

type Form = {
    menu_category_id: number;
    name: string;
    description: string;
    price: string;
    prep_minutes: string;
    chef_flag: boolean;
    dietary_tags: string[];
    allergens: AllergenTag[];
    variants: Variant[];
    addons: Addon[];
    photo: File | null;
};

type Layout = 'desktop' | 'tablet' | 'phone';
type Errors = Record<string, string>;

const DIRTY_WARNING =
    'You have unsaved changes to this dish. Leave without saving?';

/** Desktop ≥1100: three panels. Tablet: editor in a drawer. Phone: screens. */
function useLayout(): Layout {
    const read = (): Layout => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return 'desktop';
        }

        if (window.matchMedia('(min-width: 1100px)').matches) {
            return 'desktop';
        }

        return window.matchMedia('(min-width: 720px)').matches
            ? 'tablet'
            : 'phone';
    };

    const [layout, setLayout] = useState<Layout>(read);

    useEffect(() => {
        if (typeof window === 'undefined' || !window.matchMedia) {
            return;
        }

        const queries = ['(min-width: 1100px)', '(min-width: 720px)'].map(
            (query) => window.matchMedia(query),
        );
        const update = () => setLayout(read());

        queries.forEach((query) => query.addEventListener('change', update));

        return () =>
            queries.forEach((query) =>
                query.removeEventListener('change', update),
            );
    }, []);

    return layout;
}

function formFor(dish: Dish | null, categoryId: number): Form {
    return {
        menu_category_id: dish?.menu_category_id ?? categoryId,
        name: dish?.name ?? '',
        description: dish?.description ?? '',
        price: dish?.price ?? '',
        prep_minutes:
            dish?.prep_minutes === null || dish?.prep_minutes === undefined
                ? ''
                : String(dish.prep_minutes),
        chef_flag: dish?.chef_flag ?? false,
        dietary_tags: dish?.dietary_tags ?? [],
        allergens: dish?.allergens ?? [],
        variants: dish?.variants ?? [],
        addons: dish?.addons ?? [],
        photo: null,
    };
}

/** "last saved 18:42 by Yusuf Aziz" */
export function lastSaved(dish: Dish | null): string | null {
    if (!dish?.updated_at) {
        return null;
    }

    const time = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(dish.updated_at));

    return `Last saved ${time}${dish.updated_by ? ` by ${dish.updated_by}` : ''}`;
}

/**
 * Checks the design's photo rule in the browser, before uploading:
 * 4:3 and at least the minimum width. The server checks again.
 */
export function photoProblem(
    width: number,
    height: number,
    minWidth: number,
): string | null {
    if (width < minWidth) {
        return `This photo is ${width}px wide. Use one at least ${minWidth}px wide.`;
    }

    if (Math.abs(width / height - 4 / 3) > 0.01) {
        return 'Use a 4:3 photo, like 1600 × 1200.';
    }

    return null;
}

function PanelHeader({
    title,
    eyebrow,
    onBack,
    aside,
}: {
    title: ReactNode;
    eyebrow?: string;
    onBack?: () => void;
    aside?: ReactNode;
}) {
    return (
        <div className="flex min-h-14 items-center gap-2 border-b border-[var(--border-subtle)] px-4 py-2">
            {onBack && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label="Back"
                    onClick={onBack}
                >
                    <ArrowLeft className="size-4" aria-hidden />
                </Button>
            )}
            <div className="min-w-0 flex-1">
                <h2 className="font-display text-title-3 truncate font-semibold">
                    {title}
                </h2>
                {eyebrow && (
                    <p className="text-micro text-[var(--text-tertiary)]">
                        {eyebrow}
                    </p>
                )}
            </div>
            {aside}
        </div>
    );
}

function Categories({
    categories,
    selectedId,
    onSelect,
}: {
    categories: Category[];
    selectedId: number | null;
    onSelect: (id: number) => void;
}) {
    const [order, setOrder] = useState(categories);
    const [synced, setSynced] = useState(categories);
    const [dragId, setDragId] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState('');

    if (synced !== categories) {
        setSynced(categories);
        setOrder(categories);
    }

    function persist(next: Category[]) {
        setOrder(next);

        router.put(
            '/app/menu/categories/order',
            { ids: next.map((category) => category.id) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: 'Menu order saved',
                        description: 'The guest menu follows this order.',
                    }),
                onError: () => setOrder(categories),
            },
        );
    }

    function move(id: number, to: number) {
        const from = order.findIndex((category) => category.id === id);

        if (from < 0 || to < 0 || to >= order.length || from === to) {
            return;
        }

        const next = [...order];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        persist(next);
    }

    function onDrop(event: DragEvent, targetId: number) {
        event.preventDefault();

        if (dragId !== null) {
            move(
                dragId,
                order.findIndex((category) => category.id === targetId),
            );
        }

        setDragId(null);
    }

    function add() {
        if (name.trim() === '') {
            return;
        }

        router.post(
            '/app/menu/categories',
            { name },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setName('');
                    setAdding(false);
                },
                onError: (errors) =>
                    showToast({
                        type: 'error',
                        title: 'Category not added',
                        description: Object.values(errors)[0],
                    }),
            },
        );
    }

    return (
        <nav aria-label="Categories" className="flex flex-col">
            <div className="flex items-baseline gap-2 px-4 pb-2 pt-4">
                <h2 className="text-label flex-1 font-semibold text-[var(--text-secondary)]">
                    Categories
                </h2>
                <span className="text-micro text-[var(--text-tertiary)]">
                    drag to reorder
                </span>
            </div>

            <ul className="flex flex-col gap-0.5 px-2">
                {order.map((category, index) => (
                    <li
                        key={category.id}
                        draggable
                        onDragStart={() => setDragId(category.id)}
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={(event) => onDrop(event, category.id)}
                        onDragEnd={() => setDragId(null)}
                        className={dragId === category.id ? 'opacity-50' : ''}
                    >
                        <button
                            type="button"
                            aria-current={
                                selectedId === category.id ? 'true' : undefined
                            }
                            aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
                            onClick={() => onSelect(category.id)}
                            onKeyDown={(event) => {
                                if (event.altKey && event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    move(category.id, index - 1);
                                }

                                if (event.altKey && event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    move(category.id, index + 1);
                                }
                            }}
                            className={`text-body flex min-h-11 w-full items-center gap-2 rounded-[var(--radius-md)] px-2 text-start ${
                                selectedId === category.id
                                    ? 'bg-[var(--surface-sunken)] font-semibold'
                                    : 'hover:bg-[var(--action-ghost-hover)]'
                            }`}
                        >
                            <GripVertical
                                aria-hidden
                                className="size-4 shrink-0 cursor-grab text-[var(--text-tertiary)]"
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {category.name}
                            </span>
                            <Numeric className="text-caption font-mono text-[var(--text-tertiary)]">
                                {category.dish_count}
                            </Numeric>
                        </button>
                    </li>
                ))}
            </ul>

            <div className="px-2 pb-4 pt-2">
                {adding ? (
                    <div className="flex gap-2">
                        <Input
                            autoFocus
                            aria-label="New category name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    add();
                                }

                                if (event.key === 'Escape') {
                                    setAdding(false);
                                }
                            }}
                        />
                        <Button type="button" size="sm" onClick={add}>
                            Add
                        </Button>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => setAdding(true)}
                        className="text-label flex min-h-11 w-full items-center gap-2 rounded-[var(--radius-md)] px-2 font-semibold text-[var(--text-link)] hover:bg-[var(--action-ghost-hover)]"
                    >
                        <Plus className="size-4" aria-hidden />
                        New category
                    </button>
                )}
            </div>
        </nav>
    );
}

function DishTable({
    dishes,
    selectedId,
    onSelect,
}: {
    dishes: Dish[];
    selectedId: number | 'new' | null;
    onSelect: (id: number) => void;
}) {
    // Availability saves immediately, with an optimistic switch.
    const [optimistic, setOptimistic] = useState<Record<number, boolean>>({});

    function toggle(dish: Dish, available: boolean) {
        setOptimistic((current) => ({ ...current, [dish.id]: available }));

        router.patch(
            `/app/menu/items/${dish.id}/availability`,
            { available },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['dishes', 'categories'],
                onSuccess: () =>
                    showToast({
                        type: 'success',
                        title: available
                            ? `${dish.name} is back on`
                            : `${dish.name} is sold out`,
                        description: 'Guests see it on their next menu load.',
                    }),
                onError: () =>
                    showToast({
                        type: 'error',
                        title: 'Not saved',
                        description: `${dish.name} is still ${dish.available ? 'on' : 'sold out'}.`,
                    }),
                onFinish: () =>
                    setOptimistic((current) => {
                        const next = { ...current };
                        delete next[dish.id];

                        return next;
                    }),
            },
        );
    }

    if (dishes.length === 0) {
        return (
            <EmptyState
                headline="No dishes here yet"
                body="Add the first one with New dish."
            />
        );
    }

    return (
        <table className="text-body w-full table-fixed border-collapse">
            <thead className="text-label bg-[var(--surface-sunken)] text-[var(--text-secondary)]">
                <tr>
                    <th scope="col" className="w-14 p-2" />
                    <th scope="col" className="p-2 text-start font-semibold">
                        Dish
                    </th>
                    <th scope="col" className="w-24 p-2 text-end font-semibold">
                        Price
                    </th>
                    <th
                        scope="col"
                        className="w-20 p-2 text-center font-semibold"
                    >
                        On
                    </th>
                </tr>
            </thead>
            <tbody>
                {dishes.map((dish) => {
                    const available = optimistic[dish.id] ?? dish.available;

                    return (
                        <tr
                            key={dish.id}
                            onClick={() => onSelect(dish.id)}
                            aria-selected={selectedId === dish.id}
                            className={`cursor-pointer border-b border-[var(--border-subtle)] last:border-0 ${
                                selectedId === dish.id
                                    ? 'bg-[var(--surface-sunken)]'
                                    : 'hover:bg-[var(--action-ghost-hover)]'
                            } ${available ? '' : 'opacity-60'}`}
                        >
                            <td className="p-2">
                                {dish.thumb ? (
                                    <img
                                        src={dish.thumb}
                                        alt=""
                                        className="size-10 rounded-[var(--radius-sm)] object-cover"
                                    />
                                ) : (
                                    <span
                                        aria-hidden
                                        className="block size-10 rounded-[var(--radius-sm)] bg-[linear-gradient(145deg,var(--clay-300),var(--clay-700))]"
                                    />
                                )}
                            </td>
                            <td className="min-w-0 p-2">
                                <button
                                    type="button"
                                    className="block w-full truncate text-start font-medium"
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onSelect(dish.id);
                                    }}
                                >
                                    {dish.name}
                                </button>
                                <span className="text-micro text-[var(--text-tertiary)]">
                                    <Numeric>{dish.sold_today}</Numeric> sold
                                    today
                                </span>
                            </td>
                            <td className="p-2 text-end">
                                <Numeric className="font-mono">
                                    {dish.price_formatted}
                                </Numeric>
                            </td>
                            <td
                                className="p-2 text-center"
                                onClick={(event) => event.stopPropagation()}
                            >
                                <Switch
                                    checked={available}
                                    aria-label={`${dish.name} available`}
                                    onCheckedChange={(next) =>
                                        toggle(dish, next)
                                    }
                                />
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {error && (
                <span
                    role="alert"
                    className="text-caption text-[var(--berry-500)]"
                >
                    {error}
                </span>
            )}
        </div>
    );
}

function Editor({
    dish,
    form,
    setForm,
    errors,
    categories,
    allergenCatalog,
    photoRules,
    saving,
    onSave,
    onDelete,
}: {
    dish: Dish | null;
    form: Form;
    setForm: (update: (current: Form) => Form) => void;
    errors: Errors;
    categories: Category[];
    allergenCatalog: { id: number; name: string }[];
    photoRules: Props['photoRules'];
    saving: boolean;
    onSave: () => void;
    onDelete: () => void;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const [newAllergen, setNewAllergen] = useState('');
    const [newTag, setNewTag] = useState('');
    const fileInput = useRef<HTMLInputElement>(null);

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const set = <K extends keyof Form>(key: K, value: Form[K]) =>
        setForm((current) => ({ ...current, [key]: value }));

    function choosePhoto(file: File | undefined) {
        if (!file) {
            return;
        }

        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            const problem = photoProblem(
                image.naturalWidth,
                image.naturalHeight,
                photoRules.min_width,
            );

            setPhotoError(problem);

            if (problem) {
                URL.revokeObjectURL(url);

                return;
            }

            setPreview(url);
            set('photo', file);
        };

        image.onerror = () => setPhotoError('That file is not a photo.');
        image.src = url;
    }

    function addAllergen() {
        const name = newAllergen.trim();

        if (
            name === '' ||
            form.allergens.some(
                (tag) => tag.name.toLowerCase() === name.toLowerCase(),
            )
        ) {
            return;
        }

        const known = allergenCatalog.find(
            (allergen) => allergen.name.toLowerCase() === name.toLowerCase(),
        );

        set('allergens', [
            ...form.allergens,
            known
                ? { id: known.id, name: known.name, may_contain: false }
                : { name, may_contain: false },
        ]);
        setNewAllergen('');
    }

    const photo = preview ?? dish?.photo ?? null;
    const saved = lastSaved(dish);

    return (
        <div className="flex flex-col gap-5 p-4">
            {/* Photo dropzone with the design's guidance. */}
            <div
                role="button"
                tabIndex={0}
                aria-label="Drop a photo"
                onClick={() => fileInput.current?.click()}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        fileInput.current?.click();
                    }
                }}
                onDragOver={(event) => event.preventDefault()}
                onDrop={(event) => {
                    event.preventDefault();
                    choosePhoto(event.dataTransfer.files[0]);
                }}
                className="relative flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 overflow-hidden rounded-[var(--radius-lg)] border border-dashed border-[var(--border-default)] bg-[var(--surface-sunken)] text-center outline-none focus-visible:ring-[3px] focus-visible:ring-[var(--border-focus-color)]"
            >
                {photo ? (
                    <img
                        src={photo}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : (
                    <>
                        <ImagePlus
                            aria-hidden
                            className="size-6 text-[var(--text-tertiary)]"
                        />
                        <span className="text-body font-semibold">
                            Drop a photo
                        </span>
                        <span className="text-caption px-6 text-[var(--text-tertiary)]">
                            4:3, at least {photoRules.min_width}px wide. Cropped
                            square for list rows.
                        </span>
                    </>
                )}
                <input
                    ref={fileInput}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    aria-label="Choose a photo"
                    onChange={(event) => choosePhoto(event.target.files?.[0])}
                />
            </div>
            {(photoError || errors.photo) && (
                <span
                    role="alert"
                    className="text-caption -mt-3 text-[var(--berry-500)]"
                >
                    {photoError ?? errors.photo}
                </span>
            )}

            <Field id="dish-name" label="Name" error={errors.name}>
                <Input
                    id="dish-name"
                    value={form.name}
                    onChange={(event) => set('name', event.target.value)}
                />
            </Field>

            <Field
                id="dish-description"
                label="Description"
                error={errors.description}
            >
                <Textarea
                    id="dish-description"
                    rows={3}
                    maxLength={600}
                    value={form.description}
                    onChange={(event) => set('description', event.target.value)}
                />
                <span className="text-micro text-end text-[var(--text-tertiary)]">
                    <Numeric>{form.description.length}</Numeric> / 600
                </span>
            </Field>

            {/* Price and prep minutes side by side, in mono. */}
            <div className="grid grid-cols-2 gap-3">
                <Field id="dish-price" label="Price" error={errors.price}>
                    <Input
                        id="dish-price"
                        inputMode="decimal"
                        dir="ltr"
                        className="font-mono"
                        value={form.price}
                        onChange={(event) => set('price', event.target.value)}
                    />
                </Field>
                <Field
                    id="dish-prep"
                    label="Prep minutes"
                    error={errors.prep_minutes}
                >
                    <Input
                        id="dish-prep"
                        type="number"
                        min={0}
                        max={180}
                        dir="ltr"
                        className="font-mono"
                        value={form.prep_minutes}
                        onChange={(event) =>
                            set('prep_minutes', event.target.value)
                        }
                    />
                </Field>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <Field
                    id="dish-category"
                    label="Category"
                    error={errors.menu_category_id}
                >
                    <select
                        id="dish-category"
                        className="text-body h-11 w-full rounded-[var(--radius-md)] border border-[var(--border-default)] bg-[var(--surface-card)] px-3"
                        value={form.menu_category_id}
                        onChange={(event) =>
                            set('menu_category_id', Number(event.target.value))
                        }
                    >
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                </Field>
                <label className="text-body flex min-h-11 items-end gap-2 pb-2">
                    <Checkbox
                        checked={form.chef_flag}
                        onCheckedChange={(checked) =>
                            set('chef_flag', checked === true)
                        }
                    />
                    Chef’s pick
                </label>
            </div>

            {/* Allergen tags with add and remove. */}
            <section className="flex flex-col gap-2">
                <span className="text-label font-medium">Contains</span>
                <div className="flex flex-wrap gap-2">
                    {form.allergens.map((tag) => (
                        <span
                            key={tag.name}
                            className="inline-flex items-center gap-1"
                        >
                            <Tag
                                className={
                                    tag.may_contain
                                        ? '!bg-[var(--saffron-50)] !text-[var(--saffron-700)]'
                                        : ''
                                }
                            >
                                {tag.may_contain
                                    ? `May contain ${tag.name}`
                                    : tag.name}
                            </Tag>
                            <button
                                type="button"
                                className="text-micro text-[var(--text-link)]"
                                onClick={() =>
                                    set(
                                        'allergens',
                                        form.allergens.map((other) =>
                                            other.name === tag.name
                                                ? {
                                                      ...other,
                                                      may_contain:
                                                          !other.may_contain,
                                                  }
                                                : other,
                                        ),
                                    )
                                }
                            >
                                {tag.may_contain ? 'contains' : 'may contain'}
                            </button>
                            <button
                                type="button"
                                aria-label={`Remove ${tag.name}`}
                                className="touch-target grid size-6 place-items-center rounded-full text-[var(--text-tertiary)] hover:bg-[var(--action-ghost-hover)]"
                                onClick={() =>
                                    set(
                                        'allergens',
                                        form.allergens.filter(
                                            (other) => other.name !== tag.name,
                                        ),
                                    )
                                }
                            >
                                <X className="size-3" aria-hidden />
                            </button>
                        </span>
                    ))}
                </div>
                <div className="flex gap-2">
                    <Input
                        aria-label="Add an allergen"
                        list="allergen-catalog"
                        placeholder="Add an allergen"
                        value={newAllergen}
                        onChange={(event) => setNewAllergen(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                addAllergen();
                            }
                        }}
                    />
                    <datalist id="allergen-catalog">
                        {allergenCatalog.map((allergen) => (
                            <option key={allergen.id} value={allergen.name} />
                        ))}
                    </datalist>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addAllergen}
                    >
                        Add
                    </Button>
                </div>
            </section>

            {/* Dietary tags shown on the guest menu. */}
            <section className="flex flex-col gap-2">
                <span className="text-label font-medium">Dietary tags</span>
                <div className="flex flex-wrap gap-2">
                    {form.dietary_tags.map((tag) => (
                        <button
                            key={tag}
                            type="button"
                            aria-label={`Remove ${tag}`}
                            onClick={() =>
                                set(
                                    'dietary_tags',
                                    form.dietary_tags.filter(
                                        (other) => other !== tag,
                                    ),
                                )
                            }
                        >
                            <Tag>{tag} ×</Tag>
                        </button>
                    ))}
                    <Input
                        aria-label="Add a dietary tag"
                        placeholder="vegan"
                        className="h-8 w-28"
                        value={newTag}
                        onChange={(event) => setNewTag(event.target.value)}
                        onKeyDown={(event) => {
                            const tag = newTag.trim().toLowerCase();

                            if (event.key === 'Enter' && tag !== '') {
                                event.preventDefault();

                                if (!form.dietary_tags.includes(tag)) {
                                    set('dietary_tags', [
                                        ...form.dietary_tags,
                                        tag,
                                    ]);
                                }

                                setNewTag('');
                            }
                        }}
                    />
                </div>
            </section>

            <OptionRows
                title="Sizes"
                rows={form.variants}
                onChange={(rows) => set('variants', rows as Variant[])}
                blank={{ label: '', price_delta: '0.00', is_default: false }}
                flag={{ key: 'is_default', label: 'Default' }}
            />

            <OptionRows
                title="Add-ons"
                rows={form.addons}
                onChange={(rows) => set('addons', rows as Addon[])}
                blank={{ label: '', price_delta: '0.00', is_available: true }}
                flag={{ key: 'is_available', label: 'Available' }}
            />

            <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-3 border-t border-[var(--border-subtle)] bg-[var(--surface-card)] px-4 py-3">
                <span className="text-caption min-w-0 flex-1 text-[var(--text-tertiary)]">
                    {saved ?? 'Not saved yet'}
                </span>
                {dish && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onDelete}
                        aria-label={`Delete ${dish.name}`}
                    >
                        <Trash2
                            className="size-4 text-[var(--berry-500)]"
                            aria-hidden
                        />
                    </Button>
                )}
                <Button
                    type="button"
                    variant="primary"
                    loading={saving}
                    onClick={onSave}
                >
                    Save
                </Button>
            </div>
        </div>
    );
}

function OptionRows({
    title,
    rows,
    onChange,
    blank,
    flag,
}: {
    title: string;
    rows: (Variant | Addon)[];
    onChange: (rows: (Variant | Addon)[]) => void;
    blank: Variant | Addon;
    flag: { key: 'is_default' | 'is_available'; label: string };
}) {
    const update = (index: number, change: Partial<Variant & Addon>) =>
        onChange(
            rows.map((row, i) => (i === index ? { ...row, ...change } : row)),
        );

    return (
        <section className="flex flex-col gap-2">
            <span className="text-label font-medium">{title}</span>
            {rows.map((row, index) => (
                <div key={row.id ?? `new-${index}`} className="flex gap-2">
                    <Input
                        aria-label={`${title} ${index + 1} label`}
                        value={row.label}
                        onChange={(event) =>
                            update(index, { label: event.target.value })
                        }
                    />
                    <Input
                        aria-label={`${title} ${index + 1} price change`}
                        dir="ltr"
                        className="w-24 font-mono"
                        value={row.price_delta}
                        onChange={(event) =>
                            update(index, { price_delta: event.target.value })
                        }
                    />
                    <label className="text-caption flex items-center gap-1 whitespace-nowrap">
                        <Checkbox
                            checked={Boolean(
                                (row as Variant & Addon)[flag.key],
                            )}
                            onCheckedChange={(checked) =>
                                update(index, { [flag.key]: checked === true })
                            }
                        />
                        {flag.label}
                    </label>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-label={`Remove ${title} ${index + 1}`}
                        onClick={() =>
                            onChange(rows.filter((_, i) => i !== index))
                        }
                    >
                        <X className="size-4" aria-hidden />
                    </Button>
                </div>
            ))}
            <button
                type="button"
                onClick={() => onChange([...rows, { ...blank }])}
                className="text-label touch-target flex min-h-9 items-center gap-1 self-start font-semibold text-[var(--text-link)]"
            >
                <Plus className="size-4" aria-hidden />
                Add {title.toLowerCase().replace(/s$/, '')}
            </button>
        </section>
    );
}

export default function MenuManagement({
    categories,
    dishes,
    allergens,
    photoRules,
}: Props) {
    const layout = useLayout();

    const [categoryId, setCategoryId] = useState<number | null>(
        categories[0]?.id ?? null,
    );
    const [selected, setSelected] = useState<number | 'new' | null>(null);
    const [phonePanel, setPhonePanel] = useState<
        'categories' | 'dishes' | 'editor'
    >('categories');
    const [form, setFormState] = useState<Form>(() =>
        formFor(null, categories[0]?.id ?? 0),
    );
    const [dirty, setDirty] = useState(false);
    const [errors, setErrors] = useState<Errors>({});
    const [saving, setSaving] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const leavingOnPurpose = useRef(false);

    const dish = useMemo(
        () =>
            typeof selected === 'number'
                ? (dishes.find((candidate) => candidate.id === selected) ??
                  null)
                : null,
        [dishes, selected],
    );

    const visibleDishes = dishes.filter(
        (candidate) => candidate.menu_category_id === categoryId,
    );
    const category = categories.find((c) => c.id === categoryId) ?? null;

    const setForm = useCallback((update: (current: Form) => Form) => {
        setFormState(update);
        setDirty(true);
    }, []);

    // Dirty guard: leaving the page, or another Inertia visit, asks first.
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        window.addEventListener('beforeunload', beforeUnload);

        const stop = router.on('before', (event) => {
            const visit = event.detail.visit;
            const isOwnSave =
                leavingOnPurpose.current ||
                visit.method !== 'get' ||
                (visit.only?.length ?? 0) > 0;

            if (!isOwnSave && !window.confirm(DIRTY_WARNING)) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            stop();
        };
    }, [dirty]);

    function guard(): boolean {
        return !dirty || window.confirm(DIRTY_WARNING);
    }

    function open(id: number | 'new') {
        if (!guard()) {
            return;
        }

        const next =
            id === 'new'
                ? null
                : (dishes.find((candidate) => candidate.id === id) ?? null);

        setSelected(id);
        setFormState(formFor(next, categoryId ?? categories[0]?.id ?? 0));
        setDirty(false);
        setErrors({});
        setPhonePanel('editor');
    }

    function close() {
        if (!guard()) {
            return;
        }

        setSelected(null);
        setDirty(false);
        setPhonePanel('dishes');
    }

    function chooseCategory(id: number) {
        if (!guard()) {
            return;
        }

        setCategoryId(id);
        setSelected(null);
        setDirty(false);
        setPhonePanel('dishes');
    }

    function save() {
        const payload = {
            ...form,
            prep_minutes:
                form.prep_minutes === '' ? null : Number(form.prep_minutes),
            photo: form.photo ?? undefined,
        };

        const options = {
            preserveScroll: true,
            preserveState: true,
            forceFormData: form.photo !== null,
            onStart: () => {
                setSaving(true);
                leavingOnPurpose.current = true;
            },
            onFinish: () => {
                setSaving(false);
                leavingOnPurpose.current = false;
            },
            onSuccess: () => {
                setDirty(false);
                setErrors({});
                setFormState((current) => ({ ...current, photo: null }));
                showToast({ type: 'success', title: `${form.name} saved` });

                // A new dish now has a row in the table; leaving the form
                // open in "new" mode would make a second Save a duplicate.
                if (selected === 'new') {
                    setSelected(null);
                    setPhonePanel('dishes');
                }
            },
            onError: (next: Errors) => setErrors(next),
        };

        if (selected === 'new') {
            router.post('/app/menu/items', payload, options);
        } else if (dish) {
            // Method spoofing so a photo can travel as multipart.
            router.post(
                `/app/menu/items/${dish.id}`,
                { ...payload, _method: 'put' },
                options,
            );
        }
    }

    function destroy() {
        if (!dish) {
            return;
        }

        router.delete(`/app/menu/items/${dish.id}`, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                leavingOnPurpose.current = true;
            },
            onFinish: () => {
                leavingOnPurpose.current = false;
            },
            onSuccess: () => {
                setConfirmDelete(false);
                setSelected(null);
                setDirty(false);
                setPhonePanel('dishes');
                showToast({ type: 'success', title: `${dish.name} deleted` });
            },
            onError: (next) => {
                setConfirmDelete(false);
                showToast({
                    type: 'error',
                    title: 'Not deleted',
                    description: next.dish ?? Object.values(next)[0],
                });
            },
        });
    }

    const editorOpen = selected !== null;
    const editorTitle =
        selected === 'new' ? 'New dish' : (dish?.name ?? 'Dish');

    const editor = editorOpen ? (
        <Editor
            key={String(selected)}
            dish={dish}
            form={form}
            setForm={setForm}
            errors={errors}
            categories={categories}
            allergenCatalog={allergens}
            photoRules={photoRules}
            saving={saving}
            onSave={save}
            onDelete={() => setConfirmDelete(true)}
        />
    ) : null;

    const categoriesPanel = (
        <Categories
            categories={categories}
            selectedId={categoryId}
            onSelect={chooseCategory}
        />
    );

    const dishesPanel = (
        <div className="flex min-w-0 flex-col">
            <PanelHeader
                title={category?.name ?? 'Dishes'}
                eyebrow={
                    category
                        ? `${category.dish_count} ${category.dish_count === 1 ? 'dish' : 'dishes'}`
                        : undefined
                }
                onBack={
                    layout === 'phone'
                        ? () => setPhonePanel('categories')
                        : undefined
                }
                aside={
                    <Button
                        type="button"
                        size="sm"
                        variant="primary"
                        disabled={category === null}
                        onClick={() => open('new')}
                    >
                        <Plus className="size-4" aria-hidden />
                        New dish
                    </Button>
                }
            />
            <div className="overflow-x-auto">
                <DishTable
                    dishes={visibleDishes}
                    selectedId={selected}
                    onSelect={(id) => open(id)}
                />
            </div>
        </div>
    );

    const card =
        'min-w-0 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-rest)]';

    return (
        <>
            <Head title="Menu" />

            {layout === 'phone' ? (
                // Phone: three full screens with a back path.
                <div className={card}>
                    {phonePanel === 'categories' && categoriesPanel}
                    {phonePanel === 'dishes' && dishesPanel}
                    {phonePanel === 'editor' && editorOpen && (
                        <>
                            <PanelHeader title={editorTitle} onBack={close} />
                            {editor}
                        </>
                    )}
                </div>
            ) : (
                <div
                    className={`grid items-start gap-4 ${
                        layout === 'desktop'
                            ? 'grid-cols-[176px_minmax(0,1.4fr)_minmax(0,1fr)]'
                            : 'grid-cols-[176px_minmax(0,1fr)]'
                    }`}
                >
                    <div className={card}>{categoriesPanel}</div>
                    <div className={card}>{dishesPanel}</div>

                    {layout === 'desktop' && (
                        <div className={card}>
                            {editorOpen ? (
                                <>
                                    <PanelHeader
                                        title={editorTitle}
                                        aside={
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                aria-label="Close editor"
                                                onClick={close}
                                            >
                                                <X
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </Button>
                                        }
                                    />
                                    {editor}
                                </>
                            ) : (
                                <EmptyState
                                    headline="Pick a dish to edit"
                                    body="Sold-out is the switch in the table; it saves on its own."
                                />
                            )}
                        </div>
                    )}
                </div>
            )}

            {layout === 'tablet' && (
                <Drawer
                    open={editorOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            close();
                        }
                    }}
                    title={editorTitle}
                >
                    <div className="-m-5">{editor}</div>
                </Drawer>
            )}

            {confirmDelete && dish && (
                <ResponsiveOverlay
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirmDelete(false);
                        }
                    }}
                    title={`Delete ${dish.name}?`}
                    description="It leaves the guest menu. Past orders keep its name and price."
                >
                    <div className="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Keep it
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="flex-1"
                            onClick={destroy}
                        >
                            Delete dish
                        </Button>
                    </div>
                </ResponsiveOverlay>
            )}
        </>
    );
}
