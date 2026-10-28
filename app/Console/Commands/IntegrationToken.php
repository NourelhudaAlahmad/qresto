<?php

namespace App\Console\Commands;

use App\Models\Integration;
use Illuminate\Console\Command;

/**
 * Issue (or rotate) a device token for an integration. The plain token is
 * printed once; only its hash is stored, and the old token stops working.
 */
class IntegrationToken extends Command
{
    protected $signature = 'qresto:integration-token {integration : Integration id}';

    protected $description = 'Issue a new heartbeat token for a KDS screen, printer agent or gateway';

    public function handle(): int
    {
        $integration = Integration::query()->withoutGlobalScopes()->find((int) $this->argument('integration'));

        if ($integration === null) {
            $this->error('No integration with that id. List them with: php artisan qresto:integration-token --help');

            return self::FAILURE;
        }

        $token = $integration->issueToken();

        $this->info("New token for {$integration->name} (#{$integration->id}). It is shown once:");
        $this->line($token);
        $this->newLine();
        $this->line('Heartbeat: POST '.route('api.integrations.heartbeat', $integration->id));
        $this->line('Header:    Authorization: Bearer <token>');
        $this->line('Body:      {"status":"ok|warning|failing","detail":"..."}');

        return self::SUCCESS;
    }
}
