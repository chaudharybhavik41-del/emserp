@extends('layouts.erp')

@section('title', 'PWA Diagnostics')

@section('page_header')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">Progressive Web App</div>
            <h1 class="h3 mb-1">Diagnostics</h1>
            <div class="text-muted">Check install state, sync readiness, push delivery setup, and local device capabilities.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-bell me-1"></i> Notifications
            </a>
            @if($serverSummary['manage_push_report'])
                <a href="{{ route('notifications.push-report') }}" class="btn btn-primary">
                    <i class="bi bi-broadcast-pin me-1"></i> Push Report
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <style>
        .pwa-diag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }
        .pwa-diag-card {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.92);
            padding: 1rem 1.1rem;
        }
        .pwa-diag-label {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.45rem;
        }
        .pwa-diag-value {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--bs-emphasis-color);
        }
        .pwa-diag-note {
            margin-top: 0.45rem;
            color: var(--bs-secondary-color);
            font-size: 0.9rem;
        }
        .pwa-diag-list {
            display: grid;
            gap: 0.75rem;
        }
        .pwa-diag-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            padding-bottom: 0.75rem;
        }
        .pwa-diag-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .pwa-diag-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.25rem 0.6rem;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .pwa-diag-pill.ok {
            background: rgba(22, 163, 74, 0.12);
            color: #166534;
        }
        .pwa-diag-pill.warn {
            background: rgba(245, 158, 11, 0.12);
            color: #92400e;
        }
        .pwa-diag-prefixes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .pwa-diag-prefixes span {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            color: var(--bs-emphasis-color);
            font-size: 0.82rem;
        }
        [data-bs-theme="dark"] .pwa-diag-card {
            background: rgba(15, 23, 42, 0.82);
            border-color: rgba(148, 163, 184, 0.18);
        }
        [data-bs-theme="dark"] .pwa-diag-prefixes span {
            background: rgba(148, 163, 184, 0.14);
        }
    </style>

    <div class="d-flex flex-column gap-4">
        <div class="pwa-diag-grid">
            <div class="pwa-diag-card">
                <div class="pwa-diag-label">Unread Notifications</div>
                <div class="pwa-diag-value">{{ number_format($serverSummary['unread_notifications']) }}</div>
                <div class="pwa-diag-note">Used for app badge counts when supported.</div>
            </div>
            <div class="pwa-diag-card">
                <div class="pwa-diag-label">Push Subscriptions</div>
                <div class="pwa-diag-value">{{ number_format($serverSummary['active_subscription_count']) }}</div>
                <div class="pwa-diag-note">{{ number_format($serverSummary['subscription_count']) }} total subscriptions on this account.</div>
            </div>
            <div class="pwa-diag-card">
                <div class="pwa-diag-label">Push Delivery</div>
                <div class="pwa-diag-value">
                    <span class="pwa-diag-pill {{ $serverSummary['push_enabled'] && $serverSummary['push_vapid_configured'] ? 'ok' : 'warn' }}">
                        {{ $serverSummary['push_enabled'] && $serverSummary['push_vapid_configured'] ? 'Ready' : 'Needs Setup' }}
                    </span>
                </div>
                <div class="pwa-diag-note">Push enabled: {{ $serverSummary['push_enabled'] ? 'Yes' : 'No' }}, VAPID configured: {{ $serverSummary['push_vapid_configured'] ? 'Yes' : 'No' }}.</div>
            </div>
            <div class="pwa-diag-card">
                <div class="pwa-diag-label">Offline Queue</div>
                <div class="pwa-diag-value">
                    <span class="pwa-diag-pill {{ $serverSummary['background_sync_enabled'] ? 'ok' : 'warn' }}">
                        {{ $serverSummary['background_sync_enabled'] ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <div class="pwa-diag-note">Safe update prompt: {{ $serverSummary['safe_update_prompt'] ? 'Enabled' : 'Disabled' }}.</div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="pwa-diag-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Browser & Device</h2>
                        <span id="pwaDiagStandalone" class="pwa-diag-pill warn">Checking</span>
                    </div>
                    <div class="pwa-diag-list" id="pwaClientDiagnostics">
                        <div class="pwa-diag-row"><span>Service Worker</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Push Support</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Background Sync</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Periodic Sync</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>App Badge</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Share Target Flow</span><strong>Configured</strong></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="pwa-diag-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Local Runtime</h2>
                        <span id="pwaDiagConnection" class="pwa-diag-pill warn">Checking</span>
                    </div>
                    <div class="pwa-diag-list" id="pwaLocalDiagnostics">
                        <div class="pwa-diag-row"><span>Queued Actions</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Last Successful Sync</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Push Permission</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Push Subscription</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Cached Bundles</span><strong>Checking</strong></div>
                        <div class="pwa-diag-row"><span>Current App Badge</span><strong>{{ number_format($serverSummary['unread_notifications']) }} base unread</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pwa-diag-card">
            <h2 class="h5 mb-3">Offline-Critical Paths</h2>
            <div class="pwa-diag-prefixes">
                @foreach($serverSummary['critical_prefixes'] as $prefix)
                    <span>{{ $prefix }}</span>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const setRow = (containerId, label, value) => {
                const container = document.getElementById(containerId);
                if (!container) return;
                const row = Array.from(container.querySelectorAll('.pwa-diag-row'))
                    .find((item) => item.firstElementChild && item.firstElementChild.textContent.trim() === label);
                if (row && row.lastElementChild) {
                    row.lastElementChild.textContent = value;
                }
            };

            const setPill = (id, text, ok) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.textContent = text;
                el.classList.remove('ok', 'warn');
                el.classList.add(ok ? 'ok' : 'warn');
            };

            const formatRelativeTime = (timestamp) => {
                if (!timestamp) return 'Unknown';
                const date = new Date(Number(timestamp) || timestamp);
                if (Number.isNaN(date.getTime())) return 'Unknown';
                const diffMinutes = Math.max(0, Math.round((Date.now() - date.getTime()) / 60000));
                if (diffMinutes < 1) return 'Just now';
                if (diffMinutes < 60) return `${diffMinutes} min ago`;
                const diffHours = Math.round(diffMinutes / 60);
                if (diffHours < 24) return `${diffHours} hr ago`;
                const diffDays = Math.round(diffHours / 24);
                return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
            };

            setPill('pwaDiagStandalone', window.matchMedia('(display-mode: standalone)').matches ? 'Installed App' : 'Browser Tab', window.matchMedia('(display-mode: standalone)').matches);
            setPill('pwaDiagConnection', navigator.onLine ? 'Online' : 'Offline', navigator.onLine);

            setRow('pwaClientDiagnostics', 'Service Worker', 'serviceWorker' in navigator ? 'Supported' : 'Not supported');
            setRow('pwaClientDiagnostics', 'Push Support', ('Notification' in window && 'PushManager' in window) ? 'Supported' : 'Not supported');
            setRow('pwaClientDiagnostics', 'Background Sync', 'SyncManager' in window ? 'Supported' : 'Not supported');
            setRow('pwaClientDiagnostics', 'App Badge', (typeof navigator.setAppBadge === 'function' && typeof navigator.clearAppBadge === 'function') ? 'Supported' : 'Not supported');

            try {
                const registration = 'serviceWorker' in navigator ? await navigator.serviceWorker.getRegistration() : null;
                setRow('pwaClientDiagnostics', 'Periodic Sync', registration && 'periodicSync' in registration ? 'Supported' : 'Not supported');

                const dbRequest = indexedDB.open('ems-pwa-sync', 2);
                dbRequest.onsuccess = async () => {
                    const db = dbRequest.result;
                    const count = await new Promise((resolve) => {
                        const tx = db.transaction('requests', 'readonly');
                        const req = tx.objectStore('requests').count();
                        req.onsuccess = () => resolve(Number(req.result || 0));
                        req.onerror = () => resolve(0);
                    });

                    const lastSync = await new Promise((resolve) => {
                        const tx = db.transaction('meta', 'readonly');
                        const req = tx.objectStore('meta').get('lastSyncAt');
                        req.onsuccess = () => resolve(req.result ? req.result.value : null);
                        req.onerror = () => resolve(null);
                    });

                    db.close();

                    setRow('pwaLocalDiagnostics', 'Queued Actions', String(count));
                    setRow('pwaLocalDiagnostics', 'Last Successful Sync', formatRelativeTime(lastSync || window.localStorage.getItem('erp-pwa-last-sync-at')));
                };
                dbRequest.onerror = () => {
                    setRow('pwaLocalDiagnostics', 'Queued Actions', 'Unavailable');
                    setRow('pwaLocalDiagnostics', 'Last Successful Sync', 'Unavailable');
                };
            } catch (_) {
                setRow('pwaLocalDiagnostics', 'Queued Actions', 'Unavailable');
                setRow('pwaLocalDiagnostics', 'Last Successful Sync', 'Unavailable');
            }

            setRow('pwaLocalDiagnostics', 'Push Permission', 'Notification' in window ? Notification.permission : 'Not supported');

            try {
                const registration = 'serviceWorker' in navigator ? await navigator.serviceWorker.getRegistration() : null;
                const subscription = registration && registration.pushManager ? await registration.pushManager.getSubscription() : null;
                setRow('pwaLocalDiagnostics', 'Push Subscription', subscription ? 'Present on this device' : 'Not registered on this device');

                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    const appCaches = cacheNames.filter((name) => typeof name === 'string' && name.startsWith('ems-sw-'));
                    setRow('pwaLocalDiagnostics', 'Cached Bundles', `${appCaches.length} app caches`);
                } else {
                    setRow('pwaLocalDiagnostics', 'Cached Bundles', 'Not supported');
                }
            } catch (_) {
                setRow('pwaLocalDiagnostics', 'Push Subscription', 'Unavailable');
                setRow('pwaLocalDiagnostics', 'Cached Bundles', 'Unavailable');
            }
        });
    </script>
@endpush
