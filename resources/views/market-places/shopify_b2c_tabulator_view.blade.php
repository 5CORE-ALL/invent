@extends('layouts.vertical', ['title' => 'Shopify B2C - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical column headers */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
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

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* ========== STATUS INDICATORS ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ffc107;
        }

        .status-circle.blue {
            background-color: #3591dc;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        /* ========== DROPDOWN STYLING ========== */
        .shopify-b2c-toolbar {
            position: relative;
            z-index: 1055;
            overflow: visible !important;
        }
        .shopify-b2c-page .card,
        .shopify-b2c-page .card-body {
            overflow: visible;
        }
        .shopify-b2c-toolbar .dropdown,
        .shopify-b2c-toolbar .btn-group,
        .shopify-b2c-toolbar .manual-dropdown-container {
            position: relative;
            z-index: 1056;
        }
        .shopify-b2c-toolbar .dropdown-menu,
        .manual-dropdown-container .dropdown-menu {
            z-index: 2000 !important;
        }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 2000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.15);
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

        /* ========== FULL-WIDTH PAGE LAYOUT ========== */
        .content-page > .content > .container-fluid {
            padding-left: 12px;
            padding-right: 12px;
            max-width: 100%;
        }
        .shopify-b2c-page .row {
            margin-left: 0;
            margin-right: 0;
        }
        .shopify-b2c-page .row > [class*="col-"],
        .shopify-b2c-page > .row > .card {
            padding-left: 0;
            padding-right: 0;
        }
        .shopify-b2c-page .card { border-radius: 10px; }
        .shopify-b2c-page .card-body { padding: 12px 14px; }
        .shopify-b2c-page #summary-stats { padding: 6px 8px !important; overflow: hidden; }
        /* One badge row, no scroll — JS scales the row to fit width */
        .shopify-b2c-page #summary-stats .summary-badges-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 4px !important;
            width: max-content;
            max-width: none;
            transform-origin: left center;
        }
        .shopify-b2c-page #summary-stats .summary-badges-row .badge {
            flex-shrink: 0;
            font-size: 0.78rem !important;
            padding: 0.3rem 0.45rem !important;
            line-height: 1.2;
            white-space: nowrap;
        }
        .shopify-b2c-page #summary-stats .summary-search-row {
            margin-top: 8px;
        }
        .shopify-b2c-page #discount-input-container { padding: 8px 12px !important; }

        /* Parent summary rows (Amazon-style) */
        #reverb-table .tabulator-row.parent-row,
        #reverb-table .tabulator-row.parent-row .tabulator-cell {
            background-color: rgba(189, 224, 255, 0.55) !important;
            font-weight: 600;
        }
        #reverb-table .tabulator-row.parent-row:hover,
        #reverb-table .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: rgba(189, 224, 255, 0.8) !important;
        }

        /* Column visibility dropdown — 4 columns */
        .column-dropdown-multicol {
            min-width: 560px;
            padding: 6px 4px;
            column-count: 4;
            column-gap: 8px;
            max-height: 420px;
            overflow-y: auto;
        }
        .column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        .column-dropdown-multicol > li.column-dropdown-span-all {
            column-span: all;
            -webkit-column-span: all;
        }
        .column-dropdown-multicol .dropdown-item {
            padding: 3px 10px;
            white-space: nowrap;
        }

        /* ========== SKU / PARENT SEARCH (inline after NPFT badge) ========== */
        .shopify-b2c-search-group {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            height: 38px;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .shopify-b2c-search-group:focus-within {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
        .shopify-b2c-search-group .input-group-text {
            background: transparent;
            border: 0;
            color: #94a3b8;
            padding: 0 8px 0 10px;
            font-size: 0.8rem;
        }
        .shopify-b2c-search-group #sku-search,
        .shopify-b2c-search-group #parent-search {
            border: 0;
            background: transparent;
            box-shadow: none !important;
            height: 36px;
            font-size: 0.85rem;
            color: #1e293b;
            padding-left: 2px;
        }
        .shopify-b2c-search-group #sku-search::placeholder,
        .shopify-b2c-search-group #parent-search::placeholder { color: #94a3b8; }
        .shopify-b2c-search-group #sku-search:focus,
        .shopify-b2c-search-group #parent-search:focus { outline: none; border: 0; }

        /* Match Target ROI% / GPFT% height to btn-sm toolbar buttons */
        #target-roi-controls,
        #target-gpft-controls {
            height: 31px;
            padding: 0 6px !important;
            gap: 4px !important;
            box-sizing: border-box;
        }
        #target-roi-controls .form-label,
        #target-gpft-controls .form-label {
            font-size: 0.75rem;
            line-height: 1;
            margin: 0;
        }
        #target-roi-controls .form-control,
        #target-gpft-controls .form-control {
            height: 22px;
            min-height: 22px;
            width: 52px !important;
            padding: 0 4px;
            font-size: 0.75rem;
            line-height: 1.2;
        }
        #target-roi-controls .btn,
        #target-gpft-controls .btn {
            height: 22px;
            width: 26px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            line-height: 1;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify B2C - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="shopify-b2c-page">
    <div class="row">
        <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-nowrap gap-1 shopify-b2c-toolbar" style="white-space: nowrap;">
                    <select id="inventory-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 110px;">
                        <option value="all">All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 70px;">
                        <option value="all">All</option>
                        <option value="REQ" selected>REQ</option>
                        <option value="NR">NR</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm flex-shrink-0" style="width: 90px;"
                        title="GPFT% filter">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50plus">Above 50%</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm flex-shrink-0" style="width: 90px;"
                        title="CVR matches controller: OV L30 ÷ Views">
                        <option value="all">CVR%</option>
                        <option value="0-0">0%</option>
                        <option value="0-3">0-3%</option>
                        <option value="3-7">3-7%</option>
                        <option value="7-13">7-13%</option>
                        <option value="13plus">13%+</option>
                    </select>

                    {{-- Sold dropdown (mirrors Amazon tabulator + /doba page). Backed by `B2B L30`:
                         all  → no filter
                         sold → B2B L30 > 0
                         zero → B2B L30 = 0
                         Acts as the single source of truth — the #zero-sold-count-badge and
                         #more-sold-count-badge click handlers just write into this dropdown so
                         the badges and dropdown can never disagree. --}}
                    <select id="sold-filter" class="form-select form-select-sm flex-shrink-0"
                            style="width: 95px;" title="Filter by B2B L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 95px;">
                        <option value="all">GROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-60">40–60%</option>
                        <option value="60-80">60–80%</option>
                        <option value="80-100">80–100%</option>
                        <option value="gt100">100%+</option>
                    </select>

                    {{-- Row type filter (All Rows / Parents / SKUs) – same as Amazon tabulator --}}
                    <select id="parent-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 100px;" title="Filter by row type">
                        <option value="all">All Rows</option>
                        <option value="parents">Parents</option>
                        {{-- Default: hide parent summary rows on initial load --}}
                        <option value="skus" selected>SKUs</option>
                    </select>

                    <!-- DIL Filter — Amazon slabs (Red <25 / Green 25-50 / Pink 50%+) -->
                    <div class="dropdown manual-dropdown-container flex-shrink-0">
                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="dilFilterDropdown"
                            title="DIL% = OV L30 / INV × 100">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown (icon-only; includes Show All) -->
                    <div class="dropdown d-inline-block flex-shrink-0">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown"
                            data-bs-display="static" aria-expanded="false"
                            title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu column-dropdown-multicol" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>

                    <button id="export-btn" class="btn btn-sm btn-dark flex-shrink-0" title="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>

                    <div class="btn-group flex-shrink-0">
                        <button type="button" id="price-mode-btn" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="Price Mode">
                            <i class="fas fa-percent"></i> Prc M
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="price-mode-dropdown">
                            <li><a class="dropdown-item" href="#" data-mode="decrease"><i class="fas fa-arrow-down text-warning"></i> Decrease</a></li>
                            <li><a class="dropdown-item" href="#" data-mode="increase"><i class="fas fa-arrow-up text-success"></i> Increase</a></li>
                            <li><a class="dropdown-item" href="#" data-mode="same"><i class="fas fa-equals text-info"></i> Same Price</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" data-mode="cancel"><i class="fas fa-times"></i> Cancel</a></li>
                        </ul>
                    </div>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = 0.95 for Shopify B2C) --}}
                    <div class="d-inline-flex align-items-center border rounded bg-light flex-shrink-0"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.95 on every selected row (accounts for Shopify B2C 95% take-home)">
                        <label for="target-roi-input" class="form-label mb-0 fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1"
                            title="Target ROI% applied to all selected rows">
                        <button id="apply-target-roi-btn" class="btn btn-primary" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.95 for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). --}}
                    <div class="d-inline-flex align-items-center border rounded bg-light flex-shrink-0"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (0.95 − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1"
                            title="Target GPFT% applied to all selected rows. Must be less than the Shopify B2C take-home margin (< 95%).">
                        <button id="apply-target-gpft-btn" class="btn btn-primary" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (0.95 − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex summary-badges-row">
                        <span class="badge bg-success fs-6 p-2 d-none" id="total-pft-amt-badge" style="color: black; font-weight: bold;">PFT: $0</span>
                        {{-- Sales is the L30 net-sales total from the actual /shopify page
                             (shopify_raw_orders with marketplace exclusions). Server-rendered so it
                             always matches /shopify and the eBay row pattern on /all-marketplace-master.
                             Page filters do not narrow this number — it's the page-level reference. --}}
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                              style="color: black; font-weight: bold;"
                              title="L30 Net Sales from shopify_raw_orders (matches /shopify Net Sales card and the Shopify row on /all-marketplace-master). Page-level total — unaffected by table filters.">Sales: ${{ number_format((float) ($shopifyDirectL30Sales ?? 0), 0) }}</span>
                        {{-- Orders: distinct order_id count from the same source. New badge requested
                             so this page agrees with /shopify and /all-marketplace-master Shopify row. --}}
                        <span class="badge bg-secondary fs-6 p-2" id="total-orders-badge"
                              style="color: white; font-weight: bold;"
                              title="L30 distinct orders from shopify_raw_orders (matches /shopify and /all-marketplace-master Shopify row).">Orders: {{ number_format((int) ($shopifyDirectL30Orders ?? 0)) }}</span>
                        {{-- Qty: Σ ebay_order_items.quantity-equivalent from shopify_raw_orders for the
                             same L30 window. Same value /shopify reports as "Total Qty" and
                             /all-marketplace-master shows in the Shopify row's "Qty items" cell. --}}
                        <span class="badge fs-6 p-2" id="total-qty-badge"
                              style="background-color: #6f42c1; color: white; font-weight: bold;"
                              title="L30 units sold from shopify_raw_orders (matches /shopify and /all-marketplace-master Shopify row).">Qty: {{ number_format((int) ($shopifyDirectL30Qty ?? 0)) }}</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 d-none" id="avg-price-badge" style="color: black; font-weight: bold;">Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none" id="total-inv-badge" style="color: black; font-weight: bold;">INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">L30: 0</span>
                        <span class="badge fs-6 p-2" id="total-views-badge" style="background-color: #0d6efd; color: white; font-weight: bold;" title="Sum of L30 product page views (sessions)">Views: 0</span>
                        <span class="badge fs-6 p-2" id="avg-cvr-badge" style="background-color: #20c997; color: #000; font-weight: bold;" title="Overall CVR = L30 ÷ Views">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-b2b-l30-badge" style="color: black; font-weight: bold;">B2B: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter B2B L30 = 0">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter B2B L30 > 0">&gt;0 Sold: 0</span>
                        <span class="badge bg-info fs-6 p-2 d-none" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI: 0%</span>
                        <span class="badge fs-6 p-2" id="nroi-percent-badge" style="background-color: #e83e8c; color: white; font-weight: bold;">NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amazon">&lt; Amz: 0</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amazon">&gt; Amz: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs">Miss: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Ads: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Spend: $0</span>
                        <span class="badge fs-6 p-2" id="avg-npft-badge" style="background-color: #fd7e14; color: white; font-weight: bold;">NPFT: 0%</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 summary-search-row">
                        <div class="input-group shopify-b2c-search-group" style="max-width: 200px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                        </div>
                        <div class="input-group shopify-b2c-search-group" style="max-width: 200px;">
                            <span class="input-group-text"><i class="fas fa-sitemap"></i></span>
                            <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-copy"></i> Sugg Amz Prc
                        </button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="reverb-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="reverb-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
        </div>
    </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "shopify_b2c_tabulator_column_visibility";
    /** Stored in DB table channel_tabulator_column_settings (shared across all users — same as Amazon). */
    const TABULATOR_COLUMN_CHANNEL = 'shopify_b2c_tabulator';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    /** L30 sales + distinct order count from /shopify (shopify_raw_orders with
     *  marketplace exclusions). Page-level totals — used to drive the Total
     *  Sales and Orders badges so this page agrees with /shopify and the
     *  /all-marketplace-master Shopify row. Page filters do NOT narrow these
     *  numbers, mirroring how /shopify reports them. */
    const SHOPIFY_DIRECT_L30_SALES   = {{ (float) ($shopifyDirectL30Sales   ?? 0) }};
    const SHOPIFY_DIRECT_L30_ORDERS  = {{ (int)   ($shopifyDirectL30Orders  ?? 0) }};
    const SHOPIFY_DIRECT_L30_QTY     = {{ (int)   ($shopifyDirectL30Qty     ?? 0) }};
    /** Profit / cost / spend + derived percentages from the same /shopify snapshot
     *  the master Shopify row uses. Drives Total PFT, GPFT, Total Spend, TCOS,
     *  NPFT, and NROI badges on this page so they agree with /all-marketplace-master. */
    const SHOPIFY_DIRECT_TOTAL_PFT   = {{ (float) ($shopifyDirectTotalPft   ?? 0) }};
    const SHOPIFY_DIRECT_TOTAL_COGS  = {{ (float) ($shopifyDirectTotalCogs  ?? 0) }};
    const SHOPIFY_DIRECT_TOTAL_SPEND = {{ (float) ($shopifyDirectTotalSpend ?? 0) }};
    const SHOPIFY_DIRECT_GPFT_PCT    = {{ (float) ($shopifyDirectGpftPct    ?? 0) }};
    const SHOPIFY_DIRECT_GROI_PCT    = {{ (float) ($shopifyDirectGroiPct    ?? 0) }};
    const SHOPIFY_DIRECT_TCOS_PCT    = {{ (float) ($shopifyDirectTcosPct    ?? 0) }};
    const SHOPIFY_DIRECT_NPFT_PCT    = {{ (float) ($shopifyDirectNpftPct    ?? 0) }};
    const SHOPIFY_DIRECT_NROI_PCT    = {{ (float) ($shopifyDirectNroiPct    ?? 0) }};

    /**
     * Channel Ads% (TCOS badge) — same role as Amazon AMAZON_CHANNEL_ADS_PCT.
     */
    function shopifyChannelAdsPct() {
        return parseFloat(SHOPIFY_DIRECT_TCOS_PCT) || 0;
    }

    /** Amazon-style parent summary row (SKU like "PARENT 10 FR"). */
    function isShopifyB2cParentRow(row) {
        if (!row) return false;
        if (row.is_parent_summary === true) return true;
        const sku = String(row['(Child) sku'] || '').toUpperCase();
        return sku.includes('PARENT');
    }

    /**
     * Net ROI (NROI% / SNROI) — Amazon unit formula (works even when L30 qty = 0):
     *   ((Price × 0.95 − Ship − LP − Price × Ads%/100) / LP) × 100
     * Ads% = channel Ads badge (TCOS).
     */
    function shopifyComputeNetRoi(price, lp, ship, adsPct) {
        price = parseFloat(price);
        lp = parseFloat(lp);
        if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return 0;
        ship = parseFloat(ship) || 0;
        const ads = (adsPct != null && isFinite(parseFloat(adsPct)))
            ? parseFloat(adsPct)
            : shopifyChannelAdsPct();
        const grossPft = (price * 0.95) - ship - lp;
        const adSpend = price * (ads / 100);
        return ((grossPft - adSpend) / lp) * 100;
    }

    function shopifyComputeSnroi(sprice, lp, ship, adsPct) {
        return shopifyComputeNetRoi(sprice, lp, ship, adsPct);
    }

    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {
        // Show the discount-type dropdown only for % / $ modes; hide it for Same Price.
        function syncDiscountInputUi() {
            const $input = $('#discount-percentage-input');
            if (samePriceModeActive) {
                $('#discount-type-select-wrap').hide();
                $('#discount-input-label').removeClass('d-none');
                $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
                $('#apply-discount-btn').text('Apply Same Price');
            } else {
                $('#discount-type-select-wrap').show();
                $('#discount-input-label').addClass('d-none');
                const t = $('#discount-type-select').val();
                $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
                const action = increaseModeActive ? 'Increase' : (decreaseModeActive ? 'Decrease' : '');
                $('#apply-discount-btn').text(action ? `Apply ${action}` : 'Apply');
            }
        }

        function exitPriceMode() {
            decreaseModeActive = false;
            increaseModeActive = false;
            samePriceModeActive = false;
            if (table) {
                const col = table.getColumn('_select');
                if (col) col.hide();
            }
            selectedSkus.clear();
            $('.sku-select-checkbox').prop('checked', false);
            if ($('#select-all-checkbox').length) $('#select-all-checkbox').prop('checked', false);
            updateSelectedCount();
            $('#price-mode-btn').removeClass('btn-danger btn-warning btn-success btn-info').addClass('btn-primary')
                .html('<i class="fas fa-percent"></i> Prc M');
            syncDiscountInputUi();
        }

        function setPriceMode(mode) {
            if (!table) return;
            const selectColumn = table.getColumn('_select');
            if (!selectColumn) return;

            if (mode === 'cancel') {
                exitPriceMode();
                return;
            }

            decreaseModeActive = (mode === 'decrease');
            increaseModeActive = (mode === 'increase');
            samePriceModeActive = (mode === 'same');
            selectColumn.show();
            $('#discount-percentage-input').val('');

            if (mode === 'decrease') {
                $('#price-mode-btn').removeClass('btn-primary btn-success btn-info').addClass('btn-warning')
                    .html('<i class="fas fa-arrow-down"></i> Decrease');
            } else if (mode === 'increase') {
                $('#price-mode-btn').removeClass('btn-primary btn-warning btn-info').addClass('btn-success')
                    .html('<i class="fas fa-arrow-up"></i> Increase');
            } else if (mode === 'same') {
                $('#price-mode-btn').removeClass('btn-primary btn-warning btn-success').addClass('btn-info')
                    .html('<i class="fas fa-equals"></i> Same Price');
            }
            syncDiscountInputUi();
            updateSelectedCount();
        }

        $(document).on('click', '#price-mode-dropdown a[data-mode]', function(e) {
            e.preventDefault();
            setPriceMode($(this).data('mode'));
        });

        // Keep placeholder in sync when the user toggles % vs $.
        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        // Select all checkbox handler
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active').filter(row => !isShopifyB2cParentRow(row));
            
            filteredData.forEach(row => {
                if (isChecked) {
                    selectedSkus.add(row['(Child) sku']);
                } else {
                    selectedSkus.delete(row['(Child) sku']);
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

        // Individual checkbox handler
        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Apply discount button
        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        // Apply discount on Enter key
        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        /*
         * Target ROI% bulk apply (Shopify B2C, margin = 0.95)
         * ---------------------------------------------------
         * For every selected row with a usable LP, back-solve the sale price so the
         * resulting SROI column matches Target ROI%:
         *     SROI = ((sprice * margin − ship − lp) / lp) * 100
         *   → sprice = (lp * (1 + ROI%/100) + ship) / margin
         * Optimistic SGPFT / SROI / SNPFT / SNROI are written client-side and the
         * bulk save endpoint (/shopify/save-sprice) recomputes them server-side.
         */
        $('#apply-target-roi-btn').on('click', function () {
            const rawInput = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target ROI%', 'error');
                return;
            }
            if (!isFinite(targetRoiPct)) {
                showToast('Target ROI% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (use Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const SHOPIFY_B2C_MARGIN = 0.95;
            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                if (isShopifyB2cParentRow(rowData)) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const ads  = shopifyChannelAdsPct();

                const candidate = (lp * roiMultiplier + ship) / SHOPIFY_B2C_MARGIN;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const grossProfit = (newSprice * SHOPIFY_B2C_MARGIN) - lp - ship;
                const sgpft = newSprice > 0 ? (grossProfit / newSprice) * 100 : 0;
                const snpft = sgpft - ads;
                const sroi  = lp > 0 ? (grossProfit / lp) * 100 : 0;
                const snroi = shopifyComputeSnroi(newSprice, lp, ship, ads);

                row.update({
                    SPRICE: newSprice,
                    SGPFT: sgpft,
                    SNPFT: snpft,
                    SROI: sroi,
                    SNROI: snroi,
                    has_custom_sprice: true
                });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        /*
         * Target GPFT% bulk apply (Shopify B2C, margin = 0.95)
         * ----------------------------------------------------
         * Mirrors Target ROI but back-solves so SGPFT = Target GPFT%:
         *     SGPFT = ((sprice * margin − ship − lp) / sprice) * 100
         *   → sprice = (lp + ship) / (margin − GPFT%/100)
         * Constraint: (margin − target/100) must be > 0, i.e. Target GPFT% < 95%.
         */
        $('#apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target GPFT%', 'error');
                return;
            }
            if (!isFinite(targetGpftPct)) {
                showToast('Target GPFT% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (use Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const SHOPIFY_B2C_MARGIN = 0.95;
            const denom = SHOPIFY_B2C_MARGIN - (targetGpftPct / 100);
            if (denom <= 0) {
                showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < 95% (Shopify B2C take-home).`, 'error');
                return;
            }

            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                if (isShopifyB2cParentRow(rowData)) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const ads  = shopifyChannelAdsPct();

                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const grossProfit = (newSprice * SHOPIFY_B2C_MARGIN) - lp - ship;
                const sgpft = newSprice > 0 ? (grossProfit / newSprice) * 100 : 0;
                const snpft = sgpft - ads;
                const sroi  = lp > 0 ? (grossProfit / lp) * 100 : 0;
                const snroi = shopifyComputeSnroi(newSprice, lp, ship, ads);

                row.update({
                    SPRICE: newSprice,
                    SGPFT: sgpft,
                    SNPFT: snpft,
                    SROI: sroi,
                    SNROI: snroi,
                    has_custom_sprice: true
                });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        // Enter inside Target ROI%/GPFT% inputs triggers Apply S PRC
        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Sugg Amz Prc button
        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        // Clear SPRICE button
        $('#clear-sprice-btn').on('click', function() {
            clearSpriceForSelected();
        });

        // Badge clicks just toggle the #sold-filter dropdown so the dropdown stays the
        // single source of truth for the Sold filter (mirrors Amazon tabulator behavior).
        // Clicking the same badge twice clears the filter (toggle semantics preserved).
        $('#zero-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#more-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            applyFilters();
        });

        // < Amz badge click handler - filter prices less than Amazon
        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function() {
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // > Amz badge click handler - filter prices greater than Amazon
        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function() {
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // Missing badge click handler - filter SKUs missing in Shopify B2C
        let missingFilterActive = false;
        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            applyFilters();
        });

        // ========== MANUAL DROPDOWN FUNCTIONALITY (Walmart-style) ==========
        // Initialize dropdown functionality
        $(document).on('click', '.manual-dropdown-container .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            
            // Close other dropdowns
            $('.manual-dropdown-container').not(container).removeClass('show');
            
            // Toggle current dropdown
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const column = $item.data('column');
            const color = $item.data('color');
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            
            // Update active state
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            // Update button text and icon
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            // Close dropdown
            container.removeClass('show');
            
            // Apply filters
            applyFilters();
        });

        // Close dropdowns when clicking outside
        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        // Update selected count display
        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        // Update select all checkbox state
        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active').filter(row => !isShopifyB2cParentRow(row));
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                [...filteredSkus].every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        // Custom price rounding function to round to .99 endings
        function roundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            // Round to the nearest dollar and subtract 0.01 to make it .99
            const roundedDollar = Math.ceil(price);
            return roundedDollar - 0.01;
        }

        // Apply discount / same price to selected SKUs (based on Price column).
        function applyDiscount() {
            const discountType = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Choose Decrease, Increase, or Same Price from Price Mode first', 'error');
                return;
            }
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            let updatedCount = 0;
            const updates = []; // Store updates for backend saving

            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);

                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const currentPrice = parseFloat(rowData['Price']) || 0;

                    // Same Price mode applies even when current Price is 0/empty;
                    // % and $ modes still require a positive Price to compute against.
                    if (samePriceModeActive || currentPrice > 0) {
                        let newSprice;

                        if (samePriceModeActive) {
                            // The ONE price the user typed, applied verbatim to every selected SKU.
                            newSprice = Math.max(0.99, discountValue);
                        } else if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSprice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSprice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSprice = currentPrice + discountValue;
                            } else {
                                newSprice = currentPrice - discountValue;
                            }
                        }

                        // Apply retail price rounding (round to .99 endings)
                        newSprice = roundToRetailPrice(newSprice);

                        // Ensure minimum price
                        newSprice = Math.max(0.99, newSprice);

                        // Calculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2C)
                        const percentage = 0.95; // Shopify B2C margin
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        const ads = shopifyChannelAdsPct();

                        const grossProfit = (newSprice * percentage) - lp - ship;
                        const sgpft = newSprice > 0 ? (grossProfit / newSprice) * 100 : 0;
                        const snpft = sgpft - ads;
                        const sroi = lp > 0 ? (grossProfit / lp) * 100 : 0;
                        const snroi = shopifyComputeSnroi(newSprice, lp, ship, ads);

                        // Update SPRICE and calculated values in table
                        row.update({
                            SPRICE: newSprice,
                            SGPFT: sgpft,
                            SNPFT: snpft,
                            SROI: sroi,
                            SNROI: snroi,
                            has_custom_sprice: true
                        });

                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: newSprice
                        });

                        updatedCount++;
                    }
                }
            });

            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            const action = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Discount');
            showToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
        }

        // Apply Amazon suggested price
        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = []; // Store updates for backend saving

            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['A Price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        // Calculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2C)
                        const percentage = 0.95; // Shopify B2C margin
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        const ads = shopifyChannelAdsPct();
                        
                        const grossProfit = (amazonPrice * percentage) - lp - ship;
                        const sgpft = amazonPrice > 0 ? (grossProfit / amazonPrice) * 100 : 0;
                        const snpft = sgpft - ads;
                        const sroi = lp > 0 ? (grossProfit / lp) * 100 : 0;
                        const snroi = shopifyComputeSnroi(amazonPrice, lp, ship, ads);
                        
                        // Update the row with SPRICE and calculated values
                        row.update({
                            SPRICE: amazonPrice,
                            SGPFT: sgpft,
                            SNPFT: snpft,
                            SROI: sroi,
                            SNROI: snroi,
                            has_custom_sprice: true
                        });
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates) {
            console.log('Saving SPRICE updates:', updates.length, 'SKUs');
            
            $.ajax({
                url: '/shopify/save-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    updates: updates
                },
                success: function(response) {
                    console.log('Backend response:', response);
                    if (response.success) {
                        showToast(`SPRICE saved for ${response.updated} SKU(s)`, 'success');
                        if (response.errors && response.errors.length > 0) {
                            console.warn('Some updates had errors:', response.errors);
                            showToast(`${response.errors.length} SKU(s) had errors`, 'warning');
                        }
                    } else if (response.message) {
                        showToast(response.message, 'success');
                    }
                },
                error: function(xhr) {
                    console.error('Error saving SPRICE updates:', xhr);
                    console.error('Response:', xhr.responseText);
                    let errorMessage = 'Error saving SPRICE updates to database';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ': ' + xhr.responseJSON.error;
                    }
                    showToast(errorMessage, 'error');
                }
            });
        }

        // Clear SPRICE for selected SKUs
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            let clearedCount = 0;
            const updates = [];

            // Get all rows and filter by selected SKUs
            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                
                if (selectedSkus.has(sku)) {
                    // Clear SPRICE in table
                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0
                    });
                    
                    // Store update for backend saving
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    
                    clearedCount++;
                }
            });

            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        // SAVE SPRICE to database with retry
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            const maxRetries = 3;
            
            $.ajax({
                url: '/shopify/save-sprice',
                method: 'POST',
                data: {
                    sku: sku,
                    sprice: sprice,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`SPRICE saved for ${sku}`, 'success');
                    // Response from backend will have calculated values
                    if (response.sgpft_percent !== undefined) {
                        row.update({ SGPFT: response.sgpft_percent });
                    }
                    if (response.snpft_percent !== undefined) {
                        row.update({ SNPFT: response.snpft_percent });
                    }
                    if (response.sroi_percent !== undefined) {
                        row.update({ SROI: response.sroi_percent });
                    }
                    if (response.snroi_percent !== undefined) {
                        row.update({ SNROI: response.snroi_percent });
                    }
                },
                error: function(xhr) {
                    if (retryCount < maxRetries) {
                        setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1), 2000);
                    } else {
                        showToast(`Failed to save SPRICE for ${sku}`, 'error');
                    }
                }
            });
        }

        // Campaign totals from backend (like Amazon page)
        let campaignTotals = {
            google_spend_L30: 0
        };

        // Initialize Tabulator
        table = new Tabulator("#reverb-table", {
            ajaxURL: "/shopify-b2c-data-json",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            ajaxResponse: function(url, params, response) {
                // Extract campaign totals from response (like Amazon)
                if (response.campaign_totals) {
                    campaignTotals = response.campaign_totals;
                }
                // Return only the data array to Tabulator
                return response.data || response;
            },
            initialSort: [{
                column: "L30",
                dir: "desc"
            }],
            rowFormatter: function(row) {
                if (isShopifyB2cParentRow(row.getData())) {
                    row.getElement().classList.add('parent-row');
                }
            },
            columns: [
               
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    frozen: true,
                    width: 150,
                    visible: true
                },
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 80
                },
                {
                    title: "SKU",
                    field: "(Child) sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) {
                            return `<span style="font-weight: bold;">${sku}</span>`;
                        }
                        let html = `<span>${sku}</span>`;
                        
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        
                        return html;
                    }
                },
                {
                    title: "Links",
                    field: "links_column",
                    frozen: true,
                    width: 55,
                    hozAlign: "center",
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData['B Link'] || '';
                        const sellerLink = rowData['S Link'] || '';
                        
                        let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';
                        
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> S
                            </a>`;
                        }
                        
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> B
                            </a>`;
                        }
                        
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size: 12px;">-</span>';
                        }
                        
                        html += '</div>';
                        return html;
                    },
                    headerSort: false
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "Dil",
                    field: "DIL%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';

                        // Same DIL color slabs as Amazon filter: Red <25 / Green 25-50 / Pink 50%+
                        if (dil < 25) color = '#a00211';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Views",
                    field: "Views",
                    hozAlign: "center",
                    width: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0, 10);
                        if (value === 0) {
                            return '<span style="color: #6c757d;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value.toLocaleString()}</span>`;
                    }
                },
                {
                    title: "CVR%",
                    field: "CVR%",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const l30 = parseFloat(rowData['L30']) || 0;
                        const views = parseFloat(rowData['Views']) || 0;

                        if (views === 0) return '<span style="color: #6c757d;">0%</span>';

                        const cvr = (l30 / views) * 100;
                        let color = '';

                        // Same CVR color slabs as Amazon CVR L30
                        if (cvr <= 4) color = '#a00211';
                        else if (cvr > 4 && cvr <= 7) color = '#ffc107';
                        else if (cvr > 7 && cvr <= 13) color = '#28a745';
                        else color = '#e83e8c';

                        return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "B2C L30",
                    field: "B2B L30",
                    hozAlign: "center",
                    width: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #6c757d;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "Missing",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || String(value).trim() === '') {
                            value = 'REQ';
                        }
                        if (isShopifyB2cParentRow(rowData)) {
                            return value === 'NR'
                                ? '<span title="Derived from children">🔴</span>'
                                : '<span title="Derived from children">🟢</span>';
                        }
                        
                        return `<select class="form-select form-select-sm nr-req-dropdown" 
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                {
                    title: "Prc",
                    field: "Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const amazonPrice = parseFloat(rowData['A Price']) || 0;
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        // Show red if Price is less than Amazon Price
                        if (amazonPrice > 0 && value < amazonPrice) {
                            return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        // Show green if Price is greater than Amazon Price
                        if (amazonPrice > 0 && value > amazonPrice) {
                            return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "A Prc",
                    field: "A Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "GPFT %",
                    field: "GPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';

                        // Same GPFT color slabs as Amazon
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#ffc107';
                        else if (percent >= 30 && percent < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "GROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';

                        // Same color slabs as Amazon GROI%
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "PFT %",
                    field: "NPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same as Amazon PFT %: GPFT% − channel Ads% (TCOS badge)
                        const rowData = cell.getRow().getData();
                        const gpft = parseFloat(rowData['GPFT%']) || 0;
                        const ads = parseFloat(SHOPIFY_DIRECT_TCOS_PCT) || 0;
                        const npft = gpft - ads;
                        
                        let color = '';
                        if (npft < 10) color = '#a00211';
                        else if (npft >= 10 && npft < 20) color = '#3591dc';
                        else if (npft >= 20 && npft < 30) color = '#ffc107';
                        else if (npft >= 30 && npft < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${npft.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "NROI%",
                    field: "NROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same Amazon unit formula as SNROI / GROI (with channel Ads%), not qty-gated
                        const rowData = cell.getRow().getData();
                        const nroi = shopifyComputeNetRoi(
                            rowData['Price'],
                            rowData['LP_productmaster'],
                            rowData['Ship_productmaster'],
                            shopifyChannelAdsPct()
                        );
                        
                        let color = '';
                        if (nroi < 50) color = '#a00211';
                        else if (nroi >= 50 && nroi < 75) color = '#ffc107';
                        else if (nroi >= 75 && nroi <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${nroi.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Profit",
                    field: "Profit",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = value >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "Sales",
                    field: "Sales L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 80
                },
                {
                    title: "LP",
                    field: "LP_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                {
                    title: "Ship",
                    field: "Ship_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                 {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "number",
                    editable: function(cell) {
                        return !isShopifyB2cParentRow(cell.getRow().getData());
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) {
                            return '';
                        }
                        const value = parseFloat(cell.getValue() || 0);
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';
                        
                        return `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">$${value.toFixed(2)}</span>`;
                    },
                    width: 80
                },
                {
                    title: "S GPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';

                        // Same as Amazon S GPFT / GPFT % slabs
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#ffc107';
                        else if (percent >= 30 && percent < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Sroi",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';

                        // Same as Amazon Sroi / GROI% slabs
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNPFT",
                    field: "SNPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same as Amazon SNPFT: SGPFT − channel Ads% (TCOS badge)
                        const rowData = cell.getRow().getData();
                        const sgpft = parseFloat(rowData['SGPFT']) || 0;
                        const ads = parseFloat(SHOPIFY_DIRECT_TCOS_PCT) || 0;
                        const snpft = sgpft - ads;
                        
                        let color = '';
                        if (snpft < 10) color = '#a00211';
                        else if (snpft >= 10 && snpft < 20) color = '#3591dc';
                        else if (snpft >= 20 && snpft < 30) color = '#ffc107';
                        else if (snpft >= 30 && snpft < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${snpft.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNROI",
                    field: "SNROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same shape as NROI badge / Amazon SNROI: (gross − SPRICE×Ads%) / LP × 100
                        const rowData = cell.getRow().getData();
                        const snroi = shopifyComputeSnroi(
                            rowData['SPRICE'],
                            rowData['LP_productmaster'],
                            rowData['Ship_productmaster'],
                            parseFloat(SHOPIFY_DIRECT_TCOS_PCT) || 0
                        );
                        
                        let color = '';
                        if (snroi < 50) color = '#a00211';
                        else if (snroi >= 50 && snroi < 75) color = '#ffc107';
                        else if (snroi >= 75 && snroi <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${snroi.toFixed(0)}%</span>`;
                    },
                    width: 50
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const $rowEl = $cell.closest('.tabulator-row');
            const row = table.getRow($rowEl[0]); // Pass DOM element, not jQuery object
            const rowData = row.getData();
            const sku = rowData['(Child) sku'];
            const newValue = $(this).val();
            
            $.ajax({
                url: '{{ url("/shopify-b2c-update-listed-live") }}',
                method: 'POST',
                data: {
                    sku: sku,
                    nr_req: newValue,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`${sku}: Status updated to ${newValue}`, 'success');
                    row.update({ nr_req: newValue });
                },
                error: function(xhr) {
                    showToast(`Failed to update status for ${sku}`, 'error');
                }
            });
        });

        // SPRICE cell edited - save to database
        table.on('cellEdited', function(cell) {
            if (cell.getField() === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                if (isShopifyB2cParentRow(rowData)) return;
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                // Recalculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2C)
                const percentage = 0.95; // Shopify B2C margin
                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const ads = shopifyChannelAdsPct();
                
                // SGPFT = ((SPRICE × 95%) - LP - Ship) / SPRICE × 100
                const grossProfit = (newSprice * percentage) - lp - ship;
                const sgpft = newSprice > 0 ? (grossProfit / newSprice) * 100 : 0;
                
                // SNPFT = SGPFT - ADS
                const snpft = sgpft - ads;
                
                // SROI = Gross Profit / LP × 100
                const sroi = lp > 0 ? (grossProfit / lp) * 100 : 0;
                
                // SNROI = (suggested gross − SPRICE×Ads%) / LP × 100 (same shape as NROI badge)
                const snroi = shopifyComputeSnroi(newSprice, lp, ship, ads);
                
                row.update({
                    SGPFT: sgpft,
                    SNPFT: snpft,
                    SROI: sroi,
                    SNROI: snroi,
                    has_custom_sprice: true
                });
                
                // Save to database
                saveSpriceWithRetry(sku, newSprice, row);
            }
        });

        // Copy SKU button handler
        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        // Apply filters
        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active').data('color') || 'all';

            // Clear all filters first
            table.clearFilter();

            // Inventory filter
            if (inventoryFilter === 'zero') {
                table.addFilter("INV", "=", 0);
            } else if (inventoryFilter === 'more') {
                table.addFilter("INV", ">", 0);
            }

            // NRL filter
            if (nrlFilter === 'REQ') {
                table.addFilter("nr_req", "=", "REQ");
            } else if (nrlFilter === 'NR') {
                table.addFilter("nr_req", "=", "NR");
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                if (gpftFilter === 'negative') {
                    table.addFilter("GPFT%", "<", 0);
                } else if (gpftFilter === '50plus') {
                    table.addFilter("GPFT%", ">=", 50);
                } else {
                    const [min, max] = gpftFilter.split('-').map(Number);
                    table.addFilter("GPFT%", ">=", min);
                    table.addFilter("GPFT%", "<", max);
                }
            }

            // ROI filter — same slabs as Amazon GROI%
            if (roiFilter !== 'all') {
                table.addFilter(function(data) {
                    const roiVal = parseFloat(data['ROI%']) || 0;
                    if (roiFilter === 'lt40') return roiVal < 40;
                    if (roiFilter === 'gt100') return roiVal > 100;
                    const [min, max] = roiFilter.split('-').map(Number);
                    return roiVal >= min && roiVal <= max;
                });
            }

            // CVR filter — same slabs as Amazon
            const cvrFilter = $('#cvr-filter').val();
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const l30 = parseFloat(data['L30']) || 0;
                    const views = parseFloat(data['Views']) || 0;
                    const cvrPercent = views > 0 ? (l30 / views) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // DIL filter — same slabs as Amazon (OV L30 / INV × 100)
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['INV']) || 0;
                    const l30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                    if (dilFilter === 'red') return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // Sold filter (based on B2B L30). Driven by the #sold-filter dropdown;
            // the legacy zero/more "badge active" flags are kept in sync below in the
            // badge click handlers so existing badge UX (toggle on/off) still works.
            const soldFilter = $('#sold-filter').val();
            if (soldFilter === 'zero') {
                table.addFilter("B2B L30", "=", 0);
            } else if (soldFilter === 'sold') {
                table.addFilter("B2B L30", ">", 0);
            }

            // < Amz filter - show prices less than Amazon price
            if (lessAmzFilterActive) {
                table.addFilter(function(data) {
                const price = parseFloat(data['Price']) || 0;
                const amazonPrice = parseFloat(data['A Price']) || 0;
                return amazonPrice > 0 && price > 0 && price < amazonPrice;
                });
            }

            // > Amz filter - show prices greater than Amazon price
            if (moreAmzFilterActive) {
                table.addFilter(function(data) {
                const price = parseFloat(data['Price']) || 0;
                const amazonPrice = parseFloat(data['A Price']) || 0;
                return amazonPrice > 0 && price > 0 && price > amazonPrice;
                });
            }

            // Missing filter - show SKUs missing in Shopify B2C
            if (missingFilterActive) {
                table.addFilter("Missing", "=", "M");
            }

            // Row type filter: All Rows / Parents / SKUs (same as Amazon)
            const parentFilter = $('#parent-filter').val();
            if (parentFilter === 'parents') {
                table.addFilter(function(data) {
                    return isShopifyB2cParentRow(data);
                });
            } else if (parentFilter === 'skus') {
                table.addFilter(function(data) {
                    return !isShopifyB2cParentRow(data);
                });
            }

            updateSummary();
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #sold-filter, #parent-filter').on('change', function() {
            applyFilters();
        });

        // Update summary badges
        function updateSummary() {
            const inventoryFilter = $('#inventory-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            
            // Get ALL data (ignore search filter) and manually apply ONLY dropdown filters
            const allData = table.getData();
            const data = allData.filter(row => {
                // Exclude parent summary rows from badge math
                if (isShopifyB2cParentRow(row)) return false;
                
                // Apply inventory filter
                const inv = parseFloat(row.INV) || 0;
                if (inventoryFilter === 'zero' && inv !== 0) return false;
                if (inventoryFilter === 'more' && inv <= 0) return false;
                
                // Apply NRL filter
                if (nrlFilter === 'req' && row.nr_req === 'NR') return false;
                if (nrlFilter === 'nr' && row.nr_req !== 'NR') return false;
                
                return true;
            });
            
            console.log('UpdateSummary - Total rows (ignoring search):', data.length);

            let totalPft = 0, totalSales = 0, totalGpft = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, totalL30 = 0, totalViews = 0, totalB2BL30 = 0, zeroSoldCount = 0, moreSoldCount = 0;
            let totalCogs = 0, totalRoi = 0, roiCount = 0, lessAmzCount = 0, moreAmzCount = 0;
            let missingCount = 0;

            data.forEach(row => {
                totalPft += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;
                totalGpft += parseFloat(row['GPFT%']) || 0;
                
                const price = parseFloat(row['Price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                
                totalInv += parseFloat(row.INV) || 0;
                totalL30 += parseFloat(row['L30']) || 0;
                totalViews += parseFloat(row['Views']) || 0;
                totalB2BL30 += parseFloat(row['B2B L30']) || 0;
                
                // Count based on B2B L30 (not OV L30)
                const b2bL30 = parseFloat(row['B2B L30']) || 0;
                if (b2bL30 === 0) {
                    zeroSoldCount++;
                } else {
                    moreSoldCount++;
                }
                
                // COGS = LP × B2B L30 (not OV L30)
                const lp = parseFloat(row['LP_productmaster']) || 0;
                totalCogs += lp * b2bL30;
                
                const roi = parseFloat(row['ROI%']) || 0;
                if (roi !== 0) {
                    totalRoi += roi;
                    roiCount++;
                }
                
                // Compare Price with Amazon Price (must match filter logic exactly)
                const amzPrice = parseFloat(row['A Price']) || 0;
                
                // Count for < Amz (reuse price variable from above)
                if (amzPrice > 0 && price > 0 && price < amzPrice) {
                    lessAmzCount++;
                }
                
                // Count for > Amz (reuse price variable from above)
                if (amzPrice > 0 && price > 0 && price > amzPrice) {
                    moreAmzCount++;
                }
                
                // Count Missing
                if (row['Missing'] === 'M') {
                    missingCount++;
                }
            });

            // Calculate GPFT % = (Total PFT / Total Sales) * 100 (same as Sales page)
            const avgGpft = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgRoi = roiCount > 0 ? totalRoi / roiCount : 0;

            // All page-level financials below come from the same /shopify L30 snapshot
            // the Shopify row on /all-marketplace-master uses (single source of truth:
            // ChannelMasterController::getShopifyDirectL30Snapshot). Per-row totals are
            // still computed in updateSummary above for any per-row consumer that
            // needs them, but the BADGES read the page-level constants so this page,
            // /shopify, and the master Shopify row always show the same numbers.
            $('#total-pft-amt-badge').text(`PFT: $${Math.round(SHOPIFY_DIRECT_TOTAL_PFT).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Sales: $${Math.round(SHOPIFY_DIRECT_L30_SALES).toLocaleString()}`);
            $('#total-orders-badge').text(`Orders: ${SHOPIFY_DIRECT_L30_ORDERS.toLocaleString()}`);
            $('#total-qty-badge').text(`Qty: ${SHOPIFY_DIRECT_L30_QTY.toLocaleString()}`);
            $('#avg-gpft-badge').text(`GPFT: ${SHOPIFY_DIRECT_GPFT_PCT.toFixed(1)}%`);
            $('#avg-price-badge').text(`Price: $${avgPrice.toFixed(2)}`);
            $('#total-inv-badge').text(`INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`L30: ${totalL30.toLocaleString()}`);
            const overallCvr = totalViews > 0 ? (totalL30 / totalViews) * 100 : 0;
            $('#total-views-badge').text(`Views: ${totalViews.toLocaleString()}`);
            $('#avg-cvr-badge').text(`CVR: ${Math.round(overallCvr)}%`);
            $('#total-b2b-l30-badge').text(`B2B: ${totalB2BL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            $('#more-sold-count-badge').text(`>0 Sold: ${moreSoldCount}`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`ROI: ${avgRoi.toFixed(1)}%`);
            $('#less-amz-badge').text(`< Amz: ${lessAmzCount}`);
            $('#more-amz-badge').text(`> Amz: ${moreAmzCount}`);
            $('#missing-count-badge').text(`Miss: ${missingCount}`);
            
            // Spend / TCOS / NPFT / NROI all read the page-level snapshot now.
            // Spend = Google + Meta rollup from /shopify-ads-master (same number
            // its Spend badge shows). TCOS = that rollup's tcos_pct (same number its
            // TCOS badge shows). NPFT = GPFT − TCOS. NROI = (Pft − Spend) / COGS.
            // All four agree with the Shopify row on /all-marketplace-master.
            $('#total-tcos-badge').text(`Ads: ${Math.round(SHOPIFY_DIRECT_TCOS_PCT)}%`);
            $('#total-spend-badge').text(`Spend: $${Math.round(SHOPIFY_DIRECT_TOTAL_SPEND).toLocaleString()}`);
            $('#avg-npft-badge').text(`NPFT: ${SHOPIFY_DIRECT_NPFT_PCT.toFixed(1)}%`);
            $('#nroi-percent-badge').text(`NROI: ${SHOPIFY_DIRECT_NROI_PCT.toFixed(1)}%`);

            fitSummaryBadgesRow();
        }

        /** Scale badge row to container width so everything stays on 1 line with no scroll. */
        function fitSummaryBadgesRow() {
            const row = document.querySelector('#summary-stats .summary-badges-row');
            const box = document.getElementById('summary-stats');
            if (!row || !box) return;
            row.style.transform = 'none';
            row.style.marginBottom = '0';
            const available = box.clientWidth - 16;
            const needed = row.scrollWidth;
            if (available > 0 && needed > available) {
                const scale = available / needed;
                row.style.transform = 'scale(' + scale + ')';
                // Collapse leftover layout height after scale
                row.style.marginBottom = (-(1 - scale) * row.offsetHeight) + 'px';
            }
        }
        $(window).on('resize', fitSummaryBadgesRow);

        /*
         * Column visibility persists in shared DB table channel_tabulator_column_settings
         * under channel = 'shopify_b2c_tabulator' — same /tabulator-column-visibility
         * endpoint Amazon / ebay / mfrg tabulators use.
         */
        function buildColumnDropdown(savedVisibility) {
            const menu = document.getElementById('column-dropdown-menu');
            if (!menu || !table) return;

            const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
            let html = `<li class="dropdown-item column-dropdown-span-all">
                <a href="#" id="show-all-columns-btn" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-eye"></i> Show All
                </a>
            </li>
            <li class="column-dropdown-span-all"><hr class="dropdown-divider"></li>`;

            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                const field = def.field;
                const title = def.title;
                if (!field || field === '_select' || !title) return;

                const isVisible = map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible();
                const label = String(title).replace(/<[^>]*>/g, '');
                html += `<li class="dropdown-item">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                        ${label}
                    </label>
                </li>`;
            });

            menu.innerHTML = html;
        }

        function saveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getDefinition().field;
                if (field && field !== '_select') {
                    visibility[field] = col.isVisible();
                }
            });

            fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility
                })
            }).catch(err => console.error('Error saving column visibility:', err));
        }

        function applyColumnVisibilityFromServer() {
            return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            })
                .then(res => res.json())
                .then(savedVisibility => {
                    const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                    if (Object.keys(map).length > 0) {
                        table.getColumns().forEach(col => {
                            const field = col.getDefinition().field;
                            if (field && map.hasOwnProperty(field)) {
                                if (map[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                    }
                    buildColumnDropdown(map);
                })
                .catch(err => {
                    console.error('Error applying column visibility:', err);
                    buildColumnDropdown();
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
            }, 100);
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.classList.contains('column-toggle') || e.target.type === 'checkbox') {
                const field = e.target.dataset.field || e.target.getAttribute('data-field');
                const col = table.getColumn(field);
                if (col) {
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    saveColumnVisibilityToServer();
                }
            }
        });

        // Show All Columns (inside Columns dropdown)
        $(document).on('click', '#show-all-columns-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            table.getColumns().forEach(col => {
                if (col.getField() !== '_select') {
                    col.show();
                }
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export CSV button
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible() && col.getField() !== '_select');
            
            // Get headers
            const headers = visibleColumns.map(col => {
                let title = col.getDefinition().title || col.getField();
                // Remove HTML tags from header
                return title.replace(/<[^>]*>/g, '');
            });
            exportData.push(headers);
            
            // Get filtered data (all visible rows)
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    // Clean up values
                    if (value === null || value === undefined) {
                        value = '';
                    } else if (typeof value === 'number') {
                        value = parseFloat(value.toFixed(2));
                    } else if (typeof value === 'string') {
                        // Remove HTML tags
                        value = value.replace(/<[^>]*>/g, '').trim();
                    }
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            // Create CSV
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'reverb_pricing_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection

