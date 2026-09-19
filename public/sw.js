/**
 * Invent PWA service worker.
 * Cache only safe static assets. Never cache HTML pages (CSRF), JSON APIs,
 * chat messages, unread, files, or other private user data.
 */
const CACHE_NAME = 'invent-chat-v5-static';

const STATIC_ASSETS = [
  '/offline.html',
  '/manifest.json',
  '/images/pwa-icon-192.png',
  '/images/pwa-icon-512.png',
  '/js/wms-core.js',
];

const PRIVATE_PREFIXES = [
  '/chat/inbox',
  '/chat/sync',
  '/chat/unread',
  '/chat/search',
  '/chat/prefs',
  '/chat/files',
  '/chat/channels',
  '/chat/messages',
  '/chat/dms',
  '/chat/groups',
  '/chat/presence',
  '/chat/read-all',
  '/chat/health',
  '/chat/health-event',
  '/api/',
  '/sanctum/',
  '/forecast-analysis-data-view',
  '/update-forecast-data',
  '/mfrg-progresses',
  '/ready-to-ship',
];

function isPrivatePath(pathname) {
  return PRIVATE_PREFIXES.some(function (p) {
    return pathname === p || pathname.startsWith(p + '/') || pathname.startsWith(p + '?');
  });
}

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then(function (cache) {
        return Promise.allSettled(
          STATIC_ASSETS.map(function (url) {
            return cache.add(url).catch(function () {
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

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  let url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  if (url.origin !== location.origin) {
    return;
  }

  if (isPrivatePath(url.pathname)) {
    return;
  }

  if (request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
    return;
  }

  const accept = request.headers.get('Accept') || '';
  if (accept.includes('application/json')) {
    return;
  }

  if (request.mode === 'navigate' || request.destination === 'document' || accept.includes('text/html')) {
    event.respondWith(
      fetch(request).catch(function () {
        return caches.match('/offline.html');
      })
    );
    return;
  }

  if (!['style', 'script', 'image', 'font', 'manifest'].includes(request.destination) && request.destination !== '') {
    return;
  }

  event.respondWith(
    caches.match(request).then(function (cached) {
      const fetched = fetch(request).then(function (response) {
        if (response && response.ok && response.type === 'basic' && STATIC_ASSETS.indexOf(url.pathname) !== -1) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(request, copy);
          });
        }
        return response;
      }).catch(function () {
        return cached;
      });
      return cached || fetched;
    })
  );
});
