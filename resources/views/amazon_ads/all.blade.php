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
        /* Frozen checkbox + campaign name — same pin as eBay 3 (solid fill so scrolled cells don't show through) */
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-frozen {
            background-color: #dbeafe !important;
            z-index: 12 !important;
        }
        #amz-ads-raw-wrap .tabulator-row .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        #amz-ads-raw-wrap .tabulator-row.tabulator-selectable:hover .tabulator-frozen {
            background-color: #bbb !important;
        }
        #amz-ads-raw-wrap .tabulator-row.tabulator-selected .tabulator-frozen {
            background-color: #9ABCEA !important;
        }
        #amz-ads-raw-wrap .tabulator-row.tabulator-selected:hover .tabulator-frozen {
            background-color: #769BCC !important;
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
        /* Vertical column titles. Campaign name, checkbox, and task stay horizontal. */
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col {
            height: 118px !important;
            min-height: 118px;
            vertical-align: bottom;
            overflow: visible;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: 118px !important;
            min-height: 118px;
            padding: 0 0 14px !important;
            display: flex !important;
            align-items: flex-end;
            justify-content: center;
            box-sizing: border-box;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: nowrap !important;
            width: 100%;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap !important;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.15;
            padding: 4px 0;
            text-align: center;
            overflow: visible;
            text-overflow: clip;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            position: absolute !important;
            top: auto !important;
            bottom: 2px !important;
            left: 50% !important;
            right: auto !important;
            width: auto !important;
            height: auto !important;
            transform: translateX(-50%);
            justify-content: center;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignName"] .tabulator-col-title,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__sel"] .tabulator-col-title,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__task"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
            min-height: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap !important;
            padding: 5px 3px;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignName"] .tabulator-col-content,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__sel"] .tabulator-col-content,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="__task"] .tabulator-col-content {
            align-items: center;
            padding-bottom: 0 !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignName"] .tabulator-col-sorter {
            top: 0 !important;
            bottom: 0 !important;
            left: auto !important;
            right: 4px !important;
            transform: none !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignName"].tabulator-sortable .tabulator-col-title {
            padding-right: 14px !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-ads-raw-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell .amz-raw-status-cell { white-space: nowrap; }
        #amz-ads-raw-wrap .amz-sync-cell {
            display: inline-flex; align-items: center; justify-content: center; gap: 5px; white-space: nowrap;
        }
        .amz-sync-dot {
            width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        .amz-sync-dot.is-green { background: #16a34a; }
        .amz-push-alert {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            cursor: help;
        }
        .amz-active-again-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #16a34a;
            box-shadow: 0 0 0 1px rgba(22, 163, 74, 0.28);
            vertical-align: middle;
            cursor: default;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="activeAgain"] .tabulator-col-content {
            align-items: center;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="activeAgain"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
            min-height: 0 !important;
        }
        .amz-sync-dot.is-yellow { background: #f59e0b; }
        .amz-sync-dot.is-red { background: #dc2626; }
        .amz-sync-head {
            display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
            gap: 4px; line-height: 1.15; height: 100%; width: 100%;
        }
        .amz-sync-head-title {
            font-weight: 700;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .amz-sync-head-badges { display: inline-flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
        .amz-sync-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700;
            line-height: 1.2; border: 1px solid #e2e8f0; cursor: pointer;
            user-select: none; background: #fff; color: #334155;
        }
        .amz-sync-badge .amz-sync-dot { width: 7px; height: 7px; }
        .amz-sync-badge.is-green { color: #166534; border-color: #86efac; background: #f0fdf4; }
        .amz-sync-badge.is-yellow { color: #92400e; border-color: #fcd34d; background: #fffbeb; }
        .amz-sync-badge.is-red { color: #991b1b; border-color: #fca5a5; background: #fef2f2; }
        .amz-sync-badge.is-active { box-shadow: 0 0 0 2px currentColor; }
        .amz-sync-toolbar-group {
            display: inline-flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; padding: 6px 10px; border-radius: 8px;
            background: #fff; border: 1px solid #e2e8f0; line-height: 1.15;
        }
        .amz-sync-toolbar-label { font-size: 12px; font-weight: 800; color: #0f172a; letter-spacing: 0.02em; }
        #amz-ads-raw-wrap .amz-sync-badge {
            padding: 1px 5px; font-size: 10px; gap: 3px;
        }
        #amz-ads-raw-wrap .amz-sync-badge .amz-sync-dot { width: 6px; height: 6px; }
        #amz-ads-raw-wrap .amz-sync-head-badges { gap: 3px; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="bgt"] .tabulator-col-title,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="last_sbid"] .tabulator-col-title,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sbid"] .tabulator-col-title {
            overflow: visible;
        }
        #amz-ads-raw-wrap .amz-camp-skus-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 16px; height: 16px; padding: 0; margin-left: 4px; flex-shrink: 0;
            border: 1px solid #93c5fd; border-radius: 50%; background: #eff6ff;
            color: #2563eb; font-size: 9px; line-height: 1; cursor: pointer;
        }
        #amz-ads-raw-wrap .amz-camp-skus-btn:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        #amz-ads-raw-wrap .amz-low-inv-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 16px; height: 16px; padding: 0; margin-left: 4px; flex-shrink: 0;
            border: 0; border-radius: 50%; background: #dc2626; color: #fff;
            font-size: 11px; font-weight: 700; line-height: 1; cursor: pointer;
        }
        #amz-ads-raw-wrap .amz-low-inv-btn:hover { background: #991b1b; }
        .amz-sku-inv-num { font-weight: 700; font-size: 13px; line-height: 1; }
        .amz-sku-inv-num.is-low { color: #dc2626; }
        .amz-sku-img-cell { text-align: center; width: 64px; }
        .amz-sku-inv-img { width: 48px; height: 48px; object-fit: contain; border-radius: 4px; background: #f8fafc; }
        .amz-sku-lmp-btn { font-weight: 700; text-decoration: none; padding: 0; }
        #amazonAdsSkuLmpModal { z-index: 1065; }
        #amazonAdsSkuLmpModal .amz-sku-lmp-low { background: #dcfce7; }
        .amz-cpc-avg-cell {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap;
        }
        .amz-cpc-avg-history-dot {
            width: 8px; height: 8px; border: 0; padding: 0; border-radius: 50%;
            background: #166534; cursor: pointer; flex-shrink: 0;
        }
        .amz-cpc-avg-history-dot.is-up { background: #05bd30; }
        .amz-cpc-avg-history-dot.is-down { background: #ff2727; }
        .amz-cpc-avg-history-dot.is-flat { background: #9ca3af; }
        .amz-cpc-avg-history-dot:hover { transform: scale(1.35); }
        /* History icon variant: a colored history glyph instead of a plain dot. */
        .amz-cpc-avg-history-dot.amz-hist-as-icon {
            width: auto; height: auto; background: transparent; border-radius: 0;
            line-height: 1; font-size: 12px; color: #166534;
        }
        .amz-cpc-avg-history-dot.amz-hist-as-icon.is-up { background: transparent; color: #05bd30; }
        .amz-cpc-avg-history-dot.amz-hist-as-icon.is-down { background: transparent; color: #ff2727; }
        .amz-cpc-avg-history-dot.amz-hist-as-icon.is-flat { background: transparent; color: #9ca3af; }
        .amz-cpc-avg-history-dot.amz-hist-as-icon:hover { transform: scale(1.25); }
        .amz-l1-cell {
            display: inline-flex; align-items: center; justify-content: center; gap: 5px; white-space: nowrap;
        }
        .amz-lrange-btn {
            border: 0; padding: 0; background: transparent; line-height: 1; font-size: 12px;
            color: #2563eb; cursor: pointer;
        }
        .amz-lrange-btn:hover { color: #1d4ed8; transform: scale(1.2); }
        #amazonAdsLRangeModal.modal { z-index: 1085; }
        #amazonAdsLRangeModal .modal-dialog { max-width: 820px; }
        .amz-lrange-boxes {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
        }
        .amz-lrange-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            padding: 10px 8px;
            text-align: center;
            min-height: 88px;
        }
        .amz-lrange-box-ln { font-size: 12px; font-weight: 700; color: #0f172a; }
        .amz-lrange-box-date { font-size: 11px; color: #64748b; margin: 4px 0 8px; }
        .amz-lrange-box-val { font-size: 15px; font-weight: 700; color: #1d4ed8; }
        .amz-lrange-box.is-empty .amz-lrange-box-val { color: #94a3b8; font-weight: 600; }
        @media (max-width: 767px) {
            .amz-lrange-boxes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        /* CPC history modal — same full-width layout as Active Channel */
        #amazonAdsCpcAvgHistoryModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #amazonAdsCpcAvgHistoryModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #amazonAdsCpcAvgHistoryModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
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
        #amz-ads-raw-wrap .tabulator .tabulator-cell.amz-spl30-high,
        #amz-ads-raw-wrap .tabulator .tabulator-cell.amz-spl30-high .fw-semibold {
            background: #dc2626 !important;
            color: #fff !important;
            font-weight: 700;
        }
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
        #amz-raw-filter-bar .amz-stat-filter { min-width: 150px; }
        #amz-raw-filter-bar .amz-stat-filter-btn { cursor: pointer; color: #334155; }
        #amz-raw-filter-bar .amz-stat-filter-menu {
            min-width: 168px; padding: 6px; border: 0; border-radius: 10px;
            background: #3f3f46; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.28);
        }
        #amz-raw-filter-bar .amz-stat-filter-opt {
            display: flex; align-items: center; gap: 8px; margin: 0; padding: 6px 10px;
            border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        #amz-raw-filter-bar .amz-stat-filter-opt:hover { background: rgba(255, 255, 255, 0.08); }
        #amz-raw-filter-bar .amz-stat-filter-opt.is-on { background: #3b82f6; }
        #amz-raw-filter-bar .amz-stat-filter-opt input { margin: 0; accent-color: #fff; }
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
        .amz-stat-badge--acos.is-high { background: #dc2626; }
        .amz-stat-badge--spend    { background: #ef4444; }
        .amz-stat-badge--clicks   { background: #f59e0b; }
        .amz-stat-badge--sold     { background: #8b5cf6; }
        .amz-stat-badge--cvr      { background: #16a34a; }
        .amz-stat-badge--cpc      { background: #0891b2; }
        .amz-stat-badge--sales    { background: #16a34a; }
        .amz-stat-badge--budget   { background: #0f766e; }
        .amz-stat-badge--month    { background: #0e7490; }
        .amz-metric-blocks {
            display: flex; flex-wrap: wrap; gap: 8px; flex: 1 1 720px; min-width: 0;
        }
        .amz-metric-block {
            display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
            padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;
        }
        .amz-metric-block-label {
            font-size: 12px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #334155;
        }
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

        #amz-ads-raw-wrap .tabulator .tabulator-row .tabulator-cell.amz-task-cell {
            cursor: pointer;
        }
        .amz-task-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.4rem;
            min-width: 1.4rem;
            height: 1.4rem;
            padding: 0;
            margin: 0;
            border: none;
            border-radius: 7px;
            background: linear-gradient(135deg, #0d9488, #14b8a6);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(13, 148, 136, 0.4), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .amz-task-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(13, 148, 136, 0.5), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
            background: linear-gradient(135deg, #0f766e, #0d9488);
            color: #fff;
        }

        #amz-ads-column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #amz-ads-column-dropdown-menu > li.col-vis-full { list-style: none; }
        #amz-ads-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(4, minmax(140px, 1fr));
            gap: 8px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #amz-ads-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #amz-ads-column-dropdown-menu .col-vis-group.col-vis-drop-over {
            border-color: #0d6efd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
        }
        #amz-ads-column-dropdown-menu .col-vis-group-title {
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
        #amz-ads-column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #amz-ads-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #amz-ads-column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 4px;
            cursor: grab;
        }
        #amz-ads-column-dropdown-menu .col-vis-item:active { cursor: grabbing; }
        #amz-ads-column-dropdown-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
        #amz-ads-column-dropdown-menu .col-vis-item > label {
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
        #amz-ads-column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        #amz-ads-column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        #amazonAdsBgtRulesModal .modal-dialog {
            width: calc(100vw - 0.75rem);
            max-width: calc(100vw - 0.75rem);
            height: calc(100vh - 0.75rem);
            max-height: calc(100vh - 0.75rem);
            margin: 0.375rem auto;
        }
        #amazonAdsBgtRulesModal .modal-content {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #amazonAdsBgtRulesModal .modal-header {
            background: #fff;
            border-bottom: 1px solid #e8eef5;
            padding: 8px 12px;
            flex: 0 0 auto;
        }
        #amazonAdsBgtRulesModal .modal-title { font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
        #amazonAdsBgtRulesModal .amz-bgt-sub {
            color: #64748b;
            font-size: 11px;
            font-weight: 500;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(100vw - 5rem);
        }
        #amazonAdsBgtRulesModal .modal-body {
            background: #f4f7fb;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 8px;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        #amazonAdsBgtRulesModal .modal-footer {
            background: #fff;
            border-top: 1px solid #e8eef5;
            padding: 6px 12px;
            flex: 0 0 auto;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar {
            flex: 0 0 auto;
            align-self: stretch;
            width: 100%;
            max-width: none;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            margin-bottom: 8px;
            padding: 12px 14px 14px;
            overflow: visible;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-col-head { margin: 0; flex: 0 0 auto; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-title { font-size: 16px; }
        #amazonAdsBgtRulesModal .amz-bgt-sum-sub { color: #64748b; font-size: 12px; font-weight: 500; margin-top: 1px; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badges { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badge {
            display: flex; flex-direction: column; justify-content: center; min-width: 92px;
            padding: 8px 12px; border-radius: 10px; background: #fff;
            border: 1px solid #e8eef5; border-left: 4px solid var(--bgt-c, #64748b); line-height: 1.15;
        }
        #amazonAdsBgtRulesModal .amz-bgt-budget-amt { font-size: 16px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-n { margin-top: 3px; font-size: 13px; font-weight: 700; color: #334155; font-variant-numeric: tabular-nums; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-day { margin-top: 2px; font-size: 11px; font-weight: 600; color: #64748b; font-variant-numeric: tabular-nums; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badge--total {
            min-width: 148px; background: #0f766e; border: 0; margin-left: auto;
        }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badge--total .amz-bgt-budget-amt { font-size: 12px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #ccfbf1; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badge--total .amz-bgt-budget-n { font-size: 22px; color: #fff; }
        #amazonAdsBgtRulesModal .amz-bgt-budget-badge--total .amz-bgt-budget-day { color: #ccfbf1; }
        #amazonAdsBgtRulesModal .amz-bgt-sum-body {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            align-items: stretch;
            min-width: 0;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-chart {
            flex: 1 1 auto;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            margin: 0;
            min-width: 0;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-chart-canvas { flex: 0 0 180px; width: 100%; height: 180px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-chart-canvas,
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-chart-canvas canvas { height: 180px !important; flex-basis: 180px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin: 0;
            flex: 0 0 auto;
            font-size: 13px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-leg-row {
            display: inline-flex;
            width: auto;
            gap: 6px;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e8eef5;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 13px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-leg-row strong { font-size: 14px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-leg-total {
            border-top: 0;
            margin: 0;
            padding: 4px 10px;
            background: #0f172a;
            color: #fff;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-leg-total .amz-bgt-leg-pct { color: #cbd5e1; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-swatch { width: 10px; height: 10px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .table-responsive { flex: 1 1 300px; width: auto; min-width: 280px; max-width: 420px; overflow: visible; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table {
            display: table;
            width: 100%;
            height: 100%;
            border: 1px solid #e8eef5;
            border-radius: 10px;
            overflow: hidden;
            font-size: 14px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table thead { display: table-header-group; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table tbody { display: table-row-group; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table tr { display: table-row; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table th,
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table td {
            display: table-cell;
            padding: 7px 12px;
            font-size: 14px;
            line-height: 1.3;
            border-color: #eef2f7;
            background: #fff;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table thead th { font-size: 11px; padding: 8px 12px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table td:first-child { font-weight: 600; color: #0f172a; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar .amz-bgt-sum-table tr.fw-semibold td { background: #eff6ff; font-size: 15px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar.is-min {
            flex: 0 0 auto;
            flex-direction: row;
            width: 100%;
            height: auto;
            min-width: 0;
            padding: 6px 8px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar.is-min .amz-bgt-col-head { flex-direction: row; height: auto; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar.is-min .amz-bgt-title { writing-mode: horizontal-tb; transform: none; font-size: 12px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.amz-bgt-sumbar.is-min .amz-bgt-sum-sub { display: none; }
        #amazonAdsBgtRulesModal .amz-bgt-cols {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            align-content: flex-start;
            gap: 8px;
            width: 100%;
            min-width: 0;
            min-height: 0;
            flex: 0 0 auto;
            overflow: visible;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col {
            flex: 1 1 calc((100% - 24px) / 4);
            max-width: calc((100% - 24px) / 4);
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            border: 1px solid #e6edf5;
            border-radius: 10px;
            padding: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: auto;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col-head { display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 4px; }
        #amazonAdsBgtRulesModal .amz-bgt-title {
            font-weight: 700;
            font-size: 12px;
            color: #0f172a;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #amazonAdsBgtRulesModal .amz-bgt-head-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        #amazonAdsBgtRulesModal .amz-bgt-min-btn,
        #amazonAdsBgtRulesModal .amz-bgt-exp-btn { border: 0; background: #f1f5f9; color: #334155; width: 22px; height: 22px; border-radius: 6px; padding: 0; line-height: 1; font-weight: 700; flex-shrink: 0; font-size: 13px; }
        #amazonAdsBgtRulesModal .amz-bgt-min-btn:hover,
        #amazonAdsBgtRulesModal .amz-bgt-exp-btn:hover { background: #e2e8f0; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-min .amz-bgt-exp-btn { display: none; }
        #amazonAdsBgtRulesModal .amz-bgt-cols.is-expanded .amz-bgt-col:not(.is-max) { display: none; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-max { flex: 1 1 100%; min-width: 0; max-width: none; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-max .amz-bgt-chart-canvas { height: 220px; flex: 0 0 220px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-max .amz-bgt-chart-canvas canvas { height: 220px !important; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-min { flex: 0 0 40px; width: 40px; max-width: 40px; min-width: 40px; padding: 10px 4px; cursor: pointer; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-min > :not(.amz-bgt-col-head) { display: none !important; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-min .amz-bgt-col-head { flex-direction: column; justify-content: flex-start; height: 100%; margin: 0; gap: 10px; }
        #amazonAdsBgtRulesModal .amz-bgt-col.is-min .amz-bgt-title { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
        #amazonAdsBgtRulesModal .amz-bgt-chart { display: flex; flex-direction: column; margin-bottom: 4px; flex: 0 0 auto; }
        #amazonAdsBgtRulesModal .amz-bgt-chart-canvas { position: relative; width: 100%; height: 64px; flex: 0 0 64px; }
        #amazonAdsBgtRulesModal .amz-bgt-chart-canvas canvas { display: block; width: 100% !important; height: 64px !important; }
        #amazonAdsBgtRulesModal .amz-bgt-legend { width: 100%; font-size: 10px; margin-top: 2px; color: #334155; line-height: 1.2; }
        #amazonAdsBgtRulesModal .amz-bgt-leg-row {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr) auto auto;
            gap: 4px;
            align-items: center;
            padding: 0;
        }
        #amazonAdsBgtRulesModal .amz-bgt-leg-row strong { font-variant-numeric: tabular-nums; }
        #amazonAdsBgtRulesModal .amz-bgt-leg-total { font-weight: 700; border-top: 1px solid #e2e8f0; margin-top: 2px; padding-top: 2px; }
        #amazonAdsBgtRulesModal .amz-bgt-count-total td { font-weight: 700; background: #f8fafc; }
        #amazonAdsBgtRulesModal .amz-bgt-leg-pct { color: #94a3b8; font-variant-numeric: tabular-nums; min-width: 2.2rem; text-align: right; }
        #amazonAdsBgtRulesModal .amz-bgt-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #amazonAdsBgtRulesModal .amz-bgt-col .table { font-size: 11px; margin-bottom: 0; border-color: #e8eef5; }
        #amazonAdsBgtRulesModal .amz-bgt-col .table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            border-color: #e8eef5;
            white-space: nowrap;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .table th,
        #amazonAdsBgtRulesModal .amz-bgt-col .table td { padding: 1px 3px; border-color: #eef2f7; vertical-align: middle; }
        #amazonAdsBgtRulesModal .amz-bgt-col .table:not(.amz-bgt-sum-table) th:first-child,
        #amazonAdsBgtRulesModal .amz-bgt-col .table:not(.amz-bgt-sum-table) td:first-child { display: none; }
        #amazonAdsBgtRulesModal .amz-bgt-sum-table tr.fw-semibold { background: #f8fafc; }
        #amazonAdsBgtRulesModal .amz-bgt-col .form-control-sm {
            padding: 0 2px;
            font-size: 11px;
            min-width: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .form-control-sm:focus {
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .btn-outline-danger {
            border: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
            line-height: 1;
            font-size: 0.8em;
            border-radius: 0;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .btn-outline-danger:hover,
        #amazonAdsBgtRulesModal .amz-bgt-col .btn-outline-danger:focus,
        #amazonAdsBgtRulesModal .amz-bgt-col .btn-outline-danger:disabled {
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .btn-outline-danger:disabled { opacity: 0.35; }
        #amazonAdsBgtRulesModal .amz-bgt-add,
        #amazonAdsBgtRulesModal .amz-bgt-save {
            width: 100%;
            border-radius: 6px;
            font-weight: 600;
            padding: 3px 8px;
            font-size: 12px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-add { margin-top: auto; border-style: dashed; }
        #amazonAdsBgtRulesModal .amz-auto-sync-btn {
            border-radius: 999px;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            color: #64748b;
            min-width: 168px;
        }
        #amazonAdsBgtRulesModal .amz-auto-sync-btn.is-on {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        #amazonAdsBgtRulesModal #amazonAdsBgtSaveApplyBtn {
            font-weight: 700;
            border-radius: 8px;
            padding: 6px 16px;
        }
        #amazonAdsBgtRulesModal .amz-bgt-col .table-responsive { margin-bottom: 0; flex: 1 1 auto; min-height: 0; overflow: auto; }
        #amazonAdsSbidRuleModal .modal-dialog {
            width: min(1120px, calc(100vw - 1.25rem));
            max-width: calc(100vw - 1.25rem);
            margin: 0.625rem auto;
        }
        #amazonAdsSbidRuleModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        #amazonAdsSbidRuleModal .modal-header {
            background: #fff;
            border-bottom: 1px solid #e8eef5;
            padding: 14px 18px;
        }
        #amazonAdsSbidRuleModal .modal-title { font-weight: 700; color: #0f172a; letter-spacing: -0.01em; }
        #amazonAdsSbidRuleModal .amz-sbid-sub { color: #64748b; font-size: 12px; font-weight: 500; margin-top: 2px; }
        #amazonAdsSbidRuleModal .modal-body { background: #f4f7fb; padding: 14px 16px 16px; }
        #amazonAdsSbidRuleModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; padding: 10px 16px; }
        #amazonAdsSbidRuleModal .amz-sbid-thresholds {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 10px 14px;
            border: 1px solid #e6edf5;
            border-radius: 14px;
            background: #fff;
            font-size: 13px;
            color: #334155;
        }
        #amazonAdsSbidRuleModal .amz-sbid-thresholds label { font-weight: 600; margin: 0; }
        #amazonAdsSbidRuleModal .amz-sbid-thresholds .form-control {
            width: 88px;
            min-width: 88px;
            max-width: 88px;
        }
        #amazonAdsSbidRuleModal .amz-sbid-cols {
            display: flex;
            gap: 12px;
            align-items: stretch;
        }
        #amazonAdsSbidRuleModal .amz-sbid-col {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        #amazonAdsSbidRuleModal .amz-sbid-col-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            position: sticky;
            top: 0;
            z-index: 2;
            margin: -12px -12px 10px;
            padding: 10px 12px;
            background: #fff;
            border-bottom: 1px solid #e8eef5;
            border-radius: 14px 14px 0 0;
        }
        #amazonAdsSbidRuleModal .amz-sbid-title { font-weight: 700; font-size: 14px; color: #0f172a; letter-spacing: -0.01em; }
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="below"] .amz-sbid-col-head,
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="above"] .amz-sbid-col-head { justify-content: center; }
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="below"] .amz-sbid-title,
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="above"] .amz-sbid-title {
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            text-align: center;
        }
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="below"] .amz-sbid-title { background: #dc2626; }
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="above"] .amz-sbid-title { background: #ec4899; }
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="below"] .amz-sbid-min-btn,
        #amazonAdsSbidRuleModal .amz-sbid-col[data-amz-sbid-panel="above"] .amz-sbid-min-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }
        #amazonAdsSbidRuleModal .amz-sbid-min-btn { border: 0; background: #f1f5f9; color: #334155; width: 22px; height: 22px; border-radius: 6px; padding: 0; line-height: 1; font-weight: 700; flex-shrink: 0; }
        #amazonAdsSbidRuleModal .amz-sbid-min-btn:hover { background: #e2e8f0; }
        #amazonAdsSbidRuleModal .amz-sbid-col.is-min { flex: 0 0 40px; padding: 10px 4px; cursor: pointer; }
        #amazonAdsSbidRuleModal .amz-sbid-col.is-min > :not(.amz-sbid-col-head) { display: none !important; }
        #amazonAdsSbidRuleModal .amz-sbid-col.is-min .amz-sbid-col-head { flex-direction: column; justify-content: flex-start; height: 100%; margin: 0; padding: 0; gap: 10px; position: static; border: 0; border-radius: 0; background: transparent; }
        #amazonAdsSbidRuleModal .amz-sbid-col.is-min .amz-sbid-min-btn { position: static; transform: none; }
        #amazonAdsSbidRuleModal .amz-sbid-col.is-min .amz-sbid-title { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
        #amazonAdsSbidRuleModal .amz-sbid-chart-canvas { position: relative; width: 100%; height: 150px; }
        #amazonAdsSbidRuleModal .amz-sbid-chart-canvas canvas { display: block; width: 100% !important; height: 150px !important; }
        #amazonAdsSbidRuleModal .amz-sbid-legend { font-size: 12px; margin-top: 8px; color: #334155; }
        #amazonAdsSbidRuleModal .amz-sbid-leg-row {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr) auto auto;
            gap: 6px;
            align-items: center;
            padding: 2px 0;
        }
        #amazonAdsSbidRuleModal .amz-sbid-leg-head {
            display: grid;
            grid-template-columns: 8px minmax(0, 1fr) auto auto;
            gap: 6px;
            align-items: center;
            padding: 0 0 4px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        #amazonAdsSbidRuleModal .amz-sbid-leg-head span:nth-child(3),
        #amazonAdsSbidRuleModal .amz-sbid-leg-head span:nth-child(4) { text-align: right; }
        #amazonAdsSbidRuleModal .amz-sbid-leg-row strong { font-variant-numeric: tabular-nums; }
        #amazonAdsSbidRuleModal .amz-sbid-leg-pct { color: #94a3b8; font-variant-numeric: tabular-nums; min-width: 2.2rem; text-align: right; }
        #amazonAdsSbidRuleModal .amz-sbid-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #amazonAdsSbidRuleModal .amz-sbid-fields { margin-top: 10px; }
        #amazonAdsSbidRuleModal .amz-sbid-field {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 0;
            border-top: 1px solid #eef2f7;
            font-size: 12px;
            color: #334155;
        }
        #amazonAdsSbidRuleModal .amz-sbid-field .form-control { width: 88px; min-width: 88px; max-width: 88px; text-align: right; }
        #amazonAdsSbidRuleModal .amz-sbid-note { margin-top: auto; padding-top: 10px; color: #64748b; font-size: 12px; }
        #amazonAdsSbidRuleModal #amazonAdsSbidRuleSaveBtn { border-radius: 8px; font-weight: 600; padding: 6px 14px; }
        @media (max-width: 768px) {
            #amz-ads-column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(120px, 1fr));
            }
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
                        <div class="amz-metric-blocks">
                            <div class="amz-metric-block">
                                <span class="amz-metric-block-label">Last 30</span>
                                <span id="amazonAdsCampaignBadgeWrap" class="amz-stat-badge amz-stat-badge--campaign" title="Distinct campaigns matching Table + Stat + calendar. Amazon Enabled SP+SB is ~199; Stat=Enabled + All includes zero-activity ENABLED campaigns synced from Amazon.">CAMPAIGN:<span id="amazonAdsCampaignBadgeValue">0</span></span>
                                <span id="amazonAdsSpendBadgeWrap" class="amz-stat-badge amz-stat-badge--spend" title="Amazon L30 spend for the selected table (SP+SB on All). Includes paused campaigns that spent in L30 even if they have no row on the calendar day.">SPEND:<span id="amazonAdsSpendBadgeValue">$0</span></span>
                                <span id="amazonAdsSalesBadgeWrap" class="amz-stat-badge amz-stat-badge--sales" title="Ads sales (L30) — same Amazon L30 universe as Spend">ADS SALES:<span id="amazonAdsSalesBadgeValue">$0</span></span>
                                <span id="amazonAdsOverallAcosBadgeWrap" class="amz-stat-badge amz-stat-badge--acos" title="Overall ACOS from Amazon L30. Turns red when ACOS is above 40%.">ACOS%:<span id="amazonAdsOverallAcosBadgeValue">0%</span></span>
                                <span id="amazonAdsClicksBadgeWrap" class="amz-stat-badge amz-stat-badge--clicks" title="Clicks (L30) — same Amazon L30 universe as Spend">CLICKS:<span id="amazonAdsClicksBadgeValue">0</span></span>
                                <span id="amazonAdsSoldBadgeWrap" class="amz-stat-badge amz-stat-badge--sold" title="Sold (L30) — same Amazon L30 universe as Spend">SOLD:<span id="amazonAdsSoldBadgeValue">0</span></span>
                                <span id="amazonAdsCvrBadgeWrap" class="amz-stat-badge amz-stat-badge--cvr" title="Ads CVR = Ads Sold / Ads Clicks (L30)">CVR:<span id="amazonAdsCvrBadgeValue">0%</span></span>
                                <span id="amazonAdsCpcBadgeWrap" class="amz-stat-badge amz-stat-badge--cpc" title="CPC = Spend / Clicks">CPC:<span id="amazonAdsCpcBadgeValue">$0</span></span>
                            </div>
                            <div class="amz-metric-block">
                                <span class="amz-metric-block-label">Yesterday</span>
                                <span id="amazonAdsYesterdaySpendBadgeWrap" class="amz-stat-badge amz-stat-badge--spend" title="Yesterday spend (Amazon L1) for the current filters.">SPEND:<span id="amazonAdsYesterdaySpendBadgeValue">$0</span></span>
                                <span id="amazonAdsYesterdaySalesBadgeWrap" class="amz-stat-badge amz-stat-badge--sales" title="Yesterday ads sales (Amazon L1) for the current filters.">ADS SALES:<span id="amazonAdsYesterdaySalesBadgeValue">$0</span></span>
                                <span id="amazonAdsYesterdayAcosBadgeWrap" class="amz-stat-badge amz-stat-badge--acos" title="Yesterday ACOS% = yesterday spend ÷ yesterday ads sales.">ACOS%:<span id="amazonAdsYesterdayAcosBadgeValue">—</span></span>
                            </div>
                            <div class="amz-metric-block">
                                <span class="amz-metric-block-label">Projected</span>
                                <span id="amazonAdsProjectedSpendBadgeWrap" class="amz-stat-badge amz-stat-badge--spend" title="Projected spend = last 7 days of spend ÷ 7 × 30.">SPEND:<span id="amazonAdsProjectedSpendBadgeValue">$0</span></span>
                                <span id="amazonAdsProjectedSalesBadgeWrap" class="amz-stat-badge amz-stat-badge--sales" title="Projected ads sales = last 7 days of ads sales ÷ 7 × 30.">ADS SALES:<span id="amazonAdsProjectedSalesBadgeValue">$0</span></span>
                                <span id="amazonAdsProjectedAcosBadgeWrap" class="amz-stat-badge amz-stat-badge--acos" title="Projected ACOS% = projected spend ÷ projected ads sales.">ACOS%:<span id="amazonAdsProjectedAcosBadgeValue">—</span></span>
                            </div>
                            <div class="amz-metric-block">
                                <span class="amz-metric-block-label">Budget</span>
                                <span id="amazonAdsDailyBudgetBadgeWrap" class="amz-stat-badge amz-stat-badge--budget" title="Sum of floored SBGT across campaigns in the current filters. Open BGT Rules for the count at each budget.">DAILY BGT:<span id="amazonAdsDailyBudgetBadgeValue">$0</span></span>
                                <span id="amazonAdsMonthlyBudgetBadgeWrap" class="amz-stat-badge amz-stat-badge--month" title="Monthly budget = daily budget × 30.">MONTHLY BUDGET:<span id="amazonAdsMonthlyBudgetBadgeValue">$0</span></span>
                                <div class="amz-sync-toolbar-group" title="Click an Lbgt color to show only those rows. Click again to clear.">
                                    <span class="amz-sync-toolbar-label">Lbgt</span>
                                    <div class="amz-sync-head-badges" data-sync-field="bgt"></div>
                                </div>
                                <div class="amz-sync-toolbar-group" title="Click a BID color to show only those rows. Click again to clear.">
                                    <span class="amz-sync-toolbar-label">BID</span>
                                    <div class="amz-sync-head-badges" data-sync-field="bid"></div>
                                </div>
                            </div>
                        </div>

                        <div class="amz-ads-toolbar-actions">
                            <span id="amz-raw-total" class="badge bg-secondary">Total: —</span>
                            <span id="amz-raw-page-info" class="badge bg-light text-dark border">Page: —</span>
                            <button type="button" id="amz-raw-refresh" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh grid" aria-label="Refresh grid">
                                <i class="fa fa-refresh"></i>
                            </button>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                                    id="amz-ads-column-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                    aria-expanded="false" title="Columns">
                                    <i class="fas fa-columns"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" id="amz-ads-column-dropdown-menu" aria-labelledby="amz-ads-column-dropdown"></ul>
                            </div>
                            <button type="button" id="amazonAdsSectionExportBtn" class="btn btn-sm btn-success amz-raw-icon-btn" title="Export current page as CSV" aria-label="Export current page as CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <a href="{{ route('amazon-ads.push-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Failed / skipped bid & budget pushes">Fail Cpg</a>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtRulesBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtRulesModal" title="Edit ACOS, Views, CVR, Price, Reviews, and Dil budget rules together">BGT Rules</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsSbidRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsSbidRuleModal" title="Edit U2%/U1% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="amazonAdsPrRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsPrRuleModal" title="Auto-pause campaigns when Dil% is at or above your threshold. Only PARENT campaigns turn back on.">Pause Rule</button>
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
                                    <option value="all_reports" selected>All (SP + SB)</option>
                                    <option value="sp_reports">SP reports</option>
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
                            <div class="amz-raw-filter-field" id="amazonAdsFilterInvWrap">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterInv">Inv</label>
                                <select id="amazonAdsFilterInv" class="form-select form-select-sm amz-raw-filter-select" title="Shopify inventory on the Inv column">
                                    <option value="" selected>All</option>
                                    <option value="zero">= 0</option>
                                    <option value="gt">&gt; 0</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field">
                                <label class="amz-raw-filter-label mb-0" id="amazonAdsFilterCampaignStatusLabel">Stat</label>
                                <div class="dropdown amz-stat-filter" id="amazonAdsFilterCampaignStatus" data-bs-auto-close="outside">
                                    <button type="button" class="form-select form-select-sm amz-raw-filter-select amz-stat-filter-btn text-start" id="amazonAdsFilterCampaignStatusBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-labelledby="amazonAdsFilterCampaignStatusLabel">Enabled, Paused</button>
                                    <div class="dropdown-menu amz-stat-filter-menu">
                                        <label class="amz-stat-filter-opt"><input class="form-check-input" type="checkbox" value="" data-stat-all> All</label>
                                        <label class="amz-stat-filter-opt is-on"><input class="form-check-input" type="checkbox" value="ENABLED" data-stat data-label="Enabled" checked> Enabled</label>
                                        <label class="amz-stat-filter-opt is-on"><input class="form-check-input" type="checkbox" value="PAUSED" data-stat data-label="Paused" checked> Paused</label>
                                        <label class="amz-stat-filter-opt"><input class="form-check-input" type="checkbox" value="ARCHIVED" data-stat data-label="Archived"> Archived</label>
                                    </div>
                                </div>
                            </div>
                            <div class="amz-raw-filter-field" style="min-width:120px;">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterTargets">Targets</label>
                                <select id="amazonAdsFilterTargets" class="form-select form-select-sm amz-raw-filter-select" title="Filter by target count. M = 0. Under 50 red, 50–100 green, over 100 purple.">
                                    <option value="" selected>All</option>
                                    <option value="m" style="color:#dc3545">M (0)</option>
                                    <option value="lt50" style="color:#dc3545">&lt; 50</option>
                                    <option value="mid" style="color:#198754">50 – 100</option>
                                    <option value="gt100" style="color:#6f42c1">&gt; 100</option>
                                </select>
                            </div>
                            <div class="amz-raw-filter-field" style="min-width:140px;">
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterAcos">Acos</label>
                                <select id="amazonAdsFilterAcos" class="form-select form-select-sm amz-raw-filter-select" title="Filter by LT ACOS band (same ranges as BGT Vs ACOS)">
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

    <div class="modal fade p-0" id="amazonAdsCpcAvgHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amazonAdsCpcAvgHistoryTitle">CPC — Rolling 30 Days</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amazonAdsCpcAvgRange" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="amazonAdsCpcAvgHistoryContainer" style="height: 28vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="amazonAdsCpcAvgHistoryCanvas"></canvas>
                        </div>
                        <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div id="amazonAdsCpcAvgHighestLabel" style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="amazonAdsCpcAvgHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="amazonAdsCpcAvgMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div id="amazonAdsCpcAvgLowestLabel" style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="amazonAdsCpcAvgLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="amazonAdsCpcAvgHistoryLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="amazonAdsCpcAvgHistoryEmpty" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0" id="amazonAdsCpcAvgHistoryEmptyText">Daily CPC is not available for this campaign.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsLRangeModal" tabindex="-1" aria-labelledby="amazonAdsLRangeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsLRangeModalLabel">L1–L7</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2" id="amazonAdsLRangeModalSub"></p>
                    <div id="amazonAdsLRangeModalLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="amazonAdsLRangeModalError" role="alert"></p>
                    <div id="amazonAdsLRangeBoxes" class="amz-lrange-boxes d-none"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsCampaignSkusModal" tabindex="-1" aria-labelledby="amazonAdsCampaignSkusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-xl modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsCampaignSkusModalLabel">Campaign SKUs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="amazonAdsCampaignSkusModalSub"></p>
                    <div id="amazonAdsSbAdTools" class="d-none mb-2">
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="text" class="form-control form-control-sm" id="amazonAdsSbAdAddInput" placeholder="SKU or ASIN" style="max-width: 220px;" autocomplete="off">
                            <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSbAdAddBtn">Add to SB ad</button>
                            <label class="small mb-0 d-flex align-items-center gap-1" style="color:#334155;">
                                <input type="checkbox" id="amazonAdsSbShowParent" checked>
                                Show parent SKUs
                            </label>
                        </div>
                        <div class="small text-muted mt-1">SB only. Adds or removes products on the Amazon creative. Parent SKUs are the children of this campaign name.</div>
                    </div>
                    <div id="amazonAdsCampaignSkusLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="amazonAdsCampaignSkusError" role="alert"></p>
                    <p class="small text-success mb-2 d-none" id="amazonAdsCampaignSkusOk" role="status"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="amazonAdsCampaignSkusTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Inv</th>
                                    <th>SKU</th>
                                    <th>ASIN</th>
                                    <th>Item Price</th>
                                    <th>LMP</th>
                                    <th>Reviews</th>
                                    <th>State</th>
                                    <th class="amz-sb-ad-col d-none"></th>
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

    <div class="modal fade" id="amazonAdsSkuLmpModal" tabindex="-1" aria-labelledby="amazonAdsSkuLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsSkuLmpModalLabel">LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="amazonAdsSkuLmpModalSub">Item price plus paid shipping. Free shipping does not add.</p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Image</th>
                                    <th>ASIN</th>
                                    <th>Item Price</th>
                                    <th>Ship</th>
                                    <th>LMP</th>
                                    <th>Seller</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="amazonAdsSkuLmpTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsBgtRulesModal" tabindex="-1" aria-labelledby="amazonAdsBgtRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amazonAdsBgtRulesModalLabel">BGT Rules</h5>
                        <div class="amz-bgt-sub">SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil + Bgt Inv. Save and apply writes every rule, then refreshes the page. Auto Push &amp; Pull, when on, pulls live Amazon BGT and BID and pushes only mismatches.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="amz-bgt-col amz-bgt-sumbar" data-amz-bgt-panel="sum">
                            <div class="amz-bgt-col-head">
                                <div>
                                    <div class="amz-bgt-title" title="SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil + Bgt Inv. The total is floored. A total of 0 pauses the campaign.">Sum</div>
                                    <div class="amz-bgt-sum-sub">Each badge is one daily budget: campaign count, and what that group costs per day. Daily budget is the sum.</div>
                                </div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-min-btn" title="Minimize Sum" aria-label="Minimize Sum">−</button></span>
                            </div>
                            <div class="amz-bgt-budget-badges" id="amz-bgt-budget-badges"></div>
                            <div class="amz-bgt-sum-body">
                                <div class="amz-bgt-chart">
                                    <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-sum"></canvas></div>
                                    <div class="amz-bgt-legend" id="amz-bgt-leg-sum"></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 amz-bgt-sum-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Part</th>
                                                <th class="text-center">Campaigns</th>
                                                <th class="text-end">Budget</th>
                                            </tr>
                                        </thead>
                                        <tbody id="amz-bgt-sum-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <div class="amz-bgt-cols">
                        <div class="amz-bgt-col" data-amz-bgt-panel="acos">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive LT ACOS range. Use 9999 on To for the highest band. A $0 here is added as zero. The campaign pauses only when the six-part SBGT total is $0.">BGT Vs ACOS</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT Vs ACOS" aria-label="Minimize BGT Vs ACOS">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-acos"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-acos"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>SBGT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtRuleAddBandBtn">Add band</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="views">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive Amz page View L7 (parent Sess7). 0 is allowed for From and Bgt Views.">BGT Vs VIEWS</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT Vs VIEWS" aria-label="Minimize BGT Vs VIEWS">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-views"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-views"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtViewsRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtViewsRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtViewsRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtViewsRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="cvr">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive Amz page CVR L30 (parent A L30 ÷ Sess30 × 100). 0 is allowed for From and Bgt Cvr.">BGT Vs CVR</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT Vs CVR" aria-label="Minimize BGT Vs CVR">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-cvr"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-cvr"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtCvrRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtCvrRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtCvrRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtCvrRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="prc">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive Price range (Amz list, else LMP). 0 is allowed for From and Bgt Prc.">BGT PRC</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT PRC" aria-label="Minimize BGT PRC">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-prc"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-prc"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtPrcRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtPrcRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtPrcRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtPrcRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="reviews">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive Reviews star range, same rating as the Reviews column.">BGT Vs REVIEWS</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT Vs REVIEWS" aria-label="Minimize BGT Vs REVIEWS">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-reviews"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-reviews"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtReviewsRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtReviewsRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtReviewsRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtReviewsRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="dil">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive Dil% (ovl30 ÷ Inv × 100). Defaults: Pink 50%+, Green 25–50, Red under 25. 0 is allowed for From and Bgt Dil.">BGT Vs Dil</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize BGT Vs Dil" aria-label="Minimize BGT Vs Dil">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-dil"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-dil"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtDilRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtDilRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtDilRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtDilRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="inv">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive on-hand inventory (the Inv column). First matching slab wins, top to bottom. Default budgets are 0 until you set them.">Inv Rule</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize Inv Rule" aria-label="Minimize Inv Rule">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-inv"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-inv"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtInvRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtInvRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtInvRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtInvRuleModalError" role="alert"></p>
                        </div>

                        <div class="amz-bgt-col" data-amz-bgt-panel="spend">
                            <div class="amz-bgt-col-head">
                                <div class="amz-bgt-title" title="Inclusive L30 spend (the cost column). First matching slab wins, top to bottom. Negative From, To, and Bgt are allowed.">Spend Rule</div>
                                <span class="amz-bgt-head-actions"><button type="button" class="amz-bgt-exp-btn" title="Expand" aria-label="Expand">⤢</button><button type="button" class="amz-bgt-min-btn" title="Minimize Spend Rule" aria-label="Minimize Spend Rule">−</button></span>
                            </div>
                            <div class="amz-bgt-chart">
                                <div class="amz-bgt-chart-canvas"><canvas id="amz-bgt-chart-spend"></canvas></div>
                                <div class="amz-bgt-legend" id="amz-bgt-leg-spend"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtSpendRuleTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Bgt</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amazonAdsBgtSpendRuleBandsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary amz-bgt-add" id="amazonAdsBgtSpendRuleAddBandBtn">Add slab</button>
                            <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtSpendRuleModalError" role="alert"></p>
                        </div>

                        </div>
                    </div>
                <div class="modal-footer">
                    <div class="small text-muted me-auto" id="amz-bgt-status"></div>
                    <button type="button" class="btn btn-sm amz-auto-sync-btn" id="amazonAdsAutoPushPullBtn" aria-pressed="false" title="When on, each page load pulls live Amazon BGT and BID and pushes only values that differ. SBGT 0 pauses.">Auto Push &amp; Pull: OFF</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtSaveApplyBtn">Save and apply</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsSbidRuleModal" tabindex="-1" aria-labelledby="amazonAdsSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amazonAdsSbidRuleModalLabel">SBID rule</h5>
                        <div class="amz-sbid-sub">Both U7% and U1% below the low threshold use the CPC multipliers. If L1, L2, and L7 CPC are missing and Avg CPC exists, SBID = Avg CPC + 0.10; otherwise the fallback is used. Both above the high threshold use × L1 CPC. Everything else shows —.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="amz-sbid-thresholds">
                        <label for="amazonAdsSbidRuleUtilLow">Low %</label>
                        <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilLow" name="util_low" required title="Both U7% and U1% must be below this">
                        <label for="amazonAdsSbidRuleUtilHigh">High %</label>
                        <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilHigh" name="util_high" required title="Both U7% and U1% must be above this">
                        <span class="text-muted">Counts are every campaign in the current filters, the same set as the CAMPAIGN badge.</span>
                    </div>
                    <div class="amz-sbid-cols">
                        <div class="amz-sbid-col" data-amz-sbid-panel="below">
                            <div class="amz-sbid-col-head">
                                <div class="amz-sbid-title" title="Both U7% and U1% are below 66">&#60; Utilized 66</div>
                                <button type="button" class="amz-sbid-min-btn" title="Minimize < Utilized 66" aria-label="Minimize < Utilized 66">−</button>
                            </div>
                            <div class="amz-sbid-chart-canvas"><canvas id="amz-sbid-chart-below"></canvas></div>
                            <div class="amz-sbid-legend" id="amz-sbid-leg-below"></div>
                            <div class="amz-sbid-fields">
                                <div class="amz-sbid-field">
                                    <label class="mb-0" for="amazonAdsSbidRuleLowMultL1">× L1 CPC</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL1" name="both_low_mult_l1" required>
                                </div>
                                <div class="amz-sbid-field">
                                    <label class="mb-0" for="amazonAdsSbidRuleLowMultL2">× L2 CPC</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL2" name="both_low_mult_l2" required>
                                </div>
                                <div class="amz-sbid-field">
                                    <label class="mb-0" for="amazonAdsSbidRuleLowMultL7">× L7 CPC</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL7" name="both_low_mult_l7" required>
                                </div>
                                <div class="amz-sbid-field">
                                    <label class="mb-0" for="amazonAdsSbidRuleBothLowFallback">Fallback</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleBothLowFallback" name="both_low_fallback" required title="Used when L1, L2, and L7 CPC are missing and Avg CPC is missing">
                                </div>
                            </div>
                        </div>
                        <div class="amz-sbid-col" data-amz-sbid-panel="mid">
                            <div class="amz-sbid-col-head">
                                <div class="amz-sbid-title" title="U7% and U1% are not both below the low threshold and not both above the high threshold">Between</div>
                                <button type="button" class="amz-sbid-min-btn" title="Minimize Between" aria-label="Minimize Between">−</button>
                            </div>
                            <div class="amz-sbid-chart-canvas"><canvas id="amz-sbid-chart-mid"></canvas></div>
                            <div class="amz-sbid-legend" id="amz-sbid-leg-mid"></div>
                            <div class="amz-sbid-note">SBID shows — for these campaigns.</div>
                        </div>
                        <div class="amz-sbid-col" data-amz-sbid-panel="above">
                            <div class="amz-sbid-col-head">
                                <div class="amz-sbid-title" title="Both U7% and U1% are above 99%">&#62; 99% Utilized</div>
                                <button type="button" class="amz-sbid-min-btn" title="Minimize > 99% Utilized" aria-label="Minimize > 99% Utilized">−</button>
                            </div>
                            <div class="amz-sbid-chart-canvas"><canvas id="amz-sbid-chart-above"></canvas></div>
                            <div class="amz-sbid-legend" id="amz-sbid-leg-above"></div>
                            <div class="amz-sbid-fields">
                                <div class="amz-sbid-field">
                                    <label class="mb-0" for="amazonAdsSbidRuleHighMultL1">× L1 CPC</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleHighMultL1" name="both_high_mult_l1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-danger me-auto d-none" id="amazonAdsSbidRuleModalError" role="alert"></div>
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
                        Dil% uses the same <strong>dil</strong> column as this table (ovl30 ÷ Inv).
                        Pause when Dil% is ≥ the threshold (default 100) for PARENT campaigns, and for <strong>child SKU</strong> campaigns when their Dil or the <strong>PARENT family Dil</strong> is ≥ the threshold.
                        Save (with auto-pause on) applies matching pauses on Amazon now.
                        Only <strong>PARENT</strong> campaigns this Dil Pause Rule paused recently (last 31 days) are turned back on when Dil% is no longer ≥ the threshold — those rows show <strong>Active Again</strong>.
                        Child SKU campaigns that show <strong>Active Again</strong> are paused again (PARENT Active Again stays on).
                        Old ads stay paused: Price leftovers, Reviews, ACOS, old pink DIL, and anything paused before this Dil rule or older than 31 days are never turned back on. The job also runs daily at 18:25 IST.
                    </p>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="amazonAdsPrDilEnabled" checked>
                        <label class="form-check-label small" for="amazonAdsPrDilEnabled">Pause when Dil% ≥</label>
                    </div>
                    <div class="input-group input-group-sm mb-3" style="max-width: 220px;">
                        <input type="number" min="0" max="100000" step="1" class="form-control" id="amazonAdsPrDilAbove" value="100">
                        <span class="input-group-text">%</span>
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

    <div class="modal fade" id="amzTaskModal" tabindex="-1" aria-labelledby="amzTaskLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="amzTaskLabel">Assign Task</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="amz-task-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amz-task-group">Group</label>
                            <input type="text" class="form-control" id="amz-task-group" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amz-task-title">Task <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="amz-task-title" placeholder="Enter Task" maxlength="1000" autocomplete="off" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amz-task-page-link">Page Link</label>
                            <input type="text" class="form-control" id="amz-task-page-link" placeholder="" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="amz-task-assignee-search">Assign to <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="amz-task-assignee-search" list="amz-task-assignee-list" placeholder="Search user…" autocomplete="off" required>
                                <datalist id="amz-task-assignee-list"></datalist>
                                <button type="submit" class="btn btn-primary" id="amz-task-assign">Assign</button>
                            </div>
                            <input type="hidden" id="amz-task-assignee-id" value="">
                        </div>
                        <p class="text-danger small mb-0 mt-2 d-none" id="amz-task-error"></p>
                        <p class="text-success small mb-0 mt-2 d-none" id="amz-task-success"></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rawSources = @json($rawSources ?? []);
            var amazonAdsDefaultReportDates = @json($defaultReportRangeDates ?? (object) []);
            (function () {
                var d = amazonAdsDefaultReportDates.all_reports;
                var fromEl = document.getElementById('amazonAdsFilterDateFrom');
                var toEl = document.getElementById('amazonAdsFilterDateTo');
                if (d && typeof d === 'string' && fromEl && toEl && !fromEl.value && !toEl.value) {
                    fromEl.value = d;
                    toEl.value = d;
                }
            })();
            var dataUrlTemplate = @json(url('/amazon-ads/raw-data')) + '/';
            var pushSpSbidsUrl = @json(route('amazon.ads.push-sp-sbids'));
            var pushSbSbidsUrl = @json(route('amazon.ads.push-sb-sbids'));
            var pushSpSbgtsUrl = @json(route('amazon.ads.push-sp-sbgts'));
            var pushSbSbgtsUrl = @json(route('amazon.ads.push-sb-sbgts'));
            var syncLiveBidBgtUrl = @json(route('amazon.ads.sync-live-bid-bgt'));
            var bgtRuleGetUrl = @json(route('amazon.ads.bgt-rule'));
            var bgtCountsSaveUrl = @json(route('amazon.ads.bgt-counts.save'));
            var bgtCountsGetUrl = @json(route('amazon.ads.bgt-counts'));
            var bgtCountsRefreshUrl = @json(route('amazon.ads.bgt-counts.refresh'));
            var bgtRuleSaveUrl = @json(route('amazon.ads.bgt-rule.save'));
            var bgtViewsRuleGetUrl = @json(route('amazon.ads.bgt-views-rule'));
            var bgtViewsRuleSaveUrl = @json(route('amazon.ads.bgt-views-rule.save'));
            var bgtCvrRuleGetUrl = @json(route('amazon.ads.bgt-cvr-rule'));
            var bgtCvrRuleSaveUrl = @json(route('amazon.ads.bgt-cvr-rule.save'));
            var bgtPrcRuleGetUrl = @json(route('amazon.ads.bgt-prc-rule'));
            var bgtPrcRuleSaveUrl = @json(route('amazon.ads.bgt-prc-rule.save'));
            var bgtReviewsRuleGetUrl = @json(route('amazon.ads.bgt-reviews-rule'));
            var bgtReviewsRuleSaveUrl = @json(route('amazon.ads.bgt-reviews-rule.save'));
            var bgtDilRuleGetUrl = @json(route('amazon.ads.bgt-dil-rule'));
            var bgtDilRuleSaveUrl = @json(route('amazon.ads.bgt-dil-rule.save'));
            var bgtInvRuleGetUrl = @json(route('amazon.ads.bgt-inv-rule'));
            var bgtInvRuleSaveUrl = @json(route('amazon.ads.bgt-inv-rule.save'));
            var bgtSpendRuleGetUrl = @json(route('amazon.ads.bgt-spend-rule'));
            var bgtSpendRuleSaveUrl = @json(route('amazon.ads.bgt-spend-rule.save'));
            var sbidRuleGetUrl = @json(route('amazon.ads.sbid-rule'));
            var sbidRuleSaveUrl = @json(route('amazon.ads.sbid-rule.save'));
            var pauseRuleGetUrl = @json(route('amazon.ads.pause-rule'));
            var prRuleSaveUrl = @json(route('amazon.ads.pr-rule.save'));
            var campaignSkusUrl = @json(route('amazon.ads.campaign-skus'));
            var sbAdAddUrl = @json(route('amazon.ads.sb-ads.products.add'));
            var sbAdRemoveUrl = @json(route('amazon.ads.sb-ads.products.remove'));
            var cpcAvgHistoryUrl = @json(route('amazon.ads.cpc-avg-history'));
            var lRangeHistoryUrl = @json(route('amazon.ads.l-range-history'));
            var ltCvrHistoryUrl = @json(route('amazon.ads.lt-cvr-history'));
            var ltAcosHistoryUrl = @json(route('amazon.ads.lt-acos-history'));
            var sbidHistoryUrl = @json(route('amazon.ads.sbid-history'));
            var sbgtHistoryUrl = @json(route('amazon.ads.sbgt-history'));
            var lbidHistoryUrl = @json(route('amazon.ads.lbid-history'));
            var u7PieDistribUrl = @json(url('/amazon-ads/u7-distribution')) + '/';
            var u7PieHistoryUrl = @json(url('/amazon-ads/u7-distribution-history')) + '/';
            window.amazonAdsBgtRule = @json($amazonAdsBgtRule ?? null);
            window.amazonAdsBgtViewsRule = @json($amazonAdsBgtViewsRule ?? null);
            window.amazonAdsBgtCvrRule = @json($amazonAdsBgtCvrRule ?? null);
            window.amazonAdsBgtPrcRule = @json($amazonAdsBgtPrcRule ?? null);
            window.amazonAdsBgtReviewsRule = @json($amazonAdsBgtReviewsRule ?? null);
            window.amazonAdsBgtDilRule = @json($amazonAdsBgtDilRule ?? null);
            window.amazonAdsBgtInvRule = @json($amazonAdsBgtInvRule ?? null);
            window.amazonAdsBgtSpendRule = @json($amazonAdsBgtSpendRule ?? null);
            window.amazonAdsBgtCounts = @json($amazonAdsBgtCounts ?? null);
            window.amazonAdsSbidRule = @json($amazonAdsSbidRule ?? null);
            window.amazonAdsPauseRule = @json($amazonAdsPauseRule ?? null);

            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var table = null;
            var activeRawSourceKey = 'all_reports';
            var amzDrawCounter = 0;
            var amzU7PieChart = null;
            var amzU7PieRefreshTimer = null;

            var HIDDEN_COLUMNS = ['id', 'profile_id', 'campaign_id', 'report_date_range', 'ad_type', 'date', 'startDate', 'endDate', 'bgt_views_color', 'bgt_views_label', 'bgt_cvr_color', 'bgt_cvr_label', 'bgt_cvr_page_cvr', 'bgt_prc_color', 'bgt_prc_label', 'bgt_prc_price', 'bgt_dil_color', 'bgt_dil_label', 'bgt_dil_value', 'bgt_inv_color', 'bgt_inv_label', 'bgt_inv_value'];
            var NON_ORDERABLE_COLUMNS = ['pushAlert', 'sbgtAlert', 'sbidHistory', 'sbgtHistory'];
            var NUMERIC_SORT_DESC = ['Inv', 'INV', 'ovl30', 'dil', 'price', 'reviews', 'bgt', 'bgtAcos', 'bgtViews', 'bgtCvr', 'bgtPrc', 'bgtReviews', 'bgtDil', 'bgtInv', 'sbgt', 'cost', 'L7spend', 'L2spend', 'L1spend', 'L1cost', 'L1clicks', 'projectedSpend', 'projectedSales', 'ySpend', 'ySales', 'Prchase', 'purchases30d', 'Cvr', 'ltCvr', 'pageCvr', 'viewsL30', 'viewsL7', 'CPC3', 'CPCAvg', 'CPC2', 'costPerClick', 'sales30d', 'sales', 'ACOS', 'ltAcos', 'U7%', 'U2%', 'U1%', 'last_sbid', 'sbid', 'clicks', 'impressions'];
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
            function amzDash() { return '<span class="text-muted">-</span>'; }
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
                var highest = null;
                var lowest = null;
                for (var i = 0; i < bands.length; i++) {
                    var from = parseFloat(bands[i].acos_from);
                    var to = parseFloat(bands[i].acos_to);
                    if (isNaN(from)) from = 0;
                    if (isNaN(to)) to = 9999;
                    if (a >= from && a <= to) return bands[i];
                    if (!highest || from > parseFloat(highest.acos_from)) highest = bands[i];
                    if (!lowest || from < parseFloat(lowest.acos_from)) lowest = bands[i];
                }
                if (highest && a >= parseFloat(highest.acos_from || 0)) return highest;
                return lowest;
            }
            function amzBgtAcosFromAcos(acos) {
                var band = amzBandForAcos(acos);
                if (!band) return null;
                var t = amzBgtPartNum(band.sbgt);
                return isNaN(t) ? null : t;
            }
            function amzSumSbgtFromRow(row) {
                var parts = [row && row.bgtViews, row && row.bgtCvr, row && row.bgtAcos, row && row.bgtPrc, row && row.bgtReviews, row && row.bgtDil, row && row.bgtInv];
                var sum = 0, has = false;
                for (var i = 0; i < parts.length; i++) {
                    if (parts[i] === null || parts[i] === undefined || parts[i] === '') continue;
                    var n = parseFloat(parts[i]);
                    if (isNaN(n)) continue;
                    has = true;
                    sum += n;
                }
                if (!has) return null;
                // Parts may carry decimals (e.g. 1.5); the SBGT total is floored (4.5 → 4) before push.
                sum = Math.floor(Math.round(sum * 1e6) / 1e6);
                return sum < 1 ? 0 : sum;
            }
            function amzApplyAcosDrivenBgt(rows) {
                (rows || []).forEach(function (row) {
                    if (!row) return;
                    var tier = amzBgtAcosFromAcos(row.ltAcos);
                    if (tier !== null) row.bgtAcos = tier;
                    var sum = amzSumSbgtFromRow(row);
                    if (sum !== null) row.sbgt = sum;
                    if (amzShownBudgetMatches(row)) {
                        row.bgt_sync_color = 'green';
                        row.bgt_sync_status = 'synced';
                        row.bgt_sync_reason = 'already_matched';
                        row.bgt_sync_tip = 'Updated — Lbgt matches SBGT';
                    } else if (amzShownBudgetDiffers(row) && row.bgt_sync_color !== 'red') {
                        row.bgt_sync_color = 'yellow';
                        row.bgt_sync_status = 'pending';
                        row.bgt_sync_reason = 'sbgt_differs';
                        row.bgt_sync_tip = 'Pending — saved SBGT $' + Number(row.sbgt).toFixed(2)
                            + ' does not match live BGT $' + Number(row.bgt).toFixed(2);
                    }
                    row.sbgtAlert = amzSbgtAlertText(row);
                    row.pushAlert = amzPushAlertText(row);
                });
                return rows;
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
            function fmtSpl30(cell) {
                var td = cell.getElement();
                var n = amzFiniteNumber(cell.getValue());
                var high = !isNaN(n) && n > 29.99;
                if (td) td.classList.toggle('amz-spl30-high', high);
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
            function fmtPushAlert(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || String(v).trim() === '') return '';
                var tip = String(v);
                return '<span class="amz-push-alert" title="' + amzEsc(tip) + '" aria-label="' + amzEsc(tip) + '">!</span>';
            }
            function amzPushAlertText(row) {
                if (!row || !row.bid_sync_tip) return '';
                if (row.bid_sync_color === 'red') {
                    return 'SBID: ' + row.bid_sync_tip;
                }
                return '';
            }
            function amzSbgtAlertText(row) {
                if (!row || !row.bgt_sync_tip) return '';
                if (row.bgt_sync_color === 'red' || row.bgt_sync_reason === 'paused_zero_sbgt') {
                    return 'SBGT: ' + row.bgt_sync_tip;
                }
                return '';
            }
            function fmtTargets(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = parseInt(v, 10);
                if (isNaN(n)) return amzDash();
                if (n === 0) return '<span class="fw-semibold" style="color:#dc3545">M</span>';
                var color = n < 50 ? '#dc3545' : (n > 100 ? '#6f42c1' : '#198754');
                return '<span class="fw-semibold" style="color:' + color + '">' + n.toLocaleString() + '</span>';
            }
            function fmtCpcAvg(cell) {
                var v = cell.getValue();
                var text = '—';
                if (v !== null && v !== undefined && v !== '') {
                    var n = typeof v === 'number' ? v : parseFloat(v);
                    if (!isNaN(n)) text = n.toFixed(2);
                }
                var row = cell.getRow ? cell.getRow().getData() : {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id) : '';
                var name = row && row.campaignName != null ? String(row.campaignName) : '';
                var ad = row && row.ad_type != null ? String(row.ad_type) : '';
                return '<span class="amz-cpc-avg-cell">'
                    + '<button type="button" class="amz-cpc-avg-history-dot" title="Daily CPC history" aria-label="Daily CPC history"'
                    + ' data-history="cpc" data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + amzEsc(name) + '" data-ad-type="' + amzEsc(ad) + '"></button>'
                    + '<span>' + text + '</span></span>';
            }
            function fmtLtCvr(cell) {
                var v = cell.getValue();
                var text = '—';
                var color = '';
                if (v !== null && v !== undefined && v !== '') {
                    var n = typeof v === 'number' ? v : parseFloat(v);
                    if (!isNaN(n)) {
                        text = Math.round(n) + '%';
                        color = amzPageCvrColor(n);
                    }
                }
                var row = cell.getRow ? cell.getRow().getData() : {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id) : '';
                var name = row && row.campaignName != null ? String(row.campaignName) : '';
                var ad = row && row.ad_type != null ? String(row.ad_type) : '';
                var valueHtml = color
                    ? '<span class="fw-semibold" style="color:' + color + ';">' + amzEsc(text) + '</span>'
                    : '<span>' + text + '</span>';
                return '<span class="amz-cpc-avg-cell">'
                    + '<button type="button" class="amz-cpc-avg-history-dot" title="Daily CVR history" aria-label="Daily CVR history"'
                    + ' data-history="cvr" data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + amzEsc(name) + '" data-ad-type="' + amzEsc(ad) + '"></button>'
                    + valueHtml + '</span>';
            }
            function fmtMoneyHistoryDot(cell, kind, label) {
                var row = cell.getRow ? cell.getRow().getData() : {};
                return '<span class="amz-cpc-avg-cell">' + amzMoneyHistoryDotButton(row, kind, label) + '</span>';
            }
            // History dot button only (no wrapper), so it can sit inside another cell such as Lbid.
            function amzMoneyHistoryDotButton(row, kind, label) {
                row = row || {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id) : '';
                var name = row && row.campaignName != null ? String(row.campaignName) : '';
                var ad = row && row.ad_type != null ? String(row.ad_type) : '';
                var trend = kind === 'lbid' ? (row.lbid_trend || 'na') : (kind === 'sbid' ? (row.sbid_trend || 'na') : (row.sbgt_trend || 'na'));
                var current = parseFloat(kind === 'lbid' ? row.last_sbid : (kind === 'sbid' ? row.sbid : row.sbgt));
                if (kind === 'sbgt' && isFinite(current) && current === 0) trend = 'down';
                // Lbid starts green: first day (no previous day yet) shows green, not gray.
                if (kind === 'lbid' && trend === 'na' && isFinite(current)) trend = 'up';
                // API already saved today and the bid is unchanged: green, not gray.
                var cls = (trend === 'up' || trend === 'same') ? 'is-up' : (trend === 'down' ? 'is-down' : 'is-flat');
                var prev = kind === 'lbid' ? row.lbid_prev : (kind === 'sbid' ? row.sbid_prev : row.sbgt_prev);
                var prevTxt = (prev === null || prev === undefined || prev === '') ? '—' : Number(prev).toFixed(2);
                var nowTxt = isFinite(current) ? current.toFixed(2) : '—';
                var tip = 'Daily ' + label + ' history';
                if (trend === 'up' && (prev === null || prev === undefined || prev === '')) tip += ' · First day saved';
                else if (trend === 'na') tip += ' · No previous day saved yet';
                else if (trend === 'up') tip += ' · Up vs previous day $' + prevTxt + ' → $' + nowTxt;
                else if (trend === 'down') tip += ' · Down vs previous day $' + prevTxt + ' → $' + nowTxt;
                else tip += ' · Same as previous day $' + prevTxt;
                return '<button type="button" class="amz-cpc-avg-history-dot amz-hist-as-icon ' + cls + '" title="' + amzEsc(tip) + '" aria-label="' + amzEsc(tip) + '"'
                    + ' data-history="' + amzEsc(kind) + '" data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + amzEsc(name) + '" data-ad-type="' + amzEsc(ad) + '">'
                    + '<i class="fas fa-history"></i></button>';
            }
            function fmtLtAcos(cell) {
                var v = cell.getValue();
                var text = '—';
                var color = '';
                if (v !== null && v !== undefined && v !== '') {
                    var n = typeof v === 'number' ? v : parseFloat(v);
                    if (!isNaN(n)) {
                        text = Math.round(n) + '%';
                        color = amzAcosTierColor(n);
                    }
                }
                var row = cell.getRow ? cell.getRow().getData() : {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id) : '';
                var name = row && row.campaignName != null ? String(row.campaignName) : '';
                var ad = row && row.ad_type != null ? String(row.ad_type) : '';
                var valueHtml = color
                    ? '<span class="fw-semibold" style="color:' + color + ';">' + amzEsc(text) + '</span>'
                    : '<span>' + text + '</span>';
                return '<span class="amz-cpc-avg-cell">'
                    + '<button type="button" class="amz-cpc-avg-history-dot" title="Daily ACOS history" aria-label="Daily ACOS history"'
                    + ' data-history="acos" data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + amzEsc(name) + '" data-ad-type="' + amzEsc(ad) + '"></button>'
                    + valueHtml + '</span>';
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
            function fmtL1RangeCell(cell, metric) {
                var row = cell.getRow ? cell.getRow().getData() : {};
                var cid = row && row.campaign_id != null ? String(row.campaign_id) : '';
                var name = row && row.campaignName != null ? String(row.campaignName) : '';
                var ad = row && row.ad_type != null ? String(row.ad_type) : '';
                var label = metric === 'sales' ? 'L1–L7 ads sales' : 'L1–L7 ads spend';
                var btn = '<button type="button" class="amz-lrange-btn" title="' + amzEsc(label) + '" aria-label="' + amzEsc(label) + '"'
                    + ' data-lrange="' + amzEsc(metric) + '" data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + amzEsc(name) + '" data-ad-type="' + amzEsc(ad) + '">'
                    + '<i class="fas fa-chart-bar"></i></button>';
                return '<span class="amz-l1-cell">' + btn + fmtSbid(cell) + '</span>';
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
                if (dil < 25) return '#dc3545';
                if (dil < 50) return '#28a745';
                return '#e83e8c';
            }
            // Rule parts (Views / CVR / PRC / Reviews / Dil) may carry decimals (e.g. 1.5).
            // Only the SBGT total is floored (4.5 → 4) when it is saved / pushed.
            function amzBgtPartNum(v) {
                var n = parseFloat(v);
                return isFinite(n) ? Math.round(n * 100) / 100 : NaN;
            }
            function fmtSbgt(cell) {
                var v = cell.getValue();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var field = cell.getField ? cell.getField() : '';
                if (field === 'bgtAcos') {
                    var fromAcos = amzBgtAcosFromAcos(row && row.ltAcos);
                    if (fromAcos !== null) v = fromAcos;
                }
                if (v === null || v === undefined || v === '') return amzDash();
                var t = amzBgtPartNum(v);
                if (isNaN(t)) return amzDash();
                if (t === 0) {
                    return '<span class="fw-semibold" style="color:#dc2626;" title="This part adds $0">0</span>';
                }
                var color = (field === 'bgtAcos') ? amzAcosTierColor(row && row.ltAcos) : amzSbgtTierColor(t);
                return '<span class="fw-semibold" style="color:' + color + ';">' + t + '</span>';
            }
            function fmtSbgtSum(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var views = amzBgtPartNum(row && row.bgtViews);
                var cvr = amzBgtPartNum(row && row.bgtCvr);
                var acos = amzBgtPartNum(row && row.bgtAcos);
                var prc = amzBgtPartNum(row && row.bgtPrc);
                var rev = amzBgtPartNum(row && row.bgtReviews);
                var dilBgt = amzBgtPartNum(row && row.bgtDil);
                var invBgt = amzBgtPartNum(row && row.bgtInv);
                var bgt = parseFloat(row && row.bgt);
                var inSync = isFinite(bgt) && Math.round(bgt) === t;
                var color = t === 0 ? '#dc2626' : (inSync ? '#64748b' : '#0f766e');
                var tip = 'SBGT = Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil + Bgt Inv';
                var partsSum = (isFinite(views) ? views : 0) + (isFinite(cvr) ? cvr : 0) + (isFinite(acos) ? acos : 0) + (isFinite(prc) ? prc : 0) + (isFinite(rev) ? rev : 0) + (isFinite(dilBgt) ? dilBgt : 0) + (isFinite(invBgt) ? invBgt : 0);
                tip += ' · ' + (isFinite(views) ? views : 0) + ' + ' + (isFinite(cvr) ? cvr : 0) + ' + ' + (isFinite(acos) ? acos : 0) + ' + ' + (isFinite(prc) ? prc : 0) + ' + ' + (isFinite(rev) ? rev : 0) + ' + ' + (isFinite(dilBgt) ? dilBgt : 0) + ' + ' + (isFinite(invBgt) ? invBgt : 0);
                if (Math.abs(partsSum - Math.round(partsSum)) > 0.0001) {
                    tip += ' = ' + (Math.round(partsSum * 100) / 100) + ' → floored to ' + t;
                }
                if (t === 0) {
                    tip += ' · total is $0 — cannot push $0, campaign will be paused';
                } else if (isFinite(bgt)) {
                    tip += inSync ? ' · matches BGT' : (' · BGT ' + Math.round(bgt) + ' → auto-push');
                }
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtViews(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = amzBgtPartNum(v);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_views_color) ? String(row.bgt_views_color) : '#6c757d';
                var views = parseFloat(row && (row.viewsL7 != null ? row.viewsL7 : row.page_cvr_sess7));
                var parent = String((row && row.page_parent) || '').trim();
                var label = String((row && row.bgt_views_label) || '').trim();
                var tip = 'Bgt Views from Amz page View L7';
                if (isFinite(views)) tip += ' · Views ' + Math.round(views);
                if (parent) tip += ' · ' + parent;
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtCvr(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = amzBgtPartNum(v);
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
                var t = amzBgtPartNum(v);
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
                var t = amzBgtPartNum(v);
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
            function fmtBgtDil(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = amzBgtPartNum(v);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_dil_color) ? String(row.bgt_dil_color) : '#6c757d';
                var dil = parseFloat(row && (row.bgt_dil_value != null ? row.bgt_dil_value : row.dil));
                if (!isFinite(dil) && row) {
                    var inv = parseFloat(row.Inv);
                    var ovl30 = parseFloat(row.ovl30) || 0;
                    if (isFinite(inv) && inv > 0) dil = (ovl30 / inv) * 100;
                }
                var label = String((row && row.bgt_dil_label) || '').trim();
                var tip = 'Bgt Dil from campaign Dil%';
                if (isFinite(dil)) tip += ' · Dil ' + Math.round(dil) + '%';
                if (label) tip += ' · ' + label;
                return '<span class="fw-semibold" style="color:' + color + ';" title="' + String(tip).replace(/"/g, '&quot;') + '">' + t + '</span>';
            }
            function fmtBgtInv(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = amzBgtPartNum(v);
                if (isNaN(t)) return amzDash();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row.bgt_inv_color) ? String(row.bgt_inv_color) : '#6c757d';
                var inv = parseFloat(row && (row.bgt_inv_value != null ? row.bgt_inv_value : row.Inv));
                var label = String((row && row.bgt_inv_label) || '').trim();
                var tip = 'Bgt Inv from on-hand inventory';
                if (isFinite(inv)) tip += ' · Inv ' + inv;
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
                var up = raw.toUpperCase();
                var enabled = up === 'ENABLED';
                var paused = up === 'PAUSED';
                var color = enabled ? '#16a34a' : (paused ? '#dc2626' : '#6b7280');
                var label = paused ? 'P' : (enabled ? 'E' : raw.charAt(0).toUpperCase());
                return '<span class="amz-raw-status-cell" title="' + amzEsc(raw) + '" style="display:inline-flex;align-items:center;justify-content:center;gap:4px;font-size:11px;font-weight:700;color:' + color + ';">'
                     + '<span class="d-inline-block rounded-circle" style="width:8px;height:8px;background-color:' + color + ';"></span>' + amzEsc(label) + '</span>';
            }
            function fmtRuleStatus(cell) {
                var v = cell.getValue();
                var raw = (v === null || v === undefined) ? '' : String(v).trim();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var tipRaw = (row && row.ruleStatusTip) ? String(row.ruleStatusTip) : (raw || '—');
                if (raw === '') return '<span class="amz-raw-status-cell text-muted" title="' + amzEsc(tipRaw) + '">—</span>';
                var up = raw.toUpperCase();
                var enabled = up === 'ENABLED';
                var paused = up === 'PAUSED';
                var color = enabled ? '#16a34a' : (paused ? '#dc2626' : '#6b7280');
                var label = paused ? 'P' : (enabled ? 'E' : raw.charAt(0).toUpperCase());
                return '<span class="amz-raw-status-cell" title="' + amzEsc(tipRaw) + '" style="display:inline-flex;align-items:center;justify-content:center;gap:4px;font-size:11px;font-weight:700;color:' + color + ';">'
                     + '<span class="d-inline-block rounded-circle" style="width:8px;height:8px;background-color:' + color + ';"></span>' + amzEsc(label) + '</span>';
            }
            function fmtActiveAgain(cell) {
                var v = cell.getValue();
                var raw = (v === null || v === undefined) ? '' : String(v).trim();
                var row = cell.getRow ? cell.getRow().getData() : {};
                var tipRaw = (row && row.activeAgainTip) ? String(row.activeAgainTip).trim() : '';
                if (raw === '') {
                    return '<span class="amz-raw-status-cell text-muted" title="Not turned back on">—</span>';
                }
                var full = tipRaw ? (raw + ' — ' + tipRaw) : raw;
                return '<span class="amz-active-again-dot" title="' + amzEsc(full) + '" aria-label="' + amzEsc(full) + '"></span>';
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
            function amzCampaignLowInvN(row) {
                if (!row) return null;
                var knownSkuInv = !!row.sku_inv_known;
                var invSource = knownSkuInv ? row.sku_inv_min : row.Inv;
                var invN = invSource != null && invSource !== '' ? parseFloat(invSource) : NaN;
                if (!isFinite(invN) || invN >= 5) return null;
                return Math.round(invN);
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
                        + ' data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + attr + '"'
                        + ' data-ad-type="' + amzEsc(row && row.ad_type != null ? String(row.ad_type) : '') + '">'
                        + '<i class="fas fa-plus"></i></button>'
                    : '';
                var lowN = cid !== '' ? amzCampaignLowInvN(row) : null;
                var knownSkuInv = !!(row && row.sku_inv_known);
                var lowInv = lowN !== null
                    ? '<button type="button" class="amz-low-inv-btn" title="'
                        + (knownSkuInv ? 'Lowest SKU inventory ' : 'Inventory ')
                        + lowN + ' is under 5. Click to see this campaign."'
                        + ' data-campaign-id="' + amzEsc(cid) + '" data-campaign-name="' + attr + '"'
                        + ' data-ad-type="' + amzEsc(row && row.ad_type != null ? String(row.ad_type) : '') + '">!</button>'
                    : '';
                var copy = '<i class="fas fa-copy amz-copy-name" role="button" tabindex="0" title="Copy campaign name"'
                         + ' data-copy="' + attr + '" style="margin-left:6px;color:#94a3b8;cursor:pointer;flex-shrink:0;"></i>';
                return '<span style="display:inline-flex;align-items:center;gap:2px;max-width:100%;">'
                     + plus
                     + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc + '</span>'
                     + lowInv + copy + '</span>';
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
                    col.frozen = true;
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
                if (c === 'ruleStatus') { col.title = 'Rule'; col.headerTooltip = 'Rule Status — red = pause when Dil% ≥ threshold. Only PARENT campaigns auto-activate again.'; col.formatter = fmtRuleStatus; col.width = 52; col.minWidth = 48; return; }
                if (c === 'activeAgain') {
                    col.title = '';
                    col.headerTooltip = 'Active Again — turned back on after a Pause Rule match. Hover a green dot for the full reason.';
                    col.titleFormatter = function () {
                        return '<span class="amz-active-again-dot" title="Active Again"></span>';
                    };
                    col.formatter = fmtActiveAgain;
                    col.width = 36;
                    col.minWidth = 36;
                    return;
                }
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
                if (c === 'last_sbid') {
                    col.title = 'Lbid';
                    col.formatter = function (cell) { return amzFmtMoneyWithSync(cell, 'bid', 'lbid'); };
                    col.headerTooltip = 'Live Amazon BID sync vs SBID. Green = verified live match. The small dot after the value opens the daily Lbid history chart.';
                    col.minWidth = 78;
                    col.width = 88;
                    return;
                }
                if (c === 'pushAlert') {
                    col.title = 'Alert';
                    col.headerTooltip = 'Shown when the SBID push failed. A pending Lbid gap stays a yellow dot on Lbid.';
                    col.formatter = fmtPushAlert;
                    col.headerSort = false;
                    col.width = 48;
                    col.minWidth = 44;
                    return;
                }
                if (c === 'sbgtAlert') {
                    col.title = 'Alert';
                    col.headerTooltip = 'Shown when the SBGT push failed, or SBGT 0 paused the campaign. A pending Lbgt gap stays a yellow dot on Lbgt.';
                    col.formatter = fmtPushAlert;
                    col.headerSort = false;
                    col.width = 48;
                    col.minWidth = 44;
                    return;
                }
                if (c === 'sbidHistory') {
                    col.title = 'SBID History';
                    col.headerTooltip = 'Daily SBID. Green dot opens the history chart.';
                    col.formatter = function (cell) { return fmtMoneyHistoryDot(cell, 'sbid', 'SBID'); };
                    col.headerSort = false;
                    col.width = 96;
                    col.minWidth = 88;
                    return;
                }
                if (c === 'sbid') {
                    col.title = 'SBID';
                    col.formatter = fmtSbid;
                    if (!amzSourceHasColumn('last_sbid')) {
                        col.formatter = function (cell) { return amzFmtMoneyWithSync(cell, 'bid'); };
                        col.headerTooltip = 'Amazon BID sync vs SBID. Green = verified live match.';
                        col.minWidth = 64;
                        col.width = 72;
                    }
                    return;
                }
                if (c === 'targets') {
                    col.title = 'Targets';
                    col.formatter = fmtTargets;
                    col.headerTooltip = 'Keyword and product-target count. SP uses the L30 targeting report. SB and SD use the synced keyword list. 0 shows M. Under 50 red, 50–100 green, over 100 purple.';
                    col.width = 52;
                    col.minWidth = 44;
                    col.headerSort = false;
                    return;
                }
                if (c === 'nTargets') {
                    col.title = 'N Target';
                    col.formatter = fmtTargets;
                    col.headerTooltip = 'Negative keyword count. SP uses the synced negative-keyword list. SB uses the synced SB negative list. 0 shows M. Under 50 red, 50–100 green, over 100 purple.';
                    col.width = 52;
                    col.minWidth = 44;
                    col.headerSort = false;
                    return;
                }
                if (c === 'bgt') {
                    col.title = 'Lbgt';
                    col.formatter = function (cell) { return amzFmtMoneyWithSync(cell, 'bgt'); };
                    col.headerTooltip = 'Live Amazon budget vs SBGT. Green = Lbgt matches SBGT.';
                    col.minWidth = 64;
                    col.width = 72;
                    return;
                }
                if (c === 'bgtAcos') {
                    col.title = 'BGT ACOS';
                    col.headerTooltip = 'Suggested budget from BGT Vs ACOS Rule — band is the campaign LT ACOS (spend with no sales is 100%)';
                    col.formatter = fmtSbgt;
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'sbgtHistory') {
                    col.title = 'SBGT History';
                    col.headerTooltip = 'Daily SBGT. Green dot opens the history chart.';
                    col.formatter = function (cell) { return fmtMoneyHistoryDot(cell, 'sbgt', 'SBGT'); };
                    col.headerSort = false;
                    col.width = 96;
                    col.minWidth = 88;
                    return;
                }
                if (c === 'sbgt') {
                    col.title = 'SBGT';
                    col.headerTooltip = 'Bgt Views + Bgt Cvr + BGT ACOS + BGT PRC + Bgt Reviews + Bgt Dil + Bgt Inv. Auto-pushes daily budget when this sum differs from BGT. SBGT 0 pauses the campaign ($0 cannot be pushed).';
                    col.formatter = fmtSbgtSum;
                    col.width = 56;
                    col.minWidth = 50;
                    return;
                }
                if (c === 'bgtViews') {
                    col.title = 'Bgt Views';
                    col.headerTooltip = 'Suggested budget from BGT Vs VIEWS — Amz page parent View L7 (Sess7)';
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
                    col.title = 'Bgt REV';
                    col.headerTooltip = 'Suggested budget from BGT Vs REVIEWS — campaign star rating slabs';
                    col.formatter = fmtBgtReviews;
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'bgtDil') {
                    col.title = 'Bgt Dil';
                    col.headerTooltip = 'Suggested budget from BGT Vs Dil — same Dil% as the dil column (ovl30 ÷ Inv × 100)';
                    col.formatter = fmtBgtDil;
                    col.width = 64;
                    col.minWidth = 56;
                    return;
                }
                if (c === 'bgtInv') {
                    col.title = 'Bgt Inv';
                    col.headerTooltip = 'Suggested budget from Inv Rule — on-hand inventory (Inv)';
                    col.formatter = fmtBgtInv;
                    col.width = 64;
                    col.minWidth = 56;
                    return;
                }
                if (c === 'Prchase') { col.title = 'Ads Sold'; col.formatter = fmtDashInt; return; }
                if (c === 'Cvr') { col.title = 'Ads CVR'; col.headerTooltip = 'Ads Sold ÷ Ads Clicks × 100 (same L30 row)'; col.formatter = fmtCvr; col.minWidth = 64; return; }
                if (c === 'ltCvr') {
                    col.title = 'LT CVR';
                    col.headerTooltip = 'Lifetime CVR = total Ads Sold ÷ total clicks on daily Amazon report rows.';
                    col.formatter = fmtLtCvr;
                    col.minWidth = 64;
                    col.width = 72;
                    return;
                }
                if (c === 'pageCvr') {
                    col.title = 'CVR';
                    col.headerTooltip = 'Amz page parent CVR L30 — A L30 ÷ Sess30 × 100 (parent row related to this campaign)';
                    col.formatter = fmtPageCvr;
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'viewsL30') {
                    col.title = 'Vw L30';
                    col.headerTooltip = 'Amz page parent View L30 (Σ Sess30) — same parent row as CVR';
                    col.formatter = function (cell) { return fmtAmzParentViews(cell, null); };
                    col.width = 72;
                    col.minWidth = 64;
                    return;
                }
                if (c === 'viewsL7') {
                    col.title = 'Vw L7';
                    col.headerTooltip = 'Amz page parent View L7 (Σ Sess7) — same parent row as CVR. Red when under 70.';
                    col.formatter = function (cell) { return fmtAmzParentViews(cell, 70); };
                    col.width = 68;
                    col.minWidth = 60;
                    return;
                }
                if (c === 'Label' || c === 'label') { col.title = 'ACOS%'; col.headerTooltip = 'ACOS %'; return; }
                if (c === 'ACOS') { col.title = 'ACOS%'; col.headerTooltip = 'ACOS % = SPL30 ÷ SL 30 × 100. Spend with Ads Sold 0 is saved as 100% for BGT / SBGT / filters.'; col.formatter = fmtAcos; return; }
                if (c === 'ltAcos') {
                    col.title = 'LT ACOS';
                    col.headerTooltip = 'Lifetime ACOS = total cost ÷ total sales on daily Amazon report rows. Spend with no sales is 100%.';
                    col.formatter = fmtLtAcos;
                    col.minWidth = 64;
                    col.width = 72;
                    return;
                }
                if (c === 'sales') { col.title = 'Sales'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'cost') { col.title = 'SPL30'; col.headerTooltip = 'L30 spend. Above 29.99 is red.'; col.formatter = fmtSpl30; return; }
                if (c === 'L7spend') { col.title = 'L7SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L2spend') { col.title = 'L2SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L1spend') { col.title = 'L1SP'; col.formatter = function (cell) { return fmtL1RangeCell(cell, 'spend'); }; return; }
                if (c === 'L1cost') { col.title = 'L1Cost'; col.formatter = fmtDashRounded; return; }
                if (c === 'L1clicks') { col.title = 'L1Clk'; col.formatter = fmtDashInt; return; }
                if (c === 'U7%' || c === 'U2%' || c === 'U1%') { col.formatter = fmtUtilPercent; return; }
                if (c === 'CPC3') { col.title = 'CPC3'; col.formatter = fmt2dec; return; }
                if (c === 'CPCAvg') {
                    col.title = 'CPC Avg';
                    col.headerTooltip = 'Lifetime average CPC from Amazon Ads daily reports — total cost ÷ total clicks. Green dot opens daily CPC history.';
                    col.formatter = fmtCpcAvg;
                    col.width = 78;
                    col.minWidth = 70;
                    return;
                }
                if (c === 'CPC2') { col.title = 'CPC2'; col.formatter = fmt2dec; return; }
                if (c === 'costPerClick') { col.title = 'CPC1'; col.formatter = fmt2dec; return; }
                if (c === 'sales30d') { col.title = 'SL 30'; col.formatter = fmtDashRounded; return; }
                if (c === 'projectedSpend') {
                    col.title = 'Projected Spend';
                    col.headerTooltip = 'Projected monthly ads spend = (last 7 days ending yesterday ÷ 7) × 30.';
                    col.formatter = fmtSbid;
                    col.minWidth = 110;
                    col.width = 122;
                    return;
                }
                if (c === 'projectedSales') {
                    col.title = 'Projected Sales';
                    col.headerTooltip = 'Projected monthly ads sales = (last 7 days ending yesterday ÷ 7) × 30.';
                    col.formatter = fmtSbid;
                    col.minWidth = 110;
                    col.width = 122;
                    return;
                }
                if (c === 'ySpend') {
                    col.title = 'L1 Spend';
                    col.headerTooltip = 'Yesterday ads spend (Amazon L1). Chart icon shows daily L1–L7 spend.';
                    col.formatter = function (cell) { return fmtL1RangeCell(cell, 'spend'); };
                    col.minWidth = 92;
                    col.width = 102;
                    return;
                }
                if (c === 'ySales') {
                    col.title = 'L1 Sales';
                    col.headerTooltip = 'Yesterday ads sales (Amazon L1). Chart icon shows daily L1–L7 sales.';
                    col.formatter = function (cell) { return fmtL1RangeCell(cell, 'sales'); };
                    col.minWidth = 92;
                    col.width = 102;
                    return;
                }
                if (c === 'clicks') { col.title = 'Clicks'; col.headerTooltip = 'Amazon Ads clicks (L30).'; col.formatter = fmtDashInt; return; }
            }

            function amzBuildColumns(source) {
                var cols = (rawSources[source] && rawSources[source].columns) ? rawSources[source].columns : [];
                var defs = [{
                    title: '', field: '__sel', formatter: 'rowSelection', titleFormatter: 'rowSelection',
                    headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 40, minWidth: 40,
                    frozen: true
                }];
                cols.forEach(function (c) {
                    var col = { field: c, title: c, hozAlign: 'center', headerHozAlign: 'center', minWidth: 56, widthGrow: 0 };
                    col.headerSort = NON_ORDERABLE_COLUMNS.indexOf(c) === -1;
                    if (NUMERIC_SORT_DESC.indexOf(c) !== -1) col.headerSortStartingDir = 'desc';
                    if (HIDDEN_COLUMNS.indexOf(c) !== -1) col.visible = false;
                    amzApplyColFormat(col, c);
                    defs.push(col);
                });
                defs.push({
                    title: '',
                    field: '__task',
                    width: 44,
                    minWidth: 44,
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: false,
                    cssClass: 'amz-task-cell',
                    formatter: function () {
                        return '<button type="button" class="amz-task-btn" title="Assign Task" aria-label="Assign Task">TM</button>';
                    },
                    cellClick: openAmzTaskFromCell,
                });
                // Tabulator left-freeze stops at the first unfrozen column, so pin
                // the checkbox and any hidden columns through campaignName.
                var nameIdx = -1;
                for (var fi = 0; fi < defs.length; fi++) {
                    if (defs[fi].field === 'campaignName') { nameIdx = fi; break; }
                }
                if (nameIdx !== -1) {
                    for (var fj = 0; fj <= nameIdx; fj++) defs[fj].frozen = true;
                    defs.splice(nameIdx + 1, 0, {
                        title: 'Inv Alert',
                        field: 'invAlert',
                        visible: false,
                        download: true,
                        headerSort: false,
                        accessorDownload: function (value, data) {
                            return amzCampaignLowInvN(data) === null ? '' : '!';
                        }
                    });
                }
                var againIdx = -1;
                for (var ak = 0; ak < defs.length; ak++) {
                    if (defs[ak].field === 'activeAgain') { againIdx = ak; break; }
                }
                if (againIdx !== -1 && againIdx !== defs.length - 1) {
                    defs.push(defs.splice(againIdx, 1)[0]);
                }
                return defs;
            }

            // ---- filter payload ----
            function amzSearchQueryVal() {
                var el = document.getElementById('amz-filter-search');
                if (!el) return '';
                var v = String(el.value || '').replace(/\s+/g, ' ').trim();
                return v.length > 100 ? v.slice(0, 100) : v;
            }
            var amzSyncFilters = { bgt: '', bid: '' };
            var amzSyncHeaderCounts = {
                bgt: { green: 0, yellow: 0, red: 0 },
                bid: { green: 0, yellow: 0, red: 0 }
            };
            function amzSourceHasColumn(field) {
                var cols = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].columns) ? rawSources[activeRawSourceKey].columns : [];
                return cols.indexOf(field) !== -1;
            }
            function amzSyncDotHtml(color, tip) {
                var c = color === 'green' || color === 'red' ? color : 'yellow';
                return '<span class="amz-sync-dot is-' + c + '" title="' + amzEsc(tip || '') + '"></span>';
            }
            function amzShownBidMatches(row) {
                var live = parseFloat(row && row.last_sbid);
                var want = parseFloat(row && row.sbid);
                return isFinite(live) && live > 0 && isFinite(want) && want > 0 && Math.round(live * 100) === Math.round(want * 100);
            }
            function amzShownBudgetMatches(row) {
                var live = parseFloat(row && row.bgt);
                var want = parseFloat(row && row.sbgt);
                return isFinite(live) && isFinite(want) && Math.abs(live - want) <= 0.015;
            }
            function amzShownBudgetDiffers(row) {
                var live = parseFloat(row && row.bgt);
                var want = parseFloat(row && row.sbgt);
                return isFinite(live) && isFinite(want) && Math.abs(live - want) > 0.015;
            }
            function amzFmtMoneyWithSync(cell, field, historyKind) {
                var row = cell.getRow ? cell.getRow().getData() : {};
                var color = (row && row[field + '_sync_color']) ? String(row[field + '_sync_color']) : 'yellow';
                var tip = (row && row[field + '_sync_tip'])
                    ? String(row[field + '_sync_tip'])
                    : (field === 'bid'
                        ? 'Pending — BID has not been pulled from Amazon and verified yet'
                        : 'Pending — BGT has not been pulled from Amazon and verified yet');
                var valueHtml = field === 'bid' ? fmtSbid(cell) : fmtDashNumberRaw(cell);
                var historyHtml = historyKind === 'lbid' ? amzMoneyHistoryDotButton(row, 'lbid', 'Lbid') : '';
                return '<span class="amz-sync-cell">' + amzSyncDotHtml(color, tip) + valueHtml + historyHtml + '</span>';
            }
            function amzSyncBadgeHtml(color, count, field, active) {
                var label = color === 'green' ? 'Updated' : (color === 'red' ? 'Not updated' : 'Pending');
                var tip = label + ' ' + (field === 'bid' ? 'BID' : 'Lbgt') + ' rows — click to filter, click again to clear';
                return '<button type="button" class="amz-sync-badge is-' + color + (active === color ? ' is-active' : '') + '"'
                    + ' data-sync-field="' + field + '" data-sync-color="' + color + '"'
                    + ' title="' + amzEsc(tip) + '" aria-pressed="' + (active === color ? 'true' : 'false') + '">'
                    + '<span class="amz-sync-dot is-' + color + '"></span>' + (Number(count) || 0)
                    + '</button>';
            }
            function amzNormalizeSyncCounts(raw) {
                return {
                    green: Number(raw && raw.green) || 0,
                    yellow: Number(raw && raw.yellow) || 0,
                    red: Number(raw && raw.red) || 0
                };
            }
            function amzPaintSyncHeaders() {
                document.querySelectorAll('.amz-ads-all .amz-sync-head-badges').forEach(function (el) {
                    var field = el.getAttribute('data-sync-field') === 'bid' ? 'bid' : 'bgt';
                    var counts = amzSyncHeaderCounts[field] || { green: 0, yellow: 0, red: 0 };
                    var active = amzSyncFilters[field] || '';
                    el.innerHTML = amzSyncBadgeHtml('green', counts.green, field, active)
                        + amzSyncBadgeHtml('yellow', counts.yellow, field, active)
                        + amzSyncBadgeHtml('red', counts.red, field, active);
                });
            }
            function amzApplyServerSyncCounts(json) {
                amzSyncHeaderCounts.bgt = amzNormalizeSyncCounts(json && json.bgt_sync_counts);
                amzSyncHeaderCounts.bid = amzNormalizeSyncCounts(json && json.bid_sync_counts);
                amzPaintSyncHeaders();
            }
            function amzToggleSyncFilter(field, color) {
                field = field === 'bid' ? 'bid' : 'bgt';
                color = color === 'green' || color === 'red' || color === 'yellow' ? color : '';
                amzSyncFilters[field] = (amzSyncFilters[field] === color) ? '' : color;
                amzPaintSyncHeaders();
                amzReloadGridForFilters();
            }
            function amzClearSyncFilters() {
                amzSyncFilters.bgt = '';
                amzSyncFilters.bid = '';
                amzPaintSyncHeaders();
            }
            function amzColorFromStatus(status) {
                var s = String(status || '').toLowerCase();
                if (s === 'synced') return 'green';
                if (s === 'failed') return 'red';
                return 'yellow';
            }
            function amzMarkChunkPending(chunkRows) {
                if (!table || !chunkRows || !chunkRows.length) return;
                var want = {};
                chunkRows.forEach(function (r) {
                    if (r && r.campaign_id != null) want[String(r.campaign_id)] = r;
                });
                (table.getRows() || []).forEach(function (row) {
                    var d = row.getData ? row.getData() : null;
                    if (!d) return;
                    var src = want[String(d.campaign_id || '')];
                    if (!src) return;
                    var patch = {};
                    if (src.sbgt != null) {
                        if (amzShownBudgetMatches(d)) {
                            patch.bgt_sync_color = 'green';
                            patch.bgt_sync_status = 'synced';
                            patch.bgt_sync_tip = 'Updated — Lbgt matches SBGT';
                            patch.bgt_sync_reason = 'already_matched';
                        } else {
                            patch.bgt_sync_color = 'yellow';
                            patch.bgt_sync_status = 'pending';
                            patch.bgt_sync_tip = 'Pending — Pulling live Amazon BGT for verification against SBGT $'
                                + Number(src.sbgt).toFixed(2);
                        }
                    }
                    if (src.sbid != null) {
                        if (amzShownBidMatches(d)) {
                            patch.bid_sync_color = 'green';
                            patch.bid_sync_status = 'synced';
                            patch.bid_sync_tip = 'Updated — Lbid matches SBID';
                        } else {
                            patch.bid_sync_color = 'yellow';
                            patch.bid_sync_status = 'pending';
                            patch.bid_sync_tip = 'Pending — Pulling live Amazon BID for verification against SBID $'
                                + Number(src.sbid).toFixed(2);
                        }
                    }
                    if (Object.keys(patch).length) {
                        var pendingView = {};
                        Object.keys(d).forEach(function (k) { pendingView[k] = d[k]; });
                        Object.keys(patch).forEach(function (k) { pendingView[k] = patch[k]; });
                        patch.pushAlert = amzPushAlertText(pendingView);
                        patch.sbgtAlert = amzSbgtAlertText(pendingView);
                        row.update(patch);
                    }
                });
            }
            function amzPatchRowsFromLiveSync(results) {
                if (!table || !results || !results.length) return;
                var byCid = {};
                results.forEach(function (r) {
                    if (r && r.campaign_id != null) byCid[String(r.campaign_id)] = r;
                });
                (table.getRows() || []).forEach(function (row) {
                    var d = row.getData ? row.getData() : null;
                    if (!d) return;
                    var r = byCid[String(d.campaign_id || '')];
                    if (!r || !r.fields) return;
                    var patch = {};
                    if (r.fields.bgt) {
                        if (r.fields.bgt.status === 'synced' && r.fields.bgt.verified_live != null) {
                            patch.bgt = r.fields.bgt.verified_live;
                        }
                        var bgtView = {};
                        Object.keys(d).forEach(function (k) { bgtView[k] = d[k]; });
                        Object.keys(patch).forEach(function (k) { bgtView[k] = patch[k]; });
                        var bgtFailed = String(r.fields.bgt.status || '') === 'failed';
                        if (!bgtFailed && amzShownBudgetMatches(bgtView)) {
                            patch.bgt_sync_color = 'green';
                            patch.bgt_sync_status = 'synced';
                            patch.bgt_sync_reason = 'already_matched';
                            patch.bgt_sync_tip = 'Updated — Lbgt matches SBGT';
                        } else if (String(r.fields.bgt.reason || '') !== 'concurrent_sync' && String(r.fields.bgt.status || '') !== 'in_progress') {
                            patch.bgt_sync_color = r.fields.bgt.sync_color || amzColorFromStatus(r.fields.bgt.status);
                            patch.bgt_sync_tip = r.fields.bgt.sync_tip || '';
                            patch.bgt_sync_status = r.fields.bgt.status || '';
                            patch.bgt_sync_reason = r.fields.bgt.reason || '';
                            if (patch.bgt_sync_color === 'green' && amzShownBudgetDiffers(bgtView)) {
                                patch.bgt_sync_color = 'yellow';
                                patch.bgt_sync_status = 'pending';
                                patch.bgt_sync_reason = 'sbgt_differs';
                                patch.bgt_sync_tip = 'Pending — saved SBGT $' + Number(bgtView.sbgt).toFixed(2)
                                    + ' does not match live BGT $' + Number(bgtView.bgt).toFixed(2);
                            }
                        }
                        if (patch.bgt_sync_reason === 'paused_zero_sbgt') patch.campaignStatus = 'PAUSED';
                    }
                    if (r.fields.bid) {
                        var bidFailed = String(r.fields.bid.status || '') === 'failed';
                        if (!bidFailed && amzShownBidMatches(d)) {
                            patch.bid_sync_color = 'green';
                            patch.bid_sync_status = 'synced';
                            patch.bid_sync_tip = String(r.fields.bid.status || '') === 'synced' && r.fields.bid.sync_tip
                                ? r.fields.bid.sync_tip
                                : 'Updated — Lbid matches SBID';
                        } else if (String(r.fields.bid.reason || '') !== 'concurrent_sync' && String(r.fields.bid.status || '') !== 'in_progress') {
                            patch.bid_sync_color = r.fields.bid.sync_color || amzColorFromStatus(r.fields.bid.status);
                            patch.bid_sync_tip = r.fields.bid.sync_tip || '';
                            patch.bid_sync_status = r.fields.bid.status || '';
                        }
                        patch.bid_sync_reason = r.fields.bid.reason || '';
                        if (r.fields.bid.status === 'synced' && r.fields.bid.verified_live != null) {
                            patch.last_sbid = r.fields.bid.verified_live;
                        }
                        var bidView = {};
                        Object.keys(d).forEach(function (k) { bidView[k] = d[k]; });
                        Object.keys(patch).forEach(function (k) { bidView[k] = patch[k]; });
                        if (!bidFailed && patch.bid_sync_color === 'green' && !amzShownBidMatches(bidView)) {
                            patch.bid_sync_color = 'yellow';
                            patch.bid_sync_status = 'pending';
                            patch.bid_sync_reason = 'sbid_differs';
                            patch.bid_sync_tip = 'Pending — saved SBID $' + Number(bidView.sbid).toFixed(2)
                                + ' does not match live BID $' + Number(bidView.last_sbid).toFixed(2);
                        }
                    }
                    var next = {};
                    Object.keys(d).forEach(function (k) { next[k] = d[k]; });
                    Object.keys(patch).forEach(function (k) { next[k] = patch[k]; });
                    patch.pushAlert = amzPushAlertText(next);
                    patch.sbgtAlert = amzSbgtAlertText(next);
                    if (Object.keys(patch).length) row.update(patch);
                });
            }
            function amzStatFilterParts() {
                var root = document.getElementById('amazonAdsFilterCampaignStatus');
                if (!root) return null;
                return {
                    root: root,
                    all: root.querySelector('[data-stat-all]'),
                    boxes: Array.prototype.slice.call(root.querySelectorAll('input[data-stat]'))
                };
            }
            function amzSyncStatFilterLabel() {
                var s = amzStatFilterParts();
                var btn = document.getElementById('amazonAdsFilterCampaignStatusBtn');
                if (!s) return;
                if (s.all) s.all.closest('label').classList.toggle('is-on', s.all.checked);
                s.boxes.forEach(function (b) {
                    var lab = b.closest('label');
                    if (lab) lab.classList.toggle('is-on', b.checked);
                });
                if (!btn) return;
                var on = s.boxes.filter(function (b) { return b.checked; });
                if (s.all && s.all.checked) btn.textContent = 'All';
                else if (!on.length) btn.textContent = 'None';
                else btn.textContent = on.map(function (b) { return b.getAttribute('data-label') || b.value; }).join(', ');
            }
            function amzStatFilterValue() {
                var s = amzStatFilterParts();
                if (!s) return 'ENABLED,PAUSED';
                if (s.all && s.all.checked) return '';
                var vals = s.boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
                return vals.length ? vals.join(',') : '__none__';
            }
            function amzResetStatFilter() {
                var s = amzStatFilterParts();
                if (!s) return;
                if (s.all) s.all.checked = false;
                s.boxes.forEach(function (b) { b.checked = b.value === 'ENABLED' || b.value === 'PAUSED'; });
                amzSyncStatFilterLabel();
            }
            function amzEnsureStatIncludesPaused() {
                var s = amzStatFilterParts();
                if (!s || (s.all && s.all.checked)) return;
                var paused = s.boxes.filter(function (b) { return b.value === 'PAUSED'; })[0];
                if (paused && !paused.checked) {
                    paused.checked = true;
                    if (s.all) s.all.checked = s.boxes.every(function (b) { return b.checked; });
                    amzSyncStatFilterLabel();
                }
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
                    filter_campaign_status: amzStatFilterValue(),
                    filter_targets: g('amazonAdsFilterTargets'),
                    filter_inv: g('amazonAdsFilterInv'),
                    filter_acos: g('amazonAdsFilterAcos'),
                    filter_ads_cvr: g('amazonAdsFilterAdsCvr'),
                    filter_bgt_sync: amzSyncFilters.bgt || '',
                    filter_bid_sync: amzSyncFilters.bid || ''
                };
            }

            // ---- badges ----
            function amzSetText(id, txt) { var e = document.getElementById(id); if (e) e.textContent = txt; }
            var amzDistinctCampaignCount = null;
            function amzUpdateBadges(json) {
                var camp = (json && typeof json.distinctCampaignCount === 'number' && isFinite(json.distinctCampaignCount)) ? json.distinctCampaignCount : null;
                if (camp !== null) amzDistinctCampaignCount = camp;
                var acos = (json && typeof json.overallAcosPercent === 'number' && isFinite(json.overallAcosPercent)) ? json.overallAcosPercent : null;
                var spend = (json && typeof json.spendTotal === 'number' && isFinite(json.spendTotal)) ? json.spendTotal : null;
                var clicks = (json && typeof json.clicksTotal === 'number' && isFinite(json.clicksTotal)) ? json.clicksTotal : null;
                var sold = (json && typeof json.soldTotal === 'number' && isFinite(json.soldTotal)) ? json.soldTotal : null;
                var sales = (json && typeof json.salesTotal === 'number' && isFinite(json.salesTotal)) ? json.salesTotal : null;

                amzSetText('amazonAdsCampaignBadgeValue', camp === null ? '0' : Number(camp).toLocaleString('en-US'));
                amzSetText('amazonAdsOverallAcosBadgeValue', acos === null ? '—' : (Math.round(acos) + '%'));
                var acosWrap = document.getElementById('amazonAdsOverallAcosBadgeWrap');
                if (acosWrap) acosWrap.classList.toggle('is-high', acos !== null && acos > 40);
                amzSetText('amazonAdsSpendBadgeValue', spend === null ? '$0' : ('$' + Number(spend).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsClicksBadgeValue', clicks === null ? '0' : Number(clicks).toLocaleString('en-US'));
                amzSetText('amazonAdsSoldBadgeValue', sold === null ? '0' : Number(sold).toLocaleString('en-US'));
                amzSetText('amazonAdsSalesBadgeValue', sales === null ? '$0' : ('$' + Number(sales).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsCvrBadgeValue', (sold !== null && clicks && clicks > 0) ? ((sold / clicks * 100).toFixed(2) + '%') : '—');
                amzSetText('amazonAdsCpcBadgeValue', (spend !== null && clicks && clicks > 0) ? ('$' + (spend / clicks).toFixed(2)) : '$0');

                function amzMoneyBadge(n) {
                    return (typeof n === 'number' && isFinite(n)) ? ('$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 })) : '$0';
                }
                function amzAcosBadge(id, wrapId, n) {
                    var known = typeof n === 'number' && isFinite(n);
                    amzSetText(id, known ? (Math.round(n) + '%') : '—');
                    var wrap = document.getElementById(wrapId);
                    if (wrap) wrap.classList.toggle('is-high', known && n > 40);
                }
                var ySpend = json && json.yesterdaySpend;
                var ySales = json && json.yesterdaySales;
                var yAcos = json && json.yesterdayAcosPercent;
                var pSpend = json && json.projectedSpend;
                var pSales = json && json.projectedSales;
                var pAcos = json && json.projectedAcosPercent;
                amzSetText('amazonAdsYesterdaySpendBadgeValue', amzMoneyBadge(ySpend));
                amzSetText('amazonAdsYesterdaySalesBadgeValue', amzMoneyBadge(ySales));
                amzAcosBadge('amazonAdsYesterdayAcosBadgeValue', 'amazonAdsYesterdayAcosBadgeWrap', yAcos);
                amzSetText('amazonAdsProjectedSpendBadgeValue', amzMoneyBadge(pSpend));
                amzSetText('amazonAdsProjectedSalesBadgeValue', amzMoneyBadge(pSales));
                amzAcosBadge('amazonAdsProjectedAcosBadgeValue', 'amazonAdsProjectedAcosBadgeWrap', pAcos);
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
            var amzAjaxAbort = null;
            function amzAjaxRequestFunc(url, config, params) {
                var source = activeRawSourceKey || 'all_reports';
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
                } else if (cols.indexOf('ySpend') !== -1) {
                    body.set('order[0][column]', String(cols.indexOf('ySpend')));
                    body.set('order[0][dir]', 'desc');
                }
                var f = amzFilterPayload();
                Object.keys(f).forEach(function (k) { body.set(k, f[k]); });
                body.set('_token', csrfToken);
                if (amzAjaxAbort) {
                    try { amzAjaxAbort.abort(); } catch (e) {}
                }
                amzAjaxAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                return fetch(dataUrlTemplate + encodeURIComponent(source), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: body.toString(),
                    signal: amzAjaxAbort ? amzAjaxAbort.signal : undefined
                }).then(function (res) { return res.json(); }).catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        var aborted = new Error('aborted');
                        aborted.name = 'AbortError';
                        throw aborted;
                    }
                    throw err;
                });
            }

            table = new Tabulator('#amz-ads-raw-table', {
                columns: amzBuildColumns('all_reports'),
                ajaxURL: dataUrlTemplate + 'all_reports',
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
                initialSort: [{ column: 'ySpend', dir: 'desc' }],
                headerSortClickElement: 'icon',
                placeholder: 'No rows for this source.',
                selectableRows: true,
                ajaxResponse: function (url, params, response) {
                    if (!response || typeof response !== 'object') {
                        amzUpdateTotalBadge(0);
                        amzClearBadges();
                        amzApplyServerSyncCounts({});
                        return { last_page: 1, data: [] };
                    }
                    var size = parseInt(params.size, 10) || 100;
                    var filtered = Number(response.recordsFiltered);
                    if (!isFinite(filtered) || filtered < 0) filtered = 0;
                    var lastPage = Math.max(1, Math.ceil(filtered / size));
                    amzUpdateBadges(response);
                    amzApplyServerSyncCounts(response);
                    amzUpdateTotalBadge(filtered);
                    amzRefreshUiSoon();
                    amzRefreshU7PieDebounced();
                    var rows = Array.isArray(response.data) ? response.data : [];
                    return { last_page: lastPage, data: amzApplyAcosDrivenBgt(rows) };
                }
            });

            table.on('pageLoaded', amzRefreshUiSoon);
            table.on('dataLoaded', function () {
                amzRefreshUiSoon();
                if (typeof amzAutoPushPullOn === 'function' && amzAutoPushPullOn()) amzAutoPushChangedSbgt();
            });
            table.on('dataLoadError', function (error) {
                if (error && (error.name === 'AbortError' || String(error.message || error).indexOf('abort') !== -1)) {
                    return;
                }
                console.error('amazon-ads raw data load error', error);
                amzUpdateTotalBadge(NaN);
            });

            var AMZ_COL_VIS_URL = @json(url('/tabulator-column-visibility'));
            var AMZ_COL_VIS_CHANNEL = 'amazon_ads_all';
            var AMZ_COL_CAT_STORAGE = 'amazon_ads_all_col_cats_v1';
            var AMZ_COL_CAT_KEYS = ['basic', 'price', 'ads', 'other'];
            var AMZ_COL_CAT_LABELS = { basic: 'Basic', price: 'Price', ads: 'Ads', other: 'Other' };
            var amzColVisMap = {};

            function amzSkipColVisField(field) {
                if (!field || String(field).indexOf('__') === 0) return true;
                return HIDDEN_COLUMNS.indexOf(field) !== -1;
            }

            function amzColVisTitle(def) {
                var field = def && def.field ? String(def.field) : '';
                var raw = (def && def.title != null) ? def.title : field;
                var t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                return t || field;
            }

            function amzClassifyColumn(field, title) {
                var f = String(field || '');
                var t = String(title || field || '').toLowerCase();
                if (/^(cost|ACOS|Cvr|clicks|impressions|Prchase|purchases30d|sales|sales30d|L7spend|L2spend|L1spend|L1cost|L1clicks|projectedSpend|projectedSales|ySpend|ySales|U7%|U2%|U1%|CPC3|CPCAvg|CPC2|costPerClick|sbidHistory|sbgtHistory)$/i.test(f)
                    || /\b(acos|cvr|click|impr|sold|spend|spl30|cpc|sales|u7|u2|u1)\b/i.test(t)) {
                    return 'ads';
                }
                if (/^(Inv|INV|ovl30|dil|price|reviews|pageCvr|viewsL30|viewsL7)$/i.test(f)
                    || /\b(inv|dil|price|review|view|page cvr)\b/i.test(t)) {
                    return 'price';
                }
                if (/^(campaignName|campaignStatus|ruleStatus|activeAgain|matchType|state)$/i.test(f)
                    || /\b(campaign|stat|rule|match|active again)\b/i.test(t)) {
                    return 'basic';
                }
                return 'other';
            }

            function amzLoadColCats() {
                try {
                    var parsed = JSON.parse(localStorage.getItem(AMZ_COL_CAT_STORAGE) || '{}');
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function amzSaveColCats(map) {
                try { localStorage.setItem(AMZ_COL_CAT_STORAGE, JSON.stringify(map || {})); } catch (e) { /* ignore */ }
            }

            function amzSyncGroupHeaderCheckbox(groupEl) {
                if (!groupEl) return;
                var headerCb = groupEl.querySelector('.col-vis-group-toggle');
                var itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                if (!headerCb || !itemCbs.length) return;
                var checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function amzBindColVisDrag(li, groupEls) {
                li.draggable = true;
                li.addEventListener('dragstart', function (e) {
                    e.stopPropagation();
                    li.classList.add('col-vis-dragging');
                    e.dataTransfer.setData('text/plain', li.dataset.field || '');
                    e.dataTransfer.setData('text/col-vis-field', li.dataset.field || '');
                    e.dataTransfer.effectAllowed = 'move';
                });
                li.addEventListener('dragend', function () {
                    li.classList.remove('col-vis-dragging');
                    Object.keys(groupEls).forEach(function (k) {
                        groupEls[k].classList.remove('col-vis-drop-over');
                    });
                });
            }

            function amzBindColVisDropZone(group, list, groupEls) {
                [group, list].forEach(function (zone) {
                    zone.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.add('col-vis-drop-over');
                        e.dataTransfer.dropEffect = 'move';
                    });
                    zone.addEventListener('dragleave', function (e) {
                        if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
                    });
                    zone.addEventListener('drop', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.remove('col-vis-drop-over');
                        var field = e.dataTransfer.getData('text/col-vis-field') || e.dataTransfer.getData('text/plain');
                        if (!field) return;
                        var menu = document.getElementById('amz-ads-column-dropdown-menu');
                        var li = menu ? menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]') : null;
                        if (!li) return;
                        var nextCat = group.dataset.category;
                        if (!nextCat || li.dataset.group === nextCat) return;
                        var fromGroup = li.closest('.col-vis-group');
                        list.appendChild(li);
                        li.dataset.group = nextCat;
                        var cb = li.querySelector('input[type="checkbox"]');
                        if (cb) cb.dataset.group = nextCat;
                        var cats = amzLoadColCats();
                        cats[field] = nextCat;
                        amzSaveColCats(cats);
                        amzSyncGroupHeaderCheckbox(fromGroup);
                        amzSyncGroupHeaderCheckbox(group);
                    });
                });
            }

            function amzSaveColumnVisibility() {
                if (!table) return;
                var visibility = Object.assign({}, amzColVisMap);
                table.getColumns().forEach(function (col) {
                    var field = col.getField();
                    if (amzSkipColVisField(field)) return;
                    visibility[field] = col.isVisible();
                });
                amzColVisMap = visibility;
                fetch(AMZ_COL_VIS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ channel: AMZ_COL_VIS_CHANNEL, visibility: visibility }),
                }).catch(function (err) { console.error('Error saving column visibility:', err); });
            }

            function amzApplyColumnVisibility(map) {
                if (!table || !map || typeof map !== 'object') return;
                if (map.clicks === false) map.clicks = true;
                table.getColumns().forEach(function (col) {
                    var field = col.getField();
                    if (amzSkipColVisField(field) || !Object.prototype.hasOwnProperty.call(map, field)) return;
                    if (map[field]) col.show();
                    else col.hide();
                });
            }

            function amzLoadColumnVisibility() {
                return fetch(AMZ_COL_VIS_URL + '?channel=' + encodeURIComponent(AMZ_COL_VIS_CHANNEL), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.json(); })
                    .then(function (saved) {
                        amzColVisMap = (saved && typeof saved === 'object' && !Array.isArray(saved)) ? saved : {};
                        amzApplyColumnVisibility(amzColVisMap);
                        return amzColVisMap;
                    })
                    .catch(function (err) {
                        console.error('Error loading column visibility:', err);
                        return amzColVisMap;
                    });
            }

            function amzBuildColumnDropdown() {
                var menu = document.getElementById('amz-ads-column-dropdown-menu');
                if (!menu || !table) return;
                menu.innerHTML = '';
                var map = amzColVisMap || {};
                var catOverrides = amzLoadColCats();

                var groupsLi = document.createElement('li');
                groupsLi.className = 'col-vis-full';
                var groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';
                var lists = {};
                var groupEls = {};

                AMZ_COL_CAT_KEYS.forEach(function (cat) {
                    var group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;

                    var titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    var groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.dataset.group = cat;
                    groupCb.title = 'Select / deselect all in ' + AMZ_COL_CAT_LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(AMZ_COL_CAT_LABELS[cat]));
                    group.appendChild(titleEl);

                    var list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    list.dataset.category = cat;
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                    groupEls[cat] = group;
                    amzBindColVisDropZone(group, list, groupEls);
                });

                table.getColumns().forEach(function (col) {
                    var def = col.getDefinition();
                    var field = def.field;
                    if (amzSkipColVisField(field)) return;
                    var title = amzColVisTitle(def);
                    var cat = catOverrides[field];
                    if (AMZ_COL_CAT_KEYS.indexOf(cat) === -1) cat = amzClassifyColumn(field, title);
                    var isVisible = Object.prototype.hasOwnProperty.call(map, field)
                        ? map[field] !== false
                        : col.isVisible();

                    var li = document.createElement('li');
                    li.className = 'col-vis-item';
                    li.dataset.field = field;
                    li.dataset.group = cat;

                    var label = document.createElement('label');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = field;
                    checkbox.setAttribute('data-field', field);
                    checkbox.className = 'col-vis-field-toggle';
                    checkbox.dataset.group = cat;
                    checkbox.checked = isVisible;
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(' ' + title));
                    label.title = title + ' (drag to another header)';
                    li.appendChild(label);
                    amzBindColVisDrag(li, groupEls);
                    lists[cat].appendChild(li);
                });

                AMZ_COL_CAT_KEYS.forEach(function (cat) {
                    amzSyncGroupHeaderCheckbox(groupEls[cat]);
                });

                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);
            }

            function amzRefreshColumnBox() {
                return amzLoadColumnVisibility().then(function () {
                    amzBuildColumnDropdown();
                });
            }

            var amzColMenu = document.getElementById('amz-ads-column-dropdown-menu');
            if (amzColMenu) {
                amzColMenu.addEventListener('change', function (e) {
                    if (!e.target || e.target.type !== 'checkbox' || !table) return;
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        var checked = e.target.checked;
                        var groupEl = e.target.closest('.col-vis-group');
                        var itemCbs = groupEl ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]') : [];
                        itemCbs.forEach(function (cb) {
                            var field = cb.getAttribute('data-field') || cb.value;
                            cb.checked = checked;
                            var col = table.getColumn(field);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        amzSaveColumnVisibility();
                        return;
                    }
                    var field = e.target.getAttribute('data-field') || e.target.value;
                    var col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) col.show();
                    else col.hide();
                    amzSyncGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                    amzSaveColumnVisibility();
                });
                amzColMenu.addEventListener('click', function (e) {
                    if (e.target.closest('label') || e.target.type === 'checkbox') {
                        e.stopPropagation();
                    }
                });
            }

            amzRefreshColumnBox();

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
                var ok = activeRawSourceKey === 'sp_reports' || activeRawSourceKey === 'sb_reports' || activeRawSourceKey === 'all_reports';
                if (sbidBtn) {
                    sbidBtn.disabled = !ok;
                    sbidBtn.title = ok
                        ? 'Pulls live Amazon bid, compares to SBID, pushes only if different, then verifies.'
                        : 'Switch to All / SP / SB reports to sync SBID';
                }
                if (sbgtBtn) {
                    sbgtBtn.disabled = !ok;
                    sbgtBtn.title = ok
                        ? 'Pulls live Amazon BGT, compares to SBGT, pushes only if different, then verifies. SBGT 0 pauses.'
                        : 'Switch to All / SP / SB reports to sync SBGT';
                }
            }
            function amzUpdatePieButton() {
                var btn = document.getElementById('amazonAdsU7PieOpenBtn');
                if (!btn) return;
                var ok = PIE_SOURCES.indexOf(activeRawSourceKey) !== -1;
                btn.disabled = !ok;
                btn.title = ok ? 'Row counts by U7% band (U7 filter ignored).' : 'U7% mix is available for SP / SB / SD reports only';
            }

            function amzSyncInvFilterVisibility() {
                var wrap = document.getElementById('amazonAdsFilterInvWrap');
                if (!wrap) return;
                var cols = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].columns) ? rawSources[activeRawSourceKey].columns : [];
                wrap.style.display = cols.indexOf('Inv') === -1 ? 'none' : '';
            }
            function amzSwitchSource(sourceKey) {
                if (!sourceKey || !rawSources[sourceKey]) sourceKey = 'all_reports';
                activeRawSourceKey = sourceKey;
                amzSyncInvFilterVisibility();
                amzSetDatesToLatestForSource(sourceKey);
                amzClearBadges();
                amzUpdatePushButtons();
                amzUpdatePieButton();
                amzUpdateSourceLabel();
                if (table) {
                    table.setColumns(amzBuildColumns(sourceKey));
                    amzApplyColumnVisibility(amzColVisMap);
                    amzBuildColumnDropdown();
                    var srcCols = (rawSources[sourceKey] && rawSources[sourceKey].columns) ? rawSources[sourceKey].columns : [];
                    if (srcCols.indexOf('ySpend') === -1) {
                        try { table.clearSort(); } catch (e) {}
                    }
                    Promise.resolve(table.setData()).catch(function () {});
                }
            }

            var reportTypeEl = document.getElementById('amazonAdsFilterReportType');
            if (reportTypeEl) {
                reportTypeEl.addEventListener('change', function () { amzSwitchSource(this.value); });
            }

            // Auto-reload filters
            ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterInv', 'amazonAdsFilterTargets', 'amazonAdsFilterAcos', 'amazonAdsFilterAdsCvr'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', function () {
                    if (id === 'amazonAdsFilterAcos') amzTintAcosFilterSelect();
                    if (id === 'amazonAdsFilterAdsCvr') amzTintAdsCvrFilterSelect();
                    amzReloadGridForFilters();
                });
            });
            var statFilterEl = document.getElementById('amazonAdsFilterCampaignStatus');
            if (statFilterEl) {
                statFilterEl.addEventListener('change', function (e) {
                    var t = e.target;
                    if (!t || t.type !== 'checkbox') return;
                    var s = amzStatFilterParts();
                    if (!s) return;
                    if (t.hasAttribute('data-stat-all')) {
                        s.boxes.forEach(function (b) { b.checked = t.checked; });
                    } else if (s.all) {
                        s.all.checked = s.boxes.every(function (b) { return b.checked; });
                    }
                    amzSyncStatFilterLabel();
                    amzReloadGridForFilters();
                });
                amzSyncStatFilterLabel();
            }
            // Apply / Clear (dates need Apply)
            var applyBtn = document.getElementById('amazonAdsFilterApply');
            if (applyBtn) applyBtn.addEventListener('click', amzReloadGridForFilters);
            var clearBtn = document.getElementById('amazonAdsFilterClear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterInv', 'amazonAdsFilterTargets', 'amazonAdsFilterAcos', 'amazonAdsFilterAdsCvr'].forEach(function (id) {
                        var el = document.getElementById(id); if (el) el.value = '';
                    });
                    amzResetStatFilter();
                    amzSetDatesToLatestForSource(activeRawSourceKey);
                    var s = document.getElementById('amz-filter-search'); if (s) s.value = '';
                    amzTintAcosFilterSelect();
                    amzTintAdsCvrFilterSelect();
                    amzClearSyncFilters();
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
                    if (immediate) run(); else searchTimer = setTimeout(run, 500);
                };
                searchEl.addEventListener('input', function () { schedule(false); });
                searchEl.addEventListener('search', function () { schedule(true); });
                searchEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); schedule(true); } });
            }

            document.getElementById('amz-raw-refresh').addEventListener('click', function () {
                Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
            });
            document.querySelector('.amz-ads-all').addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('.amz-sync-badge') : null;
                if (!btn || !this.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                amzToggleSyncFilter(btn.getAttribute('data-sync-field'), btn.getAttribute('data-sync-color'));
            }, true);
            amzPaintSyncHeaders();
            document.getElementById('amazonAdsSectionExportBtn').addEventListener('click', function () {
                var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : 'export';
                var d = new Date().toISOString().slice(0, 10);
                table.download('csv', 'Amazon_' + tbl + '_Export_' + d + '.csv');
            });

            var amzCampSkusCache = {};
            var amzCampSkusLast = [];
            function amzMoneyHtml(n) {
                var x = parseFloat(n);
                if (!isFinite(x) || x <= 0) return '<span class="text-muted">—</span>';
                return '$' + x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function amzOpenSkuLmp(idx) {
                var s = amzCampSkusLast[idx] || {};
                var title = document.getElementById('amazonAdsSkuLmpModalLabel');
                var sub = document.getElementById('amazonAdsSkuLmpModalSub');
                var body = document.getElementById('amazonAdsSkuLmpTableBody');
                var comps = Array.isArray(s.competitors) ? s.competitors : [];
                if (title) title.textContent = 'LMP — ' + (s.sku || '');
                if (sub) {
                    var ours = parseFloat(s.price);
                    sub.textContent = (isFinite(ours) && ours > 0 ? ('Item price ' + '$' + ours.toFixed(2) + '. ') : '')
                        + 'LMP is item price plus paid shipping. Free shipping does not add.';
                }
                if (body) {
                    if (!comps.length) {
                        body.innerHTML = '<tr><td colspan="7" class="text-muted">No LMP competitors for this SKU.</td></tr>';
                    } else {
                        var lowest = parseFloat(s.lmp);
                        body.innerHTML = comps.map(function (c) {
                            var landed = parseFloat(c.lmp);
                            var isLow = isFinite(lowest) && isFinite(landed) && Math.abs(landed - lowest) < 0.01 && !c.ignored;
                            var ship = c.ship;
                            var shipHtml = '<span class="text-muted">—</span>';
                            if (ship === 0 || ship === '0') shipHtml = '<span style="color:#16a34a;font-weight:600;">Free</span>';
                            else if (isFinite(parseFloat(ship)) && parseFloat(ship) > 0) shipHtml = amzMoneyHtml(ship);
                            var img = c.image ? '<img class="amz-sku-inv-img" src="' + amzEsc(c.image) + '" alt="">' : '<span class="text-muted">—</span>';
                            var link = c.link
                                ? '<a href="' + amzEsc(c.link) + '" target="_blank" rel="noopener">Open</a>'
                                : '<span class="text-muted">—</span>';
                            return '<tr class="' + (isLow ? 'amz-sku-lmp-low' : '') + (c.ignored ? ' text-muted' : '') + '">'
                                + '<td>' + img + '</td>'
                                + '<td>' + amzEsc(c.asin || '—') + (c.ignored ? ' <span class="badge bg-secondary">Ignored</span>' : '') + '</td>'
                                + '<td>' + amzMoneyHtml(c.item_price) + '</td>'
                                + '<td>' + shipHtml + '</td>'
                                + '<td class="fw-semibold">' + amzMoneyHtml(c.lmp) + '</td>'
                                + '<td>' + amzEsc(c.seller || '—') + '</td>'
                                + '<td>' + link + '</td>'
                                + '</tr>';
                        }).join('');
                    }
                }
                var modalEl = document.getElementById('amazonAdsSkuLmpModal');
                if (modalEl && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
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
            var amzCampSkusCtx = { cid: '', cname: '', channel: '', source: '', parentFamily: '', parentSkus: [] };
            function amzIsSbCampaign(channel, adType, source) {
                var ch = String(channel || '').toLowerCase();
                if (ch === 'sb') return true;
                var t = String(adType || '').toUpperCase();
                if (t.indexOf('BRAND') !== -1 || t === 'SB') return true;
                return String(source || '') === 'sb_ads';
            }
            function amzShowParentSkusOn() {
                try {
                    var raw = localStorage.getItem('amzSbShowParentSkus');
                    if (raw === null) return true;
                    return raw !== '0';
                } catch (e) { return true; }
            }
            function amzSetShowParentSkus(on) {
                try { localStorage.setItem('amzSbShowParentSkus', on ? '1' : '0'); } catch (e) {}
            }
            function amzSetSbAdTools(on) {
                var tools = document.getElementById('amazonAdsSbAdTools');
                var col = document.querySelector('#amazonAdsCampaignSkusTable .amz-sb-ad-col');
                if (tools) tools.classList.toggle('d-none', !on);
                if (col) col.classList.toggle('d-none', !on);
                var box = document.getElementById('amazonAdsSbShowParent');
                if (box) box.checked = amzShowParentSkusOn();
            }
            function amzPaintCampSkus(skus) {
                var title = document.getElementById('amazonAdsCampaignSkusModalLabel');
                var load = document.getElementById('amazonAdsCampaignSkusLoading');
                var tbl = document.getElementById('amazonAdsCampaignSkusTable');
                var body = document.getElementById('amazonAdsCampaignSkusTableBody');
                var sbOn = amzIsSbCampaign(amzCampSkusCtx.channel, amzCampSkusCtx.adType, amzCampSkusCtx.source);
                var parentSkus = sbOn && amzShowParentSkusOn()
                    ? (amzCampSkusCtx.parentSkus || [])
                    : [];
                var q = '';
                var addInp = document.getElementById('amazonAdsSbAdAddInput');
                if (addInp) q = String(addInp.value || '').trim().toUpperCase();
                var rows = [];
                (skus || []).forEach(function (s) { rows.push(Object.assign({ on_ad: true }, s)); });
                parentSkus.forEach(function (s) { rows.push(Object.assign({ on_ad: false, from_parent: true }, s)); });
                if (q) {
                    rows = rows.filter(function (s) {
                        var sku = String(s.sku || '').toUpperCase();
                        var asin = String(s.asin || '').toUpperCase();
                        return sku.indexOf(q) !== -1 || asin.indexOf(q) !== -1;
                    });
                }
                if (load) load.classList.add('d-none');
                if (title) title.textContent = 'Campaign SKUs (' + rows.length + ')';
                amzSetSbAdTools(sbOn);
                if (!rows.length) {
                    if (tbl) tbl.classList.add('d-none');
                    if (body) body.innerHTML = '';
                    if (load) {
                        load.classList.remove('d-none');
                        load.textContent = q
                            ? 'No SKUs match “' + q + '”.'
                            : 'No SKUs found for this campaign (no product ads and the campaign name did not match a parent/SKU).';
                    }
                    return;
                }
                if (!body || !tbl) return;
                amzCampSkusLast = rows;
                body.innerHTML = rows.map(function (s, i) {
                    var onAd = !!s.on_ad;
                    var state = onAd ? String(s.state || '—') : 'Parent';
                    var color = onAd
                        ? (state.toUpperCase() === 'ENABLED' ? '#16a34a' : (state.toUpperCase() === 'PAUSED' ? '#dc2626' : '#6b7280'))
                        : '#2563eb';
                    var inv = s && s.inv != null && s.inv !== '' ? parseFloat(s.inv) : NaN;
                    var invHtml = isFinite(inv)
                        ? '<span class="amz-sku-inv-num' + (inv < 5 ? ' is-low' : '') + '">' + Math.round(inv) + '</span>'
                        : '<span class="text-muted">—</span>';
                    var img = s && s.image ? String(s.image) : '';
                    var imgHtml = img
                        ? '<img class="amz-sku-inv-img" src="' + amzEsc(img) + '" alt="' + amzEsc(s.sku || '') + '">'
                        : '<span class="amz-sku-inv-img d-inline-flex align-items-center justify-content-center text-muted">—</span>';
                    var price = parseFloat(s.price);
                    var lmp = parseFloat(s.lmp);
                    var lmpCount = parseInt(s.lmp_count, 10) || (Array.isArray(s.competitors) ? s.competitors.length : 0);
                    var lmpColor = (isFinite(lmp) && isFinite(price) && price > 0 && lmp < price) ? '#dc2626' : '#16a34a';
                    var lmpHtml = (isFinite(lmp) && lmp > 0) || lmpCount > 0
                        ? '<button type="button" class="btn btn-link btn-sm amz-sku-lmp-btn" data-sku-lmp-idx="' + i + '" style="color:' + lmpColor + ';">'
                            + ((isFinite(lmp) && lmp > 0) ? ('$' + lmp.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : 'LMP')
                            + (lmpCount ? (' (' + lmpCount + ')') : '')
                            + '</button>'
                        : '<span class="text-muted">—</span>';
                    var action = '';
                    if (sbOn) {
                        action = onAd
                            ? '<td><button type="button" class="btn btn-sm btn-outline-danger amz-sb-ad-remove" data-sku="' + amzEsc(s.sku || '') + '" data-asin="' + amzEsc(s.asin || '') + '" title="Remove from SB creative">Remove</button></td>'
                            : '<td><button type="button" class="btn btn-sm btn-outline-primary amz-sb-ad-add-row" data-sku="' + amzEsc(s.sku || '') + '" data-asin="' + amzEsc(s.asin || '') + '" title="Add this parent SKU to the SB creative">Add</button></td>';
                    }
                    var skuLabel = amzEsc(s.sku || '—');
                    if (!onAd) skuLabel += ' <span class="badge bg-light text-primary border" style="font-weight:500;">Parent</span>';
                    return '<tr>'
                        + '<td class="amz-sku-img-cell">' + imgHtml + '</td>'
                        + '<td class="text-center">' + invHtml + '</td>'
                        + '<td class="fw-semibold">' + skuLabel + '</td>'
                        + '<td>' + amzEsc(s.asin || '—') + '</td>'
                        + '<td>' + amzMoneyHtml(s.price) + '</td>'
                        + '<td>' + lmpHtml + '</td>'
                        + '<td>' + amzFormatSkuReviews(s) + '</td>'
                        + '<td><span style="color:' + color + ';font-weight:600;">' + amzEsc(state) + '</span></td>'
                        + action
                        + '</tr>';
                }).join('');
                tbl.classList.remove('d-none');
                amzSetSbAdTools(sbOn);
            }
            function amzRerenderCampSkus() {
                var cid = amzCampSkusCtx.cid;
                var pack = cid && amzCampSkusCache[cid];
                if (!pack) return;
                amzCampSkusCtx.channel = pack.channel || amzCampSkusCtx.channel;
                amzCampSkusCtx.source = pack.source || amzCampSkusCtx.source;
                amzCampSkusCtx.parentFamily = pack.parentFamily || '';
                amzCampSkusCtx.parentSkus = Array.isArray(pack.parentSkus) ? pack.parentSkus : [];
                amzPaintCampSkus(Array.isArray(pack.skus) ? pack.skus : []);
            }
            function amzOpenCampaignSkus(cid, cname, adType) {
                var title = document.getElementById('amazonAdsCampaignSkusModalLabel');
                var sub = document.getElementById('amazonAdsCampaignSkusModalSub');
                var load = document.getElementById('amazonAdsCampaignSkusLoading');
                var err = document.getElementById('amazonAdsCampaignSkusError');
                var tbl = document.getElementById('amazonAdsCampaignSkusTable');
                var body = document.getElementById('amazonAdsCampaignSkusTableBody');
                amzCampSkusCtx = { cid: cid, cname: cname || '', channel: '', source: '', adType: adType || '', parentFamily: '', parentSkus: [] };
                if (title) title.textContent = 'Campaign SKUs';
                if (sub) sub.textContent = cname || cid;
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                var okMsg = document.getElementById('amazonAdsCampaignSkusOk');
                if (okMsg && !amzSbAdBusy) { okMsg.classList.add('d-none'); okMsg.textContent = ''; }
                if (tbl) tbl.classList.add('d-none');
                if (body) body.innerHTML = '';
                var addInpOpen = document.getElementById('amazonAdsSbAdAddInput');
                if (addInpOpen) addInpOpen.value = '';
                amzSetSbAdTools(false);
                if (load) { load.classList.remove('d-none'); load.textContent = 'Loading…'; }
                var modalEl = document.getElementById('amazonAdsCampaignSkusModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                function applyCache(pack) {
                    amzCampSkusCtx.channel = pack.channel || '';
                    amzCampSkusCtx.source = pack.source || '';
                    amzCampSkusCtx.parentFamily = pack.parentFamily || '';
                    amzCampSkusCtx.parentSkus = Array.isArray(pack.parentSkus) ? pack.parentSkus : [];
                    if (sub) {
                        var src = pack.source || '';
                        var note = src === 'campaign_name'
                            ? 'SKUs from campaign name (no Amazon product ads on this campaign).'
                            : (src === 'sb_ads' ? 'SKUs from SB ads.' : (src ? 'SKUs from Amazon product ads.' : ''));
                        if (pack.parentFamily) {
                            note = (note ? note + ' ' : '') + 'Parent family ' + pack.parentFamily + ' (' + (pack.parentSkus || []).length + ' SKUs).';
                        }
                        sub.textContent = (cname || cid) + (note ? ' — ' + note : '');
                    }
                    amzPaintCampSkus(Array.isArray(pack.skus) ? pack.skus : []);
                }
                if (amzCampSkusCache[cid] && Array.isArray(amzCampSkusCache[cid].skus)) {
                    applyCache(amzCampSkusCache[cid]);
                    return;
                }
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
                        var parentSkus = (out.body && Array.isArray(out.body.parent_skus)) ? out.body.parent_skus : [];
                        var parentFamily = out.body && out.body.parent_family ? String(out.body.parent_family) : '';
                        if (!out.ok) {
                            if (load) load.classList.add('d-none');
                            if (err) { err.textContent = (out.body && out.body.message) ? out.body.message : 'Could not load SKUs.'; err.classList.remove('d-none'); }
                            return;
                        }
                        var src = out.body && out.body.source ? String(out.body.source) : '';
                        amzCampSkusCtx.source = src;
                        amzCampSkusCtx.channel = out.body && out.body.channel ? String(out.body.channel) : '';
                        amzCampSkusCtx.parentFamily = parentFamily;
                        amzCampSkusCtx.parentSkus = parentSkus;
                        if (sub) {
                            var note = src === 'campaign_name'
                                ? 'SKUs from campaign name (no Amazon product ads on this campaign).'
                                : (src === 'sb_ads' ? 'SKUs from SB ads.' : (src ? 'SKUs from Amazon product ads.' : ''));
                            if (parentFamily) {
                                note = (note ? note + ' ' : '') + 'Parent family ' + parentFamily + ' (' + parentSkus.length + ' SKUs).';
                            }
                            sub.textContent = (cname || cid) + (note ? ' — ' + note : '');
                        }
                        amzCampSkusCache[cid] = {
                            skus: skus,
                            parentSkus: parentSkus,
                            parentFamily: parentFamily,
                            channel: amzCampSkusCtx.channel,
                            source: src
                        };
                        amzPaintCampSkus(skus);
                    })
                    .catch(function () {
                        if (load) load.classList.add('d-none');
                        if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); }
                    });
            }
            var amzSbAdBusy = false;
            function amzSbAdSetBusy(on) {
                amzSbAdBusy = !!on;
                ['amazonAdsSbAdAddBtn', 'amazonAdsSbAdAddInput'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.disabled = amzSbAdBusy;
                });
                document.querySelectorAll('.amz-sb-ad-remove, .amz-sb-ad-add-row').forEach(function (el) {
                    el.disabled = amzSbAdBusy;
                });
            }
            function amzSbAdChange(kind, sku, asin) {
                var cid = amzCampSkusCtx.cid;
                var err = document.getElementById('amazonAdsCampaignSkusError');
                var ok = document.getElementById('amazonAdsCampaignSkusOk');
                if (!cid || amzSbAdBusy) return;
                if (kind === 'remove' && !window.confirm('Remove this product from the SB creative?')) return;
                var url = kind === 'remove' ? sbAdRemoveUrl : sbAdAddUrl;
                var payload = { campaign_id: cid, _token: csrfToken };
                if (sku) payload.skus = [sku];
                if (asin) payload.asins = [asin];
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                if (ok) { ok.classList.add('d-none'); ok.textContent = ''; }
                amzSbAdSetBusy(true);
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (out) {
                        var msg = (out.body && out.body.message) ? out.body.message : (kind === 'remove' ? 'Remove failed.' : 'Add failed.');
                        var failed = (out.body && Array.isArray(out.body.failed)) ? out.body.failed : [];
                        if (failed.length) {
                            msg += ' ' + failed.map(function (f) { return (f.sku || '') + ': ' + (f.message || ''); }).join(' ');
                        }
                        if (!out.ok || (out.body && out.body.success === false)) {
                            if (err) { err.textContent = msg; err.classList.remove('d-none'); }
                            amzSbAdSetBusy(false);
                            return;
                        }
                        delete amzCampSkusCache[cid];
                        amzOpenCampaignSkus(cid, amzCampSkusCtx.cname, amzCampSkusCtx.adType);
                        var okEl = document.getElementById('amazonAdsCampaignSkusOk');
                        if (okEl) { okEl.textContent = msg; okEl.classList.remove('d-none'); }
                    })
                    .catch(function () {
                        if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); }
                    })
                    .finally(function () { amzSbAdSetBusy(false); });
            }
            (function () {
                var addBtn = document.getElementById('amazonAdsSbAdAddBtn');
                var addInp = document.getElementById('amazonAdsSbAdAddInput');
                var parentBox = document.getElementById('amazonAdsSbShowParent');
                if (addBtn) addBtn.addEventListener('click', function () {
                    var v = addInp ? String(addInp.value || '').trim() : '';
                    if (!v) return;
                    amzSbAdChange('add', v, '');
                    if (addInp) addInp.value = '';
                });
                if (addInp) {
                    addInp.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            if (addBtn) addBtn.click();
                        }
                    });
                    addInp.addEventListener('input', function () { amzRerenderCampSkus(); });
                }
                if (parentBox) {
                    parentBox.checked = amzShowParentSkusOn();
                    parentBox.addEventListener('change', function () {
                        amzSetShowParentSkus(!!parentBox.checked);
                        amzRerenderCampSkus();
                    });
                }
            })();
            document.addEventListener('click', function (e) {
                var lowInv = e.target.closest ? e.target.closest('.amz-low-inv-btn') : null;
                var plus = e.target.closest ? e.target.closest('.amz-camp-skus-btn') : null;
                var opener = lowInv || plus;
                if (opener) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzOpenCampaignSkus(
                        opener.getAttribute('data-campaign-id') || '',
                        opener.getAttribute('data-campaign-name') || '',
                        opener.getAttribute('data-ad-type') || ''
                    );
                    return;
                }
                var sbRemove = e.target.closest ? e.target.closest('.amz-sb-ad-remove') : null;
                if (sbRemove) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzSbAdChange('remove', sbRemove.getAttribute('data-sku') || '', sbRemove.getAttribute('data-asin') || '');
                    return;
                }
                var sbAddRow = e.target.closest ? e.target.closest('.amz-sb-ad-add-row') : null;
                if (sbAddRow) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzSbAdChange('add', sbAddRow.getAttribute('data-sku') || '', sbAddRow.getAttribute('data-asin') || '');
                    return;
                }
                var lmpBtn = e.target.closest ? e.target.closest('.amz-sku-lmp-btn') : null;
                if (lmpBtn) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzOpenSkuLmp(+lmpBtn.getAttribute('data-sku-lmp-idx'));
                    return;
                }
                var lrangeBtn = e.target.closest ? e.target.closest('.amz-lrange-btn') : null;
                if (lrangeBtn) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzOpenLRangePop(lrangeBtn);
                    return;
                }
                var cpcDot = e.target.closest ? e.target.closest('.amz-cpc-avg-history-dot') : null;
                if (cpcDot) {
                    e.stopPropagation();
                    e.preventDefault();
                    amzOpenCpcAvgHistory({
                        campaign_id: cpcDot.getAttribute('data-campaign-id') || '',
                        campaignName: cpcDot.getAttribute('data-campaign-name') || '',
                        ad_type: cpcDot.getAttribute('data-ad-type') || '',
                        historyKind: cpcDot.getAttribute('data-history') || 'cpc'
                    });
                }
            });
            function amzLRangeModalEls() {
                return {
                    modal: document.getElementById('amazonAdsLRangeModal'),
                    title: document.getElementById('amazonAdsLRangeModalLabel'),
                    sub: document.getElementById('amazonAdsLRangeModalSub'),
                    loading: document.getElementById('amazonAdsLRangeModalLoading'),
                    error: document.getElementById('amazonAdsLRangeModalError'),
                    boxes: document.getElementById('amazonAdsLRangeBoxes')
                };
            }
            function amzShowLRangeModal() {
                var els = amzLRangeModalEls();
                if (!els.modal || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
                bootstrap.Modal.getOrCreateInstance(els.modal).show();
            }
            function amzOpenLRangePop(btn) {
                var els = amzLRangeModalEls();
                if (!els.modal) return;
                var cid = btn.getAttribute('data-campaign-id') || '';
                var name = btn.getAttribute('data-campaign-name') || cid;
                var metric = btn.getAttribute('data-lrange') === 'sales' ? 'sales' : 'spend';
                var title = metric === 'sales' ? 'L1–L7 Ads Sales' : 'L1–L7 Ads Spend';
                if (els.title) els.title.textContent = title;
                if (els.sub) els.sub.textContent = name;
                if (els.loading) { els.loading.classList.remove('d-none'); els.loading.textContent = 'Loading…'; }
                if (els.error) { els.error.classList.add('d-none'); els.error.textContent = ''; }
                if (els.boxes) { els.boxes.classList.add('d-none'); els.boxes.innerHTML = ''; }
                amzShowLRangeModal();
                if (!cid) {
                    if (els.loading) els.loading.classList.add('d-none');
                    if (els.error) { els.error.classList.remove('d-none'); els.error.textContent = 'No campaign id.'; }
                    return;
                }
                var qs = '?campaign_id=' + encodeURIComponent(cid)
                    + '&source=' + encodeURIComponent(activeRawSourceKey || '')
                    + '&ad_type=' + encodeURIComponent(btn.getAttribute('data-ad-type') || '');
                fetch(lRangeHistoryUrl + qs, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (out) {
                        if (els.loading) els.loading.classList.add('d-none');
                        var points = (out.body && Array.isArray(out.body.points)) ? out.body.points : [];
                        if (!out.ok || !out.body || out.body.ok === false) {
                            if (els.error) {
                                els.error.classList.remove('d-none');
                                els.error.textContent = (out.body && out.body.message) ? out.body.message : 'Could not load L1–L7.';
                            }
                            return;
                        }
                        var html = points.map(function (p) {
                            var raw = metric === 'sales' ? p.sales : p.spend;
                            var n = raw == null || raw === '' ? NaN : parseFloat(raw);
                            var empty = !isFinite(n);
                            var val = empty ? '—' : ('$' + n.toFixed(2));
                            return '<div class="amz-lrange-box' + (empty ? ' is-empty' : '') + '">'
                                + '<div class="amz-lrange-box-ln">' + amzEsc(p.label || '') + '</div>'
                                + '<div class="amz-lrange-box-date">' + amzEsc(p.date || '') + '</div>'
                                + '<div class="amz-lrange-box-val">' + val + '</div>'
                                + '</div>';
                        }).join('');
                        if (els.boxes) {
                            els.boxes.innerHTML = html || '<div class="small text-muted">No daily rows.</div>';
                            els.boxes.classList.remove('d-none');
                        }
                    })
                    .catch(function () {
                        if (els.loading) els.loading.classList.add('d-none');
                        if (els.error) { els.error.classList.remove('d-none'); els.error.textContent = 'Network or server error.'; }
                    });
            }
            var amzCpcAvgChart = null;
            var amzCpcAvgRow = null;
            var amzCpcAvgDays = 30;
            var amzHistoryKind = 'cpc';
            function amzCpcRangeLabel(days) {
                return days === 0 ? 'Lifetime' : (days + ' Days');
            }
            function amzHistoryIsCvr() { return amzHistoryKind === 'cvr'; }
            function amzHistoryIsPercent() { return amzHistoryKind === 'cvr' || amzHistoryKind === 'acos'; }
            function amzHistoryLowerIsBetter() { return amzHistoryKind === 'cpc' || amzHistoryKind === 'acos'; }
            function amzHistoryMetricLabel() {
                if (amzHistoryKind === 'cvr') return 'CVR';
                if (amzHistoryKind === 'acos') return 'ACOS';
                if (amzHistoryKind === 'lbid') return 'Lbid';
                if (amzHistoryKind === 'sbid') return 'SBID';
                if (amzHistoryKind === 'sbgt') return 'SBGT';
                return 'CPC';
            }
            function amzHistoryFmt(v) {
                var n = Number(v);
                if (!isFinite(n)) return '-';
                if (amzHistoryIsPercent()) return n.toFixed(1) + '%';
                return '$' + n.toFixed(2);
            }
            function amzCpcDotColors(values) {
                var gray = '#6c757d', green = '#28a745', red = '#dc3545';
                var eps = amzHistoryIsPercent() ? 0.05 : 0.005;
                return values.map(function (v, i) {
                    if (i === 0) return gray;
                    var diff = v - values[i - 1];
                    if (Math.abs(diff) <= eps) return amzHistoryKind === 'lbid' ? green : gray;
                    var improved = amzHistoryLowerIsBetter() ? diff < 0 : diff > 0;
                    return improved ? green : red;
                });
            }
            function amzRenderCpcAvgChart(points) {
                var canvas = document.getElementById('amazonAdsCpcAvgHistoryCanvas');
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzCpcAvgChart) { amzCpcAvgChart.destroy(); amzCpcAvgChart = null; }
                var labels = points.map(function (p) { return p.date; });
                var values = points.map(function (p) {
                    if (amzHistoryKind === 'cvr') return Number(p.cvr);
                    if (amzHistoryKind === 'acos') return Number(p.acos);
                    if (amzHistoryKind === 'lbid') return Number(p.lbid);
                    if (amzHistoryKind === 'sbid') return Number(p.sbid);
                    if (amzHistoryKind === 'sbgt') return Number(p.sbgt);
                    return Number(p.cpc);
                });
                var dataMin = Math.min.apply(null, values);
                var dataMax = Math.max.apply(null, values);
                var sorted = values.slice().sort(function (a, b) { return a - b; });
                var mid = Math.floor(sorted.length / 2);
                var median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                var range = dataMax - dataMin || 1;
                var yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
                var yMin = Math.max(0, dataMin - range * 0.12);
                var yMax = dataMax + yPad;
                var dotColors = amzCpcDotColors(values);
                var maxIdx = 0, minIdx = 0;
                values.forEach(function (v, i) {
                    if (v >= values[maxIdx]) maxIdx = i;
                    if (v <= values[minIdx]) minIdx = i;
                });
                var highestEl = document.getElementById('amazonAdsCpcAvgHighest');
                var medianEl = document.getElementById('amazonAdsCpcAvgMedian');
                var lowestEl = document.getElementById('amazonAdsCpcAvgLowest');
                var highestLbl = document.getElementById('amazonAdsCpcAvgHighestLabel');
                var lowestLbl = document.getElementById('amazonAdsCpcAvgLowestLabel');
                if (highestEl) { highestEl.textContent = amzHistoryFmt(dataMax); highestEl.style.color = dotColors[maxIdx]; }
                if (highestLbl) highestLbl.style.color = dotColors[maxIdx];
                if (medianEl) { medianEl.textContent = amzHistoryFmt(median); medianEl.style.color = '#6c757d'; }
                if (lowestEl) { lowestEl.textContent = amzHistoryFmt(dataMin); lowestEl.style.color = dotColors[minIdx]; }
                if (lowestLbl) lowestLbl.style.color = dotColors[minIdx];
                amzCpcAvgChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: amzHistoryMetricLabel(),
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
                            pointHoverBackgroundColor: dotColors,
                            pointHoverBorderColor: dotColors,
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [{
                        id: 'cpcMedianLine',
                        afterDraw: function (chart) {
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
                    }, {
                        id: 'cpcValueLabels',
                        afterDraw: function (chart) {
                            var dataset = chart.data.datasets[0];
                            var meta = chart.getDatasetMeta(0);
                            var ctx = chart.ctx;
                            var lastIdx = meta.data.length - 1;
                            var anchors = [];
                            ctx.save();
                            ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                            ctx.textAlign = 'left';
                            ctx.textBaseline = 'middle';
                            meta.data.forEach(function (point, i) {
                                var offsetY = (i % 2 === 0) ? -12 : -26;
                                if (i === lastIdx) offsetY = (lastIdx % 2 === 0) ? -26 : -12;
                                if (anchors.length) {
                                    var prev = anchors[anchors.length - 1];
                                    if (Math.abs(point.x - prev.x) < 36 && Math.abs((point.y + offsetY) - prev.y) < 14) {
                                        offsetY = (offsetY === -12) ? -28 : -12;
                                    }
                                }
                                anchors.push({ x: point.x, y: point.y + offsetY });
                                ctx.save();
                                ctx.fillStyle = dotColors[i];
                                ctx.translate(point.x, point.y + offsetY);
                                ctx.rotate(-Math.PI / 5);
                                ctx.fillText(amzHistoryFmt(dataset.data[i]), 2, 0);
                                ctx.restore();
                            });
                            ctx.restore();
                        }
                    }],
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
                                    labelColor: function (context) {
                                        var c = dotColors[context.dataIndex] || '#6c757d';
                                        return { borderColor: c, backgroundColor: c, borderWidth: 2, borderRadius: 8 };
                                    },
                                    label: function (context) {
                                        var idx = context.dataIndex;
                                        var parts = ['Value: ' + amzHistoryFmt(context.raw)];
                                        if (idx > 0) {
                                            var diff = context.raw - values[idx - 1];
                                            var arrow = diff < 0 ? '▼' : (diff > 0 ? '▲' : '▬');
                                            parts.push('vs Yesterday: ' + arrow + ' ' + amzHistoryFmt(Math.abs(diff)));
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
                                ticks: { font: { size: 9 }, callback: function (value) { return amzHistoryFmt(value); } }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 60,
                                    minRotation: 60,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });
            }
            function amzLoadCpcAvgHistory() {
                var row = amzCpcAvgRow || {};
                var cid = String(row.campaign_id || '').replace(/\D+/g, '');
                var name = row.campaignName != null ? String(row.campaignName) : (cid || 'Campaign');
                var title = document.getElementById('amazonAdsCpcAvgHistoryTitle');
                var box = document.getElementById('amazonAdsCpcAvgHistoryContainer');
                var load = document.getElementById('amazonAdsCpcAvgHistoryLoading');
                var empty = document.getElementById('amazonAdsCpcAvgHistoryEmpty');
                var emptyText = document.getElementById('amazonAdsCpcAvgHistoryEmptyText');
                var metric = amzHistoryMetricLabel();
                if (title) title.textContent = name + ' - ' + metric + ' (Rolling ' + amzCpcRangeLabel(amzCpcAvgDays) + ')';
                if (box) box.style.display = 'none';
                if (empty) empty.style.display = 'none';
                if (load) load.style.display = 'block';
                if (amzCpcAvgChart) { amzCpcAvgChart.destroy(); amzCpcAvgChart = null; }
                if (!cid) {
                    if (load) load.style.display = 'none';
                    if (emptyText) emptyText.textContent = 'This row has no campaign id.';
                    if (empty) empty.style.display = 'block';
                    return;
                }
                var historyUrl = cpcAvgHistoryUrl;
                if (amzHistoryKind === 'cvr') historyUrl = ltCvrHistoryUrl;
                else if (amzHistoryKind === 'acos') historyUrl = ltAcosHistoryUrl;
                else if (amzHistoryKind === 'lbid') historyUrl = lbidHistoryUrl;
                else if (amzHistoryKind === 'sbid') historyUrl = sbidHistoryUrl;
                else if (amzHistoryKind === 'sbgt') historyUrl = sbgtHistoryUrl;
                var qs = '?campaign_id=' + encodeURIComponent(cid)
                    + '&source=' + encodeURIComponent(activeRawSourceKey || '')
                    + '&ad_type=' + encodeURIComponent(row.ad_type != null ? String(row.ad_type) : '')
                    + '&days=' + encodeURIComponent(String(amzCpcAvgDays));
                fetch(historyUrl + qs, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                    .then(function (out) {
                        if (load) load.style.display = 'none';
                        var points = (out.body && Array.isArray(out.body.points)) ? out.body.points : [];
                        if (!out.ok || !out.body || out.body.ok === false || !points.length) {
                            if (emptyText) emptyText.textContent = (out.body && out.body.message) ? out.body.message : ('Daily ' + amzHistoryMetricLabel() + ' is not available for this campaign.');
                            if (empty) empty.style.display = 'block';
                            return;
                        }
                        if (box) box.style.display = 'flex';
                        amzRenderCpcAvgChart(points);
                    })
                    .catch(function () {
                        if (load) load.style.display = 'none';
                        if (emptyText) emptyText.textContent = 'Network or server error.';
                        if (empty) empty.style.display = 'block';
                    });
            }
            function amzOpenCpcAvgHistory(row) {
                amzCpcAvgRow = row || {};
                amzHistoryKind = (row && ['cvr', 'acos', 'lbid', 'sbid', 'sbgt'].indexOf(row.historyKind) !== -1) ? row.historyKind : 'cpc';
                amzCpcAvgDays = 30;
                var range = document.getElementById('amazonAdsCpcAvgRange');
                if (range) range.value = '30';
                var modalEl = document.getElementById('amazonAdsCpcAvgHistoryModal');
                if (!modalEl) return;
                if (window.bootstrap && bootstrap.Modal) {
                    modalEl.style.zIndex = '10050';
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                amzLoadCpcAvgHistory();
            }
            var amzCpcRangeEl = document.getElementById('amazonAdsCpcAvgRange');
            if (amzCpcRangeEl) {
                amzCpcRangeEl.addEventListener('change', function () {
                    var days = parseInt(amzCpcRangeEl.value, 10);
                    if (!isFinite(days) || days === amzCpcAvgDays) return;
                    amzCpcAvgDays = days;
                    amzLoadCpcAvgHistory();
                });
            }

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
            function amzRowChannel(row) {
                if (activeRawSourceKey === 'sb_reports') return 'sb';
                if (activeRawSourceKey === 'sp_reports') return 'sp';
                var t = String((row && row.ad_type) || '').toUpperCase();
                return t.indexOf('BRAND') !== -1 ? 'sb' : 'sp';
            }
            function amzCollectLiveSyncRows(kind) {
                if (activeRawSourceKey !== 'sp_reports' && activeRawSourceKey !== 'sb_reports' && activeRawSourceKey !== 'all_reports') return [];
                var sourceRows = amzCurrentPushRows();
                var out = [];
                var seen = {};
                sourceRows.forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id == null ? '' : String(row.campaign_id).trim();
                    if (!cid || seen[cid]) return;
                    var payload = {
                        campaign_id: cid,
                        channel: amzRowChannel(row),
                        campaignName: row.campaignName != null ? String(row.campaignName) : '',
                        ad_type: row.ad_type != null ? String(row.ad_type) : ''
                    };
                    if (kind === 'bgt' || kind === 'both') {
                        var sbgt = amzPickSbgtTierFromRow(row);
                        if (sbgt !== null) payload.sbgt = sbgt;
                    }
                    if (kind === 'bid' || kind === 'both') {
                        var bid = amzPickBidFromRow(row);
                        if (bid !== null) payload.sbid = bid;
                    }
                    if (payload.sbgt == null && payload.sbid == null) return;
                    seen[cid] = true;
                    out.push(payload);
                });
                return out;
            }
            function amzCollectChangedSbgtRows() {
                if (activeRawSourceKey !== 'sp_reports' && activeRawSourceKey !== 'sb_reports' && activeRawSourceKey !== 'all_reports') return [];
                if (!table) return [];
                var out = [];
                (table.getData() || []).forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id == null ? '' : String(row.campaign_id).trim();
                    var sbgt = amzPickSbgtTierFromRow(row);
                    var bid = amzPickBidFromRow(row);
                    if (!cid || (sbgt === null && bid === null)) return;
                    var status = String(row.campaignStatus || '').toUpperCase();
                    if (sbgt === 0 && (status === 'PAUSED' || status === 'ARCHIVED') && bid === null) return;
                    var key = cid + ':' + (sbgt == null ? '' : sbgt) + ':' + (bid == null ? '' : bid);
                    if (amzSbgtAutoPushedKey[key]) return;
                    var payload = {
                        campaign_id: cid,
                        channel: amzRowChannel(row),
                        campaignName: row.campaignName != null ? String(row.campaignName) : '',
                        ad_type: row.ad_type != null ? String(row.ad_type) : ''
                    };
                    if (sbgt !== null) payload.sbgt = sbgt;
                    if (bid !== null) payload.sbid = bid;
                    out.push(payload);
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
            function amzPostLiveSyncChunk(chunkRows) {
                return fetch(syncLiveBidBgtUrl, {
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
                        return { ok: false, body: { message: 'Chunk returned non-JSON HTTP ' + res.status } };
                    });
                });
            }
            function amzAutoPushChangedSbgt() {
                if (amzSbgtAutoPushBusy) return;
                if (activeRawSourceKey !== 'sp_reports' && activeRawSourceKey !== 'sb_reports' && activeRawSourceKey !== 'all_reports') return;
                var rows = amzCollectChangedSbgtRows();
                if (!rows.length) return;
                rows.forEach(function (r) {
                    amzSbgtAutoPushedKey[r.campaign_id + ':' + (r.sbgt == null ? '' : r.sbgt) + ':' + (r.sbid == null ? '' : r.sbid)] = true;
                });
                amzSbgtAutoPushBusy = true;
                var chunkSize = (typeof AMZ_PUSH_CHUNK_SIZE === 'number' && AMZ_PUSH_CHUNK_SIZE > 0) ? AMZ_PUSH_CHUNK_SIZE : 5;
                var chunks = [];
                for (var i = 0; i < rows.length; i += chunkSize) chunks.push(rows.slice(i, i + chunkSize));
                var messages = [];
                var synced = 0;
                var failed = 0;
                function runNext(index) {
                    if (index >= chunks.length) {
                        amzSbgtAutoPushBusy = false;
                        amzShowPushResult(
                            failed > 0
                                ? ('Live sync — ' + synced + ' verified, ' + failed + ' not synced')
                                : ('Live sync — ' + synced + ' campaign(s) verified'),
                            'Pulled live Amazon BGT/BID, pushed only mismatches, marked synced only after verify.\n' + messages.join('\n'),
                            failed > 0 ? 'error' : 'success'
                        );
                        if (table) Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
                        return;
                    }
                    amzMarkChunkPending(chunks[index]);
                    amzPostLiveSyncChunk(chunks[index])
                        .then(function (out) {
                            var b = (out && out.body) || {};
                            synced += Number(b.synced) || 0;
                            failed += Number(b.failed) || 0;
                            messages.push('[chunk ' + (index + 1) + '/' + chunks.length + '] ' + (b.message || 'finished'));
                            amzPatchRowsFromLiveSync(b.results || []);
                            (b.results || []).forEach(function (r) {
                                if (r && r.status === 'synced' && r.fields && r.fields.bgt && r.fields.bgt.reason === 'paused_zero_sbgt') {
                                    amzMarkPausedZeroSbgtRows([r.campaign_id]);
                                }
                            });
                            runNext(index + 1);
                        })
                        .catch(function (err) {
                            failed += chunks[index].length;
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
                    var failedN = 0;
                    bodies.forEach(function (b) { failedN += Number(b && b.failed) || 0; });
                    var text = (messages.length ? messages.join('\n') + '\n\n' : '')
                        + bodies.map(function (b, idx) {
                            return '--- chunk ' + (idx + 1) + '/' + chunkCount + ' ---\n'
                                + JSON.stringify(b, null, 2);
                        }).join('\n\n');
                    amzShowPushResult(title, text || '(no response body)', failedN > 0 ? 'error' : 'success');
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
                    amzMarkChunkPending(chunk);
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
                            amzPatchRowsFromLiveSync(b.results || []);
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
                    var rows = amzCollectLiveSyncRows('bid');
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    var chunks = Math.ceil(rows.length / AMZ_PUSH_CHUNK_SIZE) || 1;
                    amzRunPush({
                        url: syncLiveBidBgtUrl,
                        btn: pushSbidBtn,
                        rows: rows,
                        chunkSize: AMZ_PUSH_CHUNK_SIZE,
                        label: 'SBID live sync',
                        confirmMsg: 'Sync SBID for ' + scope + '? Pulls live Amazon bid first, pushes only mismatches, and marks synced only after verify.',
                        loadingTitle: 'Verifying SBID…',
                        loadingDetail: 'Pull → compare → push → verify for ' + rows.length + ' row(s) in chunks of ' + AMZ_PUSH_CHUNK_SIZE + '.'
                    });
                });
            }
            var pushSbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
            if (pushSbgtBtn) {
                pushSbgtBtn.addEventListener('click', function () {
                    var rows = amzCollectLiveSyncRows('bgt');
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    amzRunPush({
                        url: syncLiveBidBgtUrl,
                        btn: pushSbgtBtn,
                        rows: rows,
                        chunkSize: AMZ_PUSH_CHUNK_SIZE,
                        label: 'SBGT live sync',
                        confirmMsg: 'Sync SBGT for ' + scope + '? Pulls live Amazon BGT first, pushes only mismatches, and marks synced only after verify. SBGT 0 pauses.',
                        loadingTitle: 'Verifying SBGT…',
                        loadingDetail: 'Pull → compare → push → verify for ' + rows.length + ' row(s) in chunks of ' + AMZ_PUSH_CHUNK_SIZE + '.'
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

            // ---- BGT slab counts (every campaign in the current filters, not just this page) ----
            var amzBgtUniverse = { key: '', rows: null, promise: null, seq: 0 };
            var amzBgtSaved = (window.amazonAdsBgtCounts && typeof window.amazonAdsBgtCounts === 'object') ? window.amazonAdsBgtCounts : null;
            var amzBgtStaged = { columns: {}, sum: null, dirty: false };
            var amzBgtSaveCountsTimer = null;
            function amzBgtFilterKey() {
                var payload = {};
                try { payload = amzFilterPayload(); } catch (e) { payload = {}; }
                var search = '';
                try { search = amzSearchQueryVal(); } catch (e2) { search = ''; }
                return String(activeRawSourceKey || '') + '\n' + search + '\n' + JSON.stringify(payload);
            }
            function amzBgtRulesModalOpen() {
                var ids = ['amazonAdsBgtRulesModal', 'amazonAdsSbidRuleModal'];
                for (var i = 0; i < ids.length; i++) {
                    var el = document.getElementById(ids[i]);
                    if (el && el.classList.contains('show')) return true;
                }
                return false;
            }
            function amzBgtStatusCount() {
                try {
                    if (amzBgtUniverse.key === amzBgtFilterKey() && Array.isArray(amzBgtUniverse.rows)) {
                        return { n: amzBgtUniverse.rows.length, full: true };
                    }
                    if (typeof amzDistinctCampaignCount === 'number' && isFinite(amzDistinctCampaignCount)) {
                        return { n: amzDistinctCampaignCount, full: false };
                    }
                    return { n: 0, full: false };
                } catch (e) {
                    return { n: 0, full: false };
                }
            }
            function amzBgtWriteStatus(text, loading) {
                var st = document.getElementById('amz-bgt-status');
                if (!st) return;
                st.className = 'small text-muted me-auto d-inline-flex align-items-center';
                st.innerHTML = '';
                if (loading) {
                    var spin = document.createElement('span');
                    spin.className = 'spinner-border spinner-border-sm text-primary me-2';
                    spin.setAttribute('role', 'status');
                    spin.setAttribute('aria-label', 'Loading');
                    st.appendChild(spin);
                }
                var label = document.createElement('span');
                label.textContent = text;
                st.appendChild(label);
                var count = amzBgtStatusCount();
                if (count.n) {
                    var num = document.createElement('strong');
                    num.className = 'ms-2';
                    num.textContent = count.n.toLocaleString() + ' campaigns';
                    st.appendChild(num);
                }
            }
            function amzBgtSetCountStatus(msg) {
                var st = document.getElementById('amz-bgt-status');
                if (!st) return;
                if (msg) {
                    if (st.dataset.phase === 'save' || st.dataset.phase === 'apply') {
                        amzBgtWriteStatus(st.dataset.phase === 'save' ? 'Saving rules…' : 'Applying to the page…', true);
                        return;
                    }
                    st.dataset.countStatus = '1';
                    amzBgtWriteStatus(msg, true);
                    return;
                }
                if (st.dataset.phase === 'apply' || st.dataset.phase === 'save') return;
                if (st.dataset.countStatus === '1') {
                    st.textContent = '';
                    st.className = 'small text-muted me-auto';
                    delete st.dataset.countStatus;
                }
            }
            function amzBgtRefreshAllRuleCounts() {
                if (typeof amzAcosRefreshCounts === 'function') amzAcosRefreshCounts();
                if (typeof amzBgtViewsRefreshCounts === 'function') amzBgtViewsRefreshCounts();
                if (typeof amzBgtCvrRefreshCounts === 'function') amzBgtCvrRefreshCounts();
                if (typeof amzBgtPrcRefreshCounts === 'function') amzBgtPrcRefreshCounts();
                if (typeof amzBgtReviewsRefreshCounts === 'function') amzBgtReviewsRefreshCounts();
                if (typeof amzBgtDilRefreshCounts === 'function') amzBgtDilRefreshCounts();
                if (typeof amzBgtInvRefreshCounts === 'function') amzBgtInvRefreshCounts();
                if (typeof amzBgtSpendRefreshCounts === 'function') amzBgtSpendRefreshCounts();
                if (typeof amzSbidPaint === 'function') amzSbidPaint();
            }
            function amzBgtEnsureUniverse() {
                var key = amzBgtFilterKey();
                if (amzBgtUniverse.key === key && Array.isArray(amzBgtUniverse.rows)) {
                    return Promise.resolve(amzBgtUniverse.rows);
                }
                if (amzBgtUniverse.key === key && amzBgtUniverse.failed && !amzBgtUniverse.promise) {
                    return Promise.resolve([]);
                }
                if (amzBgtUniverse.key === key && amzBgtUniverse.promise) return amzBgtUniverse.promise;
                var seq = ++amzBgtUniverse.seq;
                amzBgtUniverse.key = key;
                amzBgtUniverse.rows = null;
                amzBgtUniverse.failed = false;
                if (!amzBgtHasSavedCounts()) amzBgtSetCountStatus('Counting all campaigns…');
                var source = activeRawSourceKey || 'all_reports';
                var body = new URLSearchParams();
                body.set('draw', '1');
                body.set('start', '0');
                body.set('length', '20000');
                body.set('bgt_universe', '1');
                body.set('search[value]', (function () { try { return amzSearchQueryVal(); } catch (e) { return ''; } })());
                body.set('search[regex]', 'false');
                var f = {};
                try { f = amzFilterPayload(); } catch (e2) { f = {}; }
                Object.keys(f).forEach(function (k) { body.set(k, f[k]); });
                body.set('_token', csrfToken);
                amzBgtUniverse.promise = fetch(dataUrlTemplate + encodeURIComponent(source), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: body.toString()
                }).then(function (res) { return res.json(); }).then(function (json) {
                    if (seq !== amzBgtUniverse.seq) return [];
                    if (!json || json.ok !== true || !Array.isArray(json.campaigns)) {
                        amzBgtUniverse.promise = null;
                        amzBgtUniverse.failed = true;
                        amzBgtSetCountStatus('');
                        amzBgtRefreshAllRuleCounts();
                        return [];
                    }
                    amzBgtUniverse.rows = json.campaigns;
                    amzBgtUniverse.promise = null;
                    amzBgtUniverse.failed = false;
                    amzBgtSetCountStatus('');
                    amzBgtRefreshAllRuleCounts();
                    return json.campaigns;
                }).catch(function () {
                    if (seq === amzBgtUniverse.seq) {
                        amzBgtUniverse.promise = null;
                        amzBgtUniverse.failed = true;
                        amzBgtSetCountStatus('');
                        amzBgtRefreshAllRuleCounts();
                    }
                    return [];
                });
                return amzBgtUniverse.promise;
            }
            var amzBgtCountsStamp = '';
            function amzBgtPollStoredCounts() {
                if (amzBgtPollStoredCounts.timer) return;
                var tick = function () {
                    fetch(bgtCountsGetUrl, {
                        method: 'GET',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    }).then(function (r) { return r.json(); }).then(function (body) {
                        var next = body && body.counts;
                        var stamp = body && body.updated_at ? String(body.updated_at) : '';
                        if (next && next.columns && stamp !== amzBgtCountsStamp) {
                            amzBgtCountsStamp = stamp;
                            amzBgtSaved = next;
                            window.amazonAdsBgtCounts = next;
                            if (typeof amzBgtRefreshAllRuleCounts === 'function') amzBgtRefreshAllRuleCounts();
                        }
                        var wait = (body && body.running) || !amzBgtHasSavedCounts() ? 3000 : 30000;
                        amzBgtPollStoredCounts.timer = setTimeout(function () {
                            amzBgtPollStoredCounts.timer = null;
                            tick();
                        }, wait);
                    }).catch(function () {
                        amzBgtPollStoredCounts.timer = setTimeout(function () {
                            amzBgtPollStoredCounts.timer = null;
                            tick();
                        }, 8000);
                    });
                };
                tick();
            }
            function amzBgtRequestRecount() {
                fetch(bgtCountsRefreshUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).catch(function () {});
                if (amzBgtPollStoredCounts.timer) {
                    clearTimeout(amzBgtPollStoredCounts.timer);
                    amzBgtPollStoredCounts.timer = null;
                }
                amzBgtPollStoredCounts();
            }
            function amzBgtGridRows() {
                if (!table || typeof table.getData !== 'function') return [];
                try { return table.getData() || []; } catch (e) { return []; }
            }
            var amzBgtInvBands = [];
            function amzBgtSeedRuleBands() {
                if (amzBgtSeedRuleBands.done) return;
                amzBgtSeedRuleBands.done = true;
                if (!amzCurrentBands.length && window.amazonAdsBgtRule) {
                    var acosBands = Array.isArray(window.amazonAdsBgtRule.bands) ? window.amazonAdsBgtRule.bands : [];
                    amzCurrentBands = acosBands.map(function (b) {
                        return { acos_from: Number(b.acos_from != null ? b.acos_from : 0), acos_to: Number(b.acos_to != null ? b.acos_to : 9999), sbgt: b.sbgt, label: b.label != null ? b.label : '', color: b.color || '#6c757d' };
                    });
                }
                if (!amzBgtViewsBands.length) amzBgtViewsBands = amzBgtViewsNormalizeBands((window.amazonAdsBgtViewsRule && window.amazonAdsBgtViewsRule.bands) || []);
                if (!amzBgtCvrBands.length) amzBgtCvrBands = amzBgtCvrNormalizeBands((window.amazonAdsBgtCvrRule && window.amazonAdsBgtCvrRule.bands) || []);
                if (!amzBgtPrcBands.length) amzBgtPrcBands = amzBgtPrcNormalizeBands((window.amazonAdsBgtPrcRule && window.amazonAdsBgtPrcRule.bands) || []);
                if (!amzBgtReviewsBands.length) amzBgtReviewsBands = amzBgtReviewsNormalizeBands((window.amazonAdsBgtReviewsRule && window.amazonAdsBgtReviewsRule.bands) || []);
                if (!amzBgtDilBands.length) amzBgtDilBands = amzBgtDilNormalizeBands((window.amazonAdsBgtDilRule && window.amazonAdsBgtDilRule.bands) || []);
                if (!amzBgtInvBands.length) amzBgtInvBands = amzBgtInvNormalizeBands((window.amazonAdsBgtInvRule && window.amazonAdsBgtInvRule.bands) || []);
            }
            function amzBgtCountingNote() {
                if (amzBgtUniverse.failed) {
                    return '<div class="amz-bgt-leg-row"><span>Count did not finish. Close and open BGT Rules to try again.</span></div>';
                }
                var n = (typeof amzDistinctCampaignCount === 'number' && isFinite(amzDistinctCampaignCount))
                    ? Number(amzDistinctCampaignCount).toLocaleString() + ' campaigns'
                    : 'campaigns';
                return '<div class="amz-bgt-leg-row"><span class="spinner-border spinner-border-sm text-primary me-1" role="status"></span><span>Counting ' + n + '…</span></div>';
            }
            function amzBgtCountRows() {
                amzBgtSeedRuleBands();
                var key = amzBgtFilterKey();
                if (amzBgtUniverse.key === key && Array.isArray(amzBgtUniverse.rows)) return amzBgtUniverse.rows;
                return null;
            }
            function amzBgtFirstBandIndex(value, bands, fromKey, toKey) {
                if (value == null || !isFinite(value) || !Array.isArray(bands)) return -1;
                for (var i = 0; i < bands.length; i++) {
                    var from = parseFloat(bands[i][fromKey]);
                    var to = parseFloat(bands[i][toKey]);
                    if (!isFinite(from) || !isFinite(to)) continue;
                    if (value >= from && value <= to) return i;
                }
                return -1;
            }
            function amzBgtCountByBands(bands, fromKey, toKey, valueOfRow) {
                var rows = amzBgtCountRows();
                if (!rows) return null;
                var counts = (bands || []).map(function () { return 0; });
                var unmatched = 0;
                rows.forEach(function (row) {
                    var idx = amzBgtFirstBandIndex(valueOfRow(row), bands, fromKey, toKey);
                    if (idx >= 0) counts[idx]++;
                    else unmatched++;
                });
                counts.unmatched = unmatched;
                counts.campaigns = rows.length;
                counts._live = true;
                return counts;
            }
            function amzBgtHasSavedCounts() {
                return !!(amzBgtSaved && amzBgtSaved.columns && Object.keys(amzBgtSaved.columns).length);
            }
            function amzBgtStoredColumn(key, bands) {
                var saved = amzBgtSaved && amzBgtSaved.columns ? amzBgtSaved.columns[key] : null;
                if (!saved || !Array.isArray(saved.counts) || !saved.counts.length) return null;
                if (bands && saved.counts.length !== bands.length) return null;
                var counts = saved.counts.map(function (n) { return Number(n) || 0; });
                counts.unmatched = Number(saved.unmatched) || 0;
                counts.campaigns = Number(saved.campaigns) || 0;
                return counts;
            }
            function amzBgtStageColumn(key, counts) {
                var list = [];
                for (var i = 0; i < counts.length; i++) list.push(Number(counts[i]) || 0);
                amzBgtStaged.columns[key] = {
                    counts: list,
                    unmatched: Number(counts.unmatched) || 0,
                    campaigns: Number(counts.campaigns) || 0
                };
                amzBgtStaged.dirty = true;
            }
            function amzBgtScheduleSaveCounts() {
                if (!amzBgtStaged.dirty || !bgtCountsSaveUrl) return;
                if (amzBgtSaveCountsTimer) clearTimeout(amzBgtSaveCountsTimer);
                amzBgtSaveCountsTimer = setTimeout(function () {
                    amzBgtSaveCountsTimer = null;
                    var columns = {};
                    var base = (amzBgtSaved && amzBgtSaved.columns) || {};
                    Object.keys(base).forEach(function (k) { columns[k] = base[k]; });
                    Object.keys(amzBgtStaged.columns).forEach(function (k) { columns[k] = amzBgtStaged.columns[k]; });
                    var payload = {
                        filter: (function () { try { return amzBgtFilterKey(); } catch (e) { return ''; } })(),
                        columns: columns,
                        sum: amzBgtStaged.sum || (amzBgtSaved && amzBgtSaved.sum) || null
                    };
                    fetch(bgtCountsSaveUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ counts: payload })
                    }).then(function (res) { return res.json(); }).then(function (body) {
                        if (body && body.counts) {
                            amzBgtSaved = body.counts;
                            window.amazonAdsBgtCounts = body.counts;
                        } else {
                            amzBgtSaved = payload;
                        }
                        amzBgtStaged.dirty = false;
                    }).catch(function () {});
                }, 700);
            }
            function amzBgtOnBudgetChange() {
                if (amzBgtCountRows()) amzBgtPaintSum();
            }
            function amzBgtRefreshCountCells() {}
            var amzBgtColCharts = {};
            var AMZ_BGT_COL_COLORS = ['#7c3aed', '#2563eb', '#16a34a', '#f59e0b', '#f97316', '#dc2626', '#64748b', '#0ea5e9'];
            function amzBgtFmtRange(v) {
                var n = parseFloat(v);
                if (!isFinite(n)) return '';
                var r = Math.round(n * 100) / 100;
                return r === Math.floor(r) ? String(r) : r.toFixed(2);
            }
            function amzBgtRound2(n) {
                return Math.round(Number(n) * 100) / 100;
            }
            function amzBgtSlabSpan(from, to) {
                var f = parseFloat(from), t = parseFloat(to);
                if (!isFinite(f) || !isFinite(t) || t >= 9999 || t <= f) return null;
                return t - f;
            }
            function amzBgtApplyNextSlab(bands, index, fromKey, toKey) {
                if (!bands[index] || !bands[index + 1]) return false;
                var to = parseFloat(bands[index][toKey]);
                if (!isFinite(to)) return false;
                var next = bands[index + 1];
                var nextFrom = parseFloat(next[fromKey]);
                var nextTo = parseFloat(next[toKey]);
                var open = isFinite(nextTo) && nextTo >= 9999;
                var width = amzBgtSlabSpan(nextFrom, nextTo);
                next[fromKey] = amzBgtRound2(to);
                if (open) return true;
                if (width != null) {
                    next[toKey] = amzBgtRound2(to + width);
                    return true;
                }
                var curSpan = amzBgtSlabSpan(bands[index][fromKey], to);
                if (curSpan != null && (!isFinite(nextTo) || nextTo <= to)) next[toKey] = amzBgtRound2(to + curSpan);
                return true;
            }
            function amzBgtPaintSlabInputs(tbody, bands, fromKey, toKey) {
                if (!tbody) return;
                bands.forEach(function (b, i) {
                    var fromInp = tbody.querySelector('input[data-idx="' + i + '"][data-field="' + fromKey + '"]');
                    var toInp = tbody.querySelector('input[data-idx="' + i + '"][data-field="' + toKey + '"]');
                    if (fromInp && document.activeElement !== fromInp) fromInp.value = b[fromKey] != null ? b[fromKey] : '';
                    if (toInp && document.activeElement !== toInp) toInp.value = b[toKey] != null ? b[toKey] : '';
                });
            }
            function amzBgtCascadeNextSlabs(bands, index, fromKey, toKey, tbody) {
                var changed = false;
                for (var i = index; i < bands.length - 1; i++) {
                    if (!amzBgtApplyNextSlab(bands, i, fromKey, toKey)) break;
                    changed = true;
                }
                if (changed) amzBgtPaintSlabInputs(tbody, bands, fromKey, toKey);
            }
            function amzBgtNextAddedSlab(bands, fromKey, toKey, amtKey) {
                var n = bands.length;
                var last = n ? bands[n - 1] : null;
                var prev = n > 1 ? bands[n - 2] : null;
                var lastFrom = last ? parseFloat(last[fromKey]) : NaN;
                var lastTo = last ? parseFloat(last[toKey]) : NaN;
                var span = amzBgtSlabSpan(lastFrom, lastTo);
                if (span == null && prev) span = amzBgtSlabSpan(prev[fromKey], prev[toKey]);
                if (span == null && prev) {
                    var prevFrom = parseFloat(prev[fromKey]);
                    if (isFinite(lastFrom) && isFinite(prevFrom) && lastFrom !== prevFrom) span = Math.abs(lastFrom - prevFrom);
                }
                if (span == null || span <= 0) span = 10;
                var lastAmt = last ? parseFloat(last[amtKey]) : NaN;
                var prevAmt = prev ? parseFloat(prev[amtKey]) : NaN;
                var delta = (isFinite(lastAmt) && isFinite(prevAmt)) ? (lastAmt - prevAmt) : 1;
                if (!isFinite(delta) || delta === 0) delta = 1;
                var nextAmt = isFinite(lastAmt) ? amzBgtRound2(lastAmt + delta) : 1;
                var open = isFinite(lastTo) && lastTo >= 9999;
                var from, to;
                if (!last) {
                    from = 0;
                    to = amzBgtRound2(span);
                } else if (open) {
                    from = isFinite(lastFrom) ? amzBgtRound2(lastFrom + span) : amzBgtRound2(span);
                    to = 9999;
                    last[toKey] = from;
                } else {
                    from = amzBgtRound2(lastTo);
                    to = amzBgtRound2(lastTo + span);
                }
                return { from: from, to: to, amt: nextAmt };
            }
            function amzBgtBandRangeLabel(band) {
                var pairs = [
                    ['acos_from', 'acos_to'],
                    ['views_from', 'views_to'],
                    ['cvr_from', 'cvr_to'],
                    ['prc_from', 'prc_to'],
                    ['rev_from', 'rev_to'],
                    ['dil_from', 'dil_to'],
                    ['inv_from', 'inv_to'],
                    ['spend_from', 'spend_to']
                ];
                for (var i = 0; i < pairs.length; i++) {
                    if (!band || band[pairs[i][0]] == null || band[pairs[i][0]] === '' || band[pairs[i][1]] == null || band[pairs[i][1]] === '') continue;
                    return amzBgtFmtRange(band[pairs[i][0]]) + '–' + amzBgtFmtRange(band[pairs[i][1]]);
                }
                return '';
            }
            function amzBgtPaintColumn(key, bands, counts) {
                var legend = document.getElementById('amz-bgt-leg-' + key);
                var canvas = document.getElementById('amz-bgt-chart-' + key);
                if (!legend || !canvas || typeof Chart === 'undefined') return;
                if (!counts) counts = amzBgtStoredColumn(key, bands);
                if (!counts) { legend.innerHTML = amzBgtCountingNote(); return; }
                if (counts._live) {
                    amzBgtStageColumn(key, counts);
                    amzBgtScheduleSaveCounts();
                }
                var rows = (bands || []).map(function (b, i) {
                    var n = counts && counts[i] != null ? Number(counts[i]) : 0;
                    if (!isFinite(n)) n = 0;
                    var label = amzBgtBandRangeLabel(b) || ('Band ' + (i + 1));
                    var color = (b && b.color) ? String(b.color) : AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length];
                    return { label: label, n: n, color: color };
                });
                var unmatched = counts && typeof counts.unmatched === 'number' ? counts.unmatched : 0;
                if (unmatched > 0) rows.push({ label: 'No match', n: unmatched, color: '#94a3b8' });
                var total = counts && typeof counts.campaigns === 'number'
                    ? counts.campaigns
                    : rows.reduce(function (s, r) { return s + r.n; }, 0);
                legend.innerHTML = rows.map(function (r) {
                    var pct = total > 0 ? Math.round((r.n / total) * 100) : 0;
                    return '<div class="amz-bgt-leg-row"><span class="amz-bgt-swatch" style="background:' + r.color + '"></span><span>' + amzEsc(r.label) + '</span><strong>' + r.n + '</strong><span class="amz-bgt-leg-pct">' + pct + '%</span></div>';
                }).join('') + (rows.length ? '<div class="amz-bgt-leg-row amz-bgt-leg-total"><span class="amz-bgt-swatch" style="background:transparent"></span><span>Total</span><strong>' + total + '</strong><span class="amz-bgt-leg-pct">' + (total > 0 ? '100%' : '0%') + '</span></div>' : '');
                if (amzBgtColCharts[key]) {
                    try { amzBgtColCharts[key].destroy(); } catch (e) {}
                    amzBgtColCharts[key] = null;
                }
                amzBgtColCharts[key] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(function (r) { return r.label; }),
                        datasets: [{
                            data: rows.map(function (r) { return r.n; }),
                            backgroundColor: rows.map(function (r) { return r.color; }),
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
                            x: { display: false, grid: { display: false } },
                            y: { display: false, beginAtZero: true, grid: { display: false } }
                        }
                    }
                });
                requestAnimationFrame(function () {
                    if (amzBgtColCharts[key]) {
                        try { amzBgtColCharts[key].resize(); } catch (e) {}
                    }
                });
                if (key !== 'sum') amzBgtPaintSum();
            }
            function amzBgtBandAmount(bands, idx, key) {
                if (!Array.isArray(bands) || idx < 0 || !bands[idx]) return null;
                var n = parseFloat(bands[idx][key]);
                return isFinite(n) ? n : null;
            }
            function amzBgtDraftParts(row) {
                return {
                    acos: amzBgtBandAmount(amzCurrentBands, amzBgtFirstBandIndex(amzAcosValueOfRow(row), amzCurrentBands, 'acos_from', 'acos_to'), 'sbgt'),
                    views: amzBgtBandAmount(amzBgtViewsBands, amzBgtFirstBandIndex(amzBgtViewsValueOfRow(row), amzBgtViewsBands, 'views_from', 'views_to'), 'bgt'),
                    cvr: amzBgtBandAmount(amzBgtCvrBands, amzBgtFirstBandIndex(amzBgtCvrValueOfRow(row), amzBgtCvrBands, 'cvr_from', 'cvr_to'), 'bgt'),
                    prc: amzBgtBandAmount(amzBgtPrcBands, amzBgtFirstBandIndex(amzBgtPrcValueOfRow(row), amzBgtPrcBands, 'prc_from', 'prc_to'), 'bgt'),
                    reviews: amzBgtBandAmount(amzBgtReviewsBands, amzBgtReviewsBandIndexForRating(amzBgtReviewsRatingOfRow(row), amzBgtReviewsBands), 'bgt'),
                    dil: amzBgtBandAmount(amzBgtDilBands, amzBgtFirstBandIndex(amzBgtDilValueOfRow(row), amzBgtDilBands, 'dil_from', 'dil_to'), 'bgt'),
                    inv: amzBgtBandAmount(amzBgtInvBands, amzBgtFirstBandIndex(amzBgtInvValueOfRow(row), amzBgtInvBands, 'inv_from', 'inv_to'), 'bgt')
                };
            }
            function amzBgtFloorSum(parts) {
                var sum = 0, has = false;
                parts.forEach(function (n) {
                    if (n === null || n === undefined || !isFinite(n)) return;
                    has = true;
                    sum += n;
                });
                if (!has) return null;
                sum = Math.floor(Math.round(sum * 1e6) / 1e6);
                return sum < 1 ? 0 : sum;
            }
            function amzBgtFmtMoney(n) {
                if (!isFinite(n)) return '—';
                var r = Math.round(n * 100) / 100;
                return r === Math.floor(r) ? String(r) : r.toFixed(2);
            }
            function amzBgtPaintBudgetBadges(rows, totalN, sbgtTotal) {
                var box = document.getElementById('amz-bgt-budget-badges');
                var dailyEl = document.getElementById('amazonAdsDailyBudgetBadgeValue');
                var dailyWrap = document.getElementById('amazonAdsDailyBudgetBadgeWrap');
                var monthlyEl = document.getElementById('amazonAdsMonthlyBudgetBadgeValue');
                var monthlyWrap = document.getElementById('amazonAdsMonthlyBudgetBadgeWrap');
                var dailyRounded = Math.round(sbgtTotal);
                var money = '$' + dailyRounded.toLocaleString('en-US');
                var monthMoney = '$' + (dailyRounded * 30).toLocaleString('en-US');
                var lines = (rows || []).map(function (r) {
                    var day = Number(r.label) * r.n;
                    return r.n.toLocaleString('en-US') + ' campaigns at $' + r.label + ' = $' + Math.round(day).toLocaleString('en-US') + '/day';
                });
                var tip = (lines.length ? lines.join('\n') + '\n' : '') + 'Total daily budget ' + money + ' across ' + totalN.toLocaleString('en-US') + ' campaigns';
                var monthTip = 'Monthly budget ' + monthMoney + ' = daily budget ' + money + ' × 30';
                if (dailyEl) dailyEl.textContent = money;
                if (dailyWrap) dailyWrap.title = tip;
                if (monthlyEl) monthlyEl.textContent = monthMoney;
                if (monthlyWrap) monthlyWrap.title = monthTip;
                if (!box) return;
                var chips = (rows || []).map(function (r) {
                    var day = Number(r.label) * r.n;
                    var title = r.n.toLocaleString('en-US') + ' campaigns at $' + r.label + ' = $' + Math.round(day).toLocaleString('en-US') + ' per day';
                    return '<span class="amz-bgt-budget-badge" style="--bgt-c:' + r.color + '" title="' + amzEsc(title) + '">'
                        + '<span class="amz-bgt-budget-amt">$' + amzEsc(r.label) + '</span>'
                        + '<span class="amz-bgt-budget-n">' + r.n.toLocaleString('en-US') + ' campaigns</span>'
                        + '<span class="amz-bgt-budget-day">$' + Math.round(day).toLocaleString('en-US') + '/day</span>'
                        + '</span>';
                }).join('');
                chips += '<span class="amz-bgt-budget-badge amz-bgt-budget-badge--total" title="' + amzEsc(tip) + '">'
                    + '<span class="amz-bgt-budget-amt">Daily budget</span>'
                    + '<span class="amz-bgt-budget-n">' + money + '</span>'
                    + '<span class="amz-bgt-budget-day">' + totalN.toLocaleString('en-US') + ' campaigns</span>'
                    + '</span>';
                chips += '<span class="amz-bgt-budget-badge amz-bgt-budget-badge--total" title="' + amzEsc(monthTip) + '">'
                    + '<span class="amz-bgt-budget-amt">Monthly budget</span>'
                    + '<span class="amz-bgt-budget-n">' + monthMoney + '</span>'
                    + '<span class="amz-bgt-budget-day">daily × 30</span>'
                    + '</span>';
                box.innerHTML = chips;
            }
            function amzBgtPaintSum() {
                var legend = document.getElementById('amz-bgt-leg-sum');
                var canvas = document.getElementById('amz-bgt-chart-sum');
                var tbody = document.getElementById('amz-bgt-sum-tbody');
                if (!legend && !tbody) return;
                var order = [
                    { key: 'views', label: 'Views' },
                    { key: 'cvr', label: 'CVR' },
                    { key: 'acos', label: 'ACOS' },
                    { key: 'prc', label: 'PRC' },
                    { key: 'reviews', label: 'Reviews' },
                    { key: 'dil', label: 'Dil' },
                    { key: 'inv', label: 'Inv' }
                ];
                var partCount = {};
                var partSum = {};
                order.forEach(function (p) { partCount[p.key] = 0; partSum[p.key] = 0; });
                var buckets = {};
                var campaigns = 0;
                var sbgtTotal = 0;
                var sumRows = amzBgtCountRows();
                if (!sumRows) {
                    if (amzBgtSaved && amzBgtSaved.sum && amzBgtApplySavedSum(amzBgtSaved.sum)) return;
                    if (legend) legend.innerHTML = amzBgtCountingNote();
                    return;
                }
                sumRows.forEach(function (row) {
                    var parts;
                    try { parts = amzBgtDraftParts(row); } catch (e) { return; }
                    order.forEach(function (p) {
                        var n = parts[p.key];
                        if (n === null || !isFinite(n)) return;
                        partCount[p.key]++;
                        partSum[p.key] += n;
                    });
                    var total = amzBgtFloorSum(order.map(function (p) { return parts[p.key]; }));
                    if (total === null) return;
                    campaigns++;
                    sbgtTotal += total;
                    buckets[total] = (buckets[total] || 0) + 1;
                });
                var keys = Object.keys(buckets).map(function (k) { return +k; }).sort(function (a, b) { return a - b; });
                var rows = keys.map(function (k, i) {
                    return {
                        label: String(k),
                        n: buckets[k],
                        color: k === 0 ? '#dc2626' : AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                    };
                });
                var totalN = rows.reduce(function (s, r) { return s + r.n; }, 0);
                amzBgtPaintBudgetBadges(rows, totalN, sbgtTotal);
                if (legend) {
                    legend.innerHTML = rows.map(function (r) {
                        var pct = totalN > 0 ? Math.round((r.n / totalN) * 100) : 0;
                        return '<div class="amz-bgt-leg-row"><span class="amz-bgt-swatch" style="background:' + r.color + '"></span><span>' + amzEsc(r.label) + '</span><strong>' + r.n + '</strong><span class="amz-bgt-leg-pct">' + pct + '%</span></div>';
                    }).join('') + (rows.length ? '<div class="amz-bgt-leg-row amz-bgt-leg-total"><span class="amz-bgt-swatch" style="background:transparent"></span><span>Total</span><strong>' + totalN + '</strong><span class="amz-bgt-leg-pct">' + (totalN > 0 ? '100%' : '0%') + '</span></div>' : '');
                }
                if (canvas && typeof Chart !== 'undefined') {
                    if (amzBgtColCharts.sum) { try { amzBgtColCharts.sum.destroy(); } catch (e) {} amzBgtColCharts.sum = null; }
                    amzBgtColCharts.sum = new Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: rows.map(function (r) { return r.label; }),
                            datasets: [{
                                data: rows.map(function (r) { return r.n; }),
                                backgroundColor: rows.map(function (r) { return r.color; }),
                                borderWidth: 0,
                                borderRadius: 6,
                                maxBarThickness: 48
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: function (items) { return 'SBGT ' + ((items[0] && items[0].label) || ''); },
                                        label: function (item) {
                                            var n = item.parsed && item.parsed.y != null ? item.parsed.y : item.raw;
                                            var pct = totalN > 0 ? Math.round((Number(n) / totalN) * 100) : 0;
                                            return n + ' campaigns (' + pct + '%)';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    grid: { display: false },
                                    title: { display: true, text: 'SBGT', color: '#94a3b8', font: { size: 11, weight: '600' } },
                                    ticks: { color: '#334155', font: { size: 12, weight: '600' } }
                                },
                                y: {
                                    display: true,
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    ticks: { color: '#94a3b8', precision: 0, font: { size: 11 } },
                                    title: { display: true, text: 'Campaigns', color: '#94a3b8', font: { size: 11, weight: '600' } }
                                }
                            }
                        }
                    });
                }
                var countTotal = 0;
                var bgtTotal = 0;
                order.forEach(function (p) {
                    countTotal += partCount[p.key];
                    bgtTotal += partSum[p.key];
                });
                amzBgtStaged.sum = {
                    rows: rows.map(function (r) { return { label: String(r.label), n: r.n, color: r.color }; }),
                    totalN: totalN,
                    sbgtTotal: sbgtTotal,
                    campaigns: campaigns,
                    countTotal: countTotal,
                    bgtTotal: bgtTotal,
                    parts: order.map(function (p) {
                        return { label: p.label, count: partCount[p.key], sum: partSum[p.key] };
                    })
                };
                amzBgtStaged.dirty = true;
                amzBgtScheduleSaveCounts();
                if (tbody) {
                    var html = order.map(function (p) {
                        return '<tr><td>' + amzEsc(p.label) + '</td><td class="text-center">' + partCount[p.key].toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(partSum[p.key]) + '</td></tr>';
                    }).join('');
                    html += '<tr class="amz-bgt-count-total"><td>Total</td><td class="text-center">' + countTotal.toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(bgtTotal) + '</td></tr>';
                    html += '<tr class="fw-semibold"><td>SBGT</td><td class="text-center">' + campaigns.toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(sbgtTotal) + '</td></tr>';
                    tbody.innerHTML = html;
                }
            }
            function amzBgtApplySavedSum(sum) {
                if (!sum || !Array.isArray(sum.rows)) return false;
                var legend = document.getElementById('amz-bgt-leg-sum');
                var canvas = document.getElementById('amz-bgt-chart-sum');
                var tbody = document.getElementById('amz-bgt-sum-tbody');
                var rows = sum.rows.map(function (r) {
                    return { label: String(r.label != null ? r.label : ''), n: Number(r.n) || 0, color: r.color || '#64748b' };
                });
                var totalN = Number(sum.totalN) || rows.reduce(function (s, r) { return s + r.n; }, 0);
                amzBgtPaintBudgetBadges(rows, totalN, Number(sum.sbgtTotal) || 0);
                if (legend) {
                    legend.innerHTML = rows.map(function (r) {
                        var pct = totalN > 0 ? Math.round((r.n / totalN) * 100) : 0;
                        return '<div class="amz-bgt-leg-row"><span class="amz-bgt-swatch" style="background:' + r.color + '"></span><span>' + amzEsc(r.label) + '</span><strong>' + r.n + '</strong><span class="amz-bgt-leg-pct">' + pct + '%</span></div>';
                    }).join('') + (rows.length ? '<div class="amz-bgt-leg-row amz-bgt-leg-total"><span class="amz-bgt-swatch" style="background:transparent"></span><span>Total</span><strong>' + totalN + '</strong><span class="amz-bgt-leg-pct">' + (totalN > 0 ? '100%' : '0%') + '</span></div>' : '');
                }
                if (canvas && typeof Chart !== 'undefined') {
                    if (amzBgtColCharts.sum) { try { amzBgtColCharts.sum.destroy(); } catch (e) {} amzBgtColCharts.sum = null; }
                    amzBgtColCharts.sum = new Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: rows.map(function (r) { return r.label; }),
                            datasets: [{ data: rows.map(function (r) { return r.n; }), backgroundColor: rows.map(function (r) { return r.color; }), borderWidth: 0, borderRadius: 6, maxBarThickness: 48 }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { display: true, grid: { display: false }, ticks: { color: '#334155', font: { size: 12, weight: '600' } } },
                                y: { display: true, beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', precision: 0, font: { size: 11 } } }
                            }
                        }
                    });
                }
                if (tbody && Array.isArray(sum.parts)) {
                    var html = sum.parts.map(function (p) {
                        return '<tr><td>' + amzEsc(p.label || '') + '</td><td class="text-center">' + (Number(p.count) || 0).toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(Number(p.sum) || 0) + '</td></tr>';
                    }).join('');
                    html += '<tr class="amz-bgt-count-total"><td>Total</td><td class="text-center">' + (Number(sum.countTotal) || 0).toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(Number(sum.bgtTotal) || 0) + '</td></tr>';
                    html += '<tr class="fw-semibold"><td>SBGT</td><td class="text-center">' + (Number(sum.campaigns) || 0).toLocaleString('en-US') + '</td><td class="text-end">$' + amzBgtFmtMoney(Number(sum.sbgtTotal) || 0) + '</td></tr>';
                    tbody.innerHTML = html;
                }
                return true;
            }
            function amzBgtNoteSaved(label) {
                var st = document.getElementById('amz-bgt-status');
                if (st) st.textContent = label + ' saved. Grid refreshing…';
            }

            // ---- BGT rule modal (ACOS bands -> SBGT) ----
            var amzCurrentBands = [];
            function amzAcosValueOfRow(row) {
                var n = parseFloat(row && row.ltAcos);
                return isFinite(n) ? n : null;
            }
            function amzAcosCounts(bands) {
                return amzBgtCountByBands(bands, 'acos_from', 'acos_to', amzAcosValueOfRow);
            }
            function amzAcosRefreshCounts() {
                var counts = amzAcosCounts(amzCurrentBands);
                amzBgtRefreshCountCells('amazonAdsBgtRuleBandsBody', counts);
                amzBgtPaintColumn('acos', amzCurrentBands, counts);
            }
            function amzRenderBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtRuleBandsBody');
                if (!tbody) return;
                var counts = amzAcosCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="0.1" class="form-control form-control-sm" value="' + (band.acos_from != null ? band.acos_from : '') + '" data-idx="' + i + '" data-field="acos_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.1" class="form-control form-control-sm" value="' + (band.acos_to != null ? band.acos_to : '') + '" data-idx="' + i + '" data-field="acos_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.sbgt != null ? band.sbgt : '') + '" data-idx="' + i + '" data-field="sbgt" title="0 pauses the campaign. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Remove band"><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeAcosBand = function () {
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (!amzCurrentBands[idx]) return;
                        amzCurrentBands[idx][fld] = (fld === 'sbgt' || fld === 'acos_from' || fld === 'acos_to')
                            ? (this.value === '' ? '' : parseFloat(this.value))
                            : this.value;
                        if (fld === 'acos_from' || fld === 'acos_to' || fld === 'label') amzAcosRefreshCounts();
                    };
                    inp.addEventListener('input', writeAcosBand);
                    inp.addEventListener('change', writeAcosBand);
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () { amzCurrentBands.splice(+this.dataset.removeIdx, 1); amzRenderBands(amzCurrentBands); });
                });
                amzAcosRefreshCounts();
            }
            function amzLoadBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzCurrentBands = bands.map(function (b) {
                    return { acos_from: Number(b.acos_from != null ? b.acos_from : 0), acos_to: Number(b.acos_to != null ? b.acos_to : 9999), sbgt: b.sbgt, label: b.label != null ? b.label : '', color: b.color || '#6c757d' };
                });
                amzRenderBands(amzCurrentBands);
            }
            var amzBgtServerLoadGen = 0;
            function amzBgtFetchRule(url, apply) {
                var gen = amzBgtServerLoadGen;
                fetch(url, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) {
                        if (gen !== amzBgtServerLoadGen) return;
                        if (apply) apply(body || {});
                    })
                    .catch(function () {});
            }
            function amzRefreshBgtRuleFromServer(cb) {
                amzBgtFetchRule(bgtRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtModalEl) {
                bgtModalEl.addEventListener('shown.bs.modal', function () {
                    amzBgtRefreshAllRuleCounts();
                    Object.keys(amzBgtColCharts).forEach(function (k) {
                        if (amzBgtColCharts[k]) { try { amzBgtColCharts[k].resize(); } catch (e) {} }
                    });
                });
                bgtModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var st = document.getElementById('amz-bgt-status');
                    if (st) st.textContent = '';
                });
                bgtModalEl.addEventListener('input', function (e) {
                    var field = e.target && e.target.dataset ? e.target.dataset.field : '';
                    if (field === 'bgt' || field === 'sbgt') amzBgtOnBudgetChange();
                });
                bgtModalEl.addEventListener('change', function (e) {
                    var field = e.target && e.target.dataset ? e.target.dataset.field : '';
                    if (field === 'bgt' || field === 'sbgt') amzBgtOnBudgetChange();
                });
            }
            var bgtAddBtn = document.getElementById('amazonAdsBgtRuleAddBandBtn');
            if (bgtAddBtn) {
                bgtAddBtn.addEventListener('click', function () {
                    var next = amzBgtNextAddedSlab(amzCurrentBands, 'acos_from', 'acos_to', 'sbgt');
                    var i = amzCurrentBands.length;
                    amzCurrentBands.push({ acos_from: next.from, acos_to: next.to, sbgt: next.amt, label: 'Band ' + (i + 1), color: AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length] });
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
                            sbgt: (b.sbgt === '' || b.sbgt == null) ? NaN : parseFloat(b.sbgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one band before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var b = cleaned[i];
                        if (!isFinite(b.acos_from) || !isFinite(b.acos_to) || !isFinite(b.sbgt)) { if (err) { err.textContent = 'Every band needs numeric From, To, and SBGT values.'; err.classList.remove('d-none'); } return; }
                        if (b.acos_from > b.acos_to) { if (err) { err.textContent = 'Each band needs From ≤ To.'; err.classList.remove('d-none'); } return; }
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
                            amzBgtNoteSaved('BGT Vs ACOS');
                            amzUpdatePushButtons();
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs VIEWS (View L7 → Bgt Views); dynamic slabs, Purple → Red, no autofill ----
            var AMZ_BGT_VIEWS_DEFAULTS = [
                { views_from: 351, views_to: 9999, bgt: 6, label: 'Purple', color: '#7c3aed' },
                { views_from: 281, views_to: 350, bgt: 5, label: 'Pink', color: '#e83e8c' },
                { views_from: 211, views_to: 280, bgt: 4, label: 'Green', color: '#28a745' },
                { views_from: 141, views_to: 210, bgt: 3, label: 'Blue', color: '#2563eb' },
                { views_from: 71, views_to: 140, bgt: 2, label: 'Yellow', color: '#ffc107' },
                { views_from: 0, views_to: 70, bgt: 1, label: 'Red', color: '#a00211' }
            ];
            var AMZ_BGT_VIEWS_LABELS = ['Purple', 'Pink', 'Green', 'Blue', 'Yellow', 'Red'];
            var AMZ_BGT_VIEWS_COLORS = ['#7c3aed', '#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'];
            var amzBgtViewsBands = [];
            function amzBgtViewsFlipLegacyRedFirst(bands) {
                if (!Array.isArray(bands) || bands.length !== 6) return bands;
                var labels = bands.map(function (b) { return String((b && b.label) || '').trim().toLowerCase(); });
                if (labels.join(',') !== 'red,yellow,blue,green,pink,purple') return bands;
                return bands.slice().reverse();
            }
            function amzBgtViewsNormalizeBands(existing) {
                var prev = amzBgtViewsFlipLegacyRedFirst(Array.isArray(existing) ? existing : []);
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.views_from);
                    var to = parseFloat(keep.views_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        views_from: isFinite(from) ? from : '',
                        views_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_VIEWS_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtViewsNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtViewsBands, 'views_from', 'views_to', 'bgt');
                var i = amzBgtViewsBands.length;
                return {
                    views_from: next.from,
                    views_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_VIEWS_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_VIEWS_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtViewsValueOfRow(row) {
                var n = parseFloat(row && (row.viewsL7 != null ? row.viewsL7 : row.page_cvr_sess7));
                return isFinite(n) ? n : 0;
            }
            function amzBgtViewsCounts(bands) {
                return amzBgtCountByBands(bands, 'views_from', 'views_to', amzBgtViewsValueOfRow);
            }
            function amzBgtViewsRefreshCounts() {
                var counts = amzBgtViewsCounts(amzBgtViewsBands);
                amzBgtRefreshCountCells('amazonAdsBgtViewsRuleBandsBody', counts);
                amzBgtPaintColumn('views', amzBgtViewsBands, counts);
            }
            function amzRenderBgtViewsBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtViewsRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                var counts = amzBgtViewsCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="1" class="form-control form-control-sm" value="' + (band.views_from != null ? band.views_from : '') + '" data-idx="' + i + '" data-field="views_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="1" class="form-control form-control-sm" value="' + (band.views_to != null ? band.views_to : '') + '" data-idx="' + i + '" data-field="views_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="0 is allowed. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtViewsBands[idx]) return;
                        if (fld === 'bgt') {
                            amzBgtViewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else if (fld === 'views_from' || fld === 'views_to') {
                            amzBgtViewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtViewsBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'views_from' || this.dataset.field === 'views_to' || this.dataset.field === 'label') amzBgtViewsRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'views_from' || this.dataset.field === 'views_to' || this.dataset.field === 'label') amzBgtViewsRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtViewsBands.length <= 1) return;
                        amzBgtViewsBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtViewsBands(amzBgtViewsBands);
                    });
                });
                amzBgtViewsRefreshCounts();
            }
            function amzLoadBgtViewsBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtViewsBands = amzBgtViewsNormalizeBands(bands);
                amzRenderBgtViewsBands(amzBgtViewsBands);
            }
            function amzRefreshBgtViewsRuleFromServer(cb) {
                amzBgtFetchRule(bgtViewsRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtViewsRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtViewsModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtViewsModalEl) {
                bgtViewsModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtViewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtViewsAddBtn = document.getElementById('amazonAdsBgtViewsRuleAddBandBtn');
            if (bgtViewsAddBtn) {
                bgtViewsAddBtn.addEventListener('click', function () {
                    amzBgtViewsBands.push(amzBgtViewsNewBand());
                    amzRenderBgtViewsBands(amzBgtViewsBands);
                });
            }
            var bgtViewsSaveBtn = document.getElementById('amazonAdsBgtViewsRuleSaveBtn');
            if (bgtViewsSaveBtn) {
                bgtViewsSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtViewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtViewsBands || []).map(function (b) {
                        return {
                            views_from: (b.views_from === '' || b.views_from == null) ? NaN : parseFloat(b.views_from),
                            views_to: (b.views_to === '' || b.views_to == null) ? NaN : parseFloat(b.views_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.views_from) || !isFinite(vb.views_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Views values.'; err.classList.remove('d-none'); } return; }
                        if (vb.views_from > vb.views_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
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
                            amzBgtNoteSaved('BGT Vs VIEWS');
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtViewsSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs CVR (CVR L30 → Bgt Cvr); dynamic slabs, Purple → Red, no autofill ----
            var AMZ_BGT_CVR_DEFAULTS = [
                { cvr_from: 20, cvr_to: 9999, bgt: 6, label: 'Purple', color: '#7c3aed' },
                { cvr_from: 16, cvr_to: 20, bgt: 5, label: 'Pink', color: '#e83e8c' },
                { cvr_from: 12, cvr_to: 16, bgt: 4, label: 'Green', color: '#28a745' },
                { cvr_from: 8, cvr_to: 12, bgt: 3, label: 'Blue', color: '#2563eb' },
                { cvr_from: 4, cvr_to: 8, bgt: 2, label: 'Yellow', color: '#ffc107' },
                { cvr_from: 0, cvr_to: 4, bgt: 1, label: 'Red', color: '#a00211' }
            ];
            var AMZ_BGT_CVR_LABELS = ['Purple', 'Pink', 'Green', 'Blue', 'Yellow', 'Red'];
            var AMZ_BGT_CVR_COLORS = ['#7c3aed', '#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211'];
            var amzBgtCvrBands = [];
            function amzBgtCvrFlipLegacyRedFirst(bands) {
                if (!Array.isArray(bands) || bands.length !== 6) return bands;
                var labels = bands.map(function (b) { return String((b && b.label) || '').trim().toLowerCase(); });
                if (labels.join(',') !== 'red,yellow,blue,green,pink,purple') return bands;
                return bands.slice().reverse();
            }
            function amzBgtCvrNormalizeBands(existing) {
                var prev = amzBgtCvrFlipLegacyRedFirst(Array.isArray(existing) ? existing : []);
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.cvr_from);
                    var to = parseFloat(keep.cvr_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        cvr_from: isFinite(from) ? from : '',
                        cvr_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_CVR_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtCvrNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtCvrBands, 'cvr_from', 'cvr_to', 'bgt');
                var i = amzBgtCvrBands.length;
                return {
                    cvr_from: next.from,
                    cvr_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_CVR_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_CVR_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtCvrValueOfRow(row) {
                var n = parseFloat(row && (row.bgt_cvr_page_cvr != null ? row.bgt_cvr_page_cvr : row.pageCvr));
                return isFinite(n) ? n : 0;
            }
            function amzBgtCvrCounts(bands) {
                return amzBgtCountByBands(bands, 'cvr_from', 'cvr_to', amzBgtCvrValueOfRow);
            }
            function amzBgtCvrRefreshCounts() {
                var counts = amzBgtCvrCounts(amzBgtCvrBands);
                amzBgtRefreshCountCells('amazonAdsBgtCvrRuleBandsBody', counts);
                amzBgtPaintColumn('cvr', amzBgtCvrBands, counts);
            }
            function amzRenderBgtCvrBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtCvrRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                var counts = amzBgtCvrCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.cvr_from != null ? band.cvr_from : '') + '" data-idx="' + i + '" data-field="cvr_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.cvr_to != null ? band.cvr_to : '') + '" data-idx="' + i + '" data-field="cvr_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="0 is allowed. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtCvrBands[idx]) return;
                        if (fld === 'bgt') {
                            amzBgtCvrBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else if (fld === 'cvr_from' || fld === 'cvr_to') {
                            amzBgtCvrBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtCvrBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'cvr_from' || this.dataset.field === 'cvr_to' || this.dataset.field === 'label') amzBgtCvrRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'cvr_from' || this.dataset.field === 'cvr_to' || this.dataset.field === 'label') amzBgtCvrRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtCvrBands.length <= 1) return;
                        amzBgtCvrBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtCvrBands(amzBgtCvrBands);
                    });
                });
                amzBgtCvrRefreshCounts();
            }
            function amzLoadBgtCvrBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtCvrBands = amzBgtCvrNormalizeBands(bands);
                amzRenderBgtCvrBands(amzBgtCvrBands);
            }
            function amzRefreshBgtCvrRuleFromServer(cb) {
                amzBgtFetchRule(bgtCvrRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtCvrRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtCvrModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtCvrModalEl) {
                bgtCvrModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtCvrRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtCvrAddBtn = document.getElementById('amazonAdsBgtCvrRuleAddBandBtn');
            if (bgtCvrAddBtn) {
                bgtCvrAddBtn.addEventListener('click', function () {
                    amzBgtCvrBands.push(amzBgtCvrNewBand());
                    amzRenderBgtCvrBands(amzBgtCvrBands);
                });
            }
            var bgtCvrSaveBtn = document.getElementById('amazonAdsBgtCvrRuleSaveBtn');
            if (bgtCvrSaveBtn) {
                bgtCvrSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtCvrRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtCvrBands || []).map(function (b) {
                        return {
                            cvr_from: (b.cvr_from === '' || b.cvr_from == null) ? NaN : parseFloat(b.cvr_from),
                            cvr_to: (b.cvr_to === '' || b.cvr_to == null) ? NaN : parseFloat(b.cvr_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.cvr_from) || !isFinite(vb.cvr_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Cvr values.'; err.classList.remove('d-none'); } return; }
                        if (vb.cvr_from > vb.cvr_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
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
                            amzBgtNoteSaved('BGT Vs CVR');
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtCvrSaveBtn.disabled = false; });
                });
            }

            // ---- BGT PRC (Price → Bgt Prc); dynamic slabs, no locked ranges ----
            var AMZ_BGT_PRC_DEFAULTS = [
                { prc_from: 151, prc_to: 9999, bgt: 5, label: 'Pink', color: '#e83e8c' },
                { prc_from: 101, prc_to: 150, bgt: 4, label: 'Green', color: '#28a745' },
                { prc_from: 61, prc_to: 100, bgt: 3, label: 'Blue', color: '#2563eb' },
                { prc_from: 41, prc_to: 60, bgt: 2, label: 'Yellow', color: '#ffc107' },
                { prc_from: 0, prc_to: 40, bgt: 1, label: 'Red', color: '#a00211' }
            ];
            var AMZ_BGT_PRC_LABELS = ['Pink', 'Green', 'Blue', 'Yellow', 'Red', 'Purple'];
            var AMZ_BGT_PRC_COLORS = ['#e83e8c', '#28a745', '#2563eb', '#ffc107', '#a00211', '#7c3aed'];
            var amzBgtPrcBands = [];
            function amzBgtPrcFlipLegacyRedFirst(bands) {
                if (!Array.isArray(bands) || bands.length !== 5) return bands;
                var labels = bands.map(function (b) { return String((b && b.label) || '').trim().toLowerCase(); });
                if (labels.join(',') !== 'red,yellow,blue,green,pink') return bands;
                return bands.slice().reverse();
            }
            function amzBgtPrcNormalizeBands(existing) {
                var prev = amzBgtPrcFlipLegacyRedFirst(Array.isArray(existing) ? existing : []);
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.prc_from);
                    var to = parseFloat(keep.prc_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        prc_from: isFinite(from) ? from : '',
                        prc_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_PRC_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtPrcNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtPrcBands, 'prc_from', 'prc_to', 'bgt');
                var i = amzBgtPrcBands.length;
                return {
                    prc_from: next.from,
                    prc_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_PRC_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_PRC_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtPrcValueOfRow(row) {
                var n = parseFloat(row && (row.bgt_prc_price != null ? row.bgt_prc_price : row.price));
                return isFinite(n) ? n : null;
            }
            function amzBgtPrcCounts(bands) {
                return amzBgtCountByBands(bands, 'prc_from', 'prc_to', amzBgtPrcValueOfRow);
            }
            function amzBgtPrcRefreshCounts() {
                var counts = amzBgtPrcCounts(amzBgtPrcBands);
                amzBgtRefreshCountCells('amazonAdsBgtPrcRuleBandsBody', counts);
                amzBgtPaintColumn('prc', amzBgtPrcBands, counts);
            }
            function amzRenderBgtPrcBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtPrcRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                var counts = amzBgtPrcCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.prc_from != null ? band.prc_from : '') + '" data-idx="' + i + '" data-field="prc_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.prc_to != null ? band.prc_to : '') + '" data-idx="' + i + '" data-field="prc_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="0 is allowed. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtPrcBands[idx]) return;
                        if (fld === 'bgt') amzBgtPrcBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        else if (fld === 'prc_from' || fld === 'prc_to') amzBgtPrcBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        else amzBgtPrcBands[idx][fld] = el.value;
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'prc_from' || this.dataset.field === 'prc_to' || this.dataset.field === 'label') amzBgtPrcRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'prc_from' || this.dataset.field === 'prc_to' || this.dataset.field === 'label') amzBgtPrcRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtPrcBands.length <= 1) return;
                        amzBgtPrcBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtPrcBands(amzBgtPrcBands);
                    });
                });
                amzBgtPrcRefreshCounts();
            }
            function amzLoadBgtPrcBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtPrcBands = amzBgtPrcNormalizeBands(bands);
                amzRenderBgtPrcBands(amzBgtPrcBands);
            }
            function amzRefreshBgtPrcRuleFromServer(cb) {
                amzBgtFetchRule(bgtPrcRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtPrcRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtPrcModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtPrcModalEl) {
                bgtPrcModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtPrcRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtPrcAddBtn = document.getElementById('amazonAdsBgtPrcRuleAddBandBtn');
            if (bgtPrcAddBtn) {
                bgtPrcAddBtn.addEventListener('click', function () {
                    amzBgtPrcBands.push(amzBgtPrcNewBand());
                    amzRenderBgtPrcBands(amzBgtPrcBands);
                });
            }
            var bgtPrcSaveBtn = document.getElementById('amazonAdsBgtPrcRuleSaveBtn');
            if (bgtPrcSaveBtn) {
                bgtPrcSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtPrcRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtPrcBands || []).map(function (b) {
                        return {
                            prc_from: (b.prc_from === '' || b.prc_from == null) ? NaN : parseFloat(b.prc_from),
                            prc_to: (b.prc_to === '' || b.prc_to == null) ? NaN : parseFloat(b.prc_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var pb = cleaned[i];
                        if (!isFinite(pb.prc_from) || !isFinite(pb.prc_to) || !isFinite(pb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Prc values.'; err.classList.remove('d-none'); } return; }
                        if (pb.prc_from > pb.prc_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
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
                            amzBgtNoteSaved('BGT PRC');
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
                    var bgt = parseFloat(keep.bgt);
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
                var rows = amzBgtCountRows();
                if (!rows) return null;
                var counts = (bands || []).map(function () { return 0; });
                var unmatched = 0;
                rows.forEach(function (row) {
                    var idx = amzBgtReviewsBandIndexForRating(amzBgtReviewsRatingOfRow(row), bands);
                    if (idx >= 0) counts[idx]++;
                    else unmatched++;
                });
                counts.unmatched = unmatched;
                counts.campaigns = rows.length;
                counts._live = true;
                return counts;
            }
            function amzBgtReviewsRefreshCounts() {
                var counts = amzBgtReviewsCounts(amzBgtReviewsBands);
                amzBgtRefreshCountCells('amazonAdsBgtReviewsRuleBandsBody', counts);
                amzBgtPaintColumn('reviews', amzBgtReviewsBands, counts);
            }
            function amzBgtReviewsNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtReviewsBands, 'rev_from', 'rev_to', 'bgt');
                var i = amzBgtReviewsBands.length;
                return {
                    rev_from: next.from,
                    rev_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_REVIEWS_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_REVIEWS_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
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
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.rev_from != null ? band.rev_from : '') + '" data-idx="' + i + '" data-field="rev_from" placeholder="2.99"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.rev_to != null ? band.rev_to : '') + '" data-idx="' + i + '" data-field="rev_to" placeholder="5"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="Negative values are allowed. Decimals allowed (e.g. 1.5 or -0.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtReviewsBands[idx]) return;
                        if (fld === 'bgt') amzBgtReviewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        else if (fld === 'rev_from' || fld === 'rev_to') amzBgtReviewsBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        else amzBgtReviewsBands[idx][fld] = el.value;
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'rev_from' || this.dataset.field === 'rev_to' || this.dataset.field === 'label') amzBgtReviewsRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'rev_from' || this.dataset.field === 'rev_to' || this.dataset.field === 'label') amzBgtReviewsRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtReviewsBands.length <= 1) return;
                        amzBgtReviewsBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtReviewsBands(amzBgtReviewsBands);
                    });
                });
                amzBgtReviewsRefreshCounts();
            }
            function amzLoadBgtReviewsBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtReviewsBands = amzBgtReviewsNormalizeBands(bands);
                amzRenderBgtReviewsBands(amzBgtReviewsBands);
            }
            function amzRefreshBgtReviewsRuleFromServer(cb) {
                amzBgtFetchRule(bgtReviewsRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtReviewsRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtReviewsModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtReviewsModalEl) {
                bgtReviewsModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtReviewsRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
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
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var ri = 0; ri < cleaned.length; ri++) {
                        var rb = cleaned[ri];
                        if (!isFinite(rb.rev_from) || !isFinite(rb.rev_to) || !isFinite(rb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Reviews values.'; err.classList.remove('d-none'); } return; }
                        if (rb.rev_from > rb.rev_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
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
                            amzBgtNoteSaved('BGT Vs REVIEWS');
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtReviewsSaveBtn.disabled = false; });
                });
            }

            // ---- BGT Vs Dil (Dil% → Bgt Dil); 3 default slabs, Pink → Green → Red ----
            var AMZ_BGT_DIL_DEFAULTS = [
                { dil_from: 50, dil_to: 9999, bgt: 3, label: 'Pink', color: '#e83e8c' },
                { dil_from: 25, dil_to: 50, bgt: 2, label: 'Green', color: '#28a745' },
                { dil_from: 0, dil_to: 25, bgt: 1, label: 'Red', color: '#a00211' }
            ];
            var AMZ_BGT_DIL_LABELS = ['Pink', 'Green', 'Red', 'Blue', 'Yellow', 'Purple'];
            var AMZ_BGT_DIL_COLORS = ['#e83e8c', '#28a745', '#a00211', '#2563eb', '#ffc107', '#7c3aed'];
            var amzBgtDilBands = [];
            function amzBgtDilNormalizeBands(existing) {
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.dil_from);
                    var to = parseFloat(keep.dil_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        dil_from: isFinite(from) ? from : '',
                        dil_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_DIL_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtDilNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtDilBands, 'dil_from', 'dil_to', 'bgt');
                var i = amzBgtDilBands.length;
                return {
                    dil_from: next.from,
                    dil_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_DIL_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_DIL_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtDilValueOfRow(row) {
                var n = parseFloat(row && (row.bgt_dil_value != null ? row.bgt_dil_value : row.dil));
                if (isFinite(n)) return n;
                var inv = parseFloat(row && row.Inv);
                var ovl30 = parseFloat(row && row.ovl30) || 0;
                if (isFinite(inv) && inv > 0) return (ovl30 / inv) * 100;
                if (isFinite(inv) && inv === 0) return 0;
                return null;
            }
            function amzBgtDilCounts(bands) {
                return amzBgtCountByBands(bands, 'dil_from', 'dil_to', amzBgtDilValueOfRow);
            }
            function amzBgtDilRefreshCounts() {
                var counts = amzBgtDilCounts(amzBgtDilBands);
                amzBgtRefreshCountCells('amazonAdsBgtDilRuleBandsBody', counts);
                amzBgtPaintColumn('dil', amzBgtDilBands, counts);
            }
            function amzRenderBgtDilBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtDilRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                var counts = amzBgtDilCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.dil_from != null ? band.dil_from : '') + '" data-idx="' + i + '" data-field="dil_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.dil_to != null ? band.dil_to : '') + '" data-idx="' + i + '" data-field="dil_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="0 is allowed. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtDilBands[idx]) return;
                        if (fld === 'bgt') {
                            amzBgtDilBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else if (fld === 'dil_from' || fld === 'dil_to') {
                            amzBgtDilBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtDilBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'dil_from' || this.dataset.field === 'dil_to' || this.dataset.field === 'label') amzBgtDilRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'dil_from' || this.dataset.field === 'dil_to' || this.dataset.field === 'label') amzBgtDilRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtDilBands.length <= 1) return;
                        amzBgtDilBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtDilBands(amzBgtDilBands);
                    });
                });
                amzBgtDilRefreshCounts();
            }
            function amzLoadBgtDilBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtDilBands = amzBgtDilNormalizeBands(bands);
                amzRenderBgtDilBands(amzBgtDilBands);
            }
            function amzRefreshBgtDilRuleFromServer(cb) {
                amzBgtFetchRule(bgtDilRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtDilRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtDilModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtDilModalEl) {
                bgtDilModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtDilRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtDilAddBtn = document.getElementById('amazonAdsBgtDilRuleAddBandBtn');
            if (bgtDilAddBtn) {
                bgtDilAddBtn.addEventListener('click', function () {
                    amzBgtDilBands.push(amzBgtDilNewBand());
                    amzRenderBgtDilBands(amzBgtDilBands);
                });
            }
            var bgtDilSaveBtn = document.getElementById('amazonAdsBgtDilRuleSaveBtn');
            if (bgtDilSaveBtn) {
                bgtDilSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtDilRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtDilBands || []).map(function (b) {
                        return {
                            dil_from: (b.dil_from === '' || b.dil_from == null) ? NaN : parseFloat(b.dil_from),
                            dil_to: (b.dil_to === '' || b.dil_to == null) ? NaN : parseFloat(b.dil_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.dil_from) || !isFinite(vb.dil_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt Dil values.'; err.classList.remove('d-none'); } return; }
                        if (vb.dil_from > vb.dil_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtDilSaveBtn.disabled = true;
                    fetch(bgtDilRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtDilRule = b.rule || window.amazonAdsBgtDilRule;
                            amzBgtNoteSaved('BGT Vs Dil');
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtDilSaveBtn.disabled = false; });
                });
            }

            // ---- Inv Rule (on-hand Inv → Bgt); dynamic slabs ----
            var AMZ_BGT_INV_DEFAULTS = [
                { inv_from: 50, inv_to: 9999, bgt: 0, label: 'Pink', color: '#e83e8c' },
                { inv_from: 10, inv_to: 50, bgt: 0, label: 'Green', color: '#28a745' },
                { inv_from: 0, inv_to: 10, bgt: 0, label: 'Red', color: '#a00211' }
            ];
            var AMZ_BGT_INV_LABELS = ['Pink', 'Green', 'Red', 'Yellow', 'Purple', 'Blue'];
            var AMZ_BGT_INV_COLORS = ['#e83e8c', '#28a745', '#a00211', '#ffc107', '#7c3aed', '#2563eb'];
            function amzBgtInvNormalizeBands(existing) {
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.inv_from);
                    var to = parseFloat(keep.inv_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        inv_from: isFinite(from) ? from : '',
                        inv_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_INV_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtInvNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtInvBands, 'inv_from', 'inv_to', 'bgt');
                var i = amzBgtInvBands.length;
                return {
                    inv_from: next.from,
                    inv_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_INV_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_INV_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtInvValueOfRow(row) {
                var raw = row && (row.bgt_inv_value != null && row.bgt_inv_value !== '' ? row.bgt_inv_value : row.Inv);
                var n = parseFloat(raw);
                return isFinite(n) ? n : null;
            }
            function amzBgtInvCounts(bands) {
                return amzBgtCountByBands(bands, 'inv_from', 'inv_to', amzBgtInvValueOfRow);
            }
            function amzBgtInvRefreshCounts() {
                var counts = amzBgtInvCounts(amzBgtInvBands);
                amzBgtRefreshCountCells('amazonAdsBgtInvRuleBandsBody', counts);
                amzBgtPaintColumn('inv', amzBgtInvBands, counts);
            }
            function amzRenderBgtInvBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtInvRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                var counts = amzBgtInvCounts(bands);
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="1" class="form-control form-control-sm" value="' + (band.inv_from != null ? band.inv_from : '') + '" data-idx="' + i + '" data-field="inv_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="1" class="form-control form-control-sm" value="' + (band.inv_to != null ? band.inv_to : '') + '" data-idx="' + i + '" data-field="inv_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="0 is allowed. Decimals allowed (e.g. 1.5) — the SBGT total is floored when pushed (4.5 → 4)."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtInvBands[idx]) return;
                        amzBgtInvBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        amzBgtInvRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        amzBgtInvRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtInvBands.length <= 1) return;
                        amzBgtInvBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtInvBands(amzBgtInvBands);
                    });
                });
                amzBgtInvRefreshCounts();
            }
            function amzLoadBgtInvBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtInvBands = amzBgtInvNormalizeBands(bands);
                amzRenderBgtInvBands(amzBgtInvBands);
            }
            function amzRefreshBgtInvRuleFromServer(cb) {
                amzBgtFetchRule(bgtInvRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtInvRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtInvModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtInvModalEl) {
                bgtInvModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtInvRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtInvAddBtn = document.getElementById('amazonAdsBgtInvRuleAddBandBtn');
            if (bgtInvAddBtn) {
                bgtInvAddBtn.addEventListener('click', function () {
                    amzBgtInvBands.push(amzBgtInvNewBand());
                    amzRenderBgtInvBands(amzBgtInvBands);
                });
            }

            // ---- Spend Rule (L30 cost → Bgt); dynamic slabs ----
            var AMZ_BGT_SPEND_DEFAULTS = [
                { spend_from: 50, spend_to: 9999, bgt: 3, label: 'Pink', color: '#e83e8c' },
                { spend_from: 10, spend_to: 50, bgt: 2, label: 'Green', color: '#28a745' },
                { spend_from: 0, spend_to: 10, bgt: 1, label: 'Blue', color: '#2563eb' }
            ];
            var AMZ_BGT_SPEND_LABELS = ['Pink', 'Green', 'Blue', 'Yellow', 'Purple', 'Red'];
            var AMZ_BGT_SPEND_COLORS = ['#e83e8c', '#28a745', '#2563eb', '#ffc107', '#7c3aed', '#a00211'];
            var amzBgtSpendBands = [];
            function amzBgtSpendNormalizeBands(existing) {
                var prev = Array.isArray(existing) ? existing : [];
                var out = [];
                prev.forEach(function (keep) {
                    if (!keep || typeof keep !== 'object') return;
                    var from = parseFloat(keep.spend_from);
                    var to = parseFloat(keep.spend_to);
                    var bgt = parseFloat(keep.bgt);
                    out.push({
                        spend_from: isFinite(from) ? from : '',
                        spend_to: isFinite(to) ? to : '',
                        bgt: isFinite(bgt) ? bgt : '',
                        label: keep.label != null ? String(keep.label) : '',
                        color: keep.color || '#6c757d'
                    });
                });
                return out.length ? out : AMZ_BGT_SPEND_DEFAULTS.map(function (d) { return Object.assign({}, d); });
            }
            function amzBgtSpendNewBand() {
                var next = amzBgtNextAddedSlab(amzBgtSpendBands, 'spend_from', 'spend_to', 'bgt');
                var i = amzBgtSpendBands.length;
                return {
                    spend_from: next.from,
                    spend_to: next.to,
                    bgt: next.amt,
                    label: AMZ_BGT_SPEND_LABELS[i] || ('Slab ' + (i + 1)),
                    color: AMZ_BGT_SPEND_COLORS[i] || AMZ_BGT_COL_COLORS[i % AMZ_BGT_COL_COLORS.length]
                };
            }
            function amzBgtSpendValueOfRow(row) {
                var raw = row && (row.cost != null && row.cost !== '' ? row.cost : row.spend);
                var n = parseFloat(raw);
                return isFinite(n) ? n : null;
            }
            function amzBgtSpendCounts(bands) {
                return amzBgtCountByBands(bands, 'spend_from', 'spend_to', amzBgtSpendValueOfRow);
            }
            function amzBgtSpendRefreshCounts() {
                var counts = amzBgtSpendCounts(amzBgtSpendBands);
                amzBgtRefreshCountCells('amazonAdsBgtSpendRuleBandsBody', counts);
                amzBgtPaintColumn('spend', amzBgtSpendBands, counts);
            }
            function amzRenderBgtSpendBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtSpendRuleBandsBody');
                if (!tbody) return;
                var canDelete = bands.length > 1;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.spend_from != null ? band.spend_from : '') + '" data-idx="' + i + '" data-field="spend_from" placeholder="0"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.spend_to != null ? band.spend_to : '') + '" data-idx="' + i + '" data-field="spend_to" placeholder="9999"></td>'
                        + '<td><input type="text" inputmode="decimal" step="0.01" class="form-control form-control-sm" value="' + (band.bgt != null ? band.bgt : '') + '" data-idx="' + i + '" data-field="bgt" title="Negative values are allowed. The chart counts campaigns by L30 spend."></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Delete slab"' + (canDelete ? '' : ' disabled') + '><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    var writeBand = function (el) {
                        var idx = +el.dataset.idx, fld = el.dataset.field;
                        if (!amzBgtSpendBands[idx]) return;
                        if (fld === 'bgt' || fld === 'spend_from' || fld === 'spend_to') {
                            amzBgtSpendBands[idx][fld] = (el.value === '' ? '' : parseFloat(el.value));
                        } else {
                            amzBgtSpendBands[idx][fld] = el.value;
                        }
                    };
                    inp.addEventListener('input', function () {
                        writeBand(this);
                        if (this.dataset.field === 'spend_from' || this.dataset.field === 'spend_to') amzBgtSpendRefreshCounts();
                    });
                    inp.addEventListener('change', function () {
                        writeBand(this);
                        if (this.dataset.field === 'spend_from' || this.dataset.field === 'spend_to') amzBgtSpendRefreshCounts();
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (amzBgtSpendBands.length <= 1) return;
                        amzBgtSpendBands.splice(+this.dataset.removeIdx, 1);
                        amzRenderBgtSpendBands(amzBgtSpendBands);
                    });
                });
                amzBgtSpendRefreshCounts();
            }
            function amzLoadBgtSpendBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzBgtSpendBands = amzBgtSpendNormalizeBands(bands);
                amzRenderBgtSpendBands(amzBgtSpendBands);
            }
            function amzRefreshBgtSpendRuleFromServer(cb) {
                amzBgtFetchRule(bgtSpendRuleGetUrl, function (body) {
                    if (body && body.rule) window.amazonAdsBgtSpendRule = body.rule;
                    if (cb) cb();
                });
            }
            var bgtSpendModalEl = document.getElementById('amazonAdsBgtRulesModal');
            if (bgtSpendModalEl) {
                bgtSpendModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtSpendRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                });
            }
            var bgtSpendAddBtn = document.getElementById('amazonAdsBgtSpendRuleAddBandBtn');
            if (bgtSpendAddBtn) {
                bgtSpendAddBtn.addEventListener('click', function () {
                    amzBgtSpendBands.push(amzBgtSpendNewBand());
                    amzRenderBgtSpendBands(amzBgtSpendBands);
                });
            }
            var bgtSpendSaveBtn = document.getElementById('amazonAdsBgtSpendRuleSaveBtn');
            if (bgtSpendSaveBtn) {
                bgtSpendSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtSpendRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzBgtSpendBands || []).map(function (b) {
                        return {
                            spend_from: (b.spend_from === '' || b.spend_from == null) ? NaN : parseFloat(b.spend_from),
                            spend_to: (b.spend_to === '' || b.spend_to == null) ? NaN : parseFloat(b.spend_to),
                            bgt: (b.bgt === '' || b.bgt == null) ? NaN : parseFloat(b.bgt),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one slab before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var vb = cleaned[i];
                        if (!isFinite(vb.spend_from) || !isFinite(vb.spend_to) || !isFinite(vb.bgt)) { if (err) { err.textContent = 'Every slab needs numeric From, To, and Bgt values.'; err.classList.remove('d-none'); } return; }
                        if (vb.spend_from > vb.spend_to) { if (err) { err.textContent = 'Each slab needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtSpendSaveBtn.disabled = true;
                    fetch(bgtSpendRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtSpendRule = b.rule || window.amazonAdsBgtSpendRule;
                            amzBgtNoteSaved('Spend Rule');
                        })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtSpendSaveBtn.disabled = false; });
                });
            }

            var AMZ_AUTO_PUSH_PULL_KEY = 'amzAutoPushPull';
            function amzAutoPushPullOn() {
                try { return localStorage.getItem(AMZ_AUTO_PUSH_PULL_KEY) === '1'; } catch (e) { return false; }
            }
            function amzPaintAutoPushPullBtn() {
                var btn = document.getElementById('amazonAdsAutoPushPullBtn');
                if (!btn) return;
                var on = amzAutoPushPullOn();
                btn.classList.toggle('is-on', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.textContent = 'Auto Push & Pull: ' + (on ? 'ON' : 'OFF');
            }
            function amzBgtSyncBandsFromDom(tbodyId, bands) {
                var tbody = document.getElementById(tbodyId);
                if (!tbody || !Array.isArray(bands)) return;
                tbody.querySelectorAll('input[data-idx][data-field]').forEach(function (el) {
                    var idx = +el.dataset.idx;
                    var fld = el.dataset.field;
                    if (!bands[idx]) return;
                    bands[idx][fld] = el.value === '' ? '' : parseFloat(el.value);
                });
            }
            function amzBgtCleanRuleBands(bands, fromKey, toKey, amtKey) {
                return (bands || []).map(function (b) {
                    var row = { label: (b.label || '').toString(), color: (b.color || '#6c757d').toString() };
                    [fromKey, toKey, amtKey].forEach(function (k) {
                        row[k] = (b[k] === '' || b[k] == null) ? NaN : parseFloat(b[k]);
                    });
                    return row;
                });
            }
            function amzBgtValidateRuleBands(cleaned, fromKey, toKey, amtKey, label) {
                if (!cleaned.length) return label + ': add at least one slab before saving.';
                for (var i = 0; i < cleaned.length; i++) {
                    var row = cleaned[i];
                    if (!isFinite(row[fromKey]) || !isFinite(row[toKey]) || !isFinite(row[amtKey])) {
                        return label + ': every slab needs numeric From, To, and budget.';
                    }
                }
                return '';
            }
            function amzBgtPostRule(url, bands) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ bands: bands })
                }).then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body || {} }; }); });
            }
            var amzBgtPendingSave = null;
            function amzBgtBandsFromInputs(spec) {
                var copy = (spec.bands || []).map(function (b) { return Object.assign({}, b); });
                var tbody = document.getElementById(spec.body);
                if (!tbody) return copy;
                tbody.querySelectorAll('input[data-idx][data-field]').forEach(function (el) {
                    var idx = +el.dataset.idx;
                    var fld = el.dataset.field;
                    if (!copy[idx]) return;
                    var raw = String(el.value == null ? '' : el.value).replace(/,/g, '').trim();
                    copy[idx][fld] = raw === '' ? '' : parseFloat(raw);
                });
                return copy;
            }
            function amzBgtCollectSaveJobs() {
                var specs = [
                    { label: 'BGT Vs ACOS', url: bgtRuleSaveUrl, body: 'amazonAdsBgtRuleBandsBody', bands: amzCurrentBands, from: 'acos_from', to: 'acos_to', amt: 'sbgt', store: 'amazonAdsBgtRule', load: amzLoadBandsFromRule },
                    { label: 'BGT Vs VIEWS', url: bgtViewsRuleSaveUrl, body: 'amazonAdsBgtViewsRuleBandsBody', bands: amzBgtViewsBands, from: 'views_from', to: 'views_to', amt: 'bgt', store: 'amazonAdsBgtViewsRule', load: amzLoadBgtViewsBandsFromRule },
                    { label: 'BGT Vs CVR', url: bgtCvrRuleSaveUrl, body: 'amazonAdsBgtCvrRuleBandsBody', bands: amzBgtCvrBands, from: 'cvr_from', to: 'cvr_to', amt: 'bgt', store: 'amazonAdsBgtCvrRule', load: amzLoadBgtCvrBandsFromRule },
                    { label: 'BGT PRC', url: bgtPrcRuleSaveUrl, body: 'amazonAdsBgtPrcRuleBandsBody', bands: amzBgtPrcBands, from: 'prc_from', to: 'prc_to', amt: 'bgt', store: 'amazonAdsBgtPrcRule', load: amzLoadBgtPrcBandsFromRule },
                    { label: 'BGT Vs REVIEWS', url: bgtReviewsRuleSaveUrl, body: 'amazonAdsBgtReviewsRuleBandsBody', bands: amzBgtReviewsBands, from: 'rev_from', to: 'rev_to', amt: 'bgt', store: 'amazonAdsBgtReviewsRule', load: amzLoadBgtReviewsBandsFromRule },
                    { label: 'BGT Vs Dil', url: bgtDilRuleSaveUrl, body: 'amazonAdsBgtDilRuleBandsBody', bands: amzBgtDilBands, from: 'dil_from', to: 'dil_to', amt: 'bgt', store: 'amazonAdsBgtDilRule', load: amzLoadBgtDilBandsFromRule },
                    { label: 'Inv Rule', url: bgtInvRuleSaveUrl, body: 'amazonAdsBgtInvRuleBandsBody', bands: amzBgtInvBands, from: 'inv_from', to: 'inv_to', amt: 'bgt', store: 'amazonAdsBgtInvRule', load: amzLoadBgtInvBandsFromRule },
                    { label: 'Spend Rule', url: bgtSpendRuleSaveUrl, body: 'amazonAdsBgtSpendRuleBandsBody', bands: amzBgtSpendBands, from: 'spend_from', to: 'spend_to', amt: 'bgt', store: 'amazonAdsBgtSpendRule', load: amzLoadBgtSpendBandsFromRule }
                ];
                var jobs = [];
                var errors = [];
                for (var i = 0; i < specs.length; i++) {
                    var spec = specs[i];
                    try {
                        var cleaned = amzBgtCleanRuleBands(amzBgtBandsFromInputs(spec), spec.from, spec.to, spec.amt);
                        var problem = amzBgtValidateRuleBands(cleaned, spec.from, spec.to, spec.amt, spec.label);
                        if (problem) { errors.push(problem); continue; }
                        jobs.push({ spec: spec, bands: cleaned });
                    } catch (err) {
                        errors.push(spec.label + ': ' + ((err && err.message) ? err.message : 'could not read slabs'));
                    }
                }
                return { jobs: jobs, error: errors.join('\n') };
            }
            function amzBgtSaveAndApply(ev) {
                if (ev && ev.preventDefault) ev.preventDefault();
                var btn = document.getElementById('amazonAdsBgtSaveApplyBtn');
                var st = document.getElementById('amz-bgt-status');
                var jobs = [];
                try {
                    amzBgtServerLoadGen++;
                    var collected = amzBgtCollectSaveJobs();
                    amzBgtPendingSave = null;
                    jobs = collected.jobs || [];
                    if (!jobs.length) {
                        var msg = collected.error || 'Nothing to save.';
                        if (st) { st.textContent = msg; st.className = 'small text-danger fw-semibold me-auto'; }
                        window.alert(msg);
                        return;
                    }
                    if (collected.error && st) {
                        st.textContent = collected.error;
                        st.className = 'small text-danger fw-semibold me-auto';
                    }
                } catch (err) {
                    window.alert((err && err.message) ? err.message : 'Save failed.');
                    return;
                }
                if (btn) btn.disabled = true;
                if (st) st.dataset.phase = 'save';
                amzBgtWriteStatus('Saving rules…', true);
                Promise.all(jobs.map(function (job) { return amzBgtPostRule(job.spec.url, job.bands); }))
                    .then(function (outs) {
                        for (var n = 0; n < outs.length; n++) {
                            var out = outs[n] || {};
                            var body = out.body || {};
                            if (!out.ok || body.status === 422 || body.status === 500) {
                                throw new Error(jobs[n].spec.label + ': ' + (body.message || body.error || 'Save failed.'));
                            }
                            var saved = (body.rule && Array.isArray(body.rule.bands) && body.rule.bands.length)
                                ? body.rule
                                : { bands: jobs[n].bands };
                            window[jobs[n].spec.store] = saved;
                            if (typeof jobs[n].spec.load === 'function') jobs[n].spec.load(saved);
                        }
                        if (typeof amzFillAcosFilterOptions === 'function') amzFillAcosFilterOptions();
                        if (typeof amzUpdatePushButtons === 'function') amzUpdatePushButtons();
                        amzSbgtAutoPushedKey = {};
                        if (st) st.dataset.phase = 'apply';
                        amzBgtWriteStatus('Applying to the page…', true);
                        if (typeof amzBgtRefreshAllRuleCounts === 'function') amzBgtRefreshAllRuleCounts();
                        if (typeof amzBgtRequestRecount === 'function') amzBgtRequestRecount();
                        var reloaded = table ? Promise.resolve(table.setData()).catch(function () { return null; }) : Promise.resolve();
                        return reloaded;
                    })
                    .then(function () {
                        amzRefreshUiSoon();
                        if (st) delete st.dataset.phase;
                        amzBgtWriteStatus(amzAutoPushPullOn()
                            ? 'Saved and applied. Auto Push & Pull is on.'
                            : 'Saved and applied.', false);
                    })
                    .catch(function (err) {
                        if (st) { delete st.dataset.phase; st.className = 'small text-danger fw-semibold me-auto'; st.textContent = (err && err.message) ? err.message : 'Network or server error.'; }
                    })
                    .finally(function () { if (btn) btn.disabled = false; });
            }
            window.amzBgtSaveAndApply = amzBgtSaveAndApply;
            var bgtSaveApplyBtn = document.getElementById('amazonAdsBgtSaveApplyBtn');
            if (bgtSaveApplyBtn) bgtSaveApplyBtn.addEventListener('click', amzBgtSaveAndApply);
            amzLoadBandsFromRule(window.amazonAdsBgtRule || {});
            amzLoadBgtViewsBandsFromRule(window.amazonAdsBgtViewsRule || {});
            amzLoadBgtCvrBandsFromRule(window.amazonAdsBgtCvrRule || {});
            amzLoadBgtPrcBandsFromRule(window.amazonAdsBgtPrcRule || {});
            amzLoadBgtReviewsBandsFromRule(window.amazonAdsBgtReviewsRule || {});
            amzLoadBgtDilBandsFromRule(window.amazonAdsBgtDilRule || {});
            amzLoadBgtInvBandsFromRule(window.amazonAdsBgtInvRule || {});
            amzLoadBgtSpendBandsFromRule(window.amazonAdsBgtSpendRule || {});
            amzBgtPollStoredCounts();
            var autoPushPullBtn = document.getElementById('amazonAdsAutoPushPullBtn');
            if (autoPushPullBtn) {
                amzPaintAutoPushPullBtn();
                autoPushPullBtn.addEventListener('click', function () {
                    var next = !amzAutoPushPullOn();
                    try { localStorage.setItem(AMZ_AUTO_PUSH_PULL_KEY, next ? '1' : '0'); } catch (e) {}
                    amzPaintAutoPushPullBtn();
                    var st = document.getElementById('amz-bgt-status');
                    if (!next) {
                        if (st) st.textContent = 'Auto Push & Pull is off.';
                        return;
                    }
                    if (st) st.textContent = 'Auto Push & Pull is on. Pulling live Amazon values and pushing mismatches…';
                    amzSbgtAutoPushedKey = {};
                    amzAutoPushChangedSbgt();
                });
            }

            (function () {
                var minKey = 'amzBgtPanelMin';
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
                function resizeBgtCharts() {
                    Object.keys(amzBgtColCharts).forEach(function (k) {
                        if (amzBgtColCharts[k]) { try { amzBgtColCharts[k].resize(); } catch (e) {} }
                    });
                }
                function paintMin() {
                    var min = minSet();
                    var cols = document.querySelector('#amazonAdsBgtRulesModal .amz-bgt-cols');
                    if (cols) cols.classList.toggle('is-expanded', !!maxKey);
                    document.querySelectorAll('#amazonAdsBgtRulesModal [data-amz-bgt-panel]').forEach(function (el) {
                        var key = el.getAttribute('data-amz-bgt-panel');
                        var on = min.has(key);
                        var expanded = key === maxKey;
                        el.classList.toggle('is-min', on && !expanded);
                        el.classList.toggle('is-max', expanded);
                        var title = ((el.querySelector('.amz-bgt-title') || {}).textContent || 'rule').trim();
                        var btn = el.querySelector('.amz-bgt-min-btn');
                        if (btn) {
                            btn.textContent = (on && !expanded) ? '+' : '−';
                            btn.title = ((on && !expanded) ? 'Maximize ' : 'Minimize ') + title;
                            btn.setAttribute('aria-label', btn.title);
                        }
                        var exp = el.querySelector('.amz-bgt-exp-btn');
                        if (exp) {
                            exp.textContent = expanded ? '⤡' : '⤢';
                            exp.title = (expanded ? 'Restore ' : 'Expand ') + title;
                            exp.setAttribute('aria-label', exp.title);
                        }
                    });
                    resizeBgtCharts();
                }
                document.addEventListener('click', function (e) {
                    var expBtn = e.target && e.target.closest ? e.target.closest('#amazonAdsBgtRulesModal .amz-bgt-exp-btn') : null;
                    if (expBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var col = expBtn.closest('[data-amz-bgt-panel]');
                        if (!col) return;
                        var key = col.getAttribute('data-amz-bgt-panel');
                        var min = minSet();
                        if (maxKey === key) {
                            maxKey = null;
                        } else {
                            maxKey = key;
                            min.delete(key);
                            saveMin(min);
                        }
                        paintMin();
                        setTimeout(resizeBgtCharts, 60);
                        return;
                    }
                    var btn = e.target && e.target.closest ? e.target.closest('#amazonAdsBgtRulesModal .amz-bgt-min-btn') : null;
                    if (btn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var col = btn.closest('[data-amz-bgt-panel]');
                        if (!col) return;
                        var min = minSet();
                        var key = col.getAttribute('data-amz-bgt-panel');
                        if (maxKey === key) maxKey = null;
                        if (min.has(key)) min.delete(key); else min.add(key);
                        saveMin(min);
                        paintMin();
                        return;
                    }
                    var collapsed = e.target && e.target.closest ? e.target.closest('#amazonAdsBgtRulesModal .amz-bgt-col.is-min') : null;
                    if (!collapsed) return;
                    var min = minSet();
                    min.delete(collapsed.getAttribute('data-amz-bgt-panel'));
                    saveMin(min);
                    paintMin();
                });
                var rulesModal = document.getElementById('amazonAdsBgtRulesModal');
                if (rulesModal) {
                    rulesModal.addEventListener('shown.bs.modal', function () {
                        paintMin();
                        setTimeout(resizeBgtCharts, 60);
                    });
                    rulesModal.addEventListener('hidden.bs.modal', function () {
                        maxKey = null;
                        paintMin();
                    });
                }
                paintMin();
            })();

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
                amzSbidPaint();
            }
            var amzSbidCharts = {};
            function amzSbidPos(row, key) {
                var n = parseFloat(row && row[key]);
                return isFinite(n) && n > 0 ? n : 0;
            }
            function amzSbidGroups() {
                var low = parseFloat((document.getElementById('amazonAdsSbidRuleUtilLow') || {}).value);
                var high = parseFloat((document.getElementById('amazonAdsSbidRuleUtilHigh') || {}).value);
                var below = { l1: 0, l2: 0, l7: 0, avg: 0, fb: 0 };
                var above = { l1: 0, none: 0 };
                var mid = 0;
                var sbidRows = amzBgtCountRows();
                if (!sbidRows) return { below: below, above: above, mid: mid };
                sbidRows.forEach(function (row) {
                    var u7 = parseFloat(row && row['U7%']);
                    var u1 = parseFloat(row && row['U1%']);
                    if (!isFinite(u7) || !isFinite(u1) || !isFinite(low) || !isFinite(high)) { mid++; return; }
                    var l1 = amzSbidPos(row, 'costPerClick');
                    var l2 = amzSbidPos(row, 'CPC2');
                    var l7 = amzSbidPos(row, 'CPC3');
                    var avg = amzSbidPos(row, 'CPCAvg');
                    if (u7 < low && u1 < low) {
                        if (l1 > 0) below.l1++;
                        else if (l2 > 0) below.l2++;
                        else if (l7 > 0) below.l7++;
                        else if (avg > 0) below.avg++;
                        else below.fb++;
                    } else if (u7 > high && u1 > high) {
                        if (l1 > 0) above.l1++;
                        else above.none++;
                    } else {
                        mid++;
                    }
                });
                return { below: below, above: above, mid: mid };
            }
            function amzSbidPaintColumn(key, rows) {
                var legend = document.getElementById('amz-sbid-leg-' + key);
                var canvas = document.getElementById('amz-sbid-chart-' + key);
                var total = rows.reduce(function (s, r) { return s + r.n; }, 0);
                if (legend) {
                    var head = '<div class="amz-sbid-leg-head"><span></span><span></span><span>Count</span><span>%</span></div>';
                    legend.innerHTML = head + rows.map(function (r) {
                        var pct = total > 0 ? Math.round((r.n / total) * 100) : 0;
                        return '<div class="amz-sbid-leg-row"><span class="amz-sbid-swatch" style="background:' + r.color + '"></span><span>' + amzEsc(r.label) + '</span><strong>' + r.n + '</strong><span class="amz-sbid-leg-pct">' + pct + '%</span></div>';
                    }).join('');
                }
                if (!canvas || typeof Chart === 'undefined') return;
                if (amzSbidCharts[key]) { try { amzSbidCharts[key].destroy(); } catch (e) {} amzSbidCharts[key] = null; }
                amzSbidCharts[key] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(function (r) { return r.label; }),
                        datasets: [{
                            data: rows.map(function (r) { return r.n; }),
                            backgroundColor: rows.map(function (r) { return r.color; }),
                            borderWidth: 0,
                            borderRadius: 4,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { display: false, grid: { display: false } },
                            y: { display: false, beginAtZero: true, grid: { display: false } }
                        }
                    }
                });
            }
            function amzSbidPaint() {
                var g = amzSbidGroups();
                amzSbidPaintColumn('below', [
                    { label: '× L1', n: g.below.l1, color: '#7c3aed' },
                    { label: '× L2', n: g.below.l2, color: '#2563eb' },
                    { label: '× L7', n: g.below.l7, color: '#16a34a' },
                    { label: 'Avg + 0.10', n: g.below.avg, color: '#f59e0b' },
                    { label: 'Fallback', n: g.below.fb, color: '#94a3b8' }
                ]);
                amzSbidPaintColumn('mid', [
                    { label: 'Between', n: g.mid, color: '#64748b' }
                ]);
                amzSbidPaintColumn('above', [
                    { label: '× L1', n: g.above.l1, color: '#16a34a' },
                    { label: 'No L1', n: g.above.none, color: '#dc2626' }
                ]);
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
                sbidModalEl.addEventListener('shown.bs.modal', function () {
                    amzBgtEnsureUniverse().then(function () { amzSbidPaint(); });
                    amzSbidPaint();
                    setTimeout(function () {
                        Object.keys(amzSbidCharts).forEach(function (k) {
                            if (amzSbidCharts[k]) { try { amzSbidCharts[k].resize(); } catch (e) {} }
                        });
                    }, 60);
                });
            }
            ['amazonAdsSbidRuleUtilLow', 'amazonAdsSbidRuleUtilHigh'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', amzSbidPaint);
            });
            (function () {
                var minKey = 'amzSbidPanelMin';
                function minSet() {
                    try {
                        var raw = JSON.parse(localStorage.getItem(minKey) || '[]');
                        return new Set(Array.isArray(raw) ? raw : []);
                    } catch (e) { return new Set(); }
                }
                function saveMin(set) {
                    try { localStorage.setItem(minKey, JSON.stringify(Array.from(set))); } catch (e) {}
                }
                function paintMin() {
                    var min = minSet();
                    document.querySelectorAll('#amazonAdsSbidRuleModal [data-amz-sbid-panel]').forEach(function (el) {
                        var on = min.has(el.getAttribute('data-amz-sbid-panel'));
                        el.classList.toggle('is-min', on);
                        var btn = el.querySelector('.amz-sbid-min-btn');
                        if (!btn) return;
                        var title = (el.querySelector('.amz-sbid-title') || {}).textContent || 'rule';
                        btn.textContent = on ? '+' : '−';
                        btn.title = (on ? 'Maximize ' : 'Minimize ') + title.trim();
                        btn.setAttribute('aria-label', btn.title);
                    });
                    Object.keys(amzSbidCharts).forEach(function (k) {
                        if (amzSbidCharts[k]) { try { amzSbidCharts[k].resize(); } catch (e) {} }
                    });
                }
                document.addEventListener('click', function (e) {
                    var btn = e.target && e.target.closest ? e.target.closest('#amazonAdsSbidRuleModal .amz-sbid-min-btn') : null;
                    if (btn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var col = btn.closest('[data-amz-sbid-panel]');
                        if (!col) return;
                        var min = minSet();
                        var key = col.getAttribute('data-amz-sbid-panel');
                        if (min.has(key)) min.delete(key); else min.add(key);
                        saveMin(min);
                        paintMin();
                        return;
                    }
                    var collapsed = e.target && e.target.closest ? e.target.closest('#amazonAdsSbidRuleModal .amz-sbid-col.is-min') : null;
                    if (!collapsed) return;
                    var min = minSet();
                    min.delete(collapsed.getAttribute('data-amz-sbid-panel'));
                    saveMin(min);
                    paintMin();
                });
                paintMin();
            })();
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
                var dil = Number(pr.dil_above);
                return {
                    enabled: !!pr.enabled,
                    dil_above: isFinite(dil) ? dil : 100,
                    dil_enabled: pr.dil_enabled !== false
                };
            }
            function amzRefreshPrBtn() {
                var btn = document.getElementById('amazonAdsPrRuleBtn');
                if (!btn) return;
                var pr = amzPrFromRule(window.amazonAdsPauseRule);
                btn.textContent = 'Pause Rule';
                var on = pr.enabled && pr.dil_enabled;
                btn.classList.toggle('btn-danger', on);
                btn.classList.toggle('text-white', on);
                btn.classList.toggle('btn-outline-danger', !on);
                btn.title = on
                    ? ('Auto-pause campaigns when Dil% ≥ ' + pr.dil_above + '%. Only PARENT campaigns turn back on.')
                    : 'Dil% pause rule — click to set the threshold';
            }
            function amzFillPrModal() {
                var pr = amzPrFromRule(window.amazonAdsPauseRule);
                var dilInput = document.getElementById('amazonAdsPrDilAbove');
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                if (dilInput) dilInput.value = String(pr.dil_above);
                if (dilEn) dilEn.checked = pr.dil_enabled;
                if (en) en.checked = pr.enabled;
            }
            function amzSavePrRule(apply) {
                var err = document.getElementById('amazonAdsPrRuleModalError');
                var ok = document.getElementById('amazonAdsPrRuleModalOk');
                if (err) { err.classList.add('d-none'); err.textContent = ''; }
                if (ok) { ok.classList.add('d-none'); ok.textContent = ''; }
                var dilInput = document.getElementById('amazonAdsPrDilAbove');
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                var dil = dilInput ? parseFloat(String(dilInput.value).trim()) : NaN;
                if (!isFinite(dil) || dil < 0) {
                    if (err) { err.textContent = 'Enter a Dil% threshold (0 or higher).'; err.classList.remove('d-none'); }
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
                        price_enabled: false,
                        reviews_enabled: false,
                        apply: !!apply || !!(en && en.checked)
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
                            var pausedNames = Array.isArray(b.apply.paused_names) ? b.apply.paused_names.filter(Boolean) : [];
                            if (pausedNames.length) {
                                msg += ' Stat will show P (Paused): ' + pausedNames.slice(0, 12).join(', ')
                                    + (pausedNames.length > 12 ? ' …' : '') + '.';
                            }
                            var prErrs = Array.isArray(b.apply.errors) ? b.apply.errors.filter(Boolean) : [];
                            if (prErrs.length && err) {
                                err.textContent = prErrs.slice(0, 8).join(' | ');
                                err.classList.remove('d-none');
                            }
                            if ((b.apply.paused || 0) > 0) amzEnsureStatIncludesPaused();
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
                var dilEn = document.getElementById('amazonAdsPrDilEnabled');
                var en = document.getElementById('amazonAdsPrEnabled');
                var on = !!(en && en.checked);
                var dilOn = !!(dilEn && dilEn.checked);
                var msg = on && dilOn
                    ? ('Save Pause Rule and pause matching PARENT and child SKU campaigns when Dil% ≥ ' + (dilInput ? dilInput.value : '100') + '% on Amazon now? Only PARENT campaigns will turn back on later.')
                    : 'Save Pause Rule with Dil% auto-pause off? Matching campaigns will not be auto-paused by this rule.';
                if (!window.confirm(msg)) return;
                amzSavePrRule(true);
            });
            amzRefreshPrBtn();

            const amzTaskStoreUrl = "{{ route('tasks.store') }}";
            const amzAssignorId = {{ (int) (Auth::id() ?? 0) }};
            let amzTaskUsers = [];

            function amzTaskGroupLabel() {
                if (activeRawSourceKey === 'sb_reports') return 'Amazon · HL';
                var search = amzSearchQueryVal().toUpperCase();
                if (search === 'KW') return 'Amazon · KW';
                if (search === 'PT') return 'Amazon · PT';
                return 'Amazon';
            }

            function amzTaskPageLink() {
                return window.location.origin + window.location.pathname + (window.location.search || '');
            }

            function amzShowTaskModal() {
                const modalEl = document.getElementById('amzTaskModal');
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

            function amzHideTaskModal() {
                const modalEl = document.getElementById('amzTaskModal');
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

            function amzFillTaskAssignees(users) {
                amzTaskUsers = Array.isArray(users) ? users : [];
                const list = document.getElementById('amz-task-assignee-list');
                if (!list) return;
                list.innerHTML = amzTaskUsers.map(function (u) {
                    const name = String(u.name || '').trim();
                    return name ? '<option value="' + amzEsc(name) + '"></option>' : '';
                }).join('');
            }

            function amzLoadTaskUsers(cb) {
                if (amzTaskUsers.length) {
                    cb(amzTaskUsers);
                    return;
                }
                const dataEl = document.getElementById('quick-assignee-users-data');
                if (dataEl) {
                    try {
                        const parsed = JSON.parse(dataEl.textContent || '[]');
                        if (Array.isArray(parsed) && parsed.length) {
                            amzFillTaskAssignees(parsed);
                            cb(amzTaskUsers);
                            return;
                        }
                    } catch (e) { /* fall through */ }
                }
                fetch("{{ route('tasks.usersList') }}", {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (users) {
                        amzFillTaskAssignees(users);
                        cb(amzTaskUsers);
                    })
                    .catch(function () {
                        amzFillTaskAssignees([]);
                        cb([]);
                    });
            }

            function amzResolveAssigneeId(label) {
                const q = String(label || '').trim().toLowerCase();
                if (!q) return 0;
                const exact = amzTaskUsers.find(function (u) {
                    return String(u.name || '').trim().toLowerCase() === q;
                });
                if (exact) return parseInt(exact.id, 10) || 0;
                const partial = amzTaskUsers.filter(function (u) {
                    return String(u.name || '').toLowerCase().indexOf(q) !== -1;
                });
                return partial.length === 1 ? (parseInt(partial[0].id, 10) || 0) : 0;
            }

            function openAmzTaskModal(data) {
                const groupEl = document.getElementById('amz-task-group');
                const titleEl = document.getElementById('amz-task-title');
                const linkEl = document.getElementById('amz-task-page-link');
                const searchEl = document.getElementById('amz-task-assignee-search');
                const idEl = document.getElementById('amz-task-assignee-id');
                const err = document.getElementById('amz-task-error');
                const ok = document.getElementById('amz-task-success');
                if (groupEl) groupEl.value = amzTaskGroupLabel(data);
                if (titleEl) titleEl.value = '';
                if (linkEl) linkEl.value = '';
                if (searchEl) searchEl.value = '';
                if (idEl) idEl.value = '';
                if (err) { err.textContent = ''; err.classList.add('d-none'); }
                if (ok) { ok.textContent = ''; ok.classList.add('d-none'); }
                amzLoadTaskUsers(function () {
                    amzShowTaskModal();
                    setTimeout(function () {
                        if (titleEl) titleEl.focus();
                    }, 150);
                });
            }

            function openAmzTaskFromCell(e, cell) {
                if (e && e.stopPropagation) e.stopPropagation();
                openAmzTaskModal((cell && cell.getRow) ? (cell.getRow().getData() || {}) : {});
            }

            const amzTaskAssigneeSearch = document.getElementById('amz-task-assignee-search');
            if (amzTaskAssigneeSearch) {
                amzTaskAssigneeSearch.addEventListener('input', function () {
                    const idEl = document.getElementById('amz-task-assignee-id');
                    if (idEl) idEl.value = String(amzResolveAssigneeId(this.value) || '');
                });
                amzTaskAssigneeSearch.addEventListener('change', function () {
                    const idEl = document.getElementById('amz-task-assignee-id');
                    if (idEl) idEl.value = String(amzResolveAssigneeId(this.value) || '');
                });
            }

            const amzTaskForm = document.getElementById('amz-task-form');
            if (amzTaskForm) {
                amzTaskForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const err = document.getElementById('amz-task-error');
                    const ok = document.getElementById('amz-task-success');
                    const saveBtn = document.getElementById('amz-task-assign');
                    const group = String((document.getElementById('amz-task-group') || {}).value || '').trim();
                    const title = String((document.getElementById('amz-task-title') || {}).value || '').trim();
                    const pageLink = String((document.getElementById('amz-task-page-link') || {}).value || '').trim() || amzTaskPageLink();
                    const assigneeLabel = String((document.getElementById('amz-task-assignee-search') || {}).value || '').trim();
                    const assigneeId = amzResolveAssigneeId(assigneeLabel)
                        || parseInt((document.getElementById('amz-task-assignee-id') || {}).value || '0', 10);

                    if (ok) { ok.textContent = ''; ok.classList.add('d-none'); }
                    if (!group) {
                        if (err) { err.textContent = 'Group is required.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!title) {
                        if (err) { err.textContent = 'Task is required.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!assigneeId) {
                        if (err) { err.textContent = 'Select a user to assign.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (!amzAssignorId) {
                        if (err) { err.textContent = 'You must be signed in to assign a task.'; err.classList.remove('d-none'); }
                        return;
                    }
                    if (err) { err.textContent = ''; err.classList.add('d-none'); }

                    const body = new FormData();
                    body.append('title', title);
                    body.append('group', group);
                    body.append('priority', 'normal');
                    body.append('assignor_id', String(amzAssignorId));
                    body.append('assignee_id', String(assigneeId));
                    body.append('etc_minutes', '10');
                    body.append('tid', new Date().toISOString().slice(0, 16));
                    body.append('l1', pageLink);
                    body.append('quick_create_more', '1');

                    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Assigning…'; }
                    fetch(amzTaskStoreUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body,
                    })
                        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                        .then(function (res) {
                            if (!res.ok || (res.data && res.data.success === false)) {
                                throw new Error((res.data && (res.data.message || res.data.error)) || 'Could not assign task.');
                            }
                            if (ok) {
                                ok.textContent = (res.data && res.data.message) || 'Task assigned.';
                                ok.classList.remove('d-none');
                            }
                            setTimeout(amzHideTaskModal, 700);
                        })
                        .catch(function (ex) {
                            if (err) { err.textContent = ex.message || 'Could not assign task.'; err.classList.remove('d-none'); }
                        })
                        .finally(function () {
                            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Assign'; }
                        });
                });
            }

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
                    amzSetDatesToLatestForSource('all_reports');
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
