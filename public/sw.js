/**
 * Invent PWA service worker.
 * Cache only safe static assets. Never cache HTML pages (CSRF), JSON APIs,
 * chat messages, unread, files, or other private user data.
 */
const CACHE_NAME = 'invent-v7-network';

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

// respondWith() must always receive a Response. caches.match() resolves to
// undefined on a miss, which throws "Failed to convert value to 'Response'".
function errorResponse() {
  return new Response('Network error', {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': 'text/plain' },
  });
}

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
    }).then(function () {
      return self.clients.matchAll({ type: 'window' });
    }).then(function (clients) {
      clients.forEach(function (client) {
        client.postMessage({ type: 'APP_UPDATED', cache: CACHE_NAME });
      });
    }).then(function () {
      // A deploy replaces the worker; pick up any upload the previous one was running.
      return imResumeIfNeeded();
    })
  );
});

/* ------------------------------------------------------------------ *
 * Image Master background uploads
 *
 * A page-level fetch() is cancelled the moment the user navigates away, so the
 * upload would stop. Uploads are therefore owned by this service worker, which
 * outlives page navigation. Every job (files + progress) is persisted in
 * IndexedDB, so it can be resumed if the worker is ever stopped by the browser:
 * it is re-kicked on activation, on any page navigation and on any status ping
 * sent by the page monitor (public/js/im-bg-upload.js).
 * ------------------------------------------------------------------ */
const IM_DB_NAME = 'invent-im-bg-upload';
const IM_STORE = 'jobs';
const IM_MAX_IMAGES = 20;
const IM_FILE_RETRIES = 3;
let imRunning = null;
let imDirty = false;

function imDb() {
  return new Promise(function (resolve, reject) {
    const req = indexedDB.open(IM_DB_NAME, 1);
    req.onupgradeneeded = function () {
      req.result.createObjectStore(IM_STORE, { keyPath: 'id' });
    };
    req.onsuccess = function () { resolve(req.result); };
    req.onerror = function () { reject(req.error); };
  });
}

function imTx(mode, fn) {
  return imDb().then(function (db) {
    return new Promise(function (resolve, reject) {
      const tx = db.transaction(IM_STORE, mode);
      const out = fn(tx.objectStore(IM_STORE));
      tx.oncomplete = function () { db.close(); resolve(out && out.result !== undefined ? out.result : undefined); };
      tx.onerror = function () { db.close(); reject(tx.error); };
      tx.onabort = function () { db.close(); reject(tx.error); };
    });
  });
}

function imAll() { return imTx('readonly', function (s) { return s.getAll(); }).then(function (r) { return r || []; }); }
function imPut(job) { job.updated_at = Date.now(); return imTx('readwrite', function (s) { return s.put(job); }); }
function imDel(id) { return imTx('readwrite', function (s) { return s.delete(id); }); }

function imSummary(job) {
  return {
    id: job.id,
    sku: job.sku,
    status: job.status,
    total: (job.files && job.files.length) ? job.files.length : (job.total || 0),
    done: job.next || 0,
    uploaded: (job.urls || []).length,
    message: job.message || '',
    auto_push: !!job.auto_push,
    created_at: job.created_at,
    updated_at: job.updated_at,
  };
}

function imBroadcast() {
  return imAll().then(function (jobs) {
    const payload = { type: 'IM_UPLOAD_STATE', jobs: jobs.map(imSummary) };
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
      clients.forEach(function (c) { c.postMessage(payload); });
    });
  }).catch(function () {});
}

function imSleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

function imPost(url, body, job, isJson) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': job.csrf || '',
  };
  if (isJson) headers['Content-Type'] = 'application/json';
  return fetch(url, {
    method: 'POST',
    headers: headers,
    body: isJson ? JSON.stringify(body) : body,
    credentials: 'same-origin',
  }).then(function (res) {
    return res.text().then(function (text) {
      let json = null;
      try { json = text ? JSON.parse(text) : null; } catch (e) {}
      if (res.status === 419 || res.status === 401 || res.redirected || (!json && res.ok)) {
        const err = new Error('Your session expired. Reload the page, then press Retry.');
        err.fatal = true;
        throw err;
      }
      if (!res.ok || !json) {
        const err = new Error((json && json.message) || ('Server error (HTTP ' + res.status + ')'));
        // 4xx validation errors will not succeed on retry.
        err.fatal = res.status >= 400 && res.status < 500;
        throw err;
      }
      return json;
    });
  });
}

async function imPostRetry(url, makeBody, job, isJson) {
  let lastErr;
  for (let attempt = 1; attempt <= IM_FILE_RETRIES; attempt++) {
    try {
      return await imPost(url, makeBody(), job, isJson);
    } catch (e) {
      lastErr = e;
      if (e && e.fatal) break;
      await imSleep(1500 * attempt);
    }
  }
  throw lastErr || new Error('Request failed');
}

