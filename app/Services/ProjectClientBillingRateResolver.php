<?php

namespace App\Services;

use App\Models\ClientRaBill;
use App\Models\Project;
use App\Models\ProjectClientBillingRate;
use App\Models\ProductionV2\ProductionDispatchLine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ProjectClientBillingRateResolver
{
    public function resolveForDispatchLine(Project|int $project, ProductionDispatchLine $dispatchLine, Carbon|string|null $billDate = null): ?ProjectClientBillingRate
    {
        $projectId = $project instanceof Project ? (int) $project->id : (int) $project;
        $assemblyCode = trim((string) ($dispatchLine->assembly_code_snapshot ?? ''));

        return $this->baseQuery($projectId, $billDate)
            ->where(function (Builder $query) use ($assemblyCode): void {
                if ($assemblyCode !== '') {
                    $query->orWhere(function (Builder $inner) use ($assemblyCode): void {
                        $inner->where('line_type', ProjectClientBillingRate::LINE_TYPE_ASSEMBLY_CODE)
                            ->where('source_key', $assemblyCode);
                    });
                }

                $query->orWhere('line_type', ProjectClientBillingRate::LINE_TYPE_GENERIC);
            })
            ->orderByRaw("
                CASE
                    WHEN line_type = ? AND source_key = ? THEN 0
                    WHEN line_type = ? THEN 1
                    ELSE 9
                END
            ", [
                ProjectClientBillingRate::LINE_TYPE_ASSEMBLY_CODE,
                $assemblyCode,
                ProjectClientBillingRate::LINE_TYPE_GENERIC,
            ])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function resolveForClientBillLine(Project|int $project, array $lineData, ?string $billKind = null, Carbon|string|null $billDate = null): ?ProjectClientBillingRate
    {
        $projectId = $project instanceof Project ? (int) $project->id : (int) $project;
        $boqCode = trim((string) ($lineData['boq_item_code'] ?? ''));

        return $this->baseQuery($projectId, $billDate)
            ->where(function (Builder $query) use ($boqCode, $billKind): void {
                if ($billKind === ClientRaBill::BILL_KIND_SCRAP_SALES) {
                    $query->orWhere('line_type', ProjectClientBillingRate::LINE_TYPE_SCRAP);
                }

                if ($boqCode !== '') {
                    $query->orWhere(function (Builder $inner) use ($boqCode): void {
                        $inner->where('line_type', ProjectClientBillingRate::LINE_TYPE_BOQ_ITEM_CODE)
                            ->where('source_key', $boqCode);
                    });
                }

                $query->orWhere('line_type', ProjectClientBillingRate::LINE_TYPE_GENERIC);
            })
            ->orderByRaw("
                CASE
                    WHEN line_type = ? AND source_key = ? THEN 0
                    WHEN line_type = ? THEN 1
                    WHEN line_type = ? THEN 2
                    ELSE 9
                END
            ", [
                ProjectClientBillingRate::LINE_TYPE_BOQ_ITEM_CODE,
                $boqCode,
                ProjectClientBillingRate::LINE_TYPE_SCRAP,
                ProjectClientBillingRate::LINE_TYPE_GENERIC,
            ])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    protected function baseQuery(int $projectId, Carbon|string|null $billDate): Builder
    {
        $date = $billDate
            ? ($billDate instanceof Carbon ? $billDate->copy()->startOfDay() : Carbon::parse($billDate)->startOfDay())
            : null;

        return ProjectClientBillingRate::query()
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->when($date, function (Builder $query) use ($date): void {
                $query->where(function (Builder $inner) use ($date): void {
                    $inner->whereNull('effective_from')
                        ->orWhereDate('effective_from', '<=', $date->toDateString());
                })->where(function (Builder $inner) use ($date): void {
                    $inner->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $date->toDateString());
                });
            });
    }
}
