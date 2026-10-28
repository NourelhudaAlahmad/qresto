import { describe, expect, it } from 'vitest';

import { formatMinutes } from './elapsed';

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
