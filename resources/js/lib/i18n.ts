import { usePage } from '@inertiajs/react';

import en from '@/i18n/en.json';

export { fill } from '@/types/checkout';

/** Shared copy: lang/{locale}/guest.php and orders.php, as i18n. */
export type SharedTranslations = typeof en;

/**
 * The shared copy in the active language. The server sends it on every page
 * (HandleInertiaRequests, `i18n`); the English file is the fallback for
 * component tests that render without Inertia. SharedTranslationsTest keeps
 * en.json identical to lang/en.
 */
export function useI18n(): SharedTranslations {
    let shared: Partial<SharedTranslations> | undefined;

    try {
        // Called on every render; the try only guards test mocks.
        // eslint-disable-next-line react-hooks/rules-of-hooks
        shared = usePage<{ i18n?: Partial<SharedTranslations> }>()?.props?.i18n;
    } catch {
        // Component tests often mock @inertiajs/react without usePage.
        shared = undefined;
    }

    return {
        guest: shared?.guest ?? en.guest,
        orders: shared?.orders ?? en.orders,
    };
}
