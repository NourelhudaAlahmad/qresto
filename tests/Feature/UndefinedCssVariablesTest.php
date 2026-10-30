<?php

/*
 * Every CSS variable the front end reads must exist. An undefined one fails
 * silently: no colour, no rounded corner, a 0s transition. This is how
 * --duration-fast, --radius-container, --danger-border and --alpha-paper-04
 * went unnoticed.
 */

function cssVariablesDefined(): array
{
    $defined = [];

    $css = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('css')));

    foreach ($css as $file) {
        if ($file->isFile() && $file->getExtension() === 'css') {
            preg_match_all('/(--[a-zA-Z0-9-]+)\s*:/', (string) file_get_contents($file->getPathname()), $matches);
            $defined += array_flip($matches[1]);
        }
    }

    // Set at runtime from components, e.g. style={{ '--progress': '40%' }}.
    foreach (frontendSourceFiles() as $path) {
        preg_match_all('/[\'"](--[a-zA-Z0-9-]+)[\'"]\s*:/', (string) file_get_contents($path), $matches);
        $defined += array_flip($matches[1]);
    }

    return $defined;
}

function frontendSourceFiles(): array
{
    $files = [];
    $js = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));

    foreach ($js as $file) {
        $path = $file->getPathname();

        if ($file->isFile() && preg_match('/\.(tsx?|jsx?)$/', $path) && ! str_contains($path, '.test.')) {
            $files[] = $path;
        }
    }

    return $files;
}

it('uses no CSS variable that is never defined', function () {
    $defined = cssVariablesDefined();

    // Tailwind and Radix define these themselves.
    $external = ['--tw-', '--radix-', '--spacing'];

    $missing = [];

    foreach (frontendSourceFiles() as $path) {
        preg_match_all('/var\((--[a-zA-Z0-9-]+)/', (string) file_get_contents($path), $matches);

        foreach ($matches[1] as $variable) {
            if (isset($defined[$variable]) || str_starts_with($variable, $external[0]) || str_starts_with($variable, $external[1]) || $variable === $external[2]) {
                continue;
            }

            $missing[$variable][] = str_replace(resource_path('js').DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($missing)->toBe([]);
});

it('gives the motion utilities real values', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    foreach (['instant', 'fast', 'base', 'slow', 'sheet'] as $speed) {
        expect($css)->toContain("--transition-duration-{$speed}: var(--dur-{$speed});");
    }

    foreach (['standard', 'enter', 'exit', 'spring'] as $curve) {
        expect($css)->toContain("--ease-{$curve}: var(--ease-{$curve});");
    }
});
