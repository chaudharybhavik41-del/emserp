<?php

namespace App\Services\Accounting;

use App\Models\Accounting\CostCenter;
use App\Models\Project;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve (and auto-create) the Cost Center for a Project.
 *
 * Design assumption (Account module plan v1.2):
 * - Project = Cost Center (one cost center per project for now)
 *
 * This helper is intentionally idempotent:
 * - Returns existing CC if present
 * - Creates CC if missing
 * - Keeps CC name aligned with project code/name (safe update)
 */
class ProjectCostCenterResolver
{
    /**
     * Resolve cost_center_id for a project.
     *
     * @throws \RuntimeException when cost center table is missing
     */
    public static function resolveId(int $companyId, int $projectId): int
    {
        if (! Schema::hasTable('cost_centers')) {
            throw new \RuntimeException('Cost Centers table (cost_centers) not found. Please run migrations.');
        }

        $project = Project::find($projectId);

        // If project is missing (should not happen in normal flows), still create a deterministic CC
        if (! $project) {
            $cc = CostCenter::query()
                ->where('company_id', $companyId)
                ->where('project_id', $projectId)
                ->first();

            if ($cc) {
                return (int) $cc->id;
            }

            $code = self::uniqueCode($companyId, 'PRJ-' . str_pad((string) $projectId, 6, '0', STR_PAD_LEFT), $projectId);

            $cc = CostCenter::create([
                'company_id' => $companyId,
                'name'       => 'Project #' . $projectId,
                'code'       => $code,
                'project_id' => $projectId,
                'parent_id'  => null,
                'is_active'  => true,
            ]);

            return (int) $cc->id;
        }

        return self::resolveIdForProject($companyId, $project);
    }

    /**
     * Resolve cost_center_id for a project model.
     */
    public static function resolveIdForProject(int $companyId, Project $project): int
    {
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
                $cc->code = self::uniqueCode($companyId, $preferredCode, (int) $project->id);
                $dirty = true;
            }

            if ($dirty) {
                $cc->save();
            }

            return (int) $cc->id;
        }

        $code = self::uniqueCode($companyId, $preferredCode, (int) $project->id);

        $cc = CostCenter::create([
            'company_id' => $companyId,
            'name'       => $desiredName,
            'code'       => $code,
            'project_id' => $project->id,
            'parent_id'  => null,
            'is_active'  => true,
        ]);

        return (int) $cc->id;
    }

    protected static function uniqueCode(int $companyId, string $preferred, int $projectId): string
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
