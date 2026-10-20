import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { HelpPanel } from '@/components/qresto/help-panel';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => post(...args),
    },
}));

describe('HelpPanel', () => {
    const onOpenChange = vi.fn();
    const onCallWaiter = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
    });

    function renderHelp(phone: string | null = '+966 11 123 4567') {
        return render(
            <HelpPanel
                open={true}
                onOpenChange={onOpenChange}
                restaurantPhone={phone}
                onCallWaiter={onCallWaiter}
            />,
        );
    }

    it('shows allergen guidance and restaurant phone', () => {
        renderHelp();

        expect(
            screen.getByRole('dialog', { name: 'Help' }),
        ).toBeInTheDocument();

        expect(screen.getByText('Allergen information')).toBeInTheDocument();

        expect(
            screen.getByRole('link', {
                name: '+966 11 123 4567',
            }),
        ).toHaveAttribute('href', 'tel:+966 11 123 4567');
    });

    it('shows a fallback when the phone is unavailable', () => {
        renderHelp(null);

        expect(
            screen.getByText('Phone number unavailable.'),
        ).toBeInTheDocument();
    });

    it('posts the selected English locale', () => {
        renderHelp();

        fireEvent.click(screen.getByRole('button', { name: 'English' }));

        expect(post).toHaveBeenCalledWith(
            '/locale',
            { locale: 'en' },
            expect.objectContaining({
                preserveScroll: true,
                onSuccess: expect.any(Function),
                onFinish: expect.any(Function),
            }),
        );
    });

    it('posts the selected Arabic locale', () => {
        renderHelp();

        fireEvent.click(screen.getByRole('button', { name: 'العربية' }));

        expect(post).toHaveBeenCalledWith(
            '/locale',
            { locale: 'ar' },
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
    });

    it('prevents repeated language requests while submitting', () => {
        renderHelp();

        fireEvent.click(screen.getByRole('button', { name: 'English' }));

        expect(screen.getByRole('button', { name: 'English' })).toBeDisabled();

        expect(screen.getByRole('button', { name: 'العربية' })).toBeDisabled();

        expect(post).toHaveBeenCalledTimes(1);
    });

    it('opens the waiter sheet from Help', () => {
        renderHelp();

        fireEvent.click(screen.getByRole('button', { name: 'Call waiter' }));

        expect(onOpenChange).toHaveBeenCalledWith(false);
        expect(onCallWaiter).toHaveBeenCalledOnce();
    });
});
