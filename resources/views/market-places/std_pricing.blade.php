@extends('layouts.vertical', ['title' => 'Std pricing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #std-pricing-wrap .tabulator {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #std-pricing-wrap .tabulator .tabulator-header {
            background: #d9ebf7;
            border-bottom: 1px solid #c5d9ea;
            color: #1f2937;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col {
            background: #d9ebf7;
            border-right: 1px solid #edf2f7;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-size: 13px;
            font-weight: 600;
        }
        #std-pricing-wrap .tabulator .tabulator-cell { padding: 6px 8px !important; vertical-align: middle; }
        #std-pricing-wrap .tabulator-row.tabulator-row-even { background: #f3f4f6; }
        #std-pricing-wrap .tabulator-row.tabulator-row-odd { background: #fff; }
        #std-pricing-wrap .tabulator-row.tabulator-selected { background: #e7f1ff !important; }
        .std-pricing-thumb { width: 42px; height: 42px; object-fit: contain; border-radius: 4px; background: #fff; }
        .std-pricing-price { font-weight: 700; color: #16a34a; }
        .std-pricing-edit {
            border: 1px solid #14b8c4;
            color: #0e9aa6;
            background: #fff;
            border-radius: 6px;
            padding: 2px 12px;
            font-size: 13px;
            line-height: 1.4;
            cursor: pointer;
        }
        .std-pricing-edit:hover { background: #f0fdfa; }
        #std-pricing-wrap .tabulator .tabulator-calcs-holder { background: #eef6fb; font-weight: 700; }
        #std-pricing-wrap .tabulator .tabulator-calcs-holder .tabulator-cell { padding: 6px 8px !important; }
        @include('market-places.partials.std_pricing_sprc_dil', ['stdSprcDilPart' => 'css'])
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Std pricing',
        'sub_title' => "LMP's Master",
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2" id="std-pricing-p-badges">
                        <span class="badge fs-6 p-2" id="std-badge-p-sales" style="background:#198754;color:#fff;font-weight:700;"
                            title="Σ P sales. P sales = inv × Std Price">P Sales: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-inv-avg" style="background:#ffc107;color:#000;font-weight:700;"
                            title="Inv avg price = Σ (inv × Std Price) / Σ inv">Inv Avg Price: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-groi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="P GROI% = Σ P PFT / Σ (inv × LP). 20% margin, ads not included.">P GROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="P GPFT% = Σ P PFT / Σ P sales. 20% margin, ads not included.">P GPFT%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gnroi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="P GNROI% = Σ (P PFT − P sales × 10%) / Σ (inv × LP).">P GNROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gnpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="P GNPFT% = Σ (P PFT − P sales × 10%) / Σ P sales.">P GNPFT%: —</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="std-pricing-total" class="badge bg-secondary">Total: —</span>
                        <span id="std-pricing-selected" class="badge bg-primary">Selected: 0</span>
                        <input type="search" id="std-pricing-search-parent" class="form-control form-control-sm"
                            placeholder="Search parent" autocomplete="off" style="max-width: 200px;">
                        <input type="search" id="std-pricing-search-sku" class="form-control form-control-sm"
                            placeholder="Search SKU" autocomplete="off" style="max-width: 200px;">
                        <button type="button" id="std-pricing-refresh" class="btn btn-sm btn-outline-primary" title="Reload">
                            <i class="ri-refresh-line"></i>
                        </button>
                        @include('market-places.partials.std_pricing_sprc_dil', ['stdSprcDilPart' => 'button'])
                        <span class="text-muted small" id="std-pricing-status">Loading…</span>
                    </div>
                    <div id="std-pricing-wrap">
                        <div id="std-pricing-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('market-places.partials.std_pricing_sprc_dil', ['stdSprcDilPart' => 'modal'])

    <div class="modal fade" id="stdPricingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Std Price — <span id="std-pricing-sku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-bold" for="std-pricing-input">Std Price</label>
                    <input type="number" class="form-control" id="std-pricing-input" step="0.01" min="0.01" placeholder="0.00">
                    <div class="form-text">Saves Std Price for this SKU and its Sku Link LMP siblings.</div>
                    <div class="small mt-2" id="std-pricing-msg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="std-pricing-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            function money(value) {
                const n = parseFloat(value);
                if (!isFinite(n) || n <= 0) {
                    return '<span class="text-muted">—</span>';
                }
                return '<span class="std-pricing-price">$' + n.toFixed(2) + '</span>';
            }

            function moneySigned(value) {
                const n = parseFloat(value);
                if (!isFinite(n)) return '<span class="text-muted">—</span>';
                const text = (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                if (n === 0) return '<span class="text-muted">' + text + '</span>';
                return '<span class="std-pricing-price">' + text + '</span>';
            }

            function countCell(value) {
                return Math.round(parseFloat(value) || 0).toLocaleString('en-US');
            }

            const STD_MARGIN = 0.80;
            const STD_ADS = 0.10;

            function stdMetrics(price, lp, ship) {
                const std = parseFloat(price);
                const cost = parseFloat(lp);
                const freight = parseFloat(ship) || 0;
                if (!isFinite(std) || std <= 0) {
                    return { std_groi: null, std_gpft: null, std_gnroi: null, std_gnpft: null };
                }
                const landed = isFinite(cost) && cost > 0 ? cost : 0;
                const gross = (std * STD_MARGIN) - freight - landed;
                const net = gross - (std * STD_ADS);
                const round2 = function (n) { return Math.round(n * 100) / 100; };
                return {
                    std_groi: landed > 0 ? round2((gross / landed) * 100) : null,
                    std_gpft: round2((gross / std) * 100),
                    std_gnroi: landed > 0 ? round2((net / landed) * 100) : null,
                    std_gnpft: round2((net / std) * 100),
                };
            }

            function projectedMetrics(price, lp, ship, inv) {
                const std = parseFloat(price);
                const qty = parseFloat(inv) || 0;
                const cost = parseFloat(lp);
                const freight = parseFloat(ship) || 0;
                const empty = { p_sales: null, p_pft: null, p_groi: null, p_gpft: null, p_gnroi: null, p_gnpft: null };
                if (!isFinite(std) || std <= 0) return empty;
                const landed = isFinite(cost) && cost > 0 ? cost : 0;
                const round2 = function (n) { return Math.round(n * 100) / 100; };
                const pSales = round2(qty * std);
                const pPft = round2(qty * ((std * STD_MARGIN) - freight - landed));
                const pCogs = qty * landed;
                const pNet = pPft - (pSales * STD_ADS);
                return {
                    p_sales: pSales,
                    p_pft: pPft,
                    p_groi: Math.abs(pCogs) > 0.00001 ? round2((pPft / pCogs) * 100) : null,
                    p_gpft: Math.abs(pSales) > 0.00001 ? round2((pPft / pSales) * 100) : null,
                    p_gnroi: Math.abs(pCogs) > 0.00001 ? round2((pNet / pCogs) * 100) : null,
                    p_gnpft: Math.abs(pSales) > 0.00001 ? round2((pNet / pSales) * 100) : null,
                };
            }

            function projectedPool(rows) {
                let sales = 0;
                let pft = 0;
                let cogs = 0;
                let invQty = 0;
                (rows || []).forEach(function (row) {
                    const salesN = parseFloat(row.p_sales);
                    const pftN = parseFloat(row.p_pft);
                    const qty = parseFloat(row.inv) || 0;
                    const cost = parseFloat(row.lp);
                    const std = parseFloat(row.std_price);
                    if (isFinite(salesN)) sales += salesN;
                    if (isFinite(pftN)) pft += pftN;
                    if (isFinite(cost) && cost > 0) cogs += qty * cost;
                    if (isFinite(std) && std > 0) invQty += qty;
                });
                const net = pft - (sales * STD_ADS);
                return {
                    p_gpft: Math.abs(sales) > 0.00001 ? (pft / sales) * 100 : null,
                    p_gnpft: Math.abs(sales) > 0.00001 ? (net / sales) * 100 : null,
                    p_groi: Math.abs(cogs) > 0.00001 ? (pft / cogs) * 100 : null,
                    p_gnroi: Math.abs(cogs) > 0.00001 ? (net / cogs) * 100 : null,
                    p_sales: sales,
                    inv_avg_price: Math.abs(invQty) > 0.00001 ? (sales / invQty) : null,
                };
            }

            function rowsForCalc() {
                try {
                    if (table) return table.getData('active');
                } catch (e) {}
                return [];
            }

            function pctCell(value, kind) {
                if (value == null || value === '') return '<span class="text-muted">—</span>';
                const pct = parseFloat(value);
                if (!isFinite(pct)) return '<span class="text-muted">—</span>';
                if (window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
                    return MetricPctColors.htmlFor(kind, pct, { decimals: 0, empty: '—' });
                }
                return Math.round(pct) + '%';
            }

            function pctColumn(title, field, kind, tip) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 100,
                    sorter: 'number',
                    headerTooltip: tip,
                    formatter: function (cell) { return pctCell(cell.getValue(), kind); },
                };
            }

            function projectedColumn(title, field, kind, tip) {
                return {
                    title: title,
                    field: field,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 100,
                    sorter: 'number',
                    headerTooltip: tip,
                    formatter: function (cell) { return pctCell(cell.getValue(), kind); },
                    bottomCalc: function () {
                        return projectedPool(rowsForCalc())[field];
                    },
                    bottomCalcFormatter: function (cell) {
                        return '<strong>' + pctCell(cell.getValue(), kind) + '</strong>';
                    },
                };
            }

            const modalEl = document.getElementById('stdPricingModal');
            const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;
            let editRow = null;

            function openModal(row) {
                editRow = row;
                const data = row.getData();
                document.getElementById('std-pricing-sku').textContent = data.sku || '';
                const current = parseFloat(data.std_price);
                document.getElementById('std-pricing-input').value = (isFinite(current) && current > 0) ? current.toFixed(2) : '';
                const msg = document.getElementById('std-pricing-msg');
                msg.className = 'small mt-2';
                msg.textContent = '';
                if (modal) modal.show();
                setTimeout(function () { document.getElementById('std-pricing-input').focus(); }, 200);
            }

            function applySavedPrice(sku, price, applied) {
                const keys = {};
                (applied && applied.length ? applied : [sku]).forEach(function (item) {
                    keys[String(item || '').trim().toUpperCase()] = true;
                });
                table.getRows().forEach(function (row) {
                    const data = row.getData();
                    const rowSku = String(data.sku || '').trim().toUpperCase();
                    if (keys[rowSku]) {
                        row.update(Object.assign(
                            { std_price: price },
                            stdMetrics(price, data.lp, data.ship),
                            projectedMetrics(price, data.lp, data.ship, data.inv)
                        ));
                    }
                });
                updateCounts();
            }

            let table = null;
            window.stdPricingTable = null;
            table = new Tabulator('#std-pricing-table', {
                height: 'calc(100vh - 260px)',
                layout: 'fitColumns',
                placeholder: 'No SKUs',
                selectable: true,
                pagination: true,
                paginationMode: 'local',
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                ajaxURL: @json(route('std.pricing.data')),
                ajaxConfig: 'GET',
                ajaxResponse: function (url, params, response) {
                    const meta = response && response.meta ? response.meta : {};
                    document.getElementById('std-pricing-status').textContent =
                        'Loaded · ' + (meta.refreshed_at || '') +
                        ' · SKUs: ' + (meta.sku_count || 0).toLocaleString();
                    return (response && response.data) ? response.data : [];
                },
                columns: [
                    {
                        title: '',
                        formatter: 'rowSelection',
                        titleFormatter: 'rowSelection',
                        titleFormatterParams: { rowRange: 'active' },
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 48,
                        frozen: true,
                    },
                    {
                        title: 'image',
                        field: 'image',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 72,
                        frozen: true,
                        formatter: function (cell) {
                            const url = cell.getValue();
                            if (!url) return '<span class="text-muted">—</span>';
                            const safe = String(url).replace(/"/g, '&quot;');
                            return '<img class="std-pricing-thumb" src="' + safe + '" alt="">';
                        },
                    },
                    { title: 'parent', field: 'parent', width: 130, frozen: true },
                    { title: 'sku', field: 'sku', width: 170, frozen: true },
                    {
                        title: 'inv',
                        field: 'inv',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 80,
                        sorter: 'number',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'ovl30',
                        field: 'ovl30',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerTooltip: 'Overall L30 units from Shopify',
                        formatter: function (cell) { return countCell(cell.getValue()); },
                    },
                    {
                        title: 'dil',
                        field: 'dil',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 80,
                        sorter: 'number',
                        headerTooltip: 'dil = ovl30 / inv × 100',
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ov = parseFloat(row.ovl30) || 0;
                            if (inv <= 0 || ov <= 0) {
                                return '<span style="color:#9ca3af;">0%</span>';
                            }
                            const dil = (ov / inv) * 100;
                            return '<span style="color:#e11d8c;font-weight:700;">' + Math.round(dil) + '%</span>';
                        },
                    },
                    {
                        title: 'Std Price',
                        field: 'std_price',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 110,
                        sorter: 'number',
                        headerTooltip: 'Amazon Standard Price (amazon_data_view.STANDARD_PRICE)',
                        formatter: function (cell) { return money(cell.getValue()); },
                    },
                    {
                        title: 'Sprc Dil',
                        field: 'sprc_dil',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 100,
                        sorter: 'number',
                        headerTooltip: 'Dil slab → Target NROI. (LP × (1 + NROI%/100) + Ship) / (0.80 − 10%). Rules saved in std_pricing_sprc_dil.',
                        formatter: function (cell) {
                            const live = window.stdPricingSprcForRow
                                ? window.stdPricingSprcForRow(cell.getRow().getData())
                                : parseFloat(cell.getValue());
                            const n = parseFloat(live);
                            if (!isFinite(n) || n <= 0) return '<span class="text-muted">—</span>';
                            return '<span class="std-sprc-dil-price">$' + n.toFixed(2) + '</span>';
                        },
                    },
                    pctColumn('STD GROI%', 'std_groi', 'groi', '((Std Price × 0.80 − ship − LP) / LP) × 100. 20% margin, ads not included.'),
                    pctColumn('STD GPFT%', 'std_gpft', 'gpft', '((Std Price × 0.80 − ship − LP) / Std Price) × 100. 20% margin, ads not included.'),
                    pctColumn('STD GNROI%', 'std_gnroi', 'nroi', '((Std Price × 0.80 − ship − LP − Std Price × 10%) / LP) × 100.'),
                    pctColumn('STD GNPFT%', 'std_gnpft', 'npft', 'STD GPFT% minus 10% ads.'),
                    {
                        title: 'P sales',
                        field: 'p_sales',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 110,
                        sorter: 'number',
                        headerTooltip: 'P sales = inv × Std Price',
                        formatter: function (cell) { return moneySigned(cell.getValue()); },
                        bottomCalc: function () {
                            return projectedPool(rowsForCalc()).p_sales;
                        },
                        bottomCalcFormatter: function (cell) {
                            return '<strong>' + moneySigned(cell.getValue()) + '</strong>';
                        },
                    },
                    {
                        title: 'P PFT',
                        field: 'p_pft',
                        visible: false,
                        sorter: 'number',
                        headerTooltip: 'Hidden. inv × (Std Price × 0.80 − ship − LP)',
                    },
                    projectedColumn('P GROI%', 'p_groi', 'groi', 'All rows: Σ P PFT ÷ Σ (inv × LP). Same 20% margin, ads not included.'),
                    projectedColumn('P GPFT%', 'p_gpft', 'gpft', 'All rows: Σ P PFT ÷ Σ P sales. Same 20% margin, ads not included.'),
                    projectedColumn('P GNROI%', 'p_gnroi', 'nroi', 'All rows: Σ (P PFT − P sales × 10%) ÷ Σ (inv × LP).'),
                    projectedColumn('P GNPFT%', 'p_gnpft', 'npft', 'All rows: Σ (P PFT − P sales × 10%) ÷ Σ P sales.'),
                    {
                        title: 'Edit',
                        field: 'edit',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        width: 90,
                        formatter: function () {
                            return '<button type="button" class="std-pricing-edit">Edit</button>';
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            openModal(cell.getRow());
                        },
                    },
                ],
            });

            function moneyBadge(value) {
                const n = parseFloat(value);
                if (!isFinite(n)) return '—';
                return (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            function paintPctBadge(id, label, value, kind) {
                const el = document.getElementById(id);
                if (!el) return;
                const n = parseFloat(value);
                el.textContent = label + ': ' + (isFinite(n) ? (Math.round(n) + '%') : '—');
                if (!window.MetricPctColors || !isFinite(n)) return;
                const color = MetricPctColors.colorFor(kind, n);
                if (!color) return;
                el.style.background = color;
                el.style.color = String(color).toLowerCase() === '#ffc107' ? '#000' : '#fff';
            }

            function updateCounts() {
                document.getElementById('std-pricing-total').textContent =
                    'Total: ' + table.getDataCount('active').toLocaleString();
                document.getElementById('std-pricing-selected').textContent =
                    'Selected: ' + table.getSelectedRows().length.toLocaleString();
                const pool = projectedPool(rowsForCalc());
                const salesEl = document.getElementById('std-badge-p-sales');
                const avgEl = document.getElementById('std-badge-inv-avg');
                if (salesEl) salesEl.textContent = 'P Sales: ' + moneyBadge(pool.p_sales);
                if (avgEl) avgEl.textContent = 'Inv Avg Price: ' + moneyBadge(pool.inv_avg_price);
                paintPctBadge('std-badge-p-groi', 'P GROI%', pool.p_groi, 'groi');
                paintPctBadge('std-badge-p-gpft', 'P GPFT%', pool.p_gpft, 'gpft');
                paintPctBadge('std-badge-p-gnroi', 'P GNROI%', pool.p_gnroi, 'nroi');
                paintPctBadge('std-badge-p-gnpft', 'P GNPFT%', pool.p_gnpft, 'npft');
            }

            window.stdPricingTable = table;
            table.on('dataProcessed', function () {
                updateCounts();
                if (typeof window.stdPricingStampSprcDil === 'function') window.stdPricingStampSprcDil();
            });
            table.on('rowSelectionChanged', updateCounts);
            table.on('dataFiltered', updateCounts);

            let searchTimer = null;
            function applySearch() {
                const parentTerm = (document.getElementById('std-pricing-search-parent').value || '').trim().toLowerCase();
                const skuTerm = (document.getElementById('std-pricing-search-sku').value || '').trim().toLowerCase();
                if (parentTerm === '' && skuTerm === '') {
                    table.clearFilter();
                    return;
                }
                table.setFilter(function (data) {
                    const parentOk = parentTerm === ''
                        || String(data.parent || '').toLowerCase().indexOf(parentTerm) !== -1;
                    const skuOk = skuTerm === ''
                        || String(data.sku || '').toLowerCase().indexOf(skuTerm) !== -1;
                    return parentOk && skuOk;
                });
            }
            ['std-pricing-search-parent', 'std-pricing-search-sku'].forEach(function (id) {
                document.getElementById(id).addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(applySearch, 200);
                });
            });

            document.getElementById('std-pricing-refresh').addEventListener('click', function () {
                document.getElementById('std-pricing-status').textContent = 'Loading…';
                table.setData();
            });

            document.getElementById('std-pricing-save').addEventListener('click', function () {
                const data = editRow ? editRow.getData() : null;
                const sku = data ? String(data.sku || '').trim() : '';
                const std = parseFloat(document.getElementById('std-pricing-input').value);
                const msg = document.getElementById('std-pricing-msg');
                if (!sku || !isFinite(std) || std <= 0) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = 'Enter a Std Price greater than 0.';
                    return;
                }
                const btn = this;
                btn.disabled = true;
                msg.className = 'small mt-2 text-muted';
                msg.textContent = 'Saving…';
                fetch(@json(route('std.pricing.save')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ sku: sku, std_price: std }),
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && body.error) ? body.error : 'Save failed');
                        return body;
                    });
                }).then(function (body) {
                    applySavedPrice(sku, parseFloat(body.std_price) || std, body.applied_skus);
                    msg.className = 'small mt-2 text-success';
                    msg.textContent = 'Saved.';
                    setTimeout(function () { if (modal) modal.hide(); }, 400);
                }).catch(function (err) {
                    msg.className = 'small mt-2 text-danger';
                    msg.textContent = err.message || 'Save failed';
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        })();
    </script>
    @include('market-places.partials.std_pricing_sprc_dil', ['stdSprcDilPart' => 'script'])
@endsection
