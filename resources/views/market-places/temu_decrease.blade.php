@extends('layouts.vertical', ['title' => 'Temu Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
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

        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* eBay-style color coding */
        .dil-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .dil-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .dil-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .dil-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .dil-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .dil-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
        }

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

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid #ddd;
        }

        .status-dot.green {
            background-color: #28a745;
        }

        .status-dot.red {
            background-color: #dc3545;
        }

        .status-dot.yellow {
            background-color: #ffc107;
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
        'page_title' => 'Temu Analytics',
        'sub_title' => 'Temu Analytics',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu Analytics</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Inventory Filter -->
                    <div>
                        <select id="inventory-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Inventory</option>
                            <option value="gt0" selected>INV &gt; 0</option>
                            <option value="eq0" >INV = 0</option>
                        </select>
                    </div>

                    <!-- GPFT Filter -->
                    <div>
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50-60">50-60%</option>
                            <option value="60plus">60%+</option>
                        </select>
                    </div>

                    <!-- CVR Trend Filter -->
                    <div>
                        <select id="cvr-trend-filter" class="form-select form-select-sm" style="width: 150px;">
                            <option value="all">All CVR trend</option>
                            <option value="l60_gt_l30">CVR 60 &gt; CVR 30</option>
                            <option value="l30_gt_l60">CVR 30 &gt; CVR 60</option>
                            <option value="equal">CVR 60 = CVR 30</option>
                        </select>
                    </div>

                    <!-- Arrow filter (CVR 30 vs CVR 60: up / down / equal) -->
                    <div>
                        <select id="arrow-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All arrows</option>
                            <option value="up">↑ Up (CVR 30 &gt; CVR 60)</option>
                            <option value="down">↓ Down (CVR 30 &lt; CVR 60)</option>
                            <option value="equal">＝ Equal</option>
                        </select>
                    </div>

                    <!-- CVR Filter -->
                    <div>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0.01-1">0.01-1%</option>
                            <option value="1-2">1-2%</option>
                            <option value="2-3">2-3%</option>
                            <option value="3-4">3-4%</option>
                            <option value="0-4">0-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-10">7-10%</option>
                            <option value="10plus">10%+</option>
                        </select>
                    </div>

                    <!-- DIL Filter -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.7%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.7-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- ADS Filter -->
                    <div>
                        <select id="ads-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All ADS%</option>
                            <option value="0-10">Below 10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-100">30-100%</option>
                            <option value="100plus">100%+</option>
                        </select>
                    </div>

                    <!-- SPRICE Filter -->
                    <div>
                        <select id="sprice-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All SPRICE</option>
                            <option value="blank">Blank S PRC only</option>
                            <option value="27-31">$27-$31</option>
                            <option value="lt27">&lt; $27</option>
                            <option value="gt31">&gt; $31</option>
                        </select>
                    </div>

                    <!-- Ads Req Filter -->
                    <div>
                        <select id="ads-req-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All Ads Req</option>
                            <option value="below-avg">Below Avg Views</option>
                        </select>
                    </div>

                    <!-- Ads Running Filter -->
                    <div>
                        <select id="ads-running-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Ads Status</option>
                            <option value="running">Ads Running</option>
                        </select>
                    </div>

                    <!-- NRL/REQ Filter -->
                    <div>
                        <select id="nr-req-filter" class="form-select form-select-sm" style="width: 100px;">
                            <option value="all">ALL</option>
                            <option value="NRL">NRL</option>
                            <option value="REQ" selected>REQ</option>
                        </select>
                    </div>

                    <!-- Play / Pause parent navigation (like pricing-master-cvr) -->
                    <div class="btn-group align-items-center ms-2" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause - click to reset Play">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export L30
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="export-l7-btn" title="Export L7 data from temu_daily_data_l7">
                        <i class="fa fa-download"></i> Export L7
                    </button>
                    <a href="{{ route('temu.tabulator') }}" class="btn btn-sm btn-outline-primary" title="View order-level sales data (Order ID, status, line items)">
                        <i class="fa fa-list-alt"></i> Order Data
                    </a>
                    <a href="{{ route('temu.lmp') }}" class="btn btn-sm btn-outline-secondary" title="Temu LMP table and upload">
                        <i class="fa fa-link"></i> Temu LMP
                    </a>

                    <button id="inc-dec-btn" class="btn btn-sm btn-secondary" title="Cycle: INC / DEC → Decrease → Increase → INC / DEC">
                        INC / DEC
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadViewDataModal">
                        <i class="fa fa-eye"></i> Up View Data
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadAdDataModal">
                        <i class="fa fa-chart-line"></i> Up Ad Data
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#uploadRPricingModal">
                        <i class="fa fa-tags"></i> Up R Pricing
                    </button>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#uploadPricingModal">
                        <i class="fa fa-dollar-sign"></i> Up Pricing
                    </button>
                    <button type="button" id="toggle-ads-columns-btn" class="btn btn-sm btn-secondary">
                        <i class="fa fa-filter"></i> Ads Section
                    </button>
                </div>

                <!-- Ads Count Section (shown when Show Ads Columns is on) - like TikTok -->
                <div id="temu-ads-count-section" class="mt-2 p-3 bg-light rounded border d-none">
                    <h6 class="mb-2"><i class="fa-solid fa-chart-line me-1"></i>Ads / Utilized Stats</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-sku-count" data-ads-filter="all" style="color: black; font-weight: bold; background-color: #adb5bd; cursor: pointer;" title="Click to show all">Total SKU: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-campaign-count" data-ads-filter="campaign" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has campaign">Campaign: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-ad-sku-count" data-ads-filter="ad-sku" style="color: black; font-weight: bold; background-color: #b8d4a8; cursor: pointer;" title="Click to filter: SKU active in ads with &gt;0 inventory">Ad SKU: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-missing-campaign-count" data-ads-filter="missing" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: missing campaign">Missing: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-nra-missing-count" data-ads-filter="nra-missing" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: NRA missing">NRA MISSING: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-zero-inv-count" data-ads-filter="zero-inv" style="color: black; font-weight: bold; background-color: #ffda6a; cursor: pointer;" title="Click to filter: zero inventory">Zero INV: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-nra-count" data-ads-filter="nra" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: NRA (NRL/NR)">NRA: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-ra-count" data-ads-filter="ra" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: RA (REQ)">RA: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-spend-badge" data-ads-filter="total-spend" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has spend">Total Ads Spend: $0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-budget-badge" data-ads-filter="budget" style="color: black; font-weight: bold; background-color: #ced4da; cursor: pointer;" title="Click to filter: has target/budget">Budget: $0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-ad-sales-badge" data-ads-filter="ad-sales" style="color: black; font-weight: bold; background-color: #9eeaf9; cursor: pointer;" title="Click to filter: has ad sales">Ad Sales: $0</span>
                        <span class="badge fs-6 p-2" id="temu-total-ad-sold-badge" style="color: black; font-weight: bold; background-color: #f8b4d9;" title="Total L30 Ad Sold">Total L30 Ad Sold: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-ad-clicks-badge" data-ads-filter="ad-clicks" style="color: black; font-weight: bold; background-color: #a5d6e8; cursor: pointer;" title="Click to filter: has ad clicks">Ad Clicks: 0</span>
                        <span class="badge fs-6 p-2" id="temu-total-clicks-badge" style="color: black; font-weight: bold; background-color: #a5d6e8;" title="Sum of clicks - Temu">Total Clicks: 0</span>
                        <span class="badge fs-6 p-2" id="temu-avg-clicks-badge" style="color: black; font-weight: bold; background-color: #a5d6e8;" title="Total clicks / Total Ad SKU - Temu">Avg Clicks: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-avg-acos-badge" data-ads-filter="avg-acos" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: has spend/sales">Avg ACOS: 0%</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-roas-badge" data-ads-filter="roas" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: has spend/sales">ROAS: 0.00</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                        <label class="form-label mb-0 me-1 text-nowrap" style="font-size: 0.8rem;"><i class="fa-solid fa-upload me-1"></i>Campaign:</label>
                        <input type="file" id="temu-l7-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="temu-l7-upload-btn" class="btn btn-sm btn-primary" title="Upload L7 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L7
                        </button>
                        <input type="file" id="temu-l30-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="temu-l30-upload-btn" class="btn btn-sm btn-primary" title="Upload L30 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L30
                        </button>
                        <input type="file" id="temu-l60-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="temu-l60-upload-btn" class="btn btn-sm btn-primary" title="Upload L60 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L60
                        </button>
                        <span id="temu-upload-status-container" class="ms-2" style="font-size: 0.7rem;"></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                        <label class="form-label mb-0 me-1 text-nowrap" style="font-size: 0.8rem;"><i class="fa-solid fa-upload me-1"></i>Sales:</label>
                        <input type="file" id="temu-l7-sales-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="temu-l7-sales-upload-btn" class="btn btn-sm btn-info" title="Upload L7 Sales (temu_daily_data_l7, same format as L30)" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L7 Sales
                        </button>
                        <input type="file" id="temu-l60-sales-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="temu-l60-sales-upload-btn" class="btn btn-sm btn-success" title="Upload L60 Sales (order data, same format as L30)" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L60 Sales
                        </button>
                        <span id="temu-l7-sales-upload-status" class="ms-1" style="font-size: 0.7rem;"></span>
                        <span id="temu-l60-sales-upload-status" class="ms-2" style="font-size: 0.7rem;"></span>
                    </div>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-1">Summary Statistics</h6>
                    <small class="text-muted d-block mb-2">Sums from full table (all rows, no filter)</small>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Basic Counts (sales summary = same as tabulator sales page) -->
                        <span class="badge bg-success fs-6 p-2 temu-badge-history" id="total-revenue-badge" data-badge-metric="total_sales" data-badge-label="Sales" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">Sales: $0</span>
                        <span class="badge bg-primary fs-6 p-2 temu-badge-history" id="total-orders-badge" data-badge-metric="total_orders" data-badge-label="Orders" style="color: white; font-weight: bold; cursor: pointer;" title="Click to view history">Orders: 0</span>
                        <span class="badge bg-primary fs-6 p-2 temu-badge-history" id="total-products-badge" data-badge-metric="sku_count" data-badge-label="SKU" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">SKU: 0</span>
                        <span class="badge bg-success fs-6 p-2 temu-badge-history" id="total-quantity-badge" data-badge-metric="total_quantity" data-badge-label="QTY" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">QTY: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; background-color: #dc3545; cursor: pointer;" title="Click to filter missing SKUs (INV>0)">Missing L: 0</span>
                        <span class="badge fs-6 p-2" id="not-mapped-count-badge" style="color: white; font-weight: bold; background-color: #dc3545; cursor: pointer;" title="Click to filter not mapped SKUs (INV>0)">Missing M: 0</span>
                        
                        <!-- Pricing & Performance -->
                        <span class="badge bg-info fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">AVG: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2 temu-badge-history" id="avg-cvr-badge" data-badge-metric="avg_cvr_pct" data-badge-label="CVR %" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">CVR: 0.0%</span>
                        
                        <!-- Financial Totals -->
                        <span class="badge bg-primary fs-6 p-2" id="total-profit-badge" style="color: black; font-weight: bold; display: none;">PFT: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-lp-badge" style="color: black; font-weight: bold; display: none;">Total LP: $0</span>
                        
                        <!-- Percentages (Gross) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-gprft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-groi-badge" style="color: black; font-weight: bold;">GROI: 0%</span>
                        
                        <!-- Advertising Metrics -->
                        <span class="badge fs-6 p-2 temu-badge-history" id="total-spend-badge" data-badge-metric="total_spend" data-badge-label="Spend" style="color: black; font-weight: bold; background-color: #87CEEB; cursor: pointer;" title="Click to view history">Spend: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-ads-badge" style="color: black; font-weight: bold;">Ads: 0%</span>
                        
                        <!-- Percentages (Net) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-npft-badge" style="color: black; font-weight: bold;">NPFT: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-nroi-badge" style="color: black; font-weight: bold;">NROI: 0%</span>
                        
                        <!-- Engagement -->
                        <span class="badge bg-info fs-6 p-2 temu-badge-history" id="total-views-badge" data-badge-metric="total_views" data-badge-label="Views" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">Views: 0</span>
                        <span class="badge bg-info fs-6 p-2 temu-badge-history" id="avg-views-badge" data-badge-metric="avg_views" data-badge-label="AVG views" style="color: black; font-weight: bold; cursor: pointer;" title="Click to view history">AVG views: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="badge bg-primary">0 SKUs selected</span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                               placeholder="Enter %" style="width: 150px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply 
                        </button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-amazon"></i> Suggest Amazon Price
                        </button>
                        <button id="sugg-r-prc-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-tag"></i> Suggest R Price
                        </button>
                        <button id="sprc-26-99-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-dollar-sign"></i> SPRC 26.99
                        </button>
                        <button type="button" id="clear-sprice-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU (case-insensitive)...">
                        <small id="search-result-info" class="text-muted" style="display: none;"></small>
                    </div>
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Modal: Add New + List (like Competitors), lowest LMP highlighted -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="lmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="lmpModalSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewPrice" placeholder="e.g. 29.99">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Product Link</label>
                                <input type="text" class="form-control form-control-sm" id="lmpNewLink" placeholder="https://...">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="lmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="lmpClearFormBtn" title="Clear form"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>
                    </div>
                    <h6 class="mb-2">LMP List</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="lmpListTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Price</th>
                                    <th>Link</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="lmpModalSaveBtn"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge History Modal: click on a badge to see that metric's history -->
    <div class="modal fade" id="badgeHistoryModal" tabindex="-1" aria-labelledby="badgeHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="badgeHistoryModalLabel"><i class="fas fa-history me-2"></i>History: <span id="badgeHistoryModalMetricName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label class="text-nowrap">Days:</label>
                        <select id="badgeHistoryModalDays" class="form-select form-select-sm" style="width: 90px;">
                            <option value="30">L30</option>
                            <option value="60" selected>L60</option>
                            <option value="90">L90</option>
                        </select>
                        <button type="button" id="badgeHistoryModalRefresh" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="table-responsive" style="max-height: 360px;">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Date</th><th id="badgeHistoryModalValueTh">Value</th></tr>
                            </thead>
                            <tbody id="badgeHistoryModalTbody">
                                <tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload View Data Modal -->
    <div class="modal fade" id="uploadViewDataModal" tabindex="-1" aria-labelledby="uploadViewDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadViewDataModalLabel">
                        <i class="fa fa-eye me-2"></i>Upload Temu View Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadViewDataForm" action="{{ route('temu.viewdata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="viewDataFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="viewDataFile" name="file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-lightbulb me-2"></i>
                            <strong>Note:</strong> This will INSERT new records only (no truncate/update).
                            <a href="{{ route('temu.viewdata.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadViewDataForm" class="btn btn-success">
                        <i class="fa fa-upload me-1"></i>Up View Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Ad Data Modal -->
    <div class="modal fade" id="uploadAdDataModal" tabindex="-1" aria-labelledby="uploadAdDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="uploadAdDataModalLabel">
                        <i class="fa fa-chart-line me-2"></i>Upload Temu Ad Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadAdDataForm" action="{{ route('temu.addata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="adDataFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="adDataFile" name="ad_data_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading new data!
                            <br>
                            <a href="{{ route('temu.addata.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadAdDataForm" class="btn btn-warning">
                        <i class="fa fa-upload me-1"></i>Up Ad Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload R Pricing Modal -->
    <div class="modal fade" id="uploadRPricingModal" tabindex="-1" aria-labelledby="uploadRPricingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="uploadRPricingModalLabel">
                        <i class="fa fa-tags me-2"></i>Upload Temu R Pricing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadRPricingForm" action="{{ route('temu.rpricing.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="rPricingFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="rPricingFile" name="r_pricing_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading new data!
                            <br>
                            <a href="{{ route('temu.rpricing.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadRPricingForm" class="btn btn-danger">
                        <i class="fa fa-upload me-1"></i>Up R Pricing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Pricing Modal -->
    <div class="modal fade" id="uploadPricingModal" tabindex="-1" aria-labelledby="uploadPricingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="uploadPricingModalLabel">
                        <i class="fa fa-dollar-sign me-2"></i>Upload Temu Pricing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadPricingForm" method="POST" action="{{ route('temu.pricing.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="pricingFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" name="pricing_file" id="pricingFile" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-lightbulb me-2"></i>
                            <strong>Note:</strong> This will update pricing data.
                            <br>
                            <a href="{{ route('temu.pricing.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadPricingForm" class="btn btn-info">
                        <i class="fa fa-upload me-1"></i>Up Pricing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal (UI matches Amazon: teal header, ref panel High/Med/Low, median line, value labels on points) -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Temu - <span id="modalSkuName"></span> - <span id="temuChartRefLabel">Price</span> <span id="temuChartModalSuffix">(Rolling L30)</span></span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sku-chart-days-filter" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="temuChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="skuMetricsChart"></canvas>
                        </div>
                        <div id="temuChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0; flex-wrap: nowrap; overflow-x: auto;">
                            <div class="temu-ref-col" data-metric="0" style="min-width: 62px; text-align: center; padding: 4px 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="temuChartRefDot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; flex-shrink: 0;"></span><span id="temuChartRefLabelOnly">Price</span></div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div><div id="temuCol0High" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div><div id="temuCol0Med" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div><div id="temuCol0Low" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="temuChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="chart-no-data-message" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data available for this SKU. Data will appear after running the metrics collection command.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge Trend Chart Modal (same graph as first image: teal header, line chart, median line, value labels, High/Med/Low) -->
    <div class="modal fade" id="badgeTrendChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Temu - <span id="badgeTrendChartTitle">Sales</span> <span id="badgeTrendChartSuffix">(Rolling L30)</span></span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="badgeTrendChartDays" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="badgeTrendChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="badgeTrendChartCanvas"></canvas>
                        </div>
                        <div id="badgeTrendChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0;">
                            <div style="min-width: 62px; text-align: center; padding: 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="badgeTrendChartRefDot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0dcaf0;"></span><span id="badgeTrendChartRefLabel">Sales</span></div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div><div id="badgeTrendChartHigh" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div><div id="badgeTrendChartMed" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div><div id="badgeTrendChartLow" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="badgeTrendChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="badgeTrendChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No history. Run <code>php artisan temu:collect-metrics</code> to populate.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Average Views History Modal -->
    <div class="modal fade" id="avgViewsChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-chart-line me-2"></i>Daily Average Views History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label fw-bold mb-0 me-2">Date Range:</label>
                            <select id="avg-views-days-filter" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                <option value="30" selected>Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                        <div class="text-muted">
                            <small><i class="fa fa-info-circle"></i> Shows historical average views across all products</small>
                        </div>
                    </div>
                    <div id="avg-views-no-data-message" class="alert alert-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>No Data Available:</strong> No historical data available yet. Click "Store Daily Avg" to begin tracking.
                    </div>
                    <div style="height: 400px; position: relative;">
                        <canvas id="avgViewsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "temu_decrease_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();
    let soldSpriceBlankFilterActive = false;
    let latestAvgViews = 0;
    let adsReqFilter = 'all';
    let adsRunningFilter = 'all';
    
    // SKU-specific chart (UI matches Amazon: ref panel High/Med/Low, median line, value labels on points, green/red/grey dots)
    let skuMetricsChart = null;
    let currentSku = null;
    let currentSkuChartMetric = 'price';
    let temuChartFirstSeriesStats = null; // { values, median, dataMin, dataMax, dotColors, labelColors, valueFmt }

    // Badge trend chart (same graph as first image)
    let badgeTrendChart = null;
    let badgeChartFirstSeriesStats = null;
    let currentBadgeChartMetricKey = '';
    let currentBadgeChartLabel = '';

    // Average Views chart
    let avgViewsChart = null;

    function temuChartFmtVal(v) {
        if (currentSkuChartMetric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return (Number(v) === v ? v.toFixed(1) : v) + '%';
        return Math.round(Number(v) || 0).toLocaleString('en-US');
    }

    function initSkuMetricsChart() {
        const ctx = document.getElementById('skuMetricsChart').getContext('2d');

        const medianLinePlugin = {
            id: 'temuMedianLine',
            afterDraw(chart) {
                if (!temuChartFirstSeriesStats || temuChartFirstSeriesStats.median === undefined) return;
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const cctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(temuChartFirstSeriesStats.median);
                cctx.save();
                cctx.setLineDash([6, 4]);
                cctx.strokeStyle = '#6c757d';
                cctx.lineWidth = 1.2;
                cctx.beginPath();
                cctx.moveTo(xScale.left, yPixel);
                cctx.lineTo(xScale.right, yPixel);
                cctx.stroke();
                cctx.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'temuValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 7px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                const valueFmt = (temuChartFirstSeriesStats && temuChartFirstSeriesStats.valueFmt) ? temuChartFirstSeriesStats.valueFmt : temuChartFmtVal;
                const labelColors = temuChartFirstSeriesStats && temuChartFirstSeriesStats.labelColors ? temuChartFirstSeriesStats.labelColors : [];
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    if (val == null) return;
                    const offsetY = (i % 2 === 0) ? -7 : -14;
                    cctx.fillStyle = labelColors[i] || '#6c757d';
                    cctx.fillText(valueFmt(val), point.x, point.y + offsetY);
                });
                cctx.restore();
            }
        };

        skuMetricsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Price',
                    data: [],
                    borderColor: '#008000',
                    backgroundColor: 'rgba(0, 128, 0, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    fill: true,
                    spanGaps: true
                }]
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const v = context.parsed.y;
                                if (v == null) return '';
                                if (currentSkuChartMetric === 'price') return 'Price: $' + Number(v).toFixed(2);
                                if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return (context.dataset.label || '') + ': ' + Number(v).toFixed(1) + '%';
                                return (currentSkuChartMetric === 'views' || currentSkuChartMetric === 'temu_l30') ? (context.dataset.label + ': ' + Math.round(v)) : (context.dataset.label + ': ' + v);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: { font: { size: 9 }, callback: function(v) {
                            if (currentSkuChartMetric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v));
                            if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return v.toFixed(0) + '%';
                            return Math.round(v);
                        } }
                    }
                }
            }
        });
    }

    function badgeChartValueFmt(metricKey, v) {
        var n = Number(v);
        if (metricKey === 'total_sales' || metricKey === 'total_spend') return '$' + (n % 1 !== 0 ? n.toFixed(2) : Math.round(n).toLocaleString('en-US'));
        if (metricKey === 'avg_cvr_pct') return n.toFixed(2) + '%';
        if (metricKey === 'avg_views') return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
        return Math.round(n).toLocaleString('en-US');
    }

    function initBadgeTrendChart() {
        const ctx = document.getElementById('badgeTrendChartCanvas').getContext('2d');
        const medianLinePlugin = {
            id: 'badgeMedianLine',
            afterDraw(chart) {
                if (!badgeChartFirstSeriesStats || badgeChartFirstSeriesStats.median === undefined) return;
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const cctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(badgeChartFirstSeriesStats.median);
                cctx.save();
                cctx.setLineDash([6, 4]);
                cctx.strokeStyle = '#6c757d';
                cctx.lineWidth = 1.2;
                cctx.beginPath();
                cctx.moveTo(xScale.left, yPixel);
                cctx.lineTo(xScale.right, yPixel);
                cctx.stroke();
                cctx.restore();
            }
        };
        const valueLabelsPlugin = {
            id: 'badgeValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 7px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                const valueFmt = (badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.valueFmt) ? badgeChartFirstSeriesStats.valueFmt : function(v) { return badgeChartValueFmt(currentBadgeChartMetricKey, v); };
                const labelColors = badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.labelColors ? badgeChartFirstSeriesStats.labelColors : [];
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    if (val == null) return;
                    const offsetY = (i % 2 === 0) ? -7 : -14;
                    cctx.fillStyle = labelColors[i] || '#6c757d';
                    cctx.fillText(valueFmt(val), point.x, point.y + offsetY);
                });
                cctx.restore();
            }
        };
        badgeTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Value',
                    data: [],
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    fill: true,
                    spanGaps: true
                }]
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const v = context.parsed.y;
                                if (v == null) return '';
                                return (badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.valueFmt ? badgeChartFirstSeriesStats.valueFmt(v) : badgeChartValueFmt(currentBadgeChartMetricKey, v));
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: { font: { size: 9 }, callback: function(v) {
                            return badgeChartValueFmt(currentBadgeChartMetricKey, v);
                        } }
                    }
                }
            }
        });
    }

    function loadBadgeChartData(metricKey, metricLabel, days) {
        currentBadgeChartMetricKey = metricKey || currentBadgeChartMetricKey;
        currentBadgeChartLabel = metricLabel || currentBadgeChartLabel;
        days = days || parseInt($('#badgeTrendChartDays').val(), 10) || 30;
        $('#badgeTrendChartLoading').show();
        $('#badgeTrendChartContainer').hide();
        $('#badgeTrendChartNoData').hide();
        fetch('/temu-badge-history?days=' + encodeURIComponent(days))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                $('#badgeTrendChartLoading').hide();
                if (!badgeTrendChart) return;
                var data = res.data || [];
                var key = currentBadgeChartMetricKey;
                if (!data.length) {
                    badgeChartFirstSeriesStats = null;
                    $('#badgeTrendChartHigh, #badgeTrendChartMed, #badgeTrendChartLow').text('-');
                    badgeTrendChart.data.labels = [];
                    badgeTrendChart.data.datasets[0].data = [];
                    badgeTrendChart.update('active');
                    $('#badgeTrendChartContainer').hide();
                    $('#badgeTrendChartNoData').show();
                    return;
                }
                $('#badgeTrendChartNoData').hide();
                $('#badgeTrendChartContainer').show();
                var labels = data.map(function(d) { return d.record_date; });
                var values = data.map(function(d) { return Number(d[key]) || 0; });
                var refFmt = function(v) { return badgeChartValueFmt(key, v); };
                function statsForArr(arr) {
                    var valid = arr.filter(function(v) { return v != null && !isNaN(v); });
                    if (valid.length === 0) return { min: 0, max: 0, median: 0 };
                    var min = Math.min.apply(null, valid);
                    var max = Math.max.apply(null, valid);
                    var sorted = valid.slice().sort(function(a, b) { return a - b; });
                    var mid = Math.floor(sorted.length / 2);
                    var median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                    return { min: min, max: max, median: median };
                }
                var s0 = statsForArr(values);
                var refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                $('#badgeTrendChartHigh').text(refFmt(s0.max)).css('color', refRed);
                $('#badgeTrendChartMed').text(refFmt(s0.median)).css('color', refGray);
                $('#badgeTrendChartLow').text(refFmt(s0.min)).css('color', refGreen);
                $('#badgeTrendChartRefLabel').text(currentBadgeChartLabel);
                var dotColors = values.map(function(v, i) {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? refGreen : v < values[i - 1] ? refRed : refGray;
                });
                var labelColors = values.map(function(v) { return v === 0 ? refGreen : v > 0 ? refRed : refGray; });
                badgeChartFirstSeriesStats = { values: values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors: dotColors, labelColors: labelColors, valueFmt: refFmt };
                badgeTrendChart.data.labels = labels;
                badgeTrendChart.data.datasets[0].data = values;
                badgeTrendChart.data.datasets[0].pointBackgroundColor = dotColors;
                badgeTrendChart.data.datasets[0].pointBorderColor = dotColors;
                badgeTrendChart.data.datasets[0].pointBorderWidth = 1.5;
                var range = (s0.max - s0.min) || Math.max(Math.abs(s0.min) * 0.1, 1);
                if (badgeTrendChart.options.scales && badgeTrendChart.options.scales.y) {
                    badgeTrendChart.options.scales.y.min = Math.max(0, s0.min - range * 0.1);
                    badgeTrendChart.options.scales.y.max = s0.max + range * 0.1;
                }
                badgeTrendChart.update('active');
            })
            .catch(function() {
                $('#badgeTrendChartLoading').hide();
                badgeChartFirstSeriesStats = null;
                $('#badgeTrendChartHigh, #badgeTrendChartMed, #badgeTrendChartLow').text('-');
                $('#badgeTrendChartContainer').hide();
                $('#badgeTrendChartNoData').show();
            });
    }

    function loadSkuMetricsData(sku, days = 30, metricOverride) {
        const chartMetric = metricOverride != null ? metricOverride : (currentSkuChartMetric || 'price');
        $('#temuChartLoading').show();
        $('#temuChartContainer').hide();
        $('#chart-no-data-message').hide();
        fetch(`/temu-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                $('#temuChartLoading').hide();
                if (!skuMetricsChart) return;
                function setTemuRefCol(high, med, low, fmt) {
                    const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                    const hEl = document.getElementById('temuCol0High');
                    const mEl = document.getElementById('temuCol0Med');
                    const lEl = document.getElementById('temuCol0Low');
                    if (hEl) { hEl.textContent = fmt(high); hEl.style.color = high === 0 ? refGreen : high > 0 ? refRed : refGray; }
                    if (mEl) { mEl.textContent = fmt(med); mEl.style.color = med === 0 ? refGreen : med > 0 ? refRed : refGray; }
                    if (lEl) { lEl.textContent = fmt(low); lEl.style.color = low === 0 ? refGreen : low > 0 ? refRed : refGray; }
                }
                function statsForArr(arr) {
                    const valid = arr.filter(v => v != null && !isNaN(v));
                    if (valid.length === 0) return { min: 0, max: 0, median: 0 };
                    const min = Math.min(...valid);
                    const max = Math.max(...valid);
                    const sorted = [...valid].sort((a, b) => a - b);
                    const mid = Math.floor(sorted.length / 2);
                    const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                    return { min, max, median };
                }
                if (!data || data.length === 0) {
                    temuChartFirstSeriesStats = null;
                    const h = document.getElementById('temuCol0High');
                    const m = document.getElementById('temuCol0Med');
                    const l = document.getElementById('temuCol0Low');
                    if (h) h.textContent = '-';
                    if (m) m.textContent = '-';
                    if (l) l.textContent = '-';
                    skuMetricsChart.data.labels = [];
                    skuMetricsChart.data.datasets[0].data = [];
                    skuMetricsChart.update('active');
                    $('#temuChartContainer').hide();
                    $('#chart-no-data-message').show();
                    return;
                }
                $('#chart-no-data-message').hide();
                $('#temuChartContainer').show();
                const labels = data.map(d => d.date_formatted || d.date || '');
                const metric = chartMetric;
                const isCvr = metric === 'cvr';
                const isViews = metric === 'views';
                const isTemuL30 = metric === 'temu_l30';
                const isPct = ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(metric) >= 0;
                const values = isCvr ? data.map(d => Number(d.cvr_percent) || 0) : isViews ? data.map(d => Number(d.views) || 0) : isTemuL30 ? data.map(d => Number(d.temu_l30) || 0) : isPct ? data.map(d => Number(d[metric]) || 0) : data.map(d => Number(d.price) || 0);
                const temuChartMetricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
                const temuChartMetricColors = { price: '#adb5bd', views: '#0000FF', cvr: '#008000', temu_l30: '#fd7e14', profit_percent: '#ff1493', ads_percent: '#ffc107', roi_percent: '#6f42c1', npft_percent: '#28a745', nroi_percent: '#17a2b8' };
                const bgColors = { price: 'rgba(108,117,125,0.08)', views: 'rgba(0,0,255,0.1)', cvr: 'rgba(0,128,0,0.1)', temu_l30: 'rgba(253,126,20,0.1)', profit_percent: 'rgba(255,20,147,0.1)', ads_percent: 'rgba(255,193,7,0.1)', roi_percent: 'rgba(111,66,193,0.1)', npft_percent: 'rgba(40,167,69,0.1)', nroi_percent: 'rgba(23,162,184,0.1)' };
                const labelText = temuChartMetricLabels[metric] || 'Price';
                const color = temuChartMetricColors[metric] || '#adb5bd';
                const refLabelEl = document.getElementById('temuChartRefLabel');
                const refLabelOnlyEl = document.getElementById('temuChartRefLabelOnly');
                const refDotEl = document.getElementById('temuChartRefDot');
                if (refLabelEl) refLabelEl.textContent = labelText;
                if (refLabelOnlyEl) refLabelOnlyEl.textContent = labelText;
                if (refDotEl) refDotEl.style.background = color;
                const cvrFmt = v => (Number(v) === v ? v.toFixed(1) : v) + '%';
                const intFmt = v => Math.round(Number(v) || 0).toLocaleString('en-US');
                const refFmt = (isCvr || isPct) ? cvrFmt : (isViews || isTemuL30) ? intFmt : temuChartFmtVal;
                skuMetricsChart.data.labels = labels;
                skuMetricsChart.data.datasets[0].data = values;
                skuMetricsChart.data.datasets[0].label = labelText + (metric === 'price' ? ' (USD)' : '');
                skuMetricsChart.data.datasets[0].borderColor = color;
                skuMetricsChart.data.datasets[0].backgroundColor = bgColors[metric] || 'rgba(108,117,125,0.08)';
                if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y && skuMetricsChart.options.scales.y.ticks) {
                    skuMetricsChart.options.scales.y.ticks.callback = function(v) {
                        if (metric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v));
                        if (metric === 'cvr') return v.toFixed(0) + '%';
                        return Math.round(v);
                    };
                }
                const s0 = statsForArr(values);
                setTemuRefCol(s0.max, s0.median, s0.min, refFmt);
                const refRed = '#dc3545';
                const refGray = '#6c757d';
                const refGreen = '#198754';
                const dotColors = values.map((v, i) => {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                });
                const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);
                temuChartFirstSeriesStats = { values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors, labelColors, valueFmt: refFmt };
                skuMetricsChart.data.datasets[0].pointBackgroundColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderWidth = 1.5;
                skuMetricsChart.update('active');
            })
            .catch(error => {
                $('#temuChartLoading').hide();
                temuChartFirstSeriesStats = null;
                const h = document.getElementById('temuCol0High');
                const m = document.getElementById('temuCol0Med');
                const l = document.getElementById('temuCol0Low');
                if (h) h.textContent = '-';
                if (m) m.textContent = '-';
                if (l) l.textContent = '-';
                $('#temuChartContainer').hide();
                $('#chart-no-data-message').show();
                console.error('Error loading Temu SKU metrics:', error);
            });
    }
    
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

    function initAvgViewsChart() {
        const ctx = document.getElementById('avgViewsChart').getContext('2d');

        const avgViewsValueLabelsPlugin = {
            id: 'avgViewsValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 11px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                cctx.fillStyle = '#28a745';
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    if (val != null && val !== '') cctx.fillText(Math.round(val), point.x, point.y - 8);
                });
                cctx.restore();
            }
        };

        avgViewsChart = new Chart(ctx, {
            type: 'line',
            plugins: [avgViewsValueLabelsPlugin],
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Average Views',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Average Views Trend',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Average Views: ' + Math.round(context.parsed.y);
                            },
                            afterLabel: function(context) {
                                const dataIndex = context.dataIndex;
                                const dataset = avgViewsChart.data.datasets[0];
                                if (dataset.totalProducts && dataset.totalProducts[dataIndex]) {
                                    return 'Products: ' + dataset.totalProducts[dataIndex];
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Average Views',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return Math.round(value);
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    function loadAvgViewsHistory(days = 30) {
        fetch(`/temu-avg-views-history?days=${days}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (avgViewsChart) {
                    if (!data || data.length === 0) {
                        $('#avg-views-no-data-message').show();
                        avgViewsChart.data.labels = [];
                        avgViewsChart.data.datasets[0].data = [];
                        avgViewsChart.update();
                        return;
                    }
                    
                    $('#avg-views-no-data-message').hide();
                    
                    avgViewsChart.data.labels = data.map(d => d.date);
                    avgViewsChart.data.datasets[0].data = data.map(d => parseFloat(d.avg_views));
                    
                    // Store additional data for tooltip
                    avgViewsChart.data.datasets[0].totalProducts = data.map(d => d.total_products);
                    
                    avgViewsChart.update();
                }
            })
            .catch(error => {
                console.error('Error loading average views history:', error);
                showToast('Failed to load average views history', 'error');
            });
    }

    function storeDailyAvgViews() {
        const data = table.getData('active');
        
        if (!data || data.length === 0) {
            showToast('No data available to calculate average', 'error');
            return;
        }
        
        const totalViews = data.reduce((sum, row) => sum + (parseInt(row['product_clicks']) || 0), 0);
        const totalProducts = data.length;
        const avgViews = totalViews / totalProducts;
        
        $.ajax({
            url: '/temu-store-daily-avg-views',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                avg_views: avgViews,
                total_products: totalProducts,
                total_views: totalViews
            },
            success: function(response) {
                if (response.success) {
                    showToast(`Daily average views stored successfully (${Math.round(avgViews)} avg)`, 'success');
                    // Update the latest avg views for filtering
                    latestAvgViews = avgViews;
                } else {
                    showToast('Failed to store daily average views', 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to store daily average views', 'error');
            }
        });
    }

    function autoStoreDailyAvgViews() {
        // Check if today's record already exists
        fetch('/temu-latest-avg-views')
            .then(response => {
                if (!response.ok) {
                    // If table doesn't exist or server error, silently fail
                    return response.json().catch(() => ({ avg_views: 0 }));
                }
                return response.json();
            })
            .then(data => {
                const today = new Date().toISOString().split('T')[0];
                const latestDate = data && data.date ? data.date : null;
                
                // If no record for today, store it automatically
                if (latestDate !== today) {
                    const tableData = table.getData('active');
                    
                    if (tableData && tableData.length > 0) {
                        const totalViews = tableData.reduce((sum, row) => sum + (parseInt(row['product_clicks']) || 0), 0);
                        const totalProducts = tableData.length;
                        const avgViews = totalViews / totalProducts;
                        
                        $.ajax({
                            url: '/temu-store-daily-avg-views',
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                avg_views: avgViews,
                                total_products: totalProducts,
                                total_views: totalViews
                            },
                            success: function(response) {
                                if (response.success) {
                                    console.log(`Auto-stored daily average: ${Math.round(avgViews)} views`);
                                    latestAvgViews = avgViews;
                                }
                            },
                            error: function(xhr) {
                                // Silently fail - table might not exist
                                // Don't show error to user as this is a background operation
                                if (xhr.status !== 500) {
                                    console.error('Failed to auto-store daily average views');
                                }
                            }
                        });
                    }
                } else {
                    // Update the latest avg for filtering
                    if (data && data.avg_views) {
                        latestAvgViews = parseFloat(data.avg_views);
                    }
                }
            })
            .catch(error => {
                // Silently fail - table might not exist
                // This is a background operation, don't show errors to user
            });
    }

    function loadLatestAvgViews() {
        fetch('/temu-latest-avg-views')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.avg_views) {
                    latestAvgViews = parseFloat(data.avg_views);
                }
            })
            .catch(error => {
                console.error('Error loading latest average views:', error);
            });
    }

    $(document).ready(function() {
        // Initialize SKU-specific chart
        initSkuMetricsChart();
        initBadgeTrendChart();

        // Initialize Average Views chart
        initAvgViewsChart();

        // Load latest average views for filtering
        loadLatestAvgViews();

        // SKU chart days filter
        $('#sku-chart-days-filter').on('change', function() {
            const days = $(this).val();
            const daysNum = parseInt(days, 10);
            const rangeLabel = daysNum === 60 ? 'L60' : daysNum === 14 ? 'L14' : daysNum === 7 ? 'L7' : 'L30';
            $('#temuChartModalSuffix').text('(Rolling ' + rangeLabel + ')');
            if (currentSku) loadSkuMetricsData(currentSku, daysNum || 30);
        });

        // Average Views chart days filter
        $('#avg-views-days-filter').on('change', function() {
            const days = $(this).val();
            loadAvgViewsHistory(days);
        });

        // Event delegation for chart button clicks (column-wise metric, same as Amazon)
        $(document).on('click', '.view-sku-chart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const el = e.target.closest ? e.target.closest('.view-sku-chart') : $(this)[0];
            const sku = $(el).data('sku');
            currentSkuChartMetric = (el.getAttribute ? el.getAttribute('data-metric') : $(el).data('metric')) || 'price';
            currentSku = sku;
            $('#modalSkuName').text(sku);
            const metricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
            $('#temuChartRefLabel').text(metricLabels[currentSkuChartMetric] || 'Price');
            $('#temuChartModalSuffix').text('(Rolling L30)');
            $('#sku-chart-days-filter').val('30');
            $('#chart-no-data-message').hide();
            loadSkuMetricsData(sku, 30, currentSkuChartMetric);
            $('#skuMetricsModal').modal('show');
        });

        $(document).on('click', '.copy-goods-id-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const raw = this.getAttribute('data-goods-id');
            if (!raw) return;
            const id = raw;
            navigator.clipboard.writeText(id).then(function() {
                showToast('Goods ID copied to clipboard', 'success');
            }).catch(function() {
                showToast('Failed to copy Goods ID', 'error');
            });
        });

        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() {
            const discountType = $(this).val();
            const $input = $('#discount-percentage-input');
            
            if (discountType === 'percentage') {
                $input.attr('placeholder', 'Enter %');
            } else {
                $input.attr('placeholder', 'Enter $');
            }
        });

        // INC / DEC: one button, cycle neutral → decrease → increase → neutral
        $('#inc-dec-btn').on('click', function() {
            const selectColumn = table.getColumn('_select');
            const $btn = $(this);

            if (!decreaseModeActive && !increaseModeActive) {
                decreaseModeActive = true;
                selectColumn.show();
                $btn.removeClass('btn-secondary').addClass('btn-danger').html('<i class="fas fa-arrow-down"></i> DEC <i class="fas fa-times ms-1" title="Click again for INC"></i>');
            } else if (decreaseModeActive) {
                decreaseModeActive = false;
                increaseModeActive = true;
                $btn.removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> INC <i class="fas fa-times ms-1" title="Click again to reset"></i>');
            } else {
                increaseModeActive = false;
                selectColumn.hide();
                selectedSkus.clear();
                soldSpriceBlankFilterActive = false;
                updateSelectedCount();
                updateSelectAllCheckbox();
                applyFilters();
                $btn.removeClass('btn-danger btn-success').addClass('btn-secondary').html('INC / DEC');
            }
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active');
            
            filteredData.forEach(row => {
                const sku = row['sku'];
                if (sku) {
                    if (isChecked) {
                        selectedSkus.add(sku);
                    } else {
                        selectedSkus.delete(sku);
                    }
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

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

        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        $('#sugg-r-prc-btn').on('click', function() {
            applySuggestRPrice();
        });

        $('#clear-sprice-btn').on('click', function() {
            if (confirm('Are you sure you want to clear all SPRICE data? This action cannot be undone.')) {
                clearAllSprice();
            }
        });

        $('#sprc-26-99-btn').on('click', function() {
            applySprice2699();
        });


        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        // Badge click handlers for filtering
        let zeroSoldFilterActive = false;
        let lessAmzFilterActive = false;
        let moreAmzFilterActive = false;
        let missingBadgeFilterActive = false;
        let mapBadgeFilterActive = false;
        let notMapBadgeFilterActive = false;

        $('#zero-sold-count-badge').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            applyFilters();
        });

        $('#missing-count-badge').on('click', function() {
            missingBadgeFilterActive = !missingBadgeFilterActive;
            applyFilters();
            if (table) {
                if (missingBadgeFilterActive) {
                    table.getColumn('lmp').show();
                    table.getColumn('lmp_minus_15').show();
                }
                // LMP columns stay visible when Missing L is off (no hide)
            }
        });

        $('#not-mapped-count-badge').on('click', function() {
            notMapBadgeFilterActive = !notMapBadgeFilterActive;
            mapBadgeFilterActive = false;
            applyFilters();
            if (table) {
                if (notMapBadgeFilterActive) table.getColumn('MAP').show();
                else table.getColumn('MAP').hide();
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active');
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        function roundToRetailPrice(price) {
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }
        function roundToRetailPrice49(price) {
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                if (row) {
                    row.update({ sprice_status: 'processing' });
                }
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const newPriceNum = typeof sprice === 'number' ? sprice : parseFloat(sprice);
                        let targetRow = row;
                        if (table) {
                            const found = table.getRows().find(r => (r.getData().sku || '') === sku);
                            if (found) targetRow = found;
                        }
                        if (targetRow) {
                            targetRow.update({
                                sprice: newPriceNum,
                                sgprft_percent: response.sgprft_percent,
                                sroi_percent: response.sroi_percent,
                                sprice_status: 'saved'
                            });
                            targetRow.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        
                        if (retryCount < 1) {
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            if (row) {
                                row.update({ sprice_status: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

        function applyDiscount() {
            const rawInput = $('#discount-percentage-input').val();
            const discountValue = parseFloat(String(rawInput).replace(',', '.')) || 0;
            const discountType = $('#discount-type-select').val();
            
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast('Please enter a valid discount value', 'error');
                return;
            }
            if (discountType === 'percentage' && discountValue > 100 && !increaseModeActive) {
                showToast('Discount percentage cannot exceed 100%', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;

            allData.forEach(row => {
                const sku = row['sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['base_price']) || 0;
                    if (currentPrice > 0) {
                        let newSPrice;
                        
                        if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSPrice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSPrice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSPrice = currentPrice + discountValue;
                            } else {
                                newSPrice = currentPrice - discountValue;
                            }
                        }
                        
                        newSPrice = Math.max(0.01, newSPrice);
                        const originalPrice = currentPrice;
                        newSPrice = roundToRetailPrice(newSPrice);
                        if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                            newSPrice = roundToRetailPrice49(newSPrice);
                        }
                        const newPriceNum = parseFloat(newSPrice.toFixed(2));
                        
                        const originalSPrice = parseFloat(row['sprice']) || 0;
                        
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['sku'] === sku;
                        });
                        
                        if (tableRow) {
                            tableRow.update({ 
                                sprice: newPriceNum,
                                sprice_status: 'processing'
                            });
                            tableRow.reformat();
                        }
                        
                        saveSpriceWithRetry(sku, newPriceNum, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s)`, 'success');
                                    } else {
                                        showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ sprice: originalSPrice });
                                    tableRow.reformat();
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                }
                            });
                    }
                }
            });
            
            $('#discount-percentage-input').val('');
        }

        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['a_price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        row.update({
                            sprice: amazonPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            amazon_price: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuAmazonPriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuAmazonPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-amazon-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save Amazon prices', 'error');
                }
            });
        }

        function applySuggestRPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noRPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const rPrice = parseFloat(rowData['recommended_base_price']);
                    
                    if (rPrice && rPrice > 0) {
                        row.update({
                            sprice: rPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            r_price: rPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noRPriceCount++;
                    }
                } else {
                    noRPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuRPriceUpdates(updates);
            }
            
            let message = `R price applied to ${updatedCount} SKU(s)`;
            if (noRPriceCount > 0) {
                message += ` (${noRPriceCount} SKU(s) had no R price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuRPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-r-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save R prices', 'error');
                }
            });
        }

        function applySprice2699() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            const updates = [];
            const targetPrice = 26.99;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    
                    // Update the row with new SPRICE
                    row.update({ 
                        sprice: targetPrice
                    });
                    row.reformat();
                    
                    // Add to batch update
                    updates.push({
                        sku: sku,
                        sprice: targetPrice
                    });
                    
                    updatedCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuSprice2699Updates(updates);
            }
            
            showToast(`SPRICE set to $26.99 for ${updatedCount} SKU(s)`, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuSprice2699Updates(updates) {
            let saved = 0;
            let errors = 0;
            
            updates.forEach((update, index) => {
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: update.sku,
                        sprice: update.sprice
                    },
                    success: function(response) {
                        saved++;
                        if (index === updates.length - 1) {
                            showToast(`SPRICE $26.99 saved for ${saved} SKU(s)`, 'success');
                            table.redraw();
                        }
                    },
                    error: function(xhr) {
                        errors++;
                        if (index === updates.length - 1) {
                            if (errors === updates.length) {
                                showToast('Failed to save SPRICE', 'error');
                            } else {
                                showToast(`SPRICE saved for ${saved} SKU(s), ${errors} failed`, 'warning');
                            }
                        }
                    }
                });
            });
        }

        function selectSoldWithBlankSprice() {
            // Get all table data
            const allData = table.getData('all');
            let newlySelectedCount = 0;
            
            // Don't clear current selection - only add unselected items
            
            // Select SKUs where INV > 0 AND Temu L30 > 0 AND SPRICE is null/blank AND not already selected
            allData.forEach(row => {
                const temuL30Val = row['temu_l30'];
                const spriceVal = row['sprice'];
                const invVal = row['inventory'];
                const sku = row['sku'];
                
                // Parse temu_l30 - must be a positive number
                const temuL30 = temuL30Val ? parseInt(temuL30Val) : 0;
                const inventory = invVal ? parseInt(invVal) : 0;
                
                // Check if sprice is null, undefined, empty string, or 0
                const spriceIsBlank = !spriceVal || spriceVal === '' || spriceVal === 0 || parseFloat(spriceVal) === 0;
                
                // Only select if: has SKU AND inventory > 0 AND temu sold > 0 AND sprice is blank AND not already selected
                if (sku && inventory > 0 && temuL30 > 0 && spriceIsBlank && !selectedSkus.has(sku)) {
                    selectedSkus.add(sku);
                    newlySelectedCount++;
                }
            });
            
            // Set the filter flag and reapply all filters
            soldSpriceBlankFilterActive = true;
            applyFilters();
            
            // Update UI
            updateSelectedCount();
            updateSelectAllCheckbox();
            updateSummary();
            
            // Update checkboxes
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            // Show selection mode if items found
            if (newlySelectedCount > 0 || selectedSkus.size > 0) {
                const selectColumn = table.getColumn('_select');
                selectColumn.show();
                
                if (!decreaseModeActive && !increaseModeActive) {
                    decreaseModeActive = true;
                    $('#inc-dec-btn').removeClass('btn-secondary').addClass('btn-danger').html('<i class="fas fa-arrow-down"></i> DEC <i class="fas fa-times ms-1" title="Click again for INC"></i>');
                }
                
                if (newlySelectedCount > 0) {
                    showToast(`Added ${newlySelectedCount} sold SKU(s) with blank SPRICE to selection (Total: ${selectedSkus.size})`, 'success');
                } else {
                    showToast(`Filtered to show sold items with blank SPRICE (${selectedSkus.size} already selected)`, 'info');
                }
            } else {
                showToast('No sold items with blank SPRICE found', 'info');
            }
        }

        function clearAllSprice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            const skusArray = Array.from(selectedSkus);
            
            $.ajax({
                url: '/temu-clear-sprice',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    skus: skusArray
                },
                beforeSend: function() {
                    $('#clear-sprice-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing...');
                },
                success: function(response) {
                    if (response.success) {
                        // Update the table rows
                        skusArray.forEach(sku => {
                            const rows = table.searchRows("sku", "=", sku);
                            if (rows.length > 0) {
                                rows[0].update({ sprice: null });
                                rows[0].reformat();
                            }
                        });
                        
                        showToast(`Successfully cleared SPRICE for ${response.cleared} SKU(s)`, 'success');
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to clear SPRICE data', 'error');
                },
                complete: function() {
                    $('#clear-sprice-btn').prop('disabled', false).html('<i class="fa fa-trash"></i> Clear SPRICE');
                }
            });
        }

        function updateSummary() {
            // Sum from table directly with no filter: use full dataset (getData("all"))
            const data = table.getData("all");
            
            let totalProducts = data.length;
            let totalQuantity = 0;
            let totalPriceWeighted = 0;
            let totalQty = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalLp = 0;
            let totalGprft = 0;
            let totalGroi = 0;
            let totalAds = 0;
            let totalNpft = 0;
            let totalNroi = 0;
            let totalCvr = 0;
            let totalDil = 0;
            let totalSpend = 0;
            let totalSpendL30 = 0; // Total spend_l30 for aggregate Ads% calculation (matches all-marketplace-master)
            let totalViews = 0;
            let totalTemuL30 = 0;
            let totalInv = 0;
            let cvrCount = 0;
            let dilCount = 0;
            let zeroSoldCount = 0;
            let missingCount = 0;
            let mappedCount = 0;
            let notMappedCount = 0;
            let lessAmzCount = 0;
            let moreAmzCount = 0;
            
            data.forEach(row => {
                const temuL30 = parseInt(row['temu_l30']) || 0;
                const price = parseFloat(row['base_price']) || 0;
                const temuPrice = parseFloat(row['temu_price']) || 0;  // Temu Price column = price for PFT formula
                const lpPerUnit = parseFloat(row['lp']) || 0;
                const temuShip = parseFloat(row['temu_ship']) || 0;

                totalQuantity += temuL30;
                totalPriceWeighted += price * temuL30;
                totalQty += temuL30;

                // Only include rows with sales (Temu L30 > 0 and basePrice > 0) in PFT/revenue/COGS
                // Match marketplace_daily_metrics calculation: fbPrice = (basePrice * quantity < 27) ? basePrice + 2.99 : basePrice
                const hasSales = temuL30 > 0 && price > 0;
                if (hasSales) {
                    // Calculate fbPrice same as marketplace_daily_metrics: check if basePrice * quantity < 27
                    const total = price * temuL30;
                    const fbPrice = total < 27 ? price + 2.99 : price;
                    // PFT % formula: (price * 0.96 - lp - temuship) / price — use fbPrice as price
                    const pftDecimal = fbPrice > 0 ? (fbPrice * 0.96 - lpPerUnit - temuShip) / fbPrice : 0;
                    const rowProfit = pftDecimal * fbPrice * temuL30;
                    totalRevenue += fbPrice * temuL30; // Use fbPrice for revenue (matches marketplace_daily_metrics total_sales)
                    totalProfit += rowProfit;
                    totalLp += lpPerUnit * temuL30;
                }

                // Percentage metrics (for fallback simple average when no revenue/COGS)
                totalGprft += parseFloat(row['profit_percent']) || 0;
                totalGroi += parseFloat(row['roi_percent']) || 0;
                totalAds += parseFloat(row['ads_percent']) || 0;
                totalNpft += parseFloat(row['npft_percent']) || 0;
                totalNroi += parseFloat(row['nroi_percent']) || 0;
                
                // CVR% (only count non-zero values for average)
                const cvr = parseFloat(row['cvr_percent']) || 0;
                if (cvr > 0) {
                    totalCvr += cvr;
                    cvrCount++;
                }
                
                // DIL% (only count non-zero values for average)
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                // Ad spend and views
                totalSpend += parseFloat(row['spend']) || 0;
                // Use spend_l30 ONLY (no fallback to spend) to match all-marketplace-master fetchTotalAdSpendFromTables
                totalSpendL30 += parseFloat(row['spend_l30'] || 0);
                totalViews += parseInt(row['product_clicks']) || 0;
                totalTemuL30 += temuL30;
                
                // Declare common variables once for this row
                const inventory = parseFloat(row['inventory']) || 0;
                const missing = row['missing'];
                const goodsId = row['goods_id'];
                const temuStock = parseFloat(row['temu_stock']) || 0;
                
                totalInv += parseInt(row['inventory']) || 0;
                
                // Count SKUs with 0 sold (Temu L30 = 0 AND INV > 0)
                if (temuL30 === 0 && inventory > 0) {
                    zeroSoldCount++;
                }
                
                // Count missing SKUs (only count if INV > 0)
                if (missing === 'M' && inventory > 0) {
                    missingCount++;
                }
                
                // Count MAP status - ONLY for items that exist in Temu (not missing)
                // Skip missing items - same logic as eBay (only count if exists in marketplace)
                if (missing !== 'M' && goodsId && goodsId !== '') {
                    
                    if (inventory > 0 && temuStock > 0) {
                        if (inventory === temuStock) {
                            mappedCount++; // MP (Mapped)
                        } else {
                            notMappedCount++; // N MP (Not Mapped - mismatch)
                        }
                    } else if (inventory > 0 && temuStock === 0) {
                        notMappedCount++; // N MP (Not Mapped - no Temu stock)
                    }
                }
                
                // Count < Amz and > Amz (compare Temu Price with Amazon Price)
                // temuPrice already declared above, reuse it
                const amazonPrice = parseFloat(row['a_price']) || 0;
                
                if (amazonPrice > 0 && temuPrice > 0) {
                    if (temuPrice < amazonPrice) {
                        lessAmzCount++; // Temu Price < Amazon Price
                    } else if (temuPrice > amazonPrice) {
                        moreAmzCount++; // Temu Price > Amazon Price
                    }
                }
            });
            
            // Calculate averages
            const avgPrice = totalQty > 0 ? totalPriceWeighted / totalQty : 0;
            // Avg GPRFT% = (Total Profit / Total Revenue) * 100 — profit from PFT formula (Temu Price * 0.96 - lp - temuship) / Temu Price
            const avgGprft = totalRevenue > 0 ? (totalProfit / totalRevenue) * 100 : (totalProducts > 0 ? totalGprft / totalProducts : 0);
            // Weighted GROI% = (Total Profit / Total LP/COGS) × 100
            const avgGroi = totalLp > 0 ? (totalProfit / totalLp) * 100 : (totalProducts > 0 ? totalGroi / totalProducts : 0);
            const avgAds = totalProducts > 0 ? totalAds / totalProducts : 0;
            // Use aggregate_ads_percent from backend if available (exact match with all-marketplace-master)
            // Otherwise calculate as fallback: (Total Ad Spend L30 / Total Revenue) * 100
            if (badgeAvgAds == null || badgeAvgAds === undefined) {
                const aggregateAdsPercent = totalRevenue > 0 ? (totalSpendL30 / totalRevenue) * 100 : 0;
                badgeAvgAds = aggregateAdsPercent;
            }
            // NPFT% = GPFT% - ADS% (simple formula, not weighted)
            // CRITICAL: Always use badgeAvgAds (aggregate Ads% from backend) - never use avgAds (simple average)
            // This ensures NPFT uses the same Ads% as all-marketplace-master (2.9%)
            let adsPercentForNpft = 0;
            if (badgeAvgAds != null && badgeAvgAds !== undefined) {
                adsPercentForNpft = badgeAvgAds;
            } else if (totalRevenue > 0) {
                // Fallback: calculate same way as backend
                adsPercentForNpft = (totalSpendL30 / totalRevenue) * 100;
            }
            // Use weighted avgGprft for accurate NPFT calculation
            const avgNpft = avgGprft - adsPercentForNpft;
            // NROI% = GROI% - ADS% (simple formula)
            const avgNroi = avgGroi - adsPercentForNpft;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            // QTY/Views = (Total QTY / Total Views) × 100
            const qtyPerViews = totalViews > 0 ? (totalQuantity / totalViews) * 100 : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;

            // Calculate TCOS: (Total Ad Spend / Total Revenue) × 100
            const totalTcos = totalRevenue > 0 ? (totalSpend / totalRevenue) * 100 : 0;
            
            // Calculate average views
            const avgViews = totalProducts > 0 ? totalViews / totalProducts : 0;
            
            // Update badges (Total Orders, Quantity, Revenue from API = same as sales page when available)
            if (salesSummaryFromBackend) {
                $('#total-orders-badge').text('Orders: ' + (salesSummaryFromBackend.total_orders || 0).toLocaleString());
                $('#total-quantity-badge').text('QTY: ' + (salesSummaryFromBackend.total_quantity || 0).toLocaleString());
                $('#total-revenue-badge').text('Sales: $' + (salesSummaryFromBackend.total_revenue != null ? Math.round(Number(salesSummaryFromBackend.total_revenue)).toLocaleString() : '0'));
            } else {
                $('#total-orders-badge').text('Orders: 0');
                $('#total-quantity-badge').text('QTY: ' + totalQuantity.toLocaleString());
                $('#total-revenue-badge').text('Sales: $' + Math.round(totalRevenue).toLocaleString());
            }
            $('#total-products-badge').text('SKU: ' + totalProducts.toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
            $('#missing-count-badge').text('Missing L: ' + missingCount.toLocaleString());
            $('#not-mapped-count-badge').text('Missing M: ' + notMappedCount.toLocaleString());
            $('#avg-price-badge').text('AVG: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('CVR: ' + qtyPerViews.toFixed(1) + '%');
            $('#avg-dil-badge').text('Avg DIL: ' + Math.round(avgDil) + '%');
            // Total Revenue badge set above from sales_summary or table
            $('#total-profit-badge').text('PFT: $' + Math.round(totalProfit).toLocaleString());
            $('#total-lp-badge').text('Total LP: $' + Math.round(totalLp).toLocaleString());
            $('#avg-gprft-badge').text('GPFT: ' + avgGprft.toFixed(1) + '%');
            $('#avg-groi-badge').text('GROI: ' + Math.round(avgGroi) + '%');
            $('#total-spend-badge').text('Spend: $' + totalSpend.toFixed(2));
            // Use badgeAvgAds (aggregate Ads% from backend) for badge display (matches all-marketplace-master)
            const displayAdsPercent = (badgeAvgAds != null) ? badgeAvgAds : adsPercentForNpft;
            $('#avg-ads-badge').text('Ads: ' + displayAdsPercent.toFixed(1) + '%');
            $('#avg-npft-badge').text('NPFT: ' + avgNpft.toFixed(1) + '%');
            $('#avg-nroi-badge').text('NROI: ' + avgNroi.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#avg-views-badge').text('AVG views: ' + Math.round(avgViews));
        }

        // Update Ads/Utilized count section (when Show Ads Columns is on) - like TikTok
        function updateTemuAdsCounts() {
            if (!table) return;
            const data = table.getData('all').filter(row => {
                const sku = row.sku || '';
                return sku && !String(row.parent || '').toUpperCase().includes('PARENT');
            });
            const processedSkus = new Set();
            const zeroInvSkus = new Set();
            const adSkuSet = new Set();
            let validSkuCount = 0, missingCount = 0, nraMissingCount = 0, nraCount = 0;
            let totalSpend = 0, totalAdSales = 0, totalBudget = 0, totalAdClicks = 0, totalAdSold = 0;

            data.forEach(row => {
                const sku = row.sku || '';
                if (!sku) return;
                const inv = parseFloat(row.inventory) || 0;
                const nr = (row.nr_req || '').trim().toUpperCase();
                const spend = parseFloat(row.spend) || 0;
                const adClicks = parseInt(row.ad_clicks, 10) || 0;
                const campaignStatus = (row.campaign_status || '').trim();
                const hasCampaign = campaignStatus === 'Active' || spend > 0 || adClicks > 0;

                if (!processedSkus.has(sku)) {
                    processedSkus.add(sku);
                    validSkuCount++;
                    if (nr === 'NRL' || nr === 'NR') nraCount++;
                }
                if (hasCampaign && inv > 0) adSkuSet.add(sku);
                if (inv <= 0) zeroInvSkus.add(sku);
                if (!hasCampaign) {
                    if (nr === 'NRL' || nr === 'NR') {
                        if (!processedSkus.has('nm_' + sku)) {
                            processedSkus.add('nm_' + sku);
                            nraMissingCount++;
                        }
                    } else if (inv > 0) {
                        if (!processedSkus.has('m_' + sku)) {
                            processedSkus.add('m_' + sku);
                            missingCount++;
                        }
                    }
                }
                // Use temu_campaign_reports L30 data for badge totals (matches sheet export & all-marketplace-master)
                totalSpend += parseFloat(row.spend_l30) || 0;
                totalBudget += parseFloat(row.target) || 0;
                totalAdClicks += parseInt(row.clicks_l30, 10) || 0;
                totalAdSales += parseFloat(row.ad_sales_l30) || 0;
                totalAdSold += parseInt(row.ad_sold_l30, 10) || 0;
            });
            const zeroInvCount = zeroInvSkus.size;
            const raCount = Math.max(0, validSkuCount - nraCount);
            const uniqueCampaignSkus = new Set();
            data.forEach(r => {
                const s = parseFloat(r.spend) || 0;
                const c = parseInt(r.ad_clicks, 10) || 0;
                const st = (r.campaign_status || '').trim();
                if (st === 'Active' || s > 0 || c > 0) uniqueCampaignSkus.add(r.sku);
            });
            const avgAcos = totalAdSales > 0 ? (totalSpend / totalAdSales) * 100 : 0;
            const roas = totalSpend > 0 ? totalAdSales / totalSpend : 0;
            const avgClicks = adSkuSet.size > 0 ? totalAdClicks / adSkuSet.size : 0;

            const campaignCount = totalCampaignCountFromBackend > 0 ? totalCampaignCountFromBackend : uniqueCampaignSkus.size;

            $('#temu-total-sku-count').text('Total SKU: ' + validSkuCount);
            $('#temu-campaign-count').text('Campaign: ' + campaignCount);
            $('#temu-ad-sku-count').text('Ad SKU: ' + adSkuSet.size);
            $('#temu-missing-campaign-count').text('Missing: ' + missingCount);
            $('#temu-nra-missing-count').text('NRA MISSING: ' + nraMissingCount);
            $('#temu-zero-inv-count').text('Zero INV: ' + zeroInvCount);
            $('#temu-nra-count').text('NRA: ' + nraCount);
            $('#temu-ra-count').text('RA: ' + raCount);
            $('#temu-total-spend-badge').text('Total Ads Spend: $' + Math.round(totalSpend).toLocaleString());
            $('#temu-total-budget-badge').text('Budget: $' + totalBudget.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#temu-total-ad-sales-badge').text('Ad Sales: $' + Math.round(totalAdSales).toLocaleString());
            $('#temu-total-ad-sold-badge').text('Total L30 Ad Sold: ' + totalAdSold.toLocaleString());
            $('#temu-total-ad-clicks-badge').text('Ad Clicks: ' + totalAdClicks.toLocaleString());
            $('#temu-total-clicks-badge').text('Total Clicks: ' + totalAdClicks.toLocaleString());
            $('#temu-avg-clicks-badge').text('Avg Clicks: ' + (avgClicks % 1 === 0 ? Math.round(avgClicks).toLocaleString() : avgClicks.toFixed(1)));
            $('#temu-avg-acos-badge').text('Avg ACOS: ' + Math.round(avgAcos) + '%');
            $('#temu-roas-badge').text('ROAS: ' + roas.toFixed(2));
        }

        // eBay-style color functions
        const getPftColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 125) return 'green';
            return 'pink';
        };

        let totalCampaignCountFromBackend = 0;
        let salesSummaryFromBackend = null;
        let badgeAvgAds = null; // Ads % from badge — shown in ADS% column for all rows

        // Play/Pause parent navigation (like pricing-master-cvr)
        let fullDataset = [];
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        let suppressDataLoadedHandler = false;

        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu-decrease-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_percent", dir: "asc"}
            ],
            ajaxResponse: function(url, params, response) {
                if (response && Array.isArray(response.data)) {
                    totalCampaignCountFromBackend = parseInt(response.total_campaign_count || 0, 10);
                    salesSummaryFromBackend = response.sales_summary || null;
                    // Use exact aggregate_ads_percent from backend (matches all-marketplace-master)
                    // This is the authoritative value - always use it for NPFT calculation
                    if (response.aggregate_ads_percent != null && response.aggregate_ads_percent !== undefined) {
                        badgeAvgAds = parseFloat(response.aggregate_ads_percent);
                        console.log('badgeAvgAds set from backend:', badgeAvgAds);
                    }
                    return response.data;
                }
                if (Array.isArray(response)) return response;
                return [];
            },
            columns: [
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
                    headerSort: false
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        
                        return `${sku} <button type="button" class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price trend" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
                    }
                },
                {
                    title: "Goods ID",
                    field: "goods_id",
                    headerFilter: "input",
                    hozAlign: "center",
                    width: 168,
                    accessorDownload: function(value) {
                        return value != null && value !== '' ? String(value) : '';
                    },
                    formatter: function(cell) {
                        const id = cell.getValue();
                        if (id === null || id === undefined || String(id).trim() === '') return '';
                        const s = String(id).trim();
                        const escText = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        const escAttr = s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        return '<span class="align-middle">' + escText + '</span>' +
                            ' <button type="button" class="btn btn-sm p-0 ms-1 copy-goods-id-btn align-middle" data-goods-id="' + escAttr + '" title="Copy Goods ID" style="border: none; background: none; color: #6c757d; line-height: 1;"><i class="fa fa-copy"></i></button>';
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    sorter: "number"
                },
                {
                    title: "Temu Stock",
                    field: "temu_stock",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false
                },
                {
                    title: "OVL30",
                    field: "ovl30",
                    hozAlign: "center",
                    sorter: "number"
                },
                    {
                    title: "Dil%",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const dil = parseFloat(cell.getValue()) || 0;
                        
                        let color = '';
                        if (dil < 16.66) color = '#a00211'; // red (includes 0)
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                        else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                        else color = '#e83e8c'; // pink (50 and above)
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "CVR 60",
                    field: "cvr_60",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 10 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "CVR 45",
                    field: "cvr_45",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 10 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "CVR 30",
                    field: "cvr_30",
                    hozAlign: "center",
                    sorter: "number",
                    width: 65,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const val = parseFloat(cell.getValue()) || 0;
                        const cvr60 = parseFloat(rowData.cvr_60) || 0;
                        const tol = 0.1;
                        let arrowHtml = '';
                        let arrowColor = '#6c757d';
                        let arrowIcon = 'fa-minus';
                        if (val > cvr60 + tol) {
                            arrowColor = '#28a745';
                            arrowIcon = 'fa-arrow-up';
                        } else if (val < cvr60 - tol) {
                            arrowColor = '#a00211';
                            arrowIcon = 'fa-arrow-down';
                        }
                        arrowHtml = ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 10 ? '#28a745' : '#e83e8c'));
                        const sku = rowData.sku || '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="cvr" title="View CVR% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #008000;"></span></button>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "T L60",
                    field: "temu_l60",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "T L45",
                    field: "temu_l45",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseInt(cell.getValue()) || 0;
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="temu_l30" title="View Temu L30 chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #fd7e14;"></span></button>` : '';
                        return `${value.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "Missing",
                    field: "missing",
                    hozAlign: "center",
                    sorter: "string",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold;" title="Not found in temu_pricing table">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "Campaign",
                    field: "has_campaign",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const goodsId = rowData.goods_id || '';
                        const hasCampaign = goodsId && (
                            rowData.spend > 0 ||
                            rowData.ad_clicks > 0 ||
                            (rowData.campaign_status && rowData.campaign_status !== 'Not Created')
                        );
                        const nraValue = (rowData.nr_req || '').trim().toUpperCase();
                        let dotColor, title;
                        if (nraValue === 'NRA' || nraValue === 'NRL') {
                            dotColor = 'yellow';
                            title = 'NRA - Not Required';
                        } else {
                            dotColor = hasCampaign ? 'green' : 'red';
                            title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                        }
                        return `
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="status-dot ${dotColor}" title="${title}"></span>
                            </div>
                        `;
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    sorter: "string",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const missing = rowData['missing'];
                        
                        // IMPORTANT: Only show MAP if SKU exists in Temu (not missing)
                        // Same logic as eBay - check if item exists before showing MAP
                        if (missing === 'M' || !rowData['goods_id'] || rowData['goods_id'] === '') {
                            return ''; // Don't show MAP for missing items
                        }
                        
                        const temuStock = parseFloat(rowData['temu_stock']) || 0;
                        const inv = parseFloat(rowData['inventory']) || 0;
                        
                        // Show "N MP" with INV if Temu Stock is 0 but INV exists
                        if (inv > 0 && temuStock === 0) {
                            return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${inv})</span>`;
                        }
                        
                        // Only show if both INV and Temu Stock exist
                        if (inv > 0 && temuStock > 0) {
                            if (inv === temuStock) {
                                // Perfect match - Green "MP" (Mapped)
                                return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                            } else {
                                // Mismatch - Red "N MP" with difference
                                const diff = inv - temuStock;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                        }
                        
                        return '';
                    }
                },
                {
                    title: "NRL/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const nrl = row['nr_req'] || '';
                        const sku = row['sku'];

                        // Determine current value (default to REQ if empty)
                        let value = '';
                        if (nrl === 'NRL' || nrl === 'NR') {
                            value = 'NRL';
                        } else if (nrl === 'REQ') {
                            value = 'REQ';
                        } else {
                            value = 'REQ'; // Default to REQ
                        }

                        return `<select class="form-select form-select-sm nr-select" data-sku="${sku}"
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                 {
                    title: "Views",
                    field: "product_clicks",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseInt(cell.getValue()) || 0;
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="views" title="View Views chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0000FF;"></span></button>` : '';
                        return `${value.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
               
                //  {
                //     title: "CTR",
                //     field: "ctr",
                //     hozAlign: "center",
                //     sorter: "number",
                //     formatter: function(cell) {
                //         const value = parseFloat(cell.getValue()) || 0;
                //         return value.toFixed(2) + '%';
                //     },
                //     width: 80
                // },
                {
                    title: "Base Price",
                    field: "base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseFloat(cell.getValue());
                        const str = (value === null || value === undefined || isNaN(value)) ? '' : '$' + Number(value).toFixed(2);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="price" title="View Price chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd;"></span></button>` : '';
                        return `${str} ${dotBtn}`.trim();
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const basePrice = parseFloat(cell.getRow().getData()['base_price']) || 0;
                        const rowData = cell.getRow().getData();
                        const amazonPrice = parseFloat(rowData['a_price']) || 0;
                        const ePrice = parseFloat(rowData['e_price']) || 0;
                        
                        // Only calculate Temu Price if base_price > 0 (item exists in Temu)
                        if (basePrice === 0) {
                            return '$0.00';
                        }
                        const temuPrice = basePrice <= 26.99 ? basePrice + 2.99 : basePrice;
                        
                        // Red only when Temu price exceeds (Amz price × 0.85) OR (E price × 0.90)
                        const amzThreshold = amazonPrice * 0.85;
                        const eThreshold = ePrice * 0.90;
                        const overAmz = amazonPrice > 0 && temuPrice > amzThreshold;
                        const overE = ePrice > 0 && temuPrice > eThreshold;
                        if (overAmz || overE) {
                            return `<span style="color: #a00211; font-weight: 600;">$${temuPrice.toFixed(2)}</span>`;
                        }
                        
                        return '$' + temuPrice.toFixed(2);
                    }
                },
                {
                    title: "A Prc",
                    field: "a_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    title: "E Prc",
                    field: "e_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    title: "PRFT AMT",
                    field: "profit",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value < 0 ? '#dc3545' : (value > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    visible: false
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = (row) => {
                            const price = parseFloat(row['temu_price']) || 0;
                            if (price <= 0) return 0;
                            const lp = parseFloat(row['lp']) || 0;
                            const temuShip = parseFloat(row['temu_ship']) || 0;
                            return ((price * 0.96 - lp - temuShip) / price) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const price = parseFloat(rowData['temu_price']) || 0;  // Temu Price column
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        // PFT % = (price * 0.96 - lp - temuship) / price * 100
                        const value = price > 0 ? ((price * 0.96 - lp - temuShip) / price) * 100 : 0;
                        const colorClass = getPftColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="profit_percent" title="View GPRFT% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ff1493;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${value.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Custom sorter to handle the 100% case properly
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aSpend = parseFloat(aData['spend'] || 0);
                        const bSpend = parseFloat(bData['spend'] || 0);
                        const aTemuL30 = parseFloat(aData['temu_l30'] || 0);
                        const bTemuL30 = parseFloat(bData['temu_l30'] || 0);
                        
                        // Calculate effective ADS% (100 if spend > 0 and sales = 0)
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aSpend > 0 && aTemuL30 === 0) aVal = 100;
                        if (bSpend > 0 && bTemuL30 === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        // Use badge Ads % for all rows when available
                        const displayVal = (badgeAvgAds != null ? badgeAvgAds : (parseFloat(cell.getValue()) || 0));
                        const rowData = cell.getRow().getData();
                        const spend = parseFloat(rowData['spend'] || 0);
                        const temuL30 = parseFloat(rowData['temu_l30'] || 0);
                        let color = '#000';
                        
                        // If spend > 0 but no sales, show 100% in red
                        if (spend > 0 && temuL30 === 0) {
                            const sku = rowData.sku || '';
                            const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="ads_percent" title="View ADS% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span></button>` : '';
                            return `<span style="color: #a00211; font-weight: 600;">100%</span> ${dotBtn}`.trim();
                        }
                        
                        // eBay ACOS color logic applied to displayed value
                        if (displayVal == 0 || displayVal == 100) color = '#a00211'; // red
                        else if (displayVal > 0 && displayVal <= 7) color = '#ff1493'; // pink
                        else if (displayVal > 7 && displayVal <= 14) color = '#28a745'; // green
                        else if (displayVal > 14 && displayVal <= 21) color = '#ffc107'; // yellow
                        else if (displayVal > 21) color = '#a00211'; // red
                        
                        const sku = (rowData && rowData.sku) ? rowData.sku : '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="ads_percent" title="View ADS% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span></button>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${displayVal.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="roi_percent" title="View GROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6f42c1;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${value.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },



                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="npft_percent" title="View NPFT% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #28a745;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${value.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="nroi_percent" title="View NROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #17a2b8;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${value.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "LMP",
                    field: "lmp",
                    hozAlign: "center",
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const entries = row.lmp_entries || [];
                        const prices = entries.map(function(e) { const p = e.price; return (p !== null && p !== undefined && p !== '' && !isNaN(parseFloat(p))) ? parseFloat(p) : null; }).filter(function(p) { return p !== null; });
                        const lowest = prices.length > 0 ? Math.min.apply(null, prices) : null;
                        const display = lowest !== null ? (lowest % 1 === 0 ? lowest.toLocaleString() : lowest.toFixed(2)) : '-';
                        const count = entries.length;
                        const title = count > 0 ? (display + ' (' + count + ' entries) - click eye to edit') : 'Click eye to add LMP';
                        return '<span class="lmp-display">' + (display !== '-' ? display : '<span style="color: #999;">-</span>') + '</span> <button type="button" class="btn btn-sm btn-link p-0 lmp-eye-btn" data-sku="' + (row.sku || '').replace(/"/g, '&quot;') + '" title="' + title + '"><i class="fas fa-info-circle text-info"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.lmp-eye-btn')) {
                            e.stopPropagation();
                            const row = cell.getRow().getData();
                            openLmpModal(row.sku, row.lmp_entries || []);
                        }
                    }
                },
                {
                    title: "(LMP - 15%)",
                    field: "lmp_minus_15",
                    hozAlign: "center",
                  
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const lmp = row['lmp'];
                        if (lmp === null || lmp === undefined || lmp === '') return '<span style="color: #999;">-</span>';
                        const num = parseFloat(lmp);
                        if (Number.isNaN(num)) return '-';
                        const val = num * 0.85;
                        return (val % 1 === 0) ? val.toLocaleString() : val.toFixed(2);
                    }
                },
                     {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "R Prc",
                    field: "recommended_base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value || value === 0) return '';
                        return `$${parseFloat(value).toFixed(2)}`;
                    }
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const currentPrice = parseFloat(rowData['base_price']) || 0;
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        const sprice = isNaN(spriceNum) ? 0 : spriceNum;

                        if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0) return '';
                        if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) return '';

                        return `$${sprice.toFixed(2)}`;
                    }
                },
           
                {
                    title: "S Temu Prc",
                    field: "stemu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price (SPRICE + 2.99 if <= 26.99)
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        return `$${stemuPrice.toFixed(2)}`;
                    }
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const percentage = 0.96; // Temu marketplace percentage (margin 96)
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SGPRFT% = ((S Temu Price × percentage - LP - Temu Ship) / S Temu Price) × 100
                        const sgprft = stemuPrice > 0 ? ((stemuPrice * percentage - lp - temuShip) / stemuPrice) * 100 : 0;
                        
                        const colorClass = getPftColor(sgprft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    }
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.96;
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SGPRFT%
                        const sgprft = stemuPrice > 0 ? ((stemuPrice * percentage - lp - temuShip) / stemuPrice) * 100 : 0;
                        
                        // SPFT% = SGPRFT% - ADS%
                        const spft = sgprft - adsPercent;
                        
                        const colorClass = getPftColor(spft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    }
                },
                {
                    title: "SROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        let sroi = parseFloat(rowData['sroi_percent']);
                        if (Number.isNaN(sroi)) {
                            // Same formula as GROI%: ((price * 0.96 - lp - temuShip) / lp) * 100, with price = suggested Temu price
                            const sprice = parseFloat(rowData['sprice']) || 0;
                            const lp = parseFloat(rowData['lp']) || 0;
                            const temuShip = parseFloat(rowData['temu_ship']) || 0;
                            if (sprice === 0 || lp === 0) return '';
                            const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                            sroi = ((stemuPrice * 0.96 - lp - temuShip) / lp) * 100;
                        }
                        const colorClass = getRoiColor(sroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sroi)}%</span>`;
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Spend"></i>
                        </div>`;
                    },
                    visible: false, 
                    width: 100
                },
                {
                    title: "L60 Spend",
                    field: "spend_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const disp = value > 0 ? '$' + value.toFixed(2) : '<span style="color: #999;">-</span>';
                        return `<div class="d-flex align-items-center justify-content-center gap-1"><span>${disp}</span><i class="fa-solid fa-info-circle l60-spend-info-icon" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Click to show/hide L60 columns"></i></div>`;
                    }
                },
                {
                    title: "Ad Sold",
                    field: "ad_sold_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue(), 10) || 0;
                        return value > 0 ? value.toLocaleString() : '<span style="color: #999;">-</span>';
                    }
                },
                {
                    title: "Ad Sales",
                    field: "ad_sales_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return value > 0 ? '$' + value.toFixed(2) : '<span style="color: #999;">-</span>';
                    }
                },
                {
                    title: "L60 vs L30",
                    field: "l60_vs_l30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const val = cell.getValue();
                        if (val === null || val === undefined || Number.isNaN(parseFloat(val))) return '<span style="color: #999;">-</span>';
                        const v = parseFloat(val);
                        const pct = (v % 1 === 0) ? Math.round(v) : v.toFixed(1);
                        const direction = v > 0 ? 'L60&lt;' : (v < 0 ? 'L60&gt;' : '');
                        const color = v > 0 ? '#28a745' : (v < 0 ? '#dc3545' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">${pct}%</span>${direction ? ` <span style="font-size: 0.75em; color: #6c757d;" title="${v > 0 ? 'L60 ACOS < L30 ACOS' : 'L60 ACOS > L30 ACOS'}">${direction}</span>` : ''}`;
                    }
                },
                {
                    title: "ACOS%",
                    field: "acos_ad",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${Math.round(value)}%</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="ACOS%"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "Ad Clicks",
                    field: "ad_clicks",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toLocaleString()}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Ad Clicks"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 110
                },
                {
                    title: "OUT ROAS",
                    field: "out_roas_l30",
                    hozAlign: "right",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // Use net_roas as OUT ROAS if out_roas_l30 is not available
                        const value = parseFloat(cell.getValue() || rowData.net_roas || 0);
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="OUT ROAS"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "IN ROAS",
                    field: "in_roas_l30",
                    hozAlign: "right",
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    editable: function(cell) {
                        return !window.iconClicked;
                    },
                    formatter: function(cell) {
                        // Default to 0 if field doesn't exist
                        const cellValue = cell.getValue();
                        const value = (cellValue !== null && cellValue !== undefined) ? parseFloat(cellValue) : 0;
                        const cellElement = cell.getElement();
                        
                        if (cellElement) {
                            setTimeout(function() {
                                const icon = cellElement.querySelector('.toggle-in-roas-info');
                                if (icon) {
                                    $(icon).off('mousedown click');
                                    $(icon).on('mousedown', function(e) {
                                        window.iconClicked = true;
                                        e.stopPropagation();
                                        e.preventDefault();
                                        setTimeout(function() {
                                            window.iconClicked = false;
                                        }, 100);
                                        return false;
                                    });
                                }
                            }, 0);
                        }
                        
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle toggle-in-roas-info" style="cursor: pointer; font-size: 12px; color: #3b82f6; pointer-events: auto; z-index: 10; position: relative;" title="IN ROAS"></i>
                        </div>`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('toggle-in-roas-info') || 
                            e.target.classList.contains('fa-info-circle') ||
                            e.target.closest('.toggle-in-roas-info')) {
                            e.stopPropagation();
                            e.preventDefault();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const rowData = row.getData();
                        const sku = rowData.sku;
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (!sku) {
                            console.error('SKU not found');
                            showToast('Error: SKU not found', 'error');
                            return;
                        }
                        
                        $.ajax({
                            url: '/temu/ads/update',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            data: {
                                sku: sku,
                                field: 'in_roas_l30',
                                value: value
                            },
                            success: function(response) {
                                if (response.success) {
                                    cell.setValue(value);
                                    showToast('IN ROAS updated successfully', 'success');
                                } else {
                                    const oldValue = parseFloat(rowData.in_roas_l30 || 0);
                                    cell.setValue(oldValue);
                                    showToast('Failed to update IN ROAS: ' + (response.message || 'Unknown error'), 'error');
                                }
                            },
                            error: function(xhr) {
                                const oldValue = parseFloat(rowData.in_roas_l30 || 0);
                                cell.setValue(oldValue);
                                const errorMsg = xhr.responseJSON?.message || xhr.statusText || 'Unknown error';
                                console.error('Error updating IN ROAS:', xhr);
                                showToast('Error updating IN ROAS: ' + errorMsg, 'error');
                            }
                        });
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "Status",
                    field: "campaign_status",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData().sku;
                        const rowData = row.getData();
                        const goodsId = rowData.goods_id || '';
                        const hasCampaign = goodsId && (rowData.spend > 0 || rowData.ad_clicks > 0);
                        
                        // Default to "Not Created" if no campaign exists, otherwise "Active"
                        let defaultValue = hasCampaign ? "Active" : "Not Created";
                        // Try to get value from cell, if not available use default
                        let cellValue = cell.getValue();
                        const value = (cellValue && cellValue.trim()) ? cellValue.trim() : defaultValue;
                        
                        const statusColors = {
                            "Active": "#10b981",
                            "Inactive": "#ef4444",
                            "Not Created": "#eab308"
                        };
                        const selectedColor = statusColors[value] || "#6b7280";
                        
                        return `
                            <select class="form-select form-select-sm editable-select campaign-status-select" 
                                    data-sku="${sku}" 
                                    data-field="status"
                                    style="width: 120px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: 0.875rem; color: ${selectedColor}; font-weight: 500;">
                                <option value="Active" ${value === 'Active' ? 'selected' : ''} style="color: #10b981; font-weight: 500;">Active</option>
                                <option value="Inactive" ${value === 'Inactive' ? 'selected' : ''} style="color: #ef4444; font-weight: 500;">Inactive</option>
                                <option value="Not Created" ${value === 'Not Created' ? 'selected' : ''} style="color: #eab308; font-weight: 500;">Not Created</option>
                            </select>
                        `;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    visible: false,
                    width: 130
                },
                {
                    title: "Target",
                    field: "target",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return '$' + value.toFixed(2);
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                },
                
            ]
        });

        // Toggle Ads Columns button - Show only columns that match temu/ads page
        let adsColumnsVisible = false;
        let originalColumnVisibility = {}; // Store original visibility state
        
        // Columns to show when ads view is active (matching temu/ads page)
        const adsColumnFields = ['sku', 'goods_id', 'has_campaign', 'inventory', 'ovl30', 'temu_l30', 'dil_percent', 'nr_req', 'spend', 'spend_l60', 'l60_vs_l30', 'ad_clicks', 'acos_ad', 'out_roas_l30', 'in_roas_l30', 'campaign_status'];
        
        $('#toggle-ads-columns-btn').on('click', function() {
            adsColumnsVisible = !adsColumnsVisible;
            
            if (adsColumnsVisible) {
                // Store original visibility state for all columns
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field) {
                        originalColumnVisibility[field] = column.isVisible();
                    }
                });
                
                // Hide non-ads columns, show ads columns (iterate so hidden columns are found)
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field && !adsColumnFields.includes(field)) {
                        column.hide();
                    } else if (field && adsColumnFields.includes(field)) {
                        column.show();
                    }
                });
                if (typeof l60ColumnsVisible !== 'undefined' && l60ColumnsVisible && typeof l60ColumnFields !== 'undefined') {
                    table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField())).forEach(c => c.show());
                }
                
                $(this).html('<i class="fa fa-filter"></i> Show All Columns');
                $(this).removeClass('btn-secondary btn-primary').addClass('btn-danger');
                $('#temu-ads-count-section').removeClass('d-none');
                $('#summary-stats').addClass('d-none');
                if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            } else {
                // Restore original visibility state
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field && originalColumnVisibility.hasOwnProperty(field)) {
                        if (originalColumnVisibility[field]) {
                            column.show();
                        } else {
                            column.hide();
                        }
                    }
                });
                if (typeof l60ColumnsVisible !== 'undefined' && l60ColumnsVisible && typeof l60ColumnFields !== 'undefined') {
                    table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField())).forEach(c => c.show());
                }
                
                $(this).html('<i class="fa fa-filter"></i> Ads Section');
                $(this).removeClass('btn-danger btn-primary').addClass('btn-secondary');
                $('#temu-ads-count-section').addClass('d-none');
                $('#summary-stats').removeClass('d-none');
                temuAdsBadgeFilter = null;
                $('#temu-ads-count-section .temu-ads-badge').removeClass('border border-3 border-dark');
                applyFilters();
            }
        });

        // Ads section badge filter (like TikTok) - toggle on click
        let temuAdsBadgeFilter = null;
        $(document).on('click', '.temu-ads-badge', function() {
            const filter = $(this).data('ads-filter');
            temuAdsBadgeFilter = (temuAdsBadgeFilter === filter) ? null : filter;
            $('#temu-ads-count-section .temu-ads-badge').removeClass('border border-3 border-dark');
            if (temuAdsBadgeFilter) {
                $('#temu-ads-count-section .temu-ads-badge[data-ads-filter="' + temuAdsBadgeFilter + '"]').addClass('border border-3 border-dark');
            }
            applyFilters();
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
        });

        // Temu Ads section: L7 / L30 campaign report upload (like TikTok)
        function doTemuUploadReport(fileInput, reportRange, statusContainerId) {
            const file = fileInput.files && fileInput.files[0];
            const $status = $('#' + statusContainerId);
            if (!file) {
                $status.html('<span class="text-danger">Please select a file</span>').show();
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_range', reportRange);
            $status.html('<span class="text-info">Uploading...</span>').show();
            $.ajax({
                url: '{{ route("temu.ads.upload.campaign") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response && response.success) {
                        $status.html('<span class="text-success">' + (response.message || 'Uploaded') + '</span>');
                        fileInput.value = '';
                        if (table) table.replaceData();
                        if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
                        setTimeout(function() { $status.html('').hide(); }, 5000);
                    } else {
                        $status.html('<span class="text-danger">' + (response && response.message ? response.message : 'Upload failed') + '</span>');
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Upload failed';
                    $status.html('<span class="text-danger">' + msg + '</span>');
                }
            });
        }
        $('#temu-l7-upload-btn').on('click', function() {
            $('#temu-l7-upload-file').off('change').on('change', function() {
                doTemuUploadReport(this, 'L7', 'temu-upload-status-container');
            });
            $('#temu-l7-upload-file')[0].click();
        });
        $('#temu-l30-upload-btn').on('click', function() {
            $('#temu-l30-upload-file').off('change').on('change', function() {
                doTemuUploadReport(this, 'L30', 'temu-upload-status-container');
            });
            $('#temu-l30-upload-file')[0].click();
        });

        let l60ColumnsVisible = false;
        const l60ColumnFields = ['ad_sold_l60', 'ad_sales_l60', 'l60_vs_l30'];
        function toggleL60Columns(show) {
            if (!table) return;
            l60ColumnsVisible = show;
            const cols = table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField()));
            cols.forEach(c => { show ? c.show() : c.hide(); });
        }
        $(document).on('click', '.l60-spend-info-icon', function(e) {
            e.stopPropagation();
            l60ColumnsVisible = !l60ColumnsVisible;
            toggleL60Columns(l60ColumnsVisible);
        });

        $('#temu-l60-upload-btn').on('click', function() {
            $('#temu-l60-upload-file').off('change').on('change', function() {
                doTemuUploadReport(this, 'L60', 'temu-upload-status-container');
            });
            $('#temu-l60-upload-file')[0].click();
        });

        // L60 Sales upload (same format as L30, stored in temu_daily_data_l60)
        $('#temu-l60-sales-upload-btn').on('click', function() {
            $('#temu-l60-sales-upload-file').off('change').on('change', function() {
                const file = this.files[0];
                if (!file) return;
                const $status = $('#temu-l60-sales-upload-status');
                $status.text('Uploading...').css('color', '');
                const totalChunks = 5;
                const uploadId = 'temu_l60_' + Date.now();
                let currentChunk = 0;
                let totalImported = 0;

                function uploadNextChunk() {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('chunk', currentChunk);
                    formData.append('totalChunks', totalChunks);
                    formData.append('uploadId', uploadId);
                    formData.append('_token', '{{ csrf_token() }}');

                    $.ajax({
                        url: '/temu/upload-daily-data-l60-chunk',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                totalImported += response.imported || 0;
                                $status.text('Chunk ' + (currentChunk + 1) + '/' + totalChunks + '...');
                                if (currentChunk < totalChunks - 1) {
                                    currentChunk++;
                                    setTimeout(uploadNextChunk, 300);
                                } else {
                                    $status.text('Done. ' + totalImported + ' rows.').css('color', 'green');
                                    showToast('L60 Sales upload completed. ' + totalImported + ' records.', 'success');
                                    if (table) table.setData('/temu-decrease-data');
                                }
                            } else {
                                $status.text('Error').css('color', 'red');
                                showToast(response.message || 'Upload failed', 'error');
                            }
                        },
                        error: function(xhr) {
                            const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Upload failed';
                            $status.text('Error').css('color', 'red');
                            showToast(msg, 'error');
                        }
                    });
                }
                uploadNextChunk();
            });
            $('#temu-l60-sales-upload-file')[0].click();
        });

        $('#sku-search').on('keyup', function() {
            applyFilters();
        });

        // Apply filters
        function applyFilters() {
            // When Play navigation is active, show only current parent (like pricing-master-cvr)
            if (isPlayNavigationActive) {
                if (typeof showCurrentParentPlayView === 'function') showCurrentParentPlayView();
                return;
            }

            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const cvrTrendFilter = $('#cvr-trend-filter').val();
            const arrowFilter = $('#arrow-filter').val();
            const adsFilter = $('#ads-filter').val();
            const spriceFilter = $('#sprice-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            const skuSearch = $('#sku-search').val();
            adsReqFilter = $('#ads-req-filter').val();
            adsRunningFilter = $('#ads-running-filter').val();

            // Clear all filters first
            table.clearFilter();

            // SKU search filter (case-insensitive)
            if (skuSearch) {
                table.addFilter(function(data) {
                    const sku = data.sku || '';
                    return sku.toUpperCase().includes(skuSearch.toUpperCase());
                });
            }

            // Inventory filter
            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    if (inventoryFilter === 'eq0') return inv === 0;
                    return true;
                });
            }

            // GPFT filter — use same formula as column: (temu_price * 0.96 - lp - temu_ship) / temu_price * 100
            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    const price = parseFloat(data.temu_price) || 0;
                    const gpft = price > 0 ? ((price * 0.96 - (parseFloat(data.lp) || 0) - (parseFloat(data.temu_ship) || 0)) / price) * 100 : 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                    if (gpftFilter === '50-60') return gpft >= 50 && gpft < 60;
                    if (gpftFilter === '60plus') return gpft >= 60;
                    return true;
                });
            }

            // CVR filter
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const cvr = parseFloat(data.cvr_percent) || 0;
                    const cvrRounded = Math.round(cvr * 100) / 100;
                    
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0.01-1') return cvrRounded >= 0.01 && cvrRounded <= 1;
                    if (cvrFilter === '1-2') return cvrRounded > 1 && cvrRounded <= 2;
                    if (cvrFilter === '2-3') return cvrRounded > 2 && cvrRounded <= 3;
                    if (cvrFilter === '3-4') return cvrRounded > 3 && cvrRounded <= 4;
                    if (cvrFilter === '0-4') return cvrRounded >= 0 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-10') return cvrRounded > 7 && cvrRounded <= 10;
                    if (cvrFilter === '10plus') return cvrRounded > 10;
                    return true;
                });
            }

            // ADS filter
            if (adsFilter !== 'all') {
                table.addFilter(function(data) {
                    const ads = parseFloat(data.ads_percent) || 0;
                    
                    if (adsFilter === '0-10') return ads >= 0 && ads < 10;
                    if (adsFilter === '10-20') return ads >= 10 && ads < 20;
                    if (adsFilter === '20-30') return ads >= 20 && ads < 30;
                    if (adsFilter === '30-100') return ads >= 30 && ads <= 100;
                    if (adsFilter === '100plus') return ads > 100;
                    return true;
                });
            }

            // DIL filter
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const dil = parseFloat(data['dil_percent']) || 0;
                    
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // CVR trend filter (CVR 60 vs CVR 30)
            const cvrTrendTol = 0.1;
            const applyArrowOrCvrTrend = (filterVal) => {
                if (filterVal === 'all') return;
                table.addFilter(function(data) {
                    const cvr30 = parseFloat(data.cvr_30 || data.cvr_percent) || 0;
                    const cvr60 = parseFloat(data.cvr_60) || 0;
                    if (filterVal === 'l60_gt_l30' || filterVal === 'down') return cvr60 > cvr30 + cvrTrendTol;
                    if (filterVal === 'l30_gt_l60' || filterVal === 'up') return cvr30 > cvr60 + cvrTrendTol;
                    if (filterVal === 'equal') return Math.abs(cvr30 - cvr60) <= cvrTrendTol;
                    return true;
                });
            };
            if (arrowFilter !== 'all') {
                applyArrowOrCvrTrend(arrowFilter);
            } else if (cvrTrendFilter !== 'all') {
                applyArrowOrCvrTrend(cvrTrendFilter);
            }

            // SPRICE filter
            if (spriceFilter !== 'all') {
                table.addFilter(function(data) {
                    const spriceVal = data.sprice;
                    const sprice = parseFloat(spriceVal) || 0;
                    if (spriceFilter === 'blank') {
                        const blank = spriceVal == null || spriceVal === '' || isNaN(sprice) || sprice <= 0;
                        return blank;
                    }
                    if (spriceFilter === '27-31') return sprice >= 27 && sprice <= 31;
                    if (spriceFilter === 'lt27') return sprice > 0 && sprice < 27;
                    if (spriceFilter === 'gt31') return sprice > 31;
                    return true;
                });
            }

            // Sold+SPRC Blank filter (if active)
            if (soldSpriceBlankFilterActive) {
                table.addFilter(function(data) {
                    const temuL30Val = data['temu_l30'];
                    const spriceVal = data['sprice'];
                    const invVal = data['inventory'];
                    
                    const temuL30 = temuL30Val ? parseInt(temuL30Val) : 0;
                    const inventory = invVal ? parseInt(invVal) : 0;
                    const spriceIsBlank = !spriceVal || spriceVal === '' || spriceVal === 0 || parseFloat(spriceVal) === 0;
                    
                    return inventory > 0 && temuL30 > 0 && spriceIsBlank;
                });
            }

            // Ads Req filter
            if (adsReqFilter !== 'all') {
                table.addFilter(function(data) {
                    const views = parseFloat(data['product_clicks']) || 0;
                    if (adsReqFilter === 'below-avg' && latestAvgViews > 0) {
                        return views > 0 && views < latestAvgViews;
                    }
                    return true;
                });
            }

            // Ads Running filter
            if (adsRunningFilter !== 'all') {
                table.addFilter(function(data) {
                    const target = parseFloat(data['target']) || 0;
                    if (adsRunningFilter === 'running') {
                        return target > 0;
                    }
                    return true;
                });
            }

            // Missing badge filter (clickable badge only - no dropdown)
            if (missingBadgeFilterActive) {
                table.addFilter(function(data) {
                    return data['missing'] === 'M';
                });
            }

            // 0 Sold badge filter (only INV > 0)
            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    const temuL30 = parseInt(data['temu_l30']) || 0;
                    const inv = parseFloat(data['inventory']) || 0;
                    return temuL30 === 0 && inv > 0;
                });
            }

            // Missing badge filter (only INV > 0)
            if (missingBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    return data['missing'] === 'M' && inv > 0;
                });
            }

            // Map badge filter (only INV > 0)
            if (mapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const missing = data['missing'];
                    const goodsId = data['goods_id'];
                    if (missing === 'M' || !goodsId || goodsId === '' || inv === 0) return false;
                    
                    const temuStock = parseFloat(data['temu_stock']) || 0;
                    return inv > 0 && temuStock > 0 && inv === temuStock;
                });
            }

            // Not Map badge filter (only INV > 0)
            if (notMapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const missing = data['missing'];
                    const goodsId = data['goods_id'];
                    if (missing === 'M' || !goodsId || goodsId === '' || inv === 0) return false;
                    
                    const temuStock = parseFloat(data['temu_stock']) || 0;
                    return inv > 0 && (temuStock === 0 || (temuStock > 0 && inv !== temuStock));
                });
            }

            // Temu Ads section badge filter (only when Show Ads Columns is on)
            if (typeof adsColumnsVisible !== 'undefined' && adsColumnsVisible && temuAdsBadgeFilter) {
                switch (temuAdsBadgeFilter) {
                    case 'all':
                        break;
                    case 'campaign':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            return st === 'Active' || spend > 0 || adClicks > 0;
                        });
                        break;
                    case 'ad-sku':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const inv = parseFloat(data.inventory) || 0;
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return hasCampaign && inv > 0;
                        });
                        break;
                    case 'missing':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            const inv = parseFloat(data.inventory) || 0;
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return !hasCampaign && inv > 0 && nr !== 'NRL' && nr !== 'NR';
                        });
                        break;
                    case 'nra-missing':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return !hasCampaign && (nr === 'NRL' || nr === 'NR');
                        });
                        break;
                    case 'zero-inv':
                        table.addFilter(function(data) {
                            const inv = parseFloat(data.inventory) || 0;
                            return inv <= 0;
                        });
                        break;
                    case 'nra':
                        table.addFilter(function(data) {
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            return nr === 'NRL' || nr === 'NR';
                        });
                        break;
                    case 'ra':
                        table.addFilter(function(data) {
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            return nr === 'REQ';
                        });
                        break;
                    case 'total-spend':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            return spend > 0;
                        });
                        break;
                    case 'budget':
                        table.addFilter(function(data) {
                            const t = data.target;
                            return t !== null && t !== undefined && t !== '' && (parseFloat(t) || 0) > 0;
                        });
                        break;
                    case 'ad-sales':
                    case 'avg-acos':
                    case 'roas':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const outRoas = parseFloat(data.out_roas_l30) || 0;
                            return spend > 0 && outRoas > 0;
                        });
                        break;
                    case 'ad-clicks':
                        table.addFilter(function(data) {
                            const clicks = parseInt(data.ad_clicks, 10) || 0;
                            return clicks > 0;
                        });
                        break;
                }
            }

            // NRL/REQ filter
            const nrReqFilter = $('#nr-req-filter').val();
            if (nrReqFilter !== 'all') {
                table.addFilter(function(data) {
                    const nr_req = data['nr_req'] || 'REQ';
                    // Handle both NR and NRL as same value
                    const dataValue = (nr_req === 'NR' || nr_req === 'NRL') ? 'NRL' : nr_req;
                    return dataValue === nrReqFilter;
                });
            }

            updateSummary();
            updateSelectAllCheckbox();
            
            // Show search result info
            if (skuSearch) {
                const resultCount = table.getData('active').length;
                const totalCount = table.getData('all').length;
                
                if (resultCount === 0) {
                    $('#search-result-info').html(`<i class="fa fa-exclamation-triangle text-warning"></i> No results found for "${skuSearch}". SKU may not exist in product_master table.`).show();
                } else {
                    $('#search-result-info').html(`Found ${resultCount} result(s) matching "${skuSearch}"`).show();
                }
            } else {
                $('#search-result-info').hide();
            }

            // LMP, LMP Link, (LMP - 15%): always visible (show when Missing L active, never hide)
            try {
                if (missingBadgeFilterActive) {
                    table.getColumn('lmp').show();
                    table.getColumn('lmp_minus_15').show();
                } else {
                    table.getColumn('lmp').show();
                    table.getColumn('lmp_minus_15').show();
                }
            } catch (e) {}
            // MAP column: visible only when Missing M badge is active
            try {
                if (notMapBadgeFilterActive) table.getColumn('MAP').show();
                else table.getColumn('MAP').hide();
            } catch (e) {}
        }

        // ==================== Play/Pause parent navigation (same as pricing-master-cvr) ====================
        // Group key = parent + SKU prefix (WF/FR etc) so FR and WF SKUs don't mix in same play group
        function getRowGroupKey(row) {
            const p = (row.parent != null && row.parent !== '') ? row.parent : (row.sku || '');
            const prefix = (row.sku || '').trim().split(/\s+/)[0] || '';
            return (p || '') + '|' + prefix;
        }

        function getParentRows() {
            if (!fullDataset || fullDataset.length === 0) return [];
            const seen = new Set();
            const out = [];
            fullDataset.forEach(row => {
                const key = getRowGroupKey(row);
                if (key !== '|' && !seen.has(key)) {
                    seen.add(key);
                    out.push({ parent: key });
                }
            });
            return out;
        }

        function showCurrentParentPlayView() {
            if (!fullDataset || fullDataset.length === 0) return;
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            const currentGroupKey = parentRows[currentPlayParentIndex].parent;
            const displayData = fullDataset.filter(row => getRowGroupKey(row) === currentGroupKey);
            suppressDataLoadedHandler = true;
            table.clearSort();
            table.setData(displayData).then(() => {
                updateSummary();
                updatePlayButtonStates();
            });
        }

        function startPlayNavigation() {
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            isPlayNavigationActive = true;
            currentPlayParentIndex = 0;
            showCurrentParentPlayView();
            $('#play-auto').hide();
            $('#play-pause').show();
            updatePlayButtonStates();
        }

        function stopPlayNavigation() {
            isPlayNavigationActive = false;
            currentPlayParentIndex = 0;
            $('#play-pause').hide();
            $('#play-auto').show();
            $('#play-backward, #play-forward').prop('disabled', true);
            if (fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(applyFilters);
            } else {
                applyFilters();
            }
        }

        function updatePlayButtonStates() {
            const parentRows = getParentRows();
            $('#play-backward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex >= parentRows.length - 1);
            $('#play-auto').attr('title', isPlayNavigationActive ? 'Show all' : 'Start parent navigation');
            $('#play-pause').attr('title', 'Stop navigation and show all');
        }

        function playNextParent() {
            if (!isPlayNavigationActive) return;
            const parentRows = getParentRows();
            if (currentPlayParentIndex >= parentRows.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            if (!isPlayNavigationActive) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', startPlayNavigation);
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        // LMP Modal: Add New form + table list; lowest row highlighted with LOWEST badge
        let lmpModalSku = '';
        function openLmpModal(sku, entries) {
            lmpModalSku = sku || '';
            $('#lmpModalSku').text(lmpModalSku);
            $('#lmpNewPrice').val('');
            $('#lmpNewLink').val('');
            const tbody = $('#lmpEntriesContainer');
            tbody.empty();
            const list = Array.isArray(entries) && entries.length > 0 ? entries : [];
            list.forEach(function(entry) {
                appendLmpTableRow(tbody, entry.price !== undefined && entry.price !== null ? entry.price : '', entry.link || '');
            });
            updateLmpLowestHighlight();
            $('#lmpModal').modal('show');
        }
        function appendLmpTableRow(tbody, price, link) {
            const tr = $('<tr class="lmp-entry-row">' +
                '<td class="lmp-num text-center align-middle"></td>' +
                '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="lmp-lowest-badge"></span></td>' +
                '<td class="align-middle"><input type="text" class="form-control form-control-sm lmp-link d-inline-block me-1" style="max-width:220px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary lmp-open-link" target="_blank" rel="noopener" title="Open link"><i class="fas fa-external-link-alt"></i></a></td>' +
                '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger lmp-remove-row" title="Remove"><i class="fas fa-trash-alt"></i></button></td></tr>');
            tr.find('.lmp-price').val(price !== '' && price != null ? price : '');
            tr.find('.lmp-link').val(link || '');
            tbody.append(tr);
            tr.find('.lmp-remove-row').on('click', function(e) {
                e.preventDefault();
                tr.remove();
                renumberLmpRows();
                updateLmpLowestHighlight();
            });
            tr.find('.lmp-price, .lmp-link').on('input', function() { updateLmpLowestHighlight(); });
            tr.find('.lmp-open-link').on('click', function(e) {
                e.preventDefault();
                const href = (tr.find('.lmp-link').val() || '').trim();
                if (href && (href.startsWith('http://') || href.startsWith('https://'))) window.open(href, '_blank');
            });
            renumberLmpRows();
        }
        function renumberLmpRows() {
            $('#lmpEntriesContainer .lmp-entry-row').each(function(i) {
                $(this).find('.lmp-num').text(i + 1);
            });
        }
        function updateLmpLowestHighlight() {
            let minVal = null;
            let minTr = null;
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const tr = $(this);
                tr.removeClass('table-dark');
                tr.find('.lmp-lowest-badge').empty();
                const val = tr.find('.lmp-price').val();
                const num = val !== '' && val != null ? parseFloat(val) : null;
                if (num !== null && !isNaN(num)) {
                    if (minVal === null || num < minVal) { minVal = num; minTr = tr; }
                }
            });
            if (minTr && minVal !== null) {
                minTr.addClass('table-dark');
                minTr.find('.lmp-lowest-badge').html(' <span class="badge bg-info">LOWEST</span>');
            }
        }
        $('#lmpAddRowBtn').on('click', function() {
            const price = $('#lmpNewPrice').val();
            const link = $('#lmpNewLink').val();
            if (!price && !link) {
                showToast('Enter Price or Link', 'warning');
                return;
            }
            appendLmpTableRow($('#lmpEntriesContainer'), price || '', link || '');
            $('#lmpNewPrice').val('');
            $('#lmpNewLink').val('');
        });
        $('#lmpClearFormBtn').on('click', function() {
            $('#lmpNewPrice').val('');
            $('#lmpNewLink').val('');
        });
        $('#lmpModalSaveBtn').on('click', function() {
            const entries = [];
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const price = $(this).find('.lmp-price').val();
                const link = $(this).find('.lmp-link').val();
                if (price || link) entries.push({ price: price ? parseFloat(price) : null, link: link ? link.trim() : null });
            });
            if (entries.length === 0) {
                showToast('Add at least one price or link', 'warning');
                return;
            }
            $(this).prop('disabled', true);
            $.ajax({
                url: '{{ route("temu.lmp.save") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: lmpModalSku,
                    lmp_entries: entries
                },
                success: function(response) {
                    showToast('LMP saved successfully', 'success');
                    $('#lmpModal').modal('hide');
                    if (table) table.replaceData();
                },
                error: function() {
                    showToast('Failed to save LMP', 'error');
                },
                complete: function() {
                    $('#lmpModalSaveBtn').prop('disabled', false);
                }
            });
        });

        $('#inventory-filter, #gpft-filter, #cvr-filter, #cvr-trend-filter, #arrow-filter, #ads-filter, #sprice-filter, #ads-req-filter, #ads-running-filter, #nr-req-filter').on('change', function() {
            applyFilters();
        });

        // Handle column visibility for Ads Req filter
        $('#ads-req-filter').on('change', function() {
            const value = $(this).val();
            
            if (value === 'below-avg') {
                // Hide columns from GROI% to SPFT%
                table.getColumn('roi_percent').hide();
                table.getColumn('npft_percent').hide();
                table.getColumn('nroi_percent').hide();
                table.getColumn('recommended_base_price').hide();
                table.getColumn('sprice').hide();
                table.getColumn('stemu_price').hide();
                table.getColumn('sgprft_percent').hide();
                table.getColumn('spft_percent').hide();
            } else {
                // Show columns when filter is cleared
                table.getColumn('roi_percent').show();
                table.getColumn('npft_percent').show();
                table.getColumn('nroi_percent').show();
                table.getColumn('recommended_base_price').show();
                table.getColumn('sprice').show();
                table.getColumn('stemu_price').show();
                table.getColumn('sgprft_percent').show();
                table.getColumn('spft_percent').show();
            }
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const column = $item.data('column');
            const color = $item.data('color');
            const dropdown = $item.closest('.dropdown');
            const button = dropdown.find('.dropdown-toggle');
            
            dropdown.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            applyFilters();
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            
            if (field === 'base_price') {
                const newPrice = parseFloat(cell.getValue());
                if (newPrice < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                $.ajax({
                    url: '/temu-pricing/update-price',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        base_price: newPrice
                    },
                    success: function(response) {
                        showToast('Price updated successfully', 'success');
                        updateSummary();
                    },
                    error: function(xhr) {
                        showToast('Failed to update price', 'error');
                        cell.restoreOldValue();
                    }
                });
            }
            
            // Handle SPRICE edit
            if (field === 'sprice') {
                const newSprice = parseFloat(cell.getValue());
                if (newSprice < 0) {
                    showToast('SPRICE cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                row.update({ sprice: newSprice });
                row.reformat();
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        sprice: newSprice
                    },
                    success: function(response) {
                        showToast('SPRICE saved successfully', 'success');
                    },
                    error: function(xhr) {
                        showToast('Failed to save SPRICE', 'error');
                    }
                });
            }

        });

        // NR/REQ dropdown change handler (Amazon style)
        $(document).on('change', '.nr-select', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');

            // Save to database
            $.ajax({
                url: '/temu-decrease/save-listing-status',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    nr_req: value
                },
                success: function(response) {
                    const message = response.message || 'NR/REQ updated successfully';
                    showToast(message, 'success');
                },
                error: function(xhr) {
                    showToast('Failed to update NR/REQ', 'error');
                }
            });
        });

        // Status dropdown change handler
        $(document).on('change', '.campaign-status-select', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');

            if (!sku) {
                console.error('SKU not found in status select');
                showToast('Error: SKU not found', 'error');
                return;
            }

            // Update the select color based on value
            const statusColors = {
                "Active": "#10b981",
                "Inactive": "#ef4444",
                "Not Created": "#eab308"
            };
            $select.css('color', statusColors[value] || "#6b7280");

            // Save to database via temu/ads/update endpoint
            $.ajax({
                url: '/temu/ads/update',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                data: {
                    sku: sku,
                    field: 'status',
                    value: value
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Status updated successfully', 'success');
                    } else {
                        showToast('Failed to update status: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || xhr.statusText || 'Unknown error';
                    console.error('Error updating status:', xhr);
                    showToast('Failed to update status: ' + errorMsg, 'error');
                }
            });
        });

        // Initialize iconClicked flag for IN ROAS
        window.iconClicked = false;

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field && def.field !== '_select') {
                        const visible = savedVisibility[def.field] !== undefined ? savedVisibility[def.field] : def.visible !== false;
                        const li = document.createElement('li');
                        li.className = 'dropdown-item';
                        li.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${def.field}" 
                                       id="col-${def.field}" ${visible ? 'checked' : ''}>
                                <label class="form-check-label" for="col-${def.field}">
                                    ${def.title}
                                </label>
                            </div>
                        `;
                        menu.appendChild(li);
                    }
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

            fetch('/temu-decrease-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && savedVisibility[field] !== undefined) {
                        if (savedVisibility[field]) {
                            col.show();
                        } else {
                            col.hide();
                        }
                    }
                });
            });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });

        table.on('dataLoaded', function(data) {
            if (suppressDataLoadedHandler) {
                suppressDataLoadedHandler = false;
                return;
            }
            fullDataset = (data && Array.isArray(data)) ? data : (table.getData ? table.getData("all") : []) || [];
            applyFilters();
            // Wait a bit to ensure badgeAvgAds is set from ajaxResponse before calculating NPFT
            setTimeout(function() {
                updateSummary();
            }, 50);
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();

            // Auto-store daily average views if not already stored today
            autoStoreDailyAvgViews();

            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('renderComplete', function() {
            updateSummary();
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

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

        // Export L30 — same as Tabulator (visible columns, formatters; respects Ads Section / column visibility)
        $('#export-btn').on('click', function() {
            table.download("csv", "temu_decrease_data.csv");
        });

        // Export L7 — same JSON + columns as L30; load L7 endpoint, then Tabulator CSV (respects Ads Section toggle)
        $('#export-l7-btn').on('click', function() {
            console.log('Export L7: /temu-decrease-data-l7, adsSection=' + (typeof adsColumnsVisible !== 'undefined' && adsColumnsVisible));
            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

            suppressDataLoadedHandler = true;
            table.setData('/temu-decrease-data-l7').then(function() {
                applyFilters();
                table.download("csv", "temu_l7_data.csv");
                // Allow full dataLoaded on L30 restore so fullDataset and summary refresh
                suppressDataLoadedHandler = false;
                return table.setData('/temu-decrease-data');
            }).then(function() {
                applyFilters();
                if (typeof showToast === 'function') {
                    showToast('L7 export completed (same format as L30)', 'success');
                }
            }).catch(function(err) {
                console.error('Export L7 error', err);
                suppressDataLoadedHandler = false;
                return table.setData('/temu-decrease-data').then(function() {
                    applyFilters();
                }).then(function() {
                    if (typeof showToast === 'function') {
                        showToast('Failed to export L7 data', 'error');
                    }
                });
            }).finally(function() {
                $btn.prop('disabled', false).html(originalHtml);
            });
        });

        // L7 Sales upload (same format as L30, stored in temu_daily_data_l7)
        $('#temu-l7-sales-upload-btn').on('click', function() {
            $('#temu-l7-sales-upload-file').off('change').on('change', function() {
                const file = this.files[0];
                if (!file) return;
                const $status = $('#temu-l7-sales-upload-status');
                $status.text('Uploading...').css('color', '');
                const totalChunks = 5;
                const uploadId = 'temu_l7_' + Date.now();
                let currentChunk = 0;
                let totalImported = 0;

                function uploadNextChunk() {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('chunk', currentChunk);
                    formData.append('totalChunks', totalChunks);
                    formData.append('uploadId', uploadId);
                    formData.append('_token', '{{ csrf_token() }}');

                    $.ajax({
                        url: '/temu/upload-daily-data-l7-chunk',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                totalImported += response.imported || 0;
                                $status.text('Chunk ' + (currentChunk + 1) + '/' + totalChunks + '...');
                                if (currentChunk < totalChunks - 1) {
                                    currentChunk++;
                                    setTimeout(uploadNextChunk, 300);
                                } else {
                                    $status.text('Done. ' + totalImported + ' rows.').css('color', 'green');
                                    showToast('L7 Sales upload completed. ' + totalImported + ' records.', 'success');
                                    if (table) table.setData('/temu-decrease-data');
                                }
                            } else {
                                $status.text('Error').css('color', 'red');
                                showToast(response.message || 'Upload failed', 'error');
                            }
                        },
                        error: function(xhr) {
                            const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Upload failed';
                            $status.text('Error').css('color', 'red');
                            showToast(msg, 'error');
                        }
                    });
                }
                uploadNextChunk();
            });
            $('#temu-l7-sales-upload-file')[0].click();
        });

        // Single-badge history modal: click on a badge opens history for that metric
        var currentBadgeHistoryMetric = null;
        var currentBadgeHistoryLabel = null;

        function formatBadgeHistoryValue(metric, val) {
            var n = Number(val);
            if (metric === 'total_sales' || metric === 'total_spend') {
                return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (metric === 'avg_cvr_pct') {
                return n.toFixed(2) + '%';
            }
            if (metric === 'avg_views') {
                return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
            }
            return n.toLocaleString();
        }

        function loadBadgeHistoryModal() {
            if (!currentBadgeHistoryMetric || !currentBadgeHistoryLabel) return;
            var days = $('#badgeHistoryModalDays').val();
            var tbody = document.getElementById('badgeHistoryModalTbody');
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>';
            fetch('/temu-badge-history?days=' + encodeURIComponent(days))
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var data = res.data || [];
                    var key = currentBadgeHistoryMetric;
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No history. Run <code>php artisan temu:collect-metrics</code> to populate.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(function(row) {
                        var val = row[key];
                        return '<tr><td>' + row.record_date + '</td><td>' + formatBadgeHistoryValue(key, val) + '</td></tr>';
                    }).join('');
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger">Failed to load history.</td></tr>';
                });
        }

        $(document).on('click', '.temu-badge-history', function(e) {
            e.preventDefault();
            var metric = $(this).data('badge-metric');
            var label = $(this).data('badge-label');
            if (!metric || !label) return;
            currentBadgeChartMetricKey = metric;
            currentBadgeChartLabel = label;
            $('#badgeTrendChartTitle').text(label);
            var days = parseInt($('#badgeTrendChartDays').val(), 10) || 30;
            $('#badgeTrendChartSuffix').text('(Rolling L' + days + ')');
            var modalEl = document.getElementById('badgeTrendChartModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            loadBadgeChartData(metric, label, days);
        });

        $('#badgeTrendChartDays').on('change', function() {
            var days = parseInt($(this).val(), 10) || 30;
            $('#badgeTrendChartSuffix').text('(Rolling L' + days + ')');
            loadBadgeChartData(currentBadgeChartMetricKey, currentBadgeChartLabel, days);
        });
    });
</script>
@endsection
