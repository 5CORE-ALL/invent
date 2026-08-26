@extends('layouts.vertical', ['title' => 'Temu - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .temu-pause-run-btn {
            position: relative;
            display: inline-block;
            border: 0;
            border-radius: 999px;
            width: 44px;
            height: 24px;
            padding: 0;
            cursor: pointer;
            vertical-align: middle;
        }
        .temu-pause-run-btn.is-pause { background: #dc3545; }
        .temu-pause-run-btn.is-run { background: #198754; }
        .temu-pause-run-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
        }
        .temu-pause-run-btn.is-run .temu-pause-run-knob { left: auto; right: 3px; }
        .temu-pause-run-btn:disabled { opacity: 0.65; cursor: wait; }
        .temu-pause-run-ok {
            color: #198754;
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1;
        }
        .temu-pause-run-fail {
            color: #dc3545;
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1;
            cursor: help;
        }
        /* Target ROI% / Target GPFT% inputs — narrow enough for ~2 digits and
           strip the native number-input spinner arrows (the up/down chevrons
           that Chrome/Edge/Firefox draw on type="number"). Targeted by ID so
           the rule doesn't accidentally affect any other number inputs on the
           page. */
        #target-roi-input,
        #target-gpft-input {
            -moz-appearance: textfield; /* Firefox: drop the spinner */
        }
        #target-roi-input::-webkit-outer-spin-button,
        #target-roi-input::-webkit-inner-spin-button,
        #target-gpft-input::-webkit-outer-spin-button,
        #target-gpft-input::-webkit-inner-spin-button {
            -webkit-appearance: none; /* Chrome/Safari/Edge: drop the spinner */
            margin: 0;
        }

        /* NRP cell — show just a colored dot by default; clicking opens a native
           <select> that's positioned absolutely on top with opacity:0 so the dropdown
           menu appears right where the dot is. Same UX/CSS as /forecast.analysis. */
        .temu-nrp-cell {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 28px;
            min-width: 44px;
        }
        .temu-nrp-cell .temu-nrp-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        }
        .temu-nrp-cell .temu-nrp-select {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            margin: 0 !important;
            border: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            -webkit-appearance: none;
            appearance: none;
        }

        /* Summary Statistics — sizing comes from per-badge inline styles
           (font-size:14px / padding:8px 10px / flex:1 1 0 / min-width:90px)
           so the strip matches /doba-tabulator's compact look. No global
           bump rule here; previous 1.2× override was removed. */

        /* Toolbar row — flex-wrap so the row breaks to a 2nd line instead of
           growing a horizontal scrollbar. `flex-shrink: 0` on every child so
           individual selects keep their natural width (otherwise flex would
           squish them down on a narrow viewport). */
        .temu-toolbar-row > * {
            flex-shrink: 0;
        }

        /* Column visibility — 4 groups (Basics / Pricing / Advertisement / Others)
           Only style when open (.show); never force display:block or it stays open after refresh. */
        #column-dropdown-menu.show {
            min-width: min(92vw, 720px);
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
            width: 14px;
            height: 14px;
        }
        #column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }

        /* LMP modal: image + add form + our price on one line */
        #lmpModal .lmp-add-form-box {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: nowrap;
        }
        #lmpModal .lmp-add-form-fields {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex: 1 1 auto;
            min-width: 0;
        }
        #lmpModal .lmp-add-form-fields .lmp-field-price {
            flex: 0 0 100px;
        }
        #lmpModal .lmp-add-form-fields .lmp-field-delivery {
            flex: 0 0 90px;
        }
        #lmpModal .lmp-add-form-fields .lmp-field-link {
            flex: 1 1 auto;
            min-width: 120px;
        }
        #lmpModal .lmp-add-form-fields .lmp-field-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }
        #lmpModal .lmp-product-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            padding: 3px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            flex-shrink: 0;
        }
        #lmpModal .lmp-product-badge img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.35rem;
        }
        #lmpModal .lmp-product-badge .lmp-no-image {
            font-size: 10px;
            color: #adb5bd;
            text-align: center;
            line-height: 1.2;
        }
        #lmpModal .lmp-our-price-badge {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1px;
            padding: 6px 12px;
            background: #0d6efd;
            color: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            min-width: 90px;
            flex-shrink: 0;
        }
        #lmpModal .lmp-our-price-badge .lmp-our-price-label {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.9;
        }
        #lmpModal .lmp-our-price-badge .lmp-our-price-value {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.1;
        }

        /* LMP modal header metric badges (Dil / NROI / NPFT / CVR) */
        #lmpModal .modal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        #lmpModal .modal-header .modal-title {
            margin-right: auto;
        }
        #lmpModal .lmp-header-metrics {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        #lmpModal .lmp-header-metric {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            padding: 3px 8px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 0.35rem;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            color: #212529;
        }
        #lmpModal .lmp-header-metric .lmp-hm-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        #lmpModal .lmp-header-metric .lmp-hm-value.red { color: #dc3545; }
        #lmpModal .lmp-header-metric .lmp-hm-value.yellow { color: #d4a106; }
        #lmpModal .lmp-header-metric .lmp-hm-value.blue { color: #3591dc; }
        #lmpModal .lmp-header-metric .lmp-hm-value.green { color: #28a745; }
        #lmpModal .lmp-header-metric .lmp-hm-value.pink { color: #e83e8c; }
        #lmpModal .lmp-header-metric .lmp-hm-value.purple { color: #6f42c1; }
        #lmpModal .lmp-header-metric .lmp-hm-value.muted { color: #adb5bd; }

        /* LMP list: blue 5 Core reference row (sorted by price among competitors) */
        #lmpModal .lmp-five-core-row,
        #lmpModal .lmp-five-core-row > td {
            background-color: #dbeafe !important;
            color: #1e3a8a;
            --bs-table-bg-type: #dbeafe;
            --bs-table-striped-bg: #dbeafe;
            --bs-table-hover-bg: #bfdbfe;
            font-weight: 600;
        }
        #lmpModal .lmp-five-core-row:hover > td {
            background-color: #bfdbfe !important;
        }
        #lmpModal .lmp-five-core-row .lmp-five-core-price {
            font-size: 15px;
            font-weight: 700;
        }
        #lmpModal .lmp-entry-row.lmp-ignored {
            opacity: 0.55;
            background-color: #f8f9fa !important;
        }
        #lmpModal .lmp-entry-row.lmp-ignored .lmp-price {
            text-decoration: line-through;
        }
        #lmpModal .lmp-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 0.5rem;
        }
        /* Keep LMP rows + Save visible: list scrolls inside the modal */
        #lmpModal .modal-dialog {
            max-height: calc(100vh - 1.5rem);
            margin: 0.75rem auto;
        }
        #lmpModal .modal-content {
            max-height: calc(100vh - 1.5rem);
        }
        #lmpModal .modal-body {
            overflow-y: auto;
            min-height: 0;
        }
        #lmpModal .lmp-list-scroll {
            max-height: min(42vh, 360px);
            overflow: auto;
            scrollbar-gutter: stable;
            border: 1px solid #dee2e6;
            border-radius: 0.35rem;
        }
        #lmpModal #lmpListTable {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
        }
        #lmpModal #lmpListTable thead th {
            background: #f8f9fa;
            white-space: nowrap;
        }
        /* max-width:0 keeps fixed columns from expanding when cells contain flex/inputs */
        #lmpModal #lmpListTable th,
        #lmpModal #lmpListTable td {
            vertical-align: middle !important;
            padding: 0.35rem 0.4rem;
            overflow: hidden;
            max-width: 0;
        }
        /* Select | # | Price | Del | Price+D | Link | Ignore | Actions — widths must sum ~100% */
        #lmpModal #lmpListTable col.lmp-col-select { width: 4%; }
        #lmpModal #lmpListTable col.lmp-col-num { width: 4%; }
        #lmpModal #lmpListTable col.lmp-col-price { width: 13%; }
        #lmpModal #lmpListTable col.lmp-col-delivery { width: 9%; }
        #lmpModal #lmpListTable col.lmp-col-price-d { width: 11%; }
        #lmpModal #lmpListTable col.lmp-col-link { width: 32%; }
        #lmpModal #lmpListTable col.lmp-col-ignore { width: 10%; }
        #lmpModal #lmpListTable col.lmp-col-actions { width: 17%; }
        #lmpModal #lmpBulkDeleteBtn:disabled {
            opacity: 0.55;
        }
        #lmpModal .lmp-price-d {
            font-weight: 600;
            white-space: nowrap;
        }
        #lmpModal #lmpListTable .form-control {
            min-width: 0;
            box-sizing: border-box;
        }
        #lmpModal .lmp-price-cell,
        #lmpModal .lmp-link-cell {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: nowrap;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
        }
        #lmpModal .lmp-price-cell .lmp-price {
            flex: 1 1 auto;
            width: auto !important;
            max-width: 4.5rem !important;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
        }
        #lmpModal .lmp-delivery {
            width: 100% !important;
            max-width: 4.5rem !important;
            margin: 0 auto;
            display: block;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
        }
        #lmpModal .lmp-link-cell .lmp-link {
            flex: 1 1 auto;
            min-width: 0;
            width: auto !important;
            max-width: none !important;
            font-size: 11px;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
        }
        #lmpModal .lmp-link-cell .lmp-open-link,
        #lmpModal .lmp-link-cell .lmp-five-core-open-link {
            flex: 0 0 auto;
            padding: 0.15rem 0.4rem;
        }
        #lmpModal .lmp-lowest-badge {
            flex: 0 0 auto;
            white-space: nowrap;
            line-height: 1;
        }
        #lmpModal .lmp-lowest-badge .badge {
            font-size: 10px;
        }
        #lmpModal .lmp-five-core-row .lmp-price-cell {
            gap: 6px;
        }
        #lmpModal .lmp-l1-outside-badge {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1px;
            padding: 5px 12px;
            background: #0dcaf0;
            color: #053b4a;
            border-radius: 0.5rem;
            font-weight: 700;
            min-width: 90px;
        }
        #lmpModal .lmp-l1-outside-badge .lmp-l1-label {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            opacity: 0.85;
        }
        #lmpModal .lmp-l1-outside-badge .lmp-l1-value {
            font-size: 16px;
            line-height: 1.1;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
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
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }
        /* Tabulator column resize — visible ◂▸ grip on header borders */
        .tabulator .tabulator-header .tabulator-col-resize-handle {
            width: 10px !important;
            margin-left: -5px !important;
            margin-right: -5px !important;
            cursor: ew-resize !important;
            z-index: 20 !important;
            position: relative;
            background: transparent !important;
        }
        .tabulator .tabulator-header .tabulator-col-resize-handle::before {
            content: "◂▸";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 9px;
            line-height: 1;
            color: #fff;
            font-weight: 700;
            text-shadow: 0 0 2px rgba(0,0,0,0.55), 0 1px 1px rgba(0,0,0,0.35);
            pointer-events: none;
            opacity: 0.85;
            white-space: nowrap;
        }
        .tabulator .tabulator-header .tabulator-col-resize-handle:hover {
            background: rgba(255, 255, 255, 0.25) !important;
        }
        .tabulator .tabulator-header .tabulator-col-resize-handle:hover::before {
            opacity: 1;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* eBay-style color coding */
        .dil-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .dil-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .dil-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .dil-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .dil-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .dil-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
        }

        .dil-percent-value.purple {
            color: #d63384 !important;
            background: none !important;
        }

        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ffc107;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid #ddd;
        }

        .status-dot.green {
            background-color: #28a745;
        }

        .status-dot.red {
            background-color: #dc3545;
        }

        .status-dot.yellow {
            background-color: #ffc107;
        }

        .linked-sku-badge-wrap {
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }

        .linked-sku-badge-wrap .sku-link-lmp-remove {
            font-size: 0.55rem;
            opacity: 0.65;
            padding: 0;
            margin-left: 2px;
        }

        .linked-sku-badge-wrap .sku-link-lmp-remove:hover {
            opacity: 1;
        }

        .sku-link-lmp-suggestion-item {
            cursor: pointer;
        }

        .sku-link-lmp-suggestion-item .form-check-input {
            pointer-events: none;
        }

        .sku-link-lmp-selected-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            font-size: 12px;
        }

        .sku-link-lmp-selected-chip button {
            border: 0;
            background: transparent;
            padding: 0;
            line-height: 1;
            font-size: 14px;
            color: #64748b;
        }

        /* Metric history modals — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #badgeTrendChartModal.modal,
        #avgViewsChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #badgeTrendChartModal .modal-dialog,
        #avgViewsChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #badgeTrendChartModal .modal-content,
        #avgViewsChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'temu'])
        .temu-sprice-lmp-alert {
            color: #dc3545 !important;
            font-size: 13px;
            line-height: 1;
            margin-left: 4px;
            cursor: help;
        }
        .temu-sprice-cap-lbl {
            color: #fd7e14;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }
        .temu-sprice-blue-alert {
            color: #0d6efd;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }

        /* Hover text (badges, column headers, cell titles) — 1.5× default ~14px */
        .tabulator-tooltip,
        .tabulator-popup,
        .tabulator-popup-container {
            font-size: 1.3125rem !important;
            font-weight: 600 !important;
            line-height: 1.35 !important;
            padding: 8px 12px !important;
            max-width: min(92vw, 420px);
            white-space: normal;
        }
        #temu-hover-tip {
            display: none;
            position: fixed;
            z-index: 5000;
            max-width: min(92vw, 420px);
            padding: 8px 12px;
            border-radius: 8px;
            background: #1e293b;
            color: #fff;
            font-size: 1.3125rem;
            font-weight: 600;
            line-height: 1.35;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.22);
            pointer-events: none;
            white-space: normal;
        }
        #temu-hover-tip.is-on { display: block; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/temu-ads-color-rules.js') }}?v={{ @filemtime(public_path('js/temu-ads-color-rules.js')) ?: 15 }}"></script>
    <script src="{{ asset('js/temu-view-data-upload.js') }}?v={{ @filemtime(public_path('js/temu-view-data-upload.js')) ?: 1 }}"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            {{-- py-1 to keep the toolbar block compact; previous py-3 left too much
                 padding above the badge strip and below the filter rows. --}}
            <div class="card-body py-1">
                {{-- Compact summary strip — positioned at the TOP of the toolbar
                     to match /doba-tabulator. Each badge:
                       • flex:1 1 0 + text-center → spreads edge-to-edge across the row.
                       • min-width:90px so labels stay readable on narrow viewports.
                       • font-size:14px + padding:8px 10px → matches doba sizing.
                       • flex-nowrap + overflow-x:auto → single horizontal band; the
                         strip scrolls horizontally rather than wrapping if there
                         are more badges than fit (same pattern as doba).
                     IMPORTANT: badge IDs are unchanged, so updateSummary() and the
                     click-to-filter handlers attached elsewhere still work. --}}
                <div id="summary-stats" class="p-1 bg-light rounded mb-1">
                    {{-- Was `flex-nowrap` + `overflow-x:auto` (would scroll horizontally
                         when too many badges); switched to `flex-wrap` so the strip
                         simply wraps to a second line on narrow viewports. No scroll bar. --}}
                    <div class="d-flex flex-wrap gap-1 w-100">
                        <span id="rows-count-badge"
                              class="badge bg-dark text-center"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px;"
                              title="Rows currently shown (updates when a filter is applied)"
                              aria-label="Row count">Rows 0</span>
                        <!-- Basic Counts (sales summary = same as tabulator sales page) -->
                        <span id="total-revenue-badge"
                              class="badge bg-success text-center temu-badge-history"
                              data-badge-metric="total_sales" data-badge-label="Sales"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Total Sales on Full Temu Price: (Base × 1.1364) + $2.99 if ≤ $26.99 — click to view history"
                              aria-label="Total Sales">$ 0</span>
                        <span id="total-recovery-badge"
                              class="badge bg-primary text-center"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px;"
                              title="Recovery Price = Sales × 0.88 (Full Temu Price × 0.88 × Qty)"
                              aria-label="Recovery Price">$ 0</span>
                        {{-- "Orders" badge removed per product request. backendOrders is
                             still parsed in updateSummary() (it's part of the same
                             sales_summary payload that drives QTY + Sales), but no
                             longer rendered. --}}
                        <span id="total-quantity-badge"
                              class="badge bg-success text-center temu-badge-history"
                              data-badge-metric="total_quantity" data-badge-label="QTY"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to view history">QTY 0</span>
                        <span id="zero-sold-count-badge"
                              class="badge bg-danger text-center"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to filter 0 sold items (INV>0)">0 Sold 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'temu-price-gt-lmp-badge', 'pglChannelKey' => 'temu', 'pglPriceField' => 'temu_price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'temu-price-lt80-lmp-badge', 'pltChannelKey' => 'temu', 'pltPriceField' => 'temu_price'])
                        {{-- "Views" badge (formerly "Green Alert") and "Alert" badge (formerly
                             "Red Alert") removed per product request. temuIsGreenAlert() /
                             temuIsRedAlert() and Temu Price cell colors stay. --}}
                        <span id="temu-sprice-lmp-alert-badge"
                              class="badge text-center"
                              style="background-color: #dc3545; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Red triangle in S PRC (capped at LMP). Not the blue triangle. Click to show only those rows."
                              aria-label="S PRC red triangle at or above LMP"><i class="fas fa-exclamation-triangle"></i> S PRC 0</span>
                        <span id="temu-blue-triangle-badge"
                              class="badge text-center"
                              style="background-color: #0d6efd; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Blue triangle: Temu Price &gt; S PRC. Click to show only those rows."
                              aria-label="Temu Price greater than S PRC"><i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span id="temu-amz-cap-badge"
                              class="badge text-center"
                              style="background-color: #fd7e14; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="S PRC capped to Amazon. Click to show only Amz rows."
                              aria-label="S PRC capped to Amazon">Amz 0</span>
                        <span id="temu-eb-cap-badge"
                              class="badge text-center"
                              style="background-color: #fd7e14; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="S PRC capped to eBay. Click to show only EB rows."
                              aria-label="S PRC capped to eBay">EB 0</span>

                        <!-- Pricing & Performance -->
                        {{-- "Total Views" + "Total Sold" badges removed per product request.
                             The underlying sums (cvrTotalViews / cvrTotalSold) are still
                             computed in JS because the CVR badge below uses them
                             (Total Sold ÷ Total Views × 100). --}}
                        <span id="avg-cvr-badge"
                              class="badge bg-warning text-center temu-badge-history"
                              data-badge-metric="avg_cvr_pct" data-badge-label="CVR %"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Total Sold / Total Views * 100">CVR 0.0%</span>

                        {{-- Financial Totals (kept hidden — % equivalents in the next group
                             carry the same signal; JS still writes to these IDs harmlessly). --}}
                        <span id="total-profit-badge" class="badge bg-primary" style="font-weight:700; color: white !important; display:none;">PFT $0</span>
                        <span id="total-lp-badge" class="badge bg-info" style="font-weight:700; color: #111 !important; display:none;">Total LP $0</span>

                        <!-- Percentages (Gross) -->
                        <span id="avg-gprft-badge"
                              class="badge bg-success text-center"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px;">GPFT 0%</span>
                        <span id="avg-groi-badge"
                              class="badge text-center"
                              style="background-color: #6f42c1; color: white !important; font-weight:700; font-size:14px; padding:4px 8px;">GROI 0%</span>

                        <!-- Advertising Metrics -->
                        <span id="total-spend-badge"
                              class="badge text-center temu-badge-history"
                              data-badge-metric="total_spend" data-badge-label="Spend"
                              style="background-color: #87CEEB; color: #111 !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to view history">Ads$ 0.00</span>
                        <span id="avg-ads-badge"
                              class="badge bg-warning text-center"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px;">Ads 0%</span>
                        <span id="temu-campaign-count"
                              class="badge text-center temu-ads-badge"
                              data-ads-filter="campaign"
                              style="background-color: #9ec5fe; color: #111 !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Has an ad campaign (Active / spend / clicks) — same as /temu/ads">Campaign 0</span>
                        <span id="temu-status-active-badge"
                              class="badge bg-success text-center temu-ads-badge"
                              data-ads-filter="status-active"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Status Active — same as /temu/ads">Active 0</span>
                        <span id="temu-status-inactive-badge"
                              class="badge bg-warning text-center temu-ads-badge"
                              data-ads-filter="status-inactive"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Status Inactive — same as /temu/ads">Inactive 0</span>
                        <span id="temu-status-no-ad-badge"
                              class="badge bg-dark text-center temu-ads-badge"
                              data-ads-filter="status-no-ad"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Status No ad — same as /temu/ads">No ad 0</span>
                        <span id="temu-status-not-sync-badge"
                              class="badge bg-secondary text-center temu-ads-badge"
                              data-ads-filter="status-not-sync"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Status Not sync — Ads API not confirmed">Not sync 0</span>
                        <span id="temu-total-spend-badge"
                              class="badge text-center temu-ads-badge"
                              data-ads-filter="total-spend"
                              style="background-color: #6f42c1; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Ad spend from /temu/ads (temu_ads_api_reports). Click to filter rows with spend.">Spend: $0.00</span>
                        <span id="temu-total-ad-clicks-badge"
                              class="badge text-center temu-ads-badge"
                              data-ads-filter="ad-clicks"
                              style="background-color: #e83e8c; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Ad clicks from /temu/ads">Ad Clicks 0</span>
                        <span id="temu-total-ad-sales-badge"
                              class="badge text-center temu-ads-badge"
                              data-ads-filter="ad-sales"
                              style="background-color: #9eeaf9; color: #111 !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Ad sales from /temu/ads">Ad Sales $0</span>
                        <span id="temu-avg-acos-badge"
                              class="badge bg-warning text-center temu-ads-badge"
                              data-ads-filter="avg-acos"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Avg ACOS from /temu/ads">ACOS 0%</span>
                        <span id="temu-roas-badge"
                              class="badge text-center temu-ads-badge"
                              data-ads-filter="roas"
                              style="background-color: #a3cfbb; color: #111 !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="ROAS from /temu/ads">ROAS 0.00</span>

                        <!-- Percentages (Net) -->
                        <span id="avg-npft-badge"
                              class="badge bg-success text-center"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px;">NPFT 0%</span>
                        <span id="avg-nroi-badge"
                              class="badge text-center"
                              style="background-color: #6f42c1; color: white !important; font-weight:700; font-size:14px; padding:4px 8px;">NROI 0%</span>

                       
                        <span id="total-views-badge"
                              class="badge bg-info text-center temu-badge-history"
                              data-badge-metric="total_views" data-badge-label="Views"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Total Views from Seller Center sheet (temu_view_data Product clicks) — click for history"
                              aria-label="Total Views">Views 0</span>
                        <span id="avg-views-badge"
                              class="badge bg-info text-center temu-badge-history"
                              data-badge-metric="avg_views" data-badge-label="AVG views"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Average Views per product from View Data sheet — click for history"
                              aria-label="Average Views per product">AVG 0</span>
                    </div>
                </div>

                {{-- Toolbar (filters + actions) — wraps onto a 2nd row when the
                     viewport can't fit everything on one row. No horizontal scroll
                     bar; controls keep their natural width (via `flex-shrink:0` in
                     the CSS rule for .temu-toolbar-row > *) and the row simply
                     overflows downward instead of sideways. --}}
                <div class="d-flex align-items-center flex-wrap gap-1 mb-1 temu-toolbar-row">
                    {{-- Row type filter (All Rows / Parents / SKUs) — same as temu2 / Amazon --}}
                    <div>
                        <select id="parent-filter" class="form-select form-select-sm" style="width: 130px;"
                            title="Filter by row type: All Rows, Parents only, or SKUs only">
                            <option value="all">All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus" selected>SKUs</option>
                        </select>
                    </div>

                    <!-- Inventory Filter -->
                    <div>
                        <select id="inventory-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Inventory</option>
                            <option value="gt0" selected>INV &gt; 0</option>
                            <option value="eq0" >INV = 0</option>
                        </select>
                    </div>

                    {{-- GPFT% filter — flattened into its own chip (was previously
                         stacked vertically with the CVR% filter via flex-column). --}}
                    <div>
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40plus">Above 40%</option>
                        </select>
                    </div>

                    {{-- CVR% filter — now sits in the same horizontal row as the
                         other filters instead of stacking under GPFT%. --}}
                    <div>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    {{-- CVR trend — CVR 30 vs CVR 60 (same arrows as CVR column; options match /ebay2-tabulator-view) --}}
                    <div>
                        <select id="cvr-trend-filter" class="form-select form-select-sm" style="width: 130px;"
                            title="CVR L30 vs prior period L31–L60 (CVR 60)">
                            <option value="all">CVR trend</option>
                            <option value="down">Down</option>
                            <option value="up">Up</option>
                            <option value="same">Same</option>
                        </select>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + every other /pricing page).
                         Backed by `temu_l30`:
                           all  → no filter
                           sold → temu_l30 > 0
                           zero → temu_l30 = 0 AND INV > 0 (preserves the original
                                  #zero-sold-count-badge semantics — "0 sold items (INV>0)")
                         The existing #zero-sold-count-badge click handler just toggles this
                         dropdown so badges + dropdown can never disagree. There is no
                         "> 0 Sold" badge on this page, but the dropdown still offers the
                         option for symmetry with the Amz styling. --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: 130px;"
                            title="Filter by Temu L30 sold quantity (0 Sold also requires INV > 0)">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <!-- ROI Filter -->
                    <div>
                        <select id="roi-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">GROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-60">40–60%</option>
                            <option value="60-80">60–80%</option>
                            <option value="80-100">80–100%</option>
                            <option value="gt100">100%+</option>
                        </select>
                    </div>

                    {{-- DIL% bracket filter — buckets aligned with /topdawg-tabulator:
                         Red < 25, Green 25–50, Pink ≥ 50. The yellow band (16.7–25%)
                         that used to exist was merged into red so the temu DIL color
                         scheme matches Topdawg's. --}}
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25–50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    {{-- Dil vs PRMT / Cpn% — same action row as /ebay-tabulator-view --}}
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'temu'])

                    {{-- Target ROI% bulk control — back-solves SPRICE for selected rows so SROI = Target ROI%.
                         stemuPrice = (LP × (1 + ROI%/100) + temu_ship) / margin; then sprice = stemuPrice
                         or stemuPrice − 2.99 (Temu adds a $2.99 ship bumper when sprice ≤ $26.99).
                         Visual styling matches /doba-tabulator: 🎯 emoji label + icon-only Apply button. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-white"
                        id="target-roi-controls"
                        title="Target ROI% — sets SPRICE so SROI = S Profit/LP using (S Recovery × marketplace%) − temu_ship − LP">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap"
                               aria-label="Target ROI percent">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" maxlength="2" style="width: 45px;"
                            title="Target ROI% applied to all selected rows when you click Apply">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Apply — Compute & save SPRICE so SROI column = Target ROI% for every selected row"
                            aria-label="Apply Target ROI">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves SPRICE for selected rows so SGPRFT = Target GPFT%.
                         Formula: Sprice = (LP + temu_ship) / (marketplace% − GPFT%/100). --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-white"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets SPRICE so SGPRFT = target using (Full Sprice × marketplace%) − temu_ship − LP">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap"
                               aria-label="Target GPFT percent">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" maxlength="2" style="width: 45px;"
                            title="Target GPFT% applied to all selected rows when you click Apply. Must be less than 80%.">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Apply — Compute & save SPRICE so SGPRFT column = Target GPFT% for every selected row"
                            aria-label="Apply Target GPFT">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <!-- NRL/REQ Filter -->
                    <div>
                        <select id="nr-req-filter" class="form-select form-select-sm" style="width: 100px;">
                            <option value="all">ALL</option>
                            <option value="NRL">NRL</option>
                            <option value="REQ" selected>REQ</option>
                        </select>
                    </div>

                    {{-- NRP Filter — filters by the NRP column (mirrors forecast_analysis.nr).
                         Defaults to ALL; toolbar reads option values that match what's stored
                         on each row's `nrp` field (REQ / NR / LATER). --}}
                    <div>
                        <select id="nrp-filter" class="form-select form-select-sm" style="width: 110px;" title="Filter by NRP">
                            <option value="all">NRP</option>
                            <option value="REQ">REQ</option>
                            <option value="NR">2BDC</option>
                            <option value="LATER">LATER</option>
                        </select>
                    </div>

                    <!-- Play / Pause parent navigation (like pricing-master-cvr) -->
                    <div class="btn-group align-items-center ms-2" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause - click to reset Play">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Show / hide table columns"
                            aria-label="Toggle column visibility">
                            <i class="fas fa-table-columns"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                        </ul>
                    </div>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn"
                        title="Export L30 data to CSV"
                        aria-label="Export L30">
                        <i class="fas fa-file-export"></i>
                    </button>
                    <div class="d-inline-flex align-items-center gap-1 flex-shrink-0 border rounded px-2 py-1 bg-light ms-1" title="Ads API & sales period for this table">
                        <label for="campaign-period-select" class="mb-0 small fw-semibold text-nowrap text-dark">Campaign</label>
                        <select id="campaign-period-select" class="form-select form-select-sm" style="min-width: 88px;">
                            <option value="L30" selected>L30</option>
                            <option value="L7">L7</option>
                        </select>
                    </div>
                    <button type="button" id="temu-ads-rules-btn" class="btn btn-sm btn-outline-dark flex-shrink-0"
                            data-bs-toggle="modal" data-bs-target="#temuAdsRulesModal"
                            title="Open L7 Clicks / Stop ROAS bidding rule">
                        <i class="fas fa-sliders-h me-1"></i><span id="temu-ads-rules-summary">L7 &lt; 70 → ROAS 8</span>
                    </button>
                    <a href="{{ route('temu.ads') }}" class="btn btn-sm btn-outline-warning" title="Ads Spend / Clicks / ACOS / Status come from this page (temu_ads_api_reports)">
                        <i class="fas fa-bullhorn"></i> Ads
                    </a>

                    {{-- View uploads only. Ads come from Temu Ads API (temu_ads_api_reports), not a sheet. --}}
                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle"
                            id="temuUploadDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Upload Temu data files"
                            aria-label="Upload Temu data files">
                            <i class="fas fa-upload"></i>
                        </button>
                        <ul class="dropdown-menu shadow-sm" aria-labelledby="temuUploadDropdown">
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                    data-bs-toggle="modal" data-bs-target="#uploadViewDataModal">
                                    <i class="fas fa-eye text-success" style="width: 18px;"></i>
                                    <span>Up View Data</span>
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                    data-bs-toggle="modal" data-bs-target="#scrapeViewDataModal">
                                    <i class="fas fa-spider text-primary" style="width: 18px;"></i>
                                    <span>Scrape Views</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button type="button" id="fetch-views-api-btn" class="btn btn-sm btn-outline-primary"
                        title="Fetch View 7 from Temu Ads API. Ads Spend / Clicks / ACOS also use temu_ads_api_reports (not a sheet). Main Views column uses Up View Data."
                        aria-label="Fetch View 7 from Temu Ads API">
                        <i class="fas fa-sync-alt"></i> Views API
                    </button>
                    <span id="fetch-views-api-status" class="small text-muted" style="display:none;"></span>

                    {{-- SKU search — moved into the toolbar so it wraps alongside the
                         other filter dropdowns instead of sitting on a dedicated row
                         above the table. Width capped at ~30 chars; maxlength matches
                         so users can't type past the visible field. The keyup handler
                         + filter logic in applyFilters() read $('#sku-search').val()
                         which still resolves correctly because we kept the ID. --}}
                    <div class="d-flex align-items-center gap-1">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU…"
                            maxlength="30"
                            style="width: 30ch; max-width: 100%;">
                        <small id="search-result-info" class="text-muted" style="display: none;"></small>
                    </div>
                </div>

            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="badge bg-info">0 SKUs selected</span>
                        <span id="discount-input-label" class="text-muted small">Same Price ($):</span>
                        <span id="discount-type-select-wrap" class="d-none">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                               placeholder="Enter price (e.g. 19.99)" style="width: 170px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply Same Price
                        </button>
                        <button type="button" id="apply-sprice-from-std-btn" class="btn btn-sm btn-success"
                            title="Set S PRC = Std × (1 − (PRMT% + CPN%)/100) for selected SKUs. If both % are 0, S PRC = Std.">
                            <i class="fas fa-calculator"></i> Apply SPrice
                        </button>
                        <button id="sprc-26-99-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-dollar-sign"></i> SPRC 26.99
                        </button>
                        <button type="button" id="clear-sprice-btn" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Clear SPRICE
                        </button>
                        <button type="button" id="push-temu-price-btn" class="btn btn-sm btn-primary"
                            title="Push SPRICE→base: inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)">
                            <i class="fas fa-cloud-upload-alt"></i> Push Prices
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    {{-- SKU search input moved up into the toolbar row (.temu-toolbar-row)
                         so it shares the same flex-wrap behavior as the other filters.
                         The table starts directly under the toolbar now. --}}
                    <div id="temu-table-error" class="text-danger small px-2 py-2 d-none"></div>
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Shared L7 Clicks → Stop ROAS bidding rule --}}
    <div class="modal fade" id="temuAdsRulesModal" tabindex="-1" aria-labelledby="temuAdsRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="temuAdsRulesModalLabel">Ad rules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Shared with /temu/ads. If L7 clicks are below the threshold, the row is red. Active ads with L7 clicks below the threshold and ROAS below Stop ROAS are paused automatically after the daily L7 fetch when the cron is ON.</p>
                    <div class="d-inline-flex flex-wrap align-items-center gap-1 border rounded px-3 py-2 bg-light">
                        <label for="temu-l7-clicks-red-threshold" class="mb-0 small fw-semibold text-nowrap text-dark">L7 Clicks &lt;</label>
                        <input type="number" id="temu-l7-clicks-red-threshold" class="form-control form-control-sm"
                               min="0" max="100000" step="1" value="70" style="width: 70px;">
                        <span class="small fw-bold" style="color:#a00211;">Red</span>
                        <span class="text-muted px-1">→</span>
                        <label for="temu-target-roas-bidding" class="mb-0 small fw-semibold text-nowrap text-dark">Stop ROAS</label>
                        <input type="number" id="temu-target-roas-bidding" class="form-control form-control-sm"
                               min="0.1" max="1000" step="0.1" value="8" style="width: 70px;">
                        <span class="small fw-bold" style="color:#0d6efd;">Pause</span>
                    </div>
                    <div id="temu-ads-cron-status" class="small mt-2 text-success">Daily cron: ON — auto-pause after L7 fetch and at 16:10 IST.</div>
                    <div id="temu-ads-pause-status" class="mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-warning" id="temu-ads-cron-toggle-btn"
                            data-enabled="1"
                            title="Daily auto-pause cron is ON. Click to pause it.">
                        <i class="fas fa-pause me-1"></i>Pause Cron
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="temu-ads-auto-pause-btn"
                            title="Pause Active ads that match this rule on Temu now">
                        <i class="fas fa-pause me-1"></i>Pause matching ads
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Modal: Add New + List (like Competitors), lowest LMP highlighted -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="lmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="lmpModalSku"></span></h5>
                    <div class="lmp-header-metrics" id="lmpHeaderMetrics">
                        <span class="lmp-header-metric" title="Dil%"><span class="lmp-hm-label">Dil%</span><span class="lmp-hm-value" id="lmpHeaderDil">—</span></span>
                        <span class="lmp-header-metric" title="NROI%"><span class="lmp-hm-label">NROI%</span><span class="lmp-hm-value" id="lmpHeaderNroi">—</span></span>
                        <span class="lmp-header-metric" title="NPFT%"><span class="lmp-hm-label">NPFT%</span><span class="lmp-hm-value" id="lmpHeaderNpft">—</span></span>
                        <span class="lmp-header-metric" title="CVR%"><span class="lmp-hm-label">CVR%</span><span class="lmp-hm-value" id="lmpHeaderCvr">—</span></span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-2"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="lmp-add-form-box">
                            <div class="lmp-product-badge" id="lmpModalProductBadge" title="Product image">
                                <span class="lmp-no-image">No image</span>
                            </div>
                            <div class="lmp-add-form-fields">
                                <div class="lmp-field-price">
                                    <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewPrice" placeholder="e.g. 29.99">
                                </div>
                                <div class="lmp-field-delivery">
                                    <label class="form-label small mb-0" title="Added to Price for LMP / L1">Delivery</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewDelivery" placeholder="0.00">
                                </div>
                                <div class="lmp-field-link">
                                    <label class="form-label small mb-0">Product Link</label>
                                    <input type="text" class="form-control form-control-sm" id="lmpNewLink" placeholder="https://...">
                                </div>
                                <div class="lmp-field-actions">
                                    <button type="button" class="btn btn-sm btn-primary" id="lmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lmpClearFormBtn" title="Clear form"><i class="fas fa-undo"></i></button>
                                </div>
                            </div>
                            <div class="lmp-our-price-badge" id="lmpModalOurPriceBadge" title="Our current Temu price">
                                <span class="lmp-our-price-label">Our Price</span>
                                <span class="lmp-our-price-value" id="lmpModalOurPrice">—</span>
                            </div>
                        </div>
                        <div class="form-text mt-2 mb-0">Adds to the list below — click <strong>Save</strong> to write to <code>temu_lmp</code>.</div>
                    </div>
                    <div class="lmp-list-header">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h6 class="mb-0">LMP List <span class="badge bg-secondary" id="lmpListCountBadge">0</span></h6>
                            <button type="button" class="btn btn-sm btn-danger" id="lmpBulkDeleteBtn" disabled
                                title="Delete selected LMP rows (saved immediately)">
                                <i class="fas fa-trash-alt me-1"></i>Delete Selected
                            </button>
                        </div>
                        <div class="lmp-l1-outside-badge" title="Lowest non-ignored competitor price (L1)">
                            <span class="lmp-l1-label">L1 Price</span>
                            <span class="lmp-l1-value" id="lmpModalL1Price">—</span>
                        </div>
                    </div>
                    <div class="lmp-list-scroll">
                        <table class="table table-sm table-bordered mb-0" id="lmpListTable">
                            <colgroup>
                                <col class="lmp-col-select">
                                <col class="lmp-col-num">
                                <col class="lmp-col-price">
                                <col class="lmp-col-delivery">
                                <col class="lmp-col-price-d">
                                <col class="lmp-col-link">
                                <col class="lmp-col-ignore">
                                <col class="lmp-col-actions">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" title="Select for bulk delete">
                                        <input type="checkbox" class="form-check-input m-0" id="lmpSelectAllCb" title="Select all">
                                    </th>
                                    <th class="text-center">#</th>
                                    <th>Price</th>
                                    <th class="text-center" title="Added to Price for LMP / L1">Del</th>
                                    <th class="text-center" title="Price + Delivery (defaults Del $2.99 when Price &lt; $27)">Price+D</th>
                                    <th>Link</th>
                                    <th class="text-center" title="Ignore for L1 — product stays in list">Ignore</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="lmpModalSaveBtn"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sku Link LMP Modal (same as /purchase-master/sku-link-lmp) -->
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skuLinkLmpModalLabel">Sku Link LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Link one or more SKUs to <strong id="sku-link-lmp-source"></strong>. All linked SKUs will show each other.</p>
                    <label for="sku-link-lmp-input" class="form-label mb-1">Search SKU to link</label>
                    <input type="text" id="sku-link-lmp-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                    <div id="sku-link-lmp-suggestions" class="list-group mt-2 d-none" style="max-height: 220px; overflow-y: auto;"></div>
                    <div id="sku-link-lmp-selected-wrap" class="mt-2 d-none">
                        <div class="small text-muted mb-1">Selected to link (<span id="sku-link-lmp-selected-count">0</span>):</div>
                        <div id="sku-link-lmp-selected-skus" class="d-flex flex-wrap"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sku-link-lmp-save-btn">
                        <i class="mdi mdi-link"></i> <span id="sku-link-lmp-save-btn-label">Link SKU(s)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge History Modal: click on a badge to see that metric's history -->
    <div class="modal fade" id="badgeHistoryModal" tabindex="-1" aria-labelledby="badgeHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="badgeHistoryModalLabel"><i class="fas fa-history me-2"></i>History: <span id="badgeHistoryModalMetricName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label class="text-nowrap">Days:</label>
                        <select id="badgeHistoryModalDays" class="form-select form-select-sm" style="width: 90px;">
                            <option value="30">L30</option>
                            <option value="60" selected>L60</option>
                            <option value="90">L90</option>
                        </select>
                        <button type="button" id="badgeHistoryModalRefresh" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="table-responsive" style="max-height: 360px;">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light sticky-top">
                                <tr><th>Date</th><th id="badgeHistoryModalValueTh">Value</th></tr>
                            </thead>
                            <tbody id="badgeHistoryModalTbody">
                                <tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Scrape Views Modal (Seller Center cookie / Network JSON → temu_view_data) -->
    <div class="modal fade" id="scrapeViewDataModal" tabindex="-1" aria-labelledby="scrapeViewDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="scrapeViewDataModalLabel">
                        <i class="fas fa-spider me-2"></i>Scrape Temu Views (Seller Center)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2">
                        Temu OpenAPI has <strong>no organic Views</strong>. This uses your logged-in Seller Center cookie
                        (or a Network-tab JSON paste). Cookies expire — refresh from browser when scrape fails.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Seller Center Cookie</label>
                        <textarea id="scrapeViewCookie" class="form-control form-control-sm" rows="3"
                            placeholder="Paste document.cookie from seller.temu.com / agentseller.temu.com (or leave blank if TEMU_SELLER_COOKIE is set on server)"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Days</label>
                            <select id="scrapeViewDays" class="form-select form-select-sm">
                                <option value="7">L7</option>
                                <option value="30" selected>L30</option>
                                <option value="60">L60</option>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-2">
                            <button type="button" id="scrapeViewProbeBtn" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-stethoscope"></i> Probe
                            </button>
                            <button type="button" id="scrapeViewRunBtn" class="btn btn-sm btn-primary">
                                <i class="fas fa-spider"></i> Scrape → temu_view_data
                            </button>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Or import Network JSON</label>
                        <textarea id="scrapeViewJson" class="form-control form-control-sm font-monospace" rows="6"
                            placeholder='Paste JSON response from Seller Center → Product Analytics (DevTools → Network)'></textarea>
                        <div class="form-text">Most reliable when Temu changes internal scrape URLs.</div>
                    </div>
                    <button type="button" id="scrapeViewImportJsonBtn" class="btn btn-sm btn-success">
                        <i class="fas fa-file-import"></i> Import JSON → temu_view_data
                    </button>
                    <pre id="scrapeViewStatus" class="small bg-light border rounded p-2 mt-3 mb-0" style="max-height: 180px; overflow:auto; display:none;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload View Data Modal (Seller Center product clicks → temu_view_data) -->
    <div class="modal fade" id="uploadViewDataModal" tabindex="-1" aria-labelledby="uploadViewDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadViewDataModalLabel">
                        <i class="fas fa-eye me-2"></i>Upload Temu View Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form id="uploadViewDataForm" action="{{ route('temu.viewdata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if($errors->any())
                            <div class="alert alert-danger py-2">
                                {{ $errors->first() }}
                            </div>
                        @endif
                        <div class="mb-3">
                            <label for="viewDataFile" class="form-label fw-bold">
                                <i class="fas fa-file-excel text-success me-1"></i>Choose View File(s)
                            </label>
                            <input type="file" class="form-control" id="viewDataFile" name="files[]" accept=".xlsx,.xls,.csv,.tsv,.txt" multiple>
                            <div class="form-text">
                                Select multiple Seller Center daily exports (.xlsx / .xls / .csv / .tsv). Max 10MB each.
                                Click <strong>Choose files</strong> again to add more — they stay queued.
                                Writes to <code>temu_view_data</code> — this drives the <strong>Views</strong> column.
                            </div>
                            <div id="viewDataFileList" class="small mt-2"></div>
                            <div id="viewDataUploadStatus" class="alert py-2 px-3 mb-0 mt-2" style="display:none;"></div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            First batch replaces existing Temu 1 view data. Extra files merge (same Date + Goods ID → last wins).
                            <a href="{{ route('temu.viewdata.sample') }}" class="alert-link">
                                <i class="fas fa-download"></i> Download Sample
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadViewDataForm" class="btn btn-success">
                        <i class="fas fa-upload me-1"></i>Up View Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge Trend Chart Modal (same graph as first image: teal header, line chart, median line, value labels, High/Med/Low) -->
    <div class="modal fade p-0" id="badgeTrendChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Temu - <span id="badgeTrendChartTitle">Sales</span> <span id="badgeTrendChartSuffix">(Rolling L30)</span></span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="badgeTrendChartDays" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="badgeTrendChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="badgeTrendChartCanvas"></canvas>
                        </div>
                        <div id="badgeTrendChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0;">
                            <div style="min-width: 62px; text-align: center; padding: 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="badgeTrendChartRefDot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0dcaf0;"></span><span id="badgeTrendChartRefLabel">Sales</span></div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div><div id="badgeTrendChartHigh" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div><div id="badgeTrendChartMed" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div><div id="badgeTrendChartLow" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="badgeTrendChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="badgeTrendChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No history. Run <code>php artisan temu:collect-metrics</code> to populate.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Average Views History Modal -->
    <div class="modal fade p-0" id="avgViewsChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Daily Average Views History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label fw-bold mb-0 me-2">Date Range:</label>
                            <select id="avg-views-days-filter" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                <option value="30" selected>Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                        <div class="text-muted">
                            <small><i class="fas fa-info-circle"></i> Shows historical average views across all products</small>
                        </div>
                    </div>
                    <div id="avg-views-no-data-message" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No Data Available:</strong> No historical data available yet. Click "Store Daily Avg" to begin tracking.
                    </div>
                    <div style="height: 400px; position: relative;">
                        <canvas id="avgViewsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="temuEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="temuEditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="temuEditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="temuEditSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="temuEditSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="temuEditBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="temuEditBuyerLink" placeholder="https://...">
                    </div>
                    <div id="temuEditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="temuSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'temu'])
@endsection

@section('script-bottom')
<script>
        (function () {
            var tip = document.createElement('div');
            tip.id = 'temu-hover-tip';
            document.body.appendChild(tip);
            function hideTip() { tip.classList.remove('is-on'); }
            function placeTip(e) {
                var x = e.clientX + 14;
                var y = e.clientY + 16;
                var rect = tip.getBoundingClientRect();
                if (x + rect.width > window.innerWidth - 8) x = Math.max(8, window.innerWidth - rect.width - 8);
                if (y + rect.height > window.innerHeight - 8) y = Math.max(8, e.clientY - rect.height - 12);
                tip.style.left = x + 'px';
                tip.style.top = y + 'px';
            }
            document.addEventListener('mouseover', function (e) {
                var el = e.target && e.target.closest
                    ? e.target.closest('#summary-stats [title], #summary-stats [data-hover-text], .tabulator [title], .tabulator [data-hover-text]')
                    : null;
                if (!el) return;
                var text = el.getAttribute('data-hover-text') || el.getAttribute('title');
                if (!text) return;
                if (el.getAttribute('title')) {
                    el.setAttribute('data-hover-text', text);
                    el.removeAttribute('title');
                }
                tip.textContent = text;
                tip.classList.add('is-on');
                placeTip(e);
            });
            document.addEventListener('mousemove', function (e) {
                if (tip.classList.contains('is-on')) placeTip(e);
            });
            document.addEventListener('mouseout', function (e) {
                var el = e.target && e.target.closest
                    ? e.target.closest('#summary-stats [data-hover-text], .tabulator [data-hover-text]')
                    : null;
                if (!el) return;
                var next = e.relatedTarget;
                if (next && el.contains(next)) return;
                hideTip();
            });
        })();
        const COLUMN_VIS_KEY = "temu_decrease_column_visibility";
        let savedColumnVisibilityMap = {};
    // Temu margin from marketplace_percentages (Temu) — same source as backend GROI/GPFT/SROI
    const TEMU_MARGIN = {{ (float) ($temuMargin ?? \App\Services\TemuShopifySalesService::temuMarginDecimal()) }};
    // Full Temu Price = (Base × 1.1364); if that ≤ $26.99 then +$2.99
    // Used by Temu Price column, Sales, GPFT / NPFT / SGPRFT / SPFT (not GROI/SROI)
    const TEMU_FULL_PRICE_MULT = 1.1364;
    /** Row margin (decimal) from marketplace_percentages; never hardcode 0.80. */
    function temuSpriceMargin(rowData) {
        const marginRaw = parseFloat(rowData && rowData.percentage);
        return (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
    }
    function temuRPriceFromBase(basePrice) {
        const b = parseFloat(basePrice) || 0;
        if (!(b > 0)) return 0;
        return +(b <= 26.99 ? b + 2.99 : b).toFixed(2);
    }
    function temuRPriceFromRow(rowData) {
        const basePrice = parseFloat(rowData && rowData.base_price) || 0;
        if (basePrice > 0) return temuRPriceFromBase(basePrice);
        return parseFloat(rowData && rowData.temu_price) || 0;
    }
    /** Invert Full Temu Price back to base, then apply the R Price +$2.99 rule. */
    function temuBaseFromFullPrice(full) {
        const f = parseFloat(full) || 0;
        if (!(f > 0)) return 0;
        const candidates = [(f - 2.99) / TEMU_FULL_PRICE_MULT, f / TEMU_FULL_PRICE_MULT];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            const rebuilt = temuFullPriceFromBase(base);
            const err = Math.abs(rebuilt - f);
            if (err < bestErr - 1e-6) {
                bestErr = err;
                best = base;
            } else if (Math.abs(err - bestErr) <= 1e-6 && base > best) {
                best = base;
            }
        });
        return best;
    }
    /**
     * S R Price — R-price equivalent of S PRC (same rule as Temu R Price).
     * If S PRC = Temu Price (Full) or Temu R Price, S R = Temu R Price.
     */
    function temuSRPriceFromRow(rowData, spriceOverride) {
        const sprice = temuRowSprice(rowData, spriceOverride);
        if (!(sprice > 0)) return 0;
        const rPrice = temuRPriceFromRow(rowData);
        const fullPrice = typeof temuFullPriceFromRow === 'function'
            ? temuFullPriceFromRow(rowData)
            : 0;
        if (fullPrice > 0 && Math.abs(sprice - fullPrice) < 0.02) return rPrice;
        if (rPrice > 0 && Math.abs(sprice - rPrice) < 0.02) return rPrice;
        return temuRPriceFromBase(temuBaseFromFullPrice(sprice));
    }
    const TEMU_FIXED_ADS_PERCENT = 2.2;
    function temuAdsPercentForNet() {
        return TEMU_FIXED_ADS_PERCENT;
    }
    function temuNetPctFromGross(grossPct, ads) {
        if (grossPct == null) return null;
        return ads === 100 ? grossPct : (grossPct - ads);
    }
    /** Pft $ = (Temu R Price × 0.95) − Temu Ship − LP */
    function temuPftDollars(rowData) {
        const rPrice = temuRPriceFromRow(rowData);
        if (!(rPrice > 0)) return null;
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        return (rPrice * 0.95) - ship - lp;
    }
    /** NPFT $ = Gpft − (Temu Price × Ads%) */
    function temuNpftDollars(rowData) {
        const pft = temuPftDollars(rowData);
        if (pft == null) return null;
        const temuPrice = typeof temuFullPriceFromRow === 'function'
            ? temuFullPriceFromRow(rowData)
            : 0;
        const adsPct = temuAdsPercentForNet();
        return pft - (temuPrice * (adsPct / 100));
    }
    /** SPFT $ = (S R Price × 0.95) − Temu Ship − LP */
    function temuSpftDollars(rowData, spriceOverride) {
        const sR = temuSRPriceFromRow(rowData, spriceOverride);
        if (!(sR > 0)) return null;
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        return (sR * 0.95) - ship - lp;
    }
    /** SNPFT $ = SPFT − (S PRC × Ads%) */
    function temuSnpftDollars(rowData, spriceOverride) {
        const spft = temuSpftDollars(rowData, spriceOverride);
        if (spft == null) return null;
        const sprice = temuRowSprice(rowData, spriceOverride);
        const adsPct = temuAdsPercentForNet();
        return spft - ((sprice > 0 ? sprice : 0) * (adsPct / 100));
    }
    /** GROI% = Pft / LP */
    function temuGroiParts(rowData) {
        const rPrice = temuRPriceFromRow(rowData);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        const profit = temuPftDollars(rowData);
        const groi = (profit != null && lp > 0) ? (profit / lp) * 100 : null;
        return { rPrice: rPrice, margin: 0.95, lp: lp, ship: ship, profit: profit, groi: groi };
    }
    /** Full Temu Price from Base: (base × 1.1364), then +$2.99 if ≤ $26.99 */
    function temuFullPriceFromBase(basePrice) {
        const b = parseFloat(basePrice) || 0;
        if (b <= 0) return 0;
        let full = b * TEMU_FULL_PRICE_MULT;
        if (full <= 26.99) full += 2.99;
        return full;
    }
    function temuFullPriceFromRow(rowData) {
        const basePrice = parseFloat(rowData && rowData.base_price) || 0;
        return temuFullPriceFromBase(basePrice);
    }
    // S Recovery rate = 0.88 (S Profit / SROI). S Temu B Prc inverts Temu Price.
    const TEMU_S_RECOVERY_RATE = 0.88;
    function updateTemuRecoveryBadge(salesTotal) {
        const recovery = Math.round((Number(salesTotal) || 0) * TEMU_S_RECOVERY_RATE);
        const $b = $('#total-recovery-badge');
        $b.text('$ ' + recovery.toLocaleString());
        $b.toggleClass('bg-danger', recovery < 0)
          .toggleClass('bg-primary', recovery >= 0);
    }
    function temuSRecovery(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return 0;
        return s * TEMU_S_RECOVERY_RATE;
    }
    function temuRowSprice(rowData, sprice) {
        if (sprice != null && sprice !== '') {
            const n = parseFloat(sprice);
            if (isFinite(n) && n > 0) return n;
        }
        return typeof temuDisplayedSprice === 'function'
            ? (temuDisplayedSprice(rowData) || 0)
            : (parseFloat(rowData && rowData.sprice) || 0);
    }
    /** S Profit = S Recovery × margin − LP − Temu Ship */
    function temuSProfit(rowData, sprice) {
        const recovery = temuSRecovery(temuRowSprice(rowData, sprice));
        if (recovery <= 0) return null;
        const margin = temuSpriceMargin(rowData);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const temuShip = parseFloat(rowData && rowData.temu_ship) || 0;
        return (recovery * margin) - lp - temuShip;
    }
    /** SGPRFT/SPFT profit on full Sprice (not S Recovery). */
    function temuSPftProfit(rowData, sprice) {
        const s = temuRowSprice(rowData, sprice);
        if (s <= 0) return null;
        const margin = temuSpriceMargin(rowData);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const temuShip = parseFloat(rowData && rowData.temu_ship) || 0;
        return (s * margin) - lp - temuShip;
    }
    /** Persist/display SROI / SGPRFT / SNPFT / S Recovery from the same S PRC. */
    function temuSpriceRelatedMetrics(rowData, spriceOverride) {
        const sprice = temuRowSprice(rowData, spriceOverride);
        if (!(sprice > 0)) {
            return {
                s_profit: null,
                s_recovery: null,
                stemu_price: null,
                sroi_percent: null,
                sgroi_percent: null,
                sgprft_percent: null,
                spft_percent: null,
            };
        }
        const sR = temuSRPriceFromRow(rowData, sprice);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const sRProfit = temuSpftDollars(rowData, sprice);
        const snpft = temuSnpftDollars(rowData, sprice);
        const sgroi = (sRProfit != null && lp > 0) ? (sRProfit / lp) * 100 : null;
        const sgprft = (sRProfit != null && sprice > 0) ? (sRProfit / sprice) * 100 : null;
        const snroi = (snpft != null && lp > 0) ? (snpft / lp) * 100 : null;
        const spft = (snpft != null && sprice > 0) ? (snpft / sprice) * 100 : null;
        return {
            s_profit: sRProfit,
            s_recovery: temuSRecovery(sprice),
            stemu_price: temuPushBaseFromSprice(sprice),
            sroi_percent: snroi,
            sgroi_percent: sgroi,
            sgprft_percent: sgprft,
            spft_percent: spft,
        };
    }
    function temuMoneyTxt(n) {
        const v = parseFloat(n);
        if (!isFinite(v)) return '—';
        return (v < 0 ? '-$' : '$') + Math.abs(v).toFixed(2);
    }
    function temuSpriceCalcParts(rowData, spriceOverride) {
        const sprice = temuRowSprice(rowData, spriceOverride);
        const sR = temuSRPriceFromRow(rowData, sprice);
        const margin = 0.95;
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        const sRProfit = temuSpftDollars(rowData, sprice);
        const snpft = temuSnpftDollars(rowData, sprice);
        const sgroi = (sRProfit != null && lp > 0) ? (sRProfit / lp) * 100 : null;
        const sgprft = (sRProfit != null && sprice > 0) ? (sRProfit / sprice) * 100 : null;
        const snroi = (snpft != null && lp > 0) ? (snpft / lp) * 100 : null;
        const spft = (snpft != null && sprice > 0) ? (snpft / sprice) * 100 : null;
        const ads = temuAdsPercentForNet(rowData);
        return {
            sprice: sprice,
            sR: sR,
            margin: margin,
            lp: lp,
            ship: ship,
            sRProfit: sRProfit,
            pftProfit: sRProfit,
            snpft: snpft,
            sgroi: sgroi,
            sroi: snroi,
            snroi: snroi,
            sgprft: sgprft,
            spft: spft,
            ads: ads,
        };
    }
    function temuEscAttr(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }
    function applyTemuSpriceRelatedToRow(row, sprice, saveRes) {
        if (!row || typeof row.getData !== 'function') return;
        const d = row.getData();
        const metrics = temuSpriceRelatedMetrics(d, sprice);
        if (saveRes) {
            if (saveRes.sroi_percent !== undefined) metrics.sroi_percent = saveRes.sroi_percent;
            if (saveRes.sgprft_percent !== undefined) metrics.sgprft_percent = saveRes.sgprft_percent;
            if (saveRes.sgpft_percent !== undefined && metrics.sgprft_percent == null) {
                metrics.sgprft_percent = saveRes.sgpft_percent;
            }
            if (saveRes.spft_percent !== undefined) metrics.spft_percent = saveRes.spft_percent;
        }
        row.update(metrics);
        try { row.reformat(); } catch (e) { /* ignore */ }
    }
    /** Stored in DB table channel_tabulator_column_settings (shared across all users — same pattern as amazon/ebay1/ebay2/ebay3/mfrg tabulators). */
    const TABULATOR_COLUMN_CHANNEL = 'temu_decrease';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    let table = null;

    /**
     * Temu push base from SPRICE — inverse of Temu Price
     *   (Base × 1.1364); +$2.99 if that ≤ $26.99.
     * Shown in "S Temu B Prc"; PFT / ROI stay on stored SPRICE.
     */
    function temuPushBaseFromSprice(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return null;
        const push = temuBaseFromFullPrice(s);
        if (!isFinite(push)) return null;
        return +push.toFixed(2);
    }
    function temuFormatMoney(amount, opts) {
        const n = parseFloat(amount);
        if (!isFinite(n)) return '';
        const bold = !(opts && opts.bold === false);
        const color = n < 0 ? '#dc3545' : (opts && opts.color ? opts.color : '');
        const style = 'font-weight:' + (bold ? '600' : '400') + (color ? ';color:' + color : '');
        return `<span style="${style}">$${n.toFixed(2)}</span>`;
    }
    /**
     * Inverse of temuPushBaseFromSprice — SPRICE that yields target S Temu B Prc.
     * Same as Temu Price from Base.
     */
    function temuSpriceFromPushBase(targetPush) {
        const T = parseFloat(targetPush);
        if (!isFinite(T) || T <= 0) return null;
        const full = temuFullPriceFromBase(T);
        if (!(full > 0)) return null;
        return +full.toFixed(2);
    }
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = true;
    let selectedSkus = new Set();
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'temu'])
    let soldSpriceBlankFilterActive = false;
    let latestAvgViews = 0;

    const linkedSkuAddUrl = @json(route('sku.link.lmp.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('sku.link.lmp.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('sku.link.lmp.linked-skus.remove'));
    const filteredSkusUrl = @json(route('sku.link.lmp.filtered-skus'));
    const skuLinkLmpCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let linkedSkuModal = null;
    let linkedSkuModalRow = null;
    let linkedSkuModalSelectedSkus = new Set();
    let linkedSkuSuggestionTimer = null;
    let linkedSkuSuggestionRequestId = 0;

    function rowSkuForLinkLmp(rowData) {
        return String(rowData?.sku || '').trim();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function escapeHtmlAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    function linkedLmpSkuFormatter(cell) {
        const row = cell.getRow().getData();
        const rowSku = rowSkuForLinkLmp(row);
        let skus = row.linked_lmp_skus || [];
        if (typeof skus === 'string') {
            try { skus = JSON.parse(skus) || []; } catch (e) { skus = []; }
        }
        if (!Array.isArray(skus)) {
            skus = [];
        }
        if (!skus.length && rowSku) {
            skus = [rowSku];
        }

        const seenSkuNorms = new Set();
        skus = skus.filter(function (sku) {
            const norm = String(sku || '').trim().toUpperCase();
            if (!norm || seenSkuNorms.has(norm)) {
                return false;
            }
            seenSkuNorms.add(norm);
            return true;
        });

        const badges = skus.length
            ? skus.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const removeBtn = isSelf
                    ? ''
                    : `<button type="button" class="btn-close sku-link-lmp-remove"
                        data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link to ${escapeHtmlAttr(skuText)}"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                    <span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}
                </span>`;
            }).join('')
            : '<span class="text-muted fst-italic">No SKUs</span>';

        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function linkedLmpSkuAddFormatter(cell) {
        const row = cell.getRow().getData();
        const rowSku = rowSkuForLinkLmp(row);
        if (!rowSku) {
            return '';
        }
        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn"
                title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}">
                <i class="mdi mdi-plus"></i>
            </button>
        </div>`;
    }

    function applyAffectedLinkedSkuRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item.linked_lmp_skus || [];
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            const sku = rowSkuForLinkLmp(data);
            if (!Object.prototype.hasOwnProperty.call(bySku, sku)) {
                return;
            }
            row.update({ linked_lmp_skus: bySku[sku] });
        });

        table.replaceData();
    }

    function removeLinkedSkuFromRow(rowData, linkedSku) {
        const sku = rowSkuForLinkLmp(rowData);
        const target = String(linkedSku || '').trim();
        if (!sku || !target) {
            return;
        }

        if (!confirm(`Remove LMP link between "${sku}" and "${target}"?`)) {
            return;
        }

        fetch(linkedSkuRemoveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': skuLinkLmpCsrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ sku: sku, linked_sku: target }),
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.message || 'Could not remove linked SKU.');
            }
            applyAffectedLinkedSkuRows(response.affected);
        })
        .catch(function (err) {
            alert(err.message || 'Could not remove linked SKU.');
        });
    }

    function updateLinkedSkuSelectedSummary() {
        const wrap = document.getElementById('sku-link-lmp-selected-wrap');
        const listEl = document.getElementById('sku-link-lmp-selected-skus');
        const countEl = document.getElementById('sku-link-lmp-selected-count');
        const saveLabel = document.getElementById('sku-link-lmp-save-btn-label');
        const selected = Array.from(linkedSkuModalSelectedSkus);

        if (countEl) {
            countEl.textContent = String(selected.length);
        }
        if (saveLabel) {
            saveLabel.textContent = selected.length > 1
                ? 'Link ' + selected.length + ' SKUs'
                : 'Link SKU(s)';
        }
        if (!wrap || !listEl) {
            return;
        }

        if (!selected.length) {
            wrap.classList.add('d-none');
            listEl.innerHTML = '';
            return;
        }

        wrap.classList.remove('d-none');
        listEl.innerHTML = selected.map(function (sku) {
            return `<span class="sku-link-lmp-selected-chip">
                ${escapeHtml(sku)}
                <button type="button" class="sku-link-lmp-selected-remove" data-sku="${escapeHtmlAttr(sku)}" title="Remove">&times;</button>
            </span>`;
        }).join('');
    }

    function renderLinkedSkuSuggestions(term) {
        const wrap = document.getElementById('sku-link-lmp-suggestions');
        if (!wrap) {
            return;
        }

        const query = String(term || '').trim();
        if (!query) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            return;
        }

        clearTimeout(linkedSkuSuggestionTimer);
        linkedSkuSuggestionTimer = setTimeout(function () {
            const requestId = ++linkedSkuSuggestionRequestId;
            fetch(`${filteredSkusUrl}?sku=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (requestId !== linkedSkuSuggestionRequestId) {
                    return;
                }
                if (!response.success) {
                    throw new Error(response.message || 'Could not search SKUs.');
                }

                const currentSku = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();
                const existing = new Set(
                    (Array.isArray(linkedSkuModalRow?.linked_lmp_skus) ? linkedSkuModalRow.linked_lmp_skus : [])
                        .map(function (sku) { return String(sku || '').trim().toUpperCase(); })
                );

                const matches = (Array.isArray(response.skus) ? response.skus : [])
                    .map(function (sku) { return String(sku || '').trim(); })
                    .filter(function (sku) {
                        const norm = sku.toUpperCase();
                        return sku && norm !== currentSku && !existing.has(norm);
                    })
                    .slice(0, 12);

                if (!matches.length) {
                    wrap.classList.add('d-none');
                    wrap.innerHTML = '';
                    return;
                }

                wrap.classList.remove('d-none');
                wrap.innerHTML = matches.map(function (sku) {
                    const checked = linkedSkuModalSelectedSkus.has(sku);
                    return `<label class="list-group-item list-group-item-action py-2 sku-link-lmp-suggestion-item d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" class="form-check-input sku-link-lmp-suggestion-cb"
                            value="${escapeHtmlAttr(sku)}" ${checked ? 'checked' : ''}>
                        <span class="flex-grow-1">${escapeHtml(sku)}</span>
                    </label>`;
                }).join('');
            })
            .catch(function () {
                if (requestId !== linkedSkuSuggestionRequestId) {
                    return;
                }
                wrap.classList.add('d-none');
                wrap.innerHTML = '';
            });
        }, 200);
    }

    function getLinkedSkuModalSelections() {
        const selected = Array.from(linkedSkuModalSelectedSkus);
        const inputVal = String(document.getElementById('sku-link-lmp-input')?.value || '').trim();
        const sourceNorm = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();

        if (inputVal && inputVal.toUpperCase() !== sourceNorm) {
            const alreadySelected = selected.some(function (sku) {
                return sku.toUpperCase() === inputVal.toUpperCase();
            });
            if (!alreadySelected) {
                selected.push(inputVal);
            }
        }

        return selected;
    }

    function openLinkedSkuModal(rowData) {
        if (!linkedSkuModal || !rowSkuForLinkLmp(rowData)) {
            return;
        }

        linkedSkuModalRow = rowData;
        linkedSkuModalSelectedSkus = new Set();
        document.getElementById('sku-link-lmp-source').textContent = rowSkuForLinkLmp(rowData);
        const input = document.getElementById('sku-link-lmp-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        updateLinkedSkuSelectedSummary();
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function saveLinkedSkuFromModal() {
        const sourceSku = rowSkuForLinkLmp(linkedSkuModalRow);
        if (!sourceSku) {
            return;
        }

        const toLink = getLinkedSkuModalSelections();
        if (!toLink.length) {
            alert('Select one or more SKUs from the list, or enter a SKU to link.');
            return;
        }

        const allSkus = [sourceSku].concat(toLink);
        const uniqueSkus = [];
        const seen = new Set();
        allSkus.forEach(function (sku) {
            const norm = String(sku || '').trim().toUpperCase();
            if (!norm || seen.has(norm)) {
                return;
            }
            seen.add(norm);
            uniqueSkus.push(String(sku).trim());
        });

        if (uniqueSkus.length < 2) {
            alert('Select at least one SKU to link.');
            return;
        }

        const btn = document.getElementById('sku-link-lmp-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
        }

        const isBulk = uniqueSkus.length > 2 || toLink.length > 1;
        const fetchPromise = isBulk
            ? fetch(linkedSkuBulkLinkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': skuLinkLmpCsrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ skus: uniqueSkus }),
            })
            : fetch(linkedSkuAddUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': skuLinkLmpCsrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    sku: sourceSku,
                    linked_sku: toLink[0],
                }),
            });

        fetchPromise
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.message || 'Could not link SKU(s).');
            }
            linkedSkuModalSelectedSkus = new Set();
            linkedSkuModal?.hide();
            applyAffectedLinkedSkuRows(response.affected);
        })
        .catch(function (err) {
            alert(err.message || 'Could not link SKU(s).');
        })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    }

    function initSkuLinkLmpModal() {
        const skuLinkLmpModalEl = document.getElementById('skuLinkLmpModal');
        if (skuLinkLmpModalEl) {
            linkedSkuModal = bootstrap.Modal.getOrCreateInstance(skuLinkLmpModalEl);
        }

        document.getElementById('sku-link-lmp-input')?.addEventListener('input', function () {
            renderLinkedSkuSuggestions(this.value);
        });

        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('click', function (e) {
            const item = e.target.closest('.sku-link-lmp-suggestion-item');
            if (!item) {
                return;
            }
            const cb = item.querySelector('.sku-link-lmp-suggestion-cb');
            if (!cb || e.target === cb) {
                return;
            }
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        });

        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('change', function (e) {
            const cb = e.target.closest('.sku-link-lmp-suggestion-cb');
            if (!cb) {
                return;
            }
            const sku = String(cb.value || '').trim();
            if (!sku) {
                return;
            }
            if (cb.checked) {
                linkedSkuModalSelectedSkus.add(sku);
            } else {
                linkedSkuModalSelectedSkus.delete(sku);
            }
            updateLinkedSkuSelectedSummary();
        });

        document.getElementById('sku-link-lmp-selected-skus')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sku-link-lmp-selected-remove');
            if (!btn) {
                return;
            }
            linkedSkuModalSelectedSkus.delete(String(btn.dataset.sku || '').trim());
            document.querySelectorAll('.sku-link-lmp-suggestion-cb').forEach(function (cb) {
                if (cb.value === btn.dataset.sku) {
                    cb.checked = false;
                }
            });
            updateLinkedSkuSelectedSummary();
        });

        document.getElementById('sku-link-lmp-save-btn')?.addEventListener('click', function () {
            saveLinkedSkuFromModal();
        });
    }


    // Badge trend chart (same graph as first image)
    let badgeTrendChart = null;
    let badgeChartFirstSeriesStats = null;
    let currentBadgeChartMetricKey = '';
    let currentBadgeChartLabel = '';
    let temuTableBuilt = false;

    function badgeChartValueFmt(metricKey, v) {
        var n = Number(v);
        if (metricKey === 'total_sales' || metricKey === 'total_spend') return '$' + (n % 1 !== 0 ? n.toFixed(2) : Math.round(n).toLocaleString('en-US'));
        if (metricKey === 'avg_cvr_pct') return n.toFixed(2) + '%';
        if (metricKey === 'avg_views') return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
        return Math.round(n).toLocaleString('en-US');
    }

    function initBadgeTrendChart() {
        const canvas = document.getElementById('badgeTrendChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');
        const medianLinePlugin = {
            id: 'badgeMedianLine',
            afterDraw(chart) {
                if (!badgeChartFirstSeriesStats || badgeChartFirstSeriesStats.median === undefined) return;
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const cctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(badgeChartFirstSeriesStats.median);
                cctx.save();
                cctx.setLineDash([6, 4]);
                cctx.strokeStyle = '#6c757d';
                cctx.lineWidth = 1.2;
                cctx.beginPath();
                cctx.moveTo(xScale.left, yPixel);
                cctx.lineTo(xScale.right, yPixel);
                cctx.stroke();
                cctx.restore();
            }
        };
        const valueLabelsPlugin = {
            id: 'badgeValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 7px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                const valueFmt = (badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.valueFmt) ? badgeChartFirstSeriesStats.valueFmt : function(v) { return badgeChartValueFmt(currentBadgeChartMetricKey, v); };
                const labelColors = badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.labelColors ? badgeChartFirstSeriesStats.labelColors : [];
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    if (val == null) return;
                    const offsetY = (i % 2 === 0) ? -7 : -14;
                    cctx.fillStyle = labelColors[i] || '#6c757d';
                    cctx.fillText(valueFmt(val), point.x, point.y + offsetY);
                });
                cctx.restore();
            }
        };
        badgeTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Value',
                    data: [],
                    borderColor: '#0dcaf0',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    borderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    tension: 0.3,
                    fill: true,
                    spanGaps: true
                }]
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const v = context.parsed.y;
                                if (v == null) return '';
                                return (badgeChartFirstSeriesStats && badgeChartFirstSeriesStats.valueFmt ? badgeChartFirstSeriesStats.valueFmt(v) : badgeChartValueFmt(currentBadgeChartMetricKey, v));
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: { font: { size: 9 }, callback: function(v) {
                            return badgeChartValueFmt(currentBadgeChartMetricKey, v);
                        } }
                    }
                }
            }
        });
    }

    function loadBadgeChartData(metricKey, metricLabel, days) {
        currentBadgeChartMetricKey = metricKey || currentBadgeChartMetricKey;
        currentBadgeChartLabel = metricLabel || currentBadgeChartLabel;
        days = days || parseInt($('#badgeTrendChartDays').val(), 10) || 30;
        $('#badgeTrendChartLoading').show();
        $('#badgeTrendChartContainer').hide();
        $('#badgeTrendChartNoData').hide();
        fetch('/temu-badge-history?days=' + encodeURIComponent(days))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                $('#badgeTrendChartLoading').hide();
                if (!badgeTrendChart) return;
                var data = res.data || [];
                var key = currentBadgeChartMetricKey;
                if (!data.length) {
                    badgeChartFirstSeriesStats = null;
                    $('#badgeTrendChartHigh, #badgeTrendChartMed, #badgeTrendChartLow').text('-');
                    badgeTrendChart.data.labels = [];
                    badgeTrendChart.data.datasets[0].data = [];
                    badgeTrendChart.update('active');
                    $('#badgeTrendChartContainer').hide();
                    $('#badgeTrendChartNoData').show();
                    return;
                }
                $('#badgeTrendChartNoData').hide();
                $('#badgeTrendChartContainer').show();
                var labels = data.map(function(d) { return d.record_date; });
                var values = data.map(function(d) { return Number(d[key]) || 0; });
                var refFmt = function(v) { return badgeChartValueFmt(key, v); };
                function statsForArr(arr) {
                    var valid = arr.filter(function(v) { return v != null && !isNaN(v); });
                    if (valid.length === 0) return { min: 0, max: 0, median: 0 };
                    var min = Math.min.apply(null, valid);
                    var max = Math.max.apply(null, valid);
                    var sorted = valid.slice().sort(function(a, b) { return a - b; });
                    var mid = Math.floor(sorted.length / 2);
                    var median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                    return { min: min, max: max, median: median };
                }
                var s0 = statsForArr(values);
                var refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                $('#badgeTrendChartHigh').text(refFmt(s0.max)).css('color', refRed);
                $('#badgeTrendChartMed').text(refFmt(s0.median)).css('color', refGray);
                $('#badgeTrendChartLow').text(refFmt(s0.min)).css('color', refGreen);
                $('#badgeTrendChartRefLabel').text(currentBadgeChartLabel);
                var dotColors = values.map(function(v, i) {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                });
                var labelColors = values.map(function(v) { return v === 0 ? refGreen : v > 0 ? refRed : refGray; });
                badgeChartFirstSeriesStats = { values: values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors: dotColors, labelColors: labelColors, valueFmt: refFmt };
                badgeTrendChart.data.labels = labels;
                badgeTrendChart.data.datasets[0].data = values;
                badgeTrendChart.data.datasets[0].pointBackgroundColor = dotColors;
                badgeTrendChart.data.datasets[0].pointBorderColor = dotColors;
                badgeTrendChart.data.datasets[0].pointBorderWidth = 1.5;
                var range = (s0.max - s0.min) || Math.max(Math.abs(s0.min) * 0.1, 1);
                if (badgeTrendChart.options.scales && badgeTrendChart.options.scales.y) {
                    badgeTrendChart.options.scales.y.min = Math.max(0, s0.min - range * 0.1);
                    badgeTrendChart.options.scales.y.max = s0.max + range * 0.1;
                }
                badgeTrendChart.update('active');
            })
            .catch(function() {
                $('#badgeTrendChartLoading').hide();
                badgeChartFirstSeriesStats = null;
                $('#badgeTrendChartHigh, #badgeTrendChartMed, #badgeTrendChartLow').text('-');
                $('#badgeTrendChartContainer').hide();
                $('#badgeTrendChartNoData').show();
            });
    }

    // Average Views chart
    let avgViewsChart = null;

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function temu1StdPrcChangeDotMeta(stdPrc, comparePrice) {
        const sp = parseFloat(stdPrc);
        const ap = parseFloat(comparePrice);
        if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
        const sp2 = sp.toFixed(2);
        const ap2 = ap.toFixed(2);
        if (parseFloat(sp2) < parseFloat(ap2)) {
            return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs Amz price' };
        }
        if (parseFloat(sp2) > parseFloat(ap2)) {
            return { kind: 'increase', color: '#28a745', title: 'Increase vs Amz price' };
        }
        return null;
    }

    function temu1StdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = temu1StdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyTemu1StandardPriceToLinkedRows(sku, std, appliedSkus) {
        if (typeof table === 'undefined' || !table) return null;
        const target = String(sku || '').trim().toUpperCase();
        const appliedSet = new Set(
            (Array.isArray(appliedSkus) ? appliedSkus : [])
                .map(function(s) { return String(s || '').trim().toUpperCase(); })
                .filter(Boolean)
        );
        if (target) appliedSet.add(target);
        let primaryRow = null;
        (table.getRows('all') || table.getRows() || []).forEach(function(r) {
            const d = r.getData();
            if (!d || d.is_parent) return;
            const rowSku = String(d.sku || d['(Child) sku'] || d.SKU || '').trim();
            if (!rowSku) return;
            const rowKey = rowSku.toUpperCase();
            const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
            const inGroup = appliedSet.has(rowKey)
                || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                || (target && rowKey === target);
            if (!inGroup) return;
            r.update({ STANDARD_PRICE: std });
            if (rowKey === target) primaryRow = r;
        });
        return primaryRow;
    }

    document.addEventListener('lmp-modal-sp-saved', function(e) {
        const detail = (e && e.detail) || {};
        const sku = detail.sku;
        const saved = parseFloat(detail.standard_price);
        if (!sku || !isFinite(saved) || saved <= 0) return;
        applyTemu1StandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function initAvgViewsChart() {
        const canvas = document.getElementById('avgViewsChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');

        const avgViewsValueLabelsPlugin = {
            id: 'avgViewsValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 11px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                cctx.fillStyle = '#28a745';
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    if (val != null && val !== '') cctx.fillText(Math.round(val), point.x, point.y - 8);
                });
                cctx.restore();
            }
        };

        avgViewsChart = new Chart(ctx, {
            type: 'line',
            plugins: [avgViewsValueLabelsPlugin],
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Average Views',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Average Views Trend',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Average Views: ' + Math.round(context.parsed.y);
                            },
                            afterLabel: function(context) {
                                const dataIndex = context.dataIndex;
                                const dataset = avgViewsChart.data.datasets[0];
                                if (dataset.totalProducts && dataset.totalProducts[dataIndex]) {
                                    return 'Products: ' + dataset.totalProducts[dataIndex];
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Average Views',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: function(value) {
                                return Math.round(value);
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    }

    function loadAvgViewsHistory(days = 30) {
        fetch(`/temu-avg-views-history?days=${days}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (avgViewsChart) {
                    if (!data || data.length === 0) {
                        $('#avg-views-no-data-message').show();
                        avgViewsChart.data.labels = [];
                        avgViewsChart.data.datasets[0].data = [];
                        avgViewsChart.update();
                        return;
                    }
                    
                    $('#avg-views-no-data-message').hide();
                    
                    avgViewsChart.data.labels = data.map(d => d.date);
                    avgViewsChart.data.datasets[0].data = data.map(d => parseFloat(d.avg_views));
                    
                    // Store additional data for tooltip
                    avgViewsChart.data.datasets[0].totalProducts = data.map(d => d.total_products);
                    
                    avgViewsChart.update();
                }
            })
            .catch(error => {
                console.error('Error loading average views history:', error);
                showToast('Failed to load average views history', 'error');
            });
    }

    function storeDailyAvgViews() {
        const data = table.getData('active');
        
        if (!data || data.length === 0) {
            showToast('No data available to calculate average', 'error');
            return;
        }
        
        const totalViews = data.reduce((sum, row) => sum + (parseInt(row['product_clicks']) || 0), 0);
        const totalProducts = data.length;
        const avgViews = totalViews / totalProducts;
        
        $.ajax({
            url: '/temu-store-daily-avg-views',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                avg_views: avgViews,
                total_products: totalProducts,
                total_views: totalViews
            },
            success: function(response) {
                if (response.success) {
                    showToast(`Daily average views stored successfully (${Math.round(avgViews)} avg)`, 'success');
                    // Update the latest avg views for filtering
                    latestAvgViews = avgViews;
                } else {
                    showToast('Failed to store daily average views', 'error');
                }
            },
            error: function(xhr) {
                showToast('Failed to store daily average views', 'error');
            }
        });
    }

    function autoStoreDailyAvgViews() {
        // Check if today's record already exists
        fetch('/temu-latest-avg-views')
            .then(response => {
                if (!response.ok) {
                    // If table doesn't exist or server error, silently fail
                    return response.json().catch(() => ({ avg_views: 0 }));
                }
                return response.json();
            })
            .then(data => {
                const today = new Date().toISOString().split('T')[0];
                const latestDate = data && data.date ? data.date : null;
                
                // If no record for today, store it automatically
                if (latestDate !== today) {
                    const tableData = table.getData('active');
                    
                    if (tableData && tableData.length > 0) {
                        const totalViews = tableData.reduce((sum, row) => sum + (parseInt(row['product_clicks']) || 0), 0);
                        const totalProducts = tableData.length;
                        const avgViews = totalViews / totalProducts;
                        
                        $.ajax({
                            url: '/temu-store-daily-avg-views',
                            method: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                avg_views: avgViews,
                                total_products: totalProducts,
                                total_views: totalViews
                            },
                            success: function(response) {
                                if (response.success) {
                                    console.log(`Auto-stored daily average: ${Math.round(avgViews)} views`);
                                    latestAvgViews = avgViews;
                                }
                            },
                            error: function(xhr) {
                                // Silently fail - table might not exist
                                // Don't show error to user as this is a background operation
                                if (xhr.status !== 500) {
                                    console.error('Failed to auto-store daily average views');
                                }
                            }
                        });
                    }
                } else {
                    // Update the latest avg for filtering
                    if (data && data.avg_views) {
                        latestAvgViews = parseFloat(data.avg_views);
                    }
                }
            })
            .catch(error => {
                // Silently fail - table might not exist
                // This is a background operation, don't show errors to user
            });
    }

    function loadLatestAvgViews() {
        fetch('/temu-latest-avg-views')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.avg_views) {
                    latestAvgViews = parseFloat(data.avg_views);
                }
            })
            .catch(error => {
                console.error('Error loading latest average views:', error);
            });
    }

    $(document).ready(function() {
        try { initSkuLinkLmpModal(); } catch (e) { console.error('Temu decrease: SKU link modal init failed', e); }
        try { initBadgeTrendChart(); } catch (e) { console.error('Temu decrease: badge chart init failed', e); }
        try { initAvgViewsChart(); } catch (e) { console.error('Temu decrease: avg views chart init failed', e); }
        try { loadLatestAvgViews(); } catch (e) { console.error('Temu decrease: latest avg views failed', e); }

        // Average Views chart days filter
        $('#avg-views-days-filter').on('change', function() {
            const days = $(this).val();
            loadAvgViewsHistory(days);
        });

        $(document).on('click', '.copy-goods-id', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const goodsId = ($(this).data('goods-id') || '').toString();
            if (!goodsId) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(goodsId).then(function() {
                    if (typeof showToast === 'function') showToast('Goods ID copied', 'success');
                }).catch(function() {
                    if (typeof showToast === 'function') showToast('Failed to copy Goods ID', 'error');
                });
                return;
            }

            const tempInput = document.createElement('input');
            tempInput.value = goodsId;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            if (typeof showToast === 'function') showToast('Goods ID copied', 'success');
        });

        function syncDiscountInputUi() {
            const $input = $('#discount-percentage-input');
            $('#discount-type-select-wrap').addClass('d-none');
            $('#discount-input-label').removeClass('d-none');
            $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
            $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
        }

        function temuSkuFromCheckbox(el) {
            return String($(el).attr('data-sku') || '').trim();
        }

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            if (!table) return;

            temuCurrentPageSkuRows().forEach(function(row) {
                const sku = String((row.getData() || {}).sku || '').trim();
                if (!sku) return;
                if (isChecked) selectedSkus.add(sku);
                else selectedSkus.delete(sku);
            });

            $('.sku-select-checkbox').each(function() {
                const sku = temuSkuFromCheckbox(this);
                $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
            });
            $(this).prop('indeterminate', false);
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = temuSkuFromCheckbox(this);
            if (!sku) return;
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        $('#clear-sprice-btn').on('click', function() {
            if (confirm('Are you sure you want to clear all SPRICE data? This action cannot be undone.')) {
                clearAllSprice();
            }
        });

        $('#sprc-26-99-btn').on('click', function() {
            applySprice2699();
        });

        $('#apply-sprice-from-std-btn').on('click', function() {
            applySpriceFromStdPrmtCvr();
        });

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        /*
         * ============================================================================
         * Target ROI% / Target GPFT% bulk apply for SPRICE  (Temu Decrease)
         * ----------------------------------------------------------------------------
         * Pick rows, type a target %, click Apply SPRICE → back-solve the desired
         * S Temu Price (the displayed selling price) so the on-page SROI / SGPRFT
         * column equals the target after Temu margin + temu_ship are paid out, then
         * derive what `sprice` to store using the inverse of the backend's $2.99
         * ship bumper:
         *
         *   - target S Temu Price ≤ 29.98  →  sprice = stemuPrice − 2.99
         *     (backend adds the 2.99 back; final stemuPrice matches the target,
         *      so SROI / SGPRFT land exactly on the target value)
         *   - target S Temu Price >  29.98  →  sprice = stemuPrice  (no bumper)
         *
         * Backend math (mirrors saveTemuSprice):
         *   S Recovery = sprice × 0.88
         *   S Profit   = (S Recovery × margin − lp − temu_ship)
         *   SROI%      = S Profit / lp * 100
         *      -> sprice = (lp * (1 + roi%/100) + temu_ship) / (0.88 × margin)
         *   SGPRFT%    = (Full Sprice × margin − lp − temu_ship) / Full Sprice * 100
         *      -> sprice = (lp + temu_ship) / (margin − gpft%/100)
         *   margin = marketplace_percentages.Temu (TEMU_MARGIN / row.percentage).
         *
         * All POSTs go through the existing /temu-pricing/save-sprice endpoint so
         * SGPRFT / SROI get recomputed server-side exactly like an inline SPRICE
         * edit. Selection is cleared after each batch so the next run starts fresh.
         * ============================================================================
         */
        // Fallback when a row is missing percentage — page-level TEMU_MARGIN from marketplace_percentages
        const TEMU_MARGIN_FALLBACK = TEMU_MARGIN;

        // Inverse of the backend's `stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice`
        // transformation: returns the sprice that would produce `desiredStemuPrice`
        // after the backend applies its bumper. Used so the SPRICE column ends up at
        // `target − 2.99` for low targets and the displayed S Temu Price lands exactly
        // on the target.
        function temuStemuPriceToSprice(desiredStemuPrice) {
            if (!isFinite(desiredStemuPrice) || desiredStemuPrice <= 0) return null;
            if (desiredStemuPrice <= 29.98) {
                const sprice = +(desiredStemuPrice - 2.99).toFixed(2);
                if (sprice <= 0) return null;
                return sprice;
            }
            return +desiredStemuPrice.toFixed(2);
        }

        /**
         * Green Alert rule — flag rows where the live Temu price sits below the
         * comparison prices on Amazon / eBay 1 / eBay 2 (Temu is the cheaper offer).
         * Used both by the Temu Price column formatter (to color the cell green) and
         * the Green Alert toolbar badge filter so the two surfaces never disagree.
         *
         *   Green when:  temuPrice  <  Amazon × 0.85
         *           OR   temuPrice  <  eBay 1 × 0.90
         *           OR   temuPrice  <  eBay 2 × 0.90
         *
         * `temuPrice` mirrors the same $2.99-bumper rule used elsewhere in this view
         * (basePrice ≤ 26.99 ? basePrice + 2.99 : basePrice). Rows without a base
         * price, or without any Amazon / eBay reference price to compare against,
         * are never green.
         */
        function temuIsGreenAlert(rd) {
            if (!rd) return false;
            const base = parseFloat(rd['base_price']) || 0;
            if (base <= 0) return false;
            const temuPrice = base <= 26.99 ? base + 2.99 : base;
            const amz = parseFloat(rd['a_price']) || 0;
            const e   = parseFloat(rd['e_price']) || 0;
            const e2  = parseFloat(rd['e2_price']) || 0;
            const underAmz = amz > 0 && temuPrice < amz * 0.85;
            const underE   = e   > 0 && temuPrice < e   * 0.90;
            const underE2  = e2  > 0 && temuPrice < e2  * 0.90;
            return underAmz || underE || underE2;
        }

        /**
         * Red Alert rule — opposite of Green: Temu is uncompetitive vs every reference
         * channel that has a price (no competitor is cheaper than threshold). Flags
         * rows where Temu is sitting at or above all of:
         *
         *   Red when:  (amz=0  OR  temuPrice >= amz × 0.85)
         *         AND  (e =0  OR  temuPrice >= e   × 0.90)
         *         AND  (e2=0  OR  temuPrice >= e2  × 0.90)
         *         AND  at least one of {amz, e, e2} > 0
         *
         * The "at least one reference price" guard prevents rows with no comparison
         * data from being flagged (we just don't know in that case). Mutually
         * exclusive with the Green Alert by construction, so the cell color and the
         * two badges can never both fire on the same row.
         */
        function temuIsRedAlert(rd) {
            if (!rd) return false;
            const base = parseFloat(rd['base_price']) || 0;
            if (base <= 0) return false;
            const temuPrice = base <= 26.99 ? base + 2.99 : base;
            const amz = parseFloat(rd['a_price']) || 0;
            const e   = parseFloat(rd['e_price']) || 0;
            const e2  = parseFloat(rd['e2_price']) || 0;
            const anyRef = amz > 0 || e > 0 || e2 > 0;
            if (!anyRef) return false;
            const okAmz = amz === 0 || temuPrice >= amz * 0.85;
            const okE   = e   === 0 || temuPrice >= e   * 0.90;
            const okE2  = e2  === 0 || temuPrice >= e2  * 0.90;
            return okAmz && okE && okE2;
        }

        // Reveal the row-select checkbox column on demand. It's `visible: false` in the
        // Tabulator config and the INC/DEC button is what normally shows it — but the
        // Target ROI% / Target GPFT% flow needs the same checkboxes, so we call this from
        // input focus + the Apply handler to make sure users can actually pick rows.
        function temuEnsureSelectColumnVisible() {
            if (!table) return;
            const selectCol = table.getColumn('_select');
            if (selectCol && !selectCol.isVisible()) {
                selectCol.show();
            }
        }

        // Show the select column as soon as the user interacts with either Target input
        // (clicking, focusing, or typing), so checkboxes are already visible by the time
        // they want to pick rows.
        $('#target-roi-input, #target-gpft-input').on('focus click input', temuEnsureSelectColumnVisible);


        function temuApplyTargetSpriceBatch(opts) {
            // opts: { label, $btn, btnHtml, computeStemuPrice(rd) -> {stemuPrice|sprice, skipReason?} }
            //   Prefer `sprice` when provided (SGPFT uses Sprice×marketplace%, no FB bumper).
            //   Otherwise `stemuPrice` is the desired displayed S Temu Price; it runs
            //   through temuStemuPriceToSprice(...) so we store sprice = stemu − 2.99
            //   for low prices (≤ 29.98) — used by Target ROI which still uses stemu.
            const $btn = opts.$btn;

            // Safety net: ensure the select column is visible even if the focus listeners
            // above didn't fire (e.g. button focused programmatically).
            temuEnsureSelectColumnVisible();

            if (typeof selectedSkus === 'undefined' || selectedSkus.size === 0) {
                showToast('Tick the checkboxes on the left to select SKUs first', 'error');
                return;
            }

            const rowsToProcess = [];
            const skipped = [];
            table.getRows().forEach(function(r) {
                const rd = r.getData();
                const sku = rd['sku'];
                if (!sku || !selectedSkus.has(sku)) return;
                const res = opts.computeStemuPrice(rd);
                if (!res || res.skipReason) {
                    if (res && res.skipReason) skipped.push({ sku: sku, reason: res.skipReason });
                    return;
                }
                let sprice = null;
                if (res.sprice != null && isFinite(res.sprice)) {
                    sprice = +Number(res.sprice).toFixed(2);
                } else {
                    sprice = temuStemuPriceToSprice(res.stemuPrice);
                }
                if (sprice == null || !isFinite(sprice) || sprice <= 0) return;
                sprice = temuPrepareSpriceForSave(rd, sprice);
                rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
            });

            if (rowsToProcess.length === 0) {
                if (skipped.length > 0) {
                    showToast(`Cannot apply: ${skipped[0].reason}`, 'error');
                } else {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                }
                return;
            }

            let confirmMsg = `Compute & save SPRICE for ${rowsToProcess.length} selected SKU(s) using ${opts.label}?`;
            if (skipped.length > 0) {
                confirmMsg += `\n\nNote: ${skipped.length} row(s) will be skipped (${skipped[0].reason}).`;
            }
            if (!confirm(confirmMsg)) return;

            let successCount = 0;
            let errorCount = 0;
            const total = rowsToProcess.length;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

            rowsToProcess.forEach(function(item) {
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        sku: item.sku,
                        sprice: item.sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        successCount++;
                        // Field names match the existing saveSpriceWithRetry update payload.
                        item.row.update({
                            sprice: item.sprice,
                            sgprft_percent: response.sgprft_percent,
                            sroi_percent: response.sroi_percent,
                            sprice_status: 'saved'
                        });
                        item.row.reformat();
                    },
                    error: function() { errorCount++; },
                    complete: function() {
                        if (successCount + errorCount === total) {
                            $btn.prop('disabled', false).html(opts.btnHtml);
                            if (errorCount === 0) {
                                showToast(`SPRICE saved for ${successCount} SKU(s) @ ${opts.label}`, 'success');
                            } else {
                                showToast(`Saved ${successCount} of ${total} (${errorCount} failed)`, 'error');
                            }
                            // Wipe selection so the next batch starts clean.
                            selectedSkus.clear();
                            $('.sku-select-checkbox').prop('checked', false);
                            $('#select-all-checkbox').prop('checked', false);
                            if (typeof updateSelectedCount === 'function') {
                                updateSelectedCount();
                            }
                        }
                    }
                });
            });
        }

        // Target ROI%
        $('#apply-target-roi-btn').on('click', function() {
            const $btn = $(this);
            const raw = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(raw).replace(',', '.'));
            if (raw === '' || raw == null) { showToast('Please enter a Target ROI%', 'error'); return; }
            if (!isFinite(targetRoiPct)) { showToast('Target ROI% must be a number', 'error'); return; }
            const roiMultiplier = 1 + (targetRoiPct / 100);
            temuApplyTargetSpriceBatch({
                label: `Target ROI ${targetRoiPct}%`,
                $btn: $btn,
                // Icon-only — matches the doba "Apply" chip; aria-label on the
                // <button> in HTML keeps screen readers informed.
                btnHtml: '<i class="fas fa-calculator"></i>',
                computeStemuPrice: function(rd) {
                    const lp = parseFloat(rd.lp) || 0;
                    if (lp <= 0) return null;
                    const temuShip = parseFloat(rd.temu_ship) || 0;
                    const margin = temuSpriceMargin(rd);
                    // SROI = S Profit/LP; S Profit = (S Recovery × margin) − ship − LP
                    // S Recovery = Sprice × 0.88
                    // → Sprice = (LP × (1 + ROI%/100) + ship) / (0.88 × margin)
                    return { sprice: (lp * roiMultiplier + temuShip) / (TEMU_S_RECOVERY_RATE * margin) };
                }
            });
        });
        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });

        // Target GPFT% — SGPRFT on Full Sprice = ((Sprice × margin − ship − LP) / Sprice) × 100
        // → Sprice = (LP + ship) / (margin − GPFT%/100)
        $('#apply-target-gpft-btn').on('click', function() {
            const $btn = $(this);
            const raw = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
            if (raw === '' || raw == null) { showToast('Please enter a Target GPFT%', 'error'); return; }
            if (!isFinite(targetGpftPct)) { showToast('Target GPFT% must be a number', 'error'); return; }
            const targetFraction = targetGpftPct / 100;
            temuApplyTargetSpriceBatch({
                label: `Target GPFT ${targetGpftPct}%`,
                $btn: $btn,
                // Icon-only — see note in the ROI handler above.
                btnHtml: '<i class="fas fa-calculator"></i>',
                computeStemuPrice: function(rd) {
                    const lp = parseFloat(rd.lp) || 0;
                    if (lp <= 0) return null;
                    const temuShip = parseFloat(rd.temu_ship) || 0;
                    const margin = temuSpriceMargin(rd);
                    const denom = margin - targetFraction;
                    if (denom <= 0) {
                        return { skipReason: `Target GPFT% ${targetGpftPct}% ≥ ${(margin * 100).toFixed(0)}% (marketplace take-home)` };
                    }
                    return { sprice: (lp + temuShip) / denom };
                }
            });
        });
        $('#target-gpft-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Badge click handlers for filtering
        // zeroSoldFilterActive removed — Sold filter is now owned by the #sold-filter
        // dropdown (which the 0 Sold badge below just toggles).
        let lessAmzFilterActive = false;
        let moreAmzFilterActive = false;
        let spriceLmpAlertFilterActive = false;
        let blueTriangleFilterActive = false;
        let amzCapFilterActive = false;
        let ebCapFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;

        // 0 Sold badge just toggles the #sold-filter dropdown so the dropdown stays the
        // single source of truth (mirrors Amazon tabulator). Click again to clear.
        $('#zero-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });

        $('#temu-sprice-lmp-alert-badge').on('click', function() {
            spriceLmpAlertFilterActive = !spriceLmpAlertFilterActive;
            if (spriceLmpAlertFilterActive && typeof temuClearSpriceLmpAlertCompetingFilters === 'function') {
                temuClearSpriceLmpAlertCompetingFilters();
            }
            $(this).css('outline', spriceLmpAlertFilterActive ? '3px solid #ffc107' : '');
            $(this).css('outline-offset', spriceLmpAlertFilterActive ? '2px' : '');
            if (spriceLmpAlertFilterActive && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                stopPlayNavigation();
                return;
            }
            applyFilters();
        });
        $('#temu-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive && typeof temuClearBlueTriangleCompetingFilters === 'function') {
                temuClearBlueTriangleCompetingFilters();
            }
            $(this).css('outline', blueTriangleFilterActive ? '3px solid #ffc107' : '');
            $(this).css('outline-offset', blueTriangleFilterActive ? '2px' : '');
            if (blueTriangleFilterActive && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                stopPlayNavigation();
                return;
            }
            applyFilters();
        });
        $('#temu-amz-cap-badge').on('click', function() {
            amzCapFilterActive = !amzCapFilterActive;
            if (amzCapFilterActive) {
                ebCapFilterActive = false;
                if (typeof temuClearCapBadgeCompetingFilters === 'function') temuClearCapBadgeCompetingFilters();
            }
            $(this).css('outline', amzCapFilterActive ? '3px solid #ffc107' : '');
            $(this).css('outline-offset', amzCapFilterActive ? '2px' : '');
            $('#temu-eb-cap-badge').css({ outline: '', outlineOffset: '' });
            if (amzCapFilterActive && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                stopPlayNavigation();
                return;
            }
            applyFilters();
        });
        $('#temu-eb-cap-badge').on('click', function() {
            ebCapFilterActive = !ebCapFilterActive;
            if (ebCapFilterActive) {
                amzCapFilterActive = false;
                if (typeof temuClearCapBadgeCompetingFilters === 'function') temuClearCapBadgeCompetingFilters();
            }
            $(this).css('outline', ebCapFilterActive ? '3px solid #ffc107' : '');
            $(this).css('outline-offset', ebCapFilterActive ? '2px' : '');
            $('#temu-amz-cap-badge').css({ outline: '', outlineOffset: '' });
            if (ebCapFilterActive && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                stopPlayNavigation();
                return;
            }
            applyFilters();
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').show();
        }

        /** Current pagination page SKU rows only (not the full filtered set). */
        function temuCurrentPageSkuRows() {
            if (!table) return [];
            let allActive = [];
            try {
                allActive = (table.getRows('active') || []).filter(function(row) {
                    const d = row.getData() || {};
                    if (typeof isTemuParentRow === 'function' && isTemuParentRow(d)) return false;
                    return !!d.sku;
                });
            } catch (e) {
                return [];
            }
            let pageSize = (typeof table.getPageSize === 'function' ? table.getPageSize() : 0) || 100;
            const currentPage = (typeof table.getPage === 'function' ? table.getPage() : 1) || 1;
            if (pageSize === true || pageSize === 'true') return allActive;
            pageSize = Number(pageSize) || 100;
            if (pageSize >= allActive.length && allActive.length > 0) return allActive;
            const start = (currentPage - 1) * pageSize;
            return allActive.slice(start, start + pageSize);
        }

        function updateSelectAllCheckbox() {
            if (!table) {
                $('#select-all-checkbox').prop('checked', false).prop('indeterminate', false);
                return;
            }
            const pageRows = temuCurrentPageSkuRows();
            if (pageRows.length === 0) {
                $('#select-all-checkbox').prop('checked', false).prop('indeterminate', false);
                return;
            }

            let selectedCount = 0;
            pageRows.forEach(function(row) {
                const sku = String((row.getData() || {}).sku || '').trim();
                if (sku && selectedSkus.has(sku)) selectedCount++;
            });

            if (selectedCount === 0) {
                $('#select-all-checkbox').prop('checked', false).prop('indeterminate', false);
            } else if (selectedCount === pageRows.length) {
                $('#select-all-checkbox').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#select-all-checkbox').prop('checked', false).prop('indeterminate', true);
            }
        }

        function roundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }
        function roundToRetailPrice49(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                const rowData = row && typeof row.getData === 'function' ? row.getData() : (row || {});
                sprice = temuPrepareSpriceForSave(rowData, sprice);
                if (row && typeof row.update === 'function') {
                    row.update({ sprice: sprice, sprice_status: 'processing' });
                }
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const newPriceNum = typeof sprice === 'number' ? sprice : parseFloat(sprice);
                        let targetRow = row;
                        if (table) {
                            const found = table.getRows().find(r => (r.getData().sku || '') === sku);
                            if (found) targetRow = found;
                        }
                        if (targetRow) {
                            targetRow.update({
                                sprice: newPriceNum,
                                sgprft_percent: response.sgprft_percent,
                                sroi_percent: response.sroi_percent,
                                sprice_status: 'saved'
                            });
                            targetRow.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        
                        if (retryCount < 1) {
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            if (row) {
                                row.update({ sprice_status: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

        function applyDiscount() {
            const rawInput = $('#discount-percentage-input').val();
            const discountValue = parseFloat(String(rawInput).replace(',', '.')) || 0;
            const discountType = $('#discount-type-select').val();

            if (isNaN(discountValue) || discountValue <= 0) {
                showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid discount value', 'error');
                return;
            }
            if (!samePriceModeActive && discountType === 'percentage' && discountValue > 100 && !increaseModeActive) {
                showToast('Discount percentage cannot exceed 100%', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;

            allData.forEach(row => {
                const sku = row['sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['base_price']) || 0;
                    // Same Price applies even when base_price is empty;
                    // Decrease / Increase modes still need a positive base price to compute.
                    if (samePriceModeActive || currentPrice > 0) {
                        let newSPrice;

                        if (samePriceModeActive) {
                            // The ONE price the user typed, applied verbatim to every selected SKU.
                            newSPrice = Math.max(0.01, discountValue);
                        } else if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSPrice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSPrice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSPrice = currentPrice + discountValue;
                            } else {
                                newSPrice = currentPrice - discountValue;
                            }
                        }

                        newSPrice = Math.max(0.01, newSPrice);
                        const originalPrice = currentPrice;
                        newSPrice = roundToRetailPrice(newSPrice);
                        // Only auto-bump to .49 when the computed price equals the source price
                        // (avoids producing a blank SPRC after Decrease/Increase). Same Price honors
                        // the typed value exactly (rounded by retail .99 only when >= $20.99).
                        if (!samePriceModeActive && newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                            newSPrice = roundToRetailPrice49(newSPrice);
                        }
                        const newPriceNum = parseFloat(newSPrice.toFixed(2));
                        
                        const originalSPrice = parseFloat(row['sprice']) || 0;
                        
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['sku'] === sku;
                        });
                        
                        if (tableRow) {
                            tableRow.update({ 
                                sprice: newPriceNum,
                                sprice_status: 'processing'
                            });
                            tableRow.reformat();
                        }
                        
                        const actionLabel = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Discount');
                        saveSpriceWithRetry(sku, newPriceNum, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(`${actionLabel} applied to ${updatedCount} SKU(s)`, 'success');
                                    } else {
                                        showToast(`${actionLabel} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ sprice: originalSPrice });
                                    tableRow.reformat();
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(`${actionLabel} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                }
                            });
                    }
                }
            });
            
            $('#discount-percentage-input').val('');
        }

        /** L1 = lowest non-ignored Price+D (same as LMP column). */
        function getRowLmpL1(row) {
            if (!row) return null;
            const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
            const prices = entries
                .filter(function(e) { return !e.ignored; })
                .map(function(e) {
                    const p = e.price;
                    if (p === null || p === undefined || p === '' || isNaN(parseFloat(p))) return null;
                    const base = parseFloat(p);
                    const d = parseFloat(e.delivery);
                    let delivery = (!isNaN(d) && d > 0) ? d : 0;
                    if (delivery <= 0 && base < 27) delivery = 2.99;
                    return base + delivery;
                })
                .filter(function(p) { return p !== null; });
            if (prices.length > 0) return Math.min.apply(null, prices);
            const fallback = parseFloat(row.lmp_raw != null ? row.lmp_raw : row.lmp);
            return (!isNaN(fallback) && fallback > 0) ? fallback : null;
        }

        function temuRawSprice(row) {
            const n = parseFloat(row && row.sprice);
            if (isFinite(n) && n > 0) return n;
            return typeof temuSpriceFromStdPrmtCvr === 'function'
                ? (temuSpriceFromStdPrmtCvr(row) || 0)
                : 0;
        }

        function temuAmzRefPrice(row) {
            const n = parseFloat(row && (row.a_price != null ? row.a_price : (row['A Price'] != null ? row['A Price'] : row.amazon_price)));
            return (isFinite(n) && n > 0) ? n : 0;
        }

        function temuEbayRefPrice(row) {
            const e = parseFloat(row && row.e_price) || 0;
            const e2 = parseFloat(row && row.e2_price) || 0;
            if (e > 0 && e2 > 0) return Math.min(e, e2);
            return e > 0 ? e : e2;
        }

        /** Cap S PRC to Amz / eBay when above those prices, then to LMP. */
        function temuSpriceCapResult(row, rawSprice, extra) {
            extra = extra || {};
            const raw = parseFloat(rawSprice);
            if (!(raw > 0)) return { sprice: 0, labels: [], lmpAlert: false, amz: 0, ebay: 0, lmp: 0 };
            const amz = temuAmzRefPrice(row);
            const ebay = temuEbayRefPrice(row);
            const fromStd = typeof temuSpriceFromStdPrmtCvr === 'function'
                ? (temuSpriceFromStdPrmtCvr(row) || 0)
                : 0;
            const lmp = extra.skip_lmp_cap
                ? 0
                : ((typeof getRowLmpL1 === 'function' ? (getRowLmpL1(row) || 0) : 0) || parseFloat(row && row.lmp) || 0);
            const candidates = [{ key: 'raw', price: +raw.toFixed(2) }];
            if (amz > 0 && (raw > amz + 0.0001 || fromStd > amz + 0.0001)) {
                candidates.push({ key: 'Amz', price: +amz.toFixed(2) });
            }
            if (ebay > 0 && (raw > ebay + 0.0001 || fromStd > ebay + 0.0001)) {
                candidates.push({ key: 'EB', price: +ebay.toFixed(2) });
            }
            if (lmp > 0 && raw + 0.0001 >= lmp) candidates.push({ key: 'LMP', price: +lmp.toFixed(2) });
            let minP = candidates[0].price;
            candidates.forEach(function(c) { if (c.price < minP) minP = c.price; });
            const labels = [];
            let lmpAlert = false;
            candidates.forEach(function(c) {
                if (Math.abs(c.price - minP) > 0.015) return;
                if (c.key === 'LMP') lmpAlert = true;
                else if (c.key === 'Amz' || c.key === 'EB') labels.push(c.key);
            });
            return { sprice: +minP.toFixed(2), labels: labels, lmpAlert: lmpAlert, amz: amz, ebay: ebay, lmp: lmp };
        }

        function temuPrepareSpriceForSave(rowData, sprice) {
            const cap = temuSpriceCapResult(rowData, sprice);
            return cap.sprice > 0 ? cap.sprice : (parseFloat(sprice) || sprice);
        }

        function temuDisplayedSprice(row) {
            return temuPrepareSpriceForSave(row, temuRawSprice(row)) || 0;
        }

        function temuListingPrice(row) {
            const full = typeof temuFullPriceFromRow === 'function' ? temuFullPriceFromRow(row) : 0;
            if (full > 0) return +Number(full).toFixed(2);
            const r = typeof temuRPriceFromRow === 'function'
                ? temuRPriceFromRow(row)
                : (parseFloat(row && row.temu_price) || 0);
            if (r > 0) return +Number(r).toFixed(2);
            return parseFloat(row && row.base_price) || 0;
        }

        /** Blue triangle: current Temu Price is higher than displayed S PRC. */
        function temuHasBlueTriangle(row) {
            if (!row || (typeof isTemuParentRow === 'function' && isTemuParentRow(row))) return false;
            const sprice = typeof temuDisplayedSprice === 'function' ? temuDisplayedSprice(row) : 0;
            const price = temuListingPrice(row);
            return price > 0 && sprice > 0 && price > sprice + 0.0001;
        }

        function temuSpriceCapLabels(row) {
            if (!row || (typeof isTemuParentRow === 'function' && isTemuParentRow(row))) return [];
            const raw = typeof temuRawSprice === 'function' ? temuRawSprice(row) : 0;
            if (!(raw > 0) || typeof temuSpriceCapResult !== 'function') return [];
            return temuSpriceCapResult(row, raw).labels || [];
        }
        function temuHasAmzCap(row) {
            return temuSpriceCapLabels(row).indexOf('Amz') !== -1;
        }
        function temuHasEbCap(row) {
            return temuSpriceCapLabels(row).indexOf('EB') !== -1;
        }

        /**
         * Red triangle in S PRC = LMP was the binding cap (displayed S PRC is LMP).
         * Blue triangle (Temu Price > S PRC) and Amz/EB labels are separate and must not match.
         */
        function temuSpriceHasLmpAlert(row) {
            if (!row || (typeof isTemuParentRow === 'function' && isTemuParentRow(row))) return false;
            if (!((parseFloat(row.inventory) || 0) > 0)) return false;
            let raw = typeof temuRawSprice === 'function' ? temuRawSprice(row) : (parseFloat(row.sprice) || 0);
            if (!(raw > 0) && typeof temuSpriceFromStdPrmtCvr === 'function') {
                raw = temuSpriceFromStdPrmtCvr(row) || 0;
            }
            if (!(raw > 0) || typeof temuSpriceCapResult !== 'function') return false;
            return !!temuSpriceCapResult(row, raw).lmpAlert;
        }

        /**
         * S PRC = Std − (PRMT% + CPN%)
         *   = Std × (1 − (PRMT% + CPN%)/100)
         * If both discounts are 0, S PRC = Std.
         */
        function temuSpriceFromStdPrmtCpn(row) {
            if (!row || isTemuParentRow(row)) return null;
            const std = parseFloat(row.STANDARD_PRICE != null ? row.STANDARD_PRICE : row.standard_price) || 0;
            if (!(std > 0)) return null;
            const prmt = Math.max(0, parseFloat(row.prmt_pct != null ? row.prmt_pct : row._prmt_pct_applied) || 0);
            const cpn = Math.max(0, parseFloat(row.cpn_pct != null ? row.cpn_pct : row._cpn_pct_applied) || 0);
            const adj = (typeof computeCvrUpDnPct === 'function') ? (Number(computeCvrUpDnPct(row)) || 0) : 0;
            const totalDisc = Math.min(99.99, Math.max(0, prmt + cpn + adj));
            const sprice = totalDisc > 0
                ? +(std * (1 - (totalDisc / 100))).toFixed(2)
                : +std.toFixed(2);
            return sprice >= 0.01 ? sprice : null;
        }
        function temuSpriceFromStdPrmtCvr(row) {
            return temuSpriceFromStdPrmtCpn(row);
        }

        function applySpriceFromStdPrmtCvr() {
            if (!table) {
                showToast('Load data first', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            const $btn = $('#apply-sprice-from-std-btn');
            const btnHtml = $btn.html();
            const jobs = [];
            let skipped = 0;

            selectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) {
                    skipped++;
                    return;
                }
                const row = rows[0];
                const d = row.getData();
                if (isTemuParentRow(d)) {
                    skipped++;
                    return;
                }
                const inv = parseFloat(d.inventory) || 0;
                if (inv <= 0) {
                    skipped++;
                    return;
                }
                const sprice = temuPrepareSpriceForSave(d, temuSpriceFromStdPrmtCvr(d));
                if (!(sprice > 0)) {
                    skipped++;
                    return;
                }
                jobs.push({ row: row, sku: sku, sprice: sprice });
            });

            if (!jobs.length) {
                showToast('No selected SKUs have Std Prc (skipped ' + skipped + ')', 'error');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            let ok = 0;
            let fail = 0;

            function finish() {
                $btn.prop('disabled', false).html(btnHtml);
                if (table) table.redraw(true);
                showToast(
                    'Apply SPrice: ' + ok + ' SKU(s)'
                        + (fail ? (', ' + fail + ' failed') : '')
                        + (skipped ? (', ' + skipped + ' skipped') : ''),
                    fail && !ok ? 'error' : 'success'
                );
            }

            jobs.forEach(function(job) {
                job.row.update({ sprice: job.sprice, sprice_status: 'processing' });
                job.row.reformat();
                saveSpriceWithRetry(job.sku, job.sprice, job.row)
                    .then(function() { ok++; if (ok + fail === jobs.length) finish(); })
                    .catch(function() { fail++; if (ok + fail === jobs.length) finish(); });
            });
        }

        function applySprice2699() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            const updates = [];
            const targetPrice = 26.99;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    
                    // Update the row with new SPRICE
                    row.update({ 
                        sprice: targetPrice
                    });
                    row.reformat();
                    
                    // Add to batch update
                    updates.push({
                        sku: sku,
                        sprice: targetPrice
                    });
                    
                    updatedCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuSprice2699Updates(updates);
            }
            
            showToast(`SPRICE set to $26.99 for ${updatedCount} SKU(s)`, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuSprice2699Updates(updates) {
            let saved = 0;
            let errors = 0;
            
            updates.forEach((update, index) => {
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: update.sku,
                        sprice: update.sprice
                    },
                    success: function(response) {
                        saved++;
                        if (index === updates.length - 1) {
                            showToast(`SPRICE $26.99 saved for ${saved} SKU(s)`, 'success');
                            table.redraw();
                        }
                    },
                    error: function(xhr) {
                        errors++;
                        if (index === updates.length - 1) {
                            if (errors === updates.length) {
                                showToast('Failed to save SPRICE', 'error');
                            } else {
                                showToast(`SPRICE saved for ${saved} SKU(s), ${errors} failed`, 'warning');
                            }
                        }
                    }
                });
            });
        }

        function selectSoldWithBlankSprice() {
            // Get all table data
            const allData = table.getData('all');
            let newlySelectedCount = 0;
            
            // Don't clear current selection - only add unselected items
            
            // Select SKUs where INV > 0 AND Temu L30 > 0 AND SPRICE is null/blank AND not already selected
            allData.forEach(row => {
                const temuL30Val = row['temu_l30'];
                const spriceVal = row['sprice'];
                const invVal = row['inventory'];
                const sku = row['sku'];
                
                // Parse temu_l30 - must be a positive number
                const temuL30 = temuL30Val ? parseInt(temuL30Val) : 0;
                const inventory = invVal ? parseInt(invVal) : 0;
                
                // Check if sprice is null, undefined, empty string, or 0
                const spriceIsBlank = !spriceVal || spriceVal === '' || spriceVal === 0 || parseFloat(spriceVal) === 0;
                
                // Only select if: has SKU AND inventory > 0 AND temu sold > 0 AND sprice is blank AND not already selected
                if (sku && inventory > 0 && temuL30 > 0 && spriceIsBlank && !selectedSkus.has(sku)) {
                    selectedSkus.add(sku);
                    newlySelectedCount++;
                }
            });
            
            // Set the filter flag and reapply all filters
            soldSpriceBlankFilterActive = true;
            applyFilters();
            
            // Update UI
            updateSelectedCount();
            updateSelectAllCheckbox();
            updateSummary();
            
            // Update checkboxes
            $('.sku-select-checkbox').each(function() {
                const sku = temuSkuFromCheckbox(this);
                $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
            });
            
            if (newlySelectedCount > 0 || selectedSkus.size > 0) {
                if (newlySelectedCount > 0) {
                    showToast(`Added ${newlySelectedCount} sold SKU(s) with blank SPRICE to selection (Total: ${selectedSkus.size})`, 'success');
                } else {
                    showToast(`Filtered to show sold items with blank SPRICE (${selectedSkus.size} already selected)`, 'info');
                }
            } else {
                showToast('No sold items with blank SPRICE found', 'info');
            }
        }

        function clearAllSprice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            const skusArray = Array.from(selectedSkus);
            
            $.ajax({
                url: '/temu-clear-sprice',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    skus: skusArray
                },
                beforeSend: function() {
                    $('#clear-sprice-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');
                },
                success: function(response) {
                    if (response.success) {
                        // Update the table rows
                        skusArray.forEach(sku => {
                            const rows = table.searchRows("sku", "=", sku);
                            if (rows.length > 0) {
                                rows[0].update({ sprice: null });
                                rows[0].reformat();
                            }
                        });
                        
                        showToast(`Successfully cleared SPRICE for ${response.cleared} SKU(s)`, 'success');
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to clear SPRICE data', 'error');
                },
                complete: function() {
                    $('#clear-sprice-btn').prop('disabled', false).html('<i class="fas fa-trash"></i> Clear SPRICE');
                }
            });
        }

        function updateSummary() {
            // Sum from table directly with no filter: use full dataset (getData("all"))
            const data = table.getData("all");
            const filteredData = table.getData("active") || [];
            $('#rows-count-badge').text('Rows ' + filteredData.length.toLocaleString());
            
            let totalProducts = data.length;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalRevenueFull = 0; // Full Temu Price × qty — GPFT/NPFT badge
            let totalProfitFull = 0;
            let totalLp = 0;
            let totalGprft = 0;
            let totalGroi = 0;
            let totalAds = 0;
            let totalNpft = 0;
            let totalNroi = 0;
            let totalCvr = 0;
            let totalDil = 0;
            let totalSpend = 0;
            let totalSpendL30 = 0; // Total spend_l30 for aggregate Ads% calculation (matches all-marketplace-master)
            let totalViews = 0;
            let totalTemuL30 = 0;
            let totalInv = 0;
            let cvrCount = 0;
            let dilCount = 0;
            let zeroSoldCount = 0;
            let lessAmzCount = 0;
            let moreAmzCount = 0;
            let greenAlertCount = 0;
            let spriceLmpAlertCount = 0;
            let blueTriangleCount = 0;
            let amzCapCount = 0;
            let ebCapCount = 0;
            
            data.forEach(row => {
                const temuL30 = parseInt(row['temu_l30']) || 0;
                const price = parseFloat(row['base_price']) || 0;
                const temuPrice = parseFloat(row['temu_price']) || 0;  // Temu Price column = price for PFT formula
                const lpPerUnit = parseFloat(row['lp']) || 0;
                const temuShip = parseFloat(row['temu_ship']) || 0;

                totalQuantity += temuL30;

                // Only include rows with sales (Temu L30 > 0 and basePrice > 0) in PFT/revenue/COGS
                // FB Prc: +$2.99 when per-unit base price ≤ $26.99
                // (matches the per-row Temu Price column and /temu-tabulator).
                const hasSales = temuL30 > 0 && price > 0;
                if (hasSales) {
                    const fbPrice = price <= 26.99 ? price + 2.99 : price; // Temu R Price
                    const fullPrice = temuFullPriceFromBase(price); // (Base × 1.1364) + $2.99 if ≤ $26.99
                    // Same margin as GROI / backend (row.percentage from marketplace_percentages)
                    const marginRaw = parseFloat(row['percentage']);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                    // Sales badge uses Full Temu Price; GROI profit = (Temu R Price × margin) − LP − ship
                    const rowProfitR = (fbPrice * margin - lpPerUnit - temuShip) * temuL30;
                    const rowProfitFull = (fullPrice * margin - lpPerUnit - temuShip) * temuL30;
                    totalRevenue += fullPrice * temuL30; // Sales = Full Temu Price × qty
                    totalProfit += rowProfitR;
                    totalRevenueFull += fullPrice * temuL30;
                    totalProfitFull += rowProfitFull;
                    totalLp += lpPerUnit * temuL30;
                }

                // Percentage metrics (for fallback simple average when no revenue/COGS)
                totalGprft += parseFloat(row['profit_percent']) || 0;
                totalGroi += parseFloat(row['roi_percent']) || 0;
                totalAds += parseFloat(row['ads_percent']) || 0;
                totalNpft += parseFloat(row['npft_percent']) || 0;
                totalNroi += parseFloat(row['nroi_percent']) || 0;
                
                // CVR% (only count non-zero values for average)
                const cvr = parseFloat(row['cvr_percent']) || 0;
                if (cvr > 0) {
                    totalCvr += cvr;
                    cvrCount++;
                }
                
                // DIL% (only count non-zero values for average)
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                // Ad spend and views
                totalSpend += parseFloat(row['spend']) || 0;
                // Use spend_l30 ONLY (no fallback to spend) to match all-marketplace-master fetchTotalAdSpendFromTables
                totalSpendL30 += parseFloat(row['spend_l30'] || 0);
                totalViews += parseInt(row['product_clicks']) || 0;
                totalTemuL30 += temuL30;
                
                // Declare common variables once for this row
                const inventory = parseFloat(row['inventory']) || 0;
                
                totalInv += parseInt(row['inventory']) || 0;
                
                // Count SKUs with 0 sold (Temu L30 = 0 AND INV > 0)
                if (temuL30 === 0 && inventory > 0) {
                    zeroSoldCount++;
                }

                // Green Alert count kept for any leftover toolbar wiring; Red Alert badge is gone.
                if (temuIsGreenAlert(row)) {
                    greenAlertCount++;
                }
                if (temuSpriceHasLmpAlert(row)) {
                    spriceLmpAlertCount++;
                }
                if (typeof temuHasBlueTriangle === 'function' && temuHasBlueTriangle(row)) {
                    blueTriangleCount++;
                }
                if (typeof temuHasAmzCap === 'function' && temuHasAmzCap(row)) {
                    amzCapCount++;
                }
                if (typeof temuHasEbCap === 'function' && temuHasEbCap(row)) {
                    ebCapCount++;
                }
                
                // Count < Amz and > Amz (compare Temu Price with Amazon Price)
                // temuPrice already declared above, reuse it
                const amazonPrice = parseFloat(row['a_price']) || 0;
                
                if (amazonPrice > 0 && temuPrice > 0) {
                    if (temuPrice < amazonPrice) {
                        lessAmzCount++; // Temu Price < Amz Price
                    } else if (temuPrice > amazonPrice) {
                        moreAmzCount++; // Temu Price > Amz Price
                    }
                }
            });
            
            // Calculate averages
            // Avg GPRFT% on Full Temu Price; GROI% stays on Temu R Price profit / LP
            const avgGprft = totalRevenueFull > 0 ? (totalProfitFull / totalRevenueFull) * 100 : (totalProducts > 0 ? totalGprft / totalProducts : 0);
            // Weighted GROI% = (Total Profit on R price / Total LP/COGS) × 100
            const avgGroi = totalLp > 0 ? (totalProfit / totalLp) * 100 : (totalProducts > 0 ? totalGroi / totalProducts : 0);
            const avgAds = totalProducts > 0 ? totalAds / totalProducts : 0;
            // Prefer backend aggregate_ads_percent only when it is a valid positive number.
            // If backend sends 0/invalid while table has spend+sales, compute ADS% from table totals.
            // Primary source is spend_l30; fall back to spend snapshot when spend_l30 is unavailable.
            const spendForAdsPercent = totalSpendL30 > 0 ? totalSpendL30 : totalSpend;
            const computedAggregateAdsPercent = totalRevenueFull > 0
                ? (spendForAdsPercent / totalRevenueFull) * 100
                : (totalRevenue > 0 ? (spendForAdsPercent / totalRevenue) * 100 : 0);
            const hasValidBackendAdsPercent = Number.isFinite(Number(badgeAvgAds)) && Number(badgeAvgAds) > 0;
            if (!hasValidBackendAdsPercent) {
                badgeAvgAds = computedAggregateAdsPercent;
            }
            // NPFT% = GPFT% - ADS% (simple formula, not weighted)
            // CRITICAL: Always use badgeAvgAds (aggregate Ads% from backend) - never use avgAds (simple average)
            // This ensures NPFT uses the same Ads% as all-marketplace-master (2.9%)
            let adsPercentForNpft = 0;
            if (badgeAvgAds != null && badgeAvgAds !== undefined) {
                adsPercentForNpft = badgeAvgAds;
            } else if (totalRevenueFull > 0) {
                adsPercentForNpft = (spendForAdsPercent / totalRevenueFull) * 100;
            } else if (totalRevenue > 0) {
                // Fallback: use same source selection as badge (spend_l30, else spend snapshot)
                adsPercentForNpft = (spendForAdsPercent / totalRevenue) * 100;
            }
            // Use weighted avgGprft for accurate NPFT calculation
            const avgNpft = avgGprft - adsPercentForNpft;
            // NROI% = GROI% - ADS% (simple formula)
            const avgNroi = avgGroi - adsPercentForNpft;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            // CVR is driven by the two dedicated badges below — Total Views and Total Sold —
            // so the badge value matches "sold ÷ views" exactly. totalViews comes from the
            // same product_clicks sum used by the Total Views badge; totalTemuL30 comes from
            // the same temu_l30 sum used by the Total Sold badge. Using totalTemuL30 (not
            // totalQuantity, which can be overridden by sales_summary) keeps the math
            // strictly = SoldBadge / ViewsBadge so the displayed numbers always agree.
            const cvrTotalViews = totalViews;
            const cvrTotalSold  = totalTemuL30;
            const qtyPerViews = cvrTotalViews > 0 ? (cvrTotalSold / cvrTotalViews) * 100 : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;

            // Calculate TCOS: (Total Ad Spend / Total Revenue) × 100
            const totalTcos = totalRevenueFull > 0
                ? (totalSpend / totalRevenueFull) * 100
                : (totalRevenue > 0 ? (totalSpend / totalRevenue) * 100 : 0);
            
            // Calculate average views
            const avgViews = totalProducts > 0 ? totalViews / totalProducts : 0;
            
            // Update badges (prefer backend summary; fall back to table totals when backend returns empty/zeroed summary)
            if (salesSummaryFromBackend) {
                const backendOrders = Number(salesSummaryFromBackend.total_orders || 0);
                const backendQuantity = Number(salesSummaryFromBackend.total_quantity || 0);
                const backendRevenue = Number(salesSummaryFromBackend.total_revenue || 0);
                const hasBackendSalesSummary = (backendOrders > 0 || backendQuantity > 0 || backendRevenue > 0);

                if (hasBackendSalesSummary) {
                    $('#total-quantity-badge').text('QTY ' + backendQuantity.toLocaleString());
                    $('#total-revenue-badge').text('$ ' + Math.round(backendRevenue).toLocaleString());
                    updateTemuRecoveryBadge(backendRevenue);
                } else {
                    $('#total-quantity-badge').text('QTY ' + totalQuantity.toLocaleString());
                    $('#total-revenue-badge').text('$ ' + Math.round(totalRevenue).toLocaleString());
                    updateTemuRecoveryBadge(totalRevenue);
                }
            } else {
                $('#total-quantity-badge').text('QTY ' + totalQuantity.toLocaleString());
                $('#total-revenue-badge').text('$ ' + Math.round(totalRevenue).toLocaleString());
                updateTemuRecoveryBadge(totalRevenue);
            }
            $('#zero-sold-count-badge').text('0 Sold ' + zeroSoldCount.toLocaleString());
            if (window.PriceGtLmpBadge && table) {
                const pglRows = table.getData('all') || [];
                const pglCount = (typeof temuRowHasPriceGtLmp === 'function')
                    ? pglRows.filter(temuRowHasPriceGtLmp).length
                    : PriceGtLmpBadge.count(pglRows, 'temu_price');
                PriceGtLmpBadge.paint('#temu-price-gt-lmp-badge', pglCount);
                PriceGtLmpBadge.report('temu', pglCount);
                PriceGtLmpBadge.setOutline(document.getElementById('temu-price-gt-lmp-badge'), priceGtLmpFilterActive);
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#temu-price-lt80-lmp-badge', pglRows, 'temu', 'temu_price');
                }
            }
            $('#temu-sprice-lmp-alert-badge').html('<i class="fas fa-exclamation-triangle"></i> S PRC ' + spriceLmpAlertCount.toLocaleString());
            $('#temu-sprice-lmp-alert-badge').css({
                outline: spriceLmpAlertFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: spriceLmpAlertFilterActive ? '2px' : ''
            });
            $('#temu-blue-triangle-badge').html('<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString());
            $('#temu-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
            $('#temu-amz-cap-badge').text('Amz ' + amzCapCount.toLocaleString());
            $('#temu-amz-cap-badge').css({
                outline: amzCapFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: amzCapFilterActive ? '2px' : ''
            });
            $('#temu-eb-cap-badge').text('EB ' + ebCapCount.toLocaleString());
            $('#temu-eb-cap-badge').css({
                outline: ebCapFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: ebCapFilterActive ? '2px' : ''
            });
            // CVR badge: use the LIVE qtyPerViews computed from the same totalQuantity
            // and totalViews that drive the QTY and Views badges. Previously this preferred
            // the daily snapshot in temu_badge_daily_data so the badge would visually match
            // the chart's "today" tooltip — but that meant when the user uploaded fresh data,
            // the QTY/Views badges updated immediately while CVR was frozen at the last cron
            // value, making the ratio look "stuck" and out of sync with its own numerator/denominator.
            // Now the badge always reflects the data on screen; the chart's "today" point will
            // catch up to it on the next CollectTemuMetrics run. The snapshot is used only as
            // a last-resort fallback when there is no live data (e.g. zero rows on first load).
            // Renders with 2 decimals to match the chart tooltip precision.
            let displayCvr = qtyPerViews;
            if (!isFinite(displayCvr) || (totalViews <= 0 && todayBadgeSnapshotFromBackend != null)) {
                const snapshotCvr = parseFloat(todayBadgeSnapshotFromBackend.avg_cvr_pct);
                if (isFinite(snapshotCvr)) displayCvr = snapshotCvr;
            }
            $('#avg-cvr-badge').text('CVR ' + displayCvr.toFixed(1) + '%');
            $('#avg-dil-badge').text('Avg DIL ' + Math.round(avgDil) + '%');
            // Total Revenue badge set above from sales_summary or table
            $('#total-profit-badge').text('PFT $' + Math.round(totalProfit).toLocaleString());
            $('#total-lp-badge').text('Total LP $' + Math.round(totalLp).toLocaleString());
            $('#avg-gprft-badge').text('GPFT ' + Math.round(avgGprft) + '%');
            $('#avg-groi-badge').text('GROI ' + Math.round(avgGroi) + '%');
            // Prefer Ads API totals from PHP (temu_ads_api_reports) so the badge
            // matches /temu/ads even when a goods_id is not on every pricing row.
            const spendForSummaryBadge = (adTotalsFromBackend && adTotalsFromBackend.spend != null)
                ? Number(adTotalsFromBackend.spend) : totalSpend;
            $('#total-spend-badge').text('Ads$ ' + Math.round(spendForSummaryBadge).toLocaleString());
            // Use badgeAvgAds (aggregate Ads% from backend) for badge display (matches all-marketplace-master)
            const displayAdsPercent = (badgeAvgAds != null) ? badgeAvgAds : adsPercentForNpft;
            $('#avg-ads-badge').text('Ads ' + displayAdsPercent.toFixed(1) + '%');
            $('#avg-npft-badge').text('NPFT ' + Math.round(avgNpft) + '%');
            $('#avg-nroi-badge').text('NROI ' + Math.round(avgNroi) + '%');

            // Compact "k" / "M" formatter for big numbers, e.g. 61,488 → 61k,
            // 1,500,000 → 1.5M. Anything below 1,000 stays as-is with commas.
            // Used only on the Total Views badge for now (per product request);
            // keep the rest of the strip on exact comma-separated values.
            const compactInt = (n) => {
                n = Number(n) || 0;
                if (Math.abs(n) >= 1_000_000) return Math.round(n / 100_000) / 10 + 'M';
                if (Math.abs(n) >= 1_000)     return Math.round(n / 1_000) + 'k';
                return n.toLocaleString();
            };

            $('#total-views-badge').text('Views ' + compactInt(totalViews));
            $('#avg-views-badge').text('AVG ' + Math.round(avgViews).toLocaleString());
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
        }

        // Ads badges in the main summary strip (same source as /temu/ads)
        function updateTemuAdsCounts() {
            if (!table) return;
            const data = table.getData('all').filter(row => {
                const sku = row.sku || '';
                return sku && !String(row.parent || '').toUpperCase().includes('PARENT');
            });
            const processedSkus = new Set();
            const zeroInvSkus = new Set();
            const adSkuSet = new Set();
            let validSkuCount = 0, missingCount = 0, nraMissingCount = 0, nraCount = 0;
            let totalSpend = 0, totalAdSales = 0, totalBudget = 0, totalAdClicks = 0, totalAdSold = 0;
            let statusActive = 0, statusInactive = 0, statusNoAd = 0, statusNotSync = 0;

            data.forEach(row => {
                const sku = row.sku || '';
                if (!sku) return;
                const inv = parseFloat(row.inventory) || 0;
                const nr = (row.nr_req || '').trim().toUpperCase();
                const spend = parseFloat(row.spend) || 0;
                const adClicks = parseInt(row.ad_clicks, 10) || 0;
                const campaignStatus = (row.campaign_status || '').trim();
                const hasCampaign = campaignStatus === 'Active' || spend > 0 || adClicks > 0;

                if (!processedSkus.has(sku)) {
                    processedSkus.add(sku);
                    validSkuCount++;
                    if (nr === 'NRL' || nr === 'NR') nraCount++;
                    if (campaignStatus === 'Active') statusActive++;
                    else if (campaignStatus === 'Inactive') statusInactive++;
                    else if (campaignStatus === 'No ad') statusNoAd++;
                    else if (campaignStatus === 'Not sync') statusNotSync++;
                }
                if (hasCampaign && inv > 0) adSkuSet.add(sku);
                if (inv <= 0) zeroInvSkus.add(sku);
                if (!hasCampaign) {
                    if (nr === 'NRL' || nr === 'NR') {
                        if (!processedSkus.has('nm_' + sku)) {
                            processedSkus.add('nm_' + sku);
                            nraMissingCount++;
                        }
                    } else if (inv > 0) {
                        if (!processedSkus.has('m_' + sku)) {
                            processedSkus.add('m_' + sku);
                            missingCount++;
                        }
                    }
                }
                // Ads API L30/L7 fields (spend_l30 / clicks_l30) for badge totals
                totalSpend += parseFloat(row.spend_l30) || 0;
                totalBudget += parseFloat(row.target) || 0;
                totalAdClicks += parseInt(row.clicks_l30, 10) || 0;
                totalAdSales += parseFloat(row.ad_sales_l30) || 0;
                totalAdSold += parseInt(row.ad_sold_l30, 10) || 0;
            });
            const zeroInvCount = zeroInvSkus.size;
            const raCount = Math.max(0, validSkuCount - nraCount);
            const uniqueCampaignSkus = new Set();
            data.forEach(r => {
                const s = parseFloat(r.spend) || 0;
                const c = parseInt(r.ad_clicks, 10) || 0;
                const st = (r.campaign_status || '').trim();
                if (st === 'Active') uniqueCampaignSkus.add(r.sku);
            });
            // Prefer Ads API totals (response.ad_totals) over per-row sums.
            const adB = adTotalsFromBackend || {};
            const spendForBadges    = (adB.spend != null) ? Number(adB.spend)    : totalSpend;
            const clicksForBadges   = (adB.clicks != null) ? Number(adB.clicks)   : totalAdClicks;
            const adSoldForBadges   = (adB.sub_orders != null) ? Number(adB.sub_orders) : totalAdSold;
            const adSalesForBadges  = (adB.base_price_sales != null) ? Number(adB.base_price_sales) : totalAdSales;

            const avgAcos = adSalesForBadges > 0 ? (spendForBadges / adSalesForBadges) * 100 : 0;
            const roas = spendForBadges > 0 ? adSalesForBadges / spendForBadges : 0;
            // Avg Clicks denominator stays the matched-Ad-SKU count: we don't
            // know how many distinct unmatched goods_ids should be counted as
            // SKUs, so dividing by a backend row count would be misleading.
            const avgClicks = adSkuSet.size > 0 ? clicksForBadges / adSkuSet.size : 0;

            const campaignCount = totalCampaignCountFromBackend > 0 ? totalCampaignCountFromBackend : uniqueCampaignSkus.size;

            $('#temu-campaign-count').text('Campaign ' + campaignCount);
            $('#temu-total-spend-badge').text('Spend: $' + Number(spendForBadges).toLocaleString('en-US', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            }));
            $('#temu-status-active-badge').text('Active ' + statusActive);
            $('#temu-status-inactive-badge').text('Inactive ' + statusInactive);
            $('#temu-status-no-ad-badge').text('No ad ' + statusNoAd);
            $('#temu-status-not-sync-badge').text('Not sync ' + statusNotSync);
            $('#temu-total-ad-sales-badge').text('Ad Sales $' + Math.round(adSalesForBadges).toLocaleString());
            $('#temu-total-ad-clicks-badge').text('Ad Clicks ' + clicksForBadges.toLocaleString());
            $('#temu-avg-acos-badge').text('ACOS ' + Math.round(avgAcos) + '%');
            $('#temu-roas-badge').text('ROAS ' + roas.toFixed(2));
        }

        // eBay-style color functions
        const getPftColor = (value) => (window.MetricPctColors ? MetricPctColors.legacyPftClass(value) : 'red');

        const getRoiColor = (value) => (window.MetricPctColors ? MetricPctColors.legacyRoiClass(value) : 'red');

        let totalCampaignCountFromBackend = 0;
        let salesSummaryFromBackend = null;
        // today_badge_snapshot from the backend — same row the chart's "today" point reads.
        // When present, the summary badges (esp. CVR) display this snapshot's values
        // instead of the locally-computed aggregates so badge and chart can never diverge.
        let todayBadgeSnapshotFromBackend = null;
        let badgeAvgAds = null; // Ads % from badge — shown in ADS% column for all rows
        // Ads API totals for the current range (Spend / Ad Sales / Ad Clicks badges).
        let adTotalsFromBackend = null;
        let currentCampaignPeriod = 'L30';

        try {
            if (window.TemuAdsColorRules) {
                if (typeof TemuAdsColorRules.setUrls === 'function') {
                    TemuAdsColorRules.setUrls(
                        @json(route('temu.ads.color-rules')),
                        @json(route('temu.ads.color-rules.save')),
                        @json(route('temu.ads.auto-pause')),
                        @json(route('temu.ads.toggle')),
                        @json(route('temu.ads.auto-pause-cron'))
                    );
                }
                if (typeof TemuAdsColorRules.bindThresholdInput === 'function') {
                    TemuAdsColorRules.bindThresholdInput(document.getElementById('temu-l7-clicks-red-threshold'));
                }
                if (typeof TemuAdsColorRules.bindTargetRoasInput === 'function') {
                    TemuAdsColorRules.bindTargetRoasInput(document.getElementById('temu-target-roas-bidding'));
                }
                if (typeof TemuAdsColorRules.bindRuleSummary === 'function') {
                    TemuAdsColorRules.bindRuleSummary(document.getElementById('temu-ads-rules-summary'));
                }
                if (typeof TemuAdsColorRules.bindCronToggleButton === 'function') {
                    TemuAdsColorRules.bindCronToggleButton(
                        document.getElementById('temu-ads-cron-toggle-btn'),
                        document.getElementById('temu-ads-cron-status')
                    );
                }
                if (typeof TemuAdsColorRules.bindAutoPauseButton === 'function') {
                    TemuAdsColorRules.bindAutoPauseButton(
                        document.getElementById('temu-ads-auto-pause-btn'),
                        document.getElementById('temu-ads-pause-status'),
                        function () {
                            if (table) table.replaceData();
                        }
                    );
                }
                if (typeof TemuAdsColorRules.onChange === 'function') {
                    TemuAdsColorRules.onChange(function () {
                        if (table) {
                            table.redraw(true);
                        }
                    });
                }
            }
        } catch (err) {
            console.warn('TemuAdsColorRules init skipped', err);
        }

        // Play/Pause parent navigation (like pricing-master-cvr)
        let fullDataset = [];
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        let suppressDataLoadedHandler = false;

        /** CVR display: ≤3.5 keep 1 decimal; >3.5 round to whole number (e.g. 4%). */
        function formatCvrPct(val) {
            const n = parseFloat(val) || 0;
            return (n > 3.5 ? String(Math.round(n)) : n.toFixed(1)) + '%';
        }

        const temuDecreaseDataUrl = @json(url('/temu-decrease-data'));
        const temuTableErrorEl = document.getElementById('temu-table-error');
        function showTemuTableError(message) {
            if (!temuTableErrorEl) return;
            temuTableErrorEl.textContent = message || 'Failed to load Temu Analytics data. Try refresh.';
            temuTableErrorEl.classList.remove('d-none');
        }

        table = new Tabulator("#temu-table", {
            ajaxURL: temuDecreaseDataUrl,
            ajaxRequestTimeout: 180000,
            ajaxSorting: false,
            layout: "fitData",
            layoutColumnsOnNewData: true,
            // Drag column edges to widen/narrow (same as ebay / other Tabulator pages)
            columnDefaults: {
                resizable: true,
                minWidth: 64
            },
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500, 1000, true],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_30", dir: "asc"}
            ],
            ajaxResponse: function(url, params, response) {
                if (temuTableErrorEl) temuTableErrorEl.classList.add('d-none');
                if (response && response.error && !Array.isArray(response.data)) {
                    showTemuTableError(response.error);
                    return [];
                }
                if (response && Array.isArray(response.data)) {
                    const periodFromResponse = (response.period || currentCampaignPeriod || 'L30').toUpperCase();
                    currentCampaignPeriod = periodFromResponse;
                    $('#campaign-period-select').val(currentCampaignPeriod);
                    totalCampaignCountFromBackend = parseInt(response.total_campaign_count || 0, 10);
                    salesSummaryFromBackend = response.sales_summary || null;
                    todayBadgeSnapshotFromBackend = response.today_badge_snapshot || null;
                    adTotalsFromBackend = response.ad_totals || null;
                    // Use exact aggregate_ads_percent from backend (matches all-marketplace-master)
                    // This is the authoritative value - always use it for NPFT calculation
                    if (response.aggregate_ads_percent != null && response.aggregate_ads_percent !== undefined) {
                        const parsedAggregateAds = parseFloat(response.aggregate_ads_percent);
                        badgeAvgAds = Number.isFinite(parsedAggregateAds) ? parsedAggregateAds : null;
                    } else {
                        badgeAvgAds = null;
                    }
                    return response.data;
                }
                if (Array.isArray(response)) return response;
                showTemuTableError('Temu Analytics returned no rows.');
                return [];
            },
            ajaxError: function() {
                showTemuTableError('Temu Analytics data request failed or timed out. Try refresh.');
            },
            columns: [
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false
                },
                (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, frozen: true, headerSort: false }),
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        
                        return sku;
                    }
                },
                {
                    title: "Links", field: "links_column", frozen: true, width: 55, hozAlign: "center", headerSort: false,
                    tooltip: "Double-click to add / edit links",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const buyerLink = d.buyer_link || '';
                        const sellerLink = d.seller_link || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:4px;align-items:center;">';
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size:12px;text-decoration:none;"><i class="fas fa-link"></i> S</a>`;
                        }
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size:12px;text-decoration:none;"><i class="fas fa-link"></i> B</a>`;
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openTemuEditLinksModal(cell.getRow());
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    sorter: "number",
                    minWidth: 40,
                    resizable: true
                },
                {
                    title: "Temu Stock",
                    field: "temu_stock",
                    hozAlign: "center",
                    sorter: "number",
                    visible: true
                },
                {
                    title: "OVL30",
                    field: "ovl30",
                    hozAlign: "center",
                    sorter: "number"
                },
                    {
                    title: "Dil%",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // DIL color buckets — aligned with /topdawg-tabulator:
                        // red < 25, green 25–50, pink ≥ 50. (Yellow band merged into red.)
                        const dil = parseFloat(cell.getValue()) || 0;
                        let color = '';
                        if (dil < 25)      color = '#a00211'; // red (includes 0)
                        else if (dil < 50) color = '#28a745'; // green
                        else               color = '#e83e8c'; // pink (50+)
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "CVR 45",
                    field: "cvr_45",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
                    // Hidden by default — users can re-enable via the Col dropdown.
                    visible: false,
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${formatCvrPct(val)}</span>`;
                    }
                },
                {
                    title: "CVR 30",
                    field: "cvr_30",
                    hozAlign: "center",
                    sorter: "number",
                    width: 65,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const val = parseFloat(cell.getValue()) || 0;
                        const cvr60 = parseFloat(rowData.cvr_60) || 0;
                        const tol = 0.1;
                        let arrowHtml = '';
                        let arrowColor = '#ffc107';
                        let arrowIcon = 'fa-minus';
                        let dotColor = '#ffc107';
                        if (val === 0 || val < cvr60 - tol) {
                            arrowColor = '#a00211';
                            arrowIcon = 'fa-arrow-down';
                            dotColor = '#a00211';
                        } else if (val > cvr60 + tol) {
                            arrowColor = '#28a745';
                            arrowIcon = 'fa-arrow-up';
                            dotColor = '#28a745';
                        }
                        arrowHtml = ` <span title="CVR 30 vs CVR 60: ${formatCvrPct(cvr60)}" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        const dot = `<span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span>`;
                        return `<span style="color: ${color}; font-weight: 600;">${formatCvrPct(val)}</span>${arrowHtml} ${dot}`.trim();
                    }
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
                {
                    title: "Campaign",
                    field: "has_campaign",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const goodsId = rowData.goods_id || '';
                        const st = (rowData.campaign_status || '').trim();
                        const hasApiCampaign = st === 'Active' || st === 'Inactive';
                        const hasCampaign = goodsId && (
                            rowData.spend > 0 ||
                            rowData.ad_clicks > 0 ||
                            hasApiCampaign
                        );
                        const nraValue = (rowData.nr_req || '').trim().toUpperCase();
                        let dotColor, title;
                        if (nraValue === 'NRA' || nraValue === 'NRL') {
                            dotColor = 'yellow';
                            title = 'NRA - Not Required';
                        } else {
                            dotColor = hasCampaign ? 'green' : 'red';
                            title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                        }
                        return `
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="status-dot ${dotColor}" title="${title}"></span>
                            </div>
                        `;
                    }
                },
                {
                    // NRP — mirrors /forecast.analysis (forecast_analysis.nr). Editable here:
                    // shows only a colored dot, but the whole cell is a transparent <select>
                    // overlay so clicking the dot opens the native dropdown (REQ / 2BDC / LATER).
                    // Saves go to the same /update-forecast-data endpoint forecast.analysis uses,
                    // so both pages stay in sync. See change handler bound below.
                    title: "NRP",
                    field: "nrp",
                    hozAlign: "center",
                    width: 56,
                    headerSort: true,
                    sorter: "string",
                    cssClass: "temu-nrp-col",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        let raw = cell.getValue();
                        let value = (raw == null) ? '' : String(raw).trim().toUpperCase();
                        if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') value = 'REQ';
                        let dot = '#22c55e', tip = 'REQ';
                        if (value === 'NR')    { dot = '#dc3545'; tip = '2BDC'; }
                        if (value === 'LATER') { dot = '#facc15'; tip = 'LATER'; }
                        const sku = (row.sku || '').toString().replace(/'/g, '&#39;');
                        const parent = (row.parent || '').toString().replace(/'/g, '&#39;');
                        return `
                            <div class="temu-nrp-cell" title="NRP: ${tip} (click to change)">
                                <span class="temu-nrp-dot" style="background-color:${dot};" aria-hidden="true"></span>
                                <select class="temu-nrp-select" data-sku='${sku}' data-parent='${parent}' aria-label="NRP: ${tip}">
                                    <option value="REQ"   ${value === 'REQ'   ? 'selected' : ''}>REQ</option>
                                    <option value="NR"    ${value === 'NR'    ? 'selected' : ''}>2BDC</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            </div>`;
                    }
                },
                {
                    title: "NRL/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // The NRL/REQ filter dropdown in the toolbar still works since it
                    // reads row.nr_req from the data, not from the visible column.
                    visible: false,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const nrl = row['nr_req'] || '';
                        const sku = row['sku'];

                        // Determine current value (default to REQ if empty)
                        let value = '';
                        if (nrl === 'NRL' || nrl === 'NR') {
                            value = 'NRL';
                        } else if (nrl === 'REQ') {
                            value = 'REQ';
                        } else {
                            value = 'REQ'; // Default to REQ
                        }

                        return `<select class="form-select form-select-sm nr-select" data-sku="${sku}"
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                 {
                    title: "Views",
                    field: "product_clicks",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Seller Center Product clicks (View Data) + Ads API clicks, both matched by Goods ID.",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
                {
                    title: "Ads Views",
                    field: "ads_views",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Overall clicks from Temu Ads API (temu_ads_api_reports), matched by Goods ID.",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
                {
                    // L7 ad clicks from temu_ads_api_reports (period=L7)
                    title: "View 7",
                    field: "product_clicks_l7",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80,
                    headerTooltip: "L7 Overall clicks from Temu Ads API. Red when below the shared L7 Clicks coloring rule (default 70).",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        if (window.TemuAdsColorRules) {
                            return TemuAdsColorRules.formatL7Clicks(cell, value);
                        }
                        return value.toLocaleString();
                    }
                },
                {
                    // No Partner API for days 8–14 window — always 0 for Temu 1
                    title: "Views 14",
                    field: "product_clicks_l7_to_l14",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80,
                    headerTooltip: "L7→L14 window is not available from Temu Ads API (always empty).",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
               
                //  {
                //     title: "CTR",
                //     field: "ctr",
                //     hozAlign: "center",
                //     sorter: "number",
                //     formatter: function(cell) {
                //         const value = parseFloat(cell.getValue()) || 0;
                //         return value.toFixed(2) + '%';
                //     },
                //     width: 80
                // },
                {
                    title: "Std Prc",
                    field: "STANDARD_PRICE",
                    hozAlign: "center",
                    headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs Amz/Temu price.",
                    editor: "input",
                    width: 70,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent) return false;
                        const sku = String(d.sku || d['(Child) sku'] || d.SKU || '');
                        return !!sku;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(rowData.a_price || rowData['A Price'] || rowData.amazon_price || 0) || 0;
                        const basePrice = parseFloat(rowData.base_price || 0) || 0;
                        const temuDisplay = basePrice > 0 && typeof temuFullPriceFromBase === 'function'
                            ? temuFullPriceFromBase(basePrice)
                            : (parseFloat(rowData.temu_price_display || rowData.temu_price || 0) || 0);
                        const channelPrice = amzPrice > 0 ? amzPrice : temuDisplay;
                        const dot = temu1StdPrcChangeDotHtml(std, channelPrice);
                        // Always show $ amount — same as /amazon-tabulator-view (do not hide on hold).
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price_display",
                    minWidth: 90,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const basePrice = parseFloat(rowData['base_price']) || 0;
                        if (basePrice === 0) return '$0.00';
                        const displayPrice = +temuFullPriceFromBase(basePrice).toFixed(2);
                        const lmpForTri = (typeof getRowLmpL1 === 'function')
                            ? getRowLmpL1(rowData)
                            : (rowData.lmp_price || rowData.lmp || rowData.LMP);
                        const showRedTri = typeof temuRowHasPriceGtLmp === 'function'
                            ? temuRowHasPriceGtLmp(rowData)
                            : (window.PriceGtLmpBadge && PriceGtLmpBadge.hasRedTriangle(rowData, 'temu_price_display'));
                        const lmpTri = (showRedTri && window.PriceGtLmpBadge)
                            ? PriceGtLmpBadge.triangleHtml(displayPrice, lmpForTri)
                            : '';
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(displayPrice, lmpForTri) : '');

                        if (temuIsGreenAlert(rowData)) {
                            return `<span style="color: #28a745; font-weight: 600;" title="Full Temu Price. Green Alert.">$${displayPrice.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        if (temuIsRedAlert(rowData)) {
                            return `<span style="color: #a00211; font-weight: 600;" title="Full Temu Price. Red Alert.">$${displayPrice.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        return `<span title="(Base × 1.1364)${(basePrice * TEMU_FULL_PRICE_MULT) <= 26.99 ? ' + $2.99' : ''}">$${displayPrice.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                    }
                },
                {
                    title: "Temu R Price",
                    field: "temu_price",
                    hozAlign: "center",
                    minWidth: 86,
                    sorter: "number",
                    headerTooltip: "Normal Temu price (base + $2.99 when base ≤ $26.99)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const basePrice = parseFloat(rowData['base_price']) || 0;

                        // Only calculate Temu R Price if base_price > 0 (item exists in Temu)
                        if (basePrice === 0) {
                            return '$0.00';
                        }
                        const temuRPrice = basePrice <= 26.99 ? basePrice + 2.99 : basePrice;
                        const displayPrice = +temuRPrice.toFixed(2);

                        // Green/Red alerts compare normal Temu R Price vs Amazon / eBay thresholds.
                        if (temuIsGreenAlert(rowData)) {
                            return `<span style="color: #28a745; font-weight: 600;" title="Green Alert: Temu R Price is below 85% of Amz or 90% of eBay 1 / eBay 2.">$${displayPrice.toFixed(2)}</span>`;
                        }
                        if (temuIsRedAlert(rowData)) {
                            return `<span style="color: #a00211; font-weight: 600;" title="Red Alert: Temu R Price is at/above 85% of Amz AND 90% of eBay 1 / eBay 2 (uncompetitive).">$${displayPrice.toFixed(2)}</span>`;
                        }
                        return `$${displayPrice.toFixed(2)}`;
                    }
                },
                {
                    title: "Gpft",
                    field: "r_pft",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Gpft = (Temu R Price × 0.95) − Temu Ship − LP",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const pft = typeof temuPftDollars === 'function' ? temuPftDollars(rowData) : null;
                        if (pft == null) return '';
                        const color = pft < 0 ? '#dc3545' : (pft > 0 ? '#28a745' : '#6c757d');
                        return '<span style="color:' + color + ';font-weight:600;">$' + pft.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "NPFT",
                    field: "r_npft",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "NPFT = Pft − (Temu Price × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const npft = typeof temuNpftDollars === 'function' ? temuNpftDollars(rowData) : null;
                        if (npft == null) return '';
                        const color = npft < 0 ? '#dc3545' : (npft > 0 ? '#28a745' : '#6c757d');
                        return '<span style="color:' + color + ';font-weight:600;">$' + npft.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "S Profit",
                    field: "s_profit",
                    hozAlign: "center",
                    minWidth: 88,
                    sorter: "number",
                    headerTooltip: "S Profit = S Recovery × marketplace% − LP − Temu Ship",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sProfit = temuSProfit(rowData);
                        if (sProfit == null) return '';
                        const color = sProfit < 0 ? '#dc3545' : (sProfit > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">$${sProfit.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "Base Price",
                    field: "base_price",
                    hozAlign: "center",
                    minWidth: 92,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        return (value === null || value === undefined || isNaN(value)) ? '' : '$' + Number(value).toFixed(2);
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    }
                },
                {
                    title: "PRFT AMT",
                    field: "profit",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value < 0 ? '#dc3545' : (value > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    visible: false
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    minWidth: 80,
                    sorter: "number",
                    headerTooltip: "GROI% = Gpft / LP. Gpft = (Temu R Price × 0.95) − Temu Ship − LP",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const p = temuGroiParts(rowData);
                        const groi = p.groi != null ? p.groi : (parseFloat(cell.getValue()) || 0);
                        const colorClass = getRoiColor(groi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(groi)}%</span>`;
                    }
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    minWidth: 80,
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // GPRFT badge in the summary stats still reflects the underlying data.
                    visible: false,
                    headerTooltip: "GPRFT% = Gpft / Temu Price. Gpft = (Temu R Price × 0.95) − Temu Ship − LP",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = (row) => {
                            const gpft = typeof temuPftDollars === 'function' ? temuPftDollars(row) : null;
                            const fullPrice = temuFullPriceFromRow(row);
                            if (gpft == null || !(fullPrice > 0)) return 0;
                            return (gpft / fullPrice) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const gpft = typeof temuPftDollars === 'function' ? temuPftDollars(rowData) : null;
                        const fullPrice = temuFullPriceFromRow(rowData);
                        const value = (gpft != null && fullPrice > 0) ? (gpft / fullPrice) * 100 : 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // Ads% data still loads and drives the NPFT/NROI calcs + the Ads summary badge.
                    visible: false,
                    sorter: "number",
                    headerTooltip: "ADS% = 2.2% on every row",
                    formatter: function(cell) {
                        const displayVal = typeof temuAdsPercentForNet === 'function' ? temuAdsPercentForNet() : 2.2;
                        return `<span style="color: #ff1493; font-weight: 600;">${displayVal.toFixed(1)}%</span>`;
                    }
                },



                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "NROI% = NPFT / LP. NPFT = Gpft − (Temu Price × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const npft = typeof temuNpftDollars === 'function' ? temuNpftDollars(rowData) : null;
                        const lp = parseFloat(rowData.lp) || 0;
                        const value = (npft != null && lp > 0) ? (npft / lp) * 100 : 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "NPFT% = NPFT / Temu Price. NPFT = Gpft − (Temu Price × Ads%)",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // NPFT badge in the summary stats still reflects the underlying data.
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const npft = typeof temuNpftDollars === 'function' ? temuNpftDollars(rowData) : null;
                        const temuPrice = typeof temuFullPriceFromRow === 'function'
                            ? temuFullPriceFromRow(rowData)
                            : 0;
                        const value = (npft != null && temuPrice > 0) ? (npft / temuPrice) * 100 : 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "Sku Link LMP",
                    field: "linked_lmp_skus",
                    hozAlign: "left",
                    headerHozAlign: "center",
                    width: 220,
                    headerSort: false,
                    cssClass: "linked-sku-col",
                    formatter: linkedLmpSkuFormatter,
                    cellClick: function (e, cell) {
                        if (e.target.closest('.sku-link-lmp-remove')) {
                            e.preventDefault();
                            e.stopPropagation();
                            removeLinkedSkuFromRow(
                                cell.getRow().getData(),
                                e.target.closest('.sku-link-lmp-remove').dataset.linkedSku || ''
                            );
                        }
                    },
                },
                {
                    title: "+",
                    field: "linked_lmp_sku_add",
                    hozAlign: "center",
                    headerHozAlign: "center",
                    width: 52,
                    headerSort: false,
                    cssClass: "linked-sku-add-col",
                    formatter: linkedLmpSkuAddFormatter,
                    cellClick: function (e, cell) {
                        if (e.target.closest('.sku-link-lmp-add-btn')) {
                            e.preventDefault();
                            e.stopPropagation();
                            openLinkedSkuModal(cell.getRow().getData());
                        }
                    },
                },
                {
                    title: "S Recovery",
                    field: "s_recovery",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "S Recovery = Sprice × 0.88",
                    formatter: function(cell) {
                        const sprice = temuRowSprice(cell.getRow().getData());
                        if (sprice <= 0) return '';
                        return temuFormatMoney(temuSRecovery(sprice));
                    }
                },
                {
                    title: "S Temu B Prc",
                    field: "stemu_price",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Push base = inverse of Temu Price: undo +$2.99 (if applied) then ÷ 1.1364. Matches Base Price when SPRICE = Temu Price.",
                    // CSV export uses the raw field; API never sends stemu_price — compute it.
                    accessorDownload: function(value, data) {
                        const pushBase = temuPushBaseFromSprice(temuRowSprice(data));
                        return pushBase == null ? '' : pushBase;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const pushBase = temuPushBaseFromSprice(temuRowSprice(rowData));
                        if (pushBase == null) return '';
                        return temuFormatMoney(pushBase);
                    }
                },
                {
                    title: "LMP",
                    field: "lmp",
                    hozAlign: "center",
                    minWidth: 88,
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (window.ParentExpand && typeof ParentExpand.parentAvgLmpHtml === 'function') {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(row, {
                                dataset: typeof fullDataset !== 'undefined' ? fullDataset : (typeof allTableData !== 'undefined' ? allTableData : undefined),
                                field: 'lmp',
                                getValue: function(r) {
                                    const entries = Array.isArray(r.lmp_entries) ? r.lmp_entries : [];
                                    const prices = entries
                                        .filter(function(e) { return !e.ignored; })
                                        .map(function(e) {
                                            const p = e.price;
                                            if (p === null || p === undefined || p === '' || isNaN(parseFloat(p))) return null;
                                            const base = parseFloat(p);
                                            const d = parseFloat(e.delivery);
                                            let delivery = (!isNaN(d) && d > 0) ? d : 0;
                                            if (delivery <= 0 && base < 27) delivery = 2.99;
                                            return base + delivery;
                                        })
                                        .filter(function(p) { return p !== null && p > 0; });
                                    if (prices.length) return Math.min.apply(null, prices);
                                    const fallback = parseFloat(r.lmp_raw != null ? r.lmp_raw : r.lmp);
                                    return (!isNaN(fallback) && fallback > 0) ? fallback : null;
                                }
                            });
                            if (avgHtml !== null) return avgHtml;
                        }
                        const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
                        const prices = entries
                            .filter(function(e) { return !e.ignored; })
                            .map(function(e) {
                                const p = e.price;
                                if (p === null || p === undefined || p === '' || isNaN(parseFloat(p))) return null;
                                const base = parseFloat(p);
                                const d = parseFloat(e.delivery);
                                let delivery = (!isNaN(d) && d > 0) ? d : 0;
                                if (delivery <= 0 && base < 27) delivery = 2.99;
                                return base + delivery;
                            })
                            .filter(function(p) { return p !== null; });
                        let lowest = prices.length > 0 ? Math.min.apply(null, prices) : null;
                        if (lowest === null) {
                            const fallback = parseFloat(row.lmp_raw != null ? row.lmp_raw : row.lmp);
                            if (!isNaN(fallback) && fallback > 0) lowest = fallback;
                        }
                        const display = lowest !== null ? (lowest % 1 === 0 ? lowest.toLocaleString() : lowest.toFixed(2)) : '-';
                        const count = entries.length;
                        const ignoredCount = entries.filter(function(e) { return !!e.ignored; }).length;
                        const title = count > 0
                            ? (display + ' L1 (' + count + ' entries' + (ignoredCount ? ', ' + ignoredCount + ' ignored' : '') + ') - click eye to edit')
                            : (display !== '-' ? (display + ' — click eye to edit') : 'Click eye to add LMP');
                        return '<span class="lmp-display">' + (display !== '-' ? display : '<span style="color: #999;">-</span>') + '</span> <button type="button" class="btn btn-sm btn-link p-0 lmp-eye-btn" data-sku="' + (row.sku || '').replace(/"/g, '&quot;') + '" title="' + title + '"><i class="fas fa-info-circle text-info"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.lmp-eye-btn')) {
                            e.stopPropagation();
                            const row = cell.getRow().getData();
                            openLmpModal(row);
                        }
                    }
                },
                {
                    title: "Diff",
                    field: "lmp_diff_pct",
                    hozAlign: "center",
                    minWidth: 84,
                    width: 70,
                    headerTooltip: "S PRC vs LMP: (LMP − S PRC) / LMP. Green = S PRC below LMP, Red = S PRC above LMP.",
                    headerSortStartingDir: "desc",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = function(rd) {
                            const lmp = typeof getRowLmpL1 === 'function'
                                ? (getRowLmpL1(rd) || 0)
                                : (parseFloat(rd.lmp) || 0);
                            const sprice = typeof temuDisplayedSprice === 'function'
                                ? temuDisplayedSprice(rd)
                                : (parseFloat(rd.sprice) || 0);
                            if (!(lmp > 0) || !(sprice > 0)) return -Infinity;
                            return ((lmp - sprice) / lmp) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const lmp = typeof getRowLmpL1 === 'function'
                            ? (getRowLmpL1(rowData) || 0)
                            : (parseFloat(rowData.lmp) || 0);
                        const sprice = typeof temuDisplayedSprice === 'function'
                            ? temuDisplayedSprice(rowData)
                            : (parseFloat(rowData.sprice) || 0);
                        if (!(lmp > 0) || !(sprice > 0)) {
                            return '<span style="color:#999;">—</span>';
                        }
                        const diff = ((lmp - sprice) / lmp) * 100;
                        const color = diff < 0 ? '#dc3545' : '#28a745';
                        const sign = diff > 0 ? '+' : '';
                        return '<span style="color:' + color + ';font-weight:600;">' + sign + diff.toFixed(1) + '%</span>';
                    }
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    headerTooltip: "S PRC capped to Amz if above Amazon, to EB if above eBay (lower of eBay 1 / eBay 2), then to LMP. Orange Amz/EB = channel cap. Red triangle = LMP cap. Blue triangle = Temu Price > S PRC. Yellow = same as current Temu price.",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        let rawSprice = isNaN(spriceNum) ? 0 : spriceNum;
                        if (!(rawSprice > 0) && typeof temuSpriceFromStdPrmtCvr === 'function') {
                            rawSprice = temuSpriceFromStdPrmtCvr(rowData) || 0;
                        }
                        if (!(rawSprice > 0)) return '';

                        const cap = typeof temuSpriceCapResult === 'function'
                            ? temuSpriceCapResult(rowData, rawSprice)
                            : { sprice: rawSprice, labels: [], lmpAlert: false, amz: 0, ebay: 0, lmp: 0 };
                        let sprice = cap.sprice > 0 ? cap.sprice : rawSprice;

                        const basePrice = parseFloat(rowData.base_price) || 0;
                        const rPrice = typeof temuRPriceFromRow === 'function'
                            ? temuRPriceFromRow(rowData)
                            : (parseFloat(rowData.temu_price) || 0);
                        const fullPrice = typeof temuFullPriceFromRow === 'function'
                            ? temuFullPriceFromRow(rowData)
                            : (parseFloat(rowData.temu_price_display) || 0);
                        const sameAsPrice = [basePrice, rPrice, fullPrice].some(function(p) {
                            return p > 0 && Math.abs(sprice - p) < 0.02;
                        });

                        const atOrAboveLmp = typeof temuSpriceHasLmpAlert === 'function'
                            ? temuSpriceHasLmpAlert(rowData)
                            : !!cap.lmpAlert;
                        const alertHtml = atOrAboveLmp
                            ? '<i class="fas fa-exclamation-triangle temu-sprice-lmp-alert" title="S PRC capped at LMP $'
                                + Number(cap.lmp || 0).toFixed(2) + '"></i>'
                            : '';
                        const listingPrice = typeof temuListingPrice === 'function' ? temuListingPrice(rowData) : 0;
                        const blueHtml = (typeof temuHasBlueTriangle === 'function' && temuHasBlueTriangle(rowData))
                            ? '<i class="fas fa-exclamation-triangle temu-sprice-blue-alert" title="Temu Price $'
                                + Number(listingPrice).toFixed(2) + ' &gt; S PRC $' + sprice.toFixed(2) + '"></i>'
                            : '';
                        let capHtml = '';
                        (cap.labels || []).forEach(function(lbl) {
                            const ref = lbl === 'Amz' ? cap.amz : cap.ebay;
                            const name = lbl === 'Amz' ? 'Amazon' : 'eBay';
                            capHtml += '<span class="temu-sprice-cap-lbl" title="S PRC capped to ' + name + ' $'
                                + Number(ref).toFixed(2) + '">' + lbl + '</span>';
                        });
                        let color = '';
                        if (sameAsPrice) color = '#ffc107';
                        else if (atOrAboveLmp) color = '#dc3545';
                        const priceHtml = color
                            ? '<span style="color:' + color + ';font-weight:600;">$' + sprice.toFixed(2) + '</span>'
                            : ('$' + sprice.toFixed(2));
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;">'
                            + priceHtml + capHtml + alertHtml + blueHtml + '</span>';
                    }
                },
                {
                    title: "Push Prc",
                    field: "_push",
                    width: 55,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Push base = inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent) return '';
                        const sprice = typeof temuDisplayedSprice === 'function'
                            ? temuDisplayedSprice(rowData)
                            : (parseFloat(rowData.sprice) || 0);
                        const pushBase = temuPushBaseFromSprice(sprice);
                        const pushStatus = rowData.push_status || null;
                        if (sprice <= 0 || pushBase == null || pushBase <= 0) return '';

                        const sku = rowData.sku || '';
                        const goodsId = rowData.goods_id || '';
                        const skuId = rowData.sku_id || '';

                        if (pushStatus === 'pushing') {
                            return '<i class="fas fa-spinner fa-spin" style="color: #ffc107;" title="Pushing to Temu..."></i>';
                        }
                        if (pushStatus === 'pushed') {
                            return '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Temu"></i>';
                        }
                        if (pushStatus === 'error') {
                            return `<button type="button" class="temu-push-single-btn" data-sku="${sku}" data-price="${pushBase}" data-goods-id="${goodsId}" data-sku-id="${skuId}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed — click to retry"><i class="fa-solid fa-x"></i></button>`;
                        }
                        return `<button type="button" class="temu-push-single-btn" data-sku="${sku}" data-price="${pushBase}" data-goods-id="${goodsId}" data-sku-id="${skuId}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push base $${pushBase.toFixed(2)} to Temu"><i class="fas fa-upload"></i></button>`;
                    }
                },
                {
                    title: "SPFT",
                    field: "s_r_pft",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SPFT = (S R Price × 0.95) − Temu Ship − LP",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const spft = typeof temuSpftDollars === 'function' ? temuSpftDollars(rowData) : null;
                        if (spft == null) return '';
                        const color = spft < 0 ? '#dc3545' : (spft > 0 ? '#28a745' : '#6c757d');
                        return '<span style="color:' + color + ';font-weight:600;">$' + spft.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "SNPFT",
                    field: "s_r_npft",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SNPFT = SPFT − (S PRC × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const snpft = typeof temuSnpftDollars === 'function' ? temuSnpftDollars(rowData) : null;
                        if (snpft == null) return '';
                        const color = snpft < 0 ? '#dc3545' : (snpft > 0 ? '#28a745' : '#6c757d');
                        return '<span style="color:' + color + ';font-weight:600;">$' + snpft.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "SGROI%",
                    field: "sgroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SGROI% = SPFT / LP. SPFT = (S R Price × 0.95) − Temu Ship − LP",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const p = temuSpriceCalcParts(rowData);
                        if (p.sRProfit == null || !(p.lp > 0) || p.sgroi == null) return '';
                        const colorClass = getRoiColor(p.sgroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(p.sgroi)}%</span>`;
                    }
                },
                {
                    title: "SNROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SNROI% = SNPFT / LP. SNPFT = SPFT − (S PRC × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemuParentRow === 'function' && isTemuParentRow(rowData)) return '';
                        const p = temuSpriceCalcParts(rowData);
                        if (p.snroi == null) return '';
                        const colorClass = getRoiColor(p.snroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(p.snroi)}%</span>`;
                    }
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SGPRFT% = SPFT / S PRC. SPFT = (S R Price × 0.95) − Temu Ship − LP",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const p = temuSpriceCalcParts(rowData);
                        if (p.sRProfit == null || !(p.sR > 0) || p.sgprft == null) return '';
                        const colorClass = getPftColor(p.sgprft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(p.sgprft)}%</span>`;
                    }
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SPFT% = SNPFT / S PRC. SNPFT = SPFT − (S PRC × Ads%)",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const p = temuSpriceCalcParts(rowData);
                        if (p.spft == null) return '';
                        const colorClass = getPftColor(p.spft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(p.spft)}%</span>`;
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "right",
                    sorter: "number",
                    visible: true,
                    headerTooltip: "Ad spend from /temu/ads (temu_ads_api_reports Overall), matched by Goods ID.",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle l60-spend-info-icon" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Click to show/hide L60 Ad Sold / Ad Sales"></i>
                        </div>`;
                    },
                    width: 100
                },
                {
                    title: "Clicks 30",
                    field: "ad_clicks",
                    hozAlign: "right",
                    sorter: "number",
                    headerTooltip: "Last 30 days ad clicks from /temu/ads. Red when below 300.",
                    formatter: function(cell) {
                        const value = parseInt(String(cell.getValue() ?? '0').replace(/,/g, ''), 10) || 0;
                        const color = value < 300 ? 'color:#a00211;font-weight:700;' : '';
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span style="${color}">${value.toLocaleString()}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Clicks 30"></i>
                        </div>`;
                    },
                    visible: true,
                    width: 110
                },
                {
                    title: "Clicks 7",
                    field: "clicks_l7",
                    hozAlign: "right",
                    sorter: "number",
                    headerTooltip: "Last 7 days ad clicks from /temu/ads. Red when below the shared L7 Clicks rule (default 70).",
                    formatter: function(cell) {
                        const value = parseInt(String(cell.getValue() ?? '0').replace(/,/g, ''), 10) || 0;
                        if (window.TemuAdsColorRules) {
                            TemuAdsColorRules.colorL7Clicks(cell.getElement(), value);
                        }
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toLocaleString()}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Clicks 7"></i>
                        </div>`;
                    },
                    visible: true,
                    width: 100
                },
                {
                    title: "Pause/Run",
                    field: "pause_run",
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Green = Run when Clicks 7 < 70. Red = Pause when Clicks 7 ≥ 70. Click to push to Temu.",
                    formatter: function(cell) {
                        if (!window.TemuAdsColorRules || typeof TemuAdsColorRules.pauseRunButtonHtml !== 'function') return '';
                        return TemuAdsColorRules.pauseRunButtonHtml(cell.getRow().getData() || {});
                    },
                    cellClick: function(e, cell) {
                        const btn = e.target.closest('.temu-pause-run-btn');
                        if (!btn || !window.TemuAdsColorRules || typeof TemuAdsColorRules.pushPauseRun !== 'function') return;
                        TemuAdsColorRules.pushPauseRun(btn, cell, @json(route('temu.ads.toggle')));
                    },
                    visible: true,
                    width: 110
                },
                {
                    title: "Success",
                    field: "pause_run_ok",
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Result of the last Pause/Run push. Hover the red cross for the reason.",
                    formatter: function(cell) {
                        if (!window.TemuAdsColorRules || typeof TemuAdsColorRules.pauseRunResultHtml !== 'function') return '';
                        return TemuAdsColorRules.pauseRunResultHtml(cell.getRow().getData() || {});
                    },
                    visible: true,
                    width: 80
                },
                {
                    title: "ACOS",
                    field: "acos_ad",
                    hozAlign: "right",
                    sorter: "number",
                    headerTooltip: "ACOS from /temu/ads. Blue when L7 clicks are below the merged rule and ACOS is worse than Stop ROAS / Bidding (default 8).",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (window.TemuAdsColorRules) {
                            const row = cell.getRow().getData() || {};
                            TemuAdsColorRules.colorAcosBidding(cell.getElement(), value, row.clicks_l7 != null ? row.clicks_l7 : row.ad_clicks);
                        }
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${Math.round(value)}%</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="ACOS"></i>
                        </div>`;
                    },
                    visible: true,
                    width: 100
                },
                {
                    title: "Ad Sold",
                    field: "ad_sold_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue(), 10) || 0;
                        return value > 0 ? value.toLocaleString() : '<span style="color: #999;">-</span>';
                    }
                },
                {
                    title: "Ad Sales",
                    field: "ad_sales_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return value > 0 ? '$' + value.toFixed(2) : '<span style="color: #999;">-</span>';
                    }
                },
                {
                    title: "Impressions",
                    field: "impressions",
                    hozAlign: "right",
                    sorter: "number",
                    headerTooltip: "Impressions from /temu/ads (temu_ads_api_reports Overall), matched by Goods ID.",
                    visible: false,
                    width: 100,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue(), 10) || 0;
                        return v.toLocaleString();
                    }
                },
                {
                    title: "Add to cart",
                    field: "add_to_cart_number",
                    hozAlign: "right",
                    sorter: "number",
                    visible: false,
                    width: 100,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue(), 10) || 0;
                        return v.toLocaleString();
                    }
                },
                {
                    title: "OUT ROAS",
                    field: "out_roas_l30",
                    hozAlign: "right",
                    headerTooltip: "ROAS from /temu/ads. Blue when L7 clicks are below the merged rule and ROAS is below Stop ROAS / Bidding (default 8).",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // Use net_roas as OUT ROAS if out_roas_l30 is not available
                        const value = parseFloat(cell.getValue() || rowData.net_roas || 0);
                        if (window.TemuAdsColorRules) {
                            const row = cell.getRow().getData() || {};
                            TemuAdsColorRules.colorRoasBidding(cell.getElement(), value, row.clicks_l7 != null ? row.clicks_l7 : row.ad_clicks);
                        }
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="OUT ROAS"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "IN ROAS",
                    field: "in_roas_l30",
                    hozAlign: "right",
                    headerTooltip: "Not in Temu Ads API. /temu/ads has OUT ROAS only.",
                    formatter: function(cell) {
                        const cellValue = cell.getValue();
                        const value = (cellValue !== null && cellValue !== undefined) ? parseFloat(cellValue) : 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle" style="font-size: 12px; color: #3b82f6;" title="Not in Temu Ads API"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "Status",
                    field: "campaign_status",
                    hozAlign: "center",
                    headerTooltip: "Same Status as /temu/ads (ad.detail.query). Not sync = API not confirmed. Not Created = no Ads API row for this Goods ID.",
                    formatter: function(cell) {
                        const value = String(cell.getValue() || 'Not Created').trim() || 'Not Created';
                        let cls = 'bg-secondary';
                        if (value === 'Active') cls = 'bg-success';
                        else if (value === 'Inactive') cls = 'bg-warning text-dark';
                        else if (value === 'Deleted') cls = 'bg-dark';
                        else if (value === 'No ad') cls = 'bg-danger';
                        else if (value === 'Not Created') cls = 'bg-warning text-dark';
                        else if (value === 'Not sync') cls = 'bg-secondary';
                        return '<span class="badge ' + cls + '">' + value + '</span>';
                    },
                    visible: true,
                    width: 110
                },
                {
                    title: "Target",
                    field: "target",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    headerTooltip: "Budget and Bidding Target ROAS. Empty rows use the shared Stop ROAS default (8).",
                    formatter: function(cell) {
                        let value = parseFloat(cell.getValue());
                        if (!isFinite(value) || value <= 0) {
                            value = window.TemuAdsColorRules
                                ? TemuAdsColorRules.getTargetRoasBidding()
                                : 8;
                        }
                        return Number(value).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    headerTooltip: "Uses stored Temu ship when it already exists; otherwise regular ship (+ 50% O-Size when Type is O-Size)",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                },
                {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    visible: true,
                    formatter: function(cell) {
                        const sku = String(cell.getRow().getData()['sku'] || '');
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku.replace(/"/g, '&quot;')}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: "Goods ID",
                    field: "goods_id",
                    hozAlign: "left",
                    sorter: "string",
                    minWidth: 80,
                    width: 120,
                    resizable: true,
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    visible: false,
                    accessorDownload: function(value, data) {
                        const g = (data && data.goods_id != null && data.goods_id !== '') ? String(data.goods_id) : '';
                        // Leading tab forces Excel to treat as text (avoids scientific notation)
                        return g ? ('\t' + g) : '';
                    },
                    formatter: function(cell) {
                        const goodsId = (cell.getValue() || '').toString().trim();
                        if (!goodsId) return '';
                        return `${goodsId} <button type="button" class="btn btn-sm p-0 ms-1 copy-goods-id" data-goods-id="${goodsId}" title="Copy Goods ID" style="border:none;background:none;color:#6c757d;"><i class="fas fa-copy"></i></button>`;
                    }
                },
            ]
        });

        table.on('pageLoaded', function() {
            updateSelectAllCheckbox();
        });

        function captureColumnVisibilityState() {
            const state = {};
            if (!table) return state;
            table.getColumns().forEach(function(column) {
                const field = column.getField();
                if (field) state[field] = column.isVisible();
            });
            return state;
        }

        function applyColumnVisibilityState(state) {
            if (!table || !state) return;
            table.getColumns().forEach(function(column) {
                const field = column.getField();
                if (!field || field === '_select' || !Object.prototype.hasOwnProperty.call(state, field)) return;
                if (state[field]) {
                    column.show();
                } else {
                    column.hide();
                }
            });
        }

        /**
         * Honor the column-box checkboxes after any filter.
         */
        function applySelectedColumnVisibility() {
            if (!table) return;

            const boxChecks = document.querySelectorAll('#column-dropdown-menu .col-vis-field-toggle');
            if (boxChecks.length) {
                const state = {};
                boxChecks.forEach(function(cb) {
                    if (cb.value) state[cb.value] = !!cb.checked;
                });
                applyColumnVisibilityState(state);
                return;
            }
            if (savedColumnVisibilityMap && typeof savedColumnVisibilityMap === 'object') {
                applyColumnVisibilityState(savedColumnVisibilityMap);
            }
        }
        
        let temuAdsBadgeFilter = null;
        $(document).on('click', '.temu-ads-badge', function() {
            const filter = $(this).data('ads-filter');
            temuAdsBadgeFilter = (temuAdsBadgeFilter === filter) ? null : filter;
            $('.temu-ads-badge').removeClass('border border-3 border-dark');
            if (temuAdsBadgeFilter) {
                $('.temu-ads-badge[data-ads-filter="' + temuAdsBadgeFilter + '"]').addClass('border border-3 border-dark');
            }
            applyFilters();
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
        });

        // L60 column toggle (info icon in Spend L60 column header)
        let l60ColumnsVisible = false;
        const l60ColumnFields = ['ad_sold_l60', 'ad_sales_l60'];
        function toggleL60Columns(show) {
            if (!table) return;
            l60ColumnsVisible = show;
            const cols = table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField()));
            cols.forEach(c => { show ? c.show() : c.hide(); });
        }
        $(document).on('click', '.l60-spend-info-icon', function(e) {
            e.stopPropagation();
            l60ColumnsVisible = !l60ColumnsVisible;
            toggleL60Columns(l60ColumnsVisible);
        });

        $('#sku-search').on('keyup', function() {
            applyFilters();
        });

        /** True for ProductMaster PARENT rows (used by All Rows / Parents / SKUs filter). */
        function isTemuParentRow(data) {
            if (!data) return false;
            if (data.is_parent === true || data.is_parent === 1 || data.is_parent === '1') return true;
            const sku = String(data.sku || '').trim().toUpperCase();
            return sku.indexOf('PARENT ') === 0 || sku === 'PARENT';
        }

        /** Red triangle: Full Temu Price > L1 LMP, INV > 0, SKU rows only. */
        function temuRowHasPriceGtLmp(data) {
            if (!data || isTemuParentRow(data)) return false;
            if (!((parseFloat(data.inventory) || 0) > 0)) return false;
            const basePrice = parseFloat(data.base_price) || 0;
            const displayPrice = basePrice > 0 && typeof temuFullPriceFromBase === 'function'
                ? +temuFullPriceFromBase(basePrice).toFixed(2)
                : (parseFloat(data.temu_price_display) || parseFloat(data.temu_price) || 0);
            const lmp = typeof getRowLmpL1 === 'function'
                ? getRowLmpL1(data)
                : (parseFloat(data.lmp) || 0);
            return displayPrice > 0 && lmp > 0 && displayPrice > lmp;
        }

        function temuResetCapBadgeOutlines() {
            amzCapFilterActive = false;
            ebCapFilterActive = false;
            $('#temu-amz-cap-badge').css({ outline: '', outlineOffset: '' });
            $('#temu-eb-cap-badge').css({ outline: '', outlineOffset: '' });
        }

        function temuClearSpriceLmpAlertCompetingFilters() {
            blueTriangleFilterActive = false;
            temuResetCapBadgeOutlines();
            priceGtLmpFilterActive = false;
            priceLt80LmpFilterActive = false;
            soldSpriceBlankFilterActive = false;
            temuAdsBadgeFilter = null;
            $('#nr-req-filter').val('all');
            $('#sold-filter').val('all');
            $('#gpft-filter').val('all');
            $('#roi-filter').val('all');
            $('#cvr-filter').val('all');
            if ($('#cvr-trend-filter').length) $('#cvr-trend-filter').val('all');
            $('#nrp-filter').val('all');
            $('#inventory-filter').val('all');
            $('#parent-filter').val('skus');
            $('#sku-search').val('');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $('.column-filter[data-column="dil_percent"][data-color="all"]').addClass('active');
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.setOutline(document.getElementById('temu-price-gt-lmp-badge'), false);
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.setOutline(document.getElementById('temu-price-lt80-lmp-badge'), false);
            }
            $('#temu-blue-triangle-badge').css({ outline: '', outlineOffset: '' });
        }

        function temuClearBlueTriangleCompetingFilters() {
            spriceLmpAlertFilterActive = false;
            temuResetCapBadgeOutlines();
            priceGtLmpFilterActive = false;
            priceLt80LmpFilterActive = false;
            soldSpriceBlankFilterActive = false;
            temuAdsBadgeFilter = null;
            $('#nr-req-filter').val('all');
            $('#sold-filter').val('all');
            $('#gpft-filter').val('all');
            $('#roi-filter').val('all');
            $('#cvr-filter').val('all');
            if ($('#cvr-trend-filter').length) $('#cvr-trend-filter').val('all');
            $('#nrp-filter').val('all');
            $('#inventory-filter').val('all');
            $('#parent-filter').val('skus');
            $('#sku-search').val('');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $('.column-filter[data-column="dil_percent"][data-color="all"]').addClass('active');
            $('#temu-sprice-lmp-alert-badge').css({ outline: '', outlineOffset: '' });
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.setOutline(document.getElementById('temu-price-gt-lmp-badge'), false);
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.setOutline(document.getElementById('temu-price-lt80-lmp-badge'), false);
            }
        }

        function temuClearCapBadgeCompetingFilters() {
            spriceLmpAlertFilterActive = false;
            blueTriangleFilterActive = false;
            priceGtLmpFilterActive = false;
            priceLt80LmpFilterActive = false;
            soldSpriceBlankFilterActive = false;
            temuAdsBadgeFilter = null;
            $('#nr-req-filter').val('all');
            $('#sold-filter').val('all');
            $('#gpft-filter').val('all');
            $('#roi-filter').val('all');
            $('#cvr-filter').val('all');
            if ($('#cvr-trend-filter').length) $('#cvr-trend-filter').val('all');
            $('#nrp-filter').val('all');
            $('#inventory-filter').val('all');
            $('#parent-filter').val('skus');
            $('#sku-search').val('');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $('.column-filter[data-column="dil_percent"][data-color="all"]').addClass('active');
            $('#temu-sprice-lmp-alert-badge').css({ outline: '', outlineOffset: '' });
            $('#temu-blue-triangle-badge').css({ outline: '', outlineOffset: '' });
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.setOutline(document.getElementById('temu-price-gt-lmp-badge'), false);
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.setOutline(document.getElementById('temu-price-lt80-lmp-badge'), false);
            }
        }

        function temuClearPriceGtLmpCompetingFilters() {
            spriceLmpAlertFilterActive = false;
            blueTriangleFilterActive = false;
            temuResetCapBadgeOutlines();
            priceLt80LmpFilterActive = false;
            soldSpriceBlankFilterActive = false;
            temuAdsBadgeFilter = null;
            $('#nr-req-filter').val('all');
            $('#sold-filter').val('all');
            $('#gpft-filter').val('all');
            $('#roi-filter').val('all');
            $('#cvr-filter').val('all');
            if ($('#cvr-trend-filter').length) $('#cvr-trend-filter').val('all');
            $('#nrp-filter').val('all');
            $('#inventory-filter').val('all');
            $('#parent-filter').val('skus');
            $('#sku-search').val('');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $('.column-filter[data-column="dil_percent"][data-color="all"]').addClass('active');
            $('#temu-sprice-lmp-alert-badge').css({ outline: '', outlineOffset: '' });
            $('#temu-blue-triangle-badge').css({ outline: '', outlineOffset: '' });
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.setOutline(document.getElementById('temu-price-lt80-lmp-badge'), false);
            }
        }

        // Apply filters
        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            // When Play navigation is active, show only current parent (like pricing-master-cvr)
            if (isPlayNavigationActive) {
                if (typeof showCurrentParentPlayView === 'function') showCurrentParentPlayView();
                return;
            }

            const exclusiveTriangleFilter = spriceLmpAlertFilterActive || blueTriangleFilterActive
                || amzCapFilterActive || ebCapFilterActive
                || priceGtLmpFilterActive;
            const parentFilter = exclusiveTriangleFilter
                ? 'skus'
                : ($('#parent-filter').val() || 'skus');
            const inventoryFilter = exclusiveTriangleFilter ? 'all' : $('#inventory-filter').val();
            const gpftFilter = exclusiveTriangleFilter ? 'all' : $('#gpft-filter').val();
            const groiFilter = exclusiveTriangleFilter ? 'all' : $('#roi-filter').val();
            const cvrFilter = exclusiveTriangleFilter ? 'all' : $('#cvr-filter').val();
            const cvrTrendFilter = exclusiveTriangleFilter ? 'all' : $('#cvr-trend-filter').val();
            const dilFilter = exclusiveTriangleFilter
                ? 'all'
                : ($('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all');
            const skuSearch = exclusiveTriangleFilter ? '' : $('#sku-search').val();
            // When showing All Rows / Parents, keep parent summary rows visible even if a data filter would drop them
            const parentRowsBypassDataFilters = (parentFilter === 'all' || parentFilter === 'parents');
            // Clear all filters first
            table.clearFilter(true);

            // Row type: All Rows / Parents / SKUs (default SKUs — parent-only default is eBay 2 / 3 only)
            if (parentFilter === 'parents') {
                table.addFilter(function(data) {
                    return isTemuParentRow(data);
                });
            } else if (parentFilter === 'skus') {
                table.addFilter(function(data) {
                    return !isTemuParentRow(data);
                });
            }

            // SKU search filter (case-insensitive)
            if (skuSearch) {
                table.addFilter(function(data) {
                    const sku = data.sku || '';
                    return sku.toUpperCase().includes(skuSearch.toUpperCase());
                });
            }

            // Inventory filter. All Inventory must include 0-inv rows (not only INV > 0).
            if (inventoryFilter === 'gt0' || inventoryFilter === 'eq0') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    return inv === 0;
                });
            }

            // GPFT filter — Full Temu Price (same as GPRFT column)
            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const fullPrice = temuFullPriceFromRow(data);
                    const marginRaw = parseFloat(data.percentage);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                    const gpft = fullPrice > 0 ? ((fullPrice * margin - (parseFloat(data.lp) || 0) - (parseFloat(data.temu_ship) || 0)) / fullPrice) * 100 : 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40plus') return gpft >= 40;
                    return true;
                });
            }

            // ROI filter (GROI%) — buckets: <40, 40–60, 60–80, 80–100, 100+
            if (groiFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const groi = parseFloat(data.roi_percent) || 0;
                    if (groiFilter === 'lt40') return groi < 40;
                    if (groiFilter === '40-60') return groi >= 40 && groi < 60;
                    if (groiFilter === '60-80') return groi >= 60 && groi < 80;
                    if (groiFilter === '80-100') return groi >= 80 && groi < 100;
                    if (groiFilter === 'gt100') return groi >= 100;
                    return true;
                });
            }

            // CVR filter
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const cvr = parseFloat(data.cvr_percent) || 0;
                    const cvrRounded = Math.round(cvr * 100) / 100;
                    
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // CVR trend filter: CVR 30 vs CVR 60 (same as CVR column arrows / ebay2 — Down / Up / Same)
            if (cvrTrendFilter !== 'all') {
                const cvrTrendTol = 0.1;
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const cvr30 = parseFloat(data.cvr_30 || data.cvr_percent) || 0;
                    const cvr60 = parseFloat(data.cvr_60) || 0;
                    let trend = 'equal';
                    if (cvr30 > cvr60 + cvrTrendTol) trend = 'up';
                    else if (cvr30 < cvr60 - cvrTrendTol) trend = 'down';
                    if (cvrTrendFilter === 'down') return trend === 'down';
                    if (cvrTrendFilter === 'up') return trend === 'up';
                    if (cvrTrendFilter === 'same' || cvrTrendFilter === 'equal') return trend === 'equal';
                    return true;
                });
            }

            // DIL filter — buckets match /topdawg-tabulator (red < 25, green 25–50, pink ≥ 50).
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const dil = parseFloat(data['dil_percent']) || 0;
                    if (dilFilter === 'red')   return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink')  return dil >= 50;
                    return true;
                });
            }

            // Sold+SPRC Blank filter (if active)
            if (soldSpriceBlankFilterActive) {
                table.addFilter(function(data) {
                    const temuL30Val = data['temu_l30'];
                    const spriceVal = data['sprice'];
                    const invVal = data['inventory'];
                    
                    const temuL30 = temuL30Val ? parseInt(temuL30Val) : 0;
                    const inventory = invVal ? parseInt(invVal) : 0;
                    const spriceIsBlank = !spriceVal || spriceVal === '' || spriceVal === 0 || parseFloat(spriceVal) === 0;
                    
                    return inventory > 0 && temuL30 > 0 && spriceIsBlank;
                });
            }

            // Sold filter — driven by the #sold-filter dropdown (single source of truth).
            // The legacy #zero-sold-count-badge click just toggles this dropdown to "zero".
            // `zero` keeps the original badge semantics (INV > 0 required). `sold` is the new
            // option added for parity with the Amazon-style dropdown (no INV constraint).
            const soldFilter = exclusiveTriangleFilter ? 'all' : $('#sold-filter').val();
            if (soldFilter === 'zero') {
                table.addFilter(function(data) {
                    const temuL30 = parseInt(data['temu_l30']) || 0;
                    const inv = parseFloat(data['inventory']) || 0;
                    return temuL30 === 0 && inv > 0;
                });
            } else if (soldFilter === 'sold') {
                table.addFilter(function(data) {
                    return (parseInt(data['temu_l30']) || 0) > 0;
                });
            }

            if (spriceLmpAlertFilterActive) {
                table.addFilter(function(data) {
                    return temuSpriceHasLmpAlert(data);
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return temuHasBlueTriangle(data);
                });
            }
            if (amzCapFilterActive) {
                table.addFilter(function(data) {
                    return typeof temuHasAmzCap === 'function' && temuHasAmzCap(data);
                });
            }
            if (ebCapFilterActive) {
                table.addFilter(function(data) {
                    return typeof temuHasEbCap === 'function' && temuHasEbCap(data);
                });
            }
            if (priceGtLmpFilterActive) {
                table.addFilter(function(data) {
                    return temuRowHasPriceGtLmp(data);
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'temu_price');
                });
            }

            if (temuAdsBadgeFilter && !exclusiveTriangleFilter) {
                switch (temuAdsBadgeFilter) {
                    case 'all':
                        break;
                    case 'status-active':
                        table.addFilter(function(data) {
                            return (data.campaign_status || '').trim() === 'Active';
                        });
                        break;
                    case 'status-inactive':
                        table.addFilter(function(data) {
                            return (data.campaign_status || '').trim() === 'Inactive';
                        });
                        break;
                    case 'status-no-ad':
                        table.addFilter(function(data) {
                            return (data.campaign_status || '').trim() === 'No ad';
                        });
                        break;
                    case 'status-not-sync':
                        table.addFilter(function(data) {
                            return (data.campaign_status || '').trim() === 'Not sync';
                        });
                        break;
                    case 'campaign':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            return st === 'Active' || spend > 0 || adClicks > 0;
                        });
                        break;
                    case 'ad-sku':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const inv = parseFloat(data.inventory) || 0;
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return hasCampaign && inv > 0;
                        });
                        break;
                    case 'missing':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            const inv = parseFloat(data.inventory) || 0;
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return !hasCampaign && inv > 0 && nr !== 'NRL' && nr !== 'NR';
                        });
                        break;
                    case 'nra-missing':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const adClicks = parseInt(data.ad_clicks, 10) || 0;
                            const st = (data.campaign_status || '').trim();
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            const hasCampaign = st === 'Active' || spend > 0 || adClicks > 0;
                            return !hasCampaign && (nr === 'NRL' || nr === 'NR');
                        });
                        break;
                    case 'zero-inv':
                        table.addFilter(function(data) {
                            const inv = parseFloat(data.inventory) || 0;
                            return inv <= 0;
                        });
                        break;
                    case 'nra':
                        table.addFilter(function(data) {
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            return nr === 'NRL' || nr === 'NR';
                        });
                        break;
                    case 'ra':
                        table.addFilter(function(data) {
                            const nr = (data.nr_req || '').trim().toUpperCase();
                            return nr === 'REQ';
                        });
                        break;
                    case 'total-spend':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            return spend > 0;
                        });
                        break;
                    case 'budget':
                        table.addFilter(function(data) {
                            const t = data.target;
                            return t !== null && t !== undefined && t !== '' && (parseFloat(t) || 0) > 0;
                        });
                        break;
                    case 'ad-sales':
                    case 'avg-acos':
                    case 'roas':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const outRoas = parseFloat(data.out_roas_l30) || 0;
                            return spend > 0 && outRoas > 0;
                        });
                        break;
                    case 'ad-clicks':
                        table.addFilter(function(data) {
                            const clicks = parseInt(data.ad_clicks, 10) || 0;
                            return clicks > 0;
                        });
                        break;
                }
            }

            // NRL/REQ filter. Zero-inv rows default to NRL on the backend, so All Inventory
            // would otherwise still hide them while REQ is selected.
            const nrReqFilter = exclusiveTriangleFilter ? 'all' : $('#nr-req-filter').val();
            if (nrReqFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'all' && inv <= 0) return true;
                    const nr_req = data['nr_req'] || 'REQ';
                    const dataValue = (nr_req === 'NR' || nr_req === 'NRL') ? 'NRL' : nr_req;
                    return dataValue === nrReqFilter;
                });
            }

            // NRP filter — matches the same values stored on row.nrp (REQ/NR/LATER).
            // Empty / unknown values are treated as REQ to mirror the NRP column formatter,
            // so the filter and the dot color always agree.
            const nrpFilter = exclusiveTriangleFilter ? 'all' : $('#nrp-filter').val();
            if (nrpFilter && nrpFilter !== 'all') {
                table.addFilter(function(data) {
                    let v = String(data['nrp'] || '').trim().toUpperCase();
                    if (v !== 'REQ' && v !== 'NR' && v !== 'LATER') v = 'REQ';
                    return v === nrpFilter;
                });
            }

            updateSummary();
            updateSelectAllCheckbox();
            
            // Show search result info
            if (skuSearch) {
                const resultCount = table.getData('active').length;
                const totalCount = table.getData('all').length;
                
                if (resultCount === 0) {
                    $('#search-result-info').html(`<i class="fas fa-exclamation-triangle text-warning"></i> No results found for "${skuSearch}". SKU may not exist in product_master table.`).show();
                } else {
                    $('#search-result-info').html(`Found ${resultCount} result(s) matching "${skuSearch}"`).show();
                }
            } else {
                $('#search-result-info').hide();
            }

            applySelectedColumnVisibility();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#temu-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) temuClearPriceGtLmpCompetingFilters();
                    if (on && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                        stopPlayNavigation();
                        return;
                    }
                    applyFilters();
                }
            });
        }
        $('#temu-price-gt-lmp-badge').on('click', function(e) {
            if ($(e.target).closest('.summary-trend-dot, .kpi-status-dot').length) return;
            if (this.dataset.pglBound === '1') return;
            priceGtLmpFilterActive = !priceGtLmpFilterActive;
            if (priceGtLmpFilterActive) temuClearPriceGtLmpCompetingFilters();
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.setOutline(this, priceGtLmpFilterActive);
            }
            if (priceGtLmpFilterActive && isPlayNavigationActive && typeof stopPlayNavigation === 'function') {
                stopPlayNavigation();
                return;
            }
            applyFilters();
        });
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#temu-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                                        applyFilters();
                }
            });
        }

        // ==================== Play/Pause parent navigation (same as pricing-master-cvr) ====================
        // Group key = parent + SKU prefix (WF/FR etc) so FR and WF SKUs don't mix in same play group
        function getRowGroupKey(row) {
            const p = (row.parent != null && row.parent !== '') ? row.parent : (row.sku || '');
            const prefix = (row.sku || '').trim().split(/\s+/)[0] || '';
            return (p || '') + '|' + prefix;
        }

        function getParentRows() {
            if (!fullDataset || fullDataset.length === 0) return [];
            const seen = new Set();
            const out = [];
            fullDataset.forEach(row => {
                const key = getRowGroupKey(row);
                if (key !== '|' && !seen.has(key)) {
                    seen.add(key);
                    out.push({ parent: key });
                }
            });
            return out;
        }

        function showCurrentParentPlayView() {
            if (!fullDataset || fullDataset.length === 0) return;
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            const currentGroupKey = parentRows[currentPlayParentIndex].parent;
            const displayData = fullDataset.filter(row => getRowGroupKey(row) === currentGroupKey);
            suppressDataLoadedHandler = true;
            table.clearSort();
            table.setData(displayData).then(() => {
                updateSummary();
                updatePlayButtonStates();
            });
        }

        function startPlayNavigation() {
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            isPlayNavigationActive = true;
            currentPlayParentIndex = 0;
            showCurrentParentPlayView();
            $('#play-auto').hide();
            $('#play-pause').show();
            updatePlayButtonStates();
        }

        function stopPlayNavigation() {
            isPlayNavigationActive = false;
            currentPlayParentIndex = 0;
            $('#play-pause').hide();
            $('#play-auto').show();
            $('#play-backward, #play-forward').prop('disabled', true);
            if (fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(applyFilters);
            } else {
                applyFilters();
            }
        }

        function updatePlayButtonStates() {
            const parentRows = getParentRows();
            $('#play-backward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex >= parentRows.length - 1);
            $('#play-auto').attr('title', isPlayNavigationActive ? 'Show all' : 'Start parent navigation');
            $('#play-pause').attr('title', 'Stop navigation and show all');
        }

        function playNextParent() {
            if (!isPlayNavigationActive) return;
            const parentRows = getParentRows();
            if (currentPlayParentIndex >= parentRows.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            if (!isPlayNavigationActive) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', startPlayNavigation);
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        // LMP Modal: Add New form + table list; lowest row highlighted with LOWEST badge
        let lmpModalSku = '';
        let lmpModalOurPrice = 0;
        let lmpModalBuyerLink = '';
        function lmpDilColorClass(dil) {
            if (dil < 25) return 'red';
            if (dil < 50) return 'green';
            return 'pink';
        }
        function lmpCvrColorClass(cvr) {
            if (cvr <= 4) return 'red';
            if (cvr <= 7) return 'yellow';
            if (cvr <= 13) return 'green';
            return 'pink';
        }
        function setLmpHeaderMetric(elId, text, colorClass) {
            const $el = $(elId);
            $el.text(text);
            $el.removeClass('red yellow blue green pink purple muted');
            $el.addClass(colorClass || 'muted');
        }
        function openLmpModal(row) {
            row = row || {};
            lmpModalSku = row.sku || '';
            $('#lmpModalSku').text(lmpModalSku);

            // Header metrics: Dil%, NROI%, NPFT%, CVR%
            const dil = parseFloat(row.dil_percent);
            const nroi = parseFloat(row.nroi_percent);
            const npft = parseFloat(row.npft_percent);
            const cvr = parseFloat(row.cvr_percent != null ? row.cvr_percent : row.cvr_30);
            setLmpHeaderMetric('#lmpHeaderDil', isNaN(dil) ? '—' : (Math.round(dil) + '%'), isNaN(dil) ? 'muted' : lmpDilColorClass(dil));
            setLmpHeaderMetric('#lmpHeaderNroi', isNaN(nroi) ? '—' : (Math.round(nroi) + '%'), isNaN(nroi) ? 'muted' : getRoiColor(nroi));
            setLmpHeaderMetric('#lmpHeaderNpft', isNaN(npft) ? '—' : (Math.round(npft) + '%'), isNaN(npft) ? 'muted' : getPftColor(npft));
            setLmpHeaderMetric('#lmpHeaderCvr', isNaN(cvr) ? '—' : (cvr.toFixed(1) + '%'), isNaN(cvr) ? 'muted' : lmpCvrColorClass(cvr));

            // Top-left: product image badge
            const $imgBadge = $('#lmpModalProductBadge');
            const imgSrc = String(row.image_path || '').trim();
            if (imgSrc) {
                $imgBadge.html('<img src="' + imgSrc.replace(/"/g, '&quot;') + '" alt="Product">');
            } else {
                $imgBadge.html('<span class="lmp-no-image">No image</span>');
            }

            // Top-right: our current Temu price badge (same FB Prc rule as the grid)
            const base = parseFloat(row.base_price) || 0;
            lmpModalOurPrice = base > 0 ? (base <= 26.99 ? base + 2.99 : base) : 0;
            $('#lmpModalOurPrice').text(lmpModalOurPrice > 0 ? ('$' + lmpModalOurPrice.toFixed(2)) : '—');
            lmpModalBuyerLink = String(row.buyer_link || '').trim();

            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
            $('#lmpSelectAllCb').prop('checked', false).prop('indeterminate', false);
            const tbody = $('#lmpEntriesContainer');
            tbody.empty();
            let entries = Array.isArray(row.lmp_entries) ? row.lmp_entries.slice() : [];
            // Legacy fallback: lmp / lmp_link when JSON entries are missing
            if (entries.length === 0) {
                const legacyPrice = row.lmp_raw != null ? row.lmp_raw : row.lmp;
                const legacyLink = row.lmp_link || '';
                if ((legacyPrice !== null && legacyPrice !== undefined && legacyPrice !== '') || legacyLink) {
                    entries = [{ price: legacyPrice, delivery: 0, link: legacyLink, ignored: false }];
                }
            }
            entries.forEach(function(entry) {
                const p = entry.price !== undefined && entry.price !== null ? parseFloat(entry.price) : NaN;
                let del = entry.delivery !== undefined && entry.delivery !== null && entry.delivery !== ''
                    ? entry.delivery
                    : '';
                // Prefill default Del $2.99 when Price < $27 and no saved delivery
                if ((del === '' || del == null || !(parseFloat(del) > 0)) && !isNaN(p) && p > 0 && p < 27) {
                    del = 2.99;
                }
                appendLmpTableRow(
                    tbody,
                    entry.price !== undefined && entry.price !== null ? entry.price : '',
                    del,
                    entry.link || '',
                    !!entry.ignored,
                    false,
                    entry.source_sku || lmpModalSku || ''
                );
            });
            updateLmpListLayout();
            $('#lmpModal').modal('show');
            // Ensure list starts at top so L1 rows are visible
            setTimeout(function() {
                const scroller = document.querySelector('#lmpModal .lmp-list-scroll');
                if (scroller) scroller.scrollTop = 0;
            }, 150);
        }
        function appendLmpTableRow(tbody, price, delivery, link, ignored, relayout, sourceSku) {
            const tr = $('<tr class="lmp-entry-row">' +
                '<td class="text-center"><input type="checkbox" class="form-check-input lmp-row-cb m-0" title="Select for bulk delete"></td>' +
                '<td class="lmp-num text-center"></td>' +
                '<td><div class="lmp-price-cell">' +
                    '<input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-price border-0 bg-transparent" placeholder="Price">' +
                    '<span class="lmp-lowest-badge"></span>' +
                '</div></td>' +
                '<td class="text-center"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-delivery border-0 bg-transparent text-center" placeholder="0.00" title="Added to Price for LMP"></td>' +
                '<td class="text-center"><span class="lmp-price-d text-muted">—</span></td>' +
                '<td><div class="lmp-link-cell">' +
                    '<input type="text" class="form-control form-control-sm lmp-link border-0 bg-transparent" placeholder="https://..." autocomplete="off">' +
                    '<a href="#" class="btn btn-sm btn-outline-primary lmp-open-link" target="_blank" rel="noopener" title="Open link"><i class="fas fa-external-link-alt"></i></a>' +
                '</div></td>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input lmp-ignore m-0" title="Ignore for L1"></td>' +
                '<td class="text-center text-nowrap">' +
                    '<button type="button" class="btn btn-sm btn-danger lmp-remove-row" title="Delete this LMP row">' +
                    '<i class="fas fa-trash-alt me-1"></i>Delete</button>' +
                '</td></tr>');
            tr.attr('data-source-sku', sourceSku || lmpModalSku || '');
            tr.find('.lmp-price').val(price !== '' && price != null ? price : '');
            tr.find('.lmp-delivery').val(delivery !== '' && delivery != null ? delivery : '');
            tr.find('.lmp-link').val(link || '');
            tr.find('.lmp-ignore').prop('checked', !!ignored);
            if (ignored) tr.addClass('lmp-ignored');
            tbody.append(tr);
            if (relayout !== false) updateLmpListLayout();
            else updateLmpBulkDeleteUi();
        }
        function updateLmpBulkDeleteUi() {
            const $rows = $('#lmpEntriesContainer .lmp-entry-row');
            const $checked = $rows.find('.lmp-row-cb:checked');
            const n = $checked.length;
            const total = $rows.length;
            $('#lmpBulkDeleteBtn').prop('disabled', n === 0)
                .html(n > 0
                    ? ('<i class="fas fa-trash-alt me-1"></i>Delete Selected (' + n + ')')
                    : '<i class="fas fa-trash-alt me-1"></i>Delete Selected');
            const $all = $('#lmpSelectAllCb');
            if (!$all.length) return;
            $all.prop('checked', total > 0 && n === total);
            $all.prop('indeterminate', n > 0 && n < total);
        }
        function collectLmpEntriesFromModal() {
            const entries = [];
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const $tr = $(this);
                const price = $tr.find('.lmp-price').val();
                const delivery = $tr.find('.lmp-delivery').val();
                const link = $tr.find('.lmp-link').val();
                const ignored = $tr.find('.lmp-ignore').is(':checked');
                const sourceSku = ($tr.attr('data-source-sku') || lmpModalSku || '').trim();
                if (price || link || delivery) {
                    const deliveryNum = delivery !== '' && delivery != null ? parseFloat(delivery) : 0;
                    entries.push({
                        price: price ? parseFloat(price) : null,
                        delivery: (!isNaN(deliveryNum) && deliveryNum > 0) ? deliveryNum : 0,
                        link: link ? link.trim() : null,
                        ignored: ignored,
                        source_sku: sourceSku || lmpModalSku
                    });
                }
            });
            return entries;
        }
        function saveLmpEntries(opts) {
            opts = opts || {};
            const entries = collectLmpEntriesFromModal();
            if (!lmpModalSku) {
                showToast('Missing SKU — reopen the LMP modal', 'error');
                return $.Deferred().reject().promise();
            }
            if (entries.length === 0 && !opts.allowEmpty && !confirm('Save empty LMP list? This deletes all Temu LMP entries for this SKU group.')) {
                return $.Deferred().reject().promise();
            }
            const $btn = $('#lmpModalSaveBtn');
            const $bulk = $('#lmpBulkDeleteBtn');
            $btn.prop('disabled', true);
            $bulk.prop('disabled', true);
            return $.ajax({
                url: '{{ route("temu.lmp.save") }}',
                method: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                data: JSON.stringify({
                    sku: lmpModalSku,
                    lmp_entries: entries
                })
            }).done(function(response) {
                if (response && response.success) {
                    showToast(opts.successMsg || response.message || 'LMP saved successfully', 'success');
                    if (opts.closeModal !== false) {
                        $('#lmpModal').modal('hide');
                    }
                    if (table) table.replaceData();
                } else {
                    showToast((response && (response.message || response.error)) || 'Failed to save LMP', 'error');
                }
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                    || 'Failed to save LMP';
                showToast(msg, 'error');
            }).always(function() {
                $btn.prop('disabled', false);
                updateLmpBulkDeleteUi();
            });
        }
        function updateLmpPriceDDisplay(tr) {
            const $el = $(tr).find('.lmp-price-d');
            if (!$el.length) return;
            const total = getLmpEntryPrice(tr);
            if (total === null) {
                $el.text('—').addClass('text-muted').removeClass('text-dark');
            } else {
                $el.text('$' + Number(total).toFixed(2)).removeClass('text-muted').addClass('text-dark');
            }
        }
        let lmpLayoutTimer = null;
        function scheduleLmpListLayout() {
            clearTimeout(lmpLayoutTimer);
            lmpLayoutTimer = setTimeout(function() { updateLmpListLayout(); }, 200);
        }
        // Delegated handlers so delete/open/ignore keep working after rows are re-sorted in the DOM
        $('#lmpEntriesContainer')
            .off('click.lmpActions input.lmpActions change.lmpActions')
            .on('click.lmpActions', '.lmp-remove-row', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!confirm('Delete this LMP row?')) return;
                clearTimeout(lmpLayoutTimer);
                $(this).closest('tr.lmp-entry-row').remove();
                updateLmpListLayout();
                // Persist immediately (empty list clears DB for this SKU group)
                saveLmpEntries({
                    allowEmpty: true,
                    closeModal: false,
                    successMsg: 'LMP row deleted'
                });
            })
            .on('click.lmpActions', '.lmp-open-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const href = ($(this).closest('tr').find('.lmp-link').val() || '').trim();
                if (href && (href.startsWith('http://') || href.startsWith('https://'))) window.open(href, '_blank');
            })
            .on('change.lmpActions', '.lmp-ignore', function() {
                const tr = $(this).closest('tr.lmp-entry-row');
                tr.toggleClass('lmp-ignored', $(this).is(':checked'));
                updateLmpLowestHighlight();
            })
            .on('change.lmpActions', '.lmp-row-cb', function() {
                updateLmpBulkDeleteUi();
            })
            .on('input.lmpActions', '.lmp-price, .lmp-delivery, .lmp-link', function() {
                scheduleLmpListLayout();
            });
        $('#lmpSelectAllCb').off('change.lmpSelectAll').on('change.lmpSelectAll', function() {
            const checked = $(this).is(':checked');
            $('#lmpEntriesContainer .lmp-entry-row .lmp-row-cb').prop('checked', checked);
            updateLmpBulkDeleteUi();
        });
        $('#lmpBulkDeleteBtn').off('click.lmpBulkDelete').on('click.lmpBulkDelete', function() {
            const $selected = $('#lmpEntriesContainer .lmp-entry-row').has('.lmp-row-cb:checked');
            const n = $selected.length;
            if (!n) {
                showToast('Select at least one LMP row to delete', 'warning');
                return;
            }
            if (!confirm('Delete ' + n + ' selected LMP row' + (n === 1 ? '' : 's') + '?')) return;
            clearTimeout(lmpLayoutTimer);
            $selected.remove();
            $('#lmpSelectAllCb').prop('checked', false).prop('indeterminate', false);
            updateLmpListLayout();
            saveLmpEntries({
                allowEmpty: true,
                closeModal: false,
                successMsg: n + ' LMP row' + (n === 1 ? '' : 's') + ' deleted'
            });
        });
        /** Effective LMP for sorting / L1 = Price + Delivery (default Del $2.99 when Price < $27). */
        function getLmpEntryPrice(tr) {
            const val = $(tr).find('.lmp-price').val();
            const num = val !== '' && val != null ? parseFloat(val) : NaN;
            if (isNaN(num)) return null;
            const dVal = $(tr).find('.lmp-delivery').val();
            let delivery = dVal !== '' && dVal != null ? parseFloat(dVal) : 0;
            if (isNaN(delivery) || delivery < 0) delivery = 0;
            if (delivery <= 0 && num < 27) delivery = 2.99;
            return num + delivery;
        }
        /** Sort competitor rows by price, insert blue 5 Core row at our price position, renumber + LOWEST. */
        function updateLmpListLayout() {
            clearTimeout(lmpLayoutTimer);
            const tbody = $('#lmpEntriesContainer');
            const $active = $(document.activeElement);
            const activeIsLmpInput = $active.hasClass('lmp-price') || $active.hasClass('lmp-delivery') || $active.hasClass('lmp-link');
            const activeRow = activeIsLmpInput ? $active.closest('tr.lmp-entry-row')[0] : null;
            let activeClass = 'lmp-link';
            if (activeIsLmpInput && $active.hasClass('lmp-price')) activeClass = 'lmp-price';
            else if (activeIsLmpInput && $active.hasClass('lmp-delivery')) activeClass = 'lmp-delivery';
            const selStart = activeIsLmpInput ? $active[0].selectionStart : null;
            const selEnd = activeIsLmpInput ? $active[0].selectionEnd : null;

            tbody.find('.lmp-five-core-row').remove();

            const entryRows = tbody.find('.lmp-entry-row').get();
            entryRows.sort(function(a, b) {
                const pa = getLmpEntryPrice(a);
                const pb = getLmpEntryPrice(b);
                if (pa === null && pb === null) return 0;
                if (pa === null) return 1;
                if (pb === null) return -1;
                return pa - pb;
            });
            entryRows.forEach(function(tr) { tbody.append(tr); });

            if (lmpModalOurPrice > 0) {
                const buyerHref = lmpModalBuyerLink;
                const buyerEsc = buyerHref.replace(/"/g, '&quot;');
                const linkCell = buyerHref
                    ? ('<div class="lmp-link-cell">' +
                       '<input type="text" class="form-control form-control-sm lmp-link bg-transparent border-0 text-primary" readonly value="' + buyerEsc + '" title="' + buyerEsc + '">' +
                       '<a href="' + buyerEsc + '" class="btn btn-sm btn-outline-primary lmp-five-core-open-link" target="_blank" rel="noopener" title="Open buyer link"><i class="fas fa-external-link-alt"></i></a>' +
                       '</div>')
                    : '<span class="text-muted small"><i class="fas fa-store me-1"></i>No buyer link</span>';
                const fiveCoreTr = $('<tr class="lmp-five-core-row">' +
                    '<td class="text-center text-muted small">—</td>' +
                    '<td class="lmp-num text-center">—</td>' +
                    '<td><div class="lmp-price-cell">' +
                    '<span class="lmp-five-core-price">$' + lmpModalOurPrice.toFixed(2) + '</span>' +
                    '<span class="badge bg-primary">5 CORE</span></div></td>' +
                    '<td class="text-center text-muted small">—</td>' +
                    '<td class="text-center"><span class="lmp-price-d">$' + lmpModalOurPrice.toFixed(2) + '</span></td>' +
                    '<td>' + linkCell + '</td>' +
                    '<td class="text-center text-muted small">—</td>' +
                    '<td class="text-center text-muted small">buyer</td></tr>');

                let inserted = false;
                tbody.find('.lmp-entry-row').each(function() {
                    if (inserted) return;
                    const p = getLmpEntryPrice(this);
                    if (p !== null && p >= lmpModalOurPrice) {
                        fiveCoreTr.insertBefore(this);
                        inserted = true;
                    }
                });
                if (!inserted) tbody.append(fiveCoreTr);
            }

            tbody.find('.lmp-entry-row').each(function() { updateLmpPriceDDisplay(this); });
            renumberLmpRows();
            updateLmpLowestHighlight();

            // Keep caret in the field being edited after DOM reorder
            if (activeRow && document.body.contains(activeRow)) {
                const $field = $(activeRow).find('.' + activeClass);
                if ($field.length) {
                    $field.trigger('focus');
                    try {
                        if (selStart != null && selEnd != null && $field[0].setSelectionRange) {
                            $field[0].setSelectionRange(selStart, selEnd);
                        }
                    } catch (err) { /* ignore */ }
                }
            }
        }
        function renumberLmpRows() {
            const n = $('#lmpEntriesContainer .lmp-entry-row').length;
            $('#lmpEntriesContainer .lmp-entry-row').each(function(i) {
                $(this).find('.lmp-num').text(i + 1);
            });
            $('#lmpListCountBadge').text(String(n));
            updateLmpBulkDeleteUi();
        }
        function updateLmpLowestHighlight() {
            let minVal = null;
            let minTr = null;
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const tr = $(this);
                tr.removeClass('table-dark');
                tr.find('.lmp-lowest-badge').empty();
                const ignored = tr.find('.lmp-ignore').is(':checked');
                tr.toggleClass('lmp-ignored', ignored);
                if (ignored) return;
                const num = getLmpEntryPrice(tr);
                if (num !== null) {
                    if (minVal === null || num < minVal) { minVal = num; minTr = tr; }
                }
            });
            if (minTr && minVal !== null) {
                minTr.find('.lmp-lowest-badge').html('<span class="badge bg-info">L1</span>');
            }
            $('#lmpModalL1Price').text(minVal !== null ? ('$' + Number(minVal).toFixed(2)) : '—');
            renumberLmpRows();
        }
        $('#lmpAddRowBtn').on('click', function() {
            const price = $('#lmpNewPrice').val();
            const delivery = $('#lmpNewDelivery').val();
            const link = $('#lmpNewLink').val();
            if (!price && !link) {
                showToast('Enter Price or Link', 'warning');
                return;
            }
            appendLmpTableRow($('#lmpEntriesContainer'), price || '', delivery || '', link || '', false, true, lmpModalSku || '');
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
            // Scroll new/lowest rows into view inside the list
            const scroller = document.querySelector('#lmpModal .lmp-list-scroll');
            if (scroller) scroller.scrollTop = 0;
            showToast('Added to list — click Save to store', 'info');
        });
        $('#lmpClearFormBtn').on('click', function() {
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
        });
        $('#lmpModalSaveBtn').on('click', function() {
            saveLmpEntries({ closeModal: true });
        });

        $('#parent-filter, #inventory-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #nr-req-filter, #nrp-filter, #sold-filter').on('change', function() {
            applyFilters();
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const column = $item.data('column');
            const color = $item.data('color');
            const dropdown = $item.closest('.dropdown');
            const button = dropdown.find('.dropdown-toggle');
            
            dropdown.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            applyFilters();
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                if (data.is_parent) return;
                const sku = data.sku || data['(Child) sku'] || data.SKU;
                const std = parseFloat(value);
                if (!sku || !isFinite(std) || std <= 0) {
                    row.update({ STANDARD_PRICE: null });
                    return;
                }
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: std,
                        is_standard_price: 1
                    },
                    success: function(response) {
                        const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                        applyTemu1StandardPriceToLinkedRows(sku, saved, response.applied_skus);
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        showToast(n > 1 ? ('Std Prc saved for ' + n + ' linked SKUs') : 'Std Prc saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Std Prc', 'error');
                    }
                });
                return;
            }

            if (field === 'base_price') {
                const newPrice = parseFloat(cell.getValue());
                if (newPrice < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                $.ajax({
                    url: '/temu-pricing/update-price',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        base_price: newPrice
                    },
                    success: function(response) {
                        showToast('Price updated successfully', 'success');
                        updateSummary();
                    },
                    error: function(xhr) {
                        showToast('Failed to update price', 'error');
                        cell.restoreOldValue();
                    }
                });
            }
            
            // Handle SPRICE edit
            if (field === 'sprice') {
                const cellValue = cell.getValue();
                
                // Check if the value is empty/null/blank (user is clearing the field)
                if (cellValue === '' || cellValue === null || cellValue === undefined || String(cellValue).trim() === '') {
                    // Clear the sprice from database
                    $.ajax({
                        url: '/temu-clear-sprice',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            skus: [data['sku']]
                        },
                        success: function(response) {
                            // Update row to reflect cleared values
                            row.update({ 
                                sprice: null,
                                spft_percent: null,
                                sroi_percent: null
                            });
                            row.reformat();
                            showToast('SPRICE cleared successfully', 'success');
                        },
                        error: function(xhr) {
                            showToast('Failed to clear SPRICE', 'error');
                            cell.restoreOldValue();
                        }
                    });
                    return;
                }
                
                let newSprice = parseFloat(cellValue);
                
                // Check if the parsed value is a valid number
                if (isNaN(newSprice)) {
                    showToast('SPRICE must be a valid number', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                if (newSprice < 0) {
                    showToast('SPRICE cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }

                newSprice = temuPrepareSpriceForSave(data, newSprice);
                
                row.update({ sprice: newSprice, push_status: null });
                row.reformat();
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        sprice: newSprice
                    },
                    success: function(response) {
                        showToast('SPRICE saved successfully', 'success');
                    },
                    error: function(xhr) {
                        showToast('Failed to save SPRICE', 'error');
                    }
                });
            }

        });

        // --- Temu price push: SPRICE → base via inverse of Temu Price ---
        function pushTemuPriceForRow(row, price) {
            const data = row.getData();
            const sku = data.sku;
            const goodsId = data.goods_id || '';
            const skuId = data.sku_id || '';
            // Push from S PRC capped at LMP (same value shown in the S PRC column).
            const cappedSprice = typeof temuDisplayedSprice === 'function'
                ? temuDisplayedSprice(data)
                : (parseFloat(data.sprice) || 0);
            const fromSprice = temuPushBaseFromSprice(cappedSprice);
            const raw = parseFloat(price);
            let pushPrice = fromSprice;
            if (pushPrice == null && isFinite(raw) && raw > 0) pushPrice = +raw.toFixed(2);
            if (!sku || !(pushPrice > 0)) {
                return Promise.reject({ message: 'SKU and price required' });
            }

            row.update({ push_status: 'pushing' });
            row.reformat();

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: '/temu/push-price',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        price: pushPrice,
                        goods_id: goodsId,
                        sku_id: skuId
                    },
                    success: function(response) {
                        if (response && response.success) {
                            // API-only: do not change local Base Price / Temu Price columns
                            row.update({ push_status: 'pushed' });
                            row.reformat();
                            resolve(response);
                        } else {
                            row.update({ push_status: 'error' });
                            row.reformat();
                            reject({ message: (response && response.message) || 'Push failed' });
                        }
                    },
                    error: function(xhr) {
                        row.update({ push_status: 'error' });
                        row.reformat();
                        const msg = xhr.responseJSON?.message
                            || xhr.responseJSON?.errors?.[0]?.message
                            || 'Push failed';
                        reject({ message: msg });
                    }
                });
            });
        }

        $(document).on('click', '.temu-push-single-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $btn = $(this);
            const sku = $btn.data('sku');
            const row = table.getRows().find(function(r) {
                return String(r.getData().sku || '') === String(sku);
            });
            if (!row) return;
            const sprice = typeof temuDisplayedSprice === 'function'
                ? temuDisplayedSprice(row.getData())
                : (parseFloat(row.getData().sprice) || 0);
            const pushBase = temuPushBaseFromSprice(sprice);
            if (pushBase == null || pushBase <= 0) {
                showToast('Cannot push — invalid or negative S Temu B Prc', 'error');
                return;
            }
            if (!confirm(
                'Push Temu base $' + pushBase.toFixed(2)
                + ' (inverse of Temu Price from SPRICE $' + sprice.toFixed(2) + ')'
                + ' for SKU: ' + sku + '?'
            )) return;

            pushTemuPriceForRow(row, sprice).then(function() {
                showToast('Price pushed to Temu', 'success');
                if (typeof updateSummary === 'function') updateSummary();
            }).catch(function(err) {
                showToast(err.message || 'Failed to push price', 'error');
            });
        });

        $('#push-temu-price-btn').on('click', function() {
            const items = [];
            table.getRows('active').forEach(function(row) {
                const d = row.getData();
                if (d.is_parent) return;
                const sprice = typeof temuDisplayedSprice === 'function'
                    ? temuDisplayedSprice(d)
                    : (parseFloat(d.sprice) || 0);
                const pushBase = temuPushBaseFromSprice(sprice);
                if (sprice > 0 && pushBase != null && pushBase > 0 && d.push_status !== 'pushed') {
                    items.push({ row: row, price: sprice, sku: d.sku, pushBase: pushBase });
                }
            });

            if (items.length === 0) {
                showToast('No rows with SPRICE to push (or all already pushed)', 'warning');
                return;
            }

            if (!confirm(
                'Push Temu base for ' + items.length + ' SKU(s)?\n'
                + 'Base = inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)'
            )) return;

            const $btn = $('#push-temu-price-btn');
            $btn.prop('disabled', true);
            let ok = 0, fail = 0;
            let i = 0;

            function next() {
                if (i >= items.length) {
                    $btn.prop('disabled', false);
                    showToast('Temu push done: ' + ok + ' ok, ' + fail + ' failed', fail ? 'warning' : 'success');
                    if (typeof updateSummary === 'function') updateSummary();
                    return;
                }
                const item = items[i++];
                pushTemuPriceForRow(item.row, item.price).then(function() {
                    ok++;
                    setTimeout(next, 250);
                }).catch(function() {
                    fail++;
                    setTimeout(next, 250);
                });
            }
            next();
        });

        /*
         * NRP dropdown change handler — saves to the SAME endpoint /forecast.analysis
         * uses (POST /update-forecast-data with column='NR'), so both pages stay in
         * sync and you can edit from either side. Optimistically updates the row's
         * `nrp` field + reformats the cell so the dot color flips instantly; if the
         * AJAX fails we revert and show an error toast.
         */
        $(document).on('change', '.temu-nrp-select', function() {
            const $select = $(this);
            const newValue = String($select.val() || '').trim().toUpperCase();
            const sku = $select.data('sku');
            const parent = $select.data('parent') || '';
            if (!sku) return;
            if (!['REQ', 'NR', 'LATER'].includes(newValue)) return;

            // Find the Tabulator row and remember the old value so we can revert on failure.
            const tabRow = table ? table.getRows().find(function(r) { return (r.getData().sku || '') === sku; }) : null;
            const oldValue = tabRow ? ((tabRow.getData().nrp || '') || 'REQ') : 'REQ';

            // Optimistic update so the dot flips immediately.
            if (tabRow) {
                tabRow.update({ nrp: newValue });
                tabRow.reformat();
            }

            $.ajax({
                url: '/update-forecast-data',
                method: 'POST',
                data: {
                    sku: sku,
                    parent: parent,
                    column: 'NR',
                    value: newValue,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    if (!res || res.success === false) {
                        if (tabRow) {
                            tabRow.update({ nrp: oldValue });
                            tabRow.reformat();
                        }
                        showToast((res && res.message) || 'Failed to save NRP', 'error');
                        return;
                    }
                    showToast(`NRP saved: ${newValue === 'NR' ? '2BDC' : newValue} for ${sku}`, 'success');
                },
                error: function() {
                    if (tabRow) {
                        tabRow.update({ nrp: oldValue });
                        tabRow.reformat();
                    }
                    showToast('Failed to save NRP', 'error');
                }
            });
        });

        // NR/REQ dropdown change handler (Amazon style)
        $(document).on('change', '.nr-select', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');

            // Save to database
            $.ajax({
                url: '/temu-decrease/save-listing-status',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    nr_req: value
                },
                success: function(response) {
                    const message = response.message || 'NR/REQ updated successfully';
                    showToast(message, 'success');
                },
                error: function(xhr) {
                    showToast('Failed to update NR/REQ', 'error');
                }
            });
        });

        // ---- Edit B/S Links (double-click on Links cell) ----
        let temuEditLinksRow = null;
        window.openTemuEditLinksModal = function(row) {
            if (!row) return;
            temuEditLinksRow = row;
            const d = row.getData();
            $('#temuEditLinksSku').val(d.sku);
            $('#temuEditLinksSkuDisplay').text(d.sku);
            $('#temuEditSellerLink').val(d.seller_link || '');
            $('#temuEditBuyerLink').val(d.buyer_link || '');
            $('#temuEditLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('temuEditLinksModal')).show();
        };

        $(document).on('click', '#temuSaveLinksBtn', function() {
            const sku = $('#temuEditLinksSku').val();
            const sellerLink = $('#temuEditSellerLink').val().trim();
            const buyerLink = $('#temuEditBuyerLink').val().trim();
            const $err = $('#temuEditLinksError');
            $err.hide().text('');
            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '/temu-decrease/save-links',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { sku: sku, seller_link: sellerLink, buyer_link: buyerLink },
                success: function(res) {
                    if (temuEditLinksRow) {
                        temuEditLinksRow.update({ seller_link: res.seller_link || '', buyer_link: res.buyer_link || '' })
                            .then(function() { temuEditLinksRow.reformat(); })
                            .catch(function() { temuEditLinksRow.reformat(); });
                    }
                    showToast(sku + ': links saved', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('temuEditLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        window.iconClicked = false;

        /*
         * Column visibility — 4 groups (Basics / Pricing / Advertisement / Others)
         * with group-header checkboxes to select/deselect an entire group.
         * Persists via /tabulator-column-visibility (channel = 'temu_decrease').
         */
        const COL_VIS_CATEGORY_KEYS = ['basics', 'pricing', 'advertisement', 'others'];
        const COL_VIS_CATEGORY_LABELS = {
            basics: 'Basics',
            pricing: 'Pricing',
            advertisement: 'Advertisement',
            others: 'Others'
        };

        function classifyTemuColumn(field, title) {
            const f = String(field || '');
            const t = String(title || field || '').replace(/<[^>]*>/g, '');
            const fl = f.toLowerCase();
            const tl = t.toLowerCase();

            // Advertisement
            if (
                /^(spend|ad_sold_l60|ad_sales_l60|acos_ad|ad_clicks|clicks_l7|pause_run|pause_run_ok|impressions|add_to_cart_number|out_roas_l30|in_roas_l30|campaign_status|target|ads_percent|has_campaign)$/i.test(f) ||
                /\b(spend|ad\s*sold|ad\s*sales|acos|ad\s*clicks|impressions|add\s*to\s*cart|roas|target|ads\s*%|campaign|has\s*campaign)\b/i.test(tl)
            ) {
                return 'advertisement';
            }

            // Basics — identity / inventory / listing status / views / sold
            if (
                /^(image_path|parent|sku|links_column|goods_id|inventory|temu_stock|ovl30|dil_percent|temu_l30|nr_req|nrp|product_clicks|ads_views|product_clicks_l7|product_clicks_l7_to_l14)$/i.test(f) ||
                /\b(image|parent|sku|links|goods|inv|stock|ovl|dil|temu\s*l\d+|nrl|req|views|o\s*clicks|nrp)\b/i.test(tl)
            ) {
                return 'basics';
            }

            // Pricing
            if (
                /^(cvr_percent|cvr_30|cvr_45|base_price|temu_price|temu_price_display|r_pft|r_npft|s_profit|profit|profit_percent|roi_percent|npft_percent|nroi_percent|lmp|lmp_diff_pct|linked_lmp_skus|linked_lmp_sku_add|sprice|s_r_pft|s_r_npft|s_recovery|_push|stemu_price|sgprft_percent|spft_percent|sroi_percent|sgroi_percent|lp|temu_ship|prmt_pct|cpn_pct|zero_sold|cvr_up_dn|t_discounts|push_cpn|dsc|appr|push_prc)$/i.test(f) ||
                /\b(cvr|price|prc|gpft|gprft|npft|groi|nroi|prft|profit|lmp|match|s\s*prc|sgprft|spft|sroi|lp|ship|push|recovery|prmt|cpn|dsc|appr|push\s*prc|push\s*cpn)\b/i.test(tl)
            ) {
                return 'pricing';
            }

            return 'others';
        }

        function syncGroupHeaderCheckbox(groupEl) {
            if (!groupEl) return;
            const headerCb = groupEl.querySelector('.col-vis-group-toggle');
            const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
            if (!headerCb || !itemCbs.length) return;
            let checked = 0;
            itemCbs.forEach(function(cb) { if (cb.checked) checked++; });
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            if (!menu) return;
            menu.innerHTML = '';

            fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                savedColumnVisibilityMap = map;

                const showAllLi = document.createElement("li");
                showAllLi.className = "col-vis-full";
                showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="show-all-columns-btn"><i class="fa fa-eye"></i> Show All</a>';
                menu.appendChild(showAllLi);

                const groupsLi = document.createElement("li");
                groupsLi.className = "col-vis-full";
                const groupsWrap = document.createElement("div");
                groupsWrap.className = "col-vis-groups";

                const lists = {};
                const groupEls = {};
                COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                    const group = document.createElement("div");
                    group.className = "col-vis-group";
                    group.dataset.category = cat;

                    const titleEl = document.createElement("label");
                    titleEl.className = "col-vis-group-title";
                    const groupCb = document.createElement("input");
                    groupCb.type = "checkbox";
                    groupCb.className = "col-vis-group-toggle";
                    groupCb.dataset.group = cat;
                    groupCb.title = "Select / deselect all in " + COL_VIS_CATEGORY_LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                    group.appendChild(titleEl);

                    const list = document.createElement("ul");
                    list.className = "col-vis-group-list";
                    list.dataset.category = cat;
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                    groupEls[cat] = group;
                });

                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (!def.field || def.field === '_select') return;

                    const rawTitle = def.title || def.field;
                    const title = String(rawTitle).replace(/<[^>]*>/g, '').trim() || def.field;
                    const cat = classifyTemuColumn(def.field, title);

                    const li = document.createElement("li");
                    li.className = "col-vis-item";
                    li.dataset.field = def.field;
                    li.dataset.group = cat;

                    const label = document.createElement("label");
                    const checkbox = document.createElement("input");
                    checkbox.type = "checkbox";
                    checkbox.value = def.field;
                    checkbox.className = "col-vis-field-toggle";
                    checkbox.dataset.group = cat;
                    checkbox.checked = map.hasOwnProperty(def.field) ? (map[def.field] !== false) : col.isVisible();

                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(title));
                    label.title = title;
                    li.appendChild(label);
                    lists[cat].appendChild(li);
                });

                COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                    syncGroupHeaderCheckbox(groupEls[cat]);
                });

                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);
            })
            .catch(err => console.error('Error loading column visibility:', err));
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            const boxChecks = document.querySelectorAll('#column-dropdown-menu .col-vis-field-toggle');
            if (boxChecks.length) {
                boxChecks.forEach(function(cb) {
                    if (cb.value) visibility[cb.value] = !!cb.checked;
                });
            } else {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field) {
                        visibility[def.field] = col.isVisible();
                    }
                });
            }
            savedColumnVisibilityMap = visibility;

            fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility
                })
            }).catch(err => console.error('Error saving column visibility:', err));
        }

        function applyColumnVisibilityFromServer() {
            fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                if (!savedVisibility || typeof savedVisibility !== 'object') return;
                savedColumnVisibilityMap = savedVisibility;
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && field !== '_select' && savedVisibility.hasOwnProperty(field)) {
                        if (savedVisibility[field]) {
                            col.show();
                        } else {
                            col.hide();
                        }
                    }
                });
            })
            .catch(err => console.error('Error applying column visibility:', err));
        }

        table.on('tableBuilt', function() {
            temuTableBuilt = true;
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
            updateCampaignPeriodUi();
        });

        table.on('dataLoaded', function(data) {
            if (suppressDataLoadedHandler) {
                suppressDataLoadedHandler = false;
                return;
            }
            fullDataset = (data && Array.isArray(data)) ? data : (table.getData ? table.getData("all") : []) || [];
            if (window.ParentExpand) ParentExpand.captureDataset(fullDataset);
            applyFilters();
            updateCampaignPeriodUi();
            // Wait a bit to ensure badgeAvgAds is set from ajaxResponse before calculating NPFT
            setTimeout(function() {
                updateSummary();
            }, 50);
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();

            // Auto-store daily average views if not already stored today
            autoStoreDailyAvgViews();

            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = temuSkuFromCheckbox(this);
                    $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'parent',
                skuField: 'sku',
                getTable: () => table,
                getDataset: () => fullDataset,
                onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
            });
            ParentExpand.bind();
        }

        table.on('renderComplete', function() {
            updateSummary();
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = temuSkuFromCheckbox(this);
                    $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        (function() {
            var colMenu = document.getElementById("column-dropdown-menu");
            if (!colMenu) return;
            colMenu.addEventListener("change", function(e) {
                if (e.target.type !== 'checkbox') return;

                // Group header: select / deselect entire group
                if (e.target.classList.contains('col-vis-group-toggle')) {
                    const group = e.target.dataset.group;
                    const checked = e.target.checked;
                    const groupEl = e.target.closest('.col-vis-group');
                    const itemCbs = groupEl
                        ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                        : colMenu.querySelectorAll('.col-vis-field-toggle[data-group="' + group + '"]');
                    itemCbs.forEach(function(cb) {
                        const field = cb.value;
                        cb.checked = checked;
                        const col = table.getColumn(field);
                        if (!col) return;
                        if (checked) col.show();
                        else col.hide();
                    });
                    e.target.indeterminate = false;
                    saveColumnVisibilityToServer();
                    return;
                }

                // Individual column checkbox
                const field = e.target.value;
                const col = table.getColumn(field);
                if (!col) return;
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                syncGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                saveColumnVisibilityToServer();
            });
            colMenu.addEventListener("click", function(e) {
                var showAll = e.target.closest('#show-all-columns-btn');
                if (showAll) {
                    e.preventDefault();
                    e.stopPropagation();
                    table.getColumns().forEach(col => col.show());
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                }
            });
        })();

        function temuColumnsReady() {
            if (temuTableBuilt) return true;
            if (!table || typeof table.getColumns !== 'function') return false;
            const cols = table.getColumns() || [];
            if (!cols.length) return false;
            temuTableBuilt = true;
            return true;
        }

        function temuColumnByField(field) {
            if (!temuColumnsReady()) return null;
            const cols = table.getColumns(true) || table.getColumns() || [];
            for (let i = 0; i < cols.length; i++) {
                const col = cols[i];
                if (col && typeof col.getField === 'function' && col.getField() === field) {
                    return col;
                }
            }
            return null;
        }

        function updateCampaignPeriodUi() {
            const isL7 = currentCampaignPeriod === 'L7';
            $('#export-btn').prop('disabled', isL7).toggleClass('disabled', isL7);
            if (!temuColumnsReady()) return;

            const temuSalesCol = temuColumnByField('temu_l30');
            if (temuSalesCol) {
                temuSalesCol.updateDefinition({
                    title: isL7 ? 'Temu L7' : 'Temu L30',
                });
            }
            const ovlCol = temuColumnByField('ovl30');
            if (ovlCol) {
                ovlCol.updateDefinition({ title: isL7 ? 'OVL7' : 'OVL30' });
            }
            const cvr30Col = temuColumnByField('cvr_30');
            if (cvr30Col) {
                cvr30Col.updateDefinition({ title: isL7 ? 'CVR 7' : 'CVR 30' });
            }
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
        }

        function currentPeriodEndpoint() {
            return currentCampaignPeriod === 'L7'
                ? @json(url('/temu-decrease-data-l7'))
                : @json(url('/temu-decrease-data'));
        }

        $('#campaign-period-select').on('change', function() {
            const $sel = $(this);
            $sel.prop('disabled', true);
            const visibilityState = captureColumnVisibilityState();
            currentCampaignPeriod = ($sel.val() || 'L30').toUpperCase();
            const endpoint = currentPeriodEndpoint();
            table.setData(endpoint).then(function() {
                applyFilters();
                updateCampaignPeriodUi();
                applyColumnVisibilityState(visibilityState);
                buildColumnDropdown();
                if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            }).catch(function(err) {
                console.error('Campaign period load failed', err);
                if (typeof showToast === 'function') {
                    showToast('Failed to load ' + currentCampaignPeriod + ' data', 'error');
                }
            }).finally(function() {
                $sel.prop('disabled', false);
            });
        });

        // Export L30 (only available in L30 mode)
        $('#export-btn').on('click', function() {
            if (currentCampaignPeriod !== 'L30') {
                showToast('Switch to L30 to use Export L30', 'warning');
                return;
            }
            table.download("csv", "temu_decrease_data_l30.csv");
        });

        if (window.TemuViewDataUpload) {
            TemuViewDataUpload.init({
                formId: 'uploadViewDataForm',
                inputId: 'viewDataFile',
                listId: 'viewDataFileList',
                statusId: 'viewDataUploadStatus',
                onSuccess: function() {
                    if (typeof reloadTemuDecreaseAfterViews === 'function') {
                        reloadTemuDecreaseAfterViews();
                    }
                }
            });
        }
        @if(session('success') || session('error') || $errors->has('files') || $errors->has('files.0') || $errors->any())
        try {
            var uploadViewModalEl = document.getElementById('uploadViewDataModal');
            if (uploadViewModalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(uploadViewModalEl).show();
            }
        } catch (e) {}
        @endif

        // Seller Center Views scrape / JSON import → temu_view_data
        function showScrapeViewStatus(obj) {
            const $el = $('#scrapeViewStatus');
            $el.show().text(typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2));
        }
        function reloadTemuDecreaseAfterViews() {
            if (!table) return;
            const visibilityState = captureColumnVisibilityState();
            table.setData(currentPeriodEndpoint()).then(function() {
                applyFilters();
                updateCampaignPeriodUi();
                applyColumnVisibilityState(visibilityState);
                buildColumnDropdown();
                if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            });
        }
        $('#scrapeViewProbeBtn').on('click', function() {
            const $btn = $(this).prop('disabled', true);
            showScrapeViewStatus('Probing endpoints…');
            $.ajax({
                url: '{{ route("temu.viewdata.scrape") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    probe: 1,
                    days: $('#scrapeViewDays').val() || 30,
                    cookie: $('#scrapeViewCookie').val() || ''
                },
                success: function(res) {
                    showScrapeViewStatus(res);
                    showToast(res.message || (res.success ? 'Probe OK' : 'Probe failed'), res.success ? 'success' : 'error');
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Probe failed';
                    showScrapeViewStatus(xhr.responseJSON || msg);
                    showToast(msg, 'error');
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });
        $('#scrapeViewRunBtn').on('click', function() {
            if (!confirm('Scrape Seller Center product Views into temu_view_data?\nThis replaces existing Temu 1 view rows.')) return;
            const $btn = $(this).prop('disabled', true);
            showScrapeViewStatus('Scraping…');
            $.ajax({
                url: '{{ route("temu.viewdata.scrape") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    days: $('#scrapeViewDays').val() || 30,
                    cookie: $('#scrapeViewCookie').val() || ''
                },
                timeout: 0,
                success: function(res) {
                    showScrapeViewStatus(res);
                    showToast(res.message || (res.success ? 'Scraped' : 'Failed'), res.success ? 'success' : 'error');
                    if (res.success) reloadTemuDecreaseAfterViews();
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Scrape failed';
                    showScrapeViewStatus(xhr.responseJSON || msg);
                    showToast(msg, 'error');
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });
        $('#scrapeViewImportJsonBtn').on('click', function() {
            const raw = ($('#scrapeViewJson').val() || '').trim();
            if (!raw) { showToast('Paste Network JSON first', 'warning'); return; }
            if (!confirm('Import pasted JSON into temu_view_data (replace existing)?')) return;
            const $btn = $(this).prop('disabled', true);
            showScrapeViewStatus('Importing JSON…');
            $.ajax({
                url: '{{ route("temu.viewdata.import.json") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    json: raw
                },
                success: function(res) {
                    showScrapeViewStatus(res);
                    showToast(res.message || (res.success ? 'Imported' : 'Failed'), res.success ? 'success' : 'error');
                    if (res.success) reloadTemuDecreaseAfterViews();
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Import failed';
                    showScrapeViewStatus(xhr.responseJSON || msg);
                    showToast(msg, 'error');
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // Fetch View 7 / Ads fallback from Temu Ads API (same endpoint as /temu/ads).
        // Main Views column prefers temu_view_data (sheet/scrape).
        $('#fetch-views-api-btn').on('click', function() {
            const period = (currentCampaignPeriod === 'L7') ? 'L7' : 'L30';
            const label = period === 'L7' ? 'View 7' : 'Views (L30)';
            if (!confirm('Fetch Temu Ads API for ' + period + ' (updates ' + label + ') for all goods?\nThis may take several minutes.')) {
                return;
            }
            const $btn = $(this);
            const $status = $('#fetch-views-api-status');
            $btn.prop('disabled', true);
            $status.show().html('<i class="fas fa-spinner fa-spin me-1"></i>Fetching ' + period + '…');

            $.ajax({
                url: '{{ route("temu.ads.refresh") }}',
                method: 'POST',
                data: { period: period, _token: '{{ csrf_token() }}' },
                timeout: 0,
                success: function(response) {
                    if (response && response.success) {
                        $status.html('<span class="text-success">' + (response.message || 'Done') + '</span>');
                        showToast(response.message || ('Fetched ' + period + ' Views from API'), 'success');
                        const visibilityState = captureColumnVisibilityState();
                        table.setData(currentPeriodEndpoint()).then(function() {
                            applyFilters();
                            updateCampaignPeriodUi();
                            applyColumnVisibilityState(visibilityState);
                            buildColumnDropdown();
                            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
                        });
                    } else {
                        const msg = (response && response.message) || 'Fetch failed';
                        $status.html('<span class="text-danger">' + msg + '</span>');
                        showToast(msg, 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : ('Fetch failed. Try: php artisan temu:fetch-ads-data --period=' + period);
                    $status.html('<span class="text-danger">' + msg + '</span>');
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    setTimeout(function() { $status.fadeOut(); }, 8000);
                }
            });
        });

        // Single-badge history modal: click on a badge opens history for that metric
        var currentBadgeHistoryMetric = null;
        var currentBadgeHistoryLabel = null;

        function formatBadgeHistoryValue(metric, val) {
            var n = Number(val);
            if (metric === 'total_sales' || metric === 'total_spend') {
                return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (metric === 'avg_cvr_pct') {
                return n.toFixed(2) + '%';
            }
            if (metric === 'avg_views') {
                return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
            }
            return n.toLocaleString();
        }

        function loadBadgeHistoryModal() {
            if (!currentBadgeHistoryMetric || !currentBadgeHistoryLabel) return;
            var days = $('#badgeHistoryModalDays').val();
            var tbody = document.getElementById('badgeHistoryModalTbody');
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>';
            fetch('/temu-badge-history?days=' + encodeURIComponent(days))
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var data = res.data || [];
                    var key = currentBadgeHistoryMetric;
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No history. Run <code>php artisan temu:collect-metrics</code> to populate.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.map(function(row) {
                        var val = row[key];
                        return '<tr><td>' + row.record_date + '</td><td>' + formatBadgeHistoryValue(key, val) + '</td></tr>';
                    }).join('');
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger">Failed to load history.</td></tr>';
                });
        }

        $(document).on('click', '.temu-badge-history', function(e) {
            e.preventDefault();
            var metric = $(this).data('badge-metric');
            var label = $(this).data('badge-label');
            if (!metric || !label) return;
            currentBadgeChartMetricKey = metric;
            currentBadgeChartLabel = label;
            $('#badgeTrendChartTitle').text(label);
            var days = parseInt($('#badgeTrendChartDays').val(), 10) || 30;
            $('#badgeTrendChartSuffix').text('(Rolling L' + days + ')');
            var modalEl = document.getElementById('badgeTrendChartModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            loadBadgeChartData(metric, label, days);
        });

        $('#badgeTrendChartDays').on('change', function() {
            var days = parseInt($(this).val(), 10) || 30;
            $('#badgeTrendChartSuffix').text('(Rolling L' + days + ')');
            loadBadgeChartData(currentBadgeChartMetricKey, currentBadgeChartLabel, days);
        });
    });
</script>
@endsection
