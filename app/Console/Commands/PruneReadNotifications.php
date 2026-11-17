<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Deletes staff notifications that were read longer ago than the
 * retention period (#57). Unread ones stay until someone reads them.
 * Scheduled daily.
 */
class PruneReadNotifications extends Command
{
    protected $signature = 'qresto:prune-notifications';

    protected $description = 'Delete notifications that were read longer ago than the retention period';

    public function handle(): int
    {
        $days = (int) config('qresto.retention.read_notifications_days', 90);

        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} read notifications older than {$days} days.");

        return self::SUCCESS;
    }
}
