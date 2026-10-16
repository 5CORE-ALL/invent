@extends('layouts.vertical', ['title' => 'Spare Parts Purchase Sheet', 'mode' => '', 'demo' => ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .sp-badge { font-size: 1rem; font-weight: 700; }
        #spare-forecast-table { font-size: 0.85rem; }
        #spare-forecast-table .tabulator-col { background: #f4f7fa; }
        .sp-num { display: block; text-align: center; font-weight: 600; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-0">Spare Parts Purchase Sheet</h4>
                <div class="text-muted small">Add-on to Purchase Forecast Analysis. Suggested order qty = target stock − (available − reserved + on order), when that net stock is at or below the reorder level.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('forecast.analysis') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Forecast Analysis
                </a>
                <a href="{{ route('inventory.spares.dashboard') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-screwdriver-wrench"></i> Spare Parts Dashboard
                </a>
                <button id="sp-export-btn" type="button" class="btn btn-sm btn-success">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
                <div class="text-muted small">Spare Parts</div><div class="sp-badge" id="sp-count-total">0</div>
            </div></div></div>
            <div class="col-6 col-md-3"><div class="card border-danger"><div class="card-body py-2">
                <div class="text-muted small">Need Ordering</div><div class="sp-badge text-danger" id="sp-count-need">0</div>
            </div></div></div>
            <div class="col-6 col-md-3"><div class="card border-warning"><div class="card-body py-2">
                <div class="text-muted small">With Alerts</div><div class="sp-badge text-warning" id="sp-count-alerts">0</div>
            </div></div></div>
            <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
                <div class="text-muted small">Est. Order Value</div><div class="sp-badge" id="sp-est-total">0</div>
            </div></div></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <input type="search" id="sp-search" class="form-control form-control-sm" style="max-width:240px;" placeholder="Search SKU / part / parent / supplier">
                    <select id="sp-supplier-filter" class="form-select form-select-sm" style="max-width:220px;">
                        <option value="">All suppliers</option>
                    </select>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="sp-only-need">
                        <label class="form-check-label small" for="sp-only-need">Only parts to order</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="sp-only-alerts">
                        <label class="form-check-label small" for="sp-only-alerts">Only with alerts</label>
                    </div>
                    <button id="sp-refresh-btn" type="button" class="btn btn-sm btn-outline-secondary ms-auto">
                        <i class="fas fa-rotate"></i> Refresh
                    </button>
                </div>
                <div id="spare-forecast-table"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="spPoModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="spPoForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2 small text-muted" id="spPoMeta"></div>
                    <div class="mb-2">
                        <label class="form-label">Supplier</label>
                        <select class="form-select" id="spPoSupplier" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Qty</label>
                        <input type="number" min="1" class="form-control" id="spPoQty" required>
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="spPoNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm" type="submit">Create PO</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = {
                data: @json(route('forecast.analysis.spare-parts', ['ajax' => 1])),
                createPo: @json(route('inventory.spares.purchase-orders.create')),
                suppliers: @json(route('inventory.spares.dashboard', ['ajax' => 1]))
            };
            let supplierOptions = [];
            let rows = @json($rows ?? []);

            function esc(v) {
                return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            /** Issues to fix before purchasing a spare part. Returns [{sev, label, text}]. */
            function spareAlerts(r) {
                const out = [];
                const push = (sev, label, text) => out.push({ sev, label, text });
                if (!r.supplier) push('high', 'No supplier', 'No supplier is linked to this spare part. Assign one before ordering.');
                if (!(r.reorder_level > 0)) push('high', 'No reorder level', 'Reorder level is not set, so the sheet cannot tell when to buy.');
                if (r.reorder_level > 0 && r.net <= 0 && r.available <= 0) {
                    push('high', 'Stock-out', 'Available stock is 0 and nothing is on order.');
                }
                if (r.reserved > r.available) {
                    push('high', 'Shortage vs reqs', 'Approved requisitions need ' + r.reserved + ' but only ' + r.available + ' is in stock.');
                }
                if (r.to_order > 0 && r.on_order > 0) {
                    push('med', 'Partly on order', r.on_order + ' already on open POs, still short after that.');
                }
                if (r.to_order > 0 && !(r.max_stock_level > 0)) {
                    push('med', 'No max level', 'Max stock level not set. Order target is assumed to be 2 × reorder level.');
                }
                if (r.to_order > 0 && (r.last_unit_cost == null)) {
                    push('low', 'No cost', 'No unit cost found on earlier POs, so order value cannot be estimated.');
                }
                if (r.to_order > 0 && r.lead_time_days == null) {
                    push('low', 'No lead time', 'Lead time is not set for this part.');
                }
                if (r.max_stock_level > 0 && r.available > r.max_stock_level) {
                    push('low', 'Over max', 'Stock (' + r.available + ') is above max level (' + r.max_stock_level + ').');
                }
                return out;
            }

            const SEV_RANK = { high: 3, med: 2, low: 1 };
            const SEV_NAME = { high: 'CRITICAL', med: 'REVIEW', low: 'INFO' };
            const SEV_COLOR = {
                high: ['#fee2e2', '#b91c1c', '#fca5a5'],
                med: ['#fef3c7', '#a16207', '#fcd34d'],
                low: ['#f3f4f6', '#4b5563', '#d1d5db']
            };

            function num(cell) {
                return '<span class="sp-num">' + (cell == null ? '-' : esc(cell)) + '</span>';
            }

            const table = new Tabulator('#spare-forecast-table', {
                data: rows,
                layout: 'fitColumns',
                height: '70vh',
                index: 'id',
                placeholder: 'No spare parts found',
                initialSort: [{ column: 'to_order', dir: 'desc' }],
                columns: [
                    { title: 'Parent', field: 'parent_sku', minWidth: 110 },
                    { title: 'SKU', field: 'sku', minWidth: 130 },
                    { title: 'Part', field: 'part_name', minWidth: 160 },
                    {
                        title: 'Alert', field: 'alert', width: 130, hozAlign: 'center',
                        headerTooltip: 'Issues to address before purchasing (hover a badge for details)',
                        sorter: function (a, b, aRow, bRow) {
                            const w = { high: 100, med: 10, low: 1 };
                            const score = r => spareAlerts(r.getData()).reduce((s, x) => s + w[x.sev], 0);
                            return score(aRow) - score(bRow);
                        },
                        formatter: function (cell) {
                            const alerts = spareAlerts(cell.getRow().getData());
                            if (!alerts.length) {
                                return '<span style="color:#16a34a;font-weight:700;" title="No issues found. Ready to purchase.">&#10003;</span>';
                            }
                            const top = alerts.reduce((a, b) => SEV_RANK[b.sev] > SEV_RANK[a.sev] ? b : a);
                            const c = SEV_COLOR[top.sev];
                            const tip = alerts.map(a => '[' + SEV_NAME[a.sev] + '] ' + a.label + ': ' + a.text).join('&#10;');
                            const more = alerts.length > 1 ? ' +' + (alerts.length - 1) : '';
                            return '<span title="' + esc(tip).replace(/&amp;#10;/g, '&#10;') + '" style="display:inline-block;max-width:100%;padding:1px 6px;border-radius:10px;font-size:0.7rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:help;background:' + c[0] + ';color:' + c[1] + ';border:1px solid ' + c[2] + ';">&#9888; ' + esc(top.label) + more + '</span>';
                        }
                    },
                    { title: 'Supplier', field: 'supplier', minWidth: 130 },
                    { title: 'Avail', field: 'available', sorter: 'number', hozAlign: 'center', width: 70, headerTooltip: 'Available stock', formatter: c => num(c.getValue()) },
                    { title: 'Resvd', field: 'reserved', sorter: 'number', hozAlign: 'center', width: 70, headerTooltip: 'Reserved: approved requisition qty not yet issued', formatter: c => num(c.getValue()) },
                    { title: 'On Order', field: 'on_order', sorter: 'number', hozAlign: 'center', width: 82, headerTooltip: 'Qty still to be received on open purchase orders', formatter: c => num(c.getValue()) },
                    {
                        title: 'Net', field: 'net', sorter: 'number', hozAlign: 'center', width: 64,
                        headerTooltip: 'Net = Available − Reserved + On Order',
                        formatter: function (c) {
                            const r = c.getRow().getData();
                            const low = r.reorder_level > 0 && r.net <= r.reorder_level;
                            return '<span class="sp-num" style="color:' + (low ? '#b91c1c' : '#111827') + ';">' + esc(c.getValue()) + '</span>';
                        }
                    },
                    { title: 'Reorder', field: 'reorder_level', sorter: 'number', hozAlign: 'center', width: 76, headerTooltip: 'Reorder level: buy when net stock is at or below this', formatter: c => num(c.getValue()) },
                    { title: 'Max', field: 'max_stock_level', sorter: 'number', hozAlign: 'center', width: 60, headerTooltip: 'Max stock level (order target). Blank = 2 × reorder level', formatter: c => num(c.getValue() > 0 ? c.getValue() : null) },
                    { title: 'Lead', field: 'lead_time_days', sorter: 'number', hozAlign: 'center', width: 60, headerTooltip: 'Lead time (days)', formatter: c => num(c.getValue()) },
                    {
                        title: 'To Order', field: 'to_order', sorter: 'number', hozAlign: 'center', width: 84,
                        headerTooltip: 'Suggested qty = target − net, when net is at or below the reorder level',
                        formatter: function (c) {
                            const v = c.getValue();
                            if (!(v > 0)) return '<span class="sp-num" style="color:#6c757d;">0</span>';
                            return '<span class="sp-num" style="color:#b91c1c;font-size:0.95rem;">' + esc(v) + '</span>';
                        }
                    },
                    { title: 'Unit Cost', field: 'last_unit_cost', sorter: 'number', hozAlign: 'center', width: 82, headerTooltip: 'Unit cost on the most recent PO', formatter: c => num(c.getValue() != null ? Number(c.getValue()).toFixed(2) : null) },
                    { title: 'Est. Amt', field: 'est_amount', sorter: 'number', hozAlign: 'center', width: 82, headerTooltip: 'To Order × Unit Cost', formatter: c => num(c.getValue() != null ? Number(c.getValue()).toFixed(2) : null) },
                    {
                        title: '', field: '_po', width: 80, hozAlign: 'center', headerSort: false, download: false,
                        formatter: function (c) {
                            return '<button type="button" class="btn btn-sm btn-outline-primary py-0 sp-po-btn">PO</button>';
                        },
                        cellClick: function (e, cell) {
                            if (e.target.closest('.sp-po-btn')) openPoModal(cell.getRow().getData());
                        }
                    }
                ]
            });

            function refreshSummary() {
                const data = table.getData('active');
                const all = table.getData();
                document.getElementById('sp-count-total').textContent = all.length;
                document.getElementById('sp-count-need').textContent = all.filter(r => r.to_order > 0).length;
                document.getElementById('sp-count-alerts').textContent = all.filter(r => spareAlerts(r).length > 0).length;
                const est = data.reduce((s, r) => s + (r.est_amount || 0), 0);
                document.getElementById('sp-est-total').textContent = est.toLocaleString('en-US', { maximumFractionDigits: 0 });
            }

            function populateSupplierFilter() {
                const sel = document.getElementById('sp-supplier-filter');
                const current = sel.value;
                const names = Array.from(new Set(table.getData().map(r => r.supplier).filter(Boolean))).sort();
                sel.innerHTML = '<option value="">All suppliers</option>' + names.map(n => '<option value="' + esc(n) + '">' + esc(n) + '</option>').join('') + '<option value="__none__">(No supplier)</option>';
                sel.value = current;
            }

            function applyFilters() {
                const q = document.getElementById('sp-search').value.trim().toLowerCase();
                const sup = document.getElementById('sp-supplier-filter').value;
                const onlyNeed = document.getElementById('sp-only-need').checked;
                const onlyAlerts = document.getElementById('sp-only-alerts').checked;
                table.setFilter(function (r) {
                    if (q && ![r.sku, r.part_name, r.parent_sku, r.supplier].some(v => String(v || '').toLowerCase().includes(q))) return false;
                    if (sup === '__none__' && r.supplier) return false;
                    if (sup && sup !== '__none__' && r.supplier !== sup) return false;
                    if (onlyNeed && !(r.to_order > 0)) return false;
                    if (onlyAlerts && spareAlerts(r).length === 0) return false;
                    return true;
                });
                refreshSummary();
            }

            ['sp-search'].forEach(id => document.getElementById(id).addEventListener('input', applyFilters));
            ['sp-supplier-filter', 'sp-only-need', 'sp-only-alerts'].forEach(id => document.getElementById(id).addEventListener('change', applyFilters));

            function reload() {
                return fetch(routes.data, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(function (json) {
                        table.replaceData(json.rows || []).then(function () {
                            populateSupplierFilter();
                            applyFilters();
                        });
                    });
            }

            document.getElementById('sp-refresh-btn').addEventListener('click', reload);
            document.getElementById('sp-export-btn').addEventListener('click', function () {
                table.download('csv', 'spare-parts-purchase-sheet.csv');
            });

            // ── Create PO ──
            const poModalEl = document.getElementById('spPoModal');
            const poModal = new bootstrap.Modal(poModalEl);
            let poSku = '';

            fetch(routes.suppliers, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(function (json) { supplierOptions = json.suppliers || []; })
                .catch(function () {});

            function openPoModal(r) {
                poSku = r.sku;
                document.getElementById('spPoMeta').textContent = r.sku + ' — ' + r.part_name + ' (net ' + r.net + ', reorder ' + r.reorder_level + ')';
                const sel = document.getElementById('spPoSupplier');
                sel.innerHTML = supplierOptions.map(s => '<option value="' + s.id + '">' + esc(s.label) + '</option>').join('');
                if (r.supplier_id) sel.value = String(r.supplier_id);
                document.getElementById('spPoQty').value = r.to_order > 0 ? r.to_order : '';
                document.getElementById('spPoNotes').value = '';
                poModal.show();
            }

            document.getElementById('spPoForm').addEventListener('submit', function (e) {
                e.preventDefault();
                fetch(routes.createPo, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({
                        supplier: document.getElementById('spPoSupplier').value,
                        sku: poSku,
                        qty: parseInt(document.getElementById('spPoQty').value, 10),
                        notes: document.getElementById('spPoNotes').value || null
                    })
                })
                    .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                    .then(function (res) {
                        if (!res.ok) {
                            const errs = res.body && res.body.errors ? Object.values(res.body.errors).flat().join(' ') : '';
                            alert(errs || (res.body && res.body.message) || 'Could not create PO.');
                            return;
                        }
                        poModal.hide();
                        reload();
                    })
                    .catch(function () { alert('Could not create PO.'); });
            });

            table.on('tableBuilt', function () {
                populateSupplierFilter();
                refreshSummary();
            });
        })();
    </script>
@endsection
