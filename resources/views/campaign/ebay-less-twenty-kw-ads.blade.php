@extends('layouts.vertical', ['title' => 'Ebay Kw Ads - Price < $30', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay Kw Ads - Price < $30',
        'sub_title' => 'Ebay Kw Ads - Price < $30',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                <i class="fa-solid fa-chart-line me-2"></i>
                                Ebay Kw Ads - Price < $30
                            </h4>
                            <div class="badge bg-primary fs-5 px-3 py-2">
                                Total Campaigns: <span id="total-campaigns">0</span>
                            </div>
                        </div>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nra-filter" class="form-select form-select-md">
                                        <option value="">Select NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button id="export-btn" class="btn btn-success btn-sm">
                                        <i class="fa fa-file-excel"></i> Export to Excel
                                    </button>
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search campaign...">
                                    </div>
                                    <select id="status-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All Status</option>
                                        <option value="RUNNING" selected>Running</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card text-center bg-light-primary">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-mouse-pointer text-primary fs-1 mb-2"></i>
                                    <h4 class="card-clicks fw-bold">0</h4>
                                    <p class="mb-0 text-muted">Total Clicks</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-light-success">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-dollar-sign text-success fs-1 mb-2"></i>
                                    <h4 class="card-spend fw-bold">$0</h4>
                                    <p class="mb-0 text-muted">Total Spend</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-light-info">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-chart-line text-info fs-1 mb-2"></i>
                                    <h4 class="card-ad-sales fw-bold">$0</h4>
                                    <p class="mb-0 text-muted">Ad Sales</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-light-warning">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-shopping-cart text-warning fs-1 mb-2"></i>
                                    <h4 class="card-ad-sold fw-bold">0</h4>
                                    <p class="mb-0 text-muted">Units Sold</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-light-danger">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-percentage text-danger fs-1 mb-2"></i>
                                    <h4 class="card-acos fw-bold">0%</h4>
                                    <p class="mb-0 text-muted">Avg ACOS</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card text-center bg-light-secondary">
                                <div class="card-body p-3">
                                    <i class="fa-solid fa-exchange-alt text-secondary fs-1 mb-2"></i>
                                    <h4 class="card-cvr fw-bold">0%</h4>
                                    <p class="mb-0 text-muted">Avg CVR</p>
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Chart Container -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Performance Chart</h5>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="text" id="daterangepicker" class="form-control" style="width: 250px;" placeholder="Select Date Range">
                                            <button class="btn btn-outline-primary btn-sm" onclick="toggleChart()">
                                                <i class="fa-solid fa-eye" id="chart-toggle-icon"></i> 
                                                <span id="chart-toggle-text">Show Chart</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body" id="chart-container" style="display: none;">
                                        <canvas id="performanceChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>

    <!-- Campaign Chart Modal -->
    <div class="modal fade" id="campaignChartModal" tabindex="-1" aria-labelledby="campaignChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignChartModalLabel">Campaign Performance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div style="height: 400px; position: relative;">
                        <canvas id="singleCampaignChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Moment.js for date handling -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <!-- Date Range Picker -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.zoom = "85%";

            // Chart data from backend
            const chartDates = @json($dates ?? []);
            const chartClicks = @json($clicks ?? []);
            const chartSpend = @json($spend ?? []);
            const chartAdSales = @json($ad_sales ?? []);
            const chartAdSold = @json($ad_sold ?? []);
            const chartAcos = @json($acos ?? []);
            const chartCvr = @json($cvr ?? []);

            // Calculate initial totals with proper number handling
            const totalClicks = chartClicks.reduce((a, b) => Number(a) + Number(b), 0);
            const totalSpend = chartSpend.reduce((a, b) => Number(a) + Number(b), 0);
            const totalAdSales = chartAdSales.reduce((a, b) => Number(a) + Number(b), 0);
            const totalAdSold = chartAdSold.reduce((a, b) => Number(a) + Number(b), 0);
            const avgAcos = totalAdSales > 0 ? ((totalSpend / totalAdSales) * 100) : 0;
            const avgCvr = totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;

            // Update cards with initial data (format numbers properly)
            document.querySelector('.card-clicks').innerHTML = Math.round(totalClicks).toLocaleString();
            document.querySelector('.card-spend').innerHTML = '$' + Math.round(totalSpend).toLocaleString();
            document.querySelector('.card-ad-sales').innerHTML = '$' + Math.round(totalAdSales).toLocaleString();
            document.querySelector('.card-ad-sold').innerHTML = Math.round(totalAdSold).toLocaleString();
            document.querySelector('.card-acos').innerHTML = (isNaN(avgAcos) ? 0 : avgAcos.toFixed(1)) + '%';
            document.querySelector('.card-cvr').innerHTML = (isNaN(avgCvr) ? 0 : avgCvr.toFixed(1)) + '%';

            // Main Chart initialization
            const ctx = document.getElementById('performanceChart').getContext('2d');
            let mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [
                        {
                            label: 'Clicks',
                            data: chartClicks,
                            borderColor: 'rgba(59, 130, 246, 1)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            yAxisID: 'y',
                            tension: 0.3
                        },
                        {
                            label: 'Spend ($)',
                            data: chartSpend,
                            borderColor: 'rgba(34, 197, 94, 1)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            yAxisID: 'y',
                            tension: 0.3
                        },
                        {
                            label: 'Ad Sales ($)',
                            data: chartAdSales,
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            yAxisID: 'y',
                            tension: 0.3
                        },
                        {
                            label: 'Ad Sold',
                            data: chartAdSold,
                            borderColor: 'rgba(251, 146, 60, 1)',
                            backgroundColor: 'rgba(251, 146, 60, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.3
                        },
                        {
                            label: 'ACOS (%)',
                            data: chartAcos,
                            borderColor: 'rgba(239, 68, 68, 1)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            yAxisID: 'y2',
                            tension: 0.3
                        },
                        {
                            label: 'CVR (%)',
                            data: chartCvr,
                            borderColor: 'rgba(168, 85, 247, 1)',
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            yAxisID: 'y2',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0 || context.datasetIndex === 3) {
                                        label += context.parsed.y.toLocaleString();
                                    } else if (context.datasetIndex === 4 || context.datasetIndex === 5) {
                                        label += context.parsed.y.toFixed(2) + '%';
                                    } else {
                                        label += '$' + context.parsed.y.toFixed(2);
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Clicks / Spend / Sales ($)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Ad Sold (Units)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y2: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'ACOS / CVR (%)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            // Toggle chart visibility
            document.getElementById('toggleChartBtn')?.addEventListener('click', function() {
                const container = document.getElementById('chartContainer');
                const arrow = document.getElementById('chartArrow');
                
                if (container.style.display === 'none') {
                    container.style.display = 'block';
                    arrow.innerHTML = '▼';
                    if (mainChart) {
                        mainChart.resize();
                    }
                } else {
                    container.style.display = 'none';
                    arrow.innerHTML = '▶';
                }
            });

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/ebay/keywords/ads/less-than-twenty/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                pagination: true,
                paginationSize: 500,
                paginationCounter: "rows",
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["Sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
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
                        title: "E DIL %",
                        field: "E DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.e_l30);
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
                        title: "Price",
                        field: "price",
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
                        field: "campaignName",
                        formatter: function(cell, formatterParams, onRendered) {
                            const campaignName = cell.getValue();
                            return `
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <span>${campaignName}</span>
                                    <button class="btn btn-sm btn-outline-primary campaign-chart-btn" 
                                            data-campaign-name="${campaignName}"
                                            title="View Chart">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </button>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 60 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 60) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    }, {
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget ) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 60 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 60) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sbid = row.sbid || 0;
                            return sbid.toFixed(2);
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        formatter: function(cell, formatterParams, onRendered) {
                            var value = cell.getValue() || 0;
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-row-btn">APR BID</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-row-btn")) {
                                var rowData = cell.getRow().getData();
                                var sbid = parseFloat(rowData.sbid) || 0;
                                updateBid(sbid, rowData.campaign_id);
                            }
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    // Update total campaigns count
                    const totalCampaigns = response.data ? response.data.length : 0;
                    document.getElementById('total-campaigns').textContent = totalCampaigns;
                    return response.data;
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

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            table.on("tableBuilt", function () {

                function combinedFilter(data) {

                    // Inventory filter - DEFAULT: Show only INV > 0
                    let invFilterVal = $("#inv-filter").val();
                    
                    // If no filter selected, default to showing only INV > 0
                    if (!invFilterVal || invFilterVal === "") {
                        if (parseFloat(data.INV) <= 0) {
                            return false;
                        }
                    } else if (invFilterVal === "ALL") {
                        // Show all inventory including 0 and negative
                        // No filtering needed
                    } else if (invFilterVal === "INV_0") {
                        // Show only INV = 0
                        if (parseFloat(data.INV) !== 0) {
                            return false;
                        }
                    } else if (invFilterVal === "OTHERS") {
                        // Show only INV > 0
                        if (parseFloat(data.INV) <= 0) {
                            return false;
                        }
                    }

                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    // Status filter - default to RUNNING if no filter has been changed
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    // NR filter
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="NR"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NR || "";

                        if (rowVal !== nraFilterVal) {
                            return false;
                        }
                    }

                    // If none failed, row passes
                    return true;
                }

                // Apply filter initially
                table.setFilter(combinedFilter);

                // Bind events
                $("#global-search").on("keyup", function () {
                    table.setFilter(combinedFilter);
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function () {
                    table.setFilter(combinedFilter);
                });
            });


            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "E DIL %", "NR"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            // Initialize date range picker
            $('#daterangepicker').daterangepicker({
                startDate: moment().subtract(29, 'days'),
                endDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 15 Days': [moment().subtract(14, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, function(start, end, label) {
                console.log('Date range changed:', start.format('YYYY-MM-DD'), 'to', end.format('YYYY-MM-DD'));
                loadFilteredData(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
                updateChartWithDateRange(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
            });

            // Function to toggle main chart visibility
            window.toggleChart = function() {
                const container = document.getElementById('chart-container');
                const icon = document.getElementById('chart-toggle-icon');
                const text = document.getElementById('chart-toggle-text');
                
                if (container.style.display === 'none') {
                    container.style.display = 'block';
                    icon.className = 'fa-solid fa-eye-slash';
                    text.textContent = 'Hide Chart';
                    if (mainChart) {
                        mainChart.resize();
                    }
                } else {
                    container.style.display = 'none';
                    icon.className = 'fa-solid fa-eye';
                    text.textContent = 'Show Chart';
                }
            };

            // Simple date filter functionality
            function loadFilteredData(startDate, endDate) {
                $.ajax({
                    url: '{{ route("ebay.keywords.ads.less-than-twenty.filter") }}',
                    method: 'GET',
                    data: {
                        startDate: startDate,
                        endDate: endDate
                    },
                    success: function(response) {
                        // Update stats cards with filtered totals
                        if (response.totals) {
                            document.querySelector('.card-clicks').innerHTML = Math.round(response.totals.clicks || 0).toLocaleString();
                            document.querySelector('.card-spend').innerHTML = '$' + Math.round(response.totals.spend || 0).toLocaleString();
                            document.querySelector('.card-ad-sales').innerHTML = '$' + Math.round(response.totals.ad_sales || 0).toLocaleString();
                            document.querySelector('.card-ad-sold').innerHTML = Math.round(response.totals.ad_sold || 0).toLocaleString();
                            
                            const avgAcos = response.totals.ad_sales > 0 ? ((response.totals.spend / response.totals.ad_sales) * 100) : 0;
                            const avgCvr = response.totals.clicks > 0 ? ((response.totals.ad_sold / response.totals.clicks) * 100) : 0;
                            
                            document.querySelector('.card-acos').innerHTML = avgAcos.toFixed(1) + '%';
                            document.querySelector('.card-cvr').innerHTML = avgCvr.toFixed(1) + '%';
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Filter error:', error);
                    }
                });
            }

            // Update chart with filtered date range data
            function updateChartWithDateRange(startDate, endDate) {
                $.ajax({
                    url: '{{ route("ebay.keywords.ads.less-than-twenty.filter") }}',
                    method: 'GET',
                    data: {
                        startDate: startDate,
                        endDate: endDate,
                        chartData: true
                    },
                    success: function(response) {
                        if (response.dates && mainChart) {
                            // Update chart data
                            mainChart.data.labels = response.dates;
                            mainChart.data.datasets[0].data = response.clicks || [];
                            mainChart.data.datasets[1].data = response.spend || [];
                            mainChart.data.datasets[2].data = response.ad_sales || [];
                            mainChart.data.datasets[3].data = response.ad_sold || [];
                            mainChart.data.datasets[4].data = response.acos || [];
                            mainChart.data.datasets[5].data = response.cvr || [];
                            
                            // Refresh the chart
                            mainChart.update();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Chart update error:', error);
                    }
                });
            }

            // Campaign chart handler
            let singleCampaignChart = null;
            $(document).on('click', '.campaign-chart-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const campaignName = $(this).data('campaign-name');
                
                console.log('Campaign chart button clicked for:', campaignName);
                
                // Always show last 30 days for campaign chart
                const endDate = moment().format('YYYY-MM-DD');
                const startDate = moment().subtract(30, 'days').format('YYYY-MM-DD');
                
                console.log('Date range:', startDate, 'to', endDate);
                
                $.ajax({
                    url: '{{ route("ebay.keywords.ads.less-than-twenty.campaign-chart") }}',
                    method: 'GET',
                    data: { 
                        campaignId: campaignName,
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        console.log('Campaign chart data received:', response);
                        $('#campaignChartModalLabel').text('Campaign: ' + campaignName + ' (Last 30 Days)');
                        
                        if (singleCampaignChart) {
                            singleCampaignChart.destroy();
                        }
                        
                        const ctx = document.getElementById('singleCampaignChart').getContext('2d');
                        singleCampaignChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: response.dates,
                                datasets: [
                                    {
                                        label: 'Clicks',
                                        data: response.clicks,
                                        borderColor: 'purple',
                                        backgroundColor: 'rgba(128, 0, 128, 0.1)',
                                        yAxisID: 'y'
                                    },
                                    {
                                        label: 'Spend ($)',
                                        data: response.spend,
                                        borderColor: 'red',
                                        backgroundColor: 'rgba(255, 0, 0, 0.1)',
                                        yAxisID: 'y1'
                                    },
                                    {
                                        label: 'Ad Sales ($)',
                                        data: response.ad_sales,
                                        borderColor: 'green',
                                        backgroundColor: 'rgba(0, 128, 0, 0.1)',
                                        yAxisID: 'y1'
                                    },
                                    {
                                        label: 'Units Sold',
                                        data: response.ad_sold,
                                        borderColor: 'orange',
                                        backgroundColor: 'rgba(255, 165, 0, 0.1)',
                                        yAxisID: 'y2'
                                    },
                                    {
                                        label: 'ACOS (%)',
                                        data: response.acos,
                                        borderColor: 'blue',
                                        backgroundColor: 'rgba(0, 0, 255, 0.1)',
                                        yAxisID: 'y3'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: { display: true, text: 'Clicks' }
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: { display: true, text: 'Amount ($)' },
                                        grid: { drawOnChartArea: false }
                                    },
                                    y2: {
                                        type: 'linear',
                                        display: false,
                                        position: 'right'
                                    },
                                    y3: {
                                        type: 'linear',
                                        display: false,
                                        position: 'right'
                                    }
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) label += ': ';
                                                if (context.datasetIndex === 0 || context.datasetIndex === 3) {
                                                    label += context.parsed.y;
                                                } else if (context.datasetIndex === 4) {
                                                    label += context.parsed.y.toFixed(2) + '%';
                                                } else {
                                                    label += '$' + context.parsed.y.toFixed(2);
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        
                        // Show modal after chart is created
                        $('#campaignChartModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        console.error('Campaign chart AJAX error:', error, xhr.responseText);
                        alert('Error loading campaign chart: ' + error);
                    }
                });
            });

            // Initialize campaign count on table built
            table.on("dataLoaded", function() {
                document.getElementById('total-campaigns').textContent = table.getData().filter(row => row.campaignName).length;
            });

            document.getElementById("export-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();

                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if(rowEl && rowEl.offsetParent !== null){
                        
                        var rowData = row.getData();
                        var sbid = parseFloat(rowData.sbid) || 0;

                        campaignIds.push(rowData.campaign_id);
                        bids.push(sbid);
                    }
                });
                console.log("Campaign IDs:", campaignIds);
                console.log("Bids:", bids);
                fetch('/update-ebay-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                            .getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bids: bids
                    })
                })
                .then(res => res.json())
                .then(data => {
                    console.log("Backend response:", data);
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => console.error(err))
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                console.log("Updating bid for Campaign ID:", campaignId, "New Bid:", aprBid);

                fetch('/update-ebay-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bids: [aprBid]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    console.log("Backend response:", data);
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => console.error(err))
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            document.getElementById("export-btn").addEventListener("click", function () {
                let filteredData = table.getData("active");

                let exportData = filteredData.map(row => {
                    let l7_spend = parseFloat(row.l7_spend) || 0;
                    let l1_spend = parseFloat(row.l1_spend) || 0;
                    let budget = parseFloat(row.campaignBudgetAmount) || 0;
                    let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                    let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                    let sbid = parseFloat(row.sbid || 0).toFixed(2);
                    
                    let l30 = parseFloat(row.L30);
                    let inv = parseFloat(row.INV);
                    let dilPercent = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? Math.round((l30 / inv) * 100) + "%" : "0%";
                    
                    let e_l30 = parseFloat(row.e_l30);
                    let eDilPercent = (!isNaN(e_l30) && !isNaN(inv) && inv !== 0) ? Math.round((e_l30 / inv) * 100) + "%" : "0%";

                    return {
                        Parent: row.parent,
                        SKU: row.sku,
                        INV: row.INV || 0,
                        'OV L30': row.L30 || 0,
                        'DIL %': dilPercent,
                        'E DIL %': eDilPercent,
                        Price: row.price,
                        NRA: row.NR || '',
                        Campaign: row.campaignName,
                        '7 UB%': ub7.toFixed(0) + "%",
                        '1 UB%': ub1.toFixed(0) + "%",
                        'L7 CPC': parseFloat(row.l7_cpc || 0).toFixed(2),
                        'L1 CPC': parseFloat(row.l1_cpc || 0).toFixed(2),
                        SBID: sbid,
                        Status: row.campaignStatus
                    };
                });

                if (exportData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_kw_ads_price_less_than_30.xlsx");
            });

        });
    </script>
@endsection
