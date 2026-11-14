import fs from 'node:fs';
import path from 'node:path';

import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/qresto/button';
import GuestLayout from '@/layouts/guest-layout';
import LandingLayout from '@/layouts/landing-layout';

vi.mock('@/components/locale-sync', () => ({ LocaleSync: () => null }));

const IN_RESTAURANT_SCREENS = [
    'table.tsx',
    'tables.tsx',
    'menu.tsx',
    'meal.tsx',
    'cart.tsx',
    'checkout.tsx',
    'status.tsx',
];

function mount() {
    const { container } = render(
        <GuestLayout>
            <p>content</p>
        </GuestLayout>,
    );

    return {
        shell: container.querySelector('[data-guest-shell]'),
        frame: container.querySelector('[data-guest-frame]'),
        page: container.firstElementChild,
    };
}

describe('Guest layout: mobile-first, not mobile-only (#60)', () => {
    it('drops the phone shell: no 390px clamp, no 812px height, no device frame', () => {
        const { shell, frame, page } = mount();
        const classes = [shell, frame, page]
            .map((el) => el?.className ?? '')
            .join(' ');

        expect(classes).not.toContain('390px');
        expect(classes).not.toContain('812px');
        expect(classes).not.toContain('rounded-[38px]');
        expect(classes).not.toContain('shadow-[');
        expect(classes).not.toMatch(/(^|\s)(sm:)?overflow-hidden(\s|$)/);
    });

    it('lets the page scroll: the frame is not a clipped scroll area of its own', () => {
        const { frame, page } = mount();

        expect(frame?.className).not.toMatch(/absolute|overflow-y-auto/);
        expect(page?.className).not.toContain('sm:h-[100dvh]');
        expect(frame).toHaveClass('min-h-[100dvh]');
    });

    it('keeps a readable column on wide screens, without hiding overflow from sticky docks', () => {
        const { shell } = mount();

        expect(shell).toHaveClass('w-full', 'mx-auto', 'sm:max-w-[40rem]');
        // clip, not hidden: hidden would turn the shell into a scroll
        // container and break sticky headers and docks.
        expect(shell).toHaveClass('sm:overflow-x-clip');
    });

    it('on a phone: the plain full-width page it always was', () => {
        const { shell } = mount();

        const unprefixed = (shell?.className.split(/\s+/) ?? []).filter(
            (c) => !c.includes(':'),
        );

        expect(unprefixed).toEqual(
            expect.arrayContaining(['relative', 'mx-auto', 'w-full']),
        );
        expect(unprefixed.filter((c) => c.startsWith('max-w'))).toEqual([]);
        expect(unprefixed.filter((c) => c.startsWith('rounded'))).toEqual([]);
        expect(unprefixed.filter((c) => c.startsWith('border'))).toEqual([]);
    });

    it('keeps every in-restaurant guest screen inside GuestLayout, sized to the viewport, not a shell', () => {
        const guestPagesDir = path.resolve(
            import.meta.dirname,
            '../../pages/guest',
        );

        for (const file of IN_RESTAURANT_SCREENS) {
            const source = fs.readFileSync(
                path.join(guestPagesDir, file),
                'utf8',
            );

            expect(source, `${file} must keep GuestLayout`).toContain(
                "from '@/layouts/guest-layout'",
            );
            expect(source, `${file} must render <GuestLayout>`).toMatch(
                /<GuestLayout[\s>]/,
            );
            expect(source, `${file} must not use LandingLayout`).not.toContain(
                'landing-layout',
            );
            // The shell's 24px padding is gone; nothing sizes itself to it.
            expect(
                source,
                `${file} must not size itself to the old phone shell`,
            ).not.toContain('100dvh-48px');
        }
    });
});

describe('Wide-viewport compositions (#61)', () => {
    const WIDE_SCREENS = [
        'menu.tsx',
        'meal.tsx',
        'cart.tsx',
        'checkout.tsx',
        'status.tsx',
    ];
    const READING_SCREENS = ['table.tsx', 'tables.tsx'];

    const read = (file: string) =>
        fs.readFileSync(
            path.resolve(import.meta.dirname, '../../pages/guest', file),
            'utf8',
        );

    it('opens the column to 72rem from lg only when asked', () => {
        const wide = render(
            <GuestLayout wide>
                <p>content</p>
            </GuestLayout>,
        ).container.querySelector('[data-guest-shell]');

        expect(wide).toHaveClass('sm:max-w-[40rem]', 'lg:max-w-[72rem]');
        expect(wide).toHaveAttribute('data-guest-width', 'wide');

        const { shell } = mount();

        expect(shell?.className).not.toContain('lg:max-w');
        expect(shell).toHaveAttribute('data-guest-width', 'reading');
    });

    it('gives the screens with two-column or grid compositions the wide column', () => {
        for (const file of WIDE_SCREENS) {
            const source = read(file);

            expect(source, `${file} must use <GuestLayout wide>`).toContain(
                '<GuestLayout wide>',
            );
            expect(source, `${file} must lay itself out from lg`).toMatch(
                /lg:(grid|flex)/,
            );
        }
    });

    it('keeps the table screens at a reading measure', () => {
        for (const file of READING_SCREENS) {
            expect(read(file), file).not.toContain('<GuestLayout wide>');
        }
    });

    it('never changes a phone: wide-only wrappers are display: contents below lg', () => {
        for (const file of WIDE_SCREENS) {
            const wrappers = [
                ...read(file).matchAll(/className="(contents [^"]*)"/g),
            ].map((match) => match[1]);

            for (const classes of wrappers) {
                expect(
                    classes.split(/\s+/).filter((c) => !c.startsWith('lg:')),
                    `${file}: "${classes}" must only change from lg`,
                ).toEqual(['contents']);
            }
        }
    });
});

describe('Landing layout (#53)', () => {
    it('renders full-width without the 390 × 812 phone shell at any viewport', () => {
        const { container } = render(
            <LandingLayout>
                <p>landing</p>
            </LandingLayout>,
        );

        const shell = container.querySelector('[data-landing-shell]');

        expect(shell).toBeInTheDocument();
        expect(shell).toHaveClass(
            'min-h-[100dvh]',
            'w-full',
            'overflow-x-hidden',
        );
        expect(shell?.className).not.toContain('max-w-[390px]');
        expect(shell?.className).not.toContain('812px');
        expect(shell?.className).not.toContain('rounded-[38px]');
        expect(
            container.querySelector('[data-guest-shell]'),
        ).not.toBeInTheDocument();
    });

    it('still enforces the #36 single-primary-button rule on the landing page', () => {
        expect(() =>
            render(
                <LandingLayout>
                    <Button variant="primary">Scan</Button>
                    <Button variant="primary">Book</Button>
                </LandingLayout>,
            ),
        ).toThrow(/2 primary buttons/);
    });
});
