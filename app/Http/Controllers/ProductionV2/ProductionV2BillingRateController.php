<?php

namespace App\Http\Controllers\ProductionV2;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Project;
use App\Models\ProductionV2\ProductionBillingRate;
use App\Models\ProductionV2\ProductionOperationMaster;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionV2BillingRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.billing.view')->only(['index']);
        $this->middleware('permission:production.billing.generate|production.billing.update')->only(['create', 'store', 'edit', 'update']);
    }

    public function index(Project $project)
    {
        $rows = ProductionBillingRate::query()
            ->where('project_id', $project->id)
            ->with(['contractor', 'operationMaster', 'rateUom'])
            ->orderBy('contractor_party_id')
            ->orderBy('source_type')
            ->orderBy('operation_master_id')
            ->paginate(30);

        return view('production_v2.billing_rates.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        return view('production_v2.billing_rates.form', $this->formData($project, new ProductionBillingRate(['is_active' => true])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request, $project);
        $data['project_id'] = $project->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $rate = ProductionBillingRate::query()->create($data);

        return redirect()
            ->route('projects.production-v2.billing-rates.index', ['project' => $project->id])
            ->with('success', 'Production V2 billing rate created.');
    }

    public function edit(Project $project, ProductionBillingRate $billingRate)
    {
        abort_unless((int) $billingRate->project_id === (int) $project->id, 404);

        return view('production_v2.billing_rates.form', $this->formData($project, $billingRate));
    }

    public function update(Request $request, Project $project, ProductionBillingRate $billingRate)
    {
        abort_unless((int) $billingRate->project_id === (int) $project->id, 404);

        $data = $this->validatedData($request, $project);
        $data['updated_by'] = auth()->id();
        $billingRate->update($data);

        return redirect()
            ->route('projects.production-v2.billing-rates.index', ['project' => $project->id])
            ->with('success', 'Production V2 billing rate updated.');
    }

    private function formData(Project $project, ProductionBillingRate $billingRate): array
    {
        return [
            'project' => $project,
            'billingRate' => $billingRate,
            'contractors' => Party::query()->where('is_contractor', true)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'operationMasters' => ProductionOperationMaster::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'uoms' => Uom::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'sourceTypes' => [
                'cut_batch' => 'Cut Batch',
                'fitup' => 'Fit-up',
                'welding' => 'Welding',
                'operation' => 'Operation',
            ],
            'qtyBasisOptions' => [
                'cut_batch' => ['output_qty' => 'Output Qty', 'output_weight_kg' => 'Output Weight (kg)', 'event_count' => 'Event Count'],
                'fitup' => ['assembly_qty' => 'Assembly Qty', 'assembly_weight_kg' => 'Assembly Weight (kg)', 'event_count' => 'Event Count'],
                'welding' => ['assembly_qty' => 'Assembly Qty', 'assembly_weight_kg' => 'Assembly Weight (kg)', 'event_count' => 'Event Count'],
                'operation' => ['event_qty' => 'Event Qty', 'event_count' => 'Event Count'],
            ],
        ];
    }

    private function validatedData(Request $request, Project $project): array
    {
        $data = $request->validate([
            'contractor_party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('is_contractor', true))],
            'source_type' => ['required', Rule::in(['cut_batch', 'fitup', 'welding', 'operation'])],
            'operation_master_id' => ['nullable', 'integer', 'exists:production_v2_operation_masters,id'],
            'qty_basis' => ['required', 'string', 'max:40'],
            'rate' => ['required', 'numeric', 'min:0'],
            'rate_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'description' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $allowedQtyBasis = [
            'cut_batch' => ['output_qty', 'output_weight_kg', 'event_count'],
            'fitup' => ['assembly_qty', 'assembly_weight_kg', 'event_count'],
            'welding' => ['assembly_qty', 'assembly_weight_kg', 'event_count'],
            'operation' => ['event_qty', 'event_count'],
        ];

        if ($data['source_type'] === 'operation' && empty($data['operation_master_id'])) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('operation_master_id', 'Operation is required for operation-based billing.');
            })->validate();
        }

        if (! in_array($data['qty_basis'], $allowedQtyBasis[$data['source_type']] ?? [], true)) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('qty_basis', 'Qty basis is not valid for the selected source type.');
            })->validate();
        }

        if ($data['source_type'] !== 'operation') {
            $data['operation_master_id'] = null;
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
