<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>

    {{-- Theme bootstrapper (prevents flash). Uses Bootstrap 5.3 color modes via data-bs-theme --}}
    <script>
        (function () {
            try {
                var key = 'erp-theme';
                var stored = localStorage.getItem(key);
                var theme = (stored === 'dark' || stored === 'light')
                    ? stored
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>

    {{-- Bootstrap 5 CSS --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    {{-- ERP color mode compatibility (same file used in layouts/erp.blade.php) --}}
    <link rel="stylesheet" href="{{ asset('css/erp-color-modes.css') }}">

    {{-- App assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Auth page styling (no build required) --}}
    <style>
        .auth-body{
            position: relative;
            min-height: 100vh;
        }
        .auth-bg{
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(900px circle at 15% 15%, rgba(var(--bs-primary-rgb), .22), transparent 60%),
                radial-gradient(800px circle at 85% 20%, rgba(var(--bs-info-rgb), .18), transparent 55%),
                radial-gradient(900px circle at 50% 95%, rgba(var(--bs-success-rgb), .14), transparent 55%);
            background-color: var(--bs-body-bg);
        }

        .auth-shell{
            background: rgba(var(--bs-body-bg-rgb), .86);
            border: 1px solid rgba(var(--bs-body-color-rgb), .08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .auth-brand{
            color: #fff;
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(135deg,
                    rgba(var(--bs-primary-rgb), 1) 0%,
                    rgba(var(--bs-primary-rgb), .86) 45%,
                    rgba(var(--bs-info-rgb), .68) 100%);
        }
        .auth-brand::after{
            content: "";
            position: absolute;
            inset: -40%;
            transform: rotate(12deg);
            pointer-events: none;
            background:
                radial-gradient(circle at 30% 30%, rgba(255,255,255,.22), transparent 45%),
                radial-gradient(circle at 70% 60%, rgba(255,255,255,.14), transparent 45%);
        }
        .auth-brand > *{ position: relative; }

        .auth-form .input-group-text{
            background: transparent;
        }
        .auth-form .form-control:focus{
            box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .15);
        }

        .auth-btn{
            border-radius: .9rem;
            padding-top: .75rem;
            padding-bottom: .75rem;
        }

        @media (prefers-reduced-motion: reduce){
            .auth-shell{ backdrop-filter: none; -webkit-backdrop-filter: none; }
        }
    </style>

    @stack('styles')
</head>

<body class="auth-body d-flex align-items-center py-4 py-lg-5">
    <div class="auth-bg" aria-hidden="true"></div>

    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8 col-xl-7">
                <div class="auth-shell rounded-4 overflow-hidden shadow-lg">
                    <div class="row g-0">

                        {{-- Left brand panel (desktop only) --}}
                        <div class="col-lg-6 d-none d-lg-flex">
                            <div class="auth-brand p-5 w-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <img src="{{ asset('images/ems-logo.png') }}"
                                             alt="{{ config('app.name') }}"
                                             style="height: 36px; width: auto;">
                                        <div class="fw-semibold fs-5">
                                            {{ config('app.name') }}
                                        </div>
                                    </div>

                                    <h2 class="h4 mb-2">Welcome back</h2>
                                    <p class="text-white-50 mb-4">
                                        Sign in to manage materials, CRM, projects, HR and store operations.
                                    </p>

                                    <ul class="list-unstyled small mb-0">
                                        <li class="mb-2">
                                            <i class="bi bi-shield-check me-2"></i> Role-based access
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-speedometer2 me-2"></i> Unified dashboards
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-clipboard-data me-2"></i> Reports & approvals
                                        </li>
                                    </ul>
                                </div>

                                <div class="small text-white-50">
                                    <i class="bi bi-lock-fill me-1"></i> Secure authentication
                                </div>
                            </div>
                        </div>

                        {{-- Right form panel --}}
                        <div class="col-12 col-lg-6 bg-body">
                            <div class="p-4 p-sm-5">
                                <div class="text-center text-lg-start mb-4">
                                    {{-- Mobile logo --}}
                                    <div class="d-lg-none mb-3">
                                        <img src="{{ asset('images/ems-logo.png') }}"
                                             alt="{{ config('app.name') }}"
                                             style="height: 44px; width: auto;">
                                    </div>

                                    <div class="fw-semibold text-body mb-1">
                                        {{ config('app.name') }}
                                    </div>

                                    <h1 class="h4 mb-1">@yield('title', 'Sign in')</h1>
                                    <div class="text-body-secondary small">
                                        Use your work email & password to continue.
                                    </div>
                                </div>

                                {{-- Content from child view --}}
                                @yield('content')

                                <p class="text-center text-body-secondary small mt-4 mb-0">
                                    &copy; {{ date('Y') }} {{ config('app.name') }}
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bootstrap 5 JS bundle --}}
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
        defer
    ></script>

    @stack('scripts')
</body>
</html>