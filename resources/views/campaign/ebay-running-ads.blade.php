@extends('layouts.vertical', ['title' => 'Ebay Running Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        .parent-row-bg{
            background-color: #c3efff !important;
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
        'page_title' => 'Ebay Running Ads',
        'sub_title' => 'Ebay Running Ads',
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
                            Running Ads
                        </h4>

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
                                    <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center justify-content-center">
                                        <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                    </a>
                                    <button class="btn btn-success btn-md">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        Need to increase bids: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-md">
                                        <i class="fa fa-percent me-1"></i>
                                        of Total: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
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
                                        <option value="RUNNING">Running</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ENDED">Ended</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Chart Modal -->
    <div class="modal fade" id="campaignChartModal" tabindex="-1" aria-labelledby="campaignChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignChartModalLabel">Campaign Performance Chart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <canvas id="singleCampaignChart" style="height: 400px;"></canvas>
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
        document.addEventListener("DOMContentLoaded", function() {

            // Chart data from backend
            const chartDates = @json($dates);
            const chartClicks = @json($clicks);
            const chartSpend = @json($spend);
            const chartAdSales = @json($ad_sales);
            const chartAdSold = @json($ad_sold);
            const chartAcos = @json($acos);
            const chartCvr = @json($cvr);

            // Calculate initial totals
            const totalClicks = chartClicks.reduce((a, b) => a + b, 0);
            const totalSpend = chartSpend.reduce((a, b) => a + b, 0);
            const totalAdSales = chartAdSales.reduce((a, b) => a + b, 0);
            const totalAdSold = chartAdSold.reduce((a, b) => a + b, 0);
            const avgAcos = totalSpend > 0 ? ((totalSpend / totalAdSales) * 100) : 0;
            const avgCvr = totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;

            // Update cards with initial data
            document.querySelector('.card-clicks').innerHTML = totalClicks.toLocaleString();
            document.querySelector('.card-spend').innerHTML = '$' + Math.round(totalSpend).toLocaleString();
            document.querySelector('.card-ad-sales').innerHTML = '$' + Math.round(totalAdSales).toLocaleString();
            document.querySelector('.card-ad-sold').innerHTML = totalAdSold.toLocaleString();
            document.querySelector('.card-acos').innerHTML = Math.round(avgAcos) + '%';
            document.querySelector('.card-cvr').innerHTML = Math.round(avgCvr) + '%';

            // Main Chart initialization
            const ctx = document.getElementById('campaignChart').getContext('2d');
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
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        if (label.includes('$')) {
                                            label += '$' + context.parsed.y.toFixed(2);
                                        } else if (label.includes('%')) {
                                            label += context.parsed.y.toFixed(2) + '%';
                                        } else {
                                            label += context.parsed.y;
                                        }
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

            // Date range picker
            const start = moment().subtract(29, 'days');
            const end = moment();

            $('#daterange-btn').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, function(start, end) {
                $('#daterange-btn span').html('Date range: ' + start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                
                // Fetch filtered data
                $.ajax({
                    url: '{{ route("ebay.running.ads.filter") }}',
                    method: 'GET',
                    data: {
                        startDate: start.format('YYYY-MM-DD'),
                        endDate: end.format('YYYY-MM-DD')
                    },
                    success: function(response) {
                        // Update chart
                        mainChart.data.labels = response.dates;
                        mainChart.data.datasets[0].data = response.clicks;
                        mainChart.data.datasets[1].data = response.spend;
                        mainChart.data.datasets[2].data = response.ad_sales;
                        mainChart.data.datasets[3].data = response.ad_sold;
                        mainChart.data.datasets[4].data = response.acos;
                        mainChart.data.datasets[5].data = response.cvr;
                        mainChart.update();

                        // Update cards
                        const totalClicks = response.totals.clicks || 0;
                        const totalSpend = response.totals.spend || 0;
                        const totalAdSales = response.totals.ad_sales || 0;
                        const totalAdSold = response.totals.ad_sold || 0;
                        const avgAcos = totalSpend > 0 ? ((totalSpend / totalAdSales) * 100) : 0;
                        const avgCvr = totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;

                        document.querySelector('.card-clicks').innerHTML = totalClicks.toLocaleString();
                        document.querySelector('.card-spend').innerHTML = '$' + Math.round(totalSpend).toLocaleString();
                        document.querySelector('.card-ad-sales').innerHTML = '$' + Math.round(totalAdSales).toLocaleString();
                        document.querySelector('.card-ad-sold').innerHTML = totalAdSold.toLocaleString();
                        document.querySelector('.card-acos').innerHTML = Math.round(avgAcos) + '%';
                        document.querySelector('.card-cvr').innerHTML = Math.round(avgCvr) + '%';
                    }
                });
            });

            $('#daterange-btn span').html('Date range: ' + start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay/ad-running/data",
                layout: "fitDataFill",
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
                        title: "Campaign Name",
                        field: "campaignName",
                        formatter: function(cell) {
                            let campaignName = cell.getValue();
                            if (!campaignName) return '';
                            return `
                                <span>${campaignName}</span>
                                <button class="btn btn-sm btn-link p-0 ms-2 campaign-chart-btn" 
                                        data-campaign-name="${campaignName}"
                                        title="View campaign chart">
                                    <i class="fa-solid fa-chart-line text-primary"></i>
                                </button>
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
                        title: "EL 30",
                        field: "e_l30",
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue()?.trim();

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
                        title: 'SPEND L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SPEND_L30",
                        formatter: function(cell) {
                            let SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SPEND_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                                data-spend-l30="${SPEND_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_spend_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SPEND_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_spend_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SPEND_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SPEND L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SPEND_L7",
                        formatter: function(cell) {
                            let SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SPEND_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL7-btn" 
                                data-spend-l7="${SPEND_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_spend_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SPEND_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_spend_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SPEND_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SOLD L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SOLD_L30",
                        formatter: function(cell) {
                            let SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SOLD_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-soldL30-btn" 
                                data-sold-l30="${SOLD_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sold_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SOLD_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sold_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SOLD_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SOLD L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SOLD_L7",
                        formatter: function(cell) {
                            let SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SOLD_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-soldL7-btn" 
                                data-sold-l7="${SOLD_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sold_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SOLD_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sold_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SOLD_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SALES L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SALES_L30",
                        formatter: function(cell) {
                            let SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SALES_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-salesL30-btn" 
                                data-sales-l30="${SALES_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sales_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SALES_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sales_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SALES_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SALES L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SALES_L7",
                        formatter: function(cell) {
                            let SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SALES_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-salesL7-btn" 
                                data-sales-l30="${SALES_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sales_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SALES_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sales_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SALES_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'CLICKS L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "CLICKS_L30",
                        formatter: function(cell) {
                            let CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(CLICKS_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-clicksL30-btn" 
                                data-clicks-l30="${CLICKS_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_clicks_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_CLICKS_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_clicks_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_CLICKS_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'CLICKS L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "CLICKS_L7",
                        formatter: function(cell) {
                            let CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(CLICKS_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-clicksL7-btn" 
                                data-clicks-l7="${CLICKS_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_clicks_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_CLICKS_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_clicks_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_CLICKS_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "IMP_L30",
                        formatter: function(cell) {
                            let IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(IMP_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-impL30-btn" 
                                data-clicks-l7="${IMP_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_impr_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_IMP_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_impr_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_IMP_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "IMP_L7",
                        formatter: function(cell) {
                            let IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(IMP_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-impL7-btn" 
                                data-clicks-l7="${IMP_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_impr_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_IMP_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_impr_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_IMP_L7).toFixed(0)}</span>
                            `;
                        }
                    },                  
                    {
                        title: "START AD",
                        field: "start_ad",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "KW") {
                                bgColor = "background-color:#28a745;color:#fff;";
                            } else if (value === "PMT") {
                                bgColor = "background-color:#28a745;color:#fff;";
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="start_ad"
                                        style="${bgColor}">
                                    <option value=""></option>
                                    <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                    <option value="PMT" ${value === 'PMT' ? 'selected' : ''}>PMT</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "STOP AD",
                        field: "stop_ad",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "KW") {
                                bgColor = "background-color:#dc3545;color:#fff;";
                            } else if (value === "PMT") {
                                bgColor = "background-color:#dc3545;color:#fff;";
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="stop_ad"
                                        style="${bgColor}">
                                    <option value=""></option>
                                    <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                    <option value="PMT" ${value === 'PMT' ? 'selected' : ''}>PMT</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },

                ],
                ajaxResponse: function(url, params, response) {
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

            let initialSpendL30Data = {};

            table.on("dataLoaded", function(data) {
                data.forEach(row => {
                if (row.SPEND_L30 !== undefined) {
                    initialSpendL30Data[row.sku] = row.SPEND_L30;

                    fetch('/update-ebay-nr-data', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        sku: row.sku,
                        field: 'Spend_L30', 
                        value: row.SPEND_L30
                    })
                    })
                    .then(res => res.json())
                    .then(data => {
                    console.log('SPEND_L30 saved for SKU:', row.sku);
                    })
                    .catch(err => {
                    console.error('Error saving SPEND_L30:', err);
                    });
                }
                });
            });

            table.on("tableBuilt", function () {

                function combinedFilter(data) {
                    if (!data) return false;

                    // 🔍 Search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal) {
                        const sku = (data.sku || "").toLowerCase();
                        if (!sku.includes(searchVal)) {
                            return false;
                        }
                    }

                    // 🟢 Status filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    // 📦 Inventory filter (fixed logic)
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

                    // 🧮 NRA filter (fixed select lookup)
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowVal = data.NR || "";
                        // Try to read from select if it's rendered
                        let rowSelect = document.querySelector(`select[data-sku="${data.sku}"][data-field="NR"]`);
                        if (rowSelect && rowSelect.value) {
                            rowVal = rowSelect.value;
                        }

                        if (rowVal !== nraFilterVal) {
                            return false;
                        }
                    }

                    return true;
                }

                // 🧩 Apply combined filter
                table.setFilter(combinedFilter);

                // 📊 Update campaign stats
                function updateCampaignStats() {
                    let allRows = table.getData();
                    let filteredRows = allRows.filter(combinedFilter);

                    let total = allRows.length;
                    let filtered = filteredRows.length;

                    // Improved helper function to handle both numbers and strings
                    const calculateTotal = (field) => {
                        return filteredRows.reduce((sum, row) => {
                            const sku = (row.sku || "").toLowerCase().trim();
                            if (!sku.includes("parent ")) {
                                let value = row[field];
                                // Ensure value is a number
                                if (typeof value === 'string') {
                                    value = parseFloat(value) || 0;
                                }
                                return sum + (value || 0);
                            }
                            return sum;
                        }, 0);
                    };

                    //calculate all total
                    let spendL30Total = calculateTotal('SPEND_L30');
                    let kwSpendL30Total = calculateTotal('kw_spend_L30');
                    let pmtSpendL30Total = calculateTotal('pmt_spend_L30');
                    let spendL7Total = calculateTotal('SPEND_L7');
                    let kwSpendL7Total = calculateTotal('kw_spend_L7');
                    let pmtSpendL7Total = calculateTotal('pmt_spend_L7');
                    let soldL30Total = calculateTotal('SOLD_L30');
                    let kwSoldL30Total = calculateTotal('kw_sold_L30');
                    let pmtSoldL30Total = calculateTotal('pmt_sold_L30');
                    let soldL7Total = calculateTotal('SOLD_L7');
                    let kwSoldL7Total = calculateTotal('kw_sold_L7');
                    let pmtSoldL7Total = calculateTotal('pmt_sold_L7');
                    let salesL30Total = calculateTotal('SALES_L30');
                    let kwSalesL30Total = calculateTotal('kw_sales_L30');
                    let pmtSalesL30Total = calculateTotal('pmt_sales_L30');
                    let salesL7Total = calculateTotal('SALES_L7');
                    let kwSalesL7Total = calculateTotal('kw_sales_L7');
                    let pmtSalesL7Total = calculateTotal('pmt_sales_L7');
                    let clicksL30Total = calculateTotal('CLICKS_L30');
                    let kwClicksL30Total = calculateTotal('kw_clicks_L30');
                    let pmtClicksL30Total = calculateTotal('pmt_clicks_L30');
                    let clicksL7Total = calculateTotal('CLICKS_L7');
                    let kwClicksL7Total = calculateTotal('kw_clicks_L7');
                    let pmtClicksL7Total = calculateTotal('pmt_clicks_L7');
                    let impL30Total = calculateTotal('IMP_L30');
                    let kwImpL30Total = calculateTotal('kw_impr_L30');
                    let pmtImpL30Total = calculateTotal('pmt_impr_L30');
                    let impL7Total = calculateTotal('IMP_L7');
                    let kwImpL7Total = calculateTotal('kw_impr_L7');
                    let pmtImpL7Total = calculateTotal('pmt_impr_L7');

                    $.ajax({
                        url: "{{ route('adv-ebay.ad-running.save-data') }}",
                        method: 'GET',
                        data: {
                            spendL30Total: spendL30Total,
                            kwSpendL30Total: kwSpendL30Total,
                            pmtSpendL30Total:pmtSpendL30Total,
                            clicksL30Total: clicksL30Total,
                            kwClicksL30Total:kwClicksL30Total,
                            pmtClicksL30Total:pmtClicksL30Total,
                            salesL30Total : salesL30Total,
                            kwSalesL30Total:kwSalesL30Total,
                            pmtSalesL30Total:pmtSalesL30Total,
                            soldL30Total:soldL30Total,
                            kwSoldL30Total:kwSoldL30Total,
                            pmtSoldL30Total:pmtSoldL30Total,
                        },
                        success: function(response) {
                        },
                        error: function(xhr) {
                        }
                    });


                document.getElementById("imp-l7-total").innerText = impL7Total > 0 ? ` (${impL7Total.toFixed(2)})` : "";
                document.getElementById("imp-l7-total").style.display = impL7Total > 0 ? "inline" : "none";

                document.getElementById("kw-imp-l7-total").innerText = kwImpL7Total > 0 ? ` (${kwImpL7Total.toFixed(2)})` : "";
                document.getElementById("kw-imp-l7-total").style.display = kwImpL7Total > 0 ? "inline" : "none";

                document.getElementById("pmt-imp-l7-total").innerText = pmtImpL7Total > 0 ? ` (${pmtImpL7Total.toFixed(2)})` : "";
                document.getElementById("pmt-imp-l7-total").style.display = pmtImpL7Total > 0 ? "inline" : "none";


                document.getElementById("imp-l30-total").innerText = impL30Total > 0 ? ` (${impL30Total.toFixed(2)})` : "";
                document.getElementById("imp-l30-total").style.display = impL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-imp-l30-total").innerText = kwImpL30Total > 0 ? ` (${kwImpL30Total.toFixed(2)})` : "";
                document.getElementById("kw-imp-l30-total").style.display = kwImpL30Total > 0 ? "inline" : "none";


                document.getElementById("pmt-imp-l30-total").innerText = pmtImpL30Total > 0 ? ` (${pmtImpL30Total.toFixed(2)})` : "";
                document.getElementById("pmt-imp-l30-total").style.display = pmtImpL30Total > 0 ? "inline" : "none";
                      

                document.getElementById("clicks-l7-total").innerText = clicksL7Total > 0 ? ` (${clicksL7Total.toFixed(2)})` : "";
                document.getElementById("clicks-l7-total").style.display = clicksL7Total > 0 ? "inline" : "none";

                document.getElementById("kw-clicks-l7-total").innerText = kwClicksL7Total > 0 ? ` (${kwClicksL7Total.toFixed(2)})` : "";
                document.getElementById("kw-clicks-l7-total").style.display = kwClicksL7Total > 0 ? "inline" : "none";


                document.getElementById("pmt-clicks-l7-total").innerText = pmtClicksL7Total > 0 ? ` (${pmtClicksL7Total.toFixed(2)})` : "";
                document.getElementById("pmt-clicks-l7-total").style.display = pmtClicksL7Total > 0 ? "inline" : "none";


                document.getElementById("clicks-l30-total").innerText = clicksL30Total > 0 ? ` (${clicksL30Total.toFixed(2)})` : "";
                document.getElementById("clicks-l30-total").style.display = clicksL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-clicks-l30-total").innerText = kwClicksL30Total > 0 ? ` (${kwClicksL30Total.toFixed(2)})` : "";
                document.getElementById("kw-clicks-l30-total").style.display = kwClicksL30Total > 0 ? "inline" : "none";

                document.getElementById("pmt-clicks-l30-total").innerText = pmtClicksL30Total > 0 ? ` (${pmtClicksL30Total.toFixed(2)})` : "";
                document.getElementById("pmt-clicks-l30-total").style.display = pmtClicksL30Total > 0 ? "inline" : "none";


                document.getElementById("sales-l7-total").innerText = salesL7Total > 0 ? ` (${salesL7Total.toFixed(2)})` : "";
                document.getElementById("sales-l7-total").style.display = salesL7Total > 0 ? "inline" : "none";

                document.getElementById("kw-sales-l7-total").innerText = kwSalesL7Total > 0 ? ` (${kwSalesL7Total.toFixed(2)})` : "";
                document.getElementById("kw-sales-l7-total").style.display = kwSalesL7Total > 0 ? "inline" : "none";

                document.getElementById("pmt-sales-l7-total").innerText = pmtSalesL7Total > 0 ? ` (${pmtSalesL7Total.toFixed(2)})` : "";
                document.getElementById("pmt-sales-l7-total").style.display = pmtSalesL7Total > 0 ? "inline" : "none";


                document.getElementById("sales-l30-total").innerText = salesL30Total > 0 ? ` (${salesL30Total.toFixed(2)})` : "";
                document.getElementById("sales-l30-total").style.display = salesL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-sales-l30-total").innerText = kwSalesL30Total > 0 ? ` (${kwSalesL30Total.toFixed(2)})` : "";
                document.getElementById("kw-sales-l30-total").style.display = kwSalesL30Total > 0 ? "inline" : "none";


                document.getElementById("pmt-sales-l30-total").innerText = pmtSalesL30Total > 0 ? ` (${pmtSalesL30Total.toFixed(2)})` : "";
                document.getElementById("pmt-sales-l30-total").style.display = pmtSalesL30Total > 0 ? "inline" : "none";


                document.getElementById("sold-l7-total").innerText = soldL7Total > 0 ? ` (${soldL7Total.toFixed(2)})` : "";
                document.getElementById("sold-l7-total").style.display = soldL7Total > 0 ? "inline" : "none";

                document.getElementById("kw-sold-l7-total").innerText = kwSoldL7Total > 0 ? ` (${kwSoldL7Total.toFixed(2)})` : "";
                document.getElementById("kw-sold-l7-total").style.display = kwSoldL7Total > 0 ? "inline" : "none";

                document.getElementById("pmt-sold-l7-total").innerText = pmtSoldL7Total > 0 ? ` (${pmtSoldL7Total.toFixed(2)})` : "";
                document.getElementById("pmt-sold-l7-total").style.display = pmtSoldL7Total > 0 ? "inline" : "none";


                document.getElementById("sold-l30-total").innerText = soldL30Total > 0 ? ` (${soldL30Total.toFixed(2)})` : "";
                document.getElementById("sold-l30-total").style.display = soldL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-sold-l30-total").innerText = kwSoldL30Total > 0 ? ` (${kwSoldL30Total.toFixed(2)})` : "";
                document.getElementById("kw-sold-l30-total").style.display = kwSoldL30Total > 0 ? "inline" : "none";


                document.getElementById("pmt-sold-l30-total").innerText = pmtSoldL30Total > 0 ? ` (${pmtSoldL30Total.toFixed(2)})` : "";
                document.getElementById("pmt-sold-l30-total").style.display = pmtSoldL30Total > 0 ? "inline" : "none";


                document.getElementById("spend-l30-total").innerText = spendL30Total > 0 ? ` (${spendL30Total.toFixed(2)})` : "";
                document.getElementById("spend-l30-total").style.display = spendL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-spend-l30-total").innerText = kwSpendL30Total > 0 ? ` (${kwSpendL30Total.toFixed(2)})` : "";
                document.getElementById("kw-spend-l30-total").style.display = kwSpendL30Total > 0 ? "inline" : "none";


                document.getElementById("pmt-spend-l30-total").innerText = pmtSpendL30Total > 0 ? ` (${pmtSpendL30Total.toFixed(2)})` : "";
                document.getElementById("pmt-spend-l30-total").style.display = pmtSpendL30Total > 0 ? "inline" : "none";


                document.getElementById("spend-l7-total").innerText = spendL7Total > 0 ? ` (${spendL7Total.toFixed(2)})` : "";
                document.getElementById("spend-l7-total").style.display = spendL7Total > 0 ? "inline" : "none";


                document.getElementById("kw-spend-l7-total").innerText = kwSpendL7Total > 0 ? ` (${kwSpendL7Total.toFixed(2)})` : "";
                document.getElementById("kw-spend-l7-total").style.display = kwSpendL7Total > 0 ? "inline" : "none";


                document.getElementById("pmt-spend-l7-total").innerText = pmtSpendL7Total > 0 ? ` (${pmtSpendL7Total.toFixed(2)})` : "";
                document.getElementById("pmt-spend-l7-total").style.display = pmtSpendL7Total > 0 ? "inline" : "none";

                
                let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;
                document.getElementById("total-campaigns").innerText = filtered;
                document.getElementById("percentage-campaigns").innerText = percentage + "%";

                }

                // 🔁 Update stats on relevant table events
                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                // 🔍 Live search + filter events
                $("#global-search").on("keyup", function () {
                    table.setFilter(combinedFilter);
                    updateCampaignStats();
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function () {
                    table.setFilter(combinedFilter);
                    updateCampaignStats();
                });

                // Initialize stats after build
                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "e_l30", "NR"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spendL30-btn")) {
                    let colsToToggle = ["kw_spend_L30", "pmt_spend_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-spendL7-btn")) {
                    let colsToToggle = ["kw_spend_L7", "pmt_spend_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-soldL30-btn")) {
                    let colsToToggle = ["kw_sold_L30", "pmt_sold_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-soldL7-btn")) {
                    let colsToToggle = ["kw_sold_L7", "pmt_sold_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-salesL30-btn")) {
                    let colsToToggle = ["kw_sales_L30", "pmt_sales_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-salesL7-btn")) {
                    let colsToToggle = ["kw_sales_L7", "pmt_sales_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-clicksL30-btn")) {
                    let colsToToggle = ["kw_clicks_L30", "pmt_clicks_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-clicksL7-btn")) {
                    let colsToToggle = ["kw_clicks_L7", "pmt_clicks_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-impL30-btn")) {
                    let colsToToggle = ["kw_impr_L30", "pmt_impr_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-impL7-btn")) {
                    let colsToToggle = ["kw_impr_L7", "pmt_impr_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                
            });

            document.getElementById("export-btn").addEventListener("click", function () {
                let allData = table.getData("active"); 

                if (allData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let exportData = allData.map(row => ({
                    sku: row.sku,
                    SPEND_L30: row.SPEND_L30.toFixed(2),
                }));

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_ad_running.xlsx");
            });

            // Campaign Chart Handler
            let campaignChart = null;
            
            $(document).on('click', '.campaign-chart-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const campaignName = $(this).data('campaign-name');
                
                // Fetch campaign chart data
                fetch(`/ebay/ad-running/campaign-chart?campaign_name=${encodeURIComponent(campaignName)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Destroy existing chart if any
                        if (campaignChart) {
                            campaignChart.destroy();
                        }

                        // Create new chart
                        const ctx = document.getElementById('singleCampaignChart').getContext('2d');
                        campaignChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.dates,
                                datasets: [
                                    {
                                        label: 'Clicks',
                                        data: data.clicks,
                                        borderColor: 'rgb(153, 102, 255)',
                                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                                        yAxisID: 'y1',
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Spend ($)',
                                        data: data.spend,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        yAxisID: 'y2',
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Ad Sales ($)',
                                        data: data.ad_sales,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        yAxisID: 'y2',
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Ad Sold',
                                        data: data.ad_sold,
                                        borderColor: 'rgb(255, 159, 64)',
                                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                        yAxisID: 'y1',
                                        tension: 0.4
                                    },
                                    {
                                        label: 'ACOS (%)',
                                        data: data.acos,
                                        borderColor: 'rgb(255, 99, 132)',
                                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                        yAxisID: 'y3',
                                        tension: 0.4
                                    },
                                    {
                                        label: 'CVR (%)',
                                        data: data.cvr,
                                        borderColor: 'rgb(75, 192, 75)',
                                        backgroundColor: 'rgba(75, 192, 75, 0.1)',
                                        yAxisID: 'y3',
                                        tension: 0.4
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
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                if (label.includes('$')) {
                                                    label += '$' + context.parsed.y.toFixed(2);
                                                } else if (label.includes('%')) {
                                                    label += context.parsed.y.toFixed(2) + '%';
                                                } else {
                                                    label += context.parsed.y;
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Date'
                                        }
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Clicks / Ad Sold'
                                        }
                                    },
                                    y2: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: 'Spend / Sales ($)'
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    },
                                    y3: {
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

                        // Show modal
                        $('#campaignChartModal').modal('show');
                    })
                    .catch(error => {
                        console.error('Error fetching campaign chart data:', error);
                        alert('Failed to load campaign chart data');
                    });
            });

            document.body.style.zoom = "80%";
        });
    </script>
@endsection
