#!/usr/bin/env node
/**
 * #36, rule 05: text contrast at least 4.5:1 (WCAG AA, normal text).
 *
 * Reads the light-theme tokens from resources/css/tokens, resolves var()
 * chains to hex, and checks every text colour on the backgrounds it is
 * used on. Exit 1 if a pair fails that is not a documented exception, or if
 * an exception has started passing (so its entry gets removed).
 *
 * Run: node scripts/check-contrast.mjs   (part of npm run lint)
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const ROOT = path.resolve(import.meta.dirname, '..');
const MINIMUM = 4.5;

/** Text colour → backgrounds it sits on. */
const PAIRS = [
    ['--text-primary', ['--surface-page', '--surface-card', '--surface-sunken']],
    ['--text-secondary', ['--surface-page', '--surface-card', '--surface-sunken']],
    ['--text-tertiary', ['--surface-page', '--surface-card', '--surface-sunken']],
    ['--text-link', ['--surface-page', '--surface-card']],
    ['--text-on-brand', ['--action-primary']],
    ['--success-fg', ['--success-bg']],
    ['--warning-fg', ['--warning-bg']],
    ['--danger-fg', ['--danger-bg']],
    ['--info-fg', ['--info-bg']],
];

/**
 * Pairs that fail 4.5:1 in the design as delivered. Kept on purpose until
 * the design owner decides (#36, decision of 2026-10-30): changing them
 * changes the brand button colour and the grey of helper text. Each entry
 * names the token step that would pass.
 */
const EXCEPTIONS = {
    '--text-tertiary on --surface-page': 'ink-400 → ink-500 passes on page and card (4.6–4.8)',
    '--text-tertiary on --surface-card': 'ink-400 → ink-500 passes on page and card (4.6–4.8)',
    '--text-tertiary on --surface-sunken': 'only ink-600 passes here (6.66), which equals text-secondary',
    '--text-on-brand on --action-primary': 'clay-500 → clay-600 (5.73); every primary button gets darker',
    '--warning-fg on --warning-bg': 'saffron-700 → saffron-900 (8.72)',
};

function lightTokens() {
    const css = ['colors.css', 'semantic.css']
        .map((file) => fs.readFileSync(path.join(ROOT, 'resources/css/tokens', file), 'utf8'))
        .map((text) => text.split(/@media|\[data-theme/)[0])
        .join('\n');

    const tokens = {};

    for (const [, name, value] of css.matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
        tokens[name] = value.trim();
    }

    return tokens;
}

function resolve(tokens, value, depth = 0) {
    const reference = value?.match(/^var\((--[\w-]+)\)$/);

    return reference && depth < 10 ? resolve(tokens, tokens[reference[1]], depth + 1) : value;
}

function luminance(hex) {
    let digits = hex.replace('#', '');

    if (digits.length === 3) {
        digits = [...digits].map((digit) => digit + digit).join('');
    }

    const [r, g, b] = [0, 2, 4]
        .map((offset) => parseInt(digits.slice(offset, offset + 2), 16) / 255)
        .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(foreground, background) {
    const [light, dark] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);

    return (light + 0.05) / (dark + 0.05);
}

const tokens = lightTokens();
const failures = [];
const stale = [];

console.log('Text contrast (light theme), minimum 4.5:1\n');

for (const [foreground, backgrounds] of PAIRS) {
    for (const background of backgrounds) {
        const fg = resolve(tokens, tokens[foreground]);
        const bg = resolve(tokens, tokens[background]);

        if (!/^#[0-9a-f]{3,6}$/i.test(fg ?? '') || !/^#[0-9a-f]{3,6}$/i.test(bg ?? '')) {
            failures.push(`${foreground} on ${background}: could not resolve (${fg} / ${bg})`);
            continue;
        }

        const ratio = contrast(fg, bg);
        const key = `${foreground} on ${background}`;
        const passes = ratio >= MINIMUM;
        const excepted = key in EXCEPTIONS;
        const mark = passes ? '✓' : excepted ? '!' : '✗';

        console.log(`  ${mark} ${ratio.toFixed(2).padStart(5)}  ${key}${!passes && excepted ? `  (exception: ${EXCEPTIONS[key]})` : ''}`);

        if (!passes && !excepted) {
            failures.push(`${key} is ${ratio.toFixed(2)}:1`);
        }

        if (passes && excepted) {
            stale.push(key);
        }
    }
}

if (failures.length > 0 || stale.length > 0) {
    console.error('');
    failures.forEach((failure) => console.error(`✗ ${failure}, below 4.5:1`));
    stale.forEach((key) => console.error(`✗ "${key}" now passes: remove it from EXCEPTIONS in scripts/check-contrast.mjs`));
    process.exit(1);
}

console.log(`\n✓ Every text pair is at least 4.5:1, apart from ${Object.keys(EXCEPTIONS).length} documented exceptions (!).`);
