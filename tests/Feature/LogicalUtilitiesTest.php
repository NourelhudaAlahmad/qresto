<?php

namespace Tests\Feature;

use Tests\TestCase;

class LogicalUtilitiesTest extends TestCase
{
    public function test_qresto_components_use_logical_direction_utilities(): void
    {
        $directories = [
            resource_path('js/components/qresto'),
            resource_path('js/pages'),
        ];

        $forbiddenPattern = '/\b(?:ml|mr|pl|pr|left|right)-/';

        foreach ($directories as $directory) {
            $files = glob($directory.'/**/*.{tsx,ts}', GLOB_BRACE) ?: [];

            foreach ($files as $file) {
                $contents = file_get_contents($file);

                $this->assertIsString($contents);
                $this->assertDoesNotMatchRegularExpression(
                    $forbiddenPattern,
                    $contents,
                    "Forbidden physical-direction utility found in {$file}",
                );
            }
        }
    }
}
