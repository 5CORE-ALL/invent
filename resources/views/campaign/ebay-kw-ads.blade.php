@extends('layouts.vertical', ['title' => 'EBAY KEYWORDS ADS', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
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
        .parent-row-bg{
            background-color: #c3efff !important;
        }
        #campaignChart {
            height: 500px !important;
        }
        /* Popup wrapper */
        .daterangepicker {
            border-radius: 10px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0px 6px 18px rgba(0,0,0,0.15) !important;
            font-family: "Inter", "Roboto", sans-serif !important;
            padding: 12px;
        }

        /* Range list */
        .daterangepicker .ranges {
            width: 180px;
            border-right: 1px solid #eee;
            padding-right: 10px;
        }
        .daterangepicker .ranges ul {
            padding: 0;
            margin: 0;
        }
        .daterangepicker .ranges li {
            padding: 10px 12px;
            border-radius: 6px;
            margin: 2px 0;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .daterangepicker .ranges li:hover {
            background: #f3f4f6;
        }
        .daterangepicker .ranges li.active {
            background: #3bc0c3;
            color: white;
            font-weight: 600;
        }

        /* Calendars */
        .daterangepicker .drp-calendar {
            padding: 10px;
        }
        .daterangepicker td.active, 
        .daterangepicker td.active:hover {
            background-color: #3bc0c3 !important;
            color: #fff !important;
            border-radius: 6px !important;
        }
        .daterangepicker td.in-range {
            background-color: #dbeafe !important;
            color: #111 !important;
        }
        .daterangepicker td.start-date, 
        .daterangepicker td.end-date {
            border-radius: 6px !important;
            background: #3bc0c3 !important;
            color: #fff !important;
        }

        /* Buttons */
        .daterangepicker .drp-buttons {
            padding: 8px 12px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        .daterangepicker .drp-buttons .btn {
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 13px;
        }
        .daterangepicker .drp-buttons .btn-primary {
            background-color: #3bc0c3;
            border: none;
        }
        .daterangepicker .drp-buttons .btn-default {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'EBAY KEYWORDS ADS',
        'sub_title' => 'EBAY KEYWORDS ADS',
    ])
    
    <!-- Stats and Chart Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <button id="daterange-btn" class="btn btn-outline-dark">
                            <span>Date range: Select</span> <i class="fa-solid fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                    <!-- Stats Row -->
                    <div class="row text-center mb-4">
                        <!-- Clicks -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Clicks</div>
                                <div class="h3 mb-0 fw-bold text-primary card-clicks">0</div>
                            </div>
                        </div>

                        <!-- Spend -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Spent</div>
                                <div class="h3 mb-0 fw-bold text-success card-spend">$0.00</div>
                            </div>
                        </div>

                        <!-- Ad Sales -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Ad Sales</div>
                                <div class="h3 mb-0 fw-bold text-info card-ad-sales">$0.00</div>
                            </div>
                        </div>

                        <!-- Ad Sold -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Ad Sold</div>
                                <div class="h3 mb-0 fw-bold text-warning card-ad-sold">0</div>
                            </div>
                        </div>

                        <!-- ACOS -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">ACOS</div>
                                <div class="h3 mb-0 fw-bold text-danger card-acos">0%</div>
                            </div>
                        </div>

                        <!-- CVR -->
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">CVR</div>
                                        <div class="h3 mb-0 fw-bold text-secondary card-cvr">0%</div>
                                    </div>
                                    <!-- Arrow button -->
                                    <button id="toggleChartBtn" class="btn btn-sm btn-info ms-2">
                                        <i id="chartArrow" class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart (hidden by default) -->
                    <div id="chartContainer" style="display: none;">
                        <canvas id="campaignChart" height="120"></canvas>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            EBAY KEYWORDS ADS
                        </h4>

                        <div class="row g-3 mb-3">
                            <!-- Left side: Filters and Buttons -->
                            <div class="col-md-8">
                                <!-- Filters Row -->
                                <div class="d-flex gap-2 mb-3">
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="acos-filter" class="form-select form-select-md">
                                        <option value="">Select ACOS</option>
                                        <option value="PINK">PINK</option>
                                        <option value="GREEN">GREEN</option>
                                        <option value="RED">RED</option>
                                    </select>

                                    <select id="cvr-filter" class="form-select form-select-md">
                                        <option value="">Select CVR</option>
                                        <option value="PINK">PINK</option>
                                        <option value="GREEN">GREEN</option>
                                        <option value="RED">RED</option>
                                    </select>
                                </div>
                                
                                <!-- Search and Status Row -->
                                <div class="d-flex gap-2">
                                    <input type="text" id="global-search" class="form-control form-control-md" placeholder="Search campaign...">
                                    <select id="status-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All Status</option>
                                        <option value="RUNNING" selected>Running</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                    <a href="javascript:void(0)" id="export-btn" class="btn btn-success d-flex align-items-center justify-content-center" style="width: 180px;">
                                        <i class="fas fa-file-export me-1"></i> Export Excel
                                    </a>
                                </div>
                            </div>

                            <!-- Right side: Stats with Chart Button -->
                            <div class="col-md-4">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex gap-2">
                                        <div class="text-center p-2 bg-light rounded flex-fill">
                                            <small class="d-block text-dark mb-1" style="font-size: 0.9rem;">Total</small>
                                            <h4 id="total-campaigns" class="fw-bold text-dark mb-0">0</h4>
                                        </div>
                                        <div class="text-center p-2 bg-light rounded flex-fill">
                                            <small class="d-block text-dark mb-1" style="font-size: 0.9rem;">Filtered</small>
                                            <h4 id="percentage-campaigns" class="fw-bold text-dark mb-0">0%</h4>
                                        </div>
                                    </div>
                                    <button class="btn btn-light w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#chartModal" style="color: black;">
                                        <i class="fa fa-chart-pie me-2"></i> View Distribution
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3 align-items-center" style="display: none;">
                            <!-- Hidden - moved above -->
                        </div>
                    </div>


                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Modal -->
    <div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartModalLabel">
                        <i class="fa fa-chart-pie me-2"></i> 7 UB% Distribution
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mx-auto">
                            <canvas id="acosColorPieChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-around">
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div style="width: 20px; height: 20px; background-color: #05bd30; border-radius: 3px; margin-right: 8px;"></div>
                                        <strong>Green (70-90%)</strong>
                                    </div>
                                    <h4 id="green-count" class="text-success mb-0">0</h4>
                                    <small class="text-muted">campaigns</small>
                                </div>
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div style="width: 20px; height: 20px; background-color: #ff01d0; border-radius: 3px; margin-right: 8px;"></div>
                                        <strong>Pink (>90%)</strong>
                                    </div>
                                    <h4 id="pink-count" class="mb-0" style="color: #ff01d0;">0</h4>
                                    <small class="text-muted">campaigns</small>
                                </div>
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        <div style="width: 20px; height: 20px; background-color: #ff2727; border-radius: 3px; margin-right: 8px;"></div>
                                        <strong>Red (<70%)</strong>
                                    </div>
                                    <h4 id="red-count" class="text-danger mb-0">0</h4>
                                    <small class="text-muted">campaigns</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        // Chart initialization
        const ctx = document.getElementById('campaignChart').getContext('2d');

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dates) !!},
                datasets: [
                    {
                        label: 'Clicks',
                        data: {!! json_encode($clicks) !!},
                        borderColor: 'purple',
                        backgroundColor: 'rgba(128, 0, 128, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    },
                    {
                        label: 'Spent (USD)',
                        data: {!! json_encode($spend) !!},
                        borderColor: 'teal',
                        backgroundColor: 'rgba(0, 128, 128, 0.1)',
                        yAxisID: 'y2',
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false,
                    },
                    {
                        label: 'Ad Sales (USD)',
                        data: {!! json_encode($adSales) !!},
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0, 0, 255, 0.1)',
                        yAxisID: 'y2',
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false,
                    },
                    {
                        label: 'Ad Sold',
                        data: {!! json_encode($adSold) !!},
                        borderColor: 'orange',
                        backgroundColor: 'rgba(255, 165, 0, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    },
                    {
                        label: 'ACOS (%)',
                        data: {!! json_encode($acos) !!},
                        borderColor: 'red',
                        backgroundColor: 'rgba(255, 0, 0, 0.1)',
                        yAxisID: 'y3',
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false,
                    },
                    {
                        label: 'CVR (%)',
                        data: {!! json_encode($cvr) !!},
                        borderColor: 'green',
                        backgroundColor: 'rgba(0, 128, 0, 0.1)',
                        yAxisID: 'y3',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
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
                    tooltip: {
                        backgroundColor: "#fff",
                        titleColor: "#111",
                        bodyColor: "#333",
                        borderColor: "#ddd",
                        borderWidth: 1,
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (context.dataset.label.includes("Spent") || context.dataset.label.includes("Sales")) {
                                    return `${context.dataset.label}: $${Number(value).toFixed(2)}`;
                                }
                                if (context.dataset.label.includes("ACOS") || context.dataset.label.includes("CVR")) {
                                    return `${context.dataset.label}: ${Number(value).toFixed(2)}%`;
                                }
                                return `${context.dataset.label}: ${value}`;
                            }
                        }
                    },
                    legend: {
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            padding: 20
                        },
                        onClick: (e, legendItem, legend) => {
                            const index = legendItem.datasetIndex;
                            const ci = legend.chart;
                            const meta = ci.getDatasetMeta(index);
                            meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                            ci.update();
                        }
                    }
                },
                scales: {
                    y1: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Clicks / Ad Sold'
                        }
                    },
                    y2: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Spent / Sales (USD)'
                        }
                    },
                    y3: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'ACOS / CVR (%)'
                        },
                        offset: true
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Toggle chart visibility
        document.getElementById('toggleChartBtn').addEventListener('click', function() {
            const container = document.getElementById('chartContainer');
            const arrow = document.getElementById('chartArrow');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
            } else {
                container.style.display = 'none';
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            }
        });

        // Initialize date range picker
        const startDate = moment().subtract(29, 'days');
        const endDate = moment();

        $('#daterange-btn').daterangepicker({
            startDate: startDate,
            endDate: endDate,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: {
                format: 'YYYY-MM-DD'
            }
        }, function(start, end, label) {
            $('#daterange-btn span').html('Date range: ' + start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
            
            // Fetch filtered data
            fetch('/ebay/keywords/ads/filter?startDate=' + start.format('YYYY-MM-DD') + '&endDate=' + end.format('YYYY-MM-DD'))
                .then(response => response.json())
                .then(data => {
                    // Update chart
                    chart.data.labels = data.dates;
                    chart.data.datasets[0].data = data.clicks;
                    chart.data.datasets[1].data = data.spend;
                    chart.data.datasets[2].data = data.ad_sales;
                    chart.data.datasets[3].data = data.ad_sold;
                    chart.data.datasets[4].data = data.acos;
                    chart.data.datasets[5].data = data.cvr;
                    chart.update();

                    // Update stat cards
                    document.querySelector('.card-clicks').textContent = data.totals.clicks.toLocaleString();
                    document.querySelector('.card-spend').textContent = '$' + Number(data.totals.spend).toFixed(0);
                    document.querySelector('.card-ad-sales').textContent = '$' + Number(data.totals.ad_sales).toFixed(0);
                    document.querySelector('.card-ad-sold').textContent = data.totals.ad_sold.toLocaleString();
                    
                    const acosVal = data.totals.ad_sales > 0 ? (data.totals.spend / data.totals.ad_sales * 100) : 0;
                    document.querySelector('.card-acos').textContent = acosVal.toFixed(0) + '%';
                    
                    const cvrVal = data.totals.clicks > 0 ? (data.totals.ad_sold / data.totals.clicks * 100) : 0;
                    document.querySelector('.card-cvr').textContent = cvrVal.toFixed(1) + '%';
                })
                .catch(error => console.error('Error fetching filtered data:', error));
        });

        // Set initial text for date range button
        $('#daterange-btn span').html('Date range: ' + startDate.format('YYYY-MM-DD') + ' - ' + endDate.format('YYYY-MM-DD'));

        // Calculate and display initial totals
        const initialClicks = {!! json_encode(array_sum($clicks)) !!};
        const initialSpend = {!! json_encode(array_sum($spend)) !!};
        const initialAdSales = {!! json_encode(array_sum($adSales)) !!};
        const initialAdSold = {!! json_encode(array_sum($adSold)) !!};
        
        document.querySelector('.card-clicks').textContent = initialClicks.toLocaleString();
        document.querySelector('.card-spend').textContent = '$' + Number(initialSpend).toFixed(0);
        document.querySelector('.card-ad-sales').textContent = '$' + Number(initialAdSales).toFixed(0);
        document.querySelector('.card-ad-sold').textContent = initialAdSold.toLocaleString();
        
        const initialAcos = initialAdSales > 0 ? (initialSpend / initialAdSales * 100) : 0;
        document.querySelector('.card-acos').textContent = initialAcos.toFixed(0) + '%';
        
        const initialCvr = initialClicks > 0 ? (initialAdSold / initialClicks * 100) : 0;
        document.querySelector('.card-cvr').textContent = initialCvr.toFixed(1) + '%';
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/ebay/keywords/ads/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = (data.sku || "").toLowerCase().trim();

                    if (sku.includes("parent ")) {
                        row.getElement().classList.add("parent-row-bg");
                    }
                },
                columns: [
                    {
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: false
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const data = row.getData();
                            const sku = data.sku;
                            let value = cell.getValue();

                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);
                            let color = "";
                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                color = getDilColor(dilDecimal);
                            }

                            if (color === "pink") {
                                value = "NRA";
                            }

                            let bgColor = "";
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "RA") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
                            } else if (value === "LATER") {
                                bgColor = "background-color:#ffc107;color:#000;"; // yellow
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 100px; ${bgColor}">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                    },
                    {
                        title: "7 UB%",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l7 = parseFloat(row.spend_l7) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (spend_l7 / (budget * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                            }

                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "IMP L30",
                        field: "impressions_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_l30 = cell.getValue();
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary impressions_l30_btn" 
                                    data-impression-l30="${impressions_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "IMP L60",
                        field: "impressions_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "IMP L15",
                        field: "impressions_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "IMP L7",
                        field: "impressions_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_l7 = cell.getValue();
                            return `
                                <span>${impressions_l7}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L30",
                        field: "clicks_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                                <i class="fa fa-info-circle text-primary clicks_l30_btn" 
                                data-clicks-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Clicks L60",
                        field: "clicks_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L15",
                        field: "clicks_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L7",
                        field: "clicks_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L30",
                        field: "spend_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary spend_l30_btn" 
                                data-spend-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Spend L60",
                        field: "spend_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L15",
                        field: "spend_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L7",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L30",
                        field: "ad_sales_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sales_l30_btn" 
                                    data-ad_sales-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Ad Sales L60",
                        field: "ad_sales_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L15",
                        field: "ad_sales_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L7",
                        field: "ad_sales_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L30",
                        field: "ad_sold_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sold_l30_btn" 
                                    data-ad_sold-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Ad Sold L60",
                        field: "ad_sold_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L15",
                        field: "ad_sold_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L7",
                        field: "ad_sold_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L30",
                        field: "acos_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_l30 || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "red";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            } else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                                <i class="fa fa-info-circle text-primary acos_l30_btn" 
                                    data-acos-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "acos_l60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_l60 || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "red";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L15",
                        field: "acos_l15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_l15 || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "red";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L7",
                        field: "acos_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_l7 || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "red";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CPC L30",
                        field: "cpc_l30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l30 = parseFloat(row.cpc_l30) || 0;

                            return `
                                <span>
                                    ${cpc_l30.toFixed(2)}
                                </span>
                                <i class="fa fa-info-circle text-primary cpc_l30_btn" 
                                    data-cpc-l30="${cpc_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CPC L60",
                        field: "cpc_l60",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l60 = parseFloat(row.cpc_l60) || 0;
                            return cpc_l60.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: "CPC L15",
                        field: "cpc_l15",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l15 = parseFloat(row.cpc_l15) || 0;
                            return cpc_l15.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: "CPC L7",
                        field: "cpc_l7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l7 = parseFloat(row.cpc_l7) || 0;
                            return `
                                <span>
                                    ${cpc_l7.toFixed(2)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L30",
                        field: "cvr_l30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l30 = parseFloat(row.ad_sold_l30) || 0;
                            var clicks_l30 = parseFloat(row.clicks_l30) || 0;
                            
                            var cvr_l30 = (clicks_l30 > 0) ? (ad_sold_l30 / clicks_l30) * 100 : 0;
                            let color = "";
                            if (cvr_l30 < 5) {
                                color = "red";
                            } else if (cvr_l30 >= 5 && cvr_l30 <= 10) {
                                color = "green";
                            } else if (cvr_l30 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l30.toFixed(0)}%
                                </span>
                                <i class="fa fa-info-circle text-primary cvr_l30_btn" 
                                    data-cvr-l30="${cvr_l30}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CVR L60",
                        field: "cvr_l60",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l60 = parseFloat(row.ad_sold_l60) || 0;
                            var clicks_l60 = parseFloat(row.clicks_l60) || 0;
                            
                            var cvr_l60 = (clicks_l60 > 0) ? (ad_sold_l60 / clicks_l60) * 100 : 0;
                            let color = "";
                            if (cvr_l60 < 5) {
                                color = "red";
                            } else if (cvr_l60 >= 5 && cvr_l60 <= 10) {
                                color = "green";
                            } else if (cvr_l60 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l60.toFixed(0)}%
                                </span>
                            `;

                        },
                        visible: false
                    },
                    {
                        title: "CVR L15",
                        field: "cvr_l15",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l15 = parseFloat(row.ad_sold_l15) || 0;
                            var clicks_l15 = parseFloat(row.clicks_l15) || 0;

                            var cvr_l15 = (clicks_l15 > 0) ? (ad_sold_l15 / clicks_l15) * 100 : 0;
                            let color = "";
                            if (cvr_l15 < 5) {
                                color = "red";
                            } else if (cvr_l15 >= 5 && cvr_l15 <= 10) {
                                color = "green";
                            } else if (cvr_l15 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l15.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L7",
                        field: "cvr_l7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l7 = parseFloat(row.ad_sold_l7) || 0;
                            var clicks_l7 = parseFloat(row.clicks_l7) || 0;

                            var cvr_l7 = (clicks_l7 > 0) ? (ad_sold_l7 / clicks_l7) * 100 : 0;
                            let color = "";
                            if (cvr_l7 < 5) {
                                color = "red";
                            } else if (cvr_l7 >= 5 && cvr_l7 <= 10) {
                                color = "green";
                            } else if (cvr_l7 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l7.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-ebay-nr-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
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
                        })
                        .catch(err => console.error(err));
                }
            });

            table.on("tableBuilt", function() {

                function combinedFilter(data) {

                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.sku?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal) {
                        const inv = parseFloat(data.INV || 0);

                        if (invFilterVal === "INV_0" && inv !== 0) {
                            // Show only rows where inventory = 0
                            return false;
                        } 
                        else if (invFilterVal === "OTHERS" && inv === 0) {
                            // Show only rows where inventory > 0
                            return false;
                        } 
                        else if (invFilterVal === "ALL") {
                            // Show all — no filter
                        }
                    }

                    let acosFilterVal = $("#acos-filter").val();
                    if (acosFilterVal) {
                        let acosFields = ["acos_l30"];

                        let matched = acosFields.every(field => {
                            let val = parseFloat(data[field]) || 0;

                            if (acosFilterVal === "PINK") {
                                return val < 7;
                            }
                            if (acosFilterVal === "GREEN") {
                                return val >= 7 && val <= 14;
                            }
                            if (acosFilterVal === "RED") {
                                return val > 14;
                            }
                            return false;
                        });

                        if (!matched) return false;
                    }


                    let cvrFilterVal = $("#cvr-filter").val();
                    if (cvrFilterVal) {
                        let cvrFields = ["cvr_l30"];

                        let matched = cvrFields.every(field => {
                            let val = parseFloat(data[field]) || 0;

                            if (cvrFilterVal === "PINK") {
                                return val > 10;
                            }
                            if (cvrFilterVal === "GREEN") {
                                return val >= 5 && val <= 10;
                            }
                            if (cvrFilterVal === "RED") {
                                return val < 5;
                            }
                            return false;
                        });

                        if (!matched) return false;
                    }

                    return true;
                }

                table.setFilter(combinedFilter);

                // Initialize pie chart
                let acosColorChart = null;
                const ctx = document.getElementById('acosColorPieChart');
                
                if (ctx) {
                    acosColorChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Green (70-90%)', 'Pink (>90%)', 'Red (<70%)'],
                            datasets: [{
                                data: [0, 0, 0],
                                backgroundColor: [
                                    '#05bd30',
                                    '#ff01d0',
                                    '#ff2727'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 10,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            let value = context.parsed || 0;
                                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return label + ': ' + value + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                function updateCampaignStats() {
                    let allRows = table.getData();
                    let filteredRows = allRows.filter(combinedFilter);

                    let total = allRows.length;
                    let filtered = filteredRows.length;

                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    const totalEl = document.getElementById("total-campaigns");
                    const percentageEl = document.getElementById("percentage-campaigns");

                    if (totalEl) totalEl.innerText = filtered;
                    if (percentageEl) percentageEl.innerText = percentage + "%";

                    // Update pie chart with 7 UB% color distribution
                    if (acosColorChart) {
                        let greenCount = 0;
                        let pinkCount = 0;
                        let redCount = 0;

                        filteredRows.forEach(row => {
                            let spend_l7 = parseFloat(row.spend_l7 || 0);
                            let budget = parseFloat(row.campaignBudgetAmount || 0);
                            let ub7 = budget > 0 ? (spend_l7 / (budget * 7)) * 100 : 0;

                            if (ub7 >= 70 && ub7 <= 90) {
                                greenCount++;
                            } else if (ub7 > 90) {
                                pinkCount++;
                            } else if (ub7 < 70) {
                                redCount++;
                            }
                        });

                        acosColorChart.data.datasets[0].data = [greenCount, pinkCount, redCount];
                        acosColorChart.update();

                        // Update count displays in modal
                        document.getElementById('green-count').innerText = greenCount;
                        document.getElementById('pink-count').innerText = pinkCount;
                        document.getElementById('red-count').innerText = redCount;
                    }
                }

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", function() {
                    table.setFilter(combinedFilter);
                });

                $("#status-filter,#inv-filter,#acos-filter,#cvr-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "NR",];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("impressions_l30_btn")) {
                    let colsToToggle = ["impressions_l60", "impressions_l15", "impressions_l7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("clicks_l30_btn")) {
                    let colsToToggle = ["clicks_l15", "clicks_l7", "clicks_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("spend_l30_btn")) {
                    let colsToToggle = ["spend_l15", "spend_l7", "spend_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sales_l30_btn")) {
                    let colsToToggle = ["ad_sales_l15", "ad_sales_l7", "ad_sales_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sold_l30_btn")) {
                    let colsToToggle = ["ad_sold_l15", "ad_sold_l7", "ad_sold_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("acos_l30_btn")) {
                    let colsToToggle = ["acos_l15", "acos_l7", "acos_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cpc_l30_btn")) {
                    let colsToToggle = ["cpc_l15", "cpc_l7", "cpc_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cvr_l30_btn")) {
                    let colsToToggle = ["cvr_l15", "cvr_l7", "cvr_l60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });


            document.getElementById("export-btn").addEventListener("click", function () {
                let filteredData = table.getData("active");

                let exportData = filteredData.map(row => {
                    return {
                        Parent: row.parent || "",
                        SKU: row.sku || "",
                        INV: row.INV || 0,
                        "OV L30": row.L30 || 0,
                        "DIL %": row.INV && row.INV !== 0 ? Math.round((row.L30 / row.INV) * 100) : 0,
                        NRA: row.NR || "",
                        Campaign: row.campaignName || "",
                        Status: row.campaignStatus || "",
                        "7 UB%": row.spend_l7 && row.campaignBudgetAmount ? ((row.spend_l7 / (row.campaignBudgetAmount * 7)) * 100).toFixed(0) : 0,
                        "IMP L30": row.impressions_l30 || 0,
                        "IMP L60": row.impressions_l60 || 0,
                        "IMP L15": row.impressions_l15 || 0,
                        "IMP L7": row.impressions_l7 || 0,
                        "Clicks L30": row.clicks_l30 || 0,
                        "Clicks L60": row.clicks_l60 || 0,
                        "Clicks L15": row.clicks_l15 || 0,
                        "Clicks L7": row.clicks_l7 || 0,
                        "Spend L30": row.spend_l30 || 0,
                        "Spend L60": row.spend_l60 || 0,
                        "Spend L15": row.spend_l15 || 0,
                        "Spend L7": row.spend_l7 || 0,
                        "Ad Sales L30": row.ad_sales_l30 || 0,
                        "Ad Sales L60": row.ad_sales_l60 || 0,
                        "Ad Sales L15": row.ad_sales_l15 || 0,
                        "Ad Sales L7": row.ad_sales_l7 || 0,
                        "Ad Sold L30": row.ad_sold_l30 || 0,
                        "Ad Sold L60": row.ad_sold_l60 || 0,
                        "Ad Sold L15": row.ad_sold_l15 || 0,
                        "Ad Sold L7": row.ad_sold_l7 || 0,
                        "ACOS L30": row.acos_l30 || 0,
                        "ACOS L60": row.acos_l60 || 0,
                        "ACOS L15": row.acos_l15 || 0,
                        "ACOS L7": row.acos_l7 || 0,
                        "CPC L30": row.cpc_l30 || 0,
                        "CPC L60": row.cpc_l60 || 0,
                        "CPC L15": row.cpc_l15 || 0,
                        "CPC L7": row.cpc_l7 || 0,
                        "CVR L30": row.clicks_l30 ? ((row.ad_sold_l30 / row.clicks_l30) * 100).toFixed(2) : 0,
                        "CVR L60": row.clicks_l60 ? ((row.ad_sold_l60 / row.clicks_l60) * 100).toFixed(2) : 0,
                        "CVR L15": row.clicks_l15 ? ((row.ad_sold_l15 / row.clicks_l15) * 100).toFixed(2) : 0,
                        "CVR L7": row.clicks_l7 ? ((row.ad_sold_l7 / row.clicks_l7) * 100).toFixed(2) : 0,
                    };
                });

                if (exportData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_kw_ads_report.xlsx");
            });

            document.body.style.zoom = "78%";
        });
    </script>

@endsection

