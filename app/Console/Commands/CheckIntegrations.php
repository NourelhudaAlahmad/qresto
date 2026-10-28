<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationMonitor;
use Illuminate\Console\Command;

/**
 * Catches the silent failure: a device that stops posting never triggers
 * the heartbeat endpoint, so this notices its heartbeat going stale and
 * tells managers once (#30). Scheduled every minute.
 */
class CheckIntegrations extends Command
{
    protected $signature = 'qresto:check-integrations';

    protected $description = 'Notice integrations whose heartbeat went stale and alert managers once';

    public function handle(IntegrationMonitor $monitor): int
    {
        $this->info("Checked {$monitor->checkAll()} integrations.");

        return self::SUCCESS;
    }
}
