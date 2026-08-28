import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Skeleton } from './skeleton';

describe('Skeleton', () => {
    it('renders with the default dimensions', () => {
        const { container } = render(<Skeleton />);

        const skeleton = container.firstElementChild;

        expect(skeleton).toHaveAttribute('aria-hidden', 'true');
        expect(skeleton).toHaveStyle({
            width: '100%',
            height: '12px',
            backgroundSize: '200% 100%',
            animation: 'qr-shimmer 1.4s linear infinite',
        });
    });

    it('accepts custom width and height', () => {
        const { container } = render(<Skeleton width="60%" height="20px" />);

        const skeleton = container.firstElementChild;

        expect(skeleton).toHaveStyle({
            width: '60%',
            height: '20px',
        });
    });
});
