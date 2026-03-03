<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
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
use App\Models\Hr\HrProfessionalTaxSlab;
use App\Models\Hr\HrSalaryAdvance;
use App\Models\Hr\HrSalaryComponent;
use App\Enums\Hr\PayrollStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrPayrollController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Core viewing
        $this->middleware('permission:hr.payroll.view')->only([
            'index',
            'period',
            'show',
            'payslip',
            'payslipPdf',

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
        ]);

        $this->middleware('permission:hr.payroll.pay')->only([
            'pay',
            'bulkPay',
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
            'total_paid' => $currentPeriod ? HrPayroll::where('hr_payroll_period_id', $currentPeriod->id)->where('status', PayrollStatus::PAID)->count() : 0,
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
            ]);

            // Check if already exists
            $existing = HrPayrollPeriod::where('year', $validated['year'])
                ->where('month', $validated['month'])
                ->first();

            if ($existing) {
                return back()->with('error', 'Payroll period already exists for this month.');
            }

            $startDate = Carbon::createFromDate($validated['year'], $validated['month'], 1);
            $endDate = $startDate->copy()->endOfMonth();

            $period = HrPayrollPeriod::create([
                'company_id' => 1,
                'period_code' => 'PP-' . $validated['year'] . '-' . str_pad($validated['month'], 2, '0', STR_PAD_LEFT),
                'name' => $startDate->format('F Y'),
                'year' => $validated['year'],
                'month' => $validated['month'],
                'period_start' => $startDate,
                'period_end' => $endDate,
                'attendance_start' => $startDate,
                'attendance_end' => $endDate,
                'total_days' => $startDate->daysInMonth,
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

            $query = HrEmployee::active();

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

            // Finalize period status
            $period->markAsProcessed();

            DB::commit();

            $message = "Payroll processed for {$processed} employees.";
            if (!empty($errors)) {
                $message .= " Errors: " . count($errors);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    public function show(HrPayroll $payroll): View
    {
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
        if (!$payroll->canApprove()) {
            return back()->with('error', 'This payroll cannot be approved in its current state.');
        }

        $payroll->update([
            'status' => PayrollStatus::APPROVED,
        ]);

        // If all payrolls are approved (or paid), mark the period as approved
        $periodId = $payroll->hr_payroll_period_id;
        if ($periodId) {
            $allApproved = HrPayroll::where('hr_payroll_period_id', $periodId)
                ->whereNotIn('status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
                ->doesntExist();

            if ($allApproved) {
                $period = HrPayrollPeriod::find($periodId);
                if ($period && !in_array($period->status, ['paid', 'closed'])) {
                    $period->markAsApproved();
                }
            }
        }

        return back()->with('success', 'Payroll approved successfully.');
    }

    public function bulkApprove(Request $request, HrPayrollPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:hr_payrolls,id',
        ]);

        $approved = HrPayroll::whereIn('id', $validated['payroll_ids'])
            ->where('status', PayrollStatus::PROCESSED)
            ->update(['status' => PayrollStatus::APPROVED]);

        // If all payrolls in this period are approved (or paid), mark period as approved
        $allApproved = HrPayroll::where('hr_payroll_period_id', $period->id)
            ->whereNotIn('status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
            ->doesntExist();

        if ($allApproved && !in_array($period->status, ['paid', 'closed'])) {
            $period->markAsApproved();
        }

        return back()->with('success', "{$approved} payrolls approved.");
    }

    public function pay(HrPayroll $payroll): RedirectResponse
    {
        if (!$payroll->canPay()) {
            return back()->with('error', 'This payroll cannot be marked as paid.');
        }

        $paymentDate = now();

        $payroll->update([
            'status' => PayrollStatus::PAID,
            'payment_date' => $paymentDate,
        ]);

        // Update loan repayments linked to this payroll
        HrLoanRepayment::where('hr_payroll_id', $payroll->id)
            ->update([
                'status' => 'paid',
                'paid_date' => $paymentDate,
            ]);

        // Update period if all payrolls are paid
        $periodId = $payroll->hr_payroll_period_id;
        if ($periodId) {
            $allPaid = HrPayroll::where('hr_payroll_period_id', $periodId)
                ->where('status', '!=', PayrollStatus::PAID)
                ->doesntExist();

            if ($allPaid) {
                $period = HrPayrollPeriod::find($periodId);
                if ($period && $period->status !== 'closed') {
                    $period->markAsPaid();
                }
            }
        }

        return back()->with('success', 'Payroll marked as paid.');
    }

    public function bulkPay(Request $request, HrPayrollPeriod $period): RedirectResponse
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:hr_payrolls,id',
            'payment_date' => 'required|date',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $paid = HrPayroll::whereIn('id', $validated['payroll_ids'])
            ->where('status', PayrollStatus::APPROVED)
            ->update([
                'status' => PayrollStatus::PAID,
                'payment_date' => $validated['payment_date'],
                'payment_reference' => $validated['payment_reference'],
            ]);

        // Update loan repayments
        HrLoanRepayment::whereIn('hr_payroll_id', $validated['payroll_ids'])
            ->update([
                'status' => 'paid',
                'paid_date' => $validated['payment_date'],
            ]);

        // Update period
        $allPaid = HrPayroll::where('hr_payroll_period_id', $period->id)
            ->where('status', '!=', PayrollStatus::PAID)
            ->doesntExist();

        if ($allPaid) {
            $period->markAsPaid();
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
        $payrolls = HrPayroll::with('employee')
            ->where('hr_payroll_period_id', $period->id)
            ->where('payment_mode', 'bank_transfer')
            ->whereIn('status', [PayrollStatus::APPROVED, PayrollStatus::PAID])
            ->orderBy('employee_name')
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
            'total_admin' => $payrolls->sum('pf_admin_charges'),
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

        // Get attendance summary for the period
        $attendanceSummary = $this->getAttendanceSummary($employee, $period);

        // Calculate paid days
        $paidDays = $attendanceSummary['paid_days'];
        $lopDays = $period->working_days - $paidDays;

        // Calculate per day salary
        $perDaySalary = $salary->monthly_gross / 30;

        // Create/Update payroll record
        $payroll = HrPayroll::updateOrCreate(
            [
                'hr_payroll_period_id' => $period->id,
                'hr_employee_id' => $employee->id,
            ],
            [
                'payroll_number' => HrPayroll::generateNumber($period->id),
                'hr_employee_salary_id' => $salary->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->full_name,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'bank_account' => $employee->bank_account_number,
                'bank_ifsc' => $employee->bank_ifsc,
                'payment_mode' => $employee->payment_mode,
                
                // Attendance
                'working_days' => $period->working_days,
                'present_days' => $attendanceSummary['present'],
                'paid_days' => $paidDays,
                'absent_days' => $attendanceSummary['absent'],
                'leave_days' => $attendanceSummary['leave'],
                'holidays' => $attendanceSummary['holiday'],
                'week_offs' => $attendanceSummary['weekly_off'],
                'half_days' => $attendanceSummary['half_day'],
                'late_days' => $attendanceSummary['late'],
                'ot_hours' => $attendanceSummary['ot_hours'],
                'lop_days' => $lopDays,
                
                'status' => PayrollStatus::PROCESSED,
                'created_by' => auth()->id(),
            ]
        );

        // Calculate earnings
        $this->calculateEarnings($payroll, $salary, $paidDays, $attendanceSummary);

        // Calculate statutory deductions
        $this->calculateStatutoryDeductions($payroll, $employee);

        // Calculate loan and advance deductions
        $this->calculateLoanDeductions($payroll, $employee, $period);

        // Calculate LOP deduction
        if ($lopDays > 0) {
            $payroll->lop_deduction = round($perDaySalary * $lopDays, 2);
        }

        // Calculate totals
        $payroll->calculateTotals();
        $payroll->save();

        return $payroll;
    }

    private function getAttendanceSummary(HrEmployee $employee, HrPayrollPeriod $period): array
    {
        $attendances = HrAttendance::where('hr_employee_id', $employee->id)
            ->whereBetween('attendance_date', [$period->attendance_start, $period->attendance_end])
            ->get();

        return [
            'present' => $attendances->whereIn('status', ['present', 'late', 'early_leaving', 'late_and_early', 'on_duty'])->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'half_day' => $attendances->where('status', 'half_day')->count(),
            'leave' => $attendances->where('status', 'leave')->count(),
            'weekly_off' => $attendances->where('status', 'weekly_off')->count(),
            'holiday' => $attendances->where('status', 'holiday')->count(),
            'late' => $attendances->where('late_minutes', '>', 0)->count(),
            'ot_hours' => $attendances->sum('ot_hours_approved'),
            'paid_days' => $attendances->sum('paid_days'),
        ];
    }

    private function calculateEarnings(HrPayroll $payroll, HrEmployeeSalary $salary, float $paidDays, array $attendance): void
    {
        $ratio = $paidDays / 30;

        // Get component values from employee salary
        $components = $salary->components()->with('salaryComponent')->get();

        foreach ($components as $comp) {
            $amount = round($comp->monthly_amount * $ratio, 2);
            $componentType = $comp->salaryComponent->category;

            match ($componentType) {
                'basic' => $payroll->basic = $amount,
                'hra' => $payroll->hra = $amount,
                'da' => $payroll->da = $amount,
                'special_allowance' => $payroll->special_allowance = $amount,
                'conveyance' => $payroll->conveyance = $amount,
                'medical' => $payroll->medical = $amount,
                default => $payroll->other_earnings += $amount,
            };

            // Store component detail
            HrPayrollComponent::updateOrCreate(
                [
                    'hr_payroll_id' => $payroll->id,
                    'hr_salary_component_id' => $comp->hr_salary_component_id,
                ],
                [
                    'component_code' => $comp->salaryComponent->code,
                    'component_name' => $comp->salaryComponent->name,
                    'component_type' => $comp->salaryComponent->component_type,
                    'base_amount' => $comp->monthly_amount,
                    'calculated_amount' => $amount,
                    'final_amount' => $amount,
                    'sort_order' => $comp->salaryComponent->sort_order,
                ]
            );
        }

        // Calculate OT
        if ($attendance['ot_hours'] > 0) {
            $hourlyRate = ($payroll->basic / 30) / 8;
            $payroll->ot_amount = round($hourlyRate * $attendance['ot_hours'] * 1.5, 2);
        }
    }

    private function calculateStatutoryDeductions(HrPayroll $payroll, HrEmployee $employee): void
    {
        // PF Calculation
        if ($employee->pf_applicable) {
            $pfSlab = HrPfSlab::where('is_active', true)
                ->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')
                ->first();

            if ($pfSlab) {
                $pfWages = min($payroll->basic, $pfSlab->wage_ceiling);
                $payroll->pf_employee = round($pfWages * $pfSlab->employee_contribution_rate / 100, 0);
                $payroll->pf_employer = round($pfWages * $pfSlab->employer_pf_rate / 100, 0);
                $payroll->eps_employer = round($pfWages * $pfSlab->employer_eps_rate / 100, 0);
                $payroll->edli_employer = round($pfWages * $pfSlab->employer_edli_rate / 100, 0);
                $payroll->pf_admin_charges = round($pfWages * ($pfSlab->admin_charges_rate + $pfSlab->edli_admin_rate) / 100, 0);
            }
        }

        // ESI Calculation
        if ($employee->esi_applicable) {
            $esiSlab = HrEsiSlab::where('is_active', true)
                ->where('effective_from', '<=', now())
                ->orderByDesc('effective_from')
                ->first();

            if ($esiSlab && $payroll->gross_salary <= $esiSlab->wage_ceiling) {
                $payroll->esi_employee = round($payroll->gross_salary * $esiSlab->employee_rate / 100, 0);
                $payroll->esi_employer = round($payroll->gross_salary * $esiSlab->employer_rate / 100, 0);
            }
        }

        // Professional Tax
        if ($employee->pt_applicable && $employee->pt_state) {
            $ptSlab = HrProfessionalTaxSlab::where('state_code', $employee->pt_state)
                ->where('is_active', true)
                ->where('salary_from', '<=', $payroll->gross_salary)
                ->where('salary_to', '>=', $payroll->gross_salary)
                ->first();

            if ($ptSlab) {
                $payroll->professional_tax = $ptSlab->tax_amount;
            }
        }

        // TDS would be calculated based on annual projections and declarations
        // Simplified for now
        if ($employee->tds_applicable) {
            // TDS calculation logic would go here
            $payroll->tds = 0;
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

        // Active advances
        $advances = HrSalaryAdvance::where('hr_employee_id', $employee->id)
            ->where('status', 'recovering')
            ->where('balance_amount', '>', 0)
            ->get();

        foreach ($advances as $advance) {
            $deduction = min($advance->monthly_deduction, $advance->balance_amount);
            $payroll->advance_deduction += $deduction;

            $newBalance = $advance->balance_amount - $deduction;
            $advance->update([
                'recovered_amount' => $advance->recovered_amount + $deduction,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'closed' : 'recovering',
            ]);
        }
    }
}