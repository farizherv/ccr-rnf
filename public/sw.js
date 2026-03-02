/* ============================================================
   CCR RNF — Service Worker
   Handles: PWA install, push notifications, offline fallback
   ============================================================ */

const CACHE_NAME = 'ccr-rnf-v1';
const OFFLINE_URL = '/offline.html';

// Pre-cache essentials on install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll([
                OFFLINE_URL,
                '/favicon-32.png',
                '/icon-192.png',
            ]))
            .then(() => self.skipWaiting())
    );
});

// Clean old caches on activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// Network-first strategy with offline fallback
self.addEventListener('fetch', (event) => {
    // Only handle GET requests for navigation
    if (event.request.mode !== 'navigate') return;

    event.respondWith(
        fetch(event.request)
            .catch(() => caches.match(OFFLINE_URL))
    );
});

// ─── Push Notifications ──────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = String(data.title || 'CCR Notification');
    const body = String(data.body || '');
    const url = String(data.url || '/inbox');
    const tag = String(data.tag || 'ccr-notification');
    const icon = String(data.icon || '/icon-192.png');
    const badge = String(data.badge || '/favicon-32.png');

    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            tag,
            icon,
            badge,
            renotify: false,
            requireInteraction: false,
            data: { url },
        })
    );
});

// ─── Notification Click ──────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const rawUrl = (event.notification && event.notification.data && event.notification.data.url)
        ? String(event.notification.data.url)
        : '/inbox';
    let targetUrl = '/inbox';

    try {
        const parsed = new URL(rawUrl, self.location.origin);
        if (parsed.origin === self.location.origin) {
            targetUrl = parsed.pathname + parsed.search + parsed.hash;
        }
    } catch (e) {
        targetUrl = '/inbox';
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const win of windows) {
            if ('focus' in win) {
                await win.focus();
                if ('navigate' in win) {
                    await win.navigate(targetUrl);
                }
                return;
            }
        }
        if (self.clients.openWindow) {
            await self.clients.openWindow(targetUrl);
        }
    })());
});
