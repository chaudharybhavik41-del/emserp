<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrAttendancePolicy;
use App\Models\Hr\HrEmployee;
use App\Services\Hr\PayrollPeriodStalenessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'effective_from' => 'nullable|date',
            'grace_period_minutes' => 'nullable|integer|min:0|max:120',
            'late_deduction_per_instance' => 'nullable|integer|min:0|max:480',
            'max_late_instances_per_month' => 'nullable|integer|min:0|max:31',
            'late_deduction_from_leave' => 'nullable|numeric|min:0|max:10',
            'half_day_after_late_minutes' => 'nullable|integer|min:0|max:480',
            'half_day_after_early_minutes' => 'nullable|integer|min:0|max:480',
            'min_hours_for_full_day' => 'nullable|numeric|min:0|max:24',
            'min_hours_for_half_day' => 'nullable|numeric|min:0|max:24',
            'absent_after_late_minutes' => 'nullable|integer|min:0|max:720',
            'mark_absent_on_no_punch' => 'boolean',
            'ot_allowed' => 'boolean',
            'ot_rate_multiplier' => 'nullable|numeric|min:0|max:10',
            'ot_calculation_basis' => 'nullable|in:basic,gross,fixed',
            'ot_min_minutes' => 'nullable|integer|min:0|max:720',
            'ot_max_hours_per_day' => 'nullable|integer|min:0|max:24',
            'ot_max_hours_per_month' => 'nullable|integer|min:0|max:300',
            'ot_needs_approval' => 'boolean',
            'allow_week_off_work' => 'boolean',
            'week_off_ot_multiplier' => 'nullable|numeric|min:0|max:10',
            'holiday_ot_multiplier' => 'nullable|numeric|min:0|max:10',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['company_id'] = 1;
        $validated['effective_from'] = $validated['effective_from'] ?? now()->toDateString();
        $validated['grace_period_minutes'] = (int) ($validated['grace_period_minutes'] ?? 10);
        $validated['late_deduction_per_instance'] = (int) ($validated['late_deduction_per_instance'] ?? 0);
        $validated['max_late_instances_per_month'] = (int) ($validated['max_late_instances_per_month'] ?? 3);
        $validated['late_deduction_from_leave'] = (float) ($validated['late_deduction_from_leave'] ?? 0);
        $validated['half_day_after_late_minutes'] = (int) ($validated['half_day_after_late_minutes'] ?? 120);
        $validated['half_day_after_early_minutes'] = (int) ($validated['half_day_after_early_minutes'] ?? 120);
        $validated['min_hours_for_full_day'] = (float) ($validated['min_hours_for_full_day'] ?? 8);
        $validated['min_hours_for_half_day'] = (float) ($validated['min_hours_for_half_day'] ?? 4);
        $validated['absent_after_late_minutes'] = (int) ($validated['absent_after_late_minutes'] ?? 240);
        $validated['mark_absent_on_no_punch'] = $request->boolean('mark_absent_on_no_punch', true);
        $validated['ot_allowed'] = $request->boolean('ot_allowed', true);
        $validated['ot_rate_multiplier'] = (float) ($validated['ot_rate_multiplier'] ?? 1.5);
        $validated['ot_calculation_basis'] = $validated['ot_calculation_basis'] ?? 'basic';
        $validated['ot_min_minutes'] = (int) ($validated['ot_min_minutes'] ?? 30);
        $validated['ot_max_hours_per_day'] = (int) ($validated['ot_max_hours_per_day'] ?? 4);
        $validated['ot_max_hours_per_month'] = (int) ($validated['ot_max_hours_per_month'] ?? 50);
        $validated['ot_needs_approval'] = $request->boolean('ot_needs_approval', true);
        $validated['allow_week_off_work'] = $request->boolean('allow_week_off_work', true);
        $validated['week_off_ot_multiplier'] = (float) ($validated['week_off_ot_multiplier'] ?? 2);
        $validated['holiday_ot_multiplier'] = (float) ($validated['holiday_ot_multiplier'] ?? 2);
        $validated['is_default'] = $request->boolean('is_default', false);
        $validated['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($validated): void {
            $policy = HrAttendancePolicy::create($validated);
            $this->applyDefaultPolicyToAllEmployeesIfRequired($policy, (bool) $validated['is_default']);
            app(PayrollPeriodStalenessService::class)
                ->markPeriodsFromDate($policy->effective_from ?? $validated['effective_from'], "Attendance policy {$policy->code} changed.");
        });

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
            'effective_from' => 'nullable|date',
            'grace_period_minutes' => 'nullable|integer|min:0|max:120',
            'late_deduction_per_instance' => 'nullable|integer|min:0|max:480',
            'max_late_instances_per_month' => 'nullable|integer|min:0|max:31',
            'late_deduction_from_leave' => 'nullable|numeric|min:0|max:10',
            'half_day_after_late_minutes' => 'nullable|integer|min:0|max:480',
            'half_day_after_early_minutes' => 'nullable|integer|min:0|max:480',
            'min_hours_for_full_day' => 'nullable|numeric|min:0|max:24',
            'min_hours_for_half_day' => 'nullable|numeric|min:0|max:24',
            'absent_after_late_minutes' => 'nullable|integer|min:0|max:720',
            'mark_absent_on_no_punch' => 'boolean',
            'ot_allowed' => 'boolean',
            'ot_rate_multiplier' => 'nullable|numeric|min:0|max:10',
            'ot_calculation_basis' => 'nullable|in:basic,gross,fixed',
            'ot_min_minutes' => 'nullable|integer|min:0|max:720',
            'ot_max_hours_per_day' => 'nullable|integer|min:0|max:24',
            'ot_max_hours_per_month' => 'nullable|integer|min:0|max:300',
            'ot_needs_approval' => 'boolean',
            'allow_week_off_work' => 'boolean',
            'week_off_ot_multiplier' => 'nullable|numeric|min:0|max:10',
            'holiday_ot_multiplier' => 'nullable|numeric|min:0|max:10',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['effective_from'] = $validated['effective_from'] ?? optional($attendancePolicy->effective_from)->toDateString() ?? now()->toDateString();
        $validated['grace_period_minutes'] = (int) ($validated['grace_period_minutes'] ?? $attendancePolicy->grace_period_minutes);
        $validated['late_deduction_per_instance'] = (int) ($validated['late_deduction_per_instance'] ?? $attendancePolicy->late_deduction_per_instance);
        $validated['max_late_instances_per_month'] = (int) ($validated['max_late_instances_per_month'] ?? $attendancePolicy->max_late_instances_per_month);
        $validated['late_deduction_from_leave'] = (float) ($validated['late_deduction_from_leave'] ?? $attendancePolicy->late_deduction_from_leave);
        $validated['half_day_after_late_minutes'] = (int) ($validated['half_day_after_late_minutes'] ?? $attendancePolicy->half_day_after_late_minutes);
        $validated['half_day_after_early_minutes'] = (int) ($validated['half_day_after_early_minutes'] ?? $attendancePolicy->half_day_after_early_minutes);
        $validated['min_hours_for_full_day'] = (float) ($validated['min_hours_for_full_day'] ?? $attendancePolicy->min_hours_for_full_day);
        $validated['min_hours_for_half_day'] = (float) ($validated['min_hours_for_half_day'] ?? $attendancePolicy->min_hours_for_half_day);
        $validated['absent_after_late_minutes'] = (int) ($validated['absent_after_late_minutes'] ?? $attendancePolicy->absent_after_late_minutes);
        $validated['mark_absent_on_no_punch'] = $request->boolean('mark_absent_on_no_punch', $attendancePolicy->mark_absent_on_no_punch);
        $validated['ot_allowed'] = $request->boolean('ot_allowed', $attendancePolicy->ot_allowed);
        $validated['ot_rate_multiplier'] = (float) ($validated['ot_rate_multiplier'] ?? $attendancePolicy->ot_rate_multiplier ?? 1.5);
        $validated['ot_calculation_basis'] = $validated['ot_calculation_basis'] ?? $attendancePolicy->ot_calculation_basis ?? 'basic';
        $validated['ot_min_minutes'] = (int) ($validated['ot_min_minutes'] ?? $attendancePolicy->ot_min_minutes);
        $validated['ot_max_hours_per_day'] = (int) ($validated['ot_max_hours_per_day'] ?? $attendancePolicy->ot_max_hours_per_day);
        $validated['ot_max_hours_per_month'] = (int) ($validated['ot_max_hours_per_month'] ?? $attendancePolicy->ot_max_hours_per_month);
        $validated['ot_needs_approval'] = $request->boolean('ot_needs_approval', $attendancePolicy->ot_needs_approval);
        $validated['allow_week_off_work'] = $request->boolean('allow_week_off_work', $attendancePolicy->allow_week_off_work);
        $validated['week_off_ot_multiplier'] = (float) ($validated['week_off_ot_multiplier'] ?? $attendancePolicy->week_off_ot_multiplier);
        $validated['holiday_ot_multiplier'] = (float) ($validated['holiday_ot_multiplier'] ?? $attendancePolicy->holiday_ot_multiplier);
        $validated['is_default'] = $request->boolean('is_default', $attendancePolicy->is_default);
        $validated['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($attendancePolicy, $validated): void {
            $attendancePolicy->update($validated);
            $freshPolicy = $attendancePolicy->fresh();
            $this->applyDefaultPolicyToAllEmployeesIfRequired($freshPolicy, (bool) $validated['is_default']);
            app(PayrollPeriodStalenessService::class)
                ->markPeriodsFromDate($freshPolicy->effective_from ?? $validated['effective_from'], "Attendance policy {$freshPolicy->code} changed.");
        });

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

    private function applyDefaultPolicyToAllEmployeesIfRequired(HrAttendancePolicy $policy, bool $shouldApplyToAll): void
    {
        if (! $shouldApplyToAll) {
            return;
        }

        HrAttendancePolicy::query()
            ->whereKeyNot($policy->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        HrEmployee::query()->update([
            'hr_attendance_policy_id' => $policy->id,
            'updated_at' => now(),
        ]);
    }
}
