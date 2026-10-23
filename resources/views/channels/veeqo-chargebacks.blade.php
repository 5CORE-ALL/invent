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
                        <label>Type
                            <select id="vcType" class="form-select form-select-sm">
                                <option value="">All label adjustments</option>
                                <option value="LabmanLabelChargeBack" selected>LabmanLabelChargeBack</option>
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
                            <input type="search" id="vcSearch" class="form-control form-control-sm" placeholder="Amazon order, settlement, transaction…">
                        </label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="vc-col-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="vcExport"><i class="fas fa-download"></i> Export page</button>
                        <button type="button" class="btn btn-sm btn-success" id="vcSync"><i class="fas fa-sync"></i> Sync now</button>
                    </div>
                    <div class="vc-sync mb-2" id="vcSyncInfo"></div>

                    <div id="veeqo-chargebacks-table"></div>
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
                    col('Amazon Order', 'amazon_order_id', { frozen: true, minWidth: 150 }),
                    moneyCol('Chargeback (net)', 'total_amount'),
                    moneyCol('You Paid (label)', 'customer_charge'),
                    moneyCol('Carrier Billed', 'carrier_charge'),
                    ...components.map(t => moneyCol(label(t), 'c_' + t, false)),
                    col('Currency', 'currency', { minWidth: 70 }),
                    col('Type', 'description', { minWidth: 170 }),
                    col('Debit/Credit', 'direction', { minWidth: 80 }),
                    col('Status', 'transaction_status'),
                    col('Items', 'item_types', { minWidth: 220 }),
                    plain('Veeqo Shipped', 'v_shipped_at', { minWidth: 130 }),
                    plain('Tracking #', 'v_tracking', { minWidth: 180 }),
                    plain('Carrier', 'v_carrier'),
                    plain('Service', 'v_service', { minWidth: 180 }),
                    plain('Veeqo Label Cost', 'v_label_cost', { hozAlign: 'right', formatter: c => money(c.getValue()) }),
                    plain('Labels', 'v_labels', { hozAlign: 'right', minWidth: 60 }),
                    plain('Weight', 'v_weight'),
                    plain('Tracking Status', 'v_status'),
                    plain('SKUs', 'v_skus', { minWidth: 160 }),
                    plain('Channel', 'v_channel'),
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
                    badge('You paid (labels)', money(t.customer_total || 0)) +
                    badge('Carrier billed', money(t.carrier_total || 0));

                const f = json.filters || {};
                setOptions('vcType', f.types);
                setOptions('vcStatus', f.statuses);

                const s = json.sync || {};
                let info = Number(s.rows || 0).toLocaleString() + ' label adjustments stored';
                if (s.first_posted_at) info += ' · posted ' + String(s.first_posted_at).slice(0, 10) + ' → ' + String(s.last_posted_at).slice(0, 10);
                if (s.last_sync_at) info += ' · last sync ' + String(s.last_sync_at).replace('T', ' ').slice(0, 16);
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
                document.getElementById('vcRawTitle').textContent = (row.description || 'Transaction') + ' · ' + (row.amazon_order_id || '');
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

            document.addEventListener('DOMContentLoaded', function () {
                rawModal = new bootstrap.Modal(document.getElementById('vcRawModal'));
                const today = new Date();
                const yearAgo = new Date();
                yearAgo.setFullYear(today.getFullYear() - 1);
                const iso = d => d.toISOString().slice(0, 10);
                document.getElementById('vcFrom').value = iso(yearAgo);
                document.getElementById('vcTo').value = iso(today);

                ['vcFrom', 'vcTo', 'vcType', 'vcDirection', 'vcStatus'].forEach(id => document.getElementById(id).addEventListener('change', refresh));
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
