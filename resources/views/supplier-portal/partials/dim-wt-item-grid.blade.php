@php
    $prefix = $prefix ?? 'spDw';
    $editable = (bool) ($editable ?? false);
    $variant = $variant ?? 'pkg';
    if (! in_array($variant, ['pkg', 'cover', 'sku'], true)) {
        $variant = 'pkg';
    }
    $isCover = $variant === 'cover';
    $isSkuOnly = $variant === 'sku';
    $category = $category ?? '';
    $colCount = ($isSkuOnly ? 5 : 5) + ($editable ? 1 : 0);
    $dataUrl = $editable
        ? route('supplier-portal.admin.dim-wt-data')
        : route('supplier-portal.dim-wt-data');
    $updateUrl = $isCover
        ? route('purchase-order.item-pkg-cover')
        : url('/instructions-item-pkg/update');
    $uploadUrl = $editable ? route('supplier-portal.admin.sku-files.store') : '';
    $destroyFileUrl = $editable ? url('/supplier-portal/manage/sku-files') : '';
    $title = $isSkuOnly ? 'Dim / Wt — SKU' : ($isCover ? 'Dim / Wt — Itm pkg Cover' : 'Dim / Wt — item PKG');
    $sub = $isSkuOnly
        ? 'Live data from /dim-wt-master'
        : 'Live data from /dim-wt-master — edit here updates that page too';
@endphp
<div
    class="sp-dw-wrap"
    id="{{ $prefix }}Wrap"
    data-sp-dw
    data-variant="{{ $variant }}"
    data-editable="{{ $editable ? '1' : '0' }}"
    data-url="{{ $dataUrl }}"
    data-update="{{ $updateUrl }}"
    data-category="{{ $category }}"
    data-upload="{{ $uploadUrl }}"
    data-file-destroy="{{ $destroyFileUrl }}"
>
    <div class="sp-dw-toolbar">
        <div>
            <strong>{{ $title }}</strong>
            <div class="sp-dw-sub">{{ $sub }}</div>
        </div>
        @if($editable)
            <div class="sp-dw-actions">
                <button type="button" class="sp-dw-btn is-active" data-sp-dw-mode="view">View</button>
                <button type="button" class="sp-dw-btn" data-sp-dw-mode="edit">Edit</button>
                <button type="button" class="sp-dw-btn" data-sp-dw-sync>Sync</button>
            </div>
        @endif
    </div>
    <div class="sp-dw-filters">
        <label>Parent <input type="search" data-sp-dw-filter="parent" placeholder="Search parent"></label>
        <label>SKU <input type="search" data-sp-dw-filter="sku" placeholder="Search SKU"></label>
        @if($isSkuOnly)
            <label>Files
                <select data-sp-dw-filter="pkg">
                    <option value="all">All</option>
                    <option value="missing">Missing</option>
                    <option value="has">Has data</option>
                </select>
            </label>
        @endif
        @unless($isSkuOnly)
            <label>{{ $isCover ? 'Itm pkg Cover' : 'item PKG' }}
                <select data-sp-dw-filter="pkg">
                    <option value="all">All</option>
                    <option value="missing">Missing</option>
                    <option value="has">Has data</option>
                </select>
            </label>
        @endunless
    </div>
    <div class="sp-dw-table-wrap">
        <table class="sp-dw-table">
            <thead>
                <tr>
                    <th class="sp-dw-check"><input type="checkbox" data-sp-dw-all title="Select all"></th>
                    <th>Img</th>
                    <th>Parent</th>
                    <th>SKU</th>
                    @if($isSkuOnly)
                        <th>Files</th>
                    @else
                        <th>{{ $isCover ? 'Itm pkg Cover' : 'item PKG' }}</th>
                    @endif
                    @if($editable)
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody data-sp-dw-body>
                <tr><td colspan="{{ $colCount }}" class="sp-dw-empty">Loading dim-wt-master data…</td></tr>
            </tbody>
        </table>
    </div>
