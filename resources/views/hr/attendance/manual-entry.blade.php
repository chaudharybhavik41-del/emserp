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
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Entry Details</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('hr.attendance.manual-entry') }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <select name="hr_employee_id" class="form-select select2 @error('hr_employee_id') is-invalid @enderror" required>
                                    <option value="">Select Employee</option>
                                    @foreach($employees ?? [] as $employee)
                                        <option value="{{ $employee->id }}" {{ old('hr_employee_id') == $employee->id ? 'selected' : '' }}>
                                            {{ $employee->employee_code }} - {{ $employee->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('hr_employee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="attendance_date" class="form-control @error('attendance_date') is-invalid @enderror" 
                                       value="{{ old('attendance_date', date('Y-m-d')) }}" required>
                                @error('attendance_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">First In Time</label>
                                <input type="time" name="first_in" class="form-control @error('first_in') is-invalid @enderror" 
                                       value="{{ old('first_in') }}">
                                @error('first_in')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Last Out Time</label>
                                <input type="time" name="last_out" class="form-control @error('last_out') is-invalid @enderror" 
                                       value="{{ old('last_out') }}">
                                @error('last_out')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="">Select Status</option>
                                    <option value="present" {{ old('status') == 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent" {{ old('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                                    <option value="half_day" {{ old('status') == 'half_day' ? 'selected' : '' }}>Half Day</option>
                                    <option value="on_duty" {{ old('status') == 'on_duty' ? 'selected' : '' }}>On Duty</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Remarks <span class="text-danger">*</span></label>
                                <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" 
                                          rows="3" required placeholder="Reason for manual entry">{{ old('remarks') }}</textarea>
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

        <div class="col-lg-4">
            {{-- Help --}}
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 if available
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select Employee',
            allowClear: true
        });
    }
});
</script>
@endpush
@endsection
