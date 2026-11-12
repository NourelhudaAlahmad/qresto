<?php

namespace App\Console\Commands;

use App\Support\DeploymentCheck;
use Illuminate\Console\Command;

/**
 * The production checklist as a command (#59): run it after editing .env
 * on the server, before switching traffic. Exits non-zero while anything
 * still needs to change, so a deploy script can stop on it.
 */
class CheckDeploy extends Command
{
    protected $signature = 'qresto:check-deploy';

    protected $description = 'List the configuration that must change before this app can run a restaurant';

    public function handle(DeploymentCheck $check): int
    {
        $problems = $check->problems();

        if ($problems === []) {
            $this->info('Ready for production: no development defaults left.');

            return self::SUCCESS;
        }

        $this->error(count($problems).' setting(s) must change before production:');

        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }

        return self::FAILURE;
    }
}
