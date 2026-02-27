@php
    /** @var \App\Models\MaterialSubcategory|null $subcategory */
    $isEdit = isset($subcategory) && $subcategory->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('material-subcategories.update', $subcategory) : route('material-subcategories.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="material_category_id" class="form-label">
                Category <span class="text-danger">*</span>
            </label>
            <select name="material_category_id"
                    id="material_category_id"
                    class="form-select form-select-sm @error('material_category_id') is-invalid @enderror">
                <option value="">-- Select Category --</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        {{ (int) old('material_category_id', $subcategory->material_category_id ?? 0) === $category->id ? 'selected' : '' }}>
                        {{ $category->type->code ?? '' }} - {{ $category->code }} - {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('material_category_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror>
        </div>

        <div class="col-md-4">
            <label for="name" class="form-label">
                Subcategory Name <span class="text-danger">*</span>
            </label>
            <input type="text"
                   name="name"
                   id="name"
                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                   value="{{ old('name', $subcategory->name ?? '') }}"
                   maxlength="150">
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2">
            <label for="sort_order" class="form-label">Sort Order</label>
            <input type="number"
                   name="sort_order"
                   id="sort_order"
                   class="form-control form-control-sm @error('sort_order') is-invalid @enderror"
                   value="{{ old('sort_order', $subcategory->sort_order ?? 0) }}"
                   min="0"
                   max="65535">
            @error('sort_order')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox"
                       name="is_active"
                       id="is_active"
                       class="form-check-input"
                       value="1"
                       {{ old('is_active', $subcategory->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    {{-- Auto-generated subcategory code - read-only --}}
    <div class="mb-3">
        @if($isEdit)
            <input type="hidden" name="code" value="{{ $subcategory->code }}">
            <label class="form-label">Subcategory Code</label>
            <input type="text"
                   class="form-control form-control-sm"
                   value="{{ $subcategory->code }}"
                   disabled>
            <div class="form-text">Code is generated automatically and cannot be edited.</div>
        @else
            <label class="form-label">Subcategory Code</label>
            <input type="text"
                   class="form-control form-control-sm"
                   value="Will be generated automatically"
                   disabled>
            <div class="form-text">Code will be generated when you save.</div>
        @endif
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea name="description"
                  id="description"
                  class="form-control form-control-sm @error('description') is-invalid @enderror"
                  rows="2">{{ old('description', $subcategory->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('material-subcategories.index') }}" class="btn btn-secondary btn-sm">
            Cancel
        </a>
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $isEdit ? 'Update Subcategory' : 'Create Subcategory' }}
        </button>
    </div>
</form>
