@extends('layouts.erp')

@section('title', 'Department-wise Payroll Summary')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-diagram-3"></i> Department-wise Payroll Summary</h3>
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
                    <div class="text-muted small">Departments</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['departments'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Employees</div>
                    <div class="fs-5 fw-bold">{{ number_format($summary['employees'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Gross</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_gross'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Net</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_net'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="bi bi-table"></i> Summary
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Employees</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->department_name }}</td>
                            <td class="text-end">{{ number_format($r->employees ?? 0) }}</td>
                            <td class="text-end">{{ number_format($r->total_gross ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($r->total_deductions ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r->total_net ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No rows found.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(!empty($rows) && count($rows) > 0)
                    <tfoot class="table-light">
                        <tr>
                            <th class="text-end">Totals</th>
                            <th class="text-end">{{ number_format($summary['employees'] ?? 0) }}</th>
                            <th class="text-end">{{ number_format($summary['total_gross'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_deductions'] ?? 0, 2) }}</th>
                            <th class="text-end">{{ number_format($summary['total_net'] ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
