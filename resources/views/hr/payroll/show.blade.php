@extends('layouts.erp')

@section('title', 'Payroll Detail')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Payroll {{ $payroll->payroll_number }}</h4>
        <a href="{{ route('hr.payroll.period', $payroll->period) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Gross</small><h5>₹{{ number_format($payroll->gross_salary, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Deductions</small><h5>₹{{ number_format($payroll->total_deductions, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Net Pay</small><h5>₹{{ number_format($payroll->net_payable, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Status</small><h5>{{ ucfirst(optional($payroll->status)->value ?? $payroll->status) }}</h5></div></div></div>
    </div>

    <div class="card"><div class="card-header"><strong>Component Breakdown</strong></div><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Component</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                @forelse($payroll->components as $component)
                    <tr><td>{{ $component->component_name }}</td><td>{{ ucfirst(str_replace('_', ' ', $component->component_type)) }}</td><td class="text-end">₹{{ number_format($component->final_amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No components.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
