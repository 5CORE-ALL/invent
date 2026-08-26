/**
 * Temu / Temu 2 view-data multi upload.
 * Native <input multiple> replaces the FileList on every picker open, and PHP
 * max_file_uploads is 20 — so we queue files across clicks and POST in chunks.
 */
(function (global) {
    'use strict';

    var CHUNK_SIZE = 15;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
        var hidden = document.querySelector('input[name="_token"]');
        return hidden ? hidden.value : '';
    }

    function fileKey(f) {
        return [f.name, f.size, f.lastModified].join(':');
    }

    function toast(message, type) {
        if (typeof global.showToast === 'function') {
            global.showToast(message, type || 'info');
            return;
        }
        if (typeof window.showToast === 'function') {
            window.showToast(message, type || 'info');
        }
    }

    function parseJsonSafe(res) {
        return res.text().then(function (text) {
            if (!text) return {};
            try { return JSON.parse(text); } catch (e) { return { message: text }; }
        });
    }

    function init(opts) {
        var form = document.getElementById(opts.formId || 'uploadViewDataForm');
        var input = document.getElementById(opts.inputId || 'viewDataFile');
        var listEl = document.getElementById(opts.listId || 'viewDataFileList');
        var statusEl = document.getElementById(opts.statusId || 'viewDataUploadStatus');
        var submitBtn = opts.submitSelector
            ? document.querySelector(opts.submitSelector)
            : (form ? document.querySelector('button[type="submit"][form="' + form.id + '"]') : null);
        if (!form || !input) return;

        var queued = [];

        function setStatus(html, cls) {
            if (!statusEl) return;
            statusEl.className = 'alert py-2 px-3 mb-0 mt-2 ' + (cls || 'alert-secondary');
            statusEl.innerHTML = html || '';
            statusEl.style.display = html ? '' : 'none';
        }

        function renderList() {
            if (!listEl) return;
            if (!queued.length) {
                listEl.innerHTML = '';
                return;
            }
            var rows = queued.map(function (f, i) {
                var mb = (f.size / 1048576);
                var size = mb >= 0.1 ? mb.toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
                return '<div class="d-flex align-items-center gap-2 py-1" data-key="' + encodeURIComponent(fileKey(f)) + '">'
                    + '<span class="text-muted" style="min-width:1.5rem;">' + (i + 1) + '.</span>'
                    + '<span class="flex-grow-1 text-break">' + f.name + ' <span class="text-muted">(' + size + ')</span></span>'
                    + '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 temu-view-file-remove" data-idx="' + i + '" title="Remove">&times;</button>'
                    + '</div>';
            });
            listEl.innerHTML = '<strong>' + queued.length + ' file(s) queued</strong>'
                + '<div class="text-muted mb-1">Click Choose files again to add more. PHP accepts 20 per request — this page splits automatically.</div>'
                + rows.join('');
        }

        input.removeAttribute('required');
        input.addEventListener('change', function () {
            var added = 0;
            var seen = {};
            queued.forEach(function (f) { seen[fileKey(f)] = true; });
            Array.prototype.forEach.call(this.files || [], function (f) {
                var k = fileKey(f);
                if (seen[k]) return;
                seen[k] = true;
                queued.push(f);
                added++;
            });
            this.value = '';
            renderList();
            if (added) setStatus('', '');
        });

        if (listEl) {
            listEl.addEventListener('click', function (e) {
                var btn = e.target.closest('.temu-view-file-remove');
                if (!btn) return;
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (!isFinite(idx)) return;
                queued.splice(idx, 1);
                renderList();
            });
        }

        function chunkFiles(files) {
            var chunks = [];
            for (var i = 0; i < files.length; i += CHUNK_SIZE) {
                chunks.push(files.slice(i, i + CHUNK_SIZE));
            }
            return chunks;
        }

        function sendChunk(url, files, merge) {
            var fd = new FormData();
            fd.append('_token', csrfToken());
            if (merge) fd.append('merge', '1');
            files.forEach(function (f) { fd.append('files[]', f, f.name); });
            return fetch(url, {
                method: 'POST',
                body: fd,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken()
                },
                credentials: 'same-origin'
            }).then(function (res) {
                return parseJsonSafe(res).then(function (data) {
                    data._status = res.status;
                    data._ok = res.ok;
                    return data;
                });
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!queued.length) {
                setStatus('Choose one or more view files first.', 'alert-warning');
                toast('Choose one or more view files first', 'error');
                return;
            }
            var chunks = chunkFiles(queued);
            var url = form.getAttribute('action');
            var originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading…';
            }

            var imported = 0;
            var skipped = 0;
            var filesDone = 0;

            function step(i) {
                setStatus('Uploading batch ' + (i + 1) + ' of ' + chunks.length
                    + ' (' + chunks[i].length + ' file' + (chunks[i].length === 1 ? '' : 's') + ')…', 'alert-info');
                return sendChunk(url, chunks[i], i > 0).then(function (data) {
                    if (data._status === 419) {
                        throw new Error('Session expired. Refresh the page and try again.');
                    }
                    if (data._status === 413) {
                        throw new Error('Upload too large for the server. Try fewer or smaller files.');
                    }
                    if (!data._ok || data.success === false) {
                        var msg = data.message || data.error;
                        if (!msg && data.errors) {
                            var firstKey = Object.keys(data.errors)[0];
                            msg = firstKey && data.errors[firstKey] && data.errors[firstKey][0]
                                ? data.errors[firstKey][0]
                                : 'Upload failed.';
                        }
                        throw new Error(msg || ('Upload failed (HTTP ' + data._status + ')'));
                    }
                    imported = data.imported != null ? data.imported : imported;
                    skipped += data.skipped != null ? Number(data.skipped) : 0;
                    filesDone += chunks[i].length;
                    if (i + 1 < chunks.length) return step(i + 1);
                    return data;
                });
            }

            step(0).then(function (data) {
                var msg = data.message || ('Imported ' + imported + ' row(s) from ' + filesDone + ' file(s).');
                setStatus(msg, 'alert-success');
                toast(msg, 'success');
                queued = [];
                renderList();
                if (typeof opts.onSuccess === 'function') {
                    try { opts.onSuccess(data); } catch (err) { console.error(err); }
                }
            }).catch(function (err) {
                var msg = (err && err.message) ? err.message : 'Upload failed.';
                setStatus(msg, 'alert-danger');
                toast(msg, 'error');
            }).finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            });
        });
    }

    global.TemuViewDataUpload = { init: init, CHUNK_SIZE: CHUNK_SIZE };
})(window);
