@extends('layouts.erp')

@section('title', 'Employee Qualifications')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Qualifications</h4>
            <p class="text-muted mb-0">{{ $employee->employee_code }} - {{ $employee->full_name }}</p>
        </div>
        <a href="{{ route('hr.employees.show', $employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
    </div>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong>{{ $editing ? 'Edit Qualification' : 'Add Qualification' }}</strong></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('hr.employees.qualifications.update', [$employee, $editing]) : route('hr.employees.qualifications.store', $employee) }}">
                        @csrf
                        @if($editing) @method('PUT') @endif
                        <div class="mb-2"><label class="form-label">Type</label>
                            <select name="qualification_type" class="form-select form-select-sm" required>
                                @foreach(['below_10th' => 'Below 10th', '10th' => '10th', '12th' => '12th', 'diploma' => 'Diploma', 'iti' => 'ITI', 'graduation' => 'Graduation', 'post_graduation' => 'Post Graduation', 'doctorate' => 'Doctorate', 'professional' => 'Professional', 'other' => 'Other'] as $k => $v)
                                    <option value="{{ $k }}" @selected(old('qualification_type', $editing->qualification_type ?? '') === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2"><label class="form-label">Degree/Certificate</label><input name="degree_name" class="form-control form-control-sm" value="{{ old('degree_name', $editing->degree_name ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">Specialization</label><input name="specialization" class="form-control form-control-sm" value="{{ old('specialization', $editing->specialization ?? '') }}"></div>
                        <div class="mb-2"><label class="form-label">Institution</label><input name="institution_name" class="form-control form-control-sm" value="{{ old('institution_name', $editing->institution_name ?? '') }}" required></div>
                        <div class="mb-2"><label class="form-label">University/Board</label><input name="university_board" class="form-control form-control-sm" value="{{ old('university_board', $editing->university_board ?? '') }}"></div>
                        <div class="row g-2 mb-2"><div class="col-6"><input type="number" name="year_of_passing" class="form-control form-control-sm" placeholder="Year" value="{{ old('year_of_passing', $editing->year_of_passing ?? '') }}"></div><div class="col-6"><input type="number" step="0.01" min="0" max="100" name="percentage_cgpa" class="form-control form-control-sm" placeholder="%/CGPA" value="{{ old('percentage_cgpa', $editing->percentage_cgpa ?? '') }}"></div></div>
                        <div class="row g-2 mb-2"><div class="col-6"><select name="grade_type" class="form-select form-select-sm"><option value="percentage" @selected(old('grade_type', $editing->grade_type ?? 'percentage') === 'percentage')>Percentage</option><option value="cgpa" @selected(old('grade_type', $editing->grade_type ?? '') === 'cgpa')>CGPA</option><option value="grade" @selected(old('grade_type', $editing->grade_type ?? '') === 'grade')>Grade</option></select></div><div class="col-6"><input name="roll_number" class="form-control form-control-sm" placeholder="Roll Number" value="{{ old('roll_number', $editing->roll_number ?? '') }}"></div></div>
                        <div class="mb-2"><label class="form-label">Certificate</label><input type="file" name="certificate" class="form-control form-control-sm"></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_verified" value="1" id="qual_verified" @checked(old('is_verified', $editing->is_verified ?? false))><label class="form-check-label" for="qual_verified">Verified</label></div>
                        <div class="mb-3"><textarea name="remarks" rows="2" class="form-control form-control-sm" placeholder="Remarks">{{ old('remarks', $editing->remarks ?? '') }}</textarea></div>
                        <div class="d-flex gap-2"><button class="btn btn-primary btn-sm">{{ $editing ? 'Update' : 'Save' }}</button>@if($editing)<a class="btn btn-outline-secondary btn-sm" href="{{ route('hr.employees.qualifications.index', $employee) }}">Cancel</a>@endif</div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Qualification</th><th>Institution</th><th>Year</th><th>Score</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @forelse($qualifications as $qualification)
                                    <tr>
                                        <td>{{ $qualification->degree_name }} @if($qualification->is_verified)<span class="badge bg-success">Verified</span>@endif</td>
                                        <td>{{ $qualification->institution_name }}</td>
                                        <td>{{ $qualification->year_of_passing ?? '-' }}</td>
                                        <td>{{ $qualification->percentage_cgpa ?? '-' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('hr.employees.qualifications.edit', [$employee, $qualification]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('hr.employees.qualifications.destroy', [$employee, $qualification]) }}" class="d-inline" onsubmit="return confirm('Delete qualification?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No qualifications added.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($qualifications->hasPages())<div class="card-footer">{{ $qualifications->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
