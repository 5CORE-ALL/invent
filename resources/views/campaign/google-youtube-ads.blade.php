@php
    $pageTitle = 'Youtube ads';
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
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 11px; width: 100%;
        }
        #google-ads-campaigns-raw-table { width: 100%; }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #google-ads-campaigns-raw-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        /* Vertical headers (same as Google Shopping); campaign name + checkbox stay horizontal */
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
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__gac_select"] .tabulator-col-title,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_category"] .tabulator-col-title,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_audience"] .tabulator-col-title,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_landing"] .tabulator-col-title {
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
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__gac_select"] .tabulator-col-title-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_category"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_category"] .tabulator-col-title-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_audience"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_audience"] .tabulator-col-title-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_landing"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="yt_landing"] .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        /* Tighter horizontal padding than Tabulator defaults */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 2px 3px !important;
            vertical-align: middle;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell .gac-raw-status-cell {
            white-space: nowrap;
        }
        #google-ads-campaigns-raw-wrap .gac-yt-attr {
            width: 100%;
            max-width: 100%;
            height: 22px;
            font-size: 11px;
            line-height: 1.1;
            padding: 0 2px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #fff;
        }
        .gac-metric-tabs {
            border-bottom: 1px solid #dee2e6;
        }
        .gac-metric-tabs .nav-link {
            padding: 0.35rem 0.9rem;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .gac-metric-tabs .nav-link.active {
            color: #0d6efd;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-open {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 22px;
            cursor: pointer;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-icon {
            cursor: pointer;
            font-size: 15px;
            pointer-events: none;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-icon.is-empty {
            color: #dc2626;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-icon.is-filled {
            color: #16a34a;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-pct {
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            pointer-events: none;
        }
        #google-ads-campaigns-raw-wrap .gac-audit-pct.is-empty { color: #94a3b8; }
        #google-ads-campaigns-raw-wrap .gac-audit-pct.is-good { color: #16a34a; }
        #google-ads-campaigns-raw-wrap .gac-audit-pct.is-mid { color: #d97706; }
        #google-ads-campaigns-raw-wrap .gac-audit-pct.is-bad { color: #dc2626; }
        #gacVideoAuditModal .modal-dialog {
            max-width: min(1080px, 96vw);
            margin: 0.45rem auto;
        }
        #gacVideoAuditModal .modal-content {
            max-height: calc(100vh - 0.9rem);
        }
        #gacVideoAuditModal .modal-body {
            padding: 10px 14px 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        #gacVideoAuditModal .gac-va-report {
            display: grid;
            grid-template-columns: 1.2fr repeat(3, 1fr);
            gap: 6px;
        }
        #gacVideoAuditModal .gac-va-stat {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 6px 8px;
            text-align: center;
            background: #f8fafc;
        }
        #gacVideoAuditModal .gac-va-stat .gac-va-num {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
        }
        #gacVideoAuditModal .gac-va-stat .gac-va-lbl {
            font-size: 10px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }
        #gacVideoAuditModal .gac-va-stat.is-score.is-good { background: #f0fdf4; border-color: #bbf7d0; }
        #gacVideoAuditModal .gac-va-stat.is-score.is-mid { background: #fffbeb; border-color: #fde68a; }
        #gacVideoAuditModal .gac-va-stat.is-score.is-bad { background: #fef2f2; border-color: #fecaca; }
        #gacVideoAuditModal .gac-va-stat.is-fail .gac-va-num { color: #dc2626; }
        #gacVideoAuditModal .gac-va-stat.is-pass .gac-va-num { color: #16a34a; }
        #gacVideoAuditBody {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            overflow: auto;
            min-height: 0;
            flex: 1;
        }
        #gacVideoAuditModal .gac-va-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            order: 2;
        }
        #gacVideoAuditModal .gac-va-item.is-fail { order: 0; background: #fef2f2; border-color: #fecaca; }
        #gacVideoAuditModal .gac-va-item.is-blank { order: 1; }
        #gacVideoAuditModal .gac-va-item.is-pass { order: 3; background: #f0fdf4; border-color: #bbf7d0; }
        #gacVideoAuditModal .gac-va-item.is-na { order: 4; background: #f8fafc; }
        #gacVideoAuditModal .gac-va-q {
            font-size: 12px;
            font-weight: 650;
            line-height: 1.25;
            min-width: 0;
        }
        #gacVideoAuditModal .gac-va-ans {
            display: inline-flex;
            flex-shrink: 0;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            overflow: hidden;
        }
        #gacVideoAuditModal .gac-va-ans label {
            margin: 0;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-right: 1px solid #e5e7eb;
        }
        #gacVideoAuditModal .gac-va-ans label:last-child { border-right: 0; }
        #gacVideoAuditModal .gac-va-ans input { display: none; }
        #gacVideoAuditModal .gac-va-ans label:has(input:checked) {
            color: #fff;
        }
        #gacVideoAuditModal .gac-va-ans label:has(input[value="pass"]:checked) { background: #16a34a; }
        #gacVideoAuditModal .gac-va-ans label:has(input[value="fail"]:checked) { background: #dc2626; }
        #gacVideoAuditModal .gac-va-ans label:has(input[value="na"]:checked) { background: #64748b; }
        #gacVideoAuditModal .gac-va-foot {
            display: grid;
            grid-template-columns: 1fr minmax(220px, 34%);
            gap: 8px;
            align-items: start;
        }
        #gacVideoAuditModal .gac-va-history {
            max-height: 88px;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        #gacVideoAuditModal .gac-va-history table { font-size: 11px; }
        @media (max-width: 800px) {
            #gacVideoAuditBody { grid-template-columns: 1fr; }
            #gacVideoAuditModal .gac-va-report,
            #gacVideoAuditModal .gac-va-foot { grid-template-columns: 1fr; }
        }
        /* Column visibility — 4 groups (Basics / LT / L30 / Others) */
        #column-dropdown-menu.gac-col-vis-menu,
        #column-dropdown-menu.gac-col-vis-menu.show {
            min-width: min(92vw, 720px) !important;
            width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 280px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
        }
        #column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
        }
        @media (max-width: 768px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
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
        .gac-raw-search-input {
            width: 200px;
            max-width: 36vw;
            height: 28px;
            padding: 2px 8px;
            font-size: 12px;
            flex: 0 0 auto;
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
                    <ul class="nav nav-tabs gac-metric-tabs mb-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active" id="gac-tab-l30" data-gac-tab="l30" role="tab" aria-selected="true">L30</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" id="gac-tab-lt" data-gac-tab="lt" role="tab" aria-selected="false">LT</button>
                        </li>
                    </ul>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <input type="search" id="gac-filter-search" class="form-control form-control-sm gac-raw-search-input" placeholder="Search Campaign..." autocomplete="off" aria-label="Search by campaign name" maxlength="100">

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
                            <div class="dropdown-menu dropdown-menu-start gac-col-vis-menu"
                                id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbgt-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbgtRuleModal" title="Edit ACOS band thresholds and SBGT tier values">SBGT RULE</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="gac-raw-pause-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawPauseRuleModal" title="Pause campaigns in this grid when Spend LT and ACOS LT hit a slab">Pause Rule</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbgt" title="Push SBGT to Youtube ads campaigns using the grid values for each selected row.">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" id="gac-raw-push-pause" title="Queue matching YouTube VIDEO campaigns and show a Google Ads Script that pauses them on the live account. The Ads API cannot pause VIDEO campaigns.">
                            <i class="fa fa-cloud-upload-alt"></i> Push Pause
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="gac-raw-copy-queued-script" title="Show the Google Ads Script for campaigns already queued by Push Pause.">
                            Copy Pause Script
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
                            <div class="gac-raw-filter-field d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-u7-pie-open" data-bs-toggle="modal" data-bs-target="#gacRawU7PieModal" title="Row counts by U7% band (U7 filter ignored). Opens chart; click a slice for last 30 days.">U7% mix</button>
                            </div>
                        </div>
                    </div>
                    <div id="gac-raw-push-result" class="alert alert-secondary small d-none mt-2 mb-0 py-2" role="status" aria-live="polite">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
                            <div class="fw-semibold" id="gac-raw-push-result-title"></div>
                            <button type="button" class="btn btn-sm btn-primary d-none" id="gac-raw-copy-ads-script">Copy Google Ads Script</button>
                        </div>
                        <pre id="gac-raw-push-result-pre" class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap;max-height:220px;overflow:auto;"></pre>
                        <div id="gac-raw-ads-script-wrap" class="d-none mt-2">
                            <div class="small fw-semibold mb-1">Google Ads Script — paste into Tools → Bulk actions → Scripts, then Authorize and Run</div>
                            <textarea id="gac-raw-ads-script-inline" class="form-control font-monospace small" rows="12" readonly></textarea>
                        </div>
                    </div>
                    <div id="google-ads-campaigns-raw-wrap">
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
                        Same ACOS → SBGT brackets as <strong>/facebook-ads</strong>, matched on
                        <strong>ACOS % only</strong> (no spend gate). Rows are checked
                        <strong>top to bottom</strong>; the first range that contains the
                        campaign's ACOS gets its SBGT. Use <code>9999</code> on
                        <em>To</em> for the catch-all highest band.
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

    <div class="modal fade" id="gacRawPauseRuleModal" tabindex="-1" aria-labelledby="gacRawPauseRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawPauseRuleModalLabel">Pause rule — Spend LT + ACOS LT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Slabs are checked <strong>top to bottom</strong>. After you save,
                        use <strong>Push Pause</strong> for matching
                        <strong>ENABLED</strong> campaigns
                        when <strong>Spend LT &gt; amount</strong> and
                        <strong>ACOS LT &gt; %</strong>.
                        Google blocks pausing YouTube <strong>VIDEO</strong> campaigns through the Ads API,
                        so Push Pause prepares a <strong>Google Ads Script</strong> — paste it under
                        Tools → Bulk actions → Scripts and click Run.
                    </p>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="gac-pause-rule-enabled">
                        <label class="form-check-label" for="gac-pause-rule-enabled">Enable pause rule</label>
                        </div>
                    <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="gac-pause-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Spend LT &gt;</th>
                                <th>ACOS LT &gt; (%)</th>
                                <th style="width:56px;" class="text-center">Del</th>
                            </tr>
                        </thead>
                        <tbody id="gac-pause-slabs-body"></tbody>
                    </table>
                        </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="gac-pause-add-slab-btn">
                        <i class="fas fa-plus me-1"></i>Add slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawPauseRuleErr" role="alert"></p>
                    <details class="mt-3">
                        <summary class="small fw-semibold">Optional: hourly watcher script (install once)</summary>
                        <p class="small text-muted mb-2 mt-2">
                            Save this as a second script and set a hourly schedule.
                            After Push Pause, it pulls the queued IDs and pauses them without pasting a new script each time.
                        </p>
                        <textarea id="gac-yt-pause-watcher-script" class="form-control font-monospace small" rows="8" readonly>{{ $youtubePauseWatcherScript ?? '' }}</textarea>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="gac-yt-copy-watcher-script">Copy watcher script</button>
                    </details>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawPauseRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacYtPauseScriptModal" tabindex="-1" aria-labelledby="gacYtPauseScriptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacYtPauseScriptModalLabel">Pause YouTube campaigns in Google Ads</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2">
                        Google Ads API cannot pause <strong>VIDEO</strong> campaigns
                        (<code>MUTATE_NOT_ALLOWED</code>). Run this script on the live account:
                    </p>
                    <ol class="small mb-3 ps-3">
                        <li>Open <a href="https://ads.google.com/aw/bulk/scripts" target="_blank" rel="noopener">Google Ads → Tools → Bulk actions → Scripts</a></li>
                        <li>Click <strong>+</strong>, paste the script, save</li>
                        <li>Authorize, then click <strong>Run</strong></li>
                        <li>Come back here and click <strong>Pull Data</strong> (or Refresh) so Sts matches Google Ads</li>
                    </ol>
                    <textarea id="gac-yt-pause-script-text" class="form-control font-monospace small" rows="14" readonly></textarea>
                    <pre id="gac-yt-pause-script-log" class="small bg-light border rounded p-2 mt-2 mb-0" style="white-space:pre-wrap;max-height:160px;overflow:auto;"></pre>
                </div>
                <div class="modal-footer py-2">
                    <a class="btn btn-sm btn-outline-secondary" href="https://ads.google.com/aw/bulk/scripts" target="_blank" rel="noopener">Open Scripts</a>
                    <button type="button" class="btn btn-sm btn-primary" id="gac-yt-copy-pause-script">Copy script</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="gacRawBadgeChartModal" tabindex="-1" aria-labelledby="gacRawBadgeChartModalLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;" id="gacRawBadgeChartModalLabel">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="gacRawBadgeChartTitle">Google YouTube Ads - ACOS (Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="gacRawBadgeChartRange" class="form-select form-select-sm bg-white" style="width:110px;height:26px;font-size:11px;padding:1px 8px;" aria-label="Chart date range">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                            <option value="32" selected>32 Days</option>
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

    <div class="modal fade" id="gacVideoAuditModal" tabindex="-1" aria-labelledby="gacVideoAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacVideoAuditModalLabel">
                        Video audit — <span id="gacVideoAuditCampaignName" class="fw-normal text-muted"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span>Campaign ID: <code id="gacVideoAuditCampaignId"></code></span>
                        <span>Fail marks a likely cause. Answer at least one item or add a note.</span>
                    </div>
                    <div class="gac-va-report" aria-live="polite">
                        <div class="gac-va-stat is-score" id="gacVideoAuditScoreCard">
                            <div class="gac-va-num" id="gacVideoAuditScore">—</div>
                            <div class="gac-va-lbl">Pass rate</div>
                        </div>
                        <div class="gac-va-stat is-pass">
                            <div class="gac-va-num" id="gacVideoAuditPassCount">0</div>
                            <div class="gac-va-lbl">Pass</div>
                        </div>
                        <div class="gac-va-stat is-fail">
                            <div class="gac-va-num" id="gacVideoAuditFailCount">0</div>
                            <div class="gac-va-lbl">Fail</div>
                        </div>
                        <div class="gac-va-stat">
                            <div class="gac-va-num" id="gacVideoAuditNaCount">0</div>
                            <div class="gac-va-lbl">N/A</div>
                        </div>
                    </div>
                    <input type="hidden" id="gacVideoAuditCid">
                    <input type="hidden" id="gacVideoAuditCname">
                    <div id="gacVideoAuditBody"></div>
                    <div class="gac-va-foot">
                        <div>
                            <label class="form-label small mb-1" for="gacVideoAuditComments">Notes</label>
                            <textarea id="gacVideoAuditComments" class="form-control form-control-sm" rows="2"
                                placeholder="What looked off, or what to change next…"></textarea>
                        </div>
                        <div>
                            <div class="small text-uppercase text-muted mb-1">History</div>
                            <p class="small text-muted mb-0 d-none" id="gacVideoAuditHistoryEmpty">No prior video audits.</p>
                            <div class="gac-va-history d-none" id="gacVideoAuditHistoryWrap">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>When</th>
                                            <th>%</th>
                                            <th class="text-end">Fails</th>
                                            <th>By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gacVideoAuditHistoryBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <p class="small text-danger mb-0 d-none" id="gacVideoAuditErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacVideoAuditSaveBtn">Save audit</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacVideoAiAuditModal" tabindex="-1" aria-labelledby="gacVideoAiAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacVideoAiAuditModalLabel">
                        Audit AI — <span id="gacVideoAiAuditCampaignName" class="fw-normal text-muted"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Gemini analyzes the YouTube video (when a URL is set) against the checkpoints and
                        returns each failure with a reason and a next-step direction.
                    </p>
                    <p class="small mb-3">
                        Campaign ID: <code id="gacVideoAiAuditCampaignId"></code>
                        <span class="ms-2">Fails: <strong id="gacVideoAiAuditFailCount">0</strong></span>
                        <span class="ms-2 text-muted" id="gacVideoAiAuditModel"></span>
                    </p>
                    <input type="hidden" id="gacVideoAiAuditCid">
                    <input type="hidden" id="gacVideoAiAuditCname">
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="gacVideoAiAuditUrl">YouTube / video URL</label>
                        <input type="url" id="gacVideoAiAuditUrl" class="form-control form-control-sm"
                            placeholder="https://www.youtube.com/watch?v=…">
                    </div>
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-label small mb-1" for="gacVideoAiAuditPrompt">AI prompt (editable)</label>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gacVideoAiAuditPromptReset">Reset default</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="gacVideoAiAuditPromptSave">Save prompt</button>
                            </div>
                        </div>
                        <textarea id="gacVideoAiAuditPrompt" class="form-control form-control-sm font-monospace" rows="7"></textarea>
                    </div>
                    <h6 class="small text-uppercase text-muted mb-1">Prompt history</h6>
                    <p class="small text-muted mb-0 d-none" id="gacVideoAiPromptHistoryEmpty">No saved prompt versions yet.</p>
                    <div class="table-responsive border rounded mb-3 d-none" id="gacVideoAiPromptHistoryWrap" style="max-height:140px;overflow:auto;">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th>By</th>
                                    <th>Prompt</th>
                                    <th style="width:70px;"></th>
                                </tr>
                            </thead>
                            <tbody id="gacVideoAiPromptHistoryBody"></tbody>
                        </table>
                    </div>
                    <div class="alert alert-light border small py-2 mb-3 d-none" id="gacVideoAiAuditSummary"></div>
                    <div class="table-responsive border rounded mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Checkpoint</th>
                                    <th style="width:70px;">Verdict</th>
                                    <th>Error</th>
                                    <th>Reason</th>
                                    <th>Direction</th>
                                </tr>
                            </thead>
                            <tbody id="gacVideoAiAuditBody">
                                <tr><td colspan="5" class="text-muted small text-center py-3">Run AI to fill this table.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <h6 class="small text-uppercase text-muted mb-1">Analysis history</h6>
                    <p class="small text-muted mb-0 d-none" id="gacVideoAiAuditHistoryEmpty">No prior AI audits.</p>
                    <div class="table-responsive border rounded d-none" id="gacVideoAiAuditHistoryWrap">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th>By</th>
                                    <th>Model</th>
                                    <th class="text-end">Fails</th>
                                    <th>Summary</th>
                                </tr>
                            </thead>
                            <tbody id="gacVideoAiAuditHistoryBody"></tbody>
                        </table>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacVideoAiAuditErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacVideoAiAuditRunBtn">
                        <i class="fas fa-wand-magic-sparkles me-1"></i>Run AI audit
                    </button>
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
            const dataUrl = @json(route('google.youtube.ads.campaigns.data'));
            const gacRawRuleGetUrl = @json(route('google.youtube.ads.campaigns.rule'));
            const gacRawRuleSaveUrl = @json(route('google.youtube.ads.campaigns.rule.save'));
            const gacPauseRuleGetUrl = @json(route('google.youtube.ads.campaigns.pause.rule'));
            const gacPauseRuleSaveUrl = @json(route('google.youtube.ads.campaigns.pause.rule.save'));
            const gacVideoAuditGetUrl = @json(route('google.youtube.ads.campaigns.video.audit'));
            const gacVideoAuditSaveUrl = @json(route('google.youtube.ads.campaigns.video.audit.save'));
            const gacVideoAiAuditGetUrl = @json(route('google.youtube.ads.campaigns.video.ai.audit'));
            const gacVideoAiAuditRunUrl = @json(route('google.youtube.ads.campaigns.video.ai.audit.run'));
            const gacVideoAiPromptSaveUrl = @json(route('google.youtube.ads.campaigns.video.ai.prompt.save'));
            const gacRawPushSbgtUrl = @json(route('google.youtube.ads.campaigns.push.sbgt'));
            const gacRawPushPauseUrl = @json(route('google.youtube.ads.campaigns.push.pause'));
            const gacRawPauseScriptUrl = @json(route('google.youtube.ads.campaigns.pause.script'));
            const gacRawPullDataUrl = @json(route('google.shopping.campaigns.pull.data'));
            const gacRawBadgeHistoryUrl = @json(route('google.youtube.ads.campaigns.badge.history'));
            const gacRawSbgtHistoryUrl = @json(route('google.youtube.ads.campaigns.sbgt.history'));
            const gacRawU7PieDistribUrl = @json(route('google.youtube.ads.campaigns.u7.distribution'));
            const gacRawU7PieHistoryUrl = @json(route('google.youtube.ads.campaigns.u7.history'));
            const gacYtAttrSaveUrl = @json(route('google.youtube.ads.campaigns.attr.save'));
            const gacYtAttrOptionSaveUrl = @json(route('google.youtube.ads.campaigns.attr.option.save'));
            window.gacRawRule = @json($googleShoppingRule);
            window.gacPauseRule = @json($youtubePauseRule ?? ['enabled' => true, 'slabs' => [['spend_gt' => 30, 'acos_gt' => 50]]]);
            window.gacYtAttrOptions = @json($youtubeAttrOptions ?? null) || {
                category: ['B2B', 'B2C'],
                audience: ['shops', 'music schools', 'drumers', 'Guitarist', 'DJ'],
                landing: []
            };
            const TABULATOR_COLUMN_CHANNEL = 'google_youtube_ads_user_{{ auth()->id() ?? 'guest' }}';
            const TABULATOR_COLUMN_VISIBILITY_URL = @json(url('/tabulator-column-visibility'));
            const GAC_PERMANENTLY_HIDDEN_FIELDS = {
                id: true,
                campaign_id: true,
                date: true,
                sbgt_prev: true,
                sbgt_prev_date: true,
                sbgt_trend: true,
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
                l1_spend: true,
                sbid: true,
                video_audit_pct: true,
            };
            const GAC_DEFAULT_HIDDEN_FIELDS = {
                l7_spend: true,
                l2_spend: true,
                l1_spend: true,
                cpc_L7: true,
                cpc_L2: true,
                cpc_L1: true,
                ub7: true,
                ub2: true,
                ub1: true,
            };
            const COL_VIS_CATEGORY_KEYS = ['basics', 'lt', 'l30', 'others'];
            const COL_VIS_CATEGORY_LABELS = {
                basics: 'Basics',
                lt: 'LT',
                l30: 'L30',
                others: 'Others',
            };
            const COL_VIS_BASICS = {
                campaign_status: true, yt_category: true, yt_audience: true, yt_landing: true,
                campaign_name: true, is_parent: true,
                inventory: true, dil: true, price: true,
            };
            const COL_VIS_LT = {
                spend_lt: true, views_lt: true, clicks_lt: true, ctr_lt: true,
                cpc_lt: true, cps_lt: true, cpv_lt: true,
                sold_lt: true, sales_lt: true, acos_lt: true, cvr_lt: true,
            };
            const COL_VIS_L30 = {
                spend: true, video_views_L30: true, metrics_clicks: true, ctr_l30: true, cpc_L30: true,
                cps_L30: true, cpv_L30: true, ad_sold_L30: true, ad_sales_L30: true,
                acos_l30: true, cvr_l30: true,
            };
            const GAC_L30_METRIC_FIELDS = Object.assign({
                l7_spend: true, l2_spend: true, l1_spend: true,
                cpc_L7: true, cpc_L2: true, cpc_L1: true,
            }, COL_VIS_L30);
            const GAC_LT_METRIC_FIELDS = Object.assign({}, COL_VIS_LT);
            var gacMetricTab = (function() {
                try {
                    return localStorage.getItem('gac_youtube_metric_tab') === 'lt' ? 'lt' : 'l30';
                } catch (e) {
                    return 'l30';
                }
            })();
            let gacSavedColumnVisibility = {};
            let gacSavedColumnVisibilityLoaded = false;
            let gacColDropdownBuilt = false;
            let table;
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

            function gacCsrfToken() {
                return (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            }

            function gacClassifyColumn(field) {
                var f = String(field || '');
                if (Object.prototype.hasOwnProperty.call(COL_VIS_BASICS, f)) return 'basics';
                if (Object.prototype.hasOwnProperty.call(COL_VIS_LT, f)) return 'lt';
                if (Object.prototype.hasOwnProperty.call(COL_VIS_L30, f)) return 'l30';
                return 'others';
            }

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

            var gacColumnVisibilityReady = gacFetchColumnVisibility();

            function gacSyncGroupHeaderCheckbox(groupEl) {
                if (!groupEl) return;
                var headerCb = groupEl.querySelector('.col-vis-group-toggle');
                var itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                if (!headerCb || !itemCbs.length) return;
                var checked = 0;
                itemCbs.forEach(function(cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function gacColumnTitle(field) {
                var titles = {
                    campaign_status: 'Sts',
                    yt_category: 'Category',
                    yt_audience: 'Audience',
                    yt_landing: 'Landing',
                    campaign_name: 'Campaign',
                    is_parent: 'is_parent',
                    inventory: 'INV',
                    dil: 'Dil',
                    price: 'Price',
                    views_lt: 'LT views',
                    spend_lt: 'Spend LT',
                    sold_lt: 'Sold LT',
                    sales_lt: 'Sales LT',
                    acos_lt: 'ACOS LT',
                    cpc_lt: 'CPC LT',
                    cps_lt: 'CPS LT',
                    cpv_lt: 'CPV LT',
                    ctr_lt: 'CTR LT',
                    clicks_lt: 'Clicks LT',
                    cvr_lt: 'CVR LT',
                    spend: 'Spend',
                    video_views_L30: 'L30 Views',
                    metrics_clicks: 'Clicks L30',
                    ctr_l30: 'CTR',
                    cpc_L30: 'CPC',
                    cps_L30: 'CPS',
                    cpv_L30: 'CPV',
                    ad_sold_L30: 'Sold',
                    ad_sales_L30: 'Sales',
                    acos_l30: 'ACOS',
                    cvr_l30: 'CVR',
                    l7_spend: 'L7 Spend',
                    l2_spend: 'L2 Spend',
                    bgt: 'BGT',
                    sbgt: 'SBGT',
                    sbid: 'SBID',
                    action: 'Action',
                    video_audit_filled: 'Audit',
                    video_audit_ai_filled: 'Audit AI',
                };
                if (table) {
                    try {
                        var col = table.getColumn(field);
                        if (col) {
                            var t = col.getDefinition().title;
                            if (t && String(t).replace(/<[^>]*>/g, '').trim()) {
                                return String(t).replace(/<[^>]*>/g, '').trim();
                            }
                        }
                    } catch (e) { /* ignore */ }
                }
                return titles[field] || field;
            }

            function gacColumnIsVisible(field) {
                if (!table) {
                    return !Object.prototype.hasOwnProperty.call(GAC_DEFAULT_HIDDEN_FIELDS, field);
                }
                try {
                    var col = table.getColumn(field);
                    if (col) return col.isVisible();
                } catch (e) { /* ignore */ }
                if (gacSavedColumnVisibilityLoaded
                    && Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, field)) {
                    return gacSavedColumnVisibility[field] !== false;
                }
                return !Object.prototype.hasOwnProperty.call(GAC_DEFAULT_HIDDEN_FIELDS, field);
            }

            function gacCatalogFields() {
                var seen = {};
                var byCat = { basics: [], lt: [], l30: [], others: [] };
                function add(field) {
                    if (!field || field === '__gac_select' || seen[field]) return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) return;
                    seen[field] = true;
                    byCat[gacClassifyColumn(field)].push(field);
                }
                Object.keys(COL_VIS_BASICS).forEach(add);
                Object.keys(COL_VIS_LT).forEach(add);
                Object.keys(COL_VIS_L30).forEach(add);
                ['l7_spend', 'l2_spend', 'bgt', 'sbgt', 'video_audit_filled', 'video_audit_ai_filled', 'action'].forEach(add);
                if (table && typeof table.getColumns === 'function') {
                    try {
                        table.getColumns().forEach(function(col) {
                            var def = col.getDefinition ? col.getDefinition() : {};
                            add(def && def.field);
                        });
                    } catch (e) { /* ignore */ }
                }
                return byCat;
            }

            function buildColumnDropdown() {
                var menu = document.getElementById('column-dropdown-menu');
                if (!menu) return;

                var wrap = document.createElement('div');
                var showAll = document.createElement('a');
                showAll.className = 'dropdown-item py-1';
                showAll.href = '#';
                showAll.id = 'show-all-columns-btn';
                showAll.innerHTML = '<i class="fa fa-eye"></i> Show All';
                wrap.appendChild(showAll);

                var groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';
                var byCat = gacCatalogFields();
                var groupEls = {};

                COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                    var group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;

                    var titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    var groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.dataset.group = cat;
                    groupCb.title = 'Select / deselect all in ' + COL_VIS_CATEGORY_LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                    group.appendChild(titleEl);

                    var list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    (byCat[cat] || []).forEach(function(field) {
                        var li = document.createElement('li');
                        li.className = 'col-vis-item';
                        var label = document.createElement('label');
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = field;
                        checkbox.className = 'col-vis-field-toggle';
                        checkbox.dataset.group = cat;
                        checkbox.checked = gacColumnIsVisible(field);
                        var title = gacColumnTitle(field);
                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(title));
                        label.title = title;
                        li.appendChild(label);
                        list.appendChild(li);
                    });
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    groupEls[cat] = group;
                });

                COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                    gacSyncGroupHeaderCheckbox(groupEls[cat]);
                });

                wrap.appendChild(groupsWrap);
                menu.innerHTML = '';
                menu.appendChild(wrap);
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
                    if (gacIsInactiveMetricField(field)) {
                        if (Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, field)) {
                            visibility[field] = gacSavedColumnVisibility[field];
                        }
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

            function gacIsInactiveMetricField(field) {
                if (gacMetricTab === 'lt') {
                    return Object.prototype.hasOwnProperty.call(GAC_L30_METRIC_FIELDS, field);
                }
                return Object.prototype.hasOwnProperty.call(GAC_LT_METRIC_FIELDS, field);
            }

            function gacSyncMetricTabButtons() {
                document.querySelectorAll('[data-gac-tab]').forEach(function(btn) {
                    var on = btn.getAttribute('data-gac-tab') === gacMetricTab;
                    btn.classList.toggle('active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }

            function gacApplyMetricTab() {
                gacSyncMetricTabButtons();
                if (!table || typeof table.getColumns !== 'function') return;
                table.getColumns().forEach(function(col) {
                    var field = col.getDefinition().field;
                    if (!field || field === '__gac_select') return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) {
                        col.hide();
                        return;
                    }
                    if (gacIsInactiveMetricField(field)) {
                        col.hide();
                        return;
                    }
                    if (!Object.prototype.hasOwnProperty.call(GAC_L30_METRIC_FIELDS, field)
                        && !Object.prototype.hasOwnProperty.call(GAC_LT_METRIC_FIELDS, field)) {
                        return;
                    }
                    if (Object.prototype.hasOwnProperty.call(GAC_DEFAULT_HIDDEN_FIELDS, field)
                        && !(gacSavedColumnVisibilityLoaded && gacSavedColumnVisibility[field] === true)) {
                        col.hide();
                        return;
                    }
                    if (gacSavedColumnVisibilityLoaded
                        && Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, field)
                        && gacSavedColumnVisibility[field] === false) {
                        col.hide();
                        return;
                    }
                    col.show();
                });
                gacAutofitColumnsSoon();
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
                    if (gacIsInactiveMetricField(field)) {
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
                gacApplyMetricTab();
            }

            function gacAutofitColumns() {
                if (!table) return;
                try {
                    table.redraw(true);
                } catch (e) { /* ignore */ }
            }

            function gacComputeTableHeight() {
                var el = document.getElementById('google-ads-campaigns-raw-table');
                if (!el) return 650;
                var top = el.getBoundingClientRect().top;
                return Math.max(360, Math.floor(window.innerHeight - top - 16));
            }

            function gacFitTableHeightToPage() {
                if (!table || gacFitTableHeightToPage._lock) return;
                var next = gacComputeTableHeight();
                var current = (table.element && table.element.offsetHeight) ? table.element.offsetHeight : 0;
                if (Math.abs(current - next) < 12) return;
                gacFitTableHeightToPage._lock = true;
                try {
                    table.setHeight(next);
                } catch (e) { /* ignore */ }
                gacFitTableHeightToPage._lock = false;
            }

            function gacAutofitRowSize() {
                if (!table || typeof table.getRows !== 'function') return;
                var holder = table.element ? table.element.querySelector('.tabulator-tableholder') : null;
                if (!holder) return;
                var rows = table.getRows();
                var n = rows.length;
                if (!n) return;
                var available = holder.clientHeight;
                if (available < 40) return;
                var rowH = 32;
                if (n * 32 < available) {
                    rowH = Math.min(96, Math.max(32, Math.floor(available / n)));
                }
                table.options.rowHeight = rowH;
                rows.forEach(function(row) {
                    try {
                        var rel = row.getElement();
                        if (!rel) return;
                        rel.style.height = rowH + 'px';
                        rel.querySelectorAll('.tabulator-cell').forEach(function(cell) {
                            cell.style.height = rowH + 'px';
                        });
                    } catch (e) { /* ignore */ }
                });
            }

            function gacAutofitColumnsSoon() {
                clearTimeout(gacAutofitColumnsSoon._t);
                gacAutofitColumnsSoon._t = setTimeout(function() {
                    gacFitTableHeightToPage();
                    gacAutofitColumns();
                    gacAutofitRowSize();
                }, 60);
            }

            function gacEnsureColumnVisibilityUi() {
                var finish = function() {
                    applyColumnVisibilityFromCache();
                    buildColumnDropdown();
                };
                if (gacSavedColumnVisibilityLoaded) {
                    finish();
                } else {
                    gacColumnVisibilityReady.then(finish);
                }
            }

            // Action column: red alert triangle when a row's ACOS is above the
            // current average ACOS AND its Spend is over $30. Reads the live
            // gacRawAvgAcos so it re-evaluates as the badge changes.
            function gacRawActionFormatter(cell) {
                var row = cell.getRow().getData();
                var acos = gacMetricTab === 'lt' ? gacRawNumber(row.acos_lt) : gacRawNumber(row.acos_l30);
                var spend = gacMetricTab === 'lt' ? gacRawNumber(row.spend_lt) : gacRawNumber(row.spend);
                if (acos > gacRawAvgAcos && spend > 30) {
                    var tip = 'ACOS ' + Math.round(acos) + '% > avg ' + Math.round(gacRawAvgAcos)
                            + '% and Spend $' + Math.round(spend) + ' > $30';
                    return '<i class="fas fa-exclamation-triangle" title="' + tip + '"'
                         + ' style="color:#dc2626;font-size:15px;"></i>';
                }
                return '';
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
                var prev = row.sbgt_prev;
                var prevTxt = (prev === null || prev === undefined) ? '—' : Math.round(prev).toLocaleString();
                var tip = (trend === 'na')
                    ? 'No previous day saved yet — click for daily history'
                    : ('Prev (' + (row.sbgt_prev_date || '') + '): ' + prevTxt + ' → today ' + valTxt + ' — click for daily history');
                var cid = (row.campaign_id != null) ? String(row.campaign_id) : '';
                var dot = '<span class="gac-sbgt-dot" role="button" tabindex="0" data-sbgt-cid="' + gacRawEscAttr(cid) + '"'
                        + ' title="' + gacRawEscAttr(tip) + '"'
                        + ' style="display:inline-block;width:9px;height:9px;border-radius:50%;background:' + color + ';margin-left:6px;cursor:pointer;vertical-align:middle;flex-shrink:0;"></span>';
                return '<span style="display:inline-flex;align-items:center;justify-content:center;">' + valTxt + dot + '</span>';
            }

            function gacYtAttrKind(field) {
                if (field === 'yt_audience') return 'audience';
                if (field === 'yt_landing') return 'landing';
                return 'category';
            }

            function gacYtAttrOptionsFor(field) {
                var o = window.gacYtAttrOptions || {};
                if (field === 'yt_category') return o.category || ['B2B', 'B2C'];
                if (field === 'yt_audience') return o.audience || [];
                if (field === 'yt_landing') return o.landing || [];
                return [];
            }

            function gacYtAttrSelectHtml(cell, field) {
                var row = cell.getRow().getData() || {};
                var cid = row.campaign_id != null ? String(row.campaign_id) : '';
                var cur = cell.getValue() == null ? '' : String(cell.getValue());
                var allowAdd = field !== 'yt_category';
                var html = '<select class="gac-yt-attr" data-field="' + gacRawEscAttr(field) + '" data-cid="' + gacRawEscAttr(cid) + '">';
                html += '<option value="">—</option>';
                gacYtAttrOptionsFor(field).forEach(function(label) {
                    var sel = String(label) === cur ? ' selected' : '';
                    html += '<option value="' + gacRawEscAttr(label) + '"' + sel + '>' + gacEscHtml(label) + '</option>';
                });
                if (allowAdd) {
                    html += '<option value="__add__">+ Add…</option>';
                }
                html += '</select>';
                return html;
            }

            function gacYtAttrRefreshSelects() {
                if (!table || typeof table.getRows !== 'function') return;
                try {
                    table.getRows().forEach(function(row) { row.reformat(); });
                } catch (e) { /* ignore */ }
            }

            function gacYtAttrSave(cid, field, value, selectEl) {
                fetch(gacYtAttrSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': gacCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ campaign_id: cid, field: field, value: value }),
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        var b = out.body || {};
                        if (!out.ok || !b.success) throw new Error(b.error || 'Save failed.');
                        if (b.options) window.gacYtAttrOptions = b.options;
                        if (table && typeof table.getRows === 'function') {
                            table.getRows().forEach(function(row) {
                                var d = row.getData() || {};
                                if (String(d.campaign_id || '') !== String(cid)) return;
                                var patch = {};
                                patch[field] = value;
                                row.update(patch);
                            });
                        }
                    })
                    .catch(function(e) {
                        window.alert(e.message || 'Could not save.');
                        if (selectEl) selectEl.value = '';
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
                if (!response || typeof response !== 'object' || !response.summary) {
                    if (spiEl) spiEl.textContent = '—';
                    if (acosEl) acosEl.textContent = '—';
                    if (campaignsEl) campaignsEl.textContent = '0';
                    if (activeEl) activeEl.textContent = '0';
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
                    filter_ctr_min: gacRawRangeInputVal('gac-filter-ctr-min'),
                    filter_ctr_max: gacRawRangeInputVal('gac-filter-ctr-max'),
                    filter_cvr_min: gacRawRangeInputVal('gac-filter-cvr-min'),
                    filter_cvr_max: gacRawRangeInputVal('gac-filter-cvr-max'),
                    q: gacRawSearchQueryVal(),
                };
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
                // Capture phase: Tabulator's boolean tickCross editor swallows
                // bubble clicks, which blocked both Audit column cellClick handlers.
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest ? e.target.closest('.gac-audit-open') : null;
                    if (!btn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    var cid = btn.getAttribute('data-cid') || '';
                    var name = btn.getAttribute('data-cname') || '';
                    if (btn.getAttribute('data-gac-audit') === 'ai') {
                        gacOpenVideoAiAudit({ campaign_id: cid, campaign_name: name });
                    } else {
                        gacOpenVideoAudit(cid, name);
                    }
                }, true);
                document.addEventListener('mousedown', function (e) {
                    var sel = e.target.closest ? e.target.closest('.gac-yt-attr') : null;
                    if (!sel) return;
                    e.stopPropagation();
                }, true);
                document.addEventListener('click', function (e) {
                    var sel = e.target.closest ? e.target.closest('.gac-yt-attr') : null;
                    if (!sel) return;
                    e.stopPropagation();
                }, true);
                document.addEventListener('change', function (e) {
                    var sel = e.target && e.target.classList && e.target.classList.contains('gac-yt-attr') ? e.target : null;
                    if (!sel) return;
                    e.stopPropagation();
                    var field = sel.getAttribute('data-field') || '';
                    var cid = sel.getAttribute('data-cid') || '';
                    var value = sel.value || '';
                    if (!cid || !field) return;
                    if (value === '__add__') {
                        sel.value = '';
                        var label = window.prompt(field === 'yt_landing' ? 'Add landing option' : 'Add audience option');
                        if (label == null) return;
                        label = String(label).trim();
                        if (!label) return;
                        fetch(gacYtAttrOptionSaveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': gacCsrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ kind: gacYtAttrKind(field), label: label }),
                        })
                            .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                            .then(function(out) {
                                var b = out.body || {};
                                if (!out.ok || !b.success) throw new Error(b.error || 'Could not add option.');
                                if (b.options) window.gacYtAttrOptions = b.options;
                                gacYtAttrRefreshSelects();
                                gacYtAttrSave(cid, field, b.label || label, sel);
                            })
                            .catch(function(err) {
                                window.alert(err.message || 'Could not add option.');
                            });
                        return;
                    }
                    gacYtAttrSave(cid, field, value, sel);
                }, true);
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
                    if (val !== null && typeof val === 'object') {
                        gacRawAppendQueryParams(searchParams, val, name);
                    } else {
                        searchParams.set(name, val === null ? '' : String(val));
                    }
                });
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
                    gacRawAppendQueryParams(u.searchParams, params || {});
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
                // Viewport-fit height; JS grows this and stretches rows to fill leftover space.
                height: (function() {
                    var el = document.getElementById('google-ads-campaigns-raw-table');
                    if (!el) return 650;
                    var top = el.getBoundingClientRect().top;
                    return Math.max(360, Math.floor(window.innerHeight - top - 16));
                })(),
                layout: 'fitColumns',
                layoutColumnsOnNewData: true,
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
                            widthGrow: 0,
                        });
                    }
                    ['yt_category', 'yt_audience', 'yt_landing'].forEach(function(field) {
                        if (!defs.some(function(d) { return d.field === field; })) {
                            defs.push({ field: field, title: field === 'yt_category' ? 'Category' : (field === 'yt_audience' ? 'Audience' : 'Landing') });
                        }
                    });
                    (function gacPlaceYtAttrCols() {
                        var nameIdx = defs.findIndex(function(d) { return d.field === 'campaign_name'; });
                        if (nameIdx < 0) return;
                        ['yt_landing', 'yt_audience', 'yt_category'].forEach(function(field) {
                            var i = defs.findIndex(function(d) { return d.field === field; });
                            if (i < 0) return;
                            var col = defs.splice(i, 1)[0];
                            nameIdx = defs.findIndex(function(d) { return d.field === 'campaign_name'; });
                            defs.splice(nameIdx, 0, col);
                        });
                    })();
                    var moneySpendTitles = {
                        spend: 'Spend',
                        l7_spend: 'L7 Spend',
                        l2_spend: 'L2 Spend',
                        l1_spend: 'L1 Spend',
                        spend_lt: 'Spend LT',
                    };
                    var utilizedStyleTitles = {
                        views_lt: 'LT views',
                        video_views_L30: 'L30 Views',
                        clicks_lt: 'Clicks LT',
                        cpc_L30: 'CPC',
                        cpc_lt: 'CPC LT',
                        cps_L30: 'CPS',
                        cps_lt: 'CPS LT',
                        cpv_L30: 'CPV',
                        cpv_lt: 'CPV LT',
                        cvr_lt: 'CVR LT',
                        cpc_L7: 'L7 CPV',
                        cpc_L2: 'L2 CPV',
                        cpc_L1: 'L1 CPV',
                        ad_sold_L30: 'Sold',
                        ad_sales_L30: 'Sales',
                        acos_l30: 'ACOS',
                        sold_lt: 'Sold LT',
                        sales_lt: 'Sales LT',
                        acos_lt: 'ACOS LT',
                        cvr_l30: 'CVR',
                        ctr_l30: 'CTR',
                        ctr_lt: 'CTR LT',
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
                        spend_lt: true,
                        views_lt: true,
                        video_views_L30: true,
                        clicks_lt: true,
                        l7_spend: true,
                        l2_spend: true,
                        l1_spend: true,
                        metrics_clicks: true,
                        cpc_L30: true,
                        cpc_lt: true,
                        cps_L30: true,
                        cps_lt: true,
                        cpv_L30: true,
                        cpv_lt: true,
                        ad_sold_L30: true,
                        ad_sales_L30: true,
                        acos_l30: true,
                        spend_lt: true,
                        sold_lt: true,
                        sales_lt: true,
                        acos_lt: true,
                        cvr_l30: true,
                        cvr_lt: true,
                        ctr_l30: true,
                        ctr_lt: true,
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
                            col.minWidth = 120;
                            col.widthGrow = 4;
                            col.formatter = campaignNameFormatter;
                        } else if (col.field === 'yt_category' || col.field === 'yt_audience' || col.field === 'yt_landing') {
                            col.title = col.field === 'yt_category' ? 'Category' : (col.field === 'yt_audience' ? 'Audience' : 'Landing');
                            col.headerSort = false;
                            col.editor = false;
                            col.editable = false;
                            col.widthGrow = 0;
                            col.width = col.field === 'yt_category' ? 64 : 100;
                            col.minWidth = col.field === 'yt_category' ? 58 : 86;
                            col.formatter = function(c) { return gacYtAttrSelectHtml(c, col.field); };
                        } else if (col.field === 'campaign_status') {
                            col.minWidth = 36;
                            col.width = 36;
                            col.widthGrow = 0;
                            col.title = 'Sts';
                            col.formatter = campaignStatusFormatter;
                        } else {
                            col.minWidth = 34;
                            col.widthGrow = 1;
                        }
                        if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, col.field)) {
                            col.visible = false;
                        } else if (gacIsInactiveMetricField(col.field)) {
                            col.visible = false;
                        } else if (gacSavedColumnVisibilityLoaded
                            && Object.prototype.hasOwnProperty.call(gacSavedColumnVisibility, col.field)) {
                            col.visible = gacSavedColumnVisibility[col.field] !== false;
                        } else if (Object.prototype.hasOwnProperty.call(GAC_DEFAULT_HIDDEN_FIELDS, col.field)) {
                            col.visible = false;
                        }
                        if (Object.prototype.hasOwnProperty.call(moneySpendTitles, col.field)) {
                            col.title = moneySpendTitles[col.field];
                            col.formatter = moneyRoundedFormatter;
                        }
                        if (Object.prototype.hasOwnProperty.call(utilizedStyleTitles, col.field)) {
                            col.title = utilizedStyleTitles[col.field];
                            if (col.field === 'ad_sold_L30' || col.field === 'sold_lt'
                                || col.field === 'views_lt' || col.field === 'video_views_L30'
                                || col.field === 'clicks_lt') {
                                col.formatter = intLocaleFormatter;
                            } else if (col.field === 'ad_sales_L30' || col.field === 'sales_lt') {
                                col.formatter = moneyRoundedFormatter;
                            } else if (col.field === 'acos_l30' || col.field === 'acos_lt') {
                                col.formatter = acosFormatter;
                            } else if (col.field === 'cvr_l30' || col.field === 'cvr_lt') {
                                // CVR = (Sold / Clicks) * 100 — formatted with 1 decimal,
                                // matches the toolbar CVR badge value to the percent.
                                // Flag colour is relative to the filtered-set average CVR.
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) v = 0;
                                    gacRawApplyFlagColor(c.getElement(), v, gacRawAvgCvr);
                                    return v.toFixed(1) + '%';
                                };
                            } else if (col.field === 'ctr_l30' || col.field === 'ctr_lt') {
                                // CTR = (Clicks / Impressions) * 100 — 2 decimals. Flag colour
                                // is relative to the filtered-set average CTR.
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) v = 0;
                                    gacRawApplyFlagColor(c.getElement(), v, gacRawAvgCtr);
                                    return v.toFixed(2) + '%';
                                };
                            } else if (col.field === 'ub7' || col.field === 'ub2' || col.field === 'ub1') {
                                col.formatter = ubUtilColorFormatter;
                            } else if (col.field === 'sbgt') {
                                col.formatter = gacRawSbgtCellFormatter;
                            } else if (col.field === 'sbid') {
                                col.formatter = sbidFormatter;
                            } else if (col.field === 'bgt') {
                                col.formatter = moneyRoundedFormatter;
                            } else if (col.field === 'cpc_L30' || col.field === 'cpc_lt' || col.field === 'cps_L30'
                                || col.field === 'cps_lt' || col.field === 'cpv_L30' || col.field === 'cpv_lt'
                                || col.field === 'cpc_L7' || col.field === 'cpc_L2' || col.field === 'cpc_L1') {
                                col.formatter = function(c) {
                                    var v = parseFloat(c.getValue());
                                    if (!isFinite(v)) return '';
                                    return '$' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                };
                            } else {
                                col.formatter = moneyFormatter;
                            }
                        }
                        if (col.field === 'metrics_clicks') {
                            col.title = 'Clicks L30';
                            col.formatter = intLocaleFormatter;
                        }
                        if (col.field === 'clicks_lt') {
                            col.title = 'Clicks LT';
                            col.formatter = intLocaleFormatter;
                        }
                        if (col.field === 'price') {
                            col.title = 'Price';
                            col.formatter = moneyRoundedFormatter;
                        }
                    });
                    defs.forEach(function(col) {
                        if (col.field !== 'video_audit_filled' && col.field !== 'video_audit_ai_filled') return;
                        var isAi = col.field === 'video_audit_ai_filled';
                        col.title = isAi ? 'Audit AI' : 'Audit';
                        col.headerSort = !isAi;
                        col.hozAlign = 'center';
                        col.headerHozAlign = 'center';
                        col.width = isAi ? 48 : 44;
                        col.minWidth = isAi ? 40 : 40;
                        col.widthGrow = 0;
                        col.editor = false;
                        col.editable = false;
                        if (!isAi) {
                            col.sorter = function(a, b, aRow, bRow) {
                                var pa = parseInt((aRow.getData() || {}).video_audit_pct, 10);
                                var pb = parseInt((bRow.getData() || {}).video_audit_pct, 10);
                                if (!isFinite(pa)) pa = -1;
                                if (!isFinite(pb)) pb = -1;
                                return pa - pb;
                            };
                        }
                        col.formatter = function(c) {
                            var row = c.getRow().getData() || {};
                            var filled = !!c.getValue();
                            var cid = row.campaign_id != null ? String(row.campaign_id) : '';
                            var name = row.campaign_name != null ? String(row.campaign_name) : '';
                            var inner;
                            var tip;
                            if (isAi) {
                                var cls = filled ? 'is-filled' : 'is-empty';
                                tip = filled
                                    ? 'AI video audit saved. Click to review or re-run.'
                                    : 'No AI video audit yet. Click to analyze the video.';
                                inner = '<i class="fas fa-search gac-audit-icon ' + cls + '" aria-hidden="true"></i>';
                            } else {
                                var pct = parseInt(row.video_audit_pct, 10);
                                var hasPct = isFinite(pct);
                                var tone = !hasPct ? 'is-empty' : (pct >= 70 ? 'is-good' : (pct >= 50 ? 'is-mid' : 'is-bad'));
                                tip = hasPct
                                    ? ('Audit pass rate ' + pct + '%. Click to review or update.')
                                    : 'No video audit yet. Click to fill checkpoints.';
                                inner = '<span class="gac-audit-pct ' + tone + '">' + (hasPct ? (pct + '%') : '—') + '</span>';
                            }
                            return '<span class="gac-audit-open" role="button" tabindex="0"'
                                + ' data-gac-audit="' + (isAi ? 'ai' : 'manual') + '"'
                                + ' data-cid="' + gacRawEscAttr(cid) + '"'
                                + ' data-cname="' + gacRawEscAttr(name) + '"'
                                + ' title="' + gacRawEscAttr(tip) + '"'
                                + ' aria-label="' + gacRawEscAttr(tip) + '">'
                                + inner
                                + '</span>';
                        };
                        col.cellClick = function(e, cell) {
                            if (e) {
                                e.preventDefault();
                                e.stopPropagation();
                            }
                            var row = cell.getRow().getData() || {};
                            if (isAi) gacOpenVideoAiAudit(row);
                            else gacOpenVideoAudit(row.campaign_id, row.campaign_name);
                        };
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
                            width: 36,
                            minWidth: 34,
                            widthGrow: 0,
                            formatter: gacRawActionFormatter,
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

            ['gac-filter-ub7', 'gac-filter-ub1', 'gac-filter-acos', 'gac-filter-stat'].forEach(function(fid) {
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

            table.on('pageLoaded', function() {
                gacRawRefreshTableUiSoon();
                gacAutofitColumnsSoon();
            });
            table.on('dataLoaded', function() {
                gacRawRefreshTableUiSoon();
                gacEnsureColumnVisibilityUi();
                gacApplyMetricTab();
                gacAutofitColumnsSoon();
            });
            table.on('renderComplete', function() {
                gacAutofitRowSize();
            });
            window.addEventListener('resize', function() {
                gacAutofitColumnsSoon();
            });

            document.querySelectorAll('[data-gac-tab]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var next = this.getAttribute('data-gac-tab') === 'lt' ? 'lt' : 'l30';
                    if (next === gacMetricTab) return;
                    gacMetricTab = next;
                    try { localStorage.setItem('gac_youtube_metric_tab', gacMetricTab); } catch (e) { /* ignore */ }
                    gacApplyMetricTab();
                    buildColumnDropdown();
                    gacRawReformatActionColumn();
                });
            });
            gacSyncMetricTabButtons();

            var gacVideoAuditChecklist = [];

            function gacEscHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function gacVideoAuditTone(pct) {
                if (pct == null || !isFinite(pct)) return '';
                if (pct >= 70) return 'is-good';
                if (pct >= 50) return 'is-mid';
                return 'is-bad';
            }

            function gacVideoAuditCollectChecks() {
                var checks = {};
                document.querySelectorAll('#gacVideoAuditBody input[type="radio"]:checked').forEach(function(el) {
                    if (el.dataset.auditKey) checks[el.dataset.auditKey] = el.value;
                });
                return checks;
            }

            function gacVideoAuditTally(checks) {
                var pass = 0, fail = 0, na = 0;
                (gacVideoAuditChecklist || []).forEach(function(it) {
                    var v = checks[it.key] || '';
                    if (v === 'pass') pass++;
                    else if (v === 'fail') fail++;
                    else if (v === 'na') na++;
                });
                var scored = pass + fail;
                return {
                    pass: pass,
                    fail: fail,
                    na: na,
                    pct: scored > 0 ? Math.round((pass / scored) * 100) : null,
                };
            }

            function gacVideoAuditRefreshReport() {
                var tally = gacVideoAuditTally(gacVideoAuditCollectChecks());
                var scoreEl = document.getElementById('gacVideoAuditScore');
                var card = document.getElementById('gacVideoAuditScoreCard');
                var passEl = document.getElementById('gacVideoAuditPassCount');
                var failEl = document.getElementById('gacVideoAuditFailCount');
                var naEl = document.getElementById('gacVideoAuditNaCount');
                if (scoreEl) scoreEl.textContent = tally.pct == null ? '—' : (tally.pct + '%');
                if (card) {
                    card.classList.remove('is-good', 'is-mid', 'is-bad');
                    var tone = gacVideoAuditTone(tally.pct);
                    if (tone) card.classList.add(tone);
                }
                if (passEl) passEl.textContent = String(tally.pass);
                if (failEl) failEl.textContent = String(tally.fail);
                if (naEl) naEl.textContent = String(tally.na);
                document.querySelectorAll('#gacVideoAuditBody .gac-va-item').forEach(function(item) {
                    var checked = item.querySelector('input[type="radio"]:checked');
                    var v = checked ? checked.value : '';
                    item.classList.remove('is-fail', 'is-pass', 'is-na', 'is-blank');
                    item.classList.add(v === 'fail' ? 'is-fail' : (v === 'pass' ? 'is-pass' : (v === 'na' ? 'is-na' : 'is-blank')));
                });
            }

            function gacRenderVideoAuditChecklist(items, answers) {
                var box = document.getElementById('gacVideoAuditBody');
                if (!box) return;
                box.innerHTML = '';
                (items || []).forEach(function(it, idx) {
                    var key = it.key || ('q' + idx);
                    var ans = (answers && answers[key]) ? String(answers[key]) : '';
                    var item = document.createElement('div');
                    item.className = 'gac-va-item ' + (ans === 'fail' ? 'is-fail' : (ans === 'pass' ? 'is-pass' : (ans === 'na' ? 'is-na' : 'is-blank')));
                    item.innerHTML = ''
                        + '<div class="gac-va-q" title="' + gacEscHtml(it.help || '') + '">' + gacEscHtml(it.label || key) + '</div>'
                        + '<div class="gac-va-ans">'
                        + '<label><input type="radio" name="gac-va-' + gacEscHtml(key) + '" value="pass" data-audit-key="' + gacEscHtml(key) + '"' + (ans === 'pass' ? ' checked' : '') + '> Pass</label>'
                        + '<label><input type="radio" name="gac-va-' + gacEscHtml(key) + '" value="fail" data-audit-key="' + gacEscHtml(key) + '"' + (ans === 'fail' ? ' checked' : '') + '> Fail</label>'
                        + '<label><input type="radio" name="gac-va-' + gacEscHtml(key) + '" value="na" data-audit-key="' + gacEscHtml(key) + '"' + (ans === 'na' ? ' checked' : '') + '> N/A</label>'
                        + '</div>';
                    box.appendChild(item);
                });
                box.querySelectorAll('input[type="radio"]').forEach(function(el) {
                    el.addEventListener('change', gacVideoAuditRefreshReport);
                });
                gacVideoAuditRefreshReport();
            }

            function gacRenderVideoAuditHistory(rows) {
                var body = document.getElementById('gacVideoAuditHistoryBody');
                var empty = document.getElementById('gacVideoAuditHistoryEmpty');
                var wrap = document.getElementById('gacVideoAuditHistoryWrap');
                if (!body || !empty || !wrap) return;
                body.innerHTML = '';
                if (!rows || !rows.length) {
                    empty.classList.remove('d-none');
                    wrap.classList.add('d-none');
                    return;
                }
                empty.classList.add('d-none');
                wrap.classList.remove('d-none');
                rows.forEach(function(r) {
                    var checks = r.checks || {};
                    var pct = r.score_pct;
                    var tr = document.createElement('tr');
                    tr.style.cursor = 'pointer';
                    tr.title = 'Click to load this audit into the form';
                    tr.innerHTML = ''
                        + '<td>' + gacEscHtml(r.audited_at || '—') + '</td>'
                        + '<td class="fw-semibold">' + gacEscHtml(pct == null ? '—' : (pct + '%')) + '</td>'
                        + '<td class="text-end fw-semibold">' + gacEscHtml(r.fail_count != null ? r.fail_count : '—') + '</td>'
                        + '<td class="text-muted">' + gacEscHtml(r.audited_by_name || '—') + '</td>';
                    tr.addEventListener('click', function() {
                        gacRenderVideoAuditChecklist(gacVideoAuditChecklist, checks);
                        document.getElementById('gacVideoAuditComments').value = r.comments || '';
                    });
                    body.appendChild(tr);
                });
            }

            function gacOpenVideoAudit(cid, name) {
                cid = cid == null ? '' : String(cid);
                name = name == null ? '' : String(name);
                var modalEl = document.getElementById('gacVideoAuditModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                if (!cid) return;
                var err = document.getElementById('gacVideoAuditErr');
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                var cidInp = document.getElementById('gacVideoAuditCid');
                var nameInp = document.getElementById('gacVideoAuditCname');
                var cidLbl = document.getElementById('gacVideoAuditCampaignId');
                var nameLbl = document.getElementById('gacVideoAuditCampaignName');
                var comments = document.getElementById('gacVideoAuditComments');
                var body = document.getElementById('gacVideoAuditBody');
                if (cidInp) cidInp.value = cid;
                if (nameInp) nameInp.value = name;
                if (cidLbl) cidLbl.textContent = cid;
                if (nameLbl) nameLbl.textContent = name || cid;
                if (comments) comments.value = '';
                if (body) {
                    body.innerHTML = '<div class="small text-muted py-3 text-center">Loading…</div>';
                }

                fetch(gacVideoAuditGetUrl + '?campaign_id=' + encodeURIComponent(cid), {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        var b = out.body || {};
                        if (!out.ok || !b.success) {
                            throw new Error(b.error || 'Failed to load audit.');
                        }
                        gacVideoAuditChecklist = b.checklist || [];
                        gacRenderVideoAuditChecklist(gacVideoAuditChecklist, (b.latest && b.latest.checks) || {});
                        document.getElementById('gacVideoAuditComments').value = (b.latest && b.latest.comments) || '';
                        gacRenderVideoAuditHistory(b.history || []);
                    })
                    .catch(function(e) {
                        if (err) {
                            err.textContent = e.message || 'Failed to load audit.';
                            err.classList.remove('d-none');
                        }
                    });
            }

            var gacVideoAuditSaveBtn = document.getElementById('gacVideoAuditSaveBtn');
            if (gacVideoAuditSaveBtn) {
                gacVideoAuditSaveBtn.addEventListener('click', function() {
                    var err = document.getElementById('gacVideoAuditErr');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cid = document.getElementById('gacVideoAuditCid').value;
                    var cname = document.getElementById('gacVideoAuditCname').value;
                    var checks = gacVideoAuditCollectChecks();
                    var comments = (document.getElementById('gacVideoAuditComments').value || '').trim();
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    gacVideoAuditSaveBtn.disabled = true;
                    fetch(gacVideoAuditSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            campaign_id: cid,
                            campaign_name: cname,
                            checks: checks,
                            comments: comments,
                        }),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok || b.success === false) {
                                if (err) {
                                    err.textContent = b.error || b.message || 'Save failed.';
                                    err.classList.remove('d-none');
                                }
                                return;
                            }
                            if (table && typeof table.getRows === 'function') {
                                table.getRows().forEach(function(row) {
                                    var d = row.getData() || {};
                                    if (String(d.campaign_id || '') !== String(cid)) return;
                                    row.update({
                                        video_audit_filled: true,
                                        video_audit_pct: b.score_pct != null ? b.score_pct : null,
                                    });
                                });
                            }
                            var modalEl = document.getElementById('gacVideoAuditModal');
                            if (modalEl && typeof bootstrap !== 'undefined') {
                                var inst = bootstrap.Modal.getInstance(modalEl);
                                if (inst) inst.hide();
                            }
                        })
                        .catch(function() {
                            if (err) {
                                err.textContent = 'Network or server error.';
                                err.classList.remove('d-none');
                            }
                        })
                        .finally(function() { gacVideoAuditSaveBtn.disabled = false; });
                });
            }

            var gacVideoAiDefaultPrompt = '';
            var gacVideoAiCurrentRow = null;

            function gacCsrf() {
                return (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            }

            function gacRenderVideoAiResult(result) {
                var tbody = document.getElementById('gacVideoAiAuditBody');
                var sumEl = document.getElementById('gacVideoAiAuditSummary');
                var failEl = document.getElementById('gacVideoAiAuditFailCount');
                if (!tbody) return;
                var checks = (result && result.checks) ? result.checks : [];
                var fails = 0;
                tbody.innerHTML = '';
                if (!checks.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted small text-center py-3">Run AI to fill this table.</td></tr>';
                }
                checks.forEach(function(c) {
                    if (c.verdict === 'fail') fails++;
                    var color = c.verdict === 'fail' ? '#dc2626' : (c.verdict === 'pass' ? '#16a34a' : '#6b7280');
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="small fw-semibold">' + gacEscHtml(c.label || c.key) + '</td>'
                        + '<td class="small fw-bold" style="color:' + color + '">' + gacEscHtml((c.verdict || 'na').toUpperCase()) + '</td>'
                        + '<td class="small">' + gacEscHtml(c.error || '—') + '</td>'
                        + '<td class="small">' + gacEscHtml(c.reason || '—') + '</td>'
                        + '<td class="small">' + gacEscHtml(c.direction || '—') + '</td>';
                    tbody.appendChild(tr);
                });
                if (failEl) failEl.textContent = String(fails);
                if (sumEl) {
                    var summary = result && result.summary ? String(result.summary) : '';
                    if (summary) {
                        sumEl.textContent = summary;
                        sumEl.classList.remove('d-none');
                    } else {
                        sumEl.classList.add('d-none');
                    }
                }
            }

            function gacRenderVideoAiPromptHistory(rows) {
                var body = document.getElementById('gacVideoAiPromptHistoryBody');
                var empty = document.getElementById('gacVideoAiPromptHistoryEmpty');
                var wrap = document.getElementById('gacVideoAiPromptHistoryWrap');
                if (!body || !empty || !wrap) return;
                body.innerHTML = '';
                if (!rows || !rows.length) {
                    empty.classList.remove('d-none');
                    wrap.classList.add('d-none');
                    return;
                }
                empty.classList.add('d-none');
                wrap.classList.remove('d-none');
                rows.forEach(function(r) {
                    var preview = String(r.prompt || '').replace(/\s+/g, ' ').slice(0, 90);
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="small">' + gacEscHtml(r.created_at || '—') + '</td>'
                        + '<td class="small">' + gacEscHtml(r.saved_by_name || '—') + '</td>'
                        + '<td class="small text-muted">' + gacEscHtml(preview) + (String(r.prompt || '').length > 90 ? '…' : '') + '</td>'
                        + '<td><button type="button" class="btn btn-sm btn-outline-primary py-0 px-2">Use</button></td>';
                    tr.querySelector('button').addEventListener('click', function() {
                        document.getElementById('gacVideoAiAuditPrompt').value = r.prompt || '';
                    });
                    body.appendChild(tr);
                });
            }

            function gacRenderVideoAiHistory(rows) {
                var body = document.getElementById('gacVideoAiAuditHistoryBody');
                var empty = document.getElementById('gacVideoAiAuditHistoryEmpty');
                var wrap = document.getElementById('gacVideoAiAuditHistoryWrap');
                if (!body || !empty || !wrap) return;
                body.innerHTML = '';
                if (!rows || !rows.length) {
                    empty.classList.remove('d-none');
                    wrap.classList.add('d-none');
                    return;
                }
                empty.classList.add('d-none');
                wrap.classList.remove('d-none');
                rows.forEach(function(r) {
                    var summary = (r.result && r.result.summary) ? String(r.result.summary) : '';
                    var tr = document.createElement('tr');
                    tr.style.cursor = 'pointer';
                    tr.title = 'Click to load this AI result';
                    tr.innerHTML = ''
                        + '<td class="small">' + gacEscHtml(r.audited_at || '—') + '</td>'
                        + '<td class="small">' + gacEscHtml(r.audited_by_name || '—') + '</td>'
                        + '<td class="small">' + gacEscHtml(r.model || '—') + '</td>'
                        + '<td class="text-end small fw-semibold">' + gacEscHtml(r.fail_count != null ? r.fail_count : '—') + '</td>'
                        + '<td class="small text-muted">' + gacEscHtml(summary.slice(0, 120)) + (summary.length > 120 ? '…' : '') + '</td>';
                    tr.addEventListener('click', function() {
                        gacRenderVideoAiResult(r.result || {});
                        if (r.video_url) document.getElementById('gacVideoAiAuditUrl').value = r.video_url;
                        if (r.prompt_used) document.getElementById('gacVideoAiAuditPrompt').value = r.prompt_used;
                        document.getElementById('gacVideoAiAuditModel').textContent = r.model ? ('Model: ' + r.model) : '';
                    });
                    body.appendChild(tr);
                });
            }

            function gacOpenVideoAiAudit(row) {
                row = row || {};
                gacVideoAiCurrentRow = row;
                var cid = String(row.campaign_id || '');
                var name = String(row.campaign_name || '');
                var modalEl = document.getElementById('gacVideoAiAuditModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                if (!cid) return;
                var err = document.getElementById('gacVideoAiAuditErr');
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                var cidInp = document.getElementById('gacVideoAiAuditCid');
                var nameInp = document.getElementById('gacVideoAiAuditCname');
                var cidLbl = document.getElementById('gacVideoAiAuditCampaignId');
                var nameLbl = document.getElementById('gacVideoAiAuditCampaignName');
                var urlInp = document.getElementById('gacVideoAiAuditUrl');
                var modelLbl = document.getElementById('gacVideoAiAuditModel');
                var body = document.getElementById('gacVideoAiAuditBody');
                if (cidInp) cidInp.value = cid;
                if (nameInp) nameInp.value = name;
                if (cidLbl) cidLbl.textContent = cid;
                if (nameLbl) nameLbl.textContent = name || cid;
                if (urlInp) urlInp.value = '';
                if (modelLbl) modelLbl.textContent = '';
                if (body) {
                    body.innerHTML = '<tr><td colspan="5" class="text-muted small text-center py-3">Loading…</td></tr>';
                }

                fetch(gacVideoAiAuditGetUrl + '?campaign_id=' + encodeURIComponent(cid), {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        var b = out.body || {};
                        if (!out.ok || !b.success) throw new Error(b.error || 'Failed to load AI audit.');
                        gacVideoAiDefaultPrompt = b.default_prompt || '';
                        document.getElementById('gacVideoAiAuditPrompt').value = b.prompt || gacVideoAiDefaultPrompt;
                        gacRenderVideoAiPromptHistory(b.prompt_history || []);
                        gacRenderVideoAiHistory(b.history || []);
                        if (b.latest && b.latest.result) {
                            gacRenderVideoAiResult(b.latest.result);
                            document.getElementById('gacVideoAiAuditUrl').value = b.latest.video_url || '';
                            document.getElementById('gacVideoAiAuditModel').textContent = b.latest.model ? ('Model: ' + b.latest.model) : '';
                        } else {
                            gacRenderVideoAiResult({ checks: [], summary: '' });
                        }
                    })
                    .catch(function(e) {
                        if (err) {
                            err.textContent = e.message || 'Failed to load AI audit.';
                            err.classList.remove('d-none');
                        }
                    });
            }

            var gacVideoAiPromptReset = document.getElementById('gacVideoAiAuditPromptReset');
            if (gacVideoAiPromptReset) {
                gacVideoAiPromptReset.addEventListener('click', function() {
                    document.getElementById('gacVideoAiAuditPrompt').value = gacVideoAiDefaultPrompt || '';
                });
            }

            var gacVideoAiPromptSave = document.getElementById('gacVideoAiAuditPromptSave');
            if (gacVideoAiPromptSave) {
                gacVideoAiPromptSave.addEventListener('click', function() {
                    var err = document.getElementById('gacVideoAiAuditErr');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var prompt = (document.getElementById('gacVideoAiAuditPrompt').value || '').trim();
                    gacVideoAiPromptSave.disabled = true;
                    fetch(gacVideoAiPromptSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': gacCsrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ prompt: prompt }),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok || b.success === false) throw new Error(b.error || 'Failed to save prompt.');
                            document.getElementById('gacVideoAiAuditPrompt').value = b.prompt || prompt;
                            gacRenderVideoAiPromptHistory(b.prompt_history || []);
                        })
                        .catch(function(e) {
                            if (err) {
                                err.textContent = e.message || 'Failed to save prompt.';
                                err.classList.remove('d-none');
                            }
                        })
                        .finally(function() { gacVideoAiPromptSave.disabled = false; });
                });
            }

            var gacVideoAiRunBtn = document.getElementById('gacVideoAiAuditRunBtn');
            if (gacVideoAiRunBtn) {
                gacVideoAiRunBtn.addEventListener('click', function() {
                    var err = document.getElementById('gacVideoAiAuditErr');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cid = document.getElementById('gacVideoAiAuditCid').value;
                    var row = gacVideoAiCurrentRow || {};
                    var original = gacVideoAiRunBtn.innerHTML;
                    gacVideoAiRunBtn.disabled = true;
                    gacVideoAiRunBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Analyzing…';
                    fetch(gacVideoAiAuditRunUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': gacCsrf(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            campaign_id: cid,
                            campaign_name: document.getElementById('gacVideoAiAuditCname').value,
                            video_url: document.getElementById('gacVideoAiAuditUrl').value,
                            prompt: document.getElementById('gacVideoAiAuditPrompt').value,
                            spend_lt: row.spend_lt,
                            sales_lt: row.sales_lt,
                            sold_lt: row.sold_lt,
                            acos_lt: row.acos_lt,
                            views_lt: row.views_lt,
                            spend: row.spend,
                            ad_sales_L30: row.ad_sales_L30,
                            acos_l30: row.acos_l30,
                        }),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok || b.success === false) throw new Error(b.error || 'AI audit failed.');
                            gacRenderVideoAiResult(b.result || {});
                            document.getElementById('gacVideoAiAuditModel').textContent = b.model ? ('Model: ' + b.model) : '';
                            gacRenderVideoAiPromptHistory(b.prompt_history || []);
                            if (table && typeof table.getRows === 'function') {
                                table.getRows().forEach(function(r) {
                                    var d = r.getData() || {};
                                    if (String(d.campaign_id || '') !== String(cid)) return;
                                    r.update({ video_audit_ai_filled: true });
                                });
                            }
                            fetch(gacVideoAiAuditGetUrl + '?campaign_id=' + encodeURIComponent(cid), {
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            }).then(function(res) { return res.json(); }).then(function(body) {
                                if (body && body.history) gacRenderVideoAiHistory(body.history);
                            }).catch(function() {});
                        })
                        .catch(function(e) {
                            if (err) {
                                err.textContent = e.message || 'AI audit failed.';
                                err.classList.remove('d-none');
                            }
                        })
                        .finally(function() {
                            gacVideoAiRunBtn.disabled = false;
                            gacVideoAiRunBtn.innerHTML = original;
                        });
                });
            }

            var gacColMenu = document.getElementById('column-dropdown-menu');
            if (gacColMenu) {
                gacColMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var showAll = e.target.closest('#show-all-columns-btn');
                    if (!showAll || !table) return;
                    e.preventDefault();
                    table.getColumns().forEach(function(col) {
                        var field = col.getDefinition().field;
                        if (!field || field === '__gac_select') return;
                        if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) return;
                        if (gacIsInactiveMetricField(field)) return;
                        col.show();
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                    gacAutofitColumnsSoon();
                });
                gacColMenu.addEventListener('change', function(e) {
                    if (!table || e.target.type !== 'checkbox') return;
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        var checked = e.target.checked;
                        var groupEl = e.target.closest('.col-vis-group');
                        var itemCbs = groupEl
                            ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                            : [];
                        itemCbs.forEach(function(cb) {
                            cb.checked = checked;
                            var col = table.getColumn(cb.value);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        saveColumnVisibilityToServer();
                        gacAutofitColumnsSoon();
                        return;
                    }
                    var field = e.target.value;
                    if (!field) return;
                    var col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    gacSyncGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                    saveColumnVisibilityToServer();
                    gacAutofitColumnsSoon();
                });
            }

            var gacColDropdownBtn = document.getElementById('columnVisibilityDropdown');
            if (gacColDropdownBtn) {
                gacColDropdownBtn.addEventListener('mousedown', function() {
                    buildColumnDropdown();
                });
                gacColDropdownBtn.addEventListener('show.bs.dropdown', function() {
                    buildColumnDropdown();
                });
            }
            buildColumnDropdown();

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

            function gacCopyText(text, btn) {
                var value = String(text || '');
                if (!value) return;
                var done = function() {
                    if (!btn) return;
                    var orig = btn.getAttribute('data-orig-label') || btn.textContent;
                    btn.setAttribute('data-orig-label', orig);
                    btn.textContent = 'Copied';
                    setTimeout(function() { btn.textContent = orig; }, 1600);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(done).catch(function() {
                        window.prompt('Copy script:', value);
                    });
                    return;
                }
                window.prompt('Copy script:', value);
            }
            function gacRevealAdsScript(script, logText, message) {
                gacShowPushResult(message || 'Google Ads Script ready', logText, 'success');
                var inline = document.getElementById('gac-raw-ads-script-inline');
                var wrap = document.getElementById('gac-raw-ads-script-wrap');
                var copyBtn = document.getElementById('gac-raw-copy-ads-script');
                var modalTa = document.getElementById('gac-yt-pause-script-text');
                var modalLog = document.getElementById('gac-yt-pause-script-log');
                if (inline) inline.value = script || '';
                if (modalTa) modalTa.value = script || '';
                if (modalLog) modalLog.textContent = logText || '';
                if (wrap) wrap.classList.toggle('d-none', !script);
                if (copyBtn) copyBtn.classList.toggle('d-none', !script);
                var modalEl = document.getElementById('gacYtPauseScriptModal');
                if (script && modalEl && window.bootstrap && bootstrap.Modal) {
                    try {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } catch (e) {}
                }
            }
            var gacCopyPauseScriptBtn = document.getElementById('gac-yt-copy-pause-script');
            if (gacCopyPauseScriptBtn) {
                gacCopyPauseScriptBtn.addEventListener('click', function() {
                    var ta = document.getElementById('gac-yt-pause-script-text') || document.getElementById('gac-raw-ads-script-inline');
                    gacCopyText(ta ? ta.value : '', gacCopyPauseScriptBtn);
                });
            }
            var gacCopyAdsScriptBtn = document.getElementById('gac-raw-copy-ads-script');
            if (gacCopyAdsScriptBtn) {
                gacCopyAdsScriptBtn.addEventListener('click', function() {
                    var ta = document.getElementById('gac-raw-ads-script-inline') || document.getElementById('gac-yt-pause-script-text');
                    gacCopyText(ta ? ta.value : '', gacCopyAdsScriptBtn);
                });
            }
            var gacCopyQueuedBtn = document.getElementById('gac-raw-copy-queued-script');
            if (gacCopyQueuedBtn) {
                gacCopyQueuedBtn.addEventListener('click', function() {
                    var origHtml = gacCopyQueuedBtn.innerHTML;
                    gacCopyQueuedBtn.disabled = true;
                    gacCopyQueuedBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading…';
                    fetch(gacRawPauseScriptUrl, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                        .then(function(res) { return res.json(); })
                        .then(function(b) {
                            if (!b || !b.ads_script) {
                                window.alert((b && b.message) || 'No queued script. Click Push Pause first.');
                                return;
                            }
                            gacRevealAdsScript(b.ads_script, b.message || '', b.message || 'Queued Google Ads Script');
                            var ta = document.getElementById('gac-raw-ads-script-inline');
                            gacCopyText(ta ? ta.value : b.ads_script, gacCopyQueuedBtn);
                        })
                        .catch(function(err) {
                            window.alert(String(err && err.message ? err.message : err));
                        })
                        .finally(function() {
                            gacCopyQueuedBtn.innerHTML = origHtml;
                            gacCopyQueuedBtn.disabled = false;
                        });
                });
            }
            var gacCopyWatcherBtn = document.getElementById('gac-yt-copy-watcher-script');
            if (gacCopyWatcherBtn) {
                gacCopyWatcherBtn.addEventListener('click', function() {
                    var ta = document.getElementById('gac-yt-pause-watcher-script');
                    gacCopyText(ta ? ta.value : '', gacCopyWatcherBtn);
                });
            }

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
                var pauseB = document.getElementById('gac-raw-push-pause');
                if (sbgtB) sbgtB.disabled = true;
                if (pauseB) pauseB.disabled = true;
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
                        if (b.ads_script) {
                            gacRevealAdsScript(b.ads_script, text, b.message || title);
                        } else {
                            gacShowPushResult(title, text, success ? 'success' : 'error');
                        }
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
                        if (pauseB) pauseB.disabled = false;
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
                        loadingTitle: 'Pushing SBGT (Youtube ads)…',
                        loadingDetail: 'Updating budgets for ' + ids.length + ' campaign id(s). Waiting for Google Ads API — do not close this tab.',
                    });
                });
            }
            var pushPauseBtn = document.getElementById('gac-raw-push-pause');
            if (pushPauseBtn) {
                pushPauseBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s)')
                        : ('all ' + ids.length + ' row(s) on this page');
                    gacRunArtisanPush({
                        url: gacRawPushPauseUrl,
                        btn: pushPauseBtn,
                        campaign_ids: ids,
                        confirmMsg: 'Prepare Pause for ' + scope + '? ENABLED rows that hit the Pause Rule are queued. YouTube VIDEO campaigns must be paused with the Google Ads Script that opens next (the Ads API cannot pause VIDEO).',
                        loadingTitle: 'Preparing Pause…',
                        loadingDetail: 'Matching ' + ids.length + ' campaign id(s) to the Pause Rule. VIDEO campaigns will be queued for a Google Ads Script.',
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
                                        return fmtVal(ctx.parsed.y);
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
                                        return fmtVal(value);
                                    }
                                }
                            },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } }
                        }
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin]
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

            var gacCurrentPauseSlabs = [];

            function gacDefaultPauseSlabs() {
                return [{ spend_gt: 30, acos_gt: 50 }];
            }

            function gacNormalizePauseSlabs(slabs) {
                if (!Array.isArray(slabs) || !slabs.length) {
                    return gacDefaultPauseSlabs();
                }
                return slabs.map(function(s) {
                    return {
                        spend_gt: s && s.spend_gt != null ? Number(s.spend_gt) : 0,
                        acos_gt: s && s.acos_gt != null ? Number(s.acos_gt) : 0,
                    };
                });
            }

            function gacRenderPauseSlabs(slabs) {
                var tbody = document.getElementById('gac-pause-slabs-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                slabs.forEach(function(slab, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm"'
                        + ' value="' + (slab.spend_gt ?? '') + '" data-idx="' + i + '" data-field="spend_gt"'
                        + ' placeholder="30"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm"'
                        + ' value="' + (slab.acos_gt ?? '') + '" data-idx="' + i + '" data-field="acos_gt"'
                        + ' placeholder="50"></td>'
                        + '<td class="text-center">'
                        + '<button type="button" class="btn btn-sm btn-outline-danger px-2" data-remove-idx="' + i + '"'
                        + ' title="Delete this slab" aria-label="Delete slab">×</button></td>';
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('input[data-idx]').forEach(function(inp) {
                    inp.addEventListener('input', function() {
                        var idx = +this.dataset.idx;
                        var fld = this.dataset.field;
                        if (!gacCurrentPauseSlabs[idx]) return;
                        gacCurrentPauseSlabs[idx][fld] = this.value === '' ? '' : parseFloat(this.value);
                    });
                });

                tbody.querySelectorAll('[data-remove-idx]').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var idx = parseInt(this.getAttribute('data-remove-idx'), 10);
                        if (!isFinite(idx) || idx < 0) return;
                        gacCurrentPauseSlabs.splice(idx, 1);
                        if (!gacCurrentPauseSlabs.length) {
                            gacCurrentPauseSlabs = gacDefaultPauseSlabs();
                        }
                        gacRenderPauseSlabs(gacCurrentPauseSlabs);
                    });
                });
            }

            function gacFillPauseRuleForm(rule) {
                var r = rule && typeof rule === 'object' ? rule : {};
                var enabledEl = document.getElementById('gac-pause-rule-enabled');
                if (enabledEl) {
                    enabledEl.checked = r.enabled !== false;
                }
                gacCurrentPauseSlabs = gacNormalizePauseSlabs(r.slabs);
                gacRenderPauseSlabs(gacCurrentPauseSlabs);
            }

            function gacCollectPauseRulePayload() {
                var enabledEl = document.getElementById('gac-pause-rule-enabled');
                return {
                    enabled: !!(enabledEl && enabledEl.checked),
                    slabs: (gacCurrentPauseSlabs || []).map(function(s) {
                        return {
                            spend_gt: s.spend_gt === '' || s.spend_gt == null ? NaN : parseFloat(s.spend_gt),
                            acos_gt: s.acos_gt === '' || s.acos_gt == null ? NaN : parseFloat(s.acos_gt),
                        };
                    }),
                };
            }

            function gacRefreshPauseRuleFromServer(cb) {
                fetch(gacPauseRuleGetUrl, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        if (out.ok && out.body && out.body.rule) {
                            window.gacPauseRule = out.body.rule;
                        }
                        if (typeof cb === 'function') cb();
                    })
                    .catch(function() { if (typeof cb === 'function') cb(); });
            }

            var pauseAddSlabBtn = document.getElementById('gac-pause-add-slab-btn');
            if (pauseAddSlabBtn) {
                pauseAddSlabBtn.addEventListener('click', function() {
                    gacCurrentPauseSlabs.push({ spend_gt: 0, acos_gt: 0 });
                    gacRenderPauseSlabs(gacCurrentPauseSlabs);
                });
            }

            var pauseModalEl = document.getElementById('gacRawPauseRuleModal');
            if (pauseModalEl) {
                pauseModalEl.addEventListener('show.bs.modal', function() {
                    var errEl = document.getElementById('gacRawPauseRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    gacRefreshPauseRuleFromServer(function() {
                        gacFillPauseRuleForm(window.gacPauseRule || {});
                    });
                });
            }

            var pauseSaveBtn = document.getElementById('gacRawPauseRuleSaveBtn');
            if (pauseSaveBtn) {
                pauseSaveBtn.addEventListener('click', function() {
                    var errEl = document.getElementById('gacRawPauseRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    var payload = gacCollectPauseRulePayload();
                    if (!payload.slabs.length) {
                        if (errEl) {
                            errEl.textContent = 'Add at least one slab before saving.';
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    for (var i = 0; i < payload.slabs.length; i++) {
                        var s = payload.slabs[i];
                        if (!isFinite(s.spend_gt) || !isFinite(s.acos_gt)) {
                            if (errEl) {
                                errEl.textContent = 'Every slab needs numeric Spend LT and ACOS LT values.';
                                errEl.classList.remove('d-none');
                            }
                            return;
                        }
                    }
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    pauseSaveBtn.disabled = true;
                    fetch(gacPauseRuleSaveUrl, {
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
                            window.gacPauseRule = b.rule || window.gacPauseRule;
                            if (typeof bootstrap !== 'undefined' && pauseModalEl) {
                                var inst = bootstrap.Modal.getInstance(pauseModalEl);
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
                        .finally(function() { pauseSaveBtn.disabled = false; });
                });
            }
        });
    </script>
@endsection
