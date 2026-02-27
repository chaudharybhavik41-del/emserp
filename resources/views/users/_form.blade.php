<form action="{{ isset($user->id) ? route('users.update', $user) : route('users.store') }}" 
      method="POST" enctype="multipart/form-data">
    @csrf
    @if(isset($user->id))
        @method('PUT')
    @endif

    {{-- Basic Information --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Basic Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="employee_code" class="form-label">Employee Code</label>
                    <input type="text" class="form-control @error('employee_code') is-invalid @enderror" 
                           id="employee_code" name="employee_code" 
                           value="{{ old('employee_code', $user->employee_code) }}">
                    @error('employee_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                           id="name" name="name" required
                           value="{{ old('name', $user->name) }}">
                    @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                           id="email" name="email" required
                           value="{{ old('email', $user->email) }}">
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                           id="phone" name="phone"
                           value="{{ old('phone', $user->phone) }}">
                    @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="designation" class="form-label">Designation</label>
                    <input type="text" class="form-control @error('designation') is-invalid @enderror" 
                           id="designation" name="designation"
                           value="{{ old('designation', $user->designation) }}"
                           placeholder="e.g., Manager, Engineer, Accountant">
                    @error('designation')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Profile Photo --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Profile Photo</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-auto">
                    @if(isset($user->id) && $user->profile_photo)
                        <img src="{{ Storage::url($user->profile_photo) }}" 
                             class="rounded-circle" width="80" height="80" alt="Current photo" id="preview">
                    @else
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; font-size: 24px;" id="preview-placeholder">
                            {{ $user->initials ?? 'U' }}
                        </div>
                        <img src="" class="rounded-circle d-none" width="80" height="80" alt="Preview" id="preview">
                    @endif
                </div>
                <div class="col">
                    <input type="file" class="form-control @error('profile_photo') is-invalid @enderror" 
                           id="profile_photo" name="profile_photo" accept="image/*">
                    @error('profile_photo')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Max 2MB. Recommended: 200x200 pixels.</small>
                    
                    @if(isset($user->id) && $user->profile_photo)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                        <label class="form-check-label" for="remove_photo">Remove current photo</label>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Password --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                Password
                @if(isset($user->id))
                    <small class="text-muted fw-normal">(Leave blank to keep current)</small>
                @endif
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">
                        Password 
                        @if(!isset($user->id))
                            <span class="text-danger">*</span>
                        @endif
                    </label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                           id="password" name="password" {{ !isset($user->id) ? 'required' : '' }}>
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">
                        Min {{ setting('password_min_length', 8) }} characters
                        @if(setting('password_require_uppercase', true))
                            , with uppercase
                        @endif
                        @if(setting('password_require_number', true))
                            , with number
                        @endif
                        @if(setting('password_require_special', false))
                            , with special character
                        @endif
                    </small>
                </div>

                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">
                        Confirm Password
                        @if(!isset($user->id))
                            <span class="text-danger">*</span>
                        @endif
                    </label>
                    <input type="password" class="form-control" 
                           id="password_confirmation" name="password_confirmation"
                           {{ !isset($user->id) ? 'required' : '' }}>
                </div>
            </div>
        </div>
    </div>

    {{-- Roles --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Roles</h5>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach($roles as $role)
                <div class="col-md-4 col-sm-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               id="role_{{ $role->id }}" name="roles[]" value="{{ $role->id }}"
                               {{ in_array($role->id, old('roles', $userRoleIds ?? [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="role_{{ $role->id }}">
                            {{ ucfirst($role->name) }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Departments --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Departments</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Assigned Departments</label>
                    <div class="row">
                        @foreach($departments as $dept)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input dept-checkbox" type="checkbox" 
                                       id="dept_{{ $dept->id }}" name="departments[]" value="{{ $dept->id }}"
                                       {{ in_array($dept->id, old('departments', $userDepartmentIds ?? [])) ? 'checked' : '' }}>
                                <label class="form-check-label" for="dept_{{ $dept->id }}">
                                    {{ $dept->name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="primary_department_id" class="form-label">Primary Department</label>
                    <select class="form-select @error('primary_department_id') is-invalid @enderror" 
                            id="primary_department_id" name="primary_department_id">
                        <option value="">-- Select Primary --</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" 
                                {{ old('primary_department_id', $primaryDepartmentId ?? '') == $dept->id ? 'selected' : '' }}
                                class="primary-option" data-dept="{{ $dept->id }}">
                            {{ $dept->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('primary_department_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            {{ isset($user->id) ? 'Update User' : 'Create User' }}
        </button>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
// Profile photo preview
document.getElementById('profile_photo')?.addEventListener('change', function(e) {
    const preview = document.getElementById('preview');
    const placeholder = document.getElementById('preview-placeholder');
    
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.classList.add('d-none');
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Filter primary department options based on selected departments
document.querySelectorAll('.dept-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', updatePrimaryOptions);
});

function updatePrimaryOptions() {
    const selectedDepts = Array.from(document.querySelectorAll('.dept-checkbox:checked')).map(cb => cb.value);
    const primarySelect = document.getElementById('primary_department_id');
    
    if (!primarySelect) return;
    
    primarySelect.querySelectorAll('option').forEach(function(option) {
        if (option.value === '') return;
        
        if (selectedDepts.includes(option.value)) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
            if (option.selected) {
                primarySelect.value = '';
            }
        }
    });
}

// Initial call
updatePrimaryOptions();
</script>
@endpush
