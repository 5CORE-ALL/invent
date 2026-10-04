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
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col {
            height: 128px !important;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 118px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #std-pricing-wrap .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
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
        #std-column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #std-column-dropdown-menu > li.col-vis-full { list-style: none; }
        #std-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        #std-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #std-column-dropdown-menu .col-vis-group.col-vis-drop-over {
            border-color: #0d6efd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
        }
        #std-column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #std-column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #std-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #std-column-dropdown-menu .col-vis-item { list-style: none; margin: 0; padding: 0; border-radius: 4px; cursor: grab; }
        #std-column-dropdown-menu .col-vis-item:active { cursor: grabbing; }
        #std-column-dropdown-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
        #std-column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #std-column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        #std-column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            #std-column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }
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
                    <div class="d-flex flex-wrap gap-2 mb-2" id="std-pricing-current-badges">
                        <span class="badge fs-6 p-2" id="std-badge-current-sales" style="background:#198754;color:#fff;font-weight:700;"
                            title="Current sales = Σ (OVL30 units × OVL30 avg price)">Current Sales: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-current-groi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="Current GROI% = Σ current profit / Σ (OVL30 units × LP). 20% margin, ads not included.">Current GROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-current-gpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="Current GPFT% = Σ current profit / Σ current sales. 20% margin, ads not included.">Current GPFT%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-current-gnroi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="Current GNROI% = Σ (current profit − current sales × 10%) / Σ (OVL30 units × LP).">Current GNROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-current-gnpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="Current GNPFT% = Σ (current profit − current sales × 10%) / Σ current sales.">Current GNPFT%: —</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="std-pricing-p-badges">
                        <span class="badge fs-6 p-2" id="std-badge-p-sales" style="background:#198754;color:#fff;font-weight:700;"
                            title="Σ P sales. P sales = inv × Std Price">P Sales: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-groi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="P GROI% = Σ P PFT / Σ (inv × LP). 20% margin, ads not included.">P GROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="P GPFT% = Σ P PFT / Σ P sales. 20% margin, ads not included.">P GPFT%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gnroi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="P GNROI% = Σ (P PFT − P sales × 10%) / Σ (inv × LP).">P GNROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-p-gnpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="P GNPFT% = Σ (P PFT − P sales × 10%) / Σ P sales.">P GNPFT%: —</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="std-pricing-sp-badges">
                        <span class="badge fs-6 p-2" id="std-badge-sp-sales" style="background:#198754;color:#fff;font-weight:700;"
                            title="Σ S sales. S sales = inv × S P price">S Sales: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-sp-groi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="S GROI% = Σ S PFT / Σ (inv × LP). 20% margin, ads not included.">S GROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-sp-gpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="S GPFT% = Σ S PFT / Σ S sales. 20% margin, ads not included.">S GPFT%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-sp-gnroi" style="background:#6f42c1;color:#fff;font-weight:700;"
                            title="S GNROI% = Σ (S PFT − S sales × 10%) / Σ (inv × LP).">S GNROI%: —</span>
                        <span class="badge fs-6 p-2" id="std-badge-sp-gnpft" style="background:#0dcaf0;color:#000;font-weight:700;"
                            title="S GNPFT% = Σ (S PFT − S sales × 10%) / Σ S sales.">S GNPFT%: —</span>
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
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                id="stdColumnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Columns">
                                <i class="ri-layout-column-line"></i>
                            </button>
                            <ul class="dropdown-menu" id="std-column-dropdown-menu" aria-labelledby="stdColumnVisibilityDropdown"></ul>
                        </div>
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
                (rows || []).forEach(function (row) {
                    const salesN = parseFloat(row.p_sales);
                    const pftN = parseFloat(row.p_pft);
                    const qty = parseFloat(row.inv) || 0;
                    const cost = parseFloat(row.lp);
                    if (isFinite(salesN)) sales += salesN;
                    if (isFinite(pftN)) pft += pftN;
                    if (isFinite(cost) && cost > 0) cogs += qty * cost;
                });
                const net = pft - (sales * STD_ADS);
                return {
                    p_gpft: Math.abs(sales) > 0.00001 ? (pft / sales) * 100 : null,
                    p_gnpft: Math.abs(sales) > 0.00001 ? (net / sales) * 100 : null,
                    p_groi: Math.abs(cogs) > 0.00001 ? (pft / cogs) * 100 : null,
                    p_gnroi: Math.abs(cogs) > 0.00001 ? (net / cogs) * 100 : null,
                    p_sales: sales,
                };
            }

            function currentPool(rows) {
                let sales = 0;
                let pft = 0;
                let cogs = 0;
                (rows || []).forEach(function (row) {
                    const price = parseFloat(row.ovl30_price);
                    const sold = parseFloat(row.ovl30_units) || 0;
                    const cost = parseFloat(row.lp);
                    const freight = parseFloat(row.ship) || 0;
                    if (!isFinite(price) || price <= 0 || !(sold > 0)) return;
                    const landed = isFinite(cost) && cost > 0 ? cost : 0;
                    sales += sold * price;
                    pft += sold * ((price * STD_MARGIN) - freight - landed);
                    if (landed > 0) cogs += sold * landed;
                });
                const net = pft - (sales * STD_ADS);
                return {
                    sales: sales,
                    groi: Math.abs(cogs) > 0.00001 ? (pft / cogs) * 100 : null,
                    gpft: Math.abs(sales) > 0.00001 ? (pft / sales) * 100 : null,
                    gnroi: Math.abs(cogs) > 0.00001 ? (net / cogs) * 100 : null,
                    gnpft: Math.abs(sales) > 0.00001 ? (net / sales) * 100 : null,
                };
            }

            function spPool(rows) {
                let sales = 0;
                let pft = 0;
                let cogs = 0;
                (rows || []).forEach(function (row) {
                    const price = spPriceOf(row);
                    const qty = parseFloat(row.inv) || 0;
                    const cost = parseFloat(row.lp);
                    const freight = parseFloat(row.ship) || 0;
                    if (!isFinite(price) || price <= 0 || !(qty > 0)) return;
                    const landed = isFinite(cost) && cost > 0 ? cost : 0;
                    sales += qty * price;
                    pft += qty * ((price * STD_MARGIN) - freight - landed);
                    if (landed > 0) cogs += qty * landed;
                });
                const net = pft - (sales * STD_ADS);
                return {
                    sp_sales: sales,
                    sp_groi: Math.abs(cogs) > 0.00001 ? (pft / cogs) * 100 : null,
                    sp_gpft: Math.abs(sales) > 0.00001 ? (pft / sales) * 100 : null,
                    sp_gnroi: Math.abs(cogs) > 0.00001 ? (net / cogs) * 100 : null,
                    sp_gnpft: Math.abs(sales) > 0.00001 ? (net / sales) * 100 : null,
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

            function spPriceOf(row) {
                if (window.stdPricingSprcForRow) {
                    const live = window.stdPricingSprcForRow(row);
                    if (isFinite(live) && live > 0) return live;
                }
                const stored = parseFloat(row && row.sprc_dil);
                return (isFinite(stored) && stored > 0) ? stored : null;
            }

            function spMetrics(row) {
                const price = spPriceOf(row);
                const cost = parseFloat(row && row.lp);
                const freight = parseFloat(row && row.ship) || 0;
                const empty = { sp_groi: null, sp_gpft: null, sp_gnroi: null, sp_gnpft: null };
                if (!isFinite(price) || price <= 0) return empty;
                const landed = isFinite(cost) && cost > 0 ? cost : 0;
                const gross = (price * STD_MARGIN) - freight - landed;
                const net = gross - (price * STD_ADS);
                const round2 = function (n) { return Math.round(n * 100) / 100; };
                return {
                    sp_groi: landed > 0 ? round2((gross / landed) * 100) : null,
                    sp_gpft: round2((gross / price) * 100),
                    sp_gnroi: landed > 0 ? round2((net / landed) * 100) : null,
                    sp_gnpft: round2((net / price) * 100),
                };
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

            function spColumn(title, key, kind, tip) {
                return {
                    title: title,
                    field: key,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    width: 100,
                    headerTooltip: tip,
                    sorter: function (a, b, aRow, bRow) {
                        const av = spMetrics(aRow.getData())[key];
                        const bv = spMetrics(bRow.getData())[key];
                        return (isFinite(av) ? av : -Infinity) - (isFinite(bv) ? bv : -Infinity);
                    },
                    formatter: function (cell) {
                        return pctCell(spMetrics(cell.getRow().getData())[key], kind);
                    },
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
                        title: 'Use Price',
                        field: 'use_price',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 110,
                        sorter: function (a, b, aRow, bRow) {
                            const av = window.stdPricingUsePrice ? window.stdPricingUsePrice(aRow.getData()) : 0;
                            const bv = window.stdPricingUsePrice ? window.stdPricingUsePrice(bRow.getData()) : 0;
                            return (av || 0) - (bv || 0);
                        },
                        headerTooltip: 'Lower of Std Price and LMP × the Dil factor. No LMP uses My LMP from LMP Overall.',
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const n = window.stdPricingUsePrice ? window.stdPricingUsePrice(row) : null;
                            if (!isFinite(n) || n <= 0) return '<span class="text-muted">—</span>';
                            const note = window.stdPricingUsePriceNote ? window.stdPricingUsePriceNote(row) : '';
                            return '<span class="std-pricing-price" title="' + note.replace(/"/g, '&quot;') + '">$' + n.toFixed(2) + '</span>';
                        },
                    },
                    {
                        title: 'S P price',
                        field: 'sprc_dil',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        width: 110,
                        sorter: 'number',
                        headerTooltip: 'S P price from the Sprc Dil slab. (LP × (1 + Target NROI%/100) + Ship) / (0.80 − 10%).',
                        formatter: function (cell) {
                            const live = spPriceOf(cell.getRow().getData());
                            const n = parseFloat(live);
                            if (!isFinite(n) || n <= 0) return '<span class="text-muted">—</span>';
                            return '<span class="std-sprc-dil-price">$' + n.toFixed(2) + '</span>';
                        },
                    },
                    spColumn('S P GROI%', 'sp_groi', 'groi', '((S P price × 0.80 − ship − LP) / LP) × 100. 20% margin, ads not included.'),
                    spColumn('S P GPFT%', 'sp_gpft', 'gpft', '((S P price × 0.80 − ship − LP) / S P price) × 100. 20% margin, ads not included.'),
                    spColumn('S P GNROI%', 'sp_gnroi', 'nroi', '((S P price × 0.80 − ship − LP − S P price × 10%) / LP) × 100.'),
                    spColumn('S P GNPFT%', 'sp_gnpft', 'npft', 'S P GPFT% minus 10% ads.'),
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
                const current = currentPool(rowsForCalc());
                const currentSalesEl = document.getElementById('std-badge-current-sales');
                if (currentSalesEl) currentSalesEl.textContent = 'Current Sales: ' + moneyBadge(current.sales);
                paintPctBadge('std-badge-current-groi', 'Current GROI%', current.groi, 'groi');
                paintPctBadge('std-badge-current-gpft', 'Current GPFT%', current.gpft, 'gpft');
                paintPctBadge('std-badge-current-gnroi', 'Current GNROI%', current.gnroi, 'nroi');
                paintPctBadge('std-badge-current-gnpft', 'Current GNPFT%', current.gnpft, 'npft');
                const salesEl = document.getElementById('std-badge-p-sales');
                if (salesEl) salesEl.textContent = 'P Sales: ' + moneyBadge(pool.p_sales);
                paintPctBadge('std-badge-p-groi', 'P GROI%', pool.p_groi, 'groi');
                paintPctBadge('std-badge-p-gpft', 'P GPFT%', pool.p_gpft, 'gpft');
                paintPctBadge('std-badge-p-gnroi', 'P GNROI%', pool.p_gnroi, 'nroi');
                paintPctBadge('std-badge-p-gnpft', 'P GNPFT%', pool.p_gnpft, 'npft');
                const sp = spPool(rowsForCalc());
                const spSalesEl = document.getElementById('std-badge-sp-sales');
                if (spSalesEl) spSalesEl.textContent = 'S Sales: ' + moneyBadge(sp.sp_sales);
                paintPctBadge('std-badge-sp-groi', 'S GROI%', sp.sp_groi, 'groi');
                paintPctBadge('std-badge-sp-gpft', 'S GPFT%', sp.sp_gpft, 'gpft');
                paintPctBadge('std-badge-sp-gnroi', 'S GNROI%', sp.sp_gnroi, 'nroi');
                paintPctBadge('std-badge-sp-gnpft', 'S GNPFT%', sp.sp_gnpft, 'npft');
            }
            window.stdPricingUpdateCounts = updateCounts;

            window.stdPricingTable = table;

            const STD_COL_VIS_URL = @json(route('tabulator.column.visibility.get'));
            const STD_COL_VIS_SET = @json(route('tabulator.column.visibility.set'));
            const STD_COL_CHANNEL = 'std_pricing';
            const STD_COL_CATS = ['basic', 'price', 'ads', 'other'];
            const STD_COL_CAT_LABELS = { basic: 'Basic', price: 'Price', ads: 'Ads', other: 'Other' };
            const STD_COL_CAT_STORAGE = 'std_pricing_col_cats_v1';

            function stdCsrf() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            }
            function stdColPlainTitle(def) {
                const field = def && def.field ? String(def.field) : '';
                if (field === 'sprc_dil') return 'S P price';
                if (field === 'p_pft') return 'P PFT';
                const raw = (def && def.title != null) ? def.title : field;
                const t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                return t || field;
            }
            function classifyStdColumn(field, title) {
                const f = String(field || '');
                const t = String(title || '').toLowerCase();
                if (/^(image|parent|sku|inv|ovl30|dil)$/.test(f) || /\b(image|parent|sku|inv|ovl30|dil)\b/.test(t)) return 'basic';
                if (/price|sprc|groi|gpft|gnroi|gnpft|p_sales|p_pft/.test(f) || /\b(price|groi|gpft|nroi|pft|sales)\b/.test(t)) return 'price';
                if (/\bads\b/.test(t)) return 'ads';
                return 'other';
            }
            function loadStdColCats() {
                try {
                    const parsed = JSON.parse(localStorage.getItem(STD_COL_CAT_STORAGE) || '{}');
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) { return {}; }
            }
            function saveStdColCats(map) {
                try { localStorage.setItem(STD_COL_CAT_STORAGE, JSON.stringify(map || {})); } catch (e) { /* ignore */ }
            }
            function syncStdGroupHeader(groupEl) {
                if (!groupEl) return;
                const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                if (!headerCb) return;
                if (!itemCbs.length) {
                    headerCb.checked = false;
                    headerCb.indeterminate = false;
                    return;
                }
                let checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }
            function saveStdColumnVisibility() {
                const visibility = {};
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (field) visibility[field] = col.isVisible();
                });
                fetch(STD_COL_VIS_SET, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': stdCsrf(),
                    },
                    body: JSON.stringify({ channel: STD_COL_CHANNEL, visibility: visibility }),
                }).catch(function () {});
            }
            function bindStdColDrag(li, groupEls) {
                li.draggable = true;
                li.addEventListener('dragstart', function (e) {
                    e.stopPropagation();
                    li.classList.add('col-vis-dragging');
                    e.dataTransfer.setData('text/plain', li.dataset.field || '');
                    e.dataTransfer.effectAllowed = 'move';
                });
                li.addEventListener('dragend', function () {
                    li.classList.remove('col-vis-dragging');
                    Object.keys(groupEls).forEach(function (k) { groupEls[k].classList.remove('col-vis-drop-over'); });
                });
            }
            function bindStdColDrop(group, list, groupEls) {
                [group, list].forEach(function (zone) {
                    zone.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        group.classList.add('col-vis-drop-over');
                    });
                    zone.addEventListener('dragleave', function (e) {
                        if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
                    });
                    zone.addEventListener('drop', function (e) {
                        e.preventDefault();
                        group.classList.remove('col-vis-drop-over');
                        const field = e.dataTransfer.getData('text/plain');
                        const menu = document.getElementById('std-column-dropdown-menu');
                        const item = menu ? menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]') : null;
                        if (!item) return;
                        const nextCat = group.dataset.category;
                        if (!nextCat || item.dataset.group === nextCat) return;
                        const fromGroup = item.closest('.col-vis-group');
                        list.appendChild(item);
                        item.dataset.group = nextCat;
                        const cb = item.querySelector('input[type="checkbox"]');
                        if (cb) cb.dataset.group = nextCat;
                        const cats = loadStdColCats();
                        cats[field] = nextCat;
                        saveStdColCats(cats);
                        syncStdGroupHeader(fromGroup);
                        syncStdGroupHeader(group);
                    });
                });
            }
            function buildStdColumnDropdown(savedMap) {
                const menu = document.getElementById('std-column-dropdown-menu');
                if (!menu) return;
                const map = (savedMap && typeof savedMap === 'object' && !Array.isArray(savedMap)) ? savedMap : {};
                const catOverrides = loadStdColCats();
                menu.innerHTML = '';
                const groupsLi = document.createElement('li');
                groupsLi.className = 'col-vis-full';
                const groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';
                const lists = {};
                const groupEls = {};
                STD_COL_CATS.forEach(function (cat) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;
                    const titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    const groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.dataset.group = cat;
                    groupCb.title = 'Select / deselect all in ' + STD_COL_CAT_LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(STD_COL_CAT_LABELS[cat]));
                    group.appendChild(titleEl);
                    const list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                    groupEls[cat] = group;
                    bindStdColDrop(group, list, groupEls);
                });
                table.getColumns().forEach(function (col) {
                    const def = col.getDefinition();
                    const field = def.field;
                    if (!field) return;
                    const title = stdColPlainTitle(def);
                    let cat = catOverrides[field];
                    if (STD_COL_CATS.indexOf(cat) === -1) cat = classifyStdColumn(field, title);
                    const isVisible = Object.prototype.hasOwnProperty.call(map, field)
                        ? map[field] !== false
                        : (def.visible !== false);
                    const li = document.createElement('li');
                    li.className = 'col-vis-item';
                    li.dataset.field = field;
                    li.dataset.group = cat;
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = field;
                    checkbox.setAttribute('data-field', field);
                    checkbox.className = 'col-vis-field-toggle';
                    checkbox.dataset.group = cat;
                    checkbox.checked = isVisible;
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(' ' + title));
                    label.title = title + ' (drag to another header)';
                    li.appendChild(label);
                    bindStdColDrag(li, groupEls);
                    lists[cat].appendChild(li);
                });
                STD_COL_CATS.forEach(function (cat) { syncStdGroupHeader(groupEls[cat]); });
                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);
            }
            function applyStdColumnVisibility(savedMap) {
                const map = (savedMap && typeof savedMap === 'object' && !Array.isArray(savedMap)) ? savedMap : {};
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!field || !Object.prototype.hasOwnProperty.call(map, field)) return;
                    if (map[field]) col.show();
                    else col.hide();
                });
            }
            const stdColMenu = document.getElementById('std-column-dropdown-menu');
            if (stdColMenu) {
                stdColMenu.addEventListener('change', function (e) {
                    if (e.target.type !== 'checkbox') return;
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        const checked = e.target.checked;
                        const groupEl = e.target.closest('.col-vis-group');
                        const itemCbs = groupEl ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]') : [];
                        itemCbs.forEach(function (cb) {
                            const field = cb.getAttribute('data-field') || cb.value;
                            cb.checked = checked;
                            const col = table.getColumn(field);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        saveStdColumnVisibility();
                        return;
                    }
                    const field = e.target.getAttribute('data-field') || e.target.value;
                    const col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) col.show();
                    else col.hide();
                    syncStdGroupHeader(e.target.closest('.col-vis-group'));
                    saveStdColumnVisibility();
                });
                stdColMenu.addEventListener('click', function (e) {
                    if (e.target.closest('label') || e.target.type === 'checkbox') e.stopPropagation();
                });
            }
            fetch(STD_COL_VIS_URL + '?channel=' + encodeURIComponent(STD_COL_CHANNEL), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': stdCsrf() },
            }).then(function (r) { return r.json(); }).then(function (saved) {
                applyStdColumnVisibility(saved);
                buildStdColumnDropdown(saved);
            }).catch(function () {
                buildStdColumnDropdown({});
            });

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
