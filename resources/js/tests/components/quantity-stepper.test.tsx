import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Drawer } from '@/components/qresto/drawer';
import { QuantityStepper } from '@/components/qresto/quantity-stepper';
import { SearchField } from '@/components/qresto/search-field';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({
        props: {
            i18n: {
                common: {
                    increase: 'زيادة الكمية',
                    decrease: 'إنقاص الكمية',
                    close: 'إغلاق',
                    search: 'بحث',
                },
            },
        },
    }),
}));

describe('shared controls in Arabic', () => {
    it('names the stepper buttons in the page language', () => {
        render(<QuantityStepper value={2} onChange={() => undefined} />);

        expect(
            screen.getByRole('button', { name: 'زيادة الكمية' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'إنقاص الكمية' }),
        ).toBeInTheDocument();
    });

    it('names the drawer close button in the page language', () => {
        render(
            <Drawer open onOpenChange={() => undefined} title="Add waiter">
                <p>Body</p>
            </Drawer>,
        );

        expect(
            screen.getByRole('button', { name: 'إغلاق' }),
        ).toBeInTheDocument();
    });

    it('names an unlabelled search field in the page language', () => {
        render(<SearchField />);

        expect(
            screen.getByRole('searchbox', { name: 'بحث' }),
        ).toBeInTheDocument();
    });
});
