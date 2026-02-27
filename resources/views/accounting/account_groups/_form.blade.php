@csrf

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text"
               name="code"
               value="{{ old('code', $group->code) }}"
               class="form-control @error('code') is-invalid @enderror"
               {{ ($group->exists && $group->is_primary) ? 'readonly' : '' }}
               placeholder="e.g. FIXED_ASSET">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Stored in UPPERCASE.</div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text"
               name="name"
               value="{{ old('name', $group->name) }}"
               class="form-control @error('name') is-invalid @enderror"
               placeholder="e.g. Fixed Assets">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Parent Group</label>
        <select name="parent_id"
                class="form-select @error('parent_id') is-invalid @enderror"
                {{ ($group->exists && $group->is_primary) ? 'disabled' : '' }}>
            <option value="">— None —</option>
            @foreach($parentOptions as $id => $label)
                <option value="{{ $id }}" {{ (string) old('parent_id', $group->parent_id) === (string) $id ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Selecting a parent makes this a sub-group.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Nature <span class="text-danger">*</span></label>
        <select name="nature"
                class="form-select @error('nature') is-invalid @enderror"
                {{ ($group->exists && $group->is_primary) ? 'disabled' : '' }}>
            @php $natureVal = old('nature', $group->nature); @endphp
            <option value="asset" {{ $natureVal === 'asset' ? 'selected' : '' }}>Asset</option>
            <option value="liability" {{ $natureVal === 'liability' ? 'selected' : '' }}>Liability</option>
            <option value="equity" {{ $natureVal === 'equity' ? 'selected' : '' }}>Equity</option>
            <option value="income" {{ $natureVal === 'income' ? 'selected' : '' }}>Income</option>
            <option value="expense" {{ $natureVal === 'expense' ? 'selected' : '' }}>Expense</option>
        </select>
        @error('nature')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">If a parent is selected, nature will be inherited from the parent.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number"
               name="sort_order"
               value="{{ old('sort_order', $group->sort_order ?? 0) }}"
               class="form-control @error('sort_order') is-invalid @enderror"
               min="0" step="1">
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="is_primary"
                   value="1"
                   id="is_primary"
                   {{ old('is_primary', $group->is_primary) ? 'checked' : '' }}
                   {{ ($group->exists && $group->is_primary) ? 'disabled' : '' }}>
            <label class="form-check-label" for="is_primary">
                Primary Group
            </label>
            <div class="form-text">Primary groups cannot have a parent.</div>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        {{ $submitLabel ?? 'Save' }}
    </button>
    <a href="{{ route('accounting.account-groups.index') }}" class="btn btn-outline-secondary">
        Cancel
    </a>
</div>
