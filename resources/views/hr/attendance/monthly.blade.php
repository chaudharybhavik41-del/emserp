@extends('layouts.erp')

@section('title', 'Monthly Attendance')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Monthly Attendance Calendar</h1>
            <small class="text-muted">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</small>
        </div>
        <div class="btn-group">
            @php
                $prevMonth = $month == 1 ? 12 : $month - 1;
                $prevYear = $month == 1 ? $year - 1 : $year;
                $nextMonth = $month == 12 ? 1 : $month + 1;
                $nextYear = $month == 12 ? $year + 1 : $year;
            @endphp
            <a href="{{ route('hr.attendance.monthly', ['month' => $prevMonth, 'year' => $prevYear]) }}" 
               class="btn btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="{{ route('hr.attendance.monthly') }}" class="btn btn-outline-secondary">Today</a>
            <a href="{{ route('hr.attendance.monthly', ['month' => $nextMonth, 'year' => $nextYear]) }}" 
               class="btn btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <select name="month" class="form-select form-select-sm">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3">
        <span class="badge bg-success me-2">P - Present</span>
        <span class="badge bg-danger me-2">A - Absent</span>
        <span class="badge bg-warning text-dark me-2">H - Half Day</span>
        <span class="badge bg-info me-2">L - Leave</span>
        <span class="badge bg-secondary me-2">WO - Weekly Off</span>
        <span class="badge bg-primary me-2">HO - Holiday</span>
        <span class="badge bg-orange text-dark me-2" style="background-color: #fd7e14 !important;">LT - Late</span>
    </div>

    {{-- Calendar --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-bordered table-sm mb-0" style="font-size: 11px;">
                    <thead class="table-dark">
                        <tr>
                            <th class="sticky-col" style="min-width: 180px; left: 0; z-index: 10;">Employee</th>
                            @for($day = 1; $day <= $daysInMonth; $day++)
                                @php
                                    $date = \Carbon\Carbon::create($year, $month, $day);
                                    $isSunday = $date->isSunday();
                                    $isHoliday = in_array($day, $holidays ?? []);
                                @endphp
                                <th class="text-center {{ $isSunday ? 'bg-secondary' : ($isHoliday ? 'bg-info' : '') }}" 
                                    style="min-width: 30px;">
                                    {{ $day }}<br>
                                    <small>{{ $date->format('D')[0] }}</small>
                                </th>
                            @endfor
                            <th class="text-center" style="min-width: 35px;">P</th>
                            <th class="text-center" style="min-width: 35px;">A</th>
                            <th class="text-center" style="min-width: 35px;">L</th>
                            <th class="text-center" style="min-width: 45px;">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($calendarData as $empData)
                            @php
                                $employee = $empData['employee'];
                                $present = $empData['summary']['present'] ?? 0;
                                $absent = $empData['summary']['absent'] ?? 0;
                                $leave = $empData['summary']['leave'] ?? 0;
                                $halfDay = $empData['summary']['half_day'] ?? 0;
                                $paidDays = $empData['summary']['paid_days'] ?? 0;
                            @endphp
                            <tr>
                                <td class="sticky-col" style="left: 0; background: #fff; z-index: 5;">
                                    <strong>{{ $employee->employee_code ?? '-' }}</strong><br>
                                    <small>{{ $employee->full_name ?? '-' }}</small>
                                </td>
                                @for($day = 1; $day <= $daysInMonth; $day++)
                                    @php
                                        $dayData = $empData['days'][$day] ?? null;
                                        $status = $dayData['code'] ?? '-';
                                        $bgClass = match($status) {
                                            'P' => 'bg-success text-white',
                                            'A' => 'bg-danger text-white',
                                            'H', 'HD' => 'bg-warning',
                                            'L' => 'bg-info text-white',
                                            'WO' => 'bg-secondary text-white',
                                            'HO' => 'bg-primary text-white',
                                            'LT' => 'bg-orange',
                                            'OD' => 'bg-purple text-white',
                                            default => '',
                                        };
                                        $title = $dayData['in'] ?? '';
                                        if (isset($dayData['out'])) $title .= ' - ' . $dayData['out'];
                                    @endphp
                                    <td class="text-center {{ $bgClass }}" title="{{ $title }}" style="cursor: pointer;">
                                        {{ $status }}
                                    </td>
                                @endfor
                                <td class="text-center text-success fw-bold">{{ $present }}</td>
                                <td class="text-center text-danger fw-bold">{{ $absent }}</td>
                                <td class="text-center text-info fw-bold">{{ $leave }}</td>
                                <td class="text-center fw-bold">{{ number_format($paidDays, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $daysInMonth + 5 }}" class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No attendance records found for this period
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Summary --}}
    @if(count($calendarData) > 0)
    <div class="row g-3 mt-4">
        @php
            $totalPresent = collect($calendarData)->sum(fn($e) => $e['summary']['present'] ?? 0);
            $totalAbsent = collect($calendarData)->sum(fn($e) => $e['summary']['absent'] ?? 0);
            $totalLeave = collect($calendarData)->sum(fn($e) => $e['summary']['leave'] ?? 0);
            $totalLate = collect($calendarData)->sum(fn($e) => $e['summary']['late'] ?? 0);
            $totalOT = collect($calendarData)->sum(fn($e) => $e['summary']['ot_hours'] ?? 0);
        @endphp
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-success fs-4 fw-bold">{{ $totalPresent }}</div>
                    <small class="text-muted">Total Present</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-danger fs-4 fw-bold">{{ $totalAbsent }}</div>
                    <small class="text-muted">Total Absent</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-info fs-4 fw-bold">{{ $totalLeave }}</div>
                    <small class="text-muted">On Leave</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-warning fs-4 fw-bold">{{ $totalLate }}</div>
                    <small class="text-muted">Late Count</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-primary fs-4 fw-bold">{{ number_format($totalOT, 1) }}</div>
                    <small class="text-muted">OT Hours</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="text-secondary fs-4 fw-bold">{{ count($calendarData) }}</div>
                    <small class="text-muted">Employees</small>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
.sticky-col {
    position: sticky;
    left: 0;
    background: #fff;
    border-right: 2px solid #dee2e6;
}
thead .sticky-col {
    background: #212529;
    z-index: 10;
}
thead {
    position: sticky;
    top: 0;
    z-index: 5;
}
.bg-orange {
    background-color: #fd7e14 !important;
}
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>
@endpush
@endsection
