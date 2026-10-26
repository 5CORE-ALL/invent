{{--
    Issue-board report modal (SKU / Parent / Supplier / Monthly) for the last 12 months, with a rolling graph.
    Built from the rows already loaded on the page (needs: sku, parent, supplier, total_loss, departments,
    created_at, ...) and OV sales from the shared 12-month sales endpoint.

    Include the parts separately:
      @include('partials.issue-board-report', ['ibrPart' => 'css'])                     inside <style>
      @include('partials.issue-board-report', ['ibrPart' => 'html', 'ibrDepts' => [...]])  inside content
      @include('partials.issue-board-report', ['ibrPart' => 'js',   'ibrDepts' => [...]])  inside <script> (Chart.js must be loaded)
    then call IssueBoardReport.init({ getRows: fn }) once the page table exists.
    Buttons: #ibr-btn-sku, #ibr-btn-parent, #ibr-btn-supplier, #ibr-btn-month
--}}
@php
    $ibrPart = $ibrPart ?? 'all';
    $ibrDepts = $ibrDepts ?? [['key' => 'A', 'label' => 'A'], ['key' => 'B', 'label' => 'B']];
@endphp
@if($ibrPart === 'css' || $ibrPart === 'all')
        /* Issue-board report modal */
        #ibr-modal .qcr-banner { background: linear-gradient(90deg,#4f46e5,#7c3aed 55%,#ec4899); color:#fff; padding:14px 20px; }
        #ibr-modal .qcr-banner-title { font-size:20px; font-weight:700; }
        #ibr-modal .qcr-banner-sub { font-size:12px; opacity:.85; margin-top:2px; }
        #ibr-modal .qcr-body { background:#f3f4f8; padding:16px; }
        #ibr-modal .qcr-kpis { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        #ibr-modal .qcr-kpi { color:#fff; border-radius:10px; padding:12px 14px; box-shadow:0 2px 6px rgba(0,0,0,.12); }
        #ibr-modal .qcr-kpi-label { font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; opacity:.9; }
        #ibr-modal .qcr-kpi-value { font-size:26px; font-weight:800; line-height:1.15; margin-top:4px; }
        #ibr-modal .qcr-k1 { background:linear-gradient(135deg,#4f46e5,#7c3aed); }
        #ibr-modal .qcr-k2 { background:linear-gradient(135deg,#0e7490,#0891b2); }
        #ibr-modal .qcr-k3 { background:linear-gradient(135deg,#b91c1c,#ea580c); }
        #ibr-modal .qcr-k4 { background:linear-gradient(135deg,#9a3412,#d97706); }
        #ibr-modal .qcr-k5 { background:linear-gradient(135deg,#15803d,#16a34a); }
        #ibr-modal .qcr-k6 { background:linear-gradient(135deg,#a21caf,#db2777); }
        #ibr-modal .qcr-section-label { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#374151; margin-bottom:6px; }
        #ibr-modal .qcr-dept { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
        #ibr-modal .qcr-dcard { color:#fff; border-radius:10px; padding:12px 16px; box-shadow:0 2px 6px rgba(0,0,0,.12); }
        #ibr-modal .qcr-dq { background:linear-gradient(135deg,#1d4ed8,#4338ca); }
        #ibr-modal .qcr-dp { background:linear-gradient(135deg,#78350f,#a16207); }
        #ibr-modal .qcr-dcard-name { font-size:20px; font-weight:800; }
        #ibr-modal .qcr-dcard small { display:block; font-size:9px; letter-spacing:.06em; opacity:.8; }
        #ibr-modal .qcr-dcard b { font-size:22px; font-weight:800; }
        #ibr-modal .qcr-dcard b.qcr-alt { color:#fde047; }
        #ibr-modal .qcr-dcard-sub { font-size:12px; opacity:.9; margin:4px 0 8px; }
        #ibr-modal .qcr-bar { height:5px; border-radius:3px; background:rgba(0,0,0,.18); margin-top:4px; overflow:hidden; }
        #ibr-modal .qcr-bar i { display:block; height:100%; border-radius:3px; }
        #ibr-modal .qcr-bar-w i { background:#fff; }
        #ibr-modal .qcr-bar-y i { background:#fde047; }
        #ibr-modal .qcr-bar-red { background:#fee2e2; margin-top:3px; }
        #ibr-modal .qcr-bar-red i { background:#dc2626; }
        #ibr-modal .qcr-chart { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; margin-bottom:14px; }
        #ibr-modal .qcr-chart-box { position:relative; height:260px; }
        #ibr-modal .qcr-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:8px; }
        #ibr-modal .qcr-toolbar-label { font-size:11px; font-weight:700; letter-spacing:.06em; color:#6b7280; }
        #ibr-modal .qcr-pill { border:1px solid #c7d2fe; background:#fff; color:#4338ca; border-radius:999px; padding:3px 14px; font-size:12px; font-weight:600; }
        #ibr-modal .qcr-pill.qcr-pill-on { background:linear-gradient(90deg,#4f46e5,#a855f7); color:#fff; border-color:transparent; }
        #ibr-modal .qcr-export { background:linear-gradient(90deg,#ea580c,#f59e0b); color:#fff; border:none; font-weight:600; }
        #ibr-modal .qcr-pct { display:inline-block; border-radius:999px; padding:1px 10px; font-size:12px; font-weight:700; }
        #ibr-modal .qcr-pct-low { background:#d1fae5; color:#047857; }
        #ibr-modal .qcr-pct-mid { background:#fef3c7; color:#b45309; }
        #ibr-modal .qcr-pct-high { background:#fee2e2; color:#b91c1c; }
        #ibr-modal .qcr-table-wrap { background:#fff; border-radius:8px; border:1px solid #e5e7eb; overflow:auto; max-height:52vh; }
        #ibr-modal .qcr-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        #ibr-modal .qcr-table thead th { position:sticky; top:0; z-index:2; background:linear-gradient(90deg,#4c1d95,#7e22ce 60%,#be185d); color:#fff; font-size:10px; letter-spacing:.05em; text-transform:uppercase; padding:9px 8px; white-space:nowrap; }
        #ibr-modal .qcr-table tbody td { padding:7px 8px; border-bottom:1px solid #eef0f4; vertical-align:middle; }
        #ibr-modal .qcr-table tbody tr.ibr-row { cursor:pointer; }
        #ibr-modal .qcr-table tbody tr.ibr-row:hover, #ibr-modal .qcr-table tbody tr.qcr-open { background:#f5f3ff; }
        #ibr-modal .qcr-name { font-weight:700; color:#4338ca; white-space:nowrap; }
        #ibr-modal .qcr-rank { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:#9ca3af; color:#fff; font-size:11px; font-weight:700; }
        #ibr-modal .qcr-rank-top { background:#f97316; }
        #ibr-modal .qcr-loss { color:#b91c1c; font-weight:700; }
        #ibr-modal .qcr-sold { color:#0e7490; font-weight:600; }
        #ibr-modal .qcr-summary { min-width:360px; font-size:12px; color:#374151; }
        #ibr-modal .qcr-detail { background:#faf5ff; padding:10px 14px; }
        #ibr-modal .qcr-sub { width:100%; font-size:12px; background:#fff; border:1px solid #e5e7eb; }
        #ibr-modal .qcr-sub th { background:#ede9fe; color:#4c1d95; padding:4px 8px; font-size:11px; }
        #ibr-modal .qcr-sub td { padding:4px 8px; border-top:1px solid #f1f1f5; }
        @media (max-width: 992px) { #ibr-modal .qcr-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@endif
@if($ibrPart === 'html' || $ibrPart === 'all')
    <div class="modal fade" id="ibr-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:1400px;">
            <div class="modal-content border-0">
                <div class="qcr-banner d-flex align-items-start justify-content-between">
                    <div>
                        <div class="qcr-banner-title"><i class="bi bi-bar-chart-line-fill me-2"></i><span id="ibr-title">Report</span></div>
                        <div class="qcr-banner-sub" id="ibr-sub">Loading…</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body qcr-body">
                    <div class="qcr-kpis" id="ibr-kpis"></div>
                    <div class="qcr-section-label"><i class="bi bi-diagram-2-fill me-1"></i> Issues by department</div>
                    <div class="qcr-dept" id="ibr-dept"></div>
                    <div class="qcr-chart">
                        <div class="qcr-section-label mb-1"><i class="bi bi-graph-up me-1"></i> Monthly issues &amp; loss — rolling last 12 months <span class="text-muted fw-normal text-lowercase">(click a legend item to show / hide; cumulative lines are hidden by default)</span></div>
                        <div class="qcr-chart-box"><canvas id="ibr-chart"></canvas></div>
                    </div>
                    <div class="qcr-toolbar">
                        <span id="ibr-sort-wrap" class="d-flex align-items-center gap-2">
                            <span class="qcr-toolbar-label">SORT BY</span>
                            <select id="ibr-sort" class="form-select form-select-sm" style="width:auto;"></select>
                            <button type="button" class="qcr-pill qcr-pill-on" id="ibr-dir-desc">Max → Min</button>
                            <button type="button" class="qcr-pill" id="ibr-dir-asc">Min → Max</button>
                        </span>
                        <input type="search" id="ibr-search" class="form-control form-control-sm" style="width:200px;" placeholder="Search…" autocomplete="off">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Department">
                            <button type="button" class="btn btn-outline-primary active" data-ibr-dept="">All</button>
                            @foreach($ibrDepts as $d)
                                <button type="button" class="btn btn-outline-primary" data-ibr-dept="{{ $d['key'] }}">{{ $d['label'] }}</button>
                            @endforeach
                        </div>
                        <span class="ms-auto small text-muted">Issue rate:</span>
                        <span class="qcr-pct qcr-pct-low">&lt;1%</span>
                        <span class="qcr-pct qcr-pct-mid">1–3%</span>
                        <span class="qcr-pct qcr-pct-high">&gt;3%</span>
                        <button type="button" class="btn btn-sm qcr-export" id="ibr-export"><i class="bi bi-download me-1"></i> Export CSV</button>
                    </div>
                    <div id="ibr-note" class="small text-muted mb-1"></div>
                    <div class="qcr-table-wrap">
                        <table class="qcr-table" id="ibr-table">
                            <thead id="ibr-head"></thead>
                            <tbody id="ibr-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <span class="me-auto small text-muted">Last 12 months of issues. Sold = OV (overall Shopify) units for the same months. Click a row to expand.</span>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif
@if($ibrPart === 'js' || $ibrPart === 'all')
window.IssueBoardReport = (function () {
    const DEPTS = @json($ibrDepts);
    const SALES_URL = @json(route('customer.care.qc.and.packing.sales.12m'));
    const R = { mode: 'sku', dept: '', sort: 'issues', dir: 'desc', search: '', groups: [], sales: null, salesPromise: null, getRows: function () { return []; }, chart: null };
    const MODES = {
        sku: { title: 'SKU Report — {{ $ibrDepts[0]['label'] ?? '' }} & {{ $ibrDepts[1]['label'] ?? '' }} Issues', unit: 'SKUs', hit: 'SKUs hit', col: 'SKU' },
        parent: { title: 'Parent Report — {{ $ibrDepts[0]['label'] ?? '' }} & {{ $ibrDepts[1]['label'] ?? '' }} Issues', unit: 'Parents', hit: 'Parents hit', col: 'Parent' },
        supplier: { title: 'Supplier Report — {{ $ibrDepts[0]['label'] ?? '' }} & {{ $ibrDepts[1]['label'] ?? '' }} Issues', unit: 'Suppliers', hit: 'Suppliers hit', col: 'Supplier' },
        month: { title: 'Monthly Report — {{ $ibrDepts[0]['label'] ?? '' }} & {{ $ibrDepts[1]['label'] ?? '' }} Issues', unit: 'months', hit: 'Months with issues', col: 'Month' },
    };
    const SORTS = { issues: 'Issues', pct: 'Issue %', loss: 'Loss $', sold: 'Units sold', open: 'Open issues', recent: 'Most recent', name: 'Name A–Z' };

    function esc(v) { const el = document.createElement('div'); el.textContent = String(v ?? ''); return el.innerHTML; }
    function escA(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
    function dash(v) { const t = String(v ?? '').trim(); return t === '' ? '—' : esc(t); }
    function canon(s) { return String(s || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); }
    function tm(r) { return Date.parse(String(r.created_at || '').replace(' ', 'T')) || 0; }
    function fixed(r) { return String(r.c_action_1 || '').trim() !== '' || String(r.c_action_1_remark || '').trim() !== ''; }
    function fdate(ts) { return ts ? new Date(ts).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
    function money(n) { return '$' + Math.round(n).toLocaleString('en-US'); }
    function num(n) { return Math.round(n).toLocaleString('en-US'); }
    function pctText(p) { return p >= 10 ? p.toFixed(0) + '%' : (p >= 1 ? p.toFixed(1) + '%' : p.toFixed(2) + '%'); }
    function pctHtml(p) {
        if (p === null || p === undefined) return '<span class="text-muted">—</span>';
        return '<span class="qcr-pct ' + (p < 1 ? 'qcr-pct-low' : (p <= 3 ? 'qcr-pct-mid' : 'qcr-pct-high')) + '">' + pctText(p) + '</span>';
    }
    function deptsOf(r) {
        let list = Array.isArray(r.departments) && r.departments.length ? r.departments : String(r.department || '').split(/[|,]/);
        list = list.map(function (x) { return String(x).trim(); });
        return DEPTS.map(function (d) { return list.indexOf(d.key) !== -1; });
    }
    function top(rows, getter, n) {
        const m = new Map();
        rows.forEach(function (r) {
            const v = String(getter(r) || '').trim();
            if (!v) return;
            const k = v.toLowerCase();
            const e = m.get(k) || { label: v, count: 0 };
            e.count++;
            m.set(k, e);
        });
        return Array.from(m.values()).sort(function (a, b) { return b.count - a.count; }).slice(0, n);
    }
    function listTxt(items) { return items.map(function (e) { return '"' + e.label + '" (' + e.count + '×)'; }).join(', '); }

    function ensureSales() {
        if (R.salesPromise) return R.salesPromise;
        R.salesPromise = fetch(SALES_URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success) throw new Error('sales failed');
                R.sales = { from: res.from, label: res.label, by_sku: res.by_sku || {}, by_parent: res.by_parent || {}, failed: false };
            })
            .catch(function () {
                const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - 11);
                R.sales = { from: d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-01', label: 'last 12 months', by_sku: {}, by_parent: {}, failed: true };
                R.salesPromise = null;
            });
        return R.salesPromise;
    }
    function soldSku(label) { return R.sales ? (Number(R.sales.by_sku[canon(label)]) || 0) : 0; }
    function soldParent(label, g) {
        const v = R.sales ? R.sales.by_parent[String(label).toUpperCase()] : undefined;
        if (v !== undefined) return Number(v) || 0;
        let t = 0;
        g.skus.forEach(function (sku) { t += soldSku(sku); });
        return t;
    }
    function newGroup(key, label) {
        return { key: key, label: label, rows: [], skus: new Map(), parents: new Map(), dc: DEPTS.map(function () { return 0; }), dl: DEPTS.map(function () { return 0; }), loss: 0, fixed: 0, last: 0, children: [], sold: 0 };
    }
    function addRow(g, r) {
        g.rows.push(r);
        const sku = String(r.sku || '').trim();
        if (sku) g.skus.set(canon(sku), sku);
        const parent = String(r.parent || '').trim();
        if (parent) g.parents.set(parent.toUpperCase(), parent);
        const loss = parseFloat(r.total_loss) || 0;
        deptsOf(r).forEach(function (on, i) { if (on) { g.dc[i]++; g.dl[i] += loss; } });
        g.loss += loss;
        if (fixed(r)) g.fixed++;
        g.last = Math.max(g.last, tm(r));
    }
    function groupRows(rows, keyFn, labelFn) {
        const map = new Map();
        rows.forEach(function (r) {
            const label = labelFn(r);
            if (!label) return;
            const key = keyFn(r);
            let g = map.get(key);
            if (!g) { g = newGroup(key, label); map.set(key, g); }
            addRow(g, r);
        });
        return map;
    }
    const skuKey = function (r) { return canon(r.sku); };
    const skuLabel = function (r) { return String(r.sku || '').trim(); };
    const parentLabel = function (r) { return String(r.parent || '').trim() || '(No Parent)'; };
    const parentKey = function (r) { return parentLabel(r).toUpperCase(); };
    const supplierLabel = function (r) { return String(r.supplier || '').trim() || '(No Supplier)'; };
    function finish(g) {
        g.open = g.rows.length - g.fixed;
        g.pct = g.sold > 0 ? g.rows.length / g.sold * 100 : null;
        g.parent = g.parents.size ? Array.from(g.parents.values())[0] : '';
    }
    function summary(g, mode) {
        const n = g.rows.length;
        if (!n) return 'No issues.';
        const out = [];
        out.push(n + ' issue' + (n === 1 ? '' : 's') + ' (' + DEPTS.map(function (d, i) { return g.dc[i] + ' ' + d.label.toLowerCase(); }).join(', ') + ')' +
            (g.sold > 0 ? ' on ' + num(g.sold) + ' sold = ' + pctText(g.pct) + '.' : '.'));
        if ((mode === 'parent' || mode === 'supplier') && g.children.length) {
            const worst = g.children.slice().sort(function (a, b) { return b.rows.length - a.rows.length; })[0];
            out.push('Across ' + (mode === 'parent' ? g.skus.size + ' SKU' + (g.skus.size === 1 ? '' : 's') : g.children.length + ' parent' + (g.children.length === 1 ? '' : 's')) +
                '; most affected ' + worst.label + ' (' + worst.rows.length + (worst.pct !== null ? ', ' + pctText(worst.pct) : '') + ').');
        }
        if (mode === 'month') {
            const ts = top(g.rows, function (r) { return r.sku; }, 3);
            if (ts.length) out.push('Top SKUs: ' + listTxt(ts) + '.');
        }
        const what = top(g.rows, function (r) { return r.what_happened; }, 3);
        if (what.length) out.push('Most reported: ' + listTxt(what) + '.');
        const cause = top(g.rows, function (r) { return r.issue; }, 2);
        if (cause.length) out.push('Top root cause: ' + listTxt(cause) + '.');
        if (g.loss > 0) out.push('Loss ' + money(g.loss) + '.');
        out.push(g.fixed === n ? 'All root causes fixed.' : (g.fixed + ' of ' + n + ' root causes fixed, ' + g.open + ' open.'));
        out.push('Last reported ' + fdate(g.last) + '.');
        return out.join(' ');
    }
    function monthSlots() {
        const f = String((R.sales && R.sales.from) || '').split('-');
        let y = parseInt(f[0], 10), m = parseInt(f[1], 10);
        const slots = [];
        for (let i = 0; i < 12; i++) {
            slots.push({ key: y + '-' + String(m).padStart(2, '0'), label: new Date(y, m - 1, 1).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) });
            m++; if (m > 12) { m = 1; y++; }
        }
        return slots;
    }
    /** 12 month groups with rolling 3-month average and cumulative totals. */
    function monthGroups(rows) {
        const slots = monthSlots();
        const map = new Map();
        slots.forEach(function (s) { map.set(s.key, newGroup(s.key, s.label)); });
        rows.forEach(function (r) {
            const g = map.get(String(r.created_at || '').slice(0, 7));
            if (g) addRow(g, r);
        });
        const list = slots.map(function (s) { return map.get(s.key); });
        let cumI = 0, cumL = 0;
        list.forEach(function (g, i) {
            g.open = g.rows.length - g.fixed;
            g.pct = null;
            g.parent = '';
            const w = list.slice(Math.max(0, i - 2), i + 1);
            g.roll3I = w.reduce(function (a, x) { return a + x.rows.length; }, 0) / w.length;
            g.roll3L = w.reduce(function (a, x) { return a + x.loss; }, 0) / w.length;
            cumI += g.rows.length; cumL += g.loss;
            g.cumI = cumI; g.cumL = cumL;
        });
        return list;
    }
    function filteredRows() {
        const from = R.sales ? R.sales.from : '';
        return R.getRows().filter(function (r) {
            if (from && String(r.created_at || '').slice(0, 10) < from) return false;
            if (R.dept) {
                const idx = DEPTS.findIndex(function (d) { return d.key === R.dept; });
                if (!deptsOf(r)[idx]) return false;
            }
            return true;
        });
    }
    function build() {
        const rows = filteredRows();
        let list;
        if (R.mode === 'month') {
            list = monthGroups(rows);
            list.forEach(function (g) { g.summary = summary(g, 'month'); });
            const term = R.search.toLowerCase();
            if (term) list = list.filter(function (g) { return g.label.toLowerCase().indexOf(term) !== -1; });
            return list;
        }
        const parentMap = groupRows(rows, parentKey, parentLabel);
        parentMap.forEach(function (g) {
            g.sold = soldParent(g.label, g);
            g.children = Array.from(groupRows(g.rows, skuKey, skuLabel).values());
            g.children.forEach(function (c) { c.sold = soldSku(c.label); finish(c); });
            finish(g);
        });
        if (R.mode === 'sku') {
            list = Array.from(groupRows(rows, skuKey, skuLabel).values());
            list.forEach(function (g) {
                g.sold = soldSku(g.label);
                finish(g);
                const pg = parentMap.get((g.parent || '(No Parent)').toUpperCase());
                g.parentPct = pg ? pg.pct : null;
            });
        } else if (R.mode === 'parent') {
            list = Array.from(parentMap.values());
        } else {
            list = Array.from(groupRows(rows, function (r) { return supplierLabel(r).toUpperCase(); }, supplierLabel).values());
            list.forEach(function (g) {
                g.children = Array.from(groupRows(g.rows, parentKey, parentLabel).values());
                g.children.forEach(function (c) { c.sold = soldParent(c.label, c); finish(c); });
                g.sold = g.children.reduce(function (a, c) { return a + c.sold; }, 0);
                finish(g);
            });
        }
        list.forEach(function (g) { g.summary = summary(g, R.mode); });
        const term = R.search.toLowerCase();
        if (term) {
            list = list.filter(function (g) {
                return g.label.toLowerCase().indexOf(term) !== -1 || g.parent.toLowerCase().indexOf(term) !== -1 ||
                    (R.mode === 'supplier' && g.children.some(function (c) { return c.label.toLowerCase().indexOf(term) !== -1; }));
            });
        }
        const keyFns = {
            issues: function (g) { return g.rows.length; }, pct: function (g) { return g.pct; }, loss: function (g) { return g.loss; },
            sold: function (g) { return g.sold; }, open: function (g) { return g.open; }, recent: function (g) { return g.last; },
            name: function (g) { return g.label.toLowerCase(); },
        };
        const kf = keyFns[R.sort] || keyFns.issues;
        const sign = R.dir === 'asc' ? 1 : -1;
        list.sort(function (a, b) {
            const x = kf(a), y = kf(b);
            if (x === null && y === null) return 0;
            if (x === null) return 1;
            if (y === null) return -1;
            if (x < y) return -1 * sign;
            if (x > y) return 1 * sign;
            return b.rows.length - a.rows.length;
        });
        return list;
    }
    function badges(g) {
        const cls = ['bg-primary', 'bg-warning text-dark'];
        return DEPTS.map(function (d, i) { return g.dc[i] ? '<span class="badge ' + cls[i % 2] + ' me-1">' + esc(d.label.charAt(0)) + ' ' + g.dc[i] + '</span>' : ''; }).join('');
    }
    function lossCell(g, maxLoss) {
        if (!(g.loss > 0)) return '<span class="text-muted">—</span>';
        return '<div class="qcr-loss">' + money(g.loss) + '</div><div class="qcr-bar qcr-bar-red"><i style="width:' + Math.max(4, Math.round(g.loss / maxLoss * 100)) + '%"></i></div>';
    }
    function openCell(g) { return g.open ? '<span class="badge bg-secondary">' + g.open + '</span>' : (g.rows.length ? '<span class="text-success fw-bold">✓</span>' : '<span class="text-muted">—</span>'); }
    function deptCard(cls, name, count, shareCount, loss, shareLoss) {
        return '<div class="qcr-dcard ' + cls + '"><div class="d-flex justify-content-between align-items-start"><div class="qcr-dcard-name">' + esc(name) + '</div>' +
            '<div class="d-flex gap-3 text-end"><div><small>BY COUNT</small><b>' + shareCount + '%</b></div><div><small>BY VALUE</small><b class="qcr-alt">' + shareLoss + '%</b></div></div></div>' +
            '<div class="qcr-dcard-sub">' + count + ' issue' + (count === 1 ? '' : 's') + ' · ' + money(loss) + '</div>' +
            '<div class="qcr-bar qcr-bar-w"><i style="width:' + shareCount + '%"></i></div><div class="qcr-bar qcr-bar-y"><i style="width:' + shareLoss + '%"></i></div></div>';
    }
    function drawChart(rows) {
        const canvas = document.getElementById('ibr-chart');
        if (!canvas || typeof Chart === 'undefined') return;
        const months = R.mode === 'month' ? R.groups : monthGroups(rows);
        if (R.chart) { R.chart.destroy(); R.chart = null; }
        const colors = ['#4f46e5', '#f59e0b'];
        const datasets = DEPTS.map(function (d, i) {
            return { type: 'bar', label: d.label + ' issues', data: months.map(function (g) { return g.dc[i]; }), backgroundColor: colors[i % 2], stack: 'issues', yAxisID: 'y', order: 3 };
        });
        datasets.push({ type: 'line', label: 'Loss $', data: months.map(function (g) { return Math.round(g.loss); }), borderColor: '#dc2626', backgroundColor: '#dc2626', yAxisID: 'y1', tension: 0.3, pointRadius: 3, order: 1 });
        datasets.push({ type: 'line', label: 'Rolling 3-mo avg issues', data: months.map(function (g) { return Math.round(g.roll3I * 10) / 10; }), borderColor: '#059669', backgroundColor: '#059669', borderDash: [6, 4], yAxisID: 'y', stack: 'roll3', tension: 0.3, pointRadius: 2, order: 2 });
        datasets.push({ type: 'line', label: 'Rolling 3-mo avg loss $', data: months.map(function (g) { return Math.round(g.roll3L); }), borderColor: '#ea580c', backgroundColor: '#ea580c', borderDash: [6, 4], yAxisID: 'y1', tension: 0.3, pointRadius: 2, hidden: true, order: 2 });
        datasets.push({ type: 'line', label: 'Cumulative issues', data: months.map(function (g) { return g.cumI; }), borderColor: '#0891b2', backgroundColor: '#0891b2', yAxisID: 'y', stack: 'cumI', tension: 0.2, pointRadius: 2, hidden: true, order: 2 });
        datasets.push({ type: 'line', label: 'Cumulative loss $', data: months.map(function (g) { return Math.round(g.cumL); }), borderColor: '#7c3aed', backgroundColor: '#7c3aed', yAxisID: 'y1', tension: 0.2, pointRadius: 2, hidden: true, order: 2 });
        R.chart = new Chart(canvas, {
            data: { labels: months.map(function (g) { return g.label; }), datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Issues' }, ticks: { precision: 0 } },
                    y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'Loss $' }, grid: { drawOnChartArea: false } },
                },
            },
        });
    }
    function render() {
        const cfg = MODES[R.mode];
        const isMonth = R.mode === 'month', isSku = R.mode === 'sku', isParent = R.mode === 'parent';
        const groups = build();
        R.groups = groups;
        document.getElementById('ibr-title').textContent = cfg.title;
        document.getElementById('ibr-search').placeholder = 'Search ' + cfg.col + '…';
        document.getElementById('ibr-sort-wrap').style.display = isMonth ? 'none' : '';
        document.getElementById('ibr-sort').value = R.sort;
        document.getElementById('ibr-dir-desc').classList.toggle('qcr-pill-on', R.dir === 'desc');
        document.getElementById('ibr-dir-asc').classList.toggle('qcr-pill-on', R.dir === 'asc');

        const tot = groups.reduce(function (a, g) {
            a.issues += g.rows.length; a.loss += g.loss;
            g.dc.forEach(function (c, i) { a.dc[i] += c; a.dl[i] += g.dl[i]; });
            if (g.sold > 0) { a.sold += g.sold; a.soldIssues += g.rows.length; }
            if (g.rows.length) a.hit++;
            return a;
        }, { issues: 0, loss: 0, dc: DEPTS.map(function () { return 0; }), dl: DEPTS.map(function () { return 0; }), sold: 0, soldIssues: 0, hit: 0 });
        const sales = R.sales || {};
        document.getElementById('ibr-sub').textContent = (sales.label || 'Last 12 months') + ' · ' + tot.issues.toLocaleString() + ' issues' +
            (isMonth ? ' across 12 months' : ' across ' + groups.length.toLocaleString() + ' ' + cfg.unit) + (sales.failed ? ' · sales data could not be loaded' : '');

        const kpi = function (cls, label, value) { return '<div class="qcr-kpi ' + cls + '"><div class="qcr-kpi-label">' + label + '</div><div class="qcr-kpi-value">' + value + '</div></div>'; };
        if (isMonth) {
            const peak = groups.slice().sort(function (a, b) { return b.rows.length - a.rows.length; })[0];
            document.getElementById('ibr-kpis').innerHTML =
                kpi('qcr-k1', 'Months with issues', tot.hit + ' / 12') + kpi('qcr-k2', 'Total issues', tot.issues.toLocaleString()) +
                kpi('qcr-k3', 'Total loss', money(tot.loss)) + kpi('qcr-k4', 'Avg issues / month', (tot.issues / 12).toFixed(1)) +
                kpi('qcr-k5', 'Avg loss / month', money(tot.loss / 12)) + kpi('qcr-k6', 'Peak month', peak && peak.rows.length ? esc(peak.label) + ' (' + peak.rows.length + ')' : '—');
        } else {
            const overall = tot.sold > 0 ? tot.soldIssues / tot.sold * 100 : null;
            document.getElementById('ibr-kpis').innerHTML =
                kpi('qcr-k1', cfg.hit, groups.length.toLocaleString()) + kpi('qcr-k2', 'Total issues', tot.issues.toLocaleString()) +
                kpi('qcr-k3', 'Total loss', money(tot.loss)) + kpi('qcr-k4', 'Avg loss / issue', tot.issues ? money(tot.loss / tot.issues) : '—') +
                kpi('qcr-k5', 'Units sold (OV, 12 mo)', num(tot.sold)) + kpi('qcr-k6', 'Overall issue %', overall === null ? '—' : pctText(overall));
        }
        const cp = tot.dc[0] + tot.dc[1], lp = tot.dl[0] + tot.dl[1];
        const pc = function (v, t) { return t > 0 ? Math.round(v / t * 100) : 0; };
        document.getElementById('ibr-dept').innerHTML =
            deptCard('qcr-dq', DEPTS[0].label, tot.dc[0], pc(tot.dc[0], cp), tot.dl[0], pc(tot.dl[0], lp)) +
            deptCard('qcr-dp', DEPTS[1].label, tot.dc[1], pc(tot.dc[1], cp), tot.dl[1], pc(tot.dl[1], lp));

        const allRows = groups.reduce(function (a, g) { return a.concat(g.rows); }, []);
        drawChart(allRows);

        const th = function (t, cls) { return '<th' + (cls ? ' class="' + cls + '"' : '') + '>' + t + '</th>'; };
        let head, colspan;
        if (isMonth) {
            head = th('Month') + DEPTS.map(function (d) { return th(esc(d.label), 'text-center'); }).join('') + th('Issues', 'text-center') + th('Loss', 'text-end') +
                th('Avg loss / issue', 'text-end') + th('Rolling 3-mo avg', 'text-center') + th('Cumulative issues', 'text-center') + th('Cumulative loss', 'text-end') + th('Open', 'text-center') + th('Summary');
            colspan = 11;
        } else {
            head = th('#') + th(cfg.col);
            if (isSku) head += th('Parent') + th('Dept', 'text-center');
            else if (isParent) head += th('SKUs', 'text-center') + th('Dept', 'text-center');
            else head += th('Parents', 'text-center') + th('SKUs', 'text-center') + th('Dept', 'text-center');
            head += th('Issues', 'text-center') + th('Sold (OV)', 'text-end') + th(isSku ? 'SKU %' : (isParent ? 'Parent %' : 'Supplier %'), 'text-center');
            if (isSku) head += th('Parent %', 'text-center');
            head += th('Loss', 'text-end') + th('Open', 'text-center') + th('Summary');
            colspan = isParent ? 10 : 11;
        }
        document.getElementById('ibr-head').innerHTML = '<tr>' + head + '</tr>';

        const LIMIT = 300;
        const shown = groups.slice(0, LIMIT);
        const maxLoss = Math.max.apply(null, shown.map(function (g) { return g.loss; }).concat([1]));
        document.getElementById('ibr-note').textContent = groups.length > LIMIT ? ('Showing the first ' + LIMIT + ' of ' + groups.length + ' — search to narrow, or Export CSV for all.') : '';
        document.getElementById('ibr-body').innerHTML = shown.length ? shown.map(function (g, i) {
            let cells;
            if (isMonth) {
                cells = '<td class="qcr-name">' + esc(g.label) + '</td>' + DEPTS.map(function (d, k) { return '<td class="text-center">' + (g.dc[k] || '<span class="text-muted">0</span>') + '</td>'; }).join('') +
                    '<td class="text-center fw-bold">' + g.rows.length + '</td><td class="text-end">' + lossCell(g, maxLoss) + '</td><td class="text-end">' + (g.rows.length && g.loss > 0 ? money(g.loss / g.rows.length) : '—') +
                    '</td><td class="text-center">' + g.roll3I.toFixed(1) + '</td><td class="text-center">' + g.cumI + '</td><td class="text-end">' + money(g.cumL) + '</td><td class="text-center">' + openCell(g) +
                    '</td><td class="qcr-summary">' + esc(g.summary) + '</td>';
            } else {
                cells = '<td><span class="qcr-rank' + (i < 3 ? ' qcr-rank-top' : '') + '">' + (i + 1) + '</span></td><td class="qcr-name">' + esc(g.label) + '</td>';
                if (isSku) cells += '<td>' + dash(g.parent) + '</td><td class="text-center">' + badges(g) + '</td>';
                else if (isParent) cells += '<td class="text-center">' + g.skus.size + '</td><td class="text-center">' + badges(g) + '</td>';
                else cells += '<td class="text-center">' + g.children.length + '</td><td class="text-center">' + g.skus.size + '</td><td class="text-center">' + badges(g) + '</td>';
                cells += '<td class="text-center fw-bold">' + g.rows.length + '</td><td class="text-end qcr-sold">' + (g.sold > 0 ? num(g.sold) : '<span class="text-muted">0</span>') + '</td><td class="text-center">' + pctHtml(g.pct) + '</td>';
                if (isSku) cells += '<td class="text-center">' + pctHtml(g.parentPct) + '</td>';
                cells += '<td class="text-end">' + lossCell(g, maxLoss) + '</td><td class="text-center">' + openCell(g) + '</td><td class="qcr-summary">' + esc(g.summary) + '</td>';
            }
            return '<tr class="ibr-row" data-idx="' + i + '">' + cells + '</tr>';
        }).join('') : '<tr><td colspan="' + colspan + '" class="text-center text-muted py-4">No issues found for the last 12 months.</td></tr>';
    }
    function issueRows(g, withSku) {
        return g.rows.slice().sort(function (a, b) { return tm(b) - tm(a); }).map(function (r) {
            const found = [r.issue, r.issue_remark].filter(function (x) { return String(x || '').trim(); }).join(': ');
            const fx = [r.c_action_1, r.c_action_1_remark].filter(function (x) { return String(x || '').trim(); }).join(': ');
            const depts = DEPTS.filter(function (d, i) { return deptsOf(r)[i]; }).map(function (d) { return d.label; }).join(', ');
            return '<tr><td>' + esc(fdate(tm(r))) + '</td>' + (withSku ? '<td>' + esc(r.sku || '') + '</td>' : '') + '<td>' + esc(depts) + '</td><td>' + dash(r.what_happened) + '</td><td>' + dash(found) + '</td><td>' + dash(fx) +
                '</td><td class="text-end">' + (parseFloat(r.total_loss) ? money(parseFloat(r.total_loss)) : '—') + '</td><td>' + dash(r.created_by) + '</td></tr>';
        }).join('');
    }
    function detail(g) {
        let html = '';
        if ((R.mode === 'parent' || R.mode === 'supplier') && g.children.length) {
            const pm = R.mode === 'parent';
            const kids = g.children.slice().sort(function (a, b) { return b.rows.length - a.rows.length; });
            html += '<div class="fw-semibold small mb-1">' + (pm ? 'SKUs in this parent' : 'Parents for this supplier') + '</div><table class="qcr-sub mb-3"><thead><tr><th>' + (pm ? 'SKU' : 'Parent') + '</th>' + (pm ? '' : '<th class="text-center">SKUs</th>') +
                '<th class="text-center">Issues</th><th class="text-center">Dept</th><th class="text-end">Sold (OV)</th><th class="text-center">' + (pm ? 'SKU %' : 'Parent %') + '</th><th class="text-end">Loss</th><th class="text-center">Open</th></tr></thead><tbody>' +
                kids.map(function (c) {
                    return '<tr><td class="fw-semibold">' + esc(c.label) + '</td>' + (pm ? '' : '<td class="text-center">' + c.skus.size + '</td>') + '<td class="text-center">' + c.rows.length + '</td><td class="text-center">' + badges(c) +
                        '</td><td class="text-end">' + (c.sold > 0 ? num(c.sold) : '0') + '</td><td class="text-center">' + pctHtml(c.pct) + '</td><td class="text-end">' + (c.loss > 0 ? money(c.loss) : '—') + '</td><td class="text-center">' + openCell(c) + '</td></tr>';
                }).join('') + '</tbody></table>';
        }
        if (R.mode !== 'supplier' && g.rows.length) {
            const withSku = R.mode !== 'sku';
            html += '<div class="fw-semibold small mb-1">Issues</div><table class="qcr-sub"><thead><tr><th>Date</th>' + (withSku ? '<th>SKU</th>' : '') + '<th>Dept</th><th>Issue?</th><th>Root Cause Found</th><th>Root Cause Fixed</th><th class="text-end">Loss</th><th>By</th></tr></thead><tbody>' + issueRows(g, withSku) + '</tbody></table>';
        }
        if (!html) html = '<span class="text-muted small">No issues.</span>';
        return '<tr class="ibr-detail"><td colspan="11" class="qcr-detail">' + html + '</td></tr>';
    }
    function exportCsv() {
        const mode = R.mode;
        const pt = function (p) { return p === null || p === undefined ? '' : p.toFixed(2); };
        let headers, lines;
        if (mode === 'month') {
            headers = ['Month'].concat(DEPTS.map(function (d) { return d.label; }), ['Issues', 'Loss $', 'Avg loss / issue', 'Rolling 3-mo avg issues', 'Rolling 3-mo avg loss $', 'Cumulative issues', 'Cumulative loss $', 'Open', 'Summary']);
            lines = R.groups.map(function (g) { return [g.label].concat(g.dc, [g.rows.length, Math.round(g.loss), g.rows.length ? Math.round(g.loss / g.rows.length) : '', g.roll3I.toFixed(1), Math.round(g.roll3L), g.cumI, Math.round(g.cumL), g.open, g.summary]); });
        } else if (mode === 'sku') {
            headers = ['SKU', 'Parent'].concat(DEPTS.map(function (d) { return d.label; }), ['Issues', 'Sold (OV 12m)', 'SKU %', 'Parent %', 'Loss $', 'Fixed', 'Open', 'Last reported', 'Summary']);
            lines = R.groups.map(function (g) { return [g.label, g.parent].concat(g.dc, [g.rows.length, g.sold, pt(g.pct), pt(g.parentPct), Math.round(g.loss), g.fixed, g.open, fdate(g.last), g.summary]); });
        } else if (mode === 'parent') {
            headers = ['Parent', 'SKUs'].concat(DEPTS.map(function (d) { return d.label; }), ['Issues', 'Sold (OV 12m)', 'Parent %', 'Loss $', 'Fixed', 'Open', 'Last reported', 'Summary']);
            lines = R.groups.map(function (g) { return [g.label, g.skus.size].concat(g.dc, [g.rows.length, g.sold, pt(g.pct), Math.round(g.loss), g.fixed, g.open, fdate(g.last), g.summary]); });
        } else {
            headers = ['Supplier', 'Parent', 'SKUs'].concat(DEPTS.map(function (d) { return d.label; }), ['Issues', 'Sold (OV 12m)', 'Supplier / Parent %', 'Loss $', 'Fixed', 'Open', 'Last reported', 'Summary']);
            lines = [];
            R.groups.forEach(function (g) {
                lines.push([g.label, '(all parents)', g.skus.size].concat(g.dc, [g.rows.length, g.sold, pt(g.pct), Math.round(g.loss), g.fixed, g.open, fdate(g.last), g.summary]));
                g.children.slice().sort(function (a, b) { return b.rows.length - a.rows.length; }).forEach(function (c) {
                    lines.push([g.label, c.label, c.skus.size].concat(c.dc, [c.rows.length, c.sold, pt(c.pct), Math.round(c.loss), c.fixed, c.open, fdate(c.last), '']));
                });
            });
        }
        const q = function (val) { const s = String(val ?? '').replace(/"/g, '""'); return /[",\n\r]/.test(s) ? '"' + s + '"' : s; };
        const csv = [headers.map(q).join(',')].concat(lines.map(function (r) { return r.map(q).join(','); })).join('\r\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'issues_' + mode + '_report_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    function open(mode) {
        R.mode = mode; R.dept = ''; R.search = ''; R.sort = 'issues'; R.dir = 'desc';
        document.getElementById('ibr-search').value = '';
        document.querySelectorAll('[data-ibr-dept]').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-ibr-dept') === ''); });
        document.getElementById('ibr-title').textContent = MODES[mode].title;
        document.getElementById('ibr-sub').textContent = 'Loading…';
        document.getElementById('ibr-kpis').innerHTML = '';
        document.getElementById('ibr-dept').innerHTML = '';
        document.getElementById('ibr-head').innerHTML = '';
        document.getElementById('ibr-body').innerHTML = '<tr><td class="text-center py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading sales and issues…</td></tr>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ibr-modal')).show();
        ensureSales().then(render);
    }
    function init(cfg) {
        R.getRows = cfg.getRows;
        document.getElementById('ibr-sort').innerHTML = Object.keys(SORTS).map(function (k) { return '<option value="' + k + '">' + SORTS[k] + '</option>'; }).join('');
        [['sku', 'ibr-btn-sku'], ['parent', 'ibr-btn-parent'], ['supplier', 'ibr-btn-supplier'], ['month', 'ibr-btn-month']].forEach(function (p) {
            const el = document.getElementById(p[1]);
            if (el) el.addEventListener('click', function () { open(p[0]); });
        });
        document.getElementById('ibr-export').addEventListener('click', exportCsv);
        let timer = null;
        document.getElementById('ibr-search').addEventListener('input', function () {
            const v = this.value.trim();
            clearTimeout(timer);
            timer = setTimeout(function () { R.search = v; render(); }, 200);
        });
        document.getElementById('ibr-sort').addEventListener('change', function () { R.sort = this.value; render(); });
        document.getElementById('ibr-dir-desc').addEventListener('click', function () { R.dir = 'desc'; render(); });
        document.getElementById('ibr-dir-asc').addEventListener('click', function () { R.dir = 'asc'; render(); });
        document.querySelectorAll('[data-ibr-dept]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                R.dept = btn.getAttribute('data-ibr-dept');
                document.querySelectorAll('[data-ibr-dept]').forEach(function (b) { b.classList.toggle('active', b === btn); });
                render();
            });
        });
        document.getElementById('ibr-body').addEventListener('click', function (e) {
            const tr = e.target.closest('.ibr-row');
            if (!tr) return;
            const next = tr.nextElementSibling;
            if (next && next.classList.contains('ibr-detail')) { next.remove(); tr.classList.remove('qcr-open'); return; }
            const g = R.groups[parseInt(tr.getAttribute('data-idx'), 10)];
            if (g) { tr.insertAdjacentHTML('afterend', detail(g)); tr.classList.add('qcr-open'); }
        });
        document.getElementById('ibr-modal').addEventListener('hidden.bs.modal', function () {
            if (R.chart) { R.chart.destroy(); R.chart = null; }
        });
    }
    return { init: init };
})();
@endif
