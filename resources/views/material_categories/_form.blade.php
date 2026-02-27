@php
    $isEdit = isset($category) && $category->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('material-categories.update', $category) : route('material-categories.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="material_type_id" class="form-label">Material Type <span class="text-danger">*</span></label>
            <select id="material_type_id"
                    name="material_type_id"
                    class="form-select @error('material_type_id') is-invalid @enderror"
                    required>
                <option value="">-- Select Type --</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}"
                        {{ (int) old('material_type_id', $category->material_type_id ?? 0) === $type->id ? 'selected' : '' }}>
                        {{ $type->code }} - {{ $type->name }}
                    </option>
                @endforeach
            </select>
            @error('material_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text"
                   id="code"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $category->code ?? '') }}"
                   maxlength="50"
                   required>
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text"
                   id="name"
                   name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $category->name ?? '') }}"
                   maxlength="150"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input type="number"
                   id="sort_order"
                   name="sort_order"
                   class="form-control @error('sort_order') is-invalid @enderror"
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                   min="0">
            @error('sort_order')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-check mt-2">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea id="description"
                  name="description"
                  rows="2"
                  class="form-control @error('description') is-invalid @enderror"
                  maxlength="500">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('material-categories.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Category' : 'Create Category' }}
        </button>
    </div>
</form>
