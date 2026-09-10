@extends('layouts.vertical', ['title' => 'eBay 3 Daily Sales Data', 'sidenav' => 'condensed'])

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

        /* Column visibility — 3 groups (Basic / Price / Other), same as /ebay/daily-sales */
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

        /* PARENT row light blue background */
        .tabulator-row.parent-row {
            background-color: #fffef2 !important;
        }
        .tabulator-row.parent-row:hover {
            background-color: #fefce8 !important;
        }
    
        /* sales-center-align: headers + cells */
        .tabulator .tabulator-header .tabulator-col,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            text-align: center !important;
        }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            text-align: center !important;
            justify-content: center !important;
        }
        .tabulator .tabulator-cell {
            text-align: center !important;
            justify-content: center !important;
        }

        #avg-price-badge,
        #pft-total-badge {
            display: none !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'eBay 3 Daily Sales Data',
        'sub_title' => 'eBay 3 Daily Sales Data Analysis (L30)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay 3 Daily Sales Data (L30)</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                        </ul>
                    </div>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge"
                            style="color: white; font-weight: bold;">Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge"
                            style="color: white; font-weight: bold;">Quantity: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge"
                            style="background-color: #0d6efd; color: white; font-weight: bold;">Sales: $0.00</span>
                        <span class="badge fs-6 p-2" id="y-sales-badge"
                            style="background-color: #0dcaf0; color: black; font-weight: bold;"
                            title="Yesterday's sales ({{ $yesterdayLabel ?? '' }} Pacific) from real eBay 3 orders — tax-inclusive, excl. cancelled & fully-refunded.">Y Sales: ${{ number_format((float) ($salesYesterday ?? 0), 2) }}</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge"
                            style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge"
                            style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 d-none" id="avg-price-badge"
                            style="color: black; font-weight: bold;" aria-hidden="true">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2 d-none" id="pft-total-badge"
                            style="color: white; font-weight: bold;" aria-hidden="true">GPFT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge"
                            style="color: white; font-weight: bold;">COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="ebay3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const COLUMN_VIS_KEY = "ebay3_daily_sales_column_visibility";
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
            console.log("Initializing Tabulator for eBay 3 Daily Sales Data...");
            table = new Tabulator("#ebay3-table", {
                columnDefaults: { hozAlign: "center", headerHozAlign: "center" },
                ajaxURL: "/ebay3/daily-sales-data",
                ajaxSorting: false,
                layout: "fitData",
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
                    column: "order_date",
                    dir: "desc"
                }],
                rowFormatter: function(row) {
                    const sku = row.getData().sku || '';
                    if (sku.toUpperCase().includes('PARENT')) {
                        row.getElement().classList.add('parent-row');
                    }
                },
                columns: [{
                        title: "Order ID",
                        field: "order_id",
                        width: 120,
                        frozen: true,
                        visible: false,
                    },
                    {
                        title: "Item ID",
                        field: "item_id",
                        width: 100,
                        frozen: true,
                        visible: false,
                    },
                    {
                        title: "Line Item ID",
                        field: "line_item_id",
                        width: 100,
                        visible: false,
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        width: 140,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold"
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        width: 120,
                        visible: true,
                    },
                    {
                        title: "Title",
                        field: "title",
                        width: 200,
                        visible: false,
                    },
                    {
                        title: "Quantity",
                        field: "quantity",
                        width: 50,
                        hozAlign: "center",
                        sorter: "number"
                    },
                    {
                        title: "Price",
                        field: "price",
                        width: 60,
                        hozAlign: "center",
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
                        title: "Sales AMT",
                        field: "sale_amount",
                        width: 65,
                        hozAlign: "center",
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
                        title: "Order Date",
                        field: "order_date",
                        width: 100,
                        sorter: "datetime",
                        visible: false,
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
                        width: 80,
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            let color = 'secondary';
                            if (value.toLowerCase().includes('fulfilled')) color = 'success';
                            else if (value.toLowerCase().includes('processing')) color = 'info';
                            else if (value.toLowerCase().includes('cancelled')) color = 'danger';
                            return `<span class="badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Period",
                        field: "period",
                        width: 60,
                        visible: false,
                    },
                    {
                        title: "LP",
                        field: "lp",
                        width: 55,
                        hozAlign: "center",
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
                        title: "eBay 3 Ship",
                        field: "ship",
                        width: 55,
                        hozAlign: "center",
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
                        title: "T Wt",
                        field: "t_weight",
                        width: 45,
                        hozAlign: "center",
                        sorter: "number"
                    },
                    {
                        title: "S Cost",
                        field: "ship_cost",
                        width: 55,
                        hozAlign: "center",
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
                        width: 55,
                        hozAlign: "center",
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
                        title: "PFT Ea",
                        field: "pft_each",
                        width: 60,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = value >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "PFT %",
                        field: "pft_each_pct",
                        width: 55,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = value >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color: ${color}; font-weight: bold;">${Math.round(parseFloat(value))}%</span>`;
                        }
                    },
                    {
                        title: "T PFT",
                        field: "pft",
                        width: 60,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = value >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color: ${color}; font-weight: bold;">$${parseFloat(value).toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "ROI %",
                        field: "roi",
                        width: 55,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            let color = '#6c757d';
                            if (value < 50) color = '#dc3545';
                            else if (value >= 50 && value < 75) color = '#ffc107';
                            else if (value >= 75 && value <= 125) color = '#28a745';
                            else if (value > 125) color = '#e83e8c';
                            return `<span style="color: ${color}; font-weight: bold;">${Math.round(parseFloat(value))}%</span>`;
                        }
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
                // eBay "Total sales (includes taxes)" = sum of each order's grand total
                // (total_amount includes shipping + tax), counted once per unique order.
                let totalOrderSales = 0;
                const seenOrders = new Set();

                data.forEach(row => {
                    if (!row.sku || row.sku === '' || !row.order_id || row.order_id === '') {
                        return;
                    }

                    // Add the order grand total once per unique order (matches eBay Total Sales)
                    if (!seenOrders.has(row.order_id)) {
                        seenOrders.add(row.order_id);
                        totalOrderSales += parseFloat(row.total_amount) || 0;
                    }

                    totalOrders++;
                    const quantity = parseInt(row.quantity) || 0;
                    const basePrice = parseFloat(row.price) || 0;

                    if (quantity === 0) {
                        return;
                    }

                    totalQuantity += quantity;
                    totalRevenue += basePrice * quantity;

                    if (quantity > 0 && basePrice > 0) {
                        totalWeightedPrice += basePrice * quantity;
                        totalQuantityForPrice += quantity;
                    }

                    const pft = parseFloat(row.pft) || 0;
                    const cogs = parseFloat(row.cogs) || 0;

                    totalPft += pft;
                    totalCogs += cogs;

                    const l30Sales = quantity * basePrice;
                    totalL30Sales += l30Sales;
                });

                const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
                const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
                const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                $('#total-orders-badge').text('Orders: ' + totalOrders.toLocaleString());
                $('#total-quantity-badge').text('Quantity: ' + totalQuantity.toLocaleString());
                $('#total-sales-badge').text('Sales: $' + totalOrderSales.toFixed(2));
                $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
                $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#pft-total-badge').text('GPFT: $' + totalPft.toFixed(2));

                const pftBadge = $('#pft-total-badge');
                if (totalPft >= 0) {
                    pftBadge.removeClass('bg-danger').addClass('bg-dark');
                } else {
                    pftBadge.removeClass('bg-dark').addClass('bg-danger');
                }

                $('#total-cogs-badge').text('COGS: $' + totalCogs.toFixed(2));
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
                    /^(order_id|item_id|line_item_id|sku|parent|quantity|order_date)$/i.test(f) ||
                    /\b(order id|item id|line item|sku|parent|quantity|order date)\b/i.test(t)
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

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/ebay3-daily-sales-column-visibility', {
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

                fetch('/ebay3-daily-sales-column-visibility', {
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
                fetch('/ebay3-daily-sales-column-visibility', {
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

            // Export functionality
            $('#export-btn').on('click', function() {
                table.download("csv", "ebay3_daily_sales_data.csv");
            });
        });
    </script>
@endsection
