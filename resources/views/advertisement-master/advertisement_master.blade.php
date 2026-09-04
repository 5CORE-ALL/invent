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
        .adm-stat-badge--views  { background: #0284c7; }
        .adm-stat-badge--missing { background: #dc2626; }
        .adm-missing-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .adm-missing-count { font-weight: 700; color: #0f172a; }
        .adm-missing-count.is-alert { color: #dc2626; }
        .adm-missing-ads-link {
            color: #2563eb;
            font-size: 11px;
            line-height: 1;
            text-decoration: none;
        }
        .adm-missing-ads-link:hover { color: #1d4ed8; }
        .adm-missing-header {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
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

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell:first-child,
        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell:nth-child(2) {
            text-align: left;
        }

        #advertisement-master-wrap .tabulator-row.adm-sum-row,
        #advertisement-master-wrap .tabulator-row.tabulator-row-even.adm-sum-row,
        #advertisement-master-wrap .tabulator-row.tabulator-row-odd.adm-sum-row {
            background: #fff3cd;
        }

        #advertisement-master-wrap .tabulator-row.adm-sum-row .tabulator-cell {
            background: #fff3cd;
        }

        #advertisement-master-wrap .tabulator-row.adm-sum-row:hover .tabulator-cell {
            background: #ffe69c;
        }

        #advertisement-master-wrap .tabulator-row.adm-sum-row .tabulator-cell.adm-metric-cell:hover {
            background: #ffd966;
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

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell.adm-edit-cell {
            cursor: pointer;
        }

        .adm-edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            min-width: 26px;
            height: 26px;
            padding: 0;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            line-height: 1;
        }
        .adm-edit-btn:hover {
            color: #0d6efd;
            border-color: #0d6efd;
            background: #eff6ff;
        }
        .adm-edit-btn svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        .adm-rn-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
            margin: 0;
            vertical-align: middle;
        }
        .adm-rn-switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        .adm-rn-slider {
            position: absolute;
            inset: 0;
            background: #dc2626;
            border-radius: 999px;
            cursor: pointer;
            transition: background .15s ease;
        }
        .adm-rn-slider:before {
            content: "";
            position: absolute;
            height: 14px;
            width: 14px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0,0,0,.2);
            transition: transform .15s ease;
        }
        .adm-rn-switch input:checked + .adm-rn-slider {
            background: #16a34a;
        }
        .adm-rn-switch input:checked + .adm-rn-slider:before {
            transform: translateX(16px);
        }
        .adm-rn-switch input:disabled + .adm-rn-slider {
            opacity: 0.55;
            cursor: wait;
        }

        .adm-type-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            vertical-align: -3px;
        }
        .adm-type-icon svg {
            width: 15px;
            height: 15px;
            display: block;
        }
        .adm-type-sep {
            color: #94a3b8;
            padding: 0 1px;
        }

        /* Badge trend chart modal — full-screen width, pinned to top
           (same look & sizing as /shopify-ads-master). */
        #adm-loading, #adm-error {
            font-size: 13px;
            padding: 14px 8px;
        }
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
                            <span class="adm-stat-badge adm-stat-badge--missing adm-badge-link" data-metric="missing_ads" data-label="Missing" title="Sum of missing ads — click for trend">MISSING: <span id="adm-badge-missing">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--active" title="Active (running / enabled) campaigns across all channels">ACTIVE: <span id="adm-badge-active">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--spend adm-badge-link" data-metric="spend" data-label="Spend" title="Click for trend">SPEND: <span id="adm-badge-spend">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--clicks adm-badge-link" data-metric="clicks" data-label="Clicks" title="Click for trend">CLICKS: <span id="adm-badge-clicks">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--views" title="Sum of listing views (Channel Master) across visible parent rows">VIEWS: <span id="adm-badge-views">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sold adm-badge-link" data-metric="sold" data-label="Sold" title="Click for trend">SOLD: <span id="adm-badge-sold">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sales adm-badge-link" data-metric="sales" data-label="Ads Sales" title="Click for trend">ADS SALES: <span id="adm-badge-sales">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--cvr adm-badge-link" data-metric="cvr" data-label="CVR" title="Click for trend">CVR: <span id="adm-badge-cvr">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--acos adm-badge-link" data-metric="acos" data-label="ACOS" title="Click for trend">ACOS: <span id="adm-badge-acos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--tcos adm-badge-link" data-metric="tcos" data-label="Tcos" title="Click for trend">TCOS: <span id="adm-badge-tcos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--ssales adm-badge-link" data-metric="ssales" data-label="Total Sales" title="Combined Amz + eBay + eBay 2 + eBay 3 + Shopify + TikTok 1 L30 store sales — click for trend">TOTAL SALES: <span id="adm-badge-ssales">$0</span></span>
                        </div>
                        <div class="d-flex align-items-center gap-1" style="flex-shrink:0;">
                            <select id="adm-filter-kind" class="form-select form-select-sm" style="width:110px;" aria-label="Showing type rows" title="Showing type rows">
                                <option value="">All</option>
                                <option value="total">Total</option>
                                <option value="types" selected>Types</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-1" style="flex-shrink:0;">
                            <select id="adm-filter-rn" class="form-select form-select-sm" style="width:100px;" aria-label="Filter REQ/ NR" title="Filter REQ/ NR">
                                <option value="" selected>REQ/ NR</option>
                                <option value="REQ">REQ</option>
                                <option value="NR">NR</option>
                            </select>
                        </div>
                        <div class="input-group input-group-sm" style="width:180px; flex-shrink:0;">
                            <span class="input-group-text" title="Quick Search Channel"><i class="fa fa-search"></i></span>
                            <input type="text" id="adm-filter-channel" class="form-control"
                                list="adm-filter-channel-list"
                                placeholder="Channel…"
                                autocomplete="off"
                                aria-label="Quick Search Channel"
                                title="Quick Search Channel">
                            <datalist id="adm-filter-channel-list"></datalist>
                        </div>
                        <input type="text" id="adm-search" class="form-control form-control-sm"
                            placeholder="Search type…" style="width:180px; flex-shrink:0;">
                        <button type="button" id="adm-trends" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-chart-line"></i> Trends
                        </button>
                        <button type="button" id="adm-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="adm-add-row" class="btn btn-sm btn-primary fw-bold" title="Add Channel and Type" style="min-width:32px;">+</button>
                    </div>

                    <div id="advertisement-master-wrap">
                        <div id="adm-loading" class="text-muted">Loading channel metrics…</div>
                        <div id="adm-error" class="text-danger d-none"></div>
                        <div id="advertisement-master-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Trend chart — same modal + graph format as /all-marketplace-master --}}
    <div class="modal fade p-0" id="admTrendsModal" tabindex="-1" aria-labelledby="admTrendsLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;" id="admTrendsLabel">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="adm-trend-title">Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="adm-trend-channel" class="form-select form-select-sm bg-white" style="width:auto; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="__total__">All channels</option>
                        </select>
                        <select id="adm-trend-days" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div style="height: 28vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="adm-trend-canvas"></canvas>
                            <p class="text-center text-muted small mb-0 d-none" id="adm-trend-empty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div id="adm-trend-ref-panel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="adm-trend-highest" style="font-size: 13px; font-weight: 700; color: #dc3545;">—</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="adm-trend-median" style="font-size: 13px; font-weight: 700; color: #6c757d;">—</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="adm-trend-lowest" style="font-size: 13px; font-weight: 700; color: #198754;">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="admEditModal" tabindex="-1" aria-labelledby="admEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="admEditLabel">Edit Channel &amp; Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="adm-edit-form">
                    <div class="modal-body">
                        <input type="hidden" id="adm-edit-key" name="channel_key">
                        <p class="text-muted small mb-3" id="adm-edit-original"></p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="adm-edit-group">Channel</label>
                            <input type="text" class="form-control" id="adm-edit-group" name="group_name" list="adm-edit-group-list" maxlength="80" required autocomplete="off">
                            <datalist id="adm-edit-group-list"></datalist>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="adm-edit-channel">Type</label>
                            <input type="text" class="form-control" id="adm-edit-channel" name="channel_name" maxlength="191" required autocomplete="off">
                        </div>
                        <p class="text-danger small mb-0 mt-2 d-none" id="adm-edit-error"></p>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="adm-edit-save">Save</button>
                    </div>
                </form>
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
            let admAllRows = [];

            // Metrics where a HIGHER value is worse (cost side): an up move
            // reads red, a down move green — the opposite of clicks/sold/etc.
            const ADM_INVERTED_METRICS = { spend: true, acos: true, tcos: true, missing_ads: true };

            // Day-over-day trend dot on every metric cell. Green = improvement,
            // red = decline. No grey / empty state — if there is no prior day
            // or the value is unchanged, color vs zero (spend / ACOS invert).
            function admTrendDot(cell) {
                const field = cell.getField();
                const data  = cell.getRow().getData() || {};
                const value = Number(cell.getValue() || 0);
                const inverted = !!ADM_INVERTED_METRICS[field];
                let dir = (data.trend || {})[field];
                if (!dir || dir === 'flat') {
                    dir = value > 0 ? 'up' : 'down';
                }
                const improved = inverted ? (dir === 'down') : (dir === 'up');
                const color = improved ? '#28a745' : '#dc3545';
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

            function viewsFormatter(cell) {
                const row = cell.getRow().getData() || {};
                if (!row.is_sum_row && !row.is_group_total) {
                    return '<span class="text-muted">-</span>';
                }
                return intFormatter(cell);
            }

            function tSalesFormatter(cell) {
                const row = cell.getRow().getData() || {};
                if (!row.has_t_sales) {
                    return '<span class="text-muted">-</span>';
                }
                return wholeMoneyFormatter(cell);
            }

            function missingAdsTitleFormatter() {
                return '<span class="adm-missing-header">Missing <i class="fa fa-external-link" title="Arrow opens the missing page in a new tab"></i></span>';
            }

            function missingAdsFormatter(cell) {
                const row = cell.getRow().getData() || {};
                if (!row.has_missing_ads) {
                    return '<span class="text-muted">-</span>';
                }
                const n = Math.round(Number(row.missing_ads || 0));
                const href = String(row.missing_ads_href || '').trim();
                const num = '<span class="adm-missing-count' + (n > 0 ? ' is-alert' : '') + '">' + n.toLocaleString() + '</span>';
                const arrow = href
                    ? '<a class="adm-missing-ads-link" href="' + admEsc(href) + '" target="_blank" rel="noopener" title="Open missing page"><i class="fa fa-external-link"></i></a>'
                    : '';
                return '<span class="adm-missing-cell">' + num + arrow + '</span>' + admTrendDot(cell);
            }

            function missingAdsCellClick(e, cell) {
                if (e.target.closest && e.target.closest('a.adm-missing-ads-link')) {
                    return;
                }
                const row = cell.getRow().getData() || {};
                if (!row.has_missing_ads) {
                    return;
                }
                admCellChart(e, cell);
            }

            function percentFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return value.toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1,
                }) + '%' + admTrendDot(cell);
            }

            function updateBadges(rows) {
                let spend = 0, clicks = 0, sold = 0, sales = 0, active = 0, views = 0, missing = 0;
                (admAllRows.length ? admAllRows : rows).forEach(function (r) {
                    if (r && r.has_missing_ads && r.missing_ads_href) {
                        missing += Number(r.missing_ads || 0);
                    }
                });
                rows.forEach(function (r) {
                    if (r && r.is_sub_row) return;
                    spend  += Number(r.spend  || 0);
                    clicks += Number(r.clicks || 0);
                    sold   += Number(r.sold   || 0);
                    sales  += Number(r.sales  || 0);
                    active += Number(r.active || 0);
                    views  += Number(r.views  || 0);
                });
                const cvr  = clicks > 0 ? (sold  / clicks) * 100 : 0;
                const acos = sales  > 0 ? (spend / sales)  * 100 : (spend > 0 ? 100 : 0);
                const tcos = admSSales > 0 ? (spend / admSSales) * 100 : (spend > 0 ? 100 : 0);

                const missingEl = document.getElementById('adm-badge-missing');
                if (missingEl) missingEl.textContent = Math.round(missing).toLocaleString();
                const activeEl = document.getElementById('adm-badge-active');
                if (activeEl) activeEl.textContent = Math.round(active).toLocaleString();
                document.getElementById('adm-badge-spend').textContent  = '$' + Math.round(spend).toLocaleString();
                document.getElementById('adm-badge-clicks').textContent = Math.round(clicks).toLocaleString();
                const viewsEl = document.getElementById('adm-badge-views');
                if (viewsEl) viewsEl.textContent = Math.round(views).toLocaleString();
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
                'Amz': "{{ route('amazon.ads.all') }}",
                'Amz · KW': "{{ route('amazon.ads.all') }}?search=KW",
                'Amz · PT': "{{ route('amazon.ads.all') }}?search=PT",
                'Amz · HL': "{{ route('amazon.ads.all') }}?source=sb_reports",
                'eBay Total': "{{ route('ebay.campaign.ads') }}",
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
                'TikTok Total': "{{ route('tiktok1.ads.raw') }}",
                'TikTok 1': "{{ route('tiktok1.ads.raw') }}",
                'Shopify · TikTok Video Ads': "{{ Route::has('tiktok.ads.master') ? route('tiktok.ads.master') : route('tiktok.video.ads') }}",
                'Temu': "{{ Route::has('temu.ads') ? route('temu.ads') : url('/temu1-data') }}",
                'Temu Total': "{{ Route::has('temu.ads') ? route('temu.ads') : url('/temu1-data') }}",
                'Temu 2': "{{ Route::has('temu2.ads') ? route('temu2.ads') : url('/temu2-decrease') }}",
                'Temu 2 Total': "{{ Route::has('temu2.ads') ? route('temu2.ads') : url('/temu2-decrease') }}",
                'TikTok 2': "{{ Route::has('tiktok.gmv.ads.raw') ? route('tiktok.gmv.ads.raw') : route('tiktok2.pricing') }}",
                'TikTok 2 Total': "{{ Route::has('tiktok.gmv.ads.raw') ? route('tiktok.gmv.ads.raw') : route('tiktok2.pricing') }}",
            };

            function admChannelGroup(row, inherited) {
                if (inherited) return inherited;
                const name = String((row && row.channel) || '');
                const mp = String((row && row.marketplace) || '').toLowerCase();
                if (mp === 'amazon' || /^(amazon|amz)\b/i.test(name)) return 'Amazon';
                if (mp.indexOf('ebay') === 0 || /^ebay\b/i.test(name)) return 'eBay';
                if (mp === 'shopify' || /^shopify\b/i.test(name)) return 'Shopify';
                if (mp === 'tiktok' || /^tiktok\b/i.test(name)) return 'TikTok';
                return name || 'Other';
            }

            function admEsc(value) {
                return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
                });
            }

            function flattenAdmRows(rows, inheritedGroup) {
                const flat = [];
                (rows || []).forEach(function (r) {
                    if (!r) return;
                    const group = r.channel_group || admChannelGroup(r, inheritedGroup);
                    const children = Array.isArray(r._children) ? r._children : [];
                    const copy = Object.assign({}, r);
                    delete copy._children;
                    copy.channel_key = copy.channel_key || copy.channel;
                    copy.channel_group = group;
                    copy.nr_req = String(copy.nr_req || 'REQ').toUpperCase() === 'NR' ? 'NR' : 'REQ';
                    copy.acos = Number(copy.acos || 0);
                    copy.views = Number(copy.views || 0);
                    copy.t_sales = Number(copy.t_sales || 0);
                    copy.has_t_sales = !!copy.has_t_sales;
                    copy.missing_ads = Number(copy.missing_ads || 0);
                    copy.has_missing_ads = !!copy.has_missing_ads;
                    copy.is_sum_row = children.length > 0 || !!copy.is_group_total || !!copy.is_sum_row;
                    if (copy.is_sum_row) {
                        const typeName = String(copy.channel || '').trim();
                        if (typeName && !/\bTotal$/i.test(typeName)) {
                            copy.channel = typeName + ' Total';
                        }
                    }
                    flat.push(copy);
                    if (children.length) {
                        Array.prototype.push.apply(flat, flattenAdmRows(children, group));
                    }
                });
                return flat;
            }

            function groupFormatter(cell) {
                const name = cell.getValue() || '';
                return '<span style="font-weight:600;color:#1e3a8a;">' + admEsc(name) + '</span>';
            }

            function admTypeIcon(kind, title) {
                const svgs = {
                    facebook: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05v-2.66c0-3.02 1.79-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.95h-1.51c-1.49 0-1.95.93-1.95 1.89v2.28h3.32l-.53 3.49h-2.79V24C19.61 23.09 24 18.1 24 12.07z"/></svg>',
                    instagram: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#E4405F" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5.2A4.8 4.8 0 1 0 16.8 12 4.8 4.8 0 0 0 12 7.2zm0 1.8A3 3 0 1 1 9 12a3 3 0 0 1 3-3zm5.35-2.85a1.15 1.15 0 1 0 1.15 1.15 1.15 1.15 0 0 0-1.15-1.15z"/></svg>',
                    youtube: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#FF0000" d="M23.5 6.2a3.05 3.05 0 0 0-2.15-2.16C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.35.44A3.05 3.05 0 0 0 .5 6.2 31.9 31.9 0 0 0 0 12a31.9 31.9 0 0 0 .5 5.8 3.05 3.05 0 0 0 2.15 2.16C4.5 20.4 12 20.4 12 20.4s7.5 0 9.35-.44a3.05 3.05 0 0 0 2.15-2.16A31.9 31.9 0 0 0 24 12a31.9 31.9 0 0 0-.5-5.8z"/><path fill="#fff" d="M9.75 15.5v-7l6.2 3.5z"/></svg>',
                    google: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.5c-.3 1.5-1.1 2.8-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.7z"/><path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3c-1.1.7-2.5 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.4v3.1C3.4 21.4 7.4 24 12 24z"/><path fill="#FBBC05" d="M5.4 14.4c-.2-.7-.4-1.5-.4-2.4s.1-1.7.4-2.4V6.5H1.4C.5 8.2 0 10.1 0 12s.5 3.8 1.4 5.5l4-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4C17.9 1.2 15.2 0 12 0 7.4 0 3.4 2.6 1.4 6.5l4 3.1C6.3 6.8 8.9 4.8 12 4.8z"/></svg>',
                };
                return '<span class="adm-type-icon" title="' + admEsc(title) + '">' + (svgs[kind] || '') + '</span>';
            }

            function admIsGoogleGType(name) {
                return /\bg\s+(video|carousal|carousel)\b/i.test(String(name || ''));
            }

            function admFormatTypeLabel(name) {
                const full = String(name || '');
                return full.split(' · ').map(function (part) {
                    const low = part.trim().toLowerCase();
                    if (low === 'facebook') {
                        return admIsGoogleGType(full)
                            ? admTypeIcon('google', 'Google')
                            : admTypeIcon('facebook', 'Facebook');
                    }
                    if (low === 'instagram') return admTypeIcon('instagram', 'Instagram');
                    if (low === 'youtube' || low === 'youtube ads' || low.indexOf('youtube') === 0) {
                        return admTypeIcon('youtube', part.trim());
                    }
                    if (low === 'google' || low.indexOf('google ') === 0) {
                        const rest = part.trim().replace(/^google\s+/i, '');
                        return rest
                            ? admTypeIcon('google', 'Google') + '&nbsp;' + admEsc(rest)
                            : admTypeIcon('google', 'Google');
                    }
                    return admEsc(part);
                }).join('<span class="adm-type-sep"> · </span>');
            }

            function channelFormatter(cell) {
                const name = cell.getValue() || '';
                const row  = cell.getRow().getData() || {};
                const url  = row.href || channelLinks[row.channel_key || ''] || channelLinks[name];
                const isChild = !!row.is_sub_row;
                const weight = isChild ? 'font-weight:500;' : 'font-weight:600;';
                const style = weight + 'color:#1e3a8a;';
                const label = admFormatTypeLabel(name);
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:#1e3a8a;text-decoration:none;' + style + '">' + label + '</a>';
                }
                return '<span style="' + style + '">' + label + '</span>';
            }

            function editFormatter() {
                return '<button type="button" class="adm-edit-btn" title="Edit Channel and Type" aria-label="Edit Channel and Type">'
                    + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                    + '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>'
                    + '</svg></button>';
            }

            function rnFormatter(cell) {
                const row = cell.getRow().getData() || {};
                const isReq = String(row.nr_req || 'REQ').toUpperCase() !== 'NR';
                const key = admEsc(row.channel_key || row.channel || '');
                return '<label class="adm-rn-switch" title="' + (isReq ? 'R (REQ)' : 'N (NR)') + '">'
                    + '<input type="checkbox" class="adm-rn-input"'
                    + (isReq ? ' checked' : '')
                    + ' data-key="' + key + '"'
                    + ' aria-label="R/N">'
                    + '<span class="adm-rn-slider"></span>'
                    + '</label>';
            }

            const dataUrl = "{{ route('advertisement.master.data') }}";
            const loadingEl = document.getElementById('adm-loading');
            const errorEl = document.getElementById('adm-error');

            function showAdmError(message) {
                if (loadingEl) loadingEl.classList.add('d-none');
                if (errorEl) {
                    errorEl.textContent = message || 'Failed to load Advertisement Master data. Try Refresh.';
                    errorEl.classList.remove('d-none');
                }
            }

            function showAdmLoading() {
                if (errorEl) errorEl.classList.add('d-none');
                if (loadingEl) loadingEl.classList.remove('d-none');
            }

            const table = new Tabulator('#advertisement-master-table', {
                ajaxURL: dataUrl,
                ajaxRequestTimeout: 180000,
                placeholder: 'Loading channel metrics…',
                ajaxResponse: function (url, params, response) {
                    if (loadingEl) loadingEl.classList.add('d-none');
                    if (errorEl) errorEl.classList.add('d-none');
                    if (!response || Number(response.status) === 500) {
                        showAdmError((response && response.message) || 'Failed to load Advertisement Master data.');
                        return [];
                    }
                    const rows = flattenAdmRows(response.data || []);
                    admAllRows = rows;
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
                    fillAdmChannelFilter(rows);
                    rows.sort(function (a, b) {
                        return Number(b.acos || 0) - Number(a.acos || 0);
                    });
                    setTimeout(applyAdmFilters, 0);
                    return rows;
                },
                ajaxError: function () {
                    showAdmError('Advertisement Master data request failed or timed out. Try Refresh.');
                },
                layout: 'fitColumns',
                headerSort: true,
                initialSort: [{ column: 'acos', dir: 'desc' }],
                rowFormatter: function (row) {
                    const data = row.getData() || {};
                    const el = row.getElement();
                    if (data.is_sum_row) {
                        el.classList.add('adm-sum-row');
                    } else if (data.is_sub_row) {
                        el.classList.add('adm-child-row');
                    }
                },
                columns: [
                    { title: 'Channel', field: 'channel_group', minWidth: 90, width: 100, hozAlign: 'left', headerSort: true, formatter: groupFormatter },
                    { title: 'Type', field: 'channel', minWidth: 150, hozAlign: 'center', headerHozAlign: 'center', headerSort: true, formatter: channelFormatter },
                    { title: 'R/N', field: 'nr_req', width: 64, minWidth: 64, hozAlign: 'center', headerHozAlign: 'center', headerSort: false, formatter: rnFormatter, cssClass: 'adm-rn-cell' },
                    { title: 'Missing', field: 'missing_ads', width: 110, minWidth: 100, hozAlign: 'center', headerHozAlign: 'center', headerSort: true, headerSortStartingDir: 'desc', titleFormatter: missingAdsTitleFormatter, formatter: missingAdsFormatter, cssClass: 'adm-metric-cell', cellClick: missingAdsCellClick },
                    { title: 'ACTIVE', field: 'active', hozAlign: 'center', formatter: intFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'SPEND', field: 'spend', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'CLICKS', field: 'clicks', hozAlign: 'center', formatter: intFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'VIEWS', field: 'views', hozAlign: 'center', headerHozAlign: 'center', formatter: viewsFormatter, headerSort: true, headerSortStartingDir: 'desc' },
                    { title: 'SOLD', field: 'sold', hozAlign: 'center', formatter: intFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'ADS SALES', field: 'sales', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'T Sales', field: 't_sales', hozAlign: 'center', headerHozAlign: 'center', formatter: tSalesFormatter, headerSort: true, headerSortStartingDir: 'desc' },
                    { title: 'CVR', field: 'cvr', hozAlign: 'center', formatter: percentFormatter, headerSort: true, headerSortStartingDir: 'desc', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: 'ACOS', field: 'acos', hozAlign: 'center', formatter: percentFormatter, headerSort: true, headerSortStartingDir: 'desc', sorter: 'number', cssClass: 'adm-metric-cell', cellClick: admCellChart },
                    { title: '', field: '_edit', width: 42, minWidth: 42, hozAlign: 'center', headerSort: false, formatter: editFormatter, cssClass: 'adm-edit-cell', cellClick: openAdmEditFromCell },
                ],
            });

            table.on('dataFiltered', function () {
                updateBadges(table.getData('active') || table.getData());
            });

            function fillAdmChannelFilter(rows) {
                const list = document.getElementById('adm-filter-channel-list');
                if (!list) return;
                const seen = {};
                (rows || []).forEach(function (r) {
                    const g = String((r && r.channel_group) || '').trim();
                    if (g) seen[g] = true;
                });
                const names = Object.keys(seen).sort(function (a, b) {
                    return a.localeCompare(b);
                });
                list.innerHTML = '<option value="All">'
                    + names.map(function (n) {
                        return '<option value="' + admEsc(n) + '">';
                    }).join('');
            }

            function applyAdmFilters() {
                const kind = String((document.getElementById('adm-filter-kind') || {}).value || '').trim();
                const channelRaw = String((document.getElementById('adm-filter-channel') || {}).value || '').trim();
                const channelQ = channelRaw.toLowerCase();
                const channel = (channelQ && channelQ !== 'all') ? channelQ : '';
                const typeQ = String((document.getElementById('adm-search') || {}).value || '').trim().toLowerCase();
                const rnRaw = String((document.getElementById('adm-filter-rn') || {}).value || '').trim().toUpperCase();
                const rn = (rnRaw === 'REQ' || rnRaw === 'NR') ? rnRaw : '';
                if (!kind && !channel && !typeQ && !rn) {
                    table.clearFilter();
                    return;
                }
                table.setFilter(function (data) {
                    if (kind === 'total' && !data.is_sum_row) return false;
                    if (kind === 'types' && data.is_sum_row) return false;
                    if (channel && String(data.channel_group || '').toLowerCase().indexOf(channel) === -1) return false;
                    if (typeQ && String(data.channel || '').toLowerCase().indexOf(typeQ) === -1) return false;
                    if (rn && String(data.nr_req || 'REQ').toUpperCase() !== rn) return false;
                    return true;
                });
            }

            document.getElementById('adm-filter-kind').addEventListener('change', applyAdmFilters);
            document.getElementById('adm-filter-channel').addEventListener('input', applyAdmFilters);
            document.getElementById('adm-filter-channel').addEventListener('change', applyAdmFilters);
            document.getElementById('adm-search').addEventListener('input', applyAdmFilters);
            document.getElementById('adm-filter-rn').addEventListener('change', applyAdmFilters);

            document.getElementById('adm-refresh').addEventListener('click', function () {
                document.getElementById('adm-search').value = '';
                const kindSel = document.getElementById('adm-filter-kind');
                if (kindSel) kindSel.value = 'types';
                const chInput = document.getElementById('adm-filter-channel');
                if (chInput) chInput.value = '';
                const rnSel = document.getElementById('adm-filter-rn');
                if (rnSel) rnSel.value = '';
                table.clearFilter();
                showAdmLoading();
                table.setData(dataUrl);
            });

            const admEditSaveUrl = "{{ route('advertisement.master.label.save') }}";
            const admNrSaveUrl = "{{ route('advertisement.master.nr.save') }}";
            const admCsrf = document.querySelector('meta[name="csrf-token"]');
            let admEditRow = null;

            document.getElementById('advertisement-master-wrap').addEventListener('click', function (e) {
                if (e.target && (e.target.closest && e.target.closest('.adm-rn-switch'))) {
                    e.stopPropagation();
                }
            }, true);

            document.getElementById('advertisement-master-wrap').addEventListener('change', function (e) {
                const input = e.target;
                if (!input || !input.classList || !input.classList.contains('adm-rn-input')) return;
                const key = String(input.getAttribute('data-key') || '').trim();
                const nr = input.checked ? 'REQ' : 'NR';
                if (!key) {
                    input.checked = !input.checked;
                    return;
                }
                input.disabled = true;
                const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
                if (admCsrf && admCsrf.content) headers['X-CSRF-TOKEN'] = admCsrf.content;
                fetch(admNrSaveUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ channel_key: key, nr_req: nr }),
                }).then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                .then(function (result) {
                    if (!result.ok || !result.body || Number(result.body.status) !== 200) {
                        input.checked = !input.checked;
                        return;
                    }
                    const rows = table.getRows();
                    rows.forEach(function (row) {
                        const data = row.getData() || {};
                        if (String(data.channel_key || '') === key) {
                            row.update({ nr_req: nr });
                        }
                    });
                    applyAdmFilters();
                }).catch(function () {
                    input.checked = !input.checked;
                }).finally(function () {
                    input.disabled = false;
                    const label = input.closest('.adm-rn-switch');
                    if (label) label.title = input.checked ? 'R (REQ)' : 'N (NR)';
                });
            });

            function admFillGroupList() {
                const list = document.getElementById('adm-edit-group-list');
                if (!list) return;
                const seen = {};
                ['Amazon', 'eBay', 'Shopify', 'TikTok'].forEach(function (g) { seen[g] = true; });
                (table.getData() || []).forEach(function (r) {
                    const g = String((r && r.channel_group) || '').trim();
                    if (g) seen[g] = true;
                });
                list.innerHTML = Object.keys(seen).map(function (g) {
                    return '<option value="' + admEsc(g) + '">';
                }).join('');
            }

            function admShowEditModal() {
                const modalEl = document.getElementById('admEditModal');
                if (!modalEl) return;
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    return;
                }
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
            }

            function admHideEditModal() {
                const modalEl = document.getElementById('admEditModal');
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
            }

            function openAdmEditModal(data) {
                admEditRow = data || {};
                const isAdd = !!admEditRow._is_add;
                const key = isAdd ? '' : (admEditRow.channel_key || admEditRow.channel || '');
                const title = document.getElementById('admEditLabel');
                if (title) title.textContent = isAdd ? 'Add Channel & Type' : 'Edit Channel & Type';
                document.getElementById('adm-edit-key').value = key;
                document.getElementById('adm-edit-group').value = isAdd ? '' : (admEditRow.channel_group || '');
                document.getElementById('adm-edit-channel').value = isAdd ? '' : (admEditRow.channel || '');
                const orig = document.getElementById('adm-edit-original');
                if (orig) {
                    orig.textContent = (!isAdd && key && key.indexOf('custom:') !== 0) ? ('Original: ' + key) : '';
                    orig.classList.toggle('d-none', orig.textContent === '');
                }
                const err = document.getElementById('adm-edit-error');
                if (err) { err.textContent = ''; err.classList.add('d-none'); }
                admFillGroupList();
                admShowEditModal();
                setTimeout(function () {
                    const input = document.getElementById('adm-edit-group');
                    if (input) input.focus();
                }, 150);
            }

            function openAdmEditFromCell(e, cell) {
                if (e && e.stopPropagation) e.stopPropagation();
                openAdmEditModal(cell.getRow().getData() || {});
            }

            document.getElementById('adm-add-row').addEventListener('click', function () {
                openAdmEditModal({ _is_add: true });
            });

            function admInsertCustomRow(payload) {
                const group = payload.channel_group;
                const rows = table.getRows();
                let lastMatch = null;
                rows.forEach(function (row) {
                    const d = row.getData() || {};
                    if (String(d.channel_group || '') === group) lastMatch = row;
                });
                const next = lastMatch && lastMatch.getNextRow ? lastMatch.getNextRow() : null;
                table.addRow(payload, next || false);
            }

            document.getElementById('adm-edit-form').addEventListener('submit', function (e) {
                e.preventDefault();
                const key = String(document.getElementById('adm-edit-key').value || '').trim();
                const group = String(document.getElementById('adm-edit-group').value || '').trim();
                let channel = String(document.getElementById('adm-edit-channel').value || '').trim();
                const err = document.getElementById('adm-edit-error');
                const saveBtn = document.getElementById('adm-edit-save');
                const isAdd = !!(admEditRow && admEditRow._is_add);
                if (!group || !channel) {
                    if (err) { err.textContent = 'Channel and Type are required.'; err.classList.remove('d-none'); }
                    return;
                }
                if (admEditRow && admEditRow.is_sum_row && !/\bTotal$/i.test(channel)) {
                    channel = channel + ' Total';
                    document.getElementById('adm-edit-channel').value = channel;
                }
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
                fetch(admEditSaveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': admCsrf ? admCsrf.getAttribute('content') : '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        channel_key: key,
                        group_name: group,
                        channel_name: channel,
                    }),
                })
                    .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.body && res.body.message) || 'Could not save.');
                        }
                        const savedKey = (res.body && res.body.channel_key) || key;
                        if (isAdd) {
                            admInsertCustomRow({
                                channel_group: group,
                                channel: channel,
                                channel_key: savedKey,
                                is_custom: true,
                                is_sub_row: true,
                                spend: 0,
                                clicks: 0,
                                sold: 0,
                                sales: 0,
                                cvr: 0,
                                acos: 0,
                                tcos: 0,
                                active: 0,
                            });
                        } else {
                            const oldGroup = admEditRow ? String(admEditRow.channel_group || '') : '';
                            const isSum = !!(admEditRow && admEditRow.is_sum_row);
                            table.getRows().forEach(function (row) {
                                const d = row.getData() || {};
                                const rowKey = d.channel_key || d.channel;
                                if (rowKey === key || rowKey === savedKey) {
                                    row.update({ channel: channel, channel_group: group, channel_key: savedKey });
                                } else if (isSum && oldGroup && d.channel_group === oldGroup) {
                                    row.update({ channel_group: group });
                                }
                            });
                        }
                        buildAdmChannelOptions(table.getData());
                        admHideEditModal();
                    })
                    .catch(function (ex) {
                        if (err) { err.textContent = ex.message || 'Could not save.'; err.classList.remove('d-none'); }
                    })
                    .finally(function () {
                        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
                    });
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
                const options = [];
                (rows || []).forEach(function (r) {
                    if (!r) return;
                    const key = r.channel_key || r.channel;
                    const label = r.channel || key;
                    if (key) options.push({ key: key, label: label });
                });
                const current = sel.value;
                sel.innerHTML = '<option value="__total__">All channels</option>'
                    + options.map(function (o) {
                        return '<option value="' + admEsc(o.key) + '">' + admEsc(o.label) + '</option>';
                    }).join('');
                if (Array.prototype.slice.call(sel.options).some(function (o) { return o.value === current; })) sel.value = current;
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
                const data = cell.getRow().getData() || {};
                const channel = data.channel_key || data.channel || '__total__';
                const labels  = { spend: 'Spend', clicks: 'Clicks', sold: 'Sold', sales: 'Ads Sales', active: 'Active', cvr: 'CVR', acos: 'ACOS', missing_ads: 'Missing' };
                openAdmChart(metric, labels[metric] || metric.toUpperCase(), channel);
            }

            document.querySelectorAll('.adm-badge-link').forEach(function (el) {
                el.addEventListener('click', function () {
                    openAdmChart(this.dataset.metric, this.dataset.label || this.dataset.metric.toUpperCase());
                });
            });
            var admTrendsBtn = document.getElementById('adm-trends');
            if (admTrendsBtn) admTrendsBtn.addEventListener('click', function () {
                openAdmChart('spend', 'Spend');
            });
            var admTrendChannel = document.getElementById('adm-trend-channel');
            if (admTrendChannel) admTrendChannel.addEventListener('change', function () {
                admSetTrendTitle();
                renderAdmChart();
            });
            var admTrendDays = document.getElementById('adm-trend-days');
            if (admTrendDays) admTrendDays.addEventListener('change', loadAdmHistory);

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
                    chSel.value = Array.prototype.slice.call(chSel.options).some(function (o) { return o.value === wanted; }) ? wanted : '__total__';
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
                const rawSeries = admSeriesFor(admTrendCache, channel, metric);
                const isMissing = labels.map(function (_, i) {
                    const v = rawSeries[i];
                    return v === null || v === undefined || v === '';
                });
                const values = labels.map(function (_, i) {
                    if (isMissing[i]) return 0;
                    const n = Number(rawSeries[i]);
                    return Number.isFinite(n) ? n : 0;
                });
                const numericValues = values.filter(function (_, i) { return !isMissing[i]; });

                if (admTrendChart) { admTrendChart.destroy(); admTrendChart = null; }
                ['adm-trend-highest', 'adm-trend-median', 'adm-trend-lowest'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                if (!labels.length || !numericValues.length) {
                    canvas.style.display = 'none';
                    if (emptyEl) emptyEl.classList.remove('d-none');
                    return;
                }
                if (typeof Chart === 'undefined') {
                    canvas.style.display = 'none';
                    if (emptyEl) { emptyEl.textContent = 'Chart library failed to load. Check your connection and refresh.'; emptyEl.classList.remove('d-none'); }
                    return;
                }
                canvas.style.display = '';
                if (emptyEl) emptyEl.classList.add('d-none');

                const dataMin = Math.min.apply(null, numericValues);
                const dataMax = Math.max.apply(null, numericValues);
                const sorted  = numericValues.slice().sort(function (a, b) { return a - b; });
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = (dataMax - dataMin) || 1;
                const yPad  = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
                const yMin  = Math.max(0, dataMin - range * 0.12);
                const yMax  = dataMax + yPad;

                const refGray = '#6c757d';
                const isInverted = (metric === 'acos' || metric === 'tcos' || metric === 'spend' || metric === 'missing_ads');
                const prevNumeric = function (i) {
                    for (let j = i - 1; j >= 0; j--) {
                        if (!isMissing[j]) return values[j];
                    }
                    return null;
                };
                const dotColors = values.map(function (v, i) {
                    if (isMissing[i]) return refGray;
                    const prev = prevNumeric(i);
                    if (prev === null) return refGray;
                    if (isInverted) {
                        return v < prev ? '#28a745' : v > prev ? '#dc3545' : refGray;
                    }
                    return v > prev ? '#28a745' : v < prev ? '#dc3545' : refGray;
                });

                let maxIdx = 0, minIdx = 0;
                values.forEach(function (v, i) {
                    if (isMissing[i]) return;
                    if (isMissing[maxIdx] || v >= values[maxIdx]) maxIdx = i;
                    if (isMissing[minIdx] || v <= values[minIdx]) minIdx = i;
                });
                const highestEl = document.getElementById('adm-trend-highest');
                const medianEl  = document.getElementById('adm-trend-median');
                const lowestEl  = document.getElementById('adm-trend-lowest');
                if (highestEl) {
                    highestEl.textContent = fmtAdmValue(metric, dataMax);
                    highestEl.style.color = dotColors[maxIdx] || refGray;
                    if (highestEl.previousElementSibling) highestEl.previousElementSibling.style.color = highestEl.style.color;
                }
                if (medianEl) {
                    medianEl.textContent = fmtAdmValue(metric, median);
                    medianEl.style.color = refGray;
                    if (medianEl.previousElementSibling) medianEl.previousElementSibling.style.color = refGray;
                }
                if (lowestEl) {
                    lowestEl.textContent = fmtAdmValue(metric, dataMin);
                    lowestEl.style.color = dotColors[minIdx] || refGray;
                    if (lowestEl.previousElementSibling) lowestEl.previousElementSibling.style.color = lowestEl.style.color;
                }

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
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDraw: function (chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        const lastIdx = meta.data.length - 1;
                        const anchors = [];
                        ctx.save();
                        ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        meta.data.forEach(function (point, i) {
                            if (isMissing[i] || !point || dataset.data[i] === null) return;
                            let offsetY = (i % 2 === 0) ? -12 : -26;
                            if (i === lastIdx) offsetY = (lastIdx % 2 === 0) ? -26 : -12;
                            if (anchors.length) {
                                const prev = anchors[anchors.length - 1];
                                if (Math.abs(point.x - prev.x) < 36 && Math.abs((point.y + offsetY) - prev.y) < 14) {
                                    offsetY = (offsetY === -12) ? -28 : -12;
                                }
                            }
                            anchors.push({ x: point.x, y: point.y + offsetY });
                            ctx.save();
                            ctx.fillStyle = dotColors[i] || refGray;
                            ctx.translate(point.x, point.y + offsetY);
                            ctx.rotate(-Math.PI / 5);
                            ctx.fillText(fmtAdmValue(metric, dataset.data[i]), 2, 0);
                            ctx.restore();
                        });
                        ctx.restore();
                    }
                };

                const plotValues = values.map(function (v, i) { return isMissing[i] ? null : v; });

                admTrendChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: admTrendLabel,
                            data: plotValues,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true,
                            spanGaps: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointBorderWidth: 1.5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        clip: false,
                        layout: { padding: { top: 44, left: 4, right: 22, bottom: 8 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 },
                                bodyFont: { size: 10 },
                                padding: 6,
                                callbacks: {
                                    label: function (ctx) {
                                        const parts = ['Value: ' + fmtAdmValue(metric, ctx.raw)];
                                        const idx = ctx.dataIndex;
                                        const prev = prevNumeric(idx);
                                        if (prev !== null) {
                                            const diff = ctx.raw - prev;
                                            const arrow = diff < 0 ? '▼' : (diff > 0 ? '▲' : '▬');
                                            parts.push('vs prev: ' + arrow + ' ' + fmtAdmValue(metric, Math.abs(diff)));
                                        }
                                        return parts;
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: { font: { size: 9 }, callback: function (v) { return fmtAdmValue(metric, v); } },
                            },
                            x: {
                                ticks: {
                                    maxRotation: 60,
                                    minRotation: 60,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: { size: 8 },
                                },
                            },
                        },
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                });
            }

            var admTrendsModal = document.getElementById('admTrendsModal');
            if (admTrendsModal) admTrendsModal.addEventListener('shown.bs.modal', function () {
                if (admTrendChart) admTrendChart.resize();
            });
        });
    </script>
@endsection
