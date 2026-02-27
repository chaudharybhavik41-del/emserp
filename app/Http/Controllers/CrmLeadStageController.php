<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCrmLeadStageRequest;
use App\Http\Requests\UpdateCrmLeadStageRequest;
use App\Models\CrmLeadStage;
use Illuminate\Http\Request;

class CrmLeadStageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:crm.lead_stage.view')->only(['index']);
        $this->middleware('permission:crm.lead_stage.create')->only(['create', 'store']);
        $this->middleware('permission:crm.lead_stage.update')->only(['edit', 'update']);
        $this->middleware('permission:crm.lead_stage.delete')->only(['destroy']);
    }

    protected function generateCode(): string
    {
        $prefix = 'STG';

        $last = CrmLeadStage::where('code', 'like', $prefix.'-%')
            ->orderBy('code', 'desc')
            ->first();

        $next = 1;
        if ($last && preg_match('/^STG\-(\d{3})$/', $last->code, $m)) {
            $next = (int) $m[1] + 1;
        }

        return sprintf('%s-%03d', $prefix, $next);
    }

    public function index(Request $request)
    {
        $query = CrmLeadStage::query();

        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $stages = $query
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('crm.lead_stages.index', compact('stages'));
    }

    public function create()
    {
        return view('crm.lead_stages.create');
    }

    public function store(StoreCrmLeadStageRequest $request)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $this->generateCode();
        }

        $data['is_won']    = $request->boolean('is_won');
        $data['is_lost']   = $request->boolean('is_lost');
        $data['is_closed'] = $request->boolean('is_closed');
        $data['is_active'] = $request->boolean('is_active');

        CrmLeadStage::create($data);

        return redirect()
            ->route('crm.lead-stages.index')
            ->with('success', 'Lead Stage created.');
    }

    public function edit(CrmLeadStage $lead_stage)
    {
        return view('crm.lead_stages.edit', compact('lead_stage'));
    }

    public function update(UpdateCrmLeadStageRequest $request, CrmLeadStage $lead_stage)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $lead_stage->code;
        }

        $data['is_won']    = $request->boolean('is_won');
        $data['is_lost']   = $request->boolean('is_lost');
        $data['is_closed'] = $request->boolean('is_closed');
        $data['is_active'] = $request->boolean('is_active');

        $lead_stage->update($data);

        return redirect()
            ->route('crm.lead-stages.index')
            ->with('success', 'Lead Stage updated.');
    }

    public function destroy(CrmLeadStage $lead_stage)
    {
        if ($lead_stage->leads()->exists()) {
            return redirect()
                ->route('crm.lead-stages.index')
                ->with('error', 'Cannot delete: Lead Stage is used in leads.');
        }

        $lead_stage->delete();

        return redirect()
            ->route('crm.lead-stages.index')
            ->with('success', 'Lead Stage deleted.');
    }
}
