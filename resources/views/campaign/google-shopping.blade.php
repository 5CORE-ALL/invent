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
        /* Column visibility dropdown — same pattern as Amazon Analytics */
        .column-dropdown-multicol {
            min-width: 460px;
            padding: 6px 4px;
            column-count: 3;
            column-gap: 4px;
        }
        .column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        .column-dropdown-multicol .dropdown-item {
            padding: 3px 10px;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .column-dropdown-multicol { min-width: 320px; column-count: 2; }
        }

        /* Badge chart modal — same full-width Rolling L30 UI as all-marketplace-master */
        #gacRawBadgeChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #gacRawBadgeChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #gacRawBadgeChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        #google-ads-campaigns-raw-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 11px;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        /* Compact sort arrows — clickable on every data column header */
        #google-ads-campaigns-raw-wrap .tabulator-col.tabulator-sortable .tabulator-col-title {
            cursor: pointer;
        }
        #google-ads-campaigns-raw-wrap .tabulator-col .tabulator-col-sorter {
            display: inline-flex !important;
            align-items: center;
            opacity: 0.35;
            margin-left: 2px;
        }
        #google-ads-campaigns-raw-wrap .tabulator-col.tabulator-sortable:hover .tabulator-col-sorter,
        #google-ads-campaigns-raw-wrap .tabulator-col[aria-sort="asc"] .tabulator-col-sorter,
        #google-ads-campaigns-raw-wrap .tabulator-col[aria-sort="desc"] .tabulator-col-sorter {
            opacity: 1;
        }
        /* Vertical headers (AliExpress / marketplace style); campaign name stays horizontal */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            min-height: 80px;
            vertical-align: bottom;
            overflow: visible;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: 80px !important;
            min-height: 80px;
            padding: 0;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.15;
            padding: 4px 0;
            text-align: center;
            overflow: visible;
            text-overflow: clip;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_name"] .tabulator-col-title,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__gac_select"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
            min-height: 0 !important;
            display: block;
            white-space: nowrap !important;
            padding: 5px 3px;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_name"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_name"] .tabulator-col-title-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__gac_select"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__gac_select"] .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        /* Tighter horizontal padding than Tabulator defaults */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
            text-align: center !important;
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
        /* PARENT campaign rows — same tint as google shopping missing ads */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row.parent-row {
            background-color: #fffef2;
        }
        /* CTR / CVR flag bands (relative to the filtered-set average):
           red   < avg*0.80, green avg*0.80–avg*1.20, magenta > avg*1.20 */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.flag-red {
            color: #ff2727 !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.flag-green {
            color: #05bd30 !important;
            font-weight: 600;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.flag-magenta {
            color: #d400d4 !important;
            font-weight: 600;
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
        #gac-raw-filter-bar .gac-raw-range-input {
            width: 70px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 0.8125rem;
            padding: 0.35rem 0.4rem;
            text-align: center;
        }
        #gac-raw-filter-bar .gac-raw-range-sep {
            color: #94a3b8;
            font-weight: 600;
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
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
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

                            <span id="faasActiveBadge" data-label="Active"
                                class="faas-stat-badge faas-stat-badge--sales is-static"
                                title="Active (ENABLED) campaigns matching current filters">ACTIVE:<span id="faasActiveValue">0</span></span>

                            <span id="faasGreenUtilL7Badge" data-metric="green_util_l7" data-label="Green Util (L7)"
                                class="faas-stat-badge faas-stat-badge--sales badge-chart-link"
                                title="Campaigns with Green utilisation (L7) — U7% 66–99%. Click for recorded daily history.">GREEN UTIL (L7):<span id="faasGreenUtilL7Value">0</span></span>

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
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Show / hide columns (saved per user)">
                                <i class="fas fa-columns"></i>
                            </button>
                            <ul class="dropdown-menu column-dropdown-multicol dropdown-menu-end"
                                id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                                {{-- Populated dynamically after the grid builds --}}
                            </ul>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbgt-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbgtRuleModal" title="Edit ACOS band thresholds and BGT ACOS values">BGT Vs ACOS Rule</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-bgt-views-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawBgtViewsRuleModal" title="Edit View L7 bands and Bgt Views values">BGT Vs VIEWS</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-bgt-cvr-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawBgtCvrRuleModal" title="Edit CVR L30 bands and Bgt Cvr values">BGT Vs CVR</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-bgt-prc-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawBgtPrcRuleModal" title="Edit Price bands and BGT PRC values">BGT PRC</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbid-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbidRuleModal" title="Edit 7UB/1UB% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbgt" title="Pushes each row’s SBGT as daily budget (chunks of 10). SBGT 0 / INV ≤ 0 pauses the campaign — $0 cannot be pushed.">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbid" title="Pushes SBIDs in chunks of 4 using grid values (by campaign_id). Skips SBID — and non-ENABLED rows. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBID
                        </button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-verify-id" title="Filter L30 Spend = 0 and INV &gt; 0, then compare each campaign’s Google Ads Item ID to the live Merchant Center / Shopify product ID. Red triangle in the ID column = mismatch.">
                            <i class="fa fa-id-card"></i> Verify ID
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
                                <label class="gac-raw-filter-label mb-0">CTR %</label>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="number" id="gac-filter-ctr-min" class="gac-raw-range-input" placeholder="Min" min="0" step="0.01" inputmode="decimal" aria-label="Minimum CTR percent">
                                    <span class="gac-raw-range-sep">–</span>
                                    <input type="number" id="gac-filter-ctr-max" class="gac-raw-range-input" placeholder="Max" min="0" step="0.01" inputmode="decimal" aria-label="Maximum CTR percent">
                                </div>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0">CVR %</label>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="number" id="gac-filter-cvr-min" class="gac-raw-range-input" placeholder="Min" min="0" step="0.01" inputmode="decimal" aria-label="Minimum CVR percent">
                                    <span class="gac-raw-range-sep">–</span>
                                    <input type="number" id="gac-filter-cvr-max" class="gac-raw-range-input" placeholder="Max" min="0" step="0.01" inputmode="decimal" aria-label="Maximum CVR percent">
                                </div>
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
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-inv">INV</label>
                                <select id="gac-filter-inv" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by inventory">
                                    <option value="all" selected>All</option>
                                    <option value="gt0">INV&gt;0</option>
                                    <option value="eq0">INV=0</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-u7-pie-open" data-bs-toggle="modal" data-bs-target="#gacRawU7PieModal" title="Row counts by U7% band (U7 filter ignored). Opens chart; click a slice for last 30 days.">U7% mix</button>
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
                    <h5 class="modal-title" id="gacRawSbgtRuleModalLabel">BGT Vs ACOS — ACOS % → BGT ACOS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Same ACOS → BGT ACOS brackets as <strong>/amazon-ads/all</strong>, matched on
                        <strong>ACOS % only</strong> (no spend gate). Rows are checked
                        <strong>top to bottom</strong>; the first range that contains the
                        campaign's ACOS gets its BGT ACOS. Grid <strong>SBGT</strong> is the sum of
                        Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC.
                        <strong>INV ≤ 0</strong> forces SBGT to 0. SBGT 0 cannot be pushed — those campaigns are paused.
                        Use <code>9999</code> on <em>To</em> for the catch-all highest band.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="gac-sbgt-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th style="width:110px;">ACOS%</th>
                                <th style="width:120px;">ACOS From (%)</th>
                                <th style="width:120px;">ACOS To (%)</th>
                                <th style="width:100px;">SBGT</th>
                                <th style="width:56px;" class="text-center">Del</th>
                            </tr>
                        </thead>
                        <tbody id="gac-sbgt-bands-body"></tbody>
                    </table>
                    </div>
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

    <div class="modal fade" id="gacRawBgtViewsRuleModal" tabindex="-1" aria-labelledby="gacRawBgtViewsRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawBgtViewsRuleModalLabel">BGT Vs VIEWS — View L7 → Bgt Views</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Shopify View L7</strong> range for the campaign SKU
                        (parent = sum of children). Rows are checked <strong>top to bottom</strong>; the first
                        range that contains the views gets its <strong>Bgt Views</strong>.
                        Saved in a Google-only table — not synced with Amazon.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Name</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:80px;">Count</th>
                                <th style="width:120px;">Bgt Views</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="gac-bgt-views-bands-body"></tbody>
                    </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="gac-bgt-views-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawBgtViewsRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawBgtViewsRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawBgtCvrRuleModal" tabindex="-1" aria-labelledby="gacRawBgtCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawBgtCvrRuleModalLabel">BGT Vs CVR — CVR L30 → Bgt Cvr</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Google Shopping CVR L30</strong> range
                        (Sold ÷ Clicks × 100). Rows are checked <strong>top to bottom</strong>.
                        Saved in a Google-only table — not synced with Amazon.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Name</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:80px;">Count</th>
                                <th style="width:120px;">Bgt Cvr</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="gac-bgt-cvr-bands-body"></tbody>
                    </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="gac-bgt-cvr-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawBgtCvrRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawBgtCvrRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawBgtPrcRuleModal" tabindex="-1" aria-labelledby="gacRawBgtPrcRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawBgtPrcRuleModalLabel">BGT PRC — Price → Bgt Prc</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Shopify Price</strong> range (parent = average of children with price &gt; 0).
                        Rows are checked <strong>top to bottom</strong>.
                        Saved in a Google-only table — not synced with Amazon.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Name</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:80px;">Count</th>
                                <th style="width:120px;">Bgt Prc</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="gac-bgt-prc-bands-body"></tbody>
                    </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="gac-bgt-prc-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawBgtPrcRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawBgtPrcRuleSaveBtn">Save &amp; refresh grid</button>
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
                    <p class="small text-muted mb-2">When <strong>both</strong> 7UB% and 1UB% are <strong>above</strong> the high threshold, SBID = L1 CPC × over multiplier. When <strong>both</strong> are <strong>below</strong> the low threshold: if CPC &lt; low-bid ceiling, SBID = CPC + flat incr; otherwise CPC × under multipliers (or fallback when no CPC). Otherwise SBID shows —.</p>
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
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL7">× L7 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL7">
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low — flat incr when CPC is low</p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderFlatMax">If CPC &lt;</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderFlatMax" title="When base CPC is below this, use CPC + incr instead of the multiplier">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderFlatIncr">Bid incr (+)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderFlatIncr" title="Flat amount added to CPC when below the ceiling">
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

    {{-- Badge metric trend — same full-width Rolling L30 UI as all-marketplace-master --}}
    <div class="modal fade p-0" id="gacRawBadgeChartModal" tabindex="-1" aria-labelledby="gacRawBadgeChartModalLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;" id="gacRawBadgeChartModalLabel">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="gacRawBadgeChartTitle">Google Shopping - ACOS (Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="gacRawBadgeChartRange" class="form-select form-select-sm bg-white" style="width:110px;height:26px;font-size:11px;padding:1px 8px;" aria-label="Chart date range">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="gacRawBadgeChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="gacRawBadgeChartCanvas"></canvas>
                            <p class="text-center text-muted small mb-0 py-4 d-none" id="gacRawBadgeChartEmpty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div id="gacRawBadgeChartRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="gacRawBadgeChartHighest" style="font-size:13px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="gacRawBadgeChartMedian" style="font-size:13px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="gacRawBadgeChartLowest" style="font-size:13px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawSbgtHistoryModal" tabindex="-1" aria-labelledby="gacRawSbgtHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="gacRawSbgtHistoryModalLabel">SBGT daily history — <span id="gacRawSbgtHistoryCid" class="text-muted"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2" id="gacRawSbgtHistoryBody" style="max-height:360px;overflow:auto;">
                    <p class="text-muted small mb-0">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Negative keywords for a campaign (campaign-level + ad group-level), populated by app:fetch-google-ads-negative-keywords --}}
    <div class="modal fade" id="gacRawNegKwModal" tabindex="-1" aria-labelledby="gacRawNegKwModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="gacRawNegKwModalLabel">
                        <i class="fas fa-ban" style="color:#ef4444;"></i>
                        Negative keywords — <span id="gacRawNegKwCid" class="text-muted"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2" id="gacRawNegKwBody" style="max-height:60vh;overflow:auto;">
                    <p class="text-muted small mb-0">Loading…</p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
            const gacBgtViewsRuleGetUrl = @json(route('google.shopping.campaigns.bgt-views-rule'));
            const gacBgtViewsRuleSaveUrl = @json(route('google.shopping.campaigns.bgt-views-rule.save'));
            const gacBgtCvrRuleGetUrl = @json(route('google.shopping.campaigns.bgt-cvr-rule'));
            const gacBgtCvrRuleSaveUrl = @json(route('google.shopping.campaigns.bgt-cvr-rule.save'));
            const gacBgtPrcRuleGetUrl = @json(route('google.shopping.campaigns.bgt-prc-rule'));
            const gacBgtPrcRuleSaveUrl = @json(route('google.shopping.campaigns.bgt-prc-rule.save'));
            const gacRawPushSbgtUrl = @json(route('google.shopping.campaigns.push.sbgt'));
            const gacRawPushSbidUrl = @json(route('google.shopping.campaigns.push.sbid'));
            const gacRawPullDataUrl = @json(route('google.shopping.campaigns.pull.data'));
            const gacRawBadgeHistoryUrl = @json(route('google.shopping.campaigns.badge.history'));
            const gacRawSbgtHistoryUrl = @json(route('google.shopping.campaigns.sbgt.history'));
            const gacRawU7PieDistribUrl = @json(route('google.shopping.campaigns.u7.distribution'));
            const gacRawU7PieHistoryUrl = @json(route('google.shopping.campaigns.u7.history'));
            const gacRawNegKwUrl = @json(route('google.shopping.campaigns.negatives'));
            // Per-user column show/hide — same /tabulator-column-visibility endpoint as Amazon
            // Analytics / all-marketplace-master (channel_tabulator_column_settings).
            const TABULATOR_COLUMN_CHANNEL = 'google_shopping_user_{{ auth()->id() ?? 'guest' }}';
            const TABULATOR_COLUMN_VISIBILITY_URL = @json(url('/tabulator-column-visibility'));
            window.gacRawRule = @json($googleShoppingRule);
            let table;
            /** Cached show/hide map from the server; applied inside autoColumnsDefinitions. */
            let gacSavedColumnVisibility = {};
            let gacSavedColumnVisibilityLoaded = false;
            let gacColDropdownBuilt = false;
            /** Always hidden (IDs / internal trend fields) — not offered in the Columns menu. */
            const GAC_PERMANENTLY_HIDDEN_FIELDS = {
                id: true,
                campaign_id: true,
                date: true,
                sbgt_prev: true,
                sbgt_prev_date: true,
                sbgt_trend: true,
                is_parent: true,
                id_mismatch: true,
                id_alert_title: true,
                bgt_views_color: true,
                bgt_views_label: true,
                bgt_cvr_color: true,
                bgt_cvr_label: true,
                bgt_cvr_page_cvr: true,
                bgt_prc_color: true,
                bgt_prc_label: true,
                bgt_prc_price: true,
                ovl30: true,
            };
            /** Toggle: L30 spend = 0 + INV &gt; 0 + Merchant Item ID check. */
            let gacRawVerifyIdActive = false;
            /** Hidden by default until the user opts in via Columns (and that choice is saved). */
            const GAC_DEFAULT_HIDDEN_FIELDS = {
                l7_spend: true,
                l2_spend: true,
                l1_spend: true,
            };

            function gacCsrfToken() {
                return (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            }

            /** Prefetch saved column prefs so autoColumnsDefinitions can honor them on first paint. */
            function gacFetchColumnVisibility() {
                return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': gacCsrfToken(),
                    },
                })
                    .then(function(res) { return res.json(); })
                    .then(function(map) {
                        gacSavedColumnVisibility = (map && typeof map === 'object' && !Array.isArray(map)) ? map : {};
                        gacSavedColumnVisibilityLoaded = true;
                        return gacSavedColumnVisibility;
                    })
                    .catch(function(err) {
                        console.error('Error loading column visibility:', err);
                        gacSavedColumnVisibility = {};
                        gacSavedColumnVisibilityLoaded = true;
                        return gacSavedColumnVisibility;
                    });
            }

            // Kick off early so prefs are usually ready before the first ajax column build.
            var gacColumnVisibilityReady = gacFetchColumnVisibility();

            let gacRawU7PieChart = null;
            let gacRawU7PieRefreshTimer = null;
            let gacRawBadgeChart = null;
            let gacRawActiveBadgeMetric = null;
            let gacRawActiveBadgeLabel = '';
            // Filtered-set weighted averages (from response.summary) that drive the CTR/CVR
            // flag colours. Refreshed by gacRawSummaryFromResponse() before each render.
            let gacRawAvgCtr = 0;
            let gacRawAvgCvr = 0;
            // Current average ACOS (%) — mirrors the toolbar ACOS badge
            // (ΣSpend / ΣSales over the loaded rows). Refreshed by
            // updateMetricBadges() and drives the Action column alert.
            let gacRawAvgAcos = 0;
            let gacRawReformatting = false;
            const GAC_RAW_U7_PIE_MODAL_CHART_H = 400;

            // Action column: red alert triangle when a row's ACOS is above the
            // current average ACOS AND its Spend is over $30. Reads the live
            // gacRawAvgAcos so it re-evaluates as the badge changes.
            function gacRawActionFormatter(cell) {
                var row = cell.getRow().getData();
                var acos = gacRawNumber(row.acos_l30);
                var spend = gacRawNumber(row.spend);
                if (acos > gacRawAvgAcos && spend > 30) {
                    var tip = 'ACOS ' + Math.round(acos) + '% > avg ' + Math.round(gacRawAvgAcos)
                            + '% and Spend $' + Math.round(spend) + ' > $30';
                    return '<i class="fas fa-exclamation-triangle" title="' + tip + '"'
                         + ' style="color:#dc2626;font-size:15px;"></i>';
                }
                return '';
            }

            // ID column: red triangle when Google Ads listing-group Item ID ≠ live Merchant Center ID.
            function gacRawIdCheckFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var mismatch = row.id_mismatch === true || row.id_mismatch === 1 || row.id_mismatch === '1';
                if (!mismatch) return '';
                var tip = String(row.id_alert_title || 'Item ID does not match Merchant Center');
                tip = tip.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                return '<i class="fas fa-exclamation-triangle" title="' + tip + '"'
                     + ' style="color:#dc2626;font-size:15px;"></i>';
            }

            // Re-run the Action column formatter after the average ACOS changes
            // (page change / filter / refresh). Guarded against re-entry.
            function gacRawReformatActionColumn() {
                if (!table || gacRawReformatting) return;
                gacRawReformatting = true;
                try {
                    (table.getRows('active') || []).forEach(function(r) { r.reformat(); });
                } catch (e) { /* table not ready */ }
                gacRawReformatting = false;
            }

            // SBGT cell: integer value + a day-over-day trend dot — green when today's
            // SBGT is above the previous saved day, red when below, gray when unchanged
            // or there is no prior day yet. Clicking the dot opens the daily history.
            function gacRawEscAttr(s) {
                return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }
            function gacRawSbgtCellFormatter(cell) {
                var row = cell.getRow().getData();
                var v = parseInt(cell.getValue(), 10);
                var valTxt = isFinite(v) ? v.toLocaleString() : '—';
                var trend = row.sbgt_trend || 'na';
                var color = trend === 'up' ? '#05bd30' : (trend === 'down' ? '#ff2727' : '#9ca3af');
                if (isFinite(v) && v === 0) color = '#dc2626';
                var prev = row.sbgt_prev;
                var prevTxt = (prev === null || prev === undefined) ? '—' : Math.round(prev).toLocaleString();
                var views = parseInt(row.bgt_views, 10);
                var cvr = parseInt(row.bgt_cvr, 10);
                var acos = parseInt(row.bgt_acos, 10);
                var prc = parseInt(row.bgt_prc, 10);
                var tip = 'SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC';
                tip += ' · ' + (isFinite(views) ? views : 0) + ' + ' + (isFinite(cvr) ? cvr : 0) + ' + ' + (isFinite(acos) ? acos : 0) + ' + ' + (isFinite(prc) ? prc : 0);
                var inv = parseFloat(row && row.inventory);
                if (isFinite(v) && v === 0 && isFinite(inv) && inv <= 0) {
                    tip += ' · INV ≤ 0 zeros SBGT — cannot push $0, campaign will be paused';
                } else if (isFinite(v) && v === 0) {
                    tip += ' · SBGT 0 cannot be pushed — campaign will be paused';
                }
                if (trend === 'na') {
                    tip += ' · No previous day saved yet — click for daily history';
                } else {
                    tip += ' · Prev (' + (row.sbgt_prev_date || '') + '): ' + prevTxt + ' → today ' + valTxt;
                }
                var cid = (row.campaign_id != null) ? String(row.campaign_id) : '';
                var dot = '<span class="gac-sbgt-dot" role="button" tabindex="0" data-sbgt-cid="' + gacRawEscAttr(cid) + '"'
                        + ' title="' + gacRawEscAttr(tip) + '"'
                        + ' style="display:inline-block;width:9px;height:9px;border-radius:50%;background:' + color + ';margin-left:6px;cursor:pointer;vertical-align:middle;flex-shrink:0;"></span>';
                return '<span style="display:inline-flex;align-items:center;justify-content:center;" title="' + gacRawEscAttr(tip) + '">' + valTxt + dot + '</span>';
            }
            function gacRawBgtPartFormatter(cell, colorKey, extraTip) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                var t = parseInt(v, 10);
                if (!isFinite(t)) return '—';
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row[colorKey]) ? String(row[colorKey]) : '#6c757d';
                if (t === 0) color = '#dc2626';
                var tip = extraTip(row, t);
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + gacRawEscAttr(tip) + '">' + t + '</span>';
            }
            function gacRawBgtAcosFormatter(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return '—';
                var t = parseInt(v, 10);
                if (!isFinite(t)) return '—';
                var row = cell.getRow ? cell.getRow().getData() : {};
                var acos = parseFloat(row && row.acos_l30);
                var color = '#6c757d';
                if (t === 0) {
                    color = '#dc2626';
                } else if (isFinite(acos)) {
                    if (acos <= 10) color = '#ec4899';
                    else if (acos <= 20) color = '#22c55e';
                    else if (acos <= 30) color = '#93c5fd';
                    else if (acos <= 40) color = '#ca8a04';
                    else color = '#dc2626';
                }
                var tip = 'BGT ACOS from ACOS L30';
                if (isFinite(acos)) tip += ' · ACOS ' + Math.round(acos) + '%';
                if (t === 0) tip += ' · zeros SBGT';
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + gacRawEscAttr(tip) + '">' + t + '</span>';
            }
            function gacRawBgtViewsFormatter(cell) {
                return gacRawBgtPartFormatter(cell, 'bgt_views_color', function(row) {
                    var views = parseFloat(row && row.views_l7);
                    var label = String((row && row.bgt_views_label) || '').trim();
                    var tip = 'Bgt Views from Shopify View L7';
                    if (isFinite(views)) tip += ' · Views ' + Math.round(views);
                    if (label) tip += ' · ' + label;
                    return tip;
                });
            }
            function gacRawBgtCvrFormatter(cell) {
                return gacRawBgtPartFormatter(cell, 'bgt_cvr_color', function(row) {
                    var cvr = parseFloat(row && (row.bgt_cvr_page_cvr != null ? row.bgt_cvr_page_cvr : row.cvr_l30));
                    var label = String((row && row.bgt_cvr_label) || '').trim();
                    var tip = 'Bgt Cvr from Google Shopping CVR L30';
                    if (isFinite(cvr)) tip += ' · CVR ' + cvr + '%';
                    if (label) tip += ' · ' + label;
                    return tip;
                });
            }
            function gacRawBgtPrcFormatter(cell) {
                return gacRawBgtPartFormatter(cell, 'bgt_prc_color', function(row) {
                    var price = parseFloat(row && row.bgt_prc_price);
                    var label = String((row && row.bgt_prc_label) || '').trim();
                    var tip = 'BGT PRC from Shopify Price';
                    if (isFinite(price)) tip += ' · $' + price;
                    if (label) tip += ' · ' + label;
                    return tip;
                });
            }
            function gacRawOpenSbgtHistory(campaignId) {
                var cid = String(campaignId || '').replace(/\D/g, '');
                if (!cid) return;
                var modalEl = document.getElementById('gacRawSbgtHistoryModal');
                var body = document.getElementById('gacRawSbgtHistoryBody');
                var cidEl = document.getElementById('gacRawSbgtHistoryCid');
                if (cidEl) cidEl.textContent = cid;
                if (body) body.innerHTML = '<p class="text-muted small mb-0">Loading…</p>';
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                fetch(gacRawSbgtHistoryUrl + '?campaign_id=' + encodeURIComponent(cid) + '&days=30', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); }).then(function(resp) {
                    gacRawRenderSbgtHistory((resp && resp.data) || []);
                }).catch(function() {
                    if (body) body.innerHTML = '<p class="text-danger small mb-0">Failed to load history.</p>';
                });
            }

            function gacRawRenderSbgtHistory(rows) {
                var body = document.getElementById('gacRawSbgtHistoryBody');
                if (!body) return;
                if (!rows.length) {
                    body.innerHTML = '<p class="text-muted small mb-0">No SBGT history saved yet — it builds up one row per day.</p>';
                    return;
                }
                var html = '<table class="table table-sm mb-0"><thead><tr>'
                         + '<th>Date</th><th class="text-end">SBGT</th><th class="text-center">Δ</th><th class="text-end">ACOS</th>'
                         + '</tr></thead><tbody>';
                rows.forEach(function(d) {
                    var color = d.trend === 'up' ? '#05bd30' : (d.trend === 'down' ? '#ff2727' : '#9ca3af');
                    var arrow = d.trend === 'up' ? '▲' : (d.trend === 'down' ? '▼' : '—');
                    html += '<tr><td>' + d.date + '</td>'
                          + '<td class="text-end">' + Math.round(d.sbgt).toLocaleString() + '</td>'
                          + '<td class="text-center" style="color:' + color + ';font-weight:700;">' + arrow + '</td>'
                          + '<td class="text-end">' + (d.acos != null ? Math.round(d.acos) + '%' : '—') + '</td></tr>';
                });
                html += '</tbody></table>';
                body.innerHTML = html;
            }

            // ---- Negative keywords modal --------------------------------------------------
            function gacRawEscHtml(s) {
                return String(s === null || s === undefined ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }
            function gacRawOpenNegKw(campaignId, campaignName) {
                var cid = String(campaignId || '').replace(/\D/g, '');
                if (!cid) return;
                var modalEl = document.getElementById('gacRawNegKwModal');
                var body = document.getElementById('gacRawNegKwBody');
                var titleCid = document.getElementById('gacRawNegKwCid');
                if (titleCid) {
                    titleCid.textContent = (campaignName ? campaignName + ' ' : '') + '(' + cid + ')';
                }
                if (body) body.innerHTML = '<p class="text-muted small mb-0">Loading…</p>';
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                fetch(gacRawNegKwUrl + '?campaign_id=' + encodeURIComponent(cid), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); }).then(function(resp) {
                    gacRawRenderNegKw(resp || {});
                }).catch(function() {
                    if (body) body.innerHTML = '<p class="text-danger small mb-0">Failed to load negative keywords.</p>';
                });
            }
            function gacRawRenderNegKw(resp) {
                var body = document.getElementById('gacRawNegKwBody');
                if (!body) return;
                var rows = Array.isArray(resp.data) ? resp.data : [];
                var counts = resp.counts || {};
                if (!rows.length) {
                    body.innerHTML = '<p class="text-muted small mb-0">No negative keywords stored for this campaign. '
                                   + 'Run <code>app:fetch-google-ads-negative-keywords</code> to sync from Google Ads.</p>';
                    return;
                }
                var summary = '<div class="small text-muted mb-2">'
                            + 'Campaign-level: <strong>' + (counts.campaign || 0) + '</strong> · '
                            + 'Ad group-level: <strong>' + (counts.ad_group || 0) + '</strong> · '
                            + 'Total: <strong>' + (counts.total || rows.length) + '</strong></div>';
                var matchBadge = function(m) {
                    var u = String(m || '').toUpperCase();
                    var bg = u === 'EXACT' ? '#0d6efd' : (u === 'PHRASE' ? '#6f42c1' : (u === 'BROAD' ? '#20c997' : '#6c757d'));
                    return '<span style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:11px;color:#fff;background:' + bg + ';">' + gacRawEscHtml(u || '—') + '</span>';
                };
                var levelBadge = function(l) {
                    var isCamp = String(l).toUpperCase() === 'CAMPAIGN';
                    var bg = isCamp ? '#334155' : '#0891b2';
                    var txt = isCamp ? 'Campaign' : 'Ad group';
                    return '<span style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:11px;color:#fff;background:' + bg + ';">' + txt + '</span>';
                };
                var html = summary
                         + '<table class="table table-sm table-hover mb-0"><thead><tr>'
                         + '<th>Level</th><th>Ad group</th><th>Negative keyword</th><th class="text-center">Match</th>'
                         + '</tr></thead><tbody>';
                rows.forEach(function(d) {
                    html += '<tr>'
                          + '<td>' + levelBadge(d.level) + '</td>'
                          + '<td>' + (d.ad_group_name ? gacRawEscHtml(d.ad_group_name) : '<span class="text-muted">—</span>') + '</td>'
                          + '<td>' + gacRawEscHtml(d.keyword_text) + '</td>'
                          + '<td class="text-center">' + matchBadge(d.match_type) + '</td>'
                          + '</tr>';
                });
                html += '</tbody></table>';
                body.innerHTML = html;
            }

            /**
             * Colour a CTR/CVR cell relative to the filtered-set average:
             *   red     when value < avg * 0.80
             *   magenta when value > avg * 1.20
             *   green   when avg*0.80 <= value <= avg*1.20
             * Degenerate average (avg <= 0, e.g. a channel with no conversions so every
             * CVR is 0): fall back to absolute meaning so cells still colour consistently
             * with pages that do have an average — 0 is the performance floor (red), any
             * positive value beats the zero average (magenta).
             */
            function gacRawApplyFlagColor(td, value, avg) {
                if (!td) return;
                td.classList.remove('flag-red', 'flag-green', 'flag-magenta');
                if (!isFinite(value)) return;
                if (!isFinite(avg) || avg <= 0) {
                    td.classList.add(value > 0 ? 'flag-magenta' : 'flag-red');
                    return;
                }
                var low = avg * 0.80;
                var high = avg * 1.20;
                if (value < low) {
                    td.classList.add('flag-red');
                } else if (value > high) {
                    td.classList.add('flag-magenta');
                } else {
                    td.classList.add('flag-green');
                }
            }

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
                var activeEl = document.getElementById('faasActiveValue');
                var greenUtilEl = document.getElementById('faasGreenUtilL7Value');
                if (!response || typeof response !== 'object' || !response.summary) {
                    if (spiEl) spiEl.textContent = '—';
                    if (acosEl) acosEl.textContent = '—';
                    if (campaignsEl) campaignsEl.textContent = '0';
                    if (activeEl) activeEl.textContent = '0';
                    if (greenUtilEl) greenUtilEl.textContent = '0';
                    gacRawAvgCtr = 0;
                    gacRawAvgCvr = 0;
                    return;
                }
                var s = response.summary;
                gacRawAvgCtr = Number.isFinite(Number(s.avg_ctr)) ? Number(s.avg_ctr) : 0;
                gacRawAvgCvr = Number.isFinite(Number(s.avg_cvr)) ? Number(s.avg_cvr) : 0;
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
                if (activeEl) {
                    var a = Number(s.active_count);
                    activeEl.textContent = Number.isFinite(a) ? Math.round(a).toLocaleString() : '0';
                }
                if (greenUtilEl) {
                    var g = Number(s.green_util_l7_count);
                    greenUtilEl.textContent = Number.isFinite(g) ? Math.round(g).toLocaleString() : '0';
                }
            }

            /** Read a numeric range box; returns '' when blank / negative / non-numeric. */
            function gacRawRangeInputVal(id) {
                var el = document.getElementById(id);
                if (!el) return '';
                var v = String(el.value || '').trim();
                if (v === '') return '';
                var n = parseFloat(v);
                if (!Number.isFinite(n) || n < 0) return '';
                return String(n);
            }

            function gacRawCurrentFilterParams() {
                return {
                    filter_ub7: gacRawFilterParamVal('gac-filter-ub7'),
                    filter_ub1: gacRawFilterParamVal('gac-filter-ub1'),
                    filter_acos: gacRawFilterParamVal('gac-filter-acos'),
                    filter_stat: gacRawFilterParamVal('gac-filter-stat'),
                    filter_inv: gacRawFilterParamVal('gac-filter-inv'),
                    filter_ctr_min: gacRawRangeInputVal('gac-filter-ctr-min'),
                    filter_ctr_max: gacRawRangeInputVal('gac-filter-ctr-max'),
                    filter_cvr_min: gacRawRangeInputVal('gac-filter-cvr-min'),
                    filter_cvr_max: gacRawRangeInputVal('gac-filter-cvr-max'),
                    filter_verify_id: gacRawVerifyIdActive ? 1 : 0,
                    q: gacRawSearchQueryVal(),
                };
            }

            function gacRawSyncVerifyIdButton() {
                var btn = document.getElementById('gac-raw-verify-id');
                if (!btn) return;
                if (gacRawVerifyIdActive) {
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-secondary');
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    btn.classList.add('btn-outline-secondary');
                    btn.classList.remove('btn-secondary');
                    btn.setAttribute('aria-pressed', 'false');
                }
            }

            function gacRawReloadGridForFilters() {
                if (!table) return;
                // setPage(1) + setData() both trigger a remote load; the first
                // fetch is aborted and Chrome reports TypeError: Failed to fetch.
                Promise.resolve(table.setData(dataUrl)).finally(gacRawRefreshTableUiSoon);
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
                // Click the SBGT trend dot → open the campaign's daily SBGT history.
                document.addEventListener('click', function (e) {
                    var dot = e.target.closest ? e.target.closest('.gac-sbgt-dot') : null;
                    if (!dot) return;
                    e.stopPropagation();
                    e.preventDefault();
                    gacRawOpenSbgtHistory(dot.getAttribute('data-sbgt-cid'));
                });
                // Click the red ban icon in the campaign name cell → open that campaign's negatives.
                document.addEventListener('click', function (e) {
                    var icon = e.target.closest ? e.target.closest('.gac-neg-kw') : null;
                    if (!icon) return;
                    e.stopPropagation();
                    e.preventDefault();
                    var nameAttr = icon.getAttribute('data-neg-name') || '';
                    var tmp = document.createElement('textarea');
                    tmp.innerHTML = nameAttr;
                    gacRawOpenNegKw(icon.getAttribute('data-neg-cid'), tmp.value);
                });
            })();

            function gacRawSetTotalBadge(text) {
                var el = document.getElementById('gac-raw-total');
                if (el) el.textContent = text;
            }

            function gacRawAppendQueryParams(searchParams, obj, prefix) {
                Object.keys(obj || {}).forEach(function(key) {
                    var val = obj[key];
                    var name = prefix ? prefix + '[' + key + ']' : key;
                    if (val === undefined) return;
                    // Tabulator sort entries include a ColumnComponent — do not serialize it.
                    if (key === 'column') return;
                    if (val !== null && typeof val === 'object') {
                        gacRawAppendQueryParams(searchParams, val, name);
                    } else {
                        searchParams.set(name, val === null ? '' : String(val));
                    }
                });
            }

            /** Keep only field+dir so the server receives a usable sort[] payload. */
            function gacRawSanitizeAjaxParams(params) {
                var out = {};
                Object.keys(params || {}).forEach(function(k) {
                    if (k === 'sort' || k === 'sorters') return;
                    out[k] = params[k];
                });
                var list = params && (params.sort || params.sorters);
                if (Array.isArray(list) && list.length) {
                    out.sort = list.map(function(s) {
                        return {
                            field: String((s && s.field) || ''),
                            dir: (s && s.dir) === 'desc' ? 'desc' : 'asc'
                        };
                    }).filter(function(s) { return s.field !== ''; });
                }
                return out;
            }

            function gacRawIsRetryableLoadError(err, status) {
                if (status === 502 || status === 503 || status === 504) return true;
                if (!err) return false;
                if (err.name === 'AbortError') return false;
                var msg = String(err.message || err);
                return err.name === 'TypeError' || /Failed to fetch|NetworkError|ERR_NETWORK|network changed/i.test(msg);
            }

            /** Retry transient Chrome drops (ERR_NETWORK_CHANGED / Failed to fetch). */
            function gacRawAjaxRequestFunc(url, config, params) {
                var attempts = 0;
                var maxAttempts = 3;
                var method = (config && config.method) ? String(config.method).toUpperCase() : 'GET';
                function sleep(ms) {
                    return new Promise(function(resolve) { setTimeout(resolve, ms); });
                }
                function once() {
                    attempts += 1;
                    var u = new URL(url, window.location.href);
                    gacRawAppendQueryParams(u.searchParams, gacRawSanitizeAjaxParams(params || {}));
                    return fetch(u.toString(), {
                        method: method,
                        credentials: (config && config.credentials) ? config.credentials : 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(function(res) {
                        if (!res.ok && gacRawIsRetryableLoadError(null, res.status) && attempts < maxAttempts) {
                            gacRawSetTotalBadge('Retrying… (' + attempts + '/' + maxAttempts + ')');
                            return sleep(400 * attempts).then(once);
                        }
                        if (!res.ok) {
                            throw new Error('HTTP ' + res.status);
                        }
                        return res.json();
                    }).catch(function(err) {
                        if (gacRawIsRetryableLoadError(err, 0) && attempts < maxAttempts) {
                            gacRawSetTotalBadge('Retrying… (' + attempts + '/' + maxAttempts + ')');
                            return sleep(400 * attempts).then(once);
                        }
                        throw err;
                    });
                }
                return once();
            }

            table = new Tabulator('#google-ads-campaigns-raw-table', {
                ajaxURL: dataUrl,
                ajaxConfig: { method: 'GET', credentials: 'same-origin' },
                ajaxRequestFunc: gacRawAjaxRequestFunc,
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
                headerSortClickElement: 'header',
                placeholder: 'No rows in google_ads_campaigns.',
                selectableRows: true,
                rowFormatter: function(row) {
                    var d = row.getData() || {};
                    var el = row.getElement();
                    if (!el) return;
                    var isParent = d.is_parent === true || d.is_parent === 1 || d.is_parent === '1';
                    if (!isParent && d.campaign_name) {
                        isParent = String(d.campaign_name).toUpperCase().trim().indexOf('PARENT ') === 0;
                    }
                    if (isParent) {
                        el.classList.add('parent-row');
                    } else {
                        el.classList.remove('parent-row');
                    }
                },
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
                        ctr_l30: 'CTR',
                        ub7: '7 UB%',
                        ub2: '2 UB%',
                        ub1: '1 UB%',
                        bgt: 'BGT',
                        bgt_acos: 'BGT ACOS',
                        bgt_views: 'Bgt Views',
                        bgt_cvr: 'Bgt Cvr',
                        bgt_prc: 'BGT PRC',
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
                        var row = c.getRow().getData();
                        var cid = (row.campaign_id != null) ? String(row.campaign_id).replace(/[^0-9]/g, '') : '';
                        var neg = '<i class="fas fa-ban gac-neg-kw" role="button" tabindex="0" title="View negative keywords"'
                                + ' data-neg-cid="' + cid + '"'
                                + ' data-neg-name="' + attr + '"'
                                + ' style="margin-left:6px;color:#ef4444;cursor:pointer;flex-shrink:0;"></i>';
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;max-width:100%;">'
                             + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc + '</span>'
                             + copy + neg + '</span>';
                    };
                    /** Server-side sort whitelist — keep in sync with applyRawGridSort() in the controller. */
                    var sortableFields = {
                        campaign_status: true,
                        campaign_name: true,
                        inventory: true,
                        dil: true,
                        spend: true,
                        l7_spend: true,
                        l2_spend: true,
                        l1_spend: true,
                        metrics_clicks: true,
                        ctr_l30: true,
                        cpc_L30: true,
                        cpc_L7: true,
                        cpc_L2: true,
                        cpc_L1: true,
                        ad_sold_L30: true,
                        ad_sales_L30: true,
                        acos_l30: true,
                        price: true,
                        cvr_l30: true,
                        ub7: true,
                        ub2: true,
                        ub1: true,
                        bgt: true,
                        bgt_acos: true,
                        views_l30: true,
                        views_l7: true,
                        bgt_views: true,
                        bgt_cvr: true,
                        bgt_prc: true,
                        sbgt: true,
                        sbid: true,
                    };
                    var numericSortDesc = {
                        inventory: true, dil: true, spend: true, l7_spend: true, l2_spend: true, l1_spend: true,
                        metrics_clicks: true, ctr_l30: true, cpc_L30: true, cpc_L7: true, cpc_L2: true, cpc_L1: true,
                        ad_sold_L30: true, ad_sales_L30: true, acos_l30: true, price: true, cvr_l30: true,
                        ub7: true, ub2: true, ub1: true, bgt: true, bgt_acos: true, views_l30: true, views_l7: true,
                        bgt_views: true, bgt_cvr: true, bgt_prc: true, sbgt: true, sbid: true
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
                        if (col.headerSort && Object.prototype.hasOwnProperty.call(numericSortDesc, col.field)) {
                            col.headerSortStartingDir = 'desc';
                        }
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
                        // Column visibility: permanent hide → saved user prefs → defaults (L7/L2/L1 Spend).
                        if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, col.field)) {
                            col.visible = false;
                        } else if (gacSavedColumnVisibilityLoaded
                            && Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, col.field)) {
                            col.visible = gacSavedColumnVisibility[col.field] !== false;
                        } else if (Object.prototype.hasOwnProperty.call(GAC_DEFAULT_HIDDEN_FIELDS, col.field)) {
                            // Still computed server-side for UB%/CPC/SBID; hidden until user shows them.
                            col.visible = false;
                        }
                        if (col.field === 'inventory') {
                            col.title = 'INV';
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 64;
                            col.formatter = function(c) {
                                var v = c.getValue();
                                if (v === null || v === undefined || v === '') return '—';
                                var n = Number(v);
                                return Number.isFinite(n) ? Math.round(n).toLocaleString('en-US') : '—';
                            };
                        }
                        if (col.field === 'dil') {
                            col.title = 'Dil';
                            col.headerTooltip = 'Shopify OV L30 ÷ INV × 100 (parent = summed children). Same formula as Amazon Ads Dil.';
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 60;
                            col.formatter = function(c) {
                                var row = c.getRow ? c.getRow().getData() : {};
                                var inv = parseFloat(row && (row.inventory != null ? row.inventory : row.inv));
                                var ovl30 = parseFloat(row && row.ovl30);
                                var dil = parseFloat(c.getValue());
                                if (!isFinite(inv) || inv === 0) {
                                    return '<span style="color:#6c757d;">0%</span>';
                                }
                                if (!isFinite(dil) && isFinite(ovl30)) {
                                    dil = (ovl30 / inv) * 100;
                                }
                                if (!isFinite(dil)) return '—';
                                var color = '#e83e8c';
                                if (dil < 16.66) color = '#a00211';
                                else if (dil < 25) color = '#ffc107';
                                else if (dil < 50) color = '#28a745';
                                var tip = 'Dil = OV L30 ÷ INV × 100';
                                if (isFinite(ovl30)) tip += ' · ' + Math.round(ovl30) + ' ÷ ' + Math.round(inv);
                                return '<span class="fw-semibold" style="color:' + color + ';" title="' + tip + '">' + Math.round(dil) + '%</span>';
                            };
                        }
                        if (col.field === 'views_l30' || col.field === 'views_l7') {
                            var isL7 = col.field === 'views_l7';
                            col.title = isL7 ? 'Views L7' : 'Views L30';
                            col.headerTooltip = isL7
                                ? 'Shopify View L7 — same value used by Bgt Views (parent = sum of children). Red when under 70.'
                                : 'Shopify View L30 (parent = sum of children).';
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 68;
                            col.formatter = function(c) {
                                var v = c.getValue();
                                if (v === null || v === undefined || v === '') return '—';
                                var n = Number(v);
                                if (!Number.isFinite(n)) return '—';
                                var num = Math.round(n);
                                var formatted = num.toLocaleString('en-US');
                                var field = c.getField ? c.getField() : '';
                                if (field === 'views_l7' && num < 70) {
                                    return '<span class="fw-semibold" style="color:#a00211;" title="Shopify View L7 — under 70">' + formatted + '</span>';
                                }
                                return formatted;
                            };
                        }
                        if (col.field === 'price') {
                            col.title = 'Price';
                            col.headerTooltip = 'Shopify price (parent = average of children with price > 0). Same value used by BGT PRC.';
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 72;
                            col.formatter = function(c) {
                                var v = parseFloat(c.getValue());
                                if (!isFinite(v) || v <= 0) return '—';
                                return '$' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            };
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
                                // Flag colour is relative to the filtered-set average CVR.
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) v = 0;
                                    gacRawApplyFlagColor(c.getElement(), v, gacRawAvgCvr);
                                    return v.toFixed(1) + '%';
                                };
                                col.minWidth = Math.max(col.minWidth || 0, 60);
                            } else if (col.field === 'ctr_l30') {
                                // CTR = (Clicks / Impressions) * 100 — 1 decimal. Flag colour
                                // is relative to the filtered-set average CTR.
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) v = 0;
                                    gacRawApplyFlagColor(c.getElement(), v, gacRawAvgCtr);
                                    return v.toFixed(1) + '%';
                                };
                                col.minWidth = Math.max(col.minWidth || 0, 60);
                            } else if (col.field === 'ub7' || col.field === 'ub2' || col.field === 'ub1') {
                                col.formatter = ubUtilColorFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'bgt_acos') {
                                col.headerTooltip = 'Suggested budget from BGT Vs ACOS Rule — same ACOS% as the ACOS column';
                                col.formatter = gacRawBgtAcosFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 72);
                            } else if (col.field === 'bgt_views') {
                                col.headerTooltip = 'Suggested budget from BGT Vs VIEWS — Shopify View L7';
                                col.formatter = gacRawBgtViewsFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 72);
                            } else if (col.field === 'bgt_cvr') {
                                col.headerTooltip = 'Suggested budget from BGT Vs CVR — Google Shopping CVR L30';
                                col.formatter = gacRawBgtCvrFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 68);
                            } else if (col.field === 'bgt_prc') {
                                col.headerTooltip = 'Suggested budget from BGT PRC — Shopify Price';
                                col.formatter = gacRawBgtPrcFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 68);
                            } else if (col.field === 'sbgt') {
                                col.headerTooltip = 'Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC. INV ≤ 0 zeros SBGT. SBGT 0 cannot be pushed — Push SBGT pauses the campaign.';
                                col.formatter = gacRawSbgtCellFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 72);
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
                    // Action column (synthetic — no data field). Red alert when
                    // ACOS > current avg ACOS badge AND Spend > $30.
                    if (!defs.some(function(d) { return d.field === 'action'; })) {
                        defs.push({
                            title: 'Action',
                            field: 'action',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 80,
                            minWidth: 70,
                            formatter: gacRawActionFormatter,
                        });
                    }
                    // ID column: red triangle when Ads Item ID ≠ live Merchant Center ID
                    // (populated when Verify ID filter is active).
                    if (!defs.some(function(d) { return d.field === 'id_check'; })) {
                        defs.push({
                            title: 'ID',
                            field: 'id_check',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 56,
                            minWidth: 50,
                            formatter: gacRawIdCheckFormatter,
                        });
                    }
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

            ['gac-filter-ub7', 'gac-filter-ub1', 'gac-filter-acos', 'gac-filter-stat', 'gac-filter-inv'].forEach(function(fid) {
                var fel = document.getElementById(fid);
                if (fel) {
                    fel.addEventListener('change', gacRawReloadGridForFilters);
                }
            });

            // CTR / CVR min-max boxes: debounce typing so we only reload after 400ms idle;
            // 'change' (blur / Enter / stepper) reloads immediately.
            (function() {
                var rangeTimer = null;
                var lastRangeKey = '';
                function currentRangeKey() {
                    return [
                        gacRawRangeInputVal('gac-filter-ctr-min'),
                        gacRawRangeInputVal('gac-filter-ctr-max'),
                        gacRawRangeInputVal('gac-filter-cvr-min'),
                        gacRawRangeInputVal('gac-filter-cvr-max')
                    ].join('|');
                }
                lastRangeKey = currentRangeKey();
                function scheduleRangeReload(immediate) {
                    if (rangeTimer) { clearTimeout(rangeTimer); rangeTimer = null; }
                    var run = function() {
                        var k = currentRangeKey();
                        if (k === lastRangeKey) return;
                        lastRangeKey = k;
                        gacRawReloadGridForFilters();
                    };
                    if (immediate) { run(); } else { rangeTimer = setTimeout(run, 400); }
                }
                ['gac-filter-ctr-min', 'gac-filter-ctr-max', 'gac-filter-cvr-min', 'gac-filter-cvr-max'].forEach(function(fid) {
                    var fel = document.getElementById(fid);
                    if (!fel) return;
                    fel.addEventListener('input', function() { scheduleRangeReload(false); });
                    fel.addEventListener('change', function() { scheduleRangeReload(true); });
                });
            })();

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

            /**
             * Column visibility — Amazon-style persist via /tabulator-column-visibility
             * (channel_tabulator_column_settings). Prefs are per-user and applied both in
             * autoColumnsDefinitions (so rebuilds keep them) and after each dataLoaded.
             */
            function buildColumnDropdown() {
                var menu = document.getElementById('column-dropdown-menu');
                if (!menu || !table) return;
                menu.innerHTML = '';

                table.getColumns().forEach(function(col) {
                    var def = col.getDefinition();
                    var field = def.field;
                    if (!field || field === '__gac_select') return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) return;

                    var isVisible = col.isVisible();
                    var title = def.title || field;
                    var li = document.createElement('li');
                    li.innerHTML =
                        '<label class="dropdown-item"><input type="checkbox" ' +
                        (isVisible ? 'checked' : '') +
                        ' data-field="' + String(field).replace(/"/g, '&quot;') + '"> ' +
                        String(title).replace(/</g, '&lt;') + '</label>';
                    menu.appendChild(li);
                });
                gacColDropdownBuilt = true;
            }

            function saveColumnVisibilityToServer() {
                if (!table) return;
                var visibility = {};
                table.getColumns().forEach(function(col) {
                    var field = col.getDefinition().field;
                    if (!field || field === '__gac_select') return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) {
                        visibility[field] = false;
                        return;
                    }
                    visibility[field] = col.isVisible();
                });
                gacSavedColumnVisibility = visibility;
                gacSavedColumnVisibilityLoaded = true;

                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': gacCsrfToken(),
                    },
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        visibility: visibility,
                    }),
                }).catch(function(err) { console.error('Error saving column visibility:', err); });
            }

            function applyColumnVisibilityFromCache() {
                if (!table || !gacSavedColumnVisibilityLoaded) return;
                table.getColumns().forEach(function(col) {
                    var field = col.getDefinition().field;
                    if (!field || field === '__gac_select') return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) {
                        col.hide();
                        return;
                    }
                    if (!Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, field)) return;
                    if (gacSavedColumnVisibility[field]) {
                        col.show();
                    } else {
                        col.hide();
                    }
                });
            }

            function gacEnsureColumnVisibilityUi() {
                if (!table || typeof table.getColumns !== 'function') return;
                if (table.getColumns().filter(function(c) {
                    var f = c.getDefinition().field;
                    return f && f !== '__gac_select';
                }).length === 0) {
                    return;
                }
                var finish = function() {
                    applyColumnVisibilityFromCache();
                    if (!gacColDropdownBuilt) {
                        buildColumnDropdown();
                    }
                };
                if (gacSavedColumnVisibilityLoaded) {
                    finish();
                } else {
                    gacColumnVisibilityReady.then(finish);
                }
            }

            var gacColMenu = document.getElementById('column-dropdown-menu');
            if (gacColMenu) {
                gacColMenu.addEventListener('click', function(e) {
                    // Keep the menu open while toggling checkboxes.
                    e.stopPropagation();
                });
                gacColMenu.addEventListener('change', function(e) {
                    if (!table || e.target.type !== 'checkbox') return;
                    var field = e.target.getAttribute('data-field');
                    if (!field) return;
                    var col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    saveColumnVisibilityToServer();
                });
            }

            var gacColDropdownBtn = document.getElementById('columnVisibilityDropdown');
            if (gacColDropdownBtn) {
                gacColDropdownBtn.addEventListener('show.bs.dropdown', function() {
                    buildColumnDropdown();
                });
            }

            table.on('pageLoaded', function() {
                gacRawRefreshTableUiSoon();
            });
            table.on('dataLoaded', function() {
                gacRawRefreshTableUiSoon();
                gacEnsureColumnVisibilityUi();
            });

            table.on('dataLoadError', function(error) {
                console.error('google_ads_campaigns raw data load error', error);
                gacRawSetTotalBadge('Connection dropped — click Refresh');
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

            function gacPushTargetCampaignIds(opts) {
                if (!table) return [];
                opts = opts || {};
                var selected = table.getSelectedData();
                var rows = (selected && selected.length > 0) ? selected : table.getData();
                var seen = {};
                var ids = [];
                rows.forEach(function(row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || cid === '') return;
                    if (opts.requireSbid) {
                        var sbid = row.sbid;
                        if (sbid === null || sbid === undefined || sbid === '' || !(Number(sbid) > 0)) return;
                    }
                    if (opts.enabledOnly) {
                        var st = String(row.campaign_status || '').toUpperCase();
                        if (st && st !== 'ENABLED') return;
                    }
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

                var chunkSize = Number(opts.chunkSize) > 0 ? Number(opts.chunkSize) : 0;
                var chunks = [];
                if (chunkSize > 0) {
                    for (var i = 0; i < campaignIds.length; i += chunkSize) {
                        chunks.push(campaignIds.slice(i, i + chunkSize));
                    }
                } else {
                    chunks = [campaignIds];
                }

                var total = campaignIds.length;
                var chunkCount = chunks.length;
                gacShowPushLoading(
                    opts.loadingTitle,
                    (opts.loadingDetail || '')
                        + (chunkCount > 1
                            ? (' Sending in ' + chunkCount + ' chunk(s) of up to ' + chunkSize + '.')
                            : '')
                );

                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var outputs = [];
                var messages = [];
                var lastCmd = 'command';
                var anyError = false;
                var doneCount = 0;

                function postChunk(ids) {
                    return fetch(opts.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ campaign_ids: ids }),
                    }).then(function(res) {
                        return res.json().then(function(body) {
                            return { ok: res.ok, status: res.status, body: body };
                        }).catch(function() {
                            if (res.status === 504) {
                                throw new Error('Nginx timed out after 120s (HTTP 504). Google Ads may still have applied this chunk — retry with fewer rows or wait and push again.');
                            }
                            throw new Error('Server returned a non-JSON response (HTTP '
                                + res.status + '). The request may have timed out — try fewer rows.');
                        });
                    });
                }

                function runNext(index) {
                    if (index >= chunks.length) {
                        var success = !anyError;
                        var title = lastCmd + ' — ' + (success ? 'finished' : 'failed');
                        title += ' (' + total + ' id(s) in ' + chunkCount + ' chunk(s))';
                        var text = (messages.length ? messages.join('\n') + '\n\n' : '')
                            + outputs.join('\n\n--- next chunk ---\n\n');
                        gacShowPushResult(title, text || '(no console output)', success ? 'success' : 'error');
                        if (success && table) {
                            Promise.resolve(table.setData(dataUrl)).finally(gacRawRefreshTableUiSoon);
                        }
                        opts.btn.innerHTML = origHtml;
                        if (sbgtB) sbgtB.disabled = false;
                        if (sbidB) sbidB.disabled = false;
                        return;
                    }

                    var chunk = chunks[index];
                    doneCount += chunk.length;
                    opts.btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing '
                        + doneCount + '/' + total + '…';
                    gacShowPushLoading(
                        opts.loadingTitle,
                        'Chunk ' + (index + 1) + '/' + chunkCount
                            + ' (' + doneCount + '/' + total + ' campaign id(s)). Waiting for Google Ads API — do not close this tab.'
                    );

                    postChunk(chunk)
                        .then(function(out) {
                            var b = out.body || {};
                            lastCmd = b.command || lastCmd;
                            var success = out.ok && b.ok !== false;
                            if (!success) anyError = true;
                            if (b.message) {
                                messages.push('[chunk ' + (index + 1) + '/' + chunkCount + '] ' + b.message);
                            }
                            if (b.output) {
                                outputs.push(b.output);
                            }
                            runNext(index + 1);
                        })
                        .catch(function(err) {
                            anyError = true;
                            messages.push('[chunk ' + (index + 1) + '/' + chunkCount + '] '
                                + String(err && err.message ? err.message : err));
                            // Stop further chunks on transport failure so we don't keep hammering.
                            var title = lastCmd + ' — failed (' + doneCount + '/' + total + ' sent)';
                            var text = (messages.length ? messages.join('\n') + '\n\n' : '')
                                + outputs.join('\n\n--- next chunk ---\n\n');
                            gacShowPushResult(title, text || String(err && err.message ? err.message : err), 'error');
                            opts.btn.innerHTML = origHtml;
                            if (sbgtB) sbgtB.disabled = false;
                            if (sbidB) sbidB.disabled = false;
                        });
                }

                runNext(0);
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
                    var sbgtChunks = Math.ceil(ids.length / 10) || 1;
                    gacRunArtisanPush({
                        url: gacRawPushSbgtUrl,
                        btn: pushSbgtBtn,
                        campaign_ids: ids,
                        chunkSize: 10,
                        confirmMsg: 'Push SBGT to ' + scope + '? Sends in chunks of 10 (' + sbgtChunks + ' request(s)). Each row uses the SBGT shown in the grid. Rows with SBGT 0 (including INV ≤ 0) are paused instead — Google cannot push a $0 budget.',
                        loadingTitle: 'Pushing SBGT (budget:update-shopping)…',
                        loadingDetail: 'Updating budgets for ' + ids.length + ' campaign id(s) in chunks of 10.',
                    });
                });
            }
            var pushSbidBtn = document.getElementById('gac-raw-push-sbid');
            if (pushSbidBtn) {
                pushSbidBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds({ requireSbid: true, enabledOnly: true });
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    if (!ids.length) {
                        window.alert('No ENABLED campaigns with an SBID to push. Rows with SBID — or a non-ENABLED status are skipped.');
                        return;
                    }
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s) with SBID')
                        : ('all ' + ids.length + ' ENABLED row(s) with SBID on this page');
                    var sbidChunkSize = 4;
                    var sbidChunks = Math.ceil(ids.length / sbidChunkSize) || 1;
                    gacRunArtisanPush({
                        url: gacRawPushSbidUrl,
                        btn: pushSbidBtn,
                        campaign_ids: ids,
                        chunkSize: sbidChunkSize,
                        confirmMsg: 'Push SBID to ' + scope + '? Sends in chunks of ' + sbidChunkSize + ' (' + sbidChunks + ' request(s)) so nginx does not 504. Each row uses the SBID shown in the grid.',
                        loadingTitle: 'Pushing SBID (sbid:update)…',
                        loadingDetail: 'Updating SBIDs for ' + ids.length + ' campaign id(s) in chunks of ' + sbidChunkSize + '.',
                    });
                });
            }

            var verifyIdBtn = document.getElementById('gac-raw-verify-id');
            if (verifyIdBtn) {
                verifyIdBtn.addEventListener('click', function() {
                    gacRawVerifyIdActive = !gacRawVerifyIdActive;
                    gacRawSyncVerifyIdButton();
                    gacRawReloadGridForFilters();
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
            var GAC_DEFAULT_BAND_LABELS = ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'];

            /** Same ACOS colour schema as /facebook-ads (no spend). */
            function gacAcosSchemaStyle(pct) {
                var n = Number(pct);
                if (!isFinite(n)) return { bg: '#6c757d', fg: '#fff' };
                if (n <= 10) return { bg: '#ec4899', fg: '#000' };
                if (n <= 20) return { bg: '#22c55e', fg: '#000' };
                if (n <= 30) return { bg: '#93c5fd', fg: '#000' };
                if (n <= 40) return { bg: '#facc15', fg: '#000' };
                return { bg: '#dc2626', fg: '#fff' };
            }
            function gacAcosSchemaStyleForBand(from, to) {
                var a = Number(from);
                var b = Number(to);
                var lo = isFinite(a) ? a : 0;
                var hi = (isFinite(b) && b < 9000) ? b : (lo + 10);
                return gacAcosSchemaStyle((lo + hi) / 2);
            }

            function gacNormalizeSbgtBandsForUi(bands) {
                if (!Array.isArray(bands) || !bands.length) return [];
                var hasFromTo = bands.some(function(b) {
                    return b && (b.acos_from !== undefined && b.acos_from !== null
                        || b.acos_to !== undefined && b.acos_to !== null);
                });
                var withDefaults = function(b, i) {
                    var label = String(b.label ?? '').trim();
                    var from = Number(b.acos_from ?? 0);
                    var to = Number(b.acos_to ?? 9999);
                    var schema = gacAcosSchemaStyleForBand(from, to);
                    return {
                        acos_from: from,
                        acos_to: to,
                        sbgt: b.sbgt,
                        label: label || (GAC_DEFAULT_BAND_LABELS[i] || 'Band'),
                        color: schema.bg,
                    };
                };
                if (hasFromTo) {
                    return bands.map(withDefaults);
                }
                var sorted = bands.slice().sort(function(a, b) {
                    return (Number(a.acos_max) || 0) - (Number(b.acos_max) || 0);
                });
                var prevTo = 0;
                return sorted.map(function(b, i) {
                    var to = Number(b.acos_max ?? 9999);
                    var row = withDefaults({
                        acos_from: prevTo,
                        acos_to: to,
                        sbgt: b.sbgt,
                        label: b.label ?? '',
                    }, i);
                    prevTo = to;
                    return row;
                });
            }

            function gacRenderSbgtBands(bands) {
                var tbody = document.getElementById('gac-sbgt-bands-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function(band, i) {
                    var schema = gacAcosSchemaStyleForBand(band.acos_from, band.acos_to);
                    band.color = schema.bg;
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm text-center fw-semibold"'
                        + ' value="' + String(band.label ?? '').replace(/"/g, '&quot;') + '"'
                        + ' data-idx="' + i + '" data-field="label" placeholder="e.g. Good"'
                        + ' style="background:' + schema.bg + ';color:' + schema.fg + ';border:none;min-width:6.5rem;"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_from ?? '') + '" data-idx="' + i + '" data-field="acos_from"'
                        + ' placeholder="0"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_to ?? '') + '" data-idx="' + i + '" data-field="acos_to"'
                        + ' placeholder="9999"></td>'
                        + '<td><input type="number" step="1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (band.sbgt ?? '') + '" data-idx="' + i + '" data-field="sbgt"></td>'
                        + '<td class="text-center">'
                        + '<button type="button" class="btn btn-sm btn-outline-danger px-2" data-remove-idx="' + i + '"'
                        + ' title="Delete this band" aria-label="Delete band">×</button></td>';
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
                        if (fld === 'acos_from' || fld === 'acos_to') {
                            var band = gacCurrentSbgtBands[idx];
                            var schema = gacAcosSchemaStyleForBand(band.acos_from, band.acos_to);
                            band.color = schema.bg;
                            var labelInp = this.closest('tr')
                                ? this.closest('tr').querySelector('input[data-field="label"]')
                                : null;
                            if (labelInp) {
                                labelInp.style.background = schema.bg;
                                labelInp.style.color = schema.fg;
                            }
                        }
                    });
                });

                tbody.querySelectorAll('[data-remove-idx]').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var idx = parseInt(this.getAttribute('data-remove-idx'), 10);
                        if (!isFinite(idx) || idx < 0) return;
                        gacCurrentSbgtBands.splice(idx, 1);
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
                    var acosFrom = (b.acos_from === '' || b.acos_from === null || b.acos_from === undefined)
                        ? NaN : parseFloat(b.acos_from);
                    var acosTo = (b.acos_to === '' || b.acos_to === null || b.acos_to === undefined)
                        ? NaN : parseFloat(b.acos_to);
                    return {
                        acos_from: acosFrom,
                        acos_to: acosTo,
                        sbgt: (b.sbgt === '' || b.sbgt === null || b.sbgt === undefined)
                            ? NaN : parseInt(b.sbgt, 10),
                        label: (b.label || '').toString(),
                        color: gacAcosSchemaStyleForBand(acosFrom, acosTo).bg,
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
                gacSetVal('gacSbidUnderFlatMax', sbid.under_flat_max);
                gacSetVal('gacSbidUnderFlatIncr', sbid.under_flat_incr);
            }
            function gacCollectSbid() {
                return {
                    util_low: gacNum('gacSbidUtilLow'),
                    util_high: gacNum('gacSbidUtilHigh'),
                    over_mult_l1: gacNum('gacSbidOverMultL1'),
                    under_mult_l1: gacNum('gacSbidUnderMultL1'),
                    under_mult_l7: gacNum('gacSbidUnderMultL7'),
                    under_fallback: gacNum('gacSbidUnderFallback'),
                    under_flat_max: gacNum('gacSbidUnderFlatMax'),
                    under_flat_incr: gacNum('gacSbidUnderFlatIncr'),
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

                // Keep the numeric average ACOS (matches the badge) in sync and
                // repaint the Action column so its alert reflects the current average.
                var newAvgAcos = salesSum > 0 ? (spendSum / salesSum) * 100 : 0;
                if (newAvgAcos !== gacRawAvgAcos) {
                    gacRawAvgAcos = newAvgAcos;
                    gacRawReformatActionColumn();
                }
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
                var days = parseInt((rangeEl && rangeEl.value) || '30', 10) || 30;
                var titleEl = document.getElementById('gacRawBadgeChartTitle');
                var isGreenUtil = metric === 'green_util_l7';
                if (titleEl) {
                    var rangeLabel = days === 0 ? 'Lifetime' : ('L' + days);
                    // Green util history is a daily count (re-anchored windows), not a rolling L30 average.
                    if (isGreenUtil) {
                        titleEl.textContent = 'Google Shopping - ' + (label || 'Green Util (L7)') + ' (Daily · ' + rangeLabel + ')';
                    } else {
                        // Same title pattern as all-marketplace-master: "… (Rolling L30)"
                        titleEl.textContent = 'Google Shopping - ' + (label || metric.toUpperCase()) + ' (Rolling ' + rangeLabel + ')';
                    }
                }

                var params = new URLSearchParams({ metric: metric, days: String(days) });
                // Green util history is a persisted channel-level daily count (not scoped to visible rows).
                if (!isGreenUtil) {
                    var ids = gacRawVisibleCampaignIds();
                    if (ids.length) {
                        params.set('campaign_ids', ids.join(','));
                    }
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

                var refRed = '#dc3545';
                var refGray = '#6c757d';
                var refGreen = '#198754';
                ['gacRawBadgeChartHighest', 'gacRawBadgeChartMedian', 'gacRawBadgeChartLowest'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.textContent = '-';
                        el.style.color = refGray;
                    }
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
                var dataMin = Math.min.apply(Math, values);
                var dataMax = Math.max.apply(Math, values);
                var sorted = values.slice().sort(function(a, b) { return a - b; });
                var mid = Math.floor(sorted.length / 2);
                var median = sorted.length % 2
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;
                var range = (dataMax - dataMin) || 1;
                var yMin = Math.max(0, dataMin - range * 0.1);
                var yMax = dataMax + range * 0.1;
                var inverted = (metric === 'acos');

                function fmtVal(v) {
                    return gacRawFormatBadgeChartValue(metric, v);
                }

                // Right-side panel — same color rules as all-marketplace-master
                var highestEl = document.getElementById('gacRawBadgeChartHighest');
                var medianEl = document.getElementById('gacRawBadgeChartMedian');
                var lowestEl = document.getElementById('gacRawBadgeChartLowest');
                if (highestEl) {
                    highestEl.textContent = fmtVal(dataMax);
                    highestEl.style.color = dataMax === 0 ? refGreen : (dataMax > 0 ? refRed : refGray);
                }
                if (medianEl) {
                    medianEl.textContent = fmtVal(median);
                    medianEl.style.color = median === 0 ? refGreen : (median > 0 ? refRed : refGray);
                }
                if (lowestEl) {
                    lowestEl.textContent = fmtVal(dataMin);
                    lowestEl.style.color = dataMin === 0 ? refGreen : (dataMin > 0 ? refRed : refGray);
                }

                // Dot colors: green=UP red=DOWN; inverted for ACOS (lower is better)
                var pointColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    if (inverted) {
                        return v < values[i - 1] ? '#28a745' : (v > values[i - 1] ? '#dc3545' : '#6c757d');
                    }
                    return v > values[i - 1] ? '#28a745' : (v < values[i - 1] ? '#dc3545' : '#6c757d');
                });
                var labelColors = values.map(function(v) {
                    return v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d');
                });

                var medianLinePlugin = {
                    id: 'gacRawMedianLine',
                    afterDraw: function(chart) {
                        var yScale = chart.scales.y;
                        var xScale = chart.scales.x;
                        var ctx = chart.ctx;
                        var yPixel = yScale.getPixelForValue(median);
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

                // Value labels above each point (same as all-marketplace-master)
                var valueLabelsPlugin = {
                    id: 'gacRawValueLabels',
                    afterDatasetsDraw: function(chart) {
                        var dataset = chart.data.datasets[0];
                        var meta = chart.getDatasetMeta(0);
                        var ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach(function(point, i) {
                            var val = dataset.data[i];
                            var offsetY = (i % 2 === 0) ? -10 : -20;
                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(fmtVal(val), point.x, point.y + offsetY);
                        });
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
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointColors,
                            pointBorderWidth: 1.5
                        }]
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
                                        var idx = context.dataIndex;
                                        var parts = ['Value: ' + fmtVal(context.raw)];
                                        if (idx > 0) {
                                            var diff = context.raw - values[idx - 1];
                                            var arrow = diff < 0 ? '▼' : (diff > 0 ? '▲' : '▬');
                                            parts.push('vs Yesterday: ' + arrow + ' ' + fmtVal(Math.abs(diff)));
                                        }
                                        if (idx >= 7) {
                                            var diff7 = context.raw - values[idx - 7];
                                            var arrow7 = diff7 < 0 ? '▼' : (diff7 > 0 ? '▲' : '▬');
                                            parts.push('vs 7d Ago: ' + arrow7 + ' ' + fmtVal(Math.abs(diff7)));
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
                                    callback: function(value) {
                                        return fmtVal(value);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
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
                        sbgt: 0,
                        label: GAC_DEFAULT_BAND_LABELS[bands.length] || 'Band',
                        color: gacAcosSchemaStyleForBand(lastTo, 9999).bg,
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

            function gacBindBgtSlabRule(opts) {
                var bands = [];
                var defaults = opts.defaults || [];
                var labels = opts.labels || [];
                var colors = opts.colors || [];
                var fromKey = opts.fromKey;
                var toKey = opts.toKey;
                var minBgt = opts.minBgt != null ? opts.minBgt : 0;
                function normalize(existing) {
                    var prev = Array.isArray(existing) ? existing : [];
                    var out = [];
                    prev.forEach(function(keep) {
                        if (!keep || typeof keep !== 'object') return;
                        var from = parseFloat(keep[fromKey]);
                        var to = parseFloat(keep[toKey]);
                        var bgt = parseInt(keep.bgt, 10);
                        var row = {};
                        row[fromKey] = isFinite(from) ? from : '';
                        row[toKey] = isFinite(to) ? to : '';
                        row.bgt = isFinite(bgt) && bgt >= minBgt ? bgt : '';
                        row.label = keep.label != null ? String(keep.label) : '';
                        row.color = keep.color || '#6c757d';
                        out.push(row);
                    });
                    return out.length ? out : defaults.map(function(d) { return Object.assign({}, d); });
                }
                function valueOfRow(row) {
                    return opts.valueOfRow ? opts.valueOfRow(row) : null;
                }
                function counts() {
                    var out = (bands || []).map(function() { return 0; });
                    if (!table || typeof table.getData !== 'function') return out;
                    var rows = [];
                    try { rows = table.getData() || []; } catch (e) { return out; }
                    rows.forEach(function(row) {
                        var n = valueOfRow(row);
                        if (n == null || !isFinite(n)) return;
                        for (var i = 0; i < bands.length; i++) {
                            var from = parseFloat(bands[i][fromKey]);
                            var to = parseFloat(bands[i][toKey]);
                            if (!isFinite(from) || !isFinite(to)) continue;
                            var hit = (n >= from && n <= to);
                            if (!hit && opts.fillGaps) {
                                var nextFrom = (i < bands.length - 1) ? parseFloat(bands[i + 1][fromKey]) : NaN;
                                hit = isFinite(nextFrom) && n > to && n < nextFrom;
                            }
                            if (hit) { out[i]++; break; }
                        }
                    });
                    return out;
                }
                function render() {
                    var tbody = document.getElementById(opts.tbodyId);
                    if (!tbody) return;
                    var canDelete = bands.length > 1;
                    var c = counts();
                    tbody.innerHTML = '';
                    bands.forEach(function(band, i) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = ''
                            + '<td class="text-muted small">' + (i + 1) + '</td>'
                            + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                            + '<td><input type="number" step="' + (opts.step || '1') + '" min="0" class="form-control form-control-sm" value="' + (band[fromKey] != null ? band[fromKey] : '') + '" data-idx="' + i + '" data-field="' + fromKey + '"></td>'
                            + '<td><input type="number" step="' + (opts.step || '1') + '" min="0" class="form-control form-control-sm" value="' + (band[toKey] != null ? band[toKey] : '') + '" data-idx="' + i + '" data-field="' + toKey + '"></td>'
                            + '<td class="text-center"><span class="fw-semibold" data-count-idx="' + i + '">' + (c[i] != null ? c[i] : 0) + '</span></td>'
                            + '<td><input type="number" step="1" min="' + minBgt + '" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>'
                            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                        tbody.appendChild(tr);
                    });
                    tbody.querySelectorAll('input[data-idx]').forEach(function(inp) {
                        inp.addEventListener('input', function() {
                            var idx = +this.dataset.idx, fld = this.dataset.field;
                            if (!bands[idx]) return;
                            if (fld === 'bgt') bands[idx][fld] = (this.value === '' ? '' : parseInt(this.value, 10));
                            else if (fld === fromKey || fld === toKey) bands[idx][fld] = (this.value === '' ? '' : parseFloat(this.value));
                            else bands[idx][fld] = this.value;
                            if (fld === fromKey || fld === toKey) {
                                var c = counts();
                                tbody.querySelectorAll('[data-count-idx]').forEach(function(el) {
                                    var ci = +el.dataset.countIdx;
                                    el.textContent = String(c[ci] != null ? c[ci] : 0);
                                });
                            }
                        });
                    });
                    tbody.querySelectorAll('[data-remove-idx]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            if (bands.length <= 1) return;
                            bands.splice(+this.dataset.removeIdx, 1);
                            render();
                        });
                    });
                }
                function loadFromRule(rule) {
                    bands = normalize((rule && Array.isArray(rule.bands)) ? rule.bands : []);
                    render();
                }
                var modalEl = document.getElementById(opts.modalId);
                if (modalEl) {
                    modalEl.addEventListener('show.bs.modal', function() {
                        var err = document.getElementById(opts.errId);
                        if (err) { err.classList.add('d-none'); err.textContent = ''; }
                        fetch(opts.getUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(body) { loadFromRule((body && body.rule) || {}); })
                            .catch(function() { loadFromRule({}); });
                    });
                }
                var addBtn = document.getElementById(opts.addBtnId);
                if (addBtn) {
                    addBtn.addEventListener('click', function() {
                        var last = bands.length ? bands[bands.length - 1] : null;
                        var lastTo = last ? parseFloat(last[toKey]) : NaN;
                        var from = isFinite(lastTo) ? lastTo : 0;
                        if (opts.fillGaps && isFinite(lastTo)) from = +(lastTo + 0.01).toFixed(2);
                        var i = bands.length;
                        var row = { bgt: minBgt, label: labels[i] || ('Slab ' + (i + 1)), color: colors[i] || '#6c757d' };
                        row[fromKey] = from;
                        row[toKey] = opts.fillGaps ? +(from + 0.49).toFixed(2) : 9999;
                        bands.push(row);
                        render();
                    });
                }
                var saveBtn = document.getElementById(opts.saveBtnId);
                if (saveBtn) {
                    saveBtn.addEventListener('click', function() {
                        var err = document.getElementById(opts.errId);
                        if (err) { err.classList.add('d-none'); err.textContent = ''; }
                        var cleaned = (bands || []).map(function(b) {
                            var row = {
                                bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseInt(b.bgt, 10),
                                label: (b.label || '').toString(),
                                color: (b.color || '#6c757d').toString()
                            };
                            row[fromKey] = (b[fromKey] === '' || b[fromKey] == null) ? NaN : parseFloat(b[fromKey]);
                            row[toKey] = (b[toKey] === '' || b[toKey] == null) ? NaN : parseFloat(b[toKey]);
                            return row;
                        });
                        if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                        for (var i = 0; i < cleaned.length; i++) {
                            var b = cleaned[i];
                            if (!isFinite(b[fromKey]) || !isFinite(b[toKey]) || !isFinite(b.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt values.'; err.classList.remove('d-none'); } return; }
                            if (b[fromKey] > b[toKey]) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                            if (b[fromKey] < 0) { if (err) { err.textContent = 'From must be 0 or more.'; err.classList.remove('d-none'); } return; }
                            if (b.bgt < minBgt) { if (err) { err.textContent = 'Bgt must be ' + minBgt + ' or more.'; err.classList.remove('d-none'); } return; }
                        }
                        saveBtn.disabled = true;
                        fetch(opts.saveUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': gacCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ bands: cleaned })
                        })
                            .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                            .then(function(out) {
                                var body = out.body || {};
                                if (!out.ok || body.status === 422 || body.status === 500) { if (err) { err.textContent = body.message || body.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                                if (typeof bootstrap !== 'undefined' && modalEl) { var inst = bootstrap.Modal.getInstance(modalEl); if (inst) inst.hide(); }
                                return Promise.resolve(table.setData(dataUrl));
                            })
                            .then(function() { gacRawRefreshTableUiSoon(); })
                            .catch(function() { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                            .finally(function() { saveBtn.disabled = false; });
                    });
                }
            }

            gacBindBgtSlabRule({
                defaults: [
                    { views_from: 351, views_to: 9999, bgt: 6, label: 'Purple', color: '#7c3aed' },
                    { views_from: 281, views_to: 350, bgt: 5, label: 'Pink', color: '#e83e8c' },
                    { views_from: 211, views_to: 280, bgt: 4, label: 'Green', color: '#28a745' },
                    { views_from: 141, views_to: 210, bgt: 3, label: 'Blue', color: '#2563eb' },
                    { views_from: 71, views_to: 140, bgt: 2, label: 'Yellow', color: '#ffc107' },
                    { views_from: 0, views_to: 70, bgt: 1, label: 'Red', color: '#a00211' }
                ],
                labels: ['Purple', 'Pink', 'Green', 'Blue', 'Yellow', 'Red'],
                colors: ['#7c3aed', '#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'],
                fromKey: 'views_from', toKey: 'views_to', step: '1', minBgt: 0,
                modalId: 'gacRawBgtViewsRuleModal', tbodyId: 'gac-bgt-views-bands-body',
                addBtnId: 'gac-bgt-views-add-band-btn', saveBtnId: 'gacRawBgtViewsRuleSaveBtn', errId: 'gacRawBgtViewsRuleErr',
                getUrl: gacBgtViewsRuleGetUrl, saveUrl: gacBgtViewsRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && row.views_l7); return isFinite(n) ? n : 0; }
            });
            gacBindBgtSlabRule({
                defaults: [
                    { cvr_from: 20, cvr_to: 9999, bgt: 6, label: 'Purple', color: '#7c3aed' },
                    { cvr_from: 16, cvr_to: 20, bgt: 5, label: 'Pink', color: '#e83e8c' },
                    { cvr_from: 12, cvr_to: 16, bgt: 4, label: 'Green', color: '#28a745' },
                    { cvr_from: 8, cvr_to: 12, bgt: 3, label: 'Blue', color: '#2563eb' },
                    { cvr_from: 4, cvr_to: 8, bgt: 2, label: 'Yellow', color: '#ffc107' },
                    { cvr_from: 0, cvr_to: 4, bgt: 1, label: 'Red', color: '#a00211' }
                ],
                labels: ['Purple', 'Pink', 'Green', 'Blue', 'Yellow', 'Red'],
                colors: ['#7c3aed', '#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'],
                fromKey: 'cvr_from', toKey: 'cvr_to', step: '0.01', minBgt: 0,
                modalId: 'gacRawBgtCvrRuleModal', tbodyId: 'gac-bgt-cvr-bands-body',
                addBtnId: 'gac-bgt-cvr-add-band-btn', saveBtnId: 'gacRawBgtCvrRuleSaveBtn', errId: 'gacRawBgtCvrRuleErr',
                getUrl: gacBgtCvrRuleGetUrl, saveUrl: gacBgtCvrRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && (row.bgt_cvr_page_cvr != null ? row.bgt_cvr_page_cvr : row.cvr_l30)); return isFinite(n) ? n : 0; }
            });
            gacBindBgtSlabRule({
                defaults: [
                    { prc_from: 151, prc_to: 9999, bgt: 5, label: 'Pink', color: '#e83e8c' },
                    { prc_from: 101, prc_to: 150, bgt: 4, label: 'Green', color: '#28a745' },
                    { prc_from: 61, prc_to: 100, bgt: 3, label: 'Blue', color: '#2563eb' },
                    { prc_from: 41, prc_to: 60, bgt: 2, label: 'Yellow', color: '#ffc107' },
                    { prc_from: 0, prc_to: 40, bgt: 1, label: 'Red', color: '#a00211' }
                ],
                labels: ['Pink', 'Green', 'Blue', 'Yellow', 'Red'],
                colors: ['#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'],
                fromKey: 'prc_from', toKey: 'prc_to', step: '0.01', minBgt: 0,
                modalId: 'gacRawBgtPrcRuleModal', tbodyId: 'gac-bgt-prc-bands-body',
                addBtnId: 'gac-bgt-prc-add-band-btn', saveBtnId: 'gacRawBgtPrcRuleSaveBtn', errId: 'gacRawBgtPrcRuleErr',
                getUrl: gacBgtPrcRuleGetUrl, saveUrl: gacBgtPrcRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && row.bgt_prc_price); return isFinite(n) ? n : null; }
            });
        });
    </script>
@endsection
