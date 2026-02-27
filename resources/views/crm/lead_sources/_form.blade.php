@php
    /** @var \App\Models\CrmLeadSource|null $lead_source */
    $isEdit = isset($lead_source) && $lead_source->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('crm.lead-sources.update', $lead_source) : route('crm.lead-sources.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="code" class="form-label">
                Code
                <span class="text-muted small">(leave blank for auto)</span>
            </label>
            <input type="text"
                   id="code"
                   name="code"
                   class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $lead_source->code ?? '') }}"
                   maxlength="50">
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-7">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text"
                   id="name"
                   name="name"
                   required
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $lead_source->name ?? '') }}"
                   maxlength="200">
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2">
            <div class="form-check mt-4 pt-2">
                <input type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       class="form-check-input"
                       {{ old('is_active', $lead_source->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description / Notes</label>
        <textarea id="description"
                  name="description"
                  rows="3"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $lead_source->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('crm.lead-sources.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Lead Source' : 'Create Lead Source' }}
        </button>
    </div>
</form>
