@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    @if (session('status'))
        <div class="alert alert-info small py-2 px-3 mb-3">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate class="auth-form">
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label small mb-1">Email</label>

            <div class="input-group input-group-lg">
                <span class="input-group-text">
                    <i class="bi bi-envelope"></i>
                </span>

                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="name@company.com"
                       required
                       autofocus
                       autocomplete="username">
            </div>

            @error('email')
                <div class="invalid-feedback d-block small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label small mb-1">Password</label>

            <div class="input-group input-group-lg">
                <span class="input-group-text">
                    <i class="bi bi-shield-lock"></i>
                </span>

                <input id="password"
                       type="password"
                       name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       placeholder="••••••••"
                       required
                       autocomplete="current-password">

                <button class="btn btn-outline-secondary"
                        type="button"
                        id="togglePassword"
                        aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>

            @error('password')
                <div class="invalid-feedback d-block small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Remember + forgot --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check form-switch small">
                <input class="form-check-input"
                       type="checkbox"
                       name="remember"
                       id="remember"
                       {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">
                    Remember me
                </label>
            </div>

            @if (Route::has('password.request'))
                <a class="small text-decoration-none" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-100 auth-btn">
            <i class="bi bi-box-arrow-in-right me-1"></i>
            Log in
        </button>

        <div class="text-center mt-3">
            <span class="text-body-secondary small">
                Having trouble? Contact your administrator.
            </span>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                const input = document.getElementById('password');
                const btn = document.getElementById('togglePassword');
                if (!input || !btn) return;

                btn.addEventListener('click', function () {
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';

                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.classList.remove(isHidden ? 'bi-eye' : 'bi-eye-slash');
                        icon.classList.add(isHidden ? 'bi-eye-slash' : 'bi-eye');
                    }

                    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                });
            })();
        </script>
    @endpush
@endsection