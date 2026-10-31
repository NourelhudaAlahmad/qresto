import axe from 'axe-core';
import { expect } from 'vitest';

/**
 * #36: an axe pass over a rendered screen. Fails on critical violations.
 * Colour contrast is checked from the tokens by scripts/check-contrast.mjs
 * instead: jsdom cannot compute rendered colours.
 */
export async function expectNoCriticalA11y(
    container: HTMLElement,
): Promise<void> {
    const results = await axe.run(container, {
        rules: { 'color-contrast': { enabled: false } },
    });

    const critical = results.violations
        .filter((violation) => violation.impact === 'critical')
        .map(
            (violation) =>
                `${violation.id}: ${violation.help} (${violation.nodes
                    .map((node) => node.target.join(' '))
                    .slice(0, 3)
                    .join('; ')})`,
        );

    expect(critical).toEqual([]);
}
