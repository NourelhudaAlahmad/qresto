import { describe, expect, it } from 'vitest';

import { cn } from './utils';

describe('cn and the design type scale', () => {
    it('keeps a type-scale size next to a colour', () => {
        expect(cn('text-caption', 'text-[var(--berry-500)]')).toBe(
            'text-caption text-[var(--berry-500)]',
        );
        expect(cn('text-label', 'text-text-secondary')).toBe(
            'text-label text-text-secondary',
        );
    });

    it('still lets a later size win over an earlier one', () => {
        expect(cn('text-body', 'text-caption')).toBe('text-caption');
    });
});
