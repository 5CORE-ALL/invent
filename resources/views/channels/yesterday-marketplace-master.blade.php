@extends('layouts.vertical', ['title' => 'Active Channel Yesterday', 'sidenav' => 'condensed'])

@php
    $yesterdayLabel = now('America/Los_Angeles')->subDays(2)->format('M j, Y');
@endphp

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
        }

        .channel-logo-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }

        .channel-logo-link {
            display: inline-block;
            line-height: 0;
            text-decoration: none;
        }

        .channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f1f3f5;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 12px;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        #marketplace-table.tabulator {
            overflow: visible;
        }

        #marketplace-table.tabulator .tabulator-header {
            position: sticky;
            top: var(--tz-topbar-height, 70px);
            z-index: 24;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }

        #marketplace-table.tabulator .tabulator-header .tabulator-frozen {
            z-index: 26;
        }

        #marketplace-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: black !important;
            overflow: visible;
            text-overflow: clip;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-row.tabulator-calcs {
            background: #f8f9fa !important;
            font-weight: bold !important;
            border-top: 2px solid #4361ee !important;
        }

        .tabulator-row.tabulator-calcs .tabulator-cell {
            background: #f8f9fa !important;
            font-weight: bold !important;
            color: #333 !important;
        }

        .tabulator-row.tabulator-calcs-bottom {
            display: table-row !important;
            visibility: visible !important;
        }

        .tabulator .tabulator-footer .tabulator-calcs-holder .tabulator-row {
            display: table-row !important;
        }

        .tabulator .tabulator-footer .tabulator-calcs-holder {
            display: block !important;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.4rem;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 4px;
        }

        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 0 0 auto;
            min-width: max-content;
            font-size: 0.8125rem;
            padding: 0.4rem 0.55rem;
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        #adBreakdownChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #adBreakdownChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #adBreakdownChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        .metric-chart-icon { vertical-align: middle; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Active Channel Yesterday',
        'sub_title' => $yesterdayLabel . ' (latest complete day — sales, orders, qty, spend)',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" id="channel-search" class="form-control form-control-sm"
                        placeholder="Search Channel..." style="width: 150px; display: inline-block;">

                    <a href="{{ route('l7.marketplace.master') }}" class="btn btn-sm btn-outline-dark"
                        title="Open Active Channel 7 Days">
                        <i class="fas fa-calendar-week me-1"></i> L7 Master
                    </a>
                    <a href="{{ route('all.marketplace.master') }}" class="btn btn-sm btn-outline-dark"
                        title="Open Active Channel Master (L30)">
                        <i class="fas fa-table me-1"></i> L30 Master
                    </a>
                    <span class="badge" style="background-color:#17a2b8;color:#fff;font-weight:600;">
                        Complete day: {{ $yesterdayLabel }} PT
                    </span>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2 ebay2-summary-badge-row" role="group" aria-label="Yesterday summary metrics">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_sales" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;"
                            title="Sum of 1-day sales. Click for trend + date range.">
                            Y Sales: <span id="total-y-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="l30_orders" style="color: black; font-weight: bold; cursor:pointer;"
                            title="Sum of yesterday orders. Click for trend + date range.">
                            Orders: <span id="total-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="qty" style="color: white; font-weight: bold; cursor:pointer;"
                            title="Sum of yesterday units. Click for trend + date range.">
                            Qty: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="total_views" style="color: black; font-weight: bold; cursor:pointer;"
                            title="Yesterday listing views. Click for trend + date range.">
                            Views: <span id="total-views">—</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="cvr" style="color: white; font-weight: bold; cursor:pointer;"
                            title="Yesterday CVR. Click for trend + date range.">
                            CVR: <span id="avg-cvr">—</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="gprofit" style="color: black; font-weight: bold; cursor:pointer;"
                            title="Yesterday blended GPFT%. Click for trend + date range.">
                            GPFT: <span id="avg-gprofit">0%</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="groi" style="color: white; font-weight: bold; cursor:pointer;"
                            title="Yesterday blended GROI%. Click for trend + date range.">
                            G ROI: <span id="avg-groi">0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="ad_spend" style="color: white; font-weight: bold; cursor:pointer;"
                            title="Yesterday ad spend. Click for trend + date range.">
                            Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="ads_pct" style="background-color: #d63384; color: white; font-weight: bold; cursor:pointer;"
                            title="Yesterday Ads% (TACOS). Click for trend + date range.">
                            Ads: <span id="ads-percent-badge">0%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="npft" style="color: black; font-weight: bold; cursor:pointer;"
                            title="Yesterday NPFT%. Click for trend + date range.">
                            NPFT: <span id="avg-npft">0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="nroi" style="color: white; font-weight: bold; cursor:pointer;"
                            title="Yesterday NROI%. Click for trend + date range.">
                            NROI: <span id="avg-nroi">0%</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="marketplace-table-wrapper" style="width: 100%;">
                    <div id="marketplace-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="adBreakdownChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="adChartModalTitle">Metric trend — Rolling window</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="adChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="adBreakdownChartContainer" style="height: 28vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="adBreakdownChart"></canvas>
                        </div>
                        <div id="adChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="adChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="adChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="adChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="adChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="adChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily data is not available for this channel.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        var channelMetricDotTrendsUrl = "{{ url('channel-metric-dot-trends') }}";
        var DEFAULT_DOT_GRAY = '#6c757d';
        var lastDotColorByKey = {};
        var currentChartChannel = '';
        var currentMetricKey = '';
        var currentChartMetric = '';
        var currentChartDays = 30;
        var currentCellValue = null;
        var adBreakdownChartInstance = null;
        var adChartAjax = null;

        function snapshotChannelKey(name) {
            var k = (name || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            var aliases = {
                ebay2: 'ebaytwo',
                ebay3: 'ebaythree',
                shopify: 'shopifyb2c',
                tiktok: 'tiktokshop',
                tiktok2: 'tiktokshop2',
                bestbuy: 'bestbuyusa',
                facebookmarketplace: 'fbmarketplace'
            };
            return aliases[k] || k;
        }
        function metricChartDotColors(values, isInverted) {
            var gray = '#6c757d';
            var green = '#28a745';
            var red = '#dc3545';
            var eps = 0.0001;
            return values.map(function(v, i) {
                if (i === 0) return gray;
                var prev = null;
                for (var j = i - 1; j >= 0; j--) {
                    if (Math.abs(values[j] - v) > eps) {
                        prev = values[j];
                        break;
                    }
                }
                if (prev === null) return gray;
                if (isInverted) return v < prev ? green : red;
                return v > prev ? green : red;
            });
        }
        function getMetricDotColor(channelName, metricKey) {
            var k = snapshotChannelKey(channelName) + '_' + (metricKey || '');
            return lastDotColorByKey[k] || DEFAULT_DOT_GRAY;
        }
        function saveDotColorsToStorage() {
            try { localStorage.setItem('yesterdayChannelDotColors', JSON.stringify(lastDotColorByKey)); } catch (e) {}
        }
        function channelKeyFromRow(row) {
            if (row && row.snapshot_key) return String(row.snapshot_key).trim();
            return snapshotChannelKey((row && (row['Channel '] || row.channel)) || '');
        }
        function channelLabelFromRow(row) {
            return ((row && (row['Channel '] || row.channel)) || '').toString().trim();
        }
        function withDot(html, channel, metric, value, label) {
            const color = getMetricDotColor(channel, metric);
            const v = (value === null || value === undefined || isNaN(value)) ? '' : value;
            const display = (label || channel || '').toString().replace(/"/g, '&quot;');
            const key = snapshotChannelKey(channel);
            return `<span class="d-inline-flex align-items-center justify-content-center gap-1">${html}<i class="fas fa-circle metric-chart-icon" data-channel="${key}" data-channel-label="${display}" data-metric="${metric}" data-value="${v}" style="cursor:pointer;color:${color};font-size:8px;" title="View Chart"></i></span>`;
        }

        function showToast(type, message) {
            const toast = $(`
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        }

        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || value === 'N/A' || value === '—') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        function hasPct(value) {
            return value !== null && value !== undefined && value !== '' && !isNaN(parseNumber(value));
        }

        function gpftStyle(value) {
            if (value <= 10) return 'color:#a00211;';
            if (value <= 18) return 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
            if (value <= 25) return 'color:#3591dc;';
            if (value <= 40) return 'color:#28a745;';
            return 'color:#e83e8c;';
        }

        function groiStyle(value) {
            if (value <= 50) return 'color:#a00211;';
            if (value <= 75) return 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
            if (value <= 125) return 'color:#28a745;';
            return 'color:#8000ff;';
        }

        function adsStyle(value) {
            if (value < 5) return 'color:#e83e8c;';
            if (value <= 10) return 'color:#28a745;';
            return 'color:#a00211;';
        }

        function formatPctCell(value, kind) {
            if (!hasPct(value)) return '<span style="color:#adb5bd;">—</span>';
            const v = parseNumber(value);
            const style = kind === 'ads' ? adsStyle(v) : (kind === 'gpft' || kind === 'npft' ? gpftStyle(v) : groiStyle(v));
            const digits = kind === 'ads' ? 1 : 0;
            return `<span style="${style}font-weight:600;">${v.toFixed(digits)}%</span>`;
        }

        function formatMoney(value, emptyAsNys) {
            const v = parseNumber(value);
            if (!v) {
                return emptyAsNys
                    ? '<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>'
                    : '<span style="color:#adb5bd;">—</span>';
            }
            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(v).toLocaleString('en-US')}</span>`;
        }

        function formatInt(value) {
            const v = parseNumber(value);
            if (!v) return '<span style="color:#adb5bd;">—</span>';
            return `<span style="font-weight:600;">${Math.round(v).toLocaleString('en-US')}</span>`;
        }

        function weightedTotals(data) {
            let sumSales = 0, sumPft = 0, sumCogs = 0, sumAd = 0, sumAdSales = 0, sumGpftSales = 0, sumOrders = 0, sumQty = 0, sumViews = 0;
            (data || []).forEach(function(row) {
                sumSales += parseNumber(row['Y Sales'] || 0);
                sumPft += parseNumber(row['Total PFT'] || 0);
                sumCogs += parseNumber(row['cogs'] || 0);
                sumAd += parseNumber(row['Total Ad Spend'] || 0);
                sumAdSales += parseNumber(row['Ad Sales'] || 0);
                sumGpftSales += parseNumber(row['gpft_sales'] || 0);
                sumOrders += parseNumber(row['L30 Orders'] || 0);
                sumQty += parseNumber(row['Qty'] || 0);
                if (row['Total Views'] !== null && row['Total Views'] !== undefined && row['Total Views'] !== '') {
                    sumViews += parseNumber(row['Total Views'] || 0);
                }
            });
            const gpft = sumSales > 0 ? (sumPft / sumSales) * 100 : (sumGpftSales > 0 ? (sumPft / sumGpftSales) * 100 : null);
            const groi = sumCogs > 0 ? (sumPft / sumCogs) * 100 : null;
            const ads = sumSales > 0 ? (sumAd / sumSales) * 100 : 0;
            const npft = gpft != null ? gpft - ads : null;
            const nroi = sumCogs > 0 ? ((sumPft - sumAd) / sumCogs) * 100 : null;
            const cvr = sumViews > 0 ? (sumQty / sumViews) * 100 : null;
            return { sumSales, sumPft, sumCogs, sumAd, sumAdSales, sumGpftSales, sumOrders, sumQty, sumViews, gpft, groi, ads, npft, nroi, cvr };
        }

        function updateSummaryStats(data) {
            const t = weightedTotals(data);
            $('#total-channels').text((data || []).length);
            $('#total-y-sales').text(t.sumSales > 0 ? ('$' + Math.round(t.sumSales).toLocaleString('en-US')) : 'NYS');
            $('#total-orders').text(Math.round(t.sumOrders).toLocaleString('en-US'));
            $('#total-qty').text(Math.round(t.sumQty).toLocaleString('en-US'));
            $('#total-views').text(t.sumViews > 0 ? Math.round(t.sumViews).toLocaleString('en-US') : '—');
            $('#avg-cvr').text(t.cvr == null ? '—' : (Math.round(t.cvr * 10) / 10).toFixed(1) + '%');
            $('#avg-gprofit').text(t.gpft == null ? '—' : (Math.round(t.gpft) + '%'));
            $('#avg-groi').text(t.groi == null ? '—' : (Math.round(t.groi) + '%'));
            $('#total-ad-spend').text('$' + Math.round(t.sumAd).toLocaleString('en-US'));
            $('#ads-percent-badge').text((Math.round(t.ads * 10) / 10).toFixed(1) + '%');
            $('#avg-npft').text(t.npft == null ? '—' : (Math.round(t.npft) + '%'));
            $('#avg-nroi').text(t.nroi == null ? '—' : (Math.round(t.nroi) + '%'));
            setBadgeExact('y_sales', t.sumSales);
            setBadgeExact('l30_orders', t.sumOrders);
            setBadgeExact('qty', t.sumQty);
            setBadgeExact('total_views', t.sumViews);
            setBadgeExact('cvr', t.cvr);
            setBadgeExact('gprofit', t.gpft);
            setBadgeExact('groi', t.groi);
            setBadgeExact('ad_spend', t.sumAd);
            setBadgeExact('ads_pct', t.ads);
            setBadgeExact('npft', t.npft);
            setBadgeExact('nroi', t.nroi);
        }

        function setBadgeExact(metric, value) {
            const $b = $('.badge-chart-link[data-metric="' + metric + '"]');
            if (!$b.length) return;
            if (value === null || value === undefined || isNaN(value)) {
                $b.removeAttr('data-exact-value');
            } else {
                $b.attr('data-exact-value', value);
            }
        }

        const metricLabels = {
            y_sales: 'Y Sales', l30_orders: 'Orders', qty: 'Qty', total_views: 'Views',
            cvr: 'CVR', gprofit: 'GPFT%', groi: 'G ROI%', ad_spend: 'Spend',
            ads_pct: 'Ads %', npft: 'NPFT%', nroi: 'NROI%'
        };
        function adChartRangeLabel(days) { return days === 0 ? 'Lifetime' : ('L' + days); }
        function showMetricChart(channel, metricKey, cellValue, displayName) {
            currentChartChannel = snapshotChannelKey(channel);
            currentMetricKey = metricKey;
            currentChartMetric = metricKey;
            currentChartDays = 30;
            currentCellValue = (cellValue !== undefined && cellValue !== null && !isNaN(cellValue)) ? cellValue : null;
            $('#adChartRangeSelect').val('30');
            const titleName = displayName || channel || 'All';
            $('#adChartModalTitle').text(`${titleName} - ${metricLabels[metricKey] || metricKey} (Rolling ${adChartRangeLabel(30)})`);
            new bootstrap.Modal(document.getElementById('adBreakdownChartModal')).show();
            loadMetricChart();
        }
        function loadMetricChart() {
            if (adChartAjax) adChartAjax.abort();
            $('#adChartNoData').hide();
            $('#adBreakdownChartContainer').hide();
            $('#adChartLoading').show();
            const params = { channel: currentChartChannel, metric: currentMetricKey, days: currentChartDays };
            if (currentCellValue !== null) params.badge_value = currentCellValue;
            adChartAjax = $.ajax({
                url: '/channel-metric-chart-data',
                method: 'GET',
                data: params,
                success: function(response) {
                    adChartAjax = null;
                    $('#adChartLoading').hide();
                    if (response.success && response.data && response.data.length > 0) {
                        $('#adBreakdownChartContainer').show();
                        renderMetricChart(response.data);
                    } else {
                        $('#adChartNoData').show();
                    }
                },
                error: function(xhr, status) {
                    adChartAjax = null;
                    if (status === 'abort') return;
                    $('#adChartLoading').hide();
                    $('#adChartNoData').show();
                }
            });
        }
        function renderMetricChart(data) {
            const ctx = document.getElementById('adBreakdownChart').getContext('2d');
            if (adBreakdownChartInstance) adBreakdownChartInstance.destroy();
            const labels = data.map(d => d.date);
            const values = data.map(d => d.value);
            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;
            const fmtVal = (v) => {
                const m = currentChartMetric;
                if (m === 'y_sales' || m === 'ad_spend') return '$' + Math.round(v).toLocaleString('en-US');
                if (m === 'cvr') return v.toFixed(2) + '%';
                if (['gprofit','groi','ads_pct','npft','nroi'].indexOf(m) >= 0) return v.toFixed(1) + '%';
                return Math.round(v).toLocaleString('en-US');
            };
            document.getElementById('adChartHighest').textContent = fmtVal(dataMax);
            document.getElementById('adChartMedian').textContent = fmtVal(median);
            document.getElementById('adChartLowest').textContent = fmtVal(dataMin);
            const isInverted = currentChartMetric === 'ads_pct';
            const dotColors = metricChartDotColors(values, isInverted);
            const labelColors = values.map(v => v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d'));
            const medianLinePlugin = {
                id: 'medianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y, xScale = chart.scales.x, c = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    c.save(); c.setLineDash([6, 4]); c.strokeStyle = '#6c757d'; c.lineWidth = 1.2;
                    c.beginPath(); c.moveTo(xScale.left, yPixel); c.lineTo(xScale.right, yPixel); c.stroke(); c.restore();
                }
            };
            const valueLabelsPlugin = {
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    c.save(); c.font = 'bold 10px Inter, system-ui, sans-serif'; c.textAlign = 'left'; c.textBaseline = 'middle';
                    meta.data.forEach((point, i) => {
                        c.save();
                        c.translate(point.x, point.y + ((i % 2 === 0) ? -12 : -22));
                        c.rotate(-Math.PI / 4);
                        c.fillStyle = labelColors[i];
                        c.fillText(fmtVal(dataset.data[i]), 2, 0);
                        c.restore();
                    });
                    c.restore();
                }
            };
            adBreakdownChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
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
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 36, left: 2, right: 8, bottom: 8 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Value: ' + fmtVal(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: { min: yMin, max: yMax, ticks: { font: { size: 9 }, callback: (v) => fmtVal(v) } },
                        x: { ticks: { font: { size: 9 }, maxRotation: 45, minRotation: 45 } }
                    }
                }
            });
        }

        var metricDotMetricKeys = ['y_sales','l30_orders','qty','total_views','cvr','gprofit','groi','ad_spend','ads_pct','npft','nroi'];
        function loadMetricDotTrends(tableData) {
            var data = tableData || [];
            var channelKeys = [];
            data.forEach(function(row) {
                var ch = snapshotChannelKey(channelKeyFromRow(row));
                if (ch) channelKeys.push(ch);
            });
            if (!channelKeys.length) return;
            $.ajax({
                url: channelMetricDotTrendsUrl,
                type: 'GET',
                data: { channels: channelKeys.join(',') },
                dataType: 'json'
            }).done(function(response) {
                var inverted = ['ads_pct'];
                if (response.success && response.channels) {
                    Object.keys(response.channels).forEach(function(channel) {
                        var metrics = response.channels[channel];
                        Object.keys(metrics).forEach(function(metric) {
                            var pair = metrics[metric];
                            var v1 = pair[0] != null ? parseFloat(pair[0]) : null;
                            var v2 = pair[1] != null ? parseFloat(pair[1]) : null;
                            if (v1 == null || v2 == null) {
                                lastDotColorByKey[channel + '_' + metric] = DEFAULT_DOT_GRAY;
                                return;
                            }
                            var color = v1 === v2 ? DEFAULT_DOT_GRAY : (inverted.indexOf(metric) >= 0
                                ? (v2 < v1 ? '#28a745' : '#dc3545')
                                : (v2 > v1 ? '#28a745' : '#dc3545'));
                            lastDotColorByKey[channel + '_' + metric] = color;
                        });
                    });
                }
                channelKeys.forEach(function(ch) {
                    metricDotMetricKeys.forEach(function(m) {
                        if (lastDotColorByKey[ch + '_' + m] === undefined) lastDotColorByKey[ch + '_' + m] = DEFAULT_DOT_GRAY;
                    });
                });
                saveDotColorsToStorage();
                function redrawDots() {
                    if (table && table.redraw) table.redraw(true);
                }
                redrawDots();
                setTimeout(redrawDots, 100);
                setTimeout(redrawDots, 500);
                setTimeout(redrawDots, 1200);
            });
        }

        $(document).ready(function() {
            table = new Tabulator("#marketplace-table", {
                ajaxURL: "/yesterday-marketplace-master-data",
                ajaxSorting: false,
                layout: "fitDataStretch",
                height: false,
                pagination: false,
                columnCalcs: "both",
                initialSort: [{ column: "Y Sales", dir: "desc" }],
                ajaxResponse: function(url, params, response) {
                    if (response && response.status === 200 && response.data) {
                        updateSummaryStats(response.data);
                        loadMetricDotTrends(response.data);
                        if (response.label) {
                            const $badge = $('#summary-stats').prev().find('.badge').last();
                            if ($badge.length) {
                                $badge.text('Complete day: ' + response.label + ' PT');
                            }
                        }
                        return response.data;
                    }
                    showToast('error', (response && response.message) ? response.message : 'Failed to load yesterday metrics');
                    return [];
                },
                ajaxRequestError: function() {
                    showToast('error', 'Failed to load yesterday channel data');
                },
                columns: [
                    {
                        title: "Img",
                        field: "logo",
                        frozen: true,
                        width: 60,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const logo = cell.getValue();
                            const channel = (rowData['Channel '] || '').trim();
                            const sellerLink = (rowData['seller_link'] || '').trim();
                            const imgHtml = logo
                                ? `<img src="/storage/${logo}" alt="${channel}" class="channel-logo-thumb" onerror="this.style.display='none'"/>`
                                : `<span class="channel-logo-placeholder" title="No logo"><i class="fas fa-image text-muted"></i></span>`;
                            if (sellerLink) {
                                return `<a href="${sellerLink.replace(/"/g, '&quot;')}" target="_blank" rel="noopener noreferrer" class="channel-logo-link">${imgHtml}</a>`;
                            }
                            return imgHtml;
                        }
                    },
                    {
                        title: "MP",
                        field: "Channel ",
                        frozen: true,
                        formatter: function(cell) {
                            const channel = cell.getValue();
                            const missingLink = cell.getRow().getData()['missing_link'] || '';
                            if (missingLink) {
                                return `<a href="${missingLink}" target="_blank" style="color:inherit;font-weight:inherit;text-decoration:none;">${channel}</a>`;
                            }
                            return `<span>${channel}</span>`;
                        }
                    },
                    {
                        title: "Channel",
                        field: "alias",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const alias = (cell.getValue() || '').toString().trim();
                            if (!alias) return '<span style="color:#adb5bd;">-</span>';
                            const viewLink = cell.getRow().getData()['missing_link'] || '';
                            if (viewLink) {
                                return `<a href="${viewLink}" target="_blank" style="color:#0d6efd;font-weight:600;text-decoration:none;">${alias}</a>`;
                            }
                            return `<span style="font-weight:600;">${alias}</span>`;
                        }
                    },
                    {
                        title: "Y Sales",
                        field: "Y Sales",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "1-day sales using the same latest-complete-day window as All Marketplace Master (not L30).",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatMoney(cell.getValue(), true), channelKeyFromRow(row), 'y_sales', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (!v) return '<strong>NYS</strong>';
                            return `<strong>$${Math.round(v).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Orders",
                        field: "L30 Orders",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday order count (Pacific).",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatInt(cell.getValue()), channelKeyFromRow(row), 'l30_orders', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Qty items",
                        field: "Qty",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday units sold (Pacific).",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatInt(cell.getValue()), channelKeyFromRow(row), 'qty', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Views",
                        field: "Total Views",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday listing views stored from API (1-day Pacific — not L30).",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            const row = cell.getRow().getData();
                            if (v === null || v === undefined || v === '') {
                                return withDot('<span style="color:#adb5bd;">—</span>', channelKeyFromRow(row), 'total_views', 0, channelLabelFromRow(row));
                            }
                            const n = parseNumber(v);
                            if (n <= 0) {
                                return withDot('<span style="color:#adb5bd;">—</span>', channelKeyFromRow(row), 'total_views', 0, channelLabelFromRow(row));
                            }
                            return withDot(`<span style="font-weight:600;">${Math.round(n).toLocaleString('en-US')}</span>`, channelKeyFromRow(row), 'total_views', n, channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).sumViews;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (!v) return '<strong>—</strong>';
                            return `<strong>${Math.round(v).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "CVR",
                        field: "CVR",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday CVR = yesterday qty ÷ yesterday views × 100.",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            const row = cell.getRow().getData();
                            if (v === null || v === undefined || v === '') {
                                return withDot('<span style="color:#adb5bd;">—</span>', channelKeyFromRow(row), 'cvr', 0, channelLabelFromRow(row));
                            }
                            return withDot(`<span style="font-weight:600;">${(Math.round(parseNumber(v) * 10) / 10).toFixed(1)}%</span>`, channelKeyFromRow(row), 'cvr', parseNumber(v), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).cvr;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<strong>—</strong>';
                            return `<strong>${(Math.round(parseNumber(v) * 10) / 10).toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "GPFT%",
                        field: "Gprofit%",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday GPFT% = profit $ ÷ yesterday sales (not L30).",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatPctCell(cell.getValue(), 'gpft'), channelKeyFromRow(row), 'gprofit', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).gpft;
                        },
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${formatPctCell(cell.getValue(), 'gpft')}</strong>`;
                        }
                    },
                    {
                        title: "G ROI %",
                        field: "G Roi",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday GROI% = profit $ ÷ yesterday COGS (not L30).",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatPctCell(cell.getValue(), 'groi'), channelKeyFromRow(row), 'groi', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).groi;
                        },
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${formatPctCell(cell.getValue(), 'groi')}</strong>`;
                        }
                    },
                    {
                        title: "Ads %",
                        field: "Ads%",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday Ads% = yesterday ad spend ÷ yesterday sales.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (!row.computed && !parseNumber(row['Y Sales'])) {
                                return withDot('<span style="color:#adb5bd;">—</span>', channelKeyFromRow(row), 'ads_pct', 0, channelLabelFromRow(row));
                            }
                            return withDot(formatPctCell(cell.getValue() || 0, 'ads'), channelKeyFromRow(row), 'ads_pct', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).ads;
                        },
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${formatPctCell(cell.getValue(), 'ads')}</strong>`;
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "N PFT",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday NPFT% = yesterday GPFT% − yesterday Ads%.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatPctCell(cell.getValue(), 'npft'), channelKeyFromRow(row), 'npft', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).npft;
                        },
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${formatPctCell(cell.getValue(), 'npft')}</strong>`;
                        }
                    },
                    {
                        title: "NROI %",
                        field: "N ROI",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Yesterday NROI% = (yesterday profit $ − ad spend) ÷ COGS.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            return withDot(formatPctCell(cell.getValue(), 'nroi'), channelKeyFromRow(row), 'nroi', parseNumber(cell.getValue()), channelLabelFromRow(row));
                        },
                        bottomCalc: function(values, data) {
                            return weightedTotals(data).nroi;
                        },
                        bottomCalcFormatter: function(cell) {
                            return `<strong>${formatPctCell(cell.getValue(), 'nroi')}</strong>`;
                        }
                    },
                    {
                        title: "Spend",
                        field: "Total Ad Spend",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Ad spend for the same day as Y Sales (not L1 from another day).",
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            const row = cell.getRow().getData();
                            if (!v) {
                                return withDot('<span style="color:#adb5bd;">—</span>', channelKeyFromRow(row), 'ad_spend', 0, channelLabelFromRow(row));
                            }
                            return withDot(`<span style="font-weight:600;">$${Math.round(v).toLocaleString('en-US')}</span>`, channelKeyFromRow(row), 'ad_spend', v, channelLabelFromRow(row));
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong>$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    }
                ]
            });

            $(document).on('click', '.metric-chart-icon', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const channel = $(this).data('channel');
                const metric = $(this).data('metric');
                const raw = parseFloat($(this).data('value'));
                const label = $(this).data('channel-label') || channel;
                showMetricChart(channel, metric, isNaN(raw) ? null : raw, label);
            });
            $(document).on('click', '.badge-chart-link', function() {
                const metricKey = $(this).data('metric');
                let badgeValue = parseFloat($(this).attr('data-exact-value'));
                if (isNaN(badgeValue)) badgeValue = null;
                showMetricChart('All', metricKey, badgeValue, 'All');
            });
            $(document).on('change', '#adChartRangeSelect', function() {
                const days = parseInt($(this).val(), 10);
                if (days === currentChartDays) return;
                currentChartDays = days;
                const titleEl = $('#adChartModalTitle');
                titleEl.text(titleEl.text().replace(/\(Rolling [^)]+\)/, `(Rolling ${adChartRangeLabel(days)})`));
                loadMetricChart();
            });
            $('#adBreakdownChartModal').on('hidden.bs.modal', function() {
                if (adBreakdownChartInstance) {
                    adBreakdownChartInstance.destroy();
                    adBreakdownChartInstance = null;
                }
            });

            $('#channel-search').on('keyup', function() {
                const q = $(this).val();
                if (!table) return;
                if (!q) {
                    table.clearFilter();
                    return;
                }
                table.setFilter(function(data) {
                    const name = (data['Channel '] || '').toString().toLowerCase();
                    const alias = (data.alias || '').toString().toLowerCase();
                    const needle = q.toLowerCase();
                    return name.indexOf(needle) !== -1 || alias.indexOf(needle) !== -1;
                });
            });
        });
    </script>
@endsection
