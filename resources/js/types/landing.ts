export type Restaurant = {
    id: number;
    name: string;
    tagline: string | null;
    cuisine: string | null;
    description: string | null;
    address: string | null;
    phone: string | null;
    lat: number | null;
    lng: number | null;
    currency: string;
    timezone: string;
};

export type OpeningHour = {
    day: string;
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_today: boolean;
};

export type FeaturedItem = {
    id: number;
    name: string;
    price: string;
    chef_flag: boolean;
    photo_path: string | null;
};

export type LandingTranslations = {
    open_now: string;
    closed: string;
    switch_language: string;
    hero_fallback: string;
    scan: string;
    book: string;
    reassurance: string;
    kitchen_eyebrow: string;
    kitchen_title: string;
    chef_pick: string;
    no_featured_items: string;
    room: string;
    room_fallback: string;
    find_us: string;
    powered_by: string;
    days: Record<string, string>;
};

export type LandingPageProps = {
    restaurant: Restaurant;
    hours: OpeningHour[];
    featured_items: FeaturedItem[];
    open_now: boolean;
    translations: LandingTranslations;
};

export type LandingSharedProps = {
    locale: 'en' | 'ar';
    flash?: {
        error?: string | null;
    };
};
