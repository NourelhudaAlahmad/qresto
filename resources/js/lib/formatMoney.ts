import type { Money } from '@/types';

export function formatMoney(money: Money): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: money.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(money.amount / 100);
}
