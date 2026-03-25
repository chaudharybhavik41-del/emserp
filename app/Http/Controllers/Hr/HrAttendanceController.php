<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrAttendancePunch;
use App\Models\Hr\HrAttendanceRegularization;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrHoliday;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrOvertimeRecord;
use App\Models\Hr\HrShift;
use App\Services\Hr\PayrollPeriodStalenessService;
use App\Enums\Hr\AttendanceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrAttendanceController extends Controller
{
    private const PRESENT_DAY_STATUSES = [
        'present',
        'late',
        'early_leaving',
        'late_and_early',
        'on_duty',
        'comp_off',
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:hr.attendance.view')->only(['index', 'show', 'monthly', 'report']);
        $this->middleware('permission:hr.attendance.create')->only(['create', 'store', 'manualEntry', 'importPunches']);
        $this->middleware('permission:hr.attendance.update')->only(['edit', 'update', 'approve']);
        $this->middleware('permission:hr.attendance.process')->only(['process', 'lock']);
    }

    public function index(Request $request): View
    {
        $date = $request->get('date') ? Carbon::parse($request->get('date')) : now();
        
        $query = HrAttendance::with(['employee', 'shift'])
            ->whereDate('attendance_date', $date->toDateString())
            ->orderBy('hr_employee_id');

        if ($search = $request->get('q')) {
            $query->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($department = $request->get('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $department));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $attendances = $query->paginate(50)->withQueryString();

        // Summary for the day
        $summary = [
            'total' => $this->employeesVisibleForAttendanceWindow($date->copy()->startOfDay(), $date->copy()->startOfDay())->count(),
            'present' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->whereIn('status', self::PRESENT_DAY_STATUSES)->count(),
            'absent' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->where('status', 'absent')->count(),
            'half_day' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->where('status', 'half_day')->count(),
            'on_leave' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->where('status', 'leave')->count(),
            'late' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->where('late_minutes', '>', 0)->count(),
            'ot_hours' => HrAttendance::whereDate('attendance_date', $date->toDateString())
                ->sum('ot_hours_approved'),
        ];

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $statuses = AttendanceStatus::options();

        return view('hr.attendance.index', compact(
            'attendances', 'date', 'summary', 'departments', 'statuses'
        ));
    }

    public function monthly(Request $request): View
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $query = $this->employeesVisibleForAttendanceWindow($startDate, $endDate)
            ->with(['department', 'designation'])
            ->orderBy('employee_code');

        if ($department = $request->get('department_id')) {
            $query->where('department_id', $department);
        }

        $employees = $query->get();

        // Get all attendance records for the month
        $attendanceRecords = HrAttendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->groupBy(['hr_employee_id', fn($att) => $att->attendance_date->format('d')]);

        // Get holidays for the month
        $holidays = HrHoliday::whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('d'))
            ->toArray();

        // Build calendar data
        $calendarData = [];
        foreach ($employees as $employee) {
            [$serviceStart, $serviceEnd] = $this->employeeEmploymentWindowWithinRange($employee, $startDate, $endDate);
            $empData = [
                'employee' => $employee,
                'days' => [],
                'summary' => [
                    'present' => 0,
                    'absent' => 0,
                    'half_day' => 0,
                    'leave' => 0,
                    'weekly_off' => 0,
                    'holiday' => 0,
                    'late' => 0,
                    'ot_hours' => 0,
                    'paid_days' => 0,
                ],
            ];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dayKey = str_pad($day, 2, '0', STR_PAD_LEFT);
                $attendance = $attendanceRecords[$employee->id][$dayKey][0] ?? null;
                $cellDate = Carbon::createFromDate($year, $month, $day)->startOfDay();
                $withinServiceWindow = $serviceStart && $serviceEnd
                    ? $cellDate->betweenIncluded($serviceStart, $serviceEnd)
                    : false;
                
                if ($attendance) {
                    $empData['days'][$day] = [
                        'status' => $attendance->status,
                        'code' => $attendance->status->shortCode(),
                        'color' => $attendance->status->color(),
                        'in' => $attendance->formatted_in_time,
                        'out' => $attendance->formatted_out_time,
                        'ot' => $attendance->ot_hours_approved,
                    ];

                    // Update summary
                    $empData['summary']['paid_days'] += $attendance->paid_days;
                    $empData['summary']['ot_hours'] += $attendance->ot_hours_approved;
                    
                    match ($attendance->status) {
                        AttendanceStatus::PRESENT,
                        AttendanceStatus::EARLY_LEAVING,
                        AttendanceStatus::ON_DUTY,
                        AttendanceStatus::COMP_OFF => $empData['summary']['present']++,
                        AttendanceStatus::ABSENT => $empData['summary']['absent']++,
                        AttendanceStatus::HALF_DAY => $empData['summary']['half_day']++,
                        AttendanceStatus::LEAVE => $empData['summary']['leave']++,
                        AttendanceStatus::WEEKLY_OFF => $empData['summary']['weekly_off']++,
                        AttendanceStatus::HOLIDAY => $empData['summary']['holiday']++,
                        AttendanceStatus::LATE, AttendanceStatus::LATE_AND_EARLY => $empData['summary']['late']++,
                        default => null,
                    };
                } elseif (! $withinServiceWindow) {
                    $empData['days'][$day] = [
                        'status' => null,
                        'code' => 'NA',
                        'color' => 'muted',
                        'title' => 'Not employed on this date',
                    ];
                } else {
                    $empData['days'][$day] = [
                        'status' => null,
                        'code' => '-',
                        'color' => 'light',
                    ];
                }
            }

            $calendarData[] = $empData;
        }

        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $months = collect(range(1, 12))->mapWithKeys(fn($m) => [$m => Carbon::create()->month($m)->format('F')]);
        $years = collect(range(now()->year - 2, now()->year + 1));

        return view('hr.attendance.monthly', compact(
            'calendarData', 'startDate', 'endDate', 'daysInMonth', 'holidays',
            'departments', 'months', 'years', 'year', 'month'
        ));
    }

    public function show(HrAttendance $attendance): View
    {
        $attendance->load([
            'employee', 
            'shift', 
            'holiday', 
            'leaveApplication.leaveType',
            'punches' => fn($q) => $q->orderBy('punch_time'),
            'regularizationRequests',
            'overtimeRecord',
        ]);

        return view('hr.attendance.show', compact('attendance'));
    }

   public function manualEntry(Request $request): View|RedirectResponse
{
    if ($request->isMethod('post')) {
        $validated = $request->validate([
            'hr_employee_id' => 'required|exists:hr_employees,id',
            'attendance_date' => 'required|date',
            'first_in' => 'nullable|date_format:H:i',
            'last_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,half_day,on_duty',
            'remarks' => 'required|string|max:500',
        ]);

        $employee = HrEmployee::findOrFail($validated['hr_employee_id']);
        $date = Carbon::parse($validated['attendance_date']);

        $attendance = HrAttendance::updateOrCreate(
            [
                'hr_employee_id' => $validated['hr_employee_id'],
                'attendance_date' => $date->toDateString(),
            ],
            [
                'hr_shift_id' => $employee->default_shift_id,
                'first_in' => $validated['first_in'] ? $date->copy()->setTimeFromTimeString($validated['first_in']) : null,
                'last_out' => $validated['last_out'] ? $date->copy()->setTimeFromTimeString($validated['last_out']) : null,
                'status' => $validated['status'],
                'is_manual_entry' => true,
                'remarks' => $validated['remarks'],
                'created_by' => auth()->id(),
            ]
        );

        if ($attendance->first_in && $attendance->last_out && $attendance->shift) {
            $attendance->recalculate();
            $this->applyOvertimeWorkflowState($attendance, $employee);
            $attendance->save();
            $this->syncOvertimeRecord($attendance);
        }

        app(PayrollPeriodStalenessService::class)
            ->markPeriodsOverlappingRange($attendance->attendance_date, $attendance->attendance_date, "Attendance updated for {$employee->employee_code}.");

        return redirect()
            ->route('hr.attendance.index', ['date' => $date->toDateString()])
            ->with('success', 'Attendance entry saved successfully.');
    }

    // Load employees visible for the selected date, including resigned employees with service/attendance on that date.
    $manualDate = $request->query('date') ? Carbon::parse($request->query('date'))->startOfDay() : now()->startOfDay();
    $employees = $this->employeesVisibleForAttendanceWindow($manualDate, $manualDate)->orderBy('first_name')->get();

    // Get selected employee from query param or localStorage
    $selectedEmployeeId = $request->query('employee_id') ?? null;

    // Fetch existing attendance if exists
    $attendance = null;
   if ($selectedEmployeeId && $request->query('date')) {
    $attendance = HrAttendance::where('hr_employee_id', $selectedEmployeeId)
                                ->where('attendance_date', $request->query('date'))
                              ->first();
}

    return view('hr.attendance.manual-entry', compact('employees', 'selectedEmployeeId', 'attendance'));
}

    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);
        
        DB::beginTransaction();
        try {
            $employees = $this->employeesVisibleForAttendanceWindow($date, $date)->get();
            $processed = 0;

            foreach ($employees as $employee) {
                $this->processEmployeeAttendance($employee, $date);
                $processed++;
            }

            app(PayrollPeriodStalenessService::class)
                ->markPeriodsOverlappingRange($date, $date, 'Attendance processed and recalculated.');

            DB::commit();

            return back()->with('success', "Attendance processed for {$processed} employees.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to process attendance: ' . $e->getMessage());
        }
    }

    public function approveOt(Request $request, HrAttendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'approved_hours' => 'required|numeric|min:0|max:' . $attendance->ot_hours,
        ]);

        $attendance->approve_overtime(auth()->user(), $validated['approved_hours']);
        $this->syncOvertimeRecord($attendance);
        app(PayrollPeriodStalenessService::class)
            ->markPeriodsOverlappingRange($attendance->attendance_date, $attendance->attendance_date, 'Overtime approval updated.');

        return back()->with('success', 'Overtime approved successfully.');
    }

    public function rejectOt(HrAttendance $attendance): RedirectResponse
    {
        $attendance->reject_overtime(auth()->user());
        $this->syncOvertimeRecord($attendance);
        app(PayrollPeriodStalenessService::class)
            ->markPeriodsOverlappingRange($attendance->attendance_date, $attendance->attendance_date, 'Overtime approval updated.');
        return back()->with('success', 'Overtime rejected.');
    }

    public function bulkOtApproval(Request $request): View|RedirectResponse
{
    if ($request->isMethod('post')) {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:hr_attendances,id',
            'action' => 'required|in:approve,reject',
        ]);

        $count = 0;

        foreach ($validated['attendance_ids'] as $id) {
            $attendance = HrAttendance::find($id);

            if ($attendance && in_array($attendance->ot_status, ['pending', 'none'])) {
                if ($validated['action'] === 'approve') {
                    $attendance->approve_overtime(auth()->user());
                } else {
                    $attendance->reject_overtime(auth()->user());
                }

                $this->syncOvertimeRecord($attendance);

                $count++;
            }
        }

        return back()->with('success', "{$count} overtime records {$validated['action']}d.");
    }

    $query = HrAttendance::with(['employee', 'shift', 'otApprover'])
        ->where('ot_hours', '>', 0)
        ->orderByDesc('attendance_date')
        ->orderByDesc('id');

    if ($status = $request->string('status')->toString()) {
        if ($status !== 'all') {
            $query->where('ot_status', $status);
        }
    } else {
        $query->whereIn('ot_status', ['pending', 'none']);
    }

    if ($search = trim((string) $request->string('q'))) {
        $query->whereHas('employee', function ($employeeQuery) use ($search) {
            $employeeQuery->where('employee_code', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%");
        });
    }

    if ($fromDate = $request->date('from_date')) {
        $query->whereDate('attendance_date', '>=', $fromDate->toDateString());
    }

    if ($toDate = $request->date('to_date')) {
        $query->whereDate('attendance_date', '<=', $toDate->toDateString());
    }

    $records = $query->paginate(50)->withQueryString();

    return view('hr.attendance.ot-approval', [
        'records' => $records,
        'statusOptions' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'none' => 'Unreviewed',
            'all' => 'All',
        ],
        'otSummary' => [
            'pending' => HrAttendance::query()->where('ot_hours', '>', 0)->whereIn('ot_status', ['pending', 'none'])->count(),
            'approved' => HrAttendance::query()->where('ot_hours', '>', 0)->where('ot_status', 'approved')->count(),
            'rejected' => HrAttendance::query()->where('ot_hours', '>', 0)->where('ot_status', 'rejected')->count(),
            'approved_hours' => HrAttendance::query()->where('ot_status', 'approved')->sum('ot_hours_approved'),
        ],
    ]);
}

    public function regularization(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'hr_attendance_id' => 'required|exists:hr_attendances,id',
                'requested_in_time' => 'nullable|date_format:H:i',
                'requested_out_time' => 'nullable|date_format:H:i',
                'regularization_type' => 'required|in:missed_punch,wrong_punch,forgot_id,biometric_issue,on_duty,other',
                'reason' => 'required|string|max:1000',
            ]);

            $attendance = HrAttendance::findOrFail($validated['hr_attendance_id']);
            
            $regularization = HrAttendanceRegularization::create([
                'request_number' => HrAttendanceRegularization::generateNumber(),
                'hr_employee_id' => $attendance->hr_employee_id,
                'hr_attendance_id' => $attendance->id,
                'attendance_date' => $attendance->attendance_date,
                'original_in_time' => $attendance->first_in,
                'original_out_time' => $attendance->last_out,
                'original_status' => $attendance->status->value,
                'requested_in_time' => $validated['requested_in_time'] 
                    ? $attendance->attendance_date->copy()->setTimeFromTimeString($validated['requested_in_time'])
                    : null,
                'requested_out_time' => $validated['requested_out_time']
                    ? $attendance->attendance_date->copy()->setTimeFromTimeString($validated['requested_out_time'])
                    : null,
                'regularization_type' => $validated['regularization_type'],
                'reason' => $validated['reason'],
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('hr.attendance.show', $attendance)
                ->with('success', 'Regularization request submitted.');
        }

        $attendanceId = $request->get('attendance_id');
        $attendance = $attendanceId ? HrAttendance::with('employee')->findOrFail($attendanceId) : null;
        $regularizations = HrAttendanceRegularization::with(['employee', 'attendance'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('hr.attendance.regularization', compact('attendance', 'regularizations'));
    }

    public function importPunches(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240',
                'default_date' => 'nullable|date',
                'has_header' => 'nullable|boolean',
                'auto_process' => 'nullable|boolean',
            ]);

            $defaultDate = isset($validated['default_date']) && $validated['default_date']
                ? Carbon::parse($validated['default_date'])->startOfDay()
                : null;
            $hasHeader = $request->boolean('has_header', true);
            $autoProcess = $request->boolean('auto_process', true);

            $handle = fopen($validated['file']->getRealPath(), 'r');
            if ($handle === false) {
                return back()->with('error', 'Unable to read the uploaded file.');
            }

            $header = [];
            $imported = 0;
            $duplicate = 0;
            $failed = 0;
            $errors = [];
            $employeeDates = [];
            $rowNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->isImportRowEmpty($row)) {
                    continue;
                }

                if ($rowNumber === 1 && $hasHeader) {
                    $header = array_map(fn ($h) => $this->normalizeImportHeader((string) $h), $row);
                    continue;
                }

                try {
                    $mapped = !empty($header)
                        ? $this->mapImportRowByHeader($row, $header)
                        : [
                            'employee_code' => $row[0] ?? null,
                            'punch_time' => $row[1] ?? null,
                            'punch_type' => $row[2] ?? null,
                            'device_id' => $row[3] ?? null,
                            'location_name' => $row[4] ?? null,
                            'remarks' => $row[5] ?? null,
                        ];

                    $employee = $this->resolveEmployeeForPunchImport($mapped);
                    if (!$employee) {
                        $failed++;
                        $errors[] = "Row {$rowNumber}: employee not found.";
                        continue;
                    }

                    $punchTime = $this->resolveImportPunchTime($mapped, $defaultDate);
                    if (!$punchTime) {
                        $failed++;
                        $errors[] = "Row {$rowNumber}: invalid or missing punch time.";
                        continue;
                    }

                    $punchType = $this->normalizeImportPunchType($this->findImportValue(
                        $mapped,
                        ['punch_type', 'type', 'direction', 'in_out', 'io']
                    ));

                    $exists = HrAttendancePunch::query()
                        ->where('hr_employee_id', $employee->id)
                        ->where('punch_time', $punchTime)
                        ->where('punch_type', $punchType)
                        ->exists();

                    if ($exists) {
                        $duplicate++;
                        continue;
                    }

                    HrAttendancePunch::create([
                        'hr_employee_id' => $employee->id,
                        'punch_time' => $punchTime,
                        'punch_type' => $punchType,
                        'source' => 'import',
                        'device_id' => $this->findImportValue($mapped, ['device_id', 'device', 'terminal_id', 'machine_id']),
                        'location_name' => $this->findImportValue($mapped, ['location_name', 'location', 'site']),
                        'raw_data' => substr(json_encode($mapped) ?: '', 0, 255),
                        'is_processed' => false,
                        'is_valid' => true,
                        'remarks' => $this->findImportValue($mapped, ['remarks', 'remark', 'note', 'notes']),
                        'created_by' => auth()->id(),
                    ]);

                    $employeeDates[$employee->id . '|' . $punchTime->toDateString()] = [
                        'employee_id' => $employee->id,
                        'date' => $punchTime->toDateString(),
                    ];
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            fclose($handle);

            $processedCount = 0;
            if ($autoProcess && $imported > 0 && !empty($employeeDates)) {
                $employees = HrEmployee::whereIn('id', collect($employeeDates)->pluck('employee_id')->unique()->values())
                    ->get()
                    ->keyBy('id');

                foreach ($employeeDates as $pair) {
                    $employee = $employees[$pair['employee_id']] ?? null;
                    if (!$employee) {
                        continue;
                    }

                    try {
                        $this->processEmployeeAttendance($employee, Carbon::parse($pair['date']));
                        $processedCount++;
                    } catch (\Throwable $e) {
                        $errors[] = "Processing {$employee->employee_code} on {$pair['date']}: " . $e->getMessage();
                    }
                }
            }

            $message = "Punch import complete. Imported: {$imported}, Duplicates: {$duplicate}, Failed: {$failed}.";
            if ($autoProcess) {
                $message .= " Attendance processed: {$processedCount}.";
            }

            return back()
                ->with($failed > 0 ? 'warning' : 'success', $message)
                ->with('import_errors', array_slice($errors, 0, 50));
        }

        $recentPunches = HrAttendancePunch::with('employee')
            ->orderByDesc('punch_time')
            ->paginate(50);

        return view('hr.attendance.import-punches', compact('recentPunches'));
    }

    public function approveRegularization(HrAttendanceRegularization $regularization): RedirectResponse
    {
        if ($regularization->status !== 'pending') {
            return back()->with('error', 'This request is already processed.');
        }

        DB::beginTransaction();
        try {
            // Apply regularization to attendance
            $regularization->attendance->regularize(
                $regularization->requested_in_time,
                $regularization->requested_out_time,
                $regularization->reason,
                auth()->user()
            );

            $regularization->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', 'Regularization approved and attendance updated.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to approve: ' . $e->getMessage());
        }
    }

    public function rejectRegularization(Request $request, HrAttendanceRegularization $regularization): RedirectResponse
    {
        if ($regularization->status !== 'pending') {
            return back()->with('error', 'This request is already processed.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $regularization->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_remarks' => $validated['reason'] ?? 'Rejected by reviewer.',
        ]);

        return back()->with('success', 'Regularization request rejected.');
    }

    public function report(Request $request): View
    {
        $startDate = $request->get('start_date') ? Carbon::parse($request->get('start_date')) : now()->startOfMonth();
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : now();

        $query = $this->employeesVisibleForAttendanceWindow($startDate, $endDate)
            ->with(['department', 'designation']);

        if ($department = $request->get('department_id')) {
            $query->where('department_id', $department);
        }

        $employees = $query->get();

        $reportData = [];
        foreach ($employees as $employee) {
            $attendances = $employee->attendances()
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->get();
            [$serviceStart, $serviceEnd] = $this->employeeEmploymentWindowWithinRange($employee, $startDate, $endDate);
            $serviceDays = ($serviceStart && $serviceEnd)
                ? ($serviceStart->diffInDays($serviceEnd) + 1)
                : 0;

            $reportData[] = [
                'employee' => $employee,
                'total_days' => $serviceDays,
                'present' => $attendances->whereIn('status', self::PRESENT_DAY_STATUSES)->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'half_day' => $attendances->where('status', 'half_day')->count(),
                'leave' => $attendances->where('status', 'leave')->count(),
                'weekly_off' => $attendances->where('status', 'weekly_off')->count(),
                'holiday' => $attendances->where('status', 'holiday')->count(),
                'late_count' => $attendances->where('late_minutes', '>', 0)->count(),
                'late_minutes' => $attendances->sum('late_minutes'),
                'early_minutes' => $attendances->sum('early_leaving_minutes'),
                'ot_hours' => $attendances->sum('ot_hours_approved'),
                'ot_approved' => $attendances->sum('ot_hours_approved'),
                'paid_days' => $attendances->sum('paid_days'),
            ];
        }

        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('hr.attendance.report', compact(
            'reportData', 'startDate', 'endDate', 'departments'
        ));
    }

    // Private Methods

    private function normalizeImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? '';
        return trim($header, '_');
    }

    private function employeesVisibleForAttendanceWindow(Carbon $startDate, Carbon $endDate)
    {
        return HrEmployee::query()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($employeeQuery) use ($startDate, $endDate) {
                    $employeeQuery->whereDate('date_of_joining', '<=', $endDate->toDateString())
                        ->where(function ($employmentQuery) use ($startDate) {
                            $employmentQuery->whereNull('date_of_leaving')
                                ->orWhereDate('date_of_leaving', '>=', $startDate->toDateString());
                        });
                })->orWhereHas('attendances', function ($attendanceQuery) use ($startDate, $endDate) {
                    $attendanceQuery->whereDate('attendance_date', '>=', $startDate->toDateString())
                        ->whereDate('attendance_date', '<=', $endDate->toDateString());
                });
            });
    }

    private function employeeEmploymentWindowWithinRange(HrEmployee $employee, Carbon $startDate, Carbon $endDate): array
    {
        if (! $employee->date_of_joining) {
            return [null, null];
        }

        $serviceStart = $employee->date_of_joining->copy()->startOfDay()->greaterThan($startDate)
            ? $employee->date_of_joining->copy()->startOfDay()
            : $startDate->copy()->startOfDay();

        $serviceEnd = $employee->date_of_leaving
            ? ($employee->date_of_leaving->copy()->startOfDay()->lessThan($endDate)
                ? $employee->date_of_leaving->copy()->startOfDay()
                : $endDate->copy()->startOfDay())
            : $endDate->copy()->startOfDay();

        if ($serviceEnd->lessThan($serviceStart)) {
            return [null, null];
        }

        return [$serviceStart, $serviceEnd];
    }

    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function mapImportRowByHeader(array $row, array $header): array
    {
        $mapped = [];
        foreach ($header as $index => $name) {
            if ($name === '') {
                continue;
            }
            $mapped[$name] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $mapped;
    }

    private function findImportValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function resolveEmployeeForPunchImport(array $row): ?HrEmployee
    {
        $employeeId = $this->findImportValue($row, ['hr_employee_id', 'employee_id', 'emp_id']);
        if ($employeeId !== null && ctype_digit($employeeId)) {
            $employee = HrEmployee::find((int) $employeeId);
            if ($employee) {
                return $employee;
            }
        }

        $employeeCode = $this->findImportValue($row, ['employee_code', 'emp_code', 'employeecode', 'code']);
        if ($employeeCode) {
            $employee = HrEmployee::where('employee_code', $employeeCode)->first();
            if ($employee) {
                return $employee;
            }
        }

        $biometricId = $this->findImportValue($row, ['biometric_id', 'biometricid', 'biometric', 'device_user_id', 'user_id']);
        if ($biometricId) {
            $employee = HrEmployee::where('biometric_id', $biometricId)->first();
            if ($employee) {
                return $employee;
            }
        }

        $cardNumber = $this->findImportValue($row, ['card_number', 'card', 'card_no']);
        if ($cardNumber) {
            return HrEmployee::where('card_number', $cardNumber)->first();
        }

        return null;
    }

    private function resolveImportPunchTime(array $row, ?Carbon $defaultDate): ?Carbon
    {
        $full = $this->findImportValue($row, ['punch_time', 'datetime', 'punch_datetime', 'timestamp', 'date_time', 'time_stamp']);
        if ($full) {
            try {
                return Carbon::parse($full);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $date = $this->findImportValue($row, ['date', 'punch_date', 'attendance_date', 'log_date']);
        $time = $this->findImportValue($row, ['time', 'log_time', 'punch_at', 'clock_time']);

        if ($date && $time) {
            try {
                return Carbon::parse("{$date} {$time}");
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (!$date && $time && $defaultDate) {
            try {
                return Carbon::parse($defaultDate->toDateString() . ' ' . $time);
            } catch (\Throwable $e) {
                return null;
            }
        }

        if ($date) {
            try {
                return Carbon::parse($date)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function normalizeImportPunchType(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return 'unknown';
        }

        $in = ['in', 'checkin', 'check_in', 'punchin', 'punch_in', 'entry', 'i', '1'];
        $out = ['out', 'checkout', 'check_out', 'punchout', 'punch_out', 'exit', 'o', '0'];
        $breakStart = ['break_start', 'breakstart', 'break-in', 'breakin', 'bstart'];
        $breakEnd = ['break_end', 'breakend', 'break-out', 'breakout', 'bend'];

        if (in_array($value, $in, true)) {
            return 'in';
        }
        if (in_array($value, $out, true)) {
            return 'out';
        }
        if (in_array($value, $breakStart, true)) {
            return 'break_start';
        }
        if (in_array($value, $breakEnd, true)) {
            return 'break_end';
        }

        return 'unknown';
    }

    private function processEmployeeAttendance(HrEmployee $employee, Carbon $date): void
    {
        // Check if already processed
        $existing = HrAttendance::where('hr_employee_id', $employee->id)
            ->where('attendance_date', $date->toDateString())
            ->where('is_processed', true)
            ->first();

        if ($existing && $existing->is_locked) {
            return;
        }

        // Get punches for the day
        $punches = HrAttendancePunch::where('hr_employee_id', $employee->id)
            ->whereDate('punch_time', $date)
            ->where('is_valid', true)
            ->orderBy('punch_time')
            ->get();

        $shift = $employee->getCurrentShift($date);

        // Determine day type (holiday, weekly off, working)
        $dayType = 'working';
        $isWeekOff = false;
        $isHoliday = false;
        $holidayId = null;

        // Check for holiday
        $holiday = HrHoliday::where('holiday_date', $date->toDateString())
            ->where('is_active', true)
            ->first();
        
        if ($holiday) {
            $dayType = 'holiday';
            $isHoliday = true;
            $holidayId = $holiday->id;
        }

        // Check employee-specific weekly off pattern / daily override.
        if ($employee->isWeeklyOffOn($date)) {
            $dayType = 'weekly_off';
            $isWeekOff = true;
        }

        $leaveApplication = HrLeaveApplication::query()
            ->where('hr_employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->orderBy('from_date')
            ->first();

        // Create/Update attendance record
        $attendance = HrAttendance::updateOrCreate(
            [
                'hr_employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
            ],
            [
                'hr_shift_id' => $shift?->id,
                'day_type' => $dayType,
                'is_week_off' => $isWeekOff,
                'is_holiday' => $isHoliday,
                'hr_holiday_id' => $holidayId,
                'hr_leave_application_id' => $leaveApplication?->id,
            ]
        );

        // Process punches
        if ($punches->isNotEmpty()) {
            $firstIn = $punches->first()->punch_time;
            $lastOut = $punches->count() > 1 ? $punches->last()->punch_time : null;

            $attendance->first_in = $firstIn;
            $attendance->last_out = $lastOut;

            if ($shift) {
                $attendance->recalculate();
                $this->applyOvertimeWorkflowState($attendance, $employee);
            }

            if ($firstIn && $lastOut) {
                $attendance->total_hours = round($firstIn->diffInMinutes($lastOut) / 60, 2);
            }
        } else {
            // No punches
            $policy = $employee->getApplicableAttendancePolicy($attendance->attendance_date);
            if ($leaveApplication) {
                $attendance->status = AttendanceStatus::LEAVE;
            } elseif ($isWeekOff) {
                $attendance->status = AttendanceStatus::WEEKLY_OFF;
            } elseif ($isHoliday) {
                $attendance->status = AttendanceStatus::HOLIDAY;
            } elseif ($policy && ! $policy->mark_absent_on_no_punch) {
                $attendance->status = AttendanceStatus::HALF_DAY;
                $attendance->working_hours = 0;
                $attendance->ot_hours = 0;
                $attendance->ot_hours_approved = 0;
                $attendance->ot_status = 'none';
            } else {
                $attendance->status = AttendanceStatus::ABSENT;
            }
        }

        $attendance->is_processed = true;
        $attendance->save();
        $this->syncOvertimeRecord($attendance);

        // Mark punches as processed
        $punches->each(fn($p) => $p->update(['is_processed' => true]));
    }

    private function applyOvertimeWorkflowState(HrAttendance $attendance, ?HrEmployee $employee = null): void
    {
        $employee ??= $attendance->employee ?: ($attendance->hr_employee_id ? HrEmployee::find($attendance->hr_employee_id) : null);
        $policy = $employee?->getApplicableAttendancePolicy($attendance->attendance_date);
        $otNeedsApproval = $policy ? (bool) $policy->ot_needs_approval : (bool) setting('hr.enable_ot_approval', true);

        if ((float) $attendance->ot_hours <= 0) {
            $attendance->ot_hours_approved = 0;
            $attendance->ot_status = 'none';
            $attendance->ot_approved_by = null;
            $attendance->ot_approved_at = null;

            return;
        }

        if ($otNeedsApproval) {
            $attendance->ot_hours_approved = 0;
            $attendance->ot_status = 'pending';
            $attendance->ot_approved_by = null;
            $attendance->ot_approved_at = null;

            return;
        }

        $attendance->ot_hours_approved = $attendance->ot_hours;
        $attendance->ot_status = 'approved';
        $attendance->ot_approved_by = auth()->id();
        $attendance->ot_approved_at = now();
    }

    private function syncOvertimeRecord(HrAttendance $attendance, ?int $payrollId = null, ?float $hourlyRate = null, ?float $rateMultiplier = null, ?float $otAmount = null): void
    {
        if ((float) $attendance->ot_hours <= 0) {
            $attendance->overtimeRecord()?->delete();

            return;
        }

        $shift = $attendance->shift ?: ($attendance->hr_shift_id ? HrShift::find($attendance->hr_shift_id) : null);
        $employee = $attendance->employee ?: ($attendance->hr_employee_id ? HrEmployee::find($attendance->hr_employee_id) : null);
        $policy = $employee?->getApplicableAttendancePolicy($attendance->attendance_date);
        $otNeedsApproval = $policy ? (bool) $policy->ot_needs_approval : (bool) setting('hr.enable_ot_approval', true);
        $multiplier = $rateMultiplier
            ?? ($attendance->is_holiday
                ? ($policy?->holiday_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)
                : ($attendance->is_week_off
                    ? ($policy?->week_off_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)
                    : ($policy?->ot_rate_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)));

        HrOvertimeRecord::updateOrCreate(
            ['hr_attendance_id' => $attendance->id],
            [
                'ot_number' => sprintf('OT-%s-%d', optional($attendance->attendance_date)->format('Ymd') ?: now()->format('Ymd'), $attendance->id),
                'hr_employee_id' => $attendance->hr_employee_id,
                'ot_date' => optional($attendance->attendance_date)->toDateString() ?: now()->toDateString(),
                'ot_start_time' => optional($attendance->last_out)->format('H:i:s') ?: '00:00:00',
                'ot_end_time' => optional($attendance->last_out)->format('H:i:s') ?: '00:00:00',
                'ot_hours' => (float) $attendance->ot_hours,
                'approved_hours' => (float) $attendance->ot_hours_approved,
                'ot_type' => $attendance->is_holiday ? 'holiday' : ($attendance->is_week_off ? 'weekly_off' : 'normal'),
                'rate_multiplier' => $multiplier,
                'hourly_rate' => $hourlyRate ?? 0,
                'ot_amount' => $otAmount ?? 0,
                'status' => (string) ($attendance->ot_status ?: 'pending'),
                'requested_by' => $attendance->created_by,
                'requested_at' => $attendance->created_at,
                'approved_by' => $attendance->ot_approved_by,
                'approved_at' => $attendance->ot_approved_at,
                'approval_remarks' => $attendance->ot_status === 'approved' && ! $otNeedsApproval ? 'Auto-approved by OT policy/settings.' : null,
                'rejection_reason' => $attendance->ot_status === 'rejected' ? 'Rejected from attendance OT approval' : null,
                'hr_payroll_id' => $payrollId,
                'is_paid' => $payrollId !== null,
            ]
        );
    }
}
