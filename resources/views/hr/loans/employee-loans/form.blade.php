@extends('layouts.erp')

@section('title', isset($loan) ? 'Edit Loan' : 'Apply Loan')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">{{ isset($loan) ? 'Edit Loan Application' : 'New Loan Application' }}</h4>
    @include('partials.flash')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ isset($loan) ? route('hr.loans.employee-loans.update', $loan) : route('hr.loans.employee-loans.store') }}">
                @csrf
                @if(isset($loan)) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee</label>
                        <select name="hr_employee_id" class="form-select" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('hr_employee_id', $loan->hr_employee_id ?? '') == $employee->id)>{{ $employee->employee_code }} - {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Loan Type</label>
                        <select name="hr_loan_type_id" class="form-select" required>
                            <option value="">Select loan type</option>
                            @foreach($loanTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('hr_loan_type_id', $loan->hr_loan_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Application Date</label><input type="date" name="application_date" class="form-control" value="{{ old('application_date', isset($loan->application_date) ? $loan->application_date->format('Y-m-d') : now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Applied Amount</label><input type="number" step="0.01" min="0" name="applied_amount" class="form-control" value="{{ old('applied_amount', $loan->applied_amount ?? '') }}" required></div>
                    <div class="col-md-4"><label class="form-label">Tenure (Months)</label><input type="number" min="1" max="240" name="tenure_months" class="form-control" value="{{ old('tenure_months', $loan->tenure_months ?? 12) }}" required></div>
                    <div class="col-md-4"><label class="form-label">Interest Rate %</label><input type="number" step="0.01" min="0" max="60" name="interest_rate" class="form-control" value="{{ old('interest_rate', $loan->interest_rate ?? '') }}"></div>
                    <div class="col-md-8"><label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="2">{{ old('purpose', $loan->purpose ?? '') }}</textarea></div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary">{{ isset($loan) ? 'Update Loan' : 'Create Loan' }}</button>
                    <a href="{{ route('hr.loans.employee-loans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
