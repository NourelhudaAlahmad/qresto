import type { Money } from '@/types';

type MoneyOptions = {
    /** Fraction digits; 2 by default, 0 for whole-unit summaries. */
    digits?: number;
};

/**
 * The page's active language, as LocaleSync keeps it on <html lang>, so an
 * Arabic page formats money in Arabic. Undefined outside a browser or for
 * a tag Intl rejects, which lets Intl fall back to the runtime default.
 */
export function activeLocale(): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const lang = document.documentElement.lang;

    if (!lang) {
        return undefined;
    }

    try {
        return Intl.getCanonicalLocales(lang)[0];
    } catch {
        return undefined;
    }
}

/**
 * Minor units → display money in the active locale: the one money
 * formatter for every page (#55).
 *
 * A missing or unknown currency never guesses one: the amount is shown as
 * a plain number in the same locale and precision, with no symbol.
 */
export function formatMinor(
    minor: number,
    currency: string | null | undefined,
    { digits = 2 }: MoneyOptions = {},
): string {
    const amount = minor / 100;
    const precision = {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    };

    if (currency) {
        try {
            return new Intl.NumberFormat(activeLocale(), {
                style: 'currency',
                currency,
                ...precision,
            }).format(amount);
        } catch {
            // An unknown ISO code: fall through to the plain number.
        }
    }

    return new Intl.NumberFormat(activeLocale(), precision).format(amount);
}

export function formatMoney(money: Money, options?: MoneyOptions): string {
    return formatMinor(Number(money.amount), money.currency, options);
}

/** The currency's narrow symbol in the active locale ("$", "£"), or "". */
export function currencySymbol(currency: string | null | undefined): string {
    if (!currency) {
        return '';
    }

    try {
        return (
            new Intl.NumberFormat(activeLocale(), {
                style: 'currency',
                currency,
                currencyDisplay: 'narrowSymbol',
            })
                .formatToParts(0)
                .find((part) => part.type === 'currency')?.value ?? ''
        );
    } catch {
        return '';
    }
}
