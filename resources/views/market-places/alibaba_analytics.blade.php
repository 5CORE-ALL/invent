@extends('layouts.vertical', ['title' => 'Alibaba Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #alibaba-analytics-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #alibaba-analytics-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa !important;
            border-right: 1px solid #e9ecef;
            color: #000 !important;
            font-weight: 700;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: #000 !important;
            padding: 6px 4px;
        }
        #summary-stats .badge {
            font-size: 1rem;
            white-space: nowrap;
            font-weight: bold;
        }
        .ab-filter-badge.active-filter { outline: 3px solid #0d6efd; outline-offset: 2px; }
        #alibaba-analytics-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 6px 8px !important;
            border-right: 1px solid #f1f5f9;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .ab-stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            min-width: 86px;
            justify-content: center;
        }
        .ab-stat-badge--rows { background: #0f172a; }
        .ab-stat-badge--active { background: #16a34a; }
        .ab-stat-badge--bulk { background: #2563eb; }
        .ab-stat-badge--manual { background: #d97706; }
        .ab-stat-badge--soh { background: #7c3aed; }
        .ab-stat-badge--inv { background: #0d9488; }
        .ab-stat-badge--ovl30 { background: #0369a1; }
        #ab-column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #ab-column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #ab-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #ab-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #ab-column-dropdown-menu .col-vis-group-title {
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
        #ab-column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #ab-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #ab-column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #ab-column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            margin: 0;
            font-size: 0.8rem;
            cursor: pointer;
            border-radius: 4px;
        }
        #ab-column-dropdown-menu .col-vis-item > label:hover {
            background: #e9ecef;
        }
        #ab-column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
        }
        @media (max-width: 576px) {
            #ab-column-dropdown-menu .col-vis-groups {
                grid-template-columns: 1fr;
            }
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'alibaba'])
        .card-loader-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Alibaba Analytics',
        'sub_title' => 'Alibaba',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative shadow-sm">
                <div class="card-body">
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-dark fs-6 p-2" id="ab-rows-badge">Row: 0</span>
                            <span class="badge bg-primary fs-6 p-2" id="ab-sales-badge"
                                title="Sales from /alibaba/daily-sales: Σ (unit price × qty) for the last 30 Pacific days.">Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="ab-gpft-badge"
                                title="GPFT = PFT ÷ Sales. PFT = (sales × margin) − (LP × AB L30). Ship is not subtracted.">GPFT: 0%</span>
                            <span class="badge bg-success fs-6 p-2" id="ab-pft-badge"
                                title="PFT = Σ ((sales × margin) − LP × AB L30). Ship is not subtracted.">PFT: $0</span>
                            <span class="badge fs-6 p-2" id="ab-groi-badge" style="background-color:#6f42c1;color:#fff;"
                                title="GROI = PFT ÷ COGS. COGS = Σ (LP × AB L30) on SKUs with sales. Ship is not subtracted.">GROI: 0%</span>
                            @include('partials.analytics-dil-badge', ['dilChannel' => 'alibaba'])
                            <span class="badge bg-success fs-6 p-2 ab-filter-badge" id="ab-sold-badge" data-filter="more" style="cursor:pointer;"
                                title="AB L30 &gt; 0 and INV &gt; 0">Sold &gt;0: <span id="ab-more-sold">0</span></span>
                            <span class="badge bg-danger fs-6 p-2 ab-filter-badge" id="ab-zero-badge" data-filter="zero" style="cursor:pointer;"
                                title="AB L30 = 0 and INV &gt; 0">0 Sold: <span id="ab-zero-sold">0</span></span>
                            <span class="badge fs-6 p-2" id="ab-blue-alert-badge"
                                style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                                title="Blue alert: INV &gt; 0 and Price ≠ S PRC (needs push). Click to show only those SKUs.">
                                <i class="fas fa-exclamation-triangle"></i> 0
                            </span>
                            <span class="badge bg-secondary fs-6 p-2"
                                title="marketplace_percentages for Alibaba. Ship is not used.">Margin: {{ number_format((float) ($marginPercent ?? 95), 2) }}%</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <select id="ab-row-type" class="form-select form-select-sm" style="width:110px;" title="All, SKU, or parent row">
                            <option value="all">All</option>
                            <option value="sku" selected>SKU</option>
                            <option value="row">Row</option>
                        </select>
                        <select id="ab-inventory-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="ab-sold-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">All</option>
                            <option value="zero">0 sold</option>
                            <option value="more">&gt; 0 sold</option>
                        </select>
                        <select id="ab-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="ab-cvr-filter" class="form-select form-select-sm" style="width:130px;" title="CVR = AB L30 ÷ OV L30">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                        <select id="ab-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>
                        <select id="ab-dil-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25–50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        <select id="ab-status-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">Status: All</option>
                            <option value="Active">Active</option>
                            <option value="Offline">Offline</option>
                        </select>
                        <input type="text" id="ab-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search Parent or SKU...">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="ab-column-visibility" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Show or hide columns">
                                <i class="fas fa-eye"></i> Columns
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" id="ab-column-dropdown-menu"></ul>
                        </div>
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'alibaba'])
                        <span class="badge bg-secondary" id="ab-selected-count" title="SKUs checked for bulk Push Prc">0 selected</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ab-clear-selection-btn" style="display:none;">Clear</button>
                        <button type="button" class="btn btn-sm btn-warning" id="ab-bulk-push-btn" disabled title="Push S PRC to Alibaba for the checked SKUs">
                            <i class="fas fa-upload me-1"></i> Push selected
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="ab-push-now-btn"
                            title="Emergency push: send SPRICE - Ship to Alibaba now for every SKU where it differs from the live Price. Skips the once-a-day limit.">
                            <i class="fas fa-paper-plane me-1"></i> Push now
                        </button>
                        <a href="{{ route('alibaba.analytics.export') }}" class="btn btn-sm btn-success" id="ab-export-btn">
                            <i class="fas fa-file-export me-1"></i> Export
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" id="ab-sync-btn">
                            <i class="fas fa-sync"></i> Sync from API
                        </button>
                    </div>

                    <div id="alibaba-analytics-wrap">
                        <div id="alibaba-analytics-table"></div>
                    </div>

                    <div id="ab-loader" class="card-loader-overlay" style="display:none;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="mt-2 fw-semibold text-muted">Loading Alibaba Analytics...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'alibaba'])
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        // S PRC is saved by the server (alibaba:sprc-dil-auto-push --skip-push). The page pushes S PRC − Ship to
        // Alibaba: on the first page open of the day (once a day) or with the Push now button.
        window.CH_STD_SERVER_OWNED = true;
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'alibaba'])
        document.body.style.zoom = "90%";

        const AB_MARGIN = {{ (float) ($marginPercent ?? 95) }} / 100;
        let abTable = null;
        let table = null;
        let abAllRows = [];
        let abBlueAlertFilter = false;

        function isAbParentRow(data) {
            if (!data) return false;
            return !!(data.is_parent_summary || data.is_parent_row);
        }

        /**
         * The S PRC shown, same as /amazon-tabulator-view: the live rule price (Std × (1 − sum of discounts)),
         * the exact amount Push Prc sends. Falls back to the saved S PRC only when the rule gives nothing.
         */
        function abShownSprice(data) {
            if (!data || isAbParentRow(data)) return 0;
            if (typeof chPromoRuleSpriceAmount === 'function') {
                const live = Number(chPromoRuleSpriceAmount(data)) || 0;
                if (live > 0) return live;
            }
            return parseFloat(data.SPRICE != null ? data.SPRICE : data.sprice) || 0;
        }
        window.abShownSprice = abShownSprice;

        /**
         * What is pushed to Alibaba: S PRC − Ship (the SPRICE - Ship column). 0 when there is no S PRC or Ship
         * would take the price to $0.01 or less, so nothing is pushed for that SKU.
         */
        function abPushPrice(data) {
            const sp = abShownSprice(data);
            if (!(sp > 0)) return 0;
            const v = Math.round((sp - (parseFloat(data && data.ship) || 0)) * 100) / 100;
            return v >= 0.01 ? v : 0;
        }
        window.abPushPrice = abPushPrice;
        /** Rules / maps load after first paint: repaint the price cells and counts from the live rule price. */
        window.abAfterRulesRedraw = function () {
            try { if (typeof updateSummary === 'function') updateSummary(); } catch (e) { /* ignore */ }
        };

        /** Sort by the value the cell shows (live S PRC based), blanks last in both directions. */
        function abDisplayedSorter(field) {
            const val = function (d) {
                const sp = abShownSprice(d);
                if (field === 'SPRICE') return sp > 0 ? sp : null;
                const lp = parseFloat(d && d.lp) || 0;
                if (!(sp > 0)) return null;
                if (field === 'sgroi' || field === 'snroi') return lp > 0 ? (((sp * AB_MARGIN) - lp) / lp) * 100 : null;
                return (((sp * AB_MARGIN) - lp) / sp) * 100;
            };
            return function (a, b, aRow, bRow, col, dir) {
                const x = val(aRow.getData() || {}), y = val(bRow.getData() || {});
                if (x === null && y === null) return 0;
                if (x === null) return dir === 'asc' ? 1 : -1;
                if (y === null) return dir === 'asc' ? -1 : 1;
                return x - y;
            };
        }

        function abHasBlueAlert(data) {
            if (!data || isAbParentRow(data)) return false;
            const sku = String(data.sku || '').trim().toUpperCase();
            if (!sku || sku.indexOf('PARENT') === 0) return false;
            if (!(parseFloat(data.INV) > 0)) return false;
            // Blue = the price that gets pushed (S PRC − Ship) differs from the live Alibaba Price.
            const push = abPushPrice(data);
            const price = parseFloat(data.price) || 0;
            return push > 0 && price > 0 && Math.round(push * 100) !== Math.round(price * 100);
        }
        window.abHasBlueTriangle = abHasBlueAlert;
        /** SNGPFT = SGPFT (no ads, no ship): ((S PRC × margin) − LP) / S PRC. */
        window.abApplyPushPatch = function (sku, patch) {
            const key = String(sku || '').trim().toUpperCase();
            if (!key || !patch) return;
            // Alibaba reviews a price change: Price stays at the live value until a pull / sync reads the new one.
            patch = Object.assign({}, patch);
            delete patch.price;
            delete patch.sku_price;
            (abAllRows || []).forEach(function (row) {
                if (String(row.sku || '').trim().toUpperCase() !== key) return;
                Object.assign(row, patch);
                if (patch.price != null && patch.price !== '') {
                    const price = parseFloat(patch.price) || 0;
                    const lp = parseFloat(row.lp) || 0;
                    const margin = parseFloat(row._margin) || AB_MARGIN;
                    const profit = (price * margin) - lp;
                    row.profit = profit;
                    row.sku_price = price;
                    if (lp > 0) row.groi = (profit / lp) * 100;
                    if (price > 0) row.gpft = (profit / price) * 100;
                }
            });
            if (typeof updateSummary === 'function') {
                updateSummary((abAllRows || []).filter(abRowMatches));
            }
        };

        function abSyncBlueAlertBadge() {
            const el = document.getElementById('ab-blue-alert-badge');
            if (!el) return;
            el.style.outline = abBlueAlertFilter ? '3px solid #ffc107' : '';
            el.style.outlineOffset = abBlueAlertFilter ? '2px' : '';
        }

        function abDilValue(data) {
            const inv = parseFloat(data.INV) || 0;
            const ovL30 = parseFloat(data.L30 != null ? data.L30 : data.ov_l30) || 0;
            if (inv === 0) return 0;
            return (ovL30 / inv) * 100;
        }

        function abPct(value, kind) {
            const p = parseFloat(value);
            if (!isFinite(p)) return '<span style="color:#6c757d;">–</span>';
            let color = '#e83e8c';
            if (kind === 'roi') {
                color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
            } else {
                color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
            }
            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(p) + '%</span>';
        }

        function abMoney(value) {
            const n = parseFloat(value);
            if (!isFinite(n)) return '–';
            return '$' + n.toFixed(2);
        }

        function abRowMatches(data) {
            if (abBlueAlertFilter) return abHasBlueAlert(data);
            const view = document.getElementById('ab-row-type').value;
            const parent = isAbParentRow(data);
            if (view === 'sku' && parent) return false;
            if (view === 'row' && !parent) return false;
            const inv = parseFloat(data.INV) || 0;
            const q = (document.getElementById('ab-search').value || '').trim().toLowerCase();
            if (parent) {
                const inventory = document.getElementById('ab-inventory-filter').value;
                if (inventory === 'zero' && inv !== 0) return false;
                if (inventory === 'more' && !(inv > 0)) return false;
                if (q && (String(data.sku || '') + ' ' + String(data.parent || '')).toLowerCase().indexOf(q) === -1) return false;
                return true;
            }
            const inventory = document.getElementById('ab-inventory-filter').value;
            if (inventory === 'zero' && inv !== 0) return false;
            if (inventory === 'more' && !(inv > 0)) return false;
            const sold = parseInt(data.al30, 10) || 0;
            const soldF = document.getElementById('ab-sold-filter').value;
            if (soldF === 'zero' && !(inv > 0 && sold === 0)) return false;
            if (soldF === 'more' && !(inv > 0 && sold > 0)) return false;
            const gpft = parseFloat(data.gpft) || 0;
            const gpftF = document.getElementById('ab-gpft-filter').value;
            if (gpftF === 'negative' && !(gpft < 0)) return false;
            if (gpftF === '0-10' && !(gpft >= 0 && gpft < 10)) return false;
            if (gpftF === '10-20' && !(gpft >= 10 && gpft < 20)) return false;
            if (gpftF === '20-30' && !(gpft >= 20 && gpft < 30)) return false;
            if (gpftF === '30-40' && !(gpft >= 30 && gpft < 40)) return false;
            if (gpftF === '40-50' && !(gpft >= 40 && gpft < 50)) return false;
            if (gpftF === '50plus' && !(gpft >= 50)) return false;
            const cvr = parseFloat(data.cvr) || 0;
            const cvrF = document.getElementById('ab-cvr-filter').value;
            if (cvrF === '0-0' && cvr !== 0) return false;
            if (cvrF === '0-3' && !(cvr > 0 && cvr < 3)) return false;
            if (cvrF === '3-7' && !(cvr >= 3 && cvr < 7)) return false;
            if (cvrF === '7-13' && !(cvr >= 7 && cvr < 13)) return false;
            if (cvrF === '13plus' && !(cvr >= 13)) return false;
            const roi = parseFloat(data.groi) || 0;
            const roiF = document.getElementById('ab-roi-filter').value;
            if (roiF === 'lt40' && !(roi < 40)) return false;
            if (roiF === '40-75' && !(roi >= 40 && roi < 75)) return false;
            if (roiF === '75-125' && !(roi >= 75 && roi < 125)) return false;
            if (roiF === 'gt125' && !(roi >= 125)) return false;
            const dil = abDilValue(data);
            const dilF = document.getElementById('ab-dil-filter').value;
            if (dilF === 'red' && !(dil < 25)) return false;
            if (dilF === 'green' && !(dil >= 25 && dil < 50)) return false;
            if (dilF === 'pink' && !(dil >= 50)) return false;
            const status = document.getElementById('ab-status-filter').value;
            if (status !== 'all' && String(data.status || '').toLowerCase() !== status.toLowerCase()) return false;
            if (q) {
                const hay = (String(data.Parent || '') + ' ' + String(data.sku || '') + ' ' + String(data.product_id || '')).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        }

        function updateSummary(rows) {
            if (!rows) rows = (abAllRows || []).filter(abRowMatches);
            let totalSales = 0, totalProfit = 0, totalCogs = 0, zeroSold = 0, moreSold = 0, visible = 0;
            (rows || []).forEach(function (row) {
                if (isAbParentRow(row)) return;
                visible++;
                const qty = parseInt(row.al30, 10) || 0;
                const sales = parseFloat(row.sales) || 0;
                const lp = parseFloat(row.lp) || 0;
                const margin = parseFloat(row._margin) || AB_MARGIN;
                if (sales > 0 && qty > 0) {
                    totalSales += sales;
                    totalProfit += (sales * margin) - (lp * qty);
                    totalCogs += lp * qty;
                }
                const inv = parseFloat(row.INV) || 0;
                if (inv <= 0) return;
                if (qty === 0) zeroSold++; else moreSold++;
            });
            const gpft = totalSales > 0 ? Math.round((totalProfit / totalSales) * 100) : 0;
            const groi = totalCogs > 0 ? Math.round((totalProfit / totalCogs) * 100) : 0;
            document.getElementById('ab-rows-badge').textContent = 'Row: ' + visible.toLocaleString();
            document.getElementById('ab-sales-badge').textContent = 'Sales: $' + Math.round(totalSales).toLocaleString();
            document.getElementById('ab-pft-badge').textContent = 'PFT: $' + Math.round(totalProfit).toLocaleString();
            document.getElementById('ab-gpft-badge').textContent = 'GPFT: ' + gpft + '%';
            document.getElementById('ab-groi-badge').textContent = 'GROI: ' + groi + '%';
            document.getElementById('ab-more-sold').textContent = moreSold.toLocaleString();
            document.getElementById('ab-zero-sold').textContent = zeroSold.toLocaleString();
            let blueAlert = 0;
            (abAllRows || []).forEach(function (row) {
                if (abHasBlueAlert(row)) blueAlert++;
            });
            const blueBadge = document.getElementById('ab-blue-alert-badge');
            if (blueBadge) {
                blueBadge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + blueAlert.toLocaleString();
            }
            abSyncBlueAlertBadge();
            document.querySelectorAll('.ab-filter-badge').forEach(function (el) { el.classList.remove('active-filter'); });
            const soldF = document.getElementById('ab-sold-filter').value;
            if (soldF === 'more') document.getElementById('ab-sold-badge').classList.add('active-filter');
            if (soldF === 'zero') document.getElementById('ab-zero-badge').classList.add('active-filter');
            if (window.AnalyticsDilBadge) {
                var __dilRows = (typeof abAllRows !== 'undefined' && Array.isArray(abAllRows) && abAllRows.length) ? abAllRows : (rows || []);
                AnalyticsDilBadge.paintFromRows(__dilRows);
            }
        }

        // Row selection for bulk Push Prc (same idea as the Amazon page). The shared promo script reads
        // `selectedSkus` and the checked .sku-select-checkbox boxes to find the selected rows.
        const AB_SELECT_FIELD = '_ab_select';
        let selectedSkus = new Set();

        function abEsc(v) {
            return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/'/g, '&#39;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        /** Child rows on the current pagination page (selections on other pages are kept). */
        function abCurrentPageChildRows() {
            if (!abTable) return [];
            const active = (abTable.getRows('active') || []).filter(function (r) { return !isAbParentRow(r.getData() || {}); });
            let size = typeof abTable.getPageSize === 'function' ? abTable.getPageSize() : 0;
            const page = (typeof abTable.getPage === 'function' ? abTable.getPage() : 1) || 1;
            size = Number(size) || 0;
            if (!size || size >= active.length) return active;
            return active.slice((page - 1) * size, (page - 1) * size + size);
        }

        function abUpdateSelectionUi() {
            const n = selectedSkus.size;
            const countEl = document.getElementById('ab-selected-count');
            if (countEl) countEl.textContent = n + ' selected';
            const clearBtn = document.getElementById('ab-clear-selection-btn');
            if (clearBtn) clearBtn.style.display = n > 0 ? '' : 'none';
            const pushBtn = document.getElementById('ab-bulk-push-btn');
            if (pushBtn) pushBtn.disabled = n === 0;
            const all = document.getElementById('ab-select-all');
            if (!all) return;
            const rows = abCurrentPageChildRows();
            let sel = 0;
            rows.forEach(function (r) { if (selectedSkus.has(String((r.getData() || {}).sku || ''))) sel++; });
            all.checked = rows.length > 0 && sel === rows.length;
            all.indeterminate = sel > 0 && sel < rows.length;
        }

        function abSyncRowCheckboxes() {
            document.querySelectorAll('#alibaba-analytics-table .sku-select-checkbox').forEach(function (cb) {
                cb.checked = selectedSkus.has(cb.getAttribute('data-sku') || '');
            });
            abUpdateSelectionUi();
        }

        const AB_COL_GROUPS = ['basic', 'price', 'discounts', 'other'];
        const AB_COL_LABELS = { basic: 'Basic', price: 'Price', discounts: 'Discounts', other: 'Other' };

        function abColumnShown(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        }

        function abClassifyColumn(field) {
            // Every discount column goes under one Discounts header.
            if (/^(age|dil|lmp|moq_m|buss|pricing|roi|zero_sold|cvr|review|sum)_discount(_amt)?$|^t_discounts$/i.test(field)) return 'discounts';
            if (/^(price|groi|gpft|profit|sales|sprice|sgpft|sgroi|sroi|snroi|sngpft|snpft|sprc_dil|push_prc)$/i.test(field)) return 'price';
            if (/^(product_id|status)$/i.test(field)) return 'other';
            return 'basic';
        }

        function abSyncGroupHeader(groupEl) {
            if (!groupEl) return;
            const headerCb = groupEl.querySelector('.col-vis-group-toggle');
            const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
            if (!headerCb || !itemCbs.length) return;
            let checked = 0;
            itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }

        function abBuildColumnMenu() {
            const menu = document.getElementById('ab-column-dropdown-menu');
            if (!menu || !abTable) return;
            menu.innerHTML = '';

            const showAllLi = document.createElement('li');
            showAllLi.className = 'dropdown-item col-vis-full';
            showAllLi.innerHTML = '<a class="fw-bold" href="#" data-ab-col-vis="show-all" style="text-decoration:none;">Show All Columns</a>';
            menu.appendChild(showAllLi);

            const showDefaultLi = document.createElement('li');
            showDefaultLi.className = 'dropdown-item col-vis-full';
            showDefaultLi.innerHTML = '<a class="fw-bold" href="#" data-ab-col-vis="show-default" style="text-decoration:none;">Show Default Columns</a>';
            menu.appendChild(showDefaultLi);

            const divider = document.createElement('li');
            divider.className = 'col-vis-full';
            divider.innerHTML = '<hr class="dropdown-divider">';
            menu.appendChild(divider);

            const groupsLi = document.createElement('li');
            groupsLi.className = 'col-vis-full';
            const groupsWrap = document.createElement('div');
            groupsWrap.className = 'col-vis-groups';
            const lists = {};
            const groupEls = {};

            AB_COL_GROUPS.forEach(function (cat) {
                const group = document.createElement('div');
                group.className = 'col-vis-group';
                group.dataset.category = cat;
                const titleEl = document.createElement('label');
                titleEl.className = 'col-vis-group-title';
                const groupCb = document.createElement('input');
                groupCb.type = 'checkbox';
                groupCb.className = 'col-vis-group-toggle';
                groupCb.dataset.group = cat;
                groupCb.title = 'Select / deselect all in ' + AB_COL_LABELS[cat];
                titleEl.appendChild(groupCb);
                titleEl.appendChild(document.createTextNode(AB_COL_LABELS[cat]));
                group.appendChild(titleEl);
                const list = document.createElement('ul');
                list.className = 'col-vis-group-list';
                group.appendChild(list);
                groupsWrap.appendChild(group);
                lists[cat] = list;
                groupEls[cat] = group;
            });

            abTable.getColumns().forEach(function (col) {
                const field = col.getField();
                const title = col.getDefinition().title;
                if (!field || !title || field === AB_SELECT_FIELD) return;
                const cat = abClassifyColumn(field);
                const li = document.createElement('li');
                li.className = 'col-vis-item';
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'col-vis-field-toggle';
                checkbox.setAttribute('data-field', field);
                checkbox.checked = col.isVisible();
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + String(title).replace(/<[^>]*>/g, '').trim()));
                li.appendChild(label);
                lists[cat].appendChild(li);
            });

            AB_COL_GROUPS.forEach(function (cat) { abSyncGroupHeader(groupEls[cat]); });
            groupsLi.appendChild(groupsWrap);
            menu.appendChild(groupsLi);
        }

        function abSaveColumnVisibility() {
            if (!abTable) return;
            const visibility = {};
            abTable.getColumns().forEach(function (col) {
                const field = col.getField();
                if (field && field !== AB_SELECT_FIELD) visibility[field] = col.isVisible();
            });
            fetch("{{ route('alibaba.analytics.column.set') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function abApplyColumnVisibility() {
            fetch("{{ route('alibaba.analytics.column.get') }}", { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (visibility) {
                    if (!abTable || !visibility || !Object.keys(visibility).length) return;
                    Object.keys(visibility).forEach(function (field) {
                        if (field === AB_SELECT_FIELD) return;
                        const col = abTable.getColumn(field);
                        if (!col) return;
                        abColumnShown(visibility[field]) ? col.show() : col.hide();
                    });
                })
                .finally(function () { abBuildColumnMenu(); });
        }

        document.getElementById('ab-column-dropdown-menu').addEventListener('change', function (e) {
            if (!abTable) return;
            if (e.target.classList.contains('col-vis-group-toggle')) {
                const checked = e.target.checked;
                const groupEl = e.target.closest('.col-vis-group');
                if (!groupEl) return;
                groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = checked;
                    const col = abTable.getColumn(cb.getAttribute('data-field'));
                    if (col) checked ? col.show() : col.hide();
                });
                e.target.indeterminate = false;
                abSaveColumnVisibility();
                return;
            }
            if (!e.target.classList.contains('col-vis-field-toggle')) return;
            const col = abTable.getColumn(e.target.getAttribute('data-field'));
            if (!col) return;
            e.target.checked ? col.show() : col.hide();
            abSyncGroupHeader(e.target.closest('.col-vis-group'));
            abSaveColumnVisibility();
        });
        document.getElementById('ab-column-dropdown-menu').addEventListener('click', function (e) {
            const actionEl = e.target.closest('[data-ab-col-vis]');
            if (!actionEl || !abTable) return;
            e.preventDefault();
            const action = actionEl.getAttribute('data-ab-col-vis');
            abTable.getColumns().forEach(function (col) {
                if (action === 'show-all') {
                    col.show();
                    return;
                }
                const designedOn = col.getDefinition().visible !== false;
                designedOn ? col.show() : col.hide();
            });
            abBuildColumnMenu();
            abSaveColumnVisibility();
        });

        function applyFilters() {
            if (!abTable) return;
            const filtered = (abAllRows || []).filter(abRowMatches);
            abTable.setData(filtered).then(function () { updateSummary(filtered); });
        }

        function loadTable() {
            const loader = document.getElementById('ab-loader');
            loader.style.display = 'flex';
            fetch("{{ route('alibaba.analytics.data') }}", {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(json => {
                abAllRows = json.data || [];
                window.allTableData = abAllRows;
                // New rows (Sync from API): queue the blue SKUs again once they are on the page.
                window._abReloadPushQueued = false;
                if (abTable) {
                    applyFilters();
                    if (typeof abQueuePushOnReload === 'function') abQueuePushOnReload();
                    return;
                }
                abTable = new Tabulator('#alibaba-analytics-table', {
                    data: (abAllRows || []).filter(abRowMatches),
                    layout: 'fitDataStretch',
                    height: '68vh',
                    placeholder: 'No Alibaba API products yet. Use Sync from API.',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 250],
                    initialSort: [{ column: 'al30', dir: 'desc' }],
                    rowFormatter: function (row) {
                        if (isAbParentRow(row.getData())) {
                            row.getElement().style.backgroundColor = '#fffef2';
                        }
                    },
                    columns: [
                        {
                            title: "<input type='checkbox' id='ab-select-all' title='Select / clear all SKUs on this page' style='width:16px;height:16px;cursor:pointer;'>",
                            field: AB_SELECT_FIELD, hozAlign: 'center', headerSort: false, headerHozAlign: 'center',
                            width: 40, frozen: true,
                            formatter: function (cell) {
                                const d = cell.getRow().getData() || {};
                                if (isAbParentRow(d) || !d.sku) return '';
                                const sku = String(d.sku);
                                return "<input type='checkbox' class='sku-select-checkbox' data-sku='" + abEsc(sku) + "' style='width:16px;height:16px;cursor:pointer;'"
                                    + (selectedSkus.has(sku) ? ' checked' : '') + '>';
                            },
                            cellClick: function (e) { e.stopPropagation(); }
                        },
                        {
                            title: 'Parent', field: 'Parent', hozAlign: 'left', width: 110, frozen: true,
                            formatter: function (cell) {
                                const v = cell.getValue() || '';
                                if (!v || isAbParentRow(cell.getRow().getData())) return '';
                                return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + v + '</span>';
                            }
                        },
                        {
                            title: 'Image', field: 'image_path', headerSort: false, width: 60, hozAlign: 'center',
                            formatter: function (cell) {
                                const v = cell.getValue();
                                if (isAbParentRow(cell.getRow().getData()) || !v) return '';
                                return '<img src="' + v + '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;">';
                            }
                        },
                        { title: 'SKU', field: 'sku', hozAlign: 'left', minWidth: 160, frozen: true, cssClass: 'fw-bold' },
                        {
                            title: 'Link', field: 'product_url', headerSort: false, hozAlign: 'center', width: 64,
                            headerTooltip: 'Open the Alibaba product page',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '';
                                const url = cell.getValue();
                                if (!url) return '<span style="color:#6c757d;">–</span>';
                                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">Link</a>';
                            }
                        },
                        { title: 'INV', field: 'INV', hozAlign: 'center', width: 55, sorter: 'number' },
                        { title: 'AL INV', field: 'soh', hozAlign: 'center', width: 55, sorter: 'number', headerTooltip: 'Alibaba inventory' },
                        { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 60, sorter: 'number' },
                        {
                            title: 'Dil', field: 'dil_percent', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'Dil = OV L30 ÷ INV × 100',
                            formatter: function (cell) {
                                const rowData = cell.getRow().getData();
                                const dil = abDilValue(rowData);
                                if ((parseFloat(rowData.INV) || 0) === 0) return '<span style="color:#6c757d;">0%</span>';
                                let color = '#e83e8c';
                                if (dil < 25) color = '#dc3545';
                                else if (dil < 50) color = '#28a745';
                                return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                            }
                        },
                        {
                            title: 'AB L30', field: 'al30', hozAlign: 'center', width: 60, sorter: 'number',
                            headerTooltip: 'Units from /alibaba/daily-sales, last 30 Pacific days'
                        },
                        {
                            title: 'STD Price', field: 'STANDARD_PRICE', hozAlign: 'center', width: 85, sorter: 'number',
                            headerTooltip: 'Amazon STANDARD_PRICE for this exact SKU (same as /doba-tabulator STD Price).',
                            formatter: function (cell) {
                                const v = parseFloat(cell.getValue()) || 0;
                                return v > 0 ? '$' + v.toFixed(2) : '';
                            }
                        },
                        {
                            title: 'Price', field: 'price', hozAlign: 'center', width: 80, sorter: 'number',
                            headerTooltip: 'Alibaba API SKU price',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                if (row._price_pulling) {
                                    return '<i class="fas fa-spinner fa-spin" style="color:#0d6efd;" title="Pulling live price"></i>';
                                }
                                const v = parseFloat(cell.getValue()) || 0;
                                if (row.not_listed) return '<span style="color:#6c757d;" title="In product master, not listed on Alibaba">–</span>';
                                if (v === 0) return '<span style="color:#a00211;font-weight:600;">$0.00</span>';
                                return '$' + v.toFixed(2);
                            }
                        },
                        {
                            title: 'LP', field: 'lp', hozAlign: 'center', width: 62, sorter: 'number',
                            headerTooltip: 'Landed price (LP) from product master',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                const v = parseFloat(cell.getValue()) || 0;
                                return v > 0 ? '$' + v.toFixed(2) : '<span style="color:#6c757d;">–</span>';
                            }
                        },
                        {
                            title: 'GPFT', field: 'gpft', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'GPFT = ((Price × margin) − LP) ÷ Price. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                return abPct(cell.getValue(), 'gpft');
                            }
                        },
                        {
                            title: 'GROI', field: 'groi', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'GROI = ((Price × margin) − LP) ÷ LP. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                return abPct(cell.getValue(), 'roi');
                            }
                        },
                        {
                            title: 'S PRC', field: 'SPRICE', hozAlign: 'center', width: 78, sorter: abDisplayedSorter('SPRICE'),
                            headerTooltip: 'S PRC = Amazon Std × (1 − (Age + Dil + B + 0 Sold + CVR + Rev Disc)/100). Std Prc is the maximum, no LMP. Shows the live rule price. What is pushed to Alibaba is S PRC − Ship (see the SPRICE - Ship column).',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const value = abShownSprice(row);
                                if (!(value > 0)) return '<span style="color:#6c757d;">–</span>';
                                return '<span style="white-space:nowrap;font-weight:600;">$' + value.toFixed(2) + '</span>';
                            }
                        },
                        {
                            title: 'Ship', field: 'ship', hozAlign: 'center', width: 62, sorter: 'number',
                            headerTooltip: 'Ship cost from product master. Shown for reference; Alibaba GPFT / GROI do not subtract it.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                const v = parseFloat(cell.getValue()) || 0;
                                return v > 0 ? '$' + v.toFixed(2) : '<span style="color:#6c757d;">–</span>';
                            }
                        },
                        {
                            title: 'SPRICE - Ship', field: 'sprice_ship', hozAlign: 'center', width: 90,
                            headerTooltip: 'SPRICE - Ship = S PRC minus Ship cost. This is the price pushed to Alibaba: automatically on the first page open of the day, or any time with the Push now button. Blue triangle = this price ≠ the live Alibaba Price.',
                            sorter: function (a, b, aRow, bRow, col, dir) {
                                const val = function (d) {
                                    const p = abPushPrice(d);
                                    return p > 0 ? p : null;
                                };
                                const x = val(aRow.getData() || {}), y = val(bRow.getData() || {});
                                if (x === null && y === null) return 0;
                                if (x === null) return dir === 'asc' ? 1 : -1;
                                if (y === null) return dir === 'asc' ? -1 : 1;
                                return x - y;
                            },
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const v = abPushPrice(row);
                                if (!(v > 0)) {
                                    const sp = abShownSprice(row);
                                    return sp > 0
                                        ? '<span style="color:#dc3545;" title="Ship is not below S PRC, so nothing is pushed">–</span>'
                                        : '<span style="color:#6c757d;">–</span>';
                                }
                                const live = parseFloat(row.price) || 0;
                                const tri = abHasBlueAlert(row)
                                    ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="Push price $' + v.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                    : '';
                                return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                                    + '<span style="font-weight:600;">$' + v.toFixed(2) + '</span>'
                                    + tri + '</span>';
                            }
                        },
                        {
                            title: 'SGROI', field: 'sgroi', hozAlign: 'center', width: 55, sorter: abDisplayedSorter('sgroi'),
                            headerTooltip: 'SGROI = ((S PRC × margin) − LP) ÷ LP. Ship is not subtracted.',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const sprice = abShownSprice(row);
                                const lp = parseFloat(row.lp) || 0;
                                if (!(sprice > 0) || !(lp > 0)) return '<span style="color:#6c757d;">–</span>';
                                const profit = (sprice * AB_MARGIN) - lp;
                                return abPct((profit / lp) * 100, 'roi');
                            }
                        },
                        {
                            title: 'SGPFT', field: 'sgpft', hozAlign: 'center', width: 55, sorter: abDisplayedSorter('sgpft'),
                            headerTooltip: 'SGPFT = ((S PRC × margin) − LP) ÷ S PRC. Ship is not subtracted.',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const sprice = abShownSprice(row);
                                if (!(sprice > 0)) return '<span style="color:#6c757d;">–</span>';
                                const lp = parseFloat(row.lp) || 0;
                                const profit = (sprice * AB_MARGIN) - lp;
                                return abPct((profit / sprice) * 100, 'gpft');
                            }
                        },
                        (function () {
                            if (typeof channelPromoPushPrcColumn !== 'function') return { title: 'Push Prc', field: 'push_prc', visible: false };
                            const col = channelPromoPushPrcColumn();
                            // No delete (X) button here, but the header still bulk-pushes the checked SKUs like Amazon.
                            col.titleFormatter = function () {
                                return '<button type="button" class="btn btn-sm p-0 ab-push-prc-header-btn" title="Bulk Push S PRC for the checked SKUs" '
                                    + 'style="border:none;background:none;cursor:pointer;color:#000;font-weight:700;font-size:11px;line-height:1.15;">Push Prc</button>';
                            };
                            col.headerClick = function (e) {
                                if (!e.target.closest('.ab-push-prc-header-btn')) return;
                                e.stopPropagation();
                                e.preventDefault();
                                if (typeof bulkPushChannelPrcSelected === 'function') bulkPushChannelPrcSelected();
                                return false;
                            };
                            col.width = 96;
                            col.headerTooltip = 'Push S PRC to the Alibaba listing. Check SKUs, then click this header to push them. Upload = not on the listing yet. Green check = Price already matches S PRC.';
                            return col;
                        })(),
                        {
                            title: 'Profit', field: 'profit', hozAlign: 'center', width: 70, sorter: 'number',
                            headerTooltip: 'Unit profit = (Price × margin) − LP. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                const v = parseFloat(cell.getValue()) || 0;
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return '<span style="color:' + color + ';font-weight:600;">$' + v.toFixed(2) + '</span>';
                            }
                        },
                        {
                            title: 'Sales', field: 'sales', hozAlign: 'center', width: 80, sorter: 'number',
                            headerTooltip: 'Line sales from /alibaba/daily-sales (unit price × qty), last 30 Pacific days',
                            formatter: function (cell) { return abMoney(cell.getValue()); }
                        },
                        {
                            title: 'SNROI', field: 'snroi', hozAlign: 'center', width: 55, sorter: abDisplayedSorter('snroi'),
                            headerTooltip: 'Alibaba has no ads. SNROI = SGROI.',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const sprice = abShownSprice(row);
                                const lp = parseFloat(row.lp) || 0;
                                if (!(sprice > 0) || !(lp > 0)) return '<span style="color:#6c757d;">–</span>';
                                const profit = (sprice * AB_MARGIN) - lp;
                                return abPct((profit / lp) * 100, 'roi');
                            }
                        },
                        {
                            title: 'SNGPFT', field: 'sngpft', hozAlign: 'center', width: 60, sorter: abDisplayedSorter('sngpft'),
                            headerTooltip: 'Alibaba has no ads. SNGPFT = SGPFT.',
                            formatter: function (cell) {
                                const row = cell.getRow().getData();
                                if (isAbParentRow(row)) return '<span style="color:#6c757d;">–</span>';
                                const sprice = abShownSprice(row);
                                if (!(sprice > 0)) return '<span style="color:#6c757d;">–</span>';
                                const lp = parseFloat(row.lp) || 0;
                                const profit = (sprice * AB_MARGIN) - lp;
                                return abPct((profit / sprice) * 100, 'gpft');
                            }
                        },
                        { title: 'Product Id', field: 'product_id', hozAlign: 'left', minWidth: 130, visible: false },
                        { title: 'Status', field: 'status', hozAlign: 'center', width: 80, visible: false },
                    ],
                });
                table = abTable;
                window.table = abTable;
                abTable.on('renderComplete', function () {
                    abSyncRowCheckboxes();
                });
                abTable.on('pageLoaded', abUpdateSelectionUi);
                abApplyColumnVisibility();
                updateSummary((abAllRows || []).filter(abRowMatches));
            })
            .finally(() => { loader.style.display = 'none'; });
        }

        function abApplyFiltersClearingBlue() {
            abBlueAlertFilter = false;
            applyFilters();
        }
        ['ab-row-type', 'ab-inventory-filter', 'ab-sold-filter', 'ab-gpft-filter', 'ab-cvr-filter', 'ab-roi-filter', 'ab-dil-filter', 'ab-status-filter'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', abApplyFiltersClearingBlue);
        });
        document.getElementById('ab-search').addEventListener('input', abApplyFiltersClearingBlue);
        document.getElementById('ab-sold-badge').addEventListener('click', function () {
            abBlueAlertFilter = false;
            document.getElementById('ab-sold-filter').value = document.getElementById('ab-sold-filter').value === 'more' ? 'all' : 'more';
            applyFilters();
        });
        document.getElementById('ab-zero-badge').addEventListener('click', function () {
            abBlueAlertFilter = false;
            document.getElementById('ab-sold-filter').value = document.getElementById('ab-sold-filter').value === 'zero' ? 'all' : 'zero';
            applyFilters();
        });
        document.getElementById('ab-blue-alert-badge').addEventListener('click', function () {
            abBlueAlertFilter = !abBlueAlertFilter;
            applyFilters();
        });
        document.getElementById('ab-sync-btn').addEventListener('click', function () {
            const btn = this;
            const loader = document.getElementById('ab-loader');
            const label = loader.querySelector('.fw-semibold');
            btn.disabled = true;
            loader.style.display = 'flex';

            function syncPage(page, reset) {
                if (label) label.textContent = 'Syncing Alibaba API, page ' + page + '...';
                const body = new URLSearchParams();
                body.set('page', String(page));
                body.set('reset', reset ? '1' : '0');
                return fetch("{{ route('alibaba.analytics.sync') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body.toString()
                })
                .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                .then(({ ok, body }) => {
                    if (!ok || !body.success) {
                        throw new Error(body.message || 'Sync failed.');
                    }
                    if (!body.done) {
                        return syncPage(page + 1, false);
                    }
                    if (label) label.textContent = body.message || 'Sync finished.';
                    loadTable();
                });
            }

            syncPage(1, true)
                .catch(function (err) {
                    alert(err.message || 'Sync failed.');
                })
                .finally(function () {
                    btn.disabled = false;
                    loader.style.display = 'none';
                });
        });

        // Header checkbox: select / clear every SKU on the current page (other pages keep their selection).
        document.addEventListener('change', function (e) {
            const t = e.target;
            if (!t) return;
            if (t.id === 'ab-select-all') {
                abCurrentPageChildRows().forEach(function (r) {
                    const sku = String((r.getData() || {}).sku || '');
                    if (!sku) return;
                    if (t.checked) selectedSkus.add(sku); else selectedSkus.delete(sku);
                });
                abSyncRowCheckboxes();
                return;
            }
            if (t.classList && t.classList.contains('sku-select-checkbox')) {
                const sku = t.getAttribute('data-sku') || '';
                if (!sku) return;
                if (t.checked) selectedSkus.add(sku); else selectedSkus.delete(sku);
                abUpdateSelectionUi();
            }
        });
        document.getElementById('ab-clear-selection-btn').addEventListener('click', function () {
            selectedSkus.clear();
            abSyncRowCheckboxes();
        });
        document.getElementById('ab-bulk-push-btn').addEventListener('click', function () {
            if (typeof bulkPushChannelPrcSelected === 'function') bulkPushChannelPrcSelected();
        });
        abUpdateSelectionUi();

        // Emergency push: every SKU whose SPRICE - Ship differs from the live Price, now. Ignores the once-a-day limit.
        document.getElementById('ab-push-now-btn').addEventListener('click', function () {
            const btn = this;
            if (btn.disabled) return;
            if (typeof window.scanAndQueueChannelPushSprice !== 'function' || !abTable) {
                alert('Table is still loading. Try again in a moment.');
                return;
            }
            const n = (abAllRows || []).filter(abHasBlueAlert).length;
            if (!n) {
                alert('Nothing to push: every SKU already has SPRICE - Ship as its Alibaba Price.');
                return;
            }
            if (!confirm('Push SPRICE - Ship to Alibaba now for ' + n + ' SKU(s)?')) return;
            const startedAt = Date.now();
            const MAX_MS = 10 * 60 * 1000;
            if (typeof window.chPromoAllowManualPush === 'function') window.chPromoAllowManualPush(MAX_MS);
            window._chPushSpriceLeftoverPasses = 0;
            btn.disabled = true;
            window.scanAndQueueChannelPushSprice(abTable, { catalog: true, once: false, silent: false });
            // The push window stays open while the progress bar is busy, then closes again.
            const timer = setInterval(function () {
                const busy = $('#ch-promo-reload-push-progress').hasClass('is-busy');
                if ((!busy && Date.now() - startedAt > 15000) || Date.now() - startedAt > MAX_MS) {
                    clearInterval(timer);
                    if (typeof window.chPromoAllowManualPush === 'function') window.chPromoAllowManualPush(0);
                    btn.disabled = false;
                }
            }, 1000);
        });

        /**
         * Push on reload, same as /amazon-tabulator-view: once the rows and the Std prc vs dil rules are loaded, every
         * blue-triangle SKU (INV > 0, Price ≠ S PRC) is queued for the background push. Nothing else started it
         * on this page, so the switch showed On and no push ever began after a reload.
         */
        window._abRulesSettled = false;
        window._abReloadPushQueued = false;
        function abQueuePushOnReload() {
            if (!window._abRulesSettled || window._abReloadPushQueued) return;
            if (typeof chPromoPageReloadPushAllowed === 'function' && !chPromoPageReloadPushAllowed()) return;
            if (typeof chPromoQueueReloadSpricePush !== 'function') return;
            window._abReloadPushQueued = true;
            // Once a day: the server gives the day's slot to the first page open only. Later opens do not push.
            $.ajax({
                url: '/alibaba-analytics/claim-daily-push',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '', 'Accept': 'application/json' },
                timeout: 15000,
            }).done(function (resp) {
                if (resp && resp.allowed) chPromoQueueReloadSpricePush({ delay: 300 });
            }).fail(function () {
                // Could not reach the server: do not push (better to skip than to risk a second push today).
            });
        }
        window.abQueuePushOnReload = abQueuePushOnReload;
        $(document).off('chStd:settled.abReloadPush').on('chStd:settled.abReloadPush', function () {
            window._abRulesSettled = true;
            abQueuePushOnReload();
        });

        loadTable();
    </script>
@endsection
