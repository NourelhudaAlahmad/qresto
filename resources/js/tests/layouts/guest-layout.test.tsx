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

describe('Guest layout, as the design canvas draws the phone', () => {
    it('on wider screens: a clipped 390 × 812 shell around a screen that scrolls on its own', () => {
        const { shell, frame, page } = mount();

        expect(shell).toHaveClass(
            'max-w-[390px]',
            'sm:h-[min(812px,100%)]',
            'sm:overflow-hidden',
            'sm:rounded-[38px]',
        );
        expect(frame).toHaveClass(
            'sm:absolute',
            'sm:inset-0',
            'sm:overflow-y-auto',
            'sm:[scrollbar-width:none]',
        );
        // One scroll area only: the page itself does not scroll.
        expect(page).toHaveClass('sm:h-[100dvh]', 'sm:overflow-hidden');
    });

    it('on a phone: a plain full-screen page, nothing clipped or rounded', () => {
        const { shell, frame } = mount();

        const unprefixed = (el: Element | null) =>
            (el?.className.split(/\s+/) ?? []).filter((c) => !c.includes(':'));

        expect(unprefixed(shell)).not.toContain('overflow-hidden');
        expect(
            unprefixed(shell).filter((c) => c.startsWith('rounded')),
        ).toEqual([]);
        expect(frame).toHaveClass('min-h-[100dvh]');
    });

    it('keeps every in-restaurant guest screen inside the 390px GuestLayout (#53)', () => {
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
            expect(source, `${file} must render <GuestLayout>`).toContain(
                '<GuestLayout>',
            );
            expect(source, `${file} must not use LandingLayout`).not.toContain(
                'landing-layout',
            );
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
