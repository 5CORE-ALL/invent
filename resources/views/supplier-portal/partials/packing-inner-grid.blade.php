@php
    $prefix = $prefix ?? 'spPi';
    $editable = (bool) ($editable ?? false);
    $dataUrl = $editable
        ? route('supplier-portal.admin.packing-data')
        : route('supplier-portal.packing-data');
    $updateUrl = url('/packing-instructions-master/update');
    $ctnUpdateUrl = url('/dim-wt-master/update');
    $fields = \App\Support\SupplierPortalPackingData::FIELDS;
    $ctnPkgLabel = \App\Support\SupplierPortalPackingData::CTN_PKG_LABEL;
    $ctnMasterFields = \App\Support\SupplierPortalPackingData::CTN_MASTER_FIELDS;
    $colCount = 3 + count($fields) + 1 + count($ctnMasterFields) + ($editable ? 1 : 0);
    $clearUrl = url('/packing-instructions-master/clear');
@endphp
<div class="sp-pi-wrap" id="{{ $prefix }}Wrap" data-sp-pi data-editable="{{ $editable ? '1' : '0' }}" data-url="{{ $dataUrl }}" data-update="{{ $updateUrl }}" data-ctn-update="{{ $ctnUpdateUrl }}" data-clear="{{ $clearUrl }}">
    <div class="sp-pi-toolbar">
        <div>
            <strong>Packing Carton</strong>
            <div class="sp-pi-sub">Data from /packing-instructions-master — ctn pkg, dimensions &amp; weight from /dim-wt-master-ctn</div>
        </div>
        @if($editable)
            <div class="sp-pi-actions">
                <button type="button" class="sp-pi-btn is-active" data-sp-pi-mode="view">View</button>
                <button type="button" class="sp-pi-btn" data-sp-pi-mode="edit">Edit</button>
                <button type="button" class="sp-pi-btn" data-sp-pi-sync>Sync</button>
            </div>
        @endif
    </div>
    <div class="sp-pi-filters">
        <label>Parent <input type="search" data-sp-pi-filter="parent" placeholder="Search parent"></label>
        <label>SKU <input type="search" data-sp-pi-filter="sku" placeholder="Search SKU"></label>
        <label>Status
            <select data-sp-pi-filter="status">
                <option value="all">All</option>
                <option value="missing">Missing</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="DC">DC</option>
                <option value="upcoming">Upcoming</option>
                <option value="2BDC">2BDC</option>
            </select>
        </label>
        @foreach($fields as $key => $label)
            @if($key === 'packing_instructions')
                @foreach($ctnMasterFields as $ctnKey => $ctnLabel)
                    <label>{{ $ctnLabel }}
                        <select data-sp-pi-field="{{ $ctnKey }}">
                            <option value="all">All</option>
                            <option value="missing">Missing</option>
                            <option value="has">Has data</option>
                        </select>
                    </label>
                @endforeach
                <label>{{ $ctnPkgLabel }}
                    <select data-sp-pi-field="ctn_instructions">
                        <option value="all">All</option>
                        <option value="missing">Missing</option>
                        <option value="has">Has data</option>
                    </select>
                </label>
            @endif
            <label>{{ $label }}
                <select data-sp-pi-field="{{ $key }}">
                    <option value="all">All</option>
                    <option value="missing">Missing</option>
                    <option value="has">Has data</option>
                </select>
            </label>
        @endforeach
    </div>
    <div class="sp-pi-table-wrap">
        <table class="sp-pi-table">
            <thead>
                <tr>
                    <th>Parent</th>
                    <th>SKU</th>
                    <th>Status</th>
                    @foreach($fields as $key => $label)
                        @if($key === 'packing_instructions')
                            @foreach($ctnMasterFields as $ctnLabel)
                                <th>{{ $ctnLabel }}</th>
                            @endforeach
                            <th>{{ $ctnPkgLabel }}</th>
                        @endif
                        <th>{{ $label }}</th>
                    @endforeach
                    @if($editable)
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody data-sp-pi-body>
                <tr><td colspan="{{ $colCount }}" class="sp-pi-empty">Loading packing data…</td></tr>
            </tbody>
        </table>
    </div>
