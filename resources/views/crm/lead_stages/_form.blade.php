@php
    /** @var \App\Models\CrmLeadStage|null $lead_stage */
    $isEdit = isset($lead_stage) && $lead_stage->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('crm.lead-stages.update', $lead_stage) : route('crm.lead-stages.store') }}">
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
                   value="{{ old('code', $lead_stage->code ?? '') }}"
                   maxlength="50">
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-5">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text"
                   id="name"
                   name="name"
                   required
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $lead_stage->name ?? '') }}"
                   maxlength="200">
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
                   value="{{ old('sort_order', $lead_stage->sort_order ?? 0) }}">
            @error('sort_order')
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
                       {{ old('is_active', $lead_stage->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="form-check">
                <input type="checkbox"
                       id="is_won"
                       name="is_won"
                       value="1"
                       class="form-check-input"
                       {{ old('is_won', $lead_stage->is_won ?? false) ? 'checked' : '' }}>
                <label for="is_won" class="form-check-label">Won stage</label>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-check">
                <input type="checkbox"
                       id="is_lost"
                       name="is_lost"
                       value="1"
                       class="form-check-input"
                       {{ old('is_lost', $lead_stage->is_lost ?? false) ? 'checked' : '' }}>
                <label for="is_lost" class="form-check-label">Lost stage</label>
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-check">
                <input type="checkbox"
                       id="is_closed"
                       name="is_closed"
                       value="1"
                       class="form-check-input"
                       {{ old('is_closed', $lead_stage->is_closed ?? false) ? 'checked' : '' }}>
                <label for="is_closed" class="form-check-label">Closed stage</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('crm.lead-stages.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Lead Stage' : 'Create Lead Stage' }}
        </button>
    </div>
</form>
