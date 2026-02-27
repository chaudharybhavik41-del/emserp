@php
    $isEdit = isset($template) && $template->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('mail-templates.update', $template) : route('mail-templates.store') }}">
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
                   value="{{ old('code', $template->code ?? '') }}"
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
                   value="{{ old('name', $template->name ?? '') }}"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="type" class="form-label">Type</label>
            <input type="text"
                   id="type"
                   name="type"
                   class="form-control @error('type') is-invalid @enderror"
                   value="{{ old('type', $template->type ?? 'general') }}"
                   placeholder="e.g. general, system, purchase">
            @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="mail_profile_id" class="form-label">Default Mail Profile</label>
            <select id="mail_profile_id"
                    name="mail_profile_id"
                    class="form-select @error('mail_profile_id') is-invalid @enderror">
                <option value="">-- Use default profile resolver --</option>
                @foreach($profiles as $profile)
                    <option value="{{ $profile->id }}"
                        {{ (string) old('mail_profile_id', $template->mail_profile_id ?? '') === (string) $profile->id ? 'selected' : '' }}>
                        {{ $profile->code }} - {{ $profile->name }}
                    </option>
                @endforeach
            </select>
            @error('mail_profile_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
        <input type="text"
               id="subject"
               name="subject"
               class="form-control @error('subject') is-invalid @enderror"
               value="{{ old('subject', $template->subject ?? '') }}"
               required>
        @error('subject')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="body" class="form-label">
            Body <span class="text-danger">*</span>
            <small class="text-muted">(You can use placeholders like {{ '{name}' }}, {{ '{project_code}' }} later)</small>
        </label>
        <textarea id="body"
                  name="body"
                  rows="8"
                  class="form-control @error('body') is-invalid @enderror"
                  required>{{ old('body', $template->body ?? '') }}</textarea>
        @error('body')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('mail-templates.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Template' : 'Create Template' }}
        </button>
    </div>
</form>
