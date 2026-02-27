<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrLeaveType;
use Illuminate\Http\Request;

class HrLeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrLeaveType::query();

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

        $leaveTypes = $query->orderBy('sort_order')
                            ->orderBy('name')
                            ->paginate(20)
                            ->withQueryString();

        return view('hr.leave-types.index', compact('leaveTypes'));
    }

    public function create()
    {
        return view('hr.leave-types.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_leave_types,code',
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'is_paid' => 'boolean',
            'is_encashable' => 'boolean',
            'is_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'nullable|numeric|min:0',
            'carry_forward_expiry_months' => 'nullable|integer|min:0',
            'is_document_required' => 'boolean',
            'document_required_after_days' => 'nullable|integer|min:1',
            'allow_half_day' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'max_negative_days' => 'nullable|numeric|min:0',
            'min_days_per_request' => 'nullable|numeric|min:0.5',
            'max_days_per_request' => 'nullable|numeric|min:0.5',
            'notice_days_required' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'gender_specific' => 'nullable|in:male,female',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_paid'] = $request->boolean('is_paid', true);
        $validated['is_encashable'] = $request->boolean('is_encashable', false);
        $validated['is_carry_forward'] = $request->boolean('is_carry_forward', false);
        $validated['is_document_required'] = $request->boolean('is_document_required', false);
        $validated['allow_half_day'] = $request->boolean('allow_half_day', true);
        $validated['allow_negative_balance'] = $request->boolean('allow_negative_balance', false);

        HrLeaveType::create($validated);

        return redirect()->route('hr.leave-types.index')
                         ->with('success', 'Leave type created successfully.');
    }

    public function edit(HrLeaveType $leaveType)
    {
        return view('hr.leave-types.form', compact('leaveType'));
    }

    public function update(Request $request, HrLeaveType $leaveType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:hr_leave_types,code,' . $leaveType->id,
            'name' => 'required|string|max:100',
            'short_name' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'is_paid' => 'boolean',
            'is_encashable' => 'boolean',
            'is_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'nullable|numeric|min:0',
            'carry_forward_expiry_months' => 'nullable|integer|min:0',
            'is_document_required' => 'boolean',
            'document_required_after_days' => 'nullable|integer|min:1',
            'allow_half_day' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'max_negative_days' => 'nullable|numeric|min:0',
            'min_days_per_request' => 'nullable|numeric|min:0.5',
            'max_days_per_request' => 'nullable|numeric|min:0.5',
            'notice_days_required' => 'nullable|integer|min:0',
            'max_consecutive_days' => 'nullable|integer|min:1',
            'gender_specific' => 'nullable|in:male,female',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_paid'] = $request->boolean('is_paid', true);
        $validated['is_encashable'] = $request->boolean('is_encashable', false);
        $validated['is_carry_forward'] = $request->boolean('is_carry_forward', false);
        $validated['is_document_required'] = $request->boolean('is_document_required', false);
        $validated['allow_half_day'] = $request->boolean('allow_half_day', true);
        $validated['allow_negative_balance'] = $request->boolean('allow_negative_balance', false);

        $leaveType->update($validated);

        return redirect()->route('hr.leave-types.index')
                         ->with('success', 'Leave type updated successfully.');
    }

    public function destroy(HrLeaveType $leaveType)
    {
        if ($leaveType->leaveApplications()->exists()) {
            return back()->with('error', 'Cannot delete leave type. It has leave applications.');
        }

        $leaveType->delete();

        return redirect()->route('hr.leave-types.index')
                         ->with('success', 'Leave type deleted successfully.');
    }
}
