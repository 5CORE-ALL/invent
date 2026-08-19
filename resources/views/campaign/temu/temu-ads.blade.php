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
                            <button type="button" id="temu-ads-rules-btn" class="btn btn-sm btn-outline-dark pricing-filter-item"
                                    data-bs-toggle="modal" data-bs-target="#temuAdsRulesModal"
                                    title="Open L7 Clicks / Stop ROAS bidding rule">
                                <i class="fas fa-sliders-h me-1"></i><span id="temu-ads-rules-summary">L7 &lt; 70 → ROAS 8</span>
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" id="refresh-status-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item"
                                    title="Sync Active/Inactive from Temu ad.detail.query (adsDetail.adShowStatus)">
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

    {{-- Shared L7 Clicks → Stop ROAS bidding rule --}}
    <div class="modal fade" id="temuAdsRulesModal" tabindex="-1" aria-labelledby="temuAdsRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="temuAdsRulesModalLabel">Ad rules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Shared with /temu-decrease. If L7 clicks are below the threshold, the row is red. Active ads with L7 clicks below the threshold and ROAS below Stop ROAS are paused automatically after the daily L7 fetch.</p>
                    <div class="d-inline-flex flex-wrap align-items-center gap-1 border rounded px-3 py-2 bg-light">
                        <label for="temu-l7-clicks-red-threshold" class="mb-0 small fw-semibold text-nowrap">L7 Clicks &lt;</label>
                        <input type="number" id="temu-l7-clicks-red-threshold" class="form-control form-control-sm"
                               min="0" max="100000" step="1" value="70" style="width: 70px;">
                        <span class="small fw-bold" style="color:#a00211;">Red</span>
                        <span class="text-muted px-1">→</span>
                        <label for="temu-target-roas-bidding" class="mb-0 small fw-semibold text-nowrap">Stop ROAS</label>
                        <input type="number" id="temu-target-roas-bidding" class="form-control form-control-sm"
                               min="0.1" max="1000" step="0.1" value="8" style="width: 70px;">
                        <span class="small fw-bold" style="color:#0d6efd;">Pause</span>
                    </div>
                    <div id="temu-ads-pause-status" class="mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-danger" id="temu-ads-auto-pause-btn"
                            title="Pause Active ads that match this rule on Temu now">
                        <i class="fas fa-pause me-1"></i>Pause matching ads
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    <p class="text-muted small mb-3">Calls <code>temu.searchrec.ad.create</code>. Budget is daily USD. Target ROAS defaults to the shared Budget and Bidding rule (8).</p>
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
                            <input type="number" id="create-roas" class="form-control" min="0.1" step="0.1" value="8">
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
    <script src="{{ asset('js/temu-ads-color-rules.js') }}?v=5"></script>
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
            const clicksFmt = (cell) => {
                const shown = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.fontWeight = '';
                }
                if (shown === null || shown === undefined || shown === '') return '';
                const n = Number(String(shown).replace(/,/g, ''));
                if (window.TemuAdsColorRules) {
                    TemuAdsColorRules.colorL7Clicks(el, n);
                }
                return n.toLocaleString('en-US');
            };
            const pctFmt = (cell) => {
                const v = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.fontWeight = '';
                }
                if (v === null || v === undefined || v === '') return '';
                if (window.TemuAdsColorRules && cell.getField && cell.getField() === 'acos') {
                    const row = cell.getRow ? cell.getRow().getData() : {};
                    TemuAdsColorRules.colorAcosBidding(el, v, row.clicks_l7 != null ? row.clicks_l7 : row.clicks);
                }
                return Number(v).toFixed(2) + '%';
            };
            const decFmt = (cell) => {
                const v = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.fontWeight = '';
                }
                if (v === null || v === undefined || v === '') return '';
                if (window.TemuAdsColorRules && cell.getField && cell.getField() === 'roas') {
                    const row = cell.getRow ? cell.getRow().getData() : {};
                    TemuAdsColorRules.colorRoasBidding(el, v, row.clicks_l7 != null ? row.clicks_l7 : row.clicks);
                }
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
                        headerFilterParams: { values: { Active: 'Active', Inactive: 'Inactive', 'No ad': 'No ad', Deleted: 'Deleted', 'Not sync': 'Not sync', '': '' } },
                        headerTooltip: 'Temu ad campaign status from ad.detail.query (Active / Inactive / No ad). Not sync = API not confirmed.',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || 'Not sync');
                            let cls = 'bg-secondary';
                            if (v === 'Active') cls = 'bg-success';
                            else if (v === 'Inactive') cls = 'bg-warning text-dark';
                            else if (v === 'Deleted' || v === 'No ad') cls = 'bg-dark';
                            else if (v === 'Not sync') cls = 'bg-secondary';
                            return '<span class="badge ' + cls + '">' + v + '</span>';
                        }
                    },
                    { title: 'Impressions', field: 'impressions', width: 120, hozAlign: 'right', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Impressions (Overall) — same as Temu Data Report' },
                    { title: 'Clicks', field: 'clicks', width: 100, hozAlign: 'right', formatter: clicksFmt, sorter: 'number',
                      headerTooltip: 'Clicks (Overall). Red when Last 7 days clicks are below the shared coloring rule (default 70).' },
                    { title: 'CTR', field: 'ctr', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CTR (Overall)' },
                    { title: 'CVR', field: 'cvr', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CVR (Overall) = Orders ÷ Clicks' },
                    { title: 'Cart', field: 'cart_cnt', width: 90, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Orders', field: 'order_pay_cnt', width: 90, hozAlign: 'right', formatter: numFmt, sorter: 'number' },
                    { title: 'Order $', field: 'order_pay_amt', width: 110, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Spend', field: 'ad_spend', width: 100, hozAlign: 'right', formatter: moneyFmt, sorter: 'number' },
                    { title: 'ROAS', field: 'roas', width: 90, hozAlign: 'right', formatter: decFmt, sorter: 'number',
                      headerTooltip: 'Actual ROAS. Blue when L7 clicks are below the merged rule and ROAS is below Stop ROAS / Bidding (default 8).' },
                    { title: 'ACOS', field: 'acos', width: 90, hozAlign: 'right', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'ACOS. Blue when L7 clicks are below the merged rule and ACOS is worse than Stop ROAS / Bidding (default 8).' },
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

            if (window.TemuAdsColorRules) {
                TemuAdsColorRules.setUrls(
                    @json(route('temu.ads.color-rules')),
                    @json(route('temu.ads.color-rules.save')),
                    @json(route('temu.ads.auto-pause'))
                );
                TemuAdsColorRules.bindThresholdInput(document.getElementById('temu-l7-clicks-red-threshold'));
                TemuAdsColorRules.bindTargetRoasInput(document.getElementById('temu-target-roas-bidding'));
                TemuAdsColorRules.bindRuleSummary(document.getElementById('temu-ads-rules-summary'));
                TemuAdsColorRules.bindAutoPauseButton(
                    document.getElementById('temu-ads-auto-pause-btn'),
                    document.getElementById('temu-ads-pause-status'),
                    function () { table.setData(dataUrl()); }
                );
                TemuAdsColorRules.onChange(function () {
                    const createRoas = document.getElementById('create-roas');
                    if (createRoas && document.activeElement !== createRoas) {
                        createRoas.value = String(TemuAdsColorRules.getTargetRoasBidding());
                    }
                    table.redraw(true);
                });
            }

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
                if (window.TemuAdsColorRules) {
                    document.getElementById('create-roas').value = String(TemuAdsColorRules.getTargetRoasBidding());
                }
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
                let fetchMsg = 'Fetch Temu ads API reports for ' + period + ' for all goods?\nThis may take several minutes.';
                if (period === 'L7') {
                    fetchMsg += '\n\nMatching Active ads (L7 clicks / Stop ROAS rule) will be paused automatically.';
                }
                if (!confirm(fetchMsg)) {
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
