@extends('layouts.vertical', ['title' => 'Walmart Pricing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        /* Walmart-style color coding */
        .walmart-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .walmart-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .walmart-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .walmart-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .walmart-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .walmart-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
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

        /* Badge filter styling - Simple like eBay */
        .badge.fs-6.p-2 {
            cursor: pointer;
            user-select: none;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Walmart Pricing',
        'sub_title' => 'Walmart Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Walmart Pricing</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Inventory Filter -->
                    <div>
                        <select id="inventory-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Inventory</option>
                            <option value="gt0" selected>INV &gt; 0</option>
                            <option value="eq0">INV = 0</option>
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

                    <!-- BB Issue Filter -->
                    <div>
                        <select id="bb-issue-filter" class="form-select form-select-sm" style="width: 120px; color: red; font-weight: bold;">
                            <option value="all">All Prices</option>
                            <option value="bb-issue" style="color: red; font-weight: bold;">BB Issue</option>
                        </select>
                    </div>

                    <!-- RL/NRL Dropdown Filter -->
                    <div>
                        <select id="rl-nrl-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All RL/NRL</option>
                            <option value="RL" selected style="color: #28a745; font-weight: bold;">RL</option>
                            <option value="NRL" style="color: #dc3545; font-weight: bold;">NRL</option>
                        </select>
                    </div>

                    <!-- DIL Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
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

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export CSV
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadPriceModal">
                        <i class="fa fa-dollar-sign"></i> Upload Price
                    </button>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#uploadListingModal">
                        <i class="fa fa-eye"></i> Upload Listing
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadOrderModal">
                        <i class="fa fa-shopping-cart"></i> Upload Order
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Top Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge" style="color: black; font-weight: bold;">AVG PFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        
                        <!-- Walmart Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-products-badge" style="color: black; font-weight: bold;">Total Products: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: black; font-weight: bold;">Total Quantity: 0</span>
                        <!-- Clickable Filter Badges (Dark style like eBay) -->
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: 0 Sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-than-zero-sold-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter: >0 Sold items (INV>0)">&gt;0 Sold: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: Missing items (INV>0)">Missing: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="map-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: Mapped items (INV>0)">Map: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="nmap-count-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter: Not mapped items (INV>0)">Nmap: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="lt-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: W Price < Amazon (INV>0)">&lt; AMZ: 0</span>
                        <span class="badge fs-6 p-2" id="gt-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter: W Price > Amazon (INV>0)">&gt; AMZ: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="bb-issue-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter: BB Issue items (W<A)">BB Issue: 0</span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Total SPEND L30: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS AMT: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-orders-badge" style="color: black; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-walmart-l30-badge" style="color: black; font-weight: bold;">Total Walmart L30: $0</span>
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
                            <i class="fas fa-copy"></i> Sugg Amz Prc
                        </button>
                    </div>
                </div>
                <div id="walmart-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="walmart-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Price Modal -->
    <div class="modal fade" id="uploadPriceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa fa-dollar-sign me-2"></i>Upload Price Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadPriceForm" action="{{ route('walmart-sheet-upload-price') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="price_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadPriceForm" class="btn btn-success"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Listing Modal -->
    <div class="modal fade" id="uploadListingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fa fa-eye me-2"></i>Upload Listing Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadListingForm" action="{{ route('walmart-sheet-upload-listing-views') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="listing_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadListingForm" class="btn btn-info"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Order Modal -->
    <div class="modal fade" id="uploadOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-shopping-cart me-2"></i>Upload Order Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadOrderForm" action="{{ route('walmart-sheet-upload-order') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="order_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadOrderForm" class="btn btn-primary"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-chart-line me-2"></i>Metrics Chart for <span id="modalSkuName" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label fw-bold mb-0 me-2">Date Range:</label>
                            <select id="sku-chart-days-filter" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                <option value="7" selected>Last 7 Days</option>
                                <option value="14">Last 14 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                            </select>
                        </div>
                        <div class="text-muted">
                            <small><i class="fa fa-info-circle"></i> Hover over data points for detailed information</small>
                        </div>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>No Data Available:</strong> No historical data available for this SKU. Data will appear after running the metrics collection command.
                    </div>
                    <div style="height: 500px; position: relative;">
                        <canvas id="skuMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "walmart_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();
    
    // Badge filter states
    let zeroSoldFilterActive = false;
    let moreThanZeroSoldFilterActive = false;
    let missingFilterActive = false;
    let mapFilterActive = false;
    let nmapFilterActive = false;
    let gtAmzFilterActive = false;
    let ltAmzFilterActive = false;
    let bbIssueFilterActive = false;
    
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

    // SKU-specific chart
    let skuMetricsChart = null;
    let currentSku = null;

    function initSkuMetricsChart() {
        const ctx = document.getElementById('skuMetricsChart').getContext('2d');
        
        // Register datalabels plugin
        Chart.register(ChartDataLabels);
        
        skuMetricsChart = new Chart(ctx, {
            type: 'line',
            plugins: [ChartDataLabels],
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Price (USD)',
                        data: [],
                        borderColor: '#FF0000',
                        backgroundColor: 'rgba(255, 0, 0, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FF0000',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.1, // Less smooth for better variation visibility
                        fill: false,
                        spanGaps: true,
                        datalabels: {
                            display: true, // Show all labels
                            align: function(context) {
                                // Alternate positions to avoid overlap
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 'top' : 'bottom';
                            },
                            anchor: 'center',
                            offset: function(context) {
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 10 : 10;
                            },
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#FF0000',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? '$' + value.toFixed(2) : '';
                            }
                        }
                    },
                    {
                        label: 'Views',
                        data: [],
                        borderColor: '#0000FF',
                        backgroundColor: 'rgba(0, 0, 255, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0000FF',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y2', // Separate axis for Views
                        tension: 0.1,
                        fill: false,
                        spanGaps: true,
                        datalabels: {
                            display: true, // Show all labels
                            align: function(context) {
                                // Alternate opposite to Price - if Price is top, Views is left
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 'left' : 'right';
                            },
                            anchor: 'center',
                            offset: function(context) {
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 12 : 12;
                            },
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#0000FF',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? value.toLocaleString() : '';
                            }
                        }
                    },
                    {
                        label: 'CVR%',
                        data: [],
                        borderColor: '#008000',
                        backgroundColor: 'rgba(0, 128, 0, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#008000',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y1',
                        tension: 0.1,
                        fill: false,
                        spanGaps: true, // Connect points across gaps (missing data)
                        datalabels: {
                            display: true, // Show all labels
                            align: function(context) {
                                // Position based on index pattern - alternate top/bottom opposite to Price
                                const index = context.dataIndex;
                                const dataLength = context.dataset.data.length;
                                // Last point always goes right to avoid edge crowding
                                if (index === dataLength - 1) return 'left';
                                return index % 2 === 0 ? 'bottom' : 'top';
                            },
                            anchor: function(context) {
                                const index = context.dataIndex;
                                const dataLength = context.dataset.data.length;
                                if (index === dataLength - 1) return 'end';
                                return 'center';
                            },
                            offset: function(context) {
                                const index = context.dataIndex;
                                const dataLength = context.dataset.data.length;
                                if (index === dataLength - 1) return 15; // More offset for last point
                                return 10;
                            },
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#008000',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? value.toFixed(1) + '%' : '';
                            }
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 35,
                        right: 40, // Extra padding to prevent label crowding at right edge
                        bottom: 35,
                        left: 20
                    }
                },
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
                                size: 13,
                                weight: 'bold'
                            },
                            color: '#333'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Walmart SKU Metrics',
                        font: {
                            size: 18,
                            weight: 'bold'
                        },
                        color: '#1a1a1a',
                        padding: {
                            top: 10,
                            bottom: 5
                        }
                    },
                    subtitle: {
                        display: true,
                        text: 'Price (Left Axis) | Views (Blue Line) | CVR% (Right Axis)',
                        font: {
                            size: 12,
                            style: 'italic'
                        },
                        color: '#666',
                        padding: {
                            bottom: 15
                        }
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#ddd',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.parsed.y || 0;
                                
                                if (label.includes('Price')) {
                                    return label + ': $' + value.toFixed(2);
                                } else if (label.includes('Views')) {
                                    return label + ': ' + value.toLocaleString();
                                } else if (label.includes('CVR')) {
                                    return label + ': ' + value.toFixed(1) + '%';
                                } else if (label.includes('%')) {
                                    return label + ': ' + value.toFixed(2) + '%';
                                }
                                return label + ': ' + value;
                            }
                        }
                    },
                    datalabels: {
                        display: true,
                        clamp: true, // Keep all labels inside chart boundaries
                        clip: false // But don't clip them (hide), just reposition
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            color: '#2c3e50'
                        },
                        ticks: {
                            font: {
                                size: 11,
                                weight: '600'
                            },
                            color: '#34495e'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            lineWidth: 1
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Price (USD)',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            color: '#FF0000'
                        },
                        beginAtZero: false,
                        // Dynamic min/max set in loadSkuMetricsData for tight scale
                        ticks: {
                            font: {
                                size: 11,
                                weight: '600'
                            },
                            color: '#FF0000',
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        },
                        grid: {
                            color: 'rgba(255, 0, 0, 0.1)',
                            lineWidth: 1
                        }
                    },
                    y2: {
                        type: 'linear',
                        display: false, // Hide axis but use for Views scaling
                        position: 'left',
                        beginAtZero: false,
                        // Dynamic min/max set in loadSkuMetricsData for tight scale
                        grid: {
                            drawOnChartArea: false,
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'CVR Percent (%)',
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            color: '#006400'
                        },
                        beginAtZero: false, // Don't start at zero to show variations better
                        // Dynamic min/max set in loadSkuMetricsData for tight scale
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            font: {
                                size: 11,
                                weight: '600'
                            },
                            color: '#008000',
                            callback: function(value) {
                                return value.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    function loadSkuMetricsData(sku, days = 7) {
        fetch(`/walmart-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (skuMetricsChart) {
                    if (!data || data.length === 0) {
                        console.warn('No data returned for SKU:', sku);
                        $('#chart-no-data-message').show();
                        skuMetricsChart.data.labels = [];
                        skuMetricsChart.data.datasets.forEach(dataset => {
                            dataset.data = [];
                        });
                        skuMetricsChart.options.plugins.title.text = 'Walmart SKU Metrics';
                        skuMetricsChart.update();
                        return;
                    }
                    
                    $('#chart-no-data-message').hide();
                    
                    skuMetricsChart.options.plugins.title.text = `Walmart Metrics (${days} Days)`;
                    
                    skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                    
                    const priceData = data.map(d => d.price || null);
                    const viewsData = data.map(d => d.views || null);
                    // Convert 0 CVR to null to avoid dramatic drops in the chart
                    const cvrData = data.map(d => {
                        const cvr = d.cvr_percent;
                        return (cvr && cvr > 0) ? cvr : null;
                    });
                    
                    skuMetricsChart.data.datasets[0].data = priceData;
                    skuMetricsChart.data.datasets[1].data = viewsData;
                    skuMetricsChart.data.datasets[2].data = cvrData;
                    
                    // Dynamically calculate min/max for each axis separately (shows small changes clearly)
                    // Filter out null/0 values for proper scaling
                    const priceMin = Math.min(...priceData.filter(p => p != null && p > 0));
                    const priceMax = Math.max(...priceData.filter(p => p != null));
                    const viewsMin = Math.min(...viewsData.filter(v => v != null && v > 0));
                    const viewsMax = Math.max(...viewsData.filter(v => v != null));
                    const cvrMin = Math.min(...cvrData.filter(c => c != null && c > 0));
                    const cvrMax = Math.max(...cvrData.filter(c => c != null && c > 0));
                    
                    // Set tight ranges with minimal padding to show even 1-2 unit changes clearly
                    // For Y axis (Price only) - 3% padding
                    const yMin = priceMin * 0.97; // 3% below min
                    const yMax = priceMax * 1.03; // 3% above max
                    
                    // For Y2 axis (Views only) - 3% padding
                    const y2Min = viewsMin * 0.97; // 3% below min
                    const y2Max = viewsMax * 1.03; // 3% above max
                    
                    // For Y1 axis (CVR%) - 5% padding
                    const y1Min = cvrMin > 0 ? cvrMin * 0.95 : 0; // 5% below min
                    const y1Max = cvrMax * 1.05; // 5% above max
                    
                    // Update scale options dynamically for each axis
                    skuMetricsChart.options.scales.y.min = yMin;
                    skuMetricsChart.options.scales.y.max = yMax;
                    skuMetricsChart.options.scales.y2.min = y2Min;
                    skuMetricsChart.options.scales.y2.max = y2Max;
                    skuMetricsChart.options.scales.y1.min = y1Min;
                    skuMetricsChart.options.scales.y1.max = y1Max;
                    
                    skuMetricsChart.update('active');
                }
            })
            .catch(error => {
                console.error('Error loading SKU metrics data:', error);
                alert('Error loading metrics data. Please check console for details.');
            });
    }

    $(document).ready(function() {
        // Initialize SKU-specific chart
        initSkuMetricsChart();

        // SKU chart days filter
        $('#sku-chart-days-filter').on('change', function() {
            const days = $(this).val();
            if (currentSku) {
                if (skuMetricsChart) {
                    skuMetricsChart.options.plugins.title.text = `Walmart Metrics (${days} Days)`;
                    skuMetricsChart.update();
                }
                loadSkuMetricsData(currentSku, days);
            }
        });

        // Event delegation for eye button clicks
        $(document).on('click', '.view-sku-chart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            currentSku = sku;
            $('#modalSkuName').text(sku);
            $('#sku-chart-days-filter').val('7');
            $('#chart-no-data-message').hide();
            loadSkuMetricsData(sku, 7);
            $('#skuMetricsModal').modal('show');
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

        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
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
            decreaseModeActive = false;
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

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
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

        // Custom price rounding function to round to .99 endings
        function roundToRetailPrice(price) {
            // Round to the nearest dollar and subtract 0.01 to make it .99
            const roundedDollar = Math.ceil(price);
            return roundedDollar - 0.01;
        }

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

            let updatedCount = 0;
            
            // Loop through selected SKUs using the same approach as applySuggestAmazonPrice
            selectedSkus.forEach(sku => {
                // Find the row using Tabulator's searchRows method
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0]; // Get the first matching row
                    const rowData = row.getData();
                    // Use only W Price for increase/decrease mode (not Amazon price)
                    const currentPrice = parseFloat(rowData['w_price']) || 0;
                    
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
                        
                        // Apply retail price rounding (round to .99 endings)
                        newSPrice = roundToRetailPrice(newSPrice);
                        
                        // Ensure minimum price
                        newSPrice = Math.max(0.99, newSPrice);
                        
                        // Update only sprice (don't change w_price)
                        row.update({
                            sprice: newSPrice
                        });
                        
                        updatedCount++;
                    }
                }
            });
            
            showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s) based on W Price`, 'success');
            $('#discount-percentage-input').val('');
        }

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
                // Find the row using Tabulator's searchRows method
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0]; // Get the first matching row
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['a_price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        // Update the row using the row object's update method
                        row.update({
                            sprice: amazonPrice,
                            w_price: amazonPrice
                        });
                        
                        // Store update for backend saving
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
            
            // Save to backend if there are updates
            if (updates.length > 0) {
                saveAmazonPriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
        }

        function saveAmazonPriceUpdates(updates) {
            $.ajax({
                url: '/walmart-sheet-save-amazon-prices',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        showToast(`Price updates saved (expires in 12 hours): ${response.updated} record(s)`, 'success');
                        if (response.errors && response.errors.length > 0) {
                            console.warn('Some updates had errors:', response.errors);
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error saving price updates:', xhr);
                    let errorMessage = 'Error saving price updates';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ': ' + xhr.responseJSON.error;
                    }
                    showToast(errorMessage, 'error');
                }
            });
        }

        function updateSummary() {
            if (!table) {
                return;
            }
            const data = table.getData("all");
            
            let totalProducts = data.length;
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalSpendL30 = 0;
            let totalTcos = 0;
            let totalGpftAmt = 0; // Gross Profit Total (before ads)
            let totalPftAmt = 0; // Net Profit Total (after ads)
            let totalSalesAmt = 0;
            let totalCogsAmt = 0;
            let totalWalmartL30 = 0;
            let zeroSoldCount = 0;
            let totalWeightedPrice = 0;
            let totalQty = 0;
            let totalViews = 0;
            let totalDilPercent = 0;
            let dilCount = 0;
            let bbIssueCount = 0; // Count of items where W Price < A Price
            let missingCount = 0; // Count of items missing in Walmart
            let mapCount = 0; // Count of items with inventory mapped
            let nmapCount = 0; // Count of items with inventory not mapped
            let moreThanZeroSoldCount = 0; // Count of items with sales > 0
            let gtAmzCount = 0; // Count of items where W Price > Amazon Price
            let ltAmzCount = 0; // Count of items where W Price < Amazon Price
            
            data.forEach(row => {
                const qty = parseInt(row['total_qty']) || 0;
                const price = parseFloat(row['price']) || 0;
                const lp = parseFloat(row['lp']) || 0;
                const ship = parseFloat(row['ship']) || 0;
                const adSpend = parseFloat(row['spend']) || 0;
                const adsPercent = parseFloat(row['ads_percent']) || 0;
                
                totalQuantity += qty;
                totalOrders += parseInt(row['total_orders']) || 0;
                
                // Weighted price calculation
                totalWeightedPrice += price * qty;
                totalQty += qty;
                
                // Sales amount = Use actual total_revenue from orders (actual item_cost sum)
                // This is the real sales data from Walmart orders, not calculated
                const salesAmt = parseFloat(row['total_revenue']) || 0;
                totalSalesAmt += salesAmt;
                
                // GPFT Amount from data (pre-calculated gross profit)
                totalGpftAmt += parseFloat(row['gpft_amount']) || 0;
                
                // PFT Amount from data (pre-calculated net profit after ads)
                totalPftAmt += parseFloat(row['pft_amount']) || 0;
                
                // COGS = LP × Qty (not including ship for ROI calc)
                const cogs = lp * qty;
                totalCogsAmt += cogs;
                
                // Views
                totalViews += parseInt(row['page_views']) || 0;
                
                // Dil calculation
                const INV = parseFloat(row['INV']) || 0;
                const L30 = parseFloat(row['L30']) || 0;
                if (INV > 0) {
                    const dil = (L30 / INV) * 100;
                    totalDilPercent += dil;
                    dilCount++;
                }
                
                // Walmart L30 (total sales value from actual orders)
                totalWalmartL30 += salesAmt;
                
                // Count if W Price < A Price (BB Issue) and price comparisons
                const wPrice = parseFloat(row['w_price']) || 0;
                const aPrice = parseFloat(row['a_price']) || 0;
                
                if (wPrice > 0 && aPrice > 0) {
                    // BB Issue: W Price < A Price
                    if (wPrice < aPrice) {
                        bbIssueCount++;
                    }
                    
                    // Price comparison with Amazon
                    if (wPrice > aPrice) {
                        gtAmzCount++;
                    } else if (wPrice < aPrice) {
                        ltAmzCount++;
                    }
                }
                
                // Count missing items
                if (row['missing'] === 'M') {
                    missingCount++;
                }
                
                // Count Map/Nmap items
                if (row['map_status'] === 'Map') {
                    mapCount++;
                } else if (row['map_status'] === 'Nmap') {
                    nmapCount++;
                }
                
                // Count SKUs with 0 sold and more than 0 sold
                if (qty === 0) {
                    zeroSoldCount++;
                } else if (qty > 0) {
                    moreThanZeroSoldCount++;
                }
                
            });
            
            // ===== MATCH AMAZON SUMMARY CALCULATIONS =====
            
            // Calculate averages
            const avgPrice = totalQty > 0 ? totalWeightedPrice / totalQty : 0;
            
            // AVG GPFT% = (Total GPFT Amount / Total Sales) × 100
            // This is Gross Profit % BEFORE ads
            const avgGpft = totalSalesAmt > 0 ? ((totalGpftAmt / totalSalesAmt) * 100) : 0;
            
            // TACOS% = (Total Ad Spend / Total Sales) × 100
            const tacosPercent = totalSalesAmt > 0 ? ((totalSpendL30 / totalSalesAmt) * 100) : 0;
            
            // AVG PFT% = GPFT% - TACOS%
            // This is Net Profit % AFTER ads
            const avgPft = avgGpft - tacosPercent;
            
            // ROI% = (Total PFT Amount / Total COGS) × 100
            // This is Net ROI AFTER ads
            const roiPercent = totalCogsAmt > 0 ? ((totalPftAmt / totalCogsAmt) * 100) : 0;
            
            // GROI% = (Total GPFT Amount / Total COGS) × 100
            // This is Gross ROI BEFORE ads
            const groiPercent = totalCogsAmt > 0 ? ((totalGpftAmt / totalCogsAmt) * 100) : 0;
            
            // CVR = (Total Qty / Total Views) × 100
            const avgCvr = totalViews > 0 ? (totalQty / totalViews * 100) : 0;
            
            // AVG DIL %
            const avgDilPercent = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
            
            // Update badges (same order as Amazon)
            $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt).toLocaleString());
            $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt).toLocaleString());
            $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft.toFixed(1) + '%');
            $('#avg-pft-badge').text('AVG PFT: ' + avgPft.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('Avg CVR: ' + avgCvr.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#cvr-badge').text('CVR: ' + avgCvr.toFixed(2) + '%');
            
            // Walmart specific badges
            $('#total-products-badge').text('Total Products: ' + totalProducts.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            
            // 0 Sold badge (Red when count > 0)
            const zeroSoldBadge = $('#zero-sold-count-badge');
            zeroSoldBadge.text('0 Sold: ' + zeroSoldCount.toLocaleString());
            if (zeroSoldCount === 0) {
                zeroSoldBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                zeroSoldBadge.removeClass('bg-success').addClass('bg-danger');
            }
            
            // More than 0 Sold badge (always green)
            $('#more-than-zero-sold-badge').text('>0 Sold: ' + moreThanZeroSoldCount.toLocaleString());
            
            // Update Missing badge with green color when count is 0
            const missingBadge = $('#missing-count-badge');
            missingBadge.text('Missing: ' + missingCount.toLocaleString());
            if (missingCount === 0) {
                missingBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                missingBadge.removeClass('bg-success').addClass('bg-danger');
            }
            
            // Update Map badge
            const mapBadge = $('#map-count-badge');
            mapBadge.text('Map: ' + mapCount.toLocaleString());
            
            // Update Nmap badge with green color when count is 0
            const nmapBadge = $('#nmap-count-badge');
            nmapBadge.text('Nmap: ' + nmapCount.toLocaleString());
            if (nmapCount === 0) {
                nmapBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                nmapBadge.removeClass('bg-success').addClass('bg-danger');
            }
            
            // Update Amazon price comparison badges
            const gtAmzBadge = $('#gt-amz-badge');
            gtAmzBadge.text('> AMZ: ' + gtAmzCount.toLocaleString());
            if (gtAmzCount === 0) {
                gtAmzBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                gtAmzBadge.removeClass('bg-success').addClass('bg-danger');
            }
            
            const ltAmzBadge = $('#lt-amz-badge');
            ltAmzBadge.text('< AMZ: ' + ltAmzCount.toLocaleString());
            
            // Update BB Issue badge with green color when count is 0
            const bbIssueBadge = $('#bb-issue-count-badge');
            bbIssueBadge.text('BB Issue: ' + bbIssueCount.toLocaleString());
            if (bbIssueCount === 0) {
                bbIssueBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                bbIssueBadge.removeClass('bg-success').addClass('bg-danger');
            }
            
            // Financial metrics
            $('#total-tcos-badge').text('Total TCOS: Calculating...');
            $('#total-spend-badge').text('Total SPEND L30: Loading...');
            
            // Fetch campaign spend to calculate TACOS
            fetch('/walmart/running/ads/data')
                .then(response => response.json())
                .then(responseData => {
                    const campaignSpendTotal = responseData.data.reduce((sum, row) => sum + (parseFloat(row.SPEND_L30) || 0), 0);
                    $('#total-spend-badge').text('Total SPEND L30: $' + Math.round(campaignSpendTotal).toLocaleString());
                    
                    // Update TACOS% with actual order sales and campaign spend
                    // TACOS = (Campaign Spend / Actual Order Sales) * 100
                    const actualTacosPercent = totalSalesAmt > 0 ? ((campaignSpendTotal / totalSalesAmt) * 100) : 0;
                    $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos));
                    
                    // Recalculate AVG PFT% with actual TACOS (matching Amazon logic)
                    const actualAvgPft = avgGpft - actualTacosPercent;
                    $('#avg-pft-badge').text('AVG PFT: ' + actualAvgPft.toFixed(1) + '%');
                })
                .catch(error => {
                    console.error('Error fetching campaign spend data:', error);
                    $('#total-spend-badge').text('Total SPEND L30: Error');
                    $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos));
                });
            
            $('#total-cogs-badge').text('COGS AMT: $' + Math.round(totalCogsAmt).toLocaleString());
            $('#roi-percent-badge').text('ROI %: ' + roiPercent.toFixed(1) + '%');
            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-walmart-l30-badge').text('Total Walmart L30: $' + Math.round(totalWalmartL30).toLocaleString());
            $('#avg-dil-percent-badge').text('DIL %: ' + Math.round(avgDilPercent) + '%');
        }

        // Color functions (same as Temu)
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

        table = new Tabulator("#walmart-table", {
            ajaxURL: "/walmart-sheet-upload-data-json",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_percent", dir: "asc"} // Sort by CVR lowest to highest on page load
            ],
            ajaxError: function(xhr, textStatus, errorThrown) {
                console.error('Table ajax error:', xhr.status, textStatus, errorThrown);
                console.error('Response:', xhr.responseText);
            },
            columns: [
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        
                        return `${sku} <button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    sorter: "number"
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    sorter: "number"
                },
                {
                    title: "W INV",
                    field: "inventory_walmart",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'Not Listed' || value === null || value === undefined || value === '') {
                            return '<span style="color: #dc3545; font-weight: bold;" title="Not Listed on Walmart">0</span>';
                        }
                        const numValue = parseInt(value);
                        if (numValue === 0) {
                            return '<span style="color: #ffc107; font-weight: bold;">0</span>';
                        }
                        return '<span style="color: #28a745; font-weight: bold;">' + numValue + '</span>';
                    },
                    sorter: function(a, b) {
                        // Custom sorter to handle 'Not Listed' and numeric values
                        if (a === 'Not Listed') a = 0;
                        if (b === 'Not Listed') b = 0;
                        return parseInt(a || 0) - parseInt(b || 0);
                    }
                },
                {
                    title: "Dil",
                    field: "dil_calculated", // Calculate DIL from OV L30/INV
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const calcDIL = (row) => {
                            const inv = parseFloat(row['INV']) || 0;
                            const ovl30 = parseFloat(row['L30']) || 0;
                            return inv === 0 ? 0 : (ovl30 / inv) * 100;
                        };
                        return calcDIL(aRow.getData()) - calcDIL(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const inv = parseFloat(rowData['INV']) || 0;
                        const ovl30 = parseFloat(rowData['L30']) || 0;
                        
                        // DIL Formula: (OV L30 ÷ INV) × 100
                        // Shows sales velocity ratio compared to current inventory
                        const dil = inv === 0 ? 0 : (ovl30 / inv) * 100;
                        let color = '';
                        
                        // Red: <16.7% (Critical - low velocity relative to inventory)
                        // Yellow: 16.7-25% (Warning - moderate velocity) 
                        // Green: 25-50% (Good - healthy velocity)
                        // Pink: 50%+ (Excellent - high velocity)
                        
                        if (dil < 16.66) color = '#a00211'; // red
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                        else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                        else color = '#e83e8c'; // pink (50 and above)

                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "W L30",
                    field: "total_qty",
                    hozAlign: "center",
                    sorter: "number",
                    sorterParams: {dir: "asc"}
                },
                {
                    title: "M",
                    field: "missing",
                    hozAlign: "center",
                    width: 50,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold; font-size: 14px;" title="Missing in Walmart - Product exists in Product Master but not listed on Walmart">M</span>';
                        }
                        return '';
                    },
                    sorter: function(a, b) {
                        // M values come first
                        if (a === 'M' && b !== 'M') return -1;
                        if (a !== 'M' && b === 'M') return 1;
                        return 0;
                    }
                },
                {
                    title: "Map",
                    field: "map_status",
                    hozAlign: "center",
                    width: 80,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        
                        if (value === 'Map') {
                            return '<span style="color: #28a745; font-weight: bold; background-color: #d4edda; padding: 4px 8px; border-radius: 4px;" title="Inventory is mapped correctly">Map</span>';
                        } else if (value === 'Nmap') {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #f8d7da; padding: 4px 8px; border-radius: 4px;" title="Inventory mismatch - needs update">Nmap</span>';
                        }
                        return '';
                    },
                    sorter: function(a, b) {
                        // Map comes first, then Nmap, then empty
                        if (a === 'Map' && b !== 'Map') return -1;
                        if (a !== 'Map' && b === 'Map') return 1;
                        if (a === 'Nmap' && b !== 'Nmap') return -1;
                        if (a !== 'Nmap' && b === 'Nmap') return 1;
                        return 0;
                    }
                },
                {
                    title: "RL/NRL",
                    field: "rl_nrl",
                    hozAlign: "center",
                    width: 100,
                    headerSort: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue() || '';
                        const sku = rowData.sku || '';
                        
                        if (!sku) return '';
                        
                        let bgColor = '#6c757d'; // Default gray
                        let textColor = 'white';
                        
                        if (value === 'RL') {
                            bgColor = '#28a745'; // Green
                        } else if (value === 'NRL') {
                            bgColor = '#dc3545'; // Red
                        }
                        
                        return `<select class="rl-nrl-dropdown form-control form-control-sm" data-sku="${sku}" style="width: 90%; padding: 4px 8px; border-radius: 4px; font-weight: bold; text-align: center; color: ${textColor}; background-color: ${bgColor}; border: none; cursor: pointer;">
                            <option value="">Select</option>
                            <option value="RL" ${value === 'RL' ? 'selected' : ''} style="background-color: #28a745; color: white;">RL</option>
                            <option value="NRL" ${value === 'NRL' ? 'selected' : ''} style="background-color: #dc3545; color: white;">NRL</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        // Prevent default cell click behavior
                        e.stopPropagation();
                    }
                },
                {
                    title: "CVR %",
                    field: "cvr_percent", // Pre-calculated in controller as (W L30 / Views) * 100
                    hozAlign: "center",
                    sorter: "number", // Use built-in number sorter
                    headerSortStartingDir: "asc", // First click sorts lowest to highest
                    accessor: function(value, data, type, params, column) {
                        // Ensure value is always numeric for sorting
                        // Remove any % symbols if present and parse as float
                        if (typeof value === 'string') {
                            return parseFloat(value.replace('%', '')) || 0;
                        }
                        return parseFloat(value) || 0;
                    },
                    accessorDownload: function(value, data, type, params, column) {
                        // For downloads, return numeric value
                        return parseFloat(value) || 0;
                    },
                    formatter: function(cell) {
                        // Get the numeric value (already cleaned by accessor)
                        const cvr = parseFloat(cell.getValue()) || 0;
                        
                        let color = '#000';
                        
                        if (cvr === 0) color = '#6c757d'; // gray for 0
                        else if (cvr <= 4) color = '#a00211'; // red
                        else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                        else if (cvr > 7 && cvr <= 10) color = '#28a745'; // green
                        else color = '#ff1493'; // pink (>10)
                        
                        return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                    },
                    sorterParams: {
                        alignEmptyValues: "bottom", // Put empty/0 values at bottom when sorting desc
                    }
                },
                {
                    title: "Views",
                    field: "page_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
                 {
                    title: "A Price",
                    field: "a_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    title: "W Price",
                    field: "w_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const wPrice = parseFloat(cell.getValue()) || 0;
                        const aPrice = parseFloat(rowData['a_price']) || 0;
                        
                        // If W Price < A Price, show in red (BB Issue)
                        const isRedFlag = wPrice > 0 && aPrice > 0 && wPrice < aPrice;
                        const color = isRedFlag ? '#a00211' : '#000';
                        const fontWeight = isRedFlag ? 'bold' : 'normal';
                        
                        return `<span style="color: ${color}; font-weight: ${fontWeight};">$${wPrice.toFixed(2)}</span>`;
                    }
                },
               
                {
                    title: "GPRFT %",
                    field: "gpft", // Show GPFT% (Gross Profit % - BEFORE ads)
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "GROI %",
                    field: "groi", // Show GROI% (Gross ROI % - BEFORE ads)
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#000';
                        
                        if (value == 0 || value == 100) color = '#a00211';
                        else if (value > 0 && value <= 7) color = '#ff1493';
                        else if (value > 7 && value <= 14) color = '#28a745';
                        else if (value > 14 && value <= 21) color = '#ffc107';
                        else if (value > 21) color = '#a00211';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "NPFT %",
                    field: "pft", // Show PFT% (Net Profit % - AFTER ads)
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "NROI %",
                    field: "roi", // Show ROI% (Net ROI % - AFTER ads)
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
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
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const price = parseFloat(rowData['price']) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value || sprice === 0) return '';
                        
                        if (sprice === price) {
                            return '<span style="color: #999; font-style: italic;">-</span>';
                        }
                        
                        return `$${parseFloat(value).toFixed(2)}`;
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
                        const ship = parseFloat(rowData['ship']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0) return '';
                        
                        // SGPRFT% = ((SPRICE × 0.80 - LP - Ship) / SPRICE) × 100
                        const sgprft = sprice > 0 ? ((sprice * percentage - lp - ship) / sprice) * 100 : 0;
                        
                        const colorClass = getPftColor(sgprft);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    }
                },
                {
                    title: "SROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // SROI% = ((SPRICE × 0.80 - LP - Ship) / LP) × 100
                        const sroi = lp > 0 ? ((sprice * percentage - lp - ship) / lp) * 100 : 0;
                        
                        const colorClass = getRoiColor(sroi);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(sroi)}%</span>`;
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
                        const ship = parseFloat(rowData['ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0) return '';
                        
                        // SGPRFT%
                        const sgprft = sprice > 0 ? ((sprice * percentage - lp - ship) / sprice) * 100 : 0;
                        
                        // SPFT% = SGPRFT% - ADS%
                        const spft = sgprft - adsPercent;
                        
                        const colorClass = getPftColor(spft);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    }
                },
                {
                    title: "SNROI%",
                    field: "snroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // SROI%
                        const sroi = lp > 0 ? ((sprice * percentage - lp - ship) / lp) * 100 : 0;
                        
                        // SNROI% = SROI% - ADS%
                        const snroi = sroi - adsPercent;
                        
                        const colorClass = getRoiColor(snroi);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(snroi)}%</span>`;
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "center",
                    sorter: "number",
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
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 4px; border-radius: 3px;" title="Missing LP from Product Master">⚠️ $0.00</span>';
                        }
                        return '<span style="color: #28a745; font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    },
                    visible: true
                },
                {
                    title: "Ship",
                    field: "ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 4px; border-radius: 3px;" title="Missing Ship from Product Master">⚠️ $0.00</span>';
                        }
                        return '<span style="color: #28a745; font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    },
                    visible: true
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            updateSummary();
        });

        // Apply filters - ALL filters work together (additive)
        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const bbIssueFilter = $('#bb-issue-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            const rlNrlFilter = $('#rl-nrl-filter').val();
            const skuSearch = $('#sku-search').val();

            // Clear all filters first
            table.clearFilter();

            // SKU Search (always first)
            if (skuSearch) {
                table.setFilter("sku", "like", skuSearch);
            }

            // === DROPDOWN FILTERS ===
            
            // Inventory Filter
            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.INV) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    if (inventoryFilter === 'eq0') return inv === 0;
                    return true;
                });
            }

            // GPFT Filter
            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    const gpft = parseFloat(data.gpft) || 0;
                    
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

            // CVR Filter
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const wl30 = parseInt(data['total_qty']) || 0;
                    const views = parseInt(data['page_views']) || 0;
                    const cvrPercent = views > 0 ? (wl30 / views) * 100 : 0;
                    
                    if (cvrFilter === '0-0') return cvrPercent === 0;
                    if (cvrFilter === '0.01-1') return cvrPercent >= 0.01 && cvrPercent <= 1;
                    if (cvrFilter === '1-2') return cvrPercent > 1 && cvrPercent <= 2;
                    if (cvrFilter === '2-3') return cvrPercent > 2 && cvrPercent <= 3;
                    if (cvrFilter === '3-4') return cvrPercent > 3 && cvrPercent <= 4;
                    if (cvrFilter === '0-4') return cvrPercent >= 0 && cvrPercent <= 4;
                    if (cvrFilter === '4-7') return cvrPercent > 4 && cvrPercent <= 7;
                    if (cvrFilter === '7-10') return cvrPercent > 7 && cvrPercent <= 10;
                    if (cvrFilter === '10plus') return cvrPercent > 10;
                    return true;
                });
            }

            // BB Issue Dropdown Filter
            if (bbIssueFilter !== 'all') {
                table.addFilter(function(data) {
                    const wPrice = parseFloat(data.w_price) || 0;
                    const aPrice = parseFloat(data.a_price) || 0;
                    
                    if (bbIssueFilter === 'bb-issue') {
                        return wPrice > 0 && aPrice > 0 && wPrice < aPrice;
                    }
                    return true;
                });
            }

            // RL/NRL Dropdown Filter
            if (rlNrlFilter !== 'all') {
                table.addFilter(function(data) {
                    return data.rl_nrl === rlNrlFilter;
                });
            }

            // DIL Filter
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['INV']) || 0;
                    const ovl30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (ovl30 / inv) * 100;
                    
                    // Amazon-style DIL color ranges
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // === BADGE FILTERS (CLICKABLE) - All work together ===
            
            // 0 Sold Filter (mutually exclusive with >0 Sold)
            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    const qty = parseInt(data['total_qty']) || 0;
                    return qty === 0;
                });
            }
            
            // >0 Sold Filter (mutually exclusive with 0 Sold)
            if (moreThanZeroSoldFilterActive) {
                table.addFilter(function(data) {
                    const qty = parseInt(data['total_qty']) || 0;
                    return qty > 0;
                });
            }
            
            // Missing Filter (works with all other filters)
            if (missingFilterActive) {
                table.addFilter(function(data) {
                    return data.missing === 'M';
                });
            }
            
            // Map Filter (mutually exclusive with Nmap, works with Missing)
            if (mapFilterActive) {
                table.addFilter(function(data) {
                    return data.map_status === 'Map';
                });
            }
            
            // Nmap Filter (mutually exclusive with Map, works with Missing)
            if (nmapFilterActive) {
                table.addFilter(function(data) {
                    return data.map_status === 'Nmap';
                });
            }
            
            // > AMZ Filter (mutually exclusive with < AMZ)
            if (gtAmzFilterActive) {
                table.addFilter(function(data) {
                    const wPriceVal = parseFloat(data['w_price']) || 0;
                    const aPriceVal = parseFloat(data['a_price']) || 0;
                    return wPriceVal > 0 && aPriceVal > 0 && wPriceVal > aPriceVal;
                });
            }
            
            // < AMZ Filter (mutually exclusive with > AMZ)
            if (ltAmzFilterActive) {
                table.addFilter(function(data) {
                    const wPriceVal = parseFloat(data['w_price']) || 0;
                    const aPriceVal = parseFloat(data['a_price']) || 0;
                    return wPriceVal > 0 && aPriceVal > 0 && wPriceVal < aPriceVal;
                });
            }
            
            // BB Issue Badge Filter (works with all other filters)
            if (bbIssueFilterActive) {
                table.addFilter(function(data) {
                    const wPriceVal = parseFloat(data.w_price) || 0;
                    const aPriceVal = parseFloat(data.a_price) || 0;
                    return wPriceVal > 0 && aPriceVal > 0 && wPriceVal < aPriceVal;
                });
            }

            // Update UI
            updateSummary();
            updateSelectAllCheckbox();
        }

        $('#inventory-filter, #gpft-filter, #cvr-filter, #bb-issue-filter, #rl-nrl-filter').on('change', function() {
            applyFilters();
        });
        
        // Badge filter click handlers - EXACT eBay pattern
        
        // 0 Sold vs >0 Sold (mutually exclusive)
        $('#zero-sold-count-badge').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreThanZeroSoldFilterActive = false;
            applyFilters();
        });
        
        $('#more-than-zero-sold-badge').on('click', function() {
            moreThanZeroSoldFilterActive = !moreThanZeroSoldFilterActive;
            zeroSoldFilterActive = false;
            applyFilters();
        });
        
        // Missing/Map/Nmap (mutually exclusive with each other, like eBay)
        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = false;
            nmapFilterActive = false;
            applyFilters();
        });
        
        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = false;
            nmapFilterActive = false;
            applyFilters();
        });
        
        $('#nmap-count-badge').on('click', function() {
            nmapFilterActive = !nmapFilterActive;
            mapFilterActive = false;
            missingFilterActive = false;
            applyFilters();
        });
        
        // < AMZ vs > AMZ (mutually exclusive)
        $('#lt-amz-badge').on('click', function() {
            ltAmzFilterActive = !ltAmzFilterActive;
            gtAmzFilterActive = false;
            applyFilters();
        });
        
        $('#gt-amz-badge').on('click', function() {
            gtAmzFilterActive = !gtAmzFilterActive;
            ltAmzFilterActive = false;
            applyFilters();
        });
        
        // BB Issue (works with all)
        $('#bb-issue-count-badge').on('click', function() {
            bbIssueFilterActive = !bbIssueFilterActive;
            applyFilters();
        });

        // Initialize dropdown functionality (Amazon-style)
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
            button.html('').append(statusCircle).append(' ' + column.toUpperCase());
            
            // Close dropdown
            container.removeClass('show');
            
            // Apply filters
            applyFilters();
        });

        // Close dropdowns when clicking outside
        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        // RL/NRL dropdown change handler
        $(document).on('change', '.rl-nrl-dropdown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const sku = $(this).data('sku');
            const value = $(this).val();
            const $dropdown = $(this);
            
            console.log('RL/NRL Dropdown Changed:', { sku, value });
            
            if (!sku) {
                showToast('SKU not found', 'error');
                console.error('No SKU found for dropdown');
                return;
            }
            
            if (!value) {
                showToast('Please select RL or NRL', 'error');
                return;
            }
            
            // Update dropdown color immediately
            if (value === 'RL') {
                $dropdown.css('background-color', '#28a745').css('color', 'white');
            } else if (value === 'NRL') {
                $dropdown.css('background-color', '#dc3545').css('color', 'white');
            } else {
                $dropdown.css('background-color', '#6c757d').css('color', 'white');
            }
            
            console.log('Sending request to save RL/NRL...');
            
            // Save to database
            fetch('/walmart-sheet-update-cell', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    sku: sku,
                    field: 'rl_nrl',
                    value: value
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                console.log('Save result:', result);
                if (result.success) {
                    showToast('RL/NRL updated successfully', 'success');
                    
                    // Update the table data
                    const rows = table.searchRows("sku", "=", sku);
                    if (rows.length > 0) {
                        rows[0].update({ rl_nrl: value });
                    }
                } else {
                    showToast('Error updating RL/NRL: ' + (result.error || 'Unknown error'), 'error');
                    console.error('Save failed:', result);
                }
            })
            .catch(error => {
                console.error('Error updating RL/NRL:', error);
                showToast('Error updating RL/NRL: ' + error.message, 'error');
            });
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            
            if (field === 'price' || field === 'sprice') {
                const newValue = parseFloat(cell.getValue());
                if (newValue < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                // Save sprice to database
                if (field === 'sprice') {
                    const sku = data.sku;
                    
                    fetch('/walmart-sheet-update-cell', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: newValue
                        })
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            row.update({ [field]: newValue });
                            row.reformat();
                            showToast('S PRC saved successfully', 'success');
                        } else {
                            showToast('Error saving S PRC: ' + (result.error || 'Unknown error'), 'error');
                            cell.restoreOldValue();
                        }
                    })
                    .catch(error => {
                        console.error('Error saving S PRC:', error);
                        showToast('Error saving S PRC', 'error');
                        cell.restoreOldValue();
                    });
                } else {
                    // For other price fields, just update locally
                    row.update({ [field]: newValue });
                    row.reformat();
                    showToast('Price updated successfully', 'success');
                }
            }
        });

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field && def.field !== '_select') {
                    const visible = def.visible !== false;
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
        }

        table.on('tableBuilt', function() {
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            applyFilters();
            updateSummary();
        });

        table.on('renderComplete', function() {
            updateSummary();
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
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
        });

        $('#export-btn').on('click', function() {
            table.download("csv", "walmart_data.csv");
        });
    });
</script>
@endsection
