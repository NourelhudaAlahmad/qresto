<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Restaurant;
use App\Payments\Contracts\ProbesHealth;
use App\Payments\PaymentManager;
use App\Services\Integrations\IntegrationMonitor;
use Illuminate\Console\Command;
use Throwable;

/**
 * Asks the payment gateway whether it is up and records the answer as the
 * gateway integration's heartbeat (#30). Scheduled every five minutes.
 */
class ProbeGateway extends Command
{
    protected $signature = 'qresto:probe-gateway';

    protected $description = 'Probe the payment gateway and record the result for system health';

    public function handle(PaymentManager $payments, IntegrationMonitor $monitor): int
    {
        try {
            $gateway = $payments->driver();

            $result = $gateway instanceof ProbesHealth
                ? $gateway->probe()
                : ['status' => 'warning', 'detail' => 'driver cannot be probed'];
        } catch (Throwable $exception) {
            $result = ['status' => 'failing', 'detail' => 'unreachable: '.class_basename($exception)];
        }

        Restaurant::query()->each(function (Restaurant $restaurant) use ($monitor, $result): void {
            $integration = Integration::query()->withoutGlobalScopes()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'kind' => 'gateway'],
                ['name' => 'Payment gateway'],
            );

            $monitor->recordHeartbeat($integration, $result['status'], $result['detail']);
        });

        $this->info("Gateway: {$result['status']} ({$result['detail']})");

        return self::SUCCESS;
    }
}
