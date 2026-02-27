<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrTdsSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrTdsSlabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $slabs = HrTdsSlab::query()->orderByDesc('financial_year')->orderBy('regime')->orderBy('income_from')->paginate(20);
        return view('hr.settings.slabs.index', ['slabs' => $slabs, 'type' => 'tds', 'title' => 'TDS Slabs']);
    }

    public function create(): View
    {
        return view('hr.settings.slabs.form', ['type' => 'tds', 'title' => 'Add TDS Slab']);
    }

    public function store(Request $request): RedirectResponse
    {
        HrTdsSlab::create($this->validateData($request));
        return redirect()->route('hr.settings.tds-slabs.index')->with('success', 'TDS slab created successfully.');
    }

    public function edit(HrTdsSlab $tdsSlab): View
    {
        return view('hr.settings.slabs.form', ['type' => 'tds', 'title' => 'Edit TDS Slab', 'slab' => $tdsSlab]);
    }

    public function update(Request $request, HrTdsSlab $tdsSlab): RedirectResponse
    {
        $tdsSlab->update($this->validateData($request));
        return redirect()->route('hr.settings.tds-slabs.index')->with('success', 'TDS slab updated successfully.');
    }

    public function destroy(HrTdsSlab $tdsSlab): RedirectResponse
    {
        $tdsSlab->delete();
        return redirect()->route('hr.settings.tds-slabs.index')->with('success', 'TDS slab deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'financial_year' => 'required|string|max:10',
            'regime' => 'required|in:old,new',
            'category' => 'required|in:general,senior,super_senior',
            'income_from' => 'required|numeric|min:0',
            'income_to' => 'required|numeric|gte:income_from',
            'tax_percent' => 'required|numeric|min:0|max:100',
            'surcharge_percent' => 'nullable|numeric|min:0|max:100',
            'cess_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['surcharge_percent'] = $validated['surcharge_percent'] ?? 0;
        $validated['cess_percent'] = $validated['cess_percent'] ?? 4;

        return $validated;
    }
}
