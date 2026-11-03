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
        .rb-dot.summary-trend-dot { width: 10px !important; height: 10px !important; min-width: 10px !important; min-height: 10px !important; margin: 0 2px !important; vertical-align: middle; cursor: default; }
        .rb-dots { white-space: nowrap; }
        .tabulator-cell[tabulator-field="sold"] { cursor: pointer; }
        #soldChartModal.modal { --tz-modal-width: 100%; padding-left: 0 !important; padding-right: 0 !important; z-index: 10800; }
        #soldChartModal .modal-dialog { width: 100% !important; max-width: none !important; margin: 0.5rem 0 0 0 !important; }
        #soldChartModal .modal-content { border-radius: 0; }
        .rb-dot-label { margin-left: 4px; vertical-align: middle; }
    </style>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <button type="button" class="btn btn-sm btn-outline-warning" id="sold-bump-btn"
                        title="Sold vs Bump rules — fill S Bump% from L30 sales (same rules as /reverb-pricing)">
                        <i class="fa fa-sliders"></i> Sold vs Bump…
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="push-sbump-btn"
                        title="Push S Bump% to the live Reverb Bump bid for the rows shown">
                        <i class="fa fa-upload"></i> Push S Bump%
                    </button>
                    <a href="{{ route('reverb.bump.log') }}" class="btn btn-sm btn-outline-info"
                        title="Change log, no-sales review list and 5% → 2% candidates">
                        <i class="fa fa-clipboard-list"></i> History &amp; Monitor
                    </a>
                    <span id="sbump-progress" class="small text-muted"></span>

                    <div class="dropdown d-inline-block ms-auto">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="column-dropdown-menu"
                            style="width: min(1280px, 94vw); max-height: 75vh; overflow: hidden; padding: 8px 0;"></ul>
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

    <!-- Sold / metrics graph (same Graph UI + range filter as /reverb-pricing; data from /reverb-metrics-history) -->
    <div class="modal fade p-0" id="soldChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0" style="max-width:100%;">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        Reverb - <span id="sc-sku"></span> - <span id="sc-metric-label">Sold (L30)</span> <span id="sc-suffix">(Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sc-metric" class="form-select form-select-sm bg-white" style="width:120px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="sold" selected>Sold (L30)</option>
                            <option value="views">Views</option>
                            <option value="cvr">CVR%</option>
                            <option value="price">Price</option>
                        </select>
                        <select id="sc-days" class="form-select form-select-sm bg-white" style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="sc-wrap" style="height:38vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;"><canvas id="sc-canvas"></canvas></div>
                        <div style="width:96px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div class="text-center"><div style="font-size:8px;font-weight:700;color:#dc3545;">HIGH</div><div id="sc-high" style="font-size:13px;font-weight:700;color:#dc3545;">-</div></div>
                            <div class="text-center" style="border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;"><div style="font-size:8px;font-weight:700;color:#6c757d;">MEDIAN</div><div id="sc-med" style="font-size:13px;font-weight:700;color:#6c757d;">-</div></div>
                            <div class="text-center"><div style="font-size:8px;font-weight:700;color:#198754;">LOW</div><div id="sc-low" style="font-size:13px;font-weight:700;color:#198754;">-</div></div>
                        </div>
                    </div>
                    <div id="sc-loading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="sc-nodata" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data for this SKU yet. It builds day by day from the daily metrics collection.</p>
                    </div>
                    <div class="small text-muted mt-1">
                        <span class="summary-trend-dot rb-dot up"></span> Sold &gt; 0 (performing)
                        <span class="summary-trend-dot rb-dot down ms-2"></span> Sold = 0 (non-performing)
                        <span class="ms-2">— for Views / CVR% / Price the dot shows up (green) / down (red) vs the previous day.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="soldBumpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6"><i class="fa fa-sliders me-1"></i> Sold vs Bump</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map <strong>Sold</strong> (RV L30) slabs to <strong>S Bump%</strong>. These are the same saved rules as on
                        <strong>/reverb-pricing</strong>. <strong>Apply</strong> saves the rules and fills <strong>S Bump%</strong>
                        for the rows currently shown (filter first to narrow). If <strong>INV = 0</strong>, S Bump% is forced to <strong>0</strong>.
                        Use <strong>Push S Bump%</strong> afterwards to send it to Reverb.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Sold</th>
                                    <th style="width:45%;" class="text-end">S Bump%</th>
                                </tr>
                            </thead>
                            <tbody id="sold-bump-tbody"></tbody>
                        </table>
                    </div>
                    <div class="border rounded p-2 mt-2 bg-light">
                        <div class="small fw-semibold mb-1">Exceptions (rows skipped — S Bump% left unchanged)</div>
                        <div class="row g-2 align-items-center">
                            <div class="col-7"><label class="small mb-0" for="sb-skip-low-stock">Skip if INV below</label></div>
                            <div class="col-5"><input type="number" min="0" step="1" class="form-control form-control-sm" id="sb-skip-low-stock" value="0" title="0 = off"></div>
                            <div class="col-7"><label class="small mb-0" for="sb-skip-high-volume">Skip high-volume (Sold above)</label></div>
                            <div class="col-5"><input type="number" min="0" step="1" class="form-control form-control-sm" id="sb-skip-high-volume" value="0" title="0 = off"></div>
                            <div class="col-7"><label class="small mb-0" for="sb-premium-price">Premium item (Price above $) — review manually</label></div>
                            <div class="col-5"><input type="number" min="0" step="1" class="form-control form-control-sm" id="sb-premium-price" value="0" title="0 = off"></div>
                        </div>
                        <div class="small text-muted mt-1">0 = off. Skipped rows keep their S Bump% and are logged with the reason.</div>
                    </div>
                    <div class="border rounded p-2 mt-2">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="sb-auto-enabled">
                            <label class="form-check-label small fw-semibold" for="sb-auto-enabled">
                                Auto-run weekly (Mon 11:30 IST): re-apply the rule and push to Reverb
                            </label>
                        </div>
                        <div class="small text-muted">Uses the saved rules + exceptions above, so a SKU that started selling moves 5% → 2% on the next cycle. Saved when you click Apply.</div>
                        <div class="row g-2 align-items-center mt-1">
                            <div class="col-7"><label class="small mb-0" for="sb-review-days">Flag "no sales" for review after (days)</label></div>
                            <div class="col-5"><input type="number" min="1" step="1" class="form-control form-control-sm" id="sb-review-days" value="14"></div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" id="sold-bump-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-auto" id="sold-bump-preset-btn"
                        title="Sold > 0 → 2%, Sold = 0 → 5%; skip INV < 5 and Sold > 10">Use 2% / 5% rule</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sold-bump-apply-btn">Apply</button>
                </div>
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
                ruleConfig: @json(route('reverb.bump.rule.config')),
                ruleApply: @json(route('reverb.bump.rule.apply')),
                queuePush: @json(route('reverb.push-bump.queue')),
                pushStatus: @json(route('reverb.push-bump.status')),
                metricsHistory: @json(route('reverb.metrics.history')),
            };
            const PULL_CHUNK = 10;
            const MAX_RAW_COLUMNS = 250;
            // Raw fields that never become columns (the server drops them too): description, _links, deleted_bid, bids, budgets
            const HIDDEN_RAW_PATH = /(^|\.)(description|_links|deleted_bid|budgets)(\.|$)|^bids(\.|$)/;
            const COL_VIS_KEY = 'reverb_bump_raw_cols_v2';

            // The basic column groups (description fields get their own area) shown in the Columns menu.
            const COL_GROUPS = [
                { id: 'basic',   label: 'Basic (listing & pull status)' },
                { id: 'bid',     label: 'Bump Bid' },
                { id: 'description', label: 'Description' },
                { id: 'stats',   label: 'Bump Stats (bump_v2_stats)' },
                { id: 'budgets', label: 'Budgets' },
                { id: 'other',   label: 'Other raw fields' },
            ];
            const BASE_FIELD_GROUP = {
                sku: 'basic', listing_id: 'basic', title: 'basic', listing_state: 'basic',
                http_status: 'basic', fetched_at: 'basic',
                bump_bid: 'bid', push_bump: 'bid', sold: 'bid', inv: 'bid', price: 'bid', s_bump: 'bid', skip_reason: 'bid',
            };

            /** Group of a column definition: base fields by name, raw fields by their data path. */
            function groupOfDef(def) {
                const p = fieldPath[def.field];
                if (!p) return BASE_FIELD_GROUP[def.field] || 'basic';
                if (/description/i.test(p)) return 'description';
                if (p === 'current_bid' || p.startsWith('current_bid.') || p.startsWith('bump_v2_stats.current_bid')) return 'bid';
                if (p.startsWith('bump_v2_stats')) return 'stats';
                if (p === 'budgets' || p.startsWith('budgets.') || p.includes('.budgets')) return 'budgets';
                return 'other';
            }

            /** Stable key for saving visibility: the data path for raw fields (rk1, rk2… can shift), field name otherwise. */
            function colKey(col) {
                const f = col.getField();
                return fieldPath[f] || f;
            }

            let table = null;
            let rawKeys = [];            // ordered union of flattened raw paths
            let keyField = {};           // path -> tabulator field
            let fieldPath = {};          // tabulator field -> path (raw fields only)
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
                    fieldPath[keyField[path]] = path;
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
                    if (!path || HIDDEN_RAW_PATH.test(path)) return;   // no-name path or a field that is not wanted in the table
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

            // same green (up) / red (down) / grey (none) dots as the rolling-history dots on the analytics pages
            const sdot = (kind, tip) => '<span class="summary-trend-dot rb-dot ' + kind + '" title="' + String(tip).replace(/"/g, '&quot;') + '"></span>';

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
                    {
                        title: 'Sold (L30)', field: 'sold', width: 90, hozAlign: 'right',
                        headerTooltip: 'RV L30 — Reverb sales, last 30 days. Green dot = Sold > 0 (performing), red dot = Sold = 0 (non-performing). Click for the graph.',
                        cellClick: (e, cell) => openSoldChart(cell.getRow().getData()),
                        formatter: c => {
                            const v = Number(c.getValue()) || 0;
                            return sdot(v > 0 ? 'up' : 'down', v > 0 ? 'Performing — Sold > 0' : 'Non-performing — Sold = 0')
                                + '<span class="rb-dot-label">' + v + '</span>';
                        },
                    },
                    { title: 'INV', field: 'inv', width: 70, hozAlign: 'right', headerTooltip: 'Shopify inventory' },
                    {
                        title: 'Price', field: 'price', width: 80, hozAlign: 'right',
                        formatter: c => (c.getValue() === null || c.getValue() === undefined) ? '<span class="rb-null">—</span>' : Number(c.getValue()).toFixed(2),
                    },
                    {
                        title: 'S Bump%', field: 's_bump', width: 85, hozAlign: 'right',
                        headerTooltip: 'Seller Bump% (same as /reverb-pricing). Filled by Sold vs Bump; pushed with Push S Bump%.',
                        formatter: c => (c.getValue() === null || c.getValue() === undefined || c.getValue() === '')
                            ? '<span class="rb-null">—</span>' : String(c.getValue()).replace(/</g, '&lt;'),
                    },
                    {
                        title: 'Rule note', field: 'skip_reason', width: 180,
                        headerTooltip: 'Why the last Apply skipped this row (low stock / high volume / premium)',
                        formatter: c => c.getValue() ? '<span class="text-warning">' + String(c.getValue()).replace(/</g, '&lt;') + '</span>' : '',
                    },
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
                // keep the scroll positions, so a click does not jump the lists back to the top
                const keepTop = {};
                menu.querySelectorAll('[data-gid]').forEach(el => { keepTop[el.dataset.gid] = el.scrollTop; });
                const keepLeft = menu.querySelector('[data-hscroll]')?.scrollLeft || 0;
                menu.innerHTML = '';

                // Top row: bulk actions
                const actions = document.createElement('li');
                actions.className = 'px-3 pb-2 d-flex gap-3 border-bottom';
                const mk = (label, onclick) => {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.className = 'small fw-semibold text-decoration-none';
                    a.textContent = label;
                    a.addEventListener('click', e => { e.preventDefault(); onclick(); });
                    actions.appendChild(a);
                };
                mk('Show all columns', () => { table.getColumns().forEach(c => c.show()); saveVis(); buildColumnMenu(); });
                mk('Hide all raw fields', () => { rawKeys.forEach(p => table.getColumn(keyField[p])?.hide()); saveVis(); buildColumnMenu(); });
                menu.appendChild(actions);

                // Split the columns into the 5 groups
                const byGroup = {};
                COL_GROUPS.forEach(g => { byGroup[g.id] = []; });
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (!def.title || def.field === 'actions') return;
                    byGroup[groupOfDef(def)].push(col);
                });

                // The groups sit side by side (horizontal); each one scrolls on its own
                const row = document.createElement('li');
                row.className = 'd-flex align-items-start gap-2 px-2 pt-2';
                row.style.overflowX = 'auto';
                row.dataset.hscroll = '1';
                menu.appendChild(row);

                COL_GROUPS.forEach(g => {
                    const cols = byGroup[g.id];
                    if (!cols.length) return;
                    const shown = cols.filter(c => c.isVisible()).length;

                    const panel = document.createElement('div');
                    panel.style.cssText = 'flex:0 0 240px;width:240px;border:1px solid #dee2e6;border-radius:6px;overflow:hidden;';

                    // Group header: tri-state checkbox (show/hide the whole group) + count
                    const head = document.createElement('div');
                    head.className = 'px-2 py-1 bg-light border-bottom d-flex align-items-center gap-2';
                    head.innerHTML = '<input class="form-check-input mt-0" type="checkbox">' +
                        '<span class="fw-semibold small flex-grow-1"></span>' +
                        '<span class="small text-muted"></span>';
                    const gcb = head.querySelector('input');
                    head.querySelector('span.fw-semibold').textContent = g.label;
                    head.querySelector('span.text-muted').textContent = shown + '/' + cols.length;
                    gcb.checked = shown === cols.length;
                    gcb.indeterminate = shown > 0 && shown < cols.length;
                    const applyGroup = (on) => {
                        // one redraw for the whole group, not one per column
                        try { table.blockRedraw(); } catch (e) { /* older build */ }
                        try { cols.forEach(c => on ? c.show() : c.hide()); }
                        finally { try { table.restoreRedraw(); } catch (e) { /* ignore */ } }
                        saveVis(); buildColumnMenu();
                    };
                    gcb.addEventListener('change', () => applyGroup(gcb.checked));
                    // Clicking the group name toggles it too, not only the small checkbox
                    head.style.cursor = 'pointer';
                    head.addEventListener('click', e => {
                        if (e.target === gcb) return;
                        e.preventDefault();
                        applyGroup(!(shown === cols.length));
                    });
                    panel.appendChild(head);

                    const list = document.createElement('div');
                    list.style.cssText = 'max-height:55vh;overflow-y:auto;';
                    list.dataset.gid = g.id;
                    cols.forEach(col => {
                        const def = col.getDefinition();
                        const item = document.createElement('div');
                        const id = 'cv_' + def.field;
                        item.className = 'px-2 py-1';
                        item.innerHTML = '<div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="' + id + '">' +
                            '<label class="form-check-label small" style="word-break:break-all" for="' + id + '"></label></div>';
                        item.querySelector('label').textContent = def.title;
                        const cb = item.querySelector('input');
                        cb.checked = col.isVisible();
                        cb.addEventListener('change', () => { cb.checked ? col.show() : col.hide(); saveVis(); buildColumnMenu(); });
                        list.appendChild(item);
                    });
                    panel.appendChild(list);
                    row.appendChild(panel);
                    if (keepTop[g.id]) list.scrollTop = keepTop[g.id];
                });
                if (keepLeft) row.scrollLeft = keepLeft;
            }

            function saveVis() {
                // keyed by data path for raw fields (rk1, rk2… can shift between pulls), field name for base columns
                const vis = {};
                table.getColumns().forEach(c => { const k = colKey(c); if (k) vis[k] = c.isVisible(); });
                localStorage.setItem(COL_VIS_KEY, JSON.stringify(vis));
            }

            // ---------- Sold vs Bump (same rules as /reverb-pricing) ----------
            const SOLD_BUMP_DEFAULTS = [
                { key: '0', label: '0', bump: 10 }, { key: '1', label: '1', bump: 9 },
                { key: '2', label: '2', bump: 8 }, { key: '3', label: '3', bump: 7 },
                { key: '4', label: '4', bump: 6 }, { key: '5', label: '5', bump: 5 },
                { key: '6', label: '6', bump: 4 }, { key: '7', label: '7', bump: 3 },
                { key: '8-10', label: '8–10', bump: 2 }, { key: 'gt-10', label: '> 10', bump: 0 },
            ];
            // Checklist rule: Sold > 0 → 2%, Sold = 0 → 5% (override, never incremental).
            const BUMP_CHECKLIST = { soldBump: 2, noSaleBump: 5, minStock: 5, highVolumeSold: 10 };
            let soldBumpRules = SOLD_BUMP_DEFAULTS.map(r => Object.assign({}, r));

            function renderSoldBumpRules() {
                const tb = document.getElementById('sold-bump-tbody');
                tb.innerHTML = '';
                soldBumpRules.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.dataset.key = r.key;
                    tr.innerHTML = '<td></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm sb-input"></td>';
                    tr.firstChild.textContent = r.label || r.key;
                    tr.querySelector('input').value = isFinite(Number(r.bump)) ? Number(r.bump) : 0;
                    tb.appendChild(tr);
                });
            }
            function readSoldBumpRules() {
                document.querySelectorAll('#sold-bump-tbody tr').forEach(tr => {
                    const rule = soldBumpRules.find(r => r.key === tr.dataset.key);
                    const v = parseFloat(tr.querySelector('.sb-input').value);
                    if (rule) rule.bump = (isFinite(v) && v >= 0) ? v : 0;
                });
                return soldBumpRules.map(r => ({ key: r.key, label: r.label, bump: Number(r.bump) || 0 }));
            }
            async function loadSoldBumpRules() {
                const st = document.getElementById('sold-bump-status');
                st.textContent = 'Loading…';
                try {
                    const res = await fetch(URLS.ruleConfig, { headers: { 'Accept': 'application/json' } });
                    const j = await res.json();
                    if (j && Array.isArray(j.rules) && j.rules.length) soldBumpRules = j.rules.map(r => Object.assign({}, r));
                    const s = (j && j.settings) || {};
                    document.getElementById('sb-skip-low-stock').value = s.min_stock ?? 0;
                    document.getElementById('sb-skip-high-volume').value = s.high_volume_sold ?? 0;
                    document.getElementById('sb-premium-price').value = s.premium_price ?? 0;
                    document.getElementById('sb-review-days').value = s.review_after_days ?? 14;
                    document.getElementById('sb-auto-enabled').checked = !!s.auto_enabled;
                    st.textContent = 'Saved rules & exceptions loaded (same rules as /reverb-pricing).';
                } catch (e) {
                    st.textContent = 'Could not load saved rules — showing defaults.';
                }
                renderSoldBumpRules();
            }
            function applyChecklistPreset() {
                soldBumpRules.forEach(r => { r.bump = r.key === '0' ? BUMP_CHECKLIST.noSaleBump : BUMP_CHECKLIST.soldBump; });
                renderSoldBumpRules();
                document.getElementById('sb-skip-low-stock').value = BUMP_CHECKLIST.minStock;
                document.getElementById('sb-skip-high-volume').value = BUMP_CHECKLIST.highVolumeSold;
                // premium threshold has no checklist default (0 = off) — set it to review premium items
                document.getElementById('sold-bump-status').textContent =
                    '2% / 5% rule loaded (skip INV < ' + BUMP_CHECKLIST.minStock + ', Sold > ' + BUMP_CHECKLIST.highVolumeSold + '). Click Apply to save & fill S Bump%.';
            }

            async function applySoldBump() {
                const rules = readSoldBumpRules();
                const rows = table.getRows('active');
                if (!rows.length) return toast('No rows to apply', false);
                if (!confirm('Save rules and fill S Bump% for ' + rows.length + ' shown row(s)?')) return;

                const settings = {
                    min_stock: Math.max(0, parseFloat(document.getElementById('sb-skip-low-stock').value) || 0),
                    high_volume_sold: Math.max(0, parseInt(document.getElementById('sb-skip-high-volume').value, 10) || 0),
                    premium_price: Math.max(0, parseFloat(document.getElementById('sb-premium-price').value) || 0),
                    review_after_days: Math.max(1, parseInt(document.getElementById('sb-review-days').value, 10) || 14),
                    auto_enabled: document.getElementById('sb-auto-enabled').checked,
                };
                const btn = document.getElementById('sold-bump-apply-btn');
                btn.disabled = true;
                try {
                    // The server evaluates the rule (so the manual run, the weekly auto-run and the log all agree),
                    // saves S Bump% and writes the change log.
                    const ids = rows.map(r => r.getData().listing_id);
                    let filled = 0, skipped = 0;
                    const tableUpdates = [];
                    for (let i = 0; i < ids.length; i += 1000) {
                        const r = await post(URLS.ruleApply, { rules, settings, listing_ids: ids.slice(i, i + 1000) });
                        if (!r.ok || !r.json.success) throw new Error((r.json && r.json.message) || ('Apply failed (HTTP ' + r.status + ')'));
                        filled += r.json.filled; skipped += r.json.skipped;
                        r.json.rows.forEach(x => tableUpdates.push({ listing_id: String(x.listing_id), s_bump: x.s_bump, skip_reason: x.skip_reason }));
                    }
                    table.updateData(tableUpdates);
                    toast('S Bump% filled for ' + filled + ' row(s)' + (skipped ? ', ' + skipped + ' skipped (exceptions, see Rule note)' : '')
                        + (settings.auto_enabled ? ' · weekly auto-run ON' : ''));
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('soldBumpModal')).hide();
                } catch (e) {
                    toast(e.message || 'Apply failed', false);
                } finally {
                    btn.disabled = false;
                }
            }

            // ---------- push S Bump% to Reverb (background job, same as /reverb-pricing Push B%) ----------
            async function pushSBumps() {
                const rows = table.getRows('active').map(r => r.getData())
                    .filter(d => !d.skip_reason && d.s_bump !== null && d.s_bump !== undefined && d.s_bump !== '' && isFinite(parseFloat(d.s_bump)));
                if (!rows.length) return toast('No shown rows have an S Bump% to push (rows skipped by exceptions are never pushed) — run Sold vs Bump first', false);
                if (!confirm('Push S Bump% to Reverb for ' + rows.length + ' shown listing(s)? This changes live Bump bids (0% removes the bid).')) return;

                const btn = document.getElementById('push-sbump-btn');
                const prog = document.getElementById('sbump-progress');
                btn.disabled = true;
                try {
                    const items = rows.map(d => ({ sku: d.sku, bump: parseFloat(d.s_bump) }));
                    const r = await post(URLS.queuePush, { items });
                    if (!r.ok || !r.json.success) throw new Error((r.json && r.json.message) || ('HTTP ' + r.status));
                    prog.textContent = 'Push queued…';

                    let state = r.json;
                    while (state && state.active) {
                        await new Promise(res => setTimeout(res, 2500));
                        const s = await fetch(URLS.pushStatus, { headers: { 'Accept': 'application/json' } });
                        state = await s.json();
                        prog.textContent = 'Push S Bump%: ' + state.done_count + '/' + state.total + ' (' + state.fail_count + ' failed)';
                    }
                    toast('Push S Bump% done: ' + state.ok_count + ' ok, ' + state.fail_count + ' failed', state.fail_count === 0);
                    // refresh live Bump% / raw data for what was pushed
                    const ids = rows.map(d => d.listing_id);
                    await pullMany(ids, 'Refresh');
                } catch (e) {
                    toast('Push failed: ' + (e.message || 'error'), false);
                } finally {
                    btn.disabled = false;
                }
            }

            // ---------- Sold / metrics graph (rolling history + range filter, like /reverb-pricing) ----------
            const SC = { chart: null, sku: null, stats: null };
            const SC_METRICS = {
                sold:  { label: 'Sold (L30)', field: 'rv_l30', color: '#198754', bg: 'rgba(25,135,84,0.10)', fmt: v => Math.round(Number(v) || 0).toLocaleString('en-US') },
                views: { label: 'Views', field: 'views', color: '#0000FF', bg: 'rgba(0,0,255,0.10)', fmt: v => Math.round(Number(v) || 0).toLocaleString('en-US') },
                cvr:   { label: 'CVR%', field: 'cvr_percent', color: '#008000', bg: 'rgba(0,128,0,0.10)', fmt: v => Number(v || 0).toFixed(1) + '%' },
                price: { label: 'Price', field: 'price', color: '#6c757d', bg: 'rgba(108,117,125,0.08)', fmt: v => '$' + Number(v || 0).toFixed(2) },
            };

            function scEnsureChart() {
                if (SC.chart || typeof Chart === 'undefined') return;
                const medianLine = {
                    id: 'scMedianLine',
                    afterDraw(chart) {
                        if (!SC.stats) return;
                        const y = chart.scales.y.getPixelForValue(SC.stats.median);
                        const c = chart.ctx;
                        c.save(); c.setLineDash([6, 4]); c.strokeStyle = '#6c757d'; c.lineWidth = 1.2;
                        c.beginPath(); c.moveTo(chart.scales.x.left, y); c.lineTo(chart.scales.x.right, y); c.stroke(); c.restore();
                    },
                };
                const valueLabels = {
                    id: 'scValueLabels',
                    afterDatasetsDraw(chart) {
                        if (!SC.stats) return;
                        const ds = chart.data.datasets[0], meta = chart.getDatasetMeta(0), c = chart.ctx;
                        if (ds.data.length > 45) return;   // too dense to label
                        c.save(); c.font = 'bold 8px Inter, system-ui, sans-serif'; c.textAlign = 'center'; c.textBaseline = 'bottom';
                        meta.data.forEach((pt, i) => {
                            if (ds.data[i] == null) return;
                            c.fillStyle = SC.stats.dotColors[i] || '#6c757d';
                            c.fillText(SC.stats.fmt(ds.data[i]), pt.x, pt.y + (i % 2 === 0 ? -7 : -15));
                        });
                        c.restore();
                    },
                };
                SC.chart = new Chart(document.getElementById('sc-canvas').getContext('2d'), {
                    type: 'line',
                    data: { labels: [], datasets: [{ label: 'Sold (L30)', data: [], borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.10)', borderWidth: 1.5, pointRadius: 3.5, pointHoverRadius: 6, tension: 0.3, fill: true, spanGaps: true }] },
                    plugins: [medianLine, valueLabels],
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 22, left: 2, right: 8, bottom: 2 } },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                                callbacks: { label: ctx => ctx.parsed.y == null ? '' : (SC.stats ? SC.stats.label + ': ' + SC.stats.fmt(ctx.parsed.y) : String(ctx.parsed.y)) },
                            },
                        },
                        scales: {
                            x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } },
                            y: { beginAtZero: true, ticks: { font: { size: 9 } } },
                        },
                    },
                });
            }

            function scStats(arr) {
                const valid = arr.filter(v => v != null && !isNaN(v));
                if (!valid.length) return { min: 0, max: 0, median: 0 };
                const sorted = [...valid].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                return {
                    min: sorted[0], max: sorted[sorted.length - 1],
                    median: sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2,
                };
            }

            async function scLoad() {
                const metric = document.getElementById('sc-metric').value;
                const days = parseInt(document.getElementById('sc-days').value, 10) || 0;
                const m = SC_METRICS[metric];
                document.getElementById('sc-metric-label').textContent = m.label;
                document.getElementById('sc-suffix').textContent = '(Rolling ' + (days === 0 ? 'Lifetime' : 'L' + days) + ')';
                const wrap = document.getElementById('sc-wrap'), load = document.getElementById('sc-loading'), nod = document.getElementById('sc-nodata');
                wrap.style.display = 'none'; nod.style.display = 'none'; load.style.display = 'block';
                try {
                    const res = await fetch(URLS.metricsHistory + '?days=' + days + '&sku=' + encodeURIComponent(SC.sku), { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    load.style.display = 'none';
                    if (!Array.isArray(data) || !data.length) { SC.stats = null; nod.style.display = 'block'; return; }

                    wrap.style.display = 'flex';
                    const values = data.map(d => Number(d[m.field]) || 0);
                    const st = scStats(values);
                    // Sold: green = sold > 0, red = 0. Other metrics: green up / red down vs previous day.
                    const dotColors = values.map((v, i) => {
                        if (metric === 'sold') return v > 0 ? '#28a745' : '#dc3545';
                        if (i === 0) return '#6c757d';
                        return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
                    });
                    SC.stats = { label: m.label, fmt: m.fmt, median: st.median, dotColors };
                    scEnsureChart();
                    const ds = SC.chart.data.datasets[0];
                    SC.chart.data.labels = data.map(d => d.date_formatted || d.date || '');
                    ds.data = values; ds.label = m.label; ds.borderColor = m.color; ds.backgroundColor = m.bg;
                    ds.pointBackgroundColor = dotColors; ds.pointBorderColor = dotColors;
                    SC.chart.options.scales.y.ticks.callback = v => m.fmt(v);
                    SC.chart.update('active');
                    document.getElementById('sc-high').textContent = m.fmt(st.max);
                    document.getElementById('sc-med').textContent = m.fmt(st.median);
                    document.getElementById('sc-low').textContent = m.fmt(st.min);
                } catch (e) {
                    load.style.display = 'none'; SC.stats = null; nod.style.display = 'block';
                    console.error('Reverb bump graph load failed', e);
                }
            }

            function openSoldChart(d) {
                if (!d || !d.sku) return;
                if (typeof Chart === 'undefined') return toast('Chart library failed to load', false);
                SC.sku = d.sku;
                document.getElementById('sc-sku').textContent = d.sku;
                document.getElementById('sc-metric').value = 'sold';
                document.getElementById('sc-days').value = '30';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('soldChartModal')).show();
                scEnsureChart();
                scLoad();
            }

            // ---------- init ----------
            async function init() {
                document.getElementById('sc-metric').addEventListener('change', scLoad);
                document.getElementById('sc-days').addEventListener('change', scLoad);
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
                document.getElementById('sold-bump-btn').addEventListener('click', () => {
                    loadSoldBumpRules();
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('soldBumpModal')).show();
                });
                document.getElementById('sold-bump-preset-btn').addEventListener('click', applyChecklistPreset);
                document.getElementById('sold-bump-apply-btn').addEventListener('click', applySoldBump);
                document.getElementById('push-sbump-btn').addEventListener('click', pushSBumps);
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
