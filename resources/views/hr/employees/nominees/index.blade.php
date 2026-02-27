@extends('layouts.erp')

@section('title', 'Employee Nominees')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Nominees</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Nominee' : 'Add Nominee' }}</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ $editing ? route('hr.employees.nominees.update', [$employee, $editing]) : route('hr.employees.nominees.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2">
                            <label class="form-label">Nomination For</label>
                            <select name="nomination_for" class="form-select form-select-sm" required>
                                @foreach(['pf' => 'PF', 'gratuity' => 'Gratuity', 'insurance' => 'Insurance', 'superannuation' => 'Superannuation', 'other' => 'Other'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('nomination_for', $editing->nomination_for ?? '') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control form-control-sm" value="{{ old('name', $editing->name ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Relationship</label><input name="relationship" class="form-control form-control-sm" value="{{ old('relationship', $editing->relationship ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">DOB</label><input type="date" name="date_of_birth" class="form-control form-control-sm" value="{{ old('date_of_birth', isset($editing->date_of_birth) ? $editing->date_of_birth->format('Y-m-d') : '') }}"></div>
                        <div class="mb-2"><label class="form-label">Share %</label><input type="number" min="0" max="100" step="0.01" name="share_percentage" class="form-control form-control-sm" value="{{ old('share_percentage', $editing->share_percentage ?? 100) }}" required></div>
                        <div class="mb-2"><label class="form-label">Effective From</label><input type="date" name="effective_from" class="form-control form-control-sm" value="{{ old('effective_from', isset($editing->effective_from) ? $editing->effective_from->format('Y-m-d') : now()->toDateString()) }}" required></div>
                        <div class="mb-2"><label class="form-label">Address</label><textarea name="address" rows="2" class="form-control form-control-sm">{{ old('address', $editing->address ?? '') }}</textarea></div>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><input name="guardian_name" class="form-control form-control-sm" placeholder="Guardian Name" value="{{ old('guardian_name', $editing->guardian_name ?? '') }}"></div>
                            <div class="col-6"><input name="guardian_relationship" class="form-control form-control-sm" placeholder="Guardian Relationship" value="{{ old('guardian_relationship', $editing->guardian_relationship ?? '') }}"></div>
                        </div>
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_minor" value="1" id="nom_minor" @checked(old('is_minor', $editing->is_minor ?? false))><label class="form-check-label" for="nom_minor">Minor</label></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="nom_active" @checked(old('is_active', $editing->is_active ?? true))><label class="form-check-label" for="nom_active">Active</label></div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm">{{ $editing ? 'Update' : 'Save' }}</button>
                            @if($editing)<a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.nominees.index', $employee) }}">Cancel</a>@endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Name</th><th>For</th><th>Share</th><th>Effective</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($nominees as $nominee)
                                    <tr>
                                        <td>{{ $nominee->name }}</td>
                                        <td>{{ strtoupper($nominee->nomination_for) }}</td>
                                        <td>{{ number_format((float) $nominee->share_percentage, 2) }}%</td>
                                        <td>{{ $nominee->effective_from?->format('d M Y') }}</td>
                                        <td>{!! $nominee->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.nominees.edit', [$employee, $nominee]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.employees.nominees.destroy', [$employee, $nominee]) }}" class="d-inline" onsubmit="return confirm('Delete nominee?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No nominees added.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($nominees->hasPages())<div class="card-footer">{{ $nominees->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
