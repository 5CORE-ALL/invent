@extends('layouts.vertical', ['title' => 'Business Analytics', 'sidenav' => 'condensed'])

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
        .shopify-b2b-toolbar {
            position: relative;
            z-index: auto;
            overflow: visible !important;
            flex-wrap: wrap !important;
            gap: 8px 10px !important;
        }
        .shopify-b2b-toolbar .form-select {
            width: auto !important;
            max-width: 130px;
            padding-right: 1.35rem !important;
            padding-left: 0.5rem !important;
            background-position: right 0.35rem center !important;
        }
        .shopify-b2b-page .card,
        .shopify-b2b-page .card-body {
            overflow: visible;
        }
        .shopify-b2b-page .card-body.shopify-b2b-controls {
            display: flex;
            flex-direction: column;
        }
        .shopify-b2b-toolbar .dropdown,
        .shopify-b2b-toolbar .btn-group,
        .shopify-b2b-toolbar .manual-dropdown-container {
            position: relative;
            z-index: 2;
        }
        .shopify-b2b-toolbar .dropdown-menu,
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
        .shopify-b2b-page .row {
            margin-left: 0;
            margin-right: 0;
        }
        .shopify-b2b-page .row > [class*="col-"],
        .shopify-b2b-page > .row > .card {
            padding-left: 0;
            padding-right: 0;
        }
        .shopify-b2b-page .card { border-radius: 10px; }
        .shopify-b2b-page .card-body { padding: 12px 14px; }
        /* Badges above filters (Amazon order: -1) */
        .shopify-b2b-page #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        .shopify-b2b-page #summary-stats .summary-badges-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px !important;
        }
        .shopify-b2b-page #summary-stats .summary-badges-row .badge {
            font-size: 0.85rem !important;
            padding: 0.35rem 0.55rem !important;
            white-space: nowrap;
        }
        .shopify-b2b-page #discount-input-container { padding: 8px 12px !important; }

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

        /* Column visibility — grouped menu (Basic / Pricing / Advertisement / Other) */
        #column-dropdown-menu.column-dropdown-multicol,
        #column-dropdown-menu.analytics-col-vis-menu {
            column-count: unset !important;
            right: 0 !important;
            left: auto !important;
        }
        #reverb-table {
            width: 100% !important;
        }
        #reverb-table .tabulator-tableholder {
            overflow-x: auto !important;
        }
        #reverb-table .tabulator-cell {
            white-space: nowrap !important;
            text-overflow: clip !important;
        }

        /* ========== SKU / PARENT SEARCH (inline after NPFT badge) ========== */
        .shopify-b2b-search-group {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            height: 38px;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .shopify-b2b-search-group:focus-within {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
        .shopify-b2b-search-group .input-group-text {
            background: transparent;
            border: 0;
            color: #94a3b8;
            padding: 0 8px 0 10px;
            font-size: 0.8rem;
        }
        .shopify-b2b-search-group #sku-search,
        .shopify-b2b-search-group #parent-search {
            border: 0;
            background: transparent;
            box-shadow: none !important;
            height: 36px;
            font-size: 0.85rem;
            color: #1e293b;
            padding-left: 2px;
        }
        .shopify-b2b-search-group #sku-search::placeholder,
        .shopify-b2b-search-group #parent-search::placeholder { color: #94a3b8; }
        .shopify-b2b-search-group #sku-search:focus,
        .shopify-b2b-search-group #parent-search:focus { outline: none; border: 0; }

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
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'shopify_b2b'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Business Analytics',
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
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Google LMP Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="#" id="lmpOpenGoogleSearch" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener">
                            <i class="fa fa-search"></i> Open Google Search
                        </a>
                    </div>

                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fa fa-plus-circle"></i> Add Competitor Manually</strong>
                        </div>
                        <div class="card-body">
                            <form id="addGoogleLmpForm" class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label"><strong>SKU</strong></label>
                                    <input type="text" class="form-control" id="addLmpSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Product ID</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="addLmpProductId" placeholder="Google product id" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Source</strong></label>
                                    <input type="text" class="form-control" id="addLmpSource" placeholder="e.g. Walmart">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="addLmpPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Title</strong></label>
                                    <input type="text" class="form-control" id="addLmpTitle" placeholder="Product title">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Link</strong></label>
                                    <input type="url" class="form-control" id="addLmpLink" placeholder="https://...">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-plus"></i> Add Competitor
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fa fa-undo"></i> Clear
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

    <div class="shopify-b2b-page">
    <div class="row">
        <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body py-2 shopify-b2b-controls">
                {{-- Filter bar (Amazon-style flex-wrap + auto-width selects) --}}
                <div class="d-flex align-items-center flex-wrap shopify-b2b-toolbar" id="shopify-b2b-filter-bar">
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
                        title="CVR = B2B L30 ÷ Views">
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
                        <ul class="dropdown-menu dropdown-menu-end column-dropdown-multicol" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>

                    <button id="export-btn" class="btn btn-sm btn-dark" title="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>

                    {{-- Push SPRICE to Shopify B2B via /pricing-master-cvr (marketplace=sb2b) --}}
                    <button type="button" id="push-shopify-b2b-prices-btn" class="btn btn-sm btn-success"
                        title="Push SPRICE to Shopify B2B (pricing-master-cvr sb2b) for selected SKUs">
                        <i class="fas fa-paper-plane"></i> Push
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'shopify_b2b'])

                    <div class="btn-group">
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
                         Formula: sprice = (LP × (1 + ROI%/100)) / margin   (margin = 0.95 for Shopify B2B; no Ship) --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100)) / 0.95 on every selected row (B2B: no Ship)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 90px;"
                            title="Target ROI% applied to all selected rows">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100)) / 0.95 for every selected row (no Ship)">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = LP / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). No Ship. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = LP / (0.95 − Target GPFT%/100) on every selected row (B2B: no Ship)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 90px;"
                            title="Target GPFT% applied to all selected rows. Must be less than the Shopify B2B take-home margin (< 95%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = LP / (0.95 − Target GPFT%/100) for every selected row (no Ship)">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>

                <!-- Summary Stats (order:-1 → shown above filters, same as Amz) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2 summary-badges-row">
                        <span class="badge bg-success fs-6 p-2 d-none" id="total-pft-amt-badge" style="color: black; font-weight: bold;">PFT: $0</span>
                        {{-- Sales is the L30 net-sales total from the actual /shopify page
                             (shopify_b2b_daily_data with marketplace exclusions). Server-rendered so it
                             always matches /shopify-b2b/daily-sales and the eBay row pattern on /all-marketplace-master.
                             Page filters do not narrow this number — it's the page-level reference. --}}
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                              style="color: black; font-weight: bold;"
                              title="L30 Net Sales from shopify_b2b_daily_data (matches /shopify-b2b/daily-sales Net Sales card and the Shopify row on /all-marketplace-master). Page-level total — unaffected by table filters.">Sales: ${{ number_format((float) ($shopifyB2bL30Sales ?? 0), 0) }}</span>
                        {{-- Orders: distinct order_id count from the same source. New badge requested
                             so this page agrees with /shopify and /all-marketplace-master Shopify B2B row. --}}
                        <span class="badge bg-secondary fs-6 p-2" id="total-orders-badge"
                              style="color: white; font-weight: bold;"
                              title="L30 distinct orders from shopify_b2b_daily_data (matches /shopify-b2b/daily-sales and /all-marketplace-master Shopify B2B row).">Orders: {{ number_format((int) ($shopifyB2bL30Orders ?? 0)) }}</span>
                        {{-- Qty: Σ ebay_order_items.quantity-equivalent from shopify_b2b_daily_data for the
                             same L30 window. Same value /shopify reports as "Total Qty" and
                             /all-marketplace-master shows in the Shopify row's "Qty items" cell. --}}
                        <span class="badge fs-6 p-2" id="total-qty-badge"
                              style="background-color: #6f42c1; color: white; font-weight: bold;"
                              title="L30 units sold from shopify_b2b_daily_data (matches /shopify-b2b/daily-sales and /all-marketplace-master Shopify B2B row).">Qty: {{ number_format((int) ($shopifyB2bL30Qty ?? 0)) }}</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 d-none" id="avg-price-badge" style="color: black; font-weight: bold;">Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none" id="total-inv-badge" style="color: black; font-weight: bold;">INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">L30: 0</span>
                        <span class="badge fs-6 p-2" id="total-views-badge" style="background-color: #0d6efd; color: white; font-weight: bold;" title="Sum of L30 product page views (sessions)">Views: 0</span>
                        <span class="badge fs-6 p-2" id="avg-cvr-badge" style="background-color: #20c997; color: #000; font-weight: bold;" title="Overall CVR = Qty ÷ Views">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-b2b-l30-badge" style="color: black; font-weight: bold;">B2B: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter B2B L30 = 0">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="shopifyb2b-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'shopifyb2b-lmp-missing-badge', 'lmpChannelKey' => 'shopifyb2b'])
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'shopifyb2b-price-gt-lmp-badge', 'pglChannelKey' => 'shopifyb2b', 'pglPriceField' => 'Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'shopifyb2b-price-lt80-lmp-badge', 'pltChannelKey' => 'shopifyb2b', 'pltPriceField' => 'Price'])
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter B2B L30 > 0">&gt;0 Sold: 0</span>
                        <span class="badge bg-info fs-6 p-2 d-none" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;" title="GROI% = Σ PFT ÷ Σ COGS × 100 — same as /shopify-b2b/daily-sales and Amz/eBay badges">GROI: 0%</span>
                        <span class="badge fs-6 p-2" id="nroi-percent-badge" style="background-color: #e83e8c; color: white; font-weight: bold;">NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amz">&lt; Amz: 0</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amz">&gt; Amz: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs">Miss: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Ads: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Spend: $0</span>
                        <span class="badge fs-6 p-2" id="avg-npft-badge" style="background-color: #fd7e14; color: white; font-weight: bold;">NPFT: 0%</span>
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
                        <button type="button" id="push-selected-shopify-b2b-btn" class="btn btn-success btn-sm"
                            title="Push SPRICE to Shopify for selected SKUs">
                            <i class="fas fa-paper-plane"></i> Push
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
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'shopify_b2b'])
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "shopify_b2b_tabulator_column_visibility";
    /** Stored in DB table channel_tabulator_column_settings (shared across all users — same as Amazon). */
    const TABULATOR_COLUMN_CHANNEL = 'shopify_b2b_tabulator';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    /** L30 sales + distinct order count from /shopify (shopify_b2b_daily_data with
     *  marketplace exclusions). Page-level totals — used to drive the Total
     *  Sales and Orders badges so this page agrees with /shopify and the
     *  /all-marketplace-master Shopify B2B row. Page filters do NOT narrow these
     *  numbers, mirroring how /shopify reports them. */
    const SHOPIFY_B2B_L30_SALES   = {{ (float) ($shopifyB2bL30Sales   ?? 0) }};
    const SHOPIFY_B2B_L30_ORDERS  = {{ (int)   ($shopifyB2bL30Orders  ?? 0) }};
    const SHOPIFY_B2B_L30_QTY     = {{ (int)   ($shopifyB2bL30Qty     ?? 0) }};
    /** Profit / cost / spend + derived percentages from the same /shopify snapshot
     *  the master Shopify row uses. Drives Total PFT, GPFT, Total Spend, TCOS,
     *  NPFT, and NROI badges on this page so they agree with /all-marketplace-master. */
    const SHOPIFY_B2B_TOTAL_PFT   = {{ (float) ($shopifyB2bTotalPft   ?? 0) }};
    const SHOPIFY_B2B_TOTAL_COGS  = {{ (float) ($shopifyB2bTotalCogs  ?? 0) }};
    const SHOPIFY_B2B_TOTAL_SPEND = {{ (float) ($shopifyB2bTotalSpend ?? 0) }};
    const SHOPIFY_B2B_GPFT_PCT    = {{ (float) ($shopifyB2bGpftPct    ?? 0) }};
    const SHOPIFY_B2B_GROI_PCT    = {{ (float) ($shopifyB2bGroiPct    ?? 0) }};
    const SHOPIFY_B2B_TCOS_PCT    = {{ (float) ($shopifyB2bTcosPct    ?? 0) }};
    const SHOPIFY_B2B_NPFT_PCT    = {{ (float) ($shopifyB2bNpftPct    ?? 0) }};
    const SHOPIFY_B2B_NROI_PCT    = {{ (float) ($shopifyB2bNroiPct    ?? 0) }};

    /**
     * Channel Ads% (TCOS badge) — same role as Amazon AMAZON_CHANNEL_ADS_PCT.
     */
    function shopifyChannelAdsPct() {
        return parseFloat(SHOPIFY_B2B_TCOS_PCT) || 0;
    }

    /** Amazon-style parent summary row (SKU like "PARENT 10 FR"). */
    function isShopifyB2bParentRow(row) {
        if (!row) return false;
        if (row.is_parent_summary === true) return true;
        const sku = String(row['(Child) sku'] || '').toUpperCase();
        return sku.includes('PARENT');
    }
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'shopify_b2b'])

    function shopifyB2bRowSpriceForAlert(data) {
        let sprice = parseFloat(data && data.SPRICE) || 0;
        if (typeof chPromoSpriceFromStdTPromo === 'function' && !isShopifyB2bParentRow(data)) {
            const calc = chPromoSpriceFromStdTPromo(data);
            if (calc > 0) sprice = calc;
        }
        return sprice;
    }
    function shopifyB2bHasBlueTriangle(data) {
        if (isShopifyB2bParentRow(data)) return false;
        const sprice = shopifyB2bRowSpriceForAlert(data);
        const price = parseFloat(data && data.Price) || 0;
        return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
    }
    function syncShopifyB2bTriangleBadgeState() {
        $('#shopifyb2b-blue-triangle-badge').css({
            outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: blueTriangleFilterActive ? '2px' : ''
        });
    }

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function shopifyB2bStdPrcChangeDotMeta(stdPrc, comparePrice) {
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

    function shopifyB2bStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = shopifyB2bStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyShopifyB2bStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            if (!d || isShopifyB2bParentRow(d)) return;
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
        applyShopifyB2bStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    /**
     * Net ROI (NROI% / SNROI) — B2B unit formula (includes Ship):
     *   ((Price × 0.95 − LP − Ship − Price × Ads%/100) / LP) × 100
     * Ads% = channel Ads badge (TCOS).
     */
    function shopifyComputeNetRoi(price, lp, ship, adsPct) {
        price = parseFloat(price);
        lp = parseFloat(lp);
        ship = parseFloat(ship) || 0;
        if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return 0;
        const ads = (adsPct != null && isFinite(parseFloat(adsPct)))
            ? parseFloat(adsPct)
            : shopifyChannelAdsPct();
        const grossPft = (price * 0.95) - lp - ship;
        const adSpend = price * (ads / 100);
        return ((grossPft - adSpend) / lp) * 100;
    }

    function shopifyComputeSnroi(sprice, lp, ship, adsPct) {
        return shopifyComputeNetRoi(sprice, lp, ship, adsPct);
    }

    let table = null;
    let lmpMissingFilterActive = false;
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;
    let blueTriangleFilterActive = false;
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

    function escLmpAttr(val) {
        return String(val == null ? '' : val)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
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
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    function linkedLmpSkuFormatter(cell) {
        const row = cell.getRow().getData();
        if (isShopifyB2bParentRow(row)) return '';
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
        const badges = skus.length ? skus.map(function (sku) {
            const skuText = String(sku || '').trim();
            const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
            const removeBtn = isSelf ? '' : `<button type="button" class="btn-close sku-link-lmp-remove" data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove"></button>`;
            return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1"><span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}</span>`;
        }).join('') : '<span class="text-muted fst-italic">No SKUs</span>';
        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function linkedLmpSkuAddFormatter(cell) {
        const row = cell.getRow().getData();
        if (isShopifyB2bParentRow(row)) return '';
        const rowSku = rowSkuForLinkLmp(row);
        if (!rowSku) return '';
        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn" title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}"><i class="fas fa-plus"></i></button>
        </div>`;
    }

    function applyAffectedLinkedSkuRows() {
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
            applyAffectedLinkedSkuRows();
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
            applyAffectedLinkedSkuRows();
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
            const filteredData = table.getData('active').filter(row => !isShopifyB2bParentRow(row));
            
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
         * Target ROI% bulk apply (Shopify B2B, margin = 0.95)
         * ---------------------------------------------------
         * For every selected row with a usable LP, back-solve the sale price so the
         * resulting SROI column matches Target ROI%:
         *     SROI = ((sprice * margin − lp) / lp) * 100
         *   → sprice = (lp * (1 + ROI%/100)) / margin  (no Ship)
         * Optimistic SGPFT / SROI / SNPFT / SNROI are written client-side and the
         * bulk save endpoint (/shopify-b2b/save-sprice) recomputes them server-side.
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

            const SHOPIFY_B2B_MARGIN = 0.95;
            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                if (isShopifyB2bParentRow(rowData)) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const ads  = shopifyChannelAdsPct();

                const candidate = (lp + ship) * roiMultiplier / SHOPIFY_B2B_MARGIN;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const grossProfit = (newSprice * SHOPIFY_B2B_MARGIN) - lp - ship;
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
         * Target GPFT% bulk apply (Shopify B2B, margin = 0.95)
         * ----------------------------------------------------
         * Mirrors Target ROI but back-solves so SGPFT = Target GPFT%:
         *     SGPFT = ((sprice * margin − lp) / sprice) * 100
         *   → sprice = lp / (margin − GPFT%/100)  (no Ship)
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

            const SHOPIFY_B2B_MARGIN = 0.95;
            const denom = SHOPIFY_B2B_MARGIN - (targetGpftPct / 100);
            if (denom <= 0) {
                showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < 95% (Shopify B2B take-home).`, 'error');
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
                if (isShopifyB2bParentRow(rowData)) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const ads  = shopifyChannelAdsPct();

                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const grossProfit = (newSprice * SHOPIFY_B2B_MARGIN) - lp - ship;
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

        /** Push one SKU SPRICE to Shopify B2B via /pricing-master-cvr (marketplace=sb2b). */
        function pushShopifyB2bPrice(sku, price, $btn, row) {
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
                url: @json(route('cvr.master.push.price')),
                method: 'POST',
                timeout: 120000,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { sku: sku, price: price, marketplace: 'sb2b' },
                success: function(response) {
                    let finalStatus = 'error';
                    if (response && response.success) {
                        finalStatus = 'pushed';
                        showToast(response.message || ('Shopify B2B price pushed for SKU: ' + sku), 'success');
                    } else {
                        showToast((response && response.message) || 'Shopify B2B push failed', 'error');
                    }

                    if (row) {
                        row.update({ SPRICE_STATUS: finalStatus });
                        row.reformat();
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
                    const errorMsg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : 'Unknown error';
                    showToast('Shopify B2B push failed: ' + errorMsg, 'error');
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
                if (isShopifyB2bParentRow(d)) return;
                const sku = d['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                const price = parseFloat(d.SPRICE) || 0;
                if (price > 0) {
                    toPush.push({ sku: sku, price: price, row: row });
                }
            });

            if (toPush.length === 0) {
                showToast('No selected SKUs have S PRC > 0', 'warning');
                return;
            }

            if (!confirm('Push ' + toPush.length + ' price(s) to Shopify B2B (pricing-master-cvr sb2b)?')) return;

            const $btns = $('#push-shopify-b2b-prices-btn, #push-selected-shopify-b2b-btn');
            const originalHtml = $('#push-shopify-b2b-prices-btn').html();
            $btns.prop('disabled', true);
            $('#push-shopify-b2b-prices-btn').html('<i class="fas fa-spinner fa-spin"></i> Pushing...');

            let idx = 0;
            let okCount = 0;
            let failCount = 0;

            function next() {
                if (idx >= toPush.length) {
                    $btns.prop('disabled', false);
                    $('#push-shopify-b2b-prices-btn').html(originalHtml);
                    showToast('Push done: ' + okCount + ' ok, ' + failCount + ' failed', failCount ? 'warning' : 'success');
                    return;
                }
                const item = toPush[idx++];
                item.row.update({ SPRICE_STATUS: 'processing' });
                item.row.reformat();

                $.ajax({
                    url: @json(route('cvr.master.push.price')),
                    method: 'POST',
                    timeout: 120000,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: item.sku, price: item.price, marketplace: 'sb2b' },
                    success: function(response) {
                        if (response && response.success) {
                            okCount++;
                            item.row.update({ SPRICE_STATUS: 'pushed' });
                        } else {
                            failCount++;
                            item.row.update({ SPRICE_STATUS: 'error' });
                        }
                        item.row.reformat();
                        setTimeout(next, 300);
                    },
                    error: function() {
                        failCount++;
                        item.row.update({ SPRICE_STATUS: 'error' });
                        item.row.reformat();
                        setTimeout(next, 300);
                    }
                });
            }

            next();
        }

        $('#push-shopify-b2b-prices-btn, #push-selected-shopify-b2b-btn').on('click', function() {
            pushSelectedShopifyPrices();
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

        // Missing badge click handler - filter SKUs missing in Shopify B2B
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
            
            const filteredData = table.getData('active').filter(row => !isShopifyB2bParentRow(row));
            
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

                        // Calculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2B)
                        const percentage = 0.95; // Shopify B2B margin
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
                        // Calculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2B)
                        const percentage = 0.95; // Shopify B2B margin
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        const ads = shopifyChannelAdsPct();
                        
                        // Always: SPRICE = (A Price × 0.75) − Ship
                        const calcSprice = Math.max(0.01, +((amazonPrice * 0.75) - ship).toFixed(2));
                        const grossProfit = (calcSprice * percentage) - lp - ship;
                        const sgpft = calcSprice > 0 ? (grossProfit / calcSprice) * 100 : 0;
                        const snpft = sgpft - ads;
                        const sroi = lp > 0 ? (grossProfit / lp) * 100 : 0;
                        const snroi = shopifyComputeSnroi(calcSprice, lp, ship, ads);
                        
                        // Update the row with SPRICE and calculated values
                        row.update({
                            SPRICE: calcSprice,
                            SGPFT: sgpft,
                            SNPFT: snpft,
                            SROI: sroi,
                            SNROI: snroi,
                            has_custom_sprice: true
                        });
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: calcSprice
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
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates) {
            console.log('Saving SPRICE updates:', updates.length, 'SKUs');
            
            $.ajax({
                url: '/shopify-b2b/save-sprice',
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
                url: '/shopify-b2b/save-sprice',
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
            ajaxURL: "/shopify-b2b-data-json",
            ajaxSorting: false,
            layout: "fitData",
            layoutColumnsOnNewData: true,
            columnDefaults: {
                hozAlign: "center",
                headerHozAlign: "center",
                resizable: true,
                minWidth: 64,
            },
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
                if (isShopifyB2bParentRow(row.getData())) {
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
                        if (isShopifyB2bParentRow(rowData)) {
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
                    width: 64,
                    minWidth: 64,
                    sorter: "number"
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 68,
                    minWidth: 68,
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
                    width: 64,
                    minWidth: 64
                },
                {
                    title: "Views",
                    field: "Views",
                    hozAlign: "center",
                    width: 70,
                    minWidth: 70,
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
                    width: 72,
                    minWidth: 72,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // CVR% = B2B L30 ÷ Views (not OV L30)
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
                    title: "B2B L30",
                    field: "B2B L30",
                    hozAlign: "center",
                    width: 74,
                    minWidth: 74,
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
                        if (isShopifyB2bParentRow(rowData)) {
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
                    width: 86,
                    minWidth: 86,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (isShopifyB2bParentRow(d)) return false;
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2bParentRow(rowData)) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(rowData['A Price'] || rowData.a_price || rowData.amazon_price || 0) || 0;
                        const channelPrice = parseFloat(rowData['Price'] || rowData.price || 0) || 0;
                        const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                        const dot = shopifyB2bStdPrcChangeDotHtml(std, comparePrice);

                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Price",
                    field: "Price",
                    hozAlign: "center",
                    minWidth: 86,
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
                    width: 88,
                    minWidth: 88
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    minWidth: 88,
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
                        if (isShopifyB2bParentRow(rowData)) return '';

                        const sku = String(rowData['(Child) sku'] || '');
                        const skuEnc = encodeURIComponent(sku);
                        const lmpPrice = parseFloat(cell.getValue());
                        const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
                        const ourPrice = parseFloat(rowData.Price) || 0;
                        const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                        const linkedSkusAttr = escLmpAttr(JSON.stringify(linkedSkus));
                        const skuAttr = escLmpAttr(sku);

                        if ((!lmpPrice || lmpPrice <= 0) && totalCompetitors === 0) {
                            const url = '/repricer/google-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">' +
                                '<a href="' + url + '" target="_blank" rel="noopener" title="No Google LMP — open Google Search">' +
                                '<i class="fas fa-circle" style="color:#ff9c00;font-size:10px;"></i></a>' +
                                '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"' +
                                ' style="color:#6c757d;text-decoration:none;cursor:pointer;font-size:11px;" title="Add competitor manually">' +
                                '<i class="fa fa-plus"></i> Add</a></div>';
                        }

                        let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">';
                        if (lmpPrice > 0) {
                            const color = (ourPrice > 0 && lmpPrice < ourPrice) ? '#dc3545' : '#28a745';
                            html += '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"' +
                                ' style="color:' + color + ';font-weight:600;text-decoration:none;cursor:pointer;">$' +
                                lmpPrice.toFixed(2) + '</a>';
                        }
                        if (totalCompetitors > 0) {
                            html += '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"' +
                                ' style="color:#007bff;text-decoration:none;cursor:pointer;font-size:11px;">' +
                                '<i class="fa fa-eye"></i> View ' + totalCompetitors + '</a>';
                        } else {
                            html += '<a href="#" class="view-lmp-competitors" data-sku="' + skuAttr + '" data-linked-skus="' + linkedSkusAttr + '"' +
                                ' style="color:#6c757d;text-decoration:none;cursor:pointer;font-size:11px;" title="Add competitor manually">' +
                                '<i class="fa fa-plus"></i> Add</a>';
                        }
                        html += '</div>';
                        return html;
                    }
                },
                {
                    title: "Diff",
                    field: "lmp_diff_pct",
                    hozAlign: "center",
                    width: 84,
                    minWidth: 84,
                    headerTooltip: "(Google LMP − Shopify Price) / LMP × 100",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = function(rd) {
                            if (isShopifyB2bParentRow(rd)) return -Infinity;
                            const lmp = parseFloat(rd.lmp_price || 0);
                            const price = parseFloat(rd.Price || 0);
                            if (!lmp || lmp <= 0) return -Infinity;
                            return ((lmp - price) / lmp) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2bParentRow(rowData)) return '';

                        const lmp = parseFloat(rowData.lmp_price || 0);
                        const price = parseFloat(rowData.Price || 0);
                        if (!lmp || lmp <= 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }
                        const diff = ((lmp - price) / lmp) * 100;
                        const color = diff < 0 ? '#dc3545' : '#28a745';
                        return '<span style="color:' + color + ';font-weight:600;">' + diff.toFixed(1) + '%</span>';
                    }
                },
                {
                    title: "Sku Link LMP",
                    field: "linked_lmp_skus",
                    hozAlign: "left",
                    headerHozAlign: "center",
                    width: 220,
                    headerSort: false,
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
                        }
                    },
                },
                {
                    title: "+",
                    field: "linked_lmp_sku_add",
                    hozAlign: "center",
                    headerHozAlign: "center",
                    width: 52,
                    headerSort: false,
                    cssClass: "linked-sku-add-col",
                    formatter: linkedLmpSkuAddFormatter,
                    cellClick: function(e, cell) {
                        if (e.target.closest('.sku-link-lmp-add-btn')) {
                            e.preventDefault();
                            e.stopPropagation();
                            openLinkedSkuModal(cell.getRow().getData());
                        }
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
                    title: "PFT %",
                    field: "NPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same as Amazon PFT %: GPFT% − channel Ads% (TCOS badge)
                        const rowData = cell.getRow().getData();
                        const gpft = parseFloat(rowData['GPFT%']) || 0;
                        const ads = parseFloat(SHOPIFY_B2B_TCOS_PCT) || 0;
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
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2bParentRow(rowData)) return '';
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "number",
                    editable: function(cell) {
                        return !isShopifyB2bParentRow(cell.getRow().getData());
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    sorter: "number",
                    headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). Blue triangle = S PRC ≠ Price. Red text = S PRC > LMP.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2bParentRow(rowData)) {
                            return '';
                        }
                        let value = parseFloat(cell.getValue() || 0);
                        if (typeof chPromoSpriceFromStdTPromo === 'function') {
                            const calc = chPromoSpriceFromStdTPromo(rowData);
                            if (calc > 0) value = calc;
                        }
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        const live = parseFloat(rowData.Price) || 0;
                        const lmp = parseFloat(rowData.lmp_price) || 0;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';

                        if (!(value > 0)) return '';
                        const formatted = '$' + value.toFixed(2);
                        const overLmp = lmp > 0 && value > lmp;
                        const priceHtml = overLmp
                            ? `<span style="color:#dc3545;font-weight:600;${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`
                            : `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`;
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${blueTri}</span>`;
                    },
                    width: 92
                },
                {
                    title: "Push",
                    field: "_push",
                    hozAlign: "center",
                    headerSort: false,
                    width: 50,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (isShopifyB2bParentRow(rowData)) return '';
                        const sku = rowData['(Child) sku'] || '';
                        const sprice = parseFloat(rowData.SPRICE) || 0;
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
                        return `<button type="button" class="btn btn-sm push-shopify-b2b-btn" data-sku="${sku.replace(/"/g, '&quot;')}" title="${title}" style="border:none;background:none;color:${color};padding:0;cursor:pointer;">${icon}</button>`;
                    },
                    cellClick: function(e, cell) {
                        const $target = $(e.target);
                        if (!$target.hasClass('push-shopify-b2b-btn') && !$target.closest('.push-shopify-b2b-btn').length) return;
                        e.stopPropagation();
                        const $btn = $target.hasClass('push-shopify-b2b-btn') ? $target : $target.closest('.push-shopify-b2b-btn');
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const price = parseFloat(rowData.SPRICE) || 0;
                        pushShopifyB2bPrice(sku, price, $btn, cell.getRow());
                    }
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
                    title: "SNPFT",
                    field: "SNPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Same as Amazon SNPFT: SGPFT − channel Ads% (TCOS badge)
                        const rowData = cell.getRow().getData();
                        const sgpft = parseFloat(rowData['SGPFT']) || 0;
                        const ads = parseFloat(SHOPIFY_B2B_TCOS_PCT) || 0;
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
                            parseFloat(SHOPIFY_B2B_TCOS_PCT) || 0
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
                url: '{{ url("/shopify-b2b-update-listed-live") }}',
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
                        applyShopifyB2bStandardPriceToLinkedRows(sku, saved, response.applied_skus);
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

            if (field === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                if (isShopifyB2bParentRow(rowData)) return;
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                // Recalculate SGPFT, SNPFT, SROI, SNROI (95% margin for Shopify B2B)
                const percentage = 0.95; // Shopify B2B margin
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

            // CVR filter — B2B L30 ÷ Views
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

            // Missing filter - show SKUs missing in Shopify B2B
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
                    return shopifyB2bHasBlueTriangle(data);
                });
            }

            // Row type filter: All Rows / Parents / SKUs (same as Amazon)
            const parentFilter = $('#parent-filter').val();
            if (parentFilter === 'parents') {
                table.addFilter(function(data) {
                    return isShopifyB2bParentRow(data);
                });
            } else if (parentFilter === 'skus') {
                table.addFilter(function(data) {
                    return !isShopifyB2bParentRow(data);
                });
            }

            updateSummary();
        }

        if (window.LmpMissingBadge) {
            LmpMissingBadge.bind({
                badge: '#shopifyb2b-lmp-missing-badge',
                getActive: function() { return lmpMissingFilterActive; },
                onToggle: function(on) {
                    lmpMissingFilterActive = on;
                    applyFilters();
                }
            });
        }
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#shopifyb2b-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#shopifyb2b-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        $('#shopifyb2b-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                lmpMissingFilterActive = false;
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
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
                if (isShopifyB2bParentRow(row)) return false;
                
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
            // GROI% = Σ PFT ÷ Σ COGS × 100 (same as Amazon / eBay / /shopify-b2b/daily-sales)
            // — NOT a simple average of per-row ROI% values.
            const groiFromRows = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            // Page-level financial badges use the L30 snapshot from shopify_b2b_daily_data
            // (same source as /shopify-b2b/daily-sales). GROI badge prefers that snapshot.
            $('#total-pft-amt-badge').text(`PFT: $${Math.round(SHOPIFY_B2B_TOTAL_PFT).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Sales: $${Math.round(SHOPIFY_B2B_L30_SALES).toLocaleString()}`);
            $('#total-orders-badge').text(`Orders: ${SHOPIFY_B2B_L30_ORDERS.toLocaleString()}`);
            $('#total-qty-badge').text(`Qty: ${SHOPIFY_B2B_L30_QTY.toLocaleString()}`);
            $('#avg-gpft-badge').text(`GPFT: ${SHOPIFY_B2B_GPFT_PCT.toFixed(1)}%`);
            $('#avg-price-badge').text(`Price: $${avgPrice.toFixed(2)}`);
            $('#total-inv-badge').text(`INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`L30: ${totalL30.toLocaleString()}`);
            const overallCvr = totalViews > 0 ? (SHOPIFY_B2B_L30_QTY / totalViews) * 100 : 0;
            $('#total-views-badge').text(`Views: ${totalViews.toLocaleString()}`);
            $('#avg-cvr-badge').text(`CVR: ${Math.round(overallCvr)}%`);
            $('#total-b2b-l30-badge').text(`B2B: ${totalB2BL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            if (window.LmpMissingBadge) {
                LmpMissingBadge.update('#shopifyb2b-lmp-missing-badge', allData, 'shopifyb2b');
            }
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.update('#shopifyb2b-price-gt-lmp-badge', allData, 'shopifyb2b', 'Price');
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.update('#shopifyb2b-price-lt80-lmp-badge', allData, 'shopifyb2b', 'Price');
            }
            let blueTriangleCount = 0;
            allData.forEach(function(row) {
                if (shopifyB2bHasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#shopifyb2b-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncShopifyB2bTriangleBadgeState === 'function') syncShopifyB2bTriangleBadgeState();
            $('#more-sold-count-badge').text(`>0 Sold: ${moreSoldCount}`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(SHOPIFY_B2B_TOTAL_COGS || totalCogs).toLocaleString()}`);
            const groiBadge = (typeof SHOPIFY_B2B_GROI_PCT === 'number' && !isNaN(SHOPIFY_B2B_GROI_PCT))
                ? SHOPIFY_B2B_GROI_PCT
                : groiFromRows;
            $('#roi-percent-badge').text(`GROI: ${groiBadge.toFixed(1)}%`);
            $('#less-amz-badge').text(`< Amz: ${lessAmzCount}`);
            $('#more-amz-badge').text(`> Amz: ${moreAmzCount}`);
            $('#missing-count-badge').text(`Miss: ${missingCount}`);
            
            // Ads / Spend / NPFT / NROI — B2B has no channel ads, so NPFT≈GPFT and NROI≈GROI.
            $('#total-tcos-badge').text(`Ads: ${Math.round(SHOPIFY_B2B_TCOS_PCT)}%`);
            $('#total-spend-badge').text(`Spend: $${Math.round(SHOPIFY_B2B_TOTAL_SPEND).toLocaleString()}`);
            $('#avg-npft-badge').text(`NPFT: ${SHOPIFY_B2B_NPFT_PCT.toFixed(1)}%`);
            $('#nroi-percent-badge').text(`NROI: ${SHOPIFY_B2B_NROI_PCT.toFixed(1)}%`);
        }

        /*
         * Column visibility persists in shared DB table channel_tabulator_column_settings
         * under channel = 'shopify_b2b_tabulator' — same /tabulator-column-visibility
         * endpoint Amazon / ebay / mfrg tabulators use.
         */
        function buildColumnDropdown(savedVisibility) {
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'column-dropdown-menu',
                    storageKey: 'shopify_b2b_col_cats_v1',
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
                if (typeof window.chPromoAutofitColumns === 'function') {
                    window.chPromoAutofitColumns(table);
                }
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
            }, 100);
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.closest && e.target.closest('.analytics-col-vis-menu')) return;
            if (e.target.classList.contains('column-toggle')) {
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

        function updateShopifyB2bLmpRow(sku, competitors, lowestPrice) {
            if (!table || !sku) return;
            const list = Array.isArray(competitors) ? competitors : [];
            const lowest = (lowestPrice != null && lowestPrice > 0)
                ? parseFloat(lowestPrice)
                : (list.length
                    ? Math.min.apply(null, list.map(c => parseFloat(c.price) || 0).filter(p => p > 0))
                    : null);
            const lowestLink = list.find(c => Math.abs((parseFloat(c.price) || 0) - (lowest || 0)) < 0.01) || list[0] || null;

            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (String(d['(Child) sku'] || '') !== String(sku)) return;
                row.update({
                    lmp_price: lowest && lowest > 0 ? Math.round(lowest * 100) / 100 : null,
                    lmp_link: lowestLink ? (lowestLink.product_link || lowestLink.link || null) : null,
                    lmp_source: lowestLink ? (lowestLink.source || null) : null,
                    lmp_title: lowestLink ? (lowestLink.product_title || lowestLink.title || null) : null,
                    lmp_entries: list,
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
                    '<a href="/repricer/google-search?sku=' + encodeURIComponent(sku) + '" target="_blank" rel="noopener">search on Google</a>.' +
                    '</div>'
                );
                return;
            }

            const lowest = (lowestPrice != null && lowestPrice > 0)
                ? parseFloat(lowestPrice)
                : Math.min.apply(null, list.map(c => parseFloat(c.price) || 0).filter(p => p > 0));

            let html = '';
            if (lowest && lowest > 0) {
                html += '<div class="mb-3"><span class="badge bg-success">Google lowest: $' + lowest.toFixed(2) + '</span></div>';
            }

            html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">' +
                '<thead class="table-light"><tr>' +
                '<th>#</th><th>Price</th><th>Source</th><th>Product ID</th><th>Title</th><th>Rating</th><th>Reviews</th><th>Link</th><th>Actions</th>' +
                '</tr></thead><tbody>';

            list.forEach(function(item, index) {
                const price = parseFloat(item.price) || 0;
                const isLowest = price > 0 && lowest > 0 && Math.abs(price - lowest) < 0.01;
                const link = item.product_link || item.link || '';
                const title = item.product_title || item.title || '';
                const titleShort = title.length > 50 ? title.substring(0, 50) + '...' : title;
                const source = item.source || '—';
                const productId = item.product_id || '—';
                const image = item.image || '';
                const imgHtml = image
                    ? '<img src="' + escLmpAttr(image) + '" alt="" class="rounded me-1" style="height:40px;width:40px;object-fit:contain;" onerror="this.style.display=\'none\'">'
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
                    ? '<a href="' + escLmpAttr(link) + '" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="Open product"><i class="fa fa-external-link"></i></a>'
                    : '<span class="text-muted">—</span>';
                const delBtn = '<button type="button" class="btn btn-sm btn-danger delete-google-lmp-btn" data-id="' +
                    escLmpAttr(item.id) + '" data-sku="' + escLmpAttr(sku) + '" data-price="' + price +
                    '" title="Delete this competitor"><i class="fa fa-trash"></i></button>';

                html += '<tr class="' + (isLowest ? 'table-success' : '') + '">' +
                    '<td class="text-center"><strong>' + (index + 1) + '</strong></td>' +
                    '<td><div class="d-flex align-items-center">' + imgHtml + priceBadge + '</div></td>' +
                    '<td style="font-size:11px;" title="' + escLmpAttr(source) + '">' + escLmpAttr(String(source).substring(0, 30)) + '</td>' +
                    '<td style="font-size:11px;">' + escLmpAttr(productId) + '</td>' +
                    '<td style="font-size:11px;" title="' + escLmpAttr(title) + '">' + escLmpAttr(titleShort || '—') + '</td>' +
                    '<td class="text-center">' + rating + '</td>' +
                    '<td class="text-center">' + reviews + '</td>' +
                    '<td class="text-center">' + linkBtn + '</td>' +
                    '<td class="text-center">' + delBtn + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }

        function loadGoogleLmpModal(sku, linkedLmpSkus) {
            $('#lmpSku').text(sku);
            $('#addLmpSku').val(sku);
            $('#addLmpProductId').val('');
            $('#addLmpSource').val('');
            $('#addLmpPrice').val('');
            $('#addLmpTitle').val('');
            $('#addLmpLink').val('');
            $('#lmpOpenGoogleSearch').attr('href', '/repricer/google-search?sku=' + encodeURIComponent(sku));
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
                url: '/google-lmp-data',
                method: 'GET',
                traditional: true,
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        renderGoogleLmpList(sku, response.competitors || [], response.lowest_price);
                        updateShopifyB2bLmpRow(sku, response.competitors || [], response.lowest_price);
                    } else {
                        $('#lmpDataList').html(
                            '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> ' +
                            escLmpAttr(response.error || 'No competitors found. Add one manually above.') +
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
                        escLmpAttr(msg) + '</div>'
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
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

            $.ajax({
                url: '/google-lmp-add',
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
                url: '/google-lmp-delete',
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

