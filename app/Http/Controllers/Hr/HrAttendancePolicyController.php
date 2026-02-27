<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrAttendancePolicy;
use Illuminate\Http\Request;

class HrAttendancePolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrAttendancePolicy::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $policies = $query->withCount('employees')
                          ->orderBy('name')
                          ->paginate(20)
                          ->withQueryString();

        return view('hr.attendance-policies.index', compact('policies'));
    }

    public function create()
    {
        return view('hr.attendance-policies.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_attendance_policies,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'min_working_hours_for_full_day' => 'nullable|numeric|min:0|max:24',
            'min_working_hours_for_half_day' => 'nullable|numeric|min:0|max:24',
            'weekly_off_days' => 'nullable|array',
            'weekly_off_days.*' => 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'allow_overtime' => 'boolean',
            'max_overtime_hours_per_day' => 'nullable|numeric|min:0|max:12',
            'max_overtime_hours_per_month' => 'nullable|numeric|min:0|max:100',
            'overtime_calculation_type' => 'nullable|in:daily,weekly,monthly',
            'late_mark_penalty_days' => 'nullable|integer|min:0',
            'late_marks_for_half_day' => 'nullable|integer|min:0',
            'late_marks_for_absent' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['allow_overtime'] = $request->boolean('allow_overtime', false);
        $validated['weekly_off_days'] = $validated['weekly_off_days'] ?? [];

        HrAttendancePolicy::create($validated);

        return redirect()->route('hr.attendance-policies.index')
                         ->with('success', 'Attendance policy created successfully.');
    }

    public function edit(HrAttendancePolicy $attendancePolicy)
    {
        return view('hr.attendance-policies.form', ['policy' => $attendancePolicy]);
    }

    public function update(Request $request, HrAttendancePolicy $attendancePolicy)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_attendance_policies,code,' . $attendancePolicy->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'min_working_hours_for_full_day' => 'nullable|numeric|min:0|max:24',
            'min_working_hours_for_half_day' => 'nullable|numeric|min:0|max:24',
            'weekly_off_days' => 'nullable|array',
            'weekly_off_days.*' => 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'allow_overtime' => 'boolean',
            'max_overtime_hours_per_day' => 'nullable|numeric|min:0|max:12',
            'max_overtime_hours_per_month' => 'nullable|numeric|min:0|max:100',
            'overtime_calculation_type' => 'nullable|in:daily,weekly,monthly',
            'late_mark_penalty_days' => 'nullable|integer|min:0',
            'late_marks_for_half_day' => 'nullable|integer|min:0',
            'late_marks_for_absent' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['allow_overtime'] = $request->boolean('allow_overtime', false);
        $validated['weekly_off_days'] = $validated['weekly_off_days'] ?? [];

        $attendancePolicy->update($validated);

        return redirect()->route('hr.attendance-policies.index')
                         ->with('success', 'Attendance policy updated successfully.');
    }

    public function destroy(HrAttendancePolicy $attendancePolicy)
    {
        if ($attendancePolicy->employees()->exists()) {
            return back()->with('error', 'Cannot delete policy. It is assigned to employees.');
        }

        $attendancePolicy->delete();

        return redirect()->route('hr.attendance-policies.index')
                         ->with('success', 'Attendance policy deleted successfully.');
    }
}
