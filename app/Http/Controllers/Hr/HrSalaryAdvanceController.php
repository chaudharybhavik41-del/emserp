<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Hr\Concerns\AuthorizesEmployeeWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrPayroll;
use App\Models\Hr\HrSalaryAdvance;
use App\Services\Accounting\HrEmployeeFinancePostingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\View\View;

class HrSalaryAdvanceController extends Controller
{
    use AuthorizesEmployeeWorkspace;

    public function __construct(
        protected HrEmployeeFinancePostingService $financePostingService
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.employee.view')->only(['index']);
        $this->middleware('permission:hr.employee.update')->only([
            'create',
            'store',
            'edit',
            'update',
            'destroy',
            'approve',
            'reject',
            'disburse',
            'cancel',
        ]);
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
        $employee = HrEmployee::find($advance->hr_employee_id);
        $this->authorizeEmployeeRead($employee);
        $advance->setRelation('employee', $employee);

        $advance->load('employee');

        return view('hr.advances.salary-advances.show', compact('advance'));
    }

    public function edit(HrSalaryAdvance $advance): View
    {
        if (! $this->canEditAdvance($advance)) {
            abort(403, 'This salary advance can no longer be edited. Use cancel/reversal instead.');
        }

        $employees = HrEmployee::active()->orderBy('employee_code')->get();

        return view('hr.advances.salary-advances.form', compact('advance', 'employees'));
    }

    public function update(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        if (! $this->canEditAdvance($advance)) {
            return redirect()
                ->route('hr.advances.salary-advances.show', $advance)
                ->with('error', 'This salary advance can no longer be edited. Use cancel/reversal instead.');
        }

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
        $advance->loadMissing('disbursementVoucher');

        if ($advance->disbursementVoucher && ! $advance->disbursementVoucher->isReversed()) {
            return back()->with('error', 'Cannot delete a disbursed salary advance. Cancel it first so accounting can be reversed safely.');
        }

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

        $advance->loadMissing('disbursementVoucher');

        if ($advance->disbursementVoucher && ! $advance->disbursementVoucher->isReversed()) {
            return back()->with('error', 'This salary advance has already been disbursed and posted to accounts. Cancel it first before disbursing again.');
        }

        $validated = $request->validate([
            'disbursed_amount' => 'nullable|numeric|min:0',
            'disbursement_date' => 'nullable|date',
            'recovery_start_date' => 'nullable|date',
        ]);

        $amount = (float) ($validated['disbursed_amount'] ?? $advance->approved_amount ?: $advance->requested_amount);

        try {
            DB::transaction(function () use ($advance, $amount, $validated) {
                $advance->update([
                    'company_id' => $advance->company_id ?: $advance->employee?->company_id,
                    'disbursed_amount' => $amount,
                    'disbursement_date' => isset($validated['disbursement_date'])
                        ? Carbon::parse($validated['disbursement_date'])->toDateString()
                        : now()->toDateString(),
                    'balance_amount' => $amount - (float) $advance->recovered_amount,
                    'status' => 'recovering',
                    'recovery_start_date' => isset($validated['recovery_start_date'])
                        ? Carbon::parse($validated['recovery_start_date'])->toDateString()
                        : now()->startOfMonth()->addMonth()->toDateString(),
                ]);

                $this->financePostingService->postSalaryAdvanceDisbursement($advance->fresh(['employee']));
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Salary advance disbursement failed because accounts posting could not be completed: ' . $e->getMessage());
        }

        return back()->with('success', 'Salary advance disbursed successfully.');
    }

    public function cancel(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
            'reversal_date' => 'required|date',
        ]);

        if (in_array((string) $advance->status, ['cancelled', 'closed'], true)) {
            return back()->with('error', 'This salary advance cannot be cancelled in its current state.');
        }

        if ((float) $advance->recovered_amount > 0) {
            return back()->with('error', 'Cannot cancel a salary advance after recovery has started.');
        }

        try {
            DB::transaction(function () use ($advance, $validated): void {
                $lockedAdvance = HrSalaryAdvance::query()
                    ->with('employee')
                    ->lockForUpdate()
                    ->findOrFail($advance->id);

                if ((float) $lockedAdvance->recovered_amount > 0) {
                    throw new \RuntimeException('Cannot cancel a salary advance after recovery has started.');
                }

                if ($this->hasPayrollLinkedAdvanceRecovery($lockedAdvance)) {
                    throw new \RuntimeException('Cannot cancel a salary advance already linked to approved payroll. Undo payroll first.');
                }

                if (! empty($lockedAdvance->disbursement_voucher_id)) {
                    $this->financePostingService->reverseSalaryAdvanceDisbursement(
                        $lockedAdvance,
                        $validated['reversal_date'],
                        $validated['cancellation_reason']
                    );
                }

                $lockedAdvance->update([
                    'balance_amount' => 0,
                    'status' => 'cancelled',
                ]);
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to cancel salary advance: ' . $e->getMessage());
        }

        return back()->with('success', 'Salary advance cancelled and accounting reversed successfully.');
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

    private function canEditAdvance(HrSalaryAdvance $advance): bool
    {
        return in_array((string) $advance->status, ['applied', 'rejected'], true)
            && empty($advance->disbursement_voucher_id)
            && (float) $advance->recovered_amount <= 0;
    }

    private function hasPayrollLinkedAdvanceRecovery(HrSalaryAdvance $advance): bool
    {
        $recoveryStartDate = $advance->recovery_start_date?->toDateString()
            ?? $advance->disbursement_date?->toDateString()
            ?? $advance->approved_at?->toDateString()
            ?? $advance->application_date?->toDateString();

        return HrPayroll::query()
            ->join('hr_payroll_periods', 'hr_payroll_periods.id', '=', 'hr_payrolls.hr_payroll_period_id')
            ->where('hr_payrolls.hr_employee_id', $advance->hr_employee_id)
            ->whereIn('hr_payrolls.status', ['approved', 'paid'])
            ->where('hr_payrolls.advance_deduction', '>', 0)
            ->when($recoveryStartDate, function ($query, $date): void {
                $query->whereDate('hr_payroll_periods.period_end', '>=', $date);
            })
            ->exists();
    }
}
