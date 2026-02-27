@extends('layouts.erp')

@section('title', 'Tax Computation')

@section('content')
<div class="container-fluid py-3">
    <h4 class="mb-1">Tax Computation</h4>
    <p class="text-muted">{{ $employee->employee_code }} - {{ $employee->full_name }} | FY {{ $fy }}</p>

    <div class="row g-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Monthly Gross</small><h5>₹{{ number_format($monthlyGross, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Annual Income</small><h5>₹{{ number_format($annualIncome, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Exemptions</small><h5>₹{{ number_format($totalExemption, 2) }}</h5></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Tax Liability</small><h5>₹{{ number_format($taxLiability, 2) }}</h5></div></div></div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><strong>Taxable Income:</strong> ₹{{ number_format($taxableIncome, 2) }}</div>
                <div class="col-md-4"><strong>Regime:</strong> {{ strtoupper($declaration?->tax_regime ?? 'NEW') }}</div>
                <div class="col-md-4"><strong>Status:</strong> {{ ucfirst($declaration?->status ?? 'draft') }}</div>
            </div>
        </div>
    </div>

    @if($declaration && $declaration->details->count())
        <div class="card mt-3">
            <div class="card-header"><strong>Declaration Details</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Section</th><th>Investment</th><th>Declared</th><th>Verified</th></tr></thead>
                    <tbody>
                        @foreach($declaration->details as $detail)
                            <tr>
                                <td>{{ $detail->section_code }} - {{ $detail->section_name }}</td>
                                <td>{{ $detail->investment_type }}</td>
                                <td>₹{{ number_format($detail->declared_amount, 2) }}</td>
                                <td>₹{{ number_format($detail->verified_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
