@extends('layouts.vertical', ['title' => 'Shopify B2C - Analytics', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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

        /* Sku Link LMP (same as Amazon / Newegg / Shein) */
        .tabulator .tabulator-cell.linked-sku-col .linked-sku-badge:hover {
            background-color: #cffafe !important;
        }
        .linked-sku-badge-wrap {
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        .linked-sku-badge-wrap .sku-link-lmp-remove {
            font-size: 0.55rem;
            opacity: 0.65;
            padding: 0;
            margin-left: 2px;
        }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover {
            opacity: 1;
        }
        .sku-link-lmp-suggestion-item {
            cursor: pointer;
        }
        .sku-link-lmp-suggestion-item .form-check-input {
            pointer-events: none;
        }
        .sku-link-lmp-selected-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 12px;
            margin: 2px;
        }
        .sku-link-lmp-selected-chip button {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            font-size: 14px;
            color: #64748b;
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
        /* Match /amazon-tabulator-view: wrap + auto-width selects so sidebar open doesn't clip labels.
           Keep toolbar z-index BELOW .leftside-menu (1000) — high z-index was painting filters over the sidebar. */
        .shopifyb2c-sprice-amz-lbl {
            color: #0d6efd;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }
        .shopify-b2c-toolbar {
            position: relative;
            z-index: auto;
            overflow: visible !important;
            flex-wrap: wrap !important;
            gap: 8px 10px !important;
        }
        .shopify-b2c-toolbar .form-select {
            width: auto !important;
            max-width: 130px;
            padding-right: 1.35rem !important;
            padding-left: 0.5rem !important;
            background-position: right 0.35rem center !important;
        }
        .shopify-b2c-page .card,
        .shopify-b2c-page .card-body {
            overflow: visible;
        }
        .shopify-b2c-page .card-body.shopify-b2c-controls {
            display: flex;
            flex-direction: column;
        }
        .shopify-b2c-toolbar .dropdown,
        .shopify-b2c-toolbar .btn-group,
        .shopify-b2c-toolbar .manual-dropdown-container {
            position: relative;
            z-index: 2;
        }
        .shopify-b2c-toolbar .dropdown-menu,
        .manual-dropdown-container .dropdown-menu {
            z-index: 20 !important;
        }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 20;
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
        #lmpModal .lmp-modal-sp-box,
        #lmpModal .lmp-sp-col-th,
        #lmpModal .lmp-sp-cell {
            display: none !important;
        }
        #lmpModal .lmp-add-one-line {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-end;
            gap: 8px;
        }
        #lmpModal .lmp-add-one-line .lmp-field {
            flex: 1 1 0;
            min-width: 0;
        }
        #lmpModal .lmp-add-one-line .lmp-field-sku { flex: 0 0 130px; }
        #lmpModal .lmp-add-one-line .lmp-field-price { flex: 0 0 90px; }
        #lmpModal .lmp-add-one-line .lmp-field-actions {
            display: flex;
            gap: 4px;
            flex: 0 0 auto;
        }
        #lmpModal .lmp-add-one-line .form-label {
            font-size: 11px;
            margin-bottom: 2px;
        }
        #lmpModal .lmp-add-one-line .form-control {
            height: 32px;
            font-size: 12px;
        }
        #lmpModal .lmp-add-one-line .btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        /* Badges above filters (Amazon order: -1) */
        .shopify-b2c-page #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        .shopify-b2c-page #summary-stats .summary-badges-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px !important;
        }
        .shopify-b2c-page #summary-stats .summary-badges-row .badge {
            font-size: 0.85rem !important;
            padding: 0.35rem 0.55rem !important;
            white-space: nowrap;
        }
        .shopify-b2c-page #summary-stats .summary-trend-dot {
            display: inline-block;
            width: 6px !important;
            height: 6px !important;
            margin-left: 0 !important;
            margin-right: 0.22rem !important;
            border-radius: 50%;
            flex-shrink: 0;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
            vertical-align: 0.08em;
        }
        .shopify-b2c-page #summary-stats .summary-trend-dot:hover {
            transform: scale(1.35);
        }
        .shopify-b2c-page #summary-stats .summary-trend-dot.up { background: #22c55e; }
        .shopify-b2c-page #summary-stats .summary-trend-dot.down { background: #ef4444; }
        .shopify-b2c-page #summary-stats .summary-trend-dot.flat,
        .shopify-b2c-page #summary-stats .summary-trend-dot.none { background: #9ca3af; }
        #shopifyB2cMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #shopifyB2cMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #shopifyB2cMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        .shopify-b2c-page #discount-input-container {
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
        }

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

        /* Dense body rows — same 36px as /ebay-tabulator-view */
        #reverb-table .tabulator-row {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        #reverb-table .tabulator-row .tabulator-cell {
            font-size: 13px !important;
            line-height: 1.2 !important;
            height: 36px !important;
            max-height: 36px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            vertical-align: middle !important;
        }
        #reverb-table .tabulator-row .tabulator-cell span,
        #reverb-table .tabulator-row .tabulator-cell a,
        #reverb-table .tabulator-row .tabulator-cell div,
        #reverb-table .tabulator-row .tabulator-cell button,
        #reverb-table .tabulator-row .tabulator-cell label,
        #reverb-table .tabulator-row .tabulator-cell input:not([type="checkbox"]):not([type="radio"]),
        #reverb-table .tabulator-row .tabulator-cell select,
        #reverb-table .tabulator-row .tabulator-cell i {
            font-size: 13px !important;
        }
        #reverb-table .tabulator-row .tabulator-cell img.hover-thumb {
            width: 28px !important;
            height: 28px !important;
            max-width: 28px !important;
            max-height: 28px !important;
            object-fit: cover !important;
            display: block !important;
            flex-shrink: 0 !important;
        }
        #reverb-table .tabulator-row .tabulator-cell > div {
            flex-wrap: nowrap !important;
            max-width: 100%;
            overflow: hidden;
        }
        #reverb-table .tabulator-cell.linked-sku-col,
        #reverb-table .tabulator-cell.linked-sku-col * {
            white-space: nowrap !important;
        }
        #reverb-table .tabulator-cell.linked-sku-col {
            overflow: hidden !important;
        }
        #reverb-table .tabulator-cell.linked-sku-col > div {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            height: 32px !important;
            max-height: 32px !important;
            overflow: hidden !important;
        }
        #reverb-table .tabulator-cell.linked-sku-col .badge,
        #reverb-table .tabulator-cell.linked-sku-col .linked-sku-badge-wrap {
            display: inline-flex !important;
            flex-shrink: 0 !important;
            margin: 0 4px 0 0 !important;
            max-height: 22px !important;
            line-height: 1 !important;
            font-size: 11px !important;
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
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'shopify_b2c'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'shopify_b2c'])
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'css'])
    </style>
    @include('partials.lazy-chart-js')
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify B2C - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>

    {{-- Sku Link LMP Modal (shared sku.link.lmp.* routes — same as Amazon) --}}
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skuLinkLmpModalLabel">Sku Link LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Link one or more SKUs to <strong id="sku-link-lmp-source"></strong>. All linked SKUs will show each other's LMP.</p>
                    <label for="sku-link-lmp-input" class="form-label mb-1">Search SKU to link</label>
                    <input type="text" id="sku-link-lmp-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                    <div id="sku-link-lmp-suggestions" class="list-group mt-2 d-none" style="max-height: 220px; overflow-y: auto;"></div>
                    <div id="sku-link-lmp-selected-wrap" class="mt-2 d-none">
                        <div class="small text-muted mb-1">Selected to link (<span id="sku-link-lmp-selected-count">0</span>):</div>
                        <div id="sku-link-lmp-selected-skus" class="d-flex flex-wrap"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sku-link-lmp-save-btn">
                        <i class="fas fa-link"></i> <span id="sku-link-lmp-save-btn-label">Link SKU(s)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Google LMP Competitors Modal (data from /repricer/google-search → google_sku_competitors) --}}
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" data-skip-lmp-sp="1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Google LMP Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <a href="#" id="lmpOpenGoogleSearch" class="btn btn-sm btn-outline-light ms-auto me-2" target="_blank" rel="noopener">
                        <i class="fa fa-search"></i> Open Google Search
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-success">
                        <div class="card-body py-2">
                            <form id="addGoogleLmpForm" class="lmp-add-one-line">
                                <div class="lmp-field lmp-field-sku">
                                    <label class="form-label mb-0" for="addLmpSku">SKU</label>
                                    <input type="text" class="form-control form-control-sm" id="addLmpSku" readonly>
                                </div>
                                <div class="lmp-field">
                                    <label class="form-label mb-0" for="addLmpProductId">Product ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="addLmpProductId" placeholder="Google product id" required>
                                </div>
                                <div class="lmp-field">
                                    <label class="form-label mb-0" for="addLmpSource">Source</label>
                                    <input type="text" class="form-control form-control-sm" id="addLmpSource" placeholder="e.g. Walmart">
                                </div>
                                <div class="lmp-field lmp-field-price">
                                    <label class="form-label mb-0" for="addLmpPrice">Price <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="addLmpPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="lmp-field">
                                    <label class="form-label mb-0" for="addLmpTitle">Title</label>
                                    <input type="text" class="form-control form-control-sm" id="addLmpTitle" placeholder="Product title">
                                </div>
                                <div class="lmp-field">
                                    <label class="form-label mb-0" for="addLmpLink">Link</label>
                                    <input type="url" class="form-control form-control-sm" id="addLmpLink" placeholder="https://...">
                                </div>
                                <div class="lmp-field-actions">
                                    <button type="submit" class="btn btn-success btn-sm" title="Add competitor">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                    <button type="reset" class="btn btn-secondary btn-sm" title="Clear">
                                        <i class="fa fa-undo"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="lmpDataList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="shopify-b2c-page">
    <div class="row">
        <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body py-2 shopify-b2c-controls">
                {{-- Filter bar (Amazon-style flex-wrap + auto-width selects) --}}
                <div class="d-flex align-items-center flex-wrap shopify-b2c-toolbar" id="shopify-b2c-filter-bar">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 180px;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 180px;">

                    <select id="inventory-filter" class="form-select form-select-sm" title="Inventory filter">
                        <option value="all">INV</option>
                        <option value="zero">Zero</option>
                        <option value="more" selected>More</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" title="REQ / NR filter">
                        <option value="all">ALL</option>
                        <option value="REQ" selected>REQ</option>
                        <option value="NR">NR</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm" title="GPFT% filter">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50plus">Above 50%</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm"
                        title="CVR = B2C L30 ÷ Views">
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
                    <select id="sold-filter" class="form-select form-select-sm" title="Filter by B2B L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm">
                        <option value="all">GROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-60">40–60%</option>
                        <option value="60-80">60–80%</option>
                        <option value="80-100">80–100%</option>
                        <option value="gt100">100%+</option>
                    </select>

                    {{-- Row type filter (All Rows / Parents / SKUs) – same as Amazon tabulator --}}
                    <select id="parent-filter" class="form-select form-select-sm" title="Filter by row type">
                        <option value="all">All Rows</option>
                        <option value="parents">Parents</option>
                        <option value="skus" selected>SKUs</option>
                    </select>

                    <!-- DIL Filter — Amz slabs (Red <25 / Green 25-50 / Pink 50%+) -->
                    <div class="dropdown manual-dropdown-container">
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
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside"
                            data-bs-display="static" aria-expanded="false"
                            title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu column-dropdown-multicol" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>

                    <button id="export-btn" class="btn btn-sm btn-dark" title="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>

                    {{-- Push SPRICE to Shopify B2C for selected rows (same API as /amazon-tabulator-view) --}}
                    <button type="button" id="push-shopify-prices-btn" class="btn btn-sm btn-success"
                        title="Push SPRICE to Shopify for selected SKUs">
                        <i class="fas fa-paper-plane"></i> Push
                    </button>

                    {{-- Sprc Dil (same Dil → Target GROI as /tiktok-2-pricing) + CVR Disc --}}
                    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'buttons', 'ebaySprcDilChannel' => 'shopify_b2c'])
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'shopify_b2c'])

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = 0.95 for Shopify B2C) --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.95 on every selected row (accounts for Shopify B2C 95% take-home)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 90px;"
                            title="Target ROI% applied to all selected rows">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.95 for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (0.95 − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 90px;"
                            title="Target GPFT% applied to all selected rows. Must be less than the Shopify B2C take-home margin (< 95%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (0.95 − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>

                <!-- Summary Stats (order:-1 → shown above filters, same as Amz) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2 summary-badges-row">
                        <span class="badge bg-success fs-6 p-2 d-none shopifyb2c-badge-chart" id="total-pft-amt-badge" data-metric="total_pft" data-format="money" data-live-value="{{ (float) ($shopifyDirectTotalPft ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;" title="Click for rolling history."><span class="summary-trend-dot none" data-metric="total_pft" title="Rolling history"></span>PFT: $0</span>
                        {{-- Sales is the L30 net-sales total from the actual /shopify page
                             (shopify_raw_orders with marketplace exclusions). Server-rendered so it
                             always matches /shopify and the eBay row pattern on /all-marketplace-master.
                             Page filters do not narrow this number — it's the page-level reference. --}}
                        <span class="badge bg-primary fs-6 p-2 shopifyb2c-badge-chart" id="total-sales-amt-badge"
                              data-metric="total_sales" data-format="money" data-live-value="{{ (float) ($shopifyDirectL30Sales ?? 0) }}"
                              style="color: black; font-weight: bold; cursor:pointer;"
                              title="L30 Net Sales from shopify_raw_orders (matches /shopify and /all-marketplace-master). Click for rolling history."><span class="summary-trend-dot none" data-metric="total_sales" title="Rolling history"></span>Sales: ${{ number_format((float) ($shopifyDirectL30Sales ?? 0), 0) }}</span>
                        <span class="badge bg-secondary fs-6 p-2 shopifyb2c-badge-chart" id="total-orders-badge"
                              data-metric="total_orders" data-format="number" data-live-value="{{ (int) ($shopifyDirectL30Orders ?? 0) }}"
                              style="color: white; font-weight: bold; cursor:pointer;"
                              title="L30 distinct orders from shopify_raw_orders. Click for rolling history."><span class="summary-trend-dot none" data-metric="total_orders" title="Rolling history"></span>Orders: {{ number_format((int) ($shopifyDirectL30Orders ?? 0)) }}</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart" id="total-qty-badge"
                              data-metric="total_qty" data-format="number" data-live-value="{{ (int) ($shopifyDirectL30Qty ?? 0) }}"
                              style="background-color: #6f42c1; color: white; font-weight: bold; cursor:pointer;"
                              title="L30 units sold from shopify_raw_orders. Click for rolling history."><span class="summary-trend-dot none" data-metric="total_qty" title="Rolling history"></span>Qty: {{ number_format((int) ($shopifyDirectL30Qty ?? 0)) }}</span>
                        <span class="badge bg-info fs-6 p-2 shopifyb2c-badge-chart" id="avg-gpft-badge" data-metric="gpft_percent" data-format="pct" data-live-value="{{ (float) ($shopifyDirectGpftPct ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;" title="GPFT%. Click for rolling history."><span class="summary-trend-dot none" data-metric="gpft_percent" title="Rolling history"></span>GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 d-none shopifyb2c-badge-chart" id="avg-price-badge" data-metric="avg_price" data-format="money" data-live-value="0" style="color: black; font-weight: bold; cursor:pointer;"><span class="summary-trend-dot none" data-metric="avg_price" title="Rolling history"></span>Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none shopifyb2c-badge-chart" id="total-inv-badge" data-metric="total_inv" data-format="number" data-live-value="0" style="color: black; font-weight: bold; cursor:pointer;"><span class="summary-trend-dot none" data-metric="total_inv" title="Rolling history"></span>INV: 0</span>
                        <span class="badge bg-success fs-6 p-2 shopifyb2c-badge-chart" id="total-l30-badge" data-metric="total_l30" data-format="number" data-live-value="0" style="color: black; font-weight: bold; cursor:pointer;" title="OV L30. Click for rolling history."><span class="summary-trend-dot none" data-metric="total_l30" title="Rolling history"></span>L30: 0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart" id="total-views-badge" data-metric="total_views" data-format="number" data-live-value="0" style="background-color: #0d6efd; color: white; font-weight: bold; cursor:pointer;" title="Sum of L30 product page views (sessions). Click for rolling history."><span class="summary-trend-dot none" data-metric="total_views" title="Rolling history"></span>Views: 0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart" id="avg-cvr-badge" data-metric="cvr_percent" data-format="pct" data-live-value="0" style="background-color: #20c997; color: #000; font-weight: bold; cursor:pointer;" title="Overall CVR = Qty ÷ Views. Click for rolling history."><span class="summary-trend-dot none" data-metric="cvr_percent" title="Rolling history"></span>CVR: 0.0%</span>
                        <span class="badge bg-info fs-6 p-2 shopifyb2c-badge-chart" id="total-b2b-l30-badge" data-metric="total_b2b_l30" data-format="number" data-live-value="0" style="color: black; font-weight: bold; cursor:pointer;" title="B2C L30 units. Click for rolling history."><span class="summary-trend-dot none" data-metric="total_b2b_l30" title="Rolling history"></span>B2B: 0</span>
                        <span class="badge bg-danger fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="zero-sold-count-badge" data-metric="zero_sold_count" data-invert="1" data-format="number" data-live-value="0" style="color: white; font-weight: bold; cursor: pointer;" title="Click badge to filter B2B L30 = 0. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="zero_sold_count" title="Rolling history"></span>0 Sold: 0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="shopifyb2c-blue-triangle-badge"
                            data-metric="blue_triangle_count" data-invert="1" data-format="number" data-live-value="0"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click badge to filter. Click dot for rolling history.">
                            <span class="summary-trend-dot none" data-metric="blue_triangle_count" title="Rolling history"></span><i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="shopifyb2c-purple-triangle-badge"
                            data-metric="purple_triangle_count" data-invert="1" data-format="number" data-live-value="0"
                            style="background-color:#6f42c1;color:#fff;font-weight:700;cursor:pointer;"
                            title="Amz: S PRC was below A Price and was raised to Amz. Click badge to filter. Click dot for rolling history.">
                            <span class="summary-trend-dot none" data-metric="purple_triangle_count" title="Rolling history"></span>Amz 0</span>
                        @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'shopifyb2c-lmp-missing-badge', 'lmpChannelKey' => 'shopifyb2c'])
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'shopifyb2c-price-gt-lmp-badge', 'pglChannelKey' => 'shopifyb2c', 'pglPriceField' => 'Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'shopifyb2c-price-lt80-lmp-badge', 'pltChannelKey' => 'shopifyb2c', 'pltPriceField' => 'Price'])
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="more-sold-count-badge" data-metric="sold_count" data-format="number" data-live-value="0" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click badge to filter B2B L30 &gt; 0. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="sold_count" title="Rolling history"></span>&gt;0 Sold: 0</span>
                        <span class="badge bg-info fs-6 p-2 d-none shopifyb2c-badge-chart" id="total-cogs-badge" data-metric="total_cogs" data-format="money" data-live-value="{{ (float) ($shopifyDirectTotalCogs ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;"><span class="summary-trend-dot none" data-metric="total_cogs" title="Rolling history"></span>COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2 shopifyb2c-badge-chart" id="roi-percent-badge" data-metric="groi_percent" data-format="pct" data-live-value="{{ (float) ($shopifyDirectGroiPct ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;" title="GROI% = Σ PFT ÷ Σ COGS × 100. Click for rolling history."><span class="summary-trend-dot none" data-metric="groi_percent" title="Rolling history"></span>GROI: 0%</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart" id="nroi-percent-badge" data-metric="nroi_percent" data-format="pct" data-live-value="{{ (float) ($shopifyDirectNroiPct ?? 0) }}" style="background-color: #e83e8c; color: white; font-weight: bold; cursor:pointer;" title="NROI%. Click for rolling history."><span class="summary-trend-dot none" data-metric="nroi_percent" title="Rolling history"></span>NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="less-amz-badge" data-metric="less_amz_count" data-invert="1" data-format="number" data-live-value="0" style="color: white; font-weight: bold; cursor: pointer;" title="Click badge to filter prices less than Amz. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="less_amz_count" title="Rolling history"></span>&lt; Amz: 0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="more-amz-badge" data-metric="more_amz_count" data-format="number" data-live-value="0" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click badge to filter prices greater than Amz. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="more_amz_count" title="Rolling history"></span>&gt; Amz: 0</span>
                        <span class="badge bg-danger fs-6 p-2 shopifyb2c-badge-chart shopifyb2c-badge-filter" id="missing-count-badge" data-metric="missing_count" data-invert="1" data-format="number" data-live-value="0" style="color: white; font-weight: bold; cursor: pointer;" title="Click badge to filter missing SKUs. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="missing_count" title="Rolling history"></span>Miss: 0</span>
                        <span class="badge bg-danger fs-6 p-2 shopifyb2c-badge-chart" id="total-tcos-badge" data-metric="tcos_percent" data-format="pct" data-invert="1" data-live-value="{{ (float) ($shopifyDirectTcosPct ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;" title="Ads%. Lower is better. Click for rolling history."><span class="summary-trend-dot none" data-metric="tcos_percent" title="Rolling history"></span>Ads: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 shopifyb2c-badge-chart" id="total-spend-badge" data-metric="total_spend" data-format="money" data-live-value="{{ (float) ($shopifyDirectTotalSpend ?? 0) }}" style="color: black; font-weight: bold; cursor:pointer;" title="Ad spend. Click for rolling history."><span class="summary-trend-dot none" data-metric="total_spend" title="Rolling history"></span>Spend: $0</span>
                        <span class="badge fs-6 p-2 shopifyb2c-badge-chart" id="avg-npft-badge" data-metric="npft_percent" data-format="pct" data-live-value="{{ (float) ($shopifyDirectNpftPct ?? 0) }}" style="background-color: #fd7e14; color: white; font-weight: bold; cursor:pointer;" title="NPFT%. Click for rolling history."><span class="summary-trend-dot none" data-metric="npft_percent" title="Rolling history"></span>NPFT: 0%</span>
                    </div>
                </div>

                {{-- Always visible (not hidden until selection) — same actions as the old overlay bar --}}
                <div id="discount-input-container" class="d-flex align-items-center gap-2 flex-wrap">
                    <span id="selected-skus-count" class="fw-bold">0 SKUs selected</span>
                    <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                    <span id="discount-type-select-wrap">
                    <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                        <option value="percentage">Percentage</option>
                        <option value="value">Value ($)</option>
                    </select>
                    </span>
                    <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                        placeholder="Enter %" step="0.01" style="width: 100px;">
                    <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply Decrease</button>
                    <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-copy"></i> Sugg Amz Prc
                    </button>
                    <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                        <i class="fas fa-eraser"></i> Clear SPRICE
                    </button>
                    <button type="button" id="push-selected-shopify-btn" class="btn btn-success btn-sm"
                        title="Push SPRICE to Shopify for selected SKUs">
                        <i class="fas fa-paper-plane"></i> Push
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="reverb-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="reverb-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
        </div>
    </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'shopify_b2c'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => 'shopify_b2c'])

    <div class="modal fade p-0" id="shopifyB2cMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="shopifyB2cChartModalTitle">Shopify B2C — Metric trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="shopifyB2cChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="shopifyB2cChartContainer" style="height: 38vh; display: none; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="shopifyB2cMetricChart"></canvas>
                        </div>
                        <div id="shopifyB2cChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="shopifyB2cChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="shopifyB2cChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="shopifyB2cChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="shopifyB2cChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="shopifyB2cChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0" id="shopifyB2cChartNoDataMsg">No daily snapshots yet. History builds automatically from this page and the daily cron.</p>
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
    const TABULATOR_COLUMN_VISIBILITY_URL = @json(route('tabulator.column.visibility.get'));
    const SHOPIFY_B2C_DATA_URL = @json(route('shopify.b2c.data.json'));
    const SHOPIFY_B2C_BADGE_PREV_URL = @json(route('shopify.b2c.badge.prev.day'));
    const SHOPIFY_B2C_BADGE_CHART_URL = @json(route('shopify.b2c.badge.chart.data'));
    const SHOPIFY_B2C_BADGE_SAVE_URL = @json(route('shopify.b2c.badge.stats.save'));
    const SHOPIFY_B2C_LISTED_LIVE_URL = @json(route('shopify.b2c.update.listed.live'));
    const GOOGLE_LMP_DATA_URL = @json(route('google.lmp.data'));
    const GOOGLE_LMP_ADD_URL = @json(route('google.lmp.add'));
    const GOOGLE_LMP_DELETE_URL = @json(route('google.lmp.delete'));
    const GOOGLE_SEARCH_URL = @json(route('repricer.google-search.index'));
    const PUSH_SHOPIFY_B2C_PRICE_URL = @json(route('push.shopify.b2c.price'));
    const PULL_SHOPIFY_B2C_PRICE_URL = @json(route('channel.push-sprice.pull', ['channel' => 'shopify_b2c']));
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

    const shopifyB2cBadgeMetricLabels = {
        total_sales: 'Sales', total_orders: 'Orders', total_qty: 'Qty', total_pft: 'PFT',
        total_cogs: 'COGS', total_spend: 'Spend', gpft_percent: 'GPFT%', groi_percent: 'GROI%',
        nroi_percent: 'NROI%', npft_percent: 'NPFT%', tcos_percent: 'Ads%',
        total_l30: 'L30', total_views: 'Views', cvr_percent: 'CVR%', total_b2b_l30: 'B2C L30',
        zero_sold_count: '0 Sold', sold_count: '> 0 Sold', missing_count: 'Miss',
        less_amz_count: '< Amz', more_amz_count: '> Amz',
        blue_triangle_count: 'S PRC ≠ Price', purple_triangle_count: 'S PRC raised to Amz',
        lmp_missing_count: 'LMP M.', prc_gt_lmp_count: 'Price > LMP', price_lt80_lmp_count: 'Price < 80% LMP',
        avg_price: 'Price', total_inv: 'INV'
    };
    const shopifyB2cBadgeInvertMetrics = {
        tcos_percent: true, zero_sold_count: true, missing_count: true, less_amz_count: true,
        blue_triangle_count: true, purple_triangle_count: true, lmp_missing_count: true,
        prc_gt_lmp_count: true, price_lt80_lmp_count: true
    };
    const shopifyB2cFilterBadgeIds = {
        'zero-sold-count-badge': 1, 'more-sold-count-badge': 1,
        'shopifyb2c-blue-triangle-badge': 1, 'shopifyb2c-purple-triangle-badge': 1,
        'shopifyb2c-lmp-missing-badge': 1, 'shopifyb2c-price-gt-lmp-badge': 1,
        'shopifyb2c-price-lt80-lmp-badge': 1, 'less-amz-badge': 1, 'more-amz-badge': 1,
        'missing-count-badge': 1
    };
    let shopifyB2cChartInstance = null;
    let shopifyB2cChartAjax = null;
    let shopifyB2cChartDays = 30;
    let shopifyB2cChartMetricKey = '';
    let shopifyB2cBadgePrevDay = null;
    let shopifyB2cBadgePrevDayLoaded = false;
    const shopifyB2cChartCache = {};

    function shopifyB2cMetricFormat(metricKey) {
        const $b = $('#summary-stats [data-metric="' + metricKey + '"]').not('.summary-trend-dot').first();
        return ($b.data('format') || 'number').toString();
    }
    function shopifyB2cFmtChartVal(v, metricKey) {
        const n = Number(v);
        if (!isFinite(n)) return '—';
        const key = (metricKey || '').toString();
        const fmt = key ? shopifyB2cMetricFormat(key) : 'number';
        const isPct = fmt === 'pct' || /percent|cvr|pct/i.test(key);
        if (fmt === 'money') return '$' + Math.round(n).toLocaleString('en-US');
        if (isPct) return n.toFixed(1) + '%';
        return Math.round(n).toLocaleString('en-US');
    }
    function shopifyB2cTodayPtDate() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
        } catch (e) {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
    }
    function shopifyB2cYmd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function shopifyB2cChartDateLabel(ymd) {
        const d = new Date((ymd || shopifyB2cTodayPtDate()) + 'T12:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', timeZone: 'UTC' });
    }
    function shopifyB2cFillEveryDate(rows, daysOverride) {
        const days = daysOverride != null ? (parseInt(daysOverride, 10) || 0) : (parseInt(shopifyB2cChartDays, 10) || 0);
        const src = Array.isArray(rows) ? rows.slice() : [];
        const byDate = {};
        src.forEach(function(r) {
            const key = r.full_date || '';
            if (key && /^\d{4}-\d{2}-\d{2}$/.test(key) && r.value != null && isFinite(Number(r.value))) {
                byDate[key] = r;
            }
        });
        let keys = Object.keys(byDate).sort();
        if (!keys.length) return src;
        if (days > 0) {
            const end = shopifyB2cTodayPtDate();
            const startD = new Date(end + 'T12:00:00');
            startD.setDate(startD.getDate() - (days - 1));
            const startKey = shopifyB2cYmd(startD);
            const inWindow = keys.filter(function(k) { return k >= startKey && k <= end; });
            keys = inWindow.length ? inWindow : keys;
        }
        return keys.map(function(k) {
            return { date: shopifyB2cChartDateLabel(k), full_date: k, value: Number(byDate[k].value) };
        });
    }
    function shopifyB2cOverlayLiveBadgeValue(mapped, metricKey) {
        const rows = Array.isArray(mapped) ? mapped.slice() : [];
        const $b = $('#summary-stats [data-metric="' + metricKey + '"]').not('.summary-trend-dot').first();
        let live = parseFloat($b.attr('data-live-value'));
        if (!isFinite(live)) live = parseFloat($b.data('live-value'));
        if (!isFinite(live)) return shopifyB2cFillEveryDate(rows);
        const asOf = shopifyB2cTodayPtDate();
        const last = rows.length ? rows[rows.length - 1] : null;
        if (last && last.full_date === asOf) {
            last.value = live;
        } else {
            rows.push({ date: shopifyB2cChartDateLabel(asOf), full_date: asOf, value: live });
        }
        return shopifyB2cFillEveryDate(rows);
    }
    function shopifyB2cTrendClass(curr, prev, invert) {
        if (!isFinite(curr) || !isFinite(prev)) return 'none';
        const diff = curr - prev;
        let cls = 'flat';
        if (diff > 0.05) cls = 'up';
        else if (diff < -0.05) cls = 'down';
        if (invert && cls === 'up') cls = 'down';
        else if (invert && cls === 'down') cls = 'up';
        return cls;
    }
    function applyShopifyB2cSummaryTrendDot(metricKey, currentVal) {
        const $dot = $('#summary-stats .summary-trend-dot[data-metric="' + metricKey + '"]');
        if (!$dot.length) return;
        const prev = shopifyB2cBadgePrevDay && shopifyB2cBadgePrevDay[metricKey];
        const invert = !!shopifyB2cBadgeInvertMetrics[metricKey];
        if (!isFinite(currentVal) || prev == null || !isFinite(prev)) {
            $dot.attr('class', 'summary-trend-dot none')
                .attr('title', 'Click for rolling history (no prior day yet)');
            return;
        }
        const cls = shopifyB2cTrendClass(currentVal, prev, invert);
        const tip = (cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same'))
            + ' vs prior day (' + shopifyB2cFmtChartVal(prev, metricKey)
            + ' → ' + shopifyB2cFmtChartVal(currentVal, metricKey) + '). Click for rolling history.';
        $dot.attr('class', 'summary-trend-dot ' + cls).attr('title', tip);
    }
    function syncShopifyB2cSummaryTrendDots() {
        $('#summary-stats [data-metric]').each(function() {
            if ($(this).hasClass('summary-trend-dot')) return;
            const metric = $(this).data('metric');
            if (!metric) return;
            let live = parseFloat($(this).attr('data-live-value'));
            if (!isFinite(live)) live = parseFloat($(this).data('live-value'));
            applyShopifyB2cSummaryTrendDot(metric, live);
        });
    }
    function loadShopifyB2cBadgePrevDay(done) {
        if (shopifyB2cBadgePrevDayLoaded) {
            syncShopifyB2cSummaryTrendDots();
            if (typeof done === 'function') done();
            return;
        }
        $.ajax({
            url: SHOPIFY_B2C_BADGE_PREV_URL,
            method: 'GET',
            success: function(resp) {
                shopifyB2cBadgePrevDayLoaded = true;
                shopifyB2cBadgePrevDay = (resp && resp.success && resp.metrics) ? resp.metrics : null;
                syncShopifyB2cSummaryTrendDots();
                if (typeof done === 'function') done();
            },
            error: function() {
                shopifyB2cBadgePrevDayLoaded = true;
                shopifyB2cBadgePrevDay = null;
                syncShopifyB2cSummaryTrendDots();
                if (typeof done === 'function') done();
            }
        });
    }
    function setShopifyB2cSummaryBadge($el, label, liveVal, asHtml) {
        if (!$el || !$el.length) return;
        const $dot = $el.find('.summary-trend-dot').first().detach();
        if (asHtml) $el.html(label);
        else $el.text(label);
        if ($dot.length) $el.prepend($dot);
        else {
            const metric = $el.attr('data-metric') || '';
            const $new = $('<span class="summary-trend-dot none" title="Rolling history"></span>');
            if (metric) $new.attr('data-metric', metric);
            $el.prepend($new);
        }
        if (liveVal != null && isFinite(liveVal)) {
            $el.attr('data-live-value', liveVal);
        }
    }
    function saveShopifyB2cBadgeStatsOnce() {
        if (window._shopifyB2cBadgeStatsSaved) return;
        const payload = { _token: $('meta[name="csrf-token"]').attr('content') };
        let n = 0;
        $('#summary-stats [data-metric]').each(function() {
            if ($(this).hasClass('summary-trend-dot')) return;
            const k = $(this).attr('data-metric');
            let v = parseFloat($(this).attr('data-live-value'));
            if (k && isFinite(v)) {
                payload[k] = v;
                n++;
            }
        });
        if (!n) return;
        window._shopifyB2cBadgeStatsSaved = true;
        $.post(SHOPIFY_B2C_BADGE_SAVE_URL, payload);
    }
    function openShopifyB2cChartModal() {
        const modalEl = document.getElementById('shopifyB2cMetricChartModal');
        if (!modalEl) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            $(modalEl).modal('show');
        }
    }
    function shopifyB2cPaintChartSeries(rows) {
        const series = shopifyB2cOverlayLiveBadgeValue(rows || [], shopifyB2cChartMetricKey);
        if (!series.length) {
            $('#shopifyB2cChartContainer').hide();
            $('#shopifyB2cChartNoData').show();
            return;
        }
        $('#shopifyB2cChartNoData').hide();
        $('#shopifyB2cChartLoading').hide();
        $('#shopifyB2cChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
        renderShopifyB2cMetricChart(series);
    }
    function showShopifyB2cMetricChart(metricKey) {
        shopifyB2cChartMetricKey = metricKey;
        shopifyB2cChartDays = 30;
        $('#shopifyB2cChartRangeSelect').val('30');
        const label = shopifyB2cBadgeMetricLabels[metricKey] || metricKey;
        $('#shopifyB2cChartModalTitle').text('Shopify B2C — ' + label + ' Rolling History');
        $('#shopifyB2cChartNoData').hide();
        $('#shopifyB2cChartLoading').show();
        if (typeof loadChartJs === 'function') loadChartJs();
        openShopifyB2cChartModal();
        const liveOnly = shopifyB2cOverlayLiveBadgeValue([], metricKey);
        if (liveOnly.length) {
            $('#shopifyB2cChartLoading').hide();
            $('#shopifyB2cChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
            renderShopifyB2cMetricChart(liveOnly);
        }
        loadShopifyB2cMetricChart();
    }
    function loadShopifyB2cMetricChart() {
        const cacheKey = shopifyB2cChartMetricKey + ':' + shopifyB2cChartDays;
        if (shopifyB2cChartCache[cacheKey]) {
            shopifyB2cPaintChartSeries(shopifyB2cChartCache[cacheKey]);
            return;
        }
        if (shopifyB2cChartAjax) shopifyB2cChartAjax.abort();
        if (!$('#shopifyB2cChartContainer').is(':visible')) {
            $('#shopifyB2cChartNoData').hide();
            $('#shopifyB2cChartLoading').show();
        }
        shopifyB2cChartAjax = $.ajax({
            url: SHOPIFY_B2C_BADGE_CHART_URL,
            method: 'GET',
            data: { metric: shopifyB2cChartMetricKey, days: shopifyB2cChartDays },
            success: function(resp) {
                shopifyB2cChartAjax = null;
                const rows = (resp && resp.success && resp.data) ? resp.data : [];
                shopifyB2cChartCache[cacheKey] = rows;
                shopifyB2cPaintChartSeries(rows);
            },
            error: function(xhr, status) {
                shopifyB2cChartAjax = null;
                if (status === 'abort') return;
                if ($('#shopifyB2cChartContainer').is(':visible')) return;
                $('#shopifyB2cChartLoading').hide();
                $('#shopifyB2cChartNoData').show();
            }
        });
    }
    function renderShopifyB2cMetricChart(data) {
        if (typeof Chart === 'undefined') {
            if (typeof loadChartJs === 'function') {
                loadChartJs().then(function() { renderShopifyB2cMetricChart(data); });
            }
            return;
        }
        const ctxEl = document.getElementById('shopifyB2cMetricChart');
        if (!ctxEl) return;
        const ctx = ctxEl.getContext('2d');
        if (shopifyB2cChartInstance) shopifyB2cChartInstance.destroy();
        const seenDates = {};
        data = (data || []).filter(function(d) {
            const k = d.full_date || d.date || '';
            if (!k || seenDates[k]) return false;
            seenDates[k] = true;
            return true;
        });
        const labels = data.map(function(d) { return d.date; });
        const values = data.map(function(d) { return d.value; });
        const dataMin = Math.min.apply(null, values);
        const dataMax = Math.max.apply(null, values);
        const sorted = values.slice().sort(function(a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin;
        let yMin, yMax;
        if (range < 1e-9) {
            const pad = Math.max(Math.abs(dataMax) * 0.2, 0.5);
            yMin = Math.max(0, dataMin - pad);
            yMax = dataMax + pad;
        } else {
            const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
            yMin = Math.max(0, dataMin - range * 0.12);
            yMax = dataMax + yPad;
        }
        $('#shopifyB2cChartHighest').text(shopifyB2cFmtChartVal(dataMax, shopifyB2cChartMetricKey));
        $('#shopifyB2cChartMedian').text(shopifyB2cFmtChartVal(median, shopifyB2cChartMetricKey));
        $('#shopifyB2cChartLowest').text(shopifyB2cFmtChartVal(dataMin, shopifyB2cChartMetricKey));
        const invert = !!shopifyB2cBadgeInvertMetrics[shopifyB2cChartMetricKey];
        const dotColors = values.map(function(v, i) {
            if (i === 0) return '#6c757d';
            const cls = shopifyB2cTrendClass(v, values[i - 1], invert);
            return cls === 'up' ? '#28a745' : (cls === 'down' ? '#dc3545' : '#6c757d');
        });
        shopifyB2cChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: 'rgba(32, 201, 151, 0.08)',
                    borderColor: '#20c997',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointBorderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                clip: false,
                layout: { padding: { top: 44, left: 8, right: 22, bottom: 28 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Value: ' + shopifyB2cFmtChartVal(context.raw, shopifyB2cChartMetricKey);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: { callback: function(v) { return shopifyB2cFmtChartVal(v, shopifyB2cChartMetricKey); } }
                    },
                    x: {
                        offset: true,
                        ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: labels.length > 45 ? 9 : 10, weight: '600' } }
                    }
                }
            }
        });
    }
    function bindShopifyB2cBadgeHistory() {
        $(document).on('click', '#summary-stats .summary-trend-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const m = $(this).data('metric') || $(this).closest('[data-metric]').data('metric');
            if (m) showShopifyB2cMetricChart(m);
        });
        $(document).on('click', '#summary-stats .shopifyb2c-badge-chart', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            if (shopifyB2cFilterBadgeIds[this.id]) return;
            const m = $(this).data('metric');
            if (m) showShopifyB2cMetricChart(m);
        });
        $('#shopifyB2cChartRangeSelect').on('change', function() {
            shopifyB2cChartDays = parseInt($(this).val(), 10) || 0;
            loadShopifyB2cMetricChart();
        });
    }

    /** Amazon-style parent summary row (SKU like "PARENT 10 FR"). */
    function isShopifyB2cParentRow(row) {
        if (!row) return false;
        if (row.is_parent_summary === true) return true;
        const sku = String(row['(Child) sku'] || '').toUpperCase();
        return sku.includes('PARENT');
    }

    function shopifyB2cIsAmzSuggApplied(data) {
        if (!data) return false;
        const f = data.AMZ_SUGG_APPLIED;
        return f === true || f === 1 || f === '1' || f === 'true';
    }

    /** S PRC to show / push. Sprc Dil wins (same as /tiktok-2-pricing), then CVR Disc / 0-sold / Std. Sugg Amz keeps A Price. */
    function shopifyB2cDisplayedSprice(data) {
        if (!data || isShopifyB2cParentRow(data)) return 0;
        const stored = parseFloat(data.SPRICE) || 0;
        if (shopifyB2cIsAmzSuggApplied(data) && stored > 0) {
            return Math.round(stored * 100) / 100;
        }
        if (typeof chPromoLiveSprice === 'function') {
            const calc = chPromoLiveSprice(data);
            if (calc > 0) return calc;
        }
        return stored > 0 ? Math.round(stored * 100) / 100 : 0;
    }

    function shopifyB2cAmzPrice(data) {
        return parseFloat(data && (data['A Price'] != null ? data['A Price'] : (data.a_price || data.amazon_price))) || 0;
    }

    /** Raise S PRC to A Price when it is below Amz; keep S PRC when it is above. */
    function shopifyB2cApplyAmzFloor(data, sprice) {
        const s = Math.round((parseFloat(sprice) || 0) * 100) / 100;
        const amz = shopifyB2cAmzPrice(data);
        if (s > 0 && amz > 0 && s < amz) return Math.round(amz * 100) / 100;
        return s > 0 ? s : 0;
    }

    /** Displayed S PRC after LMP cap, before the Amz floor. Sprc Dil uses the raw Dil $ so Amz still flags. */
    function shopifyB2cPriceBeforeAmzFloor(data) {
        if (!data || isShopifyB2cParentRow(data)) return 0;
        let value = 0;
        if (typeof ebayDilGroiMetaForRow === 'function') {
            const meta = ebayDilGroiMetaForRow(data);
            if (meta && (meta.rawSprc > 0 || meta.sprc > 0)) {
                value = Number(meta.rawSprc > 0 ? meta.rawSprc : meta.sprc) || 0;
            }
        }
        if (!(value > 0)) value = shopifyB2cDisplayedSprice(data);
        if (!(value > 0)) return 0;
        if (!shopifyB2cIsAmzSuggApplied(data) && window.SpriceLmpCap) {
            const cap = SpriceLmpCap.apply(data, value);
            if (cap && cap.shown > 0) value = cap.shown;
        }
        return Math.round(value * 100) / 100;
    }

    /** Cell / push value: LMP-capped S PRC, then floored up to A Price when below Amz. */
    function shopifyB2cShownSprice(data) {
        return shopifyB2cApplyAmzFloor(data, shopifyB2cPriceBeforeAmzFloor(data));
    }

    function shopifyB2cHasAmzFloor(data) {
        if (!data || isShopifyB2cParentRow(data)) return false;
        const before = shopifyB2cPriceBeforeAmzFloor(data);
        const amz = shopifyB2cAmzPrice(data);
        return before > 0 && amz > 0 && before < amz;
    }

    function shopifyB2cShowAmzLabel(data) {
        if (!data || isShopifyB2cParentRow(data)) return false;
        return shopifyB2cHasAmzFloor(data)
            || (shopifyB2cIsAmzSuggApplied(data) && shopifyB2cAmzPrice(data) > 0);
    }

    function shopifyB2cHasBlueTriangle(data) {
        if (isShopifyB2cParentRow(data)) return false;
        if (shopifyB2cShowAmzLabel(data)) return false;
        const sprice = shopifyB2cShownSprice(data);
        const price = parseFloat(data && data.Price) || 0;
        return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
    }

    /** Badge / filter: S PRC was below A Price and was raised to Amz. */
    function shopifyB2cHasPurpleTriangle(data) {
        return shopifyB2cHasAmzFloor(data);
    }

    function shopifyB2cAmzLabelHtml(data) {
        if (!shopifyB2cShowAmzLabel(data)) return '';
        const amz = shopifyB2cAmzPrice(data);
        const before = shopifyB2cPriceBeforeAmzFloor(data);
        const title = shopifyB2cHasAmzFloor(data)
            ? ('S PRC $' + before.toFixed(2) + ' &lt; A Price $' + amz.toFixed(2) + ' — raised to Amz')
            : ('S PRC set to A Price $' + amz.toFixed(2));
        return '<span class="shopifyb2c-sprice-amz-lbl" title="' + title + '">Amz</span>';
    }

    function syncShopifyB2cPurpleTriangleBadgeState() {
        $('#shopifyb2c-purple-triangle-badge').css({
            outline: purpleTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: purpleTriangleFilterActive ? '2px' : ''
        });
    }

    function syncShopifyB2cTriangleBadgeState() {
        $('#shopifyb2c-blue-triangle-badge').css({
            outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: blueTriangleFilterActive ? '2px' : ''
        });
        syncShopifyB2cPurpleTriangleBadgeState();
    }

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function shopifyB2cStdPrcChangeDotMeta(stdPrc, comparePrice) {
        const sp = parseFloat(stdPrc);
        const ap = parseFloat(comparePrice);
        if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
        const sp2 = sp.toFixed(2);
        const ap2 = ap.toFixed(2);
        if (parseFloat(sp2) < parseFloat(ap2)) {
            return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs Amz price' };
        }
        if (parseFloat(sp2) > parseFloat(ap2)) {
            return { kind: 'increase', color: '#28a745', title: 'Increase vs Amz price' };
        }
        return null;
    }

    function shopifyB2cStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = shopifyB2cStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    /** Apply STANDARD_PRICE to a SKU row + all Sku Link LMP siblings in the grid */
    function applyShopifyB2cStandardPriceToLinkedRows(sku, std, appliedSkus) {
        if (typeof table === 'undefined' || !table) return null;
        const target = String(sku || '').trim().toUpperCase();
        const appliedSet = new Set(
            (Array.isArray(appliedSkus) ? appliedSkus : [])
                .map(function(s) { return String(s || '').trim().toUpperCase(); })
                .filter(Boolean)
        );
        if (target) appliedSet.add(target);

        let primaryRow = null;
        (table.getRows('all') || table.getRows() || []).forEach(function(r) {
            const d = r.getData();
            if (!d || isShopifyB2cParentRow(d)) return;
            const rowSku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
            if (!rowSku) return;
            const rowKey = rowSku.toUpperCase();
            const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
            const inGroup = appliedSet.has(rowKey)
                || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                || (target && rowKey === target);
            if (!inGroup) return;
            r.update({ STANDARD_PRICE: std });
            if (typeof applyChannelSpriceFromStdChange === 'function') {
                applyChannelSpriceFromStdChange(r);
            }
            if (rowKey === target) primaryRow = r;
        });
        return primaryRow;
    }

    document.addEventListener('lmp-modal-sp-saved', function(e) {
        const detail = (e && e.detail) || {};
        const sku = detail.sku;
        const saved = parseFloat(detail.standard_price);
        if (!sku || !isFinite(saved) || saved <= 0) return;
        applyShopifyB2cStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

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

    /** S-metrics from the same S PRC the cell / push / Diff preview use. */
    function shopifyB2cComputeSpriceMetrics(data, sprice) {
        const s = parseFloat(sprice) || 0;
        const lp = parseFloat(data && data.LP_productmaster) || 0;
        const ship = parseFloat(data && data.Ship_productmaster) || 0;
        const ads = shopifyChannelAdsPct();
        const gross = (s * 0.95) - lp - ship;
        const sgpft = s > 0 ? (gross / s) * 100 : 0;
        return {
            SGPFT: sgpft,
            SNPFT: sgpft - ads,
            SROI: lp > 0 ? (gross / lp) * 100 : 0,
            SNROI: shopifyComputeSnroi(s, lp, ship, ads)
        };
    }

    /** Live Price metrics after a successful push (Price becomes S PRC). */
    function shopifyB2cComputeLivePriceMetrics(data, price) {
        const p = Math.round((parseFloat(price) || 0) * 100) / 100;
        const lp = parseFloat(data && data.LP_productmaster) || 0;
        const ship = parseFloat(data && data.Ship_productmaster) || 0;
        const qty = parseFloat(data && data['B2B L30']) || 0;
        const ads = shopifyChannelAdsPct();
        const gross = (p * 0.95) - lp - ship;
        const gpft = p > 0 ? (gross / p) * 100 : 0;
        return {
            Price: p,
            'GPFT%': gpft,
            'ROI%': lp > 0 ? (gross / lp) * 100 : 0,
            'NROI%': shopifyComputeNetRoi(p, lp, ship, ads),
            Profit: qty > 0 ? gross * qty : 0,
            'Sales L30': qty > 0 ? p * qty : 0
        };
    }

    function shopifyB2cFinalSprice(data, sprice) {
        let s = parseFloat(sprice) || 0;
        if (!(s > 0)) return 0;
        if (typeof chPromoFinalSpriceToSave === 'function') {
            s = chPromoFinalSpriceToSave(data, s);
        } else {
            s = shopifyB2cApplyAmzFloor(data, s);
        }
        return s > 0 ? Math.round(s * 100) / 100 : 0;
    }

    /** Amazon-style: leftover stored S PRC cannot win — write the visible rule onto the row, then save. */
    function shopifyB2cWriteRuleSpriceOnRow(row, opts) {
        opts = opts || {};
        if (!row || typeof row.getData !== 'function') return 0;
        const d = row.getData() || {};
        if (isShopifyB2cParentRow(d)) return 0;
        if (shopifyB2cIsAmzSuggApplied(d)) return 0;
        const shown = shopifyB2cShownSprice(d);
        if (!(shown > 0)) return 0;
        const stored = parseFloat(d.SPRICE) || 0;
        if (!opts.force && Math.abs(stored - shown) < 0.005) return 0;
        row.update({ SPRICE: 0, sprice: 0, has_custom_sprice: false, AMZ_SUGG_APPLIED: false });
        shopifyB2cApplySpriceMetricsToRow(row, shown);
        if (opts.persist === false) return shown;
        const sku = d['(Child) sku'] || d.sku;
        if (!sku) return shown;
        if (typeof chPromoPersistClearThenSprice === 'function') {
            chPromoPersistClearThenSprice(row, shown, true, { skip_push: true, sku: sku, row: row })
                .then(function(res) {
                    if (typeof chPromoApplySpriceSavePatch === 'function') {
                        chPromoApplySpriceSavePatch(row, shown, res, sku);
                    }
                })
                .catch(function() {
                    shopifyB2cApplySpriceMetricsToRow(row, shown);
                });
        } else {
            saveSpriceWithRetry(sku, shown, row);
        }
        return shown;
    }

    function shopifyB2cApplyRuleSpriceToAllRows(opts) {
        opts = opts || {};
        if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') return 0;
        let changed = 0;
        (table.getRows('all') || []).forEach(function(row) {
            if (shopifyB2cWriteRuleSpriceOnRow(row, opts)) changed++;
        });
        if (changed) {
            try { table.redraw(true); } catch (e) { /* ignore */ }
            if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
        }
        return changed;
    }
    window.shopifyB2cApplyRuleSpriceToAllRows = shopifyB2cApplyRuleSpriceToAllRows;

    function shopifyB2cSpriceEditor(cell, onRendered, success, cancel) {
        const data = cell.getRow().getData();
        const shown = shopifyB2cShownSprice(data);
        const input = document.createElement('input');
        input.type = 'number';
        input.min = '0';
        input.step = '0.01';
        input.value = shown > 0 ? shown.toFixed(2) : '';
        input.style.width = '100%';
        input.style.boxSizing = 'border-box';
        input.style.padding = '2px 4px';
        function commit() {
            const v = parseFloat(input.value);
            success(isFinite(v) && v > 0 ? Math.round(v * 100) / 100 : 0);
        }
        input.addEventListener('blur', commit);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancel();
            }
        });
        onRendered(function() {
            input.focus();
            input.select();
            if (shown > 0) input.value = shown.toFixed(2);
        });
        return input;
    }

    function shopifyB2cSpricePending(data) {
        const shown = shopifyB2cShownSprice(data);
        const price = parseFloat(data && data.Price) || 0;
        return shown > 0 && price > 0 && Math.round(shown * 100) !== Math.round(price * 100);
    }

    function shopifyB2cDiffComparePrice(data) {
        const shown = shopifyB2cShownSprice(data);
        if (shopifyB2cSpricePending(data)) return shown;
        const price = parseFloat(data && data.Price) || 0;
        return price > 0 ? price : shown;
    }

    function shopifyB2cApplySpriceMetricsToRow(row, sprice) {
        if (!row || typeof row.update !== 'function') return;
        const data = row.getData() || {};
        const s = shopifyB2cFinalSprice(data, sprice);
        const metrics = shopifyB2cComputeSpriceMetrics(data, s);
        row.update(Object.assign({
            SPRICE: s,
            has_custom_sprice: s > 0,
            AMZ_SUGG_APPLIED: !!data.AMZ_SUGG_APPLIED && s > 0
        }, metrics));
        try { row.reformat(); } catch (e) { /* ignore */ }
        if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
    }

    function shopifyB2cApplyLivePriceToRow(row, price, extra) {
        if (!row || typeof row.update !== 'function') return;
        const data = row.getData() || {};
        const patch = Object.assign(
            shopifyB2cComputeLivePriceMetrics(data, price),
            extra || {}
        );
        row.update(patch);
        try { row.reformat(); } catch (e) { /* ignore */ }
        if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
    }
    window.shopifyB2cApplyLivePriceToRow = shopifyB2cApplyLivePriceToRow;

    function shopifyB2cGroiColor(percent) {
        if (percent < 50) return '#a00211';
        if (percent >= 50 && percent < 75) return '#ffc107';
        if (percent >= 75 && percent <= 125) return '#28a745';
        return '#e83e8c';
    }
    function shopifyB2cGpftColor(percent) {
        if (percent < 10) return '#a00211';
        if (percent >= 10 && percent < 20) return '#3591dc';
        if (percent >= 20 && percent < 30) return '#ffc107';
        if (percent >= 30 && percent < 50) return '#28a745';
        return '#e83e8c';
    }

    let table = null;
    let allTableData = []; // Full dataset for ParentExpand
    let lmpMissingFilterActive = false;
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;
    let blueTriangleFilterActive = false;
    let purpleTriangleFilterActive = false;
    let decreaseModeActive = true;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    
    // Accepts showToast(message, type) or showToast(type, message) — Dil vs PRMT / CVR Disc
    // call type-first (same as /amazon-tabulator-view).
    function showToast(a, b) {
        var type, message;
        if (['success', 'error', 'info', 'warning', 'danger'].indexOf(String(a)) !== -1 && typeof b === 'string') {
            type = a;
            message = b;
        } else {
            message = a;
            type = b || 'info';
        }
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const bg = (type === 'error' || type === 'danger') ? 'danger'
            : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-' + bg + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '')
            + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // ── Sku Link LMP (shared sku.link.lmp.* routes — same as Amazon / Newegg) ──
    const linkedSkuAddUrl = @json(route('sku.link.lmp.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('sku.link.lmp.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('sku.link.lmp.linked-skus.remove'));
    const filteredSkusUrl = @json(route('sku.link.lmp.filtered-skus'));
    const skuLinkLmpCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    let linkedSkuModal = null;
    let linkedSkuModalRow = null;
    let linkedSkuModalSelectedSkus = new Set();
    let linkedSkuSuggestionTimer = null;
    let linkedSkuSuggestionRequestId = 0;

    function rowSkuForLinkLmp(rowData) {
        return String(rowData?.['(Child) sku'] || rowData?.sku || '').trim();
    }
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }
    function escapeHtmlAttr(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function shopifyB2cConnectedLink(url) {
        const u = String(url || '').trim();
        return /^https?:\/\//i.test(u) ? u : '';
    }

    function linkedLmpSkuFormatter(cell) {
        const row = cell.getRow().getData();
        if (isShopifyB2cParentRow(row)) return '';
        const rowSku = rowSkuForLinkLmp(row);
        let skus = row.linked_lmp_skus || [];
        if (typeof skus === 'string') { try { skus = JSON.parse(skus) || []; } catch (e) { skus = []; } }
        if (!Array.isArray(skus)) skus = [];
        if (!skus.length && rowSku) skus = [rowSku];
        const seen = new Set();
        skus = skus.filter(function (sku) {
            const norm = String(sku || '').trim().toUpperCase();
            if (!norm || seen.has(norm)) return false;
            seen.add(norm); return true;
        });
        const shown = skus.slice(0, 1);
        const extra = skus.length - shown.length;
        const badges = shown.length ? shown.map(function (sku) {
            const skuText = String(sku || '').trim();
            const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
            const removeBtn = isSelf ? '' : `<button type="button" class="btn-close sku-link-lmp-remove" data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove"></button>`;
            return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border"><span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}</span>`;
        }).join('') : '<span class="text-muted fst-italic">No SKUs</span>';
        const more = extra > 0
            ? `<span class="badge bg-secondary-subtle text-dark border" title="${skus.length} linked SKUs">+${extra}</span>`
            : '';
        return `<div class="linked-sku-one-line">${badges}${more}</div>`;
    }

    function applyAffectedLinkedSkuRows(affected) {
        if (table && Array.isArray(affected) && affected.length) {
            const bySku = {};
            affected.forEach(function (item) {
                if (item && item.sku) bySku[String(item.sku).trim().toUpperCase()] = item.linked_lmp_skus || [];
            });
            table.getRows().forEach(function (row) {
                const data = row.getData();
                const sku = rowSkuForLinkLmp(data).toUpperCase();
                if (!Object.prototype.hasOwnProperty.call(bySku, sku)) return;
                row.update({ linked_lmp_skus: bySku[sku] });
            });
            return;
        }
        if (table) table.replaceData();
    }

    function removeLinkedSkuFromRow(rowData, linkedSku) {
        const sku = rowSkuForLinkLmp(rowData);
        const target = String(linkedSku || '').trim();
        if (!sku || !target) return;
        if (!confirm(`Remove LMP link between "${sku}" and "${target}"?`)) return;
        fetch(linkedSkuRemoveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ sku: sku, linked_sku: target }),
        }).then(r => r.json()).then(function (response) {
            if (!response.success) throw new Error(response.message || 'Could not remove linked SKU.');
            applyAffectedLinkedSkuRows(response.affected);
        }).catch(function (err) { alert(err.message || 'Could not remove linked SKU.'); });
    }

    function updateLinkedSkuSelectedSummary() {
        const wrap = document.getElementById('sku-link-lmp-selected-wrap');
        const listEl = document.getElementById('sku-link-lmp-selected-skus');
        const countEl = document.getElementById('sku-link-lmp-selected-count');
        const saveLabel = document.getElementById('sku-link-lmp-save-btn-label');
        const selected = Array.from(linkedSkuModalSelectedSkus);
        if (countEl) countEl.textContent = String(selected.length);
        if (saveLabel) saveLabel.textContent = selected.length > 1 ? 'Link ' + selected.length + ' SKUs' : 'Link SKU(s)';
        if (!wrap || !listEl) return;
        if (!selected.length) { wrap.classList.add('d-none'); listEl.innerHTML = ''; return; }
        wrap.classList.remove('d-none');
        listEl.innerHTML = selected.map(function (sku) {
            return `<span class="sku-link-lmp-selected-chip">${escapeHtml(sku)}<button type="button" class="sku-link-lmp-selected-remove" data-sku="${escapeHtmlAttr(sku)}" title="Remove">&times;</button></span>`;
        }).join('');
    }

    function renderLinkedSkuSuggestions(term) {
        const wrap = document.getElementById('sku-link-lmp-suggestions');
        if (!wrap) return;
        const query = String(term || '').trim();
        if (!query) { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
        clearTimeout(linkedSkuSuggestionTimer);
        linkedSkuSuggestionTimer = setTimeout(function () {
            const requestId = ++linkedSkuSuggestionRequestId;
            fetch(`${filteredSkusUrl}?sku=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(function (response) {
                if (requestId !== linkedSkuSuggestionRequestId) return;
                if (!response.success) throw new Error(response.message || 'Could not search SKUs.');
                const currentSku = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();
                const existing = new Set((Array.isArray(linkedSkuModalRow?.linked_lmp_skus) ? linkedSkuModalRow.linked_lmp_skus : []).map(s => String(s || '').trim().toUpperCase()));
                const matches = (Array.isArray(response.skus) ? response.skus : []).map(s => String(s || '').trim())
                    .filter(function (sku) { const norm = sku.toUpperCase(); return sku && norm !== currentSku && !existing.has(norm); }).slice(0, 12);
                if (!matches.length) { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
                wrap.classList.remove('d-none');
                wrap.innerHTML = matches.map(function (sku) {
                    const checked = linkedSkuModalSelectedSkus.has(sku);
                    return `<label class="list-group-item list-group-item-action py-2 sku-link-lmp-suggestion-item d-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input sku-link-lmp-suggestion-cb" value="${escapeHtmlAttr(sku)}" ${checked ? 'checked' : ''}><span class="flex-grow-1">${escapeHtml(sku)}</span></label>`;
                }).join('');
            }).catch(function () { if (requestId !== linkedSkuSuggestionRequestId) return; wrap.classList.add('d-none'); wrap.innerHTML = ''; });
        }, 200);
    }

    function getLinkedSkuModalSelections() {
        const selected = Array.from(linkedSkuModalSelectedSkus);
        const inputVal = String(document.getElementById('sku-link-lmp-input')?.value || '').trim();
        const sourceNorm = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();
        if (inputVal && inputVal.toUpperCase() !== sourceNorm) {
            if (!selected.some(s => s.toUpperCase() === inputVal.toUpperCase())) selected.push(inputVal);
        }
        return selected;
    }

    function openLinkedSkuModal(rowData) {
        if (!linkedSkuModal || !rowSkuForLinkLmp(rowData)) return;
        linkedSkuModalRow = rowData;
        linkedSkuModalSelectedSkus = new Set();
        document.getElementById('sku-link-lmp-source').textContent = rowSkuForLinkLmp(rowData);
        const input = document.getElementById('sku-link-lmp-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        updateLinkedSkuSelectedSummary();
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function saveLinkedSkuFromModal() {
        const sourceSku = rowSkuForLinkLmp(linkedSkuModalRow);
        if (!sourceSku) return;
        const toLink = getLinkedSkuModalSelections();
        if (!toLink.length) { alert('Select one or more SKUs from the list, or enter a SKU to link.'); return; }
        const allSkus = [sourceSku].concat(toLink);
        const uniqueSkus = []; const seen = new Set();
        allSkus.forEach(function (sku) { const norm = String(sku || '').trim().toUpperCase(); if (!norm || seen.has(norm)) return; seen.add(norm); uniqueSkus.push(String(sku).trim()); });
        if (uniqueSkus.length < 2) { alert('Select at least one SKU to link.'); return; }
        const btn = document.getElementById('sku-link-lmp-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...'; }
        const isBulk = uniqueSkus.length > 2 || toLink.length > 1;
        const fetchPromise = isBulk
            ? fetch(linkedSkuBulkLinkUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify({ skus: uniqueSkus }) })
            : fetch(linkedSkuAddUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify({ sku: sourceSku, linked_sku: toLink[0] }) });
        fetchPromise.then(r => r.json()).then(function (response) {
            if (!response.success) throw new Error(response.message || 'Could not link SKU(s).');
            linkedSkuModalSelectedSkus = new Set();
            linkedSkuModal?.hide();
            applyAffectedLinkedSkuRows(response.affected);
        }).catch(function (err) { alert(err.message || 'Could not link SKU(s).'); })
        .finally(function () { if (btn) { btn.disabled = false; btn.innerHTML = original; } });
    }

    function initSkuLinkLmpModal() {
        const modalEl = document.getElementById('skuLinkLmpModal');
        if (modalEl) linkedSkuModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.getElementById('sku-link-lmp-input')?.addEventListener('input', function () { renderLinkedSkuSuggestions(this.value); });
        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('click', function (e) {
            const item = e.target.closest('.sku-link-lmp-suggestion-item'); if (!item) return;
            const cb = item.querySelector('.sku-link-lmp-suggestion-cb'); if (!cb || e.target === cb) return;
            cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('change', function (e) {
            const cb = e.target.closest('.sku-link-lmp-suggestion-cb'); if (!cb) return;
            const sku = String(cb.value || '').trim(); if (!sku) return;
            if (cb.checked) linkedSkuModalSelectedSkus.add(sku); else linkedSkuModalSelectedSkus.delete(sku);
            updateLinkedSkuSelectedSummary();
        });
        document.getElementById('sku-link-lmp-selected-skus')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sku-link-lmp-selected-remove'); if (!btn) return;
            linkedSkuModalSelectedSkus.delete(String(btn.dataset.sku || '').trim());
            document.querySelectorAll('.sku-link-lmp-suggestion-cb').forEach(function (cb) {
                if (cb.value === btn.dataset.sku) cb.checked = false;
            });
            updateLinkedSkuSelectedSummary();
        });
        document.getElementById('sku-link-lmp-save-btn')?.addEventListener('click', function () { saveLinkedSkuFromModal(); });
    }

    $(document).ready(function() {
        initSkuLinkLmpModal();
        bindShopifyB2cBadgeHistory();

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
            // Keep _select visible so Push can still use row checkboxes
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
                const newSprice = shopifyB2cApplyAmzFloor(rowData, +candidate.toFixed(2));
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
                    has_custom_sprice: true,
                    AMZ_SUGG_APPLIED: false
                });
                updates.push({ sku: sku, sprice: newSprice, amz_sugg: 0 });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
            if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
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
                const newSprice = shopifyB2cApplyAmzFloor(rowData, +candidate.toFixed(2));
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
                    has_custom_sprice: true,
                    AMZ_SUGG_APPLIED: false
                });
                updates.push({ sku: sku, sprice: newSprice, amz_sugg: 0 });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
            if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
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

        function shopifyB2cFindRowBySku(sku) {
            const want = String(sku || '').trim().toUpperCase();
            if (!want || !table) return null;
            let found = null;
            function walk(r) {
                if (found || !r || typeof r.getData !== 'function') return;
                const d = r.getData() || {};
                if (String(d['(Child) sku'] || '').trim().toUpperCase() === want) found = r;
                if (typeof r.getTreeChildren === 'function') (r.getTreeChildren() || []).forEach(walk);
            }
            try { (table.getRows() || []).forEach(walk); } catch (e) { /* ignore */ }
            return found;
        }

        function shopifyB2cApplyPulledLivePrice(sku, live) {
            const p = Math.round((Number(live) || 0) * 100) / 100;
            if (!(p > 0)) return;
            const row = shopifyB2cFindRowBySku(sku);
            if (row) shopifyB2cApplyLivePriceToRow(row, p, { SPRICE_STATUS: 'pushed' });
            const want = String(sku || '').trim().toUpperCase();
            function patch(d) {
                if (!d) return;
                if (String(d['(Child) sku'] || '').trim().toUpperCase() === want) {
                    Object.assign(d, shopifyB2cComputeLivePriceMetrics(d, p), { SPRICE_STATUS: 'pushed' });
                }
                if (Array.isArray(d._children)) d._children.forEach(patch);
            }
            try { (allTableData || []).forEach(patch); } catch (e) { /* ignore */ }
        }

        /** After a successful push, GET live variant.price from Shopify Admin and write Price. */
        function shopifyB2cPullAfterPush(skus) {
            const list = [];
            const seen = {};
            (skus || []).forEach(function(sku) {
                const s = String(sku || '').trim();
                const key = s.toUpperCase();
                if (!s || seen[key]) return;
                seen[key] = true;
                list.push(s);
            });
            if (!list.length) return;
            const expectedBySku = {};
            list.forEach(function(sku) {
                const row = shopifyB2cFindRowBySku(sku);
                const d = row && typeof row.getData === 'function' ? (row.getData() || {}) : {};
                const want = Number(typeof shopifyB2cShownSprice === 'function' ? shopifyB2cShownSprice(d) : d.SPRICE) || 0;
                if (want > 0) expectedBySku[String(sku).toUpperCase()] = want;
            });
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const retryMs = [2500, 4000, 7000];
            function runPull(attempt, pending) {
                if (!pending || !pending.length) return;
                $.ajax({
                    url: PULL_SHOPIFY_B2C_PRICE_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    data: { _token: csrf, skus: pending },
                    timeout: 300000,
                }).done(function(resp) {
                    const results = (resp && resp.results) || [];
                    const stale = [];
                    let pulled = 0;
                    results.forEach(function(r) {
                        if (!r || !r.success || !(Number(r.price) > 0) || !r.sku) return;
                        const want = Number(expectedBySku[String(r.sku).toUpperCase()]) || 0;
                        if (want > 0 && Math.abs(Number(r.price) - want) > 0.05) {
                            stale.push(r.sku);
                            return;
                        }
                        shopifyB2cApplyPulledLivePrice(r.sku, r.price);
                        pulled++;
                    });
                    if (stale.length && attempt + 1 < retryMs.length) {
                        shopifyB2cPullAfterPush._t = setTimeout(function() {
                            runPull(attempt + 1, stale);
                        }, retryMs[attempt + 1]);
                        return;
                    }
                    if (pulled > 0 && !stale.length) {
                        showToast('Pulled live Price for ' + pulled + ' SKU(s)', 'success');
                    } else if (stale.length) {
                        showToast('Pushed ' + list.length + ' SKU(s) — live Price still catching up', 'success');
                    } else if (!(Number(resp && resp.skip_count) > 0)) {
                        showToast((resp && resp.message) || 'Shopify Price pull failed', 'error');
                    }
                }).fail(function(xhr) {
                    if (attempt + 1 < retryMs.length) {
                        shopifyB2cPullAfterPush._t = setTimeout(function() {
                            runPull(attempt + 1, pending);
                        }, retryMs[attempt + 1]);
                        return;
                    }
                    showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Shopify Price pull failed', 'error');
                });
            }
            showToast('Pulling live Price for ' + list.length + ' SKU(s)…', 'success');
            clearTimeout(shopifyB2cPullAfterPush._t);
            shopifyB2cPullAfterPush._t = setTimeout(function() {
                runPull(0, list.slice());
            }, retryMs[0]);
        }
        window.shopifyB2cPullAfterPush = shopifyB2cPullAfterPush;

        /** Push one SKU SPRICE to Shopify B2C (same /push-shopify-b2c-price as Amazon tabulator). */
        function pushShopifyB2cPrice(sku, price, $btn, row) {
            if (!sku || !price || price <= 0 || isNaN(price)) {
                showToast('Invalid SKU or S PRC — set S PRC first', 'error');
                return;
            }
            if ($btn && $btn.length) {
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-clock fa-spin" style="color: black;"></i>');
            }
            if (row) {
                row.update({ SPRICE_STATUS: 'processing' });
            }

            $.ajax({
                url: PUSH_SHOPIFY_B2C_PRICE_URL,
                method: 'POST',
                timeout: 120000,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { sku: sku, price: price },
                success: function(response) {
                    const shopifyPush = response.shopify_push || {};
                    let finalStatus = 'error';
                    if (response.errors && response.errors.length > 0) {
                        showToast('Shopify push failed: ' + (response.errors[0].message || 'Unknown error'), 'error');
                    } else if (shopifyPush.ok) {
                        finalStatus = 'pushed';
                        showToast('Shopify: ' + (shopifyPush.message || 'Pushed successfully') + ' for SKU: ' + sku, 'success');
                    } else {
                        showToast('Shopify: ' + (shopifyPush.message || 'Push failed'), 'error');
                    }

                    if (row) {
                        if (finalStatus === 'pushed') {
                            shopifyB2cApplyLivePriceToRow(row, price, { SPRICE_STATUS: 'pushed' });
                            shopifyB2cPullAfterPush([sku]);
                        } else {
                            row.update({ SPRICE_STATUS: finalStatus });
                            row.reformat();
                        }
                    } else if (finalStatus === 'pushed') {
                        shopifyB2cPullAfterPush([sku]);
                    }
                    if ($btn && $btn.length) {
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    if (row) {
                        row.update({ SPRICE_STATUS: 'error' });
                        row.reformat();
                    }
                    if ($btn && $btn.length) {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fa-solid fa-x" style="color:#dc3545;"></i>');
                    }
                    const errorMsg = (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0])
                        ? xhr.responseJSON.errors[0].message
                        : 'Unknown error';
                    showToast('Shopify push failed: ' + errorMsg, 'error');
                }
            });
        }

        /** Bulk-push SPRICE for all selected SKUs (sequential to avoid rate limits). */
        function pushSelectedShopifyPrices() {
            if (!table) return;
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU first', 'error');
                return;
            }

            const toPush = [];
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (isShopifyB2cParentRow(d)) return;
                const sku = d['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                const price = shopifyB2cShownSprice(d);
                if (price > 0) {
                    toPush.push({ sku: sku, price: price, row: row });
                }
            });

            if (toPush.length === 0) {
                showToast('No selected SKUs have S PRC > 0', 'warning');
                return;
            }

            if (!confirm('Push ' + toPush.length + ' price(s) to Shopify?')) return;

            const $btns = $('#push-shopify-prices-btn, #push-selected-shopify-btn');
            const originalHtml = $('#push-shopify-prices-btn').html();
            $btns.prop('disabled', true);
            $('#push-shopify-prices-btn').html('<i class="fas fa-spinner fa-spin"></i> Pushing...');

            let idx = 0;
            let okCount = 0;
            let failCount = 0;
            const okSkus = [];

            function next() {
                if (idx >= toPush.length) {
                    $btns.prop('disabled', false);
                    $('#push-shopify-prices-btn').html(originalHtml);
                    showToast('Push done: ' + okCount + ' ok, ' + failCount + ' failed', failCount ? 'warning' : 'success');
                    if (okSkus.length) shopifyB2cPullAfterPush(okSkus);
                    if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
                    return;
                }
                const item = toPush[idx++];
                item.row.update({ SPRICE_STATUS: 'processing' });
                item.row.reformat();

                $.ajax({
                    url: PUSH_SHOPIFY_B2C_PRICE_URL,
                    method: 'POST',
                    timeout: 120000,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: item.sku, price: item.price },
                    success: function(response) {
                        const shopifyPush = response.shopify_push || {};
                        if (response.errors && response.errors.length > 0) {
                            failCount++;
                            item.row.update({ SPRICE_STATUS: 'error' });
                        } else if (shopifyPush.ok) {
                            okCount++;
                            okSkus.push(item.sku);
                            shopifyB2cApplyLivePriceToRow(item.row, item.price, { SPRICE_STATUS: 'pushed' });
                        } else {
                            failCount++;
                            item.row.update({ SPRICE_STATUS: 'error' });
                        }
                        item.row.reformat();
                        setTimeout(next, 700);
                    },
                    error: function() {
                        failCount++;
                        item.row.update({ SPRICE_STATUS: 'error' });
                        item.row.reformat();
                        setTimeout(next, 700);
                    }
                });
            }

            next();
        }

        $('#push-shopify-prices-btn, #push-selected-shopify-btn').on('click', function() {
            pushSelectedShopifyPrices();
        });

        // Badge clicks just toggle the #sold-filter dropdown so the dropdown stays the
        // single source of truth for the Sold filter (mirrors Amazon tabulator behavior).
        // Clicking the same badge twice clears the filter (toggle semantics preserved).
        $('#zero-sold-count-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#more-sold-count-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            applyFilters();
        });

        // < Amz badge click handler - filter prices less than Amazon
        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false; // Deactivate the other filter
            if (lessAmzFilterActive) purpleTriangleFilterActive = false;
            applyFilters();
        });

        // > Amz badge click handler - filter prices greater than Amazon
        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false; // Deactivate the other filter
            if (moreAmzFilterActive) purpleTriangleFilterActive = false;
            applyFilters();
        });

        // Missing badge click handler - filter SKUs missing in Shopify B2C
        let missingFilterActive = false;
        $('#missing-count-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
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

                        // Ensure minimum price, then raise to Amz when below A Price
                        newSprice = Math.max(0.99, newSprice);
                        newSprice = shopifyB2cApplyAmzFloor(rowData, newSprice);

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
                            has_custom_sprice: true,
                            AMZ_SUGG_APPLIED: false
                        });

                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: newSprice,
                            amz_sugg: 0
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
            if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
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
                        
                        // Keep A Price on S PRC — do not let live promo / LMP cap replace it
                        row.update({
                            SPRICE: amazonPrice,
                            SGPFT: sgpft,
                            SNPFT: snpft,
                            SROI: sroi,
                            SNROI: snroi,
                            has_custom_sprice: true,
                            AMZ_SUGG_APPLIED: true,
                            SPRICE_STATUS: 'applied'
                        });
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: amazonPrice,
                            amz_sugg: 1
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
            
            let message = `Amz price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amz price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
            if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates, opts) {
            opts = opts || {};
            if (!updates || !updates.length) return;
            if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
                chPromoBatchClearThenSave(updates, function(next) {
                    saveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
                }, {
                    wipeFn: function(zeros) {
                        return $.ajax({
                            url: '/shopify/save-sprice',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                updates: zeros.map(function(u) {
                                    return { sku: u.sku, sprice: 0, amz_sugg: 0 };
                                })
                            }
                        });
                    }
                });
                return;
            }
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

        // Clear SPRICE for selected SKUs, save 0, then refill Sprc Dil (same as TikTok).
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            const items = [];
            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                else {
                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0,
                        has_custom_sprice: false,
                    });
                }
                row.update({ AMZ_SUGG_APPLIED: false });
                items.push({ row: row, sku: sku });
            });

            if (!items.length) return;

            const zeros = items.map(function(i) { return { sku: i.sku, sprice: 0, amz_sugg: 0 }; });
            $.ajax({
                url: '/shopify/save-sprice',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates: zeros }
            }).always(function() {
                const fills = [];
                items.forEach(function(item) {
                    const d = item.row.getData() || {};
                    let price = (typeof ebayTiktokRuleDiscount === 'function')
                        ? ebayTiktokRuleDiscount(d)
                        : ((typeof ebaySprcDilForRow === 'function') ? (ebaySprcDilForRow(d) || 0) : 0);
                    if (!(price > 0)) return;
                    if (typeof chPromoSpricePatch === 'function') {
                        item.row.update(Object.assign({}, chPromoSpricePatch(price), { SPRICE_STATUS: 'applied' }));
                    } else {
                        item.row.update({ SPRICE: price, has_custom_sprice: true, SPRICE_STATUS: 'applied' });
                    }
                    fills.push({ sku: item.sku, sprice: price, amz_sugg: 0 });
                });
                if (fills.length) {
                    saveSpriceUpdates(fills, { clearFirst: false });
                    showToast('S PRC cleared, then Sprc Dil saved on ' + fills.length + ' SKU(s)', 'success');
                } else {
                    showToast('SPRICE cleared for ' + items.length + ' SKU(s)', 'success');
                }
                if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
            });
        }

        // SAVE SPRICE to database with retry
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0, skipClear) {
            const maxRetries = 3;
            if (row && Number(sprice) > 0 && typeof chPromoFinalSpriceToSave === 'function') {
                sprice = chPromoFinalSpriceToSave(row.getData(), sprice);
            }
            if (!skipClear && !(retryCount > 0) && Number(sprice) > 0) {
                if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                else if (row && typeof row.update === 'function') {
                    row.update({ SPRICE: 0, has_custom_sprice: false });
                }
                $.ajax({
                    url: '/shopify/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: 0,
                        amz_sugg: 0,
                        _token: '{{ csrf_token() }}'
                    }
                }).always(function() {
                    if (row && typeof row.update === 'function') {
                        row.update({ SPRICE: sprice, has_custom_sprice: true });
                    }
                    saveSpriceWithRetry(sku, sprice, row, 0, true);
                });
                return;
            }
            
            $.ajax({
                url: '/shopify/save-sprice',
                method: 'POST',
                data: {
                    sku: sku,
                    sprice: sprice,
                    amz_sugg: 0,
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
                    try { row.reformat(); } catch (e) { /* ignore */ }
                    if (typeof window.updateShopifyB2cSummary === 'function') window.updateShopifyB2cSummary();
                },
                error: function(xhr) {
                    if (retryCount < maxRetries) {
                        setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1, true), 2000);
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

        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'shopify_b2c'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => 'shopify_b2c'])
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'script'])

        // Initialize Tabulator
        table = new Tabulator("#reverb-table", {
            ajaxURL: SHOPIFY_B2C_DATA_URL,
            ajaxSorting: false,
            layout: "fitData",
            rowHeight: 36,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200, true],
            paginationCounter: "rows",
            renderVertical: "virtual",
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
                var payload = response.data || response;
                if (Array.isArray(payload)) {
                    allTableData = payload;
                    if (window.ParentExpand) ParentExpand.captureDataset(payload);
                }
                return payload;
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
                ParentExpand.columnDef(),
                {
                    title: "Image",
                    field: "image_path",
                    hozAlign: "center",
                    headerSort: false,
                    width: 36,
                    minWidth: 36,
                    maxWidth: 36,
                    resizable: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${escapeHtmlAttr(value)}" alt="" class="hover-thumb" loading="lazy" decoding="async" style="width: 28px; height: 28px; object-fit: cover;">`;
                        }
                        return '';
                    }
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
                    headerTooltip: "OV L30 / INV × 100. Same Dil Sprc Dil uses.",
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
                        // CVR% = B2C L30 ÷ Views (not OV L30)
                        const l30 = parseFloat(rowData['B2B L30']) || 0;
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
                    title: "Std Prc",
                    field: "STANDARD_PRICE",
                    hozAlign: "center",
                    headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs Amz price.",
                    editor: "input",
                    width: 70,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (isShopifyB2cParentRow(d)) return false;
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(rowData['A Price'] || rowData.a_price || rowData.amazon_price || 0) || 0;
                        const channelPrice = parseFloat(rowData['Price'] || rowData.price || 0) || 0;
                        const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                        const dot = shopifyB2cStdPrcChangeDotHtml(std, comparePrice);

                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Price",
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
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        
                        // Show red if Price is less than Amazon Price
                        if (amazonPrice > 0 && value < amazonPrice) {
                            return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        
                        // Show green if Price is greater than Amazon Price
                        if (amazonPrice > 0 && value > amazonPrice) {
                            return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        
                        return `$${value.toFixed(2)}${lmpTri}${purpleTri}`;
                    },
                    width: 70
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 100,
                    headerTooltip: "Google LMP from /repricer/google-search (manual add supported)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (window.ParentExpand) {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, {
                                dataset: typeof allTableData !== 'undefined' ? allTableData : undefined
                            });
                            if (avgHtml !== null) return avgHtml;
                        }
                        if (isShopifyB2cParentRow(rowData)) return '';

                        const sku = String(rowData['(Child) sku'] || '');
                        const lmpPrice = parseFloat(cell.getValue());
                        const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
                        const ourPrice = parseFloat(rowData.Price) || 0;
                        const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                        const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));
                        const skuAttr = escapeHtmlAttr(sku);
                        const countHtml = totalCompetitors > 0
                            ? ' <a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"'
                                + ' title="View ' + totalCompetitors + ' competitor' + (totalCompetitors === 1 ? '' : 's') + '"'
                                + ' style="color:#007bff;text-decoration:none;cursor:pointer;font-weight:600;">('
                                + totalCompetitors + ')</a>'
                            : '';

                        if (lmpPrice > 0) {
                            const priceColor = (ourPrice > 0 && lmpPrice < ourPrice) ? '#dc3545' : '#28a745';
                            return '<span style="white-space:nowrap;"><span style="color:' + priceColor + ';font-weight:600;">$'
                                + lmpPrice.toFixed(2) + '</span>' + countHtml + '</span>';
                        }

                        if (totalCompetitors > 0) {
                            return '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"'
                                + ' title="View ' + totalCompetitors + ' competitor' + (totalCompetitors === 1 ? '' : 's') + '"'
                                + ' style="color:#007bff;text-decoration:none;cursor:pointer;font-weight:600;">('
                                + totalCompetitors + ')</a>';
                        }

                        if (!sku) {
                            return '<span style="color:#999;">N/A</span>';
                        }

                        return '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"'
                            + ' title="Add Google LMP competitor"'
                            + ' style="color:#0d6efd;text-decoration:none;cursor:pointer;font-size:14px;line-height:1;">'
                            + '<i class="fas fa-plus"></i></a>';
                    }
                },
                {
                    title: "Diff",
                    field: "lmp_diff_pct",
                    hozAlign: "center",
                    width: 70,
                    headerTooltip: "(Google LMP − Price) / LMP × 100. When S PRC ≠ Price, Diff previews S PRC (what push will use).",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = function(rd) {
                            if (isShopifyB2cParentRow(rd)) return -Infinity;
                            const lmp = parseFloat(rd.lmp_price || 0);
                            const price = shopifyB2cDiffComparePrice(rd);
                            if (!lmp || lmp <= 0) return -Infinity;
                            return ((lmp - price) / lmp) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';

                        const lmp = parseFloat(rowData.lmp_price || 0);
                        const livePrice = parseFloat(rowData.Price || 0);
                        const compare = shopifyB2cDiffComparePrice(rowData);
                        if (!lmp || lmp <= 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }
                        const diff = ((lmp - compare) / lmp) * 100;
                        const color = diff < 0 ? '#dc3545' : '#28a745';
                        const pending = shopifyB2cSpricePending(rowData);
                        const tip = pending
                            ? ('S PRC $' + compare.toFixed(2) + ' vs LMP (live Price $' + livePrice.toFixed(2) + ')')
                            : ('Price $' + compare.toFixed(2) + ' vs LMP');
                        return '<span title="' + tip + '" style="color:' + color + ';font-weight:600;">' + diff.toFixed(1) + '%</span>';
                    }
                },
                {
                    title: "Sku Link LMP",
                    field: "linked_lmp_skus",
                    hozAlign: "left",
                    headerHozAlign: "center",
                    width: 180,
                    headerSort: false,
                    visible: false,
                    variableHeight: false,
                    cssClass: "linked-sku-col",
                    formatter: linkedLmpSkuFormatter,
                    cellClick: function(e, cell) {
                        if (e.target.closest('.sku-link-lmp-remove')) {
                            e.preventDefault();
                            e.stopPropagation();
                            removeLinkedSkuFromRow(
                                cell.getRow().getData(),
                                e.target.closest('.sku-link-lmp-remove').dataset.linkedSku || ''
                            );
                            return;
                        }
                        e.preventDefault();
                        e.stopPropagation();
                        openLinkedSkuModal(cell.getRow().getData());
                    },
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
                    title: "GROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const percent = parseFloat(cell.getValue());
                        if (percent === null || percent === undefined || !isFinite(percent)) return '';
                        return `<span style="color: ${shopifyB2cGroiColor(percent)}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "GPFT %",
                    field: "GPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const percent = parseFloat(cell.getValue());
                        if (percent === null || percent === undefined || !isFinite(percent)) return '';
                        return `<span style="color: ${shopifyB2cGpftColor(percent)}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "PFT %",
                    field: "NPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const ads = parseFloat(SHOPIFY_DIRECT_TCOS_PCT) || 0;
                        const npft = (parseFloat(rowData['GPFT%']) || 0) - ads;
                        return `<span style="color: ${shopifyB2cGpftColor(npft)}; font-weight: 600;">${npft.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "NROI%",
                    field: "NROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const nroi = shopifyComputeNetRoi(
                            rowData['Price'],
                            rowData['LP_productmaster'],
                            rowData['Ship_productmaster'],
                            shopifyChannelAdsPct()
                        );
                        return `<span style="color: ${shopifyB2cGroiColor(nroi)}; font-weight: 600;">${nroi.toFixed(0)}%</span>`;
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
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                ...(typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : []),
                {
                    title: "Sprc Dil",
                    field: "SPRC_DIL",
                    hozAlign: "center",
                    headerSort: true,
                    sorter: function(a, b, aRow, bRow) {
                        const val = function(row) {
                            return (typeof ebaySprcDilForRow === 'function')
                                ? (ebaySprcDilForRow(row) || 0)
                                : 0;
                        };
                        return val(aRow.getData()) - val(bRow.getData());
                    },
                    headerTooltip: "S PRC from Dil → Target GROI% slabs (same as /tiktok-2-pricing). Dil-matching when B2C L30 > 0; 0 Sold uses the lowest Target GROI. Below A Price is raised to Amz. Formula: (LP × (1 + GROI%/100) + Ship) / margin.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';
                        if (typeof ebayDilGroiMetaForRow !== 'function') return '';
                        const meta = ebayDilGroiMetaForRow(rowData);
                        if (!meta || !(meta.sprc > 0)) return '';
                        const raw = Number(meta.rawSprc > 0 ? meta.rawSprc : meta.sprc) || meta.sprc;
                        const amz = shopifyB2cAmzPrice(rowData);
                        const raised = amz > 0 && raw > 0 && raw + 0.001 < amz;
                        let tip = 'Dil ' + (isFinite(meta.dil) ? meta.dil.toFixed(1) : '0') + '%'
                            + ' → ' + meta.label
                            + ' → GROI ' + meta.groi + '%'
                            + ' → $' + Number(raw).toFixed(2);
                        if (raised) tip += ' → Amz $' + amz.toFixed(2);
                        const amzLbl = raised
                            ? ' <span class="shopifyb2c-sprice-amz-lbl" title="Dil $' + Number(raw).toFixed(2) + ' &lt; A Price $' + amz.toFixed(2) + ' — raised to Amz">Amz</span>'
                            : '';
                        return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="font-weight:600;color:#6f42c1;">$'
                            + meta.sprc.toFixed(2) + '</span>' + amzLbl;
                    },
                    width: 78
                },
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editable: false,
                    sorter: "number",
                    headerTooltip: "Not editable. Auto-saved from Sprc Dil (Dil slab or 0 Sold min GROI), then raised to Amz when below A Price. Blue triangle = S PRC ≠ Price. Red triangle = S PRC at/above LMP.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) {
                            return '';
                        }
                        const amzSugg = shopifyB2cIsAmzSuggApplied(rowData);
                        let value = shopifyB2cDisplayedSprice(rowData);
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        const live = parseFloat(rowData.Price) || 0;
                        const lmp = parseFloat(rowData.lmp_price) || 0;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';

                        if (!(value > 0)) {
                            return '';
                        }

                        let overLmp = lmp > 0 && value + 0.0001 >= lmp;
                        let redTri = '';
                        if (!amzSugg) {
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(rowData, value) : null;
                            if (cap && cap.shown > 0) value = cap.shown;
                            overLmp = cap ? cap.alert : overLmp;
                            redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                        } else if (overLmp) {
                            redTri = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="Amazon suggested price is at/above LMP $'
                                + lmp.toFixed(2) + ' — not capped"></i>';
                        }
                        const amzFloor = shopifyB2cHasAmzFloor(rowData);
                        value = shopifyB2cShownSprice(rowData);
                        if (amzFloor && lmp > 0 && value + 0.0001 >= lmp) {
                            overLmp = true;
                            redTri = '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC raised to Amz $'
                                + value.toFixed(2) + ' — at/above LMP $' + lmp.toFixed(2) + '"></i>';
                        }
                        if (!(value > 0)) {
                            return '';
                        }
                        const formatted = '$' + value.toFixed(2);
                        let priceHtml = `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`;
                        if (overLmp) {
                            priceHtml = `<span style="color:#dc3545;font-weight:600;${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`;
                        }
                        const showAmz = shopifyB2cShowAmzLabel(rowData);
                        const amzLbl = shopifyB2cAmzLabelHtml(rowData);
                        const blueTri = (!showAmz && live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        
                        return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${redTri}${amzLbl}${blueTri}</span>`;
                    }
                },
                {
                    title: "Push",
                    field: "_push",
                    hozAlign: "center",
                    headerSort: false,
                    width: 50,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2cParentRow(rowData)) return '';
                        const sku = rowData['(Child) sku'] || '';
                        const sprice = shopifyB2cShownSprice(rowData);
                        const status = rowData.SPRICE_STATUS || '';
                        if (!sprice || sprice <= 0) {
                            return '<span style="color:#999;" title="Set S PRC first">N/A</span>';
                        }
                        let icon = '<i class="fas fa-check"></i>';
                        let color = '#0d6efd';
                        let title = 'Push to Shopify';
                        if (status === 'pushed' || status === 'applied') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            title = 'Price pushed to Shopify';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-x"></i>';
                            color = '#dc3545';
                            title = 'Error pushing to Shopify';
                        } else if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            color = '#ffc107';
                            title = 'Pushing to Shopify...';
                        }
                        return `<button type="button" class="btn btn-sm push-shopify-btn" data-sku="${sku.replace(/"/g, '&quot;')}" title="${title}" style="border:none;background:none;color:${color};padding:0;cursor:pointer;">${icon}</button>`;
                    },
                    cellClick: function(e, cell) {
                        const $target = $(e.target);
                        if (!$target.hasClass('push-shopify-btn') && !$target.closest('.push-shopify-btn').length) return;
                        e.stopPropagation();
                        const $btn = $target.hasClass('push-shopify-btn') ? $target : $target.closest('.push-shopify-btn');
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const price = shopifyB2cShownSprice(rowData);
                        pushShopifyB2cPrice(sku, price, $btn, cell.getRow());
                    }
                },
                {
                    title: "SGROI",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const shown = shopifyB2cShownSprice(rowData);
                        if (!(shown > 0)) return '';
                        const percent = shopifyB2cComputeSpriceMetrics(rowData, shown).SROI;
                        return `<span style="color: ${shopifyB2cGroiColor(percent)}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "S GPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const shown = shopifyB2cShownSprice(rowData);
                        if (!(shown > 0)) return '';
                        const percent = shopifyB2cComputeSpriceMetrics(rowData, shown).SGPFT;
                        return `<span style="color: ${shopifyB2cGpftColor(percent)}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNPFT",
                    field: "SNPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const shown = shopifyB2cShownSprice(rowData);
                        if (!(shown > 0)) return '';
                        const snpft = shopifyB2cComputeSpriceMetrics(rowData, shown).SNPFT;
                        return `<span style="color: ${shopifyB2cGpftColor(snpft)}; font-weight: 600;">${snpft.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNROI",
                    field: "SNROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const shown = shopifyB2cShownSprice(rowData);
                        if (!(shown > 0)) return '';
                        const snroi = shopifyB2cComputeSpriceMetrics(rowData, shown).SNROI;
                        return `<span style="color: ${shopifyB2cGroiColor(snroi)}; font-weight: 600;">${snroi.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Links",
                    field: "links_column",
                    width: 55,
                    hozAlign: "center",
                    visible: true,
                    headerSort: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sellerLink = shopifyB2cConnectedLink(rowData['S Link']);
                        const buyerLink = shopifyB2cConnectedLink(rowData['B Link']);

                        let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';

                        if (sellerLink) {
                            html += `<a href="${escapeHtmlAttr(sellerLink)}" target="_blank" rel="noopener" class="text-info" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> S
                            </a>`;
                        }

                        if (buyerLink) {
                            html += `<a href="${escapeHtmlAttr(buyerLink)}" target="_blank" rel="noopener" class="text-success" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> B
                            </a>`;
                        }

                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size: 12px;">-</span>';
                        }

                        html += '</div>';
                        return html;
                    }
                }
            ]
        });

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'Parent',
                skuField: '(Child) sku',
                getTable: () => table,
                getDataset: () => allTableData,
                onAfterExpand: () => {
                    if (typeof updateSummary === 'function') updateSummary();
                },
                onCollapse: () => {
                    if (typeof applyFilters === 'function') applyFilters();
                },
            });
            ParentExpand.bind();
        }

        // SKU / Parent search: use applyFilters() so it stacks with INV, REQ,
        // row-type (SKUs / Parents), and every other dropdown. table.setFilter()
        // would replace those and bring parent summary rows back.
        $('#sku-search, #parent-search').on('keyup', function() {
            applyFilters();
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
                url: SHOPIFY_B2C_LISTED_LIVE_URL,
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

        // SPRICE is not editable — auto-saved from Sprc Dil / Amz. Std Prc still saves here.
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const data = row.getData();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                const sku = data['(Child) sku'] || data.sku || data.SKU;
                const std = parseFloat(value);
                if (!sku || !isFinite(std) || std <= 0) {
                    row.update({ STANDARD_PRICE: null });
                    return;
                }
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: std,
                        is_standard_price: 1
                    },
                    success: function(response) {
                        const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                        applyShopifyB2cStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        showToast(n > 1
                            ? ('Std Prc saved for ' + n + ' linked SKUs')
                            : 'Std Prc saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Std Prc', 'error');
                    }
                });
                return;
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
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function() {
                    applyFilters();
                });
                return;
            }
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

            // CVR filter — B2C L30 ÷ Views
            const cvrFilter = $('#cvr-filter').val();
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const l30 = parseFloat(data['B2B L30']) || 0;
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

            // Amz floor: formula / LMP-capped S PRC was below A Price
            if (purpleTriangleFilterActive) {
                table.addFilter(function(data) {
                    return shopifyB2cHasAmzFloor(data);
                });
            }

            // Missing filter - show SKUs missing in Shopify B2C
            if (missingFilterActive) {
                table.addFilter("Missing", "=", "M");
            }
            if (lmpMissingFilterActive && window.LmpMissingBadge) {
                table.addFilter(function(data) {
                    return !LmpMissingBadge.isParentRow(data) && !LmpMissingBadge.hasLmp(data);
                });
            }
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'Price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'Price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return shopifyB2cHasBlueTriangle(data);
                });
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

            // SKU search: match child SKU (or Parent for parent rows).
            // Normalize whitespace + case so "CAPO BLUE 2 Pcs" matches "CAPO BLUE 2PCS".
            var searchVal = ($('#sku-search').val() || '').replace(/\s+/g, '').toLowerCase();
            if (searchVal) {
                table.addFilter(function(data) {
                    var sku = (isShopifyB2cParentRow(data)
                        ? (data.Parent || data['(Child) sku'] || data.sku || '')
                        : (data['(Child) sku'] || data.sku || ''));
                    sku = (sku + '').replace(/\s+/g, '').toLowerCase();
                    return sku.indexOf(searchVal) !== -1;
                });
            }

            // Parent search: filter by Parent column
            var parentSearchVal = ($('#parent-search').val() || '').replace(/\s+/g, '').toLowerCase();
            if (parentSearchVal) {
                table.addFilter(function(data) {
                    var parent = (data.Parent || '').toString().replace(/\s+/g, '').toLowerCase();
                    return parent.indexOf(parentSearchVal) !== -1;
                });
            }

            updateSummary();
        }

        if (window.LmpMissingBadge) {
            LmpMissingBadge.bind({
                badge: '#shopifyb2c-lmp-missing-badge',
                getActive: function() { return lmpMissingFilterActive; },
                onToggle: function(on) {
                    lmpMissingFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        purpleTriangleFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        function shopifyB2cClearPriceGtLmpCompetingFilters() {
            blueTriangleFilterActive = false;
            purpleTriangleFilterActive = false;
            priceLt80LmpFilterActive = false;
            lmpMissingFilterActive = false;
            lessAmzFilterActive = false;
            moreAmzFilterActive = false;
            missingFilterActive = false;
            $('#nrl-filter').val('all');
            $('#sold-filter').val('all');
            $('#gpft-filter').val('all');
            $('#roi-filter').val('all');
            $('#cvr-filter').val('all');
            $('#inventory-filter').val('more');
            $('#parent-filter').val('skus');
            $('#sku-search').val('');
            $('#parent-search').val('');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $('.column-filter[data-column="dil_percent"][data-color="all"]').addClass('active');
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.setOutline(document.getElementById('shopifyb2c-price-lt80-lmp-badge'), false);
            }
            if (window.LmpMissingBadge) {
                LmpMissingBadge.setOutline(document.getElementById('shopifyb2c-lmp-missing-badge'), false);
            }
        }
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#shopifyb2c-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) shopifyB2cClearPriceGtLmpCompetingFilters();
                    applyFilters();
                }
            });
        }
        $('#shopifyb2c-price-gt-lmp-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot, .kpi-status-dot').length) return;
            if (this.dataset.pglBound === '1') return;
            priceGtLmpFilterActive = !priceGtLmpFilterActive;
            if (priceGtLmpFilterActive) shopifyB2cClearPriceGtLmpCompetingFilters();
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.setOutline(this, priceGtLmpFilterActive);
            }
            applyFilters();
        });
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#shopifyb2c-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        purpleTriangleFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        $('#shopifyb2c-blue-triangle-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                lmpMissingFilterActive = false;
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                purpleTriangleFilterActive = false;
                lessAmzFilterActive = false;
                moreAmzFilterActive = false;
            }
            applyFilters();
        });
        $('#shopifyb2c-purple-triangle-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            purpleTriangleFilterActive = !purpleTriangleFilterActive;
            if (purpleTriangleFilterActive) {
                lmpMissingFilterActive = false;
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                blueTriangleFilterActive = false;
                lessAmzFilterActive = false;
                moreAmzFilterActive = false;
            }
            applyFilters();
        });

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
                
                // Apply NRL filter (dropdown values are REQ / NR)
                if (nrlFilter === 'REQ' && row.nr_req !== 'REQ') return false;
                if (nrlFilter === 'NR' && row.nr_req !== 'NR') return false;
                
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
            // GROI% = Σ PFT ÷ Σ COGS × 100 (same as Amazon / eBay / CVR modal)
            // — NOT a simple average of per-row ROI% values.
            const groiFromRows = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            // All page-level financials below come from the same /shopify L30 snapshot
            // the Shopify row on /all-marketplace-master uses (single source of truth:
            // ChannelMasterController::getShopifyDirectL30Snapshot).
            setShopifyB2cSummaryBadge($('#total-pft-amt-badge'), `PFT: $${Math.round(SHOPIFY_DIRECT_TOTAL_PFT).toLocaleString()}`, SHOPIFY_DIRECT_TOTAL_PFT);
            setShopifyB2cSummaryBadge($('#total-sales-amt-badge'), `Sales: $${Math.round(SHOPIFY_DIRECT_L30_SALES).toLocaleString()}`, SHOPIFY_DIRECT_L30_SALES);
            setShopifyB2cSummaryBadge($('#total-orders-badge'), `Orders: ${SHOPIFY_DIRECT_L30_ORDERS.toLocaleString()}`, SHOPIFY_DIRECT_L30_ORDERS);
            setShopifyB2cSummaryBadge($('#total-qty-badge'), `Qty: ${SHOPIFY_DIRECT_L30_QTY.toLocaleString()}`, SHOPIFY_DIRECT_L30_QTY);
            setShopifyB2cSummaryBadge($('#avg-gpft-badge'), `GPFT: ${SHOPIFY_DIRECT_GPFT_PCT.toFixed(1)}%`, SHOPIFY_DIRECT_GPFT_PCT);
            setShopifyB2cSummaryBadge($('#avg-price-badge'), `Price: $${avgPrice.toFixed(2)}`, avgPrice);
            setShopifyB2cSummaryBadge($('#total-inv-badge'), `INV: ${totalInv.toLocaleString()}`, totalInv);
            setShopifyB2cSummaryBadge($('#total-l30-badge'), `L30: ${totalL30.toLocaleString()}`, totalL30);
            const overallCvr = totalViews > 0 ? (SHOPIFY_DIRECT_L30_QTY / totalViews) * 100 : 0;
            setShopifyB2cSummaryBadge($('#total-views-badge'), `Views: ${totalViews.toLocaleString()}`, totalViews);
            setShopifyB2cSummaryBadge($('#avg-cvr-badge'), `CVR: ${overallCvr.toFixed(1)}%`, overallCvr);
            setShopifyB2cSummaryBadge($('#total-b2b-l30-badge'), `B2B: ${totalB2BL30.toLocaleString()}`, totalB2BL30);
            setShopifyB2cSummaryBadge($('#zero-sold-count-badge'), `0 Sold: ${zeroSoldCount}`, zeroSoldCount);
            if (window.LmpMissingBadge) {
                // Same INV + REQ + child-SKU set the table uses when this badge is clicked
                LmpMissingBadge.update('#shopifyb2c-lmp-missing-badge', data, 'shopifyb2c');
            }
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.update('#shopifyb2c-price-gt-lmp-badge', allData, 'shopifyb2c', 'Price');
                PriceGtLmpBadge.setOutline(document.getElementById('shopifyb2c-price-gt-lmp-badge'), priceGtLmpFilterActive);
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.update('#shopifyb2c-price-lt80-lmp-badge', allData, 'shopifyb2c', 'Price');
            }
            let blueTriangleCount = 0;
            let purpleTriangleCount = 0;
            allData.forEach(function(row) {
                if (shopifyB2cHasBlueTriangle(row)) blueTriangleCount++;
                if (shopifyB2cHasPurpleTriangle(row)) purpleTriangleCount++;
            });
            setShopifyB2cSummaryBadge(
                $('#shopifyb2c-blue-triangle-badge'),
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString(),
                blueTriangleCount,
                true
            );
            setShopifyB2cSummaryBadge(
                $('#shopifyb2c-purple-triangle-badge'),
                'Amz ' + purpleTriangleCount.toLocaleString(),
                purpleTriangleCount,
                true
            );
            syncShopifyB2cTriangleBadgeState();
            setShopifyB2cSummaryBadge($('#more-sold-count-badge'), `>0 Sold: ${moreSoldCount}`, moreSoldCount);
            setShopifyB2cSummaryBadge($('#total-cogs-badge'), `COGS: $${Math.round(SHOPIFY_DIRECT_TOTAL_COGS || totalCogs).toLocaleString()}`, SHOPIFY_DIRECT_TOTAL_COGS || totalCogs);
            const groiBadge = (typeof SHOPIFY_DIRECT_GROI_PCT === 'number' && !isNaN(SHOPIFY_DIRECT_GROI_PCT))
                ? SHOPIFY_DIRECT_GROI_PCT
                : groiFromRows;
            setShopifyB2cSummaryBadge($('#roi-percent-badge'), `GROI: ${groiBadge.toFixed(1)}%`, groiBadge);
            setShopifyB2cSummaryBadge($('#less-amz-badge'), `< Amz: ${lessAmzCount}`, lessAmzCount);
            setShopifyB2cSummaryBadge($('#more-amz-badge'), `> Amz: ${moreAmzCount}`, moreAmzCount);
            setShopifyB2cSummaryBadge($('#missing-count-badge'), `Miss: ${missingCount}`, missingCount);
            
            // Spend / TCOS / NPFT / NROI all read the page-level snapshot now.
            setShopifyB2cSummaryBadge($('#total-tcos-badge'), `Ads: ${Math.round(SHOPIFY_DIRECT_TCOS_PCT)}%`, SHOPIFY_DIRECT_TCOS_PCT);
            setShopifyB2cSummaryBadge($('#total-spend-badge'), `Spend: $${Math.round(SHOPIFY_DIRECT_TOTAL_SPEND).toLocaleString()}`, SHOPIFY_DIRECT_TOTAL_SPEND);
            setShopifyB2cSummaryBadge($('#avg-npft-badge'), `NPFT: ${SHOPIFY_DIRECT_NPFT_PCT.toFixed(1)}%`, SHOPIFY_DIRECT_NPFT_PCT);
            setShopifyB2cSummaryBadge($('#nroi-percent-badge'), `NROI: ${SHOPIFY_DIRECT_NROI_PCT.toFixed(1)}%`, SHOPIFY_DIRECT_NROI_PCT);
            if (typeof syncShopifyB2cSummaryTrendDots === 'function') syncShopifyB2cSummaryTrendDots();
            if (typeof saveShopifyB2cBadgeStatsOnce === 'function') saveShopifyB2cBadgeStatsOnce();
        }
        window.updateShopifyB2cSummary = updateSummary;

        /*
         * Column visibility persists in shared DB table channel_tabulator_column_settings
         * under channel = 'shopify_b2c_tabulator' — same /tabulator-column-visibility
         * endpoint Amazon / ebay / mfrg tabulators use.
         */
        function buildColumnDropdown(savedVisibility) {
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'column-dropdown-menu',
                    storageKey: 'shopify_b2c_col_cats_v1',
                    skipFields: ['_select'],
                    onSave: function() {
                        if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
                    }
                });
                window.AnalyticsColVis.rebuild(savedVisibility || null);
                return;
            }
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
                    map.linked_lmp_skus = false;
                    if (Object.keys(map).length > 0) {
                        table.getColumns().forEach(col => {
                            const field = col.getDefinition().field;
                            if (field === 'linked_lmp_skus') {
                                col.hide();
                                return;
                            }
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
            if (typeof window.chPromoBindTableAutofit === 'function') {
                window.chPromoBindTableAutofit(table);
            }
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
                updateSummary();
                if (typeof window.chPromoAutofitColumns === 'function') {
                    window.chPromoAutofitColumns(table);
                }
                if (typeof loadShopifyB2cBadgePrevDay === 'function') loadShopifyB2cBadgePrevDay();
                if (typeof loadChartJs === 'function') loadChartJs();
                shopifyB2cApplyRuleSpriceToAllRows({ persist: true });
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

        // ==================== Google LMP (from /repricer/google-search) ====================

        function updateShopifyB2cLmpRow(sku, competitors, lowestPrice) {
            if (!table || !sku) return;
            const list = Array.isArray(competitors) ? competitors : [];
            const active = list.filter(function(c) { return !c.ignored; });
            const lowest = (lowestPrice != null && lowestPrice > 0)
                ? parseFloat(lowestPrice)
                : (active.length
                    ? Math.min.apply(null, active.map(c => parseFloat(c.price) || 0).filter(p => p > 0))
                    : null);

            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (String(d['(Child) sku'] || '') !== String(sku)) return;
                row.update({
                    lmp_price: lowest && lowest > 0 ? Math.round(lowest * 100) / 100 : null,
                    lmp_entries_total: list.length,
                });
            });
        }

        function renderGoogleLmpList(sku, competitors, lowestPrice) {
            const list = Array.isArray(competitors) ? competitors : [];
            if (!list.length) {
                $('#lmpDataList').html(
                    '<div class="alert alert-info mb-0">' +
                    '<i class="fa fa-info-circle"></i> No Google competitors found yet. Add one manually above, or ' +
                    '<a href="' + GOOGLE_SEARCH_URL + '?sku=' + encodeURIComponent(sku) + '" target="_blank" rel="noopener">search on Google</a>.' +
                    '</div>'
                );
                return;
            }

            const lowest = (window.LmpIgnore && LmpIgnore.l1)
                ? LmpIgnore.l1(list, 'price')
                : ((lowestPrice != null && lowestPrice > 0)
                    ? parseFloat(lowestPrice)
                    : Math.min.apply(null, list.filter(function(c) { return !c.ignored; }).map(c => parseFloat(c.price) || 0).filter(p => p > 0)));

            window.currentGoogleLmp = { sku: sku, competitors: list, lowestPrice: lowest };

            let html = '';
            if (lowest && lowest > 0) {
                html += '<div class="mb-3"><span class="badge bg-success">Google lowest (non-ignored): $' + lowest.toFixed(2) + '</span></div>';
            }

            html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">' +
                '<thead class="table-light"><tr>' +
                '<th>#</th><th>Price</th><th>Source</th><th>Product ID</th><th>Title</th><th>Rating</th><th>Reviews</th><th>Link</th>' + LmpIgnore.header() + '<th>Actions</th>' +
                '</tr></thead><tbody>';

            list.forEach(function(item, index) {
                const price = parseFloat(item.price) || 0;
                const ignored = !!item.ignored;
                const isLowest = !ignored && price > 0 && lowest > 0 && Math.abs(price - lowest) < 0.01;
                const link = shopifyB2cConnectedLink(item.product_link || item.link || '');
                const title = item.product_title || item.title || '';
                const titleShort = title.length > 50 ? title.substring(0, 50) + '...' : title;
                const source = item.source || '—';
                const productId = item.product_id || '—';
                const image = shopifyB2cConnectedLink(item.image || '');
                const imgHtml = image
                    ? '<img src="' + escapeHtmlAttr(image) + '" alt="" class="rounded me-1" style="height:40px;width:40px;object-fit:contain;" onerror="this.style.display=\'none\'">'
                    : '';
                const rating = item.rating != null
                    ? '<span><i class="fa fa-star text-warning"></i> ' + parseFloat(item.rating).toFixed(1) + '</span>'
                    : '<span class="text-muted">—</span>';
                const reviews = item.reviews != null
                    ? (parseInt(item.reviews, 10) || 0).toLocaleString()
                    : '<span class="text-muted">—</span>';
                const priceBadge = isLowest
                    ? '<span class="badge bg-success">$' + price.toFixed(2) + ' <i class="fa fa-trophy"></i></span>'
                    : '<strong>$' + price.toFixed(2) + '</strong>';
                const linkBtn = link
                    ? '<a href="' + escapeHtmlAttr(link) + '" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="Open product"><i class="fa fa-external-link"></i></a>'
                    : '<span class="text-muted">—</span>';
                const delBtn = '<button type="button" class="btn btn-sm btn-danger delete-google-lmp-btn" data-id="' +
                    escapeHtmlAttr(item.id) + '" data-sku="' + escapeHtmlAttr(sku) + '" data-price="' + price +
                    '" title="Delete this competitor"><i class="fa fa-trash"></i></button>';

                const priceBadgeHtml = ignored
                    ? '<strong>$' + price.toFixed(2) + '</strong> <span class="badge bg-secondary">Ignored</span>'
                    : priceBadge;
                html += '<tr class="' + (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '') + '">' +
                    '<td class="text-center"><strong>' + (index + 1) + '</strong></td>' +
                    '<td><div class="d-flex align-items-center">' + imgHtml + priceBadgeHtml + '</div></td>' +
                    '<td style="font-size:11px;" title="' + escapeHtmlAttr(source) + '">' + escapeHtmlAttr(String(source).substring(0, 30)) + '</td>' +
                    '<td style="font-size:11px;">' + escapeHtmlAttr(productId) + '</td>' +
                    '<td style="font-size:11px;" title="' + escapeHtmlAttr(title) + '">' + escapeHtmlAttr(titleShort || '—') + '</td>' +
                    '<td class="text-center">' + rating + '</td>' +
                    '<td class="text-center">' + reviews + '</td>' +
                    '<td class="text-center">' + linkBtn + '</td>' +
                    '<td class="text-center align-middle">' + LmpIgnore.checkbox(item, 'google', sku) + '</td>' +
                    '<td class="text-center">' + delBtn + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }
        LmpIgnore.bind({
            marketplace: 'google',
            sku: function() { return (window.currentGoogleLmp && currentGoogleLmp.sku) || ''; },
            onToggled: function(id, ignored) {
                const cache = window.currentGoogleLmp || { sku: '', competitors: [] };
                (cache.competitors || []).forEach(function(c) {
                    if (String(c.id) === String(id)) c.ignored = ignored;
                });
                cache.lowestPrice = LmpIgnore.l1(cache.competitors, 'price');
                renderGoogleLmpList(cache.sku, cache.competitors, cache.lowestPrice);
                updateShopifyB2cLmpRow(cache.sku, cache.competitors, cache.lowestPrice);
            }
        });

        function loadGoogleLmpModal(sku, linkedLmpSkus) {
            $('#lmpSku').text(sku);
            $('#addLmpSku').val(sku);
            $('#addLmpProductId').val('');
            $('#addLmpSource').val('');
            $('#addLmpPrice').val('');
            $('#addLmpTitle').val('');
            $('#addLmpLink').val('');
            $('#lmpOpenGoogleSearch').attr('href', GOOGLE_SEARCH_URL + '?sku=' + encodeURIComponent(sku));
            $('#lmpModal').data('linked-lmp-skus', Array.isArray(linkedLmpSkus) ? linkedLmpSkus : []);

            const modalEl = document.getElementById('lmpModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();

            $('#lmpDataList').html(
                '<div class="text-center py-5">' +
                '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>' +
                '<p class="mt-2">Loading competitors...</p></div>'
            );

            const ajaxData = { sku: sku };
            const linked = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
            linked.forEach(function(linkedSku, idx) {
                ajaxData['linked_lmp_skus[' + idx + ']'] = linkedSku;
            });

            $.ajax({
                url: GOOGLE_LMP_DATA_URL,
                method: 'GET',
                traditional: true,
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        renderGoogleLmpList(sku, response.competitors || [], response.lowest_price);
                        updateShopifyB2cLmpRow(sku, response.competitors || [], response.lowest_price);
                    } else {
                        $('#lmpDataList').html(
                            '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> ' +
                            escapeHtmlAttr(response.error || 'No competitors found. Add one manually above.') +
                            '</div>'
                        );
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                        ? (xhr.responseJSON.error || xhr.responseJSON.message)
                        : 'Failed to load Google LMP data';
                    $('#lmpDataList').html(
                        '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' +
                        escapeHtmlAttr(msg) + '</div>'
                    );
                }
            });
        }

        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            let linkedSkus = $(this).data('linked-skus') || [];
            if (typeof linkedSkus === 'string') {
                try { linkedSkus = JSON.parse(linkedSkus) || []; } catch (err) { linkedSkus = []; }
            }
            if (sku) loadGoogleLmpModal(String(sku), linkedSkus);
        });

        $('#addGoogleLmpForm').on('reset', function() {
            const sku = ($('#addLmpSku').val() || '').trim();
            setTimeout(function() {
                $('#addLmpSku').val(sku);
            }, 0);
        });

        $('#addGoogleLmpForm').on('submit', function(e) {
            e.preventDefault();

            const sku = ($('#addLmpSku').val() || '').trim();
            const productId = ($('#addLmpProductId').val() || '').trim();
            const source = ($('#addLmpSource').val() || '').trim() || 'manual';
            const price = parseFloat($('#addLmpPrice').val());
            const title = ($('#addLmpTitle').val() || '').trim();
            const link = ($('#addLmpLink').val() || '').trim();

            if (!sku) {
                showToast('SKU is required', 'error');
                return;
            }
            if (!productId) {
                showToast('Product ID is required', 'error');
                return;
            }
            if (!price || price <= 0) {
                showToast('Valid price is required', 'error');
                return;
            }

            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: GOOGLE_LMP_ADD_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    sku: sku,
                    product_id: productId,
                    source: source,
                    price: price,
                    product_link: link || null,
                    product_title: title || null,
                },
                success: function(response) {
                    $submitBtn.prop('disabled', false).html(originalHtml);
                    if (response.success || response.data) {
                        showToast(response.message || 'Google LMP added successfully', 'success');
                        $('#addLmpProductId').val('');
                        $('#addLmpSource').val('');
                        $('#addLmpPrice').val('');
                        $('#addLmpTitle').val('');
                        $('#addLmpLink').val('');
                        loadGoogleLmpModal(sku, $('#lmpModal').data('linked-lmp-skus') || []);
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    $submitBtn.prop('disabled', false).html(originalHtml);
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                        ? (xhr.responseJSON.error || xhr.responseJSON.message)
                        : 'Failed to add Google LMP';
                    showToast(msg, 'error');
                }
            });
        });

        $(document).on('click', '.delete-google-lmp-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const id = $btn.data('id');
            const sku = $btn.data('sku') || $('#lmpSku').text();
            const price = $btn.data('price');

            if (!id) {
                showToast('Invalid competitor ID', 'error');
                return;
            }
            if (!confirm('Delete this Google competitor ($' + (price ? parseFloat(price).toFixed(2) : '') + ')? This cannot be undone.')) {
                return;
            }

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: GOOGLE_LMP_DELETE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message || 'Competitor deleted', 'success');
                        loadGoogleLmpModal(sku, $('#lmpModal').data('linked-lmp-skus') || []);
                    } else {
                        $btn.prop('disabled', false).html(originalHtml);
                        showToast(response.error || 'Failed to delete', 'error');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html(originalHtml);
                    const msg = (xhr.responseJSON && xhr.responseJSON.error)
                        ? xhr.responseJSON.error
                        : 'Failed to delete competitor';
                    showToast(msg, 'error');
                }
            });
        });
    });
</script>
@endsection

