@php
/** @var \App\Models\PurchaseRfq $rfq */
/** @var array $vendorTotals */
/** @var array $quoteMatrix */
/** @var array $revisionHistory */
/** @var string $viewVersion */
@endphp

@extends('layouts.erp')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0">RFQ Quotes & L1</h4>
            <div class="text-muted small">
                RFQ: <strong>{{ $rfq->code }}</strong> |
                Status: <span class="badge bg-secondary">{{ ucfirst($rfq->status) }}</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('purchase-rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">Back</a>
            @if(\Illuminate\Support\Facades\Route::has('purchase-rfqs.edit') && !in_array($rfq->status, ['po_generated','cancelled','closed']))
                <a href="{{ route('purchase-rfqs.edit', $rfq) }}" class="btn btn-sm btn-outline-secondary">Edit RFQ (Add Vendor)</a>
            @endif

            @if(!in_array($rfq->status, ['po_generated','cancelled','closed']))
                @can('purchase.po.create')
                    @php
                        $poAction = \Illuminate\Support\Facades\Route::has('purchase-orders.store-from-rfq')
                            ? route('purchase-orders.store-from-rfq', ['purchase_rfq' => $rfq->id])
                            : url('purchase-orders/from-rfq/' . $rfq->id);
                    @endphp
                    <form method="POST" action="{{ $poAction }}" class="d-inline"
                          onsubmit="return confirm('Generate Purchase Order(s) based on current L1 selections?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            Generate PO to L1 (Each Line)
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Vendor totals --}}
    @if(!empty($vendorTotals))
        <div class="card mb-3">
            <div class="card-header"><strong>Vendor Totals</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Vendor</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Grand Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rfq->vendors as $rfqVendor)
                            @php
                                $vt = $vendorTotals[$rfqVendor->id] ?? ['subtotal'=>0,'tax'=>0,'total'=>0];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $rfqVendor->vendor?->name ?? ('Vendor #'.$rfqVendor->id) }}</div>
                                    <div class="text-muted small">
                                        {{ $rfqVendor->email }}
                                        @if(!empty($rfqVendor->status))
                                            • <span class="badge bg-light text-dark border">{{ $rfqVendor->status }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">{{ number_format((float)$vt['subtotal'], 2) }}</td>
                                <td class="text-end">{{ number_format((float)$vt['tax'], 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format((float)$vt['total'], 2) }}</td>
                            </tr>
                        @endforeach
                        @if($rfq->vendors->count() === 0)
                            <tr><td colspan="4" class="text-center text-muted">No vendors added to this RFQ.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Request revised quote --}}
    @if(!in_array($rfq->status, ['po_generated','cancelled','closed']))
        <form method="POST" action="{{ route('purchase-rfqs.revision.send', $rfq) }}" class="mb-3">
            @csrf
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <strong>Invite Revised Quote (Vendor Portal Link)</strong>
                    <span class="text-muted small">
                        Email includes portal link + best-rate summary (without competitor names) to help vendor match.
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="text-muted small mb-2">Select vendor(s) to request revision:</div>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach($rfq->vendors as $rfqVendor)
                                    <label class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="vendor_ids[]"
                                               value="{{ $rfqVendor->id }}">
                                        <span class="form-check-label">
                                            {{ $rfqVendor->vendor?->name ?? ('Vendor #'.$rfqVendor->id) }}
                                        </span>
                                    </label>
                                @endforeach
                                @if($rfq->vendors->count() === 0)
                                    <span class="text-muted">No vendors.</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <label class="form-label small text-muted">Message to vendor (optional)</label>
                            <textarea name="message" class="form-control form-control-sm" rows="2"
                                      placeholder="E.g. Please revise rates for highlighted items and resubmit in portal.">{{ old('message') }}</textarea>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-warning"
                                    onclick="return confirm('Send revision request email to selected vendors?');">
                                Send Revision Request
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    @endif

    {{-- Quotes + L1 form --}}
    <form method="POST" action="{{ route('purchase-rfqs.quotes.update', $rfq) }}">
        @csrf

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="submit" class="btn btn-sm btn-primary" name="save_only" value="1">Save Quotes & L1</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" name="auto_l1" value="1">Auto Select L1 (Lowest Total)</button>
            <span class="text-muted small align-self-center">
                Tip: Enter quotes, then use Auto L1 to select lowest total per line.
            </span>
        </div>

        {{-- Vendor commercial terms --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong>Supplier Commercial Terms (from Vendor Quotation)</strong>
                <span class="text-muted small">
                    RFQ default: Payment {{ $rfq->payment_terms_days ?? '-' }} days,
                    Delivery {{ $rfq->delivery_terms_days ?? '-' }} days,
                    Freight {{ $rfq->freight_terms ?? '-' }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th style="width: 140px;">Payment (days)</th>
                                <th style="width: 140px;">Delivery (days)</th>
                                <th>Freight Terms</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rfq->vendors as $rfqVendor)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $rfqVendor->vendor?->name ?? 'Vendor' }}</div>
                                        <div class="text-muted small">{{ $rfqVendor->email }}</div>
                                    </td>
                                    <td>
                                        <input type="number"
                                               name="vendor_terms[{{ $rfqVendor->id }}][payment_terms_days]"
                                               class="form-control form-control-sm"
                                               value="{{ old('vendor_terms.'.$rfqVendor->id.'.payment_terms_days', $rfqVendor->payment_terms_days ?? $rfq->payment_terms_days) }}">
                                    </td>
                                    <td>
                                        <input type="number"
                                               name="vendor_terms[{{ $rfqVendor->id }}][delivery_terms_days]"
                                               class="form-control form-control-sm"
                                               value="{{ old('vendor_terms.'.$rfqVendor->id.'.delivery_terms_days', $rfqVendor->delivery_terms_days ?? $rfq->delivery_terms_days) }}">
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="vendor_terms[{{ $rfqVendor->id }}][freight_terms]"
                                               class="form-control form-control-sm"
                                               value="{{ old('vendor_terms.'.$rfqVendor->id.'.freight_terms', $rfqVendor->freight_terms ?? $rfq->freight_terms) }}">
                                    </td>
                                </tr>
                            @endforeach
                            @if($rfq->vendors->count() === 0)
                                <tr><td colspan="4" class="text-center text-muted">No vendors.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Item-wise Quote Entry --}}
        <div class="card mb-3">
            <div class="card-header"><strong>Item-wise Quote Entry & L1</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 260px;">Item</th>
                                <th style="width: 140px;">Brand</th>
                                <th style="width: 90px;" class="text-end">Qty</th>
                                <th style="width: 90px;">UOM</th>
                                @foreach($rfq->vendors as $rfqVendor)
                                    <th style="min-width: 300px;">
                                        {{ $rfqVendor->vendor?->name ?? ('Vendor #'.$rfqVendor->id) }}
                                        <div class="text-muted small">{{ $rfqVendor->email }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($rfq->items as $rfqItem)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $rfqItem->item?->name ?? 'Item' }}</div>
                                    <div class="text-muted small">
                                        @if($rfqItem->grade) <span class="badge bg-light text-dark border">Grade: {{ $rfqItem->grade }}</span> @endif
                                        @if($rfqItem->thickness_mm || $rfqItem->width_mm || $rfqItem->length_mm)
                                            <span class="badge bg-light text-dark border ms-1">
                                                {{ $rfqItem->thickness_mm ? 'T '.$rfqItem->thickness_mm.'mm' : '' }}
                                                {{ $rfqItem->width_mm ? ' W '.$rfqItem->width_mm.'mm' : '' }}
                                                {{ $rfqItem->length_mm ? ' L '.$rfqItem->length_mm.'mm' : '' }}
                                            </span>
                                        @endif
                                        @if($rfqItem->qty_pcs !== null)
                                            <span class="badge bg-light text-dark border ms-1">Pcs: {{ number_format((float)$rfqItem->qty_pcs, 3) }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td>{{ $rfqItem->brand ?: '-' }}</td>
                                <td class="text-end">{{ number_format((float)$rfqItem->quantity, 3) }}</td>
                                <td>{{ $rfqItem->uom?->code ?? $rfqItem->uom?->name ?? '' }}</td>

                                @foreach($rfq->vendors as $rfqVendor)
                                    @php
                                        $cell = $quoteMatrix[$rfqItem->id][$rfqVendor->id] ?? null;
                                        $hist = $revisionHistory[$rfqItem->id][$rfqVendor->id] ?? [];
                                        $isSelected = ((int)($rfqItem->selected_vendor_id ?? 0) === (int)$rfqVendor->id);
                                    @endphp
                                    <td>
                                        <div class="row g-1">
                                            <div class="col-6">
                                                <input type="number" step="0.01"
                                                       name="quotes[{{ $rfqItem->id }}][{{ $rfqVendor->id }}][rate]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Rate"
                                                       value="{{ old("quotes.$rfqItem->id.$rfqVendor->id.rate", $cell['rate'] ?? '') }}">
                                            </div>
                                            <div class="col-6">
                                                <input type="number" step="0.01"
                                                       name="quotes[{{ $rfqItem->id }}][{{ $rfqVendor->id }}][discount_percent]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Disc%"
                                                       value="{{ old("quotes.$rfqItem->id.$rfqVendor->id.discount_percent", $cell['discount_percent'] ?? '') }}">
                                            </div>
                                            <div class="col-6">
                                                <input type="number" step="0.01"
                                                       name="quotes[{{ $rfqItem->id }}][{{ $rfqVendor->id }}][tax_percent]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Tax%"
                                                       value="{{ old("quotes.$rfqItem->id.$rfqVendor->id.tax_percent", $cell['tax_percent'] ?? '') }}">
                                            </div>
                                            <div class="col-6">
                                                <input type="number"
                                                       name="quotes[{{ $rfqItem->id }}][{{ $rfqVendor->id }}][delivery_days]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Del days"
                                                       value="{{ old("quotes.$rfqItem->id.$rfqVendor->id.delivery_days", $cell['delivery_days'] ?? '') }}">
                                            </div>
                                            <div class="col-12">
                                                <input type="text"
                                                       name="quotes[{{ $rfqItem->id }}][{{ $rfqVendor->id }}][remarks]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Remarks"
                                                       value="{{ old("quotes.$rfqItem->id.$rfqVendor->id.remarks", $cell['remarks'] ?? '') }}">
                                            </div>

                                            <div class="col-12 mt-1 d-flex align-items-center justify-content-between">
                                                <label class="form-check mb-0">
                                                    <input class="form-check-input" type="radio"
                                                           name="l1[{{ $rfqItem->id }}]" value="{{ $rfqVendor->id }}"
                                                           @checked($isSelected)>
                                                    <span class="form-check-label small">Select as L1</span>
                                                </label>

                                                @if(!empty($cell['revision_no']))
                                                    <span class="text-muted small">Rev: {{ $cell['revision_no'] }}</span>
                                                @endif
                                            </div>

                                            {{-- Revision History --}}
                                            @if(!empty($hist) && count($hist) > 1)
                                                @php $collapseId = 'hist-'.$rfqItem->id.'-'.$rfqVendor->id; @endphp
                                                <div class="col-12">
                                                    <a class="small" data-bs-toggle="collapse" href="#{{ $collapseId }}" role="button" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                                        View Revision History ({{ count($hist) }})
                                                    </a>

                                                    <div class="collapse mt-1" id="{{ $collapseId }}">
                                                        <div class="border rounded p-2 bg-light">
                                                            <div class="small text-muted mb-1">
                                                                Latest first. Active row is the one shown above.
                                                            </div>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width:70px;">Rev</th>
                                                                            <th class="text-end">Rate</th>
                                                                            <th class="text-end">Disc%</th>
                                                                            <th class="text-end">Tax%</th>
                                                                            <th class="text-end">Delivery</th>
                                                                            <th style="width:160px;">Submitted</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($hist as $h)
                                                                            <tr class="{{ $h->is_active ? 'table-success' : '' }}">
                                                                                <td>{{ $h->revision_no }}</td>
                                                                                <td class="text-end">{{ number_format((float)$h->rate, 2) }}</td>
                                                                                <td class="text-end">{{ number_format((float)($h->discount_percent ?? 0), 2) }}</td>
                                                                                <td class="text-end">{{ number_format((float)($h->tax_percent ?? 0), 2) }}</td>
                                                                                <td class="text-end">{{ $h->delivery_days ?? '-' }}</td>
                                                                                <td>{{ $h->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        @if($rfq->items->count() === 0)
                            <tr><td colspan="{{ 4 + $rfq->vendors->count() }}" class="text-center text-muted">No items in this RFQ.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn btn-sm btn-primary" name="save_only" value="1">Save Quotes & L1</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" name="auto_l1" value="1">Auto Select L1 (Lowest Total)</button>
        </div>
    </form>
</div>
@endsection
