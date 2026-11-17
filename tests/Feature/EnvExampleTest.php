<?php

/*
 * Every setting QResto reads from the environment is listed in
 * .env.example, set or commented out, so nobody has to read config files
 * to find out what can be configured.
 */
it('lists every QResto environment setting in .env.example', function () {
    $example = (string) file_get_contents(base_path('.env.example'));

    foreach (['qresto.php', 'demo.php'] as $file) {
        preg_match_all("/env\('([A-Z0-9_]+)'/", (string) file_get_contents(config_path($file)), $matches);

        foreach ($matches[1] as $key) {
            expect($example)->toMatch("/^#? ?{$key}=/m", "{$key} (config/{$file}) is missing from .env.example");
        }
    }
});
