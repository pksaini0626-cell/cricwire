const CACHE_NAME = 'cricwire-v2';
const OFFLINE_URL = '/offline.html';

// Core static assets to cache for offline support
const PRECACHE_ASSETS = [
    '/',
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/cricwire_favicons/favicon.ico'
];

// Patterns that must NEVER be cached (live cricket feeds, websockets, polling, APIs)
const EXCLUDE_URL_PATTERNS = [
    /\/api\//i,
    /\/api\/live\//i,
    /\/live-feed\//i,
    /\/livewire\//i,
    /\/js\/match-detail-engine\.js/i,
    /\/ws\//i,
    /\/socket\.io\//i,
    /\/matches\/live/i,
    /\/commentary\//i,
    /\/oauth\//i,
    /\/login/i,
    /\/logout/i
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[SW] Pre-caching warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Never cache dynamic live APIs, livewire calls, or external websockets
    const shouldExclude = EXCLUDE_URL_PATTERNS.some((pattern) => pattern.test(url.pathname));
    if (shouldExclude || url.origin !== self.location.origin) {
        return;
    }

    // For HTML navigation requests: Network First, fallback to Offline Cache
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(() => {
                    return caches.match(OFFLINE_URL).then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        return new Response(
                            `<!DOCTYPE html>
                            <html lang="en" class="dark">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Cricwire - Offline</title>
                                <style>
                                    body { background: #020617; color: #f8fafc; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                                    .card { padding: 2rem; max-width: 400px; }
                                    h1 { color: #f59e0b; margin-bottom: 0.5rem; }
                                    p { color: #94a3b8; font-size: 0.95rem; }
                                    button { margin-top: 1.5rem; background: #f59e0b; color: #020617; border: none; padding: 0.6rem 1.4rem; border-radius: 0.5rem; font-weight: bold; cursor: pointer; }
                                </style>
                            </head>
                            <body>
                                <div class="card">
                                    <h1>🏏 You're Offline</h1>
                                    <p>Live cricket scores require an active internet connection. Please check your network and try again.</p>
                                    <button onclick="window.location.reload()">Retry Connection</button>
                                </div>
                            </body>
                            </html>`,
                            {
                                headers: { 'Content-Type': 'text/html' }
                            }
                        );
                    });
                })
        );
        return;
    }

    // For static assets (CSS, JS, images, fonts): Cache First with Network Fallback
    if (['style', 'script', 'image', 'font'].includes(request.destination)) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseToCache);
                        });
                    }
                    return networkResponse;
                }).catch(() => {
                    // Suppress failed asset requests when offline
                });
            })
        );
    }
});

