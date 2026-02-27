@extends('layouts.erp')

@section('title', 'Leave Calendar')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Leave Calendar</h1>
            <small class="text-muted">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</small>
        </div>
        <div class="btn-group">
            @php
                $prevMonth = $month == 1 ? 12 : $month - 1;
                $prevYear = $month == 1 ? $year - 1 : $year;
                $nextMonth = $month == 12 ? 1 : $month + 1;
                $nextYear = $month == 12 ? $year + 1 : $year;
            @endphp
            <a href="{{ route('hr.leave.calendar', ['month' => $prevMonth, 'year' => $prevYear]) }}" 
               class="btn btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="{{ route('hr.leave.calendar') }}" class="btn btn-outline-secondary">Today</a>
            <a href="{{ route('hr.leave.calendar', ['month' => $nextMonth, 'year' => $nextYear]) }}" 
               class="btn btn-outline-secondary">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3">
        <span class="badge bg-success me-2">CL - Casual</span>
        <span class="badge bg-info me-2">SL - Sick</span>
        <span class="badge bg-primary me-2">EL - Earned</span>
        <span class="badge bg-warning text-dark me-2">ML - Maternity</span>
        <span class="badge bg-secondary me-2">LWP - Without Pay</span>
        <span class="badge bg-danger me-2">CO - Comp Off</span>
    </div>

    {{-- Calendar Grid --}}
    <div class="card">
        <div class="card-body">
            {{-- Day Headers --}}
            <div class="row mb-2">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                    <div class="col text-center fw-bold {{ $day == 'Sun' ? 'text-danger' : '' }}">
                        {{ $day }}
                    </div>
                @endforeach
            </div>

            {{-- Calendar Days --}}
            @php
                $firstDay = \Carbon\Carbon::create($year, $month, 1);
                $lastDay = $firstDay->copy()->endOfMonth();
                $startPadding = $firstDay->dayOfWeek;
                $totalDays = $lastDay->day;
            @endphp

            <div class="row">
                {{-- Empty cells for padding --}}
                @for($i = 0; $i < $startPadding; $i++)
                    <div class="col calendar-day bg-light"></div>
                @endfor

                {{-- Actual days --}}
                @for($day = 1; $day <= $totalDays; $day++)
                    @php
                        $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dayData = $calendarData[$dateKey] ?? null;
                        $isWeekend = $dayData['is_weekend'] ?? false;
                        $holiday = $dayData['holiday'] ?? null;
                        $leaves = $dayData['leaves'] ?? collect();
                    @endphp

                    <div class="col calendar-day {{ $isWeekend ? 'bg-light' : '' }} {{ $holiday ? 'bg-info bg-opacity-25' : '' }}">
                        <div class="day-header d-flex justify-content-between">
                            <span class="fw-bold {{ $isWeekend ? 'text-danger' : '' }}">{{ $day }}</span>
                            @if($holiday)
                                <i class="bi bi-star-fill text-info" title="{{ $holiday->name ?? 'Holiday' }}"></i>
                            @endif
                        </div>
                        <div class="day-content">
                            @foreach($leaves as $leave)
                                @php
                                    $bgColor = match($leave->leaveType->code ?? '') {
                                        'CL' => 'success',
                                        'SL' => 'info',
                                        'EL' => 'primary',
                                        'ML', 'PL' => 'warning',
                                        'LWP' => 'secondary',
                                        'CO' => 'danger',
                                        default => 'dark',
                                    };
                                @endphp
                                <div class="leave-entry badge bg-{{ $bgColor }} d-block mb-1 text-truncate" 
                                     title="{{ $leave->employee->full_name ?? '' }} - {{ $leave->leaveType->name ?? '' }}">
                                    {{ Str::limit($leave->employee->first_name ?? '', 10) }}
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Start new row after Saturday --}}
                    @if(($startPadding + $day) % 7 == 0)
                        </div><div class="row">
                    @endif
                @endfor

                {{-- Empty cells for end padding --}}
                @php
                    $endPadding = 7 - (($startPadding + $totalDays) % 7);
                    if ($endPadding == 7) $endPadding = 0;
                @endphp
                @for($i = 0; $i < $endPadding; $i++)
                    <div class="col calendar-day bg-light"></div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Today's Summary --}}
    @php
        $today = now()->format('Y-m-d');
        $todayData = $calendarData[$today] ?? null;
        $todayLeaves = $todayData['leaves'] ?? collect();
    @endphp
    @if($month == now()->month && $year == now()->year && $todayLeaves->count() > 0)
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-calendar-check me-1"></i> On Leave Today</h6>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($todayLeaves as $leave)
                    <div class="col-md-4 mb-2">
                        <div class="d-flex align-items-center">
                            <div class="avatar-sm bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center">
                                {{ strtoupper(substr($leave->employee->first_name ?? 'U', 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-bold">{{ $leave->employee->full_name ?? '-' }}</div>
                                <small class="text-muted">{{ $leave->leaveType->name ?? '-' }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
.calendar-day {
    min-height: 100px;
    border: 1px solid #dee2e6;
    padding: 5px;
}
.calendar-day .day-header {
    border-bottom: 1px solid #eee;
    margin-bottom: 5px;
    padding-bottom: 2px;
}
.calendar-day .leave-entry {
    font-size: 10px;
    cursor: pointer;
}
.avatar-sm {
    width: 35px;
    height: 35px;
    font-size: 14px;
    color: white;
}
</style>
@endpush
@endsection
