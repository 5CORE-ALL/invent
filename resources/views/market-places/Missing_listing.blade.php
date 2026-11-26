@php($c2c = $c2c ?? false)
@extends('layouts.vertical', ['title' => $c2c ? 'Missing Listing C2C' : 'Missing Listing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .ml-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .ml-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 12px;
        }
        .ml-channel-listing-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .ml-channel-listing-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        #stat-missing-listing.badge,
        .badge-ml-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important; /* rectangular, not pill */
            font-weight: 700;
        }
        .badge-ml-chart { cursor: pointer; font-weight: bold; }
        /* The without-0-Inv badge is the number the sidebar shows. */
        .ml-seller-portal-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ml-seller-portal-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e7f1ff;
            color: #0d6efd;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .ml-seller-portal-link:hover {
            background: #0d6efd;
            color: #fff;
        }
        .ml-seller-portal-empty {
            color: #adb5bd;
            font-style: italic;
            font-size: 0.75rem;
        }
        .ml-from-sheet {
            color: #6c757d;
            font-weight: 600;
            font-style: italic;
        }
        .ml-mode-select {
            width: 100%;
            max-width: 108px;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
            border-radius: 4px;
            border: 1px solid transparent;
            padding: 3px 6px;
            text-align: center;
            cursor: pointer;
        }
        .ml-mode-select.ml-mode-empty {
            background: #f1f3f5;
            color: #6c757d;
            border-color: #dee2e6;
        }
        .ml-mode-select.ml-mode-auto {
            background: #d1e7dd;
            color: #0f5132;
            border-color: #a3cfbb;
        }
        .ml-mode-select.ml-mode-csv {
            background: #cfe2ff;
            color: #084298;
            border-color: #9ec5fe;
        }
        .ml-mode-select.ml-mode-manual {
            background: #ffe5d0;
            color: #984c0c;
            border-color: #ffc107;
        }
        .ml-mode-select.ml-mode-semi {
            background: #e2d9f3;
            color: #432874;
            border-color: #c5b3e6;
        }
        .ml-mode-select option[value="Auto"] { background: #d1e7dd; color: #0f5132; }
        .ml-mode-select option[value="CSV"] { background: #cfe2ff; color: #084298; }
        .ml-mode-select option[value="Manual"] { background: #ffe5d0; color: #984c0c; }
        .ml-mode-select option[value="Semi"] { background: #e2d9f3; color: #432874; }
        .variation-single-select {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            padding: 1px 4px;
            height: 26px;
            max-width: 112px;
            cursor: pointer;
            background: #fff;
        }
        .variation-single-select.is-variation { background: #e0e7ff; color: #3730a3; border-color: #a5b4fc; }
        .variation-single-select.is-single { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .ml-source-api {
            color: #198754;
            font-weight: 700;
        }
        .ml-source-sheet {
            color: #6c757d;
            font-weight: 700;
        }
        .ml-source-csv {
            color: #084298;
            font-weight: 700;
        }
        .ml-csv-upload-btn,
        .ml-master-listing-btn {
            font-size: 11px;
            padding: 1px 7px;
            margin-left: 6px;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group {
            background: #eef4fb;
            text-align: center;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group .tabulator-col-title {
            font-weight: 700;
            color: #6c2c2c;
        }
        .ml-inactive-count {
            font-weight: 600;
            text-decoration: none;
        }
        .tabulator .tabulator-cell.tabulator-editing { padding: 2px 4px; }
        #missing-listing-table .ml-head-count {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #0d6efd;
            line-height: 1.1;
        }
        #missing-listing-table .tabulator-header-filter input,
        #missing-listing-table .tabulator-header-filter select {
            height: 22px;
            font-size: 11px;
            padding: 0 4px;
            min-width: 0;
            width: 100%;
        }
        #mlChartContainer .ml-chart-plot { flex: 1; min-width: 0; height: 100%; position: relative; }

        /* Metric history modal — same full-width layout as Active Channel */
        #mlMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #mlMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #mlMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        /* ===== Gen Z look: soft gradients, big rounded cards, glowing dots ===== */
        .ml-genz { --ml-violet: #7c3aed; --ml-pink: #ff3d81; --ml-blue: #3b82f6; --ml-ink: #1e1b4b; }
        .ml-genz .card {
            border: 0; border-radius: 24px; overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #faf8ff 100%);
            box-shadow: 0 18px 50px -20px rgba(124, 58, 237, .35), 0 2px 10px rgba(30, 27, 75, .05);
        }
        .ml-genz .ml-hero { padding: 18px 20px 6px; }
        .ml-stat {
            position: relative; display: inline-flex; align-items: center; gap: 14px;
            padding: 14px 20px 14px 22px; border-radius: 20px; color: #fff; cursor: default;
            box-shadow: 0 14px 30px -14px var(--ml-glow, rgba(124, 58, 237, .6));
            transition: transform .18s ease, box-shadow .18s ease;
            background: var(--ml-bg);
        }
        .ml-stat:hover { transform: translateY(-3px) rotate(-.4deg); }
        .ml-stat--a { --ml-bg: linear-gradient(135deg, #ff3d81 0%, #ff8a3d 100%); --ml-glow: rgba(255, 61, 129, .65); }
        .ml-stat--b { --ml-bg: linear-gradient(135deg, #7c3aed 0%, #3b82f6 100%); --ml-glow: rgba(124, 58, 237, .65); }
        .ml-stat-text { display: flex; flex-direction: column; line-height: 1.05; }
        .ml-stat-label { font-size: .72rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; opacity: .9; }
        .ml-stat-num { font-size: 2.1rem; font-weight: 800; letter-spacing: -.02em; }
        .ml-dot {
            width: 14px; height: 14px; border-radius: 50%; border: 0; padding: 0; cursor: pointer; flex: none;
            background: #fff; box-shadow: 0 0 0 0 rgba(255, 255, 255, .7); animation: ml-pulse 1.8s infinite;
        }
        .ml-dot:hover { transform: scale(1.35); }
        .ml-dot-cell {
            display: inline-block; width: 10px; height: 10px; margin-left: 7px; border-radius: 50%; cursor: pointer;
            vertical-align: middle; background: var(--dot, #7c3aed);
            box-shadow: 0 0 0 0 var(--dot, #7c3aed); animation: ml-pulse-cell 2.2s infinite;
            transition: transform .15s ease;
        }
        .ml-dot-cell:hover { transform: scale(1.5); }
        @keyframes ml-pulse { 0% { box-shadow: 0 0 0 0 rgba(255,255,255,.75); } 70% { box-shadow: 0 0 0 11px rgba(255,255,255,0); } 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); } }
        @keyframes ml-pulse-cell { 0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--dot, #7c3aed) 55%, transparent); } 70% { box-shadow: 0 0 0 8px transparent; } 100% { box-shadow: 0 0 0 0 transparent; } }
        .ml-genz #ml-refresh-all-btn {
            border: 0; border-radius: 999px; padding: 9px 18px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, #7c3aed, #ff3d81); box-shadow: 0 10px 22px -10px rgba(124, 58, 237, .8);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .ml-genz #ml-refresh-all-btn:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -10px rgba(255, 61, 129, .8); }
        .ml-genz #ml-refresh-all-details { color: var(--ml-violet); font-weight: 700; text-decoration: none; }
        .ml-genz .ml-search-wrap { padding: 12px 20px 14px; background: transparent !important; border: 0 !important; }
        .ml-genz #missing-listing-search {
            border-radius: 999px; border: 2px solid #ede9fe; padding: 9px 18px; font-size: .9rem; background: #fff;
            box-shadow: 0 6px 18px -12px rgba(124, 58, 237, .6); transition: border-color .15s ease, box-shadow .15s ease;
        }
        .ml-genz #missing-listing-search:focus { border-color: var(--ml-violet); box-shadow: 0 0 0 4px rgba(124, 58, 237, .15); outline: 0; }
        .ml-genz #missing-listing-table { padding: 0 14px 14px; }
        .ml-genz .tabulator { border: 0; background: transparent; border-radius: 18px; font-size: 13px; }
        .ml-genz .tabulator .tabulator-header {
            border: 0; border-radius: 16px; background: linear-gradient(135deg, #ede9fe 0%, #fce7f3 100%); color: var(--ml-ink);
        }
        .ml-genz .tabulator .tabulator-header .tabulator-col { background: transparent; border-right: 0; }
        .ml-genz .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        }
        .ml-genz .tabulator .tabulator-header .tabulator-col.tabulator-col-group { background: rgba(255, 255, 255, .45); }
        .ml-genz .tabulator .tabulator-tableholder { background: transparent; }
        .ml-genz .tabulator .tabulator-row { background: #fff; border: 0; border-bottom: 1px solid #f1ecff; transition: background-color .12s ease; }
        .ml-genz .tabulator .tabulator-row.tabulator-row-even { background: #fbfaff; }
        .ml-genz .tabulator .tabulator-row:hover { background: #f3edff; }
        .ml-genz .tabulator .tabulator-row .tabulator-cell { border-right: 0; }
        .ml-genz .tabulator .tabulator-footer { border: 0; background: transparent; }
        .ml-genz .tabulator .tabulator-footer .tabulator-page { border-radius: 999px; border: 0; background: #f3f0ff; margin: 0 2px; }
        .ml-genz .tabulator .tabulator-footer .tabulator-page.active { background: linear-gradient(135deg, #7c3aed, #ff3d81); color: #fff; }
        .ml-genz .tabulator .tabulator-footer .tabulator-calcs-holder { background: transparent; border: 0; }
        .ml-genz .ml-mode-select, .ml-genz .variation-single-select { border-radius: 999px; }
        .ml-genz .ml-source-api, .ml-genz .ml-source-csv, .ml-genz .ml-source-sheet {
            display: inline-block; padding: 2px 12px; border-radius: 999px; font-size: 11px; letter-spacing: .04em;
        }
        .ml-genz .ml-source-api { background: #dcfce7; color: #15803d; }
        .ml-genz .ml-source-csv { background: #dbeafe; color: #1d4ed8; }
        .ml-genz .ml-source-sheet { background: #f1f5f9; color: #64748b; }
        .ml-genz .ml-csv-upload-btn, .ml-genz .ml-master-listing-btn { border-radius: 999px; font-weight: 700; }
        .ml-genz .ml-channel-logo, .ml-genz .ml-channel-logo-placeholder { border-radius: 10px; }
        .ml-genz #missing-listing-table .ml-head-count { color: var(--ml-violet); }
        #mlMetricChartModal .modal-content { border: 0; box-shadow: 0 30px 80px -20px rgba(124, 58, 237, .5); }
        #mlMetricChartModal .modal-header { background: linear-gradient(135deg, #7c3aed 0%, #ff3d81 100%) !important; }
        #mlMetricChartModal #mlChartRefPanel { border-radius: 0 16px 16px 0; background: #faf8ff; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $c2c ? 'Missing Listing C2C' : 'Missing Listing',
        'sub_title'  => '',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row ml-genz">
        <div class="card shadow-sm">
            <div class="card-body ml-hero">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <div class="ml-stat ml-stat--a ml-mode-badge" id="stat-missing-listing" data-metric="missing_l" title="Missing L = 0: every REQ SKU not listed, including the ones with 0 inventory. Same count as the Missing L = 0 badge on each listing page.">
                        <span class="ml-stat-text">
                            <span class="ml-stat-label">Missing L = 0</span>
                            <span class="ml-stat-num" id="total-missing-listing">{{ number_format(\App\Support\Marketplace\ListingChannelCounts::totalMissingL(true)) }}</span>
                        </span>
                        <button type="button" class="ml-dot ml-overall-chart-dot" data-metric="missing_l" title="Show graph" aria-label="Show Missing L = 0 graph"></button>
                    </div>
                    <div class="ml-stat ml-stat--b ml-mode-badge is-active" id="stat-missing-listing-inv" data-metric="missing_l_inv" title="Missing L > 0: REQ SKUs not listed that have inventory (INV above 0). Same count as the sidebar and the Missing L > 0 badge on each listing page.">
                        <span class="ml-stat-text">
                            <span class="ml-stat-label">Missing L &gt; 0</span>
                            <span class="ml-stat-num" id="total-missing-listing-inv">{{ number_format(\App\Support\Marketplace\ListingChannelCounts::totalMissingLInv(true)) }}</span>
                        </span>
                        <button type="button" class="ml-dot ml-overall-chart-dot" data-metric="missing_l_inv" title="Show graph" aria-label="Show Missing L &gt; 0 graph"></button>
                    </div>
                    @unless($c2c)
                        <button type="button" class="btn btn-sm btn-primary" id="ml-refresh-all-btn" title="Recount Missing L for every marketplace from the data in the app, the same numbers each listing page shows. Nothing is fetched from the marketplaces.">
                            <i class="fas fa-sync-alt me-1"></i>Refresh all Missing L now
                        </button>
                        <button type="button" class="btn btn-sm btn-link p-0" id="ml-refresh-all-details" data-bs-toggle="modal" data-bs-target="#mlRefreshAllModal">Details</button>
                        <span class="small text-muted" id="ml-refresh-all-status"></span>
                    @endunless
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="ml-search-wrap">
                    <input type="text" id="missing-listing-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                    <input type="file" id="ml-sheet-csv-input" accept=".csv,text/csv,text/plain" hidden>
                </div>
                <div id="missing-listing-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>

    @unless($c2c)
    <div class="modal fade" id="mlRefreshAllModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">Marketplace refresh status</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-2 small text-muted border-bottom" id="ml-refresh-all-summary"></div>
                    <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                        <thead class="table-light"><tr><th>Marketplace</th><th>State</th><th class="text-end">Missing L</th><th>Last finished</th><th>Detail</th></tr></thead>
                        <tbody id="ml-refresh-all-rows"><tr><td colspan="5" class="text-muted p-3">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endunless

    <div class="modal fade p-0" id="mlMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="mlChartModalTitle">Missing Listing - Rolling window (California)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="mlChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="mlChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div class="ml-chart-plot">
                            <canvas id="mlMetricChart"></canvas>
                        </div>
                        <div id="mlChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="mlChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="mlChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="mlChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="mlChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="mlChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily history is not available yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    let table = null;
    let mlMetricChartInstance = null;
    let mlChartAjax = null;
    const mlPageScope = @json($c2c ? 'c2c' : '');
    const mlPageTitle = @json($c2c ? 'Missing Listing C2C' : 'Missing Listing');
    let mlCurrentChartChannel = 'all';
    let mlCurrentChartDisplayChannel = 'All';
    let mlCurrentMetricKey = 'missing_l';
    let mlCurrentChartDays = 32;
    let mlCurrentBadgeValue = null;

    function paintColumnHeadCounts(rows) {
        if (!table) return;
        const list = rows || [];
        const sku = list.reduce(function(n, row) {
            return Math.max(n, Number(row.sku || 0));
        }, 0);
        const listed = list.reduce(function(n, row) {
            if (!isCountableRow(row) || row.listed === null || row.listed === undefined || row.listed === '') return n;
            return n + Number(row.listed || 0);
        }, 0);
        setColumnHeadCount('sku', sku);
        setColumnHeadCount('listed', listed);
    }

    function setColumnHeadCount(field, count) {
        const column = table.getColumn(field);
        const header = column && column.getElement ? column.getElement() : null;
        const title = header ? header.querySelector('.tabulator-col-title') : null;
        if (!title) return;
        let badge = title.querySelector('.ml-head-count');
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'ml-head-count';
            title.appendChild(badge);
        }
        badge.textContent = Number(count || 0).toLocaleString('en-US');
    }

    // One Missing Listing column per count, both read straight from the channel's listing-page numbers:
    //   M = 0    -> missing_listing      (every missing SKU, opens ?missing=1)
    //   M > 0 -> missing_listing_inv  (missing SKUs with INV above 0, opens ?missing=inv)
    function mlMissingColumn(title, field, linkParam, withChart, tooltip, filterProps) {
        const num = function(row) { return Number((row && row[field]) || 0); };
        return Object.assign({
            title: title,
            field: field,
            minWidth: 80,
            hozAlign: 'center',
            sorter: 'number',
            headerTooltip: tooltip,
            formatter: function(cell) {
                const row = cell.getRow().getData();
                if (isSheetRow(row)) return fromSheetCell(row);
                if (row.counts_pending) {
                    return '<span class="text-muted" title="Counting from the listing page...">&hellip;</span>';
                }
                const v = num(row);
                const channel = (row.channel || '').trim();
                const listingUrl = String(row.listing_url || '').trim();
                const color = v === 0 ? '#198754' : '#dc3545';
                const dotColor = v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d');
                const metric = field === 'missing_listing_inv' ? 'missing_l_inv' : 'missing_l';
                const chartIcon = withChart
                    ? `<span class="ml-dot-cell ml-metric-chart-icon" data-channel="${escapeHtml(channel)}" data-metric="${metric}" style="--dot:${dotColor};" title="Show graph"></span>`
                    : '';
                const countHtml = listingUrl
                    ? `<a href="${escapeHtml(listingUrl)}?missing=${linkParam}" class="ml-channel-listing-link" style="color:${color};font-weight:600;" title="Open Missing L SKUs">${v.toLocaleString('en-US')}</a>`
                    : `<span style="color:${color};font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                return `${countHtml}${chartIcon}`;
            },
            cellClick: function(e, cell) {
                const icon = e.target.closest ? e.target.closest('.ml-metric-chart-icon') : null;
                if (!icon) return;
                e.stopPropagation();
                const channel = icon.getAttribute('data-channel') || cell.getRow().getData().channel;
                showMlMetricChart(channel, Number(cell.getValue() || 0), icon.getAttribute('data-metric') || 'missing_l');
            },
            bottomCalc: function(values, data) {
                return (data || []).reduce(function(sum, row) {
                    return isSheetRow(row) ? sum : sum + num(row);
                }, 0);
            },
            bottomCalcFormatter: function(cell) {
                return Number(cell.getValue() || 0).toLocaleString('en-US');
            },
        }, filterProps);
    }

    function updateStats(rows, totalMissingL, totalMissingLInv) {
        if (totalMissingL !== undefined && totalMissingL !== null && !isNaN(Number(totalMissingL))) {
            $('#total-missing-listing').text(Number(totalMissingL).toLocaleString('en-US'));
        } else {
            const total = (rows || []).reduce((sum, r) => {
                if (!isCountableRow(r)) return sum;
                return sum + Number(r.missing_listing || 0);
            }, 0);
            $('#total-missing-listing').text(total.toLocaleString('en-US'));
        }
        if (totalMissingLInv !== undefined && totalMissingLInv !== null && !isNaN(Number(totalMissingLInv))) {
            $('#total-missing-listing-inv').text(Number(totalMissingLInv).toLocaleString('en-US'));
        } else {
            const totalInv = (rows || []).reduce((sum, r) => {
                if (!isCountableRow(r)) return sum;
                return sum + Number(r.missing_listing_inv || 0);
            }, 0);
            $('#total-missing-listing-inv').text(totalInv.toLocaleString('en-US'));
        }
    }

    function listingSource(rowData) {
        return String((rowData && rowData.data_source) || '').toUpperCase();
    }

    function isLiveApiRow(rowData) {
        return listingSource(rowData) === 'API';
    }

    function isCountableRow(rowData) {
        const source = listingSource(rowData);
        return source === 'API' || source === 'CSV';
    }

    function isSheetRow(rowData) {
        return !isCountableRow(rowData);
    }

    function fromSheetCell(rowData) {
        if (listingSource(rowData) === 'OFFLINE') {
            return '<span class="ml-from-sheet" title="Marketplace API is not connected — Missing L is not calculated">Not connected</span>';
        }
        return '<span class="ml-from-sheet" title="Listing counts come from Sheet — not calculated here">From Sheet</span>';
    }

    function formatInactiveCount(cell, field) {
        const v = Number(cell.getValue() || 0);
        const row = cell.getRow().getData();
        const url = String(row.inactive_listings_url || '').trim();
        const color = v === 0 ? '#198754' : '#b45309';
        const label = v.toLocaleString('en-US');
        if (!url) {
            return `<span class="ml-inactive-count" style="color:${color};">${label}</span>`;
        }
        const title = field === 'inactive_parent'
            ? 'Open inactive parent listings'
            : 'Open inactive child listings';
        return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="ml-inactive-count" style="color:${color};" title="${title}">${label}</a>`;
    }

    function mlChartRangeLabel(days) {
        return days === 0 ? 'Lifetime' : ('L' + days);
    }

    function mlFmtVal(v) {
        return Math.round(Number(v || 0)).toLocaleString('en-US');
    }

    function mlMetricName() {
        return mlCurrentMetricKey === 'missing_l_inv' ? 'Missing L > 0' : 'Missing L = 0';
    }

    function mlChartTitle(days) {
        const who = mlCurrentChartDisplayChannel === 'All' ? '' : (mlCurrentChartDisplayChannel + ' · ');
        return `${who}${mlMetricName()} (Rolling ${mlChartRangeLabel(days)}, California)`;
    }

    function showMlMetricChart(channel, cellValue, metric) {
        mlCurrentChartDisplayChannel = String(channel || 'All');
        mlCurrentChartChannel = mlCurrentChartDisplayChannel;
        mlCurrentMetricKey = metric === 'missing_l_inv' ? 'missing_l_inv' : 'missing_l';
        mlCurrentChartDays = 32;
        mlCurrentBadgeValue = (cellValue !== undefined && cellValue !== null && cellValue !== '' && !isNaN(cellValue))
            ? cellValue
            : null;

        $('#mlChartRangeSelect').val('32');
        $('#mlChartModalTitle').text(mlChartTitle(mlCurrentChartDays));

        const modal = new bootstrap.Modal(document.getElementById('mlMetricChartModal'));
        modal.show();
        loadMlMetricChart();
    }

    function loadMlMetricChart() {
        if (mlChartAjax) mlChartAjax.abort();

        $('#mlChartNoData').hide();
        $('#mlChartContainer').hide();
        $('#mlChartLoading').show();

        const params = {
            channel: mlCurrentChartChannel,
            metric: mlCurrentMetricKey,
            days: mlCurrentChartDays,
            scope: mlPageScope,
        };
        if (mlCurrentBadgeValue !== null) {
            params.badge_value = mlCurrentBadgeValue;
        }

        mlChartAjax = $.ajax({
            url: "{{ route('missing.listing.chart.data') }}",
            method: 'GET',
            data: params,
        }).done(function(response) {
            mlChartAjax = null;
            $('#mlChartLoading').hide();

            if (response.success !== false && response.data && response.data.length > 0) {
                $('#mlChartContainer').css('display', 'flex');
                renderMlMetricChart(response.data);
            } else {
                $('#mlChartNoData').show();
            }
        }).fail(function(_xhr, status) {
            mlChartAjax = null;
            if (status === 'abort') return;
            $('#mlChartLoading').hide();
            $('#mlChartNoData').show();
        });
    }

    function renderMlMetricChart(data) {
        const ctx = document.getElementById('mlMetricChart').getContext('2d');
        if (mlMetricChartInstance) mlMetricChartInstance.destroy();

        const labels = data.map(d => d.date);
        const values = data.map(d => Number(d.value || 0));
        const dataMin = Math.min(...values);
        const dataMax = Math.max(...values);
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yMin = Math.max(0, dataMin - range * 0.1);
        const yMax = dataMax + range * 0.1;

        const refRed = '#dc3545';
        const refGray = '#6c757d';
        const refGreen = '#198754';
        const highestEl = document.getElementById('mlChartHighest');
        const medianEl = document.getElementById('mlChartMedian');
        const lowestEl = document.getElementById('mlChartLowest');
        highestEl.textContent = mlFmtVal(dataMax);
        highestEl.style.color = dataMax === 0 ? refGreen : dataMax > 0 ? refRed : refGray;
        medianEl.textContent = mlFmtVal(median);
        medianEl.style.color = median === 0 ? refGreen : median > 0 ? refRed : refGray;
        lowestEl.textContent = mlFmtVal(dataMin);
        lowestEl.style.color = dataMin === 0 ? refGreen : dataMin > 0 ? refRed : refGray;

        const dotColors = values.map((v, i) => {
            if (i === 0) return refGray;
            return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
        });
        const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);

        const medianLinePlugin = {
            id: 'mlMedianLine',
            afterDraw(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const c = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                c.save();
                c.setLineDash([6, 4]);
                c.strokeStyle = refGray;
                c.lineWidth = 1.2;
                c.beginPath();
                c.moveTo(xScale.left, yPixel);
                c.lineTo(xScale.right, yPixel);
                c.stroke();
                c.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'mlValueLabels',
            afterDatasetsDraw(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const c = chart.ctx;
                c.save();
                c.font = 'bold 11px Inter, system-ui, sans-serif';
                c.textAlign = 'center';
                c.textBaseline = 'bottom';
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    c.fillStyle = labelColors[i];
                    c.fillText(mlFmtVal(val), point.x, point.y + ((i % 2 === 0) ? -10 : -20));
                });
                c.restore();
            }
        };

        mlMetricChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: mlMetricName(),
                    data: values,
                    backgroundColor: 'rgba(124,58,237,0.10)',
                    borderColor: '#8b5cf6',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointBorderWidth: 1.5,
                }],
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + mlFmtVal(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                    parts.push('vs Yesterday: ' + arrow + ' ' + mlFmtVal(Math.abs(diff)));
                                }
                                return parts;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: {
                            font: { size: 9 },
                            callback: function(value) { return mlFmtVal(value); }
                        }
                    },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } }
                    }
                }
            }
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 4000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function masterListingSlug(name) {
        const key = String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        const channels = { alibaba: 'alibaba', aliexpress: 'aliexpress', wayfair: 'wayfair', topdawg: 'topdawg', shein: 'shein' };
        return channels[key] || '';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // channel_master.logo is stored as a relative path under storage/
    // (e.g. channel_logos/amazon.png) — same as all-marketplace-master Img column.
    function mlLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function applyModeSelectColor($select, value) {
        $select.removeClass('ml-mode-empty ml-mode-auto ml-mode-csv ml-mode-manual ml-mode-semi');
        const v = String(value || '').trim();
        if (v === 'Auto') $select.addClass('ml-mode-auto');
        else if (v === 'CSV') $select.addClass('ml-mode-csv');
        else if (v === 'Manual') $select.addClass('ml-mode-manual');
        else if (v === 'Semi') $select.addClass('ml-mode-semi');
        else $select.addClass('ml-mode-empty');
    }

    function saveSellerPortal(cell) {
        const row = cell.getRow();
        const data = row.getData();
        const newValue = (cell.getValue() || '').trim();
        const oldValue = (cell.getOldValue() || '').trim();

        if (newValue === oldValue) return;

        if (newValue !== '') {
            try {
                new URL(newValue);
            } catch (_) {
                showToast('Please enter a valid URL (including https://).', 'error');
                cell.setValue(oldValue, true);
                return;
            }
        }

        $.ajax({
            url: "{{ route('missing.listing.seller.portal.save') }}",
            method: 'POST',
            data: { id: data.id, seller_portal: newValue },
            dataType: 'json',
        }).done(function(res) {
            if (res && res.success) {
                showToast(res.message || 'Seller Portal updated.', 'success');
            } else {
                showToast((res && res.message) || 'Update failed.', 'error');
                cell.setValue(oldValue, true);
            }
        }).fail(function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.seller_portal)
                    ? xhr.responseJSON.errors.seller_portal[0]
                    : 'Update failed.';
            showToast(msg, 'error');
            cell.setValue(oldValue, true);
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        $('#stat-missing-listing, #stat-missing-listing-inv').on('click', '.ml-overall-chart-dot', function(e) {
            e.stopPropagation();
            const inv = $(this).data('metric') === 'missing_l_inv';
            const badgeText = $(inv ? '#total-missing-listing-inv' : '#total-missing-listing').text().replace(/[,$%]/g, '').trim();
            const badgeValue = parseFloat(badgeText);
            showMlMetricChart('All', isNaN(badgeValue) ? null : badgeValue, inv ? 'missing_l_inv' : 'missing_l');
        });

        $('#mlChartRangeSelect').on('change', function() {
            const days = parseInt($(this).val(), 10);
            if (days === mlCurrentChartDays) return;
            mlCurrentChartDays = days;
            $('#mlChartModalTitle').text(mlChartTitle(days));
            loadMlMetricChart();
        });

        function shortFilter() {
            return {
                headerFilter: 'input',
                headerFilterFunc: function(headerValue, rowValue) {
                    const q = String(headerValue || '').trim().toLowerCase();
                    if (!q) return true;
                    return String(rowValue ?? '').toLowerCase().includes(q);
                },
            };
        }

        function shortList(values) {
            return {
                headerFilter: 'list',
                headerFilterParams: { values: values, clearable: true },
                headerFilterFunc: function(headerValue, rowValue) {
                    if (headerValue === undefined || headerValue === null || headerValue === '') return true;
                    return String(rowValue || '').trim().toLowerCase() === String(headerValue).trim().toLowerCase();
                },
            };
        }

        table = new Tabulator("#missing-listing-table", {
            ajaxURL: "{{ $c2c ? route('missing.listing.c2c.data') : route('missing.listing.data') }}",
            ajaxRequestTimeout: 20000,
            ajaxRequestFunc: function(url, _config, params) {
                return new Promise(function(resolve, reject) {
                    function attempt(n) {
                        $.ajax({
                            url: url,
                            data: params || {},
                            method: 'GET',
                            dataType: 'json',
                            timeout: 20000,
                        }).done(resolve).fail(function(xhr) {
                            if (n < 2) {
                                setTimeout(function() { attempt(n + 1); }, 1200);
                                return;
                            }
                            reject(xhr);
                        });
                    }
                    attempt(0);
                });
            },
            ajaxResponse: function(_url, _params, response) {
                if (response && response.success === false) {
                    return [];
                }
                const data = (response && response.data) ? response.data : [];
                updateStats(data, response && response.total_missing_l, response && response.total_missing_l_inv);
                paintColumnHeadCounts(data);
                if (response && response.partial && (window.__mlCountsRetries || 0) < 15) {
                    window.__mlCountsRetries = (window.__mlCountsRetries || 0) + 1;
                    setTimeout(function() {
                        if (table) {
                            table.replaceData();
                        }
                    }, 8000);
                }
                return data;
            },
            ajaxError: function() {
                const holder = document.querySelector('#missing-listing-table .tabulator-placeholder-contents');
                if (holder) {
                    holder.textContent = 'Could not load channels. Refresh the page.';
                }
            },
            dataProcessed: function() {
                paintColumnHeadCounts(table ? table.getData() : []);
            },
            layout: "fitColumns",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "channel", dir: "asc" }],
            placeholder: "Loading channels…",
            columns: [
                {
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 64,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="ml-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mlLogoSrc(logo);
                        const safeSrc = escapeHtml(src);
                        const safeAlt = escapeHtml(channel);
                        return `<img src="${safeSrc}" alt="${safeAlt}" class="ml-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                Object.assign({
                    title: "Channel",
                    field: "channel",
                    minWidth: 200,
                    widthGrow: 3,
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const row = cell.getRow().getData();
                        const url = (row.listing_url || '').trim();
                        if (!name) return '';
                        const safeName = escapeHtml(name);
                        const nameHtml = url
                            ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="ml-channel-listing-link" title="Open listing page">${safeName}</a>`
                            : safeName;
                        const importUrl = String(row.csv_import_url || '').trim();
                        const sheetSlug = row.allows_csv_upload ? (importUrl.split('/').filter(Boolean).pop() || '') : '';
                        const masterSlug = sheetSlug || masterListingSlug(name);
                        if (!row.allows_csv_upload && !masterSlug) return nameHtml;
                        const csvBtn = row.allows_csv_upload
                            ? `<button type="button" class="btn btn-sm btn-outline-warning ml-csv-upload-btn" data-import-url="${escapeHtml(importUrl)}" title="Upload current ${safeName} listings CSV and match to CP Master">Upload CSV</button>`
                            : '';
                        const masterBtn = masterSlug
                            ? `<button type="button" class="btn btn-sm btn-outline-primary ml-master-listing-btn" data-slug="${escapeHtml(masterSlug)}" data-label="${safeName}" title="Title, Description, Shopify images, weight / size, category, price and inventory from our masters for ${safeName} Missing L">Master Listing</button>`
                            : '';
                        return nameHtml + csvBtn + masterBtn;
                    },
                }, shortFilter()),
                Object.assign({
                    title: "Mode",
                    field: "listing_mode",
                    minWidth: 90,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "How this channel listing is updated: Auto, CSV, Manual, or Semi",
                    formatter: function(cell) {
                        const current = String(cell.getValue() || '').trim();
                        const id = cell.getRow().getData().id;
                        const modes = ['Auto', 'CSV', 'Manual', 'Semi'];
                        const cls = current === 'Auto' ? 'ml-mode-auto'
                            : current === 'CSV' ? 'ml-mode-csv'
                            : current === 'Manual' ? 'ml-mode-manual'
                            : current === 'Semi' ? 'ml-mode-semi'
                            : 'ml-mode-empty';
                        let html = `<select class="ml-mode-select ${cls}" data-id="${escapeHtml(String(id || ''))}">`;
                        html += `<option value=""${current === '' ? ' selected' : ''}>—</option>`;
                        modes.forEach(function(mode) {
                            html += `<option value="${mode}"${current === mode ? ' selected' : ''}>${mode}</option>`;
                        });
                        html += '</select>';
                        return html;
                    },
                }, shortList({ Auto: 'Auto', CSV: 'CSV', Manual: 'Manual', Semi: 'Semi' })),
                Object.assign({
                    title: "Type",
                    field: "type",
                    minWidth: 80,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "B2B, B2C, or C2C",
                    formatter: function(cell) {
                        const current = String(cell.getValue() || '').trim();
                        const id = cell.getRow().getData().id;
                        const types = ['B2B', 'B2C', 'C2C'];
                        let html = `<select class="ml-mode-select ml-type-select" data-id="${escapeHtml(String(id || ''))}">`;
                        html += `<option value=""${current === '' ? ' selected' : ''}>—</option>`;
                        types.forEach(function(type) {
                            html += `<option value="${type}"${current === type ? ' selected' : ''}>${type}</option>`;
                        });
                        html += '</select>';
                        return html;
                    },
                }, shortList({ B2B: 'B2B', B2C: 'B2C', C2C: 'C2C', Dropship: 'Dropship', Wholesale: 'Wholesale' })),
                Object.assign({
                    // Same setting as the Variation/Single column on Active Channel Master (/all-marketplace-master).
                    title: "Variation/Single",
                    field: "variation_single",
                    minWidth: 100,
                    hozAlign: "center",
                    headerSort: true,
                    headerTooltip: "Does this channel list products as Variation or Single? Shared with the Active Channel Master column.",
                    formatter: function(cell) {
                        const current = String(cell.getValue() || '').trim();
                        const channel = String(cell.getRow().getData().channel || '').trim();
                        const cls = current === 'Variation' ? 'is-variation' : (current === 'Single' ? 'is-single' : '');
                        return `<select class="variation-single-select ${cls}" data-channel="${escapeHtml(channel)}" aria-label="Variation or Single">`
                            + `<option value=""${current === '' ? ' selected' : ''}>-</option>`
                            + `<option value="Variation"${current === 'Variation' ? ' selected' : ''}>Variation</option>`
                            + `<option value="Single"${current === 'Single' ? ' selected' : ''}>Single</option>`
                            + '</select>';
                    },
                }, shortList({ Variation: 'Variation', Single: 'Single' })),
                Object.assign({
                    title: "Data Source",
                    field: "data_source",
                    minWidth: 80,
                    hozAlign: "center",
                    headerTooltip: "API = live listing-page counts; CSV = uploaded current listings vs CP Master; Sheet = From Sheet; Not connected = marketplace API credentials missing",
                    formatter: function(cell) {
                        const v = String(cell.getValue() || '').trim().toUpperCase();
                        if (v === 'API') {
                            return '<span class="ml-source-api">API</span>';
                        }
                        if (v === 'CSV') {
                            return '<span class="ml-source-csv" title="Current listings come from the uploaded sheet until an API exists">CSV</span>';
                        }
                        if (v === 'SHEET') {
                            return '<span class="ml-source-sheet">Sheet</span>';
                        }
                        if (v === 'OFFLINE') {
                            return '<span class="ml-source-sheet" title="Marketplace API is not connected">Not connected</span>';
                        }
                        return escapeHtml(v || '-');
                    },
                }, shortList({ API: 'API', CSV: 'CSV', Sheet: 'Sheet', Offline: 'Not connected' })),
                Object.assign({
                    title: "SKU",
                    field: "sku",
                    minWidth: 60,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Total non-PARENT SKUs from CP Master",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#0d6efd;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                }, shortFilter()),
                Object.assign({
                    title: "Listed",
                    field: "listed",
                    minWidth: 70,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SKUs that have a marketplace listing id — same as the Listed badge on the listing page",
                    formatter: function(cell) {
                        if (isSheetRow(cell.getRow().getData())) return fromSheetCell(cell.getRow().getData());
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#0d6efd;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => {
                            if (isSheetRow(row)) return sum;
                            return sum + Number(row.listed || 0);
                        }, 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                }, shortFilter()),
                mlMissingColumn('M = 0', 'missing_listing', '1', true,
                    'Missing L = 0: REQ SKUs not listed on this marketplace, including the ones with 0 inventory. Same number as the Missing L = 0 badge on the listing page.', shortFilter()),
                mlMissingColumn('M > 0', 'missing_listing_inv', 'inv', true,
                    'Missing L > 0: REQ SKUs not listed on this marketplace that have inventory (INV above 0). Same number as the Missing L > 0 badge on the listing page.', shortFilter()),
                {
                    title: "Inactive Listing",
                    headerHozAlign: "center",
                    headerTooltip: "Seller-platform inactive listings with inventory (0 Inv SKUs excluded): parent products vs child SKUs",
                    columns: [
                        Object.assign({
                            title: "Parent",
                            field: "inactive_parent",
                            minWidth: 70,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "Inactive parent listings on the seller platform, excluding 0 Inv SKUs",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'inactive_parent');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.inactive_parent || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        }, shortFilter()),
                        Object.assign({
                            title: "Child",
                            field: "inactive_child",
                            minWidth: 70,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "Inactive child / variation SKUs with inventory. 0 Inv is excluded. If the channel has no parent listings, this is the full in-stock seller-platform inactive count.",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'inactive_child');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.inactive_child || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        }, shortFilter()),
                    ],
                },
                Object.assign({
                    title: "Seller Portal",
                    field: "seller_portal",
                    width: 80,
                    maxWidth: 80,
                    hozAlign: "center",
                    editor: "input",
                    headerSort: false,
                    headerTooltip: "Double-click cell to edit",
                    cellDblClick: function(_e, cell) {
                        cell.edit();
                    },
                    formatter: function(cell) {
                        const v = (cell.getValue() || '').trim();
                        if (!v) {
                            return '<div class="ml-seller-portal-cell"><span class="ml-seller-portal-empty" title="Double-click to add">Add</span></div>';
                        }
                        const safe = escapeHtml(v);
                        return `<div class="ml-seller-portal-cell">
                                    <a href="${safe}" target="_blank" rel="noopener noreferrer" class="ml-seller-portal-link" title="${safe}" onclick="event.stopPropagation();">
                                        <i class="fa fa-link"></i>
                                    </a>
                                </div>`;
                    },
                    cellEdited: function(cell) {
                        saveSellerPortal(cell);
                    },
                }, shortFilter()),
            ],
        });

        $(document).on('mousedown click', '#missing-listing-table .ml-mode-select, #missing-listing-table .ml-type-select, #missing-listing-table .variation-single-select', function(e) {
            e.stopPropagation();
        });

        // Saves to channel_variation_types — the same table the Active Channel Master column uses.
        $(document).on('change', '#missing-listing-table .variation-single-select', function() {
            const $select = $(this);
            const channel = String($select.data('channel') || '');
            const next = String($select.val() || '');
            const row = table.getRows().find(function(r) { return String(r.getData().channel || '') === channel; });
            const prev = row ? String(row.getData().variation_single || '') : '';
            if (next === prev) return;
            $select.prop('disabled', true);
            $.ajax({
                url: "{{ route('channels.master.variation.type') }}",
                method: 'POST',
                data: { channel: channel, value: next },
                dataType: 'json',
            }).done(function() {
                $select.removeClass('is-variation is-single').addClass(next === 'Variation' ? 'is-variation' : (next === 'Single' ? 'is-single' : ''));
                if (row) row.update({ variation_single: next });
                showToast(channel + ': ' + (next || 'cleared'), 'success');
            }).fail(function(xhr) {
                $select.val(prev);
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {}).flat().join(' '))) || 'Could not save Variation/Single.';
                showToast(msg, 'error');
            }).always(function() { $select.prop('disabled', false); });
        });

        $(document).on('change', '#missing-listing-table .ml-mode-select', function() {
            const $select = $(this);
            const id = $select.data('id');
            const newValue = String($select.val() || '').trim();
            const row = table.getRows().find(function(r) {
                return Number(r.getData().id) === Number(id);
            });
            const oldValue = row ? String(row.getData().listing_mode || '').trim() : '';
            applyModeSelectColor($select, newValue);
            if (newValue === oldValue) {
                return;
            }
            $.ajax({
                url: "{{ route('missing.listing.listing.mode.save') }}",
                method: 'POST',
                data: { id: id, listing_mode: newValue },
                dataType: 'json',
            }).done(function(res) {
                if (res && res.success) {
                    if (row) {
                        row.update({ listing_mode: newValue || null });
                    }
                    showToast(res.message || 'Mode updated.', 'success');
                } else {
                    showToast((res && res.message) || 'Update failed.', 'error');
                    $select.val(oldValue);
                    applyModeSelectColor($select, oldValue);
                }
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Update failed.';
                showToast(msg, 'error');
                $select.val(oldValue);
                applyModeSelectColor($select, oldValue);
            });
        });

        $(document).on('change', '#missing-listing-table .ml-type-select', function() {
            const $select = $(this);
            const id = $select.data('id');
            const newValue = String($select.val() || '').trim();
            const row = table.getRows().find(function(r) {
                return Number(r.getData().id) === Number(id);
            });
            const oldValue = row ? String(row.getData().type || '').trim() : '';
            if (newValue === '' || newValue === oldValue) {
                $select.val(oldValue);
                return;
            }
            $.ajax({
                url: "{{ route('missing.listing.listing.mode.save') }}",
                method: 'POST',
                data: { id: id, type: newValue },
                dataType: 'json',
            }).done(function(res) {
                if (res && res.success) {
                    if (row) row.update({ type: newValue });
                    showToast(res.message || 'Type updated.', 'success');
                } else {
                    showToast((res && res.message) || 'Update failed.', 'error');
                    $select.val(oldValue);
                }
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Update failed.';
                showToast(msg, 'error');
                $select.val(oldValue);
            });
        });

        $(document).on('click', '#missing-listing-table .ml-master-listing-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.MasterListing) window.MasterListing.open(String($(this).data('slug') || ''), String($(this).data('label') || ''));
        });

        let mlSheetImportUrl = '';
        $(document).on('click', '#missing-listing-table .ml-csv-upload-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            mlSheetImportUrl = String($(this).data('import-url') || '').trim();
            const input = document.getElementById('ml-sheet-csv-input');
            if (input) input.click();
        });

        $('#ml-sheet-csv-input').on('change', function() {
            const file = this.files && this.files[0];
            const url = mlSheetImportUrl;
            this.value = '';
            if (!file || !url) return;
            const body = new FormData();
            body.append('file', file);
            body.append('_token', '{{ csrf_token() }}');
            showToast('Uploading sheet…', 'success');
            $.ajax({
                url: url,
                method: 'POST',
                data: body,
                processData: false,
                contentType: false,
                dataType: 'json',
            }).done(function(res) {
                showToast((res && res.message) || 'Sheet imported.', 'success');
                location.reload();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                    ? (xhr.responseJSON.message || xhr.responseJSON.error)
                    : 'Upload failed.';
                showToast(msg, 'error');
            });
        });

        // Auto refresh: re-read the cached table every 10 minutes while the tab is open (cheap, served from the
        // daily cache) and when the tab is shown again after a while, so the numbers stay current without a reload.
        (function() {
            const EVERY_MS = 10 * 60 * 1000;
            let last = Date.now();
            function reload() {
                last = Date.now();
                if (table) table.replaceData();
            }
            setInterval(function() {
                if (!document.hidden) reload();
            }, EVERY_MS);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && Date.now() - last > EVERY_MS) reload();
            });
        })();

        $('#missing-listing-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter();
                return;
            }
            table.setFilter(function(row) {
                return String(row.channel || '').toLowerCase().includes(q);
            });
        });
    });
