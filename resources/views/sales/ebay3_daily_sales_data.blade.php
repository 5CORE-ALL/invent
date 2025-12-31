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

        /* PARENT row light blue background */
        .tabulator-row.parent-row {
            background-color: rgba(69, 233, 255, 0.25) !important;
        }
        .tabulator-row.parent-row:hover {
            background-color: rgba(69, 233, 255, 0.35) !important;
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
                            style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0.00</span>
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
                        <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge"
                            style="color: white; font-weight: bold;">L30 Sales: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge"
                            style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                        <span class="badge fs-6 p-2" id="kw-spent-badge"
                            style="background-color: #dc3545; color: white; font-weight: bold;">KW Spent:
                            ${{ number_format($kwSpent ?? 0, 0) }}</span>
                        <span class="badge fs-6 p-2" id="pmt-spent-badge"
                            style="background-color: #28a745; color: white; font-weight: bold;">PMT Spent:
                            ${{ number_format($pmtSpent ?? 0, 0) }}</span>
                        <span class="badge fs-6 p-2" id="total-ads-spent-badge"
                            style="background-color: #6610f2; color: white; font-weight: bold;">Total Ads:
                            ${{ number_format(($kwSpent ?? 0) + ($pmtSpent ?? 0), 0) }}</span>
                        <span class="badge fs-6 p-2" id="tacos-percentage-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;">TACOS %: 0%</span>
                        <span class="badge fs-6 p-2" id="m-pft-badge"
                            style="background-color: #fd7e14; color: white; font-weight: bold;">N PFT: 0%</span>
                        <span class="badge fs-6 p-2" id="n-roi-badge"
                            style="background-color: #e83e8c; color: white; font-weight: bold;">N ROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percentage-badge"
                            style="background-color: #17a2b8; color: white; font-weight: bold; display: none;">Ads %: 0%</span>
                        <span class="badge fs-6 p-2" id="pft-percentage-filtered-badge"
                            style="background-color: #20c997; color: white; font-weight: bold; display: none;">PFT %: 0%</span>
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
        const KW_SPENT = {{ $kwSpent ?? 0 }};
        const PMT_SPENT = {{ $pmtSpent ?? 0 }};
        const TOTAL_ADS_SPENT = KW_SPENT + PMT_SPENT;

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
                        title: "Sales AMT",
                        field: "sale_amount",
                        width: 65,
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
                        title: "eBay 3 Ship",
                        field: "ship",
                        width: 55,
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
                        title: "T Wt",
                        field: "t_weight",
                        width: 45,
                        hozAlign: "right",
                        sorter: "number"
                    },
                    {
                        title: "S Cost",
                        field: "ship_cost",
                        width: 55,
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
                        width: 55,
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
                        title: "PFT Ea",
                        field: "pft_each",
                        width: 60,
                        hozAlign: "right",
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
                        hozAlign: "right",
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
                        hozAlign: "right",
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
                        hozAlign: "right",
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
                    },
                    {
                        title: "KW",
                        field: "kw_spent",
                        width: 60,
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
                        title: "PMT",
                        field: "pmt_spent",
                        width: 60,
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
                        title: "T Spent",
                        field: "total_spent",
                        width: 65,
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const kwSpent = parseFloat(row.kw_spent) || 0;
                            const pmtSpent = parseFloat(row.pmt_spent) || 0;
                            const totalSpent = kwSpent + pmtSpent;
                            return '$' + totalSpent.toFixed(2);
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

                // Track unique parents for KW spend (parent-wise)
                const uniqueParentsKw = {};
                // Track unique item_ids for PMT spend (item-wise)
                const uniqueItemsPmt = {};
                // Track unique SKUs to avoid counting ads multiple times
                const countedSkusKw = new Set();
                const countedSkusPmt = new Set();

                data.forEach(row => {
                    if (!row.sku || row.sku === '' || !row.order_id || row.order_id === '') {
                        return;
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

                    // KW Spent is per parent - only count once per parent (not per SKU occurrence)
                    if (row.parent && !uniqueParentsKw[row.parent]) {
                        const kwSpent = parseFloat(row.kw_spent) || 0;
                        uniqueParentsKw[row.parent] = kwSpent;
                    }

                    // PMT Spent is per item_id - only count once per item_id (not per SKU occurrence)
                    if (row.item_id && !uniqueItemsPmt[row.item_id]) {
                        const pmtSpent = parseFloat(row.pmt_spent) || 0;
                        uniqueItemsPmt[row.item_id] = pmtSpent;
                    }
                });

                // Calculate total ads from unique tracking
                let totalKwFromData = 0;
                let totalPmtFromData = 0;
                for (const parent in uniqueParentsKw) {
                    totalKwFromData += uniqueParentsKw[parent];
                }
                for (const itemId in uniqueItemsPmt) {
                    totalPmtFromData += uniqueItemsPmt[itemId];
                }
                const totalAdsFromData = totalKwFromData + totalPmtFromData;

                const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
                const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
                const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;
                
                // TACOS % = ((KW Spent + PMT Spent) / Total Sales) * 100 (uses header-level total ads)
                const tacosPercentage = totalRevenue > 0 ? ((KW_SPENT + PMT_SPENT) / totalRevenue) * 100 : 0;
                
                // N PFT = GPFT % - TACOS %
                const mPft = pftPercentage - tacosPercentage;
                
                // N ROI = (Net Profit / Total COGS) * 100
                // Net Profit = Total PFT - (KW Spent + PMT Spent)
                const netProfit = totalPft - (KW_SPENT + PMT_SPENT);
                const nRoi = totalCogs > 0 ? (netProfit / totalCogs) * 100 : 0;
                
                // Ads % = (Unique SKU ads from sold items / Total Sales) * 100
                const adsPercentage = totalRevenue > 0 ? (totalAdsFromData / totalRevenue) * 100 : 0;
                
                // PFT % = GPFT % - Ads %
                const pftPercentageFiltered = pftPercentage - adsPercentage;
                
                // Check if data is filtered
                const totalDataCount = table.getDataCount();
                const activeDataCount = data.length;
                const skuSearchValue = $('#sku-search').val() || '';
                const hasTableFilters = table.modules.filter && table.modules.filter.getFilters().length > 0;
                const isFiltered = activeDataCount < totalDataCount || hasTableFilters || skuSearchValue.trim() !== '';

                $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
                $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
                $('#total-sales-badge').text('Total Sales: $' + totalRevenue.toFixed(2));
                $('#total-revenue-badge').text('Total Revenue: $' + totalRevenue.toFixed(2));
                $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
                $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#pft-total-badge').text('GPFT Total: $' + totalPft.toFixed(2));

                const pftBadge = $('#pft-total-badge');
                if (totalPft >= 0) {
                    pftBadge.removeClass('bg-danger').addClass('bg-dark');
                } else {
                    pftBadge.removeClass('bg-dark').addClass('bg-danger');
                }

                $('#l30-sales-badge').text('L30 Sales: $' + totalL30Sales.toFixed(2));
                $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));
                $('#tacos-percentage-badge').text('TACOS %: ' + tacosPercentage.toFixed(1) + '%');
                $('#m-pft-badge').text('N PFT: ' + mPft.toFixed(1) + '%');
                $('#n-roi-badge').text('N ROI: ' + nRoi.toFixed(1) + '%');
                
                // Show/hide Ads % and PFT % badges based on filter status
                if (isFiltered) {
                    $('#ads-percentage-badge').show().text('Ads %: ' + adsPercentage.toFixed(1) + '%');
                    $('#pft-percentage-filtered-badge').show().text('PFT %: ' + pftPercentageFiltered.toFixed(1) + '%');
                } else {
                    $('#ads-percentage-badge').hide();
                    $('#pft-percentage-filtered-badge').hide();
                }
            }

            // Build Column Visibility Dropdown
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
                table.download("csv", "ebay3_daily_sales_data.csv");
            });
        });
    </script>
@endsection
