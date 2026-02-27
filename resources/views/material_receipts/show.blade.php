@extends('layouts.erp')

@section('title', 'Material Receipt Details (GRN)')

@section('content')
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">
                Material Receipt (GRN)
                @if($receipt->receipt_number)
                    - {{ $receipt->receipt_number }}
                @endif
            </h1>
            <div class="d-flex gap-2">
                <a href="{{ route('material-receipts.index') }}" class="btn btn-sm btn-outline-secondary">
                    Back to GRNs
                </a>

                @if($receipt->status === 'qc_passed')
                    <a href="{{ route('material-receipts.return.create', $receipt) }}" class="btn btn-sm btn-outline-danger">
                        Vendor Return
                    </a>
                @else
                    <form method="POST" action="{{ route('material-receipts.destroy', $receipt) }}" onsubmit="return confirm('Delete this GRN? This is allowed only before QC PASSED.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Delete GRN</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <strong>GRN No:</strong><br>
                        {{ $receipt->receipt_number }}
                    </div>
                    <div class="col-md-3">
                        <strong>GRN Date:</strong><br>
                        {{ optional($receipt->receipt_date)->format('d-m-Y') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Material Type:</strong><br>
                        {{ $receipt->is_client_material ? 'Client Material' : 'Own Material' }}
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-secondary">{{ strtoupper($receipt->status) }}</span>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <strong>Supplier:</strong><br>
                        @if($receipt->supplier)
                            {{ $receipt->supplier->name }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Client:</strong><br>
                        @if($receipt->client)
                            {{ $receipt->client->name }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Project:</strong><br>
                        @if($receipt->project)
                            {{ $receipt->project->code }} - {{ $receipt->project->name }}
                        @else
                            -
                        @endif
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <strong>Linked PO:</strong><br>
                        @if($receipt->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}">
                                {{ $receipt->purchaseOrder->code }}
                            </a>
                        @elseif($receipt->po_number)
                            {{ $receipt->po_number }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Invoice:</strong><br>
                        {{ $receipt->invoice_number ?? '-' }}
                        @if($receipt->invoice_date)
                            ({{ $receipt->invoice_date->format('d-m-Y') }})
                        @endif
                    </div>
                    <div class="col-md-4">
                        <strong>Challan:</strong><br>
                        {{ $receipt->challan_number ?? '-' }}
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <strong>Vehicle No:</strong><br>
                        {{ $receipt->vehicle_number ?? '-' }}
                    </div>
                    <div class="col-md-8">
                        <strong>Remarks:</strong><br>
                        {{ $receipt->remarks ?? '-' }}
                    </div>
                </div>

                @can('store.material_receipt.update')
                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('material-receipts.update-status', $receipt) }}" class="d-flex gap-2 flex-wrap">
                                @csrf
                                <input type="hidden" name="status" value="qc_pending">
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        @if($receipt->status === 'qc_pending') disabled @endif>
                                    Mark QC Pending
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <form method="POST" action="{{ route('material-receipts.update-status', $receipt) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="status" value="qc_passed">
                                <button type="submit" class="btn btn-sm btn-success me-1"
                                        @if($receipt->status === 'qc_passed') disabled @endif>
                                    QC Passed
                                </button>
                            </form>
                            <form method="POST" action="{{ route('material-receipts.update-status', $receipt) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="status" value="qc_rejected">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        @if($receipt->status === 'qc_rejected') disabled @endif>
                                    QC Rejected
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
            </div>
        </div>

        @if(!empty($receipt->vendorReturns) && $receipt->vendorReturns->count())
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 h6">Vendor Returns</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 14%">Return No</th>
                                    <th style="width: 12%">Date</th>
                                    <th style="width: 18%">Party</th>
                                    <th style="width: 16%">Reason</th>
                                    <th style="width: 10%" class="text-end">Pcs</th>
                                    <th style="width: 12%" class="text-end">Wt (kg)</th>
                                    <th style="width: 18%">Voucher</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receipt->vendorReturns as $vr)
                                    @php
        $vrPcs = $vr->lines?->sum('returned_qty_pcs') ?? 0;
        $vrWt = $vr->lines?->sum('returned_weight_kg') ?? 0;
                                    @endphp

                                    <tr>
                                        <td>
                                            <button class="btn btn-link btn-sm p-0 text-decoration-none"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#vr_details_{{ $vr->id }}"
                                                    aria-expanded="false"
                                                    aria-controls="vr_details_{{ $vr->id }}">
                                                {{ $vr->vendor_return_number ?? ('#' . $vr->id) }}
                                            </button>
                                        </td>
                                        <td>{{ $vr->return_date?->format('d-m-Y') ?? '-' }}</td>
                                        <td>{{ $vr->toParty?->name ?? '-' }}</td>
                                        <td>{{ $vr->reason ?? '-' }}</td>
                                        <td class="text-end">{{ (int) $vrPcs }}</td>
                                        <td class="text-end">{{ number_format((float) $vrWt, 3) }}</td>
                                        <td>
                                            @if($vr->voucher_id)
                                                <a href="{{ route('accounting.vouchers.show', $vr->voucher_id) }}" class="text-decoration-none">
                                                    {{ $vr->voucher?->voucher_no ?? ('#' . $vr->voucher_id) }}
                                                </a>
                                                <span class="badge bg-secondary ms-1">{{ strtoupper($vr->voucher?->status ?? '') }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="vr_details_{{ $vr->id }}">
                                        <td colspan="7">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Stock Reference</th>
                                                            <th class="text-end">Pcs</th>
                                                            <th class="text-end">Wt (kg)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($vr->lines as $ln)
                                                            <tr>
                                                                <td>{{ $ln->item?->name ?? ('Item #' . $ln->item_id) }}</td>
                                                                <td class="small text-muted">
                                                                    @if($ln->stockItem)
                                                                        #{{ $ln->stockItem->id }}
                                                                        @if(!empty($ln->stockItem->plate_number))
                                                                            | Plate: {{ $ln->stockItem->plate_number }}
                                                                        @endif
                                                                        @if(!empty($ln->stockItem->heat_number))
                                                                            | Heat: {{ $ln->stockItem->heat_number }}
                                                                        @endif
                                                                    @else
                                                                        -
                                                                    @endif
                                                                </td>
                                                                <td class="text-end">{{ (int) $ln->returned_qty_pcs }}</td>
                                                                <td class="text-end">{{ number_format((float) ($ln->returned_weight_kg ?? 0), 3) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Header-level documents --}}
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 h6">Documents (Invoice / Challan / LR)</h5>
            </div>
            <div class="card-body">
                @can('store.material_receipt.update')
                    <form action="{{ route('material-receipts.attachments.store', $receipt) }}"
                          method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label">File</label>
                            <input type="file" name="file" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">-- Select --</option>
                                <option value="invoice">Invoice</option>
                                <option value="challan">Challan</option>
                                <option value="lr_copy">LR Copy</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-sm btn-primary mt-2">
                                Upload
                            </button>
                        </div>
                    </form>
                @endcan

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 40%">File</th>
                            <th style="width: 20%">Category</th>
                            <th style="width: 30%">Uploaded At</th>
                            <th style="width: 10%"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($receipt->attachments as $att)
                            <tr>
                                <td>
                                    <a href="{{ route('attachments.download', $att) }}">
                                        {{ $att->original_name }}
                                    </a>
                                </td>
                                <td>{{ $att->category ?? '-' }}</td>
                                <td>{{ optional($att->created_at)->format('d-m-Y H:i') }}</td>
                                <td class="text-end">
                                    @can('store.material_receipt.delete')
                                        <form action="{{ route('attachments.destroy', $att) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-2">
                                    No documents uploaded.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- GRN line items + line documents --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 h6 fw-semibold">
                GRN Line Items & Documents
            </h5>
            <span class="badge bg-light text-dark">
                {{ $receipt->lines->count() }} Items
            </span>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light text-center small">
                        <tr>
                        <th style="min-width:160px;">Item</th>
                        <th>Category</th>
                        <th>Grade</th>
                        <th>Thickness (mm)</th>
                        <th>Width (mm)</th>
                        <th>Length (mm)</th>
                        <th>Section Profile</th>
                        <th>Quantity (pcs)</th>
                        <th>Received Weight (kg)</th>
                        <th>UOM</th>
                        <th style="min-width:240px;">Line Documents</th>

                        </tr>
                    </thead>

                    <tbody>
                        @forelse($receipt->lines as $line)
                            <tr class="small">

                                {{-- ITEM --}}
                                <td class="fw-semibold">
                                    {{ $line->item->name ?? 'Item #' . $line->item_id }}
                                </td>

                                <td class="text-center">
                                    {{ ucfirst(str_replace('_', ' ', $line->material_category)) }}
                                </td>

                                <td class="text-center">{{ $line->grade ?? '-' }}</td>
                                <td class="text-center">{{ $line->thickness_mm ?? '-' }}</td>
                                <td class="text-center">{{ $line->width_mm ?? '-' }}</td>
                                <td class="text-center">{{ $line->length_mm ?? '-' }}</td>
                                <td class="text-center">{{ $line->section_profile ?? '-' }}</td>
                                <td class="text-center fw-semibold">{{ $line->qty_pcs }}</td>
                                <td class="text-center">{{ $line->received_weight_kg ?? '-' }}</td>
                                <td class="text-center">{{ $line->uom->name ?? '-' }}</td>

                                {{-- DOCUMENT COLUMN --}}
                                <td>

                                    {{-- Existing Files --}}
                                    @if($line->attachments->count())
                                        <div class="mb-2">
                                            @foreach($line->attachments as $att)
                                                <div
                                                    class="d-flex justify-content-between align-items-center border rounded px-2 py-1 mb-1 bg-light">

                                                    <div class="text-truncate" style="max-width:160px;">
                                                        <a href="{{ route('attachments.download', $att) }}"
                                                            class="text-decoration-none small">
                                                            {{ strtoupper($att->category ?? 'DOC') }}
                                                        </a>
                                                        <div class="text-muted small">
                                                            {{ $att->original_name }}
                                                        </div>
                                                    </div>

                                                    @can('store.material_receipt.delete')
                                                        <form action="{{ route('attachments.destroy', $att) }}" method="POST" class="ms-2">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                                ×
                                                            </button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted small mb-2">
                                            No documents
                                        </div>
                                    @endif

                                    {{-- Upload Button Toggle --}}
                                    @can('store.material_receipt.update')
                                        <button class="btn btn-sm btn-outline-primary w-100 mb-1" data-bs-toggle="collapse"
                                            data-bs-target="#upload-{{ $line->id }}">
                                            + Upload
                                        </button>

                                        <div class="collapse" id="upload-{{ $line->id }}">
                                            <form action="{{ route('material-receipt-lines.attachments.store', $line) }}"
                                                method="POST" enctype="multipart/form-data"
                                                class="border rounded p-2 bg-light small">
                                                @csrf

                                                <input type="file" name="file" class="form-control form-control-sm mb-2" required>

                                                <select name="category" class="form-select form-select-sm mb-2">
                                                    <option value="">Category</option>
                                                    <option value="mill_tc">Mill TC</option>
                                                    <option value="drawing">Drawing</option>
                                                    <option value="other">Other</option>
                                                </select>

                                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                                    Upload File
                                                </button>
                                            </form>
                                        </div>
                                    @endcan

                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-3">
                                    No line items recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

@endsection

