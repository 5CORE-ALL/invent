@extends('layouts.vertical', ['title' => 'TikTok Daily Sales Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'TikTok Daily Sales Data',
        'sub_title' => 'TikTok Daily Sales Data Analysis (L30)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>TikTok Daily Sales Data (L30)</h4>
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
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge" style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">GPFT Total: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge" style="color: white; font-weight: bold;">L30 Sales: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge fs-6 p-2" id="pt-spent-badge" style="background-color: #28a745; color: white; font-weight: bold;">PT Spent: ${{ number_format($ptSpent ?? 0, 0) }}</span>
                        <span class="badge fs-6 p-2" id="kw-spent-badge" style="background-color: #ffc107; color: black; font-weight: bold;">KW Spent: ${{ number_format($kwSpent ?? 0, 0) }}</span>
                        <span class="badge fs-6 p-2" id="hl-spent-badge" style="background-color: #dc3545; color: white; font-weight: bold;">HL Spent: ${{ number_format($hlSpent ?? 0, 0) }}</span>
                        <span class="badge fs-6 p-2" id="tacos-percentage-badge" style="background-color: #6f42c1; color: white; font-weight: bold;">TACOS %: 0%</span>
                        <span class="badge fs-6 p-2" id="m-pft-badge" style="background-color: #fd7e14; color: white; font-weight: bold;">N PFT: 0%</span>
                        <span class="badge fs-6 p-2" id="n-roi-badge" style="background-color: #e83e8c; color: white; font-weight: bold;">N ROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percentage-badge" style="background-color: #20c997; color: white; font-weight: bold; display: none;">Ads %: 0%</span>
                        <span class="badge fs-6 p-2" id="pft-percentage-filtered-badge" style="background-color: #17a2b8; color: white; font-weight: bold; display: none;">PFT %: 0%</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="tiktok-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="tiktok-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const COLUMN_VIS_KEY = "amazon_sales_column_visibility";
    let table = null;
    const KW_SPENT = {{ $kwSpent ?? 0 }};
    const PT_SPENT = {{ $ptSpent ?? 0 }};
    const HL_SPENT = {{ $hlSpent ?? 0 }};
    
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
        console.log("Initializing Tabulator for TikTok Daily Sales Data...");
        table = new Tabulator("#tiktok-table", {
            ajaxURL: "/tiktok/daily-sales-data",
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
                    frozen: true
                },
                {
                    title: "ASIN",
                    field: "asin",
                    width: 120,
                    frozen: true
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 150,
                    cssClass: "text-primary fw-bold"
                },
                {
                    title: "Quantity",
                    field: "quantity",
                    hozAlign: "center",
                    sorter: "number",
                    width: 50
                },
                {
                    title: "Price",
                    field: "price",
                    hozAlign: "right",
                    sorter: "number",
                    width: 70,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Sales AMT",
                    field: "sale_amount",
                    hozAlign: "right",
                    sorter: "number",
                    width: 70,
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    }
                },
                {
                    title: "Order Date",
                    field: "order_date",
                    sorter: "datetime",
                    width: 20,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const date = new Date(value);
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                    }
                },
                {
                    title: "Status",
                    field: "status",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        let color = 'secondary';
                        if (value.toLowerCase().includes('shipped')) color = 'success';
                        else if (value.toLowerCase().includes('pending')) color = 'warning';
                        else if (value.toLowerCase().includes('cancelled')) color = 'danger';
                        else if (value.toLowerCase().includes('unshipped')) color = 'info';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                {
                    title: "Period",
                    field: "period",
                    width: 80
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
                    title: "T Weight",
                    field: "t_weight",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100
                },
                {
                    title: "Ship Cost",
                    field: "ship_cost",
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
                    title: "PFT Each",
                    field: "pft_each",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                    }
                },
                {
                    title: "PFT Each %",
                    field: "pft_each_pct",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || isNaN(value)) return '0.00%';
                        const numValue = parseFloat(value);
                        const color = numValue >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${numValue.toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "ROI %",
                    field: "roi",
                    hozAlign: "right",
                    sorter: "number",
                    width: 100,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        let color = '#6c757d'; // gray default
                        
                        // Color code based on ROI percentage
                        if (value < 50) color = '#dc3545'; // red
                        else if (value >= 50 && value < 75) color = '#ffc107'; // yellow
                        else if (value >= 75 && value <= 125) color = '#28a745'; // green
                        else if (value > 125) color = '#e83e8c'; // pink
                        
                        return `<span style="color: ${color}; font-weight: bold;">${parseFloat(value).toFixed(0)}%</span>`;
                    }
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            // Update summary after filter is applied
            setTimeout(function() {
                updateSummary();
            }, 100);
        });

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
                // Skip rows with empty SKU or order_id
                if (!row.sku || row.sku === '' || !row.order_id || row.order_id === '') {
                    return;
                }
                
                totalOrders++;
                const quantity = parseInt(row.quantity) || 0;
                const unitPrice = parseFloat(row.price) || 0; // This is per-unit price
                
                // Skip if quantity is 0
                if (quantity === 0) {
                    return;
                }
                
                totalQuantity += quantity;
                totalRevenue += unitPrice * quantity;
                
                // Calculate weighted price for average
                if (quantity > 0 && unitPrice > 0) {
                    totalWeightedPrice += unitPrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                
                // Get PFT and COGS from row data
                const pft = parseFloat(row.t_pft) || 0;
                const cogs = parseFloat(row.cogs) || 0;
                
                totalPft += pft;
                totalCogs += cogs;
                
                const l30Sales = quantity * unitPrice;
                totalL30Sales += l30Sales;
            });

            // Calculate average price (weighted by quantity)
            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;

            // Calculate GPFT Percentage: (Sum of T PFT / Sum of Total Sales) * 100
            const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
            
            // Calculate ROI Percentage: (Total PFT / Total COGS) * 100
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            // TikTok has no ads, so Net PFT = Gross PFT
            const adsSpent = 0;
            const adsPercentage = 0; // No ads for TikTok
            const netPft = totalPft;
            const nRoi = roiPercentage;

            // Update badges (matching eBay format exactly)
            // Update summary badges (Best Buy style)
            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-sales-badge').text('Total Sales: $' + Math.round(totalRevenue).toLocaleString());
            $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toLocaleString());
            $('#pft-total-badge').text('GPFT Total: $' + Math.round(totalPft).toLocaleString());
            $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalCogs).toLocaleString());
            
            // Color code PFT Total badge based on positive/negative
            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
            
            // TikTok has no ads currently
            if ($('#ads-spent-badge').length) {
                $('#ads-spent-badge').text('Ads Spent: $0');
            }
            if ($('#net-pft-badge').length) {
                $('#net-pft-badge').text('Net PFT: $' + Math.round(netPft).toLocaleString());
            }
            
            // Hide unused badges if they exist
            if ($('#total-revenue-badge').length) $('#total-revenue-badge').hide();
            if ($('#l30-sales-badge').length) $('#l30-sales-badge').hide();
            if ($('#tacos-percentage-badge').length) $('#tacos-percentage-badge').hide();
            if ($('#m-pft-badge').length) $('#m-pft-badge').hide();
            if ($('#n-roi-badge').length) {
                $('#n-roi-badge').text('N ROI: ' + nRoi.toFixed(1) + '%');
            }
            
            // Hide unused filter badges
            if ($('#ads-percentage-badge').length) $('#ads-percentage-badge').hide();
            if ($('#pft-percentage-filtered-badge').length) $('#pft-percentage-filtered-badge').hide();
            if ($('#pt-spent-badge').length) $('#pt-spent-badge').hide();
            if ($('#kw-spent-badge').length) $('#kw-spent-badge').hide();
            if ($('#hl-spent-badge').length) $('#hl-spent-badge').hide();
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/amazon-column-visibility', {
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

            fetch('/amazon-column-visibility', {
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
            fetch('/amazon-column-visibility', {
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
        
        // Update summary when filters change
        table.on('dataFiltered', function() {
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
            table.download("csv", "amazon_daily_sales_data.csv");
        });
    });
</script>
@endsection
