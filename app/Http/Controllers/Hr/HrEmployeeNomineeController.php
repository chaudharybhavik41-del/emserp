<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeNominee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrEmployeeNomineeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $nominees = $employee->nominees()->latest()->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->nominees()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.nominees.index', compact('employee', 'nominees', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.nominees.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;

        if ($validated['is_active'] ?? false) {
            $employee->nominees()->where('nomination_for', $validated['nomination_for'])->update(['is_active' => false]);
        }

        HrEmployeeNominee::create($validated);

        return redirect()->route('hr.employees.nominees.index', $employee)
            ->with('success', 'Nominee added successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeNominee $nominee): RedirectResponse
    {
        $this->guardOwnership($employee, $nominee);

        return redirect()->route('hr.employees.nominees.index', ['employee' => $employee->id, 'edit' => $nominee->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeNominee $nominee): RedirectResponse
    {
        $this->guardOwnership($employee, $nominee);

        $validated = $this->validateData($request);
        if ($validated['is_active'] ?? false) {
            $employee->nominees()
                ->where('nomination_for', $validated['nomination_for'])
                ->whereKeyNot($nominee->id)
                ->update(['is_active' => false]);
        }

        $nominee->update($validated);

        return redirect()->route('hr.employees.nominees.index', $employee)
            ->with('success', 'Nominee updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeNominee $nominee): RedirectResponse
    {
        $this->guardOwnership($employee, $nominee);

        $nominee->delete();

        return redirect()->route('hr.employees.nominees.index', $employee)
            ->with('success', 'Nominee removed successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'nomination_for' => 'required|in:pf,gratuity,insurance,superannuation,other',
            'name' => 'required|string|max:200',
            'relationship' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'aadhar_number' => 'nullable|string|max:20',
            'share_percentage' => 'required|numeric|min:0|max:100',
            'is_minor' => 'nullable|boolean',
            'guardian_name' => 'nullable|string|max:200',
            'guardian_relationship' => 'nullable|string|max:100',
            'guardian_address' => 'nullable|string',
            'effective_from' => 'required|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_minor'] = $request->boolean('is_minor');
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeNominee $nominee): void
    {
        if ($nominee->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
