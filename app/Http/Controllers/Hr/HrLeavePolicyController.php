<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrLeavePolicy;
use App\Models\Hr\HrLeavePolicyDetail;
use App\Models\Hr\HrLeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrLeavePolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrLeavePolicy::with('leaveType');

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

        return view('hr.leave-policies.index', compact('policies'));
    }

    public function create()
    {
        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('name')->get();
        return view('hr.leave-policies.form', compact('leaveTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_leave_policies,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'leave_entitlements' => 'required|array|min:1',
            'leave_entitlements.*.leave_type_id' => 'required|exists:hr_leave_types,id',
            'leave_entitlements.*.annual_entitlement' => 'required|numeric|min:0|max:365',
            'leave_entitlements.*.max_accumulation' => 'nullable|numeric|min:0|max:365',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $policy = HrLeavePolicy::create([
                'company_id' => 1,
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'leave_year_type' => 'calendar',
                'allow_leave_in_probation' => false,
                'allow_backdated_application' => false,
                'max_backdate_days' => 7,
                'allow_future_application' => true,
                'max_future_days' => 90,
                'sandwich_rule_enabled' => true,
                'sandwich_min_gap_days' => 0,
                'approval_levels' => 1,
                'skip_level_on_absence' => true,
                'is_active' => $request->boolean('is_active', true),
            ]);

            foreach ($validated['leave_entitlements'] as $row) {
                HrLeavePolicyDetail::create([
                    'hr_leave_policy_id' => $policy->id,
                    'hr_leave_type_id' => $row['leave_type_id'],
                    'days_per_year' => $row['annual_entitlement'],
                    'max_carry_forward' => $row['max_accumulation'] ?? 0,
                    'allow_encashment' => false,
                ]);
            }
        });

        return redirect()->route('hr.leave-policies.index')
                         ->with('success', 'Leave policy created successfully.');
    }

    public function show(HrLeavePolicy $leavePolicy)
    {
        $leavePolicy->load(['leaveType', 'entitlements.leaveType']);
        $leavePolicy->loadCount('employees');
        return view('hr.leave-policies.show', compact('leavePolicy'));
    }

    public function edit(HrLeavePolicy $leavePolicy)
    {
        $leavePolicy->load('entitlements.leaveType');
        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('name')->get();
        return view('hr.leave-policies.form', ['policy' => $leavePolicy, 'leaveTypes' => $leaveTypes]);
    }

    public function update(Request $request, HrLeavePolicy $leavePolicy)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_leave_policies,code,' . $leavePolicy->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'leave_entitlements' => 'required|array|min:1',
            'leave_entitlements.*.leave_type_id' => 'required|exists:hr_leave_types,id',
            'leave_entitlements.*.annual_entitlement' => 'required|numeric|min:0|max:365',
            'leave_entitlements.*.max_accumulation' => 'nullable|numeric|min:0|max:365',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $request, $leavePolicy) {
            $leavePolicy->update([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $leavePolicy->details()->delete();

            foreach ($validated['leave_entitlements'] as $row) {
                HrLeavePolicyDetail::create([
                    'hr_leave_policy_id' => $leavePolicy->id,
                    'hr_leave_type_id' => $row['leave_type_id'],
                    'days_per_year' => $row['annual_entitlement'],
                    'max_carry_forward' => $row['max_accumulation'] ?? 0,
                    'allow_encashment' => false,
                ]);
            }
        });

        return redirect()->route('hr.leave-policies.index')
                         ->with('success', 'Leave policy updated successfully.');
    }

    public function destroy(HrLeavePolicy $leavePolicy)
    {
        if ($leavePolicy->employees()->exists()) {
            return back()->with('error', 'Cannot delete policy. It is assigned to employees.');
        }

        $leavePolicy->delete();

        return redirect()->route('hr.leave-policies.index')
                         ->with('success', 'Leave policy deleted successfully.');
    }
}
