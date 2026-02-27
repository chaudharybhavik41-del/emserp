@extends('layouts.erp')

@section('title', 'Payroll Period')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h3 class="mb-1">Payroll Period: {{ $period->name }}</h3>
            <div class="text-muted small">
                <span class="me-2">
                    <i class="bi bi-calendar-event"></i>
                    Pay Period: {{ \Illuminate\Support\Carbon::parse($period->period_start)->format('d M Y') }}
                    - {{ \Illuminate\Support\Carbon::parse($period->period_end)->format('d M Y') }}
                </span>
                <span>
                    <i class="bi bi-calendar-check"></i>
                    Attendance: {{ \Illuminate\Support\Carbon::parse($period->attendance_start)->format('d M Y') }}
                    - {{ \Illuminate\Support\Carbon::parse($period->attendance_end)->format('d M Y') }}
                </span>
            </div>
            <span class="badge bg-{{ $period->status_color }} mt-2">
                <i class="bi bi-activity"></i> {{ $period->status_label }}
            </span>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('hr.payroll.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Periods
            </a>

            @can('hr.payroll.process')
                <form method="POST"
                      action="{{ route('hr.payroll.period.lock-attendance', $period) }}"
                      class="d-inline"
                      onsubmit="return confirm('Lock attendance for this period?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="bi bi-lock"></i> Lock Attendance
                    </button>
                </form>

                <form method="POST"
                      action="{{ route('hr.payroll.period.process', $period) }}"
                      class="d-inline"
                      onsubmit="return confirm('Process payroll for this period? This will create/update processed payroll records.');">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-gear"></i> Process Payroll
                    </button>
                </form>
            @endcan

            @can('hr.payroll.update')
                <form method="POST"
                      action="{{ route('hr.payroll.period.close', $period) }}"
                      class="d-inline"
                      onsubmit="return confirm('Close this period? You can close only after all payrolls are marked as paid.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark">
                        <i class="bi bi-check2-circle"></i> Close Period
                    </button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Employees</div>
                    <div class="fs-4 fw-bold">{{ number_format($summary['total_employees'] ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Gross</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($summary['total_gross'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Deductions</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($summary['total_deductions'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Net Payable</div>
                    <div class="fs-4 fw-bold">₹{{ number_format($summary['total_net'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-white">
                    <i class="bi bi-pie-chart"></i> Status Breakdown
                </div>
                <div class="card-body">
                    @if(!empty($summary['by_status']) && count($summary['by_status']) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Net Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($summary['by_status'] as $row)
                                        @php
                                            $statusVal = $row->status ?? 'unknown';
                                            $statusLabel = is_object($statusVal) && method_exists($statusVal, 'label')
                                                ? $statusVal->label()
                                                : ucwords(str_replace('_', ' ', (string) $statusVal));
                                            $statusColor = is_object($statusVal) && method_exists($statusVal, 'color')
                                                ? $statusVal->color()
                                                : 'secondary';
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="badge bg-{{ $statusColor }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td class="text-end">{{ number_format($row->count ?? 0) }}</td>
                                            <td class="text-end">₹{{ number_format($row->total ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">No payroll rows yet for this period.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-white">
                    <i class="bi bi-file-earmark-text"></i> Period Reports
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary"
                           href="{{ route('hr.payroll.reports.salary-register', $period) }}">
                            <i class="bi bi-journal-text"></i> Salary Register
                        </a>

                        <a class="btn btn-outline-secondary"
                           href="{{ route('hr.payroll.reports.bank-statement', $period) }}">
                            <i class="bi bi-bank"></i> Bank Statement
                        </a>

                        <a class="btn btn-outline-info"
                           href="{{ route('hr.payroll.reports.pf-report', $period) }}">
                            <i class="bi bi-shield-check"></i> PF Report
                        </a>

                        <a class="btn btn-outline-info"
                           href="{{ route('hr.payroll.reports.esi-report', $period) }}">
                            <i class="bi bi-heart-pulse"></i> ESI Report
                        </a>

                        <a class="btn btn-outline-warning"
                           href="{{ route('hr.payroll.reports.pt-report', $period) }}">
                            <i class="bi bi-receipt"></i> PT Report
                        </a>

                        <a class="btn btn-outline-warning"
                           href="{{ route('hr.payroll.reports.tds-report', $period) }}">
                            <i class="bi bi-percent"></i> TDS Report
                        </a>

                        <a class="btn btn-outline-dark"
                           href="{{ route('hr.payroll.reports.department-wise', $period) }}">
                            <i class="bi bi-diagram-3"></i> Department-wise Summary
                        </a>
                    </div>

                    <div class="text-muted small mt-3">
                        Reports are period-specific, so links are generated here (not in the sidebar).
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payroll list --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <i class="bi bi-list-check"></i> Payrolls
                <span class="text-muted small ms-2">({{ number_format($payrolls->total()) }} records)</span>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @can('hr.payroll.approve')
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="submitBulkApprove()">
                        <i class="bi bi-check2-square"></i> Bulk Approve
                    </button>
                @endcan

                @can('hr.payroll.pay')
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkPayModal">
                        <i class="bi bi-currency-rupee"></i> Bulk Pay
                    </button>
                @endcan
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" id="checkAll" />
                        </th>
                        <th>Emp Code</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-end">Paid Days</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($payrolls as $payroll)
                        <tr>
                            <td>
                                <input type="checkbox" class="payroll-check" value="{{ $payroll->id }}" />
                            </td>
                            <td>{{ $payroll->employee_code }}</td>
                            <td>
                                <div class="fw-semibold">{{ $payroll->employee_name }}</div>
                                <div class="text-muted small">
                                    {{ $payroll->designation_name ?? $payroll->employee?->designation?->name }}
                                </div>
                            </td>
                            <td>{{ $payroll->department_name ?? $payroll->employee?->department?->name }}</td>
                            <td class="text-end">{{ number_format($payroll->paid_days ?? 0, 1) }}</td>
                            <td class="text-end">₹{{ number_format($payroll->gross_salary ?? 0, 2) }}</td>
                            <td class="text-end">₹{{ number_format($payroll->total_deductions ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($payroll->net_payable ?? 0, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $payroll->status_color }}">{{ $payroll->status_label }}</span>
                                @if($payroll->is_hold)
                                    <span class="badge bg-warning text-dark">Hold</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.payroll.show', $payroll) }}" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.payroll.payslip', $payroll) }}" title="Payslip">
                                    <i class="bi bi-receipt-cutoff"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                No payroll records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body">
            {{ $payrolls->links() }}

            <div class="text-muted small mt-2">
                Bulk Approve will update only <b>Processed</b> payrolls. Bulk Pay will update only <b>Approved</b> payrolls.
            </div>
        </div>
    </div>

    {{-- Hidden bulk approve form --}}
    <form id="bulkApproveForm" method="POST" action="{{ route('hr.payroll.period.bulk-approve', $period) }}" class="d-none">
        @csrf
    </form>

    {{-- Bulk Pay Modal --}}
    <div class="modal fade" id="bulkPayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="bulkPayForm" method="POST" action="{{ route('hr.payroll.period.bulk-pay', $period) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-currency-rupee"></i> Bulk Pay
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Reference</label>
                        <input type="text" name="payment_reference" class="form-control" maxlength="100" placeholder="Optional">
                    </div>

                    <div class="alert alert-info mb-0">
                        Selected payroll IDs will be captured from the table before submit.
                    </div>

                    {{-- Hidden payroll_ids[] will be injected by JS --}}
                    <div id="bulkPayIdsContainer"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle"></i> Mark Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const checkAll = document.getElementById('checkAll');
        const checks = () => Array.from(document.querySelectorAll('.payroll-check'));
        const selectedIds = () => checks().filter(c => c.checked).map(c => c.value);

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                checks().forEach(c => c.checked = checkAll.checked);
            });
        }

        window.submitBulkApprove = function () {
            const ids = selectedIds();
            if (ids.length === 0) {
                alert('Please select at least one payroll row.');
                return;
            }

            const form = document.getElementById('bulkApproveForm');
            if (!form) return;

            // Remove previous injected inputs
            form.querySelectorAll('input[name="payroll_ids[]"]').forEach(el => el.remove());

            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'payroll_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            form.submit();
        }

        const bulkPayForm = document.getElementById('bulkPayForm');
        const container = document.getElementById('bulkPayIdsContainer');

        if (bulkPayForm && container) {
            bulkPayForm.addEventListener('submit', function (e) {
                const ids = selectedIds();
                if (ids.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one payroll row.');
                    return;
                }

                // Clear old inputs
                container.innerHTML = '';

                ids.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'payroll_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });
            });
        }
    })();
</script>
@endpush
@endsection
