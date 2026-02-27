@php
    $isEdit = isset($company) && $company->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('companies.update', $company) : route('companies.store') }}">
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
                   value="{{ old('code', $company->code ?? '') }}"
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
                   value="{{ old('name', $company->name ?? '') }}"
                   maxlength="150"
                   required>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="legal_name" class="form-label">Legal Name</label>
            <input type="text"
                   id="legal_name"
                   name="legal_name"
                   class="form-control @error('legal_name') is-invalid @enderror"
                   value="{{ old('legal_name', $company->legal_name ?? '') }}"
                   maxlength="200">
            @error('legal_name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <label for="gst_number" class="form-label">GSTIN</label>
            <input type="text"
                   id="gst_number"
                   name="gst_number"
                   class="form-control @error('gst_number') is-invalid @enderror"
                   value="{{ old('gst_number', $company->gst_number ?? '') }}"
                   maxlength="20">
            @error('gst_number')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="pan" class="form-label">PAN</label>
            <input type="text"
                   id="pan"
                   name="pan"
                   class="form-control @error('pan') is-invalid @enderror"
                   value="{{ old('pan', $company->pan ?? '') }}"
                   maxlength="20">
            @error('pan')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="email" class="form-label">Email</label>
            <input type="email"
                   id="email"
                   name="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $company->email ?? '') }}"
                   maxlength="150">
            @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text"
                   id="phone"
                   name="phone"
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone', $company->phone ?? '') }}"
                   maxlength="50">
            @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="website" class="form-label">Website</label>
            <input type="text"
                   id="website"
                   name="website"
                   class="form-control @error('website') is-invalid @enderror"
                   value="{{ old('website', $company->website ?? '') }}"
                   maxlength="150">
            @error('website')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-8">
            <label for="address_line1" class="form-label">Address Line 1</label>
            <input type="text"
                   id="address_line1"
                   name="address_line1"
                   class="form-control @error('address_line1') is-invalid @enderror"
                   value="{{ old('address_line1', $company->address_line1 ?? '') }}"
                   maxlength="200">
            @error('address_line1')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-8">
            <label for="address_line2" class="form-label">Address Line 2</label>
            <input type="text"
                   id="address_line2"
                   name="address_line2"
                   class="form-control @error('address_line2') is-invalid @enderror"
                   value="{{ old('address_line2', $company->address_line2 ?? '') }}"
                   maxlength="200">
            @error('address_line2')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-4">
            <label for="city" class="form-label">City</label>
            <input type="text"
                   id="city"
                   name="city"
                   class="form-control @error('city') is-invalid @enderror"
                   value="{{ old('city', $company->city ?? '') }}"
                   maxlength="100">
            @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="state" class="form-label">State</label>
            <input type="text"
                   id="state"
                   name="state"
                   class="form-control @error('state') is-invalid @enderror"
                   value="{{ old('state', $company->state ?? '') }}"
                   maxlength="100">
            @error('state')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="pincode" class="form-label">PIN Code</label>
            <input type="text"
                   id="pincode"
                   name="pincode"
                   class="form-control @error('pincode') is-invalid @enderror"
                   value="{{ old('pincode', $company->pincode ?? '') }}"
                   maxlength="20">
            @error('pincode')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3">
            <label for="country" class="form-label">Country</label>
            <input type="text"
                   id="country"
                   name="country"
                   class="form-control @error('country') is-invalid @enderror"
                   value="{{ old('country', $company->country ?? 'India') }}"
                   maxlength="100">
            @error('country')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check me-3">
                <input class="form-check-input"
                       type="checkbox"
                       id="is_default"
                       name="is_default"
                       value="1"
                       {{ old('is_default', $company->is_default ?? false) ? 'checked' : '' }}>
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
                       {{ old('is_active', $company->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>

        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Update Company' : 'Create Company' }}
        </button>
    </div>
</form>
