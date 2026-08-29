@extends('layouts.vertical', ['title' => 'Amz Analytics', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Compact filter dropdowns — size each to its own content */
        #amazon-filter-bar .form-select {
            width: auto !important;
            max-width: 120px;
            padding-right: 1.2rem !important;
            padding-left: 0.4rem !important;
            background-position: right 0.3rem center !important;
        }

        /* Match ROI% / GPFT% inputs to the S PRC dropdown width */
        #amazon-filter-bar #sprice-filter { width: 78px !important; }

        /* Keep the filter bar in 2 rows */
        #amazon-filter-bar #parent-search,
        #amazon-filter-bar #sku-search {
            width: 140px !important;
        }
        #amazon-filter-bar #target-roi-input,
        #amazon-filter-bar #target-gpft-input {
            width: 56px !important;
        }
        #amazon-filter-bar #target-roi-controls,
        #amazon-filter-bar #target-gpft-controls {
            margin-left: 0 !important;
            padding: 2px 4px !important;
            gap: 4px !important;
        }
        #amazon-filter-bar .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }

        @include('partials.amazon-pef-promo', ['amazonPefPromoPart' => 'css'])

        /* Uniform body-cell font + fixed row height (prevents tall/short rows from wrap) */
        #amazon-table .tabulator-row {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        #amazon-table .tabulator-row .tabulator-cell {
            font-size: 13px !important;
            line-height: 1.2 !important;
            height: 36px !important;
            max-height: 36px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            vertical-align: middle !important;
        }
        #amazon-table .tabulator-row .tabulator-cell[tabulator-field="price"] {
            font-weight: 700 !important;
        }
        #amazon-table .tabulator-row .tabulator-cell span,
        #amazon-table .tabulator-row .tabulator-cell a,
        #amazon-table .tabulator-row .tabulator-cell div,
        #amazon-table .tabulator-row .tabulator-cell button,
        #amazon-table .tabulator-row .tabulator-cell label,
        #amazon-table .tabulator-row .tabulator-cell input:not([type="checkbox"]):not([type="radio"]),
        #amazon-table .tabulator-row .tabulator-cell select,
        #amazon-table .tabulator-row .tabulator-cell i {
            font-size: 13px !important;
        }
        #amazon-table .tabulator-row .tabulator-cell img.hover-thumb {
            width: 28px !important;
            height: 28px !important;
            max-width: 28px !important;
            max-height: 28px !important;
            object-fit: cover !important;
            display: block !important;
            flex-shrink: 0 !important;
        }
        #amazon-table .tabulator-row .tabulator-cell > div {
            flex-wrap: nowrap !important;
            max-width: 100%;
            overflow: hidden;
        }

        /* Give room between items without inflating control height */
        #amazon-filter-bar { gap: 4px 6px !important; }
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #summary-stats .d-flex { gap: 8px !important; }
        #summary-stats .badge {
            display: inline-flex !important;
            align-items: center;
            font-size: calc(1rem * 0.99) !important;
            padding: calc(0.5rem * 0.99) !important;
        }
        /* Dashboard-standard KPI dots: green = up, red = down, gray = same / no prior */
        #summary-stats .summary-trend-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 0.22rem;
            margin-left: 0;
            flex-shrink: 0;
            cursor: pointer;
            vertical-align: 0.08em;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
            position: relative;
            z-index: 2;
        }
        #summary-stats .summary-trend-dot:hover {
            transform: scale(1.25);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25);
        }
        #summary-stats .summary-trend-dot.up { background: #22c55e; }
        #summary-stats .summary-trend-dot.down { background: #ef4444; }
        #summary-stats .summary-trend-dot.flat,
        #summary-stats .summary-trend-dot.none { background: #9ca3af; }

        /* Column visibility — 4 groups (Basic / Price / Ads / Other) */
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
        #column-dropdown-menu .col-vis-group.col-vis-drop-over {
            border-color: #0d6efd;
            background: #eef5ff;
            box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.25);
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
            max-height: 320px;
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
            cursor: grab;
        }
        #column-dropdown-menu .col-vis-item:active { cursor: grabbing; }
        #column-dropdown-menu .col-vis-item.col-vis-dragging { opacity: 0.55; }
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
        @media (max-width: 768px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        /* Image column hover preview — 0.4× natural size */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
            max-width: min(40vw, 420px);
            max-height: min(40vh, 420px);
            overflow: hidden;
        }
        #image-hover-preview img {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: min(40vh, 420px);
            object-fit: contain;
        }

        /* Sort by clicking the header; keep vertical titles free of carets */
        .tabulator-col .tabulator-col-sorter,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        .tabulator-col .tabulator-col-sorter-element,
        .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        /* Drag to reorder columns (shared for all users) */
        #amazon-table .tabulator-col {
            cursor: grab;
        }
        #amazon-table .tabulator-col.tabulator-moving {
            cursor: grabbing;
            opacity: 0.85;
        }

        .parent-row {
            background-color: #fffef2 !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #fffef2 !important;
            font-weight: bold !important;
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }

        /* Parent row cells: same fixed height as child rows */
        .tabulator-row.parent-row .tabulator-cell {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            overflow: hidden !important;
            vertical-align: middle !important;
        }

        /* Play / Pause parent navigation (same as product-master / eBay) */
        .time-navigation-group {
            margin-left: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }
        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }
        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .time-navigation-group button:active { transform: scale(0.95); }
        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .time-navigation-group button i { font-size: 1.1rem; transition: transform 0.2s ease; }
        #play-auto { color: #28a745; }
        #play-auto:hover { background-color: #28a745 !important; color: white !important; }
        #play-pause { color: #ffc107; display: none; }
        #play-pause:hover { background-color: #ffc107 !important; color: white !important; }
        #play-backward, #play-forward { color: #007bff; }
        #play-backward:hover, #play-forward:hover { background-color: #007bff !important; color: white !important; }
        .time-navigation-group button:focus { outline: none; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); }

        /* Vertical column headers (0.8× of prior 80px) */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Keep checkbox column header upright (not rotated) */
        .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
        }
        
        .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title input {
            transform: none !important;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 64px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Hide built-in pagination counter (moved above table) */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-counter {
            display: none !important;
        }

        /* Style pagination buttons - bigger and modern */
        .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            cursor: pointer;
            transition: all 0.15s ease !important;
            text-align: center !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        .tabulator .tabulator-cell.linked-sku-col .linked-sku-badge:hover {
            background-color: #cffafe !important;
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
        #amzMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #skuMetricsModal .modal-dialog,
        #amzMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #skuMetricsModal .modal-content,
        #amzMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        /* LMP ignore: dim ignored competitors; they don't count toward L1 */
        #lmpModal tr.lmp-ignored-row {
            opacity: 0.55;
            background: #f1f3f5 !important;
        }
        #lmpModal tr.lmp-ignored-row td {
            text-decoration: line-through;
            text-decoration-color: #adb5bd;
        }
        #lmpModal tr.lmp-ignored-row td.lmp-ignore-cell,
        #lmpModal tr.lmp-ignored-row td:last-child,
        #lmpModal tr.lmp-ignored-row .lmp-ignore-cb {
            text-decoration: none;
        }
        #lmpModal .lmp-ignore-cb {
            cursor: pointer;
            width: 1.1em;
            height: 1.1em;
        }
        #lmpModal tr.lmp-lowest-row,
        #lmpModal tr.lmp-lowest-row:hover,
        #lmpModal tr.lmp-lowest-row > td {
            background: #fff8c5 !important;
        }
        #lmpModal #lmpDataList thead th {
            background: #334155 !important;
            color: #fff !important;
            border-color: #475569 !important;
            font-weight: 600;
        }
        #lmpModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0;
            padding: 0 !important;
            align-items: flex-start !important;
        }
        #lmpModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            height: auto !important;
            transform: none !important;
        }
        #lmpModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
            height: auto !important;
        }
        #lmpModal #lmpDataList .table-responsive {
            overflow-x: hidden;
        }
        #lmpModal #lmpDataList table {
            width: 100%;
            table-layout: auto;
        }
        #lmpModal .lmp-text-preview-btn {
            flex-shrink: 0;
            color: #0d6efd;
            line-height: 1;
            padding: 0 2px;
            border: 0;
            background: none;
            cursor: pointer;
            font-size: 14px;
        }
        #lmpModal .lmp-text-preview-btn:hover { color: #0a58ca; }
        .lmp-text-preview-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.45);
            padding: 1.5rem;
        }
        .lmp-text-preview-overlay.is-open { display: flex; }
        .lmp-text-preview-card {
            width: min(640px, 100%);
            max-height: 80vh;
            overflow: auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 18px 50px rgba(0,0,0,.28);
        }
        .lmp-text-preview-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: #334155;
            color: #fff;
            border-radius: 10px 10px 0 0;
        }
        .lmp-text-preview-body {
            padding: 16px 18px 18px;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz Analytics',
        'sub_title' => 'Amz Analytics',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex flex-column">
                
                <div class="d-flex align-items-center flex-wrap gap-2" id="amazon-filter-bar">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 140px; display: inline-block;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 140px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>INV</option>
                        <option value="zero">Zero </option>
                        <option value="more">More</option>
                    </select>

                    <select id="sold-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;"
                        title="Filter by A L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="zero">=0</option>
                        <option value="sold">&gt;0</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="lt-20">≤ 20%</option>
                        <option value="20-30">20–30%</option>
                        <option value="30-43">30–43%</option>
                        <option value="gt-43">≥ 43%</option>
                    </select>
                    <select id="cvr-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;"
                        title="Filter by CVR% slab band">
                        <option value="all">CVR%</option>
                        <option value="zero">0%</option>
                        <option value="yellow">0.01–3.5%</option>
                        <option value="blue">3.51–7%</option>
                        <option value="green">7.01–13%</option>
                        <option value="pink">&gt;13.01%</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">GROI%</option>
                        <option value="lt-60">&lt; 60%</option>
                        <option value="60-90">60–90%</option>
                        <option value="90-150">90–150%</option>
                        <option value="gte-150">≥ 150%</option>
                    </select>

                    <select id="diff-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Diff%</option>
                        <option value="lt80">&lt; 80%</option>
                        <option value="80-100">80–100%</option>
                        <option value="gt100">&gt; 100%</option>
                    </select>

                    <select id="cvr-trend-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;"
                        title="CVR L30 vs prior period L31–L60 (CVR L60)">
                        <option value="all">CVR trend</option>
                        <option value="down">Down</option>
                        <option value="up">Up</option>
                        <option value="same">Same</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <select id="rating-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;"
                        title="Filter by Reviews column (Amz avg rating)">
                        <option value="all">Reviews</option>
                        <option value="red">Red &lt;3</option>
                        <option value="yellow">Yellow 3-3.5</option>
                        <option value="blue">Blue 3.51-3.99</option>
                        <option value="green">Green 4-4.5</option>
                        <option value="pink">Pink &gt;4.5</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Rows</option>
                        <option value="parents">Parents</option>
                        {{-- Default: SKUs (parent-only default is eBay 2 / eBay 3 only) --}}
                        <option value="skus" selected>SKUs</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="not-pushed">Not Pushed</option>
                        <option value="pushed">Pushed</option>
                        <option value="applied">Applied</option>
                        <option value="error">Error</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">S PRC</option>
                        <option value="blank">Blank S PRC only</option>
                    </select>

                    {{-- Dil vs PRMT / CVR vs CPN — same rules store as /pricing-errors-fix --}}
                    @include('partials.amazon-pef-promo', ['amazonPefPromoPart' => 'buttons'])

                    {{-- Target ROI% bulk control — back-solves S PRC so the Sroi column = Target ROI%. --}}
                    {{-- Formula: sprice = (LP × (1 + ROI%/100) + Ship) / 0.80  (same 0.80 take-home as Sroi / GROI%) --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC so the SGROI column equals the target (gross; does not target SNROI)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target ROI% applied to all selected rows — matches the SGROI column">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC so SGROI = Target ROI% for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC so S GPFT (Sgpft) = Target GPFT%. --}}
                    {{-- Formula: sprice = (LP + Ship) / (0.80 − GPFT%/100). Target GPFT% must be < 80. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC so the S GPFT column equals the target (gross; does not target SNPFT)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target GPFT% applied to all selected rows — matches the S GPFT column. Must be < 80%.">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC so S GPFT = Target GPFT% for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <!-- Selected Rows Count -->
                    <span class="badge bg-primary fs-6 p-2 ms-2" id="selected-rows-count" style="display: none;">
                        0 selected
                    </span>
                    <button class="btn btn-sm btn-outline-secondary ms-1" id="clear-selection-btn" style="display: none;" title="Clear Selection">
                        <i class="fas fa-times"></i> Clear
                    </button>

                    <!-- Bulk Actions Dropdown -->
                    <div class="dropdown d-inline-block ms-2" id="bulk-actions-container" style="display: none;">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button"
                            id="bulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Bulk Actions">
                            <i class="fas fa-upload"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="bulkActionsDropdown" style="min-width: 250px;">
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="NRA">Mark as NRA</a></li>
                            <li><a class="dropdown-item bulk-action-item" href="#" data-action="RA">Mark as RA</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="px-3 py-2">
                                <div style="font-weight: 600; margin-bottom: 8px; color: #495057;">
                                    <i class="fas fa-upload"></i> Bulk Push Prices
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input bulk-push-checkbox" type="checkbox" value="amazon" id="bulkPushAmazon" checked>
                                    <label class="form-check-label" for="bulkPushAmazon" style="color: #FF9900; font-weight: 500;">
                                        Amz
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-primary w-100 mt-2" id="executeBulkPush">
                                    <i class="fas fa-paper-plane"></i> Push Selected
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fas fa-columns"></i>
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            <!-- Populated dynamically: Basic / Price / Ads / Other -->
                        </ul>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Export">
                            <i class="fas fa-file-export"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ url('/amazon-export-pricing-cvr') }}"><i class="fas fa-file-csv text-success"></i> Export</a></li>
                            <li><a class="dropdown-item" href="#" id="section-export-btn"><i class="fas fa-download text-primary"></i> Export view</a></li>
                            <li><a class="dropdown-item" href="#" id="export-lmp-btn"><i class="fas fa-file-export text-warning"></i> Export LMP</a></li>
                            <li><a class="dropdown-item" href="{{ url('/amazon-export-sprice-upload') }}"><i class="fas fa-download text-info"></i> SPRICE N Upload</a></li>
                        </ul>
                    </div>
                    
                    <div class="btn-group">
                        <button type="button" id="price-pct-btn" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-percent"></i> Prc Mode
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="price-pct-dropdown">
                            <li><a class="dropdown-item" href="#" data-mode="decrease"><i class="fas fa-minus-circle text-warning"></i> Decrease</a></li>
                            <li><a class="dropdown-item" href="#" data-mode="increase"><i class="fas fa-plus-circle text-success"></i> Increase</a></li>
                            <li><a class="dropdown-item" href="#" data-mode="same"><i class="fas fa-equals text-info"></i> Same Price</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" data-mode="cancel"><i class="fas fa-times"></i> Cancel</a></li>
                        </ul>
                    </div>

                    <button id="clear-sprice-btn" class="btn btn-sm btn-danger"
                        title="Clear SPRICE for selected SKUs (use the left checkbox column)">
                        <i class="fas fa-eraser"></i> Sprice
                    </button>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Filtered row count -->
                        <span class="badge bg-dark fs-6 p-2" id="rows-count-badge" style="color: white; font-weight: bold;" title="Number of rows currently shown (after filters)">Row: 0</span>

                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2 amz-badge-chart" data-metric="total_pft" data-live-value="0" data-format="money" id="total-pft-amt-badge" style="color: black; font-weight: bold; cursor:pointer; display: none;" title="View trend"><span class="summary-trend-dot none" data-metric="total_pft" title="Rolling history"></span>PFT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2 amz-badge-chart" data-metric="total_sales" data-live-value="{{ (float) ($amazonSalesL30 ?? 0) }}" data-format="money" id="total-sales-amt-badge" style="color: black; font-weight: bold; cursor:pointer;" title="30-day sales from real Amz orders (same source as /amazon/daily-sales). Click badge or dot for trend."><span class="summary-trend-dot none" data-metric="total_sales" title="Rolling history"></span>Sales: ${{ number_format((float) ($amazonSalesL30 ?? 0)) }}</span>
                        
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2 amz-badge-chart" data-metric="gpft_pct" data-live-value="0" data-format="pct" id="avg-gpft-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend"><span class="summary-trend-dot none" data-metric="gpft_pct" title="Rolling history"></span>GPFT: 0%</span>

                        <!-- Ads% (from /all-marketplace-master — Amz channel) -->
                        <span class="badge fs-6 p-2 amz-badge-chart" data-metric="tcos_pct" data-live-value="{{ $amazonAdsPercent !== null ? round((float) $amazonAdsPercent, 1) : 0 }}" data-format="pct" data-invert="1" id="amazon-ads-badge" style="background-color: #fd7e14; color: white; font-weight: bold; cursor:pointer;" title="Amz Ads% (Total Ad Spend / L30 Sales). Lower is better. Click dot for rolling history."><span class="summary-trend-dot none" data-metric="tcos_pct" title="Rolling history"></span>Ads: {{ $amazonAdsPercent !== null ? round($amazonAdsPercent, 1) . '%' : 'N/A' }}</span>
                        <span class="badge bg-info fs-6 p-2 amz-badge-chart" data-metric="npft_pct" data-live-value="0" data-format="pct" id="avg-pft-badge" style="color: black; font-weight: bold; cursor:pointer;" title="View trend"><span class="summary-trend-dot none" data-metric="npft_pct" title="Rolling history"></span>PFT: 0%</span>
                        <span class="badge fs-6 p-2 amz-badge-chart" data-metric="groi_pct" data-live-value="0" data-format="pct" id="groi-percent-badge" style="background-color: #6f42c1; color: white; font-weight: bold; cursor:pointer;" title="View GROI% rolling history"><span class="summary-trend-dot none" data-metric="groi_pct" title="Rolling history"></span>GROI: 0%</span>
                        <span class="badge fs-6 p-2 amz-badge-chart" data-metric="nroi_pct" data-live-value="0" data-format="pct" id="nroi-percent-badge" style="background-color: #6f42c1; color: white; font-weight: bold; cursor:pointer;" title="View NROI% rolling history — Net ROI = (Total PFT − Ad Spend) / COGS"><span class="summary-trend-dot none" data-metric="nroi_pct" title="Rolling history"></span>NROI: 0%</span>
                        
                        <!-- Amz Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;"><span class="summary-trend-dot none" title="No prior-day snapshot yet"></span>Price: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;"><span class="summary-trend-dot none" title="No prior-day snapshot yet"></span>Views: 0</span>
                        <span class="badge fs-6 p-2 amz-badge-chart" data-metric="total_l30_orders" data-live-value="{{ (int) ($amazonUnitsSoldL30 ?? 0) }}" id="total-qty-sold-badge" style="background-color: #20c997; color: black; font-weight: bold; cursor:pointer;" title="Total Amz units sold in the last 30 days from real Amz orders (Pacific, through yesterday) — same source as /amazon/daily-sales. Click for trend."><span class="summary-trend-dot none" data-metric="total_l30_orders" title="Rolling history"></span>Qty: {{ number_format((int) ($amazonUnitsSoldL30 ?? 0)) }}</span>
                        <span class="badge bg-success fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;"><span class="summary-trend-dot none" title="No prior-day snapshot yet"></span>CVR: 0%</span>

                        <!-- Sold Filter Badges (badge click = filter; dot click = rolling history) -->
                        <span class="badge bg-success fs-6 p-2 sold-filter-badge amz-hover-chart" data-filter="all" data-metric="sold_count" data-live-value="0" data-source="badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Click dot for trend">
                            <span class="summary-trend-dot none" data-metric="sold_count" title="Rolling history"></span>Sold >0: <span id="total-sold-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge amz-hover-chart" data-filter="zero" data-metric="zero_sold_count" data-live-value="0" data-invert="1" data-source="badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Click dot for trend">
                            <span class="summary-trend-dot none" data-metric="zero_sold_count" title="Rolling history"></span>0 Sold: <span id="zero-sold-count">0</span>
                        </span>
                        <span class="badge fs-6 p-2" id="amazon-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue alert: Price ≠ S PRC. Click to show only those SKUs. Auto-push skips SKUs where Price already equals S PRC.">
                            <i class="fas fa-exclamation-triangle"></i> 0
                        </span>
                        @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'amazon-lmp-missing-badge', 'lmpChannelKey' => 'amazon'])
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'amazon-price-gt-lmp-badge', 'pglChannelKey' => 'amazon', 'pglPriceField' => 'price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'amazon-price-lt80-lmp-badge', 'pltChannelKey' => 'amazon', 'pltPriceField' => 'price'])
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Price % input: how much to decrease or increase (shown when Decrease/Increase is active) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="discount-input-label" class="text-muted fw-bold me-1">By how much:</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 140px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="e.g. 10 or 2.50" step="0.1" min="0" 
                            style="width: 140px;" title="Enter % or $ amount to decrease/increase price">
                        <button id="apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                        <button type="button" id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-light rounded-circle" title="Show all products" style="display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-light rounded-circle" title="Start parent navigation">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                </div>
                <div id="amazon-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body (scrollable section) -->
                    <div id="amazon-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scout Modal -->
    <div class="modal fade" id="scoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scout Data for <span id="scoutSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="scoutDataList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-shopping-cart me-1"></i> <span id="lmpSku"></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" id="lmpPullApiBtn" class="btn btn-sm btn-light"
                            title="Pull live prices + shipping (delivery) for this SKU from SerpApi (Amz)">
                            <i class="fas fa-cloud-download-alt"></i> Pull
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <!-- What-if SP → GROI% / NROI% (same formulas as Sroi / SNROI) -->
                    <div class="card mb-3 border-primary">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-0 small fw-bold" for="lmpModalSpInput">Std Prc</label>
                                    <input type="number" class="form-control form-control-sm text-end fw-bold"
                                        id="lmpModalSpInput" step="0.01" min="0.01" placeholder="0.00"
                                        style="width: 7rem;" title="Manual Standard Price — use when LMP cannot be determined. Saves to Std Prc column only.">
                                </div>
                                <div class="col-auto">
                                    <div class="small text-muted mb-0">GROI %</div>
                                    <div id="lmpModalGroiPct" class="fs-5 fw-bold" style="min-width: 3.5rem;">—</div>
                                </div>
                                <div class="col-auto">
                                    <div class="small text-muted mb-0">NROI %</div>
                                    <div id="lmpModalNroiPct" class="fs-5 fw-bold" style="min-width: 3.5rem;">—</div>
                                </div>
                                <div class="col-auto small text-muted pb-1">
                                    Standard Price (manual). Saves to <strong>Std Prc</strong> for this SKU and all
                                    <strong>Sku Link LMP</strong> siblings. Use when LMP cannot be determined.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Competitor Form -->
                    <div class="card mb-3 border-success">
                        <div class="card-body">
                            <form id="addCompetitorForm" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label"><strong>SKU</strong></label>
                                    <input type="text" class="form-control" id="addCompSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="addCompAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="addCompPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="addCompLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-plus"></i> Add Competitor
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Competitors List -->
                    <div id="lmpDataList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="lmpTextPreviewOverlay" class="lmp-text-preview-overlay" hidden>
        <div class="lmp-text-preview-card" role="dialog" aria-modal="true">
            <div class="lmp-text-preview-head">
                <strong id="lmpTextPreviewTitle">Details</strong>
                <button type="button" class="btn-close btn-close-white" id="lmpTextPreviewClose" aria-label="Close"></button>
            </div>
            <div class="lmp-text-preview-body" id="lmpTextPreviewBody"></div>
        </div>
    </div>

    <!-- Sku Link LMP Modal (same as /purchase-master/sku-link-lmp) -->
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
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
    <!-- SKU Metrics Chart Modal (format matches all-marketplace-master) -->
    <div class="modal fade p-0" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden; max-height: 92vh;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="skuChartModalTitle">Amz - <span id="modalSkuName"></span> - Metrics</span> <span id="skuChartModalSuffix">(Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sku-chart-days-filter" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2" style="overflow: auto; max-height: calc(92vh - 42px);">
                    <div id="skuChartContainer" style="height: 32vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="skuMetricsChart"></canvas>
                        </div>
                        <div id="skuChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0; flex-wrap: nowrap; overflow-x: auto;">
                            <div class="sku-ref-col" data-metric="0" style="min-width: 62px; text-align: center; padding: 4px 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="skuChartRefDot" class="sku-col-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; flex-shrink: 0;"></span><span id="skuChartRefLabel">Price</span></div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div><div id="skuCol0High" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div><div id="skuCol0Med" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div><div id="skuCol0Low" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="skuChartLoading" class="text-center py-3" style="display: none;">
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

    <!-- Amz Metric Trend Chart Modal -->
    <div class="modal fade p-0" id="amzMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzChartModalTitle">Amz - Metric Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2" style="overflow: auto; max-height: calc(92vh - 42px);">
                    <div id="amzChartContainer" style="height: 32vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="amzMetricChart"></canvas>
                        </div>
                        <div id="amzChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="amzChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="amzChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="amzChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="amzChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data available for this metric.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.amazon-pef-promo', ['amazonPefPromoPart' => 'modals'])

@endsection

@section('script-bottom')
    <script>
        /** Stored in DB table channel_tabulator_column_settings (shared across all users — same pattern as ebay2/ebay3/mfrg tabulators). */
        const TABULATOR_COLUMN_CHANNEL = 'amazon_tabulator';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        const AMAZON_REMOVED_COL_FIELDS = {
            NR: true, nrp: true, NRL: true, FBA_Quantity: true, FBA: true, fba: true, fba_price: true, S_STATUS: true, PLS_STATUS: true
        };
        const TABULATOR_COLUMN_ORDER_URL = '/tabulator-column-order';
        let amazonApplyingColumnOrder = false;
        let amazonColumnOrderSaveTimer = null;
        let skuMetricsChart = null;
        let skuChartFirstSeriesStats = null; // { values, median, dataMin, dataMax, dotColors, labelColors } for ref panel & plugins
        let currentSkuChartMetric = 'price';  // 'price' | 'cvr' - which metric the SKU chart modal shows
        let currentSku = null;
        let table = null; // Global table reference
        let allTableData = []; // Full dataset for ParentExpand
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false; // Track Same Price mode (one price for all selected rows)
        // Single selection set for the leftmost row_select column (Prc Mode + bulk actions)
        let selectedRows = new Set();
        let selectedSkus = selectedRows; // alias — keep older call sites working
        let lmpMissingFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let blueTriangleFilterActive = false;

        // Escape string for safe use in HTML attribute (fixes SKUs with " e.g. WF 8"-890 1PC)
        function escAttr(s) {
            if (s == null) return '';
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        @include('partials.amazon-pef-promo', ['amazonPefPromoPart' => 'script'])

        function amazonNormalizeSkuKey(sku) {
            return String(sku == null ? '' : sku).replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function amazonCompetitorIsIgnored(item) {
            if (!item) return false;
            const v = item.ignored;
            return v === true || v === 1 || v === '1' || v === 'true' || v === 'yes';
        }

        function amazonCompetitorLandedPrice(item) {
            if (!item) return 0;
            if (item.landed_price != null && parseFloat(item.landed_price) > 0) {
                return parseFloat(item.landed_price);
            }
            const basePrice = parseFloat(item.price) || 0;
            let shipCost = 0;
            if (item.delivery) {
                const delText = String(item.delivery);
                if (!/\bfree\b/i.test(delText)) {
                    const paidMatch = delText.match(/\$\s*([\d,]+\.?\d*)\s*delivery/i)
                        || delText.match(/\$\s*([\d,]+\.?\d*)/);
                    if (paidMatch) {
                        shipCost = parseFloat(paidMatch[1].replace(/,/g, '')) || 0;
                    }
                }
            }
            return basePrice + shipCost;
        }

        function amazonL1FromCompetitors(competitors) {
            let l1 = null;
            let winner = null;
            (competitors || []).forEach(function(c) {
                if (amazonCompetitorIsIgnored(c)) return;
                const tp = amazonCompetitorLandedPrice(c);
                if (tp > 0 && (l1 === null || tp < l1)) {
                    l1 = tp;
                    winner = c;
                }
            });
            return { l1: l1, winner: winner };
        }

        function amazonApplyL1FromEntries(row) {
            if (!row || row.is_parent_summary) return row;
            const entries = row.lmp_entries || [];
            if (!entries.length) return row;
            const info = amazonL1FromCompetitors(entries);
            row.lmp_price = info.l1;
            if (info.winner) {
                if (info.winner.delivery != null) row.lmp_delivery = info.winner.delivery;
                if (info.winner.asin) row.lmp_asin = info.winner.asin;
                if (info.winner.link || info.winner.product_link) {
                    row.lmp_link = info.winner.product_link || info.winner.link;
                }
            } else {
                row.lmp_delivery = null;
                row.lmp_asin = null;
                row.lmp_link = null;
            }
            return row;
        }

        // Outer LMP = Model L1 from the same lmp_entries (DB ignored flags).
        // Used by the LMP column, Diff column, S PRC cap, and Diff filter.
        function lmpWithShipping(rowData) {
            if (!rowData) return 0;
            const entries = rowData.lmp_entries || [];
            if (entries.length) {
                const fromEntries = amazonL1FromCompetitors(entries);
                return fromEntries.l1 != null && fromEntries.l1 > 0 ? fromEntries.l1 : 0;
            }
            const base = parseFloat(rowData.lmp_price || 0) || 0;
            if (!base || base <= 0) return base;
            let shipCost = 0;
            if (rowData.lmp_delivery) {
                const m = String(rowData.lmp_delivery).match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (m) shipCost = parseFloat(m[1].replace(/,/g, '')) || 0;
            }
            return base + shipCost;
        }

        function amazonCapSpriceToLmp(rowData, sprice) {
            if (window.SpriceLmpCap) return SpriceLmpCap.prepare(rowData, sprice, lmpWithShipping);
            const lmp = lmpWithShipping(rowData);
            let s = parseFloat(sprice);
            if (!(s > 0)) return s;
            if (lmp > 0 && s + 0.0001 >= lmp) s = lmp;
            return +Number(s).toFixed(2);
        }

        /**
         * INV vs INV_AMZ map tolerance — same rule as /map-issues:
         * when 3% of INV is below 3 units, require an absolute gap > 3 units to be a mismatch;
         * otherwise apply the rounded 3% rule. Mapped when within tolerance.
         */
        function amazonInvWithinMapTolerance(inv, invAmz) {
            const invNum = parseFloat(inv) || 0;
            const amzNum = parseFloat(invAmz) || 0;
            if (invNum <= 0) {
                return true;
            }
            const diff = Math.abs(invNum - amzNum);
            let isNotMap;
            if (invNum * 0.03 < 3) {
                isNotMap = diff > 3;
            } else {
                isNotMap = Math.round((diff / invNum) * 100) > 3;
            }
            return !isNotMap;
        }

        /** Parent group key: Parent/parent field, or "PARENT xxx" pseudo-SKU on summary rows (matches table filters). */
        function amazonParentKeyFromRow(rowData) {
            if (!rowData) return '';
            var val = rowData['Parent'] != null ? rowData['Parent'] : (rowData['parent'] != null ? rowData['parent'] : '');
            var s = (val != null && val !== '') ? String(val).trim() : '';
            if (!s && rowData['(Child) sku']) {
                var sku = String(rowData['(Child) sku']).trim();
                if (sku.toUpperCase().indexOf('PARENT ') === 0) s = sku.slice(7).trim();
            }
            return String(s).trim().replace(/\s+/g, ' ');
        }

        // Amazon channel Ads% (TACOS) — same value as the Ads badge /all-marketplace-master.
        // Used for PFT% = GPFT% − Ads%, SPFT = SGPFT − Ads%, and net SROI (NROI-badge formula).
        const AMAZON_CHANNEL_ADS_PCT = {{ $amazonAdsPercent !== null ? (float) $amazonAdsPercent : 0 }};

        /**
         * Net SROI — same shape as the NROI badge:
         *   (gross profit $ − ad spend $) / COGS × 100
         * where ad spend $ = SPRICE × Ads%/100 and COGS = LP.
         * Returns null when SPRICE/LP are missing.
         */
        function amazonRowSprice(rowData) {
            if (typeof amzDisplayedSprice === 'function') {
                const live = amzDisplayedSprice(rowData);
                if (live > 0) return live;
            }
            const stored = parseFloat(rowData && rowData.SPRICE);
            return (isFinite(stored) && stored > 0) ? stored : 0;
        }

        function amazonComputeNetSroi(rowData) {
            if (!rowData) return null;
            const sprice = amazonRowSprice(rowData);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(sprice) || sprice <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
            const adsFrac = (parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0) / 100;
            const grossPft = (sprice * margin) - ship - lp;
            const adSpend = sprice * adsFrac;
            return ((grossPft - adSpend) / lp) * 100;
        }

        /**
         * Sroi (SGROI) — same formula as GROI% but with SPRICE:
         *   ((SPRICE × 0.80 − ship − lp) / lp) × 100
         * Returns null when SPRICE/LP are missing.
         */
        function amazonComputeSroi(rowData) {
            if (!rowData) return null;
            const sprice = amazonRowSprice(rowData);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(sprice) || sprice <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            return ((sprice * 0.80 - ship - lp) / lp) * 100;
        }

        function amazonModalFmtL30(v) {
            const n = parseFloat(v);
            if (isNaN(n)) return '—';
            return Math.round(n).toLocaleString('en-US');
        }
        function amazonModalFmtMoney(v) {
            const n = parseFloat(v);
            if (isNaN(n) || n <= 0) return '—';
            return '$' + n.toFixed(2);
        }
        function amazonModalGpftPftColoredHtml(fieldVal, kind) {
            kind = kind || 'gpft';
            if (window.MetricPctColors) {
                return MetricPctColors.htmlFor(kind, fieldVal, { decimals: 0, empty: '0%' });
            }
            const p = parseFloat(fieldVal);
            const n = isNaN(p) ? 0 : p;
            return '<span style="color:#dc3545;font-weight:600;">' + Math.round(n) + '%</span>';
        }
        /** GROI% — MetricPctColors */
        function amazonModalGroiColoredHtml(fieldVal) {
            if (window.MetricPctColors) {
                return MetricPctColors.htmlFor('groi', fieldVal, { decimals: 0, empty: '0%' });
            }
            const p = parseFloat(fieldVal);
            const n = isNaN(p) ? 0 : p;
            return '<span style="color:#dc3545;font-weight:600;">' + Math.round(n) + '%</span>';
        }
        /** CVR L30 — same formula and colors as tabulator CVR L30 column */
        function amazonModalCvrL30ColoredHtml(row) {
            const aL30 = parseFloat(row['A_L30']) || 0;
            const sess30 = parseFloat(row['Sess30']) || 0;
            if (sess30 === 0) {
                return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';
            }
            const cvr = (aL30 / sess30) * 100;
            let color = '#a00211';
            if (cvr > 4 && cvr <= 7) color = '#ffc107';
            else if (cvr > 7 && cvr <= 13) color = '#28a745';
            else if (cvr > 10) color = '#e83e8c';
            return '<span style="color: ' + color + '; font-weight: 600;">' + cvr.toFixed(1) + '%</span>';
        }
        function amazonModalCountEmphasisHtml(v) {
            const t = amazonModalFmtL30(v);
            if (t === '—') return '<span class="text-muted">—</span>';
            return '<span style="font-weight: 600;">' + t + '</span>';
        }
        /** Price column colors: vs LMP; reference price muted italic */
        function amazonModalChildPriceHtml(row) {
            const isListed = !row.is_missing_amazon;
            if (!isListed) return '<span class="text-muted">—</span>';
            const price = parseFloat(row.price || 0);
            const lmpPrice = parseFloat(row.lmp_price || 0);
            const lmpaPrice = parseFloat(row.price_lmpa || 0);
            if (price > 0) {
                if (lmpPrice > 0 && price > lmpPrice) {
                    return '<span style="color: #dc3545; font-weight: 600;">' + amazonModalFmtMoney(price) + '</span>';
                }
                return '<span>' + amazonModalFmtMoney(price) + '</span>';
            }
            const fallback = lmpPrice > 0 ? lmpPrice : (lmpaPrice > 0 ? lmpaPrice : 0);
            if (fallback > 0) {
                return '<span style="color: #6c757d; font-style: italic;" title="Reference (no Amz list price)">' + amazonModalFmtMoney(fallback) + '</span>';
            }
            return '<span class="text-muted">—</span>';
        }
        /** LMP column: red if LMP &lt; your price, else green (same as grid) */
        function amazonModalLmpColoredHtml(row) {
            const lmpRaw = row.lmp_price;
            const lmpPrice = parseFloat(lmpRaw);
            const totalCompetitors = parseInt(row.lmp_entries_total, 10) || 0;
            if (isNaN(lmpPrice) || lmpPrice <= 0) {
                if (!totalCompetitors) return '<span class="text-muted">N/A</span>';
                return '<span class="text-muted">—</span>';
            }
            const currentPrice = parseFloat(row.price || 0);
            const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
            return '<span style="color: ' + priceColor + '; font-weight: 600;">' + amazonModalFmtMoney(lmpPrice) + '</span>';
        }
        /**
         * SPRICE vs Amazon price: reduce / hold / increase → red / yellow / green dot.
         * Returns { kind, color, title } or null when no SPRICE.
         */
        function amazonHasBlueTriangle(data) {
            if (!data || data.is_parent_summary) return false;
            const sku = String(data['(Child) sku'] || data.sku || '').trim().toUpperCase();
            if (!sku || sku.indexOf('PARENT') === 0) return false;
            const price = parseFloat(data.price) || 0;
            const sprice = amazonRowSprice(data);
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function amazonListingPriceEqualsSprice(data, spriceOverride) {
            const price = parseFloat(data && data.price) || 0;
            const sprice = spriceOverride != null ? (parseFloat(spriceOverride) || 0) : amazonRowSprice(data);
            return price > 0 && sprice > 0 && Math.round(price * 100) === Math.round(sprice * 100);
        }
        function syncAmazonBlueTriangleBadgeState() {
            $('#amazon-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }
        function amazonSpriceChangeDotMeta(sprice, amazonPrice) {
            const sp = parseFloat(sprice) || 0;
            const ap = parseFloat(amazonPrice) || 0;
            if (sp <= 0) return null;
            if (ap <= 0) {
                return null;
            }
            const sp2 = sp.toFixed(2);
            const ap2 = ap.toFixed(2);
            if (parseFloat(sp2) < parseFloat(ap2)) {
                return { kind: 'reduce', color: '#dc3545', title: 'Reduced vs Amz price' };
            }
            if (parseFloat(sp2) > parseFloat(ap2)) {
                return { kind: 'increase', color: '#28a745', title: 'Increase vs Amz price' };
            }
            return null;
        }

        function amazonSpriceChangeDotHtml(sprice, amazonPrice, sku) {
            const meta = amazonSpriceChangeDotMeta(sprice, amazonPrice);
            if (!meta) return '';
            const tip = meta.title + ' — click for S PRC history';
            if (sku) {
                return '<button type="button" class="btn btn-sm p-0 view-sku-chart sprice-change-dot align-middle" ' +
                    'data-sku="' + escAttr(sku) + '" data-metric="sprice" ' +
                    'title="' + escAttr(tip) + '" ' +
                    'style="border:none;background:none;cursor:pointer;padding:0;line-height:1;vertical-align:middle;">' +
                    '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                    'background:' + meta.color + ';flex-shrink:0;"></span></button>';
            }
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + escAttr(tip) + '"></span>';
        }

        /** Editable S PRC — saves with /save-amazon-sprice on blur or Enter (same as grid SPRICE editor) */
        function amazonModalSpriceInputHtml(row) {
            const sku = row['(Child) sku'] || '';
            if (!sku) return '<span class="text-muted">—</span>';
            const sprice = parseFloat(row.SPRICE) || 0;
            const listPrice = parseFloat(row.price) || 0;
            const val = sprice > 0 ? sprice.toFixed(2) : '';
            let ph = 'S PRC';
            if (listPrice > 0) {
                ph = 'List $' + listPrice.toFixed(2);
            }
            return '<input type="number" class="form-control form-control-sm text-end parent-modal-sprice-input" inputmode="decimal" data-sku="' + escAttr(sku) + '" value="' + escAttr(val) + '" step="0.01" min="0.01" placeholder="' + escAttr(ph) + '" title="Enter S PRC — blur or Enter to save" style="max-width: 6.75rem; margin-left: auto;" />';
        }
        /** Accept / push from the parent pricing modal (Amazon only). */
        function amazonModalAcceptPushHtml(row) {
            const sku = row['(Child) sku'] || '';
            const sprice = parseFloat(row.SPRICE) || 0;
            const amazonStatus = row.SPRICE_STATUS || null;
            if (!sku || !sprice || sprice <= 0) {
                return '<span class="text-muted">N/A</span>';
            }

            let amazonIcon = '<i class="fas fa-check"></i>';
            let amazonColor = '#28a745';
            let amazonTitle = 'Push to Amz';
            if (amazonStatus === 'pushed') {
                amazonIcon = '<i class="fa-solid fa-check-double"></i>';
                amazonTitle = 'Price pushed to Amz (Double-click to mark as Applied)';
            } else if (amazonStatus === 'applied') {
                amazonIcon = '<i class="fa-solid fa-check-double"></i>';
                amazonTitle = 'Price applied to Amz (Double-click to change)';
            } else if (amazonStatus === 'error') {
                amazonIcon = '<i class="fa-solid fa-x"></i>';
                amazonColor = '#dc3545';
                amazonTitle = 'Error pushing to Amz';
            } else if (amazonStatus === 'processing') {
                amazonIcon = '<i class="fas fa-spinner fa-spin"></i>';
                amazonColor = '#ffc107';
                amazonTitle = 'Pushing to Amz...';
            }

            const asinVal = (row.asin != null && String(row.asin).trim() !== '') ? escAttr(String(row.asin).trim()) : '';
            return '<div style="display: flex; gap: 8px; align-items: center; justify-content: center;">' +
                '<button type="button" class="btn btn-sm parent-pricing-modal-apply-btn btn-circle" data-sku="' + escAttr(sku) + '" data-price="' + sprice + '" data-asin="' + asinVal + '" data-status="' + escAttr(amazonStatus || '') + '" title="' + escAttr(amazonTitle) + '" style="border: none; background: none; color: ' + amazonColor + '; padding: 0; cursor: pointer;">' + amazonIcon + '</button>' +
                '</div>';
        }
        /** S PFT % — NPFT schema via MetricPctColors */
        function amazonModalSpftColoredHtml(row) {
            // SPFT = SGPFT − Ads% (channel TACOS)
            const rawGpft = row.SGPFT;
            if (rawGpft === null || rawGpft === undefined || rawGpft === '') {
                return '<span class="text-muted">—</span>';
            }
            const sgpft = parseFloat(rawGpft);
            if (isNaN(sgpft)) return '<span class="text-muted">—</span>';
            const percent = sgpft - (parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0);
            if (window.MetricPctColors) {
                return MetricPctColors.htmlFor('npft', percent, { decimals: 0 });
            }
            return '<span style="color:#dc3545;font-weight:600;">' + Math.round(percent) + '%</span>';
        }
        /** SROI % — NROI schema via MetricPctColors */
        function amazonModalSroiColoredHtml(row) {
            const percent = amazonComputeNetSroi(row);
            if (percent === null || !isFinite(percent)) return '<span class="text-muted">—</span>';
            if (window.MetricPctColors) {
                return MetricPctColors.htmlFor('nroi', percent, { decimals: 0 });
            }
            return '<span style="color:#dc3545;font-weight:600;">' + Math.round(percent) + '%</span>';
        }
        function collectChildRowsForAmazonParent(parentKey) {
            if (typeof table === 'undefined' || !table || !parentKey) return [];
            const norm = String(parentKey).trim().replace(/\s+/g, ' ');
            const all = table.getData('all') || [];
            return all.filter(function(r) {
                if (!r || r.is_parent_summary) return false;
                return amazonParentKeyFromRow(r) === norm;
            }).sort(function(a, b) {
                return String(a['(Child) sku'] || '').localeCompare(String(b['(Child) sku'] || ''));
            });
        }
        function showParentPricingBreakdownModal(parentKey) {
            const rows = collectChildRowsForAmazonParent(parentKey);
            if (typeof $ === 'undefined') return;
            $('#parentPricingBreakdownModal').data('amazonParentKey', parentKey || '');
            $('#parent-pricing-modal-title').text(parentKey || '—');
            const tbody = $('#parent-pricing-breakdown-tbody');
            tbody.empty();
            if (!rows.length) {
                tbody.append('<tr><td colspan="16" class="text-center text-muted py-3">No child SKUs found for this parent.</td></tr>');
            } else {
                rows.forEach(function(row) {
                    const sku = row['(Child) sku'] || '—';
                    const tr = $('<tr></tr>').attr('data-child-sku', sku);
                    tr.append($('<td></td>').text(sku));
                    tr.append($('<td class="text-end"></td>').html(amazonModalChildPriceHtml(row)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalCountEmphasisHtml(row.Sess30)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalCountEmphasisHtml(row.L30)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalCountEmphasisHtml(row['A_L30'])));
                    tr.append($('<td class="text-end"></td>').html(amazonModalCvrL30ColoredHtml(row)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalGpftPftColoredHtml(row['GPFT%'])));
                    // PFT% = GPFT% − Ads% (channel TACOS)
                    const modalPft = (parseFloat(row['GPFT%']) || 0) - (parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0);
                    tr.append($('<td class="text-end"></td>').html(amazonModalGpftPftColoredHtml(modalPft, 'npft')));
                    tr.append($('<td class="text-end"></td>').html(amazonModalGroiColoredHtml(row['GROI%'])));
                    tr.append($('<td class="text-end"></td>').html(amazonModalLmpColoredHtml(row)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalSpriceInputHtml(row)));
                    tr.append($('<td class="text-center"></td>').html(amazonModalAcceptPushHtml(row)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalSroiColoredHtml(row)));
                    tr.append($('<td class="text-end"></td>').html(amazonModalSpftColoredHtml(row)));
                    tbody.append(tr);
                });
            }
            $('#parentPricingBreakdownModal').modal('show');
        }

        // Play / Pause parent navigation (same as product-master / eBay)
        let productUniqueParents = [];
        let isProductNavigationActive = false;
        let currentProductParentIndex = -1;

        // Chart.js is loaded only when a chart modal opens (PEF promo also calls window.loadChartJs).
        let chartJsLoadPromise = null;
        function loadChartJs() {
            if (typeof Chart !== 'undefined') return Promise.resolve();
            if (chartJsLoadPromise) return chartJsLoadPromise;
            chartJsLoadPromise = new Promise(function(resolve, reject) {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
                s.onload = function() { resolve(); };
                s.onerror = reject;
                document.head.appendChild(s);
            });
            return chartJsLoadPromise;
        }
        window.loadChartJs = loadChartJs;
        function withChartJs(fn) {
            loadChartJs().then(fn).catch(function() {
                if (typeof showToast === 'function') showToast('error', 'Could not load chart library');
            });
        }

        // === Amazon Metric Trend Chart ===
        let amzChartInstance = null;
        let amzChartDays = 30;
        let amzChartMetricKey = '';
        let amzChartAjax = null;

        const amzMetricLabels = {
            'l30_sales': 'L30 Sales', 'l30_orders': 'L30 Orders', 'qty': 'Total Qty',
            'gprofit': 'Gprofit%', 'groi': 'G ROI%',
            'npft': 'N PFT%', 'missing_l': 'Miss',
            'nmap': 'Miss M',
            // Badge-stat metrics (daily snapshot counts)
            'sold_count': 'Sold >0', 'zero_sold_count': '0 Sold',
            'map_count': 'Miss M', 'nmap_count': 'Miss M', 'missing_count': 'Miss L',
            'prc_gt_lmp_count': 'Prc > LMP',
            'total_pft': 'PFT', 'total_sales': 'Sales',
            'gpft_pct': 'GPFT%', 'npft_pct': 'PFT%', 'groi_pct': 'GROI%', 'nroi_pct': 'NROI%',
            'tcos_pct': 'Ads%', 'total_l30_orders': 'Qty',
        };

        // Metrics stored in badge stats table (daily counts/amounts)
        const amzBadgeStatMetrics = [
            'sold_count', 'zero_sold_count', 'map_count', 'nmap_count',
            'missing_count', 'prc_gt_lmp_count',
            'total_pft', 'total_sales',
            'gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct',
            'tcos_pct', 'total_l30_orders',
        ];

        const amzPctMetrics = ['gprofit', 'groi', 'npft', 'gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct', 'tcos_pct'];
        const amzDollarMetrics = ['l30_sales', 'total_pft', 'total_sales'];
        /** Metrics where lower is better → invert 3-color (up=red, down=green) */
        const amzBadgeInvertMetrics = { tcos_pct: true, zero_sold_count: true, prc_gt_lmp_count: true };
        let amzBadgePrevDay = null;
        let amzBadgePrevDayLoaded = false;

        function amzFmtVal(v) {
            if (amzDollarMetrics.includes(amzChartMetricKey)) return '$' + Math.round(v).toLocaleString('en-US');
            if (amzPctMetrics.includes(amzChartMetricKey)) return v.toFixed(1) + '%';
            return Math.round(v).toLocaleString('en-US');
        }

        function amzTodayPtDate() {
            try {
                return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
            } catch (e) {
                const d = new Date();
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }
        }

        function amzChartXTicks(labelCount) {
            return {
                maxRotation: 90,
                minRotation: 90,
                autoSkip: false,
                autoSkipPadding: 0,
                font: { size: labelCount > 45 ? 9 : 10, weight: '600' },
                callback: function(value) {
                    return this.getLabelForValue(value);
                }
            };
        }

        function amzHistoryRowIsEmpty(r) {
            if (!r) return true;
            if (currentSkuChartMetric === 'prmt' && r.prmt_pct != null && r.prmt_pct !== '' && isFinite(Number(r.prmt_pct))) return false;
            if (currentSkuChartMetric === 'push_prc' && Number(r.push_prc) > 0) return false;
            if (currentSkuChartMetric === 'sprice' && Number(r.sprice) > 0) return false;
            const views = Number(r.views || r.total_views || 0);
            const aL30 = Number(r.a_l30 || 0);
            const cvr = Number(r.cvr_percent || r.avg_cvr_percent || 0);
            return views <= 0 && aL30 <= 0 && cvr <= 0;
        }

        function amzFillEveryDate(rows, days) {
            const n = parseInt(days, 10) || 0;
            const src = Array.isArray(rows) ? rows.slice() : [];
            if (n <= 0) return src;
            const byDate = {};
            src.forEach(function(r) {
                const key = r.full_date || r.date || '';
                if (key && /^\d{4}-\d{2}-\d{2}$/.test(key) && !amzHistoryRowIsEmpty(r)) byDate[key] = r;
            });
            const end = new Date(amzTodayPtDate() + 'T12:00:00');
            const out = [];
            let carry = null;
            for (let i = n - 1; i >= 0; i--) {
                const d = new Date(end);
                d.setDate(end.getDate() - i);
                const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                const label = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
                if (byDate[key] && !amzHistoryRowIsEmpty(byDate[key])) {
                    carry = byDate[key];
                    const row = Object.assign({}, carry);
                    row.date = label;
                    row.date_formatted = label;
                    row.full_date = key;
                    if (!row.source) row.source = (key === amzTodayPtDate()) ? 'live' : 'snapshot';
                    out.push(row);
                    continue;
                }
                if (!carry) continue;
                const row = Object.assign({}, carry);
                row.date = label;
                row.date_formatted = label;
                row.source = 'carried';
                row.full_date = key;
                out.push(row);
            }
            return out.length ? out : src.slice(-n);
        }

        function amzFmtBadgeVal(v, metricKey) {
            const n = Number(v);
            if (!isFinite(n)) return '—';
            const key = (metricKey || '').toString();
            const $b = $('#summary-stats .badge[data-metric="' + key + '"]').first();
            const fmt = ($b.data('format') || '').toString();
            if (fmt === 'money' || amzDollarMetrics.includes(key)) return '$' + Math.round(n).toLocaleString('en-US');
            if (fmt === 'pct' || amzPctMetrics.includes(key)) return (/gpft|npft|groi|nroi|tcos/i.test(key) ? Math.round(n) : n.toFixed(1)) + '%';
            return Math.round(n).toLocaleString('en-US');
        }
        function amzTrendClass(curr, prev, invert) {
            if (!isFinite(curr) || !isFinite(prev)) return 'none';
            const diff = curr - prev;
            let cls = 'flat';
            if (diff > 0.05) cls = 'up';
            else if (diff < -0.05) cls = 'down';
            if (invert && cls === 'up') cls = 'down';
            else if (invert && cls === 'down') cls = 'up';
            return cls;
        }
        function applyAmzSummaryTrendDot(metricKey, currentVal) {
            const $dot = $('#summary-stats .summary-trend-dot[data-metric="' + metricKey + '"]');
            if (!$dot.length) return;
            const prev = amzBadgePrevDay && amzBadgePrevDay[metricKey];
            const invert = !!amzBadgeInvertMetrics[metricKey];
            if (!isFinite(currentVal) || prev == null || !isFinite(prev)) {
                $dot.attr('class', 'summary-trend-dot none')
                    .attr('title', 'Click for rolling history (no prior day yet)');
                return;
            }
            const cls = amzTrendClass(currentVal, prev, invert);
            const tip = (cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same'))
                + ' vs prior day (' + amzFmtBadgeVal(prev, metricKey)
                + ' → ' + amzFmtBadgeVal(currentVal, metricKey) + '). Click for rolling history.';
            $dot.attr('class', 'summary-trend-dot ' + cls).attr('title', tip);
        }
        function syncAmzSummaryTrendDots() {
            ensureAmzKpiDots();
            $('#summary-stats [data-metric]').each(function() {
                const metric = $(this).data('metric');
                if (!metric || !$(this).find('.summary-trend-dot').addBack('.summary-trend-dot').length) return;
                if ($(this).hasClass('summary-trend-dot')) return;
                let live = parseFloat($(this).attr('data-live-value'));
                if (!isFinite(live)) live = parseFloat($(this).data('live-value'));
                applyAmzSummaryTrendDot(metric, live);
            });
        }
        function loadAmzBadgePrevDay(done) {
            if (amzBadgePrevDayLoaded) {
                syncAmzSummaryTrendDots();
                if (typeof done === 'function') done();
                return;
            }
            $.ajax({
                url: '/amazon-badge-prev-day',
                method: 'GET',
                success: function(resp) {
                    amzBadgePrevDayLoaded = true;
                    amzBadgePrevDay = (resp && resp.success && resp.metrics) ? resp.metrics : null;
                    syncAmzSummaryTrendDots();
                    if (typeof done === 'function') done();
                },
                error: function() {
                    amzBadgePrevDayLoaded = true;
                    amzBadgePrevDay = null;
                    syncAmzSummaryTrendDots();
                    if (typeof done === 'function') done();
                }
            });
        }
        function ensureAmzKpiDots() {
            $('#summary-stats .d-flex > .badge').each(function() {
                if (this.id === 'rows-count-badge') return;
                const $b = $(this);
                let $dot = $b.children('.summary-trend-dot').first();
                if (!$dot.length) $dot = $b.find('.summary-trend-dot').first();
                if (!$dot.length) {
                    const metric = $b.attr('data-metric') || '';
                    $dot = $('<span class="summary-trend-dot none" title="Rolling history"></span>');
                    if (metric) $dot.attr('data-metric', metric);
                    $b.prepend($dot);
                } else if ($b.children().get(0) !== $dot.get(0)) {
                    $b.prepend($dot);
                }
            });
        }
        function setAmzSummaryBadge($el, label, liveVal) {
            if (!$el || !$el.length) return;
            const $dot = $el.find('.summary-trend-dot').first().detach();
            $el.text(label);
            if ($dot.length) $el.prepend($dot);
            else ensureAmzKpiDots();
            if (liveVal != null && isFinite(liveVal)) {
                $el.attr('data-live-value', liveVal);
            }
        }

        function showAmzMetricChart(metricKey) {
            amzChartMetricKey = metricKey;
            amzChartDays = 30;
            $('#amzChartRangeSelect').val('30');
            const label = amzMetricLabels[metricKey] || metricKey;
            const isBadge = amzBadgeStatMetrics.includes(metricKey);
            const badgeSnapshotMetrics = ['total_pft', 'total_sales', 'gpft_pct', 'npft_pct', 'groi_pct', 'nroi_pct', 'tcos_pct', 'total_l30_orders'];
            const suffix = isBadge ? (badgeSnapshotMetrics.includes(metricKey) ? 'Daily Snapshot' : 'Daily Count') : 'Rolling L30';
            $('#amzChartModalTitle').text(`Amz - ${label} (${suffix})`);
            withChartJs(function() {
                const modal = new bootstrap.Modal(document.getElementById('amzMetricChartModal'));
                modal.show();
                loadAmzMetricChart();
            });
        }

        function loadAmzMetricChart() {
            if (amzChartAjax) amzChartAjax.abort();
            $('#amzChartNoData').hide();
            $('#amzChartContainer').hide();
            $('#amzChartLoading').show();

            // Determine endpoint based on metric type
            const isBadgeStat = amzBadgeStatMetrics.includes(amzChartMetricKey);
            const ajaxUrl = isBadgeStat ? '/amazon-badge-chart-data' : '/channel-metric-chart-data';
            const ajaxData = isBadgeStat
                ? { metric: amzChartMetricKey, days: amzChartDays }
                : { channel: 'amazon', metric: amzChartMetricKey, days: amzChartDays };

            amzChartAjax = $.ajax({
                url: ajaxUrl,
                method: 'GET',
                data: ajaxData,
                success: function(resp) {
                    amzChartAjax = null;
                    $('#amzChartLoading').hide();
                    if (resp.success && resp.data && resp.data.length > 0) {
                        $('#amzChartContainer').show();
                        renderAmzMetricChart(amzFillEveryDate(resp.data, amzChartDays));
                    } else {
                        $('#amzChartNoData').show();
                    }
                },
                error: function(xhr, status) {
                    amzChartAjax = null;
                    if (status === 'abort') return;
                    $('#amzChartLoading').hide();
                    $('#amzChartNoData').show();
                }
            });
        }

        $(document).on('change', '#amzChartRangeSelect', function() {
            const days = parseInt($(this).val());
            if (days === amzChartDays) return;
            amzChartDays = days;
            const rangeLabel = days === 0 ? 'Lifetime' : 'L' + days;
            const titleEl = $('#amzChartModalTitle');
            titleEl.text(titleEl.text().replace(/\(Rolling [^)]+\)/, `(Rolling ${rangeLabel})`));
            loadAmzMetricChart();
        });

        document.addEventListener('click', function(e) {
            const el = e.target && e.target.closest ? e.target.closest('#summary-stats .summary-trend-dot') : null;
            if (!el) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
            const m = el.getAttribute('data-metric')
                || (el.closest('[data-metric]') && el.closest('[data-metric]').getAttribute('data-metric'));
            if (m && (amzBadgeStatMetrics.indexOf(m) !== -1 || amzMetricLabels[m])) {
                showAmzMetricChart(m);
            }
        }, true);
        // Badge click handler
        $(document).on('click', '.amz-badge-chart', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            const id = this.id || '';
            if (id === '' && $(this).hasClass('sold-filter-badge')) return;
            showAmzMetricChart($(this).data('metric'));
        });

        // Hover-to-chart for badges (500ms delay). Filter badges: no hover chart so click = filter only.
        let amzHoverTimer = null;
        var amzHoverChartFilterBadgeSelector = '.sold-filter-badge, .map-filter-badge';
        $(document).on('mouseenter', '.amz-hover-chart', function() {
            if ($(this).is(amzHoverChartFilterBadgeSelector)) return; // filter badges: click applies filter, never open chart on hover
            const metric = $(this).data('metric');
            if (!metric) return;
            amzHoverTimer = setTimeout(() => {
                showAmzMetricChart(metric);
            }, 500);
        });
        $(document).on('mouseleave', '.amz-hover-chart', function() {
            if (amzHoverTimer) { clearTimeout(amzHoverTimer); amzHoverTimer = null; }
        });
        $(document).on('mousedown', '.amz-hover-chart', function() {
            if (amzHoverTimer) { clearTimeout(amzHoverTimer); amzHoverTimer = null; }
        });

        function renderAmzMetricChart(data) {
            const ctx = document.getElementById('amzMetricChart').getContext('2d');
            if (amzChartInstance) amzChartInstance.destroy();

            const labels = data.map(d => d.date);
            const values = data.map(d => d.value);

            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;

            document.getElementById('amzChartHighest').textContent = amzFmtVal(dataMax);
            document.getElementById('amzChartMedian').textContent = amzFmtVal(median);
            document.getElementById('amzChartLowest').textContent = amzFmtVal(dataMin);

            const dotColors = values.map((v, i) => {
                if (data[i] && data[i].source === 'carried') return '#ced4da';
                if (data[i] && data[i].source === 'live') return '#0d6efd';
                return i === 0 ? '#6c757d' : v < values[i - 1] ? '#dc3545' : v > values[i - 1] ? '#28a745' : '#6c757d';
            });
            const labelColors = values.map(v => v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d');

            const medianLinePlugin = {
                id: 'medianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y, xScale = chart.scales.x, ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    ctx.save(); ctx.setLineDash([6, 4]); ctx.strokeStyle = '#6c757d'; ctx.lineWidth = 1.2;
                    ctx.beginPath(); ctx.moveTo(xScale.left, yPixel); ctx.lineTo(xScale.right, yPixel); ctx.stroke(); ctx.restore();
                }
            };

            const valueLabelsPlugin = {
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const dataset = chart.data.datasets[0], meta = chart.getDatasetMeta(0), ctx = chart.ctx;
                    ctx.save(); ctx.font = 'bold 7px Inter, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const offsetY = (i % 2 === 0) ? -7 : -14;
                        ctx.fillStyle = labelColors[i];
                        ctx.fillText(amzFmtVal(dataset.data[i]), point.x, point.y + offsetY);
                    });
                    ctx.restore();
                }
            };

            amzChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: 'rgba(108,117,125,0.08)',
                        borderColor: '#adb5bd',
                        borderWidth: 1.5,
                        fill: true, tension: 0.3,
                        pointRadius: 3, pointHoverRadius: 5,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true, maintainAspectRatio: false,
                    layout: { padding: { top: 36, left: 8, right: 16, bottom: 28 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                            callbacks: {
                                label: function(context) {
                                    const idx = context.dataIndex;
                                    let parts = ['Value: ' + amzFmtVal(context.raw)];
                                    if (idx > 0) {
                                        const diff = context.raw - values[idx - 1];
                                        parts.push('vs Yesterday: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + amzFmtVal(Math.abs(diff)));
                                    }
                                    if (idx >= 7) {
                                        const diff7 = context.raw - values[idx - 7];
                                        parts.push('vs 7d Ago: ' + (diff7 < 0 ? '▼' : diff7 > 0 ? '▲' : '▬') + ' ' + amzFmtVal(Math.abs(diff7)));
                                    }
                                    return parts;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { min: yMin, max: yMax, ticks: { font: { size: 9 }, callback: v => amzFmtVal(v) } },
                        x: { offset: true, ticks: amzChartXTicks(labels.length) }
                    }
                }
            });
        }

        // Format helper for SKU chart first series (Price)
        function skuChartFmtVal(v) {
            return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        }

        // SKU-specific chart (layout/plugins match all-marketplace-master: ref panel, median line, value labels on first series)
        function initSkuMetricsChart() {
            const ctx = document.getElementById('skuMetricsChart').getContext('2d');

            const medianLinePlugin = {
                id: 'skuMedianLine',
                afterDraw(chart) {
                    if (!skuChartFirstSeriesStats || skuChartFirstSeriesStats.median === undefined) return;
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    const ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(skuChartFirstSeriesStats.median);
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
                id: 'skuValueLabels',
                afterDatasetsDraw(chart) {
                    if (!chart.data.datasets.length) return;
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.font = 'bold 6px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    const fmt = skuChartFmtVal;
                    const seriesColor = dataset.borderColor || '#6c757d';
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        if (val == null) return;
                        const offsetY = (i % 2 === 0) ? -6 : -10;
                        const valueFmt = (skuChartFirstSeriesStats && skuChartFirstSeriesStats.valueFmt) ? skuChartFirstSeriesStats.valueFmt : skuChartFmtVal;
                        ctx.fillStyle = seriesColor;
                        ctx.fillText(valueFmt(val), point.x, point.y + offsetY);
                    });
                    ctx.restore();
                }
            };

            skuMetricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Price (USD)',
                            data: [],
                            borderColor: '#adb5bd',
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderWidth: 1.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            yAxisID: 'y',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 36, left: 8, right: 16, bottom: 28 } },
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
                                    return 'Price: ' + skuChartFmtVal(context.parsed.y || 0);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            offset: true,
                            ticks: amzChartXTicks(30)
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: { font: { size: 9 }, callback: function(v) { return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US')); } }
                        }
                    }
                }
            });
        }

        function openAmazonSkuChart(sku, metric) {
            if (!sku) return;
            currentSkuChartMetric = metric || 'price';
            currentSku = sku;
            $('#modalSkuName').text(sku);
            $('#sku-chart-days-filter').val('30');
            const metricLabels = { cvr: 'CVR%', views: 'View L30', inv: 'INV', inv_amz: 'INV AMZ', al30: 'A L30', ovl30: 'OV L30', sprice: 'S PRC', prmt: 'PRMT %', cpn: 'CPN %', push_prc: 'Push Prc' };
            const metricLabel = metricLabels[currentSkuChartMetric] || 'Price';
            $('#skuChartModalSuffix').text(metricLabel + ' (Rolling L30)');
            $('#skuChartLoading').show();
            $('#skuChartContainer').hide();
            $('#chart-no-data-message').hide();
            withChartJs(function() {
                if (!skuMetricsChart) initSkuMetricsChart();
                loadSkuMetricsData(sku, 30);
                $('#skuMetricsModal').modal('show');
            });
        }

        function loadSkuMetricsData(sku, days = 30) {
            $('#skuChartLoading').show();
            $('#skuChartContainer').hide();
            $('#chart-no-data-message').hide();
            const daysNum = days === 0 || days === '0' ? 0 : (parseInt(days, 10) || 30);
            fetch(`/amazon-metrics-history?days=${daysNum}&sku=${encodeURIComponent(sku)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    $('#skuChartLoading').hide();
                    if (!skuMetricsChart) return;
                    function setSkuRefCol(dsIdx, high, med, low, fmt) {
                        const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                        const hEl = document.getElementById('skuCol' + dsIdx + 'High');
                        const mEl = document.getElementById('skuCol' + dsIdx + 'Med');
                        const lEl = document.getElementById('skuCol' + dsIdx + 'Low');
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
                        skuChartFirstSeriesStats = null;
                        const h = document.getElementById('skuCol0High');
                        const m = document.getElementById('skuCol0Med');
                        const l = document.getElementById('skuCol0Low');
                        if (h) h.textContent = '-';
                        if (m) m.textContent = '-';
                        if (l) l.textContent = '-';
                        skuMetricsChart.data.labels = [];
                        skuMetricsChart.data.datasets.forEach(dataset => { dataset.data = []; });
                        skuMetricsChart.update('active');
                        $('#chart-no-data-message').show();
                        return;
                    }
                    const liveRow = (typeof getAmazonTabulatorRowDataBySku === 'function')
                        ? getAmazonTabulatorRowDataBySku(sku)
                        : null;
                    if (liveRow) {
                        const today = amzTodayPtDate();
                        const todayLabel = new Date(today + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
                        const aL30 = parseFloat(liveRow['A_L30']) || 0;
                        const sess30 = parseFloat(liveRow['Sess30']) || 0;
                        const liveCvr = sess30 > 0 ? (aL30 / sess30) * 100 : 0;
                        let last = data.length ? data[data.length - 1] : null;
                        const lastKey = last ? (last.date || last.full_date || '') : '';
                        const livePrmt = (typeof computeAmzLivePrmtPct === 'function')
                            ? computeAmzLivePrmtPct(liveRow)
                            : (parseFloat(liveRow.prmt_pct) || 0);
                        const liveSprice = (typeof amazonRowSprice === 'function')
                            ? amazonRowSprice(liveRow)
                            : (parseFloat(liveRow.SPRICE) || 0);
                        if (last && (lastKey === today || last.date_formatted === todayLabel)) {
                            last.cvr_percent = liveCvr;
                            last.views = sess30;
                            last.a_l30 = aL30;
                            last.price = parseFloat(liveRow.price || last.price) || last.price;
                            last.prmt_pct = isFinite(livePrmt) ? livePrmt : last.prmt_pct;
                            if (liveSprice > 0) last.sprice = liveSprice;
                            last.source = 'live';
                        } else {
                            data.push({
                                date: today,
                                full_date: today,
                                date_formatted: todayLabel,
                                cvr_percent: liveCvr,
                                views: sess30,
                                a_l30: aL30,
                                price: parseFloat(liveRow.price) || 0,
                                sprice: liveSprice > 0 ? liveSprice : (parseFloat(liveRow.price) || 0),
                                prmt_pct: isFinite(livePrmt) ? livePrmt : null,
                                source: 'live'
                            });
                        }
                    }
                    data = amzFillEveryDate(data, daysNum);
                    const labels = data.map(d => d.date_formatted || d.date || '');
                    const isCvr = currentSkuChartMetric === 'cvr';
                    const isViews = currentSkuChartMetric === 'views';
                    const isInv = currentSkuChartMetric === 'inv';
                    const isInvAmz = currentSkuChartMetric === 'inv_amz';
                    const isAl30 = currentSkuChartMetric === 'al30';
                    const isOvl30 = currentSkuChartMetric === 'ovl30';
                    const isSprice = currentSkuChartMetric === 'sprice';
                    const isPrmt = currentSkuChartMetric === 'prmt';
                    const isCpn = currentSkuChartMetric === 'cpn';
                    const isPushPrc = currentSkuChartMetric === 'push_prc';
                    const intFmt = v => Math.round(Number(v) || 0).toLocaleString('en-US');
                    const values = isCvr ? data.map(d => Number(d.cvr_percent) || 0)
                        : isViews ? data.map(d => Number(d.views) || 0)
                        : isInv ? data.map(d => Number(d.inv) ?? 0)
                        : isInvAmz ? data.map(d => Number(d.inv_amz) ?? 0)
                        : isAl30 ? data.map(d => Number(d.a_l30) || 0)
                        : isOvl30 ? data.map(d => Number(d.l30) ?? 0)
                        : isSprice ? data.map(d => {
                            const sp = Number(d.sprice);
                            if (isFinite(sp) && sp > 0) return sp;
                            return Number(d.price) || 0;
                        })
                        : isPushPrc ? data.map(d => {
                            const n = Number(d.push_prc);
                            return isFinite(n) && n > 0 ? n : null;
                        })
                        : isPrmt ? data.map(d => {
                            const n = Number(d.prmt_pct);
                            return isFinite(n) ? n : null;
                        })
                        : isCpn ? data.map(d => {
                            const n = Number(d.cpn_pct);
                            return isFinite(n) ? n : null;
                        })
                        : data.map(d => Number(d.price) || 0);
                    const refLabelEl = document.getElementById('skuChartRefLabel');
                    const refDotEl = document.getElementById('skuChartRefDot');
                    const refLabels = { cvr: 'CVR%', views: 'View L30', inv: 'INV', inv_amz: 'INV AMZ', al30: 'A L30', ovl30: 'OV L30', sprice: 'S PRC', prmt: 'PRMT %', cpn: 'CPN %', push_prc: 'Push Prc' };
                    const refLabelText = refLabels[currentSkuChartMetric] || 'Price';
                    if (refLabelEl) refLabelEl.textContent = refLabelText;
                    const refColors = { cvr: '#008000', views: '#0000FF', inv: '#6c757d', inv_amz: '#17a2b8', al30: '#e83e8c', ovl30: '#fd7e14', sprice: '#0d6efd', prmt: '#0d6efd', cpn: '#20c997', push_prc: '#FF9900' };
                    if (refDotEl) refDotEl.style.background = refColors[currentSkuChartMetric] || '#adb5bd';

                    if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.x) {
                        skuMetricsChart.options.scales.x.offset = true;
                        skuMetricsChart.options.scales.x.ticks = amzChartXTicks(labels.length);
                    }
                    skuMetricsChart.data.labels = labels;
                    skuMetricsChart.data.datasets[0].data = values;
                    skuMetricsChart.data.datasets[0].label = refLabelText + ((currentSkuChartMetric === 'price' || isSprice || isPushPrc) ? ' (USD)' : ((isPrmt || isCpn || isCvr) ? ' (%)' : ''));
                    skuMetricsChart.data.datasets[0].borderColor = refColors[currentSkuChartMetric] || '#adb5bd';
                    const bgColors = { cvr: 'rgba(0, 128, 0, 0.1)', views: 'rgba(0, 0, 255, 0.1)', inv: 'rgba(108,117,125,0.1)', inv_amz: 'rgba(23,162,184,0.1)', al30: 'rgba(232,62,140,0.1)', ovl30: 'rgba(253,126,20,0.1)', sprice: 'rgba(13,110,253,0.1)', prmt: 'rgba(13,110,253,0.1)', cpn: 'rgba(32,201,151,0.1)', push_prc: 'rgba(255,153,0,0.12)' };
                    skuMetricsChart.data.datasets[0].backgroundColor = bgColors[currentSkuChartMetric] || 'rgba(108,117,125,0.08)';
                    const cvrFmt = v => (Number(v) === v ? v.toFixed(1) : v) + '%';
                    const viewsFmt = intFmt;
                    const refFmt = (isCvr || isPrmt || isCpn) ? cvrFmt : (isViews || isInv || isInvAmz || isAl30 || isOvl30) ? intFmt : skuChartFmtVal;
                    if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y) {
                        if (isCvr || isPrmt || isCpn) skuMetricsChart.options.scales.y.ticks.callback = function(v) { return v.toFixed(0) + '%'; };
                        else if (isViews || isInv || isInvAmz || isAl30 || isOvl30) skuMetricsChart.options.scales.y.ticks.callback = function(v) { return Math.round(v).toLocaleString('en-US'); };
                        else skuMetricsChart.options.scales.y.ticks.callback = function(v) { return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US')); };
                    }
                    if (skuMetricsChart.options.plugins && skuMetricsChart.options.plugins.tooltip && skuMetricsChart.options.plugins.tooltip.callbacks) {
                        if (isCvr) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'CVR%: ' + (context.parsed.y != null ? (Number(context.parsed.y).toFixed(1) + '%') : '-'); };
                        else if (isPrmt) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'PRMT %: ' + (context.parsed.y != null ? (Number(context.parsed.y).toFixed(1) + '%') : '-'); };
                        else if (isCpn) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'CPN %: ' + (context.parsed.y != null ? (Number(context.parsed.y).toFixed(1) + '%') : '-'); };
                        else if (isViews) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'View L30: ' + (context.parsed.y != null ? intFmt(context.parsed.y) : '-'); };
                        else if (isInv || isInvAmz || isAl30 || isOvl30) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return refLabelText + ': ' + (context.parsed.y != null ? intFmt(context.parsed.y) : '-'); };
                        else if (isSprice) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'S PRC: ' + skuChartFmtVal(context.parsed.y || 0); };
                        else if (isPushPrc) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'Push Prc: ' + skuChartFmtVal(context.parsed.y || 0); };
                        else skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'Price: ' + skuChartFmtVal(context.parsed.y || 0); };
                    }

                    const s0 = statsForArr(values);
                    setSkuRefCol(0, s0.max, s0.median, s0.min, refFmt);

                    const refRed = '#dc3545';
                    const refGray = '#6c757d';
                    const refGreen = '#198754';
                    const dotColors = values.map((v, i) => {
                        if (data[i] && data[i].source === 'carried') return '#ced4da';
                        if (data[i] && data[i].source === 'live') return '#0d6efd';
                        if (i === 0) return refGray;
                        return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                    });
                    const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);
                    skuChartFirstSeriesStats = { values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors, labelColors, valueFmt: refFmt };
                    skuMetricsChart.data.datasets[0].pointBackgroundColor = dotColors;
                    skuMetricsChart.data.datasets[0].pointBorderColor = dotColors;
                    skuMetricsChart.data.datasets[0].pointBorderWidth = 1.5;

                    $('#skuChartContainer').show();
                    skuMetricsChart.update('active');
                })
                .catch(error => {
                    $('#skuChartLoading').hide();
                    skuChartFirstSeriesStats = null;
                    const h = document.getElementById('skuCol0High');
                    const m = document.getElementById('skuCol0Med');
                    const l = document.getElementById('skuCol0Low');
                    if (h) h.textContent = '-';
                    if (m) m.textContent = '-';
                    if (l) l.textContent = '-';
                    $('#chart-no-data-message').show();
                    console.error('Error loading SKU metrics data:', error);
                });
        }

        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null,
            linkedLmpSkus: [],
            rowData: null
        };

        function getAmazonTabulatorRowDataBySku(sku) {
            if (!sku) return null;
            const target = amazonNormalizeSkuKey(sku);
            const matchRow = function(d) {
                if (!d || d.is_parent_summary) return false;
                return amazonNormalizeSkuKey(d['(Child) sku'] || d.SKU || d.sku) === target;
            };
            if (typeof table !== 'undefined' && table && table.getRows) {
                try {
                    const rows = table.getRows('all') || table.getRows() || [];
                    for (let i = 0; i < rows.length; i++) {
                        const d = rows[i].getData();
                        if (matchRow(d)) return d;
                    }
                } catch (e) {
                    const rows = table.getRows() || [];
                    for (let i = 0; i < rows.length; i++) {
                        const d = rows[i].getData();
                        if (matchRow(d)) return d;
                    }
                }
            }
            if (Array.isArray(allTableData)) {
                for (let i = 0; i < allTableData.length; i++) {
                    if (matchRow(allTableData[i])) return allTableData[i];
                }
            }
            return null;
        }

        /** GROI% at a given SP — same as Sroi / GROI% with SPRICE: ((SP×0.80 − ship − lp) / lp) × 100 */
        function amazonComputeGroiAtSp(sp, rowData) {
            if (!rowData) return null;
            const sprice = parseFloat(sp);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(sprice) || sprice <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            return ((sprice * 0.80 - ship - lp) / lp) * 100;
        }

        /** NROI% at a given SP — same as NROI badge / SNROI: (gross − ad spend) / LP × 100 */
        function amazonComputeNroiAtSp(sp, rowData) {
            if (!rowData) return null;
            const sprice = parseFloat(sp);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(sprice) || sprice <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
            const adsFrac = (parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0) / 100;
            const grossPft = (sprice * margin) - ship - lp;
            const adSpend = sprice * adsFrac;
            return ((grossPft - adSpend) / lp) * 100;
        }

        function amazonModalNroiColoredHtml(fieldVal) {
            if (window.MetricPctColors) {
                const html = MetricPctColors.htmlFor('nroi', fieldVal, { decimals: 0, empty: '' });
                return html || '<span class="text-muted">—</span>';
            }
            const p = parseFloat(fieldVal);
            if (!isFinite(p)) return '<span class="text-muted">—</span>';
            return '<span style="color:#dc3545;font-weight:600;">' + Math.round(p) + '%</span>';
        }

        function refreshLmpModalSpMetrics() {
            const sp = parseFloat($('#lmpModalSpInput').val());
            const row = currentLmpData.rowData;
            const groi = amazonComputeGroiAtSp(sp, row);
            const nroi = amazonComputeNroiAtSp(sp, row);
            $('#lmpModalGroiPct').html(groi === null ? '<span class="text-muted">—</span>' : amazonModalGroiColoredHtml(groi));
            $('#lmpModalNroiPct').html(nroi === null ? '<span class="text-muted">—</span>' : amazonModalNroiColoredHtml(nroi));
            const spText = (isFinite(sp) && sp > 0) ? ('$' + sp.toFixed(2)) : '—';
            const groiHtml = groi === null ? '<span class="text-muted">—</span>' : amazonModalGroiColoredHtml(groi);
            const nroiHtml = nroi === null ? '<span class="text-muted">—</span>' : amazonModalNroiColoredHtml(nroi);
            $('.lmp-sp-cell').text(spText === '—' ? '—' : spText);
            $('.lmp-groi-cell').html(groiHtml);
            $('.lmp-nroi-cell').html(nroiHtml);
        }

        function initLmpModalSpFromSku(sku) {
            const row = getAmazonTabulatorRowDataBySku(sku);
            currentLmpData.rowData = row;
            // SP = Standard Price only when previously filled manually (not Amazon price / SPRICE)
            let sp = null;
            if (row) {
                const std = parseFloat(row.STANDARD_PRICE);
                if (isFinite(std) && std > 0) sp = std;
            }
            $('#lmpModalSpInput').val(sp != null ? sp.toFixed(2) : '');
            refreshLmpModalSpMetrics();
        }

        /** Apply STANDARD_PRICE to a SKU row + all Sku Link LMP siblings in the grid */
        function applyStandardPriceToLinkedRows(sku, std, appliedSkus) {
            if (typeof table === 'undefined' || !table) return null;
            const target = String(sku || '').trim().toUpperCase();
            const appliedSet = new Set(
                (Array.isArray(appliedSkus) ? appliedSkus : [])
                    .map(function(s) { return String(s || '').trim().toUpperCase(); })
                    .filter(Boolean)
            );
            if (target) appliedSet.add(target);

            let primaryRow = null;
            (table.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (!d || d.is_parent_summary) return;
                const rowSku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
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

        function saveLmpModalSpToGrid() {
            const sku = currentLmpData.sku;
            const sp = parseFloat($('#lmpModalSpInput').val());
            if (!sku || !isFinite(sp) || sp <= 0) return;
            if (typeof table === 'undefined' || !table) return;
            $.ajax({
                url: '/save-amazon-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    sprice: sp,
                    is_standard_price: 1
                },
                success: function(response) {
                    const std = response.data || sp;
                    const primary = applyStandardPriceToLinkedRows(sku, std, response.applied_skus);
                    if (primary) currentLmpData.rowData = primary.getData();
                    if (typeof amzScheduleRuleSpriceSync === 'function') {
                        amzScheduleRuleSpriceSync({ force: true, delay: 200 });
                    }
                    const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                    if (typeof showToast === 'function') {
                        showToast('success', n > 1
                            ? ('Std Prc saved for ' + n + ' linked SKUs')
                            : 'Std Prc saved');
                    }
                },
                error: function() {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Failed to save Std Prc');
                    }
                }
            });
        }

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
            return String(rowData?.['(Child) sku'] || rowData?.sku || '').trim();
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
            if (row.is_parent_summary) {
                return '';
            }

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
            if (row.is_parent_summary) {
                return '';
            }
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

        $(document).ready(function() {
            // Dil vs PRMT / CVR vs CPN — same rules store as /pricing-errors-fix
            if (typeof initAmazonPefPromoUi === 'function') {
                initAmazonPefPromoUi();
            }
            if (typeof ensureAmzKpiDots === 'function') ensureAmzKpiDots();
            if (typeof loadAmzBadgePrevDay === 'function') loadAmzBadgePrevDay();

            // Sold filter badge click handlers (toggle: click again returns to "show all")
            // Keeps #sold-filter dropdown as the single source of truth.
            $('.sold-filter-badge').on('click', function(e) {
                if ($(e.target).closest('.summary-trend-dot').length) return;
                const filter = $(this).data('filter');
                // "Sold >0" badge → 'sold', "0 Sold" badge → 'zero'
                const targetVal = (filter === 'zero') ? 'zero' : 'sold';
                const current = $('#sold-filter').val();
                $('#sold-filter').val(current === targetVal ? 'all' : targetVal);
                applyFilters();
            });

            $('#amazon-blue-triangle-badge').on('click', function() {
                blueTriangleFilterActive = !blueTriangleFilterActive;
                if (blueTriangleFilterActive) {
                    lmpMissingFilterActive = false;
                    priceGtLmpFilterActive = false;
                    priceLt80LmpFilterActive = false;
                }
                applyFilters();
                syncAmazonBlueTriangleBadgeState();
            });
            if (window.LmpMissingBadge) {
                LmpMissingBadge.bind({
                    badge: '#amazon-lmp-missing-badge',
                    getActive: function() { return lmpMissingFilterActive; },
                    onToggle: function(on) {
                        lmpMissingFilterActive = on;
                        if (on) blueTriangleFilterActive = false;
                        applyFilters();
                        syncAmazonBlueTriangleBadgeState();
                    }
                });
            }
            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.bind({
                    badge: '#amazon-price-gt-lmp-badge',
                    getActive: function() { return priceGtLmpFilterActive; },
                    onToggle: function(on) {
                        priceGtLmpFilterActive = on;
                        if (on) blueTriangleFilterActive = false;
                        applyFilters();
                        syncAmazonBlueTriangleBadgeState();
                    }
                });
            }
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.bind({
                    badge: '#amazon-price-lt80-lmp-badge',
                    getActive: function() { return priceLt80LmpFilterActive; },
                    onToggle: function(on) {
                        priceLt80LmpFilterActive = on;
                        if (on) blueTriangleFilterActive = false;
                        applyFilters();
                        syncAmazonBlueTriangleBadgeState();
                    }
                });
            }

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() {
                const type = $(this).val();
                const $input = $('#discount-percentage-input');
                
                if (type === 'percentage') {
                    $input.attr('placeholder', 'Enter percentage');
                    $input.attr('max', '100');
                } else {
                    $input.attr('placeholder', 'Enter value');
                    $input.removeAttr('max');
                }
            });

            // Price % (Decrease / Increase / Same Price) — uses leftmost row_select column
            function exitPricePctMode() {
                decreaseModeActive = false;
                increaseModeActive = false;
                samePriceModeActive = false;
                $('#discount-input-container').hide();
                $('#price-pct-btn').removeClass('btn-danger btn-warning btn-success btn-info').addClass('btn-primary')
                    .html('<i class="fas fa-percent"></i> Prc Mode');
                $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply');
                $('#discount-type-select-wrap').show();
                $('#discount-input-label').text('By how much:');
                $('#discount-percentage-input')
                    .attr('placeholder', 'e.g. 10 or 2.50')
                    .attr('title', 'Enter % or $ amount to decrease/increase price');
                updateSelectedCount();
            }

            function setPricePctMode(mode) {
                if (mode === 'cancel') {
                    exitPricePctMode();
                    return;
                }

                decreaseModeActive = (mode === 'decrease');
                increaseModeActive = (mode === 'increase');
                samePriceModeActive  = (mode === 'same');
                $('#discount-input-container').show();
                $('#discount-percentage-input').val('');

                if (mode === 'decrease') {
                    $('#discount-type-select-wrap').show();
                    $('#discount-input-label').text('By how much:');
                    $('#discount-percentage-input')
                        .attr('placeholder', 'e.g. 10 or 2.50')
                        .attr('title', 'Enter % or $ amount to decrease price');
                    $('#price-pct-btn').removeClass('btn-primary btn-success btn-info').addClass('btn-warning')
                        .html('<i class="fas fa-minus-circle"></i> Decrease');
                    $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply Decrease');
                } else if (mode === 'increase') {
                    $('#discount-type-select-wrap').show();
                    $('#discount-input-label').text('By how much:');
                    $('#discount-percentage-input')
                        .attr('placeholder', 'e.g. 10 or 2.50')
                        .attr('title', 'Enter % or $ amount to increase price');
                    $('#price-pct-btn').removeClass('btn-primary btn-warning btn-info').addClass('btn-success')
                        .html('<i class="fas fa-plus-circle"></i> Increase');
                    $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply Increase');
                } else if (mode === 'same') {
                    $('#discount-type-select-wrap').hide();
                    $('#discount-input-label').text('Same Price ($):');
                    $('#discount-percentage-input')
                        .attr('placeholder', 'Enter price (e.g. 19.99)')
                        .attr('title', 'This single price will be applied to every selected SKU');
                    $('#price-pct-btn').removeClass('btn-primary btn-warning btn-success').addClass('btn-info')
                        .html('<i class="fas fa-equals"></i> Same Price');
                    $('#apply-discount-btn').html('<i class="fas fa-check"></i> Apply Same Price');
                }
                updateSelectedCount();
            }

            $(document).on('click', '#price-pct-dropdown a[data-mode]', function(e) {
                e.preventDefault();
                const mode = $(this).data('mode');
                setPricePctMode(mode);
            });

            // Single updateSelectedCount — bulk badge + Prc Mode label (merged select column)
            function updateSelectedCount() {
                const selectedCount = selectedRows.size;
                if (selectedCount > 0) {
                    $('#selected-rows-count').text(selectedCount + ' selected').show();
                    $('#clear-selection-btn').show();
                    $('#bulk-actions-container').show();
                } else {
                    $('#selected-rows-count').hide();
                    $('#clear-selection-btn').hide();
                    $('#bulk-actions-container').hide();
                }
                if (decreaseModeActive || increaseModeActive || samePriceModeActive) {
                    $('#discount-input-container').show();
                }
                $('#selected-skus-count').text(
                    selectedCount > 0
                        ? '(' + selectedCount + ' SKU' + (selectedCount > 1 ? 's' : '') + ' selected)'
                        : '(select SKUs in table)'
                );
            }

            // Clear SPRICE button
            $('#clear-sprice-btn').on('click', function() {
                clearSpriceForSelected();
            });

            // Clear SPRICE for selected SKUs
            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select SKUs first');
                    return;
                }

                if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                    return;
                }

                let clearedCount = 0;
                const updates = [];

                // Iterate rows and clear SPRICE where selected
                table.getRows().forEach(row => {
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        // Clear in table
                        row.update({
                            SPRICE: 0,
                            SGPFT: 0,
                            'Spft%': 0,
                            SROI: 0,
                            SGROI: 0,
                            SPRICE_STATUS: null,
                            has_custom_sprice: false
                        });

                        updates.push({ sku: sku, sprice: 0 });
                        clearedCount++;
                    }
                });

                if (updates.length > 0) {
                    // Send to server to persist
                    $.ajax({
                        url: '/amazon-clear-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: { updates: updates },
                        success: function(response) {
                            showToast('success', `SPRICE cleared for ${clearedCount} SKU(s)`);
                        },
                        error: function(xhr) {
                            console.error('Failed to clear SPRICE:', xhr);
                            showToast('error', 'Failed to clear SPRICE data');
                        }
                    });
                } else {
                    showToast('warning', 'No SPRICE values to clear for selected SKUs');
                }
            }

            // Helper: round to retail (.99 endings)
            function roundToRetailPrice(price) {
                if (price < 20.99) {
                    return +price.toFixed(2);
                }
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.01).toFixed(2);
            }
            // Helper: round to retail (.49 endings) — use when .99 would match current price so S PRC stays visible
            function roundToRetailPrice49(price) {
                if (price < 20.99) {
                    return +price.toFixed(2);
                }
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.51).toFixed(2);
            }

            /** Same as eBay: persist SPRICE = 0, then insert the rule price. */
            function amazonPersistClearThenSave(sku, fill, row) {
                if (row && typeof row.update === 'function') {
                    row.update({ SPRICE: 0, has_custom_sprice: false });
                    try { row.reformat(); } catch (e) { /* ignore */ }
                }
                const token = $('meta[name="csrf-token"]').attr('content');
                const deferred = $.Deferred();
                const wipe = $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token },
                    data: { sku: sku, sprice: 0, _token: token }
                });
                wipe.always(function() {
                    $.ajax({
                        url: '/save-amazon-sprice',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token },
                        data: { sku: sku, sprice: fill, _token: token }
                    }).done(function(res) {
                        deferred.resolve(res);
                    }).fail(function(xhr) {
                        deferred.reject(xhr);
                    });
                });
                return deferred.promise();
            }
            window.amazonPersistClearThenSave = amazonPersistClearThenSave;

            // Apply Discount/Increase/Same-Price Button
            $('#apply-discount-btn').on('click', function() {
                const rawInput = $('#discount-percentage-input').val();
                const inputValue = parseFloat(String(rawInput).replace(',', '.'));
                
                if (rawInput === '' || rawInput == null) {
                    showToast('error', samePriceModeActive ? 'Please enter a price' : 'Please enter a value (% or $)');
                    return;
                }
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('error', 'Please enter a valid positive number');
                    return;
                }
                
                const discountType = $('#discount-type-select').val();
                if (!samePriceModeActive && discountType === 'percentage' && inputValue > 100) {
                    showToast('error', 'Percentage cannot exceed 100');
                    return;
                }
                
                if (selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }
                
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('error', 'Please activate Decrease, Increase, or Same Price mode first');
                    return;
                }
                
                const mode = samePriceModeActive ? 'same' : (increaseModeActive ? 'increase' : 'decrease');
                let successCount = 0;
                let errorCount = 0;
                let totalToProcess = selectedSkus.size;
                
                // Disable button during processing
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
                // Process each selected SKU
                selectedSkus.forEach(sku => {
                    // Try to find the row - it might be on a different page
                    let row = null;
                    table.getRows().forEach(r => {
                        if (r.getData()['(Child) sku'] === sku) {
                            row = r;
                        }
                    });
                    
                    if (row) {
                        const rowData = row.getData();
                        const originalPrice = parseFloat(rowData.price) || 0;
                        
                        // Same Price mode applies even when the live price column is empty.
                        if (mode === 'same' || originalPrice > 0) {
                            let newPrice;

                            if (mode === 'same') {
                                // One fixed price for every selected row, regardless of current price.
                                newPrice = Math.max(0.01, inputValue);
                            } else if (discountType === 'percentage') {
                                // Treat as percentage
                                const decimal = inputValue / 100;
                                if (mode === 'decrease') {
                                    newPrice = originalPrice * (1 - decimal);
                                } else {
                                    newPrice = originalPrice * (1 + decimal);
                                }
                            } else {
                                // Treat as fixed value ($)
                                if (mode === 'decrease') {
                                    newPrice = Math.max(0.01, originalPrice - inputValue);
                                } else {
                                    newPrice = originalPrice + inputValue;
                                }
                            }

                            // Round to retail .99; when that would match current price, use .49 so S PRC doesn’t show blank
                            newPrice = roundToRetailPrice(newPrice);
                            if (mode !== 'same' && newPrice.toFixed(2) === originalPrice.toFixed(2)) {
                                newPrice = roundToRetailPrice49(newPrice);
                            }
                            const newPriceNum = amazonCapSpriceToLmp(row.getData(), parseFloat(newPrice.toFixed(2)));
                            
                            amazonPersistClearThenSave(sku, newPriceNum, row)
                                .done(function(response) {
                                    successCount++;
                                    
                                    // Update row so SPRICE column shows the new value (use number so formatter works)
                                    const updateData = {
                                        'SPRICE': newPriceNum,
                                        'has_custom_sprice': true,
                                        'SPRICE_STATUS': response.SPRICE_STATUS != null ? response.SPRICE_STATUS : null
                                    };
                                    if (response.sgpft_percent !== undefined) {
                                        updateData['SGPFT'] = response.sgpft_percent;
                                    }
                                    if (response.spft_percent !== undefined) {
                                        updateData['Spft%'] = response.spft_percent;
                                    }
                                    if (response.sroi_percent !== undefined) {
                                        updateData['SROI'] = response.sroi_percent;
                                    }
                                    if (response.sgroi_percent !== undefined) {
                                        updateData['SGROI'] = response.sgroi_percent;
                                    }
                                    
                                    row.update(updateData);
                                    row.reformat();
                                    
                                    // Check if all requests are complete
                                    if (successCount + errorCount === totalToProcess) {
                                        const actionText = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Discount');
                                        $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                        if (errorCount === 0) {
                                            showToast('success', `${actionText} applied successfully to ${successCount} SKU${successCount > 1 ? 's' : ''}`);
                                        } else {
                                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                                        }
                                    }
                                })
                                .fail(function() {
                                    errorCount++;
                                    if (successCount + errorCount === totalToProcess) {
                                        const actionText = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Discount');
                                        $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                        showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                                    }
                                });
                        } else {
                            errorCount++;
                            if (successCount + errorCount === totalToProcess) {
                                const actionText = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Discount');
                                $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                                showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                            }
                        }
                    } else {
                        errorCount++;
                        if (successCount + errorCount === totalToProcess) {
                            const actionText = mode === 'same' ? 'Same Price' : (mode === 'increase' ? 'Increase' : 'Discount');
                            $('#apply-discount-btn').prop('disabled', false).html(`<i class="fas fa-check"></i> Apply ${actionText}`);
                            showToast('error', `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`);
                        }
                    }
                });
            });

            // Allow Enter key to apply discount
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    $('#apply-discount-btn').click();
                }
            });

            /** CVR slab/trend helpers for toolbar filters (not tied to bulk SPRICE rules). */
            function amazonCvrIsZero(cvr) {
                const v = parseFloat(cvr);
                return !isFinite(v) || Math.abs(v) < 0.005;
            }

            function amazonRowCvrL30(rd) {
                if (!rd) return 0;
                const aL30 = parseFloat(rd['A_L30']) || 0;
                const sess30 = parseFloat(rd['Sess30']) || 0;
                if (sess30 <= 0) return 0;
                return (aL30 / sess30) * 100;
            }

            function amazonRowCvrL60(rd) {
                if (!rd) return 0;
                const aL60 = parseFloat(rd['units_ordered_l60']) || 0;
                const sess60 = parseFloat(rd['sessions_l60']) || 0;
                if (sess60 <= 0) return 0;
                return (aL60 / sess60) * 100;
            }

            function amazonCvrTrend(rd, tol) {
                const cvrL30 = amazonRowCvrL30(rd);
                const cvrL60 = amazonRowCvrL60(rd);
                const t = (tol != null && isFinite(tol)) ? tol : 0.1;
                if (cvrL30 > cvrL60 + t) return 'up';
                if (cvrL30 < cvrL60 - t) return 'down';
                return 'equal';
            }

            function amazonCvrSlab(cvr, low, mid, high) {
                const v = parseFloat(cvr) || 0;
                const pinkAfter = high + 0.01;
                if (v <= low) return 'red';
                if (v <= mid) return 'blue';
                if (v <= pinkAfter) return 'green';
                return 'pink';
            }

            /*
             * Target ROI% bulk apply
             * -----------------------
             * Back-solves S PRC so the Sroi column (gross, same formula as GROI% with SPRICE)
             * equals Target ROI%. Does NOT target SNROI (ads-adjusted).
             *     Sroi = ((sprice × 0.80 − ship − lp) / lp) × 100
             *  -> sprice = (LP × (1 + Target/100) + Ship) / 0.80
             * Uses the same hard-coded 0.80 take-home as amazonComputeSroi / saveSpriceToDatabase.
             */
            $('#apply-target-roi-btn').on('click', function() {
                const $btn = $(this);
                const rawInput = $('#target-roi-input').val();
                const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    showToast('error', 'Please enter a Target ROI%');
                    return;
                }
                if (!isFinite(targetRoiPct)) {
                    showToast('error', 'Target ROI% must be a number');
                    return;
                }

                const effectiveSelected = selectedRows;

                if (effectiveSelected.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }

                // Target ROI% → Sroi (gross), not SNROI:
                //   ((sprice × 0.80 − ship − lp) / lp) × 100 = Target
                //   -> sprice = (lp × (1 + Target/100) + ship) / 0.80
                const margin = 0.80;
                const roiMultiplier = 1 + (targetRoiPct / 100);

                const rowsToProcess = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!effectiveSelected.has(sku) || rd.is_parent_summary) return;
                    const lp = parseFloat(rd.LP_productmaster) || 0;
                    if (lp <= 0) return; // skip rows without a usable cost
                    const ship = parseFloat(rd.Ship_productmaster) || 0;
                    const candidate = (lp * roiMultiplier + ship) / margin;
                    const sprice = +candidate.toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    showToast('warning', 'No selected rows have a usable LP > 0');
                    return;
                }

                const confirmMsg = `Compute & save S PRC for ${rowsToProcess.length} selected SKU(s) so SGROI = ${targetRoiPct}%?\n\n(sprice = (LP \u00d7 (1 + Target/100) + Ship) / 0.80)`;
                if (!confirm(confirmMsg)) {
                    return;
                }

                let successCount = 0;
                let errorCount = 0;
                const total = rowsToProcess.length;

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                rowsToProcess.forEach(function(item) {
                    amazonPersistClearThenSave(item.sku, item.sprice, item.row)
                        .done(function(response) {
                            successCount++;
                            const updateData = {
                                'SPRICE': response.data || item.sprice,
                                'has_custom_sprice': true,
                                'SPRICE_STATUS': response.SPRICE_STATUS != null ? response.SPRICE_STATUS : null
                            };
                            if (response.sgpft_percent !== undefined) updateData['SGPFT'] = response.sgpft_percent;
                            if (response.spft_percent !== undefined) updateData['Spft%'] = response.spft_percent;
                            if (response.sroi_percent !== undefined) updateData['SROI'] = response.sroi_percent;
                            if (response.sgroi_percent !== undefined) updateData['SGROI'] = response.sgroi_percent;
                            item.row.update(updateData);
                            item.row.reformat();
                        })
                        .fail(function() {
                            errorCount++;
                        })
                        .always(function() {
                            if (successCount + errorCount === total) {
                                $btn.prop('disabled', false).html('<i class="fas fa-calculator"></i>');
                                if (errorCount === 0) {
                                    showToast('success', `S PRC saved for ${successCount} SKU(s) — SGROI = ${targetRoiPct}%`);
                                } else {
                                    showToast('error', `Saved ${successCount} of ${total} (${errorCount} failed)`);
                                }

                                // Clear shared selection so the next batch starts fresh.
                                selectedRows.clear();
                                $('.row-select-checkbox').prop('checked', false);
                                $('#select-all-rows').prop('checked', false).prop('indeterminate', false);
                                if (typeof updateSelectedCount === 'function') {
                                    updateSelectedCount();
                                }
                            }
                        });
                });
            });

            // Enter inside the Target ROI% input triggers Apply S PRC
            $('#target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });

            /*
             * Target GPFT% bulk apply
             * ------------------------
             * Back-solves S PRC so the S GPFT (Sgpft) column equals Target GPFT%.
             * Does NOT target SNPFT (ads-adjusted). Same hard-coded 0.80 as saveSpriceToDatabase:
             *     SGPFT = ((sprice × 0.80 − ship − lp) / sprice) × 100
             *  -> sprice = (lp + ship) / (0.80 − GPFT%/100)
             * Target GPFT% must be strictly < 80.
             */
            $('#apply-target-gpft-btn').on('click', function() {
                const $btn = $(this);
                const rawInput = $('#target-gpft-input').val();
                const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    showToast('error', 'Please enter a Target GPFT%');
                    return;
                }
                if (!isFinite(targetGpftPct)) {
                    showToast('error', 'Target GPFT% must be a number');
                    return;
                }

                const effectiveSelected = selectedRows;

                if (effectiveSelected.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }

                const margin = 0.80;
                const targetFraction = targetGpftPct / 100;
                const denom = margin - targetFraction;
                if (denom <= 0) {
                    showToast('error', `Target GPFT% ${targetGpftPct}% is too high \u2014 must be less than 80%.`);
                    return;
                }

                const rowsToProcess = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!effectiveSelected.has(sku) || rd.is_parent_summary) return;
                    const lp = parseFloat(rd.LP_productmaster) || 0;
                    if (lp <= 0) return; // need a cost to back-solve
                    const ship = parseFloat(rd.Ship_productmaster) || 0;
                    const candidate = (lp + ship) / denom;
                    const sprice = +candidate.toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    showToast('warning', 'No selected rows have a usable LP > 0');
                    return;
                }

                const confirmMsg = `Compute & save S PRC for ${rowsToProcess.length} selected SKU(s) so S GPFT = ${targetGpftPct}%?\n\n(sprice = (LP + Ship) / (0.80 \u2212 ${targetGpftPct}%/100))`;
                if (!confirm(confirmMsg)) {
                    return;
                }

                let successCount = 0;
                let errorCount = 0;
                const total = rowsToProcess.length;

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                rowsToProcess.forEach(function(item) {
                    amazonPersistClearThenSave(item.sku, item.sprice, item.row)
                        .done(function(response) {
                            successCount++;
                            const updateData = {
                                'SPRICE': response.data || item.sprice,
                                'has_custom_sprice': true,
                                'SPRICE_STATUS': response.SPRICE_STATUS != null ? response.SPRICE_STATUS : null
                            };
                            if (response.sgpft_percent !== undefined) updateData['SGPFT'] = response.sgpft_percent;
                            if (response.spft_percent !== undefined) updateData['Spft%'] = response.spft_percent;
                            if (response.sroi_percent !== undefined) updateData['SROI'] = response.sroi_percent;
                            if (response.sgroi_percent !== undefined) updateData['SGROI'] = response.sgroi_percent;
                            item.row.update(updateData);
                            item.row.reformat();
                        })
                        .fail(function() {
                            errorCount++;
                        })
                        .always(function() {
                            if (successCount + errorCount === total) {
                                $btn.prop('disabled', false).html('<i class="fas fa-calculator"></i>');
                                if (errorCount === 0) {
                                    showToast('success', `S PRC saved for ${successCount} SKU(s) — S GPFT = ${targetGpftPct}%`);
                                } else {
                                    showToast('error', `Saved ${successCount} of ${total} (${errorCount} failed)`);
                                }

                                // Clear shared selection so the next batch starts fresh.
                                selectedRows.clear();
                                $('.row-select-checkbox').prop('checked', false);
                                $('#select-all-rows').prop('checked', false).prop('indeterminate', false);
                                if (typeof updateSelectedCount === 'function') {
                                    updateSelectedCount();
                                }
                            }
                        });
                });
            });

            // Enter inside the Target GPFT% input triggers Apply S PRC
            $('#target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Parent pricing modal: push SPRICE to Amazon only
            $(document).on('click', '.parent-pricing-modal-apply-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const sku = $btn.attr('data-sku');
                const price = parseFloat($btn.attr('data-price'));
                if (!sku || !price || price <= 0 || isNaN(price)) {
                    showToast('error', 'Invalid SKU or price');
                    return;
                }
                const modalRow = (typeof table !== 'undefined' && table)
                    ? table.getRows().find(function(r) {
                        return String((r.getData()['(Child) sku'] || '')).trim() === String(sku).trim();
                    })
                    : null;
                if (modalRow && amazonListingPriceEqualsSprice(modalRow.getData(), price)) {
                    showToast('success', sku + ': Price already equals S PRC — left unchanged');
                    return;
                }
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-clock fa-spin" style="color: black;"></i>');
                const asinModal = ($btn.attr('data-asin') || '').trim();
                
                // Push to Amazon only (pass push_shopify: false)
                $.ajax({
                    url: '/apply-amazon-price',
                    method: 'POST',
                    timeout: 120000,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        price: price,
                        asin: asinModal || null,
                        push_shopify: false,
                        update_amazon_min_price: true
                    },
                    success: function(response) {
                        if (response.errors && response.errors.length > 0) {
                            const errorMsg = response.errors[0].message || 'Unknown error';
                            showToast('error', `Amz push failed: ${errorMsg}`);
                            const pk = $('#parentPricingBreakdownModal').data('amazonParentKey');
                            if (pk) {
                                showParentPricingBreakdownModal(pk);
                            }
                            return;
                        }
                        
                        if (table) {
                            const tabRow = table.getRows().find(function(r) {
                                return (r.getData()['(Child) sku'] || '') === sku;
                            });
                            if (tabRow) {
                                const rowData = tabRow.getData();
                                rowData.SPRICE_STATUS = 'pushed';
                                tabRow.update(rowData);
                            }
                        }
                        const minPush = response.min_price_push;
                        if (minPush && minPush.ok === false) {
                            const minErr = (minPush.errors && minPush.errors[0] && minPush.errors[0].message) || 'Unknown error';
                            showToast('warning', `Amz: Price $${Number(price).toFixed(2)} pushed for SKU: ${sku}, but min price failed: ${minErr}`);
                        } else {
                            showToast('success', `Amz: Price $${Number(price).toFixed(2)} pushed for SKU: ${sku}`);
                        }
                        const pk = $('#parentPricingBreakdownModal').data('amazonParentKey');
                        if (pk) {
                            showParentPricingBreakdownModal(pk);
                        }
                    },
                    error: function(xhr) {
                        if (table) {
                            const tabRow = table.getRows().find(function(r) {
                                return (r.getData()['(Child) sku'] || '') === sku;
                            });
                            if (tabRow) {
                                const rowData = tabRow.getData();
                                rowData.SPRICE_STATUS = 'error';
                                tabRow.update(rowData);
                            }
                        }
                        const errorMsg = xhr.responseJSON?.errors?.[0]?.message || 'Unknown error';
                        showToast('error', `Amz push failed: ${errorMsg}`);
                        const pk = $('#parentPricingBreakdownModal').data('amazonParentKey');
                        if (pk) {
                            showParentPricingBreakdownModal(pk);
                        }
                    }
                });
            });

            $(document).on('dblclick', '.parent-pricing-modal-apply-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const sku = $btn.attr('data-sku');
                const currentStatus = $btn.attr('data-status') || '';
                if (currentStatus === 'pushed') {
                    $.ajax({
                        url: '/update-sprice-status',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: { sku: sku, status: 'applied' },
                        success: function() {
                            if (table) {
                                const tabRow = table.getRows().find(function(r) {
                                    return (r.getData()['(Child) sku'] || '') === sku;
                                });
                                if (tabRow) {
                                    const rowData = tabRow.getData();
                                    rowData.SPRICE_STATUS = 'applied';
                                    tabRow.update(rowData);
                                }
                            }
                            showToast('success', 'Status updated to Applied');
                            const pk = $('#parentPricingBreakdownModal').data('amazonParentKey');
                            if (pk) {
                                showParentPricingBreakdownModal(pk);
                            }
                        },
                        error: function() {
                            showToast('error', 'Failed to update status');
                        }
                    });
                } else if (currentStatus === 'applied') {
                    showToast('info', 'Price is already marked as Applied');
                } else {
                    showToast('info', 'Please push the price first before marking as Applied');
                }
            });

            function saveParentModalSpriceFromInput($input) {
                if (!$input || !$input.length) return;
                const sku = $input.attr('data-sku');
                if (!sku || !table) return;
                let raw = String($input.val() || '').trim().replace(',', '.');
                const all = table.getData('all') || [];
                const rowData = all.find(function(x) {
                    return (x['(Child) sku'] || '') === sku;
                });
                if (!rowData) return;
                if (raw === '') {
                    const sp = parseFloat(rowData.SPRICE) || 0;
                    $input.val(sp > 0 ? sp.toFixed(2) : '');
                    return;
                }
                const num = parseFloat(raw);
                if (isNaN(num) || num <= 0) {
                    showToast('error', 'Enter a valid price greater than 0');
                    const sp = parseFloat(rowData.SPRICE) || 0;
                    $input.val(sp > 0 ? sp.toFixed(2) : '');
                    return;
                }
                const current = parseFloat(rowData.SPRICE) || 0;
                if (Math.abs(current - num) < 0.0001) return;

                $input.prop('disabled', true);
                const tabRow = table.getRows().find(function(r) {
                    return (r.getData()['(Child) sku'] || '') === sku;
                });
                amazonPersistClearThenSave(sku, num, tabRow || null)
                    .done(function(response) {
                        showToast('success', 'SPRICE updated successfully');
                        if (tabRow) {
                            const u = {
                                SPRICE: num,
                                has_custom_sprice: true
                            };
                            if (response.sgpft_percent !== undefined) {
                                u.SGPFT = response.sgpft_percent;
                            }
                            if (response.spft_percent !== undefined) {
                                u['Spft%'] = response.spft_percent;
                            }
                            if (response.sroi_percent !== undefined) {
                                u.SROI = response.sroi_percent;
                            }
                            if (response.sgroi_percent !== undefined) {
                                u.SGROI = response.sgroi_percent;
                            }
                            if (response.SPRICE_STATUS != null) {
                                u.SPRICE_STATUS = response.SPRICE_STATUS;
                            }
                            tabRow.update(u);
                        }
                        const pk = $('#parentPricingBreakdownModal').data('amazonParentKey');
                        if (pk) {
                            showParentPricingBreakdownModal(pk);
                        }
                        $input.prop('disabled', false);
                    })
                    .fail(function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to update SPRICE';
                        showToast('error', msg);
                        const sp = parseFloat(rowData.SPRICE) || 0;
                        $input.val(sp > 0 ? sp.toFixed(2) : '');
                        $input.prop('disabled', false);
                    });
            }

            $(document).on('keydown', '.parent-modal-sprice-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });

            $(document).on('blur', '.parent-modal-sprice-input', function() {
                saveParentModalSpriceFromInput($(this));
            });

            $(document).on('click', '.parent-modal-sprice-input', function(e) {
                e.stopPropagation();
            });

            // SKU chart days filter
            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                const daysNum = parseInt(days, 10);
                const rangeLabel = daysNum === 0 ? 'Lifetime' : 'L' + daysNum;
                const metricLabels = { cvr: 'CVR%', views: 'View L30', inv: 'INV', inv_amz: 'INV AMZ', al30: 'A L30', ovl30: 'OV L30', sprice: 'S PRC', prmt: 'PRMT %', cpn: 'CPN %', push_prc: 'Push Prc' };
                const metricLabel = metricLabels[currentSkuChartMetric] || 'Price';
                $('#skuChartModalSuffix').text(metricLabel + ' (Rolling ' + rangeLabel + ')');
                if (currentSku) {
                    withChartJs(function() {
                        if (!skuMetricsChart) initSkuMetricsChart();
                        loadSkuMetricsData(currentSku, daysNum || 0);
                    });
                }
            });
            // ---- Image column hover preview ----
            let amzImagePreviewHideTimer = null;
            let amzImagePreviewEl = null;
            function amzRemoveImagePreview() {
                if (amzImagePreviewHideTimer) {
                    clearTimeout(amzImagePreviewHideTimer);
                    amzImagePreviewHideTimer = null;
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
                amzImagePreviewEl = null;
            }
            function amzCancelImagePreviewHide() {
                if (amzImagePreviewHideTimer) {
                    clearTimeout(amzImagePreviewHideTimer);
                    amzImagePreviewHideTimer = null;
                }
            }
            function amzScheduleImagePreviewHide() {
                amzCancelImagePreviewHide();
                amzImagePreviewHideTimer = setTimeout(amzRemoveImagePreview, 220);
            }
            function amzEnsureImagePreviewListeners(wrap) {
                if (wrap.dataset.amzPreviewListeners === '1') return;
                wrap.dataset.amzPreviewListeners = '1';
                wrap.addEventListener('mouseenter', amzCancelImagePreviewHide);
                wrap.addEventListener('mouseleave', amzScheduleImagePreviewHide);
            }
            function amzClampPreviewPosition(wrap, clientX, clientY) {
                const pad = 12;
                let left = clientX + pad;
                let top = clientY + pad;
                wrap.style.position = 'fixed';
                wrap.style.left = left + 'px';
                wrap.style.top = top + 'px';
                const rect = wrap.getBoundingClientRect();
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const m = 8;
                if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
                if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
                if (left < m) left = m;
                if (top < m) top = m;
                wrap.style.left = left + 'px';
                wrap.style.top = top + 'px';
            }
            function amzShowImagePreview(clientX, clientY, fullUrl) {
                if (!fullUrl) return;
                amzCancelImagePreviewHide();
                const existing = amzImagePreviewEl;
                if (existing && document.body.contains(existing)) {
                    const prevImg = existing.querySelector('img');
                    if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                        amzClampPreviewPosition(existing, clientX, clientY);
                        return;
                    }
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
                amzImagePreviewEl = null;
                const wrap = document.createElement('div');
                wrap.id = 'image-hover-preview';
                wrap.style.zIndex = '10050';
                wrap.style.pointerEvents = 'auto';
                wrap.style.border = '1px solid #ccc';
                wrap.style.background = '#fff';
                wrap.style.padding = '4px';
                wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
                wrap.style.borderRadius = '6px';
                const big = document.createElement('img');
                big.style.display = 'block';
                big.alt = '';
                // Size to 0.4× natural dimensions (capped so huge assets stay usable)
                big.onload = function() {
                    const nw = big.naturalWidth || 0;
                    const nh = big.naturalHeight || 0;
                    if (nw > 0 && nh > 0) {
                        const w = Math.max(1, Math.round(nw * 0.4));
                        const h = Math.max(1, Math.round(nh * 0.4));
                        big.style.width = w + 'px';
                        big.style.height = h + 'px';
                    }
                    amzClampPreviewPosition(wrap, clientX, clientY);
                };
                big.src = fullUrl;
                wrap.appendChild(big);
                amzEnsureImagePreviewListeners(wrap);
                document.body.appendChild(wrap);
                amzImagePreviewEl = wrap;
                amzClampPreviewPosition(wrap, clientX, clientY);
            }

            table = new Tabulator("#amazon-table", {
                ajaxURL: "/amazon-data-json",
                // POST so FastPanel/nginx GET disk-cache cannot serve a stale LMP
                ajaxConfig: {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') || '',
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    }
                },
                ajaxParams: function() {
                    return { _ts: Date.now() };
                },
                ajaxSorting: false,
                headerSort: true,
                headerSortElement: false,
                layout: "fitDataStretch",
                movableColumns: true,
                rowHeight: 36,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500, 1000, true], // true = All
                columnCalcs: "both",
                initialSort: [{
                    column: "Parent",
                    dir: "asc"
                }],
                ajaxResponse: function(url, params, response) {
                    var payload = response.data || response;
                    if (Array.isArray(payload)) {
                        payload.forEach(function(row) { amazonApplyL1FromEntries(row); });
                        allTableData = payload;
                        if (window.ParentExpand) ParentExpand.captureDataset(payload);
                    }
                    return payload;
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    const el = row.getElement();
                    if (data.is_parent_summary === true) {
                        el.style.backgroundColor = "#fffef2";
                        el.style.fontWeight = "bold";
                        el.style.minHeight = "48px";
                        el.classList.add("parent-row");
                    } else {
                        el.style.backgroundColor = "";
                        el.style.fontWeight = "";
                        el.style.minHeight = "";
                        el.classList.remove("parent-row");
                    }
                },
                columns: [
                    // Row selection checkbox column
                    {
                        title: "<div style='transform: rotate(0deg) !important; display: flex; justify-content: center; align-items: center;'><input type='checkbox' id='select-all-rows' title='Select / clear all SKUs on this page' style='transform: rotate(0deg) !important; width: 16px; height: 16px; cursor: pointer;'></div>",
                        field: "row_select",
                        hozAlign: "center",
                        headerSort: false,
                        headerVertical: false,
                        width: 40,
                        frozen: true,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sku = row['(Child) sku'] || '';
                            return "<input type='checkbox' class='row-select-checkbox' data-sku='" + sku + "' style='width: 16px; height: 16px; cursor: pointer;'>";
                        },
                        cellClick: function(e, cell) {
                            // Prevent row click event
                            e.stopPropagation();
                        }
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 120,
                        visible: true,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var val = row['Parent'] != null ? row['Parent'] : (row['parent'] != null ? row['parent'] : '');
                            var s = (val != null && val !== '') ? String(val).trim() : '';
                            if (!s && row['(Child) sku']) {
                                var sku = String(row['(Child) sku']).trim();
                                if (sku.toUpperCase().indexOf('PARENT ') === 0) s = sku.slice(7).trim();
                            }
                            return s || '—';
                        }
                    },
                    ParentExpand.columnDef(),

                    {
                        title: "Image",
                        field: "image_path",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const imagePath = cell.getValue();
                            if (imagePath) {
                                const u = String(imagePath).replace(/"/g, '&quot;');
                                // no-img-hover: skip global layout preview (js/image-hover-preview.js) — local preview only
                                return `<img src="${u}" data-full="${u}" class="hover-thumb no-img-hover" data-no-img-hover style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; cursor: zoom-in;" />`;
                            }
                            return '';
                        },
                        cellMouseOver: function(e, cell) {
                            const img = cell.getElement().querySelector('.hover-thumb');
                            if (!img) return;
                            amzShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = amzImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            const img = cell.getElement().querySelector('.hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            amzClampPreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest('#image-hover-preview')) {
                                amzCancelImagePreviewHide();
                                return;
                            }
                            amzScheduleImagePreviewHide();
                        },
                        width: 80
                    },

                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        frozen: true,
                        width: 200,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Don't show copy button for parent rows
                            if (rowData.is_parent_summary) {
                                return `<span style="font-weight: bold;">${sku}</span>`;
                            }

                            const isListed = !rowData.is_missing_amazon;
                            const chartBtn = (sku && isListed) ? `<button class="btn btn-sm ms-1 view-sku-chart" data-sku="${escAttr(sku)}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>` : '';
                            return `<div style="display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; min-width: 0; max-width: 100%;">
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;" title="${escAttr(sku)}">${sku}</span>
                                <button class="btn btn-sm btn-link copy-sku-btn p-0 flex-shrink-0" data-sku="${escAttr(sku)}" title="Copy SKU">
                                    <i class="fas fa-copy"></i>
                                </button>
                                ${chartBtn}
                            </div>`;
                        },
                     
                    },
                    {
                        title: "CVR L30",
                        field: "CVR_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const aL30 = parseFloat(row['A_L30']) || 0;
                            const sess30 = parseFloat(row['Sess30']) || 0;
                            const aL60 = parseFloat(row['units_ordered_l60']) || 0;
                            const sess60 = parseFloat(row['sessions_l60']) || 0;
                            const cvr = sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                            const sess45 = (sess30 + sess60) / 2;
                            const cvr45 = sess45 === 0 ? 0 : (((aL30 + aL60) / 2) / sess45) * 100;
                            let color = '#e83e8c';
                            if (cvr <= 4) color = '#a00211';
                            else if (cvr > 4 && cvr <= 7) color = '#ffc107';
                            else if (cvr > 7 && cvr <= 13) color = '#28a745';
                            const pctLabel = sess30 === 0 ? '0.0%' : (Math.round(cvr) + '%');
                            let arrowHtml = '';
                            if (!row.is_parent_summary) {
                                const tol = 0.1;
                                let arrowColor = '#ffc107';
                                let arrowIcon = 'fa-minus';
                                let tip = 'Same as CVR L45 ' + cvr45.toFixed(1) + '%';
                                if (cvr === 0 || cvr < cvr45 - tol) {
                                    arrowColor = '#a00211';
                                    arrowIcon = 'fa-arrow-down';
                                    tip = (cvr === 0 ? 'CVR L30 is 0 → Down' : 'Down vs CVR L45 ' + cvr45.toFixed(1) + '%');
                                } else if (cvr > cvr45 + tol) {
                                    arrowColor = '#28a745';
                                    arrowIcon = 'fa-arrow-up';
                                    tip = 'Up vs CVR L45 ' + cvr45.toFixed(1) + '%';
                                }
                                arrowHtml = ' <span title="' + escAttr(tip) + '" style="vertical-align:middle;">'
                                    + '<i class="fas ' + arrowIcon + '" style="color:' + arrowColor
                                    + ';font-size:12px;"></i></span>';
                            }
                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                                + '<span style="color:' + color + ';font-weight:600;">' + pctLabel + '</span>'
                                + arrowHtml + '</span>';
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcCVR = (row) => {
                                const aL30 = parseFloat(row['A_L30']) || 0;
                                const sess30 = parseFloat(row['Sess30']) || 0;
                                return sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                            };
                            return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                        },
                        width: 78
                    },
                    {
                        title: "Reviews",
                        field: "amz_avg_rating",
                        hozAlign: "center",
                        headerSort: true,
                        width: 85,
                        headerTooltip: "Avg rating + review count from Amz Ads API cron (amazon:collect-reviews). Falls back to SP-API Catalog when Ads Brand Posts is unavailable.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent_summary) return '';
                            const rating = row.amz_avg_rating;
                            const reviews = row.amz_review_count;
                            if (rating === null || rating === undefined || rating === '' || parseFloat(rating) <= 0) {
                                return '<span style="color: #6c757d;">-</span>';
                            }
                            const ratingVal = parseFloat(rating);
                            let ratingColor = '#a00211';
                            if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                            else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                            else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                            else if (ratingVal > 4.5) ratingColor = '#e83e8c';
                            const count = parseInt(reviews, 10) || 0;
                            const reviewColor = count < 4 ? '#a00211' : '#6c757d';
                            const src = row.amz_reviews_source ? String(row.amz_reviews_source) : 'amazon';
                            return `<span style="color: ${ratingColor}; font-weight: 600;" title="Source: ${escapeHtmlAttr(src)} · ${count.toLocaleString()} reviews">
                                    <i class="fa fa-star"></i> ${ratingVal.toFixed(1)}
                                    <span style="color: ${reviewColor};">(${count.toLocaleString()})</span>
                                </span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const ra = parseFloat(aRow.getData().amz_avg_rating) || 0;
                            const rb = parseFloat(bRow.getData().amz_avg_rating) || 0;
                            return ra - rb;
                        }
                    },
                    {
                        title: "Buyer Link",
                        field: "asin",
                        frozen: true,
                        width: 50,
                        hozAlign: "center",
                        visible: false,
                        headerTooltip: "Dynamic buyer link (same as /listing-amazon): https://www.amazon.com/dp/{asin}",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent_summary) return '';
                            const itemId = String(row.asin || '').trim();
                            if (!itemId) {
                                return '<span class="text-muted" title="No amazon ASIN">—</span>';
                            }
                            const href = 'https://www.amazon.com/dp/' + encodeURIComponent(itemId);
                            return `<a href="${escapeHtmlAttr(href)}" target="_blank" rel="noopener noreferrer"
                                title="Buyer link — Amz ASIN ${escapeHtmlAttr(itemId)}"
                                style="font-weight:600;color:#0d6efd;text-decoration:none;"
                                onclick="event.stopPropagation();">
                                <i class="fas fa-external-link-alt"></i>
                            </a>`;
                        },
                        headerSort: false
                    },
                    {
                        title: "Seller Link",
                        field: "seller_asin_link",
                        frozen: true,
                        width: 90,
                        hozAlign: "center",
                        visible: false,
                        headerTooltip: "Dynamic seller link (same as /listing-amazon): Seller Central inventory by ASIN",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent_summary) return '';
                            const itemId = String(row.asin || '').trim();
                            if (!itemId) {
                                return '<span class="text-muted" title="No amazon ASIN">—</span>';
                            }
                            const href = 'https://sellercentral.amazon.com/inventory/ref=xx_invmgr_dnav_xx?asin=' + encodeURIComponent(itemId);
                            return `<a href="${escapeHtmlAttr(href)}" target="_blank" rel="noopener noreferrer"
                                title="Seller Central inventory — Amz ASIN ${escapeHtmlAttr(itemId)}"
                                style="font-weight:600;color:#0d6efd;text-decoration:none;"
                                onclick="event.stopPropagation();">
                                <i class="fas fa-external-link-alt me-1"></i>Seller
                            </a>`;
                        },
                        headerSort: false
                    },

                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            const isListed = !row.is_missing_amazon;
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            const dotBtn = (sku && isListed) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${escAttr(sku)}" data-metric="inv" title="View INV chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6c757d;"></span></button>` : '';
                            return `${num} ${dotBtn}`.trim();
                        }
                    },

                    {
                        title: "INV AMZ",
                        field: "INV_AMZ",
                        hozAlign: "center",
                        width: 65,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'] || '';
                            const isListed = !rowData.is_missing_amazon;
                            const shopifyInv = parseFloat(rowData.INV) || 0;
                            let color = '';
                            const difference = Math.abs(value - shopifyInv);
                            const tol = shopifyInv * 0.03;
                            if (amazonInvWithinMapTolerance(shopifyInv, value)) color = '#28a745';
                            else if (difference <= Math.max(tol, 3)) color = '#ffc107';
                            else color = '#dc3545';
                            const dotBtn = (sku && isListed) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${escAttr(sku)}" data-metric="inv_amz" title="View INV AMZ chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #17a2b8;"></span></button>` : '';
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span> ${dotBtn}`.trim();
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            const isListed = !row.is_missing_amazon;
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            const dotBtn = (sku && isListed) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${escAttr(sku)}" data-metric="ovl30" title="View OV L30 chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #fd7e14;"></span></button>` : '';
                            return `${num} ${dotBtn}`.trim();
                        }
                    },



                    {
                        title: "Dil",
                        field: "E Dil%",
                        hozAlign: "center",
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const dilOf = function(row) {
                                const inv = parseFloat(row.INV) || 0;
                                const ovl30 = parseFloat(row['L30']) || 0;
                                return inv === 0 ? 0 : (ovl30 / inv) * 100;
                            };
                            return dilOf(aRow.getData()) - dilOf(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';

                            // Color logic from inc/dec page - getDilColor
                            if (dil < 16.66) color = '#a00211'; // red
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "A L30",
                        field: "A_L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const sku = row['(Child) sku'] || '';
                            const isListed = !row.is_missing_amazon;
                            const value = cell.getValue();
                            const num = Math.round(value || 0);
                            const dotBtn = (sku && isListed) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${escAttr(sku)}" data-metric="al30" title="View A L30 chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #e83e8c;"></span></button>` : '';
                            return `${num} ${dotBtn}`.trim();
                        }
                    },
                    {
                        title: "A DIL %",
                        field: "A DIL %",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const data = cell.getRow().getData();
                            const al30 = parseFloat(data.A_L30);
                            const inv = parseFloat(data.INV);
                            if (!isNaN(al30) && !isNaN(inv) && inv !== 0) {
                                const dilPercent = (al30 / inv) * 100;
                                let color = '';
                                // Color logic from DIL column
                                if (dilPercent < 16.66) color = '#a00211';
                                else if (dilPercent >= 16.66 && dilPercent < 25) color = '#ffc107';
                                else if (dilPercent >= 25 && dilPercent < 50) color = '#28a745';
                                else color = '#e83e8c';
                                return `<span style="color: ${color}; font-weight: 600;">${Math.round(dilPercent)}%</span>`;
                            }
                            return '<span style="color: #6c757d;">0%</span>';
                        }
                    },
                    {
                        title: "View L30",
                        field: "Sess30",
                        hozAlign: "center",
                        sorter: "number",
                        width: 55,
                        formatter: function(cell) {
                            const num = Math.round(cell.getValue() || 0);
                            return num.toLocaleString('en-US');
                        }
                    },

                    {
                        title: "View L7",
                        field: "Sess7",
                        hozAlign: "center",
                        sorter: "number",
                        width: 55,
                        formatter: function(cell) {
                            const num = Math.round(cell.getValue() || 0);
                            const formatted = num.toLocaleString('en-US');
                            if (num < 70) {
                                return `<span style="color: #a00211; font-weight: 600;">${formatted}</span>`;
                            }
                            return formatted;
                        }
                    },

                    {
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerSort: true,
                        sorter: "number",
                        headerTooltip: "Standard Price (Std Prc) — manual only (LMP modal / Std Prc editor). Blank unless filled when LMP cannot be determined. Dot vs Amz price.",
                        editor: "input",
                        width: 70,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) return '';
                            const value = cell.getValue();
                            const currentPrice = parseFloat(rowData.price) || 0;
                            const std = parseFloat(value) || 0;
                            // SP stays blank unless Standard Price was filled manually
                            if (!value || std <= 0) return '';
                            const sku = rowData['(Child) sku'] || '';
                            const dot = amazonSpriceChangeDotHtml(std, currentPrice, sku);
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + ('$' + std.toFixed(2)) + '</span>';
                        },
                        cellClick: function(e) {
                            if (e.target.closest('.view-sku-chart') || e.target.closest('.sprice-change-dot')) {
                                e.stopPropagation();
                                return false;
                            }
                        }
                    },

                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "center",
                        cellClick: function(e, cell) {
                            if (typeof $ === 'undefined') return;
                            const $btn = $(e.target).closest('.parent-pricing-eye-btn');
                            if ($btn.length) {
                                e.stopPropagation();
                                e.preventDefault();
                                const pk = $btn.attr('data-parent');
                                if (pk) {
                                    showParentPricingBreakdownModal(pk);
                                }
                            }
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();

                            if (rowData.is_parent_summary) {
                                const pk = amazonParentKeyFromRow(rowData);
                                if (!pk) return '';
                                return `<button type="button" class="btn btn-link p-0 parent-pricing-eye-btn" data-parent="${escAttr(pk)}" title="Child SKU pricing (incl. S PRC, push, SNPFT, SNROI)" style="color: #0dcaf0;"><i class="fas fa-eye" style="font-size: 16px;"></i></button>`;
                            }

                            const price = parseFloat(value || 0);
                            const lmpPrice = parseFloat(rowData.lmp_price || 0);
                            const lmpaPrice = parseFloat(rowData.price_lmpa || 0);
                            const isListed = !rowData.is_missing_amazon;

                            if (!isListed) return '';

                            if (price <= 0) {
                                const fallback = lmpPrice > 0 ? lmpPrice : (lmpaPrice > 0 ? lmpaPrice : 0);
                                if (fallback > 0) {
                                    return `<span style="color: #6c757d; font-style: italic; font-weight: 700;" title="Reference price (no Amz listing price)">$${fallback.toFixed(2)}</span>`;
                                }
                                return '';
                            }

                            const priceFormatted = '$' + price.toFixed(2);
                            const tri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(price, lmpPrice) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(price, lmpPrice) : '');
                            if (lmpPrice > 0 && price > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 700;">${priceFormatted}</span>${tri}`;
                            }
                            return `<span style="font-weight: 700;">${priceFormatted}</span>` + purpleTri;
                        },
                        sorter: "number",
                        width: 70
                    },

                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const av = (typeof amazonRowSprice === 'function')
                                ? (amazonRowSprice(aRow.getData()) || 0)
                                : (parseFloat(a) || 0);
                            const bv = (typeof amazonRowSprice === 'function')
                                ? (amazonRowSprice(bRow.getData()) || 0)
                                : (parseFloat(b) || 0);
                            return av - bv;
                        },
                        editable: false,
                        headerTooltip: "Read-only. Always the live rule price (0 Sold GROI, or Std − PRMT − CVR Disc − CVR UP/DN). Stored S PRC is overwritten to match. Red = reduced, Yellow = hold, Green = increase vs Amz price. S PRC ≥ LMP is capped at LMP and keeps a red triangle after push.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) return '';
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const currentPrice = parseFloat(rowData.price) || 0;
                            let sprice = amazonRowSprice(rowData);

                            if (!(sprice > 0)) return '';

                            const cap = window.SpriceLmpCap
                                ? SpriceLmpCap.apply(rowData, sprice, lmpWithShipping)
                                : null;
                            const atOrAboveLmp = cap
                                ? cap.alert
                                : (lmpWithShipping(rowData) > 0 && sprice + 0.0001 >= lmpWithShipping(rowData));
                            if (cap && cap.shown > 0) sprice = cap.shown;
                            else if (atOrAboveLmp) sprice = amazonCapSpriceToLmp(rowData, sprice);

                            const sku = rowData['(Child) sku'] || '';
                            const dot = amazonSpriceChangeDotHtml(sprice, currentPrice, sku);
                            const redTri = atOrAboveLmp
                                ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>')
                                : '';
                            const blueTri = (!atOrAboveLmp && currentPrice > 0 && sprice > 0
                                && currentPrice.toFixed(2) !== sprice.toFixed(2))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + sprice.toFixed(2) + ' ≠ Price $' + currentPrice.toFixed(2) + '"></i>'
                                : '';

                            // When SPRICE matches Amazon price and is below LMP, show "-" (hold)
                            if (!atOrAboveLmp && currentPrice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) {
                                return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                    dot + '<span style="color:#adb5bd;" title="Same as Amz price">-</span></span>';
                            }

                            let formattedValue = '$' + sprice.toFixed(2);
                            if (atOrAboveLmp) {
                                formattedValue = '<span style="color: #dc3545; font-weight: 600;">' + formattedValue + '</span>';
                            } else if (hasCustomSprice === false) {
                                formattedValue = '<span style="color: #0d6efd; font-weight: 500;">' + formattedValue + '</span>';
                            }

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + formattedValue + blueTri + redTri + '</span>';
                        },
                        cellClick: function(e) {
                            if (e.target.closest('.view-sku-chart') || e.target.closest('.sprice-change-dot')) {
                                e.stopPropagation();
                                return false;
                            }
                        },
                        width: 90
                    },

                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const av = (typeof lmpWithShipping === 'function')
                                ? (lmpWithShipping(aRow.getData()) || 0)
                                : (parseFloat(a) || 0);
                            const bv = (typeof lmpWithShipping === 'function')
                                ? (lmpWithShipping(bRow.getData()) || 0)
                                : (parseFloat(b) || 0);
                            return av - bv;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();

                            if (window.ParentExpand) {
                                const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, {
                                    dataset: typeof allTableData !== 'undefined' ? allTableData : undefined
                                });
                                if (avgHtml !== null) return avgHtml;
                            }
                            if (rowData.is_parent_summary) return '';

                            const lmpPrice = cell.getValue();
                            const lmpEntries = rowData.lmp_entries || [];
                            const sku = rowData['(Child) sku'];
                            const totalCompetitors = rowData.lmp_entries_total || lmpEntries.length || 0;
                            const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                            const linkedSkusAttr = escAttr(JSON.stringify(linkedSkus));
                            const finalPrice = lmpWithShipping(rowData);

                            if (!(finalPrice > 0) && totalCompetitors === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            // LMP price + clickable competitor count in brackets: $28.70 (4)
                            // Price is L1 (lowest non-ignored), never an ignored competitor.
                            if (finalPrice > 0) {
                                const base = parseFloat(lmpPrice) || finalPrice;
                                const shipCost = finalPrice - base;
                                const priceFormatted = '$' + finalPrice.toFixed(2);
                                const currentPrice = parseFloat(rowData.price || 0);
                                const priceColor = (finalPrice < currentPrice) ? '#dc3545' : '#28a745';
                                const shipTip = shipCost > 0
                                    ? ('$' + base.toFixed(2) + ' + $' + shipCost.toFixed(2) + ' ship')
                                    : '';
                                let html = '<span style="color: ' + priceColor + '; font-weight: 600;"'
                                    + (shipTip ? (' title="' + escAttr(shipTip) + '"') : '') + '>'
                                    + priceFormatted;
                                if (totalCompetitors > 0) {
                                    html += ' <a href="#" class="view-lmp-competitors" data-sku="' + escAttr(sku)
                                        + '" data-linked-skus="' + linkedSkusAttr + '"'
                                        + ' title="View ' + totalCompetitors + ' competitor'
                                        + (totalCompetitors === 1 ? '' : 's') + '"'
                                        + ' style="color: #007bff; text-decoration: none; cursor: pointer; font-weight: 600;">'
                                        + '(' + totalCompetitors + ')</a>';
                                }
                                html += '</span>';
                                return html;
                            }

                            if (totalCompetitors > 0) {
                                return '<a href="#" class="view-lmp-competitors" data-sku="' + escAttr(sku)
                                    + '" data-linked-skus="' + linkedSkusAttr + '"'
                                    + ' title="View ' + totalCompetitors + ' competitor'
                                    + (totalCompetitors === 1 ? '' : 's') + '"'
                                    + ' style="color: #007bff; text-decoration: none; cursor: pointer; font-weight: 600;">'
                                    + '(' + totalCompetitors + ')</a>';
                            }

                            return '<span style="color: #999;">N/A</span>';
                        },
                        width: 100
                    },

                    // PRMT % / CVR Disc. / CVR Up/Dn / T Discounts / Push Prc
                    ...amazonPefPromoColumns(),

                    {
                        title: "LP",
                        field: "LP_productmaster",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Landed price (product master)",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) return '';
                            const val = cell.getValue();
                            if (val == null || val === '') return '';
                            const value = parseFloat(val);
                            if (!Number.isFinite(value)) return '';
                            return `<span>$${value.toFixed(2)}</span>`;
                        },
                        width: 60
                    },

                    {
                        title: "Ship",
                        field: "Ship_productmaster",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Shipping cost from CP Master / Shipping Master (Values.ship).",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) return '';
                            const val = cell.getValue();
                            if (val == null || val === '') return '';
                            const value = parseFloat(val);
                            if (!Number.isFinite(value)) return '';
                            const labelQty = parseInt(rowData.label_qty, 10);
                            let tip = '';
                            if (Number.isFinite(labelQty) && labelQty >= 2) {
                                tip = ` title="Label QTY ${labelQty}. Ship is the stored CP Master / Shipping Master value (already includes combo)."`;
                            }
                            return `<span${tip}>$${value.toFixed(2)}</span>`;
                        },
                        width: 60
                    },

                    {
                        title: "GROI%",
                        field: "GROI%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GROI%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        sorter: "number",
                        width: 65
                    },

                    {
                        title: "GPFT %",
                        field: "GPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const percent = parseFloat(value) || 0;
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },

                    {
                        title: "PFT %",
                        field: "PFT%",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const ads = parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0;
                            return ((parseFloat(aRow.getData()['GPFT%'] || 0) - ads) - (parseFloat(bRow.getData()['GPFT%'] || 0) - ads));
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const ads = parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0;
                            // PFT% = GPFT% − Ads% (channel TACOS)
                            const percent = (parseFloat(rowData['GPFT%'] || 0)) - ads;
                            const _st = (window.MetricPctColors && MetricPctColors.styleFor('npft', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
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
                        title: "Diff",
                        field: "lmp_diff_pct",
                        hozAlign: "center",
                        width: 80,
                        headerSortStartingDir: "desc",
                        sorter: function(a, b, aRow, bRow) {
                            const calc = function(rd) {
                                const lmp = lmpWithShipping(rd);
                                const price = parseFloat(rd.price || 0);
                                if (!lmp || lmp <= 0) return -Infinity;
                                return ((lmp - price) / lmp) * 100;
                            };
                            return calc(aRow.getData()) - calc(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';

                            const lmp = lmpWithShipping(rowData);
                            const price = parseFloat(rowData.price || 0);

                            if (!lmp || lmp <= 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            // (LMP incl. shipping - Amazon price) / LMP, as a percentage
                            const diff = ((lmp - price) / lmp) * 100;
                            const color = diff < 0 ? '#dc3545' : '#28a745';

                            return `<span style="color: ${color}; font-weight: 600;">${diff.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "SGROI",
                        field: "SGROI",
                        hozAlign: "center",
                        // Same formula as GROI%: ((SPRICE × 0.80 − ship − lp) / lp) × 100
                        sorter: function(a, b, aRow, bRow) {
                            const aVal = amazonComputeSroi(aRow.getData());
                            const bVal = amazonComputeSroi(bRow.getData());
                            return ((aVal == null || !isFinite(aVal)) ? 0 : aVal)
                                 - ((bVal == null || !isFinite(bVal)) ? 0 : bVal);
                        },
                        formatter: function(cell) {
                            const percent = amazonComputeSroi(cell.getRow().getData());
                            if (percent === null || !isFinite(percent)) return '';

                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GROI%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 65
                    },
                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "SNROI",
                        field: "SROI",
                        hozAlign: "center",
                        // Same formula as NROI badge: (PFT$ − Ad Spend$) / COGS × 100
                        sorter: function(a, b, aRow, bRow) {
                            const aNet = amazonComputeNetSroi(aRow.getData());
                            const bNet = amazonComputeNetSroi(bRow.getData());
                            return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                                 - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                        },
                        formatter: function(cell) {
                            const percent = amazonComputeNetSroi(cell.getRow().getData());
                            if (percent === null || !isFinite(percent)) return '';
                            
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GROI%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "SNPFT",
                        field: "Spft%",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const ads = parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0;
                            const aVal = parseFloat(aRow.getData().SGPFT);
                            const bVal = parseFloat(bRow.getData().SGPFT);
                            const aSpft = isNaN(aVal) ? 0 : (aVal - ads);
                            const bSpft = isNaN(bVal) ? 0 : (bVal - ads);
                            return aSpft - bSpft;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            // SPFT = SGPFT − Ads% (net of channel ad spend)
                            const rawGpft = rowData.SGPFT;
                            if (rawGpft === null || rawGpft === undefined || rawGpft === '') return '';
                            const sgpft = parseFloat(rawGpft);
                            if (isNaN(sgpft)) return '';
                            const ads = parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0;
                            const percent = sgpft - ads;
                            
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField(cell.getField ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "Review",
                        field: "review_issue",
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "Issue",
                        field: "issue_found",
                        hozAlign: "left",
                        visible: false
                    },
                    {
                        title: "Action",
                        field: "action_taken",
                        hozAlign: "left",
                        visible: false
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";
                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }
                            return `<span style="font-weight:600; color:${color};">${percent}%</span>`;
                        }
                    }
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'Parent',
                    skuField: '(Child) sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => {
                        if (typeof updateSummary === 'function') updateSummary();
                    },
                    onCollapse: () => {
                        if (typeof applyFilters === 'function') applyFilters();
                    },
                });
                ParentExpand.bind();
            }

            // SKU Search: use applyFilters() so it stacks with Sold, A L30 range, and all other filters
            $('#sku-search').on('keyup', function() {
                applyFilters();
            });

            // Parent Search: use applyFilters() so it stacks with all other filters
            $('#parent-search').on('keyup', function() {
                applyFilters();
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                if (field === 'STANDARD_PRICE') {
                    const sku = data['(Child) sku'];
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
                            const saved = response.data || std;
                            applyStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            if (typeof amzScheduleRuleSpriceSync === 'function') {
                                amzScheduleRuleSpriceSync({ delay: 200 });
                            }
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            showToast('success', n > 1
                                ? ('Std Prc saved for ' + n + ' linked SKUs')
                                : 'Std Prc saved');
                        },
                        error: function() {
                            showToast('error', 'Failed to save Std Prc');
                        }
                    });
                } else if (field === 'SPRICE') {
                    const sku = data['(Child) sku'];
                    value = amazonCapSpriceToLmp(data, value);
                    row.update({ SPRICE: value });
                    amazonPersistClearThenSave(sku, value, row)
                        .done(function(response) {
                            showToast('success', 'SPRICE updated successfully');
                            const updates = { 'SPRICE': response.data || value };
                            if (response.sgpft_percent !== undefined) {
                                updates['SGPFT'] = response.sgpft_percent;
                            }
                            if (response.spft_percent !== undefined) {
                                updates['Spft%'] = response.spft_percent;
                            }
                            if (response.sroi_percent !== undefined) {
                                updates['SROI'] = response.sroi_percent;
                            }
                            if (response.sgroi_percent !== undefined) {
                                updates['SGROI'] = response.sgroi_percent;
                            }
                            row.update(updates);
                        })
                        .fail(function() {
                            showToast('error', 'Failed to update SPRICE');
                        });
                } else if (field === 'Listed' || field === 'Live' || field === 'APlus') {
                    const sku = data['(Child) sku'];
                    $.ajax({
                        url: '/update-amazon-listed-live',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            field: field,
                            value: value ? 1 : 0
                        },
                        success: function(response) {
                            showToast('success', field + ' updated successfully');
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update ' + field);
                        }
                    });
                }
            });

            // Apply filters
            // Normalize parent key (trim + collapse spaces) to match backend and fix Play filter matching
            function normalizeParentKey(val) {
                if (val == null || val === '') return '';
                return String(val).trim().replace(/\s+/g, ' ');
            }
            // Build parent list from table (for Play/Next/Previous - same as eBay)
            function buildProductUniqueParentsFromTable() {
                if (typeof table === 'undefined' || !table) return [];
                var allRows = table.getData('all') || [];
                var seen = {};
                var list = [];
                allRows.forEach(function(r) {
                    var p = normalizeParentKey(r.Parent || r.parent);
                    if (p && !r.is_parent_summary && !String(p).toUpperCase().startsWith('PARENT') && !seen[p]) {
                        seen[p] = true;
                        list.push(p);
                    }
                });
                list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
                return list;
            }

            // Play / Pause parent navigation - init and handlers
            function initProductPlaybackControls() {
                if (typeof table === 'undefined' || !table) return;
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }
                $(document).off('click.amzplay', '#play-forward').on('click.amzplay', '#play-forward', productNextParent);
                $(document).off('click.amzplay', '#play-backward').on('click.amzplay', '#play-backward', productPreviousParent);
                $(document).off('click.amzplay', '#play-pause').on('click.amzplay', '#play-pause', productStopNavigation);
                $(document).off('click.amzplay', '#play-auto').on('click.amzplay', '#play-auto', productStartNavigation);
                updateProductButtonStates();
            }
            function productStartNavigation(e) {
                if (e) e.preventDefault();
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    showToast('info', 'No parent groups in data');
                    return;
                }
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                applyFilters();
                table.setPage(1);
                $('#play-auto').hide();
                $('#play-pause').show().removeClass('btn-light');
                updateProductButtonStates();
            }
            function productStopNavigation(e) {
                if (e) e.preventDefault();
                isProductNavigationActive = false;
                currentProductParentIndex = -1;
                $('#play-pause').hide();
                $('#play-auto').show().removeClass('btn-success btn-warning btn-danger').addClass('btn-light');
                applyFilters();
                updateProductButtonStates();
            }
            function productNextParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;
                currentProductParentIndex++;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }
            function productPreviousParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;
                currentProductParentIndex--;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }
            function updateProductButtonStates() {
                $('#play-backward').prop('disabled', !isProductNavigationActive || currentProductParentIndex <= 0);
                $('#play-forward').prop('disabled', !isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1);
                $('#play-auto').attr('title', isProductNavigationActive ? 'Show all products' : 'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', 'Next parent');
                $('#play-backward').attr('title', 'Previous parent');
                if (isProductNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            /** Re-apply sort + clamp page after filter pass (Tabulator can drop sort / leave page past max when data shrinks). */
            function amazonTabulatorFinalizeFilterApply(sortSnapshot) {
                queueMicrotask(function() {
                    if (typeof table === 'undefined' || !table) return;
                    if (sortSnapshot && sortSnapshot.length) {
                        try {
                            table.setSort(sortSnapshot);
                        } catch (eSort) { /* ignore */ }
                    }
                    try {
                        var maxP = table.getPageMax();
                        if (typeof maxP === 'number' && maxP >= 1) {
                            var cur = table.getPage();
                            if (cur > maxP) {
                                table.setPage(maxP);
                            }
                        }
                    } catch (ePage) { /* ignore */ }
                });
            }

            function applyFilters() {
                if (typeof table === 'undefined' || !table) return;
                if (window.ParentExpand && ParentExpand.isExpanded()) {
                    ParentExpand.beforeFilters(function() {
                        applyFilters();
                    });
                    return;
                }
                var sortSnapshot = [];
                try {
                    sortSnapshot = (table.getSorters() || []).map(function(s) {
                        try {
                            var col = s.column;
                            var field = col && typeof col.getField === 'function' ? col.getField() : null;
                            return field ? { column: field, dir: s.dir } : null;
                        } catch (errCol) {
                            return null;
                        }
                    }).filter(Boolean);
                } catch (errSnap) {
                    sortSnapshot = [];
                }

                const inventoryFilter = $('#inventory-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const roiFilter = $('#roi-filter').val();
                const diffFilter = $('#diff-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const cvrTrendFilter = $('#cvr-trend-filter').val();
                const dilFilter = $('#dil-filter').val();
                const ratingFilter = $('#rating-filter').val();
                const parentFilter = $('#parent-filter').val();
                // Parents mode: only parent rows (bypass data filters).
                // All Rows: show parents + SKUs — parents must bypass data filters or they get dropped.
                // SKUs mode: parent rows are removed by the dedicated filter below.
                const parentRowsBypassDataFilters = (parentFilter === 'parents' || parentFilter === 'all');
                const statusFilter = $('#status-filter').val();
                const soldFilter = $('#sold-filter').val();
                const spriceFilter = $('#sprice-filter').val();
                $('.sold-filter-badge').css({ 'outline': '', 'outline-offset': '' });
                if (soldFilter === 'zero') {
                    $('.sold-filter-badge[data-filter="zero"]').css({ 'outline': '2px solid #212529', 'outline-offset': '1px' });
                } else if (soldFilter === 'sold') {
                    $('.sold-filter-badge[data-filter="all"]').css({ 'outline': '2px solid #212529', 'outline-offset': '1px' });
                }

                table.clearFilter(true);

                // When Play is active: apply ONLY playback filter so parent summary row always shows (no other filter can hide it)
                if (isProductNavigationActive && productUniqueParents.length > 0 && currentProductParentIndex >= 0) {
                    var currentKey = productUniqueParents[currentProductParentIndex];
                    if (currentKey) {
                        table.addFilter(function(data) {
                            var p = normalizeParentKey(data.Parent || data.parent);
                            return p === currentKey || p === ('PARENT ' + currentKey);
                        });
                    }
                    updateSummary();
                    amazonTabulatorFinalizeFilterApply(sortSnapshot);
                    return;
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        return parseFloat(data.INV) === 0 || !data.INV;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        return parseFloat(data.INV) > 0;
                    });
                }

                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const gpft = parseFloat(data['GPFT%']) || 0;
                        if (gpftFilter === 'lt-20') return gpft <= 20;
                        if (gpftFilter === '20-30') return gpft > 20 && gpft < 30;
                        if (gpftFilter === '30-43') return gpft >= 30 && gpft < 43;
                        if (gpftFilter === 'gt-43') return gpft >= 43;
                        // legacy
                        if (gpftFilter === 'negative' || gpftFilter === '0-10' || gpftFilter === '10-20') return gpft <= 20;
                        if (gpftFilter === '30-40') return gpft >= 30 && gpft < 43;
                        if (gpftFilter === '40plus') return gpft >= 43;
                        return true;
                    });
                }

                if (roiFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const roiVal = parseFloat(data['GROI%']) || 0;
                        if (roiFilter === 'lt-60') return roiVal < 60;
                        if (roiFilter === '60-90') return roiVal >= 60 && roiVal < 90;
                        if (roiFilter === '90-150') return roiVal >= 90 && roiVal < 150;
                        if (roiFilter === 'gte-150') return roiVal >= 150;
                        // legacy
                        if (roiFilter === 'lt40') return roiVal < 60;
                        if (roiFilter === 'gt100') return roiVal >= 150;
                        const [min, max] = roiFilter.split('-').map(Number);
                        return roiVal >= min && roiVal < (max || Infinity);
                    });
                }

                if (diffFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const lmp = lmpWithShipping(data);
                        const price = parseFloat(data.price || 0);
                        if (!lmp || lmp <= 0) return false;
                        const diff = ((lmp - price) / lmp) * 100;
                        if (diffFilter === 'lt80') return diff < 80;
                        if (diffFilter === '80-100') return diff >= 80 && diff <= 100;
                        if (diffFilter === 'gt100') return diff > 100;
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    const slabs = { low: 3.5, mid: 7, high: 13, yellow_start: 0.01, pink_after: 13.01 };
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const cvr = (typeof amazonRowCvrL30 === 'function')
                            ? amazonRowCvrL30(data)
                            : ((parseFloat(data['Sess30']) || 0) <= 0 ? 0
                                : ((parseFloat(data['A_L30']) || 0) / (parseFloat(data['Sess30']) || 1)) * 100);
                        const isZero = (typeof amazonCvrIsZero === 'function')
                            ? amazonCvrIsZero(cvr)
                            : (!isFinite(cvr) || Math.abs(cvr) < 0.005);

                        if (cvrFilter === 'zero' || cvrFilter === '0-0') return isZero;
                        if (isZero) return false;

                        // Legacy option values → new slab keys
                        let key = cvrFilter;
                        if (key === '0-3') key = 'yellow';
                        else if (key === '3-7') key = 'blue';
                        else if (key === '7-13') key = 'green';
                        else if (key === '13plus') key = 'pink';

                        const slab = (typeof amazonCvrSlab === 'function')
                            ? amazonCvrSlab(cvr, slabs.low, slabs.mid, slabs.high)
                            : (cvr <= slabs.low ? 'red' : (cvr <= slabs.mid ? 'blue' : (cvr <= (slabs.high + 0.01) ? 'green' : 'pink')));
                        // Yellow band uses internal key "red" in amazonCvrSlab
                        if (key === 'yellow') return slab === 'red';
                        if (key === 'blue' || key === 'green' || key === 'pink') return slab === key;
                        return true;
                    });
                }

                // CVR trend filter: CVR L30 vs prior L31–L60 (CVR L60)
                if (cvrTrendFilter !== 'all') {
                    const cvrTrendTol = 0.1; // Same within ±0.1%
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const trend = (typeof amazonCvrTrend === 'function')
                            ? amazonCvrTrend(data, cvrTrendTol)
                            : null;
                        if (cvrTrendFilter === 'down') return trend === 'down';
                        if (cvrTrendFilter === 'up') return trend === 'up';
                        if (cvrTrendFilter === 'same' || cvrTrendFilter === 'equal') return trend === 'equal';
                        return true;
                    });
                }

                // DIL filter (sales velocity = L30 / INV * 100)
                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const inv = parseFloat(data['INV']) || 0;
                        const l30 = parseFloat(data['L30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                        if (dilFilter === 'red') return dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                // Reviews filter (Amazon avg rating from amazon:collect-reviews)
                if (ratingFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const rawRating = data['amz_avg_rating'];
                        const rating = parseFloat(rawRating);

                        if (ratingFilter === 'red') {
                            if (rawRating === null || rawRating === undefined) return false;
                            if (typeof rawRating === 'string' && rawRating.trim() === '') return false;
                            if (isNaN(rating) || rating <= 0) return false;
                            return rating < 3;
                        }
                        if (ratingFilter === 'yellow') return rating >= 3 && rating <= 3.5;
                        if (ratingFilter === 'blue') return rating >= 3.51 && rating <= 3.99;
                        if (ratingFilter === 'green') return rating >= 4 && rating <= 4.5;
                        if (ratingFilter === 'pink') return rating > 4.5;
                        return true;
                    });
                }

                // Filter Rows: parents, skus, or all (skip when Play is active - show current parent group only)
                if (!isProductNavigationActive) {
                    if (parentFilter === 'parents') {
                        table.addFilter(function(data) {
                            return data.is_parent_summary === true;
                        });
                    } else if (parentFilter === 'skus') {
                        table.addFilter(function(data) {
                            return data.is_parent_summary !== true;
                        });
                    }
                }

                // Play / Pause filter is applied at top of applyFilters when isProductNavigationActive (early return)

                // SKU search: match SKU (or Parent for parent rows).
                // Normalize away all whitespace + case so inconsistently formatted SKUs
                // (e.g. "CAPO BLUE 2 Pcs" vs "CAPO BLUE 2PCS") still match.
                var searchVal = ($('#sku-search').val() || '').replace(/\s+/g, '').toLowerCase();
                if (searchVal) {
                    table.addFilter(function(data) {
                        var sku = (data.is_parent_summary ? (data.Parent || data['(Child) sku'] || data.sku || '') : (data['(Child) sku'] || data.sku || ''));
                        sku = (sku + '').replace(/\s+/g, '').toLowerCase();
                        return sku.indexOf(searchVal) !== -1;
                    });
                }

                // Parent search: filter by Parent column
                var parentSearchVal = ($('#parent-search').val() || '').replace(/\s+/g, '').toLowerCase();
                if (parentSearchVal) {
                    table.addFilter(function(data) {
                        var parent = (data.Parent || '').toString().replace(/\s+/g, '').toLowerCase();
                        return parent.indexOf(parentSearchVal) !== -1;
                    });
                }

                if (statusFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        
                        const status = data.SPRICE_STATUS || null;
                        
                        if (statusFilter === 'not-pushed') {
                            // Show SKUs that are not pushed (null, empty, or anything other than 'pushed')
                            return status !== 'pushed';
                        } else if (statusFilter === 'pushed') {
                            return status === 'pushed';
                        } else if (statusFilter === 'applied') {
                            return status === 'applied';
                        } else if (statusFilter === 'error') {
                            return status === 'error';
                        }
                        return true;
                    });
                }

                // Sold filter (based on A_L30)
                if (soldFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        
                        const aL30 = parseFloat(data.A_L30) || 0;
                        
                        if (soldFilter === 'zero') {
                            return aL30 === 0;
                        } else if (soldFilter === 'sold') {
                            return aL30 > 0;
                        }
                        return true;
                    });
                }

                // S PRC filter: show only rows where SPRICE is blank (no value or 0)
                if (spriceFilter === 'blank') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                if (lmpMissingFilterActive && window.LmpMissingBadge) {
                    table.addFilter(function(data) {
                        return !LmpMissingBadge.isParentRow(data) && !LmpMissingBadge.hasLmp(data);
                    });
                }
                if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                    table.addFilter(function(data) {
                        return PriceGtLmpBadge.hasRedTriangle(data, 'price');
                    });
                }
                if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                    table.addFilter(function(data) {
                        return PriceLt80LmpBadge.hasPurpleTriangle(data, 'price');
                    });
                }
                if (blueTriangleFilterActive) {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return parentRowsBypassDataFilters;
                        return amazonHasBlueTriangle(data);
                    });
                }
                updateSummary();
                amazonTabulatorFinalizeFilterApply(sortSnapshot);
                setTimeout(function() {
                    updateRowSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #gpft-filter, #roi-filter, #diff-filter, #cvr-filter, #cvr-trend-filter, #dil-filter, #rating-filter, #parent-filter, #status-filter, #sold-filter, #sprice-filter').on('change', function() {
                applyFilters();
            });

            // Update summary badges for INV > 0
            function updateSummary() {
                // Use "active" data for campaign/badge counts so Campaign count matches "Showing X of Y rows"
                const allData = table.getData("all");
                const data = table.getData("active");
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalSkuCount = 0;
                let totalSoldCount = 0;
                let zeroSoldCount = 0;
                let prcGtLmpCount = 0;
                let mapCount = 0;
                let missingCount = 0;
                let missingAmazonCount = 0;
                let totalViews = 0;

                data.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        totalSkuCount++;
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * parseFloat(row['A_L30'] || 0);
                        totalViews += parseFloat(row['Sess30'] || 0);

                        const aL30 = parseFloat(row['A_L30'] || 0);
                        if (aL30 > 0) {
                            totalSoldCount++;
                        } else {
                            zeroSoldCount++;
                        }

                        const price = parseFloat(row['price'] || 0);
                        const lmpPrice = parseFloat(row['lmp_price'] || 0);
                        if (lmpPrice > 0 && price > lmpPrice) {
                            prcGtLmpCount++;
                        }

                        const inv = parseFloat(row['INV'] || 0);
                        const nrValue = row['NR'] || '';
                        const isMissingAmazon = row['is_missing_amazon'] || false;

                        if (isMissingAmazon && nrValue !== 'NR') {
                            missingAmazonCount++;
                        }

                        if (inv > 0 && nrValue === 'REQ' && !isMissingAmazon && price > 0) {
                            const invAmzNum = parseFloat(row['INV_AMZ'] || 0);
                            if (invAmzNum > 0) {
                                if (amazonInvWithinMapTolerance(inv, invAmzNum)) {
                                    mapCount++;
                                } else {
                                    missingCount++;
                                }
                            }
                        }
                    }
                });

                const SERVER_AMZ_SALES_L30 = {{ (float) ($amazonSalesL30 ?? 0) }};
                const SERVER_AMZ_QTY_L30 = {{ (int) ($amazonUnitsSoldL30 ?? 0) }};

                const avgPrice = SERVER_AMZ_QTY_L30 > 0 ? (SERVER_AMZ_SALES_L30 / SERVER_AMZ_QTY_L30) : 0;
                setAmzSummaryBadge($('#avg-price-badge'), 'Price: $' + avgPrice.toFixed(2));

                let totalViewsAll = 0;
                let blueTriangleCount = 0;
                allData.forEach(row => {
                    if (!row['is_parent_summary'] && parseFloat(row['INV']) > 0) {
                        totalViewsAll += parseFloat(row['Sess30'] || 0);
                    }
                    if (amazonHasBlueTriangle(row)) blueTriangleCount++;
                });
                const avgCVR = totalViewsAll > 0 ? (SERVER_AMZ_QTY_L30 / totalViewsAll * 100) : 0;
                setAmzSummaryBadge($('#avg-cvr-badge'), 'CVR: ' + avgCVR.toFixed(1) + '%');
                setAmzSummaryBadge($('#total-views-badge'), 'Views: ' + totalViews.toLocaleString());
                // Qty Sold badge is driven from real Amazon orders (server-computed, same source as
                // /amazon/daily-sales) so it matches that page — do NOT overwrite it with the per-SKU
                // A_L30 sum here.
                // Update sold counts
                $('#total-sold-count').text(totalSoldCount.toLocaleString());
                $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
                $('.sold-filter-badge[data-metric="sold_count"]').attr('data-live-value', totalSoldCount);
                $('.sold-filter-badge[data-metric="zero_sold_count"]').attr('data-live-value', zeroSoldCount);
                if (window.LmpMissingBadge) {
                    LmpMissingBadge.update('#amazon-lmp-missing-badge', allData, 'amazon');
                }
                if (window.PriceGtLmpBadge) {
                    PriceGtLmpBadge.update('#amazon-price-gt-lmp-badge', allData, 'amazon', 'price');
                }
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#amazon-price-lt80-lmp-badge', allData, 'amazon', 'price');
                }
                $('#amazon-blue-triangle-badge').html(
                    '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
                );
                syncAmazonBlueTriangleBadgeState();

                // Filtered (active) row count — exclude parent summary rows
                const visibleRowCount = data.filter(function(row) {
                    if (row['is_parent_summary']) return false;
                    const sku = String(row['(Child) sku'] || row['Parent'] || '').trim().toUpperCase();
                    return !(sku.indexOf('PARENT ') === 0 || sku === 'PARENT');
                }).length;
                $('#rows-count-badge').text('Row: ' + visibleRowCount.toLocaleString());

                // Ads% (from /all-marketplace-master, Amazon channel).
                const amazonAdsPercent = parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0;

                // GROI% = (Total PFT / Total COGS) * 100
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;
                setAmzSummaryBadge($('#groi-percent-badge'), 'GROI: ' + Math.round(groiPercent) + '%', Math.round(groiPercent));

                // NROI% = (Total PFT − Ad Spend) / COGS × 100
                // Ad Spend estimated from channel Ads% × sales (same Ads% basis as the Ads badge).
                const adSpendEst = (amazonAdsPercent / 100) * totalSalesAmt;
                const nroiPercent = totalLpAmt > 0 ? ((totalPftAmt - adSpendEst) / totalLpAmt) * 100 : 0;
                setAmzSummaryBadge($('#nroi-percent-badge'), 'NROI: ' + Math.round(nroiPercent) + '%', Math.round(nroiPercent));
                
                setAmzSummaryBadge($('#total-pft-amt-badge'), 'PFT: $' + Math.round(totalPftAmt), Math.round(totalPftAmt));
                // Sales badge = real-orders 30-day sales (matches /amazon/daily-sales). Not filter-dependent.
                setAmzSummaryBadge($('#total-sales-amt-badge'), 'Sales: $' + Math.round(SERVER_AMZ_SALES_L30).toLocaleString('en-US'), Math.round(SERVER_AMZ_SALES_L30));
                setAmzSummaryBadge($('#total-qty-sold-badge'), 'Qty: ' + Math.round(SERVER_AMZ_QTY_L30).toLocaleString('en-US'), SERVER_AMZ_QTY_L30);
                setAmzSummaryBadge($('#amazon-ads-badge'), 'Ads: ' + (isFinite(amazonAdsPercent) ? (Math.round(amazonAdsPercent * 10) / 10) + '%' : 'N/A'), amazonAdsPercent);
                
                // AVG GPFT% = (Total_pft / Total_Sales) * 100 (Gross Profit % - before ads)
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
                setAmzSummaryBadge($('#avg-gpft-badge'), 'GPFT: ' + Math.round(avgGpft) + '%', Math.round(avgGpft));
                
                // AVG PFT% (Net Profit %) = GPFT% − Ads%  (Ads% from /all-marketplace-master, Amazon channel)
                const avgPft = avgGpft - amazonAdsPercent;
                setAmzSummaryBadge($('#avg-pft-badge'), 'PFT: ' + Math.round(avgPft) + '%', Math.round(avgPft));
                if (typeof syncAmzSummaryTrendDots === 'function') syncAmzSummaryTrendDots();
                
                // Save badge stats daily (fire-and-forget, once per page load)
                // Only save when totalSkuCount > 0 (proof that real data was processed)
                if (!window._badgeStatsSaved && totalSkuCount > 0) {
                    window._badgeStatsSaved = true;
                    $.post('/amazon-badge-stats-save', {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sold_count: totalSoldCount,
                        zero_sold_count: zeroSoldCount,
                        map_count: mapCount,
                        nmap_count: missingCount,
                        missing_count: missingAmazonCount,
                        missing_fba_count: 0,
                        missing_nonfba_count: missingAmazonCount,
                        prc_gt_lmp_count: prcGtLmpCount,
                        total_pft: Math.round(totalPftAmt),
                        total_sales: Math.round(SERVER_AMZ_SALES_L30),
                        gpft_pct: Math.round(avgGpft),
                        npft_pct: Math.round(avgPft),
                        groi_pct: Math.round(groiPercent),
                        nroi_pct: Math.round(nroiPercent),
                        tcos_pct: amazonAdsPercent,
                        total_l30_orders: SERVER_AMZ_QTY_L30
                    });
                }
            }

            /*
             * Column visibility — 4 groups (Basic / Price / Ads / Other).
             * Persists in channel_tabulator_column_settings (channel = amazon_tabulator).
             * Every column with a field is listed; none are removed.
             */
            const COL_VIS_CATEGORY_KEYS = ['basic', 'price', 'ads', 'other'];
            const COL_VIS_CATEGORY_LABELS = {
                basic: 'Basic',
                price: 'Price',
                ads: 'Ads',
                other: 'Other'
            };

            function amazonColVisPlainTitle(def) {
                const field = def && def.field ? String(def.field) : '';
                if (field === 'row_select') return 'Row Select';
                if (field === 'push_prc') return 'Push Prc';
                if (field === 'cvr_discount') return 'CVR Disc.';
                if (field === 'cvr_up_dn') return 'CVR Up/Dn';
                if (field === 't_discounts') return 'T Discounts';
                if (field === 'prmt_pct') return 'PRMT %';
                const raw = (def && def.title != null) ? def.title : field;
                const t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                return t || field;
            }

            function classifyAmazonColumn(field, title) {
                const f = String(field || '');
                const t = String(title || field || '').toLowerCase();
                const fl = f.toLowerCase();

                // Ads — ad spend / campaign / ROAS style metrics (if present)
                if (
                    /^(nra|kw_spend|hl_spend|pt_spend|total_spend|acos|roas|ads_percent|ad_spend)$/i.test(f) ||
                    /\b(ads?|acos|roas|spend|campaign|tacos)\b/i.test(t)
                ) {
                    return 'ads';
                }

                // Price — selling price, LMP, SPRICE, profit/ROI %
                if (
                    /^(price|ship_productmaster|gpft%|groi%|pft%|standard_price|lmp_price|linked_lmp_skus|linked_lmp_sku_add|lmp_diff_pct|sprice|push_prc|prmt_pct|cvr_discount|cvr_up_dn|t_discounts|sgpft|sgroi|spft%|sroi|tpft)$/i.test(f) ||
                    /\b(price|prc|ship|gpft|groi|pft|sp\b|lmp|s\s*prc|push|sgpft|sroi|snpft|snroi|tpft|diff)\b/i.test(t)
                ) {
                    return 'price';
                }

                // Basic — identity, CVR, inventory, listing links, dil/views
                if (
                    /^(row_select|parent|image_path|\(child\) sku|cvr_l30|amz_avg_rating|asin|seller_asin_link|inv|inv_amz|l30|e dil%|a_l30|a dil %|sess30|sess7)$/i.test(f) ||
                    /\b(parent|image|sku|cvr|miss|reviews?|buyer|seller|inv|ov\s*l30|dil|a\s*l30|view)\b/i.test(t)
                ) {
                    return 'basic';
                }

                // Other — review/issue/action notes and anything unmatched
                if (
                    /^(review_issue|issue_found|action_taken)$/i.test(f) ||
                    /\b(issue|action)\b/i.test(t)
                ) {
                    return 'other';
                }

                // Fallback by field list so nothing is dropped
                if (fl.includes('cvr') || fl.includes('inv') || fl.includes('sku') || fl.includes('parent') || fl.includes('dil')) {
                    return 'basic';
                }
                if (fl.includes('price') || fl.includes('lmp') || fl.includes('sprice') || fl.includes('gpft') || fl.includes('groi') || fl.includes('roi') || fl.includes('pft')) {
                    return 'price';
                }
                return 'other';
            }

            const AMAZON_COL_CAT_STORAGE = 'amazon_tabulator_col_cats_v1';
            function loadAmazonColCats() {
                try {
                    const raw = localStorage.getItem(AMAZON_COL_CAT_STORAGE);
                    const parsed = raw ? JSON.parse(raw) : {};
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) {
                    return {};
                }
            }
            function saveAmazonColCats(map) {
                try { localStorage.setItem(AMAZON_COL_CAT_STORAGE, JSON.stringify(map || {})); } catch (e) { /* ignore */ }
            }
            function bindAmazonColVisDrag(li, groupEls) {
                li.draggable = true;
                li.addEventListener('dragstart', function(e) {
                    e.stopPropagation();
                    li.classList.add('col-vis-dragging');
                    e.dataTransfer.setData('text/plain', li.dataset.field || '');
                    e.dataTransfer.setData('text/col-vis-field', li.dataset.field || '');
                    e.dataTransfer.effectAllowed = 'move';
                });
                li.addEventListener('dragend', function() {
                    li.classList.remove('col-vis-dragging');
                    Object.keys(groupEls).forEach(function(k) {
                        groupEls[k].classList.remove('col-vis-drop-over');
                    });
                });
            }
            function bindAmazonColVisDropZone(group, list, groupEls) {
                [group, list].forEach(function(zone) {
                    zone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.add('col-vis-drop-over');
                        e.dataTransfer.dropEffect = 'move';
                    });
                    zone.addEventListener('dragleave', function(e) {
                        if (!group.contains(e.relatedTarget)) group.classList.remove('col-vis-drop-over');
                    });
                    zone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.remove('col-vis-drop-over');
                        const field = e.dataTransfer.getData('text/col-vis-field')
                            || e.dataTransfer.getData('text/plain');
                        if (!field) return;
                        const menu = document.getElementById('column-dropdown-menu');
                        const li = menu ? menu.querySelector('.col-vis-item[data-field="' + CSS.escape(field) + '"]') : null;
                        if (!li) return;
                        const nextCat = group.dataset.category;
                        if (!nextCat || li.dataset.group === nextCat) return;
                        const fromGroup = li.closest('.col-vis-group');
                        list.appendChild(li);
                        li.dataset.group = nextCat;
                        const cb = li.querySelector('input[type="checkbox"]');
                        if (cb) cb.dataset.group = nextCat;
                        const cats = loadAmazonColCats();
                        cats[field] = nextCat;
                        saveAmazonColCats(cats);
                        syncAmazonGroupHeaderCheckbox(fromGroup);
                        syncAmazonGroupHeaderCheckbox(group);
                    });
                });
            }

            function syncAmazonGroupHeaderCheckbox(groupEl) {
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
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(res => res.json())
                    .then(savedVisibility => {
                        const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                        const catOverrides = loadAmazonColCats();

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
                            bindAmazonColVisDropZone(group, list, groupEls);
                        });

                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            const field = def.field;
                            if (!field) return;

                            const title = amazonColVisPlainTitle(def);
                            let cat = catOverrides[field];
                            if (COL_VIS_CATEGORY_KEYS.indexOf(cat) === -1) {
                                cat = classifyAmazonColumn(field, title);
                            }
                            if (field === '__schema' || AMAZON_REMOVED_COL_FIELDS[field]) return;
                            const isVisible = amazonVisibilityIsStale(map)
                                ? (def.visible !== false)
                                : (map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible());

                            const li = document.createElement("li");
                            li.className = "col-vis-item";
                            li.dataset.field = field;
                            li.dataset.group = cat;

                            const label = document.createElement("label");
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = field;
                            checkbox.setAttribute('data-field', field);
                            checkbox.className = "col-vis-field-toggle";
                            checkbox.dataset.group = cat;
                            checkbox.checked = isVisible;

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(' ' + title));
                            label.title = title + ' (drag to another header)';
                            li.appendChild(label);
                            bindAmazonColVisDrag(li, groupEls);
                            lists[cat].appendChild(li);
                        });

                        COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                            syncAmazonGroupHeaderCheckbox(groupEls[cat]);
                        });

                        groupsLi.appendChild(groupsWrap);
                        menu.appendChild(groupsLi);
                    })
                    .catch(err => console.error('Error loading column visibility:', err));
            }

            function amazonVisibilityIsStale(map) {
                if (!map || typeof map !== 'object') return false;
                return Object.keys(AMAZON_REMOVED_COL_FIELDS).some(function(f) {
                    return Object.prototype.hasOwnProperty.call(map, f);
                });
            }

            function applyAmazonColumnDefinitionDefaults() {
                table.getColumns().forEach(function(col) {
                    const def = col.getDefinition();
                    if (!def.field || def.field === '__schema') return;
                    if (def.visible === false) col.hide();
                    else col.show();
                });
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const field = col.getDefinition().field;
                    if (field && !AMAZON_REMOVED_COL_FIELDS[field]) {
                        visibility[field] = col.isVisible();
                    }
                });

                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        visibility: visibility
                    })
                }).catch(err => console.error('Error saving column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(res => res.json())
                    .then(savedVisibility => {
                        if (!savedVisibility || typeof savedVisibility !== 'object' || amazonVisibilityIsStale(savedVisibility)) {
                            applyAmazonColumnDefinitionDefaults();
                            saveColumnVisibilityToServer();
                            return;
                        }
                        table.getColumns().forEach(col => {
                            const field = col.getDefinition().field;
                            if (!field || AMAZON_REMOVED_COL_FIELDS[field]) return;
                            if (!savedVisibility.hasOwnProperty(field)) return;
                            if (savedVisibility[field]) col.show();
                            else col.hide();
                        });
                    })
                    .catch(err => console.error('Error applying column visibility:', err));
            }

            function currentAmazonColumnOrder() {
                if (!table) return [];
                return table.getColumns()
                    .map(function(col) { return col.getField(); })
                    .filter(function(f) { return !!f; });
            }

            function saveAmazonColumnOrderToServer() {
                if (!table || amazonApplyingColumnOrder) return;
                const order = currentAmazonColumnOrder();
                if (!order.length) return;
                fetch(TABULATOR_COLUMN_ORDER_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        order: order
                    })
                }).catch(err => console.error('Error saving column order:', err));
            }

            function scheduleAmazonColumnOrderSave() {
                if (amazonColumnOrderSaveTimer) clearTimeout(amazonColumnOrderSaveTimer);
                amazonColumnOrderSaveTimer = setTimeout(saveAmazonColumnOrderToServer, 350);
            }

            function applyAmazonColumnOrder(order) {
                if (!table || !Array.isArray(order) || !order.length) return;
                const existing = currentAmazonColumnOrder();
                if (!existing.length) return;

                const valid = [];
                const seen = {};
                order.forEach(function(f) {
                    if (!f || seen[f] || existing.indexOf(f) === -1) return;
                    seen[f] = true;
                    valid.push(f);
                });
                existing.forEach(function(f) {
                    if (seen[f]) return;
                    seen[f] = true;
                    let inserted = false;
                    const existingIdx = existing.indexOf(f);
                    for (let j = existingIdx - 1; j >= 0; j--) {
                        const prevIdx = valid.indexOf(existing[j]);
                        if (prevIdx !== -1) {
                            valid.splice(prevIdx + 1, 0, f);
                            inserted = true;
                            break;
                        }
                    }
                    if (!inserted) valid.unshift(f);
                });

                const priceIdx = valid.indexOf('price');
                const spriceIdx = valid.indexOf('SPRICE');
                if (priceIdx !== -1 && spriceIdx !== -1 && spriceIdx !== priceIdx + 1) {
                    valid.splice(spriceIdx, 1);
                    const insertAt = valid.indexOf('price') + 1;
                    valid.splice(insertAt, 0, 'SPRICE');
                }

                amazonApplyingColumnOrder = true;
                try {
                    for (let i = 0; i < valid.length; i++) {
                        const field = valid[i];
                        const cols = table.getColumns().filter(function(c) { return !!c.getField(); });
                        const currentIdx = cols.findIndex(function(c) { return c.getField() === field; });
                        if (currentIdx === i || currentIdx < 0) continue;
                        if (i === 0) {
                            const firstField = cols[0].getField();
                            if (firstField && firstField !== field) {
                                table.moveColumn(field, firstField, false);
                            }
                        } else {
                            const prevField = valid[i - 1];
                            if (prevField) table.moveColumn(field, prevField, true);
                        }
                    }
                } catch (err) {
                    console.error('Error applying column order:', err);
                } finally {
                    amazonApplyingColumnOrder = false;
                }
            }

            function applyColumnOrderFromServer() {
                return fetch(TABULATOR_COLUMN_ORDER_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp && resp.success && Array.isArray(resp.order) && resp.order.length) {
                            applyAmazonColumnOrder(resp.order);
                        }
                    })
                    .catch(err => console.error('Error loading column order:', err));
            }

            // Wait for table to be built - applyFilters first for fast visible result, then defer heavy work
            table.on('tableBuilt', function() {
                applyFilters();
                requestAnimationFrame(function() {
                    Promise.resolve(applyColumnVisibilityFromServer())
                        .then(function() { return applyColumnOrderFromServer(); })
                        .finally(function() {
                            buildColumnDropdown();
                        });
                });
            });

            table.on('columnMoved', function() {
                if (amazonApplyingColumnOrder) return;
                scheduleAmazonColumnOrderSave();
                // Keep Columns dropdown labels in sync with new order
                buildColumnDropdown();
            });

            table.on('dataLoaded', function() {
                var allRows = table.getData('all') || [];
                var seenParent = {};
                var parents = [];
                allRows.forEach(function(r) {
                    var p = normalizeParentKey(r.Parent || r.parent);
                    if (p && !r.is_parent_summary && !String(p).toUpperCase().startsWith('PARENT') && !seenParent[p]) {
                        seenParent[p] = true;
                        parents.push(p);
                    }
                });
                parents.sort(function(a, b) { return String(a).localeCompare(String(b)); });
                productUniqueParents = parents.slice(0);
                initProductPlaybackControls();
                updateSummary();
                requestAnimationFrame(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    $('.row-select-checkbox').each(function() {
                        var sku = $(this).data('sku');
                        $(this).prop('checked', selectedRows.has(sku));
                    });
                    updateRowSelectAllCheckbox();
                    updateSelectedCount();
                });

            });

            table.on('renderComplete', function() {
                setTimeout(function() {
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    $('.row-select-checkbox').each(function() {
                        var sku = $(this).data('sku');
                        $(this).prop('checked', selectedRows.has(sku));
                    });
                    updateRowSelectAllCheckbox();
                    updateSelectedCount();
                }, 100);
            });

            /**
             * Child rows on the *current pagination page* only.
             * Do not use getRows('visible') — that is the virtual-scroll window, not the page.
             * Selections on other pages stay in selectedRows when paging.
             */
            function getCurrentPageChildRows() {
                if (!table) return [];
                var allActive = (table.getRows('active') || []).filter(function(row) {
                    var rd = row.getData() || {};
                    return !rd.is_parent_summary;
                });
                var pageSize = (typeof table.getPageSize === 'function' ? table.getPageSize() : 0) || 100;
                var currentPage = (typeof table.getPage === 'function' ? table.getPage() : 1) || 1;
                // Tabulator "All" option → pageSize is true (or >= total)
                if (pageSize === true || pageSize === 'true') {
                    return allActive;
                }
                pageSize = Number(pageSize) || 100;
                if (pageSize >= allActive.length && allActive.length > 0) {
                    return allActive;
                }
                var start = (currentPage - 1) * pageSize;
                return allActive.slice(start, start + pageSize);
            }

            // Select all — current pagination page only (other-page selections kept)
            $(document).on('change', '#select-all-rows', function() {
                var isChecked = $(this).prop('checked');
                if (!table) return;

                getCurrentPageChildRows().forEach(function(row) {
                    var rd = row.getData() || {};
                    var sku = rd['(Child) sku'] || '';
                    if (!sku) return;
                    if (isChecked) selectedRows.add(sku);
                    else selectedRows.delete(sku);
                });

                $('.row-select-checkbox').each(function() {
                    var sku = $(this).data('sku');
                    $(this).prop('checked', selectedRows.has(sku));
                });
                $(this).prop('indeterminate', false);
                updateSelectedCount();
            });

            // Individual row checkbox handler
            $(document).on('change', '.row-select-checkbox', function() {
                var sku = $(this).data('sku');
                if ($(this).prop('checked')) {
                    selectedRows.add(sku);
                } else {
                    selectedRows.delete(sku);
                }
                updateRowSelectAllCheckbox();
                updateSelectedCount();
            });

            // Header checkbox reflects selection on the current page only
            function updateRowSelectAllCheckbox() {
                if (!table) {
                    $('#select-all-rows').prop('checked', false).prop('indeterminate', false);
                    return;
                }
                var pageRows = getCurrentPageChildRows();
                if (pageRows.length === 0) {
                    $('#select-all-rows').prop('checked', false).prop('indeterminate', false);
                    return;
                }

                var selectedCount = 0;
                pageRows.forEach(function(row) {
                    var sku = (row.getData() || {})['(Child) sku'] || '';
                    if (sku && selectedRows.has(sku)) selectedCount++;
                });

                if (selectedCount === 0) {
                    $('#select-all-rows').prop('checked', false).prop('indeterminate', false);
                } else if (selectedCount === pageRows.length) {
                    $('#select-all-rows').prop('checked', true).prop('indeterminate', false);
                } else {
                    $('#select-all-rows').prop('checked', false).prop('indeterminate', true);
                }
            }

            // Clear selection button handler
            $('#clear-selection-btn').on('click', function() {
                clearRowSelections();
            });

            // Bulk action handler
            $(document).on('click', '.bulk-action-item', function(e) {
                e.preventDefault();
                var action = $(this).data('action');
                var selectedSkusList = getSelectedSkus();
                
                if (selectedSkusList.length === 0) {
                    alert('Please select at least one row');
                    return;
                }
                
                // Show loading
                var $btn = $('#bulkActionsDropdown');
                var originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
                
                // Handle NRA/RA/LATER actions - use same URL as single-cell save
                var bulkSaveUrl = "{{ url('update-amazon-nr-nrl-fba') }}";
                    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                    var promises = selectedSkusList.map(function(sku) {
                        return fetch(bulkSaveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                sku: sku,
                                field: 'NRA',
                                value: action
                            })
                        }).then(function(res) {
                            if (!res.ok) {
                                return res.text().then(function(text) {
                                    var msg = res.status === 419 ? 'Session expired.' : (text || 'Save failed (' + res.status + ')');
                                    return { ok: false, sku: sku, error: msg };
                                });
                            }
                            return res.json().then(function(data) {
                                return { ok: data.success !== false, sku: sku, data: data };
                            });
                        }).catch(function(err) {
                            return { ok: false, sku: sku, error: err.message || 'Network error' };
                        });
                    });

                    Promise.all(promises).then(function(results) {
                        var succeeded = 0;
                        var failed = [];
                        results.forEach(function(r) {
                            if (r.ok) {
                                succeeded++;
                                var rows = table.getRows().filter(function(row) {
                                    return row.getData()['(Child) sku'] === r.sku;
                                });
                                rows.forEach(function(row) {
                                    row.update({ NRA: action });
                                });
                            } else {
                                failed.push(r.sku + (r.error ? ': ' + r.error : ''));
                            }
                        });

                        if (failed.length > 0) {
                            showToast('danger', succeeded + ' saved, ' + failed.length + ' failed. ' + (failed[0].length > 60 ? failed[0].substring(0, 60) + '…' : failed[0]));
                            if (failed.length > 1) console.error('Bulk NRA failures:', failed);
                        } else {
                            showToast('success', succeeded + ' row(s) marked as ' + action);
                        }
                        clearRowSelections();
                        $btn.html(originalText).prop('disabled', false);
                    }).catch(function(err) {
                        console.error('Bulk action error:', err);
                        alert('Error processing bulk action: ' + (err.message || 'Unknown error'));
                        $btn.html(originalText).prop('disabled', false);
                    });
            });

            // Bulk Push Actions - Amazon only
            $(document).on('click', '#executeBulkPush', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!$('#bulkPushAmazon').is(':checked')) {
                    alert('Please select Amz');
                    return;
                }

                var selectedSkusList = getSelectedSkus();

                if (selectedSkusList.length === 0) {
                    alert('Please select at least one row');
                    return;
                }

                if (!confirm('Push ' + selectedSkusList.length + ' price(s) to Amz?')) {
                    return;
                }
                
                // Show loading
                var $btn = $('#bulkActionsDropdown');
                var originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Pushing...').prop('disabled', true);
                
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
                
                // For each SKU, push to all selected marketplaces
                var allPromises = [];
                
                selectedSkusList.forEach(function(sku) {
                    // Get SPRICE for this SKU
                    var row = table.getRows().find(function(r) {
                        return r.getData()['(Child) sku'] === sku;
                    });
                    
                    if (!row) {
                        allPromises.push(Promise.resolve({ 
                            ok: false, 
                            sku: sku, 
                            marketplace: 'all',
                            error: 'Row not found' 
                        }));
                        return;
                    }
                    
                    var rowData = row.getData();
                    var spriceRaw = rowData.SPRICE;
                    
                    if (!spriceRaw || spriceRaw === '' || spriceRaw === '0' || spriceRaw === '0.00') {
                        allPromises.push(Promise.resolve({ 
                            ok: false, 
                            sku: sku, 
                            marketplace: 'all',
                            error: 'No SPRICE set' 
                        }));
                        return;
                    }
                    
                    var promise = fetch("{{ route('apply.amazon.price') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            price: spriceRaw,
                            push_shopify: false,
                            update_amazon_min_price: true
                        })
                    }).then(function(res) {
                        if (!res.ok) {
                            return res.text().then(function(text) {
                                var msg = res.status === 419 ? 'Session expired.' : (text || 'Push failed (' + res.status + ')');
                                return { ok: false, sku: sku, marketplace: 'amazon', error: msg };
                            });
                        }
                        return res.json().then(function(data) {
                            return { ok: data.success !== false, sku: sku, marketplace: 'amazon', data: data };
                        });
                    }).catch(function(err) {
                        return { ok: false, sku: sku, marketplace: 'amazon', error: err.message || 'Network error' };
                    });

                    allPromises.push(promise);
                });
                
                Promise.all(allPromises).then(function(results) {
                    var succeeded = 0;
                    var failed = [];
                    var skusToUpdate = {};
                    
                    results.forEach(function(r) {
                        if (r.ok) {
                            succeeded++;
                            // Track which SKU needs which status updated
                            if (!skusToUpdate[r.sku]) {
                                skusToUpdate[r.sku] = {};
                            }
                            skusToUpdate[r.sku][r.marketplace] = 'pushed';
                        } else {
                            failed.push(r.sku + (r.error ? ': ' + r.error : ''));
                        }
                    });

                    Object.keys(skusToUpdate).forEach(function(sku) {
                        var rows = table.getRows().filter(function(row) {
                            return row.getData()['(Child) sku'] === sku;
                        });
                        rows.forEach(function(row) {
                            if (skusToUpdate[sku]['amazon']) {
                                row.update({ STATUS: 'pushed', SPRICE_STATUS: 'pushed' });
                                row.reformat();
                            }
                        });
                    });

                    if (failed.length > 0) {
                        showToast('warning', succeeded + ' pushed, ' + failed.length + ' failed. ' + (failed[0].length > 80 ? failed[0].substring(0, 80) + '…' : failed[0]));
                        if (failed.length > 1) console.error('Bulk push failures:', failed);
                    } else {
                        showToast('success', succeeded + ' price(s) pushed to Amz');
                    }
                    clearRowSelections();
                    $btn.html(originalText).prop('disabled', false);
                }).catch(function(err) {
                    console.error('Bulk push error:', err);
                    alert('Error processing bulk push: ' + (err.message || 'Unknown error'));
                    $btn.html(originalText).prop('disabled', false);
                });
            });

            // Function to get all selected SKUs
            function getSelectedSkus() {
                return Array.from(selectedRows);
            }

            // Function to clear all selections
            function clearRowSelections() {
                selectedRows.clear();
                $('.row-select-checkbox').prop('checked', false);
                $('#select-all-rows').prop('checked', false);
                $('#select-all-rows').prop('indeterminate', false);
                updateSelectedCount();
            }

            // Toggle column / group from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type !== 'checkbox') return;

                // Group header: select / deselect entire category
                if (e.target.classList.contains('col-vis-group-toggle')) {
                    const checked = e.target.checked;
                    const groupEl = e.target.closest('.col-vis-group');
                    const itemCbs = groupEl
                        ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                        : [];
                    itemCbs.forEach(function(cb) {
                        const field = cb.getAttribute('data-field') || cb.value;
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

                const field = e.target.getAttribute('data-field') || e.target.value;
                const col = table.getColumn(field);
                if (!col) return;
                if (e.target.checked) col.show();
                else col.hide();
                const groupEl = e.target.closest('.col-vis-group');
                syncAmazonGroupHeaderCheckbox(groupEl);
                saveColumnVisibilityToServer();
            });

            // Keep dropdown open when clicking checkboxes / labels
            document.getElementById("column-dropdown-menu").addEventListener("click", function(e) {
                if (e.target.closest('label') || e.target.type === 'checkbox') {
                    e.stopPropagation();
                }
            });

            // Copy SKU to clipboard
            document.addEventListener("click", function(e) {
                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn") || e.target.closest('.copy-sku-btn')) {
                    const btn = e.target.classList.contains("copy-sku-btn") ? e.target : e.target.closest(
                        '.copy-sku-btn');
                    const sku = btn.getAttribute('data-sku');

                    navigator.clipboard.writeText(sku).then(() => {
                        showToast('success', 'SKU copied to clipboard');
                    }).catch(err => {
                        showToast('error', 'Failed to copy SKU');
                    });
                }

                // View SKU chart (Price or CVR from column dot / SKU info icon)
                if (e.target.closest('.view-sku-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const el = e.target.closest('.view-sku-chart');
                    openAmazonSkuChart(el.getAttribute('data-sku'), el.getAttribute('data-metric') || 'price');
                }
            });

            // Handle NRA/NRL dropdown changes - save to database
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = (e.target.getAttribute("data-sku") || '').toString().trim();
                    let field = (e.target.getAttribute("data-field") || '').toString().trim();
                    let value = (e.target.value || '').toString().trim();

                    if (!sku || !field || (field !== 'NRL' && field !== 'NRA')) return;

                    // Update color immediately for NRA field
                    if (field === 'NRA') {
                        if (value === 'NRA') {
                            e.target.style.backgroundColor = '#dc3545'; // red
                            e.target.style.color = '#000';
                        } else if (value === 'RA') {
                            e.target.style.backgroundColor = '#28a745'; // green
                            e.target.style.color = '#000';
                        } else if (value === 'LATER') {
                            e.target.style.backgroundColor = '#ffc107'; // yellow
                            e.target.style.color = '#000';
                        }
                    }

                    var saveUrl = "{{ url('update-amazon-nr-nrl-fba') }}";
                    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                    fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: value
                        })
                    })
                    .then(function(res) {
                        if (!res.ok) {
                            return res.text().then(function(t) {
                                throw new Error(res.status === 419 ? 'Session expired. Please refresh the page.' : (t || 'Save failed (' + res.status + ')'));
                            });
                        }
                        return res.json();
                    })
                    .then(function(data) {
                        if (data.success && typeof table !== 'undefined' && table) {
                            var childSku = (sku || '').toString().trim();
                            var rows = table.getRows().filter(function(r) {
                                var d = r.getData();
                                var rowSku = (d['(Child) sku'] || d.sku || '').toString().trim();
                                return rowSku === childSku;
                            });
                            if (rows.length > 0) {
                                var row = rows[0];
                                if (field === 'NRL') {
                                    row.update({ NRL: value, NR: value === 'NRL' ? 'NR' : 'REQ' });
                                } else {
                                    row.update({ NRA: value });
                                }
                            }
                        }
                        if (typeof showToast === 'function') {
                            showToast('success', field === 'NRL' ? 'NRL saved.' : 'NRA saved.');
                        }
                    })
                    .catch(function(err) {
                        console.error('Error saving NRL/NRA:', err);
                        alert("Failed to save: " + (err.message || "Network error"));
                    });
                }
            });

            // Single toast: accepts showToast(message, type) or showToast(type, message)
            function showToast(a, b) {
                var type, message;
                if (['success','error','info','warning','danger'].indexOf(String(a)) !== -1 && typeof b === 'string') {
                    type = a;
                    message = b;
                } else {
                    message = a;
                    type = b || 'info';
                }
                var container = document.querySelector('.toast-container');
                if (!container) return;
                var bg = (type === 'error' || type === 'danger') ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
                var toast = document.createElement('div');
                toast.className = 'toast align-items-center text-white bg-' + bg + ' border-0';
                toast.setAttribute('role', 'alert');
                toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '') + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
                container.appendChild(toast);
                new bootstrap.Toast(toast).show();
                toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
            }

            // Load Competitors Modal Function
            // Pass options.refresh = true to live-fetch price + delivery (ship) via SerpApi
            // (same pattern as eBay #lmpPullApiBtn → GET /ebay-lmp-data?refresh=1).
            function loadCompetitorsModal(sku, linkedLmpSkus, options) {
                options = options || {};
                const refreshFromApi = !!options.refresh;
                $('#lmpSku').text(sku);
                
                // Pre-fill form with SKU
                $('#addCompSku').val(sku);
                $('#addCompAsin').val('');
                $('#addCompPrice').val('');
                $('#addCompLink').val('');

                const rowData = getAmazonTabulatorRowDataBySku(sku);
                if ((!linkedLmpSkus || !linkedLmpSkus.length) && rowData && Array.isArray(rowData.linked_lmp_skus)) {
                    linkedLmpSkus = rowData.linked_lmp_skus;
                }

                currentLmpData.sku = sku;
                currentLmpData.linkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
                initLmpModalSpFromSku(sku);

                const cachedEntries = (rowData && Array.isArray(rowData.lmp_entries)) ? rowData.lmp_entries.slice() : [];
                const cachedIds = cachedEntries.map(function(e) { return e && e.id; }).filter(Boolean);
                
                $('#lmpModal').modal('show');

                // Same (N) list the LMP column already counted — show immediately
                if (cachedEntries.length && !refreshFromApi) {
                    currentLmpData.competitors = cachedEntries;
                    const cachedL1 = amazonL1FromCompetitors(cachedEntries);
                    currentLmpData.lowestPrice = cachedL1.l1;
                    renderCompetitorsList(cachedEntries, cachedL1.l1);
                    return;
                }

                $('#lmpDataList').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">${refreshFromApi ? 'Pulling live prices + shipping from Amz API...' : 'Loading competitors...'}</p>
                    </div>
                `);

                const reqData = {
                    sku: sku,
                    linked_lmp_skus: currentLmpData.linkedLmpSkus,
                };
                if (cachedIds.length) {
                    reqData.ids = cachedIds;
                }
                if (refreshFromApi) {
                    reqData.refresh = 1;
                }
                
                // Fetch competitors from backend (merged across Sku Link LMP group)
                $.ajax({
                    url: '/amazon/competitors',
                    method: 'GET',
                    traditional: true,
                    data: reqData,
                    // Parallel ASIN pool — usually finishes in one or two rounds
                    timeout: refreshFromApi ? 90000 : 60000,
                    success: function(response) {
                        if (response.success && response.competitors && response.competitors.length > 0) {
                            currentLmpData.sku = sku;
                            currentLmpData.competitors = response.competitors;
                            const l1Info = amazonL1FromCompetitors(response.competitors);
                            const l1 = l1Info.l1 != null ? l1Info.l1 : response.lowest_price;
                            currentLmpData.lowestPrice = l1;
                            
                            renderCompetitorsList(response.competitors, l1);
                            patchAmazonGridLmp(
                                l1,
                                l1Info.winner ? (l1Info.winner.delivery || null) : (response.lowest_delivery || null),
                                response.competitors
                            );

                            if (refreshFromApi) {
                                showToast('Pulled live LMP prices + shipping for ' + sku, 'success');
                            }
                        } else if (cachedEntries.length) {
                            if (refreshFromApi) {
                                showToast('Live pull returned no rows; showing saved LMP list', 'warning');
                            }
                        } else {
                            $('#lmpDataList').html(`
                                <div class="alert alert-warning">
                                    <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                                </div>
                            `);
                            if (refreshFromApi) {
                                showToast('No competitors found for ' + sku, 'warning');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading competitors:', xhr);
                        if (cachedEntries.length) {
                            if (refreshFromApi) {
                                const apiMsg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || '';
                                showToast(apiMsg || 'Failed to pull Amz LMP data', 'error');
                            }
                            return;
                        }
                        const apiMsg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || '';
                        $('#lmpDataList').html(`
                            <div class="alert alert-danger">
                                <i class="fa fa-exclamation-triangle"></i>
                                Could not load competitors. Please close this dialog and try again.
                                ${apiMsg ? `<div class="small mt-1">${$('<div>').text(apiMsg).html()}</div>` : ''}
                            </div>
                        `);
                        if (refreshFromApi) {
                            showToast(apiMsg || 'Failed to pull Amz LMP data', 'error');
                        }
                    },
                    complete: function() {
                        const $btn = $('#lmpPullApiBtn');
                        if ($btn.length) {
                            $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt"></i> Pull');
                        }
                    }
                });
            }

            // Pull live competitor prices + shipping (delivery) for the open SKU
            $(document).on('click', '#lmpPullApiBtn', function() {
                const sku = currentLmpData.sku || $('#lmpSku').text().trim();
                if (!sku) {
                    showToast('No SKU selected', 'error');
                    return;
                }
                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Pulling...');
                loadCompetitorsModal(sku, currentLmpData.linkedLmpSkus || [], { refresh: true });
            });

            function patchAmazonGridLmp(lowestPrice, lowestDelivery, competitors) {
                const entries = Array.isArray(competitors) ? competitors : null;
                const patch = {
                    lmp_price: lowestPrice,
                    lmp_delivery: lowestPrice == null ? null : (lowestDelivery != null ? lowestDelivery : undefined)
                };
                if (entries) {
                    patch.lmp_entries = entries;
                    patch.lmp_entries_total = entries.length;
                }

                const targets = new Set();
                const addTarget = function(s) {
                    const t = amazonNormalizeSkuKey(s);
                    if (t) targets.add(t);
                };
                addTarget(currentLmpData.sku);
                (currentLmpData.linkedLmpSkus || []).forEach(addTarget);
                if (!targets.size) return;

                const applyToData = function(d) {
                    if (!d || d.is_parent_summary) return false;
                    const rowSku = amazonNormalizeSkuKey(d['(Child) sku'] || d.SKU || d.sku);
                    if (!targets.has(rowSku)) return false;
                    d.lmp_price = patch.lmp_price;
                    if (patch.lmp_delivery !== undefined) d.lmp_delivery = patch.lmp_delivery;
                    if (entries) {
                        d.lmp_entries = entries;
                        d.lmp_entries_total = entries.length;
                    }
                    amazonApplyL1FromEntries(d);
                    return true;
                };

                if (Array.isArray(allTableData)) {
                    allTableData.forEach(applyToData);
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                }

                if (typeof table === 'undefined' || !table || !table.getRows) return;
                let rows = [];
                try {
                    rows = table.getRows('all') || table.getRows() || [];
                } catch (e) {
                    rows = table.getRows() || [];
                }
                rows.forEach(function(row) {
                    const d = row.getData();
                    if (!applyToData(d)) return;
                    const upd = {
                        lmp_price: d.lmp_price,
                        lmp_delivery: d.lmp_delivery
                    };
                    if (entries) {
                        upd.lmp_entries = d.lmp_entries;
                        upd.lmp_entries_total = d.lmp_entries_total;
                    }
                    row.update(upd);
                });
            }

            function lmpTextPreviewCell(label, text) {
                const raw = (text == null) ? '' : String(text).trim();
                if (!raw || raw === '—' || raw === 'N/A') {
                    return '<span style="color:#999;">—</span>';
                }
                return '<button type="button" class="lmp-text-preview-btn" title="View full ' + escAttr(label) + '"'
                    + ' data-label="' + escAttr(label) + '" data-text="' + escAttr(raw) + '">'
                    + '<i class="fa fa-search"></i></button>';
            }
            function openLmpTextPreview(label, text) {
                $('#lmpTextPreviewTitle').text(label || 'Details');
                $('#lmpTextPreviewBody').text(text || '');
                $('#lmpTextPreviewOverlay').prop('hidden', false).addClass('is-open');
            }
            function closeLmpTextPreview() {
                $('#lmpTextPreviewOverlay').removeClass('is-open').prop('hidden', true);
            }

            // Render Competitors List Function
            function renderCompetitorsList(competitors, lowestPrice) {
                if (!competitors || competitors.length === 0) {
                    $('#lmpDataList').html(`
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> No competitors found for this SKU
                        </div>
                    `);
                    return;
                }
                
                const modalSp = parseFloat($('#lmpModalSpInput').val());
                const modalSpText = (isFinite(modalSp) && modalSp > 0) ? ('$' + modalSp.toFixed(2)) : '—';
                const modalGroi = amazonComputeGroiAtSp(modalSp, currentLmpData.rowData);
                const modalNroi = amazonComputeNroiAtSp(modalSp, currentLmpData.rowData);
                const modalGroiHtml = modalGroi === null ? '<span class="text-muted">—</span>' : amazonModalGroiColoredHtml(modalGroi);
                const modalNroiHtml = modalNroi === null ? '<span class="text-muted">—</span>' : amazonModalNroiColoredHtml(modalNroi);
                const l1ValForList = (lowestPrice != null && isFinite(parseFloat(lowestPrice)))
                    ? parseFloat(lowestPrice) : null;

                let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
                html += `
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th style="width: 60px;">Image</th>
                            <th style="width: 100px;">ASIN</th>
                            <th class="text-center" style="width: 44px;" title="Product Title">Title</th>
                            <th class="text-center" style="width: 44px;" title="Seller">Seller</th>
                            <th style="width: 80px;">Price</th>
                            <th style="width: 70px;" title="Std Prc from top input">Std Prc</th>
                            <th style="width: 70px;" title="GROI% at top SP — same formula as Sroi">GROI %</th>
                            <th style="width: 70px;" title="NROI% at top SP — same formula as SNROI / NROI badge">NROI %</th>
                            <th style="width: 90px;">Revenue<br><small>(30d)</small></th>
                            <th style="width: 70px;">Units<br><small>(30d)</small></th>
                            <th style="width: 100px;">Buy Box</th>
                            <th style="width: 60px;">Type</th>
                            <th style="width: 70px;">Rating</th>
                            <th style="width: 70px;">Reviews</th>
                            <th style="width: 140px;">Delivery</th>
                            <th style="width: 80px;" title="Competitor inventory / stock from Amz (SerpApi)">Inv</th>
                            <th style="width: 60px;">Link</th>
                            <th class="text-center" style="width: 60px;" title="Ignore for L1">Ignore</th>
                            <th style="width: 80px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                `;
                
                competitors.forEach((item, index) => {
                    const basePrice = parseFloat(item.price) || 0;

                    let shipCost = 0;
                    if (item.delivery) {
                        const delText = String(item.delivery);
                        // FREE → do not add; paid "$X.XX delivery" → add
                        if (!/\bfree\b/i.test(delText)) {
                            const paidMatch = delText.match(/\$\s*([\d,]+\.?\d*)\s*delivery/i)
                                || delText.match(/\$\s*([\d,]+\.?\d*)/);
                            if (paidMatch) {
                                shipCost = parseFloat(paidMatch[1].replace(/,/g, '')) || 0;
                            }
                        }
                    }
                    const totalPrice = (item.landed_price != null && parseFloat(item.landed_price) > 0)
                        ? parseFloat(item.landed_price)
                        : (basePrice + shipCost);

                    // L1 compares landed (price + paid delivery); ignored rows never count
                    const ignored = !!item.ignored;
                    const l1Val = (lowestPrice != null && isFinite(parseFloat(lowestPrice)))
                        ? parseFloat(lowestPrice) : null;
                    const isLowest = !ignored && l1Val !== null && Math.abs(totalPrice - l1Val) < 0.01;
                    const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'lmp-lowest-row' : '');
                    const totalFormatted = '$' + totalPrice.toFixed(2);
                    const priceInner = shipCost > 0
                        ? `${totalFormatted}<br><small style="color:#888;font-weight:400;">$${basePrice.toFixed(2)} + $${shipCost.toFixed(2)} ship</small>`
                        : totalFormatted;
                    const priceBadge = isLowest
                        ? `<span class="badge bg-success">${priceInner} <i class="fa fa-trophy"></i></span>`
                        : (ignored
                            ? `<strong>${priceInner}</strong> <span class="badge bg-secondary">Ignored</span>`
                            : `<strong>${priceInner}</strong>`);
                    const skuEsc = escAttr(currentLmpData.sku || item.sku || '');
                    const ignoreCb = `<input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"`
                        + (ignored ? ' checked' : '')
                        + ` data-id="${item.id}" data-marketplace="amazon" data-sku="${skuEsc}">`;
                    
                    const productLink = item.link || item.product_link || '#';
                    const productTitle = item.title || item.product_title || 'N/A';
                    const sellerName = item.seller_name || '—';
                    const titleCell = lmpTextPreviewCell('Product Title', productTitle);
                    const sellerCell = lmpTextPreviewCell('Seller', sellerName);
                    const imageUrl = item.image || '';
                    const imageHtml = imageUrl ? `<img src="${imageUrl}" style="width: 50px; height: 50px; object-fit: contain;" />` : '<span style="color: #999;">—</span>';
                    
                    const revenue = item.monthly_revenue ? `<span style="color: #28a745; font-weight: 600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>` : '<span style="color: #999;">—</span>';
                    const units = item.monthly_units_sold ? `<span style="color: #007bff; font-weight: 600;">${parseInt(item.monthly_units_sold)}</span>` : '<span style="color: #999;">—</span>';
                    const buyBox = item.buy_box_owner ? `<span style="font-size: 11px;">${item.buy_box_owner}</span>` : '<span style="color: #999;">—</span>';
                    const sellerType = item.seller_type ? `<span class="badge bg-${item.seller_type === 'FBA' ? 'warning' : 'secondary'}">${item.seller_type}</span>` : '<span style="color: #999;">—</span>';
                    
                    const rating = item.rating ? `<span style="color: #ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>` : '<span style="color: #999;">—</span>';
                    const reviews = item.reviews ? `<span>${parseInt(item.reviews).toLocaleString()}</span>` : '<span style="color: #999;">—</span>';

                    let deliveryHtml = '<span style="color: #999;">—</span>';
                    if (item.delivery) {
                        const delText = String(item.delivery);
                        // Free / FREE for Prime members → show 0
                        const isFree = /\bfree\b/i.test(delText)
                            || (/\bprime\b/i.test(delText) && /\bfree\b/i.test(delText));
                        const paidMatch = delText.match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                        if (isFree) {
                            deliveryHtml = `<span style="color: #28a745; font-weight: 600;" title="${escAttr(delText)}">0</span>`;
                        } else if (paidMatch) {
                            deliveryHtml = `<span style="color: #dc3545; font-weight: 600;" title="${escAttr(delText)}">$${paidMatch[1]} ship</span>`;
                        } else {
                            deliveryHtml = `<span style="font-size: 10px;" title="${escAttr(delText)}">${delText.substring(0, 22)}${delText.length > 22 ? '…' : ''}</span>`;
                        }
                    }

                    let stockHtml = '<span style="color: #999;">—</span>';
                    const stockText = item.stock != null ? String(item.stock).trim() : '';
                    const stockQty = item.stock_quantity != null && item.stock_quantity !== ''
                        ? parseInt(item.stock_quantity, 10)
                        : NaN;
                    if (stockText || isFinite(stockQty)) {
                        const tip = escAttr(stockText || (isFinite(stockQty) ? String(stockQty) : ''));
                        if (isFinite(stockQty) && stockQty === 0) {
                            stockHtml = `<span style="color:#dc3545;font-weight:700;" title="${tip}">0</span>`;
                        } else if (isFinite(stockQty) && stockQty > 0) {
                            const color = stockQty <= 5 ? '#dc3545' : (stockQty <= 20 ? '#ffc107' : '#28a745');
                            stockHtml = `<span style="color:${color};font-weight:700;" title="${tip}">${stockQty}</span>`;
                        } else if (/\bout\s+of\s+stock\b/i.test(stockText)) {
                            stockHtml = `<span style="color:#dc3545;font-weight:600;" title="${tip}">OOS</span>`;
                        } else if (/\bin\s+stock\b/i.test(stockText)) {
                            stockHtml = `<span style="color:#28a745;font-weight:600;" title="${tip}">In Stock</span>`;
                        } else {
                            const short = stockText.length > 18 ? stockText.substring(0, 18) + '…' : stockText;
                            stockHtml = `<span style="font-size:10px;" title="${tip}">${escAttr(short)}</span>`;
                        }
                    }
                    
                    html += `
                        <tr class="${rowClass}">
                            <td class="text-center"><strong>${index + 1}</strong></td>
                            <td class="text-center">${imageHtml}</td>
                            <td>
                                <span class="text-primary" style="font-weight: 600; font-size: 11px;">${item.asin || 'N/A'}</span>
                            </td>
                            <td class="text-center" style="width: 44px;">${titleCell}</td>
                            <td class="text-center" style="width: 44px;">${sellerCell}</td>
                            <td><strong>${priceBadge}</strong></td>
                            <td class="text-center fw-bold lmp-sp-cell">${modalSpText}</td>
                            <td class="text-center lmp-groi-cell">${modalGroiHtml}</td>
                            <td class="text-center lmp-nroi-cell">${modalNroiHtml}</td>
                            <td class="text-center">${revenue}</td>
                            <td class="text-center">${units}</td>
                            <td style="font-size: 11px;">${buyBox}</td>
                            <td class="text-center">${sellerType}</td>
                             <td class="text-center">${rating}</td>
                            <td class="text-center">${reviews}</td>
                            <td class="text-center">${deliveryHtml}</td>
                            <td class="text-center">${stockHtml}</td>
                            <td class="text-center">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on Amz">
                                    <i class="fa fa-external-link"></i>
                                </a>
                            </td>
                            <td class="text-center lmp-ignore-cell align-middle">${ignoreCb}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger delete-lmp-btn" 
                                    data-id="${item.id}" 
                                    data-asin="${item.asin}" 
                                    data-price="${item.price}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                if (l1ValForList !== null) {
                    html = `<div class="small text-muted mb-2">L1 (lowest non-ignored): <strong>$${Number(l1ValForList).toFixed(2)}</strong></div>` + html;
                }
                $('#lmpDataList').html(html);
            }

            // Live SP → GROI% / NROI% in modal; blur/Enter saves to grid SP column
            $(document).on('click', '#lmpModal .lmp-text-preview-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openLmpTextPreview($(this).attr('data-label'), $(this).attr('data-text'));
            });
            $(document).on('click', '#lmpTextPreviewClose, #lmpTextPreviewOverlay', function(e) {
                if (e.target === this || e.target.id === 'lmpTextPreviewClose') {
                    closeLmpTextPreview();
                }
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#lmpTextPreviewOverlay').hasClass('is-open')) {
                    closeLmpTextPreview();
                }
            });
            $('#lmpModal').on('hidden.bs.modal', function() {
                closeLmpTextPreview();
            });

            function applyAmazonLmpIgnoreLocal(id, ignored) {
                (currentLmpData.competitors || []).forEach(function(c) {
                    if (String(c.id) === String(id)) c.ignored = ignored ? 1 : 0;
                });
                const l1Info = amazonL1FromCompetitors(currentLmpData.competitors);
                currentLmpData.lowestPrice = l1Info.l1;
                renderCompetitorsList(currentLmpData.competitors, l1Info.l1);
                patchAmazonGridLmp(
                    l1Info.l1,
                    l1Info.winner ? (l1Info.winner.delivery || null) : null,
                    currentLmpData.competitors
                );
            }

            $(document).on('change', '#lmpModal .lmp-ignore-cb', function() {
                const $cb = $(this);
                const id = $cb.attr('data-id') || $cb.data('id');
                const sku = $cb.attr('data-sku') || $cb.data('sku') || currentLmpData.sku || '';
                const ignored = $cb.is(':checked');
                applyAmazonLmpIgnoreLocal(id, ignored);
                $('#lmpModal .lmp-ignore-cb[data-id="' + id + '"]').prop('disabled', true);

                $.ajax({
                    url: '/amazon/lmp/ignore',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: {
                        id: id,
                        sku: sku,
                        ignored: ignored ? 1 : 0
                    },
                    success: function(res) {
                        $('#lmpModal .lmp-ignore-cb[data-id="' + id + '"]').prop('disabled', false);
                        if (res && res.success) {
                            showToast(res.message || (ignored ? 'Ignored for L1' : 'Included in L1'), 'success');
                        } else {
                            applyAmazonLmpIgnoreLocal(id, !ignored);
                            showToast((res && res.error) || 'Failed to update ignore', 'error');
                        }
                    },
                    error: function(xhr) {
                        applyAmazonLmpIgnoreLocal(id, !ignored);
                        const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update ignore';
                        showToast(msg, 'error');
                    }
                });
            });

            $(document).on('input', '#lmpModalSpInput', function() {
                refreshLmpModalSpMetrics();
            });
            $(document).on('keydown', '#lmpModalSpInput', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).blur();
                }
            });
            $(document).on('blur', '#lmpModalSpInput', function() {
                refreshLmpModalSpMetrics();
                saveLmpModalSpToGrid();
            });

            // View Competitors Modal Event Listener
            $(document).on('click', '.view-lmp-competitors', function(e) {
                e.preventDefault();
                const sku = $(this).data('sku') || $(this).attr('data-sku');
                let linkedSkus = $(this).data('linked-skus') || $(this).attr('data-linked-skus') || [];
                if (typeof linkedSkus === 'string') {
                    try { linkedSkus = JSON.parse(linkedSkus) || []; } catch (err) { linkedSkus = []; }
                }
                if (!Array.isArray(linkedSkus) || !linkedSkus.length) {
                    const row = getAmazonTabulatorRowDataBySku(sku);
                    if (row && Array.isArray(row.linked_lmp_skus)) {
                        linkedSkus = row.linked_lmp_skus;
                    }
                }
                loadCompetitorsModal(sku, linkedSkus);
            });

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

            // Add New Competitor Form Submit
            $('#addCompetitorForm').on('submit', function(e) {
                e.preventDefault();
                
                const sku = $('#addCompSku').val();
                const asin = $('#addCompAsin').val().trim();
                const price = parseFloat($('#addCompPrice').val());
                const link = $('#addCompLink').val().trim();
                const marketplace = 'US';
                
                // Validation
                if (!asin) {
                    showToast('error', 'ASIN is required');
                    return;
                }
                
                if (!price || price <= 0) {
                    showToast('error', 'Valid price is required');
                    return;
                }
                
                const $submitBtn = $(this).find('button[type="submit"]');
                const originalHtml = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
                
                $.ajax({
                    url: '/amazon/lmp/add',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        asin: asin,
                        price: price,
                        product_link: link || null,
                        product_title: null,
                        marketplace: marketplace
                    },
                    success: function(response) {
                        showToast('success', 'Competitor added successfully');
                        $submitBtn.prop('disabled', false).html(originalHtml);
                        
                        // Clear form
                        $('#addCompAsin').val('');
                        $('#addCompPrice').val('');
                        $('#addCompLink').val('');
                        
                        // Reload table to show updated LMP
                        if (table) {
                            table.replaceData();
                        }
                        
                        // Reload modal to show updated list
                        loadCompetitorsModal(sku);
                    },
                    error: function(xhr) {
                        $submitBtn.prop('disabled', false).html(originalHtml);
                        
                        let errorMsg = 'Failed to add competitor';
                        
                        // Handle 409 Conflict (duplicate entry)
                        if (xhr.status === 409) {
                            errorMsg = '⚠️ This ASIN is already saved for this SKU';
                        } else if (xhr.responseJSON?.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON?.messages) {
                            errorMsg = Object.values(xhr.responseJSON.messages).flat().join(', ');
                        }
                        
                        showToast('error', errorMsg);
                        console.error('Error adding competitor:', xhr);
                    }
                });
            });

            // Delete LMP Button Click
            $(document).on('click', '.delete-lmp-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const id = $btn.data('id');
                const asin = $btn.data('asin');
                const price = $btn.data('price');
                
                if (!id) {
                    showToast('error', 'Invalid competitor ID');
                    return;
                }
                
                if (!confirm(`Delete competitor ${asin} ($${price}) from tracking?`)) {
                    return;
                }
                
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: '/amazon/lmp/delete',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        _method: 'DELETE',
                        id: id
                    },
                    success: function(response) {
                        showToast('success', 'Competitor deleted successfully');
                        
                        // Reload table to show updated LMP
                        if (table) {
                            table.replaceData();
                        }
                        
                        // Reload modal to show updated list
                        loadCompetitorsModal(currentLmpData.sku);
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html(originalHtml);
                        
                        const errorMsg = xhr.responseJSON?.error || 'Failed to delete competitor';
                        showToast('error', errorMsg);
                        console.error('Error deleting LMP:', xhr);
                    }
                });
            });
        });

        // Scout Modal Event Listener
        $(document).on('click', '.scout-link', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            let data = $(this).data('scout-data');

            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                openScoutModal(sku, data);
            } catch (error) {
                console.error('Error parsing Scout data:', error);
                alert('Error loading Scout data');
            }
        });

        // Scout Modal Function
        function openScoutModal(sku, data) {
            $('#scoutSku').text(sku);
            let html = '';
            data.forEach(item => {
                html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price:</strong> $${item.price || 'N/A'}<br>
                    <strong>Sales:</strong> ${item.sales || 'N/A'}<br>
                    <strong>Revenue:</strong> $${item.revenue || 'N/A'}
                </div>`;
            });
            $('#scoutDataList').html(html);
            $('#scoutModal').modal('show');
        }

    </script>
    
    <!-- Campaign Details Modal -->
    <!-- Parent row: child SKU pricing breakdown (KW / all sections) -->
    <div class="modal fade" id="parentPricingBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-2 px-3">
                    <h6 class="modal-title mb-0">
                        <i class="fas fa-eye me-1"></i> Child pricing — <span id="parent-pricing-modal-title"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-striped mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Child SKU</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Views L30</th>
                                    <th class="text-end">OV L30</th>
                                    <th class="text-end">A L30</th>
                                    <th class="text-end">CVR L30</th>
                                    <th class="text-end">GPFT %</th>
                                    <th class="text-end">NPFT %</th>
                                    <th class="text-end">GROI %</th>
                                    <th class="text-end">LMP</th>
                                    <th class="text-end">S PRC</th>
                                    <th class="text-center">Push</th>
                                    <th class="text-end">SNROI %</th>
                                    <th class="text-end">SNPFT %</th>
                                </tr>
                            </thead>
                            <tbody id="parent-pricing-breakdown-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Table export (filtered rows)
        $('#section-export-btn').on('click', function(e) {
            e.preventDefault();
            if (!table) {
                alert('Table not loaded');
                return;
            }
            
            const sectionLabel = 'All';
            
            // Get filtered data
            const data = table.getData("active");
            
            if (data.length === 0) {
                alert('No data to export');
                return;
            }
            
            const columnsToExport = [
                '(Child) sku', 'price', 'INV', 'L30', 'A_L30', 'GPFT%', 'GROI%', 'PFT%',
                'ROI_percentage', 'NRL', 'NRA', 'amz_avg_rating', 'amz_review_count', 'lmp_price'
            ];
            
            // Build CSV
            let csv = '';
            
            // Header row
            csv += columnsToExport.join(',') + '\n';
            
            // Data rows
            data.forEach(row => {
                const values = columnsToExport.map(col => {
                    let value = row[col];
                    if (value === null || value === undefined) value = '';
                    // Escape commas and quotes
                    value = String(value).replace(/"/g, '""');
                    if (String(value).includes(',')) {
                        value = '"' + value + '"';
                    }
                    return value;
                });
                csv += values.join(',') + '\n';
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Amazon_' + sectionLabel + '_Export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showToast('success', 'Exported ' + data.length + ' rows');
        });

        // Export LMP — flatten all competitor entries for every SKU into one CSV
        $('#export-lmp-btn').on('click', function(e) {
            e.preventDefault();
            if (!table) {
                alert('Table not loaded');
                return;
            }

            const allRows = table.getData();
            const lmpRows = [];

            allRows.forEach(function(row) {
                if (row.is_parent_summary) return;
                const sku = row['(Child) sku'] || '';
                const currentPrice = row.price || '';
                const entries = row.lmp_entries || [];

                if (entries.length === 0) {
                    // Include SKU with no competitors so every SKU is represented
                    lmpRows.push({
                        sku: sku,
                        current_price: currentPrice,
                        lmp_lowest: row.lmp_price || '',
                        comp_asin: '',
                        comp_title: '',
                        comp_price: '',
                        comp_seller: '',
                        comp_rating: '',
                        comp_reviews: '',
                        comp_monthly_revenue: '',
                        comp_monthly_units: '',
                        comp_buy_box_owner: '',
                        comp_seller_type: '',
                        comp_link: ''
                    });
                } else {
                    entries.forEach(function(comp) {
                        lmpRows.push({
                            sku: sku,
                            current_price: currentPrice,
                            lmp_lowest: row.lmp_price || '',
                            comp_asin: comp.asin || '',
                            comp_title: comp.title || comp.product_title || '',
                            comp_price: comp.price !== null && comp.price !== undefined ? comp.price : '',
                            comp_seller: comp.seller_name || '',
                            comp_rating: comp.rating !== null && comp.rating !== undefined ? comp.rating : '',
                            comp_reviews: comp.reviews !== null && comp.reviews !== undefined ? comp.reviews : '',
                            comp_monthly_revenue: comp.monthly_revenue !== null && comp.monthly_revenue !== undefined ? comp.monthly_revenue : '',
                            comp_monthly_units: comp.monthly_units_sold !== null && comp.monthly_units_sold !== undefined ? comp.monthly_units_sold : '',
                            comp_buy_box_owner: comp.buy_box_owner || '',
                            comp_seller_type: comp.seller_type || '',
                            comp_link: comp.link || comp.product_link || ''
                        });
                    });
                }
            });

            if (lmpRows.length === 0) {
                alert('No LMP data to export');
                return;
            }

            const headers = [
                'SKU', 'Current Price', 'LMP Lowest', 'Comp ASIN', 'Comp Title',
                'Comp Price', 'Comp Seller', 'Rating', 'Reviews',
                'Monthly Revenue', 'Monthly Units', 'Buy Box Owner', 'Seller Type', 'Link'
            ];
            const fields = [
                'sku', 'current_price', 'lmp_lowest', 'comp_asin', 'comp_title',
                'comp_price', 'comp_seller', 'comp_rating', 'comp_reviews',
                'comp_monthly_revenue', 'comp_monthly_units', 'comp_buy_box_owner', 'comp_seller_type', 'comp_link'
            ];

            function escapeCsvCell(val) {
                val = String(val === null || val === undefined ? '' : val);
                val = val.replace(/"/g, '""');
                if (val.includes(',') || val.includes('"') || val.includes('\n')) {
                    val = '"' + val + '"';
                }
                return val;
            }

            let csv = headers.map(escapeCsvCell).join(',') + '\n';
            lmpRows.forEach(function(r) {
                csv += fields.map(function(f) { return escapeCsvCell(r[f]); }).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Amazon_LMP_Export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showToast('success', 'Exported LMP data for ' + lmpRows.length + ' competitor rows');
        });
    </script>
@endsection
