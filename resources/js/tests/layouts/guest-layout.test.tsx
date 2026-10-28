import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import GuestLayout from '@/layouts/guest-layout';

vi.mock('@/components/locale-sync', () => ({ LocaleSync: () => null }));

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
});
