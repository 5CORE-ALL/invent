@extends('layouts.vertical', ['title' => 'Ebay 3 - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Image column hover preview (forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

        /* Column visibility — 4 groups (Basics / Pricing / Advertisement / Others) */
        #column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #column-dropdown-menu > li.col-vis-full { list-style: none; }
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

        /* LMP modal: full-viewport backdrop (avoid black gaps behind modal) */
        #lmpModal {
            z-index: 1060 !important;
        }

        body.modal-open .modal-backdrop {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical column headers */
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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
        }
        
        /* eBay3 specific styling - purple accent */
        .badge.bg-ebay3 {
            background-color: #6f42c1 !important;
        }

        /* Frozen columns need solid background to prevent overlap on horizontal scroll */
        .tabulator .tabulator-header .tabulator-frozen {
            background-color: #00d5d5 !important;
            z-index: 11 !important;
        }
        .tabulator-row .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        .tabulator .tabulator-footer .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        
        /* PARENT row light yellow background */
        .tabulator-row.parent-row {
            background-color: #fffef2 !important;
        }
        .tabulator-row.parent-row .tabulator-frozen {
            background-color: #fffef2 !important;
        }
        .tabulator-row.parent-row:hover {
            background-color: #fefce8 !important;
        }
        .tabulator-row.parent-row:hover .tabulator-frozen {
            background-color: #fefce8 !important;
        }

        /* Hide tree + / − glyphs and box styling; keep control clickable to expand/collapse */
        #ebay3-table .tabulator-data-tree-control {
            background: transparent !important;
            border: none !important;
        }
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-expand,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-expand::after,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-collapse,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-collapse::after {
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }

        .ebay3-variation-dot:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }

        /* Badges above the filter controls + compact filter dropdowns (matches eBay 1 & 2) */
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #ebay3-filter-bar .form-select {
            width: auto !important;
            max-width: 140px;
            padding-right: 1.35rem !important;
            padding-left: 0.5rem !important;
            background-position: right 0.35rem center !important;
        }
        #ebay3-filter-bar { gap: 8px 10px !important; }

        /* Match Ebay 2 summary badge row: single row, shared width, scaled text */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 1 1 0;
            min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }
        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }

        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }
        .status-circle.default { background-color: #6c757d; }
        .status-circle.red { background-color: #dc3545; }
        .status-circle.yellow { background-color: #ffc107; }
        .status-circle.green { background-color: #28a745; }
        .status-circle.pink { background-color: #e83e8c; }
        .status-circle.blue { background-color: #0d6efd; }

        .manual-dropdown-container.pricing-filter-item {
            position: relative;
            display: inline-block;
        }
        .manual-dropdown-container.pricing-filter-item .dropdown-menu {
            display: none;
        }
        .manual-dropdown-container.pricing-filter-item.show .dropdown-menu {
            display: block;
        }
        .manual-dropdown-container.pricing-filter-item .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #ebay3MetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #ebay3MetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #ebay3MetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        #spriceCvrHistoryModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #skuMetricsModal .modal-dialog,
        #amzMetricChartModal .modal-dialog,
        #spriceCvrHistoryModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #skuMetricsModal .modal-content,
        #amzMetricChartModal .modal-content,
        #spriceCvrHistoryModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        .sprice-cvr-history-btn {
            border: none;
            background: transparent;
            color: #0d6efd;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
        }
        .sprice-cvr-history-btn:hover { color: #0a58ca; }

        /* Sprice × CVR rule modal — fullscreen, one-screen grid */
        #spriceCvrRuleModal.modal {
            --tz-modal-width: 100vw;
            --tz-modal-margin: 0;
            padding: 0 !important;
        }
        #spriceCvrRuleModal .modal-dialog {
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
        }
        #spriceCvrRuleModal .modal-content {
            height: 100vh;
            max-height: 100vh;
            border: 0;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }
        #spriceCvrRuleModal .modal-header,
        #spriceCvrRuleModal .modal-footer {
            flex: 0 0 auto;
        }
        /* Header metric badges ~2× default badge size; click opens rolling history */
        #spriceCvrRuleModal .sprice-cvr-header-metric-badge {
            font-size: 1.5rem;
            line-height: 1.2;
            padding: 0.45rem 0.9rem;
            cursor: pointer;
            font-weight: 700;
            color: #fff !important;
            border: none;
        }
        #spriceCvrRuleModal .sprice-cvr-header-metric-badge.sprice-cvr-badge-ok {
            background-color: #198754 !important;
        }
        #spriceCvrRuleModal .sprice-cvr-header-metric-badge.sprice-cvr-badge-bad {
            background-color: #dc3545 !important;
        }
        #spriceCvrRuleModal .sprice-cvr-trend-dot {
            display: inline-block;
            width: 0.55em;
            height: 0.55em;
            border-radius: 50%;
            margin-left: 0.35em;
            vertical-align: middle;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.55);
        }
        #spriceCvrRuleModal .sprice-cvr-trend-dot.up { background: #00ff88; }
        #spriceCvrRuleModal .sprice-cvr-trend-dot.down { background: #ff6b6b; }
        #spriceCvrRuleModal .sprice-cvr-trend-dot.flat { background: #adb5bd; }
        #spriceCvrRuleModal .sprice-cvr-trend-dot.none { display: none; }
        #spriceCvrRuleModal .modal-body.sprice-cvr-fs-body {
            overflow: hidden;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 0.5rem 0.75rem;
        }
        #spriceCvrRuleModal .sprice-cvr-toolbar {
            flex: 0 0 auto;
        }
        #spriceCvrRuleModal .sprice-cvr-fs-grid {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            grid-template-rows: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }
        #spriceCvrRuleModal .sprice-cvr-cell {
            min-height: 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        /* Rule settings on top · pie chart underneath (symmetrical cells) */
        #spriceCvrRuleModal .sprice-cvr-rule-pie-row {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            align-items: stretch;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-half {
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0;
            display: flex;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-half:has(.sprice-cvr-rule-pie-wrap) {
            flex: 0 0 28%;
            max-height: 30%;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-half > .border {
            width: 100%;
            overflow: auto;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-half:has(.sprice-cvr-rule-pie-wrap) > .border {
            overflow: hidden;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-pie-wrap {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            width: 100%;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-pie-wrap .sprice-cvr-rule-pie-canvas {
            position: relative;
            flex: 1 1 auto;
            min-height: 40px;
            width: 80%;
            max-width: 160px;
            margin: 0 auto;
        }
        #spriceCvrRuleModal .sprice-cvr-rule-pie-wrap .sprice-cvr-rule-pie-canvas canvas {
            max-height: 100%;
        }
        #spriceCvrRuleModal .sprice-cvr-summary-stack {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        #spriceCvrRuleModal .sprice-cvr-summary-stack > .border {
            flex: 1 1 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #spriceCvrRuleModal .sprice-cvr-summary-canvas {
            position: relative;
            flex: 1 1 auto;
            min-height: 80px;
        }
        #spriceCvrRuleModal .sprice-cvr-compact-legend {
            font-size: 0.68rem;
            line-height: 1.25;
            max-height: 2.6em;
            overflow: hidden;
        }
        /* Baby rule pies: full legend (counts + %) like summary pies — not clipped */
        #spriceCvrRuleModal .sprice-cvr-rule-pie-legend {
            font-size: 0.68rem;
            line-height: 1.3;
            max-height: none;
            overflow: visible;
            white-space: normal;
            word-break: break-word;
            padding: 0 2px;
        }
        #spriceCvrRuleModal .form-select-sm,
        #spriceCvrRuleModal .form-control-sm,
        #spriceCvrRuleModal .input-group-sm > .form-control,
        #spriceCvrRuleModal .input-group-sm > .input-group-text {
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.75rem;
        }
