<script>
    // Upload / download of the 9:16 and 16:9 video files, plus the "Uploaded Videos" modal.
    window.RationUpload = (function () {
        const CHUNK = 1024 * 1024;
        const MAX_BYTES = 500 * 1024 * 1024;
        const EXT = ['mp4', 'mov', 'webm', 'm4v', 'mkv'];
        const FIELD = { nine: 'nine_ratio_link', sixteen: 'sixteen_ratio_link' };
        const LABEL = { nine: '9:16', sixteen: '16:9' };
        let busy = false;
        let toastTimer = null;
        let modalCfg = null;
        let modalWired = false;
        let modalInstance = null;
        let searchTimer = null;

        function token() {
            return document.querySelector('meta[name="csrf-token"]').content;
        }

        function esc(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
            });
        }

        function sizeLabel(bytes) {
            const n = Number(bytes) || 0;
            if (n >= 1024 * 1024 * 1024) return (n / 1024 / 1024 / 1024).toFixed(2) + ' GB';
            if (n >= 1024 * 1024) return (n / 1024 / 1024).toFixed(1) + ' MB';
            return Math.max(1, Math.round(n / 1024)) + ' KB';
        }

        function hex32() {
            const bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            return Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
        }

        function toast(text, kind, sticky) {
            let el = document.getElementById('ration-upload-toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'ration-upload-toast';
                document.body.appendChild(el);
            }
            clearTimeout(toastTimer);
            el.className = kind === 'ok' ? 'is-ok' : (kind === 'err' ? 'is-err' : 'is-run');
            el.textContent = text;
            el.style.display = 'block';
            if (!sticky) {
                toastTimer = setTimeout(function () { el.style.display = 'none'; }, kind === 'err' ? 9000 : 4000);
            }
        }

        async function readJson(res) {
            const text = await res.text();
            let body = {};
            try { body = JSON.parse(text); } catch (e) { body = {}; }
            if (!res.ok && !body.message) {
                if (res.status === 413) body.message = 'The server rejected a piece of the video (HTTP 413). Ask for the upload size limit to be raised.';
                else if (res.status === 419) body.message = 'Your session expired. Reload the page and try again.';
                else body.message = 'The server answered HTTP ' + res.status + '.';
            }
            return body;
        }

        // Sends the file in 1 MB pieces, then asks the server to attach it to the SKU.
        async function upload(ratio, sku, file, onProgress) {
            const ext = String(file.name.split('.').pop() || '').toLowerCase();
            if (!EXT.includes(ext)) throw new Error('Upload an mp4, mov, webm, m4v or mkv video.');
            if (file.size < 1024) throw new Error('That file is too small to be a video.');
            if (file.size > MAX_BYTES) throw new Error('Video must be 500 MB or smaller.');

            const id = hex32();
            const total = Math.max(1, Math.ceil(file.size / CHUNK));
            for (let i = 0; i < total; i++) {
                const fd = new FormData();
                fd.append('upload_id', id);
                fd.append('chunk_index', String(i));
                fd.append('total_chunks', String(total));
                fd.append('filename', file.name);
                fd.append('chunk', file.slice(i * CHUNK, Math.min(file.size, (i + 1) * CHUNK)), 'chunk.bin');
                const res = await fetch('/ration-video/upload-chunk', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token(), 'Accept': 'application/json' },
                    body: fd,
                });
                const body = await readJson(res);
                if (!res.ok || !body.success) throw new Error(body.message || 'The upload failed.');
                if (onProgress) onProgress(Math.round(((i + 1) / total) * 100));
            }

            const fin = await fetch('/ration-video/' + ratio + '/upload', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
                body: JSON.stringify({ sku: sku, upload_id: id, filename: file.name }),
            });
            const done = await readJson(fin);
            if (!fin.ok || !done.success) throw new Error(done.message || 'The upload could not be finished.');
            return done;
        }

        async function runUpload(ratio, sku, file, onDone) {
            if (busy) { toast('Another upload is still running.', 'err'); return null; }
            busy = true;
            toast('Uploading ' + sku + ' (' + LABEL[ratio] + ') 0%', 'run', true);
            try {
                const res = await upload(ratio, sku, file, function (pct) {
                    toast('Uploading ' + sku + ' (' + LABEL[ratio] + ') ' + pct + '%', 'run', true);
                });
                toast('Uploaded ' + file.name + ' to ' + sku + ' (' + LABEL[ratio] + ').', 'ok');
                if (onDone) onDone(res, sku);
                return res;
            } catch (e) {
                toast('Upload failed: ' + (e && e.message ? e.message : e), 'err');
                return null;
            } finally {
                busy = false;
            }
        }

        function pickAndUpload(ratio, sku, onDone) {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'video/mp4,video/quicktime,video/webm,video/x-matroska,.mp4,.mov,.webm,.m4v,.mkv';
            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (file) runUpload(ratio, sku, file, onDone);
            });
            input.click();
        }

        // Upload and Download buttons for a table cell.
        function cellButtons(ratio, hasValue) {
            const up = '<button type="button" class="ration-act ration-up-btn" data-ratio="' + ratio + '" title="Upload a ' + LABEL[ratio] + ' video file"><i class="fas fa-upload"></i></button>';
            if (!hasValue) return up;
            return up + '<button type="button" class="ration-act ration-dl-btn" data-ratio="' + ratio + '" title="Download this video"><i class="fas fa-download"></i></button>';
        }

        function downloadCurrent(ratio, sku) {
            window.location.href = '/ration-video/download?ratio=' + encodeURIComponent(ratio) + '&sku=' + encodeURIComponent(sku);
        }

        // Returns true when the click was one of the upload / download buttons.
        function handleCellClick(e, cell, ratio, onChanged) {
            const up = e.target.closest('.ration-up-btn');
            const dl = e.target.closest('.ration-dl-btn');
            if (!up && !dl) return false;
            const row = cell.getRow();
            const sku = String(row.getData().Sku || '');
            if (!sku) return true;
            if (dl) {
                downloadCurrent(ratio, sku);
                return true;
            }
            pickAndUpload(ratio, sku, function (res) {
                const patch = {};
                patch[res.field || FIELD[ratio]] = res.url;
                row.update(patch).then(function () {
                    row.reformat();
                    if (onChanged) onChanged();
                });
            });
            return true;
        }

        // ---- Uploaded Videos modal ----
        function el(id) { return document.getElementById(id); }

        function fillRatioSelect(select, ratios, withAll) {
            let html = withAll && ratios.length > 1 ? '<option value="">All ratios</option>' : '';
            ratios.forEach(function (r) { html += '<option value="' + r + '">' + LABEL[r] + '</option>'; });
            select.innerHTML = html;
        }

        function closePreview() {
            const video = el('ru-preview');
            video.pause();
            video.removeAttribute('src');
            video.load();
            el('ru-preview-box').style.display = 'none';
        }

        async function loadList() {
            const body = el('ru-rows');
            const params = new URLSearchParams();
            const ratio = el('ru-filter-ratio').value || (modalCfg.ratios.length === 1 ? modalCfg.ratios[0] : '');
            if (ratio) params.set('ratio', ratio);
            const q = el('ru-search').value.trim();
            if (q) params.set('q', q);
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>';
            try {
                const res = await fetch('/ration-video/uploads?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await readJson(res);
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not load uploads.');
                const rows = data.data || [];
                el('ru-count').textContent = rows.length ? (rows.length + ' upload' + (rows.length === 1 ? '' : 's') + (rows.length >= 300 ? ' (latest 300)' : '')) : '';
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No uploads yet.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (r) {
                    const missing = r.exists ? '' : ' <span class="badge bg-danger">file missing</span>';
                    const play = r.exists ? '<button type="button" class="btn btn-sm btn-outline-primary ru-play" data-url="' + esc(r.url) + '" data-ratio="' + esc(r.ratio) + '" title="Play"><i class="fas fa-play"></i></button> ' : '';
                    const dl = r.exists ? '<a class="btn btn-sm btn-outline-success" href="' + esc(r.download_url) + '" title="Download"><i class="fas fa-download"></i></a> ' : '';
                    return '<tr>'
                        + '<td class="fw-semibold">' + esc(r.sku) + '</td>'
                        + '<td>' + esc(r.ratio_label) + '</td>'
                        + '<td style="max-width:260px;word-break:break-all;">' + esc(r.name) + missing + '</td>'
                        + '<td>' + sizeLabel(r.size) + '</td>'
                        + '<td>' + esc(r.uploaded_by) + '</td>'
                        + '<td>' + esc(r.uploaded_at) + '</td>'
                        + '<td class="text-end text-nowrap">' + play + dl
                        + '<button type="button" class="btn btn-sm btn-outline-danger ru-del" data-id="' + r.id + '" data-sku="' + esc(r.sku) + '" title="Delete"><i class="fas fa-trash"></i></button></td>'
                        + '</tr>';
                }).join('');
            } catch (e) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + esc(e.message || e) + '</td></tr>';
            }
        }

        function wireModal() {
            if (modalWired) return;
            modalWired = true;

            el('ru-upload-btn').addEventListener('click', async function () {
                const sku = el('ru-sku').value.trim();
                const file = el('ru-file').files[0];
                const ratio = el('ru-ratio').value;
                if (!sku) { toast('Enter the SKU first.', 'err'); return; }
                if (!file) { toast('Choose a video file first.', 'err'); return; }
                const btn = this;
                const wrap = el('ru-progress-wrap');
                const bar = el('ru-progress');
                btn.disabled = true;
                wrap.style.display = 'block';
                bar.style.width = '0%';
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                const res = await runUpload(ratio, sku, file, function (r, s) {
                    if (modalCfg && modalCfg.onUploaded) modalCfg.onUploaded(r, s, ratio);
                });
                // runUpload reports progress through the toast; mirror the end state on the bar.
                bar.style.width = res ? '100%' : '0%';
                btn.disabled = false;
                btn.innerHTML = original;
                if (res) {
                    el('ru-file').value = '';
                    loadList();
                }
                setTimeout(function () { wrap.style.display = 'none'; }, 1500);
            });

            el('ru-search').addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(loadList, 300);
            });
            el('ru-filter-ratio').addEventListener('change', loadList);
            el('ru-preview-close').addEventListener('click', closePreview);
            el('rationUploadsModal').addEventListener('hidden.bs.modal', closePreview);

            el('ru-rows').addEventListener('click', async function (e) {
                const play = e.target.closest('.ru-play');
                const del = e.target.closest('.ru-del');
                if (play) {
                    const video = el('ru-preview');
                    video.src = play.dataset.url;
                    el('ru-preview-box').style.display = 'block';
                    video.play().catch(function () {});
                    el('ru-preview-box').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    return;
                }
                if (del) {
                    if (!confirm('Delete this uploaded video for ' + del.dataset.sku + '? If it is the SKU\'s current video link, the link is cleared too.')) return;
                    del.disabled = true;
                    try {
                        const res = await fetch('/ration-video/uploads/' + del.dataset.id, {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token() },
                        });
                        const body = await readJson(res);
                        if (!res.ok || !body.success) throw new Error(body.message || 'Could not delete the upload.');
                        if (body.cleared_field && modalCfg && modalCfg.onCleared) modalCfg.onCleared(body.cleared_field, body.sku);
                        toast('Upload deleted.', 'ok');
                        closePreview();
                        loadList();
                    } catch (err) {
                        del.disabled = false;
                        toast(err.message || String(err), 'err');
                    }
                }
            });
        }

        // cfg: { ratios: ['nine'] | ['nine','sixteen'], skus: () => string[], onUploaded(res, sku, ratio), onCleared(field, sku) }
        function openModal(cfg) {
            modalCfg = cfg;
            wireModal();
            fillRatioSelect(el('ru-ratio'), cfg.ratios, false);
            fillRatioSelect(el('ru-filter-ratio'), cfg.ratios, true);
            el('ru-ratio-wrap').style.display = cfg.ratios.length > 1 ? '' : 'none';
            el('ru-filter-wrap').style.display = cfg.ratios.length > 1 ? '' : 'none';
            const skus = (cfg.skus ? cfg.skus() : []).filter(Boolean);
            el('ru-sku-list').innerHTML = skus.map(function (s) { return '<option value="' + esc(s) + '"></option>'; }).join('');
            el('ru-search').value = '';
            el('ru-file').value = '';
            closePreview();
            if (!modalInstance) modalInstance = new bootstrap.Modal(el('rationUploadsModal'));
            modalInstance.show();
            loadList();
        }

        return { cellButtons: cellButtons, handleCellClick: handleCellClick, openModal: openModal, downloadCurrent: downloadCurrent };
    })();
</script>
