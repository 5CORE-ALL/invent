@extends('layouts.vertical', ['title' => 'Veeqo Billing'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .vb-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .vb-toolbar { display:flex; flex-wrap:wrap; align-items:flex-end; gap:.5rem; margin-bottom:.75rem; }
        .vb-toolbar label { display:flex; flex-direction:column; gap:.2rem; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:#475569; }
        .vb-toolbar input, .vb-toolbar select { height:32px; font-size:.82rem; }
        .vb-badges { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem; }
        .vb-badge { border-radius:10px; padding:.45rem .8rem; background:#f8fafc; border:1px solid #e2e8f0; min-width:120px; }
        .vb-badge .vb-k { font-size:.68rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:.03em; }
        .vb-badge .vb-v { font-size:1.05rem; font-weight:700; color:#0f172a; }
        .vb-badge.vb-money .vb-v { color:#15803d; }
        .vb-sync { font-size:.78rem; color:#64748b; }
        #veeqo-billing-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
        #veeqo-billing-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%) !important; }
        #veeqo-billing-table .tabulator-col { background:transparent !important; color:#fff !important; border-right:1px solid rgba(255,255,255,.15); }
        #veeqo-billing-table .tabulator-col-title { font-size:11px; font-weight:700; text-transform:uppercase; white-space:normal; }
        #veeqo-billing-table .tabulator-header-filter input { border:none; border-radius:4px; padding:3px 5px; font-size:11px; color:#333; }
        #veeqo-billing-table .tabulator-cell { font-size:12.5px; color:#334155; }
        #veeqo-billing-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #veeqo-billing-table .tabulator-row:hover { background:#e8f0fe !important; }
        #vb-col-dropdown { max-height:70vh; overflow-y:auto; min-width:280px; padding:.5rem .75rem; }
        #vb-col-dropdown label { display:flex; gap:.4rem; align-items:center; font-size:.8rem; padding:2px 0; }
        #vbRaw { white-space:pre-wrap; word-break:break-word; font-family:ui-monospace,Consolas,monospace; font-size:12px; max-height:65vh; overflow:auto; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Veeqo Billing',
        'sub_title' => 'Label charges on every Veeqo shipment for the last year',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card vb-card">
                <div class="card-body">
                    <div class="vb-badges" id="vbBadges"></div>

                    <div class="vb-toolbar">
                        <label>From <input type="date" id="vbFrom" class="form-control form-control-sm"></label>
                        <label>To <input type="date" id="vbTo" class="form-control form-control-sm"></label>
                        <label>Carrier <select id="vbCarrier" class="form-select form-select-sm"><option value="">All</option></select></label>
                        <label>Channel <select id="vbChannel" class="form-select form-select-sm"><option value="">All</option></select></label>
                        <label>Tracking status <select id="vbStatus" class="form-select form-select-sm"><option value="">All</option></select></label>
                        <label>Label
                            <select id="vbLabel" class="form-select form-select-sm">
                                <option value="">All shipments</option>
                                <option value="with" selected>Bought in Veeqo</option>
                                <option value="without">No Veeqo label</option>
                            </select>
                        </label>
                        <label style="min-width:220px;flex:1;">Search
                            <input type="search" id="vbSearch" class="form-control form-control-sm" placeholder="Order, tracking, SKU, channel, service…">
                        </label>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="vb-col-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="vbExport"><i class="fas fa-download"></i> Export page</button>
                        <button type="button" class="btn btn-sm btn-success" id="vbSync"><i class="fas fa-sync"></i> Sync now</button>
                    </div>
                    <div class="vb-sync mb-2" id="vbSyncInfo"></div>

                    <div id="veeqo-billing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="vbRawModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="vbRawTitle">Shipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><div id="vbRaw"></div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const COL_KEY = 'veeqo_billing_hidden_cols_v1';
            let table = null;
            let rawModal = null;
            let chargeIds = [];

            function esc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function money(v) {
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                return isNaN(n) ? esc(v) : '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function moneyCell(cell) { return money(cell.getValue()); }
            function boolCell(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined) return '';
                return v ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>';
            }
            function label(id) {
                return id.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            }
            function params() {
                return {
                    from: document.getElementById('vbFrom').value,
                    to: document.getElementById('vbTo').value,
                    carrier: document.getElementById('vbCarrier').value,
                    channel: document.getElementById('vbChannel').value,
                    status: document.getElementById('vbStatus').value,
                    label: document.getElementById('vbLabel').value,
                    search: document.getElementById('vbSearch').value.trim(),
                };
            }
            function qs(obj) {
                return Object.keys(obj).filter(k => obj[k] !== '').map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k])).join('&');
            }

            function col(title, field, extra) {
                return Object.assign({ title: title, field: field, headerFilter: 'input', headerFilterLiveFilter: false, minWidth: 90 }, extra || {});
            }
            function moneyCol(title, field) {
                return col(title, field, { hozAlign: 'right', formatter: moneyCell, headerFilter: false, minWidth: 90 });
            }
            function boolCol(title, field) {
                return col(title, field, { hozAlign: 'center', formatter: boolCell, headerFilter: false, minWidth: 70 });
            }

            function columns() {
                const charges = chargeIds.map(id => moneyCol(label(id), 'charge_' + id));
                return [
                    {
                        title: '', field: '_view', width: 44, headerSort: false, hozAlign: 'center', frozen: true,
                        formatter: () => '<button type="button" class="btn btn-sm btn-outline-info py-0 px-1" title="All Veeqo fields"><i class="fas fa-eye"></i></button>',
                        cellClick: (e, cell) => openRaw(cell.getRow().getData()),
                    },
                    col('Shipped (label)', 'shipment_created_at', { frozen: true, minWidth: 130 }),
                    col('Order #', 'order_number', { frozen: true, minWidth: 130 }),
                    moneyCol('Label Cost', 'label_cost'),
                    moneyCol('Surcharges', 'surcharge_total'),
                    moneyCol('Carrier Fees', 'carrier_fee_total'),
                    moneyCol('Total Billed', 'total_cost'),
                    col('Currency', 'label_currency', { minWidth: 70 }),
                    ...charges,
                    col('Carrier', 'sub_carrier_id'),
                    col('Service', 'service_name', { minWidth: 180 }),
                    col('Short Service', 'short_service_name'),
                    col('Service Carrier', 'service_carrier_name'),
                    col('Service Type', 'service_type'),
                    col('Carrier Account', 'carrier_name'),
                    col('Carrier Slug', 'carrier_slug'),
                    col('Provider', 'provider_type'),
                    col('Carrier ID', 'carrier_id'),
                    col('Tracking #', 'tracking_number', { minWidth: 180 }),
                    col('Tracking Status', 'tracking_status'),
                    boolCol('Billed', 'tracking_billed'),
                    boolCol('Cancelled', 'tracking_cancelled'),
                    boolCol('Used', 'tracking_used'),
                    col('In Transit', 'in_transit_at', { minWidth: 130 }),
                    col('Delivered', 'delivered_at', { minWidth: 130 }),
                    col('Cancelled At', 'cancelled_at', { minWidth: 130 }),
                    col('Returned At', 'returned_to_sender_at', { minWidth: 130 }),
                    moneyCol('Insured Value', 'insured_value'),
                    col('Shipment Wt', 'shipment_weight', { hozAlign: 'right', headerFilter: false }),
                    col('Allocation Wt', 'allocation_weight', { hozAlign: 'right', headerFilter: false }),
                    col('Wt Unit', 'weight_unit', { minWidth: 60 }),
                    boolCol('Return Label', 'has_return_label'),
                    col('Shipped By', 'shipped_by'),
                    col('Outbound Date', 'outbound_shipment_date', { minWidth: 130 }),
                    col('Channel', 'channel_name'),
                    col('Channel Type', 'channel_type'),
                    col('Warehouse', 'warehouse_name'),
                    col('Order Status', 'order_status'),
                    col('Order Created', 'order_created_at', { minWidth: 130 }),
                    col('Order Shipped', 'order_shipped_at', { minWidth: 130 }),
                    moneyCol('Order Total', 'order_total_price'),
                    moneyCol('Order Subtotal', 'order_subtotal_price'),
                    moneyCol('Order Delivery', 'order_delivery_cost'),
                    col('Order Currency', 'order_currency', { minWidth: 70 }),
                    col('Delivery Method', 'delivery_method'),
                    col('Line Items', 'line_item_count', { hozAlign: 'right', headerFilter: false, minWidth: 70 }),
                    col('SKUs', 'skus', { minWidth: 160 }),
                    col('Shipment ID', 'shipment_id'),
                    col('Allocation ID', 'allocation_id'),
                    col('Order ID', 'order_id'),
                    col('Veeqo Updated', 'veeqo_updated_at', { minWidth: 130 }),
                ];
            }

            function hiddenCols() {
                try { return JSON.parse(localStorage.getItem(COL_KEY) || '[]'); } catch (_) { return []; }
            }

            function buildColumnPicker() {
                const menu = document.getElementById('vb-col-dropdown');
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
                table = new Tabulator('#veeqo-billing-table', {
                    layout: 'fitDataStretch',
                    height: 'calc(100vh - 330px)',
                    placeholder: 'No Veeqo shipments for these filters yet.',
                    pagination: true,
                    paginationMode: 'remote',
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, 1000],
                    paginationCounter: 'rows',
                    sortMode: 'remote',
                    filterMode: 'remote',
                    ajaxURL: '{{ route('veeqo.billing.data') }}',
                    ajaxParams: params,
                    ajaxResponse: function (url, p, response) {
                        return response;
                    },
                    columns: columns(),
                });
                table.on('tableBuilt', buildColumnPicker);
            }

            function setOptions(id, values) {
                const sel = document.getElementById(id);
                const current = sel.value;
                sel.innerHTML = '<option value="">All</option>' + (values || []).map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
                sel.value = current;
            }

            function badge(k, v, cls) {
                return '<div class="vb-badge ' + (cls || '') + '"><div class="vb-k">' + esc(k) + '</div><div class="vb-v">' + v + '</div></div>';
            }

            async function loadSummary(rebuildIfChargesChange) {
                const res = await fetch('{{ route('veeqo.billing.summary') }}?' + qs(params()), { headers: { Accept: 'application/json' } });
                const json = await res.json();
                const t = json.totals || {};
                document.getElementById('vbBadges').innerHTML =
                    badge('Shipments', Number(t.shipments || 0).toLocaleString()) +
                    badge('Orders', Number(t.orders || 0).toLocaleString()) +
                    badge('Veeqo labels', Number(t.labelled || 0).toLocaleString()) +
                    badge('Label cost', money(t.label_cost || 0), 'vb-money') +
                    badge('Surcharges', money(t.surcharge_total || 0), 'vb-money') +
                    badge('Carrier fees', money(t.carrier_fee_total || 0), 'vb-money') +
                    badge('Total billed', money(t.total_cost || 0), 'vb-money');

                const f = json.filters || {};
                setOptions('vbCarrier', f.carriers);
                setOptions('vbChannel', f.channels);
                setOptions('vbStatus', f.statuses);

                const s = json.sync || {};
                let info = Number(s.rows || 0).toLocaleString() + ' shipments stored';
                if (s.last_incremental_at) info += ' · last sync ' + String(s.last_incremental_at).replace('T', ' ').slice(0, 16);
                if (s.running) info += ' · 1-year backfill in progress (next Veeqo page ' + s.page + ')';
                document.getElementById('vbSyncInfo').textContent = info;

                const ids = json.charge_ids || [];
                if (rebuildIfChargesChange && ids.join(',') !== chargeIds.join(',')) {
                    chargeIds = ids;
                    buildTable();
                }
                return json;
            }

            function refresh() {
                if (table) table.setData();
                loadSummary(true);
            }

            async function openRaw(row) {
                document.getElementById('vbRawTitle').textContent = 'Shipment ' + row.shipment_id + ' · ' + (row.order_number || '');
                document.getElementById('vbRaw').textContent = 'Loading…';
                rawModal.show();
                try {
                    const res = await fetch('/veeqo-billing/shipment/' + encodeURIComponent(row.shipment_id), { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    document.getElementById('vbRaw').textContent = JSON.stringify(json.shipment || json, null, 2);
                } catch (e) {
                    document.getElementById('vbRaw').textContent = 'Could not load shipment.';
                }
            }

            async function syncNow(btn) {
                btn.disabled = true;
                const html = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing…';
                try {
                    const res = await fetch('{{ route('veeqo.billing.sync') }}', {
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
                rawModal = new bootstrap.Modal(document.getElementById('vbRawModal'));
                const today = new Date();
                const yearAgo = new Date();
                yearAgo.setFullYear(today.getFullYear() - 1);
                const iso = d => d.toISOString().slice(0, 10);
                document.getElementById('vbFrom').value = iso(yearAgo);
                document.getElementById('vbTo').value = iso(today);

                ['vbFrom', 'vbTo', 'vbCarrier', 'vbChannel', 'vbStatus', 'vbLabel'].forEach(id => document.getElementById(id).addEventListener('change', refresh));
                let timer = null;
                document.getElementById('vbSearch').addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(refresh, 350);
                });
                document.getElementById('vbSync').addEventListener('click', function () { syncNow(this); });
                document.getElementById('vbExport').addEventListener('click', function () {
                    if (table) table.download('csv', 'veeqo_billing_page.csv');
                });

                loadSummary(false).then(function (json) {
                    chargeIds = json.charge_ids || [];
                    buildTable();
                }).catch(function () {
                    buildTable();
                });
            });
        })();
    </script>
@endsection
