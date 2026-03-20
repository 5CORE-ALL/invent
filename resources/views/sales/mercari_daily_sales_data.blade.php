@extends('layouts.vertical', ['title' => 'Mercari With Ship Daily Sales', 'sidenav' => 'condensed'])

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
        'page_title' => 'Mercari With Ship Daily Sales',
        'sub_title' => 'Orders where seller pays shipping (buyer_shipping_fee = 0)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Mercari With Ship Daily Sales <span class="badge bg-success">Seller Pays Shipping</span></h4>
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

                    <a href="/mercari-without-ship" class="btn btn-sm btn-warning">
                        <i class="fa fa-money-bill"></i> View Without Ship
                    </a>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge"
                            style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-sales-badge"
                            style="color: white; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge"
                            style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge"
                            style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge"
                            style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge"
                            style="color: white; font-weight: bold;">GPFT Total: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge"
                            style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge fs-6 p-2" id="net-proceeds-badge"
                            style="background-color: #17a2b8; color: white; font-weight: bold;">Net Proceeds: $0.00</span>
                        <span class="badge fs-6 p-2" id="total-fees-badge"
                            style="background-color: #28a745; color: white; font-weight: bold;">Total Fees: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="mercari-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Item ID Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="item-search" class="form-control form-control-sm"
                            placeholder="Search by Item ID or Title...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="mercari-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload Mercari Daily Data</h5>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const COLUMN_VIS_KEY = "mercari_sales_column_visibility";
        let table = null;

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className =
                `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
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
            console.log("Initializing Tabulator for Mercari With Ship Daily Sales...");
            table = new Tabulator("#mercari-table", {
                ajaxURL: "/mercari/daily-data-with-ship",
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
                    column: "sold_date",
                    dir: "desc"
                }],
                columns: [{
                        title: "Item ID",
                        field: "item_id",
                        width: 150,
                        frozen: true,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Item ID...",
                    },
                    {
                        title: "Item Title",
                        field: "item_title",
                        width: 300,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Title...",
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        width: 150,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold"
                    },
                    {
                        title: "Order Status",
                        field: "order_status",
                        width: 120,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            let color = 'secondary';
                            if (value.toLowerCase().includes('completed')) color = 'success';
                            else if (value.toLowerCase().includes('cancelled')) color = 'danger';
                            else if (value.toLowerCase().includes('wait')) color = 'warning';
                            return `<span class="badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Sold Date",
                        field: "sold_date",
                        sorter: "datetime",
                        width: 120,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            const date = new Date(value);
                            return date.toLocaleDateString();
                        }
                    },
                    {
                        title: "Canceled Date",
                        field: "canceled_date",
                        sorter: "datetime",
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            const date = new Date(value);
                            return date.toLocaleDateString();
                        }
                    },
                    {
                        title: "Completed Date",
                        field: "completed_date",
                        sorter: "datetime",
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            const date = new Date(value);
                            return date.toLocaleDateString();
                        }
                    },
                    {
                        title: "Shipped To State",
                        field: "shipped_to_state",
                        width: 120,
                        visible: false,
                    },
                    {
                        title: "Shipped From State",
                        field: "shipped_from_state",
                        width: 120,
                        visible: false,
                    },
                    {
                        title: "Item Price",
                        field: "item_price",
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
                        title: "Buyer Shipping Fee",
                        field: "buyer_shipping_fee",
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
                        title: "Seller Shipping Fee",
                        field: "seller_shipping_fee",
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
                        title: "Mercari Selling Fee",
                        field: "mercari_selling_fee",
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
                        title: "Payment Processing Fee (Seller)",
                        field: "payment_processing_fee_charged_to_seller",
                        hozAlign: "right",
                        sorter: "number",
                        width: 150,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Shipping Adjustment Fee",
                        field: "shipping_adjustment_fee",
                        hozAlign: "right",
                        sorter: "number",
                        width: 150,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Penalty Fee",
                        field: "penalty_fee",
                        hozAlign: "right",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Net Seller Proceeds",
                        field: "net_seller_proceeds",
                        hozAlign: "right",
                        sorter: "number",
                        width: 140,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = value >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value || 0).toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Sales Tax (Buyer)",
                        field: "sales_tax_charged_to_buyer",
                        hozAlign: "right",
                        sorter: "number",
                        width: 120,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Merchant Fees (Buyer)",
                        field: "merchant_fees_charged_to_buyer",
                        hozAlign: "right",
                        sorter: "number",
                        width: 140,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Service Fee (Buyer)",
                        field: "service_fee_charged_to_buyer",
                        hozAlign: "right",
                        sorter: "number",
                        width: 120,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Buyer Protection (Buyer)",
                        field: "buyer_protection_charged_to_buyer",
                        hozAlign: "right",
                        sorter: "number",
                        width: 150,
                        visible: false,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Payment Processing Fee (Buyer)",
                        field: "payment_processing_fee_charged_to_buyer",
                        hozAlign: "right",
                        sorter: "number",
                        width: 180,
                        visible: false,
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
                    }
                ]
            });

            // Item Search functionality
            $('#item-search').on('keyup', function() {
                const value = $(this).val();
                // Search in both item_id and item_title
                table.setFilter(function(data) {
                    if (!value) return true;
                    const searchValue = value.toLowerCase();
                    return (data.item_id && data.item_id.toLowerCase().includes(searchValue)) ||
                           (data.item_title && data.item_title.toLowerCase().includes(searchValue));
                });
                setTimeout(function() {
                    updateSummary();
                }, 100);
            });

            // Update summary stats
            function updateSummary() {
                const data = table.getData("active");
                let totalOrders = 0;
                let totalSales = 0;
                let totalRevenue = 0;
                let totalPft = 0;
                let totalCogs = 0;
                let totalNetProceeds = 0;
                let totalFees = 0;

                data.forEach(row => {
                    if (!row.item_id || row.item_id === '') {
                        return;
                    }

                    // Skip cancelled orders
                    const orderStatus = (row.order_status || '').toLowerCase();
                    const isCancelled = row.canceled_date !== null && row.canceled_date !== '' ||
                                       orderStatus.includes('cancelled') || 
                                       orderStatus.includes('canceled');
                    if (isCancelled) {
                        return;
                    }

                    totalOrders++;
                    const itemPrice = parseFloat(row.item_price) || 0;
                    const netProceeds = parseFloat(row.net_seller_proceeds) || 0;
                    const mercariFee = parseFloat(row.mercari_selling_fee) || 0;
                    const paymentFee = parseFloat(row.payment_processing_fee_charged_to_seller) || 0;
                    const shippingAdj = parseFloat(row.shipping_adjustment_fee) || 0;
                    const penalty = parseFloat(row.penalty_fee) || 0;
                    const lp = parseFloat(row.lp) || 0;
                    const ship = parseFloat(row.ship) || 0;

                    totalSales += itemPrice;
                    totalRevenue += itemPrice;
                    totalNetProceeds += netProceeds;
                    totalFees += mercariFee + paymentFee + shippingAdj + penalty;

                    // Calculate PFT: (Item Price × 0.88) - LP - Ship
                    const pft = (itemPrice * 0.88) - lp - ship;
                    totalPft += pft;
                    totalCogs += lp;
                });

                // Calculate average price
                const avgPrice = totalOrders > 0 ? totalSales / totalOrders : 0;

                // Calculate PFT Percentage: (Total PFT / Total Sales) * 100
                const pftPercentage = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;

                // Calculate ROI Percentage: (Total PFT / Total COGS) * 100
                const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                // Update badges
                $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
                $('#total-sales-badge').text('Total Sales: $' + totalSales.toFixed(2));
                $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
                $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
                $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#pft-total-badge').text('GPFT Total: $' + totalPft.toFixed(2));
                $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
                $('#net-proceeds-badge').text('Net Proceeds: $' + totalNetProceeds.toFixed(2));
                $('#total-fees-badge').text('Total Fees: $' + totalFees.toFixed(2));

                // Color code PFT Total badge
                const pftBadge = $('#pft-total-badge');
                if (totalPft >= 0) {
                    pftBadge.removeClass('bg-danger').addClass('bg-dark');
                } else {
                    pftBadge.removeClass('bg-dark').addClass('bg-danger');
                }
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/mercari-column-visibility', {
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

                fetch('/mercari-column-visibility', {
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
                fetch('/mercari-column-visibility', {
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
                table.download("csv", "mercari_with_ship_daily_sales_data.csv");
            });

            // Upload functionality
            $('#upload-btn').on('click', function() {
                const fileInput = document.getElementById('file-input');
                const file = fileInput.files[0];
                
                if (!file) {
                    showToast('Please select a file', 'error');
                    return;
                }

                const uploadId = 'mercari_' + Date.now();
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
                    url: '/mercari/upload-daily-data',
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
                            $('#upload-status').text(`Processing: ${response.imported} imported, ${response.skipped} skipped`);

                            if (chunk === totalChunks - 1) {
                                // Last chunk completed
                                showToast(`Successfully imported ${response.imported} records!`, 'success');
                                $('#upload-progress').hide();
                                $('#upload-btn').prop('disabled', false);
                                $('#uploadModal').modal('hide');
                                $('#file-input').val('');
                                
                                // Reload table data
                                table.replaceData();
                            }
                        } else {
                            showToast('Upload failed: ' + (response.message || 'Unknown error'), 'error');
                            $('#upload-progress').hide();
                            $('#upload-btn').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Upload error:', error);
                        showToast('Upload failed: ' + (xhr.responseJSON?.message || error), 'error');
                        $('#upload-progress').hide();
                        $('#upload-btn').prop('disabled', false);
                    }
                });
            }
        });
    </script>
@endsection

