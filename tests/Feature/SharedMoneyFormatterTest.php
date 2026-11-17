<?php

namespace Tests\Feature;

use App\Support\Money;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SharedMoneyFormatterTest extends TestCase
{
    /**
     * Money goes through @/lib/formatMoney, which follows the page language
     * and settles the missing-currency case once. A page that builds its own
     * Intl.NumberFormat pins a locale and drifts from the rest (#55).
     */
    public function test_only_the_shared_helper_builds_a_number_format(): void
    {
        $allowed = ['/lib/formatMoney.ts'];
        $skip = ['/components/ui/', '/actions/', '/routes/', '/wayfinder/', '.test.', '/tests/'];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js')),
        );

        $checked = 0;
        $offenders = [];

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('/\.(tsx|ts)$/', $path)) {
                continue;
            }

            foreach ([...$allowed, ...$skip] as $fragment) {
                if (str_contains($path, $fragment)) {
                    continue 2;
                }
            }

            $checked++;

            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $number => $line) {
                if (str_contains($line, 'Intl.NumberFormat')) {
                    $offenders[] = str_replace(str_replace('\\', '/', resource_path('js')).'/', '', $path).':'.($number + 1);
                }
            }
        }

        $this->assertGreaterThan(50, $checked, 'The scan should cover the app\'s pages and components.');

        $this->assertSame(
            [],
            $offenders,
            'Format money with formatMinor / formatMoney from @/lib/formatMoney instead of a local Intl.NumberFormat.',
        );
    }

    /**
     * A server-built money string has no symbol, no grouping and no locale:
     * "12.50" on the menu beside "$12.50" on the dish (#68). Pages and
     * components format the amount themselves, and the server no longer
     * sends a string to reach for.
     */
    public function test_no_page_or_component_renders_a_preformatted_money_string(): void
    {
        $offenders = [];
        $checked = 0;

        foreach (['pages', 'components'] as $folder) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(resource_path("js/{$folder}")),
            );

            foreach ($files as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                if (! preg_match('/\.(tsx|ts)$/', $path) || str_contains($path, '.test.') || str_contains($path, '/components/ui/')) {
                    continue;
                }

                $checked++;

                foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $number => $line) {
                    if (preg_match('/\.formatted\b|\b\w+_formatted\b/', $line)) {
                        $offenders[] = str_replace(str_replace('\\', '/', resource_path('js')).'/', '', $path).':'.($number + 1);
                    }
                }
            }
        }

        $this->assertGreaterThan(50, $checked, 'The scan should cover the app\'s pages and components.');

        $this->assertSame(
            [],
            $offenders,
            'Render money with formatMoney / formatMinor from @/lib/formatMoney, not a server-built string.',
        );
    }

    public function test_the_money_json_carries_no_display_string(): void
    {
        $json = Money::fromMinor(123456, 'USD')->jsonSerialize();

        $this->assertSame(['amount', 'currency'], array_keys($json));
    }
}
