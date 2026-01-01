@extends('layouts.vertical', ['title' => "Macy's Daily Sales Data", 'sidenav' => 'condensed'])

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
        'page_title' => "Macy's Daily Sales Data",
        'sub_title' => "Macy's Daily Sales Data Analysis (L30)",
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Macy's Daily Sales Data (L30)</h4>
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
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge"
                            style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge"
                            style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge"
                            style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge"
                            style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge"
                            style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge"
                            style="color: white; font-weight: bold;">GPFT Total: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge"
                            style="color: white; font-weight: bold;">Total COGS: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="macys-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="macys-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const COLUMN_VIS_KEY = "macys_sales_column_visibility";
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
            console.log("Initializing Tabulator for Macy's Daily Sales Data...");
            table = new Tabulator("#macys-table", {
                ajaxURL: "/macys/daily-sales-data",
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
                columns: [{
                        title: "Order ID",
                        field: "order_id",
                        width: 150,
                        frozen: true,
                        visible: true,
                    },
                    {
                        title: "Channel Order ID",
                        field: "channel_order_id",
                        width: 150,
                        visible: false,
                    },
                    {
                        title: "Line ID",
                        field: "order_line_id",
                        width: 100,
                        visible: false,
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
                        title: "Unit Price",
                        field: "unit_price",
                        hozAlign: "right",
                        sorter: "number",
                        width: 80,
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
                        width: 80,
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        }
                    },
                    {
                        title: "Currency",
                        field: "currency",
                        width: 80,
                        visible: false
                    },
                    {
                        title: "Order Date",
                        field: "order_date",
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
                        title: "Status",
                        field: "status",
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            let color = 'secondary';
                            if (value.toLowerCase().includes('shipped') || value.toLowerCase().includes('received')) color = 'success';
                            else if (value.toLowerCase().includes('processing') || value.toLowerCase().includes('staging')) color = 'info';
                            else if (value.toLowerCase().includes('cancelled') || value.toLowerCase().includes('refused')) color = 'danger';
                            return `<span class="badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Period",
                        field: "period",
                        width: 80,
                        visible: false,
                    },
                    {
                        title: "LP",
                        field: "lp",
                        hozAlign: "right",
                        sorter: "number",
                        width: 80,
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
                        width: 80,
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
                        width: 80,
                        visible: false
                    },
                    {
                        title: "Ship Cost",
                        field: "ship_cost",
                        hozAlign: "right",
                        sorter: "number",
                        width: 80,
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
                        width: 80,
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
                        width: 80,
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
                        width: 80,
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
                        width: 100,
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
                        width: 80,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            let color = '#6c757d';

                            if (value < 50) color = '#dc3545';
                            else if (value >= 50 && value < 75) color = '#ffc107';
                            else if (value >= 75 && value <= 125) color = '#28a745';
                            else if (value > 125) color = '#e83e8c';

                            return `<span style="color: ${color}; font-weight: bold;">${parseFloat(value).toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "State",
                        field: "shipping_state",
                        width: 80,
                        visible: false
                    },
                    {
                        title: "City",
                        field: "shipping_city",
                        width: 100,
                        visible: false
                    }
                ]
            });

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("sku", "like", value);
                setTimeout(function() {
                    updateSummary();
                }, 100);
            });

            // Update summary stats
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
                    if (!row.sku || row.sku === '' || !row.order_id || row.order_id === '') {
                        return;
                    }

                    totalOrders++;
                    const quantity = parseInt(row.quantity) || 0;
                    const unitPrice = parseFloat(row.unit_price) || 0;

                    if (quantity === 0) {
                        return;
                    }

                    totalQuantity += quantity;
                    totalRevenue += unitPrice * quantity;

                    if (quantity > 0 && unitPrice > 0) {
                        totalWeightedPrice += unitPrice * quantity;
                        totalQuantityForPrice += quantity;
                    }

                    const pft = parseFloat(row.pft) || 0;
                    const cogs = parseFloat(row.cogs) || 0;

                    totalPft += pft;
                    totalCogs += cogs;

                    const l30Sales = quantity * unitPrice;
                    totalL30Sales += l30Sales;
                });

                const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
                const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
                const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
                $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
                $('#total-sales-badge').text('Total Sales: $' + Math.round(totalRevenue).toLocaleString());
                $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
                $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toLocaleString());
                $('#pft-total-badge').text('GPFT Total: $' + Math.round(totalPft).toLocaleString());

                const pftBadge = $('#pft-total-badge');
                if (totalPft >= 0) {
                    pftBadge.removeClass('bg-danger').addClass('bg-dark');
                } else {
                    pftBadge.removeClass('bg-dark').addClass('bg-danger');
                }

                $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalCogs).toLocaleString());
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/macys-daily-sales-column-visibility', {
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

                fetch('/macys-daily-sales-column-visibility', {
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
                fetch('/macys-daily-sales-column-visibility', {
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

            table.on('dataProcessed', function() {
                updateSummary();
            });

            table.on('renderComplete', function() {
                updateSummary();
            });

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
                table.download("csv", "macys_daily_sales_data.csv");
            });
        });
    </script>
@endsection
