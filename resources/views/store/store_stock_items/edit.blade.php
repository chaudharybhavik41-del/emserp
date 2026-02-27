@extends('layouts.erp')

@section('title', 'Edit Stock Item #'.$stockItem->id)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Stock Item</h1>
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
                    <strong>Project:</strong><br>
                    @if($stockItem->project)
                        {{ $stockItem->project->code }} - {{ $stockItem->project->name }}
                    @else
                        -
                    @endif
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <strong>Grade:</strong><br>
                    {{ $stockItem->grade ?? '-' }}
                </div>
                <div class="col-md-3">
                    <strong>T × W × L (mm):</strong><br>
                    {{ $stockItem->thickness_mm ?? '-' }} ×
                    {{ $stockItem->width_mm ?? '-' }} ×
                    {{ $stockItem->length_mm ?? '-' }}
                </div>
                <div class="col-md-3">
                    <strong>Section:</strong><br>
                    {{ $stockItem->section_profile ?? '-' }}
                </div>
                <div class="col-md-3">
                    <strong>Available Qty / Wt:</strong><br>
                    {{ $stockItem->qty_pcs_available }} pcs,
                    {{ $stockItem->weight_kg_available ?? '-' }} kg
                </div>
            </div>

            <form action="{{ route('store-stock-items.update', $stockItem) }}" method="POST" class="mt-3">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Plate Number</label>
                        <input type="text" name="plate_number"
                               value="{{ old('plate_number', $stockItem->plate_number) }}"
                               class="form-control form-control-sm">
                        @error('plate_number')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Heat Number</label>
                        <input type="text" name="heat_number"
                               value="{{ old('heat_number', $stockItem->heat_number) }}"
                               class="form-control form-control-sm">
                        @error('heat_number')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">MTC Number</label>
                        <input type="text" name="mtc_number"
                               value="{{ old('mtc_number', $stockItem->mtc_number) }}"
                               class="form-control form-control-sm">
                        @error('mtc_number')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location"
                               value="{{ old('location', $stockItem->location) }}"
                               class="form-control form-control-sm">
                        @error('location')
                        <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" rows="3"
                              class="form-control form-control-sm">{{ old('remarks', $stockItem->remarks) }}</textarea>
                    @error('remarks')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mt-3 d-flex justify-content-between">
                    <a href="{{ route('store-stock-items.index') }}" class="btn btn-sm btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
