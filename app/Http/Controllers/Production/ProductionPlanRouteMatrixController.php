<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductionPlanRouteMatrixController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.update');
    }

    public function edit(Request $request, $project, $production_plan)
    {
        $projectId = (int) $project;
        $planId = (int) $production_plan;

        $hasMachineId = Schema::hasColumn('production_plan_item_activities', 'machine_id');

        $plan = DB::table('production_plans')
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->first();

        if (! $plan) {
            abort(404);
        }

        if (($plan->status ?? '') !== 'draft') {
            return redirect(url('/projects/'.$projectId.'/production-plans/'.$planId))
                ->with('error', 'Route Matrix can be edited only when Production Plan is in DRAFT status.');
        }

        $items = DB::table('production_plan_items')
            ->where('production_plan_id', $planId)
            ->orderByRaw("FIELD(item_type,'assembly','part')")
            ->orderBy('assembly_mark')
            ->orderBy('sequence_no')
            ->orderBy('id')
            ->get([
                'id',
                'item_type',
                'assembly_mark',
                'assembly_type',
                'item_code',
                'description',
            ]);

        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('default_sequence')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'applies_to',
                'default_sequence',
                'billing_uom_id',
                'requires_qc',
                'requires_machine',
                'is_fitupp',
            ]);

        $contractors = DB::table('parties')
            ->where('is_contractor', 1)
            ->orderBy('name')
            ->get(['id','code','name']);

        $workers = DB::table('users')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name']);

        $uoms = DB::table('uoms')
            ->orderBy('code')
            ->get(['id','code','name']);

        $machines = collect();
        if ($hasMachineId) {
            $machines = DB::table('machines')
                ->where('is_active', 1)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->get(['id','code','name','short_name']);
        }

        // Ensure every (Item x Applicable Activity) row exists.
        // NOTE: We use insertOrIgnore with unique constraint uq_ppia_item_activity.
        $now = now();
        $buffer = [];

        foreach ($items as $it) {
            $itemType = (string) ($it->item_type ?? 'part');

            foreach ($activities as $a) {
                $appliesTo = (string) ($a->applies_to ?? 'both');

                if (! ($appliesTo === 'both' || $appliesTo === $itemType)) {
                    continue;
                }

                $row = [
                    'production_plan_item_id' => (int) $it->id,
                    'production_activity_id' => (int) $a->id,
                    'sequence_no' => (int) ($a->default_sequence ?? 0),
                    'is_enabled' => 1,

                    'contractor_party_id' => null,
                    'worker_user_id' => null,
                    'rate' => 0,
                    'rate_uom_id' => ($a->billing_uom_id ?? null),
                    'planned_date' => null,

                    'status' => 'pending',
                    'qc_status' => 'na',
                    'qc_by' => null,
                    'qc_at' => null,
                    'qc_remarks' => null,

                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($hasMachineId) {
                    $row['machine_id'] = null;
                }

                $buffer[] = $row;

                if (count($buffer) >= 1000) {
                    DB::table('production_plan_item_activities')->insertOrIgnore($buffer);
                    $buffer = [];
                }
            }
        }

        if (! empty($buffer)) {
            DB::table('production_plan_item_activities')->insertOrIgnore($buffer);
        }

        $piaSelect = [
            'pia.id as pia_id',
            'pia.production_plan_item_id as item_id',
            'pia.production_activity_id as activity_id',
            'pia.is_enabled',
            'pia.contractor_party_id',
            'pia.worker_user_id',
            'pia.rate',
            'pia.rate_uom_id',
            'pia.planned_date',
        ];

        if ($hasMachineId) {
            $piaSelect[] = 'pia.machine_id';
        } else {
            $piaSelect[] = DB::raw('NULL as machine_id');
        }

        $piaRows = DB::table('production_plan_item_activities as pia')
            ->join('production_plan_items as i', 'i.id', '=', 'pia.production_plan_item_id')
            ->where('i.production_plan_id', $planId)
            ->get($piaSelect);

        $cellMap = [];
        foreach ($piaRows as $r) {
            $itemId = (int) ($r->item_id ?? 0);
            $actId = (int) ($r->activity_id ?? 0);
            if ($itemId <= 0 || $actId <= 0) continue;

            $cellMap[$itemId][$actId] = [
                // Keep keys aligned with Blade template expectations.
                // This is the production_plan_item_activities.id value.
                'id' => (int) $r->pia_id,
                'is_enabled' => ((int) ($r->is_enabled ?? 0) === 1),

                'contractor_party_id' => $r->contractor_party_id ?? null,
                'worker_user_id' => $r->worker_user_id ?? null,
                'machine_id' => $r->machine_id ?? null,
                'rate' => $r->rate ?? null,
                'rate_uom_id' => $r->rate_uom_id ?? null,
                'planned_date' => $r->planned_date ?? null,
            ];
        }

        return view('production.plans.route_matrix', [
            'projectId' => $projectId,
            'plan' => $plan,
            'items' => $items,
            'activities' => $activities,
            'cellMap' => $cellMap,

            'contractors' => $contractors,
            'workers' => $workers,
            'machines' => $machines,
            'uoms' => $uoms,
            'hasMachineId' => $hasMachineId,
        ]);
    }

    /**
     * Bulk assign contractor/worker/machine/rate/etc. to selected enabled cells.
     *
     * POST /projects/{project}/production-plans/{production_plan}/route-matrix/assign
     */
    public function bulkAssign(Request $request, $project, $production_plan)
    {
        $projectId = (int) $project;
        $planId = (int) $production_plan;

        $hasMachineId = Schema::hasColumn('production_plan_item_activities', 'machine_id');

        $plan = DB::table('production_plans')
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->first();

        if (! $plan) {
            abort(404);
        }

        if (($plan->status ?? '') !== 'draft') {
            return redirect(url('/projects/'.$projectId.'/production-plans/'.$planId))
                ->with('error', 'Route Matrix can be updated only when Production Plan is in DRAFT status.');
        }

        $data = $request->validate([
            'selected_ids_json' => ['required', 'string'],
            'contractor_party_id' => ['nullable'],
            'worker_user_id' => ['nullable'],
            'machine_id' => ['nullable'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'rate_uom_id' => ['nullable'],
            'planned_date' => ['nullable', 'date'],
            'clear_planned_date' => ['nullable'],
        ]);

        $ids = json_decode((string) $data['selected_ids_json'], true);
        if (! is_array($ids)) {
            throw ValidationException::withMessages([
                'selected_ids_json' => 'Invalid selection payload (expected JSON array).',
            ]);
        }

        $ids = array_values(array_unique(array_map(function ($v) {
            return (int) $v;
        }, $ids)));

        $ids = array_values(array_filter($ids, fn ($v) => $v > 0));

        if (empty($ids)) {
            throw ValidationException::withMessages([
                'selected_ids_json' => 'Please select at least one cell to apply settings.',
            ]);
        }

        // Validate that selected IDs belong to this plan.
        $validIds = DB::table('production_plan_item_activities as pia')
            ->join('production_plan_items as i', 'i.id', '=', 'pia.production_plan_item_id')
            ->where('i.production_plan_id', $planId)
            ->whereIn('pia.id', $ids)
            ->pluck('pia.id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($validIds)) {
            throw ValidationException::withMessages([
                'selected_ids_json' => 'Selected cells do not belong to this plan.',
            ]);
        }

        // Build update array (only fields not set to "keep")
        $update = [
            'updated_at' => now(),
            // Because the matrix selection is based on checked cells, ensure selected
            // cells are enabled when applying settings.
            'is_enabled' => 1,
        ];

        // Selects use special value __KEEP__ to mean "do not change".
        $contractorVal = $data['contractor_party_id'] ?? '__KEEP__';
        if ($contractorVal !== '__KEEP__') {
            $update['contractor_party_id'] = ($contractorVal === '' || $contractorVal === null) ? null : (int) $contractorVal;
        }

        $workerVal = $data['worker_user_id'] ?? '__KEEP__';
        if ($workerVal !== '__KEEP__') {
            $update['worker_user_id'] = ($workerVal === '' || $workerVal === null) ? null : (int) $workerVal;
        }

        if ($hasMachineId) {
            $machineVal = $data['machine_id'] ?? '__KEEP__';
            if ($machineVal !== '__KEEP__') {
                $update['machine_id'] = ($machineVal === '' || $machineVal === null) ? null : (int) $machineVal;
            }
        }

        // Rate: empty means keep as-is; numeric means set.
        if (array_key_exists('rate', $data) && $request->filled('rate')) {
            $update['rate'] = (float) $data['rate'];
        }

        $rateUomVal = $data['rate_uom_id'] ?? '__KEEP__';
        if ($rateUomVal !== '__KEEP__') {
            $update['rate_uom_id'] = ($rateUomVal === '' || $rateUomVal === null) ? null : (int) $rateUomVal;
        }

        if (! empty($data['clear_planned_date'])) {
            $update['planned_date'] = null;
        } elseif ($request->filled('planned_date')) {
            $update['planned_date'] = $data['planned_date'];
        }

        if (count($update) <= 1) {
            return back()->with('error', 'Nothing to apply. Choose at least one field (Contractor/Worker/Machine/Rate/UOM/Planned Date).');
        }

        DB::table('production_plan_item_activities')
            ->whereIn('id', $validIds)
            ->update($update);

        return redirect(url('/projects/'.$projectId.'/production-plans/'.$planId.'/route-matrix'))
            ->with('success', 'Bulk assignments applied to selected route cells.');
    }

    public function update(Request $request, $project, $production_plan)
    {
        $projectId = (int) $project;
        $planId = (int) $production_plan;

        $plan = DB::table('production_plans')
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->first();

        if (! $plan) {
            abort(404);
        }

        if (($plan->status ?? '') !== 'draft') {
            return redirect(url('/projects/'.$projectId.'/production-plans/'.$planId))
                ->with('error', 'Route Matrix can be updated only when Production Plan is in DRAFT status.');
        }

        $data = $request->validate([
            'payload_json' => ['required', 'string'],
        ]);

        $payload = json_decode((string) $data['payload_json'], true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'payload_json' => 'Invalid payload (expected JSON array).',
            ]);
        }

        // Build {pia_id => is_enabled}
        $enabledById = [];
        $ids = [];

        foreach ($payload as $row) {
            if (! is_array($row)) continue;
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) continue;

            $isEnabled = (int) (! empty($row['is_enabled']));
            $enabledById[$id] = $isEnabled;
            $ids[] = $id;
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            throw ValidationException::withMessages([
                'payload_json' => 'No route rows were found in the submitted data.',
            ]);
        }

        // Validate that incoming PIA ids belong to this plan and build mapping to item_id
        $piaMap = DB::table('production_plan_item_activities as pia')
            ->join('production_plan_items as i', 'i.id', '=', 'pia.production_plan_item_id')
            ->where('i.production_plan_id', $planId)
            ->whereIn('pia.id', $ids)
            ->get([
                'pia.id as pia_id',
                'pia.production_plan_item_id as item_id',
            ]);

        if ($piaMap->isEmpty()) {
            throw ValidationException::withMessages([
                'payload_json' => 'Submitted route rows do not belong to this plan.',
            ]);
        }

        $validIds = [];
        $enabledCountByItem = [];

        foreach ($piaMap as $r) {
            $piaId = (int) ($r->pia_id ?? 0);
            $itemId = (int) ($r->item_id ?? 0);
            if ($piaId <= 0 || $itemId <= 0) continue;

            $validIds[] = $piaId;
            $isEnabled = (int) ($enabledById[$piaId] ?? 0);
            $enabledCountByItem[$itemId] = ($enabledCountByItem[$itemId] ?? 0) + $isEnabled;
        }

        // Safety: do not allow saving a route with zero enabled activities per item.
        foreach ($enabledCountByItem as $itemId => $cnt) {
            if ((int) $cnt <= 0) {
                throw ValidationException::withMessages([
                    'payload_json' => 'At least one activity must be enabled for every item. (Item ID: '.$itemId.')',
                ]);
            }
        }

        $validIds = array_values(array_unique($validIds));
        $now = now();

        $enableIds = [];
        $disableIds = [];
        foreach ($validIds as $id) {
            if ((int) ($enabledById[$id] ?? 0) === 1) {
                $enableIds[] = (int) $id;
            } else {
                $disableIds[] = (int) $id;
            }
        }

        DB::transaction(function () use ($enableIds, $disableIds, $now) {
            if (! empty($enableIds)) {
                DB::table('production_plan_item_activities')
                    ->whereIn('id', $enableIds)
                    ->update([
                        'is_enabled' => 1,
                        'updated_at' => $now,
                    ]);
            }

            if (! empty($disableIds)) {
                DB::table('production_plan_item_activities')
                    ->whereIn('id', $disableIds)
                    ->update([
                        'is_enabled' => 0,
                        'updated_at' => $now,
                    ]);
            }
        });

        return redirect(url('/projects/'.$projectId.'/production-plans/'.$planId.'/route-matrix'))
            ->with('success', 'Route Matrix updated successfully.');
    }
}
