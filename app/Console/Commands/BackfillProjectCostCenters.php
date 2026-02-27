<?php

namespace App\Console\Commands;

use App\Models\Accounting\CostCenter;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherLine;
use App\Models\Project;
use App\Services\Accounting\ProjectCostCenterResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class BackfillProjectCostCenters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan accounting:backfill-project-cost-centers
     *   php artisan accounting:backfill-project-cost-centers --dry-run
     *   php artisan accounting:backfill-project-cost-centers --only-posted
     */
    protected $signature = 'accounting:backfill-project-cost-centers
        {--company= : Company ID (defaults to accounting.default_company_id)}
        {--dry-run : Do not write changes (only show counts)}
        {--only-posted : Update only posted vouchers (skip drafts)}
        {--projects-only : Only ensure project cost centers, skip vouchers}
        {--vouchers-only : Only update vouchers/voucher_lines, skip project cost centers}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill Project = Cost Center data: ensure cost_centers exist for projects and update voucher/voucher_lines cost_center_id for project-tagged vouchers';

    public function handle(): int
    {
        $companyId = (int) ($this->option('company') ?: Config::get('accounting.default_company_id', 1));
        $dryRun = (bool) $this->option('dry-run');
        $onlyPosted = (bool) $this->option('only-posted');
        $projectsOnly = (bool) $this->option('projects-only');
        $vouchersOnly = (bool) $this->option('vouchers-only');

        if ($projectsOnly && $vouchersOnly) {
            $this->error('Invalid options: --projects-only and --vouchers-only cannot be used together.');
            return 1;
        }

        if (! Schema::hasTable('projects')) {
            $this->error('projects table not found.');
            return 1;
        }

        if (! Schema::hasTable('cost_centers')) {
            $this->error('cost_centers table not found. Please run migrations first.');
            return 1;
        }

        if (! Schema::hasTable('vouchers') || ! Schema::hasTable('voucher_lines')) {
            $this->error('vouchers/voucher_lines tables not found.');
            return 1;
        }

        $this->info('Backfilling Project Cost Centers...');
        $this->line('Company ID: ' . $companyId);
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN (no changes will be written)' : 'WRITE'));
        $this->line('');

        $createdCC = 0;
        $existingCC = 0;

        $updatedVouchers = 0;
        $updatedLines = 0;

        /*
         |--------------------------------------------------------------------------
         | 1) Ensure cost center exists for every project
         |--------------------------------------------------------------------------
         */
        if (! $vouchersOnly) {
            $this->info('1) Ensuring cost centers for projects...');

            Project::query()
                ->select(['id', 'code', 'name'])
                ->orderBy('id')
                ->chunk(200, function ($projects) use ($companyId, $dryRun, &$createdCC, &$existingCC) {
                    foreach ($projects as $project) {
                        $exists = CostCenter::query()
                            ->where('company_id', $companyId)
                            ->where('project_id', $project->id)
                            ->exists();

                        if ($exists) {
                            $existingCC++;
                            // Also keep name/code aligned (idempotent)
                            if (! $dryRun) {
                                ProjectCostCenterResolver::resolveIdForProject($companyId, $project);
                            }
                            continue;
                        }

                        $createdCC++;

                        if (! $dryRun) {
                            ProjectCostCenterResolver::resolveIdForProject($companyId, $project);
                        }
                    }
                });

            $this->line('• Projects with existing CC: ' . $existingCC);
            $this->line('• Projects missing CC (created): ' . $createdCC);
            $this->line('');
        }

        /*
         |--------------------------------------------------------------------------
         | 2) Update vouchers and voucher_lines where project_id is set
         |--------------------------------------------------------------------------
         */
        if (! $projectsOnly) {
            $this->info('2) Updating vouchers & voucher_lines cost_center_id for project-tagged vouchers...');

            $q = Voucher::query()
                ->where('company_id', $companyId)
                ->whereNotNull('project_id');

            if ($onlyPosted) {
                $q->where('status', 'posted');
            }

            $q->orderBy('id')
                ->chunkById(200, function ($vouchers) use ($companyId, $dryRun, &$updatedVouchers, &$updatedLines) {
                    foreach ($vouchers as $voucher) {
                        $pid = (int) $voucher->project_id;
                        if ($pid <= 0) {
                            continue;
                        }

                        $ccId = ProjectCostCenterResolver::resolveId($companyId, $pid);

                        // 2a) Voucher header
                        if (empty($voucher->cost_center_id)) {
                            $updatedVouchers++;
                            if (! $dryRun) {
                                $voucher->cost_center_id = $ccId;
                                $voucher->save();
                            }
                        }

                        // 2b) Voucher lines (only fill missing cost_center_id)
                        $linesToUpdate = VoucherLine::query()
                            ->where('voucher_id', $voucher->id)
                            ->whereNull('cost_center_id')
                            ->count();

                        if ($linesToUpdate > 0) {
                            $updatedLines += $linesToUpdate;

                            if (! $dryRun) {
                                VoucherLine::query()
                                    ->where('voucher_id', $voucher->id)
                                    ->whereNull('cost_center_id')
                                    ->update(['cost_center_id' => $ccId]);
                            }
                        }
                    }
                });

            $this->line('• Voucher headers updated (filled missing cost_center_id): ' . $updatedVouchers);
            $this->line('• Voucher lines updated (filled missing cost_center_id): ' . $updatedLines);
            $this->line('');
        }

        $this->info('Done.');

        return 0;
    }
}
