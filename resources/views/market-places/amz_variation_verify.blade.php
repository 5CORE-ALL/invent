@extends('layouts.vertical', ['title' => 'Amz Ads Variation Verification'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #amz-vv-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #amz-vv-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #amz-vv-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important; display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #amz-vv-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-vv-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-vv-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }

        #amz-vv-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #amz-vv-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #amz-vv-wrap { overflow-x: auto; overflow-y: visible; }

        #amz-vv-wrap .tabulator-row.amz-vv-parent-row,
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            color: #664d03;
        }
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row:hover,
        #amz-vv-wrap .tabulator-row.amz-vv-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        .amz-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .amz-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .amz-stat-badge--parents { background: #4c7ed8; }
        .amz-stat-badge--children { background: #8b5cf6; }
        .amz-stat-badge--listed { background: #16a34a; }
        .amz-stat-badge--campaigns { background: #ea580c; }
        .amz-stat-badge--issues { background: #dc2626; cursor: pointer; }
        .amz-stat-badge--issues.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #dc2626; }
        .amz-stat-badge--missing { background: #dc2626; cursor: pointer; }
        .amz-stat-badge--missing.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #dc2626; }
        .amz-stat-badge--missing-inv { background: #b91c1c; cursor: pointer; }
        .amz-stat-badge--missing-inv.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #b91c1c; }
        .amz-stat-badge--extra { background: #2563eb; cursor: pointer; }
        .amz-stat-badge--extra.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2563eb; }
        .amz-stat-badge--archived-extra { background: #6b7280; cursor: pointer; }
        .amz-stat-badge--archived-extra.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #6b7280; }
        .amz-stat-badge--issues .amz-vv-trend-dot {
            display: inline-block; width: 6px; height: 6px; border-radius: 50%;
            margin-left: 4px; vertical-align: middle; cursor: pointer; flex-shrink: 0;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.65);
            background: rgba(255,255,255,0.35);
        }
        .amz-stat-badge--issues .amz-vv-trend-dot.up { background: #ff6b6b; }   /* more issues → red */
        .amz-stat-badge--issues .amz-vv-trend-dot.down { background: #00ff88; } /* fewer issues → green */
        .amz-stat-badge--issues .amz-vv-trend-dot.flat { background: #adb5bd; } /* same → gray */
        .amz-stat-badge--issues .amz-vv-trend-dot.none { background: rgba(255,255,255,0.35); }
        #amzVvHistoryTableWrap {
            max-height: 28vh; overflow: auto; margin-top: 8px;
            border: 1px solid #e9ecef; border-radius: 6px;
        }
        #amzVvHistoryTable th, #amzVvHistoryTable td {
            font-size: 12px; padding: 4px 8px; white-space: nowrap;
        }
        #amzVvHistoryTable .hist-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%;
            margin-right: 4px; vertical-align: middle;
        }
        #amzVvHistoryTable .hist-dot.up { background: #dc3545; }
        #amzVvHistoryTable .hist-dot.down { background: #28a745; }
        #amzVvHistoryTable .hist-dot.flat { background: #adb5bd; }
        .amz-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .amz-raw-icon-btn > i { font-size: 14px; }

        .amz-vv-avail-yes { color: #16a34a; font-weight: 700; }
        .amz-vv-avail-no { color: #dc2626; font-weight: 700; }
        .amz-vv-avail-na { color: #94a3b8; }
        .amz-vv-avail-partial { color: #ea580c; font-weight: 700; }
        .amz-vv-match-ok {
            color: #16a34a; font-size: 16px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .amz-vv-match-bad {
            color: #dc2626; font-size: 12px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        }
        .amz-vv-match-na { color: #94a3b8; }
        .amz-vv-ad-added { color: #16a34a; font-weight: 700; }
        .amz-vv-ad-missing { color: #dc2626; font-weight: 700; }
        .amz-vv-ad-over { color: #ea580c; font-weight: 700; }
        .amz-vv-ad-na { color: #94a3b8; }
        .amz-vv-ad-wrap { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.25; }
        .amz-vv-ad-skus {
            display: inline-block; font-size: 10px; font-weight: 700; max-width: 240px;
            white-space: normal; word-break: break-word; padding: 2px 6px; border-radius: 4px;
            line-height: 1.35; text-align: left;
        }
        .amz-vv-ad-skus--missing { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        .amz-vv-ad-skus--over { color: #9a3412; background: #ffedd5; border: 1px solid #fdba74; }
        .amz-vv-ad-skus--extra { color: #1e3a8a; background: #dbeafe; border: 1px solid #93c5fd; }
        .amz-vv-ad-skus--archived { color: #374151; background: #e5e7eb; border: 1px solid #9ca3af; }
        .amz-vv-ad-extra { color: #2563eb; font-weight: 700; }
        .amz-vv-ad-archived { color: #6b7280; font-weight: 700; }
        .amz-vv-error {
            display: block; font-size: 11px; font-weight: 700; color: #991b1b;
            background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px;
            padding: 4px 6px; text-align: left; line-height: 1.35;
            white-space: normal; word-break: break-word; max-width: 320px;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Ads Variation Verification',
        'sub_title'  => 'Amz',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Toolbar (same pattern as /amazon-ads/all) --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span class="amz-stat-badge amz-stat-badge--parents" title="Parents from CP Master">PARENTS:<span id="amz-vv-badge-parents">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--children" title="Required child SKUs from CP Master">REQUIRED:<span id="amz-vv-badge-children">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--listed" title="Amz listings cache">LISTED:<span id="amz-vv-badge-listed">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--campaigns" title="SP L30 campaigns">CAMPAIGNS:<span id="amz-vv-badge-campaigns">0</span></span>
                            <span class="amz-stat-badge amz-stat-badge--issues" id="amz-vv-badge-issues-wrap" title="Parents with KW/PT missing or extra ads — click badge to filter. Click dot for rolling history.">
                                VARIATIONS ISSUES:<span id="amz-vv-badge-issues">0</span>
                                <span class="amz-vv-trend-dot none" id="amz-vv-issues-trend-dot" title="Rolling history" role="button" tabindex="0"></span>
                            </span>
                            <span class="amz-stat-badge amz-stat-badge--missing" id="amz-vv-badge-missing-wrap" title="All missing SKUs (KW or PT) — click to filter" role="button" tabindex="0">
                                MISSING:<span id="amz-vv-badge-missing">0</span>
                            </span>
                            <span class="amz-stat-badge amz-stat-badge--missing-inv" id="amz-vv-badge-missing-inv-wrap" title="Missing SKUs with INV &gt; 0 — click to filter" role="button" tabindex="0">
                                MISSING INV&gt;0:<span id="amz-vv-badge-missing-inv">0</span>
                            </span>
                            <span class="amz-stat-badge amz-stat-badge--extra" id="amz-vv-badge-extra-wrap" title="Extra ad SKUs not in CP Master (not archived) — click to filter" role="button" tabindex="0">
                                EXTRA:<span id="amz-vv-badge-extra">0</span>
                            </span>
                            <span class="amz-stat-badge amz-stat-badge--archived-extra" id="amz-vv-badge-archived-extra-wrap" title="Extra ads already ARCHIVED — click to filter" role="button" tabindex="0">
                                ARCHIVED:<span id="amz-vv-badge-archived-extra">0</span>
                            </span>
                        </div>
                        <span id="amz-vv-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-vv-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="amz-vv-refresh-btn" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh from CP Master" aria-label="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amz-vv-pull-btn" class="btn btn-sm btn-warning text-dark" title="Pull Amz listings (SP-API)">
                            <i class="fas fa-cloud-download-alt me-1"></i> Pull Listings
                        </button>
                        <button type="button" id="amz-vv-add-missing-btn" class="btn btn-sm btn-success" title="Add all Missing SKUs to their PARENT KW/PT campaigns (INV 0 allowed)">
                            <i class="fas fa-plus me-1"></i> Add Missing SKU
                        </button>
                        <button type="button" id="amz-vv-archive-extra-btn" class="btn btn-sm btn-outline-danger" title="Archive Extra ads campaigns that are not already ARCHIVED">
                            <i class="fas fa-archive me-1"></i> Archive Extras
                        </button>
                        <span class="text-muted small" id="amz-vv-status-line"></span>
                    </div>

                    {{-- Search strip + table --}}
                    <div id="amz-vv-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-vv-search" class="form-control" placeholder="Search Parent..." autocomplete="off" aria-label="Search Parent" maxlength="100">
                            <span id="amz-vv-source-label" class="badge bg-dark text-nowrap">CP Master</span>
                        </div>
                        <div id="amz-variation-verify-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- VARIATIONS ISSUES rolling history (red / green / gray dots) --}}
    <div class="modal fade p-0" id="amzVvIssuesChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzVvChartModalTitle">Variations Issues — Rolling History</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzVvChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="amzVvChartContainer" style="height: 22vh; display: none; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="amzVvIssuesChart"></canvas>
                        </div>
                        <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #dc3545;">Highest</div>
                                <div id="amzVvChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6c757d;">Median</div>
                                <div id="amzVvChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #198754;">Lowest</div>
                                <div id="amzVvChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzVvChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="amzVvChartNoData" class="text-center py-3" style="display: none;">
                        <p class="text-muted small mb-0">No daily snapshots yet. Open this page on separate days to build history.</p>
                    </div>
                    <div id="amzVvHistoryTableWrap" style="display: none;">
                        <table class="table table-sm table-striped table-hover mb-0" id="amzVvHistoryTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Value</th>
                                    <th class="text-end">Δ vs prior</th>
                                </tr>
                            </thead>
                            <tbody id="amzVvHistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        let amzVvTable = null;
        /** null | 'issues' | 'missing' | 'missing_inv' | 'extra' | 'archived_extra' */
        let amzVvActiveFilter = null;
        let amzVvIssuesLiveCount = 0;
        let amzVvIssuesPrevDay = null;
        let amzVvChartInstance = null;
        let amzVvChartAjax = null;
        let amzVvChartDays = 30;

        function amzVvEscapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function amzVvDash(val) {
            if (val === null || val === undefined || val === '') {
                return '<span class="text-muted">--</span>';
            }
            return val;
        }

        function amzVvFmtNum(v) {
            const n = Number(v);
            if (!isFinite(n)) return '—';
            return Math.round(n).toLocaleString('en-US');
        }

        /** Lower is better: up=red, down=green, flat=gray */
        function amzVvTrendClass(curr, prev) {
            if (!isFinite(curr) || !isFinite(prev)) return 'none';
            const diff = curr - prev;
            if (diff > 0.05) return 'up';
            if (diff < -0.05) return 'down';
            return 'flat';
        }

        function amzVvApplyIssuesTrendDot() {
            const $dot = $('#amz-vv-issues-trend-dot');
            if (!$dot.length) return;
            const curr = amzVvIssuesLiveCount;
            const prev = amzVvIssuesPrevDay;
            if (!isFinite(curr) || prev == null || !isFinite(prev)) {
                $dot.attr('class', 'amz-vv-trend-dot none')
                    .attr('title', 'Click for rolling history (no prior day yet)');
                return;
            }
            const cls = amzVvTrendClass(curr, prev);
            const tip = (cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same'))
                + ' vs prior day (' + amzVvFmtNum(prev) + ' → ' + amzVvFmtNum(curr)
                + '). Click for rolling history. Lower is better.';
            $dot.attr('class', 'amz-vv-trend-dot ' + cls).attr('title', tip);
        }

        function amzVvUpdateMeta(meta) {
            if (!meta) return;
            const parents = meta.required_parent_count || 0;
            const children = meta.required_child_count || 0;
            const listed = meta.listings_count || 0;
            const campaigns = meta.ads_count || 0;
            const issues = meta.variations_issues_count || 0;

            amzVvIssuesLiveCount = issues;
            amzVvIssuesPrevDay = (meta.variations_issues_prev_day !== null
                && meta.variations_issues_prev_day !== undefined
                && meta.variations_issues_prev_day !== '')
                ? Number(meta.variations_issues_prev_day)
                : null;

            $('#amz-vv-badge-parents').text(parents.toLocaleString());
            $('#amz-vv-badge-children').text(children.toLocaleString());
            $('#amz-vv-badge-listed').text(listed.toLocaleString());
            $('#amz-vv-badge-campaigns').text(campaigns.toLocaleString());
            $('#amz-vv-badge-issues').text(issues.toLocaleString());
            $('#amz-vv-badge-missing').text((meta.missing_sku_count || 0).toLocaleString());
            $('#amz-vv-badge-missing-inv').text((meta.missing_inv_gt0_count || 0).toLocaleString());
            $('#amz-vv-badge-extra').text((meta.extra_sku_count || 0).toLocaleString());
            $('#amz-vv-badge-archived-extra').text((meta.archived_extra_sku_count || 0).toLocaleString());
            amzVvApplyIssuesTrendDot();

            const parts = [];
            if (meta.required_refreshed_at) parts.push('CP Master · ' + meta.required_refreshed_at);
            if (meta.last_pulled_at) parts.push('Listings · ' + meta.last_pulled_at);
            if (meta.ads_source) parts.push(meta.ads_source);
            $('#amz-vv-status-line').text(parts.join(' · '));
            $('#amz-vv-source-label').text(meta.has_listings_cache ? 'CP Master + Listings' : 'CP Master');
        }

        function amzVvOpenIssuesChart() {
            amzVvChartDays = parseInt($('#amzVvChartRangeSelect').val(), 10);
            if (!isFinite(amzVvChartDays)) amzVvChartDays = 30;
            $('#amzVvChartModalTitle').text(
                'Variations Issues — Rolling History'
                + (amzVvChartDays === 0 ? ' (Lifetime)' : ' (L' + amzVvChartDays + ')')
            );
            const modalEl = document.getElementById('amzVvIssuesChartModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                $(modalEl).modal('show');
            }
            amzVvLoadIssuesChart();
        }

        function amzVvRenderHistoryTable(data) {
            const $wrap = $('#amzVvHistoryTableWrap');
            const $tbody = $('#amzVvHistoryTableBody');
            $tbody.empty();
            if (!data || !data.length) {
                $wrap.hide();
                return;
            }
            const rows = data.slice().reverse();
            rows.forEach(function (row, idx) {
                const older = rows[idx + 1];
                const curr = parseFloat(row.value);
                const prev = older ? parseFloat(older.value) : NaN;
                let deltaHtml = '<span class="text-muted">—</span>';
                if (isFinite(curr) && isFinite(prev)) {
                    const d = curr - prev;
                    const cls = amzVvTrendClass(curr, prev);
                    const sign = d > 0 ? '+' : '';
                    deltaHtml = '<span class="hist-dot ' + cls + '"></span>'
                        + '<span style="font-weight:600;">' + sign + Math.round(d).toLocaleString('en-US') + '</span>';
                }
                $tbody.append(
                    '<tr><td>' + (row.full_date || row.date || '') + '</td>'
                    + '<td class="text-end fw-semibold">' + amzVvFmtNum(curr) + '</td>'
                    + '<td class="text-end">' + deltaHtml + '</td></tr>'
                );
            });
            $wrap.show();
        }

        function amzVvRenderIssuesChart(data) {
            const ctx = document.getElementById('amzVvIssuesChart').getContext('2d');
            if (amzVvChartInstance) amzVvChartInstance.destroy();

            const labels = data.map(function (d) { return d.date; });
            const values = data.map(function (d) { return Number(d.value || 0); });
            const dataMin = Math.min.apply(null, values);
            const dataMax = Math.max.apply(null, values);
            const sorted = values.slice().sort(function (a, b) { return a - b; });
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;

            $('#amzVvChartHighest').text(amzVvFmtNum(dataMax)).css('color', dataMax === 0 ? '#198754' : '#dc3545');
            $('#amzVvChartMedian').text(amzVvFmtNum(median));
            $('#amzVvChartLowest').text(amzVvFmtNum(dataMin)).css('color', '#198754');

            // Lower is better: up=red, down=green, flat=gray
            const dotColors = values.map(function (v, i) {
                if (i === 0) return '#6c757d';
                if (v > values[i - 1]) return '#dc3545';
                if (v < values[i - 1]) return '#28a745';
                return '#6c757d';
            });

            amzVvChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Variations Issues',
                        data: values,
                        backgroundColor: 'rgba(220,38,38,0.08)',
                        borderColor: '#adb5bd',
                        borderWidth: 1.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const idx = context.dataIndex;
                                    const parts = ['Issues: ' + amzVvFmtNum(context.raw)];
                                    if (idx > 0) {
                                        const diff = context.raw - values[idx - 1];
                                        const sign = diff > 0 ? '+' : '';
                                        parts.push('Δ: ' + sign + Math.round(diff).toLocaleString('en-US'));
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
                            ticks: { callback: function (v) { return amzVvFmtNum(v); }, font: { size: 9 } }
                        },
                        x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } }
                    }
                }
            });
        }

        function amzVvLoadIssuesChart() {
            if (amzVvChartAjax) amzVvChartAjax.abort();
            $('#amzVvChartNoData').hide();
            $('#amzVvChartContainer').hide();
            $('#amzVvHistoryTableWrap').hide();
            $('#amzVvChartLoading').show();

            amzVvChartAjax = $.ajax({
                url: '{{ route("amz.variation.verify.chart") }}',
                method: 'GET',
                data: {
                    days: amzVvChartDays,
                    badge_value: amzVvIssuesLiveCount
                },
                success: function (resp) {
                    amzVvChartAjax = null;
                    $('#amzVvChartLoading').hide();
                    if (resp && resp.success !== false && resp.data && resp.data.length > 0) {
                        $('#amzVvChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                        amzVvRenderIssuesChart(resp.data);
                        amzVvRenderHistoryTable(resp.data);
                    } else {
                        $('#amzVvChartNoData').show();
                    }
                },
                error: function (xhr, status) {
                    amzVvChartAjax = null;
                    if (status === 'abort') return;
                    $('#amzVvChartLoading').hide();
                    $('#amzVvChartNoData').show();
                }
            });
        }

        function amzVvUpdateRowCount() {
            if (!amzVvTable) return;
            const shown = amzVvTable.getDataCount('active');
            const total = amzVvTable.getDataCount();
            $('#amz-vv-total').text('Total: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            try {
                const page = amzVvTable.getPage();
                const pages = amzVvTable.getPageMax();
                $('#amz-vv-page-info').text('Page: ' + page + ' / ' + pages);
            } catch (e) {
                $('#amz-vv-page-info').text('Page: —');
            }
        }

        function amzVvRowHasMissing(data) {
            return !!data.has_missing
                || (parseInt(data.kw_missing, 10) || 0) > 0
                || (parseInt(data.pt_missing, 10) || 0) > 0;
        }

        function amzVvRowHasMissingInv(data) {
            return !!data.has_missing_inv_gt0
                || (parseInt(data.missing_inv_gt0_count, 10) || 0) > 0;
        }

        function amzVvRowHasExtra(data) {
            return (parseInt(data.kw_extra, 10) || 0) > 0
                || (parseInt(data.pt_extra, 10) || 0) > 0
                || (parseInt(data.extra_sku_count, 10) || 0) > 0;
        }

        function amzVvRowHasArchivedExtra(data) {
            return !!data.has_archived_extra
                || (parseInt(data.kw_archived_extra, 10) || 0) > 0
                || (parseInt(data.pt_archived_extra, 10) || 0) > 0
                || (parseInt(data.archived_extra_sku_count, 10) || 0) > 0;
        }

        function amzVvSetActiveFilter(mode) {
            amzVvActiveFilter = (amzVvActiveFilter === mode) ? null : mode;
            amzVvApplyFilters();
        }

        function amzVvApplyFilters() {
            if (!amzVvTable) return;
            amzVvTable.clearFilter();

            const q = ($('#amz-vv-search').val() || '').trim().toLowerCase();
            if (q) {
                amzVvTable.addFilter(function (data) {
                    return String(data.parent || '').toLowerCase().includes(q)
                        || String(data.sku || '').toLowerCase().includes(q);
                });
            }

            if (amzVvActiveFilter === 'issues') {
                amzVvTable.addFilter(function (data) {
                    return amzVvRowHasMissing(data) || amzVvRowHasExtra(data);
                });
            } else if (amzVvActiveFilter === 'missing') {
                amzVvTable.addFilter(amzVvRowHasMissing);
            } else if (amzVvActiveFilter === 'missing_inv') {
                amzVvTable.addFilter(amzVvRowHasMissingInv);
            } else if (amzVvActiveFilter === 'extra') {
                amzVvTable.addFilter(amzVvRowHasExtra);
            } else if (amzVvActiveFilter === 'archived_extra') {
                amzVvTable.addFilter(amzVvRowHasArchivedExtra);
            }

            $('#amz-vv-badge-issues-wrap').toggleClass('is-active', amzVvActiveFilter === 'issues');
            $('#amz-vv-badge-missing-wrap').toggleClass('is-active', amzVvActiveFilter === 'missing');
            $('#amz-vv-badge-missing-inv-wrap').toggleClass('is-active', amzVvActiveFilter === 'missing_inv');
            $('#amz-vv-badge-extra-wrap').toggleClass('is-active', amzVvActiveFilter === 'extra');
            $('#amz-vv-badge-archived-extra-wrap').toggleClass('is-active', amzVvActiveFilter === 'archived_extra');
            amzVvUpdateRowCount();
        }

        function amzVvAdHoverTitle(type, d) {
            const addedCampaigns = Array.isArray(d[type + '_added_campaigns']) ? d[type + '_added_campaigns'] : [];
            const addedSkus = Array.isArray(d[type + '_added_skus']) ? d[type + '_added_skus'] : [];
            const missingSkus = Array.isArray(d[type + '_missing_skus']) ? d[type + '_missing_skus'] : [];
            const overSkus = Array.isArray(d[type + '_over_skus']) ? d[type + '_over_skus'] : [];
            const extraSkus = Array.isArray(d[type + '_extra_skus']) ? d[type + '_extra_skus'] : [];
            const archivedExtras = Array.isArray(d[type + '_archived_extra_skus']) ? d[type + '_archived_extra_skus'] : [];
            const parts = [];
            if (addedCampaigns.length > 0) {
                parts.push('Added campaigns (' + addedCampaigns.length + '): ' + addedCampaigns.join(', '));
            } else if (addedSkus.length > 0) {
                parts.push('Added SKUs (' + addedSkus.length + '): ' + addedSkus.join(', '));
            }
            if (missingSkus.length > 0) {
                parts.push('Missing: ' + missingSkus.join(', '));
            }
            if (overSkus.length > 0) {
                parts.push('Over: ' + overSkus.join(', '));
            }
            if (extraSkus.length > 0) {
                parts.push('Extra: ' + extraSkus.join(', '));
            }
            if (archivedExtras.length > 0) {
                parts.push('Archived: ' + archivedExtras.join(', '));
            }
            if (parts.length === 0) {
                return type.toUpperCase() + ' siblings OK';
            }
            return type.toUpperCase() + ' — ' + parts.join(' | ');
        }

        function amzVvFormatAdSibling(type) {
            return function (cell) {
                const d = cell.getRow().getData();
                const status = d[type + '_ad_status'];
                const label = d[type + '_ad_label'] || '';
                const missing = parseInt(d[type + '_missing'], 10) || 0;
                const extra = parseInt(d[type + '_extra'], 10) || 0;
                const archivedExtra = parseInt(d[type + '_archived_extra'], 10) || 0;
                const missingSkus = Array.isArray(d[type + '_missing_skus']) ? d[type + '_missing_skus'] : [];
                const overSkus = Array.isArray(d[type + '_over_skus']) ? d[type + '_over_skus'] : [];
                const extraSkus = Array.isArray(d[type + '_extra_skus']) ? d[type + '_extra_skus'] : [];
                const archivedExtraSkus = Array.isArray(d[type + '_archived_extra_skus']) ? d[type + '_archived_extra_skus'] : [];

                if (status === null || status === undefined || !label || label === '—') {
                    return amzVvDash(null);
                }

                if (d.is_parent) {
                    const tip = amzVvEscapeHtml(amzVvAdHoverTitle(type, d));
                    let mainCls = 'amz-vv-ad-missing';
                    let mainHtml = amzVvEscapeHtml(label);
                    if (status === 'ok') {
                        mainCls = 'amz-vv-ad-added';
                        mainHtml = `<i class="fas fa-check"></i> ${mainHtml}`;
                    } else if (status === 'extra' && missing === 0) {
                        mainCls = 'amz-vv-ad-extra';
                    } else if (status === 'archived_extra' && missing === 0 && extra === 0) {
                        mainCls = 'amz-vv-ad-archived';
                    } else if (status === 'over' && missing === 0 && extra === 0 && archivedExtra === 0) {
                        mainCls = 'amz-vv-ad-over';
                    }

                    let skuLines = '';
                    if (missingSkus.length > 0) {
                        const parentAttr = amzVvEscapeHtml(d.parent || '');
                        const missingAttr = amzVvEscapeHtml(missingSkus.join('|'));
                        skuLines += `<span class="amz-vv-ad-skus amz-vv-ad-skus--missing">Missing: ${amzVvEscapeHtml(missingSkus.join(', '))}`
                            + ` <button type="button" class="btn btn-link btn-sm p-0 ms-1 amz-vv-add-missing-row" data-type="${type}" data-parent="${parentAttr}" data-missing="${missingAttr}" title="Add these missing SKUs to the parent campaign (INV 0 allowed)" style="font-size:10px;font-weight:700;vertical-align:baseline;">Add</button>`
                            + `</span>`;
                    }
                    if (overSkus.length > 0) {
                        skuLines += `<span class="amz-vv-ad-skus amz-vv-ad-skus--over">Over: ${amzVvEscapeHtml(overSkus.join(', '))}</span>`;
                    }
                    if (extraSkus.length > 0) {
                        const parentAttr = amzVvEscapeHtml(d.parent || '');
                        const extrasAttr = amzVvEscapeHtml(extraSkus.join('|'));
                        skuLines += `<span class="amz-vv-ad-skus amz-vv-ad-skus--extra">Extra: ${amzVvEscapeHtml(extraSkus.join(', '))}`
                            + ` <button type="button" class="btn btn-link btn-sm p-0 ms-1 amz-vv-archive-extra-row" data-type="${type}" data-parent="${parentAttr}" data-extras="${extrasAttr}" title="Archive these Extra ads (skip if already ARCHIVED)" style="font-size:10px;font-weight:700;vertical-align:baseline;">Archive</button>`
                            + `</span>`;
                    }
                    if (archivedExtraSkus.length > 0) {
                        skuLines += `<span class="amz-vv-ad-skus amz-vv-ad-skus--archived">Archived: ${amzVvEscapeHtml(archivedExtraSkus.join(', '))}</span>`;
                    }

                    return `<span class="amz-vv-ad-wrap" title="${tip}">`
                        + `<span class="${mainCls}">${mainHtml}</span>`
                        + skuLines
                        + `</span>`;
                }

                if (status === 'over') {
                    return '<span class="amz-vv-ad-over" title="In campaign but not in active listed records">Over</span>';
                }
                if (status === 'added') {
                    return '<span class="amz-vv-ad-added" title="Ads existing (in campaign)">Added</span>';
                }
                if (status === 'missing') {
                    return '<span class="amz-vv-ad-missing" title="No matching campaign">Missing</span>';
                }
                if (status === 'coming') {
                    return '<span title="Product Master status Coming — not counted as missing">Coming</span>';
                }
                return amzVvDash(null);
            };
        }

        $(document).ready(function () {
            amzVvTable = new Tabulator('#amz-variation-verify-table', {
                ajaxURL: '{{ route("amz.variation.verify.data") }}',
                ajaxResponse: function (url, params, response) {
                    const rows = Array.isArray(response) ? response : (response.data || []);
                    if (response && response.meta) amzVvUpdateMeta(response.meta);
                    return rows;
                },
                height: '650px',
                layout: 'fitColumns',
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                paginationButtonCount: 10,
                placeholder: 'No parents found in CP Master',
                rowFormatter: function (row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('amz-vv-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 120,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const v = cell.getValue() || '';
                            if (!v) return amzVvDash(null);
                            if (d.is_parent) return `<span class="fw-semibold">${amzVvEscapeHtml(v)}</span>`;
                            return `<span class="fw-semibold" style="color:#0d6efd;">${amzVvEscapeHtml(v)}</span>`;
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 140,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const v = cell.getValue() || '';
                            if (!v) return amzVvDash(null);
                            return d.is_parent
                                ? `<span class="fw-semibold">${amzVvEscapeHtml(v)}</span>`
                                : amzVvEscapeHtml(v);
                        }
                    },
                    {
                        title: 'Parent Vs Ads SKU KW',
                        field: 'kw_ad_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 130,
                        widthGrow: 1,
                        formatter: amzVvFormatAdSibling('kw')
                    },
                    {
                        title: 'Parent Vs Ads SKU PT',
                        field: 'pt_ad_label',
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        minWidth: 130,
                        widthGrow: 1,
                        formatter: amzVvFormatAdSibling('pt')
                    },
                    {
                        title: 'Error',
                        field: 'add_error',
                        hozAlign: 'left',
                        headerHozAlign: 'center',
                        minWidth: 180,
                        widthGrow: 2,
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            const errors = Array.isArray(d.add_errors) ? d.add_errors : [];
                            if (errors.length === 0) {
                                const plain = String(cell.getValue() || '').trim();
                                if (!plain) return amzVvDash(null);
                                return `<span class="amz-vv-error">${amzVvEscapeHtml(plain)}</span>`;
                            }
                            const lines = errors.map(function (e) {
                                const type = String((e && e.type) || '').trim();
                                const sku = String((e && e.sku) || '').trim();
                                const msg = String((e && e.message) || '').trim();
                                const head = [type, sku].filter(Boolean).join(' ');
                                return (head ? head + ': ' : '') + msg;
                            }).filter(Boolean);
                            if (lines.length === 0) return amzVvDash(null);
                            return `<span class="amz-vv-error" title="${amzVvEscapeHtml(lines.join(' | '))}">${
                                lines.map(function (l) { return amzVvEscapeHtml(l); }).join('<br>')
                            }</span>`;
                        }
                    }
                ]
            });

            amzVvTable.on('dataProcessed', amzVvApplyFilters);
            amzVvTable.on('dataFiltered', amzVvUpdateRowCount);
            amzVvTable.on('pageLoaded', amzVvUpdateRowCount);

            let searchTimer = null;
            $('#amz-vv-search').on('keyup search', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(amzVvApplyFilters, 200);
            });

            // Badge body → filter; trend dot → rolling history
            $('#amz-vv-badge-issues-wrap').on('click', function (e) {
                if ($(e.target).closest('.amz-vv-trend-dot').length) return;
                amzVvSetActiveFilter('issues');
            });
            $('#amz-vv-badge-missing-wrap').on('click', function () {
                amzVvSetActiveFilter('missing');
            });
            $('#amz-vv-badge-missing-inv-wrap').on('click', function () {
                amzVvSetActiveFilter('missing_inv');
            });
            $('#amz-vv-badge-extra-wrap').on('click', function () {
                amzVvSetActiveFilter('extra');
            });
            $('#amz-vv-badge-archived-extra-wrap').on('click', function () {
                amzVvSetActiveFilter('archived_extra');
            });
            $('#amz-vv-issues-trend-dot').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                amzVvOpenIssuesChart();
            });
            $('#amz-vv-issues-trend-dot').on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    amzVvOpenIssuesChart();
                }
            });
            $('#amzVvChartRangeSelect').on('change', function () {
                const days = parseInt(this.value, 10);
                if (days === amzVvChartDays) return;
                amzVvChartDays = days;
                $('#amzVvChartModalTitle').text(
                    'Variations Issues — Rolling History'
                    + (amzVvChartDays === 0 ? ' (Lifetime)' : ' (L' + amzVvChartDays + ')')
                );
                amzVvLoadIssuesChart();
            });

            function amzVvStripTypeSuffix(name) {
                return String(name || '')
                    .trim()
                    .replace(/\s+/g, ' ')
                    .replace(/\s+PT\.?$/i, '')
                    .replace(/\s+KW\.?$/i, '')
                    .trim();
            }

            function amzVvRebuildAdLabel(d, type) {
                const existing = parseInt(d[type + '_existing'], 10) || 0;
                const required = parseInt(d[type + '_required'], 10) || 0;
                const missing = parseInt(d[type + '_missing'], 10) || 0;
                const over = parseInt(d[type + '_over'], 10) || 0;
                const extra = parseInt(d[type + '_extra'], 10) || 0;
                const archivedExtra = parseInt(d[type + '_archived_extra'], 10) || 0;

                if (required === 0) {
                    d[type + '_ad_status'] = null;
                    d[type + '_ad_label'] = '—';
                    return;
                }

                const parts = [];
                if (missing > 0) parts.push(missing + ' missing');
                if (over > 0) parts.push(over + ' over');
                if (extra > 0) parts.push(extra + ' extra');
                if (archivedExtra > 0) parts.push(archivedExtra + ' archived');

                d[type + '_ad_label'] = (existing + '/' + required)
                    + (parts.length ? ' · ' + parts.join(' · ') : '');

                let status = 'ok';
                if (missing > 0) status = 'missing';
                else if (extra > 0) status = 'extra';
                else if (archivedExtra > 0) status = 'archived_extra';
                else if (over > 0) status = 'over';

                const ok = missing === 0 && over === 0 && extra === 0
                    && archivedExtra === 0 && existing === required;
                d[type + '_ad_status'] = ok ? 'ok' : status;
            }

            /** Move archived Extra bases into Archived chips without a full table reload. */
            function amzVvApplyLocalArchive(bases, typeFilter) {
                if (!amzVvTable || !bases || !bases.length) return 0;

                const baseSet = {};
                bases.forEach(function (b) {
                    const k = String(b || '').trim().toUpperCase();
                    if (k) baseSet[k] = true;
                });
                const types = typeFilter
                    ? [String(typeFilter).toLowerCase()]
                    : ['kw', 'pt'];

                let touched = 0;
                amzVvTable.getRows().forEach(function (row) {
                    const d = row.getData();
                    if (!d || !d.is_parent) return;

                    let changed = false;
                    types.forEach(function (type) {
                        const extras = Array.isArray(d[type + '_extra_skus'])
                            ? d[type + '_extra_skus'].slice() : [];
                        const archived = Array.isArray(d[type + '_archived_extra_skus'])
                            ? d[type + '_archived_extra_skus'].slice() : [];
                        const keep = [];
                        let typeChanged = false;

                        extras.forEach(function (es) {
                            const key = String(es || '').trim().toUpperCase();
                            if (key && baseSet[key]) {
                                const already = archived.some(function (a) {
                                    return String(a || '').trim().toUpperCase() === key;
                                });
                                if (!already) archived.push(es);
                                typeChanged = true;
                            } else if (es) {
                                keep.push(es);
                            }
                        });

                        if (!typeChanged) return;

                        d[type + '_extra_skus'] = keep;
                        d[type + '_archived_extra_skus'] = archived;
                        d[type + '_extra'] = keep.length;
                        d[type + '_archived_extra'] = archived.length;

                        if (Array.isArray(d[type + '_extra_campaigns'])) {
                            d[type + '_extra_campaigns'] = d[type + '_extra_campaigns'].map(function (c) {
                                if (!c) return c;
                                const b = String(c.base || amzVvStripTypeSuffix(c.campaign_name || ''))
                                    .trim().toUpperCase();
                                if (b && baseSet[b]) {
                                    return Object.assign({}, c, { campaign_status: 'ARCHIVED' });
                                }
                                return c;
                            });
                        }

                        amzVvRebuildAdLabel(d, type);
                        changed = true;
                    });

                    if (!changed) return;

                    const kwE = parseInt(d.kw_extra, 10) || 0;
                    const ptE = parseInt(d.pt_extra, 10) || 0;
                    const kwA = parseInt(d.kw_archived_extra, 10) || 0;
                    const ptA = parseInt(d.pt_archived_extra, 10) || 0;
                    d.has_extra = (kwE + ptE) > 0;
                    d.has_archived_extra = (kwA + ptA) > 0;
                    d.extra_sku_count = kwE + ptE;
                    d.archived_extra_sku_count = kwA + ptA;
                    row.update(d);
                    touched++;
                });

                amzVvRefreshBadgeCountsFromTable();
                return touched;
            }

            function amzVvRefreshBadgeCountsFromTable() {
                if (!amzVvTable) return;

                const extraUnion = {};
                const archUnion = {};
                let issues = 0;

                const missingUnion = {};
                const missingInvUnion = {};

                amzVvTable.getData().forEach(function (d) {
                    if (!d || !d.is_parent) return;
                    const hasMiss = (parseInt(d.kw_missing, 10) || 0) > 0
                        || (parseInt(d.pt_missing, 10) || 0) > 0;
                    const hasExtra = (parseInt(d.kw_extra, 10) || 0) > 0
                        || (parseInt(d.pt_extra, 10) || 0) > 0;
                    if (hasMiss || hasExtra) issues++;

                    ['kw', 'pt'].forEach(function (t) {
                        (Array.isArray(d[t + '_extra_skus']) ? d[t + '_extra_skus'] : []).forEach(function (s) {
                            const k = String(s || '').trim().toUpperCase();
                            if (k) extraUnion[k] = true;
                        });
                        (Array.isArray(d[t + '_archived_extra_skus']) ? d[t + '_archived_extra_skus'] : []).forEach(function (s) {
                            const k = String(s || '').trim().toUpperCase();
                            if (k) archUnion[k] = true;
                        });
                        (Array.isArray(d[t + '_missing_skus']) ? d[t + '_missing_skus'] : []).forEach(function (s) {
                            const k = String(s || '').trim().toUpperCase();
                            if (k) missingUnion[k] = true;
                        });
                    });
                    (Array.isArray(d.missing_inv_gt0_skus) ? d.missing_inv_gt0_skus : []).forEach(function (s) {
                        const k = String(s || '').trim().toUpperCase();
                        if (k) missingInvUnion[k] = true;
                    });
                });

                $('#amz-vv-badge-missing').text(Object.keys(missingUnion).length.toLocaleString());
                $('#amz-vv-badge-missing-inv').text(Object.keys(missingInvUnion).length.toLocaleString());
                $('#amz-vv-badge-extra').text(Object.keys(extraUnion).length.toLocaleString());
                $('#amz-vv-badge-archived-extra').text(Object.keys(archUnion).length.toLocaleString());
                $('#amz-vv-badge-issues').text(issues.toLocaleString());
                amzVvIssuesLiveCount = issues;
                amzVvApplyIssuesTrendDot();
                amzVvApplyFilters();
            }

            function amzVvBasesFromArchiveResult(items, fallbackExtras) {
                const bases = {};
                (items || []).forEach(function (item) {
                    const base = amzVvStripTypeSuffix(item && item.campaign_name ? item.campaign_name : '');
                    if (base) bases[base] = true;
                });
                if (Object.keys(bases).length === 0) {
                    (fallbackExtras || []).forEach(function (e) {
                        const b = String(e || '').trim();
                        if (b) bases[b] = true;
                    });
                }
                return Object.keys(bases);
            }

            function amzVvCollectExtraPayload(scope) {
                // scope: 'visible' (filtered table) | row data object
                const extras = new Set();
                const names = new Set();
                let parent = '';

                function absorb(row) {
                    if (!row) return;
                    ['kw', 'pt'].forEach(function (t) {
                        (Array.isArray(row[t + '_extra_skus']) ? row[t + '_extra_skus'] : []).forEach(function (s) {
                            if (s) extras.add(String(s));
                        });
                        (Array.isArray(row[t + '_extra_campaigns']) ? row[t + '_extra_campaigns'] : []).forEach(function (c) {
                            if (c && c.campaign_name) names.add(String(c.campaign_name));
                        });
                    });
                }

                if (scope && typeof scope === 'object' && !Array.isArray(scope) && scope.parent !== undefined) {
                    parent = String(scope.parent || '');
                    absorb(scope);
                } else if (amzVvTable) {
                    amzVvTable.getData('active').forEach(absorb);
                }

                return {
                    parent: parent,
                    extra_skus: Array.from(extras),
                    campaign_names: Array.from(names)
                };
            }

            function amzVvArchiveExtras(payload, $btn) {
                const extras = payload.extra_skus || [];
                const names = payload.campaign_names || [];
                if (extras.length === 0 && names.length === 0) {
                    alert('No Extra ads to archive.');
                    return;
                }

                const typeLabel = payload.type ? String(payload.type).toUpperCase() + ' ' : '';
                const label = names.length
                    ? (names.length + ' campaign(s)')
                    : (extras.length + ' extra ' + typeLabel + 'SKU base(s)');
                if (!confirm('Archive Extra ' + typeLabel + 'ads in Amz Ads (' + label + ')?')) {
                    return;
                }

                const $target = $btn || $('#amz-vv-archive-extra-btn');
                const prevHtml = $target.html();
                $target.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Archiving…');
                $('#amz-vv-status-line').text('Archiving Extra ads…');

                $.ajax({
                    url: '{{ route("amz.variation.verify.archive.extra") }}',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({
                        parent: payload.parent || '',
                        type: payload.type || '',
                        extra_skus: extras,
                        campaign_names: names
                    }),
                    success: function (res) {
                        const archived = (res && Array.isArray(res.archived)) ? res.archived : [];
                        const skipped = (res && Array.isArray(res.skipped)) ? res.skipped : [];
                        const failed = (res && Array.isArray(res.failed)) ? res.failed : [];

                        // Patch rows in place — avoid full table reload / Loading overlay.
                        if (archived.length || skipped.length) {
                            const bases = amzVvBasesFromArchiveResult(
                                archived.concat(skipped),
                                extras
                            );
                            amzVvApplyLocalArchive(bases, payload.type || '');
                        }

                        let msg = (res && res.message) ? res.message : 'Archive finished.';
                        if (archived.length) {
                            msg += '\n\nArchived:\n' + archived.map(function (a) {
                                return '• ' + (a.campaign_name || a.campaign_id || '?');
                            }).join('\n');
                        }
                        if (skipped.length) {
                            msg += '\n\nSkipped (already archived):\n' + skipped.map(function (s) {
                                return '• ' + (s.campaign_name || s.campaign_id || '?');
                            }).join('\n');
                        }
                        if (failed.length) {
                            msg += '\n\nFailed:\n' + failed.map(function (f) {
                                return '• ' + (f.campaign_name || f.campaign_id || '?') + ': ' + (f.message || '');
                            }).join('\n');
                        }
                        $('#amz-vv-status-line').text((res && res.message) ? res.message : 'Archive finished.');
                        alert(msg);
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : ('Archive failed (' + (xhr.status || 'network') + ')');
                        alert(msg);
                        $('#amz-vv-status-line').text(msg);
                    },
                    complete: function () {
                        $target.prop('disabled', false).html(prevHtml);
                    }
                });
            }

            $('#amz-vv-archive-extra-btn').on('click', function () {
                const payload = amzVvCollectExtraPayload('visible');
                amzVvArchiveExtras(payload, $(this));
            });

            $(document).on('click', '.amz-vv-archive-extra-row', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const parent = $btn.data('parent') || '';
                const type = String($btn.data('type') || '').toUpperCase();
                const extras = String($btn.data('extras') || '').split('|').map(function (s) { return s.trim(); }).filter(Boolean);
                amzVvArchiveExtras({
                    parent: parent,
                    type: type,
                    extra_skus: extras,
                    campaign_names: []
                }, $btn);
            });

            function amzVvParentKey(parent) {
                return String(parent || '').trim().toUpperCase();
            }

            function amzVvSyncAddErrorField(d) {
                const errors = Array.isArray(d.add_errors) ? d.add_errors : [];
                d.add_error = errors.map(function (e) {
                    const type = String((e && e.type) || '').trim();
                    const sku = String((e && e.sku) || '').trim();
                    const msg = String((e && e.message) || '').trim();
                    const head = [type, sku].filter(Boolean).join(' ');
                    return (head ? head + ': ' : '') + msg;
                }).filter(Boolean).join(' | ');
            }

            function amzVvSetRowErrors(parent, type, failedItems, fallbackMessage) {
                if (!amzVvTable || !parent) return;
                const typeKey = String(type || '').toUpperCase();
                const incoming = [];
                (failedItems || []).forEach(function (f) {
                    const sku = String((f && f.sku) || '').trim();
                    const message = String((f && f.message) || fallbackMessage || '').trim();
                    if (!message) return;
                    incoming.push({ type: typeKey || '', sku: sku, message: message });
                });
                if (incoming.length === 0 && fallbackMessage) {
                    incoming.push({ type: typeKey || '', sku: '', message: String(fallbackMessage) });
                }

                amzVvTable.getRows().forEach(function (row) {
                    const d = row.getData();
                    if (!d || !d.is_parent) return;
                    if (amzVvParentKey(d.parent) !== amzVvParentKey(parent)) return;
                    const keep = (Array.isArray(d.add_errors) ? d.add_errors : []).filter(function (e) {
                        return String((e && e.type) || '').toUpperCase() !== typeKey;
                    });
                    d.add_errors = keep.concat(incoming);
                    amzVvSyncAddErrorField(d);
                    row.update(d);
                });
            }

            function amzVvClearRowErrorsForSkus(parent, type, addedSkus) {
                if (!amzVvTable || !parent || !addedSkus || !addedSkus.length) return;
                const typeKey = String(type || '').toUpperCase();
                const addedSet = {};
                addedSkus.forEach(function (s) {
                    const k = String(s || '').trim().toUpperCase();
                    if (k) addedSet[k] = true;
                });

                amzVvTable.getRows().forEach(function (row) {
                    const d = row.getData();
                    if (!d || !d.is_parent) return;
                    if (amzVvParentKey(d.parent) !== amzVvParentKey(parent)) return;
                    const next = (Array.isArray(d.add_errors) ? d.add_errors : []).filter(function (e) {
                        if (String((e && e.type) || '').toUpperCase() !== typeKey) return true;
                        const sku = String((e && e.sku) || '').trim().toUpperCase();
                        return !sku || !addedSet[sku];
                    });
                    d.add_errors = next;
                    amzVvSyncAddErrorField(d);
                    row.update(d);
                });
            }

            function amzVvApplyLocalAdd(parent, type, addedSkus) {
                if (!amzVvTable || !parent || !addedSkus || !addedSkus.length) return 0;
                const typeKey = String(type || '').toLowerCase();
                if (typeKey !== 'kw' && typeKey !== 'pt') return 0;

                const addedSet = {};
                addedSkus.forEach(function (s) {
                    const k = String(s || '').trim().toUpperCase();
                    if (k) addedSet[k] = true;
                });

                let touched = 0;
                amzVvTable.getRows().forEach(function (row) {
                    const d = row.getData();
                    if (!d || !d.is_parent) return;
                    if (String(d.parent || '').trim().toUpperCase() !== String(parent).trim().toUpperCase()) {
                        return;
                    }

                    const missing = Array.isArray(d[typeKey + '_missing_skus'])
                        ? d[typeKey + '_missing_skus'].slice() : [];
                    const added = Array.isArray(d[typeKey + '_added_skus'])
                        ? d[typeKey + '_added_skus'].slice() : [];
                    const keep = [];
                    let typeChanged = false;

                    missing.forEach(function (ms) {
                        const key = String(ms || '').trim().toUpperCase();
                        if (key && addedSet[key]) {
                            if (!added.some(function (a) {
                                return String(a || '').trim().toUpperCase() === key;
                            })) {
                                added.push(ms);
                            }
                            typeChanged = true;
                        } else if (ms) {
                            keep.push(ms);
                        }
                    });

                    if (!typeChanged) return;

                    d[typeKey + '_missing_skus'] = keep;
                    d[typeKey + '_added_skus'] = added;
                    d[typeKey + '_missing'] = keep.length;
                    d[typeKey + '_existing'] = added.length;
                    amzVvRebuildAdLabel(d, typeKey);

                    const kwM = parseInt(d.kw_missing, 10) || 0;
                    const ptM = parseInt(d.pt_missing, 10) || 0;
                    d.has_missing = (kwM + ptM) > 0;
                    d.missing_sku_count = kwM + ptM;
                    if (Array.isArray(d.missing_inv_gt0_skus)) {
                        d.missing_inv_gt0_skus = d.missing_inv_gt0_skus.filter(function (s) {
                            return !addedSet[String(s || '').trim().toUpperCase()];
                        });
                        d.missing_inv_gt0_count = d.missing_inv_gt0_skus.length;
                        d.has_missing_inv_gt0 = d.missing_inv_gt0_count > 0;
                    }

                    row.update(d);
                    touched++;
                });

                amzVvRefreshBadgeCountsFromTable();
                return touched;
            }

            function amzVvAddMissing(payload, $btn) {
                const parent = String(payload.parent || '').trim();
                const type = String(payload.type || '').toUpperCase();
                const missing = payload.missing_skus || [];
                if (!parent || missing.length === 0) {
                    alert('No missing SKUs to add.');
                    return;
                }

                const label = missing.length === 1
                    ? missing[0]
                    : (missing.length + ' SKUs');
                if (!confirm('Add ' + label + ' to PARENT ' + parent + ' ' + type + ' campaign?\n\nZero inventory is allowed.')) {
                    return;
                }

                const $target = $btn || $('#amz-vv-status-line');
                const prevHtml = $btn ? $btn.html() : '';
                if ($btn) {
                    $btn.prop('disabled', true)
                        .html('<span class="spinner-border spinner-border-sm"></span>');
                }
                $('#amz-vv-status-line').text('Adding missing SKUs to campaign…');

                $.ajax({
                    url: '{{ route("amz.variation.verify.add.missing") }}',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({
                        parent: parent,
                        type: type,
                        missing_skus: missing
                    }),
                    success: function (res) {
                        const added = (res && Array.isArray(res.added)) ? res.added : [];
                        const failed = (res && Array.isArray(res.failed)) ? res.failed : [];
                        const addedSkus = added.map(function (a) {
                            return a && a.sku ? String(a.sku) : '';
                        }).filter(Boolean);

                        if (addedSkus.length) {
                            amzVvApplyLocalAdd(parent, type, addedSkus);
                            amzVvClearRowErrorsForSkus(parent, type, addedSkus);
                        }
                        amzVvSetRowErrors(
                            parent,
                            type,
                            failed,
                            (!addedSkus.length && res && res.message) ? res.message : ''
                        );

                        const msg = (res && res.message) ? res.message : 'Add finished.';
                        $('#amz-vv-status-line').text(
                            failed.length
                                ? msg + ' Failures are in the Error column.'
                                : msg
                        );
                    },
                    error: function (xhr) {
                        const body = xhr.responseJSON || {};
                        const msg = body.message
                            ? body.message
                            : ('Add failed (' + (xhr.status || 'network') + ')');
                        amzVvSetRowErrors(parent, type, body.failed || [], msg);
                        $('#amz-vv-status-line').text(msg);
                    },
                    complete: function () {
                        if ($btn) {
                            $btn.prop('disabled', false).html(prevHtml || 'Add');
                        }
                    }
                });
            }

            $(document).on('click', '.amz-vv-add-missing-row', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const parent = $btn.data('parent') || '';
                const type = String($btn.data('type') || '').toUpperCase();
                const missing = String($btn.data('missing') || '').split('|').map(function (s) { return s.trim(); }).filter(Boolean);
                amzVvAddMissing({
                    parent: parent,
                    type: type,
                    missing_skus: missing
                }, $btn);
            });

            function amzVvCollectMissingItems() {
                const items = [];
                if (!amzVvTable) return items;
                amzVvTable.getData('active').forEach(function (d) {
                    if (!d || !d.is_parent) return;
                    const parent = String(d.parent || '').trim();
                    if (!parent) return;
                    ['kw', 'pt'].forEach(function (t) {
                        const skus = (Array.isArray(d[t + '_missing_skus']) ? d[t + '_missing_skus'] : [])
                            .map(function (s) { return String(s || '').trim(); })
                            .filter(Boolean);
                        if (skus.length) {
                            items.push({
                                parent: parent,
                                type: t.toUpperCase(),
                                missing_skus: skus
                            });
                        }
                    });
                });
                return items;
            }

            $('#amz-vv-add-missing-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                const items = amzVvCollectMissingItems();
                if (items.length === 0) {
                    alert('No missing SKUs to add in the current view.');
                    return;
                }

                let skuN = 0;
                const parents = {};
                items.forEach(function (it) {
                    skuN += (it.missing_skus || []).length;
                    parents[it.parent] = true;
                });
                const parentN = Object.keys(parents).length;
                if (!confirm(
                    'Add ' + skuN + ' missing SKU' + (skuN === 1 ? '' : 's')
                    + ' to ' + parentN + ' parent campaign' + (parentN === 1 ? '' : 's')
                    + ' via Amazon Ads API?\n\nZero inventory is allowed. Current table filter is used.'
                )) {
                    return;
                }

                const prevHtml = $btn.html();
                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Adding…');
                $('#amz-vv-status-line').text('Adding missing SKUs to parent campaigns…');

                $.ajax({
                    url: '{{ route("amz.variation.verify.add.missing.all") }}',
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    timeout: 600000,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({ items: items }),
                    success: function (res) {
                        const results = (res && Array.isArray(res.results)) ? res.results : [];
                        let failedN = 0;
                        results.forEach(function (one) {
                            const parent = one && one.parent ? String(one.parent) : '';
                            const type = one && one.type ? String(one.type) : '';
                            const added = (one && Array.isArray(one.added)) ? one.added : [];
                            const failed = (one && Array.isArray(one.failed)) ? one.failed : [];
                            const addedSkus = added.map(function (a) {
                                return a && a.sku ? String(a.sku) : '';
                            }).filter(Boolean);
                            if (addedSkus.length && parent && type) {
                                amzVvApplyLocalAdd(parent, type, addedSkus);
                                amzVvClearRowErrorsForSkus(parent, type, addedSkus);
                            }
                            if (parent) {
                                amzVvSetRowErrors(
                                    parent,
                                    type,
                                    failed,
                                    (!addedSkus.length && one && one.message) ? one.message : ''
                                );
                            }
                            failedN += failed.length;
                        });

                        const msg = (res && res.message) ? res.message : 'Add finished.';
                        $('#amz-vv-status-line').text(
                            failedN > 0 ? msg + ' Failures are in the Error column.' : msg
                        );
                    },
                    error: function (xhr) {
                        const body = xhr.responseJSON || {};
                        const msg = body.message
                            ? body.message
                            : ('Add failed (' + (xhr.status || 'network') + ')');
                        items.forEach(function (it) {
                            amzVvSetRowErrors(it.parent, it.type, [], msg);
                        });
                        $('#amz-vv-status-line').text(msg);
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(prevHtml);
                    }
                });
            });

            $('#amz-vv-refresh-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                $btn.prop('disabled', true);
                amzVvTable.setData('{{ route("amz.variation.verify.data") }}')
                    .finally(function () { $btn.prop('disabled', false); });
            });

            $('#amz-vv-pull-btn').on('click', function () {
                const $btn = $(this);
                if ($btn.prop('disabled')) return;

                if (!confirm('Pull all merchant listings from Amz SP-API?\n\nThis uses GET_MERCHANT_LISTINGS_ALL_DATA and may take several minutes.')) {
                    return;
                }

                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1"></span> Pulling…');
                $('#amz-vv-status-line').text('Requesting listings report from Amz…');

                $.ajax({
                    url: '{{ route("amz.variation.verify.pull") }}',
                    method: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            $('#amz-vv-status-line').text(res.message || 'Pull completed.');
                            amzVvTable.setData('{{ route("amz.variation.verify.data") }}');
                        } else {
                            alert(res.message || 'Pull failed.');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : ('Pull failed (' + (xhr.status || 'network') + ')');
                        alert(msg);
                    },
                    complete: function () {
                        $btn.prop('disabled', false)
                            .html('<i class="fas fa-cloud-download-alt me-1"></i> Pull Listings');
                    }
                });
            });
        });
    </script>
@endsection
