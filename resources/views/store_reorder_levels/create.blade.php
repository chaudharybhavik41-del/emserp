@extends('layouts.erp')

@section('title', 'Add Reorder Level')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Add Reorder Level</h1>
        <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">Please correct the errors and try again.</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('store-reorder-levels.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label class="form-label">Item <span class="text-danger">*</span></label>
                    <select name="item_id" class="form-select" required>
                        <option value="">Select item</option>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}" @selected(old('item_id') == $it->id)>
                                {{ $it->code ? ($it->code.' - ') : '' }}{{ $it->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Min/Target quantities are in the item UOM.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand') }}" class="form-control" placeholder="Leave blank for ANY">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Project Scope</label>
                    <select name="project_id" class="form-select">
                        <option value="NULL" @selected(old('project_id','NULL') === 'NULL')>GENERAL</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->code }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Project level considers GENERAL+same project stock.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Min Qty <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0" name="min_qty" value="{{ old('min_qty', 0) }}" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Target Qty <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0" name="target_qty" value="{{ old('target_qty', 0) }}" class="form-control" required>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', 1))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
