@extends('layouts.vertical', ['title' => 'FBA Pricing Data (> 0 INV)', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Hide sorting icons in Tabulator */
        .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Circular button styling */
        .btn-circle {
            border-radius: 50% !important;
            width: 35px;
            height: 35px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
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
        'page_title' => 'FBA pricing data (> 0 INV)',
        'sub_title' => 'FBA pricing data (> 0 INV)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>FBA pricing data (> 0 INV)</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" id="more-inventory-option" selected>More than 0</option>
                    </select>
                    <select id="parent-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="show">Show Parent</option>
                        <option value="hide" selected>Hide Parent</option>
                    </select>

                    <select id="pft-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Gpft</option>
                        <option value="0-10">0-10%</option>
                        <option value="11-14">11-14%</option>
                        <option value="15-20">15-20%</option>
                        <option value="21-49">21-49%</option>
                        <option value="50+">50%+</option>
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
                        <option value="not_pushed">Not Pushed</option>
                        <option value="pushed">Pushed</option>
                        <option value="applied">Applied</option>
                        <option value="error">Error</option>
                    </select>

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

                    <a href="{{ url('/fba-manual-sample') }}" class="btn btn-sm btn-info">
                        <i class="fa fa-download"></i> Sample Template
                    </a>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="fa fa-file-excel"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#importModal">
                        <i class="fa fa-upload"></i>
                    </button>
                    
                    <button id="toggle-chart-btn" class="btn btn-sm btn-secondary" style="display: none;">
                        <i class="fa fa-eye-slash"></i> Hide Chart
                    </button>
                    
                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-percent"></i> Decrease
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-percent"></i> Increase
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
                        
                        <!-- FBA Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-fba-inv-badge" style="color: black; font-weight: bold;">Total FBA INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-fba-l30-badge" style="color: black; font-weight: bold;">Total FBA L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-percent-badge" style="color: black; font-weight: bold;">DIL %: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-sku-count-badge" style="color: black; font-weight: bold;">0 Sold SKU: 0</span>
                        
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
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 fw-bold" id="discount-label">Discount %:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter percentage %" step="0.1" min="0" max="100" 
                            style="width: 150px; display: inline-block;">
                        <button id="apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div id="fba-table-wrapper" style="height: 600px; display: flex; flex-direction: column;">

                    <!--Table body (scrollable section) -->
                    <div id="fba-table" style="flex: 1;"></div>

                </div>
            </div>
        </div>
    </div>

    <!-- Inv age Modal -->
    <div class="modal fade" id="invageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inv age Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>SKU:</strong> <span id="modalSKU"></span></p>
                    <p><strong>Inv age:</strong> <span id="modalInvage"></span></p>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="yearsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Years Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>SKU:</strong> <span id="modalSKU"></span></p>
                    <p><strong>Year:</strong> <span id="modalYear"></span></p>

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
                    <h5 class="modal-title">Import FBA Manual Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Choose CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv"
                                required>
                        </div>
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> CSV must have: SKU, Dimensions, Weight, Qty in each box,
                            Total
                            qty Sent, Total Send Cost, Inbound qty, Send cost
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- LMP Modal -->
        <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
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
    @endsection

    @section('script-bottom')
        <script>
            const COLUMN_VIS_KEY = "fba_tabulator_column_visibility";
            let metricsChart = null;
            let table = null; // Global table reference
            let decreaseModeActive = false; // Track decrease mode state
            let increaseModeActive = false; // Track increase mode state
            let selectedSkus = new Set(); // Track selected SKUs across all pages
            
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
                                label: 'Avg GPFT%',
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
                                label: 'Avg GROI%',
                                data: [],
                                borderColor: 'rgb(153, 102, 255)',
                                backgroundColor: 'rgba(153, 102, 255, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y',
                                tension: 0.4
                            },
                            {
                                label: 'Avg TACOS%',
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
                                        } else if (label.includes('TACOS')) {
                                            // TACOS: round figure (e.g., 100%, 15%)
                                            return label + ': ' + Math.round(value) + '%';
                                        } else if (label.includes('GPFT') || label.includes('GROI') || label.includes('%')) {
                                            return label + ': ' + value.toFixed(2) + '%';
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
                                        // Format based on value range - prices are typically larger numbers
                                        // Percentages are typically 0-100 range
                                        if (value >= 0 && value <= 200) {
                                            // Likely a percentage
                                            return value.toFixed(0) + '%';
                                        } else {
                                            // Likely a price
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
                fetch(`/fba-metrics-history?days=${days}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            // Update chart
                            if (metricsChart) {
                                metricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                                metricsChart.data.datasets[0].data = data.map(d => d.avg_price || 0);
                                metricsChart.data.datasets[1].data = data.map(d => d.total_views || 0);
                                metricsChart.data.datasets[2].data = data.map(d => d.avg_gprft || 0);
                                metricsChart.data.datasets[3].data = data.map(d => d.avg_groi_percent || 0);
                                metricsChart.data.datasets[4].data = data.map(d => d.avg_tacos || 0);
                                metricsChart.update();
                            }
                            
                            // Update metrics summary with latest data
                            const latest = data[data.length - 1];
                            $('#metric-avg-gprft').text(latest.avg_gprft + '%');
                            $('#metric-avg-groi').text(latest.avg_groi_percent + '%');
                            $('#metric-avg-tacos').text(latest.avg_tacos + '%');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading metrics data:', error);
                    });
            }

            // SKU-specific chart
            let skuMetricsChart = null;
            let currentSku = null;

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
                                label: 'TACOS%',
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
                                text: 'FBA SKU Metrics',
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
                                            // CVR: 1 decimal point (e.g., 5.2%)
                                            return label + ': ' + value.toFixed(1) + '%';
                                        } else if (label.includes('TACOS')) {
                                            // TACOS: round figure (e.g., 100%, 15%)
                                            return label + ': ' + Math.round(value) + '%';
                                        } else if (label.includes('%')) {
                                            return label + ': ' + value.toFixed(2) + '%';
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
                                        // Format price values with $ symbol
                                        // Since this axis is shared, we'll format based on the value range
                                        // If values are small (< 100), likely prices, else views
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
                fetch(`/fba-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
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
                                // Show message and clear chart
                                $('#chart-no-data-message').show();
                                skuMetricsChart.data.labels = [];
                                skuMetricsChart.data.datasets.forEach(dataset => {
                                    dataset.data = [];
                                });
                                skuMetricsChart.options.plugins.title.text = 'FBA Metrics';
                                skuMetricsChart.update();
                                return;
                            }
                            
                            // Hide message if data exists
                            $('#chart-no-data-message').hide();
                            
                            // Update chart title with days
                            skuMetricsChart.options.plugins.title.text = `FBA Metrics (${days} Days)`;
                            
                            // Use actual dates instead of "Day X"
                            skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                            
                            // Update data
                            skuMetricsChart.data.datasets[0].data = data.map(d => d.price || 0);
                            skuMetricsChart.data.datasets[1].data = data.map(d => d.views || 0);
                            skuMetricsChart.data.datasets[2].data = data.map(d => d.cvr_percent || 0);
                            skuMetricsChart.data.datasets[3].data = data.map(d => d.tacos_percent || 0);
                            
                            skuMetricsChart.update('active');
                            console.log('Chart updated successfully with', data.length, 'data points');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading SKU metrics data:', error);
                        alert('Error loading metrics data. Please check console for details.');
                    });
            }

            // Background retry storage key
            const BACKGROUND_RETRY_KEY = 'fba_failed_price_pushes';
            
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
                        
                        // Try to find the cell in the table for UI update
                        let cell = null;
                        if (table) {
                            try {
                                const rows = table.getRows();
                                for (let i = 0; i < rows.length; i++) {
                                    const rowData = rows[i].getData();
                                    if (rowData.FBA_SKU === sku || rowData.SKU === sku) {
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
            
            // Apply price with retry logic
            // NOTE: Backend now includes automatic verification and retry (2 attempts with fresh token)
            // This frontend retry handles network issues and background retries for failed pushes
            async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
                const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
                const row = cell ? cell.getRow() : null;
                const rowData = row ? row.getData() : null;

                // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
                if (isBackgroundRetry) {
                    try {
                        const response = await $.ajax({
                            url: '/push-fba-price',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name=\"csrf-token\"]').attr('content')
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
                            $btn.html('<i class=\"fa-solid fa-check-double\"></i>');
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
                            $btn.html('<i class=\"fa-solid fa-x\"></i>');
                            $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                        }
                        return false;
                    }
                }

                // Foreground mode (user click): up to 5 immediate retries with spinner UI
                // Set initial loading state (only if cell exists)
                if (retries === 0 && cell && $btn && row) {
                    $btn.prop('disabled', true);
                    $btn.html('<i class=\"fas fa-spinner fa-spin\"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;'); // Yellow text, no background
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'processing';
                        row.update(rowData);
                    }
                }

                try {
                    const response = await $.ajax({
                        url: '/push-fba-price',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name=\"csrf-token\"]').attr('content')
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
                        $btn.html('<i class=\"fa-solid fa-check-double\"></i>');
                        $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;'); // Green text, no background
                    }
                    
                    if (!isBackgroundRetry) {
                        showToast('success', `Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`);
                    }
                    
                    return true;
                } catch (xhr) {
                    const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to apply price';
                    console.error(`Attempt ${retries + 1} for SKU ${sku} failed:`, errorMsg);

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
                            $btn.html('<i class=\"fa-solid fa-x\"></i>');
                            $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;'); // Red text, no background
                        }
                        
                        // Save for background retry (only if not already a background retry)
                        saveFailedSkuForRetry(sku, price, 0);
                        showToast('error', `Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`);
                        
                        return false;
                    }
                }
            }

            // Update selected count display
            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#discount-input-container').toggle(count > 0);
            }

            // Update select all checkbox state
            function updateSelectAllCheckbox() {
                const allData = table.getData('active');
                const childRows = allData.filter(row => !row.is_parent);
                const allSelected = childRows.length > 0 && childRows.every(row => selectedSkus.has(row.SKU));
                $('#select-all-checkbox').prop('checked', allSelected);
            }

            // Apply discount/increase to selected SKUs
            function applyDiscount() {
                const percent = parseFloat($('#discount-percentage-input').val());
                
                if (isNaN(percent) || percent < 0 || percent > 100) {
                    showToast('error', 'Please enter a valid percentage (0-100)');
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

                const allData = table.getData('active');
                let updatedCount = 0;
                const mode = increaseModeActive ? 'increase' : 'decrease';

                allData.forEach(row => {
                    if (row.is_parent) return;
                    
                    if (selectedSkus.has(row.SKU)) {
                        const currentPrice = parseFloat(row.FBA_Price) || 0;
                        if (currentPrice > 0) {
                            let newSPrice;
                            if (increaseModeActive) {
                                // Increase: multiply by (1 + percent/100)
                                newSPrice = currentPrice * (1 + percent / 100);
                            } else {
                                // Decrease: multiply by (1 - percent/100)
                                newSPrice = currentPrice * (1 - percent / 100);
                            }
                            newSPrice = Math.max(0.01, newSPrice);
                            
                            // Update S_Price in database
                            $.ajax({
                                url: '/update-fba-manual-data',
                                method: 'POST',
                                data: {
                                    sku: row.FBA_SKU,
                                    field: 's_price',
                                    value: newSPrice,
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function() {
                                    updatedCount++;
                                    if (updatedCount === selectedSkus.size) {
                                        const actionText = increaseModeActive ? 'Increase' : 'Decrease';
                                        showToast('success', `${actionText} applied to ${updatedCount} SKU(s)`);
                                        table.replaceData();
                                    }
                                },
                                error: function() {
                                    showToast('error', `Error applying ${mode}`);
                                }
                            });
                        }
                    }
                });
            }

            // Retry function for applying price with up to 5 attempts (Promise-based for Apply All)
            // NOTE: Backend now includes automatic verification and retry (2 attempts with fresh token)
            // This frontend retry is for network errors, timeouts, or persistent failures
            function applyPriceWithRetryPromise(sku, price, maxRetries = 5, delay = 5000) {
                return new Promise((resolve, reject) => {
                    let attempt = 0;
                    
                    function attemptApply() {
                        attempt++;
                        
                        $.ajax({
                            url: '/push-fba-price',
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
                                // Log response for debugging
                                console.log(`Attempt ${attempt} response for SKU ${sku}:`, response);
                                
                                // Check for errors in response
                                if (response.errors && response.errors.length > 0) {
                                    const errorMsg = response.errors[0].message || 'Unknown error';
                                    const errorCode = response.errors[0].code || '';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg, 'Code:', errorCode);
                                    
                                    // Check if it's an authentication error - don't retry immediately
                                    if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || errorCode === 'AuthenticationError' || errorMsg.includes('Client authentication failed')) {
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
                                    console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                                    resolve({ success: true, response: response });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                                const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                                const statusCode = xhr.status || 0;
                                
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, {
                                    error: errorMsg,
                                    code: errorCode,
                                    status: statusCode,
                                    response: xhr.responseJSON,
                                    responseText: xhr.responseText
                                });
                                
                                // Check if it's an authentication error
                                if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || statusCode === 401 || errorCode === 'AuthenticationError' || errorMsg.includes('Client authentication failed')) {
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

            // Apply all selected prices
            window.applyAllSelectedPrices = function() {
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU to apply prices');
                    return;
                }
                
                const $btn = $('#apply-all-btn');
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
                $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
                
                // Get all table data to find S_Price for selected SKUs
                const tableData = table.getData('all');
                const skusToProcess = [];
                
                // Build list of SKUs with their prices
                selectedSkus.forEach(sku => {
                    const row = tableData.find(r => r.SKU === sku);
                    if (row) {
                        const sprice = parseFloat(row.S_Price) || 0;
                        const fbaSku = row.FBA_SKU || sku;
                        if (sprice > 0) {
                            skusToProcess.push({ sku: fbaSku, price: sprice, childSku: sku });
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
                            $btn.html(`<i class="fas fa-check-double" style="color: #28a745;"></i>`);
                            showToast('success', `Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`);
                            
                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.html(originalHtml);
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                        }
                        return;
                    }
                    
                    const { sku, price, childSku } = skusToProcess[currentIndex];
                    
                    // Find the row and update button to show spinner
                    const row = table.getRows().find(r => r.getData().SKU === childSku);
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
                    
                    // First save to database (like S_Price edit does), then push to Amazon
                    console.log(`Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving S_Price ${price} to database...`);
                    
                    $.ajax({
                        url: '/update-fba-manual-data',
                        method: 'POST',
                        data: {
                            sku: sku,
                            field: 's_price',
                            value: price,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(saveResponse) {
                            console.log(`SKU ${sku}: Database save successful`, saveResponse);
                            if (saveResponse.success === false) {
                                console.error(`Failed to save S_Price for SKU ${sku}:`, saveResponse.error);
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
                            
                            // After saving, push to Amazon using retry function
                            console.log(`SKU ${sku}: Starting Amazon price push...`);
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
                            console.error(`Failed to save S_Price for SKU ${sku}:`, xhr.responseJSON || xhr.responseText);
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

                // Decrease button toggle
                $('#decrease-btn').on('click', function() {
                    decreaseModeActive = !decreaseModeActive;
                    const selectColumn = table.getColumn('_select');
                    
                    if (decreaseModeActive) {
                        // If increase mode is active, deactivate it first
                        if (increaseModeActive) {
                            increaseModeActive = false;
                            $('#increase-btn').removeClass('btn-danger').addClass('btn-success');
                            $('#increase-btn').html('<i class="fas fa-percent"></i> Increase');
                        }
                        selectColumn.show();
                        $(this).removeClass('btn-warning').addClass('btn-danger');
                        $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                        $('#discount-label').text('Decrease %:');
                    } else {
                        selectColumn.hide();
                        $(this).removeClass('btn-danger').addClass('btn-warning');
                        $(this).html('<i class="fas fa-percent"></i> Decrease');
                        selectedSkus.clear();
                        updateSelectedCount();
                        updateSelectAllCheckbox();
                        $('#discount-input-container').hide();
                    }
                });

                // Increase button toggle
                $('#increase-btn').on('click', function() {
                    increaseModeActive = !increaseModeActive;
                    const selectColumn = table.getColumn('_select');
                    
                    if (increaseModeActive) {
                        // If decrease mode is active, deactivate it first
                        if (decreaseModeActive) {
                            decreaseModeActive = false;
                            $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning');
                            $('#decrease-btn').html('<i class="fas fa-percent"></i> Decrease');
                        }
                        selectColumn.show();
                        $(this).removeClass('btn-success').addClass('btn-danger');
                        $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                        $('#discount-label').text('Increase %:');
                    } else {
                        selectColumn.hide();
                        $(this).removeClass('btn-danger').addClass('btn-success');
                        $(this).html('<i class="fas fa-percent"></i> Increase');
                        selectedSkus.clear();
                        updateSelectedCount();
                        updateSelectAllCheckbox();
                        $('#discount-input-container').hide();
                    }
                });

                // Select all checkbox handler
                $(document).on('change', '#select-all-checkbox', function() {
                    const isChecked = $(this).prop('checked');
                    const allData = table.getData('active');
                    const childRows = allData.filter(row => !row.is_parent);
                    
                    childRows.forEach(row => {
                        if (isChecked) {
                            selectedSkus.add(row.SKU);
                        } else {
                            selectedSkus.delete(row.SKU);
                        }
                    });
                    
                    // Update all checkboxes
                    table.getRows().forEach(tableRow => {
                        const rowData = tableRow.getData();
                        if (!rowData.is_parent) {
                            const checkbox = $(tableRow.getElement()).find('.sku-select-checkbox');
                            checkbox.prop('checked', isChecked);
                        }
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
                        // Update chart title immediately
                        if (skuMetricsChart) {
                            skuMetricsChart.options.plugins.title.text = `FBA Metrics (${days} Days)`;
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
                    $('#chart-no-data-message').hide(); // Hide message initially
                    loadSkuMetricsData(sku, 7);
                    $('#skuMetricsModal').modal('show');
                });

                table = new Tabulator("#fba-table", {
                    ajaxURL: "/fba-data-json",
                    ajaxSorting: true,
                    layout: "fitData",
                    pagination: true,
                    paginationSize: 50,
                    paginationCounter: "rows",
                    initialSort: [{
                        column: "FBA_Dil",
                        dir: "asc"
                    }],
                    rowFormatter: function(row) {
                        if (row.getData().is_parent) {
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
                        //     frozen: true
                        // },
                        // {
                        //     title: "Child <br> SKU",
                        //     field: "SKU",
                        //     headerFilter: "input",
                        //     headerFilterPlaceholder: "Search SKU...",
                        //     cssClass: "font-weight-bold",
                        //     tooltip: true,
                        //     frozen: true,
                        //     formatter: function(cell) {
                        //         const sku = cell.getValue();
                        //         const rowData = cell.getRow().getData();
                        //         if (rowData.is_parent) return sku;
                                
                        //         return `
                        //             <span>${sku}</span>
                        //             <i class="fa fa-copy text-secondary copy-sku-btn" 
                        //                style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                        //                data-sku="${sku}"
                        //                title="Copy SKU"></i>
                        //         `;
                        //     }
                        // },
                        {
                            title: "FBA <br>SKU",
                            field: "FBA_SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true,
                            formatter: function(cell) {
                                const fbaSku = cell.getValue();
                                const sku = cell.getRow().getData().SKU;
                                const ratings = cell.getRow().getData().Ratings;
                                if (!fbaSku || cell.getRow().getData().is_parent) return fbaSku;
                                
                                let ratingDisplay = '';
                                if (ratings && ratings > 0) {
                                    ratingDisplay = ` <i class="fa fa-star" style="color: orange;"></i> ${ratings}`;
                                }
                                
                                return `${fbaSku}${ratingDisplay} <button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
                            }
                        },
                       
                        // {
                        //     title: "Shopify INV",
                        //     field: "Shopify_INV",
                        //     hozAlign: "center"
                        // },
                        {
                            title: "FBA <br> INV",
                            field: "FBA_Quantity",
                            hozAlign: "center"
                        },


                        // {
                        //     title: "L60  FBA",
                        //     field: "l60_units",
                        //     hozAlign: "center"
                        // },

                        {
                            title: "L30 <br> FBA",
                            field: "l30_units",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA Dil",
                            field: "FBA_Dil",
                            sorter: "number",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                const formattedValue = `${value.toFixed(0)}%`;
                                let color = '';
                                if (value <= 50) color = 'red';
                                else if (value <= 100) color = 'green';
                                else color = 'purple';
                                return `<span style="color:${color}; font-weight:600;">${formattedValue}</span>`;
                            },
                        },





                        {
                            title: "FBA <br> CVR",
                            field: "FBA_CVR",
                            sorter: function(a, b) {
                                const numA = parseFloat(a.replace(/<[^>]*>/g, '').replace('%', ''));
                                const numB = parseFloat(b.replace(/<[^>]*>/g, '').replace('%', ''));
                                return numA - numB;
                            },
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },




                        {
                            title: "Views",
                            field: "Current_Month_Views",
                            hozAlign: "center"
                        },


                        {
                            title: "Inv<br> age",
                            field: "Inv_age",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue();
                                return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openInvageModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                            }
                        },

                        {
                            title: "FBA<br> Price",
                            field: "FBA_Price",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const price = parseFloat(cell.getValue() || 0);
                                const lmp = parseFloat(cell.getRow().getData().lmp_1 || 0);
                                let color = '';
                                if (lmp > 0) {
                                    if (price > lmp) color = 'red';
                                    else if (price < lmp) color = 'darkgreen';
                                }
                                return `<span style="color:${color};">${price.toFixed(2)}</span>`;
                            }
                        },


                        {
                            title: "LMP ",
                            field: "lmp_1",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue();
                                const rowData = cell.getRow().getData();
                                if (value > 0) {
                                    return `<a href="#" class="lmp-link" data-sku="${rowData.SKU}" data-lmp-data='${JSON.stringify(rowData.lmp_data)}' style="color: blue; text-decoration: underline;">${value}</a>`;
                                } else {
                                    return value || '';
                                }
                            }
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
                                if (rowData.is_parent) return '';
                                
                                const sku = rowData.SKU;
                                const isSelected = selectedSkus.has(sku);
                                
                                return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                            }
                        },
                        {
                            title: "GPFT%",
                            field: "GPFT%",
                            sorter: function(a, b) {
                                const numA = parseFloat(a.replace(/<[^>]*>/g, '').replace('%', ''));
                                const numB = parseFloat(b.replace(/<[^>]*>/g, '').replace('%', ''));
                                return numA - numB;
                            },
                            hozAlign: "center",
                            formatter: function(cell) {
                                const rawValue = cell.getValue();
                                const value = parseFloat(rawValue.replace('%', '')) || 0;
                                let style = '';
                                if (value < 10) {
                                    style = 'color: red;';
                                } else if (value >= 11 && value <= 15) {
                                    style = 'background-color: yellow; color: black;';
                                } else if (value >= 16 && value <= 20) {
                                    style = 'color: blue;';
                                } else if (value >= 21 && value <= 40) {
                                    style = 'color: green;';
                                } else if (value > 40) {
                                    style = 'color: purple;';
                                }
                                return `<span style="${style}">${rawValue}</span>`;
                            },
                        },

                        // {
                        //     title: "PFT AMT",
                        //     field: "PFT_AMT",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = parseFloat(cell.getValue()) || 0;
                        //         return '$' + value.toFixed(2);
                        //     },
                        // },

                        // {
                        //     title: "SALES AMT",
                        //     field: "SALES_AMT",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = parseFloat(cell.getValue()) || 0;
                        //         return '$' + value.toFixed(2);
                        //     },
                        // },

                        // {
                        //     title: "LP AMT",
                        //     field: "LP_AMT",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = parseFloat(cell.getValue()) || 0;
                        //         return '$' + value.toFixed(2);
                        //     },
                        // },

                        {
                            title: "GROI%",
                            field: "GROI%",
                            sorter: function(a, b) {
                                const numA = parseFloat(a.replace(/<[^>]*>/g, '').replace('%', ''));
                                const numB = parseFloat(b.replace(/<[^>]*>/g, '').replace('%', ''));
                                return numA - numB;
                            },
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "TACOS",
                            field: "TCOS_Percentage",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                return value.toFixed(0) + '%';
                            }
                        },

                        {
                            title: "PRFT<br>%",
                            field: "TPFT",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                let style = '';
                                if (value < 10) {
                                    style = 'color: red;';
                                } else if (value >= 11 && value <= 15) {
                                    style = 'background-color: yellow; color: black;';
                                } else if (value >= 16 && value <= 20) {
                                    style = 'color: blue;';
                                } else if (value >= 21 && value <= 40) {
                                    style = 'color: green;';
                                } else if (value > 40) {
                                    style = 'color: purple;';
                                }
                                return `<span style="${style}">${value.toFixed(1)}%</span>`;
                            },
                        },

                        {
                            title: "ROI%",
                            field: "ROI",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                const el = cell.getElement();

                                // remove old styles
                                el.style.color = "";
                                el.style.fontWeight = "bold";

                                // 🎨 Text Color Conditions
                                if (value >= 0 && value <= 50) {
                                    el.style.color = "red"; // 0–50
                                } else if (value >= 51 && value <= 100) {
                                    el.style.color = "green"; // 51–100
                                } else if (value >= 101) {
                                    el.style.color = "magenta"; // 101+ (Pink shade)
                                }

                                return value.toFixed(0) + "%";
                            },
                        },




                        {
                            title: "S <br> Price",
                            field: "S_Price",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = parseFloat(cell.getValue());

                                // ❌ Stop if value is 0 or < 1
                                if (isNaN(value) || value < 1) {
                                    alert("Price must be 1 or greater. Cannot push 0 or invalid prices.");
                                    // Reset previous value
                                    cell.restoreOldValue();
                                    return;
                                }

                                // ✅ Additional safety check - prevent empty or 0 from being saved
                                if (!value || value === 0) {
                                    alert("Cannot save or push 0 price.");
                                    cell.restoreOldValue();
                                    return;
                                }

                                // ✔️ Update in database
                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 's_price',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(response) {
                                        if (response.success === false) {
                                            alert('Failed to save: ' + (response.error || 'Unknown error'));
                                            cell.restoreOldValue();
                                        } else {
                                            table.replaceData();
                                        }
                                    },
                                    error: function(xhr) {
                                        alert('Error saving price: ' + (xhr.responseJSON?.error || 'Network error'));
                                        cell.restoreOldValue();
                                    }
                                });

                                // ✔️ Push price to Amazon (only if valid)
                                if (value > 0) {
                                    $.ajax({
                                        url: '/push-fba-price',
                                        method: 'POST',
                                        data: {
                                            sku: data.FBA_SKU,
                                            price: value,
                                            _token: '{{ csrf_token() }}'
                                        },
                                        success: function(result) {
                                            console.log('Price pushed to Amazon', result);
                                            if (result.success === false) {
                                                alert('Failed to push price: ' + (result.error || 'Unknown error'));
                                                cell.restoreOldValue();
                                            }
                                        },
                                        error: function(xhr) {
                                            console.error('Failed to push price', xhr.responseJSON);
                                            alert('Error pushing price: ' + (xhr.responseJSON?.error || 'Network error'));
                                            cell.restoreOldValue();
                                        }
                                    });
                                }
                            },
                        },
                        {
                            field: "_accept",
                            hozAlign: "center",
                            headerSort: false,
                            titleFormatter: function(column) {
                                return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                                    <span>Accept</span>
                                    <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to Amazon" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                        <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                    </button>
                                </div>`;
                            },
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';

                                const sku = rowData.SKU;
                                const fbaSku = rowData.FBA_SKU;
                                const sprice = parseFloat(rowData.S_Price) || 0;
                                const status = rowData.SPRICE_STATUS || null;

                                if (!sprice || sprice === 0) {
                                    return '<span style="color: #999;">N/A</span>';
                                }

                                let icon = '<i class="fas fa-check"></i>';
                                let iconColor = '#28a745'; // Green for apply
                                let titleText = 'Apply Price to Amazon';

                                if (status === 'processing') {
                                    icon = '<i class="fas fa-spinner fa-spin"></i>';
                                    iconColor = '#ffc107'; // Yellow text
                                    titleText = 'Price pushing in progress...';
                                } else if (status === 'pushed') {
                                    icon = '<i class="fa-solid fa-check-double"></i>';
                                    iconColor = '#28a745'; // Green text
                                    titleText = 'Price pushed to Amazon (Double-click to mark as Applied)';
                                } else if (status === 'applied') {
                                    icon = '<i class="fa-solid fa-check-double"></i>';
                                    iconColor = '#28a745'; // Green text
                                    titleText = 'Price applied to Amazon (Double-click to change)';
                                } else if (status === 'error') {
                                    icon = '<i class="fa-solid fa-x"></i>';
                                    iconColor = '#dc3545'; // Red text
                                    titleText = 'Error applying price to Amazon';
                                }

                                // Show only icon with color, no background
                                return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${fbaSku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
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
                                            url: '/update-fba-sprice-status',
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                            },
                                            data: { sku: sku, status: 'applied' },
                                            success: function(response) {
                                                if (response.success) {
                                                    table.replaceData();
                                                    showToast('success', 'Status updated to Applied');
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
                                    
                                    if (!sku || !price || price <= 0 || isNaN(price)) {
                                        showToast('error', 'Invalid SKU or price');
                                        return;
                                    }
                                    
                                    applyPriceWithRetry(sku, price, cell, 0);
                                }
                            }
                        },
                        {
                            title: "SGPFT%",
                            field: "SGPFT%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "SGROI%",
                            field: "SGROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },



                        {
                            title: "SPft%",
                            field: "SPFT",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                let style = '';
                                if (value < 10) {
                                    style = 'color: red;';
                                } else if (value >= 11 && value <= 15) {
                                    style = 'background-color: yellow; color: black;';
                                } else if (value >= 16 && value <= 20) {
                                    style = 'color: blue;';
                                } else if (value >= 21 && value <= 40) {
                                    style = 'color: green;';
                                } else if (value > 40) {
                                    style = 'color: purple;';
                                }
                                return `<span style="${style}">${value.toFixed(0)}%</span>`;
                            },
                        },
                        {
                            title: "SROI%",
                            field: "SROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },




                        {
                            title: "LP",
                            field: "LP",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA<br> Ship",
                            field: "FBA_Ship_Calculation",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return value.toFixed(2);
                            }
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
                        //     }
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
                        //     }
                        // },
                        {
                            title: "FBA<br> Fee",
                            field: "Fulfillment_Fee",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA <br> Fee <br> M",
                            field: "FBA_Fee_Manual",
                            hozAlign: "center",
                            editable: function(cell) {
                                // Only editable if Fulfillment_Fee is 0
                                const fulfillmentFee = parseFloat(cell.getRow().getData()
                                    .Fulfillment_Fee) || 0;
                                return fulfillmentFee === 0;
                            },
                            editor: "input",
                            formatter: function(cell) {
                                const fulfillmentFee = parseFloat(cell.getRow().getData()
                                    .Fulfillment_Fee) || 0;
                                if (fulfillmentFee === 0) {
                                    cell.getElement().style.color = "#a80f8b";
                                } else {
                                    cell.getElement().style.color = "#999";
                                    cell.getElement().style.cursor = "not-allowed";
                                }
                                return cell.getValue();
                            }
                        },

                        ,

                        // {
                        //     title: "ASIN",
                        //     field: "ASIN"
                        // },
                        // {
                        //     title: "Barcode",
                        //     field: "Barcode",
                        //     editor: "list",
                        //     editorParams: {
                        //         values: ["", "M", "A"],
                        //         autocomplete: true,
                        //         allowEmpty: true,
                        //         listOnEmpty: true
                        //     },
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Done",
                        //     field: "Done",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },


                        // {
                        //     title: "Dispatch Date",
                        //     field: "Dispatch_Date",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        // {
                        //     title: "Weight",
                        //     field: "Weight",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        {
                            title: "L CTN",
                            field: "Length",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        {
                            title: "W CTN",
                            field: "Width",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        {
                            title: "H CTN",
                            field: "Height",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        {
                            title: "Qty CTN",
                            field: "Quantity_in_each_box",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        {
                            title: "GW CTN",
                            field: "GW_CTN",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        // {
                        //     title: "Sent Quantity",
                        //     field: "Total_quantity_sent",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        {
                            title: "Send <br> Cost",
                            field: "Send_Cost",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return `
                                    <span>${value.toFixed(2)}</span>
                                    <i class="fa fa-info-circle text-primary send-cost-toggle-btn" 
                                        style="cursor:pointer; margin-left:8px;" 
                                        title="Toggle CTN columns"></i>
                                `;
                            }
                        },
                        {
                            title: "Comm %",
                            field: "Commission_Percentage",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Ratings",
                            field: "Ratings",
                            hozAlign: "center",
                            editor: "input",
                            tooltip: "Enter rating between 0 and 5"
                        },

                        // {
                        //     title: "Warehouse INV Reduction",
                        //     field: "Warehouse_INV_Reduction",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        {
                            title: "CTN cost",
                            field: "Shipping_Amount",
                            hozAlign: "center",
                            visible: false,
                            editor: "input"
                        },
                        // {
                        //     title: "Inbound Quantity",
                        //     field: "Inbound_Quantity",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },

                        // {
                        //     title: "FBA Send",
                        //     field: "FBA_Send",
                        //     hozAlign: "center",
                        //     formatter: "tickCross",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },

                        // {
                        //     title: "L x W x H",
                        //     field: "Dimensions",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },

                        // {
                        //     title: "History",
                        //     field: "history",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = cell.getValue();
                        //         return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openYearsModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                        //     }
                        // },


                        // {
                        //     title: "Jan",
                        //     field: "Jan",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Feb",
                        //     field: "Feb",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Mar",
                        //     field: "Mar",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Apr",
                        //     field: "Apr",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "May",
                        //     field: "May",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Jun",
                        //     field: "Jun",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Jul",
                        //     field: "Jul",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Aug",
                        //     field: "Aug",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Sep",
                        //     field: "Sep",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Oct",
                        //     field: "Oct",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Nov",
                        //     field: "Nov",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Dec",
                        //     field: "Dec",
                        //     hozAlign: "center"
                        // }

                       

                    ]
                });

                table.on('cellEdited', function(cell) {
                    var row = cell.getRow();
                    var data = row.getData();
                    var field = cell.getColumn().getField();
                    var value = cell.getValue();

                    // Validate ratings field (must be between 0 and 5)
                    if (field === 'Ratings') {
                        var numValue = parseFloat(value);
                        if (isNaN(numValue) || numValue < 0 || numValue > 5) {
                            alert('Ratings must be a number between 0 and 5');
                            cell.setValue(data.Ratings || 0); // Revert to original value
                            return; // Don't proceed with AJAX call
                        }
                        value = numValue; // Ensure it's a number
                    }

                    if (field === 'Barcode' || field === 'Done' || field === 'Listed' || field === 'Live' ||
                        field === 'Dispatch_Date' || field === 'Weight' || field ===
                        'Quantity_in_each_box' ||
                        field === 'Total_quantity_sent' ||
                        field === 'Commission_Percentage' || field === 'Ratings' || field === 'TCOS_Percentage' ||
                        field === 'Warehouse_INV_Reduction' || field === 'Shipping_Amount' || field ===
                        'Inbound_Quantity' || field === 'FBA_Send' || field === 'Dimensions' || field ===
                        'FBA_Fee_Manual') {
                        $.ajax({
                            url: '/update-fba-sku-manual-data',
                            method: 'POST',
                            data: {
                                sku: data.FBA_SKU,
                                field: field.toLowerCase(),
                                value: value,
                                fulfillment_fee: parseFloat(data.Fulfillment_Fee) || 0,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                console.log('Data saved successfully');
                                if (response.updatedRow) {
                                    row.update(response.updatedRow);
                                }

                                // Tabulator ke internal real row data ko update kar do
                                row.update({
                                    [field.toUpperCase()]: value, // Tabulator display
                                    [field]: value // backend JSON key
                                });

                                let d = row.getData();

                                let PRICE = parseFloat(d.FBA_Price) || 0;
                                let LP = parseFloat(d.LP) || 0;
                                let COMMISSION_PERCENTAGE = parseFloat(d.Commission_Percentage) ||
                                    0;

                                // Get FBA_SHIP from response or existing row data
                                let FBA_SHIP = parseFloat(response.updatedRow?.FBA_SHIP ?? d
                                    .FBA_Ship_Calculation ?? 0);

                                console.log('GPFT Calculation:', {
                                    PRICE: PRICE,
                                    LP: LP,
                                    COMMISSION_PERCENTAGE: COMMISSION_PERCENTAGE,
                                    FBA_SHIP: FBA_SHIP,
                                    from_response: response.updatedRow?.FBA_SHIP,
                                    from_row: d.FBA_Ship_Calculation
                                });

                                // Initialize update object
                                let updateData = {
                                    FBA_Ship_Calculation: FBA_SHIP
                                };

                                // Calculate values based on which field was edited
                                if (field === 'Commission_Percentage') {
                                    // Only GPFT and TPFT depend on commission
                                    let GPFT = 0;
                                    if (PRICE > 0) {
                                        GPFT = ((PRICE * (1 - (COMMISSION_PERCENTAGE / 100 +
                                                0.05)) -
                                            LP - FBA_SHIP) / PRICE);
                                    }
                                    let TPFT = GPFT - parseFloat(d.TCOS_Percentage || 0);

                                    updateData['GPFT%'] = `${(GPFT*100).toFixed(1)} %`;
                                    updateData['TPFT'] = TPFT;

                                    console.log('Commission edited - Updated GPFT:', GPFT, 'TPFT:',
                                        TPFT);

                                } else if (field === 'TCOS_Percentage') {
                                    // Only TPFT depends on TCOS percentage
                                    let TPFT = GPFT - parseFloat(d.TCOS_Percentage || 0);
                                    updateData['TPFT'] = TPFT;

                                    console.log('TCOS edited - Updated TPFT:', TPFT);

                                } else {
                                    // Other fields affect PFT, ROI, GPFT, TPFT
                                    let PFT = 0;
                                    if (PRICE > 0) {
                                        PFT = (((PRICE * 0.66) - LP - FBA_SHIP) / PRICE);
                                    }

                                    let ROI = 0;
                                    if (LP > 0) {
                                        ROI = (((PRICE * 0.66) - LP - FBA_SHIP) / LP);
                                    }

                                    let GPFT = 0;
                                    if (PRICE > 0) {
                                        GPFT = ((PRICE * (1 - (COMMISSION_PERCENTAGE / 100 +
                                                0.05)) -
                                            LP - FBA_SHIP) / PRICE);
                                    }

                                    let TPFT = GPFT - parseFloat(d.TCOS_Percentage || 0);

                                    updateData['Gpft'] = `${(PFT*100).toFixed(1)} %`;
                                    updateData['ROI%'] = (ROI * 100).toFixed(1);
                                    updateData['GPFT%'] = `${(GPFT*100).toFixed(1)} %`;
                                    updateData['TPFT'] = TPFT;

                                    console.log('Other field edited - Updated all calculations');
                                }

                                row.update(updateData);
                            },
                            error: function(xhr) {
                                console.error('Error saving data');
                            }
                        });
                    }
                });

                function calculateRowValues(rowData) {
                    let PRICE = parseFloat(rowData.PRICE) || 0;
                    let LP = parseFloat(rowData.LP) || 0;

                    let fbaFee = parseFloat(rowData.FBA_Fee_Manual) || 0;
                    let sendCost = parseFloat(rowData.Send_Cost) || 0;

                    // FBA_SHIP calculation
                    let FBA_SHIP = fbaFee + sendCost;

                    // PFT calculation
                    let PFT = 0;
                    if (PRICE > 0) {
                        PFT = (((PRICE * 0.66) - LP - FBA_SHIP) / PRICE).toFixed(1);
                    }

                    return {
                        FBA_SHIP,
                        PFT
                    };
                }

                function updateSummary() {
                    const data = table.getData().filter(row => !row.is_parent); // Exclude parent rows
                    let totalTcos = 0;
                    let totalSpendL30 = 0;
                    let totalPftAmt = 0;
                    let totalSalesAmt = 0;
                    let totalLpAmt = 0;
                    let totalFbaInv = 0;
                    let totalFbaL30 = 0;
                    let totalDilPercent = 0;
                    let dilCount = 0;
                    let zeroSoldSkuCount = 0;

                    data.forEach(row => {
                        if (parseFloat(row.FBA_Quantity) > 0) {
                            totalTcos += parseFloat(row.TCOS_Percentage || 0);
                            totalSpendL30 += parseFloat(row.Total_Spend_L30 || 0);
                            totalPftAmt += parseFloat(row.PFT_AMT || 0);
                            totalSalesAmt += parseFloat(row.SALES_AMT || 0);
                            totalLpAmt += parseFloat(row.LP_AMT || 0);
                            totalFbaInv += parseFloat(row.FBA_Quantity || 0);
                            totalFbaL30 += parseFloat(row.l30_units || 0);
                            
                            const dil = parseFloat(row.FBA_Dil || 0);
                            if (!isNaN(dil)) {
                                totalDilPercent += dil;
                                dilCount++;
                            }
                            
                            // Count SKUs with 0 L30 units sold
                            const l30Units = parseFloat(row.l30_units || 0);
                            if (l30Units === 0) {
                                zeroSoldSkuCount++;
                            }
                        }
                    });

                    let totalWeightedPrice = 0;
                    let totalL30 = 0;
                    data.forEach(row => {
                        if (parseFloat(row.FBA_Quantity) > 0) {
                            const price = parseFloat(row.FBA_Price) || 0;
                            const l30 = parseFloat(row.l30_units) || 0;
                            totalWeightedPrice += price * l30;
                            totalL30 += l30;
                        }
                    });
                    const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
                    $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice));

                    let totalViews = 0;
                    data.forEach(row => {
                        if (parseFloat(row.FBA_Quantity) > 0) {
                            totalViews += parseFloat(row.Current_Month_Views) || 0;
                        }
                    });
                    const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;
                    $('#avg-cvr-badge').text('Avg CVR: ' + avgCVR.toFixed(1) + '%');
                    $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                    

                    $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos) + '%');
                    $('#total-spend-l30-badge').text('Total Spend L30: $' + Math.round(totalSpendL30));
                    $('#total-pft-amt-summary-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                    $('#total-sales-amt-summary-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                    $('#total-cogs-amt-badge').text('COGS AMT: $' + Math.round(totalLpAmt));
                    const roiPercent = totalLpAmt > 0 ? Math.round((totalPftAmt / totalLpAmt) * 100) : 0;
                    $('#roi-percent-badge').text('ROI %: ' + roiPercent + '%');
                    $('#total-fba-inv-badge').text('Total FBA INV: ' + Math.round(totalFbaInv).toLocaleString());
                    $('#total-fba-l30-badge').text('Total FBA L30: ' + Math.round(totalFbaL30).toLocaleString());
                    const avgDilPercent = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
                    $('#avg-dil-percent-badge').text('DIL %: ' + Math.round(avgDilPercent) + '%');
                    $('#zero-sold-sku-count-badge').text('0 Sold SKU: ' + zeroSoldSkuCount);
                    $('#total-pft-amt').text('$' + Math.round(totalPftAmt));
                    $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                    $('#total-sales-amt').text('$' + Math.round(totalSalesAmt));
                    $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                    const avgGpft = totalSalesAmt > 0 ? Math.round((totalPftAmt / totalSalesAmt) * 100) : 0;
                    $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft + '%');
                    $('#avg-gpft-summary').text(avgGpft + '%');
                }


                // INV 0 and More than 0 Filter
                function applyFilters() {
                    const inventoryFilter = $('#inventory-filter').val();
                    const parentFilter = $('#parent-filter').val();
                    const pftFilter = $('#pft-filter').val();
                    const gpftFilter = $('#gpft-filter').val();
                    const cvrFilter = $('#cvr-filter').val();
                    const statusFilter = $('#status-filter').val();

                    table.clearFilter(true);

                    if (inventoryFilter === 'zero') {
                        table.addFilter('FBA_Quantity', '=', 0);
                    } else if (inventoryFilter === 'more') {
                        table.addFilter('FBA_Quantity', '>', 0);
                    }

                    if (parentFilter === 'hide') {
                        table.addFilter(function(data) {
                            return data.is_parent !== true;
                        });
                    }

                    if (pftFilter !== 'all') {
                        table.addFilter(function(data) {
                            const value = parseFloat(data['Gpft']);
                            if (isNaN(value)) return false;

                            switch (pftFilter) {
                                case '0-10':
                                    return value >= 0 && value <= 10;
                                case '11-14':
                                    return value >= 11 && value <= 14;
                                case '15-20':
                                    return value >= 15 && value <= 20;
                                case '21-49':
                                    return value >= 21 && value <= 49;
                                case '50+':
                                    return value >= 50;
                                default:
                                    return true;
                            }
                        });
                    }

                    if (gpftFilter !== 'all') {
                        table.addFilter(function(data) {
                            if (data.is_parent) return true;
                            const gpftStr = data['GPFT%'] || '';
                            const gpft = parseFloat(gpftStr.replace('%', '').replace(/<[^>]*>/g, '')) || 0;
                            
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
                            if (data.is_parent) return true;
                            // Extract CVR from FBA_CVR HTML
                            const cvrHtml = data['FBA_CVR'] || '';
                            const cvrMatch = cvrHtml.match(/(\d+\.?\d*)%/);
                            const cvr = cvrMatch ? parseFloat(cvrMatch[1]) : 0;
                            
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

                    if (statusFilter !== 'all') {
                        table.addFilter(function(data) {
                            if (data.is_parent) return true;
                            
                            const status = data.SPRICE_STATUS || null;
                            
                            if (statusFilter === 'not_pushed') {
                                return status === null || status === '' || status === 'error';
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
                }

                $('#inventory-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                $('#parent-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                $('#pft-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                $('#gpft-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                $('#cvr-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                $('#status-filter').on('change', function() {
                    applyFilters();
                    updateSummary();
                });

                // AJAX Import Handler
                $('#importForm').on('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData();
                    const file = $('#csvFile')[0].files[0];

                    if (!file) return;

                    formData.append('file', file);
                    formData.append('_token', '{{ csrf_token() }}');

                    const uploadBtn = $('#uploadBtn');
                    uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                    $.ajax({
                        url: '/fba-manual-import',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                showToast(response.message, 'success');
                                $('#importModal').modal('hide');
                                $('#importForm')[0].reset();
                                table.setData('/fba-data-json');
                                updateSummary();
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Import failed';
                            showToast(errorMsg, 'error');
                        },
                        complete: function() {
                            uploadBtn.prop('disabled', false).html(
                                '<i class="fa fa-upload"></i> Import');
                        }
                    });
                });

                // Build Column Visibility Dropdown
                function buildColumnDropdown() {
                    const menu = document.getElementById("column-dropdown-menu");
                    menu.innerHTML = '';

                    // Fetch saved visibility from server
                    fetch('/fba-column-visibility', {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(savedVisibility => {
                            const columns = table.getColumns().filter(col => col.getField());

                            columns.forEach(col => {
                                const field = col.getField();
                                const title = col.getDefinition().title || field;
                                const isVisible = savedVisibility[field] !== undefined ? savedVisibility[
                                    field] : col.isVisible();

                                const li = document.createElement('li');
                                li.classList.add('px-3', 'py-1');

                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.classList.add('form-check-input', 'me-2');
                                checkbox.checked = isVisible;
                                checkbox.dataset.field = field;

                                const label = document.createElement('label');
                                label.classList.add('form-check-label');
                                label.style.cursor = 'pointer';
                                label.textContent = title;

                                label.prepend(checkbox);
                                li.appendChild(label);
                                menu.appendChild(li);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching column visibility:', error);
                            // Fallback to default behavior
                            const columns = table.getColumns().filter(col => col.getField());
                            columns.forEach(col => {
                                const field = col.getField();
                                const title = col.getDefinition().title || field;
                                const isVisible = col.isVisible();

                                const li = document.createElement('li');
                                li.classList.add('px-3', 'py-1');

                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.classList.add('form-check-input', 'me-2');
                                checkbox.checked = isVisible;
                                checkbox.dataset.field = field;

                                const label = document.createElement('label');
                                label.classList.add('form-check-label');
                                label.style.cursor = 'pointer';
                                label.textContent = title;

                                label.prepend(checkbox);
                                li.appendChild(label);
                                menu.appendChild(li);
                            });
                        });
                }

                function saveColumnVisibilityToServer() {
                    const visibility = {};
                    table.getColumns().forEach(col => {
                        const field = col.getField();
                        if (field) {
                            visibility[field] = col.isVisible();
                        }
                    });

                    fetch('/fba-column-visibility', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                visibility: visibility
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('Failed to save column visibility');
                            }
                        })
                        .catch(error => {
                            console.error('Error saving column visibility:', error);
                        });
                }

                function applyColumnVisibilityFromServer() {
                    // Columns that should always be hidden by default (Pft% related columns and CTN columns)
                    const alwaysHiddenColumns = ["ROI%", "SPft%", "SROI%", "lmp_1", "Length", "Width", "Height", "Quantity_in_each_box", "GW_CTN", "Shipping_Amount"];
                    
                    fetch('/fba-column-visibility', {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(savedVisibility => {
                            table.getColumns().forEach(col => {
                                const field = col.getField();
                                if (field) {
                                    // Force hide Pft% and CTN related columns (ignore saved preferences)
                                    if (alwaysHiddenColumns.includes(field)) {
                                        col.hide();
                                    } else if (savedVisibility[field] !== undefined) {
                                        // Apply saved preferences for other columns
                                        if (savedVisibility[field]) {
                                            col.show();
                                        } else {
                                            col.hide();
                                        }
                                    }
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Error applying column visibility:', error);
                        });
                }

                // Wait for table to be built, then apply saved visibility and build dropdown
                table.on('tableBuilt', function() {
                    applyColumnVisibilityFromServer();
                    buildColumnDropdown();
                    applyFilters(); // Apply default filters on load
                    updateSummary();
                    
                    // Set up periodic background retry check (every 30 seconds)
                    setInterval(() => {
                        backgroundRetryFailedSkus();
                    }, 30000);
                });

                table.on('dataLoaded', function() {
                    updateSummary();
                    // Sync checkboxes with selectedSkus
                    table.getRows().forEach(tableRow => {
                        const rowData = tableRow.getData();
                        if (!rowData.is_parent) {
                            const checkbox = $(tableRow.getElement()).find('.sku-select-checkbox');
                            if (checkbox.length) {
                                checkbox.prop('checked', selectedSkus.has(rowData.SKU));
                            }
                        }
                    });
                    updateSelectAllCheckbox();
                });

                // Toggle column from dropdown
                document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                    if (e.target.type === 'checkbox') {
                        const field = e.target.dataset.field;
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

                // Show All Columns button
                document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                    // Columns that should always be hidden (Pft% related columns and CTN columns)
                    const alwaysHiddenColumns = ["ROI%", "SPft%", "SROI%", "lmp_1", "Length", "Width", "Height", "Quantity_in_each_box", "GW_CTN", "Shipping_Amount"];
                    
                    table.getColumns().forEach(col => {
                        const field = col.getField();
                        // Don't show Pft% and CTN related columns even when "Show All" is clicked
                        if (field && !alwaysHiddenColumns.includes(field)) {
                            col.show();
                        }
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                });

                // Send Cost Toggle Event Listener
                $(document).on('click', '.send-cost-toggle-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    console.log('Send Cost toggle clicked, table:', table);
                    let colsToToggle = ["Length", "Width", "Height", "Quantity_in_each_box", "GW_CTN", "Shipping_Amount"];

                    colsToToggle.forEach(colName => {
                        try {
                            let col = table.getColumn(colName);
                            if (col) {
                                console.log('Toggling CTN column:', colName);
                                col.toggle();
                            } else {
                                console.warn('CTN column not found:', colName);
                            }
                        } catch (err) {
                            console.error('Error toggling CTN column', colName, err);
                        }
                    });
                });
            });

            // Copy SKU to clipboard
            $(document).on('click', '.copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).data('sku');
                
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
            });

            // LMP Modal Event Listener
            $(document).on('click', '.lmp-link', function(e) {
                e.preventDefault();
                const sku = $(this).data('sku');
                let data = $(this).data('lmp-data');
                console.log('SKU:', sku);
                console.log('Raw data:', data);
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
                    }
                    console.log('Parsed data:', data);
                    openLmpModal(sku, data);
                } catch (error) {
                    console.error('Error parsing LMP data:', error);
                    alert('Error loading LMP data');
                }
            });

            // LMP Modal Function
            function openLmpModal(sku, data) {
                console.log('Opening modal for SKU:', sku, 'Data length:', data.length);
                console.log('lmpDataList exists:', $('#lmpDataList').length);
                $('#lmpSku').text(sku);
                let html = '';
                data.forEach(item => {
                    console.log('Item:', item);
                    html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price: $${item.price}</strong><br>
                    <a href="${item.link}" target="_blank">View Link</a>
                    ${item.image ? `<br><img src="${item.image}" alt="Product Image" style="max-width: 100px; max-height: 100px;">` : ''}
                </div>`;
                });
                console.log('Generated HTML:', html);
                $('#lmpDataList').html(html);
                $('#lmpModal').appendTo('body').modal('show');
                console.log('Modal shown');
            }

            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                'SKU': 'Child SKU',
                'FBA_SKU': 'FBA SKU',
                'FBA_Quantity': 'FBA INV',
                'l60_units': 'L60 Units',
                'l30_units': 'L30 Units',
                'FBA_Dil': 'FBA Dil',
                'FBA_Price': 'FBA Price',
                'GPFT%': 'GPFT%',
                'GROI%': 'GROI%',
                'TCOS_Percentage': 'TACOS',
                'TPFT': 'PRFT%',
                'ROI': 'ROI%',
                'S_Price': 'S Price',
                'SPFT': 'SPft%',
                'SROI%': 'SROI%',
                'SGPFT%': 'SGPFT%',
                'LP': 'LP',
                'FBA_Ship_Calculation': 'FBA Ship',
                'FBA_CVR': 'FBA CVR',
                'Current_Month_Views': 'Views',
                'Inv_age': 'Inv age',
                'lmp_1': 'LMP',
                'Fulfillment_Fee': 'FBA Fee',
                'FBA_Fee_Manual': 'FBA Fee M',
                'Send_Cost': 'Send Cost',
                'Commission_Percentage': 'Comm %',
                'Ratings': 'Ratings'
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
                const exportUrl = `/fba-manual-export?columns=${columnsParam}`;
                
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
        </script>
    @endsection
