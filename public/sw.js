/* EMS Infra ERP Service Worker - Phase 2 */

const SW_VERSION = 'ems-sw-v2.0.0';

const CACHES = {
    static: `${SW_VERSION}-static`,
    runtime: `${SW_VERSION}-runtime`,
    transactional: `${SW_VERSION}-transactional`,
    reference: `${SW_VERSION}-reference`,
    api: `${SW_VERSION}-api`,
};

const CACHE_LIMITS = {
    [CACHES.static]: 220,
    [CACHES.runtime]: 140,
    [CACHES.transactional]: 140,
    [CACHES.reference]: 110,
    [CACHES.api]: 80,
};

const OFFLINE_URL = '/offline.html';
const SYNC_DB_NAME = 'ems-pwa-sync';
const SYNC_STORE_NAME = 'requests';
const SYNC_TAG = 'ems-critical-form-sync-v1';

const PRECACHE_ASSETS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/ems-favicon.ico',
    '/images/pwa-180.png',
    '/images/pwa-192.png',
    '/images/pwa-512.png',
    '/images/pwa-maskable-512.png',
];

const MODULE_POLICIES = [
    { prefix: '/dashboard', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/machines', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/machine-', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/maintenance', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/store', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/material-receipts', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/purchase', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/accounting', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/production', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/crm', strategy: 'network-first', cacheName: CACHES.transactional },
    { prefix: '/notifications', strategy: 'network-first', cacheName: CACHES.api },
    { prefix: '/reports', strategy: 'stale-while-revalidate', cacheName: CACHES.reference },
    { prefix: '/reports-hub', strategy: 'stale-while-revalidate', cacheName: CACHES.reference },
    { prefix: '/activity-logs', strategy: 'stale-while-revalidate', cacheName: CACHES.reference },
    { prefix: '/support', strategy: 'stale-while-revalidate', cacheName: CACHES.reference },
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHES.static);
        await cache.addAll(PRECACHE_ASSETS);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = new Set(Object.values(CACHES));
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => key.startsWith('ems-sw-') && !keep.has(key))
                .map((key) => caches.delete(key))
        );

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }

    if (data.type === 'REPLAY_SYNC_QUEUE') {
        const task = replayQueuedRequests('message');
        if (typeof event.waitUntil === 'function') {
            event.waitUntil(task);
        }
    }
});

function getModulePolicy(pathname) {
    const path = pathname.toLowerCase();
    return MODULE_POLICIES.find((policy) => path.startsWith(policy.prefix)) || null;
}

async function trimCache(cacheName, maxEntries) {
    if (!maxEntries || maxEntries < 1) return;

    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    const remove = keys.length - maxEntries;
    if (remove <= 0) return;

    for (let i = 0; i < remove; i += 1) {
        await cache.delete(keys[i]);
    }
}

function isCacheableResponse(response) {
    return !!response && response.ok;
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const networkPromise = fetch(request)
        .then(async (response) => {
            if (isCacheableResponse(response)) {
                await cache.put(request, response.clone());
                await trimCache(cacheName, CACHE_LIMITS[cacheName]);
            }
            return response;
        })
        .catch(() => null);

    return cached || networkPromise || Response.error();
}

async function fetchWithTimeout(request, timeoutMs = 7000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        return await fetch(request, { signal: controller.signal });
    } finally {
        clearTimeout(timer);
    }
}

async function networkFirst(request, cacheName, options = {}) {
    const cache = await caches.open(cacheName);
    const { timeoutMs = 7000, fallbackToOffline = false } = options;

    try {
        const response = await fetchWithTimeout(request, timeoutMs);
        if (isCacheableResponse(response)) {
            await cache.put(request, response.clone());
            await trimCache(cacheName, CACHE_LIMITS[cacheName]);
        }
        return response;
    } catch (_) {
        const cached = await cache.match(request);
        if (cached) return cached;

        if (fallbackToOffline && request.mode === 'navigate') {
            const offline = await caches.match(OFFLINE_URL);
            if (offline) return offline;
        }

        return Response.error();
    }
}

function isStaticAssetRequest(request, url) {
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'worker' ||
        request.destination === 'font' ||
        request.destination === 'image'
    ) {
        return true;
    }

    if (url.pathname.startsWith('/build/')) {
        return true;
    }

    return /\.(?:css|js|mjs|png|jpg|jpeg|svg|gif|webp|ico|woff2?|ttf)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;
    if (request.cache === 'only-if-cached' && request.mode !== 'same-origin') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        const policy = getModulePolicy(url.pathname);
        const cacheName = policy ? policy.cacheName : CACHES.runtime;

        event.respondWith(networkFirst(request, cacheName, {
            fallbackToOffline: true,
            timeoutMs: 7000,
        }));
        return;
    }

    if (isStaticAssetRequest(request, url)) {
        event.respondWith(staleWhileRevalidate(request, CACHES.static));
        return;
    }

    if (url.pathname.startsWith('/dashboard/api/') || url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request, CACHES.api, { timeoutMs: 6000 }));
        return;
    }

    const policy = getModulePolicy(url.pathname);
    if (policy) {
        if (policy.strategy === 'network-first') {
            event.respondWith(networkFirst(request, policy.cacheName, { timeoutMs: 6500 }));
        } else {
            event.respondWith(staleWhileRevalidate(request, policy.cacheName));
        }
        return;
    }

    event.respondWith(staleWhileRevalidate(request, CACHES.runtime));
});