async function imProcess(job) {
  try {
    if (job.status !== 'saving') {
      job.status = 'uploading';
      job.message = '';
      await imPut(job);
      await imBroadcast();

      while ((job.next || 0) < job.files.length) {
        const file = job.files[job.next];
        const json = await imPostRetry('/image-master/upload', function () {
          const fd = new FormData();
          fd.append('sku', job.sku);
          fd.append('files[]', file, file.name);
          return fd;
        }, job, false);
        if (!json.success) throw new Error(json.message || 'Upload failed');
        (json.urls || []).forEach(function (u) { job.urls.push(u); });
        job.next = (job.next || 0) + 1;
        await imPut(job);
        await imBroadcast();
      }

      if (!job.urls.length) throw new Error('No valid image could be uploaded.');
      job.status = 'saving';
      await imPut(job);
      await imBroadcast();
    }

    const finalUrls = (job.base_urls || []).concat(job.urls).slice(0, IM_MAX_IMAGES);
    const saved = await imPostRetry('/image-master/save-pm', function () {
      return {
        sku: job.sku,
        images: finalUrls,
        main_by_marketplace: job.main_by_marketplace || {},
        removed_urls: job.removed_urls || [],
      };
    }, job, true);
    if (!saved.success) throw new Error(saved.message || 'Could not save images to Product Master');

    job.status = 'done';
    job.files = [];            // free the stored blobs
    job.total = job.next || 0;
    job.message = job.urls.length + ' image(s) uploaded and saved.';
    await imPut(job);
    await imBroadcast();
    imNotify(job);
  } catch (e) {
    job.status = 'failed';
    job.message = (e && e.message) || 'Upload failed';
    // A failed "saving" step must redo only the save on retry (files are already uploaded).
    if (job.urls && job.urls.length && (job.next || 0) >= (job.files || []).length) job.resume_save = true;
    try { await imPut(job); } catch (_) {}
    await imBroadcast();
  }
}

function imNotify(job) {
  try {
    if (self.Notification && Notification.permission === 'granted') {
      self.registration.showNotification('Image upload finished', {
        body: job.sku + ': ' + job.message,
        tag: 'im-upload-' + job.id,
        icon: '/images/pwa-icon-192.png',
      });
    }
  } catch (e) {}
}

async function imRun() {
  while (imDirty) {
    imDirty = false;
    for (;;) {
      let jobs;
      try { jobs = await imAll(); } catch (e) { return; }
      const next = jobs
        .filter(function (j) { return j.status === 'queued' || j.status === 'uploading' || j.status === 'saving'; })
        .sort(function (a, b) { return (a.created_at || 0) - (b.created_at || 0); })[0];
      if (!next) break;
      await imProcess(next);
    }
  }
}

function imKick() {
  imDirty = true;
  if (!imRunning) {
    imRunning = imRun().catch(function () {}).then(function () {
      imRunning = null;
      return imBroadcast();
    });
  }
  return imRunning;
}

// Resume anything unfinished (e.g. the worker was stopped mid-upload by the browser).
function imResumeIfNeeded() {
  if (imRunning) return imRunning;
  return imAll().then(function (jobs) {
    const pending = jobs.some(function (j) { return j.status === 'queued' || j.status === 'uploading' || j.status === 'saving'; });
    return pending ? imKick() : imBroadcast();
  }).catch(function () {});
}

function imHandleMessage(event) {
  const data = event.data || {};
  switch (data.type) {
    case 'IM_UPLOAD_START': {
      const j = data.job;
      if (!j || !j.id || !Array.isArray(j.files) || !j.files.length) return null;
      const job = {
        id: j.id,
        sku: String(j.sku || ''),
        files: j.files,
        next: 0,
        urls: [],
        base_urls: j.base_urls || [],
        removed_urls: j.removed_urls || [],
        main_by_marketplace: j.main_by_marketplace || {},
        csrf: j.csrf || '',
        auto_push: !!j.auto_push,
        status: 'queued',
        message: '',
        created_at: Date.now(),
      };
      return imPut(job).then(function () { return imBroadcast(); }).then(function () { return imKick(); });
    }
    case 'IM_UPLOAD_STATE_REQ':
      return imResumeIfNeeded();
    case 'IM_UPLOAD_RETRY':
      return imAll().then(function (jobs) {
        const job = jobs.filter(function (x) { return x.id === data.id; })[0];
        if (!job || job.status !== 'failed') return null;
        if (data.csrf) job.csrf = data.csrf;
        job.status = job.resume_save ? 'saving' : 'queued';
        job.resume_save = false;
        job.message = '';
        return imPut(job).then(function () { return imKick(); });
      });
    case 'IM_UPLOAD_DISMISS':
      return imDel(data.id).then(function () { return imBroadcast(); });
    default:
      return null;
  }
}

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }
  if (event.data && typeof event.data.type === 'string' && event.data.type.indexOf('IM_UPLOAD_') === 0) {
    const p = imHandleMessage(event);
    if (p) event.waitUntil(p);
  }
});

self.addEventListener('fetch', function (event) {
  const request = event.request;

  // Any page navigation is a chance to resume an unfinished background upload.
  if (request.mode === 'navigate') {
    event.waitUntil(imResumeIfNeeded());
  }

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
      fetch(request, { cache: 'no-store' }).catch(function () {
        return caches.match('/offline.html').then(function (offline) {
          return offline || errorResponse();
        });
      })
    );
    return;
  }

  // Scripts and styles always come from the network, so a deploy is visible
  // without clearing site data. The HTTP cache is skipped on purpose.
  if (request.destination === 'script' || request.destination === 'style') {
    event.respondWith(
      fetch(request, { cache: 'no-store' }).catch(function () {
        return caches.match(request).then(function (cached) {
          return cached || errorResponse();
        });
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
        return cached || errorResponse();
      });
      return cached || fetched;
    })
  );
});
