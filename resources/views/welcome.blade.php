<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'EMS Infra ERP') }}</title>

    {{-- Fonts (optional) --}}

    <link rel="preconnect" href="https://fonts.bunny.net">

    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- Styles / Scripts --}}

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))

        @vite(['resources/css/app.css', 'resources/js/app.js'])

    @else

        {{-- Simple Bootstrap fallback for dev environments --}}

        <link

            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"

            rel="stylesheet"

        >

        <style>

            body {

                font-family: "Instrument Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",

                    sans-serif;

            }

        </style>

    @endif

</head>

<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4 p-sm-5 text-center">

                    <div class="mb-3">

                        <div

                            class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-semibold"

                            style="width: 40px; height: 40px;"

                        >

                            {{ strtoupper(substr(config('app.name', 'EMS'), 0, 2)) }}

                        </div>

                    </div>

                    <h1 class="h4 mb-1">{{ config('app.name', 'EMS Infra ERP') }}</h1>

                    <p class="text-muted small mb-4">

                        Centralized ERP for materials, CRM, projects and store operations.

                    </p>

                    @if (Route::has('login'))

                        @auth

                            <p class="small text-muted mb-3">

                                You are logged in as <span class="fw-semibold">{{ auth()->user()->name }}</span>.

                            </p>

                            <a href="{{ route('dashboard') }}" class="btn btn-primary w-100 mb-2">

                                Go to Dashboard

                            </a>

                            @if (Route::has('logout'))

                                <form method="POST" action="{{ route('logout') }}" class="d-inline">

                                    @csrf

                                    <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted">

                                        Logout

                                    </button>

                                </form>

                            @endif

                        @else

                            <a href="{{ route('login') }}" class="btn btn-primary w-100 mb-2">

                                Log in

                            </a>

                            @if (Route::has('register'))

                                <a href="{{ route('register') }}" class="btn btn-outline-secondary w-100 mb-2">

                                    Register

                                </a>

                            @endif

                        @endauth

                    @endif

                    <hr class="my-4">

                    <div class="small text-muted">

                        <div class="mb-1 fw-semibold">Quick links</div>

                        <a href="https://laravel.com/docs" target="_blank" class="link-secondary me-2">

                            Laravel Docs

                        </a>

                        <span class="text-muted">·</span>

                        <a href="https://laracasts.com" target="_blank" class="link-secondary ms-2">

                            Laracasts

                        </a>

                    </div>

                </div>

            </div>

            <p class="text-center text-muted small mt-3 mb-0">

                &copy; {{ date('Y') }} {{ config('app.name', 'EMS Infra ERP') }}

            </p>

        </div>

    </div>

</div>

</body>

</html>