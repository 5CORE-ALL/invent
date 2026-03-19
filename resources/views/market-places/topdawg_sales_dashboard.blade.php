@extends('layouts.vertical', ['title' => 'TopDawg Sales Dashboard', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; white-space: nowrap; transform: rotate(180deg);
            height: 80px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TopDawg Sales Dashboard',
        'sub_title' => 'Orders from topdawg_order_metrics (margin 0.95, no ship)',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>TopDawg Sales Dashboard</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                    <a href="{{ url('marketplace/topdawg/orders') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-list"></i> Orders
                    </a>
                </div>
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">PFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT Total: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge" style="color: white; font-weight: bold;">L30 Sales: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="topdawg-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU or Order #...">
                    </div>
                    <div id="topdawg-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table = null;
    const MARGIN = 0.95;

    function showToast(message, type) {
        const c = document.querySelector('.toast-container');
        if (!c) return;
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info') + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        c.appendChild(toast);
        const bs = new bootstrap.Toast(toast);
        bs.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        table = new Tabulator("#topdawg-table", {
            ajaxURL: "/topdawg/sales-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxError: function(err) {
                showToast("Error loading data: " + (err.message || "Unknown error"), "error");
            },
            dataLoaded: function() { updateSummary(); },
            initialSort: [{ column: "order_date", dir: "desc" }, { column: "id", dir: "desc" }],
            columns: [
                { title: "Order #", field: "order_number", width: 140, headerFilter: "input" },
                { title: "Order Date", field: "order_date", width: 120, sorter: "date" },
                { title: "SKU", field: "sku", width: 150, headerFilter: "input", cssClass: "text-primary fw-bold" },
                { title: "Display SKU", field: "display_sku", width: 220, tooltip: true },
                { title: "Qty", field: "quantity", width: 70, hozAlign: "center", sorter: "number" },
                { title: "Amount", field: "amount", width: 100, hozAlign: "right", sorter: "number", formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "LP", field: "lp", width: 80, hozAlign: "right", sorter: "number", formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "COGS", field: "cogs", width: 100, hozAlign: "right", sorter: "number", formatter: "money", formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 } },
                { title: "PFT", field: "pft", width: 100, hozAlign: "right", sorter: "number",
                    formatter: function(cell) {
                        var v = cell.getValue();
                        var color = v >= 0 ? '#28a745' : '#dc3545';
                        return '<span style="color:' + color + ';font-weight:bold;">$' + parseFloat(v).toFixed(2) + '</span>';
                    }
                },
                { title: "Status", field: "status", width: 110,
                    formatter: function(cell) {
                        var v = (cell.getValue() || '').toLowerCase();
                        if (!v) return '';
                        var color = 'secondary';
                        if (v.indexOf('delivered') !== -1 || v.indexOf('complete') !== -1) color = 'success';
                        else if (v.indexOf('ship') !== -1) color = 'info';
                        else if (v.indexOf('cancel') !== -1) color = 'danger';
                        else if (v.indexOf('pending') !== -1) color = 'warning';
                        return '<span class="badge bg-' + color + '">' + cell.getRow().getData().status + '</span>';
                    }
                },
            ]
        });

        $("#sku-search").on("keyup", function() {
            var val = ($(this).val() || "").toLowerCase();
            if (!val) {
                table.clearFilter();
                return;
            }
            table.setFilter(function(data) {
                var sku = (data.sku || "").toLowerCase();
                var orderNumber = (data.order_number || "").toLowerCase();
                return sku.indexOf(val) !== -1 || orderNumber.indexOf(val) !== -1;
            });
        });

        function updateSummary() {
            var data = table.getData("active");
            var totalOrders = 0, totalQuantity = 0, totalRevenue = 0, totalPft = 0, totalCogs = 0, totalWeighted = 0, qtyForPrice = 0;
            data.forEach(function(row) {
                totalOrders++;
                var qty = parseInt(row.quantity, 10) || 0;
                var amt = parseFloat(row.amount) || 0;
                totalQuantity += qty;
                totalRevenue += amt;
                if (qty > 0 && amt > 0) {
                    totalWeighted += amt;
                    qtyForPrice += qty;
                }
                totalPft += parseFloat(row.pft) || 0;
                totalCogs += parseFloat(row.cogs) || 0;
            });
            var avgPrice = qtyForPrice > 0 ? totalWeighted / qtyForPrice : 0;
            var pftPct = totalRevenue > 0 ? (totalPft / totalRevenue) * 100 : 0;
            var roiPct = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $("#total-orders-badge").text("Total Orders: " + totalOrders.toLocaleString());
            $("#total-quantity-badge").text("Total Quantity: " + totalQuantity.toLocaleString());
            $("#total-revenue-badge").text("Total Revenue: $" + totalRevenue.toFixed(2));
            $("#pft-percentage-badge").text("PFT %: " + pftPct.toFixed(1) + "%");
            $("#roi-percentage-badge").text("ROI %: " + roiPct.toFixed(1) + "%");
            $("#avg-price-badge").text("Avg Price: $" + avgPrice.toFixed(2));
            $("#pft-total-badge").text("PFT Total: $" + totalPft.toFixed(2));
            $("#pft-total-badge").toggleClass("bg-danger", totalPft < 0).toggleClass("bg-dark", totalPft >= 0);
            $("#l30-sales-badge").text("L30 Sales: $" + totalRevenue.toFixed(2));
            $("#total-cogs-badge").text("Total COGS: $" + totalCogs.toFixed(2));
        }

        table.on("dataProcessed", updateSummary);
        table.on("renderComplete", updateSummary);

        $("#export-btn").on("click", function() {
            table.download("csv", "topdawg_sales.csv");
        });
    });
</script>
@endsection
