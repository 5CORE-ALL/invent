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
                    <input type="text" id="sku-search" class="form-control" placeholder="Search by SKU" style="max-width: 200px;">
                    
                    <button id="upload-data-btn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload"></i> Upload Data
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="columnDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                            <!-- Will be populated by JS -->
                        </ul>
                    </div>

                    <button id="show-all-columns-btn" class="btn btn-info">
                        <i class="fas fa-eye"></i> Show All
                    </button>
                    
                    <button id="export-btn" class="btn btn-success">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <span id="total-orders-badge" class="badge bg-primary">Total Orders: 0</span>
                        <span id="total-quantity-badge" class="badge bg-info">Total Quantity: 0</span>
                        <span id="total-revenue-badge" class="badge bg-success">Total Revenue: $0.00</span>
                        <span id="pft-percentage-badge" class="badge bg-warning text-dark">PFT %: 0%</span>
                        <span id="roi-percentage-badge" class="badge bg-secondary">ROI %: 0%</span>
                        <span id="avg-price-badge" class="badge bg-dark">Avg Price: $0.00</span>
                        <span id="pft-total-badge" class="badge bg-dark">PFT Total: $0.00</span>
                        <span id="total-cogs-badge" class="badge bg-secondary">Total COGS: $0.00</span>
                        <span id="total-commission-badge" class="badge bg-info">Commission: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="aliexpress-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="loading-indicator" style="display: none; padding: 20px; text-align: center;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="aliexpress-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload Aliexpress Daily Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="file-input" class="form-label">Choose Excel/CSV File</label>
                        <input type="file" class="form-control" id="file-input" accept=".xlsx,.xls,.csv">
                        <small class="text-muted">Supported formats: Excel (.xlsx, .xls), CSV</small>
                    </div>
                    <div id="upload-progress" class="progress" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" 
                             style="width: 0%;" id="progress-bar">0%</div>
                    </div>
                    <div id="upload-status" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="upload-btn">Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
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
                        "page_size": "Rows per page",
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
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search..."
                },
                {
                    title: "SKU Code",
                    field: "sku_code",
                    width: 150,
                    frozen: true,
                    headerFilter: "input",
                    cssClass: "text-primary fw-bold"
                },
                {
                    title: "Quantity",
                    field: "quantity",
                    width: 80,
                    hozAlign: "center",
                    formatter: function(cell) {
                        return cell.getValue() || 1;
                    }
                },
                {
                    title: "Order Status",
                    field: "order_status",
                    width: 120
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
                    sorter: "date"
                },
                {
                    title: "Product Price",
                    field: "product_total",
                    width: 100,
                    hozAlign: "right",
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
                    width: 100,
                    hozAlign: "right",
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
                    field: "pft_total",
                    width: 100,
                    hozAlign: "right",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    mutator: function(value, data) {
                        const productPrice = parseFloat(data.product_total) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        const ship = parseFloat(data.ship) || 0;
                        const quantity = parseInt(data.quantity) || 1;
                        
                        // Formula: (Product Price × 0.89 - LP - Ship) × Quantity
                        return ((productPrice * 0.89) - lp - ship) * quantity;
                    },
                    cssClass: function(cell) {
                        const value = cell.getValue();
                        return value >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
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
                    title: "State",
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
                    width: 150
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
                
                const productPrice = parseFloat(row.product_total) || 0;
                const orderAmount = parseFloat(row.order_amount) || 0;
                const platformCoupon = parseFloat(row.platform_coupon) || 0;
                
                totalRevenue += orderAmount;
                totalCommission += platformCoupon;
                
                // For weighted average price
                totalWeightedPrice += productPrice * quantity;
                totalQuantityForPrice += quantity;
                
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;
                const cogs = lp + ship;
                
                // PFT calculation: (Product Price × 0.89 - LP - Ship) × Quantity
                const pft = ((productPrice * 0.89) - lp - ship) * quantity;
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
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    const title = col.getDefinition().title;
                    if (!field || !title) return;

                    const isVisible = col.isVisible();
                    const li = document.createElement("li");
                    li.className = "dropdown-item";
                    li.innerHTML = `
                        <label style="cursor: pointer;">
                            <input type="checkbox" value="${field}" ${isVisible ? 'checked' : ''}> ${title}
                        </label>
                    `;
                    menu.appendChild(li);
                });
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

            fetch('/aliexpress-column-visibility', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/aliexpress-column-visibility', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(visibility => {
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && visibility.hasOwnProperty(field)) {
                        if (visibility[field]) {
                            col.show();
                        } else {
                            col.hide();
                        }
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
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    saveColumnVisibilityToServer();
                }
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

        // Upload functionality
        $('#upload-btn').on('click', function() {
            const fileInput = document.getElementById('file-input');
            const file = fileInput.files[0];
            
            if (!file) {
                showToast('Please select a file first', 'error');
                return;
            }

            const uploadId = 'aliexpress_' + Date.now();
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
                url: '/aliexpress/upload-daily-data',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        const progress = Math.round(((chunk + 1) / totalChunks) * 100);
                        $('#progress-bar').css('width', progress + '%').text(progress + '%');
                        
                        if (response.isLastChunk) {
                            showToast('Upload completed successfully!', 'success');
                            $('#upload-progress').hide();
                            $('#upload-btn').prop('disabled', false);
                            $('#uploadModal').modal('hide');
                            table.replaceData(); // Reload table data
                        } else {
                            uploadChunk(file, chunk + 1, totalChunks, uploadId);
                        }
                    } else {
                        showToast('Upload failed: ' + response.message, 'error');
                        $('#upload-progress').hide();
                        $('#upload-btn').prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    showToast('Upload error: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
                    $('#upload-progress').hide();
                    $('#upload-btn').prop('disabled', false);
                }
            });
        }
    });
</script>
@endsection
