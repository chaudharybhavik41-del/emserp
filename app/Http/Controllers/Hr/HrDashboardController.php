<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrLeaveApplication;
use App\Models\Hr\HrPayrollPeriod;
use App\Models\Hr\HrPayroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HrDashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $nextSevenDays = $today->copy()->addDays(7)->endOfDay();
        $nextThirtyDays = $today->copy()->addDays(30)->endOfDay();

        // Employee Statistics
        $employeeStats = [
            'total' => HrEmployee::count(),
            'active' => HrEmployee::active()->count(),
            'on_probation' => HrEmployee::onProbation()->count(),
            'joined_this_month' => HrEmployee::whereMonth('date_of_joining', now()->month)
                ->whereYear('date_of_joining', now()->year)
                ->count(),
            'left_this_month' => HrEmployee::whereMonth('date_of_leaving', now()->month)
                ->whereYear('date_of_leaving', now()->year)
                ->count(),
        ];

        // Today's Attendance
        $todayAttendance = [
            'present' => HrAttendance::whereDate('attendance_date', today())
                ->whereIn('status', ['present', 'late', 'half_day'])
                ->count(),
            'absent' => HrAttendance::whereDate('attendance_date', today())
                ->where('status', 'absent')
                ->count(),
            'on_leave' => HrAttendance::whereDate('attendance_date', today())
                ->where('status', 'leave')
                ->count(),
            'late' => HrAttendance::whereDate('attendance_date', today())
                ->where('late_minutes', '>', 0)
                ->count(),
        ];

        // Pending Approvals
        $pendingApprovals = [
            'leave' => HrLeaveApplication::where('status', 'pending')->count(),
            'overtime' => HrAttendance::where('ot_status', 'pending')->count(),
            'regularization' => DB::table('hr_attendance_regularizations')
                ->where('status', 'pending')
                ->count(),
        ];

        // Current Payroll Period
        $currentPayroll = HrPayrollPeriod::where('status', '!=', 'paid')
            ->orderBy('period_start', 'desc')
            ->first();

        if ($currentPayroll) {
            $currentPayroll->loadCount('payrolls');
            $currentPayroll->total_amount = HrPayroll::where('hr_payroll_period_id', $currentPayroll->id)
                ->sum('net_pay');
        }

        // Department-wise Employee Count
        $departmentWise = HrEmployee::active()
            ->select('department_id', DB::raw('count(*) as count'))
            ->groupBy('department_id')
            ->with('department:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'department' => $item->department?->name ?? 'Unassigned',
                    'count' => $item->count,
                ];
            });

        // Upcoming Birthdays (next 7 days)
        $upcomingBirthdays = HrEmployee::active()
            ->whereNotNull('date_of_birth')
            ->get()
            ->map(function (HrEmployee $employee) use ($today) {
                $nextBirthday = $this->nextAnnualOccurrence($employee->date_of_birth, $today);

                return [
                    'employee' => $employee,
                    'event_date' => $nextBirthday,
                ];
            })
            ->filter(fn (array $item) => $item['event_date'] !== null && $item['event_date']->lte($nextSevenDays))
            ->sortBy(fn (array $item) => $item['event_date']->timestamp)
            ->take(5)
            ->pluck('employee')
            ->values();

        // Upcoming Work Anniversaries (next 7 days)
        $upcomingAnniversaries = HrEmployee::active()
            ->whereNotNull('date_of_joining')
            ->whereYear('date_of_joining', '<', now()->year)
            ->get()
            ->map(function (HrEmployee $employee) use ($today) {
                $nextAnniversary = $this->nextAnnualOccurrence($employee->date_of_joining, $today);

                return [
                    'employee' => $employee,
                    'event_date' => $nextAnniversary,
                ];
            })
            ->filter(fn (array $item) => $item['event_date'] !== null && $item['event_date']->lte($nextSevenDays))
            ->sortBy(fn (array $item) => $item['event_date']->timestamp)
            ->take(5)
            ->pluck('employee')
            ->values();

        // Probation Due (next 30 days)
        $probationDue = HrEmployee::active()
            ->whereNotNull('date_of_joining')
            ->whereNotNull('probation_period_months')
            ->whereNull('confirmation_date')
            ->get()
            ->filter(function (HrEmployee $employee) use ($today, $nextThirtyDays) {
                $probationEndDate = $employee->probation_end_date;

                return $probationEndDate !== null
                    && $probationEndDate->gte($today)
                    && $probationEndDate->lte($nextThirtyDays);
            })
            ->sortBy(fn (HrEmployee $employee) => $employee->probation_end_date?->timestamp ?? PHP_INT_MAX)
            ->take(5)
            ->values();

        // Attendance Trend (last 7 days)
        $attendanceTrend = HrAttendance::select(
            'attendance_date',
            DB::raw("SUM(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present"),
            DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
            DB::raw("SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as on_leave")
        )
            ->whereBetween('attendance_date', [now()->subDays(6), now()])
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get();

        // Recent Leave Applications
        $recentLeaves = HrLeaveApplication::with(['employee', 'leaveType'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // On Leave Today
        $onLeaveToday = HrLeaveApplication::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->where('from_date', '<=', today())
            ->where('to_date', '>=', today())
            ->get();

        return view('hr.dashboard', compact(
            'employeeStats',
            'todayAttendance',
            'pendingApprovals',
            'currentPayroll',
            'departmentWise',
            'upcomingBirthdays',
            'upcomingAnniversaries',
            'probationDue',
            'attendanceTrend',
            'recentLeaves',
            'onLeaveToday'
        ));
    }

    private function nextAnnualOccurrence(?Carbon $date, Carbon $today): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        $occurrence = $date->copy()->year($today->year);

        if ($occurrence->lt($today)) {
            $occurrence->addYear();
        }

        return $occurrence;
    }
}
