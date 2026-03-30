const CACHE_NAME = 'invent-v3';
const urlsToCache = [
  '/',
  '/wms',
  '/wms/scan',
  '/wms/pick',
  '/wms/putaway',
  '/js/wms-core.js'
];

// Install service worker with error handling
self.addEventListener('install', function(event) {
  console.log('Service Worker installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Opened cache');
        // Cache URLs one by one with error handling
        return Promise.allSettled(
          urlsToCache.map(url => 
            cache.add(url).catch(err => {
              console.warn('Failed to cache:', url, err);
              return Promise.resolve();
            })
          )
        );
      })
      .then(() => {
        console.log('Service Worker installed successfully');
        self.skipWaiting(); // Activate immediately
      })
      .catch(err => {
        console.error('Service Worker installation failed:', err);
      })
  );
});

// Cache and return requests
self.addEventListener('fetch', function(event) {
  const url = new URL(event.request.url);

  // Never intercept: AJAX/API calls, non-GET requests, or cross-origin requests
  if (event.request.method !== 'GET') return;
  if (url.origin !== location.origin) return;
  // Skip data endpoints and any XHR/fetch calls (identified by header or path patterns)
  const skipPaths = ['/forecast-analysis-data-view', '/update-forecast-data', '/mfrg-progresses', '/ready-to-ship', '/api/', '/sanctum/'];
  if (skipPaths.some(p => url.pathname.startsWith(p))) return;
  // Skip if it's an AJAX request
  if (event.request.headers.get('X-Requested-With') === 'XMLHttpRequest') return;
  if (event.request.headers.get('Accept')?.includes('application/json')) return;

  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});

// Update service worker
self.addEventListener('activate', function(event) {
  console.log('Service Worker activating...');
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
