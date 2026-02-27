@extends('layouts.erp')

@section('title', 'Subcontractor RA Bill')

@section('content')
<div class="container-fluid">
    @include('partials.alerts')

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">Subcontractor RA Bill: {{ $subcontractorRa->ra_number }}</h1>
            <div class="small text-muted">
                Project: {{ $subcontractorRa->project?->name }} | Subcontractor: {{ $subcontractorRa->subcontractor?->name }}
            </div>
        </div>

        <div class="text-end">
            @php
                $badge = match($subcontractorRa->status) {
                    'posted'    => 'success',
                    'approved'  => 'primary',
                    'submitted' => 'warning',
                    'rejected'  => 'danger',
                    default     => 'secondary',
                };
            @endphp
            <div class="mb-2">
                <span class="badge text-bg-{{ $badge }}">{{ ucfirst($subcontractorRa->status) }}</span>
            </div>

            <a href="{{ route('accounting.subcontractor-ra.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
            @if($subcontractorRa->status !== 'posted')
                <a href="{{ route('accounting.subcontractor-ra.edit', $subcontractorRa) }}" class="btn btn-outline-primary btn-sm ms-1">Edit</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header py-2 fw-semibold">Bill Details</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4"><div class="small text-muted">Bill No</div><div class="fw-semibold">{{ $subcontractorRa->bill_number ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Bill Date</div><div class="fw-semibold">{{ $subcontractorRa->bill_date?->format('d-m-Y') }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Due Date</div><div class="fw-semibold">{{ $subcontractorRa->due_date?->format('d-m-Y') ?? '-' }}</div></div>

                        <div class="col-md-4"><div class="small text-muted">Period</div><div class="fw-semibold">{{ $subcontractorRa->period_from?->format('d-m-Y') ?? '-' }} to {{ $subcontractorRa->period_to?->format('d-m-Y') ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Work Order</div><div class="fw-semibold">{{ $subcontractorRa->work_order_number ?? '-' }}</div></div>
                        <div class="col-md-4"><div class="small text-muted">Voucher</div><div class="fw-semibold">{{ $subcontractorRa->voucher?->voucher_no ?? '-' }}</div></div>

                        <div class="col-12"><div class="small text-muted">Remarks</div><div>{{ $subcontractorRa->remarks ?? '-' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 fw-semibold">Lines</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th style="width: 9%">BOQ</th>
                                <th>Description</th>
                                <th style="width: 10%">UOM</th>
                                <th class="text-end" style="width: 10%">Prev Qty</th>
                                <th class="text-end" style="width: 10%">Curr Qty</th>
                                <th class="text-end" style="width: 10%">Rate</th>
                                <th class="text-end" style="width: 12%">Curr Amt</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($subcontractorRa->lines as $line)
                                <tr>
                                    <td>{{ $line->boq_item_code ?? '-' }}</td>
                                    <td>{{ $line->description }}</td>
                                    <td>{{ $line->uom?->name ?? '-' }}</td>
                                    <td class="text-end">{{ number_format((float) $line->previous_qty, 4) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->current_qty, 4) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->rate, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $line->current_amount, 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2 fw-semibold">Totals</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><div class="small text-muted">Current Amount</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->current_amount, 2) }}</div></div>
                        <div class="col-6"><div class="small text-muted">Retention</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->retention_amount, 2) }}</div></div>
                        <div class="col-6"><div class="small text-muted">Advance Recovery</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->advance_recovery, 2) }}</div></div>
                        <div class="col-6"><div class="small text-muted">Other Deductions</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->other_deductions, 2) }}</div></div>

                        <div class="col-6"><div class="small text-muted">Net Amount</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->net_amount, 2) }}</div></div>
                        <div class="col-6"><div class="small text-muted">GST Total</div><div class="fw-semibold">{{ number_format((float) $subcontractorRa->total_gst, 2) }}</div></div>

                        <div class="col-6">
                            <div class="small text-muted">TDS</div>
                            <div class="fw-semibold">
                                {{ number_format((float) $subcontractorRa->tds_amount, 2) }}
                                @if($subcontractorRa->tds_section)
                                    <div class="small text-muted">Sec: {{ $subcontractorRa->tds_section }} @ {{ rtrim(rtrim(number_format((float) $subcontractorRa->tds_rate, 4), '0'), '.') }}%</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-6"><div class="small text-muted">Payable</div><div class="fw-bold fs-5">{{ number_format((float) $subcontractorRa->total_amount, 2) }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2 fw-semibold">Actions</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @can('subcontractor_ra.submit')
                            @if($subcontractorRa->status === 'draft')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.submit', $subcontractorRa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-warning">Submit for Approval</button>
                                </form>
                            @endif
                        @endcan

                        @can('subcontractor_ra.approve')
                            @if($subcontractorRa->status === 'submitted')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.approve', $subcontractorRa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </form>
                            @endif
                        @endcan

                        @can('subcontractor_ra.reject')
                            @if($subcontractorRa->status === 'submitted')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.reject', $subcontractorRa) }}" class="d-flex gap-2 align-items-end">
                                    @csrf
                                    <div>
                                        <label class="form-label mb-1">Rejection Reason</label>
                                        <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason" required>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger">Reject</button>
                                </form>
                            @endif
                        @endcan

                        @can('subcontractor_ra.post')
                            @if($subcontractorRa->status === 'approved')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.post', $subcontractorRa) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Post to Accounts</button>
                                </form>
                            @endif
                        @endcan

                        @can('subcontractor_ra.post')
                            @if($subcontractorRa->status === 'posted')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.reverse', $subcontractorRa) }}" class="d-flex gap-2 align-items-end">
                                    @csrf
                                    <div>
                                        <label class="form-label mb-1">Reversal Reason</label>
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason" required>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger">Reverse Posting</button>
                                </form>
                            @endif
                        @endcan

                        @can('subcontractor_ra.delete')
                            @if($subcontractorRa->status !== 'posted')
                                <form method="POST" action="{{ route('accounting.subcontractor-ra.destroy', $subcontractorRa) }}" onsubmit="return confirm('Delete this RA Bill?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
