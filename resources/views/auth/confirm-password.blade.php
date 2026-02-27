@extends('layouts.auth')

@section('title', 'Confirm Password')

@section('content')
    <div class="mb-3 small text-muted">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    @if (session('status'))
        <div class="alert alert-info small py-2 px-3 mb-3">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf

        {{-- Password --}}
        <div class="mb-3">
            <label for="password" class="form-label small mb-1">{{ __('Password') }}</label>
            <input id="password"
                   type="password"
                   name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required
                   autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback small">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">
            {{ __('Confirm Password') }}
        </button>
    </form>
@endsection
