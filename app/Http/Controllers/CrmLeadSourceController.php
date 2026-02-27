<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCrmLeadSourceRequest;
use App\Http\Requests\UpdateCrmLeadSourceRequest;
use App\Models\CrmLeadSource;
use Illuminate\Http\Request;

class CrmLeadSourceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:crm.lead_source.view')->only(['index']);
        $this->middleware('permission:crm.lead_source.create')->only(['create', 'store']);
        $this->middleware('permission:crm.lead_source.update')->only(['edit', 'update']);
        $this->middleware('permission:crm.lead_source.delete')->only(['destroy']);
    }

    protected function generateCode(): string
    {
        $prefix = 'SRC';

        $last = CrmLeadSource::where('code', 'like', $prefix.'-%')
            ->orderBy('code', 'desc')
            ->first();

        $next = 1;
        if ($last && preg_match('/^SRC\-(\d{3})$/', $last->code, $m)) {
            $next = (int) $m[1] + 1;
        }

        return sprintf('%s-%03d', $prefix, $next);
    }

    public function index(Request $request)
    {
        $query = CrmLeadSource::query();

        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $sources = $query
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('crm.lead_sources.index', compact('sources'));
    }

    public function create()
    {
        return view('crm.lead_sources.create');
    }

    public function store(StoreCrmLeadSourceRequest $request)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $this->generateCode();
        }

        $data['is_active'] = $request->boolean('is_active');

        CrmLeadSource::create($data);

        return redirect()
            ->route('crm.lead-sources.index')
            ->with('success', 'Lead Source created.');
    }

    public function edit(CrmLeadSource $lead_source)
    {
        return view('crm.lead_sources.edit', compact('lead_source'));
    }

    public function update(UpdateCrmLeadSourceRequest $request, CrmLeadSource $lead_source)
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $data['code'] = $lead_source->code;
        }

        $data['is_active'] = $request->boolean('is_active');

        $lead_source->update($data);

        return redirect()
            ->route('crm.lead-sources.index')
            ->with('success', 'Lead Source updated.');
    }

    public function destroy(CrmLeadSource $lead_source)
    {
        // Guardrail: prevent delete if used by leads
        if ($lead_source->leads()->exists()) {
            return redirect()
                ->route('crm.lead-sources.index')
                ->with('error', 'Cannot delete: Lead Source is used in leads.');
        }

        $lead_source->delete();

        return redirect()
            ->route('crm.lead-sources.index')
            ->with('success', 'Lead Source deleted.');
    }
}
