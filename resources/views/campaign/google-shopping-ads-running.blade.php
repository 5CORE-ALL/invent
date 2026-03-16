@extends('layouts.vertical', ['title' => 'G-Shopping Ads Running', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
        #campaignChart {
            height: 500px !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'G-Shopping Ads Running',
        'sub_title' => 'G-Shopping Ads Running',
    ])
    <div class="row">
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
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Clicks</div>
                                <div class="h3 mb-0 fw-bold text-primary card-clicks">{{ $clicks->sum() }}</div>
                            </div>
                        </div>

                        <!-- Spend -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Spend</div>
                                <div class="h3 mb-0 fw-bold text-success card-spend">
                                    US${{ number_format($spend->sum(), 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Orders -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Orders</div>
                                <div class="h3 mb-0 fw-bold text-danger card-orders">{{ $orders->sum() }}</div>
                            </div>
                        </div>

                        <!-- Sales -->
                        <div class="col-md-3">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Sales</div>
                                        <div class="h3 mb-0 fw-bold text-info card-sales">
                                            US${{ number_format($sales->sum(), 2) }}
                                        </div>
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

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Google Shopping Ads Running
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="dil-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All DIL%</option>
                                        <option value="RED">Red</option>
                                        <option value="YELLOW">Yellow</option>
                                        <option value="GREEN">Green</option>
                                        <option value="PINK">Pink</option>
                                    </select>


                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nrl-filter" class="form-select form-select-md">
                                        <option value="">Select NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

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
                                        <option value="ENABLED">Enabled</option>
                                        <option value="PAUSED">Paused</option>
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
    
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                if (percent >= 50 && isFinite(percent)) return "pink";
                return '';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/google/shopping/data",
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
                        // visible: false
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        // visible: false
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
                        // visible: false
                    },
                    {
                        title: "NRA",
                        field: "NRA",
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
                                        data-field="NRA"
                                        style="width: 100px; ${bgColor}">
                                    <option value=""></option>
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        // visible: false
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "AD STATUS",
                        field: "status",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue()?.trim();

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="status"
                                        style="width: 100px;">
                                    <option value="" selected></option>
                                    <option value="ENABLED" ${value === 'ENABLED' ? 'selected' : ''}>RUNNING</option>
                                    <option value="PAUSED" ${value === 'PAUSED' ? 'selected' : ''}>PAUSED</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        // visible: false
                    },
                    
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });


            table.on("tableBuilt", function () {

                function combinedFilter(data) {
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal) {
                        let campaignMatch = data.campaignName?.toLowerCase().includes(searchVal);
                        let skuMatch = data.sku?.toLowerCase().includes(searchVal);

                        if (!(campaignMatch || skuMatch)) {
                            return false;
                        }
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.status !== statusVal) {
                        return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal === "INV_0") {
                        if (parseFloat(data.INV) !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }

                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowSelect = getRowSelectBySkuAndField(data.sku, "NRL");
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NRL || "";

                        if (rowVal !== nrlFilterVal) return false;
                    }

                    let dilFilterVal = $("#dil-filter").val();
                    if (dilFilterVal) {
                        const dilDecimal = (data.L30 / data.INV);
                        let dilColor = getDilColor(dilDecimal);
                        if (dilFilterVal.toLowerCase() !== dilColor.toLowerCase()) {
                            return false;
                        }
                    }

                    return true;
                }

                function updateCampaignStats() {
                    let total = table.getDataCount();                 
                    let filtered = table.getDataCount("active");      
                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    const totalEl = document.getElementById("total-campaigns");
                    const percentageEl = document.getElementById("percentage-campaigns");

                    if (totalEl) totalEl.innerText = filtered;
                    if (percentageEl) percentageEl.innerText = percentage + "%";
                }

                function refreshFilters() {
                    table.setFilter(combinedFilter);
                    updateCampaignStats(); 
                }

                table.setFilter(combinedFilter);

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", refreshFilters);
                $("#status-filter, #nrl-filter, #inv-filter, #dil-filter").on("change", refreshFilters);

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "WA_L30", "NRA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            // Safe selector function
            function getRowSelectBySkuAndField(sku, field) {
                try {
                    let escapedSku = CSS.escape(sku); // escape special chars
                    return document.querySelector(`select[data-sku="${escapedSku}"][data-field="${field}"]`);
                } catch (e) {
                    console.warn("Invalid selector for SKU:", sku, e);
                    return null;
                }
            }

            document.body.style.zoom = "78%";
        });
    </script>

    <script>
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
                    label: 'Spend (USD)',
                    data: {!! json_encode($spend) !!},
                    borderColor: 'teal',
                    backgroundColor: 'rgba(0, 128, 128, 0.1)',
                    yAxisID: 'y2',
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: false,
                },
                {
                    label: 'Orders',
                    data: {!! json_encode($orders) !!},
                    borderColor: 'magenta',
                    backgroundColor: 'rgba(255, 0, 255, 0.1)',
                    yAxisID: 'y1',
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: false,
                },
                {
                    label: 'Sales (USD)',
                    data: {!! json_encode($sales) !!},
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0, 0, 255, 0.1)',
                    yAxisID: 'y2',
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
                            if (context.dataset.label.includes("Spend") || context.dataset.label.includes("Sales")) {
                                return `${context.dataset.label}: $${Number(value).toFixed(2)}`;
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
                        text: 'Clicks / Orders'
                    }
                },
                y2: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Spend / Sales (USD)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById("toggleChartBtn");
        const chartContainer = document.getElementById("chartContainer");
        const arrowIcon = document.getElementById("chartArrow");

        toggleBtn.addEventListener("click", function() {
            if (chartContainer.style.display === "none") {
                chartContainer.style.display = "block";
                arrowIcon.classList.remove("fa-chevron-down");
                arrowIcon.classList.add("fa-chevron-up");
            } else {
                chartContainer.style.display = "none";
                arrowIcon.classList.remove("fa-chevron-up");
                arrowIcon.classList.add("fa-chevron-down");
            }
        });
    });

    $(function() {
        let picker = $('#daterange-btn').daterangepicker({
            opens: 'right',
            autoUpdateInput: false,
            alwaysShowCalendars: true,
            locale: {
                format: "D MMM YYYY",
                cancelLabel: 'Clear'
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            const startDate = start.format("YYYY-MM-DD");
            const endDate   = end.format("YYYY-MM-DD");

            $('#daterange-btn span').html("Date range: " + startDate + " - " + endDate);
            fetchChartData(startDate, endDate);
        });

        // Reset on cancel
        $('#daterange-btn').on('cancel.daterangepicker', function(ev, picker) {
            $(this).find('span').html("Date range: Select");
            fetchChartData(); 
        });

    });

    function fetchChartData(startDate, endDate) {
        $.ajax({
            url: "{{ route('google.shopping.running.chart.filter') }}",
            type: "GET",
            data: { startDate, endDate },
            success: function(response) {
                const formattedDates = response.dates.map(d => moment(d).format('MMM DD'));
                chart.data.labels = formattedDates;
                chart.data.datasets[0].data = response.clicks;
                chart.data.datasets[1].data = response.spend;
                chart.data.datasets[2].data = response.orders;
                chart.data.datasets[3].data = response.sales;
                chart.update();

                $('.card-clicks').text(response.totals.clicks);
                $('.card-spend').text('US$' + Number(response.totals.spend).toFixed(2));
                $('.card-orders').text(response.totals.orders);
                $('.card-sales').text('US$' + Number(response.totals.sales).toFixed(2));
            }
        });
    }

    </script>
@endsection