@media (max-width: 1200px) {
            #spriceCvrRuleModal .sprice-cvr-fs-grid {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: none;
                overflow-y: auto;
            }
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
        'page_title' => 'Ebay 3 - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex flex-column">
                <div class="d-flex align-items-center flex-wrap gap-2" id="ebay3-filter-bar">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 160px; display: inline-block;">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 160px; display: inline-block;">

                    <select id="view-mode-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="sku" selected>SKU Only</option>
                        <option value="parent">Parent Only</option>
                        <option value="both">Both (Parent + SKU)</option>
                    </select>

                    <select id="inv-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>E L30</option>
                        <option value="zero">0 E L30</option>
                        <option value="more">E L30 &gt; 0</option>
                    </select>

                    <select id="variation-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>Variation — All</option>
                        <option value="red">Variation — Red</option>
                        <option value="green">Variation — Green</option>
                    </select>


                    <!-- Pricing section filters -->
                    <select id="growth-sign-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="eBay E L30 vs E L60: (L30 − L60) / L60 × 100; L60=0 and L30&gt;0 counts as +100%">
                        <option value="all" selected>Growth</option>
                        <option value="negative">Negative Only</option>
                        <option value="zero">Zero Only</option>
                        <option value="positive">Positive Only</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex flex-row flex-nowrap align-items-center gap-1 pricing-filter-item" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block; flex-shrink: 0;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40plus">Above 40%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block; flex-shrink: 0;">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="cvr-trend-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="CVR L30 vs prior period L31–L60 (CVR L60)">
                        <option value="all">CVR trend</option>
                        <option value="down">Down</option>
                        <option value="up">Up</option>
                        <option value="same">Same</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">SPRICE</option>
                        <option value="blank">Blank SPRICE only</option>
                    </select>

                    <!-- DIL Filter (plain select — matches /amazon & eBay 1/2 dropdown UI) -->
                    <select id="dil-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="ebay3-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    <button id="clear-sprice-btn" type="button"
                        class="btn btn-sm btn-danger pricing-filter-item"
                        title="Clear SPRICE for selected SKUs (turn on Price % to select)">
                        <i class="fa fa-trash"></i> Clear SPRICE
                    </button>

                    <button type="button" id="export-section-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export current section (visible columns & filtered data)">
                        <i class="fas fa-file-export"></i> Export
                    </button>

                    {{-- Sbid (Views) — same as /ebay-tabulator-view + /ebay3/campaign-ads --}}
                    <button type="button" class="btn btn-sm pricing-filter-item"
                            style="border:1px solid #6610f2; color:#6610f2;"
                            data-bs-toggle="modal" data-bs-target="#sbidViewsRuleModal"
                            title="Configure Min/Max caps and the daily ±%/day step per L7 View colour for the S BID column">
                        <i class="fas fa-eye me-1"></i>Sbid (Views)
                    </button>

                    {{-- Sprice×CVR — CVR L30 vs L31–L60 + CVR bands; gear edits shared rule --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded"
                        id="sprice-cvr-controls"
                        style="background: #ffc107;"
                        title="Adjust SPRICE by % of price from CVR trend rules (not PFT%). Gear edits rule (shared).">
                        <button type="button" id="apply-sprice-cvr-btn"
                            class="btn btn-sm btn-warning border-0 py-0 px-2 fw-bold text-dark"
                            style="background: transparent;">
                            <i class="fas fa-percentage"></i> <span id="sprice-cvr-btn-label">Sprice×CVR</span>
                        </button>
                        <button type="button" id="open-sprice-cvr-modal-btn"
                            class="btn btn-sm border-0 py-0 px-1 text-dark"
                            style="background: transparent;"
                            data-bs-toggle="modal" data-bs-target="#spriceCvrRuleModal"
                            title="Edit Sprice × CVR trend rules (saved for everyone)">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>


                    {{-- Target ROI% bulk control — back-solves SPRICE so SGROI = Target (gross, no Ads%). --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target SGROI% — sets SPRICE so S GROI column = Target (gross ROI: fees + shipping, no Ads%)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target SGROI% applied to all selected rows when you click 'Apply SPRICE'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE so SGROI equals Target for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves SPRICE for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target SGPFT% — sets SPRICE = (LP + Ship) / (0.85 − Target GPFT%/100) on every selected row">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target SGPFT% applied to all selected rows when you click 'Apply SPRICE'. Must be less than the eBay3 take-home margin (< 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE so SGPFT equals Target for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <!-- Play / Pause parent navigation (like pricing-master-cvr) -->
                    <div class="btn-group align-items-center ms-2 pricing-filter-item" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play — parents low → high by CVR 30 (SCVR)">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause - show all">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                </div>

                <!-- Summary Stats — same badge set/order as Ebay 2 Analytics -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="ebay2-summary-badge-row">
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge ebay3-hover-chart" data-filter="zero" data-metric="zero_sold_count" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for daily trend">0 Sold: <span id="zero-sold-count">0</span></span>
                        <span class="badge fs-6 p-2 sold-filter-badge ebay3-hover-chart" data-filter="sold" data-metric="sold_count" style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;" title="Click to filter · Hover for daily trend">&gt; 0 Sold: <span id="more-sold-count">0</span></span>
                        <span class="badge bg-success fs-6 p-2 d-none ebay3-badge-chart ebay3-hover-chart" id="total-pft-amt-badge" data-metric="total_pft_amt" style="color: black; font-weight: bold; cursor: pointer;" aria-hidden="true" title="View trend">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="total-sales-amt-badge" data-metric="total_sales_amt" style="color: black; font-weight: bold; cursor: pointer;" title="View trend">Sales: $0</span>
                        <span class="badge fs-6 p-2" id="qty-sold-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="L30 units sold (Σ real ebay3 order quantity, excl. cancelled &amp; fully-refunded). Same value /ebay3/daily-sales shows.">Qty: {{ number_format((int) ($ordersL30TotalQty ?? 0)) }}</span>
                        <span class="badge bg-info fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="avg-gpft-badge" data-metric="gpft_percent" style="color: black; font-weight: bold; cursor: pointer;" title="View trend">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="groi-percent-badge" data-metric="groi_percent" style="color: white; font-weight: bold; cursor: pointer;" title="View trend">GROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percent-badge" style="background-color: #d63384; color: white; font-weight: bold;" title="TACOS = eBay 3 channel Total Ad Spend (31-day KW + PMT from ebay_3_priority_reports + ebay_3_general_reports — same source as /ebay3/campaign-ads) ÷ real-orders L30 Sales × 100.">Ads: {{ number_format((float) ($channelAdsPercent ?? 0), 1) }}%</span>
                        <span class="badge fs-6 p-2" id="npft-percent-badge" style="background-color: #0f766e; color: white; font-weight: bold;" title="NPFT% = GPFT% − Ads% (net profit margin after ad spend).">NPFT: {{ round((float) ($ordersL30Gpft ?? 0) - (float) ($channelAdsPercent ?? 0)) }}%</span>
                        <span class="badge fs-6 p-2" id="nroi-percent-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same as Amazon (do not cut Ads% from GROI%).">NROI: {{ round((float) ($ordersL30Nroi ?? 0)) }}%</span>
                        <span class="badge bg-warning fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="avg-price-badge" data-metric="avg_price" style="color: black; font-weight: bold; cursor: pointer;" title="View trend">Prc: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="avg-cvr-badge" data-metric="cvr_percent" style="color: white; font-weight: bold; cursor: pointer;" title="CVR = (real-orders L30 units sold / Σ Views) × 100. Numerator is the orders-API L30 units (same source /ebay3/daily-sales uses), denominator is Σ views across rows with E Stock > 0. Click for trend.">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2 ebay3-badge-chart ebay3-hover-chart" id="total-views-badge" data-metric="total_views" style="color: black; font-weight: bold; cursor: pointer;" title="View trend">Views: 0</span>
                        <span class="badge fs-6 p-2" id="avg-l7-views-badge" style="background-color: #6610f2; color: white; font-weight: bold;" title="Average L7 views across rows with E Stock &gt; 0 — drives L7 View colours and Sbid (Views)">L7: 0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none" id="total-inv-badge" style="color: black; font-weight: bold;" aria-hidden="true">E Stock: 0</span>
                        <span class="badge bg-danger fs-6 p-2 ebay3-hover-chart" id="missing-count-badge" data-metric="missing_count" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for daily trend">M L: 0</span>
                        <span class="badge bg-warning fs-6 p-2 ebay3-hover-chart" id="inv-stock-badge" data-metric="nmap_count" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter · Hover for daily trend">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="ebay3-discount-type-block" class="d-flex align-items-center gap-2">
                            <label class="mb-0 fw-bold">Type:</label>
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 130px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter percentage" step="0.01" min="0" style="width: 150px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div id="ebay3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body (scrollable section) -->
                    <div id="ebay3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> eBay3 Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fa fa-plus-circle"></i> Add New Competitor</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">eBay Item ID *</label>
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id" required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price" step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping" name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link" placeholder="https://ebay.com/itm/...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <label class="form-label">Product Title (optional)</label>
                                        <input type="text" class="form-control" id="addCompTitle" name="product_title" placeholder="Product title">
                                    </div>
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

    <!-- eBay 3 summary badge daily trend (same idea as amazon-tabulator-view badge chart) -->
    <div class="modal fade p-0" id="ebay3MetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="ebay3ChartModalTitle">eBay 3 — Metric trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="ebay3ChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                    <div id="ebay3ChartContainer" style="height: 22vh; display: none; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="ebay3MetricChart"></canvas>
                        </div>
                        <div id="ebay3ChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="ebay3ChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="ebay3ChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="ebay3ChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="ebay3ChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="ebay3ChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No daily snapshots yet. Open this page on separate days to build history (auto-saved from summary).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="ebay3EditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="ebay3EditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="ebay3SellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="ebay3BuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ebay3SaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sbid (Views) Modal — shared with /ebay3/campaign-ads (ebay_sbid_rules.key = ebay3_sbid_views). --}}
    <div class="modal fade" id="sbidViewsRuleModal" tabindex="-1" aria-labelledby="sbidViewsRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="sbidViewsRuleModalLabel">
                        <i class="fas fa-eye me-2" style="color:#6610f2;"></i>Sbid (Views)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        The <strong>S BID</strong> column adjusts each row's current <strong>C BID</strong> once per day based on
                        its <strong>L7 View</strong> colour (green = keep C Bid), then clamps the result between the Min and Max caps.
                        Same rule as <code>/ebay3/campaign-ads</code> and <code>ebay3:update-suggestedbid</code>.
                    </p>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label mb-1" for="sbid-views-min-cap">Min Cap %</label>
                            <input type="number" step="0.1" id="sbid-views-min-cap" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1" for="sbid-views-max-cap">Max Cap %</label>
                            <input type="number" step="0.1" id="sbid-views-max-cap" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="border rounded p-2 mb-3 bg-light">
                        <div class="small fw-bold mb-1">Do not decrease when E L30 sold is low</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label mb-1 small" for="sbid-views-no-dec-max-el30">
                                    If E L30 sold ≤
                                </label>
                                <input type="number" step="1" min="0" id="sbid-views-no-dec-max-el30"
                                       class="form-control form-control-sm" style="width: 88px;"
                                       title="When eBay L30 units sold is at or below this qty, Decrease steps are skipped (bid stays at C Bid).">
                            </div>
                            <div class="col">
                                <div class="small text-muted pb-1">
                                    then <strong>do not decrease</strong> bid (Increase / No change still apply).
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded p-2">
                        <div class="small fw-bold mb-2">Daily action per L7 View colour (direction + %/day)</div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label mb-1">
                                    <span style="color:#d63384; font-weight:700;">Pink</span> (high views)
                                </label>
                                <select id="sbid-views-pink-dir" class="form-select form-select-sm mb-1">
                                    <option value="dec">Decrease</option>
                                    <option value="inc">Increase</option>
                                    <option value="none">No change</option>
                                </select>
                                <input type="number" step="0.1" id="sbid-views-pink-step" class="form-control form-control-sm"
                                       title="Points/day to apply for Pink L7 (≥ 2× avg)">
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1">
                                    <span style="color:#28a745; font-weight:700;">Green</span> (mid views)
                                </label>
                                <select id="sbid-views-green-dir" class="form-select form-select-sm mb-1">
                                    <option value="none">No change</option>
                                    <option value="inc">Increase</option>
                                    <option value="dec">Decrease</option>
                                </select>
                                <input type="number" step="0.1" id="sbid-views-green-step" class="form-control form-control-sm"
                                       title="Points/day to apply for Green L7 (avg..2× avg)">
                            </div>
                            <div class="col-4">
                                <label class="form-label mb-1">
                                    <span style="color:#a00211; font-weight:700;">Red</span> (low views)
                                </label>
                                <select id="sbid-views-red-dir" class="form-select form-select-sm mb-1">
                                    <option value="inc">Increase</option>
                                    <option value="dec">Decrease</option>
                                    <option value="none">No change</option>
                                </select>
                                <input type="number" step="0.1" id="sbid-views-red-step" class="form-control form-control-sm"
                                       title="Points/day to apply for Red L7 (< avg)">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sbid-views-save-btn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="spriceCvrRuleModal" tabindex="-1" aria-labelledby="spriceCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#ffc107;">
                    <h5 class="modal-title text-dark d-flex align-items-center flex-wrap gap-2 mb-0" id="spriceCvrRuleModalLabel">
                        <span><i class="fas fa-percentage me-2"></i>Sprice × CVR Rule</span>
                        <span class="badge sprice-cvr-header-metric-badge ebay3-badge-chart sprice-cvr-badge-bad"
                            id="sprice-cvr-groi-badge" data-metric="groi_pct" data-threshold="80"
                            title="GROI% — green if ≥80%. Click for rolling history">
                            GROI: <span id="sprice-cvr-groi-val">0%</span>
                            <span class="sprice-cvr-trend-dot none" id="sprice-cvr-groi-trend" aria-hidden="true"></span>
                        </span>
                        <span class="badge sprice-cvr-header-metric-badge ebay3-badge-chart sprice-cvr-badge-bad"
                            id="sprice-cvr-nroi-badge" data-metric="nroi_pct" data-threshold="60"
                            title="NROI% — green if ≥60%. Click for rolling history">
                            NROI: <span id="sprice-cvr-nroi-val">0%</span>
                            <span class="sprice-cvr-trend-dot none" id="sprice-cvr-nroi-trend" aria-hidden="true"></span>
                        </span>
                        <span class="badge sprice-cvr-header-metric-badge ebay3-badge-chart sprice-cvr-badge-bad"
                            id="sprice-cvr-gpft-badge" data-metric="gpft_pct" data-threshold="35"
                            title="GPFT% — green if ≥35%. Click for rolling history">
                            GPFT: <span id="sprice-cvr-gpft-val">0%</span>
                            <span class="sprice-cvr-trend-dot none" id="sprice-cvr-gpft-trend" aria-hidden="true"></span>
                        </span>
                        <span class="badge sprice-cvr-header-metric-badge ebay3-badge-chart sprice-cvr-badge-bad"
                            id="sprice-cvr-npft-badge" data-metric="npft_pct" data-threshold="25"
                            title="NPFT% — green if ≥25%. Click for rolling history">
                            NPFT: <span id="sprice-cvr-npft-val">0%</span>
                            <span class="sprice-cvr-trend-dot none" id="sprice-cvr-npft-trend" aria-hidden="true"></span>
                        </span>
                        <span class="text-muted small d-none d-xl-inline">SPRICE by CVR L30 vs L31–L60 + band · shared Amazon + eBay</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body sprice-cvr-fs-body">
                    <div class="sprice-cvr-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="fw-bold">5 CVR Rules</span>
                            {{-- Yellow≤ / Blue≤ / Green≤ kept as hidden state (bands shown on each rule card) --}}
                            <input type="hidden" id="sprice-cvr-low-input" value="3.5">
                            <input type="hidden" id="sprice-cvr-mid-input" value="7">
                            <input type="hidden" id="sprice-cvr-high-input" value="13">
                            {{-- roi_floor kept for stored rule compat; CVR % adjusts PRICE only (not GROI/PFT) --}}
                            <input type="hidden" id="sprice-cvr-roi-floor-input" value="40">
                        </div>
                        <div id="sprice-cvr-modal-status" class="small text-muted"></div>
                    </div>

                    <div class="sprice-cvr-fs-grid">
                        {{-- Rule 1 Red — CVR trend signed % of price --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-rule-pie-row" data-rule-pie="zero">
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-2 h-100" style="background:#fde8ea; border-left:4px solid #a00211 !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-white" style="background:#a00211;">1 · Red</span>
                                            <span class="fw-bold small">CVR = 0%</span>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Down</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-zero-down-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="zero" data-trend="down" value="-1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Same</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-zero-equal-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="zero" data-trend="equal" value="-1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Up</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-zero-up-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="zero" data-trend="up" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-1 h-100 sprice-cvr-rule-pie-wrap" style="background:#fff5f5; border-left:4px solid #a00211 !important;">
                                        <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <div class="fw-bold small mb-0">Rule 1 · by trend</div>
                                            <button type="button" class="sprice-cvr-history-btn" data-history-type="rule_zero" title="View rolling history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted text-center sprice-cvr-rule-pie-legend mb-1" id="sprice-cvr-zero-pie-legend">—</div>
                                        <div class="sprice-cvr-rule-pie-canvas" data-pie-canvas="zero">
                                            <canvas id="sprice-cvr-zero-pie-chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rule 2 Yellow --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-rule-pie-row" data-rule-pie="yellow">
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-2 h-100" style="background:#fff8e1; border-left:4px solid #e6b800 !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-dark" style="background:#ffc107;">2 · Yellow</span>
                                            <span class="fw-bold small" id="sprice-cvr-slab-red-label">0.01 – 3.5%</span>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Down</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-yellow-down-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="red" data-trend="down" value="-2" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Same</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-yellow-equal-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="red" data-trend="equal" value="-1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Up</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-yellow-up-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="red" data-trend="up" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-1 h-100 sprice-cvr-rule-pie-wrap" style="background:#fffde7; border-left:4px solid #e6b800 !important;">
                                        <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <div class="fw-bold small mb-0">Rule 2 · by trend</div>
                                            <button type="button" class="sprice-cvr-history-btn" data-history-type="rule_yellow" title="View rolling history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted text-center sprice-cvr-rule-pie-legend mb-1" id="sprice-cvr-yellow-pie-legend">—</div>
                                        <div class="sprice-cvr-rule-pie-canvas" data-pie-canvas="yellow">
                                            <canvas id="sprice-cvr-yellow-pie-chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Summary pies --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-summary-stack">
                                <div class="border rounded p-1">
                                    <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                        <div class="fw-bold small mb-0">SKU by CVR slab</div>
                                        <button type="button" class="sprice-cvr-history-btn" data-history-type="slab" title="View rolling history">
                                            <i class="fas fa-history"></i>
                                        </button>
                                    </div>
                                    <div class="text-muted text-center sprice-cvr-compact-legend mb-1" id="sprice-cvr-pie-legend">—</div>
                                    <div class="sprice-cvr-summary-canvas">
                                        <canvas id="sprice-cvr-pie-chart"></canvas>
                                    </div>
                                </div>
                                <div class="border rounded p-1">
                                    <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                        <div class="fw-bold small mb-0">SKU action</div>
                                        <button type="button" class="sprice-cvr-history-btn" data-history-type="action" title="View rolling history">
                                            <i class="fas fa-history"></i>
                                        </button>
                                    </div>
                                    <div class="text-muted text-center sprice-cvr-compact-legend mb-1" id="sprice-cvr-action-pie-legend">—</div>
                                    <div class="sprice-cvr-summary-canvas">
                                        <canvas id="sprice-cvr-action-pie-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rule 3 Blue --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-rule-pie-row" data-rule-pie="blue">
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-2 h-100" style="background:#e7f1ff; border-left:4px solid #0d6efd !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-white" style="background:#0d6efd;">3 · Blue</span>
                                            <span class="fw-bold small" id="sprice-cvr-slab-blue-label">3.51 – 7%</span>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Down</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-blue-down-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="blue" data-trend="down" value="-1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Same</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-blue-equal-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="blue" data-trend="equal" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Up</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-blue-up-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="blue" data-trend="up" value="1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-1 h-100 sprice-cvr-rule-pie-wrap" style="background:#f0f6ff; border-left:4px solid #0d6efd !important;">
                                        <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <div class="fw-bold small mb-0">Rule 3 · by trend</div>
                                            <button type="button" class="sprice-cvr-history-btn" data-history-type="rule_blue" title="View rolling history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted text-center sprice-cvr-rule-pie-legend mb-1" id="sprice-cvr-blue-pie-legend">—</div>
                                        <div class="sprice-cvr-rule-pie-canvas" data-pie-canvas="blue">
                                            <canvas id="sprice-cvr-blue-pie-chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rule 4 Green --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-rule-pie-row" data-rule-pie="green">
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-2 h-100" style="background:#e8f8ee; border-left:4px solid #28a745 !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-white" style="background:#28a745;">4 · Green</span>
                                            <span class="fw-bold small" id="sprice-cvr-slab-green-label">7.01 – 13%</span>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Down</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-green-down-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="green" data-trend="down" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Same</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-green-equal-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="green" data-trend="equal" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Up</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-green-up-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="green" data-trend="up" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-1 h-100 sprice-cvr-rule-pie-wrap" style="background:#f1faf4; border-left:4px solid #28a745 !important;">
                                        <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <div class="fw-bold small mb-0">Rule 4 · by trend</div>
                                            <button type="button" class="sprice-cvr-history-btn" data-history-type="rule_green" title="View rolling history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted text-center sprice-cvr-rule-pie-legend mb-1" id="sprice-cvr-green-pie-legend">—</div>
                                        <div class="sprice-cvr-rule-pie-canvas" data-pie-canvas="green">
                                            <canvas id="sprice-cvr-green-pie-chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Rule 5 Pink --}}
                        <div class="sprice-cvr-cell">
                            <div class="sprice-cvr-rule-pie-row" data-rule-pie="pink">
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-2 h-100" style="background:#fce8f3; border-left:4px solid #e83e8c !important;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-white" style="background:#e83e8c;">5 · Pink</span>
                                            <span class="fw-bold small" id="sprice-cvr-slab-pink-label">&gt; 13.01%</span>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Down</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-pink-down-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="pink" data-trend="down" value="0" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Same</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-pink-equal-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="pink" data-trend="equal" value="-1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-1 small align-items-center mb-1">
                                            <div class="col-4 text-nowrap"><strong>Up</strong> <span class="text-muted fw-normal">→ price</span></div>
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" id="sprice-cvr-pink-up-pct" class="form-control text-end sprice-cvr-slab-pct-input" data-slab="pink" data-trend="up" value="1" step="0.1" min="-50" max="50" title="Adjust eBay price by this %: +N raise, −N lower, 0 = hold at eBay price (0% change, of price not PFT%)">
                                                    <span class="input-group-text">% price</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="sprice-cvr-rule-half">
                                    <div class="border rounded p-1 h-100 sprice-cvr-rule-pie-wrap" style="background:#fff0f7; border-left:4px solid #e83e8c !important;">
                                        <div class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <div class="fw-bold small mb-0">Rule 5 · by trend</div>
                                            <button type="button" class="sprice-cvr-history-btn" data-history-type="rule_pink" title="View rolling history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                        <div class="text-muted text-center sprice-cvr-rule-pie-legend mb-1" id="sprice-cvr-pink-pie-legend">—</div>
                                        <div class="sprice-cvr-rule-pie-canvas" data-pie-canvas="pink">
                                            <canvas id="sprice-cvr-pink-pie-chart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="sprice-cvr-save-btn">
                        <i class="fas fa-save me-1"></i>Save Rules
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sprice × CVR pie history (rolling style, same as Amazon metric trend modals) -->
    <div class="modal fade p-0" id="spriceCvrHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-history me-1"></i>
                        <span id="spriceCvrHistoryModalTitle">Sprice × CVR — Sprice × CVR History</span>
                        <span id="spriceCvrHistoryModalSuffix">(Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sprice-cvr-history-days" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                <div class="modal-body p-2">
                    <div id="spriceCvrHistoryContainer" style="height: 22vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="spriceCvrHistoryChart"></canvas>
                        </div>
                        <div id="spriceCvrHistoryRefPanel" style="width: 110px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="spriceCvrHistoryHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="spriceCvrHistoryMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="spriceCvrHistoryLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="spriceCvrHistoryLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading history...</p>
                    </div>
                    <div id="spriceCvrHistoryNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No history yet. Open the Sprice × CVR modal (or Apply) to save today’s snapshot.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script-bottom')
<script>
    /** Stored in DB table channel_tabulator_column_settings (shared for all users). */
    const TABULATOR_COLUMN_CHANNEL = 'ebay3_tabulator';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    const KW_SPENT = {{ $kwSpent ?? 0 }};
    const PMT_SPENT = {{ $pmtSpent ?? 0 }};
    const TOTAL_ADS_SPENT = KW_SPENT + PMT_SPENT;
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();

    /** Average L7 views (rows with E Stock > 0) — drives L7 View colours + Sbid (Views). */
    let avgL7ViewsGlobal = 0;

    /** L7 View colour band: red < avg, green avg..2×avg, pink ≥ 2×avg. */
    function l7ViewBand(value) {
        const v = parseFloat(value) || 0;
        const avg = avgL7ViewsGlobal || 0;
        if (avg <= 0) return { key: '', color: '' };
        if (v < avg) return { key: 'red', color: '#a00211' };
        if (v < avg * 2) return { key: 'green', color: '#28a745' };
        return { key: 'pink', color: '#d63384' };
    }

    /** Sbid (Views) settings — shared with /ebay3/campaign-ads (ebay3_sbid_views). */
    function sbidViewsNum(key, fallback) {
        const v = parseFloat(localStorage.getItem(key));
        return isFinite(v) ? v : fallback;
    }
    function sbidViewsDir(key, fallback) {
        const v = localStorage.getItem(key);
        return (v === 'inc' || v === 'dec' || v === 'none') ? v : fallback;
    }
    let sbidViewsMinCap   = sbidViewsNum('ebay3_sbid_views_min_cap', 1);
    let sbidViewsMaxCap   = sbidViewsNum('ebay3_sbid_views_max_cap', 20);
    let sbidViewsPinkDir  = sbidViewsDir('ebay3_sbid_views_pink_dir', 'dec');
    let sbidViewsPinkStep = sbidViewsNum('ebay3_sbid_views_pink_step', 1);
    let sbidViewsGreenDir = sbidViewsDir('ebay3_sbid_views_green_dir', 'none');
    let sbidViewsGreenStep = sbidViewsNum('ebay3_sbid_views_green_step', 0);
    let sbidViewsRedDir   = sbidViewsDir('ebay3_sbid_views_red_dir', 'inc');
    let sbidViewsRedStep  = sbidViewsNum('ebay3_sbid_views_red_step', 1);
    let sbidViewsNoDecMaxEl30 = sbidViewsNum('ebay3_sbid_views_no_dec_max_el30', 0);

    function sbidViewsApplyStep(base, dir, step, el30Sold) {
        let d = dir;
        if (d === 'dec' && isFinite(el30Sold) && el30Sold <= sbidViewsNoDecMaxEl30) {
            d = 'none';
        }
        const s = isFinite(step) ? step : 0;
        if (d === 'inc') return base + s;
        if (d === 'dec') return base - s;
        return base;
    }

    /** Daily one-step adjustment of C BID by L7 View band, clamped to Min/Max. */
    function computeSbidViews(rowData) {
        const cbid = parseFloat(rowData.ca_bid_percentage);
        if (!isFinite(cbid) || cbid <= 0) {
            return { bid: 0, color: '#6c757d', skip: true };
        }
        const el30Sold = parseFloat(rowData['eBay L30']) || 0;
        const band = l7ViewBand(rowData.l7_views);
        let bid = cbid;
        if (band.key === 'pink') bid = sbidViewsApplyStep(cbid, sbidViewsPinkDir, sbidViewsPinkStep, el30Sold);
        else if (band.key === 'green') bid = sbidViewsApplyStep(cbid, sbidViewsGreenDir, sbidViewsGreenStep, el30Sold);
        else if (band.key === 'red') bid = sbidViewsApplyStep(cbid, sbidViewsRedDir, sbidViewsRedStep, el30Sold);

        const min = isFinite(sbidViewsMinCap) ? sbidViewsMinCap : -Infinity;
        const max = isFinite(sbidViewsMaxCap) ? sbidViewsMaxCap : Infinity;
        if (bid < min) bid = min;
        if (bid > max) bid = max;

        return { bid: bid, color: band.color || '#0d6efd', skip: false };
    }

    // Badge filter state variables
    let zeroSoldFilterActive = false;
    let moreSoldFilterActive = false;
    let missingFilterActive = false;
    let mapFilterActive = false;
    let invStockFilterActive = false;

    /** Daily snapshot badge chart (amazon_channel_summary_data, channel=ebay3) */
    const ebay3BadgeMetricLabels = {
        zero_sold_count: '0 Sold',
        sold_count: '> 0 Sold',
        total_pft_amt: 'Total PFT',
        total_sales_amt: 'Sales',
        total_spend_l30: 'Ad spend (KW+PMT)',
        gpft_percent: 'GPFT %',
        npft_percent: 'NPFT %',
        groi_percent: 'GROI %',
        nroi_percent: 'NROI %',
        tcos_percent: 'TACOS %',
        avg_price: 'Avg price',
        cvr_percent: 'CVR %',
        total_views: 'Views',
        missing_count: 'Missing',
        map_count: 'Map',
        nmap_count: 'N Map',
    };
    // L30 units sold from real ebay3 orders (same source /ebay3/daily-sales uses) — CVR numerator.
    const ORDERS_L30_TOTAL_QTY = {{ (int) ($ordersL30TotalQty ?? 0) }};
    // L30 Sales / GPFT% / GROI% from the same real orders /ebay3/daily-sales uses (fixed server values).
    const ORDERS_L30_TOTAL_SALES = {{ (float) ($ordersL30TotalSales ?? 0) }};
    const ORDERS_L30_GPFT = {{ (float) ($ordersL30Gpft ?? 0) }};
    const ORDERS_L30_GROI = {{ (float) ($ordersL30Groi ?? 0) }};
    const ORDERS_L30_PFT = {{ (float) ($ordersL30Pft ?? 0) }};
    const ORDERS_L30_COGS = {{ (float) ($ordersL30Cogs ?? 0) }};
    const EBAY3_AD_SPEND = {{ (float) ($ebayAdSpend ?? 0) }};
    const ORDERS_L30_NROI = {{ (float) ($ordersL30Nroi ?? 0) }};
    const EBAY3_CHANNEL_ADS_PCT = {{ (float) ($channelAdsPercent ?? 0) }};

    /**
     * Gross ROI — same formula as GROI% (ROI%) column:
     *   ((price × margin − Ship − LP) / LP) × 100
     * @param {object} rowData
     * @param {string} priceKey  'eBay Price' for GROI%, 'SPRICE' for S GROI
     */
    function ebay3ComputeGrossRoi(rowData, priceKey) {
        if (!rowData) return null;
        const price = parseFloat(rowData[priceKey]);
        const lp = parseFloat(rowData.LP_productmaster);
        if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
        const ship = parseFloat(rowData.Ship_productmaster) || 0;
        const marginRaw = parseFloat(rowData.percentage);
        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
        return ((price * margin - lp - ship) / lp) * 100;
    }

    /**
     * Net ROI — same shape as Amazon NROI / SNROI badge:
     *   (gross profit $ − ad spend $) / COGS × 100
     * where ad spend $ = price × Ads%/100 and COGS = LP.
     * @param {object} rowData
     * @param {string} priceKey  'eBay Price' for NROI, 'SPRICE' for SNROI
     */
    function ebay3ComputeNetRoi(rowData, priceKey) {
        if (!rowData) return null;
        const price = parseFloat(rowData[priceKey]);
        const lp = parseFloat(rowData.LP_productmaster);
        if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
        const ship = parseFloat(rowData.Ship_productmaster) || 0;
        const marginRaw = parseFloat(rowData.percentage);
        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
        const adsFrac = (parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0) / 100;
        const grossPft = (price * margin) - ship - lp;
        const adSpend = price * adsFrac;
        return ((grossPft - adSpend) / lp) * 100;
    }

    /** True when S PRC would show a distinct dollar amount (not blank / not "-"). */
    function ebay3HasDistinctSprice(rowData) {
        if (!rowData) return false;
        const sprice = parseFloat(rowData.SPRICE);
        if (!isFinite(sprice) || sprice <= 0) return false;
        const ebayPrice = parseFloat(rowData['eBay Price']) || 0;
        if (ebayPrice > 0 && ebayPrice.toFixed(2) === sprice.toFixed(2)) return false;
        return true;
    }
    const ebay3BadgeDollarMetrics = ['total_pft_amt', 'total_sales_amt', 'total_spend_l30', 'avg_price'];
    const ebay3BadgePctMetrics = ['gpft_percent', 'npft_percent', 'groi_percent', 'nroi_percent', 'tcos_percent', 'cvr_percent'];
    let ebay3ChartInstance = null;
    let ebay3ChartAjax = null;
    let ebay3ChartDays = 30;
    let ebay3ChartMetricKey = '';

    function ebay3FmtChartVal(v) {
        if (ebay3BadgeDollarMetrics.includes(ebay3ChartMetricKey)) {
            const n = Number(v);
            if (Number.isFinite(n) && Math.abs(n % 1) > 1e-9) {
                return '$' + n.toFixed(2);
            }
            return '$' + Math.round(n).toLocaleString('en-US');
        }
        if (ebay3BadgePctMetrics.includes(ebay3ChartMetricKey)) {
            return Number(v).toFixed(1) + '%';
        }
        return Math.round(Number(v)).toLocaleString('en-US');
    }

    function showEbay3MetricChart(metricKey) {
        ebay3ChartMetricKey = metricKey;
        ebay3ChartDays = 30;
        $('#ebay3ChartRangeSelect').val('30');
        const label = ebay3BadgeMetricLabels[metricKey] || metricKey;
        $('#ebay3ChartModalTitle').text('eBay 3 — ' + label + ' (Daily snapshot)');
        const modalEl = document.getElementById('ebay3MetricChartModal');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            $(modalEl).modal('show');
        }
        loadEbay3MetricChart();
    }

    function loadEbay3MetricChart() {
        if (ebay3ChartAjax) {
            ebay3ChartAjax.abort();
        }
        $('#ebay3ChartNoData').hide();
        $('#ebay3ChartContainer').hide();
        $('#ebay3ChartLoading').show();

        ebay3ChartAjax = $.ajax({
            url: '/ebay3-badge-chart-data',
            method: 'GET',
            data: { metric: ebay3ChartMetricKey, days: ebay3ChartDays },
            success: function(resp) {
                ebay3ChartAjax = null;
                $('#ebay3ChartLoading').hide();
                if (resp.success && resp.data && resp.data.length > 0) {
                    $('#ebay3ChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                    renderEbay3MetricChart(resp.data);
                } else {
                    $('#ebay3ChartNoData').show();
                }
            },
            error: function(xhr, status) {
                ebay3ChartAjax = null;
                if (status === 'abort') {
                    return;
                }
                $('#ebay3ChartLoading').hide();
                $('#ebay3ChartNoData').show();
            }
        });
    }

    function renderEbay3MetricChart(data) {
        const ctx = document.getElementById('ebay3MetricChart').getContext('2d');
        if (ebay3ChartInstance) {
            ebay3ChartInstance.destroy();
        }

        const labels = data.map(function(d) { return d.date; });
        const values = data.map(function(d) { return d.value; });

        const dataMin = Math.min.apply(null, values);
        const dataMax = Math.max.apply(null, values);
        const sorted = values.slice().sort(function(a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yMin = Math.max(0, dataMin - range * 0.1);
        const yMax = dataMax + range * 0.1;

        document.getElementById('ebay3ChartHighest').textContent = ebay3FmtChartVal(dataMax);
        document.getElementById('ebay3ChartMedian').textContent = ebay3FmtChartVal(median);
        document.getElementById('ebay3ChartLowest').textContent = ebay3FmtChartVal(dataMin);

        const dotColors = values.map(function(v, i) {
            if (i === 0) return '#6c757d';
            return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#28a745' : '#6c757d');
        });
        const labelColors = values.map(function(v) {
            return v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d';
        });

        const medianLinePlugin = {
            id: 'ebay3MedianLine',
            afterDraw: function(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const c = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                c.save();
                c.setLineDash([6, 4]);
                c.strokeStyle = '#6c757d';
                c.lineWidth = 1.2;
                c.beginPath();
                c.moveTo(xScale.left, yPixel);
                c.lineTo(xScale.right, yPixel);
                c.stroke();
                c.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'ebay3ValueLabels',
            afterDatasetsDraw: function(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const c = chart.ctx;
                if (!dataset || !meta || !meta.data) return;
                c.save();
                c.font = 'bold 9px Inter, system-ui, sans-serif';
                c.textAlign = 'center';
                c.textBaseline = 'bottom';
                meta.data.forEach(function(point, i) {
                    if (point == null || point.skip) return;
                    const txt = ebay3FmtChartVal(dataset.data[i]);
                    const offsetY = (i % 2 === 0) ? -8 : -16;
                    const py = point.y + offsetY;
                    c.lineJoin = 'round';
                    c.lineWidth = 3;
                    c.strokeStyle = 'rgba(255,255,255,0.92)';
                    c.strokeText(txt, point.x, py);
                    c.fillStyle = labelColors[i];
                    c.fillText(txt, point.x, py);
                });
                c.restore();
            }
        };

        ebay3ChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: 'rgba(0, 168, 168, 0.08)',
                    borderColor: '#00a8a8',
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
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22, left: 2, right: 2, bottom: 2 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + ebay3FmtChartVal(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + ebay3FmtChartVal(Math.abs(diff)));
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
                        ticks: { font: { size: 9 }, callback: function(v) { return ebay3FmtChartVal(v); } }
                    },
                    x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                }
            }
        });
    }

    let ebay3BadgeHoverTimer = null;
    $(document).on('click', '.ebay3-badge-chart', function(e) {
        e.stopPropagation();
        const m = $(this).data('metric');
        if (m) {
            showEbay3MetricChart(m);
        }
    });
    $(document).on('mouseenter', '.ebay3-hover-chart', function() {
        const metric = $(this).data('metric');
        if (!metric) return;
        ebay3BadgeHoverTimer = setTimeout(function() {
            showEbay3MetricChart(metric);
        }, 500);
    });
    $(document).on('mouseleave', '.ebay3-hover-chart', function() {
        if (ebay3BadgeHoverTimer) {
            clearTimeout(ebay3BadgeHoverTimer);
            ebay3BadgeHoverTimer = null;
        }
    });
    $(document).on('mousedown', '.ebay3-hover-chart', function() {
        if (ebay3BadgeHoverTimer) {
            clearTimeout(ebay3BadgeHoverTimer);
            ebay3BadgeHoverTimer = null;
        }
    });

    // Toast notification function
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
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            toast.classList.add('show');
            toast.style.position = 'fixed';
            toast.style.top = '1rem';
            toast.style.right = '1rem';
            toast.style.zIndex = '10800';
            setTimeout(function() { toast.remove(); }, 5000);
        }
    }

    // Background retry storage key
    const BACKGROUND_RETRY_KEY = 'ebay3_failed_price_pushes';
    const VARIATION_GREEN_KEY = 'ebay3_variation_green_skus';

    function loadVariationGreenSkus() {
        try {
            const raw = localStorage.getItem(VARIATION_GREEN_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return new Set(Array.isArray(arr) ? arr : []);
        } catch (e) {
            return new Set();
        }
    }

    function persistVariationGreenSkus() {
        try {
            localStorage.setItem(VARIATION_GREEN_KEY, JSON.stringify([...variationGreenSkus]));
        } catch (e) {}
    }

    let variationGreenSkus = loadVariationGreenSkus();
    
    // Save failed SKU to localStorage for background retry
    function saveFailedSkuForRetry(sku, price, retryCount = 0) {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            failedSkus[sku] = {
                sku: sku,
                price: price,
                retryCount: retryCount,
                timestamp: Date.now()
            };
            localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
        } catch (e) {
            console.error('Error saving failed SKU to localStorage:', e);
        }
    }
    
    // Remove SKU from background retry list
    function removeFailedSkuFromRetry(sku) {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            delete failedSkus[sku];
            localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
        } catch (e) {
            console.error('Error removing SKU from localStorage:', e);
        }
    }
    
    // Background retry function (runs even after page refresh)
    async function backgroundRetryFailedSkus() {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            const skuKeys = Object.keys(failedSkus);
            
            if (skuKeys.length === 0) return;
            
            console.log(`Found ${skuKeys.length} failed SKU(s) to retry in background`);
            
            for (const sku of skuKeys) {
                const failedData = failedSkus[sku];
                
                // Skip if already retried 5 times
                if (failedData.retryCount >= 5) {
                    console.log(`SKU ${sku} has reached max retries (5), removing from retry list`);
                    removeFailedSkuFromRetry(sku);
                    continue;
                }
                
                // Skip if account is restricted
                let isAccountRestricted = false;
                if (table) {
                    try {
                        const rows = table.getRows();
                        for (let i = 0; i < rows.length; i++) {
                            const rowData = rows[i].getData();
                            if (rowData['(Child) sku'] === sku) {
                                if (rowData.SPRICE_STATUS === 'account_restricted') {
                                    isAccountRestricted = true;
                                }
                                break;
                            }
                        }
                    } catch (e) {
                        // Continue if table check fails
                    }
                }
                
                if (isAccountRestricted) {
                    console.log(`SKU ${sku} is account restricted, skipping background retry`);
                    removeFailedSkuFromRetry(sku);
                    continue;
                }
                
                // Try to find the cell in the table for UI update
                let cell = null;
                if (table) {
                    try {
                        const rows = table.getRows();
                        for (let i = 0; i < rows.length; i++) {
                            const rowData = rows[i].getData();
                            if (rowData['(Child) sku'] === sku) {
                                cell = rows[i].getCell('_accept');
                                break;
                            }
                        }
                    } catch (e) {
                        // Table might not be ready, continue without UI update
                    }
                }
                
                // Retry the price push once (background retry)
                const success = await applyPriceWithRetry(sku, failedData.price, cell, 0, true);
                
                if (!success) {
                    // Increment retry count if still failed
                    failedData.retryCount++;
                    saveFailedSkuForRetry(sku, failedData.price, failedData.retryCount);
                    console.log(`Background retry ${failedData.retryCount}/5 failed for SKU: ${sku}`);
                } else {
                    // Success - already removed from retry list in applyPriceWithRetry
                    // Update table if it's loaded
                    if (table) {
                        setTimeout(() => {
                            table.replaceData();
                        }, 1000);
                    }
                }
                
                // Small delay between SKUs to avoid burst calls
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        } catch (e) {
            console.error('Error in background retry:', e);
        }
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                if (row) {
                    row.update({ SPRICE_STATUS: 'processing' });
                }
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        let targetRow = row;
                        if (table && table.getRows) {
                            table.getRows().forEach(function(r) {
                                const d = r.getData();
                                if (d['(Child) sku'] === sku) targetRow = r;
                            });
                        }
                        const numSprice = (typeof sprice === 'number' && !isNaN(sprice)) ? sprice : parseFloat(sprice);
                        if (targetRow) {
                            if (numSprice === 0) {
                                targetRow.update({
                                    SPRICE: null,
                                    SPFT: null,
                                    SROI: null,
                                    SGROI: null,
                                    SGPFT: null,
                                    SPRICE_STATUS: 'saved',
                                    has_custom_sprice: false
                                });
                            } else {
                                targetRow.update({
                                    SPRICE: numSprice,
                                    SPFT: response.data?.spft || response.spft_percent,
                                    SROI: response.data?.sroi || response.sroi_percent,
                                    SGROI: response.data?.sgroi ?? response.sgroi_percent ?? null,
                                    SGPFT: response.data?.sgpft || response.sgpft_percent,
                                    SPRICE_STATUS: 'saved',
                                    has_custom_sprice: true
                                });
                            }
                            targetRow.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`, errorMsg);
                        
                        if (retryCount < 1) {
                            console.log(`Retrying SKU ${sku} in 2 seconds...`);
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            console.error(`Max retries reached for SKU ${sku}`);
                            if (row) {
                                row.update({ SPRICE_STATUS: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

    // Apply price with retry logic (for pushing to eBay3)
    async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
            const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
            const row = cell ? cell.getRow() : null;
            const rowData = row ? row.getData() : null;

        // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
        if (isBackgroundRetry) {
            try {
                const response = await $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { sku: sku, price: price }
                });

                if (response.errors && response.errors.length > 0) {
                    throw new Error(response.errors[0].message || 'API error');
                }

                // Success - update UI and remove from retry list
                if (rowData) {
                    rowData.SPRICE_STATUS = 'pushed';
                    row.update(rowData);
                }
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-check-double"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                }
                removeFailedSkuFromRetry(sku);
                return true;
            } catch (e) {
                // Background failure is handled by retryCount in backgroundRetryFailedSkus
                if (rowData) {
                    rowData.SPRICE_STATUS = 'error';
                    row.update(rowData);
                }
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-x"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                }
                return false;
            }
        }

        // Foreground mode (user click): up to 5 immediate retries with spinner UI
            if (retries === 0 && cell && $btn && row) {
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                $btn.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                if (rowData) {
                    rowData.SPRICE_STATUS = 'processing';
                    row.update(rowData);
                }
            }

            try {
                console.log(`🚀 eBay3 API Request - Push Price`, {
                    sku: sku,
                    price: price,
                    url: '/push-ebay3-price-tabulator',
                    timestamp: new Date().toISOString()
                });
                
                const response = await $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { sku: sku, price: price }
                });

                console.log(`✅ eBay3 API Response - Success`, {
                    sku: sku,
                    price: price,
                    response: response,
                    trackingIds: {
                        rlogId: response.rlogId || 'N/A',
                        correlationId: response.correlationId || 'N/A',
                        build: response.build || 'N/A',
                        timestamp: response.timestamp || 'N/A'
                    },
                    requestTimestamp: new Date().toISOString()
                });

                if (response.errors && response.errors.length > 0) {
                    throw new Error(response.errors[0].message || 'API error');
                }

                // Success - update UI
                if (rowData) {
                    rowData.SPRICE_STATUS = 'pushed';
                    row.update(rowData);
                }
                
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-check-double"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                }
                
                if (!isBackgroundRetry) {
                    showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                }
                // Remove from background retry list so we don't keep retrying this SKU
                removeFailedSkuFromRetry(sku);
                return true;
            } catch (xhr) {
                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to apply price';
                const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                const rlogId = xhr.responseJSON?.rlogId || 'N/A';
                
                console.error(`❌ eBay3 API Response - Error (Attempt ${retries + 1})`, {
                    sku: sku,
                    price: price,
                    errorCode: errorCode,
                    errorMessage: errorMsg,
                    trackingIds: {
                        rlogId: xhr.responseJSON?.rlogId || rlogId || 'N/A',
                        correlationId: xhr.responseJSON?.correlationId || 'N/A',
                        build: xhr.responseJSON?.build || 'N/A',
                        timestamp: xhr.responseJSON?.timestamp || 'N/A'
                    },
                    fullResponse: xhr.responseJSON,
                    requestTimestamp: new Date().toISOString()
                });

                // Check if this is an account restriction error (don't retry)
                const isAccountRestricted = errorCode === 'AccountRestricted' || 
                                            errorMsg.includes('ACCOUNT RESTRICTION') ||
                                            errorMsg.includes('account is restricted') ||
                                            errorMsg.includes('embargoed country');
            
            if (isAccountRestricted) {
                // Account restriction - don't retry, mark as account_restricted
                if (rowData) {
                    rowData.SPRICE_STATUS = 'account_restricted';
                    row.update(rowData);
                }
                
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-ban"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #ff6b00; padding: 0;');
                    $btn.attr('title', 'Account restricted - cannot update price');
                }
                
                showToast(`Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`, 'error');
                return false;
            }

                if (retries < 5) {
                    console.log(`Retrying SKU ${sku} in 5 seconds...`);
                    await new Promise(resolve => setTimeout(resolve, 5000));
                return applyPriceWithRetry(sku, price, cell, retries + 1, isBackgroundRetry);
                } else {
                // Final failure - mark error and save for background retry
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'error';
                        row.update(rowData);
                    }
                    
                    if ($btn && cell) {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fa-solid fa-x"></i>');
                        $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                    }
                    
                // Save for background retry (only if not already a background retry)
                saveFailedSkuForRetry(sku, price, 0);
                showToast(`Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`, 'error');
                    
                    return false;
                }
            }
        }

    // Retry function for applying price with up to 5 attempts (Promise-based for Apply All)
    function applyPriceWithRetryPromise(sku, price, maxRetries = 5, delay = 5000) {
        return new Promise((resolve, reject) => {
            let attempt = 0;
            
            function attemptApply() {
                attempt++;
                
                $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        price: price,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.errors && response.errors.length > 0) {
                            const errorMsg = response.errors[0].message || 'Unknown error';
                            const errorCode = response.errors[0].code || '';
                            console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg, 'Code:', errorCode);
                            
                            if (attempt < maxRetries) {
                                console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                setTimeout(attemptApply, delay);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                reject({ error: true, response: response });
                            }
                        } else {
                            console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                            resolve({ success: true, response: response });
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                        console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                        
                        if (attempt < maxRetries) {
                            console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                            setTimeout(attemptApply, delay);
                        } else {
                            console.error(`Max retries reached for SKU ${sku}`);
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            }
            
            attemptApply();
        });
    }

    // Update selected count display
    function updateSelectedCount() {
        const count = selectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(
            count > 0 || decreaseModeActive || increaseModeActive || samePriceModeActive
        );
    }

    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        if (!table) return;
        
        const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
        
        if (filteredData.length === 0) {
            $('#select-all-checkbox').prop('checked', false);
            return;
        }
        
        const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
        
        const allFilteredSelected = filteredSkus.size > 0 && 
            Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
        
        $('#select-all-checkbox').prop('checked', allFilteredSelected);
    }

    $(document).ready(function() {
        const lmpModalEl = document.getElementById('lmpModal');
        if (lmpModalEl) {
            lmpModalEl.addEventListener('hidden.bs.modal', cleanupLmpModalBackdrop);
        }

        // Sbid (Views) modal — shared settings with /ebay3/campaign-ads
        function seedSbidViewsInputs() {
            $('#sbid-views-min-cap').val(isFinite(sbidViewsMinCap) ? sbidViewsMinCap : '');
            $('#sbid-views-max-cap').val(isFinite(sbidViewsMaxCap) ? sbidViewsMaxCap : '');
            $('#sbid-views-no-dec-max-el30').val(isFinite(sbidViewsNoDecMaxEl30) ? sbidViewsNoDecMaxEl30 : 0);
            $('#sbid-views-pink-dir').val(sbidViewsPinkDir);
            $('#sbid-views-pink-step').val(isFinite(sbidViewsPinkStep) ? sbidViewsPinkStep : '');
            $('#sbid-views-green-dir').val(sbidViewsGreenDir);
            $('#sbid-views-green-step').val(isFinite(sbidViewsGreenStep) ? sbidViewsGreenStep : '');
            $('#sbid-views-red-dir').val(sbidViewsRedDir);
            $('#sbid-views-red-step').val(isFinite(sbidViewsRedStep) ? sbidViewsRedStep : '');
        }
        function applySbidViewsSettings(s) {
            if (!s || typeof s !== 'object') return;
            if (isFinite(parseFloat(s.min_cap)))    sbidViewsMinCap   = parseFloat(s.min_cap);
            if (isFinite(parseFloat(s.max_cap)))    sbidViewsMaxCap   = parseFloat(s.max_cap);
            if (isFinite(parseFloat(s.no_dec_max_el30))) sbidViewsNoDecMaxEl30 = parseFloat(s.no_dec_max_el30);
            if (s.pink_dir)  sbidViewsPinkDir  = s.pink_dir;
            if (isFinite(parseFloat(s.pink_step)))  sbidViewsPinkStep = parseFloat(s.pink_step);
            if (s.green_dir) sbidViewsGreenDir = s.green_dir;
            if (isFinite(parseFloat(s.green_step))) sbidViewsGreenStep = parseFloat(s.green_step);
            if (s.red_dir)   sbidViewsRedDir   = s.red_dir;
            if (isFinite(parseFloat(s.red_step)))   sbidViewsRedStep  = parseFloat(s.red_step);
        }
        $.get(@json(url('/ebay3/campaign-ads/sbid-views-rule')), function(s) {
            applySbidViewsSettings(s);
            seedSbidViewsInputs();
            if (table) table.redraw(false);
        });
        seedSbidViewsInputs();
        $('#sbidViewsRuleModal').on('show.bs.modal', seedSbidViewsInputs);
        $('#sbid-views-save-btn').on('click', function() {
            const num = function(sel, dflt) {
                const v = parseFloat($(sel).val());
                return isFinite(v) ? v : dflt;
            };
            const dir = function(sel, dflt) {
                let v = $(sel).val();
                return (v === 'inc' || v === 'dec' || v === 'none') ? v : dflt;
            };
            const payload = {
                min_cap:    num('#sbid-views-min-cap', 1),
                max_cap:    num('#sbid-views-max-cap', 20),
                no_dec_max_el30: num('#sbid-views-no-dec-max-el30', 0),
                pink_dir:   dir('#sbid-views-pink-dir', 'dec'),
                pink_step:  num('#sbid-views-pink-step', 1),
                green_dir:  dir('#sbid-views-green-dir', 'none'),
                green_step: num('#sbid-views-green-step', 0),
                red_dir:    dir('#sbid-views-red-dir', 'inc'),
                red_step:   num('#sbid-views-red-step', 1),
            };
            $.ajax({
                url: @json(url('/ebay3/campaign-ads/sbid-views-rule')),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function(resp) {
                    applySbidViewsSettings(resp && resp.rule ? resp.rule : payload);
                    if (table) table.redraw(false);
                    const modalEl = document.getElementById('sbidViewsRuleModal');
                    const inst = bootstrap.Modal.getInstance(modalEl);
                    if (inst) inst.hide();
                },
                error: function(xhr) {
                    alert('Save failed: ' + xhr.status);
                }
            });
        });

        // ---- Edit Links (Buyer / Seller) ----
        let ebay3EditLinksRow = null;
        window.openEbay3EditLinksModal = function(row) {
            ebay3EditLinksRow = row;
            const d = row.getData();
            $('#ebay3EditLinksSku').text(d['(Child) sku'] || '');
            $('#ebay3SellerLinkInput').val(d.seller_link || '');
            $('#ebay3BuyerLinkInput').val(d.buyer_link || '');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay3EditLinksModal')).show();
        };

        $('#ebay3SaveLinksBtn').on('click', function() {
            if (!ebay3EditLinksRow) return;
            const sku = ebay3EditLinksRow.getData()['(Child) sku'];
            const sellerLink = $('#ebay3SellerLinkInput').val().trim();
            const buyerLink = $('#ebay3BuyerLinkInput').val().trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: '/ebay3/save-links',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sku: sku,
                    seller_link: sellerLink,
                    buyer_link: buyerLink
                },
                success: function(res) {
                    if (res && res.success) {
                        ebay3EditLinksRow.update({
                            seller_link: res.seller_link || '',
                            buyer_link: res.buyer_link || ''
                        }).then(function() {
                            ebay3EditLinksRow.reformat();
                        }).catch(function() {
                            ebay3EditLinksRow.reformat();
                        });
                        showToast('Links saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay3EditLinksModal')).hide();
                    } else {
                        showToast((res && res.message) || 'Failed to save links', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $('#ebay3ChartRangeSelect').on('change', function() {
            const days = parseInt($(this).val(), 10);
            if (days === ebay3ChartDays) return;
            ebay3ChartDays = days;
            const label = ebay3BadgeMetricLabels[ebay3ChartMetricKey] || ebay3ChartMetricKey;
            $('#ebay3ChartModalTitle').text('eBay 3 — ' + label + ' (Daily snapshot)');
            loadEbay3MetricChart();
        });

        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() {
            if (samePriceModeActive) {
                return;
            }
            const discountType = $(this).val();
            const $input = $('#discount-percentage-input');
            if (discountType === 'percentage') {
                $input.attr('placeholder', 'Enter percentage');
                $input.attr('max', '100');
            } else {
                $input.attr('placeholder', 'Enter value');
                $input.removeAttr('max');
            }
        });

        function syncEbay3DiscountBarForMode() {
            const $inp = $('#discount-percentage-input');
            if (samePriceModeActive) {
                $('#ebay3-discount-type-block').addClass('d-none');
                $('#discount-input-label').text('eBay price:');
                $inp.attr('placeholder', 'Each row — click Apply');
                $inp.prop('disabled', true);
                $inp.removeAttr('max');
                $inp.val('');
            } else {
                $('#ebay3-discount-type-block').removeClass('d-none');
                $('#discount-input-label').text('Value:');
                $inp.prop('disabled', false);
                const type = $('#discount-type-select').val();
                if (type === 'percentage') {
                    $inp.attr('placeholder', 'Enter percentage');
                    $inp.attr('max', '100');
                } else {
                    $inp.attr('placeholder', 'Enter value');
                    $inp.removeAttr('max');
                }
            }
        }

        function syncEbay3PriceModeUi() {
            if (!table || !table.getColumn) {
                return;
            }
            const $btn = $('#ebay3-price-mode-btn');
            const selectColumn = table.getColumn('_select');
            syncEbay3DiscountBarForMode();
            if (decreaseModeActive) {
                $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            if (increaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            if (samePriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price %');
            selectColumn.hide();
            selectedSkus.clear();
            $('.sku-select-checkbox').prop('checked', false);
            $('#select-all-checkbox').prop('checked', false);
            $('#discount-input-container').hide();
            updateSelectedCount();
            updateSelectAllCheckbox();
        }

        $('#ebay3-price-mode-btn').on('click', function() {
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                decreaseModeActive = true;
            } else if (decreaseModeActive) {
                decreaseModeActive = false;
                increaseModeActive = true;
            } else if (increaseModeActive) {
                increaseModeActive = false;
                samePriceModeActive = true;
            } else {
                samePriceModeActive = false;
            }
            syncEbay3PriceModeUi();
        });

        // Apply all selected prices
        window.applyAllSelectedPrices = function() {
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU to apply prices', 'error');
                return;
            }
            
            const $btn = $('#apply-all-btn');
            if ($btn.length === 0) {
                showToast('Apply All button not found', 'error');
                return;
            }
            
            if ($btn.prop('disabled')) {
                return;
            }
            
            const originalHtml = $btn.html();
            
            // Disable button and show loading state
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
            
            // Get all table data to find SPRICE for selected SKUs
            const tableData = table.getData('all');
            const skusToProcess = [];
            
            // Build list of SKUs with their prices
            selectedSkus.forEach(sku => {
                const row = tableData.find(r => r['(Child) sku'] === sku);
                if (row) {
                    const sprice = parseFloat(row.SPRICE) || 0;
                    if (sprice > 0) {
                        skusToProcess.push({ sku: sku, price: sprice });
                    }
                }
            });
            
            if (skusToProcess.length === 0) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                showToast('No valid prices found for selected SKUs', 'error');
                return;
            }
            
            let successCount = 0;
            let errorCount = 0;
            let currentIndex = 0;
            
            // Process SKUs sequentially (one by one) with delay to avoid rate limiting
            function processNextSku() {
                if (currentIndex >= skusToProcess.length) {
                    // All SKUs processed
                    $btn.prop('disabled', false);
                    
                    if (errorCount === 0) {
                        // All successful
                        $btn.html(`<i class="fas fa-check-double" style="color: #28a745;"></i>`);
                        showToast(`Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`, 'success');
                        
                        // Reset to original state after 3 seconds
                        setTimeout(() => {
                            $btn.html(originalHtml);
                        }, 3000);
                    } else {
                        $btn.html(originalHtml);
                        showToast(`Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                    }
                    return;
                }
                
                const { sku, price } = skusToProcess[currentIndex];
                
                // Find the row and update button to show spinner
                const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                if (row) {
                    const acceptCell = row.getCell('_accept');
                    if (acceptCell) {
                        const $cellElement = $(acceptCell.getElement());
                        const $btnInCell = $cellElement.find('.apply-price-btn');
                        if ($btnInCell.length) {
                            $btnInCell.prop('disabled', true);
                            $btnInCell.html('<i class="fas fa-spinner fa-spin"></i>');
                            $btnInCell.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                        }
                    }
                }
                
                // First save to database (like SPRICE edit does), then push to eBay3
                console.log(`Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`);
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: price,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(saveResponse) {
                        console.log(`SKU ${sku}: Database save successful`, saveResponse);
                        if (saveResponse.error) {
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse.error);
                            errorCount++;
                            
                            // Update row data with error status
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                                
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                        $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                    }
                                }
                            }
                            
                            // Process next SKU
                            currentIndex++;
                            setTimeout(() => {
                                processNextSku();
                            }, 2000);
                            return;
                        }
                        
                        // After saving, push to eBay3 using retry function
                        console.log(`SKU ${sku}: Starting eBay3 price push...`);
                        applyPriceWithRetryPromise(sku, price, 5, 5000)
                            .then((result) => {
                            successCount++;
                                
                                // Update row data with pushed status instantly
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'pushed';
                                row.update(rowData);
                                
                                    // Update button to show green check-double
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-check-double"></i>');
                                        $btnInCell.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                                    }
                                }
                            }
                                
                                // Process next SKU with delay to avoid rate limiting (2 seconds between requests)
                            currentIndex++;
                                setTimeout(() => {
                                    processNextSku();
                                }, 2000);
                            })
                            .catch((error) => {
                            errorCount++;
                                
                                // Update row data with error status
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                                    
                                    // Update button to show error icon
                                    const acceptCell = row.getCell('_accept');
                                    if (acceptCell) {
                                        const $cellElement = $(acceptCell.getElement());
                                        const $btnInCell = $cellElement.find('.apply-price-btn');
                                        if ($btnInCell.length) {
                                            $btnInCell.prop('disabled', false);
                                            $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                            $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                        }
                                    }
                                }
                                
                                // Save for background retry
                                saveFailedSkuForRetry(sku, price, 0);
                                
                                // Process next SKU with delay to avoid rate limiting
                            currentIndex++;
                                setTimeout(() => {
                                    processNextSku();
                                }, 2000);
                        });
                    },
                    error: function(xhr) {
                        console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr.responseJSON || xhr.responseText);
                        errorCount++;
                        
                        // Update row data with error status
                        if (row) {
                            const rowData = row.getData();
                            rowData.SPRICE_STATUS = 'error';
                            row.update(rowData);
                            
                            const acceptCell = row.getCell('_accept');
                            if (acceptCell) {
                                const $cellElement = $(acceptCell.getElement());
                                const $btnInCell = $cellElement.find('.apply-price-btn');
                                if ($btnInCell.length) {
                                    $btnInCell.prop('disabled', false);
                                    $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                    $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                }
                            }
                        }
                        
                        // Process next SKU
                        currentIndex++;
                        setTimeout(() => {
                            processNextSku();
                        }, 2000);
                    }
                });
            }
            
            // Start processing
            processNextSku();
        };

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

        // Apply discount to selected SKUs (Price %: Decrease / Increase / Same Price — aligned with Open Box)
        function applyDiscount() {
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const rawInput = $('#discount-percentage-input').val();
            const inputValue = parseFloat(String(rawInput || '').replace(',', '.'));
            const discountType = $('#discount-type-select').val();

            if (!samePriceModeActive) {
                if (rawInput === '' || rawInput == null) {
                    showToast('Please enter a value (% or $)', 'error');
                    return;
                }
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('Please enter a valid positive number', 'error');
                    return;
                }
                if (discountType === 'percentage' && inputValue > 100) {
                    showToast('Percentage cannot exceed 100', 'error');
                    return;
                }
            }

            const allData = table.getData('all');
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;
            const appliedAsSamePrice = samePriceModeActive;

            allData.forEach(row => {
                const isParent = row.Parent && row.Parent.startsWith('PARENT');
                if (isParent) return;

                const sku = row['(Child) sku'];
                if (!selectedSkus.has(sku)) return;

                const originalPrice = parseFloat(row['eBay Price']) || 0;
                if (originalPrice <= 0) return;

                let newPriceNum;
                if (samePriceModeActive) {
                    let newSPrice = roundToRetailPrice(originalPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                } else {
                    let newSPrice;
                    if (discountType === 'percentage') {
                        const decimal = inputValue / 100;
                        if (increaseModeActive) {
                            newSPrice = originalPrice * (1 + decimal);
                        } else {
                            newSPrice = originalPrice * (1 - decimal);
                        }
                    } else {
                        if (increaseModeActive) {
                            newSPrice = originalPrice + inputValue;
                        } else {
                            newSPrice = Math.max(0.01, originalPrice - inputValue);
                        }
                    }
                    newSPrice = Math.max(0.01, newSPrice);
                    newSPrice = roundToRetailPrice(newSPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                }

                const originalSPrice = parseFloat(row['SPRICE']) || 0;
                const tableRow = table.getRows().find(r => {
                    const rowData = r.getData();
                    return rowData['(Child) sku'] === sku;
                });

                if (tableRow) {
                    tableRow.update({ SPRICE: newPriceNum, SPRICE_STATUS: 'processing' });
                }

                saveSpriceWithRetry(sku, newPriceNum, tableRow)
                    .then(() => {
                        updatedCount++;
                        if (updatedCount + errorCount === totalSkus) {
                            if (errorCount === 0) {
                                showToast(
                                    appliedAsSamePrice
                                        ? `SPRICE set to eBay price for ${updatedCount} SKU(s)`
                                        : `Discount applied to ${updatedCount} SKU(s)`,
                                    'success'
                                );
                            } else {
                                showToast(
                                    appliedAsSamePrice
                                        ? `Updated ${updatedCount} SKU(s), ${errorCount} failed`
                                        : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                    'error'
                                );
                            }
                        }
                    })
                    .catch(() => {
                        errorCount++;
                        if (tableRow) {
                            tableRow.update({ SPRICE: originalSPrice });
                        }
                        if (updatedCount + errorCount === totalSkus) {
                            showToast(
                                appliedAsSamePrice
                                    ? `Updated ${updatedCount} SKU(s), ${errorCount} failed`
                                    : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                'error'
                            );
                        }
                    });
            });
        }

        // Clear SPRICE for selected SKUs (use getRows() so tree child rows are included)
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            let clearedCount = 0;
            const allRows = table.getRows();

            allRows.forEach(function(tableRow) {
                const rowData = tableRow.getData();
                const sku = rowData['(Child) sku'] || '';
                if (!sku || sku.toUpperCase().includes('PARENT')) return;
                if (!selectedSkus.has(sku)) return;

                tableRow.update({
                    SPRICE: 0,
                    SPRICE_STATUS: 'processing'
                });

                saveSpriceWithRetry(sku, 0, tableRow)
                    .then(function(response) {
                        clearedCount++;
                        if (clearedCount === selectedSkus.size) {
                            showToast('SPRICE cleared for ' + clearedCount + ' SKU(s)', 'success');
                            // Refetch from server so table shows cleared state without page refresh
                            table.replaceData('/ebay3-data-json?_=' + Date.now()).then(function() {
                                if (typeof allTableData !== 'undefined') {
                                    allTableData = table.getData('all');
                                }
                                applyFilters();
                            }).catch(function(err) {
                                console.error('Reload after clear failed:', err);
                            });
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to clear SPRICE for', sku);
                    });
            });
        }

        // Clear SPRICE for selected SKUs only
        $('#clear-sprice-btn').on('click', function() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }
            clearSpriceForSelected();
        });

        // Sprice × CVR — shared via /ebay/sprice-cvr-rule.
        // eBay3: CVR L30 vs prior L31–L60 (CVR L60) + band rules.
        const SPRICE_CVR_DEFAULT_ACTIONS = {
            // Rule 1 (CVR=0%): same Down/Equal/Up defaults as Yellow
            zero: { down: 'decrease', equal: 'decrease', up: 'hold' },
            red: { down: 'decrease', equal: 'decrease', up: 'hold' },
            blue: { down: 'decrease', equal: 'hold', up: 'increase' },
            green: { down: 'hold', equal: 'hold', up: 'hold' },
            pink: { down: 'hold', equal: 'decrease', up: 'increase' }
        };
        /** Includes Rule 1 (zero) + Yellow–Pink (red/blue/green/pink). */
        const SPRICE_CVR_SLABS = ['zero', 'red', 'blue', 'green', 'pink'];
        /** Yellow band starts at this CVR% (Rule 2: 0.01 – Yellow≤). */
        const SPRICE_CVR_YELLOW_START = 0.01;
        /** Default Yellow≤ / Blue start boundary. */
        const SPRICE_CVR_YELLOW_END_DEFAULT = 3.5;
        /** Signed %: +increase, −decrease, 0=hold (no suggestion) */
        const SPRICE_CVR_DEFAULT_TREND_PCT = { down: 0, equal: 0, up: 0 };
        const SPRICE_CVR_DEFAULT_YELLOW_PCT = { down: -2, equal: -1, up: 0 };
        /** Rule 1 CVR=0%: Down −1 / Same −1 / Up 0 (% of eBay price) */
        const SPRICE_CVR_DEFAULT_ZERO_PCT = { down: -1, equal: -1, up: 0 };
        /** Blue default: Down −1 / Same 0 / Up +1 (% of eBay price) */
        const SPRICE_CVR_DEFAULT_BLUE_PCT = { down: -1, equal: 0, up: 1 };
        const SPRICE_CVR_DEFAULT_PINK_PCT = { down: 0, equal: -1, up: 1 };
        const SPRICE_CVR_DEFAULT_SLAB_PCT = {
            zero: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_ZERO_PCT)),
            red: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_YELLOW_PCT)),
            blue: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_BLUE_PCT)),
            green: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_TREND_PCT)),
            pink: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_PINK_PCT))
        };
        /** Rule 1 Dil% band edges: Down < dil_low · Equal mid · Up > dil_high */
        const SPRICE_CVR_DEFAULT_ZERO_CVR_DIL = {
            enabled: true,
            dil_low: 25,
            dil_high: 50,
            roi_low: 40,
            roi_mid: 50,
            roi_high: 60
        };
        /** Stored Dil keys kept for API compat (UI/apply Dil conditions removed) */
        const SPRICE_CVR_DEFAULT_DIL_OVERRIDE = 100;
        const SPRICE_CVR_DEFAULT_ZERO_UP_DIL = SPRICE_CVR_DEFAULT_DIL_OVERRIDE;
        const SPRICE_CVR_DEFAULT_YELLOW_UP_DIL = SPRICE_CVR_DEFAULT_DIL_OVERRIDE;
        const SPRICE_CVR_DEFAULT_BLUE_UP_DIL = SPRICE_CVR_DEFAULT_DIL_OVERRIDE;
        const SPRICE_CVR_DEFAULT_GREEN_UP_DIL = SPRICE_CVR_DEFAULT_DIL_OVERRIDE;
        const SPRICE_CVR_DEFAULT_DIL_OVERRIDES = {
            zero_down_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            zero_equal_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            zero_up_dil: SPRICE_CVR_DEFAULT_ZERO_UP_DIL,
            yellow_down_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            yellow_equal_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            yellow_up_dil: SPRICE_CVR_DEFAULT_YELLOW_UP_DIL,
            blue_down_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            blue_equal_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            blue_up_dil: SPRICE_CVR_DEFAULT_BLUE_UP_DIL,
            green_down_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            green_equal_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            green_up_dil: SPRICE_CVR_DEFAULT_GREEN_UP_DIL,
            pink_down_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            pink_equal_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE,
            pink_up_dil: SPRICE_CVR_DEFAULT_DIL_OVERRIDE
        };
        let spriceCvrRule = {
            low_cvr: 3.5,
            mid_cvr: 7,
            high_cvr: 13,
            down_mult: 0.99,
            up_mult: 1.01,
            roi_floor_pct: 40,
            trend_tolerance: 0.1,
            actions: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_ACTIONS)),
            yellow_pct: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_YELLOW_PCT)),
            slab_pct: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_SLAB_PCT)),
            ...JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_DIL_OVERRIDES)),
            zero_cvr_dil: JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_ZERO_CVR_DIL))
        };
        const SPRICE_CVR_URL = @json(url('/ebay/sprice-cvr-rule'));

        // eBay3 spriceCvrShowToast(message, type) — accept Amazon-style (type, message) too
        function spriceCvrShowToast(a, b) {
            const types = { error: 1, success: 1, warning: 1, info: 1 };
            if (typeof showToast !== 'function') return;
            if (types[a] && b != null) showToast(b, a);
            else showToast(a, b || 'info');
        }
        let spriceCvrPieChart = null;
        let spriceCvrActionPieChart = null;
        let spriceCvrZeroPieChart = null;
        const spriceCvrTrendPieCharts = { yellow: null, blue: null, green: null, pink: null };
        const SPRICE_CVR_TREND_PIE_META = {
            yellow: { slab: 'red', label: 'Yellow', colors: ['#e67700', '#f59f00', '#ffd43b', '#ced4da'] },
            blue: { slab: 'blue', label: 'Blue', colors: ['#1c7ed6', '#4dabf7', '#a5d8ff', '#ced4da'] },
            green: { slab: 'green', label: 'Green', colors: ['#2f9e44', '#51cf66', '#b2f2bb', '#ced4da'] },
            pink: { slab: 'pink', label: 'Pink', colors: ['#c2255c', '#f06595', '#fcc2d7', '#ced4da'] }
        };

        function formatCvrMult(v) {
            const n = Number(v);
            if (!isFinite(n)) return '0';
            return String(+n.toFixed(4));
        }

        function sanitizeSpriceCvrAction(val, fallback) {
            const v = String(val || '').toLowerCase();
            if (v === 'increase' || v === 'decrease' || v === 'hold') return v;
            return fallback || 'hold';
        }

        function normalizeSpriceCvrActions(raw) {
            const src = raw && typeof raw === 'object' ? raw : {};
            const out = {};
            SPRICE_CVR_SLABS.forEach(function(slab) {
                const def = SPRICE_CVR_DEFAULT_ACTIONS[slab];
                const row = src[slab] && typeof src[slab] === 'object' ? src[slab] : {};
                out[slab] = {
                    down: sanitizeSpriceCvrAction(row.down, def.down),
                    equal: sanitizeSpriceCvrAction(row.equal, def.equal),
                    up: sanitizeSpriceCvrAction(row.up, def.up)
                };
            });
            return out;
        }

        function actionFromSignedPct(v) {
            const n = parseFloat(v);
            if (!isFinite(n) || n === 0) return 'hold';
            return n > 0 ? 'increase' : 'decrease';
        }

        /** Derive legacy actions from signed % inputs (for storage). */
        function readSpriceCvrActionsFromUi() {
            const sp = readSlabPctFromUi();
            const out = {};
            SPRICE_CVR_SLABS.forEach(function(slab) {
                out[slab] = {
                    down: actionFromSignedPct(sp[slab].down),
                    equal: actionFromSignedPct(sp[slab].equal),
                    up: actionFromSignedPct(sp[slab].up)
                };
            });
            return out;
        }

        function applySpriceCvrActionsToUi() {
            // Actions removed from UI — signed % inputs only
        }

        function normalizeTrendPct(raw, def) {
            const d = def || SPRICE_CVR_DEFAULT_TREND_PCT;
            const src = raw && typeof raw === 'object' ? raw : {};
            let down = parseFloat(src.down);
            let equal = parseFloat(src.equal);
            let up = parseFloat(src.up);
            if (!isFinite(down) || down < -50 || down > 50) down = d.down;
            if (!isFinite(equal) || equal < -50 || equal > 50) equal = d.equal;
            if (!isFinite(up) || up < -50 || up > 50) up = d.up;
            return { down: down, equal: equal, up: up };
        }

        function normalizeYellowPct(raw) {
            return normalizeTrendPct(raw, SPRICE_CVR_DEFAULT_YELLOW_PCT);
        }

        /** Convert legacy positive % + action → signed % (once). */
        function migrateLegacySlabPctToSigned(raw, actions, yellowLegacy) {
            const src = raw && typeof raw === 'object' ? raw : {};
            const acts = normalizeSpriceCvrActions(actions);
            // Signed format uses ≤0 (0=hold). Legacy magnitudes were always >0.
            let looksSigned = false;
            SPRICE_CVR_SLABS.forEach(function(slab) {
                const row = src[slab];
                if (row && typeof row === 'object') {
                    ['down', 'equal', 'up'].forEach(function(k) {
                        const n = parseFloat(row[k]);
                        if (isFinite(n) && n <= 0) looksSigned = true;
                    });
                }
            });
            const out = {};
            SPRICE_CVR_SLABS.forEach(function(slab) {
                const def = SPRICE_CVR_DEFAULT_SLAB_PCT[slab];
                let row = src[slab];
                if ((!row || typeof row !== 'object') && slab === 'red' && yellowLegacy) {
                    row = yellowLegacy;
                }
                if (looksSigned) {
                    out[slab] = normalizeTrendPct(row, def);
                    return;
                }
                // Legacy: magnitude was always positive; Hold → 0
                const magSrc = row && typeof row === 'object' ? row : {};
                let down = parseFloat(magSrc.down);
                let equal = parseFloat(magSrc.equal);
                let up = parseFloat(magSrc.up);
                if (!isFinite(down) || down < 0 || down > 50) down = Math.abs(def.down);
                if (!isFinite(equal) || equal < 0 || equal > 50) equal = Math.abs(def.equal);
                if (!isFinite(up) || up < 0 || up > 50) up = Math.abs(def.up);
                const a = acts[slab] || SPRICE_CVR_DEFAULT_ACTIONS[slab];
                out[slab] = {
                    down: a.down === 'hold' ? 0 : (a.down === 'decrease' ? -Math.abs(down) : Math.abs(down)),
                    equal: a.equal === 'hold' ? 0 : (a.equal === 'decrease' ? -Math.abs(equal) : Math.abs(equal)),
                    up: a.up === 'hold' ? 0 : (a.up === 'decrease' ? -Math.abs(up) : Math.abs(up))
                };
            });
            return out;
        }

        function normalizeSlabPct(raw, yellowLegacy, actions) {
            if (actions) {
                return migrateLegacySlabPctToSigned(raw, actions, yellowLegacy);
            }
            const src = raw && typeof raw === 'object' ? raw : {};
            const out = {};
            SPRICE_CVR_SLABS.forEach(function(slab) {
                const def = SPRICE_CVR_DEFAULT_SLAB_PCT[slab];
                let row = src[slab];
                if ((!row || typeof row !== 'object') && slab === 'red' && yellowLegacy) {
                    row = yellowLegacy;
                }
                out[slab] = normalizeTrendPct(row, def);
            });
            return out;
        }

        function slabPctForTrend(slabPct, slab, trend) {
            const sp = normalizeSlabPct(slabPct);
            const row = sp[slab] || SPRICE_CVR_DEFAULT_TREND_PCT;
            if (trend === 'equal') return row.equal;
            if (trend === 'up') return row.up;
            return row.down;
        }

        function yellowPctForTrend(yp, trend) {
            return slabPctForTrend({ red: yp }, 'red', trend);
        }

        function slabPctInputId(slab, trend) {
            if (slab === 'red') return '#sprice-cvr-yellow-' + trend + '-pct';
            if (slab === 'zero') return '#sprice-cvr-zero-' + trend + '-pct';
            return '#sprice-cvr-' + slab + '-' + trend + '-pct';
        }

        function readSlabPctFromUi() {
            const out = {};
            SPRICE_CVR_SLABS.forEach(function(slab) {
                out[slab] = normalizeTrendPct({
                    down: String($(slabPctInputId(slab, 'down')).val()).replace(',', '.'),
                    equal: String($(slabPctInputId(slab, 'equal')).val()).replace(',', '.'),
                    up: String($(slabPctInputId(slab, 'up')).val()).replace(',', '.')
                }, SPRICE_CVR_DEFAULT_SLAB_PCT[slab]);
            });
            return out;
        }

        function readYellowPctFromUi() {
            return readSlabPctFromUi().red;
        }

        function applySlabPctToUi(sp) {
            const r = normalizeSlabPct(sp);
            SPRICE_CVR_SLABS.forEach(function(slab) {
                $(slabPctInputId(slab, 'down')).val(r[slab].down);
                $(slabPctInputId(slab, 'equal')).val(r[slab].equal);
                $(slabPctInputId(slab, 'up')).val(r[slab].up);
            });
        }

        function applyYellowPctToUi(yp) {
            const r = normalizeYellowPct(yp);
            $('#sprice-cvr-yellow-down-pct').val(r.down);
            $('#sprice-cvr-yellow-equal-pct').val(r.equal);
            $('#sprice-cvr-yellow-up-pct').val(r.up);
        }

        function normalizeZeroCvrDil(raw) {
            const def = SPRICE_CVR_DEFAULT_ZERO_CVR_DIL;
            const src = raw && typeof raw === 'object' ? raw : {};
            const enRaw = src.enabled;
            const enabled = !(enRaw === false || enRaw === 0 || enRaw === '0' || enRaw === 'false');
            let dilLow = parseFloat(src.dil_low);
            let dilHigh = parseFloat(src.dil_high);
            if (!isFinite(dilLow)) dilLow = def.dil_low;
            if (!isFinite(dilHigh)) dilHigh = def.dil_high;
            if (dilHigh < dilLow) {
                const t = dilLow; dilLow = dilHigh; dilHigh = t;
            }
            const roi = function(v, fb) {
                const n = parseFloat(v);
                return (isFinite(n) && n >= 0 && n <= 500) ? n : fb;
            };
            return {
                enabled: enabled,
                dil_low: dilLow,
                dil_high: dilHigh,
                roi_low: roi(src.roi_low, def.roi_low),
                roi_mid: roi(src.roi_mid, def.roi_mid),
                roi_high: roi(src.roi_high, def.roi_high)
            };
        }

        function readZeroCvrDilFromUi() {
            // Band edges kept in stored rule (no UI); Rule 1 now uses CVR trend like Rule 2
            return normalizeZeroCvrDil(spriceCvrRule && spriceCvrRule.zero_cvr_dil);
        }

        function applyZeroCvrDilToUi(z) {
            spriceCvrRule.zero_cvr_dil = normalizeZeroCvrDil(z);
        }

        function normalizeZeroUpDil(raw) {
            return normalizeUpDilThreshold(raw, SPRICE_CVR_DEFAULT_ZERO_UP_DIL);
        }

        function normalizeSpriceCvrRule(resp) {
            const slabs = resolveSpriceCvrSlabs({
                low_cvr: resp && resp.low_cvr,
                mid_cvr: resp && resp.mid_cvr,
                high_cvr: resp && resp.high_cvr
            });
            const actions = normalizeSpriceCvrActions(resp && resp.actions);
            const yellowPct = normalizeYellowPct(resp && resp.yellow_pct);
            const slabPct = normalizeSlabPct(resp && resp.slab_pct, yellowPct, actions);
            return {
                low_cvr: slabs.low,
                mid_cvr: slabs.mid,
                high_cvr: slabs.high,
                down_mult: parseFloat(resp && resp.down_mult) || 0.99,
                up_mult: parseFloat(resp && resp.up_mult) || 1.01,
                roi_floor_pct: parseFloat(resp && resp.roi_floor_pct) || 40,
                trend_tolerance: parseFloat(resp && resp.trend_tolerance) || 0.1,
                actions: actions,
                yellow_pct: slabPct.red,
                slab_pct: slabPct,
                zero_down_dil: normalizeDilOverride(resp && resp.zero_down_dil),
                zero_equal_dil: normalizeDilOverride(resp && resp.zero_equal_dil),
                zero_up_dil: normalizeZeroUpDil(resp && resp.zero_up_dil),
                yellow_down_dil: normalizeDilOverride(resp && resp.yellow_down_dil),
                yellow_equal_dil: normalizeDilOverride(resp && resp.yellow_equal_dil),
                yellow_up_dil: normalizeYellowUpDil(resp && resp.yellow_up_dil),
                blue_down_dil: normalizeDilOverride(resp && resp.blue_down_dil),
                blue_equal_dil: normalizeDilOverride(resp && resp.blue_equal_dil),
                blue_up_dil: normalizeBlueUpDil(resp && resp.blue_up_dil),
                green_down_dil: normalizeDilOverride(resp && resp.green_down_dil),
                green_equal_dil: normalizeDilOverride(resp && resp.green_equal_dil),
                green_up_dil: normalizeGreenUpDil(resp && resp.green_up_dil),
                pink_down_dil: normalizeDilOverride(resp && resp.pink_down_dil),
                pink_equal_dil: normalizeDilOverride(resp && resp.pink_equal_dil),
                pink_up_dil: normalizeDilOverride(resp && resp.pink_up_dil),
                zero_cvr_dil: normalizeZeroCvrDil(resp && resp.zero_cvr_dil)
            };
        }

        /** CVR L30 treated as zero for Rule 1 (CVR trend + zero actions) */
        function amazonCvrIsZero(cvr) {
            const v = parseFloat(cvr);
            return !isFinite(v) || Math.abs(v) < 0.005;
        }

        function amazonRowCvrL30(rd) {
            if (!rd) return 0;
            // eBay3: SCVR is precomputed CVR L30 %
            const scvr = parseFloat(rd['SCVR']);
            if (isFinite(scvr)) return scvr;
            return 0;
        }

        /** Prior period CVR (L31–L60) — same as CVR L60 column: units_ordered_l60 / sessions_l60 */
        function amazonRowCvrL60(rd) {
            if (!rd) return 0;
            // eBay3: CVR_60 is prior-period (L31–L60) CVR %
            const cvr60 = parseFloat(rd['CVR_60']);
            if (isFinite(cvr60)) return cvr60;
            return 0;
        }

        /**
         * CVR L30 vs prior L31–L60 (CVR L60).
         * up = L30 higher · down = L30 lower · equal/same = within tol (±0.1%).
         */
        function amazonCvrTrend(rd, tol) {
            const cvrL30 = amazonRowCvrL30(rd);
            const cvrL60 = amazonRowCvrL60(rd);
            const t = (tol != null && isFinite(tol)) ? tol : 0.1;
            if (cvrL30 > cvrL60 + t) return 'up';
            if (cvrL30 < cvrL60 - t) return 'down';
            return 'equal';
        }

        /** Dil% = L30 / INV × 100 (same as Dil column). */
        function amazonRowDilPct(rd) {
            if (!rd) return 0;
            const inv = parseFloat(rd.INV) || 0;
            if (inv <= 0) return 0;
            const l30 = parseFloat(rd.L30) || 0;
            return (l30 / inv) * 100;
        }

        function normalizeDilOverride(raw) {
            let v = parseFloat(raw);
            if (!isFinite(v) || v < 0 || v > 500) v = SPRICE_CVR_DEFAULT_DIL_OVERRIDE;
            return +Number(v).toFixed(2);
        }

        function normalizeUpDilThreshold(raw, fallback) {
            return normalizeDilOverride(raw != null ? raw : fallback);
        }

        function normalizeYellowUpDil(raw) {
            return normalizeUpDilThreshold(raw, SPRICE_CVR_DEFAULT_YELLOW_UP_DIL);
        }

        function normalizeBlueUpDil(raw) {
            return normalizeUpDilThreshold(raw, SPRICE_CVR_DEFAULT_BLUE_UP_DIL);
        }

        function normalizeGreenUpDil(raw) {
            return normalizeUpDilThreshold(raw, SPRICE_CVR_DEFAULT_GREEN_UP_DIL);
        }

        /** Dil override UI removed — keep stored defaults for API compat only. */
        function readDilOverridesFromUi() {
            return JSON.parse(JSON.stringify(SPRICE_CVR_DEFAULT_DIL_OVERRIDES));
        }

        function applyDilOverridesToUi() {
            // no-op: Dil conditions removed from UI
        }

        /**
         * Signed % of PRICE (not PFT/GROI): +N raise, −N lower, 0 = hold at eBay price (0% change).
         * Returns null only when trend/rule missing — never silently upgrades 0 to +1.
         */
        function resolveSpriceCvrSignedPct(cvr, trend, rule, rd) {
            if (!trend || !rule) return null;
            const slabs = resolveSpriceCvrSlabs(rule);
            const slab = amazonCvrIsZero(cvr) ? 'zero' : amazonCvrSlab(cvr, slabs.low, slabs.mid, slabs.high);
            // Use signed % as stored/entered — do not re-interpret via legacy actions / Dil
            const slabPct = normalizeSlabPct(rule.slab_pct, rule.yellow_pct);
            const signed = slabPctForTrend(slabPct, slab, trend);
            if (!isFinite(signed)) return null;
            return signed;
        }

        /** Price multiplier: listing/base price × (1 + pct/100). Null when hold (0%). */
        function resolveSpriceCvrMult(cvr, trend, rule, rd) {
            const signed = resolveSpriceCvrSignedPct(cvr, trend, rule, rd);
            if (signed == null || !isFinite(signed) || signed === 0) return null;
            const mult = 1 + (signed / 100);
            return (isFinite(mult) && mult > 0) ? +mult.toFixed(6) : null;
        }

        /**
         * Base eBay listing price for % adjust (not PFT/GROI back-solve).
         * Prefer eBay Price; fall back to existing SPRICE only if price missing.
         */
        function amazonSpriceCvrBasePrice(rd) {
            if (!rd) return 0;
            const ebayPrice = parseFloat(rd['eBay Price']) || 0;
            if (ebayPrice > 0) return ebayPrice;
            return parseFloat(rd.SPRICE) || 0;
        }

        /**
         * Suggest SPRICE = eBay price × (1 + signed%/100). Of price only — never PFT%.
         * 0% → SPRICE = eBay price (clears prior wrong increases).
         */
        function applySpriceCvrPctToPrice(basePrice, signedPct) {
            const base = parseFloat(basePrice);
            const pct = parseFloat(signedPct);
            if (!isFinite(base) || base <= 0 || !isFinite(pct)) return null;
            if (pct === 0) return +base.toFixed(2);
            const sprice = +(base * (1 + pct / 100)).toFixed(2);
            return (isFinite(sprice) && sprice > 0) ? sprice : null;
        }

        /** Lowest SPRICE where Sroi = roi_floor_pct: (LP×(1+ROI/100)+Ship)/0.80 */
        function amazonSpriceRoiFloor(rd, roiFloorPct) {
            const lp = parseFloat(rd.LP_productmaster) || 0;
            if (lp <= 0) return null;
            const ship = parseFloat(rd.Ship_productmaster) || 0;
            const roi = (roiFloorPct != null && isFinite(roiFloorPct)) ? roiFloorPct : 40;
            const floor = (lp * (1 + roi / 100) + ship) / 0.80;
            if (!isFinite(floor) || floor <= 0) return null;
            return +Number(floor).toFixed(2);
        }

        /**
         * Yellow = 0.01 – 3.5 (fixed). Blue = 3.51 – mid (default 7).
         * Green = mid+0.01 – high. Pink > high+0.01. Defaults mid/high: 7 / 13.
         */
        function resolveSpriceCvrSlabs(ruleOrInputs) {
            const yellowStart = SPRICE_CVR_YELLOW_START;
            const low = SPRICE_CVR_YELLOW_END_DEFAULT; // Yellow end fixed at 3.5% per rule
            let mid = parseFloat(ruleOrInputs && ruleOrInputs.mid_cvr);
            let high = parseFloat(ruleOrInputs && ruleOrInputs.high_cvr);
            if (!isFinite(mid) || mid <= low) mid = 7;
            if (!isFinite(high) || high <= 0) high = 13;
            if (high <= mid) high = Math.min(100, mid + 6);
            if (high <= mid) {
                mid = 7; high = 13;
            }
            return {
                low: low,
                mid: mid,
                high: high,
                yellow_start: yellowStart,
                blue_start: +(low + 0.01).toFixed(2),
                pink_after: high + 0.01
            };
        }

        function readSpriceCvrSlabInputs() {
            return resolveSpriceCvrSlabs({
                low_cvr: String($('#sprice-cvr-low-input').val()).replace(',', '.'),
                mid_cvr: String($('#sprice-cvr-mid-input').val()).replace(',', '.'),
                high_cvr: String($('#sprice-cvr-high-input').val()).replace(',', '.')
            });
        }

        /**
         * 1 Red    : CVR = 0%              (CVR trend + zero actions, like Yellow)
         * 2 Yellow : 0.01 – low            (default 0.01–3.5; internal key: red)
         * 3 Blue   : low+0.01 – mid        (default 3.51–7)
         * 4 Green  : mid+0.01 – high       (default 7.01–13; values through high+0.01 stay green)
         * 5 Pink   : > high + 0.01         (default >13.01)
         */
        function amazonCvrSlab(cvr, low, mid, high) {
            const v = parseFloat(cvr) || 0;
            const pinkAfter = high + 0.01;
            if (v <= low) return 'red'; // Yellow 0.01 – low (CVR=0 handled separately)
            if (v <= mid) return 'blue';
            if (v <= pinkAfter) return 'green';
            return 'pink';
        }

        function countSpriceCvrPieBands(low, mid, high) {
            const counts = { zero: 0, yellow: 0, blue: 0, green: 0, pink: 0 };
            if (!table) return counts;
            const seen = new Set();
            table.getRows().forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (!sku || seen.has(sku)) return;
                const inv = parseFloat(rd.INV) || 0;
                if (inv <= 0) return;
                seen.add(sku);
                const cvr = amazonRowCvrL30(rd);
                if (amazonCvrIsZero(cvr)) {
                    counts.zero++;
                    return;
                }
                const slab = amazonCvrSlab(cvr, low, mid, high);
                if (slab === 'red') counts.yellow++;
                else counts[slab]++;
            });
            return counts;
        }

        function formatSlabBound(v) {
            const n = Number(v);
            if (!isFinite(n)) return String(v);
            return Number.isInteger(n) ? String(n) : String(+n.toFixed(2));
        }

        function refreshSpriceCvrSlabLabels() {
            const s = readSpriceCvrSlabInputs();
            const low = formatSlabBound(s.low);
            const mid = formatSlabBound(s.mid);
            const high = formatSlabBound(s.high);
            const yellowStart = formatSlabBound(s.yellow_start || SPRICE_CVR_YELLOW_START);
            const blueStart = formatSlabBound(s.blue_start != null ? s.blue_start : (s.low + 0.01));
            const greenStart = formatSlabBound(s.mid + 0.01);
            const pinkAfter = formatSlabBound(s.pink_after);
            $('#sprice-cvr-slab-red-label').text(yellowStart + ' – ' + low + '%');
            $('#sprice-cvr-slab-blue-label').text(blueStart + ' – ' + mid + '%');
            $('#sprice-cvr-slab-green-label').text(greenStart + ' – ' + high + '%');
            $('#sprice-cvr-slab-pink-label').text('> ' + pinkAfter + '%');
        }

        function pieChartTooltipLabel(ctx) {
            const v = ctx.parsed || 0;
            const sum = (ctx.dataset.data || []).reduce(function(a, b) {
                return a + (parseFloat(b) || 0);
            }, 0);
            const pct = sum > 0 ? ((v / sum) * 100).toFixed(1) : '0';
            return ctx.label + ': ' + v + ' (' + pct + '%)';
        }

        /** Legend fragment: "Label: 12 (34%)" — same clarity as summary pies */
        function spriceCvrLegendPart(label, count, total, color) {
            const n = parseInt(count, 10) || 0;
            const t = parseInt(total, 10) || 0;
            const pct = t > 0 ? ((n / t) * 100).toFixed(0) : '0';
            return '<span style="color:' + color + ';font-weight:600;">' +
                label + ': ' + n + ' (' + pct + '%)</span>';
        }

        function upsertPieChart(existing, canvasId, data, opts) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || typeof Chart === 'undefined') return existing;
            const hideLegend = !!(opts && opts.hideLegend);
            if (existing) {
                existing.data = data;
                if (existing.options && existing.options.plugins && existing.options.plugins.legend) {
                    existing.options.plugins.legend.display = !hideLegend;
                }
                existing.update();
                return existing;
            }
            return new Chart(canvas.getContext('2d'), {
                type: 'pie',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: !hideLegend,
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 9 } }
                        },
                        tooltip: { callbacks: { label: pieChartTooltipLabel } }
                    }
                }
            });
        }

        function refreshSpriceCvrPieChart() {
            const s = readSpriceCvrSlabInputs();
            const counts = countSpriceCvrPieBands(s.low, s.mid, s.high);
            const total = counts.zero + counts.yellow + counts.blue + counts.green + counts.pink;
            const low = formatSlabBound(s.low);
            const mid = formatSlabBound(s.mid);
            const high = formatSlabBound(s.high);
            const yellowStart = formatSlabBound(s.yellow_start || SPRICE_CVR_YELLOW_START);
            const yellowLabel = yellowStart + '–' + low + '%';
            const blueStart = formatSlabBound(s.blue_start != null ? s.blue_start : (s.low + 0.01));
            const greenStart = formatSlabBound(s.mid + 0.01);
            const pinkAfter = formatSlabBound(s.pink_after);
            $('#sprice-cvr-pie-legend').html(
                total === 0
                    ? 'No INV &gt; 0 SKUs'
                    : ('<span style="color:#a00211;font-weight:600;">Red CVR=0%: ' + counts.zero + '</span>' +
                        ' · <span style="color:#e6b800;font-weight:600;">Yellow ' + yellowLabel + ': ' + counts.yellow + '</span>' +
                        ' · <span style="color:#0d6efd;font-weight:600;">Blue ' + blueStart + '–' + mid + ': ' + counts.blue + '</span>' +
                        ' · <span style="color:#28a745;font-weight:600;">Green ' + greenStart + '–' + high + ': ' + counts.green + '</span>' +
                        ' · <span style="color:#e83e8c;font-weight:600;">Pink &gt;' + pinkAfter + ': ' + counts.pink + '</span>' +
                        ' <span class="text-muted">(n=' + total + ')</span>')
            );
            spriceCvrPieChart = upsertPieChart(spriceCvrPieChart, 'sprice-cvr-pie-chart', {
                labels: [
                    'Red CVR=0%',
                    'Yellow ' + yellowLabel,
                    'Blue ' + blueStart + '–' + mid + '%',
                    'Green ' + greenStart + '–' + high + '%',
                    'Pink >' + pinkAfter + '%'
                ],
                datasets: [{
                    data: [counts.zero, counts.yellow, counts.blue, counts.green, counts.pink],
                    backgroundColor: ['#a00211', '#ffc107', '#0d6efd', '#28a745', '#e83e8c'],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            }, { hideLegend: true });
            refreshSpriceCvrZeroPieChart();
            refreshSpriceCvrTrendPieCharts();
            refreshSpriceCvrActionPieChart();
            syncSpriceCvrSummaryPieSizes();
            scheduleSpriceCvrDailySnapshot();
        }

        /** Rule 1: INV>0 SKUs with CVR=0%, split by CVR Down / Equal / Up trend (like Rule 2) */
        function countSpriceCvrZeroDilBands() {
            const counts = { down: 0, equal: 0, up: 0, none: 0, low: 0, mid: 0, high: 0 };
            if (!table) return counts;
            const tol = spriceCvrRule.trend_tolerance || 0.1;
            const seen = new Set();
            table.getRows().forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (!sku || seen.has(sku)) return;
                const inv = parseFloat(rd.INV) || 0;
                if (inv <= 0) return;
                if (!amazonCvrIsZero(amazonRowCvrL30(rd))) return;
                seen.add(sku);
                const trend = amazonCvrTrend(rd, tol);
                if (trend === 'down') { counts.down++; counts.low++; }
                else if (trend === 'equal') { counts.equal++; counts.mid++; }
                else if (trend === 'up') { counts.up++; counts.high++; }
                else counts.none++;
            });
            return counts;
        }

        /** Size rule pie canvas to the under-rule pie card (~20% inset) */
        function syncSpriceCvrRulePieSize(key, chart) {
            const $row = $('.sprice-cvr-rule-pie-row[data-rule-pie="' + key + '"]');
            const $canvasWrap = $row.find('.sprice-cvr-rule-pie-canvas[data-pie-canvas="' + key + '"]');
            const pieCard = $row.find('.sprice-cvr-rule-pie-wrap');
            if (!$canvasWrap.length || !pieCard.length) return;
            const pieH = pieCard.innerHeight() || 0;
            const headerH = (pieCard.find('.fw-bold').first().outerHeight(true) || 0) +
                (pieCard.find('.sprice-cvr-rule-pie-legend, [id$="-pie-legend"]').first().outerHeight(true) || 0) + 2;
            const canvasH = Math.max(36, Math.round((pieH - headerH) * 0.8));
            $canvasWrap.css('height', canvasH + 'px');
            if (chart && typeof chart.resize === 'function') chart.resize();
        }

        function syncSpriceCvrZeroPieSize() {
            syncSpriceCvrRulePieSize('zero', spriceCvrZeroPieChart);
        }

        function syncSpriceCvrAllRulePieSizes() {
            syncSpriceCvrZeroPieSize();
            Object.keys(SPRICE_CVR_TREND_PIE_META).forEach(function(key) {
                syncSpriceCvrRulePieSize(key, spriceCvrTrendPieCharts[key]);
            });
            syncSpriceCvrSummaryPieSizes();
        }

        function syncSpriceCvrSummaryPieSizes() {
            $('.sprice-cvr-summary-stack > .border').each(function() {
                const $card = $(this);
                const $canvas = $card.find('.sprice-cvr-summary-canvas');
                if (!$canvas.length) return;
                const h = $card.innerHeight() || 0;
                const headerH = ($card.find('.fw-bold').outerHeight(true) || 0) +
                    ($card.find('.sprice-cvr-compact-legend').outerHeight(true) || 0) + 6;
                $canvas.css('height', Math.max(60, h - headerH) + 'px');
            });
            if (spriceCvrPieChart && spriceCvrPieChart.resize) spriceCvrPieChart.resize();
            if (spriceCvrActionPieChart && spriceCvrActionPieChart.resize) spriceCvrActionPieChart.resize();
        }

        function refreshSpriceCvrZeroPieChart() {
            const counts = countSpriceCvrZeroDilBands();
            const total = counts.down + counts.equal + counts.up + counts.none;
            $('#sprice-cvr-zero-pie-legend').html(
                total === 0
                    ? 'No INV &gt; 0 SKUs with CVR = 0%'
                    : (spriceCvrLegendPart('CVR % = Down', counts.down, total, '#c92a2a') +
                        ' · ' + spriceCvrLegendPart('CVR % = Same', counts.equal, total, '#e03131') +
                        ' · ' + spriceCvrLegendPart('CVR % = Up', counts.up, total, '#a00211') +
                        (counts.none
                            ? (' · ' + spriceCvrLegendPart('No trend', counts.none, total, '#ced4da'))
                            : '') +
                        ' <span class="text-muted">(n=' + total + ')</span>')
            );
            spriceCvrZeroPieChart = upsertPieChart(spriceCvrZeroPieChart, 'sprice-cvr-zero-pie-chart', {
                labels: ['CVR % = Down', 'CVR % = Same', 'CVR % = Up', 'No trend'],
                datasets: [{
                    data: [counts.down, counts.equal, counts.up, counts.none],
                    backgroundColor: ['#ff8787', '#fa5252', '#a00211', '#ced4da'],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            }, { hideLegend: true });
            requestAnimationFrame(syncSpriceCvrZeroPieSize);
        }

        /** INV>0 SKUs in a CVR slab, split by Down / Equal / Up trend */
        function countSpriceCvrSlabTrendBands(slabKey) {
            const counts = { down: 0, equal: 0, up: 0, none: 0 };
            if (!table) return counts;
            const slabs = resolveSpriceCvrSlabs(readSpriceCvrSlabInputs());
            const tol = spriceCvrRule.trend_tolerance || 0.1;
            const seen = new Set();
            table.getRows().forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (!sku || seen.has(sku)) return;
                const inv = parseFloat(rd.INV) || 0;
                if (inv <= 0) return;
                const cvr = amazonRowCvrL30(rd);
                if (amazonCvrIsZero(cvr)) return;
                if (amazonCvrSlab(cvr, slabs.low, slabs.mid, slabs.high) !== slabKey) return;
                seen.add(sku);
                const trend = amazonCvrTrend(rd, tol);
                if (trend === 'down' || trend === 'equal' || trend === 'up') counts[trend]++;
                else counts.none++;
            });
            return counts;
        }

        function refreshSpriceCvrTrendPieChart(uiKey) {
            const meta = SPRICE_CVR_TREND_PIE_META[uiKey];
            if (!meta) return;
            const counts = countSpriceCvrSlabTrendBands(meta.slab);
            const total = counts.down + counts.equal + counts.up + counts.none;
            const c = meta.colors;
            $('#sprice-cvr-' + uiKey + '-pie-legend').html(
                total === 0
                    ? ('No INV &gt; 0 SKUs in ' + meta.label)
                    : (spriceCvrLegendPart('CVR % = Down', counts.down, total, c[0]) +
                        ' · ' + spriceCvrLegendPart('CVR % = Same', counts.equal, total, c[1]) +
                        ' · ' + spriceCvrLegendPart('CVR % = Up', counts.up, total, c[2]) +
                        (counts.none
                            ? (' · ' + spriceCvrLegendPart('No trend', counts.none, total, c[3] || '#ced4da'))
                            : '') +
                        ' <span class="text-muted">(n=' + total + ')</span>')
            );
            spriceCvrTrendPieCharts[uiKey] = upsertPieChart(
                spriceCvrTrendPieCharts[uiKey],
                'sprice-cvr-' + uiKey + '-pie-chart',
                {
                    labels: ['CVR % = Down', 'CVR % = Same', 'CVR % = Up', 'No trend'],
                    datasets: [{
                        data: [counts.down, counts.equal, counts.up, counts.none],
                        backgroundColor: c,
                        borderWidth: 1,
                        borderColor: '#fff'
                    }]
                },
                { hideLegend: true }
            );
            requestAnimationFrame(function() {
                syncSpriceCvrRulePieSize(uiKey, spriceCvrTrendPieCharts[uiKey]);
            });
        }

        function refreshSpriceCvrTrendPieCharts() {
            Object.keys(SPRICE_CVR_TREND_PIE_META).forEach(refreshSpriceCvrTrendPieChart);
        }

        /** Count INV>0 SKUs by rule action: Increase / Decrease / Hold */
        function countSpriceCvrActionBands() {
            const counts = { increase: 0, decrease: 0, hold: 0 };
            if (!table) return counts;
            const slabsLive = readSpriceCvrSlabInputs();
            const zeroDil = readZeroCvrDilFromUi();
            const rule = {
                low_cvr: slabsLive.low,
                mid_cvr: slabsLive.mid,
                high_cvr: slabsLive.high,
                down_mult: spriceCvrRule.down_mult,
                up_mult: spriceCvrRule.up_mult,
                trend_tolerance: spriceCvrRule.trend_tolerance,
                actions: readSpriceCvrActionsFromUi(),
                yellow_pct: readYellowPctFromUi(),
                slab_pct: readSlabPctFromUi(),
                ...readDilOverridesFromUi(),
                zero_cvr_dil: zeroDil
            };
            const seen = new Set();
            table.getRows().forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (!sku || seen.has(sku)) return;
                const inv = parseFloat(rd.INV) || 0;
                if (inv <= 0) return;
                seen.add(sku);

                const cvr = amazonRowCvrL30(rd);
                const trend = amazonCvrTrend(rd, rule.trend_tolerance);
                const mult = resolveSpriceCvrMult(cvr, trend, rule, rd);
                if (mult == null || !isFinite(mult) || mult === 1) {
                    counts.hold++;
                    return;
                }
                if (mult > 1) counts.increase++;
                else if (mult < 1) counts.decrease++;
                else counts.hold++;
            });
            return counts;
        }

        function refreshSpriceCvrActionPieChart() {
            const counts = countSpriceCvrActionBands();
            const total = counts.increase + counts.decrease + counts.hold;
            $('#sprice-cvr-action-pie-legend').html(
                total === 0
                    ? 'No INV &gt; 0 SKUs'
                    : ('<span style="color:#28a745;font-weight:600;">Increased: ' + counts.increase + '</span>' +
                        ' · <span style="color:#a00211;font-weight:600;">Decreased: ' + counts.decrease + '</span>' +
                        ' · <span style="color:#6c757d;font-weight:600;">Hold: ' + counts.hold + '</span>' +
                        ' <span class="text-muted">(n=' + total + ')</span>')
            );
            spriceCvrActionPieChart = upsertPieChart(spriceCvrActionPieChart, 'sprice-cvr-action-pie-chart', {
                labels: ['Increased', 'Decreased', 'Hold'],
                datasets: [{
                    data: [counts.increase, counts.decrease, counts.hold],
                    backgroundColor: ['#28a745', '#a00211', '#6c757d'],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            }, { hideLegend: true });
        }

        let spriceCvrHistoryChart = null;
        let spriceCvrHistoryType = 'slab';
        let spriceCvrHistoryDays = 30;
        let spriceCvrHistoryAjax = null;
        let spriceCvrSnapshotTimer = null;

        function saveSpriceCvrDailySnapshot() {
            // eBay3: live pies only — do not overwrite Amazon daily stats table
            return;
            if (!table) return;
            const s = readSpriceCvrSlabInputs();
            const slabCounts = countSpriceCvrPieBands(s.low, s.mid, s.high);
            const actionCounts = countSpriceCvrActionBands();
            const zeroBands = countSpriceCvrZeroDilBands();
            const rulePies = {
                zero: zeroBands,
                yellow: countSpriceCvrSlabTrendBands('red'),
                blue: countSpriceCvrSlabTrendBands('blue'),
                green: countSpriceCvrSlabTrendBands('green'),
                pink: countSpriceCvrSlabTrendBands('pink')
            };
            // History still stores 3 bands: yellow+zero → red; blue+green → green
            const redBand = (slabCounts.zero || 0) + (slabCounts.yellow || 0);
            const greenBand = (slabCounts.blue || 0) + (slabCounts.green || 0);
            const total = redBand + greenBand + slabCounts.pink;
            if (total <= 0) return;
            $.ajax({
                url: '/amazon-sprice-cvr-stats-save',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    red_count: redBand,
                    green_count: greenBand,
                    pink_count: slabCounts.pink,
                    increased_count: actionCounts.increase,
                    decreased_count: actionCounts.decrease,
                    hold_count: actionCounts.hold,
                    low_cvr: s.low,
                    high_cvr: s.high,
                    rule_pies: JSON.stringify(rulePies)
                }
            });
        }

        function scheduleSpriceCvrDailySnapshot() {
            if (spriceCvrSnapshotTimer) clearTimeout(spriceCvrSnapshotTimer);
            spriceCvrSnapshotTimer = setTimeout(saveSpriceCvrDailySnapshot, 600);
        }

        const SPRICE_CVR_HISTORY_TITLES = {
            slab: 'Sprice × CVR — Sprice × CVR Slab History',
            action: 'Sprice × CVR — Sprice × CVR Action History',
            rule_zero: 'Sprice × CVR — Rule 1 Trend History',
            rule_yellow: 'Sprice × CVR — Rule 2 Yellow Trend History',
            rule_blue: 'Sprice × CVR — Rule 3 Blue Trend History',
            rule_green: 'Sprice × CVR — Rule 4 Green Trend History',
            rule_pink: 'Sprice × CVR — Rule 5 Pink Trend History'
        };

        function openSpriceCvrHistoryModal(type) {
            const t = String(type || 'slab');
            spriceCvrHistoryType = SPRICE_CVR_HISTORY_TITLES[t] ? t : 'slab';
            spriceCvrHistoryDays = parseInt($('#sprice-cvr-history-days').val(), 10);
            if (!isFinite(spriceCvrHistoryDays)) spriceCvrHistoryDays = 30;
            const title = SPRICE_CVR_HISTORY_TITLES[spriceCvrHistoryType] || SPRICE_CVR_HISTORY_TITLES.slab;
            const rangeLabel = spriceCvrHistoryDays === 0 ? 'Lifetime' : ('L' + spriceCvrHistoryDays);
            $('#spriceCvrHistoryModalTitle').text(title);
            $('#spriceCvrHistoryModalSuffix').text('(Rolling ' + rangeLabel + ')');
            const el = document.getElementById('spriceCvrHistoryModal');
            if (el && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $('#spriceCvrHistoryModal').modal('show');
            }
            loadSpriceCvrHistoryChart();
        }

        function loadSpriceCvrHistoryChart() {
            if (spriceCvrHistoryAjax) spriceCvrHistoryAjax.abort();
            $('#spriceCvrHistoryNoData').hide();
            $('#spriceCvrHistoryContainer').hide();
            $('#spriceCvrHistoryLoading').show();
            spriceCvrHistoryAjax = $.ajax({
                url: '/amazon-sprice-cvr-history',
                method: 'GET',
                data: { type: spriceCvrHistoryType, days: spriceCvrHistoryDays },
                success: function(resp) {
                    spriceCvrHistoryAjax = null;
                    $('#spriceCvrHistoryLoading').hide();
                    if (resp && resp.success && resp.labels && resp.labels.length && resp.series && resp.series.length) {
                        $('#spriceCvrHistoryContainer').show();
                        renderSpriceCvrHistoryChart(resp.labels, resp.series);
                    } else {
                        $('#spriceCvrHistoryNoData').show();
                    }
                },
                error: function(xhr, status) {
                    spriceCvrHistoryAjax = null;
                    if (status === 'abort') return;
                    $('#spriceCvrHistoryLoading').hide();
                    $('#spriceCvrHistoryNoData').show();
                }
            });
        }

        function renderSpriceCvrHistoryChart(labels, series) {
            const canvas = document.getElementById('spriceCvrHistoryChart');
            if (!canvas || typeof Chart === 'undefined') return;
            const allVals = [];
            series.forEach(function(s) {
                (s.data || []).forEach(function(v) { allVals.push(parseFloat(v) || 0); });
            });
            const dataMin = allVals.length ? Math.min.apply(null, allVals) : 0;
            const dataMax = allVals.length ? Math.max.apply(null, allVals) : 0;
            const sorted = allVals.slice().sort(function(a, b) { return a - b; });
            const mid = Math.floor(sorted.length / 2);
            const median = !sorted.length ? 0
                : (sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2);
            const range = (dataMax - dataMin) || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;
            $('#spriceCvrHistoryHighest').text(Math.round(dataMax).toLocaleString());
            $('#spriceCvrHistoryMedian').text(Math.round(median).toLocaleString());
            $('#spriceCvrHistoryLowest').text(Math.round(dataMin).toLocaleString());

            if (spriceCvrHistoryChart) {
                spriceCvrHistoryChart.destroy();
                spriceCvrHistoryChart = null;
            }
            spriceCvrHistoryChart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: series.map(function(s) {
                        return {
                            label: s.label,
                            data: s.data,
                            borderColor: s.color,
                            backgroundColor: s.color,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            tension: 0.25,
                            fill: false
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const v = ctx.parsed.y;
                                    return ctx.dataset.label + ': ' + Math.round(v).toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { size: 10 } } },
                        y: {
                            min: yMin,
                            max: yMax,
                            ticks: {
                                font: { size: 10 },
                                callback: function(v) { return Math.round(v).toLocaleString(); }
                            }
                        }
                    }
                }
            });
        }

        $(document).on('click', '.sprice-cvr-history-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openSpriceCvrHistoryModal($(this).data('history-type'));
        });
        $('#sprice-cvr-history-days').on('change', function() {
            const days = parseInt($(this).val(), 10);
            if (days === spriceCvrHistoryDays) return;
            spriceCvrHistoryDays = days;
            const rangeLabel = days === 0 ? 'Lifetime' : ('L' + days);
            $('#spriceCvrHistoryModalSuffix').text('(Rolling ' + rangeLabel + ')');
            loadSpriceCvrHistoryChart();
        });

        function refreshSpriceCvrUi() {
            const r = spriceCvrRule;
            const slabs = resolveSpriceCvrSlabs(r);
            // Yellow end fixed at 3.5%; keep mid/high open if a bad save collapsed them
            r.low_cvr = SPRICE_CVR_YELLOW_END_DEFAULT;
            if (parseFloat(r.mid_cvr) <= r.low_cvr) r.mid_cvr = slabs.mid;
            if (parseFloat(r.high_cvr) <= parseFloat(r.mid_cvr)) r.high_cvr = slabs.high;
            if (!r.actions) r.actions = normalizeSpriceCvrActions(null);
            r.slab_pct = normalizeSlabPct(r.slab_pct, r.yellow_pct, r.actions);
            r.yellow_pct = r.slab_pct.red;
            $('#sprice-cvr-btn-label').text('Sprice×CVR');
            $('#sprice-cvr-low-input').val(SPRICE_CVR_YELLOW_END_DEFAULT);
            $('#sprice-cvr-mid-input').val(r.mid_cvr);
            $('#sprice-cvr-high-input').val(r.high_cvr);
            $('#sprice-cvr-roi-floor-input').val(r.roi_floor_pct);
            applySlabPctToUi(r.slab_pct);
            applyZeroCvrDilToUi(r.zero_cvr_dil);
            r.actions = readSpriceCvrActionsFromUi();
            refreshSpriceCvrSlabLabels();
            const sp = r.slab_pct;
            $('#apply-sprice-cvr-btn').attr('title',
                'SPRICE = eBay price × (1 + %/100) — not PFT%: +inc / −dec / 0=hold at eBay price · Z ' +
                sp.zero.down + '/' + sp.zero.equal + '/' + sp.zero.up +
                ' · Y ' + sp.red.down + '/' + sp.red.equal + '/' + sp.red.up +
                ' · B ' + sp.blue.down + '/' + sp.blue.equal + '/' + sp.blue.up +
                ' · G ' + sp.green.down + '/' + sp.green.equal + '/' + sp.green.up +
                ' · P ' + sp.pink.down + '/' + sp.pink.equal + '/' + sp.pink.up);
            syncCvrFilterSlabOptions();
        }

        /** Keep toolbar CVR% filter labels in sync with Sprice × CVR Rule slabs */
        function syncCvrFilterSlabOptions() {
            const $sel = $('#cvr-filter');
            if (!$sel.length) return;
            const s = resolveSpriceCvrSlabs(spriceCvrRule || {});
            const yellowStart = formatSlabBound(s.yellow_start || SPRICE_CVR_YELLOW_START);
            const low = formatSlabBound(s.low);
            const mid = formatSlabBound(s.mid);
            const high = formatSlabBound(s.high);
            const blueStart = formatSlabBound(s.blue_start != null ? s.blue_start : (s.low + 0.01));
            const greenStart = formatSlabBound(s.mid + 0.01);
            const pinkAfter = formatSlabBound(s.pink_after != null ? s.pink_after : (s.high + 0.01));
            $sel.find('option[value="0-0"]').text('0%');
            $sel.find('option[value="0-3"]').text(yellowStart + '–' + low + '%');
            $sel.find('option[value="3-7"]').text(blueStart + '–' + mid + '%');
            $sel.find('option[value="7-13"]').text(greenStart + '–' + high + '%');
            $sel.find('option[value="13plus"]').html('&gt;' + pinkAfter + '%');
        }

        function loadSpriceCvrRule() {
            $.ajax({
                url: SPRICE_CVR_URL,
                method: 'GET',
                success: function(resp) {
                    if (resp && typeof resp === 'object') {
                        spriceCvrRule = normalizeSpriceCvrRule(resp);
                    }
                    refreshSpriceCvrUi();
                    syncCvrFilterSlabOptions();
                },
                error: function() {
                    refreshSpriceCvrUi();
                    syncCvrFilterSlabOptions();
                }
            });
        }

        function saveSpriceCvrRuleFromModal() {
            try {
                const slabs = readSpriceCvrSlabInputs();
                const zeroDil = readZeroCvrDilFromUi();
                const slabPct = readSlabPctFromUi();
                const payload = Object.assign({
                    low_cvr: SPRICE_CVR_YELLOW_END_DEFAULT,
                    mid_cvr: slabs.mid,
                    high_cvr: slabs.high,
                    down_mult: spriceCvrRule.down_mult || 0.99,
                    up_mult: spriceCvrRule.up_mult || 1.01,
                    roi_floor_pct: parseFloat(String($('#sprice-cvr-roi-floor-input').val()).replace(',', '.')),
                    actions: readSpriceCvrActionsFromUi(),
                    yellow_pct: slabPct.red,
                    slab_pct: slabPct,
                    zero_cvr_dil: zeroDil
                }, readDilOverridesFromUi());
                const pctOk = SPRICE_CVR_SLABS.every(function(slab) {
                    const p = slabPct[slab];
                    return p && isFinite(p.down) && isFinite(p.equal) && isFinite(p.up);
                });
                if (!isFinite(payload.mid_cvr) || !isFinite(payload.high_cvr) ||
                    !isFinite(payload.roi_floor_pct) || !pctOk) {
                    $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger')
                        .text('Enter valid numbers for all rule % fields');
                    return;
                }
                if (!(payload.mid_cvr > payload.low_cvr && payload.high_cvr > payload.mid_cvr)) {
                    $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger')
                        .text('Need Blue≤ > 3.5 and Green≤ > Blue≤');
                    return;
                }
                const $btn = $('#sprice-cvr-save-btn');
                const btnHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $.ajax({
                    url: SPRICE_CVR_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify(payload),
                    success: function(resp) {
                        if (resp && resp.rule) spriceCvrRule = normalizeSpriceCvrRule(resp.rule);
                        refreshSpriceCvrUi();
                        refreshSpriceCvrPieChart();
                        $('#sprice-cvr-modal-status').removeClass('text-danger').addClass('text-success').text('Saved');
                        if (typeof spriceCvrShowToast === 'function') spriceCvrShowToast('success', 'Sprice × CVR rule saved');
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || ('Failed to save' + (xhr.status ? ' (' + xhr.status + ')' : ''));
                        $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger').text(msg);
                        if (typeof spriceCvrShowToast === 'function') spriceCvrShowToast('error', msg);
                    },
                    complete: function() { $btn.prop('disabled', false).html(btnHtml); }
                });
            } catch (err) {
                console.error('saveSpriceCvrRuleFromModal', err);
                $('#sprice-cvr-modal-status').removeClass('text-success').addClass('text-danger')
                    .text('Save failed: ' + (err && err.message ? err.message : 'JS error'));
            }
        }

        loadSpriceCvrRule();
        const SPRICE_CVR_BADGE_THRESHOLDS = {
            groi_pct: 80,
            nroi_pct: 60,
            gpft_pct: 35,
            npft_pct: 25
        };
        let spriceCvrPrevDayMetrics = null;
        let spriceCvrPrevDayLoaded = false;

        function parseBadgePct(text) {
            const m = String(text || '').match(/-?\d+(\.\d+)?/);
            return m ? parseFloat(m[0]) : NaN;
        }

        function applySpriceCvrBadgeThreshold($badge, pct) {
            const metric = $badge.data('metric');
            const thr = SPRICE_CVR_BADGE_THRESHOLDS[metric];
            const ok = isFinite(pct) && isFinite(thr) && pct >= thr;
            $badge.toggleClass('sprice-cvr-badge-ok', ok).toggleClass('sprice-cvr-badge-bad', !ok);
        }

        function applySpriceCvrTrendDot(metric, currentPct) {
            const $dot = $('#sprice-cvr-' + metric.replace('_pct', '') + '-trend');
            // ids: groi/nroi/gpft/npft — map metric keys
            const idMap = {
                groi_pct: '#sprice-cvr-groi-trend',
                nroi_pct: '#sprice-cvr-nroi-trend',
                gpft_pct: '#sprice-cvr-gpft-trend',
                npft_pct: '#sprice-cvr-npft-trend'
            };
            const $el = $(idMap[metric] || $dot);
            if (!$el.length) return;
            const prev = spriceCvrPrevDayMetrics && spriceCvrPrevDayMetrics[metric];
            if (!isFinite(currentPct) || prev == null || !isFinite(prev)) {
                $el.attr('class', 'sprice-cvr-trend-dot none').attr('title', '');
                return;
            }
            const diff = currentPct - prev;
            let cls = 'flat';
            let tip = 'Unchanged vs previous day (' + Math.round(prev) + '%)';
            if (diff > 0.05) {
                cls = 'up';
                tip = 'Up vs previous day (' + Math.round(prev) + '% → ' + Math.round(currentPct) + '%)';
            } else if (diff < -0.05) {
                cls = 'down';
                tip = 'Down vs previous day (' + Math.round(prev) + '% → ' + Math.round(currentPct) + '%)';
            }
            $el.attr('class', 'sprice-cvr-trend-dot ' + cls).attr('title', tip);
        }

        function loadSpriceCvrPrevDayMetrics(done) {
            if (spriceCvrPrevDayLoaded) {
                if (typeof done === 'function') done();
                return;
            }
            $.ajax({
                url: '/amazon-badge-prev-day',
                method: 'GET',
                success: function(resp) {
                    spriceCvrPrevDayLoaded = true;
                    spriceCvrPrevDayMetrics = (resp && resp.success && resp.metrics) ? resp.metrics : null;
                    if (typeof done === 'function') done();
                },
                error: function() {
                    spriceCvrPrevDayLoaded = true;
                    spriceCvrPrevDayMetrics = null;
                    if (typeof done === 'function') done();
                }
            });
        }

        function syncSpriceCvrProfitBadges() {
            // Mirror summary badges (updateSummary); order GROI/NROI then GPFT/NPFT
            const vals = {
                groi_pct: parseBadgePct(($('#groi-percent-badge').text() || '').replace(/^GROI:\s*/i, '')),
                nroi_pct: parseBadgePct(($('#nroi-percent-badge').text() || '').replace(/^NROI:\s*/i, '')),
                gpft_pct: parseBadgePct(($('#avg-gpft-badge').text() || '').replace(/^GPFT:\s*/i, '')),
                npft_pct: parseBadgePct(($('#npft-percent-badge').text() || '').replace(/^NPFT:\s*/i, ''))
            };
            const fmt = function(n) { return (isFinite(n) ? Math.round(n) : 0) + '%'; };
            $('#sprice-cvr-groi-val').text(fmt(vals.groi_pct));
            $('#sprice-cvr-nroi-val').text(fmt(vals.nroi_pct));
            $('#sprice-cvr-gpft-val').text(fmt(vals.gpft_pct));
            $('#sprice-cvr-npft-val').text(fmt(vals.npft_pct));

            ['groi_pct', 'nroi_pct', 'gpft_pct', 'npft_pct'].forEach(function(metric) {
                const $badge = $('#sprice-cvr-' + metric.replace('_pct', '') + '-badge');
                // gpft/npft ids don't follow replace pattern for gpft_pct → gpft ✓, npft_pct → npft ✓
                applySpriceCvrBadgeThreshold($badge, vals[metric]);
                applySpriceCvrTrendDot(metric, vals[metric]);
            });

            loadSpriceCvrPrevDayMetrics(function() {
                ['groi_pct', 'nroi_pct', 'gpft_pct', 'npft_pct'].forEach(function(metric) {
                    applySpriceCvrTrendDot(metric, vals[metric]);
                });
            });
        }

        $('#spriceCvrRuleModal').on('shown.bs.modal', function() {
            refreshSpriceCvrUi();
            refreshSpriceCvrPieChart();
            syncSpriceCvrProfitBadges();
            requestAnimationFrame(syncSpriceCvrAllRulePieSizes);
            $('#sprice-cvr-modal-status').removeClass('text-danger text-success').addClass('text-muted').text('');
        });
        $(window).on('resize.spriceCvrModal', function() {
            if ($('#spriceCvrRuleModal').hasClass('show')) {
                syncSpriceCvrAllRulePieSizes();
            }
        });
        $(document).on('click', '.sprice-cvr-metric-history-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(this).data('metric');
            if (!metric || typeof showEbay3MetricChart !== 'function') return;
            showEbay3MetricChart(metric);
        });
        $('#sprice-cvr-low-input, #sprice-cvr-mid-input, #sprice-cvr-high-input').on('input change', function() {
            refreshSpriceCvrSlabLabels();
            refreshSpriceCvrPieChart();
        });
        $(document).on('input change', '.sprice-cvr-slab-pct-input', function() {
            spriceCvrRule.slab_pct = readSlabPctFromUi();
            spriceCvrRule.yellow_pct = spriceCvrRule.slab_pct.red;
            spriceCvrRule.actions = readSpriceCvrActionsFromUi();
            refreshSpriceCvrPieChart();
        });
        $('#sprice-cvr-save-btn').on('click', saveSpriceCvrRuleFromModal);

        $('#apply-sprice-cvr-btn').on('click', function() {
            const $btn = $(this);
            const slabsLive = readSpriceCvrSlabInputs();
            const zeroDil = readZeroCvrDilFromUi();
            const slabPct = readSlabPctFromUi();
            // Keep in-memory rule in sync so post-apply UI refresh does not wipe Blue ±% etc.
            spriceCvrRule = Object.assign({}, spriceCvrRule, {
                low_cvr: slabsLive.low,
                mid_cvr: slabsLive.mid,
                high_cvr: slabsLive.high,
                down_mult: spriceCvrRule.down_mult || 0.99,
                up_mult: spriceCvrRule.up_mult || 1.01,
                roi_floor_pct: parseFloat(String($('#sprice-cvr-roi-floor-input').val()).replace(',', '.')) || spriceCvrRule.roi_floor_pct,
                actions: readSpriceCvrActionsFromUi(),
                yellow_pct: slabPct.red,
                slab_pct: slabPct,
                zero_cvr_dil: zeroDil
            });
            const rule = spriceCvrRule;
            const btnHtml = '<i class="fas fa-percentage"></i> <span id="sprice-cvr-btn-label">' +
                $('#sprice-cvr-btn-label').text() + '</span>';
            if (!table) {
                spriceCvrShowToast('error', 'Table not ready');
                return;
            }

            const effectiveSelected = new Set();
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(function(s) { if (s) effectiveSelected.add(s); });
            }
            if (typeof selectedRows !== 'undefined' && selectedRows && selectedRows.forEach) {
                selectedRows.forEach(function(s) { if (s) effectiveSelected.add(s); });
            }
            // Only honor selection when at least one selected SKU is in the active (filtered) set.
            // Stale selections from a prior filter used to skip all visible Blue rows.
            let selectedInActive = 0;
            table.getRows('active').forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (sku && effectiveSelected.has(sku)) selectedInActive++;
            });
            const useSelection = selectedInActive > 0;
            const rowsToProcess = [];
            const seen = new Set();
            let zeroCvrCount = 0;
            let holdSkipCount = 0;
            const slabHit = { zero: 0, red: 0, blue: 0, green: 0, pink: 0 };

            table.getRows('active').forEach(function(r) {
                const rd = r.getData();
                if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                const sku = rd['(Child) sku'];
                if (!sku || seen.has(sku)) return;
                if (useSelection && !effectiveSelected.has(sku)) return;

                const cvr = amazonRowCvrL30(rd);
                const trend = amazonCvrTrend(rd, rule.trend_tolerance);
                const slabs = resolveSpriceCvrSlabs(rule);
                const slabKey = amazonCvrIsZero(cvr) ? 'zero' : amazonCvrSlab(cvr, slabs.low, slabs.mid, slabs.high);
                const signedPct = resolveSpriceCvrSignedPct(cvr, trend, rule, rd);
                if (signedPct == null) {
                    holdSkipCount++;
                    return;
                }

                // Always % of eBay listing price (not prior SPRICE / not PFT%).
                // 0% → SPRICE = eBay price (true hold / no increase — clears stale +% suggestions).
                const base = amazonSpriceCvrBasePrice(rd);
                const sprice = applySpriceCvrPctToPrice(base, signedPct);
                if (sprice == null) {
                    holdSkipCount++;
                    return;
                }

                seen.add(sku);
                if (amazonCvrIsZero(cvr)) zeroCvrCount++;
                if (slabHit[slabKey] != null) slabHit[slabKey]++;
                rowsToProcess.push({
                    row: r, sku: sku, sprice: sprice, trend: trend, slab: slabKey,
                    mult: 1 + signedPct / 100, signed_pct: signedPct
                });
            });

            if (rowsToProcess.length === 0) {
                spriceCvrShowToast('warning', useSelection
                    ? 'No selected rows match CVR trend rules (' + holdSkipCount + ' skipped).'
                    : 'No visible rows match CVR trend rules (' + holdSkipCount + ' skipped).');
                return;
            }

            const scope = useSelection ? 'selected' : 'visible eligible';
            const zeroNote = zeroCvrCount > 0
                ? ('\n' + zeroCvrCount + ' SKU(s) via CVR=0% trend')
                : '';
            const slabNote = '\nHits: Red0=' + slabHit.zero +
                ' Yellow=' + slabHit.red +
                ' Blue=' + slabHit.blue +
                ' Green=' + slabHit.green +
                ' Pink=' + slabHit.pink +
                (holdSkipCount ? (' · skipped=' + holdSkipCount) : '');
            const slabs = resolveSpriceCvrSlabs(rule);
            const sp = normalizeSlabPct(rule.slab_pct, rule.yellow_pct);
            const actLabel = function(slab, trend) {
                const pct = slabPctForTrend(sp, slab || 'red', trend || 'down');
                if (!isFinite(pct)) return 'hold';
                if (pct === 0) return '0% hold (= eBay price)';
                const s = formatSlabBound(pct);
                return (pct > 0 ? '+' : '') + s + '% of eBay price';
            };
            const midL = formatSlabBound(slabs.mid);
            const highL = formatSlabBound(slabs.high);
            const lowL = formatSlabBound(slabs.low);
            const blueStart = formatSlabBound(slabs.blue_start != null ? slabs.blue_start : (slabs.low + 0.01));
            const greenStart = formatSlabBound(slabs.mid + 0.01);
            const pinkAfter = formatSlabBound(slabs.pink_after);
            const yellowStart = formatSlabBound(slabs.yellow_start || SPRICE_CVR_YELLOW_START);
            const yellowBand = yellowStart + '–' + lowL + '%';
            if (!confirm(
                'Adjust SPRICE by % of eBay listing price for ' + rowsToProcess.length + ' ' + scope + ' SKU(s)?\n' +
                '(SPRICE = eBay price × (1 + %/100) — of price, not PFT%)\n' +
                '1 Red CVR=0%: Down=' + actLabel('zero', 'down') +
                ', Same=' + actLabel('zero', 'equal') +
                ', Up=' + actLabel('zero', 'up') + '\n' +
                '2 Yellow ' + yellowBand + ': Down=' + actLabel('red', 'down') +
                ', Same=' + actLabel('red', 'equal') +
                ', Up=' + actLabel('red', 'up') + '\n' +
                '3 Blue ' + blueStart + '–' + midL + '%: Down=' + actLabel('blue', 'down') +
                ', Same=' + actLabel('blue', 'equal') +
                ', Up=' + actLabel('blue', 'up') + '\n' +
                '4 Green ' + greenStart + '–' + highL + '%: Down=' + actLabel('green', 'down') +
                ', Same=' + actLabel('green', 'equal') +
                ', Up=' + actLabel('green', 'up') + '\n' +
                '5 Pink >' + pinkAfter + '%: Down=' + actLabel('pink', 'down') +
                ', Same=' + actLabel('pink', 'equal') +
                ', Up=' + actLabel('pink', 'up') +
                zeroNote + slabNote
            )) {
                return;
            }

            let successCount = 0;
            let errorCount = 0;
            const total = rowsToProcess.length;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            const finish = function() {
                if (successCount + errorCount !== total) return;
                $btn.prop('disabled', false).html(btnHtml);
                refreshSpriceCvrUi();
                const blueN = slabHit.blue || 0;
                if (errorCount === 0) {
                    spriceCvrShowToast('success', 'Sprice × CVR saved for ' + successCount +
                        ' SKU(s) (Blue ' + blueN + ')');
                } else {
                    spriceCvrShowToast('error', 'Saved ' + successCount + ' of ' + total +
                        ' (' + errorCount + ' failed; Blue ' + blueN + ')');
                }
                if (useSelection) {
                    if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.clear) selectedSkus.clear();
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
                    return;
                }
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: item.sku, sprice: item.sprice },
                    success: function(response) {
                        successCount++;
                        item.row.update({
                            SPRICE: item.sprice,
                            SPFT: response.spft_percent != null ? response.spft_percent : 0,
                            SROI: response.sroi_percent != null ? response.sroi_percent : 0,
                            SGPFT: response.sgpft_percent != null ? response.sgpft_percent : 0,
                            SPRICE_STATUS: 'saved',
                            has_custom_sprice: true
                        });
                        item.row.reformat();
                    },
                    error: function() { errorCount++; },
                    complete: function() { finish(); }
                });
            });
        });

        /*
         * Target ROI% / Target GPFT% bulk apply (eBay3, margin = 0.85 fixed)
         * ------------------------------------------------------------------
         * Back-solves SPRICE so the resulting SGROI / SGPFT columns match the entered
         * target (gross only — Ads% / SNROI are not used). eBay3 formulas:
         *     SGPFT% = ((sprice * 0.85 − ship − lp) / sprice) * 100
         *     SGROI% = ((sprice * 0.85 − ship − lp) / lp)     * 100
         *   → sprice = (lp * (1 + ROI%/100)  + ship) / 0.85
         *   → sprice = (lp + ship) / (0.85 − GPFT%/100)
         * Each save goes through the existing saveSpriceWithRetry() Promise pipeline
         * so SPRICE_STATUS (processing → saved / error) and the server-recomputed
         * SGPFT / SGROI values stay in sync exactly like applyDiscount.
         * Rounding is plain 2-decimal — no .99 / .49 retail snapping — because
         * snapping would shift the achieved SGROI / SGPFT off the user-typed target.
         */
        function ebay3ApplyTargetBackSolve(computeFn, labelPrefix) {
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU first (turn on Price % mode to reveal checkboxes)', 'error');
                return;
            }

            const allData     = table.getData('all');
            const targetSkus  = new Set(selectedSkus);
            const tasks       = [];
            let skippedNoLp   = 0;
            const skippedHigh = [];

            allData.forEach(row => {
                if (row.Parent && String(row.Parent).startsWith('PARENT')) return;
                const sku = row['(Child) sku'];
                if (!sku || !targetSkus.has(sku)) return;

                const lp = parseFloat(row['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(row['Ship_productmaster']) || 0;

                const EBAY3_MARGIN = 0.85;
                const computed = computeFn(lp, ship, EBAY3_MARGIN);
                if (computed == null) { skippedHigh.push(sku); return; }
                const newSprice = +computed.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const tableRow = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                if (!tableRow) return;
                tableRow.update({ SPRICE: newSprice, SPRICE_STATUS: 'processing' });

                tasks.push({ sku: sku, newSprice: newSprice, tableRow: tableRow });
            });

            if (tasks.length === 0) {
                if (skippedHigh.length > 0) {
                    showToast(`${labelPrefix} too high — must be less than the eBay3 take-home margin (< 85%).`, 'error');
                } else {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                }
                return;
            }

            let okCount  = 0;
            let errCount = 0;
            const total  = tasks.length;

            tasks.forEach(t => {
                saveSpriceWithRetry(t.sku, t.newSprice, t.tableRow)
                    .then(() => {
                        okCount++;
                        if (okCount + errCount === total) {
                            let note = '';
                            if (skippedNoLp > 0)    note += ` (${skippedNoLp} skipped — no LP)`;
                            if (skippedHigh.length) note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
                            if (errCount === 0) {
                                showToast(`${labelPrefix} applied to ${okCount} SKU(s)${note}`, 'success');
                            } else {
                                showToast(`${labelPrefix} applied to ${okCount} SKU(s), ${errCount} failed${note}`, 'error');
                            }
                        }
                    })
                    .catch(() => {
                        errCount++;
                        if (okCount + errCount === total) {
                            let note = '';
                            if (skippedNoLp > 0)    note += ` (${skippedNoLp} skipped — no LP)`;
                            if (skippedHigh.length) note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
                            showToast(`${labelPrefix} applied to ${okCount} SKU(s), ${errCount} failed${note}`, 'error');
                        }
                    });
            });
        }

        $('#apply-target-roi-btn').on('click', function () {
            const rawInput = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target ROI%', 'error');
                return;
            }
            if (!isFinite(targetRoiPct)) {
                showToast('Target ROI% must be a number', 'error');
                return;
            }

            // Target displayed SGROI (gross), not SNROI:
            //   (sprice×margin − ship − lp) / lp × 100 = Target
            //   -> sprice = (lp × (1 + Target/100) + ship) / margin
            const roiMultiplier = 1 + (targetRoiPct / 100);
            ebay3ApplyTargetBackSolve(function (lp, ship, margin) {
                if (margin <= 0) return null;
                return (lp * roiMultiplier + ship) / margin;
            }, `Target SGROI ${targetRoiPct}%`);
        });

        $('#apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target GPFT%', 'error');
                return;
            }
            if (!isFinite(targetGpftPct)) {
                showToast('Target GPFT% must be a number', 'error');
                return;
            }

            // Target displayed SGPFT:
            //   ((sprice×margin − ship − lp) / sprice) × 100 = Target
            //   -> sprice = (lp + ship) / (margin − Target/100)
            const targetFraction = targetGpftPct / 100;
            ebay3ApplyTargetBackSolve(function (lp, ship, margin) {
                const denom = margin - targetFraction;
                if (denom <= 0) return null; // signals "target ≥ margin" skip
                return (lp + ship) / denom;
            }, `Target SGPFT ${targetGpftPct}%`);
        });

        $('#target-roi-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // ==================== Play/Pause parent navigation (like pricing-master-cvr) ====================
        /** CVR 30 for a parent tree row: SCVR from API, else eBay L30 / views. */
        function parentRowCvr30(parentRow) {
            if (!parentRow) return 0;
            const scvr = parseFloat(parentRow['SCVR']);
            if (!isNaN(scvr)) return scvr;
            const views = parseFloat(parentRow.views || 0);
            const l30 = parseFloat(parentRow['eBay L30'] || 0);
            return views > 0 ? (l30 / views) * 100 : 0;
        }

        function getPlayModeParentList() {
            if (isPlayNavigationActive && playModeParentList && playModeParentList.length) {
                return playModeParentList;
            }
            return allTableData;
        }

        /** True when row is marked NR in NR/REQ column (excluded from Play). */
        function ebay3RowNrReqIsNr(row) {
            return !!(row && String(row.nr_req || '').trim() === 'NR');
        }

        function ebay3EbayStockQty(row) {
            return parseFloat(row['eBay Stock'] || row['E Stock'] || 0) || 0;
        }

        // Full tree for badge math: use live table data (always has current rows); in Play mode use full snapshot.
        function ebay3GetSummaryTreeRoots() {
            try {
                if (typeof isPlayNavigationActive !== 'undefined' && isPlayNavigationActive
                    && allTableData && allTableData.length) {
                    return allTableData;
                }
                if (typeof table !== 'undefined' && table && typeof table.getData === 'function') {
                    const live = table.getData('all');
                    if (live && live.length) {
                        return live;
                    }
                }
            } catch (e) { /* ignore */ }
            return allTableData || [];
        }

        // Build SKU-only rows from tree (recurse _children, skip PARENT skus) — same idea as PHP flattenEbay3TreeForSummary
        function ebay3GetBackendSkuRows() {
            const skuRows = [];
            function walk(node) {
                if (!node) return;
                const sku = String((node['(Child) sku']) || '').toUpperCase();
                if (sku && !sku.includes('PARENT')) {
                    skuRows.push(node);
                }
                if (node._children && Array.isArray(node._children) && node._children.length) {
                    node._children.forEach(walk);
                }
            }
            (ebay3GetSummaryTreeRoots() || []).forEach(walk);
            return skuRows;
        }

        /** Rows to show in Play: INV>0, not NR/REQ=NR; parent row only if INV>0 and not NR. */
        function ebay3BuildPlayDisplayData(parentRow) {
            if (!parentRow) return [];
            var rawChildren = (parentRow._children && Array.isArray(parentRow._children)) ? parentRow._children : [];
            var invKids = [];
            if (typeof ebay3Qty === 'function') {
                invKids = rawChildren.filter(function(c) {
                    if (ebay3RowNrReqIsNr(c)) return false;
                    return ebay3Qty(c.INV) > 0;
                });
            } else {
                invKids = rawChildren.filter(function(c) {
                    if (ebay3RowNrReqIsNr(c)) return false;
                    return (parseFloat(c.INV || 0) || 0) > 0;
                });
            }
            var parentInvOk = typeof ebay3Qty === 'function'
                ? ebay3Qty(parentRow.INV) > 0
                : (parseFloat(parentRow.INV || 0) || 0) > 0;
            var parentOkForPlay = parentInvOk && !ebay3RowNrReqIsNr(parentRow);
            return parentOkForPlay ? invKids.concat([parentRow]) : invKids.slice();
        }

        function showCurrentParentPlayView() {
            var parentList = getPlayModeParentList();
            if (!parentList || parentList.length === 0) return;
            if (currentPlayParentIndex < 0) {
                currentPlayParentIndex = 0;
            }

            while (currentPlayParentIndex < parentList.length) {
                var displayData = ebay3BuildPlayDisplayData(parentList[currentPlayParentIndex]);
                if (displayData.length > 0) {
                    table.clearFilter(true);
                    table.setData(displayData).then(function() {
                        updateCalcValues();
                        updateSummary();
                        updatePlayButtonStates();
                    });
                    return;
                }
                currentPlayParentIndex++;
            }

            if (isPlayNavigationActive) {
                showToast('No parent left with inventory — exiting Play', 'warning');
                stopPlayNavigation();
            }
        }

        function startPlayNavigation() {
            if (!allTableData || allTableData.length === 0) {
                showToast('No parent data to navigate', 'warning');
                return;
            }
            playModeParentList = [...allTableData].sort(function(a, b) {
                const da = parentRowCvr30(a);
                const db = parentRowCvr30(b);
                if (da !== db) return da - db;
                const sa = String(a['(Child) sku'] || a['Parent'] || '');
                const sb = String(b['(Child) sku'] || b['Parent'] || '');
                return sa.localeCompare(sb);
            });
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
            playModeParentList = null;
            $('#play-pause').hide();
            $('#play-auto').show();
            $('#play-backward, #play-forward').prop('disabled', true);
            table.setData(allTableData);
            applyFilters();
        }

        function updatePlayButtonStates() {
            const plist = getPlayModeParentList();
            const len = plist && plist.length ? plist.length : 0;
            $('#play-backward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex >= len - 1);
            $('#play-auto').attr('title', isPlayNavigationActive ? 'Show all' : 'Play — parents low → high by CVR 30 (SCVR)');
            $('#play-pause').attr('title', 'Pause - show all');
        }

        function playNextParent() {
            const plist = getPlayModeParentList();
            if (!isPlayNavigationActive || !plist || !plist.length) return;
            if (currentPlayParentIndex >= plist.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            var parentList = getPlayModeParentList();
            if (!isPlayNavigationActive || !parentList || !parentList.length) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            while (currentPlayParentIndex >= 0) {
                var displayData = ebay3BuildPlayDisplayData(parentList[currentPlayParentIndex]);
                if (displayData.length > 0) {
                    table.clearFilter(true);
                    table.setData(displayData).then(function() {
                        updateCalcValues();
                        updateSummary();
                        updatePlayButtonStates();
                    });
                    return;
                }
                currentPlayParentIndex--;
            }
            currentPlayParentIndex = 0;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', startPlayNavigation);
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        // Badge filter click handlers (same pattern as Ebay 2)
        $('.sold-filter-badge[data-filter="zero"]').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreSoldFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('.sold-filter-badge[data-filter="sold"]').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = false;
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = false;
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#inv-stock-badge').on('click', function() {
            invStockFilterActive = !invStockFilterActive;
            missingFilterActive = false;
            mapFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        // Update badge styles based on active filters
        function updateBadgeStyles() {
            if (zeroSoldFilterActive) {
                $('.sold-filter-badge[data-filter="zero"]').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('.sold-filter-badge[data-filter="zero"]').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (moreSoldFilterActive) {
                $('.sold-filter-badge[data-filter="sold"]').css('opacity', '1').css('box-shadow', '0 0 10px rgba(14, 165, 233, 0.75)');
            } else {
                $('.sold-filter-badge[data-filter="sold"]').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (missingFilterActive) {
                $('#missing-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('#missing-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (mapFilterActive) {
                $('#map-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(40, 167, 69, 0.8)');
            } else {
                $('#map-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (invStockFilterActive) {
                $('#inv-stock-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(255, 193, 7, 0.8)');
            } else {
                $('#inv-stock-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }
        }

        // Store all unfiltered data for summary calculations
        let allTableData = [];
        /** Missing/Map/N Map from last /ebay3-data-json (PHP). Cleared on cell edit so client recomputes. */
        let ebay3ServerSummary = null;

        // Play/Pause parent navigation (like pricing-master-cvr)
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        /** While play is active: top-level parents sorted by CVR 30 (SCVR) ascending. */
        let playModeParentList = null;

        /** Coerce /ebay3-data-json "summary" (PHP) — Tabulator/JSON can surface ints as number or string. */
        function ebay3NormalizeServerSummary(raw) {
            if (!raw || typeof raw !== 'object') {
                return null;
            }
            const nMapVal = (raw.nMap != null) ? raw.nMap : (raw.n_map != null ? raw.n_map : raw['nMap']);
            const toNum = function(v) {
                if (v === null || v === undefined || v === '') {
                    return NaN;
                }
                const n = Number(v);
                return Number.isFinite(n) ? n : NaN;
            };
            const m = toNum(raw.missing);
            const mp = toNum(raw.map);
            const nm = toNum(nMapVal);
            if (isNaN(m) || isNaN(mp) || isNaN(nm)) {
                return null;
            }
            return { missing: m, map: mp, nMap: nm };
        }

        let ebayMpImagePreviewHideTimer = null;
        let ebayMpImagePreviewEl = null;
        function ebayMpRemoveImagePreview() {
            if (ebayMpImagePreviewHideTimer) {
                clearTimeout(ebayMpImagePreviewHideTimer);
                ebayMpImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
            ebayMpImagePreviewEl = null;
        }
        function ebayMpCancelImagePreviewHide() {
            if (ebayMpImagePreviewHideTimer) {
                clearTimeout(ebayMpImagePreviewHideTimer);
                ebayMpImagePreviewHideTimer = null;
            }
        }
        function ebayMpScheduleImagePreviewHide() {
            ebayMpCancelImagePreviewHide();
            ebayMpImagePreviewHideTimer = setTimeout(ebayMpRemoveImagePreview, 220);
        }
        function ebayMpEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.ebayMpPreviewListeners === '1') return;
            wrap.dataset.ebayMpPreviewListeners = '1';
            wrap.addEventListener('mouseenter', ebayMpCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', ebayMpScheduleImagePreviewHide);
        }
        function ebayMpClampPreviewPosition(wrap, clientX, clientY) {
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
        function ebayMpShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            ebayMpCancelImagePreviewHide();
            const existing = ebayMpImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    ebayMpClampPreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
            ebayMpImagePreviewEl = null;
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
            big.style.maxWidth = '350px';
            big.style.maxHeight = '350px';
            big.style.display = 'block';
            big.alt = '';
            big.src = fullUrl;
            wrap.appendChild(big);
            ebayMpEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            ebayMpImagePreviewEl = wrap;
            ebayMpClampPreviewPosition(wrap, clientX, clientY);
        }
        
        // Initialize Tabulator
        table = new Tabulator("#ebay3-table", {
            ajaxURL: "/ebay3-data-json",
            ajaxResponse: function(url, params, response) {
                let rows = response;
                ebay3ServerSummary = null;
                if (response && !Array.isArray(response) && response.data !== undefined) {
                    rows = response.data;
                    ebay3ServerSummary = ebay3NormalizeServerSummary(response.summary);
                } else {
                    rows = Array.isArray(response) ? response : [];
                }
                allTableData = rows || [];
                if (ebay3ServerSummary) {
                    console.log('eBay3 server summary (badges from PHP):', ebay3ServerSummary, 'root rows:', allTableData.length);
                } else {
                    console.log('API Response - Total rows:', allTableData.length, '(Missing/Map/N Map: client — add summary to /ebay3-data-json or check response shape)');
                }

                // Tree: eBay L30 is on child SKUs under _children — sum whole tree, not just roots
                let totalL30 = 0;
                let parentRowCount = 0;
                function walkTreeL30Log(node) {
                    if (!node) return;
                    const skuU = String(node['(Child) sku'] || '').toUpperCase();
                    if (skuU.includes('PARENT')) {
                        parentRowCount++;
                    } else {
                        totalL30 += parseFloat(node['eBay L30'] || 0) || 0;
                    }
                    if (node._children && Array.isArray(node._children) && node._children.length) {
                        node._children.forEach(walkTreeL30Log);
                    }
                }
                (allTableData || []).forEach(walkTreeL30Log);
                console.log('Total eBay3 L30 (all SKU rows in tree):', totalL30, '· PARENT group rows in tree:', parentRowCount);

                return rows;
            },
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            dataTree: true,
            dataTreeStartExpanded: false,
            dataTreeChildField: "_children",
            dataTreeFilter: true,
            dataTreeChildColumnCalcs: true,
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{
                column: "Parent",
                dir: "asc"
            }],
            rowFormatter: function(row) {
                const sku = row.getData()['(Child) sku'] || '';
                if (sku.toUpperCase().includes('PARENT')) {
                    row.getElement().classList.add('parent-row');
                }
            },
            columns: [
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    frozen: true,
                    width: 150,
                    visible: false, 
                },
                {
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    frozen: true,
                    width: 50,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isSelected = selectedSkus.has(sku);
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                    }
                },
                {
                    title: "Image",
                    field: "image_path",
                    frozen: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            const u = String(value).replace(/"/g, '&quot;');
                            return '<img src="' + u + '" data-full="' + u + '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                        }
                        return '';
                    },
                    cellMouseOver: function(e, cell) {
                        const img = cell.getElement().querySelector('.hover-thumb');
                        if (!img) return;
                        ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                    },
                    cellMouseMove: function(e, cell) {
                        const preview = ebayMpImagePreviewEl;
                        if (!preview || !document.body.contains(preview)) return;
                        const img = cell.getElement().querySelector('.hover-thumb');
                        const fullUrl = img ? img.getAttribute('data-full') : '';
                        const big = preview.querySelector('img');
                        if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                        ebayMpClampPreviewPosition(preview, e.clientX, e.clientY);
                    },
                    cellMouseOut: function(e, cell) {
                        const related = e.relatedTarget;
                        if (related && typeof related.closest === 'function' && related.closest('#image-hover-preview')) {
                            ebayMpCancelImagePreviewHide();
                            return;
                        }
                        ebayMpScheduleImagePreviewHide();
                    },
                    headerSort: false,
                    width: 80,
                    visible: false
                },
                {
                    title: "Sku",
                    field: "(Child) sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = cell.getValue();
                        const isParent = sku && sku.toUpperCase().startsWith('PARENT');
                        
                        if (isParent) {
                            return `<span style="font-weight: 700;">${sku}</span>`;
                        }
                        
                        let html = `<span>${sku}</span>`;
                        
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        
                        return html;
                    }
                },
                {
                    title: "Links",
                    field: "buyer_link",
                    hozAlign: "center",
                    width: 55,
                    frozen: true,
                    headerSort: false,
                    headerTooltip: "eBay Buyer / Seller links (same source as pricing-master-cvr)",
                    tooltip: "Double-click to add / edit links",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData.buyer_link || '';
                        const sellerLink = rowData.seller_link || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                        if (sellerLink) {
                            html += '<a href="' + sellerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                        }
                        if (buyerLink) {
                            html += '<a href="' + buyerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        openEbay3EditLinksModal(cell.getRow());
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "Dil",
                    field: "E Dil%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';
                        
                        if (dil < 16.66) color = '#a00211';
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "CVR 60",
                    field: "CVR_60",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "CVR 30",
                    field: "SCVR",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        const aVal = parseFloat(aData.SCVR) || 0;
                        const bVal = parseFloat(bData.SCVR) || 0;
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const val = parseFloat(cell.getValue()) || 0;
                        const cvr60 = parseFloat(rowData.CVR_60) || 0;
                        const tol = 0.1;
                        let arrowHtml = '';
                        let dotColor = '#008000'; // green by default
                        const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                        if (!isParent) {
                            let arrowColor = '#6c757d';
                            let arrowIcon = 'fa-minus';
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
                            arrowHtml = ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                        }
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        const dotIndicator = !isParent ? ` <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor}; vertical-align: middle;"></span>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml}${dotIndicator}`;
                    },
                    width: 65
                },
                {
                    title: "E Stock",
                    field: "eBay Stock",
                    hozAlign: "center",
                    width: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "E L30",
                    field: "eBay L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "Growth",
                    field: "growth_percent",
                    hozAlign: "center",
                    width: 50,
                    sorter: function(a, b, aRow, bRow) {
                        function ebaySalesGrowthPct(row) {
                            const d = row.getData();
                            const l30 = parseFloat(d['eBay L30']) || 0;
                            const l60 = parseFloat(d['eBay L60']) || 0;
                            if (l60 === 0) return l30 > 0 ? 100 : 0;
                            return ((l30 - l60) / l60) * 100;
                        }
                        return ebaySalesGrowthPct(aRow) - ebaySalesGrowthPct(bRow);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const l30 = parseFloat(rowData['eBay L30']) || 0;
                        const l60 = parseFloat(rowData['eBay L60']) || 0;
                        if (l60 === 0) {
                            if (l30 > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">+100%</span>`;
                            }
                            return '<span style="color: #6c757d;">0%</span>';
                        }
                        const growth = ((l30 - l60) / l60) * 100;
                        const growthRounded = Math.round(growth);
                        let color = '#6c757d';
                        if (growthRounded > 0) color = '#28a745';
                        else if (growthRounded < 0) color = '#dc3545';
                        const sign = growthRounded > 0 ? '+' : '';
                        return `<span style="color: ${color}; font-weight: bold;">${sign}${growthRounded}%</span>`;
                    }
                },
                {
                    title: "Missing",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const skuUpper = ((rowData['(Child) sku'] || '') + '').toUpperCase();
                        const isParentRow = skuUpper.includes('PARENT');
                        const children = rowData._children;

                        function isMissingItemId(itemId) {
                            return !itemId || itemId === null || itemId === '';
                        }

                        // Parent row: M if any child would show M; else green dot (including zero children)
                        if (isParentRow && children && Array.isArray(children)) {
                            if (children.length > 0) {
                                const anyChildMissing = children.some(function(ch) {
                                    return isMissingItemId(ch['eBay_item_id'])
                                        && !ebay3RowNrReqIsNr(ch);
                                });
                                if (anyChildMissing) {
                                    return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                                }
                            }
                            return '<span title="All child SKUs have eBay listing" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#28a745;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.12);"></span>';
                        }

                        // Child / leaf: Missing = no eBay3 item_id (exclude NR)
                        const itemId = rowData['eBay_item_id'];
                        if (isMissingItemId(itemId) && !ebay3RowNrReqIsNr(rowData)) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const inv = typeof ebay3Qty === 'function' ? ebay3Qty(rowData['INV']) : (parseFloat(rowData['INV'] || 0) || 0);
                        const ebayStock = ebay3EbayStockQty(rowData);
                        const nrReq = String(rowData.nr_req || '').trim();

                        if (inv <= 0 || ebayStock <= 0 || nrReq !== 'REQ') {
                            return '';
                        }
                        if (ebay3RowMapStockMatch(rowData)) {
                            return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                        }
                        const diff = inv - ebayStock;
                        const sign = diff > 0 ? '+' : '';
                        return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                    }
                },
               
                {
                    title: "View",
                    field: "views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        if (value >= 30) color = '#28a745';
                        else color = '#a00211';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                    },
                    width: 50
                },
                {
                    title: "L7 View",
                    field: "l7_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        var value = parseInt(cell.getValue() || 0);
                        var color = l7ViewBand(value).color;
                        var style = color ? ` style="color: ${color}; font-weight: 600;"` : '';
                        return `<span${style}>${value.toLocaleString()}</span>`;
                    },
                    width: 70
                },
               
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '')) {
                            value = 'REQ';
                        }
                        
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'] || '';
                        
                        return `<select class="form-select form-select-sm nr-req-dropdown" 
                            data-sku="${sku}"
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (window.ParentExpand) {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(rowData);
                            if (avgHtml !== null) return avgHtml;
                        }
                        const lmpPrice = cell.getValue();
                        const sku = rowData['(Child) sku'];
                        const totalCompetitors = rowData.lmp_entries_total || 0;
                        const currentPrice = parseFloat(rowData['eBay Price'] || 0);

                        if (!lmpPrice && totalCompetitors === 0) {
                            return `<a href="#" class="view-lmp-competitors" data-sku="${sku}"
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px;">
                                <i class="fa fa-eye"></i> View
                            </a>`;
                        }

                        let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                        
                        // Show lowest price OUTSIDE modal
                        if (lmpPrice) {
                            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                            const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                            html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
                        }
                        
                        // Show link to open modal with all competitors
                        if (totalCompetitors > 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${sku}" 
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                                <i class="fa fa-eye"></i> View ${totalCompetitors}
                            </a>`;
                        }
                        
                        html += '</div>';
                        return html;
                    },
                    width: 70
                },
                {
                    title: "Price",
                    field: "eBay Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "GROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: "avg",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 65
                },
                {
                    title: "NROI",
                    field: "NROI",
                    hozAlign: "center",
                    // Same formula as Amazon NROI: (PFT$ − Ad Spend$) / LP × 100
                    sorter: function(a, b, aRow, bRow) {
                        const aNet = ebay3ComputeNetRoi(aRow.getData(), 'eBay Price');
                        const bNet = ebay3ComputeNetRoi(bRow.getData(), 'eBay Price');
                        return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                             - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                    },
                    formatter: function(cell) {
                        const percent = ebay3ComputeNetRoi(cell.getRow().getData(), 'eBay Price');
                        if (percent === null || !isFinite(percent)) return '';
                        let color = '';

                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';

                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: function(values, data) {
                        let sum = 0, n = 0;
                        data.forEach(r => {
                            const v = ebay3ComputeNetRoi(r, 'eBay Price');
                            if (v != null && isFinite(v)) { sum += v; n++; }
                        });
                        return n ? sum / n : 0;
                    },
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 65
                },
                {
                    title: "GPFT %",
                    field: "GPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "NPFT",
                    field: "PFT %",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const ads = (typeof EBAY3_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0) : 0;
                        return ((parseFloat(aRow.getData()['GPFT%'] || 0) - ads) - (parseFloat(bRow.getData()['GPFT%'] || 0) - ads));
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const ads = (typeof EBAY3_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0) : 0;
                        // NPFT% = GPFT% − Ads% (channel TACOS)
                        const percent = (parseFloat(rowData['GPFT%'] || 0)) - ads;
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: function(values, data) {
                        const ads = (typeof EBAY3_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0) : 0;
                        let sum = 0, n = 0;
                        data.forEach(r => { const v = parseFloat(r['GPFT%']); if (!isNaN(v)) { sum += (v - ads); n++; } });
                        return n ? sum / n : 0;
                    },
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 50
                },
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const hasCustomSprice = rowData.has_custom_sprice;
                        const currentPrice = parseFloat(rowData['eBay Price']) || 0;
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        const sprice = isNaN(spriceNum) ? 0 : spriceNum;
                        
                        if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0) return '';
                        // When SPRICE matches eBay Price, show "-" instead of the same dollar amount
                        if (currentPrice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) {
                            return '<span style="color:#adb5bd;" title="Same as eBay Price">-</span>';
                        }
                        const formattedValue = `$${Number(sprice).toFixed(2)}`;
                        if (hasCustomSprice === false) {
                            return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                        }
                        return formattedValue;
                    },
                    width: 80
                },
                {
                    field: "_accept",
                    hozAlign: "center",
                    headerSort: false,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                            <span>Accept</span>
                            <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay3" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                            </button>
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const sprice = parseFloat(rowData.SPRICE) || 0;
                        const status = rowData.SPRICE_STATUS || null;

                        if (!sprice || sprice === 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }

                        let icon = '<i class="fas fa-check"></i>';
                        let iconColor = '#28a745';
                        let titleText = 'Apply Price to eBay3';

                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            iconColor = '#ffc107';
                            titleText = 'Price pushing in progress...';
                        } else if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price pushed to eBay3 (Double-click to mark as Applied)';
                        } else if (status === 'applied') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price applied to eBay3 (Double-click to change)';
                        } else if (status === 'saved') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'SPRICE saved (Click to push to eBay3)';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-x"></i>';
                            iconColor = '#dc3545';
                            titleText = 'Error applying price to eBay3';
                        } else if (status === 'account_restricted') {
                            icon = '<i class="fa-solid fa-ban"></i>';
                            iconColor = '#ff6b00';
                            titleText = 'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
                        }

                        return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                            ${icon}
                        </button>`;
                    },
                    cellClick: function(e, cell) {
                        const $target = $(e.target);
                        
                        // Handle double-click to change status from 'pushed' to 'applied'
                        if (e.originalEvent && e.originalEvent.detail === 2) {
                            const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                            const currentStatus = $btn.attr('data-status') || '';
                            
                            if (currentStatus === 'pushed') {
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                $.ajax({
                                    url: '/update-ebay3-sprice-status',
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    data: { sku: sku, status: 'applied' },
                                    success: function(response) {
                                        if (response.success) {
                                            // Update only this row (no full reload → no horizontal slide)
                                            const row = cell.getRow();
                                            row.update({ SPRICE_STATUS: 'applied' });
                                            row.reformat();
                                            showToast('Status updated to Applied', 'success');
                                        }
                                    }
                                });
                            }
                            return;
                        }
                        
                        if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                            e.stopPropagation();
                            const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                            // Read SKU/price from LIVE row data (not stale data-* attributes),
                            // so a freshly-edited SPRICE is pushed instead of an old value.
                            const liveRowData = cell.getRow().getData();
                            const sku = liveRowData['(Child) sku'] || $btn.attr('data-sku');
                            const price = parseFloat(liveRowData.SPRICE) || parseFloat($btn.attr('data-price'));
                            const currentStatus = liveRowData.SPRICE_STATUS || $btn.attr('data-status') || '';
                            
                            if (!sku || !price || price <= 0 || isNaN(price)) {
                                showToast('Invalid SKU or price', 'error');
                                return;
                            }
                            
                            // If status is 'saved' or null, first save SPRICE, then push to eBay3
                            if (currentStatus === 'saved' || !currentStatus) {
                                const row = cell.getRow();
                                row.update({ SPRICE_STATUS: 'processing' });
                                
                                saveSpriceWithRetry(sku, price, row)
                                    .then((response) => {
                                        // After saving, push to eBay3
                                        applyPriceWithRetry(sku, price, cell, 0);
                                    })
                                    .catch((error) => {
                                        row.update({ SPRICE_STATUS: 'error' });
                                        showToast('Failed to save SPRICE', 'error');
                                    });
                            } else {
                                // If already saved, just push to eBay3
                                applyPriceWithRetry(sku, price, cell, 0);
                            }
                        }
                    }
                },
                {
                    title: "S GROI",
                    field: "SGROI",
                    hozAlign: "center",
                    // Always derive from S PRC (SPRICE) with the same formula as GROI% from Prc.
                    sorter: function(a, b, aRow, bRow) {
                        const aVal = ebay3HasDistinctSprice(aRow.getData())
                            ? ebay3ComputeGrossRoi(aRow.getData(), 'SPRICE') : null;
                        const bVal = ebay3HasDistinctSprice(bRow.getData())
                            ? ebay3ComputeGrossRoi(bRow.getData(), 'SPRICE') : null;
                        return ((aVal == null || !isFinite(aVal)) ? 0 : aVal)
                             - ((bVal == null || !isFinite(bVal)) ? 0 : bVal);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData.SPRICE);
                        const ebayPrice = parseFloat(rowData['eBay Price']) || 0;

                        // Match S PRC column: blank when no SPRICE, "-" when same as eBay Price
                        if (!isFinite(sprice) || sprice <= 0) return '';
                        if (ebayPrice > 0 && ebayPrice.toFixed(2) === sprice.toFixed(2)) {
                            return '<span style="color:#adb5bd;" title="Same as eBay Price">-</span>';
                        }

                        // Same formula as GROI% (ROI%) but price = S PRC / SPRICE:
                        // ((SPRICE × margin − LP − Ship) / LP) × 100
                        const percent = ebay3ComputeGrossRoi(rowData, 'SPRICE');
                        if (percent === null || !isFinite(percent)) return '';

                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';

                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
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
                        
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SNROI",
                    field: "SROI",
                    hozAlign: "center",
                    // Same formula as Amazon SNROI / NROI badge:
                    // (gross PFT$ − SPRICE×Ads%/100) / LP × 100
                    sorter: function(a, b, aRow, bRow) {
                        const aNet = ebay3ComputeNetRoi(aRow.getData(), 'SPRICE');
                        const bNet = ebay3ComputeNetRoi(bRow.getData(), 'SPRICE');
                        return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                             - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                    },
                    formatter: function(cell) {
                        const percent = ebay3ComputeNetRoi(cell.getRow().getData(), 'SPRICE');
                        if (percent === null || !isFinite(percent)) return '';

                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';

                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SNPFT",
                    field: "SPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // SNPFT = S GPFT − Ads% (net of channel ad spend).
                        const rawGpft = rowData.SGPFT;
                        if (rawGpft === null || rawGpft === undefined || rawGpft === '') return '';
                        const sgpft = parseFloat(rawGpft);
                        if (isNaN(sgpft)) return '';
                        const ads = parseFloat(EBAY3_CHANNEL_ADS_PCT) || 0;
                        const percent = sgpft - ads;

                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';

                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },

                // === Campaign-Ads columns (ES BID / C BID / PROMOTE) ===
                // Same source & formatters as /ebay3/campaign-ads. SKU-wise via listing_id; rows
                // without a campaign-ads match stay visible with the data displayed as-is ('—').
                {
                    title: "ES BID",
                    field: "ca_suggested_bid",
                    hozAlign: "center",
                    sorter: "number",
                    width: 90,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        if (isNaN(v)) return '<span class="text-muted">—</span>';
                        return `<span class="text-info fw-semibold">${v.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "C BID",
                    field: "ca_bid_percentage",
                    hozAlign: "center",
                    sorter: "number",
                    width: 90,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        if (isNaN(v)) return '<span class="text-muted">—</span>';
                        const color = v <= 4 ? '#dc3545' : v <= 7 ? '#ffc107' : v <= 13 ? '#198754' : '#e83e8c';
                        return `<span style="color:${color}; font-weight:600;">${v.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "S BID",
                    field: "ca_bid_percentage",
                    hozAlign: "center",
                    width: 90,
                    headerTooltip: "Daily adjustment of the current C BID by L7 View band — green keeps C Bid, pink/red apply the direction + %/day set in the 'Sbid (Views)' button — clamped to the Min/Max caps. No C Bid → —. Same as /ebay-tabulator-view and /ebay3/campaign-ads.",
                    sorter: function(a, b, aRow, bRow) {
                        return computeSbidViews(aRow.getData()).bid - computeSbidViews(bRow.getData()).bid;
                    },
                    formatter: function(cell) {
                        const res = computeSbidViews(cell.getRow().getData());
                        if (res.skip) {
                            return '<span class="text-muted" title="No S Bid — no current C Bid to adjust" style="font-size:11px;">—</span>';
                        }
                        return `<span style="color:${res.color}; font-weight:700;">${res.bid.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "PROMOTE",
                    field: "ca_promote_with_ad",
                    hozAlign: "center",
                    headerTooltip: "eBay Promotion eligibility status (from /ebay3/campaign-ads)",
                    width: 140,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        if (!v) return '<span class="text-muted">—</span>';
                        const map = {
                            'RECOMMENDED':        { color: '#198754', bg: '#d1f5e0', label: '⭐ Eligible' },
                            'OPTIONAL':           { color: '#856404', bg: '#fff3cd', label: '⚡ Optional' },
                            'AD_ALREADY_CREATED': { color: '#0d6efd', bg: '#cfe2ff', label: '📢 In Campaign' },
                            'NOT_RECOMMENDED':    { color: '#6c757d', bg: '#f8f9fa', label: '— Not Rec.' },
                            'UNDETERMINED':       { color: '#6c757d', bg: '#f8f9fa', label: '? Unknown' },
                        };
                        const s = map[v] || { color: '#6c757d', bg: '#f8f9fa', label: v };
                        return `<span style="color:${s.color}; background:${s.bg}; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">${s.label}</span>`;
                    }
                },
            ]
        });

        /** Tabulator dataTree always draws parent before children; move each parent row after its last visible descendant. */
        function reorderEbay3ParentRowsBelowSkus() {
            if (!table) return;
            if (isPlayNavigationActive) return;
            if (($('#view-mode-filter').val() || '') === 'sku') return;

            var roots;
            try {
                roots = document.querySelectorAll('#ebay3-table .tabulator-row.parent-row.tabulator-tree-level-0');
            } catch (e) {
                return;
            }

            roots.forEach(function(level0) {
                var lastDescEl = null;
                var walker = level0.nextElementSibling;
                while (walker && !walker.classList.contains('tabulator-tree-level-0')) {
                    lastDescEl = walker;
                    walker = walker.nextElementSibling;
                }
                if (lastDescEl && lastDescEl.parentNode === level0.parentNode) {
                    lastDescEl.after(level0);
                }
            });
        }

        // SKU Search functionality
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        /** Parent + all child SKUs in the same eBay3 tree group (for NR cascade). */
        function ebay3GetNrCascadeSkus(primarySku) {
            var key = (primarySku || '').toString().trim();
            if (!key || !Array.isArray(allTableData) || !allTableData.length) {
                return [key];
            }
            for (var i = 0; i < allTableData.length; i++) {
                var pRow = allTableData[i];
                var pSku = ((pRow['(Child) sku'] || '') + '').trim();
                if (pSku.toUpperCase().indexOf('PARENT') === -1) {
                    continue;
                }
                var kids = pRow._children;
                if (!kids || !Array.isArray(kids) || kids.length === 0) {
                    if (pSku === key) {
                        return [pSku];
                    }
                    continue;
                }
                var match = (pSku === key) || kids.some(function(c) {
                    return (((c['(Child) sku'] || '') + '').trim() === key);
                });
                if (match) {
                    var out = [pSku];
                    kids.forEach(function(c) {
                        var cs = ((c['(Child) sku'] || '') + '').trim();
                        if (cs) {
                            out.push(cs);
                        }
                    });
                    return [...new Set(out)];
                }
            }
            if (typeof table !== 'undefined' && table && typeof table.searchRows === 'function') {
                var hits = table.searchRows('(Child) sku', '=', key);
                if (hits.length > 0) {
                    var pdata = hits[0].getData();
                    var parentVal = ((pdata.Parent || '') + '').trim();
                    if (parentVal) {
                        for (var j = 0; j < allTableData.length; j++) {
                            var pr = allTableData[j];
                            var ps = ((pr['(Child) sku'] || '') + '').trim();
                            if (ps.toUpperCase().indexOf('PARENT') === -1) {
                                continue;
                            }
                            if (((pr.Parent || '') + '').trim() !== parentVal) {
                                continue;
                            }
                            var ch2 = pr._children;
                            var out2 = [ps];
                            if (ch2 && Array.isArray(ch2)) {
                                ch2.forEach(function(c) {
                                    var cs = ((c['(Child) sku'] || '') + '').trim();
                                    if (cs) {
                                        out2.push(cs);
                                    }
                                });
                            }
                            return [...new Set(out2)];
                        }
                    }
                }
            }
            return [key];
        }

        function ebay3DeepUpdateNrReqForSkus(skuList, val) {
            var set = {};
            skuList.forEach(function(s) { set[s] = true; });
            function walk(rows) {
                if (!rows || !rows.length) {
                    return;
                }
                rows.forEach(function(r) {
                    if (set[r['(Child) sku']]) {
                        r.nr_req = val;
                    }
                    if (r._children && r._children.length) {
                        walk(r._children);
                    }
                });
            }
            walk(allTableData);
        }

        function ebay3UpdateVisibleRowsNrReq(skuList, val) {
            skuList.forEach(function(s) {
                var sku = (s || '').toString().trim();
                if (!sku) return;
                var rows = table.searchRows('(Child) sku', '=', sku);
                rows.forEach(function(r) {
                    r.update({ nr_req: val });
                });
            });
        }

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = ($select.attr('data-sku') || $select.data('sku') || '').toString().trim();

            if (!sku) {
                console.error('Could not find SKU in dropdown data attribute');
                showToast('Could not find SKU', 'error');
                return;
            }

            const skusToSave = (value === 'NR')
                ? ebay3GetNrCascadeSkus(sku)
                : [sku];

            console.log('Saving NR/REQ for SKU(s):', skusToSave, 'Value:', value);

            const token = '{{ csrf_token() }}';
            const saveOne = function(s) {
                return $.ajax({
                    url: '/listing_ebaythree/save-status',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        _token: token,
                        sku: s,
                        nr_req: value
                    }
                });
            };

            const saveOk = function(response) {
                return response && (response.status === 'success' || response.status === true);
            };

            const onSuccess = function() {
                if (value === 'NR') {
                    ebay3DeepUpdateNrReqForSkus(skusToSave, 'NR');
                    ebay3UpdateVisibleRowsNrReq(skusToSave, 'NR');
                } else {
                    ebay3DeepUpdateNrReqForSkus([sku], value);
                    ebay3UpdateVisibleRowsNrReq([sku], value);
                }
                const message = value === 'REQ'
                    ? 'REQ updated'
                    : (value === 'NR'
                        ? (skusToSave.length > 1 ? ('NR applied to parent and ' + (skusToSave.length - 1) + ' SKU(s)') : 'NR updated')
                        : 'Status cleared');
                showToast(message, 'success');
            };

            const onFail = function(xhr, labelSku) {
                console.error('Failed to save NR/REQ for', labelSku, 'Error:', xhr && xhr.responseText);
                showToast('Failed to save NR/REQ for ' + (labelSku || sku), 'error');
            };

            var idx = 0;
            function saveNext() {
                if (idx >= skusToSave.length) {
                    onSuccess();
                    return;
                }
                var s = skusToSave[idx++];
                saveOne(s)
                    .done(function(response) {
                        if (saveOk(response)) {
                            saveNext();
                        } else {
                            showToast((response && response.message) || 'Failed to save status', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        onFail(xhr, s);
                    });
            }

            saveNext();
        });

        table.on('cellEdited', function(cell) {
            var row = cell.getRow();
            var data = row.getData();
            var field = cell.getColumn().getField();
            var value = cell.getValue();

            if (field === 'SPRICE') {
                row.update({ SPRICE_STATUS: 'processing' });
                
                saveSpriceWithRetry(data['(Child) sku'], value, row)
                    .then((response) => {
                        showToast('SPRICE saved successfully', 'success');
                    })
                    .catch((error) => {
                        showToast('Failed to save SPRICE', 'error');
                    });
            } else if (field === 'Listed' || field === 'Live') {
                $.ajax({
                    url: '/ebay3/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['(Child) sku'],
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        showToast(field + ' status updated successfully', 'success');
                    },
                    error: function(error) {
                        showToast('Failed to update ' + field + ' status', 'error');
                    }
                });
            }
            if (field === 'INV' || field === 'eBay Stock' || field === 'E Stock' || field === 'nr_req' || field === 'eBay_item_id') {
                ebay3ServerSummary = null;
                if (typeof isPlayNavigationActive === 'undefined' || !isPlayNavigationActive) {
                    try {
                        if (table && table.getData) {
                            const d = table.getData('all');
                            if (d && d.length) {
                                allTableData = d;
                            }
                        }
                    } catch (e) { /* ignore */ }
                }
                updateSummary();
            }
        });

        /** Parse Shop/PM stock (INV) for row filters: numbers, comma thousands, trim, no false "0" from bad parse. */
        function ebay3Qty(v) {
            if (v == null || v === '' || v === false) {
                return 0;
            }
            if (typeof v === 'number' && Number.isFinite(v)) {
                return v;
            }
            var s = String(v).replace(/,/g, '').replace(/[\s\u00A0]+/g, '').trim();
            if (s === '' || s === '—' || s === '-' || s === 'N/A' || s === 'n/a') {
                return 0;
            }
            var n = parseFloat(s);
            return Number.isFinite(n) ? n : 0;
        }

        /** INV (ebay3Qty) vs eBay stock: "mapped" (MP) when |INV − eBay| ≤ 3, both sides &gt; 0. */
        function ebay3RowMapStockMatch(row) {
            if (!row) return false;
            const inv = typeof ebay3Qty === 'function' ? ebay3Qty(row['INV']) : (parseFloat(row['INV'] || 0) || 0);
            const st = typeof ebay3EbayStockQty === 'function' ? ebay3EbayStockQty(row) : (parseFloat(row['eBay Stock'] || row['E Stock'] || 0) || 0);
            if (inv <= 0 || st <= 0) return false;
            return Math.abs(inv - st) <= 3 + 1e-9;
        }

        /** Map row — same rule as Ebay 2 badge: listed (has item id), REQ, INV>0, eBay stock>0, |INV − eBay| ≤ 3. */
        function ebay3RowMap(data) {
            if (!data) return false;
            if (String(data['(Child) sku'] || '').toUpperCase().includes('PARENT')) return false;
            const itemId = data['eBay_item_id'];
            if (!itemId || String(itemId).trim() === '') return false;
            const inv = typeof ebay3Qty === 'function' ? ebay3Qty(data['INV']) : (parseFloat(data['INV'] || 0) || 0);
            if (inv <= 0) return false;
            if (String(data.nr_req || 'REQ').toUpperCase() !== 'REQ') return false;
            const est = typeof ebay3EbayStockQty === 'function' ? ebay3EbayStockQty(data) : (parseFloat(data['eBay Stock'] || data['E Stock'] || 0) || 0);
            if (est <= 0) return false;
            return Math.abs(inv - est) <= 3 + 1e-9;
        }

        /** N Map row — same rule as Ebay 2 badge: listed, REQ, INV>0, and (eBay stock===0 ? INV>3 : |INV − eBay| > 3). */
        function ebay3RowNMap(data) {
            if (!data) return false;
            if (String(data['(Child) sku'] || '').toUpperCase().includes('PARENT')) return false;
            const itemId = data['eBay_item_id'];
            if (!itemId || String(itemId).trim() === '') return false;
            const inv = typeof ebay3Qty === 'function' ? ebay3Qty(data['INV']) : (parseFloat(data['INV'] || 0) || 0);
            if (inv <= 0) return false;
            if (String(data.nr_req || 'REQ').toUpperCase() !== 'REQ') return false;
            const est = typeof ebay3EbayStockQty === 'function' ? ebay3EbayStockQty(data) : (parseFloat(data['eBay Stock'] || data['E Stock'] || 0) || 0);
            if (est <= 0) return inv > 3;
            return Math.abs(inv - est) > 3 + 1e-9;
        }

        // Apply filters
        function applyFilters() {
            if (isPlayNavigationActive) {
                showCurrentParentPlayView();
                return;
            }

            const viewModeFilter = $('#view-mode-filter').val();
            const invFilter = $('#inv-filter').val() || 'more';
            const el30Filter = $('#el30-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const cvrTrendFilter = $('#cvr-trend-filter').val();
            const spriceFilter = $('#sprice-filter').val();
            const variationFilter = $('#variation-filter').val() || 'all';
            const dilFilter = $('#dil-filter').val() || 'all';

            table.clearFilter(true);
            
            // Disable tree mode for SKU-only view
            if (viewModeFilter === 'sku') {
                // Flatten the tree for SKU-only view
                const flatData = [];
                allTableData.forEach(parent => {
                    if (parent._children && Array.isArray(parent._children)) {
                        // Add only child rows, skip parent
                        flatData.push(...parent._children);
                    } else {
                        // If no children, check if it's not a parent row
                        const sku = parent['(Child) sku'] || '';
                        if (!sku.toUpperCase().includes('PARENT')) {
                            flatData.push(parent);
                        }
                    }
                });
                table.setData(flatData);
            } else {
                // Restore original tree data for parent or both mode
                table.setData(allTableData);
            }

            // View Mode Filter - controls parent/SKU/both visibility
            if (viewModeFilter === 'parent') {
                // Show only parent rows, hide child rows
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    return sku.toUpperCase().includes('PARENT');
                });
            }
            // If 'both' is selected, no additional filter needed
            // If 'sku' is selected, data is already filtered above

            if (invFilter === 'zero') {
                table.addFilter(function(data) {
                    if (!data) {
                        return false;
                    }
                    return ebay3Qty(data.INV) === 0;
                });
            } else if (invFilter === 'more') {
                table.addFilter(function(data) {
                    if (!data) {
                        return false;
                    }
                    return ebay3Qty(data.INV) > 0;
                });
            }

            if (el30Filter === 'zero') {
                table.addFilter(function(data) {
                    return (parseFloat(data['eBay L30'] || 0) || 0) === 0;
                });
            } else if (el30Filter === 'more') {
                table.addFilter(function(data) {
                    return (parseFloat(data['eBay L30'] || 0) || 0) > 0;
                });
            }

            const growthSign = $('#growth-sign-filter').val();
            if (growthSign && growthSign !== 'all') {
                table.addFilter(function(data) {
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    const l60 = parseFloat(data['eBay L60']) || 0;
                    let growth = 0;
                    if (l60 > 0) {
                        growth = ((l30 - l60) / l60) * 100;
                    } else if (l30 > 0) {
                        growth = 100;
                    }
                    const g = Math.round(growth);
                    if (growthSign === 'negative') return g < 0;
                    if (growthSign === 'zero') return g === 0;
                    if (growthSign === 'positive') return g > 0;
                    return true;
                });
            }

            // Skip other filters for PARENT rows in tree mode
            if (nrlFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows
                    const sku = data['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) return true;
                    
                    if (nrlFilter === 'REQ') {
                        return data.nr_req === 'REQ';
                    } else if (nrlFilter === 'NR') {
                        return data.nr_req === 'NR';
                    }
                    return true;
                });
            }

            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const gpft = parseFloat(data['GPFT%']) || 0;
                    
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40plus') return gpft >= 40;
                    return true;
                });
            }

            if (roiFilter !== 'all') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) return true;
                    const roiVal = parseFloat(data['ROI%']) || 0;
                    if (roiFilter === 'lt40') return roiVal < 40;
                    if (roiFilter === '40-75') return roiVal >= 40 && roiVal < 75;
                    if (roiFilter === '75-125') return roiVal >= 75 && roiVal < 125;
                    if (roiFilter === 'gt125') return roiVal >= 125;
                    return true;
                });
            }

            if (cvrFilter !== 'all') {
                const slabs = (typeof resolveSpriceCvrSlabs === 'function')
                    ? resolveSpriceCvrSlabs(spriceCvrRule || {})
                    : { low: 3.5, mid: 7, high: 13, yellow_start: 0.01, pink_after: 13.01 };
                table.addFilter(function(data) {
                    if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT')) return true;
                    const cvr = (typeof amazonRowCvrL30 === 'function')
                        ? amazonRowCvrL30(data)
                        : (parseFloat(data['SCVR']) || 0);
                    const isZero = (typeof amazonCvrIsZero === 'function')
                        ? amazonCvrIsZero(cvr)
                        : (!isFinite(cvr) || Math.abs(cvr) < 0.005);

                    if (cvrFilter === 'zero' || cvrFilter === '0-0') return isZero;
                    if (isZero) return false;

                    let key = cvrFilter;
                    if (key === '0-3') key = 'yellow';
                    else if (key === '3-7') key = 'blue';
                    else if (key === '7-13') key = 'green';
                    else if (key === '13plus') key = 'pink';

                    const slab = (typeof amazonCvrSlab === 'function')
                        ? amazonCvrSlab(cvr, slabs.low, slabs.mid, slabs.high)
                        : (cvr <= slabs.low ? 'red' : (cvr <= slabs.mid ? 'blue' : (cvr <= (slabs.high + 0.01) ? 'green' : 'pink')));
                    if (key === 'yellow') return slab === 'red';
                    if (key === 'blue' || key === 'green' || key === 'pink') return slab === key;
                    return true;
                });
            }

            // CVR trend filter: CVR L30 vs prior L31–L60 (same as Amazon — Down / Up / Same)
            if (cvrTrendFilter !== 'all') {
                const cvrTrendTol = 0.1;
                table.addFilter(function(data) {
                    let trend = null;
                    if (typeof amazonCvrTrend === 'function') {
                        trend = amazonCvrTrend(data, cvrTrendTol);
                    } else {
                        const cvr30 = parseFloat(data['SCVR'] || 0);
                        const cvr60 = parseFloat(data['CVR_60'] || 0);
                        if (cvr30 > cvr60 + cvrTrendTol) trend = 'up';
                        else if (cvr30 < cvr60 - cvrTrendTol) trend = 'down';
                        else trend = 'equal';
                    }
                    if (cvrTrendFilter === 'down') return trend === 'down';
                    if (cvrTrendFilter === 'up') return trend === 'up';
                    if (cvrTrendFilter === 'same' || cvrTrendFilter === 'equal') return trend === 'equal';
                    return true;
                });
            }

            if (spriceFilter === 'blank') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    const sprice = data.SPRICE;
                    if (sprice == null || sprice === '') return true;
                    const num = parseFloat(sprice);
                    return isNaN(num) || num <= 0;
                });
            }

            // DIL% filter (OV dil: L30 / INV) — same bands as before
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;

                    const inv = parseFloat(data.INV) || 0;
                    const l30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    // DIL slabs match eBay 1/2: red <25, green 25-50, pink 50+
                    if (dilFilter === 'red') return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // 0 Sold filter (based on eBay L30) - triggered by badge click
            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 === 0;
                });
            }

            // > 0 Sold filter (based on eBay L30) - triggered by badge click
            if (moreSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 > 0;
                });
            }

            // Missing filter - show SKUs missing in eBay
            if (missingFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const itemId = data['eBay_item_id'];
                    const invQty = (typeof ebay3Qty === 'function') ? ebay3Qty(data['INV']) : (parseFloat(data['INV'] || 0) || 0);
                    // Missing L: in stock (INV>0) but not listed on eBay (no item id); exclude NR — Amazon parity
                    return invQty > 0
                        && (!itemId || itemId === null || itemId === '')
                        && !ebay3RowNrReqIsNr(data);
                });
            }

            // Variation column state: default red; green = user-marked in this browser
            if (variationFilter === 'red') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (!sku) return true;
                    return !variationGreenSkus.has(sku);
                });
            } else if (variationFilter === 'green') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (!sku) return true;
                    return variationGreenSkus.has(sku);
                });
            }

            // Map filter — same rule as Ebay 2 badge
            if (mapFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;

                    return ebay3RowMap(data);
                });
            }

            // N Map filter — same rule as Ebay 2 badge
            if (invStockFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;

                    return ebay3RowNMap(data);
                });
            }

            updateCalcValues();
            updateSummary();
            setTimeout(function() {
                updateSelectAllCheckbox();
            }, 100);
        }

        $('#view-mode-filter, #inv-filter, #el30-filter, #variation-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter, #dil-filter').on('change', function() {
            applyFilters();
        });

        $('#growth-sign-filter').on('change', function() {
            applyFilters();
        });

        // No-op kept for backward compatibility with existing callers (e.g. tableBuilt).
        function applySectionColumnVisibility(_sectionVal) {
            if (table && table.redraw) table.redraw(true);
        }

        // Flatten one tree branch for export (parent row then all _children), without mutating source objects
        function ebay3FlattenTreeBranchForExport(rowData, out) {
            if (!rowData || typeof rowData !== 'object') {
                return;
            }
            var kids = rowData._children;
            var rowCopy = {};
            Object.keys(rowData).forEach(function(k) {
                if (k !== '_children') {
                    rowCopy[k] = rowData[k];
                }
            });
            out.push(rowCopy);
            if (Array.isArray(kids) && kids.length) {
                kids.forEach(function(child) {
                    ebay3FlattenTreeBranchForExport(child, out);
                });
            }
        }

        function ebay3CsvEscapeCell(val) {
            if (val === null || val === undefined) {
                return '';
            }
            if (typeof val === 'object') {
                try {
                    val = JSON.stringify(val);
                } catch (e) {
                    val = String(val);
                }
            } else {
                val = String(val);
            }
            if (/[",\r\n]/.test(val)) {
                return '"' + val.replace(/"/g, '""') + '"';
            }
            return val;
        }

        function ebay3VisibleExportColumns() {
            var cols = [];
            table.getColumns().forEach(function(col) {
                try {
                    if (!col.isVisible()) {
                        return;
                    }
                    var def = col.getDefinition();
                    if (def.download === false) {
                        return;
                    }
                    var f = def.field;
                    if (f === undefined || f === null || f === '' || f === '_select') {
                        return;
                    }
                    var t = def.title !== undefined && def.title !== null ? String(def.title) : String(f);
                    cols.push({ field: f, title: t });
                } catch (e) {}
            });
            return cols;
        }

        function ebay3DownloadManualCsv(filename, rows, cols) {
            var lines = [];
            lines.push(cols.map(function(c) {
                return ebay3CsvEscapeCell(c.title);
            }).join(','));
            rows.forEach(function(row) {
                lines.push(cols.map(function(c) {
                    return ebay3CsvEscapeCell(row[c.field]);
                }).join(','));
            });
            var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }

        // Export button: download CSV for current section (visible columns + filtered data)
        // Always build the CSV manually from table.getRows('active') so that all dropdown,
        // column, and tree filters are honored consistently. Tabulator's built-in
        // table.download('csv', { downloadRowRange: 'active' }) is unreliable with
        // dataTree + dataTreeFilter and can emit unfiltered rows.
        $('#export-section-btn').on('click', function() {
            var dateStr = new Date().toISOString().slice(0, 10);
            var filename = 'ebay3_export_' + dateStr + '.csv';
            try {
                if (!table) {
                    throw new Error('Table not ready');
                }

                var exportCols = ebay3VisibleExportColumns();
                if (!exportCols.length) {
                    throw new Error('No visible columns to export');
                }

                var viewMode = $('#view-mode-filter').val();
                var activeRows = table.getRows('active');
                var flatRows = [];

                if (viewMode === 'both') {
                    // Parent + SKU: emit each visible parent followed by all its descendants.
                    activeRows.forEach(function(rc) {
                        ebay3FlattenTreeBranchForExport(rc.getData(), flatRows);
                    });
                } else {
                    // sku or parent: emit only the rows that pass the active filter set,
                    // stripped of the _children reference so the CSV stays flat.
                    activeRows.forEach(function(rc) {
                        var d = rc.getData() || {};
                        var copy = {};
                        Object.keys(d).forEach(function(k) {
                            if (k !== '_children') {
                                copy[k] = d[k];
                            }
                        });
                        flatRows.push(copy);
                    });
                }

                ebay3DownloadManualCsv(filename, flatRows, exportCols);

                if (typeof showToast === 'function') {
                    showToast('success', 'Export started (' + flatRows.length + ' rows)');
                }
            } catch (e) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Export failed: ' + (e.message || e));
                } else {
                    alert('Export failed: ' + (e.message || e));
                }
            }
        });

        // NRL listing-status dropdown change handler (kw-nrl-dropdown class kept for compat)
        $(document).on('change', '.kw-nrl-dropdown', function() {
            var $select = $(this);
            var value = $select.val();
            var sku = $select.data('sku');
            $.ajax({
                url: '/update-ebay3-nr-data',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', sku: sku, field: 'NRL', value: value },
                success: function(response) {
                    if (response.success) {
                        showToast('NRL updated for ' + sku, 'success');
                    } else {
                        showToast('Error updating NRL', 'error');
                    }
                },
                error: function() { showToast('Error updating NRL', 'error'); }
            });
        });

        // NRA listing-status dropdown change handler (kw-nra-dropdown class kept for compat)
        $(document).on('change', '.kw-nra-dropdown', function() {
            var $select = $(this);
            var value = $select.val();
            var sku = $select.data('sku');
            $.ajax({
                url: '/update-ebay3-nr-data',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', sku: sku, field: 'NR', value: value },
                success: function(response) {
                    if (response.success) {
                        showToast('NRA updated for ' + sku, 'success');
                    } else {
                        showToast('Error updating NRA', 'error');
                    }
                },
                error: function() { showToast('Error updating NRA', 'error'); }
            });
        });

        // Update calc values
        function updateCalcValues() {
            const data = table.getData("active");
            let totalSales = 0;
            let totalProfit = 0;
            let sumLp = 0;
            
            data.forEach(row => {
                const profit = parseFloat(row['Total_pft']) || 0;
                const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                if (profit > 0 && salesL30 > 0) {
                    totalProfit += profit;
                    totalSales += salesL30;
                }
                sumLp += parseFloat(row['LP_productmaster']) || 0;
            });
        }

        // Update summary badges — same metrics/order as Ebay 2 (E Stock gate uses eBay Stock with E Stock fallback)
        function updateSummary() {
            const data = ebay3GetBackendSkuRows();

            let totalPftAmt = 0;
            let totalSalesAmt = 0;
            let totalLpAmt = 0;
            let totalEStockSum = 0;
            let zeroSoldCount = 0;
            let moreSoldCount = 0;
            let missingCount = 0;
            let mapCount = 0;
            let invStockCount = 0;

            data.forEach(row => {
                const estock = parseFloat(row['eBay Stock'] || row['E Stock'] || 0) || 0;
                const ebayL30 = parseFloat(row['eBay L30'] || 0);

                if (estock > 0) {
                    totalPftAmt += parseFloat(row['Total_pft'] || 0);
                    totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                    totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                    totalEStockSum += estock;
                    if (ebayL30 === 0) {
                        zeroSoldCount++;
                    } else {
                        moreSoldCount++;
                    }
                }

                const itemId = row['eBay_item_id'];
                const sku = String(row['(Child) sku'] || '').toUpperCase();
                const isParentRow = sku.includes('PARENT');
                const invQty = (typeof ebay3Qty === 'function') ? ebay3Qty(row['INV']) : (parseFloat(row['INV'] || 0) || 0);
                // Missing L: in stock (INV>0) but not listed on eBay (no item id); exclude NR — Amazon parity
                if (!isParentRow
                    && invQty > 0
                    && (!itemId || itemId === null || itemId === '')
                    && !ebay3RowNrReqIsNr(row)) {
                    missingCount++;
                }

                // Map / N Map — same rule as Ebay 2 badges
                if (ebay3RowMap(row)) {
                    mapCount++;
                } else if (ebay3RowNMap(row)) {
                    invStockCount++;
                }
            });

            let totalWeightedPrice = 0;
            let totalL30 = 0;
            let totalViews = 0;
            let totalL7Views = 0;
            let l7ViewsCount = 0;
            data.forEach(row => {
                if (parseFloat(row['eBay Stock'] || row['E Stock'] || 0) > 0) {
                    const price = parseFloat(row['eBay Price'] || 0);
                    const l30 = parseFloat(row['eBay L30'] || 0);
                    totalWeightedPrice += price * l30;
                    totalL30 += l30;
                    totalViews += parseFloat(row.views || 0);
                    totalL7Views += parseFloat(row.l7_views || 0);
                    l7ViewsCount++;
                }
            });
            const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
            // CVR = (real-orders L30 units sold / Σ views) × 100. Numerator is the orders-API
            // L30 units (same value /ebay3/daily-sales shows), not the laggier datasheet
            // "eBay L30" sum — matches the eBay 1 & 2 tabulator CVR.
            const avgCVR = totalViews > 0 ? (ORDERS_L30_TOTAL_QTY / totalViews * 100) : 0;
            const avgL7Views = l7ViewsCount > 0 ? (totalL7Views / l7ViewsCount) : 0;
            const prevAvgL7Views = avgL7ViewsGlobal;
            avgL7ViewsGlobal = avgL7Views;

            if (ebay3ServerSummary) {
                missingCount = ebay3ServerSummary.missing;
                mapCount = ebay3ServerSummary.map;
                invStockCount = ebay3ServerSummary.nMap;
            }

            $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
            $('#more-sold-count').text(moreSoldCount.toLocaleString());
            $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
            // Sales / GPFT% / GROI% are fixed server values from the same real L30 orders
            // /ebay3/daily-sales uses, so this page agrees with that page (the per-SKU datasheet
            // is tax-excluded, lags the Orders API, and only counts filtered rows).
            $('#total-sales-amt-badge').text('Sales: $' + Math.round(ORDERS_L30_TOTAL_SALES).toLocaleString());
            $('#avg-gpft-badge').text('GPFT: ' + Math.round(ORDERS_L30_GPFT) + '%');
            $('#groi-percent-badge').text('GROI: ' + Math.round(ORDERS_L30_GROI) + '%');
            // NPFT% = GPFT% − Ads%. NROI% = (GPFT$ − Ad Spend) / COGS × 100 (Amazon formula).
            $('#npft-percent-badge').text('NPFT: ' + Math.round(ORDERS_L30_GPFT - EBAY3_CHANNEL_ADS_PCT) + '%');
            const nroiBadge = (ORDERS_L30_COGS > 0)
                ? ((ORDERS_L30_PFT - EBAY3_AD_SPEND) / ORDERS_L30_COGS) * 100
                : ORDERS_L30_NROI;
            $('#nroi-percent-badge').text('NROI: ' + Math.round(nroiBadge) + '%');
            $('#avg-price-badge').text('Prc: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#avg-l7-views-badge').text('L7: ' + avgL7Views.toFixed(1));
            $('#total-inv-badge').text('E Stock: ' + Math.round(totalEStockSum).toLocaleString());

            $('#missing-count-badge').text('M L: ' + missingCount);
            $('#map-count-badge').text('Map: ' + mapCount);
            $('#inv-stock-badge').text('N Map: ' + invStockCount);

            // Repaint L7 View + S BID colours when the avg changes.
            if (table && Math.abs(prevAvgL7Views - avgL7Views) > 0.0001) {
                table.redraw(false);
            }
        }

        // Build Column Visibility Dropdown
        const COL_VIS_CATEGORY_KEYS = ['basics', 'pricing', 'advertisement', 'others'];
        const COL_VIS_CATEGORY_LABELS = {
            basics: 'Basics',
            pricing: 'Pricing',
            advertisement: 'Advertisement',
            others: 'Others'
        };

        function classifyEbay3Column(field, title) {
            const f = String(field || '');
            const t = String(title || field || '').replace(/<[^>]*>/g, '');
            const fl = f.toLowerCase();
            const tl = t.toLowerCase();
            const blob = fl + ' ' + tl;

            if (
                /^(views|l7_views|l7_views_chg_pct|l7_views_prev|_ads_pct|ca_bid_percentage|ca_suggested_bid|ca_promote_with_ad)$/i.test(f) ||
                /\b(ads\s*%|es\s*bid|c\s*bid|s\s*bid|promote|l30\s*view|l7\s*view)\b/i.test(t) ||
                /\b(bid|promote|ads)\b/i.test(blob)
            ) {
                return 'advertisement';
            }

            if (
                /^(eBay Price|GPFT%|PFT %|ROI%|NROI|lmp_price|linked_lmp_skus|linked_lmp_sku_add|SPRICE|_accept|SGPFT|SPFT|SGROI|SROI|E Dil%|SCVR|CVR_45|CVR_60)$/i.test(f) ||
                /\b(prc|price|gpft|npft|groi|nroi|lmp|t\s*prc|target|s\s*prc|s\s*gpft|s\s*pft|s\s*groi|sroi|dil|cvr)\b/i.test(tl) ||
                /^(_accept|\+)$/i.test(t)
            ) {
                return 'pricing';
            }

            if (
                /^(image_path|Parent|\(Child\) sku|INV|L30|rating|links_column|E Stock|Missing|MAP|nr_req|nrp|NRL|NR|eBay L30|eBay L45|eBay L60|growth_percent)$/i.test(f) ||
                /\b(image|parent|sku|inv|ov\s*l30|links|rating|stock|missing|map|nr\/req|nrp|nrl|nra|growth|e\s*l\d+)\b/i.test(tl)
            ) {
                return 'basics';
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
                    showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="show-all-columns-btn-menu"><i class="fa fa-eye"></i> Show All</a>';
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
                        if (!def.field) return;
                        if (def.field === '_parent_expand' || def.field === '_select') return;

                        const rawTitle = def.title || def.field;
                        const title = String(rawTitle).replace(/<[^>]*>/g, '').trim() || def.field;
                        const cat = classifyEbay3Column(def.field, title);

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
            });
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
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) {
                            col.hide();
                        }
                    });
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            applySectionColumnVisibility('all');
            syncEbay3PriceModeUi();
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
            applyFilters();
            
            // Set up periodic background retry check (every 30 seconds)
            setInterval(() => {
                backgroundRetryFailedSkus();
            }, 30000);
        });

        table.on('dataLoaded', function() {
            if (typeof isPlayNavigationActive === 'undefined' || !isPlayNavigationActive) {
                try {
                    if (table && table.getData) {
                        const d = table.getData('all');
                        if (d && d.length) {
                            allTableData = d;
                        }
                    }
                } catch (e) { /* ignore */ }
            }
            updateCalcValues();
            updateSummary();
            requestAnimationFrame(function() {
                reorderEbay3ParentRowsBelowSkus();
            });
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('dataTreeRowExpanded', function() {
            requestAnimationFrame(function() {
                reorderEbay3ParentRowsBelowSkus();
            });
        });

        table.on('renderComplete', function() {
            reorderEbay3ParentRowsBelowSkus();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        // Row checkbox: add/remove SKU from selectedSkus
        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).attr('data-sku') || $(this).data('sku');
            if (!sku) return;
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Select-all checkbox: add/remove all filtered (non-parent) SKUs
        $(document).on('change', '#select-all-checkbox', function() {
            const checked = $(this).prop('checked');
            const filteredData = table.getData('active').filter(function(row) {
                return !(row.Parent && row.Parent.startsWith('PARENT'));
            });
            const filteredSkus = filteredData.map(function(row) { return row['(Child) sku']; }).filter(Boolean);
            if (checked) {
                filteredSkus.forEach(function(sku) { selectedSkus.add(sku); });
            } else {
                filteredSkus.forEach(function(sku) { selectedSkus.delete(sku); });
            }
            table.getRows().forEach(function(row) {
                row.reformat();
            });
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Toggle column from dropdown (group header + individual)
        (function() {
            var colMenu = document.getElementById("column-dropdown-menu");
            if (colMenu) {
                colMenu.addEventListener("change", function(e) {
                    if (e.target.type !== 'checkbox') return;

                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        const group = e.target.dataset.group;
                        const checked = e.target.checked;
                        const groupEl = e.target.closest('.col-vis-group');
                        const itemCbs = groupEl
                            ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                            : colMenu.querySelectorAll('.col-vis-field-toggle[data-group="' + group + '"]');
                        itemCbs.forEach(function(cb) {
                            cb.checked = checked;
                            const col = table.getColumn(cb.value);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        saveColumnVisibilityToServer();
                        return;
                    }

                    const field = e.target.value;
                    const col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) col.show();
                    else col.hide();
                    syncGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                    saveColumnVisibilityToServer();
                });
                colMenu.addEventListener("click", function(e) {
                    var showAll = e.target.closest('#show-all-columns-btn-menu');
                    if (showAll) {
                        e.preventDefault();
                        e.stopPropagation();
                        table.getColumns().forEach(col => col.show());
                        buildColumnDropdown();
                        saveColumnVisibilityToServer();
                    }
                });
            }
            var showAllBtn = document.getElementById("show-all-columns-btn");
            if (showAllBtn) {
                showAllBtn.addEventListener("click", function() {
                    table.getColumns().forEach(col => {
                        col.show();
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                });
            }
        })();

        document.addEventListener("click", function(e) {
            // Copy SKU to clipboard
            if (e.target.classList.contains("copy-sku-btn")) {
                const sku = e.target.getAttribute("data-sku");
                
                navigator.clipboard.writeText(sku).then(function() {
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                }).catch(function(err) {
                    const textarea = document.createElement('textarea');
                    textarea.value = sku;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                });
            }
        });
        
        // LMP Modal function
        window.showLmpModal = function(lmpEntries) {
            let modalHtml = `
                <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="lmpModalLabel">Lowest Marketplace Prices</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Price</th>
                                            <th>Title</th>
                                            <th>Seller</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;
            
            lmpEntries.forEach(function(entry) {
                const price = entry.price ? '$' + parseFloat(entry.price).toFixed(2) : '-';
                const title = entry.title || '-';
                const seller = entry.seller || '-';
                const link = entry.link || '#';
                
                modalHtml += `
                    <tr>
                        <td><strong>${price}</strong></td>
                        <td>${title}</td>
                        <td>${seller}</td>
                        <td><a href="${link}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> View</a></td>
                    </tr>
                `;
            });
            
            modalHtml += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // This function is deprecated - using new enhanced LMP modal
        };
        
        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null
        };

        // Load Competitors Modal Function
        function cleanupLmpModalBackdrop() {
            document.querySelectorAll('.modal-backdrop').forEach(function(node) {
                node.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }

        function openLmpModal() {
            const el = document.getElementById('lmpModal');
            if (!el) {
                return;
            }
            if (el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
            cleanupLmpModalBackdrop();
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        }

        function loadEbayCompetitorsModal(sku) {
            $('#lmpSku').text(sku);
            
            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');
            
            openLmpModal();
            
            // Show loading state
            $('#lmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `);
            
            // Fetch LMP data
            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: { sku: sku },
                success: function(response) {
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.sku = sku;
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;
                        
                        renderEbayCompetitorsList(response.competitors, response.lowest_price);
                    } else {
                        $('#lmpDataList').html(`
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    console.error('Error loading competitors:', xhr);
                    $('#lmpDataList').html(`
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                        </div>
                    `);
                }
            });
        }

        // Render Competitors List Function
        function renderEbayCompetitorsList(competitors, lowestPrice) {
            if (!competitors || competitors.length === 0) {
                $('#lmpDataList').html(`
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No competitors found for this SKU
                    </div>
                `);
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `
                <thead class="table-dark">
                    <tr>
                        <th>Image</th>
                        <th>Price</th>
                        <th>Shipping</th>
                        <th>Total</th>
                        <th>Title</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            competitors.forEach(function(item) {
                const isLowest = item.total_price === lowestPrice;
                const rowClass = isLowest ? 'table-success' : '';
                const badge = isLowest ? '<span class="badge bg-success ms-2">Lowest</span>' : '';
                const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
                const imageCell = item.image
                    ? `<img src="${item.image}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                    : '<span class="text-muted">—</span>';
                
                html += `
                    <tr class="${rowClass}">
                        <td>${imageCell}</td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                        <td><strong>$${parseFloat(item.total_price).toFixed(2)}</strong> ${badge}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.title || 'N/A'}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay"><i class="fa fa-external-link"></i></a>
                                <button class="btn btn-sm btn-danger delete-ebay-lmp-btn" data-id="${item.id}" data-item-id="${item.item_id}" data-price="${item.total_price}" title="Delete this competitor"><i class="fa fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }

        // View Competitors Modal Event Listener
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            loadEbayCompetitorsModal(sku);
        });

        // Add Competitor Form Submission
        $('#addCompetitorForm').on('submit', function(e) {
            e.preventDefault();
            
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
            
            $.ajax({
                url: '/ebay-lmp-add',
                method: 'POST',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                data: {
                    sku: $('#addCompSku').val(),
                    item_id: $('#addCompItemId').val(),
                    price: $('#addCompPrice').val(),
                    shipping_cost: $('#addCompShipping').val() || 0,
                    product_link: $('#addCompLink').val(),
                    product_title: $('#addCompTitle').val()
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor added successfully', 'success');
                        $('#addCompItemId, #addCompPrice, #addCompShipping, #addCompLink, #addCompTitle').val('');
                        loadEbayCompetitorsModal($('#addCompSku').val());
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.error || 'Failed to add competitor', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Delete Competitor Button Click
        $(document).on('click', '.delete-ebay-lmp-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const itemId = $btn.data('item-id');
            const price = $btn.data('price');
            
            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) return;
            
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/ebay-lmp-delete',
                method: 'POST',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        loadEbayCompetitorsModal(currentLmpData.sku);
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to delete competitor', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.error || 'Failed to delete competitor', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

    });
</script>
@endsection

