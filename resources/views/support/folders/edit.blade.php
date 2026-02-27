@extends('layouts.erp')

@section('title', 'Support - Edit Folder')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Edit Folder</h4>
        <div class="text-muted">Update folder details</div>
    </div>
    <a href="{{ route('support.folders.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

@include('partials.alerts')

<div class="card">
    <div class="card-body">
        <form action="{{ route('support.folders.update', $folder) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Parent Folder (optional)</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- None (root) --</option>
                        @foreach($folders as $f)
                            <option value="{{ $f['id'] }}" @selected(old('parent_id', $folder->parent_id) == $f['id'])>
                                {{ str_repeat('— ', (int) ($f['depth'] ?? 0)) }}{{ $f['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Folder Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $folder->name) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $folder->sort_order) }}" min="0">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" @selected(old('is_active', (int)$folder->is_active) == 1)>Active</option>
                        <option value="0" @selected(old('is_active', (int)$folder->is_active) == 0)>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Description (optional)</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $folder->description) }}</textarea>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Folder
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
