@php
    $pageTitle = 'Shopify Ads Master';
    $pageSubtitle = 'Shopify B2C';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .sam-stat-badge {
            display: inline-block;
            flex-shrink: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            line-height: 1.2;
            cursor: pointer;
            transition: transform .1s ease, filter .1s ease;
        }
        .sam-stat-badge:hover { transform: translateY(-1px); filter: brightness(1.1); }
        .sam-stat-badge--spend  { background: #ef4444; }
        .sam-stat-badge--clicks { background: #4c7ed8; }
        .sam-stat-badge--sold   { background: #f59e0b; }
        .sam-stat-badge--sales  { background: #16a34a; }
        .sam-stat-badge--cvr    { background: #db2777; }
        .sam-stat-badge--acos   { background: #ea580c; }
        .sam-stat-badge--tcos   { background: #7c3aed; }
        .sam-stat-badge--ssales { background: #0d9488; }

        #shopify-ads-master-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }

        #shopify-ads-master-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 11px;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row {
            min-height: 32px;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
            text-align: center;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell:first-child {
            text-align: left;
        }

        /* Metric cells open the trend chart on click. */
        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell.sam-metric-cell {
            cursor: pointer;
        }
        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell.sam-metric-cell:hover {
            background: #e0f7fa;
        }

        /* ── Badge trend chart modal — full screen width, pinned to top
           (same look & sizing as /all-marketplace-master adBreakdownChartModal).
           Theme uses --tz-modal-* CSS variables, so we override those *and*
           the dialog/content widths directly to be safe across themes. */
        #samTrendsModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #samTrendsModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #samTrendsModal .modal-content {
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
                    {{-- Badge strip + Search + Refresh --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-nowrap gap-2 flex-grow-1 overflow-x-auto py-1" style="min-width:0;">
                            <span class="sam-stat-badge sam-stat-badge--spend sam-badge-link" data-metric="spend" data-label="Spend" title="Click for trend">SPEND: <span id="sam-badge-spend">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--clicks sam-badge-link" data-metric="clicks" data-label="Clicks" title="Click for trend">CLICKS: <span id="sam-badge-clicks">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sold sam-badge-link" data-metric="sold" data-label="Sold" title="Click for trend">SOLD: <span id="sam-badge-sold">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sales sam-badge-link" data-metric="sales" data-label="Ads Sales" title="Click for trend">ADS SALES: <span id="sam-badge-sales">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--cvr sam-badge-link" data-metric="cvr" data-label="CVR" title="Click for trend">CVR: <span id="sam-badge-cvr">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--acos sam-badge-link" data-metric="acos" data-label="ACOS" title="Click for trend">ACOS: <span id="sam-badge-acos">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--tcos sam-badge-link" data-metric="tcos" data-label="Tcos" title="Click for trend">TCOS: <span id="sam-badge-tcos">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--ssales sam-badge-link" data-metric="ssales" data-label="S Sales" title="Net Sales (gross − discounts) from /shopify — click for trend">S SALES: <span id="sam-badge-ssales">$0</span></span>
                        </div>
                        <input type="text" id="sam-search" class="form-control form-control-sm"
                            placeholder="Search channel…" style="width:180px; flex-shrink:0;">
                        <button type="button" id="sam-trends" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-chart-line"></i> Trends
                        </button>
                        <button type="button" id="sam-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>

                    <div id="shopify-ads-master-wrap">
                        <div id="shopify-ads-master-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Badge trend chart modal (same look as /facebook-all-ads-sheet) ──
         Clickable badge opens this modal; /shopify-ads-master/history feeds
         it. Chart.js draws a rolling line with dots (green = up, red = down,
         grey = flat), a dashed median line, value labels, and a side panel
         showing HIGHEST / MEDIAN / LOWEST. A channel selector lenses the
         series to the rolled-up total or one channel. --}}
    <div class="modal fade p-0" id="samTrendsModal" tabindex="-1" aria-labelledby="samTrendsLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#0d6efd;color:#fff;">
                    <h6 class="modal-title fw-bold" id="samTrendsLabel">
                        <i class="fa fa-chart-line me-1"></i>
                        <span id="sam-trend-title">Trend</span>
                    </h6>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <select id="sam-trend-channel" class="form-select form-select-sm" style="width:auto;">
                            <option value="__total__">All channels</option>
                            <option value="Google Shopping">Google Shopping</option>
                            <option value="Google SERP">Google SERP</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Facebook · G Video">Facebook · G Video</option>
                            <option value="Facebook · G Carousal">Facebook · G Carousal</option>
                            <option value="Facebook · P Video">Facebook · P Video</option>
                            <option value="Facebook · P Carousal">Facebook · P Carousal</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Instagram · G Video">Instagram · G Video</option>
                            <option value="Instagram · G Carousal">Instagram · G Carousal</option>
                            <option value="Instagram · P Video">Instagram · P Video</option>
                            <option value="Instagram · P Carousal">Instagram · P Carousal</option>
                        </select>
                        <select id="sam-trend-days" class="form-select form-select-sm" style="width:110px;">
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
                            <canvas id="sam-trend-canvas"></canvas>
                            <p class="text-center text-muted small mb-0 d-none" id="sam-trend-empty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div style="width:120px; border-left:1px solid #dee2e6; padding:14px 10px; text-align:center; font-family:'Inter',system-ui,sans-serif;">
                            <div class="small text-uppercase fw-bold" style="color:#dc3545;">Highest</div>
                            <div class="fs-5 fw-bold" id="sam-trend-highest">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#6c757d;">Median</div>
                            <div class="fs-5 fw-bold" id="sam-trend-median">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#198754;">Lowest</div>
                            <div class="fs-5 fw-bold" id="sam-trend-lowest">—</div>
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
            let samSSales = 0;   // latest /shopify Net Sales (for TCOS + S Sales badge)

            function wholeMoneyFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return '$' + Math.round(value).toLocaleString();
            }

            function intFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return Math.round(value).toLocaleString();
            }

            function percentFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return value.toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1,
                }) + '%';
            }

            function updateBadges(rows) {
                // Skip "sub-row" channels (Facebook · G Video, etc.) when summing — they're
                // typed slices of the parent Facebook / Instagram rows, not separate channels,
                // so including them would double-count the rolled-up totals.
                let spend = 0, clicks = 0, sold = 0, sales = 0;
                rows.forEach(function (r) {
                    if (r && r.is_sub_row) return;
                    spend  += Number(r.spend  || 0);
                    clicks += Number(r.clicks || 0);
                    sold   += Number(r.sold   || 0);
                    sales  += Number(r.sales  || 0);
                });
                const cvr  = clicks > 0 ? (sold  / clicks) * 100 : 0;
                const acos = sales  > 0 ? (spend / sales)  * 100 : (spend > 0 ? 100 : 0);
                // TCOS = Spend / S Sales (store net sales).
                const tcos = samSSales > 0 ? (spend / samSSales) * 100 : (spend > 0 ? 100 : 0);

                document.getElementById('sam-badge-spend').textContent  = '$' + Math.round(spend).toLocaleString();
                document.getElementById('sam-badge-clicks').textContent = Math.round(clicks).toLocaleString();
                document.getElementById('sam-badge-sold').textContent   = Math.round(sold).toLocaleString();
                document.getElementById('sam-badge-sales').textContent  = '$' + Math.round(sales).toLocaleString();
                document.getElementById('sam-badge-cvr').textContent    = cvr.toFixed(1)  + '%';
                document.getElementById('sam-badge-acos').textContent   = Math.round(acos) + '%';
                document.getElementById('sam-badge-tcos').textContent   = Math.round(tcos) + '%';
            }

            const channelLinks = {
                'Google Shopping': "{{ route('google.shopping.campaigns') }}",
                'Google SERP':     "{{ route('google.serp.campaigns') }}",
                'Youtube ads':     "{{ route('google.youtube.ads.campaigns') }}",
                'Facebook':        "{{ route('facebook.ads.channel') }}",
                'Facebook · G Video':    "{{ route('facebook.ads.channel.group.video') }}",
                'Facebook · G Carousal': "{{ route('facebook.ads.channel.group.carousal') }}",
                'Facebook · P Video':    "{{ route('facebook.ads.channel.parent.video') }}",
                'Facebook · P Carousal': "{{ route('facebook.ads.channel.parent.carousal') }}",
                'Instagram':       "{{ route('instagram.ads.channel') }}",
                'Instagram · G Video':    "{{ route('instagram.ads.channel.group.video') }}",
                'Instagram · G Carousal': "{{ route('instagram.ads.channel.group.carousal') }}",
                'Instagram · P Video':    "{{ route('instagram.ads.channel.parent.video') }}",
                'Instagram · P Carousal': "{{ route('instagram.ads.channel.parent.carousal') }}",
            };

            function channelFormatter(cell) {
                const name = cell.getValue() || '';
                const url  = channelLinks[name];
                const row  = cell.getRow().getData() || {};
                // Sub-rows ("Facebook · G Video", etc.) indent + lighten so they read
                // visually as children of the parent channel above them in the table.
                const indent = row.is_sub_row ? 'padding-left:18px;color:#475569;' : '';
                const weight = row.is_sub_row ? 'font-weight:500;' : 'font-weight:600;';
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:inherit;text-decoration:underline;' + weight + indent + '">' + name + '</a>';
                }
                return '<span style="' + weight + indent + '">' + name + '</span>';
            }

            const dataUrl = "{{ route('shopify.ads.master.data') }}";

            const table = new Tabulator('#shopify-ads-master-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    // S Sales — independent of the table filters; comes
                    // straight from the /shopify Net Sales figure. Stored so
                    // the TCOS badge (Spend / S Sales) can use it.
                    samSSales = Number(response.shopify_net_sales || 0);
                    const ssEl = document.getElementById('sam-badge-ssales');
                    if (ssEl) ssEl.textContent = '$' + Math.round(samSSales).toLocaleString();
                    updateBadges(rows);
                    return rows;
                },
                layout: 'fitColumns',
                headerSort: true,
                initialSort: [],
                columns: [
                    { title: 'Channel', field: 'channel', minWidth: 150, headerSort: true, formatter: channelFormatter },
                    { title: 'SPEND',   field: 'spend',   hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'CLICKS',  field: 'clicks',  hozAlign: 'center', formatter: intFormatter,        headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'SOLD',    field: 'sold',    hozAlign: 'center', formatter: intFormatter,        headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'ADS SALES', field: 'sales', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'CVR',     field: 'cvr',     hozAlign: 'center', formatter: percentFormatter,    headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'ACOS',    field: 'acos',    hozAlign: 'center', formatter: percentFormatter,    headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'TCOS',    field: 'tcos',    hozAlign: 'center', formatter: percentFormatter,    headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                ],
            });

            // Clicking a metric cell opens the trend chart lensed to that
            // row's channel + the clicked metric.
            const SAM_METRIC_LABELS = { spend: 'Spend', clicks: 'Clicks', sold: 'Sold', sales: 'Ads Sales', cvr: 'CVR', acos: 'ACOS', tcos: 'Tcos', ssales: 'S Sales' };
            function samCellChart(e, cell) {
                const metric  = cell.getField();
                const channel = (cell.getRow().getData() || {}).channel || '__total__';
                openSamChart(metric, SAM_METRIC_LABELS[metric] || metric.toUpperCase(), channel);
            }

            // Re-compute badges from whatever rows are visible after a filter.
            table.on('dataFiltered', function (filters, rows) {
                updateBadges(rows.map(function (r) { return r.getData(); }));
            });

            // Search: filter Channel column on input
            document.getElementById('sam-search').addEventListener('input', function () {
                const q = this.value.trim();
                if (q === '') {
                    table.clearFilter();
                } else {
                    table.setFilter('channel', 'like', q);
                }
            });

            document.getElementById('sam-refresh').addEventListener('click', function () {
                document.getElementById('sam-search').value = '';
                table.clearFilter();
                table.setData(dataUrl);
            });

            // ── Badge trend chart (same look as /facebook-all-ads-sheet) ──
            // Clicking any badge opens the modal and pulls the daily series
            // from /shopify-ads-master/history; the chart draws a rolling
            // line with coloured dots, a dashed median, value labels and a
            // HIGHEST / MEDIAN / LOWEST side panel.
            const historyUrl = "{{ route('shopify.ads.master.history') }}";
            let samTrendChart  = null;
            let samTrendCache  = null;   // last /history payload
            let samTrendMetric = 'spend';
            let samTrendLabel  = 'Spend';

            // Format a number per metric so chart labels and the side panel
            // match the badges (currency for $, % for ratios, plain ints).
            function fmtSamValue(metric, v) {
                if (v === null || v === undefined || isNaN(v)) return '—';
                if (metric === 'spend' || metric === 'sales' || metric === 'ssales') {
                    return '$' + Math.round(v).toLocaleString('en-US');
                }
                if (metric === 'acos' || metric === 'cvr' || metric === 'tcos') {
                    return Number(v).toFixed(1) + '%';
                }
                return Math.round(v).toLocaleString('en-US');
            }

            function samSeriesFor(payload, channel, metric) {
                if (!payload) return [];
                if (channel === '__total__') return (payload.metrics || {})[metric] || [];
                const ch = (payload.channels || {})[channel];
                return ch ? (ch[metric] || []) : [];
            }

            // Badge clicks + Trends button open the chart.
            document.querySelectorAll('.sam-badge-link').forEach(el => {
                el.addEventListener('click', function () {
                    openSamChart(this.dataset.metric, this.dataset.label || this.dataset.metric.toUpperCase());
                });
            });
            document.getElementById('sam-trends')?.addEventListener('click', function () {
                openSamChart('spend', 'Spend');
            });

            document.getElementById('sam-trend-channel')?.addEventListener('change', function () {
                samSetTrendTitle();
                renderSamChart();
            });
            document.getElementById('sam-trend-days')?.addEventListener('change', loadSamHistory);

            function openSamChart(metric, label, channel) {
                samTrendMetric = metric;
                samTrendLabel  = label;
                // Point the channel selector at the requested channel (or the
                // rolled-up total when opened from a badge / the Trends button).
                const chSel = document.getElementById('sam-trend-channel');
                if (chSel) {
                    const wanted = channel || '__total__';
                    chSel.value = [...chSel.options].some(o => o.value === wanted) ? wanted : '__total__';
                }
                samSetTrendTitle();

                const modalEl = document.getElementById('samTrendsModal');
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                loadSamHistory();
            }

            function samSetTrendTitle() {
                const days = parseInt(document.getElementById('sam-trend-days').value || '30', 10);
                const chSel = document.getElementById('sam-trend-channel');
                const ch = chSel ? chSel.value : '__total__';
                const chTxt = (ch && ch !== '__total__') ? ` · ${ch}` : '';
                const titleEl = document.getElementById('sam-trend-title');
                if (titleEl) titleEl.textContent = `${samTrendLabel}${chTxt} (Rolling L${days})`;
            }

            function loadSamHistory() {
                const days = parseInt(document.getElementById('sam-trend-days').value || '30', 10);
                samSetTrendTitle();
                fetch(historyUrl + '?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(payload => { samTrendCache = payload; renderSamChart(); })
                    .catch(() => { samTrendCache = { labels: [], metrics: {}, channels: {} }; renderSamChart(); });
            }

            function renderSamChart() {
                const canvas  = document.getElementById('sam-trend-canvas');
                const emptyEl = document.getElementById('sam-trend-empty');
                if (!canvas) return;
                const metric  = samTrendMetric;
                const channel = document.getElementById('sam-trend-channel').value;
                const labels  = (samTrendCache && samTrendCache.labels) || [];
                const values  = samSeriesFor(samTrendCache, channel, metric).map(v => Number(v) || 0);

                // Tear down previous chart + reset the side panel first.
                if (samTrendChart) { samTrendChart.destroy(); samTrendChart = null; }
                ['sam-trend-highest', 'sam-trend-median', 'sam-trend-lowest'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                if (!labels.length) {
                    canvas.style.display = 'none';
                    emptyEl?.classList.remove('d-none');
                    return;
                }
                canvas.style.display = '';
                emptyEl?.classList.add('d-none');

                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = (dataMax - dataMin) || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                // Side panel — same colour rules as the Facebook page.
                const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                const setStat = (id, v) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = fmtSamValue(metric, v);
                    el.style.color = (v === 0) ? refGreen : (v > 0 ? refRed : refGray);
                };
                setStat('sam-trend-highest', dataMax);
                setStat('sam-trend-median',  median);
                setStat('sam-trend-lowest',  dataMin);

                // Per-day dot colours: green = improved vs prev day, red =
                // worse, grey = flat. Inverted for "lower is better" (ACOS).
                const isInverted = (metric === 'acos' || metric === 'tcos');
                const dotColors = values.map((v, i) => {
                    if (i === 0) return refGray;
                    if (isInverted) {
                        return v < values[i - 1] ? '#28a745'
                             : v > values[i - 1] ? '#dc3545'
                             : refGray;
                    }
                    return v > values[i - 1] ? '#28a745'
                         : v < values[i - 1] ? '#dc3545'
                         : refGray;
                });

                const medianLinePlugin = {
                    id: 'medianLine',
                    afterDraw(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const ctx = chart.ctx;
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
                const labelColors = values.map(v => v === 0 ? refGreen : (v > 0 ? refRed : refGray));
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDatasetsDraw(chart) {
                        const meta = chart.getDatasetMeta(0);
                        const ctx  = chart.ctx;
                        ctx.save();
                        ctx.font         = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign    = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach((point, i) => {
                            const offY = (i % 2 === 0) ? -10 : -20;
                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(fmtSamValue(metric, values[i]), point.x, point.y + offY);
                        });
                        ctx.restore();
                    }
                };

                samTrendChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: samTrendLabel,
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor:     '#adb5bd',
                            borderWidth:     1.5,
                            fill:            true,
                            tension:         0.3,
                            pointRadius:     3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor:     dotColors,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 24, right: 16, bottom: 12, left: 16 } },
                        plugins: {
                            legend:  { display: false },
                            tooltip: { callbacks: { label: (ctx) => fmtSamValue(metric, ctx.parsed.y) } },
                        },
                        scales: {
                            y: { min: yMin, max: yMax, ticks: { callback: (v) => fmtSamValue(metric, v) } },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } },
                        },
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                });
            }

            // Resize correctly once the modal is fully shown.
            document.getElementById('samTrendsModal')?.addEventListener('shown.bs.modal', function () {
                if (samTrendChart) samTrendChart.resize();
            });
        });
    </script>
@endsection
