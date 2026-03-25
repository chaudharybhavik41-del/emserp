@extends('layouts.erp')

@section('title', 'Payslip')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-3">Payslip</h4>
    <div class="card"><div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6"><strong>Employee:</strong> {{ $payroll->employee?->full_name }}</div>
            <div class="col-md-6 text-md-end"><strong>Period:</strong> {{ $payroll->period?->name }}</div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <h6>Earnings</h6>
                <table class="table table-sm">
                    @foreach($payroll->components->where('component_type', 'earning') as $row)
                        <tr><td>{{ $row->component_name }}</td><td class="text-end">₹{{ number_format($row->final_amount, 2) }}</td></tr>
                    @endforeach
                </table>
            </div>
            <div class="col-md-6">
                <h6>Deductions</h6>
                <table class="table table-sm">
                    @foreach($payroll->components->where('component_type', 'deduction') as $row)
                        <tr><td>{{ $row->component_name }}</td><td class="text-end">₹{{ number_format($row->final_amount, 2) }}</td></tr>
                    @endforeach
                    @if(abs((float) $payroll->round_off) > 0)
                        <tr><td>Round Off</td><td class="text-end">₹{{ number_format($payroll->round_off, 2) }}</td></tr>
                    @endif
                </table>
            </div>
        </div>
        <hr>
        <div class="d-flex justify-content-between"><span>Net Before Round Off</span><span>₹{{ number_format($payroll->net_pay, 2) }}</span></div>
        <div class="d-flex justify-content-between"><span>Round Off</span><span>₹{{ number_format($payroll->round_off, 2) }}</span></div>
        <div class="d-flex justify-content-between"><strong>Net Payable</strong><strong>₹{{ number_format($payroll->net_payable, 2) }}</strong></div>
    </div></div>
</div>
@endsection
