@extends('layouts.vertical', ['title' => 'TikTok 2 Daily Sales Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
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
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TikTok 2 Daily Sales Data',
        'sub_title' => 'Upload-based sales data (margin 80%, same as TikTok)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>TikTok 2 Daily Sales Data (L30) — Margin 80%</h4>
                <p class="text-muted small mb-3 mb-md-2">Import replaces all rows in this table. Use <strong>Upload</strong> to select your TikTok Seller Center export.</p>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tiktokTwoUploadModal">
                        <i class="fa fa-upload"></i> Upload (Truncate &amp; Replace)
                    </button>
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary"><i class="fa fa-eye"></i> Show All</button>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn"><i class="fa fa-file-excel"></i> Export</button>
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
                <div id="tiktok-two-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="tiktok-two-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tiktokTwoUploadModal" tabindex="-1" aria-labelledby="tiktokTwoUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tiktokTwoUploadModalLabel">
                        <i class="fa fa-upload me-1"></i> Upload TikTok order export
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-secondary py-2 small mb-3">
                        Upload the <strong>TikTok Seller Center</strong> order export (tab-separated <code>.txt</code> / <code>.csv</code>).
                        The file must include the <strong>header row</strong> with columns such as
                        <strong>Order ID</strong>, <strong>Seller SKU</strong>, <strong>Quantity</strong>,
                        <strong>SKU Unit Original Price</strong>, <strong>Order Amount</strong>, and <strong>Created Time</strong>.
                        Column positions are detected from the header so new TikTok columns do not break the import.
                    </div>
                    <form id="upload-form-tiktok-two">
                        @csrf
                        <label for="upload-file-tiktok-two" class="form-label small mb-1">Order export file</label>
                        <input type="file" name="file" id="upload-file-tiktok-two" accept=".txt,.csv,.tsv" class="form-control form-control-sm">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="upload-form-tiktok-two" class="btn btn-primary btn-sm" id="upload-btn-tiktok-two">
                        <i class="fa fa-upload"></i> Upload (Truncate &amp; Replace)
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;

    function showToast(message, type) {
        type = type || 'info';
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info') + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        table = new Tabulator("#tiktok-two-table", {
            ajaxURL: "{{ url('/tiktok-two/daily-sales-data') }}",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                return Array.isArray(response) ? response : [];
            },
            ajaxError: function(error) {
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) { updateSummary(); },
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "Show", "first": "First", "first_title": "First Page",
                        "last": "Last", "last_title": "Last Page", "prev": "Prev", "prev_title": "Prev Page",
                        "next": "Next", "next_title": "Next Page",
                        "counter": { "showing": "Showing", "of": "of", "rows": "rows" }
                    }
                }
            },
            initialSort: [{ column: "order_date", dir: "desc" }],
            columns: [
                { title: "Order ID", field: "order_id", width: 180, frozen: true },
                { title: "ASIN", field: "asin", width: 120, frozen: true },
                { title: "SKU", field: "sku", headerFilter: "input", headerFilterPlaceholder: "Search SKU...", width: 150, cssClass: "text-primary fw-bold" },
                { title: "Quantity", field: "quantity", hozAlign: "center", sorter: "number", width: 50 },
                { title: "Price", field: "price", hozAlign: "right", sorter: "number", width: 70, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "Sales AMT", field: "sale_amount", hozAlign: "right", sorter: "number", width: 70, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "Order Date", field: "order_date", sorter: "datetime", width: 20, formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '';
                    return new Date(value).toLocaleDateString() + ' ' + new Date(value).toLocaleTimeString();
                }},
                { title: "Status", field: "status", width: 120, formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '';
                    var color = 'secondary';
                    if (value.toLowerCase().indexOf('shipped') !== -1) color = 'success';
                    else if (value.toLowerCase().indexOf('cancel') !== -1) color = 'danger';
                    else if (value.toLowerCase().indexOf('pending') !== -1) color = 'warning';
                    return '<span class="badge bg-' + color + '">' + value + '</span>';
                }},
                { title: "Period", field: "period", width: 80 },
                { title: "LP", field: "lp", hozAlign: "right", sorter: "number", width: 100, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "Ship", field: "ship", hozAlign: "right", sorter: "number", width: 100, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "T Weight", field: "t_weight", hozAlign: "right", sorter: "number", width: 100 },
                { title: "Ship Cost", field: "ship_cost", hozAlign: "right", sorter: "number", width: 100, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "COGS", field: "cogs", hozAlign: "right", sorter: "number", width: 100, formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "PFT Each", field: "pft_each", hozAlign: "right", sorter: "number", width: 100, formatter: function(cell) {
                    var v = cell.getValue();
                    var color = v >= 0 ? '#28a745' : '#dc3545';
                    return '<span style="color:' + color + ';font-weight:bold;">$' + parseFloat(v).toFixed(2) + '</span>';
                }},
                { title: "PFT Each %", field: "pft_each_pct", hozAlign: "right", sorter: "number", width: 100, formatter: function(cell) {
                    var v = cell.getValue();
                    if (v === null || v === undefined || isNaN(v)) return '0.00%';
                    var color = parseFloat(v) >= 0 ? '#28a745' : '#dc3545';
                    return '<span style="color:' + color + ';font-weight:bold;">' + parseFloat(v).toFixed(2) + '%</span>';
                }},
                { title: "ROI %", field: "roi", hozAlign: "right", sorter: "number", width: 100, formatter: function(cell) {
                    var v = cell.getValue();
                    var color = '#6c757d';
                    if (v < 50) color = '#dc3545';
                    else if (v >= 50 && v < 75) color = '#ffc107';
                    else if (v >= 75 && v <= 125) color = '#28a745';
                    else if (v > 125) color = '#e83e8c';
                    return '<span style="color:' + color + ';font-weight:bold;">' + parseFloat(v).toFixed(0) + '%</span>';
                }}
            ]
        });

        $('#sku-search').on('keyup', function() {
            table.setFilter("sku", "like", $(this).val());
            setTimeout(updateSummary, 100);
        });

        function updateSummary() {
            var data = table.getData("active");
            var totalOrders = 0, totalQuantity = 0, totalRevenue = 0, totalPft = 0, totalL30Sales = 0, totalWeightedPrice = 0, totalQuantityForPrice = 0, totalCogs = 0;
            data.forEach(function(row) {
                if (!row.sku || !row.order_id) return;
                var quantity = parseInt(row.quantity, 10) || 0;
                if (quantity === 0) return;
                var unitPrice = parseFloat(row.price) || 0;
                totalOrders++;
                totalQuantity += quantity;
                totalRevenue += unitPrice * quantity;
                if (quantity > 0 && unitPrice > 0) {
                    totalWeightedPrice += unitPrice * quantity;
                    totalQuantityForPrice += quantity;
                }
                totalPft += parseFloat(row.t_pft) || 0;
                totalCogs += parseFloat(row.cogs) || 0;
                totalL30Sales += quantity * unitPrice;
            });
            var avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            var pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
            var roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-sales-badge').text('Total Sales: $' + Math.round(totalRevenue).toLocaleString());
            $('#pft-percentage-badge').text('GPFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice).toLocaleString());
            $('#pft-total-badge').text('GPFT Total: $' + Math.round(totalPft).toLocaleString());
            $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalCogs).toLocaleString());
            $('#pft-total-badge').removeClass('bg-danger').addClass(totalPft >= 0 ? 'bg-dark' : 'bg-danger');
        }

        function buildColumnDropdown() {
            var menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';
            fetch("{{ url('/tiktok-two-column-visibility') }}", { method: 'GET', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(function(r) { return r.json(); })
                .then(function(savedVisibility) {
                    table.getColumns().forEach(function(col) {
                        var def = col.getDefinition();
                        if (!def.field) return;
                        var li = document.createElement("li");
                        var label = document.createElement("label");
                        label.style.display = "block";
                        label.style.padding = "5px 10px";
                        label.style.cursor = "pointer";
                        var checkbox = document.createElement("input");
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
            var visibility = {};
            table.getColumns().forEach(function(col) {
                var def = col.getDefinition();
                if (def.field) visibility[def.field] = col.isVisible();
            });
            fetch("{{ url('/tiktok-two-column-visibility') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch("{{ url('/tiktok-two-column-visibility') }}", { method: 'GET', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(function(r) { return r.json(); })
                .then(function(savedVisibility) {
                    table.getColumns().forEach(function(col) {
                        var def = col.getDefinition();
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
        table.on('renderComplete', updateSummary);
        table.on('dataFiltered', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                var col = table.getColumn(e.target.value);
                if (e.target.checked) col.show(); else col.hide();
                saveColumnVisibilityToServer();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(function(col) { col.show(); });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        $('#export-btn').on('click', function() {
            table.download("csv", "tiktok_two_daily_sales_data.csv");
        });

        $('#upload-form-tiktok-two').on('submit', function(e) {
            e.preventDefault();
            var fileInput = document.getElementById('upload-file-tiktok-two');
            if (!fileInput.files.length) {
                showToast('Please select a file', 'error');
                return;
            }
            var formData = new FormData(this);
            formData.append('file', fileInput.files[0]);
            $('#upload-btn-tiktok-two').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            $.ajax({
                url: "{{ url('/tiktok-two/upload') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    showToast(res.message || 'Upload complete. ' + (res.rows || 0) + ' rows imported.', 'success');
                    table.setData();
                    $('#upload-file-tiktok-two').val('');
                    var modalEl = document.getElementById('tiktokTwoUploadModal');
                    if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(modalEl);
                        if (inst) inst.hide();
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : (xhr.statusText || 'Upload failed');
                    showToast(msg, 'error');
                },
                complete: function() {
                    $('#upload-btn-tiktok-two').prop('disabled', false).html('<i class="fa fa-upload"></i> Upload (Truncate &amp; Replace)');
                }
            });
        });
    });
</script>
@endsection
