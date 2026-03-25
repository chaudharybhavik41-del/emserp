<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Hr\Concerns\AuthorizesEmployeeWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Hr\HrAttendancePolicy;
use App\Models\Hr\HrCandidate;
use App\Models\Hr\HrDesignation;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrGrade;
use App\Models\Hr\HrLeavePolicy;
use App\Models\Hr\HrSalaryStructure;
use App\Models\Hr\HrShift;
use App\Models\Hr\HrWorkLocation;
use App\Models\User;
use App\Services\Accounting\AccountingFinancialYearService;
use App\Services\Accounting\EmployeeAccountService;
use App\Services\Hr\PayrollPeriodStalenessService;
use App\Services\Hr\EmployeeUserProvisioningService;
use App\Enums\Hr\EmployeeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HrEmployeeController extends Controller
{
    use AuthorizesEmployeeWorkspace;

    public function __construct(
        protected EmployeeAccountService $employeeAccountService
    )
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.employee.view')->only(['index']);
        $this->middleware('permission:hr.employee.create')->only(['create', 'store']);
        $this->middleware('permission:hr.employee.update')->only(['edit', 'update']);
        $this->middleware('permission:hr.employee.delete')->only(['destroy']);
        $this->middleware('permission:hr.employee.confirm')->only(['confirm']);
        $this->middleware('permission:hr.employee.separation')->only(['separation']);
    }

    public function index(Request $request): View
    {
        $query = HrEmployee::with(['department', 'designation', 'grade', 'reportingManager'])
            ->withCount(['salaries', 'payrolls'])
            ->orderBy('employee_code');

        // Filters
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('personal_mobile', 'like', "%{$search}%")
                    ->orWhere('official_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->active();
            } else {
                $query->where('status', $status);
            }
        }

        if ($department = $request->get('department_id')) {
            $query->where('department_id', $department);
        }

        if ($designation = $request->get('designation_id')) {
            $query->where('hr_designation_id', $designation);
        }

        if ($employmentType = $request->get('employment_type')) {
            $query->where('employment_type', $employmentType);
        }

        $employees = $query->paginate(25)->withQueryString();

        // For filters
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $designations = HrDesignation::where('is_active', true)->orderBy('name')->get();
        $statuses = EmployeeStatus::options();
        $employmentTypes = [
            'permanent' => 'Permanent',
            'probation' => 'Probation',
            'contract' => 'Contract',
            'trainee' => 'Trainee',
            'intern' => 'Intern',
            'consultant' => 'Consultant',
            'casual' => 'Casual',
            'daily_wage' => 'Daily Wage',
        ];

        // Stats
        $stats = [
            'total' => HrEmployee::count(),
            'active' => HrEmployee::active()->count(),
            'on_probation' => HrEmployee::onProbation()->count(),
            'left_this_month' => HrEmployee::whereMonth('date_of_leaving', now()->month)
                ->whereYear('date_of_leaving', now()->year)->count(),
        ];

        return view('hr.employees.index', compact(
            'employees', 'departments', 'designations', 'statuses', 
            'employmentTypes', 'stats'
        ));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $employee = new HrEmployee();
        $employee->employee_code = HrEmployee::generateEmployeeCode();

        $sourceCandidate = null;
        if ($candidateId = $request->integer('candidate_id')) {
            $sourceCandidate = HrCandidate::query()->findOrFail($candidateId);

            if ($sourceCandidate->converted_hr_employee_id) {
                return redirect()
                    ->route('hr.employees.show', $sourceCandidate->converted_hr_employee_id)
                    ->with('info', 'This candidate is already converted to an employee.');
            }

            $this->prefillEmployeeFromCandidate($employee, $sourceCandidate);
        }

        return view('hr.employees.form', $this->getFormData($employee, $sourceCandidate));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeBooleanInputs($request);
        $validated = $this->validateEmployee($request);

        DB::beginTransaction();
        try {
            $sourceCandidate = null;
            if ($sourceCandidateId = $request->integer('source_candidate_id')) {
                $sourceCandidate = HrCandidate::query()->lockForUpdate()->findOrFail($sourceCandidateId);

                if ($sourceCandidate->converted_hr_employee_id) {
                    throw ValidationException::withMessages([
                        'source_candidate_id' => 'This candidate is already converted to an employee.',
                    ]);
                }
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $validated['photo_path'] = $request->file('photo')
                    ->store('hr/employees/photos', 'public');
            }

            $this->applyDerivedEmployeeFields($validated, $request);
            $validated['created_by'] = auth()->id();
            
            $employee = HrEmployee::create($validated);

            if ($sourceCandidate) {
                $sourceCandidate->update([
                    'status' => 'joined',
                    'converted_hr_employee_id' => $employee->id,
                    'converted_at' => now(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $this->employeeAccountService->syncAccountForEmployee($employee);

            // Create/link user account + set user's primary department (if requested)
            if ($request->boolean('create_user_account')) {
                app(EmployeeUserProvisioningService::class)->provisionForEmployee($employee);
            }

DB::commit();

            return redirect()
                ->route('hr.employees.show', $employee)
                ->with('success', 'Employee created successfully.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to create employee: ' . $e->getMessage());
        }
    }

    public function show(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        return $this->renderHub($employee, 'overview');
    }

    public function myWorkspace(?string $section = null): RedirectResponse
    {
        $employee = $this->resolveOwnEmployee();

        $routeMap = [
            'overview' => 'hr.employees.show',
            'attendance' => 'hr.employees.attendance',
            'leave' => 'hr.employees.leave',
            'salary' => 'hr.employees.salary.show',
            'payroll' => 'hr.employees.payroll',
            'loans-advances' => 'hr.employees.loans-advances',
            'compliance' => 'hr.employees.compliance',
            'timeline' => 'hr.employees.timeline',
        ];

        $targetRoute = $routeMap[$section ?: 'overview'] ?? $routeMap['overview'];

        return redirect()->route($targetRoute, $employee);
    }

    public function attendance(Request $request, HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        $selectedMonth = Carbon::parse(($request->string('month')->toString() ?: now()->format('Y-m')) . '-01');
        $attendanceRows = $employee->attendances()
            ->with('shift')
            ->forMonth($selectedMonth->year, $selectedMonth->month)
            ->orderByDesc('attendance_date')
            ->get();

        return $this->renderHub($employee, 'attendance', [
            'selectedMonth' => $selectedMonth,
            'attendanceRows' => $attendanceRows,
            'attendanceSummary' => [
                'paid_days' => $attendanceRows->sum('paid_days'),
                'present_days' => $attendanceRows->whereIn('status', ['present', 'late', 'early_leaving', 'late_and_early', 'on_duty'])->count(),
                'absent_days' => $attendanceRows->where('status', 'absent')->count(),
                'leave_days' => $attendanceRows->where('status', 'leave')->sum('paid_days'),
                'ot_hours' => $attendanceRows->sum('ot_hours_approved'),
            ],
        ]);
    }

    public function leave(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        return $this->renderHub($employee, 'leave', [
            'leaveBalances' => $employee->leaveBalances()
                ->with('leaveType')
                ->where('year', now()->year)
                ->orderBy('hr_leave_type_id')
                ->get(),
            'leaveApplications' => $employee->leaveApplications()
                ->with(['leaveType', 'approvedBy'])
                ->latest('from_date')
                ->limit(12)
                ->get(),
        ]);
    }

    public function payroll(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee, ['hr.employee.view', 'hr.payroll.view']);

        $currentRange = app(AccountingFinancialYearService::class)->currentRange();
        $ytdPayrolls = $employee->payrolls()
            ->whereHas('period', function ($query) use ($currentRange) {
                $query->whereBetween('period_end', [$currentRange['start'], $currentRange['end']]);
            })
            ->get();

        return $this->renderHub($employee, 'payroll', [
            'employeePayrolls' => $employee->payrolls()
                ->with('period')
                ->latest('id')
                ->limit(12)
                ->get(),
            'payrollYtd' => [
                'gross' => $ytdPayrolls->sum('gross_salary'),
                'deductions' => $ytdPayrolls->sum('total_deductions'),
                'net_payable' => $ytdPayrolls->sum('net_payable'),
                'paid_count' => $ytdPayrolls->where('status', 'paid')->count(),
            ],
        ]);
    }

    public function loansAdvances(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        $loans = $employee->loans()
            ->with('loanType')
            ->latest('application_date')
            ->limit(10)
            ->get();

        $advances = $employee->advances()
            ->latest('application_date')
            ->limit(10)
            ->get();

        return $this->renderHub($employee, 'loans-advances', [
            'employeeLoans' => $loans,
            'employeeAdvances' => $advances,
            'loanAdvanceSummary' => [
                'loan_outstanding' => $loans->sum('total_outstanding'),
                'loan_recovered' => $loans->sum('total_recovered'),
                'advance_balance' => $advances->sum('balance_amount'),
                'advance_recovered' => $advances->sum('recovered_amount'),
            ],
        ]);
    }

    public function compliance(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        return $this->renderHub($employee, 'compliance', [
            'taxDeclarations' => $employee->taxDeclarations()
                ->latest('financial_year')
                ->limit(5)
                ->get(),
        ]);
    }

    public function timeline(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        return $this->renderHub($employee, 'timeline', [
            'timelineEvents' => $this->buildTimelineEvents($employee),
        ]);
    }

    public function edit(HrEmployee $employee): View
    {
        return view('hr.employees.form', $this->getFormData($employee));
    }

  public function update(Request $request, HrEmployee $employee): RedirectResponse
{
    $this->normalizeBooleanInputs($request);

    $validated = $this->validateEmployee($request, $employee->id);

    DB::beginTransaction();

    try {
        $payrollImpactFields = [
            'default_shift_id',
            'hr_attendance_policy_id',
            'overtime_applicable',
            'ot_hourly_rate',
            'pf_applicable',
            'esi_applicable',
            'pt_applicable',
            'pt_state',
            'lwf_applicable',
            'tds_applicable',
            'tax_regime',
            'date_of_joining',
            'date_of_leaving',
        ];
        $originalImpactData = $employee->only($payrollImpactFields);

        // Photo upload
        if ($request->hasFile('photo')) {
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }

            $validated['photo_path'] = $request->file('photo')
                ->store('hr/employees/photos', 'public');
        }

        $this->applyDerivedEmployeeFields($validated, $request);
        $validated['updated_by'] = auth()->id();

        $employee->update($validated);

        $this->employeeAccountService->syncAccountForEmployee($employee->fresh());

        if ($employee->user_id || $request->boolean('create_user_account')) {
            app(EmployeeUserProvisioningService::class)->provisionForEmployee($employee->fresh());
        }

        $freshEmployee = $employee->fresh();
        $impactChanged = collect($payrollImpactFields)->contains(function (string $field) use ($freshEmployee, $originalImpactData): bool {
            return (string) ($originalImpactData[$field] ?? '') !== (string) ($freshEmployee->{$field} ?? '');
        });

        if ($impactChanged) {
            $effectiveDate = collect([
                $originalImpactData['date_of_joining'] ?? null,
                $originalImpactData['date_of_leaving'] ?? null,
                $freshEmployee->date_of_joining?->toDateString(),
                $freshEmployee->date_of_leaving?->toDateString(),
                now()->startOfMonth()->toDateString(),
            ])->filter()->sort()->first();

            app(PayrollPeriodStalenessService::class)
                ->markPeriodsFromDate($effectiveDate, "Employee master updated for {$freshEmployee->employee_code}.");
        }

        DB::commit();

        return redirect()
            ->route('hr.employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    } catch (ValidationException $e) {
        DB::rollBack();
        throw $e;
    } catch (\Exception $e) {
        DB::rollBack();

        return back()
            ->withInput()
            ->with('error', 'Failed to update employee: ' . $e->getMessage());
    }
}

    public function destroy(HrEmployee $employee): RedirectResponse
    {
        if ($reason = $employee->deletionBlockedReason()) {
            return back()->with('error', $reason);
        }

        DB::beginTransaction();
        try {
            // Delete photo
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }

            $employee->delete();

            DB::commit();

            return redirect()
                ->route('hr.employees.index')
                ->with('success', 'Employee deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete employee: ' . $e->getMessage());
        }
    }

    private function renderHub(HrEmployee $employee, string $activeSection, array $data = []): View
    {
        $employee = $this->loadHubEmployee($employee);
        $ledgerReportRange = app(AccountingFinancialYearService::class)->currentRange();
        $isOwnWorkspace = $this->isOwnEmployee($employee);
        $canManageEmployee = $this->hasAnyHrPermission([
            'hr.employee.view',
            'hr.employee.update',
            'hr.payroll.view',
            'hr.leave.view',
        ]);

        $overviewAttendance = $employee->attendances()
            ->with('shift')
            ->orderByDesc('attendance_date')
            ->limit(10)
            ->get();

        $overviewLeaveBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->where('year', now()->year)
            ->get();

        $overviewPayrolls = $employee->payrolls()
            ->with('period')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $currentMonthAttendance = $employee->attendances()
            ->forMonth(now()->year, now()->month)
            ->get();

        $hubStats = [
            'attendance_paid_days' => $currentMonthAttendance->sum('paid_days'),
            'leave_balance' => $overviewLeaveBalances->sum(fn ($balance) => (float) $balance->available_balance),
            'latest_net_pay' => (float) ($overviewPayrolls->first()?->net_payable ?? 0),
            'loan_outstanding' => (float) $employee->loans()->sum('total_outstanding'),
            'advance_balance' => (float) $employee->advances()->sum('balance_amount'),
        ];

        return view('hr.employees.show', array_merge([
            'employee' => $employee,
            'activeSection' => $activeSection,
            'recentAttendance' => $overviewAttendance,
            'leaveBalances' => $overviewLeaveBalances,
            'recentPayrolls' => $overviewPayrolls,
            'ledgerReportRange' => $ledgerReportRange,
            'hubStats' => $hubStats,
            'isOwnWorkspace' => $isOwnWorkspace,
            'canManageEmployee' => $canManageEmployee,
        ], $data));
    }

    private function resolveOwnEmployee(): HrEmployee
    {
        $query = HrEmployee::query();

        if (method_exists(HrEmployee::class, 'scopeActive')) {
            $query->active();
        } else {
            $query->where('status', 'active');
        }

        $employee = $query->where('user_id', auth()->id())->first();

        if (! $employee) {
            abort(403, 'No active employee record is linked to your user account.');
        }

        return $employee;
    }

    private function loadHubEmployee(HrEmployee $employee): HrEmployee
    {
        $employee->load([
            'department',
            'designation',
            'grade',
            'reportingManager',
            'subordinates',
            'workLocation',
            'defaultShift',
            'currentSalary.components',
            'salaryStructure',
            'leaveBalances.leaveType',
            'documents',
            'qualifications',
            'experiences',
            'dependents',
            'nominees',
            'accountingLedger',
            'bankAccounts',
            'assets' => fn($q) => $q->where('status', 'issued'),
            'trainings' => fn($q) => $q->latest()->limit(5),
        ]);

        return $employee;
    }

    private function buildTimelineEvents(HrEmployee $employee)
    {
        $events = collect();

        if ($employee->date_of_joining) {
            $events->push([
                'date' => $employee->date_of_joining->copy(),
                'title' => 'Employee joined',
                'meta' => $employee->department?->name ?: 'Employment started',
                'tone' => 'success',
                'action_url' => route('hr.employees.show', $employee),
            ]);
        }

        if ($employee->confirmation_date) {
            $events->push([
                'date' => $employee->confirmation_date->copy(),
                'title' => 'Confirmed',
                'meta' => 'Probation completed',
                'tone' => 'primary',
                'action_url' => route('hr.employees.show', $employee),
            ]);
        }

        if ($employee->date_of_leaving) {
            $events->push([
                'date' => $employee->date_of_leaving->copy(),
                'title' => 'Separated',
                'meta' => $employee->leaving_reason ?: 'Employee separation recorded',
                'tone' => 'danger',
                'action_url' => route('hr.employees.show', $employee),
            ]);
        }

        foreach ($employee->salaries()->latest('effective_from')->limit(10)->get() as $salary) {
            $events->push([
                'date' => $salary->effective_from?->copy() ?: $salary->created_at,
                'title' => 'Salary updated',
                'meta' => 'Monthly gross ₹' . number_format((float) $salary->monthly_gross, 2),
                'tone' => 'info',
                'action_url' => route('hr.employees.salary.show', $employee),
            ]);
        }

        foreach ($employee->payrolls()->with('period')->latest('id')->limit(10)->get() as $payroll) {
            $events->push([
                'date' => $payroll->payment_date ?: $payroll->created_at,
                'title' => 'Payroll ' . $payroll->status->label(),
                'meta' => trim(($payroll->period?->name ?: $payroll->payroll_number) . ' | Net ₹' . number_format((float) $payroll->net_payable, 2)),
                'tone' => $payroll->status->color(),
                'action_url' => route('hr.payroll.show', $payroll),
            ]);
        }

        foreach ($employee->leaveApplications()->with('leaveType')->latest('from_date')->limit(10)->get() as $application) {
            $events->push([
                'date' => $application->from_date?->copy() ?: $application->created_at,
                'title' => 'Leave ' . $application->status->label(),
                'meta' => trim(($application->leaveType?->name ?: 'Leave') . ' | ' . $application->period_text),
                'tone' => $application->status->color(),
                'action_url' => route('hr.leave-applications.show', $application),
            ]);
        }

        foreach ($employee->loans()->latest('application_date')->limit(10)->get() as $loan) {
            $events->push([
                'date' => $loan->disbursement_date ?: $loan->application_date ?: $loan->created_at,
                'title' => 'Loan ' . ucfirst((string) $loan->status),
                'meta' => trim(($loan->loan_number ?: 'Loan') . ' | ₹' . number_format((float) ($loan->disbursed_amount ?: $loan->approved_amount ?: $loan->applied_amount), 2)),
                'tone' => in_array($loan->status, ['rejected', 'cancelled'], true) ? 'danger' : 'warning',
                'action_url' => route('hr.loans.employee-loans.show', $loan),
            ]);
        }

        foreach ($employee->advances()->latest('application_date')->limit(10)->get() as $advance) {
            $events->push([
                'date' => $advance->disbursement_date ?: $advance->application_date ?: $advance->created_at,
                'title' => 'Salary advance ' . ucfirst((string) $advance->status),
                'meta' => trim(($advance->advance_number ?: 'Advance') . ' | ₹' . number_format((float) ($advance->disbursed_amount ?: $advance->approved_amount ?: $advance->requested_amount), 2)),
                'tone' => in_array($advance->status, ['rejected', 'cancelled'], true) ? 'danger' : 'secondary',
                'action_url' => route('hr.advances.salary-advances.show', $advance),
            ]);
        }

        return $events
            ->filter(fn ($event) => !empty($event['date']))
            ->sortByDesc(fn ($event) => $event['date'])
            ->take(30)
            ->values();
    }

    // Additional Actions

    public function confirm(HrEmployee $employee): RedirectResponse
    {
        if ($employee->confirmation_date) {
            return back()->with('error', 'Employee is already confirmed.');
        }

        $employee->update([
            'confirmation_date' => now(),
            'employment_type' => 'permanent',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Employee confirmed successfully.');
    }

    public function separation(Request $request, HrEmployee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'date_of_leaving' => 'required|date',
            'leaving_reason' => 'required|string|max:255',
            'status' => 'required|in:resigned,terminated,absconded,retired',
        ]);

        $employee->update([
            'date_of_leaving' => $validated['date_of_leaving'],
            'leaving_reason' => $validated['leaving_reason'],
            'status' => $validated['status'],
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        $this->employeeAccountService->syncAccountForEmployee($employee->fresh());

        // Deactivate user account
        if ($employee->user) {
            $employee->user->update(['is_active' => false]);
        }

        return back()->with('success', 'Employee separation processed successfully.');
    }

    public function idCard(HrEmployee $employee): View
    {
        $this->authorizeEmployeeRead($employee);

        $employee->load(['department', 'designation']);
        return view('hr.employees.id-card', compact('employee'));
    }

    // Private Methods

    private function getFormData(HrEmployee $employee, ?HrCandidate $sourceCandidate = null): array
    {
        $activeOrSelected = static fn ($query, $selectedId) => $query
            ->when(
                $selectedId,
                fn ($q) => $q->where('is_active', true)->orWhere($q->getModel()->getQualifiedKeyName(), $selectedId),
                fn ($q) => $q->where('is_active', true)
            );

        return [
            'employee' => $employee,
            'sourceCandidate' => $sourceCandidate,
            'departments' => $activeOrSelected(Department::query(), $employee->department_id)->orderBy('name')->get(),
            'designations' => $activeOrSelected(HrDesignation::query(), $employee->hr_designation_id)->orderBy('name')->get(),
            'grades' => $activeOrSelected(HrGrade::query(), $employee->hr_grade_id)->orderBy('level')->get(),
            'shifts' => $activeOrSelected(HrShift::query(), $employee->default_shift_id)->orderBy('name')->get(),
            'locations' => $activeOrSelected(HrWorkLocation::query(), $employee->work_location_id)->orderBy('name')->get(),
            'attendancePolicies' => $activeOrSelected(HrAttendancePolicy::query(), $employee->hr_attendance_policy_id)->orderBy('name')->get(),
            'leavePolicies' => $activeOrSelected(HrLeavePolicy::query(), $employee->hr_leave_policy_id)->orderBy('name')->get(),
            'salaryStructures' => $activeOrSelected(HrSalaryStructure::query(), $employee->hr_salary_structure_id)->orderBy('name')->get(),
            'managers' => HrEmployee::query()
                ->when(
                    $employee->reporting_to,
                    fn ($q) => $q->active()->orWhere($q->getModel()->getQualifiedKeyName(), $employee->reporting_to),
                    fn ($q) => $q->active()
                )
                ->orderBy('first_name')
                ->get(),
            'statuses' => EmployeeStatus::options(),
            'employmentTypes' => [
                'permanent' => 'Permanent',
                'probation' => 'Probation',
                'contract' => 'Contract',
                'trainee' => 'Trainee',
                'intern' => 'Intern',
                'consultant' => 'Consultant',
                'casual' => 'Casual',
                'daily_wage' => 'Daily Wage',
            ],
            'employeeCategories' => [
                'staff' => 'Staff',
                'worker' => 'Worker',
                'supervisor' => 'Supervisor',
                'manager' => 'Manager',
                'executive' => 'Executive',
                'contractor_employee' => 'Contractor Employee',
            ],
            'genders' => [
                'male' => 'Male',
                'female' => 'Female',
                'other' => 'Other',
            ],
            'maritalStatuses' => [
                'single' => 'Single',
                'married' => 'Married',
                'divorced' => 'Divorced',
                'widowed' => 'Widowed',
            ],
            'bloodGroups' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'states' => $this->getIndianStates(),
        ];
    }

    private function validateEmployee(Request $request, ?int $employeeId = null): array
    {
        $uniqueRule = $employeeId ? "unique:hr_employees,employee_code,{$employeeId}" : 'unique:hr_employees,employee_code';

        $rules = [
            'source_candidate_id' => ['nullable', 'exists:hr_candidates,id'],
            'employee_code' => ['required', 'string', 'max:20', $uniqueRule],
            'biometric_id' => ['nullable', 'string', 'max:50'],
            'card_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:200'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'spouse_name' => ['nullable', 'string', 'max:200'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'caste_category' => ['nullable', 'string', 'max:50'],
            
            'personal_email' => ['nullable', 'email', 'max:150'],
            'official_email' => ['nullable', 'email', 'max:150'],
            'personal_mobile' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:200'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            
            'present_address' => ['nullable', 'string'],
            'present_city' => ['nullable', 'string', 'max:100'],
            'present_state' => ['nullable', 'string', 'max:100'],
            'present_pincode' => ['nullable', 'string', 'max:10'],
            'permanent_address' => ['nullable', 'string'],
            'permanent_city' => ['nullable', 'string', 'max:100'],
            'permanent_state' => ['nullable', 'string', 'max:100'],
            'permanent_pincode' => ['nullable', 'string', 'max:10'],
            'address_same_as_present' => ['nullable', 'boolean'],
            
            'pan_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhar_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{12}$/'],
            'passport_number' => ['nullable', 'string', 'max:20'],
            'passport_expiry' => ['nullable', 'date'],
            'driving_license' => ['nullable', 'string', 'max:30'],
            'dl_expiry' => ['nullable', 'date'],
            'voter_id' => ['nullable', 'string', 'max:30'],
            
            'date_of_joining' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'employment_type' => ['required', 'in:permanent,probation,contract,trainee,intern,consultant,casual,daily_wage'],
            'employee_category' => ['required', 'in:staff,worker,supervisor,manager,executive,contractor_employee'],
            'probation_period_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            
            'department_id' => ['nullable', 'exists:departments,id'],
            'hr_designation_id' => ['nullable', 'exists:hr_designations,id'],
            'hr_grade_id' => ['nullable', 'exists:hr_grades,id'],
            'reporting_to' => ['nullable', 'exists:hr_employees,id'],
            'cost_center' => ['nullable', 'string', 'max:50'],
            'work_location_id' => ['nullable', 'exists:hr_work_locations,id'],
            
            'default_shift_id' => ['nullable', 'exists:hr_shifts,id'],
            'hr_attendance_policy_id' => ['nullable', 'exists:hr_attendance_policies,id'],
            'hr_leave_policy_id' => ['nullable', 'exists:hr_leave_policies,id'],
            'overtime_applicable' => ['nullable', 'boolean'],
            'ot_hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'attendance_mode' => ['nullable', 'in:biometric,manual,both'],
            
            'hr_salary_structure_id' => ['nullable', 'exists:hr_salary_structures,id'],
            'payment_mode' => ['nullable', 'in:bank_transfer,cheque,cash'],
            'pf_applicable' => ['nullable', 'boolean'],
            'pf_number' => ['nullable', 'string', 'max:30'],
            'pf_join_date' => ['nullable', 'date'],
            'eps_applicable' => ['nullable', 'boolean'],
            'esi_applicable' => ['nullable', 'boolean'],
            'esi_number' => ['nullable', 'string', 'max:30'],
            'esi_join_date' => ['nullable', 'date'],
            'pt_applicable' => ['nullable', 'boolean'],
            'pt_state' => ['nullable', 'string', 'max:50'],
            'lwf_applicable' => ['nullable', 'boolean'],
            'tds_applicable' => ['nullable', 'boolean'],
            'tax_regime' => ['nullable', 'in:old,new'],
            
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_branch' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
            'bank_account_type' => ['nullable', 'in:savings,current,salary'],
            
            'gratuity_applicable' => ['nullable', 'boolean'],
            'health_insurance_enrolled' => ['nullable', 'boolean'],
            'health_insurance_policy_no' => ['nullable', 'string', 'max:50'],
            'sum_insured' => ['nullable', 'numeric', 'min:0'],
            
            'highest_qualification' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'total_experience_months' => ['nullable', 'integer', 'min:0'],
            
            'status' => ['nullable', 'in:active,inactive,resigned,terminated,absconded,retired,deceased'],
            'is_active' => ['nullable', 'boolean'],
            
            'photo' => ['nullable', 'image', 'max:2048'],
        ];

        // If we are creating/linking a login user account (or already linked), require Official Email and Department.
        // This prevents partial records that cannot be linked to users/departments.
        $existingUserId = null;
        if ($employeeId) {
            $existingUserId = HrEmployee::whereKey($employeeId)->value('user_id');
        }

        $needsLogin = $request->boolean('create_user_account') || !is_null($existingUserId);

        if ($needsLogin) {
            // Official Email must exist to create/link a user.
            if (isset($rules['official_email']) && is_array($rules['official_email'])) {
                $rules['official_email'][] = 'required';
            }

            // Department must exist so we can set user's primary department.
            if (isset($rules['department_id']) && is_array($rules['department_id'])) {
                // Replace nullable with required (keeping exists rule).
                $rules['department_id'] = array_values(array_unique(array_merge(['required'], $rules['department_id'])));
            }
        }

        $attendancePolicyId = $request->input('hr_attendance_policy_id');
        if ($attendancePolicyId) {
            $policyBasis = HrAttendancePolicy::query()->whereKey($attendancePolicyId)->value('ot_calculation_basis');
            if ($policyBasis === 'fixed') {
                $rules['ot_hourly_rate'] = ['required', 'numeric', 'min:0.01'];
            }
        }

        return $request->validate($rules);

    }

    private function prefillEmployeeFromCandidate(HrEmployee $employee, HrCandidate $candidate): void
    {
        $employee->first_name = $candidate->first_name;
        $employee->last_name = $candidate->last_name;
        $employee->personal_email = $candidate->email;
        $employee->personal_mobile = $candidate->phone;
        $employee->present_city = $candidate->current_location;
        $employee->permanent_city = $candidate->current_location;
        $employee->notice_period_days = $candidate->notice_period_days;
        $employee->total_experience_months = $candidate->total_experience_months;
        $employee->specialization = $candidate->position_applied;
        $employee->date_of_joining = now()->toDateString();
        $employee->employment_type = 'probation';
        $employee->employee_category = 'staff';
        $employee->status = 'active';
        $employee->is_active = true;
    }

    private function normalizeBooleanInputs(Request $request): void
    {
        $booleanFields = [
            'address_same_as_present',
            'overtime_applicable',
            'pf_applicable',
            'eps_applicable',
            'esi_applicable',
            'pt_applicable',
            'lwf_applicable',
            'tds_applicable',
            'gratuity_applicable',
            'health_insurance_enrolled',
            'is_active',
            'create_user_account',
        ];

        $request->merge(
            collect($booleanFields)
                ->mapWithKeys(fn ($field) => [$field => $request->boolean($field)])
                ->all()
        );
    }

    private function applyDerivedEmployeeFields(array &$validated, Request $request): void
    {
        if ($request->boolean('address_same_as_present')) {
            $validated['permanent_address'] = $validated['present_address'] ?? null;
            $validated['permanent_city'] = $validated['present_city'] ?? null;
            $validated['permanent_state'] = $validated['present_state'] ?? null;
            $validated['permanent_pincode'] = $validated['present_pincode'] ?? null;
        }

        if (!$request->boolean('pf_applicable')) {
            $validated['pf_number'] = null;
            $validated['pf_join_date'] = null;
            $validated['eps_applicable'] = false;
        }

        if (!$request->boolean('esi_applicable')) {
            $validated['esi_number'] = null;
            $validated['esi_join_date'] = null;
        }

        if (!$request->boolean('pt_applicable')) {
            $validated['pt_state'] = null;
        }

        if (!$request->boolean('health_insurance_enrolled')) {
            $validated['health_insurance_policy_no'] = null;
            $validated['sum_insured'] = 0;
        }
    }

    private function getIndianStates(): array
    {
        return [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
            'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
            'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
            'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
            'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
        ];
    }
}
