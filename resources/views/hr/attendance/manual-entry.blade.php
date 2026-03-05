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
                                            class="form-select @error('hr_employee_id') is-invalid @enderror" required>
                                            <option value="">Select Employee</option>
                                            @foreach($employees ?? [] as $employee)
                                                @php
                                                    // Determine selected employee (cast to string to avoid type issues)
                                                    $selected = (string) (old('hr_employee_id')
                                                        ?? ($attendance->hr_employee_id ?? null)
                                                        ?? ($selectedEmployeeId ?? null));
                                                @endphp
                                                <option value="{{ $employee->id }}" {{ (string) $employee->hr_employee_id == $selected ? 'selected' : '' }}>
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
                                        <input type="date" name="attendance_date" class="form-control @error('attendance_date') is-invalid @enderror"
                                            value="{{ old('attendance_date', $attendance->attendance_date ?? $date ?? date('Y-m-d')) }}" required>
                                        @error('attendance_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- First In --}}
                                    <div class="col-md-4">
                                        <label class="form-label">First In Time</label>
                                        <input type="time" name="first_in" class="form-control @error('first_in') is-invalid @enderror"
                                            value="{{ old('first_in', optional($attendance)->first_in?->format('H:i') ?? '') }}">
                                        @error('first_in')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Last Out --}}
                                    <div class="col-md-4">
                                        <label class="form-label">Last Out Time</label>
                                        <input type="time" name="last_out" class="form-control @error('last_out') is-invalid @enderror"
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
                                                <option value="{{ $status }}" {{ (old('status') ?? $attendance->status ?? '') == $status ? 'selected' : '' }}>
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
                                        <textarea name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="3" required
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

                                {{-- Right Column: Multiple Info Panels --}}
                                <div class="col-lg-4">
                                    <div class="row g-3">

                                        {{-- Panel 1: Selected Employee Info --}}
                                        <div class="col-12">
                                            <div class="card border-info h-100">
                                                <div class="card-header bg-info bg-opacity-10">
                                                    <h6 class="mb-0 text-info"><i class="bi bi-person-circle me-2"></i> Employee Information</h6>
                                                </div>
                                                <div class="card-body" id="employeeInfoPanel">
                                                    <div class="text-muted text-center py-3">
                                                        <small>Select an employee to view details</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Panel 2: Status Guide --}}
                                        <div class="col-12">
                                            <div class="card border-success h-100">
                                                <div class="card-header bg-success bg-opacity-10">
                                                    <h6 class="mb-0 text-success"><i class="bi bi-tags me-2"></i> Status Guide</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="status-guide">
                                                        <div class="mb-2 pb-2 border-bottom">
                                                            <span class="badge bg-success me-2">P</span>
                                                            <strong>Present</strong>
                                                            <small class="text-muted d-block ms-3">Employee attended full day</small>
                                                        </div>
                                                        <div class="mb-2 pb-2 border-bottom">
                                                            <span class="badge bg-danger me-2">A</span>
                                                            <strong>Absent</strong>
                                                            <small class="text-muted d-block ms-3">Employee did not attend</small>
                                                        </div>
                                                        <div class="mb-2 pb-2 border-bottom">
                                                            <span class="badge bg-warning me-2">H</span>
                                                            <strong>Half Day</strong>
                                                            <small class="text-muted d-block ms-3">Employee worked half day</small>
                                                        </div>
                                                        <div class="mb-0">
                                                            <span class="badge bg-primary me-2">OD</span>
                                                            <strong>On Duty</strong>
                                                            <small class="text-muted d-block ms-3">Official duty outside office</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Panel 3: Guidelines --}}
                                        <div class="col-12">
                                            <div class="card border-warning h-100">
                                                <div class="card-header bg-warning bg-opacity-10">
                                                    <h6 class="mb-0 text-warning"><i class="bi bi-exclamation-circle me-2"></i> Guidelines</h6>
                                                </div>
                                                <div class="card-body">
                                                    <ul class="mb-0 ps-3">
                                                        <li class="mb-2"><small>Manual entries require valid reason/remarks</small></li>
                                                        <li class="mb-2"><small>Times auto-calculate work hours</small></li>
                                                        <li class="mb-2"><small>Same date entries will be updated</small></li>
                                                        <li class="mb-0"><small>Flagged for audit purposes</small></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Panel 4: Recent Manual Entries --}}
                                        <div class="col-12">
                                            <div class="card border-primary h-100">
                                                <div class="card-header bg-primary bg-opacity-10">
                                                    <h6 class="mb-0 text-primary"><i class="bi bi-clock-history me-2"></i> Recent Entries (Last 5)</h6>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div class="list-group list-group-sm list-group-flush">
                                                        @php
    $recentManual = \App\Models\Hr\HrAttendance::where('is_manual_entry', true)
        ->with('employee')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
                                                        @endphp
                                                        @forelse($recentManual as $entry)
                                                            <div class="list-group-item px-3 py-2">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div class="flex-grow-1">
                                                                        <strong
                                                                            class="d-block text-dark">{{ $entry->employee->employee_code ?? '-' }}</strong>
                                                                        <small class="text-muted">{{ $entry->employee->full_name ?? '-' }}</small>
                                                                    </div>
                                                                    <small
                                                                        class="text-muted text-end ms-2">{{ $entry->attendance_date->format('d M Y') }}</small>
                                                                </div>
                                                                <small class="text-muted d-block mt-1">{{ Str::limit($entry->remarks, 40) }}</small>
                                                            </div>
                                                        @empty
                                                            <div class="text-muted text-center py-4">
                                                                <small><i class="bi bi-inbox"></i> No recent entries</small>
                                                            </div>
                                                        @endforelse
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Panel 5: Quick Tips --}}
                                        <div class="col-12">
                                            <div class="card border-secondary h-100">
                                                <div class="card-header bg-secondary bg-opacity-10">
                                                    <h6 class="mb-0 text-secondary"><i class="bi bi-lightbulb me-2"></i> Quick Tips</h6>
                                                </div>
                                                <div class="card-body">
                                                    <ul class="mb-0 ps-3">
                                                        <li class="mb-2"><small>Use Tab key to navigate between fields</small></li>
                                                        <li class="mb-2"><small>All red fields are required</small></li>
                                                        <li class="mb-2"><small>Times in 24-hour format (HH:MM)</small></li>
                                                        <li class="mb-0"><small>Remarks must be at least 10 characters</small></li>
                                                    </ul>
                                                </div>
                                            </div>
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
                        // $('#employee_dropdown').select2({
                        //     theme: 'bootstrap-5',
                        //     placeholder: 'Select Employee',
                        //     allowClear: true
                        // });

                        // Handle employee selection change
                        $('#employee_dropdown').on('change', function() {
                            updateEmployeeInfo();
                        });
                    }

                    // Function to update employee info panel
                    function updateEmployeeInfo() {
                        const employeeId = document.getElementById('employee_dropdown')?.value;

                        if (!employeeId) {
                            document.getElementById('employeeInfoPanel').innerHTML = 
                                '<div class="text-muted text-center py-3"><small>Select an employee to view details</small></div>';
                            return;
                        }

                        // Get employee data from the page
                        const options = document.getElementById('employee_dropdown').options;
                        const selectedOption = Array.from(options).find(opt => opt.value === employeeId);

                        if (selectedOption) {
                            const employeeText = selectedOption.text;
                            const [code, name] = employeeText.split(' - ');

                            // Build employee info display
                            const infoHTML = `
                                <div class="selected-employee-info">
                                    <div class="text-center mb-3">
                                        <i class="bi bi-person-circle" style="font-size: 2.5rem; color: #0d6efd;"></i>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Employee Code</small>
                                        <strong class="d-block" style="color: #0d6efd; font-size: 1.1rem;">${code.trim()}</strong>
                                    </div>
                                    <div class="mb-3">
                                        <small class="text-muted d-block">Employee Name</small>
                                        <strong class="d-block text-dark">${name.trim()}</strong>
                                    </div>
                                    <div class="p-3 rounded" style="background-color: rgba(13, 110, 253, 0.05); border-left: 4px solid #0d6efd;">
                                        <small class="text-muted d-block">Status: <strong class="text-success">✓ Active</strong></small>
                                    </div>
                                </div>
                            `;

                            document.getElementById('employeeInfoPanel').innerHTML = infoHTML;
                        }
                    }

                    // Update on page load if employee is pre-selected
                    if (document.getElementById('employee_dropdown')?.value) {
                        updateEmployeeInfo();
                    }

                    // Prefill form fields from URL parameters (for edit flow)
                    const urlParams = new URLSearchParams(window.location.search);
                    const prefilledEmployeeId = urlParams.get('employee_id');
                    const prefilledDate = urlParams.get('date');

                    if (prefilledEmployeeId) {
                        const employeeSelect = document.getElementById('employee_dropdown');
                        if (employeeSelect) {
                            employeeSelect.value = prefilledEmployeeId;
                            if (typeof $ !== 'undefined' && $(employeeSelect).hasClass('select2')) {
                                $(employeeSelect).trigger('change');
                            }
                            updateEmployeeInfo();
                        }
                    }

                    if (prefilledDate) {
                        const dateInput = document.querySelector('input[name="attendance_date"]');
                        if (dateInput) {
                            dateInput.value = prefilledDate;
                        }
                    }

                    // Prefill form fields from localStorage (backup method)
                    const fields = {
                        employee_id: 'employee_dropdown',
                        date: 'attendance_date',
                        status: 'status',
                        first_in: 'first_in',
                        last_out: 'last_out',
                        remarks: 'remarks'
                    };

                    for (let key in fields) {
                        const value = localStorage.getItem('manual_entry_' + key);
                        if (value && !prefilledEmployeeId) {
                            let el;
                            if (key === 'employee_id') {
                                el = document.getElementById(fields[key]);
                                if (el) {
                                    el.value = value;
                                    if (typeof $ !== 'undefined' && $(el).hasClass('select2')) {
                                        $(el).trigger('change');
                                    }
                                    updateEmployeeInfo();
                                }
                            } else {
                                el = document.querySelector(`[name="${fields[key]}"]`);
                                if (el) el.value = value;
                            }
                            localStorage.removeItem('manual_entry_' + key);
                        }
                    }
                });
            </script>
        @endpush
@endsection