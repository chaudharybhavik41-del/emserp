@php
    $isEdit = isset($uom) && $uom->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('uoms.update', $uom) : route('uoms.store') }}">
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
                   value="{{ old('code', $uom->code ?? '') }}"
                   maxlength="20"
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
                   value="{{ old('name', $uom->name ?? '') }}"
                   maxlength="100"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="category" class="form-label">Category</label>
            <input type="text"
                   id="category"
                   name="category"
                   class="form-control @error('category') is-invalid @enderror"
                   value="{{ old('category', $uom->category ?? '') }}"
                   maxlength="50"
                   placeholder="length / weight / count / volume">
            @error('category')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="decimal_places" class="form-label">Decimal Places <span class="text-danger">*</span></label>
            <input type="number"
                   id="decimal_places"
                   name="decimal_places"
                   class="form-control @error('decimal_places') is-invalid @enderror"
                   value="{{ old('decimal_places', $uom->decimal_places ?? 3) }}"
                   min="0"
                   max="6"
                   required>
            @error('decimal_places')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $uom->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('uoms.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update UOM' : 'Create UOM' }}
        </button>
    </div>
</form>
