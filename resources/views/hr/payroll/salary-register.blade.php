@extends('layouts.erp')

@section('title', 'Salary Register')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-journal-text"></i> Salary Register</h3>
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
                    <div class="fs-5 fw-bold">{{ number_format($summary['total_employees'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Gross</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_gross'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Deductions</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_deductions'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Net</div>
                    <div class="fs-5 fw-bold">₹{{ number_format($summary['total_net'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <i class="bi bi-table"></i> Register
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Emp Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-end">Paid Days</th>

                        <th class="text-end">Basic</th>
                        <th class="text-end">HRA</th>
                        <th class="text-end">DA</th>
                        <th class="text-end">Special</th>
                        <th class="text-end">Convey.</th>
                        <th class="text-end">Medical</th>
                        <th class="text-end">OT</th>
                        <th class="text-end">Bonus</th>
                        <th class="text-end">Other Earn.</th>

                        <th class="text-end">PF</th>
                        <th class="text-end">ESI</th>
                        <th class="text-end">PT</th>
                        <th class="text-end">TDS</th>
                        <th class="text-end">Loan</th>
                        <th class="text-end">Advance</th>
                        <th class="text-end">LOP</th>
                        <th class="text-end">Other Ded.</th>

                        <th class="text-end">Gross</th>
                        <th class="text-end">Deduct.</th>
                        <th class="text-end">Net</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($payrolls as $p)
                        <tr>
                            <td>{{ $p->employee_code }}</td>
                            <td>
                                <div class="fw-semibold">{{ $p->employee_name }}</div>
                                <div class="text-muted small">{{ $p->designation_name ?? $p->employee?->designation?->name }}</div>
                            </td>
                            <td>{{ $p->department_name ?? $p->employee?->department?->name }}</td>
                            <td class="text-end">{{ number_format($p->paid_days ?? 0, 1) }}</td>

                            <td class="text-end">{{ number_format($p->basic_salary ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->hra ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->da ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->special_allowance ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->conveyance ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->medical_allowance ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->ot_amount ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->bonus ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->other_earnings ?? 0, 2) }}</td>

                            <td class="text-end">{{ number_format($p->pf_employee ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->esi_employee ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->professional_tax ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->tds ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->loan_deduction ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->advance_deduction ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->lop_deduction ?? 0, 2) }}</td>
                            <td class="text-end">{{ number_format($p->other_deductions ?? 0, 2) }}</td>

                            <td class="text-end fw-semibold">{{ number_format($p->gross_salary ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($p->total_deductions ?? 0, 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($p->net_payable ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="24" class="text-center text-muted py-4">No payrolls found.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(!empty($payrolls) && count($payrolls) > 0)
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="21" class="text-end">Totals</th>
                            <th class="text-end">₹{{ number_format($summary['total_gross'] ?? 0, 2) }}</th>
                            <th class="text-end">₹{{ number_format($summary['total_deductions'] ?? 0, 2) }}</th>
                            <th class="text-end">₹{{ number_format($summary['total_net'] ?? 0, 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
