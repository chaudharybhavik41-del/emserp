@extends('layouts.erp')

@section('title', 'Employee Dependents')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Dependents</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Dependent' : 'Add Dependent' }}</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ $editing ? route('hr.employees.dependents.update', [$employee, $editing]) : route('hr.employees.dependents.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $editing->name ?? '') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Relationship</label>
                            <input type="text" name="relationship" class="form-control form-control-sm" value="{{ old('relationship', $editing->relationship ?? '') }}" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">DOB</label>
                                <input type="date" name="date_of_birth" class="form-control form-control-sm" value="{{ old('date_of_birth', isset($editing->date_of_birth) ? $editing->date_of_birth->format('Y-m-d') : '') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select form-select-sm">
                                    <option value="">Select</option>
                                    @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $key => $label)
                                        <option value="{{ $key }}" @selected(old('gender', $editing->gender ?? '') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $editing->phone ?? '') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nomination %</label>
                                <input type="number" min="0" max="100" step="0.01" name="nomination_percentage" class="form-control form-control-sm" value="{{ old('nomination_percentage', $editing->nomination_percentage ?? '') }}">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Occupation</label>
                            <input type="text" name="occupation" class="form-control form-control-sm" value="{{ old('occupation', $editing->occupation ?? '') }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Address</label>
                            <textarea name="address" rows="2" class="form-control form-control-sm">{{ old('address', $editing->address ?? '') }}</textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_dependent_for_insurance" value="1" id="dep_ins" @checked(old('is_dependent_for_insurance', $editing->is_dependent_for_insurance ?? false))>
                                <label class="form-check-label" for="dep_ins">Insurance</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_emergency_contact" value="1" id="dep_emg" @checked(old('is_emergency_contact', $editing->is_emergency_contact ?? false))>
                                <label class="form-check-label" for="dep_emg">Emergency Contact</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_nominee" value="1" id="dep_nom" @checked(old('is_nominee', $editing->is_nominee ?? false))>
                                <label class="form-check-label" for="dep_nom">Nominee</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_disabled" value="1" id="dep_dis" @checked(old('is_disabled', $editing->is_disabled ?? false))>
                                <label class="form-check-label" for="dep_dis">Disabled</label>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm" type="submit">{{ $editing ? 'Update' : 'Save' }}</button>
                            @if($editing)
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.dependents.index', $employee) }}">Cancel</a>
                            @endif
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
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Relationship</th>
                                    <th>DOB</th>
                                    <th>Flags</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dependents as $dependent)
                                    <tr>
                                        <td>{{ $dependent->name }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $dependent->relationship)) }}</td>
                                        <td>{{ $dependent->date_of_birth?->format('d M Y') ?? '-' }}</td>
                                        <td>
                                            @if($dependent->is_emergency_contact)<span class="badge bg-warning text-dark">Emergency</span>@endif
                                            @if($dependent->is_dependent_for_insurance)<span class="badge bg-info">Insurance</span>@endif
                                            @if($dependent->is_nominee)<span class="badge bg-primary">Nominee</span>@endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.dependents.edit', [$employee, $dependent]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.employees.dependents.destroy', [$employee, $dependent]) }}" class="d-inline" onsubmit="return confirm('Delete dependent?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No dependents added.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($dependents->hasPages())
                    <div class="card-footer">{{ $dependents->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
