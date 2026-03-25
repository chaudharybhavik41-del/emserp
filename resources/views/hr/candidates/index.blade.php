@extends('layouts.erp')

@section('title', 'Candidates')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Candidate Database</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('hr.dashboard') }}">HR</a></li>
                    <li class="breadcrumb-item active">Candidates</li>
                </ol>
            </nav>
        </div>
        @can('hr.candidate.create')
            <a href="{{ route('hr.candidates.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add Candidate
            </a>
        @endcan
    </div>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Candidates</div>
                    <div class="fs-4 fw-semibold">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">New Profiles</div>
                    <div class="fs-4 fw-semibold">{{ $stats['new'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Shortlisted</div>
                    <div class="fs-4 fw-semibold">{{ $stats['shortlisted'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Interviewed</div>
                    <div class="fs-4 fw-semibold">{{ $stats['interviewed'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Search by name, code, email, phone, company..."
                           value="{{ request('q') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="source" class="form-select form-select-sm">
                        <option value="">All Sources</option>
                        @foreach($sources as $source)
                            <option value="{{ $source }}" {{ request('source') === $source ? 'selected' : '' }}>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-md-end">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <a href="{{ route('hr.candidates.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Contact</th>
                            <th>Applied For</th>
                            <th>Experience</th>
                            <th>CTC</th>
                            <th>Status</th>
                            <th>Resume</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidates as $candidate)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $candidate->full_name }}</div>
                                    <div class="small text-muted">{{ $candidate->candidate_code }}</div>
                                    @if($candidate->current_company)
                                        <div class="small text-muted">{{ $candidate->current_company }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $candidate->phone }}</div>
                                    @if($candidate->email)
                                        <div class="small text-muted">{{ $candidate->email }}</div>
                                    @endif
                                    @if($candidate->current_location)
                                        <div class="small text-muted">{{ $candidate->current_location }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $candidate->position_applied ?: '-' }}</div>
                                    @if($candidate->current_designation)
                                        <div class="small text-muted">{{ $candidate->current_designation }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $candidate->experience_label }}</div>
                                    @if($candidate->notice_period_days !== null)
                                        <div class="small text-muted">Notice: {{ $candidate->notice_period_days }} days</div>
                                    @endif
                                </td>
                                <td>
                                    <div>Current: {{ $candidate->current_ctc !== null ? number_format((float) $candidate->current_ctc, 2) : '-' }}</div>
                                    <div class="small text-muted">Expected: {{ $candidate->expected_ctc !== null ? number_format((float) $candidate->expected_ctc, 2) : '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $statuses[$candidate->status] ?? ucfirst(str_replace('_', ' ', $candidate->status)) }}</span>
                                    @if($candidate->source)
                                        <div class="small text-muted mt-1">{{ $candidate->source }}</div>
                                    @endif
                                    @if($candidate->convertedEmployee)
                                        <div class="small text-success mt-1">Employee: {{ $candidate->convertedEmployee->employee_code }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($candidate->resume_path)
                                        <a href="{{ route('hr.candidates.resume', $candidate) }}" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-download me-1"></i> Resume
                                        </a>
                                    @else
                                        <span class="text-muted small">Not uploaded</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('hr.candidates.show', $candidate) }}" class="btn btn-sm btn-outline-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @can('hr.employee.create')
                                        @if($candidate->convertedEmployee)
                                            <a href="{{ route('hr.employees.show', $candidate->convertedEmployee) }}" class="btn btn-sm btn-outline-success" title="Open Employee">
                                                <i class="bi bi-person-badge"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('hr.candidates.convert-to-employee', $candidate) }}" class="btn btn-sm btn-outline-success" title="Convert To Employee">
                                                <i class="bi bi-person-plus"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @can('hr.candidate.update')
                                        <a href="{{ route('hr.candidates.edit', $candidate) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No candidates found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($candidates->hasPages())
            <div class="card-footer">
                {{ $candidates->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
