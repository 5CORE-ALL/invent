@extends('layouts.vertical', ['title' => 'AliExpress Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
        }

        .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers – identical to TikTok */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        /* ========== DIL DROPDOWN – identical to TikTok ========== */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }

        .dropdown-item:hover {
            color: #1e2125;
            background-color: #e9ecef;
        }

        /* ========== STATUS CIRCLES – identical to TikTok ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default { background-color: #6c757d; }
        .status-circle.red     { background-color: #dc3545; }
        .status-circle.yellow  { background-color: #ffc107; }
        .status-circle.green   { background-color: #28a745; }
        .status-circle.pink    { background-color: #e83e8c; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'AliExpress Pricing',
        'sub_title'  => 'Separate pricing page (sales page unchanged)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- ===== FILTER BAR (mirrors TikTok filter bar) ===== --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">

                        {{-- Inventory filter --}}
                        <select id="inventory-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        {{-- AE Stock filter (mirrors TikTok TT Stock filter) --}}
                        <select id="ae-stock-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">AE Stock</option>
                            <option value="zero">0 AE Stock</option>
                            <option value="more">More than 0</option>
                        </select>

                        {{-- GPFT% filter --}}
                        <select id="gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50-60">50–60%</option>
                            <option value="60plus">60%+</option>
                        </select>

                        {{-- AE L30 filter (mirrors TikTok T L30) --}}
                        <select id="al30-filter" class="form-select form-select-sm" style="width:130px;" title="Excludes 0 inventory items">
                            <option value="all">AE L30</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>

                        {{-- DIL% dropdown (identical to TikTok) --}}
                        <div class="dropdown manual-dropdown-container">
                            <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                                <span class="status-circle default"></span> DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item ae-dil-filter active" href="#" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                                <li><a class="dropdown-item ae-dil-filter" href="#" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.7%)</a></li>
                                <li><a class="dropdown-item ae-dil-filter" href="#" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.7–25%)</a></li>
                                <li><a class="dropdown-item ae-dil-filter" href="#" data-color="green">
                                    <span class="status-circle green"></span> Green (25–50%)</a></li>
                                <li><a class="dropdown-item ae-dil-filter" href="#" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                            </ul>
                        </div>

                        {{-- Column visibility (mirrors TikTok) --}}
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="aeColumnVisDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-eye"></i> Columns
                            </button>
                            <ul class="dropdown-menu" id="ae-col-dropdown-menu"
                                style="max-height:400px; overflow-y:auto;"></ul>
                        </div>
                        <button id="ae-show-all-cols-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-eye"></i> Show All
                        </button>

                        {{-- SKU search --}}
                        <input type="text" id="pricing-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU...">

                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-sm btn-warning"
                            data-bs-toggle="modal" data-bs-target="#uploadPriceSheetModal">
                            <i class="fas fa-upload"></i> Upload Price
                        </button>
                    </div>

                    {{-- ===== SUMMARY BADGES (mirrors TikTok summary section) ===== --}}
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-success fs-6 p-2" id="ae-total-profit-badge"
                                style="color:#fff; font-weight:700; cursor:pointer;" title="Total Profit">Total PFT: $0</span>
                            <span class="badge bg-primary fs-6 p-2" id="ae-total-sales-badge"
                                style="font-weight:700;">Total Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="ae-avg-gpft-badge"
                                style="font-weight:700; color:#111;">AVG GPFT: 0%</span>
                            <span class="badge bg-warning fs-6 p-2" id="ae-total-al30-badge"
                                style="font-weight:700; color:#111;">AE L30: 0</span>
                            <span class="badge bg-secondary fs-6 p-2" id="ae-total-sku-badge"
                                style="font-weight:700;">Total SKU: 0</span>
                            <span class="badge bg-danger fs-6 p-2" id="ae-zero-sold-badge"
                                style="font-weight:700; cursor:pointer;" title="Click to filter 0 sold">0 Sold: 0</span>
                            <span class="badge fs-6 p-2" id="ae-more-sold-badge"
                                style="background:#28a745; color:#fff; font-weight:700; cursor:pointer;" title="Click to filter >0 sold">&gt; 0 Sold: 0</span>
                            <span class="badge bg-warning fs-6 p-2" id="ae-avg-dil-badge"
                                style="font-weight:700; color:#111;">DIL%: 0%</span>
                            <span class="badge bg-info fs-6 p-2" id="ae-total-cogs-badge"
                                style="font-weight:700; color:#111;">COGS: $0</span>
                            <span class="badge bg-secondary fs-6 p-2" id="ae-roi-badge"
                                style="font-weight:700; color:#111;">ROI%: 0%</span>
                            <span class="badge bg-danger fs-6 p-2" id="ae-missing-badge"
                                style="font-weight:700; cursor:pointer;" title="Click to filter Missing">Missing: 0</span>
                            <span class="badge bg-success fs-6 p-2" id="ae-map-badge"
                                style="font-weight:700; cursor:pointer;" title="Click to filter Mapped">Map: 0</span>
                        </div>
                    </div>

                    <div id="aliexpress-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload Modal --}}
    <div class="modal fade" id="uploadPriceSheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload AliExpress Price Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="priceSheetFile"
                        accept=".txt,.tsv,.csv,.xlsx,.xls">
                    <small class="text-muted">
                        Upload the native AliExpress seller-export file (.txt / .tsv)
                        or a plain CSV/Excel with <code>sku</code> and <code>price</code> columns.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="uploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        // ============================================================
        // Same constant/variable naming pattern as TikTok tabulator
        // ============================================================
        const AE_COL_VIS_KEY = "ae_pricing_column_visibility";
        let table = null;
        let summaryDataCache = [];

        // Badge-click filter flags (identical pattern to TikTok)
        let zeroSoldFilterActive  = false;
        let moreSoldFilterActive  = false;
        let missingFilterActive   = false;
        let mapFilterActive       = false;

        // ============================================================
        // Helper: money format – identical to TikTok
        // ============================================================
        function money(value) {
            return `$${(parseFloat(value) || 0).toFixed(2)}`;
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(r => (r && typeof r.getData === "function") ? r.getData() : r || {});
            }
            if (rowsInput && typeof rowsInput === "object") {
                return Object.values(rowsInput).map(r => (r && typeof r.getData === "function") ? r.getData() : r || {});
            }
            return [];
        }

        // ============================================================
        // GPFT color – IDENTICAL to TikTok GPFT% formatter
        // ============================================================
        function gpftColor(pct) {
            if (pct < 10)                  return '#a00211';
            if (pct >= 10 && pct < 15)     return '#ffc107';
            if (pct >= 15 && pct < 20)     return '#3591dc';
            if (pct >= 20 && pct <= 40)    return '#28a745';
            return '#e83e8c';
        }

        // ============================================================
        // ROI color – IDENTICAL to TikTok ROI% formatter
        // ============================================================
        function roiColor(pct) {
            if (pct < 50)                  return '#a00211';
            if (pct >= 50 && pct < 100)    return '#ffc107';
            if (pct >= 100 && pct < 150)   return '#28a745';
            return '#e83e8c';
        }

        // ============================================================
        // DIL color – IDENTICAL to TikTok Dil formatter
        // ============================================================
        function dilColor(dil) {
            if (dil < 16.66)               return '#a00211';
            if (dil >= 16.66 && dil < 25)  return '#ffc107';
            if (dil >= 25 && dil < 50)     return '#28a745';
            return '#e83e8c';
        }

        // ============================================================
        // Summary update – mirrors TikTok updateSummary() exactly
        // ============================================================
        function updateSummary(rowsInput = null) {
            let rows = normalizeRows(rowsInput);
            if (!rows.length && table) {
                const active = normalizeRows(table.getData("active"));
                const all    = normalizeRows(table.getData());
                rows = active.length ? active : all;
            }
            if (!rows.length) rows = normalizeRows(summaryDataCache);

            let totalPft   = 0, totalSales = 0, totalGpft = 0, gpftCount = 0;
            let totalAl30  = 0, totalCogs  = 0;
            let totalRoi   = 0, roiCount   = 0;
            let totalDil   = 0, dilCount   = 0;
            let zeroSold   = 0, moreSold   = 0;
            let missingCnt = 0, mapCnt     = 0;

            rows.forEach(row => {
                const profit = parseFloat(row.profit) || 0;
                const al30   = parseFloat(row.al30)   || 0;
                const price  = parseFloat(row.price)  || 0;
                const lp     = parseFloat(row.lp)     || 0;
                const inv    = parseInt(row.inv, 10)  || 0;
                const ovL30  = parseFloat(row.ov_l30) || 0;

                // TikTok: totalPft += l30 * profit (per-unit profit × sold qty)
                totalPft   += al30 * profit;
                totalSales += parseFloat(row.sales) || 0;

                const gpft = parseFloat(row.gpft);
                if (Number.isFinite(gpft)) { totalGpft += gpft; gpftCount++; }

                // AE L30 total (mirrors TikTok TT L30 total)
                totalAl30 += al30;

                // COGS = LP × AL30  (mirrors TikTok: LP × TT L30)
                totalCogs += lp * al30;

                const roi = parseFloat(row.groi);
                if (Number.isFinite(roi) && roi !== 0) { totalRoi += roi; roiCount++; }

                // DIL uses ov_l30 / inv (same as TikTok: L30(shopify) / INV(shopify))
                if (inv > 0) { totalDil += (ovL30 / inv) * 100; dilCount++; }

                // 0 Sold / >0 Sold (mirrors TikTok using TT L30)
                if (al30 === 0) zeroSold++; else moreSold++;

                if ((row.missing || '').toString().trim().toUpperCase() === 'M') missingCnt++;
                if ((row.map     || '').toString().trim().toUpperCase() === 'MAP') mapCnt++;
            });

            const avgGpft = gpftCount > 0 ? totalGpft / gpftCount : 0;
            const avgDil  = dilCount  > 0 ? totalDil  / dilCount  : 0;
            const avgRoi  = roiCount  > 0 ? totalRoi  / roiCount  : 0;

            $('#ae-total-sku-badge').text(`Total SKU: ${rows.length.toLocaleString()}`);
            $('#ae-total-profit-badge').text(`Total PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#ae-total-sales-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#ae-avg-gpft-badge').text(`AVG GPFT: ${avgGpft.toFixed(1)}%`);
            $('#ae-total-al30-badge').text(`AE L30: ${totalAl30.toLocaleString()}`);
            $('#ae-zero-sold-badge').text(`0 Sold: ${zeroSold.toLocaleString()}`);
            $('#ae-more-sold-badge').text(`> 0 Sold: ${moreSold.toLocaleString()}`);
            $('#ae-avg-dil-badge').text(`DIL%: ${avgDil.toFixed(1)}%`);
            $('#ae-total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#ae-roi-badge').text(`ROI%: ${avgRoi.toFixed(1)}%`);
            $('#ae-missing-badge').text(`Missing: ${missingCnt.toLocaleString()}`);
            $('#ae-map-badge').text(`Map: ${mapCnt.toLocaleString()}`);
        }

        // ============================================================
        // applyFilters – same structure as TikTok applyFilters()
        // ============================================================
        function applyFilters() {
            if (!table) return;

            const skuSearch  = ($('#pricing-sku-search').val() || '').trim().toLowerCase();
            const invFilter  = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const al30Filter = $('#al30-filter').val();
            const dilColor_  = $('.ae-dil-filter.active').data('color') || 'all';

            table.clearFilter();

            // SKU search
            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }

            // Inventory filter (identical to TikTok inventory filter)
            if (invFilter === 'zero') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }

            // AE Stock filter (mirrors TikTok TT Stock filter)
            const aeStockFilter = $('#ae-stock-filter').val();
            if (aeStockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) === 0);
            } else if (aeStockFilter === 'more') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) > 0);
            }

            // GPFT filter (identical to TikTok GPFT filter)
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '60plus')   return gpft >= 60;
                    const [min, max] = gpftFilter.split('-').map(Number);
                    return gpft >= min && gpft < max;
                });
            }

            // AL30 filter (mirrors TikTok T L30 filter; excludes 0-inv rows)
            if (al30Filter !== 'all') {
                table.addFilter(function(d) {
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const al30 = parseFloat(d.al30) || 0;
                    if (al30Filter === '0')      return al30 === 0;
                    if (al30Filter === '0-10')   return al30 > 0 && al30 <= 10;
                    if (al30Filter === '10plus') return al30 > 10;
                    return true;
                });
            }

            // DIL filter using ov_l30/inv (identical to TikTok DIL filter using L30/INV)
            if (dilColor_ !== 'all') {
                table.addFilter(function(d) {
                    const inv   = parseFloat(d.inv)    || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil   = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilColor_ === 'red')    return dil < 16.66;
                    if (dilColor_ === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilColor_ === 'green')  return dil >= 25 && dil < 50;
                    if (dilColor_ === 'pink')   return dil >= 50;
                    return true;
                });
            }

            // Badge filters (identical to TikTok badge filters)
            if (zeroSoldFilterActive) {
                table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            }
            if (moreSoldFilterActive) {
                table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
            }
            if (missingFilterActive) {
                table.addFilter(d => (d.missing || '').trim().toUpperCase() === 'M');
            }
            if (mapFilterActive) {
                table.addFilter(d => (d.map || '').trim().toUpperCase() === 'MAP');
            }
        }

        // ============================================================
        // Column visibility helpers (same pattern as TikTok)
        // ============================================================
        function buildColumnDropdown() {
            const $menu = $('#ae-col-dropdown-menu');
            $menu.empty();
            if (!table) return;
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                if (!field) return;
                const rawTitle = col.getDefinition().title || field;
                const label = rawTitle.replace(/<[^>]*>/g, '');
                const visible = col.isVisible();
                const $li = $(`<li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="#" data-field="${field}">
                        <input type="checkbox" ${visible ? 'checked' : ''}> ${label}
                    </a></li>`);
                $li.find('a').on('click', function(e) {
                    e.preventDefault();
                    const cb = $(this).find('input')[0];
                    cb.checked = !cb.checked;
                    cb.checked ? col.show() : col.hide();
                    saveColVis();
                });
                $menu.append($li);
            });
        }

        function saveColVis() {
            if (!table) return;
            const vis = {};
            table.getColumns().forEach(col => { if (col.getField()) vis[col.getField()] = col.isVisible(); });
            localStorage.setItem(AE_COL_VIS_KEY, JSON.stringify(vis));
        }

        function applyColVis() {
            const vis = JSON.parse(localStorage.getItem(AE_COL_VIS_KEY) || '{}');
            if (!Object.keys(vis).length) return;
            table.getColumns().forEach(col => {
                const f = col.getField();
                if (f && f in vis) { vis[f] ? col.show() : col.hide(); }
            });
        }

        // ============================================================
        // Document Ready
        // ============================================================
        $(document).ready(function() {

            // ============================================================
            // Tabulator – column definitions match TikTok style exactly
            // ============================================================
            table = new Tabulator("#aliexpress-pricing-table", {
                ajaxURL: "/aliexpress/pricing-data",
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    return response;
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200],
                paginationCounter: "rows",
                initialSort: [{ column: "sku", dir: "asc" }],

                columns: [
                    // ---- SKU (frozen, same as TikTok) ----
                    {
                        title: "SKU",
                        field: "sku",
                        minWidth: 220,
                        headerFilter: "input",
                        frozen: true,
                        cssClass: "fw-bold text-primary",
                        sorter: function(a, b) {
                            const av = (a || '').toString().trim().toUpperCase();
                            const bv = (b || '').toString().trim().toUpperCase();
                            const aL = /^[A-Z]/.test(av), bL = /^[A-Z]/.test(bv);
                            if (aL !== bL) return aL ? -1 : 1;
                            return av.localeCompare(bv, undefined, { numeric: true, sensitivity: "base" });
                        }
                    },

                    // ---- INV (same as TikTok INV) ----
                    {
                        title: "INV",
                        field: "inv",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const val = parseInt(cell.getValue(), 10) || 0;
                            // Identical to TikTok TT Stock formatter for zero
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },

                    // ---- AE Stock (from uploaded sheet – mirrors TikTok TT Stock) ----
                    {
                        title: "AE Stock",
                        field: "ae_stock",
                        sorter: "number",
                        hozAlign: "center",
                        width: 65,
                        formatter: function(cell) {
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },

                    // ---- OV L30 (ShopifySku.quantity – same as TikTok's "OV L30" / L30 field) ----
                    {
                        title: "OV L30",
                        field: "ov_l30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const value = parseFloat(raw || 0);
                            // Identical to TikTok TT L30 formatter
                            return `<span style="font-weight:700;">${value}</span>`;
                        }
                    },

                    // ---- Dil (computed from ov_l30/inv – identical to TikTok TT Dil%) ----
                    {
                        title: "Dil",
                        field: "dil_percent",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const inv    = parseFloat(rowData.inv)    || 0;
                            const ovL30  = parseFloat(rowData.ov_l30) || 0;
                            // Identical formula to TikTok: INV=0 → 0%
                            if (inv === 0) return `<span style="color:#6c757d;">0%</span>`;
                            const dil = (ovL30 / inv) * 100;
                            // Identical color thresholds to TikTok Dil formatter
                            return `<span style="color:${dilColor(dil)};font-weight:600;">${Math.round(dil)}%</span>`;
                        }
                    },

                    // ---- AE L30 (actual AliExpress sold – mirrors TikTok's "TT L30") ----
                    {
                        title: "AE L30",
                        field: "al30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            // Identical to TikTok TT L30 bold formatter
                            return `<span style="font-weight:700;">${value}</span>`;
                        }
                    },

                    // ---- Price ----
                    {
                        title: "Price",
                        field: "price",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue() || 0);
                            // Identical to TikTok TT Price formatter
                            if (val === 0) {
                                return `<span style="color:#a00211;font-weight:700;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left:4px;"></i></span>`;
                            }
                            return `<span style="font-weight:700;">${money(val)}</span>`;
                        }
                    },

                    // ---- Missing (identical to TikTok Missing formatter) ----
                    {
                        title: "Missing",
                        field: "missing",
                        hozAlign: "center",
                        width: 70,
                        formatter: function(cell) {
                            const value = (cell.getValue() || '').toString().trim().toUpperCase();
                            if (value === 'M') {
                                return '<span style="color:#dc3545;font-weight:bold;background:#ffe6e6;padding:2px 6px;border-radius:3px;">M</span>';
                            }
                            return '';
                        }
                    },

                    // ---- Map (identical to TikTok MAP formatter) ----
                    {
                        title: "Map",
                        field: "map",
                        hozAlign: "center",
                        width: 90,
                        formatter: function(cell) {
                            const val = (cell.getValue() || '').trim();
                            const row = cell.getRow().getData();
                            const isMissing = (row.missing || '').trim().toUpperCase() === 'M';
                            if (isMissing) return '';
                            // Identical to TikTok MAP formatter
                            if (val === 'Map') {
                                return '<span style="color:#28a745;font-weight:bold;">Map</span>';
                            } else if (val.startsWith('N Map|')) {
                                const diff = val.split('|')[1];
                                return `<span style="color:#dc3545;font-weight:bold;">N Map (${diff})</span>`;
                            } else if (val.startsWith('Diff|')) {
                                const diff = val.split('|')[1];
                                return `<span style="color:#ffc107;font-weight:bold;">${diff}<br>(INV > AE Stock)</span>`;
                            }
                            return '';
                        }
                    },

                    // ---- GPFT% (identical color coding to TikTok GPFT% formatter) ----
                    {
                        title: "GPFT%",
                        field: "gpft",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '') return '';
                            const pct = parseFloat(value);
                            if (isNaN(pct)) return '';
                            return `<span style="color:${gpftColor(pct)};font-weight:700;">${pct.toFixed(0)}%</span>`;
                        }
                    },

                    // ---- ROI% (identical color coding to TikTok ROI% formatter) ----
                    {
                        title: "ROI%",
                        field: "groi",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '') return '';
                            const pct = parseFloat(value);
                            if (isNaN(pct)) return '';
                            return `<span style="color:${roiColor(pct)};font-weight:600;">${pct.toFixed(0)}%</span>`;
                        }
                    },

                    // ---- Profit (visible: false by default – same as TikTok) ----
                    {
                        title: "Profit",
                        field: "profit",
                        sorter: "number",
                        hozAlign: "center",
                        width: 75,
                        visible: false,
                        formatter: function(cell) {
                            const val   = parseFloat(cell.getValue() || 0);
                            const color = val >= 0 ? '#28a745' : '#a00211';
                            // Identical to TikTok Profit formatter
                            return `<span style="color:${color};font-weight:600;">${money(val)}</span>`;
                        }
                    },

                    // ---- Sales (visible: false – same as TikTok Sales L30) ----
                    {
                        title: "Sales",
                        field: "sales",
                        sorter: "number",
                        hozAlign: "center",
                        width: 80,
                        visible: false,
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue() || 0);
                            return `$${val.toFixed(2)}`;
                        }
                    },

                    // ---- LP (visible: false – same as TikTok LP) ----
                    {
                        title: "LP",
                        field: "lp",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        visible: false,
                        formatter: function(cell) {
                            return `$${(parseFloat(cell.getValue() || 0)).toFixed(2)}`;
                        }
                    },

                    // ---- Ship (visible: false – same as TikTok Ship) ----
                    {
                        title: "Ship",
                        field: "ship",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        visible: false,
                        formatter: function(cell) {
                            return `$${(parseFloat(cell.getValue() || 0)).toFixed(2)}`;
                        }
                    },

                    // ---- SPRICE ----
                    {
                        title: "SPRICE",
                        field: "sprice",
                        sorter: "number",
                        hozAlign: "center",
                        width: 80,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            // Identical to TikTok SPRICE formatter
                            return `<span style="font-weight:600;padding:2px 6px;border-radius:3px;">${money(value)}</span>`;
                        }
                    },

                    // ---- SGPFT% (identical color coding to TikTok SGPFT formatter) ----
                    {
                        title: "SGPFT",
                        field: "sgpft",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const pct = parseFloat(value);
                            return `<span style="color:${gpftColor(pct)};font-weight:600;">${pct.toFixed(0)}%</span>`;
                        }
                    },
                ],

                dataLoaded:     function(data) { updateSummary(data); buildColumnDropdown(); applyColVis(); },
                dataFiltered:   function(filters, rows) { updateSummary(rows); },
                dataProcessed:  function() { updateSummary(); },
                renderComplete: function() { updateSummary(); }
            });

            // ============================================================
            // Filter event bindings
            // ============================================================
            $('#pricing-sku-search').on('input',  function() { applyFilters(); });
            $('#inventory-filter').on('change',   function() { applyFilters(); });
            $('#ae-stock-filter').on('change',    function() { applyFilters(); });
            $('#gpft-filter').on('change',        function() { applyFilters(); });
            $('#al30-filter').on('change',        function() { applyFilters(); });

            // DIL dropdown (identical to TikTok manual dropdown logic)
            $(document).on('click', '.manual-dropdown-container .btn', function(e) {
                e.stopPropagation();
                const container = $(this).closest('.manual-dropdown-container');
                $('.manual-dropdown-container').not(container).removeClass('show');
                container.toggleClass('show');
            });

            $(document).on('click', '.ae-dil-filter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.ae-dil-filter').removeClass('active');
                $(this).addClass('active');
                const statusCircle = $(this).find('.status-circle').clone();
                $('#dilFilterDropdown').html('').append(statusCircle).append(' DIL%');
                $(this).closest('.manual-dropdown-container').removeClass('show');
                applyFilters();
            });

            $(document).on('click', function() { $('.manual-dropdown-container').removeClass('show'); });

            // ============================================================
            // Badge click filters (identical to TikTok badge click handlers)
            // ============================================================
            $('#ae-zero-sold-badge').on('click', function() {
                zeroSoldFilterActive = !zeroSoldFilterActive;
                moreSoldFilterActive = false;
                applyFilters();
            });

            $('#ae-more-sold-badge').on('click', function() {
                moreSoldFilterActive = !moreSoldFilterActive;
                zeroSoldFilterActive = false;
                applyFilters();
            });

            $('#ae-missing-badge').on('click', function() {
                missingFilterActive = !missingFilterActive;
                mapFilterActive = false;
                applyFilters();
            });

            $('#ae-map-badge').on('click', function() {
                mapFilterActive = !mapFilterActive;
                missingFilterActive = false;
                applyFilters();
            });

            // ============================================================
            // Column visibility controls
            // ============================================================
            $('#ae-show-all-cols-btn').on('click', function() {
                if (!table) return;
                table.getColumns().forEach(col => { if (col.getField()) col.show(); });
                buildColumnDropdown();
                saveColVis();
            });

            $('#aeColumnVisDropdown').closest('.dropdown').on('show.bs.dropdown', function() {
                buildColumnDropdown();
            });

            // ============================================================
            // Toolbar buttons
            // ============================================================
            $('#refresh-pricing-table').on('click', function() {
                table.setData("/aliexpress/pricing-data");
            });

            $('#export-pricing-btn').on('click', function() {
                table.download("csv", "aliexpress_pricing_data.csv");
            });

            // ============================================================
            // Upload price sheet (unchanged)
            // ============================================================
            $('#uploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('priceSheetFile').files[0];
                if (!file) { alert('Please select a file first.'); return; }

                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $.ajax({
                    url: '/aliexpress/pricing-upload-price',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (window.toastr) toastr.success(response.message || 'Price upload completed.');
                        else alert(response.message || 'Price upload completed.');
                        $('#uploadPriceSheetModal').modal('hide');
                        $('#priceSheetFile').val('');
                        table.setData('/aliexpress/pricing-data');
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.message || 'Price upload failed.';
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    }
                });
            });
        });
    </script>
@endsection
