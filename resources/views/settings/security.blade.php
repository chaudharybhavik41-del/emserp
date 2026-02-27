@extends('layouts.erp')

@section('title', 'Security Settings')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Security Settings</h1>
            </div>

            <form action="{{ route('settings.security.update') }}" method="POST">
                @csrf

                {{-- Password Policy --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-key me-2"></i>Password Policy
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                <input type="number" class="form-control @error('password_min_length') is-invalid @enderror" 
                                       id="password_min_length" name="password_min_length" 
                                       value="{{ old('password_min_length', $settings['password_min_length'] ?? 8) }}"
                                       min="6" max="32">
                                @error('password_min_length')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="password_history_count" class="form-label">
                                    Password History Count
                                    <i class="bi bi-info-circle" title="Number of previous passwords to remember"></i>
                                </label>
                                <input type="number" class="form-control @error('password_history_count') is-invalid @enderror" 
                                       id="password_history_count" name="password_history_count" 
                                       value="{{ old('password_history_count', $settings['password_history_count'] ?? 5) }}"
                                       min="0" max="24">
                                @error('password_history_count')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Set to 0 to disable password history check</small>
                            </div>

                            <div class="col-md-6">
                                <label for="password_expiry_days" class="form-label">Password Expiry Days</label>
                                <input type="number" class="form-control @error('password_expiry_days') is-invalid @enderror" 
                                       id="password_expiry_days" name="password_expiry_days" 
                                       value="{{ old('password_expiry_days', $settings['password_expiry_days'] ?? 0) }}"
                                       min="0" max="365">
                                @error('password_expiry_days')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Set to 0 to disable password expiry</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Password Requirements</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="password_require_uppercase" name="password_require_uppercase" value="true"
                                                   {{ ($settings['password_require_uppercase'] ?? 'true') === 'true' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="password_require_uppercase">
                                                Require uppercase letter
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="password_require_number" name="password_require_number" value="true"
                                                   {{ ($settings['password_require_number'] ?? 'true') === 'true' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="password_require_number">
                                                Require number
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="password_require_special" name="password_require_special" value="true"
                                                   {{ ($settings['password_require_special'] ?? 'false') === 'true' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="password_require_special">
                                                Require special character
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Login Security --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-lock me-2"></i>Login Security
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                <input type="number" class="form-control @error('max_login_attempts') is-invalid @enderror" 
                                       id="max_login_attempts" name="max_login_attempts" 
                                       value="{{ old('max_login_attempts', $settings['max_login_attempts'] ?? 5) }}"
                                       min="3" max="10">
                                @error('max_login_attempts')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Number of failed attempts before lockout</small>
                            </div>

                            <div class="col-md-6">
                                <label for="lockout_duration_minutes" class="form-label">Lockout Duration (minutes)</label>
                                <input type="number" class="form-control @error('lockout_duration_minutes') is-invalid @enderror" 
                                       id="lockout_duration_minutes" name="lockout_duration_minutes" 
                                       value="{{ old('lockout_duration_minutes', $settings['lockout_duration_minutes'] ?? 30) }}"
                                       min="5" max="1440">
                                @error('lockout_duration_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Session Settings --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Session Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="session_timeout_minutes" class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control @error('session_timeout_minutes') is-invalid @enderror" 
                                       id="session_timeout_minutes" name="session_timeout_minutes" 
                                       value="{{ old('session_timeout_minutes', $settings['session_timeout_minutes'] ?? 120) }}"
                                       min="5" max="1440">
                                @error('session_timeout_minutes')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Multiple Sessions</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" 
                                           id="allow_multiple_sessions" name="allow_multiple_sessions" value="true"
                                           {{ ($settings['allow_multiple_sessions'] ?? 'true') === 'true' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="allow_multiple_sessions">
                                        Allow users to login from multiple devices
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
