@extends('layouts.vertical', ['title' => 'Walmart Pricing CVR', 'sidenav' => 'condensed'])

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

        /* NR Status Color Coding */
        .nr-select.nr-status {
            color: white;
            font-weight: bold;
        }

        .nr-select.nr-status option {
            color: black;
            font-weight: normal;
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
        'page_title' => 'Walmart Pricing CVR',
        'sub_title' => 'Walmart Pricing CVR',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..."
                        style="width: 150px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 120px; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">INV = 0</option>
                        <option value="more">INV &gt; 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" style="width: 120px; display: inline-block;">
                        <option value="all">All NR</option>
                        <option value="1">NR = 1</option>
                        <option value="0">NR = 0</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm"
                        style="width: 130px; display: inline-block;">
                        <option value="all">Show All</option>
                        <option value="hide">Hide Parents</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: 120px; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="listed">Listed</option>
                        <option value="live">Live</option>
                        <option value="both">Listed & Live</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                            id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu p-2" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> Show All
                    </button>

                    <!-- Export Button -->
                    <a href="{{ url('/walmart-export') }}" class="btn btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Export
                    </a>

                    <!-- Import Button -->
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import
                    </button>

                    <!-- Template Download Button -->
                    <a href="{{ url('/walmart-analytics/sample') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> Template
                    </a>

                    <!-- Refresh Button -->
                    <button id="refresh-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="row g-2">
                        <div class="col-auto">
                            <span id="total-sku-count-badge" class="badge bg-primary">Total SKUs: 0</span>
                        </div>
                        <div class="col-auto">
                            <span id="inv-gt-0" class="badge bg-success">INV &gt; 0: 0</span>
                        </div>
                        <div class="col-auto">
                            <span id="listed-count" class="badge bg-info">Listed: 0</span>
                        </div>
                        <div class="col-auto">
                            <span id="live-count" class="badge bg-warning">Live: 0</span>
                        </div>
                        <div class="col-auto">
                            <span id="avg-pft" class="badge bg-secondary">Avg PFT%: 0%</span>
                        </div>
                        <div class="col-auto">
                            <span id="avg-roi" class="badge bg-dark">Avg ROI%: 0%</span>
                        </div>
                        <div class="col-auto">
                            <span id="total-sales-amt-badge" class="badge bg-primary">Total SALES: $0</span>
                        </div>
                        <div class="col-auto">
                            <span id="total-pft-amt-badge" class="badge bg-success">Total PFT: $0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="walmart-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="walmart-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Walmart Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="importForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Upload CSV/Excel File</label>
                            <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-info">
                            <small><strong>File should contain:</strong> SKU, Buybox Price (or buybox_price, bb_price), SPRICE (optional)</small>
                            <br><small>Example: SKU, Buybox Price<br>ABC123, 29.99</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "walmart_tabulator_column_visibility";
        let table = null;

        $(document).ready(function() {
            table = new Tabulator("#walmart-table", {
                ajaxURL: "/walmart-data-json",
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                columnCalcs: "both",
                initialSort: [{
                    column: "parent",
                    dir: "asc"
                }],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent_summary) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        title: "SL No.",
                        field: "sl_no",
                        width: 60,
                        headerSort: false,
                        visible: true
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        width: 120,
                        headerSort: false,
                        visible: true
                    },
                    {
                        title: "(Child) Sku",
                        field: "sku",
                        width: 150,
                        headerSort: false,
                        visible: true,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) {
                                return `<strong>${sku}</strong>`;
                            }
                            return `<span style="cursor: pointer; user-select: all;" title="Click to copy">${sku}</span>`;
                        },
                        cellClick: function(e, cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();
                            if (!rowData.is_parent_summary && sku) {
                                navigator.clipboard.writeText(sku).then(function() {
                                    showToast('success', `SKU copied: ${sku}`);
                                }).catch(function(err) {
                                    console.error('Copy failed:', err);
                                });
                            }
                        }
                    },
                    {
                        title: "NR",
                        field: "nr",
                        width: 90,
                        headerSort: false,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue() || 'REQ'; // Default to REQ if empty
                            const sku = cell.getRow().getData().sku;
                            const isParent = cell.getRow().getData().is_parent_summary;

                            if (isParent) return '';

                            // Determine background color based on value
                            let bgColor = '#28a745'; // Default green for REQ
                            if (value === 'NR') {
                                bgColor = '#dc3545'; // Red for NR
                            }

                            return `
                                <select class="form-select form-select-sm nr-select nr-status" 
                                        data-sku="${sku}" 
                                        style="width: 70px; background-color: ${bgColor}; color: white; font-weight: bold;">
                                    <option value="NR" ${value === 'NR' ? 'selected' : ''}>NR</option>
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                </select>
                            `;
                        }
                    },
                    {
                        title: "Listed",
                        field: "listed",
                        width: 70,
                        headerSort: false,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const sku = cell.getRow().getData().sku;
                            const isParent = cell.getRow().getData().is_parent_summary;

                            if (isParent) return '';

                            return `<input type="checkbox" class="form-check-input listed-checkbox" 
                                   data-sku="${sku}" ${value ? 'checked' : ''}>`;
                        }
                    },
                    {
                        title: "Live",
                        field: "live",
                        width: 70,
                        headerSort: false,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const sku = cell.getRow().getData().sku;
                            const isParent = cell.getRow().getData().is_parent_summary;

                            if (isParent) return '';

                            return `<input type="checkbox" class="form-check-input live-checkbox" 
                                   data-sku="${sku}" ${value ? 'checked' : ''}>`;
                        }
                    },
                    {
                        title: "INV",
                        field: "inv",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "OV L30",
                        field: "l30",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "LP",
                        field: "lp",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "Ship",
                        field: "ship",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "COGS",
                        field: "cogs",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "Price",
                        field: "price",
                        width: 90,
                        headerSort: false
                    },
                    {
                        title: "SPRICE",
                        field: "sprice",
                        width: 90,
                        headerSort: false,
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return '$' + value.toFixed(2);
                        }
                    },
                    {
                        title: "PFT %",
                        field: "pft_percent",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            let color = '';
                            
                            // Same as Amazon PFT% color logic
                            if (value < 10) color = '#a00211'; // red
                            else if (value >= 10 && value < 15) color = '#ffc107'; // yellow
                            else if (value >= 15 && value < 20) color = '#3591dc'; // blue
                            else if (value >= 20 && value <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "ROI %",
                        field: "roi",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            let color = '';
                            
                            // Same as Amazon ROI% color logic
                            if (value < 50) color = '#a00211'; // red
                            else if (value >= 50 && value < 75) color = '#ffc107'; // yellow
                            else if (value >= 75 && value <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "SPFT %",
                        field: "spft_percent",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            let color = '';
                            
                            // Same as Amazon SPFT% color logic
                            if (value < 10) color = '#a00211'; // red
                            else if (value >= 10 && value < 15) color = '#ffc107'; // yellow
                            else if (value >= 15 && value < 20) color = '#3591dc'; // blue
                            else if (value >= 20 && value <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "SROI %",
                        field: "sroi",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            let color = '';
                            
                            // Same as Amazon SROI% color logic
                            if (value < 50) color = '#a00211'; // red
                            else if (value >= 50 && value < 75) color = '#ffc107'; // yellow
                            else if (value >= 75 && value <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "L60",
                        field: "l60",
                        width: 70,
                        headerSort: false
                    },
                    {
                        title: "W L30",
                        field: "w_l30",
                        width: 80,
                        headerSort: false,
                        visible: true
                    },
                    {
                        title: "BB Price",
                        field: "buybox_price",
                        width: 90,
                        headerSort: false,
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            if (value === 0) return '';
                            return '$' + value.toFixed(2);
                        }
                    }

                ]
            });

            // NR select change handler with color coding
            $(document).on('change', '.nr-select', function() {
                const $select = $(this);
                const value = $select.val();
                const sku = $select.data('sku');

                // Update background color based on selection (only NR or REQ)
                let bgColor = '#28a745'; // Default green for REQ
                if (value === 'NR') {
                    bgColor = '#dc3545'; // Red for NR
                    $select.css('background-color', bgColor).css('color', 'white');
                } else if (value === 'REQ') {
                    bgColor = '#28a745'; // Green for REQ
                    $select.css('background-color', bgColor).css('color', 'white');
                }

                // Save to database
                $.ajax({
                    url: '/walmart/save-nr',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        nr: value
                    },
                    success: function(response) {
                        showToast('success', `NR status updated to ${value}`);
                    },
                    error: function() {
                        showToast('error', 'Failed to update NR status');
                    }
                });
            });

            // Listed checkbox change handler
            $(document).on('change', '.listed-checkbox', function() {
                const sku = $(this).data('sku');
                const value = $(this).is(':checked');

                $.ajax({
                    url: '/walmart/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: 'Listed',
                        value: value
                    },
                    success: function(response) {
                        showToast('success', 'Listed status updated');
                    },
                    error: function() {
                        showToast('error', 'Failed to update Listed status');
                    }
                });
            });

            // Live checkbox change handler
            $(document).on('change', '.live-checkbox', function() {
                const sku = $(this).data('sku');
                const value = $(this).is(':checked');

                $.ajax({
                    url: '/walmart/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: 'Live',
                        value: value
                    },
                    success: function(response) {
                        showToast('success', 'Live status updated');
                    },
                    error: function() {
                        showToast('error', 'Failed to update Live status');
                    }
                });
            });

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("sku", "like", value);
            });

            // Cell edited handler
            table.on('cellEdited', function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();

                if (field === 'sprice') {
                    const sku = data.sku;
                    const sprice = parseFloat(cell.getValue()) || 0;

                    // Save to database
                    $.ajax({
                        url: '/save-walmart-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: sprice
                        },
                        success: function(response) {
                            showToast('success', 'SPRICE updated successfully');
                            
                            // Update SPFT% and SROI% from server response
                            if (response.spft_percent !== undefined) {
                                row.update({
                                    'spft_percent': response.spft_percent
                                });
                            }
                            if (response.sroi_percent !== undefined) {
                                row.update({
                                    'sroi': response.sroi_percent
                                });
                            }
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update SPRICE');
                        }
                    });
                } else if (field === 'buybox_price') {
                    const sku = data.sku;
                    const buyboxPrice = parseFloat(cell.getValue()) || 0;

                    // Save to database
                    $.ajax({
                        url: '/save-walmart-buybox-price',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            buybox_price: buyboxPrice
                        },
                        success: function(response) {
                            showToast('success', 'Buybox price updated successfully');
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update buybox price');
                        }
                    });
                } else if (field === 'price') {
                    updateCalcValues();
                }
            });

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const parentFilter = $('#parent-filter').val();
                const statusFilter = $('#status-filter').val();

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('inv', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('inv', '>', 0);
                }

                if (nrlFilter !== 'all') {
                    table.addFilter('nr', '=', parseInt(nrlFilter));
                }

                if (parentFilter === 'hide') {
                    table.addFilter('is_parent_summary', '=', false);
                }

                if (statusFilter === 'listed') {
                    table.addFilter('listed', '=', true);
                } else if (statusFilter === 'live') {
                    table.addFilter('live', '=', true);
                } else if (statusFilter === 'both') {
                    table.addFilter([{
                            field: 'listed',
                            type: '=',
                            value: true
                        },
                        {
                            field: 'live',
                            type: '=',
                            value: true
                        }
                    ]);
                }

                updateCalcValues();
                updateSummary();
            }

            $('#inventory-filter, #nrl-filter, #parent-filter, #status-filter').on('change', function() {
                applyFilters();
            });

            // Update calc values (matching Amazon's formula with 0.80)
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let totalCogs = 0;

                data.forEach(row => {
                    if (row.is_parent_summary || parseFloat(row.inv) <= 0) return;

                    const price = parseFloat(row.price) || 0;
                    const l30 = parseFloat(row.l30) || 0;
                    const lp = parseFloat(row.lp) || 0;
                    const ship = parseFloat(row.ship) || 0;

                    const sales = price * l30;
                    const profit = (price * 0.80 - lp - ship) * l30;
                    const cogs = lp * l30;

                    totalSales += sales;
                    totalProfit += profit;
                    totalCogs += cogs;
                });

                // TOP PFT% = (total profit sum / total sales) * 100
                const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // TOP ROI% = (total profit sum / total COGS) * 100
                const avgRoi = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

                $('#avg-pft').text('Avg PFT%: ' + avgPft.toFixed(2) + '%');
                $('#avg-roi').text('Avg ROI%: ' + avgRoi.toFixed(2) + '%');
            }

            // Update summary
            function updateSummary() {
                const data = table.getData("active");
                const childData = data.filter(row => !row.is_parent_summary);

                let totalSkuCount = childData.length;
                let invGt0 = 0;
                let listedCount = 0;
                let liveCount = 0;
                let totalSalesAmt = 0;
                let totalPftAmt = 0;

                childData.forEach(row => {
                    if (parseFloat(row.inv) > 0) invGt0++;
                    if (row.listed) listedCount++;
                    if (row.live) liveCount++;

                    const price = parseFloat(row.price) || 0;
                    const l30 = parseFloat(row.l30) || 0;
                    const lp = parseFloat(row.lp) || 0;
                    const ship = parseFloat(row.ship) || 0;

                    totalSalesAmt += price * l30;
                    totalPftAmt += (price * 0.80 - lp - ship) * l30;
                });

                $('#total-sku-count-badge').text('Total SKUs: ' + totalSkuCount.toLocaleString());
                $('#inv-gt-0').text('INV > 0: ' + invGt0.toLocaleString());
                $('#listed-count').text('Listed: ' + listedCount.toLocaleString());
                $('#live-count').text('Live: ' + liveCount.toLocaleString());
                $('#total-sales-amt-badge').text('Total SALES: $' + Math.round(totalSalesAmt).toLocaleString());
                $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
            }

            // Build column visibility dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                table.getColumns().forEach(col => {
                    const field = col.getField();
                    const title = col.getDefinition().title;
                    const visible = col.isVisible();

                    if (field && title) {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input" type="checkbox" 
                                   data-field="${field}" ${visible ? 'checked' : ''}>
                            <label class="form-check-label">${title}</label>
                        `;
                        menu.appendChild(div);
                    }
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

                fetch('/walmart-column-visibility', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        visibility: JSON.stringify(visibility)
                    })
                }).catch(err => console.error('Error saving column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                fetch('/walmart-column-visibility', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.visibility) {
                            const visibility = JSON.parse(data.visibility);
                            Object.keys(visibility).forEach(field => {
                                const column = table.getColumn(field);
                                if (column) {
                                    if (visibility[field]) {
                                        column.show();
                                    } else {
                                        column.hide();
                                    }
                                }
                            });
                            buildColumnDropdown();
                        }
                    })
                    .catch(err => console.error('Error loading column visibility:', err));
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
                setTimeout(function() {
                    buildColumnDropdown();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(function() {
                    updateSummary();
                }, 100);
            });

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const field = e.target.getAttribute('data-field');
                    const column = table.getColumn(field);

                    if (e.target.checked) {
                        column.show();
                    } else {
                        column.hide();
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

            // Export button - now handled by anchor tag, no JS needed

            // Refresh button
            $('#refresh-btn').on('click', function() {
                table.setData();
            });

            // Import form handler
            $('#importForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#excelFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a file');
                    return;
                }

                formData.append('excel_file', file);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $('#uploadBtn').prop('disabled', true).html(
                    '<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/walmart-import',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.success);
                            $('#importModal').modal('hide');
                            $('#importForm')[0].reset();
                            table.setData();
                        } else if (response.error) {
                            showToast('error', response.error);
                        } else {
                            showToast('success', 'Import successful');
                            $('#importModal').modal('hide');
                            table.setData();
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Import failed';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        showToast('error', errorMsg);
                    },
                    complete: function() {
                        $('#uploadBtn').prop('disabled', false).html(
                            '<i class="fas fa-upload"></i> Upload');
                    }
                });
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
    </script>
@endsection
