@extends('layouts.vertical', ['title' => 'Aliexpress Daily Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'Aliexpress Daily Data',
        'sub_title' => 'Aliexpress Daily Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Aliexpress Daily Data</h4>
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
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-commission-badge" style="color: white; font-weight: bold;">Commission: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="aliexpress-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="aliexpress-table" style="flex: 1;"></div>
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
                        <i class="fa fa-upload me-2"></i>Upload Aliexpress Daily Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dailyDataFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="dailyDataFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">
                            Supported formats: Excel (.xlsx, .xls) or CSV
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
    const COLUMN_VIS_KEY = "aliexpress_tabulator_column_visibility";
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
        console.log("Initializing Tabulator for Aliexpress Daily Data...");
        table = new Tabulator("#aliexpress-table", {
            ajaxURL: "/aliexpress/daily-data",
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
            initialSort: [{
                column: "order_date",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Order ID",
                    field: "order_id",
                    width: 180,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search..."
                },
                {
                    title: "SKU Code",
                    field: "sku_code",
                    width: 150,
                    frozen: true,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold"
                },
                {
                    title: "Quantity",
                    field: "quantity",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        return cell.getValue() || 1;
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
                    title: "Buyer Name",
                    field: "buyer_name",
                    width: 150
                },
                {
                    title: "Order Date",
                    field: "order_date",
                    width: 150,
                    sorter: "datetime",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Product Price",
                    field: "product_total",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Unit Price",
                    field: "unit_price",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Order Amount",
                    field: "order_amount",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Platform Coupon",
                    field: "platform_coupon",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
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
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
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
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "PFT Each",
                    field: "pft_each",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "PFT Each %",
                    field: "pft_each_pct",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "T PFT",
                    field: "pft",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "ROI %",
                    field: "roi",
                    width: 80,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "Payment Method",
                    field: "payment_method",
                    width: 150
                },
                {
                    title: "Buyer Country",
                    field: "buyer_country",
                    width: 120
                },
                {
                    title: "State/Province",
                    field: "state_province",
                    width: 120
                },
                {
                    title: "City",
                    field: "city",
                    width: 120
                },
                {
                    title: "Tracking Number",
                    field: "tracking_number",
                    width: 150
                },
                {
                    title: "Shipping Time",
                    field: "shipping_time",
                    width: 150,
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
            table.setFilter("sku_code", "like", value);
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
                // Skip rows with empty SKU or order_id
                if (!row.sku_code || row.sku_code === '' || !row.order_id || row.order_id === '') {
                    return;
                }
                
                // Skip refunded, returned, cancelled orders
                const status = (row.order_status || '').toLowerCase();
                if (status.includes('refund') || status.includes('return') || status.includes('cancel') || status.includes('closed')) {
                    return;
                }
                
                totalOrders++;
                
                const quantity = parseInt(row.quantity) || 1;
                totalQuantity += quantity;
                
                const orderAmount = parseFloat(row.order_amount) || 0;
                const platformCoupon = parseFloat(row.platform_coupon) || 0;
                
                // Use order_amount for revenue (it's already total)
                totalRevenue += orderAmount;
                totalCommission += platformCoupon;
                
                // For weighted average price - use unit_price from backend
                const unitPrice = parseFloat(row.unit_price) || 0;
                if (quantity > 0 && unitPrice > 0) {
                    totalWeightedPrice += unitPrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                
                // Use backend-calculated values
                const pft = parseFloat(row.pft) || 0;
                const cogs = parseFloat(row.cogs) || 0;
                
                totalPft += pft;
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
            
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
            $('#total-commission-badge').text('Commission: $' + totalCommission.toFixed(2));
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/aliexpress-column-visibility', {
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

            fetch('/aliexpress-column-visibility', {
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
            fetch('/aliexpress-column-visibility', {
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
            table.download("csv", "aliexpress_daily_data.csv");
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
            const totalChunks = 1; // Single chunk for Aliexpress
            const uploadId = 'aliexpress_' + Date.now();
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
                    url: '/aliexpress/upload-daily-data',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            totalImported += response.imported || 0;
                            const progress = Math.round(((currentChunk + 1) / totalChunks) * 100);

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
                                    table.setData('/aliexpress/daily-data'); // Refresh table data
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
