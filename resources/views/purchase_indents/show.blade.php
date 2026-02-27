@extends('layouts.erp')

@section('title', 'Indent ' . $indent->code)

@section('content')
    @php
        $procStatus = $indent->procurement_status ?? 'open';

        $procBadge = match ($procStatus) {
            'ordered'            => 'success',
            'partially_ordered'  => 'warning',
            'rfq_created'        => 'info',
            'closed'             => 'dark',
            'cancelled'          => 'danger',
            default              => 'secondary',
        };

        $procLabel = match ($procStatus) {
            'ordered'            => 'Ordered',
            'partially_ordered'  => 'Partially Ordered',
            'rfq_created'        => 'RFQ Created',
            'closed'             => 'Closed',
            'cancelled'          => 'Cancelled',
            default              => 'Open',
        };

        $statusBadge = $indent->status === 'approved'
            ? 'success'
            : ($indent->status === 'rejected'
                ? 'danger'
                : 'secondary');

        // ✅ Related docs (kept simple: query here to avoid controller edits)
        $relatedRfqs = \App\Models\PurchaseRfq::query()
            ->where('purchase_indent_id', $indent->id)
            ->orderByDesc('id')
            ->get();

        $relatedPos = \App\Models\PurchaseOrder::query()
            ->where('purchase_indent_id', $indent->id)
            ->orderByDesc('id')
            ->get();
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Purchase Indent: {{ $indent->code }}</h1>

            <div class="small text-muted mt-1 d-flex flex-wrap gap-2 align-items-center">
                <div>
                    Status:
                    <span class="badge bg-{{ $statusBadge }}">
                        {{ ucfirst($indent->status) }}
                    </span>
                </div>

                <div>
                    Procurement:
                    <span class="badge bg-{{ $procBadge }}">
                        {{ $procLabel }}
                    </span>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('purchase-indents.index') }}" class="btn btn-sm btn-secondary">Back to list</a>

            @can('purchase.rfq.create')
                @if($indent->status === 'approved')
                    <a href="{{ route('purchase-rfqs.create', ['purchase_indent_id' => $indent->id]) }}"
                       class="btn btn-sm btn-outline-success">
                        <i class="bi bi-send"></i> Create RFQ
                    </a>
                @endif
            @endcan

            @can('purchase.indent.update')
                @if(!in_array($indent->status, ['approved', 'rejected']))
                    <a href="{{ route('purchase-indents.edit', $indent) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                @endif
            @endcan

            @can('purchase.indent.approve')
                @if($indent->status === 'draft')
                    <form action="{{ route('purchase-indents.approve', $indent) }}" method="POST" style="display: inline"
                          onsubmit="return confirm('Approve this indent?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                    </form>

                    <form action="{{ route('purchase-indents.reject', $indent) }}" method="POST" style="display: inline"
                          onsubmit="return confirm('Reject this indent? This cannot be undone.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @include('partials.flash')

    <div class="card mb-3">
        <div class="card-header"><strong>Indent Details</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Project:</label>
                    <div>{{ $indent->project ? $indent->project->code . ' - ' . $indent->project->name : 'N/A' }}</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Department:</label>
                    <div>{{ $indent->department->name ?? 'N/A' }}</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Required By:</label>
                    <div>{{ optional($indent->required_by_date)->format('d-m-Y') ?? 'N/A' }}</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Created:</label>
                    <div>{{ optional($indent->created_at)->format('d-m-Y H:i') ?? 'N/A' }}</div>
                </div>
            </div>

            @if($indent->remarks)
                <div class="mt-3">
                    <label class="form-label fw-semibold">Remarks:</label>
                    <div class="text-muted">{{ $indent->remarks }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Indent Items</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 4%;" class="text-center">#</th>
                        <th>Item</th>
                        <th style="width: 10%;">Grade</th>
                        <th style="width: 8%;" class="text-end">Thk</th>
                        <th style="width: 8%;" class="text-end">Len</th>
                        <th style="width: 8%;" class="text-end">Wid</th>
                        <th style="width: 10%;" class="text-end">Wt/M</th>
                        <th style="width: 8%;" class="text-end">Pcs</th>
                        <th style="width: 12%;" class="text-end">Required</th>
                        <th style="width: 12%;" class="text-end">Received</th>
                        <th style="width: 12%;" class="text-end">Balance</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($indent->items as $item)
                        @php
                            $receivedQty = (float) ($item->received_qty_total ?? 0);
                            $requiredQty = (float) ($item->order_qty ?? 0);
                            $balanceQty  = max($requiredQty - $receivedQty, 0);
                        @endphp
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $item->item->code ?? '' }}</strong>
                                - {{ $item->item->name ?? '' }}
                            </td>
                            <td>{{ $item->grade ?? '-' }}</td>
                            <td class="text-end">{{ $item->thickness_mm ? number_format($item->thickness_mm, 2) : '-' }}</td>
                            <td class="text-end">{{ $item->length_mm ? number_format($item->length_mm, 2) : '-' }}</td>
                            <td class="text-end">{{ $item->width_mm ? number_format($item->width_mm, 2) : '-' }}</td>
                            <td class="text-end">{{ $item->weight_per_meter_kg ? number_format($item->weight_per_meter_kg, 3) : '-' }}</td>
                            <td class="text-end">{{ $item->qty_pcs ? number_format($item->qty_pcs, 0) : '-' }}</td>

                            <td class="text-end">
                                <strong>{{ number_format($requiredQty, 3) }}</strong> {{ optional($item->uom)->name ?? '' }}
                            </td>

                            <td class="text-end">{{ $receivedQty > 0 ? number_format($receivedQty, 3) : '-' }}</td>
                            <td class="text-end">{{ $requiredQty > 0 ? number_format($balanceQty, 3) : '-' }}</td>

                            <td>
                                @if((int) ($item->is_closed ?? 0) === 1)
                                    <span class="badge bg-success">Closed</span>
                                @elseif($receivedQty > 0)
                                    <span class="badge bg-warning text-dark">Part Received</span>
                                @else
                                    <span class="badge bg-secondary">Open</span>
                                @endif
                            </td>
                            <td><small>{{ $item->remarks ?? '-' }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">No items in this indent.</td>
                        </tr>
                    @endforelse
                    </tbody>

                    @if($indent->items->count() > 0)
                        <tfoot class="table-light">
                        <tr>
                            <th colspan="8" class="text-end">Total Items:</th>
                            <th class="text-end">{{ $indent->items->count() }}</th>
                            <th colspan="4"></th>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- ✅ NEW: Related RFQs --}}
    <div class="card mb-3">
        <div class="card-header"><strong>Related RFQs</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width:20%">RFQ No</th>
                        <th style="width:15%">Date</th>
                        <th style="width:15%">Status</th>
                        <th style="width:20%">Project</th>
                        <th style="width:10%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($relatedRfqs as $rfq)
                        <tr>
                            <td class="fw-semibold">{{ $rfq->code }}</td>
                            <td>{{ optional($rfq->rfq_date)->format('d-m-Y') }}</td>
                            <td><span class="badge bg-secondary">{{ strtoupper($rfq->status) }}</span></td>
                            <td>{{ $rfq->project?->code }} {{ $rfq->project?->name ? ('- '.$rfq->project->name) : '' }}</td>
                            <td class="text-end">
                                <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No RFQs found for this indent.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ✅ NEW: Related Purchase Orders --}}
    <div class="card mb-3">
        <div class="card-header"><strong>Related Purchase Orders</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width:20%">PO No</th>
                        <th style="width:15%">Date</th>
                        <th style="width:15%">Status</th>
                        <th style="width:30%">Vendor</th>
                        <th style="width:15%" class="text-end">Total</th>
                        <th style="width:10%"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($relatedPos as $po)
                        <tr>
                            <td class="fw-semibold">{{ $po->code }}</td>
                            <td>{{ optional($po->po_date)->format('d-m-Y') }}</td>
                            <td><span class="badge bg-secondary">{{ strtoupper($po->status) }}</span></td>
                            <td>{{ $po->vendor?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float)($po->total_amount ?? 0), 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('purchase-orders.show', $po) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No Purchase Orders found for this indent.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($indent->status === 'rejected')
        <div class="card border-danger">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <i class="bi bi-x-circle-fill"></i> Indent Rejected
                </h5>
                <p class="mb-0">This indent has been rejected and cannot be used for RFQs or Purchase Orders.</p>
            </div>
        </div>
    @endif
@endsection
