/**
 * Image Master background uploads - page side.
 *
 * Uploads are executed by the service worker (public/sw.js), not by the page, so they keep
 * running when the user navigates to another page. This script (loaded on every page via the
 * main layout):
 *   - hands upload jobs to the service worker            -> ImBgUpload.start()
 *   - shows live progress on whatever page is open       -> floating panel (bottom-left)
 *   - pings the worker while a job is active so the browser keeps it alive / resumes it
 *   - lets the Image Master page react to finished jobs  -> ImBgUpload.onUpdate()
 */
(function () {
    'use strict';
    if (window.ImBgUpload) return;

    var ACTIVE = { queued: 1, uploading: 1, saving: 1 };
    var PING_MS = 5000;
    var jobs = [];
    var listeners = [];
    var pingTimer = null;
    var doneTimers = {};
    var panel = null;

    function supported() {
        return !!(('serviceWorker' in navigator) && ('indexedDB' in window) && window.isSecureContext);
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function worker() {
        if (!supported()) return Promise.resolve(null);
        return Promise.race([
            navigator.serviceWorker.ready.then(function (reg) { return reg.active || null; }),
            new Promise(function (resolve) { setTimeout(function () { resolve(null); }, 4000); })
        ]).catch(function () { return null; });
    }

    function post(msg) {
        return worker().then(function (sw) {
            if (!sw) return false;
            sw.postMessage(msg);
            return true;
        });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function anyActive() {
        return jobs.some(function (j) { return ACTIVE[j.status]; });
    }

    // ── floating progress panel ────────────────────────────────────────────────
    function ensurePanel() {
        if (panel) return panel;
        panel = document.createElement('div');
        panel.id = 'imBgUploadPanel';
        panel.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:2147483000;width:320px;max-width:calc(100vw - 32px);'
            + 'font:13px/1.35 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;display:none;';
        panel.addEventListener('click', function (e) {
            var b = e.target.closest('[data-im-act]');
            if (!b) return;
            var id = b.getAttribute('data-id');
            if (b.getAttribute('data-im-act') === 'retry') api.retry(id);
            else api.dismiss(id);
        });
        document.body.appendChild(panel);
        return panel;
    }

    function render() {
        if (!jobs.length) {
            if (panel) panel.style.display = 'none';
            return;
        }
        var p = ensurePanel();
        p.style.display = 'block';
        p.innerHTML = jobs.map(function (j) {
            var total = Number(j.total) || 0;
            var done = Math.min(Number(j.done) || 0, total);
            var pct = j.status === 'done' ? 100 : (total ? Math.round(done / total * 100) : 0);
            var color = j.status === 'failed' ? '#dc3545' : (j.status === 'done' ? '#198754' : '#0d6efd');
            var title, detail = '';
            if (j.status === 'queued') title = 'Waiting to upload';
            else if (j.status === 'uploading') title = 'Uploading ' + done + '/' + total + ' image(s)';
            else if (j.status === 'saving') title = 'Saving to Product Master…';
            else if (j.status === 'done') { title = 'Upload complete'; detail = j.message; }
            else { title = 'Upload failed'; detail = j.message; }
            var btn = j.status === 'failed'
                ? '<button data-im-act="retry" data-id="' + esc(j.id) + '" style="border:0;background:#0d6efd;color:#fff;border-radius:4px;padding:1px 8px;margin-right:6px;">Retry</button>'
                : '';
            var close = !ACTIVE[j.status]
                ? '<button data-im-act="dismiss" data-id="' + esc(j.id) + '" title="Dismiss" style="border:0;background:transparent;color:#6c757d;font-size:16px;line-height:1;padding:0 2px;">&times;</button>'
                : '';
            return '<div style="background:#fff;border:1px solid #dee2e6;border-left:4px solid ' + color + ';border-radius:6px;'
                + 'box-shadow:0 4px 14px rgba(0,0,0,.18);padding:8px 10px;margin-top:8px;">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">'
                + '<strong style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Image Master · ' + esc(j.sku) + '</strong>'
                + '<span style="white-space:nowrap;">' + btn + close + '</span></div>'
                + '<div style="color:#495057;">' + esc(title) + '</div>'
                + (detail ? '<div style="color:' + (j.status === 'failed' ? '#dc3545' : '#6c757d') + ';word-break:break-word;">' + esc(detail) + '</div>' : '')
                + '<div style="height:5px;background:#e9ecef;border-radius:3px;margin-top:5px;overflow:hidden;">'
                + '<div style="height:100%;width:' + pct + '%;background:' + color + ';transition:width .3s;"></div></div></div>';
        }).join('');
    }

    // ── state from the service worker ──────────────────────────────────────────
    function applyState(list) {
        jobs = (list || []).slice().sort(function (a, b) { return (a.created_at || 0) - (b.created_at || 0); });
        render();

        // Finished jobs that nobody needs to act on clear themselves after a few seconds.
        jobs.forEach(function (j) {
            if (j.status === 'done' && !j.auto_push && !doneTimers[j.id]) {
                doneTimers[j.id] = setTimeout(function () {
                    delete doneTimers[j.id];
                    api.dismiss(j.id);
                }, 8000);
            }
        });

        schedulePing();
        listeners.forEach(function (fn) { try { fn(jobs.slice()); } catch (e) { /* ignore */ } });
    }

    // While a job is active keep poking the worker: it keeps the worker alive on a long upload
    // and resumes the job if the browser stopped the worker.
    function schedulePing() {
        if (anyActive()) {
            if (pingTimer === null) {
                pingTimer = setInterval(function () { post({ type: 'IM_UPLOAD_STATE_REQ' }); }, PING_MS);
            }
        } else if (pingTimer !== null) {
            clearInterval(pingTimer);
            pingTimer = null;
        }
    }

    var api = {
        supported: supported,

        /**
         * Hand files to the background uploader.
         * opts: { sku, files: File[], base_urls: string[], removed_urls: string[],
         *         main_by_marketplace: object, auto_push: boolean }
         * Resolves true when the worker accepted the job, false when the caller should upload in the foreground.
         */
        start: function (opts) {
            if (!supported() || !opts || !opts.files || !opts.files.length) return Promise.resolve(false);
            var id = 'im-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
            return post({
                type: 'IM_UPLOAD_START',
                job: {
                    id: id,
                    sku: opts.sku,
                    files: opts.files,
                    base_urls: opts.base_urls || [],
                    removed_urls: opts.removed_urls || [],
                    main_by_marketplace: opts.main_by_marketplace || {},
                    auto_push: !!opts.auto_push,
                    csrf: csrf()
                }
            }).then(function (ok) {
                if (ok) {
                    // Show it immediately; the worker's broadcast will replace this.
                    applyState(jobs.concat([{
                        id: id, sku: opts.sku, status: 'queued', total: opts.files.length, done: 0,
                        uploaded: 0, message: '', auto_push: !!opts.auto_push, created_at: Date.now()
                    }]));
                }
                return ok;
            });
        },

        retry: function (id) { return post({ type: 'IM_UPLOAD_RETRY', id: id, csrf: csrf() }); },

        dismiss: function (id) {
            jobs = jobs.filter(function (j) { return j.id !== id; });
            render();
            return post({ type: 'IM_UPLOAD_DISMISS', id: id });
        },

        jobs: function () { return jobs.slice(); },

        onUpdate: function (fn) {
            listeners.push(fn);
            if (jobs.length) { try { fn(jobs.slice()); } catch (e) { /* ignore */ } }
        }
    };

    window.ImBgUpload = api;

    if (supported()) {
        navigator.serviceWorker.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'IM_UPLOAD_STATE') applyState(event.data.jobs);
        });
        if (navigator.serviceWorker.startMessages) navigator.serviceWorker.startMessages();
        // Ask for the current state on every page load: restores progress after navigation and
        // lets the worker resume an interrupted job.
        post({ type: 'IM_UPLOAD_STATE_REQ' });
    }
})();
