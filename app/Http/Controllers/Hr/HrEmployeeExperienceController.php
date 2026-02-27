<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeExperience;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HrEmployeeExperienceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(HrEmployee $employee, Request $request): View
    {
        $experiences = $employee->experiences()->orderByDesc('from_date')->paginate(20)->withQueryString();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = $employee->experiences()->whereKey($request->integer('edit'))->first();
        }

        return view('hr.employees.experiences.index', compact('employee', 'experiences', 'editing'));
    }

    public function create(HrEmployee $employee): RedirectResponse
    {
        return redirect()->route('hr.employees.experiences.index', $employee)->with('show_form', true);
    }

    public function store(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $this->validateData($request);
        $validated['hr_employee_id'] = $employee->id;

        if ($request->hasFile('experience_letter')) {
            $validated['experience_letter_path'] = $request->file('experience_letter')->store('hr/employees/experiences', 'public');
        }

        if ($request->hasFile('relieving_letter')) {
            $validated['relieving_letter_path'] = $request->file('relieving_letter')->store('hr/employees/experiences', 'public');
        }

        if (!empty($validated['from_date'])) {
            $to = !empty($validated['to_date']) ? Carbon::parse($validated['to_date']) : now();
            $from = Carbon::parse($validated['from_date']);
            $validated['experience_months'] = max(0, $from->diffInMonths($to));
        }

        HrEmployeeExperience::create($validated);

        return redirect()->route('hr.employees.experiences.index', $employee)
            ->with('success', 'Experience record added successfully.');
    }

    public function edit(HrEmployee $employee, HrEmployeeExperience $experience): RedirectResponse
    {
        $this->guardOwnership($employee, $experience);

        return redirect()->route('hr.employees.experiences.index', ['employee' => $employee->id, 'edit' => $experience->id]);
    }

    public function update(Request $request, HrEmployee $employee, HrEmployeeExperience $experience): RedirectResponse
    {
        $this->guardOwnership($employee, $experience);

        $validated = $this->validateData($request);

        if ($request->hasFile('experience_letter')) {
            if ($experience->experience_letter_path) {
                Storage::disk('public')->delete($experience->experience_letter_path);
            }
            $validated['experience_letter_path'] = $request->file('experience_letter')->store('hr/employees/experiences', 'public');
        }

        if ($request->hasFile('relieving_letter')) {
            if ($experience->relieving_letter_path) {
                Storage::disk('public')->delete($experience->relieving_letter_path);
            }
            $validated['relieving_letter_path'] = $request->file('relieving_letter')->store('hr/employees/experiences', 'public');
        }

        if (!empty($validated['from_date'])) {
            $to = !empty($validated['to_date']) ? Carbon::parse($validated['to_date']) : now();
            $from = Carbon::parse($validated['from_date']);
            $validated['experience_months'] = max(0, $from->diffInMonths($to));
        }

        $experience->update($validated);

        return redirect()->route('hr.employees.experiences.index', $employee)
            ->with('success', 'Experience record updated successfully.');
    }

    public function destroy(HrEmployee $employee, HrEmployeeExperience $experience): RedirectResponse
    {
        $this->guardOwnership($employee, $experience);

        if ($experience->experience_letter_path) {
            Storage::disk('public')->delete($experience->experience_letter_path);
        }
        if ($experience->relieving_letter_path) {
            Storage::disk('public')->delete($experience->relieving_letter_path);
        }

        $experience->delete();

        return redirect()->route('hr.employees.experiences.index', $employee)
            ->with('success', 'Experience record removed successfully.');
    }

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:200',
            'designation' => 'required|string|max:150',
            'department' => 'nullable|string|max:100',
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'is_current' => 'nullable|boolean',
            'location' => 'nullable|string|max:150',
            'reporting_to' => 'nullable|string|max:150',
            'last_ctc' => 'nullable|numeric|min:0',
            'job_responsibilities' => 'nullable|string',
            'reason_for_leaving' => 'nullable|string',
            'reference_name' => 'nullable|string|max:150',
            'reference_contact' => 'nullable|string|max:50',
            'reference_email' => 'nullable|email|max:150',
            'reference_verified' => 'nullable|boolean',
            'experience_letter' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'relieving_letter' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'is_verified' => 'nullable|boolean',
            'remarks' => 'nullable|string',
        ]);

        $validated['is_current'] = $request->boolean('is_current');
        $validated['reference_verified'] = $request->boolean('reference_verified');
        $validated['is_verified'] = $request->boolean('is_verified');

        return $validated;
    }

    private function guardOwnership(HrEmployee $employee, HrEmployeeExperience $experience): void
    {
        if ($experience->hr_employee_id !== $employee->id) {
            abort(404);
        }
    }
}
