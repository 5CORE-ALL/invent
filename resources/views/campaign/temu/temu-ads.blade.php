@extends('layouts.vertical', ['title' => 'Temu Ads (API)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #temu-ads-table .tabulator-header {
            background: #fd7e14;
            font-size: 0.8rem;
            color: #fff;
        }
        #temu-ads-table .tabulator-header .tabulator-col {
            background: #fd7e14;
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.25);
        }
        #temu-ads-table .tabulator-cell {
            font-size: 0.85rem;
        }
        #temu-ads-table .tabulator-footer {
            background: #f4f7fa;
            padding: 8px;
        }
        #summary-stats .temu-ads-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .pricing-filter-item {
            display: inline-block;
            vertical-align: middle;
        }
        #raw-json-pre {
            max-height: 70vh;
            overflow: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu Ads (API)',
        'sub_title' => 'Overall goods ad reports (Seller Center Overall clicks/impressions) from temu.searchrec.ad.reports.goods.query',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="period-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;">
                                <option value="">All Periods</option>
                                <option value="L7">L7</option>
                                <option value="L30" selected>L30</option>
                                <option value="L60">L60</option>
                            </select>
                            <input type="text" id="search-input" class="form-control form-control-sm pricing-filter-item"
                                   placeholder="Search Goods ID / SKU" style="width: 220px;">
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" id="refresh-btn" class="btn btn-sm btn-primary pricing-filter-item"
                                    title="Fetch from Temu API and store raw data">
                                <i class="fa fa-sync"></i> Fetch from API
                            </button>
                            <button type="button" id="export-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export CSV">
                                <i class="fa fa-download"></i>
                            </button>
                        </div>
                    </div>

                    <div id="fetch-status" class="mb-2" style="display:none;"></div>

                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                        <div class="temu-ads-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-dark fs-6 p-2" id="row-count"
                                style="color: white; font-weight: bold;">Rows: 0</span>
                            <span class="badge fs-6 p-2" id="impr-sum"
                                style="background-color: #0d6efd; color: white; font-weight: bold;">Impr: 0</span>
                            <span class="badge fs-6 p-2" id="click-sum"
                                style="background-color: #e83e8c; color: white; font-weight: bold;">Clicks: 0</span>
                            <span class="badge fs-6 p-2" id="spend-sum"
                                style="background-color: #6f42c1; color: white; font-weight: bold;">Spend: $0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="temu-ads-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Raw JSON modal --}}
    <div class="modal fade" id="rawJsonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rawJsonModalLabel">Raw API Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="raw-json-pre"></pre>
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
                const period = document.getElementById('period-filter').value;
                let url = '{{ route("temu.ads.data") }}';
                if (period) url += '?period=' + encodeURIComponent(period);
                return url;
            }

            function setBadges(rows, response) {
                const n = rows.length;
                document.getElementById('row-count').textContent = 'Rows: ' + Number(n).toLocaleString();

                let impr = response && response.impressions_sum != null
                    ? Number(response.impressions_sum)
                    : rows.reduce((s, r) => s + (parseFloat(r.impressions) || 0), 0);
                let clicks = response && response.clicks_sum != null
                    ? Number(response.clicks_sum)
                    : rows.reduce((s, r) => s + (parseFloat(r.clicks) || 0), 0);
                let spend = response && response.spend_sum != null
                    ? Number(response.spend_sum)
                    : rows.reduce((s, r) => s + (parseFloat(r.ad_spend) || 0), 0);

                // When locally filtered, recompute from visible rows
                if (table) {
                    const visible = table.getData(true);
                    if (visible && visible.length !== n) {
                        // getData(true) = filtered; use that for badge after filter
                    }
                }

                document.getElementById('impr-sum').textContent = 'Impr: ' + Number(impr).toLocaleString();
                document.getElementById('click-sum').textContent = 'Clicks: ' + Number(clicks).toLocaleString();
                document.getElementById('spend-sum').textContent = 'Spend: $' + Number(spend).toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }

            function updateBadgesFromTable() {
                if (!table) return;
                const rows = table.getData(true);
                let impr = 0, clicks = 0, spend = 0;
                rows.forEach(function (r) {
                    impr += parseFloat(r.impressions) || 0;
                    clicks += parseFloat(r.clicks) || 0;
                    spend += parseFloat(r.ad_spend) || 0;
                });
                document.getElementById('row-count').textContent = 'Rows: ' + rows.length.toLocaleString();
                document.getElementById('impr-sum').textContent = 'Impr: ' + Math.round(impr).toLocaleString();
                document.getElementById('click-sum').textContent = 'Clicks: ' + Math.round(clicks).toLocaleString();
                document.getElementById('spend-sum').textContent = 'Spend: $' + spend.toLocaleString('en-US', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }

            const table = new Tabulator('#temu-ads-table', {
                ajaxURL: dataUrl(),
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    setBadges(rows, response);
                    return rows;
                },
                layout: 'fitDataStretch',
                height: '70vh',
                pagination: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, true],
                placeholder: 'No Temu ads API data yet — click “Fetch from API”.',
                columns: [
                    { title: 'Period', field: 'period', width: 70, headerFilter: 'list',
                      headerFilterParams: { values: { L7: 'L7', L30: 'L30', L60: 'L60', '': '' } } },
                    { title: 'Goods ID', field: 'goods_id', width: 150, headerFilter: 'input' },
                    { title: 'SKU', field: 'sku', width: 140, headerFilter: 'input' },
                    { title: 'Impressions', field: 'impressions', width: 120, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Clicks', field: 'clicks', width: 100, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'CTR', field: 'ctr', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    { title: 'Cart', field: 'cart_cnt', width: 90, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Orders', field: 'order_pay_cnt', width: 90, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Order $', field: 'order_pay_amt', width: 110, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Spend', field: 'ad_spend', width: 100, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'ROAS', field: 'roas', width: 90, hozAlign: 'right', formatter: decFmt, sorter: 'number' },
                    { title: 'ACOS', field: 'acos', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number' },
                    {
                        title: 'OK',
                        field: 'success',
                        width: 60,
                        hozAlign: 'center',
                        formatter: function (cell) {
                            return cell.getValue()
                                ? '<span class="text-success"><i class="fas fa-check"></i></span>'
                                : '<span class="text-danger" title="' + (cell.getRow().getData().error_msg || '') + '"><i class="fas fa-x"></i></span>';
                        }
                    },
                    { title: 'Fetched', field: 'fetched_at', width: 160 },
                    {
                        title: 'Raw',
                        field: 'raw_response',
                        width: 70,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-secondary view-raw-btn" title="View raw JSON"><i class="fas fa-code"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            const raw = cell.getRow().getData().raw_response;
                            const el = document.getElementById('raw-json-pre');
                            if (!raw) {
                                el.textContent = '(empty)';
                            } else {
                                try {
                                    el.textContent = JSON.stringify(JSON.parse(raw), null, 2);
                                } catch (err) {
                                    el.textContent = String(raw);
                                }
                            }
                            const data = cell.getRow().getData();
                            document.getElementById('rawJsonModalLabel').textContent =
                                'Raw API — Goods ' + (data.goods_id || '') + ' (' + (data.period || '') + ')';
                            new bootstrap.Modal(document.getElementById('rawJsonModal')).show();
                        }
                    },
                ],
            });

            table.on('dataFiltered', updateBadgesFromTable);
            table.on('dataLoaded', updateBadgesFromTable);

            document.getElementById('period-filter').addEventListener('change', function () {
                table.setData(dataUrl());
            });

            document.getElementById('search-input').addEventListener('input', function () {
                const q = (this.value || '').trim().toLowerCase();
                if (!q) {
                    table.clearFilter(true);
                    updateBadgesFromTable();
                    return;
                }
                table.setFilter(function (data) {
                    return [data.goods_id, data.sku]
                        .some(v => String(v || '').toLowerCase().includes(q));
                });
                updateBadgesFromTable();
            });

            document.getElementById('export-btn').addEventListener('click', function () {
                table.download('csv', 'temu-ads-api-reports.csv', {}, {
                    documentProcessing: function (doc) {
                        // strip huge raw_response from CSV export
                        return doc;
                    }
                });
            });

            document.getElementById('refresh-btn').addEventListener('click', function () {
                const period = document.getElementById('period-filter').value || 'L30';
                const status = document.getElementById('fetch-status');
                const btn = this;
                if (!confirm('Fetch Temu ads API reports for ' + period + ' for all goods?\nThis may take several minutes.')) {
                    return;
                }
                status.style.display = 'block';
                status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Fetching ' + period + ' from Temu API…</div>';
                btn.disabled = true;

                $.ajax({
                    url: '{{ route("temu.ads.refresh") }}',
                    method: 'POST',
                    data: { period: period, _token: '{{ csrf_token() }}' },
                    timeout: 0,
                    success: function (response) {
                        if (response.success) {
                            status.innerHTML = '<div class="alert alert-success py-2 mb-0">' + (response.message || 'Done') + '</div>';
                            table.setData(dataUrl());
                        } else {
                            status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Failed') + '</div>';
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Fetch failed (timeout or server error). Try: php artisan temu:fetch-ads-api-reports --period=' + period;
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
