@extends('layouts.erp')

@section('title', 'New Leave Application')

@section('content')
<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">New Leave Application</h1>
            <small class="text-muted">Create a leave request for an employee</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('hr.leave.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="hr_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                <select id="hr_employee_id" name="hr_employee_id"
                                        class="form-select @error('hr_employee_id') is-invalid @enderror" required>
                                    <option value="">-- Select Employee --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}"
                                            {{ (string)old('hr_employee_id', $selectedEmployee?->id) === (string)$emp->id ? 'selected' : '' }}>
                                            {{ $emp->employee_code }} - {{ $emp->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('hr_employee_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="hr_leave_type_id" class="form-label">Leave Type <span class="text-danger">*</span></label>
                                <select id="hr_leave_type_id" name="hr_leave_type_id"
                                        class="form-select @error('hr_leave_type_id') is-invalid @enderror" required>
                                    <option value="">-- Select Leave Type --</option>
                                    @foreach($leaveTypes as $type)
                                        <option value="{{ $type->id }}" {{ (string)old('hr_leave_type_id') === (string)$type->id ? 'selected' : '' }}>
                                            {{ $type->name }}@if(!$type->is_paid) (Unpaid) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('hr_leave_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="from_date" class="form-label">From Date <span class="text-danger">*</span></label>
                                <input type="date" id="from_date" name="from_date"
                                       class="form-control @error('from_date') is-invalid @enderror"
                                       value="{{ old('from_date') }}" required>
                                @error('from_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="from_session" class="form-label">From Session <span class="text-danger">*</span></label>
                                <select id="from_session" name="from_session"
                                        class="form-select @error('from_session') is-invalid @enderror" required>
                                    <option value="full_day" {{ old('from_session', 'full_day') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                    <option value="first_half" {{ old('from_session') === 'first_half' ? 'selected' : '' }}>First Half</option>
                                    <option value="second_half" {{ old('from_session') === 'second_half' ? 'selected' : '' }}>Second Half</option>
                                </select>
                                @error('from_session')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="to_date" class="form-label">To Date <span class="text-danger">*</span></label>
                                <input type="date" id="to_date" name="to_date"
                                       class="form-control @error('to_date') is-invalid @enderror"
                                       value="{{ old('to_date') }}" required>
                                @error('to_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label for="to_session" class="form-label">To Session <span class="text-danger">*</span></label>
                                <select id="to_session" name="to_session"
                                        class="form-select @error('to_session') is-invalid @enderror" required>
                                    <option value="full_day" {{ old('to_session', 'full_day') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                    <option value="first_half" {{ old('to_session') === 'first_half' ? 'selected' : '' }}>First Half</option>
                                    <option value="second_half" {{ old('to_session') === 'second_half' ? 'selected' : '' }}>Second Half</option>
                                </select>
                                @error('to_session')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                <textarea id="reason" name="reason" rows="4" maxlength="1000"
                                          class="form-control @error('reason') is-invalid @enderror"
                                          placeholder="Type reason..." required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Max 1000 characters.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="contact_during_leave" class="form-label">Contact During Leave</label>
                                <input type="text" id="contact_during_leave" name="contact_during_leave" maxlength="50"
                                       class="form-control @error('contact_during_leave') is-invalid @enderror"
                                       value="{{ old('contact_during_leave') }}"
                                       placeholder="Phone number / alternate contact">
                                @error('contact_during_leave')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="document" class="form-label">Attachment (optional)</label>
                                <input type="file" id="document" name="document"
                                       class="form-control @error('document') is-invalid @enderror"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                @error('document')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Allowed: PDF/JPG/PNG. Max 2MB.</div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Submit
                            </button>
                            <a href="{{ route('hr.leave.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Notes</h6>
                </div>
                <div class="card-body">
                    <ul class="small text-muted mb-0">
                        <li class="mb-2">Half-day leaves are controlled by the <em>From Session</em> and <em>To Session</em> fields.</li>
                        <li class="mb-2">Leave days are calculated server-side when you submit.</li>
                        <li class="mb-2">Attach medical certificate for sick/medical leaves if required.</li>
                        <li class="mb-0">If balance is insufficient and negative balance is not allowed, submission will be rejected.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const fromDate = document.getElementById('from_date');
        const toDate = document.getElementById('to_date');

        if (!fromDate || !toDate) return;

        function syncToDateMin() {
            if (!fromDate.value) return;
            toDate.min = fromDate.value;
            if (!toDate.value || toDate.value < fromDate.value) {
                toDate.value = fromDate.value;
            }
        }

        fromDate.addEventListener('change', syncToDateMin);
        // Initialize on load
        syncToDateMin();
    })();
</script>
@endpush
@endsection
