@extends('layouts.vertical', ['title' => 'Vinted Sheet Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
    
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
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Vinted Sales Data',
        'sub_title' => 'Vinted sales synced from Shopify (Fluf). Margin ' . number_format($vintedPercentage ?? 95, 0) . '%.',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Vinted Sales Data — Margin {{ number_format($vintedPercentage ?? 95, 0) }}%</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <a href="{{ route('vinted.analytics') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-tags"></i> Analytics / Pricing
                    </a>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn"><i class="fa fa-file-excel"></i> Export</button>
                    <span class="text-muted small">Syncs automatically from Shopify (Fluf) every hour; updates L30 on the Analytics page by SKU</span>
                </div>
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-rows-badge">Rows: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge">Total Qty: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge" style="background-color: #17a2b8; color: white;">Sales: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge">GPFT Total: $0</span>
                        <span class="badge fs-6 p-2" id="gpft-pct-badge" style="background-color: #6f42c1; color: white;">G PFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="groi-pct-badge" style="background-color: #fd7e14; color: white;"
                              title="G ROI % = GPFT Total ÷ COGS (LP × qty), for the rows shown">G ROI %: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge">COGS: $0</span>
                        <span class="badge fs-6 p-2" id="guessed-sku-badge" style="background-color:#ffc107;color:#212529;cursor:pointer;"
                              title="Rows whose SKU was guessed from the product title. Click to show only these, click again to show all.">Guessed SKU: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                </div>
                <div id="vinted-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table = null;
    function showToast(message, type) {
        type = type || 'info';
        var c = document.querySelector('.toast-container');
        if (!c) return;
        var t = document.createElement('div');
        t.className = 'toast align-items-center text-white bg-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info') + ' border-0';
        t.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        c.appendChild(t);
        new bootstrap.Toast(t).show();
        t.addEventListener('hidden.bs.toast', function() { t.remove(); });
    }
    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
        table = new Tabulator("#vinted-table", {
                columnDefaults: { hozAlign: "center", headerHozAlign: "center" },
            ajaxURL: "{{ url('/vinted/sheet-data') }}",
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            initialSort: [{ column: "sale_date", dir: "desc" }],
            columns: [
                { title: "Sale Date", field: "sale_date", width: 100 },
                { title: "Buyer", field: "buyer", width: 120 },
                { title: "Source", field: "source", width: 110 },
                { title: "Product", field: "product_name", width: 280 },
                { title: "Size", field: "size", width: 80 },
                {
                    title: "SKU", field: "sku", width: 170, cssClass: "text-primary fw-bold",
                    editor: "input", editorParams: { elementAttributes: { maxlength: "191" } },
                    headerTooltip: "Double-click a SKU to edit it. Yellow ? = SKU guessed from the product title. A SKU you type is kept (the Shopify sync will not overwrite it).",
                    formatter: function(cell) {
                        var d = cell.getRow().getData();
                        var v = String(cell.getValue() == null ? '' : cell.getValue()).replace(/</g, '&lt;');
                        if (d.sku_source === 'guess') {
                            return '<span style="background:#fff3cd;color:#664d03;padding:1px 6px;border-radius:4px;" title="Guessed from the product title - double-click to correct">' + v + ' <b>?</b></span>';
                        }
                        if (d.sku_source === 'manual') {
                            return '<span title="Edited by hand">' + v + ' <i class="fa fa-pen text-success" style="font-size:10px;"></i></span>';
                        }
                        if (v !== '' && d.sku_in_pm === false) {
                            return '<span style="color:#dc3545;" title="Not a Product Master SKU">' + v + '</span>';
                        }
                        return v;
                    }
                },
                { title: "Qty", field: "quantity", width: 60, hozAlign: "center" },
                { title: "Price", field: "price", width: 80, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Sale AMT", field: "sale_amount", width: 90, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "LP", field: "lp", width: 70, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Ship", field: "ship", width: 70, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Ship Cost", field: "ship_cost", width: 80, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "COGS", field: "cogs", width: 80, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "PFT Each", field: "pft_each", width: 90, hozAlign: "center", formatter: function(c) { var v = c.getValue(); var color = v >= 0 ? '#28a745' : '#dc3545'; return '<span style="color:'+color+'">$'+parseFloat(v).toFixed(2)+'</span>'; }},
                { title: "PFT %", field: "pft_each_pct", width: 80, hozAlign: "center", formatter: function(c) { var v = c.getValue(); return (v != null ? parseFloat(v).toFixed(1) : '0') + '%'; }},
                { title: "T PFT", field: "t_pft", width: 90, hozAlign: "center", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "ROI %", field: "roi", width: 80, hozAlign: "center", formatter: function(c) { var v = c.getValue(); return (v != null ? parseFloat(v).toFixed(0) : '0') + '%'; }},
            ],
            dataLoaded: function() { updateSummary(); },
            ajaxError: function(e) { showToast("Error loading data", "error"); }
        });
        table.on('cellEdited', function(cell) {
            if (cell.getField() !== 'sku') return;
            var row = cell.getRow();
            var data = row.getData();
            $.post("{{ route('vinted.sheet.save.sku') }}", { id: data.id, sku: cell.getValue() })
                .done(function(res) {
                    row.update({ sku: res.sku, sku_source: res.sku_source, sku_in_pm: res.sku_in_pm });
                    showToast(res.message || 'SKU saved.', res.sku_in_pm || !res.sku ? 'success' : 'info');
                    updateSummary();
                })
                .fail(function(xhr) {
                    cell.restoreOldValue();
                    showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not save SKU', 'error');
                });
        });
        $('#sku-search').on('keyup', function() { table.setFilter("sku", "like", $(this).val()); setTimeout(updateSummary, 100); });
        function updateSummary() {
            var data = table.getData("active");
            var totalQty = 0, totalSales = 0, totalPft = 0, totalCogs = 0;
            data.forEach(function(r) {
                totalQty += parseInt(r.quantity, 10) || 0;
                totalSales += parseFloat(r.sale_amount) || 0;
                totalPft += parseFloat(r.t_pft) || 0;
                totalCogs += parseFloat(r.cogs) || 0;
            });
            var gpftPct = totalSales > 0 ? ((totalPft / totalSales) * 100).toFixed(1) : '0';
            $('#total-rows-badge').text('Rows: ' + data.length);
            $('#total-quantity-badge').text('Total Qty: ' + totalQty.toLocaleString());
            $('#total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#pft-total-badge').text('GPFT Total: $' + Math.round(totalPft).toLocaleString());
            $('#gpft-pct-badge').text('G PFT %: ' + gpftPct + '%');
            var groiPct = totalCogs > 0 ? ((totalPft / totalCogs) * 100).toFixed(1) : '0';
            $('#groi-pct-badge').text('G ROI %: ' + groiPct + '%');
            $('#total-cogs-badge').text('COGS: $' + Math.round(totalCogs).toLocaleString());
            var guessed = table.getData().filter(function(r) { return r.sku_source === 'guess'; }).length;
            $('#guessed-sku-badge').text('Guessed SKU: ' + guessed);
        }
        var guessedOnly = false;
        $('#guessed-sku-badge').on('click', function() {
            guessedOnly = !guessedOnly;
            if (guessedOnly) { table.setFilter("sku_source", "=", "guess"); } else { table.clearFilter(true); }
            $(this).css('outline', guessedOnly ? '3px solid #212529' : 'none');
            setTimeout(updateSummary, 100);
        });
        table.on('dataProcessed', updateSummary);
        table.on('dataFiltered', updateSummary);
        $('#export-btn').on('click', function() { table.download("csv", "vinted_sheet_data.csv"); });
    });
</script>
@endsection
