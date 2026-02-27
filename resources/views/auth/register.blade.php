@extends('layouts.auth')

@section('title', 'Register')

@section('content')
    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        {{-- Name --}}
        <div class="mb-3">
            <label for="name" class="form-label small mb-1">{{ __('Name') }}</label>
            <input id="name"
                   type="text"
                   name="name"
                   value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror"
                   required
                   autofocus
                   autocomplete="name">
            @error('name')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Email --}}
        <div class="mb-3">
            <label for="email" class="form-label small mb-1">{{ __('Email') }}</label>
            <input id="email"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   required
                   autocomplete="username">
            @error('email')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label small mb-1">{{ __('Password') }}</label>
            <input id="password"
                   type="password"
                   name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required
                   autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
            <div class="form-text small">
                {{ __('Use at least 8 characters.') }}
            </div>
        </div>

        {{-- Confirm Password --}}
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small mb-1">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation"
                   type="password"
                   name="password_confirmation"
                   class="form-control @error('password_confirmation') is-invalid @enderror"
                   required
                   autocomplete="new-password">
            @error('password_confirmation')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            {{ __('Register') }}
        </button>

        <p class="small text-muted text-center mb-0 mt-3">
            {{ __('Already registered?') }}
            <a href="{{ route('login') }}" class="text-decoration-none">{{ __('Log in') }}</a>
        </p>
    </form>
@endsection
