/**
 * PWA Phase-2 bootstrap:
 * - Service worker registration and seamless updates
 * - Install prompt handling
 * - Online/offline badge updates
 * - Critical form background sync queue
 * - Push notification subscription plumbing
 */

const DEFAULT_CRITICAL_PREFIXES = [
    '/machines',
    '/machine-assignments',
    '/machine-calibrations',
    '/maintenance',
    '/store-issues',
    '/store-returns',
    '/store-requisitions',
    '/material-receipts',
    '/purchase-orders',
    '/purchase-indents'
];

const SYNC_DB_NAME = 'ems-pwa-sync';
const SYNC_STORE_NAME = 'requests';
const SYNC_TAG = 'ems-critical-form-sync-v1';

let deferredPrompt = null;
let isRefreshing = false;
let isQueueFlushInProgress = false;
let swRegistration = null;

function getMetaContent(name) {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el ? (el.getAttribute('content') || '') : '';
}

function toBoolean(value, fallback = false) {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (normalized === '1' || normalized === 'true' || normalized === 'yes') return true;
        if (normalized === '0' || normalized === 'false' || normalized === 'no') return false;
    }
    return fallback;
}

function normalizePrefixes(prefixes) {
    if (!Array.isArray(prefixes)) return DEFAULT_CRITICAL_PREFIXES;
    const normalized = prefixes
        .filter((item) => typeof item === 'string' && item.trim() !== '')
        .map((item) => {
            const value = item.trim().toLowerCase();
            return value.startsWith('/') ? value : `/${value}`;
        });

    return normalized.length ? normalized : DEFAULT_CRITICAL_PREFIXES;
}

function resolvePwaConfig() {
    const runtimeConfig = window.__ERP_PWA || {};

    return {
        pushEnabled: toBoolean(runtimeConfig.pushEnabled, toBoolean(getMetaContent('pwa-push-enabled'), true)),
        vapidPublicKey: String(runtimeConfig.vapidPublicKey || getMetaContent('pwa-vapid-public-key') || '').trim(),
        syncEnabled: toBoolean(runtimeConfig.syncEnabled, toBoolean(getMetaContent('pwa-sync-enabled'), true)),
        criticalFormPathPrefixes: normalizePrefixes(runtimeConfig.criticalFormPathPrefixes),
    };
}

const PWA_CONFIG = resolvePwaConfig();

function getCsrfToken() {
    return getMetaContent('csrf-token');
}

function inStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function getInstallButtons() {
    return Array.from(document.querySelectorAll('.js-pwa-install'));
}

function setInstallButtonsVisible(visible) {
    getInstallButtons().forEach((btn) => {
        btn.classList.toggle('d-none', !visible);
        if (visible) {
            btn.classList.add('d-inline-flex');
        } else {
            btn.classList.remove('d-inline-flex');
        }
    });
}

function getPushButtons() {
    return Array.from(document.querySelectorAll('.js-pwa-enable-notifications'));
}

function setPushButtonsVisible(visible) {
    getPushButtons().forEach((btn) => {
        btn.classList.toggle('d-none', !visible);
        if (visible) {
            btn.classList.add('d-inline-flex');
        } else {
            btn.classList.remove('d-inline-flex');
        }
    });
}

function showPwaNotice(message, level = 'info') {
    if (!message) return;

    let container = document.getElementById('pwaNoticeStack');
    if (!container) {
        container = document.createElement('div');
        container.id = 'pwaNoticeStack';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1100';
        container.style.maxWidth = 'min(95vw, 420px)';
        document.body.appendChild(container);
    }

    const alert = document.createElement('div');
    alert.className = `alert alert-${level} shadow-sm py-2 px-3 mb-2 small`;
    alert.setAttribute('role', 'status');
    alert.textContent = message;

    container.appendChild(alert);

    window.setTimeout(() => {
        alert.remove();
        if (!container.childElementCount) {
            container.remove();
        }
    }, 4200);
}

function bindInstallPromptButtons() {
    getInstallButtons().forEach((btn) => {
        if (btn.dataset.pwaBound === '1') return;
        btn.dataset.pwaBound = '1';

        btn.addEventListener('click', async () => {
            if (!deferredPrompt) return;

            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } catch (_) {
                // no-op
            }

            deferredPrompt = null;
            setInstallButtonsVisible(false);
        });
    });
}

