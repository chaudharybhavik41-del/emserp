<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Party;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductionPlanRouteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.plan.update');
    }

    /**
     * GET /projects/{project}/production-plans/{production_plan}/items/{item}/route
     * Note: Params are not model-bound in your system; accept IDs.
     */
    public function edit($project, $production_plan, $item)
    {
        $projectId = (int) $project;
        $planId    = (int) $production_plan;
        $itemId    = (int) $item;

        $hasMachineId = Schema::hasColumn('production_plan_item_activities', 'machine_id');

        $plan = DB::table('production_plans')
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->first();
        if (! $plan) abort(404);

        $planItem = DB::table('production_plan_items')
            ->where('id', $itemId)
            ->where('production_plan_id', $planId)
            ->first();
        if (! $planItem) abort(404);

        if (($plan->status ?? '') !== 'draft') {
            return redirect(url("/projects/{$projectId}/production-plans/{$planId}"))
                ->with('error', 'Routing cannot be changed after plan approval.');
        }

        $uoms = Uom::orderBy('code')->get();

        $contractors = Party::query()
            ->where('is_contractor', true)
            ->orderBy('name')
            ->get();

        // Workers: Users (active)
        $workers = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id','name','email']);

        $machines = collect();
        if ($hasMachineId) {
            $machines = Machine::query()
                ->where('is_active', true)
                ->where('status', 'active')
                ->orderBy('code')
                ->get(['id','code','name','short_name']);
        }

        // Activities applicable to item type
        $activities = DB::table('production_activities')
            ->where('is_active', 1)
            ->orderBy('default_sequence')
            ->orderBy('name')
            ->get()
            ->filter(function ($a) use ($planItem) {
                $applies  = $a->applies_to ?? 'both';
                $itemType = $planItem->item_type ?? 'part';
                if ($applies === 'both') return true;
                if ($itemType === 'part' && $applies === 'part') return true;
                if ($itemType === 'assembly' && $applies === 'assembly') return true;
                return false;
            })
            ->values();

        // Ensure route rows exist for each applicable activity
        DB::transaction(function () use ($activities, $itemId, $hasMachineId) {
            $now = now();
            foreach ($activities as $act) {
                $exists = DB::table('production_plan_item_activities')
                    ->where('production_plan_item_id', $itemId)
                    ->where('production_activity_id', $act->id)
                    ->exists();

                if (! $exists) {
                    $insert = [
                        'production_plan_item_id' => $itemId,
                        'production_activity_id'  => $act->id,
                        'sequence_no'             => (int) ($act->default_sequence ?? 0),
                        'is_enabled'              => 1,
                        'contractor_party_id'     => null,
                        'worker_user_id'          => null,
                        'rate'                    => 0,
                        'rate_uom_id'             => $act->billing_uom_id ?? null,
                        'planned_date'            => null,
                        'status'                  => 'pending',
                        'qc_status'               => 'na',
                        'qc_by'                   => null,
                        'qc_at'                   => null,
                        'qc_remarks'              => null,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ];

                    if ($hasMachineId) {
                        $insert['machine_id'] = null;
                    }

                    DB::table('production_plan_item_activities')->insert($insert);
                }
            }
        });

        $routeRows = DB::table('production_plan_item_activities as pia')
            ->join('production_activities as a', 'a.id', '=', 'pia.production_activity_id')
            ->where('pia.production_plan_item_id', $itemId)
            ->orderBy('pia.sequence_no')
            ->orderBy('pia.id')
            ->select([
                'pia.*',
                'a.code as activity_code',
                'a.name as activity_name',
                'a.is_fitupp',
                'a.requires_machine',
                'a.requires_qc',
                'a.applies_to',
            ])
            ->get();

        return view('production.plans.route_edit', [
            'projectId'   => $projectId,
            'plan'        => $plan,
            'item'        => $planItem,
            'routeRows'   => $routeRows,
            'uoms'        => $uoms,
            'contractors' => $contractors,
            'workers'     => $workers,
            'machines'    => $machines,
            'hasMachineId'=> $hasMachineId,
        ]);
    }

    /**
     * PUT /projects/{project}/production-plans/{production_plan}/items/{item}/route
     */
    public function update(Request $request, $project, $production_plan, $item)
    {
        $hasMachineId = Schema::hasColumn('production_plan_item_activities', 'machine_id');

        $projectId = (int) $project;
        $planId    = (int) $production_plan;
        $itemId    = (int) $item;

        $plan = DB::table('production_plans')
            ->where('id', $planId)
            ->where('project_id', $projectId)
            ->first();
        if (! $plan) abort(404);

        if (($plan->status ?? '') !== 'draft') {
            return back()->with('error', 'Routing cannot be changed after plan approval.');
        }

        // IMPORTANT FIX:
        // "is_enabled" checkbox must be included in validation rules, otherwise
        // Laravel strips it from $data and every save will set is_enabled = 0.
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.is_enabled' => ['nullable'],
            'rows.*.sequence_no' => ['nullable', 'integer', 'min:0'],
            'rows.*.contractor_party_id' => ['nullable'],
            'rows.*.worker_user_id' => ['nullable'],
            'rows.*.machine_id' => ['nullable'],
            'rows.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rows.*.rate_uom_id' => ['nullable'],
            'rows.*.planned_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $itemId, $hasMachineId) {
            $now = now();
            foreach (($data['rows'] ?? []) as $r) {
                $rowId = (int) $r['id'];

                $pia = DB::table('production_plan_item_activities')
                    ->where('id', $rowId)
                    ->where('production_plan_item_id', $itemId)
                    ->first();

                if (! $pia) continue;

                // Checkbox posts value only when checked; treat any non-empty value as enabled.
                $isEnabled = !empty($r['is_enabled']) ? 1 : 0;

                $contractorId = $r['contractor_party_id'] ?? null;
                $contractorId = ($contractorId === '' || $contractorId === null) ? null : (int) $contractorId;

                $workerId = $r['worker_user_id'] ?? null;
                $workerId = ($workerId === '' || $workerId === null) ? null : (int) $workerId;

                $machineId = null;
                if ($hasMachineId) {
                    $machineId = $r['machine_id'] ?? null;
                    $machineId = ($machineId === '' || $machineId === null) ? null : (int) $machineId;
                }

                $rateUomId = $r['rate_uom_id'] ?? null;
                $rateUomId = ($rateUomId === '' || $rateUomId === null) ? null : (int) $rateUomId;

                $update = [
                        'is_enabled'          => $isEnabled,
                        'sequence_no'         => (int) ($r['sequence_no'] ?? ($pia->sequence_no ?? 0)),
                        'contractor_party_id' => $contractorId,
                        'worker_user_id'      => $workerId,
                        'rate'                => (float) ($r['rate'] ?? 0),
                        'rate_uom_id'         => $rateUomId,
                        'planned_date'        => $r['planned_date'] ?? null,
                        'updated_at'          => $now,
                    ];

                if ($hasMachineId) {
                    $update['machine_id'] = $machineId;
                }

                DB::table('production_plan_item_activities')
                    ->where('id', $rowId)
                    ->update($update);
            }

            // Safety: do not allow saving a route with zero enabled activities.
            $enabledCount = DB::table('production_plan_item_activities')
                ->where('production_plan_item_id', $itemId)
                ->where('is_enabled', 1)
                ->count();

            if ($enabledCount <= 0) {
                throw ValidationException::withMessages([
                    'rows' => 'At least one activity must be enabled for a plan item route.',
                ]);
            }
        });

        return redirect(url("/projects/{$projectId}/production-plans/{$planId}"))
            ->with('success', 'Route updated for selected plan item.');
    }
}
