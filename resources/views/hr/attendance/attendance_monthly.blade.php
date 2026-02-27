@extends('layouts.erp')

@section('title', 'Monthly Attendance')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Monthly Attendance</h1>
            <small class="text-muted">{{ $startDate->format('F Y') }}</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('hr.attendance.monthly', ['month' => $startDate->copy()->subMonth()->month, 'year' => $startDate->copy()->subMonth()->year]) }}" 
               class="btn btn-outline-secondary">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="{{ route('hr.attendance.monthly') }}" class="btn btn-outline-secondary">Today</a>
            <a href="{{ route('hr.attendance.monthly', ['month' => $startDate->copy()->addMonth()->month, 'year' => $startDate->copy()->addMonth()->year]) }}" 
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
                            <option value="{{ $m }}" {{ $startDate->month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                            <option value="{{ $y }}" {{ $startDate->year == $y ? 'selected' : '' }}>{{ $y }}</option>
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
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Name or code..." value="{{ request('q') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Filter
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
        <span class="badge bg-dark me-2">HO - Holiday</span>
        <span class="badge bg-primary me-2">OD - On Duty</span>
    </div>

    {{-- Monthly Calendar Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0" style="font-size: 12px;">
                    <thead class="table-dark">
                        <tr>
                            <th class="sticky-col" style="min-width: 150px;">Employee</th>
                            @for($day = 1; $day <= $endDate->day; $day++)
                                @php
                                    $date = $startDate->copy()->day($day);
                                    $isWeekend = $date->isWeekend();
                                    $isHoliday = in_array(str_pad($day, 2, '0', STR_PAD_LEFT), $holidays ?? []);
                                @endphp
                                <th class="text-center {{ $isWeekend || $isHoliday ? 'bg-secondary' : '' }}" 
                                    style="min-width: 35px;">
                                    {{ $day }}<br>
                                    <small>{{ $date->format('D')[0] }}</small>
                                </th>
                            @endfor
                            <th class="text-center" style="min-width: 40px;">P</th>
                            <th class="text-center" style="min-width: 40px;">A</th>
                            <th class="text-center" style="min-width: 40px;">L</th>
                            <th class="text-center" style="min-width: 50px;">PD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php
                                $empAttendance = $attendanceRecords[$employee->id] ?? collect();
                                $present = 0; $absent = 0; $leave = 0; $halfDay = 0;
                            @endphp
                            <tr>
                                <td class="sticky-col">
                                    <strong>{{ $employee->employee_code }}</strong><br>
                                    <small>{{ $employee->full_name }}</small>
                                </td>
                                @for($day = 1; $day <= $endDate->day; $day++)
                                    @php
                                        $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                                        $att = $empAttendance[$dayStr] ?? null;
                                        $date = $startDate->copy()->day($day);
                                        $isWeekend = $date->isWeekend();
                                        $isHoliday = in_array($dayStr, $holidays ?? []);
                                        
                                        $status = '-';
                                        $bgClass = '';
                                        
                                        if ($att && $att->first()) {
                                            $attRecord = $att->first();
                                            switch($attRecord->status) {
                                                case 'present':
                                                    $status = 'P';
                                                    $bgClass = 'bg-success text-white';
                                                    $present++;
                                                    break;
                                                case 'absent':
                                                    $status = 'A';
                                                    $bgClass = 'bg-danger text-white';
                                                    $absent++;
                                                    break;
                                                case 'half_day':
                                                    $status = 'H';
                                                    $bgClass = 'bg-warning';
                                                    $halfDay++;
                                                    break;
                                                case 'leave':
                                                    $status = 'L';
                                                    $bgClass = 'bg-info text-white';
                                                    $leave++;
                                                    break;
                                                case 'weekly_off':
                                                    $status = 'WO';
                                                    $bgClass = 'bg-secondary text-white';
                                                    break;
                                                case 'holiday':
                                                    $status = 'HO';
                                                    $bgClass = 'bg-dark text-white';
                                                    break;
                                                case 'on_duty':
                                                    $status = 'OD';
                                                    $bgClass = 'bg-primary text-white';
                                                    $present++;
                                                    break;
                                                default:
                                                    $status = substr($attRecord->status, 0, 1);
                                            }
                                        } elseif ($isHoliday) {
                                            $status = 'HO';
                                            $bgClass = 'bg-dark text-white';
                                        } elseif ($isWeekend) {
                                            $status = 'WO';
                                            $bgClass = 'bg-secondary text-white';
                                        } elseif ($date <= now()) {
                                            $status = '-';
                                            $bgClass = 'bg-light';
                                        }
                                        
                                        $paidDays = $present + ($halfDay * 0.5) + $leave;
                                    @endphp
                                    <td class="text-center {{ $bgClass }}" style="font-size: 10px;">
                                        {{ $status }}
                                    </td>
                                @endfor
                                <td class="text-center fw-bold text-success">{{ $present }}</td>
                                <td class="text-center fw-bold text-danger">{{ $absent }}</td>
                                <td class="text-center fw-bold text-info">{{ $leave }}</td>
                                <td class="text-center fw-bold">{{ $paidDays }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $endDate->day + 5 }}" class="text-center text-muted py-4">
                                    No employees found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.sticky-col {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 1;
    border-right: 2px solid #dee2e6;
}
thead .sticky-col {
    background: #212529;
    z-index: 2;
}
.table-responsive {
    max-height: 70vh;
    overflow: auto;
}
thead {
    position: sticky;
    top: 0;
    z-index: 3;
}
</style>
@endpush
@endsection
