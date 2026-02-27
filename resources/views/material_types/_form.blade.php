@php
    $isEdit = isset($type) && $type->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('material-types.update', $type) : route('material-types.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text"
                   id="code"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $type->code ?? '') }}"
                   maxlength="50"
                   required>
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-5">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text"
                   id="name"
                   name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $type->name ?? '') }}"
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
                   value="{{ old('sort_order', $type->sort_order ?? 0) }}"
                   min="0">
            @error('sort_order')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $type->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="accounting_usage" class="form-label">
                Accounting Usage <span class="text-danger">*</span>
            </label>
            <select id="accounting_usage"
                    name="accounting_usage"
                    class="form-select @error('accounting_usage') is-invalid @enderror"
                    required>
                @php
                    $usage = old('accounting_usage', $type->accounting_usage ?? 'inventory');
                @endphp
                <option value="inventory" {{ $usage === 'inventory' ? 'selected' : '' }}>Inventory (goes to stock)</option>
                <option value="expense" {{ $usage === 'expense' ? 'selected' : '' }}>Expense (OPEX / direct expense)</option>
                <option value="fixed_asset" {{ $usage === 'fixed_asset' ? 'selected' : '' }}>Fixed Asset (CAPEX)</option>
                <option value="mixed" {{ $usage === 'mixed' ? 'selected' : '' }}>Mixed (decided per document)</option>
            </select>
            @error('accounting_usage')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea id="description"
                  name="description"
                  rows="2"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $type->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('material-types.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Type' : 'Create Type' }}
        </button>
    </div>
</form>
