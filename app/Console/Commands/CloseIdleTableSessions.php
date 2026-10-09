<?php

namespace App\Console\Commands;

use App\Models\TableSession;
use Illuminate\Console\Command;

class CloseIdleTableSessions extends Command
{
    protected $signature = 'qresto:close-idle-table-sessions';

    protected $description = 'Close table sessions that have been idle past the configured limit';

    public function handle(): int
    {
        $idleMinutes = (int) config(
            'qresto.table_session_idle_minutes',
            30,
        );

        $cutoff = now()->subMinutes($idleMinutes);

        $closed = TableSession::withoutGlobalScopes()
            ->whereNull('closed_at')
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where(
                        'last_seen_at',
                        '<',
                        $cutoff,
                    )
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query
                            ->whereNull('last_seen_at')
                            ->where(
                                'opened_at',
                                '<',
                                $cutoff,
                            );
                    });
            })
            ->update([
                'closed_at' => now(),
            ]);

        $this->info(
            "Closed {$closed} idle table session(s).",
        );

        return self::SUCCESS;
    }
}
