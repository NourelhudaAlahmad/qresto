import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();

const sourceTokensDir = path.join(root, 'docs', 'design', 'ds', 'tokens');
const appCss = path.join(root, 'resources', 'css', 'app.css');
const compiledCssDir = path.join(root, 'public', 'build', 'assets');

function read(file) {
    return fs.readFileSync(file, 'utf8');
}

function getCustomProperties(css) {
    return new Set(
        [...css.matchAll(/(--[a-zA-Z0-9_-]+)\s*:/g)].map(
            (match) => match[1],
        ),
    );
}

function collectImportedCss(entryFile, visited = new Set()) {
    const resolved = path.resolve(entryFile);

    if (visited.has(resolved)) {
        return '';
    }

    visited.add(resolved);

    const css = read(resolved);
    let combined = css;

    const importRegex = /@import\s+(?:url\()?["']([^"')]+)["']\)?\s*;/g;

    for (const match of css.matchAll(importRegex)) {
        const importPath = match[1];

        if (
            importPath.startsWith('http://') ||
            importPath.startsWith('https://')
        ) {
            continue;
        }

        const importedFile = path.resolve(
            path.dirname(resolved),
            importPath,
        );

        if (fs.existsSync(importedFile)) {
            combined += `\n${collectImportedCss(importedFile, visited)}`;
        }
    }

    return combined;
}

function getTokenFiles() {
    return fs
        .readdirSync(sourceTokensDir)
        .filter((file) => file.endsWith('.css'))
        .sort();
}

function getCompiledCss() {
    if (!fs.existsSync(compiledCssDir)) {
        throw new Error(
            'public/build/assets does not exist. Run "npm run build" first.',
        );
    }

    return fs
        .readdirSync(compiledCssDir)
        .filter((file) => file.endsWith('.css'))
        .map((file) => read(path.join(compiledCssDir, file)))
        .join('\n');
}

const tokenFiles = getTokenFiles();

if (tokenFiles.length === 0) {
    throw new Error('No token CSS files found.');
}

const expectedTokens = new Set();

for (const file of tokenFiles) {
    const css = read(path.join(sourceTokensDir, file));

    for (const token of getCustomProperties(css)) {
        expectedTokens.add(token);
    }
}

const appSource = collectImportedCss(appCss);
const appTokens = getCustomProperties(appSource);

const missingTokens = [...expectedTokens]
    .filter((token) => !appTokens.has(token))
    .sort();

if (missingTokens.length > 0) {
    console.error('\nToken drift detected.\n');
    console.error('Missing tokens from app CSS source graph:\n');

    for (const token of missingTokens) {
        console.error(`  - ${token}`);
    }

    process.exit(1);
}

const compiledCss = getCompiledCss();

const clayMatch = compiledCss.match(
    /--clay-500\s*:\s*#C85C34\b/i,
);

if (!clayMatch) {
    console.error(
        '\nCompiled CSS is missing "--clay-500: #C85C34".\n',
    );

    process.exit(1);
}

console.log(
    `✓ ${expectedTokens.size} QResto design tokens are present.`,
);

console.log(
    '✓ Compiled CSS contains --clay-500: #C85C34.',
);

console.log('✓ Token drift check passed.');