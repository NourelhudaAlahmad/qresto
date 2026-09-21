import { Head } from '@inertiajs/react';
import {
    CircleHelp,
    ClipboardList,
    Menu as MenuIcon,
    SearchX,
    Timer,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Button } from '@/components/qresto/button';
import { ChipFilter } from '@/components/qresto/chip-filter';
import { EmptyState } from '@/components/qresto/empty-state';
import { MenuItemCard } from '@/components/qresto/menu-item-card';
import { SearchField } from '@/components/qresto/search-field';
import { SectionHead } from '@/components/qresto/section-head';
import { StickyDock } from '@/components/qresto/sticky-dock';
import { TabBar } from '@/components/qresto/tab-bar';
import GuestLayout from '@/layouts/guest-layout';

type Money = {
    amount: number;
    currency: string;
    formatted: string;
};

type Allergen = {
    id: number;
    name: string;
    may_contain: boolean;
};

type MenuItem = {
    id: number;
    name: string;
    description: string | null;
    price: Money;
    photo: string | null;
    tags: string[];
    allergens: Allergen[];
    available: boolean;
    prep_minutes: number | null;
    flag: string | null;
};

type MenuCategory = {
    id: number;
    name: string;
    sort: number;
    items: MenuItem[];
};

type MenuPageProps = {
    restaurant: {
        id: number;
        name: string;
        currency: string;
    };

    table: {
        id: number;
        number: string | number;
    };

    session: {
        active: boolean;
    };

    categories: MenuCategory[];

    cart: {
        count: number;
        subtotal: Money | null;
    };
};

const ALL_CATEGORIES = 'all';
const ALL_DIETARY = 'all';