</div>
@once
<style>
    .sp-dw-wrap { margin-top: 18px; border: 1px solid #e8e8e8; border-radius: 10px; background: #fff; overflow: hidden; }
    .sp-dw-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid #eee; }
    .sp-dw-sub { color: #6b6b6b; font-size: 12px; margin-top: 2px; }
    .sp-dw-actions { display: flex; gap: 6px; }
    .sp-dw-btn { border: 1px solid #ddd; background: #fff; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .sp-dw-btn.is-active, .sp-dw-btn:hover { border-color: #e31c23; color: #e31c23; }
    .sp-dw-filters { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 14px; background: #fafafa; border-bottom: 1px solid #eee; }
    .sp-dw-filters label { font-size: 11px; color: #666; font-weight: 600; display: flex; flex-direction: column; gap: 3px; min-width: 140px; }
    .sp-dw-filters input, .sp-dw-filters select { font-weight: 500; font-size: 12px; border: 1px solid #ddd; border-radius: 6px; padding: 5px 7px; background: #fff; }
    .sp-dw-table-wrap { max-height: 480px; overflow: auto; }
    .sp-dw-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sp-dw-table th { position: sticky; top: 0; background: #eef4ff; color: #111; text-align: left; padding: 8px; font-weight: 700; white-space: nowrap; }
    .sp-dw-table td { border-bottom: 1px solid #f0f0f0; padding: 7px 8px; vertical-align: middle; }
    .sp-dw-table tbody tr:hover { background: #f8fbff; }
    .sp-dw-table tbody tr.sp-dw-parent,
    .sp-dw-table tbody tr.sp-dw-parent:nth-child(even) { background: #fffef2; }
    .sp-dw-table tbody tr.sp-dw-parent:hover { background: #fdf3a8; }
    .sp-dw-empty { color: #888; padding: 16px !important; }
    .sp-dw-check { width: 36px; text-align: center; }
    .sp-dw-table img.sp-dw-thumb { width: 30px; height: 30px; object-fit: cover; border-radius: 4px; cursor: zoom-in; }
    #spDwImgHover {
        position: fixed; display: none; z-index: 200060; pointer-events: none;
        max-width: min(420px, 90vw); max-height: min(420px, 80vh);
        object-fit: contain; background: #fff; border-radius: 10px; padding: 4px;
        box-shadow: 0 8px 32px rgba(0,0,0,.35); border: 1px solid rgba(0,0,0,.08);
    }
    .sp-dw-pkg, .sp-dw-cover { border: 0; background: transparent; cursor: pointer; padding: 0; line-height: 1; color: #2563eb; }
    .sp-dw-pkg svg, .sp-dw-cover svg { width: 16px; height: 16px; display: block; }
    .sp-dw-pkg.is-ok svg { color: #28a745; }
    .sp-dw-pkg.is-miss svg { color: #dc3545; }
    .sp-dw-dash { color: #888; }
    .sp-dw-table textarea, .sp-dw-table input.sp-dw-url { width: 100%; min-width: 160px; border: 1px solid #ddd; border-radius: 4px; padding: 4px 6px; font: inherit; }
    .sp-dw-save, .sp-dw-edit, .sp-dw-del { border: 0; border-radius: 5px; padding: 4px 8px; font-size: 12px; font-weight: 700; margin: 0 2px 0 0; cursor: pointer; }
    .sp-dw-save { background: #e31c23; color: #fff; margin-top: 4px; }
    .sp-dw-edit { background: #111; color: #fff; }
    .sp-dw-del { background: #fff; color: #dc3545; border: 1px solid #f1c0c0; }
    .sp-dw-file { display: flex; align-items: center; gap: 6px; margin: 2px 0; font-size: 12px; }
    .sp-dw-file a { color: #2563eb; text-decoration: none; }
    .sp-dw-file a:hover { text-decoration: underline; }
    .sp-dw-file-del { border: 0; background: transparent; color: #dc3545; cursor: pointer; font-weight: 700; padding: 0 4px; }
    .sp-dw-modal {
        position: fixed; inset: 0; z-index: 2100; display: none;
        align-items: center; justify-content: center; background: rgba(0,0,0,.35);
    }
    .sp-dw-modal.is-open { display: flex; }
    .sp-dw-modal-card { background: #fff; border-radius: 10px; max-width: 520px; width: 92%; padding: 16px 18px; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
    .sp-dw-modal-card h4 { margin: 0 0 8px; font-size: 16px; }
    .sp-dw-modal-card p { margin: 0; white-space: pre-wrap; color: #333; font-size: 14px; }
    .sp-dw-modal-card img { max-width: 100%; max-height: 360px; display: block; }
</style>
<script>
(function () {
    if (window.spInitDimWtGrid) return;
    var CSRF = @json(csrf_token());
    var SEARCH_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>';
    var IMAGE_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><circle cx="8.5" cy="10.5" r="1.5"></circle><path d="M21 16l-5-5-4 4-2-2-5 5"></path></svg>';
    window.spDimWtRows = null;
    window.spRefreshDimWtGrids = function () {
        window.spDimWtRows = null;
        document.querySelectorAll('[data-sp-dw]').forEach(function (w) {
            if (typeof w._spDwLoad === 'function') w._spDwLoad(true);
        });
    };
    window.spInitDimWtGrid = function (wrap) {
        if (!wrap || wrap.dataset.spDwReady === '1') return;
        wrap.dataset.spDwReady = '1';
        var body = wrap.querySelector('[data-sp-dw-body]');
        var editable = wrap.getAttribute('data-editable') === '1';
        var variant = wrap.getAttribute('data-variant') || 'pkg';
        var isCover = variant === 'cover';
        var isSkuOnly = variant === 'sku';
        var colSpan = 5 + (editable ? 1 : 0);
        var url = wrap.getAttribute('data-url');
        var updateUrl = wrap.getAttribute('data-update');
        var category = wrap.getAttribute('data-category') || '';
        var uploadUrl = wrap.getAttribute('data-upload') || '';
        var fileDestroyUrl = wrap.getAttribute('data-file-destroy') || '';
        var mode = 'view';
        var editingId = '';
        var rows = [];

        function esc(v) {
            return String(v == null ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function val(row, key) { return String(row[key] || '').trim(); }
        function isParentSku(sku) { return String(sku || '').toUpperCase().indexOf('PARENT') !== -1; }

        var modal = document.getElementById('spDwModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'spDwModal';
            modal.className = 'sp-dw-modal';
            modal.innerHTML = '<div class="sp-dw-modal-card"><h4 id="spDwModalTitle">item PKG</h4><div id="spDwModalBody"></div></div>';
            document.body.appendChild(modal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.classList.remove('is-open');
            });
        }

        function filtered() {
            var parent = (wrap.querySelector('[data-sp-dw-filter="parent"]')?.value || '').toLowerCase();
            var sku = (wrap.querySelector('[data-sp-dw-filter="sku"]')?.value || '').toLowerCase();
            var pkg = wrap.querySelector('[data-sp-dw-filter="pkg"]')?.value || 'all';
            var field = isCover ? 'item_pkg_cover' : 'instructions_item_pkg';
            return rows.filter(function (row) {
                if (parent && val(row, 'Parent').toLowerCase().indexOf(parent) === -1) return false;
                if (sku && val(row, 'SKU').toLowerCase().indexOf(sku) === -1) return false;
                if (isSkuOnly) {
                    var files = rowFiles(row);
                    var hasFiles = files.length > 0;
                    if (pkg === 'missing' && hasFiles) return false;
                    if (pkg === 'has' && !hasFiles) return false;
                    return true;
                }
                var has = val(row, field) !== '';
                if (pkg === 'missing' && has) return false;
                if (pkg === 'has' && !has) return false;
                return true;
            });
        }

        function rowIsEditing(row) {
            return editable && !isParentSku(row.SKU) && (mode === 'edit' || String(editingId) === String(row.id));
        }

        function rowFiles(row) {
            var all = row.files || {};
            return Array.isArray(all[category]) ? all[category] : [];
        }

        function familyImage(row) {
            var img = val(row, 'image_path');
            if (img) return img;
            var parent = val(row, 'Parent') || val(row, 'SKU');
            if (!parent) return '';
            for (var i = 0; i < rows.length; i++) {
                var other = rows[i];
                if (val(other, 'image_path') === '') continue;
                if (val(other, 'Parent') === parent || val(other, 'SKU') === parent) {
                    return val(other, 'image_path');
                }
            }
            return '';
        }

        function filesCell(row) {
            var list = rowFiles(row);
            var html = list.map(function (f) {
                var name = esc(f.title || f.file_name || 'File');
                var view = esc(f.view || f.url || '#');
                var dl = esc(f.download || f.url || '#');
                var extra = editable
                    ? ' <button type="button" class="sp-dw-file-del" data-file-id="' + esc(f.id) + '" title="Remove file">×</button>'
                    : '';
                return '<div class="sp-dw-file">' +
                    '<a href="' + view + '" target="_blank" rel="noopener" title="View">' + name + '</a>' +
                    '<a href="' + dl + '">Download</a>' + extra +
                    '</div>';
            }).join('');
            if (rowIsEditing(row)) {
                html += '<input type="file" data-field="sku_file" multiple>';
            }
            return html || '<span class="sp-dw-dash">—</span>';
        }

        function actionCell(row) {
            if (!editable) return '';
            if (isParentSku(row.SKU)) return '<td></td>';
            if (rowIsEditing(row)) {
                return '<td><button type="button" class="sp-dw-save" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '" data-parent="' + esc(row.Parent || '') + '">Save</button>' +
                    '<button type="button" class="sp-dw-del" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '">Delete</button></td>';
            }
            return '<td><button type="button" class="sp-dw-edit" data-id="' + esc(row.id) + '">Edit</button>' +
                '<button type="button" class="sp-dw-del" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '">Delete</button></td>';
        }

        function extraCell(row) {
            if (isSkuOnly) return filesCell(row);
            if (isCover) {
                var cover = val(row, 'item_pkg_cover');
                if (rowIsEditing(row)) {
                    return '<input class="sp-dw-url" data-field="item_pkg_cover" value="' + esc(cover) + '" placeholder="Image URL or path">' +
                        '<input type="file" accept="image/*" data-field="item_pkg_cover_file">';
                }
                if (!cover) return '<span class="sp-dw-dash">—</span>';
                return '<button type="button" class="sp-dw-cover" data-cover="' + esc(cover) + '" title="Open Itm pkg Cover">' + IMAGE_SVG + '</button>';
            }
            var text = val(row, 'instructions_item_pkg');
            var has = text !== '';
            if (rowIsEditing(row)) {
                return '<textarea rows="2" data-field="instructions_item_pkg">' + esc(text) + '</textarea>';
            }
            return '<button type="button" class="sp-dw-pkg ' + (has ? 'is-ok' : 'is-miss') + '" data-pkg="' + esc(text || 'No instructions available') + '" title="' + esc(has ? text : 'No instructions available') + '">' + SEARCH_SVG + '</button>';
        }

        function render() {
            var list = filtered();
            if (!list.length) {
                body.innerHTML = '<tr><td colspan="' + colSpan + '" class="sp-dw-empty">No dim-wt rows match these filters.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function (row) {
                var img = familyImage(row);
                return '<tr data-id="' + esc(row.id) + '"' + (isParentSku(row.SKU) ? ' class="sp-dw-parent"' : '') + '>' +
                    '<td class="sp-dw-check"><input type="checkbox" data-sp-dw-row value="' + esc(row.SKU) + '"></td>' +
                    '<td>' + (img ? '<img class="sp-dw-thumb no-img-hover" src="' + esc(img) + '" alt="">' : '—') + '</td>' +
                    '<td title="' + esc(row.Parent) + '">' + esc(row.Parent || '—') + '</td>' +
                    '<td title="' + esc(row.SKU) + '">' + esc(row.SKU) + '</td>' +
                    '<td>' + extraCell(row) + '</td>' +
                    actionCell(row) +
                    '</tr>';
            }).join('');
        }

        function load(force) {
            body.innerHTML = '<tr><td colspan="' + colSpan + '" class="sp-dw-empty">Loading dim-wt-master data…</td></tr>';
            var req = (!force && window.spDimWtRows)
                ? Promise.resolve(window.spDimWtRows)
                : fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'ts=' + Date.now(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (json) {
                    window.spDimWtRows = json.data || [];
                    return window.spDimWtRows;
                });
            req.then(function (data) {
                rows = data || [];
                render();
            }).catch(function () {
                body.innerHTML = '<tr><td colspan="' + colSpan + '" class="sp-dw-empty">Could not load /dim-wt-master data.</td></tr>';
            });
        }
        wrap._spDwLoad = load;

        wrap.addEventListener('input', function (e) {
            if (e.target.matches('[data-sp-dw-filter]')) render();
        });
        wrap.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-dw-filter]')) render();
            if (e.target.matches('[data-sp-dw-all]')) {
                wrap.querySelectorAll('[data-sp-dw-row]').forEach(function (cb) { cb.checked = e.target.checked; });
            }
        });
        wrap.querySelectorAll('[data-sp-dw-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.getAttribute('data-sp-dw-mode') || 'view';
                editingId = '';
                wrap.querySelectorAll('[data-sp-dw-mode]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                render();
            });
        });
        wrap.querySelector('[data-sp-dw-sync]')?.addEventListener('click', function () {
            window.spRefreshDimWtGrids();
        });
        wrap.addEventListener('click', function (e) {
            var view = e.target.closest('.sp-dw-pkg');
            if (view) {
                document.getElementById('spDwModalTitle').textContent = 'item PKG';
                document.getElementById('spDwModalBody').innerHTML = '<p></p>';
                document.querySelector('#spDwModalBody p').textContent = view.getAttribute('data-pkg') || '';
                modal.classList.add('is-open');
                return;
            }
            var coverBtn = e.target.closest('.sp-dw-cover');
            if (coverBtn) {
                var cover = coverBtn.getAttribute('data-cover') || '';
                document.getElementById('spDwModalTitle').textContent = 'Itm pkg Cover';
                document.getElementById('spDwModalBody').innerHTML = cover
                    ? '<img src="' + esc(cover) + '" alt="Itm pkg Cover">'
                    : '<p>No cover</p>';
                modal.classList.add('is-open');
                return;
            }
            var editBtn = e.target.closest('.sp-dw-edit');
            if (editBtn) {
                editingId = editBtn.getAttribute('data-id') || '';
                mode = 'view';
                wrap.querySelectorAll('[data-sp-dw-mode]').forEach(function (b) {
                    b.classList.toggle('is-active', b.getAttribute('data-sp-dw-mode') === 'view');
                });
                render();
                return;
            }
            var fileDel = e.target.closest('.sp-dw-file-del');
            if (fileDel) {
                if (!confirm('Remove this file?')) return;
                fileDel.disabled = true;
                fetch(fileDestroyUrl + '/' + encodeURIComponent(fileDel.getAttribute('data-file-id') || ''), {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok || res.j.success === false) throw new Error(res.j.message || 'Could not remove file');
                        window.spRefreshDimWtGrids();
                    }).catch(function (err) {
                        alert(err.message || 'Could not remove file.');
                        fileDel.disabled = false;
                    });
                return;
            }
            var delBtn = e.target.closest('.sp-dw-del');
            if (delBtn) {
                if (isSkuOnly) {
                    if (!confirm('Remove all files for this SKU?')) return;
                    delBtn.disabled = true;
                    fetch(fileDestroyUrl, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ category: category, sku: delBtn.getAttribute('data-sku') || '', _token: CSRF })
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (res) {
                            if (!res.ok || res.j.success === false) throw new Error(res.j.message || 'Could not remove files');
                            window.spRefreshDimWtGrids();
                        }).catch(function (err) {
                            alert(err.message || 'Could not remove files.');
                            delBtn.disabled = false;
                        });
                    return;
                }
                if (!confirm('Clear this row on /dim-wt-master?')) return;
                saveDimWt(delBtn, delBtn.closest('tr'), true);
                return;
            }
            var btn = e.target.closest('.sp-dw-save');
            if (!btn) return;
            if (isSkuOnly) {
                saveSkuFiles(btn, btn.closest('tr'));
                return;
            }
            saveDimWt(btn, btn.closest('tr'), false);
        });

        function saveSkuFiles(btn, tr) {
            var input = tr.querySelector('[data-field="sku_file"]');
            var files = input && input.files ? Array.prototype.slice.call(input.files) : [];
            if (!files.length) {
                alert('Choose one or more files to upload.');
                return;
            }
            btn.disabled = true;
            var chain = Promise.resolve();
            files.forEach(function (file) {
                chain = chain.then(function () {
                    var fd = new FormData();
                    fd.append('_token', CSRF);
                    fd.append('category', category);
                    fd.append('sku', btn.getAttribute('data-sku') || '');
                    fd.append('parent', btn.getAttribute('data-parent') || '');
                    fd.append('file', file);
                    return fetch(uploadUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (res) {
                            if (!res.ok || res.j.success === false) throw new Error(res.j.message || 'Upload failed');
                        });
                });
            });
            chain.then(function () {
                editingId = '';
                window.spRefreshDimWtGrids();
            }).catch(function (err) {
                alert(err.message || 'Could not upload file.');
                btn.disabled = false;
            });
        }

        function saveDimWt(btn, tr, clear) {
            btn.disabled = true;
            var req;
            if (isCover) {
                var fd = new FormData();
                fd.append('_token', CSRF);
                fd.append('product_id', btn.getAttribute('data-id') || '');
                fd.append('sku', btn.getAttribute('data-sku') || '');
                if (!clear) {
                    var file = tr.querySelector('[data-field="item_pkg_cover_file"]');
                    var path = tr.querySelector('[data-field="item_pkg_cover"]');
                    if (file && file.files && file.files[0]) {
                        fd.append('image', file.files[0]);
                    } else {
                        fd.append('path', path ? path.value : '');
                        fd.append('url', path ? path.value : '');
                    }
                } else {
                    fd.append('path', '');
                    fd.append('url', '');
                }
                req = fetch(updateUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
            } else {
                var ta = tr.querySelector('[data-field="instructions_item_pkg"]');
                req = fetch(updateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({
                        product_id: parseInt(btn.getAttribute('data-id'), 10),
                        sku: btn.getAttribute('data-sku') || '',
                        instructions: clear ? '' : (ta ? ta.value : ''),
                        _token: CSRF
                    })
                });
            }
            req.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || res.j.success === false) throw new Error(res.j.message || 'Save failed');
                    editingId = '';
                    window.spRefreshDimWtGrids();
                }).catch(function (err) {
                    alert(err.message || 'Could not save dim-wt-master data.');
                    btn.disabled = false;
                });
        }
        load(false);
    };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sp-dw]').forEach(window.spInitDimWtGrid);
    });

    (function bindThumbHover() {
        if (window.__spDwImgHoverBound) return;
        window.__spDwImgHoverBound = true;
        var popup = document.getElementById('spDwImgHover');
        if (!popup) {
            popup = document.createElement('img');
            popup.id = 'spDwImgHover';
            popup.alt = '';
            document.body.appendChild(popup);
        }
        var active = null;
        var moveRaf = false;
        var cx = 0;
        var cy = 0;
        function position() {
            if (popup.style.display !== 'block') return;
            var pad = 16;
            var vw = window.innerWidth;
            var vh = window.innerHeight;
            var pw = popup.offsetWidth || 320;
            var ph = popup.offsetHeight || 320;
            var x = cx + pad;
            var y = cy + pad;
            if (x + pw > vw - 8) x = cx - pw - pad;
            if (y + ph > vh - 8) y = cy - ph - pad;
            if (x < 8) x = 8;
            if (y < 8) y = 8;
            popup.style.left = x + 'px';
            popup.style.top = y + 'px';
        }
        function show(img, e) {
            active = img;
            popup.src = img.currentSrc || img.src;
            popup.style.display = 'block';
            cx = e.clientX;
            cy = e.clientY;
            position();
            popup.onload = position;
        }
        function hide() {
            active = null;
            popup.style.display = 'none';
            popup.removeAttribute('src');
            popup.onload = null;
        }
        document.addEventListener('mouseover', function (e) {
            var img = e.target && e.target.closest ? e.target.closest('.sp-dw-table img.sp-dw-thumb') : null;
            if (!img) return;
            show(img, e);
        }, true);
        document.addEventListener('mouseout', function (e) {
            var img = e.target && e.target.closest ? e.target.closest('.sp-dw-table img.sp-dw-thumb') : null;
            if (!img || img !== active) return;
            var to = e.relatedTarget;
            if (to && img.contains && img.contains(to)) return;
            hide();
        }, true);
        document.addEventListener('mousemove', function (e) {
            if (popup.style.display !== 'block') return;
            cx = e.clientX;
            cy = e.clientY;
            if (moveRaf) return;
            moveRaf = true;
            requestAnimationFrame(function () {
                moveRaf = false;
                position();
            });
        }, true);
    })();
})();
</script>
@endonce
