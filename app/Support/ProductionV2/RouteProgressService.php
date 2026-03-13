<?php

namespace App\Support\ProductionV2;

use App\Models\Project;
use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyRouteStep;
use App\Models\ProductionV2\ProductionFitup;
use App\Models\ProductionV2\ProductionInspectionEvent;
use App\Models\ProductionV2\ProductionOperationEvent;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionPartRouteStep;
use App\Models\ProductionV2\ProductionQcGateEvent;
use App\Models\ProductionV2\ProductionTrialAssembly;
use App\Models\ProductionV2\ProductionWeldingEvent;
use App\Models\ProductionV2\ProductionWipItem;
use Illuminate\Support\Collection;

class RouteProgressService
{
    public function __construct(private QcGateCatalog $qcGateCatalog)
    {
    }

    public function nextAssemblyStep(ProductionAssembly $assembly): ?array
    {
        $steps = $assembly->routeSteps()->get();
        if ($steps->isEmpty()) {
            return null;
        }

        $baselineQty = max(1.0, (float) ($assembly->planned_qty ?: 1));

        foreach ($steps as $step) {
            $completedQty = $this->completedAssemblyQty($assembly, $step);
            if ($completedQty + 0.0001 < $baselineQty) {
                return [
                    'step' => $step,
                    'required_qty' => $baselineQty,
                    'completed_qty' => $completedQty,
                    'status' => 'operation_pending',
                    'gate_event' => null,
                ];
            }

            $gateEvent = $this->latestAssemblyGateEvent($assembly, $step);
            if ($this->requiresQcGate($step) && ! $this->qcGateCatalog->isPassed($gateEvent?->result)) {
                return [
                    'step' => $step,
                    'required_qty' => $baselineQty,
                    'completed_qty' => $completedQty,
                    'status' => 'qc_gate_pending',
                    'gate_event' => $gateEvent,
                ];
            }
        }

        return null;
    }

    public function nextPartStep(ProductionPartDefinition $part): ?array
    {
        $steps = $part->routeSteps()->get();
        if ($steps->isEmpty()) {
            return null;
        }

        $baselineQty = max(0.0, (float) $part->required_qty);
        if ($baselineQty <= 0) {
            return null;
        }

        foreach ($steps as $step) {
            $completedQty = $this->completedPartQty($part, $step);
            if ($completedQty + 0.0001 < $baselineQty) {
                return [
                    'step' => $step,
                    'required_qty' => $baselineQty,
                    'completed_qty' => $completedQty,
                    'status' => 'operation_pending',
                    'gate_event' => null,
                ];
            }

            $gateEvent = $this->latestPartGateEvent($part, $step);
            if ($this->requiresQcGate($step) && ! $this->qcGateCatalog->isPassed($gateEvent?->result)) {
                return [
                    'step' => $step,
                    'required_qty' => $baselineQty,
                    'completed_qty' => $completedQty,
                    'status' => 'qc_gate_pending',
                    'gate_event' => $gateEvent,
                ];
            }
        }

        return null;
    }

    public function routeAwareActivityOptions(Project $project, OperationCatalog $catalog): Collection
    {
        $catalog->ensureDefaults();

        $partOperationIds = ProductionPartRouteStep::query()
            ->whereHas('partDefinition', fn ($query) => $query->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'obsolete']))
            ->pluck('operation_master_id');

        $assemblyOperationIds = ProductionAssemblyRouteStep::query()
            ->whereHas('assembly', fn ($query) => $query->where('project_id', $project->id)->whereNotIn('status', ['superseded', 'closed']))
            ->pluck('operation_master_id');

        return collect()
            ->merge($partOperationIds)
            ->merge($assemblyOperationIds)
            ->unique()
            ->values();
    }

    private function completedAssemblyQty(ProductionAssembly $assembly, ProductionAssemblyRouteStep $step): float
    {
        return match ($step->operation_code) {
            'fitup' => (float) ProductionFitup::query()->where('project_id', $assembly->project_id)->where('assembly_id', $assembly->id)->count(),
            'welding' => (float) ProductionWeldingEvent::query()->where('project_id', $assembly->project_id)->where('assembly_id', $assembly->id)->count(),
            'inspection' => (float) ProductionInspectionEvent::query()->where('project_id', $assembly->project_id)->where('assembly_id', $assembly->id)->count(),
            'trial_assembly' => (float) ProductionTrialAssembly::query()
                ->where('project_id', $assembly->project_id)
                ->whereHas('assemblies', fn ($query) => $query->where('production_v2_assemblies.id', $assembly->id))
                ->count(),
            default => (float) ProductionOperationEvent::query()
                ->where('project_id', $assembly->project_id)
                ->where('assembly_route_step_id', $step->id)
                ->sum('qty'),
        };
    }

    private function completedPartQty(ProductionPartDefinition $part, ProductionPartRouteStep $step): float
    {
        return match ($step->operation_code) {
            'cutting' => (float) ProductionWipItem::query()
                ->where('project_id', $part->project_id)
                ->where('part_definition_id', $part->id)
                ->sum('qty'),
            default => (float) ProductionOperationEvent::query()
                ->where('project_id', $part->project_id)
                ->where('part_route_step_id', $step->id)
                ->sum('qty'),
        };
    }

    private function requiresQcGate(object $step): bool
    {
        return (bool) ($step->qc_gate_required ?? false)
            && ! blank($step->qc_gate_mode ?? null)
            && ! blank($step->qc_gate_type ?? null);
    }

    private function latestAssemblyGateEvent(ProductionAssembly $assembly, ProductionAssemblyRouteStep $step): ?ProductionQcGateEvent
    {
        return ProductionQcGateEvent::query()
            ->where('project_id', $assembly->project_id)
            ->where('assembly_id', $assembly->id)
            ->where('assembly_route_step_id', $step->id)
            ->latest('gate_date')
            ->latest('id')
            ->first();
    }

    private function latestPartGateEvent(ProductionPartDefinition $part, ProductionPartRouteStep $step): ?ProductionQcGateEvent
    {
        return ProductionQcGateEvent::query()
            ->where('project_id', $part->project_id)
            ->where('part_definition_id', $part->id)
            ->where('part_route_step_id', $step->id)
            ->latest('gate_date')
            ->latest('id')
            ->first();
    }
}
