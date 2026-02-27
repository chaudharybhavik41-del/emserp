@extends('layouts.erp')

@section('title', isset($application) ? 'Edit Leave Application' : 'New Leave Application')

@section('content')
<div class="container-fluid py-3">
    <div class="mb-3">
        <h4 class="mb-1">{{ isset($application) ? 'Edit Leave Application' : 'New Leave Application' }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hr.leave-applications.index') }}">Leave Applications</a></li>
                <li class="breadcrumb-item active">{{ isset($application) ? 'Edit' : 'New' }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" 
                          action="{{ isset($application) ? route('hr.leave-applications.update', $application) : route('hr.leave-applications.store') }}">
                        @csrf
                        @if(isset($application))
                            @method('PUT')
                        @endif

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="hr_employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                                <select class="form-select @error('hr_employee_id') is-invalid @enderror" 
                                        id="hr_employee_id" name="hr_employee_id" required>
                                    <option value="">-- Select Employee --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" 
                                                {{ old('hr_employee_id', $application->hr_employee_id ?? '') == $emp->id ? 'selected' : '' }}>
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
                                <select class="form-select @error('hr_leave_type_id') is-invalid @enderror" 
                                        id="hr_leave_type_id" name="hr_leave_type_id" required>
                                    <option value="">-- Select Leave Type --</option>
                                    @foreach($leaveTypes as $type)
                                        <option value="{{ $type->id }}" 
                                                {{ old('hr_leave_type_id', $application->hr_leave_type_id ?? '') == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                            @if(!$type->is_paid) (Unpaid) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('hr_leave_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="from_date" class="form-label">From Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('from_date') is-invalid @enderror" 
                                       id="from_date" name="from_date" 
                                       value="{{ old('from_date', isset($application) ? $application->from_date->format('Y-m-d') : '') }}" 
                                       required>
                                @error('from_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="from_session" class="form-label">Session <span class="text-danger">*</span></label>
                                <select class="form-select @error('from_session') is-invalid @enderror" 
                                        id="from_session" name="from_session" required>
                                    <option value="full_day" {{ old('from_session', $application->from_session ?? 'full_day') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                    <option value="first_half" {{ old('from_session', $application->from_session ?? '') === 'first_half' ? 'selected' : '' }}>First Half</option>
                                    <option value="second_half" {{ old('from_session', $application->from_session ?? '') === 'second_half' ? 'selected' : '' }}>Second Half</option>
                                </select>
                                @error('from_session')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="to_date" class="form-label">To Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('to_date') is-invalid @enderror" 
                                       id="to_date" name="to_date" 
                                       value="{{ old('to_date', isset($application) ? $application->to_date->format('Y-m-d') : '') }}" 
                                       required>
                                @error('to_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label for="to_session" class="form-label">Session <span class="text-danger">*</span></label>
                                <select class="form-select @error('to_session') is-invalid @enderror" 
                                        id="to_session" name="to_session" required>
                                    <option value="full_day" {{ old('to_session', $application->to_session ?? 'full_day') === 'full_day' ? 'selected' : '' }}>Full Day</option>
                                    <option value="first_half" {{ old('to_session', $application->to_session ?? '') === 'first_half' ? 'selected' : '' }}>First Half</option>
                                    <option value="second_half" {{ old('to_session', $application->to_session ?? '') === 'second_half' ? 'selected' : '' }}>Second Half</option>
                                </select>
                                @error('to_session')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" 
                                      id="reason" name="reason" 
                                      rows="3" maxlength="1000" required>{{ old('reason', $application->reason ?? '') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="contact_during_leave" class="form-label">Contact During Leave</label>
                                <input type="text" class="form-control @error('contact_during_leave') is-invalid @enderror" 
                                       id="contact_during_leave" name="contact_during_leave" 
                                       value="{{ old('contact_during_leave', $application->contact_during_leave ?? '') }}" 
                                       maxlength="20" placeholder="Phone number">
                                @error('contact_during_leave')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="handover_to" class="form-label">Handover To</label>
                                <select class="form-select @error('handover_to') is-invalid @enderror" 
                                        id="handover_to" name="handover_to">
                                    <option value="">-- Select Employee --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" 
                                                {{ old('handover_to', $application->handover_to ?? '') == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->employee_code }} - {{ $emp->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('handover_to')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                {{ isset($application) ? 'Update' : 'Submit' }}
                            </button>
                            <a href="{{ route('hr.leave-applications.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Info --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Leave Application Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="small text-muted mb-0">
                        <li class="mb-2">Select the appropriate leave type based on your reason.</li>
                        <li class="mb-2">For half-day leaves, select "First Half" or "Second Half" in the session dropdown.</li>
                        <li class="mb-2">Provide a clear and valid reason for your leave request.</li>
                        <li class="mb-2">Emergency contact is recommended for leaves longer than 3 days.</li>
                        <li class="mb-2">Specify a handover person for critical responsibilities.</li>
                        <li>Supporting documents may be required for medical or extended leaves.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('from_date').addEventListener('change', function() {
    const toDate = document.getElementById('to_date');
    if (!toDate.value || toDate.value < this.value) {
        toDate.value = this.value;
    }
    toDate.min = this.value;
});
</script>
@endpush
@endsection