</div>
@once
<style>
    .sp-pi-wrap { margin-top: 18px; border: 1px solid #e8e8e8; border-radius: 10px; background: #fff; overflow: hidden; }
    .sp-pi-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid #eee; }
    .sp-pi-sub { color: #6b6b6b; font-size: 12px; margin-top: 2px; }
    .sp-pi-actions { display: flex; gap: 6px; }
    .sp-pi-btn { border: 1px solid #ddd; background: #fff; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .sp-pi-btn.is-active, .sp-pi-btn:hover { border-color: #e31c23; color: #e31c23; }
    .sp-pi-filters { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 14px; background: #fafafa; border-bottom: 1px solid #eee; }
    .sp-pi-filters label { font-size: 11px; color: #666; font-weight: 600; display: flex; flex-direction: column; gap: 3px; min-width: 110px; }
    .sp-pi-filters input, .sp-pi-filters select { font-weight: 500; font-size: 12px; border: 1px solid #ddd; border-radius: 6px; padding: 5px 7px; background: #fff; }
    .sp-pi-table-wrap { max-height: 420px; overflow: auto; }
    .sp-pi-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .sp-pi-table th { position: sticky; top: 0; background: #141414; color: #fff; text-align: left; padding: 8px; font-weight: 600; white-space: nowrap; }
    .sp-pi-table td { border-bottom: 1px solid #f0f0f0; padding: 6px 8px; vertical-align: top; }
    .sp-pi-table tbody tr:hover { background: #fffaf8; }
    .sp-pi-table tbody tr.sp-pi-parent,
    .sp-pi-table tbody tr.sp-pi-parent:nth-child(even) { background: #fffef2; }
    .sp-pi-table tbody tr.sp-pi-parent:hover { background: #fdf3a8; }
    .sp-pi-empty { color: #888; padding: 16px !important; }
    .sp-pi-table textarea, .sp-pi-table input.sp-pi-cell { width: 100%; min-width: 90px; border: 1px solid #ddd; border-radius: 4px; padding: 4px 6px; font: inherit; }
    .sp-pi-table input.sp-pi-num { min-width: 72px; max-width: 92px; }
    .sp-pi-save, .sp-pi-edit, .sp-pi-del { border: 0; border-radius: 5px; padding: 4px 8px; font-size: 12px; font-weight: 700; margin: 0 2px 0 0; cursor: pointer; }
    .sp-pi-save { background: #e31c23; color: #fff; }
    .sp-pi-save:disabled { opacity: .6; }
    .sp-pi-edit { background: #111; color: #fff; }
    .sp-pi-del { background: #fff; color: #dc3545; border: 1px solid #f1c0c0; }
    .sp-pi-pkg { border: 0; background: transparent; cursor: pointer; padding: 0; line-height: 1; }
    .sp-pi-pkg svg { width: 16px; height: 16px; display: block; }
    .sp-pi-pkg.is-ok svg { color: #28a745; }
    .sp-pi-pkg.is-miss svg { color: #dc3545; }
    .sp-pi-dash { color: #888; }
    .sp-pi-modal {
        position: fixed; inset: 0; z-index: 2100; display: none;
        align-items: center; justify-content: center; background: rgba(0,0,0,.35);
    }
    .sp-pi-modal.is-open { display: flex; }
    .sp-pi-modal-card { background: #fff; border-radius: 10px; max-width: 520px; width: 92%; padding: 16px 18px; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
    .sp-pi-modal-card h4 { margin: 0 0 8px; font-size: 16px; }
    .sp-pi-modal-card p { margin: 0; white-space: pre-wrap; color: #333; font-size: 14px; }
</style>
<script>
(function () {
    if (window.spInitPackingGrid) return;
    var FIELDS = @json(array_keys($fields));
    var CTN_MASTER_KEYS = @json(array_keys($ctnMasterFields));
    var CSRF = @json(csrf_token());
    var IN_CM = 2.54;
    var KG_LB = 2.2046226218;
    var SEARCH_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>';
    var DISPLAY_KEYS = [];
    FIELDS.forEach(function (key) {
        if (key === 'packing_instructions') {
            CTN_MASTER_KEYS.forEach(function (k) { DISPLAY_KEYS.push(k); });
            DISPLAY_KEYS.push('ctn_instructions');
        }
        DISPLAY_KEYS.push(key);
    });
    window.spPackingRows = null;
    window.spRefreshPackingGrids = function () {
        window.spPackingRows = null;
        document.querySelectorAll('[data-sp-pi]').forEach(function (w) {
            if (typeof w._spPiLoad === 'function') w._spPiLoad(true);
        });
    };
    window.spInitPackingGrid = function (wrap) {
        if (!wrap || wrap.dataset.spPiReady === '1') return;
        wrap.dataset.spPiReady = '1';
        var body = wrap.querySelector('[data-sp-pi-body]');
        var editable = wrap.getAttribute('data-editable') === '1';
        var url = wrap.getAttribute('data-url');
        var updateUrl = wrap.getAttribute('data-update');
        var ctnUpdateUrl = wrap.getAttribute('data-ctn-update');
        var clearUrl = wrap.getAttribute('data-clear');
        var mode = 'view';
        var editingSku = '';
        var rows = [];

        var modal = document.getElementById('spPiModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'spPiModal';
            modal.className = 'sp-pi-modal';
            modal.innerHTML = '<div class="sp-pi-modal-card"><h4 id="spPiModalTitle">ctn pkg</h4><div id="spPiModalBody"></div></div>';
            document.body.appendChild(modal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) modal.classList.remove('is-open');
            });
        }

        function esc(v) {
            return String(v == null ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function val(row, key) { return String(row[key] || '').trim(); }
        function isParentSku(sku) { return String(sku || '').toUpperCase().indexOf('PARENT') !== -1; }
        function toNum(v) {
            if (v === null || v === undefined || String(v).trim() === '') return null;
            var n = parseFloat(v);
            return isNaN(n) ? null : n;
        }
        function fmt(n, d) {
            if (n == null) return '';
            var p = Math.pow(10, d);
            return String(Math.round(n * p) / p);
        }
        function storedKey(key) {
            if (key === 'ctn_l_in') return 'ctn_l';
            if (key === 'ctn_w_in') return 'ctn_w';
            if (key === 'ctn_h_in') return 'ctn_h';
            if (key === 'ctn_weight_lb') return 'ctn_weight_kg';
            return key;
        }
        function displayVal(row, key) {
            if (key === 'ctn_l_in' || key === 'ctn_w_in' || key === 'ctn_h_in') {
                var cm = toNum(row[storedKey(key)]);
                return cm == null ? '' : fmt(cm / IN_CM, 2);
            }
            if (key === 'ctn_weight_lb') {
                var kg = toNum(row.ctn_weight_kg);
                if (kg != null) return fmt(kg * KG_LB, 2);
                return val(row, 'ctn_weight_lb');
            }
            return val(row, key);
        }
        function fieldEmpty(row, key) {
            if (key === 'ctn_weight_lb' || key === 'ctn_weight_kg') {
                return val(row, 'ctn_weight_kg') === '' && val(row, 'ctn_weight_lb') === '';
            }
            return val(row, storedKey(key)) === '';
        }
        function convertPair(el) {
            var tr = el.closest('tr');
            var pair = tr && tr.querySelector('[data-field="' + el.getAttribute('data-pair') + '"]');
            if (!pair) return;
            var n = toNum(el.value);
            var kind = el.getAttribute('data-convert');
            if (kind === 'cm') pair.value = n == null ? '' : fmt(n / IN_CM, 2);
            if (kind === 'in') pair.value = n == null ? '' : fmt(n * IN_CM, 2);
            if (kind === 'kg') pair.value = n == null ? '' : fmt(n * KG_LB, 2);
            if (kind === 'lb') pair.value = n == null ? '' : fmt(n / KG_LB, 2);
        }
        function ctnInput(key, shown) {
            var convert = 'cm';
            var pair = '';
            if (key === 'ctn_l' || key === 'ctn_w' || key === 'ctn_h') {
                convert = 'cm';
                pair = key + '_in';
            } else if (key === 'ctn_l_in' || key === 'ctn_w_in' || key === 'ctn_h_in') {
                convert = 'in';
                pair = key.replace(/_in$/, '');
            } else if (key === 'ctn_weight_kg') {
                convert = 'kg';
                pair = 'ctn_weight_lb';
            } else {
                convert = 'lb';
                pair = 'ctn_weight_kg';
            }
            return '<td><input type="number" step="0.01" class="sp-pi-cell sp-pi-num" data-field="' + key + '" data-convert="' + convert + '" data-pair="' + pair + '" value="' + esc(shown) + '"></td>';
        }

        function filtered() {
            var parent = (wrap.querySelector('[data-sp-pi-filter="parent"]')?.value || '').toLowerCase();
            var sku = (wrap.querySelector('[data-sp-pi-filter="sku"]')?.value || '').toLowerCase();
            var status = wrap.querySelector('[data-sp-pi-filter="status"]')?.value || 'all';
            return rows.filter(function (row) {
                if (parent && val(row, 'Parent').toLowerCase().indexOf(parent) === -1) return false;
                if (sku && val(row, 'SKU').toLowerCase().indexOf(sku) === -1) return false;
                var st = val(row, 'status');
                if (status === 'missing' && st !== '') return false;
                if (status !== 'all' && status !== 'missing' && st.toLowerCase() !== status.toLowerCase()) return false;
                for (var i = 0; i < DISPLAY_KEYS.length; i++) {
                    var sel = wrap.querySelector('[data-sp-pi-field="' + DISPLAY_KEYS[i] + '"]');
                    if (!sel) continue;
                    var fv = sel.value;
                    var empty = fieldEmpty(row, DISPLAY_KEYS[i]);
                    if (fv === 'missing' && !empty) return false;
                    if (fv === 'has' && empty) return false;
                }
                return true;
            });
        }

        function render() {
            var list = filtered();
            if (!list.length) {
                body.innerHTML = '<tr><td colspan="32" class="sp-pi-empty">No packing rows match these filters.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function (row) {
                var canEdit = editable && !isParentSku(row.SKU) && (mode === 'edit' || editingSku === String(row.SKU));
                var cells = DISPLAY_KEYS.map(function (key) {
                    var v = val(row, key);
                    if (CTN_MASTER_KEYS.indexOf(key) !== -1) {
                        var shown = displayVal(row, key);
                        if (!canEdit) return '<td>' + esc(shown || '—') + '</td>';
                        return ctnInput(key, shown);
                    }
                    if (key === 'ctn_instructions') {
                        if (canEdit) {
                            return '<td><input class="sp-pi-cell" data-field="ctn_instructions" maxlength="100" value="' + esc(v) + '" placeholder="ctn pkg (max 100)"></td>';
                        }
                        var has = v !== '';
                        return '<td><button type="button" class="sp-pi-pkg ' + (has ? 'is-ok' : 'is-miss') + '" data-pkg="' + esc(v || 'No instructions available') + '" title="' + esc(has ? v : 'No instructions available') + '">' + SEARCH_SVG + '</button></td>';
                    }
                    if (!canEdit) return '<td>' + esc(v || '—') + '</td>';
                    if (key === 'packing_instructions') {
                        return '<td><textarea rows="2" data-field="' + key + '">' + esc(v) + '</textarea></td>';
                    }
                    return '<td><input class="sp-pi-cell" data-field="' + key + '" value="' + esc(v) + '"></td>';
                }).join('');
                var action = '';
                if (editable) {
                    if (isParentSku(row.SKU)) {
                        action = '<td></td>';
                    } else if (canEdit) {
                        action = '<td><button type="button" class="sp-pi-save" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '" data-parent="' + esc(row.Parent || '') + '">Save</button>' +
                            '<button type="button" class="sp-pi-del" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '" data-parent="' + esc(row.Parent || '') + '">Delete</button></td>';
                    } else {
                        action = '<td><button type="button" class="sp-pi-edit" data-sku="' + esc(row.SKU) + '">Edit</button>' +
                            '<button type="button" class="sp-pi-del" data-id="' + esc(row.id) + '" data-sku="' + esc(row.SKU) + '" data-parent="' + esc(row.Parent || '') + '">Delete</button></td>';
                    }
                }
                return '<tr data-sku="' + esc(row.SKU) + '"' + (isParentSku(row.SKU) ? ' class="sp-pi-parent"' : '') + '>' +
                    '<td>' + esc(row.Parent || '—') + '</td>' +
                    '<td>' + esc(row.SKU) + '</td>' +
                    '<td>' + esc(row.status || '—') + '</td>' +
                    cells + action + '</tr>';
            }).join('');
        }

        function load(force) {
            body.innerHTML = '<tr><td colspan="32" class="sp-pi-empty">Loading packing data…</td></tr>';
            var req = (!force && window.spPackingRows)
                ? Promise.resolve(window.spPackingRows)
                : fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'ts=' + Date.now(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (json) {
                    window.spPackingRows = json.data || [];
                    return window.spPackingRows;
                });
            req.then(function (data) {
                rows = data || [];
                render();
            }).catch(function () {
                body.innerHTML = '<tr><td colspan="32" class="sp-pi-empty">Could not load packing-instructions-master data.</td></tr>';
            });
        }
        wrap._spPiLoad = load;

        wrap.addEventListener('input', function (e) {
            if (e.target.matches('[data-convert]')) {
                convertPair(e.target);
                return;
            }
            if (e.target.matches('[data-sp-pi-filter], [data-sp-pi-field]')) render();
        });
        wrap.addEventListener('change', function (e) {
            if (e.target.matches('[data-sp-pi-filter], [data-sp-pi-field]')) render();
        });
        wrap.querySelectorAll('[data-sp-pi-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.getAttribute('data-sp-pi-mode') || 'view';
                editingSku = '';
                wrap.querySelectorAll('[data-sp-pi-mode]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                render();
            });
        });
        wrap.querySelector('[data-sp-pi-sync]')?.addEventListener('click', function () {
            window.spRefreshPackingGrids();
        });
        wrap.addEventListener('click', function (e) {
            var view = e.target.closest('.sp-pi-pkg');
            if (view) {
                document.getElementById('spPiModalTitle').textContent = 'ctn pkg';
                document.getElementById('spPiModalBody').innerHTML = '<p></p>';
                document.querySelector('#spPiModalBody p').textContent = view.getAttribute('data-pkg') || '';
                modal.classList.add('is-open');
                return;
            }
            var editBtn = e.target.closest('.sp-pi-edit');
            if (editBtn) {
                editingSku = editBtn.getAttribute('data-sku') || '';
                mode = 'view';
                wrap.querySelectorAll('[data-sp-pi-mode]').forEach(function (b) {
                    b.classList.toggle('is-active', b.getAttribute('data-sp-pi-mode') === 'view');
                });
                render();
                return;
            }
            var delBtn = e.target.closest('.sp-pi-del');
            if (delBtn) {
                if (!confirm('Clear packing carton and ctn pkg for this SKU?')) return;
                delBtn.disabled = true;
                var sku = delBtn.getAttribute('data-sku') || '';
                Promise.all([
                    fetch(clearUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ sku: sku, _token: CSRF })
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }),
                    fetch(ctnUpdateUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({
                            product_id: parseInt(delBtn.getAttribute('data-id'), 10),
                            sku: sku,
                            parent: delBtn.getAttribute('data-parent') || '',
                            ctn_instructions: null,
                            _token: CSRF
                        })
                    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                ]).then(function (results) {
                    if (!results[0].ok || results[0].j.success === false) throw new Error(results[0].j.message || 'Could not clear packing row');
                    window.spRefreshPackingGrids();
                }).catch(function (err) {
                    alert(err.message || 'Could not delete packing row.');
                    delBtn.disabled = false;
                });
                return;
            }
            var btn = e.target.closest('.sp-pi-save');
            if (!btn) return;
            var tr = btn.closest('tr');
            var sku = btn.getAttribute('data-sku');
            var payload = { sku: sku, _token: CSRF };
            FIELDS.forEach(function (key) {
                var el = tr.querySelector('[data-field="' + key + '"]');
                payload[key] = el ? el.value : '';
            });
            var ctnEl = tr.querySelector('[data-field="ctn_instructions"]');
            var ctnVal = ctnEl ? String(ctnEl.value || '').trim().slice(0, 100) : '';
            var kg = toNum(tr.querySelector('[data-field="ctn_weight_kg"]')?.value);
            if (kg == null) {
                var lb = toNum(tr.querySelector('[data-field="ctn_weight_lb"]')?.value);
                if (lb != null) kg = lb / KG_LB;
            }
            btn.disabled = true;
            var packingReq = fetch(updateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
            var ctnReq = fetch(ctnUpdateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    product_id: parseInt(btn.getAttribute('data-id'), 10),
                    sku: sku,
                    parent: btn.getAttribute('data-parent') || '',
                    ctn_instructions: ctnVal.length ? ctnVal : null,
                    ctn_l: toNum(tr.querySelector('[data-field="ctn_l"]')?.value),
                    ctn_w: toNum(tr.querySelector('[data-field="ctn_w"]')?.value),
                    ctn_h: toNum(tr.querySelector('[data-field="ctn_h"]')?.value),
                    ctn_weight_kg: kg,
                    _token: CSRF
                })
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
            Promise.all([packingReq, ctnReq])
                .then(function (results) {
                    if (!results[0].ok || results[0].j.success === false) throw new Error(results[0].j.message || 'Save failed');
                    if (!results[1].ok || results[1].j.success === false) throw new Error(results[1].j.message || 'Could not save ctn pkg');
                    editingSku = '';
                    window.spRefreshPackingGrids();
                }).catch(function (err) {
                    alert(err.message || 'Could not save packing row.');
                    btn.disabled = false;
                });
        });
        load(false);
    };
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sp-pi]').forEach(window.spInitPackingGrid);
    });
})();
</script>
@endonce
