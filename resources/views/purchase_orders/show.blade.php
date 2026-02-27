@extends('layouts.erp')

@section('title', 'Purchase Order ' . ($order->code ?? ('#' . $order->id)))

@section('content')
@php
    $status = $order->status ?? 'draft';
    $badge = 'secondary';
    $label = ucfirst($status);

    if ($status === 'draft') {
        $badge = 'secondary';
        $label = 'Draft';
    } elseif ($status === 'approved') {
        $badge = 'success';
        $label = 'Approved';
    } elseif ($status === 'cancelled') {
        $badge = 'danger';
        $label = 'Cancelled';
    }

    $indent = isset($order->indent) ? $order->indent : null;
    $rfq    = isset($order->rfq) ? $order->rfq : null;

    $itemsCount = isset($order->items) ? $order->items->count() : 0;

    // ✅ Route-safe email action (avoid RouteNotFoundException)
    $emailRouteName = null;
    if (\Illuminate\Support\Facades\Route::has('purchase-orders.sendEmail')) {
        $emailRouteName = 'purchase-orders.sendEmail';
    } elseif (\Illuminate\Support\Facades\Route::has('purchase-orders.send-email')) {
        $emailRouteName = 'purchase-orders.send-email';
    } elseif (\Illuminate\Support\Facades\Route::has('purchase.po.send')) {
        $emailRouteName = 'purchase.po.send';
    } elseif (\Illuminate\Support\Facades\Route::has('purchase-orders.email')) {
        $emailRouteName = 'purchase-orders.email';
    }
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Purchase Order: {{ $order->code ?? ('PO-' . $order->id) }}</h1>
        <div class="small text-muted mt-1">
            Status:
            <span class="badge bg-{{ $badge }}">{{ $label }}</span>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-secondary">Back to list</a>

        {{-- ✅ Email button only if route exists --}}
        @can('purchase.po.send')
            @if($emailRouteName)
                <form method="POST" action="{{ route($emailRouteName, $order) }}"
                      class="d-inline"
                      onsubmit="return confirm('Send PO to vendor by email?');">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-envelope"></i> Email
                    </button>
                </form>
            @endif
        @endcan

        {{-- ✅ Edit button (Draft only) --}}
        @can('purchase.po.update')
            @if($status === 'draft' && \Illuminate\Support\Facades\Route::has('purchase-orders.edit'))
                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
        @endcan

        <a href="{{ route('purchase-orders.print', $order) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
            <i class="bi bi-printer"></i> Print
        </a>

        @can('purchase.po.approve')
            @if($status === 'draft')
                <form method="POST" action="{{ route('purchase-orders.approve', $order) }}"
                      class="d-inline"
                      onsubmit="return confirm('Approve this Purchase Order?');">
                    @csrf
                    <button class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle"></i> Approve
                    </button>
                </form>
            @endif
        @endcan

        @can('purchase.po.delete')
            @if($status !== 'cancelled')
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
            @endif
        @endcan
    </div>
</div>

@include('partials.flash')

<div class="card mb-3">
    <div class="card-header"><strong>Order Details</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">PO Date</div>
                <div class="fw-semibold">{{ optional($order->po_date)->format('d-m-Y') ?? '-' }}</div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Project</div>
                <div class="fw-semibold">
                    @if($order->project)
                        {{ $order->project->code }} - {{ $order->project->name }}
                    @else
                        -
                    @endif
                </div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Department</div>
                <div class="fw-semibold">{{ $order->department->name ?? '-' }}</div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Vendor</div>
                <div class="fw-semibold">{{ $order->vendor->name ?? '-' }}</div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Indent</div>
                <div class="fw-semibold">
                    @if($indent)
                        <a href="{{ route('purchase-indents.show', $indent) }}">
                            {{ $indent->code ?? ('IND-' . $indent->id) }}
                        </a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">RFQ</div>
                <div class="fw-semibold">
                    @if($rfq)
                        <a href="{{ route('purchase-rfqs.show', $rfq) }}">
                            {{ $rfq->code ?? ('RFQ-' . $rfq->id) }}
                        </a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Payment Terms (days)</div>
                <div class="fw-semibold">{{ $order->payment_terms_days ?? '-' }}</div>
            </div>

            <div class="col-md-3">
                <div class="text-muted small">Delivery Terms (days)</div>
                <div class="fw-semibold">{{ $order->delivery_terms_days ?? '-' }}</div>
            </div>

            <div class="col-md-6">
                <div class="text-muted small">Freight Terms</div>
                <div class="fw-semibold">{{ $order->freight_terms ?? '-' }}</div>
            </div>

            <div class="col-md-6">
                <div class="text-muted small">Remarks</div>
                <div class="fw-semibold">{{ $order->remarks ?? '-' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Items</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width:4%" class="text-center">#</th>
                    <th>Item</th>
                    <th style="width:10%" class="text-end">Qty</th>
                    <th style="width:10%">UOM</th>
                    <th style="width:10%" class="text-end">Rate</th>
                    <th style="width:10%" class="text-end">Disc%</th>
                    <th style="width:10%" class="text-end">Tax%</th>
                    <th style="width:12%" class="text-end">Line Total</th>
                </tr>
                </thead>

                <tbody>
                @php $grand = 0.0; @endphp

                @if($itemsCount <= 0)
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No items found.</td>
                    </tr>
                @else
                    @foreach($order->items as $line)
                        @php
                            $lineTotal = (float) ($line->total_amount ?? $line->net_amount ?? 0);
                            $grand += $lineTotal;

                            $uomName = '-';
                            if ($line->uom && $line->uom->name) {
                                $uomName = $line->uom->name;
                            } elseif ($line->item && $line->item->uom && $line->item->uom->name) {
                                $uomName = $line->item->uom->name;
                            }
                        @endphp
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $line->item->code ?? '' }}</strong>
                                - {{ $line->item->name ?? '' }}
                                @if($line->purchase_indent_item_id)
                                    <div class="small text-muted">Indent Line ID: {{ $line->purchase_indent_item_id }}</div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((float) ($line->quantity ?? 0), 3) }}</td>
                            <td>{{ $uomName }}</td>
                            <td class="text-end">{{ number_format((float) ($line->rate ?? 0), 2) }}</td>
                            <td class="text-end">{{ number_format((float) ($line->discount_percent ?? 0), 2) }}</td>
                            <td class="text-end">{{ number_format((float) ($line->tax_percent ?? $line->tax_rate ?? 0), 2) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($lineTotal, 2) }}</td>
                        </tr>
                    @endforeach
                @endif
                </tbody>

                @if($itemsCount > 0)
                    <tfoot class="table-light">
                    <tr>
                        <th colspan="7" class="text-end">Grand Total</th>
                        <th class="text-end">{{ number_format($grand, 2) }}</th>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@can('purchase.po.delete')
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Cancel Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Reason (optional)</label>
                    <textarea name="reason" class="form-control" rows="3"></textarea>
                </div>
                <div class="alert alert-warning mb-0">
                    This will cancel the PO. If GRN / Purchase Bill exists, cancellation will be blocked.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-sm btn-danger">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
