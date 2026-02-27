@extends('layouts.erp')

@section('title', 'Edit Employee Salary')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Edit Salary - {{ $employee->full_name }}</h4>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('hr.employees.salary.update', [$employee, $salary]) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Structure</label><select class="form-select" name="hr_salary_structure_id"><option value="">Select</option>@foreach($structures as $structure)<option value="{{ $structure->id }}" @selected(old('hr_salary_structure_id', $salary->hr_salary_structure_id) == $structure->id)>{{ $structure->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="{{ old('effective_from', $salary->effective_from?->format('Y-m-d')) }}" required></div>
                <div class="col-md-4"><label class="form-label">Basic</label><input type="number" min="0" step="0.01" class="form-control" name="basic" value="{{ old('basic', $salary->monthly_basic) }}" required></div>
                <div class="col-md-4"><label class="form-label">HRA</label><input type="number" min="0" step="0.01" class="form-control" name="hra" value="{{ old('hra', 0) }}"></div>
                <div class="col-md-4"><label class="form-label">DA</label><input type="number" min="0" step="0.01" class="form-control" name="da" value="{{ old('da', 0) }}"></div>
                <div class="col-md-4"><label class="form-label">Special Allowance</label><input type="number" min="0" step="0.01" class="form-control" name="special_allowance" value="{{ old('special_allowance', 0) }}"></div>
                <div class="col-md-4"><label class="form-label">Conveyance</label><input type="number" min="0" step="0.01" class="form-control" name="conveyance" value="{{ old('conveyance', 0) }}"></div>
                <div class="col-md-4"><label class="form-label">Medical</label><input type="number" min="0" step="0.01" class="form-control" name="medical" value="{{ old('medical', 0) }}"></div>
                <div class="col-md-4"><label class="form-label">Other Allowances</label><input type="number" min="0" step="0.01" class="form-control" name="other_allowances" value="{{ old('other_allowances', 0) }}"></div>
                <div class="col-12"><label class="form-label">Revision Reason</label><textarea class="form-control" name="revision_reason" rows="2">{{ old('revision_reason', $salary->remarks) }}</textarea></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Update Salary</button> <a class="btn btn-outline-secondary" href="{{ route('hr.employees.salary.show', $employee) }}">Cancel</a></div>
        </form>
    </div></div>
</div>
@endsection
