<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrPfSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrPfSlabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $slabs = HrPfSlab::query()->orderByDesc('effective_from')->paginate(20);
        return view('hr.settings.slabs.index', ['slabs' => $slabs, 'type' => 'pf', 'title' => 'PF Slabs']);
    }

    public function create(): View
    {
        return view('hr.settings.slabs.form', ['type' => 'pf', 'title' => 'Add PF Slab']);
    }

    public function store(Request $request): RedirectResponse
    {
        HrPfSlab::create($this->validateData($request));
        return redirect()->route('hr.settings.pf-slabs.index')->with('success', 'PF slab created successfully.');
    }

    public function edit(HrPfSlab $pfSlab): View
    {
        return view('hr.settings.slabs.form', ['type' => 'pf', 'title' => 'Edit PF Slab', 'slab' => $pfSlab]);
    }

    public function update(Request $request, HrPfSlab $pfSlab): RedirectResponse
    {
        $pfSlab->update($this->validateData($request));
        return redirect()->route('hr.settings.pf-slabs.index')->with('success', 'PF slab updated successfully.');
    }

    public function destroy(HrPfSlab $pfSlab): RedirectResponse
    {
        $pfSlab->delete();
        return redirect()->route('hr.settings.pf-slabs.index')->with('success', 'PF slab deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'wage_ceiling' => 'required|numeric|min:0',
            'employee_contribution_rate' => 'required|numeric|min:0|max:100',
            'employer_pf_rate' => 'required|numeric|min:0|max:100',
            'employer_eps_rate' => 'required|numeric|min:0|max:100',
            'employer_edli_rate' => 'required|numeric|min:0|max:100',
            'admin_charges_rate' => 'required|numeric|min:0|max:100',
            'edli_admin_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        return $validated;
    }
}
