@extends('layouts.erp')

@section('title', 'Candidate Details')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $candidate->full_name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.candidates.index') }}">Candidates</a></li>
                    <li class="breadcrumb-item active">{{ $candidate->candidate_code }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            @if($candidate->resume_path)
                <a href="{{ route('hr.candidates.resume', $candidate) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-download me-1"></i> Resume
                </a>
            @endif
            @can('hr.employee.create')
                @if($candidate->convertedEmployee)
                    <a href="{{ route('hr.employees.show', $candidate->convertedEmployee) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-person-badge me-1"></i> Open Employee
                    </a>
                @else
                    <a href="{{ route('hr.candidates.convert-to-employee', $candidate) }}" class="btn btn-success btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Convert To Employee
                    </a>
                @endif
            @endcan
            @can('hr.candidate.update')
                <a href="{{ route('hr.candidates.edit', $candidate) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </div>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-light">Candidate Summary</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Code</dt>
                        <dd class="col-sm-7">{{ $candidate->candidate_code }}</dd>

                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-light text-dark">{{ $statuses[$candidate->status] ?? ucfirst(str_replace('_', ' ', $candidate->status)) }}</span>
                        </dd>

                        <dt class="col-sm-5">Employee</dt>
                        <dd class="col-sm-7">
                            @if($candidate->convertedEmployee)
                                <a href="{{ route('hr.employees.show', $candidate->convertedEmployee) }}">
                                    {{ $candidate->convertedEmployee->employee_code }}
                                </a>
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-5">Phone</dt>
                        <dd class="col-sm-7">{{ $candidate->phone }}</dd>

                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7">{{ $candidate->email ?: '-' }}</dd>

                        <dt class="col-sm-5">Location</dt>
                        <dd class="col-sm-7">{{ $candidate->current_location ?: '-' }}</dd>

                        <dt class="col-sm-5">Source</dt>
                        <dd class="col-sm-7">{{ $candidate->source ?: '-' }}</dd>

                        <dt class="col-sm-5">Interview Date</dt>
                        <dd class="col-sm-7">{{ $candidate->interview_date?->format('d M Y') ?: '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-light">Professional Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Position Applied</div>
                            <div>{{ $candidate->position_applied ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Current Company</div>
                            <div>{{ $candidate->current_company ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Current Designation</div>
                            <div>{{ $candidate->current_designation ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase">Experience</div>
                            <div>{{ $candidate->experience_label }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted text-uppercase">Notice Period</div>
                            <div>{{ $candidate->notice_period_days !== null ? $candidate->notice_period_days . ' days' : '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted text-uppercase">Current CTC</div>
                            <div>{{ $candidate->current_ctc !== null ? number_format((float) $candidate->current_ctc, 2) : '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted text-uppercase">Expected CTC</div>
                            <div>{{ $candidate->expected_ctc !== null ? number_format((float) $candidate->expected_ctc, 2) : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-light">Skills</div>
                <div class="card-body">
                    {!! nl2br(e($candidate->skills ?: 'No skills added yet.')) !!}
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light">HR Remarks</div>
                <div class="card-body">
                    {!! nl2br(e($candidate->remarks ?: 'No remarks added yet.')) !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
