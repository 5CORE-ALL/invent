@extends('layouts.vertical', ['title' => 'Amz Daily Sales Data', 'sidenav' => 'condensed'])

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

        /* Column visibility — 3 groups (Basic / Price / Other), same headers as /amazon-tabulator-view */
        #column-dropdown-menu.show {
            min-width: min(92vw, 560px);
            max-width: min(96vw, 640px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            margin: 0;
            font-size: 0.8rem;
            cursor: pointer;
            border-radius: 4px;
        }
        #column-dropdown-menu .col-vis-item > label:hover {
            background: #e9ecef;
        }
        #column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
        }
        @media (max-width: 576px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: 1fr;
            }
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
        'page_title' => 'Amz Daily Sales Data',
        'sub_title' => 'Amz Daily Sales Data (Last ' . (int) ($amazonSalesWindowDays ?? \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS) . ' Days, California)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Amz Daily Sales Data </h4>
                <p class="text-muted small mb-2" id="date-range-info">
                    Date range (Pacific): {{ $amazonSalesWindowStart ?? '—' }} – {{ $amazonSalesWindowEnd ?? '—' }}
                    — {{ (int) ($amazonSalesWindowDays ?? \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS) }} days through yesterday (today excluded).
                    <strong>Total Sales</strong> uses mode <code>{{ $amazonSalesTotalMode ?? 'lines' }}</code>
                    (<code>AMAZON_SALES_TOTAL_MODE</code> in <code>.env</code>):
                    <code>lines</code> = Σ line <code>price</code> only — default, matches Seller Central "Ordered Product Sales" (tax excluded);
                    <code>order_greatest</code> = Σ per order max(line prices, <code>total_amount</code>, JSON OrderTotal) — includes tax/shipping;
                    <code>qty_times_price</code> = legacy Σ (quantity × price).
                    Canceled / Cancelled excluded.
                </p>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
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
                        <span class="badge fs-6 p-2" id="amazon-sales-total-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"> Total Sales: ${{ number_format($amazonSalesTotal ?? 0, 2) }}</span>
                        <span class="badge fs-6 p-2" id="y-sales-badge" title="Yesterday's product sales, tax excluded ({{ $amazonYesterdayLabel ?? '' }} Pacific) — matches Amz Seller Central 'Sales'" style="background-color: #0dcaf0; color: black; font-weight: bold;">Y Sales: ${{ number_format($salesYesterday ?? 0, 2) }}</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">GPFT Total: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="amazon-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="amazon-table" style="flex: 1;"></div>
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
    // Server-computed rolling total (amazon_orders effective total; orders without items included)
    const SERVER_AMAZON_SALES_TOTAL = {{ $amazonSalesTotal ?? 0 }};

    function formatUsdTwoDecimals(value) {
        return (Number(value) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

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
        console.log("Initializing Tabulator for Amz Daily Sales Data...");
        table = new Tabulator("#amazon-table", {
            ajaxURL: "/amazon/daily-sales-data",
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
                        // Extract and display date range
                        const dates = response.map(r => r.order_date).filter(d => d);
                        if (dates.length > 0) {
                            dates.sort();
                            const startDate = new Date(dates[0]).toLocaleDateString();
                            const endDate = new Date(dates[dates.length - 1]).toLocaleDateString();
                        }
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
                    title: "Title",
                    field: "title",
                    width: 280,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const maxLen = 150;
                        return value.length > maxLen ? value.substring(0, maxLen) + '...' : value;
                    },
                    tooltip: true
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
                    width: 80,
                    headerTooltip: "API period label (e.g. L{{ (int) ($amazonSalesWindowDays ?? \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS) }}). Matches the page: {{ (int) ($amazonSalesWindowDays ?? \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS) }} Pacific calendar days through yesterday, today excluded."
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
                    headerTooltip: "Shipping cost. Default $6.00 is hidden; only non-default amounts show.",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (!Number.isFinite(value) || Math.abs(value - 6) < 0.005) return '';
                        return '$' + value.toFixed(2);
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
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${parseFloat(value).toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "T PFT",
                    field: "pft",
                    hozAlign: "right",
                    sorter: "number",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
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
            let uniqueOrderIds = new Set(); // Track unique order IDs
            const uniqueOrderTotals = {};   // order_id -> order_total_amount (for accurate sales sum)
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalPft = 0;
            let totalSkuLineSales = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;
            let totalCogs = 0;

            data.forEach(row => {
                // Skip rows without order_id
                if (!row.order_id || row.order_id === '') {
                    return;
                }

                // Track unique order and its order-level total (only once per order)
                uniqueOrderIds.add(row.order_id);
                if (!(row.order_id in uniqueOrderTotals)) {
                    uniqueOrderTotals[row.order_id] = parseFloat(row.order_total_amount) || 0;
                }

                // SKU-level calculations — only when SKU present and qty > 0
                if (!row.sku || row.sku === '') {
                    return;
                }

                const quantity = parseInt(row.quantity) || 0;
                const basePrice = parseFloat(row.price) || 0;
                const saleAmount = parseFloat(row.sale_amount) || 0; // Use pre-calculated sale_amount
                
                // Skip if quantity is 0
                if (quantity === 0) {
                    return;
                }
                
                // Total revenue = use pre-calculated sale_amount (avoids rounding errors)
                totalQuantity += quantity;
                totalRevenue += saleAmount;
                
                // Calculate weighted price
                if (quantity > 0 && basePrice > 0) {
                    totalWeightedPrice += basePrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                
                // Get PFT and COGS from row data
                const pft = parseFloat(row.pft) || 0;
                const cogs = parseFloat(row.cogs) || 0;
                
                totalPft += pft;
                totalCogs += cogs;
                
                totalSkuLineSales += saleAmount;
            });

            // Calculate average price (weighted by quantity)
            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            // Sum of unique order-level totals (accurate even for orders without items in table)
            const totalSalesByOrders = Object.values(uniqueOrderTotals).reduce((sum, v) => sum + v, 0);

            // Calculate PFT Percentage: (Sum of T PFT / Sum of Total Sales) * 100
            const pftPercentage = totalSkuLineSales > 0 ? (totalPft / totalSkuLineSales) * 100 : 0;
            
            // Calculate ROI Percentage: (PFT Total / Total COGS) * 100
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            // Check if data is filtered (compare active data with total data or check for filters)
            const totalDataCount = table.getDataCount();
            const activeDataCount = data.length;
            const skuSearchValue = $('#sku-search').val() || '';
            const hasTableFilters = table.modules.filter && table.modules.filter.getFilters().length > 0;
            const isFiltered = activeDataCount < totalDataCount || hasTableFilters || skuSearchValue.trim() !== '';

            // Update badges (matching eBay format exactly)
            const totalOrders = uniqueOrderIds.size; // Count unique orders
            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            // Unfiltered: use server-side total (amazon_orders direct sum, includes orders without items)
            // Filtered: use JS order-total sum so filtered result is accurate
            const displaySales = isFiltered ? totalSalesByOrders : SERVER_AMAZON_SALES_TOTAL;
            $('#amazon-sales-total-badge').text('Total Sales: $' + formatUsdTwoDecimals(displaySales));
            $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
            $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('GPFT Total: $' + totalPft.toFixed(2));
            
            // Color code PFT Total badge
            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
            
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
        }

        const COL_VIS_CATEGORY_KEYS = ['basic', 'price', 'other'];
        const COL_VIS_CATEGORY_LABELS = {
            basic: 'Basic',
            price: 'Price',
            other: 'Other'
        };

        function classifyDailySalesColumn(field, title) {
            const f = String(field || '');
            const t = String(title || field || '').toLowerCase();

            if (
                /^(price|sale_amount|lp|ship|ship_cost|cogs|pft_each|pft_each_pct|pft|roi)$/i.test(f) ||
                /\b(price|sales?\s*amt|lp|ship|cogs|pft|roi)\b/i.test(t)
            ) {
                return 'price';
            }
            if (
                /^(order_id|asin|sku|title|quantity|order_date)$/i.test(f) ||
                /\b(order id|asin|sku|title|quantity|order date)\b/i.test(t)
            ) {
                return 'basic';
            }
            return 'other';
        }

        function syncDailySalesGroupHeaderCheckbox(groupEl) {
            if (!groupEl) return;
            const headerCb = groupEl.querySelector('.col-vis-group-toggle');
            const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
            if (!headerCb || !itemCbs.length) return;
            const checked = Array.from(itemCbs).filter(function(cb) { return cb.checked; }).length;
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }

        // Build Column Visibility Dropdown — 3 groups (Basic / Price / Other)
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
                    const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};

                    const groupsLi = document.createElement("li");
                    groupsLi.className = "col-vis-full";
                    const groupsWrap = document.createElement("div");
                    groupsWrap.className = "col-vis-groups";

                    const lists = {};
                    const groupEls = {};
                    COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                        const group = document.createElement("div");
                        group.className = "col-vis-group";
                        group.dataset.category = cat;

                        const titleEl = document.createElement("label");
                        titleEl.className = "col-vis-group-title";
                        const groupCb = document.createElement("input");
                        groupCb.type = "checkbox";
                        groupCb.className = "col-vis-group-toggle";
                        groupCb.dataset.group = cat;
                        groupCb.title = "Select / deselect all in " + COL_VIS_CATEGORY_LABELS[cat];
                        titleEl.appendChild(groupCb);
                        titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                        group.appendChild(titleEl);

                        const list = document.createElement("ul");
                        list.className = "col-vis-group-list";
                        list.dataset.category = cat;
                        group.appendChild(list);
                        groupsWrap.appendChild(group);
                        lists[cat] = list;
                        groupEls[cat] = group;
                    });

                    table.getColumns().forEach(function(col) {
                        const def = col.getDefinition();
                        if (!def.field) return;

                        const title = String(def.title || def.field);
                        const cat = classifyDailySalesColumn(def.field, title);
                        const isVisible = map.hasOwnProperty(def.field)
                            ? (map[def.field] !== false)
                            : col.isVisible();

                        const li = document.createElement("li");
                        li.className = "col-vis-item";
                        li.dataset.field = def.field;

                        const label = document.createElement("label");
                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox";
                        checkbox.value = def.field;
                        checkbox.className = "col-vis-field-toggle";
                        checkbox.checked = isVisible;

                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(' ' + title));
                        li.appendChild(label);
                        lists[cat].appendChild(li);
                    });

                    COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                        syncDailySalesGroupHeaderCheckbox(groupEls[cat]);
                    });

                    groupsLi.appendChild(groupsWrap);
                    menu.appendChild(groupsLi);
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

        // Toggle column / group from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type !== 'checkbox') return;

            if (e.target.classList.contains('col-vis-group-toggle')) {
                const groupEl = e.target.closest('.col-vis-group');
                const itemCbs = groupEl
                    ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                    : [];
                itemCbs.forEach(function(cb) {
                    cb.checked = e.target.checked;
                    const col = table.getColumn(cb.value);
                    if (!col) return;
                    if (e.target.checked) col.show();
                    else col.hide();
                });
                e.target.indeterminate = false;
                saveColumnVisibilityToServer();
                return;
            }

            const field = e.target.value;
            const col = table.getColumn(field);
            if (col) {
                if (e.target.checked) col.show();
                else col.hide();
            }
            syncDailySalesGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
            saveColumnVisibilityToServer();
        });

        document.getElementById("column-dropdown-menu").addEventListener("click", function(e) {
            if (e.target.closest('label') || e.target.type === 'checkbox') {
                e.stopPropagation();
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
