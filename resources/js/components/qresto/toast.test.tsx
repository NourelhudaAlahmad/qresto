import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { showToast } from './toast';

vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), {
        success: vi.fn(),
        error: vi.fn(),
    }),
}));

describe('showToast', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows a success toast', () => {
        showToast({
            type: 'success',
            title: 'Waiter added',
        });

        expect(toast.success).toHaveBeenCalledWith(
            'Waiter added',
            expect.objectContaining({
                description: undefined,
                action: undefined,
            }),
        );
    });

    it('shows an info toast with an action', () => {
        const onClick = vi.fn();

        showToast({
            type: 'info',
            title: 'Order ready',
            description: 'Table 12',
            action: {
                label: 'View',
                onClick,
            },
        });

        expect(toast).toHaveBeenCalledWith(
            'Order ready',
            expect.objectContaining({
                description: 'Table 12',
                action: {
                    label: 'View',
                    onClick,
                },
            }),
        );
    });

    it('shows an error toast', () => {
        showToast({
            type: 'error',
            title: 'Could not save dish',
        });

        expect(toast.error).toHaveBeenCalledWith(
            'Could not save dish',
            expect.objectContaining({
                description: undefined,
                action: undefined,
            }),
        );
    });
});
