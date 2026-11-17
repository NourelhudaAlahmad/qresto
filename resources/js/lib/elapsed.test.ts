import { describe, expect, it } from 'vitest';

import { formatAgo, formatMinutes } from './elapsed';
import type { ElapsedCopy } from './elapsed';

const arabic: ElapsedCopy = {
    just_now: 'الآن',
    minutes: ':count د',
    hour_unit: 'س',
    minute_unit: 'د',
    ago: 'منذ :time',
};

describe('formatMinutes', () => {
    it('reads minutes under an hour and hours with minutes after, in English by default', () => {
        expect(formatMinutes(14)).toBe('14 min');
        expect(formatMinutes(1167)).toBe('19h 27m');
        expect(formatMinutes(null)).toBe('0 min');
    });

    it('speaks the page language', () => {
        expect(formatMinutes(14, arabic)).toBe('14 د');
        expect(formatMinutes(1167, arabic)).toBe('19س 27د');
    });
});

describe('formatAgo', () => {
    const now = Date.parse('2026-11-17T12:00:00Z');

    it('says just now, then minutes and hours ago', () => {
        expect(formatAgo('2026-11-17T11:59:40Z', now)).toBe('just now');
        expect(formatAgo('2026-11-17T11:56:00Z', now)).toBe('4 min ago');
        expect(formatAgo('2026-11-17T09:50:00Z', now)).toBe('2h 10m ago');
    });

    it('speaks the page language', () => {
        expect(formatAgo('2026-11-17T11:59:40Z', now, arabic)).toBe('الآن');
        expect(formatAgo('2026-11-17T11:56:00Z', now, arabic)).toBe('منذ 4 د');
    });
});
