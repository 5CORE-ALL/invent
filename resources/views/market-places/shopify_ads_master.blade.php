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
        .sam-stat-badge--active { background: #059669; }

        /* Today / Yesterday / L30 / Projected panels — same look as /all-marketplace-master. */
        #sam-panels {
            display: grid;
            /* L30 has 4 badges per row, so it gets a wider column than the other panels. */
            grid-template-columns: 1fr 1fr 1.7fr 1fr;
            gap: 0.5rem;
            /* Panels shown at 0.7x size (badges + text); layout still spans the full width. */
            zoom: 0.7;
        }
        @media (max-width: 1200px) { #sam-panels { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px)  { #sam-panels { grid-template-columns: minmax(0, 1fr); } }
        #sam-panels .sam-panel {
            min-width: 0;
            border-radius: 12px;
            padding: 0.55rem 0.6rem 0.65rem;
            background: #fef9c3;
            border: 1px solid #facc15;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        #sam-panels .sam-panel-title {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1;
            margin: 0 0 0.35rem;
            color: #854d0e;
        }
        #sam-panels .sam-panel-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.3rem;
        }
        #sam-panels .sam-panel--l30 .sam-panel-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        #sam-panels .sam-pbadge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            min-height: 31px;
            padding: 0.1rem 0.25rem;
            border-radius: 0.375rem;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            text-align: center;
            color: #fff;
        }
        #sam-panels .sam-pbadge.sam-dark-text { color: #111; }
        #sam-panels .sam-pbadge.sam-up   { background: #198754 !important; color: #fff; }
        #sam-panels .sam-pbadge.sam-down { background: #dc3545 !important; color: #fff; }
        #sam-panels .sam-pbadge .sam-vs { font-size: 0.7rem; margin-left: 0.2rem; }

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
12
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

       
        #shopify-ads-master-wrap .tabulator .tabulator-data-tree-control {
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



        #shopify-ads-master-wrap .tabulator .tabulator-data-tree-control:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-data-tree-control-expand::after {
            content: '+';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-data-tree-control-collapse::after {
            content: '−';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #shopify-ads-master-wrap .tabulator-row.sam-child-row .tabulator-cell {
            background: #f8fafc;
        }

        #shopify-ads-master-wrap .tabulator-row.sam-child-row:hover .tabulator-cell {
            background: #f1f5f9;
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


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Today / Yesterday / L30 / Projected for Shopify — same figures as the
                         Shopify B2C row on /all-marketplace-master (filled by script below). --}}
                    <div id="sam-panels" class="mb-2"></div>

                    {{-- Badge strip + Search + Refresh --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 flex-grow-1 py-1" style="min-width:0;">
                            <span class="sam-stat-badge sam-stat-badge--active" title="Active (running / enabled) campaigns across all channels">ACTIVE: <span id="sam-badge-active">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--spend sam-badge-link" data-metric="spend" data-label="Spend" title="Click for trend">SPEND: <span id="sam-badge-spend">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--clicks sam-badge-link" data-metric="clicks" data-label="Clicks" title="Click for trend">CLICKS: <span id="sam-badge-clicks">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sold sam-badge-link" data-metric="sold" data-label="Sold" title="Click for trend">SOLD: <span id="sam-badge-sold">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sales sam-badge-link" data-metric="sales" data-label="Ads Sales" title="Click for trend">ADS SALES: <span id="sam-badge-sales">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--cvr sam-badge-link" data-metric="cvr" data-label="CVR" title="Click for trend">CVR: <span id="sam-badge-cvr">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--acos sam-badge-link" data-metric="acos" data-label="ACOS" title="Click for trend">ACOS: <span id="sam-badge-acos">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--tcos sam-badge-link" data-metric="tcos" data-label="Tcos" title="Click for trend">TCOS: <span id="sam-badge-tcos">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--ssales sam-badge-link" data-metric="ssales" data-label="Shopify Sales" title="Net Sales (gross − discounts) from /shopify — click for trend">SHOPIFY SALES: <span id="sam-badge-ssales">$0</span></span>
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
                            <option value="Youtube ads">Youtube ads</option>
                            <option value="TikTok Video Ads">TikTok Video Ads</option>
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

            // Metrics where a HIGHER value is worse: an up move reads red, a
            // down move green — the opposite of clicks/sold/spend/etc.
            // Spend matches the trend chart (up = green); only ACOS/TCOS invert.
            const SAM_INVERTED_METRICS = { acos: true, tcos: true };

            // Day-over-day trend dot for a metric cell. Green = improvement,
            // red = decline, grey = no change or no prior day. Direction
            // ('up'|'down'|'flat') is supplied per-row by the backend in `row.trend`.
            function samTrendDot(cell) {
                const field = cell.getField();
                const data  = cell.getRow().getData() || {};
                const dir   = (data.trend || {})[field];

                let color;
                if (dir === 'same') {
                    // Data is updated and the value is unchanged vs previous day.
                    color = '#facc15';
                } else if (!dir || dir === 'flat') {
                    color = '#9ca3af';
                } else {
                    const inverted = !!SAM_INVERTED_METRICS[field];
                    const improved = inverted ? (dir === 'down') : (dir === 'up');
                    color = improved ? '#28a745' : '#dc3545';
                }
                const title = dir ? 'vs previous day' : 'No previous day yet';
                return '<span title="' + title + '" style="display:inline-block;width:8px;height:8px;'
                    + 'border-radius:50%;background:' + color + ';margin-left:5px;vertical-align:middle;'
                    + 'border:1px solid rgba(15,23,42,0.35);box-sizing:border-box;"></span>';
            }

            function wholeMoneyFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return '$' + Math.round(value).toLocaleString() + samTrendDot(cell);
            }

            function intFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return Math.round(value).toLocaleString() + samTrendDot(cell);
            }

            function percentFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return value.toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1,
                }) + '%' + samTrendDot(cell);
            }

            function updateBadges(rows) {
                // Skip "sub-row" channels (Facebook · G Video, etc.) when summing — they're
                // typed slices of the parent Facebook / Instagram rows, not separate channels,
                // so including them would double-count the rolled-up totals.
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
                // TCOS = Spend / S Sales (store net sales).
                const tcos = samSSales > 0 ? (spend / samSSales) * 100 : (spend > 0 ? 100 : 0);

                const samActiveEl = document.getElementById('sam-badge-active');
                if (samActiveEl) samActiveEl.textContent = Math.round(active).toLocaleString();
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
                'Youtube ads':     "{{ Route::has('google.youtube.ads.campaigns') ? route('google.youtube.ads.campaigns') : url('/google/shopping/youtube-ads') }}",
                'TikTok Video Ads': "{{ route('tiktok.video.ads') }}",
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
                // Child rows ("Facebook · G Video", etc.) are nested under their parent
                // via the Tabulator data tree, which supplies the indent. Lighten them
                // so they read visually as children of the parent channel above them.
                const isChild = !!cell.getRow().getTreeParent();
                const weight = isChild ? 'font-weight:500;' : 'font-weight:600;';
                const color = isChild ? 'color:#475569;' : '';
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:inherit;text-decoration:underline;' + weight + color + '">' + name + '</a>';
                }
                return '<span style="' + weight + color + '">' + name + '</span>';
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
                    if (ssEl) {
                        ssEl.textContent = '$' + Number(samSSales).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }
                    updateBadges(rows);
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
                        row.getElement().classList.add('sam-child-row');
                    }
                },
                columns: [
                    { title: 'Channel', field: 'channel', minWidth: 150, headerSort: true, formatter: channelFormatter },
                    { title: 'ACTIVE',  field: 'active',  hozAlign: 'center', formatter: intFormatter,        headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'SPEND',   field: 'spend',   hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'CLICKS',  field: 'clicks',  hozAlign: 'center', formatter: intFormatter,        headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'SOLD',    field: 'sold',    hozAlign: 'center', formatter: intFormatter,        headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'ADS SALES', field: 'sales', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'CVR',     field: 'cvr',     hozAlign: 'center', formatter: percentFormatter,    headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                    { title: 'ACOS',    field: 'acos',    hozAlign: 'center', formatter: percentFormatter,    headerSort: true, cssClass: 'sam-metric-cell', cellClick: samCellChart },
                ],
            });
 
            // Clicking a metric cell opens the trend chart lensed to that
            // row's channel + the clicked metric.
            const SAM_METRIC_LABELS = { spend: 'Spend', clicks: 'Clicks', sold: 'Sold', sales: 'Ads Sales', active: 'Active', cvr: 'CVR', acos: 'ACOS', tcos: 'Tcos', ssales: 'Shopify Sales' };
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
            let samTrendCacheDays = 0;
            let samHistoryReq  = 0;
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

            // Show/hide the trend modal. Uses Bootstrap's JS API when it's
            // available, otherwise falls back to toggling the classes manually
            // so the graph still opens even if `window.bootstrap` isn't ready
            // (the previous guard silently did nothing in that case).
            function samShowModal() {
                const modalEl = document.getElementById('samTrendsModal');
                if (!modalEl) return;
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                    modalEl.removeAttribute('aria-hidden');
                    modalEl.setAttribute('aria-modal', 'true');
                    document.body.classList.add('modal-open');
                    if (!document.getElementById('sam-modal-backdrop')) {
                        const bd = document.createElement('div');
                        bd.id = 'sam-modal-backdrop';
                        bd.className = 'modal-backdrop fade show';
                        document.body.appendChild(bd);
                        bd.addEventListener('click', samHideModal);
                    }
                }
                setTimeout(function () { if (samTrendChart) samTrendChart.resize(); }, 200);
            }

            function samHideModal() {
                const modalEl = document.getElementById('samTrendsModal');
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
                const bd = document.getElementById('sam-modal-backdrop');
                if (bd) bd.remove();
            }

            // Manual close buttons (work with or without the Bootstrap JS API).
            document.querySelectorAll('#samTrendsModal [data-bs-dismiss="modal"]').forEach(function (btn) {
                btn.addEventListener('click', samHideModal);
            });

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

                samShowModal();
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

            function samClearChart(message) {
                if (samTrendChart) { samTrendChart.destroy(); samTrendChart = null; }
                const canvas = document.getElementById('sam-trend-canvas');
                const emptyEl = document.getElementById('sam-trend-empty');
                if (canvas) canvas.style.display = 'none';
                if (emptyEl) {
                    emptyEl.textContent = message || 'Loading…';
                    emptyEl.classList.remove('d-none');
                }
                ['sam-trend-highest', 'sam-trend-median', 'sam-trend-lowest'].forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = '—';
                    el.style.color = '';
                });
            }

            function loadSamHistory() {
                const days = parseInt(document.getElementById('sam-trend-days').value || '30', 10);
                samSetTrendTitle();
                const req = ++samHistoryReq;
                // The history payload already has every metric. Draw the one
                // just opened immediately so the previous graph does not stay
                // on screen while the refresh request is in flight.
                if (samTrendCache && samTrendCacheDays === days) {
                    renderSamChart();
                } else {
                    samClearChart('Loading…');
                }
                fetch(historyUrl + '?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(payload => {
                        if (req !== samHistoryReq) return;
                        samTrendCache = payload;
                        samTrendCacheDays = days;
                        renderSamChart();
                    })
                    .catch(() => {
                        if (req !== samHistoryReq) return;
                        samTrendCache = { labels: [], metrics: {}, channels: {} };
                        samTrendCacheDays = days;
                        renderSamChart();
                    });
            }

            function renderSamChart() {
                const canvas  = document.getElementById('sam-trend-canvas');
                const emptyEl = document.getElementById('sam-trend-empty');
                if (!canvas) return;
                const metric  = samTrendMetric;
                const channel = document.getElementById('sam-trend-channel').value;
                const days = parseInt(document.getElementById('sam-trend-days').value || '30', 10);
                let labels  = (samTrendCache && samTrendCache.labels) || [];
                // Each point is the value stored for that day. Missing days
                // stay empty instead of repeating the previous number.
                const rawSeries = samSeriesFor(samTrendCache, channel, metric);
                let values = labels.map((_, i) => {
                    const v = rawSeries[i];
                    if (v === null || v === undefined || v === '') return null;
                    const n = Number(v);
                    return Number.isFinite(n) ? n : null;
                });
                // Google, Facebook, and Instagram stop on the last completed California day.
                // Other channels keep today so the last point matches the row.
                const completedDayChannel = channel === 'Google Shopping' || channel === 'Google SERP' || channel === 'Youtube ads' || channel === 'Facebook' || channel === 'Instagram' || channel.indexOf('Facebook · ') === 0 || channel.indexOf('Instagram · ') === 0;
                if (completedDayChannel) {
                    let end = values.length;
                    while (end > 0 && values[end - 1] === null) end--;
                    const start = Math.max(0, end - days);
                    labels = labels.slice(start, end);
                    values = values.slice(start, end);
                } else if (labels.length > days) {
                    labels = labels.slice(labels.length - days);
                    values = values.slice(values.length - days);
                }
                const numericValues = values.filter((v) => v !== null);

                // Tear down previous chart + reset the side panel first.
                if (samTrendChart) { samTrendChart.destroy(); samTrendChart = null; }
                ['sam-trend-highest', 'sam-trend-median', 'sam-trend-lowest'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                if (!labels.length || !numericValues.length) {
                    canvas.style.display = 'none';
                    if (emptyEl) {
                        emptyEl.textContent = 'No history available for this metric in the selected window.';
                        emptyEl.classList.remove('d-none');
                    }
                    return;
                }
                if (typeof Chart === 'undefined') {
                    canvas.style.display = 'none';
                    if (emptyEl) { emptyEl.textContent = 'Chart library failed to load. Check your connection and refresh.'; emptyEl.classList.remove('d-none'); }
                    return;
                }

                canvas.style.display = '';
                emptyEl?.classList.add('d-none');

                // Scale + side stats from recorded snapshot days. Held days
                // repeat the last value and must not shift the median.
                const dataMin = Math.min(...numericValues);
                const dataMax = Math.max(...numericValues);
                const sorted  = [...numericValues].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = (dataMax - dataMin) || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                const setStat = (id, v, color) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = fmtSamValue(metric, v);
                    el.style.color = color;
                };
                setStat('sam-trend-highest', dataMax, refRed);
                setStat('sam-trend-median',  median,  refGray);
                setStat('sam-trend-lowest',  dataMin, refGreen);

                // Dot + label share one colour: green = improved vs the
                // previous day, red = worse, grey = flat (including a day
                // that only repeats the last snapshot). ACOS / TCOS invert.
                // Compare the REAL stored values, not the rounded label text:
                // a real change smaller than the label precision (e.g. $2,271.40
                // -> $2,271.33, both shown as "$2,271") is still a change, and
                // the delta label already reports it, so the dot must not stay grey.
                const isInverted = (metric === 'acos' || metric === 'tcos');
                const EPS = 1e-9;
                const prevValue = (i) => {
                    for (let j = i - 1; j >= 0; j--) {
                        if (values[j] !== null) return values[j];
                    }
                    return null;
                };
                const dotColors = values.map((v, i) => {
                    if (v === null) return refGray;
                    const prev = prevValue(i);
                    if (prev === null || Math.abs(v - prev) < EPS) return refGray;
                    const improved = isInverted ? v < prev : v > prev;
                    return improved ? '#28a745' : '#dc3545';
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
                // Slanted value labels (−45°), same colour as that day's dot.
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDatasetsDraw(chart) {
                        const meta = chart.getDatasetMeta(0);
                        const ctx  = chart.ctx;
                        meta.data.forEach((point, i) => {
                            if (values[i] === null || !Number.isFinite(point?.x) || !Number.isFinite(point?.y)) return;
                            const offY = (i % 2 === 0) ? -10 : -20;
                            ctx.save();
                            ctx.translate(point.x, point.y + offY);
                            ctx.rotate(-Math.PI / 4);
                            ctx.textAlign    = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillStyle    = dotColors[i];
                            ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                            ctx.fillText(fmtSamValue(metric, values[i]), 0, 0);
                            ctx.restore();
                        });
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
                            spanGaps:        true,
                            pointRadius:      (ctx) => values[ctx.dataIndex] === null ? 0 : 4,
                            pointHoverRadius: (ctx) => values[ctx.dataIndex] === null ? 0 : 6,
                            pointBackgroundColor: dotColors,
                            pointBorderColor:     '#ffffff',
                            pointBorderWidth:     2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 36, right: 16, bottom: 12, left: 16 } },
                        plugins: {
                            legend:  { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => fmtSamValue(metric, ctx.parsed.y),
                                },
                            },
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
    <script>
        /* Today / Yesterday / L30 / Projected panels for Shopify.
           Reads the Shopify B2C row of /all-marketplace-master (saved Active Channel
           table) and applies the same formulas that page uses for its badges, so the
           numbers match. */
        document.addEventListener('DOMContentLoaded', function () {
            const host = document.getElementById('sam-panels');
            if (!host) return;
            const summaryUrl = "{{ route('shopify.ads.master.channel.summary') }}";

            const PANELS = [
                { key: 'today', title: 'Today', badges: [
                    ['sales', 'Sales', '#fd7e14'], ['orders', 'Orders', '#0dcaf0', true],
                    ['npft', 'Nprft%', '#17a2b8'], ['groi', 'Groi%', '#dc3545'],
                    ['nroi', 'NROI%', '#0d6efd'], ['ads', 'Ads%', '#d63384'],
                    ['views', 'Views', '#0dcaf0', true], ['cvr', 'CVR', '#6f42c1'],
                ] },
                { key: 'yest', title: 'Yesterday', badges: [
                    ['sales', 'Sales', '#17a2b8'], ['orders', 'Orders', '#0dcaf0', true],
                    ['npft', 'Nprft%', '#17a2b8'], ['groi', 'Groi%', '#dc3545'],
                    ['nroi', 'NROI%', '#0d6efd'], ['ads', 'Ads%', '#d63384'],
                    ['views', 'Views', '#0dcaf0', true], ['cvr', 'CVR', '#6f42c1'],
                ] },
                { key: 'l30', title: 'L30', badges: [
                    ['sales', 'Sales', '#198754', true], ['orders', 'Orders', '#0dcaf0', true],
                    ['gpft', 'GPFT', '#ffc107', true], ['groi', 'G ROI', '#dc3545'],
                    ['spend', 'Spend', '#6c757d'], ['ads', 'Ads', '#d63384'],
                    ['views', 'Views', '#0dcaf0', true], ['cvr', 'CVR', '#0d6efd'],
                    ['npftAmt', 'NPFT', '#ffc107', true], ['npft', 'NPFT', '#ffc107', true],
                    ['nroi', 'NROI', '#0d6efd'],
                ] },
                { key: 'proj', title: 'Projected', badges: [
                    ['sales', 'Sales', '#0d6efd'], ['orders', 'Orders', '#0dcaf0', true],
                    ['groi', 'GROI%', '#0d6efd'], ['npft', 'Npft%', '#0d6efd'],
                    ['nroi', 'NROI%', '#0d6efd'], ['ads', 'Ads%', '#d63384'],
                    ['views', 'Views', '#0d6efd'], ['cvr', 'CVR', '#6f42c1'],
                ] },
            ];

            host.innerHTML = PANELS.map(function (p) {
                return '<div class="sam-panel sam-panel--' + p.key + '"><div class="sam-panel-title">' + p.title + '</div>'
                    + '<div class="sam-panel-row">'
                    + p.badges.map(function (b) {
                        return '<span class="sam-pbadge' + (b[3] ? ' sam-dark-text' : '') + '" id="sam-p-' + p.key + '-' + b[0]
                            + '" style="background:' + b[2] + ';">' + b[1] + ': <span class="sam-pval">—</span></span>';
                    }).join('')
                    + '</div></div>';
            }).join('');

            function num(v) {
                if (v === null || v === undefined || v === '' || v === 'N/A') return 0;
                if (typeof v === 'number') return v;
                const n = parseFloat(String(v).replace(/[^0-9.-]/g, ''));
                return isFinite(n) ? n : 0;
            }
            const has = function (v) { return v !== undefined && v !== null && v !== ''; };
            const money = function (v) { return '$' + Math.round(v).toLocaleString('en-US'); };
            const compact = function (v) {
                const r = Math.round(v);
                if (Math.abs(r) >= 1000000) return (r / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                if (Math.abs(r) >= 1000) return Math.round(r / 1000) + 'K';
                return String(r);
            };
            const pct = function (v, d) {
                return (v === null || v === undefined || !isFinite(v)) ? '—' : (Math.round(v * Math.pow(10, d || 1)) / Math.pow(10, d || 1)).toFixed(d || 1) + '%';
            };
            const int = function (v) { return Math.round(v).toLocaleString('en-US'); };

            // out[panel][key] = { text, cur, base, invert }
            function compute(row, y) {
                const l30 = num(row['L30 Sales']);
                const l30Orders = num(row['L30 Orders']);
                const gp = num(row['Gprofit%']);
                const cogs = num(row['cogs']);
                const views = num(row['Total Views']);
                const qty = num(row['Qty']);
                const spend = num(row['Total Ad Spend']);
                const hasCvr = has(row['CVR']);
                const listingCvr = hasCvr ? num(row['CVR']) : (views > 0 ? (qty / views) * 100 : null);

                // ── L30 ──
                const l30Pft = (gp / 100) * l30;
                const l30Ads = l30 > 0 ? (spend / l30) * 100 : 0;
                const l30Groi = cogs > 0 ? (l30Pft / cogs) * 100 : 0;
                const l30Npft = gp - l30Ads;
                const l30Nroi = num(row['N ROI']);

                // ── Today ──
                const tSales = num(row['Today Sales']);
                let t = { sales: 0, gross: 0, net: 0, cogs: 0, spend: 0, views: 0, orders: 0 };
                if (tSales > 0) {
                    const npftCol = num(row['N PFT']);
                    const yMeasuredRate = (row['Y Ads Measured'] === true)
                        ? (function () {
                            const ys = num(row['Y Sales']) > 0 ? num(row['Y Sales']) : num(row['Y Day Sales']);
                            return ys > 0 ? (num(row['Y Ad Spend']) / ys) * 100 : null;
                        })()
                        : null;
                    const adsRate = yMeasuredRate != null ? yMeasuredRate : num(has(row['Ads%']) ? row['Ads%'] : row['TACOS']);
                    const tCogs = (l30 > 0 && cogs > 0) ? cogs * (tSales / l30) : tSales * Math.max(0, 1 - gp / 100);
                    t = {
                        sales: tSales,
                        gross: (tSales * gp) / 100,
                        net: (tSales * npftCol) / 100,
                        cogs: tCogs,
                        spend: (tSales * adsRate) / 100,
                        views: (l30 > 0 && views > 0) ? views * (tSales / l30) : 0,
                        orders: (l30 > 0 && l30Orders > 0) ? l30Orders * (tSales / l30) : 0,
                    };
                }
                let tCvr = null;
                if (tSales > 0 && l30 > 0) {
                    const ratio = tSales / l30;
                    const tv = views * ratio;
                    let tu;
                    if (l30Orders > 0) tu = l30Orders * ratio;
                    else if (hasCvr) tu = (num(row['CVR']) / 100) * tv;
                    else tu = qty * ratio;
                    tCvr = tv > 0 ? (tu / tv) * 100 : null;
                }

                // ── Yesterday ──
                const ySales = num(row['Y Sales']);
                const yHasMeasured = has(row['Y GPFT $']) && has(row['Y COGS']);
                const yM = yHasMeasured
                    ? { pft: num(row['Y GPFT $']), cogs: num(row['Y COGS']), spend: num(row['Y Spend']), sales: num(row['Y Day Sales']) }
                    : null;
                const yGross = yM ? yM.pft : (ySales * gp) / 100;
                const yNet = yM ? yM.pft - yM.spend : (ySales * num(row['N PFT'])) / 100;
                let yCogs;
                if (yM) yCogs = yM.cogs;
                else if (!ySales) yCogs = 0;
                else if (l30 > 0 && cogs > 0) yCogs = cogs * (ySales / l30);
                else yCogs = ySales * Math.max(0, 1 - gp / 100);
                const yPctSales = yM ? yM.sales : ySales;
                const yDaySales = ySales > 0 ? ySales : num(row['Y Day Sales']);
                let yAdsSpend = 0, yAdsSales = 0;
                if (yDaySales > 0) {
                    yAdsSales = yDaySales;
                    yAdsSpend = (row['Y Ads Measured'] === true && num(row['Y Day Sales']) > 0)
                        ? num(row['Y Ad Spend'])
                        : (yDaySales * num(has(row['Ads%']) ? row['Ads%'] : row['TACOS'])) / 100;
                }
                const yNpft = yPctSales > 0 ? (yNet / yPctSales) * 100 : 0;
                const yGroi = yCogs > 0 ? (yGross / yCogs) * 100 : 0;
                const yNroi = yCogs > 0 ? (yNet / yCogs) * 100 : 0;
                const yAds = yAdsSales > 0 ? (yAdsSpend / yAdsSales) * 100 : null;
                const yOrders = y ? num(y.orders) : null;
                const yViews = (y && has(y.views)) ? num(y.views) : null;
                const yApprox = !!(y && (y.views_estimate || y.views_as_of));
                let yCvr = null;
                if (y && yViews > 0 && !yApprox) yCvr = (num(y.qty) / yViews) * 100;

                // ── Projected ──
                const pSales = (num(row['L7 Sales']) / 7) * 30;
                const pGross = (gp / 100) * pSales;
                const pCogs = pSales > 0
                    ? ((l30 > 0 && cogs > 0) ? cogs * (pSales / l30) : pSales * Math.max(0, 1 - gp / 100))
                    : 0;
                const pRatio = (l30 > 0 && pSales > 0) ? pSales / l30 : 0;
                const pViews = views > 0 ? (pRatio > 0 ? views * pRatio : views) : 0;
                const pOrders = (l30Orders > 0 && pRatio > 0) ? l30Orders * pRatio : 0;
                let pCvr = null;
                if (pSales > 0 && l30 > 0 && views > 0) {
                    const pv = views * pRatio;
                    let pu;
                    if (l30Orders > 0) pu = l30Orders * pRatio;
                    else if (listingCvr != null) pu = (pv * listingCvr) / 100;
                    else pu = null;
                    pCvr = (pu != null && pv > 0) ? (pu / pv) * 100 : null;
                }
                const pNpft = pSales > 0 ? ((pGross - spend) / pSales) * 100 : 0;
                const pGroi = pCogs > 0 ? (pGross / pCogs) * 100 : 0;
                const pNroi = pCogs > 0 ? ((pGross - spend) / pCogs) * 100 : 0;
                const pAds = pSales > 0 ? (spend / pSales) * 100 : 0;
                const vsL30 = l30 > 0 ? ((pSales - l30) / l30) * 100 : null;

                const base30Orders = l30Orders / 30;
                const base30Views = views / 30;

                return {
                    today: {
                        sales:  { text: money(t.sales), cur: t.sales, base: ySales },
                        orders: { text: int(t.orders), cur: t.orders, base: base30Orders },
                        npft:   { text: pct(tSales > 0 ? (t.net / t.sales) * 100 : 0), cur: tSales > 0 ? (t.net / t.sales) * 100 : null, base: yNpft },
                        groi:   { text: pct(t.cogs > 0 ? (t.gross / t.cogs) * 100 : 0), cur: t.cogs > 0 ? (t.gross / t.cogs) * 100 : null, base: yGroi },
                        nroi:   { text: pct(t.cogs > 0 ? (t.net / t.cogs) * 100 : 0), cur: t.cogs > 0 ? (t.net / t.cogs) * 100 : null, base: yNroi },
                        ads:    { text: pct(tSales > 0 ? (t.spend / t.sales) * 100 : 0), cur: tSales > 0 ? (t.spend / t.sales) * 100 : null, base: yAds, invert: true },
                        views:  { text: compact(t.views), cur: t.views, base: base30Views },
                        cvr:    { text: tCvr === null ? '-' : pct(tCvr, 2), cur: tCvr, base: yCvr },
                    },
                    yest: {
                        sales:  { text: ySales > 0 ? money(ySales) : 'NYS', cur: ySales, base: l30 / 30 },
                        orders: { text: yOrders === null ? '-' : int(yOrders), cur: yOrders, base: base30Orders },
                        npft:   { text: ySales > 0 ? pct(yNpft) : 'NYS', cur: ySales > 0 ? yNpft : null, base: l30Npft },
                        groi:   { text: ySales > 0 ? pct(yGroi) : 'NYS', cur: ySales > 0 ? yGroi : null, base: l30Groi },
                        nroi:   { text: ySales > 0 ? pct(yNroi) : 'NYS', cur: ySales > 0 ? yNroi : null, base: l30Nroi },
                        ads:    { text: yAds === null ? 'NYS' : pct(yAds), cur: yAds, base: l30Ads, invert: true },
                        views:  { text: yViews === null ? '-' : (compact(yViews) + (yApprox ? '~' : '')), cur: yViews, base: base30Views },
                        cvr:    { text: yCvr === null ? '-' : pct(yCvr, 2), cur: yCvr, base: listingCvr },
                    },
                    l30: {
                        sales:  { text: compact(l30) },
                        orders: { text: int(l30Orders) },
                        gpft:   { text: pct(gp) },
                        groi:   { text: pct(l30Groi) },
                        spend:  { text: compact(spend) },
                        ads:    { text: pct(l30Ads) },
                        views:  { text: compact(views) },
                        cvr:    { text: listingCvr === null ? '-' : pct(listingCvr, 2) },
                        npftAmt:{ text: compact(l30Pft - spend) },
                        npft:   { text: pct(l30Npft) },
                        nroi:   { text: pct(l30Nroi) },
                    },
                    proj: {
                        sales:  { text: compact(pSales), vs: vsL30, cur: pSales, base: l30 },
                        orders: { text: int(pOrders), cur: pOrders, base: l30Orders },
                        groi:   { text: pct(pGroi), cur: pGroi, base: l30Groi },
                        npft:   { text: pct(pNpft), cur: pNpft, base: l30Npft },
                        nroi:   { text: pct(pNroi), cur: pNroi, base: l30Nroi },
                        ads:    { text: pct(pAds), cur: pAds, base: l30Ads, invert: true },
                        views:  { text: compact(pViews), cur: pViews, base: views },
                        cvr:    { text: pCvr === null ? '-' : pct(pCvr, 2), cur: pCvr, base: listingCvr },
                    },
                };
            }

            function paint(out) {
                PANELS.forEach(function (p) {
                    p.badges.forEach(function (b) {
                        const el = document.getElementById('sam-p-' + p.key + '-' + b[0]);
                        const d = out && out[p.key] && out[p.key][b[0]];
                        if (!el || !d) return;
                        el.querySelector('.sam-pval').textContent = d.text;
                        const old = el.querySelector('.sam-vs');
                        if (old) old.remove();
                        if (d.vs != null && isFinite(d.vs) && Math.abs(d.vs) >= 0.1) {
                            const s = document.createElement('span');
                            s.className = 'sam-vs';
                            s.textContent = (d.vs > 0 ? '+' : '−') + Math.abs(d.vs).toFixed(0) + '%';
                            el.appendChild(s);
                        }
                        el.classList.remove('sam-up', 'sam-down');
                        if (d.cur != null && d.base != null && isFinite(d.cur) && isFinite(d.base) && Math.abs(d.cur - d.base) > 1e-9) {
                            const better = d.invert ? d.cur < d.base : d.cur > d.base;
                            el.classList.add(better ? 'sam-up' : 'sam-down');
                        }
                    });
                });
            }

            function load() {
                fetch(summaryUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res || !res.row) {
                            host.title = 'Shopify row not found in Active Channel data (run channel:calculate-data).';
                            return;
                        }
                        host.title = '';
                        paint(compute(res.row, res.yesterday || null));
                    })
                    .catch(function () { host.title = 'Could not load Shopify Active Channel summary.'; });
            }

            load();
            document.getElementById('sam-refresh')?.addEventListener('click', load);
        });
    </script>
@endsection
