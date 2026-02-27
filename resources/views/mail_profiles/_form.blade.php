@php
    $isEdit = isset($profile) && $profile->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('mail-profiles.update', $profile) : route('mail-profiles.store') }}">
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
                   value="{{ old('code', $profile->code ?? '') }}"
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
                   value="{{ old('name', $profile->name ?? '') }}"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="company_id" class="form-label">Company</label>
            <select id="company_id"
                    name="company_id"
                    class="form-select @error('company_id') is-invalid @enderror">
                <option value="">-- Global --</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}"
                        {{ (string) old('company_id', $profile->company_id ?? '') === (string) $company->id ? 'selected' : '' }}>
                        {{ $company->code }} - {{ $company->name }}
                    </option>
                @endforeach
            </select>
            @error('company_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="department_id" class="form-label">Department</label>
            <select id="department_id"
                    name="department_id"
                    class="form-select @error('department_id') is-invalid @enderror">
                <option value="">-- None --</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}"
                        {{ (string) old('department_id', $profile->department_id ?? '') === (string) $dept->id ? 'selected' : '' }}>
                        {{ $dept->code }} - {{ $dept->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="from_name" class="form-label">From Name</label>
            <input type="text"
                   id="from_name"
                   name="from_name"
                   class="form-control @error('from_name') is-invalid @enderror"
                   value="{{ old('from_name', $profile->from_name ?? '') }}">
            @error('from_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="from_email" class="form-label">From Email <span class="text-danger">*</span></label>
            <input type="email"
                   id="from_email"
                   name="from_email"
                   class="form-control @error('from_email') is-invalid @enderror"
                   value="{{ old('from_email', $profile->from_email ?? '') }}"
                   required>
            @error('from_email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="reply_to" class="form-label">Reply-To</label>
            <input type="email"
                   id="reply_to"
                   name="reply_to"
                   class="form-control @error('reply_to') is-invalid @enderror"
                   value="{{ old('reply_to', $profile->reply_to ?? '') }}">
            @error('reply_to')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="smtp_host" class="form-label">SMTP Host <span class="text-danger">*</span></label>
            <input type="text"
                   id="smtp_host"
                   name="smtp_host"
                   class="form-control @error('smtp_host') is-invalid @enderror"
                   value="{{ old('smtp_host', $profile->smtp_host ?? '') }}"
                   required>
            @error('smtp_host')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2">
            <label for="smtp_port" class="form-label">Port <span class="text-danger">*</span></label>
            <input type="number"
                   id="smtp_port"
                   name="smtp_port"
                   class="form-control @error('smtp_port') is-invalid @enderror"
                   value="{{ old('smtp_port', $profile->smtp_port ?? 587) }}"
                   required>
            @error('smtp_port')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="smtp_encryption" class="form-label">Encryption</label>
            <select id="smtp_encryption"
                    name="smtp_encryption"
                    class="form-select @error('smtp_encryption') is-invalid @enderror">
                <option value="" {{ old('smtp_encryption', $profile->smtp_encryption ?? '') === '' ? 'selected' : '' }}>None</option>
                <option value="tls" {{ old('smtp_encryption', $profile->smtp_encryption ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                <option value="ssl" {{ old('smtp_encryption', $profile->smtp_encryption ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
            </select>
            @error('smtp_encryption')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="smtp_username" class="form-label">SMTP Username <span class="text-danger">*</span></label>
            <input type="text"
                   id="smtp_username"
                   name="smtp_username"
                   class="form-control @error('smtp_username') is-invalid @enderror"
                   value="{{ old('smtp_username', $profile->smtp_username ?? '') }}"
                   required>
            @error('smtp_username')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="smtp_password" class="form-label">
                SMTP Password
                @if($isEdit)
                    <small class="text-muted">(leave blank to keep existing)</small>
                @endif
            </label>
            <input type="password"
                   id="smtp_password"
                   name="smtp_password"
                   class="form-control @error('smtp_password') is-invalid @enderror">
            @error('smtp_password')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check me-3">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_default"
                       name="is_default"
                       value="1"
                       {{ old('is_default', $profile->is_default ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_default">
                    Default
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $profile->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('mail-profiles.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Profile' : 'Create Profile' }}
        </button>
    </div>
</form>
