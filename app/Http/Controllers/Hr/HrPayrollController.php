<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Hr\Concerns\AuthorizesEmployeeWorkspace;
use App\Services\Accounting\HrPayrollPostingService;
use App\Models\Department;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeLoan;
use App\Models\Hr\HrEmployeeSalary;
use App\Models\Hr\HrEsiSlab;
use App\Models\Hr\HrLoanRepayment;
use App\Models\Hr\HrPayroll;
use App\Models\Hr\HrPayrollBatch;
use App\Models\Hr\HrPayrollComponent;
use App\Models\Hr\HrPayrollPeriod;
use App\Models\Hr\HrPfSlab;
use App\Models\Hr\HrOvertimeRecord;
use App\Models\Hr\HrProfessionalTaxSlab;
use App\Models\Hr\HrSalaryAdvance;
use App\Models\Hr\HrSalaryComponent;
use App\Models\Hr\HrTaxDeclaration;
use App\Models\Hr\HrTdsSlab;
use App\Models\Hr\HrLwfSlab;
use App\Enums\Hr\AttendanceStatus;
use App\Enums\Hr\PayrollStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrPayrollController extends Controller
{
    use AuthorizesEmployeeWorkspace;

    public function __construct(
        protected HrPayrollPostingService $hrPayrollPostingService
    ) {
        $this->middleware('auth');

        // Core viewing
        $this->middleware('permission:hr.payroll.view')->only([
            'index',
            'period',

            // Reports
            'bankStatement',
            'pfReport',
            'esiReport',
            'ptReport',
            'tdsReport',
            'salaryRegister',
            'departmentWise',
        ]);

        // Period creation
        $this->middleware('permission:hr.payroll.create')->only([
            'create',
            'store',
            'createPeriod',
        ]);

        // Processing
        $this->middleware('permission:hr.payroll.process')->only([
            'process',
            'lockAttendance',
        ]);

        // Approvals & payment
        $this->middleware('permission:hr.payroll.approve')->only([
            'approve',
            'bulkApprove',
            'unapprove',
        ]);

        $this->middleware('permission:hr.payroll.pay')->only([
            'pay',
            'bulkPay',
            'unpay',
        ]);

        // Hold / release
        $this->middleware('permission:hr.payroll.hold')->only(['hold']);
        $this->middleware('permission:hr.payroll.release')->only(['release']);

        // Close period
        $this->middleware('permission:hr.payroll.update')->only(['closePeriod']);
    }

    public function index(Request $request): View
    {
        $query = HrPayrollPeriod::withCount('payrolls')
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        $periods = $query->paginate(12)->withQueryString();

        // Summary
        $currentPeriod = HrPayrollPeriod::where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $summary = [
            'current_period' => $currentPeriod,
            'total_processed' => $currentPeriod ? HrPayroll::where('hr_payroll_period_id', $currentPeriod->id)->count() : 0,
            'total_paid' => $currentPeriod ? HrPayroll::where('hr_payroll_period_id', $currentPeriod->id)->where('status', PayrollStatus::PAID->value)->count() : 0,
            'total_net_pay' => $currentPeriod ? HrPayroll::where('hr_payroll_period_id', $currentPeriod->id)->sum('net_payable') : 0,
        ];

        $years = HrPayrollPeriod::distinct()->pluck('year');

        return view('hr.payroll.index', compact('periods', 'summary', 'years'));
    }

    public function period(HrPayrollPeriod $period): View
    {
        $period->load('payrolls.employee');

        $payrolls = HrPayroll::with(['employee.department', 'employee.designation'])
            ->where('hr_payroll_period_id', $period->id)
            ->orderBy('employee_code')
            ->paginate(50);

        // Summary
        $summary = [
            'total_employees' => $payrolls->total(),
            'total_gross' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('gross_salary'),
            'total_deductions' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('total_deductions'),
            'total_net' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('net_payable'),
            'total_pf' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('pf_employee'),
            'total_esi' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('esi_employee'),
            'total_tds' => HrPayroll::where('hr_payroll_period_id', $period->id)->sum('tds'),
            'by_status' => HrPayroll::where('hr_payroll_period_id', $period->id)
                ->selectRaw('status, COUNT(*) as count, SUM(net_payable) as total')
                ->groupBy('status')
                ->get(),
        ];

        return view('hr.payroll.period', compact('period', 'payrolls', 'summary'));
    }

    public function createPeriod(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'year' => 'required|integer|min:2020|max:2099',
                'month' => 'required|integer|min:1|max:12',
                'period_start' => 'nullable|date',
                'period_end' => 'nullable|date|after_or_equal:period_start',
                'attendance_start' => 'nullable|date',
                'attendance_end' => 'nullable|date|after_or_equal:attendance_start',
                'working_days' => 'nullable|integer|min:0|max:31',
                'payment_date' => 'nullable|date',
                'remarks' => 'nullable|string|max:1000',
            ]);

            // Check if already exists
            $existing = HrPayrollPeriod::where('year', $validated['year'])
                ->where('month', $validated['month'])
                ->first();

            if ($existing) {
                return back()->with('error', 'Payroll period already exists for this month.');
            }

            $defaultStartDate = Carbon::createFromDate($validated['year'], $validated['month'], 1)->startOfDay();
            $defaultEndDate = $defaultStartDate->copy()->endOfMonth()->startOfDay();
            $startDate = !empty($validated['period_start'])
                ? Carbon::parse($validated['period_start'])->startOfDay()
                : $defaultStartDate;
            $endDate = !empty($validated['period_end'])
                ? Carbon::parse($validated['period_end'])->startOfDay()
                : $defaultEndDate;
            $attendanceStart = !empty($validated['attendance_start'])
                ? Carbon::parse($validated['attendance_start'])->startOfDay()
                : $startDate->copy();
            $attendanceEnd = !empty($validated['attendance_end'])
                ? Carbon::parse($validated['attendance_end'])->startOfDay()
                : $endDate->copy();
            $workingDays = array_key_exists('working_days', $validated) && $validated['working_days'] !== null
                ? (int) $validated['working_days']
                : $startDate->diffInWeekdays($endDate) + 1;
            $totalDays = $startDate->diffInDays($endDate) + 1;
            $weekOffs = max(0, $totalDays - ($startDate->diffInWeekdays($endDate) + 1));

            $period = HrPayrollPeriod::create([
                'company_id' => 1,
                'period_code' => 'PP-' . $validated['year'] . '-' . str_pad($validated['month'], 2, '0', STR_PAD_LEFT),
                'name' => $startDate->format('F Y'),
                'year' => $validated['year'],
                'month' => $validated['month'],
                'period_start' => $startDate,
                'period_end' => $endDate,
                'attendance_start' => $attendanceStart,
                'attendance_end' => $attendanceEnd,
                'payment_date' => $validated['payment_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total_days' => $totalDays,
                'working_days' => $workingDays,
                'week_offs' => $weekOffs,
                'holidays' => 0,
                'status' => 'draft',
            ]);

            return redirect()
                ->route('hr.payroll.period', $period)
                ->with('success', 'Payroll period created successfully.');
        }

        return view('hr.payroll.create-period');
    }

    public function lockAttendance(HrPayrollPeriod $period): RedirectResponse
    {
        if (in_array($period->status, ['paid', 'closed'])) {
            return back()->with('error', 'This payroll period cannot be modified.');
        }

        $period->lockAttendance();
        HrAttendance::query()
            ->whereBetween('attendance_date', [$period->attendance_start, $period->attendance_end])
            ->update(['is_locked' => true]);

        return back()->with('success', 'Attendance locked for this period.');
    }

    public function closePeriod(HrPayrollPeriod $period): RedirectResponse
    {
        if ($period->status === 'closed') {
            return back()->with('error', 'This payroll period is already closed.');
        }

        $allPaid = HrPayroll::where('hr_payroll_period_id', $period->id)
            ->where('status', '!=', PayrollStatus::PAID)
            ->doesntExist();

        if (!$allPaid) {
            return back()->with('error', 'You can close a period only after all payrolls are marked as paid.');
        }

        $period->update(['status' => 'closed']);

        return back()->with('success', 'Payroll period closed successfully.');
    }

    public function process(Request $request, HrPayrollPeriod $period): RedirectResponse
    {
        if (in_array($period->status, ['paid', 'closed'])) {
            return back()->with('error', 'This payroll period is closed/paid and cannot be processed.');
        }

        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:hr_employees,id',
        ]);

        DB::beginTransaction();
        try {
            // Mark period as "processing" (will be finalized to "processed" after commit)
            if (in_array($period->status, ['draft', 'attendance_locked', 'processed', 'processing'])) {
                $period->update(['status' => 'processing']);
            }

            $query = HrEmployee::query()
                ->whereDate('date_of_joining', '<=', $period->attendance_end)
                ->where(function (Builder $builder) use ($period): void {
                    $builder->whereNull('date_of_leaving')
                        ->orWhereDate('date_of_leaving', '>', $period->attendance_start);
                });

            if (!empty($validated['employee_ids'])) {
                $query->whereIn('id', $validated['employee_ids']);
            } elseif (!empty($validated['department_id'])) {
                $query->where('department_id', $validated['department_id']);
            }

            $employees = $query->get();
            $processed = 0;
            $errors = [];

            foreach ($employees as $employee) {
                try {
                    $this->processEmployeePayroll($employee, $period);
                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = "{$employee->employee_code}: {$e->getMessage()}";
                }
            }

            if ($processed === 0) {
                DB::rollBack();

                if (!empty($errors)) {
                    return back()->with('error', 'Payroll processing failed. ' . implode(' ', $errors));
                }

                return back()->with('error', 'No employees matched the selected payroll filters for this period.');
            }

            // Finalize period status
            $period->markAsProcessed();

            DB::commit();

            $message = "Payroll processed for {$processed} employees.";
            if (!empty($errors)) {
                $message .= ' Errors: ' . count($errors) . '. ' . implode(' ', array_slice($errors, 0, 5));
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    public function show(HrPayroll $payroll): View
    {
        $employee = HrEmployee::find($payroll->hr_employee_id);
        $this->authorizeEmployeeRead($employee, ['hr.payroll.view', 'hr.employee.view']);
        $payroll->setRelation('employee', $employee);

        $payroll->load([
            'employee',
            'period',
            'employeeSalary',
            'components' => fn($q) => $q->orderBy('component_type')->orderBy('sort_order'),
            'adjustments',
            'loanRepayments.loan',
        ]);

        return view('hr.payroll.show', compact('payroll'));
    }

    public function payslip(HrPayroll $payroll): View
    {
        $employee = HrEmployee::find($payroll->hr_employee_id);
        $this->authorizeEmployeeRead($employee, ['hr.payroll.view', 'hr.employee.view']);
        $payroll->setRelation('employee', $employee);

        $payroll->load([
            'employee.department',
            'employee.designation',
            'period',
            'components',
        ]);

        return view('hr.payroll.payslip', compact('payroll'));
    }

    public function payslipPdf(HrPayroll $payroll)
    {
        $employee = HrEmployee::find($payroll->hr_employee_id);
        $this->authorizeEmployeeRead($employee, ['hr.payroll.view', 'hr.employee.view']);
        $payroll->setRelation('employee', $employee);

        $payroll->load([
            'employee.department',
            'employee.designation',
            'period',
            'components',
        ]);

        $viewName = view()->exists('hr.payroll.payslip-pdf')
            ? 'hr.payroll.payslip-pdf'
            : 'hr.payroll.payslip';

        // If dompdf is installed, render a PDF; otherwise fall back to a print-friendly HTML view.
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewName, compact('payroll'));
            return $pdf->download("Payslip-{$payroll->payroll_number}.pdf");
        }

        return view($viewName, compact('payroll'))->with('pdf_mode', true);
    }

    public function approve(HrPayroll $payroll): RedirectResponse
    {
        $payroll->loadMissing('period');

        if ($payroll->period?->is_stale) {
            return back()->with('error', 'Payroll source data changed after processing. Reprocess the payroll period before approval.');
        }

        if (!$payroll->canApprove()) {
            return back()->with('error', 'This payroll cannot be approved in its current state.');
        }

        try {
            DB::transaction(function () use ($payroll): void {
                $lockedPayroll = HrPayroll::query()
                    ->with('period')
                    ->lockForUpdate()
                    ->findOrFail($payroll->id);

                if (!$lockedPayroll->canApprove()) {
                    throw new \RuntimeException('This payroll cannot be approved in its current state.');
                }

                $lockedPayroll->update([
                    'status' => PayrollStatus::APPROVED,
                ]);

                $this->hrPayrollPostingService->postAccrual($lockedPayroll);

                // If all payrolls are approved (or paid), mark the period as approved
                $periodId = $lockedPayroll->hr_payroll_period_id;
                if ($periodId) {
                    $allApproved = HrPayroll::where('hr_payroll_period_id', $periodId)
                        ->whereNotIn('status', [PayrollStatus::APPROVED->value, PayrollStatus::PAID->value])
                        ->doesntExist();

                    if ($allApproved) {
                        $period = HrPayrollPeriod::query()->lockForUpdate()->find($periodId);
                        if ($period && !in_array($period->status, ['paid', 'closed'])) {
                            $period->markAsApproved();
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to approve payroll: ' . $e->getMessage());
        }

        return back()->with('success', 'Payroll approved successfully.');
    }

    public function bulkApprove(Request $request, HrPayrollPeriod $period): RedirectResponse
    {
        if ($period->is_stale) {
            return back()->with('error', 'Payroll source data changed after processing. Reprocess the payroll period before approval.');
        }

        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:hr_payrolls,id',
        ]);

        $approved = 0;

        try {
            DB::transaction(function () use ($validated, $period, &$approved): void {
                $payrolls = HrPayroll::query()
                    ->where('hr_payroll_period_id', $period->id)
                    ->whereIn('id', $validated['payroll_ids'])
                    ->where('status', PayrollStatus::PROCESSED->value)
                    ->lockForUpdate()
                    ->get();

                foreach ($payrolls as $payroll) {
                    $payroll->update(['status' => PayrollStatus::APPROVED->value]);
                    $this->hrPayrollPostingService->postAccrual($payroll);
                    $approved++;
                }

                // If all payrolls in this period are approved (or paid), mark period as approved
                $allApproved = HrPayroll::where('hr_payroll_period_id', $period->id)
                    ->whereNotIn('status', [PayrollStatus::APPROVED->value, PayrollStatus::PAID->value])
                    ->doesntExist();

                if ($allApproved && !in_array($period->status, ['paid', 'closed'])) {
                    $lockedPeriod = HrPayrollPeriod::query()->lockForUpdate()->find($period->id);
                    if ($lockedPeriod) {
                        $lockedPeriod->markAsApproved();
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to approve payrolls: ' . $e->getMessage());
        }

        return back()->with('success', "{$approved} payrolls approved.");
    }

    public function pay(HrPayroll $payroll): RedirectResponse
    {
        $payroll->loadMissing('period');

        if ($payroll->period?->is_stale) {
            return back()->with('error', 'Payroll source data changed after approval. Reprocess the payroll period before payment.');
        }

        if (!$payroll->canPay()) {
            return back()->with('error', 'This payroll cannot be marked as paid.');
        }

        try {
            DB::transaction(function () use ($payroll): void {
                $lockedPayroll = HrPayroll::query()
                    ->with('period')
                    ->lockForUpdate()
                    ->findOrFail($payroll->id);

                if (!$lockedPayroll->canPay()) {
                    throw new \RuntimeException('This payroll cannot be marked as paid.');
                }

                $paymentDate = now();

                $this->hrPayrollPostingService->postAccrual($lockedPayroll);

                $lockedPayroll->update([
                    'status' => PayrollStatus::PAID->value,
                    'payment_date' => $paymentDate,
                ]);

                $this->applyLoanRecoveries($lockedPayroll, $paymentDate);
                $this->applyAdvanceRecoveries($lockedPayroll);
                $this->hrPayrollPostingService->postPayment($lockedPayroll->fresh(['period', 'employee']));

                // Update period if all payrolls are paid
                $periodId = $lockedPayroll->hr_payroll_period_id;
                if ($periodId) {
                    $allPaid = HrPayroll::where('hr_payroll_period_id', $periodId)
                        ->where('status', '!=', PayrollStatus::PAID)
                        ->doesntExist();

                    if ($allPaid) {
                        $period = HrPayrollPeriod::query()->lockForUpdate()->find($periodId);
                        if ($period && $period->status !== 'closed') {
                            $period->markAsPaid();
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to mark payroll paid: ' . $e->getMessage());
        }

        return back()->with('success', 'Payroll marked as paid.');
    }

    public function unapprove(Request $request, HrPayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'reversal_date' => 'required|date',
            'reason' => 'required|string|max:1000',
        ]);

        if ($payroll->status !== PayrollStatus::APPROVED) {
            return back()->with('error', 'Only approved payrolls can be moved back to processed.');
        }

        try {
            $lockedPayroll = HrPayroll::query()
                ->with('period')
                ->findOrFail($payroll->id);

            if ($lockedPayroll->status !== PayrollStatus::APPROVED) {
                throw new \RuntimeException('Only approved payrolls can be moved back to processed.');
            }

            if (!empty($lockedPayroll->payment_voucher_id)) {
                throw new \RuntimeException('Payroll payment exists. Undo payment first.');
            }

            $this->hrPayrollPostingService->reverseAccrual(
                $lockedPayroll,
                $validated['reversal_date'],
                $validated['reason']
            );

            DB::table('hr_payrolls')
                ->where('id', $payroll->id)
                ->update([
                    'status' => PayrollStatus::PROCESSED->value,
                ]);

            $this->refreshPeriodStatus($lockedPayroll->hr_payroll_period_id);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to rollback payroll approval: ' . $e->getMessage());
        }

        return back()->with('success', 'Payroll moved back to processed and accrual reversed.');
    }

    public function unpay(Request $request, HrPayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'reversal_date' => 'required|date',
            'reason' => 'required|string|max:1000',
        ]);

        if ($payroll->status !== PayrollStatus::PAID) {
            return back()->with('error', 'Only paid payrolls can be moved back to approved.');
        }

        try {
            DB::transaction(function () use ($payroll, $validated): void {
                $lockedPayroll = HrPayroll::query()
                    ->with(['period', 'employee'])
                    ->lockForUpdate()
                    ->findOrFail($payroll->id);

                if ($lockedPayroll->status !== PayrollStatus::PAID) {
                    throw new \RuntimeException('Only paid payrolls can be moved back to approved.');
                }

                if ($this->hasLaterPaidPayroll($lockedPayroll)) {
                    throw new \RuntimeException('A later paid payroll exists for this employee. Undo the latest payment first.');
                }

                $this->hrPayrollPostingService->reversePayment(
                    $lockedPayroll,
                    $validated['reversal_date'],
                    $validated['reason']
                );

                $this->restoreLoanRecoveries($lockedPayroll);
                $this->restoreAdvanceRecoveries($lockedPayroll);

                $lockedPayroll->update([
                    'status' => PayrollStatus::APPROVED->value,
                    'payment_date' => null,
                    'payment_reference' => null,
                ]);

                $this->refreshPeriodStatus($lockedPayroll->hr_payroll_period_id);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to rollback payroll payment: ' . $e->getMessage());
        }

        return back()->with('success', 'Payroll moved back to approved and payment reversed.');
    }

    public function bulkPay(Request $request, HrPayrollPeriod $period): RedirectResponse
    {
        if ($period->is_stale) {
            return back()->with('error', 'Payroll source data changed after approval. Reprocess the payroll period before payment.');
        }

        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:hr_payrolls,id',
            'payment_date' => 'required|date',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $paid = 0;

        try {
            DB::transaction(function () use ($validated, $period, &$paid): void {
                $payrolls = HrPayroll::query()
                    ->with(['period', 'employee'])
                    ->where('hr_payroll_period_id', $period->id)
                    ->whereIn('id', $validated['payroll_ids'])
                    ->where('status', PayrollStatus::APPROVED->value)
                    ->lockForUpdate()
                    ->get();

                foreach ($payrolls as $payroll) {
                    $this->hrPayrollPostingService->postAccrual($payroll);

                    $payroll->update([
                        'status' => PayrollStatus::PAID->value,
                        'payment_date' => $validated['payment_date'],
                        'payment_reference' => $validated['payment_reference'],
                    ]);

                    $this->applyLoanRecoveries($payroll, Carbon::parse($validated['payment_date']));
                    $this->applyAdvanceRecoveries($payroll);
                    $this->hrPayrollPostingService->postPayment($payroll->fresh(['period', 'employee']));
                    $paid++;
                }

                // Update period
                $allPaid = HrPayroll::where('hr_payroll_period_id', $period->id)
                    ->where('status', '!=', PayrollStatus::PAID)
                    ->doesntExist();

                if ($allPaid) {
                    $lockedPeriod = HrPayrollPeriod::query()->lockForUpdate()->find($period->id);
                    if ($lockedPeriod) {
                        $lockedPeriod->markAsPaid();
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to mark payrolls paid: ' . $e->getMessage());
        }

        return back()->with('success', "{$paid} payrolls marked as paid.");
    }

    public function hold(Request $request, HrPayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'hold_reason' => 'required|string|max:500',
        ]);

        $payroll->hold($validated['hold_reason']);

        return back()->with('success', 'Payroll put on hold.');
    }

    public function release(HrPayroll $payroll): RedirectResponse
    {
        $payroll->release();
        return back()->with('success', 'Payroll released from hold.');
    }

    // Reports

    public function bankStatement(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::query()
            ->leftJoin('hr_employees', 'hr_employees.id', '=', 'hr_payrolls.hr_employee_id')
            ->where('hr_payrolls.hr_payroll_period_id', $period->id)
            ->where('hr_payrolls.payment_mode', 'bank_transfer')
            ->whereIn('hr_payrolls.status', [PayrollStatus::APPROVED->value, PayrollStatus::PAID->value])
            ->select('hr_payrolls.*')
            ->selectRaw("COALESCE(NULLIF(hr_payrolls.bank_account, ''), hr_employees.bank_account_number, '-') as resolved_bank_account")
            ->selectRaw("COALESCE(NULLIF(hr_payrolls.bank_ifsc, ''), hr_employees.bank_ifsc, '-') as resolved_bank_ifsc")
            ->orderBy('hr_payrolls.employee_name')
            ->get();

        $total = $payrolls->sum('net_payable');

        return view('hr.payroll.bank-statement', compact('period', 'payrolls', 'total'));
    }

    public function pfReport(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::with('employee')
            ->where('hr_payroll_period_id', $period->id)
            ->where(function ($q) {
                $q->where('pf_employee', '>', 0)
                    ->orWhere('pf_employer', '>', 0);
            })
            ->orderBy('employee_code')
            ->get();

        $summary = [
            'total_pf_employee' => $payrolls->sum('pf_employee'),
            'total_pf_employer' => $payrolls->sum('pf_employer'),
            'total_eps' => $payrolls->sum('eps_employer'),
            'total_edli' => $payrolls->sum('edli_employer'),
            'total_admin_charges' => $payrolls->sum('pf_admin_charges'),
            'total_contribution' => $payrolls->sum('pf_employee') + $payrolls->sum('pf_employer') +
                $payrolls->sum('eps_employer') + $payrolls->sum('edli_employer') + $payrolls->sum('pf_admin_charges'),
        ];

        return view('hr.payroll.pf-report', compact('period', 'payrolls', 'summary'));
    }

    public function esiReport(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::with('employee')
            ->where('hr_payroll_period_id', $period->id)
            ->where(function ($q) {
                $q->where('esi_employee', '>', 0)
                    ->orWhere('esi_employer', '>', 0);
            })
            ->orderBy('employee_code')
            ->get();

        $summary = [
            'total_esi_employee' => $payrolls->sum('esi_employee'),
            'total_esi_employer' => $payrolls->sum('esi_employer'),
            'total_contribution' => $payrolls->sum('esi_employee') + $payrolls->sum('esi_employer'),
        ];

        return view('hr.payroll.esi-report', compact('period', 'payrolls', 'summary'));
    }


    public function ptReport(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::with('employee')
            ->where('hr_payroll_period_id', $period->id)
            ->where('professional_tax', '>', 0)
            ->orderBy('employee_code')
            ->get();

        $summary = [
            'total_employees' => $payrolls->count(),
            'total_professional_tax' => $payrolls->sum('professional_tax'),
        ];

        return view('hr.payroll.pt-report', compact('period', 'payrolls', 'summary'));
    }

    public function tdsReport(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::with('employee')
            ->where('hr_payroll_period_id', $period->id)
            ->where('tds', '>', 0)
            ->orderBy('employee_code')
            ->get();

        $summary = [
            'total_employees' => $payrolls->count(),
            'total_tds' => $payrolls->sum('tds'),
        ];

        return view('hr.payroll.tds-report', compact('period', 'payrolls', 'summary'));
    }

    public function salaryRegister(HrPayrollPeriod $period): View
    {
        $payrolls = HrPayroll::with(['employee.department', 'employee.designation'])
            ->where('hr_payroll_period_id', $period->id)
            ->orderBy('employee_code')
            ->get();

        $summary = [
            'total_employees' => $payrolls->count(),
            'total_gross' => $payrolls->sum('gross_salary'),
            'total_earnings' => $payrolls->sum('total_earnings'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'total_round_off' => $payrolls->sum('round_off'),
            'total_net' => $payrolls->sum('net_payable'),
            'total_pf' => $payrolls->sum('pf_employee'),
            'total_esi' => $payrolls->sum('esi_employee'),
            'total_pt' => $payrolls->sum('professional_tax'),
            'total_tds' => $payrolls->sum('tds'),
        ];

        return view('hr.payroll.salary-register', compact('period', 'payrolls', 'summary'));
    }

    public function departmentWise(HrPayrollPeriod $period): View
    {
        $rows = HrPayroll::where('hr_payroll_period_id', $period->id)
            ->selectRaw("COALESCE(NULLIF(department_name, ''), 'Unassigned') as department_name")
            ->selectRaw('COUNT(*) as employees')
            ->selectRaw('SUM(gross_salary) as total_gross')
            ->selectRaw('SUM(total_deductions) as total_deductions')
            ->selectRaw('SUM(net_payable) as total_net')
            ->groupBy('department_name')
            ->orderBy('department_name')
            ->get();

        $summary = [
            'departments' => $rows->count(),
            'employees' => $rows->sum('employees'),
            'total_gross' => $rows->sum('total_gross'),
            'total_deductions' => $rows->sum('total_deductions'),
            'total_net' => $rows->sum('total_net'),
        ];

        return view('hr.payroll.department-wise', [
            'period' => $period,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    // Private Methods

    private function processEmployeePayroll(HrEmployee $employee, HrPayrollPeriod $period): HrPayroll
    {
        // Get current salary structure
        $salary = $employee->currentSalary;
        if (!$salary) {
            throw new \Exception('No salary structure assigned');
        }

        [$employmentStart, $employmentEnd] = $this->getPayrollEmploymentWindow($employee, $period);

        if (!$employmentStart || !$employmentEnd) {
            throw new \Exception('Employee is not employed during this payroll period');
        }

        // Get attendance summary for the payable service window.
        $attendanceSummary = $this->getAttendanceSummary($employee, $employmentStart, $employmentEnd);

        // Calculate paid days
        $paidDays = $attendanceSummary['paid_days'];
        $employmentWorkingDays = $employmentStart->diffInWeekdays($employmentEnd) + 1;
        $employmentServiceDays = $employmentStart->diffInDays($employmentEnd) + 1;
        $lopDays = max(0, $employmentServiceDays - $paidDays);

        $calendarDayDivisor = $this->calendarDayDivisor($period);

        $payroll = HrPayroll::firstOrNew([
            'hr_payroll_period_id' => $period->id,
            'hr_employee_id' => $employee->id,
        ]);
        $payroll->hr_payroll_period_id ??= $period->id;

        $payroll->fill([
            'company_id' => $period->company_id ?: 1,
            'payroll_number' => $payroll->exists
                ? $payroll->payroll_number
                : HrPayroll::generateNumber((int) $payroll->hr_payroll_period_id),
            'hr_employee_salary_id' => $salary->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->full_name,
            'department_name' => $employee->department?->name,
            'designation_name' => $employee->designation?->name,
            'bank_account' => $employee->bank_account_number,
            'bank_ifsc' => $employee->bank_ifsc,
            'payment_mode' => $employee->payment_mode,
            'working_days' => max(0, (int) $employmentWorkingDays),
            'present_days' => $attendanceSummary['present'],
            'paid_days' => $paidDays,
            'absent_days' => $attendanceSummary['absent'],
            'leave_days' => $attendanceSummary['leave'],
            'holidays' => $attendanceSummary['holiday'],
            'week_offs' => $attendanceSummary['weekly_off'],
            'half_days' => $attendanceSummary['half_day'],
            'late_days' => $attendanceSummary['late'],
            'ot_hours' => $attendanceSummary['ot_hours'],
            'lop_days' => max(0, (int) round($lopDays)),
            'status' => PayrollStatus::PROCESSED,
            'created_by' => $payroll->created_by ?: auth()->id(),
        ]);
        $this->resetCalculatedAmounts($payroll);
        $payroll->save();

        HrPayrollComponent::where('hr_payroll_id', $payroll->id)->delete();

        // Calculate earnings
        $this->calculateEarnings($payroll, $salary, $paidDays, $attendanceSummary, $calendarDayDivisor);

        // Calculate statutory deductions
        $this->calculateStatutoryDeductions($payroll, $employee, $period);

        // Calculate loan and advance deductions
        $this->calculateLoanDeductions($payroll, $employee, $period);

        // Earnings are already prorated by paid days, so LOP remains informational.
        $payroll->lop_deduction = 0;

        // Calculate totals
        $payroll->calculateTotals();
        $payroll->save();

        return $payroll;
    }

    private function getAttendanceSummary(HrEmployee $employee, Carbon $attendanceStart, Carbon $attendanceEnd): array
    {
        $attendances = HrAttendance::where('hr_employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $attendanceStart->toDateString())
            ->whereDate('attendance_date', '<=', $attendanceEnd->toDateString())
            ->with(['shift', 'overtimeRecord'])
            ->get();

        $presentCount = $attendances->filter(fn(HrAttendance $attendance) => in_array($attendance->status, [
            AttendanceStatus::PRESENT,
            AttendanceStatus::LATE,
            AttendanceStatus::EARLY_LEAVING,
            AttendanceStatus::LATE_AND_EARLY,
            AttendanceStatus::ON_DUTY,
        ], true))->count();
        $halfDayCount = $attendances->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::HALF_DAY)->count();
        $weeklyOffCount = $attendances->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::WEEKLY_OFF)->count();
        $holidayCount = $attendances->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::HOLIDAY)->count();
        $paidLeaveCount = $attendances
            ->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::LEAVE
                && ($attendance->leaveApplication?->leaveType?->is_paid ?? false))
            ->count();
        $paidDays = $presentCount + $weeklyOffCount + $holidayCount + $paidLeaveCount + ($halfDayCount * 0.5);

        return [
            'present' => $presentCount,
            'absent' => $attendances->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::ABSENT)->count(),
            'half_day' => $halfDayCount,
            'leave' => $attendances->filter(fn(HrAttendance $attendance) => $attendance->status === AttendanceStatus::LEAVE)->count(),
            'weekly_off' => $weeklyOffCount,
            'holiday' => $holidayCount,
            'late' => $attendances->where('late_minutes', '>', 0)->count(),
            'ot_hours' => $attendances->sum('ot_hours_approved'),
            'ot_attendances' => $attendances->filter(fn (HrAttendance $attendance) => (float) $attendance->ot_hours_approved > 0)->values(),
            'paid_days' => $paidDays,
        ];
    }

    private function getPayrollEmploymentWindow(HrEmployee $employee, HrPayrollPeriod $period): array
    {
        $employmentStart = $employee->date_of_joining
            ? $employee->date_of_joining->copy()->startOfDay()
            : null;

        if (!$employmentStart) {
            return [null, null];
        }

        $windowStart = $employmentStart->greaterThan($period->attendance_start)
            ? $employmentStart
            : $period->attendance_start->copy()->startOfDay();

        $windowEnd = $period->attendance_end->copy()->startOfDay();

        if ($employee->date_of_leaving) {
            $lastWorkingDay = $employee->date_of_leaving->copy()->subDay()->startOfDay();

            if ($lastWorkingDay->lessThan($windowEnd)) {
                $windowEnd = $lastWorkingDay;
            }
        }

        if ($windowEnd->lessThan($windowStart)) {
            return [null, null];
        }

        return [$windowStart, $windowEnd];
    }

    private function calculateEarnings(HrPayroll $payroll, HrEmployeeSalary $salary, float $paidDays, array $attendance, int $calendarDayDivisor): void
    {
        $ratio = $paidDays / max(1, $calendarDayDivisor);

        // Get component values from employee salary
        $components = $salary->components()->with('salaryComponent')->get();

        if ($components->isEmpty()) {
            $basicAmount = round(((float) $salary->monthly_basic) * $ratio, 2);
            $grossAmount = round(((float) $salary->monthly_gross) * $ratio, 2);
            $otherEarnings = max(0, round($grossAmount - $basicAmount, 2));

            $payroll->basic = $basicAmount;
            $payroll->other_earnings = $otherEarnings;
            $payroll->total_earnings = $grossAmount;
            $payroll->gross_salary = $grossAmount;

            if ($basicAmount > 0) {
                $this->upsertPayrollComponent(
                    $payroll,
                    null,
                    'BASIC',
                    'Basic Salary',
                    'earning',
                    (float) $salary->monthly_basic,
                    $basicAmount,
                    1
                );
            }

            if ($otherEarnings > 0) {
                $this->upsertPayrollComponent(
                    $payroll,
                    null,
                    'SPECIAL',
                    'Other Earnings',
                    'earning',
                    max(0, round(((float) $salary->monthly_gross) - ((float) $salary->monthly_basic), 2)),
                    $otherEarnings,
                    99
                );
            }
        } else {
            foreach ($components as $comp) {
                if ($comp->salaryComponent->component_type !== 'earning') {
                    continue;
                }

                $amount = round($comp->monthly_amount * $ratio, 2);
                $componentCategory = $comp->salaryComponent->category;
                $componentType = $comp->salaryComponent->component_type;

                match ($componentCategory) {
                    'basic' => $payroll->basic = $amount,
                    'hra' => $payroll->hra = $amount,
                    'da' => $payroll->da = $amount,
                    'special_allowance' => $payroll->special_allowance = $amount,
                    'conveyance' => $payroll->conveyance = $amount,
                    'medical' => $payroll->medical = $amount,
                    default => $componentType === 'earning' ? $payroll->other_earnings += $amount : null,
                };

                // Store component detail
                $this->upsertPayrollComponent(
                    $payroll,
                    $comp->hr_salary_component_id,
                    $comp->salaryComponent->code,
                    $comp->salaryComponent->name,
                    $comp->salaryComponent->component_type,
                    (float) $comp->monthly_amount,
                    $amount,
                    (int) $comp->salaryComponent->sort_order
                );
            }
        }

        // Calculate OT
        if (($salary->employee?->overtime_applicable ?? $salary->employee()->value('overtime_applicable')) && $attendance['ot_hours'] > 0) {
            $otAmount = 0.0;

            foreach (($attendance['ot_attendances'] ?? collect()) as $otAttendance) {
                $hourlyRate = $this->resolveAttendanceOtHourlyRate($payroll, $otAttendance, $calendarDayDivisor);
                $multiplier = $this->resolveAttendanceOtMultiplier($otAttendance);
                $rowAmount = round($hourlyRate * (float) $otAttendance->ot_hours_approved * $multiplier, 2);
                $otAmount += $rowAmount;
                $this->syncAttendanceOvertimeRecordForPayroll($otAttendance, $payroll, $hourlyRate, $multiplier, $rowAmount);
            }

            $payroll->ot_amount = round($otAmount, 2);

            if ($payroll->ot_amount > 0) {
                $this->upsertPayrollComponent(
                    $payroll,
                    null,
                    'OT',
                    'Overtime',
                    'earning',
                    $payroll->ot_amount,
                    $payroll->ot_amount,
                    110
                );
            }
        }

        $payroll->total_earnings = $payroll->basic + $payroll->hra + $payroll->da +
            $payroll->special_allowance + $payroll->conveyance + $payroll->medical +
            $payroll->other_earnings + $payroll->ot_amount + $payroll->incentive +
            $payroll->bonus + $payroll->arrears + $payroll->reimbursements;
        $payroll->gross_salary = $payroll->total_earnings;
    }

    private function resolveAttendanceOtMultiplier(HrAttendance $attendance): float
    {
        $shift = $attendance->shift;
        $policy = $attendance->employee?->getApplicableAttendancePolicy($attendance->attendance_date)
            ?? HrEmployee::query()->find($attendance->hr_employee_id)?->getApplicableAttendancePolicy($attendance->attendance_date);

        if ($attendance->is_holiday || $attendance->is_week_off) {
            if ($attendance->is_holiday) {
                return (float) ($policy?->holiday_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5);
            }

            return (float) ($policy?->week_off_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5);
        }

        return (float) ($policy?->ot_rate_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5);
    }

    private function resolveAttendanceOtHourlyRate(HrPayroll $payroll, HrAttendance $attendance, int $calendarDayDivisor): float
    {
        $policy = $attendance->employee?->getApplicableAttendancePolicy($attendance->attendance_date)
            ?? HrEmployee::query()->find($attendance->hr_employee_id)?->getApplicableAttendancePolicy($attendance->attendance_date);

        $basis = $policy?->ot_calculation_basis ?? 'basic';
        if ($basis === 'fixed') {
            $employee = $attendance->employee ?: ($payroll->relationLoaded('employee') ? $payroll->employee : $payroll->employee()->first());
            $fixedRate = (float) ($employee?->ot_hourly_rate ?? 0);

            if ($fixedRate <= 0) {
                throw new \RuntimeException('OT hourly rate is not configured for fixed-basis overtime.');
            }

            return round($fixedRate, 2);
        }

        $salary = $payroll->relationLoaded('employeeSalary')
            ? $payroll->employeeSalary
            : $payroll->employeeSalary()->first();

        $monthlyBase = $basis === 'gross'
            ? (float) ($salary?->monthly_gross ?? $payroll->gross_salary)
            : (float) ($salary?->monthly_basic ?? $payroll->basic);

        return round($monthlyBase / max(1, $calendarDayDivisor) / 8, 2);
    }

    private function syncAttendanceOvertimeRecordForPayroll(HrAttendance $attendance, HrPayroll $payroll, float $hourlyRate, float $multiplier, float $otAmount): void
    {
        HrOvertimeRecord::updateOrCreate(
            ['hr_attendance_id' => $attendance->id],
            [
                'ot_number' => sprintf('OT-%s-%d', optional($attendance->attendance_date)->format('Ymd') ?: now()->format('Ymd'), $attendance->id),
                'hr_employee_id' => $attendance->hr_employee_id,
                'ot_date' => optional($attendance->attendance_date)->toDateString() ?: now()->toDateString(),
                'ot_start_time' => $attendance->last_out ?: now()->startOfDay(),
                'ot_end_time' => $attendance->last_out ?: now()->startOfDay(),
                'ot_hours' => (float) $attendance->ot_hours,
                'approved_hours' => (float) $attendance->ot_hours_approved,
                'ot_type' => $attendance->is_holiday ? 'holiday' : ($attendance->is_week_off ? 'weekly_off' : 'normal'),
                'rate_multiplier' => round($multiplier, 2),
                'hourly_rate' => round($hourlyRate, 2),
                'ot_amount' => round($otAmount, 2),
                'status' => (string) ($attendance->ot_status ?: 'approved'),
                'requested_by' => $attendance->created_by,
                'requested_at' => $attendance->created_at,
                'approved_by' => $attendance->ot_approved_by,
                'approved_at' => $attendance->ot_approved_at,
                'rejection_reason' => $attendance->ot_status === 'rejected' ? 'Rejected from attendance OT approval' : null,
                'hr_payroll_id' => $payroll->id,
                'is_paid' => false,
            ]
        );
    }

    private function calculateStatutoryDeductions(HrPayroll $payroll, HrEmployee $employee, HrPayrollPeriod $period): void
    {
        $effectiveDate = $period->period_end?->toDateString() ?: now()->toDateString();

        // PF Calculation
        if ($employee->pf_applicable) {
            $pfSlab = HrPfSlab::where('is_active', true)
                ->where('effective_from', '<=', $effectiveDate)
                ->orderByDesc('effective_from')
                ->first();

            if ($pfSlab) {
                $pfWages = min($payroll->basic, $pfSlab->wage_ceiling);
                $payroll->pf_employee = round($pfWages * $pfSlab->employee_contribution_rate / 100, 0);
                $payroll->pf_employer = round($pfWages * $pfSlab->employer_pf_rate / 100, 0);
                $payroll->eps_employer = round($pfWages * $pfSlab->employer_eps_rate / 100, 0);
                $payroll->edli_employer = round($pfWages * $pfSlab->employer_edli_rate / 100, 0);
                $payroll->pf_admin_charges = round($pfWages * ($pfSlab->admin_charges_rate + $pfSlab->edli_admin_rate) / 100, 0);

                if ($payroll->pf_employee > 0) {
                    $this->upsertPayrollComponent($payroll, null, 'PF_EE', 'Provident Fund (Employee)', 'deduction', $payroll->pf_employee, $payroll->pf_employee, 201);
                }
            }
        }

        // ESI Calculation
        if ($employee->esi_applicable) {
            $esiSlab = HrEsiSlab::where('is_active', true)
                ->where('effective_from', '<=', $effectiveDate)
                ->orderByDesc('effective_from')
                ->first();

            if ($esiSlab && $payroll->gross_salary <= $esiSlab->wage_ceiling) {
                $payroll->esi_employee = round($payroll->gross_salary * $esiSlab->employee_rate / 100, 0);
                $payroll->esi_employer = round($payroll->gross_salary * $esiSlab->employer_rate / 100, 0);

                if ($payroll->esi_employee > 0) {
                    $this->upsertPayrollComponent($payroll, null, 'ESI_EE', 'ESI (Employee)', 'deduction', $payroll->esi_employee, $payroll->esi_employee, 202);
                }
            }
        }

        // Professional Tax
        if ($employee->pt_applicable && $employee->pt_state) {
            $employeeState = mb_strtolower(trim((string) $employee->pt_state));
            $employeeGender = mb_strtolower((string) ($employee->gender ?? 'all'));

            $ptSlab = HrProfessionalTaxSlab::query()
                ->where('is_active', true)
                ->where('effective_from', '<=', $effectiveDate)
                ->where(function ($query) use ($effectiveDate) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $effectiveDate);
                })
                ->where('salary_from', '<=', $payroll->gross_salary)
                ->where('salary_to', '>=', $payroll->gross_salary)
                ->get()
                ->first(function (HrProfessionalTaxSlab $slab) use ($employeeState, $employeeGender) {
                    $slabStateCode = mb_strtolower(trim((string) $slab->state_code));
                    $slabStateName = mb_strtolower(trim((string) $slab->state_name));
                    $slabGender = mb_strtolower((string) ($slab->gender ?? 'all'));

                    $stateMatches = $employeeState !== ''
                        && ($employeeState === $slabStateCode || $employeeState === $slabStateName);

                    $genderMatches = $slabGender === 'all'
                        || $employeeGender === ''
                        || $employeeGender === $slabGender;

                    return $stateMatches && $genderMatches;
                });

            if ($ptSlab) {
                $payroll->professional_tax = $ptSlab->tax_amount;

                if ($payroll->professional_tax > 0) {
                    $this->upsertPayrollComponent($payroll, null, 'PT', 'Professional Tax', 'deduction', $payroll->professional_tax, $payroll->professional_tax, 203);
                }
            }
        }

        if ($employee->tds_applicable) {
            $payroll->tds = $this->calculateMonthlyTds($employee, $payroll, $period);

            if ($payroll->tds > 0) {
                $this->upsertPayrollComponent($payroll, null, 'TDS', 'Tax Deducted at Source', 'deduction', $payroll->tds, $payroll->tds, 204);
            }
        }

        if ($employee->lwf_applicable && $employee->pt_state) {
            $lwfSlab = HrLwfSlab::query()
                ->where('state_code', $employee->pt_state)
                ->where('is_active', true)
                ->where('effective_from', '<=', $effectiveDate)
                ->where(function ($query) use ($effectiveDate) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $effectiveDate);
                })
                ->orderByDesc('effective_from')
                ->first();

            if ($lwfSlab && $this->isLwfApplicableForPeriod($lwfSlab, $period)) {
                $payroll->lwf_employee = round((float) $lwfSlab->employee_contribution, 2);
                $payroll->lwf_employer = round((float) $lwfSlab->employer_contribution, 2);

                if ($payroll->lwf_employee > 0) {
                    $this->upsertPayrollComponent($payroll, null, 'LWF_EE', 'Labour Welfare Fund', 'deduction', $payroll->lwf_employee, $payroll->lwf_employee, 205);
                }
            }
        }
    }

    private function calculateLoanDeductions(HrPayroll $payroll, HrEmployee $employee, HrPayrollPeriod $period): void
    {
        // Active loans
        $loanRepayments = HrLoanRepayment::whereHas('loan', fn($q) => $q->where('hr_employee_id', $employee->id))
            ->whereYear('due_date', $period->year)
            ->whereMonth('due_date', $period->month)
            ->where('status', 'pending')
            ->get();

        foreach ($loanRepayments as $repayment) {
            $payroll->loan_deduction += $repayment->emi_amount;
            $repayment->update(['hr_payroll_id' => $payroll->id]);
        }

        if ($payroll->loan_deduction > 0) {
            $this->upsertPayrollComponent(
                $payroll,
                null,
                'LOAN',
                'Loan Recovery',
                'deduction',
                (float) $payroll->loan_deduction,
                (float) $payroll->loan_deduction,
                297
            );
        }

        // Active advances
        $advances = HrSalaryAdvance::where('hr_employee_id', $employee->id)
            ->where('status', 'recovering')
            ->where('balance_amount', '>', 0)
            ->where(function ($query) use ($period) {
                $query->whereNull('recovery_start_date')
                    ->orWhere('recovery_start_date', '<=', $period->period_end);
            })
            ->orderBy('recovery_start_date')
            ->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            $deduction = min($advance->monthly_deduction, $advance->balance_amount);
            $payroll->advance_deduction += $deduction;
        }

        if ($payroll->advance_deduction > 0) {
            $this->upsertPayrollComponent(
                $payroll,
                null,
                'ADV',
                'Advance Recovery',
                'deduction',
                (float) $payroll->advance_deduction,
                (float) $payroll->advance_deduction,
                298
            );
        }
    }

    private function upsertPayrollComponent(
        HrPayroll $payroll,
        ?int $salaryComponentId,
        string $code,
        string $name,
        string $componentType,
        float $baseAmount,
        float $finalAmount,
        int $sortOrder
    ): void {
        $salaryComponentId ??= $this->resolvePayrollComponentId($code, $name, $componentType, $sortOrder);

        HrPayrollComponent::updateOrCreate(
            [
                'hr_payroll_id' => $payroll->id,
                'hr_salary_component_id' => $salaryComponentId,
                'component_code' => $code,
            ],
            [
                'component_name' => $name,
                'component_type' => $componentType,
                'base_amount' => $baseAmount,
                'calculated_amount' => $finalAmount,
                'final_amount' => $finalAmount,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function resolvePayrollComponentId(
        string $code,
        string $name,
        string $componentType,
        int $sortOrder
    ): int {
        $canonicalCode = $code;

        $component = HrSalaryComponent::query()
            ->where('code', $canonicalCode)
            ->first();

        if ($component) {
            return $component->id;
        }

        $category = match ($canonicalCode) {
            'BASIC' => 'basic',
            'HRA' => 'hra',
            'DA' => 'da',
            'CONV' => 'conveyance',
            'MED' => 'medical',
            'SPECIAL' => 'special_allowance',
            'OTH' => 'other_earning',
            'OT' => 'overtime',
            'PF_EE' => 'pf_employee',
            'ESI_EE' => 'esi_employee',
            'PT' => 'professional_tax',
            'TDS' => 'tds',
            'LOAN' => 'loan_recovery',
            'ADV' => 'advance_recovery',
            'LOP' => 'other_deduction',
            'ODED' => 'other_deduction',
            default => $componentType === 'deduction' ? 'other_deduction' : 'other_earning',
        };

        $component = HrSalaryComponent::query()->create([
            'code' => $canonicalCode,
            'name' => $name,
            'component_type' => $componentType,
            'category' => $category,
            'calculation_type' => 'fixed',
            'default_value' => 0,
            'sort_order' => $sortOrder,
            'show_in_payslip' => true,
            'show_if_zero' => false,
            'is_active' => true,
        ]);

        return $component->id;
    }

    private function resetCalculatedAmounts(HrPayroll $payroll): void
    {
        foreach ([
            'basic',
            'hra',
            'da',
            'special_allowance',
            'conveyance',
            'medical',
            'other_earnings',
            'ot_amount',
            'incentive',
            'bonus',
            'arrears',
            'reimbursements',
            'total_earnings',
            'gross_salary',
            'pf_employee',
            'esi_employee',
            'professional_tax',
            'tds',
            'lwf_employee',
            'loan_deduction',
            'advance_deduction',
            'other_deductions',
            'lop_deduction',
            'total_deductions',
            'net_pay',
            'round_off',
            'net_payable',
            'pf_employer',
            'eps_employer',
            'edli_employer',
            'pf_admin_charges',
            'esi_employer',
            'lwf_employer',
            'gratuity_provision',
            'total_employer_cost',
            'ctc',
        ] as $field) {
            $payroll->{$field} = 0;
        }
    }

    private function applyAdvanceRecoveries(HrPayroll $payroll): void
    {
        if ((float) $payroll->advance_deduction <= 0) {
            return;
        }

        $remaining = (float) $payroll->advance_deduction;
        $advances = HrSalaryAdvance::query()
            ->where('hr_employee_id', $payroll->hr_employee_id)
            ->where('status', 'recovering')
            ->where('balance_amount', '>', 0)
            ->orderBy('recovery_start_date')
            ->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $recovery = min($remaining, (float) $advance->balance_amount);
            if ($recovery <= 0) {
                continue;
            }

            $newBalance = max(0, (float) $advance->balance_amount - $recovery);
            $advance->update([
                'recovered_amount' => (float) $advance->recovered_amount + $recovery,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'closed' : 'recovering',
            ]);

            $remaining -= $recovery;
        }
    }

    private function applyLoanRecoveries(HrPayroll $payroll, Carbon $paymentDate): void
    {
        $repayments = HrLoanRepayment::query()
            ->with('loan')
            ->where('hr_payroll_id', $payroll->id)
            ->get();

        if ($repayments->isEmpty()) {
            return;
        }

        $repaymentsByLoan = $repayments->groupBy('hr_employee_loan_id');

        foreach ($repaymentsByLoan as $loanId => $loanRepayments) {
            $loan = $loanRepayments->first()?->loan;
            if (!$loan) {
                continue;
            }

            $principalRecovered = 0.0;
            $interestRecovered = 0.0;

            foreach ($loanRepayments as $repayment) {
                $principalRecovered += (float) $repayment->principal_amount;
                $interestRecovered += (float) $repayment->interest_amount;

                $repayment->update([
                    'status' => 'paid',
                    'paid_date' => $paymentDate->toDateString(),
                    'paid_amount' => (float) $repayment->emi_amount,
                ]);
            }

            $newPrincipalOutstanding = max(0, (float) $loan->principal_outstanding - $principalRecovered);
            $newInterestOutstanding = max(0, (float) $loan->interest_outstanding - $interestRecovered);
            $newEmisPaid = min((int) $loan->tenure_months, (int) $loan->emis_paid + $loanRepayments->count());
            $newEmisPending = max(0, (int) $loan->tenure_months - $newEmisPaid);
            $newTotalOutstanding = round($newPrincipalOutstanding + $newInterestOutstanding, 2);

            $loan->update([
                'principal_outstanding' => $newPrincipalOutstanding,
                'interest_outstanding' => $newInterestOutstanding,
                'total_outstanding' => $newTotalOutstanding,
                'total_recovered' => (float) $loan->total_recovered + $principalRecovered + $interestRecovered,
                'emis_paid' => $newEmisPaid,
                'emis_pending' => $newEmisPending,
                'status' => $newTotalOutstanding <= 0 ? 'closed' : 'active',
            ]);
        }
    }

    private function restoreAdvanceRecoveries(HrPayroll $payroll): void
    {
        if ((float) $payroll->advance_deduction <= 0) {
            return;
        }

        $remaining = (float) $payroll->advance_deduction;
        $advances = HrSalaryAdvance::query()
            ->where('hr_employee_id', $payroll->hr_employee_id)
            ->where('recovered_amount', '>', 0)
            ->orderByDesc('recovery_start_date')
            ->orderByDesc('id')
            ->get();

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $restore = min($remaining, (float) $advance->recovered_amount);
            if ($restore <= 0) {
                continue;
            }

            $newRecovered = max(0, (float) $advance->recovered_amount - $restore);
            $newBalance = (float) $advance->balance_amount + $restore;

            $advance->update([
                'recovered_amount' => $newRecovered,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'closed' : 'recovering',
            ]);

            $remaining -= $restore;
        }
    }

    private function restoreLoanRecoveries(HrPayroll $payroll): void
    {
        $repayments = HrLoanRepayment::query()
            ->with('loan')
            ->where('hr_payroll_id', $payroll->id)
            ->get();

        if ($repayments->isEmpty()) {
            return;
        }

        $repaymentsByLoan = $repayments->groupBy('hr_employee_loan_id');

        foreach ($repaymentsByLoan as $loanRepayments) {
            $loan = $loanRepayments->first()?->loan;
            if (!$loan) {
                continue;
            }

            $principalRestore = 0.0;
            $interestRestore = 0.0;

            foreach ($loanRepayments as $repayment) {
                $principalRestore += (float) $repayment->principal_amount;
                $interestRestore += (float) $repayment->interest_amount;

                $repayment->update([
                    'status' => 'pending',
                    'paid_date' => null,
                    'paid_amount' => 0,
                ]);
            }

            $newPrincipalOutstanding = (float) $loan->principal_outstanding + $principalRestore;
            $newInterestOutstanding = (float) $loan->interest_outstanding + $interestRestore;
            $newEmisPaid = max(0, (int) $loan->emis_paid - $loanRepayments->count());
            $newEmisPending = min((int) $loan->tenure_months, (int) $loan->emis_pending + $loanRepayments->count());

            $loan->update([
                'principal_outstanding' => $newPrincipalOutstanding,
                'interest_outstanding' => $newInterestOutstanding,
                'total_outstanding' => round($newPrincipalOutstanding + $newInterestOutstanding, 2),
                'total_recovered' => max(0, (float) $loan->total_recovered - $principalRestore - $interestRestore),
                'emis_paid' => $newEmisPaid,
                'emis_pending' => $newEmisPending,
                'status' => 'active',
            ]);
        }
    }

    private function hasLaterPaidPayroll(HrPayroll $payroll): bool
    {
        $period = $payroll->period;
        if (!$period) {
            return false;
        }

        return HrPayroll::query()
            ->join('hr_payroll_periods as period_lookup', 'period_lookup.id', '=', 'hr_payrolls.hr_payroll_period_id')
            ->where('hr_payrolls.hr_employee_id', $payroll->hr_employee_id)
            ->where('hr_payrolls.status', PayrollStatus::PAID->value)
            ->where('hr_payrolls.id', '!=', $payroll->id)
            ->where(function ($query) use ($period) {
                $query->where('period_lookup.year', '>', $period->year)
                    ->orWhere(function ($nested) use ($period) {
                        $nested->where('period_lookup.year', $period->year)
                            ->where('period_lookup.month', '>', $period->month);
                    });
            })
            ->exists();
    }

    private function refreshPeriodStatus(?int $periodId): void
    {
        if (!$periodId) {
            return;
        }

        $period = HrPayrollPeriod::query()->lockForUpdate()->find($periodId);
        if (!$period || $period->status === 'closed') {
            return;
        }

        $statuses = HrPayroll::query()
            ->where('hr_payroll_period_id', $periodId)
            ->pluck('status')
            ->map(fn($status) => $status instanceof PayrollStatus ? $status->value : (string) $status)
            ->all();

        if ($statuses === []) {
            return;
        }

        if (count(array_diff($statuses, [PayrollStatus::PAID->value])) === 0) {
            $period->markAsPaid();
            return;
        }

        if (count(array_diff($statuses, [PayrollStatus::APPROVED->value, PayrollStatus::PAID->value])) === 0) {
            $period->markAsApproved();
            return;
        }

        if (count(array_diff($statuses, [PayrollStatus::PROCESSED->value, PayrollStatus::APPROVED->value, PayrollStatus::PAID->value])) === 0) {
            $period->markAsProcessed();
        }
    }

    private function calculateMonthlyTds(HrEmployee $employee, HrPayroll $payroll, HrPayrollPeriod $period): float
    {
        $financialYear = $this->financialYearForDate($period->period_end);
        $declaration = HrTaxDeclaration::query()
            ->where('hr_employee_id', $employee->id)
            ->where('financial_year', $financialYear)
            ->first();

        $regime = (string) ($declaration?->tax_regime ?: $employee->tax_regime ?: 'new');
        $annualIncome = max(0, round((float) $payroll->gross_salary * 12, 2));
        $totalExemption = (float) ($declaration?->total_verified ?? $declaration?->total_declared ?? $declaration?->total_exemption ?? 0);
        $taxableIncome = max(0, $annualIncome - $totalExemption);

        $annualTax = $this->estimateAnnualTaxFromSlabs($taxableIncome, $financialYear, $regime);
        if ($annualTax <= 0) {
            return 0;
        }

        return round($annualTax / 12, 2);
    }

    private function estimateAnnualTaxFromSlabs(float $taxableIncome, string $financialYear, string $regime): float
    {
        $slabs = HrTdsSlab::query()
            ->whereIn('financial_year', $this->financialYearAliases($financialYear))
            ->where('regime', $regime)
            ->where('category', 'general')
            ->where('is_active', true)
            ->orderBy('income_from')
            ->get();

        if ($slabs->isEmpty()) {
            return $this->estimateAnnualTaxFallback($taxableIncome, $regime);
        }

        $tax = 0.0;
        $cessPercent = 0.0;

        foreach ($slabs as $slab) {
            $slabStart = (float) $slab->income_from;
            $slabEnd = (float) $slab->income_to;
            if ($taxableIncome <= $slabStart) {
                continue;
            }

            $taxablePortion = min($taxableIncome, $slabEnd) - $slabStart;
            if ($taxablePortion <= 0) {
                continue;
            }

            $tax += $taxablePortion * ((float) $slab->tax_percent / 100);
            $tax += $taxablePortion * ((float) $slab->surcharge_percent / 100);
            $cessPercent = max($cessPercent, (float) $slab->cess_percent);
        }

        if ($tax <= 0) {
            return 0;
        }

        return round($tax * (1 + ($cessPercent / 100)), 2);
    }

    private function estimateAnnualTaxFallback(float $taxableIncome, string $regime): float
    {
        if ($taxableIncome <= 300000) {
            return 0;
        }

        $tax = 0.0;
        $slabs = $regime === 'old'
            ? [
                [250000, 0],
                [500000, 5],
                [1000000, 20],
                [INF, 30],
            ]
            : [
                [300000, 0],
                [700000, 5],
                [1000000, 10],
                [1200000, 15],
                [1500000, 20],
                [INF, 30],
            ];

        $previous = 0.0;
        foreach ($slabs as [$limit, $rate]) {
            if ($taxableIncome <= $previous) {
                break;
            }

            $amount = min($taxableIncome, $limit) - $previous;
            if ($amount > 0) {
                $tax += $amount * ($rate / 100);
            }

            $previous = $limit;
        }

        return round($tax * 1.04, 2);
    }

    private function isLwfApplicableForPeriod(HrLwfSlab $slab, HrPayrollPeriod $period): bool
    {
        return match ((string) $slab->frequency) {
            'monthly' => true,
            'half_yearly' => in_array((int) $period->month, [6, 12], true),
            'annual' => (int) $period->month === 12,
            default => false,
        };
    }

    private function financialYearForDate(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    private function financialYearAliases(string $financialYear): array
    {
        if (!preg_match('/^(\d{4})-(\d{2}|\d{4})$/', $financialYear, $matches)) {
            return [$financialYear];
        }

        $startYear = (int) $matches[1];
        $endYear = $matches[2];
        $endYearFull = strlen($endYear) === 2 ? (int) substr((string) ($startYear + 1), 0, 2) . $endYear : (int) $endYear;

        return array_values(array_unique([
            sprintf('%d-%02d', $startYear, $endYearFull % 100),
            sprintf('%d-%d', $startYear, $endYearFull),
        ]));
    }

    private function calendarDayDivisor(HrPayrollPeriod $period): int
    {
        if ((int) ($period->total_days ?? 0) > 0) {
            return (int) $period->total_days;
        }

        return max(1, $period->period_start->diffInDays($period->period_end) + 1);
    }
}
