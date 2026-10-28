<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class LogicalUtilitiesTest extends TestCase
{
    /**
     * Physical-direction utilities break the Arabic (RTL) layout. Use the
     * logical ones instead: ms/me, ps/pe, start/end, text-start/text-end,
     * rounded-s/e, border-s/e.
     *
     * Scans every app file recursively. The previous version used
     * glob('dir/**\/*.tsx'), which PHP does not recurse, so it never looked
     * at a single file directly inside components/qresto.
     */
    public function test_app_code_uses_logical_direction_utilities(): void
    {
        // Generated or vendored code we do not own.
        $skip = ['/components/ui/', '/actions/', '/routes/', '/wayfinder/', '.test.', '/tests/'];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js')),
        );

        $checked = 0;

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('/\.(tsx|ts)$/', $path)) {
                continue;
            }

            foreach ($skip as $fragment) {
                if (str_contains($path, $fragment)) {
                    continue 2;
                }
            }

            $checked++;

            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $number => $line) {
                // Only look inside class strings.
                if (! preg_match_all('/className=|cn\(|class=|[\'"`][^\'"`]*[\'"`]/', $line)) {
                    continue;
                }

                preg_match_all('/[\'"`]([^\'"`]*)[\'"`]/', $line, $strings);

                foreach ($strings[1] as $classes) {
                    foreach (preg_split('/\s+/', $classes) ?: [] as $class) {
                        $utility = preg_replace('/^(?:[\w-]+:|\[[^\]]*\]:)+/', '', $class) ?? $class;

                        $this->assertDoesNotMatchRegularExpression(
                            '/^-?(?:(?:ml|mr|pl|pr|left|right)-|rounded-(?:l|r|tl|tr|bl|br)(?:-|$)|border-(?:l|r)(?:-|$)|text-(?:left|right)$)/',
                            $utility,
                            sprintf('Physical utility "%s" in %s:%d', $class, $path, $number + 1),
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(50, $checked, 'The scan should cover the app, not a handful of files.');
    }
}
