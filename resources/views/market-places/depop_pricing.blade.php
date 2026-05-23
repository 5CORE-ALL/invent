@extends('layouts.vertical', ['title' => 'Depop Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .badge-pricing-stat { font-size: 0.9rem; padding: 0.45rem 0.7rem; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Depop Pricing',
        'sub_title'  => 'One row per ProductMaster SKU. Edit Price and L30 by exporting the CSV, editing in Excel, and re-uploading.',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <a href="{{ route('depop.pricing.export') }}" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-csv"></i> Export CSV
                    </a>

                    <form id="import-form" class="d-flex align-items-center gap-2 mb-0">
                        @csrf
                        <input type="file" name="file" id="import-file" accept=".csv,.txt"
                            class="form-control form-control-sm" style="max-width: 240px;" required>
                        <button type="submit" class="btn btn-sm btn-primary" id="import-btn">
                            <i class="fa fa-upload"></i> Import CSV
                        </button>
                    </form>

                    <span class="text-muted small ms-2">
                        CSV columns: <code>parent, sku, price, l30</code> (price &amp; l30 are editable; sku is the match key)
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge bg-primary badge-pricing-stat" id="stat-total">SKUs: 0</span>
                    <span class="badge bg-success badge-pricing-stat" id="stat-priced">With Price: 0</span>
                    <span class="badge bg-info text-dark badge-pricing-stat" id="stat-l30">With L30: 0</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by Parent or SKU...">
                </div>
                <div id="depop-pricing-table" style="height: calc(100vh - 320px);"></div>
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
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 6000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function updateStats(rows) {
        const total  = rows.length;
        const priced = rows.filter(r => r.price !== null && r.price !== undefined && r.price > 0).length;
        const withL30 = rows.filter(r => r.l30 !== null && r.l30 !== undefined && r.l30 > 0).length;
        $('#stat-total').text('SKUs: ' + total.toLocaleString('en-US'));
        $('#stat-priced').text('With Price: ' + priced.toLocaleString('en-US'));
        $('#stat-l30').text('With L30: ' + withL30.toLocaleString('en-US'));
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        table = new Tabulator("#depop-pricing-table", {
            ajaxURL: "{{ route('depop.pricing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "parent", dir: "asc" }],
            placeholder: "No SKUs found. Make sure ProductMaster has SKUs.",
            columns: [
                {
                    // Image — same source as Macy's pricing (shopify_skus.image_src)
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 70,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (!v) return '<span class="text-muted">-</span>';
                        return `<img src="${v}" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                { title: "Parent", field: "parent", width: 180 },
                { title: "SKU",    field: "sku",    minWidth: 200 },
                {
                    title: "Inv",
                    field: "inv",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return v ? v.toLocaleString('en-US') : '<span class="text-muted">0</span>';
                    }
                },
                {
                    title: "OV L30",
                    field: "ov_l30",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return v ? v.toLocaleString('en-US') : '<span class="text-muted">0</span>';
                    }
                },
                {
                    // Dil% = OV L30 ÷ INV × 100, with the same colour bands Macy's uses.
                    title: "Dil",
                    field: "dil",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = Number(row.inv   || 0);
                        const ov  = Number(row.ov_l30 || 0);
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ov / inv) * 100;
                        let color = '#a00211';                                  // < 16.66
                        if (dil >= 16.66 && dil < 25) color = '#ffc107';        // 16.66–25
                        else if (dil >= 25 && dil < 50) color = '#28a745';      // 25–50
                        else if (dil >= 50) color = '#e83e8c';                  // >= 50
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "Price ($)",
                    field: "price",
                    width: 110,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return '$' + Number(v).toFixed(2);
                    }
                },
                {
                    title: "L30",
                    field: "l30",
                    width: 90,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return Number(v).toLocaleString('en-US');
                    }
                },
            ],
        });

        // Search across parent + sku
        $('#sku-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return (String(row.parent || '').toLowerCase().includes(q))
                    || (String(row.sku    || '').toLowerCase().includes(q));
            });
        });

        // Import handler
        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            const fileInput = $('#import-file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showToast('Choose a CSV file first.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            const $btn = $('#import-btn');
            const original = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

            $.ajax({
                url: "{{ route('depop.pricing.import') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || 'Import complete', 'success');
                        $('#import-file').val('');
                        table.setData();
                    } else {
                        showToast(res.message || 'Import failed', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Import failed';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(original);
                }
            });
        });
    });
</script>
@endsection
