<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrAttendancePunch;
use App\Models\Hr\HrAttendanceRegularization;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrHoliday;
use App\Models\Hr\HrShift;
use App\Enums\Hr\AttendanceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrAttendanceController extends Controller
{
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
            ->where('attendance_date', $date->toDateString())
            ->orderBy('hr_employee_id');

        if ($department = $request->get('department_id')) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $department));
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $attendances = $query->paginate(50)->withQueryString();

        // Summary for the day
        $summary = [
            'total' => HrEmployee::active()->count(),
            'present' => HrAttendance::where('attendance_date', $date->toDateString())
                ->whereIn('status', ['present', 'late', 'early_leaving', 'late_and_early'])->count(),
            'absent' => HrAttendance::where('attendance_date', $date->toDateString())
                ->where('status', 'absent')->count(),
            'half_day' => HrAttendance::where('attendance_date', $date->toDateString())
                ->where('status', 'half_day')->count(),
            'on_leave' => HrAttendance::where('attendance_date', $date->toDateString())
                ->where('status', 'leave')->count(),
            'late' => HrAttendance::where('attendance_date', $date->toDateString())
                ->where('late_minutes', '>', 0)->count(),
            'ot_hours' => HrAttendance::where('attendance_date', $date->toDateString())
                ->sum('ot_hours'),
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

        $query = HrEmployee::active()
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
                
                if ($attendance) {
                    $empData['days'][$day] = [
                        'status' => $attendance->status,
                        'code' => $attendance->status->shortCode(),
                        'color' => $attendance->status->color(),
                        'in' => $attendance->formatted_in_time,
                        'out' => $attendance->formatted_out_time,
                        'ot' => $attendance->ot_hours,
                    ];

                    // Update summary
                    $empData['summary']['paid_days'] += $attendance->paid_days;
                    $empData['summary']['ot_hours'] += $attendance->ot_hours;
                    
                    match ($attendance->status) {
                        AttendanceStatus::PRESENT => $empData['summary']['present']++,
                        AttendanceStatus::ABSENT => $empData['summary']['absent']++,
                        AttendanceStatus::HALF_DAY => $empData['summary']['half_day']++,
                        AttendanceStatus::LEAVE => $empData['summary']['leave']++,
                        AttendanceStatus::WEEKLY_OFF => $empData['summary']['weekly_off']++,
                        AttendanceStatus::HOLIDAY => $empData['summary']['holiday']++,
                        AttendanceStatus::LATE, AttendanceStatus::LATE_AND_EARLY => $empData['summary']['late']++,
                        default => null,
                    };
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

            // Recalculate if times provided
            if ($attendance->first_in && $attendance->last_out && $attendance->shift) {
                $attendance->recalculate();
                $attendance->save();
            }

            return redirect()
                ->route('hr.attendance.index', ['date' => $date->toDateString()])
                ->with('success', 'Attendance entry saved successfully.');
        }

        $employees = HrEmployee::active()->orderBy('first_name')->get();
        return view('hr.attendance.manual-entry', compact('employees'));
    }

    public function process(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);
        
        DB::beginTransaction();
        try {
            $employees = HrEmployee::active()->get();
            $processed = 0;

            foreach ($employees as $employee) {
                $this->processEmployeeAttendance($employee, $date);
                $processed++;
            }

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

        return back()->with('success', 'Overtime approved successfully.');
    }

    public function rejectOt(HrAttendance $attendance): RedirectResponse
    {
        $attendance->reject_overtime(auth()->user());
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
                if ($attendance && $attendance->ot_status === 'pending') {
                    if ($validated['action'] === 'approve') {
                        $attendance->approve_overtime(auth()->user());
                    } else {
                        $attendance->reject_overtime(auth()->user());
                    }
                    $count++;
                }
            }

            return back()->with('success', "{$count} overtime records {$validated['action']}ed.");
        }

        $pendingOt = HrAttendance::with(['employee', 'shift'])
            ->where('ot_status', 'pending')
            ->where('ot_hours', '>', 0)
            ->orderByDesc('attendance_date')
            ->paginate(50);

        return view('hr.attendance.ot-approval', [
            'pendingOt' => $pendingOt,
            'records' => $pendingOt,
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

        $query = HrEmployee::active()
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

            $reportData[] = [
                'employee' => $employee,
                'total_days' => $startDate->diffInDays($endDate) + 1,
                'present' => $attendances->whereIn('status', ['present', 'late', 'early_leaving'])->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'half_day' => $attendances->where('status', 'half_day')->count(),
                'leave' => $attendances->where('status', 'leave')->count(),
                'weekly_off' => $attendances->where('status', 'weekly_off')->count(),
                'holiday' => $attendances->where('status', 'holiday')->count(),
                'late_count' => $attendances->where('late_minutes', '>', 0)->count(),
                'late_minutes' => $attendances->sum('late_minutes'),
                'early_minutes' => $attendances->sum('early_leaving_minutes'),
                'ot_hours' => $attendances->sum('ot_hours'),
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

        // Check for weekly off (simplified - check if Sunday or Saturday based on pattern)
        // This would normally check the weekly off pattern assigned to employee
        if ($date->isSunday()) {
            $dayType = 'weekly_off';
            $isWeekOff = true;
        }

        // Check for approved leave
        $leaveApplication = null;
        // Would check hr_leave_applications here

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
            ]
        );

        // Process punches
        if ($punches->isNotEmpty()) {
            $firstIn = $punches->first()->punch_time;
            $lastOut = $punches->count() > 1 ? $punches->last()->punch_time : null;

            $attendance->first_in = $firstIn;
            $attendance->last_out = $lastOut;

            if ($shift) {
                $result = $shift->determineAttendanceStatus($firstIn, $lastOut, $date);
                $attendance->status = AttendanceStatus::from($result['status']);
                $attendance->late_minutes = $result['late_minutes'];
                $attendance->early_leaving_minutes = $result['early_minutes'];
                $attendance->working_hours = $result['working_hours'];
                $attendance->ot_hours = round($result['ot_minutes'] / 60, 2);
                
                if ($attendance->ot_hours > 0) {
                    $attendance->ot_status = 'pending';
                }
            }

            if ($firstIn && $lastOut) {
                $attendance->total_hours = round($lastOut->diffInMinutes($firstIn) / 60, 2);
            }
        } else {
            // No punches
            if ($isWeekOff) {
                $attendance->status = AttendanceStatus::WEEKLY_OFF;
            } elseif ($isHoliday) {
                $attendance->status = AttendanceStatus::HOLIDAY;
            } else {
                $attendance->status = AttendanceStatus::ABSENT;
            }
        }

        $attendance->is_processed = true;
        $attendance->save();

        // Mark punches as processed
        $punches->each(fn($p) => $p->update(['is_processed' => true]));
    }
}
