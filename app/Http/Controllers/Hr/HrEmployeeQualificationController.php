<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeQualification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HrEmployeeQualificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $qualifications = $employee->qualifications()->orderByDesc('year_of_passing')->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->qualifications()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.qualifications.index', compact('employee', 'qualifications', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.qualifications.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;

        if ($request->hasFile('certificate')) {
            $validated['certificate_path'] = $request->file('certificate')->store('hr/employees/qualifications', 'public');
        }

        HrEmployeeQualification::create($validated);

        return redirect()->route('hr.employees.qualifications.index', $employee)
            ->with('success', 'Qualification added successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeQualification $qualification): RedirectResponse
    {
        $this->guardOwnership($employee, $qualification);

        return redirect()->route('hr.employees.qualifications.index', ['employee' => $employee->id, 'edit' => $qualification->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeQualification $qualification): RedirectResponse
    {
        $this->guardOwnership($employee, $qualification);

        $validated = $this->validateData($request);

        if ($request->hasFile('certificate')) {
            if ($qualification->certificate_path) {
                Storage::disk('public')->delete($qualification->certificate_path);
            }
            $validated['certificate_path'] = $request->file('certificate')->store('hr/employees/qualifications', 'public');
        }

        $qualification->update($validated);

        return redirect()->route('hr.employees.qualifications.index', $employee)
            ->with('success', 'Qualification updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeQualification $qualification): RedirectResponse
    {
        $this->guardOwnership($employee, $qualification);

        if ($qualification->certificate_path) {
            Storage::disk('public')->delete($qualification->certificate_path);
        }

        $qualification->delete();

        return redirect()->route('hr.employees.qualifications.index', $employee)
            ->with('success', 'Qualification removed successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'qualification_type' => 'required|in:below_10th,10th,12th,diploma,iti,graduation,post_graduation,doctorate,professional,other',
            'degree_name' => 'required|string|max:150',
            'specialization' => 'nullable|string|max:150',
            'institution_name' => 'required|string|max:200',
            'university_board' => 'nullable|string|max:200',
            'year_of_passing' => 'nullable|integer|min:1950|max:2100',
            'percentage_cgpa' => 'nullable|numeric|min:0|max:100',
            'grade_type' => 'nullable|in:percentage,cgpa,grade',
            'roll_number' => 'nullable|string|max:50',
            'certificate' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'is_verified' => 'nullable|boolean',
            'remarks' => 'nullable|string',
        ]);

        $validated['is_verified'] = $request->boolean('is_verified');
        if ($validated['is_verified']) {
            $validated['verified_by'] = auth()->id();
            $validated['verified_at'] = now();
        } else {
            $validated['verified_by'] = null;
            $validated['verified_at'] = null;
        }

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeQualification $qualification): void
    {
        if ($qualification->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
