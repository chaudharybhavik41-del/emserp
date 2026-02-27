<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Enums\Hr\AttendanceStatus;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrEmployee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function handle(Request $request)
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

                    $status = (string) ($row['status'] ?? '');
                    // Validate against enum values
                    $allowed = array_map(fn($c) => $c->value, AttendanceStatus::cases());
                    if (! in_array($status, $allowed, true)) {
                        continue;
                    }

                    HrAttendance::updateOrCreate(
                        [
                            'hr_employee_id'  => $employeeId,
                            'attendance_date' => $date,
                        ],
                        [
                            'status'   => $status,
                            'in_time'  => $row['in_time'] ?? null,
                            'out_time' => $row['out_time'] ?? null,
                            'ot_hours' => $row['ot_hours'] ?? 0,
                            'remarks'  => $row['remarks'] ?? null,
                            'updated_by' => auth()->id(),
                            'created_by' => auth()->id(),
                        ]
                    );
                }
            });

            return redirect()
                ->route('hr.attendance.bulk-entry', [
                    'date' => $date,
                ])
                ->with('success', 'Attendance saved successfully for ' . $date . '.');
        }

        // GET: employees + existing attendance for the date
        $employeesQuery = HrEmployee::query()
            ->where('is_active', true)
            ->orderBy('employee_code');

        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }
        if ($shiftId) {
            $employeesQuery->where('shift_id', $shiftId);
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
}
