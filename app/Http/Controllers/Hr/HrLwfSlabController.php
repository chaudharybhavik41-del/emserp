<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrLwfSlab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrLwfSlabController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $slabs = HrLwfSlab::query()->orderBy('state_name')->orderByDesc('effective_from')->paginate(20);
        return view('hr.settings.slabs.index', ['slabs' => $slabs, 'type' => 'lwf', 'title' => 'LWF Slabs']);
    }

    public function create(): View
    {
        return view('hr.settings.slabs.form', ['type' => 'lwf', 'title' => 'Add LWF Slab']);
    }

    public function store(Request $request): RedirectResponse
    {
        HrLwfSlab::create($this->validateData($request));
        return redirect()->route('hr.settings.lwf-slabs.index')->with('success', 'LWF slab created successfully.');
    }

    public function edit(HrLwfSlab $lwfSlab): View
    {
        return view('hr.settings.slabs.form', ['type' => 'lwf', 'title' => 'Edit LWF Slab', 'slab' => $lwfSlab]);
    }

    public function update(Request $request, HrLwfSlab $lwfSlab): RedirectResponse
    {
        $lwfSlab->update($this->validateData($request));
        return redirect()->route('hr.settings.lwf-slabs.index')->with('success', 'LWF slab updated successfully.');
    }

    public function destroy(HrLwfSlab $lwfSlab): RedirectResponse
    {
        $lwfSlab->delete();
        return redirect()->route('hr.settings.lwf-slabs.index')->with('success', 'LWF slab deleted successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'state_code' => 'required|string|max:10',
            'state_name' => 'required|string|max:50',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'employee_contribution' => 'required|numeric|min:0',
            'employer_contribution' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,half_yearly,annual',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['state_code'] = strtoupper($validated['state_code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        return $validated;
    }
}
