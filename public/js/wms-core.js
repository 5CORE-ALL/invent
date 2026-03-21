/**
 * Shared JSON helpers for WMS pages (session auth + CSRF).
 */
(function () {
  window.wmsCsrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };

  window.wmsJson = async function (url, options) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['Accept'] = 'application/json';
    options.headers['X-Requested-With'] = 'XMLHttpRequest';
    options.headers['X-CSRF-TOKEN'] = window.wmsCsrf();
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
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
