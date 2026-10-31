<?php

/*
 * #40: Arabic is drawn in a face chosen with the brand faces, never in
 * whatever system-ui happens to be, and money keeps its mono shape in both
 * directions.
 */

function fontStack(string $token): array
{
    $css = file_get_contents(resource_path('css/tokens/fonts.css'));

    preg_match('/'.preg_quote($token, '/').':\s*([^;]+);/', $css, $match);

    return array_map(
        fn (string $face): string => trim($face, " \n\t'\""),
        explode(',', $match[1] ?? ''),
    );
}

it('requests Arabic-capable faces in the rendered head, with swap and preconnect', function () {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="preconnect" href="https://fonts.googleapis.com">')
        ->toContain('href="https://fonts.gstatic.com"')
        ->toMatch('/fonts\.googleapis\.com\/css2\?[^"]*family=Noto\+Sans\+Arabic/')
        ->toMatch('/fonts\.googleapis\.com\/css2\?[^"]*family=Noto\+Kufi\+Arabic/')
        ->toMatch('/fonts\.googleapis\.com\/css2\?[^"]*display=swap/');
});

it('puts the Arabic face after the Latin faces, so Latin text is unaffected', function (string $token, array $latin, string $arabic) {
    $stack = fontStack($token);

    expect($stack)->toContain($arabic);

    foreach ($latin as $face) {
        expect(array_search($face, $stack, true))->toBeLessThan(array_search($arabic, $stack, true), "{$face} must come before {$arabic} in {$token}");
    }
})->with([
    'UI' => ['--font-ui', ['Outfit', 'Familjen Grotesk'], 'Noto Sans Arabic'],
    'display' => ['--font-display', ['Familjen Grotesk'], 'Noto Kufi Arabic'],
]);

it('keeps the mono stack free of any Arabic face', function () {
    $mono = fontStack('--font-mono');

    expect($mono[0])->toBe('JetBrains Mono')
        ->and(implode(',', $mono))->not->toContain('Arabic');
});

it('keeps money mono, tabular and left-to-right under dir="rtl"', function () {
    $css = implode("\n", array_map(
        fn (string $file): string => file_get_contents(resource_path("css/{$file}")),
        ['app.css', 'tokens/base.css'],
    ));

    // Both ways of marking a number set the mono face and LTR.
    expect($css)->toMatch('/@utility qr-numeric\s*\{[^}]*font-family:\s*var\(--font-mono\)[^}]*direction:\s*ltr/s')
        ->and($css)->toMatch('/\[data-numeric\]\s*\{[^}]*font-family:\s*var\(--font-mono\)[^}]*direction:\s*ltr/s');

    // …and nothing under RTL swaps the face for numbers.
    preg_match_all('/([^{}]*(?:\[dir=["\']?rtl|:dir\(rtl\))[^{}]*)\{([^}]*)\}/', $css, $rtlRules, PREG_SET_ORDER);

    foreach ($rtlRules as [, $selector, $body]) {
        if (str_contains($selector, 'numeric')) {
            expect($body)->not->toContain('font-family');
        }
    }

    // <Numeric> always carries the marker and the direction itself.
    expect(file_get_contents(resource_path('js/components/numeric.tsx')))
        ->toContain('data-numeric')
        ->toContain('dir="ltr"');
});
