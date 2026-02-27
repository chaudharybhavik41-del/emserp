<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Hr\HrAttendance;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrEmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $cards = [
            ['title' => 'Total Employees', 'value' => HrEmployee::count()],
            ['title' => 'Active Employees', 'value' => HrEmployee::active()->count()],
            ['title' => 'On Probation', 'value' => HrEmployee::onProbation()->count()],
            ['title' => 'Docs Expiring (60d)', 'value' => HrEmployeeDocument::expiringSoon(60)->count()],
        ];

        return view('hr.reports.index', compact('cards'));
    }

    public function headcount(): View
    {
        $rows = Department::query()
            ->leftJoin('hr_employees', 'departments.id', '=', 'hr_employees.department_id')
            ->selectRaw('departments.name as department, COUNT(hr_employees.id) as total')
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name')
            ->get();

        return $this->listView('Headcount Report', ['Department', 'Employees'], $rows->map(fn($r) => [$r->department, $r->total])->toArray());
    }

    public function attrition(Request $request): View
    {
        $year = $request->integer('year', now()->year);

        $rows = HrEmployee::query()
            ->whereYear('date_of_leaving', $year)
            ->selectRaw('MONTH(date_of_leaving) as month_no, COUNT(*) as exits')
            ->groupBy('month_no')
            ->orderBy('month_no')
            ->get()
            ->map(function ($r) {
                return [now()->startOfYear()->month($r->month_no)->format('F'), (int) $r->exits];
            })->toArray();

        return $this->listView("Attrition Report ({$year})", ['Month', 'Exits'], $rows);
    }

    public function birthday(): View
    {
        $rows = HrEmployee::active()
            ->whereMonth('date_of_birth', now()->month)
            ->orderByRaw('DAY(date_of_birth) asc')
            ->get(['employee_code', 'first_name', 'last_name', 'date_of_birth'])
            ->map(fn($e) => [$e->employee_code, $e->full_name, optional($e->date_of_birth)->format('d M') ?: '-'])
            ->toArray();

        return $this->listView('Birthday Report (Current Month)', ['Emp Code', 'Name', 'Birthday'], $rows);
    }

    public function anniversary(): View
    {
        $rows = HrEmployee::active()
            ->whereMonth('date_of_joining', now()->month)
            ->orderByRaw('DAY(date_of_joining) asc')
            ->get(['employee_code', 'first_name', 'last_name', 'date_of_joining'])
            ->map(function ($e) {
                $years = $e->date_of_joining ? $e->date_of_joining->diffInYears(now()) : 0;
                return [$e->employee_code, $e->full_name, optional($e->date_of_joining)->format('d M') ?: '-', $years . ' yrs'];
            })->toArray();

        return $this->listView('Work Anniversary Report (Current Month)', ['Emp Code', 'Name', 'Date', 'Service'], $rows);
    }

    public function probationDue(): View
    {
        $rows = HrEmployee::active()
            ->whereNull('confirmation_date')
            ->get()
            ->filter(fn($e) => $e->probation_end_date && $e->probation_end_date->between(now(), now()->copy()->addDays(45)))
            ->map(fn($e) => [$e->employee_code, $e->full_name, $e->probation_end_date?->format('d M Y')])
            ->values()
            ->toArray();

        return $this->listView('Probation Due (Next 45 Days)', ['Emp Code', 'Name', 'Probation End'], $rows);
    }

    public function contractExpiry(): View
    {
        $rows = HrEmployee::query()
            ->where('employment_type', 'contract')
            ->whereNotNull('date_of_leaving')
            ->whereBetween('date_of_leaving', [now(), now()->copy()->addDays(60)])
            ->orderBy('date_of_leaving')
            ->get()
            ->map(fn($e) => [$e->employee_code, $e->full_name, $e->date_of_leaving?->format('d M Y')])
            ->toArray();

        return $this->listView('Contract Expiry (Next 60 Days)', ['Emp Code', 'Name', 'Expiry Date'], $rows);
    }

    public function documentExpiry(): View
    {
        $rows = HrEmployeeDocument::with('employee')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->copy()->addDays(60)->toDateString()])
            ->orderBy('expiry_date')
            ->get()
            ->map(function ($d) {
                return [
                    $d->employee?->employee_code ?? '-',
                    $d->employee?->full_name ?? '-',
                    ucfirst(str_replace('_', ' ', $d->document_type)),
                    $d->expiry_date?->format('d M Y') ?: '-',
                ];
            })->toArray();

        return $this->listView('Document Expiry (Next 60 Days)', ['Emp Code', 'Name', 'Document', 'Expiry'], $rows);
    }

    public function employeeDirectory(Request $request): View
    {
        $rows = HrEmployee::with(['department', 'designation'])
            ->active()
            ->orderBy('employee_code')
            ->get()
            ->map(fn($e) => [$e->employee_code, $e->full_name, $e->department?->name ?? '-', $e->designation?->name ?? '-', $e->personal_mobile ?? '-'])
            ->toArray();

        return $this->listView('Employee Directory', ['Emp Code', 'Name', 'Department', 'Designation', 'Mobile'], $rows);
    }

    public function musterRoll(Request $request): View
    {
        $from = $request->date('from', now()->startOfMonth())?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to', now()->endOfMonth())?->toDateString() ?? now()->endOfMonth()->toDateString();

        $rows = HrAttendance::with('employee')
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get()
            ->map(fn($a) => [
                $a->attendance_date?->format('d M Y') ?: '-',
                $a->employee?->employee_code ?? '-',
                $a->employee?->full_name ?? '-',
                optional($a->status)->value ?? (string) $a->status,
                (string) ($a->working_hours ?? 0),
            ])->toArray();

        return $this->listView('Muster Roll', ['Date', 'Emp Code', 'Name', 'Status', 'Work Hrs'], $rows);
    }

    private function listView(string $title, array $headers, array $rows): View
    {
        return view('hr.reports.list', compact('title', 'headers', 'rows'));
    }
}
