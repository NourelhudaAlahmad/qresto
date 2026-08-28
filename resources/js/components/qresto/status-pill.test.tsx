import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StatusPill } from './status-pill';

describe('StatusPill', () => {
    const statuses = [
        'placed',
        'pending',
        'preparing',
        'ready',
        'served',
        'paid',
        'cancelled',
    ] as const;

    it.each(statuses)(
        'resolves the correct token class for %s',
        (status) => {
            const { getByText } = render(
                <StatusPill status={status} />,
            );

            expect(
                getByText(
                    status.charAt(0).toUpperCase() + status.slice(1),
                ).className,
            ).toContain(`bg-[var(--status-${status}-bg)]`);

            expect(
                getByText(
                    status.charAt(0).toUpperCase() + status.slice(1),
                ).className,
            ).toContain(`text-[var(--status-${status}-fg)]`);
        },
    );
});