</script>
@unless($c2c)
<script>
(function () {
    const startUrl = @json(route('listing.refresh.all.start'));
    const statusUrl = @json(route('listing.refresh.all.status'));
    const csrf = @json(csrf_token());
    let timer = null;
    let lastDone = -1;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function ago(iso) {
        if (!iso) return '—';
        const t = new Date(iso).getTime();
        if (isNaN(t)) return '—';
        const m = Math.round((Date.now() - t) / 60000);
        if (m < 1) return 'just now';
        if (m < 60) return m + ' min ago';
        if (m < 2880) return Math.round(m / 60) + ' h ago';
        return Math.round(m / 1440) + ' days ago';
    }

    function render(res) {
        const s = res.summary || {};
        const finished = (s.done || 0) + (s.failed || 0);
        const line = s.running > 0
            ? 'Refreshing ' + finished + ' / ' + s.total + ' marketplaces…'
            : (s.total ? 'Refreshed ' + (s.done || 0) + ' of ' + s.total + (s.failed ? ', ' + s.failed + ' failed' : '')
                + (s.stale ? ', ' + s.stale + ' not refreshed in 26 h' : '') : '');
        $('#ml-refresh-all-status').text(line + (res.computed_at ? ' · counts built ' + ago(res.computed_at) : ''));
        $('#ml-refresh-all-summary').text(
            'Total Missing L ' + (res.total_missing_l != null ? Number(res.total_missing_l).toLocaleString('en-US') : '—')
            + ' · counts built ' + ago(res.computed_at)
            + ' · running ' + (s.running || 0) + ', done ' + (s.done || 0) + ', failed ' + (s.failed || 0)
            + ', never run ' + (s.never || 0) + ', older than 26 h ' + (s.stale || 0));
        const colors = { done: 'success', failed: 'danger', running: 'primary', idle: 'secondary' };
        $('#ml-refresh-all-rows').html((res.rows || []).map(function (r) {
            const detail = (r.failed_steps && r.failed_steps.length) ? r.failed_steps.join(' | ') : r.message;
            return '<tr><td>' + esc(r.slug) + '</td>'
                + '<td><span class="badge bg-' + (colors[r.state] || 'secondary') + '">' + esc(r.state === 'idle' ? 'never run' : r.state) + '</span></td>'
                + '<td class="text-end">' + (r.missing_l != null ? Number(r.missing_l).toLocaleString('en-US') : '—') + '</td>'
                + '<td>' + esc(ago(r.finished_at)) + '</td>'
                + '<td style="max-width:360px;white-space:normal;">' + esc(detail) + '</td></tr>';
        }).join('') || '<tr><td colspan="5" class="text-muted p-3">No marketplaces.</td></tr>');
    }

    function poll() {
        $.getJSON(statusUrl).done(function (res) {
            render(res);
            const s = res.summary || {};
            const finished = (s.done || 0) + (s.failed || 0);
            // Each finished marketplace patches its row in the cached payload: reload the table when that grows.
            if (finished !== lastDone && typeof table !== 'undefined' && table) {
                lastDone = finished;
                table.replaceData();
            }
            if ((s.running || 0) > 0) {
                timer = setTimeout(poll, 5000);
            } else {
                timer = null;
                $('#ml-refresh-all-btn').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Refresh all Missing L now');
            }
        }).fail(function () {
            timer = setTimeout(poll, 8000);
        });
    }

    $('#ml-refresh-all-btn').on('click', function () {
        const btn = $(this);
        if (!confirm('Recount Missing L for ALL marketplaces now from the listing page data? Nothing is fetched from the marketplaces. This takes a few minutes and runs in the background.')) return;
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Queuing…');
        $.ajax({ url: startUrl, method: 'POST', dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } })
            .done(function (res) {
                showToast('Queued ' + (res.queued || 0) + ' marketplace refresh(es)' + (res.already_running ? ', ' + res.already_running + ' already running' : '') + '.', 'success');
                render(res);
                lastDone = -1;
                btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…');
                if (timer) clearTimeout(timer);
                timer = setTimeout(poll, 4000);
            })
            .fail(function (xhr) {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Refresh all Missing L now');
                showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not start the refresh.', 'error');
            });
    });

    $('#mlRefreshAllModal').on('show.bs.modal', function () { poll(); });

    // Show the last-run state without a click, and resume the progress display if a refresh is already running.
    $.getJSON(statusUrl).done(function (res) {
        render(res);
        if (((res.summary || {}).running || 0) > 0) {
            $('#ml-refresh-all-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…');
            timer = setTimeout(poll, 5000);
        }
    });
})();
</script>
@endunless
@include('partials.master-listing-form')
@endsection
