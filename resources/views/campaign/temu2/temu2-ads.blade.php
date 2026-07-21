@extends('layouts.vertical', ['title' => 'Temu 2 Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #temu2-ads-table .tabulator-header {
            background: #20c997;
            font-size: 0.8rem;
            color: #fff;
        }
        #temu2-ads-table .tabulator-header .tabulator-col {
            background: #20c997;
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.25);
        }
        #temu2-ads-table .tabulator-cell {
            font-size: 0.85rem;
        }
        #temu2-ads-table .tabulator-footer {
            background: #f4f7fa;
            padding: 8px;
        }
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            white-space: nowrap;
        }
        .pricing-filter-item {
            display: inline-block;
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 2 Ads',
        'sub_title' => 'Raw campaign report data',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="range-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;">
                                <option value="">All Ranges</option>
                                <option value="L7">L7</option>
                                <option value="L30" selected>L30</option>
                                <option value="L60">L60</option>
                            </select>
                            <input type="text" id="search-input" class="form-control form-control-sm pricing-filter-item"
                                   placeholder="Search Goods ID / SKU / name" style="width: 220px;">
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" id="export-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export CSV">
                                <i class="fa fa-download"></i>
                            </button>
                            <div class="dropdown pricing-filter-item">
                                <button type="button" class="btn btn-sm btn-success" id="upload-actions-btn"
                                    data-bs-toggle="dropdown" aria-expanded="false" title="Upload">
                                    <i class="fa fa-upload"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="upload-actions-btn">
                                    <li>
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadTemu2AdsModal">
                                            <i class="fa fa-chart-line me-1 text-primary"></i> Up Ads Report
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-dark fs-6 p-2" id="row-count"
                                style="color: white; font-weight: bold;"
                                title="Number of rows currently loaded">Rows: 0</span>
                            <span class="badge fs-6 p-2" id="spend-sum"
                                style="background-color: #6f42c1; color: white; font-weight: bold;"
                                title="Sum of Spend for the loaded range">Spend: $0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="temu2-ads-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Upload Ads Report Modal (same pattern as Temu 2 Analytics uploads) --}}
    <div class="modal fade" id="uploadTemu2AdsModal" tabindex="-1" aria-labelledby="uploadTemu2AdsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="uploadTemu2AdsModalLabel">
                        <i class="fa fa-chart-line me-2"></i>Upload Temu 2 Ads Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="temu2-upload-status" class="mb-2"></div>
                    <form id="uploadTemu2AdsForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="upload-range" class="form-label fw-bold">Report Range</label>
                            <select id="upload-range" name="report_range" class="form-select" required>
                                <option value="L30" selected>L30</option>
                                <option value="L7">L7</option>
                                <option value="L60">L60</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="upload-file" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose File
                            </label>
                            <input type="file" class="form-control" id="upload-file" name="file"
                                   accept=".xlsx,.xls,.csv,.txt,.tsv" required>
                            <div class="form-text">
                                Accepts .xlsx, .xls, .csv, .txt, .tsv (Temu ads export). Max 10MB.
                            </div>
                        </div>
                        <div class="alert alert-warning mb-0">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> This replaces existing rows for the selected range only
                            (<code>temu2_campaign_reports</code>).
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="upload-btn" class="btn btn-primary">
                        <i class="fa fa-upload me-1"></i>Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const moneyFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
            const numFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toLocaleString('en-US');
            };
            const pctFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toFixed(2) + '%';
            };
            const decFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            function dataUrl() {
                const range = document.getElementById('range-filter').value;
                let url = '{{ route("temu2.ads.data") }}';
                if (range) url += '?report_range=' + encodeURIComponent(range);
                return url;
            }

            function formatMoney(total) {
                return Number(total || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function setSpendBadge(total) {
                document.getElementById('spend-sum').textContent = 'Spend: $' + formatMoney(total);
            }

            function setRowCount(n) {
                document.getElementById('row-count').textContent = 'Rows: ' + Number(n || 0).toLocaleString();
            }

            function updateSpendBadgeFromTable() {
                if (!table) return;
                const rows = table.getData(true);
                let total = 0;
                rows.forEach(function (row) {
                    total += parseFloat(row.spend) || 0;
                });
                setSpendBadge(total);
                setRowCount(rows.length);
            }

            const table = new Tabulator('#temu2-ads-table', {
                ajaxURL: dataUrl(),
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    setRowCount(response.total ?? rows.length);
                    if (response.spend_sum !== undefined && response.spend_sum !== null) {
                        setSpendBadge(response.spend_sum);
                    } else {
                        let total = 0;
                        rows.forEach(function (row) { total += parseFloat(row.spend) || 0; });
                        setSpendBadge(total);
                    }
                    return rows;
                },
                layout: 'fitDataStretch',
                height: '70vh',
                pagination: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, true],
                placeholder: 'No Temu 2 ads data yet — use Upload → Up Ads Report.',
                columns: [
                    { title: 'Range', field: 'report_range', width: 70, headerFilter: 'list', headerFilterParams: { values: { L7: 'L7', L30: 'L30', L60: 'L60', '': '' } } },
                    { title: 'Goods name', field: 'goods_name', minWidth: 220, headerFilter: 'input', formatter: 'textarea' },
                    { title: 'Goods ID', field: 'goods_id', width: 150, headerFilter: 'input' },
                    { title: 'SKU', field: 'sku', width: 140, headerFilter: 'input' },
                    { title: 'Spend', field: 'spend', width: 100, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Net total cost', field: 'net_total_cost', width: 120, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Base Price Sales (Overall)', field: 'base_price_sales', width: 160, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'ROAS (Overall)', field: 'roas', width: 110, hozAlign: 'right', formatter: decFmt, sorter: 'number' },
                    { title: 'ACOS (Overall)', field: 'acos_ad', width: 110, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    { title: 'Cost Per Order (Overall)', field: 'cost_per_transaction', width: 150, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Sub Order Count (Overall)', field: 'sub_orders', width: 150, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Items (Overall)', field: 'items', width: 110, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Impressions (Overall)', field: 'impressions', width: 140, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Clicks (Overall)', field: 'clicks', width: 110, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'CTR (Overall)', field: 'ctr', width: 100, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    { title: 'CVR (Overall)', field: 'cvr', width: 100, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    { title: 'Add to cart count (Overall)', field: 'add_to_cart_number', width: 170, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Net Base Price Sales (Overall)', field: 'net_declared_sales', width: 180, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Net ROAS (Overall)', field: 'net_roas', width: 130, hozAlign: 'right', formatter: decFmt, sorter: 'number' },
                    { title: 'Net ACOS (Overall)', field: 'net_acos_ad', width: 130, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    { title: 'Net Cost Per Order (Overall)', field: 'net_cost_per_transaction', width: 180, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Net Sub Order Count (Overall)', field: 'net_orders', width: 180, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Net Items (Overall)', field: 'net_number_pieces', width: 130, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Updated', field: 'updated_at', width: 150 },
                ],
            });

            table.on('dataFiltered', function () {
                updateSpendBadgeFromTable();
            });
            table.on('dataLoaded', function () {
                updateSpendBadgeFromTable();
            });

            document.getElementById('range-filter').addEventListener('change', function () {
                table.setData(dataUrl());
            });

            document.getElementById('search-input').addEventListener('input', function () {
                const q = (this.value || '').trim().toLowerCase();
                if (!q) {
                    table.clearFilter(true);
                    updateSpendBadgeFromTable();
                    return;
                }
                table.setFilter(function (data) {
                    return [data.goods_name, data.goods_id, data.sku]
                        .some(v => String(v || '').toLowerCase().includes(q));
                });
                updateSpendBadgeFromTable();
            });

            document.getElementById('export-btn').addEventListener('click', function () {
                table.download('csv', 'temu2-ads-raw.csv');
            });

            document.getElementById('upload-btn').addEventListener('click', function () {
                const fileInput = document.getElementById('upload-file');
                const range = document.getElementById('upload-range').value;
                const status = document.getElementById('temu2-upload-status');
                const file = fileInput.files[0];
                const btn = this;

                if (!file) {
                    status.innerHTML = '<div class="alert alert-danger py-2 mb-0">Please select a file.</div>';
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);
                formData.append('report_range', range);

                status.innerHTML = '<div class="alert alert-info py-2 mb-0">Uploading ' + range + '…</div>';
                btn.disabled = true;

                $.ajax({
                    url: '{{ route("temu2.ads.upload.campaign") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        if (response.success) {
                            status.innerHTML = '<div class="alert alert-success py-2 mb-0">' + response.message + '</div>';
                            fileInput.value = '';
                            document.getElementById('range-filter').value = range;
                            table.setData(dataUrl());
                            setTimeout(function () {
                                const modalEl = document.getElementById('uploadTemu2AdsModal');
                                const modal = bootstrap.Modal.getInstance(modalEl);
                                if (modal) modal.hide();
                                status.innerHTML = '';
                            }, 1200);
                        } else {
                            status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Upload failed') + '</div>';
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed';
                        status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + msg + '</div>';
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>
@endsection
