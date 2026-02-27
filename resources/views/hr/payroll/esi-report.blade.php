@extends('layouts.erp')

@section('title', 'ESI Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-heart-pulse"></i> ESI Report</h3>
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
                    <div class="text-muted small">Employees</div>
                    <div class="fs-5 fw-bold">{{ number_format(count($payrolls ?? [])) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">ESI (Employee)</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_esi_employee'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">ESI (Employer)</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_esi_employer'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="bi bi-table"></i> ESI Contribution
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Emp Code</th>
                        <th>Employee</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">ESI (Emp)</th>
                        <th class="text-end">ESI (Empr)</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $p)
                        <tr>
                            <td>{{ $p->employee_code }}</td>
                            <td>{{ $p->employee_name }}</td>
                            <td class="text-end">{{ number_format($p->gross_salary ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->esi_employee ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->esi_employer ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format(($p->esi_employee ?? 0) + ($p->esi_employer ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No ESI contribution rows found.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(!empty($payrolls) && count($payrolls) > 0)
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Totals</th>
                            <th class="text-end">{{ number_format($summary['total_esi_employee'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_esi_employer'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_contribution'] ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
