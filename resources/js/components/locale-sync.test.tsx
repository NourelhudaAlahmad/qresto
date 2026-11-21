import { afterEach, describe, expect, it, vi } from 'vitest';

import { syncDocumentLocaleOnNavigate } from '@/components/locale-sync';

type NavigateListener = (event: {
    detail: { page: { props: Record<string, unknown> } };
}) => void;

let listener: NavigateListener | undefined;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: {} }),
    router: {
        on: (name: string, callback: NavigateListener) => {
            if (name === 'navigate') {
                listener = callback;
            }

            return () => {
                listener = undefined;
            };
        },
    },
}));

afterEach(() => {
    document.documentElement.lang = 'en';
    document.documentElement.dir = 'ltr';
});

describe('the page language follows every visit (staff screens too)', () => {
    it('flips <html> to Arabic, right to left, when a visit switches locale', () => {
        const stop = syncDocumentLocaleOnNavigate();

        listener?.({
            detail: { page: { props: { locale: 'ar', dir: 'rtl' } } },
        });

        expect(document.documentElement.lang).toBe('ar');
        expect(document.documentElement.dir).toBe('rtl');

        stop();
    });

    it('leaves <html> alone on a visit that carries no locale', () => {
        const stop = syncDocumentLocaleOnNavigate();

        listener?.({ detail: { page: { props: {} } } });

        expect(document.documentElement.lang).toBe('en');
        expect(document.documentElement.dir).toBe('ltr');

        stop();
    });
});
