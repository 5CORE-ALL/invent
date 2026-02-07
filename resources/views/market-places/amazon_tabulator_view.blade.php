@extends('layouts.vertical', ['title' => 'Amazon FBM', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
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
        
        /* Keep checkbox column header upright (not rotated) */
        .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
        }
        
        .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title input {
            transform: none !important;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Hide built-in pagination counter (moved above table) */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-counter {
            display: none !important;
        }

        /* Style pagination buttons - bigger and modern */
        .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            cursor: pointer;
            transition: all 0.15s ease !important;
            text-align: center !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        .acos-info-icon {
            transition: color 0.2s;
        }

        .acos-info-icon:hover {
            color: #007bff !important;
        }

        #campaignModal .table {
            font-size: 0.875rem;
        }

        #campaignModal .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            height: 60px;
            width: 40px;
            min-width: 40px;
            font-size: 11px;
            vertical-align: middle;
            text-align: center;
            padding: 5px;
        }

        #campaignModal .table td {
            white-space: nowrap;
            vertical-align: middle;
            text-align: center;
        }

        /* Coloring for ACOS, 7UB, 1UB */
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
        'page_title' => 'Amazon FBM',
        'sub_title' => 'Amazon FBM',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 150px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">INV</option>
                        <option value="zero">Zero </option>
                        <option value="more" selected>More</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">ALL</option>
                        <option value="nr">NRL</option>
                        <option value="req" selected>RL</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative (&lt;0%)</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40plus">40%+</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR</option>
                        <option value="0-0">0 to 0.00%</option>
                        <option value="0.01-1">0.01 - 1%</option>
                        <option value="1-2">1-2%</option>
                        <option value="2-3">2-3%</option>
                        <option value="3-4">3-4%</option>
                        <option value="4-7">4-7%</option>
                        <option value="7-10">7-10%</option>
                        <option value="10plus">10%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;16.7%</option>
                        <option value="yellow">Yellow 16.7-25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <select id="rating-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">Rating</option>
                        <option value="red">Red &lt;3</option>
                        <option value="yellow">Yellow 3-3.5</option>
                        <option value="blue">Blue 3.51-3.99</option>
                        <option value="green">Green 4-4.5</option>
                        <option value="pink">Pink &gt;4.5</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Rows</option>
                        <option value="parents">Parents</option>
                        <option value="skus" selected>SKUs</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="not-pushed">Not Pushed</option>
                        <option value="pushed">Pushed</option>
                        <option value="applied">Applied</option>
                        <option value="error">Error</option>
                    </select>

                    <select id="sold-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Sold</option>
                        <option value="sold">Sold (>0)</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="section-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Section</option>
                        <option value="pricing">Pricing</option>
                        <option value="kw-ads">KW Ads</option>
                        <option value="pt-ads">PT Ads</option>
                        <option value="hl-ads">HL Ads</option>
                    </select>

                    <!-- KW Page Filters -->
                    <select id="utilization-type-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Utilization</option>
                        <option value="gg">Green+Green</option>
                        <option value="gp">Green+Pink</option>
                        <option value="gr">Green+Red</option>
                        <option value="pg">Pink+Green</option>
                        <option value="pp">Pink+Pink</option>
                        <option value="pr">Pink+Red</option>
                        <option value="rg">Red+Green</option>
                        <option value="rp">Red+Pink</option>
                        <option value="rr">Red+Red</option>
                    </select>

                    <select id="campaign-status-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">Active Filter</option>
                        <option value="ALL">All</option>
                        <option value="ENABLED">Active</option>
                        <option value="PAUSED">Paused</option>
                    </select>

                    <select id="nra-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">KW NRA</option>
                        <option value="NRA">NRA</option>
                        <option value="RA">RA</option>
                        <option value="LATER">LATER</option>
                    </select>

                    <select id="price-slab-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">Price Slab</option>
                        <option value="lt10">&lt; $10</option>
                        <option value="10-20">$10 - $20</option>
                        <option value="20-30">$20 - $30</option>
                        <option value="30-50">$30 - $50</option>
                        <option value="50-100">$50 - $100</option>
                        <option value="gt100">&gt; $100</option>
                    </select>

                    <select id="acos-slab-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">ACOS</option>
                        <option value="8">&lt; 5%</option>
                        <option value="7">5-9%</option>
                        <option value="6">10-14%</option>
                        <option value="5">15-19%</option>
                        <option value="4">20-24%</option>
                        <option value="3">25-29%</option>
                        <option value="2">30-34%</option>
                        <option value="1">≥ 35%</option>
                        <option value="acos35spend10">&gt;35% &amp; SPEND &gt;10</option>
                    </select>

                    <!-- Unified Range Filter (Views & Sold) -->
                    <select id="range-column-select" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">Select Filter</option>
                        <option value="Sess30">View L30</option>
                        <option value="Sess7">View L7</option>
                        <option value="A_L30">Sold L30</option>
                        <option value="A_L7">Sold L7</option>
                    </select>
                    <input type="number" id="range-min" class="form-control form-control-sm" 
                        placeholder="Min" min="0" style="width: 90px; display: inline-block;">
                    <input type="number" id="range-max" class="form-control form-control-sm" 
                        placeholder="Max" min="0" style="width: 90px; display: inline-block;">
                    <button id="clear-range-filter" class="btn btn-sm btn-outline-secondary" title="Clear Range Filter">
                        <i class="fas fa-times"></i>
                    </button>
                    <span class="badge bg-info fs-6 p-2" id="range-filter-count-badge" style="color: white; font-weight: bold; display: none;">
                        Filtered: <span id="range-filter-count">0</span>
                    </span>

                    <!-- 7UB/1UB/ACOS Range Filters -->
                    <span class="text-muted ms-2" style="font-size: 0.75rem;">7UB:</span>
                    <input type="number" id="7ub-min" class="form-control form-control-sm" 
                        placeholder="Min" step="0.1" style="width: 60px; display: inline-block;">
                    <input type="number" id="7ub-max" class="form-control form-control-sm" 
                        placeholder="Max" step="0.1" style="width: 60px; display: inline-block;">
                    <span class="text-muted ms-2" style="font-size: 0.75rem;">1UB:</span>
                    <input type="number" id="1ub-min" class="form-control form-control-sm" 
                        placeholder="Min" step="0.1" style="width: 60px; display: inline-block;">
                    <input type="number" id="1ub-max" class="form-control form-control-sm" 
                        placeholder="Max" step="0.1" style="width: 60px; display: inline-block;">
                    <span class="text-muted ms-2" style="font-size: 0.75rem;">ACOS:</span>
                    <input type="number" id="acos-range-min" class="form-control form-control-sm" 
                        placeholder="Min" step="0.1" style="width: 60px; display: inline-block;">
                    <input type="number" id="acos-range-max" class="form-control form-control-sm" 
                        placeholder="Max" step="0.1" style="width: 60px; display: inline-block;">

                    <!-- Selected Rows Count -->
                    <span class="badge bg-primary fs-6 p-2 ms-2" id="selected-rows-count" style="display: none;">
                        0 selected
                    </span>
                    <button class="btn btn-sm btn-outline-secondary ms-1" id="clear-selection-btn" style="display: none;" title="Clear Selection">
                        <i class="fas fa-times"></i> Clear
                    </button>

                    <!-- Bulk Actions Dropdown -->
                    <div class="dropdown d-inline-block ms-2" id="bulk-actions-container" style="display: none;">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button"
                            id="bulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tasks"></i> Bulk Actions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown">
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="NRA">Mark as NRA</a></li>
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="RA">Mark as RA</a></li>
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="LATER">Mark as LATER</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="PAUSE">Pause Campaigns</a></li>
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="ACTIVATE">Activate Campaigns</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns"></i> Col
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> Show All
                    </button>

                    {{-- <span class="me-3 px-3 py-1" style="background-color: #e3f2fd; border-radius: 5px;">
                        <strong>PFT%:</strong> <span id="pft-calc">0.00%</span>
                    </span>
                    <span class="me-3 px-3 py-1" style="background-color: #e8f5e9; border-radius: 5px;">
                        <strong>ROI%:</strong> <span id="roi-calc">0.00%</span>
                    </span> --}}

                    <button id="import-btn" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import Ratings
                    </button>

                    <a href="{{ url('/amazon-ratings-sample') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> Template
                    </a>

                    <a href="{{ url('/amazon-export-pricing-cvr') }}" class="btn btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Export
                    </a>

                    <a href="{{ url('/amazon-export-sprice-upload') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> SPRICE N Upload
                    </a>
                    
                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-percent"></i> Decrease
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-percent"></i> Increase
                    </button>

                    <button id="clear-sprice-btn" class="btn btn-sm btn-danger" style="display: none;">
                        <i class="fas fa-eraser"></i> Clear SPRICE
                    </button>

                    <button id="seo-btn" class="btn btn-sm" style="background-color: #8B0000; color: white; font-weight: bold;">
                        CVR Content (<span id="seo-count">0</span>)
                    </button>

                    <span class="badge bg-info fs-6 p-2" id="total-sku-count-badge" style="color: black; font-weight: bold; display: none;">Total SKUs: 0</span>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (INV > 0)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Sold Filter Badges (Clickable + Hover for chart) -->
                        <span class="badge bg-success fs-6 p-2 sold-filter-badge amz-hover-chart" data-filter="all" data-metric="sold_count" data-source="badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Sold (>0): <span id="total-sold-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge amz-hover-chart" data-filter="zero" data-metric="zero_sold_count" data-source="badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            0 Sold: <span id="zero-sold-count">0</span>
                        </span>
                        
                        <!-- Inventory Mapping Badges (Clickable + Hover for chart) -->
                        <span class="badge bg-success fs-6 p-2 map-filter-badge amz-hover-chart" data-filter="mapped" data-metric="map_count" data-source="badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Map: <span id="map-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 map-filter-badge amz-hover-chart" data-filter="nmapped" data-metric="nmap_count" data-source="badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                             N Map: <span id="nmap-count">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 missing-amz-filter-badge amz-hover-chart" data-filter="missing-amazon" data-metric="missing_count" data-source="badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Missing: <span id="missing-amazon-count">0</span>
                        </span>
                        
                        <!-- Price Comparison Badge -->
                        <span class="badge bg-danger fs-6 p-2 price-filter-badge amz-hover-chart" data-filter="prc-gt-lmp" data-metric="prc_gt_lmp_count" data-source="badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Prc > LMP: <span id="prc-gt-lmp-count">0</span>
                        </span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2 amz-badge-chart" data-metric="l30_sales" id="total-pft-amt-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">Total PFT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2 amz-badge-chart" data-metric="l30_sales" id="total-sales-amt-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">Total Sales: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2 amz-badge-chart" data-metric="ad_spend" id="total-spend-l30-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">Spend L30: $0.00</span>
                        
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2 amz-badge-chart" data-metric="gprofit" id="avg-gpft-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2 amz-badge-chart" data-metric="npft" id="avg-pft-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">PFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2 amz-badge-chart" data-metric="groi" id="groi-percent-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">GROI: 0%</span>
                        <span class="badge bg-primary fs-6 p-2 amz-badge-chart" data-metric="nroi" id="nroi-percent-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2 amz-badge-chart" data-metric="ads_pct" id="tcos-percent-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">TCOS: 0%</span>
                        
                        <!-- Amazon Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-views-badge" style="color: black; font-weight: bold;">Avg Views: 0</span>
                        <span class="badge bg-success fs-6 p-2 amz-badge-chart" data-metric="l30_orders" id="total-amazon-l30-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">A L30: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-amazon-l7-badge" style="color: black; font-weight: bold;">A L7: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-amazon-inv-badge" style="color: black; font-weight: bold;">INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-amazon-inv-amz-badge" style="color: black; font-weight: bold;">INV AMZ: 0</span>
                        
                        <!-- Ad Spend Breakdown -->
                        <span class="badge bg-dark fs-6 p-2 amz-badge-chart" data-metric="kw_spend" data-source="badge" id="kw-spend-badge" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">KW Ads: $0</span>
                        <span class="badge bg-secondary fs-6 p-2 amz-badge-chart" data-metric="hl_spend" data-source="badge" id="hl-spend-badge" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">HL Ads: $0</span>
                        <span class="badge bg-dark fs-6 p-2 amz-badge-chart" data-metric="pt_spend" data-source="badge" id="pt-spend-badge" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">PT Ads: $0</span>
                        
                        <!-- Campaign Statistics (from KW page) -->
                        <span class="badge fs-6 p-2 campaign-count-badge amz-hover-chart" id="campaign-count-badge" data-metric="campaign_count" data-source="badge" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Campaign: <span id="campaign-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 missing-campaign-badge amz-hover-chart" id="missing-campaign-badge" data-metric="missing_campaign_count" data-source="badge" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Missing Camp: <span id="missing-campaign-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 nra-count-badge amz-hover-chart" id="nra-count-badge" data-metric="nra_count" data-source="badge" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            NRA: <span id="nra-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 ra-count-badge amz-hover-chart" id="ra-count-badge" data-metric="ra_count" data-source="badge" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            RA: <span id="ra-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 paused-campaigns-badge amz-hover-chart" id="paused-campaigns-badge" data-metric="paused_count" data-source="badge" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            Paused: <span id="paused-campaigns-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 7ub-count-badge amz-hover-chart" id="7ub-count-badge" data-metric="ub7_count" data-source="badge" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            7UB: <span id="7ub-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2 7ub-1ub-count-badge amz-hover-chart" id="7ub-1ub-count-badge" data-metric="ub7_ub1_count" data-source="badge" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for trend">
                            7UB+1UB: <span id="7ub-1ub-count">0</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 fw-bold">Type:</label>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 130px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="value">Value ($)</option>
                        </select>
                        
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter value" step="0.1" min="0" 
                            style="width: 150px; display: inline-block;">
                        <button id="apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-end mb-1">
                    <span id="table-row-counter" style="font-size:15px;color:#334155;font-weight:600;"></span>
                </div>
                <div id="amazon-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body (scrollable section) -->
                    <div id="amazon-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scout Modal -->
    <div class="modal fade" id="scoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scout Data for <span id="scoutSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="scoutDataList"></div>
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
                        <i class="fa fa-shopping-cart"></i> Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fa fa-plus-circle"></i> Add New Competitor</strong>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label"><strong>SKU</strong></label>
                                    <input type="text" class="form-control" id="addCompSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="addCompAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="addCompPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="addCompLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Marketplace</strong></label>
                                    <select class="form-select" id="addCompMarketplace">
                                        <option value="amazon" selected>Amazon</option>
                                        <option value="US">US</option>
                                    </select>
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
                            <option value="7" selected>Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30">Last 30 Days</option>
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

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Amazon Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Choose CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv" required>
                        </div>
                        <div class="mb-3">
                            <h6>Sample CSV Format:</h6>
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i> CSV must have SKU in the first column, followed by rating column.<br>
                                Example format:<br>
                                <code>SKU,rating<br>ABC123,5<br>DEF456,4<br>GHI789,3</code>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Amazon Metric Trend Chart Modal -->
    <div class="modal fade" id="amzMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzChartModalTitle">Amazon - Metric Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                    <div id="amzChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="amzMetricChart"></canvas>
                        </div>
                        <div id="amzChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="amzChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="amzChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="amzChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="amzChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data available for this metric.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "amazon_tabulator_column_visibility";
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let selectedSkus = new Set(); // Track selected SKUs across all pages
        let soldFilterActive = 'all'; // Track sold filter state: 'all', 'sold', 'zero'
        let priceFilterActive = false; // Track price filter state: true = show only Prc > LMP
        let mapFilterActive = 'all'; // Track map filter state: 'all', 'mapped', 'missing'
        let seoModeActive = false; // Track SEO mode state

        // === Amazon Metric Trend Chart ===
        let amzChartInstance = null;
        let amzChartDays = 30;
        let amzChartMetricKey = '';
        let amzChartAjax = null;

        const amzMetricLabels = {
            'l30_sales': 'L30 Sales', 'l30_orders': 'L30 Orders', 'qty': 'Total Qty',
            'gprofit': 'Gprofit%', 'groi': 'G ROI%', 'ads_pct': 'Ads%/TCOS',
            'npft': 'N PFT%', 'nroi': 'N ROI%', 'ad_spend': 'Ad Spend',
            'clicks': 'Clicks', 'acos': 'ACOS', 'missing_l': 'Missing',
            'nmap': 'N Map',
            // Badge-stat metrics (daily snapshot counts)
            'sold_count': 'Sold (>0)', 'zero_sold_count': '0 Sold',
            'map_count': 'Map', 'nmap_count': 'N Map', 'missing_count': 'Missing',
            'prc_gt_lmp_count': 'Prc > LMP', 'campaign_count': 'Campaign',
            'missing_campaign_count': 'Missing Camp', 'nra_count': 'NRA',
            'ra_count': 'RA', 'paused_count': 'Paused',
            'ub7_count': '7UB', 'ub7_ub1_count': '7UB+1UB',
            'kw_spend': 'KW Ads Spend', 'hl_spend': 'HL Ads Spend', 'pt_spend': 'PT Ads Spend',
        };

        // Metrics stored in badge stats table (daily counts/amounts)
        const amzBadgeStatMetrics = [
            'sold_count', 'zero_sold_count', 'map_count', 'nmap_count',
            'missing_count', 'prc_gt_lmp_count', 'campaign_count',
            'missing_campaign_count', 'nra_count', 'ra_count',
            'paused_count', 'ub7_count', 'ub7_ub1_count',
            'kw_spend', 'hl_spend', 'pt_spend',
        ];

        const amzPctMetrics = ['gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'acos'];
        const amzDollarMetrics = ['l30_sales', 'ad_spend', 'kw_spend', 'hl_spend', 'pt_spend'];

        function amzFmtVal(v) {
            if (amzDollarMetrics.includes(amzChartMetricKey)) return '$' + Math.round(v).toLocaleString('en-US');
            if (amzPctMetrics.includes(amzChartMetricKey)) return v.toFixed(1) + '%';
            return Math.round(v).toLocaleString('en-US');
        }

        function showAmzMetricChart(metricKey) {
            amzChartMetricKey = metricKey;
            amzChartDays = 30;
            $('#amzChartRangeSelect').val('30');
            const label = amzMetricLabels[metricKey] || metricKey;
            const isBadge = amzBadgeStatMetrics.includes(metricKey);
            $('#amzChartModalTitle').text(`Amazon - ${label} (${isBadge ? 'Daily Count' : 'Rolling L30'})`);
            const modal = new bootstrap.Modal(document.getElementById('amzMetricChartModal'));
            modal.show();
            loadAmzMetricChart();
        }

        function loadAmzMetricChart() {
            if (amzChartAjax) amzChartAjax.abort();
            $('#amzChartNoData').hide();
            $('#amzChartContainer').hide();
            $('#amzChartLoading').show();

            // Determine endpoint based on metric type
            const isBadgeStat = amzBadgeStatMetrics.includes(amzChartMetricKey);
            const ajaxUrl = isBadgeStat ? '/amazon-badge-chart-data' : '/channel-metric-chart-data';
            const ajaxData = isBadgeStat
                ? { metric: amzChartMetricKey, days: amzChartDays }
                : { channel: 'amazon', metric: amzChartMetricKey, days: amzChartDays };

            amzChartAjax = $.ajax({
                url: ajaxUrl,
                method: 'GET',
                data: ajaxData,
                success: function(resp) {
                    amzChartAjax = null;
                    $('#amzChartLoading').hide();
                    if (resp.success && resp.data && resp.data.length > 0) {
                        $('#amzChartContainer').show();
                        renderAmzMetricChart(resp.data);
                    } else {
                        $('#amzChartNoData').show();
                    }
                },
                error: function(xhr, status) {
                    amzChartAjax = null;
                    if (status === 'abort') return;
                    $('#amzChartLoading').hide();
                    $('#amzChartNoData').show();
                }
            });
        }

        $(document).on('change', '#amzChartRangeSelect', function() {
            const days = parseInt($(this).val());
            if (days === amzChartDays) return;
            amzChartDays = days;
            const rangeLabel = days === 0 ? 'Lifetime' : 'L' + days;
            const titleEl = $('#amzChartModalTitle');
            titleEl.text(titleEl.text().replace(/\(Rolling [^)]+\)/, `(Rolling ${rangeLabel})`));
            loadAmzMetricChart();
        });

        // Badge click handler
        $(document).on('click', '.amz-badge-chart', function() {
            showAmzMetricChart($(this).data('metric'));
        });

        // Hover-to-chart for filter-toggle badges (500ms delay to avoid accidental triggers)
        let amzHoverTimer = null;
        $(document).on('mouseenter', '.amz-hover-chart', function() {
            const metric = $(this).data('metric');
            if (!metric) return;
            amzHoverTimer = setTimeout(() => {
                showAmzMetricChart(metric);
            }, 500);
        });
        $(document).on('mouseleave', '.amz-hover-chart', function() {
            if (amzHoverTimer) { clearTimeout(amzHoverTimer); amzHoverTimer = null; }
        });

        function renderAmzMetricChart(data) {
            const ctx = document.getElementById('amzMetricChart').getContext('2d');
            if (amzChartInstance) amzChartInstance.destroy();

            const labels = data.map(d => d.date);
            const values = data.map(d => d.value);

            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;

            document.getElementById('amzChartHighest').textContent = amzFmtVal(dataMax);
            document.getElementById('amzChartMedian').textContent = amzFmtVal(median);
            document.getElementById('amzChartLowest').textContent = amzFmtVal(dataMin);

            const dotColors = values.map((v, i) => i === 0 ? '#6c757d' : v < values[i - 1] ? '#198754' : v > values[i - 1] ? '#dc3545' : '#6c757d');
            const labelColors = values.map((v, i) => i < 7 ? '#6c757d' : v < values[i - 7] ? '#198754' : v > values[i - 7] ? '#dc3545' : '#6c757d');

            const medianLinePlugin = {
                id: 'medianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y, xScale = chart.scales.x, ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    ctx.save(); ctx.setLineDash([6, 4]); ctx.strokeStyle = '#6c757d'; ctx.lineWidth = 1.2;
                    ctx.beginPath(); ctx.moveTo(xScale.left, yPixel); ctx.lineTo(xScale.right, yPixel); ctx.stroke(); ctx.restore();
                }
            };

            const valueLabelsPlugin = {
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const dataset = chart.data.datasets[0], meta = chart.getDatasetMeta(0), ctx = chart.ctx;
                    ctx.save(); ctx.font = 'bold 7px Inter, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const offsetY = (i % 2 === 0) ? -7 : -14;
                        ctx.fillStyle = labelColors[i];
                        ctx.fillText(amzFmtVal(dataset.data[i]), point.x, point.y + offsetY);
                    });
                    ctx.restore();
                }
            };

            amzChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: 'rgba(108,117,125,0.08)',
                        borderColor: '#adb5bd',
                        borderWidth: 1.5,
                        fill: true, tension: 0.3,
                        pointRadius: 3, pointHoverRadius: 5,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true, maintainAspectRatio: false,
                    layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                            callbacks: {
                                label: function(context) {
                                    const idx = context.dataIndex;
                                    let parts = ['Value: ' + amzFmtVal(context.raw)];
                                    if (idx > 0) {
                                        const diff = context.raw - values[idx - 1];
                                        parts.push('vs Yesterday: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + amzFmtVal(Math.abs(diff)));
                                    }
                                    if (idx >= 7) {
                                        const diff7 = context.raw - values[idx - 7];
                                        parts.push('vs 7d Ago: ' + (diff7 < 0 ? '▼' : diff7 > 0 ? '▲' : '▬') + ' ' + amzFmtVal(Math.abs(diff7)));
                                    }
                                    return parts;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { min: yMin, max: yMax, ticks: { font: { size: 9 }, callback: v => amzFmtVal(v) } },
                        x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                    }
                }
            });
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
                        },
                        {
                            label: 'AD%',
                            data: [],
                            borderColor: '#FFD700',
                            backgroundColor: 'rgba(255, 215, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1',
                            tension: 0.4
                        },
                        {
                            label: 'Sold (L30)',
                            data: [],
                            borderColor: '#FF00FF',
                            backgroundColor: 'rgba(255, 0, 255, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
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
                            text: 'Amazon SKU Metrics',
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
                                    } else if (label.includes('Sold')) {
                                        return label + ': ' + Math.round(value).toLocaleString();
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
                                text: 'Price/Views/Sold',
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
                                    // Format as number (for Views and Sold), not currency
                                    // Price will be shown in tooltip, but axis shows numeric values
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

        function loadSkuMetricsData(sku, days = 7) {
            console.log('Loading metrics data for SKU:', sku, 'Days:', days);
            fetch(`/amazon-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
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
                            skuMetricsChart.options.plugins.title.text = 'Amazon Metrics';
                            skuMetricsChart.update();
                            return;
                        }
                        
                        $('#chart-no-data-message').hide();
                        skuMetricsChart.options.plugins.title.text = `Amazon Metrics (${days} Days)`;
                        skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                        skuMetricsChart.data.datasets[0].data = data.map(d => d.price || 0);
                        skuMetricsChart.data.datasets[1].data = data.map(d => d.views || 0);
                        skuMetricsChart.data.datasets[2].data = data.map(d => d.cvr_percent || 0);
                        skuMetricsChart.data.datasets[3].data = data.map(d => d.ad_percent || 0);
                        skuMetricsChart.data.datasets[4].data = data.map(d => d.a_l30 || 0);
                        skuMetricsChart.update('active');
                        console.log('Chart updated successfully with', data.length, 'data points');
                    }
                })
                .catch(error => {
                    console.error('Error loading SKU metrics data:', error);
                    alert('Error loading metrics data. Please check console for details.');
                });
        }

        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null
        };

        $(document).ready(function() {
            // Initialize charts
            initSkuMetricsChart();

            // Sold filter badge click handlers
            $('.sold-filter-badge').on('click', function() {
                const filter = $(this).data('filter');
                
                // Update dropdown value
                if (filter === 'all') {
                    $('#sold-filter').val('sold'); // Show only sold items
                } else if (filter === 'zero') {
                    $('#sold-filter').val('zero'); // Show only 0-sold items
                }
                
                // Re-apply filters
                applyFilters();
            });

            // Price filter badge click handler
            $('.price-filter-badge').on('click', function() {
                // Toggle the price filter
                priceFilterActive = !priceFilterActive;
                
                // Update badge appearance
                if (priceFilterActive) {
                    $(this).removeClass('bg-danger').addClass('bg-warning').css('color', 'black');
                } else {
                    $(this).removeClass('bg-warning').addClass('bg-danger').css('color', 'white');
                }
                
                // Re-apply filters
                applyFilters();
            });

            // Map filter badge click handlers
            $('.map-filter-badge').on('click', function() {
                const filter = $(this).data('filter');
                
                // Toggle filter state
                if (mapFilterActive === filter) {
                    // If clicking the same filter, turn it off
                    mapFilterActive = 'all';
                    // Reset badge appearance
                    $('.map-filter-badge').each(function() {
                        const badgeFilter = $(this).data('filter');
                        if (badgeFilter === 'mapped') {
                            $(this).removeClass('bg-warning').addClass('bg-success').css('color', 'black');
                        } else {
                            $(this).removeClass('bg-warning').addClass('bg-danger').css('color', 'white');
                        }
                    });
                } else {
                    // Set new filter
                    mapFilterActive = filter;
                    // Update badge appearance
                    $('.map-filter-badge').each(function() {
                        const badgeFilter = $(this).data('filter');
                        if (badgeFilter === filter) {
                            $(this).removeClass('bg-success bg-danger').addClass('bg-warning').css('color', 'black');
                        } else {
                            if (badgeFilter === 'mapped') {
                                $(this).removeClass('bg-warning').addClass('bg-success').css('color', 'black');
                            } else {
                                $(this).removeClass('bg-warning').addClass('bg-danger').css('color', 'white');
                            }
                        }
                    });
                }
                
                // Re-apply filters
                applyFilters();
            });

            // Missing Amazon filter badge click handler
            let missingAmazonFilterActive = false;
            $('.missing-amz-filter-badge').on('click', function() {
                missingAmazonFilterActive = !missingAmazonFilterActive;
                
                // Update badge appearance
                if (missingAmazonFilterActive) {
                    $(this).removeClass('bg-warning').addClass('bg-info').css('color', 'black');
                    // Turn off map filters
                    mapFilterActive = 'all';
                    $('.map-filter-badge').each(function() {
                        const badgeFilter = $(this).data('filter');
                        if (badgeFilter === 'mapped') {
                            $(this).removeClass('bg-warning').addClass('bg-success').css('color', 'black');
                        } else {
                            $(this).removeClass('bg-warning').addClass('bg-danger').css('color', 'white');
                        }
                    });
                } else {
                    $(this).removeClass('bg-info').addClass('bg-warning').css('color', 'black');
                }
                
                // Re-apply filters
                applyFilters();
            });

            // SEO button click handler
            $('#seo-btn').on('click', function() {
                seoModeActive = !seoModeActive;
                
                if (seoModeActive) {
                    $(this).css('background-color', '#006400'); // Darker green when active
                    
                    // Hide specified columns
                    table.getColumn('NR').hide();
                    table.getColumn('image_path').hide();
                    table.getColumn('is_missing').hide();
                    table.getColumn('inv_map').hide();
                    table.getColumn('rating').hide();
                    table.getColumn('INV_AMZ').hide();
                    
                    // Hide columns from NROI to AD SALES L30
                    table.getColumn('NROI').hide();
                    table.getColumn('AD%').hide();
                    table.getColumn('ACOS').hide();
                    table.getColumn('SALES_L30').hide();
                    
                    // Hide SPFT and SROI columns
                    table.getColumn('Spft%').hide();
                    table.getColumn('SROI').hide();
                    
                    // Apply SEO filters
                    applyFilters();
                } else {
                    $(this).css('background-color', '#8B0000'); // Dark red when inactive
                    
                    // Show specified columns
                    table.getColumn('NR').show();
                    table.getColumn('image_path').show();
                    table.getColumn('is_missing').show();
                    table.getColumn('inv_map').show();
                    table.getColumn('rating').show();
                    table.getColumn('INV_AMZ').show();
                    
                    // Show columns from NROI to AD SALES L30
                    table.getColumn('NROI').show();
                    table.getColumn('AD%').show();
                    table.getColumn('ACOS').show();
                    table.getColumn('SALES_L30').show();
                    
                    // Show SPFT and SROI columns
                    table.getColumn('Spft%').show();
                    table.getColumn('SROI').show();
                    
                    // Remove SEO filters
                    applyFilters();
                }
                
                updateSeoCount();
            });

            // Function to update SEO count
            function updateSeoCount() {
                if (!table) return;
                
                const filteredData = table.getData('active').filter(row => row.is_parent_summary);
                $('#seo-count').text(filteredData.length);
            }

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() {
                const type = $(this).val();
                const $input = $('#discount-percentage-input');
                
                if (type === 'percentage') {
                    $input.attr('placeholder', 'Enter percentage');
                    $input.attr('max', '100');
                } else {
                    $input.attr('placeholder', 'Enter value');
                    $input.removeAttr('max');
                }
            });

            // Decrease Mode Toggle
            $('#decrease-btn').on('click', function() {
                decreaseModeActive = !decreaseModeActive;
                const selectColumn = table.getColumn('_select');
                
                if (decreaseModeActive) {
                    // Disable increase mode if active
                    if (increaseModeActive) {
                        increaseModeActive = false;
                        $('#increase-btn').removeClass('btn-danger').addClass('btn-success');
                        $('#increase-btn').html('<i class="fas fa-percent"></i> Increase');
                    }
                    selectColumn.show();
                    $(this).removeClass('btn-warning').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                    // Show Clear SPRICE button
                    $('#clear-sprice-btn').show();
                } else {
                    selectColumn.hide();
                    $(this).removeClass('btn-danger').addClass('btn-warning');
                    $(this).html('<i class="fas fa-percent"></i> Decrease');
                    // Clear all selections
                    selectedSkus.clear();
                    $('.sku-select-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    $('#discount-input-container').hide();
                    // Hide Clear SPRICE button
                    $('#clear-sprice-btn').hide();
                }
            });

            // Increase Mode Toggle
            $('#increase-btn').on('click', function() {
                increaseModeActive = !increaseModeActive;
                const selectColumn = table.getColumn('_select');
                
                if (increaseModeActive) {
                    // Disable decrease mode if active
                    if (decreaseModeActive) {
                        decreaseModeActive = false;
                        $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning');
                        $('#decrease-btn').html('<i class="fas fa-percent"></i> Decrease');
                    }
                    selectColumn.show();
                    $(this).removeClass('btn-success').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                    // Show Clear SPRICE button
                    $('#clear-sprice-btn').show();
                } else {
                    selectColumn.hide();
                    $(this).removeClass('btn-danger').addClass('btn-success');
                    $(this).html('<i class="fas fa-percent"></i> Increase');
                    // Clear all selections
                    selectedSkus.clear();
                    $('.sku-select-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    $('#discount-input-container').hide();
                    // Hide Clear SPRICE button
                    $('#clear-sprice-btn').hide();
                }
            });

            // Checkbox change handler - track selected SKUs
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                const isChecked = $(this).prop('checked');
                
                if (isChecked) {
                    selectedSkus.add(sku);
                } else {
                    selectedSkus.delete(sku);
                }
                
                updateSelectedCount();
                updateSelectAllCheckbox();
            });

            // Update selected count and discount input visibility
            function updateSelectedCount() {
                const selectedCount = selectedSkus.size;
                
                if (selectedCount > 0) {
                    $('#discount-input-container').show();
                    $('#selected-skus-count').text(`(${selectedCount} SKU${selectedCount > 1 ? 's' : ''} selected)`);
                } else {
                    $('#discount-input-container').hide();
                }
                
                // Update Apply All button
                updateApplyAllButton();
            }

            // Update Apply All button count and state
            function updateApplyAllButton() {
                const selectedCount = selectedSkus.size;
                const $btn = $('.apply-all-prices-btn');
                const $count = $('.apply-all-count');
                
                if ($count.length) {
                    $count.text(selectedCount);
                }
                
                if ($btn.length) {
                    if (selectedCount === 0) {
                        $btn.prop('disabled', true).addClass('disabled');
                    } else {
                        $btn.prop('disabled', false).removeClass('disabled');
                    }
                }
            }

            // Retry function for applying price with up to 5 attempts
            // NOTE: Backend now includes automatic verification and retry (2 attempts with fresh token)
            // This frontend retry is for network errors, timeouts, or persistent failures
            function applyPriceWithRetry(sku, price, cell, maxRetries = 5, delay = 5000) {
                return new Promise((resolve, reject) => {
                    let attempt = 0;
                    
                    function attemptApply() {
                        attempt++;
                        
                        $.ajax({
                            url: '/apply-amazon-price',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                price: price
                            },
                            success: function(response) {
                                // Check for errors in response
                                if (response.errors && response.errors.length > 0) {
                                    const errorMsg = response.errors[0].message || 'Unknown error';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                    
                                    // Check if it's an authentication error - don't retry immediately
                                    if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || errorMsg.includes('Client authentication failed')) {
                                        // For auth errors, wait longer before retry (10 seconds)
                                        if (attempt < maxRetries) {
                                            console.log(`Auth error - waiting longer before retry ${attempt} for SKU ${sku}...`);
                                            setTimeout(attemptApply, 10000);
                                        } else {
                                            console.error(`Max retries reached for SKU ${sku} due to auth error`);
                                            reject({ error: true, response: response, isAuthError: true });
                                        }
                                    } else {
                                        // For other errors, retry with normal delay
                                        if (attempt < maxRetries) {
                                            console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                            setTimeout(attemptApply, delay);
                                        } else {
                                            console.error(`Max retries reached for SKU ${sku}`);
                                            reject({ error: true, response: response });
                                        }
                                    }
                                } else {
                                    // Success
                                    resolve({ success: true, response: response });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                
                                // Check if it's an authentication error
                                if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || xhr.status === 401 || errorMsg.includes('Client authentication failed')) {
                                    // For auth errors, wait longer before retry
                                    if (attempt < maxRetries) {
                                        console.log(`Auth error - waiting longer before retry ${attempt} for SKU ${sku}...`);
                                        setTimeout(attemptApply, 10000);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku} due to auth error`);
                                        reject({ error: true, xhr: xhr, isAuthError: true });
                                    }
                                } else {
                                    // For other errors, retry with normal delay
                                    if (attempt < maxRetries) {
                                        console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({ error: true, xhr: xhr });
                                    }
                                }
                            }
                        });
                    }
                    
                    attemptApply();
                });
            }

            // Global function to apply all selected prices (can be called from button)
            window.applyAllSelectedPrices = function() {
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU to apply prices');
                    return;
                }
                
                const $btn = $('.apply-all-prices-btn');
                if ($btn.length === 0) {
                    showToast('error', 'Apply All button not found');
                    return;
                }
                
                if ($btn.prop('disabled')) {
                    return;
                }
                
                const originalHtml = $btn.html();
                
                // Disable button and show loading state
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
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
                    showToast('error', 'No valid prices found for selected SKUs');
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
                            $btn.removeClass('btn-primary').addClass('btn-success');
                            const selectedCount = selectedSkus.size;
                            $btn.html(`<i class="fas fa-check-double" style="color: black; font-weight: bold;"></i> Applied (<span class="apply-all-count">${selectedCount}</span>)`);
                            showToast('success', `Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`);
                            
                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.removeClass('btn-success').addClass('btn-primary');
                                $btn.html(originalHtml);
                                updateApplyAllButton();
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                        }
                        return;
                    }
                    
                    const { sku, price } = skusToProcess[currentIndex];
                    
                    // Find the row and update button to show clock spinner
                    const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                    if (row) {
                        const acceptCell = row.getCell('_accept');
                        if (acceptCell) {
                            const $cellElement = $(acceptCell.getElement());
                            const $btnInCell = $cellElement.find('.apply-price-btn');
                            if ($btnInCell.length) {
                                $btnInCell.prop('disabled', true);
                                // Ensure circular styling
                                $btnInCell.css({
                                    'border-radius': '50%',
                                    'width': '35px',
                                    'height': '35px',
                                    'padding': '0',
                                    'display': 'flex',
                                    'align-items': 'center',
                                    'justify-content': 'center'
                                });
                                $btnInCell.html('<i class="fas fa-clock fa-spin" style="color: black;"></i>');
                            }
                        }
                    }
                    
                    // Use retry function to apply price
                    applyPriceWithRetry(sku, price, null, 5, 5000)
                        .then((result) => {
                            successCount++;
                            
                            // Update row data with pushed status instantly
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'pushed';
                                row.update(rowData);
                                
                                // Update button to show green tick in circular button
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        // Ensure circular styling
                                        $btnInCell.css({
                                            'border-radius': '50%',
                                            'width': '35px',
                                            'height': '35px',
                                            'padding': '0',
                                            'display': 'flex',
                                            'align-items': 'center',
                                            'justify-content': 'center'
                                        });
                                        $btnInCell.html('<i class="fas fa-check-circle" style="color: black; font-size: 1.1em;"></i>');
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
                                
                                // Update button to show error icon in circular button
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        // Ensure circular styling
                                        $btnInCell.css({
                                            'border-radius': '50%',
                                            'width': '35px',
                                            'height': '35px',
                                            'padding': '0',
                                            'display': 'flex',
                                            'align-items': 'center',
                                            'justify-content': 'center'
                                        });
                                        $btnInCell.html('<i class="fas fa-times" style="color: black;"></i>');
                                    }
                                }
                            }
                            
                            // Process next SKU with delay to avoid rate limiting
                            currentIndex++;
                            setTimeout(() => {
                                processNextSku();
                            }, 2000);
                        });
                }
                
                // Start processing
                processNextSku();
            };

            // Update select all checkbox state based on current selections
            function updateSelectAllCheckbox() {
                if (!table) return;
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent_summary);
                
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

            // Select All checkbox handler
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent_summary);
                
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

            // Clear SPRICE button
            $('#clear-sprice-btn').on('click', function() {
                clearSpriceForSelected();
            });

            // Clear SPRICE for selected SKUs
            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select SKUs first');
                    return;
                }

                if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                    return;
                }

                let clearedCount = 0;
                const updates = [];

                // Iterate rows and clear SPRICE where selected
                table.getRows().forEach(row => {
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        // Clear in table
                        row.update({
                            SPRICE: 0,
                            SGPFT: 0,
                            'Spft%': 0,
                            SROI: 0,
                            SPRICE_STATUS: null,
                            has_custom_sprice: false
                        });

                        updates.push({ sku: sku, sprice: 0 });
                        clearedCount++;
                    }
                });

                if (updates.length > 0) {
                    // Send to server to persist
                    $.ajax({
                        url: '/amazon-clear-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: { updates: updates },
                        success: function(response) {
                            showToast('success', `SPRICE cleared for ${clearedCount} SKU(s)`);
                        },
                        error: function(xhr) {
                            console.error('Failed to clear SPRICE:', xhr);
                            showToast('error', 'Failed to clear SPRICE data');
                        }
                    });
                } else {
                    showToast('warning', 'No SPRICE values to clear for selected SKUs');
                }
            }

            // Helper: round to retail (.99 endings)
            function roundToRetailPrice(price) {
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.01).toFixed(2);
            }

            // Apply Discount/Increase Button
            $('#apply-discount-btn').on('click', function() {
                const inputValue = parseFloat($('#discount-percentage-input').val());
                
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('error', 'Please enter a valid positive number');
                    return;
                }
                
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }
                
                if (!decreaseModeActive && !increaseModeActive) {
                    showToast('error', 'Please activate Decrease or Increase mode first');
                    return;
                }
                
                const mode = increaseModeActive ? 'increase' : 'decrease';
                const discountType = $('#discount-type-select').val(); // Get selected type
                let successCount = 0;
                let errorCount = 0;
                let totalToProcess = selectedSkus.size;
                
                // Disable button during processing
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
                // Process each selected SKU
                selectedSkus.forEach(sku => {
                    // Try to find the row - it might be on a different page
                    let row = null;
                    table.getRows().forEach(r => {
                        if (r.getData()['(Child) sku'] === sku) {
                            row = r;
                        }
                    });
                    
                    if (row) {
                        const rowData = row.getData();
                        const originalPrice = parseFloat(rowData.price) || 0;
                        
                        if (originalPrice > 0) {
                            let newPrice;
                            
                            // Use selected type (percentage or value)
                            if (discountType === 'percentage') {
                                // Treat as percentage
                                const decimal = inputValue / 100;
                                if (mode === 'decrease') {
                                    newPrice = originalPrice * (1 - decimal);
                                } else {
                                    newPrice = originalPrice * (1 + decimal);
                                }
                            } else {
                                // Treat as fixed value
                                if (mode === 'decrease') {
                                    newPrice = Math.max(0.01, originalPrice - inputValue);
                                } else {
                                    newPrice = originalPrice + inputValue;
                                }
                            }

                            // Round to retail .99 endings
                            newPrice = roundToRetailPrice(newPrice);
                            
                            // Update SPRICE via AJAX
                            $.ajax({
                                url: '/save-amazon-sprice',
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    sku: sku,
                                    sprice: newPrice.toFixed(2)
                                },
                                success: function(response) {
                                    successCount++;
                                    
                                    // Always update SPRICE with the new value, let the formatter decide display
                                    const updateData = {
                                        'SPRICE': newPrice.toFixed(2), // Always save the new price
                                        'has_custom_sprice': true,
                                        'SPRICE_STATUS': null // Reset status so formatter shows/hides based on price match
                                    };
                                    
                                    if (response.sgpft_percent !== undefined) {
                                        updateData['SGPFT'] = response.sgpft_percent;
                                    }
                                    if (response.spft_percent !== undefined) {
                                        updateData['Spft%'] = response.spft_percent;
                                    }
                                    if (response.sroi_percent !== undefined) {
                                        updateData['SROI'] = response.sroi_percent;
                                    }
                                    
                                    // Update row with all data at once
                                    row.update(updateData);
                                    
                                    // Force redraw of the entire row to ensure all formatters run
                                    row.reformat();
                                    
                                    // Check if all requests are complete
                                    if (successCount + errorCount === totalToProcess) {
                                        const actionText = mode === 'increase' ? 'Increase' : 'Discount';
                                        $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                        if (errorCount === 0) {
                                            showToast('success', `${actionText} applied successfully to ${successCount} SKU${successCount > 1 ? 's' : ''}`);
                                        } else {
                                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                                        }
                                    }
                                },
                                error: function(xhr) {
                                    errorCount++;
                                    if (successCount + errorCount === totalToProcess) {
                                        const actionText = mode === 'increase' ? 'Increase' : 'Discount';
                                        $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                        showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                                    }
                                }
                            });
                        } else {
                            errorCount++;
                            if (successCount + errorCount === totalToProcess) {
                                const actionText = mode === 'increase' ? 'Increase' : 'Discount';
                                $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                            }
                        }
                    } else {
                        errorCount++;
                        if (successCount + errorCount === totalToProcess) {
                            const actionText = mode === 'increase' ? 'Increase' : 'Discount';
                            $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                        }
                    }
                });
            });

            // Allow Enter key to apply discount
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    $('#apply-discount-btn').click();
                }
            });

            // Apply Price to Amazon button - delegated event handler
            $(document).on('click', '.apply-price-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Apply button clicked'); // Debug log
                
                const $btn = $(this);
                const sku = $btn.attr('data-sku') || $btn.data('sku');
                const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                
                console.log('SKU:', sku, 'Price:', price); // Debug log
                
                if (!sku || !price || price <= 0 || isNaN(price)) {
                    console.error('Invalid SKU or price:', {sku, price}); // Debug log
                    showToast('error', 'Invalid SKU or price');
                    return;
                }
                
                // Disable button and show loading state
                $btn.prop('disabled', true);
                const originalHtml = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
                // Call the API to update Amazon price
                $.ajax({
                    url: '/apply-amazon-price',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        price: price
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        
                        // Check for errors in response (matching FBA pattern)
                        if (response.errors && response.errors.length > 0) {
                            $btn.html(originalHtml);
                            const errorMsg = response.errors[0].message || 'Failed to apply price to Amazon';
                            showToast('error', errorMsg);
                        } else {
                            // Success - no errors
                            showToast('success', `Price $${price.toFixed(2)} applied successfully to Amazon for SKU: ${sku}`);
                            // Update button to show success state
                            $btn.removeClass('btn-success').addClass('btn-secondary');
                            $btn.html('<i class="fas fa-check-circle"></i> Applied');
                            setTimeout(() => {
                                $btn.removeClass('btn-secondary').addClass('btn-success');
                                $btn.html(originalHtml);
                            }, 3000);
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false);
                        $btn.html(originalHtml);
                        
                        let errorMsg = 'Failed to apply price to Amazon';
                        if (xhr.responseJSON) {
                            // Check for error field first (matching FBA pattern)
                            errorMsg = xhr.responseJSON.error || xhr.responseJSON.message || errorMsg;
                        } else if (xhr.responseText) {
                            try {
                                const errorData = JSON.parse(xhr.responseText);
                                errorMsg = errorData.error || errorData.message || errorMsg;
                            } catch (e) {
                                errorMsg = xhr.responseText.substring(0, 100);
                            }
                        }
                        
                        showToast('error', errorMsg);
                        console.error('Apply price error:', xhr);
                    }
                });
            });

            // Apply All Prices button - delegated event handler
            $(document).on('click', '.apply-all-prices-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Apply All button clicked via delegated handler'); // Debug log
                
                // Call the global function
                if (typeof applyAllSelectedPrices === 'function') {
                    applyAllSelectedPrices();
                } else {
                    console.error('applyAllSelectedPrices function not found');
                    showToast('error', 'Apply All function not available');
                }
            });

            // SKU chart days filter
            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                if (currentSku) {
                    if (skuMetricsChart) {
                        skuMetricsChart.options.plugins.title.text = `Amazon Metrics (${days} Days)`;
                        skuMetricsChart.update();
                    }
                    loadSkuMetricsData(currentSku, days);
                }
            });
            let campaignTotals = {
                kw_spend_L30: 0,
                pt_spend_L30: 0,
                hl_spend_L30: 0
            };

            table = new Tabulator("#amazon-table", {
                ajaxURL: "/amazon-data-json",
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                columnCalcs: "both",
                initialSort: [{
                    column: "CVR_L30",
                    dir: "asc"
                }],
                ajaxResponse: function(url, params, response) {
                    // Extract campaign totals from response
                    if (response.campaign_totals) {
                        campaignTotals = response.campaign_totals;
                    }
                    // Return only the data array to Tabulator
                    return response.data || response;
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent_summary === true) {
                        row.getElement().style.backgroundColor = "#bde0ff";
                        row.getElement().style.fontWeight = "bold";
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [
                    // Row selection checkbox column
                    {
                        title: "<div style='transform: rotate(0deg) !important; display: flex; justify-content: center; align-items: center;'><input type='checkbox' id='select-all-rows' title='Select All' style='transform: rotate(0deg) !important; width: 16px; height: 16px; cursor: pointer;'></div>",
                        field: "row_select",
                        hozAlign: "center",
                        headerSort: false,
                        headerVertical: false,
                        width: 40,
                        frozen: true,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sku = row['(Child) sku'] || '';
                            return "<input type='checkbox' class='row-select-checkbox' data-sku='" + sku + "' style='width: 16px; height: 16px; cursor: pointer;'>";
                        },
                        cellClick: function(e, cell) {
                            // Prevent row click event
                            e.stopPropagation();
                        }
                    },
                    // {
                    //     title: "Parent",
                    //     field: "Parent",
                    //     headerFilter: "input",
                    //     headerFilterPlaceholder: "Search Parent...",
                    //     cssClass: "text-primary",
                    //     tooltip: true,
                    //     frozen: true,
                    //     width: 150,
                    //     visible: false
                    // },

                    {
                        title: "Image",
                        field: "image_path",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const imagePath = cell.getValue();
                            if (imagePath) {
                                return `<img src="${imagePath}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" />`;
                            }
                            return '';
                        },
                        width: 80
                    },

                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Don't show copy button for parent rows
                            if (rowData.is_parent_summary) {
                                return `<span style="font-weight: bold;">${sku}</span>`;
                            }

                            return `<div style="display: flex; align-items: center; gap: 5px;">
                                <span>${sku}</span>
                                <button class="btn btn-sm btn-link copy-sku-btn p-0" data-sku="${sku}" title="Copy SKU">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;">
                                    <i class="fa fa-info-circle"></i>
                                </button>
                            </div>`;
                        },
                     
                    },
                    {
                        title: "CVR L30",
                        field: "CVR_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const aL30 = parseFloat(row['A_L30']) || 0;
                            const sess30 = parseFloat(row['Sess30']) || 0;

                            if (sess30 === 0) return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';

                            const cvr = (aL30 / sess30) * 100;
                            let color = '';
                            
                            // getCvrColor logic from inc/dec page (same as eBay)
                            if (cvr <= 4) color = '#a00211'; // red
                            else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                            else if (cvr > 7 && cvr <= 10) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcCVR = (row) => {
                                const aL30 = parseFloat(row['A_L30']) || 0;
                                const sess30 = parseFloat(row['Sess30']) || 0;
                                return sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                            };
                            return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                        },
                        width: 65
                    },
                    {
                        title: "CVR L7",
                        field: "CVR_L7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const aL7 = parseFloat(row['A_L7']) || 0;
                            const sess7 = parseFloat(row['Sess7']) || 0;

                            if (sess7 === 0) return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';

                            const cvr = (aL7 / sess7) * 100;
                            let color = '';
                            
                            if (cvr <= 4) color = '#a00211'; // red
                            else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                            else if (cvr > 7 && cvr <= 10) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcCVR = (row) => {
                                const aL7 = parseFloat(row['A_L7']) || 0;
                                const sess7 = parseFloat(row['Sess7']) || 0;
                                return sess7 === 0 ? 0 : (aL7 / sess7) * 100;
                            };
                            return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                        },
                        width: 60
                    },
                    {
                        title: "CVR L60",
                        field: "CVR_L60",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const aL60 = parseFloat(row['units_ordered_l60']) || 0;
                            const sess60 = parseFloat(row['sessions_l60']) || 0;

                            if (sess60 === 0) return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';

                            const cvr = (aL60 / sess60) * 100;
                            let color = '';
                            
                            if (cvr <= 4) color = '#a00211'; // red
                            else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                            else if (cvr > 7 && cvr <= 10) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcCVR = (row) => {
                                const aL60 = parseFloat(row['units_ordered_l60']) || 0;
                                const sess60 = parseFloat(row['sessions_l60']) || 0;
                                return sess60 === 0 ? 0 : (aL60 / sess60) * 100;
                            };
                            return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                        },
                        width: 65
                    },
                    {
                        title: "NR/RL",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();

                            // Empty for parent rows
                            if (row.is_parent_summary) return '';

                            const nrl = row['NR'] || '';
                            const sku = row['(Child) sku'];

                            // Determine current value (default to RL if empty)
                            let value = '';
                            if (nrl === 'NR') {
                                value = 'NR';
                            } else if (nrl === 'REQ') {
                                value = 'REQ';
                            } else {
                                value = 'REQ'; // Default to REQ
                            }

                            return `<select class="form-select form-select-sm nr-select" data-sku="${sku}"
                                style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px; color: black; font-weight: bold;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''} style="color: black;">🟢</option>
                                <option value="NR" ${value === 'NR' ? 'selected' : ''} style="color: black;">🔴</option>
                            </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 60
                    },
                    {
                        title: "Missing L",
                        field: "is_missing",
                        hozAlign: "center",
                        width: 65,
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            
                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';
                            
                            const inv = parseFloat(rowData.INV) || 0;
                            const nrValue = rowData.NR || '';
                            const isMissingAmazon = rowData.is_missing_amazon || false;
                            
                            // Only check for INV > 0 and NR = REQ
                            if (inv > 0 && nrValue === 'REQ') {
                                if (isMissingAmazon) {
                                    return `<span style="font-size: 16px; color: #dc3545; font-weight: bold;">M</span>`;
                                }
                            }
                            
                            return '';
                        }
                    },

                    {
                        title: "Map",
                        field: "inv_map",
                        hozAlign: "center",
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            
                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';
                            
                            const inv = parseFloat(rowData.INV) || 0;
                            const nrValue = rowData.NR || '';
                            const isMissingAmazon = rowData.is_missing_amazon || false;
                            
                            // Only show for INV > 0 and NR = REQ
                            if (inv <= 0 || nrValue !== 'REQ') return '';
                            
                            // If item is missing from Amazon, leave Map blank
                            if (isMissingAmazon) return '';
                            
                            const invAmz = parseFloat(rowData.INV_AMZ) || 0;
                            const difference = Math.abs(inv - invAmz);
                            
                            if (difference === 0) {
                                // Perfect match - show green dot
                                return `<span style="font-size: 20px; color: #28a745;">🟢</span>`;
                            } else {
                                // Not matching - show red dot with difference count
                                return `<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                    <span style="font-size: 16px; color: #dc3545;">🔴</span>
                                    <span style="font-size: 11px; color: #dc3545; font-weight: 600;">${Math.round(difference)}</span>
                                </div>`;
                            }
                        }
                    },
                    {
                        title: "Rating",
                        field: "rating",
                        hozAlign: "center",
                        headerSort: true,
                        tooltip: "Rating and Reviews from Jungle Scout",
                        formatter: function(cell) {
                            const rating = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const reviews = rowData.reviews || 0;
                            
                            if (!rating || rating === 0) {
                                return '<span style="color: #6c757d;">-</span>';
                            }
                            
                            // Use same colors as rating filter
                            let ratingColor = '';
                            const ratingVal = parseFloat(rating);
                            if (ratingVal < 3) ratingColor = '#a00211'; // red
                            else if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107'; // yellow
                            else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc'; // blue
                            else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745'; // green
                            else ratingColor = '#e83e8c'; // pink (>4.5)
                            
                            const reviewColor = reviews < 4 ? '#a00211' : '#6c757d';
                            const fontWeight = '600';
                            
                            return `<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                <span style="color: ${ratingColor}; font-weight: ${fontWeight};">
                                    <i class="fa fa-star"></i> ${parseFloat(rating).toFixed(1)}
                                </span>
                                <span style="font-size: 11px; color: ${reviewColor}; font-weight: ${fontWeight};">
                                    ${parseInt(reviews).toLocaleString()} reviews
                                </span>
                            </div>`;
                        },
                        width: 80
                    },
                    {
                        title: "Links",
                        field: "links_column",
                        frozen: true,
                        width: 100,
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['buyer_link'] || '';
                            const sellerLink = rowData['seller_link'] || '';
                            
                            let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';
                            
                            if (sellerLink) {
                                html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> S Link
                                </a>`;
                            }
                            
                            if (buyerLink) {
                                html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> B Link
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
                        title: "INV AMZ",
                        field: "INV_AMZ",
                        hozAlign: "center",
                        width: 65,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const rowData = cell.getRow().getData();
                            const shopifyInv = parseFloat(rowData.INV) || 0;
                            
                            // Color logic: Green if matches, Red if different by >3, Yellow if different by <=3
                            let color = '';
                            const difference = Math.abs(value - shopifyInv);
                            
                            if (difference === 0) {
                                color = '#28a745'; // Green - exact match
                            } else if (difference <= 3) {
                                color = '#ffc107'; // Yellow - small difference
                            } else {
                                color = '#dc3545'; // Red - large difference
                            }
                            
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                        }
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
                        title: "A L30",
                        field: "A_L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },
                    {
                        title: "A DIL %",
                        field: "A DIL %",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const data = cell.getRow().getData();
                            const al30 = parseFloat(data.A_L30);
                            const inv = parseFloat(data.INV);
                            if (!isNaN(al30) && !isNaN(inv) && inv !== 0) {
                                const dilPercent = (al30 / inv) * 100;
                                let color = '';
                                // Color logic from DIL column
                                if (dilPercent < 16.66) color = '#a00211';
                                else if (dilPercent >= 16.66 && dilPercent < 25) color = '#ffc107';
                                else if (dilPercent >= 25 && dilPercent < 50) color = '#28a745';
                                else color = '#e83e8c';
                                return `<span style="color: ${color}; font-weight: 600;">${Math.round(dilPercent)}%</span>`;
                            }
                            return '<span style="color: #6c757d;">0%</span>';
                        }
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData()['(Child) sku'];
                            const value = cell.getValue() || "REQ";

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                                </select>
                            `;
                        }
                    },
                    {
                        title: "KW NRA",
                        field: "NRA",
                        visible: false,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData()['(Child) sku'];
                            const rowData = row.getData();
                            // If NRL is 'NRL' (red dot), default to NRA, otherwise default to RA
                            const nrlValue = rowData.NRL || "REQ";
                            const defaultValue = (nrlValue === 'NRL') ? "NRA" : "RA";
                            const value = (cell.getValue()?.trim()) || defaultValue;

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                </select>
                            `;
                        }
                    },

                    {
                        title: "A L7",
                        field: "A_L7",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },

                    {
                        title: "A L60",
                        field: "units_ordered_l60",
                        hozAlign: "center",
                        width: 55,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },

                    {
                        title: "View L30",
                        field: "Sess30",
                        hozAlign: "center",
                        sorter: "number",
                        width: 55,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },

                    {
                        title: "View L7",
                        field: "Sess7",
                        hozAlign: "center",
                        sorter: "number",
                        width: 50,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },

                    {
                        title: "View L60",
                        field: "sessions_l60",
                        hozAlign: "center",
                        sorter: "number",
                        width: 60,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return Math.round(value || 0);
                        }
                    },

                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary || !value) return '';

                            const price = parseFloat(value);
                            const lmpPrice = parseFloat(rowData.lmp_price || 0);
                            const priceFormatted = '$' + price.toFixed(2);
                            
                            // Color red if price > lmp_price
                            if (lmpPrice > 0 && price > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 600;">${priceFormatted}</span>`;
                            }
                            
                            return priceFormatted;
                        },
                        sorter: "number",
                        width: 70
                    },

                    {
                        title: "GPFT %",
                        field: "GPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const percent = parseFloat(value) || 0;
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
                        title: "GROI%",
                        field: "ROI_percentage",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            let color = '';
                            
                            // getRoiColor logic from inc/dec page (same as eBay)
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 65
                    },

                    {
                        title: "NROI%",
                        field: "NROI",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const roi = parseFloat(rowData.ROI_percentage) || 0;
                            const adSpend = parseFloat(rowData.AD_Spend_L30) || 0;
                            const price = parseFloat(rowData.price) || 0;
                            const aL30 = parseFloat(rowData.A_L30) || 0;
                            const totalSales = price * aL30;
                            const tcos = totalSales > 0 ? (adSpend / totalSales) * 100 : 0;
                            
                            // NROI% = GROI% - TCOS%
                            const nroi = roi - tcos;
                            
                            let color = '';
                            if (nroi < 50) color = '#a00211'; // red
                            else if (nroi >= 50 && nroi < 75) color = '#ffc107'; // yellow
                            else if (nroi >= 75 && nroi <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${nroi.toFixed(0)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcNROI = (row) => {
                                const roi = parseFloat(row.ROI_percentage) || 0;
                                const adSpend = parseFloat(row.AD_Spend_L30) || 0;
                                const price = parseFloat(row.price) || 0;
                                const aL30 = parseFloat(row.A_L30) || 0;
                                const totalSales = price * aL30;
                                const tcos = totalSales > 0 ? (adSpend / totalSales) * 100 : 0;
                                return roi - tcos;
                            };
                            return calcNROI(aRow.getData()) - calcNROI(bRow.getData());
                        },
                        width: 65
                    },

                    {
                        title: "TACOS",
                        field: "AD%",
                        hozAlign: "center",
                        visible: false,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const adSpend = parseFloat(rowData.AD_Spend_L30) || 0;
                            const sales = parseFloat(rowData['A_L30']) || 0;
                            
                            // If there is ad spend but no sales, show 100%
                            if (adSpend > 0 && sales === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">100%</span>`;
                            }
                            
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '0.00%';
                            
                            // If spend > 0 but AD% is 0, show red alert
                            if (adSpend > 0 && percent === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                            }
                            
                            return `${parseFloat(value).toFixed(0)}%`;
                        },
                        width: 55
                    },
                    {
                        title: "KW ACOS",
                        field: "acos",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend30 = parseFloat(row.l30_spend || 0);
                            var sales30 = parseFloat(row.l30_sales || 0);
                            var acosRaw = row.acos; 
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            // ACOS must be 0 when Spend L30 and Sales L30 are both 0
                            if (spend30 === 0 && sales30 === 0) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            // Build tooltip with L30 and L7 stats (same as KW page)
                            var clicks30 = parseInt(row.l30_clicks || 0).toLocaleString();
                            var spend30Display = parseFloat(row.l30_spend || 0).toFixed(0);
                            var sales30Display = parseFloat(row.l30_sales || 0).toFixed(0);
                            var adSold30 = parseInt(row.l30_purchases || 0).toLocaleString();
                            var clicks7 = parseInt(row.l7_clicks || 0).toLocaleString();
                            var spend7 = parseFloat(row.l7_spend || 0).toFixed(2);
                            var sales7 = parseFloat(row.l7_sales || 0).toFixed(2);
                            var adSold7 = parseInt(row.l7_purchases || 0).toLocaleString();
                            var tooltipText = "L30: Clicks " + clicks30 + ", Spend " + spend30Display + ", Sales " + sales30Display + ", Ad Sold " + adSold30 +
                                "\nL7: Clicks " + clicks7 + ", Spend " + spend7 + ", Sales " + sales7 + ", Ad Sold " + adSold7 +
                                "\n(Click info to show/hide Clicks L7, Spend L7, Sales L7, Ad Sold L7 and L30 columns)";
                            
                            var acosDisplay;
                            if (acos === 0) {
                                acosDisplay = "0%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            }
                            return `<div class="text-center">${acosDisplay}<i class="fas fa-info-circle ms-1 info-icon-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        },
                        sorter: "number"
                    },

                    {
                        title: "KW BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return Math.round(value);
                        }
                    },
                    {
                        title: "KW SBGT",
                        field: "sbgt",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 60,
                        mutator: function (value, data) {
                            var acos = parseFloat(data.acos || data.ACOS || 0);
                            var price = parseFloat(data.price || 0);
                            var sbgt;
                            // Same price-based SBGT for all rows (parent now has avg price)
                            if (acos > 20) {
                                sbgt = 1;
                            } else {
                                sbgt = Math.ceil(price * 0.10);
                                if (sbgt < 1) sbgt = 1;
                                if (sbgt > 5) sbgt = 5;
                            }
                            return sbgt;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var value = cell.getValue();
                            if (value === undefined || value === null) return '-';
                            return value;
                        }
                    },
                    {
                        title: "Clicks L7",
                        field: "l7_clicks",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 80,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            var tooltipL7 = "Click info to show/hide Spend L7, Sales L7, Ad Sold L7";
                            return value.toLocaleString() + '<i class="fas fa-info-circle ms-1 info-icon-l7-toggle" style="cursor: pointer; color: #0d6efd;" title="' + tooltipL7 + '"></i>';
                        },
                        sorter: "number"
                    },
                    {
                        title: "Spend L7",
                        field: "spend_l7_col",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 75,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "Sales L7",
                        field: "l7_sales",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 70,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "Ad Sold L7",
                        field: "l7_purchases",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "Clicks L30",
                        field: "l30_clicks",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "Spend L30",
                        field: "l30_spend",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 80,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(0);
                        }
                    },
                    {
                        title: "Sales L30",
                        field: "l30_sales",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 75,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(0);
                        }
                    },
                    {
                        title: "Ad Sold L30",
                        field: "l30_purchases",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 90,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "KW AD CVR",
                        field: "ad_cvr",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(1) + "%";
                        },
                        sorter: "number",
                        width: 90
                    },

                    // PT Ads specific columns
                    {
                        title: "PT ACOS",
                        field: "pt_acos",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend30 = parseFloat(row.pt_spend_L30 || 0);
                            var sales30 = parseFloat(row.pt_sales_L30 || 0);
                            var acos = sales30 > 0 ? (spend30 / sales30) * 100 : 0;
                            
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            // Build tooltip with PT L30 and L7 stats
                            var clicks30 = parseInt(row.pt_clicks_L30 || 0).toLocaleString();
                            var spend30Display = parseFloat(row.pt_spend_L30 || 0).toFixed(0);
                            var sales30Display = parseFloat(row.pt_sales_L30 || 0).toFixed(0);
                            var adSold30 = parseInt(row.pt_sold_L30 || 0).toLocaleString();
                            var clicks7 = parseInt(row.pt_clicks_L7 || 0).toLocaleString();
                            var spend7 = parseFloat(row.pt_spend_L7 || 0).toFixed(2);
                            var sales7 = parseFloat(row.pt_sales_L7 || 0).toFixed(2);
                            var adSold7 = parseInt(row.pt_sold_L7 || 0).toLocaleString();
                            var tooltipText = "L30: Clicks " + clicks30 + ", Spend " + spend30Display + ", Sales " + sales30Display + ", Ad Sold " + adSold30 +
                                "\nL7: Clicks " + clicks7 + ", Spend " + spend7 + ", Sales " + sales7 + ", Ad Sold " + adSold7 +
                                "\n(Click info to show/hide PT detail columns)";
                            
                            var acosDisplay;
                            if (acos === 0) {
                                acosDisplay = "0%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            }
                            return `<div class="text-center">${acosDisplay}<i class="fas fa-info-circle ms-1 pt-info-icon-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        },
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aSpend = parseFloat(aData.pt_spend_L30 || 0);
                            var aSales = parseFloat(aData.pt_sales_L30 || 0);
                            var bSpend = parseFloat(bData.pt_spend_L30 || 0);
                            var bSales = parseFloat(bData.pt_sales_L30 || 0);
                            var aAcos = aSales > 0 ? (aSpend / aSales) * 100 : 0;
                            var bAcos = bSales > 0 ? (bSpend / bSales) * 100 : 0;
                            return aAcos - bAcos;
                        }
                    },
                    {
                        title: "PT BGT",
                        field: "pt_campaignBudgetAmount",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = parseFloat(row.pt_campaignBudgetAmount || row.campaignBudgetAmount || 0);
                            return Math.round(value);
                        }
                    },
                    {
                        title: "PT SBGT",
                        field: "pt_sbgt",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 60,
                        mutator: function (value, data) {
                            var spend = parseFloat(data.pt_spend_L30 || 0);
                            var sales = parseFloat(data.pt_sales_L30 || 0);
                            var acos = sales > 0 ? (spend / sales) * 100 : 0;
                            var price = parseFloat(data.price || 0);
                            var sbgt;
                            if (acos > 20) {
                                sbgt = 1;
                            } else {
                                sbgt = Math.ceil(price * 0.10);
                                if (sbgt < 1) sbgt = 1;
                                if (sbgt > 5) sbgt = 5;
                            }
                            return sbgt;
                        },
                        formatter: function(cell) {
                            return cell.getValue();
                        }
                    },
                    {
                        title: "PT Clicks L7",
                        field: "pt_clicks_L7",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number"
                    },
                    {
                        title: "PT Spend L7",
                        field: "pt_spend_L7",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "PT Sales L7",
                        field: "pt_sales_L7",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "PT Sold L7",
                        field: "pt_sold_L7",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 85,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "PT Clicks L30",
                        field: "pt_clicks_L30",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 90,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            var tooltipL7 = "Click info to show/hide PT L7 columns";
                            return value.toLocaleString() + '<i class="fas fa-info-circle ms-1 pt-info-icon-l7-toggle" style="cursor: pointer; color: #0d6efd;" title="' + tooltipL7 + '"></i>';
                        },
                        sorter: "number"
                    },
                    {
                        title: "PT Spend L30",
                        field: "pt_spend_L30",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 90,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(0);
                        }
                    },
                    {
                        title: "PT Sales L30",
                        field: "pt_sales_L30",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 90,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return '$' + value.toFixed(0);
                        }
                    },
                    {
                        title: "PT Sold L30",
                        field: "pt_sold_L30",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 90,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "PT AD CVR",
                        field: "pt_ad_cvr",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            var cvr = parseFloat(row.pt_ad_cvr || 0);
                            return cvr.toFixed(1) + "%";
                        },
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aCvr = parseFloat(aData.pt_ad_cvr || 0);
                            var bCvr = parseFloat(bData.pt_ad_cvr || 0);
                            return aCvr - bCvr;
                        },
                        width: 90
                    },
                    {
                        title: "PT 7 UB%",
                        field: "pt_7ub",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            var l7_spend = parseFloat(row.pt_spend_L7 || 0);
                            var budget = parseFloat(row.pt_campaignBudgetAmount || 0);
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 66) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "PT 1 UB%",
                        field: "pt_1ub",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            // Use pt_spend_L1 directly from backend
                            var l1_spend = parseFloat(row.pt_spend_L1 || 0);
                            var budget = parseFloat(row.pt_campaignBudgetAmount || 0);
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 66 && ub1 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 66) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "PT AVG CPC",
                        field: "pt_avg_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            // Use pt_avg_cpc directly from backend
                            var avg_cpc = parseFloat(row.pt_avg_cpc || 0);
                            return avg_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "PT L7 CPC",
                        field: "pt_l7_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            // Use pt_l7_cpc directly from backend
                            var l7_cpc = parseFloat(row.pt_l7_cpc || 0);
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "PT L1 CPC",
                        field: "pt_l1_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            // Use pt_l1_cpc directly from backend
                            var l1_cpc = parseFloat(row.pt_l1_cpc || 0);
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "PT Last SBID",
                        field: "pt_last_sbid",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 80,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            return cell.getValue() || '-';
                        }
                    },
                    {
                        title: "PT SBID",
                        field: "pt_sbid",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            
                            // Calculate SBID same as amazon-utilized-pt page
                            var l1_cpc = parseFloat(row.pt_l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.pt_l7_cpc) || 0;
                            var avg_cpc = parseFloat(row.pt_avg_cpc) || 0;
                            var budget = parseFloat(row.pt_campaignBudgetAmount) || 0;
                            var l7_spend = parseFloat(row.pt_spend_L7) || 0;
                            var l1_spend = parseFloat(row.pt_spend_L1) || 0;
                            var price = parseFloat(row.price) || 0;
                            
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            
                            var sbid = 0;
                            
                            // Determine utilization type
                            var rowUtilizationType = 'all';
                            if (ub7 > 99 && ub1 > 99) {
                                rowUtilizationType = 'over';
                            } else if (ub7 < 66 && ub1 < 66) {
                                rowUtilizationType = 'under';
                            } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                                rowUtilizationType = 'correctly';
                            }
                            
                            // Special case: If UB7 and UB1 = 0%, use price-based default
                            if (ub7 === 0 && ub1 === 0) {
                                if (price < 50) {
                                    sbid = 0.50;
                                } else if (price >= 50 && price < 100) {
                                    sbid = 1.00;
                                } else if (price >= 100 && price < 200) {
                                    sbid = 1.50;
                                } else {
                                    sbid = 2.00;
                                }
                            } else if (rowUtilizationType === 'over') {
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else if (avg_cpc > 0) {
                                    sbid = Math.floor(avg_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            } else if (rowUtilizationType === 'under') {
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                } else if (avg_cpc > 0) {
                                    sbid = Math.floor(avg_cpc * 1.10 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            }
                            
                            // Apply price-based caps
                            if (price < 10 && sbid > 0.10) {
                                sbid = 0.10;
                            } else if (price >= 10 && price < 20 && sbid > 0.20) {
                                sbid = 0.20;
                            }
                            
                            return sbid === 0 ? '-' : sbid.toFixed(2);
                        }
                    },
                    {
                        title: "PT SBID M",
                        field: "pt_sbid_m",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            return cell.getValue() || '-';
                        }
                    },
                    {
                        title: "PT APR BID",
                        field: "pt_apr_bid",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 80,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0;
                            if (!hasCampaign) return '-';
                            return cell.getValue() || '-';
                        }
                    },

                    {
                        title: "AD SALES L30",
                        field: "SALES_L30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            if (value === 0) return '';
                            return `<span>${value.toFixed(0)}</span>`;
                        },
                        width: 85
                    },

                     {
                        title: "PFT %",
                        field: "PFT%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            let color = '';
                            
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 50
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';

                            const lmpPrice = cell.getValue();
                            const lmpEntries = rowData.lmp_entries || [];
                            const sku = rowData['(Child) sku'];
                            const totalCompetitors = rowData.lmp_entries_total || 0;

                            if (!lmpPrice && totalCompetitors === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                            
                            // Show lowest price OUTSIDE modal
                            if (lmpPrice) {
                                const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                                const currentPrice = parseFloat(rowData.price || 0);
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
                        width: 100
                    },
                    {
                        title: "Select",
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <span>Select</span>
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Visible">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            
                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';
                            
                            const sku = rowData['(Child) sku'];
                            const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked} style="cursor: pointer;">`;
                        },
                        width: 60
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
                            const currentPrice = parseFloat(rowData.price) || 0;
                            const sprice = parseFloat(value) || 0;
                            
                            if (!value) return '';
                            
                            // ONLY condition: Show blank if price and SPRICE match
                            if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) {
                                return '';
                            }
                            
                            // Show SPRICE when it's different from current price
                            const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                            
                            // If using default price (not custom), show in blue
                            if (hasCustomSprice === false) {
                                return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                            }
                            
                            return formattedValue;
                        },
                        width: 80
                    },
                    {
                        title: "Accept",
                        field: "_accept",
                        hozAlign: "center",
                        headerSort: false,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                                <span>Accept</span>
                                <button type="button" class="btn btn-sm apply-all-prices-btn" title="Apply All Selected Prices to Amazon" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;" onclick="event.stopPropagation(); if(typeof applyAllSelectedPrices === 'function') { applyAllSelectedPrices(); }">
                                    <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                </button>
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            
                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';
                            
                            const sku = rowData['(Child) sku'];
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            const status = rowData.SPRICE_STATUS || null;
                            
                            if (!sprice || sprice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }
                            
                            // Determine icon and color based on status
                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745'; // Green for apply
                            let titleText = 'Apply Price to Amazon';
                            
                            if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green
                                titleText = 'Price pushed to Amazon (Double-click to mark as Applied)';
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green
                                titleText = 'Price applied to Amazon (Double-click to change)';
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545'; // Red
                                titleText = 'Error applying price to Amazon';
                            } else if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107'; // Yellow
                                titleText = 'Price pushing in progress...';
                            }
                            
                            // Show only icon with color, no background
                            return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                                ${icon}
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            // Handle button click directly in cellClick
                            const $target = $(e.target);
                            if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                                
                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('error', 'Invalid SKU or price');
                                    return;
                                }
                                
                                // Disable button and show loading state (only clock icon)
                                $btn.prop('disabled', true);
                                // Ensure circular styling
                                $btn.css({
                                    'border-radius': '50%',
                                    'width': '35px',
                                    'height': '35px',
                                    'padding': '0',
                                    'display': 'flex',
                                    'align-items': 'center',
                                    'justify-content': 'center'
                                });
                                $btn.html('<i class="fas fa-clock fa-spin" style="color: black;"></i>');
                                
                                // Use retry function
                                applyPriceWithRetry(sku, price, cell, 5, 5000)
                                    .then((result) => {
                                        // Success - update row data with pushed status
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'pushed';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        // Show green tick icon in circular button
                                        $btn.html('<i class="fas fa-check-circle" style="color: black; font-size: 1.1em;"></i>');
                                    })
                                    .catch((error) => {
                                        // Update row data with error status
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'error';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        // Show error icon in circular button
                                        $btn.html('<i class="fas fa-times" style="color: black;"></i>');
                                        
                                        console.error('Apply price failed after retries:', error);
                                    });
                                return;
                            }
                            // Don't stop propagation for other clicks
                            e.stopPropagation();
                        },
                        cellDblClick: function(e, cell) {
                            // Handle double-click to manually set status to 'applied'
                            const $target = $(e.target);
                            if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const currentStatus = $btn.attr('data-status') || '';
                                
                                // Only allow setting to 'applied' if current status is 'pushed'
                                if (currentStatus === 'pushed') {
                                    $.ajax({
                                        url: '/update-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        data: {
                                            sku: sku,
                                            status: 'applied'
                                        },
                                        success: function(response) {
                                            // Update row data
                                            const row = cell.getRow();
                                            const rowData = row.getData();
                                            rowData.SPRICE_STATUS = 'applied';
                                            row.update(rowData);
                                            showToast('success', 'Status updated to Applied');
                                        },
                                        error: function(xhr) {
                                            showToast('error', 'Failed to update status');
                                        }
                                    });
                                } else if (currentStatus === 'applied') {
                                    // If already applied, show message
                                    showToast('info', 'Price is already marked as Applied');
                                } else {
                                    showToast('info', 'Please push the price first before marking as Applied');
                                }
                            }
                        },
                        width: 80
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
                        field: "Spft%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
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
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },

                    // KW Page Columns
                    {
                        title: "KW 7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 66) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "KW 1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 66 && ub1 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 66) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "KW AVG CPC",
                        field: "avg_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var avg_cpc = parseFloat(row.avg_cpc) || 0;
                            return avg_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "KW L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "KW L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "KW Last SBID",
                        field: "last_sbid",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        }
                    },
                    {
                        title: "KW SBID",
                        field: "sbid",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            
                            // Calculate SBID dynamically like KW page
                            var l1Cpc = parseFloat(row.l1_cpc) || 0;
                            var l7Cpc = parseFloat(row.l7_cpc) || 0;
                            var avgCpc = parseFloat(row.avg_cpc) || 0;
                            var price = parseFloat(row.price) || 0;
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            
                            // Calculate UB7 and UB1
                            var ub7 = 0, ub1 = 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                                ub1 = (parseFloat(row.l1_spend) || 0) / budget * 100;
                            }
                            
                            // Determine utilization type
                            var rowType = 'all';
                            if (ub7 > 99 && ub1 > 99) {
                                rowType = 'over';
                            } else if (ub7 < 66 && ub1 < 66) {
                                rowType = 'under';
                            } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                                rowType = 'correctly';
                            }
                            
                            var sbid = 0;
                            
                            // Special case: If UB7 and UB1 = 0%, use price-based default
                            if (ub7 === 0 && ub1 === 0) {
                                if (price < 50) {
                                    sbid = 0.50;
                                } else if (price >= 50 && price < 100) {
                                    sbid = 1.00;
                                } else if (price >= 100 && price < 200) {
                                    sbid = 1.50;
                                } else {
                                    sbid = 2.00;
                                }
                            } else if (rowType === 'over') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                                if (l1Cpc > 0) {
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                } else if (l7Cpc > 0) {
                                    sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                } else if (avgCpc > 0) {
                                    sbid = Math.floor(avgCpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            } else if (rowType === 'under') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                                if (l1Cpc > 0) {
                                    sbid = Math.floor(l1Cpc * 1.10 * 100) / 100;
                                } else if (l7Cpc > 0) {
                                    sbid = Math.floor(l7Cpc * 1.10 * 100) / 100;
                                } else if (avgCpc > 0) {
                                    sbid = Math.floor(avgCpc * 1.10 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            }
                            
                            // Apply price-based caps
                            if (price < 10 && sbid > 0.10) {
                                sbid = 0.10;
                            } else if (price >= 10 && price < 20 && sbid > 0.20) {
                                sbid = 0.20;
                            }
                            
                            if (sbid === 0) return '-';
                            return sbid.toFixed(2);
                        }
                    },
                    {
                        title: "KW SBID M",
                        field: "sbid_m",
                        hozAlign: "center",
                        visible: false,
                        minWidth: 72,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        }
                    },
                    {
                        title: "Active",
                        field: "active_toggle",
                        hozAlign: "center",
                        visible: false,
                        width: 80,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sku = row['(Child) sku'] || '';
                            var currentSection = $('#section-filter').val();
                            
                            // Check if has campaign - section-aware
                            var hasCampaign = false;
                            if (currentSection === 'pt-ads') {
                                // For PT Ads section, check PT campaign existence
                                hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0 || (row.pt_campaign_status && row.pt_campaign_status !== '');
                            } else {
                                // For KW Ads or other sections, check KW campaign existence
                                hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            }
                            
                            if (!hasCampaign) {
                                return '<span style="color: #999;">-</span>';
                            }
                            
                            // Section-aware campaign status check
                            var isEnabled = false;
                            if (currentSection === 'pt-ads') {
                                // PT Ads section: only check PT campaign status (same as amazon-utilized-pt page)
                                var ptStatus = (row.pt_campaign_status || '').toUpperCase();
                                isEnabled = ptStatus === 'ENABLED';
                            } else {
                                // KW Ads or default: check KW status
                                var kwStatus = (row.kw_campaign_status || row.campaignStatus || '').toUpperCase();
                                var ptStatus = (row.pt_campaign_status || '').toUpperCase();
                                isEnabled = kwStatus === 'ENABLED' || ptStatus === 'ENABLED';
                            }
                            
                            return `
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input campaign-status-toggle" 
                                           type="checkbox" 
                                           role="switch" 
                                           data-sku="${sku}"
                                           data-campaign-id="${row.campaign_id || ''}"
                                           ${isEnabled ? 'checked' : ''}
                                           style="cursor: pointer; width: 3rem; height: 1.5rem;">
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('campaign-status-toggle')) {
                                e.stopPropagation();
                            }
                        }
                    },
                    {
                        title: "Missing AD",
                        field: "missing_ad",
                        hozAlign: "center",
                        visible: false,
                        width: 60,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var currentSection = $('#section-filter').val();
                            
                            // First check NRA status (from KW NRA column)
                            var nraValue = (row.NRA || '').toString().trim();
                            if (!nraValue) {
                                var nrlValue = (row.NRL || 'REQ').toString().trim();
                                nraValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                            }
                            
                            // If NRA (red dot in KW NRA) - show Yellow dot
                            if (nraValue === 'NRA') {
                                return '<span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background-color: #ffc107;"></span>';
                            }
                            
                            // Check if has campaign - section-aware
                            var hasCampaign = false;
                            if (currentSection === 'pt-ads') {
                                // PT Ads: check PT campaign existence
                                hasCampaign = row.pt_campaignName || row.pt_spend_L30 > 0 || (row.pt_campaign_status && row.pt_campaign_status !== '');
                            } else {
                                // KW Ads or default: check KW campaign existence
                                hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            }
                            
                            if (hasCampaign) {
                                // Has campaign and not NRA - Green dot
                                return '<span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background-color: #28a745;"></span>';
                            } else {
                                // No campaign and not NRA - Missing - Red dot
                                return '<span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background-color: #dc3545;"></span>';
                            }
                        }
                    },
                    {
                        title: "KW APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sbidM = parseFloat(row.sbid_m) || 0;
                            var isApproved = row.sbid_approved || false;
                            var campaignId = row.campaign_id;
                            
                            if (!campaignId) {
                                return '<span style="color: #999;">-</span>';
                            }
                            
                            if (isApproved) {
                                return '<i class="fas fa-check-circle text-success apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="SBID Approved"></i>';
                            } else {
                                return '<i class="fas fa-check text-primary apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="Click to approve SBID"></i>';
                            }
                        },
                        cellClick: function(e, cell) {
                            var row = cell.getRow();
                            var rowData = row.getData();
                            var campaignId = rowData.campaign_id;
                            var sbidM = parseFloat(rowData.sbid_m) || 0;
                            
                            if (!campaignId || sbidM <= 0) {
                                alert('Please enter a valid SBID M value first');
                                return;
                            }
                            
                            // Show loading
                            cell.getElement().innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
                            
                            $.ajax({
                                url: '/approve-amazon-sbid',
                                method: 'POST',
                                data: {
                                    campaign_id: campaignId,
                                    sbid_m: sbidM,
                                    campaign_type: 'KW',
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function(response) {
                                    if (response.status === 200) {
                                        rowData.sbid_approved = true;
                                        row.update(rowData);
                                        cell.getElement().innerHTML = '<i class="fas fa-check-circle text-success apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="SBID Approved"></i>';
                                    } else {
                                        alert('Failed to approve SBID: ' + (response.message || 'Unknown error'));
                                        row.reformat();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    alert('Error approving SBID: ' + error);
                                    row.reformat();
                                }
                            });
                        }
                    },
                    {
                        title: "KW Campaign",
                        field: "campaignName",
                        visible: false,
                        minWidth: 220
                    },
                    {
                        title: "PT Campaign",
                        field: "pt_campaignName",
                        visible: false,
                        minWidth: 220
                    },
                    {
                        title: "KW Issue",
                        field: "target_kw_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "PT Issue",
                        field: "target_pt_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Variation",
                        field: "variation_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Wrong Prod.",
                        field: "incorrect_product_added",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "-ve KW",
                        field: "target_negative_kw_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Review Target",
                        field: "target_review_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "CVR Target",
                        field: "target_cvr_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Content",
                        field: "content_check",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Price Justify",
                        field: "price_justification_check",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Ad Not Req",
                        field: "ad_not_req",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Review",
                        field: "review_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Issue",
                        field: "issue_found",
                        hozAlign: "left",
                        visible: false
                    },
                    {
                        title: "Action",
                        field: "action_taken",
                        hozAlign: "left",
                        visible: false
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";
                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }
                            return `<span style="font-weight:600; color:${color};">${percent}%</span>`;
                        }
                    }
                ]
            });

            // NR select change handler
            $(document).on('change', '.nr-select', function() {
                const $select = $(this);
                const value = $select.val();
                const sku = $select.data('sku');

                // Save to database
                $.ajax({
                    url: '/listing_amazon/save-status',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        const message = response.message || 'NR updated successfully';
                        showToast('success', message);
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to update NR');
                    }
                });
            });

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                if (field === 'SPRICE') {
                    const sku = data['(Child) sku'];
                    $.ajax({
                        url: '/save-amazon-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: value
                        },
                        success: function(response) {
                            showToast('success', 'SPRICE updated successfully');
                            if (response.sgpft_percent !== undefined) {
                                row.update({
                                    'SGPFT': response.sgpft_percent
                                });
                            }
                            if (response.spft_percent !== undefined) {
                                row.update({
                                    'Spft%': response.spft_percent
                                });
                            }
                            if (response.sroi_percent !== undefined) {
                                row.update({
                                    'SROI': response.sroi_percent
                                });
                            }
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update SPRICE');
                        }
                    });
                } else if (field === 'Listed' || field === 'Live' || field === 'APlus') {
                    const sku = data['(Child) sku'];
                    $.ajax({
                        url: '/update-amazon-listed-live',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            field: field,
                            value: value ? 1 : 0
                        },
                        success: function(response) {
                            showToast('success', field + ' updated successfully');
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update ' + field);
                        }
                    });
                }
            });

            // Apply filters
            // UB Zone function for utilization type filtering
            function ubZone(ub) {
                if (ub >= 66 && ub <= 99) return 'g';
                if (ub > 99) return 'p';
                return 'r';
            }

            // Update utilization dropdown option counts
            // Uses getData() (all data) + manual filter checks - same approach as KW/PT utilized pages
            function updateUtilizationCounts() {
                if (!table) return;
                
                var currentSection = $('#section-filter').val();
                var comboLabels = {
                    'gg': 'Green+Green', 'gp': 'Green+Pink', 'gr': 'Green+Red',
                    'pg': 'Pink+Green', 'pp': 'Pink+Pink', 'pr': 'Pink+Red',
                    'rg': 'Red+Green', 'rp': 'Red+Pink', 'rr': 'Red+Red'
                };
                
                if (currentSection !== 'kw-ads' && currentSection !== 'pt-ads') {
                    // Reset counts when not in KW/PT section
                    $('#utilization-type-filter option').each(function() {
                        var val = $(this).val();
                        $(this).text(val === 'all' ? 'All' : (comboLabels[val] || val));
                    });
                    return;
                }
                
                // Use ALL data and manually apply filters (same approach as KW/PT utilized pages)
                // getData("all") returns ALL rows regardless of Tabulator filters
                var allData = table.getData("all");
                var comboCounts = {gg:0, gp:0, gr:0, pg:0, pp:0, pr:0, rg:0, rp:0, rr:0};
                
                // Read current filter values for manual filtering
                var invFilterVal = $('#inventory-filter').val() || '';
                var nraFilterVal = $('#nra-filter').val() || '';
                var campaignStatusVal = $('#campaign-status-filter').val() || '';
                var parentFilterVal = $('#parent-filter').val() || 'all';
                var searchVal = ($('#sku-search').val() || '').toLowerCase();
                
                allData.forEach(function(row) {
                    var sku = (row['(Child) sku'] || row.sku || '').toString();
                    var isParent = row.is_parent_summary === true;
                    
                    // Parent filter
                    if (parentFilterVal === 'parents' && !isParent) return;
                    if (parentFilterVal === 'skus' && isParent) return;
                    
                    // Search filter
                    if (searchVal) {
                        var skuLower = sku.toLowerCase();
                        var campName = '';
                        if (currentSection === 'pt-ads') {
                            campName = (row.pt_campaignName || '').toLowerCase();
                        } else {
                            campName = (row.campaignName || '').toLowerCase();
                        }
                        if (skuLower.indexOf(searchVal) === -1 && campName.indexOf(searchVal) === -1) return;
                    }
                    
                    // Inventory filter
                    var inv = parseFloat(row.INV) || 0;
                    if (invFilterVal === 'zero' && inv !== 0) return;
                    if (invFilterVal === 'more' && inv <= 0) return;
                    
                    // NRA filter (dropdown - not section-specific auto-hide)
                    if (nraFilterVal && nraFilterVal !== '') {
                        var rowNra = (row.NRA || '').toString().trim();
                        if (!rowNra) {
                            var nrlVal = (row.NRL || 'REQ').toString().trim();
                            rowNra = (nrlVal === 'NRL') ? 'NRA' : 'RA';
                        }
                        if (nraFilterVal === 'RA') {
                            if (rowNra === 'NRA') return;
                        } else {
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    
                    // Campaign status filter
                    if (campaignStatusVal && campaignStatusVal !== '' && campaignStatusVal !== 'ALL') {
                        if (!isParent) {
                            var csEnabled = false;
                            if (currentSection === 'pt-ads') {
                                csEnabled = (row.pt_campaign_status || '').toUpperCase() === 'ENABLED';
                            } else {
                                var ks = (row.kw_campaign_status || '').toUpperCase();
                                var ps = (row.pt_campaign_status || '').toUpperCase();
                                var gs = (row.campaignStatus || '').toUpperCase();
                                csEnabled = ks === 'ENABLED' || ps === 'ENABLED' || gs === 'ENABLED';
                            }
                            if (campaignStatusVal === 'ENABLED' && !csEnabled) return;
                            if (campaignStatusVal === 'PAUSED' && csEnabled) return;
                        }
                    }
                    
                    // Now check utilization eligibility (campaign + ENABLED + budget)
                    var hasCampaign, l7_spend, l1_spend, budget, campStatus;
                    
                    if (currentSection === 'pt-ads') {
                        hasCampaign = row.pt_campaignName || (row.pt_campaign_status && row.pt_campaign_status !== '') || parseFloat(row.pt_spend_L30) > 0 || parseFloat(row.pt_spend_L7) > 0 || parseFloat(row.pt_spend_L1) > 0;
                        if (!hasCampaign) return;
                        campStatus = (row.pt_campaign_status || '').toUpperCase();
                        if (campStatus !== 'ENABLED') return;
                        l7_spend = parseFloat(row.pt_spend_L7) || 0;
                        l1_spend = parseFloat(row.pt_spend_L1) || 0;
                        budget = parseFloat(row.pt_campaignBudgetAmount) || 0;
                    } else {
                        hasCampaign = row.campaignName || row.campaign_id || (row.kw_campaign_status && row.kw_campaign_status !== '') || parseFloat(row.l7_spend) > 0 || parseFloat(row.l1_spend) > 0;
                        if (!hasCampaign) return;
                        campStatus = (row.kw_campaign_status || row.campaignStatus || '').toUpperCase();
                        if (campStatus !== 'ENABLED') return;
                        l7_spend = parseFloat(row.l7_spend) || 0;
                        l1_spend = parseFloat(row.l1_spend) || 0;
                        budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                    }
                    
                    if (!(budget > 0) || isNaN(budget)) return;
                    
                    var ub7 = (l7_spend / (budget * 7)) * 100;
                    var ub1 = (l1_spend / budget) * 100;
                    var combo = ubZone(ub7) + ubZone(ub1);
                    
                    if (comboCounts.hasOwnProperty(combo)) {
                        comboCounts[combo]++;
                    }
                });
                
                // Update dropdown option text with counts
                $('#utilization-type-filter option').each(function() {
                    var val = $(this).val();
                    if (val === 'all') {
                        $(this).text('All');
                    } else if (comboLabels[val]) {
                        $(this).text(comboLabels[val] + ' (' + (comboCounts[val] || 0) + ')');
                    }
                });
            }

            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const dilFilter = $('#dil-filter').val();
                const ratingFilter = $('#rating-filter').val();
                const parentFilter = $('#parent-filter').val();
                const statusFilter = $('#status-filter').val();
                const soldFilter = $('#sold-filter').val();
                const rangeMin = parseFloat($('#range-min').val()) || null;
                const rangeMax = parseFloat($('#range-max').val()) || null;
                const rangeColumn = $('#range-column-select').val() || '';
                
                // New KW filters
                const utilizationTypeFilter = $('#utilization-type-filter').val();
                const campaignStatusFilter = $('#campaign-status-filter').val();
                const nraFilter = $('#nra-filter').val();
                const priceSlabFilter = $('#price-slab-filter').val();
                const acosSlabFilter = $('#acos-slab-filter').val();
                const ub7Min = parseFloat($('#7ub-min').val()) || null;
                const ub7Max = parseFloat($('#7ub-max').val()) || null;
                const ub1Min = parseFloat($('#1ub-min').val()) || null;
                const ub1Max = parseFloat($('#1ub-max').val()) || null;
                const acosRangeMin = parseFloat($('#acos-range-min').val()) || null;
                const acosRangeMax = parseFloat($('#acos-range-max').val()) || null;

                table.clearFilter(true);

                // SEO Mode filters
                if (seoModeActive) {
                    // Show only parent rows with INV > 0
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        return data.is_parent_summary === true && inv > 0;
                    });
                    
                    // Skip other filters when in SEO mode
                    updateCalcValues();
                    updateSummary();
                    updateSeoCount();
                    return;
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
                }

                if (nrlFilter !== 'all') {
                    if (nrlFilter === 'req') {
                        // Show only REQ (exclude NR)
                        table.addFilter(function(data) {
                            return data.NR !== 'NR';
                        });
                    } else if (nrlFilter === 'nr') {
                        // Show only NR
                        table.addFilter(function(data) {
                            return data.NR === 'NR';
                        });
                    }
                }

                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        const gpft = parseFloat(data['GPFT%']) || 0;
                        
                        if (gpftFilter === 'negative') return gpft < 0;
                        if (gpftFilter === '0-10') return gpft >= 0 && gpft <= 10;
                        if (gpftFilter === '10-20') return gpft > 10 && gpft <= 20;
                        if (gpftFilter === '20-30') return gpft > 20 && gpft <= 30;
                        if (gpftFilter === '30-40') return gpft > 30 && gpft <= 40;
                        if (gpftFilter === '40plus') return gpft > 40;
                        
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        const aL30 = parseFloat(data['A_L30']) || 0;
                        const sess30 = parseFloat(data['Sess30']) || 0;
                        const cvr = sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                        
                        if (cvrFilter === '0-0') return cvr === 0;
                        if (cvrFilter === '0.01-1') return cvr > 0 && cvr <= 1;
                        if (cvrFilter === '1-2') return cvr > 1 && cvr <= 2;
                        if (cvrFilter === '2-3') return cvr > 2 && cvr <= 3;
                        if (cvrFilter === '3-4') return cvr > 3 && cvr <= 4;
                        if (cvrFilter === '4-7') return cvr > 4 && cvr <= 7;
                        if (cvrFilter === '7-10') return cvr > 7 && cvr <= 10;
                        if (cvrFilter === '10plus') return cvr > 10;
                        return true;
                    });
                }

                // DIL filter (sales velocity = L30 / INV * 100)
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

                // Rating filter
                if (ratingFilter !== 'all') {
                    table.addFilter(function(data) {
                        const rating = parseFloat(data['rating']) || 0;
                        
                        if (ratingFilter === 'red') return rating < 3;
                        if (ratingFilter === 'yellow') return rating >= 3 && rating <= 3.5;
                        if (ratingFilter === 'blue') return rating >= 3.51 && rating <= 3.99;
                        if (ratingFilter === 'green') return rating >= 4 && rating <= 4.5;
                        if (ratingFilter === 'pink') return rating > 4.5;
                        return true;
                    });
                }

                // Filter Rows: parents, skus, or all
                if (parentFilter === 'parents') {
                    // Show only parent rows
                    table.addFilter(function(data) {
                        return data.is_parent_summary === true;
                    });
                } else if (parentFilter === 'skus') {
                    // Show all rows except parent rows
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                }
                // If 'all', don't add any filter - show all rows

                if (statusFilter !== 'all') {
                    table.addFilter(function(data) {
                        // Skip parent rows
                        if (data.is_parent_summary) return false;
                        
                        const status = data.SPRICE_STATUS || null;
                        
                        if (statusFilter === 'not-pushed') {
                            // Show SKUs that are not pushed (null, empty, or anything other than 'pushed')
                            return status !== 'pushed';
                        } else if (statusFilter === 'pushed') {
                            return status === 'pushed';
                        } else if (statusFilter === 'applied') {
                            return status === 'applied';
                        } else if (statusFilter === 'error') {
                            return status === 'error';
                        }
                        return true;
                    });
                }

                // Sold filter (based on A_L30)
                if (soldFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        const aL30 = parseFloat(data.A_L30) || 0;
                        
                        if (soldFilter === 'zero') {
                            return aL30 === 0;
                        } else if (soldFilter === 'sold') {
                            return aL30 > 0;
                        }
                        return true;
                    });
                }

                // Unified Range Filter (Views L30/L7, Sold L30/L7)
                if (rangeColumn && (rangeMin !== null || rangeMax !== null)) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        const value = parseFloat(data[rangeColumn]) || 0;
                        
                        // Apply min filter
                        if (rangeMin !== null && value < rangeMin) {
                            return false;
                        }
                        
                        // Apply max filter
                        if (rangeMax !== null && value > rangeMax) {
                            return false;
                        }
                        
                        return true;
                    });
                }

                // Update range filter badge
                updateRangeFilterBadge();

                // Price filter (Prc > LMP)
                if (priceFilterActive) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        const price = parseFloat(data.price) || 0;
                        const lmpPrice = parseFloat(data.lmp_price) || 0;
                        
                        return lmpPrice > 0 && price > lmpPrice;
                    });
                }

                // Map filter (INV vs INV_AMZ) - for inventory sync
                if (mapFilterActive !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        const inv = parseFloat(data.INV) || 0;
                        const nrValue = data.NR || '';
                        const isMissingAmazon = data.is_missing_amazon || false;
                        
                        // Only apply to INV > 0, NR = REQ, and not missing from Amazon
                        if (inv <= 0 || nrValue !== 'REQ' || isMissingAmazon) return false;
                        
                        const invAmz = parseFloat(data.INV_AMZ) || 0;
                        const difference = Math.abs(inv - invAmz);
                        
                        if (mapFilterActive === 'mapped') {
                            return difference === 0; // Show only matched items (Map)
                        } else if (mapFilterActive === 'nmapped') {
                            return difference > 0; // Show only mismatched items (N Map)
                        }
                        return true;
                    });
                }

                // Missing Amazon filter - for items not in amazon_datsheets table
                if (missingAmazonFilterActive) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        const inv = parseFloat(data.INV) || 0;
                        const nrValue = data.NR || '';
                        const isMissingAmazon = data.is_missing_amazon || false;
                        
                        // Show only REQ items with INV > 0 that are missing from Amazon
                        return isMissingAmazon && inv > 0 && nrValue === 'REQ';
                    });
                }

                // Campaign Status filter (Active Filter) - section-aware
                if (campaignStatusFilter && campaignStatusFilter !== '' && campaignStatusFilter !== 'ALL') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return true; // Show parent rows
                        
                        var currentSection = $('#section-filter').val();
                        var isEnabled = false;
                        
                        if (currentSection === 'pt-ads') {
                            // PT Ads section: only check PT campaign status
                            var ptStatus = (data.pt_campaign_status || '').toUpperCase();
                            isEnabled = ptStatus === 'ENABLED';
                        } else {
                            // KW Ads or default: check all statuses
                            var kwStatus = (data.kw_campaign_status || '').toUpperCase();
                            var ptStatus = (data.pt_campaign_status || '').toUpperCase();
                            var generalStatus = (data.campaignStatus || '').toUpperCase();
                            isEnabled = kwStatus === 'ENABLED' || ptStatus === 'ENABLED' || generalStatus === 'ENABLED';
                        }
                        
                        var isPaused = !isEnabled;
                        
                        if (campaignStatusFilter === 'ENABLED') {
                            return isEnabled;
                        } else if (campaignStatusFilter === 'PAUSED') {
                            return !isEnabled && (kwStatus || ptStatus || generalStatus); // Has campaign but not enabled
                        }
                        
                        return true;
                    });
                }

                // NRA filter - apply same default logic as formatter
                if (nraFilter && nraFilter !== '') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return true; // Show parent rows
                        
                        // Get NRA value, applying default logic if empty
                        var nraValue = (data.NRA || '').toString().trim();
                        if (!nraValue) {
                            // Apply default: if NRL is 'NRL', default to 'NRA', otherwise 'RA'
                            var nrlValue = (data.NRL || 'REQ').toString().trim();
                            nraValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                        }
                        
                        return nraValue === nraFilter;
                    });
                }

                // Price Slab filter
                if (priceSlabFilter && priceSlabFilter !== '') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        const price = parseFloat(data.price) || 0;
                        
                        if (priceSlabFilter === 'lt10') return price < 10;
                        if (priceSlabFilter === '10-20') return price >= 10 && price < 20;
                        if (priceSlabFilter === '20-30') return price >= 20 && price < 30;
                        if (priceSlabFilter === '30-50') return price >= 30 && price < 50;
                        if (priceSlabFilter === '50-100') return price >= 50 && price < 100;
                        if (priceSlabFilter === 'gt100') return price >= 100;
                        return true;
                    });
                }

                // ACOS Slab filter
                if (acosSlabFilter && acosSlabFilter !== '') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        const acos = parseFloat(data.ACOS || data.acos) || 0;
                        const spend = parseFloat(data.AD_Spend_L30 || data.l30_spend) || 0;
                        
                        if (acosSlabFilter === 'acos35spend10') return acos >= 35 && spend > 10;
                        if (acosSlabFilter === '8') return acos < 5;
                        if (acosSlabFilter === '7') return acos >= 5 && acos < 10;
                        if (acosSlabFilter === '6') return acos >= 10 && acos < 15;
                        if (acosSlabFilter === '5') return acos >= 15 && acos < 20;
                        if (acosSlabFilter === '4') return acos >= 20 && acos < 25;
                        if (acosSlabFilter === '3') return acos >= 25 && acos < 30;
                        if (acosSlabFilter === '2') return acos >= 30 && acos < 35;
                        if (acosSlabFilter === '1') return acos >= 35;
                        return true;
                    });
                }

                // 7UB Range filter
                if (ub7Min !== null || ub7Max !== null) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        var l7_spend = parseFloat(data.l7_spend) || 0;
                        var budget = (data.utilization_budget != null && data.utilization_budget !== '') ? parseFloat(data.utilization_budget) : (parseFloat(data.campaignBudgetAmount) || 0);
                        var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        
                        if (ub7Min !== null && ub7 < ub7Min) return false;
                        if (ub7Max !== null && ub7 > ub7Max) return false;
                        return true;
                    });
                }

                // 1UB Range filter
                if (ub1Min !== null || ub1Max !== null) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        
                        var l1_spend = parseFloat(data.l1_spend) || 0;
                        var budget = (data.utilization_budget != null && data.utilization_budget !== '') ? parseFloat(data.utilization_budget) : (parseFloat(data.campaignBudgetAmount) || 0);
                        var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                        
                        if (ub1Min !== null && ub1 < ub1Min) return false;
                        if (ub1Max !== null && ub1 > ub1Max) return false;
                        return true;
                    });
                }

                // ACOS Range filter
                if (acosRangeMin !== null || acosRangeMax !== null) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return false;
                        const acos = parseFloat(data.ACOS || data.acos) || 0;
                        
                        if (acosRangeMin !== null && acos < acosRangeMin) return false;
                        if (acosRangeMax !== null && acos > acosRangeMax) return false;
                        return true;
                    });
                }

                // Update utilization counts AFTER all other filters (except NRA section & utilization) are applied
                // Counts include NRA rows - same as KW/PT utilized pages where NRA defaults to "All"
                updateUtilizationCounts();

                // Apply section-specific NRA filter ONLY when utilization type is NOT selected
                // When a utilization type is selected, show all matching rows including NRA
                // (matches KW/PT utilized page behavior where NRA = "All" by default)
                var sectionFilter = $('#section-filter').val();
                if ((sectionFilter === 'kw-ads' || sectionFilter === 'pt-ads') && nraFilter !== 'NRA' && (!utilizationTypeFilter || utilizationTypeFilter === 'all')) {
                    // Hide rows marked as NRA (red dot) in NRA column
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return true; // Show parent rows
                        
                        // Get NRA value, applying default logic if empty
                        var nraValue = (data.NRA || '').toString().trim();
                        if (!nraValue) {
                            // Apply default: if NRL is 'NRL', default to 'NRA', otherwise 'RA'
                            var nrlValue = (data.NRL || 'REQ').toString().trim();
                            nraValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                        }
                        
                        // Hide rows with NRA (red dot)
                        return nraValue !== 'NRA';
                    });
                }

                // Utilization Type filter (7UB x 1UB combinations) - section-aware
                // Applied LAST so counts reflect the correct numbers
                if (utilizationTypeFilter && utilizationTypeFilter !== 'all') {
                    table.addFilter(function(data) {
                        // Do NOT skip parent summary rows - KW/PT utilized pages include parents
                        
                        var currentSection = $('#section-filter').val();
                        var hasCampaign, l7_spend, l1_spend, budget, campaignStatus;
                        
                        if (currentSection === 'pt-ads') {
                            // PT section: check PT campaign existence broadly (L30/L7/L1)
                            hasCampaign = data.pt_campaignName || (data.pt_campaign_status && data.pt_campaign_status !== '') || parseFloat(data.pt_spend_L30) > 0 || parseFloat(data.pt_spend_L7) > 0 || parseFloat(data.pt_spend_L1) > 0;
                            if (!hasCampaign) return false;
                            campaignStatus = (data.pt_campaign_status || '').toUpperCase();
                            if (campaignStatus !== 'ENABLED') return false;
                            l7_spend = parseFloat(data.pt_spend_L7) || 0;
                            l1_spend = parseFloat(data.pt_spend_L1) || 0;
                            budget = parseFloat(data.pt_campaignBudgetAmount) || 0;
                        } else {
                            // KW Ads or default: check KW campaign existence broadly (L30/L7/L1)
                            hasCampaign = data.campaignName || data.campaign_id || (data.kw_campaign_status && data.kw_campaign_status !== '') || parseFloat(data.l7_spend) > 0 || parseFloat(data.l1_spend) > 0;
                            if (!hasCampaign) return false;
                            campaignStatus = (data.kw_campaign_status || data.campaignStatus || '').toUpperCase();
                            if (campaignStatus !== 'ENABLED') return false;
                            l7_spend = parseFloat(data.l7_spend) || 0;
                            l1_spend = parseFloat(data.l1_spend) || 0;
                            budget = (data.utilization_budget != null && data.utilization_budget !== '') ? parseFloat(data.utilization_budget) : (parseFloat(data.campaignBudgetAmount) || 0);
                        }
                        
                        if (!(budget > 0) || isNaN(budget)) return false;
                        
                        var ub7 = (l7_spend / (budget * 7)) * 100;
                        var ub1 = (l1_spend / budget) * 100;
                        
                        var combo = ubZone(ub7) + ubZone(ub1);
                        
                        return combo === utilizationTypeFilter;
                    });
                }

                updateCalcValues();
                updateSummary();
                updateSeoCount();
                // Update select all checkbox after filter is applied
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #dil-filter, #rating-filter, #parent-filter, #status-filter, #sold-filter, #utilization-type-filter, #campaign-status-filter, #nra-filter, #price-slab-filter, #acos-slab-filter').on('change', function() {
                applyFilters();
            });

            // 7UB, 1UB, ACOS range filter input handlers
            $('#7ub-min, #7ub-max, #1ub-min, #1ub-max, #acos-range-min, #acos-range-max').on('keyup change', function() {
                applyFilters();
            });

            // Unified range filter input handlers
            $('#range-min, #range-max, #range-column-select').on('keyup change', function() {
                applyFilters();
            });

            // Clear range filter button
            $('#clear-range-filter').on('click', function() {
                $('#range-min').val('');
                $('#range-max').val('');
                $('#range-column-select').val('');
                applyFilters();
            });

            // Section filter - show/hide columns based on section
            $('#section-filter').on('change', function() {
                var section = $(this).val();
                
                // Show loading overlay
                $('#section-loading-overlay').remove();
                var sectionLabel = section === 'kw-ads' ? 'KW Ads' : section === 'pt-ads' ? 'PT Ads' : section === 'hl-ads' ? 'HL Ads' : section === 'pricing' ? 'Pricing' : 'All';
                var sectionColor = section === 'kw-ads' ? '#4361ee' : section === 'pt-ads' ? '#7209b7' : section === 'hl-ads' ? '#f72585' : section === 'pricing' ? '#2ec4b6' : '#4361ee';
                $('body').append(
                    '<div id="section-loading-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.45);backdrop-filter:blur(3px);z-index:99999;display:flex;align-items:center;justify-content:center;animation:secFadeIn .15s ease;">' +
                        '<div style="background:#fff;border-radius:16px;padding:36px 52px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.15);border-top:4px solid ' + sectionColor + ';">' +
                            '<div style="position:relative;width:48px;height:48px;margin:0 auto 16px;">' +
                                '<div style="width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:' + sectionColor + ';border-radius:50%;animation:secSpin .7s linear infinite;"></div>' +
                            '</div>' +
                            '<p style="margin:0 0 4px;font-weight:700;font-size:15px;color:#1e293b;letter-spacing:-.2px;">Switching to ' + sectionLabel + '</p>' +
                            '<p style="margin:0;font-size:12px;color:#94a3b8;">Updating columns & filters</p>' +
                        '</div>' +
                    '</div>' +
                    '<style>' +
                        '@keyframes secSpin{to{transform:rotate(360deg)}}' +
                        '@keyframes secFadeIn{from{opacity:0}to{opacity:1}}' +
                    '</style>'
                );
                
                // Use setTimeout to allow the overlay to render before heavy operations
                setTimeout(function() {
                
                // Define column groups for each section
                // KW Ads columns as specified by user:
                // Active, SKU, Ratings, Missing, INV, OV L30, DIL %, AL 30, A DIL %, NRL, NRA, Price, 
                // BGT, SBGT, ACOS, Clicks L7, Clicks L30, Spend L30, Sales L30, Ad Sold L30, AD CVR,
                // 7 UB%, 1 UB%, AVG CPC, L7 CPC, L1 CPC, Last SBID, SBID, SBID M, APR BID, TPFT%, Status, CAMPAIGN
                var kwAdsColumns = [
                    '(Child) sku',      // SKU
                    'acos',             // KW ACOS (first after SKU)
                    'l30_spend',        // KW Spend (after ACOS)
                    'l30_clicks',       // KW Clicks (after Spend)
                    'ad_cvr',           // KW CVR (after Clicks)
                    'rating',           // Reviews (after CVR)
                    'campaignBudgetAmount', // KW BGT
                    'sbgt',             // SBGT
                    'NRA',              // KW NRA
                    'active_toggle',    // Active toggle (after NRA)
                    'missing_ad',       // Missing AD
                    'l7_clicks',        // Clicks L7
                    'spend_l7_col',     // Spend L7
                    'l7_sales',         // Sales L7
                    'l7_purchases',     // Ad Sold L7
                    'l30_sales',        // Sales L30
                    'l30_purchases',    // Ad Sold L30
                    'INV',              // INV
                    'L30',              // OV L30
                    'E Dil%',           // DIL %
                    'A_L30',            // AL 30
                    'A DIL %',          // A DIL %
                    'NRL',              // NRL
                    'price',            // Price
                    'l7_spend',         // 7 UB%
                    'l1_spend',         // 1 UB%
                    'avg_cpc',          // AVG CPC
                    'l7_cpc',           // L7 CPC
                    'l1_cpc',           // L1 CPC
                    'last_sbid',        // Last SBID
                    'sbid',             // SBID
                    'sbid_m',           // SBID M
                    'apr_bid',          // APR BID
                    'TPFT',             // TPFT%
                    'campaignName'      // CAMPAIGN
                ];
                
                var pricingColumns = [
                    '(Child) sku', 'price', 'c_price', 'actual_cost', 'buy_box_price', 
                    'GPFT%', 'PFT%', 'ROI_percentage', 'cost', 'margin', 'INV', 'A_L30'
                ];
                // PT Ads columns - PT campaign specific (SAME sequence as KW Ads)
                var ptAdsColumns = [
                    '(Child) sku',          // 1. SKU
                    'pt_acos',              // 2. PT ACOS
                    'pt_spend_L30',         // 3. PT Spend L30
                    'pt_clicks_L30',        // 4. PT Clicks L30
                    'pt_ad_cvr',            // 5. PT CVR
                    'rating',               // 6. Rating
                    'INV',                  // 7. INV
                    'L30',                  // 8. OV L30
                    'E Dil%',               // 9. DIL %
                    'A_L30',                // 10. A L30
                    'A DIL %',              // 11. A DIL %
                    'NRL',                  // 12. NRL
                    'NRA',                  // 13. NRA
                    'active_toggle',        // 14. Active
                    'missing_ad',           // 15. Missing AD
                    'price',                // 16. Price
                    'pt_campaignBudgetAmount', // 17. PT BGT
                    'pt_sbgt',              // 18. PT SBGT
                    'pt_clicks_L7',         // 19. PT Clicks L7
                    'pt_spend_L7',          // 20. PT Spend L7
                    'pt_sales_L7',          // 21. PT Sales L7
                    'pt_sold_L7',           // 22. PT Ad Sold L7
                    'pt_sales_L30',         // 23. PT Sales L30
                    'pt_sold_L30',          // 24. PT Ad Sold L30
                    'pt_7ub',               // 25. PT 7 UB%
                    'pt_1ub',               // 26. PT 1 UB%
                    'pt_avg_cpc',           // 27. PT AVG CPC
                    'pt_l7_cpc',            // 28. PT L7 CPC
                    'pt_l1_cpc',            // 29. PT L1 CPC
                    'pt_last_sbid',         // 30. PT Last SBID
                    'pt_sbid',              // 31. PT SBID
                    'pt_sbid_m',            // 32. PT SBID M
                    'pt_apr_bid',           // 33. PT APR BID
                    'pt_campaignName',      // 34. PT CAMPAIGN
                    'TPFT'                  // 35. TPFT%
                ];
                var hlAdsColumns = [
                    '(Child) sku', 'hl_spend_L30', 'hl_sales_L30', 'price', 'INV', 'A_L30', 
                    'rating'
                ];
                
                if (section === 'all') {
                    // Reset to default visibility based on column definitions
                    table.getColumns().forEach(function(col) {
                        var def = col.getDefinition();
                        var field = def.field;
                        if (!field) return;
                        
                        // Always keep row_select column visible
                        if (field === 'row_select') {
                            table.showColumn(field);
                            return;
                        }
                        
                        // Show columns that don't have visible: false in their definition
                        // Hide columns that have visible: false
                        if (def.visible === false) {
                            table.hideColumn(field);
                        } else {
                            table.showColumn(field);
                        }
                    });
                    // Reset utilization filter and re-apply filters
                    $('#utilization-type-filter').val('all');
                    table.clearFilter();
                    applyFilters();
                    // Remove loading overlay
                    setTimeout(function() {
                        var $overlay = $('#section-loading-overlay');
                        $overlay.css({transition: 'opacity .25s ease', opacity: 0});
                        setTimeout(function() { $overlay.remove(); }, 260);
                    }, 150);
                    return;
                }
                
                // Get all column field names
                var allColumns = table.getColumns().map(function(col) {
                    return col.getField();
                }).filter(function(field) {
                    return field; // Filter out undefined/null
                });
                
                // Determine which columns to show based on section
                var columnsToShow = [];
                if (section === 'pricing') {
                    columnsToShow = pricingColumns;
                } else if (section === 'kw-ads') {
                    columnsToShow = kwAdsColumns;
                } else if (section === 'pt-ads') {
                    columnsToShow = ptAdsColumns;
                } else if (section === 'hl-ads') {
                    columnsToShow = hlAdsColumns;
                }
                
                // Hide all columns first (except row_select checkbox column)
                allColumns.forEach(function(col) {
                    if (table.getColumn(col) && col !== 'row_select') {
                        table.hideColumn(col);
                    }
                });
                
                // Show only the columns for the selected section
                columnsToShow.forEach(function(col) {
                    if (table.getColumn(col)) {
                        table.showColumn(col);
                    }
                });
                
                // Always keep row_select column visible
                if (table.getColumn('row_select')) {
                    table.showColumn('row_select');
                }
                
                // For KW Ads section: sort by ACOS descending and show all rows including parents
                if (section === 'kw-ads') {
                    // Move columns in order after SKU: ACOS, Spend, Clicks, CVR, Reviews, then NRA, then Active toggle, then Missing AD
                    table.moveColumn("acos", "(Child) sku", true);      // KW ACOS after SKU
                    table.moveColumn("l30_spend", "acos", true);        // KW Spend after ACOS
                    table.moveColumn("l30_clicks", "l30_spend", true);  // KW Clicks after Spend
                    table.moveColumn("ad_cvr", "l30_clicks", true);     // KW CVR after Clicks
                    table.moveColumn("rating", "ad_cvr", true);         // Reviews after CVR
                    table.moveColumn("NRA", "NRL", true);               // KW NRA after NRL
                    table.moveColumn("active_toggle", "NRA", true);     // Active toggle after NRA
                    table.moveColumn("missing_ad", "active_toggle", true); // Missing AD after Active
                    
                    table.setSort("acos", "desc");
                    // Reset utilization filter, clear any filters and re-apply with section rules
                    $('#utilization-type-filter').val('all');
                    table.clearFilter();
                    applyFilters(); // Re-apply all filters including section-specific rules
                }
                
                // For PT Ads section: SAME sequence as KW Ads
                if (section === 'pt-ads') {
                    // Move columns in EXACT same sequence as KW Ads
                    // 1-6: SKU, ACOS, Spend L30, Clicks L30, CVR, Rating
                    table.moveColumn("pt_acos", "(Child) sku", true);        // 2. PT ACOS after SKU
                    table.moveColumn("pt_spend_L30", "pt_acos", true);       // 3. PT Spend L30
                    table.moveColumn("pt_clicks_L30", "pt_spend_L30", true); // 4. PT Clicks L30
                    table.moveColumn("pt_ad_cvr", "pt_clicks_L30", true);    // 5. PT CVR
                    table.moveColumn("rating", "pt_ad_cvr", true);           // 6. Rating
                    
                    // 7-12: INV, OV L30, DIL%, A L30, A DIL%, NRL
                    table.moveColumn("INV", "rating", true);                 // 7. INV
                    table.moveColumn("L30", "INV", true);                    // 8. OV L30
                    table.moveColumn("E Dil%", "L30", true);                 // 9. DIL %
                    table.moveColumn("A_L30", "E Dil%", true);               // 10. A L30
                    table.moveColumn("A DIL %", "A_L30", true);              // 11. A DIL %
                    table.moveColumn("NRL", "A DIL %", true);                // 12. NRL
                    
                    // 13-16: NRA, Active, Missing AD, Price
                    table.moveColumn("NRA", "NRL", true);                    // 13. NRA
                    table.moveColumn("active_toggle", "NRA", true);          // 14. Active
                    table.moveColumn("missing_ad", "active_toggle", true);   // 15. Missing AD
                    table.moveColumn("price", "missing_ad", true);           // 16. Price
                    
                    // 17-18: BGT, SBGT
                    table.moveColumn("pt_campaignBudgetAmount", "price", true); // 17. PT BGT
                    table.moveColumn("pt_sbgt", "pt_campaignBudgetAmount", true); // 18. PT SBGT
                    
                    // 19-24: L7/L30 detail columns
                    table.moveColumn("pt_clicks_L7", "pt_sbgt", true);       // 19. Clicks L7
                    table.moveColumn("pt_spend_L7", "pt_clicks_L7", true);   // 20. Spend L7
                    table.moveColumn("pt_sales_L7", "pt_spend_L7", true);    // 21. Sales L7
                    table.moveColumn("pt_sold_L7", "pt_sales_L7", true);     // 22. Ad Sold L7
                    table.moveColumn("pt_sales_L30", "pt_sold_L7", true);    // 23. Sales L30
                    table.moveColumn("pt_sold_L30", "pt_sales_L30", true);   // 24. Ad Sold L30
                    
                    // 25-33: Utilization, CPC, SBID columns
                    table.moveColumn("pt_7ub", "pt_sold_L30", true);         // 25. PT 7 UB%
                    table.moveColumn("pt_1ub", "pt_7ub", true);              // 26. PT 1 UB%
                    table.moveColumn("pt_avg_cpc", "pt_1ub", true);          // 27. PT AVG CPC
                    table.moveColumn("pt_l7_cpc", "pt_avg_cpc", true);       // 28. PT L7 CPC
                    table.moveColumn("pt_l1_cpc", "pt_l7_cpc", true);        // 29. PT L1 CPC
                    table.moveColumn("pt_last_sbid", "pt_l1_cpc", true);     // 30. PT Last SBID
                    table.moveColumn("pt_sbid", "pt_last_sbid", true);       // 31. PT SBID
                    table.moveColumn("pt_sbid_m", "pt_sbid", true);          // 32. PT SBID M
                    table.moveColumn("pt_apr_bid", "pt_sbid_m", true);       // 33. PT APR BID
                    
                    // 34-35: Campaign and TPFT at the end
                    table.moveColumn("pt_campaignName", "pt_apr_bid", true); // 34. PT CAMPAIGN
                    table.moveColumn("TPFT", "pt_campaignName", true);       // 35. TPFT%
                    
                    // Sort by PT ACOS descending (like KW page sorts by ACOS)
                    table.setSort([
                        {column:"pt_acos", dir:"desc"}
                    ]);
                    
                    // Reset utilization filter, clear any filters and re-apply with section rules
                    $('#utilization-type-filter').val('all');
                    table.clearFilter();
                    applyFilters(); // Re-apply all filters including section-specific rules
                }
                
                // Remove loading overlay after operations complete
                setTimeout(function() {
                    var $overlay = $('#section-loading-overlay');
                    $overlay.css({transition: 'opacity .25s ease', opacity: 0});
                    setTimeout(function() { $overlay.remove(); }, 260);
                }, 150);
                
                }, 50); // End of setTimeout for loading overlay
            });

            // ACOS info icon: toggle detail columns (Clicks L7, Clicks L30, Spend L30, Sales L30, Ad Sold L30)
            $(document).on('click', '.info-icon-toggle', function(e) {
                e.stopPropagation();
                e.preventDefault();
                // ACOS info: toggle Clicks L7 + L30 columns
                var acosDetailFields = ['l7_clicks', 'l30_clicks', 'l30_spend', 'l30_sales', 'l30_purchases'];
                var firstCol = table.getColumn('l7_clicks');
                var anyVisible = firstCol && firstCol.isVisible();
                acosDetailFields.forEach(function(fieldName) {
                    if (anyVisible) {
                        table.hideColumn(fieldName);
                    } else {
                        table.showColumn(fieldName);
                    }
                });
            });
            
            // Clicks L7 info icon: toggle only Spend L7, Sales L7, Ad Sold L7
            $(document).on('click', '.info-icon-l7-toggle', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var l7DetailFields = ['spend_l7_col', 'l7_sales', 'l7_purchases'];
                var spendL7Col = table.getColumn('spend_l7_col');
                var anyL7Visible = spendL7Col && spendL7Col.isVisible();
                l7DetailFields.forEach(function(fieldName) {
                    if (anyL7Visible) {
                        table.hideColumn(fieldName);
                    } else {
                        table.showColumn(fieldName);
                    }
                });
            });
            
            // PT ACOS info icon: toggle PT detail columns
            $(document).on('click', '.pt-info-icon-toggle', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var ptDetailFields = ['pt_clicks_L7', 'pt_clicks_L30', 'pt_spend_L30', 'pt_sales_L30', 'pt_sold_L30'];
                var firstCol = table.getColumn('pt_clicks_L7');
                var anyVisible = firstCol && firstCol.isVisible();
                ptDetailFields.forEach(function(fieldName) {
                    if (anyVisible) {
                        table.hideColumn(fieldName);
                    } else {
                        table.showColumn(fieldName);
                    }
                });
            });
            
            // PT Clicks L30 info icon: toggle only PT L7 columns
            $(document).on('click', '.pt-info-icon-l7-toggle', function(e) {
                e.stopPropagation();
                e.preventDefault();
                var ptL7DetailFields = ['pt_spend_L7', 'pt_sales_L7', 'pt_sold_L7'];
                var spendL7Col = table.getColumn('pt_spend_L7');
                var anyL7Visible = spendL7Col && spendL7Col.isVisible();
                ptL7DetailFields.forEach(function(fieldName) {
                    if (anyL7Visible) {
                        table.hideColumn(fieldName);
                    } else {
                        table.showColumn(fieldName);
                    }
                });
            });

            // Function to update range filter badge
            function updateRangeFilterBadge() {
                const rangeMin = parseFloat($('#range-min').val()) || null;
                const rangeMax = parseFloat($('#range-max').val()) || null;
                const rangeColumn = $('#range-column-select').val() || '';
                
                // Only show badge if filter is active
                if (rangeColumn && (rangeMin !== null || rangeMax !== null)) {
                    const data = table.getData("active");
                    let filteredCount = 0;
                    
                    data.forEach(row => {
                        if (!row['is_parent_summary']) {
                            filteredCount++;
                        }
                    });
                    
                    $('#range-filter-count').text(filteredCount.toLocaleString());
                    $('#range-filter-count-badge').show();
                } else {
                    $('#range-filter-count-badge').hide();
                }
            }

            // Update PFT% and ROI% calc values (only for INV > 0)
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let totalCogs = 0;
                
                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        const price = parseFloat(row['price']) || 0;
                        const aL30 = parseFloat(row['A_L30']) || 0;
                        const lp = parseFloat(row['LP_productmaster']) || 0;
                        const ship = parseFloat(row['Ship_productmaster']) || 0;
                        const adPercent = parseFloat(row['AD%']) || 0;
                        const adDecimal = adPercent / 100;
                        
                        // Only process rows with sales
                        if (aL30 > 0 && price > 0) {
                            // Profit per unit = (price * (0.80 - adDecimal)) - ship - lp
                            const profitPerUnit = (price * (0.80 - adDecimal)) - ship - lp;
                            // Total profit for this row = profitPerUnit * L30
                            const profitTotal = profitPerUnit * aL30;
                            const salesL30 = price * aL30;
                            const cogs = lp * aL30;
                            
                            totalProfit += profitTotal;
                            totalSales += salesL30;
                            totalCogs += cogs;
                        }
                    }
                });

                // TOP PFT% = (total profit sum / total sales) * 100
                const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // TOP ROI% = (total profit sum / total COGS) * 100
                const avgRoi = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

                $('#pft-calc').text(avgPft.toFixed(2) + '%');
                $('#roi-calc').text(avgRoi.toFixed(2) + '%');
                $('#avg-pft-badge').text('AVG PFT: ' + avgPft.toFixed(2) + '%');
            }

            // Update summary badges for INV > 0
            function updateSummary() {
                const data = table.getData("active");
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalAmazonInv = 0;
                let totalAmazonInvAmz = 0;
                let totalAmazonL30 = 0;
                let totalAmazonL7 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let totalSkuCount = 0;
                let totalSoldCount = 0;
                let zeroSoldCount = 0;
                let prcGtLmpCount = 0;
                let mapCount = 0;
                let missingCount = 0;
                let missingAmazonCount = 0;
                
                // KW page counts
                let campaignCount = 0;
                let missingCampaignCount = 0;
                let nraCount = 0;
                let raCount = 0;
                let pausedCampaignsCount = 0;
                let ub7Count = 0;
                let ub7Ub1Count = 0;

                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        totalSkuCount++;
                        // DO NOT sum AD_Spend_L30 from rows - causes double-counting
                        // Will use campaign totals instead (calculated after loop)
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * parseFloat(row['A_L30'] || 0);
                        totalAmazonInv += parseFloat(row['INV'] || 0);
                        
                        // Handle INV_AMZ - only sum if numeric
                        const invAmz = row['INV_AMZ'];
                        if (invAmz && !isNaN(parseFloat(invAmz))) {
                            totalAmazonInvAmz += parseFloat(invAmz);
                        }
                        
                        // Ad Spend Breakdown - DO NOT sum from rows as it causes double-counting
                        // We'll use the campaign totals from the backend instead (calculated below)
                        
                        const aL30 = parseFloat(row['A_L30'] || 0);
                        const aL7 = parseFloat(row['A_L7'] || 0);
                        totalAmazonL30 += aL30;
                        totalAmazonL7 += aL7;
                        
                        // Count sold and 0-sold
                        if (aL30 > 0) {
                            totalSoldCount++;
                        } else {
                            zeroSoldCount++;
                        }
                        
                        // Count Prc > LMP
                        const price = parseFloat(row['price'] || 0);
                        const lmpPrice = parseFloat(row['lmp_price'] || 0);
                        if (lmpPrice > 0 && price > lmpPrice) {
                            prcGtLmpCount++;
                        }
                        
                        // Count Missing from Amazon and Map/Missing inventory sync
                        // Only count for INV > 0 and NR = REQ
                        const inv = parseFloat(row['INV'] || 0);
                        const nrValue = row['NR'] || '';
                        const isMissingAmazon = row['is_missing_amazon'] || false;
                        
                        if (inv > 0 && nrValue === 'REQ') {
                            if (isMissingAmazon) {
                                // SKU doesn't exist in amazon_datsheets
                                missingAmazonCount++;
                            } else {
                                // SKU exists in amazon_datsheets, check inventory sync
                                const invAmzNum = parseFloat(row['INV_AMZ'] || 0);
                                const invDifference = Math.abs(inv - invAmzNum);
                                
                                if (invDifference === 0) {
                                    mapCount++; // Perfect match
                                } else {
                                    missingCount++; // Inventory mismatch
                                }
                            }
                        }
                        
                        const dil = parseFloat(row['E Dil%'] || 0);
                        if (!isNaN(dil)) {
                            totalDilPercent += dil;
                            dilCount++;
                        }
                        
                        // KW page counts
                        // Campaign count
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                        if (hasCampaign) {
                            campaignCount++;
                            
                            // Check for paused campaigns
                            const campaignStatus = (row.campaignStatus || '').toUpperCase();
                            if (campaignStatus === 'PAUSED') {
                                pausedCampaignsCount++;
                            }
                            
                            // Calculate 7UB and 1UB
                            const l7_spend = parseFloat(row.l7_spend) || 0;
                            const l1_spend = parseFloat(row.l1_spend) || 0;
                            const budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            const ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            const ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            
                            // 7UB green count (66-99%)
                            if (ub7 >= 66 && ub7 <= 99) {
                                ub7Count++;
                            }
                            
                            // 7UB + 1UB both green count
                            if ((ub7 >= 66 && ub7 <= 99) && (ub1 >= 66 && ub1 <= 99)) {
                                ub7Ub1Count++;
                            }
                        } else {
                            missingCampaignCount++;
                        }
                        
                        // NRA/RA count
                        const nraValue = row.NRA || '';
                        if (nraValue === 'NRA') {
                            nraCount++;
                        } else if (nraValue === 'RA') {
                            raCount++;
                        }
                    }
                });

                let totalWeightedPrice = 0;
                let totalL30 = 0;
                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        const price = parseFloat(row['price'] || 0);
                        const l30 = parseFloat(row['A_L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
                $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice));

                let totalViews = 0;
                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        totalViews += parseFloat(row['Sess30'] || 0);
                    }
                });
                const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;
                const avgViews = totalSkuCount > 0 ? Math.round(totalViews / totalSkuCount) : 0;
                $('#avg-cvr-badge').text('Avg CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#avg-views-badge').text('Avg Views: ' + avgViews.toLocaleString());
                $('#total-amazon-l30-badge').text('A L30: ' + Math.round(totalAmazonL30).toLocaleString());
                $('#total-amazon-l7-badge').text('A L7: ' + Math.round(totalAmazonL7).toLocaleString());
                
                // Update sold counts
                $('#total-sold-count').text(totalSoldCount.toLocaleString());
                $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
                
                // Update Map and N Map counts (inventory sync for items that exist in Amazon)
                $('#map-count').text(mapCount.toLocaleString());
                $('#nmap-count').text(missingCount.toLocaleString());
                
                // Update Missing Amazon count (items not in amazon_datsheets)
                $('#missing-amazon-count').text(missingAmazonCount.toLocaleString());
                
                // Update Prc > LMP count
                $('#prc-gt-lmp-count').text(prcGtLmpCount.toLocaleString());
                
                // Calculate Total Spend L30 from campaign totals (avoid double-counting)
                const totalSpendL30 = (campaignTotals.kw_spend_L30 || 0) + (campaignTotals.pt_spend_L30 || 0) + (campaignTotals.hl_spend_L30 || 0);
                
                // Calculate TCOS% = (Total Spend L30 / Total Sales) * 100
                const tcosPercent = totalSalesAmt > 0 ? ((totalSpendL30 / totalSalesAmt) * 100) : 0;
                
                $('#total-spend-l30-badge').text('Spend L30: $' + Math.round(totalSpendL30));
                
                // GROI% = (Total PFT / Total COGS) * 100
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;
                $('#groi-percent-badge').text('GROI: ' + groiPercent.toFixed(1) + '%');
                
                // NROI% = GROI% - TCOS%
                const nroiPercent = groiPercent - tcosPercent;
                $('#nroi-percent-badge').text('NROI: ' + nroiPercent.toFixed(1) + '%');
                
                // TCOS%
                $('#tcos-percent-badge').text('TCOS: ' + tcosPercent.toFixed(1) + '%');
                
                $('#total-amazon-inv-badge').text('INV: ' + Math.round(totalAmazonInv).toLocaleString());
                $('#total-amazon-inv-amz-badge').text('INV AMZ: ' + Math.round(totalAmazonInvAmz).toLocaleString());
                $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt));
                $('#total-sales-amt-badge').text('Total Sales: $' + Math.round(totalSalesAmt));
                
                // AVG GPFT% = (Total_pft / Total_Sales) * 100 (Gross Profit % - before ads)
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
                $('#avg-gpft-badge').text('GPFT: ' + avgGpft.toFixed(1) + '%');
                
                // TACOS% = (Total Ad Spend / Total Sales) * 100
                const tacosPercent = totalSalesAmt > 0 ? ((totalSpendL30 / totalSalesAmt) * 100) : 0;
                
                // AVG PFT% = GPFT% - TACOS% (Net Profit % - after ads)
                const avgPft = avgGpft - tacosPercent;
                $('#avg-pft-badge').text('PFT: ' + avgPft.toFixed(1) + '%');
                
                // Update Ad Spend Breakdown Badges
                // Use campaign totals from backend to avoid double-counting
                $('#kw-spend-badge').text('KW Ads: $' + Math.round(campaignTotals.kw_spend_L30 || 0));
                $('#hl-spend-badge').text('HL Ads: $' + Math.round(campaignTotals.hl_spend_L30 || 0));
                $('#pt-spend-badge').text('PT Ads: $' + Math.round(campaignTotals.pt_spend_L30 || 0));
                
                // Update KW page badges
                $('#campaign-count').text(campaignCount.toLocaleString());
                $('#missing-campaign-count').text(missingCampaignCount.toLocaleString());
                $('#nra-count').text(nraCount.toLocaleString());
                $('#ra-count').text(raCount.toLocaleString());
                $('#paused-campaigns-count').text(pausedCampaignsCount.toLocaleString());
                $('#7ub-count').text(ub7Count.toLocaleString());
                $('#7ub-1ub-count').text(ub7Ub1Count.toLocaleString());

                // Save badge stats daily (fire-and-forget, once per page load)
                if (!window._badgeStatsSaved) {
                    window._badgeStatsSaved = true;
                    $.post('/amazon-badge-stats-save', {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sold_count: totalSoldCount,
                        zero_sold_count: zeroSoldCount,
                        map_count: mapCount,
                        nmap_count: missingCount,
                        missing_count: missingAmazonCount,
                        prc_gt_lmp_count: prcGtLmpCount,
                        campaign_count: campaignCount,
                        missing_campaign_count: missingCampaignCount,
                        nra_count: nraCount,
                        ra_count: raCount,
                        paused_count: pausedCampaignsCount,
                        ub7_count: ub7Count,
                        ub7_ub1_count: ub7Ub1Count,
                        kw_spend: Math.round(campaignTotals.kw_spend_L30 || 0),
                        hl_spend: Math.round(campaignTotals.hl_spend_L30 || 0),
                        pt_spend: Math.round(campaignTotals.pt_spend_L30 || 0)
                    });
                }
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/amazon-column-visibility', {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(res => res.json())
                    .then(visibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            const field = def.field;
                            if (!field) return;

                            const isVisible = col.isVisible();
                            const li = document.createElement("li");
                            li.innerHTML =
                                `<label class="dropdown-item"><input type="checkbox" ${isVisible ? 'checked' : ''} data-field="${field}"> ${def.title}</label>`;
                            menu.appendChild(li);
                        });
                    })
                    .catch(err => console.error('Error loading column visibility:', err));
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const field = col.getDefinition().field;
                    if (field) {
                        visibility[field] = col.isVisible();
                    }
                });

                fetch('/amazon-column-visibility', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        visibility
                    })
                }).catch(err => console.error('Error saving column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                fetch('/amazon-column-visibility', {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(res => res.json())
                    .then(visibility => {
                        table.getColumns().forEach(col => {
                            const field = col.getDefinition().field;
                            if (field && visibility.hasOwnProperty(field)) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                    })
                    .catch(err => console.error('Error applying column visibility:', err));
            }

            // Wait for table to be built
            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
                updateApplyAllButton();
            });

            table.on('dataLoaded', function() {
                updateCalcValues();
                updateSummary();
                updateSeoCount();
                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updateApplyAllButton();
                }, 100);
            });

            table.on('renderComplete', function() {
                // Update row counter above table
                try {
                    var totalRows = table.getDataCount('active');
                    var pageSize = table.getPageSize();
                    var currentPage = table.getPage();
                    var start = (currentPage - 1) * pageSize + 1;
                    var end = Math.min(currentPage * pageSize, totalRows);
                    if (totalRows === 0) {
                        $('#table-row-counter').text('No rows');
                    } else {
                        $('#table-row-counter').text('Showing ' + start + '-' + end + ' of ' + totalRows + ' rows');
                    }
                } catch(e) {}

                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updateSeoCount();
                    // Refresh row selection checkboxes to reflect selectedRows set
                    $('.row-select-checkbox').each(function() {
                        var sku = $(this).data('sku');
                        $(this).prop('checked', selectedRows.has(sku));
                    });
                    updateRowSelectAllCheckbox();
                    updateSelectedCount();
                }, 100);
            });

            // Row selection - track selected rows
            var selectedRows = new Set();

            // Select all rows checkbox handler - only select rows on current page
            $(document).on('change', '#select-all-rows', function() {
                var isChecked = $(this).prop('checked');
                
                // Get rows on current page using Tabulator's getPageData or getRows
                var currentPageRows = table.getRows('active');
                var pageSize = table.getPageSize();
                var currentPage = table.getPage();
                var startIndex = (currentPage - 1) * pageSize;
                var endIndex = startIndex + pageSize;
                
                // Get only rows for current page
                var pageRows = currentPageRows.slice(startIndex, endIndex);
                
                pageRows.forEach(function(row) {
                    var sku = row.getData()['(Child) sku'] || '';
                    if (sku) {
                        if (isChecked) {
                            selectedRows.add(sku);
                        } else {
                            selectedRows.delete(sku);
                        }
                    }
                });
                
                // Update all visible checkboxes on current page
                $('.row-select-checkbox').prop('checked', isChecked);
                updateSelectedCount();
            });

            // Individual row checkbox handler
            $(document).on('change', '.row-select-checkbox', function() {
                var sku = $(this).data('sku');
                if ($(this).prop('checked')) {
                    selectedRows.add(sku);
                } else {
                    selectedRows.delete(sku);
                }
                updateRowSelectAllCheckbox();
                updateSelectedCount();
            });

            // Update select all checkbox state based on individual checkboxes
            function updateRowSelectAllCheckbox() {
                var allCheckboxes = $('.row-select-checkbox');
                var checkedCheckboxes = $('.row-select-checkbox:checked');
                
                if (allCheckboxes.length === 0) {
                    $('#select-all-rows').prop('checked', false);
                    $('#select-all-rows').prop('indeterminate', false);
                } else if (checkedCheckboxes.length === 0) {
                    $('#select-all-rows').prop('checked', false);
                    $('#select-all-rows').prop('indeterminate', false);
                } else if (checkedCheckboxes.length === allCheckboxes.length) {
                    $('#select-all-rows').prop('checked', true);
                    $('#select-all-rows').prop('indeterminate', false);
                } else {
                    $('#select-all-rows').prop('checked', false);
                    $('#select-all-rows').prop('indeterminate', true);
                }
            }

            // Update selected count display
            function updateSelectedCount() {
                var count = selectedRows.size;
                if (count > 0) {
                    $('#selected-rows-count').text(count + ' selected').show();
                    $('#clear-selection-btn').show();
                    $('#bulk-actions-container').show();
                } else {
                    $('#selected-rows-count').hide();
                    $('#clear-selection-btn').hide();
                    $('#bulk-actions-container').hide();
                }
            }

            // Clear selection button handler
            $('#clear-selection-btn').on('click', function() {
                clearRowSelections();
            });

            // Bulk action handler
            $(document).on('click', '.bulk-action-item', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var selectedSkusList = getSelectedSkus();
                
                if (selectedSkusList.length === 0) {
                    alert('Please select at least one row');
                    return;
                }
                
                // Show loading
                var $btn = $('#bulkActionsDropdown');
                var originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
                
                // Handle PAUSE/ACTIVATE actions differently
                if (action === 'PAUSE' || action === 'ACTIVATE') {
                    var newStatus = action === 'ACTIVATE' ? 'ENABLED' : 'PAUSED';
                    
                    var promises = selectedSkusList.map(function(sku) {
                        return fetch('/toggle-amazon-sku-ads', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                sku: sku,
                                status: newStatus
                            })
                        }).then(function(res) { return res.json(); });
                    });
                    
                    Promise.all(promises).then(function(results) {
                        // Update table data
                        selectedSkusList.forEach(function(sku) {
                            var rows = table.getRows().filter(function(row) {
                                return row.getData()['(Child) sku'] === sku;
                            });
                            rows.forEach(function(row) {
                                row.update({
                                    kw_campaign_status: newStatus,
                                    pt_campaign_status: newStatus,
                                    campaignStatus: newStatus
                                });
                                row.reformat();
                            });
                        });
                        
                        // Show success message
                        var statusText = newStatus === 'ENABLED' ? 'activated' : 'paused';
                        showToast('success', selectedSkusList.length + ' campaign(s) ' + statusText);
                        
                        // Clear selections
                        clearRowSelections();
                        
                        // Restore button
                        $btn.html(originalText).prop('disabled', false);
                    }).catch(function(err) {
                        console.error('Bulk action error:', err);
                        alert('Error processing bulk action: ' + (err.message || 'Unknown error'));
                        $btn.html(originalText).prop('disabled', false);
                    });
                } else {
                    // Handle NRA/RA/LATER actions
                    var promises = selectedSkusList.map(function(sku) {
                        return fetch('/update-amazon-nr-nrl-fba', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                sku: sku,
                                field: 'NRA',
                                value: action
                            })
                        }).then(function(res) { return res.json(); });
                    });
                    
                    Promise.all(promises).then(function(results) {
                        // Update table data
                        selectedSkusList.forEach(function(sku) {
                            var rows = table.getRows().filter(function(row) {
                                return row.getData()['(Child) sku'] === sku;
                            });
                            rows.forEach(function(row) {
                                row.update({NRA: action});
                            });
                        });
                        
                        // Show success message
                        showToast('success', selectedSkusList.length + ' row(s) marked as ' + action);
                        
                        // Clear selections
                        clearRowSelections();
                        
                        // Restore button
                        $btn.html(originalText).prop('disabled', false);
                    }).catch(function(err) {
                        console.error('Bulk action error:', err);
                        alert('Error processing bulk action: ' + (err.message || 'Unknown error'));
                        $btn.html(originalText).prop('disabled', false);
                    });
                }
            });

            // Function to get all selected SKUs
            function getSelectedSkus() {
                return Array.from(selectedRows);
            }

            // Function to clear all selections
            function clearRowSelections() {
                selectedRows.clear();
                $('.row-select-checkbox').prop('checked', false);
                $('#select-all-rows').prop('checked', false);
                $('#select-all-rows').prop('indeterminate', false);
                updateSelectedCount();
            }

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const field = e.target.getAttribute('data-field');
                    const col = table.getColumn(field);
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    saveColumnVisibilityToServer();
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    col.show();
                });
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            // Handle campaign status toggle
            document.addEventListener("change", function(e) {
                if(e.target.classList.contains("campaign-status-toggle")) {
                    let campaignId = e.target.getAttribute("data-campaign-id");
                    let isEnabled = e.target.checked;
                    let newStatus = isEnabled ? 'ENABLED' : 'PAUSED';
                    
                    if(!campaignId) {
                        alert("Campaign ID not found!");
                        e.target.checked = !isEnabled; // Revert toggle
                        return;
                    }
                    
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    
                    fetch('/toggle-amazon-sp-campaign-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            campaign_id: campaignId,
                            status: newStatus
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 200){
                            // Update the row data
                            let rows = table.getRows();
                            for(let i = 0; i < rows.length; i++) {
                                let rowData = rows[i].getData();
                                if(rowData.campaign_id === campaignId) {
                                    rows[i].update({campaignStatus: newStatus});
                                    break;
                                }
                            }
                        } else {
                            alert("Error: " + (data.message || "Failed to update campaign status"));
                            e.target.checked = !isEnabled; // Revert toggle
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Request failed: " + err.message);
                        e.target.checked = !isEnabled; // Revert toggle
                    })
                    .finally(() => {
                        if (overlay) {
                            overlay.style.display = "none";
                        }
                    });
                }
            });

            // Copy SKU to clipboard
            document.addEventListener("click", function(e) {
                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn") || e.target.closest('.copy-sku-btn')) {
                    const btn = e.target.classList.contains("copy-sku-btn") ? e.target : e.target.closest(
                        '.copy-sku-btn');
                    const sku = btn.getAttribute('data-sku');

                    navigator.clipboard.writeText(sku).then(() => {
                        showToast('success', 'SKU copied to clipboard');
                    }).catch(err => {
                        showToast('error', 'Failed to copy SKU');
                    });
                }

                // View SKU chart
                if (e.target.closest('.view-sku-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = e.target.closest('.view-sku-chart').getAttribute('data-sku');
                    currentSku = sku;
                    $('#modalSkuName').text(sku);
                    $('#sku-chart-days-filter').val('7');
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 7);
                    $('#skuMetricsModal').modal('show');
                }
            });

            // Ad Pause Toggle - use change event like acos-control-kw
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("ad-pause-toggle")) {
                    const checkbox = e.target;
                    const sku = checkbox.getAttribute("data-sku");
                    const isEnabled = checkbox.checked;
                    const newStatus = isEnabled ? 'ENABLED' : 'PAUSED';
                    
                    if (!sku) {
                        alert("SKU not found!");
                        checkbox.checked = !isEnabled; // Revert toggle
                        return;
                    }
                    
                    // Show loading overlay if available
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) overlay.style.display = "flex";
                    
                    fetch('/toggle-amazon-sku-ads', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            status: newStatus
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            throw new Error(`HTTP error! status: ${res.status}`);
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data.status === 200) {
                            // Update the row data - update campaign status fields like acos-control-kw
                            let rows = table.getRows();
                            for (let i = 0; i < rows.length; i++) {
                                let rowData = rows[i].getData();
                                if (rowData['(Child) sku'] === sku) {
                                    // Update campaign status fields
                                    rows[i].update({
                                        kw_campaign_status: newStatus,
                                        pt_campaign_status: newStatus
                                    });
                                    
                                    // Reformat the row to update the toggle button with new status
                                    rows[i].reformat();
                                    
                                    showToast('success', `Ads ${newStatus === 'ENABLED' ? 'enabled' : 'paused'} for SKU: ${sku}`);
                                    break;
                                }
                            }
                        } else {
                            alert("Error: " + (data.message || "Failed to update ad status"));
                            checkbox.checked = !isEnabled; // Revert toggle
                        }
                    })
                    .catch(err => {
                        console.error('Toggle error:', err);
                        alert("Request failed: " + (err.message || "Network error"));
                        checkbox.checked = !isEnabled; // Revert toggle
                    })
                    .finally(() => {
                        if (overlay) overlay.style.display = "none";
                    });
                }
            });

            // Handle NRA/NRL dropdown changes - save to database
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    // Update color immediately for NRA field
                    if (field === 'NRA') {
                        if (value === 'NRA') {
                            e.target.style.backgroundColor = '#dc3545'; // red
                            e.target.style.color = '#000';
                        } else if (value === 'RA') {
                            e.target.style.backgroundColor = '#28a745'; // green
                            e.target.style.color = '#000';
                        } else if (value === 'LATER') {
                            e.target.style.backgroundColor = '#ffc107'; // yellow
                            e.target.style.color = '#000';
                        }
                    }

                    // Save to database
                    fetch('/update-amazon-nr-nrl-fba', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: value
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log(data);
                        // Update table data with response
                        if (data.success && typeof table !== 'undefined' && table) {
                            let rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({[field]: value});
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error saving NRA:', err);
                        alert("Failed to save: " + (err.message || "Network error"));
                    });
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

            // Load Competitors Modal Function
            function loadCompetitorsModal(sku) {
                $('#lmpSku').text(sku);
                
                // Pre-fill form with SKU
                $('#addCompSku').val(sku);
                $('#addCompAsin').val('');
                $('#addCompPrice').val('');
                $('#addCompLink').val('');
                $('#addCompMarketplace').val('amazon');
                
                $('#lmpModal').modal('show');
                
                // Show loading state
                $('#lmpDataList').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading competitors...</p>
                    </div>
                `);
                
                // Fetch competitors from backend
                $.ajax({
                    url: '/amazon/competitors',
                    method: 'GET',
                    data: { sku: sku },
                    success: function(response) {
                        if (response.success) {
                            currentLmpData.sku = sku;
                            currentLmpData.competitors = response.competitors;
                            currentLmpData.lowestPrice = response.lowest_price;
                            
                            renderCompetitorsList(response.competitors, response.lowest_price);
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
            function renderCompetitorsList(competitors, lowestPrice) {
                if (!competitors || competitors.length === 0) {
                    $('#lmpDataList').html(`
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> No competitors found for this SKU
                        </div>
                    `);
                    return;
                }
                
                let html = '<div class="table-responsive"><table class="table table-hover table-bordered">';
                html += `
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>ASIN</th>
                            <th>Price</th>
                            <th style="width: 80px;">Link</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                
                competitors.forEach((item, index) => {
                    const isLowest = (item.price === lowestPrice);
                    const rowClass = isLowest ? 'table-success' : '';
                    const priceFormatted = '$' + parseFloat(item.price).toFixed(2);
                    const priceBadge = isLowest ? 
                        `<span class="badge bg-success">${priceFormatted} <i class="fa fa-trophy"></i> LOWEST</span>` : 
                        `<strong>${priceFormatted}</strong>`;
                    
                    const productLink = item.link || item.product_link || '#';
                    
                    html += `
                        <tr class="${rowClass}">
                            <td class="text-center"><strong>${index + 1}</strong></td>
                            <td>
                                <span class="text-primary" style="font-weight: 600;">${item.asin || 'N/A'}</span>
                            </td>
                            <td><strong>${priceBadge}</strong></td>
                            <td class="text-center">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on Amazon">
                                    <i class="fa fa-external-link"></i>
                                </a>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger delete-lmp-btn" 
                                    data-id="${item.id}" 
                                    data-asin="${item.asin}" 
                                    data-price="${item.price}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
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
                loadCompetitorsModal(sku);
            });

            // Add New Competitor Form Submit
            $('#addCompetitorForm').on('submit', function(e) {
                e.preventDefault();
                
                const sku = $('#addCompSku').val();
                const asin = $('#addCompAsin').val().trim();
                const price = parseFloat($('#addCompPrice').val());
                const link = $('#addCompLink').val().trim();
                const marketplace = $('#addCompMarketplace').val();
                
                // Validation
                if (!asin) {
                    showToast('error', 'ASIN is required');
                    return;
                }
                
                if (!price || price <= 0) {
                    showToast('error', 'Valid price is required');
                    return;
                }
                
                const $submitBtn = $(this).find('button[type="submit"]');
                const originalHtml = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
                
                $.ajax({
                    url: '/amazon/lmp/add',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        asin: asin,
                        price: price,
                        product_link: link || null,
                        product_title: null,
                        marketplace: marketplace
                    },
                    success: function(response) {
                        showToast('success', 'Competitor added successfully');
                        $submitBtn.prop('disabled', false).html(originalHtml);
                        
                        // Clear form
                        $('#addCompAsin').val('');
                        $('#addCompPrice').val('');
                        $('#addCompLink').val('');
                        
                        // Reload table to show updated LMP
                        if (table) {
                            table.replaceData();
                        }
                        
                        // Reload modal to show updated list
                        loadCompetitorsModal(sku);
                    },
                    error: function(xhr) {
                        $submitBtn.prop('disabled', false).html(originalHtml);
                        
                        let errorMsg = 'Failed to add competitor';
                        
                        // Handle 409 Conflict (duplicate entry)
                        if (xhr.status === 409) {
                            errorMsg = '⚠️ This ASIN is already saved for this SKU';
                        } else if (xhr.responseJSON?.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON?.messages) {
                            errorMsg = Object.values(xhr.responseJSON.messages).flat().join(', ');
                        }
                        
                        showToast('error', errorMsg);
                        console.error('Error adding competitor:', xhr);
                    }
                });
            });

            // Delete LMP Button Click
            $(document).on('click', '.delete-lmp-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const id = $btn.data('id');
                const asin = $btn.data('asin');
                const price = $btn.data('price');
                
                if (!id) {
                    showToast('error', 'Invalid competitor ID');
                    return;
                }
                
                if (!confirm(`Delete competitor ${asin} ($${price}) from tracking?`)) {
                    return;
                }
                
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: '/amazon/lmp/delete',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        _method: 'DELETE',
                        id: id
                    },
                    success: function(response) {
                        showToast('success', 'Competitor deleted successfully');
                        
                        // Reload table to show updated LMP
                        if (table) {
                            table.replaceData();
                        }
                        
                        // Reload modal to show updated list
                        loadCompetitorsModal(currentLmpData.sku);
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html(originalHtml);
                        
                        const errorMsg = xhr.responseJSON?.error || 'Failed to delete competitor';
                        showToast('error', errorMsg);
                        console.error('Error deleting LMP:', xhr);
                    }
                });
            });
        });

        // Scout Modal Event Listener
        $(document).on('click', '.scout-link', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            let data = $(this).data('scout-data');

            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                openScoutModal(sku, data);
            } catch (error) {
                console.error('Error parsing Scout data:', error);
                alert('Error loading Scout data');
            }
        });

        // Scout Modal Function
        function openScoutModal(sku, data) {
            $('#scoutSku').text(sku);
            let html = '';
            data.forEach(item => {
                html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price:</strong> $${item.price || 'N/A'}<br>
                    <strong>Sales:</strong> ${item.sales || 'N/A'}<br>
                    <strong>Revenue:</strong> $${item.revenue || 'N/A'}
                </div>`;
            });
            $('#scoutDataList').html(html);
            $('#scoutModal').modal('show');
        }

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
                url: '/import-amazon-ratings',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    uploadBtn.html('<i class="fa fa-spinner fa-spin"></i> Reloading...');
                    table.reload(function() {
                        showToast('success', response.success || 'Ratings imported successfully');
                        $('#importModal').modal('hide');
                        $('#importForm')[0].reset();
                        uploadBtn.prop('disabled', false).html('Upload & Import');
                    });
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Import failed';
                    showToast('error', error);
                },
                complete: function() {
                    uploadBtn.prop('disabled', false).html('Upload & Import');
                }
            });
        });

        // ACOS Info Icon Click Handler
        $(document).on('click', '.acos-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (!sku) {
                showToast('error', 'SKU not found');
                return;
            }
            
            $('#campaignModalLabel').text(`Campaign Details - ${sku}`);
            $('#campaignModalBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#campaignModal').modal('show');
            
            $.ajax({
                url: '/amazon-campaign-data-by-sku',
                type: 'GET',
                data: { sku: sku },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Helper function to get ACOS color class
                    function getAcosColorClass(acos) {
                        if (acos === 0) return '';
                        if (acos < 7) return 'pink-bg';
                        if (acos >= 7 && acos <= 14) return 'green-bg';
                        if (acos > 14) return 'red-bg';
                        return '';
                    }
                    
                    // Helper function to get UB color class
                    function getUbColorClass(ub) {
                        if (ub >= 66 && ub <= 99) return 'green-bg';
                        if (ub > 99) return 'pink-bg';
                        if (ub < 66) return 'red-bg';
                        return '';
                    }
                    
                    let html = '';
                    
                    // Check if HL campaigns exist - if yes, only show HL (not KW/PT)
                    const hasHlCampaigns = response.hl_campaigns && response.hl_campaigns.length > 0;
                    
                    if (hasHlCampaigns) {
                        // Only show HL campaigns
                        response.hl_campaigns.forEach(function(campaign, index) {
                            html += `<h5 class="mb-3">HL Campaign - ${campaign.campaign_name || 'N/A'}</h5>`;
                            html += '<div class="table-responsive mb-4">';
                            html += '<table class="table table-bordered table-sm">';
                            html += '<thead><tr>';
                            html += '<th>BGT</th><th>SBGT</th><th>ACOS</th><th>Clicks</th><th>Ad Spend</th><th>Ad Sales</th><th>Ad Sold</th>';
                            html += '<th>AD CVR</th><th>7UB%</th><th>1UB%</th><th>AVG CPC</th><th>L7CPC</th><th>L1CPC</th><th>L BID</th><th>SBID</th>';
                            html += '</tr></thead><tbody>';
                            const acos = parseFloat(campaign.acos || 0);
                            const ub7 = parseFloat(campaign['7ub'] || 0);
                            const ub1 = parseFloat(campaign['1ub'] || 0);
                            
                            html += '<tr>';
                            html += `<td>${(campaign.bgt || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.sbgt || 0).toFixed(0)}</td>`;
                            html += `<td class="${getAcosColorClass(acos)}">${acos.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.clicks || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_spend || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sales || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sold || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_cvr || 0).toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub7)}">${ub7.toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub1)}">${ub1.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.avg_cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l7cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l1cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l_bid && campaign.l_bid !== '' && campaign.l_bid !== '0' && parseFloat(campaign.l_bid) > 0) ? parseFloat(campaign.l_bid).toFixed(2) : '-'}</td>`;
                            // Show SBID if it exists and is > 0 (for over, under, or zero utilization cases)
                            const showSbid = campaign.sbid && campaign.sbid > 0;
                            html += `<td>${showSbid ? campaign.sbid.toFixed(2) : '-'}</td>`;
                            html += '</tr>';
                            html += '</tbody></table></div>';
                        });
                    } else {
                        // Show KW and PT campaigns (only if no HL campaigns)
                        // KW Campaigns
                        if (response.kw_campaigns && response.kw_campaigns.length > 0) {
                        response.kw_campaigns.forEach(function(campaign, index) {
                            html += `<h5 class="mb-3">KW Campaign - ${campaign.campaign_name || 'N/A'}</h5>`;
                            html += '<div class="table-responsive mb-4">';
                            html += '<table class="table table-bordered table-sm">';
                            html += '<thead><tr>';
                            html += '<th>BGT</th><th>SBGT</th><th>ACOS</th><th>Clicks</th><th>Ad Spend</th><th>Ad Sales</th><th>Ad Sold</th>';
                            html += '<th>AD CVR</th><th>7UB%</th><th>1UB%</th><th>AVG CPC</th><th>L7CPC</th><th>L1CPC</th><th>L BID</th><th>SBID</th>';
                            html += '</tr></thead><tbody>';
                            const acos = parseFloat(campaign.acos || 0);
                            const ub7 = parseFloat(campaign['7ub'] || 0);
                            const ub1 = parseFloat(campaign['1ub'] || 0);
                            
                            html += '<tr>';
                            html += `<td>${(campaign.bgt || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.sbgt || 0).toFixed(0)}</td>`;
                            html += `<td class="${getAcosColorClass(acos)}">${acos.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.clicks || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_spend || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sales || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sold || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_cvr || 0).toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub7)}">${ub7.toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub1)}">${ub1.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.avg_cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l7cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l1cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l_bid && campaign.l_bid !== '' && campaign.l_bid !== '0' && parseFloat(campaign.l_bid) > 0) ? parseFloat(campaign.l_bid).toFixed(2) : '-'}</td>`;
                            // Show SBID if it exists and is > 0 (for over, under, or zero utilization cases)
                            const showSbid = campaign.sbid && campaign.sbid > 0;
                            html += `<td>${showSbid ? campaign.sbid.toFixed(2) : '-'}</td>`;
                            html += '</tr>';
                            html += '</tbody></table></div>';
                        });
                    } else {
                        html += '<h5 class="mb-3">KW Campaigns</h5><p class="text-muted">No KW campaigns found</p>';
                    }
                    
                    // PT Campaigns
                    if (response.pt_campaigns && response.pt_campaigns.length > 0) {
                        response.pt_campaigns.forEach(function(campaign, index) {
                            html += `<h5 class="mb-3">PT Campaign - ${campaign.campaign_name || 'N/A'}</h5>`;
                            html += '<div class="table-responsive mb-4">';
                            html += '<table class="table table-bordered table-sm">';
                            html += '<thead><tr>';
                            html += '<th>BGT</th><th>SBGT</th><th>ACOS</th><th>Clicks</th><th>Ad Spend</th><th>Ad Sales</th><th>Ad Sold</th>';
                            html += '<th>AD CVR</th><th>7UB%</th><th>1UB%</th><th>AVG CPC</th><th>L7CPC</th><th>L1CPC</th><th>L BID</th><th>SBID</th>';
                            html += '</tr></thead><tbody>';
                            const acos = parseFloat(campaign.acos || 0);
                            const ub7 = parseFloat(campaign['7ub'] || 0);
                            const ub1 = parseFloat(campaign['1ub'] || 0);
                            
                            html += '<tr>';
                            html += `<td>${(campaign.bgt || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.sbgt || 0).toFixed(0)}</td>`;
                            html += `<td class="${getAcosColorClass(acos)}">${acos.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.clicks || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_spend || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sales || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_sold || 0).toFixed(0)}</td>`;
                            html += `<td>${(campaign.ad_cvr || 0).toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub7)}">${ub7.toFixed(0)}%</td>`;
                            html += `<td class="${getUbColorClass(ub1)}">${ub1.toFixed(0)}%</td>`;
                            html += `<td>${(campaign.avg_cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l7cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l1cpc || 0).toFixed(2)}</td>`;
                            html += `<td>${(campaign.l_bid && campaign.l_bid !== '' && campaign.l_bid !== '0' && parseFloat(campaign.l_bid) > 0) ? parseFloat(campaign.l_bid).toFixed(2) : '-'}</td>`;
                            // Show SBID if it exists and is > 0 (for over, under, or zero utilization cases)
                            const showSbid = campaign.sbid && campaign.sbid > 0;
                            html += `<td>${showSbid ? campaign.sbid.toFixed(2) : '-'}</td>`;
                            html += '</tr>';
                            html += '</tbody></table></div>';
                        });
                        } else {
                            html += '<h5 class="mb-3">PT Campaigns</h5><p class="text-muted">No PT campaigns found</p>';
                        }
                    }
                    
                    // Show empty message only if no campaigns at all
                    if (!hasHlCampaigns && (!response.kw_campaigns || !response.kw_campaigns.length) && (!response.pt_campaigns || !response.pt_campaigns.length)) {
                        html = '<p class="text-muted">No campaigns found for this SKU</p>';
                    }
                    
                    $('#campaignModalBody').html(html);
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.error || 'Failed to load campaign data';
                    $('#campaignModalBody').html(`<div class="alert alert-danger">${error}</div>`);
                }
            });
        });
    </script>
    
    <!-- Campaign Details Modal -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignModalLabel">Campaign Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="campaignModalBody">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection
