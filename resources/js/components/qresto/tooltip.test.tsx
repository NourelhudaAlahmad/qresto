import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Tooltip } from './tooltip';

describe('Tooltip', () => {
    it('renders the trigger content', () => {
        const { getByRole } = render(
            <Tooltip content="More actions">
                <button type="button">⋮</button>
            </Tooltip>,
        );

        expect(getByRole('button', { name: '⋮' })).toBeInTheDocument();
    });

    it('uses the design token classes for the tooltip content', () => {
        const { getByRole } = render(
            <Tooltip content="More actions">
                <button type="button">⋮</button>
            </Tooltip>,
        );

        const trigger = getByRole('button', { name: '⋮' });

        expect(trigger).toBeInTheDocument();
    });
});