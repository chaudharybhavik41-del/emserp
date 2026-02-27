@extends('layouts.erp')

@section('title', 'Bank Statement')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-bank"></i> Bank Statement</h3>
            <div class="text-muted small">
                Period: <b>{{ $period->name }}</b>
                ({{ \Illuminate\Support\Carbon::parse($period->period_start)->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($period->period_end)->format('d M Y') }})
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('hr.payroll.period', $period) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Period
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Employees (Bank Transfer)</div>
                    <div class="fs-5 fw-bold">{{ number_format(count($payrolls ?? [])) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Amount</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($total ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="bi bi-table"></i> Bank Transfer List
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Emp Code</th>
                        <th>Employee</th>
                        <th>Bank A/C</th>
                        <th>IFSC</th>
                        <th class="text-end">Net Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $p)
                        <tr>
                            <td>{{ $p->employee_code }}</td>
                            <td>{{ $p->employee_name }}</td>
                            <td>{{ $p->bank_account ?: ($p->employee?->bank_account ?? '-') }}</td>
                            <td>{{ $p->bank_ifsc ?: ($p->employee?->bank_ifsc ?? '-') }}</td>
                            <td class="text-end">₹{{ number_format($p->net_payable ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No bank transfer payrolls found.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(!empty($payrolls) && count($payrolls) > 0)
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end">₹{{ number_format($total ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
