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
        /* SBID Rules — editable, live-previewed */
        #gacRawSbidRulesModal .modal-content { border: 0; border-radius: 14px; overflow: hidden; }
        #gacRawSbidRulesModal .modal-header {
            background: linear-gradient(120deg, #7c3aed 0%, #db2777 55%, #f97316 100%); color: #fff; border: 0; align-items: flex-start; padding: 14px 18px;
        }
        #gacRawSbidRulesModal .modal-title { font-weight: 800; color: #fff; letter-spacing: -0.01em; }
        #gacRawSbidRulesModal .btn-close { filter: invert(1) grayscale(1) brightness(2); opacity: 0.9; }
        #gacRawSbidRulesModal .gac-sbid-sub { color: rgba(255, 255, 255, 0.88); font-size: 12px; margin-top: 3px; line-height: 1.4; max-width: 640px; }
        #gacRawSbidRulesModal .modal-body { background: #f8fafc; padding: 14px 18px; }
        #gacRawSbidRulesModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; padding: 10px 18px; justify-content: space-between; }
        #gacRawSbidRulesModal .gac-sbid-dirty { font-size: 12px; font-weight: 700; color: #b45309; background: #fef3c7; border-radius: 999px; padding: 3px 10px; }
        #gacRawSbidRulesModal .gac-sbid-save { background: linear-gradient(120deg, #7c3aed, #db2777); border: 0; color: #fff; font-weight: 700; }
        #gacRawSbidRulesModal .gac-sbid-save:hover { filter: brightness(1.08); color: #fff; }
        #gacRawSbidRulesModal .gac-sbid-err { font-size: 12px; font-weight: 600; margin: 0 0 10px; }

        /* utilisation scale: shows where the two thresholds cut 0 → 100%+ */
        #gacRawSbidRulesModal .gac-sbid-scale { margin-bottom: 14px; }
        #gacRawSbidRulesModal .gac-sbid-scale-bar { display: flex; height: 26px; border-radius: 8px; overflow: hidden; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08); }
        #gacRawSbidRulesModal .gac-sbid-seg { display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; transition: flex-basis .25s ease; }
        #gacRawSbidRulesModal .gac-sbid-seg--under { background: linear-gradient(90deg, #ff2727, #fb7185); }
        #gacRawSbidRulesModal .gac-sbid-seg--none { background: linear-gradient(90deg, #64748b, #94a3b8); }
        #gacRawSbidRulesModal .gac-sbid-seg--over { background: linear-gradient(90deg, #ff01d0, #c026d3); }
        #gacRawSbidRulesModal .gac-sbid-scale-labels { display: flex; font-size: 11px; font-weight: 700; color: #64748b; margin-top: 3px; }

        #gacRawSbidRulesModal .gac-sbid-rule { border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; border: 1px solid transparent; border-left-width: 6px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.07); }
        #gacRawSbidRulesModal .gac-sbid-rule--over { background: linear-gradient(135deg, #fdf4ff, #fce7f3); border-color: #f5d0fe; border-left-color: #ff01d0; }
        #gacRawSbidRulesModal .gac-sbid-rule--under { background: linear-gradient(135deg, #fff7ed, #ffe4e6); border-color: #fecdd3; border-left-color: #ff2727; }
        #gacRawSbidRulesModal .gac-sbid-rule--none { background: linear-gradient(135deg, #f1f5f9, #e0f2fe); border-color: #cbd5e1; border-left-color: #64748b; }
        #gacRawSbidRulesModal .gac-sbid-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        #gacRawSbidRulesModal .gac-sbid-title { font-weight: 800; font-size: 14px; color: #0f172a; }
        #gacRawSbidRulesModal .gac-sbid-count { min-width: 150px; text-align: right; font-size: 11px; font-weight: 800; color: #334155; }
        #gacRawSbidRulesModal .gac-sbid-track { height: 6px; border-radius: 999px; background: rgba(15, 23, 42, 0.1); margin-top: 3px; overflow: hidden; }
        #gacRawSbidRulesModal .gac-sbid-fill { height: 100%; border-radius: 999px; transition: width .3s ease; }
        #gacRawSbidRulesModal .gac-sbid-rule--over .gac-sbid-fill { background: linear-gradient(90deg, #ff01d0, #c026d3); }
        #gacRawSbidRulesModal .gac-sbid-rule--under .gac-sbid-fill { background: linear-gradient(90deg, #ff2727, #fb7185); }
        #gacRawSbidRulesModal .gac-sbid-rule--none .gac-sbid-fill { background: linear-gradient(90deg, #64748b, #94a3b8); }
        #gacRawSbidRulesModal .gac-sbid-when { font-size: 12.5px; color: #475569; margin-bottom: 8px; }
        #gacRawSbidRulesModal .gac-sbid-lines { display: grid; gap: 5px; }
        #gacRawSbidRulesModal .gac-sbid-line {
            display: flex; align-items: center; justify-content: space-between; gap: 10px; background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(15, 23, 42, 0.07); border-radius: 8px; padding: 6px 10px; font-size: 12.5px; color: #334155; transition: all .15s ease;
        }
        #gacRawSbidRulesModal .gac-sbid-line.is-hit { background: #fff; border-color: #7c3aed; box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.25); }
        #gacRawSbidRulesModal .gac-sbid-res { font-weight: 800; color: #0f172a; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; }
        #gacRawSbidRulesModal .gac-sbid-chg { font-size: 11px; font-weight: 800; border-radius: 999px; padding: 1px 7px; }
        #gacRawSbidRulesModal .gac-sbid-chg.is-up { background: #dcfce7; color: #166534; }
        #gacRawSbidRulesModal .gac-sbid-chg.is-down { background: #fee2e2; color: #991b1b; }
        #gacRawSbidRulesModal .gac-sbid-in {
            width: 64px; border: 1.5px solid #c4b5fd; border-radius: 6px; background: #fff; color: #5b21b6; font-weight: 800;
            font-size: 12.5px; padding: 1px 4px; text-align: center; font-variant-numeric: tabular-nums;
        }
        #gacRawSbidRulesModal .gac-sbid-in:focus { outline: 0; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2); }
        #gacRawSbidRulesModal .gac-sbid-bind { font-weight: 800; color: #5b21b6; }

        #gacRawSbidRulesModal .gac-sbid-try { border-radius: 12px; padding: 12px 14px; background: linear-gradient(135deg, #ede9fe, #fae8ff); border: 1px solid #ddd6fe; border-left: 6px solid #7c3aed; }
        #gacRawSbidRulesModal .gac-sbid-try-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin: 8px 0; }
        #gacRawSbidRulesModal .gac-sbid-try-grid label { font-size: 11px; font-weight: 700; color: #6d28d9; display: block; margin-bottom: 2px; }
        #gacRawSbidRulesModal .gac-sbid-try-grid input { width: 100%; }
        #gacRawSbidRulesModal .gac-sbid-try-out { font-size: 13px; font-weight: 700; color: #0f172a; }
        #gacRawSbidRulesModal .gac-sbid-try-out .gac-sbid-big { font-size: 18px; font-weight: 800; color: #7c3aed; }
        /* Column picker grouped into 5 columns (Basic / Bids / Utilization / BGT / Other); the header checkbox toggles the whole group. */
        .gac-col-menu { min-width: 0; padding: 6px 6px; }
        /* !important + id selector: the theme's `.dropdown-menu.show { display:block }` was winning and stacking the groups vertically. */
        #column-dropdown-menu.gac-col-menu.show { display: grid !important; grid-template-columns: repeat(5, max-content); align-items: start; gap: 0 10px; max-height: none; overflow: visible; }
        .gac-col-menu .gac-col-group { list-style: none; min-width: 0; }
        .gac-col-menu .gac-col-group-head {
            display: flex; align-items: center; gap: 6px; padding: 4px 10px; margin-bottom: 2px;
            font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
            color: #854d0e; background: #fef9c3; border: 1px solid #facc15; border-radius: 6px; cursor: pointer;
        }
        .gac-col-menu .dropdown-item { padding: 3px 10px; white-space: nowrap; }
        @media (max-width: 991.98px) {
            #column-dropdown-menu.gac-col-menu.show { grid-template-columns: repeat(2, max-content); gap: 6px 10px; }
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
        #google-ads-campaigns-raw-wrap .gac-live-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        #google-ads-campaigns-raw-wrap .gac-live-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            flex-shrink: 0;
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
            padding: 4px 8px;
            flex: 1 1 auto;
            min-width: 0;
        }
        /* label sits beside its control so the whole bar is one line tall */
        #gac-raw-filter-bar .gac-raw-filter-field { display: flex; align-items: center; gap: 4px; }
        #gac-raw-filter-bar .gac-raw-filter-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0;
            letter-spacing: 0.01em;
            white-space: nowrap;
        }
        #gac-raw-filter-bar .gac-raw-range-input {
            width: 52px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            font-size: 0.8125rem;
            padding: 0.2rem 0.25rem;
            text-align: center;
        }
        #gac-raw-filter-bar .gac-raw-range-sep {
            color: #94a3b8;
            font-weight: 600;
        }
        .gac-fix-btn { padding: 1px 8px; font-size: 11px; font-weight: 700; color: #fff; background: #f97316; border: 1px solid #ea580c; border-radius: 6px; line-height: 1.5; }
        .gac-fix-btn:hover { background: #ea580c; color: #fff; }
        .gac-status-switch { margin: 0; padding: 0; min-height: 0; display: flex; justify-content: center; align-items: center; }
        .gac-status-switch .form-check-input { margin: 0; float: none; cursor: pointer; width: 2.1em; height: 1.1em; }
        .gac-status-switch .form-check-input:checked { background-color: #16a34a; border-color: #16a34a; }
        .gac-status-switch .form-check-input:disabled { cursor: not-allowed; opacity: .45; }
        #gac-filter-ver.gac-alert-match { background: #dcfce7; border-color: #16a34a; color: #166534; font-weight: 600; }
        #gac-filter-ver.gac-alert-mismatch { background: #fee2e2; border-color: #dc2626; color: #991b1b; font-weight: 600; }
        #gac-filter-idst.gac-alert-matched { background: #dcfce7; border-color: #16a34a; color: #166534; font-weight: 600; }
        #gac-filter-idst.gac-alert-missing { background: #fef9c3; border-color: #eab308; color: #854d0e; font-weight: 600; }
        #gac-filter-idst.gac-alert-mismatch { background: #fee2e2; border-color: #dc2626; color: #991b1b; font-weight: 600; }
        #gac-raw-filter-bar .gac-raw-filter-select {
            width: 86px;
            min-width: 0;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #64748b;
            font-size: 0.8125rem;
            padding: 0.2rem 1.35rem 0.2rem 0.4rem;
            background-size: 12px 9px;
            background-position: right 0.4rem center;
        }
        #gac-raw-filter-bar #gac-filter-stat { width: 120px; }
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

        /* ── Summary cards (same look as the All Marketplace "Yesterday / L30" cards): yellow card + title + equal-size badge tiles ── */
        .faas-stat-badge--util-green { background: #05bd30; }  /* matches the grid's green UB% cells */
        .faas-stat-badge--util-yellow { background: #facc15; color: #713f12; }  /* 0.1–66% */
        .faas-stat-badge--util-red  { background: #ff2727; }   /* matches the grid's red UB% cells  */
        .faas-stat-badge--util-pink { background: #ff01d0; }   /* matches the grid's pink UB% cells */
        .gac-cards {
            display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 0.5rem; align-items: start; margin-bottom: 0.5rem;
        }
        .gac-card {
            min-width: 0; border-radius: 12px; padding: 0.55rem 0.6rem 0.65rem;
            background: #fef9c3; border: 1px solid #facc15; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        /* One row on wide screens: Yesterday 4 · L30 4 · Utilized Y 2 · Utilized L7 2 (of 12). Narrower screens wrap (media queries below). */
        .gac-card--yesterday, .gac-card--l30, .gac-card--projected { grid-column: span 4; }
        .gac-card--util { grid-column: span 2; }
        .gac-card--util .gac-card-badges { grid-template-columns: minmax(0, 1fr); }
        .gac-card-title {
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase;
            line-height: 1; margin: 0 0 0.35rem; color: #854d0e;
        }
        .gac-card-badges { display: grid; gap: 0.4rem; align-items: stretch; grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .gac-card .faas-stat-badge {
            width: 100%; min-width: 0; min-height: 36px; box-sizing: border-box; justify-content: center; text-align: center;
            font-size: 0.78rem; line-height: 1.2; padding: 0.3rem 0.35rem; border-radius: 0.375rem; white-space: nowrap;
        }
        .gac-card .faas-stat-badge.faas-stat-badge--util-yellow { color: #713f12; }
        .faas-stat-badge--util-total { background: #8b5cf6; color: #fff; }   /* purple */
        .gac-card .faas-stat-badge.faas-stat-badge--util-total { color: #fff; }
        .gac-card .faas-stat-badge > span { font-size: 0.85rem; }
        .gac-card .faas-stat-badge > .gac-util-pct { font-size: 0.75rem; font-weight: 700; opacity: 0.95; margin-left: 5px; }
        @media (max-width: 1399.98px) {
            .gac-card--yesterday, .gac-card--l30, .gac-card--projected, .gac-card--util { grid-column: span 6; }
            .gac-card--util .gac-card-badges { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 991.98px) {
            .gac-card--yesterday, .gac-card--l30, .gac-card--projected, .gac-card--util { grid-column: span 12; }
        }
        @media (max-width: 575.98px) {
            .gac-card--util { grid-column: span 12; }
            .gac-card-badges, .gac-card--util .gac-card-badges { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        /* KPI tiles take the dashboard trend colour (dot up = green, down = red, same = yellow, none = grey). */
        /* Tiles in the Yesterday / L30 / Projected cards are white. Yesterday and Projected turn green when better than the L30 average, red otherwise. */
        .gac-card--yesterday .faas-stat-badge, .gac-card--l30 .faas-stat-badge, .gac-card--projected .faas-stat-badge {
            background: #fff; color: #111827; border: 1px solid #e5e7eb;
        }
        .gac-card .faas-stat-badge.gac-ads-needed { background: #7c3aed; color: #fff; border-color: #6d28d9; }
        .gac-card .faas-stat-badge.gac-cmp-better { background: #22c55e; color: #052e16; border-color: #16a34a; }
        .gac-card .faas-stat-badge.gac-cmp-worse  { background: #ef4444; color: #fff; border-color: #dc2626; }
        /* Dashboard-standard history dot: green = up, red = down, yellow = same, grey = no prior day. */
        .gac-cards .summary-trend-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; flex-shrink: 0;
            cursor: pointer; position: relative; z-index: 2; box-shadow: 0 0 0 1.5px rgba(255, 255, 255, 0.9);
            background: #9ca3af;
        }
        .gac-cards .summary-trend-dot:hover { transform: scale(1.3); box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25); }
        .gac-cards .summary-trend-dot.up { background: #22c55e; }
        .gac-cards .summary-trend-dot.down { background: #ef4444; }
        .gac-cards .summary-trend-dot.flat { background: #eab308; }
        .gac-cards .summary-trend-dot.none { background: #9ca3af; }

        /* ── BGT Rules — single modal (layout matches the "Std prc vs dil" modal: one row of rule cards) ── */
        #gacRawBgtRulesModal .modal-dialog {
            width: calc(100vw - 1.25rem);
            max-width: calc(100vw - 1.25rem);
            height: calc(100vh - 1.25rem);
            max-height: calc(100vh - 1.25rem);
            margin: 0.625rem auto;
        }
        #gacRawBgtRulesModal .modal-content {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #gacRawBgtRulesModal .modal-header { background: #fff; border-bottom: 1px solid #e8eef5; padding: 14px 18px; flex: 0 0 auto; align-items: flex-start; }
        #gacRawBgtRulesModal .modal-title { font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
        #gacRawBgtRulesModal .gac-bgt-sub { color: #64748b; font-size: 12px; font-weight: 500; margin-top: 2px; line-height: 1.4; }
        #gacRawBgtRulesModal .modal-body {
            background: #f4f7fb; overflow-x: hidden; overflow-y: auto; padding: 14px 16px 16px;
            flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column;
        }
        #gacRawBgtRulesModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; padding: 10px 18px; flex: 0 0 auto; justify-content: flex-end; }
        #gacRawBgtRulesModal #gacRawBgtRulesSaveBtn {
            font-weight: 700; border-radius: 8px; padding: 7px 16px;
            background: #2bb3a6; border-color: #2bb3a6; color: #fff;
        }
        #gacRawBgtRulesModal #gacRawBgtRulesSaveBtn:hover,
        #gacRawBgtRulesModal #gacRawBgtRulesSaveBtn:focus,
        #gacRawBgtRulesModal #gacRawBgtRulesSaveBtn:active { background: #239b8f; border-color: #239b8f; color: #fff; }
        #gacRawBgtRulesModal .gac-bgt-global-err { margin: 0 0 8px; }

        /* All rule cards sit side by side in one row (scrolls sideways on narrow screens). */
        #gacRawBgtRulesModal .gac-bgt-cols {
            display: flex; flex-wrap: nowrap; align-items: stretch; align-content: flex-start;
            gap: 12px; width: 100%; min-width: 0; flex: 1 1 auto; min-height: 0; overflow-x: auto; padding-bottom: 4px;
        }
        #gacRawBgtRulesModal .gac-bgt-col {
            flex: 1 1 0;
            min-width: 230px; min-height: 0;
            display: flex; flex-direction: column;
            border: 1px solid #e6edf5; border-radius: 14px; padding: 12px; background: #fff;
            overflow-x: hidden; overflow-y: auto;
        }
        #gacRawBgtRulesModal .gac-bgt-col.gac-bgt-col--wide { flex: 1.35 1 0; min-width: 300px; }
        #gacRawBgtRulesModal .gac-bgt-col-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 8px; }
        #gacRawBgtRulesModal .gac-bgt-title {
            font-weight: 700; font-size: 13px; color: #0f172a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        #gacRawBgtRulesModal .gac-bgt-head-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        #gacRawBgtRulesModal .gac-bgt-min-btn,
        #gacRawBgtRulesModal .gac-bgt-exp-btn {
            border: 0; background: #f1f5f9; color: #334155; width: 24px; height: 24px; border-radius: 6px;
            padding: 0; line-height: 1; font-weight: 700; flex-shrink: 0; font-size: 14px;
        }
        #gacRawBgtRulesModal .gac-bgt-min-btn:hover,
        #gacRawBgtRulesModal .gac-bgt-exp-btn:hover { background: #e2e8f0; }
        #gacRawBgtRulesModal .gac-bgt-col.is-min .gac-bgt-exp-btn { display: none; }
        #gacRawBgtRulesModal .gac-bgt-cols.is-expanded .gac-bgt-col:not(.is-max) { display: none; }
        #gacRawBgtRulesModal .gac-bgt-col.is-max { flex: 1 1 100%; min-width: 0; max-width: none; }
        #gacRawBgtRulesModal .gac-bgt-col.is-max .gac-bgt-chart-canvas { height: 240px; flex: 0 0 240px; }
        #gacRawBgtRulesModal .gac-bgt-col.is-max .gac-bgt-chart-canvas canvas { height: 240px !important; }
        #gacRawBgtRulesModal .gac-bgt-col.is-min { flex: 0 0 40px; width: 40px; max-width: 40px; min-width: 40px; padding: 10px 4px; cursor: pointer; }
        #gacRawBgtRulesModal .gac-bgt-col.is-min > :not(.gac-bgt-col-head) { display: none !important; }
        #gacRawBgtRulesModal .gac-bgt-col.is-min .gac-bgt-col-head { flex-direction: column; justify-content: flex-start; height: 100%; margin: 0; gap: 10px; }
        #gacRawBgtRulesModal .gac-bgt-col.is-min .gac-bgt-title { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }

        #gacRawBgtRulesModal .gac-bgt-chart { display: flex; flex-direction: column; margin-bottom: 8px; flex: 0 0 auto; }
        #gacRawBgtRulesModal .gac-bgt-chart-canvas { position: relative; width: 100%; height: 110px; flex: 0 0 110px; }
        #gacRawBgtRulesModal .gac-bgt-chart-canvas canvas { display: block; width: 100% !important; height: 110px !important; }
        #gacRawBgtRulesModal .gac-bgt-legend { width: 100%; font-size: 12px; margin: 8px 0 0; color: #334155; line-height: 1.3; }
        #gacRawBgtRulesModal .gac-bgt-leg-row {
            display: grid; grid-template-columns: 8px minmax(0, 1fr) auto auto; gap: 6px; align-items: center; padding: 2px 0;
        }
        #gacRawBgtRulesModal .gac-bgt-leg-row strong { font-variant-numeric: tabular-nums; }
        #gacRawBgtRulesModal .gac-bgt-leg-total { font-weight: 700; border-top: 1px solid #e2e8f0; margin-top: 3px; padding-top: 4px; }
        #gacRawBgtRulesModal .gac-bgt-leg-pct { color: #94a3b8; font-variant-numeric: tabular-nums; min-width: 2.2rem; text-align: right; }
        #gacRawBgtRulesModal .gac-bgt-swatch { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        #gacRawBgtRulesModal .gac-bgt-col .table { font-size: 12px; margin-bottom: 0; border-color: transparent; --bs-table-bg: transparent; }
        #gacRawBgtRulesModal .gac-bgt-col .table thead th {
            background: #eaf1fb; color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase; border: 0; white-space: nowrap; text-align: center; padding: 6px 6px;
        }
        #gacRawBgtRulesModal .gac-bgt-col .table th,
        #gacRawBgtRulesModal .gac-bgt-col .table td { padding: 5px 6px; border-width: 0; vertical-align: middle; text-align: center; }
        #gacRawBgtRulesModal .gac-bgt-col .table th:first-child,
        #gacRawBgtRulesModal .gac-bgt-col .table td:first-child { display: none; }
        /* Boxed number inputs, same as the Std prc vs dil modal. */
        #gacRawBgtRulesModal .gac-bgt-col .table .form-control-sm {
            width: 100%; min-width: 52px; max-width: 72px; height: 30px; margin: 0 auto; padding: 0 4px;
            font-size: 13px; font-weight: 600; line-height: 24px; text-align: center; color: #0f172a;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 7px; box-shadow: none;
            -moz-appearance: textfield; appearance: textfield;
        }
        #gacRawBgtRulesModal .gac-bgt-col .table .form-control-sm::-webkit-outer-spin-button,
        #gacRawBgtRulesModal .gac-bgt-col .table .form-control-sm::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        #gacRawBgtRulesModal .gac-bgt-col .table .form-control-sm:focus { border-color: #2bb3a6; box-shadow: 0 0 0 2px rgba(43, 179, 166, 0.18); }
        #gacRawBgtRulesModal .gac-bgt-col .btn-outline-danger {
            border: 0; background: transparent; box-shadow: none; width: 22px; height: 22px; padding: 0; line-height: 1;
            font-size: 12px; border-radius: 6px; color: #dc3545;
        }
        #gacRawBgtRulesModal .gac-bgt-col .btn-outline-danger:hover,
        #gacRawBgtRulesModal .gac-bgt-col .btn-outline-danger:focus { border: 0; background: #fee2e2; color: #b91c1c; box-shadow: none; }
        #gacRawBgtRulesModal .gac-bgt-col .btn-outline-danger:disabled { border: 0; background: transparent; box-shadow: none; opacity: 0.35; }
        #gacRawBgtRulesModal .gac-bgt-add {
            width: 100%; border-radius: 8px; font-weight: 600; padding: 4px 8px; font-size: 13px; margin-top: auto; border-style: dashed;
        }
        #gacRawBgtRulesModal .gac-bgt-col .table-responsive { margin-bottom: 0; flex: 0 0 auto; overflow: visible; }
        #gacRawBgtRulesModal .gac-bgt-col .gac-bgt-add { margin-top: 10px; }

        /* Sum card: last card in the row (right-hand side), same look as the rule cards. Data only, no chart. */
        #gacRawBgtRulesModal .gac-bgt-opts { display: flex; flex-wrap: wrap; gap: 10px 22px; align-items: center; margin: 0 0 12px; padding: 8px 12px; background: #fefce8; border: 1px solid #fde68a; border-radius: 8px; }
        #gacRawBgtRulesModal .gac-bgt-opt { display: inline-flex; align-items: center; gap: 6px; margin: 0; font-size: 13px; font-weight: 700; color: #854d0e; cursor: pointer; }
        #gacRawBgtRulesModal .gac-bgt-opt input[type="number"] { width: 70px; text-align: center; font-weight: 700; }
        #gacRawBgtRulesModal .gac-bgt-col.gac-bgt-col--sum { flex: 1.4 1 0; min-width: 330px; }
        #gacRawBgtRulesModal .gac-bgt-sum-dist-head {
            display: grid; grid-template-columns: minmax(0, 1fr) 62px 62px 42px; gap: 6px; padding: 7px 8px 7px 22px;
            background: #eaf1fb; border-radius: 8px 8px 0 0; color: #64748b; font-size: 10px; font-weight: 700;
            letter-spacing: 0.04em; text-transform: uppercase;
        }
        #gacRawBgtRulesModal .gac-bgt-sum-dist-head span:nth-child(n+2):nth-child(-n+3) { text-align: center; }
        #gacRawBgtRulesModal .gac-bgt-sum-dist-head span:nth-child(4) { text-align: right; }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-legend {
            margin: 0; font-size: 13px; padding: 0 8px; background: #fff;
            border: 1px solid #e8eef5; border-top: 0; border-radius: 0 0 8px 8px;
        }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-leg-row {
            display: grid; grid-template-columns: 10px minmax(0, 1fr) 62px 62px 42px; gap: 6px; align-items: center;
            padding: 6px 0; border-bottom: 1px solid #eef2f7;
        }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-leg-row > span:nth-child(2) { font-weight: 700; color: #0f172a; }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-leg-row strong { font-size: 13px; text-align: center; color: #334155; }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-leg-pct { min-width: 0; font-size: 12px; }
        #gacRawBgtRulesModal .gac-bgt-col--sum .gac-bgt-leg-total {
            margin: 0 -8px; padding: 6px 8px; border-top: 0; border-bottom: 0; background: #eff6ff;
            grid-template-columns: 10px minmax(0, 1fr) 62px 62px 42px;
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
                    {{-- Summary cards (same look as the All Marketplace "Yesterday / L30" cards). Every tile is the same size.
                         Yesterday / L30 KPI tiles: BGT / Util / Avg Bid / CVR% / ACOS% / Clicks / Sold / Sales / Spend — Y = Yesterday (last completed US day).
                         Dot + tile colour = trend vs the prior day (green up, red down, yellow same, grey none; Avg Bid and ACOS are lower-is-better).
                         Click a tile or its dot for history. Filled by gacRawLoadKpiBadges() in the script below. --}}
                    <div class="gac-cards" id="gacSummaryCards">
                        @php
                            $gacKpiRows = [
                                ['tag' => 'Yesterday', 'items' => [
                                    ['y_bgt',  'Y BGT',        'Y BGT',           'faas-stat-badge--acos',  'Total daily budget of the campaigns, as of yesterday (last completed US day).'],
                                    ['y_util', 'Y UTIL',       'Y Utilization',   'faas-stat-badge--sold', 'Yesterday utilization = yesterday spend ÷ daily budget.'],
                                    ['y_bid',  'Y AVG BID',    'Y Avg Bid',       'faas-stat-badge--impr',  'Yesterday avg bid = yesterday spend ÷ clicks (avg CPC).'],
                                    ['y_cvr',  'Y CVR%',       'Y CVR%',          'faas-stat-badge--cvr',   'Yesterday CVR% = yesterday sold ÷ clicks.'],
                                    ['y_acos', 'Y ACOS%',      'Y ACOS%',         'faas-stat-badge--clk',   'Yesterday ACOS% = yesterday spend ÷ sales.'],
                                    ['y_clicks', 'Y CLICKS',   'Y Clicks',        'faas-stat-badge--impr',  'Yesterday clicks = total clicks of the campaigns on the last completed US day.'],
                                    ['y_sold', 'Y SOLD',       'Y Sold',          'faas-stat-badge--clk',   'Yesterday sold = units sold by the campaigns on the last completed US day.'],
                                    ['y_sales', 'Y SALES',     'Y Sales',         'faas-stat-badge--spend', 'Yesterday sales = sales of the campaigns on the last completed US day.'],
                                    ['y_spend', 'Y SPEND',     'Y Spend',         'faas-stat-badge--spend', 'Yesterday spend = ad spend of the campaigns on the last completed US day.'],
                                ]],
                                ['tag' => 'L30', 'items' => [
                                    ['l30_bgt',  'DAILY BGT',  'L30 Daily BGT',  'faas-stat-badge--acos',  'Average daily budget over the last 30 days.'],
                                    ['l30_util', 'UTIL',       'L30 Utilization','faas-stat-badge--sold', 'L30 utilization = 30-day spend ÷ 30-day budget.'],
                                    ['l30_bid',  'AVG BID',    'L30 Avg Bid',    'faas-stat-badge--impr',  'L30 avg bid = 30-day spend ÷ clicks (avg CPC).'],
                                    ['l30_cvr',  'CVR%',       'L30 CVR%',       'faas-stat-badge--cvr',   'L30 CVR% = 30-day sold ÷ clicks.'],
                                    ['l30_acos', 'ACOS%',      'L30 ACOS%',      'faas-stat-badge--clk',   'L30 ACOS% = 30-day spend ÷ sales.'],
                                ]],
                                ['tag' => 'Projected', 'title' => 'Projected · L7 ÷ 7 × 30', 'items' => [
                                    ['p_bgt',   'DAILY BGT', 'Projected Daily BGT', 'faas-stat-badge--acos',  'Average daily budget over the last 7 days (L7 ÷ 7).'],
                                    ['p_util',  'UTIL',      'Projected Utilization','faas-stat-badge--sold', 'L7 utilization = 7-day spend ÷ 7-day budget (the ratio is the same when scaled to 30 days).'],
                                    ['p_bid',   'AVG BID',   'Projected Avg Bid',   'faas-stat-badge--impr',  'L7 avg bid = 7-day spend ÷ clicks (avg CPC).'],
                                    ['p_cvr',   'CVR%',      'Projected CVR%',      'faas-stat-badge--cvr',   'L7 CVR% = 7-day sold ÷ clicks.'],
                                    ['p_acos',  'ACOS%',     'Projected ACOS%',     'faas-stat-badge--clk',   'L7 ACOS% = 7-day spend ÷ sales.'],
                                    ['p_clicks','CLICKS',    'Projected Clicks',    'faas-stat-badge--impr',  'Projected 30-day clicks = L7 clicks ÷ 7 × 30 (campaigns shown).'],
                                    ['p_sold',  'SOLD',      'Projected Sold',      'faas-stat-badge--clk',   'Projected 30-day sold = L7 sold ÷ 7 × 30 (all campaigns).'],
                                    ['p_sales', 'SALES',     'Projected Sales',     'faas-stat-badge--spend', 'Projected 30-day sales = L7 sales ÷ 7 × 30 (all campaigns).'],
                                    ['p_spend', 'SPEND',     'Projected Spend',     'faas-stat-badge--spend', 'Projected 30-day spend = L7 spend ÷ 7 × 30 (all campaigns).'],
                                    ['p_bgt_total', 'TOTAL BGT', 'Projected Total BGT', 'faas-stat-badge--acos', 'Projected 30-day total budget = L7 daily budgets summed ÷ 7 × 30 (all campaigns).'],
                                ]],
                            ];
                        @endphp
                        @foreach ($gacKpiRows as $kpiRow)
                            <div class="gac-card gac-card--{{ strtolower($kpiRow['tag']) }}">
                                <div class="gac-card-title">{{ $kpiRow['title'] ?? $kpiRow['tag'] }}</div>
                                <div class="gac-card-badges">
                                    @foreach ($kpiRow['items'] as [$kpiKey, $kpiText, $kpiLabel, $kpiClass, $kpiTip])
                                        <span id="gacKpi_{{ $kpiKey }}" data-metric="{{ $kpiKey }}" data-label="{{ $kpiLabel }}" data-kpi="{{ $kpiKey }}"
                                            class="faas-stat-badge gac-trend-none badge-chart-link"
                                            title="{{ $kpiTip }} Click for history."><i class="summary-trend-dot none" data-kpi-dot="{{ $kpiKey }}" title="Click for history"></i>{{ $kpiText }}:<span class="gac-kpi-val">—</span></span>
                                    @endforeach
                                    @if ($kpiRow['tag'] === 'Yesterday')
                                        {{-- ACTIVE: enabled / all campaigns (every status) + share; from the server summary (gacRawSummaryFromResponse()), not a chart link. --}}
                                        <span id="faasActiveBadge" data-label="Active"
                                    class="faas-stat-badge faas-stat-badge--sales is-static"
                                    title="Enabled campaigns out of all campaigns (every status), and the share that is active.">ACTIVE:<span id="faasActiveValue">0 / 0</span><span class="gac-util-pct" id="faasActivePct">0%</span></span>
                                        {{-- ADS NEEDED: SKUs with INV > 0 and no running ad, across all enabled campaigns (purple). --}}
                                        <span id="gacAdsNeededBadge" data-label="Ads Needed"
                                    class="faas-stat-badge gac-ads-needed is-static"
                                    title="SKUs that have inventory but no running ad (all enabled campaigns).">ADS NEEDED:<span id="gacAdsNeededValue">—</span></span>
                                    @endif
                                    @if ($kpiRow['tag'] === 'L30')
                                        {{-- L30 CLICKS: sum of the visible rows' L30 clicks (updateMetricBadges()); opens the clicks history chart. --}}
                                        <span id="faasClicksBadge" data-metric="clicks" data-label="L30 Clicks"
                                            class="faas-stat-badge faas-stat-badge--impr badge-chart-link"
                                            title="L30 clicks of the campaigns shown. Click for trend.">CLICKS:<span id="faasClicksValue">0</span></span>
                                        {{-- L30 SOLD: units sold over 30 days, all campaigns incl. Paused / Removed (updateMetricBadges()); opens the sold history chart. --}}
                                        <span id="faasL30SoldBadge" data-metric="sold" data-label="L30 Sold"
                                            class="faas-stat-badge faas-stat-badge--clk badge-chart-link"
                                            title="L30 sold of all campaigns, Paused and Removed included (ignores the Sts filter). Click for trend.">SOLD:<span id="faasL30SoldValue">0</span></span>
                                        {{-- L30 SALES: 30-day sales, all campaigns incl. Paused / Removed (updateMetricBadges()); opens the sales history chart. --}}
                                        <span id="faasL30SalesBadge" data-metric="sales" data-label="L30 Sales"
                                            class="faas-stat-badge faas-stat-badge--spend badge-chart-link"
                                            title="L30 sales of all campaigns, Paused and Removed included (ignores the Sts filter). Click for trend.">SALES:<span id="faasL30SalesValue">$0</span></span>
                                        {{-- L30 SPEND: 30-day spend, all campaigns incl. Paused / Removed (updateMetricBadges()); opens the spend history chart. --}}
                                        <span id="faasL30SpendBadge" data-metric="spend" data-label="L30 Spend"
                                            class="faas-stat-badge faas-stat-badge--spend badge-chart-link"
                                            title="L30 spend of all campaigns, Paused and Removed included (ignores the Sts filter). Click for trend.">SPEND:<span id="faasL30SpendValue">0</span></span>
                                        {{-- TOTAL BGT: the daily budgets summed over the last 30 days, ALL campaigns (Active / Paused / Removed …).
                                             A KPI tile (filled by gacKpiPaint()): dot + colour = trend vs the prior day; click for history. --}}
                                        <span id="gacKpi_l30_bgt_total" data-metric="l30_bgt_total" data-label="L30 Total BGT" data-kpi="l30_bgt_total"
                                            class="faas-stat-badge gac-trend-none badge-chart-link"
                                            title="Daily budgets summed over the last 30 days, all campaigns (Active, Paused, Removed …). Click for history."><i class="summary-trend-dot none" data-kpi-dot="l30_bgt_total" title="Click for history"></i>TOTAL BGT:<span class="gac-kpi-val">—</span></span>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- Utilization (grid bands: green 66–99%, pink > 99%, red < 66%): Y = yesterday ("1 UB%"), L7 = "U7%". --}}
                        <div class="gac-card gac-card--util" id="gacUtilBlockY">
                            <div class="gac-card-title">Utilized Y</div>
                            <div class="gac-card-badges">
                                <span id="faasGreenUtilYBadge" data-metric="util_y_green" data-label="Green Util (Y)"
                                    class="faas-stat-badge faas-stat-badge--util-green badge-chart-link"
                                    title="Campaigns whose yesterday utilisation (1 UB%) is 66–99% — the green cells in the grid. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_y_green" title="Click for history"></i>UTIL (Y):<span id="faasGreenUtilYValue">0</span><span class="gac-util-pct" id="faasGreenUtilYPct" title="Share of all campaigns">0%</span></span>
                                <span id="faasYellowUtilYBadge" data-metric="util_y_yellow" data-label="Yellow Util (Y)"
                                    class="faas-stat-badge faas-stat-badge--util-yellow badge-chart-link"
                                    title="Campaigns whose yesterday utilisation (1 UB%) is 0.1% up to under 66%. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_y_yellow" title="Click for history"></i>UTIL (Y):<span id="faasYellowUtilYValue">0</span><span class="gac-util-pct" id="faasYellowUtilYPct" title="Share of all campaigns">0%</span></span>
                                <span id="faasRedUtilYBadge" data-metric="util_y_red" data-label="Red Util (Y)"
                                    class="faas-stat-badge faas-stat-badge--util-red badge-chart-link"
                                    title="Campaigns with 0% yesterday utilisation (1 UB%) — nothing spent, or no budget. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_y_red" title="Click for history"></i>UTIL (Y):<span id="faasRedUtilYValue">0</span><span class="gac-util-pct" id="faasRedUtilYPct" title="Share of all campaigns">0%</span></span>
                                <span id="faasPinkUtilYBadge" data-metric="util_y_pink" data-label="Pink Util (Y)"
                                    class="faas-stat-badge faas-stat-badge--util-pink badge-chart-link"
                                    title="Campaigns whose yesterday utilisation (1 UB%) is over 99% — the pink cells in the grid. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_y_pink" title="Click for history"></i>UTIL (Y):<span id="faasPinkUtilYValue">0</span><span class="gac-util-pct" id="faasPinkUtilYPct" title="Share of all campaigns">0%</span></span>
                                <span id="faasTotalUtilYBadge" data-metric="util_y_avg" data-label="Avg Util (Y)"
                                    class="faas-stat-badge faas-stat-badge--util-total badge-chart-link"
                                    title="Total active campaigns, and their average yesterday utilisation (1 UB%) = total spend ÷ total budget (campaigns with a budget)."><i class="summary-trend-dot none" data-util-dot="util_y_avg" title="Click for history"></i>TOTAL (Y):<span id="faasTotalUtilYValue">0</span><span class="gac-util-pct" id="faasAvgUtilYPct">avg 0%</span></span>

                            </div>
                        </div>
                        <div class="gac-card gac-card--util" id="gacUtilBlockL7">
                            <div class="gac-card-title">Utilized L7</div>
                            <div class="gac-card-badges">
                                <span id="faasGreenUtilL7Badge" data-metric="util_l7_green" data-label="Green Util (L7)"
                                    class="faas-stat-badge faas-stat-badge--util-green badge-chart-link"
                                    title="Campaigns with Green utilisation (L7) — U7% 66–99%. Click for history."><i class="summary-trend-dot none" data-util-dot="util_l7_green" title="Click for history"></i>UTIL (L7):<span id="faasGreenUtilL7Value">0</span><span class="gac-util-pct" id="faasGreenUtilL7Pct" title="Share of all campaigns">0%</span></span>
                                <span id="faasYellowUtilL7Badge" data-metric="util_l7_yellow" data-label="Yellow Util (L7)"
                                    class="faas-stat-badge faas-stat-badge--util-yellow badge-chart-link"
                                    title="Campaigns whose L7 utilisation (U7%) is 0.1% up to under 66%. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_l7_yellow" title="Click for history"></i>UTIL (L7):<span id="faasYellowUtilL7Value">0</span><span class="gac-util-pct" id="faasYellowUtilL7Pct" title="Share of all campaigns">0%</span></span>
                                <span id="faasRedUtilL7Badge" data-metric="util_l7_red" data-label="Red Util (L7)"
                                    class="faas-stat-badge faas-stat-badge--util-red badge-chart-link"
                                    title="Campaigns with 0% L7 utilisation (U7%) — nothing spent in 7 days, or no budget. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_l7_red" title="Click for history"></i>UTIL (L7):<span id="faasRedUtilL7Value">0</span><span class="gac-util-pct" id="faasRedUtilL7Pct" title="Share of all campaigns">0%</span></span>
                                <span id="faasPinkUtilL7Badge" data-metric="util_l7_pink" data-label="Pink Util (L7)"
                                    class="faas-stat-badge faas-stat-badge--util-pink badge-chart-link"
                                    title="Campaigns whose L7 utilisation (U7%) is over 99% — the pink cells in the grid. Active campaigns only."><i class="summary-trend-dot none" data-util-dot="util_l7_pink" title="Click for history"></i>UTIL (L7):<span id="faasPinkUtilL7Value">0</span><span class="gac-util-pct" id="faasPinkUtilL7Pct" title="Share of all campaigns">0%</span></span>
                                <span id="faasTotalUtilL7Badge" data-metric="util_l7_avg" data-label="Avg Util (L7)"
                                    class="faas-stat-badge faas-stat-badge--util-total badge-chart-link"
                                    title="Total active campaigns, and their average L7 utilisation (U7%) = total spend ÷ total budget (campaigns with a budget)."><i class="summary-trend-dot none" data-util-dot="util_l7_avg" title="Click for history"></i>TOTAL (L7):<span id="faasTotalUtilL7Value">0</span><span class="gac-util-pct" id="faasAvgUtilL7Pct">avg 0%</span></span>

                            </div>
                        </div>
                    </div>

                    {{-- Toolbar buttons + filters share ONE row (wraps only when the screen is too narrow). --}}
                    <div id="gac-raw-toolbar-row" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span id="gac-raw-total" class="badge bg-secondary">Rows: —</span>
                        <button type="button" id="gac-raw-refresh" class="btn btn-sm btn-outline-primary gac-raw-icon-btn" title="Refresh grid" aria-label="Refresh grid">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="gac-raw-pull-data" class="btn btn-sm btn-primary" title="Runs app:fetch-google-ads-campaigns — pulls fresh campaign metrics from Google Ads + GA4. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-download-alt"></i>
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
                            <ul class="dropdown-menu gac-col-menu dropdown-menu-end"
                                id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                                {{-- Populated dynamically after the grid builds --}}
                            </ul>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-bgt-rules-btn" data-bs-toggle="modal" data-bs-target="#gacRawBgtRulesModal" title="Edit BGT Vs ACOS, BGT Vs VIEWS, BGT Vs CVR and BGT PRC rules in one place">BGT</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbid-rules-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbidRulesModal" title="Show the SBID rules currently in use (live from the saved rule)"><i class="fas fa-list-check"></i> SBID Rules</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbgt" title="Pushes each row’s SBGT as daily budget (chunks of 10). SBGT 0 / INV ≤ 0 pauses the campaign — $0 cannot be pushed.">
                            <i class="fa fa-cloud-upload-alt"></i> SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbid" title="Pushes SBIDs in chunks of 4 using grid values (by campaign_id). Skips SBID — and non-ENABLED rows. Waits until complete; shows success or error.">
                            <i class="fa fa-cloud-upload-alt"></i> SBID
                        </button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-verify-id" title="Filter L30 Spend = 0 and INV &gt; 0, then compare each campaign’s Google Ads Item ID to the live Merchant Center / Shopify product ID. Red triangle in the ID column = mismatch.">
                            <i class="fa fa-id-card"></i> ID
                        </button>
                    </div>
                    <div id="gac-raw-filter-bar">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub7">U7%</label>
                                <select id="gac-filter-ub7" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 7 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                    <option value="zero">0%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub1">U1%</label>
                                <select id="gac-filter-ub1" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 1 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                    <option value="zero">0%</option>
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
                                <label class="gac-raw-filter-label mb-0">Price $</label>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="number" id="gac-filter-price-min" class="gac-raw-range-input" placeholder="Min" min="0" step="0.01" inputmode="decimal" aria-label="Minimum price">
                                    <span class="gac-raw-range-sep">–</span>
                                    <input type="number" id="gac-filter-price-max" class="gac-raw-range-input" placeholder="Max" min="0" step="0.01" inputmode="decimal" aria-label="Maximum price">
                                </div>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-stat">Sts</label>
                                <select id="gac-filter-stat" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by campaign status">
                                    <option value="all">All</option>
                                    <option value="ENABLED" selected>Enabled</option>
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
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ntarget" title="Negative keyword count (N Target column)">N Tgt</label>
                                <select id="gac-filter-ntarget" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by N Target (negative keyword count)">
                                    <option value="all" selected>All</option>
                                    <option value="m">M (none)</option>
                                    <option value="has">Has negatives</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-idst">Alert</label>
                                <select id="gac-filter-idst" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by Item ID check against Merchant Center" title="ID alert (checks Google Ads Item ID vs Merchant Center): Green = ID matched, Yellow = no ID found, Red = ID mismatch / lookup failed.">
                                    <option value="all" selected>All</option>
                                    <option value="matched">🟢 Green</option>
                                    <option value="missing">🟡 Yellow</option>
                                    <option value="mismatch">🔴 Red</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ver">Verif</label>
                                <select id="gac-filter-ver" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by Verification" title="Verification: Items attached on Google Ads vs SKUs under the parent. Green = equal, Red = mismatch.">
                                    <option value="all" selected>All</option>
                                    <option value="match">🟢 Match</option>
                                    <option value="mismatch">🔴 Mismatch</option>
                                </select>
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

    {{-- BGT Rules — one modal for BGT Vs ACOS, VIEWS, CVR, PRC and DIL. --}}
    @php
        $gacBgtSlabCards = [
            ['key' => 'views', 'title' => 'BGT Vs VIEWS', 'hint' => 'Inclusive Shopify View L7 range (parent = sum of children). First matching slab wins, top to bottom. Saved in a Google-only table.', 'tbody' => 'gac-bgt-views-bands-body', 'add' => 'gac-bgt-views-add-band-btn', 'err' => 'gacRawBgtViewsRuleErr'],
            ['key' => 'cvr', 'title' => 'BGT Vs CVR', 'hint' => 'Inclusive Google Shopping CVR L30 range (Sold ÷ Clicks × 100). First matching slab wins, top to bottom. Saved in a Google-only table.', 'tbody' => 'gac-bgt-cvr-bands-body', 'add' => 'gac-bgt-cvr-add-band-btn', 'err' => 'gacRawBgtCvrRuleErr'],
            ['key' => 'prc', 'title' => 'BGT PRC', 'hint' => 'Inclusive Shopify Price range (parent = average of children with price > 0). First matching slab wins, top to bottom. Saved in a Google-only table.', 'tbody' => 'gac-bgt-prc-bands-body', 'add' => 'gac-bgt-prc-add-band-btn', 'err' => 'gacRawBgtPrcRuleErr'],
            ['key' => 'dil', 'title' => 'BGT Vs DIL', 'hint' => 'Inclusive Dil% range (OV L30 ÷ INV × 100; parent = children total). First matching slab wins, top to bottom; the matching BGT is added to SBGT. Negative values are allowed. Default slabs add 0. Saved in a Google-only table.', 'tbody' => 'gac-bgt-dil-bands-body', 'add' => 'gac-bgt-dil-add-band-btn', 'err' => 'gacRawBgtDilRuleErr'],
        ];
    @endphp
    <div class="modal fade" id="gacRawBgtRulesModal" tabindex="-1" aria-labelledby="gacRawBgtRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="gacRawBgtRulesModalLabel"><i class="fas fa-tags me-1"></i> BGT Rules</h5>
                        <div class="gac-bgt-sub">SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + BGT DIL, capped at the max % of price. SBGT 0 cannot be pushed (the campaign is paused). Negative numbers are allowed in every rule. Save and apply writes every rule, then refreshes the grid.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-danger gac-bgt-global-err d-none" id="gacRawBgtRulesErr" role="alert"></p>

                    <div class="gac-bgt-opts">
                        <label class="gac-bgt-opt" title="Rule: when INV is 0 (parent = total of its SKUs) SBGT becomes 0, and a $0 budget cannot be pushed, so the ad is paused automatically.">
                            <input type="checkbox" id="gac-bgt-pause-inv0" checked>
                            <span>Pause ads if INV = 0</span>
                        </label>
                        <label class="gac-bgt-opt" title="Rule: SBGT can never be more than this % of the price (whole dollars, minimum $1 because a $0 budget cannot be pushed). 0 = no cap.">
                            <span>Max BGT =</span>
                            <input type="number" id="gac-bgt-max-pct" value="10" step="0.5" min="0" max="1000" class="form-control form-control-sm">
                            <span>% of price</span>
                        </label>
                        <label class="gac-bgt-opt" title="Rule: when the price is below this amount SBGT becomes 0, and a $0 budget cannot be pushed, so the ad is paused automatically. Change the amount any time; 0 = rule off.">
                            <span>Pause ads if price below $</span>
                            <input type="number" id="gac-bgt-pause-price" value="20" step="1" min="0" class="form-control form-control-sm">
                        </label>
                        <label class="gac-bgt-opt" title="Rule: when Dil% (OV L30 ÷ INV × 100; parent = children total) is above this, SBGT becomes 0, and a $0 budget cannot be pushed, so the ad is paused automatically. Change the amount any time; 0 = rule off.">
                            <span>Pause ads if Dil above</span>
                            <input type="number" id="gac-bgt-pause-dil" value="50" step="1" min="0" class="form-control form-control-sm">
                            <span>%</span>
                        </label>
                    </div>

                    <div class="gac-bgt-cols">
                        <div class="gac-bgt-col" data-gac-bgt-panel="acos">
                            <div class="gac-bgt-col-head">
                                <div class="gac-bgt-title" title="Same ACOS → BGT ACOS brackets as /amazon-ads/all, matched on ACOS % only (no spend gate). Rows are checked top to bottom; the first range that contains the campaign's ACOS gets its BGT ACOS. Use 9999 on To for the catch-all highest band.">BGT Vs ACOS</div>
                                <span class="gac-bgt-head-actions"><button type="button" class="gac-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="gac-bgt-min-btn" title="Minimize BGT Vs ACOS" aria-label="Minimize BGT Vs ACOS">−</button></span>
                            </div>
                            <div class="gac-bgt-chart">
                                <div class="gac-bgt-chart-canvas"><canvas id="gac-bgt-chart-acos"></canvas></div>
                                <div class="gac-bgt-legend" id="gac-bgt-leg-acos"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="gac-sbgt-rule-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>SBGT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="gac-sbgt-bands-body"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary gac-bgt-add" id="gac-sbgt-add-band-btn">Add band</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="gacRawSbgtRuleErr" role="alert"></p>
                        </div>

                        @foreach ($gacBgtSlabCards as $card)
                            <div class="gac-bgt-col" data-gac-bgt-panel="{{ $card['key'] }}">
                                <div class="gac-bgt-col-head">
                                    <div class="gac-bgt-title" title="{{ $card['hint'] }}">{{ $card['title'] }}</div>
                                    <span class="gac-bgt-head-actions"><button type="button" class="gac-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="gac-bgt-min-btn" title="Minimize {{ $card['title'] }}" aria-label="Minimize {{ $card['title'] }}">−</button></span>
                                </div>
                                <div class="gac-bgt-chart">
                                    <div class="gac-bgt-chart-canvas"><canvas id="gac-bgt-chart-{{ $card['key'] }}"></canvas></div>
                                    <div class="gac-bgt-legend" id="gac-bgt-leg-{{ $card['key'] }}"></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>BGT</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="{{ $card['tbody'] }}"></tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary gac-bgt-add" id="{{ $card['add'] }}">Add slab</button>
                                <p class="small text-danger mb-0 mt-2 d-none" id="{{ $card['err'] }}" role="alert"></p>
                            </div>
                        @endforeach

                        <div class="gac-bgt-col gac-bgt-col--sum" data-gac-bgt-panel="sum">
                            <div class="gac-bgt-col-head">
                                <div class="gac-bgt-title" title="SBGT for ALL campaigns, every status (saved rules; not limited by the grid page or filters): campaigns per daily budget, and BGT = daily budget x campaigns. SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + BGT DIL, capped at the max % of price; a total of 0 pauses the campaign. The charts count the same rows against the bands you are editing.">Sum</div>
                                <span class="gac-bgt-head-actions"><button type="button" class="gac-bgt-min-btn" title="Minimize Sum" aria-label="Minimize Sum">−</button></span>
                            </div>
                            <div class="gac-bgt-sum-dist">
                                <div class="gac-bgt-sum-dist-head"><span>Daily budget</span><span>Campaigns</span><span>BGT</span><span>%</span></div>
                                <div class="gac-bgt-legend" id="gac-bgt-leg-sum"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawBgtRulesSaveBtn"><i class="fas fa-save me-1"></i> Save and Apply</button>
                </div>
            </div>
        </div>
    </div>

    {{-- SBID Rules — editable. Rendered by gacSbidRulesRender() from the saved rule each time it opens; numbers can be edited
         inline with live previews (threshold scale, rows per rule, a try-it calculator). Save and Apply stores the rule
         through the same endpoint as the BGT rules (the SBGT bands are re-sent unchanged) and reloads the grid. --}}
    <div class="modal fade" id="gacRawSbidRulesModal" tabindex="-1" aria-labelledby="gacRawSbidRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="gacRawSbidRulesModalLabel"><i class="fas fa-list-check me-1"></i> SBID Rules</h5>
                        <div class="gac-sbid-sub">How the grid's SBID is worked out. Edit any purple number and everything below updates live; SBID is rounded down to the cent. Nothing changes until you Save and Apply.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger gac-sbid-err d-none" id="gacRawSbidRulesErr" role="alert"></p>
                    <div id="gacRawSbidRulesBody"><p class="text-muted small mb-0">Loading…</p></div>
                </div>
                <div class="modal-footer">
                    <span><span class="gac-sbid-dirty d-none" id="gacRawSbidRulesDirty">Unsaved changes</span></span>
                    <span class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="gacRawSbidRulesResetBtn"><i class="fas fa-rotate-left me-1"></i> Reset</button>
                        <button type="button" class="btn btn-sm gac-sbid-save" id="gacRawSbidRulesSaveBtn"><i class="fas fa-save me-1"></i> Save and Apply</button>
                    </span>
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

    {{-- "+" next to the campaign name: SKUs of the (parent) campaign with INV, Item ID, live state in the ad and an Enable / Pause / Remove dropdown. --}}
    <div class="modal fade" id="gacSkuModal" tabindex="-1" aria-labelledby="gacSkuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="gacSkuModalLabel">
                        <i class="fas fa-plus-circle" style="color:#2563eb;"></i>
                        SKUs — <span id="gacSkuName" class="text-muted"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2" style="min-height:120px;">
                    <div id="gacSkuMeta" class="small text-muted mb-2"></div>
                    <div id="gacSkuMsg" class="small mb-2 d-none"></div>
                    <div id="gacSkuBody"><p class="text-muted small mb-0">Loading…</p></div>
                </div>
            </div>
        </div>
    </div>

    {{-- N Target click: Google Ads Keyword Planner ("Discover new keywords"), United States / English. --}}
    <div class="modal fade" id="gacKwPlannerModal" tabindex="-1" aria-labelledby="gacKwPlannerLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="gacKwPlannerLabel">
                        <i class="fas fa-search" style="color:#2563eb;"></i>
                        Keyword Planner — Discover new keywords <span id="gacKwPlannerName" class="text-muted"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold mb-1" for="gacKwPlannerKeywords">Enter products or services closely related to your business</label>
                            <textarea id="gacKwPlannerKeywords" class="form-control form-control-sm" rows="2" maxlength="600" placeholder='Try "meal delivery" or "leather boots" (separate several with commas)'></textarea>
                            <div class="small text-muted mt-1"><i class="fas fa-language"></i> English &nbsp; <i class="fas fa-map-marker-alt"></i> United States</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1" for="gacKwPlannerSite">Enter a site to filter unrelated keywords</label>
                            <input type="text" id="gacKwPlannerSite" class="form-control form-control-sm" placeholder="https://" maxlength="200">
                            <div class="small text-muted mt-1">Optional. Filters out products or brands you don't offer.</div>
                        </div>
                    </div>
                    <div class="mt-2 mb-2">
                        <button type="button" class="btn btn-sm btn-primary" id="gacKwPlannerGo">Get results</button>
                        <span id="gacKwPlannerMsg" class="small ms-2"></span>
                    </div>
                    <div id="gacKwPlannerBody" style="max-height:52vh;overflow:auto;"></div>
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
            const gacBgtDilRuleGetUrl = @json(route('google.shopping.campaigns.bgt-dil-rule'));
            const gacBgtDilRuleSaveUrl = @json(route('google.shopping.campaigns.bgt-dil-rule.save'));
            const gacRawPushSbgtUrl = @json(route('google.shopping.campaigns.push.sbgt'));
            const gacRawPushSbidUrl = @json(route('google.shopping.campaigns.push.sbid'));
            const gacRawToggleStatusUrl = @json(route('google.shopping.campaigns.toggle.status'));
            const gacRawFixItemIdsUrl = @json(route('google.shopping.campaigns.fix.item.ids'));
            const gacKwPlannerUrl = @json(route('google.shopping.campaigns.keyword.planner'));
            const gacCampaignSkusUrl = @json(route('google.shopping.campaigns.campaign.skus'));
            const gacCampaignSkuActionUrl = @json(route('google.shopping.campaigns.campaign.sku.action'));
            const gacRawPullDataUrl = @json(route('google.shopping.campaigns.pull.data'));
            const gacRawBadgeHistoryUrl = @json(route('google.shopping.campaigns.badge.history'));
            const gacRawBadgeKpisUrl = @json(route('google.shopping.campaigns.badge.kpis'));
            const gacRawBadgeUtilUrl = @json(route('google.shopping.campaigns.badge.util'));
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
                id_status: true,
                ver_attached: true,
                ver_total: true,
                ver_status: true,
                ver_reason: true,
                ads_needed_title: true,
                id_alert_title: true,
                bgt_views_color: true,
                bgt_views_label: true,
                bgt_cvr_color: true,
                bgt_cvr_label: true,
                bgt_cvr_page_cvr: true,
                bgt_prc_color: true,
                bgt_prc_label: true,
                bgt_prc_price: true,
                bgt_dil_color: true,
                bgt_dil_label: true,
                bgt_dil_value: true,
                sbgt_cap: true,
                ovl30: true,
                lbid_green: true,
                lbgt_green: true,
                lbid_tip: true,
                lbgt_tip: true,
                live_cpc_tip: true,
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
            /** L30 Spend / Sales / Sold across every status (Paused + Removed included) from the server summary; null until loaded. */
            let gacRawSpendL30All = null;
            let gacRawSalesL30All = null;
            let gacRawSoldL30All = null;
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

            // ID column (filled by the ID button or the ID filter): green check = Ads Item ID matches Merchant Center,
            // yellow triangle = no ID found, red triangle = ID does not match.
            function gacRawIdCheckFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var status = String(row.id_status || '');
                if (status === '' && (row.id_mismatch === true || row.id_mismatch === 1 || row.id_mismatch === '1')) status = 'mismatch';
                if (status === '') return '';
                var tip = String(row.id_alert_title || '');
                tip = tip.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                if (status === 'matched') {
                    return '<i class="fas fa-check-circle" title="' + (tip || 'Item ID matches Merchant Center') + '" style="color:#16a34a;font-size:16px;"></i>';
                }
                if (status === 'missing') {
                    return '<i class="fas fa-exclamation-triangle" title="' + (tip || 'No Item ID found') + '" style="color:#eab308;font-size:15px;"></i>';
                }
                return '<i class="fas fa-exclamation-triangle" title="' + (tip || 'Item ID does not match Merchant Center') + '" style="color:#dc2626;font-size:15px;"></i>';
            }

            // Ads on/off switch: ON = ENABLED. Flipping it pushes PAUSED/ENABLED to Google Ads right away.
            // If the row is one of several checked rows, the same change is applied to all checked rows (bulk).
            var gacRawToggleBusy = false;
            /** Ads Needed: purple alert with the number of SKUs that have INV but no running ad (click → SKUs modal). */
            function gacRawAdsNeededFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var n = row.ads_needed;
                if (n === null || n === undefined || n === '') {
                    var err = String(row.ads_needed_title || '');
                    return err ? '<span title="' + gacRawEscAttr(err) + '" style="color:#9ca3af;">?</span>' : '<span style="color:#d1d5db;">—</span>';
                }
                n = parseInt(n, 10) || 0;
                var title = String(row.ads_needed_title || '');
                if (n <= 0) {
                    return '<span style="color:#9ca3af;" title="' + gacRawEscAttr(title || 'Every SKU with inventory has a running ad') + '">0</span>';
                }
                var cid = String(row.campaign_id || '').replace(/\D/g, '');
                return '<span class="gac-sku-plus gac-ads-needed-pill" role="button" tabindex="0" data-sku-cid="' + cid + '" data-sku-name="' + gacRawEscAttr(String(row.campaign_name || '')) + '"'
                    + ' title="' + gacRawEscAttr(title + ' — click to see the SKUs') + '"'
                    + ' style="display:inline-flex;align-items:center;gap:4px;background:#7c3aed;color:#fff;font-weight:700;font-size:12px;line-height:1;padding:3px 8px;border-radius:999px;cursor:pointer;">'
                    + '<i class="fas fa-exclamation-circle" style="font-size:11px;"></i>' + n + '</span>';
            }
            function gacRawStatusToggleFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var cid = String(row.campaign_id || '').replace(/\D/g, '');
                var st = String(row.campaign_status || '').toUpperCase();
                var wrap = document.createElement('div');
                wrap.className = 'form-check form-switch gac-status-switch';
                var input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.checked = st === 'ENABLED';
                var locked = cid === '' || st === 'REMOVED';
                input.disabled = locked;
                input.title = locked ? 'Status cannot be changed (' + (st || 'no campaign id') + ')'
                    : (st === 'ENABLED' ? 'Enabled — switch off to pause in Google Ads' : 'Not enabled (' + (st || '—') + ') — switch on to enable in Google Ads');
                input.addEventListener('click', function(ev) { ev.stopPropagation(); });
                input.addEventListener('change', function() {
                    gacRawToggleStatus(cell.getRow(), input.checked);
                });
                wrap.appendChild(input);
                return wrap;
            }

            function gacRawToggleStatus(clickedRow, wantEnabled) {
                var target = wantEnabled ? 'ENABLED' : 'PAUSED';
                if (gacRawToggleBusy) {
                    clickedRow.reformat();
                    return;
                }
                var selected = table && table.getSelectedRows ? table.getSelectedRows() : [];
                var isBulk = selected.length > 1 && selected.indexOf(clickedRow) !== -1;
                var rows = isBulk ? selected : [clickedRow];
                var byCid = {};
                rows.forEach(function(r) {
                    var d = r.getData() || {};
                    var cid = String(d.campaign_id || '').replace(/\D/g, '');
                    var st = String(d.campaign_status || '').toUpperCase();
                    if (cid && st !== 'REMOVED' && (isBulk ? st !== target : true)) byCid[cid] = r;
                });
                var ids = Object.keys(byCid);
                var revert = function() { rows.forEach(function(r) { try { r.reformat(); } catch (e) {} }); };
                if (!ids.length) {
                    revert();
                    if (isBulk) gacShowPushResult('Nothing to change', 'All checked campaigns are already ' + target + ' (or REMOVED).', 'success');
                    return;
                }
                if (isBulk && !window.confirm((wantEnabled ? 'ENABLE' : 'PAUSE') + ' ' + ids.length + ' checked campaign(s) in Google Ads now?')) {
                    revert();
                    return;
                }
                gacRawToggleBusy = true;
                gacShowPushLoading((wantEnabled ? 'Enabling' : 'Pausing') + ' ' + ids.length + ' campaign(s) in Google Ads…',
                    'Updating Google Ads in chunks — keep this tab open.');
                var chunkSize = 5;
                var lines = [];
                var okCount = 0, failCount = 0;
                var chunks = [];
                for (var i = 0; i < ids.length; i += chunkSize) chunks.push(ids.slice(i, i + chunkSize));

                var runChunk = function(idx) {
                    if (idx >= chunks.length) return Promise.resolve();
                    return fetch(gacRawToggleStatusUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': gacCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ campaign_ids: chunks[idx], status: target }),
                    })
                        .then(function(res) {
                            return res.json().catch(function() { return { results: [], message: 'HTTP ' + res.status }; })
                                .then(function(b) { return { b: b, http: res.status }; });
                        })
                        .then(function(x) {
                            var results = (x.b && x.b.results) || [];
                            if (!results.length) {
                                chunks[idx].forEach(function(cid) {
                                    failCount++;
                                    lines.push('[ERROR] ' + cid + ': ' + ((x.b && x.b.message) || ('HTTP ' + x.http)));
                                });
                            }
                            results.forEach(function(r) {
                                var label = (r.name ? r.name + ' ' : '') + '(' + r.campaign_id + ')';
                                if (r.ok) {
                                    okCount++;
                                    lines.push('[OK] ' + label + ': ' + r.message);
                                    var row = byCid[String(r.campaign_id)];
                                    if (row) { try { row.update({ campaign_status: target }); } catch (e) {} }
                                } else {
                                    failCount++;
                                    lines.push('[ERROR] ' + label + ': ' + r.message);
                                }
                            });
                        })
                        .catch(function(err) {
                            chunks[idx].forEach(function(cid) {
                                failCount++;
                                lines.push('[ERROR] ' + cid + ': ' + (err && err.message ? err.message : err));
                            });
                        })
                        .then(function() { return runChunk(idx + 1); });
                };

                runChunk(0).then(function() {
                    gacRawToggleBusy = false;
                    revert();
                    var title = (wantEnabled ? 'Enable' : 'Pause') + ' in Google Ads — ' + okCount + ' updated'
                        + (failCount ? ', ' + failCount + ' failed' : '');
                    gacShowPushResult(title, lines.join('\n'), failCount ? 'error' : 'success');
                    if (okCount && typeof gacRawRefreshTableUiSoon === 'function') gacRawRefreshTableUiSoon();
                });
            }

            // Fix button: shown on rows whose Ads Item IDs differ from Merchant Center / the parent's SKUs.
            var gacRawFixBusy = false;
            function gacRawFixFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var ids = String(row.id_status || '');
                var needs = String(row.ver_status || '') === 'mismatch' || ids === 'mismatch' || ids === 'missing';
                if (!needs || ids === 'error') return '';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm gac-fix-btn';
                btn.innerHTML = '<i class="fas fa-wrench"></i> Fix';
                btn.title = 'Add missing SKUs / remove wrong SKUs on this campaign in Google Ads (you will see the plan first)';
                btn.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    gacRawFixItemIds(cell.getRow());
                });
                return btn;
            }

            function gacRawReasonFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var txt = String(row.ver_reason || '');
                if (txt === '') return '';
                var st = String(row.id_status || '');
                var color = txt.indexOf('OK —') === 0 ? '#16a34a' : (st === 'missing' ? '#a16207' : '#dc2626');
                var esc = txt.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                return '<span title="' + esc + '" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:' + color + ';">' + esc + '</span>';
            }

            function gacRawFixPost(ids, dryRun) {
                return fetch(gacRawFixItemIdsUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': gacCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ campaign_ids: ids, dry_run: dryRun ? 1 : 0 }),
                }).then(function(res) {
                    return res.json().catch(function() { return {}; }).then(function(b) {
                        if (!b || !Array.isArray(b.results)) {
                            return ids.map(function(cid) {
                                return { campaign_id: cid, name: '', status: 'error', add: [], remove: [], applied: false, message: (b && b.message) || ('HTTP ' + res.status) };
                            });
                        }
                        return b.results;
                    });
                }).catch(function(err) {
                    return ids.map(function(cid) {
                        return { campaign_id: cid, name: '', status: 'error', add: [], remove: [], applied: false, message: String(err && err.message ? err.message : err) };
                    });
                });
            }

            function gacRawFixRunChunks(ids, size, dryRun) {
                var out = [];
                var chunks = [];
                for (var i = 0; i < ids.length; i += size) chunks.push(ids.slice(i, i + size));
                var step = function(idx) {
                    if (idx >= chunks.length) return Promise.resolve(out);
                    return gacRawFixPost(chunks[idx], dryRun).then(function(rs) {
                        out = out.concat(rs);
                        return step(idx + 1);
                    });
                };
                return step(0);
            }

            function gacRawFixLine(r) {
                var label = (r.name ? r.name + ' ' : '') + '(' + r.campaign_id + ')';
                var tag = r.status === 'ok' ? (r.applied ? '[FIXED]' : '[PLAN]') : (r.status === 'nothing' ? '[OK]' : (r.status === 'unsupported' ? '[SKIP]' : '[ERROR]'));
                var t = tag + ' ' + label + ': ' + (r.message || '');
                if (r.add && r.add.length) t += '\n      + add: ' + r.add.join(', ');
                if (r.reinclude && r.reinclude.length) t += '\n      ↺ re-include (was excluded): ' + r.reinclude.join(', ');
                if (r.enable && r.enable.length) t += '\n      ▶ enable (was paused): ' + r.enable.join(', ');
                if (r.remove && r.remove.length) t += '\n      - remove: ' + r.remove.join(', ');
                if (r.paused) t += '\n      ⏸ campaign PAUSED (INV 0)';
                else if (r.pause_campaign) t += '\n      ⏸ campaign will be paused (INV 0)';
                return t;
            }

            function gacRawFixItemIds(clickedRow) {
                if (gacRawFixBusy) return;
                var selected = table && table.getSelectedRows ? table.getSelectedRows() : [];
                var isBulk = selected.length > 1 && selected.indexOf(clickedRow) !== -1;
                var rows = isBulk ? selected : [clickedRow];
                var seen = {};
                var ids = [];
                rows.forEach(function(r) {
                    var d = r.getData() || {};
                    var cid = String(d.campaign_id || '').replace(/\D/g, '');
                    var needs = String(d.ver_status || '') === 'mismatch' || d.id_status === 'mismatch' || d.id_status === 'missing';
                    if (cid && needs && !seen[cid]) { seen[cid] = true; ids.push(cid); }
                });
                if (!ids.length) {
                    window.alert('No checked row has a verification issue to fix. Run the ID / Verification check first.');
                    return;
                }
                gacRawFixBusy = true;
                gacShowPushLoading('Checking ' + ids.length + ' campaign(s) against Merchant Center…', 'Reading listing groups from Google Ads — nothing is changed yet.');
                gacRawFixRunChunks(ids, 5, true).then(function(plans) {
                    var cnt = function(p) { return (p.add || []).length + (p.reinclude || []).length + (p.enable || []).length + (p.remove || []).length; };
                    var actionable = plans.filter(function(p) { return (p.status === 'ok' || p.status === 'nothing') && (cnt(p) > 0 || p.pause_campaign); });
                    var planText = plans.map(gacRawFixLine).join('\n');
                    if (!actionable.length) {
                        gacRawFixBusy = false;
                        gacShowPushResult('Nothing to change in Google Ads', planText, plans.some(function(p) { return p.status === 'error'; }) ? 'error' : 'success');
                        return null;
                    }
                    var nAdd = 0, nRem = 0, nPause = 0;
                    actionable.forEach(function(p) { nAdd += (p.add || []).length + (p.reinclude || []).length + (p.enable || []).length; nRem += (p.remove || []).length; if (p.pause_campaign) nPause++; });
                    gacShowPushResult('Fix plan — ' + actionable.length + ' campaign(s): +' + nAdd + ' / −' + nRem + ' Item ID(s)' + (nPause ? ', pause ' + nPause : ''), planText, 'success');
                    var preview = actionable.slice(0, 8).map(gacRawFixLine).join('\n');
                    if (!window.confirm('Apply this in Google Ads now?\n\n' + actionable.length + ' campaign(s): add/enable ' + nAdd + ', remove ' + nRem + ' Item ID(s)' + (nPause ? ', pause ' + nPause + ' campaign(s) with INV 0' : '') + '.\n\n' + preview + (actionable.length > 8 ? '\n…' : ''))) {
                        gacRawFixBusy = false;
                        return null;
                    }
                    gacShowPushLoading('Fixing ' + actionable.length + ' campaign(s) in Google Ads…', 'Keep this tab open until finished.');
                    return gacRawFixRunChunks(actionable.map(function(p) { return String(p.campaign_id); }), 3, false).then(function(results) {
                        gacRawFixBusy = false;
                        var fixed = results.filter(function(r) { return r.applied; }).length;
                        var bad = results.filter(function(r) { return r.status === 'error' || r.status === 'unsupported'; }).length;
                        gacShowPushResult('Fix in Google Ads — ' + fixed + ' fixed' + (bad ? ', ' + bad + ' failed/skipped' : ''), results.map(gacRawFixLine).join('\n'), bad ? 'error' : 'success');
                        // Reload either way so the Reason column shows the latest result / error.
                        if (typeof gacRawReloadGridForFilters === 'function') gacRawReloadGridForFilters();
                    });
                }).catch(function(err) {
                    gacRawFixBusy = false;
                    gacShowPushResult('Fix failed', String(err && err.message ? err.message : err), 'error');
                });
            }

            // Verification: "<Items attached> / <SKU count>" — green text when equal, red when not.
            function gacRawVerificationFormatter(cell) {
                var row = cell.getRow().getData() || {};
                var st = String(row.ver_status || '');
                if (st === '') return '';
                var total = row.ver_total;
                var att = row.ver_attached;
                var attTxt = (att === null || att === undefined || att === '') ? '?' : String(att);
                var color = st === 'match' ? '#16a34a' : '#dc2626';
                var tip = 'Items attached on Google Ads: ' + attTxt + ' | SKUs under this parent: ' + (total === null || total === undefined ? '?' : total);
                return '<span title="' + tip + '" style="color:' + color + ';font-weight:700;">' + attTxt + ' / ' + (total === null || total === undefined ? '?' : total) + '</span>';
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
            function gacLiveMoneyFormatter(cell) {
                var row = cell.getRow() ? cell.getRow().getData() : {};
                var field = cell.getField ? cell.getField() : '';
                var isBid = field === 'lbid';
                var v = parseFloat(cell.getValue());
                var flagged = isBid ? row.lbid_green : row.lbgt_green;
                var green = (flagged === true || flagged === 1 || flagged === '1') && isFinite(v) && v > 0;
                var tip = isBid ? (row.lbid_tip || 'Live Bid') : (row.lbgt_tip || 'Live Budget');
                var text = isFinite(v) && v > 0
                    ? v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '—';
                var dot = green ? '<span class="gac-live-dot" aria-hidden="true"></span>' : '';
                return '<span class="gac-live-cell" title="' + gacRawEscAttr(tip) + '">' + dot + text + '</span>';
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
                var dilp = parseInt(row.bgt_dil, 10);
                var tip = 'SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + BGT DIL';
                tip += ' · ' + (isFinite(views) ? views : 0) + ' + ' + (isFinite(cvr) ? cvr : 0) + ' + ' + (isFinite(acos) ? acos : 0) + ' + ' + (isFinite(prc) ? prc : 0) + ' + ' + (isFinite(dilp) ? dilp : 0);
                var pBelow = gacPausePriceBelow();
                var rowPrice = parseFloat(row && row.price);
                if (pBelow > 0 && isFinite(rowPrice) && rowPrice > 0 && rowPrice < pBelow) {
                    tip += ' · Price under $' + pBelow + ' zeros SBGT — cannot push $0, campaign will be paused';
                }
                var dAbove = gacPauseDilAbove();
                var rowDil = parseFloat(row && row.dil);
                if (dAbove > 0 && isFinite(rowDil) && rowDil > dAbove) {
                    tip += ' · Dil ' + Math.round(rowDil) + '% is above ' + dAbove + '% — zeros SBGT, cannot push $0, campaign will be paused';
                }
                var capV = parseInt(row.sbgt_cap, 10);
                if (isFinite(capV)) tip += ' · max $' + capV + ' (% of price cap)';
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
            function gacRawBgtDilFormatter(cell) {
                return gacRawBgtPartFormatter(cell, 'bgt_dil_color', function(row) {
                    var dil = parseFloat(row && row.bgt_dil_value);
                    var label = String((row && row.bgt_dil_label) || '').trim();
                    var tip = 'BGT DIL from Dil%';
                    if (isFinite(dil)) tip += ' · Dil ' + Math.round(dil) + '%';
                    if (label) tip += ' · ' + label;
                    return tip;
                });
            }
            // ---- "+" modal: SKUs of a campaign with INV / Item ID / state in the ad / Enable-Pause-Remove ----
            var gacSkuCid = '';
            var gacSkuNeededOnly = false; // true when opened from the purple Ads Needed pill
            var gacSkuLast = null;
            var GAC_SKU_STATE = {
                ENABLED: { label: 'Enabled', bg: '#dcfce7', fg: '#166534', sel: 'enable' },
                PAUSED: { label: 'Paused', bg: '#fef3c7', fg: '#92400e', sel: 'pause' },
                EXCLUDED: { label: 'Excluded', bg: '#fee2e2', fg: '#991b1b', sel: '' },
                NOT_ATTACHED: { label: 'Not in ad', bg: '#f3f4f6', fg: '#4b5563', sel: '' },
                NO_ITEM_ID: { label: 'No Item ID', bg: '#f3f4f6', fg: '#9ca3af', sel: '' }
            };
            function gacSkuShowMsg(text, ok) {
                var el = document.getElementById('gacSkuMsg');
                if (!el) return;
                if (!text) { el.classList.add('d-none'); el.textContent = ''; return; }
                el.className = 'small mb-2 fw-semibold ' + (ok ? 'text-success' : 'text-danger');
                el.textContent = text;
            }
            function gacSkuRender(resp) {
                var body = document.getElementById('gacSkuBody');
                var meta = document.getElementById('gacSkuMeta');
                if (!body) return;
                gacSkuLast = resp;
                var allRows = resp.rows || [];
                var rows = allRows;
                if (gacSkuNeededOnly) {
                    // Ads Needed view: SKUs with stock whose Item ID is not running in the ad.
                    rows = allRows.filter(function(r) {
                        return !r.extra && r.item_id && r.state !== 'ENABLED' && r.inv !== null && r.inv !== undefined && Number(r.inv) > 0;
                    });
                }
                if (meta) {
                    var invT = resp.inv_total === null || resp.inv_total === undefined ? '—' : Number(resp.inv_total).toLocaleString();
                    meta.textContent = 'Campaign ' + (resp.campaign_id || '') + ' · Status ' + (resp.campaign_status || '—') + ' · Total INV ' + invT + ' · '
                        + (gacSkuNeededOnly
                            ? rows.length + ' SKU(s) with INV but not running in the ad'
                            : allRows.filter(function(r) { return !r.extra; }).length + ' SKU(s)');
                }
                var warn = '';
                if (resp.live_error) warn = '<div class="alert alert-warning py-1 px-2 small mb-2">Could not read the live ad: ' + gacRawEscAttr(resp.live_error) + '</div>';
                else if (resp.live_note) warn = '<div class="text-muted small mb-2">' + gacRawEscAttr(resp.live_note) + '</div>';
                var toggle = '<div class="mb-2"><a href="#" class="small gac-sku-toggle">' + (gacSkuNeededOnly ? 'Show all SKUs of this campaign' : 'Show only SKUs not in ads') + '</a></div>';
                if (!rows.length) {
                    body.innerHTML = warn + toggle + '<p class="text-muted small mb-0">' + (gacSkuNeededOnly ? 'No ads needed — every SKU with inventory is running.' : 'No SKUs found for this campaign.') + '</p>';
                    return;
                }
                var html = warn + toggle + '<table class="table table-sm table-bordered align-middle mb-0" style="font-size:12px;">'
                    + '<thead class="table-light"><tr><th>SKU</th><th class="text-center">INV</th><th>Item ID</th><th class="text-center">Current status in ad</th><th class="text-center" style="width:130px;">Action</th></tr></thead><tbody>';
                rows.forEach(function(r, i) {
                    var st = GAC_SKU_STATE[r.state] || GAC_SKU_STATE.NOT_ATTACHED;
                    var inv = (r.inv === null || r.inv === undefined) ? '—' : Number(r.inv).toLocaleString();
                    var invStyle = (r.inv !== null && r.inv !== undefined && Number(r.inv) <= 0) ? 'color:#dc2626;font-weight:700;' : '';
                    var skuTxt = r.extra ? '— (not in this parent)' : r.sku;
                    var dis = r.item_id ? '' : ' disabled';
                    var sel = '<select class="form-select form-select-sm gac-sku-action" data-item="' + gacRawEscAttr(r.item_id || '') + '" data-prev="' + st.sel + '"' + dis + '>'
                        + (st.sel ? '' : '<option value="" selected disabled>Select…</option>')
                        + ['enable:Enable', 'pause:Pause', 'remove:Remove'].map(function(o) {
                            var kv = o.split(':');
                            return '<option value="' + kv[0] + '"' + (st.sel === kv[0] ? ' selected' : '') + '>' + kv[1] + '</option>';
                        }).join('') + '</select>';
                    html += '<tr' + (r.extra ? ' style="background:#fffbeb;"' : '') + '>'
                        + '<td class="fw-semibold">' + gacRawEscAttr(skuTxt) + '</td>'
                        + '<td class="text-center" style="' + invStyle + '">' + inv + '</td>'
                        + '<td style="font-family:monospace;font-size:11px;word-break:break-all;">' + (r.item_id ? gacRawEscAttr(r.item_id) : '<span class="text-muted">—</span>') + '</td>'
                        + '<td class="text-center"><span class="badge" style="background:' + st.bg + ';color:' + st.fg + ';font-weight:700;">' + st.label + '</span></td>'
                        + '<td>' + sel + '</td></tr>';
                });
                body.innerHTML = html + '</tbody></table>';
            }
            function gacSkuLoad() {
                var body = document.getElementById('gacSkuBody');
                fetch(gacCampaignSkusUrl + '?campaign_id=' + encodeURIComponent(gacSkuCid), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json().catch(function() { return {}; }); }).then(function(resp) {
                    if (!resp || !resp.ok) {
                        if (body) body.innerHTML = '<p class="text-danger small mb-0">' + gacRawEscAttr((resp && resp.message) || 'Failed to load SKUs.') + '</p>';
                        return;
                    }
                    gacSkuRender(resp);
                }).catch(function() {
                    if (body) body.innerHTML = '<p class="text-danger small mb-0">Failed to load SKUs.</p>';
                });
            }
            function gacOpenSkuModal(campaignId, name, neededOnly) {
                var cid = String(campaignId || '').replace(/\D/g, '');
                if (!cid) return;
                gacSkuCid = cid;
                gacSkuNeededOnly = !!neededOnly;
                var nameEl = document.getElementById('gacSkuName');
                if (nameEl) nameEl.textContent = name || cid;
                var metaEl = document.getElementById('gacSkuMeta');
                if (metaEl) metaEl.textContent = '';
                gacSkuShowMsg('', true);
                var body = document.getElementById('gacSkuBody');
                if (body) body.innerHTML = '<p class="text-muted small mb-0">Loading from Google Ads…</p>';
                var modalEl = document.getElementById('gacSkuModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                gacSkuLoad();
            }
            // ---- N Target click: Keyword Planner modal ----
            function gacKwPlannerOpen(campaignName) {
                var seed = String(campaignName || '').replace(/^\s*PARENT\s+/i, '').trim();
                var kw = document.getElementById('gacKwPlannerKeywords');
                if (kw) kw.value = seed;
                var site = document.getElementById('gacKwPlannerSite');
                if (site) site.value = '';
                var nm = document.getElementById('gacKwPlannerName');
                if (nm) nm.textContent = seed ? '— ' + seed : '';
                var msg = document.getElementById('gacKwPlannerMsg');
                if (msg) { msg.textContent = ''; msg.className = 'small ms-2'; }
                var body = document.getElementById('gacKwPlannerBody');
                if (body) body.innerHTML = '';
                var modalEl = document.getElementById('gacKwPlannerModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            function gacKwPlannerFetch() {
                var kw = (document.getElementById('gacKwPlannerKeywords') || {}).value || '';
                var site = (document.getElementById('gacKwPlannerSite') || {}).value || '';
                var msg = document.getElementById('gacKwPlannerMsg');
                var body = document.getElementById('gacKwPlannerBody');
                var btn = document.getElementById('gacKwPlannerGo');
                if (!kw.trim() && !site.trim()) {
                    if (msg) { msg.textContent = 'Enter a keyword or a site.'; msg.className = 'small ms-2 text-danger'; }
                    return;
                }
                if (btn) btn.disabled = true;
                if (msg) { msg.textContent = 'Fetching from Google Keyword Planner…'; msg.className = 'small ms-2 text-muted'; }
                if (body) body.innerHTML = '';
                var tokenEl = document.querySelector('meta[name="csrf-token"]');
                fetch(gacKwPlannerUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': tokenEl ? tokenEl.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ keywords: kw, site: site })
                }).then(function(r) { return r.json().catch(function() { return {}; }); }).then(function(resp) {
                    if (btn) btn.disabled = false;
                    if (!resp || !resp.ok) {
                        if (msg) { msg.textContent = (resp && resp.message) || 'Keyword Planner request failed.'; msg.className = 'small ms-2 text-danger'; }
                        return;
                    }
                    var rows = resp.rows || [];
                    if (msg) { msg.textContent = rows.length + ' keyword idea(s)'; msg.className = 'small ms-2 text-success fw-semibold'; }
                    if (!rows.length) { if (body) body.innerHTML = '<p class="text-muted small mb-0">No keyword ideas returned.</p>'; return; }
                    var money = function(v) { return (v === null || v === undefined) ? '—' : '$' + Number(v).toFixed(2); };
                    var compColor = { LOW: '#166534', MEDIUM: '#92400e', HIGH: '#991b1b' };
                    var html = '<table class="table table-sm table-bordered align-middle mb-0" style="font-size:12px;"><thead class="table-light" style="position:sticky;top:0;">'
                        + '<tr><th>Keyword</th><th class="text-end">Avg. monthly searches</th><th class="text-center">Competition</th><th class="text-end">Top of page bid (low)</th><th class="text-end">Top of page bid (high)</th></tr></thead><tbody>';
                    rows.forEach(function(r) {
                        html += '<tr><td>' + gacRawEscAttr(r.keyword) + '</td>'
                            + '<td class="text-end">' + (r.avg_monthly === null || r.avg_monthly === undefined ? '—' : Number(r.avg_monthly).toLocaleString()) + '</td>'
                            + '<td class="text-center" style="font-weight:700;color:' + (compColor[r.competition] || '#6b7280') + ';">' + gacRawEscAttr(r.competition || '—') + '</td>'
                            + '<td class="text-end">' + money(r.low_bid) + '</td><td class="text-end">' + money(r.high_bid) + '</td></tr>';
                    });
                    if (body) body.innerHTML = html + '</tbody></table>';
                }).catch(function() {
                    if (btn) btn.disabled = false;
                    if (msg) { msg.textContent = 'Keyword Planner request failed.'; msg.className = 'small ms-2 text-danger'; }
                });
            }
            function gacSkuApply(selectEl) {
                var item = selectEl.getAttribute('data-item') || '';
                var action = selectEl.value;
                var prev = selectEl.getAttribute('data-prev') || '';
                if (!item || !action || action === prev) return;
                if (action === 'remove' && !window.confirm('Remove Item ID ' + item + ' from this campaign in Google Ads?')) {
                    selectEl.value = prev;
                    return;
                }
                selectEl.disabled = true;
                gacSkuShowMsg('Applying ' + action + ' in Google Ads…', true);
                fetch(gacCampaignSkuActionUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': gacCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ campaign_id: gacSkuCid, item_id: item, action: action }),
                }).then(function(r) { return r.json().catch(function() { return {}; }).then(function(b) { b = b || {}; b._status = r.status; return b; }); }).then(function(resp) {
                    var ok = !!(resp && resp.ok);
                    gacSkuShowMsg(((resp && resp.message) || (ok ? 'Done.' : 'Request failed (HTTP ' + (resp && resp._status || '?') + ').')) + (ok && resp.state ? ' → ' + resp.state : ''), ok);
                    if (ok && typeof gacRawReloadGridForFilters === 'function') { try { gacRawReloadGridForFilters(); } catch (e) {} }
                    gacSkuLoad(); // re-read the live state either way
                }).catch(function() {
                    gacSkuShowMsg('Request failed.', false);
                    selectEl.disabled = false;
                    selectEl.value = prev;
                });
            }
            document.addEventListener('change', function(e) {
                var sel = e.target && e.target.closest ? e.target.closest('.gac-sku-action') : null;
                if (sel) gacSkuApply(sel);
            });
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
                // BGT Rules "Sum" card: server-side SBGT distribution over every campaign (not just this page).
                if (response && response.summary && Array.isArray(response.summary.sbgt_dist)) {
                    window.gacRawSbgtDist = response.summary.sbgt_dist;
                    if (typeof gacBgtPaintSum === 'function' && document.getElementById('gacRawBgtRulesModal')
                        && document.getElementById('gacRawBgtRulesModal').classList.contains('show')) {
                        gacBgtPaintSum();
                    }
                }
                var spiEl = document.getElementById('gac-raw-summary-spi30-val');
                var acosEl = document.getElementById('gac-raw-summary-acos-val');
                var campaignsEl = document.getElementById('faasCampaignsValue');
                var activeEl = document.getElementById('faasActiveValue');
                var greenUtilEl = document.getElementById('faasGreenUtilL7Value');
                var redUtilYEl = document.getElementById('faasRedUtilYValue');
                var greenUtilYEl = document.getElementById('faasGreenUtilYValue');
                var redUtilL7El = document.getElementById('faasRedUtilL7Value');
                var pinkUtilL7El = document.getElementById('faasPinkUtilL7Value');
                var pinkUtilYEl = document.getElementById('faasPinkUtilYValue');
                var yellowUtilYEl = document.getElementById('faasYellowUtilYValue');
                var totalUtilYEl = document.getElementById('faasTotalUtilYValue');
                var totalUtilL7El = document.getElementById('faasTotalUtilL7Value');
                var yellowUtilL7El = document.getElementById('faasYellowUtilL7Value');
                if (!response || typeof response !== 'object' || !response.summary) {
                    if (spiEl) spiEl.textContent = '—';
                    if (acosEl) acosEl.textContent = '—';
                    if (campaignsEl) campaignsEl.textContent = '0';
                    if (activeEl) activeEl.textContent = '0 / 0';
                    if (greenUtilEl) greenUtilEl.textContent = '0';
                    if (redUtilYEl) redUtilYEl.textContent = '0';
                    if (greenUtilYEl) greenUtilYEl.textContent = '0';
                    if (redUtilL7El) redUtilL7El.textContent = '0';
                    if (pinkUtilL7El) pinkUtilL7El.textContent = '0';
                    if (pinkUtilYEl) pinkUtilYEl.textContent = '0';
                    if (yellowUtilYEl) yellowUtilYEl.textContent = '0';
                    if (yellowUtilL7El) yellowUtilL7El.textContent = '0';
                    document.querySelectorAll('.gac-util-pct').forEach(function(el) { el.textContent = '0%'; });
                    if (totalUtilYEl) totalUtilYEl.textContent = '0';
                    if (totalUtilL7El) totalUtilL7El.textContent = '0';
                    ['faasAvgUtilYPct', 'faasAvgUtilL7Pct'].forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) el.textContent = 'avg 0%';
                    });
                    gacRawAvgCtr = 0;
                    gacRawAvgCvr = 0;
                    gacRawSpendL30All = null;
                    gacRawSalesL30All = null;
                    gacRawSoldL30All = null;
                    return;
                }
                var s = response.summary;
                var needEl = document.getElementById('gacAdsNeededValue');
                if (needEl) {
                    var needBadge = document.getElementById('gacAdsNeededBadge');
                    if (s.ads_needed_error) {
                        needEl.textContent = '?';
                        if (needBadge) needBadge.title = 'Could not read live ads: ' + s.ads_needed_error;
                    } else {
                        var nTot = Number(s.ads_needed_total) || 0;
                        needEl.textContent = nTot.toLocaleString() + (nTot ? ' · ' + (Number(s.ads_needed_campaigns) || 0) + ' camp.' : '');
                        if (needBadge) {
                            needBadge.title = nTot + ' SKU(s) have inventory but no running ad, in ' + (Number(s.ads_needed_campaigns) || 0) + ' enabled campaign(s). They are created automatically every hour.'
                                + ((Number(s.ads_needed_pause) || 0) ? ' ' + s.ads_needed_pause + ' SKU(s) with INV 0 still run and will be paused.' : '')
                                + ((Number(s.ads_needed_no_id) || 0) ? ' ' + s.ads_needed_no_id + ' SKU(s) have no Merchant Center Item ID and cannot be created.' : '');
                        }
                    }
                }
                var gacAllNum = function(v) {
                    return (v !== null && v !== undefined && Number.isFinite(Number(v))) ? Number(v) : null;
                };
                gacRawSpendL30All = gacAllNum(s.spend_l30_all);
                gacRawSalesL30All = gacAllNum(s.sales_l30_all);
                gacRawSoldL30All = gacAllNum(s.sold_l30_all);
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
                    // Active / Total over ALL campaigns (every status), with the active share.
                    var a = Number(s.util_active_count !== undefined ? s.util_active_count : s.active_count);
                    var t = Number(s.util_total_count !== undefined ? s.util_total_count : s.filtered_row_count);
                    activeEl.textContent = (Number.isFinite(a) ? Math.round(a).toLocaleString() : '0')
                        + ' / ' + (Number.isFinite(t) ? Math.round(t).toLocaleString() : '0');
                    var activePctEl = document.getElementById('faasActivePct');
                    if (activePctEl) {
                        activePctEl.textContent = (Number.isFinite(a) && Number.isFinite(t) && t > 0)
                            ? Math.round((a / t) * 100) + '%' : '0%';
                    }
                }
                if (greenUtilEl) {
                    var g = Number(s.green_util_l7_count);
                    greenUtilEl.textContent = Number.isFinite(g) ? Math.round(g).toLocaleString() : '0';
                }
                // Utilized blocks cover ACTIVE (enabled) campaigns only, so their total / % use util_enabled_count.
                // (the ACTIVE badge above keeps util_total_count = every status; these blocks show active campaigns only)
                var gacTotalRows = Number(s.util_enabled_count !== undefined ? s.util_enabled_count : s.filtered_row_count);
                var gacSetUtil = function(valEl, pctId, v) {
                    var n = Number(v);
                    if (valEl) valEl.textContent = Number.isFinite(n) ? Math.round(n).toLocaleString() : '0';
                    var pctEl = document.getElementById(pctId);
                    if (pctEl) {
                        pctEl.textContent = (Number.isFinite(n) && Number.isFinite(gacTotalRows) && gacTotalRows > 0)
                            ? Math.round((n / gacTotalRows) * 100) + '%' : '0%';
                    }
                };
                gacSetUtil(greenUtilEl, 'faasGreenUtilL7Pct', s.green_util_l7_count);
                gacSetUtil(redUtilL7El, 'faasRedUtilL7Pct', s.red_util_l7_count);
                gacSetUtil(pinkUtilL7El, 'faasPinkUtilL7Pct', s.pink_util_l7_count);
                gacSetUtil(yellowUtilL7El, 'faasYellowUtilL7Pct', s.yellow_util_l7_count);
                gacSetUtil(yellowUtilYEl, 'faasYellowUtilYPct', s.yellow_util_y_count);
                // Total campaigns + weighted average utilisation (Σ spend ÷ Σ budget).
                var gacTotalTxt = Number.isFinite(gacTotalRows) ? Math.round(gacTotalRows).toLocaleString() : '0';
                if (totalUtilYEl) totalUtilYEl.textContent = gacTotalTxt;
                if (totalUtilL7El) totalUtilL7El.textContent = gacTotalTxt;
                [['faasAvgUtilYPct', s.avg_util_y_pct], ['faasAvgUtilL7Pct', s.avg_util_l7_pct]].forEach(function(pair) {
                    var el = document.getElementById(pair[0]);
                    var v = Number(pair[1]);
                    if (el) el.textContent = 'avg ' + (Number.isFinite(v) ? v.toFixed(1) : '0') + '%';
                });
                gacSetUtil(greenUtilYEl, 'faasGreenUtilYPct', s.green_util_y_count);
                gacSetUtil(redUtilYEl, 'faasRedUtilYPct', s.red_util_y_count);
                gacSetUtil(pinkUtilYEl, 'faasPinkUtilYPct', s.pink_util_y_count);
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
                    filter_ntarget: gacRawFilterParamVal('gac-filter-ntarget'),
                    filter_id_status: gacRawFilterParamVal('gac-filter-idst'),
                    filter_verification: gacRawFilterParamVal('gac-filter-ver'),
                    filter_ctr_min: gacRawRangeInputVal('gac-filter-ctr-min'),
                    filter_ctr_max: gacRawRangeInputVal('gac-filter-ctr-max'),
                    filter_cvr_min: gacRawRangeInputVal('gac-filter-cvr-min'),
                    filter_cvr_max: gacRawRangeInputVal('gac-filter-cvr-max'),
                    filter_price_min: gacRawRangeInputVal('gac-filter-price-min'),
                    filter_price_max: gacRawRangeInputVal('gac-filter-price-max'),
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
                // N Target number → Keyword Planner modal.
                document.addEventListener('click', function (e) {
                    var t = e.target.closest ? e.target.closest('.gac-ntarget-link') : null;
                    if (!t) return;
                    e.stopPropagation();
                    e.preventDefault();
                    var tmp = document.createElement('textarea');
                    tmp.innerHTML = t.getAttribute('data-kw-name') || '';
                    gacKwPlannerOpen(tmp.value);
                });
                var gacKwGo = document.getElementById('gacKwPlannerGo');
                if (gacKwGo) gacKwGo.addEventListener('click', gacKwPlannerFetch);
                ['gacKwPlannerKeywords', 'gacKwPlannerSite'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('keydown', function(ev) { if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); gacKwPlannerFetch(); } });
                });
                // "Show only SKUs not in ads" / "Show all SKUs" link inside the SKUs modal.
                document.addEventListener('click', function (e) {
                    var a = e.target.closest ? e.target.closest('.gac-sku-toggle') : null;
                    if (!a) return;
                    e.preventDefault();
                    gacSkuNeededOnly = !gacSkuNeededOnly;
                    if (gacSkuLast) gacSkuRender(gacSkuLast);
                });
                // Click the blue + icon in the campaign name cell → open the campaign's SKUs modal.
                document.addEventListener('click', function (e) {
                    var icon = e.target.closest ? e.target.closest('.gac-sku-plus') : null;
                    if (!icon) return;
                    e.stopPropagation();
                    e.preventDefault();
                    var nameAttr = icon.getAttribute('data-sku-name') || '';
                    var tmp = document.createElement('textarea');
                    tmp.innerHTML = nameAttr;
                    gacOpenSkuModal(icon.getAttribute('data-sku-cid'), tmp.value, icon.classList.contains('gac-ads-needed-pill'));
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
                        bgt: 'C BGT',
                        bgt_acos: 'BGT ACOS',
                        bgt_views: 'Bgt Views',
                        bgt_cvr: 'Bgt Cvr',
                        bgt_prc: 'BGT PRC',
                        bgt_dil: 'BGT DIL',
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
                    /** Same bands as Amazon Ads Targets: 0 = red M, under 50 red, 50–100 green, over 100 purple. */
                    var nTargetFormatter = function(c) {
                        var v = c.getValue();
                        if (v === null || v === undefined || v === '') return '—';
                        var n = parseInt(v, 10);
                        if (!isFinite(n)) return '—';
                        var rowD = c.getRow().getData() || {};
                        var nm = ' data-kw-name="' + gacRawEscAttr(String(rowD.campaign_name || '')) + '" role="button" title="Click to open Keyword Planner" style="cursor:pointer;';
                        if (n === 0) return '<span class="fw-semibold gac-ntarget-link"' + nm + 'color:#dc3545">M</span>';
                        var color = n < 50 ? '#dc3545' : (n > 100 ? '#6f42c1' : '#198754');
                        return '<span class="fw-semibold gac-ntarget-link"' + nm + 'color:' + color + '">' + n.toLocaleString() + '</span>';
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
                        var plus = '<i class="fas fa-plus-circle gac-sku-plus" role="button" tabindex="0" title="Show SKUs of this campaign"'
                                 + ' data-sku-cid="' + cid + '" data-sku-name="' + attr + '"'
                                 + ' style="margin-left:6px;color:#2563eb;cursor:pointer;flex-shrink:0;"></i>';
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;max-width:100%;">'
                             + plus
                             + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-left:4px;">' + esc + '</span>'
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
                        bgt_dil: true,
                        sbgt: true,
                        sbid: true,
                    };
                    var numericSortDesc = {
                        inventory: true, dil: true, spend: true, l7_spend: true, l2_spend: true, l1_spend: true,
                        metrics_clicks: true, ctr_l30: true, cpc_L30: true, cpc_L7: true, cpc_L2: true, cpc_L1: true,
                        ad_sold_L30: true, ad_sales_L30: true, acos_l30: true, price: true, cvr_l30: true,
                        ub7: true, ub2: true, ub1: true, bgt: true, bgt_acos: true, views_l30: true, views_l7: true,
                        bgt_views: true, bgt_cvr: true, bgt_prc: true, bgt_dil: true, sbgt: true, sbid: true
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
                                if (dil < 25) color = '#dc3545';
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
                        if (col.field === 'ads_needed') {
                            col.title = 'Ads Needed';
                            col.headerTooltip = 'Purple = SKUs that have inventory (INV > 0) but no running ad in this campaign. The number is how many ads are not created. They are created automatically (hourly); SKUs with INV 0 are paused.';
                            col.headerSort = false;
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.width = 78;
                            col.minWidth = 70;
                            col.formatter = gacRawAdsNeededFormatter;
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
                                // Whole dollars; anything under the "pause ads if price below" amount (default $20) is red.
                                var rounded = Math.round(v);
                                var txt = '$' + rounded.toLocaleString('en-US');
                                var lowBelow = gacPausePriceBelow();
                                if (lowBelow > 0 && v < lowBelow) {
                                    return '<span style="color:#dc2626;font-weight:700;" title="Price under $' + lowBelow + ' — ad is paused (SBGT 0)">' + txt + '</span>';
                                }
                                return txt;
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
                            } else if (col.field === 'bgt_dil') {
                                col.headerTooltip = 'Suggested budget from BGT Vs DIL — Dil% slabs (added to SBGT)';
                                col.formatter = gacRawBgtDilFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 68);
                            } else if (col.field === 'sbgt') {
                                col.headerTooltip = 'Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + BGT DIL, capped at the max % of price. With the rule on, INV ≤ 0 zeros SBGT. SBGT 0 cannot be pushed — Push SBGT pauses the campaign.';
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
                        if (col.field === 'lbid' || col.field === 'lbgt') {
                            var isLiveBid = col.field === 'lbid';
                            col.title = isLiveBid ? 'C Bid' : 'LBgt';
                            col.headerTooltip = isLiveBid
                                ? 'Stored Live Bid. Green only after the background sync verifies it matches SBID. Reloading this page does not call Google Ads.'
                                : 'Stored Live Budget. Green only after the background sync verifies it matches SBGT. Reloading this page does not call Google Ads.';
                            col.formatter = gacLiveMoneyFormatter;
                            col.headerSort = false;
                            col.minWidth = 78;
                            col.width = 84;
                        }
                        if (col.field === 'live_cpc') {
                            // LT CPC: today's average CPC (spend ÷ clicks so far) read from Google Ads by google-shopping:sync-live-cpc.
                            col.title = 'LT CPC';
                            col.headerTooltip = 'Live CPC — today\'s average CPC so far (spend ÷ clicks), pulled from the Google Ads API every 15 minutes. — means no clicks yet today or not pulled yet.';
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.headerSort = false;
                            col.minWidth = 64;
                            col.formatter = function(c) {
                                var v = parseFloat(c.getValue());
                                var row = c.getRow() ? c.getRow().getData() : {};
                                var text = isFinite(v) && v > 0 ? v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                                return '<span title="' + gacRawEscAttr(row.live_cpc_tip || 'Live CPC') + '">' + text + '</span>';
                            };
                        }
                        if (col.field === 'n_targets') {
                            col.title = 'N Target';
                            col.formatter = nTargetFormatter;
                            col.headerTooltip = 'Negative keyword count from Google Ads (campaign + ad group). 0 shows M. Under 50 red, 50–100 green, over 100 purple.';
                            col.headerSort = false;
                            col.width = 52;
                            col.minWidth = 44;
                        }
                    });
                    // Ads Needed sits right before the campaign name.
                    (function() {
                        var iNeed = defs.findIndex(function(d) { return d.field === 'ads_needed'; });
                        if (iNeed === -1) return;
                        var needDef = defs.splice(iNeed, 1)[0];
                        var iName = defs.findIndex(function(d) { return d.field === 'campaign_name'; });
                        if (iName === -1) defs.push(needDef); else defs.splice(iName, 0, needDef);
                    })();
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
                    // Pause / Enable switch right after the status column (bulk when several rows are checked).
                    if (!defs.some(function(d) { return d.field === 'ads_toggle'; })) {
                        var adsToggleDef = {
                            title: 'Ads',
                            field: 'ads_toggle',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 56,
                            minWidth: 50,
                            formatter: gacRawStatusToggleFormatter,
                        };
                        var stIdxForToggle = defs.findIndex(function(d) { return d.field === 'campaign_status'; });
                        if (stIdxForToggle === -1) {
                            defs.push(adsToggleDef);
                        } else {
                            defs.splice(stIdxForToggle + 1, 0, adsToggleDef);
                        }
                    }
                    // Verification column (right after ID): "Items attached on Ads / SKUs under this parent".
                    // Green when equal, red when not (populated when ID / Alert / Verification filter is active).
                    if (!defs.some(function(d) { return d.field === 'verification'; })) {
                        defs.push({
                            title: 'Verification',
                            field: 'verification',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 100,
                            minWidth: 90,
                            formatter: gacRawVerificationFormatter,
                        });
                    }
                    // Fix column (right after Verification): button that adds the missing Item IDs and removes the wrong ones in Google Ads.
                    if (!defs.some(function(d) { return d.field === 'ver_fix'; })) {
                        defs.push({
                            title: 'Fix',
                            field: 'ver_fix',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 64,
                            minWidth: 56,
                            formatter: gacRawFixFormatter,
                        });
                    }
                    // Reason column (right after Fix): the exact reason the ID / Verification check failed (+ last Fix error).
                    if (!defs.some(function(d) { return d.field === 'reason'; })) {
                        defs.push({
                            title: 'Reason',
                            field: 'reason',
                            headerSort: false,
                            hozAlign: 'left',
                            headerHozAlign: 'center',
                            width: 360,
                            minWidth: 200,
                            formatter: gacRawReasonFormatter,
                        });
                    }
                    // Column order: BGT ACOS sits immediately before Bgt Views.
                    var acosIdx = defs.findIndex(function(d) { return d.field === 'bgt_acos'; });
                    if (acosIdx !== -1) {
                        var acosDef = defs.splice(acosIdx, 1)[0];
                        var viewsIdx = defs.findIndex(function(d) { return d.field === 'bgt_views'; });
                        if (viewsIdx === -1) {
                            defs.splice(acosIdx, 0, acosDef);
                        } else {
                            defs.splice(viewsIdx, 0, acosDef);
                        }
                    }
                    // Column order: LBid sits immediately before SBID (swapped).
                    var sbidIdx = defs.findIndex(function(d) { return d.field === 'sbid'; });
                    var lbidIdx = defs.findIndex(function(d) { return d.field === 'lbid'; });
                    if (sbidIdx !== -1 && lbidIdx !== -1 && lbidIdx > sbidIdx) {
                        var lbidDef = defs.splice(lbidIdx, 1)[0];
                        defs.splice(sbidIdx, 0, lbidDef);
                    }
                    return defs;
                },
                ajaxResponse: function(url, params, response) {
                    if (!response || typeof response !== 'object') {
                        var te0 = document.getElementById('gac-raw-total');
                        if (te0) {
                            te0.textContent = 'Rows: —';
                        }
                        return { last_page: 1, last_row: 0, data: [] };
                    }
                    const lastPage = Math.max(1, parseInt(response.last_page, 10) || 1);
                    const lastRow = gacRawFilteredRowCountFromResponse(response);
                    const rows = Array.isArray(response.data) ? response.data : [];

                    const totalEl = document.getElementById('gac-raw-total');
                    if (totalEl) {
                        totalEl.textContent = 'Rows: ' + lastRow.toLocaleString();
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

            ['gac-filter-idst', 'gac-filter-ver'].forEach(function(sid) {
                var sel = document.getElementById(sid);
                if (!sel) return;
                sel.addEventListener('change', function() {
                    sel.classList.remove('gac-alert-matched', 'gac-alert-missing', 'gac-alert-mismatch', 'gac-alert-match');
                    if (sel.value !== 'all') sel.classList.add('gac-alert-' + sel.value);
                });
            });
            ['gac-filter-ub7', 'gac-filter-ub1', 'gac-filter-acos', 'gac-filter-stat', 'gac-filter-inv', 'gac-filter-ntarget', 'gac-filter-idst', 'gac-filter-ver'].forEach(function(fid) {
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
                        gacRawRangeInputVal('gac-filter-cvr-max'),
                        gacRawRangeInputVal('gac-filter-price-min'),
                        gacRawRangeInputVal('gac-filter-price-max')
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
                ['gac-filter-ctr-min', 'gac-filter-ctr-max', 'gac-filter-cvr-min', 'gac-filter-cvr-max', 'gac-filter-price-min', 'gac-filter-price-max'].forEach(function(fid) {
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
            // Column picker groups. Any column not listed lands in "Other".
            var GAC_COL_GROUPS = [
                { key: 'basic', title: 'Basic', fields: ['campaign_status', 'ads_toggle', 'ads_needed', 'campaign_name', 'inventory', 'dil', 'price', 'id_check', 'verification', 'ver_fix', 'reason'] },
                { key: 'bids', title: 'Bids', fields: ['cpc_L30', 'live_cpc', 'cpc_L7', 'cpc_L2', 'cpc_L1', 'lbid', 'sbid'] },
                { key: 'util', title: 'Utilization', fields: ['spend', 'l7_spend', 'l2_spend', 'l1_spend', 'ub7', 'ub2', 'ub1'] },
                { key: 'bgt', title: 'BGT', fields: ['bgt', 'views_l30', 'views_l7', 'bgt_acos', 'bgt_views', 'bgt_cvr', 'bgt_prc', 'bgt_dil', 'sbgt', 'lbgt'] },
                { key: 'other', title: 'Other', fields: [] }
            ];

            function gacColGroupKey(field) {
                for (var i = 0; i < GAC_COL_GROUPS.length - 1; i++) {
                    if (GAC_COL_GROUPS[i].fields.indexOf(field) !== -1) return GAC_COL_GROUPS[i].key;
                }
                return 'other';
            }

            /** Header checkbox state for a group: checked = all shown, indeterminate = some shown. */
            function gacSyncColGroupCheckboxes() {
                var menu = document.getElementById('column-dropdown-menu');
                if (!menu) return;
                menu.querySelectorAll('input[data-group]').forEach(function(head) {
                    var boxes = menu.querySelectorAll('input[data-field][data-in-group="' + head.getAttribute('data-group') + '"]');
                    var on = 0;
                    boxes.forEach(function(b) { if (b.checked) on++; });
                    head.checked = boxes.length > 0 && on === boxes.length;
                    head.indeterminate = on > 0 && on < boxes.length;
                });
            }

            function buildColumnDropdown() {
                var menu = document.getElementById('column-dropdown-menu');
                if (!menu || !table) return;
                menu.innerHTML = '';

                var buckets = {};
                GAC_COL_GROUPS.forEach(function(g) { buckets[g.key] = []; });

                table.getColumns().forEach(function(col) {
                    var def = col.getDefinition();
                    var field = def.field;
                    if (!field || field === '__gac_select') return;
                    if (Object.prototype.hasOwnProperty.call(GAC_PERMANENTLY_HIDDEN_FIELDS, field)) return;
                    buckets[gacColGroupKey(field)].push({ field: field, title: def.title || field, visible: col.isVisible() });
                });

                var esc = function(t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); };
                GAC_COL_GROUPS.forEach(function(g) {
                    var items = buckets[g.key];
                    if (!items.length) return;
                    var li = document.createElement('li');
                    li.className = 'gac-col-group';
                    var html = '<label class="gac-col-group-head" title="Show / hide every ' + esc(g.title) + ' column">' +
                        '<input type="checkbox" data-group="' + g.key + '"> ' + esc(g.title) + '</label>';
                    items.forEach(function(it) {
                        html += '<label class="dropdown-item"><input type="checkbox" ' + (it.visible ? 'checked' : '') +
                            ' data-in-group="' + g.key + '" data-field="' + esc(it.field) + '"> ' + esc(it.title) + '</label>';
                    });
                    li.innerHTML = html;
                    menu.appendChild(li);
                });
                gacSyncColGroupCheckboxes();
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
                    // Group header checkbox: show / hide the whole group at once.
                    var groupKey = e.target.getAttribute('data-group');
                    if (groupKey) {
                        e.target.indeterminate = false;
                        gacColMenu.querySelectorAll('input[data-field][data-in-group="' + groupKey + '"]').forEach(function(box) {
                            box.checked = e.target.checked;
                            var gcol = table.getColumn(box.getAttribute('data-field'));
                            if (!gcol) return;
                            if (e.target.checked) { gcol.show(); } else { gcol.hide(); }
                        });
                        saveColumnVisibilityToServer();
                        return;
                    }
                    var field = e.target.getAttribute('data-field');
                    if (!field) return;
                    var col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    gacSyncColGroupCheckboxes();
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
                        + '<td><input type="number" step="0.1" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_from ?? '') + '" data-idx="' + i + '" data-field="acos_from"'
                        + ' placeholder="0"></td>'
                        + '<td><input type="number" step="0.1" class="form-control form-control-sm"'
                        + ' value="' + (band.acos_to ?? '') + '" data-idx="' + i + '" data-field="acos_to"'
                        + ' placeholder="9999"></td>'
                        + '<td><input type="number" step="1" class="form-control form-control-sm"'
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
                            : (this.value === '' ? '' : parseFloat(this.value));
                        if (fld === 'acos_from' || fld === 'acos_to') {
                            var band = gacCurrentSbgtBands[idx];
                            band.color = gacAcosSchemaStyleForBand(band.acos_from, band.acos_to).bg;
                        }
                        gacBgtPaintAcosCard();
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
                gacBgtPaintAcosCard();
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
            /** BGT Rules options: "Pause ads if INV = 0" and "Max BGT = x % of price". */
            function gacBgtPauseInv0() {
                var el = document.getElementById('gac-bgt-pause-inv0');
                return el ? !!el.checked : true;
            }
            function gacBgtPausePrice() {
                var el = document.getElementById('gac-bgt-pause-price');
                var v = el ? parseFloat(el.value) : 20;
                return (isFinite(v) && v >= 0) ? v : 20;
            }
            /** Saved "pause ads if price below $X" threshold (default 20; 0 = off). */
            function gacPausePriceBelow() {
                var sb = (window.gacRawRule && window.gacRawRule.sbgt) || {};
                var v = parseFloat(sb.pause_price_below);
                return isFinite(v) ? v : 20;
            }
            function gacBgtPauseDil() {
                var el = document.getElementById('gac-bgt-pause-dil');
                var v = el ? parseFloat(el.value) : 50;
                return (isFinite(v) && v >= 0) ? v : 50;
            }
            /** Saved "pause ads if Dil above X%" threshold (default 50; 0 = off). */
            function gacPauseDilAbove() {
                var sb = (window.gacRawRule && window.gacRawRule.sbgt) || {};
                var v = parseFloat(sb.pause_dil_above);
                return isFinite(v) ? v : 50;
            }
            function gacBgtMaxPct() {
                var el = document.getElementById('gac-bgt-max-pct');
                var v = el ? parseFloat(el.value) : 10;
                return (isFinite(v) && v >= 0) ? v : 10;
            }
            /** SBID is no longer edited on this page; re-send the saved rule so saving the BGT bands never resets it. */
            function gacCollectSbid() {
                return (window.gacRawRule && window.gacRawRule.sbid) || {};
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
                if (!spendEl || !clicksEl || !soldEl || !salesEl || !table) {
                    return;
                }

                var spendSum = 0;
                var clicksSum = 0;
                var soldSum = 0;
                var salesSum = 0;
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
                });

                // Spend / Sold / Sales badges = L30 of every campaign (Paused + Removed included), independent of the Sts filter.
                // ACOS and CVR below keep using the visible rows so they stay ratios of the same set.
                spendEl.textContent = gacRawWholeMoney(gacRawSpendL30All !== null ? gacRawSpendL30All : spendSum);
                clicksEl.textContent = Math.round(clicksSum).toLocaleString();
                soldEl.textContent = Math.round(gacRawSoldL30All !== null ? gacRawSoldL30All : soldSum).toLocaleString();
                salesEl.textContent = gacRawWholeMoney(gacRawSalesL30All !== null ? gacRawSalesL30All : salesSum);

                // Keep the numeric average ACOS (matches the badge) in sync and
                // repaint the Action column so its alert reflects the current average.
                var newAvgAcos = salesSum > 0 ? (spendSum / salesSum) * 100 : 0;
                if (newAvgAcos !== gacRawAvgAcos) {
                    gacRawAvgAcos = newAvgAcos;
                    gacRawReformatActionColumn();
                }

                gacRawLoadKpiBadges();
            }

            // ---- KPI badges (Yesterday + L30): Daily BGT / Util / Avg Bid / Avg CVR% / ACOS% ----
            // Lower is better for Avg Bid and ACOS, so their dot colours are inverted (standard dot rule).
            var GAC_KPI_META = {
                bgt:  { fmt: function(v) { return gacRawWholeMoney(v); }, eps: 0.5,   invert: false },
                bgt_total: { fmt: function(v) { return gacRawWholeMoney(v); }, eps: 0.5, invert: false },
                clicks: { fmt: function(v) { return Math.round(v).toLocaleString(); }, eps: 0.5, invert: false },
                sold: { fmt: function(v) { return Math.round(v).toLocaleString(); }, eps: 0.5, invert: false },
                sales: { fmt: function(v) { return gacRawWholeMoney(v); }, eps: 0.5, invert: false },
                spend: { fmt: function(v) { return gacRawWholeMoney(v); }, eps: 0.5, invert: false },
                util: { fmt: function(v) { return v.toFixed(1) + '%'; },   eps: 0.05,  invert: false },
                bid:  { fmt: function(v) { return '$' + v.toFixed(2); },   eps: 0.005, invert: true },
                cvr:  { fmt: function(v) { return v.toFixed(1) + '%'; },   eps: 0.05,  invert: false },
                acos: { fmt: function(v) { return v.toFixed(1) + '%'; },   eps: 0.05,  invert: true }
            };
            var GAC_KPI_KEYS = ['y_bgt', 'y_util', 'y_bid', 'y_cvr', 'y_acos', 'y_clicks', 'y_sold', 'y_sales', 'y_spend', 'l30_bgt', 'l30_util', 'l30_bid', 'l30_cvr', 'l30_acos', 'l30_bgt_total', 'p_bgt', 'p_util', 'p_bid', 'p_cvr', 'p_acos', 'p_clicks', 'p_sold', 'p_sales', 'p_spend', 'p_bgt_total'];
            var gacKpiLastSig = '';
            var gacKpiLastAt = 0;
            var gacKpiAjaxSeq = 0;

            function gacKpiKind(key) { return key.slice(key.indexOf('_') + 1); }

            /** Standard dot: up green / down red / same grey (inverted for lower-is-better). */
            function gacKpiTrend(key, cur, prev) {
                var meta = GAC_KPI_META[gacKpiKind(key)];
                if (!Number.isFinite(cur) || !Number.isFinite(prev)) return 'none';
                var diff = cur - prev;
                var cls = 'flat';
                if (diff > meta.eps) cls = 'up';
                else if (diff < -meta.eps) cls = 'down';
                if (meta.invert && cls === 'up') cls = 'down';
                else if (meta.invert && cls === 'down') cls = 'up';
                return cls;
            }

            function gacKpiPaint(values, prev, cmp, base) {
                GAC_KPI_KEYS.forEach(function(key) {
                    var badge = document.getElementById('gacKpi_' + key);
                    if (!badge) return;
                    var valEl = badge.querySelector('.gac-kpi-val');
                    var dot = badge.querySelector('.summary-trend-dot');
                    var meta = GAC_KPI_META[gacKpiKind(key)];
                    var cur = values ? Number(values[key]) : NaN;
                    var old = prev ? Number(prev[key]) : NaN;
                    if (valEl) valEl.textContent = Number.isFinite(cur) ? meta.fmt(cur) : '—';
                    if (!dot) return;
                    var cls = gacKpiTrend(key, cur, old);
                    dot.className = 'summary-trend-dot ' + cls;
                    badge.classList.remove('gac-trend-up', 'gac-trend-down', 'gac-trend-flat', 'gac-trend-none', 'gac-cmp-better', 'gac-cmp-worse');
                    badge.classList.add('gac-trend-' + cls);
                    // Yesterday / Projected vs the L30 average: green = better, red = otherwise.
                    var verdict = cmp ? cmp[key] : null;
                    if (badge.dataset.tip === undefined) badge.dataset.tip = badge.getAttribute('title') || '';
                    var tipTxt = badge.dataset.tip;
                    if (verdict === 'better' || verdict === 'worse') {
                        badge.classList.add(verdict === 'better' ? 'gac-cmp-better' : 'gac-cmp-worse');
                        var ref = base ? Number(base[key]) : NaN;
                        var refLbl = key.charAt(0) === 'y' && /_(clicks|sold|sales|spend)$/.test(key) ? 'L30 daily avg' : 'L30';
                        tipTxt += ' · ' + (verdict === 'better' ? 'Better' : 'Worse') + ' than ' + refLbl + (Number.isFinite(ref) ? ' (' + meta.fmt(ref) + ')' : '') + '.';
                    }
                    badge.setAttribute('title', tipTxt);
                    var word = cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : (cls === 'flat' ? 'Same' : ''));
                    dot.title = word
                        ? (word + ' vs prior day (' + meta.fmt(old) + ' → ' + meta.fmt(cur) + '). Click for history.')
                        : 'Click for history (no prior day yet)';
                });
            }

            // ---- Utilized Y / L7 badge dots: daily count (or avg %) vs the prior day, same dot rules as the KPI tiles ----
            // More yellow / red campaigns is worse, so those two are inverted; more green / pink / higher avg is up.
            var GAC_UTIL_INVERT = { util_y_yellow: true, util_y_red: true, util_l7_yellow: true, util_l7_red: true };
            var gacUtilDotsAt = 0;
            var gacUtilDotsBusy = false;

            function gacUtilFmt(key, v) {
                return /_avg$/.test(key) ? v.toFixed(1) + '%' : Math.round(v).toLocaleString();
            }

            function gacUtilDotsPaint(values, prev) {
                document.querySelectorAll('[data-util-dot]').forEach(function(dot) {
                    var key = dot.getAttribute('data-util-dot');
                    var cur = values ? Number(values[key]) : NaN;
                    var old = prev ? Number(prev[key]) : NaN;
                    var cls = 'none';
                    if (Number.isFinite(cur) && Number.isFinite(old)) {
                        var eps = /_avg$/.test(key) ? 0.05 : 0.5;
                        var diff = cur - old;
                        cls = diff > eps ? 'up' : (diff < -eps ? 'down' : 'flat');
                        if (GAC_UTIL_INVERT[key] && cls === 'up') cls = 'down';
                        else if (GAC_UTIL_INVERT[key] && cls === 'down') cls = 'up';
                    }
                    dot.className = 'summary-trend-dot ' + cls;
                    var word = cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : (cls === 'flat' ? 'Same' : ''));
                    dot.title = word
                        ? (word + ' vs prior day (' + gacUtilFmt(key, old) + ' → ' + gacUtilFmt(key, cur) + '). Click for history.')
                        : 'Click for history (no prior day yet)';
                });
            }

            function gacRawLoadUtilDots() {
                if (gacUtilDotsBusy || Date.now() - gacUtilDotsAt < 60000) return;
                gacUtilDotsBusy = true;
                fetch(gacRawBadgeUtilUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(res) { return res.json(); })
                    .then(function(resp) {
                        if (resp && resp.success) {
                            gacUtilDotsAt = Date.now();
                            gacUtilDotsPaint(resp.values, resp.prev);
                        } else {
                            gacUtilDotsPaint(null, null);
                        }
                    })
                    .catch(function(err) {
                        console.error('google shopping utilisation dots failed', err);
                        gacUtilDotsPaint(null, null);
                    })
                    .finally(function() { gacUtilDotsBusy = false; });
            }

            function gacRawLoadKpiBadges() {
                if (typeof GAC_KPI_META === 'undefined' || !GAC_KPI_META) return; // script still initialising
                gacRawLoadUtilDots(); // channel-wide (all campaigns) — independent of the visible rows
                var ids = gacRawVisibleCampaignIds();
                if (!ids.length) {
                    gacKpiLastSig = '';
                    gacKpiPaint(null, null);
                    return;
                }
                var sig = ids.join(',');
                // Same rows re-render a lot (sort, scroll, column toggles) — only refetch after 60s or when the rows change.
                if (sig === gacKpiLastSig && Date.now() - gacKpiLastAt < 60000) return;
                gacKpiLastSig = sig;
                gacKpiLastAt = Date.now();
                var seq = ++gacKpiAjaxSeq;
                fetch(gacRawBadgeKpisUrl + '?' + new URLSearchParams({ campaign_ids: sig }).toString(), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(res) { return res.json(); })
                    .then(function(resp) {
                        if (seq !== gacKpiAjaxSeq) return;
                        if (resp && resp.success) gacKpiPaint(resp.values, resp.prev, resp.cmp, resp.base);
                        else { gacKpiLastSig = ''; gacKpiPaint(null, null); }
                    })
                    .catch(function(err) {
                        if (seq !== gacKpiAjaxSeq) return;
                        gacKpiLastSig = '';
                        console.error('google shopping KPI badges failed', err);
                        gacKpiPaint(null, null);
                    });
            }

            function gacRawFormatBadgeChartValue(metric, value) {
                var n = Number(value);
                if (!Number.isFinite(n)) return '—';
                if (metric === 'spend' || metric === 'sales' || metric === 'bgt') {
                    return '$' + Math.round(n).toLocaleString();
                }
                if (metric === 'acos' || metric === 'cvr' || /^util_(y|l7)_avg$/.test(metric)) {
                    return n.toFixed(1) + '%';
                }
                if (GAC_KPI_META[metric.slice(metric.indexOf('_') + 1)] && /^(y|l30|p)_/.test(metric)) {
                    var kpiMeta = GAC_KPI_META[metric.slice(metric.indexOf('_') + 1)];
                    return kpiMeta.fmt(n);
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
                // Green util (L7) legacy + Utilized Y / L7 badges: channel-wide daily counts, not scoped to visible rows.
                var isGreenUtil = metric === 'green_util_l7' || /^util_(y|l7)_/.test(metric);
                if (titleEl) {
                    var rangeLabel = days === 0 ? 'Lifetime' : ('L' + days);
                    // Green util history is a daily count (re-anchored windows), not a rolling L30 average.
                    if (isGreenUtil) {
                        titleEl.textContent = 'Google Shopping - ' + (label || 'Green Util (L7)') + ' (Daily · ' + rangeLabel + ')';
                    } else if (/^p_/.test(metric)) {
                        titleEl.textContent = 'Google Shopping - ' + (label || metric.toUpperCase()) + ' (Projected from L7 ÷ 7 × 30 · ' + rangeLabel + ')';
                    } else if (/^y_/.test(metric)) {
                        // Yesterday KPI badges: one point per day (that day's own value).
                        titleEl.textContent = 'Google Shopping - ' + (label || metric.toUpperCase()) + ' (Daily · ' + rangeLabel + ')';
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
                // Lower is better for ACOS and Avg Bid (also their Y / L30 variants): invert the dot colours.
                var inverted = (metric === 'acos' || /(^|_)(acos|bid)$/.test(metric) || /^util_(y|l7)_(yellow|red)$/.test(metric));

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

            // ---- SBID Rules: editable with live previews ----
            var GAC_SBID_KEYS = ['util_low', 'util_high', 'over_mult_l1', 'under_mult_l1', 'under_mult_l7', 'under_fallback', 'under_flat_max', 'under_flat_incr'];
            var GAC_SBID_DEFAULTS = { util_low: 66, util_high: 99, over_mult_l1: 0.9, under_mult_l1: 1.1, under_mult_l7: 1.1, under_fallback: 0.75, under_flat_max: 0.25, under_flat_incr: 0.05 };
            var gacSbidDraft = {};
            var gacSbidDirty = false;

            function gacSbidSaved() {
                var r = (window.gacRawRule && window.gacRawRule.sbid) || {};
                var out = {};
                GAC_SBID_KEYS.forEach(function(k) {
                    var v = parseFloat(r[k]);
                    out[k] = Number.isFinite(v) ? v : GAC_SBID_DEFAULTS[k];
                });
                return out;
            }
            function gacSbidDraftNum(k) {
                var v = parseFloat(gacSbidDraft[k]);
                return Number.isFinite(v) ? v : GAC_SBID_DEFAULTS[k];
            }
            function gacSbidMoney(v) { return '$' + v.toFixed(2); }
            function gacSbidPct(v) { return String(+v.toFixed(2)) + '%'; }
            function gacSbidFloor(v) { return Math.floor(v * 100) / 100; }

            /** Same maths as GoogleShoppingCampaignsRawRule::sbidFromUb7Ub1Cpc(), on the draft values. Returns { bid, line } (bid null = no SBID). */
            function gacSbidCompute(u7, u1, l1, l7) {
                var low = gacSbidDraftNum('util_low'), high = gacSbidDraftNum('util_high');
                if (u7 > high && u1 > high) {
                    return { bid: gacSbidFloor(l1 * gacSbidDraftNum('over_mult_l1')), line: 'over' };
                }
                if (u7 < low && u1 < low) {
                    var fb = gacSbidDraftNum('under_fallback'), m1 = gacSbidDraftNum('under_mult_l1'), m7 = gacSbidDraftNum('under_mult_l7');
                    var fMax = gacSbidDraftNum('under_flat_max'), fInc = gacSbidDraftNum('under_flat_incr');
                    if (l1 <= 0 && l7 <= 0) return { bid: fb, line: 'u1' };
                    if (l1 > 0) {
                        return l1 < fMax ? { bid: gacSbidFloor(l1 + fInc), line: 'u2' } : { bid: gacSbidFloor(l1 * m1), line: 'u3' };
                    }
                    return l7 < fMax ? { bid: gacSbidFloor(l7 + fInc), line: 'u4' } : { bid: gacSbidFloor(l7 * m7), line: 'u5' };
                }
                return { bid: null, line: 'none' };
            }

            function gacSbidChg(m) {
                var d = (m - 1) * 100;
                return '<span class="gac-sbid-chg ' + (d >= 0 ? 'is-up' : 'is-down') + '" data-chg="' + '">' + (d >= 0 ? '+' : '−') + gacSbidPct(Math.abs(d)) + '</span>';
            }
            function gacSbidIn(k, step, min, max) {
                return '<input type="number" class="gac-sbid-in" data-sbid="' + k + '" step="' + step + '" min="' + min + '" max="' + max + '" value="' + gacSbidDraftNum(k) + '">';
            }
            function gacSbidBind(k, kind) { return '<span class="gac-sbid-bind" data-bind="' + k + '" data-kind="' + kind + '"></span>'; }

            function gacSbidRulesRender() {
                var body = document.getElementById('gacRawSbidRulesBody');
                if (!body) return;
                var chgSlot = function(k) { return '<span data-chg-for="' + k + '"></span>'; };
                var line = function(id, cond, res) {
                    return '<div class="gac-sbid-line" data-line="' + id + '"><span>' + cond + '</span><span class="gac-sbid-res">' + res + '</span></div>';
                };
                var card = function(cls, key, title, when, lines) {
                    return '<div class="gac-sbid-rule gac-sbid-rule--' + cls + '">' +
                        '<div class="gac-sbid-head"><span class="gac-sbid-title">' + title + '</span>' +
                        '<span class="gac-sbid-count"><span data-count="' + key + '"></span><span class="gac-sbid-track"><span class="gac-sbid-fill d-block" data-fill="' + key + '" style="width:0"></span></span></span></div>' +
                        '<div class="gac-sbid-when">' + when + '</div>' + (lines ? '<div class="gac-sbid-lines">' + lines + '</div>' : '') + '</div>';
                };

                body.innerHTML =
                    '<div class="gac-sbid-scale"><div class="gac-sbid-scale-bar">' +
                        '<div class="gac-sbid-seg gac-sbid-seg--under" data-seg="under"></div>' +
                        '<div class="gac-sbid-seg gac-sbid-seg--none" data-seg="none"></div>' +
                        '<div class="gac-sbid-seg gac-sbid-seg--over" data-seg="over"></div>' +
                    '</div><div class="gac-sbid-scale-labels" data-scale-labels></div></div>' +

                    card('over', 'over', '<i class="fas fa-arrow-trend-down me-1" style="color:#c026d3"></i>Over-utilised — lower the bid',
                        'When <b>U7%</b> and <b>U1%</b> are both above ' + gacSbidIn('util_high', 1, 1, 500) + '%',
                        line('over', 'L1 CPC', 'SBID = L1 CPC × ' + gacSbidIn('over_mult_l1', 0.01, 0.01, 5) + ' ' + chgSlot('over_mult_l1'))) +

                    card('under', 'under', '<i class="fas fa-arrow-trend-up me-1" style="color:#e11d48"></i>Under-utilised — raise the bid',
                        'When <b>U7%</b> and <b>U1%</b> are both below ' + gacSbidIn('util_low', 1, 1, 200) + '%. First matching line wins:',
                        line('u1', 'L1 CPC and L7 CPC are both 0', 'SBID = $' + gacSbidIn('under_fallback', 0.01, 0.01, 25)) +
                        line('u2', 'L1 CPC &gt; 0 and under $' + gacSbidIn('under_flat_max', 0.01, 0.01, 25), 'SBID = L1 CPC + $' + gacSbidIn('under_flat_incr', 0.01, 0.01, 5)) +
                        line('u3', 'L1 CPC ≥ ' + gacSbidBind('under_flat_max', 'money'), 'SBID = L1 CPC × ' + gacSbidIn('under_mult_l1', 0.01, 0.01, 5) + ' ' + chgSlot('under_mult_l1')) +
                        line('u4', 'L1 CPC = 0, L7 CPC &gt; 0 and under ' + gacSbidBind('under_flat_max', 'money'), 'SBID = L7 CPC + ' + gacSbidBind('under_flat_incr', 'money')) +
                        line('u5', 'L1 CPC = 0, L7 CPC ≥ ' + gacSbidBind('under_flat_max', 'money'), 'SBID = L7 CPC × ' + gacSbidIn('under_mult_l7', 0.01, 0.01, 5) + ' ' + chgSlot('under_mult_l7'))) +

                    card('none', 'none', '<i class="fas fa-equals me-1" style="color:#475569"></i>In between — no change',
                        'Anything else (U7% and U1% not both below ' + gacSbidBind('util_low', 'pct') + ' or both above ' + gacSbidBind('util_high', 'pct') + ') gets <b>no SBID</b> — shown as — and skipped by Push SBID.',
                        '') +

                    '<div class="gac-sbid-try"><div class="gac-sbid-title"><i class="fas fa-flask me-1" style="color:#7c3aed"></i>Try it — what SBID would these numbers get?</div>' +
                        '<div class="gac-sbid-try-grid">' +
                            '<div><label>U7 %</label><input type="number" class="gac-sbid-in" data-try="u7" value="40" step="1" min="0"></div>' +
                            '<div><label>U1 %</label><input type="number" class="gac-sbid-in" data-try="u1" value="40" step="1" min="0"></div>' +
                            '<div><label>L1 CPC $</label><input type="number" class="gac-sbid-in" data-try="l1" value="0.30" step="0.01" min="0"></div>' +
                            '<div><label>L7 CPC $</label><input type="number" class="gac-sbid-in" data-try="l7" value="0.30" step="0.01" min="0"></div>' +
                        '</div><div class="gac-sbid-try-out" data-try-out></div></div>';
                gacSbidRefresh();
            }

            /** Re-paint everything that depends on the draft values (no re-render, so inputs keep focus). */
            function gacSbidRefresh() {
                var body = document.getElementById('gacRawSbidRulesBody');
                if (!body) return;
                var low = gacSbidDraftNum('util_low'), high = gacSbidDraftNum('util_high');

                // scale
                var max = Math.max(120, Math.ceil((high + 20) / 10) * 10);
                var pLow = Math.min(100, Math.max(0, low / max * 100)), pHigh = Math.min(100, Math.max(pLow, high / max * 100));
                var seg = function(n) { return body.querySelector('[data-seg="' + n + '"]'); };
                if (seg('under')) { seg('under').style.flexBasis = pLow + '%'; seg('under').textContent = pLow > 12 ? 'raise bid' : ''; }
                if (seg('none')) { seg('none').style.flexBasis = (pHigh - pLow) + '%'; seg('none').textContent = (pHigh - pLow) > 14 ? 'no change' : ''; }
                if (seg('over')) { seg('over').style.flexBasis = (100 - pHigh) + '%'; seg('over').textContent = (100 - pHigh) > 12 ? 'lower bid' : ''; }
                var lab = body.querySelector('[data-scale-labels]');
                if (lab) {
                    lab.innerHTML = '<span style="flex-basis:' + pLow + '%">0%</span><span style="flex-basis:' + (pHigh - pLow) + '%">' + gacSbidPct(low) + '</span>' +
                        '<span style="flex-basis:' + (100 - pHigh) + '%">' + gacSbidPct(high) + '</span>';
                }

                // bound values / % change chips
                body.querySelectorAll('[data-bind]').forEach(function(el) {
                    var v = gacSbidDraftNum(el.getAttribute('data-bind'));
                    el.textContent = el.getAttribute('data-kind') === 'money' ? gacSbidMoney(v) : gacSbidPct(v);
                });
                body.querySelectorAll('[data-chg-for]').forEach(function(el) {
                    el.innerHTML = gacSbidChg(gacSbidDraftNum(el.getAttribute('data-chg-for')));
                });

                // rows per rule on the current grid page
                var cnt = { over: 0, under: 0, none: 0 }, total = 0;
                try {
                    (table ? table.getData('active') : []).forEach(function(r) {
                        if (!r || r.campaign_name === undefined) return;
                        total++;
                        var res = gacSbidCompute(gacRawNumber(r.ub7), gacRawNumber(r.ub1), 0, 0);
                        cnt[res.line === 'over' ? 'over' : (res.line === 'none' ? 'none' : 'under')]++;
                    });
                } catch (e) { total = 0; }
                ['over', 'under', 'none'].forEach(function(k) {
                    var c = body.querySelector('[data-count="' + k + '"]');
                    var f = body.querySelector('[data-fill="' + k + '"]');
                    if (c) c.textContent = total ? cnt[k] + ' of ' + total + ' rows on this page' : '';
                    if (f) f.style.width = total ? (cnt[k] / total * 100) + '%' : '0';
                });

                // try-it calculator + highlight the matching line
                var tv = function(n) { var el = body.querySelector('[data-try="' + n + '"]'); var v = el ? parseFloat(el.value) : NaN; return Number.isFinite(v) ? v : 0; };
                var out = gacSbidCompute(tv('u7'), tv('u1'), tv('l1'), tv('l7'));
                body.querySelectorAll('.gac-sbid-line').forEach(function(el) { el.classList.toggle('is-hit', el.getAttribute('data-line') === out.line); });
                var tryOut = body.querySelector('[data-try-out]');
                if (tryOut) {
                    tryOut.innerHTML = out.bid === null
                        ? '<span class="gac-sbid-big">No SBID</span> — in between, so the row is skipped.'
                        : '<span class="gac-sbid-big">' + gacSbidMoney(out.bid) + '</span> — ' + (out.line === 'over' ? 'over-utilised rule' : 'under-utilised rule') + ' (highlighted above).';
                }

                var dirtyEl = document.getElementById('gacRawSbidRulesDirty');
                if (dirtyEl) dirtyEl.classList.toggle('d-none', !gacSbidDirty);
            }

            function gacSbidShowErr(msg) {
                var el = document.getElementById('gacRawSbidRulesErr');
                if (!el) return;
                el.textContent = msg || '';
                el.classList.toggle('d-none', !msg);
            }
            function gacSbidLoadSaved() {
                gacSbidDraft = gacSbidSaved();
                gacSbidDirty = false;
                gacSbidShowErr('');
                gacSbidRulesRender();
            }

            (function() {
                var modalEl = document.getElementById('gacRawSbidRulesModal');
                if (!modalEl) return;
                modalEl.addEventListener('show.bs.modal', function() {
                    gacSbidLoadSaved(); // instant, from the rule already on the page
                    gacRefreshRuleFromServer(function() { if (!gacSbidDirty) gacSbidLoadSaved(); }); // then the latest saved rule
                });
                modalEl.addEventListener('input', function(e) {
                    var t = e.target;
                    if (t && t.hasAttribute && t.hasAttribute('data-sbid')) {
                        gacSbidDraft[t.getAttribute('data-sbid')] = t.value;
                        gacSbidDirty = true;
                        gacSbidShowErr('');
                        // keep the other copies of the same input in sync
                        modalEl.querySelectorAll('[data-sbid="' + t.getAttribute('data-sbid') + '"]').forEach(function(o) { if (o !== t) o.value = t.value; });
                    }
                    gacSbidRefresh();
                });
                document.getElementById('gacRawSbidRulesResetBtn').addEventListener('click', gacSbidLoadSaved);
                var saveBtn = document.getElementById('gacRawSbidRulesSaveBtn');
                saveBtn.addEventListener('click', function() {
                    var sbid = {};
                    var bad = '';
                    GAC_SBID_KEYS.forEach(function(k) {
                        var v = parseFloat(gacSbidDraft[k]);
                        if (!Number.isFinite(v)) bad = 'Every SBID value needs a number.';
                        sbid[k] = v;
                    });
                    if (bad) { gacSbidShowErr(bad); return; }
                    saveBtn.disabled = true;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': gacCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        // the SBGT bands are re-sent unchanged so saving SBID never touches them
                        body: JSON.stringify({ sbgt: Object.assign({}, (window.gacRawRule && window.gacRawRule.sbgt) || { bands: [] }), sbid: sbid }),
                    })
                        .then(function(res) { return res.json().then(function(b) { return { ok: res.ok, body: b }; }); })
                        .then(function(out) {
                            if (!out.ok) throw new Error((out.body && (out.body.message || out.body.error)) || 'Save failed.');
                            window.gacRawRule = out.body.rule || window.gacRawRule;
                            gacSbidDirty = false;
                            if (typeof bootstrap !== 'undefined') {
                                var inst = bootstrap.Modal.getInstance(modalEl);
                                if (inst) inst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { gacRawRefreshTableUiSoon(); })
                        .catch(function(err) { gacSbidShowErr((err && err.message) || 'Network or server error.'); })
                        .finally(function() { saveBtn.disabled = false; });
                });
            })();

            // ======================================================================
            // BGT Rules — single modal (BGT Vs ACOS, VIEWS, CVR, PRC, SBID).
            // Rule values, validation and save endpoints are unchanged; this only
            // lets all of them be edited and saved together.
            // ======================================================================
            var GAC_BGT_FALLBACK_COLORS = ['#e83e8c', '#2563eb', '#28a745', '#f59e0b', '#7c3aed', '#94a3b8'];
            var gacBgtCharts = {};
            var gacBgtSlabCtrls = {};
            var gacBgtRulesLoaded = false;

            function gacBgtEsc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function gacBgtFmtNum(v) {
                var n = Number(v);
                if (!isFinite(n)) return '';
                return String(Math.round(n * 100) / 100);
            }
            function gacBgtMoney(n) {
                return '$' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
            }
            function gacBgtGridRows() {
                try {
                    return (table && typeof table.getData === 'function') ? (table.getData() || []) : [];
                } catch (e) {
                    return [];
                }
            }
            function gacBgtShowErr(id, msg) {
                var el = document.getElementById(id);
                if (!el) return;
                if (msg) { el.textContent = msg; el.classList.remove('d-none'); }
                else { el.textContent = ''; el.classList.add('d-none'); }
            }

            /** Rows per band (first matching band wins, inclusive From/To) for the rows on the grid page. */
            function gacBgtTally(bands, fromKey, toKey, valueOfRow) {
                var counts = (bands || []).map(function() { return 0; });
                var unmatched = 0;
                var total = 0;
                gacBgtGridRows().forEach(function(row) {
                    var n = valueOfRow(row);
                    if (n == null || !isFinite(n)) return;
                    total++;
                    var hit = false;
                    for (var i = 0; i < bands.length; i++) {
                        var from = parseFloat(bands[i][fromKey]);
                        var to = parseFloat(bands[i][toKey]);
                        if (!isFinite(from) || !isFinite(to)) continue;
                        if (n >= from && n <= to) { counts[i]++; hit = true; break; }
                    }
                    if (!hit) unmatched++;
                });
                return { counts: counts, unmatched: unmatched, total: total };
            }

            /** Legend pills + bar chart for one card. items = [{label, n, color}]. */
            function gacBgtDrawChart(key, items, total, axes) {
                var legend = document.getElementById('gac-bgt-leg-' + key);
                var canvas = document.getElementById('gac-bgt-chart-' + key);
                if (!legend) return;
                // Optional Budget column (Sum card): each item carries budget = daily budget x campaigns.
                var hasBudget = items.some(function(r) { return r.budget != null; });
                var totalBudget = items.reduce(function(s, r) { return s + (Number(r.budget) || 0); }, 0);
                legend.classList.toggle('has-budget', hasBudget);
                legend.innerHTML = items.map(function(r) {
                    var pct = total > 0 ? Math.round((r.n / total) * 100) : 0;
                    return '<div class="gac-bgt-leg-row"><span class="gac-bgt-swatch" style="background:' + gacBgtEsc(r.color) + '"></span>'
                        + '<span>' + gacBgtEsc(r.label) + '</span><strong>' + r.n + '</strong>'
                        + (hasBudget ? '<strong class="gac-bgt-leg-bgt">' + gacBgtMoney(r.budget) + '</strong>' : '')
                        + '<span class="gac-bgt-leg-pct">' + pct + '%</span></div>';
                }).join('') + (items.length
                    ? '<div class="gac-bgt-leg-row gac-bgt-leg-total"><span class="gac-bgt-swatch" style="background:transparent"></span><span>Total</span><strong>'
                        + total + '</strong>' + (hasBudget ? '<strong class="gac-bgt-leg-bgt">' + gacBgtMoney(totalBudget) + '</strong>' : '')
                        + '<span class="gac-bgt-leg-pct">' + (total > 0 ? '100%' : '0%') + '</span></div>'
                    : '<div class="text-muted">No rows on this page.</div>');
                if (gacBgtCharts[key]) {
                    try { gacBgtCharts[key].destroy(); } catch (e) {}
                    gacBgtCharts[key] = null;
                }
                // The Sum card shows its data as a column only (no canvas).
                if (!canvas || typeof Chart === 'undefined') return;
                gacBgtCharts[key] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: items.map(function(r) { return r.label; }),
                        datasets: [{
                            data: items.map(function(r) { return r.n; }),
                            backgroundColor: items.map(function(r) { return r.color; }),
                            borderWidth: 0,
                            borderRadius: 4,
                            maxBarThickness: 28
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { display: !!axes, grid: { display: false } },
                            y: { display: !!axes, beginAtZero: true, grid: { display: false } }
                        }
                    }
                });
            }

            function gacBgtPaintCard(key, bands, fromKey, toKey, valueOfRow) {
                var t = gacBgtTally(bands, fromKey, toKey, valueOfRow);
                var items = (bands || []).map(function(b, i) {
                    var f = gacBgtFmtNum(b[fromKey]);
                    var to = gacBgtFmtNum(b[toKey]);
                    return {
                        label: (f !== '' && to !== '') ? (f + '–' + to) : ('Band ' + (i + 1)),
                        n: t.counts[i] || 0,
                        color: b.color || GAC_BGT_FALLBACK_COLORS[i % GAC_BGT_FALLBACK_COLORS.length]
                    };
                });
                if (t.unmatched > 0) items.push({ label: 'No match', n: t.unmatched, color: '#94a3b8' });
                gacBgtDrawChart(key, items, t.total, false);
            }

            function gacBgtAcosValueOfRow(row) {
                var n = parseFloat(row && row.acos_l30);
                return (isFinite(n) && n >= 0) ? n : null;
            }
            function gacBgtPaintAcosCard() {
                gacBgtPaintCard('acos', gacCurrentSbgtBands || [], 'acos_from', 'acos_to', gacBgtAcosValueOfRow);
            }

            /** Summary: SBGT currently on the grid (saved rules) + what each part adds up to. */
            function gacBgtPaintSum() {
                var byVal = {};
                var total = 0;
                if (Array.isArray(window.gacRawSbgtDist)) {
                    // Every campaign (all statuses), from the server.
                    window.gacRawSbgtDist.forEach(function(d) {
                        var n = Number(d.value);
                        var c = Number(d.count) || 0;
                        if (!isFinite(n) || c <= 0) return;
                        var k = String(n);
                        byVal[k] = (byVal[k] || 0) + c;
                        total += c;
                    });
                } else {
                    gacBgtGridRows().forEach(function(r) {
                        var n = parseFloat(r && r.sbgt);
                        if (!isFinite(n)) return;
                        var k = String(n);
                        byVal[k] = (byVal[k] || 0) + 1;
                        total++;
                    });
                }
                var keys = Object.keys(byVal).sort(function(a, b) { return Number(a) - Number(b); });
                var items = keys.map(function(k, i) {
                    return {
                        label: '$' + k, n: byVal[k], budget: Number(k) * byVal[k],
                        color: GAC_BGT_FALLBACK_COLORS[i % GAC_BGT_FALLBACK_COLORS.length]
                    };
                });
                gacBgtDrawChart('sum', items, total, true);
            }

            function gacBgtPaintAll() {
                gacBgtPaintSum();
                gacBgtPaintAcosCard();
                Object.keys(gacBgtSlabCtrls).forEach(function(k) { gacBgtSlabCtrls[k].repaint(); });
            }

            // ---- BGT Vs ACOS (+ SBID) — add band ------------------------------------
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

            // ---- BGT Vs VIEWS / CVR / PRC — slab cards ------------------------------
            function gacBgtBindSlab(opts) {
                var bands = [];
                var defaults = opts.defaults || [];
                var labels = opts.labels || [];
                var colors = opts.colors || [];
                var fromKey = opts.fromKey;
                var toKey = opts.toKey;
                var minBgt = opts.minBgt != null ? opts.minBgt : -100000;

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
                function repaint() {
                    gacBgtPaintCard(opts.key, bands, fromKey, toKey, opts.valueOfRow);
                }
                function render() {
                    var tbody = document.getElementById(opts.tbodyId);
                    if (!tbody) return;
                    var canDelete = bands.length > 1;
                    tbody.innerHTML = '';
                    bands.forEach(function(band, i) {
                        var tr = document.createElement('tr');
                        tr.innerHTML = ''
                            + '<td class="text-muted small">' + (i + 1) + '</td>'
                            + '<td><input type="number" step="' + (opts.step || '1') + '" class="form-control form-control-sm" value="' + (band[fromKey] != null ? band[fromKey] : '') + '" data-idx="' + i + '" data-field="' + fromKey + '"></td>'
                            + '<td><input type="number" step="' + (opts.step || '1') + '" class="form-control form-control-sm" value="' + (band[toKey] != null ? band[toKey] : '') + '" data-idx="' + i + '" data-field="' + toKey + '"></td>'
                            + '<td><input type="number" step="1" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt"></td>'
                            + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '"' + (canDelete ? '' : ' disabled') + ' title="Delete this slab" aria-label="Delete slab"><i class="fas fa-trash"></i></button></td>';
                        tbody.appendChild(tr);
                    });
                    tbody.querySelectorAll('input[data-idx]').forEach(function(inp) {
                        inp.addEventListener('input', function() {
                            var idx = +this.dataset.idx, fld = this.dataset.field;
                            if (!bands[idx]) return;
                            if (fld === 'bgt') bands[idx][fld] = (this.value === '' ? '' : parseInt(this.value, 10));
                            else bands[idx][fld] = (this.value === '' ? '' : parseFloat(this.value));
                            repaint();
                        });
                    });
                    tbody.querySelectorAll('[data-remove-idx]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            if (bands.length <= 1) return;
                            bands.splice(+this.dataset.removeIdx, 1);
                            render();
                        });
                    });
                    repaint();
                }
                function loadFromRule(rule) {
                    bands = normalize((rule && Array.isArray(rule.bands)) ? rule.bands : []);
                    render();
                }
                function fetchAndLoad() {
                    return fetch(opts.getUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(body) { loadFromRule((body && body.rule) || {}); })
                        .catch(function() { loadFromRule({}); });
                }
                /** Returns an object with cleaned (Array or null) and error (string). */
                function collect() {
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
                    if (!cleaned.length) return { cleaned: null, error: 'Add at least one slab before saving.' };
                    for (var i = 0; i < cleaned.length; i++) {
                        var b = cleaned[i];
                        if (!isFinite(b[fromKey]) || !isFinite(b[toKey]) || !isFinite(b.bgt)) return { cleaned: null, error: 'Every slab needs numeric From, To, and Bgt values.' };
                        if (b[fromKey] > b[toKey]) return { cleaned: null, error: 'Each slab needs From ≤ To.' };
                        if (b.bgt < minBgt) return { cleaned: null, error: 'Bgt must be ' + minBgt + ' or more.' };
                    }
                    return { cleaned: cleaned, error: '' };
                }
                /** @return {Promise<string>} resolves '' on success, else an error message. */
                function save(cleaned) {
                    return fetch(opts.saveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': gacCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var body = out.body || {};
                            if (!out.ok || body.status === 422 || body.status === 500) return body.message || body.error || 'Save failed.';
                            return '';
                        });
                }

                var addBtn = document.getElementById(opts.addBtnId);
                if (addBtn) {
                    addBtn.addEventListener('click', function() {
                        var last = bands.length ? bands[bands.length - 1] : null;
                        var lastTo = last ? parseFloat(last[toKey]) : NaN;
                        var from = isFinite(lastTo) ? lastTo : 0;
                        var i = bands.length;
                        var row = { bgt: 0, label: labels[i] || ('Slab ' + (i + 1)), color: colors[i] || '#6c757d' };
                        row[fromKey] = from;
                        row[toKey] = 9999;
                        bands.push(row);
                        render();
                    });
                }

                var ctrl = { key: opts.key, errId: opts.errId, repaint: repaint, fetchAndLoad: fetchAndLoad, collect: collect, save: save };
                gacBgtSlabCtrls[opts.key] = ctrl;
                return ctrl;
            }

            gacBgtBindSlab({
                key: 'views',
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
                fromKey: 'views_from', toKey: 'views_to', step: '1', minBgt: -100000,
                tbodyId: 'gac-bgt-views-bands-body',
                addBtnId: 'gac-bgt-views-add-band-btn', errId: 'gacRawBgtViewsRuleErr',
                getUrl: gacBgtViewsRuleGetUrl, saveUrl: gacBgtViewsRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && row.views_l7); return isFinite(n) ? n : 0; }
            });
            gacBgtBindSlab({
                key: 'cvr',
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
                fromKey: 'cvr_from', toKey: 'cvr_to', step: '0.01', minBgt: -100000,
                tbodyId: 'gac-bgt-cvr-bands-body',
                addBtnId: 'gac-bgt-cvr-add-band-btn', errId: 'gacRawBgtCvrRuleErr',
                getUrl: gacBgtCvrRuleGetUrl, saveUrl: gacBgtCvrRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && (row.bgt_cvr_page_cvr != null ? row.bgt_cvr_page_cvr : row.cvr_l30)); return isFinite(n) ? n : 0; }
            });
            gacBgtBindSlab({
                key: 'prc',
                defaults: [
                    { prc_from: 151, prc_to: 9999, bgt: 5, label: 'Pink', color: '#e83e8c' },
                    { prc_from: 101, prc_to: 150, bgt: 4, label: 'Green', color: '#28a745' },
                    { prc_from: 61, prc_to: 100, bgt: 3, label: 'Blue', color: '#2563eb' },
                    { prc_from: 41, prc_to: 60, bgt: 2, label: 'Yellow', color: '#ffc107' },
                    { prc_from: 0, prc_to: 40, bgt: 1, label: 'Red', color: '#a00211' }
                ],
                labels: ['Pink', 'Green', 'Blue', 'Yellow', 'Red'],
                colors: ['#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'],
                fromKey: 'prc_from', toKey: 'prc_to', step: '0.01', minBgt: -100000,
                tbodyId: 'gac-bgt-prc-bands-body',
                addBtnId: 'gac-bgt-prc-add-band-btn', errId: 'gacRawBgtPrcRuleErr',
                getUrl: gacBgtPrcRuleGetUrl, saveUrl: gacBgtPrcRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && row.bgt_prc_price); return isFinite(n) ? n : null; }
            });
            gacBgtBindSlab({
                key: 'dil',
                defaults: [
                    { dil_from: 50, dil_to: 9999, bgt: 0, label: 'Pink', color: '#e83e8c' },
                    { dil_from: 25, dil_to: 50, bgt: 0, label: 'Green', color: '#28a745' },
                    { dil_from: 0, dil_to: 25, bgt: 0, label: 'Red', color: '#a00211' }
                ],
                labels: ['Pink', 'Green', 'Red', 'Blue', 'Yellow', 'Purple'],
                colors: ['#e83e8c', '#28a745', '#a00211', '#2563eb', '#ffc107', '#7c3aed'],
                fromKey: 'dil_from', toKey: 'dil_to', step: '0.01', minBgt: -100000,
                tbodyId: 'gac-bgt-dil-bands-body',
                addBtnId: 'gac-bgt-dil-add-band-btn', errId: 'gacRawBgtDilRuleErr',
                getUrl: gacBgtDilRuleGetUrl, saveUrl: gacBgtDilRuleSaveUrl,
                valueOfRow: function(row) { var n = parseFloat(row && row.bgt_dil_value); return isFinite(n) ? n : null; }
            });

            // ---- Modal open: load every rule, then paint ----------------------------
            var gacBgtModalEl = document.getElementById('gacRawBgtRulesModal');
            var gacBgtSaveBtn = document.getElementById('gacRawBgtRulesSaveBtn');
            var GAC_BGT_ERR_IDS = ['gacRawBgtRulesErr', 'gacRawSbgtRuleErr'];

            function gacBgtClearErrors() {
                GAC_BGT_ERR_IDS.forEach(function(id) { gacBgtShowErr(id, ''); });
                Object.keys(gacBgtSlabCtrls).forEach(function(k) { gacBgtShowErr(gacBgtSlabCtrls[k].errId, ''); });
            }

            if (gacBgtModalEl) {
                gacBgtModalEl.addEventListener('show.bs.modal', function() {
                    gacBgtClearErrors();
                    gacBgtRulesLoaded = false;
                    if (gacBgtSaveBtn) gacBgtSaveBtn.disabled = true;
                    var loads = [
                        new Promise(function(resolve) {
                            gacRefreshRuleFromServer(function() {
                                gacLoadSbgtBandsFromRule((window.gacRawRule && window.gacRawRule.sbgt) || {});
                                var sbOpt = (window.gacRawRule && window.gacRawRule.sbgt) || {};
                                var pauseChk = document.getElementById('gac-bgt-pause-inv0');
                                var maxPctEl = document.getElementById('gac-bgt-max-pct');
                                if (pauseChk) pauseChk.checked = sbOpt.pause_if_inv_zero !== false;
                                if (maxPctEl) maxPctEl.value = (sbOpt.max_pct_of_price != null ? sbOpt.max_pct_of_price : 10);
                                var pausePriceEl = document.getElementById('gac-bgt-pause-price');
                                if (pausePriceEl) pausePriceEl.value = (sbOpt.pause_price_below != null ? sbOpt.pause_price_below : 20);
                                var pauseDilEl = document.getElementById('gac-bgt-pause-dil');
                                if (pauseDilEl) pauseDilEl.value = (sbOpt.pause_dil_above != null ? sbOpt.pause_dil_above : 50);
                                resolve();
                            });
                        })
                    ];
                    Object.keys(gacBgtSlabCtrls).forEach(function(k) { loads.push(gacBgtSlabCtrls[k].fetchAndLoad()); });
                    Promise.all(loads).then(function() {
                        gacBgtRulesLoaded = true;
                        if (gacBgtSaveBtn) gacBgtSaveBtn.disabled = false;
                        gacBgtPaintAll();
                    });
                });
                gacBgtModalEl.addEventListener('shown.bs.modal', function() { gacBgtPaintAll(); });
                gacBgtModalEl.addEventListener('hidden.bs.modal', function() {
                    Object.keys(gacBgtCharts).forEach(function(k) {
                        if (gacBgtCharts[k]) { try { gacBgtCharts[k].destroy(); } catch (e) {} gacBgtCharts[k] = null; }
                    });
                });
            }

            // ---- Save & apply: validate everything first, then write every rule ------
            if (gacBgtSaveBtn) {
                gacBgtSaveBtn.addEventListener('click', function() {
                    if (!gacBgtRulesLoaded) return;
                    gacBgtClearErrors();

                    // 1) Validate every card before anything is written.
                    var failed = false;
                    var sbgtBands = gacCollectSbgtBandsPayload();
                    if (!sbgtBands.length) {
                        gacBgtShowErr('gacRawSbgtRuleErr', 'Add at least one band before saving.');
                        failed = true;
                    } else {
                        for (var i = 0; i < sbgtBands.length; i++) {
                            var sb = sbgtBands[i];
                            if (!isFinite(sb.acos_from) || !isFinite(sb.acos_to) || !isFinite(sb.sbgt)) {
                                gacBgtShowErr('gacRawSbgtRuleErr', 'Every band needs numeric From, To, and SBGT values.');
                                failed = true;
                                break;
                            }
                            if (sb.acos_from > sb.acos_to) {
                                gacBgtShowErr('gacRawSbgtRuleErr', 'Each band needs From ≤ To.');
                                failed = true;
                                break;
                            }
                        }
                    }
                    var slabPayloads = {};
                    Object.keys(gacBgtSlabCtrls).forEach(function(k) {
                        var res = gacBgtSlabCtrls[k].collect();
                        if (res.error) {
                            gacBgtShowErr(gacBgtSlabCtrls[k].errId, res.error);
                            failed = true;
                        } else {
                            slabPayloads[k] = res.cleaned;
                        }
                    });
                    if (failed) {
                        gacBgtShowErr('gacRawBgtRulesErr', 'Nothing was saved. Fix the highlighted rule(s) and try again.');
                        return;
                    }

                    // 2) Write the ACOS + SBID rule, then each slab rule.
                    var token = gacCsrfToken();
                    gacBgtSaveBtn.disabled = true;
                    var savedAny = false;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ sbgt: { bands: sbgtBands, pause_if_inv_zero: gacBgtPauseInv0(), max_pct_of_price: gacBgtMaxPct(), pause_price_below: gacBgtPausePrice(), pause_dil_above: gacBgtPauseDil() }, sbid: gacCollectSbid() }),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok) {
                                throw { slot: 'gacRawSbgtRuleErr', message: b.message || b.error || 'Save failed.' };
                            }
                            savedAny = true;
                            window.gacRawRule = b.rule || window.gacRawRule;
                            var chain = Promise.resolve();
                            Object.keys(slabPayloads).forEach(function(k) {
                                chain = chain.then(function() {
                                    return gacBgtSlabCtrls[k].save(slabPayloads[k]).then(function(msg) {
                                        if (msg) throw { slot: gacBgtSlabCtrls[k].errId, message: msg };
                                        savedAny = true;
                                    });
                                });
                            });
                            return chain;
                        })
                        .then(function() {
                            if (typeof bootstrap !== 'undefined' && gacBgtModalEl) {
                                var inst = bootstrap.Modal.getInstance(gacBgtModalEl);
                                if (inst) inst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { gacRawRefreshTableUiSoon(); })
                        .catch(function(err) {
                            var slot = (err && err.slot) || 'gacRawBgtRulesErr';
                            var msg = (err && err.message) || 'Network or server error.';
                            gacBgtShowErr(slot, msg);
                            gacBgtShowErr('gacRawBgtRulesErr', savedAny
                                ? 'Some rules were saved before this error. Fix it and Save & apply again.'
                                : 'Nothing was saved.');
                        })
                        .finally(function() { gacBgtSaveBtn.disabled = false; });
                });
            }

            // ---- Minimize / expand cards (same behaviour as /amazon-ads/all) ---------
            (function() {
                var minKey = 'gacBgtPanelMin';
                var maxKey = null;
                function minSet() {
                    try {
                        var raw = JSON.parse(localStorage.getItem(minKey) || '[]');
                        return new Set(Array.isArray(raw) ? raw : []);
                    } catch (e) { return new Set(); }
                }
                function saveMin(set) {
                    try { localStorage.setItem(minKey, JSON.stringify(Array.from(set))); } catch (e) {}
                }
                function resizeCharts() {
                    Object.keys(gacBgtCharts).forEach(function(k) {
                        if (gacBgtCharts[k]) { try { gacBgtCharts[k].resize(); } catch (e) {} }
                    });
                }
                function paintMin() {
                    var min = minSet();
                    var cols = document.querySelector('#gacRawBgtRulesModal .gac-bgt-cols');
                    if (cols) cols.classList.toggle('is-expanded', !!maxKey);
                    document.querySelectorAll('#gacRawBgtRulesModal [data-gac-bgt-panel]').forEach(function(el) {
                        var key = el.getAttribute('data-gac-bgt-panel');
                        var on = min.has(key);
                        var expanded = key === maxKey;
                        el.classList.toggle('is-min', on && !expanded);
                        el.classList.toggle('is-max', expanded);
                        var title = ((el.querySelector('.gac-bgt-title') || {}).textContent || 'rule').trim();
                        var btn = el.querySelector('.gac-bgt-min-btn');
                        if (btn) {
                            btn.textContent = (on && !expanded) ? '+' : '−';
                            btn.title = ((on && !expanded) ? 'Maximize ' : 'Minimize ') + title;
                            btn.setAttribute('aria-label', btn.title);
                        }
                        var exp = el.querySelector('.gac-bgt-exp-btn');
                        if (exp) {
                            exp.textContent = expanded ? '⤡' : '⤢';
                            exp.title = (expanded ? 'Restore ' : 'Expand ') + title;
                            exp.setAttribute('aria-label', exp.title);
                        }
                    });
                    resizeCharts();
                }
                document.addEventListener('click', function(e) {
                    var t = e.target;
                    if (!t || !t.closest) return;
                    var expBtn = t.closest('#gacRawBgtRulesModal .gac-bgt-exp-btn');
                    if (expBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var colX = expBtn.closest('[data-gac-bgt-panel]');
                        if (!colX) return;
                        var kx = colX.getAttribute('data-gac-bgt-panel');
                        var minX = minSet();
                        if (maxKey === kx) {
                            maxKey = null;
                        } else {
                            maxKey = kx;
                            minX.delete(kx);
                            saveMin(minX);
                        }
                        paintMin();
                        setTimeout(resizeCharts, 60);
                        return;
                    }
                    var btn = t.closest('#gacRawBgtRulesModal .gac-bgt-min-btn');
                    var col = btn
                        ? btn.closest('[data-gac-bgt-panel]')
                        : (t.closest('#gacRawBgtRulesModal [data-gac-bgt-panel].is-min'));
                    if (!col) return;
                    e.preventDefault();
                    var key = col.getAttribute('data-gac-bgt-panel');
                    var min = minSet();
                    if (maxKey === key) maxKey = null;
                    if (min.has(key)) min.delete(key); else min.add(key);
                    saveMin(min);
                    paintMin();
                    setTimeout(resizeCharts, 60);
                });
                if (gacBgtModalEl) {
                    gacBgtModalEl.addEventListener('show.bs.modal', function() { maxKey = null; paintMin(); });
                }
            })();
        });
    </script>
@endsection
