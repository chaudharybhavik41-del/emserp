@extends('layouts.erp')

@section('title', 'Manual Attendance Entry')

@section('content')
        <div class="container-fluid">

            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-0">Manual Attendance Entry</h1>
                    <small class="text-muted">Add or modify attendance manually</small>
                </div>
                <a href="{{ route('hr.attendance.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="row">
                {{-- Left Column: Form --}}
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Entry Details</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('hr.attendance.manual-entry') }}" method="POST">
                                @csrf
                                <div class="row g-3">

                                    {{-- Employee --}}
                                    <div class="col-md-6">
                                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                                        <select name="hr_employee_id" id="employee_dropdown"
                                            class="form-select  @error('hr_employee_id') is-invalid @enderror" required>
                                            <option value="">Select Employee</option>
                                            @foreach($employees ?? [] as $employee)
                                                @php
        // Determine which employee should be selected
        $selected = old('hr_employee_id')
            ?? ($attendance->hr_employee_id ?? null)
            ?? ($selectedEmployeeId ?? null);
                                                @endphp
                                                <option value="{{ $employee->id }}" {{ $employee->id == $selected ? 'selected' : '' }}>
                                                    {{ $employee->employee_code }} - {{ $employee->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('hr_employee_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>


                                    {{-- Date --}}
                                    <div class="col-md-6">
                                        <label class="form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" name="attendance_date"
                                            class="form-control @error('attendance_date') is-invalid @enderror"
                                            value="{{ old('attendance_date', $attendance->attendance_date ?? $date ?? date('Y-m-d')) }}"
                                            required>
                                        @error('attendance_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- First In --}}
                                    <div class="col-md-4">
                                        <label class="form-label">First In Time</label>
                                        <input type="time" name="first_in"
                                            class="form-control @error('first_in') is-invalid @enderror"
                                            value="{{ old('first_in', optional($attendance)->first_in?->format('H:i') ?? '') }}">
                                        @error('first_in')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Last Out --}}
                                    <div class="col-md-4">
                                        <label class="form-label">Last Out Time</label>
                                        <input type="time" name="last_out"
                                            class="form-control @error('last_out') is-invalid @enderror"
                                            value="{{ old('last_out', optional($attendance)->last_out?->format('H:i') ?? '') }}">
                                        @error('last_out')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Status --}}
                                    <div class="col-md-4">
                                        <label class="form-label">Status <span class="text-danger">*</span></label>
                                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                            <option value="">Select Status</option>
                                            @foreach(['present', 'absent', 'half_day', 'on_duty'] as $status)
                                                <option value="{{ $status }}"
                                                    {{ (old('status') ?? $attendance->status ?? '') == $status ? 'selected' : '' }}>
                                                    {{ ucwords(str_replace('_', ' ', $status)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Remarks --}}
                                    <div class="col-12">
                                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                        <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror"
                                            rows="3" required
                                            placeholder="Reason for manual entry">{{ old('remarks', $attendance->remarks ?? '') }}</textarea>
                                        @error('remarks')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>

                                <hr class="my-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('hr.attendance.index') }}" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i> Save Entry
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Right Column --}}
                <div class="col-lg-4">
                    {{-- Guidelines --}}
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-1"></i> Guidelines</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li class="mb-2">Manual entries require a valid reason/remarks</li>
                                <li class="mb-2">If times are provided, the system will auto-calculate work hours</li>
                                <li class="mb-2">Existing attendance for the same date will be updated</li>
                                <li class="mb-2">Manual entries are flagged for audit purposes</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Recent Manual Entries --}}
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Recent Manual Entries</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @php
    $recentManual = \App\Models\Hr\HrAttendance::where('is_manual_entry', true)
        ->with('employee')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
                                @endphp
                                @forelse($recentManual as $entry)
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <span>{{ $entry->employee->employee_code ?? '-' }}</span>
                                            <small class="text-muted">{{ $entry->attendance_date->format('d M') }}</small>
                                        </div>
                                        <small class="text-muted">{{ Str::limit($entry->remarks, 30) }}</small>
                                    </li>
                                @empty
                                    <li class="list-group-item text-muted text-center">No recent entries</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener("DOMContentLoaded", function () {

                    // Initialize Select2 first
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        $('.select2').select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Select Employee',
                            allowClear: true
                        });
                    }

                    // Prefill form fields from localStorage
                    const fields = {
                        employee_id: 'employee_dropdown', // special case: select2 dropdown
                        date: 'attendance_date',
                        status: 'status',
                        first_in: 'first_in',
                        last_out: 'last_out',
                        remarks: 'remarks'
                    };

                    for (let key in fields) {
                        const value = localStorage.getItem('manual_entry_' + key);
                        if (value) {
                            let el;
                            if (key === 'employee_id') {
                                el = document.getElementById(fields[key]);
                                if (el) el.value = value;
                                // trigger select2 update if applicable
                                if (typeof $ !== 'undefined' && $(el).hasClass('select2')) {
                                    $(el).trigger('change');
                                }
                            } else {
                                el = document.querySelector(`[name="${fields[key]}"]`);
                                if (el) el.value = value;
                            }
                            // Remove the localStorage item after filling
                            localStorage.removeItem('manual_entry_' + key);
                        }
                    }
                });
            </script>
        @endpush
@endsection