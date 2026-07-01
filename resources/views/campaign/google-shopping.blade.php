@php
    $pageTitle = 'Google Shopping';
    $pageSubtitle = 'Google Ads';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #google-ads-campaigns-raw-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 11px;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #google-ads-campaigns-raw-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        /* Normal horizontal headers (not vertical / aliexpress-style) */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        /* Tighter horizontal padding than Tabulator defaults */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        /* Sts: column was too narrow — header wrapped one letter per line; keep title on one row like other cols */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-title {
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-title-holder {
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell .gac-raw-status-cell {
            white-space: nowrap;
        }
        /* ── Pagination footer — same rules as aliexpress_pricing_view (amazon_tabulator_view style) ── */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important;
            min-width: 36px !important; height: 36px !important; line-height: 36px !important;
            padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important;
            color: #475569 !important; cursor: pointer; transition: all 0.15s ease !important;
            text-align: center !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }
        #google-ads-campaigns-raw-wrap { overflow-x: auto; overflow-y: visible; }
        /* UB% utilization colors — same as /google/shopping/utilized (7 UB% / 1 UB% formatters) */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.green-bg {
            color: #05bd30 !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.pink-bg {
            color: #ff01d0 !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.red-bg {
            color: #ff2727 !important;
        }
        /* ACOS L30 text color bands: <10 pink, <20 green, <30 blue, <40 yellow, <=50 orange, >50 red */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-pink {
            color: #ff01d0 !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-green {
            color: #05bd30 !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-blue {
            color: #2563eb !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-yellow {
            color: #ca8a04 !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-orange {
            background-color: #fde047 !important;
            color: #000 !important;
            font-weight: 700;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.acos-red {
            color: #ff2727 !important;
            font-weight: 600;
        }
        #gac-raw-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        #gac-raw-filter-bar .gac-raw-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
            letter-spacing: 0.01em;
        }
        #gac-raw-filter-bar .gac-raw-filter-select {
            min-width: 132px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #64748b;
            font-size: 0.8125rem;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
        }
        #gac-raw-filter-bar .gac-raw-pill-dark {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #gac-raw-filter-bar .gac-raw-pill-muted {
            display: inline-block;
            background: #94a3b8;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #gac-raw-filter-bar .gac-raw-summary-num {
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
        }
        #gac-raw-filter-bar .gac-raw-summary-acos {
            color: #2563eb;
            font-weight: 700;
            font-size: 0.875rem;
        }
        #google-ads-campaigns-raw-wrap #gacRawU7PieModal .gac-raw-u7-pie-modal-chart {
            width: 100%;
            min-height: 400px;
        }

        .faas-stat-badge {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 9px 16px;
            border-radius: 8px;
            white-space: nowrap;
            line-height: 1.25;
            letter-spacing: 0.2px;
            cursor: pointer;            /* clicks open the trend chart */
            transition: transform 0.1s ease, filter 0.1s ease;
        }
        /* Inner value span is bumped slightly larger than the label for visual hierarchy */
        .faas-stat-badge > span {
            margin-left: 4px;
            font-size: 16px;
            font-weight: 800;
        }
        .faas-stat-badge:hover { transform: translateY(-1px); filter: brightness(1.1); }
        /* Static (non-chart-link) count badges keep a default cursor and skip the hover lift
           so users don't expect a trend chart on click. */
        .faas-stat-badge.is-static { cursor: default; }
        .faas-stat-badge.is-static:hover { transform: none; filter: none; }

        /* Compact square icon-only buttons (Refresh / Export) — keep BS btn-sm height
           but drop the text so the toolbar reads as a row of equal-size icon controls. */
        .gac-raw-icon-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .gac-raw-icon-btn > i { font-size: 14px; }
        /* Compact title so the badge strip has more horizontal room. */
        .faas-toolbar-title { font-size: 1rem; flex-shrink: 0; }
        .faas-stat-badge--count { background: #475569; }   /* slate   */
        .faas-stat-badge--impr  { background: #4c7ed8; }   /* blue    */
        .faas-stat-badge--clk   { background: #f59e0b; }   /* amber   */
        .faas-stat-badge--spend { background: #ef4444; }   /* red     */
        .faas-stat-badge--sales { background: #16a34a; }   /* green   */
        .faas-stat-badge--sold  { background: #8b5cf6; }   /* purple  */
        .faas-stat-badge--acos  { background: #ea580c; }   /* orange  */
        .faas-stat-badge--ctr   { background: #0891b2; }   /* cyan    */
        .faas-stat-badge--cvr   { background: #db2777; }   /* pink    */

    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title'  => $pageSubtitle,
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        {{--
                            Badge strip — kept on one line via flex-nowrap. flex-grow-1 and
                            overflow-x-auto are intentionally NOT set so the strip sizes to its
                            content; if the total width plus the right-side action buttons
                            exceeds the row width, the OUTER `d-flex flex-wrap` (parent of this
                            div) moves the action buttons to a second row instead of clipping
                            or horizontally scrolling the badges.
                        --}}
                        <div class="d-flex align-items-center flex-nowrap gap-2 py-1">
                            {{-- Live sums of key Tabulator columns
                                across whatever rows are currently visible
                                (after search / header filters). Updated by
                                updateMetricBadges() in the script below. --}}
                            {{-- CAMPAIGNS badge — total campaign count matching the current
                                 filter set (server-side `summary.filtered_row_count`, NOT just
                                 the rows on this page). Not a chart link; intentionally lacks
                                 .badge-chart-link so clicking does nothing. Updated by
                                 gacRawSummaryFromResponse() in the script below. --}}
                            <span id="faasCampaignsBadge" data-metric="campaigns" data-label="Campaigns"
                                class="faas-stat-badge faas-stat-badge--count is-static"
                                title="Total campaigns matching current filters">CAMPAIGNS:<span id="faasCampaignsValue">0</span></span>

                            <span id="faasL30SpendBadge" data-metric="spend" data-label="Spend"
                                class="faas-stat-badge faas-stat-badge--spend badge-chart-link"
                                title="Click for trend">SPEND:<span id="faasL30SpendValue">0</span></span>

                            <span id="faasClicksBadge" data-metric="clicks" data-label="Clicks"
                                class="faas-stat-badge faas-stat-badge--impr badge-chart-link"
                                title="Click for trend">CLICKS:<span id="faasClicksValue">0</span></span>

                            <span id="faasL30SoldBadge" data-metric="sold" data-label="Sold"
                                class="faas-stat-badge faas-stat-badge--clk badge-chart-link"
                                title="Click for trend">SOLD :<span id="faasL30SoldValue">0</span></span>

                            <span id="faasL30SalesBadge" data-metric="sales" data-label="Sales"
                                class="faas-stat-badge faas-stat-badge--spend badge-chart-link"
                                title="Click for trend">SALES:<span id="faasL30SalesValue">$0</span></span>

                            <span id="faasAcosBadge" data-metric="acos" data-label="ACOS"
                                class="faas-stat-badge faas-stat-badge--sales badge-chart-link"
                                title="Click for trend">ACOS:<span id="faasAcosValue">0%</span></span>

                            <span id="faasCvrBadge" data-metric="cvr" data-label="CVR"
                                class="faas-stat-badge faas-stat-badge--sold badge-chart-link"
                                title="Click for trend">CVR:<span id="faasCvrValue">0%</span></span>

                            <span id="faasTotalBgtBadge" data-metric="bgt" data-label="Total BGT"
                                class="faas-stat-badge faas-stat-badge--acos badge-chart-link"
                                title="Click for trend">TOTAL BGT:<span id="faasTotalBgtValue">$0</span></span>
                            
                        </div>
                        
                        <span id="gac-raw-total" class="badge bg-secondary">Total: —</span>
                        <span id="gac-raw-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="gac-raw-refresh" class="btn btn-sm btn-outline-primary gac-raw-icon-btn" title="Refresh grid" aria-label="Refresh grid">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="gac-raw-pull-data" class="btn btn-sm btn-primary" title="Runs app:fetch-google-ads-campaigns — pulls fresh campaign metrics from Google Ads + GA4. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-download-alt"></i> Pull Data
                            <input type="number" id="gac-raw-pull-days" min="1" max="30" value="1" class="form-control form-control-sm d-inline-block ms-1" style="width: 56px; padding: 1px 4px; height: 22px; font-size: 11px;" title="Days to fetch (1-30)" onclick="event.stopPropagation();">
                        </button>
                        <button type="button" id="gac-raw-export" class="btn btn-sm btn-success gac-raw-icon-btn" title="Export current page as CSV" aria-label="Export current page as CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbgt-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbgtRuleModal" title="Edit ACOS band thresholds and SBGT tier values">SBGT RULE</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbid-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbidRuleModal" title="Edit 7UB/1UB% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbgt" title="Runs budget:update-shopping — sets Google Shopping daily budgets from the saved SBGT rule. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbid" title="Runs sbid:update — pushes SBIDs for PARENT Shopping campaigns from the saved SBID rule. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBID
                        </button>
                    </div>
                    <div id="gac-raw-filter-bar" class="mb-3">
                        <div class="d-flex flex-wrap align-items-end gap-3 gap-md-4">
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub7">U7%</label>
                                <select id="gac-filter-ub7" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 7 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub1">U1%</label>
                                <select id="gac-filter-ub1" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 1 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-acos">ACOS</label>
                                <select id="gac-filter-acos" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by ACOS band">
                                    <option value="all" selected>All</option>
                                    <option value="pink">0 – 10%</option>
                                    <option value="green">10 – 20%</option>
                                    <option value="blue">20 – 30%</option>
                                    <option value="yellow">30 – 40%</option>
                                    <option value="orange">40 – 50%</option>
                                    <option value="red">&gt; 50%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-stat">Sts</label>
                                <select id="gac-filter-stat" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by campaign status">
                                    <option value="all" selected>All</option>
                                    <option value="ENABLED">Enabled</option>
                                    <option value="NOT_ENABLED">All except Enabled</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="REMOVED">Removed</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-u7-pie-open" data-bs-toggle="modal" data-bs-target="#gacRawU7PieModal" title="Row counts by U7% band (U7 filter ignored). Opens chart; click a slice for last 30 days.">U7% mix</button>
                            </div>
                            <span class="vr align-self-stretch d-none d-md-inline-block opacity-50"></span>
                            <div class="d-flex flex-wrap align-items-center gap-3 ms-md-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="gac-raw-pill-dark">SPI30</span>
                                    <span id="gac-raw-summary-spi30-val" class="gac-raw-summary-num">—</span>
                                </div>
                                <span class="vr align-self-stretch d-none d-sm-inline-block opacity-50"></span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="gac-raw-pill-muted">ACOS</span>
                                    <span id="gac-raw-summary-acos-val" class="gac-raw-summary-acos">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="gac-raw-push-result" class="alert alert-secondary small d-none mt-2 mb-0 py-2" role="status" aria-live="polite">
                        <div class="fw-semibold mb-1" id="gac-raw-push-result-title"></div>
                        <pre id="gac-raw-push-result-pre" class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap;max-height:280px;overflow:auto;"></pre>
                    </div>
                    <div id="google-ads-campaigns-raw-wrap">
                        <div class="p-2 bg-light border rounded-top">
                            <input type="search" id="gac-filter-search" class="form-control" placeholder="Search Campaign..." autocomplete="off" aria-label="Search by campaign name" maxlength="100">
                        </div>
                        <div id="google-ads-campaigns-raw-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawU7PieModal" tabindex="-1" aria-labelledby="gacRawU7PieModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawU7PieModalLabel">U7% mix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Row counts by U7% band (U7 grid filter ignored). Click a slice for the last 30 days.</p>
                    <div id="gacRawU7Pie" class="gac-raw-u7-pie-modal-chart" role="img" aria-label="U7 percent distribution pie chart"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawU7HistoryModal" tabindex="-1" aria-labelledby="gacRawU7HistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawU7HistoryModalLabel">U7% — daily row counts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="gacRawU7HistoryModalSub">Last 30 calendar days. Same U1/Sts filters as the grid; U7 filter ignored. Each day uses the 30-day window ending on that date.</p>
                    <div id="gacRawU7HistoryModalLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="gacRawU7HistoryModalError" role="alert"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="gacRawU7HistoryTable">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col" data-u7-bucket-col="lt66">&lt; 66%</th>
                                    <th scope="col" data-u7-bucket-col="66_99">66–99%</th>
                                    <th scope="col" data-u7-bucket-col="gt99">&gt; 99%</th>
                                    <th scope="col" data-u7-bucket-col="na">N/A</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody id="gacRawU7HistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawSbgtRuleModal" tabindex="-1" aria-labelledby="gacRawSbgtRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawSbgtRuleModalLabel">SBGT rule — ACOS % → Suggested Budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>ACOS % range</strong> (From → To).
                        Rows are checked <strong>top to bottom</strong>; the first range that
                        contains the campaign's ACOS gets its SBGT. Use <code>9999</code> on
                        <em>To</em> for the catch-all highest band.
                    </p>
                    <table class="table table-sm table-bordered align-middle mb-0" id="gac-sbgt-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From (%)</th>
                                <th style="width:110px;">To (%)</th>
                                <th style="width:120px;">SBGT</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="gac-sbgt-bands-body"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="gac-sbgt-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add band
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawSbgtRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawSbgtRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawSbidRuleModal" tabindex="-1" aria-labelledby="gacRawSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawSbidRuleModalLabel">SBID rule — 7UB% / 1UB% → suggested bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">When <strong>both</strong> 7UB% and 1UB% are <strong>above</strong> the high threshold, SBID = L1 CPC × over multiplier. When <strong>both</strong> are <strong>below</strong> the low threshold, SBID uses L1 / L7 CPC × under multipliers or fallback. Otherwise SBID shows —.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUtilLow">Low threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="gacSbidUtilLow">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUtilHigh">High threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="gacSbidUtilHigh">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderFallback">Fallback (no CPC)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderFallback">
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low — CPC multipliers</p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL7">× L7 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL7">
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both above high</p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidOverMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidOverMultL1">
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawSbidRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawSbidRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawBadgeChartModal" tabindex="-1" aria-labelledby="gacRawBadgeChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#0d6efd;color:#fff;">
                    <h6 class="modal-title fw-bold" id="gacRawBadgeChartModalLabel">
                        <i class="fas fa-chart-line me-1"></i>
                        <span id="gacRawBadgeChartTitle">Trend</span>
                    </h6>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <select id="gacRawBadgeChartRange" class="form-select form-select-sm" style="width:120px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex">
                        <div style="flex:1; min-height:320px; padding:10px;">
                            <canvas id="gacRawBadgeChartCanvas"></canvas>
                            <p class="text-center text-muted small mb-0 d-none" id="gacRawBadgeChartEmpty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div style="width:120px; border-left:1px solid #dee2e6; padding:14px 10px; text-align:center;">
                            <div class="small text-uppercase fw-bold" style="color:#dc3545;">Highest</div>
                            <div class="fs-5 fw-bold" id="gacRawBadgeChartHighest">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#6c757d;">Median</div>
                            <div class="fs-5 fw-bold" id="gacRawBadgeChartMedian">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#198754;">Lowest</div>
                            <div class="fs-5 fw-bold" id="gacRawBadgeChartLowest">—</div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const dataUrl = @json(route('google.shopping.campaigns.data'));
            const gacRawRuleGetUrl = @json(route('google.shopping.campaigns.rule'));
            const gacRawRuleSaveUrl = @json(route('google.shopping.campaigns.rule.save'));
            const gacRawPushSbgtUrl = @json(route('google.shopping.campaigns.push.sbgt'));
            const gacRawPushSbidUrl = @json(route('google.shopping.campaigns.push.sbid'));
            const gacRawPullDataUrl = @json(route('google.shopping.campaigns.pull.data'));
            const gacRawBadgeHistoryUrl = @json(route('google.shopping.campaigns.badge.history'));
            const gacRawU7PieDistribUrl = @json(route('google.shopping.campaigns.u7.distribution'));
            const gacRawU7PieHistoryUrl = @json(route('google.shopping.campaigns.u7.history'));
            window.gacRawRule = @json($googleShoppingRule);
            let table;
            let gacRawU7PieChart = null;
            let gacRawU7PieRefreshTimer = null;
            let gacRawBadgeChart = null;
            let gacRawActiveBadgeMetric = null;
            let gacRawActiveBadgeLabel = '';
            const GAC_RAW_U7_PIE_MODAL_CHART_H = 400;

            function updatePageInfoBadge() {
                const el = document.getElementById('gac-raw-page-info');
                if (!el || !table) return;
                try {
                    const p = table.getPage();
                    const max = table.getPageMax();
                    el.textContent = 'Page: ' + p + ' / ' + max;
                } catch (e) {
                    el.textContent = 'Page: —';
                }
            }

            function gacRawRefreshTableUiSoon() {
                setTimeout(function() {
                    updatePageInfoBadge();
                    updateMetricBadges();
                }, 0);
            }

            function gacRawFilterParamVal(id) {
                var el = document.getElementById(id);
                return (el && el.value) ? el.value : 'all';
            }

            /** Trim and cap the campaign search box; empty values are sent as ''. */
            function gacRawSearchQueryVal() {
                var el = document.getElementById('gac-filter-search');
                if (!el) return '';
                var v = String(el.value || '').replace(/\s+/g, ' ').trim();
                return v.length > 100 ? v.slice(0, 100) : v;
            }

            /** Filtered row count for badge + Tabulator remote pagination (coerce strings; prefer server fields). */
            function gacRawFilteredRowCountFromResponse(response) {
                if (!response || typeof response !== 'object') {
                    return 0;
                }
                var n = Number(response.last_row);
                if (!Number.isFinite(n)) {
                    n = Number(response.total);
                }
                if (!Number.isFinite(n) && response.summary != null && response.summary.filtered_row_count != null) {
                    n = Number(response.summary.filtered_row_count);
                }
                if (!Number.isFinite(n) || n < 0) {
                    return 0;
                }
                return Math.floor(n);
            }

            function gacRawSummaryFromResponse(response) {
                var spiEl = document.getElementById('gac-raw-summary-spi30-val');
                var acosEl = document.getElementById('gac-raw-summary-acos-val');
                var campaignsEl = document.getElementById('faasCampaignsValue');
                if (!response || typeof response !== 'object' || !response.summary) {
                    if (spiEl) spiEl.textContent = '—';
                    if (acosEl) acosEl.textContent = '—';
                    if (campaignsEl) campaignsEl.textContent = '0';
                    return;
                }
                var s = response.summary;
                if (spiEl) {
                    if (s.spi30 !== null && s.spi30 !== undefined && !isNaN(Number(s.spi30))) {
                        spiEl.textContent = Number(s.spi30).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    } else {
                        spiEl.textContent = '—';
                    }
                }
                if (acosEl) {
                    if (s.acos_pct !== null && s.acos_pct !== undefined && !isNaN(Number(s.acos_pct))) {
                        acosEl.textContent = String(Math.round(Number(s.acos_pct))) + '%';
                    } else {
                        acosEl.textContent = '—';
                    }
                }
                if (campaignsEl) {
                    var n = Number(s.filtered_row_count);
                    campaignsEl.textContent = Number.isFinite(n) ? Math.round(n).toLocaleString() : '0';
                }
            }

            function gacRawCurrentFilterParams() {
                return {
                    filter_ub7: gacRawFilterParamVal('gac-filter-ub7'),
                    filter_ub1: gacRawFilterParamVal('gac-filter-ub1'),
                    filter_acos: gacRawFilterParamVal('gac-filter-acos'),
                    filter_stat: gacRawFilterParamVal('gac-filter-stat'),
                    q: gacRawSearchQueryVal(),
                };
            }

            function gacRawReloadGridForFilters() {
                if (!table) return;
                try {
                    table.setPage(1);
                } catch (e) { /* ignore */ }
                table.setData(dataUrl);
                gacRawRefreshU7PieChartDebounced();
            }

            function gacRawPieFilterPayload() {
                var p = gacRawCurrentFilterParams();
                return {
                    filter_ub1: p.filter_ub1,
                    filter_acos: p.filter_acos,
                    filter_stat: p.filter_stat,
                    q: p.q
                };
            }

            function gacRawU7PieModalIsOpen() {
                var m = document.getElementById('gacRawU7PieModal');
                return !!(m && m.classList.contains('show'));
            }

            function gacRawOpenU7HistoryModal(bucketKey, sliceLabel) {
                var modalEl = document.getElementById('gacRawU7HistoryModal');
                var titleEl = document.getElementById('gacRawU7HistoryModalLabel');
                var subEl = document.getElementById('gacRawU7HistoryModalSub');
                var loadEl = document.getElementById('gacRawU7HistoryModalLoading');
                var errEl = document.getElementById('gacRawU7HistoryModalError');
                var tbl = document.getElementById('gacRawU7HistoryTable');
                var tbody = document.getElementById('gacRawU7HistoryTableBody');
                if (!modalEl || !tbody) {
                    return;
                }
                if (titleEl) {
                    titleEl.textContent = 'U7% — ' + (sliceLabel || bucketKey) + ' — last 30 days';
                }
                if (subEl) {
                    subEl.textContent = 'Daily row counts for the selected band. Same U1/Sts filters as the grid; U7 filter ignored. Each day uses the 30-day window ending on that date.';
                }
                errEl.classList.add('d-none');
                errEl.textContent = '';
                tbl.classList.add('d-none');
                tbody.innerHTML = '';
                loadEl.classList.remove('d-none');
                loadEl.textContent = 'Loading…';
                document.querySelectorAll('#gacRawU7HistoryTable thead [data-u7-bucket-col]').forEach(function(th) {
                    th.classList.remove('table-secondary');
                    if (th.getAttribute('data-u7-bucket-col') === bucketKey) {
                        th.classList.add('table-secondary');
                    }
                });
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                var p = gacRawPieFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: gacRawU7PieHistoryUrl,
                    type: 'POST',
                    data: {
                        _token: tok,
                        days: 30,
                        bucket: bucketKey,
                        filter_ub1: p.filter_ub1,
                        filter_acos: p.filter_acos,
                        filter_stat: p.filter_stat,
                        q: p.q
                    },
                    success: function(res) {
                        loadEl.classList.add('d-none');
                        if (!res || !res.ok || !res.days || !res.days.length) {
                            errEl.textContent = (res && res.reason) ? ('Could not load history (' + res.reason + ').') : 'No history data.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        tbl.classList.remove('d-none');
                        var frag = document.createDocumentFragment();
                        res.days.forEach(function(row) {
                            var tr = document.createElement('tr');
                            var td0 = document.createElement('td');
                            td0.textContent = row.date || '';
                            tr.appendChild(td0);
                            ['lt66', '66_99', 'gt99', 'na', 'total'].forEach(function(k) {
                                var td = document.createElement('td');
                                td.textContent = String(row[k] != null ? row[k] : '');
                                if (k === bucketKey) {
                                    td.classList.add('fw-semibold');
                                }
                                tr.appendChild(td);
                            });
                            frag.appendChild(tr);
                        });
                        tbody.appendChild(frag);
                    },
                    error: function() {
                        loadEl.classList.add('d-none');
                        errEl.textContent = 'Request failed.';
                        errEl.classList.remove('d-none');
                    }
                });
            }

            function gacRawRefreshU7PieChartDebounced() {
                if (gacRawU7PieRefreshTimer) {
                    clearTimeout(gacRawU7PieRefreshTimer);
                }
                gacRawU7PieRefreshTimer = setTimeout(function() {
                    if (gacRawU7PieModalIsOpen()) {
                        gacRawRefreshU7PieChart();
                    }
                }, 280);
            }

            function gacRawU7PieDataLabelFormatter() {
                var rp = Math.round(this.percentage);
                var fs = rp < 4 ? '34px' : '46px';
                return '<span style="color:#fff;text-shadow:0 0 5px rgba(0,0,0,0.9);font-size:' + fs + ';font-weight:800">' + rp + '%</span>';
            }

            function gacRawRefreshU7PieChart() {
                var box = document.getElementById('gacRawU7Pie');
                if (!box) {
                    return;
                }
                if (!gacRawU7PieModalIsOpen()) {
                    return;
                }
                if (typeof Highcharts === 'undefined') {
                    box.innerHTML = '<p class="small text-muted mb-0">—</p>';
                    return;
                }
                var p = gacRawPieFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: gacRawU7PieDistribUrl,
                    type: 'POST',
                    data: {
                        _token: tok,
                        filter_ub1: p.filter_ub1,
                        filter_acos: p.filter_acos,
                        filter_stat: p.filter_stat,
                        q: p.q
                    },
                    success: function(res) {
                        if (gacRawU7PieChart) {
                            try {
                                gacRawU7PieChart.destroy();
                            } catch (e0) {}
                            gacRawU7PieChart = null;
                        }
                        if (!gacRawU7PieModalIsOpen()) {
                            return;
                        }
                        if (!res || !res.ok) {
                            box.innerHTML = '<p class="small text-muted mb-0 px-1">No chart</p>';
                            return;
                        }
                        box.innerHTML = '';
                        var b = res.buckets || {};
                        var lt = b.lt66 || 0;
                        var mid = b['66_99'] || 0;
                        var gt = b.gt99 || 0;
                        var na = b.na || 0;
                        var seriesData = [];
                        if (lt > 0) {
                            seriesData.push({ name: '< 66%', y: lt, color: '#dc2626', bucket: 'lt66' });
                        }
                        if (mid > 0) {
                            seriesData.push({ name: '66–99%', y: mid, color: '#16a34a', bucket: '66_99' });
                        }
                        if (gt > 0) {
                            seriesData.push({ name: '> 99%', y: gt, color: '#db2777', bucket: 'gt99' });
                        }
                        if (na > 0) {
                            seriesData.push({ name: 'N/A', y: na, color: '#9ca3af', bucket: 'na' });
                        }
                        var tot = res.total || 0;
                        if (!seriesData.length || tot < 1) {
                            box.innerHTML = '<p class="small text-muted mb-0">No rows</p>';
                            return;
                        }
                        if (!gacRawU7PieModalIsOpen()) {
                            return;
                        }
                        gacRawU7PieChart = Highcharts.chart('gacRawU7Pie', {
                            chart: { type: 'pie', backgroundColor: 'transparent', height: GAC_RAW_U7_PIE_MODAL_CHART_H, spacing: [12, 12, 12, 12] },
                            credits: { enabled: false },
                            exporting: { enabled: false },
                            title: { text: null },
                            tooltip: {
                                useHTML: true,
                                outside: false,
                                formatter: function() {
                                    var rn = Math.round(this.point.y);
                                    var rp = Math.round(this.percentage);
                                    return '<span style="color:' + this.point.color + '">\u25cf</span> <b>' + this.point.name + '</b><br/>'
                                        + 'Rows: <b>' + rn + '</b> (' + rp + '%)<br/><span style="font-size:11px;color:#6b7280">Click for 30-day history</span>';
                                }
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    size: '100%',
                                    borderWidth: 1,
                                    borderColor: 'rgba(255,255,255,0.85)',
                                    states: {
                                        hover: {
                                            brightness: 0.08,
                                            halo: { size: 0 }
                                        }
                                    },
                                    point: {
                                        events: {
                                            click: function() {
                                                var bk = this.options.bucket;
                                                if (bk) {
                                                    gacRawOpenU7HistoryModal(bk, this.name);
                                                }
                                            }
                                        }
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        useHTML: true,
                                        distance: -120,
                                        connectorWidth: 0,
                                        allowOverlap: true,
                                        crop: false,
                                        overflow: 'allow',
                                        style: {
                                            fontSize: '38px',
                                            fontWeight: '700',
                                            textOutline: 'none'
                                        },
                                        formatter: gacRawU7PieDataLabelFormatter
                                    }
                                }
                            },
                            series: [{ type: 'pie', name: 'Rows', data: seriesData }]
                        });
                        setTimeout(function() {
                            if (gacRawU7PieChart && typeof gacRawU7PieChart.reflow === 'function') {
                                gacRawU7PieChart.reflow();
                            }
                        }, 50);
                    },
                    error: function() {
                        if (gacRawU7PieChart) {
                            try {
                                gacRawU7PieChart.destroy();
                            } catch (e1) {}
                            gacRawU7PieChart = null;
                        }
                        if (gacRawU7PieModalIsOpen() && box) {
                            box.innerHTML = '<p class="small text-danger mb-0">Error</p>';
                        }
                    }
                });
            }

            var u7PieModalEl = document.getElementById('gacRawU7PieModal');
            if (u7PieModalEl) {
                u7PieModalEl.addEventListener('shown.bs.modal', function() {
                    gacRawRefreshU7PieChart();
                });
                u7PieModalEl.addEventListener('hidden.bs.modal', function() {
                    if (gacRawU7PieChart) {
                        try {
                            gacRawU7PieChart.destroy();
                        } catch (e2) {}
                        gacRawU7PieChart = null;
                    }
                    var pieBox = document.getElementById('gacRawU7Pie');
                    if (pieBox) {
                        pieBox.innerHTML = '';
                    }
                });
            }

            // Delegated copy-to-clipboard for the campaign-name copy icon.
            (function () {
                function copyText(text) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        return navigator.clipboard.writeText(text);
                    }
                    return new Promise(function (resolve, reject) {
                        try {
                            var ta = document.createElement('textarea');
                            ta.value = text;
                            ta.style.position = 'fixed';
                            ta.style.opacity = '0';
                            document.body.appendChild(ta);
                            ta.focus(); ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                            resolve();
                        } catch (e) { reject(e); }
                    });
                }
                document.addEventListener('click', function (e) {
                    var icon = e.target.closest ? e.target.closest('.gac-copy-name') : null;
                    if (!icon) return;
                    e.stopPropagation();
                    e.preventDefault();
                    var text = icon.getAttribute('data-copy') || '';
                    // Decode the HTML entities stored in the attribute.
                    var tmp = document.createElement('textarea');
                    tmp.innerHTML = text;
                    text = tmp.value;
                    copyText(text).then(function () {
                        var prev = icon.className;
                        icon.className = 'fas fa-check gac-copy-name';
                        icon.style.color = '#22c55e';
                        setTimeout(function () {
                            icon.className = prev;
                            icon.style.color = '#94a3b8';
                        }, 1000);
                    }).catch(function () {});
                });
            })();

            table = new Tabulator('#google-ads-campaigns-raw-table', {
                ajaxURL: dataUrl,
                ajaxConfig: { method: 'GET', credentials: 'same-origin' },
                ajaxParams: function() {
                    return gacRawCurrentFilterParams();
                },
                // Fixed height prevents Tabulator's variable-height resize loop from recursing on Windows/browser zoom.
                height: '650px',
                layout: 'fitData',
                layoutColumnsOnNewData: false,
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 200, 500, 1000],
                paginationCounter: 'rows',
                paginationButtonCount: 12,
                paginationInitialPage: 1,
                sortMode: 'remote',
                placeholder: 'No rows in google_ads_campaigns.',
                selectableRows: true,
                autoColumns: true,
                autoColumnsDefinitions: function(defs) {
                    if (!defs.some(function(d) { return d.field === '__gac_select'; })) {
                        defs.unshift({
                            title: '',
                            field: '__gac_select',
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 40,
                            minWidth: 40,
                        });
                    }
                    var moneySpendTitles = {
                        spend: 'Spend',
                        l7_spend: 'L7 Spend',
                        l2_spend: 'L2 Spend',
                        l1_spend: 'L1 Spend',
                    };
                    var utilizedStyleTitles = {
                        cpc_L30: 'CPC',
                        cpc_L7: 'L7 CPC',
                        cpc_L2: 'L2 CPC',
                        cpc_L1: 'L1 CPC',
                        ad_sold_L30: 'Sold',
                        ad_sales_L30: 'Sales',
                        acos_l30: 'ACOS',
                        cvr_l30: 'CVR',
                        ub7: '7 UB%',
                        ub2: '2 UB%',
                        ub1: '1 UB%',
                        bgt: 'BGT',
                        sbgt: 'SBGT',
                        sbid: 'SBID',
                    };
                    var moneyFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };
                    /** Same as moneyFormatter but rounded to whole units with thousands separator. */
                    var moneyRoundedFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return Math.round(v).toLocaleString();
                    };
                    var intLocaleFormatter = function(c) {
                        var v = c.getValue();
                        if (v === null || v === undefined || v === '') return '';
                        var n = parseInt(v, 10);
                        if (!isFinite(n)) return String(v);
                        return n.toLocaleString();
                    };
                    var pct0Formatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return Math.round(v) + '%';
                    };
                    /** Same bands as google-shopping-utilized 7 UB% / 1 UB%: green 66–99%, pink &gt;99%, red &lt;66%. */
                    var ubUtilColorFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) v = 0;
                        var td = c.getElement();
                        if (td) {
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (v >= 66 && v <= 99) {
                                td.classList.add('green-bg');
                            } else if (v > 99) {
                                td.classList.add('pink-bg');
                            } else if (v < 66) {
                                td.classList.add('red-bg');
                            }
                        }
                        return Math.round(v) + '%';
                    };
                    /** ACOS L30 text color: <10 pink, <20 green, <30 blue, <40 yellow, 40–50 orange, >50 red. */
                    var acosFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        var td = c.getElement();
                        if (td) {
                            td.classList.remove('acos-pink', 'acos-green', 'acos-blue', 'acos-yellow', 'acos-orange', 'acos-red');
                        }
                        if (!isFinite(v)) return '';
                        if (td) {
                            if (v > 50) {
                                td.classList.add('acos-red');
                            } else if (v >= 40) {
                                td.classList.add('acos-orange');
                            } else if (v >= 30) {
                                td.classList.add('acos-yellow');
                            } else if (v >= 20) {
                                td.classList.add('acos-blue');
                            } else if (v >= 10) {
                                td.classList.add('acos-green');
                            } else {
                                td.classList.add('acos-pink');
                            }
                        }
                        return Math.round(v) + '%';
                    };
                    var sbidFormatter = function(c) {
                        var v = c.getValue();
                        if (v === null || v === undefined || v === '') return '—';
                        var n = parseFloat(v);
                        if (!isFinite(n)) return '—';
                        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };
                    var campaignStatusFormatter = function(c) {
                        var v = c.getValue();
                        var s = v === null || v === undefined ? '' : String(v).trim();
                        if (s === '') {
                            return '<span class="gac-raw-status-cell text-muted" title="—" aria-label="No status">—</span>';
                        }
                        var u = s.toUpperCase();
                        var dotColor = '#eab308';
                        if (u === 'ENABLED') {
                            dotColor = '#22c55e';
                        } else if (u === 'PAUSED') {
                            dotColor = '#fb923c';
                        } else if (u === 'REMOVED') {
                            dotColor = '#64748b';
                        } else if (u === 'UNKNOWN' || u === 'UNSPECIFIED') {
                            dotColor = '#a855f7';
                        } else if (u === 'SUSPENDED') {
                            dotColor = '#f43f5e';
                        }
                        var tipAttr = s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/'/g, '&#39;');
                        var dot = '<span aria-hidden="true" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + dotColor + ';"></span>';
                        return '<span class="gac-raw-status-cell" title="' + tipAttr + '" aria-label="' + tipAttr + '" style="display:inline-flex;align-items:center;justify-content:center;">' + dot + '</span>';
                    };
                    /** Campaign name + a copy-to-clipboard icon. */
                    var campaignNameFormatter = function(c) {
                        var v = c.getValue();
                        var s = v === null || v === undefined ? '' : String(v);
                        var esc = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        var attr = esc.replace(/'/g, '&#39;');
                        var copy = '<i class="fas fa-copy gac-copy-name" role="button" tabindex="0" title="Copy campaign name"'
                                 + ' data-copy="' + attr + '" style="margin-left:6px;color:#94a3b8;cursor:pointer;flex-shrink:0;"></i>';
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;max-width:100%;">'
                             + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc + '</span>'
                             + copy + '</span>';
                    };
                    /** Server-side sort whitelist — keep in sync with applyRawGridSort() in the controller. */
                    var sortableFields = {
                        campaign_name: true,
                        spend: true,
                        l7_spend: true,
                        l2_spend: true,
                        l1_spend: true,
                        metrics_clicks: true,
                        ad_sold_L30: true,
                        ad_sales_L30: true,
                        acos_l30: true,
                        cvr_l30: true,
                        ub7: true,
                        ub2: true,
                        ub1: true,
                        bgt: true,
                    };
                    defs.forEach(function(col) {
                        if (col.field === '__gac_select') {
                            col.headerSort = false;
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 40;
                            col.width = 40;
                            return;
                        }
                        // Avoid a uniform minWidth on every column — Tabulator fits width to data unless width/minWidth is set
                        col.headerSort = Object.prototype.hasOwnProperty.call(sortableFields, col.field);
                        col.hozAlign = 'center';
                        col.headerHozAlign = 'center';
                        if (col.field === 'campaign_name') {
                            col.minWidth = 141;
                            col.formatter = campaignNameFormatter;
                        } else if (col.field === 'campaign_status') {
                            col.minWidth = 44;
                            col.width = 44;
                            col.title = 'Sts';
                            col.formatter = campaignStatusFormatter;
                        } else {
                            col.minWidth = 50;
                        }
                        if (col.field === 'id' || col.field === 'campaign_id' || col.field === 'date') {
                            col.visible = false;
                        }
                        // L7 / L2 Spend are still computed server-side (the SQL joins them so
                        // utilized-style enrichments — UB%, CPC, suggested SBID — still work)
                        // but the columns are hidden from the grid per product request.
                        // Sorting by these fields is still allowed (server whitelist unchanged)
                        // in case future UI surfaces them.
                        if (col.field === 'l7_spend' || col.field === 'l2_spend') {
                            col.visible = false;
                        }
                        if (Object.prototype.hasOwnProperty.call(moneySpendTitles, col.field)) {
                            col.title = moneySpendTitles[col.field];
                            col.formatter = moneyRoundedFormatter;
                            col.minWidth = Math.max(col.minWidth || 0, 70);
                        }
                        if (Object.prototype.hasOwnProperty.call(utilizedStyleTitles, col.field)) {
                            col.title = utilizedStyleTitles[col.field];
                            if (col.field === 'ad_sold_L30') {
                                col.formatter = intLocaleFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'ad_sales_L30') {
                                col.formatter = moneyRoundedFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 77);
                            } else if (col.field === 'acos_l30') {
                                col.formatter = acosFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 64);
                            } else if (col.field === 'cvr_l30') {
                                // CVR = (Sold / Clicks) * 100 — formatted with 1 decimal,
                                // matches the toolbar CVR badge value to the percent.
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) return '0%';
                                    return v.toFixed(1) + '%';
                                };
                                col.minWidth = Math.max(col.minWidth || 0, 60);
                            } else if (col.field === 'ub7' || col.field === 'ub2' || col.field === 'ub1') {
                                col.formatter = ubUtilColorFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'sbgt') {
                                col.formatter = intLocaleFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'sbid') {
                                col.formatter = sbidFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 70);
                            } else if (col.field === 'bgt') {
                                col.formatter = moneyRoundedFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else {
                                col.formatter = moneyFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 70);
                            }
                        }
                        if (col.field === 'metrics_clicks') {
                            col.title = 'Click';
                            col.formatter = intLocaleFormatter;
                            col.minWidth = Math.max(col.minWidth || 0, 57);
                        }
                    });
                    return defs;
                },
                ajaxResponse: function(url, params, response) {
                    if (!response || typeof response !== 'object') {
                        var te0 = document.getElementById('gac-raw-total');
                        if (te0) {
                            te0.textContent = 'Total rows: —';
                        }
                        return { last_page: 1, last_row: 0, data: [] };
                    }
                    const lastPage = Math.max(1, parseInt(response.last_page, 10) || 1);
                    const lastRow = gacRawFilteredRowCountFromResponse(response);
                    const rows = Array.isArray(response.data) ? response.data : [];

                    const totalEl = document.getElementById('gac-raw-total');
                    if (totalEl) {
                        totalEl.textContent = 'Total rows: ' + lastRow.toLocaleString();
                    }

                    gacRawSummaryFromResponse(response);
                    gacRawRefreshTableUiSoon();

                    return {
                        last_page: lastPage,
                        last_row: lastRow,
                        data: rows,
                    };
                },
            });

            ['gac-filter-ub7', 'gac-filter-ub1', 'gac-filter-acos', 'gac-filter-stat'].forEach(function(fid) {
                var fel = document.getElementById(fid);
                if (fel) {
                    fel.addEventListener('change', gacRawReloadGridForFilters);
                }
            });

            // Campaign-name search: debounce keystrokes so we only hit the server after 300ms of inactivity.
            // 'search' fires on the native ✕ clear button and on Enter, both of which should reload immediately.
            var gacRawSearchEl = document.getElementById('gac-filter-search');
            if (gacRawSearchEl) {
                var gacRawSearchTimer = null;
                var gacRawLastSearchVal = gacRawSearchQueryVal();
                var gacRawSearchScheduleReload = function(immediate) {
                    if (gacRawSearchTimer) {
                        clearTimeout(gacRawSearchTimer);
                        gacRawSearchTimer = null;
                    }
                    var run = function() {
                        var v = gacRawSearchQueryVal();
                        if (v === gacRawLastSearchVal) return;
                        gacRawLastSearchVal = v;
                        gacRawReloadGridForFilters();
                    };
                    if (immediate) { run(); } else { gacRawSearchTimer = setTimeout(run, 300); }
                };
                gacRawSearchEl.addEventListener('input', function() { gacRawSearchScheduleReload(false); });
                gacRawSearchEl.addEventListener('search', function() { gacRawSearchScheduleReload(true); });
                gacRawSearchEl.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        gacRawSearchScheduleReload(true);
                    }
                });
            }

            table.on('pageLoaded', function() {
                gacRawRefreshTableUiSoon();
            });
            table.on('dataLoaded', function() {
                gacRawRefreshTableUiSoon();
            });

            table.on('dataLoadError', function(error) {
                console.error('google_ads_campaigns raw data load error', error);
                const totalEl = document.getElementById('gac-raw-total');
                if (totalEl) {
                    totalEl.textContent = 'Load error (see console)';
                }
            });

            document.getElementById('gac-raw-refresh').addEventListener('click', function() {
                Promise.resolve(table.setData(dataUrl)).finally(gacRawRefreshTableUiSoon);
            });

            document.getElementById('gac-raw-export').addEventListener('click', function() {
                table.download('csv', 'google_ads_campaigns_page.csv');
            });

            function gacShowPushResult(title, body, variant) {
                var wrap = document.getElementById('gac-raw-push-result');
                var tEl = document.getElementById('gac-raw-push-result-title');
                var pre = document.getElementById('gac-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                if (variant === 'error') {
                    wrap.classList.add('alert-danger');
                } else {
                    wrap.classList.add('alert-success');
                }
                tEl.textContent = title;
                pre.textContent = body || '(no console output)';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function gacShowPushLoading(title, detail) {
                var wrap = document.getElementById('gac-raw-push-result');
                var tEl = document.getElementById('gac-raw-push-result-title');
                var pre = document.getElementById('gac-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                wrap.classList.add('alert-info');
                tEl.innerHTML = '<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i>' + (title || 'Working…');
                pre.textContent = detail || 'Running on the server — please keep this tab open until finished.';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function gacPushTargetCampaignIds() {
                if (!table) return [];
                var selected = table.getSelectedData();
                var rows = (selected && selected.length > 0) ? selected : table.getData();
                var seen = {};
                var ids = [];
                rows.forEach(function(row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || cid === '') return;
                    var s = String(cid).replace(/\D/g, '');
                    if (!s) s = String(cid).trim();
                    if (s && !seen[s]) {
                        seen[s] = true;
                        ids.push(s);
                    }
                });
                return ids;
            }

            function gacRunArtisanPush(opts) {
                var campaignIds = opts.campaign_ids || [];
                if (!campaignIds.length) {
                    window.alert('No campaigns to push: this page has no rows with a campaign_id. Load data or switch page.');
                    return;
                }
                if (!window.confirm(opts.confirmMsg)) {
                    return;
                }
                var sbgtB = document.getElementById('gac-raw-push-sbgt');
                var sbidB = document.getElementById('gac-raw-push-sbid');
                if (sbgtB) sbgtB.disabled = true;
                if (sbidB) sbidB.disabled = true;
                var origHtml = opts.btn.innerHTML;
                opts.btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing…';

                gacShowPushLoading(opts.loadingTitle, opts.loadingDetail);

                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch(opts.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ campaign_ids: campaignIds }),
                })
                    .then(function(res) {
                        return res.json().then(function(body) {
                            return { ok: res.ok, status: res.status, body: body };
                        });
                    })
                    .then(function(out) {
                        var b = out.body || {};
                        var cmd = b.command || 'command';
                        var success = out.ok && b.ok !== false;
                        var title = cmd + ' — ' + (success ? 'finished' : 'failed');
                        if (b.exit_code != null) {
                            title += ' (exit ' + b.exit_code + ')';
                        }
                        var text = (b.message ? b.message + '\n\n' : '') + (b.output || '');
                        gacShowPushResult(title, text, success ? 'success' : 'error');
                        if (success && table) {
                            Promise.resolve(table.setData(dataUrl)).finally(gacRawRefreshTableUiSoon);
                        }
                    })
                    .catch(function(err) {
                        gacShowPushResult('Request failed', String(err && err.message ? err.message : err), 'error');
                    })
                    .finally(function() {
                        opts.btn.innerHTML = origHtml;
                        if (sbgtB) sbgtB.disabled = false;
                        if (sbidB) sbidB.disabled = false;
                    });
            }

            var pullDataBtn = document.getElementById('gac-raw-pull-data');
            if (pullDataBtn) {
                pullDataBtn.addEventListener('click', function(ev) {
                    if (ev && ev.target && ev.target.id === 'gac-raw-pull-days') {
                        return;
                    }
                    var daysEl = document.getElementById('gac-raw-pull-days');
                    var days = daysEl ? parseInt(daysEl.value, 10) : 1;
                    if (!Number.isFinite(days) || days < 1) days = 1;
                    if (days > 30) days = 30;
                    if (daysEl) daysEl.value = String(days);

                    if (!window.confirm('Run app:fetch-google-ads-campaigns for the last ' + days + ' day(s)? This pulls campaigns + metrics from Google Ads / GA4 and waits until complete (may take several minutes).')) {
                        return;
                    }

                    var origHtml = pullDataBtn.innerHTML;
                    pullDataBtn.disabled = true;
                    pullDataBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pulling…';
                    gacShowPushLoading('Pulling data (app:fetch-google-ads-campaigns)…',
                        'Fetching the last ' + days + ' day(s) from Google Ads + GA4. This runs synchronously — do not close this tab.');

                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    fetch(gacRawPullDataUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ days: days }),
                    })
                        .then(function(res) {
                            return res.json().then(function(body) {
                                return { ok: res.ok, status: res.status, body: body };
                            });
                        })
                        .then(function(out) {
                            var b = out.body || {};
                            var cmd = b.command || 'app:fetch-google-ads-campaigns';
                            var success = out.ok && b.ok !== false;
                            var title = cmd + ' — ' + (success ? 'finished' : 'failed');
                            if (b.exit_code != null) {
                                title += ' (exit ' + b.exit_code + ')';
                            }
                            var text = (b.message ? b.message + '\n\n' : '') + (b.output || '');
                            gacShowPushResult(title, text, success ? 'success' : 'error');
                            if (success && table) {
                                Promise.resolve(table.setData(dataUrl)).finally(gacRawRefreshTableUiSoon);
                            }
                        })
                        .catch(function(err) {
                            gacShowPushResult('Request failed', String(err && err.message ? err.message : err), 'error');
                        })
                        .finally(function() {
                            pullDataBtn.innerHTML = origHtml;
                            pullDataBtn.disabled = false;
                        });
                });
            }

            var pushSbgtBtn = document.getElementById('gac-raw-push-sbgt');
            if (pushSbgtBtn) {
                pushSbgtBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s)')
                        : ('all ' + ids.length + ' row(s) on this page');
                    gacRunArtisanPush({
                        url: gacRawPushSbgtUrl,
                        btn: pushSbgtBtn,
                        campaign_ids: ids,
                        confirmMsg: 'Push SBGT to ' + scope + '? Each row is sent to Google Ads using the SBGT value shown in the grid (direct by campaign_id).',
                        loadingTitle: 'Pushing SBGT (budget:update-shopping)…',
                        loadingDetail: 'Updating budgets for ' + ids.length + ' campaign id(s). Waiting for Google Ads API — do not close this tab.',
                    });
                });
            }
            var pushSbidBtn = document.getElementById('gac-raw-push-sbid');
            if (pushSbidBtn) {
                pushSbidBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s)')
                        : ('all ' + ids.length + ' row(s) on this page');
                    gacRunArtisanPush({
                        url: gacRawPushSbidUrl,
                        btn: pushSbidBtn,
                        campaign_ids: ids,
                        confirmMsg: 'Push SBID to ' + scope + '? Each row is sent to Google Ads using the SBID value shown in the grid (direct by campaign_id). Rows with SBID — are skipped.',
                        loadingTitle: 'Pushing SBID (sbid:update)…',
                        loadingDetail: 'Updating SBIDs for ' + ids.length + ' campaign id(s). Waiting for Google Ads API — do not close this tab.',
                    });
                });
            }

            function gacNum(id) {
                var el = document.getElementById(id);
                if (!el) return NaN;
                return parseFloat(String(el.value).trim());
            }
            function gacInt(id) {
                var el = document.getElementById(id);
                if (!el) return NaN;
                return parseInt(String(el.value).trim(), 10);
            }
            function gacSetVal(id, v) {
                var el = document.getElementById(id);
                if (el && v != null && v !== '') el.value = String(v);
            }
            var gacCurrentSbgtBands = [];

            function gacNormalizeSbgtBandsForUi(bands) {
                if (!Array.isArray(bands) || !bands.length) return [];
                var hasFromTo = bands.some(function(b) {
                    return b && (b.acos_from !== undefined && b.acos_from !== null
                        || b.acos_to !== undefined && b.acos_to !== null);
                });
                if (hasFromTo) {
                    return bands.map(function(b) {
                        return {
                            acos_from: Number(b.acos_from ?? 0),
                            acos_to: Number(b.acos_to ?? 9999),
                            sbgt: b.sbgt,
                            label: b.label ?? '',
                            color: b.color ?? '#6c757d',
                        };
                    });
                }
                var sorted = bands.slice().sort(function(a, b) {
                    return (Number(a.acos_max) || 0) - (Number(b.acos_max) || 0);
                });
                var prevTo = 0;
                return sorted.map(function(b) {
                    var to = Number(b.acos_max ?? 9999);
                    var row = {
                        acos_from: prevTo,
                        acos_to: to,
                        sbgt: b.sbgt,
                        label: b.label ?? '',
                        color: b.color ?? '#6c757d',
                    };
                    prevTo = to;
                    return row;
                });
            }

            function gacRenderSbgtBands(bands) {
                var tbody = document.getElementById('gac-sbgt-bands-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function(band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm"'
                        + ' value="' + String(band.label ?? '').replace(/"/g, '&quot;') + '"'
                        + ' data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm"'
                        + ' value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">'
                        + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_from ?? '') + '" data-idx="' + i + '" data-field="acos_from"'
                        + ' placeholder="0"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_to ?? '') + '" data-idx="' + i + '" data-field="acos_to"'
                        + ' placeholder="9999"></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm"'
                        + ' value="' + (band.sbgt ?? '') + '" data-idx="' + i + '" data-field="sbgt"></td>'
                        + '<td class="text-center">'
                        + '<button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Remove band">'
                        + '<i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('input[data-idx]').forEach(function(inp) {
                    inp.addEventListener('input', function() {
                        var idx = +this.dataset.idx;
                        var fld = this.dataset.field;
                        if (!gacCurrentSbgtBands[idx]) return;
                        gacCurrentSbgtBands[idx][fld] = (fld === 'sbgt')
                            ? (this.value === '' ? '' : parseInt(this.value, 10))
                            : (fld === 'acos_from' || fld === 'acos_to')
                                ? (this.value === '' ? '' : parseFloat(this.value))
                                : this.value;
                        if (fld === 'label' || fld === 'color') {
                            var row = this.closest('tr');
                            var chip = row ? row.querySelector('.badge') : null;
                            var band = gacCurrentSbgtBands[idx];
                            if (chip) {
                                chip.style.background = band.color || '#6c757d';
                                chip.textContent = band.label || '—';
                            }
                        }
                    });
                });

                tbody.querySelectorAll('[data-remove-idx]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        gacCurrentSbgtBands.splice(+this.dataset.removeIdx, 1);
                        gacRenderSbgtBands(gacCurrentSbgtBands);
                    });
                });
            }

            function gacLoadSbgtBandsFromRule(sbgt) {
                var bands = gacNormalizeSbgtBandsForUi(
                    (sbgt && Array.isArray(sbgt.bands)) ? sbgt.bands : []
                );
                gacCurrentSbgtBands = bands;
                gacRenderSbgtBands(gacCurrentSbgtBands);
            }

            function gacCollectSbgtBandsPayload() {
                return (gacCurrentSbgtBands || []).map(function(b) {
                    return {
                        acos_from: (b.acos_from === '' || b.acos_from === null || b.acos_from === undefined)
                            ? NaN : parseFloat(b.acos_from),
                        acos_to: (b.acos_to === '' || b.acos_to === null || b.acos_to === undefined)
                            ? NaN : parseFloat(b.acos_to),
                        sbgt: (b.sbgt === '' || b.sbgt === null || b.sbgt === undefined)
                            ? NaN : parseInt(b.sbgt, 10),
                        label: (b.label || '').toString(),
                        color: (b.color || '#6c757d').toString(),
                    };
                });
            }
            function gacFillSbidForm(sbid) {
                if (!sbid) return;
                gacSetVal('gacSbidUtilLow', sbid.util_low);
                gacSetVal('gacSbidUtilHigh', sbid.util_high);
                gacSetVal('gacSbidOverMultL1', sbid.over_mult_l1);
                gacSetVal('gacSbidUnderMultL1', sbid.under_mult_l1);
                gacSetVal('gacSbidUnderMultL7', sbid.under_mult_l7);
                gacSetVal('gacSbidUnderFallback', sbid.under_fallback);
            }
            function gacCollectSbid() {
                return {
                    util_low: gacNum('gacSbidUtilLow'),
                    util_high: gacNum('gacSbidUtilHigh'),
                    over_mult_l1: gacNum('gacSbidOverMultL1'),
                    under_mult_l1: gacNum('gacSbidUnderMultL1'),
                    under_mult_l7: gacNum('gacSbidUnderMultL7'),
                    under_fallback: gacNum('gacSbidUnderFallback'),
                };
            }
            function gacRawNumber(value) {
                if (value === null || value === undefined || value === '') {
                    return 0;
                }
                if (typeof value === 'number') {
                    return Number.isFinite(value) ? value : 0;
                }
                var n = parseFloat(String(value).replace(/[$,%\s,]/g, ''));
                return Number.isFinite(n) ? n : 0;
            }

            function gacRawWholeMoney(value) {
                return '$' + Math.round(value).toLocaleString();
            }

            function gacRawPercent(numerator, denominator, decimals) {
                if (!Number.isFinite(numerator) || !Number.isFinite(denominator) || denominator <= 0) {
                    return '0%';
                }
                return ((numerator / denominator) * 100).toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }) + '%';
            }

            // Update the metric badges with sums from the rows currently loaded/visible in Tabulator.
            function updateMetricBadges() {
                var spendEl = document.getElementById('faasL30SpendValue');
                var clicksEl = document.getElementById('faasClicksValue');
                var soldEl = document.getElementById('faasL30SoldValue');
                var salesEl = document.getElementById('faasL30SalesValue');
                var acosEl = document.getElementById('faasAcosValue');
                var cvrEl = document.getElementById('faasCvrValue');
                var bgtEl = document.getElementById('faasTotalBgtValue');
                if (!spendEl || !clicksEl || !soldEl || !salesEl || !acosEl || !cvrEl || !bgtEl || !table) {
                    return;
                }

                var spendSum = 0;
                var clicksSum = 0;
                var soldSum = 0;
                var salesSum = 0;
                var bgtSum = 0;
                var rows = [];
                try {
                    rows = table.getData('active') || [];
                } catch (e) {
                    rows = [];
                }

                rows.forEach(function(row) {
                    spendSum += gacRawNumber(row.spend);
                    clicksSum += gacRawNumber(row.metrics_clicks);
                    soldSum += gacRawNumber(row.ad_sold_L30);
                    salesSum += gacRawNumber(row.ad_sales_L30);
                    bgtSum += gacRawNumber(row.bgt);
                });

                spendEl.textContent = gacRawWholeMoney(spendSum);
                clicksEl.textContent = Math.round(clicksSum).toLocaleString();
                soldEl.textContent = Math.round(soldSum).toLocaleString();
                salesEl.textContent = gacRawWholeMoney(salesSum);
                acosEl.textContent = gacRawPercent(spendSum, salesSum, 0);
                cvrEl.textContent = gacRawPercent(soldSum, clicksSum, 1);
                bgtEl.textContent = gacRawWholeMoney(bgtSum);
            }

            function gacRawFormatBadgeChartValue(metric, value) {
                var n = Number(value);
                if (!Number.isFinite(n)) return '—';
                if (metric === 'spend' || metric === 'sales' || metric === 'bgt') {
                    return '$' + Math.round(n).toLocaleString();
                }
                if (metric === 'acos' || metric === 'cvr') {
                    return n.toFixed(1) + '%';
                }
                return Math.round(n).toLocaleString();
            }

            function gacRawVisibleCampaignIds() {
                if (!table) return [];
                var seen = {};
                var out = [];
                try {
                    (table.getData('active') || []).forEach(function(row) {
                        var raw = row && row.campaign_id != null ? String(row.campaign_id) : '';
                        var id = raw.replace(/\D/g, '');
                        if (id && !seen[id]) {
                            seen[id] = true;
                            out.push(id);
                        }
                    });
                } catch (e) {
                    return [];
                }
                return out;
            }

            function gacRawOpenBadgeChart(metric, label) {
                gacRawActiveBadgeMetric = metric;
                gacRawActiveBadgeLabel = label || metric.toUpperCase();
                var modalEl = document.getElementById('gacRawBadgeChartModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                gacRawLoadBadgeChart(metric, gacRawActiveBadgeLabel);
            }

            function gacRawLoadBadgeChart(metric, label) {
                var rangeEl = document.getElementById('gacRawBadgeChartRange');
                var days = parseInt((rangeEl && rangeEl.value) || '32', 10) || 32;
                var titleEl = document.getElementById('gacRawBadgeChartTitle');
                if (titleEl) {
                    titleEl.textContent = (label || metric.toUpperCase()) + ' (Daily L' + days + ')';
                }

                var params = new URLSearchParams({ metric: metric, days: String(days) });
                var ids = gacRawVisibleCampaignIds();
                if (ids.length) {
                    params.set('campaign_ids', ids.join(','));
                }

                fetch(gacRawBadgeHistoryUrl + '?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(res) { return res.json(); })
                    .then(function(resp) {
                        gacRawRenderBadgeChart(metric, (resp && resp.data) || []);
                    })
                    .catch(function(err) {
                        console.error('google shopping badge history failed', err);
                        gacRawRenderBadgeChart(metric, []);
                    });
            }

            function gacRawRenderBadgeChart(metric, data) {
                var canvas = document.getElementById('gacRawBadgeChartCanvas');
                var emptyEl = document.getElementById('gacRawBadgeChartEmpty');
                if (!canvas || typeof Chart === 'undefined') return;

                if (gacRawBadgeChart) {
                    gacRawBadgeChart.destroy();
                    gacRawBadgeChart = null;
                }
                ['gacRawBadgeChartHighest', 'gacRawBadgeChartMedian', 'gacRawBadgeChartLowest'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.textContent = '—';
                });

                if (!data.length) {
                    canvas.style.display = 'none';
                    if (emptyEl) emptyEl.classList.remove('d-none');
                    return;
                }
                canvas.style.display = '';
                if (emptyEl) emptyEl.classList.add('d-none');

                var labels = data.map(function(row) { return row.date; });
                var values = data.map(function(row) { return Number(row.value) || 0; });
                var min = Math.min.apply(Math, values);
                var max = Math.max.apply(Math, values);
                var sorted = values.slice().sort(function(a, b) { return a - b; });
                var mid = Math.floor(sorted.length / 2);
                var median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                var range = (max - min) || 1;
                var yMin = Math.max(0, min - range * 0.1);
                var yMax = max + range * 0.1;
                var red = '#dc3545';
                var green = '#198754';
                var gray = '#6c757d';
                var inverted = metric === 'acos';

                function setStat(id, value) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    el.textContent = gacRawFormatBadgeChartValue(metric, value);
                }
                setStat('gacRawBadgeChartHighest', max);
                setStat('gacRawBadgeChartMedian', median);
                setStat('gacRawBadgeChartLowest', min);

                var pointColors = values.map(function(value, i) {
                    if (i === 0) return gray;
                    if (value === values[i - 1]) return gray;
                    if (inverted) return value < values[i - 1] ? green : red;
                    return value > values[i - 1] ? green : red;
                });

                var medianLinePlugin = {
                    id: 'gacRawMedianLine',
                    afterDraw: function(chart) {
                        var yScale = chart.scales.y;
                        var xScale = chart.scales.x;
                        var ctx = chart.ctx;
                        var y = yScale.getPixelForValue(median);
                        ctx.save();
                        ctx.setLineDash([6, 4]);
                        ctx.strokeStyle = gray;
                        ctx.lineWidth = 1.2;
                        ctx.beginPath();
                        ctx.moveTo(xScale.left, y);
                        ctx.lineTo(xScale.right, y);
                        ctx.stroke();
                        ctx.restore();
                    }
                };

                gacRawBadgeChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: gacRawActiveBadgeLabel,
                            data: values,
                            backgroundColor: 'rgba(13,110,253,0.08)',
                            borderColor: '#0d6efd',
                            borderWidth: 1.6,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointColors
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 20, right: 16, bottom: 10, left: 16 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return gacRawFormatBadgeChartValue(metric, ctx.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: {
                                    callback: function(value) {
                                        return gacRawFormatBadgeChartValue(metric, value);
                                    }
                                }
                            },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } }
                        }
                    },
                    plugins: [medianLinePlugin]
                });
            }

            document.querySelectorAll('.badge-chart-link').forEach(function(el) {
                el.addEventListener('click', function() {
                    var metric = this.dataset.metric;
                    if (!metric) return;
                    gacRawOpenBadgeChart(metric, this.dataset.label || metric.toUpperCase());
                });
            });

            var badgeRangeEl = document.getElementById('gacRawBadgeChartRange');
            if (badgeRangeEl) {
                badgeRangeEl.addEventListener('change', function() {
                    if (gacRawActiveBadgeMetric) {
                        gacRawLoadBadgeChart(gacRawActiveBadgeMetric, gacRawActiveBadgeLabel);
                    }
                });
            }

            var badgeModalEl = document.getElementById('gacRawBadgeChartModal');
            if (badgeModalEl) {
                badgeModalEl.addEventListener('hidden.bs.modal', function() {
                    if (gacRawBadgeChart) {
                        gacRawBadgeChart.destroy();
                        gacRawBadgeChart = null;
                    }
                });
            }


            function gacRefreshRuleFromServer(cb) {
                fetch(gacRawRuleGetUrl, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        if (out.ok && out.body && out.body.rule) {
                            window.gacRawRule = out.body.rule;
                        }
                        if (typeof cb === 'function') cb();
                    })
                    .catch(function() { if (typeof cb === 'function') cb(); });
            }

            var sbgtAddBandBtn = document.getElementById('gac-sbgt-add-band-btn');
            if (sbgtAddBandBtn) {
                sbgtAddBandBtn.addEventListener('click', function() {
                    var bands = gacCurrentSbgtBands || [];
                    var lastTo = bands.length
                        ? Number(bands[bands.length - 1].acos_to ?? 0)
                        : 0;
                    gacCurrentSbgtBands.push({
                        acos_from: lastTo,
                        acos_to: 9999,
                        sbgt: 1,
                        label: 'New band',
                        color: '#6c757d',
                    });
                    gacRenderSbgtBands(gacCurrentSbgtBands);
                });
            }

            var sbgtModalEl = document.getElementById('gacRawSbgtRuleModal');
            if (sbgtModalEl) {
                sbgtModalEl.addEventListener('show.bs.modal', function() {
                    var errEl = document.getElementById('gacRawSbgtRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    gacRefreshRuleFromServer(function() {
                        gacLoadSbgtBandsFromRule((window.gacRawRule && window.gacRawRule.sbgt) || {});
                    });
                });
            }
            var sbgtSaveBtn = document.getElementById('gacRawSbgtRuleSaveBtn');
            if (sbgtSaveBtn) {
                sbgtSaveBtn.addEventListener('click', function() {
                    var errEl = document.getElementById('gacRawSbgtRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    var cleaned = gacCollectSbgtBandsPayload();
                    if (!cleaned.length) {
                        if (errEl) {
                            errEl.textContent = 'Add at least one band before saving.';
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    for (var i = 0; i < cleaned.length; i++) {
                        var b = cleaned[i];
                        if (!isFinite(b.acos_from) || !isFinite(b.acos_to) || !isFinite(b.sbgt)) {
                            if (errEl) {
                                errEl.textContent = 'Every band needs numeric From, To, and SBGT values.';
                                errEl.classList.remove('d-none');
                            }
                            return;
                        }
                        if (b.acos_from > b.acos_to) {
                            if (errEl) {
                                errEl.textContent = 'Each band needs From ≤ To.';
                                errEl.classList.remove('d-none');
                            }
                            return;
                        }
                    }
                    var sbidKeep = (window.gacRawRule && window.gacRawRule.sbid) ? window.gacRawRule.sbid : {};
                    var payload = { sbgt: { bands: cleaned }, sbid: sbidKeep };
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    sbgtSaveBtn.disabled = true;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok) {
                                if (errEl) {
                                    errEl.textContent = b.message || b.error || 'Save failed.';
                                    errEl.classList.remove('d-none');
                                }
                                return;
                            }
                            window.gacRawRule = b.rule || window.gacRawRule;
                            if (typeof bootstrap !== 'undefined' && sbgtModalEl) {
                                var inst = bootstrap.Modal.getInstance(sbgtModalEl);
                                if (inst) inst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { gacRawRefreshTableUiSoon(); })
                        .catch(function() {
                            if (errEl) {
                                errEl.textContent = 'Network or server error.';
                                errEl.classList.remove('d-none');
                            }
                        })
                        .finally(function() { sbgtSaveBtn.disabled = false; });
                });
            }

            var sbidModalEl = document.getElementById('gacRawSbidRuleModal');
            if (sbidModalEl) {
                sbidModalEl.addEventListener('show.bs.modal', function() {
                    var sErr = document.getElementById('gacRawSbidRuleErr');
                    if (sErr) { sErr.classList.add('d-none'); sErr.textContent = ''; }
                    gacRefreshRuleFromServer(function() {
                        gacFillSbidForm((window.gacRawRule && window.gacRawRule.sbid) || {});
                    });
                });
            }
            var sbidSaveBtn = document.getElementById('gacRawSbidRuleSaveBtn');
            if (sbidSaveBtn) {
                sbidSaveBtn.addEventListener('click', function() {
                    var sErr = document.getElementById('gacRawSbidRuleErr');
                    if (sErr) { sErr.classList.add('d-none'); sErr.textContent = ''; }
                    var sbgtKeep = (window.gacRawRule && window.gacRawRule.sbgt) ? window.gacRawRule.sbgt : {};
                    var payload = { sbgt: sbgtKeep, sbid: gacCollectSbid() };
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    sbidSaveBtn.disabled = true;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok) {
                                if (sErr) {
                                    sErr.textContent = b.message || b.error || 'Save failed.';
                                    sErr.classList.remove('d-none');
                                }
                                return;
                            }
                            window.gacRawRule = b.rule || window.gacRawRule;
                            if (typeof bootstrap !== 'undefined' && sbidModalEl) {
                                var sInst = bootstrap.Modal.getInstance(sbidModalEl);
                                if (sInst) sInst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { gacRawRefreshTableUiSoon(); })
                        .catch(function() {
                            if (sErr) {
                                sErr.textContent = 'Network or server error.';
                                sErr.classList.remove('d-none');
                            }
                        })
                        .finally(function() { sbidSaveBtn.disabled = false; });
                });
            }
        });
    </script>
@endsection
