@extends('layouts.vertical', ['title' => 'Ebay - UNDER UTILIZED', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        'page_title' => 'Ebay - UNDER UTILIZED',
        'sub_title' => 'Ebay - UNDER UTILIZED',
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
                                <div class="h3 mb-0 fw-bold text-success card-spend">$0</div>
                            </div>
                        </div>

                        <!-- Ad Sales -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Ad Sales</div>
                                <div class="h3 mb-0 fw-bold text-info card-ad-sales">$0</div>
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
                            Ebay - UNDER UTILIZED
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
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
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
                                        <option value="ARCHIVED">Archived</option>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

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
                    url: '{{ route("ebay-under-uti.filter") }}',
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
                        const avgAcos = totalAdSales > 0 ? ((totalSpend / totalAdSales) * 100) : 0;
                        const avgCvr = totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;

                        document.querySelector('.card-clicks').innerHTML = Math.round(totalClicks).toLocaleString();
                        document.querySelector('.card-spend').innerHTML = '$' + Math.round(totalSpend).toLocaleString();
                        document.querySelector('.card-ad-sales').innerHTML = '$' + Math.round(totalAdSales).toLocaleString();
                        document.querySelector('.card-ad-sold').innerHTML = Math.round(totalAdSold).toLocaleString();
                        document.querySelector('.card-acos').innerHTML = (isNaN(avgAcos) ? 0 : avgAcos.toFixed(1)) + '%';
                        document.querySelector('.card-cvr').innerHTML = (isNaN(avgCvr) ? 0 : avgCvr.toFixed(1)) + '%';
                    }
                });
            });

            $('#daterange-btn span').html('Date range: ' + start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

            // Campaign chart handler
            let singleCampaignChart = null;
            $(document).on('click', '.campaign-chart-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const campaignName = $(this).data('campaign');
                
                console.log('Campaign chart button clicked for:', campaignName);
                
                // Always show last 30 days for campaign chart
                const endDate = moment().format('YYYY-MM-DD');
                const startDate = moment().subtract(30, 'days').format('YYYY-MM-DD');
                
                console.log('Date range:', startDate, 'to', endDate);
                
                $.ajax({
                    url: '{{ route("ebay-under-uti.campaign-chart") }}',
                    method: 'GET',
                    data: { 
                        campaignName: campaignName,
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
                                        borderColor: 'rgba(59, 130, 246, 1)',
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        yAxisID: 'y',
                                        tension: 0.3
                                    },
                                    {
                                        label: 'Spend ($)',
                                        data: response.spend,
                                        borderColor: 'rgba(34, 197, 94, 1)',
                                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                        yAxisID: 'y',
                                        tension: 0.3
                                    },
                                    {
                                        label: 'Ad Sales ($)',
                                        data: response.ad_sales,
                                        borderColor: 'rgba(99, 102, 241, 1)',
                                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                        yAxisID: 'y',
                                        tension: 0.3
                                    },
                                    {
                                        label: 'Ad Sold',
                                        data: response.ad_sold,
                                        borderColor: 'rgba(251, 146, 60, 1)',
                                        backgroundColor: 'rgba(251, 146, 60, 0.1)',
                                        yAxisID: 'y1',
                                        tension: 0.3
                                    },
                                    {
                                        label: 'ACOS (%)',
                                        data: response.acos,
                                        borderColor: 'rgba(239, 68, 68, 1)',
                                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                        yAxisID: 'y2',
                                        tension: 0.3
                                    },
                                    {
                                        label: 'CVR (%)',
                                        data: response.cvr,
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
                        
                        // Show modal after chart is created
                        $('#campaignChartModal').modal('show');
                    },
                    error: function(xhr, status, error) {
                        console.error('Campaign chart AJAX error:', error, xhr.responseText);
                        alert('Error loading campaign chart: ' + error);
                    }
                });
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
                ajaxURL: "/ebay-uti-acos/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
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
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();
                            const bgColor = value === 'NRA' ? 'red-bg' : 'green-bg';
                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 90px;">
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
                        formatter: function(cell) {
                            const campaignName = cell.getValue();
                            return `
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <span>${campaignName}</span>
                                    <button class="btn btn-sm btn-outline-primary campaign-chart-btn" 
                                            data-campaign="${campaignName}"
                                            title="View Chart">
                                        <i class="fa-solid fa-chart-line"></i>
                                    </button>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0).toFixed(2)
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acosRaw = row.acos; 
                            var acos = parseFloat(acosRaw);

                            if (isNaN(acos)) {
                                acos = 0;
                            }

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');

                            if (acos === 0) {
                                td.classList.add('red-bg');
                                return "100%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                            }

                            return acos.toFixed(0) + "%";
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
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
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
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
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
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var sbid = 0;

                            if (ub7 < 10) {
                                sbid = 0.50;
                            }else if(l7_cpc > 0) {
                                sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                            }else if(l1_cpc > 0) {
                                sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                            }
                            
                            sbid = sbid.toFixed(2);
                            return sbid;
                        },
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
                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;

                                var sbid = 0;
                                if (ub7 < 10) {
                                    sbid = 0.50;
                                }else if(l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                }else if(l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                                }
                                sbid = sbid.toFixed(2);
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
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
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
                    let acos = parseFloat(data.acos || 0);
                    let budget = parseFloat(data.campaignBudgetAmount) || 0;
                    let l7_spend = parseFloat(data.l7_spend) || 0;
                    let l1_spend = parseFloat(data.l1_spend) || 0;

                    let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                    let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                    if (!(ub7 < 70 && ub1 < 70)) return false;

                    // price < 30
                    let price = parseFloat(data.price || 0);
                    if (price < 30) {
                        return false;
                    }

                    // Show only INV > 0 rows
                    let inv = parseFloat(data.INV || 0);
                    if (inv <= 0) {
                        return false;
                    }

                    // Pink DIL filter (exclude pink rows)
                    let l30 = parseFloat(data.L30);
                    let dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                    let dilColor = getDilColor(dilDecimal);
                    if (dilColor === "pink") return false;

                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    // Status filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    // Inventory filter
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

                    // NR filter (use only data object)
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowVal = data.NR || "";
                        if (rowVal !== nraFilterVal) return false;
                    }

                    return true;
                }

                table.setFilter(combinedFilter);

                function updateCampaignStats() {
                    let allData = table.getData(); // poora data
                    let filteredCount = allData.filter(combinedFilter).length; // apply same filter function

                    let total = allData.length;
                    let percentage = total > 0 ? ((filteredCount / total) * 100).toFixed(0) : 0;

                    document.getElementById("total-campaigns").innerText = filteredCount; // filtered rows count
                    document.getElementById("percentage-campaigns").innerText = percentage + "%";
                }

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", function() {
                    table.setFilter(combinedFilter);
                    updateCampaignStats(); // update count immediately
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                    updateCampaignStats(); // update count immediately
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "NR"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();

                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if(rowEl && rowEl.offsetParent !== null){
                        
                        var rowData = row.getData();
                        var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                        var l7_cpc = parseFloat(rowData.l7_cpc) || 0;

                        var sbid = 0;
                        if (ub7 < 10) {
                            sbid = 0.50;
                        }else if(l7_cpc > 0) {
                            sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                        }else if(l1_cpc > 0) {
                            sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                        }
                        
                        sbid = sbid.toFixed(2);

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
                    let l1_cpc = parseFloat(row.l1_cpc || 0);
                    let l7_cpc = parseFloat(row.l7_cpc || 0);
                    let sbid = 0;

                    if(l7_cpc == 0) {
                        sbid = 0.75;
                    }else{
                        sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                    }
                    sbid = sbid.toFixed(2);

                    return {
                        campaignName: row.campaignName || "",
                        sbid: sbid
                    };
                });

                if (exportData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_over_acos_pink.xlsx");
            });



            document.body.style.zoom = "78%";
        });
    </script>
@endsection
