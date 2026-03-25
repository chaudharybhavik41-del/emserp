<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Hr\Concerns\AuthorizesEmployeeWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeLoan;
use App\Models\Hr\HrLoanRepayment;
use App\Models\Hr\HrLoanType;
use App\Services\Accounting\HrEmployeeFinancePostingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\View\View;

class HrEmployeeLoanController extends Controller
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
        $query = HrEmployeeLoan::with(['employee', 'loanType'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('hr_employee_id', $request->integer('employee_id'));
        }

        $loans = $query->paginate(20)->withQueryString();
        $employees = HrEmployee::active()->orderBy('employee_code')->get(['id', 'employee_code', 'first_name', 'last_name']);

        return view('hr.loans.employee-loans.index', compact('loans', 'employees'));
    }

    public function create(): View
    {
        $employees = HrEmployee::active()->orderBy('employee_code')->get();
        $loanTypes = HrLoanType::where('is_active', true)->orderBy('name')->get();

        return view('hr.loans.employee-loans.form', compact('employees', 'loanTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $loanType = HrLoanType::findOrFail($validated['hr_loan_type_id']);
        $approvedAmount = $validated['applied_amount'];
        $emi = $this->calculateEmi($approvedAmount, $validated['tenure_months'], $validated['interest_rate'] ?? (float) $loanType->interest_rate);

        $loan = HrEmployeeLoan::create([
            'loan_number' => HrEmployeeLoan::generateNumber(),
            'hr_employee_id' => $validated['hr_employee_id'],
            'hr_loan_type_id' => $validated['hr_loan_type_id'],
            'application_date' => $validated['application_date'],
            'applied_amount' => $validated['applied_amount'],
            'approved_amount' => 0,
            'disbursed_amount' => 0,
            'tenure_months' => $validated['tenure_months'],
            'interest_rate' => $validated['interest_rate'] ?? $loanType->interest_rate,
            'emi_amount' => $emi,
            'principal_outstanding' => 0,
            'interest_outstanding' => 0,
            'total_outstanding' => 0,
            'total_recovered' => 0,
            'emis_paid' => 0,
            'emis_pending' => $validated['tenure_months'],
            'status' => 'pending_approval',
            'purpose' => $validated['purpose'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hr.loans.employee-loans.show', $loan)
            ->with('success', 'Loan application created successfully.');
    }

    public function show(HrEmployeeLoan $loan): View
    {
        $employee = HrEmployee::find($loan->hr_employee_id);
        $this->authorizeEmployeeRead($employee);
        $loan->setRelation('employee', $employee);

        $loan->load(['employee', 'loanType', 'repayments']);

        return view('hr.loans.employee-loans.show', compact('loan'));
    }

    public function edit(HrEmployeeLoan $loan): View
    {
        if (! $this->canEditLoan($loan)) {
            abort(403, 'This loan can no longer be edited. Use cancellation/reversal instead.');
        }

        $employees = HrEmployee::active()->orderBy('employee_code')->get();
        $loanTypes = HrLoanType::where('is_active', true)->orderBy('name')->get();

        return view('hr.loans.employee-loans.form', compact('loan', 'employees', 'loanTypes'));
    }

    public function update(Request $request, HrEmployeeLoan $loan): RedirectResponse
    {
        if (! $this->canEditLoan($loan)) {
            return redirect()
                ->route('hr.loans.employee-loans.show', $loan)
                ->with('error', 'This loan can no longer be edited. Use cancellation/reversal instead.');
        }

        $validated = $this->validateData($request);

        $emi = $this->calculateEmi($validated['applied_amount'], $validated['tenure_months'], $validated['interest_rate'] ?? 0);

        $loan->update([
            'hr_employee_id' => $validated['hr_employee_id'],
            'hr_loan_type_id' => $validated['hr_loan_type_id'],
            'application_date' => $validated['application_date'],
            'applied_amount' => $validated['applied_amount'],
            'tenure_months' => $validated['tenure_months'],
            'interest_rate' => $validated['interest_rate'] ?? $loan->interest_rate,
            'emi_amount' => $emi,
            'purpose' => $validated['purpose'] ?? null,
        ]);

        return redirect()->route('hr.loans.employee-loans.show', $loan)
            ->with('success', 'Loan updated successfully.');
    }

    public function destroy(HrEmployeeLoan $loan): RedirectResponse
    {
        $loan->loadMissing('disbursementVoucher');

        if ($loan->disbursementVoucher && ! $loan->disbursementVoucher->isReversed()) {
            return back()->with('error', 'Cannot delete a disbursed loan. Cancel it first so accounting can be reversed safely.');
        }

        if ($loan->repayments()->where('status', 'paid')->exists()) {
            return back()->with('error', 'Cannot delete loan with paid installments.');
        }

        $loan->repayments()->delete();
        $loan->delete();

        return redirect()->route('hr.loans.employee-loans.index')
            ->with('success', 'Loan deleted successfully.');
    }

    public function approve(Request $request, HrEmployeeLoan $loan): RedirectResponse
    {
        $validated = $request->validate([
            'approved_amount' => 'nullable|numeric|min:0',
            'approval_remarks' => 'nullable|string|max:1000',
        ]);

        $approvedAmount = $validated['approved_amount'] ?? (float) $loan->applied_amount;

        $loan->update([
            'approved_amount' => $approvedAmount,
            'approved_date' => now()->toDateString(),
            'principal_outstanding' => $approvedAmount,
            'total_outstanding' => $approvedAmount,
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approval_remarks' => $validated['approval_remarks'] ?? null,
        ]);

        return back()->with('success', 'Loan approved successfully.');
    }

    public function reject(Request $request, HrEmployeeLoan $loan): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $loan->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_date' => now()->toDateString(),
        ]);

        return back()->with('success', 'Loan rejected successfully.');
    }

    public function disburse(Request $request, HrEmployeeLoan $loan): RedirectResponse
    {
        if (!in_array($loan->status, ['approved', 'disbursed', 'active'], true)) {
            return back()->with('error', 'Only approved loans can be disbursed.');
        }

        $loan->loadMissing('disbursementVoucher');

        if ($loan->disbursementVoucher && ! $loan->disbursementVoucher->isReversed()) {
            return back()->with('error', 'This employee loan has already been disbursed and posted to accounts. Cancel it first before disbursing again.');
        }

        $validated = $request->validate([
            'disbursed_amount' => 'nullable|numeric|min:0',
            'disbursement_date' => 'nullable|date',
            'emi_start_date' => 'nullable|date',
        ]);

        $amount = $validated['disbursed_amount'] ?? ((float) $loan->approved_amount ?: (float) $loan->applied_amount);
        $emiStart = isset($validated['emi_start_date'])
            ? Carbon::parse($validated['emi_start_date'])
            : now()->startOfMonth()->addMonth();

        try {
            DB::transaction(function () use ($loan, $amount, $emiStart, $validated) {
                $loan->update([
                    'company_id' => $loan->company_id ?: $loan->employee?->company_id,
                    'disbursed_amount' => $amount,
                    'disbursement_date' => isset($validated['disbursement_date'])
                        ? Carbon::parse($validated['disbursement_date'])->toDateString()
                        : now()->toDateString(),
                    'emi_start_date' => $emiStart->toDateString(),
                    'emi_end_date' => $emiStart->copy()->addMonths(max(1, (int) $loan->tenure_months) - 1)->endOfMonth()->toDateString(),
                    'principal_outstanding' => $amount,
                    'interest_outstanding' => 0,
                    'total_outstanding' => $amount,
                    'emis_paid' => 0,
                    'emis_pending' => $loan->tenure_months,
                    'status' => 'active',
                ]);

                if (! $loan->repayments()->exists()) {
                    $this->createSchedule($loan, $emiStart);
                }

                $this->financePostingService->postLoanDisbursement($loan->fresh(['employee']));
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Loan disbursement failed because accounts posting could not be completed: ' . $e->getMessage());
        }

        return back()->with('success', 'Loan disbursed and repayment schedule created.');
    }

    public function cancel(Request $request, HrEmployeeLoan $loan): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
            'reversal_date' => 'required|date',
        ]);

        if (in_array((string) $loan->status, ['cancelled', 'closed', 'written_off'], true)) {
            return back()->with('error', 'This loan cannot be cancelled in its current state.');
        }

        try {
            DB::transaction(function () use ($loan, $validated): void {
                $lockedLoan = HrEmployeeLoan::query()
                    ->with(['employee', 'repayments'])
                    ->lockForUpdate()
                    ->findOrFail($loan->id);

                if ($lockedLoan->repayments()->where('status', 'paid')->exists()) {
                    throw new \RuntimeException('Cannot cancel a loan with paid installments.');
                }

                if ($lockedLoan->repayments()->whereNotNull('hr_payroll_id')->exists()) {
                    throw new \RuntimeException('Cannot cancel a loan already linked to payroll. Undo payroll first.');
                }

                if (! empty($lockedLoan->disbursement_voucher_id)) {
                    $this->financePostingService->reverseLoanDisbursement(
                        $lockedLoan,
                        $validated['reversal_date'],
                        $validated['cancellation_reason']
                    );
                }

                $lockedLoan->repayments()->delete();

                $lockedLoan->update([
                    'principal_outstanding' => 0,
                    'interest_outstanding' => 0,
                    'total_outstanding' => 0,
                    'emis_pending' => 0,
                    'status' => 'cancelled',
                ]);
            });
        } catch (Throwable $e) {
            return back()->with('error', 'Failed to cancel loan: ' . $e->getMessage());
        }

        return back()->with('success', 'Loan cancelled and accounting reversed successfully.');
    }

    public function schedule(HrEmployeeLoan $loan): View
    {
        $employee = HrEmployee::find($loan->hr_employee_id);
        $this->authorizeEmployeeRead($employee);
        $loan->setRelation('employee', $employee);

        $loan->load(['employee', 'loanType', 'repayments']);

        return view('hr.loans.employee-loans.schedule', compact('loan'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'hr_loan_type_id' => 'required|exists:hr_loan_types,id',
            'application_date' => 'required|date',
            'applied_amount' => 'required|numeric|min:0',
            'tenure_months' => 'required|integer|min:1|max:240',
            'interest_rate' => 'nullable|numeric|min:0|max:60',
            'purpose' => 'nullable|string|max:1000',
        ]);
    }

    private function calculateEmi(float $principal, int $months, float $annualRate): float
    {
        if ($months <= 0) {
            return 0;
        }

        if ($annualRate <= 0) {
            return round($principal / $months, 2);
        }

        $monthlyRate = ($annualRate / 12) / 100;
        $factor = pow(1 + $monthlyRate, $months);

        if ($factor <= 1) {
            return round($principal / $months, 2);
        }

        return round(($principal * $monthlyRate * $factor) / ($factor - 1), 2);
    }

    private function createSchedule(HrEmployeeLoan $loan, Carbon $emiStart): void
    {
        $opening = (float) $loan->disbursed_amount;
        $rate = ((float) $loan->interest_rate) / 100 / 12;

        for ($i = 1; $i <= (int) $loan->tenure_months; $i++) {
            $interest = $rate > 0 ? round($opening * $rate, 2) : 0;
            $principal = max(0, round((float) $loan->emi_amount - $interest, 2));
            if ($i === (int) $loan->tenure_months) {
                $principal = $opening;
            }

            $closing = max(0, round($opening - $principal, 2));

            HrLoanRepayment::create([
                'hr_employee_loan_id' => $loan->id,
                'installment_no' => $i,
                'due_date' => $emiStart->copy()->addMonths($i - 1)->endOfMonth()->toDateString(),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'emi_amount' => round($principal + $interest, 2),
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'paid_amount' => 0,
                'status' => 'pending',
            ]);

            $opening = $closing;
        }
    }

    private function canEditLoan(HrEmployeeLoan $loan): bool
    {
        return in_array((string) $loan->status, ['applied', 'pending_approval', 'rejected'], true)
            && empty($loan->disbursement_voucher_id)
            && ! $loan->repayments()->exists();
    }
}
