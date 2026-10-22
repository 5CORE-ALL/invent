{{--
  "Save and Apply" background hand-off (all pricing pages).

  window.bgSpriceSave(url, updates, { mode, extra, label })
    Sends every S PRC value to /background-sprice-save in a few requests. The server then writes them
    through `url` in a detached process (php artisan sprice:background-save), so the page can be closed
    as soon as the hand-off is done (about a second). Resolves when the server has taken everything;
    rejects if it could not, so the caller can fall back to saving from the browser.

  window.bgSpriceGuard(promise)
    Warns before the tab is closed while the page is still computing / handing over (before the hand-off
    completes). Once bgSpriceSave resolves nothing in the browser is needed any more.

  window.bgSpriceCloseModal(el)
    Closes a Save and Apply modal right after the button is clicked.
--}}
<script>
(function () {
    if (window.bgSpriceSave) return;

    var busy = 0;
    window.addEventListener('beforeunload', function (e) {
        if (busy <= 0) return;
        e.preventDefault();
        e.returnValue = 'Still handing your changes to the server — wait a second before closing.';
        return e.returnValue;
    });

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function toast(type, msg) {
        try {
            if (typeof window.chPromoToast === 'function') return window.chPromoToast(type, msg);
            if (typeof window.amzPefToast === 'function') return window.amzPefToast(type, msg);
            if (window.toastr && typeof window.toastr[type] === 'function') return window.toastr[type](msg);
        } catch (e) { /* ignore */ }
    }
    window.bgSpriceToast = toast;

    /** Keep the "don't close yet" warning on until the given promise settles. */
    window.bgSpriceGuard = function (promise) {
        busy++;
        var release = function () { busy = Math.max(0, busy - 1); };
        if (promise && typeof promise.always === 'function') promise.always(release);
        else if (promise && typeof promise.then === 'function') promise.then(release, release);
        else release();
        return promise;
    };
    window.bgSpriceBegin = function () {
        busy++;
        var done = false;
        return function () { if (!done) { done = true; busy = Math.max(0, busy - 1); } };
    };

    window.bgSpriceCloseModal = function (el) {
        try {
            if (typeof el === 'string') el = document.getElementById(el);
            if (el && window.bootstrap && bootstrap.Modal) {
                var inst = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
                inst.hide();
            }
        } catch (e) { /* ignore */ }
    };

    window.bgSpriceSave = function (url, updates, opts) {
        opts = opts || {};
        var out = $.Deferred();
        updates = Array.isArray(updates) ? updates : [];
        if (!url || !updates.length) return out.resolve({ count: 0 }).promise();

        var size = 5000;
        var chunks = [];
        for (var i = 0; i < updates.length; i += size) chunks.push(updates.slice(i, i + size));
        var accepted = 0;
        var end = window.bgSpriceBegin();

        function next(idx) {
            if (idx >= chunks.length) {
                end();
                return out.resolve({ count: accepted });
            }
            $.ajax({
                url: '{{ route('background.sprice.save', [], false) }}',
                method: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                data: JSON.stringify({
                    _token: csrf(),
                    url: url,
                    updates: chunks[idx],
                    mode: opts.mode || 'batch',
                    extra: opts.extra || {},
                    label: opts.label || 'S PRC'
                })
            }).done(function (res) {
                if (!res || res.success === false) {
                    end();
                    return out.reject(res);
                }
                accepted += chunks[idx].length;
                next(idx + 1);
            }).fail(function (xhr) {
                end();
                out.reject(xhr);
            });
        }
        next(0);
        return out.promise();
    };
})();
</script>
