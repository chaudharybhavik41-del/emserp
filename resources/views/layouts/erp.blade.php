<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.pwa_head')
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" type="image/x-icon" href="{{ asset('ems-favicon.ico') }}">

    <title  class="sdd">
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
    <style>
.erp-main-scroll {
    transition: all 0.3s ease;
}

.erp-mobile-offcanvas {
    --bs-offcanvas-width: min(94vw, 380px);
}
.erp-mobile-offcanvas .erp-sidebar {
    width: 100%;
    max-width: none;
    box-shadow: none;
}

/* --- Premium Header Styling --- */
.erp-header {
    background: rgba(255, 255, 255, 0.96) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
    z-index: 1030;
}

[data-bs-theme="dark"] .erp-header {
    background: rgba(33, 37, 41, 0.92) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.25);
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header,
.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header:hover,
.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link:hover {
    transform: translateY(-2px);
    background: rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

[data-bs-theme="dark"] .erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header:hover,
[data-bs-theme="dark"] .erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link:hover {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.erp-nav-container {
    background: transparent !important;
}

.erp-module-topbar {
    background: transparent !important;
}
/* --- End Premium Styling --- */

.erp-module-topnav {
    border-top: 1px solid var(--bs-border-color-translucent);
    background: var(--bs-tertiary-bg);
    position: relative;
    z-index: 1300;
    overflow: visible;
}

.erp-module-topnav .container-fluid {
    overflow: visible;
}

.erp-module-topbar {
    width: 100%;
    max-width: none;
    display: block;
    flex: 0 0 auto;
    min-height: 0;
    box-shadow: none;
    border-right: 0 !important;
    position: relative;
    z-index: 1200;
    overflow: visible;
}

.erp-module-topbar .erp-sidebar-nav {
    overflow: visible !important;
    height: auto;
    min-height: auto;
}

.erp-module-topbar .erp-sidebar-nav > .p-2 {
    padding: 0.4rem 0.35rem;
    overflow: visible;
    position: relative;
}

.erp-module-topbar-list {
    display: flex;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center;
    gap: 0.3rem;
    overflow-x: visible !important;
    overflow-y: visible !important;
    width: 100%;
    min-width: 0;
    margin: 0;
    padding: 0 0 0.15rem;
    white-space: nowrap;
}

.erp-module-topbar-list::-webkit-scrollbar {
    height: 6px;
}

.erp-module-topbar-list > .erp-sidebar-section {
    position: relative;
    margin-bottom: 0 !important;
    flex: 0 0 auto;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header,
.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link {
    width: 5.5rem !important;
    min-width: 5.5rem;
    min-height: 4rem;
    height: auto;
    padding: 0.35rem 0.2rem !important;
    border-radius: 0.75rem;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    text-align: center;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header > span {
    display: inline-flex !important;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.12rem;
    line-height: 1.05;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header > span > i,
.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link > i {
    margin: 0 !important;
    font-size: 1.35rem; /* Increased icon size */
    line-height: 1;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header > span > span,
.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link > span {
    display: block !important;
    font-size: 0.68rem !important; /* Increased text size */
    font-weight: 600;
    line-height: 1.1;
    white-space: normal;
    text-transform: none !important;
    letter-spacing: 0;
    max-width: 100%;
}

.erp-module-topbar-list > .erp-sidebar-section > .erp-accordion-header > .erp-chevron {
    display: none !important;
}

.erp-module-topbar-list > .erp-sidebar-section > a.erp-nav-link.active::before {
    display: none;
}

.erp-module-topbar-list > .erp-sidebar-section > .collapse,
.erp-module-topbar-list > .erp-sidebar-section > .collapsing {
    position: absolute;
    top: calc(100% + 0.35rem);
    left: 0;
    min-width: 16rem;
    width: max-content;
    max-width: min(72rem, 96vw);
    z-index: 3000;
    padding: 0.35rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    background: var(--bs-body-bg);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
    transform: translateX(var(--erp-flyout-shift, 0));
}

.erp-module-topbar-list > .erp-sidebar-section > .collapse {
    display: none;
}

.erp-module-topbar-list > .erp-sidebar-section > .collapsing {
    display: block;
    height: auto !important;
    transition: none !important;
}

.erp-module-topbar-list > .erp-sidebar-section.is-align-end > .collapse,
.erp-module-topbar-list > .erp-sidebar-section.is-align-end > .collapsing {
    left: auto;
    right: 0;
}

.erp-module-topbar-list > .erp-sidebar-section > .collapse.show {
    display: block;
}

/* 
 * Dropdowns in the topbar now use native Bootstrap Collapse (click to open).
 * The hover-to-open rules have been removed.
 */

.erp-module-topbar-list > .erp-sidebar-section > .collapse > ul {
    margin: 0;
    display: flex;
    flex-direction: column;
    flex-wrap: wrap;
    align-content: flex-start;
    max-height: 380px; /* Force it to wrap columns */
    gap: 0.2rem 1.5rem;
    width: fit-content;
    max-width: 100%;
}

.erp-module-topbar-list > .erp-sidebar-section > .collapse > ul > li {
    min-width: 12rem;
    max-width: 14rem;
}

.erp-module-topbar-list > .erp-sidebar-section > .collapse .erp-nav-link {
    white-space: normal;
    transition: all 0.2s ease;
    border-radius: 0.35rem;     /* Slightly rounded corners on hover items */
}

/* Premium Hover Effect inside the sub-menus */
.erp-module-topbar-list > .erp-sidebar-section > .collapse .erp-nav-link:hover,
.erp-module-topbar-list > .erp-sidebar-section > .collapse .list-group-item:hover,
.erp-module-topbar-list > .erp-sidebar-section > .collapse .dropdown-item:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
    color: var(--bs-primary) !important;
}

[data-bs-theme="dark"] .erp-module-topbar-list > .erp-sidebar-section > .collapse .erp-nav-link:hover,
[data-bs-theme="dark"] .erp-module-topbar-list > .erp-sidebar-section > .collapse .list-group-item:hover,
[data-bs-theme="dark"] .erp-module-topbar-list > .erp-sidebar-section > .collapse .dropdown-item:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
    color: var(--bs-primary-bg-subtle) !important;
}

@media (max-width: 767.98px) {
    .erp-module-topnav {
        display: none !important;
    }

    .erp-main-scroll > .container-fluid {
        padding: 0.75rem !important;
    }
    .erp-main-card {
        border-radius: 0.5rem;
        padding: 0.875rem !important;
    }
    .erp-main-card .card-header,
    .erp-main-card .card-body {
        padding-left: 0.875rem;
        padding-right: 0.875rem;
    }
}

@media (max-width: 575.98px) {
    .erp-topbar-right {
        gap: 0.35rem !important;
    }
    .erp-topbar-right .btn-sm,
    .erp-topbar-right .dropdown-toggle {
        --bs-btn-padding-y: 0.2rem;
        --bs-btn-padding-x: 0.45rem;
    }
    #themeToggle {
        display: none !important;
    }
    .notifications-dropdown {
        min-width: min(92vw, 360px) !important;
        max-width: 92vw !important;
    }
}
</style>
    @stack('styles')
</head>
<body class="bg-body-tertiary erp-shell">
<a class="erp-skip-link" href="#main-content">Skip to content</a>
{{-- 
    FIXED LAYOUT: 
    - Header is fixed at top
    - Sidebar scrolls independently 
    - Main content scrolls independently
    - No coupled scrolling between sidebar and content
--}}
<div class="d-flex flex-column vh-100 overflow-hidden">

    {{-- Top bar (fixed height, never scrolls) --}}
    <header class="erp-header flex-shrink-0">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between py-2">

                {{-- Left: mobile menu + logo + breadcrumb --}}
                <div class="d-flex align-items-center gap-2 erp-topbar-left flex-shrink-0">

<button class="btn btn-outline-secondary btn-sm d-md-none me-1"
        type="button"
        id="sidebarToggle"
        data-bs-toggle="offcanvas"
        data-bs-target="#sidebarOffcanvas"
        aria-controls="sidebarOffcanvas"
        aria-label="Open module navigation">
    <i class="bi bi-grid-3x3-gap"></i>
    <span class="ms-1 d-none d-lg-inline">Modules</span>
</button>



                    {{-- Logo + app name --}}
                    <a href="{{ route('dashboard') }}"
                       class="text-decoration-none d-flex align-items-center gap-2">
                        <img src="{{ asset('images/ems-logo.png') }}"
                             alt="{{ config('app.name') }}"
                             style="height: 40px; width: auto; object-fit: contain;">
                    </a>

                    {{-- Optional breadcrumb --}}
                    @hasSection('breadcrumb')
                        <div class="ms-3 small text-body-secondary d-none d-md-block">
                            @yield('breadcrumb')
                        </div>
                    @endif
                </div>

                @auth
                <div class="erp-nav-container flex-grow-1 d-none d-md-block px-2" style="z-index: 1300; overflow: visible;">
                    @include('partials.sidebar', ['sidebarId' => 'topbar', 'navigationMode' => 'topbar'])
                </div>
                @endauth

                {{-- Right: per-page content + notifications + theme + user menu --}}
                <div class="d-flex align-items-center gap-2 erp-topbar-right flex-shrink-0">
                    @hasSection('topbar_right')
                        @yield('topbar_right')
                    @endif

                    @auth
                        @php
                            $notifUser = auth()->user();
                            $notifUnread = $notifUser?->unreadNotifications()->count() ?? 0;
                        @endphp

                        <span id="pwaNetworkBadge" class="badge text-bg-warning d-none" aria-live="polite"></span>
                        <span id="pwaSyncBadge" class="badge text-bg-info d-none" aria-live="polite"></span>
                        <button
                            class="btn btn-outline-secondary btn-sm d-none js-pwa-enable-notifications"
                            type="button"
                            aria-label="Enable browser alerts"
                            title="Enable browser alerts"
                        >
                            <i class="bi bi-bell-fill me-1"></i>
                            <span class="d-none d-xl-inline">Alerts</span>
                        </button>
                        <button
                            class="btn btn-outline-secondary btn-sm d-none js-pwa-install"
                            type="button"
                            aria-label="Install app"
                            title="Install app"
                        >
                            <i class="bi bi-download me-1"></i>
                            <span class="d-none d-xl-inline">Install</span>
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
                                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ route('notifications.index') }}">
                                        <span><i class="bi bi-bell me-1"></i> Notifications</span>
                                        @if($notifUnread > 0)
                                            <span class="badge rounded-pill bg-danger">{{ $notifUnread > 99 ? '99+' : $notifUnread }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item" type="button" id="themeToggle" aria-label="Toggle dark mode">
                                        <i class="bi bi-moon-stars me-1" id="themeToggleIcon"></i> Dark Mode
                                    </button>
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

    {{-- Full-width main content --}}
    <main id="main-content" class="flex-grow-1 overflow-auto erp-main-scroll" role="main" tabindex="-1">
        <div class="container-fluid p-3 erp-content-wrap">

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

{{-- Global offcanvas module navigation --}}
<div class="offcanvas offcanvas-start erp-mobile-offcanvas"
     tabindex="-1"
     id="sidebarOffcanvas"
     aria-labelledby="sidebarOffcanvasLabel"
     data-bs-scroll="true">
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

        const mobileSidebarEl = document.getElementById('sidebarOffcanvas');

        // Mobile UX: close offcanvas after clicking a menu link.
        if (mobileSidebarEl) {
            mobileSidebarEl.addEventListener('click', function (event) {
                const link = event.target.closest('a[data-erp-menu-item][href]');
                if (!link || !window.bootstrap) return;
                const offcanvas = bootstrap.Offcanvas.getInstance(mobileSidebarEl);
                if (offcanvas) offcanvas.hide();
            });
        }

        const topbarNav = document.querySelector('.erp-module-topbar');
        if (topbarNav) {
            const topbarSections = Array.from(topbarNav.querySelectorAll('.erp-module-topbar-list > .erp-sidebar-section'));
            const isHoverCapable = function () {
                return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
            };

            const getPanel = function (section) {
                return Array.from(section.children).find(function (node) {
                    return node.classList && (node.classList.contains('collapse') || node.classList.contains('collapsing'));
                }) || null;
            };

            const getToggle = function (section) {
                return Array.from(section.children).find(function (node) {
                    return node.classList && node.classList.contains('erp-accordion-header') && node.hasAttribute('data-erp-section-toggle');
                }) || null;
            };

            const updateFlyoutAlignment = function (section) {
                const panel = getPanel(section);
                if (!panel) return;

                section.classList.remove('is-align-end');
                panel.style.removeProperty('--erp-flyout-shift');

                const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                const viewportPadding = 8;
                const isVisible = panel.classList.contains('show') || panel.classList.contains('collapsing');
                const prevDisplay = panel.style.display;
                const prevVisibility = panel.style.visibility;

                if (!isVisible) {
                    panel.style.display = 'block';
                    panel.style.visibility = 'hidden';
                }

                let rect = panel.getBoundingClientRect();
                if (rect.right > viewportWidth - viewportPadding) {
                    section.classList.add('is-align-end');
                    rect = panel.getBoundingClientRect();
                }

                if (rect.left < viewportPadding) {
                    panel.style.setProperty('--erp-flyout-shift', `${viewportPadding - rect.left}px`);
                } else if (rect.right > viewportWidth - viewportPadding) {
                    panel.style.setProperty('--erp-flyout-shift', `${(viewportWidth - viewportPadding) - rect.right}px`);
                }

                if (!isVisible) {
                    panel.style.display = prevDisplay;
                    panel.style.visibility = prevVisibility;
                }
            };

            const closeSection = function (section) {
                const panel = getPanel(section);
                const toggle = getToggle(section);
                if (panel) {
                    panel.classList.remove('show', 'collapsing');
                    panel.style.height = '';
                    panel.style.removeProperty('--erp-flyout-shift');
                }
                section.classList.remove('is-open', 'is-align-end');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            };

            const closeAllSections = function (exceptSection) {
                topbarSections.forEach(function (section) {
                    if (exceptSection && section === exceptSection) return;
                    closeSection(section);
                });
            };

            const openSection = function (section) {
                const panel = getPanel(section);
                const toggle = getToggle(section);
                if (!panel || !toggle) return;
                closeAllSections(section);
                section.classList.add('is-open');
                panel.classList.remove('collapsing');
                panel.style.height = '';
                panel.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                updateFlyoutAlignment(section);
            };

            topbarSections.forEach(function (section) {
                const panel = getPanel(section);
                const toggle = getToggle(section);
                const directLink = Array.from(section.children).find(function (node) {
                    return node.tagName === 'A' && node.classList && node.classList.contains('erp-nav-link');
                }) || null;

                if (toggle && panel) {
                    toggle.removeAttribute('data-bs-toggle');

                    toggle.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (section.classList.contains('is-open')) {
                            closeSection(section);
                        } else {
                            openSection(section);
                        }
                    });

                    panel.querySelectorAll('a').forEach(function (link) {
                        link.addEventListener('click', function () {
                            closeAllSections();
                        });
                    });
                } else if (directLink) {
                    directLink.addEventListener('click', function () {
                        closeAllSections();
                    });
                }
            });

            window.addEventListener('resize', function () {
                topbarSections.forEach(function (section) {
                    if (section.classList.contains('is-open')) {
                        updateFlyoutAlignment(section);
                    }
                });
            });

            document.addEventListener('click', function (event) {
                if (event.target.closest('.erp-module-topbar')) return;
                closeAllSections();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') return;
                closeAllSections();
            });

            window.addEventListener('load', function () {
                closeAllSections();
            });
        }

    });
</script>

{{-- Per-page extra scripts --}}
@stack('scripts')
</body>
</html>
