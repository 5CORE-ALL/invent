@extends('layouts.vertical', ['title' => 'Amazon Pricing FBM', 'sidenav' => 'condensed'])

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
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
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
        'page_title' => 'Amazon Pricing FBM',
        'sub_title' => 'Amazon Pricing FBM',
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
                        <option value="0-4">0-4%</option>
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
                        <option value="show">Show Parent</option>
                        <option value="hide" selected>Hide Parent</option>
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

                    <span class="badge bg-info fs-6 p-2" id="total-sku-count-badge" style="color: black; font-weight: bold; display: none;">Total SKUs: 0</span>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (INV > 0)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-success fs-6 p-2 sold-filter-badge" data-filter="all" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter sold items">
                            Sold (>0): <span id="total-sold-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge" data-filter="zero" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">
                            0 Sold: <span id="zero-sold-count">0</span>
                        </span>
                        
                        <!-- Inventory Mapping Badges (Clickable) -->
                        <span class="badge bg-success fs-6 p-2 map-filter-badge" data-filter="mapped" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter items where INV = INV AMZ">
                            Map: <span id="map-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 map-filter-badge" data-filter="nmapped" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter items where INV ≠ INV AMZ">
                             N Map: <span id="nmap-count">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 missing-amz-filter-badge" data-filter="missing-amazon" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter items missing from Amazon table">
                            Missing: <span id="missing-amazon-count">0</span>
                        </span>
                        
                        <!-- Price Comparison Badge -->
                        <span class="badge bg-danger fs-6 p-2 price-filter-badge" data-filter="prc-gt-lmp" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter items where Prc > LMP">
                            Prc > LMP: <span id="prc-gt-lmp-count">0</span>
                        </span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-l30-badge" style="color: black; font-weight: bold;">Spend L30: $0.00</span>
                        
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge" style="color: black; font-weight: bold;">PFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge" style="color: black; font-weight: bold;">GROI: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="nroi-percent-badge" style="color: black; font-weight: bold;">NROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="tcos-percent-badge" style="color: black; font-weight: bold;">TCOS: 0%</span>
                        
                        <!-- Amazon Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-amazon-inv-badge" style="color: black; font-weight: bold;">INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-amazon-inv-amz-badge" style="color: black; font-weight: bold;">INV AMZ: 0</span>
                        
                        <!-- Ad Spend Breakdown -->
                        <span class="badge bg-dark fs-6 p-2" id="kw-spend-badge" style="color: white; font-weight: bold;">KW Ads: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="hl-spend-badge" style="color: white; font-weight: bold;">HL Ads: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pt-spend-badge" style="color: white; font-weight: bold;">PT Ads: $0</span>
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

    <!-- LMP Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">LMP Data for <span id="lmpSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="lmpDataList"></div>
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
                paginationCounter: "rows",
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
                        title: "Missing",
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
                        sorter: "number"
                    },

                    {
                        title: "A L7",
                        field: "A_L7",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },

                    {
                        title: "View L30",
                        field: "Sess30",
                        hozAlign: "center",
                        sorter: "number",
                        width: 55
                    },

                    {
                        title: "View L7",
                        field: "Sess7",
                        hozAlign: "center",
                        sorter: "number",
                        width: 50
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
                        title: "Prc",
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
                        title: "ACOS",
                        field: "ACOS",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const spend = parseFloat(rowData.SPEND_L30 || rowData.AD_Spend_L30) || 0;
                            const sales = parseFloat(rowData.SALES_L30) || 0;
                            
                            // Calculate ACOS: (SPEND_L30 / SALES_L30) * 100
                            let acos = 0;
                            if (sales > 0) {
                                acos = (spend / sales) * 100;
                            } else if (spend > 0 && sales === 0) {
                                acos = 100;
                            }
                            
                            // If spend > 0 but ACOS is 0, show red alert
                            if (spend > 0 && acos === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                            }
                            
                            let color = '';
                            if (acos < 20) color = '#28a745'; // green
                            else if (acos >= 20 && acos < 30) color = '#3591dc'; // blue
                            else if (acos >= 30 && acos < 40) color = '#ffc107'; // yellow
                            else color = '#a00211'; // red
                            
                            return `<span style="color: ${color}; font-weight: 600;">${acos.toFixed(0)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcACOS = (row) => {
                                const spend = parseFloat(row.SPEND_L30 || row.AD_Spend_L30) || 0;
                                const sales = parseFloat(row.SALES_L30) || 0;
                                if (sales > 0) {
                                    return (spend / sales) * 100;
                                } else if (spend > 0 && sales === 0) {
                                    return 100;
                                }
                                return 0;
                            };
                            return calcACOS(aRow.getData()) - calcACOS(bRow.getData());
                        },
                        width: 60
                    },

                    {
                        title: "Ad Pause",
                        field: "ad_pause",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            
                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';
                            
                            // Don't show toggle if no campaigns exist for this SKU
                            const hasCampaigns = rowData.has_campaigns === true || rowData.has_campaigns === 1;
                            if (!hasCampaigns) return '';
                            
                            // Check campaign status - if either KW or PT is ENABLED, ads are enabled
                            const kwStatus = (rowData.kw_campaign_status || '').toUpperCase();
                            const ptStatus = (rowData.pt_campaign_status || '').toUpperCase();
                            const isEnabled = kwStatus === 'ENABLED' || ptStatus === 'ENABLED';
                            
                            return `
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input ad-pause-toggle" 
                                           type="checkbox" 
                                           role="switch" 
                                           data-sku="${sku}"
                                           ${isEnabled ? 'checked' : ''}
                                           style="cursor: pointer; width: 3rem; height: 1.5rem;">
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            // Prevent row click event but allow checkbox to toggle
                            if (e.target.classList.contains('ad-pause-toggle')) {
                                e.stopPropagation();
                            }
                        },
                        width: 90
                    },

                    {
                        title: "SPEND L30",
                        field: "AD_Spend_L30",
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
                            const lmpLink = rowData.lmp_link;
                            const lmpEntries = rowData.lmp_entries || [];
                            const sku = rowData['(Child) sku'];

                            if (!lmpPrice) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                            const entriesJson = JSON.stringify(lmpEntries).replace(/"/g, '&quot;');

                            if (lmpEntries.length > 0) {
                                return `<a href="#" class="lmp-link" data-sku="${sku}" data-lmp-data="${entriesJson}" 
                                    style="color: #007bff; text-decoration: none; cursor: pointer;">
                                    ${priceFormatted} (${lmpEntries.length})
                                </a>`;
                            } else {
                                return priceFormatted;
                            }
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

                    {
                        title: "SPEND L30",
                        field: "AD_Spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const kwSpend = parseFloat(rowData.kw_spend_L30) || 0;
                            const pmtSpend = parseFloat(rowData.pmt_spend_L30) || 0;
                            const totalSpend = kwSpend + pmtSpend;
                            
                            if (totalSpend === 0) return '';
                            return `
                                <span>$${totalSpend.toFixed(2)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 90,
                        visible: false
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
                        width: 100
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
                        width: 100
                    },
                   
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

                table.clearFilter(true);

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
                        if (gpftFilter === '40plus') return gpft >= 40;
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
                        if (cvrFilter === '0-4') return cvr >= 0 && cvr <= 4;
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

                if (parentFilter === 'hide') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                }

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

                updateCalcValues();
                updateSummary();
                // Update select all checkbox after filter is applied
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #dil-filter, #rating-filter, #parent-filter, #status-filter, #sold-filter').on('change', function() {
                applyFilters();
            });

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
                let totalDilPercent = 0;
                let dilCount = 0;
                let totalSkuCount = 0;
                let totalSoldCount = 0;
                let zeroSoldCount = 0;
                let prcGtLmpCount = 0;
                let mapCount = 0;
                let missingCount = 0;
                let missingAmazonCount = 0;

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
                        totalAmazonL30 += aL30;
                        
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
                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                
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
                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                }, 100);
            });

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

            // Toggle SPEND L30 breakdown columns
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spendL30-btn")) {
                    let colsToToggle = ["kw_spend_L30", "pmt_spend_L30"];

                    colsToToggle.forEach(colField => {
                        const col = table.getColumn(colField);
                        if (col.isVisible()) {
                            col.hide();
                        } else {
                            col.show();
                        }
                    });
                    
                    // Update column visibility in cache
                    saveColumnVisibilityToServer();
                    buildColumnDropdown();
                }

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
                                        pt_campaign_status: newStatus,
                                        ad_pause: !isEnabled
                                    });
                                    
                                    // Reformat the row to update the toggle button with new status
                                    // This will re-run the formatter with the updated row data
                                    rows[i].reformat();
                                    
                                    // Also directly update the checkbox state to ensure it's correct
                                    setTimeout(() => {
                                        const adPauseCell = rows[i].getCell('ad_pause');
                                        if (adPauseCell) {
                                            const cellElement = adPauseCell.getElement();
                                            const checkbox = cellElement.querySelector('.ad-pause-toggle');
                                            if (checkbox) {
                                                // Set checkbox state based on new status
                                                checkbox.checked = isEnabled;
                                            }
                                        }
                                    }, 100);
                                    
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

        // LMP Modal Event Listener
        $(document).on('click', '.lmp-link', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            let data = $(this).data('lmp-data');

            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                openLmpModal(sku, data);
            } catch (error) {
                console.error('Error parsing LMP data:', error);
                alert('Error loading LMP data');
            }
        });

        // LMP Modal Function
        function openLmpModal(sku, data) {
            $('#lmpSku').text(sku);
            let html = '';
            data.forEach(item => {
                html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price: $${item.price}</strong><br>
                    <a href="${item.link}" target="_blank">View Link</a>
                    ${item.image ? `<br><img src="${item.image}" alt="Product Image" style="max-width: 100px; max-height: 100px;">` : ''}
                </div>`;
            });
        $('#lmpDataList').html(html);
            $('#lmpModal').modal('show');
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
    </script>
@endsection
