import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import AlertError from '@/components/alert-error';
import AppearanceToggleTab from '@/components/appearance-tabs';
import en from '@/i18n/en.json';

/*
 * The staff account screens (sign-in help, settings, passkeys, 2FA) take
 * their copy from the page's shared `i18n`, so an Arabic page reads Arabic.
 */
const arabicAccount = {
    ...en.staff.account,
    appearance: {
        ...en.staff.account.appearance,
        light: 'فاتح',
        dark: 'داكن',
        system: 'حسب النظام',
    },
    errors: { ...en.staff.account.errors, generic: 'حدث خطأ ما.' },
};

// jsdom has no matchMedia; the appearance hook asks it about dark mode.
vi.stubGlobal(
    'matchMedia',
    vi.fn(() => ({
        matches: false,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    })),
);

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            i18n: { ...en, staff: { ...en.staff, account: arabicAccount } },
        },
    }),
}));

describe('staff account copy follows the page language', () => {
    it('names the appearance options in Arabic', () => {
        render(<AppearanceToggleTab />);

        expect(screen.getByText('فاتح')).toBeInTheDocument();
        expect(screen.getByText('داكن')).toBeInTheDocument();
        expect(screen.getByText('حسب النظام')).toBeInTheDocument();
        expect(screen.queryByText('Light')).toBeNull();
    });

    it('titles a failure alert in Arabic', () => {
        render(<AlertError errors={['x']} />);

        expect(screen.getByText('حدث خطأ ما.')).toBeInTheDocument();
    });
});
