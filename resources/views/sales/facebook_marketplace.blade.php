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
                        <span class="badge fs-6 p-2" id="fbm-y-sales-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="Yesterday's Facebook Marketplace sales — {{ !empty($ySalesDate) ? \Carbon\Carbon::parse($ySalesDate)->format('M j, Y') . ' (PT / California)' : 'Pacific / California calendar day' }}. Σ sold_price × qty ({{ number_format((int) ($yQuantity ?? 0)) }} qty, {{ number_format((int) ($yOrders ?? 0)) }} orders). Same source as the FB Marketplace row on /all-marketplace-master.">Y Sales: ${{ number_format((float) ($ySales ?? 0), 2) }}</span>
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
                        <span class="badge fs-6 p-2" id="fbm-margin-badge"
                            style="background-color: #20c997; color: white; font-weight: bold;"
                            title="Take-home margin from marketplace_percentages">Margin: —</span>
                        <span class="badge bg-info fs-6 p-2" id="fbm-gpft-badge"
                            style="color: black; font-weight: bold;"
                            title="GPFT% = Σ PFT / Σ Sales × 100 (sold_price × margin − LP, no ship)">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="fbm-roi-badge"
                            style="color: white; font-weight: bold;"
                            title="ROI% = Σ PFT / Σ COGS × 100">ROI: 0%</span>
                        <span class="badge fs-6 p-2" id="fbm-ads-badge"
                            style="background-color: #d63384; color: white; font-weight: bold;"
                            title="Ads% = Facebook ads spend (CH=FB from /facebook-ads) / Sales × 100">Ads: 0%</span>
                        <span class="badge fs-6 p-2" id="fbm-ad-spend-badge"
                            style="background-color: #fd7e14; color: white; font-weight: bold;"
                            title="Total Facebook ads spend (CH=FB)">Ad Spend: $0</span>
                        <span class="badge fs-6 p-2" id="fbm-npft-badge"
                            style="background-color: #0f766e; color: white; font-weight: bold;"
                            title="NPFT% = GPFT% − Ads%">NPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="fbm-nroi-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="NROI% = (Σ PFT − Ad Spend) / Σ COGS × 100">NROI: 0%</span>
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

            function pctColor(val, kind) {
                const n = Number(val);
                if (!isFinite(n)) return '#6c757d';
                if (kind === 'gpft') {
                    if (n < 10) return '#a00211';
                    if (n < 15) return '#ffc107';
                    if (n < 20) return '#0d6efd';
                    if (n <= 40) return '#28a745';
                    return '#e83e8c';
                }
                // ROI
                if (n >= 125) return '#d63384';
                if (n >= 75) return '#28a745';
                if (n >= 40) return '#ffc107';
                return '#a00211';
            }

            function updateBadges(rows, summary) {
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
                const gpft = summary && summary.gpft_percent != null ? Number(summary.gpft_percent) : 0;
                const roi  = summary && summary.roi_percent  != null ? Number(summary.roi_percent)  : 0;
                const margin = summary && summary.margin_percent != null ? Number(summary.margin_percent) : null;
                const adsPct = summary && summary.ads_percent != null ? Number(summary.ads_percent) : 0;
                const adSpend = summary && summary.total_ad_spend != null ? Number(summary.total_ad_spend) : 0;
                const npft = summary && summary.npft_percent != null ? Number(summary.npft_percent) : (gpft - adsPct);
                const nroi = summary && summary.nroi_percent != null ? Number(summary.nroi_percent) : 0;

                const ySales = summary && summary.y_sales != null ? Number(summary.y_sales) : 0;
                const ySalesDate = summary && summary.y_sales_date ? String(summary.y_sales_date) : '';
                const yQty = summary && summary.y_quantity != null ? Number(summary.y_quantity) : 0;
                const yOrders = summary && summary.y_orders != null ? Number(summary.y_orders) : 0;
                const yBadge = document.getElementById('fbm-y-sales-badge');
                if (yBadge) {
                    yBadge.textContent = 'Y Sales: ' + fmtMoney(ySales);
                    let dateLabel = 'Pacific / California calendar day';
                    if (ySalesDate) {
                        try {
                            const d = new Date(ySalesDate + 'T12:00:00');
                            dateLabel = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' (PT / California)';
                        } catch (e) {
                            dateLabel = ySalesDate + ' (PT / California)';
                        }
                    }
                    yBadge.title = "Yesterday's Facebook Marketplace sales — " + dateLabel
                        + '. Σ sold_price × qty (' + fmtInt(yQty) + ' qty, ' + fmtInt(yOrders)
                        + ' orders). Same source as the FB Marketplace row on /all-marketplace-master.';
                }

                document.getElementById('fbm-total-orders-badge').textContent   = 'Total Orders: '   + fmtInt(totalOrders);
                document.getElementById('fbm-total-quantity-badge').textContent = 'Total Quantity: ' + fmtInt(totalQuantity);
                document.getElementById('fbm-total-sales-badge').textContent    = 'Total Sales: '    + fmtMoney(totalSales);
                document.getElementById('fbm-total-revenue-badge').textContent  = 'Total Revenue: '  + fmtMoney(totalSales);
                document.getElementById('fbm-avg-price-badge').textContent      = 'Avg Price: '      + fmtMoney(avgPrice);
                document.getElementById('fbm-aov-badge').textContent            = 'Avg Order Value: '+ fmtMoney(aov);
                document.getElementById('fbm-total-skus-badge').textContent     = 'Total SKUs: '     + fmtInt(totalSkus);
                document.getElementById('fbm-total-rows-badge').textContent     = 'Total Rows: '     + fmtInt(totalRows);
                document.getElementById('fbm-avg-qty-badge').textContent        = 'Avg Qty / Order: '+ avgQtyOrder.toFixed(2);
                document.getElementById('fbm-margin-badge').textContent         = margin != null
                    ? ('Margin: ' + margin.toFixed(0) + '%')
                    : 'Margin: —';
                document.getElementById('fbm-gpft-badge').textContent           = 'GPFT: ' + gpft.toFixed(1) + '%';
                document.getElementById('fbm-roi-badge').textContent            = 'ROI: '  + roi.toFixed(1)  + '%';
                document.getElementById('fbm-ads-badge').textContent            = 'Ads: ' + adsPct.toFixed(1) + '%';
                document.getElementById('fbm-ad-spend-badge').textContent       = 'Ad Spend: ' + fmtMoney(adSpend);
                document.getElementById('fbm-npft-badge').textContent           = 'NPFT: ' + npft.toFixed(1) + '%';
                document.getElementById('fbm-nroi-badge').textContent           = 'NROI: ' + nroi.toFixed(1) + '%';
            }

            function loadTable() {
                fetch(dataUrl, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(payload => {
                        const rows = Array.isArray(payload) ? payload : (payload.data || []);
                        const summary = Array.isArray(payload) ? null : (payload.summary || null);
                        updateBadges(rows, summary);
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
                        { title: 'Sold Price',   field: 'sold_price',   hozAlign: 'right', width: 120,
                            formatter: c => fmtMoney(c.getValue()) },
                        { title: 'Total',        field: 'total',        hozAlign: 'right', width: 120,
                            formatter: c => fmtMoney(c.getValue()) },
                        { title: 'LP',           field: 'lp',           hozAlign: 'right', width: 90,
                            formatter: c => fmtMoney(c.getValue()) },
                        { title: 'ROI %',        field: 'roi',          hozAlign: 'center', width: 90, sorter: 'number',
                            formatter: c => {
                                const v = Number(c.getValue()) || 0;
                                return '<span style="color:' + pctColor(v, 'roi') + ';font-weight:600;">' + v.toFixed(1) + '%</span>';
                            } },
                        { title: 'GPFT %',       field: 'gpft',         hozAlign: 'center', width: 90, sorter: 'number',
                            formatter: c => {
                                const v = Number(c.getValue()) || 0;
                                return '<span style="color:' + pctColor(v, 'gpft') + ';font-weight:600;">' + v.toFixed(1) + '%</span>';
                            } },
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
