@extends('layouts.erp')

@section('title', 'Subcontractor Work Order')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $subcontractorWorkOrder->work_order_number }}</h1>
            <div class="text-muted small">Subcontractor work order master</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('accounting.subcontractor-work-orders.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('accounting.subcontractor-work-orders.edit', $subcontractorWorkOrder) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <form method="POST" action="{{ route('accounting.subcontractor-work-orders.destroy', $subcontractorWorkOrder) }}" onsubmit="return confirm('Delete this work order?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header py-2 fw-semibold">Work Order Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="small text-muted">Date</div><div class="fw-semibold">{{ $subcontractorWorkOrder->work_order_date?->format('d-m-Y') ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Subcontractor</div><div class="fw-semibold">{{ $subcontractorWorkOrder->subcontractor?->name ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Project</div><div class="fw-semibold">{{ $subcontractorWorkOrder->project?->name ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Start Date</div><div class="fw-semibold">{{ $subcontractorWorkOrder->start_date?->format('d-m-Y') ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">End Date</div><div class="fw-semibold">{{ $subcontractorWorkOrder->end_date?->format('d-m-Y') ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Status</div><div class="fw-semibold">{{ ucfirst((string) $subcontractorWorkOrder->status) }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Payment Terms</div><div class="fw-semibold">{{ $subcontractorWorkOrder->payment_terms_days !== null ? ($subcontractorWorkOrder->payment_terms_days . ' days') : '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Retention %</div><div class="fw-semibold">{{ number_format((float) ($subcontractorWorkOrder->retention_percent ?? 0), 2) }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Security Deposit %</div><div class="fw-semibold">{{ number_format((float) ($subcontractorWorkOrder->security_deposit_percent ?? 0), 2) }}</div></div>
                        <div class="col-12"><div class="small text-muted">Other Terms</div><div>{{ $subcontractorWorkOrder->other_terms ?: '-' }}</div></div>
                        <div class="col-12"><div class="small text-muted">Remarks</div><div>{{ $subcontractorWorkOrder->remarks ?: '-' }}</div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-2 fw-semibold">Recent Linked RA Bills</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>RA No</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Payable</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subcontractorWorkOrder->raBills as $bill)
                                    <tr>
                                        <td>
                                            <a href="{{ route('accounting.subcontractor-ra.show', $bill) }}">{{ $bill->ra_number }}</a>
                                        </td>
                                        <td>{{ $bill->bill_date?->format('d-m-Y') ?? '-' }}</td>
                                        <td>{{ ucfirst((string) $bill->status) }}</td>
                                        <td class="text-end">{{ number_format((float) ($bill->total_amount ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No RA bills linked yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
