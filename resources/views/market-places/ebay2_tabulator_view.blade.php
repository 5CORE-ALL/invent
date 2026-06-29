@extends('layouts.vertical', ['title' => 'Ebay 2 - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Toolbar: compact controls, wrap to next row if needed.
           NOTE: do NOT use overflow-x on this row — it clips Bootstrap dropdown menus
           (Columns, DIL%, etc.) so they wouldn't open. */
        .ebay2-toolbar-row {
            row-gap: 4px;
        }
        /* Compact every interactive control in the toolbar so more fits per row,
           but keep enough padding for the native select arrow + full label text. */
        .ebay2-toolbar-row > .form-select,
        .ebay2-toolbar-row .form-select.pricing-filter-item,
        .ebay2-toolbar-row > .btn,
        .ebay2-toolbar-row > .dropdown > .btn,
        .ebay2-toolbar-row > .manual-dropdown-container > .btn {
            padding: 3px 10px;
            font-size: 0.8125rem;
            line-height: 1.3;
            min-height: 30px;
        }
        /* Selects need a touch more right-side room so the native ▼ arrow doesn't overlap the label. */
        .ebay2-toolbar-row .form-select {
            padding-right: 24px;
            background-position: right 6px center;
        }
        .ebay2-toolbar-row .dropdown-menu {
            font-size: 0.8125rem;
        }

        /* Image column hover preview (forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Status circle indicators */
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

        /* Manual dropdown styling */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .manual-dropdown-container .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
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

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }

        /* Forecast NRP (REQ / 2BDC / LATER) — shared with Forecast Analysis */
        .nrp-dot-cell { min-height: 32px; min-width: 44px; }
        .nrp-dot-cell .nrp-status-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            border: 1px solid rgba(0,0,0,.12); flex-shrink: 0;
        }
        .nrp-dot-cell .nrp-nr-select {
            opacity: 0; cursor: pointer; font-size: 11px; padding: 0; border: 0; background: transparent;
        }
        .nrp-dot-cell .nrp-nr-select:focus { opacity: 1; outline: 1px solid #0d6efd; }

        /* Summary badges: one row only; share width; text scales to fit; thin scroll if needed */
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
        #summary-stats .ebay2-summary-badge-row > .badge {
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 2 - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2 ebay2-toolbar-row">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All E Stock</option>
                        <option value="zero">0 E Stock</option>
                        <option value="more" selected>E Stock &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
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

                    <select id="gpft-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50plus">Above 50%</option>
                    </select>
                    <select id="cvr-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">All CVR%</option>
                        <option value="0-0">0%</option>
                        <option value="0-3">0-3%</option>
                        <option value="3-7">3-7%</option>
                        <option value="7-13">7-13%</option>
                        <option value="13plus">13%+</option>
                    </select>

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

                    <select id="temu-price-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="Filter rows by Temu Price color (vs eBay Prc × 0.90)">
                        <option value="all">Temu vs Prc</option>
                        <option value="red">🔴 Red (Temu &gt; Prc × 0.90)</option>
                        <option value="yellow">🟡 Yellow (Temu = Prc × 0.90)</option>
                        <option value="green">🟢 Green (Temu &lt; Prc × 0.90)</option>
                    </select>

                    <!-- DIL Filter -->
                    <div class="manual-dropdown-container pricing-filter-item" id="dil-filter-wrapper">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.66%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.66-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
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
                    <button id="ebay2-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    <button type="button" class="btn btn-sm btn-success pricing-filter-item" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = row.percentage or 0.85 for eBay2) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + eBay2 Ship) / margin on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S PRC'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + eBay2 Ship) / margin for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + eBay2 Ship) / (margin − Target GPFT%/100) on every selected row">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than the eBay2 take-home margin (typically < 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP + eBay2 Ship) / (margin − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (E Stock &gt; 0)</h6>
                    <div class="ebay2-summary-badge-row">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge" data-filter="zero" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: <span id="zero-sold-count">0</span></span>
                        <span class="badge fs-6 p-2 sold-filter-badge" data-filter="sold" style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;" title="Click to filter sold items">> 0 Sold: <span id="more-sold-count">0</span></span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2 d-none" id="total-pft-amt-badge" style="color: black; font-weight: bold;" aria-hidden="true">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Sales: $0</span>
                        {{-- S Qty: L30 units from ebay2_order_items.quantity (period='l30').
                             Same source the /all-marketplace-master Qty column for the EbayTwo row uses,
                             so this page agrees with the master page and with the eBay 1 tabulator's S Qty
                             badge. Static — page filters do not narrow it. --}}
                        <span class="badge fs-6 p-2" id="qty-sold-badge"
                              style="background-color: #6f42c1; color: white; font-weight: bold;"
                              title="L30 units sold (Σ ebay2_order_items.quantity for period='l30'). Same value /all-marketplace-master shows in the EbayTwo row's Qty cell.">S Qty: {{ number_format((int) ($ordersL30TotalQty ?? 0)) }}</span>
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge" style="color: white; font-weight: bold;">GROI: 0%</span>

                        <!-- eBay Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge"
                              style="color: white; font-weight: bold;"
                              title="CVR = (S Qty / Σ Views) × 100. Numerator is the orders-API L30 units (same value the S Qty badge shows). Denominator is the sum of 'views' across rows with E Stock > 0.">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none" id="total-inv-badge" style="color: black; font-weight: bold;" aria-hidden="true">E Stock: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="ebay2-missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: not listed (no eBay item id), REQ, INV &gt; 0 (Missing L) — same as /map-issues">Missing L: 0</span>
                        <span class="badge fs-6 p-2" id="ebay2-nmap-count-badge" style="color: white; font-weight: bold; cursor: pointer; background-color: #a71d2a;" title="Click to filter: N Map rows (same as MAP column)">N Map: 0</span>
                        
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="ebay2-discount-type-block" class="d-flex align-items-center gap-2">
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
                            <i class="fa fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="ebay2-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e7eb;">
                        <div style="flex: 1; position: relative;">
                            <i class="fa fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px;"></i>
                            <input type="text" id="sku-search" class="form-control form-control-sm" style="padding-left: 32px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;" placeholder="Search by SKU...">
                        </div>
                        <div style="min-width: 200px; position: relative;">
                            <i class="fa fa-sitemap" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px;"></i>
                            <input type="text" id="parent-search" class="form-control form-control-sm" style="padding-left: 32px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;" placeholder="Search Parent...">
                        </div>
                        <span id="custom-pagination-counter" style="font-size: 13px; color: #555; white-space: nowrap; margin-left: 16px;"></span>
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay2-table" style="flex: 1;"></div>
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
                        <i class="fa fa-shopping-cart"></i> eBay2 Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id" required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price" step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping" name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link" placeholder="https://ebay.com/itm/...">
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
                                        <input type="text" class="form-control" id="addCompTitle" name="product_title" placeholder="Product title">
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
                        <select id="sku-chart-days-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                            <option value="7">Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                        </select>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-info" style="display: none;">
                        No historical data available for this SKU. Data will appear after running the metrics collection command.
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
                    <div id="export-columns-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
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
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv" required>
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

    <!-- Edit Links Modal -->
    <div class="modal fade" id="ebay2EditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="ebay2EditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="ebay2SellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="ebay2BuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ebay2SaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

    @section('script-bottom')
    <script>
        // Cache bust: v2.1 - OPEN BOX items now included with base SKU lookup
        /** Stored in DB table channel_tabulator_column_settings (shared for all users). */
        const TABULATOR_COLUMN_CHANNEL = 'ebay2_tabulator';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        /** L30 units sold from ebay2_orders (period='l30'). Same value rendered into the
         *  S Qty badge and the eBay 2 row's Qty cell on /all-marketplace-master. Used by
         *  the CVR formula so the page CVR is computed against orders-API ground truth
         *  instead of the laggier ebay_2_metrics.ebay_l30 sum. */
        const ORDERS_L30_TOTAL_QTY = {{ (int) ($ordersL30TotalQty ?? 0) }};
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false;
        let selectedSkus = new Set(); // Track selected SKUs across all pages
        /** Shared with /ebay2/campaign-ads SBID Rule (ebay_sbid_rules.key = ebay2). */
        let currentSbidRule = { l7_views_threshold: 70, bands: [] };

        function resolveSbidBandBid(band, ctx) {
            const color = band.color || '#333';
            const sub = band.sub;
            if (sub && sub.metric && Array.isArray(sub.bands) && sub.bands.length) {
                const val = parseFloat(ctx[sub.metric]) || 0;
                for (let j = 0; j < sub.bands.length; j++) {
                    if (val <= parseFloat(sub.bands[j].max)) {
                        return { bid: parseFloat(sub.bands[j].bid), color: color, skip: false };
                    }
                }
                const ls = sub.bands[sub.bands.length - 1];
                return { bid: parseFloat(ls.bid), color: color, skip: false };
            }
            return { bid: parseFloat(band.bid), color: color, skip: false };
        }

        function getSbidFromRule(scvr, rowData) {
            const s = parseFloat(scvr);
            const safeScvr = (!isFinite(s) || s < 0) ? 0 : s;
            const bands = currentSbidRule.bands || [];
            const ctx = {
                scvr: safeScvr,
                ebay_price: parseFloat(rowData['eBay Price']) || 0,
                ebay_l30: parseFloat(rowData['eBay L30']) || 0,
                views: parseFloat(rowData.views) || 0,
            };
            for (let i = 0; i < bands.length; i++) {
                if (safeScvr <= parseFloat(bands[i].scvr_max)) {
                    return resolveSbidBandBid(bands[i], ctx);
                }
            }
            const last = bands[bands.length - 1] || { bid: 2.1, color: '#e83e8c' };
            return resolveSbidBandBid(last, ctx);
        }

        function getCombinedSbid(rowData) {
            const l7 = parseFloat(rowData.l7_views) || 0;
            const esBidRaw = parseFloat(rowData.ca_suggested_bid);
            const sold = parseFloat(rowData['eBay L30']) || 0;
            const views = parseFloat(rowData.views) || 0;
            const scvr = views > 0 ? (sold / views) * 100 : 0;
            const threshold = parseFloat(currentSbidRule.l7_views_threshold);
            const thr = isFinite(threshold) ? threshold : 70;

            if (l7 < thr) {
                if (!isFinite(esBidRaw) || esBidRaw <= 0) {
                    return { bid: 0, color: '#6c757d', skip: true };
                }
                return { bid: esBidRaw, color: '#0dcaf0', skip: false };
            }
            return getSbidFromRule(scvr, rowData);
        }

        function loadSbidRule() {
            $.ajax({
                url: '/ebay2/campaign-ads/rule',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    currentSbidRule = data || { l7_views_threshold: 70, bands: [] };
                    if (currentSbidRule.l7_views_threshold == null) currentSbidRule.l7_views_threshold = 70;
                    if (!Array.isArray(currentSbidRule.bands)) currentSbidRule.bands = [];
                    if (table) table.redraw(true);
                }
            });
        }

        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingFilterActive = false;
        let mapFilterActive = false;
        let nmapFilterActive = false;

        function rowEbay2StockQty(data) {
            return parseFloat(data['E Stock'] || 0) || 0;
        }
        function isEbay2TabulatorParentRowForMap(data) {
            if (!data) return false;
            if (data.is_parent_summary === true) return true;
            const p = data.Parent;
            return !!(p && String(p).toUpperCase().startsWith('PARENT'));
        }
        /** N Map — same rule as /map-issues (listed, REQ, INV>0, E Stock>0, outside tolerance). */
        function isEbay2TabulatorNMapRow(data) {
            if (isEbay2TabulatorParentRowForMap(data)) return false;
            const itemId = data['eBay_item_id'];
            if (!itemId || String(itemId).trim() === '') return false;
            const inv = parseFloat(data['INV']) || 0;
            if (inv <= 0) return false;
            if (String(data.nr_req || 'REQ').toUpperCase() !== 'REQ') return false;
            const ebayStock = rowEbay2StockQty(data);
            if (ebayStock <= 0) return false; // same as /map-issues: both sides need stock
            const diff = Math.abs(inv - ebayStock);
            if (inv * 0.03 < 3) {
                return diff > 3;
            }
            return Math.round((diff / inv) * 100) > 3;
        }
        function isEbay2TabulatorMapRow(data) {
            if (isEbay2TabulatorParentRowForMap(data)) return false;
            const itemId = data['eBay_item_id'];
            if (!itemId || String(itemId).trim() === '') return false;
            const inv = parseFloat(data['INV']) || 0;
            if (inv <= 0) return false;
            if (String(data.nr_req || 'REQ').toUpperCase() !== 'REQ') return false;
            const ebayStock = rowEbay2StockQty(data);
            if (ebayStock <= 0) return false;
            const diff = Math.abs(inv - ebayStock);
            if (inv * 0.03 < 3) {
                return diff <= 3;
            }
            return Math.round((diff / inv) * 100) <= 3;
        }
        /** Missing L — same rule as /map-issues: not listed (no item id), REQ, INV > 0, non-parent. */
        function isEbay2MissingL(data) {
            if (isEbay2TabulatorParentRowForMap(data)) return false;
            const itemId = data['eBay_item_id'];
            const notListed = (!itemId || String(itemId).trim() === '');
            if (!notListed) return false;
            if (String(data.nr_req || 'REQ').toUpperCase() !== 'REQ') return false;
            const inv = parseFloat(data['INV']) || 0;
            return inv > 0;
        }
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
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

        // SKU-specific chart
        function initSkuMetricsChart() {
            const ctx = document.getElementById('skuMetricsChart').getContext('2d');
            skuMetricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
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

            // ---- Edit Links (Buyer / Seller) ----
            function ebay2LinksNotify(msg, type) {
                type = type || 'info';
                var bg = type === 'success' ? 'bg-success' : (type === 'error' || type === 'danger' ? 'bg-danger' : 'bg-info');
                var $c = $('.toast-container');
                if (!$c.length) {
                    $c = $('<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;"></div>').appendTo('body');
                }
                var $t = $('<div class="toast align-items-center text-white ' + bg + ' border-0" role="alert"><div class="d-flex"><div class="toast-body">' + msg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
                $c.append($t);
                var bsT = new bootstrap.Toast($t[0]);
                bsT.show();
                setTimeout(function() { $t.remove(); }, 5000);
            }

            let ebay2EditLinksRow = null;
            window.openEbay2EditLinksModal = function(row) {
                ebay2EditLinksRow = row;
                const d = row.getData();
                $('#ebay2EditLinksSku').text(d['(Child) sku'] || '');
                $('#ebay2SellerLinkInput').val(d['S Link'] || '');
                $('#ebay2BuyerLinkInput').val(d['B Link'] || '');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay2EditLinksModal')).show();
            };

            $('#ebay2SaveLinksBtn').on('click', function() {
                if (!ebay2EditLinksRow) return;
                const sku = ebay2EditLinksRow.getData()['(Child) sku'];
                const sellerLink = $('#ebay2SellerLinkInput').val().trim();
                const buyerLink = $('#ebay2BuyerLinkInput').val().trim();
                const $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');
                $.ajax({
                    url: '/ebay2/save-links',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        seller_link: sellerLink,
                        buyer_link: buyerLink
                    },
                    success: function(res) {
                        if (res && res.success) {
                            ebay2EditLinksRow.update({
                                'S Link': res.seller_link || '',
                                'B Link': res.buyer_link || ''
                            }).then(function() {
                                ebay2EditLinksRow.reformat();
                            }).catch(function() {
                                ebay2EditLinksRow.reformat();
                            });
                            ebay2LinksNotify('Links saved successfully', 'success');
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay2EditLinksModal')).hide();
                        } else {
                            ebay2LinksNotify((res && res.message) || 'Failed to save links', 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                        ebay2LinksNotify(msg, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Save');
                    }
                });
            });

            // Initialize SKU-specific chart only
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

            function syncEbay2DiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#ebay2-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('eBay price:');
                    $inp.attr('placeholder', 'Each row — click Apply');
                    $inp.prop('disabled', true);
                    $inp.removeAttr('max');
                    $inp.val('');
                } else {
                    $('#ebay2-discount-type-block').removeClass('d-none');
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

            function syncEbay2PriceModeUi() {
                const $btn = $('#ebay2-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncEbay2DiscountBarForMode();
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

            $('#ebay2-price-mode-btn').on('click', function() {
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
                syncEbay2PriceModeUi();
            });

            // Select all checkbox handler (matching Amazon approach)
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
                
                // Add or remove all filtered SKUs from the selected set
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

            // Clear SPRICE button handler
            $('#clear-sprice-selected-btn').on('click', function() {
                if (confirm('Are you sure you want to clear SPRICE for selected SKUs?')) {
                    clearSpriceForSelected();
                }
            });

            /*
             * Target ROI% / Target GPFT% bulk apply (eBay2, margin = row.percentage or 0.85)
             * -----------------------------------------------------------------------------
             * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
             * target. eBay2's server-side SGPFT formula (EbayTwoController::saveSpriceToDatabase
             * line 1165) includes shipping:
             *     SGPFT% = ((sprice * margin − ship − lp) / sprice) * 100
             *     SROI%  = ((sprice * margin − ship − lp) / lp)     * 100   (same shape used elsewhere)
             *   → sprice = (lp * (1 + ROI%/100)  + ship) / margin
             *   → sprice = (lp + ship) / (margin − GPFT%/100)
             * Each save goes through the existing saveSpriceWithRetry() Promise pipeline so
             * SPRICE_STATUS (processing → saved / error) and the server-recomputed
             * SGPFT / SPFT / SROI values stay in sync exactly like Decrease / Increase / Same Price.
             * Rounding is plain 2-decimal — no .99 / .49 retail snapping — because snapping
             * would shift the achieved SROI / SGPFT off the user-typed target.
             * Ship field is `ebay2_ship` (per the table's column definition + the
             * EbayTwoController saveSpriceToDatabase shipping lookup at line 1161).
             */
            function ebay2ApplyTargetBackSolve(computeFn, labelPrefix) {
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU first (turn on Price % mode to reveal checkboxes)', 'error');
                    return;
                }

                const allData     = table.getData('all');
                const targetSkus  = new Set(selectedSkus);
                const tasks       = [];
                let skippedNoLp   = 0;
                const skippedHigh = [];

                allData.forEach(row => {
                    if (row.Parent && String(row.Parent).startsWith('PARENT')) return;
                    const sku = row['(Child) sku'];
                    if (!sku || !targetSkus.has(sku)) return;

                    const lp = parseFloat(row['LP_productmaster']) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const ship = parseFloat(row['ebay2_ship']) || 0;
                    const marginRaw = parseFloat(row['percentage']);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;

                    const computed = computeFn(lp, ship, margin);
                    if (computed == null) { skippedHigh.push(sku); return; }
                    const newSprice = +computed.toFixed(2);
                    if (!isFinite(newSprice) || newSprice <= 0) return;

                    const tableRow = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                    if (!tableRow) return;
                    tableRow.update({ SPRICE: newSprice, SPRICE_STATUS: 'processing' });

                    tasks.push({ sku: sku, newSprice: newSprice, tableRow: tableRow });
                });

                if (tasks.length === 0) {
                    if (skippedHigh.length > 0) {
                        showToast(`${labelPrefix} too high — must be less than each row's take-home margin (typically < 85%).`, 'error');
                    } else {
                        showToast('No selected rows have a usable LP > 0', 'warning');
                    }
                    return;
                }

                let okCount = 0;
                let errCount = 0;
                const total = tasks.length;

                tasks.forEach(t => {
                    saveSpriceWithRetry(t.sku, t.newSprice, t.tableRow)
                        .then(() => {
                            okCount++;
                            if (okCount + errCount === total) {
                                let note = '';
                                if (skippedNoLp > 0)       note += ` (${skippedNoLp} skipped — no LP)`;
                                if (skippedHigh.length)    note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
                                if (errCount === 0) {
                                    showToast(`${labelPrefix} applied to ${okCount} SKU(s)${note}`, 'success');
                                } else {
                                    showToast(`${labelPrefix} applied to ${okCount} SKU(s), ${errCount} failed${note}`, 'error');
                                }
                            }
                        })
                        .catch(() => {
                            errCount++;
                            if (okCount + errCount === total) {
                                let note = '';
                                if (skippedNoLp > 0)       note += ` (${skippedNoLp} skipped — no LP)`;
                                if (skippedHigh.length)    note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
                                showToast(`${labelPrefix} applied to ${okCount} SKU(s), ${errCount} failed${note}`, 'error');
                            }
                        });
                });
            }

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

                const roiMultiplier = 1 + (targetRoiPct / 100);
                ebay2ApplyTargetBackSolve(function (lp, ship, margin) {
                    return (lp * roiMultiplier + ship) / margin;
                }, `Target ROI ${targetRoiPct}%`);
            });

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

                const targetFraction = targetGpftPct / 100;
                ebay2ApplyTargetBackSolve(function (lp, ship, margin) {
                    const denom = margin - targetFraction;
                    if (denom <= 0) return null; // signals "target ≥ margin" skip
                    return (lp + ship) / denom;
                }, `Target GPFT ${targetGpftPct}%`);
            });

            $('#target-roi-input').on('keypress', function (e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });
            $('#target-gpft-input').on('keypress', function (e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Badge filter click handlers - Work together with other filters
            $('.sold-filter-badge[data-filter="zero"], #zero-sold-count-badge').on('click', function() {
                zeroSoldFilterActive = !zeroSoldFilterActive;
                moreSoldFilterActive = false;
                missingFilterActive = false;
                mapFilterActive = false;
                nmapFilterActive = false;
                applyFilters();
            });

            $('.sold-filter-badge[data-filter="sold"]').on('click', function() {
                moreSoldFilterActive = !moreSoldFilterActive;
                zeroSoldFilterActive = false;
                missingFilterActive = false;
                mapFilterActive = false;
                nmapFilterActive = false;
                applyFilters();
            });

            $('#ebay2-missing-count-badge').on('click', function() {
                missingFilterActive = !missingFilterActive;
                if (missingFilterActive) {
                    mapFilterActive = false;
                    nmapFilterActive = false;
                }
                zeroSoldFilterActive = false;
                moreSoldFilterActive = false;
                applyFilters();
            });
            $('#ebay2-map-count-badge').on('click', function() {
                mapFilterActive = !mapFilterActive;
                if (mapFilterActive) {
                    missingFilterActive = false;
                    nmapFilterActive = false;
                }
                zeroSoldFilterActive = false;
                moreSoldFilterActive = false;
                applyFilters();
            });
            $('#ebay2-nmap-count-badge').on('click', function() {
                nmapFilterActive = !nmapFilterActive;
                if (nmapFilterActive) {
                    missingFilterActive = false;
                    mapFilterActive = false;
                }
                zeroSoldFilterActive = false;
                moreSoldFilterActive = false;
                applyFilters();
            });

            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first', 'error');
                    return;
                }

                let clearedCount = 0;

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows("(Child) sku", "=", sku);
                    
                    if (rows.length > 0) {
                        const row = rows[0];
                        row.update({
                            SPRICE: 0,
                            SGPFT: 0,
                            SPFT: 0,
                            SROI: 0
                        });
                        
                        row.reformat();
                        saveSpriceWithRetry(sku, 0, row);
                        clearedCount++;
                    }
                });

                showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
            }

            // Apply All button handler
            $(document).on('click', '#apply-all-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.applyAllSelectedPrices();
            });

            // Chart days filter
            $('#chart-days-filter').on('change', function() {
                const days = $(this).val();
                loadMetricsData(days);
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
                $('#discount-input-container').toggle(count > 0);
            }

            // Update select all checkbox state (matching Amazon approach)
            function updateSelectAllCheckbox() {
                if (!table) return;
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
                
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
            const BACKGROUND_RETRY_KEY = 'ebay2_failed_price_pushes';
            
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
                                    refreshEbay2TableData();
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
                        row.update({ SPRICE_STATUS: 'processing' });
                    }
                    
                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            sku: sku,
                            sprice: sprice,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            // Update calculated fields instantly
                            if (row) {
                                row.update({
                                    SPRICE: sprice,
                                    SPFT: response.spft_percent,
                                    SROI: response.sroi_percent,
                                    SGPFT: response.sgpft_percent,
                                    SPRICE_STATUS: 'saved'
                                });
                                // Re-render the row so the Accept button's data-price
                                // reflects the NEW SPRICE (otherwise push uses the old value).
                                row.reformat();
                            }
                            resolve(response);
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                            console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`, errorMsg);
                            
                            // Only retry once (retryCount < 1)
                            if (retryCount < 1) {
                                console.log(`Retrying SKU ${sku} in 2 seconds...`);
                                setTimeout(() => {
                                    saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                        .then(resolve)
                                        .catch(reject);
                                }, 2000);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                // Update status to error
                                if (row) {
                                    row.update({ SPRICE_STATUS: 'error' });
                                }
                                reject({ error: true, xhr: xhr });
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
                            url: '/push-ebay2-price',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: { sku: sku, price: price }
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
                    $btn.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;'); // Yellow text, no background
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'processing';
                        row.update(rowData);
                    }
                }

                try {
                    const response = await $.ajax({
                        url: '/push-ebay2-price',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: { sku: sku, price: price }
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
                        $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;'); // Green text, no background
                    }
                    
                    if (!isBackgroundRetry) {
                        showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                    }
                    
                    return true;
                } catch (xhr) {
                    const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to apply price';
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
                            $btn.attr('style', 'border: none; background: none; color: #ff6b00; padding: 0;'); // Orange text for restriction
                            $btn.attr('title', 'Account restricted - cannot update price');
                        }
                        
                        showToast(`Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`, 'error');
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
                            $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;'); // Red text, no background
                        }
                        
                        // Save for background retry (only if not already a background retry)
                        saveFailedSkuForRetry(sku, price, 0);
                        showToast(`Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`, 'error');
                        
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
                            url: '/push-ebay2-price',
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
                                    const errorMsg = response.errors[0].message || 'Unknown error';
                                    const errorCode = response.errors[0].code || '';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg, 'Code:', errorCode);
                                    
                                    if (attempt < maxRetries) {
                                        console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({ error: true, response: response });
                                    }
                                } else {
                                    console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                                    resolve({ success: true, response: response });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                
                                if (attempt < maxRetries) {
                                    console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                    setTimeout(attemptApply, delay);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku}`);
                                    reject({ error: true, xhr: xhr });
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
                            skusToProcess.push({ sku: sku, price: sprice });
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
                            showToast(`Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`, 'success');
                            
                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.html(originalHtml);
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast(`Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                        }
                        return;
                    }
                    
                    const { sku, price } = skusToProcess[currentIndex];
                    
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
                                $btnInCell.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                            }
                        }
                    }
                    
                    // First save to database (like SPRICE edit does), then push to eBay
                    console.log(`Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`);
                    
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
                                console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse.error);
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
                                            $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
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
                                            const $btnInCell = $cellElement.find('.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html('<i class="fa-solid fa-check-double"></i>');
                                                $btnInCell.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
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
                                            const $btnInCell = $cellElement.find('.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                                $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
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
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr.responseJSON || xhr.responseText);
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
                                        $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
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

            // Apply discount to selected SKUs
            function applyDiscount() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                    return;
                }

                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                const discountValue = parseFloat($('#discount-percentage-input').val());
                const discountType = $('#discount-type-select').val();
                if (!samePriceModeActive) {
                    if (isNaN(discountValue) || discountValue <= 0) {
                        showToast('Please enter a valid discount value', 'error');
                        return;
                    }
                }

                const allData = table.getData('all');
                let updatedCount = 0;
                let errorCount = 0;
                const totalSkus = selectedSkus.size;
                const isIncrease = increaseModeActive;
                const appliedAsSamePrice = samePriceModeActive;

                allData.forEach(row => {
                    const isParent = row.Parent && row.Parent.startsWith('PARENT');
                    if (isParent) return;

                    const sku = row['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        let newSPrice;
                        if (samePriceModeActive) {
                            const p = parseFloat(row['eBay Price']);
                            newSPrice = isNaN(p) ? 0 : p;
                        } else {
                            const currentPrice = parseFloat(row['eBay Price']) || 0;
                            if (currentPrice <= 0) return;

                            if (discountType === 'percentage') {
                                if (isIncrease) {
                                    newSPrice = currentPrice * (1 + discountValue / 100);
                                } else {
                                    newSPrice = currentPrice * (1 - discountValue / 100);
                                }
                            } else {
                                if (isIncrease) {
                                    newSPrice = currentPrice + discountValue;
                                } else {
                                    newSPrice = currentPrice - discountValue;
                                }
                            }
                            newSPrice = Math.max(0.01, newSPrice);
                        }

                        const originalSPrice = parseFloat(row['SPRICE']) || 0;

                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['(Child) sku'] === sku;
                        });

                        if (tableRow) {
                            tableRow.update({
                                SPRICE: newSPrice,
                                SPRICE_STATUS: 'processing'
                            });
                        }

                        saveSpriceWithRetry(sku, newSPrice, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(
                                            appliedAsSamePrice
                                                ? `SPRICE set to eBay price for ${updatedCount} SKU(s)`
                                                : `Discount applied to ${updatedCount} SKU(s)`,
                                            'success'
                                        );
                                    } else {
                                        showToast(
                                            appliedAsSamePrice
                                                ? `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed`
                                                : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                            'error'
                                        );
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ SPRICE: originalSPrice });
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(
                                        appliedAsSamePrice
                                            ? `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed`
                                            : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                        'error'
                                    );
                                }
                            });
                    }
                });
            }

            // Event delegation for eye button clicks (add to SKU column formatter)
            let allTableData = []; // Store all unfiltered data

            function ebay2EscHtmlAttr(val) {
                if (val == null || val === '') return '';
                return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function ebay2UpdateForecastNrp(data, onSuccess, onFail) {
                onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
                onFail = typeof onFail === 'function' ? onFail : function() {};
                $.post('{{ route("update.forecast.data") }}', {
                    sku: data.sku,
                    parent: data.parent != null ? String(data.parent) : '',
                    column: 'NR',
                    value: data.value,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res.success) {
                        onSuccess();
                    } else {
                        console.warn('NRP not saved:', res.message);
                        onFail();
                    }
                }).fail(function(err) {
                    console.error('NRP save failed:', err);
                    if (typeof showToast === 'function') showToast('Error saving NRP.', 'error');
                    onFail();
                });
            }

            let ebayMpImagePreviewHideTimer = null;
            let ebayMpImagePreviewEl = null;
            function ebayMpRemoveImagePreview() {
                if (ebayMpImagePreviewHideTimer) {
                    clearTimeout(ebayMpImagePreviewHideTimer);
                    ebayMpImagePreviewHideTimer = null;
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
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
                document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
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
            
            table = new Tabulator("#ebay2-table", {
                ajaxURL: "/ebay2-data",
                ajaxResponse: function(url, params, response) {
                    // Extract the data array from the response object
                    allTableData = response.data || []; // Store unfiltered data
                    console.log('API Response - Total rows:', allTableData.length);
                    
                    // Calculate total L30 for verification
                    let totalL30 = 0;
                    let parentCount = 0;
                    allTableData.forEach(row => {
                        const sku = row['(Child) sku'] || '';
                        if (sku.toUpperCase().includes('PARENT')) {
                            parentCount++;
                        } else {
                            totalL30 += parseFloat(row['eBay L30'] || 0);
                        }
                    });
                    console.log('Total eBay L30 from API:', totalL30, '(excluding', parentCount, 'PARENT rows)');
                    
                    return response.data || [];
                },
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    var start = currentRow;
                    var end = Math.min(currentRow + pageSize - 1, totalRows);
                    var text = "Showing " + start + "-" + end + " of " + totalRows + " rows";
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
                    column: "SCVR",
                    dir: "asc"
                }],
                rowFormatter: function(row) {
                    if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                        row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                    }
                },
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: false
                    },

                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        frozen: true,
                        width: 50,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const isSelected = selectedSkus.has(sku);
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                        }
                    },

                    {
                        title: "Image",
                        field: "image_path",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value) {
                                const u = String(value).replace(/"/g, '&quot;');
                                return '<img src="' + u + '" data-full="' + u + '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                            }
                            return '';
                        },
                        cellMouseOver: function(e, cell) {
                            const img = cell.getElement().querySelector('.hover-thumb');
                            if (!img) return;
                            ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
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
                            if (related && typeof related.closest === 'function' && related.closest('#image-hover-preview')) {
                                ebayMpCancelImagePreviewHide();
                                return;
                            }
                            ebayMpScheduleImagePreviewHide();
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
                        // Full SKU tooltip on hover — explicit so it works even when the SKU text
                        // is truncated by the narrower column width.
                        tooltip: function(e, cell) {
                            return cell.getValue() || '';
                        },
                        frozen: true,
                        width: 175,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();
                            
                            // Ratings display with star icon (like FBA/Amazon format)
                            const ratingDisplay = (rowData.rating && rowData.rating > 0) 
                                ? ` <i class="fa fa-star" style="color: orange;"></i> ${rowData.rating}` 
                                : '';
                            
                            // Truncate the SKU text with ellipsis when it exceeds the narrower column
                            // width; full text remains visible via the column's tooltip on hover.
                            let html = `<span style="display: inline-block; max-width: 105px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;" title="${sku}">${sku}${ratingDisplay}</span>`;
                            
                            // Copy button
                            html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 6px; font-size: 14px; vertical-align: middle;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                            
                            // Metrics chart button
                            html += `<button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 4px; vertical-align: middle;">
                                        <i class="fa fa-info-circle"></i>
                                     </button>`;
                            
                            return html;
                        }
                    },
                    // {
                    //     title: "Ratings",
                    //     field: "rating",
                    //     hozAlign: "center",
                    //     editor: "input",
                    //     tooltip: "Enter rating between 0 and 5",
                    //     width: 80
                    // },
                    {
                        title: "Links",
                        field: "links_column",
                        frozen: true,
                        width: 55,
                        visible: true,
                        hozAlign: "center",
                        headerSort: false,
                        tooltip: "Double-click to add / edit links",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['B Link'] || '';
                            const sellerLink = rowData['S Link'] || '';

                            let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                            if (sellerLink) {
                                html += `<a href="${sellerLink}" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>`;
                            }
                            if (buyerLink) {
                                html += `<a href="${buyerLink}" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>`;
                            }
                            if (!sellerLink && !buyerLink) {
                                html += '<span class="text-muted" style="font-size:12px;">-</span>';
                            }
                            html += '</div>';
                            return html;
                        },
                        cellDblClick: function(e, cell) {
                            openEbay2EditLinksModal(cell.getRow());
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
                        field: "E Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;
                            
                            if (INV === 0) return '<span style="color: #a00211; font-weight: 600;">0%</span>'; // red for 0%
                            
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
                        cellClick: function(e, cell) { e.stopPropagation(); },
                        width: 70
                    },
                    {
                        title: "E L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 30,
                        sorter: "number"
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
                        field: "E Stock",
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
                        headerTooltip: "M when not listed (no eBay item id), REQ, INV > 0 — same as /map-issues.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (isEbay2MissingL(rowData)) {
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
                        headerTooltip: "MP when within /map-issues tolerance (3 units, or rounded 3% for INV ≥ 100); N MP otherwise (listed rows with E Stock > 0).",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT')) {
                                return '';
                            }
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '';
                            }
                            const ebayStock = parseFloat(rowData['E Stock']) || 0;
                            const inv = parseFloat(rowData['INV']) || 0;
                            // Same as /map-issues: both sides must have stock to be Map / N Map.
                            if (inv > 0 && ebayStock > 0) {
                                const diff = Math.abs(inv - ebayStock);
                                const isNotMap = (inv * 0.03 < 3) ? (diff > 3) : (Math.round((diff / inv) * 100) > 3);
                                if (!isNotMap) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                }
                                const signedDiff = inv - ebayStock;
                                const sign = signedDiff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${signedDiff})</span>`;
                            }
                            return '';
                        }
                    },

                    
                    // {
                    //     title: "eBay L60",
                    //     field: "eBay L60",
                    //     hozAlign: "center",
                    //     width: 100,
                    //     visible: false
                    // },
                    {
                        title: "CVR 60",
                        field: "CVR_60",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue()) || 0;
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                        },
                        width: 60,
                        visible: false
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
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
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
                                arrowHtml = ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                            }
                            const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            const sku = rowData['(Child) sku'] || '';
                            const dotBtn = (sku && !isParent) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" title="View CVR chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>` : '';
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml} ${dotBtn}`.trim();
                        },
                        width: 65
                    },

                    {
                        title: "View",
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
                        title: "L7 View",
                        field: "l7_views",
                        hozAlign: "center",
                        sorter: "number",
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
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith('PARENT');
                            
                            // Don't show dropdown for parent rows
                            // if (isParent) {
                            //     return '';
                            // }
                            
                            // Get value and handle null/undefined/empty cases
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value.trim() === '') {
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
                            if (value === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }
                            return `$${value.toFixed(2)}`;
                        },
                        width: 70
                    },

                    {
                        title: "Temu Price",
                        field: "Temu Price",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Temu Price = base_price + 2.99 if ≤ $26.99, else base_price.\nBackground: vs (eBay Prc × 0.90) — Red > threshold, Yellow ≈, Green < threshold.",
                        formatter: function(cell) {
                            const el = cell.getElement();
                            const value = parseFloat(cell.getValue() || 0);
                            // Always reset (Tabulator reuses cell elements between data reloads).
                            el.style.backgroundColor = '';
                            el.style.color = '';
                            el.style.fontWeight = '';

                            if (!value) {
                                return `<span style="color: #adb5bd;">—</span>`;
                            }
                            const ebayPrice = parseFloat(cell.getRow().getData()['eBay Price'] || 0);
                            if (ebayPrice > 0) {
                                const threshold = Math.round(ebayPrice * 0.90 * 100) / 100; // round to cents
                                const diff = Math.round((value - threshold) * 100) / 100;
                                if (diff > 0) {
                                    // Temu Price > 90% of Prc → red
                                    el.style.backgroundColor = '#f8d7da';
                                    el.style.color = '#721c24';
                                } else if (diff === 0) {
                                    // Temu Price ≈ 90% of Prc (within 1¢) → yellow
                                    el.style.backgroundColor = '#fff3cd';
                                    el.style.color = '#856404';
                                } else {
                                    // Temu Price < 90% of Prc → green
                                    el.style.backgroundColor = '#d4edda';
                                    el.style.color = '#155724';
                                }
                                el.style.fontWeight = '600';
                            }
                            return `$${value.toFixed(2)}`;
                        },
                        width: 80
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

                            let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                            
                            // Show lowest price OUTSIDE modal
                            if (lmpPrice) {
                                const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                                const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                                html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
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
                            const ebay2Price = parseFloat(rowData['eBay Price']) || 0;
                            const sprice = parseFloat(value) || 0;
                            
                            if (!value) return '';
                            
                            // Always show SPRICE when it has a value — even if it equals the eBay price.
                            const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                            
                            // If using default eBay Price (not custom), show in blue
                            if (hasCustomSprice === false) {
                                return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                            }
                            
                            return formattedValue;
                        },
                        width: 80
                    },
                    {
                        title: "Temu Price",
                        // Pseudo-field — same value as `Temu Price` but a unique key so this column's
                        // visibility/filter/save state stays independent from the first Temu Price column.
                        field: "Temu Price S",
                        hozAlign: "center",
                        headerTooltip: "Temu Price vs (S PRC × 0.90) — Red > threshold, Yellow ≈, Green < threshold.",
                        sorter: function(a, b, aRow, bRow) {
                            const aVal = parseFloat(aRow.getData()['Temu Price']) || 0;
                            const bVal = parseFloat(bRow.getData()['Temu Price']) || 0;
                            return aVal - bVal;
                        },
                        formatter: function(cell) {
                            const el = cell.getElement();
                            el.style.backgroundColor = '';
                            el.style.color = '';
                            el.style.fontWeight = '';

                            const rowData = cell.getRow().getData();
                            const value = parseFloat(rowData['Temu Price']) || 0;
                            if (!value) {
                                return `<span style="color: #adb5bd;">—</span>`;
                            }
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            if (sprice > 0) {
                                const threshold = Math.round(sprice * 0.90 * 100) / 100;
                                const diff = Math.round((value - threshold) * 100) / 100;
                                if (diff > 0) {
                                    // Temu Price > 90% of S PRC → red
                                    el.style.backgroundColor = '#f8d7da';
                                    el.style.color = '#721c24';
                                } else if (diff === 0) {
                                    // Temu Price ≈ 90% of S PRC → yellow
                                    el.style.backgroundColor = '#fff3cd';
                                    el.style.color = '#856404';
                                } else {
                                    // Temu Price < 90% of S PRC → green
                                    el.style.backgroundColor = '#d4edda';
                                    el.style.color = '#155724';
                                }
                                el.style.fontWeight = '600';
                            }
                            return `$${value.toFixed(2)}`;
                        },
                        width: 80
                    },
                    {
                        field: "_accept",
                        hozAlign: "center",
                        headerSort: false,
                        width: 60,
                        titleFormatter: function(column) {
                            // Bulk-apply button is kept in the DOM (hidden) so existing #apply-all-btn
                            // click handlers and programmatic triggers continue to work.
                            return `<div style="display: flex; align-items: center; justify-content: center;">
                                <span>Accept</span>
                                <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay" style="display: none; border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
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
                                titleText = 'Price pushed to eBay (Double-click to mark as Applied)';
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
                                titleText = 'Error applying price to eBay2';
                            } else if (status === 'account_restricted') {
                                icon = '<i class="fa-solid fa-ban"></i>';
                                iconColor = '#ff6b00'; // Orange text
                                titleText = 'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
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
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                const currentStatus = $btn.attr('data-status') || '';
                                
                                if (currentStatus === 'pushed') {
                                    const sku = $btn.attr('data-sku') || $btn.data('sku');
                                    $.ajax({
                                        url: '/update-ebay-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        data: { sku: sku, status: 'applied' },
                                        success: function(response) {
                                            if (response.success) {
                                                // Update only this row (no full reload → no horizontal slide)
                                                const row = cell.getRow();
                                                row.update({ SPRICE_STATUS: 'applied' });
                                                row.reformat();
                                                showToast('Status updated to Applied', 'success');
                                            }
                                        }
                                    });
                                }
                                return;
                            }
                            
                            if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                // Read SKU/price from LIVE row data (not the stale data-* HTML attributes),
                                // so a freshly-edited SPRICE is used instead of reverting to the old value.
                                const liveRowData = cell.getRow().getData();
                                const sku = liveRowData['(Child) sku'] || $btn.attr('data-sku');
                                const price = parseFloat(liveRowData.SPRICE) || parseFloat($btn.attr('data-price'));
                                const currentStatus = liveRowData.SPRICE_STATUS || $btn.attr('data-status') || '';
                                
                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('Invalid SKU or price', 'error');
                                    return;
                                }
                                
                                // If status is 'saved' or null, first save SPRICE, then push to eBay
                                if (currentStatus === 'saved' || !currentStatus) {
                                    const row = cell.getRow();
                                    row.update({ SPRICE_STATUS: 'processing' });
                                    
                                    saveSpriceWithRetry(sku, price, row)
                                        .then((response) => {
                                            // After saving, push to eBay
                                            applyPriceWithRetry(sku, price, cell, 0);
                                        })
                                        .catch((error) => {
                                            row.update({ SPRICE_STATUS: 'error' });
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
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
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
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const percent = parseFloat(rowData.SGPFT || 0);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80,
                        visible: false
                    },
                    {
                        title: "SGROI",
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


                    {
                        title: "NRP",
                        field: "nrp",
                        hozAlign: "center",
                        sorter: "string",
                        headerSort: true,
                        width: 56,
                        minWidth: 52,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && String(rowData.Parent).startsWith('PARENT');
                            if (isParent) {
                                return '<span style="color: #999;">-</span>';
                            }
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') {
                                value = rowData.nrp;
                            }
                            if (value === null || value === undefined) {
                                value = '';
                            } else {
                                value = String(value).trim().toUpperCase();
                            }
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') {
                                value = 'REQ';
                            }
                            const sku = String(rowData['(Child) sku'] || '');
                            const parent = rowData.Parent != null ? String(rowData.Parent) : '';
                            let dotColor = '#22c55e';
                            let tip = 'REQ';
                            if (value === 'NR') {
                                dotColor = '#dc3545';
                                tip = '2BDC';
                            } else if (value === 'LATER') {
                                dotColor = '#facc15';
                                tip = 'LATER';
                            }
                            const skuAttr = ebay2EscHtmlAttr(sku);
                            const parentAttr = ebay2EscHtmlAttr(parent);
                            return (
                                '<div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="' +
                                ebay2EscHtmlAttr(tip + ' (click to change)') + '">' +
                                '<span class="nrp-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>' +
                                '<select class="form-select form-select-sm nrp-nr-select position-absolute top-0 start-0 w-100 h-100" ' +
                                'data-sku="' + skuAttr + '" data-parent="' + parentAttr + '" ' +
                                'aria-label="NRP: ' + ebay2EscHtmlAttr(tip) + '">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>REQ</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>2BDC</option>' +
                                '<option value="LATER"' + (value === 'LATER' ? ' selected' : '') + '>LATER</option>' +
                                '</select></div>'
                            );
                        },
                        cellClick: function(e, cell) { e.stopPropagation(); }
                    },
                  
                    // {
                    //     title: "Listed",
                    //     field: "Listed",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },
                    // {
                    //     title: "Live",
                    //     field: "Live",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },

                    // === Campaign-Ads columns (ES BID / C BID / PROMOTE) ===
                    // Same source & formatters as /ebay2/campaign-ads. SKU-wise via listing_id; rows
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
                        title: "S BID",
                        field: "ca_suggested_bid",
                        hozAlign: "center",
                        width: 90,
                        headerTooltip: "Suggested bid: if l7_views < L7 threshold → ES Bid; else SCVR-band lookup (same as /ebay2/campaign-ads).",
                        sorter: function(a, b, aRow, bRow) {
                            return getCombinedSbid(aRow.getData()).bid - getCombinedSbid(bRow.getData()).bid;
                        },
                        formatter: function(cell) {
                            const match = getCombinedSbid(cell.getRow().getData());
                            if (match.skip) {
                                return '<span class="text-muted" title="No SBID — l7_views below threshold and no ES Bid available" style="font-size:11px;">—</span>';
                            }
                            return `<span style="color:${match.color}; font-weight:700;">${match.bid.toFixed(1)}%</span>`;
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
                        headerTooltip: "eBay Promotion eligibility status (from /ebay2/campaign-ads)",
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

            loadSbidRule();

            $(document).on('change', '#ebay2-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.searchRows('(Child) sku', '=', sku);
                const row = rows && rows.length ? rows[0] : null;
                const prevRaw = row ? String(row.getData().nrp ?? '').trim().toUpperCase() : '';
                const prevSelect = (prevRaw === 'NR' || prevRaw === 'LATER') ? prevRaw : 'REQ';
                ebay2UpdateForecastNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {
                        if (row) {
                            row.update({ nrp: newValue }, true);
                            const nrCell = row.getCells().find(function(c) { return c.getField() === 'nrp'; });
                            if (nrCell) nrCell.reformat();
                        }
                        if (typeof showToast === 'function') showToast('NRP saved', 'success');
                    },
                    function() {
                        $el.val(prevSelect);
                    }
                );
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
                row.update({nr_req: value});
                
                // Save to database using listing_ebaytwo endpoint (saves to ebay_two_listing_status table)
                $.ajax({
                    url: '/listing_ebaytwo/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('NR/REQ saved successfully for', sku, 'value:', value);
                            const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ? 'NR updated' : 'Status cleared');
                            showToast('success', message);
                        } else {
                            showToast('error', response.message || 'Failed to save status');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr.responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
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
                            row.update({rating: numValue});
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
                    row.update({ SPRICE_STATUS: 'processing' });
                    
                    saveSpriceWithRetry(data['(Child) sku'], value, row)
                        .then((response) => {
                            showToast('success', 'SPRICE saved successfully');
                        })
                        .catch((error) => {
                            showToast('error', 'Failed to save SPRICE');
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
                const temuPriceFilter = $('#temu-price-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

                table.clearFilter(true);

                // Missing / Map / N Map badges are authoritative (same rows as /map-issues).
                // Skip the "E Stock" inventory filter while one is active, otherwise not-listed
                // Missing L rows (E Stock = 0) get filtered out and the view shows nothing.
                const badgeFilterActive = missingFilterActive || mapFilterActive || nmapFilterActive;

                if (!badgeFilterActive) {
                    if (inventoryFilter === 'zero') {
                        table.addFilter(function(data) {
                            return (parseFloat(data['E Stock'] || 0) || 0) === 0;
                        });
                    } else if (inventoryFilter === 'more') {
                        table.addFilter(function(data) {
                            return (parseFloat(data['E Stock'] || 0) || 0) > 0;
                        });
                    }
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
                        if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                        if (gpftFilter === '50plus') return gpft >= 50;
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

                // CVR trend filter: CVR 60 vs CVR 30
                if (cvrTrendFilter !== 'all') {
                    const cvrTrendTol = 0.1;
                    table.addFilter(function(data) {
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
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                // Temu Price color filter (matches the Temu Price column formatter):
                //   red    → Temu Price > (eBay Price × 0.90)
                //   yellow → Temu Price ≈ (eBay Price × 0.90)   (within 1¢)
                //   green  → Temu Price < (eBay Price × 0.90)
                if (temuPriceFilter === 'red' || temuPriceFilter === 'green' || temuPriceFilter === 'yellow') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT')) return false;
                        const temu = parseFloat(data['Temu Price'] || 0) || 0;
                        const ebay = parseFloat(data['eBay Price'] || 0) || 0;
                        if (temu <= 0 || ebay <= 0) return false;
                        const threshold = Math.round(ebay * 0.90 * 100) / 100;
                        const diff = Math.round((temu - threshold) * 100) / 100;
                        if (temuPriceFilter === 'red')    return diff > 0;
                        if (temuPriceFilter === 'yellow') return diff === 0;
                        return diff < 0; // green
                    });
                }

                // Badge Filters (only E Stock > 0)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = parseFloat(data['E Stock'] || 0) || 0;
                        return ebayL30 === 0 && estock > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = parseFloat(data['E Stock'] || 0) || 0;
                        return ebayL30 > 0 && estock > 0;
                    });
                }

                if (missingFilterActive) {
                    table.addFilter(function(data) {
                        return isEbay2MissingL(data);
                    });
                }
                if (mapFilterActive) {
                    table.addFilter(function(data) {
                        return isEbay2TabulatorMapRow(data);
                    });
                }
                if (nmapFilterActive) {
                    table.addFilter(function(data) {
                        return isEbay2TabulatorNMapRow(data);
                    });
                }

                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const INV = parseFloat(data['INV'] || 0);
                        const OVL30 = parseFloat(data['L30'] || 0);
                        
                        if (INV === 0) return dilFilter === 'red'; // 0% is red
                        
                        // Calculate DIL percentage (matching the column formatter)
                        const dil = (OVL30 / INV) * 100;
                        
                        if (dilFilter === 'red') return dil < 16.66;
                        if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                updateCalcValues();
                updateSummary();
                // Update select all checkbox after filter is applied (matching Amazon approach)
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #el30-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter, #temu-price-filter').on('change', function() {
                applyFilters();
            });

            $('#growth-sign-filter').on('change', function() {
                applyFilters();
            });

            // No-op kept for backward compatibility with other call sites.
            function applySectionColumnVisibility(_sectionVal) {
                if (table && table.redraw) table.redraw(true);
            }


            // DIL Filter Dropdown Button Handlers
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
                
                
            }

            // Update summary badges - use filtered data for accurate counts
            function updateSummary() {
                // Use active (filtered) data for all counts to match what's actually visible
                const data = table.getData('active');
                
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaInv = 0;
                let totalFbaL30 = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;

                data.forEach(row => {
                    const estock = parseFloat(row['E Stock'] || 0) || 0;
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);
                    
                    if (estock > 0) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaInv += estock;
                        totalFbaL30 += ebayL30;

                        // Count sold
                        if (ebayL30 === 0) zeroSoldCount++;
                        else moreSoldCount++;
                    }
                });

                // Calculate weighted average price
                let totalWeightedPrice = 0;
                let totalL30 = 0;
                data.forEach(row => {
                    if (parseFloat(row['E Stock'] || 0) > 0) {
                        const price = parseFloat(row['eBay Price'] || 0);
                        const l30 = parseFloat(row['eBay L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;

                // Calculate views and CVR
                let totalViews = 0;
                data.forEach(row => {
                    if (parseFloat(row['E Stock'] || 0) > 0) {
                        totalViews += parseFloat(row.views || 0);
                    }
                });
                // CVR = (orders-API L30 units / Σ views) × 100. Numerator is the same
                // fixed value the S Qty badge shows (Σ ebay2_order_items.quantity for
                // period='l30') — orders-API ground truth, same source the master
                // page's Qty cell uses. Previously this used the per-row eBay L30 sum
                // from ebay_2_metrics, which lags the Orders API. Denominator stays
                // the page sum of 'views' across rows with E Stock > 0.
                const avgCVR = totalViews > 0 ? (ORDERS_L30_TOTAL_QTY / totalViews * 100) : 0;

                // Missing L / Map / N Map are counted over the FULL dataset (like /map-issues),
                // not the active/filtered view — otherwise not-listed rows are hidden by the
                // default filters and the Missing badge shows 0.
                let missingCount = 0;
                let mapCount = 0;
                let nmapCount = 0;
                table.getData().forEach(row => {
                    if (isEbay2MissingL(row)) missingCount++;
                    if (isEbay2TabulatorMapRow(row)) mapCount++;
                    if (isEbay2TabulatorNMapRow(row)) nmapCount++;
                });
                
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;

                // Update all badges
                $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
                $('#more-sold-count').text(moreSoldCount.toLocaleString());

                $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
                $('#total-sales-amt-badge').text('Sales: $' + Math.round(totalSalesAmt).toLocaleString());

                $('#avg-gpft-badge').text('GPFT: ' + Math.round(avgGpft) + '%');
                $('#groi-percent-badge').text('GROI: ' + Math.round(groiPercent) + '%');

                
                $('#avg-price-badge').text('Price: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(2) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#total-inv-badge').text('E Stock: ' + Math.round(totalFbaInv).toLocaleString());
                $('#ebay2-missing-count-badge').text('Missing L: ' + missingCount.toLocaleString());
                $('#ebay2-map-count-badge').text('Map: ' + mapCount.toLocaleString());
                $('#ebay2-nmap-count-badge').text('N Map: ' + nmapCount.toLocaleString());
            }

            // Build Column Visibility Dropdown
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
                            checkbox.checked = savedVisibility[def.field] !== false;
                            checkbox.style.marginRight = "8px";

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(def.title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    });
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
                });
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
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (def.field && savedVisibility[def.field] === false) {
                                col.hide();
                            }
                        });
                    });
            }

            // Wait for table to be built
            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
                
                // Set up periodic background retry check (every 30 seconds)
                setInterval(() => {
                    backgroundRetryFailedSkus();
                }, 30000);
            });

            table.on('dataLoaded', function() {
                updateCalcValues();
                updateSummary();
                // Refresh checkboxes to reflect selectedSkus set (matching Amazon approach)
                setTimeout(function() {
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
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
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
            });

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
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


            // Toggle functionality removed - only PMT Spend L30 shown now
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
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            }

            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                '(Child) sku': 'SKU',
                'INV': 'INV',
                'L30': 'L30',
                'E Dil%': 'Dil%',
                'eBay L30': 'eBay L30',
                'eBay L60': 'eBay L60',
                'growth_percent': 'Growth',
                'eBay Price': 'eBay Price',
                'lmp_price': 'LMP',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'ROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'E Stock': 'E Stock',
                'Missing': 'Missing L',
                'MAP': 'MAP',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SPFT',
                'SROI': 'SROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'SCVR',
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
                    return field && exportColumnMapping[field] && field !== '_select' && field !== '_accept';
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
                const exportUrl = `/export-ebay2-pricing-data?columns=${columnsParam}`;
                
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
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success || 'Ratings imported successfully');
                        
                        // Reload table data
                        setTimeout(() => {
                            table.setData('/ebay2-data?_=' + Date.now());
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
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

        function refreshEbay2TableData() {
            if (typeof table !== 'undefined' && table) {
                // Preserve the horizontal scroll position so the table doesn't
                // "slide" back to the left when data is reloaded (e.g. after a push).
                const holder = table.element ? table.element.querySelector('.tabulator-tableHolder') : null;
                const scrollLeft = holder ? holder.scrollLeft : 0;

                table.replaceData('/ebay2-data?_=' + Date.now()).then(function() {
                    const h = table.element ? table.element.querySelector('.tabulator-tableHolder') : null;
                    if (h) h.scrollLeft = scrollLeft;
                }).catch(function() {});
            }
        }

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
                data: { sku: sku },
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
                    $('#lmpDataList').html(`
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
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
                        refreshEbay2TableData();
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
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        
                        // Reload competitors list
                        const sku = currentLmpData.sku;
                        loadEbayCompetitorsModal(sku);
                        
                        // Reload main table data
                        refreshEbay2TableData();
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

        // Tooltip functions for eBay2 links
        function showEbay2Tooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        }

        function hideEbay2Tooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        }

    </script>
@endsection
