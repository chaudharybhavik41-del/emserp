<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrSalaryAdvance;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrSalaryAdvanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $query = HrSalaryAdvance::with('employee')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('hr_employee_id', $request->integer('employee_id'));
        }

        $advances = $query->paginate(20)->withQueryString();
        $employees = HrEmployee::active()->orderBy('employee_code')->get(['id', 'employee_code', 'first_name', 'last_name']);

        return view('hr.advances.salary-advances.index', compact('advances', 'employees'));
    }

    public function create(): View
    {
        $employees = HrEmployee::active()->orderBy('employee_code')->get();
        return view('hr.advances.salary-advances.form', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $approvedAmount = 0;
        $monthlyDeduction = 0;

        HrSalaryAdvance::create([
            'advance_number' => HrSalaryAdvance::generateNumber(),
            'hr_employee_id' => $validated['hr_employee_id'],
            'application_date' => $validated['application_date'],
            'requested_amount' => $validated['requested_amount'],
            'approved_amount' => $approvedAmount,
            'disbursed_amount' => 0,
            'purpose' => $validated['purpose'],
            'recovery_months' => $validated['recovery_months'],
            'monthly_deduction' => $monthlyDeduction,
            'recovered_amount' => 0,
            'balance_amount' => 0,
            'status' => 'applied',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hr.advances.salary-advances.index')
            ->with('success', 'Salary advance application created successfully.');
    }

    public function show(HrSalaryAdvance $advance): View
    {
        $advance->load('employee');

        return view('hr.advances.salary-advances.show', compact('advance'));
    }

    public function edit(HrSalaryAdvance $advance): View
    {
        $employees = HrEmployee::active()->orderBy('employee_code')->get();

        return view('hr.advances.salary-advances.form', compact('advance', 'employees'));
    }

    public function update(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $validated = $this->validateData($request);

        $advance->update([
            'hr_employee_id' => $validated['hr_employee_id'],
            'application_date' => $validated['application_date'],
            'requested_amount' => $validated['requested_amount'],
            'purpose' => $validated['purpose'],
            'recovery_months' => $validated['recovery_months'],
        ]);

        return redirect()->route('hr.advances.salary-advances.show', $advance)
            ->with('success', 'Salary advance updated successfully.');
    }

    public function destroy(HrSalaryAdvance $advance): RedirectResponse
    {
        if ($advance->recovered_amount > 0) {
            return back()->with('error', 'Cannot delete advance after recovery has started.');
        }

        $advance->delete();

        return redirect()->route('hr.advances.salary-advances.index')
            ->with('success', 'Salary advance deleted successfully.');
    }

    public function approve(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $validated = $request->validate([
            'approved_amount' => 'nullable|numeric|min:0',
            'recovery_months' => 'nullable|integer|min:1|max:60',
        ]);

        $approved = (float) ($validated['approved_amount'] ?? $advance->requested_amount);
        $months = (int) ($validated['recovery_months'] ?? $advance->recovery_months ?: 1);

        $advance->update([
            'approved_amount' => $approved,
            'recovery_months' => $months,
            'monthly_deduction' => round($approved / max(1, $months), 2),
            'balance_amount' => $approved,
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Salary advance approved successfully.');
    }

    public function reject(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $advance->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Salary advance rejected successfully.');
    }

    public function disburse(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        if (!in_array($advance->status, ['approved', 'disbursed', 'recovering'], true)) {
            return back()->with('error', 'Only approved advances can be disbursed.');
        }

        $validated = $request->validate([
            'disbursed_amount' => 'nullable|numeric|min:0',
            'recovery_start_date' => 'nullable|date',
        ]);

        $amount = (float) ($validated['disbursed_amount'] ?? $advance->approved_amount ?: $advance->requested_amount);

        $advance->update([
            'disbursed_amount' => $amount,
            'balance_amount' => $amount - (float) $advance->recovered_amount,
            'status' => 'recovering',
            'recovery_start_date' => isset($validated['recovery_start_date'])
                ? Carbon::parse($validated['recovery_start_date'])->toDateString()
                : now()->startOfMonth()->addMonth()->toDateString(),
        ]);

        return back()->with('success', 'Salary advance disbursed successfully.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'application_date' => 'required|date',
            'requested_amount' => 'required|numeric|min:0',
            'purpose' => 'required|string|max:1000',
            'recovery_months' => 'required|integer|min:1|max:60',
        ]);
    }
}
