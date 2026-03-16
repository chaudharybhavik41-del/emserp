<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneReadNotifications extends Command
{
    protected $signature = 'notifications:prune-read
        {--days= : Delete read notifications older than this many days}
        {--dry-run : Show the number of notifications that would be deleted}';

    protected $description = 'Prune old read notifications from the shared inbox table.';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('erp_notifications.retention.read_days', 90)));
        $cutoff = now()->subDays($days);

        $query = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<=', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} read notification(s) would be deleted for cutoff {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} read notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
