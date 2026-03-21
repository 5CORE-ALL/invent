/* Lightweight PWA shell for Incoming (installability + future caching hooks) */
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function () {
    /* Network-only: avoids stale admin HTML while still allowing install */
});
