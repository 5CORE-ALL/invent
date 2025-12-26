@extends('layouts.vertical', ['title' => 'Temu Daily Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'Temu Daily Data',
        'sub_title' => 'Temu Daily Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu Daily Data</h4>
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

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDailyDataModal">
                        <i class="fa fa-upload"></i> Upload Daily Data
                    </button>
                </div>

                <!-- Summary Stats -->
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
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Upload Daily Data Modal -->
    <div class="modal fade" id="uploadDailyDataModal" tabindex="-1" aria-labelledby="uploadDailyDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDailyDataModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Temu Daily Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dailyDataFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="dailyDataFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">
                            Supported formats: Excel (.xlsx, .xls) or CSV
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
    const COLUMN_VIS_KEY = "temu_tabulator_column_visibility";
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
        
        // Initialize Tabulator
        console.log("Initializing Tabulator for Temu Daily Data...");
        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                console.log("Response type:", typeof response);
                console.log("Is array:", Array.isArray(response));
                if (Array.isArray(response)) {
                    console.log("Number of records:", response.length);
                    if (response.length > 0) {
                        console.log("First record:", response[0]);
                    }
                }
                // Return the response as-is (should be an array)
                return response;
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
                console.error("Error details:", JSON.stringify(error));
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                console.log("Data loaded:", data.length, "rows");
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
                        
                        if (isParent) {
                            return '';
                        }
                        
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
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "FB Prc",
                    field: "fb_price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    mutator: function(value, data, type, params, component) {
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        
                        if (total < 27) {
                            return (basePrice + 2.99).toFixed(2);
                        }
                        return basePrice.toFixed(2);
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "COGS",
                    field: "cogs",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    mutator: function(value, data, type, params, component) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        const cogs = quantity * lp;
                        return cogs.toFixed(2);
                    }
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
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
                    mutator: function(value, data, type, params, component) {
                        const fbPrice = parseFloat(data.fb_price || data.base_price_total) || 0;
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        
                        // Calculate FB Price first
                        let calculatedFbPrice = fbPrice;
                        if (total < 27) {
                            calculatedFbPrice = basePrice + 2.99;
                        } else {
                            calculatedFbPrice = basePrice;
                        }
                        
                        const lp = parseFloat(data.lp) || 0;
                        const temuShip = parseFloat(data.temu_ship) || 0;
                        
                        // PFT = (FB Prc * 0.91 - LP - Temu Ship) * Quantity
                        const pft = (calculatedFbPrice * 0.91 - lp - temuShip) * quantity;
                        return pft.toFixed(2);
                    }
                },
                {
                    title: "L30 Sales",
                    field: "l30_sales",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    mutator: function(value, data, type, params, component) {
                        const basePrice = parseFloat(data.base_price_total) || 0;
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const total = basePrice * quantity;
                        
                        // Calculate FB Price
                        let calculatedFbPrice;
                        if (total < 27) {
                            calculatedFbPrice = basePrice + 2.99;
                        } else {
                            calculatedFbPrice = basePrice;
                        }
                        
                        // L30 Sales = Quantity * FB Prc
                        const l30Sales = quantity * calculatedFbPrice;
                        return l30Sales.toFixed(2);
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

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("contribution_sku", "like", value);
        });

        // Update summary stats
        // Update summary stats (matching eBay pattern exactly)
        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalPft = 0;
            let totalL30Sales = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;
            let totalCogs = 0;

            data.forEach(row => {
                // Skip parent rows (like eBay does)
                if (row.Parent && row.Parent.startsWith('PARENT')) {
                    return;
                }
                
                // Skip rows with empty SKU or order_id
                if (!row.contribution_sku || row.contribution_sku === '' || !row.order_id || row.order_id === '') {
                    return;
                }
                
                totalOrders++;
                const quantity = parseInt(row.quantity_purchased) || 0;
                const basePrice = parseFloat(row.base_price_total) || 0;
                
                totalQuantity += quantity;
                totalRevenue += basePrice * quantity;
                
                // Calculate weighted price (like eBay does: price * quantity / sum quantity)
                if (quantity > 0 && basePrice > 0) {
                    totalWeightedPrice += basePrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                
                // Calculate FB Price
                const total = basePrice * quantity;
                let calculatedFbPrice;
                if (total < 27) {
                    calculatedFbPrice = basePrice + 2.99;
                } else {
                    calculatedFbPrice = basePrice;
                }
                
                // Calculate PFT Total: (FB Prc * 0.91 - LP - Temu Ship) * Quantity
                const lp = parseFloat(row.lp) || 0;
                const temuShip = parseFloat(row.temu_ship) || 0;
                const pft = (calculatedFbPrice * 0.91 - lp - temuShip) * quantity;
                totalPft += pft;
                
                // Calculate L30 Sales: Quantity * FB Prc
                const l30Sales = quantity * calculatedFbPrice;
                totalL30Sales += l30Sales;
                
                // Calculate COGS: Quantity * LP
                const cogs = quantity * lp;
                totalCogs += cogs;
            });

            // Calculate average price (weighted by quantity, like eBay)
            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;

            // Calculate PFT Percentage: (PFT Total / Total Revenue) * 100
            const pftPercentage = totalRevenue > 0 ? (totalPft / totalRevenue) * 100 : 0;
            
            // Calculate ROI Percentage: (PFT Total / Total COGS) * 100
            // COGS = LP * Quantity
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
            $('#pft-percentage-badge').text('PFT %: ' + Math.round(pftPercentage) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + Math.round(roiPercentage) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('PFT Total: $' + totalPft.toFixed(2));
            
            // Color code PFT Total badge
            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
            
            $('#l30-sales-badge').text('L30 Sales: $' + totalL30Sales.toFixed(2));
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/temu-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;

                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block";
                        label.style.padding = "5px 10px";
                        label.style.cursor = "pointer";

                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox";
                        checkbox.value = def.field;
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
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

            fetch('/temu-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) {
                            col.hide();
                        }
                    });
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            updateSummary();
        });

        // Update summary when data changes (filters, pagination, etc.)
        table.on('dataProcessed', function() {
            updateSummary();
        });

        // Update summary when table is rendered
        table.on('renderComplete', function() {
            updateSummary();
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
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

        // Export functionality
        $('#export-btn').on('click', function() {
            table.download("csv", "temu_daily_data.csv");
        });

        // Upload Daily Data Handler
        $('#startUploadBtn').on('click', function() {
            const fileInput = document.getElementById('dailyDataFile');
            const file = fileInput.files[0];

            if (!file) {
                showToast('Please select a file to upload', 'error');
                return;
            }

            // Validate file type
            const validTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv'
            ];
            if (!validTypes.includes(file.type)) {
                showToast('Please select a valid Excel or CSV file', 'error');
                return;
            }

            // Show progress container
            $('#uploadProgressContainer').show();
            $('#uploadResult').hide();
            $('#startUploadBtn').prop('disabled', true);

            // Chunk settings
            const totalChunks = 5;
            const uploadId = 'temu_' + Date.now();
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
                    url: '/temu/upload-daily-data-chunk',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            totalImported += response.imported || 0;
                            const progress = response.progress || 0;

                            $('#uploadProgressBar')
                                .css('width', progress + '%')
                                .text(Math.round(progress) + '%');

                            $('#uploadStatus').text(
                                `Processing chunk ${currentChunk + 1} of ${totalChunks}... (${totalImported} records imported so far)`
                            );

                            if (currentChunk < totalChunks - 1) {
                                currentChunk++;
                                setTimeout(uploadChunk, 500);
                            } else {
                                $('#uploadProgressBar')
                                    .removeClass('progress-bar-animated')
                                    .addClass('bg-success');

                                $('#uploadResult')
                                    .removeClass('alert-danger')
                                    .addClass('alert-success')
                                    .html(`<i class="fa fa-check-circle me-2"></i>Upload completed successfully! ${totalImported} records imported.`)
                                    .show();

                                $('#startUploadBtn').prop('disabled', false);
                                showToast(`Upload completed! ${totalImported} records imported.`, 'success');

                                setTimeout(function() {
                                    $('#uploadDailyDataModal').modal('hide');
                                    resetUploadForm();
                                    table.setData('/temu/daily-data'); // Refresh table data
                                }, 2000);
                            }
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Upload failed. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        $('#uploadProgressBar')
                            .removeClass('progress-bar-animated')
                            .addClass('bg-danger');

                        $('#uploadResult')
                            .removeClass('alert-success')
                            .addClass('alert-danger')
                            .html(`<i class="fa fa-exclamation-circle me-2"></i>${errorMessage}`)
                            .show();

                        $('#startUploadBtn').prop('disabled', false);
                        showToast(errorMessage, 'error');
                    }
                });
            }

            uploadChunk();
        });

        // Reset upload form when modal is hidden
        $('#uploadDailyDataModal').on('hidden.bs.modal', function() {
            resetUploadForm();
        });

        function resetUploadForm() {
            $('#dailyDataFile').val('');
            $('#uploadProgressContainer').hide();
            $('#uploadResult').hide();
            $('#uploadProgressBar')
                .removeClass('bg-success bg-danger')
                .addClass('progress-bar-animated')
                .css('width', '0%')
                .text('0%');
            $('#uploadStatus').text('');
            $('#startUploadBtn').prop('disabled', false);
        }
    });
</script>
@endsection
