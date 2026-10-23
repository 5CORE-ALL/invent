@extends('layouts.vertical', ['title' => 'Veeqo Chargebacks'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .vc-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .vc-toolbar { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.5rem; margin-bottom:.75rem; }
        .vc-toolbar label { display:flex; flex-direction:column; gap:.2rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#475569; }
        .vc-toolbar input, .vc-toolbar select { height:32px; font-size:.82rem; }
        .vc-badges { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem; }
        .vc-badge { border-radius:10px; padding:.45rem .8rem; background:#f8fafc; border:1px solid #e2e8f0; min-width:120px; }
        .vc-badge .vc-k { font-size:.68rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:.03em; }
        .vc-badge .vc-v { font-size:1.05rem; font-weight:700; color:#0f172a; }
        .vc-badge.vc-debit .vc-v { color:#b91c1c; }
        .vc-badge.vc-credit .vc-v { color:#15803d; }
        .vc-sync { font-size:.78rem; color:#64748b; }
        .vc-neg { color:#b91c1c; }
        .vc-pos { color:#15803d; }
        #veeqo-chargebacks-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
        #veeqo-chargebacks-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%) !important; }
        #veeqo-chargebacks-table .tabulator-col { background:transparent !important; color:#fff !important; border-right:1px solid rgba(255,255,255,.15); }
        #veeqo-chargebacks-table .tabulator-col-title { font-size:11px; font-weight:700; text-transform:uppercase; white-space:normal; }
        #veeqo-chargebacks-table .tabulator-header-filter input { border:none; border-radius:4px; padding:3px 5px; font-size:11px; color:#333; }
        #veeqo-chargebacks-table .tabulator-cell { font-size:12.5px; color:#334155; }
        #veeqo-chargebacks-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #veeqo-chargebacks-table .tabulator-row:hover { background:#e8f0fe !important; }
        #vc-col-dropdown { max-height:70vh; overflow-y:auto; min-width:280px; padding:.5rem .75rem; }
        #vc-col-dropdown label { display:flex; gap:.4rem; align-items:center; font-size:.8rem; padding:2px 0; }
        .btn-sku-report { background:linear-gradient(135deg,#7c3aed 0%,#db2777 55%,#f97316 100%); color:#fff; border:none; font-weight:600; box-shadow:0 3px 10px rgba(219,39,119,.35); }
        .btn-sku-report:hover { color:#fff; filter:brightness(1.08); transform:translateY(-1px); }
        #vcSkuModal .modal-content { border:none; border-radius:16px; overflow:hidden; background:#f8fafc; }
        #vcSkuModal .modal-header { background:linear-gradient(120deg,#4f46e5 0%,#7c3aed 35%,#db2777 70%,#f97316 100%); color:#fff; border:none; padding:1rem 1.5rem; }
        #vcSkuModal .modal-header .btn-close { filter:invert(1) grayscale(1) brightness(2); }
        #vcSkuModal .sr-sub { font-size:.8rem; opacity:.9; }
        .sr-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.75rem; margin-bottom:1rem; }
        .sr-kpi { border-radius:14px; padding:.8rem 1rem; color:#fff; box-shadow:0 6px 18px rgba(15,23,42,.12); position:relative; overflow:hidden; }
        .sr-kpi::after { content:''; position:absolute; right:-18px; top:-18px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,.15); }
        .sr-kpi .k { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; opacity:.92; }
        .sr-kpi .v { font-size:1.45rem; font-weight:800; line-height:1.2; }
        .sr-kpi i { position:absolute; right:12px; bottom:10px; font-size:1.4rem; opacity:.35; }
        .sr-k1 { background:linear-gradient(135deg,#6366f1,#8b5cf6); }
        .sr-k2 { background:linear-gradient(135deg,#0ea5e9,#06b6d4); }
        .sr-k3 { background:linear-gradient(135deg,#ef4444,#f97316); }
        .sr-k4 { background:linear-gradient(135deg,#f59e0b,#eab308); }
        .sr-k5 { background:linear-gradient(135deg,#10b981,#22c55e); }
        .sr-k6 { background:linear-gradient(135deg,#ec4899,#d946ef); }
        .sr-tools { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem; }
        .sr-tools .btn-sort { border-radius:999px; font-weight:600; }
        .sr-tools .btn-sort.active { background:linear-gradient(135deg,#4f46e5,#db2777); color:#fff; border-color:transparent; }
        #vcSkuTable { border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fff; }
        #vcSkuTable .tabulator-header { background:linear-gradient(90deg,#312e81,#6d28d9,#be185d) !important; }
        #vcSkuTable .tabulator-col { background:transparent !important; color:#fff !important; border-right:1px solid rgba(255,255,255,.15); }
        #vcSkuTable .tabulator-col-title { font-size:11px; font-weight:700; text-transform:uppercase; white-space:normal; }
        #vcSkuTable .tabulator-header-filter input { border:none; border-radius:4px; padding:3px 5px; font-size:11px; color:#333; }
        #vcSkuTable .tabulator-row:nth-child(even) { background:#faf5ff; }
        #vcSkuTable .tabulator-row:hover { background:#fce7f3 !important; }
        #vcSkuTable .tabulator-cell { font-size:12.5px; }
        .sr-rank { display:inline-flex; width:26px; height:26px; border-radius:50%; align-items:center; justify-content:center; font-weight:800; font-size:11px; color:#fff; background:#94a3b8; }
        .sr-rank.r1 { background:linear-gradient(135deg,#f59e0b,#ef4444); }
        .sr-rank.r2 { background:linear-gradient(135deg,#94a3b8,#64748b); }
        .sr-rank.r3 { background:linear-gradient(135deg,#d97706,#92400e); }
        .sr-sku { font-weight:700; color:#4c1d95; }
        .sr-bar { position:relative; height:20px; border-radius:6px; background:#fee2e2; overflow:hidden; min-width:120px; }
        .sr-bar span { position:absolute; inset:0 auto 0 0; background:linear-gradient(90deg,#f97316,#dc2626); border-radius:6px; }
        .sr-bar b { position:relative; z-index:1; display:block; text-align:right; padding-right:6px; font-size:12px; line-height:20px; color:#7f1d1d; }
        .sr-count { display:inline-block; min-width:30px; text-align:center; padding:1px 8px; border-radius:999px; background:#ede9fe; color:#5b21b6; font-weight:700; }
        .sr-sold { color:#0369a1; font-weight:700; }
        .sr-pct { display:inline-block; min-width:62px; text-align:center; padding:2px 8px; border-radius:999px; font-weight:800; font-size:12px; }
        .sr-pct.low { background:#dcfce7; color:#166534; }
        .sr-pct.mid { background:#fef3c7; color:#92400e; }
        .sr-pct.high { background:#fee2e2; color:#991b1b; }
        .sr-pct.na { background:#f1f5f9; color:#64748b; font-weight:600; }
        .sr-legend { font-size:.75rem; color:#475569; display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
        #vcRaw { white-space:pre-wrap; word-break:break-word; font-family:ui-monospace,Consolas,monospace; font-size:12px; max-height:65vh; overflow:auto; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Veeqo Chargebacks',
        'sub_title' => 'Carrier label adjustments on Veeqo / Buy Shipping labels (Amazon Finances)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card vc-card">
                <div class="card-body">
                    <div class="vc-badges" id="vcBadges"></div>

                    <div class="vc-toolbar">
                        <label>From <input type="date" id="vcFrom" class="form-control form-control-sm"></label>
                        <label>To <input type="date" id="vcTo" class="form-control form-control-sm"></label>
                        <label>Source
                            <select id="vcSource" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="veeqo_csv">Veeqo card (CSV)</option>
                                <option value="amazon_api">Amazon settlement (API)</option>
                            </select>
                        </label>
                        <label>Type
                            <select id="vcType" class="form-select form-select-sm">
                                <option value="">All label adjustments</option>
                                <option value="chargebacks" selected>All chargebacks</option>
                            </select>
                        </label>
                        <label>Debit / Credit
                            <select id="vcDirection" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="debit" selected>Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </label>
                        <label>Status <select id="vcStatus" class="form-select form-select-sm"><option value="">All</option></select></label>
                        <label style="min-width:220px;flex:1;">Search
                            <input type="search" id="vcSearch" class="form-control form-control-sm" placeholder="Order, reason, service, settlement, transaction…">
                        </label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="vc-col-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-sku-report" id="vcSkuReport" title="Chargebacks per SKU for the last 12 months"><i class="fas fa-chart-bar"></i> Report SKU wise</button>
                        <button type="button" class="btn btn-sm btn-primary" id="vcExport"><i class="fas fa-download"></i> Export page</button>
                        <button type="button" class="btn btn-sm btn-success" id="vcSync" title="Pull the last 30 days from Amazon Finances"><i class="fas fa-sync"></i> Sync Amazon</button>
                        <button type="button" class="btn btn-sm btn-warning" id="vcImport" title="Veeqo → Reports → transactions CSV (reports-*.csv)"><i class="fas fa-file-upload"></i> Import Veeqo CSV</button>
                        <input type="file" id="vcImportFile" accept=".csv,text/csv" class="d-none">
                    </div>
                    <div class="vc-sync mb-2" id="vcSyncInfo"></div>

                    <div id="veeqo-chargebacks-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vcSkuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down" style="max-width:min(1400px,96vw);">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h4 class="modal-title mb-0"><i class="fas fa-chart-bar me-2"></i>Chargebacks by SKU · last 12 months</h4>
                        <div class="sr-sub" id="vcSkuSub">Loading…</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="sr-kpis" id="vcSkuKpis"></div>
                    <div class="sr-tools">
                        <span class="fw-bold text-secondary small text-uppercase">Sort by chargeback amount</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sort active" data-dir="desc"><i class="fas fa-sort-amount-down"></i> Max → Min</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-sort" data-dir="asc"><i class="fas fa-sort-amount-up"></i> Min → Max</button>
                        <input type="search" id="vcSkuSearch" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU…">
                        <div class="sr-legend ms-auto">
                            Chargeback rate:
                            <span class="sr-pct low">&lt; 1%</span>
                            <span class="sr-pct mid">1–3%</span>
                            <span class="sr-pct high">&gt; 3%</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-sku-report" id="vcSkuExport"><i class="fas fa-download"></i> Export CSV</button>
                    </div>
                    <div id="vcSkuTable"></div>
                    <div class="small text-muted mt-2">
                        Uses the Source / Type / Debit-Credit filters from the page. Chargeback count = orders charged back (Veeqo splits one chargeback into a line per reason; those are the charge lines).
                        Orders with several SKUs count for each SKU and their amount is split evenly. Units sold = 12-month sales across all channels. Chargeback % = chargeback count ÷ units sold.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vcRawModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vcRawTitle">Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><div id="vcRaw"></div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const COL_KEY = 'veeqo_chargebacks_hidden_cols_v1';
            let table = null;
            let rawModal = null;
            let components = [];

            function esc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function money(v, signed) {
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                if (isNaN(n)) return esc(v);
                const txt = (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return signed ? '<span class="' + (n < 0 ? 'vc-neg' : 'vc-pos') + '">' + txt + '</span>' : txt;
            }
            function label(type) {
                return type.replace(/^(Return)?PostageBilling_/, '$1 ').replace(/([a-z])([A-Z])/g, '$1 $2').trim();
            }
            function params() {
                return {
                    from: document.getElementById('vcFrom').value,
                    to: document.getElementById('vcTo').value,
                    source: document.getElementById('vcSource').value,
                    type: document.getElementById('vcType').value,
                    direction: document.getElementById('vcDirection').value,
                    status: document.getElementById('vcStatus').value,
                    search: document.getElementById('vcSearch').value.trim(),
                };
            }
            function qs(obj) {
                return Object.keys(obj).filter(k => obj[k] !== '').map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k])).join('&');
            }
            function col(title, field, extra) {
                return Object.assign({ title: title, field: field, headerFilter: 'input', headerFilterLiveFilter: false, minWidth: 90 }, extra || {});
            }
            function moneyCol(title, field, sortable) {
                return col(title, field, { hozAlign: 'right', formatter: c => money(c.getValue(), true), headerFilter: false, headerSort: sortable !== false });
            }
            function plain(title, field, extra) {
                return col(title, field, Object.assign({ headerFilter: false, headerSort: false }, extra || {}));
            }

            function columns() {
                return [
                    {
                        title: '', field: '_view', width: 44, headerSort: false, hozAlign: 'center', frozen: true,
                        formatter: () => '<button type="button" class="btn btn-sm btn-outline-info py-0 px-1" title="Full Amazon transaction"><i class="fas fa-eye"></i></button>',
                        cellClick: (e, cell) => openRaw(cell.getRow().getData()),
                    },
                    col('Posted', 'posted_at', { frozen: true, minWidth: 130 }),
                    col('Order #', 'order_number', { frozen: true, minWidth: 150 }),
                    moneyCol('Chargeback', 'total_amount'),
                    col('Reason', 'reason', { minWidth: 170 }),
                    col('Source', 'source', { minWidth: 100, formatter: c => c.getValue() === 'veeqo_csv' ? '<span class="badge bg-warning text-dark">Veeqo CSV</span>' : '<span class="badge bg-info text-dark">Amazon API</span>' }),
                    plain('Channel', 'v_channel'),
                    col('Carrier', 'carrier'),
                    col('Service', 'service', { minWidth: 180 }),
                    moneyCol('You Paid (label)', 'customer_charge'),
                    moneyCol('Carrier Billed', 'carrier_charge'),
                    ...components.map(t => moneyCol(label(t), 'c_' + t, false)),
                    col('Currency', 'currency', { minWidth: 70 }),
                    col('Type', 'description', { minWidth: 170 }),
                    col('Debit/Credit', 'direction', { minWidth: 80 }),
                    col('Status', 'transaction_status'),
                    col('Payment Method', 'payment_method', { minWidth: 140 }),
                    col('Items', 'item_types', { minWidth: 220 }),
                    plain('Veeqo Shipped', 'v_shipped_at', { minWidth: 130 }),
                    plain('Tracking #', 'v_tracking', { minWidth: 180 }),
                    plain('Veeqo Carrier', 'v_carrier'),
                    plain('Veeqo Service', 'v_service', { minWidth: 180 }),
                    plain('Veeqo Label Cost', 'v_label_cost', { hozAlign: 'right', formatter: c => money(c.getValue()) }),
                    plain('Labels', 'v_labels', { hozAlign: 'right', minWidth: 60 }),
                    plain('Weight', 'v_weight'),
                    plain('Tracking Status', 'v_status'),
                    plain('SKUs', 'v_skus', { minWidth: 160 }),
                    col('Amazon Order', 'amazon_order_id', { minWidth: 150 }),
                    col('Settlement ID', 'settlement_id', { minWidth: 120 }),
                    col('Event Group ID', 'financial_event_group_id', { minWidth: 200 }),
                    col('Marketplace', 'marketplace_name'),
                    col('Transaction Type', 'transaction_type'),
                    col('Transaction ID', 'transaction_id', { minWidth: 200 }),
                ];
            }

            function hiddenCols() {
                try { return JSON.parse(localStorage.getItem(COL_KEY) || '[]'); } catch (_) { return []; }
            }

            function buildColumnPicker() {
                const menu = document.getElementById('vc-col-dropdown');
                if (!menu || !table) return;
                const hidden = new Set(hiddenCols());
                menu.innerHTML = '';
                table.getColumns().forEach(function (c) {
                    const def = c.getDefinition();
                    if (!def.field || def.field === '_view') return;
                    if (hidden.has(def.field)) c.hide();
                    const row = document.createElement('label');
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = !hidden.has(def.field);
                    cb.addEventListener('change', function () {
                        const now = new Set(hiddenCols());
                        if (cb.checked) { c.show(); now.delete(def.field); } else { c.hide(); now.add(def.field); }
                        localStorage.setItem(COL_KEY, JSON.stringify(Array.from(now)));
                    });
                    row.appendChild(cb);
                    row.appendChild(document.createTextNode(def.title));
                    menu.appendChild(row);
                });
            }

            function buildTable() {
                if (table) table.destroy();
                table = new Tabulator('#veeqo-chargebacks-table', {
                    layout: 'fitDataStretch',
                    height: 'calc(100vh - 330px)',
                    placeholder: 'No chargebacks for these filters.',
                    pagination: true,
                    paginationMode: 'remote',
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, 1000],
                    paginationCounter: 'rows',
                    sortMode: 'remote',
                    filterMode: 'remote',
                    ajaxURL: '{{ route('veeqo.chargebacks.data') }}',
                    ajaxParams: params,
                    ajaxResponse: (url, p, response) => response,
                    columns: columns(),
                });
                table.on('tableBuilt', buildColumnPicker);
            }

            function setOptions(id, values) {
                const sel = document.getElementById(id);
                const current = sel.value;
                const list = (values || []).slice();
                if (current && !list.includes(current)) list.unshift(current);
                const first = sel.options[0] ? sel.options[0].outerHTML : '<option value="">All</option>';
                sel.innerHTML = first + list.map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
                sel.value = current;
            }

            function badge(k, v, cls) {
                return '<div class="vc-badge ' + (cls || '') + '"><div class="vc-k">' + esc(k) + '</div><div class="vc-v">' + v + '</div></div>';
            }

            async function loadSummary(rebuildIfChanged) {
                const res = await fetch('{{ route('veeqo.chargebacks.summary') }}?' + qs(params()), { headers: { Accept: 'application/json' } });
                const json = await res.json();
                const t = json.totals || {};
                document.getElementById('vcBadges').innerHTML =
                    badge('Adjustments', Number(t.rows_count || 0).toLocaleString()) +
                    badge('Orders', Number(t.orders || 0).toLocaleString()) +
                    badge('Debits', money(t.debit_total || 0), 'vc-debit') +
                    badge('Credits', money(t.credit_total || 0), 'vc-credit') +
                    badge('Net', money(t.net_total || 0), Number(t.net_total) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Veeqo card net', money(t.veeqo_net || 0), Number(t.veeqo_net) < 0 ? 'vc-debit' : 'vc-credit') +
                    badge('Amazon settlement net', money(t.amazon_net || 0), Number(t.amazon_net) < 0 ? 'vc-debit' : 'vc-credit');

                const f = json.filters || {};
                setOptions('vcType', f.types);
                setOptions('vcStatus', f.statuses);

                const s = json.sync || {};
                const day = v => String(v || '').slice(0, 10);
                let info = Number(s.rows || 0).toLocaleString() + ' rows stored';
                info += s.csv_first
                    ? ' · Veeqo CSV ' + day(s.csv_first) + ' → ' + day(s.csv_last) + ' (imported ' + String(s.csv_imported_at).replace('T', ' ').slice(0, 16) + ')'
                    : ' · no Veeqo CSV imported yet';
                if (s.api_first) info += ' · Amazon API ' + day(s.api_first) + ' → ' + day(s.api_last) + ' (synced ' + String(s.api_synced_at).replace('T', ' ').slice(0, 16) + ')';
                document.getElementById('vcSyncInfo').textContent = info;

                const comps = json.components || [];
                if (rebuildIfChanged && comps.join(',') !== components.join(',')) {
                    components = comps;
                    buildTable();
                }
                return json;
            }

            function refresh() {
                if (table) table.setData();
                loadSummary(true);
            }

            async function openRaw(row) {
                document.getElementById('vcRawTitle').textContent = (row.description || 'Transaction') + ' · ' + (row.order_number || '');
                document.getElementById('vcRaw').textContent = 'Loading…';
                rawModal.show();
                try {
                    const res = await fetch('/veeqo-chargebacks/transaction/' + encodeURIComponent(row.id), { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    document.getElementById('vcRaw').textContent = JSON.stringify(json.transaction || json, null, 2);
                } catch (e) {
                    document.getElementById('vcRaw').textContent = 'Could not load transaction.';
                }
            }

            async function syncNow(btn) {
                btn.disabled = true;
                const html = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing…';
                try {
                    const res = await fetch('{{ route('veeqo.chargebacks.sync') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    });
                    const json = await res.json();
                    alert(json.message || (res.ok ? 'Sync finished.' : 'Sync failed.'));
                    refresh();
                } catch (e) {
                    alert('Sync failed: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = html;
                }
            }

            let skuModal = null;
            let skuTable = null;
            let skuMax = 1;

            function pctBadge(v) {
                if (v === null || v === undefined) return '<span class="sr-pct na">no sales</span>';
                const n = Number(v);
                const cls = n > 3 ? 'high' : (n >= 1 ? 'mid' : 'low');
                return '<span class="sr-pct ' + cls + '">' + n.toFixed(2) + '%</span>';
            }
            function absSorter(a, b) { return Math.abs(Number(a) || 0) - Math.abs(Number(b) || 0); }

            function skuKpi(cls, icon, k, v) {
                return '<div class="sr-kpi ' + cls + '"><div class="k">' + esc(k) + '</div><div class="v">' + v + '</div><i class="fas ' + icon + '"></i></div>';
            }

            function buildSkuTable(rows) {
                skuMax = Math.max(1, ...rows.map(r => Math.abs(Number(r.total) || 0)));
                if (skuTable) { skuTable.setData(rows); return; }
                skuTable = new Tabulator('#vcSkuTable', {
                    data: rows,
                    layout: 'fitColumns',
                    height: 'calc(100vh - 420px)',
                    placeholder: 'No chargebacks in the last 12 months for these filters.',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, true],
                    initialSort: [{ column: 'total', dir: 'desc' }],
                    columns: [
                        { title: '#', field: '_rank', width: 56, hozAlign: 'center', headerSort: false,
                          formatter: c => { const p = c.getRow().getPosition(); return '<span class="sr-rank ' + (p <= 3 ? 'r' + p : '') + '">' + p + '</span>'; } },
                        { title: 'SKU', field: 'sku', minWidth: 200, headerFilter: 'input', formatter: c => '<span class="sr-sku">' + esc(c.getValue()) + '</span>' },
                        { title: 'Chargeback Count', field: 'count', hozAlign: 'center', width: 130, formatter: c => '<span class="sr-count">' + c.getValue() + '</span>' },
                        { title: 'Charge Lines', field: 'lines', hozAlign: 'center', width: 100, formatter: c => '<span class="text-muted">' + c.getValue() + '</span>' },
                        { title: 'Total Chargeback', field: 'total', minWidth: 190, sorter: absSorter,
                          formatter: c => { const v = Number(c.getValue()) || 0; const w = Math.round(Math.abs(v) / skuMax * 100); return '<div class="sr-bar"><span style="width:' + w + '%"></span><b>' + money(v) + '</b></div>'; } },
                        { title: 'Avg Chargeback', field: 'average', hozAlign: 'right', width: 140, sorter: absSorter, formatter: c => money(c.getValue(), true) },
                        { title: 'Units Sold (12 mo)', field: 'sold', hozAlign: 'right', width: 150,
                          formatter: c => c.getValue() === null ? '<span class="text-muted">—</span>' : '<span class="sr-sold">' + Number(c.getValue()).toLocaleString() + '</span>' },
                        { title: 'Chargeback %', field: 'pct', hozAlign: 'center', width: 130, sorter: (a, b) => (a ?? -1) - (b ?? -1), formatter: c => pctBadge(c.getValue()) },
                    ],
                });
                ['dataSorted', 'dataFiltered'].forEach(ev => skuTable.on(ev, () => setTimeout(() => skuTable.getRows('active').forEach(r => r.reformat()), 0)));
            }

            async function openSkuReport() {
                if (!skuModal) skuModal = new bootstrap.Modal(document.getElementById('vcSkuModal'));
                document.getElementById('vcSkuSub').textContent = 'Loading…';
                document.getElementById('vcSkuKpis').innerHTML = '';
                skuModal.show();
                try {
                    const p = params();
                    delete p.from; delete p.to; delete p.search;
                    const res = await fetch('{{ route('veeqo.chargebacks.sku-report') }}?' + qs(p), { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    const t = json.totals || {};
                    document.getElementById('vcSkuSub').textContent = json.from + ' → ' + json.to + ' · ' + Number(t.chargebacks || 0).toLocaleString() + ' orders charged back (' + Number(t.lines || 0).toLocaleString() + ' charge lines) across ' + Number(t.skus || 0).toLocaleString() + ' SKUs';
                    document.getElementById('vcSkuKpis').innerHTML =
                        skuKpi('sr-k1', 'fa-barcode', 'SKUs hit', Number(t.skus || 0).toLocaleString()) +
                        skuKpi('sr-k2', 'fa-receipt', 'Orders charged back', Number(t.chargebacks || 0).toLocaleString()) +
                        skuKpi('sr-k3', 'fa-dollar-sign', 'Total chargeback', money(t.amount || 0)) +
                        skuKpi('sr-k4', 'fa-balance-scale', 'Avg per order', money(t.average || 0)) +
                        skuKpi('sr-k5', 'fa-box-open', 'Units sold (those SKUs)', Number(t.sold || 0).toLocaleString()) +
                        skuKpi('sr-k6', 'fa-percentage', 'Overall chargeback %', t.pct === null || t.pct === undefined ? '—' : Number(t.pct).toFixed(2) + '%');
                    buildSkuTable(json.rows || []);
                    document.querySelectorAll('#vcSkuModal .btn-sort').forEach(b => b.classList.toggle('active', b.dataset.dir === 'desc'));
                } catch (e) {
                    document.getElementById('vcSkuSub').textContent = 'Could not load the SKU report: ' + e.message;
                }
            }

            async function importCsv(input) {
                const file = input.files && input.files[0];
                if (!file) return;
                const btn = document.getElementById('vcImport');
                const html = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing…';
                const form = new FormData();
                form.append('file', file);
                try {
                    const res = await fetch('{{ route('veeqo.chargebacks.import') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: form,
                    });
                    const json = await res.json().catch(() => ({}));
                    alert(json.message || (res.ok ? 'Import finished.' : 'Import failed (' + res.status + ').'));
                    refresh();
                } catch (e) {
                    alert('Import failed: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = html;
                    input.value = '';
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                rawModal = new bootstrap.Modal(document.getElementById('vcRawModal'));
                const today = new Date();
                const yearAgo = new Date();
                yearAgo.setFullYear(today.getFullYear() - 1);
                const iso = d => d.toISOString().slice(0, 10);
                document.getElementById('vcFrom').value = iso(yearAgo);
                document.getElementById('vcTo').value = iso(today);

                document.getElementById('vcSkuReport').addEventListener('click', openSkuReport);
                document.querySelectorAll('#vcSkuModal .btn-sort').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.querySelectorAll('#vcSkuModal .btn-sort').forEach(b => b.classList.toggle('active', b === btn));
                        if (skuTable) skuTable.setSort('total', btn.dataset.dir);
                    });
                });
                document.getElementById('vcSkuSearch').addEventListener('input', function () {
                    if (!skuTable) return;
                    const q = this.value.trim().toLowerCase();
                    if (q === '') skuTable.clearFilter(); else skuTable.setFilter(d => String(d.sku || '').toLowerCase().includes(q));
                });
                document.getElementById('vcSkuExport').addEventListener('click', function () {
                    if (skuTable) skuTable.download('csv', 'veeqo_chargebacks_by_sku.csv');
                });
                document.getElementById('vcImport').addEventListener('click', () => document.getElementById('vcImportFile').click());
                document.getElementById('vcImportFile').addEventListener('change', function () { importCsv(this); });
                ['vcFrom', 'vcTo', 'vcSource', 'vcType', 'vcDirection', 'vcStatus'].forEach(id => document.getElementById(id).addEventListener('change', refresh));
                let timer = null;
                document.getElementById('vcSearch').addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(refresh, 350);
                });
                document.getElementById('vcSync').addEventListener('click', function () { syncNow(this); });
                document.getElementById('vcExport').addEventListener('click', function () {
                    if (table) table.download('csv', 'veeqo_chargebacks_page.csv');
                });

                loadSummary(false).then(function (json) {
                    components = json.components || [];
                    buildTable();
                }).catch(buildTable);
            });
        })();
    </script>
@endsection
