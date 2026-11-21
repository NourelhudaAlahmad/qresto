import { describe, expect, it, vi } from 'vitest';

import { failInPlace } from '@/lib/visit-failure';

describe('failInPlace', () => {
    it.each([403, 429, 500, 503])(
        'handles a %i in place and keeps the screen',
        (status) => {
            const onFailure = vi.fn();

            expect(failInPlace(onFailure).onHttpException({ status })).toBe(
                false,
            );
            expect(onFailure).toHaveBeenCalledOnce();
        },
    );

    it('handles a dropped connection in place', () => {
        const onFailure = vi.fn();

        expect(failInPlace(onFailure).onNetworkError()).toBe(false);
        expect(onFailure).toHaveBeenCalledOnce();
    });

    it.each([401, 419])(
        'leaves a %i to Inertia, so the person signs in again',
        (status) => {
            const onFailure = vi.fn();

            expect(
                failInPlace(onFailure).onHttpException({ status }),
            ).toBeUndefined();
            expect(onFailure).not.toHaveBeenCalled();
        },
    );
});
