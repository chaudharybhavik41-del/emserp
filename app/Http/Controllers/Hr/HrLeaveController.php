<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrLeaveType;
use App\Models\Hr\HrLeaveBalance;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrLeaveTransaction;
use App\Models\Hr\HrHoliday;
use App\Enums\Hr\LeaveStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class HrLeaveController extends Controller
{
    /**
     * Tiny cache for Schema::hasColumn lookups.
     *
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (!array_key_exists($key, self::$columnCache)) {
            self::$columnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$columnCache[$key];
    }

    /**
     * Determine which "applied" timestamp column exists, falling back safely.
     */
    private function appliedAtColumn(): string
    {
        if ($this->hasColumn('hr_leave_applications', 'applied_on')) {
            return 'applied_on';
        }
        if ($this->hasColumn('hr_leave_applications', 'applied_at')) {
            return 'applied_at';
        }
        if ($this->hasColumn('hr_leave_applications', 'created_at')) {
            return 'created_at';
        }
        return 'id';
    }

    /**
     * Leave Applications List
     */
    public function index(Request $request)
    {
        $query = HrLeaveApplication::with(['employee', 'leaveType', 'approvedBy'])
            ->latest($this->appliedAtColumn());

        // Filters
        if ($request->filled('employee_id')) {
            $query->where('hr_employee_id', $request->employee_id);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('hr_leave_type_id', $request->leave_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->where('from_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('to_date', '<=', $request->to_date);
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $applications = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => HrLeaveApplication::where('status', 'pending')->count(),
            'approved_today' => HrLeaveApplication::where('status', 'approved')
                ->whereDate('approved_at', today())->count(),
            'on_leave_today' => HrLeaveApplication::where('status', 'approved')
                ->where('from_date', '<=', today())
                ->where('to_date', '>=', today())
                ->count(),
        ];

        $leaveTypes = HrLeaveType::where('is_active', true)->get();
        $employees = HrEmployee::active()->orderBy('first_name')->get();
        $statuses = collect(LeaveStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()]);

        return view('hr.leave.index', compact('applications', 'stats', 'leaveTypes', 'employees', 'statuses'));
    }

    /**
     * Pending Leave Applications
     */
    public function pending(Request $request)
    {
        $applications = HrLeaveApplication::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->latest($this->appliedAtColumn())
            ->paginate(20);

        return view('hr.leave.pending', compact('applications'));
    }

    /**
     * Leave Calendar View
     */
    public function calendar(Request $request)
    {
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get approved leaves for the month
        $leaves = HrLeaveApplication::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('from_date', [$startDate, $endDate])
                    ->orWhereBetween('to_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('from_date', '<=', $startDate)
                            ->where('to_date', '>=', $endDate);
                    });
            })
            ->get();

        // Get holidays
        $holidays = HrHoliday::whereBetween('holiday_date', [$startDate, $endDate])->get();

        // Build calendar data
        $calendarData = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $dayLeaves = $leaves->filter(function ($leave) use ($date) {
                return $date->between($leave->from_date, $leave->to_date);
            });

            $holiday = $holidays->firstWhere('date', $date->format('Y-m-d'));

            $calendarData[$date->format('Y-m-d')] = [
                'date' => $date,
                'leaves' => $dayLeaves,
                'holiday' => $holiday,
                'is_weekend' => $date->isWeekend(),
            ];
        }

        $departments = \App\Models\Department::orderBy('name')->get();

        return view('hr.leave.calendar', compact('calendarData', 'month', 'year', 'departments'));
    }

    /**
     * Leave Balance Report
     */
    public function balanceReport(Request $request)
    {
        $query = HrLeaveBalance::with(['employee', 'leaveType'])
            ->whereHas('employee', function ($q) {
                $q->where('status', 'active');
            });

        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('leave_type_id')) {
            $query->where('hr_leave_type_id', $request->leave_type_id);
        }

        $balances = $query->orderBy('hr_employee_id')->paginate(50);

        $leaveTypes = HrLeaveType::where('is_active', true)->get();
        $departments = \App\Models\Department::orderBy('name')->get();

        return view('hr.leave.balance-report', compact('balances', 'leaveTypes', 'departments'));
    }

    /**
     * Create Leave Application Form
     */
    public function create(Request $request)
    {
        $employees = HrEmployee::active()->orderBy('first_name')->get();
        $leaveTypes = HrLeaveType::where('is_active', true)->orderBy('sort_order')->get();

        $selectedEmployee = $request->filled('employee_id')
            ? HrEmployee::find($request->employee_id)
            : null;

        return view('hr.leave.create', compact('employees', 'leaveTypes', 'selectedEmployee'));
    }

    /**
     * Store Leave Application
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'hr_leave_type_id' => 'required|exists:hr_leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'from_session' => 'required|in:full_day,first_half,second_half',
            'to_session' => 'required|in:full_day,first_half,second_half',
            'reason' => 'required|string|max:1000',
            'contact_during_leave' => 'nullable|string|max:50',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $employee = HrEmployee::findOrFail($validated['hr_employee_id']);
        $leaveType = HrLeaveType::findOrFail($validated['hr_leave_type_id']);

        // Calculate leave days
        $totalDays = $this->calculateLeaveDays(
            $validated['from_date'],
            $validated['to_date'],
            $validated['from_session'],
            $validated['to_session'],
            $employee->id
        );

        // Check balance
        $balance = HrLeaveBalance::where('hr_employee_id', $employee->id)
            ->where('hr_leave_type_id', $leaveType->id)
            ->where('year', Carbon::parse($validated['from_date'])->year)
            ->first();

        $availableBalance = $balance ? $balance->available_balance : 0;

        if (!$leaveType->is_negative_balance_allowed && $totalDays > $availableBalance) {
            return back()->withErrors(['days' => "Insufficient leave balance. Available: {$availableBalance} days"])->withInput();
        }

        // Handle document upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leave-documents', 'public');
        }

        // Generate application number
        $applicationNumber = 'LV' . date('Ym') . str_pad(
            HrLeaveApplication::whereYear('created_at', date('Y'))->count() + 1,
            5,
            '0',
            STR_PAD_LEFT
        );

        $data = [
            'company_id' => $employee->company_id,
            'application_number' => $applicationNumber,
            'hr_employee_id' => $employee->id,
            'hr_leave_type_id' => $leaveType->id,
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'from_session' => $validated['from_session'],
            'to_session' => $validated['to_session'],
            'total_days' => $totalDays,
            'reason' => $validated['reason'],
            'contact_during_leave' => $validated['contact_during_leave'],
            'document_path' => $documentPath,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ];

        $appliedCol = $this->appliedAtColumn();
        if (in_array($appliedCol, ['applied_on', 'applied_at'], true)) {
            $data[$appliedCol] = now();
        }

        $application = HrLeaveApplication::create($data);

        return redirect()->route('hr.leave.index')
            ->with('success', "Leave application {$applicationNumber} submitted successfully.");
    }

    /**
     * Show Leave Application
     */
    public function show(HrLeaveApplication $application)
    {
        $application->load(['employee', 'leaveType', 'approvedBy', 'transactions']);

        return view('hr.leave.show', compact('application'));
    }

    /**
     * Approve Leave Application
     */
    public function approve(Request $request, HrLeaveApplication $application)
    {
        if ($application->status !== LeaveStatus::PENDING) {
            return back()->with('error', 'Only pending applications can be approved.');
        }

        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($application, $request) {
            // Update application
            $application->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_remarks' => $request->remarks,
            ]);

            // Deduct from balance
            $year = Carbon::parse($application->from_date)->year;
            $balance = HrLeaveBalance::firstOrCreate(
                [
                    'hr_employee_id' => $application->hr_employee_id,
                    'hr_leave_type_id' => $application->hr_leave_type_id,
                    'year' => $year,
                ],
                [
                    'company_id' => $application->company_id,
                    'opening_balance' => 0,
                    'credited' => 0,
                    'availed' => 0,
                    'adjusted' => 0,
                ]
            );

            $balance->increment('availed', $application->total_days);

            // Create transaction
            HrLeaveTransaction::create([
                'company_id' => $application->company_id,
                'hr_leave_balance_id' => $balance->id,
                'hr_leave_application_id' => $application->id,
                'transaction_type' => 'availed',
                'days' => -$application->total_days,
                'balance_after' => $balance->available_balance,
                'remarks' => "Leave approved: {$application->application_number}",
                'transaction_date' => now(),
                'created_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Leave application approved successfully.');
    }

    /**
     * Reject Leave Application
     */
    public function reject(Request $request, HrLeaveApplication $application)
    {
        if ($application->status !== LeaveStatus::PENDING) {
            return back()->with('error', 'Only pending applications can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $application->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_remarks' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Leave application rejected.');
    }

    /**
     * Cancel Leave Application
     */
    public function cancel(Request $request, HrLeaveApplication $application)
    {
        if (!$application->status->canCancel()) {
            return back()->with('error', 'This application cannot be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($application, $request) {
            $wasApproved = $application->status->value === 'approved';

            $application->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->cancellation_reason,
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
            ]);

            // Restore balance if was approved
            if ($wasApproved) {
                $year = Carbon::parse($application->from_date)->year;
                $balance = HrLeaveBalance::where('hr_employee_id', $application->hr_employee_id)
                    ->where('hr_leave_type_id', $application->hr_leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->decrement('availed', $application->total_days);

                    HrLeaveTransaction::create([
                        'company_id' => $application->company_id,
                        'hr_leave_balance_id' => $balance->id,
                        'hr_leave_application_id' => $application->id,
                        'transaction_type' => 'restored',
                        'days' => $application->total_days,
                        'balance_after' => $balance->available_balance,
                        'remarks' => "Leave cancelled: {$application->application_number}",
                        'transaction_date' => now(),
                        'created_by' => Auth::id(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Leave application cancelled.');
    }

    /**
     * Bulk Approve
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'application_ids' => 'required|array',
            'application_ids.*' => 'exists:hr_leave_applications,id',
        ]);

        $count = 0;
        foreach ($request->application_ids as $id) {
            $application = HrLeaveApplication::find($id);
            if ($application && $application->status->value === 'pending') {
                // Use the approve logic
                DB::transaction(function () use ($application) {
                    $application->update([
                        'status' => 'approved',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    $year = Carbon::parse($application->from_date)->year;
                    $balance = HrLeaveBalance::firstOrCreate(
                        [
                            'hr_employee_id' => $application->hr_employee_id,
                            'hr_leave_type_id' => $application->hr_leave_type_id,
                            'year' => $year,
                        ],
                        [
                            'company_id' => $application->company_id,
                            'opening_balance' => 0,
                            'credited' => 0,
                            'availed' => 0,
                            'adjusted' => 0,
                        ]
                    );

                    $balance->increment('availed', $application->total_days);
                });
                $count++;
            }
        }

        return back()->with('success', "{$count} leave applications approved.");
    }

    /**
     * Year End Processing
     */
    public function yearEndProcessing(Request $request)
    {
        $currentYear = (int) now()->year;

        if (!$request->isMethod('post')) {
            $fromYear = (int) $request->query('from_year', $currentYear - 1);
            $toYear = (int) $request->query('to_year', $currentYear);

            $previewBalances = HrLeaveBalance::with(['leaveType', 'employee'])
                ->where('year', $fromYear)
                ->whereHas('employee', fn($q) => $q->where('status', 'active'))
                ->get();

            $eligibleCount = 0;
            $eligibleDays = 0.0;

            foreach ($previewBalances as $balance) {
                $leaveType = $balance->leaveType;
                if (!$leaveType || !$leaveType->is_carry_forward) {
                    continue;
                }

                $available = (float) $balance->available_balance;
                if ($available <= 0) {
                    continue;
                }

                $maxCarry = $leaveType->max_carry_forward_days !== null
                    ? (float) $leaveType->max_carry_forward_days
                    : $available;
                $carryForward = min($available, $maxCarry);

                if ($carryForward <= 0) {
                    continue;
                }

                $eligibleCount++;
                $eligibleDays += $carryForward;
            }

            $summary = [
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'source_balances' => $previewBalances->count(),
                'eligible_balances' => $eligibleCount,
                'estimated_days' => round($eligibleDays, 2),
            ];

            return view('hr.leave.year-end', compact('fromYear', 'toYear', 'summary'));
        }

        $validated = $request->validate([
            'from_year' => 'required|integer|min:2000|max:2100',
            'to_year' => 'required|integer|min:2000|max:2100|gt:from_year',
        ]);

        $fromYear = (int) $validated['from_year'];
        $toYear = (int) $validated['to_year'];
        $processed = 0;

        DB::transaction(function () use ($fromYear, $toYear, &$processed) {
            $balances = HrLeaveBalance::with('leaveType')
                ->where('year', $fromYear)
                ->whereHas('employee', fn($q) => $q->where('status', 'active'))
                ->get();

            foreach ($balances as $balance) {
                $leaveType = $balance->leaveType;
                if (!$leaveType || !$leaveType->is_carry_forward) {
                    continue;
                }

                $available = (float) $balance->available_balance;
                if ($available <= 0) {
                    continue;
                }

                $maxCarry = $leaveType->max_carry_forward_days !== null
                    ? (float) $leaveType->max_carry_forward_days
                    : $available;
                $carryForward = min($available, $maxCarry);

                if ($carryForward <= 0) {
                    continue;
                }

                // Create or update next year balance
                $newBalance = HrLeaveBalance::firstOrCreate(
                    [
                        'hr_employee_id' => $balance->hr_employee_id,
                        'hr_leave_type_id' => $balance->hr_leave_type_id,
                        'year' => $toYear,
                    ],
                    [
                        'company_id' => $balance->company_id,
                        'opening_balance' => 0,
                        'credited' => 0,
                        'used' => 0,
                        'pending' => 0,
                        'adjusted' => 0,
                        'lapsed' => 0,
                        'encashed' => 0,
                        'carry_forward' => 0,
                        'closing_balance' => 0,
                        'available_balance' => 0,
                        'is_processed' => false,
                    ]
                );

                $newBalance->increment('opening_balance', $carryForward);
                $newBalance->increment('carry_forward', $carryForward);
                $newBalance->increment('available_balance', $carryForward);

                // Create transaction
                HrLeaveTransaction::create([
                    'company_id' => $balance->company_id,
                    'hr_leave_balance_id' => $newBalance->id,
                    'transaction_type' => 'carry_forward',
                    'days' => $carryForward,
                    'balance_after' => $newBalance->available_balance,
                    'remarks' => "Carry forward from {$fromYear}",
                    'transaction_date' => now(),
                    'created_by' => Auth::id(),
                ]);

                $processed++;
            }
        });

        return redirect()
            ->route('hr.leave.year-end', ['from_year' => $fromYear, 'to_year' => $toYear])
            ->with('success', "Year end processing completed. {$processed} balances carried forward.");
    }

    /**
     * Calculate leave days excluding holidays and weekends
     */
    private function calculateLeaveDays($fromDate, $toDate, $fromSession, $toSession, $employeeId)
    {
        $start = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);

        // Get holidays in range
        $holidays = HrHoliday::whereBetween('date', [$start, $end])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $days = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            // Skip weekends (assuming Sunday is weekly off)
            if ($date->isSunday()) {
                continue;
            }

            // Skip holidays
            if (in_array($date->format('Y-m-d'), $holidays)) {
                continue;
            }

            // Calculate day value
            if ($date->equalTo($start) && $fromSession !== 'full_day') {
                $days += 0.5;
            } elseif ($date->equalTo($end) && $toSession !== 'full_day') {
                $days += 0.5;
            } else {
                $days += 1;
            }
        }

        return $days;
    }

    /**
     * Get employee leave balance via AJAX
     */
    public function getEmployeeBalance(Request $request)
    {
        $employee = HrEmployee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $year = $request->get('year', date('Y'));

        $balances = HrLeaveBalance::with('leaveType')
            ->where('hr_employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->map(function ($balance) {
                return [
                    'leave_type_id' => $balance->hr_leave_type_id,
                    'leave_type' => $balance->leaveType->name,
                    'opening' => $balance->opening_balance,
                    'credited' => $balance->credited,
                    'availed' => $balance->availed,
                    'available' => $balance->available_balance,
                ];
            });

        return response()->json(['balances' => $balances]);
    }
}
