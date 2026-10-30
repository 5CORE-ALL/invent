@extends('layouts.vertical', ['title' => 'Reverb Bump - Raw API Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-size: 11px;
            font-weight: 600;
            white-space: normal;
        }
        .tabulator .tabulator-cell { font-size: 12px; }
        .rb-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; color: #fff; }
        .rb-ok { background: #198754; }
        .rb-err { background: #dc3545; }
        .rb-none { background: #6c757d; }
        .rb-null { color: #adb5bd; }
        #raw-json-body { max-height: 70vh; overflow: auto; font-size: 12px; background: #f8f9fa; padding: 12px; border-radius: 6px; }
        #pull-progress { min-width: 220px; }
    </style>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Reverb Bump - Raw API Data',
        'sub_title' => 'GET /listings/{id}/bump response per listing, with Bump push & pull',
    ])
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">Reverb Bump — Raw API Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <input type="text" id="sku-search" class="form-control form-control-sm" style="max-width: 260px;"
                        placeholder="Search SKU / listing id / title...">
                    <select id="filter-status" class="form-select form-select-sm" style="max-width: 190px;">
                        <option value="all">All listings</option>
                        <option value="pulled">Pulled (HTTP 200)</option>
                        <option value="failed">Pull failed</option>
                        <option value="never">Never pulled</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="pull-all-btn">
                        <i class="fa fa-download"></i> Pull All
                    </button>
                    <button type="button" class="btn btn-sm btn-danger d-none" id="pull-stop-btn">
                        <i class="fa fa-stop"></i> Stop
                    </button>
                    <span id="pull-progress" class="small text-muted"></span>

                    <div class="dropdown d-inline-block ms-auto">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto; min-width: 280px;"></ul>
                    </div>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-csv"></i> Export CSV
                    </button>
                </div>
                <div class="small text-muted" id="summary-line"></div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="bump-table" style="height: calc(100vh - 260px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rawJsonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="raw-json-title">Raw response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="raw-json-body" class="mb-0"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="raw-json-copy">Copy</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        (function () {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            const URLS = {
                data: @json(route('reverb.bump.raw.data')),
                pull: @json(route('reverb.bump.raw.pull')),
                push: @json(route('reverb.bump.raw.push')),
            };
            const PULL_CHUNK = 10;
            const MAX_RAW_COLUMNS = 250;
            const COL_VIS_KEY = 'reverb_bump_raw_cols_v2';
            const COL_COLLAPSE_KEY = 'reverb_bump_raw_col_groups_v1';

            // The 5 basic column groups shown in the Columns menu.
            const COL_GROUPS = [
                { id: 'basic',   label: 'Basic (listing & pull status)' },
                { id: 'bid',     label: 'Bump Bid' },
                { id: 'stats',   label: 'Bump Stats (bump_v2_stats)' },
                { id: 'budgets', label: 'Budgets' },
                { id: 'other',   label: 'Other raw fields' },
            ];
            const BASE_FIELD_GROUP = {
                sku: 'basic', listing_id: 'basic', title: 'basic', listing_state: 'basic',
                http_status: 'basic', fetched_at: 'basic',
                bump_bid: 'bid', push_bump: 'bid',
            };

            /** Group of a column definition: base fields by name, raw fields by their data path. */
            function groupOfDef(def) {
                const p = def.rawPath;
                if (!p) return BASE_FIELD_GROUP[def.field] || 'basic';
                if (p === 'current_bid' || p.startsWith('current_bid.') || p.startsWith('bump_v2_stats.current_bid')) return 'bid';
                if (p.startsWith('bump_v2_stats')) return 'stats';
                if (p === 'budgets' || p.startsWith('budgets.') || p.includes('.budgets')) return 'budgets';
                return 'other';
            }

            /** Stable key for saving visibility: the data path for raw fields (rk1, rk2… can shift), field name otherwise. */
            function colKey(col) {
                const def = col.getDefinition();
                return def.rawPath || def.field;
            }

            function loadCollapsed() {
                try { return JSON.parse(localStorage.getItem(COL_COLLAPSE_KEY) || '{}'); } catch (e) { return {}; }
            }

            let table = null;
            let rawKeys = [];            // ordered union of flattened raw paths
            let keyField = {};           // path -> tabulator field
            let stopPull = false;
            let pulling = false;
            const rawStore = new Map();  // listing_id -> { raw, raw_text }

            // ---------- helpers ----------
            function toast(msg, ok = true) {
                const c = document.querySelector('.toast-container');
                const el = document.createElement('div');
                el.className = 'toast align-items-center text-white border-0 show ' + (ok ? 'bg-success' : 'bg-danger');
                el.innerHTML = '<div class="d-flex"><div class="toast-body"></div></div>';
                el.querySelector('.toast-body').textContent = msg;
                c.appendChild(el);
                setTimeout(() => el.remove(), 4500);
            }

            async function post(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify(body),
                });
                let json = {};
                try { json = await res.json(); } catch (e) { /* non-json */ }
                return { ok: res.ok, status: res.status, json };
            }

            /** Flatten nested JSON into { 'a.b.0.c': scalar } */
            function flatten(value, prefix, out) {
                if (value === null || value === undefined) {
                    if (prefix) out[prefix] = null;
                } else if (Array.isArray(value)) {
                    if (value.length === 0) {
                        out[prefix] = '[]';
                    } else if (value.every(v => v === null || typeof v !== 'object')) {
                        out[prefix] = value.join(', ');
                    } else {
                        value.forEach((v, i) => flatten(v, prefix ? prefix + '.' + i : String(i), out));
                    }
                } else if (typeof value === 'object') {
                    const keys = Object.keys(value);
                    if (keys.length === 0 && prefix) out[prefix] = '{}';
                    keys.forEach(k => flatten(value[k], prefix ? prefix + '.' + k : k, out));
                } else {
                    out[prefix] = value;
                }
                return out;
            }

            function fieldFor(path) {
                if (!keyField[path]) {
                    keyField[path] = 'rk' + (Object.keys(keyField).length + 1);
                }
                return keyField[path];
            }

            /**
             * Copy flattened raw values onto `target` (a plain object). Returns true when new paths appeared.
             * The nested raw JSON is kept out of the table rows (rawStore) so Tabulator stays light.
             */
            function hydrateRow(target, raw) {
                let fresh = false;
                if (!raw) return fresh;
                const flat = flatten(raw, '', {});
                Object.keys(flat).forEach(path => {
                    if (!keyField[path]) {
                        if (rawKeys.length >= MAX_RAW_COLUMNS) return;
                        fresh = true;
                        rawKeys.push(path);
                    }
                    target[fieldFor(path)] = flat[path];
                });
                return fresh;
            }

            function searchText(r) {
                return ((r.sku || '') + ' ' + (r.listing_id || '') + ' ' + (r.title || '')).toLowerCase();
            }

            function debounce(fn, ms) {
                let t = null;
                return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
            }

            function rawCellFormatter(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined) return '<span class="rb-null">null</span>';
                if (typeof v === 'boolean') return v ? 'true' : 'false';
                return String(v).replace(/</g, '&lt;');
            }

            function rawColumnDef(path) {
                return {
                    title: path,
                    rawPath: path,
                    headerTooltip: path,
                    field: fieldFor(path),
                    formatter: rawCellFormatter,
                    headerFilter: 'input',
                    minWidth: 110,
                    visible: !path.startsWith('_links'),
                    headerSort: true,
                    download: true,
                };
            }

            function statusFormatter(cell) {
                const r = cell.getRow().getData();
                if (r.http_status === null || r.http_status === undefined) return '<span class="rb-pill rb-none">never</span>';
                const ok = Number(r.http_status) >= 200 && Number(r.http_status) < 300;
                return '<span class="rb-pill ' + (ok ? 'rb-ok' : 'rb-err') + '" title="' + (r.error || '') + '">' + r.http_status + '</span>';
            }

            function baseColumns() {
                return [
                    { title: 'SKU', field: 'sku', frozen: true, width: 170, headerFilter: 'input' },
                    { title: 'Listing ID', field: 'listing_id', width: 110, headerFilter: 'input' },
                    { title: 'Title', field: 'title', width: 220, headerFilter: 'input' },
                    { title: 'State', field: 'listing_state', width: 80 },
                    { title: 'Live Bump%', field: 'bump_bid', width: 90 },
                    { title: 'HTTP', field: 'http_status', width: 70, formatter: statusFormatter, hozAlign: 'center' },
                    { title: 'Pulled At', field: 'fetched_at', width: 140 },
                    {
                        title: 'Push B%', field: 'push_bump', width: 90, editor: 'number',
                        editorParams: { min: 0, max: 30, step: 0.5 },
                        headerTooltip: 'Type 0.5–30 (0 removes Bump), then click Push',
                        formatter: c => c.getValue() === undefined || c.getValue() === null || c.getValue() === ''
                            ? '<span class="rb-null">edit…</span>' : c.getValue() + '%',
                        download: false,
                    },
                    {
                        title: 'Actions', field: 'actions', width: 200, headerSort: false, download: false, frozen: false,
                        formatter: () =>
                            '<button class="btn btn-sm btn-outline-success py-0 me-1" data-act="push">Push</button>' +
                            '<button class="btn btn-sm btn-outline-secondary py-0" data-act="json">JSON</button>',
                        cellClick: async (e, cell) => {
                            const act = e.target?.dataset?.act;
                            if (!act) return;
                            const row = cell.getRow();
                            const d = row.getData();
                            if (act === 'json') return showJson(d);
                            if (act === 'push') return pushRow(row, e.target);
                        },
                    },
                ];
            }

            /** Apply a whole batch of pull results with ONE table update (per-row updates froze the page). */
            function applyPullResults(results) {
                const updates = [];
                let newColumns = false;
                results.forEach(res => {
                    const id = String(res.listing_id);
                    const u = {
                        listing_id: id,
                        http_status: res.status,
                        error: res.success ? null : res.message,
                        fetched_at: res.fetched_at,
                    };
                    if (res.raw) {
                        rawStore.set(id, { raw: res.raw, raw_text: null });
                        if (hydrateRow(u, res.raw)) newColumns = true;
                        const live = res.raw.current_bid ?? res.raw.bump_v2_stats?.current_bid;
                        if (live && typeof live === 'object' && live.display) u.bump_bid = live.display;
                    }
                    updates.push(u);
                });
                if (newColumns) refreshColumns();
                table.updateData(updates);
                scheduleSummary();
            }

            /** Rebuild the column set (base + every raw field seen so far). */
            function refreshColumns() {
                table.setColumns(baseColumns().concat(rawKeys.map(rawColumnDef)));
                applySavedColVis();
                buildColumnMenu();
            }

            async function pushRow(row, btn) {
                const d = row.getData();
                const v = d.push_bump;
                if (v === undefined || v === null || v === '' || isNaN(Number(v))) {
                    return toast('Enter a Push B% (0–30) first for ' + d.sku, false);
                }
                if (!confirm('Push Bump ' + v + '% to Reverb for ' + d.sku + '?')) return;
                btn.disabled = true;
                const r = await post(URLS.push, { sku: d.sku, bump: Number(v) });
                btn.disabled = false;
                if (r.ok && r.json.success) {
                    toast(r.json.message || 'Pushed');
                    if (r.json.pulled) applyPullResults([r.json.pulled]);
                    if (r.json.display) row.update({ bump_bid: r.json.display });
                } else {
                    toast('Push failed: ' + (r.json.message || ('HTTP ' + r.status)), false);
                }
            }

            function showJson(d) {
                document.getElementById('raw-json-title').textContent = d.sku + ' — listing ' + d.listing_id;
                const stored = rawStore.get(String(d.listing_id)) || {};
                const body = stored.raw ? JSON.stringify(stored.raw, null, 2)
                    : (stored.raw_text || (d.error ? 'Error: ' + d.error : 'Not pulled yet.'));
                document.getElementById('raw-json-body').textContent = body;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('rawJsonModal')).show();
            }

            // ---------- pull many ----------
            async function pullMany(listingIds, label) {
                if (pulling) return;
                if (!listingIds.length) return toast('Nothing to pull', false);
                pulling = true; stopPull = false;
                const stopBtn = document.getElementById('pull-stop-btn');
                const prog = document.getElementById('pull-progress');
                stopBtn.classList.remove('d-none');
                document.getElementById('pull-all-btn').disabled = true;

                let done = 0, fail = 0;
                for (let i = 0; i < listingIds.length; i += PULL_CHUNK) {
                    if (stopPull) break;
                    const chunk = listingIds.slice(i, i + PULL_CHUNK);
                    prog.textContent = label + ': ' + done + '/' + listingIds.length + ' (' + fail + ' failed)';
                    const r = await post(URLS.pull, { listing_ids: chunk });
                    if (r.json && r.json.results) {
                        applyPullResults(r.json.results);
                        r.json.results.forEach(res => { done++; if (!res.success) fail++; });
                    } else {
                        done += chunk.length; fail += chunk.length;
                    }
                }
                prog.textContent = (stopPull ? 'Stopped' : 'Done') + ': ' + done + '/' + listingIds.length + ' pulled, ' + fail + ' failed';
                stopBtn.classList.add('d-none');
                document.getElementById('pull-all-btn').disabled = false;
                pulling = false;
                updateSummary();
            }

            // ---------- filters / summary / columns ----------
            function statusFilterFn(data) {
                const mode = document.getElementById('filter-status').value;
                if (mode === 'all') return true;
                const s = data.http_status;
                if (mode === 'never') return s === null || s === undefined;
                if (mode === 'pulled') return Number(s) >= 200 && Number(s) < 300;
                if (mode === 'failed') return s !== null && s !== undefined && !(Number(s) >= 200 && Number(s) < 300);
                return true;
            }

            function applyFilters() {
                const q = document.getElementById('sku-search').value.trim().toLowerCase();
                const mode = document.getElementById('filter-status').value;
                if (!q && mode === 'all') {
                    table.clearFilter();
                } else {
                    table.setFilter(data => statusFilterFn(data) && (!q || searchText(data).includes(q)));
                }
            }

            function updateSummary() {
                if (!table) return;
                let pulled = 0, failed = 0;
                const all = table.getData();
                for (let i = 0; i < all.length; i++) {
                    const s = all[i].http_status;
                    if (s === null || s === undefined) continue;
                    (Number(s) >= 200 && Number(s) < 300) ? pulled++ : failed++;
                }
                document.getElementById('summary-line').textContent =
                    all.length + ' listings · ' + pulled + ' pulled · ' + failed + ' failed · ' +
                    (all.length - pulled - failed) + ' never pulled · ' + table.getDataCount('active') + ' shown · ' +
                    rawKeys.length + ' raw fields';
            }
            const scheduleSummary = debounce(updateSummary, 300);

            // Saved per-column visibility: { field: true(visible) | false(hidden) }
            function loadColVis() {
                try { return JSON.parse(localStorage.getItem(COL_VIS_KEY) || '{}'); } catch (e) { return {}; }
            }

            function applySavedColVis() {
                const saved = loadColVis();
                table.getColumns().forEach(c => {
                    const k = colKey(c);
                    if (k in saved) { saved[k] ? c.show() : c.hide(); }
                });
            }

            function buildColumnMenu() {
                const menu = document.getElementById('column-dropdown-menu');
                menu.innerHTML = '';
                const mk = (label, onclick) => {
                    const li = document.createElement('li');
                    li.innerHTML = '<a class="dropdown-item small" href="#"></a>';
                    li.firstChild.textContent = label;
                    li.firstChild.addEventListener('click', e => { e.preventDefault(); onclick(); });
                    menu.appendChild(li);
                };
                mk('Show all columns', () => { table.getColumns().forEach(c => c.show()); saveVis(); buildColumnMenu(); });
                mk('Hide all raw fields', () => { rawKeys.forEach(p => table.getColumn(keyField[p])?.hide()); saveVis(); buildColumnMenu(); });
                const hr = document.createElement('li'); hr.innerHTML = '<hr class="dropdown-divider">'; menu.appendChild(hr);

                // Split the columns into the 5 groups
                const byGroup = {};
                COL_GROUPS.forEach(g => { byGroup[g.id] = []; });
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (!def.title || def.field === 'actions') return;
                    byGroup[groupOfDef(def)].push(col);
                });

                const collapsed = loadCollapsed();
                COL_GROUPS.forEach(g => {
                    const cols = byGroup[g.id];
                    if (!cols.length) return;
                    const shown = cols.filter(c => c.isVisible()).length;

                    // Group header: caret (collapse), tri-state checkbox (show/hide the whole group), count
                    const head = document.createElement('li');
                    head.className = 'px-3 py-1 bg-light border-top border-bottom d-flex align-items-center gap-2';
                    head.innerHTML = '<i class="fa fa-caret-down small text-muted" style="cursor:pointer;width:10px"></i>' +
                        '<input class="form-check-input mt-0" type="checkbox">' +
                        '<span class="fw-semibold small flex-grow-1" style="cursor:pointer"></span>' +
                        '<span class="small text-muted"></span>';
                    const caret = head.querySelector('i');
                    const gcb = head.querySelector('input');
                    const label = head.querySelector('span.fw-semibold');
                    label.textContent = g.label;
                    head.querySelector('span.text-muted').textContent = shown + '/' + cols.length;
                    gcb.checked = shown === cols.length;
                    gcb.indeterminate = shown > 0 && shown < cols.length;
                    gcb.addEventListener('change', () => {
                        cols.forEach(c => gcb.checked ? c.show() : c.hide());
                        saveVis(); buildColumnMenu();
                    });
                    const isCollapsed = !!collapsed[g.id];
                    caret.className = 'fa ' + (isCollapsed ? 'fa-caret-right' : 'fa-caret-down') + ' small text-muted';
                    const toggleCollapse = () => {
                        const c = loadCollapsed();
                        c[g.id] = !c[g.id];
                        localStorage.setItem(COL_COLLAPSE_KEY, JSON.stringify(c));
                        buildColumnMenu();
                    };
                    caret.addEventListener('click', toggleCollapse);
                    label.addEventListener('click', toggleCollapse);
                    menu.appendChild(head);
                    if (isCollapsed) return;

                    cols.forEach(col => {
                        const def = col.getDefinition();
                        const li = document.createElement('li');
                        const id = 'cv_' + def.field;
                        li.className = 'px-3 py-1 ps-4';
                        li.innerHTML = '<div class="form-check"><input class="form-check-input" type="checkbox" id="' + id + '">' +
                            '<label class="form-check-label small" for="' + id + '"></label></div>';
                        li.querySelector('label').textContent = def.title;
                        const cb = li.querySelector('input');
                        cb.checked = col.isVisible();
                        cb.addEventListener('change', () => { cb.checked ? col.show() : col.hide(); saveVis(); buildColumnMenu(); });
                        menu.appendChild(li);
                    });
                });
            }

            function saveVis() {
                // keyed by data path for raw fields (rk1, rk2… can shift between pulls), field name for base columns
                const vis = {};
                table.getColumns().forEach(c => { const k = colKey(c); if (k) vis[k] = c.isVisible(); });
                localStorage.setItem(COL_VIS_KEY, JSON.stringify(vis));
            }

            // ---------- init ----------
            async function init() {
                const res = await fetch(URLS.data, { headers: { 'Accept': 'application/json' } });
                const payload = await res.json();
                if (payload.table_ready === false) {
                    toast('reverb_bump_raw table is missing — run php artisan migrate', false);
                }
                const rows = payload.rows || [];
                rows.forEach(r => {
                    r.listing_id = String(r.listing_id);
                    rawStore.set(r.listing_id, { raw: r.raw, raw_text: r.raw_text });
                    hydrateRow(r, r.raw);
                    delete r.raw;        // keep nested JSON out of the table rows
                    delete r.raw_text;
                });

                const columns = baseColumns().concat(rawKeys.map(rawColumnDef));
                table = new Tabulator('#bump-table', {
                    data: rows,
                    columns: columns,
                    layout: 'fitData',
                    height: '100%',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, true],
                    movableColumns: true,
                    index: 'listing_id',
                    placeholder: 'No Reverb listings found',
                    columnDefaults: { headerSort: true },
                });
                table.on('tableBuilt', () => {
                    applySavedColVis();
                    buildColumnMenu();
                    updateSummary();
                });
                table.on('dataFiltered', scheduleSummary);

                document.getElementById('sku-search').addEventListener('input', debounce(applyFilters, 300));
                document.getElementById('filter-status').addEventListener('change', applyFilters);
                document.getElementById('pull-all-btn').addEventListener('click', () => {
                    const ids = table.getData().map(r => r.listing_id);
                    if (confirm('Pull raw Bump data for ' + ids.length + ' listings from Reverb? This can take a while.')) pullMany(ids, 'Pull All');
                });
                document.getElementById('pull-stop-btn').addEventListener('click', () => { stopPull = true; });
                document.getElementById('export-btn').addEventListener('click', () => {
                    table.download('csv', 'reverb_bump_raw.csv');
                });
                document.getElementById('raw-json-copy').addEventListener('click', () => {
                    navigator.clipboard.writeText(document.getElementById('raw-json-body').textContent);
                    toast('Copied');
                });
            }

            document.addEventListener('DOMContentLoaded', init);
        })();
    </script>
@endsection
