@php
    /** @var \App\Models\Accounting\TdsSection $section */
    $isEdit = isset($section) && $section->exists;
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Section Code</label>
        <input type="text"
               name="code"
               class="form-control form-control-sm @error('code') is-invalid @enderror"
               value="{{ old('code', $section->code ?? '') }}"
               placeholder="e.g. 194C"
               maxlength="20"
               style="text-transform: uppercase;">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Use the exact Income Tax Act section code.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Name</label>
        <input type="text"
               name="name"
               class="form-control form-control-sm @error('name') is-invalid @enderror"
               value="{{ old('name', $section->name ?? '') }}"
               placeholder="Contractor / Professional / Rent..."
               maxlength="150">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">Default Rate %</label>
        <input type="number"
               step="0.0001"
               name="default_rate"
               class="form-control form-control-sm @error('default_rate') is-invalid @enderror"
               value="{{ old('default_rate', $section->default_rate ?? 0) }}">
        @error('default_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Example: 1, 2, 10, 0.1</div>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="is_active"
                   value="1"
                   id="is_active"
                   {{ old('is_active', $section->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>

    <div class="col-md-12">
        <label class="form-label">Description</label>
        <input type="text"
               name="description"
               class="form-control form-control-sm @error('description') is-invalid @enderror"
               value="{{ old('description', $section->description ?? '') }}"
               placeholder="Optional notes"
               maxlength="500">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-3 d-flex justify-content-between">
    <a href="{{ route('accounting.tds-sections.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
            {{ $isEdit ? 'Save Changes' : 'Create TDS Section' }}
        </button>
    </div>
</div>
