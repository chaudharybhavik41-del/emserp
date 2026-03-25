@extends('layouts.erp')

@section('title', 'Payroll Detail')

@section('content')
<div class="container-fluid py-3">
    @if($payroll->employee)
        @include('hr.employees.partials.hub-nav', ['employee' => $payroll->employee, 'activeSection' => 'payroll'])
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Payroll {{ $payroll->payroll_number }}</h4>
        @if($payroll->employee)
            <a href="{{ route('hr.employees.payroll', $payroll->employee) }}" class="btn btn-outline-secondary btn-sm">Back to Employee</a>
        @else
            <a href="{{ route('hr.payroll.period', $payroll->period) }}" class="btn btn-outline-secondary btn-sm">Back</a>
        @endif
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Gross</small><h5>₹{{ number_format($payroll->gross_salary, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Deductions</small><h5>₹{{ number_format($payroll->total_deductions, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Round Off</small><h5>₹{{ number_format($payroll->round_off, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Net Pay</small><h5>₹{{ number_format($payroll->net_payable, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Status</small><h5>{{ ucfirst(optional($payroll->status)->value ?? $payroll->status) }}</h5></div></div></div>
    </div>

    @canany(['hr.payroll.approve', 'hr.payroll.pay'])
    <div class="row g-3 mb-3">
        @if(($payroll->status->value ?? $payroll->status) === 'approved')
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><strong>Rollback Approval</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('hr.payroll.unapprove', $payroll) }}">
                            @csrf
                            <div class="mb-2">
                                <input type="date" name="reversal_date" class="form-control form-control-sm" value="{{ optional($payroll->period?->period_end)->format('Y-m-d') ?? now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="mb-2">
                                <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="Reason for rollback" required></textarea>
                            </div>
                            <button class="btn btn-outline-dark btn-sm">Move Back To Processed</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        @if(($payroll->status->value ?? $payroll->status) === 'paid')
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><strong>Rollback Payment</strong></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('hr.payroll.unpay', $payroll) }}">
                            @csrf
                            <div class="mb-2">
                                <input type="date" name="reversal_date" class="form-control form-control-sm" value="{{ optional($payroll->payment_date)->format('Y-m-d') ?? now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="mb-2">
                                <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="Reason for rollback" required></textarea>
                            </div>
                            <button class="btn btn-outline-dark btn-sm">Move Back To Approved</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endcanany

    <div class="card"><div class="card-header"><strong>Component Breakdown</strong></div><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Component</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                @forelse($payroll->components as $component)
                    <tr><td>{{ $component->component_name }}</td><td>{{ ucfirst(str_replace('_', ' ', $component->component_type)) }}</td><td class="text-end">₹{{ number_format($component->final_amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No components.</td></tr>
                @endforelse
                @if(abs((float) $payroll->round_off) > 0)
                    <tr><td>Round Off</td><td>Adjustment</td><td class="text-end">₹{{ number_format($payroll->round_off, 2) }}</td></tr>
                @endif
            </tbody>
        </table>
    </div></div>
</div>
@endsection
