<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Account;
use App\Models\Project;
use App\Models\ProjectClientBillingRate;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectClientBillingRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:project.project.view')->only(['index']);
        $this->middleware('permission:project.project.update')->only(['create', 'store', 'edit', 'update']);
    }

    public function index(Project $project)
    {
        $rows = ProjectClientBillingRate::query()
            ->where('project_id', $project->id)
            ->with(['uom', 'revenueAccount'])
            ->orderByRaw("
                CASE
                    WHEN line_type = 'assembly_code' THEN 0
                    WHEN line_type = 'boq_item_code' THEN 1
                    WHEN line_type = 'scrap' THEN 2
                    WHEN line_type = 'generic' THEN 3
                    ELSE 9
                END
            ")
            ->orderBy('source_key')
            ->orderByDesc('effective_from')
            ->paginate(30);

        return view('projects.client_billing_rates.index', [
            'project' => $project,
            'rows' => $rows,
        ]);
    }

    public function create(Project $project)
    {
        return view('projects.client_billing_rates.form', $this->formData($project, new ProjectClientBillingRate(['is_active' => true])));
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validatedData($request);
        $data['project_id'] = $project->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        ProjectClientBillingRate::query()->create($data);

        return redirect()
            ->route('projects.client-billing-rates.index', $project)
            ->with('success', 'Client billing rate created.');
    }

    public function edit(Project $project, ProjectClientBillingRate $clientBillingRate)
    {
        abort_unless((int) $clientBillingRate->project_id === (int) $project->id, 404);

        return view('projects.client_billing_rates.form', $this->formData($project, $clientBillingRate));
    }

    public function update(Request $request, Project $project, ProjectClientBillingRate $clientBillingRate)
    {
        abort_unless((int) $clientBillingRate->project_id === (int) $project->id, 404);

        $data = $this->validatedData($request);
        $data['updated_by'] = auth()->id();

        $clientBillingRate->update($data);

        return redirect()
            ->route('projects.client-billing-rates.index', $project)
            ->with('success', 'Client billing rate updated.');
    }

    protected function formData(Project $project, ProjectClientBillingRate $clientBillingRate): array
    {
        return [
            'project' => $project,
            'clientBillingRate' => $clientBillingRate,
            'lineTypeOptions' => ProjectClientBillingRate::lineTypeOptions(),
            'uoms' => Uom::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'revenueAccounts' => Account::query()
                ->where('is_active', true)
                ->whereHas('group', fn (Builder $query) => $query->where('nature', 'income'))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ];
    }

    protected function validatedData(Request $request): array
    {
        $data = $request->validate([
            'line_type' => ['required', Rule::in(array_keys(ProjectClientBillingRate::lineTypeOptions()))],
            'source_key' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'rate' => ['required', 'numeric', 'min:0'],
            'revenue_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'sac_hsn_code' => ['nullable', 'string', 'max:20'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (in_array($data['line_type'], [ProjectClientBillingRate::LINE_TYPE_ASSEMBLY_CODE, ProjectClientBillingRate::LINE_TYPE_BOQ_ITEM_CODE], true)
            && blank($data['source_key'] ?? null)) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('source_key', 'A source key is required for assembly-code and BOQ-code billing rates.');
            })->validate();
        }

        if (in_array($data['line_type'], [ProjectClientBillingRate::LINE_TYPE_GENERIC, ProjectClientBillingRate::LINE_TYPE_SCRAP], true)) {
            $data['source_key'] = null;
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
