import type { Money } from '@/types';

export function formatMoney(money: Money): string {
    return money.formatted;
}