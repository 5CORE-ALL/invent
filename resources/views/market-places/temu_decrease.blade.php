@extends('layouts.vertical', ['title' => 'Temu - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
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
        #skuMetricsModal.modal,
        #badgeTrendChartModal.modal,
        #avgViewsChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #skuMetricsModal .modal-dialog,
        #badgeTrendChartModal .modal-dialog,
        #avgViewsChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #skuMetricsModal .modal-content,
        #badgeTrendChartModal .modal-content,
        #avgViewsChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <!-- Basic Counts (sales summary = same as tabulator sales page) -->
                        <span id="total-revenue-badge"
                              class="badge bg-success text-center temu-badge-history"
                              data-badge-metric="total_sales" data-badge-label="Sales"
                              style="font-weight:700; color: white !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Total Sales — click to view history"
                              aria-label="Total Sales">$ 0</span>
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
                        <span id="missing-count-badge"
                              class="badge text-center"
                              style="background-color: #dc3545; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to filter missing SKUs (INV>0)">M-L 0</span>
                        <span id="not-mapped-count-badge"
                              class="badge text-center"
                              style="background-color: #dc3545; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to filter not mapped SKUs (INV>0)">M-M 0</span>
                        {{-- "Views" badge (formerly "Green Alert") removed per product request.
                             temuIsGreenAlert() helper, greenAlertCount, and the cell-color
                             logic on the Temu Price column stay so the green coloring on
                             individual rows still works. Click-to-filter via the badge is
                             gone with the badge; filter via toolbar dropdowns instead. --}}
                        {{-- "Alert" badge (formerly "Red Alert"): opposite of the Views/Green-Alert
                             badge — Temu is uncompetitive (at/above every competitor threshold). --}}
                        <span id="temu-red-alert-badge"
                              class="badge text-center"
                              style="background-color: #a00211; color: white !important; font-weight:700; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Click to filter rows where Temu Price &ge; Amazon × 0.85 AND &ge; eBay 1 × 0.90 AND &ge; eBay 2 × 0.90 (uncompetitive)"
                              aria-label="Alert — uncompetitive Temu pricing"><i class="fas fa-triangle-exclamation"></i> 0</span>

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
                              aria-label="Total Views"><i class="fas fa-eye"></i> 0</span>
                        <span id="avg-views-badge"
                              class="badge bg-info text-center temu-badge-history"
                              data-badge-metric="avg_views" data-badge-label="AVG views"
                              style="font-weight:700; color: #111 !important; font-size:14px; padding:4px 8px; cursor: pointer;"
                              title="Average Views per product from View Data sheet — click for history"
                              aria-label="Average Views per product"><i class="far fa-eye"></i> 0</span>
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
                         option for symmetry with the Amazon styling. --}}
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

                    {{-- Sprice×CVR — Apply sprice × 0.99 when CVR≤7, ×1.01 when CVR>13; gear edits thresholds --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded"
                        id="sprice-cvr-controls"
                        style="background: #ffc107;"
                        title="Adjust SPRICE by CVR: ≤7% → ×0.99, &gt;13% → ×1.01. Selected rows, or all visible eligible. Gear edits rule (shared).">
                        <button type="button" id="apply-sprice-cvr-btn"
                            class="btn btn-sm btn-warning border-0 py-0 px-2 fw-bold text-dark"
                            style="background: transparent;">
                            <i class="fas fa-percentage"></i> <span id="sprice-cvr-btn-label">Sprice×CVR</span>
                        </button>
                        <button type="button" id="open-sprice-cvr-modal-btn"
                            class="btn btn-sm border-0 py-0 px-1 text-dark"
                            style="background: transparent;"
                            data-bs-toggle="modal" data-bs-target="#spriceCvrRuleModal"
                            title="Edit CVR SPRICE multipliers (saved for everyone)">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>

                    {{-- LMP −1% — set SPRICE so S Temu Price = L1 (Price+D) × 0.99 for selected rows --}}
                    <button type="button" id="apply-lmp-minus-1-toolbar-btn"
                        class="btn btn-sm btn-outline-primary ms-2 fw-bold"
                        title="Apply LMP −1%: set SPRICE so S Temu Price = LMP × 0.99 for selected SKUs">
                        <i class="fas fa-percentage"></i> LMP −1%
                    </button>

                    {{-- Target ROI% bulk control — back-solves SPRICE for selected rows so SROI = Target ROI%.
                         stemuPrice = (LP × (1 + ROI%/100) + temu_ship) / margin; then sprice = stemuPrice
                         or stemuPrice − 2.99 (Temu adds a $2.99 ship bumper when sprice ≤ $26.99).
                         Visual styling matches /doba-tabulator: 🎯 emoji label + icon-only Apply button. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-white"
                        id="target-roi-controls"
                        title="Target ROI% — sets SPRICE so SROI = Profit/LP using (Sprice × 0.80) − temu_ship − LP">
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
                         Formula: Sprice = (LP + temu_ship) / (0.80 − GPFT%/100). Target GPFT% must be < 80%. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-white"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets SPRICE so SGPRFT = target using (Sprice × 0.80) − temu_ship − LP">
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
                    <div class="d-inline-flex align-items-center gap-1 flex-shrink-0 border rounded px-2 py-1 bg-light ms-1" title="Campaign report & sales period for this table">
                        <label for="campaign-period-select" class="mb-0 small fw-semibold text-nowrap text-dark">Campaign</label>
                        <select id="campaign-period-select" class="form-select form-select-sm" style="min-width: 88px;">
                            <option value="L30" selected>L30</option>
                            <option value="L7">L7</option>
                        </select>
                    </div>
                    <a href="{{ route('temu.lmp') }}" class="btn btn-sm btn-outline-secondary" title="Temu LMP table and upload">
                        <i class="fas fa-link"></i> LMP
                    </a>

                    <button id="inc-dec-btn" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        INC / DEC
                    </button>
                    
                    {{-- All four upload flows merged into a single dropdown.
                         Each item still opens its own modal via data-bs-toggle="modal";
                         the modals themselves were not touched. --}}
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
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                                    data-bs-toggle="modal" data-bs-target="#uploadAdDataModal">
                                    <i class="fas fa-chart-line text-warning" style="width: 18px;"></i>
                                    <span>Up Ad Data</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button type="button" id="fetch-views-api-btn" class="btn btn-sm btn-outline-primary"
                        title="Fetch View 7 / Ads fallback from Temu Ads API (clkCntAll). Main Views column uses Up View Data sheet."
                        aria-label="Fetch View 7 from Temu Ads API">
                        <i class="fas fa-sync-alt"></i> Views API
                    </button>
                    <span id="fetch-views-api-status" class="small text-muted" style="display:none;"></span>
                    <button type="button" id="toggle-ads-columns-btn" class="btn btn-sm btn-secondary"
                        title="Toggle Ads Section (show only ad-related columns + ads-stats strip)"
                        aria-label="Toggle Ads Section">
                        <i class="fas fa-filter"></i>
                    </button>

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

                <!-- Ads Count Section (shown when Show Ads Columns is on) - like TikTok -->
                <div id="temu-ads-count-section" class="mt-2 p-3 bg-light rounded border d-none">
                    <h6 class="mb-2"><i class="fa-solid fa-chart-line me-1"></i>Ads / Utilized Stats</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-sku-count" data-ads-filter="all" style="color: black; font-weight: bold; background-color: #adb5bd; cursor: pointer;" title="Click to show all">Total SKU: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-campaign-count" data-ads-filter="campaign" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has campaign">Campaign: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-ad-sku-count" data-ads-filter="ad-sku" style="color: black; font-weight: bold; background-color: #b8d4a8; cursor: pointer;" title="Click to filter: SKU active in ads with &gt;0 inventory">Ad SKU: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-missing-campaign-count" data-ads-filter="missing" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: missing campaign">Missing: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-nra-missing-count" data-ads-filter="nra-missing" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: NRA missing">NRA MISSING: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-zero-inv-count" data-ads-filter="zero-inv" style="color: black; font-weight: bold; background-color: #ffda6a; cursor: pointer;" title="Click to filter: zero inventory">Zero INV: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-nra-count" data-ads-filter="nra" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: NRA (NRL/NR)">NRA: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-ra-count" data-ads-filter="ra" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: RA (REQ)">RA: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-spend-badge" data-ads-filter="total-spend" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has spend">Total Ads Spend: $0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-budget-badge" data-ads-filter="budget" style="color: black; font-weight: bold; background-color: #ced4da; cursor: pointer;" title="Click to filter: has target/budget">Budget: $0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-ad-sales-badge" data-ads-filter="ad-sales" style="color: black; font-weight: bold; background-color: #9eeaf9; cursor: pointer;" title="Click to filter: has ad sales">Ad Sales: $0</span>
                        <span class="badge fs-6 p-2" id="temu-total-ad-sold-badge" style="color: black; font-weight: bold; background-color: #f8b4d9;" title="Total L30 Ad Sold">Total L30 Ad Sold: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-total-ad-clicks-badge" data-ads-filter="ad-clicks" style="color: black; font-weight: bold; background-color: #a5d6e8; cursor: pointer;" title="Click to filter: has ad clicks">Ad Clicks: 0</span>
                        <span class="badge fs-6 p-2" id="temu-total-clicks-badge" style="color: black; font-weight: bold; background-color: #a5d6e8;" title="Sum of clicks - Temu">Total Clicks: 0</span>
                        <span class="badge fs-6 p-2" id="temu-avg-clicks-badge" style="color: black; font-weight: bold; background-color: #a5d6e8;" title="Total clicks / Total Ad SKU - Temu">Avg Clicks: 0</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-avg-acos-badge" data-ads-filter="avg-acos" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: has spend/sales">Avg ACOS: 0%</span>
                        <span class="badge fs-6 p-2 temu-ads-badge" id="temu-roas-badge" data-ads-filter="roas" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: has spend/sales">ROAS: 0.00</span>
                    </div>
                </div>

            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="badge bg-primary">0 SKUs selected</span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                               placeholder="Enter %" style="width: 150px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply 
                        </button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-amazon"></i> Suggest Amazon Price
                        </button>
                        <button id="sugg-r-prc-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-tag"></i> Suggest R Price
                        </button>
                        <button id="sprc-26-99-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-dollar-sign"></i> SPRC 26.99
                        </button>
                        <button type="button" id="clear-sprice-btn" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Clear SPRICE
                        </button>
                        <button type="button" id="push-temu-price-btn" class="btn btn-sm btn-success"
                            title="Push SPRICE→base: (Sprice×0.85)−2.99 if SPRICE&lt;$35; else Sprice×0.85">
                            <i class="fas fa-cloud-upload-alt"></i> Push Prices
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    {{-- SKU search input moved up into the toolbar row (.temu-toolbar-row)
                         so it shares the same flex-wrap behavior as the other filters.
                         The table starts directly under the toolbar now. --}}
                    <div id="temu-table" style="flex: 1;"></div>
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
                        <div class="mb-3">
                            <label for="viewDataFile" class="form-label fw-bold">
                                <i class="fas fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="viewDataFile" name="file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                Seller Center product analytics export (.xlsx / .xls / .csv, max 10MB).
                                Writes to <code>temu_view_data</code> — this drives the <strong>Views</strong> column.
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Replaces existing Temu 1 view data. Temu Ads API only returns ad clicks (often 0 with organic sales).
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

    <!-- Upload Ad Data Modal -->
    <div class="modal fade" id="uploadAdDataModal" tabindex="-1" aria-labelledby="uploadAdDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="uploadAdDataModalLabel">
                        <i class="fas fa-chart-line me-2"></i>Upload Temu Ad Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    
                    <form id="uploadAdDataForm" action="{{ route('temu.addata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="adDataFile" class="form-label fw-bold">
                                <i class="fas fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="adDataFile" name="ad_data_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        {{-- Report range drives temu_campaign_reports.report_range so the
                             Spend / ACOS / ROAS badges (which sum that table by range)
                             refresh after this upload. Defaults to L30 to match the
                             default "Campaign Data" filter at the top of the page. --}}
                        <div class="mb-3">
                            <label for="adDataReportRange" class="form-label fw-bold">
                                <i class="fas fa-calendar-alt text-primary me-1"></i>Report Range
                            </label>
                            <select class="form-select" id="adDataReportRange" name="report_range" required>
                                <option value="L30" selected>L30 (last 30 days)</option>
                                <option value="L7">L7 (last 7 days)</option>
                                <option value="L60">L60 (last 60 days)</option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle text-info me-1"></i>
                                Match this to the period the Temu export covers — it's used by the Spend/ACOS/ROAS badges.
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will clear existing ad data and replace the selected report range before uploading new data.
                            <br>
                            <i class="fas fa-info-circle me-1"></i>
                            Upload the Temu Ads report Excel directly (as exported from Temu).
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadAdDataForm" class="btn btn-warning">
                        <i class="fas fa-upload me-1"></i>Up Ad Data
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- SKU Metrics Chart Modal (UI matches Amazon: teal header, ref panel High/Med/Low, median line, value labels on points) -->
    <div class="modal fade p-0" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Temu - <span id="modalSkuName"></span> - <span id="temuChartRefLabel">Price</span> <span id="temuChartModalSuffix">(Rolling L30)</span></span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sku-chart-days-filter" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="temuChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="skuMetricsChart"></canvas>
                        </div>
                        <div id="temuChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0; flex-wrap: nowrap; overflow-x: auto;">
                            <div class="temu-ref-col" data-metric="0" style="min-width: 62px; text-align: center; padding: 4px 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="temuChartRefDot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; flex-shrink: 0;"></span><span id="temuChartRefLabelOnly">Price</span></div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div><div id="temuCol0High" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div><div id="temuCol0Med" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div><div id="temuCol0Low" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="temuChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="chart-no-data-message" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data available for this SKU. Data will appear after running the metrics collection command.</p>
                    </div>
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

    <div class="modal fade" id="spriceCvrRuleModal" tabindex="-1" aria-labelledby="spriceCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#ffc107;">
                    <h5 class="modal-title text-dark" id="spriceCvrRuleModalLabel">
                        <i class="fas fa-percentage me-2"></i>Sprice × CVR Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Adjusts existing <strong>SPRICE</strong> (falls back to Base Price) by row CVR%.
                        Shared across Temu, Amazon, and eBay 1 / 2 / 3.
                    </p>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small" for="sprice-cvr-low-input">Low CVR ≤</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="sprice-cvr-low-input" class="form-control text-end" value="7" step="0.1" min="0" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small" for="sprice-cvr-down-input">→ Down ×</label>
                            <input type="number" id="sprice-cvr-down-input" class="form-control form-control-sm text-end" value="0.99" step="0.01" min="0.01" max="2">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small" for="sprice-cvr-high-input">High CVR &gt;</label>
                            <div class="input-group input-group-sm">
                                <input type="number" id="sprice-cvr-high-input" class="form-control text-end" value="13" step="0.1" min="0" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small" for="sprice-cvr-up-input">→ Up ×</label>
                            <input type="number" id="sprice-cvr-up-input" class="form-control form-control-sm text-end" value="1.01" step="0.01" min="0.01" max="2">
                        </div>
                    </div>
                    <div class="form-text mt-2">Default: CVR ≤7 → ×0.99, CVR &gt;13 → ×1.01. Middle band unchanged.</div>
                    <div id="sprice-cvr-modal-status" class="small mt-2 text-muted"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sprice-cvr-save-btn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "temu_decrease_column_visibility";
    // Temu margin from marketplace_percentages (Temu) — same source as backend GROI/GPFT
    const TEMU_MARGIN = {{ (float) ($temuMargin ?? \App\Services\TemuShopifySalesService::temuMarginDecimal()) }};
    /** Stored in DB table channel_tabulator_column_settings (shared across all users — same pattern as amazon/ebay1/ebay2/ebay3/mfrg tabulators). */
    const TABULATOR_COLUMN_CHANNEL = 'temu_decrease';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    let table = null;

    /**
     * Temu push base from SPRICE (same as /price-increase):
     *   if SPRICE < $35 → (Sprice × 0.85) − 2.99
     *   if SPRICE ≥ $35 → (Sprice × 0.85)
     * PFT / ROI / S Temu Prc stay on stored SPRICE — conversion is push-only.
     */
    function temuPushBaseFromSprice(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return null;
        const push = s < 35 ? ((s * 0.85) - 2.99) : (s * 0.85);
        if (!(push > 0)) return null;
        return +push.toFixed(2);
    }
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
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

    // SKU-specific chart (UI matches Amazon: ref panel High/Med/Low, median line, value labels on points, green/red/grey dots)
    let skuMetricsChart = null;
    let currentSku = null;
    let currentSkuChartMetric = 'price';
    let temuChartFirstSeriesStats = null; // { values, median, dataMin, dataMax, dotColors, labelColors, valueFmt }

    // Badge trend chart (same graph as first image)
    let badgeTrendChart = null;
    let badgeChartFirstSeriesStats = null;
    let currentBadgeChartMetricKey = '';
    let currentBadgeChartLabel = '';

    // Average Views chart
    let avgViewsChart = null;

    function temuChartFmtVal(v) {
        if (currentSkuChartMetric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return (Number(v) === v ? v.toFixed(1) : v) + '%';
        return Math.round(Number(v) || 0).toLocaleString('en-US');
    }

    function initSkuMetricsChart() {
        const ctx = document.getElementById('skuMetricsChart').getContext('2d');

        const medianLinePlugin = {
            id: 'temuMedianLine',
            afterDraw(chart) {
                if (!temuChartFirstSeriesStats || temuChartFirstSeriesStats.median === undefined) return;
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const cctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(temuChartFirstSeriesStats.median);
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
            id: 'temuValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 7px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                const valueFmt = (temuChartFirstSeriesStats && temuChartFirstSeriesStats.valueFmt) ? temuChartFirstSeriesStats.valueFmt : temuChartFmtVal;
                const labelColors = temuChartFirstSeriesStats && temuChartFirstSeriesStats.labelColors ? temuChartFirstSeriesStats.labelColors : [];
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

        skuMetricsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Price',
                    data: [],
                    borderColor: '#008000',
                    backgroundColor: 'rgba(0, 128, 0, 0.1)',
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
                                if (currentSkuChartMetric === 'price') return 'Price: $' + Number(v).toFixed(2);
                                if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return (context.dataset.label || '') + ': ' + Number(v).toFixed(1) + '%';
                                return (currentSkuChartMetric === 'views' || currentSkuChartMetric === 'temu_l30') ? (context.dataset.label + ': ' + Math.round(v)) : (context.dataset.label + ': ' + v);
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
                            if (currentSkuChartMetric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v));
                            if (currentSkuChartMetric === 'cvr' || ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(currentSkuChartMetric) >= 0) return v.toFixed(0) + '%';
                            return Math.round(v);
                        } }
                    }
                }
            }
        });
    }

    function badgeChartValueFmt(metricKey, v) {
        var n = Number(v);
        if (metricKey === 'total_sales' || metricKey === 'total_spend') return '$' + (n % 1 !== 0 ? n.toFixed(2) : Math.round(n).toLocaleString('en-US'));
        if (metricKey === 'avg_cvr_pct') return n.toFixed(2) + '%';
        if (metricKey === 'avg_views') return n.toLocaleString(undefined, { maximumFractionDigits: 2 });
        return Math.round(n).toLocaleString('en-US');
    }

    function initBadgeTrendChart() {
        const ctx = document.getElementById('badgeTrendChartCanvas').getContext('2d');
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

    function loadSkuMetricsData(sku, days = 30, metricOverride) {
        const chartMetric = metricOverride != null ? metricOverride : (currentSkuChartMetric || 'price');
        $('#temuChartLoading').show();
        $('#temuChartContainer').hide();
        $('#chart-no-data-message').hide();
        fetch(`/temu-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                $('#temuChartLoading').hide();
                if (!skuMetricsChart) return;
                function setTemuRefCol(high, med, low, fmt) {
                    const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                    const hEl = document.getElementById('temuCol0High');
                    const mEl = document.getElementById('temuCol0Med');
                    const lEl = document.getElementById('temuCol0Low');
                    if (hEl) { hEl.textContent = fmt(high); hEl.style.color = high === 0 ? refGreen : high > 0 ? refRed : refGray; }
                    if (mEl) { mEl.textContent = fmt(med); mEl.style.color = med === 0 ? refGreen : med > 0 ? refRed : refGray; }
                    if (lEl) { lEl.textContent = fmt(low); lEl.style.color = low === 0 ? refGreen : low > 0 ? refRed : refGray; }
                }
                function statsForArr(arr) {
                    const valid = arr.filter(v => v != null && !isNaN(v));
                    if (valid.length === 0) return { min: 0, max: 0, median: 0 };
                    const min = Math.min(...valid);
                    const max = Math.max(...valid);
                    const sorted = [...valid].sort((a, b) => a - b);
                    const mid = Math.floor(sorted.length / 2);
                    const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                    return { min, max, median };
                }
                if (!data || data.length === 0) {
                    temuChartFirstSeriesStats = null;
                    const h = document.getElementById('temuCol0High');
                    const m = document.getElementById('temuCol0Med');
                    const l = document.getElementById('temuCol0Low');
                    if (h) h.textContent = '-';
                    if (m) m.textContent = '-';
                    if (l) l.textContent = '-';
                    skuMetricsChart.data.labels = [];
                    skuMetricsChart.data.datasets[0].data = [];
                    skuMetricsChart.update('active');
                    $('#temuChartContainer').hide();
                    $('#chart-no-data-message').show();
                    return;
                }
                $('#chart-no-data-message').hide();
                $('#temuChartContainer').show();
                const labels = data.map(d => d.date_formatted || d.date || '');
                const metric = chartMetric;
                const isCvr = metric === 'cvr';
                const isViews = metric === 'views';
                const isTemuL30 = metric === 'temu_l30';
                const isPct = ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(metric) >= 0;
                const values = isCvr ? data.map(d => Number(d.cvr_percent) || 0) : isViews ? data.map(d => Number(d.views) || 0) : isTemuL30 ? data.map(d => Number(d.temu_l30) || 0) : isPct ? data.map(d => Number(d[metric]) || 0) : data.map(d => Number(d.price) || 0);
                const temuChartMetricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
                const temuChartMetricColors = { price: '#adb5bd', views: '#0000FF', cvr: '#008000', temu_l30: '#fd7e14', profit_percent: '#ff1493', ads_percent: '#ffc107', roi_percent: '#6f42c1', npft_percent: '#28a745', nroi_percent: '#17a2b8' };
                const bgColors = { price: 'rgba(108,117,125,0.08)', views: 'rgba(0,0,255,0.1)', cvr: 'rgba(0,128,0,0.1)', temu_l30: 'rgba(253,126,20,0.1)', profit_percent: 'rgba(255,20,147,0.1)', ads_percent: 'rgba(255,193,7,0.1)', roi_percent: 'rgba(111,66,193,0.1)', npft_percent: 'rgba(40,167,69,0.1)', nroi_percent: 'rgba(23,162,184,0.1)' };
                const labelText = temuChartMetricLabels[metric] || 'Price';
                const color = temuChartMetricColors[metric] || '#adb5bd';
                const refLabelEl = document.getElementById('temuChartRefLabel');
                const refLabelOnlyEl = document.getElementById('temuChartRefLabelOnly');
                const refDotEl = document.getElementById('temuChartRefDot');
                if (refLabelEl) refLabelEl.textContent = labelText;
                if (refLabelOnlyEl) refLabelOnlyEl.textContent = labelText;
                if (refDotEl) refDotEl.style.background = color;
                const cvrFmt = v => (Number(v) === v ? v.toFixed(1) : v) + '%';
                const intFmt = v => Math.round(Number(v) || 0).toLocaleString('en-US');
                const refFmt = (isCvr || isPct) ? cvrFmt : (isViews || isTemuL30) ? intFmt : temuChartFmtVal;
                skuMetricsChart.data.labels = labels;
                skuMetricsChart.data.datasets[0].data = values;
                skuMetricsChart.data.datasets[0].label = labelText + (metric === 'price' ? ' (USD)' : '');
                skuMetricsChart.data.datasets[0].borderColor = color;
                skuMetricsChart.data.datasets[0].backgroundColor = bgColors[metric] || 'rgba(108,117,125,0.08)';
                if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y && skuMetricsChart.options.scales.y.ticks) {
                    skuMetricsChart.options.scales.y.ticks.callback = function(v) {
                        if (metric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v));
                        if (metric === 'cvr') return v.toFixed(0) + '%';
                        return Math.round(v);
                    };
                }
                const s0 = statsForArr(values);
                setTemuRefCol(s0.max, s0.median, s0.min, refFmt);
                const refRed = '#dc3545';
                const refGray = '#6c757d';
                const refGreen = '#198754';
                const dotColors = values.map((v, i) => {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                });
                const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);
                temuChartFirstSeriesStats = { values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors, labelColors, valueFmt: refFmt };
                skuMetricsChart.data.datasets[0].pointBackgroundColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderWidth = 1.5;
                skuMetricsChart.update('active');
            })
            .catch(error => {
                $('#temuChartLoading').hide();
                temuChartFirstSeriesStats = null;
                const h = document.getElementById('temuCol0High');
                const m = document.getElementById('temuCol0Med');
                const l = document.getElementById('temuCol0Low');
                if (h) h.textContent = '-';
                if (m) m.textContent = '-';
                if (l) l.textContent = '-';
                $('#temuChartContainer').hide();
                $('#chart-no-data-message').show();
                console.error('Error loading Temu SKU metrics:', error);
            });
    }
    
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
        const ctx = document.getElementById('avgViewsChart').getContext('2d');

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
        initSkuLinkLmpModal();

        // Initialize SKU-specific chart
        initSkuMetricsChart();
        initBadgeTrendChart();

        // Initialize Average Views chart
        initAvgViewsChart();

        // Load latest average views for filtering
        loadLatestAvgViews();

        // SKU chart days filter
        $('#sku-chart-days-filter').on('change', function() {
            const days = $(this).val();
            const daysNum = parseInt(days, 10);
            const rangeLabel = daysNum === 60 ? 'L60' : daysNum === 14 ? 'L14' : daysNum === 7 ? 'L7' : 'L30';
            $('#temuChartModalSuffix').text('(Rolling ' + rangeLabel + ')');
            if (currentSku) loadSkuMetricsData(currentSku, daysNum || 30);
        });

        // Average Views chart days filter
        $('#avg-views-days-filter').on('change', function() {
            const days = $(this).val();
            loadAvgViewsHistory(days);
        });

        // Event delegation for chart button clicks (column-wise metric, same as Amazon)
        $(document).on('click', '.view-sku-chart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const el = e.target.closest ? e.target.closest('.view-sku-chart') : $(this)[0];
            const sku = $(el).data('sku');
            currentSkuChartMetric = (el.getAttribute ? el.getAttribute('data-metric') : $(el).data('metric')) || 'price';
            currentSku = sku;
            $('#modalSkuName').text(sku);
            const metricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
            $('#temuChartRefLabel').text(metricLabels[currentSkuChartMetric] || 'Price');
            $('#temuChartModalSuffix').text('(Rolling L30)');
            $('#sku-chart-days-filter').val('30');
            $('#chart-no-data-message').hide();
            loadSkuMetricsData(sku, 30, currentSkuChartMetric);
            $('#skuMetricsModal').modal('show');
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

        // Swap the discount-input panel between %/$ and Same Price modes.
        function syncDiscountInputUi() {
            const $input = $('#discount-percentage-input');
            if (samePriceModeActive) {
                $('#discount-type-select-wrap').hide();
                $('#discount-input-label').removeClass('d-none');
                $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
                $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
            } else {
                $('#discount-type-select-wrap').show();
                $('#discount-input-label').addClass('d-none');
                const t = $('#discount-type-select').val();
                $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
                $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply');
            }
        }

        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        // INC / DEC: one button, cycle Off → DEC → INC → SAME → Off
        $('#inc-dec-btn').on('click', function() {
            const selectColumn = table.getColumn('_select');
            const $btn = $(this);

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                // Off → DEC
                decreaseModeActive = true;
                increaseModeActive = false;
                samePriceModeActive = false;
                selectColumn.show();
                $btn.removeClass('btn-secondary btn-success btn-info').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> DEC <i class="fas fa-times ms-1" title="Click again for INC"></i>');
            } else if (decreaseModeActive) {
                // DEC → INC
                decreaseModeActive = false;
                increaseModeActive = true;
                samePriceModeActive = false;
                $btn.removeClass('btn-danger btn-info btn-secondary').addClass('btn-success')
                    .html('<i class="fas fa-arrow-up"></i> INC <i class="fas fa-times ms-1" title="Click again for SAME"></i>');
            } else if (increaseModeActive) {
                // INC → SAME PRICE
                decreaseModeActive = false;
                increaseModeActive = false;
                samePriceModeActive = true;
                $btn.removeClass('btn-danger btn-success btn-secondary').addClass('btn-info')
                    .html('<i class="fas fa-equals"></i> SAME <i class="fas fa-times ms-1" title="Click again to reset"></i>');
            } else {
                // SAME → Off
                decreaseModeActive = false;
                increaseModeActive = false;
                samePriceModeActive = false;
                selectColumn.hide();
                selectedSkus.clear();
                soldSpriceBlankFilterActive = false;
                updateSelectedCount();
                updateSelectAllCheckbox();
                applyFilters();
                $btn.removeClass('btn-danger btn-success btn-info').addClass('btn-secondary')
                    .html('INC / DEC');
            }
            syncDiscountInputUi();
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active');
            
            filteredData.forEach(row => {
                const sku = row['sku'];
                if (sku) {
                    if (isChecked) {
                        selectedSkus.add(sku);
                    } else {
                        selectedSkus.delete(sku);
                    }
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
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

        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        $('#sugg-r-prc-btn').on('click', function() {
            applySuggestRPrice();
        });

        $('#clear-sprice-btn').on('click', function() {
            if (confirm('Are you sure you want to clear all SPRICE data? This action cannot be undone.')) {
                clearAllSprice();
            }
        });

        $('#sprc-26-99-btn').on('click', function() {
            applySprice2699();
        });

        $('#apply-lmp-minus-1-toolbar-btn').on('click', function() {
            applyLmpMinus1Percent();
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
         *   Profit  = (sprice * 0.80 − lp − temu_ship)
         *   SROI%   = Profit / lp * 100
         *      -> sprice = (lp * (1 + roi%/100) + temu_ship) / 0.80
         *   SGPRFT% = Profit / sprice * 100
         *      -> sprice = (lp + temu_ship) / (0.80 − gpft%/100)
         *      Constraint: (0.80 − gpft%/100) must be > 0 (else infinite/neg price).
         *
         * Target ROI and Target GPFT both use fixed 0.80 take-home on Sprice.
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
        $('#target-roi-input, #target-gpft-input, #apply-target-roi-btn, #apply-target-gpft-btn, #apply-sprice-cvr-btn')
            .on('focus click', temuEnsureSelectColumnVisible);

        // Sprice × CVR — shared via /ebay/sprice-cvr-rule. ≤low → ×down, >high → ×up.
        let spriceCvrRule = { low_cvr: 7, high_cvr: 13, down_mult: 0.99, up_mult: 1.01 };
        const SPRICE_CVR_URL = @json(url('/ebay/sprice-cvr-rule'));

        function formatCvrMult(v) {
            const n = Number(v);
            if (!isFinite(n)) return '0';
            return String(+n.toFixed(4));
        }

        function refreshSpriceCvrUi() {
            const r = spriceCvrRule;
            const label = 'S×' + formatCvrMult(r.down_mult) + '/' + formatCvrMult(r.up_mult);
            $('#sprice-cvr-btn-label').text(label);
            $('#sprice-cvr-low-input').val(r.low_cvr);
            $('#sprice-cvr-high-input').val(r.high_cvr);
            $('#sprice-cvr-down-input').val(formatCvrMult(r.down_mult));
            $('#sprice-cvr-up-input').val(formatCvrMult(r.up_mult));
            $('#apply-sprice-cvr-btn').attr('title',
                'CVR ≤' + r.low_cvr + '% → SPRICE × ' + formatCvrMult(r.down_mult) +
                '; CVR >' + r.high_cvr + '% → SPRICE × ' + formatCvrMult(r.up_mult));
        }

        function loadSpriceCvrRule() {
            $.ajax({
                url: SPRICE_CVR_URL,
                method: 'GET',
                success: function(resp) {
                    if (resp && typeof resp === 'object') {
                        spriceCvrRule = {
                            low_cvr: parseFloat(resp.low_cvr) || 7,
                            high_cvr: parseFloat(resp.high_cvr) || 13,
                            down_mult: parseFloat(resp.down_mult) || 0.99,
                            up_mult: parseFloat(resp.up_mult) || 1.01
                        };
                    }
                    refreshSpriceCvrUi();
                },
                error: function() { refreshSpriceCvrUi(); }
            });
        }

        function saveSpriceCvrRuleFromModal() {
            const payload = {
                low_cvr: parseFloat(String($('#sprice-cvr-low-input').val()).replace(',', '.')),
                high_cvr: parseFloat(String($('#sprice-cvr-high-input').val()).replace(',', '.')),
                down_mult: parseFloat(String($('#sprice-cvr-down-input').val()).replace(',', '.')),
                up_mult: parseFloat(String($('#sprice-cvr-up-input').val()).replace(',', '.'))
            };
            if (!isFinite(payload.low_cvr) || !isFinite(payload.high_cvr) ||
                !isFinite(payload.down_mult) || !isFinite(payload.up_mult)) {
                $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger')
                    .text('Enter valid numbers for all fields');
                return;
            }
            const $btn = $('#sprice-cvr-save-btn');
            const btnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            $.ajax({
                url: SPRICE_CVR_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: payload,
                success: function(resp) {
                    if (resp && resp.rule) spriceCvrRule = resp.rule;
                    refreshSpriceCvrUi();
                    $('#sprice-cvr-modal-status').removeClass('text-danger').addClass('text-success').text('Saved');
                    showToast('Sprice × CVR rule saved', 'success');
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save';
                    $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger').text(msg);
                    showToast(msg, 'error');
                },
                complete: function() { $btn.prop('disabled', false).html(btnHtml); }
            });
        }

        loadSpriceCvrRule();
        $('#spriceCvrRuleModal').on('show.bs.modal', function() {
            refreshSpriceCvrUi();
            $('#sprice-cvr-modal-status').removeClass('text-danger text-success').addClass('text-muted').text('');
        });
        $('#sprice-cvr-save-btn').on('click', saveSpriceCvrRuleFromModal);

        $('#apply-sprice-cvr-btn').on('click', function() {
            const $btn = $(this);
            const rule = spriceCvrRule;
            const btnHtml = '<i class="fas fa-percentage"></i> <span id="sprice-cvr-btn-label">' +
                $('#sprice-cvr-btn-label').text() + '</span>';
            if (!table) {
                showToast('Table not ready', 'error');
                return;
            }

            temuEnsureSelectColumnVisible();

            const useSelection = typeof selectedSkus !== 'undefined' && selectedSkus.size > 0;
            const rowsToProcess = [];
            const seen = new Set();

            table.getRows('active').forEach(function(r) {
                const rd = r.getData();
                if (!rd) return;
                const sku = rd.sku;
                if (!sku || seen.has(sku)) return;
                if (useSelection && !selectedSkus.has(sku)) return;

                const cvr = parseFloat(rd.cvr_percent != null ? rd.cvr_percent : rd.cvr_30) || 0;
                let mult = null;
                if (cvr <= rule.low_cvr) mult = rule.down_mult;
                else if (cvr > rule.high_cvr) mult = rule.up_mult;
                else return;

                const existing = parseFloat(rd.sprice) || 0;
                const basePrice = parseFloat(rd.base_price) || 0;
                const base = existing > 0 ? existing : basePrice;
                if (base <= 0) return;

                const sprice = +Number(base * mult).toFixed(2);
                if (!isFinite(sprice) || sprice <= 0) return;
                seen.add(sku);
                rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
            });

            if (rowsToProcess.length === 0) {
                showToast(useSelection
                    ? 'No selected rows with CVR ≤' + rule.low_cvr + '% or >' + rule.high_cvr + '% and a price base'
                    : 'No visible rows eligible (CVR ≤' + rule.low_cvr + '% or >' + rule.high_cvr + '% with SPRICE/Base Price)',
                    'error');
                return;
            }

            const scope = useSelection ? 'selected' : 'visible eligible';
            if (!confirm(
                'Adjust SPRICE by CVR for ' + rowsToProcess.length + ' ' + scope + ' SKU(s)?\n' +
                'CVR ≤' + rule.low_cvr + '% → ×' + formatCvrMult(rule.down_mult) + '\n' +
                'CVR >' + rule.high_cvr + '% → ×' + formatCvrMult(rule.up_mult)
            )) {
                return;
            }

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            let successCount = 0;
            let errorCount = 0;
            const total = rowsToProcess.length;
            const finish = function() {
                if (successCount + errorCount !== total) return;
                $btn.prop('disabled', false).html(btnHtml);
                refreshSpriceCvrUi();
                if (errorCount === 0) {
                    showToast('Sprice × CVR saved for ' + successCount + ' SKU(s)', 'success');
                } else {
                    showToast('Saved ' + successCount + ' of ' + total + ' (' + errorCount + ' failed)', 'error');
                }
                if (useSelection) {
                    selectedSkus.clear();
                    $('.sku-select-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    if (typeof updateSelectedCount === 'function') updateSelectedCount();
                }
            };

            rowsToProcess.forEach(function(item) {
                if (typeof saveSpriceWithRetry === 'function') {
                    saveSpriceWithRetry(item.sku, item.sprice, item.row)
                        .then(function() { successCount++; finish(); })
                        .catch(function() { errorCount++; finish(); });
                } else {
                    $.ajax({
                        url: '/temu-pricing/save-sprice',
                        method: 'POST',
                        data: { sku: item.sku, sprice: item.sprice, _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            successCount++;
                            item.row.update({
                                sprice: item.sprice,
                                sgprft_percent: response.sgprft_percent,
                                sroi_percent: response.sroi_percent,
                                sprice_status: 'saved'
                            });
                            item.row.reformat();
                        },
                        error: function() { errorCount++; },
                        complete: finish
                    });
                }
            });
        });

        function temuApplyTargetSpriceBatch(opts) {
            // opts: { label, $btn, btnHtml, computeStemuPrice(rd) -> {stemuPrice|sprice, skipReason?} }
            //   Prefer `sprice` when provided (SGPFT now uses Sprice×0.80, no FB bumper).
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
                    // SROI = Profit/LP; Profit = (Sprice × 0.80) − ship − LP
                    // → Sprice = (LP × (1 + ROI%/100) + ship) / 0.80
                    return { sprice: (lp * roiMultiplier + temuShip) / 0.80 };
                }
            });
        });
        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });

        // Target GPFT% — SGPFT = ((Sprice × 0.80 − ship − LP) / Sprice) × 100
        // → Sprice = (LP + ship) / (0.80 − GPFT%/100)
        $('#apply-target-gpft-btn').on('click', function() {
            const $btn = $(this);
            const raw = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
            if (raw === '' || raw == null) { showToast('Please enter a Target GPFT%', 'error'); return; }
            if (!isFinite(targetGpftPct)) { showToast('Target GPFT% must be a number', 'error'); return; }
            const targetFraction = targetGpftPct / 100;
            const SGPFT_MARGIN = 0.80;
            temuApplyTargetSpriceBatch({
                label: `Target GPFT ${targetGpftPct}%`,
                $btn: $btn,
                // Icon-only — see note in the ROI handler above.
                btnHtml: '<i class="fas fa-calculator"></i>',
                computeStemuPrice: function(rd) {
                    const lp = parseFloat(rd.lp) || 0;
                    if (lp <= 0) return null;
                    const temuShip = parseFloat(rd.temu_ship) || 0;
                    const denom = SGPFT_MARGIN - targetFraction;
                    if (denom <= 0) {
                        return { skipReason: `Target GPFT% ${targetGpftPct}% \u2265 80% (Sprice take-home)` };
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
        let missingBadgeFilterActive = false;
        let mapBadgeFilterActive = false;
        let notMapBadgeFilterActive = false;
        let redAlertFilterActive = false;

        // 0 Sold badge just toggles the #sold-filter dropdown so the dropdown stays the
        // single source of truth (mirrors Amazon tabulator). Click again to clear.
        $('#zero-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });

        // Red Alert badge toggle — filters to only rows where temuIsRedAlert(rd) is true
        // (Temu uncompetitive). Mutually exclusive with Green Alert by construction, but
        // users can toggle either filter independently; if both are on, the intersection
        // is empty so the table shows no rows — that's a feature, not a bug.
        $('#temu-red-alert-badge').on('click', function() {
            redAlertFilterActive = !redAlertFilterActive;
            $(this).css('outline', redAlertFilterActive ? '3px solid #ffc107' : '');
            $(this).css('outline-offset', redAlertFilterActive ? '2px' : '');
            applyFilters();
        });

        $('#missing-count-badge').on('click', function() {
            missingBadgeFilterActive = !missingBadgeFilterActive;
            applyFilters();
            if (table) {
                if (missingBadgeFilterActive) {
                    table.getColumn('lmp').show();
                }
                // LMP columns stay visible when Missing L is off (no hide)
            }
        });

        $('#not-mapped-count-badge').on('click', function() {
            notMapBadgeFilterActive = !notMapBadgeFilterActive;
            mapBadgeFilterActive = false;
            applyFilters();
            if (table) {
                if (notMapBadgeFilterActive) table.getColumn('MAP').show();
                else table.getColumn('MAP').hide();
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active');
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
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
                if (row) {
                    row.update({ sprice_status: 'processing' });
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

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
                return;
            }
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

        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['a_price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        row.update({
                            sprice: amazonPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            amazon_price: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuAmazonPriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuAmazonPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-amazon-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save Amazon prices', 'error');
                }
            });
        }

        function applySuggestRPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noRPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const rPrice = parseFloat(rowData['recommended_base_price']);
                    
                    if (rPrice && rPrice > 0) {
                        row.update({
                            sprice: rPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            r_price: rPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noRPriceCount++;
                    }
                } else {
                    noRPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuRPriceUpdates(updates);
            }
            
            let message = `R price applied to ${updatedCount} SKU(s)`;
            if (noRPriceCount > 0) {
                message += ` (${noRPriceCount} SKU(s) had no R price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuRPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-r-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save R prices', 'error');
                }
            });
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

        /**
         * Apply LMP −1%: set SPRICE so displayed S Temu Price ≈ LMP × 0.99
         * (uses temuStemuPriceToSprice for the $2.99 bumper on low prices).
         */
        function applyLmpMinus1Percent() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let skippedCount = 0;
            let errorCount = 0;
            const jobs = [];

            selectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) {
                    skippedCount++;
                    return;
                }
                const tableRow = rows[0];
                const rowData = tableRow.getData();
                const lmp = getRowLmpL1(rowData);
                if (lmp === null) {
                    skippedCount++;
                    return;
                }
                const targetStemu = +(lmp * 0.99).toFixed(2);
                const newSPrice = temuStemuPriceToSprice(targetStemu);
                if (newSPrice == null || !isFinite(newSPrice) || newSPrice <= 0) {
                    skippedCount++;
                    return;
                }
                const originalSPrice = parseFloat(rowData.sprice) || 0;
                tableRow.update({
                    sprice: newSPrice,
                    sprice_status: 'processing'
                });
                tableRow.reformat();
                jobs.push({ sku: sku, sprice: newSPrice, tableRow: tableRow, originalSPrice: originalSPrice });
            });

            if (jobs.length === 0) {
                showToast('No selected SKUs with a valid LMP', 'warning');
                return;
            }

            const total = jobs.length;
            jobs.forEach(function(job) {
                saveSpriceWithRetry(job.sku, job.sprice, job.tableRow)
                    .then(function() {
                        updatedCount++;
                        if (updatedCount + errorCount === total) {
                            let msg = 'LMP −1% applied to ' + updatedCount + ' SKU(s)';
                            if (skippedCount > 0) msg += ' (' + skippedCount + ' skipped — no LMP)';
                            if (errorCount > 0) msg += ', ' + errorCount + ' failed';
                            showToast(msg, errorCount > 0 ? 'error' : 'success');
                        }
                    })
                    .catch(function() {
                        errorCount++;
                        if (job.tableRow) {
                            job.tableRow.update({ sprice: job.originalSPrice });
                            job.tableRow.reformat();
                        }
                        if (updatedCount + errorCount === total) {
                            let msg = 'LMP −1% applied to ' + updatedCount + ' SKU(s), ' + errorCount + ' failed';
                            if (skippedCount > 0) msg += ' (' + skippedCount + ' skipped)';
                            showToast(msg, 'error');
                        }
                    });
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
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            // Show selection mode if items found
            if (newlySelectedCount > 0 || selectedSkus.size > 0) {
                const selectColumn = table.getColumn('_select');
                selectColumn.show();
                
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    decreaseModeActive = true;
                    $('#inc-dec-btn').removeClass('btn-secondary btn-info btn-success').addClass('btn-danger').html('<i class="fas fa-arrow-down"></i> DEC <i class="fas fa-times ms-1" title="Click again for INC"></i>');
                }
                
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
            
            let totalProducts = data.length;
            let totalQuantity = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
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
            let missingCount = 0;
            let mappedCount = 0;
            let notMappedCount = 0;
            let lessAmzCount = 0;
            let moreAmzCount = 0;
            let greenAlertCount = 0;
            let redAlertCount = 0;
            
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
                    const fbPrice = price <= 26.99 ? price + 2.99 : price;
                    // Same margin as GROI / backend (row.percentage from marketplace_percentages)
                    const marginRaw = parseFloat(row['percentage']);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                    const pftDecimal = fbPrice > 0 ? (fbPrice * margin - lpPerUnit - temuShip) / fbPrice : 0;
                    const rowProfit = pftDecimal * fbPrice * temuL30;
                    totalRevenue += fbPrice * temuL30; // Use fbPrice for revenue (matches marketplace_daily_metrics total_sales)
                    totalProfit += rowProfit;
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
                const missing = row['missing'];
                const goodsId = row['goods_id'];
                const temuStock = parseFloat(row['temu_stock']) || 0;
                const nrReq = (row['nr_req'] || 'REQ').toString().toUpperCase();
                
                totalInv += parseInt(row['inventory']) || 0;
                
                // Count SKUs with 0 sold (Temu L30 = 0 AND INV > 0)
                if (temuL30 === 0 && inventory > 0) {
                    zeroSoldCount++;
                }
                
                // Missing L: not listed (missing='M'), INV > 0, REQ only — same rule as /map-issues.
                if (missing === 'M' && inventory > 0 && nrReq === 'REQ') {
                    missingCount++;
                }

                // Green Alert: same rule the formatter uses (Temu Price < Amazon × 0.85
                // or < eBay × 0.90 or < eBay 2 × 0.90). Count drives the toolbar badge.
                if (temuIsGreenAlert(row)) {
                    greenAlertCount++;
                }
                // Red Alert: opposite — Temu uncompetitive (at/above every reference threshold).
                if (temuIsRedAlert(row)) {
                    redAlertCount++;
                }
                
                // Map / Missing M (N Map): listed, REQ, both sides with stock — same rule as /map-issues.
                // Tolerance: < 3 units when 3% of INV < 3, else rounded % > 3.
                if (missing !== 'M' && goodsId && goodsId !== '' && nrReq === 'REQ' && inventory > 0 && temuStock > 0) {
                    const invTemuDiff = Math.abs(inventory - temuStock);
                    let isNotMap;
                    if (inventory * 0.03 < 3) {
                        isNotMap = invTemuDiff > 3;
                    } else {
                        isNotMap = Math.round((invTemuDiff / inventory) * 100) > 3;
                    }
                    if (isNotMap) {
                        notMappedCount++; // N MP (Not Mapped - mismatch)
                    } else {
                        mappedCount++; // MP (Mapped) or within tolerance
                    }
                }
                
                // Count < Amz and > Amz (compare Temu Price with Amazon Price)
                // temuPrice already declared above, reuse it
                const amazonPrice = parseFloat(row['a_price']) || 0;
                
                if (amazonPrice > 0 && temuPrice > 0) {
                    if (temuPrice < amazonPrice) {
                        lessAmzCount++; // Temu Price < Amazon Price
                    } else if (temuPrice > amazonPrice) {
                        moreAmzCount++; // Temu Price > Amazon Price
                    }
                }
            });
            
            // Calculate averages
            // Avg GPRFT% = (Total Profit / Total Revenue) * 100 — margin from marketplace_percentages
            const avgGprft = totalRevenue > 0 ? (totalProfit / totalRevenue) * 100 : (totalProducts > 0 ? totalGprft / totalProducts : 0);
            // Weighted GROI% = (Total Profit / Total LP/COGS) × 100
            const avgGroi = totalLp > 0 ? (totalProfit / totalLp) * 100 : (totalProducts > 0 ? totalGroi / totalProducts : 0);
            const avgAds = totalProducts > 0 ? totalAds / totalProducts : 0;
            // Prefer backend aggregate_ads_percent only when it is a valid positive number.
            // If backend sends 0/invalid while table has spend+sales, compute ADS% from table totals.
            // Primary source is spend_l30; fall back to spend snapshot when spend_l30 is unavailable.
            const spendForAdsPercent = totalSpendL30 > 0 ? totalSpendL30 : totalSpend;
            const computedAggregateAdsPercent = totalRevenue > 0 ? (spendForAdsPercent / totalRevenue) * 100 : 0;
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
            const totalTcos = totalRevenue > 0 ? (totalSpend / totalRevenue) * 100 : 0;
            
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
                } else {
                    $('#total-quantity-badge').text('QTY ' + totalQuantity.toLocaleString());
                    $('#total-revenue-badge').text('$ ' + Math.round(totalRevenue).toLocaleString());
                }
            } else {
                $('#total-quantity-badge').text('QTY ' + totalQuantity.toLocaleString());
                $('#total-revenue-badge').text('$ ' + Math.round(totalRevenue).toLocaleString());
            }
            $('#zero-sold-count-badge').text('0 Sold ' + zeroSoldCount.toLocaleString());
            $('#missing-count-badge').text('M-L ' + missingCount.toLocaleString());
            $('#not-mapped-count-badge').text('M-M ' + notMappedCount.toLocaleString());
            // Use .html() so the FontAwesome <i> renders; .text() would HTML-escape it.
            $('#temu-red-alert-badge').html('<i class="fas fa-triangle-exclamation"></i> ' + redAlertCount.toLocaleString());
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
            // Prefer the file total from temu_campaign_reports (computed in PHP) so
            // the badge always matches what the user uploaded — even rows whose
            // goods_id isn't yet in temu_pricing AND have no SKU column would
            // otherwise be dropped by the per-row sum above.
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

            // Use .html() so the FontAwesome <i> renders; .text() would HTML-escape it.
            $('#total-views-badge').html('<i class="fas fa-eye"></i> ' + compactInt(totalViews));
            $('#avg-views-badge').html('<i class="far fa-eye"></i> ' + Math.round(avgViews).toLocaleString());
        }

        // Update Ads/Utilized count section (when Show Ads Columns is on) - like TikTok
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
                // Use temu_campaign_reports L30 data for badge totals (matches sheet export & all-marketplace-master)
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
                if (st === 'Active' || s > 0 || c > 0) uniqueCampaignSkus.add(r.sku);
            });
            // Prefer file totals from temu_campaign_reports (returned in
            // response.ad_totals). The per-row sums above silently miss any
            // uploaded row whose goods_id isn't in temu_pricing AND whose SKU
            // column is empty, so the badges would otherwise be lower than the
            // upload. ROAS / ACOS / Avg Clicks are derived from these totals so
            // they stay self-consistent with Spend and Ad Sales.
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

            $('#temu-total-sku-count').text('Total SKU: ' + validSkuCount);
            $('#temu-campaign-count').text('Campaign: ' + campaignCount);
            $('#temu-ad-sku-count').text('Ad SKU: ' + adSkuSet.size);
            $('#temu-missing-campaign-count').text('Missing: ' + missingCount);
            $('#temu-nra-missing-count').text('NRA MISSING: ' + nraMissingCount);
            $('#temu-zero-inv-count').text('Zero INV: ' + zeroInvCount);
            $('#temu-nra-count').text('NRA: ' + nraCount);
            $('#temu-ra-count').text('RA: ' + raCount);
            $('#temu-total-spend-badge').text('Total Ads Spend: $' + Math.round(spendForBadges).toLocaleString());
            $('#temu-total-budget-badge').text('Budget: $' + totalBudget.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#temu-total-ad-sales-badge').text('Ad Sales: $' + Math.round(adSalesForBadges).toLocaleString());
            const adSoldLabel = (typeof currentCampaignPeriod !== 'undefined' && currentCampaignPeriod === 'L7') ? 'Total L7 Ad Sold' : 'Total L30 Ad Sold';
            $('#temu-total-ad-sold-badge').text(adSoldLabel + ': ' + adSoldForBadges.toLocaleString());
            $('#temu-total-ad-clicks-badge').text('Ad Clicks: ' + clicksForBadges.toLocaleString());
            $('#temu-total-clicks-badge').text('Total Clicks: ' + clicksForBadges.toLocaleString());
            $('#temu-avg-clicks-badge').text('Avg Clicks: ' + (avgClicks % 1 === 0 ? Math.round(avgClicks).toLocaleString() : avgClicks.toFixed(1)));
            $('#temu-avg-acos-badge').text('Avg ACOS: ' + Math.round(avgAcos) + '%');
            $('#temu-roas-badge').text('ROAS: ' + roas.toFixed(2));
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
        // File totals straight from temu_campaign_reports for the current range.
        // Used by the Spend / Total Ads Spend / Ad Sales / Ad Sold / Ad Clicks
        // badges so they always equal the upload — including rows whose goods_id
        // isn't yet in temu_pricing (which the per-row sum would drop).
        let adTotalsFromBackend = null;
        let currentCampaignPeriod = 'L30';

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

        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu-decrease-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            // Drag column edges to widen/narrow (same as ebay / other Tabulator pages)
            columnDefaults: {
                resizable: true,
                minWidth: 40
            },
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_percent", dir: "asc"}
            ],
            ajaxResponse: function(url, params, response) {
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
                return [];
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
                ParentExpand.columnDef(),
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        
                        return `${sku} <button type="button" class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price trend" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fas fa-info-circle"></i></button>`;
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
                    title: "CVR 60",
                    field: "cvr_60",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    visible: false,
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${formatCvrPct(val)}</span>`;
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
                        let arrowColor = '#6c757d';
                        let arrowIcon = 'fa-minus';
                        let dotColor = '#008000'; // green by default
                        if (val > cvr60 + tol) {
                            // CVR 30 > CVR 60 (improving)
                            arrowColor = '#28a745';
                            arrowIcon = 'fa-arrow-up';
                            dotColor = '#28a745'; // green
                        } else if (val < cvr60 - tol) {
                            // CVR 60 > CVR 30 (declining)
                            arrowColor = '#a00211';
                            arrowIcon = 'fa-arrow-down';
                            dotColor = '#a00211'; // red
                        } else {
                            // CVR 30 equals CVR 60 (within tolerance)
                            dotColor = '#ffc107'; // yellow
                        }
                        arrowHtml = ` <span title="CVR 30 vs CVR 60: ${formatCvrPct(cvr60)}" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        const sku = rowData.sku || '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="cvr" title="View CVR% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${formatCvrPct(val)}</span>${arrowHtml} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "T L60",
                    field: "temu_l60",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "T L45",
                    field: "temu_l45",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    // Hidden by default — users can re-enable via the Col dropdown.
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseInt(cell.getValue()) || 0;
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="temu_l30" title="View Temu L30 chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #fd7e14;"></span></button>` : '';
                        return `${value.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "Missing",
                    field: "missing",
                    hozAlign: "center",
                    sorter: "string",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold;" title="Missing listing: not in Temu API metrics, or listed with stock but no base price">M</span>';
                        }
                        return '';
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
                        const hasCampaign = goodsId && (
                            rowData.spend > 0 ||
                            rowData.ad_clicks > 0 ||
                            (rowData.campaign_status && rowData.campaign_status !== 'Not Created')
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
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    sorter: "string",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const missing = rowData['missing'];
                        const nrReq = (rowData['nr_req'] || 'REQ').toString().toUpperCase();

                        // Map / N Map only for listed (not missing) REQ rows with stock on both
                        // sides — same gate as /map-issues. Otherwise leave blank.
                        if (missing === 'M' || !rowData['goods_id'] || rowData['goods_id'] === '' || nrReq !== 'REQ') {
                            return '';
                        }

                        const temuStock = parseFloat(rowData['temu_stock']) || 0;
                        const inv = parseFloat(rowData['inventory']) || 0;
                        if (inv <= 0 || temuStock <= 0) {
                            return '';
                        }

                        // Tolerance: < 3 units when 3% of INV < 3, else rounded % > 3.
                        const diffUnits = Math.abs(inv - temuStock);
                        let isNotMap;
                        if (inv * 0.03 < 3) {
                            isNotMap = diffUnits > 3;
                        } else {
                            isNotMap = Math.round((diffUnits / inv) * 100) > 3;
                        }
                        if (!isNotMap) {
                            return '<span style="color: #28a745; font-weight: bold;" title="Within tolerance: counts as MP">MP</span>';
                        }
                        const diff = inv - temuStock;
                        const sign = diff > 0 ? '+' : '';
                        return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
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
                    headerTooltip: "Seller Center Product clicks (temu_view_data). Fallback: Ads API clkCntAll when sheet has no row.",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseInt(cell.getValue()) || 0;
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="views" title="View Views chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0000FF;"></span></button>` : '';
                        return `${value.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
                {
                    // L7 ad clicks from temu_ads_api_reports (period=L7)
                    title: "View 7",
                    field: "product_clicks_l7",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80,
                    headerTooltip: "L7 ad clicks from Temu Ads API (temu_ads_api_reports period=L7). Run: php artisan temu:fetch-ads-api-reports --period=L7",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
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
                    title: "Base Price",
                    field: "base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const value = parseFloat(cell.getValue());
                        const str = (value === null || value === undefined || isNaN(value)) ? '' : '$' + Number(value).toFixed(2);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="price" title="View Price chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd;"></span></button>` : '';
                        return `${str} ${dotBtn}`.trim();
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const basePrice = parseFloat(rowData['base_price']) || 0;

                        // Only calculate Temu Price if base_price > 0 (item exists in Temu)
                        if (basePrice === 0) {
                            return '$0.00';
                        }
                        const temuPrice = basePrice <= 26.99 ? basePrice + 2.99 : basePrice;

                        // Green Alert: Temu Price < Amazon × 0.85 OR < eBay 1 × 0.90 OR < eBay 2 × 0.90.
                        // Driven by temuIsGreenAlert(rd) so the toolbar filter + summary
                        // count + cell color always agree on which rows count.
                        if (temuIsGreenAlert(rowData)) {
                            return `<span style="color: #28a745; font-weight: 600;" title="Green Alert: Temu price is below 85% of Amazon or 90% of eBay 1 / eBay 2">$${temuPrice.toFixed(2)}</span>`;
                        }
                        // Red Alert: opposite — Temu uncompetitive (at/above every reference threshold).
                        if (temuIsRedAlert(rowData)) {
                            return `<span style="color: #a00211; font-weight: 600;" title="Red Alert: Temu price is at/above 85% of Amazon AND 90% of eBay 1 / eBay 2 (uncompetitive)">$${temuPrice.toFixed(2)}</span>`;
                        }
                        return '$' + temuPrice.toFixed(2);
                    }
                },
                {
                    title: "A Prc",
                    field: "a_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    title: "E Prc",
                    field: "e_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    // Sibling to "E Prc" — eBay 2 listing price from ebay_2_metrics.ebay_price,
                    // surfaced here so Temu pricing can be compared against both eBay stores
                    // alongside Amazon (A Prc) in one row.
                    title: "E2 Prc",
                    field: "e2_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
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
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // GPRFT badge in the summary stats still reflects the underlying data.
                    visible: false,
                    sorter: function(a, b, aRow, bRow) {
                        const calc = (row) => {
                            const price = parseFloat(row['temu_price']) || 0;
                            if (price <= 0) return 0;
                            const lp = parseFloat(row['lp']) || 0;
                            const temuShip = parseFloat(row['temu_ship']) || 0;
                            const marginRaw = parseFloat(row['percentage']);
                            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                            // Same formula/margin as GROI and backend profit_percent
                            return ((price * margin - lp - temuShip) / price) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const price = parseFloat(rowData['temu_price']) || 0;  // Temu Price column
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const marginRaw = parseFloat(rowData['percentage']);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                        // GPRFT% = ((Temu Price × margin − LP − temu_ship) / Temu Price) × 100 — same margin as GROI
                        const value = price > 0 ? ((price * margin - lp - temuShip) / price) * 100 : 0;
                        const colorClass = getPftColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="profit_percent" title="View GPRFT% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ff1493;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
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
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Custom sorter to handle the 100% case properly
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aSpend = parseFloat(aData['spend'] || 0);
                        const bSpend = parseFloat(bData['spend'] || 0);
                        const aTemuL30 = parseFloat(aData['temu_l30'] || 0);
                        const bTemuL30 = parseFloat(bData['temu_l30'] || 0);
                        
                        // Calculate effective ADS% (100 if spend > 0 and sales = 0)
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aSpend > 0 && aTemuL30 === 0) aVal = 100;
                        if (bSpend > 0 && bTemuL30 === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        // Use badge Ads % for all rows when available
                        const displayVal = (badgeAvgAds != null ? badgeAvgAds : (parseFloat(cell.getValue()) || 0));
                        const rowData = cell.getRow().getData();
                        const spend = parseFloat(rowData['spend'] || 0);
                        const temuL30 = parseFloat(rowData['temu_l30'] || 0);
                        let color = '#000';
                        
                        // If spend > 0 but no sales, show 100% in red
                        if (spend > 0 && temuL30 === 0) {
                            const sku = rowData.sku || '';
                            const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="ads_percent" title="View ADS% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span></button>` : '';
                            return `<span style="color: #a00211; font-weight: 600;">100%</span> ${dotBtn}`.trim();
                        }
                        
                        // eBay ACOS color logic applied to displayed value
                        if (displayVal == 0 || displayVal == 100) color = '#a00211'; // red
                        else if (displayVal > 0 && displayVal <= 7) color = '#ff1493'; // pink
                        else if (displayVal > 7 && displayVal <= 14) color = '#28a745'; // green
                        else if (displayVal > 14 && displayVal <= 21) color = '#ffc107'; // yellow
                        else if (displayVal > 21) color = '#a00211'; // red
                        
                        const sku = (rowData && rowData.sku) ? rowData.sku : '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="ads_percent" title="View ADS% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span></button>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${displayVal.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="roi_percent" title="View GROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6f42c1;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },



                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    // NPFT badge in the summary stats still reflects the underlying data.
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="npft_percent" title="View NPFT% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #28a745;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="nroi_percent" title="View NROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #17a2b8;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "LMP",
                    field: "lmp",
                    hozAlign: "center",
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
                        // L1 = lowest non-ignored entry; fall back to row.lmp / lmp_raw
                        const prices = entries
                            .filter(function(e) { return !e.ignored; })
                            .map(function(e) {
                                const p = e.price;
                                if (p === null || p === undefined || p === '' || isNaN(parseFloat(p))) return null;
                                const base = parseFloat(p);
                                const d = parseFloat(e.delivery);
                                let delivery = (!isNaN(d) && d > 0) ? d : 0;
                                // Default +$2.99 when Price < $27
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
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "R Prc",
                    field: "recommended_base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value || value === 0) return '';
                        return `$${parseFloat(value).toFixed(2)}`;
                    }
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const currentPrice = parseFloat(rowData['base_price']) || 0;
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        const sprice = isNaN(spriceNum) ? 0 : spriceNum;

                        if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0) return '';
                        if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) return '';

                        return `$${sprice.toFixed(2)}`;
                    }
                },
                {
                    title: "Push",
                    field: "_push",
                    width: 55,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Push base = (Sprice×0.85)−2.99 if SPRICE<$35; else Sprice×0.85",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent) return '';
                        const sprice = parseFloat(rowData.sprice) || 0;
                        const pushBase = temuPushBaseFromSprice(sprice);
                        const pushStatus = rowData.push_status || null;
                        if (sprice <= 0 || pushBase == null) return '';

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
                    title: "S Temu Prc",
                    field: "stemu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const currentTemuPrice = parseFloat(rowData['temu_price']) || 0;
                        
                        if (sprice === 0) return '';
                        
                        // If user enters current Temu Price into SPRICE, treat it as final price
                        // (avoid applying +2.99 twice). Otherwise keep normal base->Temu conversion.
                        const stemuPrice = (currentTemuPrice > 0 && Math.abs(sprice - currentTemuPrice) < 0.01)
                            ? sprice
                            : (sprice <= 26.99 ? sprice + 2.99 : sprice);
                        return `$${stemuPrice.toFixed(2)}`;
                    }
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        if (sprice === 0) return '';
                        // Profit = (Sprice × 0.80) − temu_ship − LP; SGPRFT% = Profit / Sprice × 100
                        const sgprft = ((sprice * 0.80 - temuShip - lp) / sprice) * 100;
                        const colorClass = getPftColor(sgprft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    }
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    // Hidden by default — users can re-enable via the Col dropdown
                    // (persists in channel_tabulator_column_settings as 'temu_decrease').
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const currentTemuPrice = parseFloat(rowData['temu_price']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const adsPercentRow = parseFloat(rowData['ads_percent']) || 0;
                        const spend = parseFloat(rowData['spend']) || 0;
                        const temuL30 = parseFloat(rowData['temu_l30']) || 0;
                        
                        if (sprice === 0) return '';
                        
                        const isSameAsCurrentTemuPrice = currentTemuPrice > 0 && Math.abs(sprice - currentTemuPrice) < 0.01;

                        // If S PRC equals current Temu Price, SPFT must match NPFT exactly.
                        if (isSameAsCurrentTemuPrice) {
                            const npftExact = parseFloat(rowData['npft_percent']) || 0;
                            const colorClass = getPftColor(npftExact);
                            return `<span class="dil-percent-value ${colorClass}">${Math.round(npftExact)}%</span>`;
                        }
                        
                        // Profit = (Sprice × 0.80) − temu_ship − LP; SGPRFT% then − Ads%
                        const sgprft = ((sprice * 0.80 - temuShip - lp) / sprice) * 100;
                        
                        // Keep SPFT aligned with NPFT logic:
                        // prefer aggregate ADS% badge value; if spend>0 and no sales (100% case), don't subtract ADS.
                        const adsForSpft = (badgeAvgAds != null && Number.isFinite(Number(badgeAvgAds)))
                            ? Number(badgeAvgAds)
                            : adsPercentRow;
                        const spft = (spend > 0 && temuL30 === 0) ? sgprft : (sgprft - adsForSpft);
                        
                        const colorClass = getPftColor(spft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    }
                },
                {
                    title: "SROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // SROI = Profit / LP; Profit = (Sprice × 0.80) − temu_ship − LP
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        if (sprice === 0 || lp === 0) return '';
                        const profit = (sprice * 0.80) - temuShip - lp;
                        const sroi = (profit / lp) * 100;
                        const colorClass = getRoiColor(sroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sroi)}%</span>`;
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Spend"></i>
                        </div>`;
                    },
                    visible: false, 
                    width: 100
                },
                {
                    title: "L60 Spend",
                    field: "spend_l60",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const disp = value > 0 ? '$' + value.toFixed(2) : '<span style="color: #999;">-</span>';
                        return `<div class="d-flex align-items-center justify-content-center gap-1"><span>${disp}</span><i class="fa-solid fa-info-circle l60-spend-info-icon" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Click to show/hide L60 columns"></i></div>`;
                    }
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
                    title: "L60 vs L30",
                    field: "l60_vs_l30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const val = cell.getValue();
                        if (val === null || val === undefined || Number.isNaN(parseFloat(val))) return '<span style="color: #999;">-</span>';
                        const v = parseFloat(val);
                        const pct = (v % 1 === 0) ? Math.round(v) : v.toFixed(1);
                        const direction = v > 0 ? 'L60&lt;' : (v < 0 ? 'L60&gt;' : '');
                        const color = v > 0 ? '#28a745' : (v < 0 ? '#dc3545' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">${pct}%</span>${direction ? ` <span style="font-size: 0.75em; color: #6c757d;" title="${v > 0 ? 'L60 ACOS < L30 ACOS' : 'L60 ACOS > L30 ACOS'}">${direction}</span>` : ''}`;
                    }
                },
                {
                    title: "ACOS%",
                    field: "acos_ad",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${Math.round(value)}%</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="ACOS%"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "Ad Clicks",
                    field: "ad_clicks",
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toLocaleString()}</span>
                            <i class="fa-solid fa-info-circle" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Ad Clicks"></i>
                        </div>`;
                    },
                    visible: false,
                    width: 110
                },
                {
                    title: "Impressions",
                    field: "impressions",
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
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // Use net_roas as OUT ROAS if out_roas_l30 is not available
                        const value = parseFloat(cell.getValue() || rowData.net_roas || 0);
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
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    editable: function(cell) {
                        return !window.iconClicked;
                    },
                    formatter: function(cell) {
                        // Default to 0 if field doesn't exist
                        const cellValue = cell.getValue();
                        const value = (cellValue !== null && cellValue !== undefined) ? parseFloat(cellValue) : 0;
                        const cellElement = cell.getElement();
                        
                        if (cellElement) {
                            setTimeout(function() {
                                const icon = cellElement.querySelector('.toggle-in-roas-info');
                                if (icon) {
                                    $(icon).off('mousedown click');
                                    $(icon).on('mousedown', function(e) {
                                        window.iconClicked = true;
                                        e.stopPropagation();
                                        e.preventDefault();
                                        setTimeout(function() {
                                            window.iconClicked = false;
                                        }, 100);
                                        return false;
                                    });
                                }
                            }, 0);
                        }
                        
                        return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                            <span>${value.toFixed(2)}</span>
                            <i class="fa-solid fa-info-circle toggle-in-roas-info" style="cursor: pointer; font-size: 12px; color: #3b82f6; pointer-events: auto; z-index: 10; position: relative;" title="IN ROAS"></i>
                        </div>`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('toggle-in-roas-info') || 
                            e.target.classList.contains('fa-info-circle') ||
                            e.target.closest('.toggle-in-roas-info')) {
                            e.stopPropagation();
                            e.preventDefault();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        const row = cell.getRow();
                        const rowData = row.getData();
                        const sku = rowData.sku;
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (!sku) {
                            console.error('SKU not found');
                            showToast('Error: SKU not found', 'error');
                            return;
                        }
                        
                        $.ajax({
                            url: '/temu/ads/update',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            data: {
                                sku: sku,
                                field: 'in_roas_l30',
                                value: value
                            },
                            success: function(response) {
                                if (response.success) {
                                    cell.setValue(value);
                                    showToast('IN ROAS updated successfully', 'success');
                                } else {
                                    const oldValue = parseFloat(rowData.in_roas_l30 || 0);
                                    cell.setValue(oldValue);
                                    showToast('Failed to update IN ROAS: ' + (response.message || 'Unknown error'), 'error');
                                }
                            },
                            error: function(xhr) {
                                const oldValue = parseFloat(rowData.in_roas_l30 || 0);
                                cell.setValue(oldValue);
                                const errorMsg = xhr.responseJSON?.message || xhr.statusText || 'Unknown error';
                                console.error('Error updating IN ROAS:', xhr);
                                showToast('Error updating IN ROAS: ' + errorMsg, 'error');
                            }
                        });
                    },
                    visible: false,
                    width: 100
                },
                {
                    title: "Status",
                    field: "campaign_status",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData().sku;
                        const rowData = row.getData();
                        const goodsId = rowData.goods_id || '';
                        const hasCampaign = goodsId && (rowData.spend > 0 || rowData.ad_clicks > 0);
                        
                        // Default to "Not Created" if no campaign exists, otherwise "Active"
                        let defaultValue = hasCampaign ? "Active" : "Not Created";
                        // Try to get value from cell, if not available use default
                        let cellValue = cell.getValue();
                        const value = (cellValue && cellValue.trim()) ? cellValue.trim() : defaultValue;
                        
                        const statusColors = {
                            "Active": "#10b981",
                            "Inactive": "#ef4444",
                            "Not Created": "#eab308"
                        };
                        const selectedColor = statusColors[value] || "#6b7280";
                        
                        return `
                            <select class="form-select form-select-sm editable-select campaign-status-select" 
                                    data-sku="${sku}" 
                                    data-field="status"
                                    style="width: 120px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: 0.875rem; color: ${selectedColor}; font-weight: 500;">
                                <option value="Active" ${value === 'Active' ? 'selected' : ''} style="color: #10b981; font-weight: 500;">Active</option>
                                <option value="Inactive" ${value === 'Inactive' ? 'selected' : ''} style="color: #ef4444; font-weight: 500;">Inactive</option>
                                <option value="Not Created" ${value === 'Not Created' ? 'selected' : ''} style="color: #eab308; font-weight: 500;">Not Created</option>
                            </select>
                        `;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    visible: false,
                    width: 130
                },
                {
                    title: "Target",
                    field: "target",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return '$' + value.toFixed(2);
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
                
            ]
        });

        // Toggle Ads Columns button - Show only columns that match temu/ads page
        let adsColumnsVisible = false;
        let originalColumnVisibility = {}; // Store original visibility state
        
        // Columns to show when ads view is active (matching temu/ads page)
        const adsColumnFields = ['sku', 'goods_id', 'has_campaign', 'inventory', 'ovl30', 'temu_l30', 'dil_percent', 'nr_req', 'spend', 'spend_l60', 'l60_vs_l30', 'ad_clicks', 'acos_ad', 'out_roas_l30', 'in_roas_l30', 'campaign_status'];

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
                if (!field || !Object.prototype.hasOwnProperty.call(state, field)) return;
                if (state[field]) {
                    column.show();
                } else {
                    column.hide();
                }
            });
        }
        
        $('#toggle-ads-columns-btn').on('click', function() {
            adsColumnsVisible = !adsColumnsVisible;
            
            if (adsColumnsVisible) {
                // Store original visibility state for all columns
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field) {
                        originalColumnVisibility[field] = column.isVisible();
                    }
                });
                
                // Hide non-ads columns, show ads columns (iterate so hidden columns are found)
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field && !adsColumnFields.includes(field)) {
                        column.hide();
                    } else if (field && adsColumnFields.includes(field)) {
                        column.show();
                    }
                });
                if (typeof l60ColumnsVisible !== 'undefined' && l60ColumnsVisible && typeof l60ColumnFields !== 'undefined') {
                    table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField())).forEach(c => c.show());
                }
                
                // Icon-only — title attr already conveys the action; aria-label set in HTML.
                $(this).html('<i class="fas fa-eye"></i>').attr('title', 'Show all columns (exit Ads Section view)');
                $(this).removeClass('btn-secondary btn-primary').addClass('btn-danger');
                $('#temu-ads-count-section').removeClass('d-none');
                $('#summary-stats').addClass('d-none');
                if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
            } else {
                // Restore original visibility state
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field && originalColumnVisibility.hasOwnProperty(field)) {
                        if (originalColumnVisibility[field]) {
                            column.show();
                        } else {
                            column.hide();
                        }
                    }
                });
                if (typeof l60ColumnsVisible !== 'undefined' && l60ColumnsVisible && typeof l60ColumnFields !== 'undefined') {
                    table.getColumns().filter(c => c.getField() && l60ColumnFields.includes(c.getField())).forEach(c => c.show());
                }
                
                $(this).html('<i class="fas fa-filter"></i>').attr('title', 'Toggle Ads Section (show only ad-related columns + ads-stats strip)');
                $(this).removeClass('btn-danger btn-primary').addClass('btn-secondary');
                $('#temu-ads-count-section').addClass('d-none');
                $('#summary-stats').removeClass('d-none');
                temuAdsBadgeFilter = null;
                $('#temu-ads-count-section .temu-ads-badge').removeClass('border border-3 border-dark');
                applyFilters();
            }
        });

        // Ads section badge filter (like TikTok) - toggle on click
        let temuAdsBadgeFilter = null;
        $(document).on('click', '.temu-ads-badge', function() {
            const filter = $(this).data('ads-filter');
            temuAdsBadgeFilter = (temuAdsBadgeFilter === filter) ? null : filter;
            $('#temu-ads-count-section .temu-ads-badge').removeClass('border border-3 border-dark');
            if (temuAdsBadgeFilter) {
                $('#temu-ads-count-section .temu-ads-badge[data-ads-filter="' + temuAdsBadgeFilter + '"]').addClass('border border-3 border-dark');
            }
            applyFilters();
            if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
        });

        // Temu Ads section: L7 / L30 campaign report upload (like TikTok)
        function doTemuUploadReport(fileInput, reportRange, statusContainerId) {
            const file = fileInput.files && fileInput.files[0];
            const $status = $('#' + statusContainerId);
            if (!file) {
                $status.html('<span class="text-danger">Please select a file</span>').show();
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_range', reportRange);
            $status.html('<span class="text-info">Uploading...</span>').show();
            $.ajax({
                url: '{{ route("temu.ads.upload.campaign") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response && response.success) {
                        $status.html('<span class="text-success">' + (response.message || 'Uploaded') + '</span>');
                        fileInput.value = '';
                        if (table) table.replaceData();
                        if (typeof updateTemuAdsCounts === 'function') updateTemuAdsCounts();
                        setTimeout(function() { $status.html('').hide(); }, 5000);
                    } else {
                        $status.html('<span class="text-danger">' + (response && response.message ? response.message : 'Upload failed') + '</span>');
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Upload failed';
                    $status.html('<span class="text-danger">' + msg + '</span>');
                }
            });
        }

        // L60 column toggle (info icon in Spend L60 column header)
        let l60ColumnsVisible = false;
        const l60ColumnFields = ['ad_sold_l60', 'ad_sales_l60', 'l60_vs_l30'];
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

            const parentFilter = $('#parent-filter').val() || 'skus';
            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const groiFilter = $('#roi-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const cvrTrendFilter = $('#cvr-trend-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            const skuSearch = $('#sku-search').val();
            // When showing All Rows / Parents, keep parent summary rows visible even if a data filter would drop them
            const parentRowsBypassDataFilters = (parentFilter === 'all' || parentFilter === 'parents');
            // Clear all filters first
            table.clearFilter();

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

            // Inventory filter
            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    if (inventoryFilter === 'eq0') return inv === 0;
                    return true;
                });
            }

            // GPFT filter — same formula/margin as GPRFT column & GROI (row.percentage)
            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemuParentRow(data) && parentRowsBypassDataFilters) return true;
                    const price = parseFloat(data.temu_price) || 0;
                    const marginRaw = parseFloat(data.percentage);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
                    const gpft = price > 0 ? ((price * margin - (parseFloat(data.lp) || 0) - (parseFloat(data.temu_ship) || 0)) / price) * 100 : 0;
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

            // Missing badge filter (clickable badge only - no dropdown)
            if (missingBadgeFilterActive) {
                table.addFilter(function(data) {
                    return data['missing'] === 'M';
                });
            }

            // Sold filter — driven by the #sold-filter dropdown (single source of truth).
            // The legacy #zero-sold-count-badge click just toggles this dropdown to "zero".
            // `zero` keeps the original badge semantics (INV > 0 required). `sold` is the new
            // option added for parity with the Amazon-style dropdown (no INV constraint).
            const soldFilter = $('#sold-filter').val();
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

            // Missing L badge filter — not listed, INV > 0, REQ only (same as /map-issues).
            if (missingBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const nrReq = (data['nr_req'] || 'REQ').toString().toUpperCase();
                    return data['missing'] === 'M' && inv > 0 && nrReq === 'REQ';
                });
            }

            // Map badge filter — listed, REQ, both sides with stock, within tolerance (same as /map-issues).
            if (mapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const missing = data['missing'];
                    const goodsId = data['goods_id'];
                    const nrReq = (data['nr_req'] || 'REQ').toString().toUpperCase();
                    const temuStock = parseFloat(data['temu_stock']) || 0;
                    if (missing === 'M' || !goodsId || goodsId === '' || nrReq !== 'REQ' || inv <= 0 || temuStock <= 0) return false;

                    const diffUnits = Math.abs(inv - temuStock);
                    let isNotMap;
                    if (inv * 0.03 < 3) {
                        isNotMap = diffUnits > 3;
                    } else {
                        isNotMap = Math.round((diffUnits / inv) * 100) > 3;
                    }
                    return !isNotMap;
                });
            }

            // Red Alert badge filter — opposite (Temu uncompetitive).
            if (redAlertFilterActive) {
                table.addFilter(function(data) {
                    return temuIsRedAlert(data);
                });
            }

            // Not Map (Missing M) badge filter — listed, REQ, both sides with stock, out of tolerance (same as /map-issues).
            if (notMapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const missing = data['missing'];
                    const goodsId = data['goods_id'];
                    const nrReq = (data['nr_req'] || 'REQ').toString().toUpperCase();
                    const temuStock = parseFloat(data['temu_stock']) || 0;
                    if (missing === 'M' || !goodsId || goodsId === '' || nrReq !== 'REQ' || inv <= 0 || temuStock <= 0) return false;

                    const diffUnits = Math.abs(inv - temuStock);
                    let isNotMap;
                    if (inv * 0.03 < 3) {
                        isNotMap = diffUnits > 3;
                    } else {
                        isNotMap = Math.round((diffUnits / inv) * 100) > 3;
                    }
                    return isNotMap;
                });
            }

            // Temu Ads section badge filter (only when Show Ads Columns is on)
            if (typeof adsColumnsVisible !== 'undefined' && adsColumnsVisible && temuAdsBadgeFilter) {
                switch (temuAdsBadgeFilter) {
                    case 'all':
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

            // NRL/REQ filter
            const nrReqFilter = $('#nr-req-filter').val();
            if (nrReqFilter !== 'all') {
                table.addFilter(function(data) {
                    const nr_req = data['nr_req'] || 'REQ';
                    // Handle both NR and NRL as same value
                    const dataValue = (nr_req === 'NR' || nr_req === 'NRL') ? 'NRL' : nr_req;
                    return dataValue === nrReqFilter;
                });
            }

            // NRP filter — matches the same values stored on row.nrp (REQ/NR/LATER).
            // Empty / unknown values are treated as REQ to mirror the NRP column formatter,
            // so the filter and the dot color always agree.
            const nrpFilter = $('#nrp-filter').val();
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

            // LMP + linked LMP columns: always visible
            try {
                table.getColumn('lmp').show();
                table.getColumn('linked_lmp_skus').show();
                table.getColumn('linked_lmp_sku_add').show();
            } catch (e) {}
            // MAP column: visible only when Missing M badge is active
            try {
                if (notMapBadgeFilterActive) table.getColumn('MAP').show();
                else table.getColumn('MAP').hide();
            } catch (e) {}
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
                
                const newSprice = parseFloat(cellValue);
                
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

        // --- Temu price push: SPRICE → base via (×0.85)−2.99 if SPRICE<$35; else ×0.85 ---
        function pushTemuPriceForRow(row, price) {
            const data = row.getData();
            const sku = data.sku;
            const goodsId = data.goods_id || '';
            const skuId = data.sku_id || '';
            // Always convert from stored SPRICE (same rule as /price-increase)
            const fromSprice = temuPushBaseFromSprice(data.sprice);
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
            const sprice = parseFloat(row.getData().sprice) || 0;
            const pushBase = temuPushBaseFromSprice(sprice);
            if (pushBase == null) {
                showToast('Cannot push — invalid SPRICE', 'error');
                return;
            }
            if (!confirm(
                'Push Temu base $' + pushBase.toFixed(2)
                + ' (from SPRICE $' + sprice.toFixed(2) + ' × 0.85'
                + (sprice < 35 ? ' − 2.99' : '')
                + ') for SKU: ' + sku + '?'
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
                const sprice = parseFloat(d.sprice) || 0;
                const pushBase = temuPushBaseFromSprice(sprice);
                if (sprice > 0 && pushBase != null && d.push_status !== 'pushed') {
                    items.push({ row: row, price: sprice, sku: d.sku, pushBase: pushBase });
                }
            });

            if (items.length === 0) {
                showToast('No rows with SPRICE to push (or all already pushed)', 'warning');
                return;
            }

            if (!confirm(
                'Push Temu base for ' + items.length + ' SKU(s)?\n'
                + '(Sprice × 0.85) − 2.99 if SPRICE < $35; else Sprice × 0.85'
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

        // Status dropdown change handler
        $(document).on('change', '.campaign-status-select', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');

            if (!sku) {
                console.error('SKU not found in status select');
                showToast('Error: SKU not found', 'error');
                return;
            }

            // Update the select color based on value
            const statusColors = {
                "Active": "#10b981",
                "Inactive": "#ef4444",
                "Not Created": "#eab308"
            };
            $select.css('color', statusColors[value] || "#6b7280");

            // Save to database via temu/ads/update endpoint
            $.ajax({
                url: '/temu/ads/update',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                data: {
                    sku: sku,
                    field: 'status',
                    value: value
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Status updated successfully', 'success');
                    } else {
                        showToast('Failed to update status: ' + (response.message || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || xhr.statusText || 'Unknown error';
                    console.error('Error updating status:', xhr);
                    showToast('Failed to update status: ' + errorMsg, 'error');
                }
            });
        });

        // Initialize iconClicked flag for IN ROAS
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
                /^(spend|spend_l60|ad_sold_l60|ad_sales_l60|l60_vs_l30|acos_ad|ad_clicks|impressions|add_to_cart_number|out_roas_l30|in_roas_l30|campaign_status|target|ads_percent|has_campaign)$/i.test(f) ||
                /\b(spend|ad\s*sold|ad\s*sales|acos|ad\s*clicks|impressions|add\s*to\s*cart|roas|target|ads\s*%|campaign|has\s*campaign)\b/i.test(tl)
            ) {
                return 'advertisement';
            }

            // Basics — identity / inventory / listing status / views / sold
            if (
                /^(image_path|parent|sku|links_column|goods_id|inventory|temu_stock|ovl30|dil_percent|temu_l30|temu_l45|temu_l60|missing|MAP|nr_req|nrp|product_clicks|product_clicks_l7|product_clicks_l7_to_l14)$/i.test(f) ||
                /\b(image|parent|sku|links|goods|inv|stock|ovl|dil|temu\s*l\d+|t\s*l\d+|missing|map|nrl|req|views|o\s*clicks|nrp)\b/i.test(tl)
            ) {
                return 'basics';
            }

            // Pricing
            if (
                /^(cvr_percent|cvr_30|cvr_45|cvr_60|base_price|temu_price|a_price|e_price|e2_price|profit|profit_percent|roi_percent|npft_percent|nroi_percent|lmp|linked_lmp_skus|linked_lmp_sku_add|recommended_base_price|sprice|_push|stemu_price|sgprft_percent|spft_percent|sroi_percent|lp|temu_ship)$/i.test(f) ||
                /\b(cvr|price|prc|gpft|gprft|npft|groi|nroi|prft|profit|lmp|s\s*prc|sgprft|spft|sroi|lp|ship|push)\b/i.test(tl)
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
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

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
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && savedVisibility.hasOwnProperty(field)) {
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
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
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
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
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
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
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

        function updateCampaignPeriodUi() {
            const isL7 = currentCampaignPeriod === 'L7';
            $('#export-btn').prop('disabled', isL7).toggleClass('disabled', isL7);

            const temuSalesCol = table.getColumn('temu_l30');
            if (temuSalesCol) {
                temuSalesCol.updateDefinition({
                    title: isL7 ? 'Temu L7' : 'Temu L30',
                });
            }
            const ovlCol = table.getColumn('ovl30');
            if (ovlCol) {
                ovlCol.updateDefinition({ title: isL7 ? 'OVL7' : 'OVL30' });
            }
            const cvr30Col = table.getColumn('cvr_30');
            if (cvr30Col) {
                cvr30Col.updateDefinition({ title: isL7 ? 'CVR 7' : 'CVR 30' });
            }
            const l60VsL30Col = table.getColumn('l60_vs_l30');
            if (l60VsL30Col) {
                l60VsL30Col.updateDefinition({ title: isL7 ? 'L60 vs L7' : 'L60 vs L30' });
            }
            $('#temu-total-ad-sold-badge').attr('title', isL7 ? 'Total L7 Ad Sold' : 'Total L30 Ad Sold');
        }

        function currentPeriodEndpoint() {
            return currentCampaignPeriod === 'L7' ? '/temu-decrease-data-l7' : '/temu-decrease-data';
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

        updateCampaignPeriodUi();
    });
</script>
@endsection
