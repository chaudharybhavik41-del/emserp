<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrProfessionalTaxSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrPtSlabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $slabs = HrProfessionalTaxSlab::query()->orderBy('state_name')->orderBy('salary_from')->paginate(20);
        return view('hr.settings.slabs.index', ['slabs' => $slabs, 'type' => 'pt', 'title' => 'Professional Tax Slabs']);
    }

    public function create(): View
    {
        return view('hr.settings.slabs.form', ['type' => 'pt', 'title' => 'Add Professional Tax Slab']);
    }

    public function store(Request $request): RedirectResponse
    {
        HrProfessionalTaxSlab::create($this->validateData($request));
        return redirect()->route('hr.settings.pt-slabs.index')->with('success', 'PT slab created successfully.');
    }

    public function edit(HrProfessionalTaxSlab $ptSlab): View
    {
        return view('hr.settings.slabs.form', ['type' => 'pt', 'title' => 'Edit Professional Tax Slab', 'slab' => $ptSlab]);
    }

    public function update(Request $request, HrProfessionalTaxSlab $ptSlab): RedirectResponse
    {
        $ptSlab->update($this->validateData($request));
        return redirect()->route('hr.settings.pt-slabs.index')->with('success', 'PT slab updated successfully.');
    }

    public function destroy(HrProfessionalTaxSlab $ptSlab): RedirectResponse
    {
        $ptSlab->delete();
        return redirect()->route('hr.settings.pt-slabs.index')->with('success', 'PT slab deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'state_code' => 'required|string|max:10',
            'state_name' => 'required|string|max:50',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'salary_from' => 'required|numeric|min:0',
            'salary_to' => 'required|numeric|gte:salary_from',
            'tax_amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,annual',
            'gender' => 'required|in:all,male,female',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['state_code'] = strtoupper($validated['state_code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        return $validated;
    }
}
