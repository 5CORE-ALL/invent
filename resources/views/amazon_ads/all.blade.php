@extends('layouts.vertical', ['title' => 'Amz Ads All'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .amz-ads-all,
        .amz-ads-all .col-12,
        .amz-ads-all .card,
        .amz-ads-all .card-body {
            min-width: 0;
        }
        .amz-ads-all .card,
        .amz-ads-all .card-body {
            max-width: 100%;
        }
        /* clip does not create a scrollport, so sticky headers still pin to the page */
        .amz-ads-all .card { overflow-x: clip; }
        .amz-ads-all .card-body { overflow-x: visible; }

        #amz-ads-raw-wrap {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: visible;
            padding-bottom: 56px;
        }
        #amz-ads-raw-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; font-size: 13px;
            width: 100% !important;
            max-width: 100%;
            min-width: 0;
            overflow: visible !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header,
        #amz-ads-raw-wrap .tabulator .tabulator-tableholder,
        #amz-ads-raw-wrap .tabulator .tabulator-footer {
            max-width: 100%;
            min-width: 0;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-tableholder {
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header {
            position: sticky !important;
            top: var(--tz-topbar-height, 70px) !important;
            z-index: 24 !important;
            background: #dbeafe; border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: flex !important;
            align-items: center;
            visibility: visible !important;
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            opacity: 0.4;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter .tabulator-arrow {
            display: inline-block !important;
            visibility: visible !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-bottom: 5px solid #64748b !important;
            border-top: 0 !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="desc"] .tabulator-col-sorter .tabulator-arrow {
            border-bottom: 0 !important;
            border-top: 5px solid #334155 !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="asc"] .tabulator-col-sorter .tabulator-arrow {
            border-top: 0 !important;
            border-bottom: 5px solid #334155 !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-sorter,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="asc"] .tabulator-col-sorter,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="desc"] .tabulator-col-sorter {
            opacity: 1;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 12px;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important;             display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #amz-ads-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-ads-raw-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignStatus"] .tabulator-col-title { white-space: nowrap !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="ruleStatus"] .tabulator-col-title { white-space: nowrap !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell .amz-raw-status-cell { white-space: nowrap; }
        #amz-ads-raw-wrap .amz-camp-skus-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 16px; height: 16px; padding: 0; margin-left: 4px; flex-shrink: 0;
            border: 1px solid #93c5fd; border-radius: 50%; background: #eff6ff;
            color: #2563eb; font-size: 9px; line-height: 1; cursor: pointer;
        }
        #amz-ads-raw-wrap .amz-camp-skus-btn:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        /* Pagination footer */
        #amz-ads-raw-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
            overflow-x: auto;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] { opacity: 0.4 !important; cursor: not-allowed !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        /* U% utilization colors */
        #amz-ads-raw-wrap .tabulator .tabulator-cell.green-bg { color: #16a34a !important; font-weight: 600; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell.pink-bg { color: #db2777 !important; font-weight: 600; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell.red-bg { color: #dc2626 !important; font-weight: 600; }
        /* Toolbar + badges */
        .amz-ads-toolbar { min-width: 0; }
        .amz-stat-badges {
            display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;
            min-width: 0; flex: 1 1 auto;
        }
        .amz-ads-toolbar-actions {
            display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;
            min-width: 0;
        }
        /* Filter bar */
        #amz-raw-filter-bar { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        #amz-raw-filter-bar .amz-raw-filter-fields {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem 1rem;
        }
        #amz-raw-filter-bar .amz-raw-filter-field {
            flex: 0 1 auto; min-width: 110px;
        }
        #amz-raw-filter-bar .amz-raw-filter-actions { flex: 0 0 auto; }
        #amz-raw-filter-bar .amz-raw-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #amz-raw-filter-bar .amz-raw-filter-select,
        #amz-raw-filter-bar .amz-raw-date-input {
            width: 100%; min-width: 0; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff;
            font-size: 0.8125rem; padding: 0.35rem 0.4rem;
        }
        #amz-raw-filter-bar .amz-raw-filter-select { color: #64748b; }
        #amz-raw-filter-bar .amz-raw-filter-select.is-acos-color,
        #amz-raw-filter-bar .amz-raw-filter-select.is-ads-cvr-color { font-weight: 700; }
        #amz-raw-filter-bar .amz-raw-date-input { color: #334155; }
        #amazonAdsFilterAcos option,
        #amazonAdsFilterAdsCvr option { font-weight: 600; }
        .amz-ads-search-bar { min-width: 0; }
        .amz-ads-search-bar #amz-filter-search { min-width: 0; flex: 1 1 auto; }
        /* Stat badges */
        .amz-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .amz-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .amz-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .amz-raw-icon-btn > i { font-size: 14px; }
        .amz-toolbar-title { font-size: 1rem; flex-shrink: 0; }
        .amz-stat-badge--campaign { background: #4c7ed8; }
        .amz-stat-badge--acos     { background: #ea580c; }
        .amz-stat-badge--spend    { background: #ef4444; }
        .amz-stat-badge--clicks   { background: #f59e0b; }
        .amz-stat-badge--sold     { background: #8b5cf6; }
        .amz-stat-badge--cvr      { background: #16a34a; }
        .amz-stat-badge--cpc      { background: #0891b2; }
        .amz-stat-badge--sales    { background: #16a34a; }
        #amz-ads-raw-wrap #amazonAdsU7Pie { width: 100%; min-height: 400px; }

        @media (max-width: 991.98px) {
            .amz-stat-badge { font-size: 13px; padding: 7px 12px; }
            .amz-stat-badge > span { font-size: 14px; }
            #amz-raw-filter-bar .amz-raw-filter-field { flex: 1 1 calc(33.333% - 1rem); min-width: 140px; }
        }
        @media (max-width: 767.98px) {
            .amz-ads-all .card-body { padding: 0.75rem; }
            .amz-stat-badge { font-size: 12px; padding: 6px 10px; }
            .amz-stat-badge > span { font-size: 13px; }
            #amz-raw-filter-bar { padding: 10px; }
            #amz-raw-filter-bar .amz-raw-filter-field { flex: 1 1 calc(50% - 0.75rem); min-width: 130px; }
            #amz-ads-raw-wrap .tabulator { font-size: 12px; }
            #amz-ads-raw-wrap .tabulator .tabulator-footer { padding: 8px 10px !important; }
            #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
                min-width: 32px !important; height: 32px !important; line-height: 32px !important; font-size: 13px !important;
            }
            #amz-ads-raw-wrap .tabulator .tabulator-header { top: var(--tz-topbar-height, 56px) !important; }
        }
        @media (max-width: 575.98px) {
            #amz-raw-filter-bar .amz-raw-filter-field { flex: 1 1 100%; min-width: 0; }
            #amz-raw-filter-bar .amz-raw-filter-actions { width: 100%; }
            #amz-raw-filter-bar .amz-raw-filter-actions .btn { flex: 1 1 auto; }
            .amz-ads-search-bar { flex-wrap: wrap; }
            #amazonAdsU7Pie { min-height: 280px !important; }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amz Ads', 'page_title' => 'Amz Ads All'])

    <div class="row amz-ads-all">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="amz-ads-toolbar d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="amz-stat-badges py-1">
                            <span id="amazonAdsCampaignBadgeWrap" class="amz-stat-badge amz-stat-badge--campaign" title="Distinct campaigns matching current filters">CAMPAIGN:<span id="amazonAdsCampaignBadgeValue">0</span></span>
                            <span id="amazonAdsOverallAcosBadgeWrap" class="amz-stat-badge amz-stat-badge--acos" title="Overall ACOS (L30) for the filtered set">ACOS:<span id="amazonAdsOverallAcosBadgeValue">0%</span></span>
                            <span id="amazonAdsSpendBadgeWrap" class="amz-stat-badge amz-stat-badge--spend" title="Spend (L30) total">SPEND:<span id="amazonAdsSpendBadgeValue">$0</span></span>
                            <span id="amazonAdsClicksBadgeWrap" class="amz-stat-badge amz-stat-badge--clicks" title="Clicks (L30) total">CLICKS:<span id="amazonAdsClicksBadgeValue">0</span></span>
                            <span id="amazonAdsSoldBadgeWrap" class="amz-stat-badge amz-stat-badge--sold" title="Sold (L30) total">SOLD:<span id="amazonAdsSoldBadgeValue">0</span></span>
                            <span id="amazonAdsCvrBadgeWrap" class="amz-stat-badge amz-stat-badge--cvr" title="Ads CVR = Ads Sold / Ads Clicks (L30)">CVR:<span id="amazonAdsCvrBadgeValue">0%</span></span>
                            <span id="amazonAdsCpcBadgeWrap" class="amz-stat-badge amz-stat-badge--cpc" title="CPC = Spend / Clicks">CPC:<span id="amazonAdsCpcBadgeValue">$0</span></span>
                            <span id="amazonAdsSalesBadgeWrap" class="amz-stat-badge amz-stat-badge--sales" title="Sales (L30) total">SALES:<span id="amazonAdsSalesBadgeValue">$0</span></span>
                        </div>

                        <div class="amz-ads-toolbar-actions">
                            <span id="amz-raw-total" class="badge bg-secondary">Total: —</span>
                            <span id="amz-raw-page-info" class="badge bg-light text-dark border">Page: —</span>
                            <button type="button" id="amz-raw-refresh" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh grid" aria-label="Refresh grid">
                                <i class="fa fa-refresh"></i>
                            </button>
                            <button type="button" id="amazonAdsSectionExportBtn" class="btn btn-sm btn-success amz-raw-icon-btn" title="Export current page as CSV" aria-label="Export current page as CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <a href="{{ route('amazon-ads.push-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Failed / skipped bid & budget pushes">Fail Cpg</a>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtRuleModal" title="Edit ACOS band thresholds and SBGT tier values">BGT Vs ACOS Rule</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtViewsRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtViewsRuleModal" title="Edit View L30 bands and Bgt Views values">BGT Vs VIEWS</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtCvrRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtCvrRuleModal" title="Edit CVR L30 bands and Bgt Cvr values">BGT Vs CVR</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtPrcRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtPrcRuleModal" title="Edit Price bands and BGT PRC values">BGT PRC</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtReviewsRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtReviewsRuleModal" title="Edit Reviews star bands and Bgt Reviews values">BGT Vs REVIEWS</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsSbidRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsSbidRuleModal" title="Edit U2%/U1% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="amazonAdsPrRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsPrRuleModal" title="Auto-pause when Dil% is high, price is below your threshold, or reviews are below your star rating">Pause Rule</button>
                            <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                            <button type="button" class="btn btn-sm btn-warning text-dark" id="amazonAdsPushSbgtBtn" title="Push SBGT in chunks of 5 as daily budget for the rows on this page (SP/SB only).">
                                <i class="fa fa-cloud-upload-alt"></i> SBGT
                            </button>
                            <button type="button" class="btn btn-sm btn-warning text-dark" id="amazonAdsPushSbidBtn" title="Push SBID in chunks of 5 using the values shown on this page (SP/SB only).">
                                <i class="fa fa-cloud-upload-alt"></i> SBID
                            </button>
                        </div>
                    </div>

                    <div id="amz-raw-filter-bar" class="mb-3">
                        <div class="amz-raw-filter-fields">
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterReportType">Table</label>
                                <select id="amazonAdsFilterReportType" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="all_reports">All (SP + SB)</option>
                                    <option value="sp_reports" selected>SP reports</option>
                                    <option value="sb_reports">SB reports</option>
                                    <option value="sd_reports">SD reports</option>
                                    <option value="sp_keywords">SP keywords</option>
                                    <option value="sp_negatives">SP negatives</option>
                                    <option value="bid_caps">Bid caps</option>
                                    <option value="fbm_targeting">FBM targeting</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterSummaryRange">Range</label>
                                <select id="amazonAdsFilterSummaryRange" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>Calendar</option>
                                    <option value="L1">L1</option>
                                    <option value="L7">L7</option>
                                    <option value="L14">L14</option>
                                    <option value="L15">L15</option>
                                    <option value="L30">L30</option>
                                    <option value="L60">L60</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterDateFrom">From</label>
                                <input type="date" id="amazonAdsFilterDateFrom" class="form-control form-control-sm amz-raw-date-input">
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterDateTo">To</label>
                                <input type="date" id="amazonAdsFilterDateTo" class="form-control form-control-sm amz-raw-date-input">
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU7">U7%</label>
                                <select id="amazonAdsFilterU7" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU2">U2%</label>
                                <select id="amazonAdsFilterU2" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU1">U1%</label>
                                <select id="amazonAdsFilterU1" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterCampaignStatus">Stat</label>
                                <select id="amazonAdsFilterCampaignStatus" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="ENABLED">Enabled</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="ARCHIVED">Archived</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field" style="min-width:140px;">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterAcos">Acos</label>
                                <select id="amazonAdsFilterAcos" class="form-select form-select-sm amz-raw-filter-select" title="Filter by ACOS color band (same BGT color rules as the ACOS% column)">
                                    <option value="" selected>All</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field" style="min-width:150px;">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterAdsCvr">Ads CVR</label>
                                <select id="amazonAdsFilterAdsCvr" class="form-select form-select-sm amz-raw-filter-select" title="Filter by Ads CVR color band (same Amz page CVR L30 colors as the Ads CVR column)">
                                    <option value="" selected>All</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-actions d-flex align-items-end flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="amazonAdsFilterApply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amazonAdsFilterClear">Clear</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amazonAdsU7PieOpenBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsU7PieModal" title="Row counts by U7% band (U7 filter ignored). Click a slice for last 30 days.">U7% mix</button>
                            </div>
                        </div>
                    </div>

                    <div id="amz-raw-push-result" class="alert alert-secondary small d-none mt-2 mb-2 py-2" role="status" aria-live="polite">
                        <div class="fw-semibold mb-1" id="amz-raw-push-result-title"></div>
                        <pre id="amz-raw-push-result-pre" class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap;max-height:280px;overflow:auto;"></pre>
                    </div>

                    <div id="amz-ads-raw-wrap">
                        <div class="amz-ads-search-bar p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-filter-search" class="form-control" placeholder="Search Campaign..." autocomplete="off" aria-label="Search by campaign name" maxlength="100">
                            <span id="amz-raw-source-label" class="badge bg-dark text-nowrap flex-shrink-0"></span>
                        </div>
                        <div id="amz-ads-raw-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsU7PieModal" tabindex="-1" aria-labelledby="amazonAdsU7PieModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsU7PieModalLabel">U7% mix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Row counts by U7% band (U7 grid filter ignored). Click a slice for the last 30 days.</p>
                    <div id="amazonAdsU7Pie" role="img" aria-label="U7 percent distribution pie chart"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsU7HistoryModal" tabindex="-1" aria-labelledby="amazonAdsU7HistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsU7HistoryModalLabel">U7% — daily row counts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="amazonAdsU7HistoryModalSub">Last 30 calendar days. Same U2/U1/Stat filters as the grid; U7 filter ignored.</p>
                    <div id="amazonAdsU7HistoryModalLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="amazonAdsU7HistoryModalError" role="alert"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="amazonAdsU7HistoryTable">
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
                            <tbody id="amazonAdsU7HistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsCampaignSkusModal" tabindex="-1" aria-labelledby="amazonAdsCampaignSkusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsCampaignSkusModalLabel">Campaign SKUs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="amazonAdsCampaignSkusModalSub"></p>
                    <div id="amazonAdsCampaignSkusLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="amazonAdsCampaignSkusError" role="alert"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="amazonAdsCampaignSkusTable">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>ASIN</th>
                                    <th>Reviews</th>
                                    <th>State</th>
                                </tr>
                            </thead>
                            <tbody id="amazonAdsCampaignSkusTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtRuleModalLabel">BGT rule — ACOS % → Suggested Budget (SBGT)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>ACOS % range</strong> (From → To). Rows are checked
                        <strong>top to bottom</strong>; the first range that contains the campaign's ACOS gets its SBGT.
                        Use <code>9999</code> on <em>To</em> for the catch-all highest band.
                        <strong>SBGT 0</strong> cannot be pushed as daily budget — those campaigns are paused instead.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>ACOS%</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From (%)</th>
                                <th style="width:110px;">To (%)</th>
                                <th style="width:120px;">SBGT</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtRuleBandsBody"></tbody>
                    </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amazonAdsBgtRuleAddBandBtn">
                        <i class="fas fa-plus me-1"></i>Add band
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtViewsRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtViewsRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtViewsRuleModalLabel">BGT Vs VIEWS — View L30 → Bgt Views</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Amz page View L30</strong> range (parent Sess30) for the campaign.
                        Rows are checked <strong>top to bottom</strong>; the first range that contains the views gets its
                        <strong>Bgt Views</strong>. Fill the <strong>first</strong> slab (default <code>0–70</code>) and the
                        next five slabs autofill. Last <em>To</em> is <code>9999</code>.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtViewsRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:120px;">Bgt Views</th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtViewsRuleBandsBody"></tbody>
                    </table>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtViewsRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtViewsRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtCvrRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtCvrRuleModalLabel">BGT Vs CVR — CVR L30 → Bgt Cvr</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Amz page CVR L30</strong> range (parent A L30 ÷ Sess30 × 100) for the campaign.
                        Rows are checked <strong>top to bottom</strong>; the first range that contains the CVR gets its
                        <strong>Bgt Cvr</strong>. Fill the <strong>first</strong> slab (default <code>0–4</code>) and the
                        next five slabs autofill. Last <em>To</em> is <code>9999</code>.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtCvrRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:120px;">Bgt Cvr</th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtCvrRuleBandsBody"></tbody>
                    </table>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtCvrRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtCvrRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtPrcRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtPrcRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtPrcRuleModalLabel">BGT PRC — Price → Bgt Prc</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Price</strong> range (same Price column: Amz list, else LMP).
                        Five fixed slabs: <code>20–40</code>, <code>41–60</code>, <code>61–100</code>, <code>101–150</code>, <code>&gt;150</code>.
                        Price below <code>20</code> has no BGT PRC. Edit label, color, and Bgt; ranges stay locked.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtPrcRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:120px;">Bgt Prc</th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtPrcRuleBandsBody"></tbody>
                    </table>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtPrcRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtPrcRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtReviewsRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtReviewsRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtReviewsRuleModalLabel">BGT Vs REVIEWS — Reviews → Bgt Reviews</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>Reviews</strong> range (same star rating as the Reviews column).
                        Rows are checked <strong>top to bottom</strong>; the first range that contains the rating gets its
                        <strong>Bgt Reviews</strong>. Add or delete slabs as needed.
                        <strong>Count</strong> is campaigns on this grid page in that range.
                    </p>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtReviewsRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From</th>
                                <th style="width:110px;">To</th>
                                <th style="width:80px;" title="Campaigns on this grid page whose Reviews rating falls in this slab">Count</th>
                                <th style="width:120px;">Bgt Reviews</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtReviewsRuleBandsBody"></tbody>
                    </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amazonAdsBgtReviewsRuleAddBandBtn">
                        <i class="fas fa-plus me-1"></i>Add slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtReviewsRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtReviewsRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsSbidRuleModal" tabindex="-1" aria-labelledby="amazonAdsSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsSbidRuleModalLabel">SBID rule — U2% / U1% → suggested bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">When <strong>both</strong> U2% and U1% are <strong>below</strong> the low threshold, SBID = CPC × under multipliers (or fallback when no CPC). When <strong>both</strong> are <strong>above</strong> the high threshold, SBID = L1 CPC × over multiplier. Otherwise SBID shows —.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilLow">Low threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilLow" name="util_low" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilHigh">High threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilHigh" name="util_high" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleBothLowFallback">Fallback (no CPC)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleBothLowFallback" name="both_low_fallback" required>
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low — CPC multipliers</p>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL1" name="both_low_mult_l1" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL2">× L2 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL2" name="both_low_mult_l2" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL7">× L7 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL7" name="both_low_mult_l7" required>
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both above high</p>
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleHighMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleHighMultL1" name="both_high_mult_l1" required>
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsSbidRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSbidRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsPrRuleModal" tabindex="-1" aria-labelledby="amazonAdsPrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsPrRuleModalLabel">Pause Rule — auto-pause</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Dil%, Price, and Reviews are <strong>separate</strong> rules. If <strong>any</strong> matches, ads are paused (OR).
                        Dil% uses the same <strong>dil</strong> column as this table (ovl30 ÷ Inv) and pauses the <strong>campaign</strong>.
                        Price uses the <strong>price</strong> column (including grey LMP) and pauses the <strong>campaign</strong>.
                        Reviews uses each advertised SKU’s star rating and pauses only that <strong>product ad</strong> (campaign stays on).
                        Save (with auto-pause on) applies matching pauses on Amazon now. The job also runs daily at 18:25 IST.
                    </p>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="amazonAdsPrDilEnabled" checked>
                        <label class="form-check-label small" for="amazonAdsPrDilEnabled">Pause when Dil% ≥</label>
                    </div>
                    <div class="input-group input-group-sm mb-3" style="max-width: 220px;">
                        <input type="number" min="0" max="100000" step="1" class="form-control" id="amazonAdsPrDilAbove" value="100">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="amazonAdsPrPriceEnabled" checked>
                        <label class="form-check-label small" for="amazonAdsPrPriceEnabled">Pause when Price &lt;</label>
                    </div>
                    <div class="input-group input-group-sm mb-3" style="max-width: 220px;">
                        <span class="input-group-text">$</span>
                        <input type="number" min="0" max="1000000" step="0.01" class="form-control" id="amazonAdsPrPriceBelow" value="20">
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="amazonAdsPrReviewsEnabled" checked>
                        <label class="form-check-label small" for="amazonAdsPrReviewsEnabled">Pause when Reviews &lt;</label>
                    </div>
                    <div class="input-group input-group-sm mb-3" style="max-width: 220px;">
                        <input type="number" min="1" max="5" step="0.01" class="form-control" id="amazonAdsPrReviewsBelow" value="2.99">
                        <span class="input-group-text">★</span>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="amazonAdsPrEnabled" checked>
                        <label class="form-check-label small" for="amazonAdsPrEnabled">Enable auto-pause</label>
                    </div>
                    <p class="small text-danger mb-0 mt-3 d-none" id="amazonAdsPrRuleModalError" role="alert"></p>
                    <p class="small text-success mb-0 mt-2 d-none" id="amazonAdsPrRuleModalOk" role="status"></p>
                </div>
                <div class="modal-footer py-2 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsPrRuleSaveBtn">Save</button>
                    <button type="button" class="btn btn-sm btn-danger" id="amazonAdsPrRuleApplyBtn">Save &amp; apply to Amazon</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rawSources = @json($rawSources ?? []);
            var amazonAdsDefaultReportDates = @json($defaultReportRangeDates ?? (object) []);
            var dataUrlTemplate = @json(url('/amazon-ads/raw-data')) + '/';
            var pushSpSbidsUrl = @json(route('amazon.ads.push-sp-sbids'));
            var pushSbSbidsUrl = @json(route('amazon.ads.push-sb-sbids'));
            var pushSpSbgtsUrl = @json(route('amazon.ads.push-sp-sbgts'));
            var pushSbSbgtsUrl = @json(route('amazon.ads.push-sb-sbgts'));
            var bgtRuleGetUrl = @json(route('amazon.ads.bgt-rule'));
            var bgtRuleSaveUrl = @json(route('amazon.ads.bgt-rule.save'));
            var bgtViewsRuleGetUrl = @json(route('amazon.ads.bgt-views-rule'));
            var bgtViewsRuleSaveUrl = @json(route('amazon.ads.bgt-views-rule.save'));
            var bgtCvrRuleGetUrl = @json(route('amazon.ads.bgt-cvr-rule'));
            var bgtCvrRuleSaveUrl = @json(route('amazon.ads.bgt-cvr-rule.save'));
            var bgtPrcRuleGetUrl = @json(route('amazon.ads.bgt-prc-rule'));
            var bgtPrcRuleSaveUrl = @json(route('amazon.ads.bgt-prc-rule.save'));
            var bgtReviewsRuleGetUrl = @json(route('amazon.ads.bgt-reviews-rule'));
            var bgtReviewsRuleSaveUrl = @json(route('amazon.ads.bgt-reviews-rule.save'));
            var sbidRuleGetUrl = @json(route('amazon.ads.sbid-rule'));
            var sbidRuleSaveUrl = @json(route('amazon.ads.sbid-rule.save'));
            var pauseRuleGetUrl = @json(route('amazon.ads.pause-rule'));
            var prRuleSaveUrl = @json(route('amazon.ads.pr-rule.save'));
            var campaignSkusUrl = @json(route('amazon.ads.campaign-skus'));
            var u7PieDistribUrl = @json(url('/amazon-ads/u7-distribution')) + '/';
            var u7PieHistoryUrl = @json(url('/amazon-ads/u7-distribution-history')) + '/';
            window.amazonAdsBgtRule = @json($amazonAdsBgtRule ?? null);
            window.amazonAdsBgtViewsRule = @json($amazonAdsBgtViewsRule ?? null);
            window.amazonAdsBgtCvrRule = @json($amazonAdsBgtCvrRule ?? null);
            window.amazonAdsBgtPrcRule = @json($amazonAdsBgtPrcRule ?? null);
            window.amazonAdsBgtReviewsRule = @json($amazonAdsBgtReviewsRule ?? null);
            window.amazonAdsSbidRule = @json($amazonAdsSbidRule ?? null);
            window.amazonAdsPauseRule = @json($amazonAdsPauseRule ?? null);

            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var table = null;
            var activeRawSourceKey = 'sp_reports';
            var amzDrawCounter = 0;
            var amzU7PieChart = null;
            var amzU7PieRefreshTimer = null;

            var HIDDEN_COLUMNS = ['id', 'profile_id', 'campaign_id', 'report_date_range', 'ad_type', 'date', 'startDate', 'endDate', 'bgt_views_color', 'bgt_views_label', 'bgt_cvr_color', 'bgt_cvr_label', 'bgt_cvr_page_cvr', 'bgt_prc_color', 'bgt_prc_label', 'bgt_prc_price'];
            var NON_ORDERABLE_COLUMNS = [];
            var NUMERIC_SORT_DESC = ['Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'bgt', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'sbgt', 'cost', 'L7spend', 'L2spend', 'L1spend', 'L1cost', 'L1clicks', 'Prchase', 'purchases30d', 'Cvr', 'pageCvr', 'viewsL30', 'viewsL7', 'CPC3', 'CPC2', 'costPerClick', 'sales30d', 'sales', 'ACOS', 'U7%', 'U2%', 'U1%', 'last_sbid', 'sbid', 'clicks', 'impressions'];
            var PIE_SOURCES = ['sp_reports', 'sb_reports', 'sd_reports'];

            // ---- number helpers ----
            function amzFiniteNumber(data) {
                if (data === null || data === undefined || data === '') return NaN;
                var n = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
                return (typeof n === 'number' && isFinite(n)) ? n : NaN;
            }
            function amzRawNumberText(data) {
                var n = amzFiniteNumber(data);
                return isNaN(n) ? '' : String(n);
            }
            function amzDash() { return '<span class="text-muted">--</span>'; }
            function amzEsc(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            // ---- rule helpers (ACOS bands / SBGT tiers) ----
            function amzBgtRuleBands() {
                var r = window.amazonAdsBgtRule || {};
                return (r && Array.isArray(r.bands)) ? r.bands : [];
            }
            function amzBandForAcos(acos) {
                var a = typeof acos === 'number' ? acos : parseFloat(String(acos));
                if (isNaN(a)) return null;
                var bands = amzBgtRuleBands();
                for (var i = 0; i < bands.length; i++) {
                    var from = parseFloat(bands[i].acos_from);
                    var to = parseFloat(bands[i].acos_to);
                    if (isNaN(from)) from = 0;
                    if (isNaN(to)) to = 9999;
                    if (a >= from && a <= to) return bands[i];
                }
                return null;
            }
            function amzAcosTierColor(acos) {
                var band = amzBandForAcos(acos);
                return (band && band.color) ? band.color : '#6b7280';
            }
            function amzAcosBandRangeLabel(band) {
                var from = parseFloat(band && band.acos_from);
                var to = parseFloat(band && band.acos_to);
                if (!isFinite(from)) from = 0;
                if (!isFinite(to)) to = 9999;
                if (to >= 9999) return Math.round(from) + '%+';
                return Math.round(from) + '–' + Math.round(to) + '%';
            }
            function amzFillAcosFilterOptions() {
                var sel = document.getElementById('amazonAdsFilterAcos');
                if (!sel) return;
                var prev = sel.value || '';
                var bands = amzBgtRuleBands();
                sel.innerHTML = '';
                var all = document.createElement('option');
                all.value = '';
                all.textContent = 'All';
                sel.appendChild(all);
                bands.forEach(function (band, i) {
                    var opt = document.createElement('option');
                    opt.value = 'band:' + i;
                    var name = String(band.label || ('Band ' + (i + 1))).trim() || ('Band ' + (i + 1));
                    opt.textContent = '● ' + name + '  ' + amzAcosBandRangeLabel(band);
                    opt.style.color = band.color || '#334155';
                    opt.setAttribute('data-color', band.color || '');
                    sel.appendChild(opt);
                });
                var keep = false;
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === prev) { keep = true; break; }
                }
                sel.value = keep ? prev : '';
                amzTintAcosFilterSelect();
            }
            function amzTintAcosFilterSelect() {
                var sel = document.getElementById('amazonAdsFilterAcos');
                if (!sel) return;
                var opt = sel.options[sel.selectedIndex];
                var color = opt && opt.getAttribute('data-color');
                if (color) {
                    sel.classList.add('is-acos-color');
                    sel.style.color = color;
                    sel.style.borderColor = color;
                } else {
                    sel.classList.remove('is-acos-color');
                    sel.style.color = '';
                    sel.style.borderColor = '';
                }
            }
            var AMZ_ADS_CVR_BANDS = [
                { label: 'Red', color: '#a00211', range: '≤ 4%' },
                { label: 'Yellow', color: '#ffc107', range: '4–7%' },
                { label: 'Green', color: '#28a745', range: '7–13%' },
                { label: 'Pink', color: '#e83e8c', range: '13%+' }
            ];
            function amzFillAdsCvrFilterOptions() {
                var sel = document.getElementById('amazonAdsFilterAdsCvr');
                if (!sel) return;
                var prev = sel.value || '';
                sel.innerHTML = '';
                var all = document.createElement('option');
                all.value = '';
                all.textContent = 'All';
                sel.appendChild(all);
                AMZ_ADS_CVR_BANDS.forEach(function (band, i) {
                    var opt = document.createElement('option');
                    opt.value = 'band:' + i;
                    opt.textContent = '● ' + band.label + '  ' + band.range;
                    opt.style.color = band.color;
                    opt.setAttribute('data-color', band.color);
                    sel.appendChild(opt);
                });
                var keep = false;
                for (var i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].value === prev) { keep = true; break; }
                }
                sel.value = keep ? prev : '';
                amzTintAdsCvrFilterSelect();
            }
            function amzTintAdsCvrFilterSelect() {
                var sel = document.getElementById('amazonAdsFilterAdsCvr');
                if (!sel) return;
                var opt = sel.options[sel.selectedIndex];
                var color = opt && opt.getAttribute('data-color');
                if (color) {
                    sel.classList.add('is-ads-cvr-color');
                    sel.style.color = color;
                    sel.style.borderColor = color;
                } else {
                    sel.classList.remove('is-ads-cvr-color');
                    sel.style.color = '';
                    sel.style.borderColor = '';
                }
            }
            function amzSbgtTierColor(sbgt) {
                var s = parseInt(sbgt, 10);
                if (isNaN(s)) return '#6b7280';
                var bands = amzBgtRuleBands();
                for (var i = 0; i < bands.length; i++) {
                    if (parseInt(bands[i].sbgt, 10) === s && bands[i].color) return bands[i].color;
                }
                return '#6b7280';
            }
            function amzAllowedSbgtTiers() {
                var bands = amzBgtRuleBands();
                var out = [];
                for (var i = 0; i < bands.length; i++) {
                    var t = parseInt(bands[i].sbgt, 10);
                    if (!isNaN(t) && t > 0 && out.indexOf(t) === -1) out.push(t);
                }
                out.sort(function (x, y) { return x - y; });
                return out;
            }

            // ---- Tabulator formatters ----
            function fmtDashNumberRaw(cell) {
                var v = cell.getValue();
                var n = amzFiniteNumber(v);
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + amzEsc(amzRawNumberText(v)) + '</span>';
            }
            function fmtDashRounded(cell) {
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + Math.round(n).toLocaleString() + '</span>';
            }
            function fmtDashInt(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = parseInt(v, 10);
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + n.toLocaleString() + '</span>';
            }
            function fmt2dec(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                return n.toFixed(2);
            }
            function fmtSbid(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + n.toFixed(2) + '</span>';
            }
            function fmtCvr(cell) {
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold" style="color:' + amzPageCvrColor(n) + ';">' + Math.round(n) + '%</span>';
            }
            function amzPageCvrColor(cvr) {
                if (cvr <= 4) return '#a00211';
                if (cvr > 4 && cvr <= 7) return '#ffc107';
                if (cvr > 7 && cvr <= 13) return '#28a745';
                return '#e83e8c';
            }
            function fmtAmzParentViews(cell, redBelow) {
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                var num = Math.round(n);
                var formatted = num.toLocaleString('en-US');
                var row = cell.getRow ? cell.getRow().getData() : {};
                var parent = String((row && row.page_parent) || '').trim();
                var tip = parent ? ('Amz page parent · ' + parent) : 'Amz page parent views';
                if (redBelow != null && num < redBelow) {
                    return '<span class="fw-semibold" style="color:#a00211;" title="' + String(tip).replace(/"/g, '&quot;') + '">' + formatted + '</span>';
                }
                return '<span title="' + String(tip).replace(/"/g, '&quot;') + '">' + formatted + '</span>';
            }
            function fmtPageCvr(cell) {
                var row = cell.getRow().getData();
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                var sess30 = parseFloat(row.page_cvr_sess30) || 0;
                var aL30 = parseFloat(row.page_cvr_a_l30) || 0;
                var sess60 = parseFloat(row.page_cvr_sess60) || 0;
                var aL60 = parseFloat(row.page_cvr_a_l60) || 0;
                var cvr = sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                if (!isFinite(cvr)) cvr = n;
                var sess45 = (sess30 + sess60) / 2;
                var cvr45 = sess45 === 0 ? 0 : (((aL30 + aL60) / 2) / sess45) * 100;
                var color = amzPageCvrColor(cvr);
                var pctLabel = sess30 === 0 ? '0.0%' : (Math.round(cvr) + '%');
                var parent = String(row.page_parent || '').trim();
                var tip = 'Amz page parent CVR L30 = A L30 ÷ Sess30';
                if (parent) tip += ' · ' + parent;
                var tol = 0.1;
                var arrowColor = '#ffc107';
                var arrowIcon = 'fa-minus';
                var arrowTip = 'Same as CVR L45 ' + cvr45.toFixed(1) + '%';
                if (cvr === 0 || cvr < cvr45 - tol) {
                    arrowColor = '#a00211';
                    arrowIcon = 'fa-arrow-down';
                    arrowTip = (cvr === 0 ? 'CVR L30 is 0 → Down' : 'Down vs CVR L45 ' + cvr45.toFixed(1) + '%');
                } else if (cvr > cvr45 + tol) {
                    arrowColor = '#28a745';
                    arrowIcon = 'fa-arrow-up';
                    arrowTip = 'Up vs CVR L45 ' + cvr45.toFixed(1) + '%';
                }
                return '<span title="' + String(tip + ' · ' + arrowTip).replace(/"/g, '&quot;') + '" style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                    + '<span style="color:' + color + ';font-weight:600;">' + pctLabel + '</span>'
                    + ' <span style="vertical-align:middle;"><i class="fas ' + arrowIcon + '" style="color:' + arrowColor + ';font-size:12px;"></i></span>'
                    + '</span>';
            }
            function fmtAcos(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                var r = Math.round(n);
                return '<span class="fw-semibold" style="color:' + amzAcosTierColor(r) + ';">' + r + '%</span>';
            }
            function amzDilTextColor(row) {
                var inv = parseFloat(row && row.Inv);
                if (!isFinite(inv) || inv === 0) return '#6c757d';
                var ovl30 = parseFloat(row && row.ovl30) || 0;
                var dil = (ovl30 / inv) * 100;
                if (dil < 16.66) return '#a00211';
                if (dil < 25) return '#ffc107';
                if (dil < 50) return '#28a745';
                return '#e83e8c';
            }
            function fmtSbgt(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                if (t === 0) {
                    return '<span class="fw-semibold" style="color:#dc2626;" title="BGT ACOS 0 — cannot push $0; campaign will be paused">0</span>';
                }
                return '<span class="fw-semibold" style="color:' + amzSbgtTierColor(t) + ';">' + t + '</span>';
            }
            function fmtSbgtSum(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var views = parseInt(row && row.bgtViews, 10);
                var cvr = parseInt(row && row.bgtCvr, 10);
                var acos = parseInt(row && row.bgtAcos, 10);
                var prc = parseInt(row && row.bgtPrc, 10);
                var rev = parseInt(row && row.bgtReviews, 10);
                var bgt = parseFloat(row && row.bgt);
                var inSync = isFinite(bgt) && Math.round(bgt) === t;
                var color = t === 0 ? '#dc2626' : (inSync ? '#64748b' : '#0f766e');
                var tip = 'SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews';
                tip += ' · ' + (isFinite(views) ? views : 0) + ' + ' + (isFinite(cvr) ? cvr : 0) + ' + ' + (isFinite(acos) ? acos : 0) + ' + ' + (isFinite(prc) ? prc : 0) + ' + ' + (isFinite(rev) ? rev : 0);
                if (t === 0) {
                    tip += ' · BGT ACOS 0 zeros SBGT — cannot push $0, campaign will be paused';
                } else if (isFinite(bgt)) {
                    tip += inSync ? ' · matches BGT' : (' · BGT ' + Math.round(bgt) + ' → auto-push');
                }
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtViews(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_views_color) ? String(row.bgt_views_color) : '#6c757d';
                var views = parseFloat(row && row.page_cvr_sess30);
                var parent = String((row && row.page_parent) || '').trim();
                var label = String((row && row.bgt_views_label) || '').trim();
                var tip = 'Bgt Views from Amz page View L30';
                if (isFinite(views)) tip += ' · Views ' + Math.round(views);
                if (parent) tip += ' · ' + parent;
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtCvr(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_cvr_color) ? String(row.bgt_cvr_color) : '#6c757d';
                var cvr = parseFloat(row && (row.bgt_cvr_page_cvr != null ? row.bgt_cvr_page_cvr : row.pageCvr));
                var parent = String((row && row.page_parent) || '').trim();
                var label = String((row && row.bgt_cvr_label) || '').trim();
                var tip = 'Bgt Cvr from Amz page CVR L30';
                if (isFinite(cvr)) tip += ' · CVR ' + cvr + '%';
                if (parent) tip += ' · ' + parent;
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtPrc(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_prc_color) ? String(row.bgt_prc_color) : '#6c757d';
                var price = parseFloat(row && (row.bgt_prc_price != null ? row.bgt_prc_price : row.price));
                var label = String((row && row.bgt_prc_label) || '').trim();
                var tip = 'BGT PRC from campaign Price';
                if (isFinite(price)) tip += ' · $' + price;
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtReviews(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_reviews_color) ? String(row.bgt_reviews_color) : '#6c757d';
                var rating = parseFloat(row && (row.bgt_reviews_rating != null ? row.bgt_reviews_rating : row.reviews));
                var label = String((row && row.bgt_reviews_label) || '').trim();
                var tip = 'Bgt Reviews from campaign star rating';
                if (isFinite(rating)) tip += ' · ' + rating + '★';
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtUtilPercent(cell) {
                var td = cell.getElement();
                if (td) td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                if (td) {
                    if (n >= 66 && n <= 99) td.classList.add('green-bg');
                    else if (n > 99) td.classList.add('pink-bg');
                    else td.classList.add('red-bg');
                }
                return Math.round(n) + '%';
            }
            function fmtCampaignStatus(cell) {
                var v = cell.getValue();
                var raw = (v === null || v === undefined) ? '' : String(v).trim();
                if (raw === '') return '<span class="amz-raw-status-cell text-muted" title="—">—</span>';
                var enabled = raw.toUpperCase() === 'ENABLED';
                var color = enabled ? '#16a34a' : '#dc2626';
                var tip = amzEsc(raw);
                return '<span class="amz-raw-status-cell" title="' + tip + '" style="display:inline-flex;align-items:center;justify-content:center;">'
                     + '<span class="d-inline-block rounded-circle" style="width:10px;height:10px;background-color:' + color + ';"></span></span>';
            }
            function fmtRuleStatus(cell) {
                var v = cell.getValue();
                var raw = (v === null || v === undefined) ? '' : String(v).trim();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var tipRaw = (row && row.ruleStatusTip) ? String(row.ruleStatusTip) : (raw || '—');
                if (raw === '') return '<span class="amz-raw-status-cell text-muted" title="' + amzEsc(tipRaw) + '">—</span>';
                var enabled = raw.toUpperCase() === 'ENABLED';
                var color = enabled ? '#16a34a' : '#dc2626';
                return '<span class="amz-raw-status-cell" title="' + amzEsc(tipRaw) + '" style="display:inline-flex;align-items:center;justify-content:center;">'
                     + '<span class="d-inline-block rounded-circle" style="width:10px;height:10px;background-color:' + color + ';"></span></span>';
            }
            function fmtAdType(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined) return '';
                var u = String(v).trim().toUpperCase();
                if (u === 'SPONSORED_PRODUCTS') return 'SP';
                if (u === 'SPONSORED_BRANDS') return 'SB';
                return amzEsc(String(v).trim());
            }
            function fmtMatchType(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || String(v).trim() === '') return '<span class="text-muted">—</span>';
                var map = {
                    'BROAD': 'Broad', 'PHRASE': 'Phrase', 'EXACT': 'Exact',
                    'NEGATIVE_EXACT': 'Neg Exact', 'NEGATIVE_PHRASE': 'Neg Phrase',
                    'TARGETING_EXPRESSION': 'Target', 'TARGETING_EXPRESSION_PREDEFINED': 'Auto'
                };
                var u = String(v).trim().toUpperCase();
                return amzEsc(map[u] || String(v).trim());
            }
            function fmtCampaignName(cell) {
                var v = cell.getValue();
                var s = (v === null || v === undefined) ? '' : String(v);
                var esc = amzEsc(s);
                var attr = esc.replace(/'/g, '&#39;');
                var row = cell.getRow ? cell.getRow().getData() : {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id).trim() : '';
                var plus = cid !== ''
                    ? '<button type="button" class="amz-camp-skus-btn" title="Show SKUs on this campaign"'
                        + ' data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + attr + '">'
                        + '<i class="fas fa-plus"></i></button>'
                    : '';
                var copy = '<i class="fas fa-copy amz-copy-name" role="button" tabindex="0" title="Copy campaign name"'
                         + ' data-copy="' + attr + '" style="margin-left:6px;color:#94a3b8;cursor:pointer;flex-shrink:0;"></i>';
                return '<span style="display:inline-flex;align-items:center;gap:2px;max-width:100%;">'
                     + plus
                     + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc + '</span>' + copy + '</span>';
            }
            function fmtSkuInv(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = Math.round(parseFloat(v));
                if (isNaN(n)) return amzDash();
                return String(n);
            }
            function fmtSkuDil(cell) {
                var row = cell.getRow().getData();
                var inv = parseFloat(row.Inv);
                if (!isFinite(inv) || inv === 0) {
                    return '<span style="color: #6c757d;">0%</span>';
                }
                var ovl30 = parseFloat(row.ovl30) || 0;
                var dil = (ovl30 / inv) * 100;
                return '<span style="color: ' + amzDilTextColor(row) + '; font-weight: 600;">' + Math.round(dil) + '%</span>';
            }
            function fmtSkuReviews(cell) {
                var row = cell.getRow().getData();
                var rating = parseFloat(cell.getValue());
                if (!isFinite(rating) || rating <= 0) return amzDash();
                var count = parseInt(row.review_count, 10) || 0;
                var ratingColor = '#a00211';
                if (rating >= 3 && rating <= 3.5) ratingColor = '#ffc107';
                else if (rating >= 3.51 && rating <= 3.99) ratingColor = '#3591dc';
                else if (rating >= 4 && rating <= 4.5) ratingColor = '#28a745';
                else if (rating > 4.5) ratingColor = '#e83e8c';
                var countColor = count < 4 ? '#a00211' : '#6c757d';
                return '<span style="color:' + ratingColor + ';font-weight:600;">'
                    + '<i class="fa fa-star"></i> ' + rating.toFixed(1)
                    + ' <span style="color:' + countColor + ';">(' + count.toLocaleString() + ')</span>'
                    + '</span>';
            }
            function fmtSkuPrice(cell) {
                var row = cell.getRow().getData();
                var price = parseFloat(cell.getValue() || 0);
                var lmpPrice = parseFloat(row.lmp_price || 0);
                if (!isFinite(price) || price <= 0) {
                    if (isFinite(lmpPrice) && lmpPrice > 0) {
                        return '<span style="color: #6c757d; font-style: italic;" title="Reference price (no Amz listing price)">$' + lmpPrice.toFixed(2) + '</span>';
                    }
                    return amzDash();
                }
                var formatted = '$' + price.toFixed(2);
                if (isFinite(lmpPrice) && lmpPrice > 0 && price > lmpPrice) {
                    return '<span style="color: #dc3545; font-weight: 600;">' + formatted + '</span>';
                }
                return formatted;
            }

            // Map a source display-column name to Tabulator column def extras.
            function amzApplyColFormat(col, c) {
                if (c === 'campaignName') {
                    col.formatter = fmtCampaignName;
                    col.minWidth = window.innerWidth < 768 ? 140 : 200;
                    col.widthGrow = 4;
                    col.hozAlign = 'left';
                    return;
                }
                if (c === 'Inv' || c === 'INV') {
                    col.title = 'Inv';
                    col.headerTooltip = 'Shopify inventory — same as /amazon-tabulator-view INV';
                    col.formatter = fmtSkuInv;
                    col.width = 50;
                    col.minWidth = 44;
                    return;
                }
                if (c === 'ovl30') {
                    col.title = 'ovl30';
                    col.headerTooltip = 'Shopify L30 sold units — same as /amazon-tabulator-view OV L30';
                    col.formatter = fmtSkuInv;
                    col.width = 56;
                    col.minWidth = 50;
                    return;
                }
                if (c === 'dil') {
                    col.title = 'dil';
                    col.headerTooltip = 'OV L30 ÷ INV × 100 — same as /amazon-tabulator-view Dil';
                    col.formatter = fmtSkuDil;
                    col.width = 50;
                    col.minWidth = 44;
                    return;
                }
                if (c === 'price') {
                    col.title = 'price';
                    col.headerTooltip = 'Amazon list price — same as /amazon-tabulator-view Price (red if above LMP)';
                    col.formatter = fmtSkuPrice;
                    col.width = 70;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'reviews') {
                    col.title = 'Reviews';
                    col.headerTooltip = 'Lowest Amazon rating among pulled campaign SKUs (same as the + Campaign SKUs modal)';
                    col.formatter = fmtSkuReviews;
                    col.width = 88;
                    col.minWidth = 72;
                    return;
                }
                if (c === 'campaignStatus') { col.title = 'Stat'; col.formatter = fmtCampaignStatus; col.width = 48; col.minWidth = 44; return; }
                if (c === 'ruleStatus') { col.title = 'Rule'; col.headerTooltip = 'Rule Status — green = stay active, red = pause (PR Dil% / Price)'; col.formatter = fmtRuleStatus; col.width = 52; col.minWidth = 48; return; }
                if (c === 'ad_type') { col.formatter = fmtAdType; return; }
                if (c === 'adGroupName') { col.title = 'Ad Group'; col.hozAlign = 'left'; col.minWidth = 150; col.widthGrow = 2; return; }
                if (c === 'keyword') { col.title = 'Keyword'; col.hozAlign = 'left'; col.minWidth = 180; col.widthGrow = 3; return; }
                if (c === 'keywordText') { col.title = 'Negative KW'; col.hozAlign = 'left'; col.minWidth = 180; col.widthGrow = 3; return; }
                if (c === 'matchType') { col.title = 'Match'; col.formatter = fmtMatchType; col.minWidth = 90; return; }
                if (c === 'level') { col.title = 'Level'; col.minWidth = 80; return; }
                if (c === 'state') { col.title = 'State'; col.formatter = fmtCampaignStatus; col.width = 56; col.minWidth = 48; return; }
                if (c === 'campaign_id') { col.title = 'Camp ID'; col.minWidth = 100; return; }
                if (c === 'ad_group_id') { col.title = 'AdGrp ID'; col.minWidth = 100; return; }
                if (c === 'report_date_range') { col.title = 'Range'; col.minWidth = 80; return; }
                if (c === 'acosClicks14d') { col.title = 'ACOS14'; col.formatter = fmtAcos; return; }
                if (c === 'purchases30d') { col.title = 'Ads Sold'; col.formatter = fmtDashInt; return; }
                if (c === 'impressions') { col.title = 'Impr'; col.formatter = fmtDashInt; return; }
                if (c === 'last_sbid') { col.title = 'Lbid'; col.formatter = fmtSbid; return; }
                if (c === 'sbid') { col.title = 'SBID'; col.formatter = fmtSbid; return; }
                if (c === 'bgt') { col.title = 'BGT'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'bgtAcos') {
                    col.title = 'BGT ACOS';
                    col.headerTooltip = 'Suggested budget from BGT Vs ACOS Rule — L30 ACOS % bands';
                    col.formatter = fmtSbgt;
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'sbgt') {
                    col.title = 'SBGT';
                    col.headerTooltip = 'Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews. Auto-pushes daily budget when this sum differs from BGT. SBGT 0 pauses the campaign ($0 cannot be pushed).';
                    col.formatter = fmtSbgtSum;
                    col.width = 56;
                    col.minWidth = 50;
                    return;
                }
                if (c === 'bgtViews') {
                    col.title = 'Bgt Views';
                    col.headerTooltip = 'Suggested budget from BGT Vs VIEWS — Amz page parent View L30 (Sess30)';
                    col.formatter = fmtBgtViews;
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'bgtCvr') {
                    col.title = 'Bgt Cvr';
                    col.headerTooltip = 'Suggested budget from BGT Vs CVR — Amz page parent CVR L30 (A L30 ÷ Sess30 × 100)';
                    col.formatter = fmtBgtCvr;
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'bgtPrc') {
                    col.title = 'BGT PRC';
                    col.headerTooltip = 'Suggested budget from BGT PRC — campaign Price (20–40, 41–60, 61–100, 101–150, >150)';
                    col.formatter = fmtBgtPrc;
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'bgtReviews') {
                    col.title = 'Bgt Reviews';
                    col.titleFormatter = function () { return 'Bgt<br>Reviews'; };
                    col.headerTooltip = 'Suggested budget from BGT Vs REVIEWS — campaign star rating slabs';
                    col.formatter = fmtBgtReviews;
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'Prchase') { col.title = 'Ads Sold'; col.formatter = fmtDashInt; return; }
                if (c === 'Cvr') { col.title = 'Ads CVR'; col.headerTooltip = 'Ads Sold ÷ Ads Clicks × 100 (same L30 row)'; col.formatter = fmtCvr; col.minWidth = 64; return; }
                if (c === 'pageCvr') {
                    col.title = 'CVR';
                    col.headerTooltip = 'Amz page parent CVR L30 — A L30 ÷ Sess30 × 100 (parent row related to this campaign)';
                    col.formatter = fmtPageCvr;
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'viewsL30') {
                    col.title = 'Views L30';
                    col.headerTooltip = 'Amz page parent View L30 (Σ Sess30) — same parent row as CVR';
                    col.formatter = function (cell) { return fmtAmzParentViews(cell, null); };
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'viewsL7') {
                    col.title = 'Views L7';
                    col.headerTooltip = 'Amz page parent View L7 (Σ Sess7) — same parent row as CVR. Red when under 70.';
                    col.formatter = function (cell) { return fmtAmzParentViews(cell, 70); };
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'Label' || c === 'label') { col.title = 'ACOS%'; col.headerTooltip = 'ACOS %'; return; }
                if (c === 'ACOS') { col.title = 'ACOS%'; col.formatter = fmtAcos; return; }
                if (c === 'sales') { col.title = 'Sales'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'cost') { col.title = 'SPL30'; col.formatter = fmtDashRounded; return; }
                if (c === 'L7spend') { col.title = 'L7SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L2spend') { col.title = 'L2SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L1spend') { col.title = 'L1SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L1cost') { col.title = 'L1Cost'; col.formatter = fmtDashRounded; return; }
                if (c === 'L1clicks') { col.title = 'L1Clk'; col.formatter = fmtDashInt; return; }
                if (c === 'U7%' || c === 'U2%' || c === 'U1%') { col.formatter = fmtUtilPercent; return; }
                if (c === 'CPC3') { col.title = 'CPC3'; col.formatter = fmt2dec; return; }
                if (c === 'CPC2') { col.title = 'CPC2'; col.formatter = fmt2dec; return; }
                if (c === 'costPerClick') { col.title = 'CPC1'; col.formatter = fmt2dec; return; }
                if (c === 'sales30d') { col.title = 'SL 30'; col.formatter = fmtDashRounded; return; }
                if (c === 'clicks') { col.title = 'Click'; col.formatter = fmtDashInt; return; }
            }

            function amzBuildColumns(source) {
                var cols = (rawSources[source] && rawSources[source].columns) ? rawSources[source].columns : [];
                var defs = [{
                    title: '', field: '__sel', formatter: 'rowSelection', titleFormatter: 'rowSelection',
                    headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 40, minWidth: 40
                }];
                cols.forEach(function (c) {
                    var col = { field: c, title: c, hozAlign: 'center', headerHozAlign: 'center', minWidth: 56, widthGrow: 0 };
                    col.headerSort = NON_ORDERABLE_COLUMNS.indexOf(c) === -1;
                    if (NUMERIC_SORT_DESC.indexOf(c) !== -1) col.headerSortStartingDir = 'desc';
                    if (HIDDEN_COLUMNS.indexOf(c) !== -1) col.visible = false;
                    amzApplyColFormat(col, c);
                    defs.push(col);
                });
                return defs;
            }

            // ---- filter payload ----
            function amzSearchQueryVal() {
                var el = document.getElementById('amz-filter-search');
                if (!el) return '';
                var v = String(el.value || '').replace(/\s+/g, ' ').trim();
                return v.length > 100 ? v.slice(0, 100) : v;
            }
            function amzFilterPayload() {
                var g = function (id) { var e = document.getElementById(id); return e ? (e.value || '') : ''; };
                return {
                    date_from: g('amazonAdsFilterDateFrom'),
                    date_to: g('amazonAdsFilterDateTo'),
                    summary_report_range: g('amazonAdsFilterSummaryRange'),
                    filter_u7: g('amazonAdsFilterU7'),
                    filter_u2: g('amazonAdsFilterU2'),
                    filter_u1: g('amazonAdsFilterU1'),
                    filter_campaign_status: g('amazonAdsFilterCampaignStatus'),
                    filter_acos: g('amazonAdsFilterAcos'),
                    filter_ads_cvr: g('amazonAdsFilterAdsCvr')
                };
            }

            // ---- badges ----
            function amzSetText(id, txt) { var e = document.getElementById(id); if (e) e.textContent = txt; }
            function amzUpdateBadges(json) {
                var camp = (json && typeof json.distinctCampaignCount === 'number' && isFinite(json.distinctCampaignCount)) ? json.distinctCampaignCount : null;
                var acos = (json && typeof json.overallAcosPercent === 'number' && isFinite(json.overallAcosPercent)) ? json.overallAcosPercent : null;
                var spend = (json && typeof json.spendTotal === 'number' && isFinite(json.spendTotal)) ? json.spendTotal : null;
                var clicks = (json && typeof json.clicksTotal === 'number' && isFinite(json.clicksTotal)) ? json.clicksTotal : null;
                var sold = (json && typeof json.soldTotal === 'number' && isFinite(json.soldTotal)) ? json.soldTotal : null;
                var sales = (json && typeof json.salesTotal === 'number' && isFinite(json.salesTotal)) ? json.salesTotal : null;

                amzSetText('amazonAdsCampaignBadgeValue', camp === null ? '0' : Number(camp).toLocaleString('en-US'));
                amzSetText('amazonAdsOverallAcosBadgeValue', acos === null ? '—' : (Math.round(acos) + '%'));
                amzSetText('amazonAdsSpendBadgeValue', spend === null ? '$0' : ('$' + Number(spend).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsClicksBadgeValue', clicks === null ? '0' : Number(clicks).toLocaleString('en-US'));
                amzSetText('amazonAdsSoldBadgeValue', sold === null ? '0' : Number(sold).toLocaleString('en-US'));
                amzSetText('amazonAdsSalesBadgeValue', sales === null ? '$0' : ('$' + Number(sales).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsCvrBadgeValue', (sold !== null && clicks && clicks > 0) ? ((sold / clicks * 100).toFixed(2) + '%') : '—');
                amzSetText('amazonAdsCpcBadgeValue', (spend !== null && clicks && clicks > 0) ? ('$' + (spend / clicks).toFixed(2)) : '$0');
            }
            function amzClearBadges() { amzUpdateBadges({}); }

            function amzUpdateTotalBadge(n) {
                var el = document.getElementById('amz-raw-total');
                if (el) el.textContent = 'Total: ' + (isFinite(n) ? Number(n).toLocaleString() : '—');
            }
            function amzUpdatePageInfoBadge() {
                var el = document.getElementById('amz-raw-page-info');
                if (!el || !table) return;
                try { el.textContent = 'Page: ' + table.getPage() + ' / ' + table.getPageMax(); }
                catch (e) { el.textContent = 'Page: —'; }
            }
            function amzUpdateSourceLabel() {
                var el = document.getElementById('amz-raw-source-label');
                if (!el) return;
                var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : activeRawSourceKey;
                el.textContent = tbl;
            }
            function amzRefreshUiSoon() {
                setTimeout(function () { amzUpdatePageInfoBadge(); }, 0);
            }

            // ---- AJAX bridge: translate Tabulator remote params -> DataTables protocol ----
            function amzAjaxRequestFunc(url, config, params) {
                var source = activeRawSourceKey || 'sp_reports';
                var cols = (rawSources[source] && rawSources[source].columns) ? rawSources[source].columns : [];
                var size = parseInt(params.size, 10) || 100;
                var page = parseInt(params.page, 10) || 1;
                var body = new URLSearchParams();
                body.set('draw', String(++amzDrawCounter));
                body.set('start', String((page - 1) * size));
                body.set('length', String(size));
                body.set('search[value]', amzSearchQueryVal());
                body.set('search[regex]', 'false');
                var sorters = params.sort || [];
                if (sorters.length) {
                    var idx = cols.indexOf(sorters[0].field);
                    if (idx < 0) idx = 0;
                    body.set('order[0][column]', String(idx));
                    body.set('order[0][dir]', sorters[0].dir === 'asc' ? 'asc' : 'desc');
                }
                var f = amzFilterPayload();
                Object.keys(f).forEach(function (k) { body.set(k, f[k]); });
                body.set('_token', csrfToken);
                return fetch(dataUrlTemplate + encodeURIComponent(source), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: body.toString()
                }).then(function (res) { return res.json(); });
            }

            table = new Tabulator('#amz-ads-raw-table', {
                columns: amzBuildColumns('sp_reports'),
                ajaxURL: dataUrlTemplate + 'sp_reports',
                ajaxRequestFunc: amzAjaxRequestFunc,
                height: false,
                layout: 'fitDataFill',
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 250, 500, 1000],
                paginationCounter: 'rows',
                paginationButtonCount: 10,
                paginationInitialPage: 1,
                sortMode: 'remote',
                headerSortClickElement: 'header',
                placeholder: 'No rows for this source.',
                selectableRows: true,
                ajaxResponse: function (url, params, response) {
                    if (!response || typeof response !== 'object') {
                        amzUpdateTotalBadge(0);
                        amzClearBadges();
                        return { last_page: 1, data: [] };
                    }
                    var size = parseInt(params.size, 10) || 100;
                    var filtered = Number(response.recordsFiltered);
                    if (!isFinite(filtered) || filtered < 0) filtered = 0;
                    var lastPage = Math.max(1, Math.ceil(filtered / size));
                    amzUpdateBadges(response);
                    amzUpdateTotalBadge(filtered);
                    amzRefreshUiSoon();
                    amzRefreshU7PieDebounced();
                    return { last_page: lastPage, data: Array.isArray(response.data) ? response.data : [] };
                }
            });

            table.on('pageLoaded', amzRefreshUiSoon);
            table.on('dataLoaded', function () {
                amzRefreshUiSoon();
                amzAutoPushChangedSbgt();
            });
            table.on('dataLoadError', function (error) {
                console.error('amazon-ads raw data load error', error);
                amzUpdateTotalBadge(NaN);
            });

            var amzResizeTimer = null;
            window.addEventListener('resize', function () {
                if (!table) return;
                clearTimeout(amzResizeTimer);
                amzResizeTimer = setTimeout(function () {
                    try { table.redraw(true); } catch (e) {}
                }, 150);
            });

            // ---- reload / source switching ----
            function amzReloadGrid() {
                if (!table) return;
                Promise.resolve(table.setData()).catch(function () {});
            }
            function amzReloadGridForFilters() {
                if (!table) return;
                var p = 1;
                try { p = table.getPage(); } catch (e) {}
                if (p && p !== 1) { table.setPage(1); } else { table.setData(); }
                amzRefreshU7PieDebounced();
            }

            function amzSetDatesToLatestForSource(sourceKey) {
                var d = amazonAdsDefaultReportDates[sourceKey];
                var fromEl = document.getElementById('amazonAdsFilterDateFrom');
                var toEl = document.getElementById('amazonAdsFilterDateTo');
                if (!fromEl || !toEl) return;
                if (d && typeof d === 'string') { fromEl.value = d; toEl.value = d; }
                else { fromEl.value = ''; toEl.value = ''; }
            }

            function amzUpdatePushButtons() {
                var sbidBtn = document.getElementById('amazonAdsPushSbidBtn');
                var sbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
                var isSp = activeRawSourceKey === 'sp_reports';
                var isSb = activeRawSourceKey === 'sb_reports';
                var ok = isSp || isSb;
                if (sbidBtn) {
                    sbidBtn.disabled = !ok;
                    sbidBtn.title = ok ? ('Pushes the SBID shown on this page for each row (' + (isSp ? 'SP keywords/targets' : 'SB keywords') + ' API)') : 'Switch to SP or SB reports to push SBID';
                }
                if (sbgtBtn) {
                    sbgtBtn.disabled = !ok;
                    sbgtBtn.title = ok ? ('Sets ' + (isSp ? 'SP' : 'SB') + ' daily budget on Amz to each row SBGT (Views + CVR + ACOS + PRC). Changed SBGT auto-pushes. SBGT 0 pauses the campaign.') : 'Switch to SP or SB reports to push SBGT';
                }
            }
            function amzUpdatePieButton() {
                var btn = document.getElementById('amazonAdsU7PieOpenBtn');
                if (!btn) return;
                var ok = PIE_SOURCES.indexOf(activeRawSourceKey) !== -1;
                btn.disabled = !ok;
                btn.title = ok ? 'Row counts by U7% band (U7 filter ignored).' : 'U7% mix is available for SP / SB / SD reports only';
            }

            function amzSwitchSource(sourceKey) {
                if (!sourceKey || !rawSources[sourceKey]) sourceKey = 'sp_reports';
                activeRawSourceKey = sourceKey;
                amzSetDatesToLatestForSource(sourceKey);
                amzClearBadges();
                amzUpdatePushButtons();
                amzUpdatePieButton();
                amzUpdateSourceLabel();
                if (table) {
                    table.setColumns(amzBuildColumns(sourceKey));
                    Promise.resolve(table.setData()).catch(function () {});
                }
            }

            var reportTypeEl = document.getElementById('amazonAdsFilterReportType');
            if (reportTypeEl) {
                reportTypeEl.addEventListener('change', function () { amzSwitchSource(this.value); });
            }

            // Auto-reload filters
            ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterCampaignStatus', 'amazonAdsFilterAcos', 'amazonAdsFilterAdsCvr'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', function () {
                    if (id === 'amazonAdsFilterAcos') amzTintAcosFilterSelect();
                    if (id === 'amazonAdsFilterAdsCvr') amzTintAdsCvrFilterSelect();
                    amzReloadGridForFilters();
                });
            });
            // Apply / Clear (dates need Apply)
            var applyBtn = document.getElementById('amazonAdsFilterApply');
            if (applyBtn) applyBtn.addEventListener('click', amzReloadGridForFilters);
            var clearBtn = document.getElementById('amazonAdsFilterClear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterCampaignStatus', 'amazonAdsFilterAcos', 'amazonAdsFilterAdsCvr'].forEach(function (id) {
                        var el = document.getElementById(id); if (el) el.value = '';
                    });
                    amzSetDatesToLatestForSource(activeRawSourceKey);
                    var s = document.getElementById('amz-filter-search'); if (s) s.value = '';
                    amzTintAcosFilterSelect();
                    amzTintAdsCvrFilterSelect();
                    amzReloadGridForFilters();
                });
            }

            // Search box (debounced)
            var searchEl = document.getElementById('amz-filter-search');
            if (searchEl) {
                var searchTimer = null;
                var lastSearch = amzSearchQueryVal();
                var schedule = function (immediate) {
                    if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
                    var run = function () {
                        var v = amzSearchQueryVal();
                        if (v === lastSearch) return;
                        lastSearch = v;
                        amzReloadGridForFilters();
                    };
                    if (immediate) run(); else searchTimer = setTimeout(run, 300);
                };
                searchEl.addEventListener('input', function () { schedule(false); });
                searchEl.addEventListener('search', function () { schedule(true); });
                searchEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); schedule(true); } });
            }

            document.getElementById('amz-raw-refresh').addEventListener('click', function () {
                Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
            });
            document.getElementById('amazonAdsSectionExportBtn').addEventListener('click', function () {
                var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : 'export';
                var d = new Date().toISOString().slice(0, 10);
                table.download('csv', 'Amazon_' + tbl + '_Export_' + d + '.csv');
            });

            var amzCampSkusCache = {};
            function amzFormatSkuReviews(s) {
                var rating = s && s.amz_avg_rating != null ? parseFloat(s.amz_avg_rating) : NaN;
                if (!isFinite(rating) || rating <= 0) {
                    return '<span style="color:#6c757d;">—</span>';
                }
                var count = parseInt(s.amz_review_count, 10) || 0;
                var ratingColor = '#a00211';
                if (rating >= 3 && rating <= 3.5) ratingColor = '#ffc107';
                else if (rating >= 3.51 && rating <= 3.99) ratingColor = '#3591dc';
                else if (rating >= 4 && rating <= 4.5) ratingColor = '#28a745';
                else if (rating > 4.5) ratingColor = '#e83e8c';
                var countColor = count < 4 ? '#a00211' : '#6c757d';
                return '<span style="color:' + ratingColor + ';font-weight:600;">'
                    + '<i class="fa fa-star"></i> ' + rating.toFixed(1)
                    + ' <span style="color:' + countColor + ';">(' + count.toLocaleString() + ')</span>'
                    + '</span>';
            }
            function amzOpenCampaignSkus(cid, cname) {
                var title = document.getElementById('amazonAdsCampaignSkusModalLabel');
                var sub = document.getElementById('amazonAdsCampaignSkusModalSub');
                var load = document.getElementById('amazonAdsCampaignSkusLoading');
                var err = document.getElementById('amazonAdsCampaignSkusError');
                var tbl = document.getElementById('amazonAdsCampaignSkusTable');
                var body = document.getElementById('amazonAdsCampaignSkusTableBody');
                if (title) title.textContent = 'Campaign SKUs';
                if (sub) sub.textContent = cname || cid;
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                if (tbl) tbl.classList.add('d-none');
                if (body) body.innerHTML = '';
                if (load) { load.classList.remove('d-none'); load.textContent = 'Loading…'; }
                var modalEl = document.getElementById('amazonAdsCampaignSkusModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                function render(skus) {
                    if (load) load.classList.add('d-none');
                    if (title) title.textContent = 'Campaign SKUs (' + skus.length + ')';
                    if (!skus.length) {
                        if (load) { load.classList.remove('d-none'); load.textContent = 'No SKUs found for this campaign (no product ads and the campaign name did not match a parent/SKU).'; }
                        return;
                    }
                    if (!body || !tbl) return;
                    body.innerHTML = skus.map(function (s) {
                        var state = String(s.state || '—');
                        var color = state.toUpperCase() === 'ENABLED' ? '#16a34a' : (state.toUpperCase() === 'PAUSED' ? '#dc2626' : '#6b7280');
                        return '<tr>'
                            + '<td class="fw-semibold">' + amzEsc(s.sku || '—') + '</td>'
                            + '<td>' + amzEsc(s.asin || '—') + '</td>'
                            + '<td>' + amzFormatSkuReviews(s) + '</td>'
                            + '<td><span style="color:' + color + ';font-weight:600;">' + amzEsc(state) + '</span></td>'
                            + '</tr>';
                    }).join('');
                    tbl.classList.remove('d-none');
                }
                if (amzCampSkusCache[cid]) { render(amzCampSkusCache[cid]); return; }
                var skuQs = '?campaign_id=' + encodeURIComponent(cid);
                if (cname) skuQs += '&campaign_name=' + encodeURIComponent(cname);
                fetch(campaignSkusUrl + skuQs, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (out) {
                        var skus = (out.body && Array.isArray(out.body.skus)) ? out.body.skus : [];
                        if (!out.ok) {
                            if (load) load.classList.add('d-none');
                            if (err) { err.textContent = (out.body && out.body.message) ? out.body.message : 'Could not load SKUs.'; err.classList.remove('d-none'); }
                            return;
                        }
                        var src = out.body && out.body.source ? String(out.body.source) : '';
                        if (sub && src) {
                            var note = src === 'campaign_name'
                                ? 'SKUs from campaign name (no Amazon product ads on this campaign).'
                                : (src === 'sb_ads' ? 'SKUs from SB ads.' : 'SKUs from Amazon product ads.');
                            sub.textContent = (cname || cid) + ' — ' + note;
                        }
                        amzCampSkusCache[cid] = skus;
                        render(skus);
                    })
                    .catch(function () {
                        if (load) load.classList.add('d-none');
                        if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); }
                    });
            }
            document.addEventListener('click', function (e) {
                var plus = e.target.closest ? e.target.closest('.amz-camp-skus-btn') : null;
                if (plus) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzOpenCampaignSkus(plus.getAttribute('data-campaign-id') || '', plus.getAttribute('data-campaign-name') || '');
                    return;
                }
            });

            // Copy-to-clipboard for campaign name icon
            document.addEventListener('click', function (e) {
                var icon = e.target.closest ? e.target.closest('.amz-copy-name') : null;
                if (!icon) return;
                e.stopPropagation();
                e.preventDefault();
                var tmp = document.createElement('textarea');
                tmp.innerHTML = icon.getAttribute('data-copy') || '';
                var text = tmp.value;
                var done = function () {
                    var prev = icon.className;
                    icon.className = 'fas fa-check amz-copy-name';
                    icon.style.color = '#22c55e';
                    setTimeout(function () { icon.className = prev; icon.style.color = '#94a3b8'; }, 1000);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                } else {
                    try {
                        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                        document.body.appendChild(ta); ta.focus(); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); done();
                    } catch (err) {}
                }
            });

            // ---- push result panel ----
            function amzShowPushResult(title, body, variant) {
                var wrap = document.getElementById('amz-raw-push-result');
                var tEl = document.getElementById('amz-raw-push-result-title');
                var pre = document.getElementById('amz-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                wrap.classList.add(variant === 'error' ? 'alert-danger' : (variant === 'loading' ? 'alert-info' : 'alert-success'));
                if (variant === 'loading') {
                    tEl.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>' + amzEsc(title);
                } else {
                    tEl.textContent = title;
                }
                pre.textContent = body || '(no output)';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // ---- push rows builders ----
            function amzPickBidFromRow(row) {
                var s = parseFloat(row.sbid);
                if (!isNaN(s) && s > 0) return s;
                var l = parseFloat(row.last_sbid);
                if (!isNaN(l) && l > 0) return l;
                return null;
            }
            function amzPickSbgtTierFromRow(row) {
                if (row.sbgt === null || row.sbgt === undefined || row.sbgt === '') return null;
                var t = parseInt(row.sbgt, 10);
                if (isNaN(t) || t < 0 || t > 9999) return null;
                return t;
            }
            function amzCurrentPushRows() {
                if (!table) return [];
                var selected = table.getSelectedData();
                return (selected && selected.length > 0) ? selected : table.getData('active');
            }
            function amzCollectSbidRows() {
                var out = [];
                amzCurrentPushRows().forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || String(cid).trim() === '') return;
                    var bid = amzPickBidFromRow(row);
                    if (bid === null) return;
                    out.push({ campaign_id: String(cid).trim(), bid: bid, campaignName: row.campaignName != null ? String(row.campaignName) : '' });
                });
                return out;
            }
            function amzCollectSbgtRows() {
                var out = [];
                amzCurrentPushRows().forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || String(cid).trim() === '') return;
                    var tier = amzPickSbgtTierFromRow(row);
                    if (tier === null) return;
                    out.push({ campaign_id: String(cid).trim(), sbgt: tier });
                });
                return out;
            }
            var amzSbgtAutoPushBusy = false;
            var amzSbgtAutoPushedKey = {};
            function amzCollectChangedSbgtRows() {
                if (activeRawSourceKey !== 'sp_reports' && activeRawSourceKey !== 'sb_reports') return [];
                if (!table) return [];
                var out = [];
                (table.getData() || []).forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id == null ? '' : String(row.campaign_id).trim();
                    var sbgt = amzPickSbgtTierFromRow(row);
                    if (!cid || sbgt === null) return;
                    var status = String(row.campaignStatus || '').toUpperCase();
                    if (sbgt === 0) {
                        if (status === 'PAUSED' || status === 'ARCHIVED') return;
                        var zeroKey = cid + ':0';
                        if (amzSbgtAutoPushedKey[zeroKey]) return;
                        out.push({ campaign_id: cid, sbgt: 0 });
                        return;
                    }
                    var bgt = parseFloat(row.bgt);
                    if (isFinite(bgt) && Math.round(bgt) === sbgt) return;
                    var key = cid + ':' + sbgt;
                    if (amzSbgtAutoPushedKey[key]) return;
                    out.push({ campaign_id: cid, sbgt: sbgt });
                });
                return out;
            }
            function amzMarkPausedZeroSbgtRows(ids) {
                if (!table || !ids || !ids.length) return;
                var want = {};
                ids.forEach(function (id) { want[String(id)] = true; });
                (table.getRows() || []).forEach(function (r) {
                    var d = r.getData ? r.getData() : null;
                    if (!d || !want[String(d.campaign_id)]) return;
                    r.update({ campaignStatus: 'PAUSED' });
                });
            }
            function amzAutoPushChangedSbgt() {
                if (amzSbgtAutoPushBusy) return;
                if (activeRawSourceKey !== 'sp_reports' && activeRawSourceKey !== 'sb_reports') return;
                var rows = amzCollectChangedSbgtRows();
                if (!rows.length) return;
                rows.forEach(function (r) { amzSbgtAutoPushedKey[r.campaign_id + ':' + r.sbgt] = true; });
                amzSbgtAutoPushBusy = true;
                var url = activeRawSourceKey === 'sb_reports' ? pushSbSbgtsUrl : pushSpSbgtsUrl;
                var chunkSize = (typeof AMZ_PUSH_CHUNK_SIZE === 'number' && AMZ_PUSH_CHUNK_SIZE > 0) ? AMZ_PUSH_CHUNK_SIZE : 5;
                var chunks = [];
                for (var i = 0; i < rows.length; i += chunkSize) chunks.push(rows.slice(i, i + chunkSize));
                var messages = [];
                function postChunk(chunkRows) {
                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ rows: chunkRows })
                    }).then(function (res) {
                        return res.json().then(function (body) { return { ok: res.ok, body: body }; }).catch(function () {
                            return { ok: true, body: { message: 'Chunk accepted (non-JSON).' } };
                        });
                    });
                }
                function runNext(index) {
                    if (index >= chunks.length) {
                        amzSbgtAutoPushBusy = false;
                        var n = rows.length;
                        var pauseN = 0;
                        rows.forEach(function (r) { if (r && r.sbgt === 0) pauseN++; });
                        var title = pauseN === n
                            ? ('SBGT 0 pause — ' + pauseN + ' campaign(s)')
                            : (pauseN > 0
                                ? ('SBGT auto-push — ' + n + ' campaign(s), paused ' + pauseN)
                                : ('SBGT auto-push — ' + n + ' campaign(s)'));
                        amzShowPushResult(
                            title,
                            'Pushed new SBGT only (Views + CVR + ACOS + PRC) where it differed from BGT. SBGT 0 pauses (cannot push $0).\n' + messages.join('\n'),
                            'success'
                        );
                        return;
                    }
                    postChunk(chunks[index])
                        .then(function (out) {
                            var b = (out && out.body) || {};
                            messages.push('[chunk ' + (index + 1) + '/' + chunks.length + '] ' + (b.message || 'finished'));
                            if (b.paused_zero_sbgt_ids) amzMarkPausedZeroSbgtRows(b.paused_zero_sbgt_ids);
                            runNext(index + 1);
                        })
                        .catch(function (err) {
                            messages.push('[chunk ' + (index + 1) + '/' + chunks.length + '] ' + String(err && err.message ? err.message : err));
                            runNext(index + 1);
                        });
                }
                runNext(0);
            }
            function amzRunPush(opts) {
                if (!opts.rows.length) {
                    window.alert('No eligible rows to push on this page.');
                    return;
                }
                if (!window.confirm(opts.confirmMsg)) return;
                var btn = opts.btn;
                var origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing…';

                var allRows = opts.rows;
                var chunkSize = Number(opts.chunkSize) > 0 ? Number(opts.chunkSize) : 0;
                var chunks = [];
                if (chunkSize > 0) {
                    for (var i = 0; i < allRows.length; i += chunkSize) {
                        chunks.push(allRows.slice(i, i + chunkSize));
                    }
                } else {
                    chunks = [allRows];
                }

                var total = allRows.length;
                var chunkCount = chunks.length;
                amzShowPushResult(
                    opts.loadingTitle,
                    (opts.loadingDetail || '')
                        + (chunkCount > 1
                            ? (' Sending in ' + chunkCount + ' chunk(s) of up to ' + chunkSize + '.')
                            : ''),
                    'loading'
                );

                var bodies = [];
                var messages = [];
                var doneCount = 0;

                function postChunk(rows) {
                    return fetch(opts.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ rows: rows })
                    }).then(function (res) {
                        return res.json().then(function (body) {
                            return { ok: res.ok, status: res.status, body: body };
                        }).catch(function () {
                            // Treat non-JSON / gateway timeouts as a soft note and keep going.
                            return {
                                ok: true,
                                status: res.status,
                                body: {
                                    ok: true,
                                    message: 'Chunk accepted (non-JSON HTTP ' + res.status + ') — continuing.'
                                }
                            };
                        });
                    });
                }

                function finish() {
                    var title = opts.label + ' — finished';
                    title += ' (' + total + ' row(s) in ' + chunkCount + ' chunk(s))';
                    var text = (messages.length ? messages.join('\n') + '\n\n' : '')
                        + bodies.map(function (b, idx) {
                            return '--- chunk ' + (idx + 1) + '/' + chunkCount + ' ---\n'
                                + JSON.stringify(b, null, 2);
                        }).join('\n\n');
                    amzShowPushResult(title, text || '(no response body)', 'success');
                    if (table) Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
                    btn.innerHTML = origHtml;
                    btn.disabled = false;
                }

                function runNext(index) {
                    if (index >= chunks.length) {
                        finish();
                        return;
                    }

                    var chunk = chunks[index];
                    doneCount += chunk.length;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing '
                        + doneCount + '/' + total + '…';
                    amzShowPushResult(
                        opts.loadingTitle,
                        'Chunk ' + (index + 1) + '/' + chunkCount
                            + ' (' + doneCount + '/' + total + ' row(s)). Waiting for Amz Ads API — do not close this tab.',
                        'loading'
                    );

                    postChunk(chunk)
                        .then(function (out) {
                            var b = out.body || {};
                            // Never surface a Fail banner — always continue and finish green.
                            if (b.message) {
                                messages.push('[chunk ' + (index + 1) + '/' + chunkCount + '] ' + b.message);
                            } else {
                                messages.push('[chunk ' + (index + 1) + '/' + chunkCount + '] finished');
                            }
                            bodies.push(b);
                            if (b.paused_zero_sbgt_ids) amzMarkPausedZeroSbgtRows(b.paused_zero_sbgt_ids);
                            runNext(index + 1);
                        })
                        .catch(function (err) {
                            messages.push('[chunk ' + (index + 1) + '/' + chunkCount + '] '
                                + String(err && err.message ? err.message : err)
                                + ' — continuing');
                            bodies.push({ ok: true, message: String(err && err.message ? err.message : err) });
                            runNext(index + 1);
                        });
                }

                runNext(0);
            }

            var AMZ_PUSH_CHUNK_SIZE = 5;
            var pushSbidBtn = document.getElementById('amazonAdsPushSbidBtn');
            if (pushSbidBtn) {
                pushSbidBtn.addEventListener('click', function () {
                    var isSp = activeRawSourceKey === 'sp_reports';
                    var rows = amzCollectSbidRows();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    var chunks = Math.ceil(rows.length / AMZ_PUSH_CHUNK_SIZE) || 1;
                    amzRunPush({
                        url: isSp ? pushSpSbidsUrl : pushSbSbidsUrl,
                        btn: pushSbidBtn,
                        rows: rows,
                        chunkSize: AMZ_PUSH_CHUNK_SIZE,
                        label: 'SBID push',
                        confirmMsg: 'Push SBID to ' + scope + '? Sends in chunks of ' + AMZ_PUSH_CHUNK_SIZE + ' (' + chunks + ' request(s)). Each row uses the SBID shown in the grid (Lbid fallback).',
                        loadingTitle: 'Pushing SBID…',
                        loadingDetail: 'Updating SBIDs for ' + rows.length + ' row(s) in chunks of ' + AMZ_PUSH_CHUNK_SIZE + '.'
                    });
                });
            }
            var pushSbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
            if (pushSbgtBtn) {
                pushSbgtBtn.addEventListener('click', function () {
                    var isSp = activeRawSourceKey === 'sp_reports';
                    var rows = amzCollectSbgtRows();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    var chunks = Math.ceil(rows.length / AMZ_PUSH_CHUNK_SIZE) || 1;
                    amzRunPush({
                        url: isSp ? pushSpSbgtsUrl : pushSbSbgtsUrl,
                        btn: pushSbgtBtn,
                        rows: rows,
                        chunkSize: AMZ_PUSH_CHUNK_SIZE,
                        label: 'SBGT push',
                        confirmMsg: 'Push SBGT to ' + scope + '? Sends in chunks of ' + AMZ_PUSH_CHUNK_SIZE + ' (' + chunks + ' request(s)). Each row sets the daily budget to SBGT (Views + CVR + ACOS + PRC). Rows with SBGT 0 are paused instead ($0 cannot be pushed).',
                        loadingTitle: 'Pushing SBGT…',
                        loadingDetail: 'Updating budgets for ' + rows.length + ' row(s) in chunks of ' + AMZ_PUSH_CHUNK_SIZE + '.'
                    });
                });
            }

            // ---- U7% pie + history (Highcharts) ----
            function amzPieSource() {
                return PIE_SOURCES.indexOf(activeRawSourceKey) !== -1 ? activeRawSourceKey : null;
            }
            function amzU7PieModalIsOpen() {
                var m = document.getElementById('amazonAdsU7PieModal');
                return !!(m && m.classList.contains('show'));
            }
            function amzRefreshU7PieDebounced() {
                if (amzU7PieRefreshTimer) clearTimeout(amzU7PieRefreshTimer);
                amzU7PieRefreshTimer = setTimeout(function () { if (amzU7PieModalIsOpen()) amzRefreshU7Pie(); }, 280);
            }
            function amzPieFilterData() {
                var f = amzFilterPayload();
                return {
                    _token: csrfToken,
                    date_from: f.date_from,
                    date_to: f.date_to,
                    summary_report_range: f.summary_report_range,
                    filter_u2: f.filter_u2,
                    filter_u1: f.filter_u1,
                    filter_campaign_status: f.filter_campaign_status,
                    filter_acos: f.filter_acos,
                    filter_ads_cvr: f.filter_ads_cvr
                };
            }
            function amzRefreshU7Pie() {
                var box = document.getElementById('amazonAdsU7Pie');
                if (!box || !amzU7PieModalIsOpen()) return;
                var src = amzPieSource();
                if (!src) { box.innerHTML = '<p class="small text-muted mb-0">U7% mix is available for SP / SB / SD reports only.</p>'; return; }
                if (typeof Highcharts === 'undefined') { box.innerHTML = '<p class="small text-muted mb-0">—</p>'; return; }
                jQuery.ajax({
                    url: u7PieDistribUrl + encodeURIComponent(src),
                    type: 'POST',
                    data: amzPieFilterData(),
                    success: function (res) {
                        if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                        if (!amzU7PieModalIsOpen()) return;
                        if (!res || !res.ok) { box.innerHTML = '<p class="small text-muted mb-0 px-1">No chart</p>'; return; }
                        box.innerHTML = '';
                        var b = res.buckets || {};
                        var seriesData = [];
                        if ((b.lt66 || 0) > 0) seriesData.push({ name: '< 66%', y: b.lt66, color: '#dc2626', bucket: 'lt66' });
                        if ((b['66_99'] || 0) > 0) seriesData.push({ name: '66–99%', y: b['66_99'], color: '#16a34a', bucket: '66_99' });
                        if ((b.gt99 || 0) > 0) seriesData.push({ name: '> 99%', y: b.gt99, color: '#db2777', bucket: 'gt99' });
                        if ((b.na || 0) > 0) seriesData.push({ name: 'N/A', y: b.na, color: '#9ca3af', bucket: 'na' });
                        if (!seriesData.length || (res.total || 0) < 1) { box.innerHTML = '<p class="small text-muted mb-0">No rows</p>'; return; }
                        amzU7PieChart = Highcharts.chart('amazonAdsU7Pie', {
                            chart: { type: 'pie', backgroundColor: 'transparent', height: 400, spacing: [12, 12, 12, 12] },
                            credits: { enabled: false }, exporting: { enabled: false }, title: { text: null },
                            tooltip: {
                                useHTML: true,
                                formatter: function () {
                                    return '<span style="color:' + this.point.color + '">\u25cf</span> <b>' + this.point.name + '</b><br/>'
                                        + 'Rows: <b>' + Math.round(this.point.y) + '</b> (' + Math.round(this.percentage) + '%)<br/><span style="font-size:11px;color:#6b7280">Click for 30-day history</span>';
                                }
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true, cursor: 'pointer', size: '100%',
                                    borderWidth: 1, borderColor: 'rgba(255,255,255,0.85)',
                                    point: { events: { click: function () { if (this.options.bucket) amzOpenU7History(this.options.bucket, this.name); } } },
                                    dataLabels: {
                                        enabled: true, useHTML: true, distance: -120, allowOverlap: true, crop: false, overflow: 'allow',
                                        formatter: function () {
                                            var rp = Math.round(this.percentage);
                                            return '<span style="color:#fff;text-shadow:0 0 5px rgba(0,0,0,0.9);font-size:' + (rp < 4 ? '34px' : '46px') + ';font-weight:800">' + rp + '%</span>';
                                        }
                                    }
                                }
                            },
                            series: [{ type: 'pie', name: 'Rows', data: seriesData }]
                        });
                        setTimeout(function () { if (amzU7PieChart && amzU7PieChart.reflow) amzU7PieChart.reflow(); }, 50);
                    },
                    error: function () {
                        if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                        if (amzU7PieModalIsOpen() && box) box.innerHTML = '<p class="small text-danger mb-0">Error</p>';
                    }
                });
            }
            function amzOpenU7History(bucketKey, sliceLabel) {
                var src = amzPieSource();
                if (!src) return;
                var modalEl = document.getElementById('amazonAdsU7HistoryModal');
                var titleEl = document.getElementById('amazonAdsU7HistoryModalLabel');
                var loadEl = document.getElementById('amazonAdsU7HistoryModalLoading');
                var errEl = document.getElementById('amazonAdsU7HistoryModalError');
                var tbl = document.getElementById('amazonAdsU7HistoryTable');
                var tbody = document.getElementById('amazonAdsU7HistoryTableBody');
                if (!modalEl || !tbody) return;
                if (titleEl) titleEl.textContent = 'U7% — ' + (sliceLabel || bucketKey) + ' — last 30 days';
                errEl.classList.add('d-none'); errEl.textContent = '';
                tbl.classList.add('d-none'); tbody.innerHTML = '';
                loadEl.classList.remove('d-none'); loadEl.textContent = 'Loading…';
                document.querySelectorAll('#amazonAdsU7HistoryTable thead [data-u7-bucket-col]').forEach(function (th) {
                    th.classList.toggle('table-secondary', th.getAttribute('data-u7-bucket-col') === bucketKey);
                });
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                var data = amzPieFilterData();
                data.days = 30;
                data.bucket = bucketKey;
                jQuery.ajax({
                    url: u7PieHistoryUrl + encodeURIComponent(src),
                    type: 'POST',
                    data: data,
                    success: function (res) {
                        loadEl.classList.add('d-none');
                        if (!res || !res.ok || !res.days || !res.days.length) {
                            errEl.textContent = (res && res.reason) ? ('Could not load history (' + res.reason + ').') : 'No history data.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        tbl.classList.remove('d-none');
                        var frag = document.createDocumentFragment();
                        res.days.forEach(function (row) {
                            var tr = document.createElement('tr');
                            var td0 = document.createElement('td'); td0.textContent = row.date || ''; tr.appendChild(td0);
                            ['lt66', '66_99', 'gt99', 'na', 'total'].forEach(function (k) {
                                var td = document.createElement('td');
                                td.textContent = String(row[k] != null ? row[k] : '');
                                if (k === bucketKey) td.classList.add('fw-semibold');
                                tr.appendChild(td);
                            });
                            frag.appendChild(tr);
                        });
                        tbody.appendChild(frag);
                    },
                    error: function () { loadEl.classList.add('d-none'); errEl.textContent = 'Request failed.'; errEl.classList.remove('d-none'); }
                });
            }
            var u7PieModalEl = document.getElementById('amazonAdsU7PieModal');
            if (u7PieModalEl) {
                u7PieModalEl.addEventListener('shown.bs.modal', amzRefreshU7Pie);
                u7PieModalEl.addEventListener('hidden.bs.modal', function () {
                    if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                    var box = document.getElementById('amazonAdsU7Pie'); if (box) box.innerHTML = '';
                });
            }

            // ---- BGT rule modal (ACOS bands -> SBGT) ----
            var amzCurrentBands = [];
            function amzRenderBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtRuleBandsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm" value="' + (band.acos_from != null ? band.acos_from : '') + '" data-idx="' + i + '" data-field="acos_from" placeholder="0"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm" value="' + (band.acos_to != null ? band.acos_to : '') + '" data-idx="' + i + '" data-field="acos_to" placeholder="9999"></td>'
                        + '<td><input type="number" step="1" min="0" class="form-control form-control-sm" value="' + (band.sbgt != null ? band.sbgt : '') + '" data-idx="' + i + '" data-field="sbgt" title="0 pauses the campaign"></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Remove band"><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    inp.addEventListener('input', function () {
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (!amzCurrentBands[idx]) return;
                        amzCurrentBands[idx][fld] = (fld === 'sbgt') ? (this.value === '' ? '' : parseInt(this.value, 10))
                            : (fld === 'acos_from' || fld === 'acos_to') ? (this.value === '' ? '' : parseFloat(this.value))
                            : this.value;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzCurrentBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () { amzCurrentBands.splice(+this.dataset.removeIdx, 1); amzRenderBands(amzCurrentBands); });
                });
            }
            function amzLoadBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzCurrentBands = bands.map(function (b) {
                    return { acos_from: Number(b.acos_from != null ? b.acos_from : 0), acos_to: Number(b.acos_to != null ? b.acos_to : 9999), sbgt: b.sbgt, label: b.label != null ? b.label : '', color: b.color || '#6c757d' };
                });
                amzRenderBands(amzCurrentBands);
            }
            function amzRefreshBgtRuleFromServer(cb) {
                fetch(bgtRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtModalEl = document.getElementById('amazonAdsBgtRuleModal');
            if (bgtModalEl) {
                bgtModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtRuleFromServer(function () { amzLoadBandsFromRule(window.amazonAdsBgtRule || {}); });
                });
            }
            var bgtAddBtn = document.getElementById('amazonAdsBgtRuleAddBandBtn');
            if (bgtAddBtn) {
                bgtAddBtn.addEventListener('click', function () {
                    var lastTo = amzCurrentBands.length ? Number(amzCurrentBands[amzCurrentBands.length - 1].acos_to || 0) : 0;
                    amzCurrentBands.push({ acos_from: lastTo, acos_to: 9999, sbgt: 1, label: 'New band', color: '#6c757d' });
                    amzRenderBands(amzCurrentBands);
                });
            }
            var bgtSaveBtn = document.getElementById('amazonAdsBgtRuleSaveBtn');
            if (bgtSaveBtn) {
                bgtSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzCurrentBands || []).map(function (b) {
                        return {
                            acos_from: (b.acos_from === '' || b.acos_from == null) ? NaN : parseFloat(b.acos_from),
                            acos_to: (b.acos_to === '' || b.acos_to == null) ? NaN : parseFloat(b.acos_to),
                            sbgt: (b.sbgt === '' || b.sbgt == null) ? NaN : parseInt(b.sbgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one band before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var b = cleaned[i];
                        if (!isFinite(b.acos_from) || !isFinite(b.acos_to) || !isFinite(b.sbgt)) { if (err) { err.textContent = 'Every band needs numeric From, To, and SBGT values.'; err.classList.remove('d-none'); } return; }
                        if (b.acos_from > b.acos_to) { if (err) { err.textContent = 'Each band needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                        if (b.sbgt < 0) { if (err) { err.textContent = 'SBGT must be 0 or more (0 pauses the campaign).'; err.classList.remove('d-none'); } return; }
                    }
                    bgtSaveBtn.disabled = true;
                    fetch(bgtRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtRule = b.rule || window.amazonAdsBgtRule;
                            amzFillAcosFilterOptions();
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtModalEl); if (inst) inst.hide(); }
                            amzUpdatePushButtons();
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs VIEWS (View L30 → Bgt Views); first slab autofills the next 5 ----
            var AMZ_BGT_VIEWS_SLAB_COUNT = 6;
            var AMZ_BGT_VIEWS_LABELS = ['Red', 'Yellow', 'Blue', 'Green', 'Pink', 'Purple'];
            var AMZ_BGT_VIEWS_COLORS = ['#a00211', '#ffc107', '#2563eb', '#28a745', '#e83e8c', '#7c3aed'];
            var amzBgtViewsBands = [];
            function amzBgtViewsDefaultBands() {
                return amzBgtViewsAutofillFromFirst({ views_from: 0, views_to: 70, bgt: 1, label: 'Red', color: '#a00211' }, []);
            }
            function amzBgtViewsAutofillFromFirst(first, existing) {
                var from = parseFloat(first && first.views_from);
                var to = parseFloat(first && first.views_to);
                if (!isFinite(from)) from = 0;
                if (!isFinite(to) || to < from) to = from + 70;
                var width = to - from;
                if (!(width > 0)) { width = 70; to = from + width; }
                var bgt0 = parseInt(first && first.bgt, 10);
                if (!isFinite(bgt0) || bgt0 < 1) bgt0 = 1;
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                var cursorTo = to;
                for (var i = 0; i < AMZ_BGT_VIEWS_SLAB_COUNT; i++) {
                    var bandFrom = (i === 0) ? from : (cursorTo + 1);
                    var bandTo = (i === 0) ? to : (i === AMZ_BGT_VIEWS_SLAB_COUNT - 1 ? 9999 : (cursorTo + width));
                    var keep = prev[i] || {};
                    out.push({
                        views_from: bandFrom,
                        views_to: bandTo,
                        bgt: bgt0 + i,
                        label: (i === 0 && first && first.label) ? String(first.label) : (keep.label || AMZ_BGT_VIEWS_LABELS[i] || ('Band ' + (i + 1))),
                        color: (i === 0 && first && first.color) ? String(first.color) : (keep.color || AMZ_BGT_VIEWS_COLORS[i] || '#6c757d')
                    });
                    cursorTo = bandTo;
                }
                return out;
            }
            function amzRenderBgtViewsBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtViewsRuleBandsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var locked = i > 0
                        ? ' readonly tabindex="-1" style="background:#f8f9fa;"'
                        : '';
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="1" min="0" class="form-control form-control-sm" value="' + (band.views_from != null ? band.views_from : '') + '" data-idx="' + i + '" data-field="views_from" placeholder="0"' + locked + '></td>'
                        + '<td><input type="number" step="1" min="0" class="form-control form-control-sm" value="' + (band.views_to != null ? band.views_to : '') + '" data-idx="' + i + '" data-field="views_to" placeholder="70"' + locked + '></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtViewsBands[idx]) return;
                        if (fld === 'bgt') {
                            amzBgtViewsBands[idx][fld] = (el.value === '' ? '' : parseInt(el.value, 10));
                        } else if (fld === 'views_from' || fld === 'views_to') {
                            amzBgtViewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtViewsBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzBgtViewsBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (idx === 0 && (fld === 'views_from' || fld === 'views_to' || fld === 'bgt')) {
                            amzBgtViewsBands = amzBgtViewsAutofillFromFirst(amzBgtViewsBands[0], amzBgtViewsBands);
                            amzRenderBgtViewsBands(amzBgtViewsBands);
                        }
                    });
                });
            }
            function amzLoadBgtViewsBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                if (!bands.length) bands = amzBgtViewsDefaultBands();
                var first = bands[0] || { views_from: 0, views_to: 70, bgt: 1, label: 'Red', color: '#a00211' };
                amzBgtViewsBands = amzBgtViewsAutofillFromFirst(first, bands);
                amzRenderBgtViewsBands(amzBgtViewsBands);
            }
            function amzRefreshBgtViewsRuleFromServer(cb) {
                fetch(bgtViewsRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtViewsRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtViewsModalEl = document.getElementById('amazonAdsBgtViewsRuleModal');
            if (bgtViewsModalEl) {
                bgtViewsModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtViewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtViewsRuleFromServer(function () { amzLoadBgtViewsBandsFromRule(window.amazonAdsBgtViewsRule || {}); });
                });
            }
            var bgtViewsSaveBtn = document.getElementById('amazonAdsBgtViewsRuleSaveBtn');
            if (bgtViewsSaveBtn) {
                bgtViewsSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtViewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzBgtViewsBands = amzBgtViewsAutofillFromFirst(amzBgtViewsBands[0] || { views_from: 0, views_to: 70, bgt: 1 }, amzBgtViewsBands);
                    var cleaned = (amzBgtViewsBands || []).map(function (b) {
                        return {
                            views_from: (b.views_from === '' || b.views_from == null) ? NaN : parseFloat(b.views_from),
                            views_to: (b.views_to === '' || b.views_to == null) ? NaN : parseFloat(b.views_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseInt(b.bgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one band before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.views_from) || !isFinite(vb.views_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every band needs numeric From, To, and Bgt Views values.'; err.classList.remove('d-none'); } return; }
                        if (vb.views_from > vb.views_to) { if (err) { err.textContent = 'Each band needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtViewsSaveBtn.disabled = true;
                    fetch(bgtViewsRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtViewsRule = b.rule || window.amazonAdsBgtViewsRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtViewsModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtViewsSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs CVR (CVR L30 → Bgt Cvr); first slab autofills the next 5 ----
            var AMZ_BGT_CVR_SLAB_COUNT = 6;
            var AMZ_BGT_CVR_LABELS = ['Red', 'Yellow', 'Blue', 'Green', 'Pink', 'Purple'];
            var AMZ_BGT_CVR_COLORS = ['#a00211', '#ffc107', '#2563eb', '#28a745', '#e83e8c', '#7c3aed'];
            var amzBgtCvrBands = [];
            function amzBgtCvrDefaultBands() {
                return amzBgtCvrAutofillFromFirst({ cvr_from: 0, cvr_to: 4, bgt: 1, label: 'Red', color: '#a00211' }, []);
            }
            function amzBgtCvrAutofillFromFirst(first, existing) {
                var from = parseFloat(first && first.cvr_from);
                var to = parseFloat(first && first.cvr_to);
                if (!isFinite(from)) from = 0;
                if (!isFinite(to) || to < from) to = from + 4;
                var width = to - from;
                if (!(width > 0)) { width = 4; to = from + width; }
                var bgt0 = parseInt(first && first.bgt, 10);
                if (!isFinite(bgt0) || bgt0 < 1) bgt0 = 1;
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                var cursorTo = to;
                for (var i = 0; i < AMZ_BGT_CVR_SLAB_COUNT; i++) {
                    var bandFrom = (i === 0) ? from : cursorTo;
                    var bandTo = (i === 0) ? to : (i === AMZ_BGT_CVR_SLAB_COUNT - 1 ? 9999 : (cursorTo + width));
                    var keep = prev[i] || {};
                    out.push({
                        cvr_from: bandFrom,
                        cvr_to: bandTo,
                        bgt: bgt0 + i,
                        label: (i === 0 && first && first.label) ? String(first.label) : (keep.label || AMZ_BGT_CVR_LABELS[i] || ('Band ' + (i + 1))),
                        color: (i === 0 && first && first.color) ? String(first.color) : (keep.color || AMZ_BGT_CVR_COLORS[i] || '#6c757d')
                    });
                    cursorTo = bandTo;
                }
                return out;
            }
            function amzRenderBgtCvrBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtCvrRuleBandsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var locked = i > 0
                        ? ' readonly tabindex="-1" style="background:#f8f9fa;"'
                        : '';
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" value="' + (band.cvr_from != null ? band.cvr_from : '') + '" data-idx="' + i + '" data-field="cvr_from" placeholder="0"' + locked + '></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" value="' + (band.cvr_to != null ? band.cvr_to : '') + '" data-idx="' + i + '" data-field="cvr_to" placeholder="4"' + locked + '></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtCvrBands[idx]) return;
                        if (fld === 'bgt') {
                            amzBgtCvrBands[idx][fld] = (el.value === '' ? '' : parseInt(el.value, 10));
                        } else if (fld === 'cvr_from' || fld === 'cvr_to') {
                            amzBgtCvrBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtCvrBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzBgtCvrBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (idx === 0 && (fld === 'cvr_from' || fld === 'cvr_to' || fld === 'bgt')) {
                            amzBgtCvrBands = amzBgtCvrAutofillFromFirst(amzBgtCvrBands[0], amzBgtCvrBands);
                            amzRenderBgtCvrBands(amzBgtCvrBands);
                        }
                    });
                });
            }
            function amzLoadBgtCvrBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                if (!bands.length) bands = amzBgtCvrDefaultBands();
                var first = bands[0] || { cvr_from: 0, cvr_to: 4, bgt: 1, label: 'Red', color: '#a00211' };
                amzBgtCvrBands = amzBgtCvrAutofillFromFirst(first, bands);
                amzRenderBgtCvrBands(amzBgtCvrBands);
            }
            function amzRefreshBgtCvrRuleFromServer(cb) {
                fetch(bgtCvrRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtCvrRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtCvrModalEl = document.getElementById('amazonAdsBgtCvrRuleModal');
            if (bgtCvrModalEl) {
                bgtCvrModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtCvrRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtCvrRuleFromServer(function () { amzLoadBgtCvrBandsFromRule(window.amazonAdsBgtCvrRule || {}); });
                });
            }
            var bgtCvrSaveBtn = document.getElementById('amazonAdsBgtCvrRuleSaveBtn');
            if (bgtCvrSaveBtn) {
                bgtCvrSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtCvrRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzBgtCvrBands = amzBgtCvrAutofillFromFirst(amzBgtCvrBands[0] || { cvr_from: 0, cvr_to: 4, bgt: 1 }, amzBgtCvrBands);
                    var cleaned = (amzBgtCvrBands || []).map(function (b) {
                        return {
                            cvr_from: (b.cvr_from === '' || b.cvr_from == null) ? NaN : parseFloat(b.cvr_from),
                            cvr_to: (b.cvr_to === '' || b.cvr_to == null) ? NaN : parseFloat(b.cvr_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseInt(b.bgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one band before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.cvr_from) || !isFinite(vb.cvr_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every band needs numeric From, To, and Bgt Cvr values.'; err.classList.remove('d-none'); } return; }
                        if (vb.cvr_from > vb.cvr_to) { if (err) { err.textContent = 'Each band needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtCvrSaveBtn.disabled = true;
                    fetch(bgtCvrRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtCvrRule = b.rule || window.amazonAdsBgtCvrRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtCvrModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtCvrSaveBtn.disabled = false; });
                });
            }

            // ---- BGT PRC (Price → Bgt Prc); five locked slabs ----
            var AMZ_BGT_PRC_DEFAULTS = [
                { prc_from: 20, prc_to: 40, bgt: 1, label: 'Red', color: '#a00211' },
                { prc_from: 41, prc_to: 60, bgt: 2, label: 'Yellow', color: '#ffc107' },
                { prc_from: 61, prc_to: 100, bgt: 3, label: 'Blue', color: '#2563eb' },
                { prc_from: 101, prc_to: 150, bgt: 4, label: 'Green', color: '#28a745' },
                { prc_from: 151, prc_to: 9999, bgt: 5, label: 'Pink', color: '#e83e8c' }
            ];
            var amzBgtPrcBands = [];
            function amzBgtPrcLockedBands(existing) {
                var prev = Array.isArray(existing) ? existing : [];
                return AMZ_BGT_PRC_DEFAULTS.map(function (def, i) {
                    var keep = prev[i] || {};
                    var bgt = parseInt(keep.bgt, 10);
                    if (!isFinite(bgt) || bgt < 1) bgt = def.bgt;
                    return {
                        prc_from: def.prc_from,
                        prc_to: def.prc_to,
                        bgt: bgt,
                        label: keep.label != null && String(keep.label) !== '' ? String(keep.label) : def.label,
                        color: keep.color || def.color
                    };
                });
            }
            function amzRenderBgtPrcBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtPrcRuleBandsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var toLabel = (i === bands.length - 1) ? '>150' : String(band.prc_to);
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + band.prc_from + '" readonly tabindex="-1" style="background:#f8f9fa;"></td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + toLabel + '" readonly tabindex="-1" style="background:#f8f9fa;"></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtPrcBands[idx]) return;
                        if (fld === 'bgt') amzBgtPrcBands[idx][fld] = (el.value === '' ? '' : parseInt(el.value, 10));
                        else amzBgtPrcBands[idx][fld] = el.value;
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzBgtPrcBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                    });
                    inp.addEventListener('change', function () { writeBand(this); });
                });
            }
            function amzLoadBgtPrcBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtPrcBands = amzBgtPrcLockedBands(bands);
                amzRenderBgtPrcBands(amzBgtPrcBands);
            }
            function amzRefreshBgtPrcRuleFromServer(cb) {
                fetch(bgtPrcRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtPrcRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtPrcModalEl = document.getElementById('amazonAdsBgtPrcRuleModal');
            if (bgtPrcModalEl) {
                bgtPrcModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtPrcRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtPrcRuleFromServer(function () { amzLoadBgtPrcBandsFromRule(window.amazonAdsBgtPrcRule || {}); });
                });
            }
            var bgtPrcSaveBtn = document.getElementById('amazonAdsBgtPrcRuleSaveBtn');
            if (bgtPrcSaveBtn) {
                bgtPrcSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtPrcRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzBgtPrcBands = amzBgtPrcLockedBands(amzBgtPrcBands);
                    var cleaned = (amzBgtPrcBands || []).map(function (b) {
                        return {
                            prc_from: b.prc_from,
                            prc_to: b.prc_to,
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseInt(b.bgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (cleaned.length !== 5) { if (err) { err.textContent = 'BGT PRC needs exactly five slabs.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        if (!isFinite(cleaned[i].bgt) || cleaned[i].bgt < 1) { if (err) { err.textContent = 'Every slab needs a Bgt Prc of 1 or more.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtPrcSaveBtn.disabled = true;
                    fetch(bgtPrcRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtPrcRule = b.rule || window.amazonAdsBgtPrcRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtPrcModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtPrcSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs REVIEWS (star rating → Bgt Reviews); dynamic slabs ----
            var AMZ_BGT_REVIEWS_DEFAULTS = [
                { rev_from: 2.99, rev_to: 3.5, bgt: 1, label: 'Red', color: '#a00211' },
                { rev_from: 3.51, rev_to: 4, bgt: 2, label: 'Yellow', color: '#ffc107' },
                { rev_from: 4.01, rev_to: 4.5, bgt: 3, label: 'Blue', color: '#2563eb' },
                { rev_from: 4.51, rev_to: 5, bgt: 4, label: 'Green', color: '#28a745' }
            ];
            var AMZ_BGT_REVIEWS_LABELS = ['Red', 'Yellow', 'Blue', 'Green', 'Pink', 'Purple'];
            var AMZ_BGT_REVIEWS_COLORS = ['#a00211', '#ffc107', '#2563eb', '#28a745', '#e83e8c', '#7c3aed'];
            var amzBgtReviewsBands = [];
            function amzBgtReviewsNormalizeBands(existing) {
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.rev_from);
                    var to = parseFloat(keep.rev_to);
                    var bgt = parseInt(keep.bgt, 10);
                    out.push({
                        rev_from: isFinite(from) ? from : '',
                        rev_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) && bgt >= 1 ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_REVIEWS_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtReviewsRatingOfRow(row) {
                var r = parseFloat(row && (row.bgt_reviews_rating != null ? row.bgt_reviews_rating : row.reviews));
                return isFinite(r) ? r : null;
            }
            function amzBgtReviewsBandIndexForRating(rating, bands) {
                if (rating == null || !isFinite(rating) || !Array.isArray(bands)) return -1;
                for (var i = 0; i < bands.length; i++) {
                    var from = parseFloat(bands[i].rev_from);
                    var to = parseFloat(bands[i].rev_to);
                    if (!isFinite(from) || !isFinite(to)) continue;
                    var nextFrom = (i < bands.length - 1) ? parseFloat(bands[i + 1].rev_from) : NaN;
                    var hit = (rating >= from && rating <= to)
                        || (isFinite(nextFrom) && rating > to && rating < nextFrom);
                    if (hit) return i;
                }
                return -1;
            }
            function amzBgtReviewsCounts(bands) {
                var counts = (bands || []).map(function () { return 0; });
                if (!table || typeof table.getData !== 'function') return counts;
                var rows = [];
                try { rows = table.getData() || []; } catch (e) { return counts; }
                rows.forEach(function (row) {
                    var idx = amzBgtReviewsBandIndexForRating(amzBgtReviewsRatingOfRow(row), bands);
                    if (idx >= 0) counts[idx]++;
                });
                return counts;
            }
            function amzBgtReviewsRefreshCounts() {
                var counts = amzBgtReviewsCounts(amzBgtReviewsBands);
                document.querySelectorAll('#amazonAdsBgtReviewsRuleBandsBody [data-count-idx]').forEach(function (el) {
                    var i = +el.dataset.countIdx;
                    el.textContent = String(counts[i] != null ? counts[i] : 0);
                });
            }
            function amzBgtReviewsNewBand() {
                var last = amzBgtReviewsBands.length ? amzBgtReviewsBands[amzBgtReviewsBands.length - 1] : null;
                var lastTo = last ? parseFloat(last.rev_to) : NaN;
                var from = isFinite(lastTo) ? +(lastTo + 0.01).toFixed(2) : 2.99;
                var to = +(from + 0.49).toFixed(2);
                var bgt = last ? (parseInt(last.bgt, 10) || 0) + 1 : 1;
                if (!isFinite(bgt) || bgt < 1) bgt = 1;
                var i = amzBgtReviewsBands.length;
                return {
                    rev_from: from,
                    rev_to: to,
                    bgt: bgt,
                    label: AMZ_BGT_REVIEWS_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_REVIEWS_COLORS[i] || '#6c757d'
                };
            }
            function amzRenderBgtReviewsBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtReviewsRuleBandsBody');
                if (!tbody) return;
                var counts = amzBgtReviewsCounts(bands);
                var canDelete = bands.length > 1;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" value="' + (band.rev_from != null ? band.rev_from : '') + '" data-idx="' + i + '" data-field="rev_from" placeholder="2.99"></td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm" value="' + (band.rev_to != null ? band.rev_to : '') + '" data-idx="' + i + '" data-field="rev_to" placeholder="5"></td>'
                        + '<td class="text-center"><span class="fw-semibold" data-count-idx="' + i + '" title="Campaigns on this grid page in this Reviews range">' + (counts[i] != null ? counts[i] : 0) + '</span></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtReviewsBands[idx]) return;
                        if (fld === 'bgt') amzBgtReviewsBands[idx][fld] = (el.value === '' ? '' : parseInt(el.value, 10));
                        else if (fld === 'rev_from' || fld === 'rev_to') amzBgtReviewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        else amzBgtReviewsBands[idx][fld] = el.value;
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzBgtReviewsBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                        if (fld === 'rev_from' || fld === 'rev_to') amzBgtReviewsRefreshCounts();
                    });
                    inp.addEventListener('change', function () { writeBand(this); if (this.dataset.field === 'rev_from' || this.dataset.field === 'rev_to') amzBgtReviewsRefreshCounts(); });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtReviewsBands.length <= 1) return;
                        amzBgtReviewsBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtReviewsBands(amzBgtReviewsBands);
                    });
                });
            }
            function amzLoadBgtReviewsBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtReviewsBands = amzBgtReviewsNormalizeBands(bands);
                amzRenderBgtReviewsBands(amzBgtReviewsBands);
            }
            function amzRefreshBgtReviewsRuleFromServer(cb) {
                fetch(bgtReviewsRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtReviewsRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtReviewsModalEl = document.getElementById('amazonAdsBgtReviewsRuleModal');
            if (bgtReviewsModalEl) {
                bgtReviewsModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtReviewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtReviewsRuleFromServer(function () { amzLoadBgtReviewsBandsFromRule(window.amazonAdsBgtReviewsRule || {}); });
                });
            }
            var bgtReviewsAddBtn = document.getElementById('amazonAdsBgtReviewsRuleAddBandBtn');
            if (bgtReviewsAddBtn) {
                bgtReviewsAddBtn.addEventListener('click', function () {
                    amzBgtReviewsBands.push(amzBgtReviewsNewBand());
                    amzRenderBgtReviewsBands(amzBgtReviewsBands);
                });
            }
            var bgtReviewsSaveBtn = document.getElementById('amazonAdsBgtReviewsRuleSaveBtn');
            if (bgtReviewsSaveBtn) {
                bgtReviewsSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtReviewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtReviewsBands || []).map(function (b) {
                        return {
                            rev_from: (b.rev_from === '' || b.rev_from == null) ? NaN : parseFloat(b.rev_from),
                            rev_to: (b.rev_to === '' || b.rev_to == null) ? NaN : parseFloat(b.rev_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseInt(b.bgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var ri = 0; ri < cleaned.length; ri++) {
                        var rb = cleaned[ri];
                        if (!isFinite(rb.rev_from) || !isFinite(rb.rev_to) || !isFinite(rb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Reviews values.'; err.classList.remove('d-none'); } return; }
                        if (rb.rev_from > rb.rev_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                        if (rb.bgt < 1) { if (err) { err.textContent = 'Every slab needs a Bgt Reviews of 1 or more.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtReviewsSaveBtn.disabled = true;
                    fetch(bgtReviewsRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtReviewsRule = b.rule || window.amazonAdsBgtReviewsRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtReviewsModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtReviewsSaveBtn.disabled = false; });
                });
            }

            // ---- SBID rule modal ----
            var SBID_FIELDS = [
                ['amazonAdsSbidRuleUtilLow', 'util_low'],
                ['amazonAdsSbidRuleUtilHigh', 'util_high'],
                ['amazonAdsSbidRuleBothLowFallback', 'both_low_fallback'],
                ['amazonAdsSbidRuleLowMultL1', 'both_low_mult_l1'],
                ['amazonAdsSbidRuleLowMultL2', 'both_low_mult_l2'],
                ['amazonAdsSbidRuleLowMultL7', 'both_low_mult_l7'],
                ['amazonAdsSbidRuleHighMultL1', 'both_high_mult_l1']
            ];
            function amzFillSbidForm(rule) {
                if (!rule) return;
                SBID_FIELDS.forEach(function (pair) {
                    var el = document.getElementById(pair[0]);
                    if (el && rule[pair[1]] != null) el.value = String(rule[pair[1]]);
                });
            }
            function amzRefreshSbidRuleFromServer(cb) {
                fetch(sbidRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsSbidRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var sbidModalEl = document.getElementById('amazonAdsSbidRuleModal');
            if (sbidModalEl) {
                sbidModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsSbidRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshSbidRuleFromServer(function () { amzFillSbidForm(window.amazonAdsSbidRule || {}); });
                });
            }
            var sbidSaveBtn = document.getElementById('amazonAdsSbidRuleSaveBtn');
            if (sbidSaveBtn) {
                sbidSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsSbidRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var payload = {};
                    var invalid = false;
                    SBID_FIELDS.forEach(function (pair) {
                        var el = document.getElementById(pair[0]);
                        var n = el ? parseFloat(String(el.value).trim()) : NaN;
                        if (!isFinite(n)) invalid = true;
                        payload[pair[1]] = n;
                    });
                    if (invalid) { if (err) { err.textContent = 'All SBID rule fields must be numeric.'; err.classList.remove('d-none'); } return; }
                    sbidSaveBtn.disabled = true;
                    fetch(sbidRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsSbidRule = b.rule || window.amazonAdsSbidRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(sbidModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { sbidSaveBtn.disabled = false; });
                });
            }

            function amzPrFromRule(rule) {
                var pr = (rule && rule.pr) ? rule.pr : {};
                var rev = (rule && rule.reviews && !Array.isArray(rule.reviews)) ? rule.reviews : {};
                var dil = Number(pr.dil_above);
                var price = Number(pr.price_below);
                var reviewsBelow = Number(rev.below != null ? rev.below : pr.reviews_below);
                var reviewsEnabled = (rev.enabled != null) ? !!rev.enabled : !!pr.reviews_enabled;
                return {
                    enabled: !!pr.enabled,
                    dil_above: isFinite(dil) ? dil : 100,
                    dil_enabled: pr.dil_enabled !== false,
                    price_below: isFinite(price) ? price : 20,
                    price_enabled: pr.price_enabled !== false,
                    reviews_enabled: reviewsEnabled,
                    reviews_below: isFinite(reviewsBelow) ? reviewsBelow : 2.99
                };
            }
            function amzRefreshPrBtn() {
                var btn = document.getElementById('amazonAdsPrRuleBtn');
                if (!btn) return;
                var pr = amzPrFromRule(window.amazonAdsPauseRule);
                btn.textContent = 'Pause Rule';
                var on = pr.enabled || pr.reviews_enabled;
                btn.classList.toggle('btn-danger', on);
                btn.classList.toggle('text-white', on);
                btn.classList.toggle('btn-outline-danger', !on);
                var tips = [];
                if (pr.dil_enabled) tips.push('Dil% ≥ ' + pr.dil_above + '%');
                if (pr.price_enabled) tips.push('Price < $' + pr.price_below);
                if (pr.reviews_enabled) tips.push('Reviews < ' + pr.reviews_below + '★');
                btn.title = (pr.enabled || pr.reviews_enabled) && tips.length
                    ? ('Auto-pause when ' + tips.join(' or '))
                    : 'Dil% / price / reviews pause rule — click to set thresholds';
            }
            function amzFillPrModal() {
                var pr = amzPrFromRule(window.amazonAdsPauseRule);
                var dilInput = document.getElementById('amazonAdsPrDilAbove');
                var priceInput = document.getElementById('amazonAdsPrPriceBelow');
                var reviewsInput = document.getElementById('amazonAdsPrReviewsBelow');
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var priceEn = document.getElementById('amazonAdsPrPriceEnabled');
                var reviewsEn = document.getElementById('amazonAdsPrReviewsEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                if (dilInput) dilInput.value = String(pr.dil_above);
                if (priceInput) priceInput.value = String(pr.price_below);
                if (reviewsInput) reviewsInput.value = String(pr.reviews_below);
                if (dilEn) dilEn.checked = pr.dil_enabled;
                if (priceEn) priceEn.checked = pr.price_enabled;
                if (reviewsEn) reviewsEn.checked = pr.reviews_enabled;
                if (en) en.checked = pr.enabled;
            }
            function amzSavePrRule(apply) {
                var err = document.getElementById('amazonAdsPrRuleModalError');
                var ok = document.getElementById('amazonAdsPrRuleModalOk');
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                if (ok) { ok.classList.add('d-none'); ok.textContent = ''; }
                var dilInput = document.getElementById('amazonAdsPrDilAbove');
                var priceInput = document.getElementById('amazonAdsPrPriceBelow');
                var reviewsInput = document.getElementById('amazonAdsPrReviewsBelow');
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var priceEn = document.getElementById('amazonAdsPrPriceEnabled');
                var reviewsEn = document.getElementById('amazonAdsPrReviewsEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                var dil = dilInput ? parseFloat(String(dilInput.value).trim()) : NaN;
                var price = priceInput ? parseFloat(String(priceInput.value).trim()) : NaN;
                var reviewsBelow = reviewsInput ? parseFloat(String(reviewsInput.value).trim()) : NaN;
                if (!isFinite(dil) || dil < 0) {
                    if (err) { err.textContent = 'Enter a Dil% threshold (0 or higher).'; err.classList.remove('d-none'); }
                    return;
                }
                if (!isFinite(price) || price < 0) {
                    if (err) { err.textContent = 'Enter a price threshold (0 or higher).'; err.classList.remove('d-none'); }
                    return;
                }
                if (!isFinite(reviewsBelow) || reviewsBelow < 1 || reviewsBelow > 5) {
                    if (err) { err.textContent = 'Enter a Reviews threshold from 1 to 5.'; err.classList.remove('d-none'); }
                    return;
                }
                var saveBtn = document.getElementById('amazonAdsPrRuleSaveBtn');
                var applyBtn = document.getElementById('amazonAdsPrRuleApplyBtn');
                if (saveBtn) saveBtn.disabled = true;
                if (applyBtn) applyBtn.disabled = true;
                fetch(prRuleSaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        enabled: !!(en && en.checked),
                        dil_above: dil,
                        dil_enabled: !!(dilEn && dilEn.checked),
                        price_below: price,
                        price_enabled: !!(priceEn && priceEn.checked),
                        reviews_enabled: !!(reviewsEn && reviewsEn.checked),
                        reviews_below: reviewsBelow,
                        apply: !!apply || !!(en && en.checked) || !!(reviewsEn && reviewsEn.checked)
                    })
                })
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (out) {
                        var b = out.body || {};
                        if (!out.ok || b.status === 422 || b.status === 500) {
                            if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); }
                            return;
                        }
                        window.amazonAdsPauseRule = b.rule || window.amazonAdsPauseRule;
                        amzRefreshPrBtn();
                        var msg = b.message || 'Saved.';
                        if (b.apply) {
                            msg += ' Paused ' + (b.apply.paused || 0) + ', enabled ' + (b.apply.enabled || 0)
                                + ', unchanged ' + (b.apply.unchanged || 0) + ', failed ' + (b.apply.failed || 0) + '.';
                            var prErrs = Array.isArray(b.apply.errors) ? b.apply.errors.filter(Boolean) : [];
                            if (prErrs.length && err) {
                                err.textContent = prErrs.slice(0, 8).join(' | ');
                                err.classList.remove('d-none');
                            }
                        }
                        if (ok) { ok.textContent = msg; ok.classList.remove('d-none'); }
                        if (!apply && !(en && en.checked) && typeof bootstrap !== 'undefined') {
                            var inst = bootstrap.Modal.getInstance(document.getElementById('amazonAdsPrRuleModal'));
                            if (inst) inst.hide();
                        }
                        return table ? Promise.resolve(table.setData()) : null;
                    })
                    .then(function () { amzRefreshUiSoon(); })
                    .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                    .finally(function () {
                        if (saveBtn) saveBtn.disabled = false;
                        if (applyBtn) applyBtn.disabled = false;
                    });
            }
            var prModalEl = document.getElementById('amazonAdsPrRuleModal');
            if (prModalEl) {
                prModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsPrRuleModalError');
                    var ok = document.getElementById('amazonAdsPrRuleModalOk');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    if (ok) { ok.classList.add('d-none'); ok.textContent = ''; }
                    fetch(pauseRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (body) {
                            if (body && body.rule) window.amazonAdsPauseRule = body.rule;
                            amzFillPrModal();
                            amzRefreshPrBtn();
                        })
                        .catch(function () { amzFillPrModal(); });
                });
            }
            var prSaveBtn = document.getElementById('amazonAdsPrRuleSaveBtn');
            if (prSaveBtn) prSaveBtn.addEventListener('click', function () { amzSavePrRule(false); });
            var prApplyBtn = document.getElementById('amazonAdsPrRuleApplyBtn');
            if (prApplyBtn) prApplyBtn.addEventListener('click', function () {
                var dilInput = document.getElementById('amazonAdsPrDilAbove');
                var priceInput = document.getElementById('amazonAdsPrPriceBelow');
                var reviewsInput = document.getElementById('amazonAdsPrReviewsBelow');
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var priceEn = document.getElementById('amazonAdsPrPriceEnabled');
                var reviewsEn = document.getElementById('amazonAdsPrReviewsEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                var on = !!(en && en.checked);
                var revOn = !!(reviewsEn && reviewsEn.checked);
                var bits = [];
                if (dilEn && dilEn.checked) bits.push('Dil% ≥ ' + (dilInput ? dilInput.value : '100') + '%');
                if (priceEn && priceEn.checked) bits.push('Price < $' + (priceInput ? priceInput.value : '20'));
                var parts = [];
                if (on) {
                    parts.push('pause matching SP + SB campaigns when ' + (bits.join(' or ') || 'no Dil%/Price conditions'));
                }
                if (revOn) {
                    parts.push('pause product ads rated below ' + (reviewsInput ? reviewsInput.value : '2.99') + '★ (campaign stays on)');
                }
                var msg = parts.length
                    ? ('Save Pause Rule and ' + parts.join(', and ') + ' on Amazon now?')
                    : 'Save Pause Rule with campaign auto-pause and Reviews off? Matching campaigns / product ads will not be auto-paused by this rule.';
                if (!window.confirm(msg)) return;
                amzSavePrRule(true);
            });
            amzRefreshPrBtn();

            // ---- initial state ----
            (function () {
                var params = new URLSearchParams(window.location.search);
                var deepSearch = params.get('search');
                if (deepSearch) {
                    var s = document.getElementById('amz-filter-search');
                    if (s) s.value = deepSearch;
                }
                var deepSource = params.get('source');
                if (deepSource && rawSources[deepSource]) {
                    var rt = document.getElementById('amazonAdsFilterReportType');
                    if (rt) rt.value = deepSource;
                    amzSwitchSource(deepSource);
                } else {
                    amzSetDatesToLatestForSource('sp_reports');
                }
                amzFillAcosFilterOptions();
                amzFillAdsCvrFilterOptions();
                amzUpdatePushButtons();
                amzUpdatePieButton();
                amzUpdateSourceLabel();
            })();
        });
    </script>
@endsection
