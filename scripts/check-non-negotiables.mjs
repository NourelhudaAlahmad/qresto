#!/usr/bin/env node
/**
 * The design's non-negotiables (#36) that the source itself can prove.
 * Parses the JSX with the TypeScript compiler (not grep), so a className
 * built from a constant or a template is read the way React reads it.
 *
 *   02  Nothing interactive is under 44px (a `touch-target` hit area counts)
 *   05  Type stays on the scale: no text-xs, text-sm or sub-15px arbitrary
 *       sizes. Micro, caption and label are the design's own small roles.
 *   06  Money renders through <Numeric> or in mono, tabular figures
 *   §06 No physical-direction utilities (ml-, pr-, left-, text-right…)
 *
 * Run: node scripts/check-non-negotiables.mjs [paths…]   (part of npm run lint)
 * Exit 1 lists every break with file:line.
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import ts from 'typescript';

const ROOT = path.resolve(import.meta.dirname, '..');

/** QResto's own screens. The Laravel starter-kit pages are not the design. */
const SCOPE = [
    'resources/js/components/qresto',
    'resources/js/pages/guest',
    'resources/js/pages/staff',
    'resources/js/pages/errors',
    'resources/js/layouts/staff-layout.tsx',
    'resources/js/layouts/guest-layout.tsx',
];

/** Heights of the shared Button sizes, from button.tsx. */
const BUTTON_SIZE = { sm: 34, md: 44, lg: 54 };

const PHYSICAL =
    /^(?:-?m[lr]|p[lr]|left|right|border-[lr]|rounded-[lr]|rounded-(?:tl|tr|bl|br)|text-(?:left|right)|float-(?:left|right))(?:-|$)/;

const SMALL_TYPE = /^text-(?:xs|sm|\[(?:\d|1[0-4])px\]|\[0?\.\d+rem\])$/;

function files(target) {
    const full = path.resolve(ROOT, target);

    if (!fs.existsSync(full)) {
        return [];
    }

    if (fs.statSync(full).isFile()) {
        return [full];
    }

    return fs.readdirSync(full, { withFileTypes: true }).flatMap((entry) => {
        const child = path.join(target, entry.name);

        if (entry.isDirectory()) {
            return files(child);
        }

        return /\.tsx$/.test(entry.name) && !entry.name.includes('.test.')
            ? [path.resolve(ROOT, child)]
            : [];
    });
}

/** Pixel height a class sets, or null. */
function height(token) {
    let match = token.match(/^(?:min-)?(?:h|size)-(\d+(?:\.5)?)$/);

    if (match) {
        return parseFloat(match[1]) * 4;
    }

    match = token.match(/^(?:min-)?(?:h|size)-\[(\d+)px\]$/);

    return match ? Number(match[1]) : null;
}

const problems = [];

// Paths on the command line replace the default scope (used by the tests
// to prove a deliberately broken file fails).
const targets = process.argv.length > 2 ? process.argv.slice(2) : SCOPE;

