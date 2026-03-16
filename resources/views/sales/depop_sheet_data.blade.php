@extends('layouts.vertical', ['title' => 'Depop Sheet Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Depop Sales Data',
        'sub_title' => 'Upload Depop sales export (TSV/Excel). Margin 87%.',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Depop Sales Data — Margin 87%</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <form id="upload-form-depop" class="d-flex align-items-center gap-2 me-2">
                        @csrf
                        <input type="file" name="file" id="upload-file-depop" accept=".txt,.csv,.tsv,.xlsx,.xls" class="form-control form-control-sm" style="max-width: 220px;">
                        <button type="submit" class="btn btn-sm btn-primary" id="upload-btn-depop">
                            <i class="fa fa-upload"></i> Upload (Truncate & Replace)
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn"><i class="fa fa-file-excel"></i> Export</button>
                </div>
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-rows-badge">Rows: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge">Total Qty: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-badge" style="background-color: #17a2b8; color: white;">Sales: $0</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge">GPFT Total: $0</span>
                        <span class="badge fs-6 p-2" id="gpft-pct-badge" style="background-color: #6f42c1; color: white;">G PFT %: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-cogs-badge">COGS: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                </div>
                <div id="depop-table" style="height: calc(100vh - 280px);"></div>
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
        table = new Tabulator("#depop-table", {
            ajaxURL: "{{ url('/depop/sheet-data') }}",
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            initialSort: [{ column: "sale_date", dir: "desc" }],
            columns: [
                { title: "Sale Date", field: "sale_date", width: 100 },
                { title: "Buyer", field: "buyer", width: 120 },
                { title: "Product", field: "product_name", width: 280 },
                { title: "Size", field: "size", width: 80 },
                { title: "SKU", field: "sku", width: 120, cssClass: "text-primary fw-bold" },
                { title: "Qty", field: "quantity", width: 60, hozAlign: "right" },
                { title: "Price", field: "price", width: 80, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Sale AMT", field: "sale_amount", width: 90, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "LP", field: "lp", width: 70, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Ship", field: "ship", width: 70, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "Ship Cost", field: "ship_cost", width: 80, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "COGS", field: "cogs", width: 80, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "PFT Each", field: "pft_each", width: 90, hozAlign: "right", formatter: function(c) { var v = c.getValue(); var color = v >= 0 ? '#28a745' : '#dc3545'; return '<span style="color:'+color+'">$'+parseFloat(v).toFixed(2)+'</span>'; }},
                { title: "PFT %", field: "pft_each_pct", width: 80, hozAlign: "right", formatter: function(c) { var v = c.getValue(); return (v != null ? parseFloat(v).toFixed(1) : '0') + '%'; }},
                { title: "T PFT", field: "t_pft", width: 90, hozAlign: "right", formatter: "money", formatterParams: { symbol: "$", precision: 2 } },
                { title: "ROI %", field: "roi", width: 80, hozAlign: "right", formatter: function(c) { var v = c.getValue(); return (v != null ? parseFloat(v).toFixed(0) : '0') + '%'; }},
            ],
            dataLoaded: function() { updateSummary(); },
            ajaxError: function(e) { showToast("Error loading data", "error"); }
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
            $('#total-cogs-badge').text('COGS: $' + Math.round(totalCogs).toLocaleString());
        }
        table.on('dataProcessed', updateSummary);
        table.on('dataFiltered', updateSummary);
        $('#export-btn').on('click', function() { table.download("csv", "depop_sheet_data.csv"); });
        $('#upload-form-depop').on('submit', function(e) {
            e.preventDefault();
            if (!document.getElementById('upload-file-depop').files.length) { showToast('Select a file', 'error'); return; }
            var fd = new FormData(this);
            fd.append('file', document.getElementById('upload-file-depop').files[0]);
            $('#upload-btn-depop').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            $.ajax({ url: "{{ url('/depop/upload') }}", type: 'POST', data: fd, processData: false, contentType: false,
                success: function(res) { showToast(res.message || 'Upload complete.', 'success'); table.setData(); },
                error: function(xhr) { showToast((xhr.responseJSON && xhr.responseJSON.error) || 'Upload failed', 'error'); },
                complete: function() { $('#upload-btn-depop').prop('disabled', false).html('<i class="fa fa-upload"></i> Upload (Truncate & Replace)'); }
            });
        });
    });
</script>
@endsection
