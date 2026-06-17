/**
 * Shared JSON helpers for WMS pages (session auth + CSRF).
 */
(function () {
  window.wmsCsrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    var fromMeta = m ? (m.getAttribute('content') || '') : '';
    if (typeof fromMeta === 'string') {
      fromMeta = fromMeta.trim();
    }
    if (fromMeta) {
      return fromMeta;
    }
    if (typeof window.__LaravelCsrfToken === 'string' && window.__LaravelCsrfToken.trim()) {
      return window.__LaravelCsrfToken.trim();
    }
    return '';
  };

  window.wmsJson = async function (url, options) {
    options = options || {};
    options.credentials = options.credentials || 'same-origin';
    options.headers = options.headers || {};
    var token = window.wmsCsrf();
    options.headers['Accept'] = 'application/json';
    options.headers['X-Requested-With'] = 'XMLHttpRequest';
    if (token) {
      options.headers['X-CSRF-TOKEN'] = token;
    }
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      var payload = Object.assign({}, options.body);
      if (token && payload._token === undefined) {
        payload._token = token;
      }
      options.body = JSON.stringify(payload);
    }
    var res = await fetch(url, options);
    var data;
    try {
      data = await res.json();
    } catch (e) {
      data = { message: 'Invalid JSON response' };
    }
    if (!res.ok) {
      var err = new Error(data.message || res.statusText || 'Request failed');
      err.status = res.status;
      err.payload = data;
      throw err;
    }
    return data;
  };
})();
