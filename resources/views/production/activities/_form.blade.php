@php
    $isEdit = isset($activity) && $activity->exists;
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $activity->code) }}" placeholder="CUTTING">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Unique short code (used in reporting and integrations).</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $activity->name) }}" placeholder="Cutting">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Applies To <span class="text-danger">*</span></label>
        <select name="applies_to" class="form-select @error('applies_to') is-invalid @enderror">
            @foreach($appliesToOptions as $k => $label)
                <option value="{{ $k }}" {{ old('applies_to', $activity->applies_to) === $k ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('applies_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Default Sequence</label>
        <input type="number" name="default_sequence" min="0" class="form-control @error('default_sequence') is-invalid @enderror"
               value="{{ old('default_sequence', $activity->default_sequence ?? 0) }}">
        @error('default_sequence')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Used as suggested order in routes.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Calculation Method <span class="text-danger">*</span></label>
        <select name="calculation_method" class="form-select @error('calculation_method') is-invalid @enderror">
            @foreach($calcOptions as $k => $label)
                <option value="{{ $k }}" {{ old('calculation_method', $activity->calculation_method) === $k ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('calculation_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Billing UOM</label>
        <select name="billing_uom_id" class="form-select @error('billing_uom_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($uoms as $u)
                <option value="{{ $u->id }}" {{ (string) old('billing_uom_id', $activity->billing_uom_id) === (string) $u->id ? 'selected' : '' }}>
                    {{ $u->code }} — {{ $u->name }}
                </option>
            @endforeach
        </select>
        @error('billing_uom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_fitupp" id="is_fitupp" value="1"
                       {{ old('is_fitupp', (bool) $activity->is_fitupp) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_fitupp">
                    Fitup activity (creates assembly & consumes parts)
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_machine" id="requires_machine" value="1"
                       {{ old('requires_machine', (bool) $activity->requires_machine) ? 'checked' : '' }}>
                <label class="form-check-label" for="requires_machine">
                    Requires machine selection in DPR
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requires_qc" id="requires_qc" value="1"
                       {{ old('requires_qc', (bool) $activity->requires_qc) ? 'checked' : '' }}>
                <label class="form-check-label" for="requires_qc">
                    Requires QC gate
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', $activity->exists ? (bool) $activity->is_active : true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>
</div>