function parsePushData(event) {
    const fallback = {
        title: 'EMS Infra Alert',
        body: 'You have a new update in ERP.',
        url: '/notifications',
    };

    if (!event.data) return fallback;

    try {
        const json = event.data.json();
        return {
            ...fallback,
            ...(json || {}),
            url: (json && (json.url || (json.data && json.data.url))) || fallback.url,
        };
    } catch (_) {
        try {
            const text = event.data.text();
            if (!text) return fallback;
            return {
                ...fallback,
                body: text,
            };
        } catch (__unused) {
            return fallback;
        }
    }
}

self.addEventListener('push', (event) => {
    const payload = parsePushData(event);

    event.waitUntil(self.registration.showNotification(payload.title, {
        body: payload.body,
        icon: payload.icon || '/images/pwa-192.png',
        badge: payload.badge || '/images/pwa-192.png',
        tag: payload.tag || 'ems-system-alert',
        renotify: !!payload.renotify,
        data: {
            ...(payload.data || {}),
            url: payload.url || '/notifications',
        },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/notifications';

    event.waitUntil((async () => {
        const targetUrl = new URL(url, self.location.origin).toString();
        const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of allClients) {
            if (!client.url) continue;
            const clientUrl = new URL(client.url);
            if (clientUrl.origin === self.location.origin && 'focus' in client) {
                if (clientUrl.pathname === new URL(targetUrl).pathname) {
                    await client.focus();
                    client.postMessage({ type: 'PWA_PUSH_OPENED', url: targetUrl });
                    return;
                }
            }
        }

        if (self.clients.openWindow) {
            await self.clients.openWindow(targetUrl);
        }
    })());
});

function openSyncDb() {
    if (!('indexedDB' in self)) {
        return Promise.reject(new Error('IndexedDB not available in service worker'));
    }

    return new Promise((resolve, reject) => {
        const request = self.indexedDB.open(SYNC_DB_NAME, 1);

        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(SYNC_STORE_NAME)) {
                const store = db.createObjectStore(SYNC_STORE_NAME, { keyPath: 'id' });
                store.createIndex('createdAt', 'createdAt', { unique: false });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('Failed to open sync database'));
    });
}

async function getQueuedRequests() {
    const db = await openSyncDb();

    const rows = await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readonly');
        const req = tx.objectStore(SYNC_STORE_NAME).getAll();
        req.onsuccess = () => resolve(Array.isArray(req.result) ? req.result : []);
        req.onerror = () => reject(req.error || new Error('Failed to read queued requests'));
    });

    db.close();
    return rows.sort((a, b) => Number(a.createdAt || 0) - Number(b.createdAt || 0));
}

async function putQueuedRequest(item) {
    const db = await openSyncDb();

    await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
        tx.objectStore(SYNC_STORE_NAME).put(item);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error || new Error('Failed to update queued request'));
        tx.onabort = () => reject(tx.error || new Error('Queue update transaction aborted'));
    });

    db.close();
}

async function deleteQueuedRequest(id) {
    const db = await openSyncDb();

    await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readwrite');
        tx.objectStore(SYNC_STORE_NAME).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error || new Error('Failed to delete queued request'));
        tx.onabort = () => reject(tx.error || new Error('Queue delete transaction aborted'));
    });

    db.close();
}

async function countQueuedRequests() {
    const db = await openSyncDb();

    const count = await new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_STORE_NAME, 'readonly');
        const req = tx.objectStore(SYNC_STORE_NAME).count();
        req.onsuccess = () => resolve(Number(req.result || 0));
        req.onerror = () => reject(req.error || new Error('Failed to count queued requests'));
    });

    db.close();
    return count;
}

function buildReplayRequestInit(item) {
    const headers = new Headers(item.headers || {});
    if (!headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
    }
    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    return {
        method: item.method || 'POST',
        headers,
        body: new URLSearchParams(Array.isArray(item.body) ? item.body : []),
        credentials: 'include',
        redirect: 'follow',
    };
}

async function replayQueuedRequest(item) {
    try {
        const response = await fetch(item.url, buildReplayRequestInit(item));

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

async function broadcastToClients(message) {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
        client.postMessage(message);
    }
}

async function replayQueuedRequests(trigger = 'sync') {
    let succeeded = 0;
    let failed = 0;

    try {
        const rows = await getQueuedRequests();

        for (const row of rows) {
            const result = await replayQueuedRequest(row);
            if (result.done) {
                succeeded += 1;
                await deleteQueuedRequest(row.id);
                continue;
            }

            failed += 1;
            const retryCount = Number(row.retryCount || 0) + 1;
            await putQueuedRequest({ ...row, retryCount });
        }
    } catch (_) {
        // Keep queue for future retries.
    }

    let pending = 0;
    try {
        pending = await countQueuedRequests();
    } catch (_) {
        pending = 0;
    }

    await broadcastToClients({
        type: 'PWA_SYNC_RESULT',
        succeeded,
        failed,
        pending,
        trigger,
        at: Date.now(),
    });

    if (succeeded > 0 && self.registration && typeof self.registration.showNotification === 'function') {
        try {
            await self.registration.showNotification('ERP Sync Complete', {
                body: `${succeeded} queued action${succeeded === 1 ? '' : 's'} synced successfully.`,
                icon: '/images/pwa-192.png',
                badge: '/images/pwa-192.png',
                tag: 'erp-sync-complete',
                renotify: false,
                data: { url: '/dashboard' },
            });
        } catch (_) {
            // notification permission may not be granted.
        }
    }
}

self.addEventListener('sync', (event) => {
    if (event.tag !== SYNC_TAG) return;
    event.waitUntil(replayQueuedRequests('sync'));
});
