@extends('layouts.vertical', ['title' => 'Newegg Sales Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
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
        'page_title' => 'Newegg Sales Data',
        'sub_title' => 'Newegg Sales Data Analysis',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Newegg Sales Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
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

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge" style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">GPFT Total: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="newegg-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU or Order #...">
                    </div>
                    <div id="newegg-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        let table = null;

        function moneyCol(title, field, visible = true) {
            return {
                title, field, visible,
                hozAlign: "right", sorter: "number", width: 100,
                formatter: "money",
                formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
            };
        }

        function pftColor(value) {
            return value >= 0 ? '#28a745' : '#dc3545';
        }

        $(document).ready(function() {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            table = new Tabulator("#newegg-table", {
                ajaxURL: "{{ route('newegg.daily.sales.data') }}",
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: "rows",
                placeholder: "No Data Available",
                ajaxResponse: function(url, params, response) {
                    return Array.isArray(response) ? response : (response.data || []);
                },
                initialSort: [{ column: "order_date", dir: "desc" }],
                columns: [
                    { title: "Order #", field: "order_id", width: 130, frozen: true, headerFilter: "input" },
                    { title: "SKU", field: "sku", width: 160, headerFilter: "input", headerFilterPlaceholder: "Search SKU...", cssClass: "text-primary fw-bold" },
                    { title: "Description", field: "description", width: 250, visible: false, tooltip: true },
                    { title: "Qty", field: "quantity", hozAlign: "center", sorter: "number", width: 60 },
                    moneyCol("Price", "price"),
                    moneyCol("Sales AMT", "sale_amount"),
                    moneyCol("Order Total", "total_amount", false),
                    { title: "Currency", field: "currency", width: 80, visible: false },
                    {
                        title: "Order Date", field: "order_date", sorter: "datetime", width: 150,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            return v ? new Date(v).toLocaleString() : '';
                        }
                    },
                    {
                        title: "Status", field: "status", width: 120,
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            let color = 'secondary';
                            const lv = v.toLowerCase();
                            if (lv.includes('shipped')) color = 'success';
                            else if (lv.includes('unshipped')) color = 'warning';
                            else if (lv.includes('invoiced')) color = 'info';
                            else if (lv.includes('void')) color = 'danger';
                            return v ? `<span class="badge bg-${color}">${v}</span>` : '';
                        }
                    },
                    { title: "Customer", field: "customer", width: 150, visible: false },
                    moneyCol("LP", "lp"),
                    moneyCol("Ship", "ship"),
                    { title: "T Weight", field: "t_weight", hozAlign: "right", sorter: "number", width: 90, visible: false },
                    moneyCol("Ship Cost", "ship_cost", false),
                    moneyCol("COGS", "cogs"),
                    {
                        title: "PFT Each", field: "pft_each", hozAlign: "right", sorter: "number", width: 100,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: ${pftColor(v)}; font-weight: bold;">$${v.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "PFT Each %", field: "pft_each_pct", hozAlign: "right", sorter: "number", width: 100,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: ${pftColor(v)}; font-weight: bold;">${v.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "T PFT", field: "pft", hozAlign: "right", sorter: "number", width: 110,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: ${pftColor(v)}; font-weight: bold;">$${v.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "ROI %", field: "roi", hozAlign: "right", sorter: "number", width: 90,
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            let color = '#6c757d';
                            if (v < 50) color = '#dc3545';
                            else if (v < 75) color = '#ffc107';
                            else if (v <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color: ${color}; font-weight: bold;">${v.toFixed(0)}%</span>`;
                        }
                    }
                ]
            });

            $('#sku-search').on('keyup', function() {
                const value = ($(this).val() || '').trim().toLowerCase();
                if (value) {
                    table.setFilter(function(row) {
                        const sku = String(row.sku || '').toLowerCase();
                        const order = String(row.order_id || '').toLowerCase();
                        return sku.indexOf(value) !== -1 || order.indexOf(value) !== -1;
                    });
                } else {
                    table.clearFilter();
                }
                setTimeout(updateSummary, 100);
            });

            function updateSummary() {
                const data = table.getData("active");
                let totalOrders = 0, totalQuantity = 0, totalRevenue = 0, totalPft = 0, totalCogs = 0;
                let totalWeightedPrice = 0, totalQuantityForPrice = 0;

                data.forEach(row => {
                    if (!row.sku) return;
                    const quantity = parseInt(row.quantity) || 0;
                    const basePrice = parseFloat(row.price) || 0;
                    if (quantity === 0) return;

                    totalOrders++;
                    totalQuantity += quantity;
                    totalRevenue += basePrice * quantity;
                    if (basePrice > 0) {
                        totalWeightedPrice += basePrice * quantity;
                        totalQuantityForPrice += quantity;
                    }
                    totalPft += parseFloat(row.pft) || 0;
                    totalCogs += parseFloat(row.cogs) || 0;
                });

                const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
                const pftPct = totalRevenue > 0 ? (totalPft / totalRevenue) * 100 : 0;
                const roiPct = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
                $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
                $('#total-sales-badge').text('Total Sales: $' + totalRevenue.toFixed(2));
                $('#pft-percentage-badge').text('GPFT %: ' + pftPct.toFixed(1) + '%');
                $('#roi-percentage-badge').text('ROI %: ' + roiPct.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#pft-total-badge').text('GPFT Total: $' + totalPft.toFixed(2));
                $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));

                const pftBadge = $('#pft-total-badge');
                if (totalPft >= 0) pftBadge.removeClass('bg-danger').addClass('bg-dark');
                else pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }

            const COL_URL = '/newegg-daily-sales-column-visibility';

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            const li = document.createElement("li");
                            const label = document.createElement("label");
                            label.style.cssText = "display:block;padding:5px 10px;cursor:pointer;";
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
                    if (def.field) visibility[def.field] = col.isVisible();
                });
                fetch(COL_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ visibility })
                });
            }

            function applyColumnVisibilityFromServer() {
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (def.field && savedVisibility[def.field] === false) col.hide();
                        });
                    });
            }

            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
            });
            table.on('dataLoaded', updateSummary);
            table.on('dataProcessed', updateSummary);
            table.on('dataFiltered', updateSummary);

            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const col = table.getColumn(e.target.value);
                    if (e.target.checked) col.show(); else col.hide();
                    saveColumnVisibilityToServer();
                }
            });

            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => col.show());
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            $('#export-btn').on('click', function() {
                table.download("csv", "newegg_sales_data.csv");
            });
        });
    </script>
@endsection
