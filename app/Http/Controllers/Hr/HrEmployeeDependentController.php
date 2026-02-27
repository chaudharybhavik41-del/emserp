<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeDependent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrEmployeeDependentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $dependents = $employee->dependents()->latest()->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->dependents()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.dependents.index', compact('employee', 'dependents', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.dependents.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;

        HrEmployeeDependent::create($validated);

        return redirect()->route('hr.employees.dependents.index', $employee)
            ->with('success', 'Dependent added successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeDependent $dependent): RedirectResponse
    {
        $this->guardOwnership($employee, $dependent);

        return redirect()->route('hr.employees.dependents.index', ['employee' => $employee->id, 'edit' => $dependent->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeDependent $dependent): RedirectResponse
    {
        $this->guardOwnership($employee, $dependent);

        $dependent->update($this->validateData($request));

        return redirect()->route('hr.employees.dependents.index', $employee)
            ->with('success', 'Dependent updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeDependent $dependent): RedirectResponse
    {
        $this->guardOwnership($employee, $dependent);

        $dependent->delete();

        return redirect()->route('hr.employees.dependents.index', $employee)
            ->with('success', 'Dependent removed successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'relationship' => 'required|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'aadhar_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'nomination_percentage' => 'nullable|numeric|min:0|max:100',
            'occupation' => 'nullable|string|max:100',
            'is_dependent_for_insurance' => 'nullable|boolean',
            'is_emergency_contact' => 'nullable|boolean',
            'is_nominee' => 'nullable|boolean',
            'is_disabled' => 'nullable|boolean',
        ]);

        $validated['is_dependent_for_insurance'] = $request->boolean('is_dependent_for_insurance');
        $validated['is_emergency_contact'] = $request->boolean('is_emergency_contact');
        $validated['is_nominee'] = $request->boolean('is_nominee');
        $validated['is_disabled'] = $request->boolean('is_disabled');

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeDependent $dependent): void
    {
        if ($dependent->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
