@extends('layouts.erp')

@section('title', 'Edit Reorder Level')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Reorder Level</h1>
        <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">Please correct the errors and try again.</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('store-reorder-levels.update', $level) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-6">
                    <label class="form-label">Item <span class="text-danger">*</span></label>
                    <select name="item_id" class="form-select" required>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}" @selected(old('item_id', $level->item_id) == $it->id)>
                                {{ $it->code ? ($it->code.' - ') : '' }}{{ $it->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Min/Target quantities are in the item UOM.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand', $level->brand) }}" class="form-control" placeholder="Leave blank for ANY">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Project Scope</label>
                    <select name="project_id" class="form-select">
                        <option value="NULL" @selected(old('project_id', $level->project_id ? (string)$level->project_id : 'NULL') === 'NULL')>GENERAL</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}" @selected(old('project_id', $level->project_id) == $p->id)>{{ $p->code }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Project level considers GENERAL+same project stock.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Min Qty <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0" name="min_qty" value="{{ old('min_qty', (float)$level->min_qty) }}" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Target Qty <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0" name="target_qty" value="{{ old('target_qty', (float)$level->target_qty) }}" class="form-control" required>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $level->is_active))>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="{{ route('store-reorder-levels.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection
