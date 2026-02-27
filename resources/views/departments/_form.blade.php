@php
    $isEdit = isset($department) && $department->exists;
    $selectedUserIds = old('user_ids', $department->users->pluck('id')->all() ?? []);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('departments.update', $department) : route('departments.store') }}">
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
                   value="{{ old('code', $department->code ?? '') }}"
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
                   value="{{ old('name', $department->name ?? '') }}"
                   maxlength="150"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $department->is_active ?? true) ? 'checked' : '' }}>
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
                  maxlength="500">{{ old('description', $department->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="user_ids" class="form-label">Assigned Users</label>
        <select id="user_ids"
                name="user_ids[]"
                class="form-select @error('user_ids') is-invalid @enderror"
                multiple
                size="6">
            @foreach($users as $user)
                <option value="{{ $user->id }}"
                    {{ in_array($user->id, $selectedUserIds) ? 'selected' : '' }}>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
        <div class="form-text">
            Hold Ctrl (Windows) or ⌘ (Mac) to select multiple users.
        </div>
        @error('user_ids')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @error('user_ids.*')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('departments.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Department' : 'Create Department' }}
        </button>
    </div>
</form>
