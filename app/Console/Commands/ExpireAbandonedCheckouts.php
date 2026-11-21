<?php

namespace App\Console\Commands;

use App\Actions\Orders\ResolveAbandonedCheckout;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A guest who leaves at the 3-D Secure step and never checks out again
 * leaves an order no one finishes. After a while it is cancelled at the
 * gateway and discarded, as the guest's own next checkout would; one that
 * turns out paid meanwhile is finished instead.
 */
class ExpireAbandonedCheckouts extends Command
{
    protected $signature = 'qresto:expire-abandoned-checkouts';

    protected $description = 'Cancel and discard card orders left waiting on 3-D Secure past the configured limit';

    public function handle(ResolveAbandonedCheckout $abandoned): int
    {
        $minutes = max(1, (int) config('qresto.abandoned_checkout_minutes', 30));

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('is_paid', false)
            ->where('placed_at', '<', now()->subMinutes($minutes))
            ->whereHas('payments', fn ($payments) => $payments->where('status', PaymentStatus::REQUIRES_ACTION->value))
            ->get();

        $outcomes = [
            ResolveAbandonedCheckout::PAID => 0,
            ResolveAbandonedCheckout::DISCARDED => 0,
            ResolveAbandonedCheckout::UNDECIDED => 0,
        ];

        foreach ($orders as $order) {
            try {
                $outcome = $abandoned->resolve($order);
            } catch (Throwable $exception) {
                // One order the gateway cannot settle must not stop the rest.
                Log::error('Could not resolve an abandoned checkout', [
                    'order' => $order->id,
                    'exception' => $exception->getMessage(),
                ]);

                $outcome = ResolveAbandonedCheckout::UNDECIDED;
            }

            $outcomes[$outcome]++;
        }

        $this->info(sprintf(
            'Abandoned checkouts: %d discarded, %d found paid, %d left for the next run.',
            $outcomes[ResolveAbandonedCheckout::DISCARDED],
            $outcomes[ResolveAbandonedCheckout::PAID],
            $outcomes[ResolveAbandonedCheckout::UNDECIDED],
        ));

        return self::SUCCESS;
    }
}
