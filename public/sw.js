/*
 * ABG Metal Works service worker — ONLINE-FIRST.
 *
 * - HTML pages always come from the network and are NEVER cached (they hold business data and CSRF tokens).
 *   If the network is down, /offline.html is shown instead.
 * - Static files (/build/, /css/, /icons/, /images/, Google Fonts) are served stale-while-revalidate.
 * - Non-GET requests and /login, /logout, /api are never touched.
 *
 * When you change this file, bump CACHE_VERSION (abg-v2, abg-v3, ...) so phones pick up the update.
 */
const CACHE_VERSION = 'abg-v1';

const PRECACHE_URLS = [
    '/offline.html',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-512.png',
    '/icons/apple-touch-icon.png',
    '/images/abg-logo.png',
    '/css/abg-theme.css',
    '/css/login.css',
];

const STATIC_PATH_PREFIXES = ['/build/', '/css/', '/icons/', '/images/'];
const FONT_HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com'];
const BYPASS_PATH_PREFIXES = ['/login', '/logout', '/api'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Form posts, saves and deletes go straight to the network
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    if (sameOrigin && BYPASS_PATH_PREFIXES.some((prefix) => url.pathname === prefix || url.pathname.startsWith(prefix + '/'))) {
        return;
    }

    // Pages: network only, offline page as the fallback. Never cached.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );

        return;
    }

    const isStaticAsset = sameOrigin && STATIC_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
    const isFont = FONT_HOSTS.includes(url.hostname);

    if (isStaticAsset || isFont) {
        event.respondWith(staleWhileRevalidate(event, request));
    }

    // Everything else: browser default (network)
});

/**
 * Answer from the cache when possible and refresh the cached copy in the background.
 */
async function staleWhileRevalidate(event, request) {
    const cache = await caches.open(CACHE_VERSION);
    // "?v=filemtime" versions are new files, but a precached plain URL is still a usable fallback
    const cached = await cache.match(request) || await cache.match(request, { ignoreSearch: true });

    const refresh = fetch(request)
        .then(async (response) => {
            if (response.ok || response.type === 'opaque') {
                await removeOtherVersions(cache, request);
                await cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached || Response.error());

    if (cached) {
        event.waitUntil(refresh);

        return cached;
    }

    return refresh;
}

/**
 * Drop older "?v=..." copies of the same file so the cache does not grow with every CSS change.
 */
async function removeOtherVersions(cache, request) {
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || ! url.search) {
        return;
    }

    const keys = await cache.keys();
    await Promise.all(keys
        .filter((key) => {
            const keyUrl = new URL(key.url);

            return keyUrl.pathname === url.pathname && keyUrl.search && keyUrl.search !== url.search;
        })
        .map((key) => cache.delete(key)));
}
