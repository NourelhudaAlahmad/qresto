import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import ErrorShow from '@/pages/errors/show';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
        <a href={href}>{children}</a>
    ),
}));

describe('Designed error page', () => {
    it('explains a 403 and links back to the person\u2019s own screen', () => {
        render(
            <ErrorShow
                status={403}
                home="/app/my-orders"
                homeLabel="Back to your screen"
            />,
        );

        expect(screen.getByText('403')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'This isn’t open to you' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Back to your screen' }),
        ).toHaveAttribute('href', '/app/my-orders');
    });

    it('prefers a reason we wrote on purpose', () => {
        render(
            <ErrorShow
                status={403}
                message="This table is not yours tonight."
                home="/app/my-orders"
                homeLabel="Back to your screen"
            />,
        );

        expect(
            screen.getByText('This table is not yours tonight.'),
        ).toBeInTheDocument();
    });
});