function initNetworkBadge() {
    const badge = document.getElementById('pwaNetworkBadge');
    if (!badge) return;

    const sync = () => {
        const offline = !window.navigator.onLine;
        badge.textContent = offline ? 'Offline Mode' : '';
        badge.classList.toggle('d-none', !offline);
    };

    window.addEventListener('online', sync);
    window.addEventListener('offline', sync);
    sync();
}

function openSyncDb() {
    if (!('indexedDB' in window)) {
        return Promise.reject(new Error('IndexedDB not supported'));
    }

    return new Promise((resolve, reject) => {
        const request = window.indexedDB.open(SYNC_DB_NAME, 1);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(SYNC_STORE_NAME)) {
                const store = db.createObjectStore(SYNC_STORE_NAME, { keyPath: 'id' });
                store.createIndex('createdAt', 'createdAt', { unique: false });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('Failed to open sync queue database'));
    });
}

async function enqueueSyncRequest(payload) {
    const db = await openSyncDb();

    await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
        tx.objectStore(SYNC_STORE_NAME).put(payload);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error || new Error('Failed to enqueue request'));
        tx.onabort = () => reject(tx.error || new Error('Sync queue transaction aborted'));
    });

    db.close();
}

async function updateSyncRequest(payload) {
    await enqueueSyncRequest(payload);
}

async function getQueuedSyncRequests() {
    const db = await openSyncDb();

    const rows = await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readonly');
        const req = tx.objectStore(SYNC_STORE_NAME).getAll();
        req.onsuccess = () => resolve(Array.isArray(req.result) ? req.result : []);
        req.onerror = () => reject(req.error || new Error('Failed to read sync queue'));
    });

    db.close();

    return rows.sort((a, b) => Number(a.createdAt || 0) - Number(b.createdAt || 0));
}

async function removeQueuedSyncRequest(id) {
    const db = await openSyncDb();

    await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
        tx.objectStore(SYNC_STORE_NAME).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error || new Error('Failed to delete queued request'));
        tx.onabort = () => reject(tx.error || new Error('Sync queue delete transaction aborted'));
    });

    db.close();
}

async function getQueuedSyncCount() {
    const db = await openSyncDb();

    const count = await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readonly');
        const req = tx.objectStore(SYNC_STORE_NAME).count();
        req.onsuccess = () => resolve(Number(req.result || 0));
        req.onerror = () => reject(req.error || new Error('Failed to count sync queue'));
    });

    db.close();

    return count;
}

