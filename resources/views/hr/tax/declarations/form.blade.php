@extends('layouts.erp')

@section('title', isset($declaration) ? 'Edit Tax Declaration' : 'Create Tax Declaration')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">{{ isset($declaration) ? 'Edit Tax Declaration' : 'Create Tax Declaration' }}</h4>
    @include('partials.flash')

    <form method="POST" action="{{ isset($declaration) ? route('hr.tax.declarations.update', $declaration) : route('hr.tax.declarations.store') }}">
        @csrf
        @if(isset($declaration)) @method('PUT') @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Employee</label>
                        <select name="hr_employee_id" class="form-select" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('hr_employee_id', $declaration->hr_employee_id ?? '') == $employee->id)>{{ $employee->employee_code }} - {{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Financial Year</label>
                        <input type="text" name="financial_year" class="form-control" value="{{ old('financial_year', $declaration->financial_year ?? '') }}" placeholder="2025-26" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Regime</label>
                        <select name="tax_regime" class="form-select" required>
                            <option value="new" @selected(old('tax_regime', $declaration->tax_regime ?? 'new') === 'new')>New</option>
                            <option value="old" @selected(old('tax_regime', $declaration->tax_regime ?? '') === 'old')>Old</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        @php
            $details = old('details', isset($declaration) ? $declaration->details->map(fn($d) => [
                'section_code' => $d->section_code,
                'section_name' => $d->section_name,
                'investment_type' => $d->investment_type,
                'description' => $d->description,
                'declared_amount' => $d->declared_amount,
                'max_limit' => $d->max_limit,
            ])->toArray() : [['section_code' => '', 'section_name' => '', 'investment_type' => '', 'description' => '', 'declared_amount' => '', 'max_limit' => '']]);
        @endphp

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Investments / Exemptions</strong>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addRow">Add Row</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="detailTable">
                        <thead class="table-light"><tr><th>Section</th><th>Name</th><th>Investment</th><th>Description</th><th>Declared</th><th>Limit</th><th></th></tr></thead>
                        <tbody>
                            @foreach($details as $i => $detail)
                                <tr>
                                    <td><input name="details[{{ $i }}][section_code]" class="form-control form-control-sm" value="{{ $detail['section_code'] ?? '' }}"></td>
                                    <td><input name="details[{{ $i }}][section_name]" class="form-control form-control-sm" value="{{ $detail['section_name'] ?? '' }}"></td>
                                    <td><input name="details[{{ $i }}][investment_type]" class="form-control form-control-sm" value="{{ $detail['investment_type'] ?? '' }}"></td>
                                    <td><input name="details[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $detail['description'] ?? '' }}"></td>
                                    <td><input type="number" min="0" step="0.01" name="details[{{ $i }}][declared_amount]" class="form-control form-control-sm" value="{{ $detail['declared_amount'] ?? '' }}"></td>
                                    <td><input type="number" min="0" step="0.01" name="details[{{ $i }}][max_limit]" class="form-control form-control-sm" value="{{ $detail['max_limit'] ?? '' }}"></td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm removeRow">X</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary">{{ isset($declaration) ? 'Update Declaration' : 'Create Declaration' }}</button>
            <a href="{{ route('hr.tax.declarations.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.querySelector('#detailTable tbody');
    const addBtn = document.getElementById('addRow');

    addBtn.addEventListener('click', function () {
        const idx = tbody.querySelectorAll('tr').length;
        tbody.insertAdjacentHTML('beforeend', `
            <tr>
                <td><input name="details[${idx}][section_code]" class="form-control form-control-sm"></td>
                <td><input name="details[${idx}][section_name]" class="form-control form-control-sm"></td>
                <td><input name="details[${idx}][investment_type]" class="form-control form-control-sm"></td>
                <td><input name="details[${idx}][description]" class="form-control form-control-sm"></td>
                <td><input type="number" min="0" step="0.01" name="details[${idx}][declared_amount]" class="form-control form-control-sm"></td>
                <td><input type="number" min="0" step="0.01" name="details[${idx}][max_limit]" class="form-control form-control-sm"></td>
                <td><button type="button" class="btn btn-outline-danger btn-sm removeRow">X</button></td>
            </tr>
        `);
    });

    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('removeRow')) {
            e.target.closest('tr').remove();
        }
    });
});
</script>
@endpush
@endsection
