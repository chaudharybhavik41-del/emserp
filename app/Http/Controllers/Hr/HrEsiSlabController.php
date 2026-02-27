<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEsiSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrEsiSlabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $slabs = HrEsiSlab::query()->orderByDesc('effective_from')->paginate(20);
        return view('hr.settings.slabs.index', ['slabs' => $slabs, 'type' => 'esi', 'title' => 'ESI Slabs']);
    }

    public function create(): View
    {
        return view('hr.settings.slabs.form', ['type' => 'esi', 'title' => 'Add ESI Slab']);
    }

    public function store(Request $request): RedirectResponse
    {
        HrEsiSlab::create($this->validateData($request));
        return redirect()->route('hr.settings.esi-slabs.index')->with('success', 'ESI slab created successfully.');
    }

    public function edit(HrEsiSlab $esiSlab): View
    {
        return view('hr.settings.slabs.form', ['type' => 'esi', 'title' => 'Edit ESI Slab', 'slab' => $esiSlab]);
    }

    public function update(Request $request, HrEsiSlab $esiSlab): RedirectResponse
    {
        $esiSlab->update($this->validateData($request));
        return redirect()->route('hr.settings.esi-slabs.index')->with('success', 'ESI slab updated successfully.');
    }

    public function destroy(HrEsiSlab $esiSlab): RedirectResponse
    {
        $esiSlab->delete();
        return redirect()->route('hr.settings.esi-slabs.index')->with('success', 'ESI slab deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'wage_ceiling' => 'required|numeric|min:0',
            'employee_rate' => 'required|numeric|min:0|max:100',
            'employer_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        return $validated;
    }
}