function makeQueueId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2, 12)}`;
}

function getFormActionUrl(form) {
    try {
        const raw = form.getAttribute('action') || window.location.href;
        return new URL(raw, window.location.origin);
    } catch (_) {
        return null;
    }
}

function shouldQueueForm(form) {
    if (!PWA_CONFIG.syncEnabled) return false;

    const override = String(form.dataset.pwaSync || '').trim().toLowerCase();
    if (override === 'off' || override === 'disabled') return false;
    if (override === 'critical' || override === 'on') return true;

    const method = String(form.getAttribute('method') || 'GET').toUpperCase();
    if (method === 'GET') return false;

    const actionUrl = getFormActionUrl(form);
    if (!actionUrl || actionUrl.origin !== window.location.origin) return false;

    const path = actionUrl.pathname.toLowerCase();
    return PWA_CONFIG.criticalFormPathPrefixes.some((prefix) => path.startsWith(prefix));
}

function serializeFormForQueue(form) {
    const formData = new FormData(form);

    if (!formData.has('_token')) {
        const fallbackToken = form.querySelector('input[name="_token"]')?.value || getCsrfToken();
        if (fallbackToken) {
            formData.append('_token', fallbackToken);
        }
    }

    const body = [];
    for (const [key, value] of formData.entries()) {
        if (value instanceof File) {
            if (value.size > 0) {
                return { ok: false, reason: 'file_upload' };
            }
            continue;
        }

        body.push([key, String(value)]);
    }

    return { ok: true, body };
}

async function refreshSyncBadge() {
    const badge = document.getElementById('pwaSyncBadge');
    if (!badge) return;

    try {
        const count = await getQueuedSyncCount();
        if (count > 0) {
            badge.textContent = `${count} Pending Sync`;
            badge.classList.remove('d-none');
        } else {
            badge.textContent = '';
            badge.classList.add('d-none');
        }
    } catch (_) {
        badge.classList.add('d-none');
    }
}

function buildReplayOptions(item) {
    const headers = new Headers(item.headers || {});
    if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
    }
    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    if (!headers.has('X-CSRF-TOKEN')) {
        const csrf = getCsrfToken();
        if (csrf) {
            headers.set('X-CSRF-TOKEN', csrf);
        }
    }

    return {
        method: item.method || 'POST',
        headers,
        body: new URLSearchParams(Array.isArray(item.body) ? item.body : []),
        credentials: 'same-origin',
        redirect: 'follow',
    };
}

async function replaySingleQueuedRequest(item) {
    try {
        const response = await fetch(item.url, buildReplayOptions(item));

        if (response.ok) {
            return { done: true, retry: false };
        }

        if (response.status >= 400 && response.status < 500 && ![408, 425, 429].includes(response.status)) {
            return { done: true, retry: false };
        }

        return { done: false, retry: true };
    } catch (_) {
        return { done: false, retry: true };
    }
}

async function flushSyncQueue(trigger = 'manual') {
    if (!window.navigator.onLine) return;
    if (isQueueFlushInProgress) return;

    isQueueFlushInProgress = true;

    let succeeded = 0;
    let failed = 0;

    try {
        const items = await getQueuedSyncRequests();
        for (const item of items) {
            const result = await replaySingleQueuedRequest(item);

            if (result.done) {
                succeeded += 1;
                await removeQueuedSyncRequest(item.id);
                continue;
            }

            failed += 1;
            const retryCount = Number(item.retryCount || 0) + 1;
            await updateSyncRequest({ ...item, retryCount });
        }
    } catch (_) {
        // Keep silent; queue will retry later.
    } finally {
        isQueueFlushInProgress = false;
    }

    await refreshSyncBadge();

    if (succeeded > 0) {
        showPwaNotice(`${succeeded} queued action${succeeded === 1 ? '' : 's'} synced.`, 'success');
    }

    if (failed > 0 && trigger !== 'online') {
        showPwaNotice(`${failed} queued action${failed === 1 ? '' : 's'} still pending sync.`, 'warning');
    }
}

async function scheduleBackgroundSync() {
    if (!PWA_CONFIG.syncEnabled) return;

    if (swRegistration && 'sync' in swRegistration) {
        try {
            await swRegistration.sync.register(SYNC_TAG);
            return;
        } catch (_) {
            // fall back to direct replay if online
        }
    }

    if (window.navigator.onLine) {
        await flushSyncQueue('fallback');
    }
}

function bindCriticalFormQueueing() {
    if (!PWA_CONFIG.syncEnabled) return;
    if (document.documentElement.dataset.pwaSyncBound === '1') return;
    document.documentElement.dataset.pwaSyncBound = '1';

    document.addEventListener('submit', async (event) => {
        const form = event.target instanceof HTMLFormElement
            ? event.target
            : event.target?.closest?.('form');

        if (!form || !(form instanceof HTMLFormElement)) return;
        if (!shouldQueueForm(form)) return;
        if (window.navigator.onLine) return;

        const method = String(form.getAttribute('method') || 'POST').toUpperCase();
        if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) return;

        const actionUrl = getFormActionUrl(form);
        if (!actionUrl) return;

        const serialized = serializeFormForQueue(form);
        if (!serialized.ok) {
            if (serialized.reason === 'file_upload') {
                showPwaNotice('Offline queue does not support file uploads. Reconnect to submit this form.', 'warning');
            }
            return;
        }

        event.preventDefault();

        const payload = {
            id: makeQueueId(),
            method,
            url: actionUrl.toString(),
            path: actionUrl.pathname,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json, text/plain, */*',
            },
            body: serialized.body,
            createdAt: Date.now(),
            retryCount: 0,
        };

        try {
            await enqueueSyncRequest(payload);
            await refreshSyncBadge();
            await scheduleBackgroundSync();
            showPwaNotice('No network. Action queued and will auto-sync when online.', 'info');
        } catch (_) {
            showPwaNotice('Unable to queue this action offline. Please retry when online.', 'danger');
        }
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i += 1) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

function supportsPushNotifications() {
    return (
        PWA_CONFIG.pushEnabled &&
        !!PWA_CONFIG.vapidPublicKey &&
        'Notification' in window &&
        'PushManager' in window
    );
}

function refreshPushButtonVisibility() {
    if (!supportsPushNotifications()) {
        setPushButtonsVisible(false);
        return;
    }

    setPushButtonsVisible(Notification.permission === 'default');
}

async function persistPushSubscription(subscription) {
    const csrfToken = getCsrfToken();
    if (!csrfToken) return false;

    try {
        const response = await fetch('/pwa/push-subscriptions', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                subscription: subscription.toJSON(),
                user_agent: window.navigator.userAgent || null,
            }),
        });

        return response.ok;
    } catch (_) {
        return false;
    }
}

async function initPushSubscriptionSync() {
    if (!supportsPushNotifications()) {
        return;
    }

    if (!swRegistration) {
        return;
    }

    if (Notification.permission !== 'granted') {
        refreshPushButtonVisibility();
        return;
    }

    try {
        let subscription = await swRegistration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(PWA_CONFIG.vapidPublicKey),
            });
        }

        if (subscription) {
            await persistPushSubscription(subscription);
        }
    } catch (_) {
        // no-op
    }

    refreshPushButtonVisibility();
}

function bindPushButtons() {
    getPushButtons().forEach((btn) => {
        if (btn.dataset.pwaPushBound === '1') return;
        btn.dataset.pwaPushBound = '1';

        btn.addEventListener('click', async () => {
            if (!supportsPushNotifications()) return;
            if (!swRegistration) return;

            try {
                if (Notification.permission === 'default') {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') {
                        refreshPushButtonVisibility();
                        return;
                    }
                }

                if (Notification.permission !== 'granted') {
                    refreshPushButtonVisibility();
                    return;
                }

                let subscription = await swRegistration.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await swRegistration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(PWA_CONFIG.vapidPublicKey),
                    });
                }

                if (!subscription) {
                    showPwaNotice('Unable to enable browser alerts on this device.', 'warning');
                    return;
                }

                const stored = await persistPushSubscription(subscription);
                if (!stored) {
                    showPwaNotice('Alerts enabled in browser, but server registration failed. Try again later.', 'warning');
                    return;
                }

                showPwaNotice('Browser alerts enabled.', 'success');
                refreshPushButtonVisibility();
            } catch (_) {
                showPwaNotice('Unable to enable alerts in this browser.', 'warning');
            }
        });
    });
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return null;
    if (!window.isSecureContext) return null;

    try {
        const registration = await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
            updateViaCache: 'none'
        });

        swRegistration = registration;

        if (registration.waiting) {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        registration.addEventListener('updatefound', () => {
            const installing = registration.installing;
            if (!installing) return;

            installing.addEventListener('statechange', () => {
                if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                    installing.postMessage({ type: 'SKIP_WAITING' });
                }
            });
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (isRefreshing) return;
            isRefreshing = true;
            window.location.reload();
        });

        navigator.serviceWorker.addEventListener('message', (event) => {
            const data = event.data || {};
            if (data.type !== 'PWA_SYNC_RESULT') return;

            refreshSyncBadge();

            const succeeded = Number(data.succeeded || 0);
            const failed = Number(data.failed || 0);
            if (succeeded > 0) {
                showPwaNotice(`${succeeded} queued action${succeeded === 1 ? '' : 's'} synced.`, 'success');
            }
            if (failed > 0) {
                showPwaNotice(`${failed} queued action${failed === 1 ? '' : 's'} still pending sync.`, 'warning');
            }
        });

        return registration;
    } catch (_) {
        // Fail silently; app works without PWA layer.
        return null;
    }
}

async function initPwa() {
    bindInstallPromptButtons();
    bindPushButtons();
    bindCriticalFormQueueing();

    initNetworkBadge();
    setInstallButtonsVisible(false);
    refreshPushButtonVisibility();
    await refreshSyncBadge();

    if (inStandaloneMode()) {
        setInstallButtonsVisible(false);
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        if (!inStandaloneMode()) {
            setInstallButtonsVisible(true);
        }
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        setInstallButtonsVisible(false);
    });

    window.addEventListener('online', () => {
        flushSyncQueue('online');
        scheduleBackgroundSync();
    });

    await registerServiceWorker();
    await initPushSubscriptionSync();

    if (window.navigator.onLine) {
        flushSyncQueue('startup');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPwa);
} else {
    initPwa();
}
