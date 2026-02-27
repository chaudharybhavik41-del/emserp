<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrLeaveType;
use App\Models\Hr\HrEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrLeaveApplicationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = HrLeaveApplication::with(['employee', 'leaveType', 'approvedBy']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('hr_leave_type_id')) {
            $query->where('hr_leave_type_id', $request->hr_leave_type_id);
        }

        $applications = $query->orderByDesc('created_at')
                              ->paginate(20)
                              ->withQueryString();

        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave-applications.index', compact('applications', 'leaveTypes'));
    }

    public function create()
    {
        $employees = HrEmployee::where('employment_status', 'active')
                               ->orderBy('first_name')
                               ->get();
        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave-applications.form', compact('employees', 'leaveTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'hr_leave_type_id' => 'required|exists:hr_leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'from_session' => 'nullable|in:first_half,second_half,full_day',
            'to_session' => 'nullable|in:first_half,second_half,full_day',
            'reason' => 'required|string|max:1000',
            'contact_during_leave' => 'nullable|string|max:20',
            'handover_to' => 'nullable|exists:hr_employees,id',
        ]);

        // Calculate days
        $fromDate = \Carbon\Carbon::parse($validated['from_date']);
        $toDate = \Carbon\Carbon::parse($validated['to_date']);
        $days = $fromDate->diffInDays($toDate) + 1;

        // Adjust for half days
        if ($validated['from_session'] === 'second_half') {
            $days -= 0.5;
        }
        if ($validated['to_session'] === 'first_half') {
            $days -= 0.5;
        }

        $validated['total_days'] = $days;
        $validated['status'] = 'pending';
        $validated['applied_by'] = Auth::id();

        HrLeaveApplication::create($validated);

        return redirect()->route('hr.leave-applications.index')
                         ->with('success', 'Leave application submitted successfully.');
    }

    public function show(HrLeaveApplication $leaveApplication)
    {
        $leaveApplication->load(['employee', 'leaveType', 'approvedBy', 'appliedByUser']);
        return view('hr.leave-applications.show', ['application' => $leaveApplication]);
    }

    public function edit(HrLeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Cannot edit application. It has already been processed.');
        }

        $employees = HrEmployee::where('employment_status', 'active')
                               ->orderBy('first_name')
                               ->get();
        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('name')->get();

        return view('hr.leave-applications.form', [
            'application' => $leaveApplication,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function update(Request $request, HrLeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Cannot update application. It has already been processed.');
        }

        $validated = $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'hr_leave_type_id' => 'required|exists:hr_leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'from_session' => 'nullable|in:first_half,second_half,full_day',
            'to_session' => 'nullable|in:first_half,second_half,full_day',
            'reason' => 'required|string|max:1000',
            'contact_during_leave' => 'nullable|string|max:20',
            'handover_to' => 'nullable|exists:hr_employees,id',
        ]);

        // Calculate days
        $fromDate = \Carbon\Carbon::parse($validated['from_date']);
        $toDate = \Carbon\Carbon::parse($validated['to_date']);
        $days = $fromDate->diffInDays($toDate) + 1;

        if ($validated['from_session'] === 'second_half') {
            $days -= 0.5;
        }
        if ($validated['to_session'] === 'first_half') {
            $days -= 0.5;
        }

        $validated['total_days'] = $days;

        $leaveApplication->update($validated);

        return redirect()->route('hr.leave-applications.index')
                         ->with('success', 'Leave application updated successfully.');
    }

    public function destroy(HrLeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Cannot delete application. It has already been processed.');
        }

        $leaveApplication->delete();

        return redirect()->route('hr.leave-applications.index')
                         ->with('success', 'Leave application deleted successfully.');
    }

    public function approve(Request $request, HrLeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Application has already been processed.');
        }

        $leaveApplication->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_remarks' => $request->get('remarks'),
        ]);

        return back()->with('success', 'Leave application approved successfully.');
    }

    public function reject(Request $request, HrLeaveApplication $leaveApplication)
    {
        if ($leaveApplication->status !== 'pending') {
            return back()->with('error', 'Application has already been processed.');
        }

        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $leaveApplication->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_remarks' => $request->get('remarks'),
        ]);

        return back()->with('success', 'Leave application rejected.');
    }

    public function cancel(HrLeaveApplication $leaveApplication)
    {
        if (!in_array($leaveApplication->status, ['pending', 'approved'])) {
            return back()->with('error', 'Cannot cancel this application.');
        }

        $leaveApplication->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', 'Leave application cancelled.');
    }
}
