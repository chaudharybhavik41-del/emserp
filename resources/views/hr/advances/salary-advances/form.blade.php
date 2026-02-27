@extends('layouts.erp')

@section('title', isset($advance) ? 'Edit Salary Advance' : 'Apply Salary Advance')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">{{ isset($advance) ? 'Edit Salary Advance' : 'Apply Salary Advance' }}</h4>
    @include('partials.flash')
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ isset($advance) ? route('hr.advances.salary-advances.update', $advance) : route('hr.advances.salary-advances.store') }}">
            @csrf
            @if(isset($advance)) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Employee</label><select name="hr_employee_id" class="form-select" required><option value="">Select employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('hr_employee_id', $advance->hr_employee_id ?? '') == $employee->id)>{{ $employee->employee_code }} - {{ $employee->full_name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Application Date</label><input type="date" name="application_date" class="form-control" value="{{ old('application_date', isset($advance->application_date) ? $advance->application_date->format('Y-m-d') : now()->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label">Recovery Months</label><input type="number" min="1" max="60" name="recovery_months" class="form-control" value="{{ old('recovery_months', $advance->recovery_months ?? 1) }}" required></div>
                <div class="col-md-4"><label class="form-label">Requested Amount</label><input type="number" min="0" step="0.01" name="requested_amount" class="form-control" value="{{ old('requested_amount', $advance->requested_amount ?? '') }}" required></div>
                <div class="col-md-8"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="2" required>{{ old('purpose', $advance->purpose ?? '') }}</textarea></div>
            </div>
            <div class="mt-3 d-flex gap-2"><button class="btn btn-primary">{{ isset($advance) ? 'Update' : 'Create' }}</button><a class="btn btn-outline-secondary" href="{{ route('hr.advances.salary-advances.index') }}">Cancel</a></div>
        </form>
    </div></div>
</div>
@endsection
