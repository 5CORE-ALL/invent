@extends('layouts.vertical', ['title' => 'Shein Daily Data', 'sidenav' => 'condensed'])

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shein Daily Data',
        'sub_title' => 'Shein Daily Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Shein Daily Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Upload Button -->
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fa fa-upload"></i> Upload CSV
                    </button>

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
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-commission-badge" style="color: white; font-weight: bold;">Commission: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="shein-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="shein-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload Shein Daily Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="upload-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="file-input" class="form-label">Select CSV/Excel File</label>
                            <input type="file" class="form-control" id="file-input" name="file" accept=".csv,.xlsx,.xls,.txt" required>
                            <div class="form-text">Accepted formats: CSV, XLSX, XLS, TXT (tab-separated)</div>
                        </div>
                        <div id="upload-progress" class="mb-3" style="display: none;">
                            <div class="progress">
                                <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="upload-status" class="text-muted"></small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="upload-btn">
                        <i class="fa fa-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "shein_tabulator_column_visibility";
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
        console.log("Initializing Tabulator for Shein Daily Data...");
        table = new Tabulator("#shein-table", {
            ajaxURL: "/shein/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                return response;
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
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
            initialSort: [{
                column: "order_processed_on",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Order Number",
                    field: "order_number",
                    width: 180,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search..."
                },
                {
                    title: "Seller SKU",
                    field: "seller_sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 150,
                    frozen: true,
                    cssClass: "text-primary fw-bold"
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
                    title: "Ship",
                    field: "ship",
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
                        const quantity = parseInt(data.quantity) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        const cogs = quantity * lp;
                        return cogs.toFixed(2);
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
                        const productPrice = parseFloat(data.product_price) || 0;
                        const quantity = parseInt(data.quantity) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        const ship = parseFloat(data.ship) || 0;
                        
                        // PFT = (Product Price * 0.89 - LP - Ship) * Quantity
                        const pft = (productPrice * 0.89 - lp - ship) * quantity;
                        return pft.toFixed(2);
                    }
                },
                {
                    title: "Order Type",
                    field: "order_type",
                    width: 120
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
                    title: "Quantity",
                    field: "quantity",
                    hozAlign: "center",
                    sorter: "number",
                    width: 100
                },
                {
                    title: "Product Name",
                    field: "product_name",
                    width: 300,
                    tooltip: true
                },
                {
                    title: "Product Description",
                    field: "product_description",
                    width: 250,
                    tooltip: true
                },
                {
                    title: "Specification",
                    field: "specification",
                    width: 150
                },
                {
                    title: "Shein SKU",
                    field: "shein_sku",
                    width: 130
                },
                {
                    title: "Product Price",
                    field: "product_price",
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
                    title: "Commission",
                    field: "commission",
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
                    title: "Coupon Discount",
                    field: "coupon_discount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Store Campaign",
                    field: "store_campaign_discount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Est. Revenue",
                    field: "estimated_merchandise_revenue",
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
                    title: "Consumption Tax",
                    field: "consumption_tax",
                    hozAlign: "right",
                    sorter: "number",
                    width: 130,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Shipment Mode",
                    field: "shipment_mode",
                    width: 130
                },
                {
                    title: "Tracking Number",
                    field: "tracking_number",
                    width: 150
                },
                {
                    title: "Seller Package",
                    field: "sellers_package",
                    width: 130
                },
                {
                    title: "Order Processed",
                    field: "order_processed_on",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Collection Deadline",
                    field: "collection_deadline",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString();
                    }
                },
                {
                    title: "Delivery Deadline",
                    field: "delivery_deadline",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString();
                    }
                },
                {
                    title: "Delivery Time",
                    field: "delivery_time",
                    sorter: "datetime",
                    width: 160,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Province",
                    field: "province",
                    width: 120
                },
                {
                    title: "City",
                    field: "city",
                    width: 120
                },
                {
                    title: "Exchange Order",
                    field: "exchange_order",
                    width: 130
                },
                {
                    title: "Product Status",
                    field: "product_status",
                    width: 130
                },
                {
                    title: "SKC",
                    field: "skc",
                    width: 100
                },
                {
                    title: "Item ID",
                    field: "item_id",
                    width: 100
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("seller_sku", "like", value);
        });

        // Update summary stats
        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalCommission = 0;
            let totalPft = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;
            let totalCogs = 0;

            data.forEach(row => {
                // Skip rows with empty order_number
                if (!row.order_number || row.order_number === '') {
                    return;
                }
                
                // Skip refunded orders
                const orderStatus = (row.order_status || '').toLowerCase();
                if (orderStatus.includes('refund') || orderStatus.includes('returned') || orderStatus.includes('cancelled')) {
                    return;
                }
                
                totalOrders++;
                const quantity = parseInt(row.quantity) || 0;
                const productPrice = parseFloat(row.product_price) || 0;
                const commission = parseFloat(row.commission) || 0;
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;
                
                totalQuantity += quantity;
                totalRevenue += productPrice * quantity;
                totalCommission += commission;
                
                // Calculate weighted price (price * quantity for average)
                if (quantity > 0 && productPrice > 0) {
                    totalWeightedPrice += productPrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                
                // Calculate PFT Total: (Product Price * 0.89 - LP - Ship) * Quantity
                const pft = (productPrice * 0.89 - lp - ship) * quantity;
                totalPft += pft;
                
                // Calculate COGS: Quantity * LP
                const cogs = quantity * lp;
                totalCogs += cogs;
            });

            // Calculate average price (weighted by quantity)
            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;

            // Calculate PFT Percentage: (PFT Total / Total Revenue) * 100
            const pftPercentage = totalRevenue > 0 ? (totalPft / totalRevenue) * 100 : 0;
            
            // Calculate ROI Percentage: (PFT Total / Total COGS) * 100
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
            $('#pft-percentage-badge').text('PFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('PFT Total: $' + totalPft.toFixed(2));
            
            // Color code PFT Total badge
            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
            
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
            $('#total-commission-badge').text('Commission: $' + totalCommission.toFixed(2));
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/shein-column-visibility', {
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

            fetch('/shein-column-visibility', {
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
            fetch('/shein-column-visibility', {
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

        // Update summary when data changes
        table.on('dataProcessed', function() {
            updateSummary();
        });

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
            table.download("csv", "shein_daily_data.csv");
        });

        // Upload functionality
        $('#upload-btn').on('click', function() {
            const fileInput = document.getElementById('file-input');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select a file', 'error');
                return;
            }

            const uploadId = 'shein_' + Date.now();
            const totalChunks = 1; // Process in single chunk for simplicity
            
            $('#upload-progress').show();
            $('#upload-btn').prop('disabled', true);
            
            uploadChunk(file, 0, totalChunks, uploadId);
        });

        function uploadChunk(file, chunk, totalChunks, uploadId) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('chunk', chunk);
            formData.append('totalChunks', totalChunks);
            formData.append('uploadId', uploadId);

            $.ajax({
                url: '/shein/upload-daily-data',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            $('#upload-progress-bar').css('width', percentComplete + '%');
                            $('#upload-status').text('Uploading: ' + percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        const progress = response.progress || 0;
                        $('#upload-progress-bar').css('width', progress + '%');
                        $('#upload-status').text(`Processing: ${progress}% (${response.imported || 0} imported, ${response.skipped || 0} skipped)`);
                        
                        if (chunk < totalChunks - 1) {
                            // Upload next chunk
                            uploadChunk(file, chunk + 1, totalChunks, uploadId);
                        } else {
                            // Upload complete
                            showToast(`Upload complete! ${response.imported || 0} records imported, ${response.skipped || 0} skipped`, 'success');
                            $('#upload-progress').hide();
                            $('#upload-btn').prop('disabled', false);
                            $('#file-input').val('');
                            $('#uploadModal').modal('hide');
                            
                            // Reload table data
                            table.setData('/shein/daily-data');
                        }
                    } else {
                        showToast('Upload failed: ' + (response.message || 'Unknown error'), 'error');
                        $('#upload-progress').hide();
                        $('#upload-btn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Upload failed: ';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg += xhr.responseJSON.message;
                    } else {
                        errorMsg += error;
                    }
                    showToast(errorMsg, 'error');
                    $('#upload-progress').hide();
                    $('#upload-btn').prop('disabled', false);
                }
            });
        }
    });
</script>
@endsection
