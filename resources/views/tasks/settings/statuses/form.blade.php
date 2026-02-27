@extends('layouts.erp')

@section('title', isset($taskStatus) ? 'Edit Status' : 'Create Status')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-{{ isset($taskStatus) ? 'pencil' : 'plus-circle' }} me-2"></i>
                        {{ isset($taskStatus) ? 'Edit Status: ' . $taskStatus->name : 'Create Task Status' }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($taskStatus) ? route('task-settings.statuses.update', $taskStatus) : route('task-settings.statuses.store') }}" method="POST">
                        @csrf
                        @if(isset($taskStatus)) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $taskStatus->name ?? '') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="not_started" {{ old('category', $taskStatus->category ?? '') === 'not_started' ? 'selected' : '' }}>Not Started</option>
                                <option value="in_progress" {{ old('category', $taskStatus->category ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="completed" {{ old('category', $taskStatus->category ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="cancelled" {{ old('category', $taskStatus->category ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Color <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="color" name="color" class="form-control form-control-color" 
                                       value="{{ old('color', $taskStatus->color ?? '#6c757d') }}" style="width: 60px;">
                                <input type="text" class="form-control" id="colorText" 
                                       value="{{ old('color', $taskStatus->color ?? '#6c757d') }}" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Icon (Bootstrap Icons class)</label>
                            <input type="text" name="icon" class="form-control" 
                                   value="{{ old('icon', $taskStatus->icon ?? '') }}" placeholder="e.g., bi-circle-fill">
                            <small class="text-muted">Browse icons at <a href="https://icons.getbootstrap.com/" target="_blank">icons.getbootstrap.com</a></small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" 
                                           id="isDefault" {{ old('is_default', $taskStatus->is_default ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isDefault">
                                        Set as Default Status
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_closed" value="1" 
                                           id="isClosed" {{ old('is_closed', $taskStatus->is_closed ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isClosed">
                                        Mark as Closed Status
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check me-1"></i> {{ isset($taskStatus) ? 'Update' : 'Create' }}
                            </button>
                            <a href="{{ route('task-settings.statuses.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelector('input[type="color"]').addEventListener('input', function() {
    document.getElementById('colorText').value = this.value;
});
</script>
@endpush
@endsection
