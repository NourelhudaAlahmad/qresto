<?php

use Symfony\Component\Finder\Finder;

/*
 * #48: staff screens take their copy from lang/{en,ar}/staff.php through
 * useI18n(), so an Arabic-speaking waiter never gets an English board in an
 * Arabic frame. This guard fails when a staff screen holds English that a
 * person reads: JSX text, a text attribute, or a quoted or templated sentence
 * such as a toast. Same shape as LogicalUtilitiesTest.
 *
 * Screens not migrated yet. Each batch of #48 removes its screens; the list
 * must end empty. An entry whose screen is already clean fails, so it cannot
 * be forgotten here.
 */
$notYetMigrated = [
    'staff/admin/restaurant.tsx',
    'staff/admin/system.tsx',
    'staff/manager/overview.tsx',
];

/**
 * English a person would read, as [line => text].
 *
 * @return array<int, array{0: int, 1: string}>
 */
function staffCopyHits(string $source): array
{
    // Comments are for developers, not for the screen.
    $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);
    $source = (string) preg_replace('#(^|[^:\'"`])//.*$#m', '$1', $source);

    $patterns = [
        // A whole line of JSX text.
        'text' => "/^\s*((?:[A-Z][A-Za-z'’]*|[a-z]+ [A-Za-z'’]+)(?:[ ,.!?:;·…–—&\/%-]+[A-Za-z0-9'’()]+)*[.!?…]?)\s*$/u",
        // Text between tags on one line.
        'inline' => '/>\s*([A-Za-z][^<>{}]*[A-Za-z.!?…])\s*</u',
        // A text attribute written as a plain string.
        'attribute' => '/\b(?:title|label|placeholder|aria-label|description|headline|body|alt|ariaLabel|emptyLabel|content)="([A-Z][^"]*|[a-z]+ [^"]+)"/u',
        // A capitalised phrase in single quotes, such as a toast title.
        'quoted' => "/'([A-Z][a-z]+(?:[ -][A-Za-z’]+)+[.!?…]?)'/u",
        // A template literal holding words.
        'template' => '/`([^`]*\b[A-Za-z][a-z]+ [a-z]{2,}[^`]*)`/u',
    ];

    $hits = [];

    foreach (preg_split('/\R/', $source) ?: [] as $index => $line) {
        if (str_contains($line, 'className') || str_starts_with(trim($line), 'import ') || str_contains($line, " from '")) {
            continue;
        }

        foreach ($patterns as $kind => $pattern) {
            if (! preg_match_all($pattern, $line, $matches)) {
                continue;
            }

            foreach ($matches[1] as $text) {
                if ($kind === 'text' && (
                    ! preg_match('/[a-z]{2,}/', $text)
                    || preg_match('/^\s*(return|else|default|break|continue|true|false|null|undefined)\b/', $line)
                    || preg_match('/\w\.\w|\($|\binstanceof\b|^\s*throw\b/', $line)
                )) {
                    continue;
                }

                if ($kind === 'template') {
                    $words = (string) preg_replace('/\$\{[^}]*\}/', '', $text);

                    if (! preg_match('/\b[A-Za-z][a-z]+ [a-z]{2,}/', $words)) {
                        continue;
                    }
                }

                $hits[] = [$index + 1, $text];
            }
        }
    }

    return $hits;
}

$screens = function (): array {
    $root = resource_path('js/pages');
    $screens = [];

    foreach (Finder::create()->files()->in(["{$root}/staff", "{$root}/manager"])->name('*.tsx')->notName('*.test.tsx') as $file) {
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $screens[$path] = $file->getContents();
    }

    ksort($screens);

    return $screens;
};

it('keeps hard-coded English out of the staff screens', function () use ($notYetMigrated, $screens) {
    $found = [];

    foreach ($screens() as $path => $source) {
        if (in_array($path, $notYetMigrated, true)) {
            continue;
        }

        foreach (staffCopyHits($source) as [$line, $text]) {
            $found[] = "  resources/js/pages/{$path}:{$line}  {$text}";
        }
    }

    expect($found)->toBe(
        [],
        "Staff screens must take their copy from lang/{en,ar}/staff.php through useI18n() (#48):\n".implode("\n", $found),
    );
});

it('keeps the not-yet-migrated list honest', function () use ($notYetMigrated, $screens) {
    $all = $screens();

    foreach ($notYetMigrated as $path) {
        // array_key_exists, not toHaveKey: the dot in ".tsx" reads as nesting there.
        expect(array_key_exists($path, $all))->toBeTrue("{$path} is listed as not migrated but does not exist");

        expect(staffCopyHits($all[$path]))->not->toBeEmpty(
            "{$path} has no hard-coded English left. Remove it from \$notYetMigrated in ".basename(__FILE__).'.',
        );
    }
});

it('catches the shapes it is meant to catch', function (string $snippet) {
    expect(staffCopyHits($snippet))->not->toBeEmpty();
})->with([
    'jsx text' => ["                    Void order\n"],
    'inline jsx' => ['<span>Waiting for payment</span>'],
    'attribute' => ['aria-label="Filter by order status"'],
    'toast' => ["title: 'Could not update order',"],
    'template' => ['`More actions for ${code}`'],
]);

it('leaves code and copy from useI18n alone', function (string $snippet) {
    expect(staffCopyHits($snippet))->toBe([]);
})->with([
    'translated' => ["                    {t.void_order}\n"],
    'member access' => ["                    order.payments\n"],
    'prop name' => ["                    fullWidth\n"],
    'class names' => ['className="text-caption text-text-secondary"'],
    'email example' => ['placeholder="name@restaurant.com"'],
    'comment' => ['// Waiting for payment is shown later'],
    'call' => ["            return Number.isFinite(value)\n"],
]);
