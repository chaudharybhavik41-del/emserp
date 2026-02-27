@extends('layouts.erp')

@section('title', 'Employee Experience')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Experience</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Experience' : 'Add Experience' }}</strong></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('hr.employees.experiences.update', [$employee, $editing]) : route('hr.employees.experiences.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2"><label class="form-label">Company</label><input name="company_name" class="form-control form-control-sm" value="{{ old('company_name', $editing->company_name ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Designation</label><input name="designation" class="form-control form-control-sm" value="{{ old('designation', $editing->designation ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Department</label><input name="department" class="form-control form-control-sm" value="{{ old('department', $editing->department ?? '') }}"></div>
                        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">From</label><input type="date" name="from_date" class="form-control form-control-sm" value="{{ old('from_date', isset($editing->from_date) ? $editing->from_date->format('Y-m-d') : '') }}" required></div><div class="col-6"><label class="form-label">To</label><input type="date" name="to_date" class="form-control form-control-sm" value="{{ old('to_date', isset($editing->to_date) ? $editing->to_date->format('Y-m-d') : '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><input name="location" class="form-control form-control-sm" placeholder="Location" value="{{ old('location', $editing->location ?? '') }}"></div><div class="col-6"><input name="reporting_to" class="form-control form-control-sm" placeholder="Reporting To" value="{{ old('reporting_to', $editing->reporting_to ?? '') }}"></div></div>
                        <div class="mb-2"><input type="number" step="0.01" min="0" name="last_ctc" class="form-control form-control-sm" placeholder="Last CTC" value="{{ old('last_ctc', $editing->last_ctc ?? '') }}"></div>
                        <div class="mb-2"><textarea name="job_responsibilities" class="form-control form-control-sm" rows="2" placeholder="Responsibilities">{{ old('job_responsibilities', $editing->job_responsibilities ?? '') }}</textarea></div>
                        <div class="mb-2"><textarea name="reason_for_leaving" class="form-control form-control-sm" rows="2" placeholder="Reason for leaving">{{ old('reason_for_leaving', $editing->reason_for_leaving ?? '') }}</textarea></div>
                        <div class="row g-2 mb-2"><div class="col-4"><input name="reference_name" class="form-control form-control-sm" placeholder="Reference Name" value="{{ old('reference_name', $editing->reference_name ?? '') }}"></div><div class="col-4"><input name="reference_contact" class="form-control form-control-sm" placeholder="Contact" value="{{ old('reference_contact', $editing->reference_contact ?? '') }}"></div><div class="col-4"><input name="reference_email" class="form-control form-control-sm" placeholder="Email" value="{{ old('reference_email', $editing->reference_email ?? '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">Experience Letter</label><input type="file" name="experience_letter" class="form-control form-control-sm"></div><div class="col-6"><label class="form-label">Relieving Letter</label><input type="file" name="relieving_letter" class="form-control form-control-sm"></div></div>
                        <div class="d-flex flex-wrap gap-3 mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_current" value="1" id="exp_current" @checked(old('is_current', $editing->is_current ?? false))><label class="form-check-label" for="exp_current">Current</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="reference_verified" value="1" id="exp_refv" @checked(old('reference_verified', $editing->reference_verified ?? false))><label class="form-check-label" for="exp_refv">Reference Verified</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" value="1" id="exp_verified" @checked(old('is_verified', $editing->is_verified ?? false))><label class="form-check-label" for="exp_verified">Verified</label></div></div>
                        <div class="mb-3"><textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Remarks">{{ old('remarks', $editing->remarks ?? '') }}</textarea></div>
                        <div class="d-flex gap-2"><button class="btn btn-primary btn-sm">{{ $editing ? 'Update' : 'Save' }}</button>@if($editing)<a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.experiences.index', $employee) }}">Cancel</a>@endif</div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Company</th><th>Designation</th><th>Period</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($experiences as $experience)
                                    <tr>
                                        <td>{{ $experience->company_name }}</td>
                                        <td>{{ $experience->designation }}</td>
                                        <td>{{ $experience->from_date?->format('M Y') }} - {{ $experience->is_current ? 'Present' : ($experience->to_date?->format('M Y') ?? '-') }}</td>
                                        <td>@if($experience->is_current)<span class="badge bg-success">Current</span>@endif @if($experience->is_verified)<span class="badge bg-info">Verified</span>@endif</td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.experiences.edit', [$employee, $experience]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.employees.experiences.destroy', [$employee, $experience]) }}" class="d-inline" onsubmit="return confirm('Delete experience record?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No experience records added.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($experiences->hasPages())<div class="card-footer">{{ $experiences->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
