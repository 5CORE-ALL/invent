@extends('layouts.vertical', ['title' => 'FBA Dispatch Data', 'sidenav' => 'condensed'])

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
    </style>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    <div class="toast-container"></div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>FBA Dispatch Data</h4>
                        <div>
                            <input type="text" id="sku-search" class="form-control form-control-sm me-2"
                                style="width: 200px; display: inline-block;" placeholder="Search SKU or FBA SKU...">
                            <select id="inventory-filter" class="form-select form-select-sm me-2"
                                style="width: auto; display: inline-block;">
                                <option value="all">All Inventory</option>
                                <option value="zero">0 Inventory</option>
                                <option value="more">More than 0</option>
                            </select>
                            <select id="parent-filter" class="form-select form-select-sm me-2"
                                style="width: auto; display: inline-block;">
                                <option value="show">Show Parent</option>
                                <option value="hide">Hide Parent</option>
                            </select>
                            <select id="pft-filter" class="form-select form-select-sm me-2"
                                style="width: auto; display: inline-block;">
                                <option value="all">All Pft%</option>
                                <option value="0-10">0-10%</option>
                                <option value="11-14">11-14%</option>
                                <option value="15-20">15-20%</option>
                                <option value="21-49">21-49%</option>
                                <option value="50+">50%+</option>
                            </select>
                            <a href="{{ url('/fba-manual-sample') }}" class="btn btn-sm btn-info me-2">
                                <i class="fa fa-download"></i> Sample Template
                            </a>
                            <a href="{{ url('/fba-manual-export') }}" class="btn btn-sm btn-success me-2">
                                <i class="fa fa-file-excel"></i>
                            </a>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa fa-columns"></i> Columns
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;">
                                </ul>
                            </div>
                            <button id="show-all-columns-btn" type="button" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="fa fa-eye"></i> Show All
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#importModal">
                                <i class="fa fa-upload"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div id="fba-table-wrapper"
                            style="height: calc(100vh - 200px); display: flex; flex-direction: column;">

                            <!--Table body (scrollable section) -->
                            <div id="fba-table" style="flex: 1;"></div>

                        </div>
                    </div>
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
                            <i class="fa fa-info-circle"></i> CSV must have: SKU, Length, Width, Height, Weight, Qty in each box, Total
                            qty Sent, Total Send Cost, Inbound qty, Send cost, Commission Percentage, S Price, Ratings, Shipment Track Status
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
        <!-- Monthly Sales Modal (Jan-Dec) -->
        <div class="modal fade" id="monthlySalesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width:900px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Monthly Sales for <span id="monthlyModalSku"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="monthlySalesModalBody" style="min-width:300px; overflow-x:auto;">
                        <!-- Content populated by JS -->
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
                                        
                                        if (label.includes('Price')) {
                                            return label + ': $' + value.toFixed(2);
                                        } else if (label.includes('Views')) {
                                            return label + ': ' + value.toLocaleString();
                                        } else if (label.includes('CVR')) {
                                            return label + ': ' + value.toFixed(1) + '%';
                                        } else if (label.includes('TACOS')) {
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
                                $('#chart-no-data-message').show();
                                skuMetricsChart.data.labels = [];
                                skuMetricsChart.data.datasets.forEach(dataset => {
                                    dataset.data = [];
                                });
                                skuMetricsChart.options.plugins.title.text = 'FBA Metrics';
                                skuMetricsChart.update();
                                return;
                            }
                            
                            $('#chart-no-data-message').hide();
                            skuMetricsChart.options.plugins.title.text = `FBA Metrics (${days} Days)`;
                            skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
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

            $(document).ready(function() {
                // Initialize SKU metrics chart
                initSkuMetricsChart();

                // SKU chart days filter
                $('#sku-chart-days-filter').on('change', function() {
                    const days = $(this).val();
                    if (currentSku) {
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
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 7);
                    $('#skuMetricsModal').modal('show');
                });

                const table = new Tabulator("#fba-table", {
                    ajaxURL: "/fba-data-json",
                    layout: "fitData",
                    pagination: true,
                    paginationSize: 50,
                    rowFormatter: function(row) {
                        if (row.getData().is_parent) {
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
                            frozen: true
                        },
                        {
                            title: "Child SKU",
                            field: "SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true
                        },
                        {
                            title: "FBA SKU",
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

                          {
                            title: "Shopify INV",
                            field: "Shopify_INV",
                            hozAlign: "center"
                        },

                        

                        {
                            title: "Ov L30",
                            field: "Shopify_OV_L30",
                            hozAlign: "center",
                        },

                        {
                            title: "Dil",
                            field: "Dil",
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
                            title: "FBA INV",
                            field: "FBA_Quantity",
                            hozAlign: "center"
                        },

                        {
                            title: "L30 FBA",
                            field: "l30_units",
                            hozAlign: "center"
                        },
                        {
                            title: "AMZ L30",
                            field: "AMZ_L30",
                            hozAlign: "center"
                        },
                        {
                            title: "FBA Dil",
                            field: "FBA_Dil",
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
                            title: "Views",
                            field: "Current_Month_Views",
                            hozAlign: "center"
                        },


                        {
                            title: "UPC Codes",
                            field: "UPC_Codes",
                            hozAlign: "center",
                            editor: "input",
                            tooltip: true
                        },


                        {
                            title: "M/A Barcode",
                            field: "Barcode",
                            editor: "list",
                            editorParams: {
                                values: ["", "M", "A"],
                                autocomplete: true,
                                allowEmpty: true,
                                listOnEmpty: true
                            },
                            hozAlign: "center"
                        },


                        {
                            title: "Done",
                            field: "Done",
                            formatter: "tickCross",
                            hozAlign: "center",
                            editor: true,
                            cellClick: function(e, cell) {
                                var currentValue = cell.getValue();
                                cell.setValue(!currentValue);
                            }
                        },


                        {
                            title: "D Date",
                            field: "Dispatch_Date",
                            hozAlign: "center",
                            editor: "input"
                        },

  {
                            title: "Length",
                            field: "Length",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 'length',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    }
                                });
                            }
                        },

                        {
                            title: "Width",
                            field: "Width",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 'width',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    }
                                });
                            }
                        },

                        {
                            title: "Height",
                            field: "Height",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 'height',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table.replaceData();
                                    }
                                });
                            }
                        },


                        {
                            title: "Q in E box",
                            field: "Quantity_in_each_box",
                            hozAlign: "center",
                            editor: "input"
                        },


                        {
                            title: "Sent Quantity",
                            field: "Total_quantity_sent",
                            hozAlign: "center",
                            editor: "input"
                        },

                           {
                            title: "T Sent C",
                            field: "Shipping_Amount",
                            hozAlign: "center",
                            editor: "input"
                        },

                            {
                            title: "W H INV RED",
                            field: "Warehouse_INV_Reduction",
                            formatter: "tickCross",
                            hozAlign: "center",
                            editor: true,
                            cellClick: function(e, cell) {
                                var currentValue = cell.getValue();
                                cell.setValue(!currentValue);
                            }
                        },

                        {
                            title: "Shipment Status",
                            field: "FBA_Shipment_Status",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const status = cell.getValue() || '';
                                if (!status) return '';
                                
                                const statusColors = {
                                    'WORKING': '#FF8C00',
                                    'SHIPPED': '#1E90FF',
                                    'IN_TRANSIT': '#0066CC',
                                    'DELIVERED': '#228B22',
                                    'RECEIVING': '#DAA520',
                                    'CLOSED': '#696969',
                                    'CANCELLED': '#B22222',
                                    'ERROR': '#CC0000'
                                };
                                const color = statusColors[status] || '#000';
                                return `<span style="color: ${color}; font-weight: 700; font-size: 12px;">${status}</span>`;
                            }
                        },

                      

                        {
                            title: "Inbound Quantity",
                            field: "Inbound_Quantity",
                            hozAlign: "center",
                            editor: "input"
                        },


                        {
                            title: "ASIN",
                            field: "ASIN"
                        },


                        {
                            title: "MSL",
                            field: "MSL",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value) || value === 0) return value || '';
                                
                                const bgColor = value < 0 ? '#ffeb3b' : '#f44336'; // yellow for negative, red for positive
                                const textColor = value < 0 ? '#000' : '#fff';
                                return `<span style="background-color:${bgColor}; color:${textColor}; padding:4px 8px; border-radius:3px; font-weight:600;">${value}</span>`;
                            }
                        },
    {
                            title: "FBA Price",
                            field: "FBA_Price",
                            hozAlign: "center",
                            // formatter: "dollar"
                        },


                         {
                                title: "Pft%",
                                field: "Pft%",
                                hozAlign: "center",
                                formatter: function(cell) {
                                    return cell.getValue();
                                }
                            },
                            {
                                title: "ROI%",
                                field: "ROI%",
                                hozAlign: "center",
                                formatter: function(cell) {
                                    return cell.getValue();
                                }
                            },


                        {
                            title: "S Price",
                            field: "S_Price",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 's_price',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table
                                            .replaceData();
                                    }
                                });
                            }
                        },
                        {
                            title: "SPft%",
                            field: "SPft%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
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
                            title: "FBA Ship",
                            field: "FBA_Ship_Calculation",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return value.toFixed(2);
                            }
                        },

                         {
                            title: "Send Cost",
                            field: "Send_Cost",
                            hozAlign: "center",
                            editor: "input"
                        },



                        {
                            title: "FBA Fee",
                            field: "Fulfillment_Fee",
                            hozAlign: "center"
                        },


                        // {
                        //     title: "Correct Cost",
                        //     field: "Correct_Cost",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
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
                        //     title: "Approval",
                        //     field: "Approval",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Profit is ok",
                        //     field: "Profit_is_ok",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                    
                        // {
                        //     title: "TPFT",
                        //     field: "TPFT",
                        //     hozAlign: "center",
                        //     // formatter: "dollar"
                        // },

                        // {
                        //     title: "Inv age",
                        //     field: "Inv_age",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = cell.getValue();
                        //         return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openInvageModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                        //     }
                        // },





                        // {
                        //     title: "TPFT",
                        //     field: "TPFT",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         return cell.getValue();
                        //     },
                        // },


                       


                        // {
                        //     title: "FBA_CVR",
                        //     field: "FBA_CVR",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         return cell.getValue();
                        //     },
                        // },

                       


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



                        // {
                        //     title: "WH ACT",
                        //     field: "WH_ACT",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     tooltip: true
                        // },

                      

                        // {
                        //     title: "Shipment Track Status",
                        //     field: "Shipment_Track_Status",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     cellEdited: function(cell) {
                        //         var data = cell.getRow().getData();
                        //         var value = cell.getValue();

                        //         $.ajax({
                        //             url: '/update-fba-manual-data',
                        //             method: 'POST',
                        //             data: {
                        //                 sku: data.FBA_SKU,
                        //                 field: 'shipment_track_status',
                        //                 value: value,
                        //                 _token: '{{ csrf_token() }}'
                        //             },
                        //             success: function() {
                        //                 table.replaceData();
                        //             }
                        //         });
                        //     }
                        // },

                       

                        // {
                        //     title: "FBA Fee Manual",
                        //     field: "FBA_Fee_Manual",
                        //     hozAlign: "center",
                        //     editor: "input",
                        //     formatter: function(cell) {
                        //         cell.getElement().style.color = "#a80f8b"; // dark text
                        //         return cell.getValue();
                        //     }
                        // },

                        // ,



                     



                        {
                            title: "T sales",
                            field: "T_sales",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue() || 0;
                                const row = cell.getRow().getData();
                                const months = {
                                    Jan: row.Jan || 0,
                                    Feb: row.Feb || 0,
                                    Mar: row.Mar || 0,
                                    Apr: row.Apr || 0,
                                    May: row.May || 0,
                                    Jun: row.Jun || 0,
                                    Jul: row.Jul || 0,
                                    Aug: row.Aug || 0,
                                    Sep: row.Sep || 0,
                                    Oct: row.Oct || 0,
                                    Nov: row.Nov || 0,
                                    Dec: row.Dec || 0,
                                };
                                const payload = encodeURIComponent(JSON.stringify(months));
                                return `<span style='font-weight:600;'>${value}</span> &nbsp; <i class='fa fa-eye monthly-eye' data-months='${payload}' data-sku='${row.SKU}' style='cursor:pointer; color:#3b7ddd;' title='View monthly breakdown'></i>`;
                            }
                        },

                        // Jan–Dec columns intentionally removed — values are available in modal via the T_sales eye icon
                    ]
                });

                table.on('cellEdited', function(cell) {
                    var row = cell.getRow();
                    var data = row.getData();
                    var field = cell.getColumn().getField();
                    var value = cell.getValue();

                    if (field === 'Barcode' || field === 'Done' || field === 'Listed' || field === 'Live' ||
                        field === 'Dispatch_Date' || field === 'Weight' || field === 'WH_ACT' || field ===
                        'Quantity_in_each_box' ||
                        field === 'Total_quantity_sent' || field === 'Send_Cost' || field ===
                        'IN_Charges' ||
                        field === 'Warehouse_INV_Reduction' || field === 'Shipping_Amount' || field ===
                        'Inbound_Quantity' || field === 'FBA_Send' || field ===
                        'FBA_Fee_Manual' || field === 'MSL' || field === 'Correct_Cost' || field ===
                        'Approval' || field === 'Profit_is_ok') {
                        $.ajax({
                            url: '/update-fba-manual-data',
                            method: 'POST',
                            data: {
                                sku: data.FBA_SKU,
                                field: field.toLowerCase(),
                                value: value,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                console.log('Data saved successfully');
                            },
                            error: function(xhr) {
                                console.error('Error saving data');
                            }
                        });
                    }
                });

                // INV 0 and More than 0 Filter
                function applyFilters() {
                    const inventoryFilter = $('#inventory-filter').val();
                    const parentFilter = $('#parent-filter').val();
                    const pftFilter = $('#pft-filter').val();

                    table.clearFilter(true);

                    // SKU Search Filter
                    if (skuSearch) {
                        table.addFilter(function(data) {
                            const sku = (data.SKU || '').toUpperCase();
                            const fbaSku = (data.FBA_SKU || '').toUpperCase();
                            return sku.includes(skuSearch) || fbaSku.includes(skuSearch);
                        });
                    }

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
                            const value = parseFloat(data['Pft%']);
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
                }

                $('#sku-search').on('input', function() {
                    applyFilters();
                });

                $('#inventory-filter').on('change', function() {
                    applyFilters();
                });

                $('#parent-filter').on('change', function() {
                    applyFilters();
                });

                $('#pft-filter').on('change', function() {
                    applyFilters();
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

                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        const field = def.field;
                        const title = def.title || field;
                        
                        if (field && field !== '_select' && field !== '_accept' && title) {
                            const li = document.createElement('li');
                            const label = document.createElement('label');
                            label.className = 'dropdown-item';
                            label.style.cursor = 'pointer';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.checked = col.isVisible();
                            checkbox.style.marginRight = '8px';
                            checkbox.dataset.field = field;
                            
                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(title));
                            li.appendChild(label);
                            menu.appendChild(li);
                            
                            checkbox.addEventListener('change', function() {
                                if (this.checked) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                                saveColumnVisibilityToServer();
                            });
                        }
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

                    fetch('/fba-dispatch-column-visibility', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ visibility: visibility })
                    });
                }

                function applyColumnVisibilityFromServer() {
                    fetch('/fba-dispatch-column-visibility', {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(savedVisibility => {
                            table.getColumns().forEach(col => {
                                const def = col.getDefinition();
                                if (def.field && savedVisibility[def.field] !== undefined) {
                                    if (savedVisibility[def.field]) {
                                        col.show();
                                    } else {
                                        col.hide();
                                    }
                                }
                            });
                        })
                        .catch(error => console.error('Error loading column visibility:', error));
                }

                // Apply saved visibility and build dropdown when table is ready
                setTimeout(() => {
                    applyColumnVisibilityFromServer();
                    buildColumnDropdown();
                }, 500);

                // Show All Columns button
                document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                    table.getColumns().forEach(col => {
                        col.show();
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                });

                // Toggle column from dropdown
                document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                    if (e.target.type === 'checkbox') {
                        buildColumnDropdown();
                    }
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

            // Monthly sales modal handler (twelve-month breakdown)
            $(document).on('click', '.monthly-eye', function(e) {
                e.preventDefault();
                try {
                    // data-months is url-encoded JSON
                    const raw = $(this).attr('data-months') || '';
                    const json = decodeURIComponent(String(raw));
                    const months = JSON.parse(json || '{}');
                    const sku = $(this).data('sku') || '';

                    // Render months horizontally: one header row with months and one row with values
                    const monthKeys = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    let html = '<div style="overflow-x:auto;"><table class="table table-sm table-bordered"><thead><tr>';
                    monthKeys.forEach(m => {
                        html += `<th class="text-center" style="min-width:60px;">${m}</th>`;
                    });
                    html += '</tr></thead><tbody><tr>';
                    monthKeys.forEach(m => {
                        const v = months[m] || 0;
                        // format numbers with commas
                        const formatted = (typeof v === 'number') ? v.toLocaleString() : (parseFloat(v) ? parseFloat(v).toLocaleString() : v);
                        html += `<td class='text-end' style="vertical-align:middle;">${formatted}</td>`;
                    });
                    html += '</tr></tbody></table></div>';
                    html += '</tbody></table>';

                    $('#monthlyModalSku').text(sku);
                    $('#monthlySalesModalBody').html(html);
                    $('#monthlySalesModal').appendTo('body').modal('show');
                } catch (err) {
                    console.error('Failed to show monthly sales modal', err);
                    alert('Failed to load monthly sales details');
                }
            });
        </script>
    @endsection
