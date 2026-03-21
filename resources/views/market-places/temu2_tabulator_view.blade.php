@extends('layouts.vertical', ['title' => 'Temu 2 Daily Data', 'sidenav' => 'condensed'])

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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
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
        'page_title' => 'Temu 2 Daily Data',
        'sub_title' => 'Temu 2 Daily Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu 2 Daily Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-success dropdown-toggle" type="button"
                            id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-file-excel"></i> Export
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li>
                                <a class="dropdown-item export-l30" href="#" data-action="l30">
                                    <i class="fa fa-download me-1"></i> Export L30 Data
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item export-l7" href="#" data-action="l7">
                                    <i class="fa fa-download me-1"></i> Export L7 Data
                                </a>
                            </li>
                        </ul>
                    </div>
                    <span id="export-loading" class="ms-2" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                        <span class="ms-1">Loading L7 data...</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDailyDataModal">
                        <i class="fa fa-upload"></i> Upload Daily Data
                    </button>
                    <a href="{{ route('temu.decrease') }}" class="btn btn-sm btn-outline-primary" title="View SKU analytics (DIL%, CVR, pricing, ads)">
                        <i class="fa fa-chart-line"></i> Temu Analytics
                    </a>
                </div>

                <!-- Summary Stats (same badges as Temu) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">PFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT Total: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge" style="color: white; font-weight: bold;">L30 Sales: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="temu2-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="temu2-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Upload Daily Data Modal (same as Temu: same DB tables temu_daily_data / temu_daily_data_l60) -->
    <div class="modal fade" id="uploadDailyDataModal" tabindex="-1" aria-labelledby="uploadDailyDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDailyDataModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Temu 2 Daily Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dailyDataUploadPeriod" class="form-label">Upload for</label>
                        <select id="dailyDataUploadPeriod" class="form-select form-select-sm" style="width: auto;">
                            <option value="L30">L30 Sales (temu2_daily_data)</option>
                            <option value="L60">L60 Sales (temu2_daily_data_l60)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dailyDataFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="dailyDataFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">
                            Supported formats: Excel (.xlsx, .xls) or CSV. Same format for L30 and L60.
                            <br>
                            <a href="{{ route('temu.daily.sample') }}" class="text-primary">
                                <i class="fa fa-download me-1"></i>Download Sample Excel Template
                            </a>
                        </div>
                    </div>
                    
                    <div id="uploadProgressContainer" style="display: none;">
                        <div class="mb-2">
                            <strong>Upload Progress:</strong>
                        </div>
                        <div class="progress mb-2" style="height: 25px;">
                            <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="uploadStatus" class="text-muted small"></div>
                    </div>

                    <div id="uploadResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startUploadBtn">
                        <i class="fa fa-upload me-1"></i>Start Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const COLUMN_VIS_KEY = "temu2_tabulator_column_visibility";
    let table = null;
    
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

    $(document).ready(function() {
        // Set CSRF token for AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        // Initialize Tabulator (Temu 2 data: temu2_daily_data table)
        console.log("Initializing Tabulator for Temu 2 Daily Data...");
        table = new Tabulator("#temu2-table", {
            ajaxURL: "/temu2/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                if (Array.isArray(response)) {
                    return response;
                }
                return response;
            },
            ajaxError: function(error) {
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                updateSummary();
            },
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "Show",
                        "first": "First",
                        "first_title": "First Page",
                        "last": "Last",
                        "last_title": "Last Page",
                        "prev": "Prev",
                        "prev_title": "Prev Page",
                        "next": "Next",
                        "next_title": "Next Page",
                        "counter": {
                            "showing": "Showing",
                            "of": "of",
                            "rows": "rows"
                        }
                    }
                }
            },
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                }
            },
            initialSort: [{
                column: "created_at",
                dir: "desc"
            }],
            columns: [
                {
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
                    title: "Order ID",
                    field: "order_id",
                    width: 180,
                    frozen: true
                },
                {
                    title: "SKU",
                    field: "contribution_sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 150,
                    frozen: true,
                    cssClass: "text-primary fw-bold",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');
                        if (isParent) return '';
                        return sku || '';
                    }
                },
                {
                    title: "Product Name",
                    field: "product_name_by_customer_order",
                    width: 300,
                    tooltip: true
                },
                {
                    title: "Variation",
                    field: "variation",
                    width: 120
                },
                {
                    title: "Qty Purchased",
                    field: "quantity_purchased",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Qty Shipped",
                    field: "quantity_shipped",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Qty To Ship",
                    field: "quantity_to_ship",
                    hozAlign: "center",
                    sorter: "number",
                    width: 120
                },
                {
                    title: "Base Price",
                    field: "base_price_total",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "FB Prc",
                    field: "fb_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        return total < 27 ? (basePrice + 2.99).toFixed(2) : basePrice.toFixed(2);
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "COGS",
                    field: "cogs",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        return (quantity * lp).toFixed(2);
                    }
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
                },
                {
                    title: "PFT Total",
                    field: "pft",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                    },
                    mutator: function(value, data) {
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        let calculatedFbPrice = total < 27 ? basePrice + 2.99 : basePrice;
                        const lp = parseFloat(data.lp) || 0;
                        const temuShip = parseFloat(data.temu_ship) || 0;
                        const pftDecimal = calculatedFbPrice > 0 ? (calculatedFbPrice * 0.96 - lp - temuShip) / calculatedFbPrice : 0;
                        return (pftDecimal * calculatedFbPrice * quantity).toFixed(2);
                    }
                },
                {
                    title: "L30 Sales",
                    field: "l30_sales",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 },
                    mutator: function(value, data) {
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        const calculatedFbPrice = total < 27 ? basePrice + 2.99 : basePrice;
                        return (quantity * calculatedFbPrice).toFixed(2);
                    }
                },
                {
                    title: "Order Status",
                    field: "order_status",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        let color = 'secondary';
                        if (value.toLowerCase().includes('delivered')) color = 'success';
                        else if (value.toLowerCase().includes('shipped')) color = 'info';
                        else if (value.toLowerCase().includes('cancelled') || value.toLowerCase().includes('cancel')) color = 'danger';
                        else if (value.toLowerCase().includes('pending')) color = 'warning';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                {
                    title: "Fulfillment",
                    field: "fulfillment_mode",
                    width: 150
                },
                {
                    title: "Tracking",
                    field: "tracking_number",
                    width: 150
                },
                {
                    title: "Carrier",
                    field: "carrier",
                    width: 120
                },
                {
                    title: "Created At",
                    field: "created_at",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            table.setFilter("contribution_sku", "like", $(this).val());
        });

        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0, totalQuantity = 0, totalRevenue = 0, totalPft = 0, totalL30Sales = 0;
            let totalWeightedPrice = 0, totalQuantityForPrice = 0, totalCogs = 0;

            data.forEach(row => {
                if (row.Parent && row.Parent.startsWith('PARENT')) return;
                if (!row.contribution_sku || row.contribution_sku === '' || !row.order_id || row.order_id === '') return;
                totalOrders++;
                const quantity = parseInt(row.quantity_purchased) || 0;
                const basePrice = parseFloat(row.base_price_total) || 0;
                const lp = parseFloat(row.lp) || 0;
                const temuShip = parseFloat(row.temu_ship) || 0;
                totalQuantity += quantity;
                totalRevenue += basePrice * quantity;
                if (quantity > 0 && basePrice > 0) {
                    totalWeightedPrice += basePrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                if (quantity > 0 && basePrice > 0) {
                    const total = basePrice * quantity;
                    const calculatedFbPrice = total < 27 ? basePrice + 2.99 : basePrice;
                    const pftDecimal = calculatedFbPrice > 0 ? (calculatedFbPrice * 0.96 - lp - temuShip) / calculatedFbPrice : 0;
                    totalPft += pftDecimal * calculatedFbPrice * quantity;
                    totalL30Sales += quantity * calculatedFbPrice;
                    totalCogs += lp * quantity;
                }
            });

            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
            $('#pft-percentage-badge').text('PFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('PFT Total: $' + totalPft.toFixed(2));
            $('#pft-total-badge').toggleClass('bg-danger', totalPft < 0).toggleClass('bg-dark', totalPft >= 0);
            $('#l30-sales-badge').text('L30 Sales: $' + totalL30Sales.toFixed(2));
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
        }

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';
            fetch('/temu2-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block"; label.style.padding = "5px 10px"; label.style.cursor = "pointer";
                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox"; checkbox.value = def.field;
                        checkbox.checked = savedVisibility[def.field] !== false;
                        checkbox.style.marginRight = "8px";
                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(def.title));
                        li.appendChild(label);
                        menu.appendChild(li);
                    });
                });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) visibility[def.field] = col.isVisible();
            });
            fetch('/temu2-column-visibility', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu2-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) col.hide();
                    });
                });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });
        table.on('dataLoaded', updateSummary);
        table.on('dataProcessed', updateSummary);
        table.on('renderComplete', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const col = table.getColumn(e.target.value);
                e.target.checked ? col.show() : col.hide();
                saveColumnVisibilityToServer();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export L30 Data - uses current table data
        $(document).on('click', '.export-l30', function(e) {
            e.preventDefault();
            table.download("csv", "temu2_l30_daily_data.csv");
        });

        // Export L7 Data - fetches from L7 endpoint and downloads as CSV
        $(document).on('click', '.export-l7', function(e) {
            e.preventDefault();
            const $loading = $('#export-loading');
            $loading.show();
            $.ajax({
                url: '{{ url("/temu2/daily-data-l7") }}',
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(data) {
                    if (!Array.isArray(data)) {
                        showToast('Invalid response from L7 endpoint', 'error');
                        return;
                    }
                    const columns = ['Parent', 'order_id', 'contribution_sku', 'product_name_by_customer_order', 'variation',
                        'quantity_purchased', 'quantity_shipped', 'quantity_to_ship', 'base_price_total', 'fb_price',
                        'lp', 'temu_ship', 'pft', 'l30_sales', 'order_status', 'fulfillment_mode', 'tracking_number', 'carrier', 'created_at'];
                    const headers = columns.join(',');
                    const escapeCsv = function(val) {
                        if (val === null || val === undefined) return '""';
                        const s = String(val);
                        if (s.includes(',') || s.includes('"') || s.includes('\n')) {
                            return '"' + s.replace(/"/g, '""') + '"';
                        }
                        return '"' + s + '"';
                    };
                    const rows = data.map(function(row) {
                        const qty = parseInt(row.quantity_purchased) || 0;
                        const fbPrice = parseFloat(row.fb_price) || 0;
                        const l7Sales = (qty * fbPrice).toFixed(2);
                        const rowData = { ...row, l30_sales: l7Sales };
                        return columns.map(function(col) {
                            return escapeCsv(rowData[col] ?? '');
                        }).join(',');
                    });
                    const csv = [headers, ...rows].join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'temu2_l7_daily_data.csv';
                    link.click();
                    URL.revokeObjectURL(link.href);
                    showToast('L7 data exported successfully', 'success');
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Failed to fetch L7 data';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        });

        // Upload Daily Data (same endpoints and DB tables as Temu)
        $('#startUploadBtn').on('click', function() {
            const fileInput = document.getElementById('dailyDataFile');
            const file = fileInput.files[0];
            if (!file) {
                showToast('Please select a file to upload', 'error');
                return;
            }
            const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
            if (!validTypes.includes(file.type)) {
                showToast('Please select a valid Excel or CSV file', 'error');
                return;
            }
            $('#uploadProgressContainer').show();
            $('#uploadResult').hide();
            $('#startUploadBtn').prop('disabled', true);
            const totalChunks = 5;
            const period = $('#dailyDataUploadPeriod').val() || 'L30';
            const uploadUrl = period === 'L60' ? '/temu2/upload-daily-data-l60-chunk' : '/temu2/upload-daily-data-chunk';
            const uploadId = (period === 'L60' ? 'temu2_l60_' : 'temu2_') + Date.now();
            let currentChunk = 0;
            let totalImported = 0;

            function uploadChunk() {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('chunk', currentChunk);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            totalImported += response.imported || 0;
                            const progress = response.progress || 0;
                            $('#uploadProgressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                            $('#uploadStatus').text(`Processing chunk ${currentChunk + 1} of ${totalChunks}... (${totalImported} records imported so far)`);
                            if (currentChunk < totalChunks - 1) {
                                currentChunk++;
                                setTimeout(uploadChunk, 500);
                            } else {
                                $('#uploadProgressBar').removeClass('progress-bar-animated').addClass('bg-success');
                                $('#uploadResult').removeClass('alert-danger').addClass('alert-success')
                                    .html(`<i class="fa fa-check-circle me-2"></i>Upload completed successfully! ${totalImported} records imported to ${period} Sales.`).show();
                                $('#startUploadBtn').prop('disabled', false);
                                showToast(`${period} Sales upload completed! ${totalImported} records imported.`, 'success');
                                setTimeout(function() {
                                    $('#uploadDailyDataModal').modal('hide');
                                    resetUploadForm();
                                    if (period === 'L30') table.setData('/temu2/daily-data');
                                }, 2000);
                            }
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Upload failed. Please try again.';
                        $('#uploadProgressBar').removeClass('progress-bar-animated').addClass('bg-danger');
                        $('#uploadResult').removeClass('alert-success').addClass('alert-danger').html(`<i class="fa fa-exclamation-circle me-2"></i>${errorMessage}`).show();
                        $('#startUploadBtn').prop('disabled', false);
                        showToast(errorMessage, 'error');
                    }
                });
            }
            uploadChunk();
        });

        $('#uploadDailyDataModal').on('hidden.bs.modal', resetUploadForm);

        function resetUploadForm() {
            $('#dailyDataFile').val('');
            $('#uploadProgressContainer').hide();
            $('#uploadResult').hide();
            $('#uploadProgressBar').removeClass('bg-success bg-danger').addClass('progress-bar-animated').css('width', '0%').text('0%');
            $('#uploadStatus').text('');
            $('#startUploadBtn').prop('disabled', false);
        }
    });
</script>
@endsection
