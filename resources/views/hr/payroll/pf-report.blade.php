@extends('layouts.erp')

@section('title', 'PF Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-shield-check"></i> PF Report</h3>
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
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Employees</div>
                    <div class="fs-5 fw-bold">{{ number_format(count($payrolls ?? [])) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">PF (Employee)</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_pf_employee'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">PF (Employer)</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_pf_employer'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Contribution</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_contribution'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="bi bi-table"></i> PF Contribution
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Emp Code</th>
                        <th>Employee</th>
                        <th class="text-end">PF (Emp)</th>
                        <th class="text-end">PF (Empr)</th>
                        <th class="text-end">EPS</th>
                        <th class="text-end">EDLI</th>
                        <th class="text-end">Admin</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $p)
                        @php
                            $rowTotal = ($p->pf_employee ?? 0)
                                + ($p->pf_employer ?? 0)
                                + ($p->eps_employer ?? 0)
                                + ($p->edli_employer ?? 0)
                                + ($p->pf_admin_charges ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $p->employee_code }}</td>
                            <td>{{ $p->employee_name }}</td>
                            <td class="text-end">{{ number_format($p->pf_employee ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->pf_employer ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->eps_employer ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->edli_employer ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->pf_admin_charges ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($rowTotal, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No PF contribution rows found.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(!empty($payrolls) && count($payrolls) > 0)
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">Totals</th>
                            <th class="text-end">{{ number_format($summary['total_pf_employee'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_pf_employer'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_eps'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_edli'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_admin_charges'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_contribution'] ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
