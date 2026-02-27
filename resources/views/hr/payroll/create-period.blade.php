@extends('layouts.erp')

@section('title', 'Create Payroll Period')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">Create Payroll Period</h1>
            <small class="text-muted">Set up a new payroll period for salary processing</small>
        </div>
        <a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Period Details</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('hr.payroll.create-period') }}" method="POST">
                        @csrf
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Year <span class="text-danger">*</span></label>
                                <select name="year" class="form-select @error('year') is-invalid @enderror" required>
                                    @for($y = date('Y') - 1; $y <= date('Y') + 1; $y++)
                                        <option value="{{ $y }}" {{ old('year', date('Y')) == $y ? 'selected' : '' }}>{{ $y }}</option>
                                    @endfor
                                </select>
                                @error('year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Month <span class="text-danger">*</span></label>
                                <select name="month" class="form-select @error('month') is-invalid @enderror" required>
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ old('month', date('n')) == $m ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                        </option>
                                    @endfor
                                </select>
                                @error('month')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Period Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" 
                                       value="{{ old('period_start', date('Y-m-01')) }}" required>
                                @error('period_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Period End Date <span class="text-danger">*</span></label>
                                <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" 
                                       value="{{ old('period_end', date('Y-m-t')) }}" required>
                                @error('period_end')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Attendance Start Date</label>
                                <input type="date" name="attendance_start" class="form-control @error('attendance_start') is-invalid @enderror" 
                                       value="{{ old('attendance_start', date('Y-m-01')) }}">
                                @error('attendance_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Leave blank to use period start date</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Attendance End Date</label>
                                <input type="date" name="attendance_end" class="form-control @error('attendance_end') is-invalid @enderror" 
                                       value="{{ old('attendance_end', date('Y-m-t')) }}">
                                @error('attendance_end')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Leave blank to use period end date</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Working Days <span class="text-danger">*</span></label>
                                <input type="number" name="working_days" class="form-control @error('working_days') is-invalid @enderror" 
                                       value="{{ old('working_days', 26) }}" min="1" max="31" required>
                                @error('working_days')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" 
                                       value="{{ old('payment_date') }}">
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3">{{ old('remarks') }}</textarea>
                                @error('remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Create Period
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Help Card --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i> Help</h6>
                </div>
                <div class="card-body">
                    <h6>Payroll Period</h6>
                    <p class="text-muted small">A payroll period defines the time frame for which salaries are calculated and paid.</p>
                    
                    <h6>Period Dates</h6>
                    <p class="text-muted small">Period start and end dates define the salary calculation period. Typically this is the 1st to the last day of a month.</p>
                    
                    <h6>Attendance Dates</h6>
                    <p class="text-muted small">If your attendance cycle differs from the payroll period (e.g., 26th to 25th), specify the attendance date range separately.</p>
                    
                    <h6>Working Days</h6>
                    <p class="text-muted small">Total working days in this period. Used to calculate per-day salary for LWP deductions and partial month payments.</p>
                </div>
            </div>

            {{-- Recent Periods --}}
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Recent Periods</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @php
                            $recentPeriods = \App\Models\Hr\HrPayrollPeriod::orderByDesc('year')
                                ->orderByDesc('month')
                                ->limit(5)
                                ->get();
                        @endphp
                        @forelse($recentPeriods as $period)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $period->name }}</span>
                                <span class="badge bg-{{ $period->status_color }}">{{ $period->status_label }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center">No periods created yet</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.querySelector('select[name="year"]');
    const monthSelect = document.querySelector('select[name="month"]');
    const periodStart = document.querySelector('input[name="period_start"]');
    const periodEnd = document.querySelector('input[name="period_end"]');
    const attendanceStart = document.querySelector('input[name="attendance_start"]');
    const attendanceEnd = document.querySelector('input[name="attendance_end"]');
    const workingDays = document.querySelector('input[name="working_days"]');

    function updateDates() {
        const year = parseInt(yearSelect.value);
        const month = parseInt(monthSelect.value);
        
        // Calculate first and last day of month
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        
        periodStart.value = firstDay.toISOString().split('T')[0];
        periodEnd.value = lastDay.toISOString().split('T')[0];
        attendanceStart.value = firstDay.toISOString().split('T')[0];
        attendanceEnd.value = lastDay.toISOString().split('T')[0];
        
        // Calculate working days (exclude Sundays)
        let days = 0;
        let current = new Date(firstDay);
        while (current <= lastDay) {
            if (current.getDay() !== 0) { // Not Sunday
                days++;
            }
            current.setDate(current.getDate() + 1);
        }
        workingDays.value = days;
    }

    yearSelect.addEventListener('change', updateDates);
    monthSelect.addEventListener('change', updateDates);
});
</script>
@endpush
@endsection