export default function Menu({
    restaurant,
    table,
    categories,
    cart,
}: MenuPageProps) {
    const [search, setSearch] = useState('');
    const [activeCategory, setActiveCategory] = useState<
        number | typeof ALL_CATEGORIES
    >(ALL_CATEGORIES);
    const [activeDietary, setActiveDietary] = useState(ALL_DIETARY);

    const dietaryTags = useMemo(() => {
        return Array.from(
            new Set(
                categories.flatMap((category) =>
                    category.items.flatMap((item) => item.tags),
                ),
            ),
        );
    }, [categories]);

    const filteredCategories = useMemo(() => {
        const normalizedSearch = search.trim().toLocaleLowerCase();

        return categories
            .filter(
                (category) =>
                    activeCategory === ALL_CATEGORIES ||
                    category.id === activeCategory,
            )
            .map((category) => ({
                ...category,
                items: category.items.filter((item) => {
                    const matchesSearch =
                        normalizedSearch === '' ||
                        item.name
                            .toLocaleLowerCase()
                            .includes(normalizedSearch) ||
                        (item.description ?? '')
                            .toLocaleLowerCase()
                            .includes(normalizedSearch);

                    const matchesDietary =
                        activeDietary === ALL_DIETARY ||
                        item.tags.includes(activeDietary);

                    return matchesSearch && matchesDietary;
                }),
            }))
            .filter((category) => category.items.length > 0);
    }, [activeCategory, activeDietary, categories, search]);

    const visibleItemCount = filteredCategories.reduce(
        (total, category) => total + category.items.length,
        0,
    );

    const allergenNames = useMemo(() => {
        return Array.from(
            new Set(
                filteredCategories.flatMap((category) =>
                    category.items.flatMap((item) =>
                        item.allergens.map((allergen) => allergen.name),
                    ),
                ),
            ),
        );
    }, [filteredCategories]);

    return (
        <GuestLayout>
            <Head title={`${restaurant.name} · Menu`} />

            <div className="flex h-[100dvh] min-h-0 flex-col overflow-hidden bg-[var(--surface-page)]">
                {/* =========================
                    TOP HEADER
                ========================== */}
                <header className="z-20 shrink-0 border-b border-[var(--border-subtle)] bg-[var(--surface-page)]">
                    <div className="px-4 pb-3 pt-4">
                        {/* Restaurant title */}
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h1 className="font-display truncate text-[26px] font-semibold leading-none tracking-[-0.04em]">
                                    {restaurant.name}
                                </h1>

                                <p className="mt-1 font-mono text-xs tracking-[0.02em] text-[var(--text-tertiary)]">
                                    Kitchen closes 22:30
                                </p>
                            </div>

                            <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--clay-50)] px-3 py-2 text-xs font-semibold text-[var(--clay-700)]">
                                <span aria-hidden="true">🍴</span>
                                Table {table.number}
                            </span>
                        </div>

                        {/* Search */}
                        <SearchField
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search the menu"
                            aria-label="Search the menu"
                        />

                        {/* Category chips */}
                        <div className="scrollbar-none -mx-4 mt-3 overflow-x-auto px-4">
                            <div className="flex w-max gap-2">
                                <ChipFilter
                                    type="category"
                                    active={activeCategory === ALL_CATEGORIES}
                                    onClick={() =>
                                        setActiveCategory(ALL_CATEGORIES)
                                    }
                                >
                                    All
                                </ChipFilter>

                                {categories.map((category) => (
                                    <ChipFilter
                                        key={category.id}
                                        type="category"
                                        active={activeCategory === category.id}
                                        onClick={() =>
                                            setActiveCategory(category.id)
                                        }
                                    >
                                        {category.name}
                                    </ChipFilter>
                                ))}
                            </div>
                        </div>

                        {/* Dietary chips */}
                        {dietaryTags.length > 0 && (
                            <div className="scrollbar-none -mx-4 mt-2 overflow-x-auto px-4">
                                <div className="flex w-max gap-2">
                                    <ChipFilter
                                        active={activeDietary === ALL_DIETARY}
                                        onClick={() =>
                                            setActiveDietary(ALL_DIETARY)
                                        }
                                    >
                                        All diets
                                    </ChipFilter>

                                    {dietaryTags.map((tag) => (
                                        <ChipFilter
                                            key={tag}
                                            active={activeDietary === tag}
                                            onClick={() =>
                                                setActiveDietary(tag)
                                            }
                                        >
                                            {tag}
                                        </ChipFilter>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </header>

                {/* =========================
                    SCROLLABLE MENU
                ========================== */}
                <main className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-10 pt-5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {visibleItemCount === 0 ? (
                        <EmptyState
                            icon={<SearchX className="size-7" />}
                            headline="Nothing found"
                            body="Try another search or clear one of your filters."
                            className="min-h-[320px]"
                        />
                    ) : (
                        <div className="space-y-8">
                            {filteredCategories.map((category) => (
                                <section key={category.id}>
                                    {/* Section heading */}
                                    <SectionHead
                                        title={category.name}
                                        count={category.items.length}
                                    />

                                    {/* Items */}
                                    <div className="divide-border-subtle mt-2 divide-y">
                                        {category.items.map((item) => (
                                            <div key={item.id} className="py-0">
                                                <MenuItemCard
                                                    name={item.name}
                                                    description={
                                                        item.description
                                                    }
                                                    price={item.price.formatted}
                                                    photo={item.photo}
                                                    tags={item.tags}
                                                    flag={item.flag}
                                                    available={item.available}
                                                />

                                                {item.prep_minutes !== null && (
                                                    <div className="text-micro -mt-2 flex items-center gap-1 pb-3 text-[var(--text-tertiary)]">
                                                        <Timer className="size-3" />

                                                        <span>
                                                            {item.prep_minutes}{' '}
                                                            min
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))}

                            {/* Allergy note */}
                            {allergenNames.length > 0 && (
                                <p className="text-caption border-border-subtle border-t pb-4 pt-4 text-[var(--text-tertiary)]">
                                    Allergens are listed on every dish. Tell
                                    your waiter about anything severe — the
                                    kitchen shares equipment.
                                </p>
                            )}
                        </div>
                    )}
                </main>

                {/* =========================
                    ORDER CTA
                ========================== */}
                {cart.count > 0 && cart.subtotal !== null && (
                    <StickyDock className="shrink-0">
                        <Button
                            size="lg"
                            fullWidth
                            className="h-14 rounded-full text-base font-semibold shadow-sm"
                        >
                            View order · {cart.count}{' '}
                            {cart.count === 1 ? 'item' : 'items'} ·{' '}
                            {cart.subtotal.formatted}
                        </Button>
                    </StickyDock>
                )}

                {/* =========================
                    BOTTOM NAVIGATION
                ========================== */}
                <div className="shrink-0">
                    <TabBar
                        items={[
                            {
                                label: 'Menu',
                                icon: <MenuIcon className="size-5" />,
                                active: true,
                            },
                            {
                                label: 'Order',
                                icon: <ClipboardList className="size-5" />,
                                badge: cart.count > 0 ? cart.count : undefined,
                            },
                            {
                                label: 'Status',
                                icon: (
                                    <span className="font-mono text-base leading-none">
                                        02
                                    </span>
                                ),
                            },
                            {
                                label: 'Help',
                                icon: <CircleHelp className="size-5" />,
                            },
                        ]}
                    />
                </div>
            </div>
        </GuestLayout>
    );
}
