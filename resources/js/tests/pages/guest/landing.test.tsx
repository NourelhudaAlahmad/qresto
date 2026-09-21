import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Landing from '@/pages/guest/landing';
import type { LandingPageProps } from '@/types/landing';

const post = vi.fn();

const landingProps: LandingPageProps = {
    restaurant: {
        id: 1,
        name: 'Levantine',
        tagline: 'Charcoal, citrus, and a long table.',
        cuisine: 'Levantine',
        description:
            'A modern Levantine restaurant serving charcoal-grilled dishes.',
        address: 'Al Bustan Street',
        phone: '+1 (212) 555 0148',
        lat: 40.7128,
        lng: -74.006,
        currency: 'USD',
        timezone: 'America/New_York',
    },

    hours: [
        {
            day: 'Sunday',
            day_of_week: 0,
            opens_at: '12:00',
            closes_at: '22:00',
            is_today: true,
        },
        {
            day: 'Monday',
            day_of_week: 1,
            opens_at: '17:00',
            closes_at: '23:00',
            is_today: false,
        },
    ],

    featured_items: [
        {
            id: 1,
            name: 'Lamb kofta',
            price: '$18.00',
            chef_flag: true,
            photo_path: null,
        },
        {
            id: 2,
            name: 'Charred aubergine',
            price: '$12.50',
            chef_flag: false,
            photo_path: null,
        },
    ],

    open_now: true,

    translations: {
        open_now: 'Open now',
        closed: 'Closed',
        switch_language: 'Switch language to :language',
        hero_fallback: 'Charcoal, citrus, and a long table.',
        scan: 'Scan the code on your table',
        book: 'Book a table',
        reassurance: 'No app. No account. Just scan and start your order.',
        kitchen_eyebrow: "Tonight's kitchen",
        kitchen_title: "What's worth ordering",
        chef_pick: "Chef's pick",
        no_featured_items: "Tonight's featured dishes are being prepared.",
        room: 'The room',
        room_fallback:
            'A warm room built around good food, live fire, and a long table.',
        find_us: 'Find us',
        powered_by: 'Powered by QResto',
        days: {
            Sunday: 'Sunday',
            Monday: 'Monday',
        },
    },
};

let currentSharedProps = {
    locale: 'en' as const,
    flash: {
        error: null,
    },
};

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,

    router: {
        post: (...args: unknown[]) => post(...args),
    },

    usePage: () => ({
        props: currentSharedProps,
    }),
}));

describe('Guest landing page', () => {
    beforeEach(() => {
        post.mockClear();

        currentSharedProps = {
            locale: 'en',
            flash: {
                error: null,
            },
        };
    });

    it('renders the restaurant, status, featured items, and primary actions', () => {
        render(<Landing {...landingProps} />);

        expect(screen.getAllByText('Levantine')).toHaveLength(2);

        expect(screen.getByText('Open now')).toBeInTheDocument();

        expect(
            screen.getByRole('link', {
                name: /scan the code on your table/i,
            }),
        ).toHaveAttribute('href', '#scan');

        expect(
            screen.getByRole('link', {
                name: /book a table/i,
            }),
        ).toHaveAttribute('href', 'tel:+1(212)5550148');

        expect(screen.getByText('Lamb kofta')).toBeInTheDocument();
        expect(screen.getByText('Charred aubergine')).toBeInTheDocument();
        expect(screen.getByText("Chef's pick")).toBeInTheDocument();
    });

    it('renders the closed status when the restaurant is closed', () => {
        render(<Landing {...landingProps} open_now={false} />);

        expect(screen.getByText('Closed')).toBeInTheDocument();
        expect(screen.queryByText('Open now')).not.toBeInTheDocument();
    });

    it('keeps prices, opening times, and phone numbers left-to-right', () => {
        render(<Landing {...landingProps} />);

        expect(screen.getByText('$18.00')).toHaveAttribute('dir', 'ltr');

        expect(screen.getByText(/12:00 PM.*10:00 PM/)).toHaveAttribute(
            'dir',
            'ltr',
        );

        expect(screen.getByText('+1 (212) 555 0148')).toHaveAttribute(
            'dir',
            'ltr',
        );
    });

    it('marks today and renders the room prose at 17px', () => {
        render(<Landing {...landingProps} />);

        const sunday = screen.getByText('Sunday');
        const todayRow = sunday.parentElement;

        expect(todayRow).toHaveClass('font-semibold');
        expect(todayRow).toHaveClass('font-mono');

        const roomProse = screen.getAllByText(
            'A modern Levantine restaurant serving charcoal-grilled dishes.',
        )[1];

        expect(roomProse).toHaveClass('text-[17px]');
    });

    it('switches from English to Arabic while preserving page state', () => {
        render(<Landing {...landingProps} />);

        fireEvent.click(
            screen.getByRole('button', {
                name: /switch language/i,
            }),
        );

        expect(post).toHaveBeenCalledWith(
            '/locale',
            {
                locale: 'ar',
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    });

    it('renders the empty featured-items state', () => {
        render(<Landing {...landingProps} featured_items={[]} />);

        expect(
            screen.getByText("Tonight's featured dishes are being prepared."),
        ).toBeInTheDocument();
    });
});
