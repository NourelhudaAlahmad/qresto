import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Button } from './button';

describe('Button sizes', () => {
    it('resolves to the documented heights', () => {
        const { getByText } = render(
            <>
                <Button size="sm">Small</Button>
                <Button size="md">Medium</Button>
                <Button size="lg">Large</Button>
            </>,
        );

        expect(getByText('Small').classList.contains('h-[34px]')).toBe(true);
        expect(getByText('Medium').classList.contains('h-[44px]')).toBe(true);
        expect(getByText('Large').classList.contains('h-[54px]')).toBe(true);
    });
});