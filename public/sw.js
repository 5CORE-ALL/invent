/**
 * PWA offline shell — must NOT cache HTML pages: Laravel embeds a fresh CSRF token
 * in each response; serving cached HTML causes "CSRF token mismatch" on every POST.
 */
const CACHE_NAME = 'invent-v4-static';

const urlsToCache = [
  '/js/wms-core.js',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then(function (cache) {
        return Promise.allSettled(
          urlsToCache.map(function (url) {
            return cache.add(url).catch(function (err) {
              console.warn('SW: failed to cache', url, err);
              return Promise.resolve();
            });
          })
        );
      })
      .then(function () {
        return self.skipWaiting();
      })
  );
});

self.addEventListener('fetch', function (event) {
  const url = new URL(event.request.url);

  if (event.request.method !== 'GET') {
    return;
  }
  if (url.origin !== location.origin) {
    return;
  }

  // Never intercept document navigations — always load fresh HTML + CSRF meta.
  if (event.request.mode === 'navigate' || event.request.destination === 'document') {
    return;
  }

  const accept = event.request.headers.get('Accept') || '';
  if (accept.includes('text/html')) {
    return;
  }

  const skipPaths = [
    '/forecast-analysis-data-view',
    '/update-forecast-data',
    '/mfrg-progresses',
    '/ready-to-ship',
    '/api/',
    '/sanctum/',
  ];
  if (skipPaths.some(function (p) {
    return url.pathname.startsWith(p);
  })) {
    return;
  }

  if (event.request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
    return;
  }
  if (accept.includes('application/json')) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then(function (response) {
      return response || fetch(event.request);
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (cacheNames) {
      return Promise.all(
        cacheNames.map(function (cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});