for (const file of targets.flatMap(files)) {
    const text = fs.readFileSync(file, 'utf8');
    const source = ts.createSourceFile(file, text, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    const where = (node) =>
        `${path.relative(ROOT, file)}:${source.getLineAndCharacterOfPosition(node.getStart()).line + 1}`;

    // `const NUM = 'text-end font-mono'` style constants, so className={`p-3 ${NUM}`} resolves.
    const constants = new Map();

    (function collect(node) {
        if (
            ts.isVariableDeclaration(node) &&
            ts.isIdentifier(node.name) &&
            node.initializer &&
            (ts.isStringLiteralLike(node.initializer) || ts.isNoSubstitutionTemplateLiteral(node.initializer))
        ) {
            constants.set(node.name.text, node.initializer.text);
        }

        node.forEachChild(collect);
    })(source);

    const strings = (node) => {
        const parts = [];

        (function read(child) {
            if (ts.isStringLiteralLike(child) || ts.isNoSubstitutionTemplateLiteral(child)) {
                parts.push(child.text);
            } else if (ts.isTemplateExpression(child)) {
                parts.push(child.head.text);
                child.templateSpans.forEach((span) => {
                    parts.push(span.literal.text);
                    read(span.expression);
                });

                return;
            } else if (ts.isIdentifier(child) && constants.has(child.text)) {
                parts.push(constants.get(child.text));
            }

            child.forEachChild(read);
        })(node);

        return parts.join(' ');
    };

    const attribute = (element, name) =>
        element.attributes.properties.find(
            (property) => ts.isJsxAttribute(property) && property.name.getText() === name,
        );

    const classesOf = (element) => {
        const found = attribute(element, 'className');

        return found ? strings(found).split(/\s+/).filter(Boolean) : [];
    };

    (function visit(node) {
        if (ts.isJsxOpeningElement(node) || ts.isJsxSelfClosingElement(node)) {
            const tag = node.tagName.getText();
            const classes = classesOf(node);

            // §06 / F9: logical, not physical, direction utilities.
            for (const token of classes) {
                const bare = token.split(':').pop() ?? token;

                if (PHYSICAL.test(bare)) {
                    problems.push(`${where(node)}  §06 physical utility "${token}"; use the logical one (ms-/me-/ps-/pe-/start-/end-/text-start…)`);
                }

                if (SMALL_TYPE.test(bare)) {
                    problems.push(`${where(node)}  05 "${token}" is off the type scale; use text-body (15), or the design's micro/caption/label roles`);
                }
            }

            // 02: nothing interactive under 44px.
            const type = attribute(node, 'type')?.initializer?.getText() ?? '';
            const interactive =
                ['button', 'select', 'textarea', 'Button', 'Link'].includes(tag) ||
                (tag === 'a' && attribute(node, 'href')) ||
                (tag === 'input' && !/hidden|file|checkbox|radio/.test(type));

            if (interactive && !classes.includes('touch-target')) {
                const heights = classes.map((token) => height(token.split(':').pop())).filter((value) => value !== null);

                if (tag === 'Button') {
                    const size = attribute(node, 'size')?.initializer?.getText().replace(/['"{}]/g, '') ?? 'md';

                    // Button sm carries its own touch-target hit area.
                    if (size !== 'sm') {
                        heights.push(BUTTON_SIZE[size] ?? 44);
                    }
                }

                if (heights.length > 0 && Math.max(...heights) < 44) {
                    problems.push(`${where(node)}  02 <${tag}> is ${Math.max(...heights)}px; make it 44px or add "touch-target"`);
                }
            }
        }

        // 06: money through <Numeric> or in mono, tabular figures.
        if (ts.isJsxExpression(node) && node.expression && !ts.isJsxAttribute(node.parent)) {
            let containsJsx = false;

            (function scan(child) {
                if (ts.isJsxElement(child) || ts.isJsxSelfClosingElement(child) || ts.isJsxFragment(child)) {
                    containsJsx = true;
                } else {
                    child.forEachChild(scan);
                }
            })(node.expression);

            const expression = node.expression.getText();

            if (!containsJsx && /\.formatted\b|\bmoney\(|\bformatMoney\(/.test(expression)) {
                let parent = node.parent;
                let numeric = false;

                while (parent && !ts.isSourceFile(parent)) {
                    if (ts.isJsxElement(parent)) {
                        const opening = parent.openingElement;
                        const classes = classesOf(opening).join(' ');

                        if (
                            opening.tagName.getText() === 'Numeric' ||
                            /\bfont-mono\b|\btabular-nums\b|\bqr-numeric\b/.test(classes)
                        ) {
                            numeric = true;
                            break;
                        }
                    }

                    parent = parent.parent;
                }

                if (!numeric) {
                    problems.push(`${where(node)}  06 money "${expression.replace(/\s+/g, ' ').slice(0, 50)}" renders bare; wrap it in <Numeric>`);
                }
            }
        }

        node.forEachChild(visit);
    })(source);
}

if (problems.length > 0) {
    console.error(`✗ ${problems.length} non-negotiable(s) broken (#36):\n`);
    problems.forEach((problem) => console.error(`  ${problem}`));
    process.exit(1);
}

console.log('✓ Non-negotiables hold: 44px targets, type scale, money in Numeric, logical direction.');
