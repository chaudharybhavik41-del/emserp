@csrf

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text"
               name="code"
               value="{{ old('code', $accountType->code) }}"
               class="form-control @error('code') is-invalid @enderror"
               {{ ($accountType->exists && $accountType->is_system) ? 'readonly' : '' }}
               placeholder="e.g. bank_od">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Lowercase letters, numbers, underscore only.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text"
               name="name"
               value="{{ old('name', $accountType->name) }}"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="e.g. Bank Overdraft">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number"
               name="sort_order"
               value="{{ old('sort_order', $accountType->sort_order ?? 0) }}"
               class="form-control @error('sort_order') is-invalid @enderror"
               min="0" step="1">
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="is_active"
                   value="1"
                   id="is_active"
                   {{ old('is_active', $accountType->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">
                Active
            </label>
        </div>
        @error('is_active')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-text">
            <strong>System:</strong> {{ $accountType->is_system ? 'Yes' : 'No' }}
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        {{ $submitLabel ?? 'Save' }}
    </button>
    <a href="{{ route('accounting.account-types.index') }}" class="btn btn-outline-secondary">
        Cancel
    </a>
</div>
