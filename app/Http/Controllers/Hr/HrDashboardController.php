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
            ->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN ? AND ?", [
                now()->format('m-d'),
                now()->addDays(7)->format('m-d')
            ])
            ->orderByRaw("DATE_FORMAT(date_of_birth, '%m-%d')")
            ->limit(5)
            ->get();

        // Upcoming Work Anniversaries (next 7 days)
        $upcomingAnniversaries = HrEmployee::active()
            ->whereNotNull('date_of_joining')
            ->whereRaw("DATE_FORMAT(date_of_joining, '%m-%d') BETWEEN ? AND ?", [
                now()->format('m-d'),
                now()->addDays(7)->format('m-d')
            ])
            ->whereYear('date_of_joining', '<', now()->year)
            ->orderByRaw("DATE_FORMAT(date_of_joining, '%m-%d')")
            ->limit(5)
            ->get();

        // Probation Due (next 30 days)
        $probationDue = HrEmployee::active()
            ->onProbation()
            ->whereNotNull('date_of_joining')
            ->whereNotNull('probation_period_months')
            ->whereRaw("DATE_ADD(date_of_joining, INTERVAL probation_period_months MONTH) BETWEEN ? AND ?", [
                now()->format('Y-m-d'),
                now()->addDays(30)->format('Y-m-d')
            ])
            ->limit(5)
            ->get();

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
}
