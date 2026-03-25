<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Enums\Hr\AttendanceStatus;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrOvertimeRecord;
use App\Services\Hr\PayrollPeriodStalenessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrAttendanceBulkEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Reuse existing attendance permissions (present in DB export):contentReference[oaicite:9]{index=9}
        $this->middleware('permission:hr.attendance.update');
    }

    /**
     * GET/POST: Bulk attendance entry for all employees for a selected date.
     */
    public function handle(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : now()->toDateString();

        // Optional filters (safe to ignore if you don't have these relations/fields in UI yet)
        $departmentId = $request->integer('department_id') ?: null;
        $shiftId      = $request->integer('shift_id') ?: null;

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'date' => ['required', 'date'],
                'rows' => ['required', 'array'],
                'rows.*.status' => ['required', 'string'],
                'rows.*.in_time' => ['nullable', 'date_format:H:i'],
                'rows.*.out_time' => ['nullable', 'date_format:H:i'],
                'rows.*.ot_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
                'rows.*.remarks' => ['nullable', 'string', 'max:500'],
            ]);

            $date = Carbon::parse((string) $data['date'])->toDateString();

            DB::transaction(function () use ($data, $date) {
                foreach ($data['rows'] as $employeeId => $row) {
                    $employeeId = (int) $employeeId;
                    if ($employeeId <= 0) {
                        continue;
                    }

                    $employee = HrEmployee::find($employeeId);
                    if (! $employee) {
                        continue;
                    }

                    $status = (string) ($row['status'] ?? '');
                    // Validate against enum values
                    $allowed = array_map(fn($c) => $c->value, AttendanceStatus::cases());
                    if (! in_array($status, $allowed, true)) {
                        continue;
                    }

                    $firstIn = !empty($row['in_time'])
                        ? Carbon::parse($date . ' ' . $row['in_time'])
                        : null;
                    $lastOut = !empty($row['out_time'])
                        ? Carbon::parse($date . ' ' . $row['out_time'])
                        : null;

                    $policy = $employee->getApplicableAttendancePolicy(Carbon::parse($date));
                    $otHours = (float) ($row['ot_hours'] ?? 0);
                    if ($policy && !$policy->ot_allowed) {
                        $otHours = 0;
                    }
                    if ($policy && (int) $policy->ot_max_hours_per_day > 0) {
                        $otHours = min($otHours, (float) $policy->ot_max_hours_per_day);
                    }
                    if ($policy && (int) $policy->ot_max_hours_per_month > 0) {
                        $existingMonthlyOtHours = (float) HrAttendance::query()
                            ->where('hr_employee_id', $employeeId)
                            ->whereDate('attendance_date', '>=', Carbon::parse($date)->startOfMonth()->toDateString())
                            ->whereDate('attendance_date', '<=', Carbon::parse($date)->endOfMonth()->toDateString())
                            ->whereDate('attendance_date', '!=', $date)
                            ->sum('ot_hours');
                        $remainingMonthlyOtHours = max(0, (float) $policy->ot_max_hours_per_month - $existingMonthlyOtHours);
                        $otHours = min($otHours, $remainingMonthlyOtHours);
                    }
                    $otNeedsApproval = $policy ? (bool) $policy->ot_needs_approval : (bool) setting('hr.enable_ot_approval', true);

                    $attendance = HrAttendance::updateOrCreate(
                        [
                            'hr_employee_id'  => $employeeId,
                            'attendance_date' => $date,
                        ],
                        [
                            'hr_shift_id' => $employee->default_shift_id,
                            'status' => $status,
                            'first_in' => $firstIn,
                            'last_out' => $lastOut,
                            'ot_hours' => $otHours,
                            'ot_hours_approved' => $otNeedsApproval ? 0 : $otHours,
                            'ot_status' => ($otHours <= 0)
                                ? 'none'
                                : ($otNeedsApproval ? 'pending' : 'approved'),
                            'ot_approved_by' => ($otNeedsApproval || $otHours <= 0) ? null : auth()->id(),
                            'ot_approved_at' => ($otNeedsApproval || $otHours <= 0) ? null : now(),
                            'remarks' => $row['remarks'] ?? null,
                            'is_manual_entry' => true,
                            'is_processed' => true,
                            'updated_by' => auth()->id(),
                            'created_by' => auth()->id(),
                        ]
                    );

                    $this->syncOvertimeRecord($attendance);
                }

                app(PayrollPeriodStalenessService::class)
                    ->markPeriodsOverlappingRange($date, $date, 'Bulk attendance updated.');
            });

            return redirect()
                ->route('hr.attendance.bulk-entry', [
                    'date' => $date,
                ])
                ->with('success', 'Attendance saved successfully for ' . $date . '.');
        }

        // GET: employees + existing attendance for the date
        $targetDate = Carbon::parse($date)->startOfDay();
        $employeesQuery = HrEmployee::query()
            ->where(function ($query) use ($targetDate) {
                $query->where(function ($employeeQuery) use ($targetDate) {
                    $employeeQuery->whereDate('date_of_joining', '<=', $targetDate->toDateString())
                        ->where(function ($employmentQuery) use ($targetDate) {
                            $employmentQuery->whereNull('date_of_leaving')
                                ->orWhereDate('date_of_leaving', '>=', $targetDate->toDateString());
                        });
                })->orWhereHas('attendances', function ($attendanceQuery) use ($targetDate) {
                    $attendanceQuery->whereDate('attendance_date', $targetDate->toDateString());
                });
            })
            ->orderBy('employee_code');

        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }
        if ($shiftId) {
            $employeesQuery->where('default_shift_id', $shiftId);
        }

        $employees = $employeesQuery->get();

        $existing = HrAttendance::query()
            ->whereDate('attendance_date', $date)
            ->whereIn('hr_employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('hr_employee_id');

        return view('hr.attendance.bulk-entry', [
            'date'        => $date,
            'employees'   => $employees,
            'existing'    => $existing,
            'statusOptions' => AttendanceStatus::options(),
            'departmentId'=> $departmentId,
            'shiftId'     => $shiftId,
        ]);
    }

    private function syncOvertimeRecord(HrAttendance $attendance): void
    {
        if ((float) $attendance->ot_hours <= 0) {
            $attendance->overtimeRecord()?->delete();

            return;
        }

        $shift = $attendance->shift ?: ($attendance->hr_shift_id ? $attendance->employee?->defaultShift : null);
        $policy = $attendance->employee?->getApplicableAttendancePolicy($attendance->attendance_date);
        $multiplier = (float) ($attendance->is_holiday
            ? ($policy?->holiday_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)
            : ($attendance->is_week_off
                ? ($policy?->week_off_ot_multiplier ?: $shift?->ot_rate_holiday_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)
                : ($policy?->ot_rate_multiplier ?: $shift?->ot_rate_multiplier ?: 1.5)));

        HrOvertimeRecord::updateOrCreate(
            ['hr_attendance_id' => $attendance->id],
            [
                'ot_number' => sprintf('OT-%s-%d', Carbon::parse($attendance->attendance_date)->format('Ymd'), $attendance->id),
                'hr_employee_id' => $attendance->hr_employee_id,
                'ot_date' => $attendance->attendance_date,
                'ot_start_time' => $attendance->last_out ?: Carbon::parse($attendance->attendance_date)->setTime(0, 0),
                'ot_end_time' => $attendance->last_out ?: Carbon::parse($attendance->attendance_date)->setTime(0, 0),
                'ot_hours' => (float) $attendance->ot_hours,
                'approved_hours' => (float) $attendance->ot_hours_approved,
                'ot_type' => $attendance->is_holiday ? 'holiday' : ($attendance->is_week_off ? 'weekly_off' : 'normal'),
                'rate_multiplier' => $multiplier,
                'hourly_rate' => 0,
                'ot_amount' => 0,
                'status' => (string) ($attendance->ot_status ?: 'pending'),
                'requested_by' => $attendance->created_by,
                'requested_at' => $attendance->created_at,
                'approved_by' => $attendance->ot_approved_by,
                'approved_at' => $attendance->ot_approved_at,
                'approval_remarks' => $attendance->ot_status === 'approved' && ! ($policy ? (bool) $policy->ot_needs_approval : (bool) setting('hr.enable_ot_approval', true)) ? 'Auto-approved by OT policy/settings.' : null,
                'rejection_reason' => $attendance->ot_status === 'rejected' ? 'Rejected from attendance OT approval' : null,
                'hr_payroll_id' => null,
                'is_paid' => false,
            ]
        );
    }
}
