@extends('layouts.erp')

@section('title', 'GRN ' . ($receipt->receipt_number ?? ''))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            GRN Details
            @if($receipt->receipt_number)
                - {{ $receipt->receipt_number }}
            @endif
        </h1>
        <a href="{{ route('material-receipts.index') }}" class="btn btn-sm btn-outline-secondary">
            Back to list
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <strong>Receipt Date:</strong><br>
                    {{ optional($receipt->receipt_date)->format('d-m-Y') ?? '-' }}
                </div>
                <div class="col-md-3">
                    <strong>Type:</strong><br>
                    {{ $receipt->is_client_material ? 'Client Material' : 'Own Material' }}
                </div>
                <div class="col-md-3">
                    <strong>PO Number:</strong><br>
                    {{ $receipt->po_number ?? '-' }}
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
                <div class="col-md-4">
                    <strong>Vehicle No:</strong><br>
                    {{ $receipt->vehicle_number ?? '-' }}
                </div>
            </div>

            @if($receipt->remarks)
                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <strong>Remarks:</strong><br>
                        {{ $receipt->remarks }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0 h6">GRN Line Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 22%">Item</th>
                        <th style="width: 10%">Category</th>
                        <th style="width: 8%">Grade</th>
                        <th style="width: 10%">Brand</th>
                        <th style="width: 7%">T (mm)</th>
                        <th style="width: 7%">W (mm)</th>
                        <th style="width: 7%">L (mm)</th>
                        <th style="width: 10%">Section</th>
                        <th style="width: 7%">Qty (pcs)</th>
                        <th style="width: 10%">Recv Wt (kg)</th>
                        <th style="width: 8%">UOM</th>
                        <th style="width: 14%">Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($receipt->lines as $line)
                        <tr>
                            <td>
                                {{ $line->item->name ?? 'Item #'.$line->item_id }}
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $line->material_category)) }}</td>
                            <td>{{ $line->grade ?? '-' }}</td>
                            <td>{{ $line->brand ?? \'-\' }}</td>
                            <td>{{ $line->thickness_mm ?? '-' }}</td>
                            <td>{{ $line->width_mm ?? '-' }}</td>
                            <td>{{ $line->length_mm ?? '-' }}</td>
                            <td>{{ $line->section_profile ?? '-' }}</td>
                            <td>{{ $line->qty_pcs }}</td>
                            <td>{{ $line->received_weight_kg ?? '-' }}</td>
                            <td>{{ $line->uom->name ?? '-' }}</td>
                            <td>{{ $line->remarks ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-3">
                                No line items.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
