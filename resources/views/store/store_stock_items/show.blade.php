@extends('layouts.erp')

@section('title', 'Stock Item #'.$stockItem->id)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Stock Item Details</h1>
        <a href="{{ route('store-stock-items.index') }}" class="btn btn-sm btn-outline-secondary">
            Back to Stock
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>Item:</strong><br>
                    {{ $stockItem->item->name ?? 'Item #'.$stockItem->item_id }}
                </div>
                <div class="col-md-4">
                    <strong>Category:</strong><br>
                    {{ ucfirst(str_replace('_', ' ', $stockItem->material_category)) }}
                </div>
                <div class="col-md-4">
                    <strong>Type:</strong><br>
                    {{ $stockItem->is_client_material ? 'Client Material' : 'Own Material' }}
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>Project:</strong><br>
                    @if($stockItem->project)
                        {{ $stockItem->project->code }} - {{ $stockItem->project->name }}
                    @else
                        -
                    @endif
                </div>
                <div class="col-md-4">
                    <strong>Grade:</strong><br>
                    {{ $stockItem->grade ?? '-' }}
                </div>
                <div class="col-md-4">
                    <strong>Status:</strong><br>
                    <span class="badge bg-secondary">{{ strtoupper($stockItem->status) }}</span>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>T × W × L (mm):</strong><br>
                    {{ $stockItem->thickness_mm ?? '-' }} ×
                    {{ $stockItem->width_mm ?? '-' }} ×
                    {{ $stockItem->length_mm ?? '-' }}
                </div>
                <div class="col-md-4">
                    <strong>Section:</strong><br>
                    {{ $stockItem->section_profile ?? '-' }}
                </div>
                <div class="col-md-4">
                    <strong>Available Qty / Wt:</strong><br>
                    {{ $stockItem->qty_pcs_available }} pcs,
                    {{ $stockItem->weight_kg_available ?? '-' }} kg
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>Plate Number:</strong><br>
                    {{ $stockItem->plate_number ?? '-' }}
                </div>
                <div class="col-md-4">
                    <strong>Heat Number:</strong><br>
                    {{ $stockItem->heat_number ?? '-' }}
                </div>
                <div class="col-md-4">
                    <strong>MTC Number:</strong><br>
                    {{ $stockItem->mtc_number ?? '-' }}
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>Location:</strong><br>
                    {{ $stockItem->location ?? '-' }}
                </div>
                <div class="col-md-8">
                    <strong>Remarks:</strong><br>
                    {{ $stockItem->remarks ?? '-' }}
                </div>
            </div>
        </div>
    </div>
@endsection
