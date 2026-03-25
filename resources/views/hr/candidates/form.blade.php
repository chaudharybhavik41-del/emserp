@extends('layouts.erp')

@section('title', $candidate->exists ? 'Edit Candidate' : 'Add Candidate')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $candidate->exists ? 'Edit Candidate' : 'Add Candidate' }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.candidates.index') }}">Candidates</a></li>
                    <li class="breadcrumb-item active">{{ $candidate->exists ? $candidate->candidate_code : 'New' }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.candidates.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @include('partials.flash')

    <form method="POST"
          action="{{ $candidate->exists ? route('hr.candidates.update', $candidate) : route('hr.candidates.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($candidate->exists)
            @method('PUT')
        @endif

        <div class="card mb-3">
            <div class="card-header bg-light">Candidate Profile</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Candidate Code</label>
                        <input type="text" class="form-control" value="{{ $candidate->candidate_code }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name', $candidate->first_name) }}" required>
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $candidate->last_name) }}" required>
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $candidate->status ?: 'new') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $candidate->email) }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $candidate->phone) }}" required>
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="alternate_phone" class="form-label">Alternate Phone</label>
                        <input type="text" id="alternate_phone" name="alternate_phone" class="form-control @error('alternate_phone') is-invalid @enderror"
                               value="{{ old('alternate_phone', $candidate->alternate_phone) }}">
                        @error('alternate_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="current_location" class="form-label">Current Location</label>
                        <input type="text" id="current_location" name="current_location" class="form-control @error('current_location') is-invalid @enderror"
                               value="{{ old('current_location', $candidate->current_location) }}">
                        @error('current_location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="source" class="form-label">Source</label>
                        <input type="text" id="source" name="source" class="form-control @error('source') is-invalid @enderror"
                               value="{{ old('source', $candidate->source) }}" placeholder="Referral, Naukri, LinkedIn...">
                        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="interview_date" class="form-label">Interview Date</label>
                        <input type="date" id="interview_date" name="interview_date" class="form-control @error('interview_date') is-invalid @enderror"
                               value="{{ old('interview_date', optional($candidate->interview_date)->format('Y-m-d')) }}">
                        @error('interview_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-light">Professional Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="position_applied" class="form-label">Position Applied</label>
                        <input type="text" id="position_applied" name="position_applied" class="form-control @error('position_applied') is-invalid @enderror"
                               value="{{ old('position_applied', $candidate->position_applied) }}">
                        @error('position_applied')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="current_company" class="form-label">Current Company</label>
                        <input type="text" id="current_company" name="current_company" class="form-control @error('current_company') is-invalid @enderror"
                               value="{{ old('current_company', $candidate->current_company) }}">
                        @error('current_company')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="current_designation" class="form-label">Current Designation</label>
                        <input type="text" id="current_designation" name="current_designation" class="form-control @error('current_designation') is-invalid @enderror"
                               value="{{ old('current_designation', $candidate->current_designation) }}">
                        @error('current_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="total_experience_months" class="form-label">Total Experience (Months)</label>
                        <input type="number" id="total_experience_months" name="total_experience_months" min="0"
                               class="form-control @error('total_experience_months') is-invalid @enderror"
                               value="{{ old('total_experience_months', $candidate->total_experience_months) }}">
                        @error('total_experience_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="notice_period_days" class="form-label">Notice Period (Days)</label>
                        <input type="number" id="notice_period_days" name="notice_period_days" min="0"
                               class="form-control @error('notice_period_days') is-invalid @enderror"
                               value="{{ old('notice_period_days', $candidate->notice_period_days) }}">
                        @error('notice_period_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="current_ctc" class="form-label">Current CTC</label>
                        <input type="number" step="0.01" id="current_ctc" name="current_ctc" min="0"
                               class="form-control @error('current_ctc') is-invalid @enderror"
                               value="{{ old('current_ctc', $candidate->current_ctc) }}">
                        @error('current_ctc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="expected_ctc" class="form-label">Expected CTC</label>
                        <input type="number" step="0.01" id="expected_ctc" name="expected_ctc" min="0"
                               class="form-control @error('expected_ctc') is-invalid @enderror"
                               value="{{ old('expected_ctc', $candidate->expected_ctc) }}">
                        @error('expected_ctc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="skills" class="form-label">Skills</label>
                        <textarea id="skills" name="skills" rows="4"
                                  class="form-control @error('skills') is-invalid @enderror"
                                  placeholder="Mention primary skills, tools, certifications...">{{ old('skills', $candidate->skills) }}</textarea>
                        @error('skills')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="remarks" class="form-label">Interview / HR Remarks</label>
                        <textarea id="remarks" name="remarks" rows="4"
                                  class="form-control @error('remarks') is-invalid @enderror"
                                  placeholder="Assessment notes, follow-up items, interview feedback...">{{ old('remarks', $candidate->remarks) }}</textarea>
                        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="resume" class="form-label">Resume</label>
                        <input type="file" id="resume" name="resume" class="form-control @error('resume') is-invalid @enderror"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        @error('resume')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($candidate->resume_path)
                            <div class="form-text">
                                Current file:
                                <a href="{{ route('hr.candidates.resume', $candidate) }}">{{ $candidate->resume_file_name ?: 'Download resume' }}</a>
                            </div>
                        @else
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG. Max size 8 MB.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('hr.candidates.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">{{ $candidate->exists ? 'Update Candidate' : 'Save Candidate' }}</button>
        </div>
    </form>
</div>
@endsection
