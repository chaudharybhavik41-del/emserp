<?php

namespace App\Console\Commands;

use App\Models\PwaPushSubscription;
use Illuminate\Console\Command;

class PrunePwaPushSubscriptions extends Command
{
    protected $signature = 'pwa:subscriptions:prune
        {--days= : Inactivity days to disable active subscriptions}
        {--delete-days= : Delete disabled subscriptions older than this many days}
        {--hard-delete : Delete stale active subscriptions instead of disabling them}
        {--dry-run : Show counts without changing data}';

    protected $description = 'Disable stale PWA push subscriptions and purge old disabled entries';

    public function handle(): int
    {
        $days = max(7, (int) ($this->option('days') ?: config('pwa.push.prune_days', 90)));
        $deleteDays = max($days, (int) ($this->option('delete-days') ?: config('pwa.push.delete_disabled_after_days', 180)));
        $hardDelete = (bool) $this->option('hard-delete');
        $dryRun = (bool) $this->option('dry-run');

        $staleCutoff = now()->subDays($days);
        $deleteCutoff = now()->subDays($deleteDays);

        $staleActiveQuery = PwaPushSubscription::query()
            ->whereNull('disabled_at')
            ->where(function ($query) use ($staleCutoff) {
                $query
                    ->where('last_seen_at', '<=', $staleCutoff)
                    ->orWhere(function ($q) use ($staleCutoff) {
                        $q->whereNull('last_seen_at')->where('created_at', '<=', $staleCutoff);
                    });
            });

        $oldDisabledQuery = PwaPushSubscription::query()
            ->whereNotNull('disabled_at')
            ->where('disabled_at', '<=', $deleteCutoff);

        $staleActiveCount = (clone $staleActiveQuery)->count();
        $oldDisabledCount = (clone $oldDisabledQuery)->count();

        $this->info("PWA prune scan: stale_active={$staleActiveCount}, old_disabled={$oldDisabledCount}");
        $this->line("Rules: days={$days}, delete_days={$deleteDays}, hard_delete=" . ($hardDelete ? 'yes' : 'no'));

        if ($dryRun) {
            $this->warn('Dry-run mode: no records changed.');
            return self::SUCCESS;
        }

        $disabled = 0;
        $deleted = 0;

        if ($hardDelete) {
            $deleted += (clone $staleActiveQuery)->delete();
        } else {
            $disabled += (clone $staleActiveQuery)->update([
                'disabled_at' => now(),
                'last_push_status' => 'pruned',
                'last_push_error' => 'Auto-disabled due to subscription inactivity.',
            ]);
        }

        $deleted += (clone $oldDisabledQuery)->delete();

        $this->info("PWA prune done: disabled={$disabled}, deleted={$deleted}");

        return self::SUCCESS;
    }
}
