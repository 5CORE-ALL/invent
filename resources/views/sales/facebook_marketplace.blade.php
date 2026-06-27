@extends('layouts.vertical', ['title' => 'Facebook Marketplace Sales', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .fb-upload-card {
            border: 1px dashed #d1d5db;
            background: #f9fafb;
            border-radius: 8px;
            padding: 18px 20px;
        }

        .fb-upload-card .form-control {
            max-width: 360px;
        }

        .fb-upload-card .btn {
            min-width: 150px;
        }

        #fbm-table .tabulator-row.tabulator-row-even {
            background-color: #fafafa;
        }

        .fbm-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .fbm-instructions {
            font-size: 12px;
            color: #6b7280;
            margin-top: 8px;
        }

        .fbm-instructions code {
            background: #eef2ff;
            color: #3730a3;
            padding: 1px 6px;
            border-radius: 4px;
        }
    </style>
@endsection

@section('content')
    @csrf
    <div class="container-fluid py-3">

        <div class="fbm-page-header">
            <div>
                <h4 class="mb-1">Facebook Marketplace Sales</h4>
                <div class="text-muted" style="font-size: 13px;">
                    Upload sales reports exported from Facebook Marketplace. Rows are upserted on
                    <code>order_number + sku</code>.
                </div>
            </div>
        </div>

        {{-- ───── Template / Upload card ───── --}}
        <div class="card">
            <div class="card-body">
                <div class="fb-upload-card">
                    <form id="fbm-upload-form" enctype="multipart/form-data">
                        @csrf
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <div>
                                <label class="form-label mb-1" style="font-weight:600;">Upload Sales File (CSV)</label>
                                <input type="file" name="file" id="fbm-file" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div>
                                <div class="btn-group" role="group" aria-label="Upload and template actions">
                                    <button type="submit" class="btn btn-primary" id="fbm-upload-btn">
                                        <i class="ri-upload-cloud-2-line me-1"></i> Upload
                                    </button>
                                    <a href="{{ route('facebook.marketplace.template') }}" class="btn btn-outline-secondary">
                                        <i class="ri-download-2-line me-1"></i> Download Template
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="fbm-instructions">
                            Required columns (exact order): <code>sku</code>, <code>qty_sold</code>,
                            <code>sold_price</code>, <code>order_number</code>. Max file size 10 MB.
                        </div>
                        <div id="fbm-upload-msg" class="mt-2" style="font-size: 13px;"></div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ───── Summary Statistics (ebay3 style) ───── --}}
        <div class="card mt-3 shadow-sm">
            <div class="card-body py-3">
                <div id="summary-stats" class="p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="fbm-total-orders-badge"
                            style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="fbm-total-quantity-badge"
                            style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge fs-6 p-2" id="fbm-total-sales-badge"
                            style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="fbm-total-revenue-badge"
                            style="color: white; font-weight: bold;">Total Revenue: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="fbm-avg-price-badge"
                            style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="fbm-aov-badge"
                            style="color: white; font-weight: bold;">Avg Order Value: $0.00</span>
                        <span class="badge fs-6 p-2" id="fbm-total-skus-badge"
                            style="background-color: #6610f2; color: white; font-weight: bold;">Total SKUs: 0</span>
                        <span class="badge fs-6 p-2" id="fbm-total-rows-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;">Total Rows: 0</span>
                        <span class="badge fs-6 p-2" id="fbm-avg-qty-badge"
                            style="background-color: #fd7e14; color: white; font-weight: bold;">Avg Qty / Order: 0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ───── Sales grid ───── --}}
        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Sales Rows</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="fbm-refresh">
                        <i class="ri-refresh-line"></i> Refresh
                    </button>
                </div>
                <div id="fbm-table"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const dataUrl   = "{{ route('facebook.marketplace.data') }}";
            const uploadUrl = "{{ route('facebook.marketplace.upload') }}";
            const deleteUrlBase = "{{ url('/facebook-marketplace') }}";
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            let table;

            function fmtMoney(v) {
                const n = Number(v || 0);
                return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function fmtInt(v) {
                return Number(v || 0).toLocaleString();
            }

            function updateBadges(rows) {
                // Aggregate metrics over all rows.
                let totalQuantity = 0;
                let totalSales    = 0;
                const orderSet    = new Set();
                const skuSet      = new Set();

                for (const r of rows) {
                    const qty   = Number(r.qty_sold   || 0);
                    const total = Number(r.total      || 0);
                    totalQuantity += qty;
                    totalSales    += total;
                    if (r.order_number) orderSet.add(r.order_number);
                    if (r.sku)          skuSet.add(r.sku);
                }

                const totalOrders = orderSet.size;
                const totalSkus   = skuSet.size;
                const totalRows   = rows.length;
                const avgPrice    = totalQuantity > 0 ? totalSales / totalQuantity : 0;
                const aov         = totalOrders   > 0 ? totalSales / totalOrders   : 0;
                const avgQtyOrder = totalOrders   > 0 ? totalQuantity / totalOrders : 0;

                document.getElementById('fbm-total-orders-badge').textContent   = 'Total Orders: '   + fmtInt(totalOrders);
                document.getElementById('fbm-total-quantity-badge').textContent = 'Total Quantity: ' + fmtInt(totalQuantity);
                document.getElementById('fbm-total-sales-badge').textContent    = 'Total Sales: '    + fmtMoney(totalSales);
                document.getElementById('fbm-total-revenue-badge').textContent  = 'Total Revenue: '  + fmtMoney(totalSales);
                document.getElementById('fbm-avg-price-badge').textContent      = 'Avg Price: '      + fmtMoney(avgPrice);
                document.getElementById('fbm-aov-badge').textContent            = 'Avg Order Value: '+ fmtMoney(aov);
                document.getElementById('fbm-total-skus-badge').textContent     = 'Total SKUs: '     + fmtInt(totalSkus);
                document.getElementById('fbm-total-rows-badge').textContent     = 'Total Rows: '     + fmtInt(totalRows);
                document.getElementById('fbm-avg-qty-badge').textContent        = 'Avg Qty / Order: '+ avgQtyOrder.toFixed(2);
            }

            function loadTable() {
                fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(rows => {
                        updateBadges(rows);
                        if (!table) {
                            buildTable(rows);
                        } else {
                            table.replaceData(rows);
                        }
                    })
                    .catch(err => console.error('Load failed:', err));
            }

            function buildTable(rows) {
                table = new Tabulator('#fbm-table', {
                    data: rows,
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 25,
                    paginationSizeSelector: [25, 50, 100, 250],
                    movableColumns: true,
                    height: '600px',
                    placeholder: 'No Facebook Marketplace sales uploaded yet — use the Upload section above.',
                    columns: [
                        { title: '#',            field: 'id',           width: 70, hozAlign: 'right' },
                        { title: 'Order Number', field: 'order_number', headerFilter: 'input', minWidth: 160 },
                        { title: 'SKU',          field: 'sku',          headerFilter: 'input', minWidth: 140 },
                        { title: 'Qty Sold',     field: 'qty_sold',     hozAlign: 'right', width: 110, headerFilter: 'input' },
                        { title: 'Sold Price',   field: 'sold_price',   hozAlign: 'right', width: 130,
                            formatter: c => fmtMoney(c.getValue()) },
                        { title: 'Total',        field: 'total',        hozAlign: 'right', width: 130,
                            formatter: c => fmtMoney(c.getValue()) },
                        { title: 'Order Date',   field: 'order_date',   width: 120 },
                        { title: 'Uploaded At',  field: 'created_at',   width: 170 },
                        { title: '',             field: '_actions',     width: 90, hozAlign: 'center',
                            headerSort: false,
                            formatter: () => '<button class="btn btn-sm btn-outline-danger fbm-del-btn"><i class="ri-delete-bin-line"></i></button>',
                            cellClick: (e, cell) => {
                                if (!e.target.closest('.fbm-del-btn')) return;
                                const id = cell.getRow().getData().id;
                                if (!confirm('Delete this row?')) return;
                                fetch(deleteUrlBase + '/' + id, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'Accept': 'application/json',
                                    },
                                }).then(r => r.json()).then(j => {
                                    if (j.success) { loadTable(); }
                                    else { alert(j.message || 'Delete failed.'); }
                                });
                            }
                        },
                    ],
                });
            }

            document.getElementById('fbm-upload-form').addEventListener('submit', function (e) {
                e.preventDefault();
                const fileEl  = document.getElementById('fbm-file');
                const btn     = document.getElementById('fbm-upload-btn');
                const msgEl   = document.getElementById('fbm-upload-msg');
                if (!fileEl.files.length) {
                    msgEl.innerHTML = '<span class="text-danger">Please choose a CSV file first.</span>';
                    return;
                }
                const fd = new FormData();
                fd.append('file', fileEl.files[0]);
                fd.append('_token', csrfToken);

                btn.disabled = true;
                msgEl.innerHTML = '<span class="text-muted">Uploading…</span>';

                fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: fd,
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        msgEl.innerHTML = '<span class="text-success">' + j.message + '</span>';
                        fileEl.value = '';
                        loadTable();
                    } else {
                        msgEl.innerHTML = '<span class="text-danger">' + (j.message || 'Upload failed.') + '</span>';
                    }
                })
                .catch(err => {
                    msgEl.innerHTML = '<span class="text-danger">Upload failed: ' + err.message + '</span>';
                })
                .finally(() => { btn.disabled = false; });
            });

            document.getElementById('fbm-refresh').addEventListener('click', loadTable);

            document.addEventListener('DOMContentLoaded', loadTable);
        })();
    </script>
@endsection
