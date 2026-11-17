import { afterEach, describe, expect, it } from 'vitest';

import { serviceDateLabel } from '@/layouts/staff-layout';

// Tuesday 17 November 2026, midday UTC.
const tuesday = Date.parse('2026-11-17T12:00:00Z');

afterEach(() => {
    document.documentElement.lang = '';
});

describe('the top bar service date', () => {
    it('reads "Tuesday 17 Nov" on an English page', () => {
        document.documentElement.lang = 'en';

        expect(serviceDateLabel(tuesday)).toBe('Tuesday 17 Nov');
    });

    it('is written in Arabic on an Arabic page', () => {
        document.documentElement.lang = 'ar';

        const label = serviceDateLabel(tuesday);

        expect(label).toContain('الثلاثاء');
        expect(label).not.toContain('Tuesday');
    });
});
