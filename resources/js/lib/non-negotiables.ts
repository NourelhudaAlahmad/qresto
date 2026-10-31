/**
 * The design's non-negotiables (#36), checked at runtime where only the
 * rendered page can tell. Under test a broken rule throws, so every page
 * test that renders a dock or a guest screen enforces it. In development it
 * warns; in production it does nothing.
 */

const PRIMARY = '[data-slot="qresto-button"][data-variant="primary"]';

function report(message: string): void {
    if (import.meta.env.MODE === 'test') {
        throw new Error(`Non-negotiable broken: ${message}`);
    }

    if (import.meta.env.DEV) {
        console.warn(`[QResto non-negotiable] ${message}`);
    }
}

/**
 * 01 — on the guest phone the primary action lives in the glass dock: at
 * most one, 54px tall (size lg) and full width.
 */
export function checkDock(dock: HTMLElement | null): void {
    if (!dock) {
        return;
    }

    const primaries = dock.querySelectorAll<HTMLElement>(PRIMARY);

    if (primaries.length > 1) {
        report(
            `the dock has ${primaries.length} primary buttons; it may have one.`,
        );
    }

    primaries.forEach((button) => {
        // Full width, or filling what the dock has left beside a quantity
        // stepper (the meal page's design).
        const fills =
            button.classList.contains('w-full') ||
            button.classList.contains('flex-1');

        if (button.dataset.size !== 'lg' || !fills) {
            report(
                `the dock's primary button "${button.textContent?.trim()}" must be 54px (size lg) and fill the dock's width.`,
            );
        }
    });
}

/** 01 — one primary button per guest screen, never two. */
export function checkScreen(screen: HTMLElement | null): void {
    if (!screen) {
        return;
    }

    const visible = [...screen.querySelectorAll<HTMLElement>(PRIMARY)].filter(
        (button) => !button.closest('[role="dialog"], [hidden]'),
    );

    if (visible.length > 1) {
        report(
            `a guest screen shows ${visible.length} primary buttons (${visible
                .map((button) => `"${button.textContent?.trim()}"`)
                .join(', ')}); it may show one.`,
        );
    }
}
