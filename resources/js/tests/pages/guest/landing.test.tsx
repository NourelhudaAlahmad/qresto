import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import Landing from '@/pages/guest/landing';
import type { LandingPageProps } from '@/types/landing';

const post = vi.fn();

const landingProps: LandingPageProps = {
    restaurant: {
        id: 1,
        name: 'Al Bustan',
        tagline: 'Charcoal, citrus, and a long table.',
        intro: 'Al Bustan cooks over live fire, seven nights a week.',
        cuisine: 'Levantine',
        description:
            'A modern Levantine restaurant serving charcoal-grilled dishes.',
        address: '14 Marsh Lane, Downtown',
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
            opens_at: '12:00:00',
            closes_at: '22:00:00',
            is_today: true,
        },
        {
            day: 'Monday',
            day_of_week: 1,
            opens_at: '17:00:00',
            closes_at: '23:00:00',
            is_today: false,
        },
        {
            day: 'Tuesday',
            day_of_week: 2,
            opens_at: '17:00:00',
            closes_at: '23:00:00',
            is_today: false,
        },
        {
            day: 'Wednesday',
            day_of_week: 3,
            opens_at: '17:00:00',
            closes_at: '23:00:00',
            is_today: false,
        },
        {
            day: 'Thursday',
            day_of_week: 4,
            opens_at: '17:00:00',
            closes_at: '23:00:00',
            is_today: false,
        },
        {
            day: 'Friday',
            day_of_week: 5,
            opens_at: '12:00:00',
            closes_at: '00:00:00',
            is_today: false,
        },
        {
            day: 'Saturday',
            day_of_week: 6,
            opens_at: '12:00:00',
            closes_at: '00:00:00',
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
        reassurance: 'Already seated? Scanning opens the menu for your table.',
        until: 'Until :time',
        every_day: 'Every day',
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
            Tuesday: 'Tuesday',
            Wednesday: 'Wednesday',
            Thursday: 'Thursday',
            Friday: 'Friday',
            Saturday: 'Saturday',
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

    it('names the restaurant, not the platform, in the header and footer', () => {
        render(<Landing {...landingProps} />);

        const header = screen.getByRole('banner');

        expect(header.textContent).toContain('Al');
        expect(header.textContent).toContain('Bustan');
        expect(header.textContent).not.toContain('QResto');
        expect(screen.getByRole('contentinfo').textContent).toContain(
            'Powered by QResto',
        );
    });

    it('renders the hero, status, featured items and primary actions', () => {
        render(<Landing {...landingProps} />);

        expect(screen.getByText('Levantine · Downtown')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Charcoal, citrus, and a long table.',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Al Bustan cooks over live fire, seven nights a week.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('Open now')).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: /scan the code on your table/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /book a table/i }),
        ).toBeInTheDocument();
        expect(screen.getByText('Lamb kofta')).toBeInTheDocument();
        expect(screen.getByText("Chef's pick")).toHaveClass(
            'text-[var(--clay-600)]',
        );
        expect(screen.getByText('Until 22:00')).toBeInTheDocument();
    });

    it('renders the closed status when the restaurant is closed', () => {
        render(<Landing {...landingProps} open_now={false} />);

        expect(screen.getByText('Closed')).toBeInTheDocument();
        expect(screen.queryByText('Open now')).not.toBeInTheDocument();
    });

    it('groups the opening hours like the design, on a 24-hour clock', () => {
        render(<Landing {...landingProps} />);

        const rows = screen
            .getAllByRole('term')
            .map((term) => [
                term.textContent,
                term.nextElementSibling?.textContent,
            ]);

        expect(rows).toEqual([
            ['Monday – Thursday', '17:00 – 23:00'],
            ['Friday – Saturday', '12:00 – 00:00'],
            ['Sunday', '12:00 – 22:00'],
        ]);
    });

    it('keeps prices, opening times, and phone numbers left-to-right', () => {
        render(<Landing {...landingProps} />);

        expect(screen.getByText('$18.00')).toHaveAttribute('dir', 'ltr');
        expect(screen.getByText('12:00 – 22:00')).toHaveAttribute('dir', 'ltr');
        expect(screen.getByText('+1 (212) 555 0148')).toHaveAttribute(
            'dir',
            'ltr',
        );
    });

    it('marks today and renders the room prose at body-lg', () => {
        render(<Landing {...landingProps} />);

        expect(screen.getByText('Sunday').parentElement).toHaveAttribute(
            'aria-current',
            'date',
        );
        expect(
            screen.getByText(
                'A modern Levantine restaurant serving charcoal-grilled dishes.',
            ),
        ).toHaveClass('text-body-lg');
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
