import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Button } from '@/components/qresto/button';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
}));

describe('Button', () => {
    it('says it is loading in the page language', () => {
        page.props = {
            i18n: { common: { loading: 'جارٍ التحميل…' } },
        };

        render(<Button loading>Pay</Button>);

        const button = screen.getByRole('button');

        expect(button).toHaveTextContent('جارٍ التحميل…');
        expect(button).not.toHaveTextContent('Pay');
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
    });

    it('falls back to English outside Inertia', () => {
        page.props = {};

        render(<Button loading>Pay</Button>);

        expect(screen.getByRole('button')).toHaveTextContent('Loading…');
    });
});
