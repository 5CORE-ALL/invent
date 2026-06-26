@extends('layouts.vertical', ['title' => 'Ebay - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* LMP modal: full-viewport backdrop (avoid black gaps behind modal) */
        #lmpModal {
            z-index: 1060 !important;
        }

        body.modal-open .modal-backdrop {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
        }

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

        /* Custom pagination label (match ebay2-tabulator-view) */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
        }

        /* Parent row light blue background */
        .tabulator-row.ebay-parent-row,
        .tabulator-row.ebay-parent-row .tabulator-cell {
            background-color: #b3e5fc !important;
        }

        /* Play / Pause parent navigation (same as product-master) */
        .time-navigation-group {
            margin-left: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Status circle for DIL filter */
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

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        .status-circle.blue {
            background-color: #0d6efd;
        }

        /* Summary badges: same layout as Ebay 2 Analytics — one row, equal flex share, scaled text */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        /* Image column hover preview (same pattern as forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

        #summary-stats .ebay2-summary-badge-row>.badge {
            flex: 1 1 0;
            min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 5px 0;
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, .175);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .manual-dropdown-container .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
        }

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay - Analytics',
        'sub_title' => 'Tabulator view — pricing, ads, and inventory',
    ])
    <div class="ebay-tabulator-page">
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all" selected>All E L30</option>
                        <option value="zero">0 E L30</option>
                        <option value="more">E L30 &gt; 0</option>
                    </select>

                    <select id="growth-sign-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="eBay E L30 vs E L60: (L30 − L60) / L60 × 100; L60=0 and L30&gt;0 counts as +100%">
                        <option value="all" selected>All Growth</option>
                        <option value="negative">Negative Only</option>
                        <option value="zero">Zero Only</option>
                        <option value="positive">Positive Only</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex flex-column gap-1 pricing-filter-item" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40plus">Above 40%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="cvr-trend-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR trend</option>
                        <option value="l60_gt_l30">CVR 60 &gt; CVR 30</option>
                        <option value="l30_gt_l60">CVR 30 &gt; CVR 60</option>
                        <option value="equal">CVR 60 = CVR 30</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">SPRICE</option>
                        <option value="blank">Blank SPRICE only</option>
                    </select>

                    {{-- Target ROI% bulk control — back-solves SPRICE for selected rows so SGROI = Target ROI%. --}}
                    {{-- Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin  (margin = MarketplacePercentage take-home for eBay) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target ROI% — sets SPRICE = (LP × (1 + Target ROI%/100) + Ship) / margin on every selected row (accounts for eBay fees + shipping)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply SPRICE'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE = (LP \u00d7 (1 + Target ROI%/100) + Ship) / margin for every selected row">
                            <i class="fas fa-calculator"></i> Apply SPRICE
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves SPRICE for selected rows so SGPFT = Target GPFT%. --}}
                    {{-- Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets SPRICE = (LP + Ship) / (margin − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply SPRICE'. Must be less than the eBay take-home margin (e.g. < 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE = (LP + Ship) / (margin \u2212 Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply SPRICE
                        </button>
                    </div>

                    <!-- DIL Filter -->
                    <div class="manual-dropdown-container pricing-filter-item" id="dil-filter-wrapper">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.66%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.66-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="ebay-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    <button type="button" class="btn btn-sm btn-success pricing-filter-item" data-bs-toggle="modal"
                        data-bs-target="#exportModal">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    <button id="export-lmp-btn" class="btn btn-sm btn-warning pricing-filter-item">
                        <i class="fas fa-file-export"></i> Export LMP
                    </button>

                    <button type="button" class="btn btn-sm btn-primary pricing-filter-item" data-bs-toggle="modal"
                        data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import Ratings
                    </button>

                    <a href="{{ url('/ebay-ratings-sample') }}" class="btn btn-sm btn-info pricing-filter-item">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>

                    {{-- SBID Rule button — opens the same shared rule editor as /ebay/campaign-ads.
                         Edits are stored centrally (ebay_sbid_rules.key = ebay1) so both pages see the same values. --}}
                    <button type="button" class="btn btn-sm btn-outline-primary pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#sbidRuleModal"
                            title="Configure L7 views threshold + SCVR bands (shared with /ebay/campaign-ads S Bid column)">
                        <i class="fas fa-sliders-h me-1"></i>SBID Rule
                    </button>

                    {{-- Dil Rule button — same shared editor as /ebay/campaign-ads
                         (ebay_sbid_rules.key = ebay1_dil). --}}
                    <button type="button" class="btn btn-sm btn-outline-danger pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#dilRuleModal"
                            title="Configure DIL% color bands (shared with /ebay/campaign-ads)">
                        <i class="fas fa-tint me-1"></i>Dil Rule
                    </button>
                </div>

                <!-- Summary Stats (layout matches Ebay 2 Analytics summary row) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (INV &gt; 0)</h6>
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter 0 sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                            title="Click to filter items with sales (INV>0)">> 0 Sold: 0</span>

                        <!-- Financial Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                            style="color: black; font-weight: bold;">Sales: $0</span>
                        
                        <span class="badge fs-6 p-2" id="qty-sold-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="L30 units sold (Σ ebay_order_items.quantity for period='l30'). Same value /ebay/daily-sales shows.">S Qty: {{ number_format((int) ($ordersL30TotalQty ?? 0)) }}</span>
                        <span class="badge fs-6 p-2" id="ebay1-shopify-sales-badge"
                            style="background-color: #0f766e; color: white; font-weight: bold;"
                            title="eBay1 sales from Shopify raw data (L30, excludes cancelled)">EShp: $0</span>

                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                            style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge"
                            style="color: white; font-weight: bold;">GROI: 0%</span>

                        <!-- eBay Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: black; font-weight: bold;">Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge"
                            style="color: white; font-weight: bold;"
                            title="CVR = (S Qty / Σ Views) × 100. Numerator is the orders-API L30 units (same value the S Qty badge shows, same source /ebay/daily-sales uses). Denominator is the sum of 'views' across rows with E Stock > 0.">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge"
                            style="color: black; font-weight: bold;">Views: 0</span>

                        <!-- Badge Filters -->
                        <span class="badge bg-secondary fs-6 p-2" id="missing-l-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing L (INV>0, not listed on eBay, REQ)">Missing L: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="missing-m-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing M (listed, INV>0, REQ, INV vs eBay Stock mismatch)">Missing M: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="ebay-discount-type-block" class="d-flex align-items-center gap-2">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- View / Parent / SKU + search + row counter (toolbar matches ebay2-tabulator-view) -->
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e7eb;">
                        <div class="d-flex align-items-center gap-1">
                            <label for="view-type-filter" class="form-label mb-0 text-nowrap small"
                                style="font-size: 13px;">View:</label>
                            <select id="view-type-filter" class="form-select form-select-sm"
                                style="width: 100px; font-size: 13px;">
                                <option value="all">All</option>
                                <option value="parent">Parent</option>
                                {{-- Default selection: hide parent summary rows on initial load.
                                     Filter logic (applyFilters) already drops parent rows
                                     when this value is 'sku', so nothing else needs to change. --}}
                                <option value="sku" selected>SKU</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <label for="parent-sku-dropdown" class="form-label mb-0 text-nowrap small"
                                style="font-size: 13px;">Parent / SKU:</label>
                            <select id="parent-sku-dropdown" class="form-select form-select-sm"
                                style="width: 220px; font-size: 13px;">
                                <option value="">All (show all)</option>
                            </select>
                        </div>
                        <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-light rounded-circle"
                                title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-light rounded-circle"
                                title="Show all products" style="display: none;">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-light rounded-circle"
                                title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-light rounded-circle"
                                title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <div style="flex: 1; min-width: 200px; position: relative;">
                            <i class="fa fa-search"
                                style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px;"></i>
                            <input type="text" id="sku-search" class="form-control form-control-sm"
                                style="padding-left: 32px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;"
                                placeholder="Search by campaign name or SKU...">
                        </div>
                        <div style="min-width: 200px; position: relative;">
                            <i class="fa fa-sitemap"
                                style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px;"></i>
                            <input type="text" id="parent-search" class="form-control form-control-sm"
                                style="padding-left: 32px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;"
                                placeholder="Search Parent...">
                        </div>
                        <span id="custom-pagination-counter"
                            style="font-size: 13px; color: #555; white-space: nowrap; margin-left: 16px;"></span>
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- LMP Competitors Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> eBay Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fa fa-plus-circle"></i> Add New Competitor</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">eBay Item ID *</label>
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price"
                                            step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping"
                                            name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link"
                                            placeholder="https://ebay.com/itm/...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <label class="form-label">Product Title (optional)</label>
                                        <input type="text" class="form-control" id="addCompTitle"
                                            name="product_title" placeholder="Product title">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Competitors List -->
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

    <!-- SKU Metrics Chart Modal -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Metrics Chart for <span id="modalSkuName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date Range:</label>
                        <select id="sku-chart-days-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="7">Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                        </select>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-info" style="display: none;">
                        No historical data available for this SKU. Data will appear after running the metrics collection
                        command.
                    </div>
                    <div style="height: 400px;">
                        <canvas id="skuMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Column Selection Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Columns to Export</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="select-all-export-columns">
                            <i class="fa fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="deselect-all-export-columns">
                            <i class="fa fa-square"></i> Deselect All
                        </button>
                    </div>
                    <div id="export-columns-list"
                        style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <!-- Columns will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirm-export-btn">
                        <i class="fa fa-file-excel"></i> Export Selected Columns
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import eBay Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file"
                                accept=".csv" required>
                            <div class="form-text">Upload a CSV file with columns: sku, rating (0-5)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fa fa-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- SBID Rule Modal — mirrors /ebay/campaign-ads; same endpoints (/ebay/campaign-ads/rule). --}}
    <div class="modal fade" id="sbidRuleModal" tabindex="-1" aria-labelledby="sbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="sbidRuleModalLabel">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>eBay SBID Rule — L7 Views + SCVR &rarr; Bid
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Step 1: if a row's <code>l7_views</code> is below the threshold &rarr; S Bid = <strong>ES Bid</strong> (raw eBay <code>suggested_bid</code>).
                        Step 2: otherwise SCVR bands are evaluated <strong>top to bottom</strong> &mdash; first match wins.
                        <code>SCVR = (Sold L30 / Views) &times; 100</code>.
                    </p>

                    {{-- L7 views threshold --}}
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold mb-1">L7 Views Threshold</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="sbid-l7-threshold-input"
                                       step="1" min="0" value="70">
                                <span class="input-group-text">views</span>
                            </div>
                            <small class="text-muted">
                                If a row's <code>l7_views</code> &lt; this value &rarr; S Bid = ES Bid (raw <code>suggested_bid</code>).
                            </small>
                        </div>
                    </div>

                    <table class="table table-sm table-bordered align-middle" id="sbid-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th>Color</th>
                                <th>CVR &le; (%)</th>
                                <th>Bid (%)</th>
                            </tr>
                        </thead>
                        <tbody id="sbid-bands-body">
                            {{-- filled by JS --}}
                        </tbody>
                    </table>

                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Set SCVR Max to <code>9999</code> for the last band (catch-all).
                        Tick <strong>Dynamic by metric</strong> on any band to decide its bid from
                        Price / L30 Sold / Views / SCVR tiers instead of a flat bid.
                        Changes are shared with <code>/ebay/campaign-ads</code> and apply next time
                        <strong>ebay:update-suggestedbid</strong> runs.
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="sbid-rule-err"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sbid-rule-save-btn">
                        <i class="fas fa-save me-1"></i>Save Rule
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Dilution Rule Modal — mirrors /ebay/campaign-ads; same endpoints (/ebay/campaign-ads/dil-rule). --}}
    <div class="modal fade" id="dilRuleModal" tabindex="-1" aria-labelledby="dilRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="dilRuleModalLabel">
                        <i class="fas fa-tint me-2 text-danger"></i>eBay Dilution Rule &mdash; DIL % &rarr; Color
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Bands evaluated <strong>top to bottom</strong> &mdash; first match wins.
                        <code>DIL = (L30 sold / Inventory) &times; 100</code>. Each band sets a color and a bid.
                    </p>

                    <table class="table table-sm table-bordered align-middle" id="dil-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th>Color</th>
                                <th>DIL &le; (%)</th>
                                <th>Bid (%)</th>
                            </tr>
                        </thead>
                        <tbody id="dil-bands-body">
                            {{-- filled by JS --}}
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-sm btn-outline-primary py-0 mb-2" id="dil-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add band
                    </button>

                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Set DIL Max to <code>9999</code> for the last band (catches everything above the previous threshold).
                        <strong>Push logic:</strong> if a listing's SCVR <em>or</em> DIL lands in its <strong>Pink (catch-all)</strong>
                        band, the Pink bid is pushed; otherwise the SCVR rule's bid is used. Changes are shared with
                        <code>/ebay/campaign-ads</code>.
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="dil-rule-err"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="dil-rule-save-btn">
                        <i class="fas fa-save me-1"></i>Save Rule
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "ebay_tabulator_column_visibility";
        /** Stored in DB table channel_tabulator_column_settings (shared across all users — same pattern as ebay2/ebay3/mfrg/amazon tabulators). */
        const TABULATOR_COLUMN_CHANNEL = 'ebay1_tabulator';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        /** L30 units sold from ebay_orders (period='l30'). Same value rendered into the
         *  S Qty badge and the same number /ebay/daily-sales shows. Used by the CVR
         *  formula so the page CVR is computed against orders-API ground truth instead
         *  of the laggier ebay_metrics.ebay_l30 sum. */
        const ORDERS_L30_TOTAL_QTY = {{ (int) ($ordersL30TotalQty ?? 0) }};
        /** App base path (XAMPP subdir / public): root-relative "/ebay-data-json" would 404 */
        const EBAY_DATA_JSON_URL = @json(url('/ebay-data-json'));
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false;
        let selectedSkus = new Set(); // Track selected SKUs across all pages

        /**
         * Child SKUs on the current pagination page only (respects filters + SKU Count).
         * Never return all filtered rows: always slice [start, start + pageSize) over the active/filtered set.
         */
        function ebayCurrentPageChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            const notParent = function(d) {
                return !isParent(d);
            };
            var page = 1;
            var pageSize = 100;
            try {
                page = table.getPage();
                pageSize = table.getPageSize();
            } catch (e) {
                /* ignore */ }
            if (page < 1) page = 1;
            const start = Math.max(0, (page - 1) * pageSize);
            const end = start + pageSize;

            var totalActive = null;
            try {
                if (typeof table.getDataCount === 'function') {
                    totalActive = table.getDataCount('active');
                }
            } catch (e) {
                /* ignore */ }

            var activeData = [];
            try {
                activeData = table.getData('active') || [];
            } catch (e) {
                /* ignore */ }

            // Full filtered dataset in memory → paginate (works with filters)
            if (activeData.length > 0) {
                var fullActiveSet = totalActive == null || activeData.length === totalActive;
                var longEnough = activeData.length >= end;
                if (fullActiveSet || longEnough) {
                    return activeData.slice(start, end).filter(notParent);
                }
                if (activeData.length <= pageSize && start === 0) {
                    return activeData.filter(notParent);
                }
            }

            try {
                var activeRows = table.getRows('active') || [];
                if (activeRows.length > 0) {
                    if (totalActive == null || activeRows.length === totalActive || activeRows.length >= end) {
                        return activeRows.slice(start, end).map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                    if (activeRows.length <= pageSize && start === 0) {
                        return activeRows.map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                }
            } catch (e2) {
                /* ignore */ }

            return [];
        }

        // Play / Pause parent navigation (same as product-master)
        let productUniqueParents = [];
        let isProductNavigationActive = false;
        let currentProductParentIndex = -1;

        function ebayParentKey(p) {
            var s = (p || '').toString().trim();
            if (s.toUpperCase().startsWith('PARENT')) return s.replace(/^PARENT\s+/i, '').trim();
            return s;
        }

        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingLFilterActive = false;
        let missingMFilterActive = false;

        /**
         * When any narrowing filter/search is on, header "select all" should include every filtered row (all pages).
         * Default table state (E Stock &gt; 0, REQ only, etc.) = current page only.
         */
        function ebaySelectAllUsesFullFilteredSet() {
            if (typeof isProductNavigationActive !== 'undefined' && isProductNavigationActive) return true;
            if (($('#sku-search').val() || '').trim() !== '') return true;
            if (($('#parent-sku-dropdown').val() || '') !== '') return true;
            if (($('#view-type-filter').val() || 'all') !== 'all') return true;
            if (($('#inventory-filter').val() || 'more') !== 'more') return true;
            if (($('#el30-filter').val() || 'all') !== 'all') return true;
            if (($('#nrl-filter').val() || 'REQ') !== 'REQ') return true;
            if (($('#gpft-filter').val() || 'all') !== 'all') return true;
            if (($('#roi-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-trend-filter').val() || 'all') !== 'all') return true;
            if (($('#sprice-filter').val() || 'all') !== 'all') return true;
            if (($('#growth-sign-filter').val() || 'all') !== 'all') return true;
            var dil = 'all';
            try {
                dil = $('.column-filter[data-column="dil_percent"].active').data('color') || 'all';
            } catch (eDil) {
                /* ignore */ }
            if (dil !== 'all') return true;
            if (zeroSoldFilterActive || moreSoldFilterActive || missingLFilterActive || missingMFilterActive) return true;

            return false;
        }

        /** All filtered child rows (every page), excluding parent summary rows. */
        function ebayAllFilteredChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            try {
                return (table.getData('active') || []).filter(function(d) {
                    return !isParent(d);
                });
            } catch (e) {
                return [];
            }
        }

        function ebayRowsForHeaderSelectAll() {
            return ebaySelectAllUsesFullFilteredSet() ?
                ebayAllFilteredChildRowsForSelection() :
                ebayCurrentPageChildRowsForSelection();
        }

        // Single toast: accepts showToast(message, type) or showToast(type, message)
        function showToast(a, b) {
            var type, message;
            if (['success', 'error', 'info', 'warning'].indexOf(String(a)) !== -1 && typeof b === 'string') {
                type = a;
                message = b;
            } else {
                message = a;
                type = b || 'info';
            }
            var container = document.querySelector('.toast-container');
            if (!container) return;
            var bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' :
                'info'));
            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-' + bg + ' border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '') +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            container.appendChild(toast);
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                new bootstrap.Toast(toast).show();
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            } else {
                toast.classList.add('show');
                toast.style.position = 'fixed';
                toast.style.top = '1rem';
                toast.style.right = '1rem';
                toast.style.zIndex = '10800';
                setTimeout(function() {
                    toast.remove();
                }, 5000);
            }
        }

        // SKU-specific chart
        function initSkuMetricsChart() {
            const canvas = document.getElementById('skuMetricsChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }
            const ctx = canvas.getContext('2d');
            skuMetricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                            label: 'Price (USD)',
                            data: [],
                            borderColor: '#FF0000',
                            backgroundColor: 'rgba(255, 0, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Views',
                            data: [],
                            borderColor: '#0000FF',
                            backgroundColor: 'rgba(0, 0, 255, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'CVR%',
                            data: [],
                            borderColor: '#008000',
                            backgroundColor: 'rgba(0, 128, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'eBay SKU Metrics',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.parsed.y || 0;

                                    // Format based on dataset label
                                    if (label.includes('Price')) {
                                        return label + ': $' + value.toFixed(2);
                                    } else if (label.includes('Views')) {
                                        return label + ': ' + value.toLocaleString();
                                    } else if (label.includes('CVR')) {
                                        return label + ': ' + value.toFixed(1) + '%';
                                    } else if (label.includes('AD')) {
                                        return label + ': ' + Math.round(value) + '%';
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Price/Views',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 11
                                },
                                callback: function(value, index, values) {
                                    if (values.length > 0 && Math.max(...values.map(v => v.value)) < 1000) {
                                        return '$' + value.toFixed(0);
                                    }
                                    return value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Percent (%)',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return value.toFixed(0) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        function loadSkuMetricsData(sku, days = 30) {
            console.log('Loading metrics data for SKU:', sku, 'Days:', days);
            fetch(`/ebay-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Metrics data received:', data);
                    if (skuMetricsChart) {
                        if (!data || data.length === 0) {
                            console.warn('No data returned for SKU:', sku);
                            $('#chart-no-data-message').show();
                            skuMetricsChart.data.labels = [];
                            skuMetricsChart.data.datasets.forEach(dataset => {
                                dataset.data = [];
                            });
                            skuMetricsChart.options.plugins.title.text = 'eBay Metrics';
                            skuMetricsChart.update();
                            return;
                        }

                        $('#chart-no-data-message').hide();
                        skuMetricsChart.options.plugins.title.text = `eBay Metrics (${days} Days)`;
                        skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                        skuMetricsChart.data.datasets[0].data = data.map(d => d.price || 0);
                        skuMetricsChart.data.datasets[1].data = data.map(d => d.views || 0);
                        skuMetricsChart.data.datasets[2].data = data.map(d => d.cvr_percent || 0);
                        skuMetricsChart.update('active');
                        console.log('Chart updated successfully with', data.length, 'data points');
                    }
                })
                .catch(error => {
                    console.error('Error loading SKU metrics data:', error);
                    alert('Error loading metrics data. Please check console for details.');
                });
        }

        $(document).ready(function() {
            const lmpModalEl = document.getElementById('lmpModal');
            if (lmpModalEl) {
                lmpModalEl.addEventListener('hidden.bs.modal', cleanupLmpModalBackdrop);
            }

            // Initialize SKU-specific chart
            initSkuMetricsChart();

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() {
                if (samePriceModeActive) {
                    return;
                }
                const discountType = $(this).val();
                const $input = $('#discount-percentage-input');
                if (discountType === 'percentage') {
                    $input.attr('placeholder', 'Enter percentage');
                    $input.attr('max', '100');
                } else {
                    $input.attr('placeholder', 'Enter value');
                    $input.removeAttr('max');
                }
            });

            function syncEbayDiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#ebay-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('eBay price:');
                    $inp.attr('placeholder', 'Each row — click Apply');
                    $inp.prop('disabled', true);
                    $inp.removeAttr('max');
                    $inp.val('');
                } else {
                    $('#ebay-discount-type-block').removeClass('d-none');
                    $('#discount-input-label').text('Value:');
                    $inp.prop('disabled', false);
                    const type = $('#discount-type-select').val();
                    if (type === 'percentage') {
                        $inp.attr('placeholder', 'Enter percentage');
                        $inp.attr('max', '100');
                    } else {
                        $inp.attr('placeholder', 'Enter value');
                        $inp.removeAttr('max');
                    }
                }
            }

            function syncEbayPriceModeUi() {
                if (!table || !table.getColumn) {
                    return;
                }
                const $btn = $('#ebay-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncEbayDiscountBarForMode();
                if (decreaseModeActive) {
                    $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                    selectColumn.show();
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                    selectColumn.show();
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                    selectColumn.show();
                    return;
                }
                $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                    .html('<i class="fas fa-exchange-alt"></i> Price %');
                selectColumn.hide();
                selectedSkus.clear();
                $('.sku-select-checkbox').prop('checked', false);
                $('#select-all-checkbox').prop('checked', false);
                $('#discount-input-container').hide();
                updateSelectedCount();
                updateSelectAllCheckbox();
            }

            $('#ebay-price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    decreaseModeActive = true;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false;
                    increaseModeActive = true;
                } else if (increaseModeActive) {
                    increaseModeActive = false;
                    samePriceModeActive = true;
                } else {
                    samePriceModeActive = false;
                }
                syncEbayPriceModeUi();
            });

            // Select all checkbox handler (matching Amazon approach)
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');

                // With filters/search: all matching rows (all pages). Default state: current page only.
                const filteredData = ebayRowsForHeaderSelectAll();

                // Add or remove those SKUs from the selected set
                filteredData.forEach(row => {
                    const sku = row['(Child) sku'];
                    if (sku) {
                        if (isChecked) {
                            selectedSkus.add(sku);
                        } else {
                            selectedSkus.delete(sku);
                        }
                    }
                });

                // Update all visible checkboxes
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

            // Helper: round to retail (.99 endings)
            function roundToRetailPrice(price) {
                if (price < 20.99) {
                    return +price.toFixed(2);
                }
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.01).toFixed(2);
            }
            // Helper: round to retail (.49 endings) — use when .99 would match current price so S PRC stays visible
            function roundToRetailPrice49(price) {
                if (price < 20.99) {
                    return +price.toFixed(2);
                }
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.51).toFixed(2);
            }

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

           
            function ebayApplyTargetSpriceBatch(opts) {
                // opts: { targetPct, computeSprice(rd) -> {sprice, skipReason?}, label, $btn, btnHtml }
                const $btn = opts.$btn;
                if (typeof selectedSkus === 'undefined' || selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }

                const rowsToProcess = [];
                const skipped = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rd.is_parent_summary || rd.is_parent_row) return;
                    const res = opts.computeSprice(rd);
                    if (!res || res.skipReason) {
                        if (res && res.skipReason) skipped.push({ sku: sku, reason: res.skipReason });
                        return;
                    }
                    const sprice = +Number(res.sprice).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    if (skipped.length > 0) {
                        showToast('error', `Cannot apply: ${skipped[0].reason}`);
                    } else {
                        showToast('warning', 'No selected rows have a usable LP > 0');
                    }
                    return;
                }

                let confirmMsg = `Compute & save SPRICE for ${rowsToProcess.length} selected SKU(s) using ${opts.label}?`;
                if (skipped.length > 0) {
                    confirmMsg += `\n\nNote: ${skipped.length} row(s) will be skipped (${skipped[0].reason}).`;
                }
                if (!confirm(confirmMsg)) return;

                let successCount = 0;
                let errorCount = 0;
                const total = rowsToProcess.length;
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

                rowsToProcess.forEach(function(item) {
                    $.ajax({
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { sku: item.sku, sprice: item.sprice },
                        success: function(response) {
                            successCount++;
                            // Field names match the existing eBay saveSpriceWithRetry update payload.
                            const updateData = {
                                SPRICE: item.sprice,
                                SPFT: response.spft_percent != null ? response.spft_percent : 0,
                                SROI: response.sroi_percent != null ? response.sroi_percent : 0,
                                SGROI: response.sgroi_percent != null ? response.sgroi_percent : 0,
                                SGPFT: response.sgpft_percent != null ? response.sgpft_percent : 0,
                                SPRICE_STATUS: 'saved',
                                has_custom_sprice: true
                            };
                            item.row.update(updateData);
                            item.row.reformat();
                        },
                        error: function() { errorCount++; },
                        complete: function() {
                            if (successCount + errorCount === total) {
                                $btn.prop('disabled', false).html(opts.btnHtml);
                                if (errorCount === 0) {
                                    showToast('success', `SPRICE saved for ${successCount} SKU(s) @ ${opts.label}`);
                                } else {
                                    showToast('error', `Saved ${successCount} of ${total} (${errorCount} failed)`);
                                }
                                // Wipe selection so the next batch starts clean.
                                selectedSkus.clear();
                                $('.sku-select-checkbox').prop('checked', false);
                                $('#select-all-checkbox').prop('checked', false);
                                if (typeof updateSelectedCount === 'function') {
                                    updateSelectedCount();
                                } else if (typeof updateSelectionUI === 'function') {
                                    updateSelectionUI();
                                }
                            }
                        }
                    });
                });
            }

            // Target ROI%
            $('#apply-target-roi-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#target-roi-input').val();
                const targetRoiPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('error', 'Please enter a Target ROI%'); return; }
                if (!isFinite(targetRoiPct)) { showToast('error', 'Target ROI% must be a number'); return; }
                const roiMultiplier = 1 + (targetRoiPct / 100);
                ebayApplyTargetSpriceBatch({
                    targetPct: targetRoiPct,
                    label: `Target ROI ${targetRoiPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.LP_productmaster) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.Ship_productmaster) || 0;
                        const marginRaw = parseFloat(rd.percentage);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                        return { sprice: (lp * roiMultiplier + ship) / margin };
                    }
                });
            });
            $('#target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });

            // Target GPFT%
            $('#apply-target-gpft-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#target-gpft-input').val();
                const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('error', 'Please enter a Target GPFT%'); return; }
                if (!isFinite(targetGpftPct)) { showToast('error', 'Target GPFT% must be a number'); return; }
                const targetFraction = targetGpftPct / 100;
                ebayApplyTargetSpriceBatch({
                    targetPct: targetGpftPct,
                    label: `Target GPFT ${targetGpftPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.LP_productmaster) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.Ship_productmaster) || 0;
                        const marginRaw = parseFloat(rd.percentage);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                        const denom = margin - targetFraction;
                        if (denom <= 0) {
                            return { skipReason: `Target GPFT% ${targetGpftPct}% \u2265 eBay take-home margin (~${Math.round(margin * 100)}%)` };
                        }
                        return { sprice: (lp + ship) / denom };
                    }
                });
            });
            $('#target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Badge click handlers for filtering
            $('#zero-sold-count-badge').on('click', function() {
                zeroSoldFilterActive = !zeroSoldFilterActive;
                moreSoldFilterActive = false;
                applyFilters();
            });

            $('#more-sold-count-badge').on('click', function() {
                moreSoldFilterActive = !moreSoldFilterActive;
                zeroSoldFilterActive = false;
                applyFilters();
            });

            $('#missing-l-count-badge').on('click', function() {
                missingLFilterActive = !missingLFilterActive;
                $(this).toggleClass('bg-secondary', !missingLFilterActive)
                       .toggleClass('bg-danger', missingLFilterActive);
                applyFilters();
            });

            $('#missing-m-count-badge').on('click', function() {
                missingMFilterActive = !missingMFilterActive;
                $(this).toggleClass('bg-secondary', !missingMFilterActive)
                       .toggleClass('bg-danger', missingMFilterActive);
                applyFilters();
            });

            // Clear SPRICE button handler (in selection container)
            $('#clear-sprice-selected-btn').on('click', function() {
                if (confirm('Are you sure you want to clear SPRICE for selected SKUs?')) {
                    clearSpriceForSelected();
                }
            });

            // DIL filter click handler
            $(document).on('click', '.column-filter', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $item = $(this);
                const $container = $item.closest('.manual-dropdown-container');
                const button = $container.find('button').first();

                $container.find('.column-filter').removeClass('active');
                $item.addClass('active');

                const statusCircle = $item.find('.status-circle').clone();
                button.html('').append(statusCircle).append(' DIL%');

                $container.removeClass('show');
                applyFilters();
            });

            // Apply All button handler
            $(document).on('click', '#apply-all-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.applyAllSelectedPrices();
            });

            // SKU chart days filter
            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                if (currentSku) {
                    if (skuMetricsChart) {
                        skuMetricsChart.options.plugins.title.text = `eBay Metrics (${days} Days)`;
                        skuMetricsChart.update();
                    }
                    loadSkuMetricsData(currentSku, days);
                }
            });

            // Update selected count display
            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#discount-input-container').toggle(count > 0 || decreaseModeActive || increaseModeActive ||
                    samePriceModeActive);
            }

            // Update select all checkbox state (matching Amazon approach)
            function updateSelectAllCheckbox() {
                if (!table) return;

                const filteredData = ebayRowsForHeaderSelectAll();

                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }

                // Get all filtered SKUs
                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));

                // Check if all filtered SKUs are selected
                const allFilteredSelected = filteredSkus.size > 0 &&
                    Array.from(filteredSkus).every(sku => selectedSkus.has(sku));

                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }

            // Background retry storage key
            const BACKGROUND_RETRY_KEY = 'ebay_failed_price_pushes';

            // Save failed SKU to localStorage for background retry
            function saveFailedSkuForRetry(sku, price, retryCount = 0) {
                try {
                    const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
                    failedSkus[sku] = {
                        sku: sku,
                        price: price,
                        retryCount: retryCount,
                        timestamp: Date.now()
                    };
                    localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
                } catch (e) {
                    console.error('Error saving failed SKU to localStorage:', e);
                }
            }

            // Remove SKU from background retry list
            function removeFailedSkuFromRetry(sku) {
                try {
                    const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
                    delete failedSkus[sku];
                    localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
                } catch (e) {
                    console.error('Error removing SKU from localStorage:', e);
                }
            }

            // Background retry function (runs even after page refresh)
            async function backgroundRetryFailedSkus() {
                try {
                    const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
                    const skuKeys = Object.keys(failedSkus);

                    if (skuKeys.length === 0) return;

                    console.log(`Found ${skuKeys.length} failed SKU(s) to retry in background`);

                    for (const sku of skuKeys) {
                        const failedData = failedSkus[sku];

                        // Skip if already retried 5 times
                        if (failedData.retryCount >= 5) {
                            console.log(`SKU ${sku} has reached max retries (5), removing from retry list`);
                            removeFailedSkuFromRetry(sku);
                            continue;
                        }

                        // Skip if account is restricted (check status in table if available)
                        let isAccountRestricted = false;
                        if (table) {
                            try {
                                const rows = table.getRows();
                                for (let i = 0; i < rows.length; i++) {
                                    const rowData = rows[i].getData();
                                    if (rowData['(Child) sku'] === sku) {
                                        if (rowData.SPRICE_STATUS === 'account_restricted') {
                                            isAccountRestricted = true;
                                        }
                                        break;
                                    }
                                }
                            } catch (e) {
                                // Continue if table check fails
                            }
                        }

                        if (isAccountRestricted) {
                            console.log(`SKU ${sku} is account restricted, skipping background retry`);
                            removeFailedSkuFromRetry(sku);
                            continue;
                        }

                        // Try to find the cell in the table for UI update
                        let cell = null;
                        if (table) {
                            try {
                                const rows = table.getRows();
                                for (let i = 0; i < rows.length; i++) {
                                    const rowData = rows[i].getData();
                                    if (rowData['(Child) sku'] === sku) {
                                        cell = rows[i].getCell('_accept');
                                        break;
                                    }
                                }
                            } catch (e) {
                                // Table might not be ready, continue without UI update
                            }
                        }

                        // Retry the price push once (background retry)
                        const success = await applyPriceWithRetry(sku, failedData.price, cell, 0, true);

                        if (!success) {
                            // Increment retry count if still failed
                            failedData.retryCount++;
                            saveFailedSkuForRetry(sku, failedData.price, failedData.retryCount);
                            console.log(`Background retry ${failedData.retryCount}/5 failed for SKU: ${sku}`);
                        } else {
                            // Success - already removed from retry list in applyPriceWithRetry
                            // Update table if it's loaded
                            if (table) {
                                setTimeout(() => {
                                    table.replaceData();
                                }, 1000);
                            }
                        }

                        // Small delay between SKUs to avoid burst calls
                        await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                } catch (e) {
                    console.error('Error in background retry:', e);
                }
            }

            // Retry function for saving SPRICE (only 1 retry for eBay)
            function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
                return new Promise((resolve, reject) => {
                    // Update status to processing
                    if (row) {
                        row.update({
                            SPRICE_STATUS: 'processing'
                        });
                    }

                    $.ajax({
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: sprice
                        },
                        success: function(response) {
                            // Re-find row by SKU so we update the current row (avoids blank S PRC if table redrew)
                            let targetRow = row;
                            if (table && table.getRows) {
                                table.getRows().forEach(function(r) {
                                    if (r.getData()['(Child) sku'] === sku) targetRow =
                                        r;
                                });
                            }
                            const numSprice = typeof sprice === 'number' && !isNaN(sprice) ?
                                sprice : parseFloat(sprice);
                            if (targetRow) {
                                targetRow.update({
                                    SPRICE: numSprice,
                                    SPFT: response.spft_percent != null ? response
                                        .spft_percent : 0,
                                    SROI: response.sroi_percent != null ? response
                                        .sroi_percent : 0,
                                    SGROI: response.sgroi_percent != null ? response
                                        .sgroi_percent : 0,
                                    SGPFT: response.sgpft_percent != null ? response
                                        .sgpft_percent : 0,
                                    SPRICE_STATUS: numSprice > 0 ? 'saved' : null,
                                    has_custom_sprice: numSprice > 0
                                });
                                targetRow.reformat();
                            }
                            resolve(response);
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.error || xhr.responseText ||
                                'Failed to save SPRICE';
                            console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`,
                                errorMsg);

                            // Only retry once (retryCount < 1)
                            if (retryCount < 1) {
                                console.log(`Retrying SKU ${sku} in 2 seconds...`);
                                setTimeout(() => {
                                    saveSpriceWithRetry(sku, sprice, row, retryCount +
                                            1)
                                        .then(resolve)
                                        .catch(reject);
                                }, 2000);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                // Update status to error
                                if (row) {
                                    row.update({
                                        SPRICE_STATUS: 'error'
                                    });
                                }
                                reject({
                                    error: true,
                                    xhr: xhr
                                });
                            }
                        }
                    });
                });
            }

            // Apply price with retry logic (for pushing to eBay)
            async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
                const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
                const row = cell ? cell.getRow() : null;
                const rowData = row ? row.getData() : null;

                // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
                if (isBackgroundRetry) {
                    try {
                        const response = await $.ajax({
                            url: '/push-ebay-price-tabulator',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                price: price
                            }
                        });

                        if (response.errors && response.errors.length > 0) {
                            throw new Error(response.errors[0].message || 'API error');
                        }

                        // Success - update UI and remove from retry list
                        if (rowData) {
                            rowData.SPRICE_STATUS = 'pushed';
                            row.update(rowData);
                        }
                        if ($btn && cell) {
                            $btn.prop('disabled', false);
                            $btn.html('<i class="fa-solid fa-check-double"></i>');
                            $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                        }
                        removeFailedSkuFromRetry(sku);
                        return true;
                    } catch (e) {
                        // Background failure is handled by retryCount in backgroundRetryFailedSkus
                        if (rowData) {
                            rowData.SPRICE_STATUS = 'error';
                            row.update(rowData);
                        }
                        if ($btn && cell) {
                            $btn.prop('disabled', false);
                            $btn.html('<i class="fa-solid fa-x"></i>');
                            $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                        }
                        return false;
                    }
                }

                // Foreground mode (user click): up to 5 immediate retries with spinner UI
                // Set initial loading state (only if cell exists)
                if (retries === 0 && cell && $btn && row) {
                    $btn.prop('disabled', true);
                    $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                    $btn.attr('style',
                    'border: none; background: none; color: #ffc107; padding: 0;'); // Yellow text, no background
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'processing';
                        row.update(rowData);
                    }
                }

                try {
                    const response = await $.ajax({
                        url: '/push-ebay-price-tabulator',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            price: price
                        }
                    });

                    if (response.errors && response.errors.length > 0) {
                        throw new Error(response.errors[0].message || 'API error');
                    }

                    // Success - update UI
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'pushed';
                        row.update(rowData);
                    }

                    if ($btn && cell) {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fa-solid fa-check-double"></i>');
                        $btn.attr('style',
                        'border: none; background: none; color: #28a745; padding: 0;'); // Green text, no background
                    }

                    if (!isBackgroundRetry) {
                        showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                    }

                    return true;
                } catch (xhr) {
                    const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr
                        .responseJSON?.message || 'Failed to apply price';
                    const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                    console.error(`Attempt ${retries + 1} for SKU ${sku} failed:`, errorMsg);

                    // Check if this is an account restriction error (don't retry)
                    const isAccountRestricted = errorCode === 'AccountRestricted' ||
                        errorMsg.includes('ACCOUNT RESTRICTION') ||
                        errorMsg.includes('account is restricted') ||
                        errorMsg.includes('embargoed country');

                    if (isAccountRestricted) {
                        // Account restriction - don't retry, mark as account_restricted
                        if (rowData) {
                            rowData.SPRICE_STATUS = 'account_restricted';
                            row.update(rowData);
                        }

                        if ($btn && cell) {
                            $btn.prop('disabled', false);
                            $btn.html('<i class="fa-solid fa-ban"></i>');
                            $btn.attr('style',
                            'border: none; background: none; color: #ff6b00; padding: 0;'); // Orange text for restriction
                            $btn.attr('title', 'Account restricted - cannot update price');
                        }

                        showToast(
                            `Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`,
                            'error');
                        return false;
                    }

                    if (retries < 5) {
                        console.log(`Retrying SKU ${sku} in 5 seconds...`);
                        await new Promise(resolve => setTimeout(resolve, 5000));
                        return applyPriceWithRetry(sku, price, cell, retries + 1, isBackgroundRetry);
                    } else {
                        // Final failure - mark error and save for background retry
                        if (rowData) {
                            rowData.SPRICE_STATUS = 'error';
                            row.update(rowData);
                        }

                        if ($btn && cell) {
                            $btn.prop('disabled', false);
                            $btn.html('<i class="fa-solid fa-x"></i>');
                            $btn.attr('style',
                            'border: none; background: none; color: #dc3545; padding: 0;'); // Red text, no background
                        }

                        // Save for background retry (only if not already a background retry)
                        saveFailedSkuForRetry(sku, price, 0);
                        showToast(
                            `Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`,
                            'error');

                        return false;
                    }
                }
            }

            // Retry function for applying price with up to 5 attempts (Promise-based for Apply All)
            function applyPriceWithRetryPromise(sku, price, maxRetries = 5, delay = 5000) {
                return new Promise((resolve, reject) => {
                    let attempt = 0;

                    function attemptApply() {
                        attempt++;

                        $.ajax({
                            url: '/push-ebay-price-tabulator',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                price: price,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.errors && response.errors.length > 0) {
                                    const errorMsg = response.errors[0].message ||
                                        'Unknown error';
                                    const errorCode = response.errors[0].code || '';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                        errorMsg, 'Code:', errorCode);

                                    if (attempt < maxRetries) {
                                        console.log(
                                            `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                            );
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({
                                            error: true,
                                            response: response
                                        });
                                    }
                                } else {
                                    console.log(
                                        `Successfully pushed price for SKU ${sku} on attempt ${attempt}`
                                        );
                                    resolve({
                                        success: true,
                                        response: response
                                    });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr
                                    .responseJSON?.error || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                    errorMsg);

                                if (attempt < maxRetries) {
                                    console.log(
                                        `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                        );
                                    setTimeout(attemptApply, delay);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku}`);
                                    reject({
                                        error: true,
                                        xhr: xhr
                                    });
                                }
                            }
                        });
                    }

                    attemptApply();
                });
            }

            // Apply all selected prices
            window.applyAllSelectedPrices = function() {
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU to apply prices', 'error');
                    return;
                }

                const $btn = $('#apply-all-btn');
                if ($btn.length === 0) {
                    showToast('Apply All button not found', 'error');
                    return;
                }

                if ($btn.prop('disabled')) {
                    return;
                }

                const originalHtml = $btn.html();

                // Disable button and show loading state
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');

                // Get all table data to find SPRICE for selected SKUs
                const tableData = table.getData('all');
                const skusToProcess = [];

                // Build list of SKUs with their prices
                selectedSkus.forEach(sku => {
                    const row = tableData.find(r => r['(Child) sku'] === sku);
                    if (row) {
                        const sprice = parseFloat(row.SPRICE) || 0;
                        if (sprice > 0) {
                            skusToProcess.push({
                                sku: sku,
                                price: sprice
                            });
                        }
                    }
                });

                if (skusToProcess.length === 0) {
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                    showToast('No valid prices found for selected SKUs', 'error');
                    return;
                }

                let successCount = 0;
                let errorCount = 0;
                let currentIndex = 0;

                // Process SKUs sequentially (one by one) with delay to avoid rate limiting
                function processNextSku() {
                    if (currentIndex >= skusToProcess.length) {
                        // All SKUs processed
                        $btn.prop('disabled', false);

                        if (errorCount === 0) {
                            // All successful
                            $btn.html(`<i class="fas fa-check-double" style="color: #28a745;"></i>`);
                            showToast(
                                `Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`,
                                'success');

                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.html(originalHtml);
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast(
                                `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`,
                                'error');
                        }
                        return;
                    }

                    const {
                        sku,
                        price
                    } = skusToProcess[currentIndex];

                    // Find the row and update button to show spinner
                    const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                    if (row) {
                        const acceptCell = row.getCell('_accept');
                        if (acceptCell) {
                            const $cellElement = $(acceptCell.getElement());
                            const $btnInCell = $cellElement.find('.apply-price-btn');
                            if ($btnInCell.length) {
                                $btnInCell.prop('disabled', true);
                                $btnInCell.html('<i class="fas fa-spinner fa-spin"></i>');
                                $btnInCell.attr('style',
                                    'border: none; background: none; color: #ffc107; padding: 0;');
                            }
                        }
                    }

                    // First save to database (like SPRICE edit does), then push to eBay
                    console.log(
                        `Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`
                        );

                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            sku: sku,
                            sprice: price,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(saveResponse) {
                            console.log(`SKU ${sku}: Database save successful`, saveResponse);
                            if (saveResponse.error) {
                                console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse
                                    .error);
                                errorCount++;

                                // Update row data with error status
                                if (row) {
                                    const rowData = row.getData();
                                    rowData.SPRICE_STATUS = 'error';
                                    row.update(rowData);

                                    const acceptCell = row.getCell('_accept');
                                    if (acceptCell) {
                                        const $cellElement = $(acceptCell.getElement());
                                        const $btnInCell = $cellElement.find('.apply-price-btn');
                                        if ($btnInCell.length) {
                                            $btnInCell.prop('disabled', false);
                                            $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                            $btnInCell.attr('style',
                                                'border: none; background: none; color: #dc3545; padding: 0;'
                                                );
                                        }
                                    }
                                }

                                // Process next SKU
                                currentIndex++;
                                setTimeout(() => {
                                    processNextSku();
                                }, 2000);
                                return;
                            }

                            // After saving, push to eBay using retry function
                            console.log(`SKU ${sku}: Starting eBay price push...`);
                            applyPriceWithRetryPromise(sku, price, 5, 5000)
                                .then((result) => {
                                    successCount++;

                                    // Update row data with pushed status instantly
                                    if (row) {
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'pushed';
                                        row.update(rowData);

                                        // Update button to show green check-double
                                        const acceptCell = row.getCell('_accept');
                                        if (acceptCell) {
                                            const $cellElement = $(acceptCell.getElement());
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                    '<i class="fa-solid fa-check-double"></i>'
                                                    );
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #28a745; padding: 0;'
                                                    );
                                            }
                                        }
                                    }

                                    // Process next SKU with delay to avoid rate limiting (2 seconds between requests)
                                    currentIndex++;
                                    setTimeout(() => {
                                        processNextSku();
                                    }, 2000);
                                })
                                .catch((error) => {
                                    errorCount++;

                                    // Update row data with error status
                                    if (row) {
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'error';
                                        row.update(rowData);

                                        // Update button to show error icon
                                        const acceptCell = row.getCell('_accept');
                                        if (acceptCell) {
                                            const $cellElement = $(acceptCell.getElement());
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                '<i class="fa-solid fa-x"></i>');
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #dc3545; padding: 0;'
                                                    );
                                            }
                                        }
                                    }

                                    // Save for background retry
                                    saveFailedSkuForRetry(sku, price, 0);

                                    // Process next SKU with delay to avoid rate limiting
                                    currentIndex++;
                                    setTimeout(() => {
                                        processNextSku();
                                    }, 2000);
                                });
                        },
                        error: function(xhr) {
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr
                                .responseJSON || xhr.responseText);
                            errorCount++;

                            // Update row data with error status
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);

                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                        $btnInCell.attr('style',
                                            'border: none; background: none; color: #dc3545; padding: 0;'
                                            );
                                    }
                                }
                            }

                            // Process next SKU
                            currentIndex++;
                            setTimeout(() => {
                                processNextSku();
                            }, 2000);
                        }
                    });
                }

                // Start processing
                processNextSku();
            };

            // Apply discount to selected SKUs (same flow as Amazon: validate, round .99/.49, re-find row on save)
            function applyDiscount() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                    return;
                }
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                const rawInput = $('#discount-percentage-input').val();
                const inputValue = parseFloat(String(rawInput || '').replace(',', '.'));
                const discountType = $('#discount-type-select').val();

                if (!samePriceModeActive) {
                    if (rawInput === '' || rawInput == null) {
                        showToast('Please enter a value (% or $)', 'error');
                        return;
                    }
                    if (isNaN(inputValue) || inputValue < 0) {
                        showToast('Please enter a valid positive number', 'error');
                        return;
                    }
                    if (discountType === 'percentage' && inputValue > 100) {
                        showToast('Percentage cannot exceed 100', 'error');
                        return;
                    }
                }

                const allData = table.getData('all');
                let updatedCount = 0;
                let errorCount = 0;
                const totalSkus = selectedSkus.size;
                const appliedAsSamePrice = samePriceModeActive;

                allData.forEach(row => {
                    const isParent = row.Parent && row.Parent.startsWith('PARENT');
                    if (isParent) return;

                    const sku = row['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        const originalPrice = parseFloat(row['eBay Price']) || 0;
                        if (originalPrice <= 0) {
                            return;
                        }

                        let newPriceNum;
                        if (samePriceModeActive) {
                            let newSPrice = roundToRetailPrice(originalPrice);
                            if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                                newSPrice = roundToRetailPrice49(newSPrice);
                            }
                            newPriceNum = parseFloat(newSPrice.toFixed(2));
                        } else {
                            let newSPrice;
                            if (discountType === 'percentage') {
                                const decimal = inputValue / 100;
                                if (increaseModeActive) {
                                    newSPrice = originalPrice * (1 + decimal);
                                } else {
                                    newSPrice = originalPrice * (1 - decimal);
                                }
                            } else {
                                if (increaseModeActive) {
                                    newSPrice = originalPrice + inputValue;
                                } else {
                                    newSPrice = Math.max(0.01, originalPrice - inputValue);
                                }
                            }
                            newSPrice = Math.max(0.01, newSPrice);
                            newSPrice = roundToRetailPrice(newSPrice);
                            if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                                newSPrice = roundToRetailPrice49(newSPrice);
                            }
                            newPriceNum = parseFloat(newSPrice.toFixed(2));
                        }

                        const originalSPrice = parseFloat(row['SPRICE']) || 0;
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['(Child) sku'] === sku;
                        });

                        if (tableRow) {
                            tableRow.update({
                                SPRICE: newPriceNum,
                                SPRICE_STATUS: 'processing'
                            });
                        }

                        saveSpriceWithRetry(sku, newPriceNum, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE set to eBay price for ${updatedCount} SKU(s)` :
                                            `Discount applied to ${updatedCount} SKU(s)`,
                                            'success'
                                        );
                                    } else {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                            `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                            'error'
                                        );
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) tableRow.update({
                                    SPRICE: originalSPrice
                                });
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(
                                        appliedAsSamePrice ?
                                        `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                        `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                        'error'
                                    );
                                }
                            });
                    }
                });
            }

            // Clear SPRICE for selected SKUs (same method as Amazon: batch POST to clear endpoint, then update table)
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

                table.getRows().forEach(row => {
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT')) return;

                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SGROI: 0,
                        SROI: 0,
                        SPRICE_STATUS: null,
                        has_custom_sprice: false
                    });
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    clearedCount++;
                });

                if (updates.length > 0) {
                    $.ajax({
                        url: '/ebay-clear-sprice',
                        method: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json'
                        },
                        data: JSON.stringify({
                            updates: updates
                        }),
                        success: function(response) {
                            showToast(response.message || `SPRICE cleared for ${clearedCount} SKU(s)`,
                                'success');
                        },
                        error: function(xhr) {
                            console.error('Failed to clear SPRICE:', xhr.status, xhr.responseJSON || xhr
                                .responseText);
                            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON
                                .error : 'Failed to clear SPRICE data';
                            showToast(msg, 'error');
                        }
                    });
                } else {
                    showToast('warning', 'No SPRICE values to clear for selected SKUs');
                }
            }

            // Build parent list from table (same logic as dropdown in dataLoaded) - call when needed so Play always has list
            function buildProductUniqueParentsFromTable() {
                if (typeof table === 'undefined' || !table) return [];
                var allRows = table.getData('all') || [];
                var seen = {};
                var list = [];
                allRows.forEach(function(r) {
                    var p = (r.Parent || '').toString().trim();
                    if (p && !String(p).toUpperCase().startsWith('PARENT') && !seen[p]) {
                        seen[p] = true;
                        list.push(p);
                    }
                });
                list.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                return list;
            }

            // Play / Pause parent navigation (same as product-master) - productUniqueParents set in dataLoaded or on first Play
            function initProductPlaybackControls() {
                if (typeof table === 'undefined' || !table) return;
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }

                // Use event delegation so clicks work even if buttons are re-rendered (same as product-master behavior)
                $(document).off('click.ebayplay', '#play-forward').on('click.ebayplay', '#play-forward',
                    productNextParent);
                $(document).off('click.ebayplay', '#play-backward').on('click.ebayplay', '#play-backward',
                    productPreviousParent);
                $(document).off('click.ebayplay', '#play-pause').on('click.ebayplay', '#play-pause',
                    productStopNavigation);
                $(document).off('click.ebayplay', '#play-auto').on('click.ebayplay', '#play-auto',
                    productStartNavigation);

                updateProductButtonStates();
            }

            function productStartNavigation(e) {
                if (e) e.preventDefault();
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    showToast('info', 'No parent groups in data');
                    return;
                }
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                applyFilters();
                table.setPage(1);
                $('#play-auto').hide();
                $('#play-pause').show().removeClass('btn-light');
                updateProductButtonStates();
            }

            function productStopNavigation(e) {
                if (e) e.preventDefault();
                isProductNavigationActive = false;
                currentProductParentIndex = -1;
                $('#play-pause').hide();
                $('#play-auto').show().removeClass('btn-success btn-warning btn-danger').addClass('btn-light');
                applyFilters();
                updateProductButtonStates();
            }

            function productNextParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;
                currentProductParentIndex++;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }

            function productPreviousParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;
                currentProductParentIndex--;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }

            function updateProductButtonStates() {
                $('#play-backward').prop('disabled', !isProductNavigationActive || currentProductParentIndex <= 0);
                $('#play-forward').prop('disabled', !isProductNavigationActive || currentProductParentIndex >=
                    productUniqueParents.length - 1);
                $('#play-auto').attr('title', isProductNavigationActive ? 'Show all products' :
                    'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', 'Next parent');
                $('#play-backward').attr('title', 'Previous parent');
                if (isProductNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            // Image hover preview (forecast.analysis pattern)
            let ebayMpImagePreviewHideTimer = null;
            let ebayMpImagePreviewEl = null;

            function ebayMpRemoveImagePreview() {
                if (ebayMpImagePreviewHideTimer) {
                    clearTimeout(ebayMpImagePreviewHideTimer);
                    ebayMpImagePreviewHideTimer = null;
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
                ebayMpImagePreviewEl = null;
            }

            function ebayMpCancelImagePreviewHide() {
                if (ebayMpImagePreviewHideTimer) {
                    clearTimeout(ebayMpImagePreviewHideTimer);
                    ebayMpImagePreviewHideTimer = null;
                }
            }

            function ebayMpScheduleImagePreviewHide() {
                ebayMpCancelImagePreviewHide();
                ebayMpImagePreviewHideTimer = setTimeout(ebayMpRemoveImagePreview, 220);
            }

            function ebayMpEnsureImagePreviewListeners(wrap) {
                if (wrap.dataset.ebayMpPreviewListeners === '1') return;
                wrap.dataset.ebayMpPreviewListeners = '1';
                wrap.addEventListener('mouseenter', ebayMpCancelImagePreviewHide);
                wrap.addEventListener('mouseleave', ebayMpScheduleImagePreviewHide);
            }

            function ebayMpClampPreviewPosition(wrap, clientX, clientY) {
                const pad = 12;
                let left = clientX + pad;
                let top = clientY + pad;
                wrap.style.position = 'fixed';
                wrap.style.left = left + 'px';
                wrap.style.top = top + 'px';
                const rect = wrap.getBoundingClientRect();
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const m = 8;
                if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
                if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
                if (left < m) left = m;
                if (top < m) top = m;
                wrap.style.left = left + 'px';
                wrap.style.top = top + 'px';
            }

            function ebayMpShowImagePreview(clientX, clientY, fullUrl) {
                if (!fullUrl) return;
                ebayMpCancelImagePreviewHide();
                const existing = ebayMpImagePreviewEl;
                if (existing && document.body.contains(existing)) {
                    const prevImg = existing.querySelector('img');
                    if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                        ebayMpClampPreviewPosition(existing, clientX, clientY);
                        return;
                    }
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
                ebayMpImagePreviewEl = null;
                const wrap = document.createElement('div');
                wrap.id = 'image-hover-preview';
                wrap.style.zIndex = '10050';
                wrap.style.pointerEvents = 'auto';
                wrap.style.border = '1px solid #ccc';
                wrap.style.background = '#fff';
                wrap.style.padding = '4px';
                wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
                wrap.style.borderRadius = '6px';
                const big = document.createElement('img');
                big.style.maxWidth = '350px';
                big.style.maxHeight = '350px';
                big.style.display = 'block';
                big.alt = '';
                big.src = fullUrl;
                wrap.appendChild(big);
                ebayMpEnsureImagePreviewListeners(wrap);
                document.body.appendChild(wrap);
                ebayMpImagePreviewEl = wrap;
                ebayMpClampPreviewPosition(wrap, clientX, clientY);
            }

            // Event delegation for eye button clicks (add to SKU column formatter)
            table = new Tabulator("#ebay-table", {
                ajaxURL: EBAY_DATA_JSON_URL,
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    var start = currentRow;
                    var end = Math.min(currentRow + pageSize - 1, totalRows);
                    var text = totalRows > 0
                        ? "Showing " + start + "-" + end + " of " + totalRows + " rows"
                        : "Showing 0 of 0 rows";
                    $('#custom-pagination-counter').text(text);
                    return "";
                },
                columnCalcs: "both",
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "SKU Count"
                        }
                    }
                },
                initialSort: [{
                        column: "Parent",
                        dir: "asc"
                    },
                    {
                        column: "_parent_sort",
                        dir: "asc"
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    const isParent = data.Parent && String(data.Parent).toUpperCase().startsWith(
                        'PARENT');
                    const el = row.getElement();
                    if (isParent) {
                        el.classList.add('ebay-parent-row');
                        el.style.setProperty('background-color', '#b3e5fc', 'important');
                    } else {
                        el.classList.remove('ebay-parent-row');
                    }
                },
                columns: [{
                        title: "",
                        field: "_parent_sort",
                        visible: false,
                        width: 0
                    },
                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: true,
                        frozen: true,
                        width: 50,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="No extra filter: this page only. If filter/search is on: all matching rows (all pages).">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            const isSelected = sku ? selectedSkus.has(sku) : false;
                            if (isParent) {
                                return '<input type="checkbox" class="sku-select-checkbox" data-sku="' +
                                    (sku || '') +
                                    '" disabled style="cursor: not-allowed; opacity: 0.6;">';
                            }
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku || ''}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                        }
                    },
                    {
                        title: "Image",
                        field: "image_path",
                        frozen: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value) {
                                const u = String(value).replace(/"/g, '&quot;');
                                return '<img src="' + u + '" data-full="' + u +
                                    '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                            }
                            return '';
                        },
                        cellMouseOver: function(e, cell) {
                            const img = cell.getElement().querySelector('.hover-thumb');
                            if (!img) return;
                            ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute(
                                'data-full'));
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = ebayMpImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            const img = cell.getElement().querySelector('.hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            ebayMpClampPreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest(
                                    '#image-hover-preview')) {
                                ebayMpCancelImagePreviewHide();
                                return;
                            }
                            ebayMpScheduleImagePreviewHide();
                        },
                        headerSort: false,
                        width: 80
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            if (String(value).toUpperCase().startsWith('PARENT ')) {
                                return String(value).replace(/^PARENT\s+/i, '').trim();
                            }
                            return value;
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
                            const rowData = cell.getRow().getData();
                            let sku = cell.getValue();
                            if (!sku && rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT')) {
                                sku = rowData.Parent;
                            }
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            let html =
                                `<span class="${isParent ? 'fw-bold text-primary' : ''}">${sku || ''}</span>`;
                            if (sku) {
                                html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                                if (!isParent) {
                                    html += `<button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;">
                                        <i class="fa fa-info-circle"></i>
                                     </button>`;
                                }
                            }
                            return html;
                        }
                    },
                    {
                        title: "Ratings",
                        field: "rating",
                        hozAlign: "center",
                        editor: "input",
                        tooltip: "Enter rating between 0 and 5",
                        width: 80,
                        visible: false
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

                            let html =
                                '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';

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
                                html +=
                                '<span class="text-muted" style="font-size: 12px;">-</span>';
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
                        field: "E Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';

                            // Color logic from inc/dec page - getDilColor
                            if (dil < 16.66) color = '#a00211'; // red
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "CVR 60",
                        field: "CVR_60",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue()) || 0;
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                        },
                        width: 60
                    },
                    {
                        title: "CVR 45",
                        field: "CVR_45",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue()) || 0;
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                        },
                        width: 60
                    },
                    {
                        title: "CVR 30",
                        field: "SCVR",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            const aVal = parseFloat(aData.SCVR) || 0;
                            const bVal = parseFloat(bData.SCVR) || 0;
                            return aVal - bVal;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const val = parseFloat(cell.getValue()) || 0;
                            const cvr60 = parseFloat(rowData.CVR_60) || 0;
                            const tol = 0.1;
                            let arrowHtml = '';
                            let dotColor = '#008000'; // green by default
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            if (!isParent) {
                                let arrowColor = '#6c757d';
                                let arrowIcon = 'fa-minus';
                                if (val > cvr60 + tol) {
                                    // CVR 30 > CVR 60 (improving)
                                    arrowColor = '#28a745';
                                    arrowIcon = 'fa-arrow-up';
                                    dotColor = '#28a745'; // green
                                } else if (val < cvr60 - tol) {
                                    // CVR 60 > CVR 30 (declining)
                                    arrowColor = '#a00211';
                                    arrowIcon = 'fa-arrow-down';
                                    dotColor = '#a00211'; // red
                                } else {
                                    // CVR 30 equals CVR 60 (within tolerance)
                                    dotColor = '#ffc107'; // yellow
                                }
                                arrowHtml =
                                    ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                            }
                            const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' :
                                (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            const sku = rowData['(Child) sku'] || '';
                            const dotBtn = (sku && !isParent) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" title="View CVR chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>` : '';
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml} ${dotBtn}`.trim();
                        },
                        width: 65
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        formatter: function(cell) {
                            var sku = cell.getRow().getData()['(Child) sku'];
                            var value = cell.getValue() || 'REQ';
                            return `<select class="form-select form-select-sm kw-nrl-dropdown" 
                                        data-sku="${sku}" data-field="NRL"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                                    </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var sku = rowData['(Child) sku'];
                            var nrlValue = rowData.NRL || 'REQ';
                            var defaultValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                            var value = (cell.getValue() || '').trim() || defaultValue;
                            return `<select class="form-select form-select-sm kw-nra-dropdown" 
                                        data-sku="${sku}" data-field="NR"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                    </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                    {
                        title: "E L60",
                        field: "eBay L60",
                        visible: false,
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
                        }
                    },
                    {
                        title: "E L45",
                        field: "eBay L45",
                        visible: false,
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
                        }
                    },
                    {
                        title: "E L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
                        }
                    },
                    {
                        title: "Growth",
                        field: "growth_percent",
                        hozAlign: "center",
                        width: 50,
                        sorter: function(a, b, aRow, bRow) {
                            function ebaySalesGrowthPct(row) {
                                const d = row.getData();
                                const l30 = parseFloat(d['eBay L30']) || 0;
                                const l60 = parseFloat(d['eBay L60']) || 0;
                                if (l60 === 0) return l30 > 0 ? 100 : 0;
                                return ((l30 - l60) / l60) * 100;
                            }
                            return ebaySalesGrowthPct(aRow) - ebaySalesGrowthPct(bRow);
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const l30 = parseFloat(rowData['eBay L30']) || 0;
                            const l60 = parseFloat(rowData['eBay L60']) || 0;
                            if (l60 === 0) {
                                if (l30 > 0) {
                                    return `<span style="color: #28a745; font-weight: bold;">+100%</span>`;
                                }
                                return '<span style="color: #6c757d;">0%</span>';
                            }
                            const growth = ((l30 - l60) / l60) * 100;
                            const growthRounded = Math.round(growth);
                            let color = '#6c757d';
                            if (growthRounded > 0) color = '#28a745';
                            else if (growthRounded < 0) color = '#dc3545';
                            const sign = growthRounded > 0 ? '+' : '';
                            return `<span style="color: ${color}; font-weight: bold;">${sign}${growthRounded}%</span>`;
                        }
                    },
                    {
                        title: "E Stock",
                        field: "eBay Stock",
                        hozAlign: "center",
                        width: 60,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            if (value === 0) {
                                return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                            }
                            return `<span style="font-weight: 600;">${value}</span>`;
                        }
                    },
                    {
                        title: "Missing L",
                        field: "Missing",
                        hozAlign: "center",
                        width: 70,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith(
                                    'PARENT')) {
                                return '';
                            }
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "MAP",
                        field: "MAP",
                        hozAlign: "center",
                        width: 90,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '';
                            }
                            const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                            const inv = parseFloat(rowData['INV']) || 0;
                            // Same as /map-issues: both sides must have stock to be Map / N Map.
                            if (inv > 0 && ebayStock > 0) {
                                // Mapped (green) when within /map-issues tolerance (3 units or rounded 3%)
                                if (ebayInvWithinMapTolerance(inv, ebayStock)) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                }
                                const diff = inv - ebayStock;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                            return '';
                        }
                    },

                    {
                        title: "L30 View",
                        field: "views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            let color = '';

                            // getViewColor logic from inc/dec page
                            if (value >= 30) color = '#28a745'; // green
                            else color = '#a00211'; // red

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "L7 VIEWS",
                        field: "l7_views",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        width: 70
                    },
                    {
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith(
                                'PARENT');

                            // Don't show dropdown for parent rows
                            // if (isParent) {
                            //     return '';
                            // }

                            // Get value and handle null/undefined/empty cases
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value
                                .trim() === '') {
                                value = 'REQ';
                            }

                            let bgColor = '#f8f9fa';
                            let textColor = '#000';

                            if (value === 'REQ') {
                                bgColor = '#28a745';
                                textColor = 'white';
                            } else if (value === 'NR') {
                                bgColor = '#dc3545';
                                textColor = 'white';
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
                        field: "eBay Price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const rowData = cell.getRow().getData();
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);

                            if (value === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }

                            if (lmpPrice > 0 && value > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 600;">$${value.toFixed(2)}</span>`;
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

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 50
                    },


                    {
                        title: "PFT %",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const percent = parseFloat(rowData['GPFT%'] || 0);
                            let color = '';

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: "avg",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
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

                            // getRoiColor logic from inc/dec page
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: "avg",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                        },
                        width: 65
                    },


                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const lmpPrice = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const totalCompetitors = rowData.lmp_entries_total || 0;
                            const currentPrice = parseFloat(rowData['eBay Price'] || 0);

                            if (!lmpPrice && totalCompetitors === 0) {
                                return `<a href="#" class="view-lmp-competitors" data-sku="${sku}"
                                    style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px;">
                                    <i class="fa fa-eye"></i> View
                                </a>`;
                            }

                            let html =
                                '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';

                            // Show lowest price
                            if (lmpPrice) {
                                const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                                const priceColor = (lmpPrice < currentPrice) ? '#dc3545' :
                                '#28a745';
                                html +=
                                    `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
                            }

                            // Show link to open modal with all competitors
                            if (totalCompetitors > 0) {
                                html += `<a href="#" class="view-lmp-competitors" data-sku="${sku}" 
                                    style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                                    <i class="fa fa-eye"></i> View ${totalCompetitors}
                                </a>`;
                            }

                            html += '</div>';
                            return html;
                        },
                        width: 70
                    },
                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const currentPrice = parseFloat(rowData['eBay Price']) || 0;
                            const spriceNum = (value != null && value !== '') ? parseFloat(value) :
                                NaN;
                            const sprice = isNaN(spriceNum) ? 0 : spriceNum;

                            // Blank only when SPRICE is missing or zero (no override)
                            if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0)
                                return '';

                            // Always show SPRICE when it has a value — even if it equals the eBay price.

                            const formattedValue = `$${Number(sprice).toFixed(2)}`;
                            if (hasCustomSprice === false) {
                                return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                            }
                            return formattedValue;
                        },
                        width: 80
                    },
                    {
                        field: "_accept",
                        hozAlign: "center",
                        headerSort: false,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                                <span>Accept</span>
                                <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                    <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                </button>
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');

                            // if (isParent) return '';

                            const sku = rowData['(Child) sku'];
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            const status = rowData.SPRICE_STATUS || null;

                            if (!sprice || sprice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745'; // Green for ready
                            let titleText = 'Apply Price to eBay';

                            if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107'; // Yellow text
                                titleText = 'Price pushing in progress...';
                            } else if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText =
                                'Price pushed to eBay (Double-click to mark as Applied)';
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'Price applied to eBay (Double-click to change)';
                            } else if (status === 'saved') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'SPRICE saved (Click to push to eBay)';
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545'; // Red text
                                titleText = 'Error applying price to eBay';
                            } else if (status === 'account_restricted') {
                                icon = '<i class="fa-solid fa-ban"></i>';
                                iconColor = '#ff6b00'; // Orange text
                                titleText =
                                    'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
                            }

                            // Show only icon with color, no background
                            return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                                ${icon}
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            const $target = $(e.target);

                            // Handle double-click to change status from 'pushed' to 'applied'
                            if (e.originalEvent && e.originalEvent.detail === 2) {
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const currentStatus = $btn.attr('data-status') || '';

                                if (currentStatus === 'pushed') {
                                    const sku = $btn.attr('data-sku') || $btn.data('sku');
                                    $.ajax({
                                        url: '/update-ebay-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: {
                                            sku: sku,
                                            status: 'applied'
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                table.replaceData();
                                                showToast('Status updated to Applied',
                                                    'success');
                                            }
                                        }
                                    });
                                }
                                return;
                            }

                            if ($target.hasClass('apply-price-btn') || $target.closest(
                                    '.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data(
                                    'price'));
                                const currentStatus = $btn.attr('data-status') || '';

                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('Invalid SKU or price', 'error');
                                    return;
                                }

                                // If status is 'saved' or null, first save SPRICE, then push to eBay
                                if (currentStatus === 'saved' || !currentStatus) {
                                    const row = cell.getRow();
                                    row.update({
                                        SPRICE_STATUS: 'processing'
                                    });

                                    saveSpriceWithRetry(sku, price, row)
                                        .then((response) => {
                                            // After saving, push to eBay
                                            applyPriceWithRetry(sku, price, cell, 0);
                                        })
                                        .catch((error) => {
                                            row.update({
                                                SPRICE_STATUS: 'error'
                                            });
                                            showToast('Failed to save SPRICE', 'error');
                                        });
                                } else {
                                    // If already saved, just push to eBay
                                    applyPriceWithRetry(sku, price, cell, 0);
                                }
                            }
                        }
                    },

                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        visible: false,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S PFT",
                        field: "SPFT",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const percent = parseFloat(rowData.SGPFT || 0);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S GROI",
                        field: "SGROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as GROI% / ROI% color logic
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },

                    // === Campaign-Ads columns (ES BID / C BID / PROMOTE) ===
                    // Same source & formatters as /ebay/campaign-ads. SKU-wise via listing_id; rows
                    // without a campaign-ads match stay visible with the data displayed as-is ('—').
                    {
                        title: "ES BID",
                        field: "ca_suggested_bid",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span class="text-muted">—</span>';
                            return `<span class="text-info fw-semibold">${v.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "C BID",
                        field: "ca_bid_percentage",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span class="text-muted">—</span>';
                            const color = v <= 4 ? '#dc3545' : v <= 7 ? '#ffc107' : v <= 13 ? '#198754' : '#e83e8c';
                            return `<span style="color:${color}; font-weight:600;">${v.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "PROMOTE",
                        field: "ca_promote_with_ad",
                        hozAlign: "center",
                        headerTooltip: "eBay Promotion eligibility status (from /ebay/campaign-ads)",
                        width: 140,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '<span class="text-muted">—</span>';
                            const map = {
                                'RECOMMENDED':        { color: '#198754', bg: '#d1f5e0', label: '⭐ Eligible' },
                                'OPTIONAL':           { color: '#856404', bg: '#fff3cd', label: '⚡ Optional' },
                                'AD_ALREADY_CREATED': { color: '#0d6efd', bg: '#cfe2ff', label: '📢 In Campaign' },
                                'NOT_RECOMMENDED':    { color: '#6c757d', bg: '#f8f9fa', label: '— Not Rec.' },
                                'UNDETERMINED':       { color: '#6c757d', bg: '#f8f9fa', label: '? Unknown' },
                            };
                            const s = map[v] || { color: '#6c757d', bg: '#f8f9fa', label: v };
                            return `<span style="color:${s.color}; background:${s.bg}; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">${s.label}</span>`;
                        }
                    }
                ]
            });

            // SKU & Parent Search functionality
            $('#sku-search, #parent-search').on('keyup', function() {
                table.setFilter([
                    { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                    { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
                ]);
                setTimeout(function() {
                    if (typeof updateSelectAllCheckbox === 'function') updateSelectAllCheckbox();
                }, 50);
            });

            // NR/REQ dropdown change handler
            $(document).on('change', '.nr-req-dropdown', function() {
                const $select = $(this);
                const value = $select.val();

                // Find the row and get SKU
                const $cell = $select.closest('.tabulator-cell');
                const row = table.getRow($cell.closest('.tabulator-row')[0]);

                if (!row) {
                    console.error('Could not find row');
                    return;
                }

                const sku = row.getData()['(Child) sku'];

                // Update the row data
                row.update({
                    nr_req: value
                });

                // Save to database using listing_ebay endpoint
                $.ajax({
                    url: '/listing_ebay/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('NR/REQ saved successfully for', sku, 'value:', value);
                            const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ?
                                'NR updated' : 'Status cleared');
                            showToast('success', message);
                        } else {
                            showToast('error', response.message || 'Failed to save status');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr
                            .responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
                    }
                });
            });

            // NRL listing-status dropdown change handler
            $(document).on('change', '.kw-nrl-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRL updated');
                            // Update row data
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NRL: value
                                });
                                // If NRL set to NRL, auto-set NRA to NRA
                                if (value === 'NRL') {
                                    rows[0].update({
                                        NR: 'NRA'
                                    });
                                    $.ajax({
                                        url: '/update-ebay-nr-data',
                                        method: 'POST',
                                        contentType: 'application/json',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: JSON.stringify({
                                            sku: sku,
                                            field: 'NR',
                                            value: 'NRA'
                                        })
                                    });
                                }
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRL');
                    }
                });
            });

            // NRA listing-status dropdown change handler
            $(document).on('change', '.kw-nra-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRA updated');
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NR: value
                                });
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRA');
                    }
                });
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                // Validate and save ratings field (must be between 0 and 5)
                if (field === 'rating') {
                    var numValue = parseFloat(value);
                    if (isNaN(numValue) || numValue < 0 || numValue > 5) {
                        alert('Ratings must be a number between 0 and 5');
                        cell.setValue(data.rating || 0); // Revert to original value
                        return;
                    }

                    // Save rating to database
                    $.ajax({
                        url: '/update-ebay-rating',
                        method: 'POST',
                        data: {
                            sku: data['(Child) sku'],
                            rating: numValue,
                            _token: $('meta[name=\"csrf-token\"]').attr('content')
                        },
                        success: function(response) {
                            console.log('Rating saved successfully');
                            showToast('success', 'Rating updated successfully');
                            // Update the row data
                            row.update({
                                rating: numValue
                            });
                        },
                        error: function(xhr) {
                            console.error('Error saving rating:', xhr.responseText);
                            showToast('error', 'Error saving rating');
                            cell.setValue(data.rating || 0); // Revert on error
                        }
                    });
                    return;
                }

                if (field === 'SPRICE') {
                    // Save SPRICE and recalculate SPFT, SROI
                    const row = cell.getRow();
                    row.update({
                        SPRICE_STATUS: 'processing'
                    });

                    saveSpriceWithRetry(data['(Child) sku'], value, row)
                        .then((response) => {
                            showToast('SPRICE saved successfully', 'success');
                        })
                        .catch((error) => {
                            showToast('Failed to save SPRICE', 'error');
                        });
                } else if (field === 'Listed' || field === 'Live') {
                    // Save Listed/Live status
                    $.ajax({
                        url: '/update-listed-live-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            field: field,
                            value: value
                        },
                        success: function(response) {
                            showToast('success', field + ' status updated successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to update ' + field + ' status');
                        }
                    });
                }
            });

            /**
             * Inventory mapping tolerance — same rule as /map-issues:
             * when 3% of INV is below 3 units, require an absolute gap > 3 units to be a mismatch;
             * otherwise apply the rounded 3% rule. Mapped (green) when within tolerance.
             */
            function ebayInvWithinMapTolerance(inv, stock) {
                const invNum = parseFloat(inv) || 0;
                const stockNum = parseFloat(stock) || 0;
                if (invNum <= 0) {
                    return true;
                }
                const diff = Math.abs(invNum - stockNum);
                let isNotMap;
                if (invNum * 0.03 < 3) {
                    isNotMap = diff > 3;
                } else {
                    isNotMap = Math.round((diff / invNum) * 100) > 3;
                }
                return !isNotMap;
            }

            /**
             * Missing M (eBay) — same logic as /map-issues N Map (mapping mismatch):
             * listed (has eBay item_id), REQ, INV > 0, eBay Stock > 0, and INV vs eBay Stock is OUTSIDE the map tolerance.
             */
            function isEbayMissingM(data) {
                var d = data || {};
                if (d.is_parent_summary === true) return false;
                var parent = d['Parent'];
                if (parent && String(parent).toUpperCase().startsWith('PARENT')) return false;
                var itemId = d['eBay_item_id'];
                if (!itemId || itemId === null || itemId === '') return false; // not listed -> handled by Missing L
                // REQ only — match the default "REQ Only" view (nr_req can also be NRL / LATER / NR).
                var nr = (d['nr_req'] || '').toString().trim().toUpperCase();
                if (nr !== 'REQ') return false;
                var inv = parseFloat(d['INV'] || 0) || 0;
                if (inv <= 0) return false;
                var ebayStock = parseFloat(d['eBay Stock'] || 0) || 0;
                if (ebayStock <= 0) return false; // same as /map-issues: both sides must have stock
                return !ebayInvWithinMapTolerance(inv, ebayStock);
            }

            /** eBay listing qty: API uses `eBay Stock` (column field); legacy code used `E Stock`. */
            function rowEbayStockQty(data) {
                var d = data || {};
                var v = d['eBay Stock'];
                if (v === undefined || v === null || v === '') v = d['E Stock'];
                return parseFloat(v || 0) || 0;
            }

            /**
             * Missing L (eBay) — same logic as amazon-tabulator-view Missing L:
             * row is NOT listed (no eBay item_id), NR/REQ is not 'NR', INV > 0, and not a parent row.
             */
            function isEbayMissingL(data) {
                var d = data || {};
                if (d.is_parent_summary === true) return false;
                var parent = d['Parent'];
                if (parent && String(parent).toUpperCase().startsWith('PARENT')) return false;
                var itemId = d['eBay_item_id'];
                var notListed = (!itemId || itemId === null || itemId === '');
                // REQ only — match the default "REQ Only" view (nr_req can also be NRL / LATER / NR).
                var nr = (d['nr_req'] || '').toString().trim().toUpperCase();
                var inv = parseFloat(d['INV'] || 0) || 0;
                return notListed && nr === 'REQ' && inv > 0;
            }

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const el30Filter = $('#el30-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const roiFilter = $('#roi-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const cvrTrendFilter = $('#cvr-trend-filter').val();
                const spriceFilter = $('#sprice-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const parentSkuVal = $('#parent-sku-dropdown').val() || '';
                const viewTypeFilter = $('#view-type-filter').val() || 'all';

                table.clearFilter(true);

                // When Play is active: show only current parent group (child SKUs + parent summary row, like product-master photo)
                // Skip View and Parent/SKU dropdown so we always show both children and parent row for that group
                if (!isProductNavigationActive) {
                    // View type: All | Parent | SKU (parent = only parent rows; sku = only child SKU rows)
                    if (viewTypeFilter === 'parent') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !!isParent;
                        });
                    } else if (viewTypeFilter === 'sku') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !isParent;
                        });
                    }

                    // Parent / SKU dropdown: show child SKUs for selected parent, or single row for selected SKU
                    if (parentSkuVal) {
                        if (parentSkuVal.startsWith('p:')) {
                            const parentVal = parentSkuVal.slice(2);
                            table.addFilter(function(data) {
                                return (data.Parent || '') === parentVal;
                            });
                        } else if (parentSkuVal.startsWith('s:')) {
                            const skuVal = parentSkuVal.slice(2);
                            table.addFilter(function(data) {
                                return (data['(Child) sku'] || '') === skuVal;
                            });
                        }
                    }
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV']) || 0) === 0;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV']) || 0) > 0;
                    });
                }

                if (el30Filter === 'zero') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['eBay L30'] || 0) || 0) === 0;
                    });
                } else if (el30Filter === 'more') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['eBay L30'] || 0) || 0) > 0;
                    });
                }

                const growthSign = $('#growth-sign-filter').val();
                if (growthSign && growthSign !== 'all') {
                    table.addFilter(function(data) {
                        const l30 = parseFloat(data['eBay L30']) || 0;
                        const l60 = parseFloat(data['eBay L60']) || 0;
                        let growth = 0;
                        if (l60 > 0) {
                            growth = ((l30 - l60) / l60) * 100;
                        } else if (l30 > 0) {
                            growth = 100;
                        }
                        const g = Math.round(growth);
                        if (growthSign === 'negative') return g < 0;
                        if (growthSign === 'zero') return g === 0;
                        if (growthSign === 'positive') return g > 0;
                        return true;
                    });
                }

                if (nrlFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (nrlFilter === 'REQ') {
                            return data.nr_req === 'REQ';
                        } else if (nrlFilter === 'NR') {
                            return data.nr_req === 'NR';
                        }
                        return true;
                    });
                }

                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;

                        // GPFT% is stored as a number, not a string with %
                        const gpft = parseFloat(data['GPFT%']) || 0;

                        if (gpftFilter === 'negative') return gpft < 0;
                        if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                        if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                        if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                        if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                        if (gpftFilter === '40plus') return gpft >= 40;
                        return true;
                    });
                }

                if (roiFilter !== 'all') {
                    table.addFilter(function(data) {
                        const roiVal = parseFloat(data['ROI%']) || 0;
                        if (roiFilter === 'lt40') return roiVal < 40;
                        if (roiFilter === '40-75') return roiVal >= 40 && roiVal < 75;
                        if (roiFilter === '75-125') return roiVal >= 75 && roiVal < 125;
                        if (roiFilter === 'gt125') return roiVal >= 125;
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;
                        // Extract CVR from SCVR field
                        const scvrValue = parseFloat(data['SCVR'] || 0);
                        const views = parseFloat(data.views || 0);
                        const l30 = parseFloat(data['eBay L30'] || 0);
                        const cvr = views > 0 ? (l30 / views) * 100 : 0;

                        // Round to 2 decimal places to avoid floating point precision issues
                        const cvrRounded = Math.round(cvr * 100) / 100;

                        if (cvrFilter === '0-0') return cvrRounded === 0;
                        if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                        if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                        if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                        if (cvrFilter === '13plus') return cvrRounded > 13;
                        return true;
                    });
                }

                // CVR trend filter: CVR 60 vs CVR 30 (same as Amazon)
                if (cvrTrendFilter !== 'all') {
                    const cvrTrendTol = 0.1;
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
                        const cvr30 = parseFloat(data['SCVR'] || 0);
                        const cvr60 = parseFloat(data['CVR_60'] || 0);
                        if (cvrTrendFilter === 'l60_gt_l30') return cvr60 > cvr30 + cvrTrendTol;
                        if (cvrTrendFilter === 'l30_gt_l60') return cvr30 > cvr60 + cvrTrendTol;
                        if (cvrTrendFilter === 'equal') return Math.abs(cvr60 - cvr30) <= cvrTrendTol;
                        return true;
                    });
                }

                if (spriceFilter === 'blank') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                // DIL filter
                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const l30 = parseFloat(data['L30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                        if (dilFilter === 'red') return dil < 16.66;
                        if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                // Badge Filters (E Stock > 0 — aligned with E Stock filter)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 === 0 && estock > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 > 0 && estock > 0;
                    });
                }

                if (missingLFilterActive) {
                    table.addFilter(function(data) {
                        return isEbayMissingL(data);
                    });
                }

                if (missingMFilterActive) {
                    table.addFilter(function(data) {
                        return isEbayMissingM(data);
                    });
                }

                // Play / Pause: show only current parent group (child SKUs + parent summary row, like product-master photo)
                if (isProductNavigationActive && productUniqueParents.length > 0 && currentProductParentIndex >=
                    0) {
                    var currentKey = productUniqueParents[currentProductParentIndex];
                    if (currentKey) {
                        table.addFilter(function(data) {
                            var p = (data.Parent || '').toString().trim();
                            return p === currentKey || p === ('PARENT ' + currentKey);
                        });
                    }
                }

                // Update range filter badge
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Update select all checkbox after filter is applied (matching Amazon approach)
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#view-type-filter, #parent-sku-dropdown, #inventory-filter, #el30-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter')
                .on('change', function() {
                    applyFilters();
                });

            $('#growth-sign-filter').on('change', function() {
                applyFilters();
            });

            // Columns that should ALWAYS stay hidden, regardless of saved state.
            var alwaysHiddenColumns = ['CVR_60', 'CVR_45', 'eBay L60', 'eBay L45', 'SGPFT', 'SPFT'];
            function enforceAlwaysHiddenColumns() {
                alwaysHiddenColumns.forEach(function(col) {
                    try { table.hideColumn(col); } catch (e) {}
                });
            }

            // No-op kept for compatibility with existing callers.
            function applySectionColumnVisibility(_sectionVal) {
                enforceAlwaysHiddenColumns();
                if (table && table.redraw) table.redraw(true);
            }

            // Update PFT% and ROI% calc values
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let sumLp = 0;

                data.forEach(row => {
                    const profit = parseFloat(row['Total_pft']) || 0;
                    const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                    // Only add if both values are > 0 (matching inc/dec page logic)
                    if (profit > 0 && salesL30 > 0) {
                        totalProfit += profit;
                        totalSales += salesL30;
                    }
                    sumLp += parseFloat(row['LP_productmaster']) || 0;
                });

                // PFT% and ROI% calculations removed - display elements removed
                // const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // const avgRoi = sumLp > 0 ? (totalProfit / sumLp) * 100 : 0;
            }

            // Update summary badges - use ALL data (not filtered) to match KW/PMP ads pages
            function updateSummary() {
                if (!table) return;
                // Use getData("all") to get ALL data without filters
                const allData = table.getData("all");
                const filteredData = table.getData("active");

                // Filtered data metrics (for other badges)
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;
                filteredData.forEach(row => {
                    const estock = rowEbayStockQty(row);
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);
                    const isParent = row['Parent'] && String(row['Parent']).toUpperCase().startsWith('PARENT');

                    // Financial totals: include ALL sold items (even out-of-stock) so Sales reflects
                    // true eBay sales. Exclude parent summary rows to avoid double counting.
                    if (!isParent) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaL30 += ebayL30;
                    }

                    if (estock > 0) {
                        // Count 0 Sold and > 0 Sold (only E Stock > 0)
                        if (ebayL30 === 0) {
                            zeroSoldCount++;
                        } else {
                            moreSoldCount++;
                        }

                        const dil = parseFloat(row['E Dil%'] || 0);
                        if (!isNaN(dil)) {
                            totalDilPercent += dil;
                            dilCount++;
                        }
                    }
                });

                let totalWeightedPrice = 0;
                let totalL30 = 0;
                filteredData.forEach(row => {
                    if (rowEbayStockQty(row) > 0) {
                        const price = parseFloat(row['eBay Price'] || 0);
                        const l30 = parseFloat(row['eBay L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;

                let totalViews = 0;
                filteredData.forEach(row => {
                    if (rowEbayStockQty(row) > 0) {
                        totalViews += parseFloat(row.views || 0);
                    }
                });
                // CVR = (orders-API L30 units sold / Σ views) × 100. Numerator is the
                // same fixed value the S Qty badge shows (Σ ebay_order_items.quantity
                // for period='l30') — same source /ebay/daily-sales uses, so the two
                // pages report the same units. Previously this used the per-row
                // eBay L30 sum from ebay_metrics, which lags the Orders API by 1-2
                // days (often 100+ units short — see /ebay/daily-sales discrepancy
                // notes), making CVR read low. Denominator stays the page sum of
                // 'views' across rows with E Stock > 0 — that's the eBay impression
                // pool the units are converting against.
                const avgCVR = totalViews > 0 ? (ORDERS_L30_TOTAL_QTY / totalViews * 100) : 0;

                // GROI% = (Total PFT / Total COGS) * 100
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;

                // GPFT% = (Total PFT / Total Sales) * 100
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;

                $('#total-sales-amt-badge').text('Sales: $' + Math.round(totalSalesAmt).toLocaleString());

                $('#avg-gpft-badge').text('GPFT: ' + Math.round(avgGpft) + '%');
                $('#groi-percent-badge').text('GROI: ' + Math.round(groiPercent) + '%');

                $('#avg-price-badge').text('Price: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(2) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());

                $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
                $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());

                let missingLCount = 0;
                let missingMCount = 0;
                allData.forEach(row => {
                    if (isEbayMissingL(row)) missingLCount++;
                    if (isEbayMissingM(row)) missingMCount++;
                });
                $('#missing-l-count-badge').text('Missing L: ' + missingLCount.toLocaleString());
                $('#missing-m-count-badge').text('Missing M: ' + missingMCount.toLocaleString());
            }

            /*
             * Column visibility (every column for this page) persists in the shared DB table
             * `channel_tabulator_column_settings` under channel = 'ebay1_tabulator'. We hit the
             * same /tabulator-column-visibility endpoint used by the ebay2 / ebay3 / mfrg /
             * amazon tabulators so a single row owns the show/hide map for everyone on this view.
             */
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                if (!menu) return;
                menu.innerHTML = '';

                fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(savedVisibility => {
                        const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;

                            const li = document.createElement("li");
                            const label = document.createElement("label");
                            label.style.display = "block";
                            label.style.padding = "5px 10px";
                            label.style.cursor = "pointer";

                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = def.field;
                            // Prefer saved value; anything explicitly false in the DB map = hidden.
                            // Otherwise fall back to the column's current visibility.
                            checkbox.checked = map.hasOwnProperty(def.field) ? (map[def.field] !== false) : col.isVisible();
                            checkbox.style.marginRight = "8px";

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(def.title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    })
                    .catch(err => console.error('Error loading column visibility:', err));
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field) {
                        visibility[def.field] = col.isVisible();
                    }
                });

                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        visibility: visibility
                    })
                }).catch(err => console.error('Error saving column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(savedVisibility => {
                        if (savedVisibility && typeof savedVisibility === 'object') {
                            table.getColumns().forEach(col => {
                                const def = col.getDefinition();
                                if (def.field && savedVisibility.hasOwnProperty(def.field)) {
                                    if (savedVisibility[def.field]) {
                                        col.show();
                                    } else {
                                        col.hide();
                                    }
                                }
                            });
                        }
                        enforceAlwaysHiddenColumns();
                    })
                    .catch(err => console.error('Error applying column visibility:', err));
            }

            // Wait for table to be built
            // eBay1 sales (from shopify_raw_orders, L30, excludes cancelled / other eBay stores)
            function loadEbay1ShopifySales() {
                fetch('{{ route('shopify-raw-data.ebay1-sales') }}', {
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    })
                    .then(r => r.json())
                    .then(d => {
                        const sales = parseFloat(d.sales || 0);
                        $('#ebay1-shopify-sales-badge')
                            .text('EShp: $' + Math.round(sales).toLocaleString())
                            .attr('title', 'eBay1 sales from Shopify raw data ' +
                                (d.date_from || '') + ' to ' + (d.date_to || '') +
                                ' (excludes cancelled). Orders: ' + (d.orders || 0) + ', Qty: ' + (d.qty || 0));
                    })
                    .catch(() => {});
            }

            table.on('tableBuilt', function() {
                applySectionColumnVisibility('all');
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
                loadEbay1ShopifySales();

                // Set up periodic background retry check (every 30 seconds)
                setInterval(() => {
                    backgroundRetryFailedSkus();
                }, 30000);
            });

            table.on('dataLoaded', function() {
                // Populate Parent / SKU dropdown: unique parents and all SKUs (show child SKUs on select)
                var allRows = table.getData('all') || [];
                var parents = [];
                var seenParent = {};
                allRows.forEach(function(r) {
                    var p = r.Parent || '';
                    if (p && String(p).trim() !== '' && !String(p).toUpperCase().startsWith(
                            'PARENT') && !seenParent[p]) {
                        seenParent[p] = true;
                        parents.push(p);
                    }
                });
                parents.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                // Use same parent list for Play/Next/Previous (single parent SKUs like product-master)
                productUniqueParents = parents.slice(0);
                var skus = allRows.map(function(r) {
                    return r['(Child) sku'] || '';
                }).filter(function(s) {
                    return s !== '';
                });
                skus.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                var $dropdown = $('#parent-sku-dropdown');
                $dropdown.find('option:not(:first)').remove();
                if (parents.length > 0) {
                    var $pg = $('<optgroup label="Parents (show child SKUs)">');
                    parents.forEach(function(p) {
                        $pg.append($('<option>').attr('value', 'p:' + p).text(p));
                    });
                    $dropdown.append($pg);
                }
                if (skus.length > 0) {
                    var $sg = $('<optgroup label="SKUs">');
                    skus.forEach(function(s) {
                        $sg.append($('<option>').attr('value', 's:' + s).text(s));
                    });
                    $dropdown.append($sg);
                }
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Refresh checkboxes to reflect selectedSkus set (matching Amazon approach)
                setTimeout(function() {
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
                // Redraw so rowFormatter runs and parent rows get light blue background
                setTimeout(function() {
                    table.redraw(true);
                }, 50);
                // Play / Pause parent navigation (same as product-master)
                initProductPlaybackControls();
            });

            // Also initialize tooltips when table is rendered (matching Amazon approach)
            table.on('renderComplete', function() {
                setTimeout(function() {
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
            });

            // Toggle column from dropdown
            (function() {
                var colMenu = document.getElementById("column-dropdown-menu");
                if (colMenu) {
                    colMenu.addEventListener("change", function(e) {
                        if (e.target.type === 'checkbox') {
                            const field = e.target.value;
                            const col = table.getColumn(field);
                            if (e.target.checked) {
                                col.show();
                            } else {
                                col.hide();
                            }
                            saveColumnVisibilityToServer();
                        }
                    });
                }
                var showAllBtn = document.getElementById("show-all-columns-btn");
                if (showAllBtn) {
                    showAllBtn.addEventListener("click", function() {
                        table.getColumns().forEach(col => {
                            col.show();
                        });
                        buildColumnDropdown();
                        saveColumnVisibilityToServer();
                    });
                }
            })();

            document.addEventListener("click", function(e) {
                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn")) {
                    const sku = e.target.getAttribute("data-sku");

                    // Copy to clipboard
                    navigator.clipboard.writeText(sku).then(function() {
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    }).catch(function(err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = sku;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    });
                }

                // View SKU chart
                if (e.target.closest('.view-sku-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = e.target.closest('.view-sku-chart').getAttribute('data-sku');
                    currentSku = sku;
                    $('#modalSkuName').text(sku);
                    $('#sku-chart-days-filter').val('30');
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 30);
                    $('#skuMetricsModal').modal('show');
                }
            });

            // Toast notification
            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                '(Child) sku': 'SKU',
                'INV': 'INV',
                'L30': 'L30',
                'E Dil%': 'Dil%',
                'eBay L30': 'eBay L30',
                'eBay L45': 'eBay L45',
                'eBay L60': 'eBay L60',
                'growth_percent': 'Growth',
                'eBay Stock': 'eBay Stock',
                'Missing': 'Missing',
                'MAP': 'MAP',
                'eBay Price': 'eBay Price',
                'lmp_price': 'LMP',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'GROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SPFT',
                'SGROI': 'SGROI',
                'SROI': 'SROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'CVR 30',
                'CVR_45': 'CVR 45',
                'CVR_60': 'CVR 60',
                'ebay2_ship': 'eBay2 Ship',
                'LP_productmaster': 'LP',
                'ca_suggested_bid': 'ES BID',
                'ca_bid_percentage': 'C BID',
                'ca_promote_with_ad': 'PROMOTE'
            };

            // Build export columns list
            function buildExportColumnsList() {
                const container = document.getElementById('export-columns-list');
                container.innerHTML = '';

                const columns = table.getColumns().filter(col => {
                    const field = col.getField();
                    return field && exportColumnMapping[field] && field !== '_select' && field !==
                    '_accept';
                });

                columns.forEach(col => {
                    const field = col.getField();
                    const displayName = exportColumnMapping[field];

                    const div = document.createElement('div');
                    div.className = 'form-check mb-2';
                    div.innerHTML = `
                        <input class="form-check-input export-column-checkbox" type="checkbox" 
                               value="${field}" id="export-col-${field}" checked>
                        <label class="form-check-label" for="export-col-${field}">
                            ${displayName}
                        </label>
                    `;
                    container.appendChild(div);
                });
            }

            // Select all export columns
            $('#select-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', true);
            });

            // Deselect all export columns
            $('#deselect-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', false);
            });

            // Confirm export
            $('#confirm-export-btn').on('click', function() {
                const selectedColumns = [];
                $('.export-column-checkbox:checked').each(function() {
                    selectedColumns.push($(this).val());
                });

                if (selectedColumns.length === 0) {
                    showToast('error', 'Please select at least one column to export');
                    return;
                }

                // Build export URL with selected columns
                const columnsParam = encodeURIComponent(JSON.stringify(selectedColumns));
                const exportUrl = `/ebay-export?columns=${columnsParam}`;

                // Close modal and trigger download
                $('#exportModal').modal('hide');
                window.location.href = exportUrl;
            });

            // When export modal is shown, build the columns list
            $('#exportModal').on('show.bs.modal', function() {
                if (table) {
                    buildExportColumnsList();
                }
            });

            // Import Ratings Modal Handler
            $('#importForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#csvFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a CSV file');
                    return;
                }

                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');

                const uploadBtn = $('#uploadBtn');
                uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/import-ebay-ratings',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success ||
                            'Ratings imported successfully');

                        // Reload table data
                        setTimeout(() => {
                            table.setData(EBAY_DATA_JSON_URL);
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
                        const errorMsg = xhr.responseJSON?.error || 'Failed to import ratings';
                        showToast('error', errorMsg);
                    }
                });
            });
        });

        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null
        };

        // Load Competitors Modal Function
        function cleanupLmpModalBackdrop() {
            document.querySelectorAll('.modal-backdrop').forEach(function(node) {
                node.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        function openLmpModal() {
            const el = document.getElementById('lmpModal');
            if (!el) {
                return;
            }
            if (el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            cleanupLmpModalBackdrop();
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        }

        function loadEbayCompetitorsModal(sku) {
            $('#lmpSku').text(sku);

            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');

            openLmpModal();

            // Show loading state
            $('#lmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `);

            // Fetch LMP data
            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: {
                    sku: sku
                },
                success: function(response) {
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.sku = sku;
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;

                        renderEbayCompetitorsList(response.competitors, response.lowest_price);
                    } else {
                        $('#lmpDataList').html(`
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    console.error('Error loading competitors:', xhr);
                    // Distinct message: an AJAX failure is NOT the same as "really empty".
                    // The form to add a new competitor is already visible above the list,
                    // so we don't need to nudge the user there — they need to know the
                    // load actually failed and a retry is the right next step.
                    $('#lmpDataList').html(`
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i>
                            Could not load competitors. Please close this dialog and try again.
                        </div>
                    `);
                }
            });
        }

        // Render Competitors List Function
        function renderEbayCompetitorsList(competitors, lowestPrice) {
            if (!competitors || competitors.length === 0) {
                $('#lmpDataList').html(`
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No competitors found for this SKU
                    </div>
                `);
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `
                <thead class="table-dark">
                    <tr>
                        <th>Image</th>
                        <th>Price</th>
                        <th>Shipping</th>
                        <th>Total</th>
                        <th>Title</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;

            competitors.forEach(function(item) {
                const isLowest = item.total_price === lowestPrice;
                const rowClass = isLowest ? 'table-success' : '';
                const badge = isLowest ? '<span class="badge bg-success ms-2">Lowest</span>' : '';
                const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
                const imageCell = item.image
                    ? `<img src="${item.image}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                    : '<span class="text-muted">—</span>';

                html += `
                    <tr class="${rowClass}">
                        <td>${imageCell}</td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                        <td><strong>$${parseFloat(item.total_price).toFixed(2)}</strong> ${badge}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${item.title || 'N/A'}
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-ebay-lmp-btn" 
                                    data-id="${item.id}" 
                                    data-item-id="${item.item_id}" 
                                    data-price="${item.total_price}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }

        // View Competitors Modal Event Listener
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            loadEbayCompetitorsModal(sku);
        });

        // Add Competitor Form Submission
        $('#addCompetitorForm').on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

            $.ajax({
                url: '/ebay-lmp-add',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: $('#addCompSku').val(),
                    item_id: $('#addCompItemId').val(),
                    price: $('#addCompPrice').val(),
                    shipping_cost: $('#addCompShipping').val() || 0,
                    product_link: $('#addCompLink').val(),
                    product_title: $('#addCompTitle').val()
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor added successfully', 'success');

                        // Clear form
                        $('#addCompItemId').val('');
                        $('#addCompPrice').val('');
                        $('#addCompShipping').val('');
                        $('#addCompLink').val('');
                        $('#addCompTitle').val('');

                        // Reload competitors list
                        const sku = $('#addCompSku').val();
                        loadEbayCompetitorsModal(sku);

                        // Reload main table data
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to add competitor';
                    showToast(errorMsg, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Delete Competitor Button Click
        $(document).on('click', '.delete-ebay-lmp-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const itemId = $btn.data('item-id');
            const price = $btn.data('price');

            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) {
                return;
            }

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: '/ebay-lmp-delete',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');

                        // Reload competitors list
                        const sku = currentLmpData.sku;
                        loadEbayCompetitorsModal(sku);

                        // Reload main table data
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to delete competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to delete competitor';
                    showToast(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });


        // Tooltip functions for eBay links
        function showEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        }

        function hideEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        }

        // Export LMP — flatten all competitor entries for every SKU into one CSV
        $('#export-lmp-btn').on('click', function() {
            if (!table) {
                alert('Table not loaded');
                return;
            }

            const allRows = table.getData();
            const lmpRows = [];

            allRows.forEach(function(row) {
                if (row.is_parent_summary) return;
                const sku = row['(Child) sku'] || '';
                const currentPrice = row['eBay Price'] || '';
                const entries = row.lmp_entries || [];

                if (entries.length === 0) {
                    lmpRows.push({
                        sku: sku,
                        current_price: currentPrice,
                        lmp_lowest: row.lmp_price || '',
                        comp_asin: '',
                        comp_title: '',
                        comp_price: '',
                        comp_seller: '',
                        comp_rating: '',
                        comp_reviews: '',
                        comp_monthly_revenue: '',
                        comp_monthly_units: '',
                        comp_buy_box_owner: '',
                        comp_seller_type: '',
                        comp_link: ''
                    });
                } else {
                    entries.forEach(function(comp) {
                        lmpRows.push({
                            sku: sku,
                            current_price: currentPrice,
                            lmp_lowest: row.lmp_price || '',
                            comp_asin: comp.asin || '',
                            comp_title: comp.title || comp.product_title || '',
                            comp_price: comp.price !== null && comp.price !== undefined ? comp.price : '',
                            comp_seller: comp.seller_name || '',
                            comp_rating: comp.rating !== null && comp.rating !== undefined ? comp.rating : '',
                            comp_reviews: comp.reviews !== null && comp.reviews !== undefined ? comp.reviews : '',
                            comp_monthly_revenue: comp.monthly_revenue !== null && comp.monthly_revenue !== undefined ? comp.monthly_revenue : '',
                            comp_monthly_units: comp.monthly_units_sold !== null && comp.monthly_units_sold !== undefined ? comp.monthly_units_sold : '',
                            comp_buy_box_owner: comp.buy_box_owner || '',
                            comp_seller_type: comp.seller_type || '',
                            comp_link: comp.link || comp.product_link || ''
                        });
                    });
                }
            });

            if (lmpRows.length === 0) {
                alert('No LMP data to export');
                return;
            }

            const headers = [
                'SKU', 'Current Price', 'LMP Lowest', 'Comp ASIN', 'Comp Title',
                'Comp Price', 'Comp Seller', 'Rating', 'Reviews',
                'Monthly Revenue', 'Monthly Units', 'Buy Box Owner', 'Seller Type', 'Link'
            ];
            const fields = [
                'sku', 'current_price', 'lmp_lowest', 'comp_asin', 'comp_title',
                'comp_price', 'comp_seller', 'comp_rating', 'comp_reviews',
                'comp_monthly_revenue', 'comp_monthly_units', 'comp_buy_box_owner', 'comp_seller_type', 'comp_link'
            ];

            function escapeCsvCell(val) {
                val = String(val === null || val === undefined ? '' : val);
                val = val.replace(/"/g, '""');
                if (val.includes(',') || val.includes('"') || val.includes('\n')) {
                    val = '"' + val + '"';
                }
                return val;
            }

            let csv = headers.map(escapeCsvCell).join(',') + '\n';
            lmpRows.forEach(function(r) {
                csv += fields.map(function(f) { return escapeCsvCell(r[f]); }).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'eBay_LMP_Export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showToast('success', 'Exported LMP data for ' + lmpRows.length + ' competitor rows');
        });

        // ════════════════════════════════════════════════════════════════════
        // SBID Rule modal (shared with /ebay/campaign-ads; same endpoints)
        // Edits L7 views threshold + SCVR band lookup + dynamic sub-rules.
        // No S Bid column on this page — the rule is just editable here for convenience.
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const ruleGetUrl  = '/ebay/campaign-ads/rule';
            const ruleSaveUrl = '/ebay/campaign-ads/rule';
            let currentRule = { l7_views_threshold: 70, bands: [] };

            // Metric options for a band's dynamic sub-rule
            const SUB_METRICS = {
                scvr:       { label: 'SCVR %',   unit: '%', step: '0.1' },
                ebay_price: { label: 'Price $',  unit: '$', step: '0.01' },
                ebay_l30:   { label: 'L30 Sold', unit: '',  step: '1'   },
                views:      { label: 'Views',    unit: '',  step: '1'   },
            };

            function renderBands(bands) {
                const tbody = document.getElementById('sbid-bands-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                (bands || []).forEach(function(band, i) {
                    const isLast = (parseFloat(band.scvr_max) >= 9999);
                    const hasSub = !!(band.sub && band.sub.metric);
                    tbody.innerHTML += `
                    <tr>
                        <td class="text-center text-muted small">${i + 1}</td>
                        <td><input type="text" class="form-control form-control-sm" value="${band.label || ''}"
                                   data-idx="${i}" data-field="label" onchange="window.sbidRuleUpdateBand(this)"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color form-control-sm" style="width:40px;height:31px;"
                                       value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color"
                                       onchange="window.sbidRuleUpdateBand(this)">
                                <span class="badge" style="background:${band.color || '#6c757d'};">${band.label || ''}</span>
                            </div>
                        </td>
                        <td>
                            ${isLast
                                ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="' + i + '" data-field="scvr_max">'
                                : `<input type="number" step="0.1" min="0" class="form-control form-control-sm" value="${band.scvr_max}"
                                          data-idx="${i}" data-field="scvr_max" onchange="window.sbidRuleUpdateBand(this)">`
                            }
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                                       value="${band.bid}" data-idx="${i}" data-field="bid" ${hasSub ? 'disabled' : ''}
                                       style="color:${band.color || '#333'}; font-weight:600;"
                                       onchange="window.sbidRuleUpdateBand(this)">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-check form-check-inline mt-1">
                                <input class="form-check-input" type="checkbox" id="sub-toggle-${i}" ${hasSub ? 'checked' : ''}
                                       onchange="window.sbidRuleToggleSub(${i}, this.checked)">
                                <label class="form-check-label small text-muted" for="sub-toggle-${i}">Dynamic by metric</label>
                            </div>
                        </td>
                    </tr>
                    ${hasSub ? renderSubEditor(band, i) : ''}`;
                });
            }

            function renderSubEditor(band, i) {
                const sub    = band.sub || { metric: 'ebay_price', bands: [] };
                const metric = sub.metric || 'ebay_price';
                const unit   = (SUB_METRICS[metric] || {}).unit || '';
                const step   = (SUB_METRICS[metric] || {}).step || '0.1';
                const opts   = Object.keys(SUB_METRICS).map(function(k) {
                    return `<option value="${k}" ${k === metric ? 'selected' : ''}>${SUB_METRICS[k].label}</option>`;
                }).join('');

                const rows = (sub.bands || []).map(function(sb, j) {
                    const isLastSub = (parseFloat(sb.max) >= 9999);
                    return `
                    <tr>
                        <td class="text-center text-muted small">${j + 1}</td>
                        <td>
                            ${isLastSub
                                ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="' + i + '" data-sub="max" data-j="' + j + '">'
                                : `<div class="input-group input-group-sm">
                                       <input type="number" step="${step}" min="0" class="form-control form-control-sm"
                                              value="${sb.max}" data-idx="${i}" data-sub="max" data-j="${j}" onchange="window.sbidRuleUpdateSubBand(this)">
                                       <span class="input-group-text">${unit || '≤'}</span>
                                   </div>`
                            }
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                                       value="${sb.bid}" data-idx="${i}" data-sub="bid" data-j="${j}" onchange="window.sbidRuleUpdateSubBand(this)">
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                    onclick="window.sbidRuleRemoveSubBand(${i}, ${j})" title="Remove tier">&times;</button>
                        </td>
                    </tr>`;
                }).join('');

                return `
                <tr class="sub-rule-row">
                    <td></td>
                    <td colspan="4" class="bg-light">
                        <div class="border rounded p-2">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="small fw-semibold text-secondary">
                                    <i class="fas fa-layer-group me-1"></i>${band.label || 'Band'} — bid by
                                </span>
                                <select class="form-select form-select-sm" style="width:auto;"
                                        data-idx="${i}" onchange="window.sbidRuleUpdateSubMetric(${i}, this.value)">${opts}</select>
                                <span class="small text-muted">tiers (top to bottom — first match wins)</span>
                            </div>
                            <table class="table table-sm table-bordered align-middle mb-2">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>${(SUB_METRICS[metric] || {}).label || 'Value'} ≤</th>
                                        <th>Bid (%)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0"
                                    onclick="window.sbidRuleAddSubBand(${i})">
                                <i class="fas fa-plus me-1"></i>Add tier
                            </button>
                            <span class="small text-muted ms-2">Set the last tier's value to <code>9999</code> as the catch-all.</span>
                        </div>
                    </td>
                </tr>`;
            }

            // Exposed globals (referenced by inline onchange handlers above).
            // Prefixed with `sbidRule` to avoid clashing with anything else on this page.
            window.sbidRuleUpdateBand = function(el) {
                const idx   = parseInt(el.dataset.idx);
                const field = el.dataset.field;
                if (!currentRule.bands[idx]) return;
                currentRule.bands[idx][field] = (field === 'scvr_max' || field === 'bid')
                    ? parseFloat(el.value) : el.value;
                if (field === 'color') {
                    const badge = el.closest('tr').querySelector('.badge');
                    if (badge) badge.style.background = el.value;
                }
            };

            window.sbidRuleToggleSub = function(idx, enabled) {
                if (!currentRule.bands[idx]) return;
                if (enabled) {
                    currentRule.bands[idx].sub = {
                        metric: 'ebay_price',
                        bands: [{ max: 9999, bid: parseFloat(currentRule.bands[idx].bid) || 2.1 }]
                    };
                } else {
                    delete currentRule.bands[idx].sub;
                }
                renderBands(currentRule.bands);
            };

            window.sbidRuleUpdateSubMetric = function(idx, metric) {
                if (!currentRule.bands[idx] || !currentRule.bands[idx].sub) return;
                currentRule.bands[idx].sub.metric = metric;
                renderBands(currentRule.bands);
            };

            window.sbidRuleUpdateSubBand = function(el) {
                const idx   = parseInt(el.dataset.idx);
                const j     = parseInt(el.dataset.j);
                const field = el.dataset.sub;
                if (!currentRule.bands[idx] || !currentRule.bands[idx].sub) return;
                currentRule.bands[idx].sub.bands[j][field] = parseFloat(el.value);
            };

            window.sbidRuleAddSubBand = function(idx) {
                if (!currentRule.bands[idx] || !currentRule.bands[idx].sub) return;
                const sb = currentRule.bands[idx].sub.bands;
                const lastIsCatch = sb.length && parseFloat(sb[sb.length - 1].max) >= 9999;
                const newTier = { max: 0, bid: parseFloat(currentRule.bands[idx].bid) || 2.1 };
                if (lastIsCatch) sb.splice(sb.length - 1, 0, newTier);
                else sb.push(newTier);
                renderBands(currentRule.bands);
            };

            window.sbidRuleRemoveSubBand = function(idx, j) {
                if (!currentRule.bands[idx] || !currentRule.bands[idx].sub) return;
                currentRule.bands[idx].sub.bands.splice(j, 1);
                renderBands(currentRule.bands);
            };

            // Fetches the saved rule and re-renders the L7 threshold input + bands table.
            // Called once at script load (pre-populates the modal so it's ready on first open)
            // and again every time the modal opens (so we always get the freshest data).
            function loadRule() {
                $.ajax({
                    url: ruleGetUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentRule = data || { l7_views_threshold: 70, bands: [] };
                        if (currentRule.l7_views_threshold == null) currentRule.l7_views_threshold = 70;
                        if (!Array.isArray(currentRule.bands)) currentRule.bands = [];
                        const thrEl = document.getElementById('sbid-l7-threshold-input');
                        if (thrEl) thrEl.value = currentRule.l7_views_threshold;
                        renderBands(currentRule.bands);
                    },
                    error: function(xhr) {
                        const errEl = document.getElementById('sbid-rule-err');
                        if (errEl) {
                            errEl.textContent = 'Could not load SBID Rule (HTTP ' + xhr.status + '). Check console / network tab.';
                            errEl.classList.remove('d-none');
                        }
                        // eslint-disable-next-line no-console
                        console.error('[SBID Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            // Wait for jQuery + the modal DOM to be ready, then prime the rule.
            // Using both DOMContentLoaded and a fallback so it works regardless of where the
            // script tag ends up relative to the modal HTML.
            function primeOnReady() {
                if (document.getElementById('sbidRuleModal')) {
                    loadRule();
                } else {
                    document.addEventListener('DOMContentLoaded', loadRule);
                }
            }
            primeOnReady();

            // Reload on every modal open so we always reflect the latest server state.
            // Using vanilla addEventListener (mirrors /ebay/campaign-ads) — Bootstrap 5 fires
            // these as native CustomEvents which addEventListener catches reliably.
            const modalEl = document.getElementById('sbidRuleModal');
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', loadRule);
            }

            // Track threshold edits in-memory
            $(document).on('change', '#sbid-l7-threshold-input', function() {
                currentRule.l7_views_threshold = parseFloat(this.value) || 0;
            });

            // Save
            $('#sbid-rule-save-btn').on('click', function() {
                const errEl = document.getElementById('sbid-rule-err');
                errEl.classList.add('d-none');
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

                const threshold = parseFloat(document.getElementById('sbid-l7-threshold-input').value);

                $.ajax({
                    url: ruleSaveUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        bands: currentRule.bands || [],
                        l7_views_threshold: isFinite(threshold) ? threshold : 70,
                    }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                        currentRule = resp.rule || currentRule;
                        if (typeof showToast === 'function') showToast('success', 'SBID Rule saved');
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                            const modalEl = document.getElementById('sbidRuleModal');
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }, 1000);
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                        errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                        errEl.classList.remove('d-none');
                    }
                });
            });
        })();

        // ════════════════════════════════════════════════════════════════════
        // Dil Rule modal (shared with /ebay/campaign-ads; same endpoints)
        // Edits DIL% color bands. Storage: ebay_sbid_rules.key = 'ebay1_dil'.
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const dilGetUrl  = '/ebay/campaign-ads/dil-rule';
            const dilSaveUrl = '/ebay/campaign-ads/dil-rule';
            let currentDilRule = { bands: [] };

            function renderDilBands(bands) {
                const tbody = document.getElementById('dil-bands-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                (bands || []).forEach(function(band, i) {
                    const isLast = (parseFloat(band.dil_max) >= 9999);
                    tbody.innerHTML += `
                    <tr>
                        <td class="text-center text-muted small">${i + 1}</td>
                        <td><input type="text" class="form-control form-control-sm" value="${band.label || ''}"
                                   data-idx="${i}" data-field="label" onchange="window.dilRuleUpdateBand(this)"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color form-control-sm" style="width:40px;height:31px;"
                                       value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color"
                                       onchange="window.dilRuleUpdateBand(this)">
                                <span class="badge" style="background:${band.color || '#6c757d'};">${band.label || ''}</span>
                            </div>
                        </td>
                        <td>
                            ${isLast
                                ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="' + i + '" data-field="dil_max">'
                                : `<input type="number" step="0.01" min="0" class="form-control form-control-sm" value="${band.dil_max}"
                                          data-idx="${i}" data-field="dil_max" onchange="window.dilRuleUpdateBand(this)">`
                            }
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                                       value="${band.bid != null ? band.bid : ''}" data-idx="${i}" data-field="bid"
                                       style="color:${band.color || '#333'}; font-weight:600;"
                                       onchange="window.dilRuleUpdateBand(this)">
                                <span class="input-group-text">%</span>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                        onclick="window.dilRuleRemoveBand(${i})" title="Remove band">&times;</button>
                            </div>
                        </td>
                    </tr>`;
                });
            }

            // Inline-handler globals (prefixed to avoid clashing with anything else on this page).
            window.dilRuleUpdateBand = function(el) {
                const idx   = parseInt(el.dataset.idx);
                const field = el.dataset.field;
                if (!currentDilRule.bands[idx]) return;
                currentDilRule.bands[idx][field] = (field === 'dil_max' || field === 'bid')
                    ? parseFloat(el.value) : el.value;
                if (field === 'color') {
                    const badge = el.closest('tr').querySelector('.badge');
                    if (badge) badge.style.background = el.value;
                }
            };

            window.dilRuleRemoveBand = function(idx) {
                currentDilRule.bands.splice(idx, 1);
                renderDilBands(currentDilRule.bands);
            };

            // Add-band button
            $(document).on('click', '#dil-add-band-btn', function() {
                const bands = currentDilRule.bands;
                const lastIsCatch = bands.length && parseFloat(bands[bands.length - 1].dil_max) >= 9999;
                const newBand = { dil_max: 0, bid: 2.1, label: 'New', color: '#6c757d' };
                if (lastIsCatch) bands.splice(bands.length - 1, 0, newBand);
                else bands.push(newBand);
                renderDilBands(bands);
            });

            function loadDilRule() {
                $.ajax({
                    url: dilGetUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentDilRule = data || { bands: [] };
                        if (!Array.isArray(currentDilRule.bands)) currentDilRule.bands = [];
                        renderDilBands(currentDilRule.bands);
                    },
                    error: function(xhr) {
                        const errEl = document.getElementById('dil-rule-err');
                        if (errEl) {
                            errEl.textContent = 'Could not load Dil Rule (HTTP ' + xhr.status + '). Check console / network tab.';
                            errEl.classList.remove('d-none');
                        }
                        // eslint-disable-next-line no-console
                        console.error('[Dil Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            // Prime on init
            if (document.getElementById('dilRuleModal')) {
                loadDilRule();
            } else {
                document.addEventListener('DOMContentLoaded', loadDilRule);
            }

            // Reload on each modal open
            const dilModalEl = document.getElementById('dilRuleModal');
            if (dilModalEl) {
                dilModalEl.addEventListener('show.bs.modal', loadDilRule);
            }

            // Save
            $('#dil-rule-save-btn').on('click', function() {
                const errEl = document.getElementById('dil-rule-err');
                errEl.classList.add('d-none');
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

                $.ajax({
                    url: dilSaveUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ bands: currentDilRule.bands || [] }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                        currentDilRule = resp.rule || currentDilRule;
                        if (typeof showToast === 'function') showToast('success', 'Dil Rule saved');
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                            const modal = bootstrap.Modal.getInstance(document.getElementById('dilRuleModal'));
                            if (modal) modal.hide();
                        }, 1000);
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                        errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                        errEl.classList.remove('d-none');
                    }
                });
            });
        })();

    </script>
@endsection
