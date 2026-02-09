@extends('layouts.vertical', ['title' => 'eBay Pricing Decrease', 'sidenav' => 'condensed'])

@section('css')
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
        'page_title' => 'eBay Pricing Decrease',
        'sub_title' => 'eBay Pricing Decrease',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="section-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>Section Filter</option>
                        <option value="pricing">Pricing</option>
                        <option value="kw_ads">KW Ads</option>
                        <option value="pmt_ads">PMT Ads</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50-60">50-60%</option>
                        <option value="60plus">60%+</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR</option>
                        <option value="0-0">0 to 0.00%</option>
                        <option value="0.01-1">0.01 - 1%</option>
                        <option value="1-2">1-2%</option>
                        <option value="2-3">2-3%</option>
                        <option value="3-4">3-4%</option>
                        <option value="0-4">0-4%</option>
                        <option value="4-7">4-7%</option>
                        <option value="7-10">7-10%</option>
                        <option value="10plus">10%+</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="REQ">REQ</option>
                        <option value="NR">NR</option>
                    </select>

                    <select id="ads-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">AD%</option>
                        <option value="0-10">Below 10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-100">30-100%</option>
                        <option value="100plus">100%+</option>
                    </select>

                    <!-- Unified Range Filter (E L30 & Views) -->
                    <select id="range-column-select" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="">Select Filter</option>
                        <option value="E_L30">E L30</option>
                        <option value="views">Views</option>
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

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>

                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import Ratings
                    </button>

                    <a href="{{ url('/ebay-ratings-sample') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (INV > 0)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter items with sales (INV>0)">> 0 Sold: 0</span>
                        
                        <!-- Price Comparison Badge -->
                        <span class="badge bg-danger fs-6 p-2 price-filter-badge" data-filter="prc-gt-lmp" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter items where Prc > LMP">
                            Prc > LMP: <span id="prc-gt-lmp-count">0</span>
                        </span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="kw-spend-badge" style="color: black; font-weight: bold;">KW Spend: $0</span>
                        <span class="badge bg-warning fs-6 p-2" id="pmt-spend-badge" style="color: black; font-weight: bold;">PMT Spend: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Total Spend: $0.00</span>
                        
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge" style="color: black; font-weight: bold;">NPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge" style="color: black; font-weight: bold;">GROI: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="nroi-percent-badge" style="color: black; font-weight: bold;">NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="tacos-percent-badge" style="color: black; font-weight: bold;">TACOS: 0%</span>
                        
                        <!-- eBay Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">CVR: 0.00%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color: black; font-weight: bold;">INV: 0</span>
                        
                        <!-- Badge Filters -->
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs (INV>0)">Missing: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="map-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter mapped SKUs (INV>0)">Map: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="not-map-count-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter not mapped SKUs (INV>0)">N MP: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amazon (INV>0)">< Amz: 0</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amazon (INV>0)">> Amz: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="sugg-amz-prc-selected-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-amazon"></i> Suggest Amazon Price
                        </button>
                        <button id="clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay-table" style="flex: 1;"></div>
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

    <!-- Campaign Details Modal (ACOS info icon) -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignModalLabel">Campaign Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="campaignModalBody">
                    <!-- Content loaded via JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

    @section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "ebay_tabulator_column_visibility";
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let selectedSkus = new Set(); // Track selected SKUs across all pages
        
        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingFilterActive = false;
        let mapFilterActive = false;
        let notMapFilterActive = false;
        let lessAmzFilterActive = false;
        let moreAmzFilterActive = false;
        let priceFilterActive = false; // Track price filter state: true = show only Prc > LMP
        
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
                        skuMetricsChart.data.datasets[3].data = data.map(d => d.ad_percent || 0);
                        skuMetricsChart.update('active');
                        console.log('Chart updated successfully with', data.length, 'data points');
                    }
                })
                .catch(error => {
                    console.error('Error loading SKU metrics data:', error);
                    alert('Error loading metrics data. Please check console for details.');
                });
        }

        // Load eBay KW and PMT spend totals from reports (matches KW/PMP ads pages exactly)
        function loadEbayKwPmtSpendTotals() {
            fetch('/ebay-kw-pmt-spend-totals')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update spend badges with grand totals from reports
                        $('#kw-spend-badge').text('KW Spend: $' + Math.round(data.kw_spend).toLocaleString());
                        $('#pmt-spend-badge').text('PMT Spend: $' + Math.round(data.pmt_spend).toLocaleString());
                        $('#total-spend-badge').text('Total Spend: $' + Math.round(data.total_spend).toLocaleString());
                        console.log('Loaded spend totals:', data);
                    }
                })
                .catch(error => {
                    console.error('Error loading eBay KW/PMT spend totals:', error);
                });
        }

        $(document).ready(function() {
            // Initialize SKU-specific chart
            initSkuMetricsChart();
            
            // Load eBay KW and PMT spend totals (grand totals from reports - matches KW/PMP ads pages)
            loadEbayKwPmtSpendTotals();

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

            // Decrease button toggle
            $('#decrease-btn').on('click', function() {
                decreaseModeActive = !decreaseModeActive;
                increaseModeActive = false; // Disable increase mode
                const selectColumn = table.getColumn('_select');
                
                if (decreaseModeActive) {
                    selectColumn.show();
                    $(this).removeClass('btn-warning').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                    $('#increase-btn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
                } else {
                    selectColumn.hide();
                    $(this).removeClass('btn-danger').addClass('btn-warning');
                    $(this).html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                    selectedSkus.clear();
                    updateSelectedCount();
                    updateSelectAllCheckbox();
                }
            });
            
            // Increase Mode Toggle
            $('#increase-btn').on('click', function() {
                increaseModeActive = !increaseModeActive;
                decreaseModeActive = false; // Disable decrease mode
                const selectColumn = table.getColumn('_select');
                
                if (increaseModeActive) {
                    selectColumn.show();
                    $(this).removeClass('btn-success').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                    $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                } else {
                    selectColumn.hide();
                    selectedSkus.clear();
                    $(this).removeClass('btn-danger').addClass('btn-success');
                    $(this).html('<i class="fas fa-arrow-up"></i> Increase Mode');
                    updateSelectedCount();
                    updateSelectAllCheckbox();
                }
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

            $('#missing-count-badge').on('click', function() {
                missingFilterActive = !missingFilterActive;
                mapFilterActive = false;
                applyFilters();
            });

            $('#map-count-badge').on('click', function() {
                mapFilterActive = !mapFilterActive;
                missingFilterActive = false;
                notMapFilterActive = false;
                applyFilters();
            });

            $('#not-map-count-badge').on('click', function() {
                notMapFilterActive = !notMapFilterActive;
                mapFilterActive = false;
                missingFilterActive = false;
                applyFilters();
            });

            $('#less-amz-badge').on('click', function() {
                lessAmzFilterActive = !lessAmzFilterActive;
                moreAmzFilterActive = false;
                applyFilters();
            });

            $('#more-amz-badge').on('click', function() {
                moreAmzFilterActive = !moreAmzFilterActive;
                lessAmzFilterActive = false;
                applyFilters();
            });

            // Suggest Amazon Price button handler (top button)
            $('#sugg-amz-prc-btn').on('click', function() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first (enable Decrease/Increase mode)', 'error');
                    return;
                }
                applySuggestAmazonPrice();
            });

            // Suggest Amazon Price button handler (in selection container)
            $('#sugg-amz-prc-selected-btn').on('click', function() {
                applySuggestAmazonPrice();
            });

            // Clear SPRICE button handler (top button)
            $('#clear-sprice-btn').on('click', function() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first (enable Decrease/Increase mode)', 'error');
                    return;
                }
                if (confirm('Are you sure you want to clear SPRICE for selected SKUs?')) {
                    clearSpriceForSelected();
                }
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
                const column = $item.data('column');
                const color = $item.data('color');
                const dropdown = $item.closest('.dropdown');
                const button = dropdown.find('.dropdown-toggle');
                
                dropdown.find('.column-filter').removeClass('active');
                $item.addClass('active');
                
                const statusCircle = $item.find('.status-circle').clone();
                button.html('').append(statusCircle).append(' DIL%');
                
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
                        row.update({ SPRICE_STATUS: 'processing' });
                    }
                    
                    $.ajax({
                        url: '/ebay-one/save-sprice',
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
                            url: '/push-ebay-price-tabulator',
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
                        url: '/push-ebay-price-tabulator',
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
                const discountValue = parseFloat($('#discount-percentage-input').val());
                const discountType = $('#discount-type-select').val();
                
                if (isNaN(discountValue) || discountValue <= 0) {
                    showToast('Please enter a valid discount value', 'error');
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
                    const isParent = row.Parent && row.Parent.startsWith('PARENT');
                    if (isParent) return;
                    
                    const sku = row['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        const currentPrice = parseFloat(row['eBay Price']) || 0;
                        if (currentPrice > 0) {
                            let newSPrice;
                            
                            if (discountType === 'percentage') {
                                if (increaseModeActive) {
                                    newSPrice = currentPrice * (1 + discountValue / 100);
                                } else {
                                    newSPrice = currentPrice * (1 - discountValue / 100);
                                }
                            } else { // value
                                if (increaseModeActive) {
                                    newSPrice = currentPrice + discountValue;
                                } else {
                                    newSPrice = currentPrice - discountValue;
                                }
                            }
                            
                            newSPrice = Math.max(0.01, newSPrice);
                            
                            // Store original SPRICE value for potential revert
                            const originalSPrice = parseFloat(row['SPRICE']) || 0;
                            
                            // Find the table row
                            const tableRow = table.getRows().find(r => {
                                const rowData = r.getData();
                                return rowData['(Child) sku'] === sku;
                            });
                            
                            // Update SPRICE instantly in the table
                            if (tableRow) {
                                tableRow.update({ 
                                    SPRICE: newSPrice,
                                    SPRICE_STATUS: 'processing'
                                });
                            }
                            
                            // Save SPRICE in database with retry
                            saveSpriceWithRetry(sku, newSPrice, tableRow)
                                .then((response) => {
                                    updatedCount++;
                                    if (updatedCount + errorCount === totalSkus) {
                                        if (errorCount === 0) {
                                            showToast(`Discount applied to ${updatedCount} SKU(s)`, 'success');
                                        } else {
                                            showToast(`Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                        }
                                    }
                                })
                                .catch((error) => {
                                    errorCount++;
                                    // Revert SPRICE on error
                                    if (tableRow) {
                                        tableRow.update({ SPRICE: originalSPrice });
                                    }
                                    if (updatedCount + errorCount === totalSkus) {
                                        showToast(`Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                    }
                                });
                        }
                    }
                });
            }

            // Apply Suggest Amazon Price to selected SKUs
            function applySuggestAmazonPrice() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first', 'error');
                    return;
                }

                let updatedCount = 0;
                let noAmazonPriceCount = 0;

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows("(Child) sku", "=", sku);
                    
                    if (rows.length > 0) {
                        const row = rows[0];
                        const rowData = row.getData();
                        const amazonPrice = parseFloat(rowData['A Price']);
                        
                        if (amazonPrice && amazonPrice > 0) {
                            row.update({
                                SPRICE: amazonPrice
                            });
                            
                            row.reformat();
                            saveSpriceWithRetry(sku, amazonPrice, row);
                            updatedCount++;
                        } else {
                            noAmazonPriceCount++;
                        }
                    } else {
                        noAmazonPriceCount++;
                    }
                });
                
                let message = `Amazon price applied to ${updatedCount} SKU(s)`;
                if (noAmazonPriceCount > 0) {
                    message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price)`;
                }

                showToast(message, updatedCount > 0 ? 'success' : 'error');
            }

            // Clear SPRICE for selected SKUs
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

            // Event delegation for eye button clicks (add to SKU column formatter)
            table = new Tabulator("#ebay-table", {
                ajaxURL: "/ebay-data-json",
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
                            
                            let html = `<span>${sku}</span>`;
                            
                            // Copy button
                            html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                            
                            // Metrics chart button
                            html += `<button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;">
                                        <i class="fa fa-info-circle"></i>
                                     </button>`;
                            
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
                        width: 100,
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['B Link'] || '';
                            const sellerLink = rowData['S Link'] || '';
                            
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
                        title: "E L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 30,
                        sorter: "number"
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
                            if (inv > 0 && ebayStock === 0) {
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${inv})</span>`;
                            }
                            if (inv > 0 && ebayStock > 0) {
                                if (inv === ebayStock) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                } else {
                                    const diff = inv - ebayStock;
                                    const sign = diff > 0 ? '+' : '';
                                    return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                                }
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
                        title: "S CVR",
                        field: "SCVR",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            
                            const aViews = parseFloat(aData.views || 0);
                            const bViews = parseFloat(bData.views || 0);
                            const aL30 = parseFloat(aData['eBay L30'] || 0);
                            const bL30 = parseFloat(bData['eBay L30'] || 0);
                            
                            const aValue = aViews === 0 ? 0 : (aL30 / aViews) * 100;
                            const bValue = bViews === 0 ? 0 : (bL30 / bViews) * 100;
                            
                            return aValue - bValue;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const views = parseFloat(rowData.views || 0);
                            const l30 = parseFloat(rowData['eBay L30'] || 0);
                            
                            const scvrValue = views > 0 ? (l30 / views) * 100 : 0;
                            let color = '';
                            
                            // getCvrColor logic - 0% should be RED
                            if (scvrValue <= 4) color = '#a00211'; // red (includes 0%)
                            else if (scvrValue > 4 && scvrValue <= 7) color = '#ffc107'; // yellow
                            else if (scvrValue > 7 && scvrValue <= 10) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${scvrValue.toFixed(1)}%</span>`;
                        },
                        width: 60
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
                            const rowData = cell.getRow().getData();
                            const amazonPrice = parseFloat(rowData['A Price']) || 0;
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);
                            
                            if (value === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }
                            
                            // Priority 1: Check if price > LMP (highest priority - red)
                            if (lmpPrice > 0 && value > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 600;">$${value.toFixed(2)}</span>`;
                            }
                            
                            // Priority 2: Color code based on Amazon price comparison
                            if (amazonPrice > 0 && value > 0) {
                                if (value < amazonPrice) {
                                    return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>`;
                                } else if (value > amazonPrice) {
                                    return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>`;
                                }
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
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            // Custom sorter to handle the 100% case
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            
                            const aKwSpend = parseFloat(aData['kw_spend_L30'] || 0);
                            const bKwSpend = parseFloat(bData['kw_spend_L30'] || 0);
                            
                            // Calculate effective AD% (100 if kw_spend > 0 and AD% is 0)
                            let aVal = parseFloat(a || 0);
                            let bVal = parseFloat(b || 0);
                            
                            if (aKwSpend > 0 && aVal === 0) aVal = 100;
                            if (bKwSpend > 0 && bVal === 0) bVal = 100;
                            
                            return aVal - bVal;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            
                            const rowData = cell.getRow().getData();
                            const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                            const adPercent = parseFloat(value || 0);
                            const sku = rowData["(Child) sku"] || rowData.SKU || rowData.sku || '';
                            const iconHtml = sku ? ` <i class="fas fa-info-circle acos-info-icon" style="cursor: pointer; color: #6c757d; margin-left: 5px;" data-sku="${sku}" title="View Campaign Details"></i>` : '';
                            
                            // If KW ads spend > 0 but AD% is 0, show red alert
                            if (kwSpend > 0 && adPercent === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>${iconHtml}`;
                            }
                            
                            return `${parseFloat(value).toFixed(0)}%${iconHtml}`;
                        },
                        width: 70
                    },

                     {
                        title: "PFT %",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const gpft = parseFloat(rowData['GPFT%'] || 0);
                            const ad = parseFloat(rowData['AD%'] || 0);
                            
                            // PFT% = GPFT% - AD%
                            const percent = gpft - ad;
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
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
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
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <span>Select</span>
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');
                            // 
                            // if (isParent) return '';
                            
                            const sku = rowData['(Child) sku'];
                            const isSelected = selectedSkus.has(sku);
                            
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                        }
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
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                            
                            // Show lowest price
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
                    // {
                    //     title: "AD <br> Spend <br> L30",
                    //     field: "AD_Spend_L30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                    // {
                    //     title: "AD Sales L30",
                    //     field: "AD_Sales_L30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                    // {
                    //     title: "AD Units L30",
                    //     field: "AD_Units_L30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     width: 120
                    // },
                   
                    // {
                    //     title: "TACOS <br> L30",
                    //     field: "TacosL30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: function(cell) {
                    //         const value = cell.getValue();
                    //         if (value === null || value === undefined) return '';
                    //         const percent = parseFloat(value) * 100;
                    //         let color = '';
                            
                    //         // getTacosColor logic from inc/dec page
                    //         if (percent <= 7) color = '#e83e8c'; // pink
                    //         else if (percent > 7 && percent <= 14) color = '#28a745'; // green
                    //         else if (percent > 14 && percent <= 21) color = '#ffc107'; // yellow
                    //         else color = '#a00211'; // red
                            
                    //         return `<span style="color: ${color}; font-weight: 600;">${parseFloat(value).toFixed(2)}%</span>`;
                    //     },
                    //     width: 80
                    // },
                    // {
                    //     title: "Total Sales L30",
                    //     field: "T_Sale_l30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 140
                    // },
                    // {
                    //     title: "Total Profit",
                    //     field: "Total_pft",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                    //     width: 130
                    // },
                   
                   
                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const ebayPrice = parseFloat(rowData['eBay Price']) || 0;
                            const sprice = parseFloat(value) || 0;
                            
                            if (!value) return '';
                            
                            // If SPRICE matches eBay Price, show blank
                            if (sprice === ebayPrice) {
                                return '<span style="color: #999; font-style: italic;">-</span>';
                            }
                            
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
                                titleText = 'Error applying price to eBay';
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
                                                table.replaceData();
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
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                                const currentStatus = $btn.attr('data-status') || '';
                                
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
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sgpft = parseFloat(rowData.SGPFT || 0);
                            const ad = parseFloat(rowData['AD%'] || 0);
                            
                            // SPFT = SGPFT - AD%
                            const percent = sgpft - ad;
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
                        sorter: "number",
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


                        {
                        title: "SPEND L30",
                        field: "AD_Spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>$${value.toFixed(2)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 90
                    },

                        {
                        title: "KW SPEND L30",
                        field: "kw_spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `$${value.toFixed(2)}`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 110
                    },

                    {
                        title: "PMT SPEND L30",
                        field: "pmt_spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `$${value.toFixed(2)}`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 110
                    },

                    // ========== KW Ads Section Columns (hidden by default) ==========
                    {
                        title: "Missing Ad",
                        field: "kw_hasCampaign",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.kw_campaign_id && row.kw_campaign_id !== '';
                            var nrReq = (row.nr_req || '').trim();

                            var dotColor, title;
                            if (nrReq === 'NR' || nrReq === 'NRL') {
                                dotColor = '#ffc107'; // yellow
                                title = 'NR - Not Required';
                            } else if (hasCampaign) {
                                dotColor = '#28a745'; // green
                                title = 'Campaign Exists';
                            } else {
                                dotColor = '#dc3545'; // red
                                title = 'Campaign Missing';
                            }

                            return '<div style="display: flex; align-items: center; justify-content: center;">' +
                                '<span style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: ' + dotColor + ';" title="' + title + '"></span>' +
                                '</div>';
                        },
                        width: 80
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
                        title: "BGT",
                        field: "kw_campaignBudgetAmount",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            return parseFloat(cell.getValue() || 0).toFixed(0);
                        },
                        width: 60
                    },
                    {
                        title: "SBGT",
                        field: "kw_sbgt",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var acos = parseFloat(rowData.kw_acos || 0);
                            if (isNaN(acos) || acos === 0) {
                                acos = 100;
                            }
                            var suggestedBudget = 0;
                            if (acos < 4) {
                                suggestedBudget = 9;
                            } else if (acos >= 4 && acos < 8) {
                                suggestedBudget = 6;
                            } else {
                                suggestedBudget = 3;
                            }
                            return suggestedBudget.toFixed(0);
                        },
                        width: 60
                    },
                    {
                        title: "ACOS",
                        field: "kw_acos",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var kwSpend = parseFloat(rowData.kw_spend_L30 || 0);
                            var acos = parseFloat(cell.getValue() || 0);
                            
                            if (kwSpend === 0) {
                                return '-';
                            }
                            
                            var td = cell.getElement();
                            td.style.removeProperty('background-color');
                            
                            if (acos === 0) {
                                td.style.backgroundColor = '#f8d7da';
                                return '100%';
                            } else if (acos < 7) {
                                td.style.backgroundColor = '#f8d7f8';
                                return acos.toFixed(0) + '%';
                            } else if (acos >= 7 && acos <= 14) {
                                td.style.backgroundColor = '#d4edda';
                                return acos.toFixed(0) + '%';
                            } else {
                                td.style.backgroundColor = '#f8d7da';
                                return acos.toFixed(0) + '%';
                            }
                        },
                        width: 70
                    },
                    {
                        title: "CLICKS",
                        field: "kw_clicks",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            return parseInt(cell.getValue() || 0).toLocaleString();
                        },
                        width: 70
                    },
                    {
                        title: "AD SOLD",
                        field: "kw_ad_sold",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            return parseInt(cell.getValue() || 0).toLocaleString();
                        },
                        width: 70
                    },
                    {
                        title: "7UB%",
                        field: "kw_l7_spend",
                        hozAlign: "center",
                        visible: false,
                        sorter: function(a, b, aRow, bRow) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb7 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(aData.kw_l7_spend || 0) / (parseFloat(aData.kw_campaignBudgetAmount) * 7)) * 100 : 0;
                            var bUb7 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(bData.kw_l7_spend || 0) / (parseFloat(bData.kw_campaignBudgetAmount) * 7)) * 100 : 0;
                            return aUb7 - bUb7;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.kw_l7_spend) || 0;
                            var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.style.removeProperty('background-color');
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.style.backgroundColor = '#d4edda';
                            } else if (ub7 > 99) {
                                td.style.backgroundColor = '#f8d7f8';
                            } else if (ub7 < 66 && budget > 0) {
                                td.style.backgroundColor = '#f8d7da';
                            }
                            return ub7.toFixed(0) + '%';
                        },
                        width: 70
                    },
                    {
                        title: "1UB%",
                        field: "kw_l1_spend",
                        hozAlign: "center",
                        visible: false,
                        sorter: function(a, b, aRow, bRow) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb1 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(aData.kw_l1_spend || 0) / parseFloat(aData.kw_campaignBudgetAmount)) * 100 : 0;
                            var bUb1 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(bData.kw_l1_spend || 0) / parseFloat(bData.kw_campaignBudgetAmount)) * 100 : 0;
                            return aUb1 - bUb1;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.kw_l1_spend) || 0;
                            var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            var td = cell.getElement();
                            td.style.removeProperty('background-color');
                            if (ub1 >= 66 && ub1 <= 99) {
                                td.style.backgroundColor = '#d4edda';
                            } else if (ub1 > 99) {
                                td.style.backgroundColor = '#f8d7f8';
                            } else if (ub1 < 66 && budget > 0) {
                                td.style.backgroundColor = '#f8d7da';
                            }
                            return ub1.toFixed(0) + '%';
                        },
                        width: 70
                    },
                    {
                        title: "L7 CPC",
                        field: "kw_l7_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value > 0 ? value.toFixed(2) : '-';
                        },
                        width: 70
                    },
                    {
                        title: "L1 CPC",
                        field: "kw_l1_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value > 0 ? value.toFixed(2) : '-';
                        },
                        width: 70
                    },
                    {
                        title: "LBID",
                        field: "kw_last_sbid",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "SBID",
                        field: "kw_sbid_calc",
                        hozAlign: "center",
                        visible: false,
                        sorter: function(a, b, aRow, bRow) {
                            function calcSbid(rowData) {
                                var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                                var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                                var budget = parseFloat(rowData.kw_campaignBudgetAmount) || 0;
                                var inv = parseFloat(rowData.INV || 0);
                                var price = parseFloat(rowData['eBay Price'] || 0);
                                var ub7 = budget > 0 ? (parseFloat(rowData.kw_l7_spend) || 0) / (budget * 7) * 100 : 0;
                                var ub1 = budget > 0 ? (parseFloat(rowData.kw_l1_spend) || 0) / budget * 100 : 0;
                                var lastSbidRaw = rowData.kw_last_sbid;
                                var lastSbid = (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0') ? 0 : parseFloat(lastSbidRaw) || 0;

                                if (ub7 > 99 && ub1 > 99) {
                                    if (l1Cpc > 1.25) return Math.floor(l1Cpc * 0.80 * 100) / 100;
                                    if (l1Cpc > 0) return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    if (l7Cpc > 0) return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    return 0;
                                }
                                var isOver = ub7 > 99 && ub1 > 99;
                                var isUnder = !isOver && budget > 0 && ub7 < 66 && ub1 < 66 && inv > 0;
                                if (isOver) {
                                    var sbid = l1Cpc > 1.25 ? Math.floor(l1Cpc * 0.80 * 100) / 100 : (l1Cpc > 0 ? Math.floor(l1Cpc * 0.90 * 100) / 100 : 0);
                                    if (price < 20 && sbid > 0.20) sbid = 0.20;
                                    return sbid;
                                }
                                if (isUnder) {
                                    var baseBid = lastSbid > 0 ? lastSbid : (l1Cpc > 0 ? l1Cpc : (l7Cpc > 0 ? l7Cpc : 0));
                                    if (ub1 < 33) return Math.floor((baseBid + 0.10) * 100) / 100;
                                    if (ub1 >= 33 && ub1 < 66) return Math.floor(baseBid * 1.10 * 100) / 100;
                                    return Math.floor(baseBid * 100) / 100;
                                }
                                if (l1Cpc > 0) return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                if (l7Cpc > 0) return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                return 0;
                            }
                            return calcSbid(aRow.getData()) - calcSbid(bRow.getData());
                        },
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                            var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                            var budget = parseFloat(rowData.kw_campaignBudgetAmount) || 0;
                            var inv = parseFloat(rowData.INV || 0);
                            var price = parseFloat(rowData['eBay Price'] || 0);
                            var ub7 = budget > 0 ? (parseFloat(rowData.kw_l7_spend) || 0) / (budget * 7) * 100 : 0;
                            var ub1 = budget > 0 ? (parseFloat(rowData.kw_l1_spend) || 0) / budget * 100 : 0;
                            var lastSbidRaw = rowData.kw_last_sbid;
                            var lastSbid = (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0') ? 0 : parseFloat(lastSbidRaw) || 0;

                            var sbid = 0;
                            if (ub7 > 99 && ub1 > 99) {
                                if (l1Cpc > 1.25) sbid = Math.floor(l1Cpc * 0.80 * 100) / 100;
                                else if (l1Cpc > 0) sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                else if (l7Cpc > 0) sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                if (price < 20 && sbid > 0.20) sbid = 0.20;
                            } else {
                                var isUnder = budget > 0 && ub7 < 66 && ub1 < 66 && inv > 0;
                                if (isUnder) {
                                    var baseBid = lastSbid > 0 ? lastSbid : (l1Cpc > 0 ? l1Cpc : (l7Cpc > 0 ? l7Cpc : 0));
                                    if (ub1 < 33) sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                    else if (ub1 >= 33 && ub1 < 66) sbid = Math.floor(baseBid * 1.10 * 100) / 100;
                                    else sbid = Math.floor(baseBid * 100) / 100;
                                } else {
                                    if (l1Cpc > 0) sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    else if (l7Cpc > 0) sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                }
                            }
                            return sbid > 0 ? sbid.toFixed(2) : '-';
                        },
                        width: 70
                    },
                    {
                        title: "SBID M",
                        field: "kw_sbid_m",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "APR BID",
                        field: "kw_apr_bid",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var apprSbid = rowData.kw_apprSbid || '';
                            if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) {
                                return `<div style="display: flex; justify-content: center; align-items: center;">
                                    <i class="fa-solid fa-circle-check" style="color: #28a745; font-size: 20px;" title="Bid pushed: ${apprSbid}"></i>
                                </div>`;
                            } else {
                                return `<div style="display: flex; justify-content: center; align-items: center;">
                                    <i class="fa-solid fa-check" style="color: #6c757d; font-size: 18px;" title="Not pushed"></i>
                                </div>`;
                            }
                        },
                        width: 70
                    },
                    {
                        title: "Status",
                        field: "kw_campaignStatus",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var status = cell.getValue() || '';
                            if (!status || status === '') {
                                return '<span style="color: #999;">-</span>';
                            }
                            var isRunning = status === 'RUNNING';
                            var color = isRunning ? '#28a745' : '#dc3545';
                            var icon = isRunning ? 'fa-circle' : 'fa-circle-pause';
                            return `<span style="color: ${color};" title="${status}"><i class="fa-solid ${icon}"></i></span>`;
                        },
                        width: 60
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
                    // }
                ]
            });

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
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

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const statusFilter = $('#status-filter').val();
                const adsFilter = $('#ads-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const rangeMin = parseFloat($('#range-min').val()) || null;
                const rangeMax = parseFloat($('#range-max').val()) || null;
                const rangeColumn = $('#range-column-select').val() || '';

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
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
                        if (gpftFilter === '50-60') return gpft >= 50 && gpft < 60;
                        if (gpftFilter === '60plus') return gpft >= 60;
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

                if (statusFilter !== 'all') {
                    table.addFilter(function(data) {
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;
                        
                        const status = data.nr_req || '';
                        
                        if (statusFilter === 'REQ') {
                            return status === 'REQ';
                        } else if (statusFilter === 'NR') {
                            return status === 'NR';
                        }
                        return true;
                    });
                }

                if (adsFilter !== 'all') {
                    table.addFilter(function(data) {
                        const adValue = data['AD%'];
                        const kwSpend = parseFloat(data['kw_spend_L30'] || 0);
                        
                        // If KW spend > 0 but AD% is 0, treat as 100% (same as formatter logic)
                        let adPercent;
                        if (kwSpend > 0 && (adValue === null || adValue === undefined || adValue === '' || parseFloat(adValue) === 0)) {
                            adPercent = 100;
                        } else if (adValue === null || adValue === undefined || adValue === '' || isNaN(parseFloat(adValue))) {
                            // Skip items with no valid AD% value and no KW spend
                            return false;
                        } else {
                            adPercent = parseFloat(adValue);
                        }
                        
                        if (adsFilter === '0-10') return adPercent >= 0 && adPercent < 10;
                        if (adsFilter === '10-20') return adPercent >= 10 && adPercent < 20;
                        if (adsFilter === '20-30') return adPercent >= 20 && adPercent < 30;
                        if (adsFilter === '30-100') return adPercent >= 30 && adPercent <= 100;
                        if (adsFilter === '100plus') return adPercent > 100;
                        return true;
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

                // Badge Filters (only INV > 0)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const inv = parseFloat(data['INV']) || 0;
                        return ebayL30 === 0 && inv > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const inv = parseFloat(data['INV']) || 0;
                        return ebayL30 > 0 && inv > 0;
                    });
                }

                if (missingFilterActive) {
                    table.addFilter(function(data) {
                        const itemId = data['eBay_item_id'];
                        const inv = parseFloat(data['INV']) || 0;
                        return (!itemId || itemId === null || itemId === '') && inv > 0;
                    });
                }

                if (mapFilterActive) {
                    table.addFilter(function(data) {
                        const itemId = data['eBay_item_id'];
                        const inv = parseFloat(data['INV']) || 0;
                        if (!itemId || itemId === null || itemId === '' || inv === 0) return false;
                        
                        const ebayStock = parseFloat(data['eBay Stock']) || 0;
                        return inv > 0 && ebayStock > 0 && inv === ebayStock;
                    });
                }

                if (notMapFilterActive) {
                    table.addFilter(function(data) {
                        const itemId = data['eBay_item_id'];
                        const inv = parseFloat(data['INV']) || 0;
                        if (!itemId || itemId === null || itemId === '' || inv === 0) return false;
                        
                        const ebayStock = parseFloat(data['eBay Stock']) || 0;
                        return inv > 0 && (ebayStock === 0 || (ebayStock > 0 && inv !== ebayStock));
                    });
                }

                if (lessAmzFilterActive) {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const ebayPrice = parseFloat(data['eBay Price']) || 0;
                        const amazonPrice = parseFloat(data['A Price']) || 0;
                        return inv > 0 && amazonPrice > 0 && ebayPrice > 0 && ebayPrice < amazonPrice;
                    });
                }

                if (moreAmzFilterActive) {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const ebayPrice = parseFloat(data['eBay Price']) || 0;
                        const amazonPrice = parseFloat(data['A Price']) || 0;
                        return inv > 0 && amazonPrice > 0 && ebayPrice > 0 && ebayPrice > amazonPrice;
                    });
                }

                // Price filter (Prc > LMP)
                if (priceFilterActive) {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const ebayPrice = parseFloat(data['eBay Price']) || 0;
                        const lmpPrice = parseFloat(data['lmp_price']) || 0;
                        return inv > 0 && lmpPrice > 0 && ebayPrice > lmpPrice;
                    });
                }

                // Unified Range Filter (E L30 & Views)
                if (rangeColumn && (rangeMin !== null || rangeMax !== null)) {
                    table.addFilter(function(data) {
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
                
                updateCalcValues();
                updateSummary();
                // Update select all checkbox after filter is applied (matching Amazon approach)
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #status-filter, #ads-filter').on('change', function() {
                applyFilters();
            });

            // Section Filter: show/hide column groups
            // Define column groups for each section
            // Columns that are ONLY in pricing section (will be hidden when KW Ads is selected)
            var pricingOnlyColumns = [
                'image_path', 'L30', 'E Dil%', 'eBay Stock', 'MAP', 'nr_req',
                'A Price', 'GPFT%', 'AD%', 'PFT %', 'ROI%', '_select',
                'lmp_price', 'SPRICE', '_accept', 'SGPFT', 'SPFT', 'SROI',
                'AD_Spend_L30', 'pmt_spend_L30'
            ];
            // Columns that are ONLY in KW Ads section (will be hidden in pricing view)
            var kwAdsOnlyColumns = [
                'kw_hasCampaign', 'l7_views', 'kw_campaignBudgetAmount', 'kw_sbgt', 'kw_acos', 'kw_clicks',
                'kw_ad_sold', 'kw_l7_spend', 'kw_l1_spend', 'kw_l7_cpc', 'kw_l1_cpc',
                'kw_last_sbid', 'kw_sbid_calc', 'kw_sbid_m', 'kw_apr_bid', 'kw_campaignStatus'
            ];
            // Columns shared between sections
            var sharedColumns = [
                'Missing', 'INV', 'eBay Price', 'eBay L30', 'views', 'SCVR', 'kw_spend_L30'
            ];

            $('#section-filter').on('change', function() {
                var sectionVal = $(this).val();

                if (sectionVal === 'all' || sectionVal === 'pricing') {
                    // Show pricing columns, hide KW-only columns
                    kwAdsOnlyColumns.forEach(function(col) {
                        try { table.hideColumn(col); } catch(e) {}
                    });
                    pricingOnlyColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                    // Hide kw_spend_L30 in pricing (it shows as part of AD_Spend_L30)
                    try { table.hideColumn('kw_spend_L30'); } catch(e) {}
                } else if (sectionVal === 'kw_ads') {
                    // Hide pricing-only columns, show KW Ads columns
                    pricingOnlyColumns.forEach(function(col) {
                        try { table.hideColumn(col); } catch(e) {}
                    });
                    kwAdsOnlyColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                } else if (sectionVal === 'pmt_ads') {
                    // For now PMT Ads shows same as pricing (can be expanded later)
                    kwAdsOnlyColumns.forEach(function(col) {
                        try { table.hideColumn(col); } catch(e) {}
                    });
                    pricingOnlyColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try { table.showColumn(col); } catch(e) {}
                    });
                    try { table.hideColumn('kw_spend_L30'); } catch(e) {}
                }
            });

            // Range filter event listeners (E L30, Views)
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

            // Update range filter badge
            function updateRangeFilterBadge() {
                const rangeMin = parseFloat($('#range-min').val()) || null;
                const rangeMax = parseFloat($('#range-max').val()) || null;
                const rangeColumn = $('#range-column-select').val() || '';
                
                // Only show badge if filter is active
                if (rangeColumn && (rangeMin !== null || rangeMax !== null)) {
                    const filteredData = table.getData("active");
                    const filteredCount = filteredData.filter(row => !row.is_parent_summary).length;
                    $('#range-filter-count').text(filteredCount);
                    $('#range-filter-count-badge').show();
                } else {
                    $('#range-filter-count-badge').hide();
                }
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
                // Use getData("all") to get ALL data without filters
                const allData = table.getData("all");
                const filteredData = table.getData("active");
                
                // Grand totals from ALL data (matches KW/PMP ads pages)
                let grandTotalKwSpend = 0;
                let grandTotalPmtSpend = 0;
                let grandTotalSpend = 0;
                
                // Calculate grand totals from ALL data (no filters)
                allData.forEach(row => {
                    const inv = parseFloat(row.INV || 0);
                    if (inv > 0) {
                        grandTotalKwSpend += parseFloat(row['kw_spend_L30'] || 0);
                        grandTotalPmtSpend += parseFloat(row['pmt_spend_L30'] || 0);
                        grandTotalSpend += parseFloat(row['AD_Spend_L30'] || 0);
                    }
                });
                
                // Filtered data metrics (for other badges)
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaInv = 0;
                let totalFbaL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;
                let missingCount = 0;
                let mapCount = 0;
                let notMapCount = 0;
                let lessAmzCount = 0;
                let moreAmzCount = 0;
                let prcGtLmpCount = 0;

                filteredData.forEach(row => {
                    const inv = parseFloat(row.INV || 0);
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);
                    
                    if (inv > 0) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaInv += inv;
                        totalFbaL30 += ebayL30;
                        
                        // Count 0 Sold and > 0 Sold (only INV > 0)
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
                        
                        // Count Missing (only INV > 0)
                        const itemId = row['eBay_item_id'];
                        if (!itemId || itemId === null || itemId === '') {
                            missingCount++;
                        }
                        
                        // Count Map and N MP (only INV > 0 and exists in eBay)
                        if (itemId && itemId !== null && itemId !== '') {
                            const ebayStock = parseFloat(row['eBay Stock']) || 0;
                            if (inv > 0 && ebayStock > 0 && inv === ebayStock) {
                                mapCount++;
                            } else if (inv > 0 && (ebayStock === 0 || (ebayStock > 0 && inv !== ebayStock))) {
                                notMapCount++;
                            }
                        }
                        
                        // Count < Amz and > Amz (only INV > 0)
                        const ebayPrice = parseFloat(row['eBay Price']) || 0;
                        const amazonPrice = parseFloat(row['A Price']) || 0;
                        if (amazonPrice > 0 && ebayPrice > 0) {
                            if (ebayPrice < amazonPrice) {
                                lessAmzCount++;
                            } else if (ebayPrice > amazonPrice) {
                                moreAmzCount++;
                            }
                        }
                        
                        // Count Prc > LMP (only INV > 0)
                        const lmpPrice = parseFloat(row['lmp_price'] || 0);
                        if (lmpPrice > 0 && ebayPrice > lmpPrice) {
                            prcGtLmpCount++;
                        }
                    }
                });

                let totalWeightedPrice = 0;
                let totalL30 = 0;
                filteredData.forEach(row => {
                    if (parseFloat(row.INV) > 0) {
                        const price = parseFloat(row['eBay Price'] || 0);
                        const l30 = parseFloat(row['eBay L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
                $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toFixed(2));

                let totalViews = 0;
                filteredData.forEach(row => {
                    if (parseFloat(row.INV) > 0) {
                        totalViews += parseFloat(row.views || 0);
                    }
                });
                const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;
                
                // Calculate TACOS% = (Total Spend / Total Sales) * 100
                const tacosPercent = totalSalesAmt > 0 ? ((grandTotalSpend / totalSalesAmt) * 100) : 0;
                
                // GROI% = (Total PFT / Total COGS) * 100
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;
                
                // GPFT% = (Total PFT / Total Sales) * 100
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
                
                // NPFT% = GPFT% - TACOS%
                const npftPercent = avgGpft - tacosPercent;
                
                // NROI% = GROI% - TACOS%
                const nroiPercent = groiPercent - tacosPercent;
                
                // Update all badges (spend badges are loaded separately via AJAX to match KW/PMP pages)
                $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
                $('#total-sales-amt-badge').text('Total Sales: $' + Math.round(totalSalesAmt).toLocaleString());
                
                $('#avg-gpft-badge').text('GPFT: ' + avgGpft.toFixed(1) + '%');
                $('#avg-pft-badge').text('NPFT: ' + npftPercent.toFixed(1) + '%');
                $('#groi-percent-badge').text('GROI: ' + groiPercent.toFixed(1) + '%');
                $('#nroi-percent-badge').text('NROI: ' + nroiPercent.toFixed(1) + '%');
                $('#tacos-percent-badge').text('TACOS: ' + tacosPercent.toFixed(1) + '%');
                
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#total-inv-badge').text('INV: ' + Math.round(totalFbaInv).toLocaleString());
                
                $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
                $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());
                
                // Update Prc > LMP count
                $('#prc-gt-lmp-count').text(prcGtLmpCount.toLocaleString());
                
                $('#missing-count-badge').text('Missing: ' + missingCount.toLocaleString());
                $('#map-count-badge').text('Map: ' + mapCount.toLocaleString());
                $('#not-map-count-badge').text('N MP: ' + notMapCount.toLocaleString());
                $('#less-amz-badge').text('< Amz: ' + lessAmzCount.toLocaleString());
                $('#more-amz-badge').text('> Amz: ' + moreAmzCount.toLocaleString());
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/ebay-column-visibility', {
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

                fetch('/ebay-column-visibility', {
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
                fetch('/ebay-column-visibility', {
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

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    col.show();
                });
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            // Toggle SPEND L30 breakdown columns
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spendL30-btn")) {
                    let colsToToggle = ["kw_spend_L30", "pmt_spend_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                    
                    // Update column visibility in cache
                    saveColumnVisibilityToServer();
                    buildColumnDropdown();
                }

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
                'eBay Stock': 'eBay Stock',
                'Missing': 'Missing',
                'MAP': 'MAP',
                'eBay Price': 'eBay Price',
                'A Price': 'A Price',
                'lmp_price': 'LMP',
                'AD_Spend_L30': 'Total Spend L30',
                'AD_Sales_L30': 'AD Sales L30',
                'AD_Units_L30': 'AD Units L30',
                'AD%': 'AD%',
                'TacosL30': 'TACOS L30',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'GROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SPFT',
                'SROI': 'SROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'SCVR',
                'kw_spend_L30': 'KW Spend L30',
                'pmt_spend_L30': 'PMT Spend L30',
                'ebay2_ship': 'eBay2 Ship',
                'LP_productmaster': 'LP'
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
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success || 'Ratings imported successfully');
                        
                        // Reload table data
                        setTimeout(() => {
                            table.setData('/ebay-data-json');
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

        // Load Competitors Modal Function
        function loadEbayCompetitorsModal(sku) {
            $('#lmpSku').text(sku);
            
            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');
            
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
                        <th>Item ID</th>
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
                
                html += `
                    <tr class="${rowClass}">
                        <td>
                            <code>${item.item_id}</code>
                        </td>
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
                data: { id: id },
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

        // ACOS Info Icon Click Handler – show KW/PMT campaign modal
        $(document).on('click', '.acos-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (!sku) {
                showToast('error', 'SKU not found');
                return;
            }
            $('#campaignModalLabel').text('Campaign Details - ' + sku);
            $('#campaignModalBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#campaignModal').modal('show');

            $.ajax({
                url: '/ebay-campaign-data-by-sku',
                type: 'GET',
                data: { sku: sku },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    function getAcosColorClass(acos) {
                        if (acos === 0) return '';
                        if (acos < 7) return 'pink-bg';
                        if (acos >= 7 && acos <= 14) return 'green-bg';
                        if (acos > 14) return 'red-bg';
                        return '';
                    }
                    function fmt(val, decimals) {
                        if (val == null || val === '' || (typeof val === 'number' && isNaN(val))) return '-';
                        return Number(val).toFixed(decimals || 0);
                    }
                    function fmtPct(val) {
                        if (val == null || val === '' || (typeof val === 'number' && isNaN(val))) return '-';
                        return Number(val).toFixed(0) + '%';
                    }
                    function fmtBid(val) {
                        if (val == null || val === '' || val === '0') return '-';
                        const n = parseFloat(val);
                        return (n > 0) ? n.toFixed(2) : '-';
                    }
                    function getUbColorClass(ub) {
                        if (ub == null || ub === '' || (typeof ub === 'number' && isNaN(ub))) return '';
                        const n = parseFloat(ub);
                        if (n >= 66 && n <= 99) return 'green-bg';
                        if (n > 99) return 'pink-bg';
                        return 'red-bg';
                    }

                    let html = '';

                    if (response.kw_campaigns && response.kw_campaigns.length > 0) {
                        response.kw_campaigns.forEach(function(c) {
                            const acos = parseFloat(c.acos || 0);
                            html += '<h5 class="mb-3">KW Campaign - ' + (c.campaign_name || 'N/A') + '</h5>';
                            html += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm">';
                            html += '<thead><tr><th>BGT</th><th>SBGT</th><th>ACOS</th><th>Clicks</th><th>Ad Spend</th><th>Ad Sales</th><th>Ad Sold</th>';
                            html += '<th>AD CVR</th><th>7UB%</th><th>1UB%</th><th>L7CPC</th><th>L1CPC</th><th>L BID</th><th>SBID</th></tr></thead><tbody><tr>';
                            html += '<td>' + fmt(c.bgt, 0) + '</td><td>' + fmt(c.sbgt, 0) + '</td>';
                            html += '<td class="' + getAcosColorClass(acos) + '">' + fmtPct(acos) + '</td>';
                            html += '<td>' + fmt(c.clicks) + '</td><td>' + fmt(c.ad_spend, 2) + '</td><td>' + fmt(c.ad_sales, 2) + '</td><td>' + fmt(c.ad_sold) + '</td>';
                            html += '<td>' + fmtPct(c.ad_cvr) + '</td>';
                            html += '<td class="' + getUbColorClass(c['7ub']) + '">' + (c['7ub'] != null ? fmtPct(c['7ub']) : '-') + '</td>';
                            html += '<td class="' + getUbColorClass(c['1ub']) + '">' + (c['1ub'] != null ? fmtPct(c['1ub']) : '-') + '</td>';
                            html += '<td>' + (c.l7cpc != null && !isNaN(c.l7cpc) ? fmt(c.l7cpc, 2) : '-') + '</td><td>' + (c.l1cpc != null && !isNaN(c.l1cpc) ? fmt(c.l1cpc, 2) : '-') + '</td>';
                            html += '<td>' + fmtBid(c.l_bid) + '</td><td>' + (c.sbid != null && c.sbid > 0 ? fmt(c.sbid, 2) : '-') + '</td>';
                            html += '</tr></tbody></table></div>';
                        });
                    } else {
                        html += '<h5 class="mb-3">KW Campaigns</h5><p class="text-muted">No KW campaigns found</p>';
                    }

                    function calcSbid(l7Views, esBid) {
                        const l7 = Number(l7Views || 0) || 0;
                        const es = parseFloat(esBid) || 0;
                        let v;
                        if (l7 >= 0 && l7 < 50) v = es;
                        else if (l7 >= 50 && l7 < 100) v = 9;
                        else if (l7 >= 100 && l7 < 150) v = 8;
                        else if (l7 >= 150 && l7 < 200) v = 7;
                        else if (l7 >= 200 && l7 < 250) v = 6;
                        else if (l7 >= 250 && l7 < 300) v = 5;
                        else if (l7 >= 300 && l7 < 350) v = 4;
                        else if (l7 >= 350 && l7 < 400) v = 3;
                        else if (l7 >= 400) v = 2;
                        else v = es;
                        return Math.min(v, 15);
                    }
                    // SCVR coloring – same rule as ebay/pmp/ads getCvrColor
                    function getScvrColor(scvr) {
                        if (scvr == null || scvr === '' || (typeof scvr === 'number' && isNaN(scvr))) return '#6c757d';
                        const percent = parseFloat(scvr);
                        if (percent <= 4) return 'red';
                        if (percent > 4 && percent <= 7) return 'yellow';
                        if (percent > 7 && percent <= 10) return 'green';
                        return '#E83E8C';
                    }
                    if (response.pt_campaigns && response.pt_campaigns.length > 0) {
                        response.pt_campaigns.forEach(function(c) {
                            const sBid = calcSbid(c.l7_views, c.es_bid);
                            const scvrVal = c.scvr != null ? parseFloat(c.scvr) : null;
                            const scvrHtml = scvrVal != null && !isNaN(scvrVal)
                                ? '<span style="color:' + getScvrColor(scvrVal) + '; font-weight: 600;">' + fmt(scvrVal, 1) + '%</span>'
                                : '-';
                            html += '<h5 class="mb-3">PMT Campaign - ' + (c.campaign_name || 'N/A') + '</h5>';
                            html += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm">';
                            html += '<thead><tr><th>CBID</th><th>ES BID</th><th>S BID</th><th>T VIEWS</th><th>L7 VIEWS</th><th>SCVR</th></tr></thead><tbody><tr>';
                            html += '<td>' + fmt(c.cbid, 2) + '</td><td>' + fmt(c.es_bid, 2) + '</td><td>' + fmt(sBid, 2) + '</td>';
                            html += '<td>' + fmt(c.t_views, 0) + '</td><td>' + fmt(c.l7_views, 0) + '</td><td>' + scvrHtml + '</td>';
                            html += '</tr></tbody></table></div>';
                        });
                    } else {
                        html += '<h5 class="mb-3">PMT Campaigns</h5><p class="text-muted">No PMT campaigns found</p>';
                    }

                    if (!(response.kw_campaigns && response.kw_campaigns.length) && !(response.pt_campaigns && response.pt_campaigns.length)) {
                        html = '<p class="text-muted">No campaigns found for this SKU</p>';
                    }
                    $('#campaignModalBody').html(html);
                },
                error: function(xhr) {
                    const err = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to load campaign data';
                    $('#campaignModalBody').html('<div class="alert alert-danger">' + err + '</div>');
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
    </script>
@endsection
