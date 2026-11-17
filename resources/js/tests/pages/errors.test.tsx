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
    it('explains a 403 and links back to the person’s own screen', () => {
        render(
            <ErrorShow
                status={403}
                title="This isn’t open to you"
                message="Your account doesn’t have access to this screen."
                home="/app/my-orders"
                homeLabel="Back to your screen"
                backLabel="Go back"
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

    it('shows the copy the server sent in the page language', () => {
        render(
            <ErrorShow
                status={404}
                title="لم نجد هذه الصفحة"
                message="ربما نُقلت الصفحة، أو أن الرابط قديم."
                home="/"
                homeLabel="العودة إلى البداية"
                backLabel="رجوع"
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'لم نجد هذه الصفحة' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('ربما نُقلت الصفحة، أو أن الرابط قديم.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'رجوع' })).toBeInTheDocument();
    });
});
