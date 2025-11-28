@extends('layouts.vertical', ['title' => 'Amazon Pricing Decrease CVR FBM', 'sidenav' => 'condensed'])

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
        'page_title' => 'Amazon Pricing Decrease CVR FBM',
        'sub_title' => 'Amazon Pricing Decrease CVR FBM',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                
                <div>
                    <input type="text" id="sku-search" class="form-control form-control-sm me-2" placeholder="Search SKU..." style="width: 150px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">INV</option>
                        <option value="zero">Zero </option>
                        <option value="more" selected>More</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">NR</option>
                        <option value="nrl">NRL</option>
                        <option value="req">REQ</option>
                    </select>

                    <select id="pft-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">PFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30plus">30%+</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR</option>
                        <option value="0-4">0-4%</option>
                        <option value="4-7">4-7%</option>
                        <option value="7-10">7-10%</option>
                        <option value="10plus">10%+</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="show">Show Parent</option>
                        <option value="hide" selected>Hide Parent</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block me-2">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns"></i> Col
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-eye"></i> Show All
                    </button>

                    {{-- <span class="me-3 px-3 py-1" style="background-color: #e3f2fd; border-radius: 5px;">
                        <strong>PFT%:</strong> <span id="pft-calc">0.00%</span>
                    </span>
                    <span class="me-3 px-3 py-1" style="background-color: #e8f5e9; border-radius: 5px;">
                        <strong>ROI%:</strong> <span id="roi-calc">0.00%</span>
                    </span> --}}

                    <button id="import-btn" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import Ratings
                    </button>

                    <a href="{{ url('/amazon-ratings-sample') }}" class="btn btn-sm btn-info me-2">
                        <i class="fas fa-download"></i> Template
                    </a>

                    <a href="{{ url('/amazon-export-pricing-cvr') }}" class="btn btn-sm btn-success me-2">
                        <i class="fas fa-file-csv"></i> Export
                    </a>
                    
                    <button id="toggle-chart-btn" class="btn btn-sm btn-secondary me-2" style="display: none;">
                        <i class="fa fa-eye-slash"></i> Hide Chart
                    </button>
                </div>

                <!-- Metrics Chart Section -->
                <div id="metrics-chart-section" class="mt-2 p-2 bg-white rounded border" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Metrics Trend</h6>
                        <select id="chart-days-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="7" selected>Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="60">Last 60 Days</option>
                        </select>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="metricsChart"></canvas>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">All Calculations Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Top Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.00%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="cvr-badge" style="color: black; font-weight: bold;">CVR: 0.00%</span>
                        
                        <!-- Amazon Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-amazon-inv-badge" style="color: black; font-weight: bold;">Total Amazon INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-amazon-l30-badge" style="color: black; font-weight: bold;">Total Amazon L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-percent-badge" style="color: black; font-weight: bold;">DIL %: 0%</span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Total TCOS: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-l30-badge" style="color: black; font-weight: bold;">Total Spend L30: $0.00</span>
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-summary-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-summary-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-amt-badge" style="color: black; font-weight: bold;">COGS AMT: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
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
        let metricsChart = null;
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference

        // Initialize Metrics Chart
        function initMetricsChart() {
            const ctx = document.getElementById('metricsChart').getContext('2d');
            metricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Avg Price ($)',
                            data: [],
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Total Views',
                            data: [],
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1',
                            tension: 0.4
                        },
                        {
                            label: 'Avg CVR%',
                            data: [],
                            borderColor: 'rgb(255, 206, 86)',
                            backgroundColor: 'rgba(255, 206, 86, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Avg AD%',
                            data: [],
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
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
                                padding: 10
                            }
                        },
                        title: {
                            display: false
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
                                    if (label.includes('Price') || label.includes('price')) {
                                        return label + ': $' + value.toFixed(2);
                                    } else if (label.includes('Views') || label.includes('views')) {
                                        return label + ': ' + value.toLocaleString();
                                    } else if (label.includes('CVR')) {
                                        return label + ': ' + value.toFixed(1) + '%';
                                    } else if (label.includes('AD') || label.includes('%')) {
                                        return label + ': ' + Math.round(value) + '%';
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Price / Percentages'
                            },
                            beginAtZero: false,
                            ticks: {
                                callback: function(value, index, values) {
                                    // Format based on value range
                                    if (value >= 0 && value <= 200) {
                                        return value.toFixed(0) + '%';
                                    } else {
                                        return '$' + value.toFixed(0);
                                    }
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Views'
                            },
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Load Metrics Data
        function loadMetricsData(days = 7) {
            // Check if table is initialized
            if (!table) {
                console.warn('Table not initialized yet, skipping chart data load');
                return;
            }
            
            // Get current filtered SKUs (INV > 0) from table
            const tableData = table.getData("active");
            const filteredSkus = tableData
                .filter(row => !row['is_parent_summary'] && parseFloat(row['INV']) > 0)
                .map(row => {
                    const sku = row['(Child) sku'];
                    return sku ? sku.toUpperCase().trim() : '';
                })
                .filter(sku => sku && !sku.startsWith('PARENT'));
            
            // Build query string with SKU filter
            const skuParam = filteredSkus.length > 0 ? '&skus=' + encodeURIComponent(JSON.stringify(filteredSkus)) : '';
            
            fetch(`/amazon-metrics-history?days=${days}${skuParam}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0 && metricsChart) {
                        metricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                        metricsChart.data.datasets[0].data = data.map(d => d.avg_price || 0);
                        metricsChart.data.datasets[1].data = data.map(d => d.total_views || 0);
                        metricsChart.data.datasets[2].data = data.map(d => d.avg_cvr_percent || 0);
                        metricsChart.data.datasets[3].data = data.map(d => d.avg_ad_percent || 0);
                        metricsChart.update();
                    }
                })
                .catch(error => {
                    console.error('Error loading metrics data:', error);
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
            initMetricsChart();
            loadMetricsData(7);
            initSkuMetricsChart();

            // Toggle chart button
            $('#toggle-chart-btn').on('click', function() {
                const $chartSection = $('#metrics-chart-section');
                const $btn = $(this);
                
                if ($chartSection.is(':visible')) {
                    $chartSection.slideUp();
                    $btn.html('<i class="fa fa-eye"></i> Show Chart');
                } else {
                    $chartSection.slideDown();
                    $btn.html('<i class="fa fa-eye-slash"></i> Hide Chart');
                }
            });

            // Show chart button by default on first load
            $('#toggle-chart-btn').show();

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
                        skuMetricsChart.options.plugins.title.text = `Amazon Metrics (${days} Days)`;
                        skuMetricsChart.update();
                    }
                    loadSkuMetricsData(currentSku, days);
                }
            });
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
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent_summary === true) {
                        row.getElement().style.backgroundColor = "#bde0ff";
                        row.getElement().style.fontWeight = "bold";
                        row.getElement().classList.add("parent-row");
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
                        width: 150,
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
                                &nbsp;
                                &nbsp;
                                ${rowData.rating ? `<i class="fas fa-star" style="color: orange;"></i> <span style="font-weight: bold;">${rowData.rating}</span>` : ''}
                            </div>`;
                        },
                        width: 120
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
                        title: "A L30",
                        field: "A_L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },


                     {
                        title: "CVR",
                        field: "CVR_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const aL30 = parseFloat(row['A_L30']) || 0;
                            const sess30 = parseFloat(row['Sess30']) || 0;

                            if (sess30 === 0) return '<span style="color: #6c757d; font-weight: 600;">0.0%</span>';

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
                        width: 60
                    },


                    {
                        title: "View",
                        field: "Sess30",
                        hozAlign: "center",
                        sorter: "number",
                        width: 50
                    },



                    {
                        title: "NR/REQ",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();

                            // Empty for parent rows
                            if (row.is_parent_summary) return '';

                            const nrl = row['NRL'] || '';
                            const sku = row['(Child) sku'];

                            // Determine current value (default to REQ if empty)
                            let value = '';
                            if (nrl === 'NRL') {
                                value = 'NRL';
                            } else if (nrl === 'REQ') {
                                value = 'REQ';
                            } else {
                                value = 'REQ'; // Default to REQ
                            }

                            return `<select class="form-select form-select-sm nr-select" data-sku="${sku}"
                                style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 4px;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>NR</option>
                            </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 90
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

                            return '$' + parseFloat(value).toFixed(2);
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
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 50
                    },

                    {
                        title: "AD%",
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
                        title: "PFT %",
                        field: "PFT%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            let color = '';
                            
                            // getPftColor logic from inc/dec page (same as eBay)
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 50
                    },
                    {
                        title: "ROI%",
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
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            
                            if (!value) return '';
                            
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
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
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
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
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
                    url: '/save-amazon-nr',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        nr: value
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
                table.setFilter("SKU", "like", value);
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
                const pftFilter = $('#pft-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const parentFilter = $('#parent-filter').val();

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
                }

                if (nrlFilter !== 'all') {
                    if (nrlFilter === 'req') {
                        // Show all data except NRL
                        table.addFilter(function(data) {
                            return data.NRL !== 'NRL';
                        });
                    } else {
                        // Show only NRL
                        table.addFilter(function(data) {
                            return data.NRL === 'NRL';
                        });
                    }
                }

                if (pftFilter !== 'all') {
                    table.addFilter(function(data) {
                        const pft = parseFloat(data['PFT_percentage']) || 0;
                        if (pftFilter === 'negative') return pft < 0;
                        if (pftFilter === '0-10') return pft >= 0 && pft < 10;
                        if (pftFilter === '10-20') return pft >= 10 && pft < 20;
                        if (pftFilter === '20-30') return pft >= 20 && pft < 30;
                        if (pftFilter === '30plus') return pft >= 30;
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        const aL30 = parseFloat(data['A_L30']) || 0;
                        const sess30 = parseFloat(data['Sess30']) || 0;
                        const cvr = sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                        
                        if (cvrFilter === '0-4') return cvr >= 0 && cvr <= 4;
                        if (cvrFilter === '4-7') return cvr > 4 && cvr <= 7;
                        if (cvrFilter === '7-10') return cvr > 7 && cvr <= 10;
                        if (cvrFilter === '10plus') return cvr > 10;
                        return true;
                    });
                }

                if (parentFilter === 'hide') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                }

                updateCalcValues();
                updateSummary();
                // Reload chart with filtered data (only INV > 0)
                const days = $('#chart-days-filter').val() || 7;
                loadMetricsData(days);
            }

            $('#inventory-filter, #nrl-filter, #pft-filter, #cvr-filter, #parent-filter').on('change', function() {
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
            }

            // Update summary badges for INV > 0
            function updateSummary() {
                const data = table.getData("active");
                let totalTcos = 0;
                let totalSpendL30 = 0;
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalAmazonInv = 0;
                let totalAmazonL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;

                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        totalTcos += parseFloat(row['AD%'] || 0);
                        totalSpendL30 += parseFloat(row['AD_Spend_L30'] || 0);
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * parseFloat(row['A_L30'] || 0);
                        totalAmazonInv += parseFloat(row['INV'] || 0);
                        totalAmazonL30 += parseFloat(row['A_L30'] || 0);
                        
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
                $('#avg-cvr-badge').text('Avg CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#cvr-badge').text('CVR: ' + avgCVR.toFixed(2) + '%');
                

                $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos) + '%');
                $('#total-spend-l30-badge').text('Total Spend L30: $' + Math.round(totalSpendL30));
                $('#total-pft-amt-summary-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                $('#total-sales-amt-summary-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                $('#total-cogs-amt-badge').text('COGS AMT: $' + Math.round(totalLpAmt));
                const roiPercent = totalLpAmt > 0 ? Math.round((totalPftAmt / totalLpAmt) * 100) : 0;
                $('#roi-percent-badge').text('ROI %: ' + roiPercent + '%');
                $('#total-amazon-inv-badge').text('Total Amazon INV: ' + Math.round(totalAmazonInv).toLocaleString());
                $('#total-amazon-l30-badge').text('Total Amazon L30: ' + Math.round(totalAmazonL30).toLocaleString());
                const avgDilPercent = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
                $('#avg-dil-percent-badge').text('DIL %: ' + Math.round(avgDilPercent) + '%');
                $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                const avgGpft = totalSalesAmt > 0 ? Math.round((totalPftAmt / totalSalesAmt) * 100) : 0;
                $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft + '%');
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
            });

            table.on('dataLoaded', function() {
                updateCalcValues();
                updateSummary();
                // Reload chart with current filtered data
                const days = $('#chart-days-filter').val() || 7;
                loadMetricsData(days);
                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
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
