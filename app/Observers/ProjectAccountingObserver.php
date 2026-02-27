<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Accounting\CostCenter;
use App\Models\Project;
use App\Services\Accounting\ProjectWipToCogsDraftService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class ProjectAccountingObserver
{
    /**
     * When a project is created, automatically create a matching Cost Center.
     *
     * Why?
     * - Cost centers are used for project-wise tagging on voucher lines.
     * - This avoids empty Cost Center dropdowns and prevents manual setup.
     */
    public function created(Project $project): void
    {
        $this->ensureCostCenterForProject($project, 'created');
    }

    /**
     * When a project status changes to COMPLETED, auto-generate a DRAFT WIP→COGS voucher.
     *
     * This is intentionally DRAFT (not auto-posted) so Accounts can review and post.
     */
    public function updated(Project $project): void
    {
        // Keep cost center name aligned if project name/code changes
        if ($project->wasChanged(['name', 'code'])) {
            $this->ensureCostCenterForProject($project, 'updated');
        }

        $enabled = (bool) Config::get('accounting.project_close.auto_generate_wip_to_cogs_on_completion', false);
        if (! $enabled) {
            return;
        }

        // Only when status changes
        if (! $project->wasChanged('status')) {
            return;
        }

        $completionStatus = (string) Config::get('accounting.project_close.completion_status_value', 'completed');
        if ((string) $project->status !== $completionStatus) {
            return;
        }

        // Avoid duplicates
        if (Schema::hasColumn('projects', 'wip_to_cogs_voucher_id') && ! empty($project->wip_to_cogs_voucher_id)) {
            return;
        }

        try {
            /** @var \App\Models\Accounting\Voucher|null $voucher */
            $voucher = app(ProjectWipToCogsDraftService::class)->createDraftForProject($project);

            if (! $voucher) {
                return; // Nothing to close
            }

            // Link the draft voucher to project for visibility + preventing duplicates
            if (Schema::hasColumn('projects', 'wip_to_cogs_voucher_id')) {
                $project->wip_to_cogs_voucher_id = $voucher->id;
                $project->saveQuietly();
            }
        } catch (\Throwable $e) {
            report($e);

            ActivityLog::logCustom(
                'project_wip_to_cogs_draft_failed',
                'Failed to create draft WIP→COGS voucher for project ' . ($project->code ?: ('#' . $project->id)) . ': ' . $e->getMessage(),
                $project
            );
        }
    }

    protected function ensureCostCenterForProject(Project $project, string $event): void
    {
        // Guardrail: if cost centers are not migrated yet, do nothing.
        if (! Schema::hasTable('cost_centers')) {
            return;
        }

        $companyId = (int) Config::get('accounting.default_company_id', 1);

        try {
            $cc = CostCenter::query()
                ->where('company_id', $companyId)
                ->where('project_id', $project->id)
                ->first();

            $desiredName = trim(($project->code ? ($project->code . ' - ') : '') . ($project->name ?: ''));
            if ($desiredName === '') {
                $desiredName = 'Project #' . $project->id;
            }

            $preferredCode = trim((string) ($project->code ?: ''));
            if ($preferredCode === '') {
                $preferredCode = 'PRJ-' . str_pad((string) $project->id, 6, '0', STR_PAD_LEFT);
            }

            if ($cc) {
                $dirty = false;

                if ($cc->name !== $desiredName) {
                    $cc->name = $desiredName;
                    $dirty = true;
                }

                // Only fill code if blank (do not silently change existing codes)
                if ((empty($cc->code) || trim((string) $cc->code) === '') && ! empty($preferredCode)) {
                    $cc->code = $this->uniqueCostCenterCode($companyId, $preferredCode, $project->id);
                    $dirty = true;
                }

                if ($dirty) {
                    $cc->save();
                }

                return;
            }

            // Create new cost center
            $code = $this->uniqueCostCenterCode($companyId, $preferredCode, $project->id);

            CostCenter::create([
                'company_id' => $companyId,
                'name'       => $desiredName,
                'code'       => $code,
                'project_id' => $project->id,
                'parent_id'  => null,
                'is_active'  => true,
            ]);
        } catch (\Throwable $e) {
            report($e);

            ActivityLog::logCustom(
                'project_cost_center_sync_failed',
                'Failed to sync Cost Center for project ' . ($project->code ?: ('#' . $project->id)) . ' on ' . $event . ': ' . $e->getMessage(),
                $project
            );
        }
    }

    protected function uniqueCostCenterCode(int $companyId, string $preferred, int $projectId): string
    {
        $preferred = trim($preferred);

        if ($preferred === '') {
            $preferred = 'PRJ-' . str_pad((string) $projectId, 6, '0', STR_PAD_LEFT);
        }

        // If preferred is free, use it
        $exists = CostCenter::query()
            ->where('company_id', $companyId)
            ->where('code', $preferred)
            ->exists();

        if (! $exists) {
            return $preferred;
        }

        // Fallback: append project id
        $fallback = $preferred . '-P' . $projectId;
        $fallbackExists = CostCenter::query()
            ->where('company_id', $companyId)
            ->where('code', $fallback)
            ->exists();

        if (! $fallbackExists) {
            return $fallback;
        }

        // Final fallback: deterministic by project id
        return 'PRJ-' . str_pad((string) $projectId, 6, '0', STR_PAD_LEFT);
    }
}
