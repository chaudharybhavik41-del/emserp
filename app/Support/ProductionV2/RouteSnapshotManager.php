<?php

namespace App\Support\ProductionV2;

use App\Models\ProductionV2\ProductionAssembly;
use App\Models\ProductionV2\ProductionAssemblyRouteStep;
use App\Models\ProductionV2\ProductionPartDefinition;
use App\Models\ProductionV2\ProductionPartRouteStep;
use App\Models\ProductionV2\ProductionRouteTemplate;

class RouteSnapshotManager
{
    public function syncPart(ProductionPartDefinition $part): void
    {
        ProductionPartRouteStep::query()->where('part_definition_id', $part->id)->delete();

        if (! $part->route_template_id) {
            return;
        }

        $template = ProductionRouteTemplate::query()
            ->with(['steps.operationMaster'])
            ->find($part->route_template_id);

        if (! $template) {
            return;
        }

        foreach ($template->steps as $step) {
            if (! $step->operationMaster) {
                continue;
            }

            ProductionPartRouteStep::query()->create([
                'part_definition_id' => $part->id,
                'route_template_id' => $template->id,
                'route_template_step_id' => $step->id,
                'operation_master_id' => $step->operation_master_id,
                'operation_code' => $step->operationMaster->code,
                'operation_name' => $step->operationMaster->name,
                'entry_mode' => $step->operationMaster->entry_mode,
                'entry_route' => $step->operationMaster->entry_route,
                'sequence_no' => $step->sequence_no,
                'is_mandatory' => $step->is_mandatory,
                'qc_gate_required' => $step->qc_gate_required,
                'qc_gate_mode' => $step->qc_gate_mode,
                'qc_gate_type' => $step->qc_gate_type,
                'qc_gate_remarks' => $step->qc_gate_remarks,
                'remarks' => $step->remarks,
            ]);
        }
    }

    public function syncAssembly(ProductionAssembly $assembly): void
    {
        ProductionAssemblyRouteStep::query()->where('assembly_id', $assembly->id)->delete();

        if (! $assembly->route_template_id) {
            return;
        }

        $template = ProductionRouteTemplate::query()
            ->with(['steps.operationMaster'])
            ->find($assembly->route_template_id);

        if (! $template) {
            return;
        }

        foreach ($template->steps as $step) {
            if (! $step->operationMaster) {
                continue;
            }

            ProductionAssemblyRouteStep::query()->create([
                'assembly_id' => $assembly->id,
                'route_template_id' => $template->id,
                'route_template_step_id' => $step->id,
                'operation_master_id' => $step->operation_master_id,
                'operation_code' => $step->operationMaster->code,
                'operation_name' => $step->operationMaster->name,
                'entry_mode' => $step->operationMaster->entry_mode,
                'entry_route' => $step->operationMaster->entry_route,
                'sequence_no' => $step->sequence_no,
                'is_mandatory' => $step->is_mandatory,
                'qc_gate_required' => $step->qc_gate_required,
                'qc_gate_mode' => $step->qc_gate_mode,
                'qc_gate_type' => $step->qc_gate_type,
                'qc_gate_remarks' => $step->qc_gate_remarks,
                'remarks' => $step->remarks,
            ]);
        }
    }

    public function clonePartSteps(ProductionPartDefinition $source, ProductionPartDefinition $target): void
    {
        ProductionPartRouteStep::query()->where('part_definition_id', $target->id)->delete();

        foreach ($source->routeSteps()->get() as $step) {
            ProductionPartRouteStep::query()->create($step->only([
                'route_template_id',
                'route_template_step_id',
                'operation_master_id',
                'operation_code',
                'operation_name',
                'entry_mode',
                'entry_route',
                'sequence_no',
                'is_mandatory',
                'qc_gate_required',
                'qc_gate_mode',
                'qc_gate_type',
                'qc_gate_remarks',
                'remarks',
            ]) + [
                'part_definition_id' => $target->id,
            ]);
        }
    }

    public function cloneAssemblySteps(ProductionAssembly $source, ProductionAssembly $target): void
    {
        ProductionAssemblyRouteStep::query()->where('assembly_id', $target->id)->delete();

        foreach ($source->routeSteps()->get() as $step) {
            ProductionAssemblyRouteStep::query()->create($step->only([
                'route_template_id',
                'route_template_step_id',
                'operation_master_id',
                'operation_code',
                'operation_name',
                'entry_mode',
                'entry_route',
                'sequence_no',
                'is_mandatory',
                'qc_gate_required',
                'qc_gate_mode',
                'qc_gate_type',
                'qc_gate_remarks',
                'remarks',
            ]) + [
                'assembly_id' => $target->id,
            ]);
        }
    }
}
