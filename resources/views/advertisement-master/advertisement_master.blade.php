@php
    $pageTitle = 'Advertisement Master';
    $pageSubtitle = '';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .adm-stat-badge {
            display: inline-block;
            flex-shrink: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            line-height: 1.2;
        }
        .adm-stat-badge--spend  { background: #ef4444; }
        .adm-stat-badge--clicks { background: #4c7ed8; }
        .adm-stat-badge--sold   { background: #f59e0b; }
        .adm-stat-badge--sales  { background: #16a34a; }
        .adm-stat-badge--cvr    { background: #db2777; }
        .adm-stat-badge--acos   { background: #ea580c; }
        .adm-stat-badge--tcos   { background: #7c3aed; }
        .adm-stat-badge--ssales { background: #0d9488; }
        .adm-stat-badge--active { background: #059669; }
        .adm-badge-link { cursor: pointer; transition: transform .1s ease, filter .1s ease; }
        .adm-badge-link:hover { transform: translateY(-1px); filter: brightness(1.1); }

        #advertisement-master-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }

        #advertisement-master-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 11px;
        }

        #advertisement-master-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
            height: auto !important;
            min-height: 0 !important;
            display: block;
            align-items: unset;
            justify-content: unset;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            padding: 5px 3px;
            text-align: center;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }

        #advertisement-master-wrap .tabulator .tabulator-row {
            min-height: 32px;
        }

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
            text-align: center;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell:first-child {
            text-align: left;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-right: 4px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            vertical-align: middle;
            flex-shrink: 0;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control-expand::after {
            content: '+';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control-collapse::after {
            content: '−';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #advertisement-master-wrap .tabulator-row.adm-child-row .tabulator-cell {
            background: #f8fafc;
        }

        #advertisement-master-wrap .tabulator-row.adm-child-row:hover .tabulator-cell {
            background: #f1f5f9;
        }

        /* Metric cells open the trend chart on click. */
        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell.adm-metric-cell {
            cursor: pointer;
        }
        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell.adm-metric-cell:hover {
            background: #e0f7fa;
        }

        /* ── Badge trend chart modal — full-screen width, pinned to top
           (same look & sizing as /shopify-ads-master). --}} */
        #admTrendsModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #admTrendsModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #admTrendsModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title' => $pageSubtitle,
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 flex-grow-1 py-1" style="min-width:0;">
                            <span class="adm-stat-badge adm-stat-badge--active" title="Active (running / enabled) campaigns across all channels">ACTIVE: <span id="adm-badge-active">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--spend adm-badge-link" data-metric="spend" data-label="Spend" title="Click for trend">SPEND: <span id="adm-badge-spend">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--clicks adm-badge-link" data-metric="clicks" data-label="Clicks" title="Click for trend">CLICKS: <span id="adm-badge-clicks">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sold adm-badge-link" data-metric="sold" data-label="Sold" title="Click for trend">SOLD: <span id="adm-badge-sold">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sales adm-badge-link" data-metric="sales" data-label="Ads Sales" title="Click for trend">ADS SALES: <span id="adm-badge-sales">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--cvr adm-badge-link" data-metric="cvr" data-label="CVR" title="Click for trend">CVR: <span id="adm-badge-cvr">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--acos adm-badge-link" data-metric="acos" data-label="ACOS" title="Click for trend">ACOS: <span id="adm-badge-acos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--tcos adm-badge-link" data-metric="tcos" data-label="Tcos" title="Click for trend">TCOS: <span id="adm-badge-tcos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--ssales adm-badge-link" data-metric="ssales" data-label="Total Sales" title="Combined Amazon + eBay + eBay 2 + eBay 3 + Shopify L30 store sales — click for trend">TOTAL SALES: <span id="adm-badge-ssales">$0</span></span>
                        </div>
                        <input type="text" id="adm-search" class="form-control form-control-sm"
                            placeholder="Search channel…" style="width:180px; flex-shrink:0;">
                        <button type="button" id="adm-trends" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-chart-line"></i> Trends
                        </button>
                        <button type="button" id="adm-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>

                    <div id="advertisement-master-wrap">
                        <div id="advertisement-master-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Badge/cell trend chart modal (same look as /shopify-ads-master) ──
         Clicking a badge or a metric cell opens this modal;
         /advertisement-master/history feeds it. --}}
    <div class="modal fade p-0" id="admTrendsModal" tabindex="-1" aria-labelledby="admTrendsLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#0d6efd;color:#fff;">
                    <h6 class="modal-title fw-bold" id="admTrendsLabel">
                        <i class="fa fa-chart-line me-1"></i>
                        <span id="adm-trend-title">Trend</span>
                    </h6>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <select id="adm-trend-channel" class="form-select form-select-sm" style="width:auto;">
                            <option value="__total__">All channels</option>
                        </select>
                        <select id="adm-trend-days" class="form-select form-select-sm" style="width:110px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex">
                        <div style="flex:1; min-height:320px; padding:8px;">
                            <canvas id="adm-trend-canvas"></canvas>
                            <p class="text-center text-muted small mb-0 d-none" id="adm-trend-empty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div style="width:120px; border-left:1px solid #dee2e6; padding:14px 10px; text-align:center; font-family:'Inter',system-ui,sans-serif;">
                            <div class="small text-uppercase fw-bold" style="color:#dc3545;">Highest</div>
                            <div class="fs-5 fw-bold" id="adm-trend-highest">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#6c757d;">Median</div>
                            <div class="fs-5 fw-bold" id="adm-trend-median">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#198754;">Lowest</div>
                            <div class="fs-5 fw-bold" id="adm-trend-lowest">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let admSSales = 0;

            // Metrics where a HIGHER value is worse (cost side): an up move
            // reads red, a down move green — the opposite of clicks/sold/etc.
            const ADM_INVERTED_METRICS = { spend: true, acos: true, tcos: true };

            // Day-over-day trend dot for a metric cell. Green = improvement,
            // red = decline, grey = no change; nothing when there's no prior
            // day to compare against. Direction ('up'|'down'|'flat') is
            // supplied per-row by the backend in `row.trend`.
            function admTrendDot(cell) {
                const field = cell.getField();
                const data  = cell.getRow().getData() || {};
                const dir   = (data.trend || {})[field];
                if (!dir) return '';

                let color;
                if (dir === 'flat') {
                    color = '#9ca3af';
                } else {
                    const inverted = !!ADM_INVERTED_METRICS[field];
                    const improved = inverted ? (dir === 'down') : (dir === 'up');
                    color = improved ? '#28a745' : '#dc3545';
                }
                return '<span title="vs previous day" style="display:inline-block;width:8px;height:8px;'
                    + 'border-radius:50%;background:' + color + ';margin-left:5px;vertical-align:middle;"></span>';
            }

            function wholeMoneyFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return '$' + Math.round(value).toLocaleString() + admTrendDot(cell);
            }

            function intFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return Math.round(value).toLocaleString() + admTrendDot(cell);
            }

            function percentFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return value.toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1,
                }) + '%' + admTrendDot(cell);
            }

            function updateBadges(rows) {
                let spend = 0, clicks = 0, sold = 0, sales = 0, active = 0;
                rows.forEach(function (r) {
                    if (r && r.is_sub_row) return;
                    spend  += Number(r.spend  || 0);
                    clicks += Number(r.clicks || 0);
                    sold   += Number(r.sold   || 0);
                    sales  += Number(r.sales  || 0);
                    active += Number(r.active || 0);
                });
                const cvr  = clicks > 0 ? (sold  / clicks) * 100 : 0;
                const acos = sales  > 0 ? (spend / sales)  * 100 : (spend > 0 ? 100 : 0);
                const tcos = admSSales > 0 ? (spend / admSSales) * 100 : (spend > 0 ? 100 : 0);

                const activeEl = document.getElementById('adm-badge-active');
                if (activeEl) activeEl.textContent = Math.round(active).toLocaleString();
                document.getElementById('adm-badge-spend').textContent  = '$' + Math.round(spend).toLocaleString();
                document.getElementById('adm-badge-clicks').textContent = Math.round(clicks).toLocaleString();
                document.getElementById('adm-badge-sold').textContent   = Math.round(sold).toLocaleString();
                document.getElementById('adm-badge-sales').textContent  = '$' + Math.round(sales).toLocaleString();
                document.getElementById('adm-badge-cvr').textContent    = cvr.toFixed(1) + '%';
                document.getElementById('adm-badge-acos').textContent   = Math.round(acos) + '%';
                document.getElementById('adm-badge-tcos').textContent   = Math.round(tcos) + '%';
            }

            const channelLinks = {
                'Amazon': "{{ route('amazon.ads.all') }}",
                'Amazon · KW': "{{ route('amazon.ads.all') }}?search=KW",
                'Amazon · PT': "{{ route('amazon.ads.all') }}?search=PT",
                'Amazon · HL': "{{ route('amazon.ads.all') }}?source=sb_reports",
                'eBay': "{{ route('ebay.campaign.ads') }}",
                'eBay 2': "{{ route('ebay2.campaign.ads') }}",
                'eBay 3': "{{ route('ebay3.campaign.ads') }}",
                'Shopify': "{{ route('shopify.ads.master') }}",
                'Shopify · Google Shopping': "{{ route('google.shopping.campaigns') }}",
                'Shopify · Google SERP': "{{ route('google.serp.campaigns') }}",
                'Shopify · Youtube ads': "{{ route('google.youtube.ads.campaigns') }}",
                'Shopify · Facebook': "{{ route('facebook.ads.channel') }}",
                'Shopify · Facebook · G Video': "{{ route('facebook.ads.channel.group.video') }}",
                'Shopify · Facebook · G Carousal': "{{ route('facebook.ads.channel.group.carousal') }}",
                'Shopify · Facebook · P Video': "{{ route('facebook.ads.channel.parent.video') }}",
                'Shopify · Facebook · P Carousal': "{{ route('facebook.ads.channel.parent.carousal') }}",
                'Shopify · Instagram': "{{ route('instagram.ads.channel') }}",
                'Shopify · Instagram · G Video': "{{ route('instagram.ads.channel.group.video') }}",
                'Shopify · Instagram · G Carousal': "{{ route('instagram.ads.channel.group.carousal') }}",
                'Shopify · Instagram · P Video': "{{ route('instagram.ads.channel.parent.video') }}",
                'Shopify · Instagram · P Carousal': "{{ route('instagram.ads.channel.parent.carousal') }}",
            };

            function channelFormatter(cell) {
                const name = cell.getValue() || '';
                const url  = channelLinks[name];
                const row  = cell.getRow().getData() || {};
                const isChild = !!cell.getRow().getTreeParent();
                const weight = isChild ? 'font-weight:500;' : 'font-weight:600;';
                const color = isChild ? 'color:#475569;' : '';
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:inherit;text-decoration:underline;' + weight + color + '">' + name + '</a>';
                }
                return '<span style="' + weight + color + '">' + name + '</span>';
            }

            const dataUrl = "{{ route('advertisement.master.data') }}";

            const table = new Tabulator('#advertisement-master-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    admSSales = Number(response.total_net_sales || 0);
                    const ssEl = document.getElementById('adm-badge-ssales');
                    if (ssEl) {
                        ssEl.textContent = '$' + Number(admSSales).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }
                    updateBadges(rows);
                    buildAdmChannelOptions(rows);
                    return rows;
                },
                layout: 'fitColumns',
                headerSort: true,
                initialSort: [],
                dataTree: true,
                dataTreeStartExpanded: false,
                dataTreeChildField: '_children',
                dataTreeFilter: true,
                rowFormatter: function (row) {
                    if (row.getTreeParent()) {
                        row.getElement().classList.add('adm-child-row');
                    }
                },
                columns: [
                    { title: 'Channel', field: 'channel', minWidth: 150, headerSort: true, formatter: channelFormatter },
                    { title: 'ACTIVE', field: 'active', hozAlign: 'center', formatter: intFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'SPEND', field: 'spend', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'CLICKS', field: 'clicks', hozAlign: 'center', formatter: intFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'SOLD', field: 'sold', hozAlign: 'center', formatter: intFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'ADS SALES', field: 'sales', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'CVR', field: 'cvr', hozAlign: 'center', formatter: percentFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'ACOS', field: 'acos', hozAlign: 'center', formatter: percentFormatter, headerSort: true, cssClass: 'adm-metric-cell', cellClick: admCellChart },
                ],
            });

            table.on('dataFiltered', function () {
                updateBadges(table.getData());
            });

            document.getElementById('adm-search').addEventListener('input', function () {
                const q = this.value.trim();
                if (q === '') {
                    table.clearFilter();
                } else {
                    table.setFilter('channel', 'like', q);
                }
            });

            document.getElementById('adm-refresh').addEventListener('click', function () {
                document.getElementById('adm-search').value = '';
                table.clearFilter();
                table.setData(dataUrl);
            });

            // ── Badge / cell trend chart (same look as /shopify-ads-master) ──
            const historyUrl = "{{ route('advertisement.master.history') }}";
            let admTrendChart  = null;
            let admTrendCache  = null;
            let admTrendMetric = 'spend';
            let admTrendLabel  = 'Spend';

            // Build the channel selector options from the loaded tree (flattened),
            // so every parent + child channel can be lensed in the chart.
            function buildAdmChannelOptions(rows) {
                const sel = document.getElementById('adm-trend-channel');
                if (!sel) return;
                const names = [];
                (function walk(list) {
                    (list || []).forEach(function (r) {
                        if (r && r.channel) names.push(r.channel);
                        if (r && r._children) walk(r._children);
                    });
                })(rows);
                const current = sel.value;
                sel.innerHTML = '<option value="__total__">All channels</option>'
                    + names.map(function (n) { return '<option value="' + n + '">' + n + '</option>'; }).join('');
                if ([...sel.options].some(function (o) { return o.value === current; })) sel.value = current;
            }

            function fmtAdmValue(metric, v) {
                if (v === null || v === undefined || isNaN(v)) return '—';
                if (metric === 'spend' || metric === 'sales' || metric === 'ssales') return '$' + Math.round(v).toLocaleString('en-US');
                if (metric === 'acos' || metric === 'cvr' || metric === 'tcos') return Number(v).toFixed(1) + '%';
                return Math.round(v).toLocaleString('en-US');
            }

            function admSeriesFor(payload, channel, metric) {
                if (!payload) return [];
                if (channel === '__total__') return (payload.metrics || {})[metric] || [];
                const ch = (payload.channels || {})[channel];
                return ch ? (ch[metric] || []) : [];
            }

            // Clicking a metric cell opens the chart lensed to that row + metric.
            function admCellChart(e, cell) {
                const metric  = cell.getField();
                const channel = (cell.getRow().getData() || {}).channel || '__total__';
                const labels  = { spend: 'Spend', clicks: 'Clicks', sold: 'Sold', sales: 'Ads Sales', active: 'Active', cvr: 'CVR', acos: 'ACOS' };
                openAdmChart(metric, labels[metric] || metric.toUpperCase(), channel);
            }

            document.querySelectorAll('.adm-badge-link').forEach(function (el) {
                el.addEventListener('click', function () {
                    openAdmChart(this.dataset.metric, this.dataset.label || this.dataset.metric.toUpperCase());
                });
            });
            document.getElementById('adm-trends')?.addEventListener('click', function () {
                openAdmChart('spend', 'Spend');
            });
            document.getElementById('adm-trend-channel')?.addEventListener('change', function () {
                admSetTrendTitle();
                renderAdmChart();
            });
            document.getElementById('adm-trend-days')?.addEventListener('change', loadAdmHistory);

            // Show/hide the modal — Bootstrap API when available, manual fallback
            // otherwise so it opens even if window.bootstrap isn't ready.
            function admShowModal() {
                const modalEl = document.getElementById('admTrendsModal');
                if (!modalEl) return;
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                    modalEl.removeAttribute('aria-hidden');
                    modalEl.setAttribute('aria-modal', 'true');
                    document.body.classList.add('modal-open');
                    if (!document.getElementById('adm-modal-backdrop')) {
                        const bd = document.createElement('div');
                        bd.id = 'adm-modal-backdrop';
                        bd.className = 'modal-backdrop fade show';
                        document.body.appendChild(bd);
                        bd.addEventListener('click', admHideModal);
                    }
                }
                setTimeout(function () { if (admTrendChart) admTrendChart.resize(); }, 200);
            }

            function admHideModal() {
                const modalEl = document.getElementById('admTrendsModal');
                if (!modalEl) return;
                if (window.bootstrap && bootstrap.Modal) {
                    const inst = bootstrap.Modal.getInstance(modalEl);
                    if (inst) { inst.hide(); return; }
                }
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                const bd = document.getElementById('adm-modal-backdrop');
                if (bd) bd.remove();
            }

            document.querySelectorAll('#admTrendsModal [data-bs-dismiss="modal"]').forEach(function (btn) {
                btn.addEventListener('click', admHideModal);
            });

            function openAdmChart(metric, label, channel) {
                admTrendMetric = metric;
                admTrendLabel  = label;
                const chSel = document.getElementById('adm-trend-channel');
                if (chSel) {
                    const wanted = channel || '__total__';
                    chSel.value = [...chSel.options].some(function (o) { return o.value === wanted; }) ? wanted : '__total__';
                }
                admSetTrendTitle();
                admShowModal();
                loadAdmHistory();
            }

            function admSetTrendTitle() {
                const days = parseInt(document.getElementById('adm-trend-days').value || '30', 10);
                const chSel = document.getElementById('adm-trend-channel');
                const ch = chSel ? chSel.value : '__total__';
                const chTxt = (ch && ch !== '__total__') ? ' · ' + ch : '';
                const titleEl = document.getElementById('adm-trend-title');
                if (titleEl) titleEl.textContent = admTrendLabel + chTxt + ' (Rolling L' + days + ')';
            }

            function loadAdmHistory() {
                const days = parseInt(document.getElementById('adm-trend-days').value || '30', 10);
                admSetTrendTitle();
                fetch(historyUrl + '?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (payload) { admTrendCache = payload; renderAdmChart(); })
                    .catch(function () { admTrendCache = { labels: [], metrics: {}, channels: {} }; renderAdmChart(); });
            }

            function renderAdmChart() {
                const canvas  = document.getElementById('adm-trend-canvas');
                const emptyEl = document.getElementById('adm-trend-empty');
                if (!canvas) return;
                const metric  = admTrendMetric;
                const channel = document.getElementById('adm-trend-channel').value;
                const labels  = (admTrendCache && admTrendCache.labels) || [];
                const values  = admSeriesFor(admTrendCache, channel, metric).map(function (v) { return Number(v) || 0; });

                if (admTrendChart) { admTrendChart.destroy(); admTrendChart = null; }
                ['adm-trend-highest', 'adm-trend-median', 'adm-trend-lowest'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                if (!labels.length) {
                    canvas.style.display = 'none';
                    emptyEl?.classList.remove('d-none');
                    return;
                }
                if (typeof Chart === 'undefined') {
                    canvas.style.display = 'none';
                    if (emptyEl) { emptyEl.textContent = 'Chart library failed to load. Check your connection and refresh.'; emptyEl.classList.remove('d-none'); }
                    return;
                }
                canvas.style.display = '';
                emptyEl?.classList.add('d-none');

                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort(function (a, b) { return a - b; });
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = (dataMax - dataMin) || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                const setStat = function (id, v) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = fmtAdmValue(metric, v);
                    el.style.color = (v === 0) ? refGreen : (v > 0 ? refRed : refGray);
                };
                setStat('adm-trend-highest', dataMax);
                setStat('adm-trend-median',  median);
                setStat('adm-trend-lowest',  dataMin);

                const isInverted = (metric === 'acos' || metric === 'tcos' || metric === 'spend');
                const dotColors = values.map(function (v, i) {
                    if (i === 0) return refGray;
                    if (isInverted) {
                        return v < values[i - 1] ? '#28a745' : v > values[i - 1] ? '#dc3545' : refGray;
                    }
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : refGray;
                });

                const medianLinePlugin = {
                    id: 'medianLine',
                    afterDraw: function (chart) {
                        const yScale = chart.scales.y, xScale = chart.scales.x, ctx = chart.ctx;
                        const yPixel = yScale.getPixelForValue(median);
                        ctx.save();
                        ctx.setLineDash([6, 4]);
                        ctx.strokeStyle = '#6c757d';
                        ctx.lineWidth = 1.2;
                        ctx.beginPath();
                        ctx.moveTo(xScale.left, yPixel);
                        ctx.lineTo(xScale.right, yPixel);
                        ctx.stroke();
                        ctx.restore();
                    }
                };
                const labelColors = values.map(function (v) { return v === 0 ? refGreen : (v > 0 ? refRed : refGray); });
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDatasetsDraw: function (chart) {
                        const meta = chart.getDatasetMeta(0), ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach(function (point, i) {
                            const offY = (i % 2 === 0) ? -10 : -20;
                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(fmtAdmValue(metric, values[i]), point.x, point.y + offY);
                        });
                        ctx.restore();
                    }
                };

                admTrendChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: admTrendLabel,
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 24, right: 16, bottom: 12, left: 16 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function (ctx) { return fmtAdmValue(metric, ctx.parsed.y); } } },
                        },
                        scales: {
                            y: { min: yMin, max: yMax, ticks: { callback: function (v) { return fmtAdmValue(metric, v); } } },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } },
                        },
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                });
            }

            document.getElementById('admTrendsModal')?.addEventListener('shown.bs.modal', function () {
                if (admTrendChart) admTrendChart.resize();
            });
        });
    </script>
@endsection
