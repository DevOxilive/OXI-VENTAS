const VERSION = 'oxi-ventas-pwa-v11';
const STATIC_CACHE = `${VERSION}-static`;
const RUNTIME_CACHE = `${VERSION}-runtime`;

const PRECACHE_URLS = [
    '/offline.html',
    '/favicon.ico',
    '/icons/icon-192.png',
    '/icons/maskable-192.png',
    '/pwa/manifest.webmanifest',
];

const CACHEABLE_DESTINATIONS = new Set(['font', 'image', 'manifest', 'script', 'style']);
const CACHEABLE_PATHS = [
    /^\/build\/assets\/.+\.(?:css|js|mjs|woff2?)$/,
    /^\/icons\/(?:icon-192|icon-512|maskable-192|maskable-512|apple-touch-icon)\.png$/,
    /^\/pwa\/manifest\.webmanifest$/,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) =>
            Promise.all(
                cacheNames
                    .filter((cacheName) => ![STATIC_CACHE, RUNTIME_CACHE].includes(cacheName))
                    .map((cacheName) => caches.delete(cacheName))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => response)
                .catch(async () => {
                    const cached = await caches.match(request);

                    if (cached) {
                        return cached;
                    }

                    return caches.match('/offline.html');
                })
        );

        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    const shouldUseRuntimeCache =
        CACHEABLE_DESTINATIONS.has(request.destination)
        || CACHEABLE_PATHS.some((pattern) => pattern.test(url.pathname));

    if (!shouldUseRuntimeCache) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request)
                .then((response) => {
                    if (response && response.ok) {
                        const clone = response.clone();

                        caches.open(RUNTIME_CACHE)
                            .then((cache) => cache.put(request, clone))
                            .catch(() => {});
                    }

                    return response;
                });
        })
    );
});
