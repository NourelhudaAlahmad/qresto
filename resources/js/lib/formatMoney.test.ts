import { afterEach, describe, expect, it } from 'vitest';

import {
    activeLocale,
    currencySymbol,
    formatMinor,
    formatMoney,
} from './formatMoney';

function pageLanguage(lang: string): void {
    document.documentElement.lang = lang;
}

afterEach(() => pageLanguage('en'));

describe('formatMinor', () => {
    it('formats minor units in the page language', () => {
        pageLanguage('en');

        expect(formatMinor(5435, 'USD')).toBe('$54.35');
        expect(formatMinor(123456, 'GBP')).toBe('£1,234.56');
    });

    it('formats Arabic-locale money on an Arabic page', () => {
        pageLanguage('ar');

        const formatted = formatMinor(5435, 'USD');

        expect(formatted).toBe(
            new Intl.NumberFormat('ar', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(54.35),
        );
        // Arabic order and marks (symbol after the amount, right-to-left
        // mark first), not the '$54.35' an 'en' pin gave every page.
        expect(formatted).not.toBe('$54.35');
        expect(formatted.startsWith('‏')).toBe(true);
        expect(formatted).toMatch(/US\$$/);
    });

    it('takes the number of fraction digits', () => {
        pageLanguage('en');

        expect(formatMinor(123456, 'USD', { digits: 0 })).toBe('$1,235');
    });

    it('shows a plain number when the currency is missing or unknown', () => {
        pageLanguage('en');

        expect(formatMinor(5435, '')).toBe('54.35');
        expect(formatMinor(5435, null)).toBe('54.35');
        expect(formatMinor(5435, undefined, { digits: 0 })).toBe('54');
        expect(formatMinor(5435, 'NOT-A-CODE')).toBe('54.35');
    });
});

describe('activeLocale', () => {
    it('ignores a lang attribute Intl cannot read', () => {
        pageLanguage('not a locale!');

        expect(activeLocale()).toBeUndefined();
        expect(() => formatMinor(5435, 'USD')).not.toThrow();
    });
});

describe('formatMoney', () => {
    it('reads the amount and currency off the Money shape', () => {
        pageLanguage('en');

        expect(
            formatMoney({ amount: 999, currency: 'EUR', formatted: '' }),
        ).toBe('€9.99');
    });
});

describe('currencySymbol', () => {
    it('returns the narrow symbol, or nothing without a currency', () => {
        pageLanguage('en');

        expect(currencySymbol('USD')).toBe('$');
        expect(currencySymbol('')).toBe('');
        expect(currencySymbol('NOT-A-CODE')).toBe('');
    });
});
