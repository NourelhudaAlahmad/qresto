import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Numeric } from './numeric';

describe('Numeric', () => {
    it('renders exactly its children, with no stray whitespace (#43)', () => {
        const { container } = render(<Numeric>$18.00</Numeric>);
        const span = container.querySelector('[data-numeric]');

        // textContent is not normalised, unlike getByText, so a stray
        // leading or trailing space would fail here.
        expect(span?.textContent).toBe('$18.00');
    });

    it('stays an isolated left-to-right run', () => {
        const { container } = render(<Numeric>$18.00</Numeric>);
        const span = container.querySelector('[data-numeric]');

        expect(span).toHaveAttribute('dir', 'ltr');
        expect(span).toHaveClass('qr-numeric');
    });

    it('emits a clean class list with and without extra classes', () => {
        const { container } = render(
            <>
                <Numeric>1</Numeric>
                <Numeric className="text-end">2</Numeric>
            </>,
        );
        const [plain, extended] = container.querySelectorAll('[data-numeric]');

        expect(plain.getAttribute('class')).toBe('qr-numeric');
        expect(extended.getAttribute('class')).toBe('qr-numeric text-end');
    });

    it('adds no whitespace of its own inside an Arabic line', () => {
        const { container } = render(
            <p dir="rtl">
                المجموع <Numeric>$14.06</Numeric>
            </p>,
        );

        expect(container.querySelector('p')?.textContent).toBe(
            'المجموع $14.06',
        );
    });
});
