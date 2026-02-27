@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="mb-3 small text-muted">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link.') }}
    </div>

    @if (session('status'))
        <div class="alert alert-success small py-2 px-3 mb-3">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label small mb-1">{{ __('Email') }}</label>
            <input id="email"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   required
                   autofocus>
            @error('email')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            {{ __('Email Password Reset Link') }}
        </button>

        <div class="text-center mt-3">
            <a href="{{ route('login') }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left-short"></i> {{ __('Back to login') }}
            </a>
        </div>
    </form>
@endsection
