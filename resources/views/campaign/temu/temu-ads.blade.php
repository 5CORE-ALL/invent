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
        'sub_title' => 'Matches Temu Data Report Overall (Last 30 days includes today, US Pacific)',
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
                            <button type="button" id="refresh-status-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item"
                                    title="Refresh Active/Inactive from Temu ad detail API">
                                <i class="fa fa-toggle-on"></i> Refresh Status
                            </button>
                            <button type="button" id="create-ad-btn" class="btn btn-sm btn-warning pricing-filter-item"
                                    title="Create a Temu search ad for a goods ID">
                                <i class="fa fa-plus"></i> Create Ad
                            </button>
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

    {{-- Create Ad modal --}}
    <div class="modal fade" id="createAdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Temu Ad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Calls <code>temu.searchrec.ad.create</code>. Budget is daily USD. ROAS is the target multiple (12 = 12x).</p>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-goods-id">Goods ID</label>
                        <input type="text" id="create-goods-id" class="form-control form-control-sm" placeholder="602442267775049">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-budget">Daily budget (USD)</label>
                        <input type="number" id="create-budget" class="form-control form-control-sm" min="1" step="0.01" value="10">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-roas">Target ROAS</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="create-roas" class="form-control" min="0.1" step="0.1" value="12">
                            <button type="button" class="btn btn-outline-secondary" id="predict-roas-btn">Suggest</button>
                        </div>
                    </div>
                    <div id="create-ad-status" class="mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-warning" id="create-ad-submit">Create Ad</button>
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
                    {
                        title: 'Status',
                        field: 'ad_status',
                        width: 110,
                        hozAlign: 'center',
                        headerFilter: 'list',
                        headerFilterParams: { values: { Active: 'Active', Inactive: 'Inactive', 'No ad': 'No ad', Deleted: 'Deleted', Unknown: 'Unknown', '': '' } },
                        headerTooltip: 'Temu ad campaign status (Active / Inactive)',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || 'Unknown');
                            let cls = 'bg-secondary';
                            if (v === 'Active') cls = 'bg-success';
                            else if (v === 'Inactive') cls = 'bg-warning text-dark';
                            else if (v === 'Deleted' || v === 'No ad') cls = 'bg-dark';
                            return '<span class="badge ' + cls + '">' + v + '</span>';
                        }
                    },
                    { title: 'Impressions', field: 'impressions', width: 120, hozAlign: 'right', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Impressions (Overall) — same as Temu Data Report' },
                    { title: 'Clicks', field: 'clicks', width: 100, hozAlign: 'right', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Clicks (Overall) — same as Temu Data Report' },
                    { title: 'CTR', field: 'ctr', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CTR (Overall)' },
                    { title: 'CVR', field: 'cvr', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CVR (Overall) = Orders ÷ Clicks' },
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
                        title: 'Ad',
                        field: 'create_ad',
                        width: 80,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-warning create-row-ad-btn" title="Create Temu ad">Create</button>';
                        },
                        cellClick: function (e, cell) {
                            const goodsId = cell.getRow().getData().goods_id || '';
                            document.getElementById('create-goods-id').value = goodsId;
                            document.getElementById('create-ad-status').style.display = 'none';
                            new bootstrap.Modal(document.getElementById('createAdModal')).show();
                        }
                    },
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

            function showCreateStatus(html) {
                const el = document.getElementById('create-ad-status');
                el.style.display = 'block';
                el.innerHTML = html;
            }

            function openCreateModal(goodsId) {
                document.getElementById('create-goods-id').value = goodsId || '';
                document.getElementById('create-ad-status').style.display = 'none';
                new bootstrap.Modal(document.getElementById('createAdModal')).show();
            }

            document.getElementById('refresh-status-btn').addEventListener('click', function () {
                const status = document.getElementById('fetch-status');
                const btn = this;
                if (!confirm('Refresh Active/Inactive status from Temu for all goods on this page?')) {
                    return;
                }
                status.style.display = 'block';
                status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Refreshing ad status…</div>';
                btn.disabled = true;
                $.ajax({
                    url: '{{ route("temu.ads.refresh-status") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    timeout: 0,
                    success: function (response) {
                        status.innerHTML = '<div class="alert ' + (response.success ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + (response.message || 'Done') + '</div>';
                        table.setData(dataUrl());
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Status refresh failed';
                        status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + msg + '</div>';
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });

            document.getElementById('create-ad-btn').addEventListener('click', function () {
                const q = (document.getElementById('search-input').value || '').trim();
                openCreateModal(/^\d+$/.test(q) ? q : '');
            });

            document.getElementById('predict-roas-btn').addEventListener('click', function () {
                const goodsId = (document.getElementById('create-goods-id').value || '').trim();
                if (!goodsId) {
                    showCreateStatus('<div class="alert alert-warning py-2 mb-0">Enter a Goods ID first.</div>');
                    return;
                }
                const btn = this;
                btn.disabled = true;
                showCreateStatus('<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Asking Temu for suggested ROAS…</div>');
                $.ajax({
                    url: '{{ route("temu.ads.predict-roas") }}',
                    method: 'POST',
                    data: { goods_id: goodsId, _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (!response.success) {
                            showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Predict failed') + '</div>');
                            return;
                        }
                        const raw = response.result;
                        let suggested = null;
                        if (raw && typeof raw === 'object') {
                            suggested = raw.roas ?? raw.predRoas ?? raw.predictRoas
                                ?? (raw.goodsInfoList && raw.goodsInfoList[0] && (raw.goodsInfoList[0].roas || raw.goodsInfoList[0].predRoas))
                                ?? null;
                            if (suggested && typeof suggested === 'object') suggested = suggested.val ?? suggested.roas ?? null;
                        }
                        if (suggested != null && Number(suggested) > 0) {
                            let n = Number(suggested);
                            if (n > 1000) n = n / 1000;
                            document.getElementById('create-roas').value = String(Math.round(n * 10) / 10);
                            showCreateStatus('<div class="alert alert-success py-2 mb-0">Suggested ROAS filled from Temu.</div>');
                        } else {
                            showCreateStatus('<div class="alert alert-warning py-2 mb-0">No suggested ROAS in response. Check Raw if needed.</div>');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Predict failed';
                        showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });

            document.getElementById('create-ad-submit').addEventListener('click', function () {
                const goodsId = (document.getElementById('create-goods-id').value || '').trim();
                const budget = parseFloat(document.getElementById('create-budget').value);
                const roas = parseFloat(document.getElementById('create-roas').value);
                if (!goodsId || !(budget >= 1) || !(roas >= 0.1)) {
                    showCreateStatus('<div class="alert alert-warning py-2 mb-0">Enter Goods ID, budget (≥ $1), and ROAS (≥ 0.1).</div>');
                    return;
                }
                if (!confirm('Create a live Temu ad for goods ' + goodsId + '?\nDaily budget: $' + budget.toFixed(2) + '\nTarget ROAS: ' + roas)) {
                    return;
                }
                const btn = this;
                btn.disabled = true;
                showCreateStatus('<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Creating ad…</div>');
                $.ajax({
                    url: '{{ route("temu.ads.create") }}',
                    method: 'POST',
                    data: { goods_id: goodsId, budget: budget, roas: roas, _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (response.success) {
                            showCreateStatus('<div class="alert alert-success py-2 mb-0">' + (response.message || 'Created') + '</div>');
                            table.setData(dataUrl());
                        } else {
                            showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Failed') + '</div>');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Create failed';
                        showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
                    },
                    complete: function () {
                        btn.disabled = false;
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
