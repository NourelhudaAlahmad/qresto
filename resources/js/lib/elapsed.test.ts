import { describe, expect, it } from 'vitest';

import { formatAgo, formatMinutes } from './elapsed';

describe('formatMinutes', () => {
    it('reads in minutes under an hour', () => {
        expect(formatMinutes(0)).toBe('0 min');
        expect(formatMinutes(14)).toBe('14 min');
        expect(formatMinutes(59)).toBe('59 min');
    });

    it('switches to hours and minutes from an hour', () => {
        expect(formatMinutes(60)).toBe('1h 0m');
        expect(formatMinutes(1167)).toBe('19h 27m');
    });

    it('never shows a negative or fractional age', () => {
        expect(formatMinutes(-3)).toBe('0 min');
        expect(formatMinutes(14.9)).toBe('14 min');
        expect(formatMinutes(null)).toBe('0 min');
    });
});

describe('formatAgo', () => {
    const now = Date.parse('2026-10-29T20:00:00Z');

    it('reads just now under a minute, then minutes and hours ago', () => {
        expect(formatAgo('2026-10-29T19:59:30Z', now)).toBe('just now');
        expect(formatAgo('2026-10-29T19:56:00Z', now)).toBe('4 min ago');
        expect(formatAgo('2026-10-29T17:50:00Z', now)).toBe('2h 10m ago');
    });

    it('never reads a future timestamp as negative', () => {
        expect(formatAgo('2026-10-29T20:05:00Z', now)).toBe('just now');
    });
});
