<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/x-icon" href="{{ asset('ems-favicon.ico') }}">

    <title  class="sdd">
        @hasSection('title')
            @yield('title') - {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>

    <style></style>
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

  
  	<link rel="preconnect" href="https://fonts.bunny.net">
	<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    {{-- App assets (Tailwind, custom styles, etc.) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- ERP dark mode + UI compatibility overrides (no build required) --}}
    <link rel="stylesheet" href="{{ asset('css/erp-color-modes.css') }}">

    {{-- Per-page extra styles --}}
    <style>/* Sidebar default width */
.erp-sidebar-wrapper {
    width: 260px;
    transition: all 0.3s ease;
}

/* Hide sidebar on desktop */
.sidebar-collapsed .erp-sidebar-wrapper {
    margin-left: -260px;
}

/* Smooth main expand */
.erp-main-scroll {
    transition: all 0.3s ease;
}


</style>
    @stack('styles')
</head>
<body class="bg-body-tertiary">
{{-- 
    FIXED LAYOUT: 
    - Header is fixed at top
    - Sidebar scrolls independently 
    - Main content scrolls independently
    - No coupled scrolling between sidebar and content
--}}
<div class="d-flex flex-column vh-100 overflow-hidden">

    {{-- Top bar (fixed height, never scrolls) --}}
    <header class="border-bottom bg-body erp-header flex-shrink-0">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between py-2">

                {{-- Left: mobile menu + logo + breadcrumb --}}
                <div class="d-flex align-items-center gap-2">

                    {{-- Mobile sidebar toggle --}}
                    {{-- <button class="btn btn-outline-secondary btn-sm d-inline-flex d-md-none me-1"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#sidebarOffcanvas"
                            aria-controls="sidebarOffcanvas"
                            aria-label="Toggle navigation">
                        <i class="bi bi-list"></i>
                    </button> --}}
<button class="btn btn-outline-secondary btn-sm d-inline-flex d-md-none me-1"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#sidebarOffcanvas"
        aria-controls="sidebarOffcanvas"
        aria-label="Toggle navigation">
    <i class="bi bi-list"></i>
</button>
<button class="btn btn-sm erp-hamburger-btn me-2 d-none d-lg-inline-flex" type="button" id="sidebarToggle"
    aria-label="Toggle navigation">
    <i class="bi bi-list"></i>
</button>



                    {{-- Logo + app name --}}
                    <a href="{{ route('dashboard') }}"
                       class="text-decoration-none d-flex align-items-center gap-2">
                        <img src="{{ asset('images/ems-logo.png') }}"
                             alt="{{ config('app.name') }}"
                             style="height: 28px; width: auto;">
                        <span class="fw-semibold text-body small d-none d-sm-inline">
                            {{ config('app.name') }}
                        </span>
                    </a>

                    {{-- Optional breadcrumb --}}
                    @hasSection('breadcrumb')
                        <div class="ms-3 small text-body-secondary d-none d-md-block">
                            @yield('breadcrumb')
                        </div>
                    @endif
                </div>

                {{-- Right: per-page content + notifications + theme + user menu --}}
                <div class="d-flex align-items-center gap-2">
                    @hasSection('topbar_right')
                        @yield('topbar_right')
                    @endif

                    @auth
                        {{-- Notifications dropdown --}}
                        @php
    $notifUser = auth()->user();
    $notifUnread = $notifUser?->unreadNotifications()->count() ?? 0;
    $notifLatest = $notifUser?->notifications()->latest()->limit(5)->get() ?? collect();
                        @endphp

                        <div class="dropdown">
                            <button
                                class="btn btn-outline-secondary btn-sm position-relative"
                                type="button"
                                id="notifDropdownBtn"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="Notifications"
                            >
                                <i class="bi bi-bell"></i>

                                @if($notifUnread > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        {{ $notifUnread > 99 ? '99+' : $notifUnread }}
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                                @endif
                            </button>

                            <div class="dropdown-menu dropdown-menu-end p-0 notifications-dropdown" aria-labelledby="notifDropdownBtn" style="min-width: 360px; max-width: 420px;">
                                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                    <div class="fw-semibold small">Notifications</div>
                                    <a class="small text-decoration-none" href="{{ route('notifications.index') }}">
                                        View all
                                    </a>
                                </div>

                                <div class="list-group list-group-flush">
                                    @forelse($notifLatest as $n)
                                        @php
        $data = $n->data ?? [];
        $title = $data['title'] ?? ($data['message'] ?? class_basename($n->type));
        $message = $data['message'] ?? '';
        $url = $data['url'] ?? null;
        $isUnread = $n->read_at === null;
                                        @endphp

                                        <a
                                            href="{{ $url ?: route('notifications.index') }}"
                                            class="list-group-item list-group-item-action d-flex gap-2 {{ $isUnread ? 'fw-semibold' : '' }}"
                                            @if($url) target="_blank" rel="noopener" @endif
                                        >
                                            <div class="pt-1">
                                                <i class="bi {{ $isUnread ? 'bi-circle-fill' : 'bi-circle' }}" style="font-size: 0.5rem;"></i>
                                            </div>

                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div class="small">
                                                        {{ \Illuminate\Support\Str::limit((string) $title, 70) }}
                                                    </div>
                                                    <div class="text-body-secondary small">
                                                        {{ optional($n->created_at)->diffForHumans() }}
                                                    </div>
                                                </div>

                                                @if(!empty($message) && $message !== $title)
                                                    <div class="text-body-secondary small">
                                                        {{ \Illuminate\Support\Str::limit((string) $message, 100) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </a>
                                    @empty
                                        <div class="px-3 py-3 text-body-secondary small">
                                            No notifications yet.
                                        </div>
                                    @endforelse
                                </div>

                                @if($notifUnread > 0)
                                    <div class="px-3 py-2 border-top">
                                        <form method="POST" action="{{ route('notifications.read_all') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                                                Mark all as read
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Dark mode toggle --}}
                        <button
                            class="btn btn-outline-secondary btn-sm"
                            type="button"
                            id="themeToggle"
                            aria-label="Toggle dark mode"
                            title="Toggle dark mode"
                        >
                            <i class="bi bi-moon-stars" id="themeToggleIcon"></i>
                        </button>

                        {{-- User dropdown --}}
                        <div class="dropdown">
                            <button
                                class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center"
                                type="button"
                                id="userMenuDropdown"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i class="bi bi-person-circle me-1"></i>
                                <span class="d-none d-sm-inline">
                                    {{ auth()->user()->name }}
                                </span>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                                <li class="dropdown-header small">
                                    Signed in as<br>
                                    <strong>{{ auth()->user()->email }}</strong>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                        <i class="bi bi-gear me-1"></i> Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endauth
                </div>

            </div>
        </div>
    </header>

    {{-- Main area: sidebar + content (fills remaining height) --}}
    <div class="d-flex flex-grow-1 overflow-hidden">

        {{-- Desktop sidebar (independent scroll) --}}
        <aside   id="desktopSidebar" class="border-end bg-body d-none d-md-flex flex-column erp-sidebar-wrapper">
            @include('partials.sidebar', ['sidebarId' => 'desktop'])
        </aside>

        {{-- Main content (independent scroll) --}}
        <main class="flex-grow-1 overflow-auto erp-main-scroll">
            <div class="container-fluid p-3">

                {{-- Flash messages --}}
                @include('partials.flash')

                {{-- Optional page header (title + actions) --}}
                @hasSection('page_header')
                    <div class="d-flex flex-column gap-2 flex-sm-row flex-sm-wrap align-items-sm-center justify-content-sm-between mb-3">
                        @yield('page_header')
                    </div>
                @endif

                {{-- Main page wrapper with soft shadow --}}
                <div class="erp-main-card">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>
</div>

{{-- Mobile offcanvas sidebar --}}
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title small text-uppercase text-body-secondary" id="sidebarOffcanvasLabel">
            {{ config('app.name', 'EMS Infra ERP') }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        {{-- Reuse same sidebar partial --}}
        @include('partials.sidebar', ['sidebarId' => 'mobile'])
    </div>
</div>

{{-- Bootstrap 5 JS bundle --}}
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
    defer
></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

{{-- Theme toggle (no build required) --}}
<script src="{{ asset('js/erp-theme.js') }}" defer></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {

        const toggleBtn = document.getElementById("sidebarToggle");

        toggleBtn.addEventListener("click", function () {

            // MOBILE
            if (window.innerWidth < 768) {
                let offcanvas = new bootstrap.Offcanvas(
                    document.getElementById('sidebarOffcanvas')
                );
                offcanvas.toggle();
            }

            // DESKTOP
            else {
                document.body.classList.toggle("sidebar-collapsed");
            }

        });

    });
</script>

{{-- Per-page extra scripts --}}
@stack('scripts')

</body>
</html>
