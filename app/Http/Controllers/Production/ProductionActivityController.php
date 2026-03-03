<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductionActivity;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:production.activity.view')->only(['index']);
        $this->middleware('permission:production.activity.create')->only(['create', 'store']);
        $this->middleware('permission:production.activity.update')->only(['edit', 'update']);
        $this->middleware('permission:production.activity.delete')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'active'); // active|inactive|all
        $appliesToRaw = (string) $request->get('applies_to', '');
        $legacyAppliesToMap = [
            'fg' => 'part',
            'sub_assembly' => 'assembly',
        ];
        $appliesTo = $legacyAppliesToMap[$appliesToRaw] ?? $appliesToRaw;
        $requiresMachine = (string) $request->get('requires_machine', '');
        $requiresQc = (string) $request->get('requires_qc', '');

        $query = ProductionActivity::query()
            ->with('billingUom');

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', '%' . $q . '%')
                   ->orWhere('name', 'like', '%' . $q . '%');
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if (in_array($appliesTo, ['part', 'assembly', 'both'], true)) {
            $query->where('applies_to', $appliesTo);
        }

        if (in_array($requiresMachine, ['1', '0'], true)) {
            $query->where('requires_machine', $requiresMachine === '1');
        }

        if (in_array($requiresQc, ['1', '0'], true)) {
            $query->where('requires_qc', $requiresQc === '1');
        }

        $sort = (string) $request->get('sort', '');
        $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['default_sequence', 'code', 'name', 'applies_to', 'calculation_method', 'is_active', 'updated_at', 'created_at'];
        if ($sort !== '' && in_array($sort, $sortable, true)) {
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('default_sequence')->orderBy('name');
        }

        $perPage = (int) $request->get('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $activities = $query->paginate($perPage)->withQueryString();

        return view('production.activities.index', [
            'activities' => $activities,
            'q' => $q,
            'status' => $status,
            'appliesTo' => $appliesTo,
        ]);
    }

    public function create()
    {
        $activity = new ProductionActivity([
            'applies_to' => 'both',
            'default_sequence' => 0,
            'calculation_method' => 'manual',
            'is_active' => true,
            'is_fitupp' => false,
            'requires_machine' => false,
            'requires_qc' => false,
        ]);

        $uoms = Uom::orderBy('code')->get();

        return view('production.activities.create', [
            'activity' => $activity,
            'uoms' => $uoms,
            'appliesToOptions' => ProductionActivity::appliesToOptions(),
            'calcOptions' => ProductionActivity::calculationMethodOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:production_activities,code'],
            'name' => ['required', 'string', 'max:200'],
            'applies_to' => ['required', Rule::in(array_keys(ProductionActivity::appliesToOptions()))],
            'default_sequence' => ['nullable', 'integer', 'min:0'],
            'billing_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'calculation_method' => ['required', Rule::in(array_keys(ProductionActivity::calculationMethodOptions()))],
            'is_fitupp' => ['nullable', 'boolean'],
            'requires_machine' => ['nullable', 'boolean'],
            'requires_qc' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $activity = new ProductionActivity();
        $activity->code = strtoupper(trim($data['code']));
        $activity->name = trim($data['name']);
        $activity->applies_to = $data['applies_to'];
        $activity->default_sequence = (int) ($data['default_sequence'] ?? 0);
        $activity->billing_uom_id = $data['billing_uom_id'] ?? null;
        $activity->calculation_method = $data['calculation_method'];
        $activity->is_fitupp = (bool) ($data['is_fitupp'] ?? false);
        $activity->requires_machine = (bool) ($data['requires_machine'] ?? false);
        $activity->requires_qc = (bool) ($data['requires_qc'] ?? false);
        $activity->is_active = (bool) ($data['is_active'] ?? false);

        $activity->created_by = auth()->id();
        $activity->updated_by = auth()->id();
        $activity->save();

        return redirect()
            ->route('production.activities.index')
            ->with('success', 'Production activity created.');
    }

    public function edit(ProductionActivity $activity)
    {
        $uoms = Uom::orderBy('code')->get();

        return view('production.activities.edit', [
            'activity' => $activity,
            'uoms' => $uoms,
            'appliesToOptions' => ProductionActivity::appliesToOptions(),
            'calcOptions' => ProductionActivity::calculationMethodOptions(),
        ]);
    }

    public function update(Request $request, ProductionActivity $activity)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('production_activities', 'code')->ignore($activity->id)],
            'name' => ['required', 'string', 'max:200'],
            'applies_to' => ['required', Rule::in(array_keys(ProductionActivity::appliesToOptions()))],
            'default_sequence' => ['nullable', 'integer', 'min:0'],
            'billing_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'calculation_method' => ['required', Rule::in(array_keys(ProductionActivity::calculationMethodOptions()))],
            'is_fitupp' => ['nullable', 'boolean'],
            'requires_machine' => ['nullable', 'boolean'],
            'requires_qc' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $activity->code = strtoupper(trim($data['code']));
        $activity->name = trim($data['name']);
        $activity->applies_to = $data['applies_to'];
        $activity->default_sequence = (int) ($data['default_sequence'] ?? 0);
        $activity->billing_uom_id = $data['billing_uom_id'] ?? null;
        $activity->calculation_method = $data['calculation_method'];
        $activity->is_fitupp = (bool) ($data['is_fitupp'] ?? false);
        $activity->requires_machine = (bool) ($data['requires_machine'] ?? false);
        $activity->requires_qc = (bool) ($data['requires_qc'] ?? false);
        $activity->is_active = (bool) ($data['is_active'] ?? false);

        $activity->updated_by = auth()->id();
        $activity->save();

        return redirect()
            ->route('production.activities.index')
            ->with('success', 'Production activity updated.');
    }

    public function destroy(ProductionActivity $activity)
    {
        // Soft-delete style: deactivate instead of deleting (safer for history)
        $activity->is_active = false;
        $activity->updated_by = auth()->id();
        $activity->save();

        return redirect()
            ->route('production.activities.index')
            ->with('success', 'Production activity disabled.');
    }
}
