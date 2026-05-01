@extends('layouts.vertical', ['title' => 'Reverb Daily Sales Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'Reverb Daily Sales Data (Last 30 Days)',
        'sub_title' => 'Reverb Daily Sales Analysis - Last 30 Days',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Reverb Daily Sales Data (Last 30 Days)</h4>
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
                    <h6 class="mb-3">Summary Statistics (Last 30 Days)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: #000; font-weight: bold;">Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: #000; font-weight: bold;">Quantity: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;">Sales: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">PFT: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">COGS: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-fees-badge" style="color: white; font-weight: bold;">T Fees: $0</span>
                        <span class="badge fs-6 p-2" id="fee-percentage-badge" style="background-color: #6c757d; color: white; font-weight: bold;">Fee: 0%</span>
                        <span class="badge fs-6 p-2" id="bump-fees-badge" style="background-color: #e83e8c; color: white; font-weight: bold;">Bump Fees: $0</span>
                        <span class="badge fs-6 p-2" id="bump-percentage-badge" style="background-color: #d63384; color: white; font-weight: bold;">Bump: 0%</span>
                        <span class="badge fs-6 p-2" id="selling-fees-badge" style="background-color: #20c997; color: white; font-weight: bold;">Selling Fees: $0</span>
                        <span class="badge fs-6 p-2" id="selling-percentage-badge" style="background-color: #198754; color: white; font-weight: bold;">Selling: 0%</span>
                        <span class="badge fs-6 p-2" id="checkout-fees-badge" style="background-color: #0dcaf0; color: white; font-weight: bold;">Checkout Fees: $0</span>
                        <span class="badge fs-6 p-2" id="checkout-percentage-badge" style="background-color: #0d6efd; color: white; font-weight: bold;">Checkout: 0%</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="reverb-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="reverb-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const COLUMN_VIS_KEY = "reverb_sales_tabulator_column_visibility";
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
        console.log("Initializing Tabulator for Reverb Daily Sales Data...");
        table = new Tabulator("#reverb-table", {
            ajaxURL: "/reverb/sales/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                if (Array.isArray(response)) {
                    console.log("Number of records:", response.length);
                }
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
                column: "order_date",
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
                    title: "SKU",
                    field: "sku",
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
                    title: "Order Date",
                    field: "order_date",
                    width: 120,
                    sorter: "date"
                },
                {
                    title: "Status",
                    field: "status",
                    width: 120,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        let color = 'secondary';
                        if (value.toLowerCase().includes('paid')) color = 'success';
                        else if (value.toLowerCase().includes('shipped')) color = 'info';
                        else if (value.toLowerCase().includes('cancelled') || value.toLowerCase().includes('cancel')) color = 'danger';
                        else if (value.toLowerCase().includes('pending')) color = 'warning';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                {
                    title: "Product Subtotal",
                    field: "product_subtotal",
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
                    title: "Amount",
                    field: "amount",
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
                    title: "Shipping",
                    field: "shipping_amount",
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
                    title: "Tax",
                    field: "tax_amount",
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
                    title: "Selling Fee",
                    field: "selling_fee",
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
                    title: "Bump Fee",
                    field: "bump_fee",
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
                    title: "Checkout Fee",
                    field: "direct_checkout_fee",
                    width: 110,
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
                    title: "Total Fees",
                    field: "total_fees",
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
                    title: "Payout",
                    field: "payout_amount",
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
                    title: "Buyer Name",
                    field: "buyer_name",
                    width: 150
                },
                {
                    title: "Buyer Email",
                    field: "buyer_email",
                    width: 180
                },
                {
                    title: "City",
                    field: "shipping_city",
                    width: 120
                },
                {
                    title: "State",
                    field: "shipping_state",
                    width: 100
                },
                {
                    title: "Country",
                    field: "shipping_country",
                    width: 100
                },
                {
                    title: "Payment Method",
                    field: "payment_method",
                    width: 120
                },
                {
                    title: "Order Type",
                    field: "order_type",
                    width: 120
                },
                {
                    title: "Shipment Status",
                    field: "shipment_status",
                    width: 120
                },
                {
                    title: "Paid At",
                    field: "paid_at",
                    width: 140
                },
                {
                    title: "Shipped At",
                    field: "shipped_at",
                    width: 140
                },
                {
                    title: "Title",
                    field: "title",
                    width: 250
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
        });

        // Update summary stats
        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalFees = 0;
            let totalPft = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;
            let totalCogs = 0;
            let l30Sales = 0; // Sales for last 30 days only
            let totalBumpFees = 0; // Sum of bump fees
            let totalSellingFees = 0; // Sum of selling fees
            let totalCheckoutFees = 0; // Sum of checkout fees
            
            // Calculate date 30 days ago (through yesterday, matching ChannelMaster)
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            yesterday.setHours(23, 59, 59, 999); // End of yesterday
            
            const thirtyDaysAgo = new Date(yesterday);
            thirtyDaysAgo.setDate(yesterday.getDate() - 29); // 30 days total (yesterday + 29 days back)
            thirtyDaysAgo.setHours(0, 0, 0, 0); // Start of day

            data.forEach(row => {
                // Skip rows with empty SKU or order_number
                if (!row.sku || row.sku === '' || !row.order_number || row.order_number === '') {
                    return;
                }
                
                // Skip cancelled orders
                const status = (row.status || '').toLowerCase();
                if (status.includes('cancel') || status.includes('refund')) {
                    return;
                }
                
                // Check if order is within last 30 days - FILTER ALL DATA
                if (!row.order_date) {
                    return; // Skip orders without date
                }
                
                const orderDate = new Date(row.order_date);
                if (orderDate < thirtyDaysAgo) {
                    return; // Skip orders older than 30 days
                }
                
                // All calculations below are now for L30 only
                totalOrders++;
                
                const quantity = parseInt(row.quantity) || 1;
                totalQuantity += quantity;
                
                // Revenue from product_subtotal
                const lineRevenue = parseFloat(row.product_subtotal) || parseFloat(row.amount) || 0;
                totalRevenue += lineRevenue;
                l30Sales += lineRevenue; // Same as totalRevenue now
                
                // Fees
                totalFees += parseFloat(row.total_fees) || 0;
                
                // Bump Fees
                totalBumpFees += parseFloat(row.bump_fee) || 0;
                
                // Selling Fees
                totalSellingFees += parseFloat(row.selling_fee) || 0;
                
                // Checkout Fees
                totalCheckoutFees += parseFloat(row.direct_checkout_fee) || 0;
                
                // For weighted average price
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
            
            // Calculate Fee Percentage: (Total Fees / Total Revenue) * 100
            const feePercentage = totalRevenue > 0 ? (totalFees / totalRevenue) * 100 : 0;
            
            // Calculate Bump Percentage: (Bump Fees / Total Revenue) * 100
            const bumpPercentage = totalRevenue > 0 ? (totalBumpFees / totalRevenue) * 100 : 0;
            
            // Calculate Selling Percentage: (Selling Fees / Total Revenue) * 100
            const sellingPercentage = totalRevenue > 0 ? (totalSellingFees / totalRevenue) * 100 : 0;
            
            // Calculate Checkout Percentage: (Checkout Fees / Total Revenue) * 100
            const checkoutPercentage = totalRevenue > 0 ? (totalCheckoutFees / totalRevenue) * 100 : 0;

            $('#total-orders-badge').text('Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Quantity: ' + totalQuantity.toLocaleString());
            $('#total-revenue-badge').text('Sales: $' + Math.round(totalRevenue).toLocaleString());
            $('#pft-percentage-badge').text('PFT: ' + Math.round(pftPercentage) + '%');
            $('#roi-percentage-badge').text('ROI: ' + Math.round(roiPercentage) + '%');
            $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toLocaleString());
            $('#pft-total-badge').text('PFT: $' + Math.round(totalPft).toLocaleString());
            
            // Color code PFT Total badge
            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
            
            $('#total-cogs-badge').text('COGS: $' + Math.round(totalCogs).toLocaleString());
            $('#total-fees-badge').text('T Fees: $' + Math.round(totalFees).toLocaleString());
            $('#fee-percentage-badge').text('Fee: ' + Math.round(feePercentage) + '%');
            $('#bump-fees-badge').text('Bump Fees: $' + Math.round(totalBumpFees).toLocaleString());
            $('#bump-percentage-badge').text('Bump: ' + Math.round(bumpPercentage) + '%');
            $('#selling-fees-badge').text('Selling Fees: $' + Math.round(totalSellingFees).toLocaleString());
            $('#selling-percentage-badge').text('Selling: ' + Math.round(sellingPercentage) + '%');
            $('#checkout-fees-badge').text('Checkout Fees: $' + Math.round(totalCheckoutFees).toLocaleString());
            $('#checkout-percentage-badge').text('Checkout: ' + Math.round(checkoutPercentage) + '%');
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/reverb/sales/column-visibility', {
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

            fetch('/reverb/sales/column-visibility', {
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
            fetch('/reverb/sales/column-visibility', {
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
            table.download("csv", "reverb_sales_data.csv");
        });
    });
</script>
@endsection
