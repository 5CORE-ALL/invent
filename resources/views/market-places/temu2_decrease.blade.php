@extends('layouts.vertical', ['title' => 'Temu 2 - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        /* Filter UI — matches /ebay-tabulator-view: compact dropdowns, badges on top */
        #ebay-filter-bar .form-select {
            width: auto !important;
            max-width: 140px;
            padding-right: 1.35rem !important;
            padding-left: 0.5rem !important;
            background-position: right 0.35rem center !important;
        }
        /* Compact SPRICE / LMP filter cluster — size to short labels, not longest option */
        #ebay-filter-bar #sprice-filter,
        #ebay-filter-bar #sprice-lmp-filter,
        #ebay-filter-bar #prc-lmp-filter,
        #ebay-filter-bar #lmp-filter {
            width: 5.75rem !important;
            max-width: 5.75rem !important;
            min-width: 0 !important;
        }
        #ebay-filter-bar #sprice-lmp-filter {
            width: 6.25rem !important;
            max-width: 6.25rem !important;
        }
        #ebay-filter-bar { gap: 8px 10px !important; }
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #summary-stats .ebay2-summary-badge-row,
        #summary-stats .d-flex { gap: 8px !important; }

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

        /* Parent row light blue background (same as /ebay-tabulator-view) */
        .tabulator-row.temu2-parent-row,
        .tabulator-row.temu2-parent-row .tabulator-cell,
        .tabulator-row.temu2-parent-row .tabulator-cell.tabulator-frozen,
        .tabulator-row.temu2-parent-row .tabulator-cell.tabulator-frozen-left {
            background-color: #fffef2 !important;
        }

        /* Keep frozen left columns above scrolling cells */
        #temu-table .tabulator-cell.tabulator-frozen,
        #temu-table .tabulator-cell.tabulator-frozen-left,
        #temu-table .tabulator-col.tabulator-frozen,
        #temu-table .tabulator-col.tabulator-frozen-left {
            z-index: 11;
        }

        /* Full-width table; columns autofit to cell values and scroll horizontally */
        #temu-table {
            width: 100% !important;
        }
        #temu-table .tabulator-tableholder {
            overflow-x: auto !important;
        }
        #temu-table .tabulator-cell {
            white-space: nowrap !important;
            text-overflow: clip !important;
        }
        #temu-table .tabulator-cell[tabulator-field="parent"],
        #temu-table .tabulator-cell[tabulator-field="sku"],
        #temu-table .tabulator-col[tabulator-field="parent"] .tabulator-col-content,
        #temu-table .tabulator-col[tabulator-field="sku"] .tabulator-col-content {
            text-align: left !important;
            justify-content: flex-start !important;
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

        /* Summary badges: wrap to next line — never clip/hide */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 0.3rem;
            width: 100%;
            overflow: visible;
        }

        #summary-stats .ebay2-summary-badge-row>.badge {
            flex: 0 0 auto;
            font-size: 0.7rem;
            padding: 0.25rem 0.45rem;
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
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
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'temu2'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="{{ asset('js/temu-view-data-upload.js') }}?v={{ @filemtime(public_path('js/temu-view-data-upload.js')) ?: 1 }}"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 2 - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex flex-column">
                <div class="d-flex align-items-center flex-wrap gap-2" id="ebay-filter-bar">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 180px; display: inline-block;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 180px; display: inline-block;">

                    {{-- Row type filter (All Rows / Parents / SKUs) — same as Amazon / Shopify B2C --}}
                    <select id="parent-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="Filter by row type: All Rows, Parents only, or SKUs only">
                        <option value="all">All Rows</option>
                        <option value="parents" selected>Parents</option>
                        <option value="skus">SKUs</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="tl30-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>T L30</option>
                        <option value="zero">0 T L30</option>
                        <option value="more">T L30 &gt; 0</option>
                    </select>

                    <select id="growth-sign-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="Temu T L30 vs T L60: (L30 − L60) / L60 × 100; L60=0 and L30&gt;0 counts as +100%">
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

                    <select id="gpft-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40plus">Above 40%</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR%</option>
                        <option value="0-0">0%</option>
                        <option value="0-3">0-3%</option>
                        <option value="3-7">3-7%</option>
                        <option value="7-13">7-13%</option>
                        <option value="13plus">13%+</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="cvr-trend-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR trend</option>
                        <option value="l60_gt_l30">CVR 60 &gt; CVR 30</option>
                        <option value="l30_gt_l60">CVR 30 &gt; CVR 60</option>
                        <option value="equal">CVR 60 = CVR 30</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm pricing-filter-item"
                        style="display: inline-block;"
                        title="SPRICE: Blank shows only rows with empty SPRICE">
                        <option value="all">SPRICE</option>
                        <option value="blank">Blank</option>
                    </select>

                    <select id="sprice-lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="display: inline-block;"
                        title="Sprice/LMP: Red = SPRICE &gt; LMP">
                        <option value="all">S/LMP</option>
                        <option value="red">Red</option>
                    </select>

                    <select id="prc-lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="display: inline-block;"
                        title="Prc/LMP: Red = Temu Price &gt; LMP">
                        <option value="all">P/LMP</option>
                        <option value="red">Red</option>
                    </select>

                    <select id="lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="display: inline-block;"
                        title="LMP: Red = no LMP value">
                        <option value="all">LMP</option>
                        <option value="red">Red</option>
                    </select>

                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="matchFilterDropdown"
                            data-bs-toggle="dropdown" data-color="all" aria-expanded="false"
                            title="Match: Green = S PRC is LMP − $0.01; Red = not matched. Diff ± from Diff column.">
                            <span class="status-circle default"></span> Match
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="matchFilterDropdown">
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="all">
                                    <span class="status-circle default"></span> All Match</a></li>
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="green">
                                    <span class="status-circle green"></span> <span class="match-filter-green-label">Green (0)</span></a></li>
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="red">
                                    <span class="status-circle red"></span> <span class="match-filter-red-label">Red (0)</span></a></li>
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="red-">
                                    <span class="status-circle red"></span> <span class="match-filter-red-minus-label">Diff − (0)</span></a></li>
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="red+">
                                    <span class="status-circle red"></span> <span class="match-filter-red-plus-label">Diff + (0)</span></a></li>
                            <li><a class="dropdown-item match-column-filter" href="#" data-color="none">
                                    <span class="status-circle default"></span> <span class="match-filter-none-label">No LMP (0)</span></a></li>
                        </ul>
                    </div>

                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Temu Ship) / marketplace%">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button" title="Apply Target ROI%">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <button type="button" id="apply-lmp-minus-1-toolbar-btn"
                        class="btn btn-sm btn-outline-primary ms-2 fw-bold pricing-filter-item"
                        title="Apply LMP: set SPRICE so S Temu B Prc = LMP × 0.99 for selected SKUs">
                        LMP
                    </button>

                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Temu Ship) / (marketplace% − Target GPFT%/100)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button" title="Apply Target GPFT%">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <select id="dil-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                        </ul>
                    </div>

                    <button id="inc-dec-btn" class="btn btn-sm btn-secondary pricing-filter-item"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'temu2'])

                    {{-- Temu-only actions (kept after ebay-aligned filters) --}}
                    <div class="btn-group align-items-center pricing-filter-item" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <div class="dropdown pricing-filter-item">
                        <button type="button" class="btn btn-sm btn-success" id="export-btn"
                            data-bs-toggle="dropdown" aria-expanded="false" title="Export">
                            <i class="fa fa-download"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="export-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="export-l30-btn">
                                    <i class="fa fa-download me-1"></i> Export L30
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="export-l7-btn">
                                    <i class="fa fa-download me-1"></i> Export L7
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="dropdown pricing-filter-item">
                        <button type="button" class="btn btn-sm btn-success" id="upload-actions-btn"
                            data-bs-toggle="dropdown" aria-expanded="false" title="Upload">
                            <i class="fa fa-upload"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="upload-actions-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="sync-temu2-api-pricing">
                                    <i class="fa fa-cloud-download-alt me-1 text-info"></i> Sync Pricing (API)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadPricingModal">
                                    <i class="fa fa-dollar-sign me-1 text-info"></i> Up Pricing (Goods ID)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadDailyDataModal">
                                    <i class="fa fa-calendar me-1 text-primary"></i> Up Daily Data
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadViewDataModal">
                                    <i class="fa fa-eye me-1 text-success"></i> Up View Data
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadLmpModal">
                                    <i class="fa fa-link me-1 text-warning"></i> Up LMP
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <small id="search-result-info" class="text-muted" style="display: none;"></small>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-dark fs-6 p-2" id="rows-count-badge"
                            style="color: white; font-weight: bold;"
                            title="Number of rows currently shown after filters">Rows: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter 0 sold items (INV&gt;0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                            title="Click to filter items with sales (INV&gt;0)">&gt; 0 Sold: 0</span>
                        <span class="badge bg-primary fs-6 p-2 temu-badge-history" id="total-sales-amt-badge"
                            data-badge-metric="total_sales" data-badge-label="Sales"
                            style="color: black; font-weight: bold; cursor: pointer;"
                            title="L30 sales on Full Temu Price: (Base × 1.1364) + $2.99 if ≤ $26.99">Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-recovery-badge"
                            style="color: white; font-weight: bold;"
                            title="Recovery Price = Sales × 0.88 (Full Temu Price × 0.88 × Qty)">Recovery: $0</span>
                        <span class="badge fs-6 p-2" id="total-spend-badge"
                            style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; font-weight: bold;"
                            title="Sum of Spend from Temu 2 Ads upload">Spend: $0</span>
                        <span class="badge fs-6 p-2 temu-badge-history" id="qty-sold-badge"
                            data-badge-metric="total_quantity" data-badge-label="QTY"
                            style="background-color: #6f42c1; color: white; font-weight: bold; cursor: pointer;"
                            title="L30 units sold">Qty: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                            style="color: black; font-weight: bold;"
                            title="GPFT% = Σ Full-Price PFT ÷ Σ Full Temu Price Sales × 100 (same as /temu-decrease)">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge"
                            style="color: white; font-weight: bold;"
                            title="GROI% = Σ R-Price PFT ÷ Σ COGS × 100 (same as /temu-decrease)">GROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percent-badge"
                            style="background-color: #d63384; color: white; font-weight: bold;"
                            title="Ads% = Ad Spend / Full Temu Price Sales × 100">Ads: 0%</span>
                        <span class="badge bg-success fs-6 p-2" id="avg-npft-badge"
                            style="color: white; font-weight: bold;"
                            title="NPFT% = GPFT% − Ads% (Full Temu Price)">NPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="avg-nroi-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="NROI% = GROI% − Ads% (R Price GROI)">NROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: black; font-weight: bold;">Prc: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2 temu-badge-history" id="avg-cvr-badge"
                            data-badge-metric="avg_cvr_pct" data-badge-label="CVR %"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="CVR = (Sold / T Clicks) × 100">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2 temu-badge-history" id="total-views-badge"
                            data-badge-metric="total_views" data-badge-label="Views"
                            style="color: black; font-weight: bold; cursor: pointer;">Views: 0</span>
                        <span class="badge fs-6 p-2" id="total-t-clicks-badge"
                            style="background-color: #0d6efd; color: white; font-weight: bold;"
                            title="Sum of T Clicks from parent rows only (goods_id totals; SKUs excluded)">T Clicks: 0</span>
                        <span class="badge fs-6 p-2" id="total-t-clicks-7-badge"
                            style="background-color: #0a58ca; color: white; font-weight: bold;"
                            title="((T Clicks / 30) × 7) ÷ parent count — weekly pace per parent">T Clicks 7: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="missing-l-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing L (INV&gt;0, not listed, REQ)">M L: 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'temu2-price-gt-lmp-badge', 'pglChannelKey' => 'temu2', 'pglPriceField' => 'temu_price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'temu2-price-lt80-lmp-badge', 'pltChannelKey' => 'temu2', 'pltPriceField' => 'temu_price'])
                        <span class="badge fs-6 p-2" id="temu2-blue-triangle-badge" style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;" title="Blue triangle: S PRC ≠ Price.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="missing-m-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing M (listed, INV&gt;0, REQ, INV vs Temu Stock mismatch)">M M: 0</span>
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
                        <button id="sprc-26-99-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-dollar-sign"></i> SPRC 26.99
                        </button>
                        <button type="button" id="clear-sprice-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                        <button type="button" id="push-temu2-price-btn" class="btn btn-sm btn-success"
                            title="Push SPRICE→base: inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)">
                            <i class="fas fa-cloud-upload-alt"></i> Push Prices
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Modal: Add New + List (like Competitors), lowest LMP highlighted -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="lmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="lmpModalSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewPrice" placeholder="e.g. 29.99">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0" title="Added to Price for LMP / L1">Delivery</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewDelivery" placeholder="0.00">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-0">Product Link</label>
                                <input type="text" class="form-control form-control-sm" id="lmpNewLink" placeholder="https://...">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="lmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="lmpClearFormBtn" title="Clear form"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>
                    </div>
                    <h6 class="mb-2">LMP List</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="lmpListTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Price</th>
                                    <th style="width: 90px;" title="Added to Price for LMP / L1">Delivery</th>
                                    <th style="width: 90px;" title="Price + Delivery (defaults Del $2.99 when Price &lt; $27)">Price+D</th>
                                    <th>Link</th>
                                    <th style="width: 80px;">Actions</th>
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

    <!-- Upload Daily Data Modal (temu2_daily_data / temu2_daily_data_l60) -->
    <div class="modal fade" id="uploadDailyDataModal" tabindex="-1" aria-labelledby="uploadDailyDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDailyDataModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Temu 2 Daily Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dailyDataUploadPeriod" class="form-label">Upload for</label>
                        <select id="dailyDataUploadPeriod" class="form-select form-select-sm" style="width: auto;">
                            <option value="L30">L30 Sales (temu2_daily_data)</option>
                            <option value="L60">L60 Sales (temu2_daily_data_l60)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="dailyDataFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="dailyDataFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">
                            Same format as Temu 2 tabulator daily upload.
                            <a href="{{ route('temu.daily.sample') }}" class="text-primary">
                                <i class="fa fa-download me-1"></i>Download Sample
                            </a>
                        </div>
                    </div>
                    <div id="uploadProgressContainer" style="display: none;">
                        <div class="progress mb-2" style="height: 25px;">
                            <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="uploadStatus" class="text-muted small"></div>
                    </div>
                    <div id="uploadResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startUploadBtn">
                        <i class="fa fa-upload me-1"></i>Start Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload LMP Modal (shared temu_lmp) -->
    <div class="modal fade" id="uploadLmpModal" tabindex="-1" aria-labelledby="uploadLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="uploadLmpModalLabel">
                        <i class="fa fa-link me-2"></i>Upload Temu LMP
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadLmpForm" method="POST" action="{{ route('temu.lmp.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="lmp_file" class="form-label fw-bold">File (Excel or CSV/TSV)</label>
                            <input type="file" class="form-control" id="lmp_file" name="lmp_file"
                                   accept=".xlsx,.xls,.csv,.txt" required>
                            <div class="form-text">
                                Writes to shared <code>temu_lmp</code> (used by Temu 1 &amp; Temu 2 decrease).
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadLmpForm" class="btn btn-warning">
                        <i class="fa fa-upload me-1"></i>Upload LMP
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload View Data Modal -->
    <div class="modal fade" id="uploadViewDataModal" tabindex="-1" aria-labelledby="uploadViewDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadViewDataModalLabel">
                        <i class="fa fa-eye me-2"></i>Upload Temu View Data
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
                    
                    <form id="uploadViewDataForm" action="{{ route('temu2.viewdata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if($errors->any())
                            <div class="alert alert-danger py-2">
                                {{ $errors->first() }}
                            </div>
                        @endif
                        <div class="mb-3">
                            <label for="viewDataFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose View File(s)
                            </label>
                            <input type="file" class="form-control" id="viewDataFile" name="files[]" accept=".xlsx,.xls,.csv,.tsv,.txt" multiple>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Select multiple Seller Center daily exports (.xlsx / .xls / .csv / .tsv). Max 10MB each.
                                Click <strong>Choose files</strong> again to add more — they stay queued.
                                Writes to <code>temu2_view_data</code> (separate from Temu 1).
                            </div>
                            <div id="viewDataFileList" class="small mt-2"></div>
                            <div id="viewDataUploadStatus" class="alert py-2 px-3 mb-0 mt-2" style="display:none;"></div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            First batch replaces existing rows in <code>temu2_view_data</code>. Extra files merge (same Date + Goods ID → last wins).
                            <a href="{{ route('temu2.viewdata.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadViewDataForm" class="btn btn-success">
                        <i class="fa fa-upload me-1"></i>Up View Data
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Upload Pricing Modal -->
    <div class="modal fade" id="uploadPricingModal" tabindex="-1" aria-labelledby="uploadPricingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="uploadPricingModalLabel">
                        <i class="fa fa-dollar-sign me-2"></i>Upload Temu 2 Pricing Data
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
                    
                    <form id="uploadPricingForm" method="POST" action="{{ route('temu2.pricing.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="pricingFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Temu 2 listing / pricing export
                            </label>
                            <input type="file" class="form-control" name="pricing_file" id="pricingFile"
                                   accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                            <div class="form-text">
                                Accepts .xlsx, .xls, .csv, or .tsv (Max: 20MB)
                            </div>
                        </div>
                        <div class="alert alert-info mb-2">
                            <strong>Format:</strong> Category, Category id, Product name, Contribution Goods,
                            SKU, <strong>Goods ID</strong>, SKU ID, Variation, Quantity, <strong>Base price</strong>, …
                            <br>
                            Prices are matched by <strong>SKU</strong> to CP Master (<code>product_master</code>) and shown in the Base Price column.
                            <br>
                            <a href="{{ route('temu2.pricing.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                        <div id="pricingUploadResult" class="alert" style="display:none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-info" id="startPricingUploadBtn">
                        <i class="fa fa-upload me-1"></i>Up Pricing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal (UI matches Amz: teal header, ref panel High/Med/Low, median line, value labels on points) -->
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
                    <h5 class="modal-title"><i class="fa fa-chart-line me-2"></i>Daily Average Views History</h5>
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
                            <small><i class="fa fa-info-circle"></i> Shows historical average views across all products</small>
                        </div>
                    </div>
                    <div id="avg-views-no-data-message" class="alert alert-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle me-2"></i>
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
    <div class="modal fade" id="temu2EditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="temu2EditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="temu2EditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="temu2EditSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="temu2EditSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="temu2EditBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="temu2EditBuyerLink" placeholder="https://...">
                    </div>
                    <div id="temu2EditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="temu2SaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'temu2'])
@endsection

@section('script-bottom')
<script>
    // Same margin as /temu-decrease — marketplace_percentages.Temu (TEMU_MARGIN)
    const TEMU_MARGIN = {{ (float) ($temuMargin ?? \App\Services\TemuShopifySalesService::temuMarginDecimal()) }};
    function temuSpriceMargin(rowData) {
        const marginRaw = parseFloat(rowData && rowData.percentage);
        return (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : TEMU_MARGIN;
    }
    const TEMU_FULL_PRICE_MULT = 1.1364;
    function temu2RPriceFromRow(rowData) {
        const basePrice = parseFloat(rowData && rowData.base_price) || 0;
        if (basePrice > 0) return basePrice <= 26.99 ? basePrice + 2.99 : basePrice;
        return parseFloat(rowData && rowData.temu_price) || 0;
    }
    /** Full Temu Price from Base: (base × 1.1364), then +$2.99 if ≤ $26.99 */
    function temu2FullPriceFromBase(basePrice) {
        const b = parseFloat(basePrice) || 0;
        if (b <= 0) return 0;
        let full = b * TEMU_FULL_PRICE_MULT;
        if (full <= 26.99) full += 2.99;
        return full;
    }
    function temu2FullPriceFromRow(rowData) {
        return temu2FullPriceFromBase(parseFloat(rowData && rowData.base_price) || 0);
    }
    function temu2BaseFromFullPrice(full) {
        const f = parseFloat(full) || 0;
        if (!(f > 0)) return 0;
        const candidates = [(f - 2.99) / TEMU_FULL_PRICE_MULT, f / TEMU_FULL_PRICE_MULT];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            const rebuilt = temu2FullPriceFromBase(base);
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
    const TEMU_FIXED_ADS_PERCENT = 2.2;
    function temuAdsPercentForNet() {
        return TEMU_FIXED_ADS_PERCENT;
    }
    function temu2PftDollars(rowData) {
        const rPrice = temu2RPriceFromRow(rowData);
        if (!(rPrice > 0)) return null;
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        return (rPrice * 0.95) - ship - lp;
    }
    function temu2NpftDollars(rowData) {
        const pft = temu2PftDollars(rowData);
        if (pft == null) return null;
        const temuPrice = temu2FullPriceFromRow(rowData);
        return pft - (temuPrice * (temuAdsPercentForNet() / 100));
    }
    function temu2SRPriceFromRow(rowData, spriceOverride) {
        const sprice = parseFloat(spriceOverride != null ? spriceOverride : (rowData && rowData.sprice)) || 0;
        if (!(sprice > 0)) return 0;
        const rPrice = temu2RPriceFromRow(rowData);
        const fullPrice = temu2FullPriceFromRow(rowData);
        if (fullPrice > 0 && Math.abs(sprice - fullPrice) < 0.02) return rPrice;
        if (rPrice > 0 && Math.abs(sprice - rPrice) < 0.02) return rPrice;
        const candidates = [(sprice - 2.99) / TEMU_FULL_PRICE_MULT, sprice / TEMU_FULL_PRICE_MULT];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            const rebuilt = temu2FullPriceFromBase(base);
            const err = Math.abs(rebuilt - sprice);
            if (err < bestErr) {
                bestErr = err;
                best = base;
            }
        });
        return best > 0 ? (best <= 26.99 ? best + 2.99 : best) : 0;
    }
    function temu2SpftDollars(rowData, spriceOverride) {
        const sR = temu2SRPriceFromRow(rowData, spriceOverride);
        if (!(sR > 0)) return null;
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const ship = parseFloat(rowData && rowData.temu_ship) || 0;
        return (sR * 0.95) - ship - lp;
    }
    function temu2SnpftDollars(rowData, spriceOverride) {
        const spft = temu2SpftDollars(rowData, spriceOverride);
        if (spft == null) return null;
        const sprice = parseFloat(spriceOverride != null ? spriceOverride : (rowData && rowData.sprice)) || 0;
        return spft - ((sprice > 0 ? sprice : 0) * (temuAdsPercentForNet() / 100));
    }
    // S Recovery rate = 0.88 (S Profit / SROI). S Temu B Prc inverts Temu Price.
    const TEMU2_S_RECOVERY_RATE = 0.88;
    function updateTemu2RecoveryBadge(salesTotal) {
        const recovery = Math.round((Number(salesTotal) || 0) * TEMU2_S_RECOVERY_RATE);
        const $b = $('#total-recovery-badge');
        $b.text('Recovery: $' + recovery.toLocaleString());
        if (recovery < 0) {
            $b.removeClass('bg-info').addClass('bg-danger').css({'background-color': '#dc3545', 'color': 'white'});
        } else {
            $b.removeClass('bg-danger').addClass('bg-info').css({'background-color': '', 'color': 'white'});
        }
    }
    function temu2SRecovery(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return 0;
        return s * TEMU2_S_RECOVERY_RATE;
    }
    /** S Profit = S Recovery × margin − LP − Temu Ship */
    function temu2SProfit(rowData, sprice) {
        const recovery = temu2SRecovery(sprice != null ? sprice : (rowData && rowData.sprice));
        if (recovery <= 0) return null;
        const margin = temuSpriceMargin(rowData);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const temuShip = parseFloat(rowData && rowData.temu_ship) || 0;
        return (recovery * margin) - lp - temuShip;
    }
    /** SGPRFT/SPFT profit on full Sprice (not S Recovery). */
    function temu2SPftProfit(rowData, sprice) {
        const s = parseFloat(sprice != null ? sprice : (rowData && rowData.sprice)) || 0;
        if (s <= 0) return null;
        const margin = temuSpriceMargin(rowData);
        const lp = parseFloat(rowData && rowData.lp) || 0;
        const temuShip = parseFloat(rowData && rowData.temu_ship) || 0;
        return (s * margin) - lp - temuShip;
    }
    // Same shared DB persistence as /ebay-tabulator-view (channel_tabulator_column_settings)
    const TABULATOR_COLUMN_CHANNEL = 'temu2_decrease';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    let table = null;

    /**
     * Temu2 push base from SPRICE — inverse of Temu Price (same as /temu-decrease).
     */
    function temuPushBaseFromSprice(sprice) {
        const s = parseFloat(sprice);
        if (!isFinite(s) || s <= 0) return null;
        const push = temu2BaseFromFullPrice(s);
        if (!isFinite(push)) return null;
        return +push.toFixed(2);
    }
    function temu2FormatMoney(amount, opts) {
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
        const full = temu2FullPriceFromBase(T);
        if (!(full > 0)) return null;
        return +full.toFixed(2);
    }
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    let soldSpriceBlankFilterActive = false;
    let latestAvgViews = 0;
    let adsReqFilter = 'all';
    let adsRunningFilter = 'all';
    
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
                                return (currentSkuChartMetric === 'views' || currentSkuChartMetric === 't_clicks' || currentSkuChartMetric === 'temu_l30') ? (context.dataset.label + ': ' + Math.round(v)) : (context.dataset.label + ': ' + v);
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
        fetch(`/temu-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}&temu2=1`)
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
                const isTClicks = metric === 't_clicks';
                const isTemuL30 = metric === 'temu_l30';
                const isPct = ['profit_percent', 'ads_percent', 'roi_percent', 'npft_percent', 'nroi_percent'].indexOf(metric) >= 0;
                const values = isCvr
                    ? data.map(d => Number(d.cvr_percent) || 0)
                    : isTClicks
                        ? data.map(d => Number(d.t_clicks != null ? d.t_clicks : ((Number(d.views) || 0) + (Number(d.ad_clicks) || 0))) || 0)
                        : isViews
                            ? data.map(d => Number(d.views) || 0)
                            : isTemuL30
                                ? data.map(d => Number(d.temu_l30) || 0)
                                : isPct
                                    ? data.map(d => Number(d[metric]) || 0)
                                    : data.map(d => Number(d.price) || 0);
                const temuChartMetricLabels = { price: 'Price', views: 'O Clicks', t_clicks: 'T Clicks', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
                const temuChartMetricColors = { price: '#adb5bd', views: '#0000FF', t_clicks: '#6610f2', cvr: '#008000', temu_l30: '#fd7e14', profit_percent: '#ff1493', ads_percent: '#ffc107', roi_percent: '#6f42c1', npft_percent: '#28a745', nroi_percent: '#17a2b8' };
                const bgColors = { price: 'rgba(108,117,125,0.08)', views: 'rgba(0,0,255,0.1)', t_clicks: 'rgba(102,16,242,0.1)', cvr: 'rgba(0,128,0,0.1)', temu_l30: 'rgba(253,126,20,0.1)', profit_percent: 'rgba(255,20,147,0.1)', ads_percent: 'rgba(255,193,7,0.1)', roi_percent: 'rgba(111,66,193,0.1)', npft_percent: 'rgba(40,167,69,0.1)', nroi_percent: 'rgba(23,162,184,0.1)' };
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
                const refFmt = (isCvr || isPct) ? cvrFmt : (isViews || isTClicks || isTemuL30) ? intFmt : temuChartFmtVal;
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
    

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function temu2StdPrcChangeDotMeta(stdPrc, comparePrice) {
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

    function temu2StdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = temu2StdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyTemu2StandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            if (typeof applyChannelSpriceFromStdChange === 'function') {
                applyChannelSpriceFromStdChange(r);
            }
            if (rowKey === target) primaryRow = r;
        });
        return primaryRow;
    }

    document.addEventListener('lmp-modal-sp-saved', function(e) {
        const detail = (e && e.detail) || {};
        const sku = detail.sku;
        const saved = parseFloat(detail.standard_price);
        if (!sku || !isFinite(saved) || saved <= 0) return;
        applyTemu2StandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    function isTemu2ParentRow(data) {
        if (!data) return false;
        if (data.is_parent === true || data.is_parent === 1 || data.is_parent === '1') return true;
        const sku = String(data.sku || data['(Child) sku'] || '').trim().toUpperCase();
        return sku.indexOf('PARENT ') === 0 || sku === 'PARENT' || sku.includes('PARENT');
    }
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'temu2'])
    function temu2RowSpriceForAlert(data) {
        let sprice = parseFloat(data && (data.sprice != null ? data.sprice : data.SPRICE)) || 0;
        if (typeof chPromoSpriceFromStdTPromo === 'function' && !isTemu2ParentRow(data)) {
            const calc = chPromoSpriceFromStdTPromo(data);
            if (calc > 0) sprice = calc;
        }
        return sprice;
    }
    function temu2HasBlueTriangle(data) {
        if (isTemu2ParentRow(data)) return false;
        const sprice = temu2RowSpriceForAlert(data);
        const price = parseFloat(data && data.temu_price) || 0;
        return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
    }
    let blueTriangleFilterActive = false;
    function syncTemu2TriangleBadgeState() {
        $('#temu2-blue-triangle-badge').css({
            outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: blueTriangleFilterActive ? '2px' : ''
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
            const metricLabels = { price: 'Price', views: 'O Clicks', t_clicks: 'T Clicks', cvr: 'CVR%', temu_l30: 'Temu L30', profit_percent: 'GPRFT%', ads_percent: 'ADS%', roi_percent: 'GROI%', npft_percent: 'NPFT%', nroi_percent: 'NROI%' };
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

        function temu2SkuFromCheckbox(el) {
            return String($(el).attr('data-sku') || '').trim();
        }

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            if (!table) return;
            temu2CurrentPageSkuRows().forEach(function(row) {
                const sku = String((row.getData() || {}).sku || '').trim();
                if (!sku) return;
                if (isChecked) selectedSkus.add(sku);
                else selectedSkus.delete(sku);
            });
            $('.sku-select-checkbox').each(function() {
                const sku = temu2SkuFromCheckbox(this);
                $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
            });
            $(this).prop('indeterminate', false);
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = temu2SkuFromCheckbox(this);
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

        $('#apply-lmp-minus-1-toolbar-btn').on('click', function() {
            applyLmpMinus1Percent();
        });

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        /*
         * Target ROI% / Target GPFT% bulk apply (Temu2)
         * ----------------------------------------------------------------
         * Back-solves S PRC for every selected row so the resulting SROI / SGPFT
         * column matches the entered target:
         *     S Recovery = sprice × 0.88
         *     SROI%  = S Profit / lp; S Profit = (S Recovery × marketplace% − temu_ship − lp)
         *           → sprice = (lp * (1 + ROI%/100) + temu_ship) / (0.88 × marketplace%)
         *     SGPFT% on Full Sprice = (sprice × marketplace% − temu_ship − lp) / sprice * 100
         *           → sprice = (lp + temu_ship) / (marketplace% − GPFT%/100)
         * Each save goes through the existing saveSpriceWithRetry() pipeline so
         * sprice_status (processing → saved / error) and sgprft_percent /
         * sroi_percent stay in sync exactly like Decrease / Increase / Same Price.
         * Rounding is plain 2-decimal — no .99 / .49 retail snapping — because
         * snapping would shift the achieved SROI / SGPFT off the target.
         */
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
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU first', 'error');
                return;
            }

            applyTargetBackSolveTemu2(function (rowData) {
                const lp = parseFloat(rowData['lp']) || 0;
                if (lp <= 0) return null;
                const temuShip = parseFloat(rowData['temu_ship']) || 0;
                const margin = temuSpriceMargin(rowData);
                // SROI = S Profit/LP; S Profit = (S Recovery × margin) − ship − LP
                // → sprice = (lp × (1 + ROI%/100) + ship) / (0.88 × marketplace%)
                const candidate = (lp * (1 + targetRoiPct / 100) + temuShip) / (TEMU2_S_RECOVERY_RATE * margin);
                const newPrice = +candidate.toFixed(2);
                if (!isFinite(newPrice) || newPrice <= 0) return null;
                return newPrice;
            }, `Target ROI ${targetRoiPct}%`);
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
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU first', 'error');
                return;
            }

            const marginCap = TEMU_MARGIN;
            const denomCheck = marginCap - targetGpftPct / 100;
            if (denomCheck <= 0) {
                showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < ${(marginCap * 100).toFixed(0)}% (marketplace take-home).`, 'error');
                return;
            }

            applyTargetBackSolveTemu2(function (rowData) {
                const lp = parseFloat(rowData['lp']) || 0;
                if (lp <= 0) return null;
                const temuShip = parseFloat(rowData['temu_ship']) || 0;
                const margin = temuSpriceMargin(rowData);
                const denom = margin - targetGpftPct / 100;
                if (denom <= 0) return null;
                // SGPRFT on Full Sprice → sprice = (lp + ship) / (margin − GPFT%/100)
                const candidate = (lp + temuShip) / denom;
                const newPrice = +candidate.toFixed(2);
                if (!isFinite(newPrice) || newPrice <= 0) return null;
                return newPrice;
            }, `Target GPFT ${targetGpftPct}%`);
        });

        // Shared back-solve runner — mirrors applyDiscount's per-row save loop so
        // sprice_status icons and reformat() behave identically.
        function applyTargetBackSolveTemu2(computeFn, labelPrefix) {
            const allData      = table.getData('all');
            const totalSkus    = selectedSkus.size;
            let updatedCount   = 0;
            let errorCount     = 0;
            let skippedNoLp    = 0;

            const tasks = [];
            allData.forEach(row => {
                const sku = row['sku'];
                if (!sku || !selectedSkus.has(sku)) return;

                const newPrice = computeFn(row);
                if (newPrice == null) { skippedNoLp++; return; }

                const tableRow = table.getRows().find(r => r.getData()['sku'] === sku);
                if (!tableRow) return;
                const originalSPrice = parseFloat(row['sprice']) || 0;

                tableRow.update({ sprice: newPrice, sprice_status: 'processing' });
                tableRow.reformat();

                tasks.push({ sku: sku, newPrice: newPrice, tableRow: tableRow, originalSPrice: originalSPrice });
            });

            if (tasks.length === 0) {
                const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
                showToast(`No selected rows have a usable LP > 0${note}`, 'warning');
                return;
            }

            tasks.forEach(t => {
                saveSpriceWithRetry(t.sku, t.newPrice, t.tableRow)
                    .then(() => {
                        updatedCount++;
                        if (updatedCount + errorCount === tasks.length) {
                            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
                            if (errorCount === 0) {
                                showToast(`${labelPrefix} applied to ${updatedCount} SKU(s)${note}`, 'success');
                            } else {
                                showToast(`${labelPrefix} applied to ${updatedCount} SKU(s), ${errorCount} failed${note}`, 'error');
                            }
                        }
                    })
                    .catch(() => {
                        errorCount++;
                        if (t.tableRow) {
                            t.tableRow.update({ sprice: t.originalSPrice });
                            t.tableRow.reformat();
                        }
                        if (updatedCount + errorCount === tasks.length) {
                            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
                            showToast(`${labelPrefix} applied to ${updatedCount} SKU(s), ${errorCount} failed${note}`, 'error');
                        }
                    });
            });
        }

        $('#target-roi-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Badge click filters — same pattern as /ebay-tabulator-view
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingLFilterActive = false;
        let missingMFilterActive = false;
        let lessAmzFilterActive = false;
        let moreAmzFilterActive = false;
        let mapBadgeFilterActive = false;
        // aliases kept for any leftover refs
        let missingBadgeFilterActive = false;
        let notMapBadgeFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;

        // Map tolerance — same formula as /map-issues, /temu-decrease, and the
        // /all-marketplace-master Temu 2 row helper (getTemuLiveMapMissNMapFromDecreaseData):
        //   if inv * 3% < 3   →  mapped iff diff <= 3
        //   else              →  mapped iff round((diff / inv) * 100) <= 3
        // This produces identical results to those endpoints (down to the round-to-3 edge
        // case at inv ≈ 350+ and diff/inv between 3.0% and 3.5%) so the badge count exactly
        // matches the Map / N Map column on /all-marketplace-master's Temu 2 row.
        // INV <= 0 always counts as mapped.
        function temuInvWithinMapTolerance(inv, stock) {
            const invNum = parseFloat(inv) || 0;
            const stockNum = parseFloat(stock) || 0;
            if (invNum <= 0) return true;
            const diff = Math.abs(invNum - stockNum);
            if (invNum * 0.03 < 3) return diff <= 3;
            return Math.round((diff / invNum) * 100) <= 3;
        }

        /**
         * Temu Recovery (same as /pricing-master-cvr Temu 2 LMP):
         * price ≤ $27 → (Price × 0.85) + 2.99
         * price > $27 → Price × 0.85
         */
        function temuLmpRecovery(price) {
            const p = parseFloat(price);
            if (!(p > 0)) return null;
            if (p <= 27) return +((p * 0.85) + 2.99).toFixed(2);
            return +(p * 0.85).toFixed(2);
        }

        /** Lowest non-ignored raw LMP from entries / lmp_raw (before Temu Recovery). */
        function getTemu2RawLmp(row) {
            if (!row) return null;
            const rawField = parseFloat(row.lmp_raw);
            if (Number.isFinite(rawField) && rawField > 0) return rawField;
            const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
            const prices = entries
                .filter(function(e) { return !e || !e.ignored; })
                .map(function(e) {
                    const p = e && e.price;
                    if (p === null || p === undefined || p === '' || isNaN(parseFloat(p))) return null;
                    const base = parseFloat(p);
                    const d = parseFloat(e.delivery);
                    let delivery = (!isNaN(d) && d > 0) ? d : 0;
                    if (delivery <= 0 && base < 27) delivery = 2.99;
                    return base + delivery;
                })
                .filter(function(p) { return p !== null && p > 0; });
            if (prices.length > 0) return Math.min.apply(null, prices);
            return null;
        }

        function getTemu2DisplayLmp(row) {
            const raw = getTemu2RawLmp(row);
            const recovery = temuLmpRecovery(raw);
            if (recovery != null && recovery > 0) return recovery;
            const v = parseFloat(row && row.lmp);
            return (isFinite(v) && v > 0) ? v : 0;
        }
        function temu2DisplayedSprice(row) {
            const n = parseFloat(row && row.sprice);
            return (isFinite(n) && n > 0) ? n : 0;
        }
        function temu2SpriceMatchesLmp(row) {
            if (!row || isTemu2ParentRow(row)) return false;
            const lmp = getTemu2DisplayLmp(row);
            const target = lmp > 0 ? +(lmp - 0.01).toFixed(2) : 0;
            const sprice = temu2DisplayedSprice(row);
            return target > 0 && sprice > 0 && Math.abs(sprice - target) < 0.015;
        }
        function temu2LmpDiffPct(row) {
            const lmp = getTemu2DisplayLmp(row);
            const sprice = temu2DisplayedSprice(row);
            if (!(lmp > 0) || !(sprice > 0)) return null;
            return ((lmp - sprice) / lmp) * 100;
        }
        function temu2MatchStatus(row) {
            if (!row || isTemu2ParentRow(row)) return null;
            const lmp = getTemu2DisplayLmp(row);
            if (!(lmp > 0)) return 'none';
            if (temu2SpriceMatchesLmp(row)) return 'green';
            const diff = temu2LmpDiffPct(row);
            if (diff == null) return 'none';
            if (diff > 0) return 'red+';
            if (diff < 0) return 'red-';
            return 'red';
        }
        function temu2MatchFilterMatches(status, filter) {
            if (filter === 'none') return status === 'none';
            if (!status || status === 'none') return false;
            if (filter === 'green') return status === 'green';
            if (filter === 'red') return status === 'red' || status === 'red-' || status === 'red+';
            if (filter === 'red-') return status === 'red-';
            if (filter === 'red+') return status === 'red+';
            return false;
        }

        /** Inverse of stemu = sprice ≤ 26.99 ? sprice + 2.99 : sprice */
        function temu2StemuPriceToSprice(desiredStemuPrice) {
            if (!isFinite(desiredStemuPrice) || desiredStemuPrice <= 0) return null;
            if (desiredStemuPrice <= 29.98) {
                const sprice = +(desiredStemuPrice - 2.99).toFixed(2);
                return sprice > 0 ? sprice : null;
            }
            return +desiredStemuPrice.toFixed(2);
        }

        /** Apply LMP: SPRICE so S Temu B Prc (push base) ≈ raw LMP × 0.99 */
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
                const lmp = getTemu2RawLmp(rowData);
                if (lmp === null) {
                    skippedCount++;
                    return;
                }
                const targetPush = +(lmp * 0.99).toFixed(2);
                const newSPrice = temuSpriceFromPushBase(targetPush);
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
                            let msg = 'LMP applied to ' + updatedCount + ' SKU(s)';
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
                            let msg = 'LMP applied to ' + updatedCount + ' SKU(s), ' + errorCount + ' failed';
                            if (skippedCount > 0) msg += ' (' + skippedCount + ' skipped)';
                            showToast(msg, 'error');
                        }
                    });
            });
        }

        $('#zero-sold-count-badge').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreSoldFilterActive = false;
            applyFilters();
        });

        $('#more-sold-count-badge').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false;
            applyFilters();
        });

        $('#missing-l-count-badge').on('click', function() {
            missingLFilterActive = !missingLFilterActive;
            missingBadgeFilterActive = missingLFilterActive;
            $(this).toggleClass('bg-secondary', !missingLFilterActive)
                   .toggleClass('bg-danger', missingLFilterActive);
            applyFilters();
            if (table && missingLFilterActive) {
                try { table.getColumn('lmp').show(); } catch (e) {}
            }
        });

        $('#missing-m-count-badge').on('click', function() {
            missingMFilterActive = !missingMFilterActive;
            notMapBadgeFilterActive = missingMFilterActive;
            mapBadgeFilterActive = false;
            $(this).toggleClass('bg-secondary', !missingMFilterActive)
                   .toggleClass('bg-danger', missingMFilterActive);
            applyFilters();
            if (table) {
                try {
                    if (missingMFilterActive) table.getColumn('MAP').show();
                    else table.getColumn('MAP').hide();
                } catch (e) {}
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function temu2CurrentPageSkuRows() {
            if (!table) return [];
            let allActive = [];
            try {
                allActive = (table.getRows('active') || []).filter(function(row) {
                    const d = row.getData() || {};
                    if (typeof isTemu2ParentRow === 'function' && isTemu2ParentRow(d)) return false;
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
            const pageRows = temu2CurrentPageSkuRows();
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
                if (row) {
                    row.update({ sprice_status: 'processing' });
                }
                
                $.ajax({
                    url: '/temu2-pricing/save-sprice',
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
                        // Only auto-bump to .49 when Decrease/Increase would produce an
                        // unchanged price. Same Price honors the typed value exactly.
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
                    url: '/temu2-pricing/save-sprice',
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
                const sku = temu2SkuFromCheckbox(this);
                $(this).prop('checked', sku !== '' && selectedSkus.has(sku));
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
                url: '/temu2-clear-sprice',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    skus: skusArray
                },
                beforeSend: function() {
                    $('#clear-sprice-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing...');
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
                    $('#clear-sprice-btn').prop('disabled', false).html('<i class="fa fa-trash"></i> Clear SPRICE');
                }
            });
        }

        // Badges wrap via CSS; no shrink-to-fit (keeps all badges visible).
        function fitSummaryBadges() {}

        function updateSummary() {
            if (!table) return;
            const allData = table.getData('all');
            const filteredData = table.getData('active');

            let totalProducts = allData.length;
            let totalQuantity = 0;
            let totalPriceWeighted = 0;
            let totalQty = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalRevenueFull = 0;
            let totalProfitFull = 0;
            let totalLp = 0;
            let totalSpend = 0;
            let totalSpendL30 = 0;
            let totalViews = 0;
            let totalTClicks = 0;
            let totalParentTClicks = 0;
            let totalParentCount = 0;
            let totalTemuL30 = 0;
            let zeroSoldCount = 0;
            let moreSoldCount = 0;
            let missingCount = 0;
            let notMappedCount = 0;
            let rowsCount = 0;
            let matchGreenCount = 0;
            let matchRedCount = 0;
            let matchRedMinusCount = 0;
            let matchRedPlusCount = 0;
            let matchNoneCount = 0;

            // Filtered counts: Rows / 0 Sold / >0 Sold (exclude parent rows from sold badges)
            filteredData.forEach(row => {
                if (isTemu2ParentRow(row)) {
                    rowsCount++;
                    return;
                }
                rowsCount++;
                const temuL30 = parseInt(row.temu_l30, 10) || 0;
                const inventory = parseFloat(row.inventory) || 0;
                if (inventory > 0 && temuL30 === 0) zeroSoldCount++;
                if (inventory > 0 && temuL30 > 0) moreSoldCount++;
            });

            // Parent-only T Clicks badge (goods_id totals on parent rows — never sum SKUs)
            allData.forEach(row => {
                if (!isTemu2ParentRow(row)) return;
                totalParentCount++;
                const parentT = parseInt(row.t_clicks, 10);
                totalParentTClicks += Number.isFinite(parentT)
                    ? parentT
                    : ((parseInt(row.product_clicks, 10) || 0) + (parseInt(row.ad_clicks, 10) || 0));
            });

            // Financials + M L / M M from full dataset (ebay pattern for missing) — SKUs only
            // Same calc as /temu-decrease: Sales/GPFT on Full Price; GROI on R Price
            const viewsByGoodsId = {};
            const tClicksByGoodsId = {};
            allData.forEach(row => {
                if (isTemu2ParentRow(row)) return;
                const temuL30 = parseInt(row.temu_l30, 10) || 0;
                const price = parseFloat(row.base_price) || 0;
                const temuPrice = parseFloat(row.temu_price) || 0;
                const lpPerUnit = parseFloat(row.lp) || 0;
                const temuShip = parseFloat(row.temu_ship) || 0;
                const inventory = parseFloat(row.inventory) || 0;

                totalQuantity += temuL30;
                totalPriceWeighted += price * temuL30;
                totalQty += temuL30;

                const hasSales = temuL30 > 0 && price > 0;
                if (hasSales) {
                    const fbPrice = temu2RPriceFromRow(row); // Temu R Price
                    const fullPrice = temu2FullPriceFromBase(price);
                    const margin = temuSpriceMargin(row);
                    totalRevenue += fullPrice * temuL30;
                    totalProfit += (fbPrice * margin - lpPerUnit - temuShip) * temuL30;
                    totalRevenueFull += fullPrice * temuL30;
                    totalProfitFull += (fullPrice * margin - lpPerUnit - temuShip) * temuL30;
                    totalLp += lpPerUnit * temuL30;
                }

                totalSpend += parseFloat(row.spend) || 0;
                totalSpendL30 += parseFloat(row.spend_l30 || 0);
                // Views / T Clicks are goods_id-level — count each Goods ID once
                const gid = String(row.goods_id || '').trim();
                const rowViews = parseInt(row.o_clicks, 10) || parseInt(row.product_clicks, 10) || 0;
                const rowTClicks = parseInt(row.t_clicks, 10);
                const tVal = Number.isFinite(rowTClicks)
                    ? rowTClicks
                    : (rowViews + (parseInt(row.ad_clicks, 10) || 0));
                if (gid !== '') {
                    viewsByGoodsId[gid] = rowViews;
                    tClicksByGoodsId[gid] = tVal;
                } else {
                    totalViews += rowViews;
                    totalTClicks += tVal;
                }
                totalTemuL30 += temuL30;

                const missing = row.missing;
                const temuStock = parseFloat(row.temu_stock) || 0;
                const nrReq = String(row.nr_req || 'REQ').toUpperCase();

                if (missing === 'M' && inventory > 0 && nrReq !== 'NR' && nrReq !== 'NRL') {
                    missingCount++;
                }
                if (inventory > 0 && nrReq === 'REQ' && missing !== 'M' && temuPrice > 0 && temuStock > 0) {
                    if (!temuInvWithinMapTolerance(inventory, temuStock)) {
                        notMappedCount++;
                    }
                }
                const matchStatus = typeof temu2MatchStatus === 'function' ? temu2MatchStatus(row) : null;
                if (matchStatus === 'green') matchGreenCount++;
                else if (matchStatus === 'red-') { matchRedMinusCount++; matchRedCount++; }
                else if (matchStatus === 'red+') { matchRedPlusCount++; matchRedCount++; }
                else if (matchStatus === 'red') matchRedCount++;
                else if (matchStatus === 'none') matchNoneCount++;
            });
            Object.keys(viewsByGoodsId).forEach(function(gid) {
                totalViews += parseInt(viewsByGoodsId[gid], 10) || 0;
            });
            Object.keys(tClicksByGoodsId).forEach(function(gid) {
                totalTClicks += parseInt(tClicksByGoodsId[gid], 10) || 0;
            });

            $('.match-filter-green-label').text('Green (' + matchGreenCount.toLocaleString() + ')');
            $('.match-filter-red-label').text('Red (' + matchRedCount.toLocaleString() + ')');
            $('.match-filter-red-minus-label').text('Diff − (' + matchRedMinusCount.toLocaleString() + ')');
            $('.match-filter-red-plus-label').text('Diff + (' + matchRedPlusCount.toLocaleString() + ')');
            $('.match-filter-none-label').text('No LMP (' + matchNoneCount.toLocaleString() + ')');

            const avgPrice = totalQty > 0 ? totalPriceWeighted / totalQty : 0;
            // Client-computed like /temu-decrease (do not override GPFT/GROI from sales_summary)
            const avgGprft = totalRevenueFull > 0 ? (totalProfitFull / totalRevenueFull) * 100 : 0;
            const avgGroi = totalLp > 0 ? (totalProfit / totalLp) * 100 : 0;
            let salesAmt = totalRevenueFull > 0 ? totalRevenueFull : totalRevenue;
            let qtyAmt = totalQuantity;
            let profitAmt = totalProfit;
            let cogsAmt = totalLp;
            if (salesSummaryFromBackend) {
                const backendOrders = Number(salesSummaryFromBackend.total_orders || 0);
                const backendQuantity = Number(salesSummaryFromBackend.total_quantity || 0);
                const backendRevenue = Number(salesSummaryFromBackend.total_revenue || 0);
                if (backendOrders > 0 || backendQuantity > 0 || backendRevenue > 0) {
                    salesAmt = backendRevenue;
                    qtyAmt = backendQuantity;
                }
                if (salesSummaryFromBackend.total_pft != null && salesSummaryFromBackend.total_pft !== undefined) {
                    profitAmt = Number(salesSummaryFromBackend.total_pft) || 0;
                }
                if (salesSummaryFromBackend.total_cogs != null && salesSummaryFromBackend.total_cogs !== undefined) {
                    cogsAmt = Number(salesSummaryFromBackend.total_cogs) || 0;
                }
            }
            // Prefer file total from temu2_campaign_reports — summing per-SKU spend double-counts
            // when multiple SKUs share one goods_id.
            const backendSpend = adTotalsFromBackend ? parseFloat(adTotalsFromBackend.spend) : NaN;
            const spendSum = Number.isFinite(backendSpend)
                ? backendSpend
                : (totalSpendL30 > 0 ? totalSpendL30 : totalSpend);
            const spendForAdsPercent = Number.isFinite(backendSpend) && backendSpend > 0
                ? backendSpend
                : (totalSpendL30 > 0 ? totalSpendL30 : totalSpend);
            const adsRevenueBase = salesAmt > 0 ? salesAmt : totalRevenueFull;
            const computedAggregateAdsPercent = adsRevenueBase > 0 ? (spendForAdsPercent / adsRevenueBase) * 100 : 0;
            const hasValidBackendAdsPercent = Number.isFinite(Number(badgeAvgAds)) && Number(badgeAvgAds) > 0;
            if (!hasValidBackendAdsPercent) {
                badgeAvgAds = computedAggregateAdsPercent;
            }
            const adsPercentForNpft = (badgeAvgAds != null && badgeAvgAds !== undefined)
                ? badgeAvgAds
                : computedAggregateAdsPercent;
            const avgNpft = avgGprft - adsPercentForNpft;
            const avgNroi = avgGroi - adsPercentForNpft;
            const cvrTotalSold = totalTemuL30;
            const qtyPerViews = totalTClicks > 0 ? (cvrTotalSold / totalTClicks) * 100 : 0;

            $('#rows-count-badge').text('Rows: ' + rowsCount.toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
            $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());
            $('#total-sales-amt-badge').text('Sales: $' + Math.round(salesAmt).toLocaleString());
            updateTemu2RecoveryBadge(salesAmt);
            $('#total-spend-badge').text('Spend: $' + Math.round(spendSum).toLocaleString());
            $('#qty-sold-badge').text('Qty: ' + Number(qtyAmt).toLocaleString());
            $('#avg-gpft-badge').text('GPFT: ' + Math.round(avgGprft) + '%');
            $('#groi-percent-badge').text('GROI: ' + Math.round(avgGroi) + '%');
            $('#ads-percent-badge').text('Ads: ' + (Number(adsPercentForNpft) || 0).toFixed(1) + '%');
            $('#avg-npft-badge').text('NPFT: ' + Math.round(avgNpft) + '%');
            $('#avg-nroi-badge').text('NROI: ' + Math.round(avgNroi) + '%');
            $('#avg-price-badge').text('Prc: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('CVR: ' + qtyPerViews.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#total-t-clicks-badge').text('T Clicks: ' + totalParentTClicks.toLocaleString());
            // T Clicks 7 = ((T Clicks / 30) × 7) / total parents
            const tClicks7 = totalParentCount > 0
                ? ((totalParentTClicks / 30) * 7) / totalParentCount
                : 0;
            $('#total-t-clicks-7-badge').text('T Clicks 7: ' + tClicks7.toLocaleString(undefined, {
                maximumFractionDigits: 1,
                minimumFractionDigits: 0
            }));
            $('#missing-l-count-badge').text('M L: ' + missingCount.toLocaleString());
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#temu2-price-gt-lmp-badge', table.getData(), 'temu2', 'temu_price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#temu2-price-lt80-lmp-badge', table.getData(), 'temu2', 'temu_price');
                }
            }
            let blueTriangleCount = 0;
            (table ? table.getData() : []).forEach(function(row) {
                if (temu2HasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#temu2-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncTemu2TriangleBadgeState === 'function') syncTemu2TriangleBadgeState();
            $('#missing-m-count-badge').text('M M: ' + notMappedCount.toLocaleString());

            // Legacy hidden IDs (if present) — avoid JS errors
            $('#total-products-badge').text('SKU: ' + totalProducts.toLocaleString());
            $('#total-profit-badge').text('PFT: $' + Math.round(profitAmt).toLocaleString());
            $('#total-lp-badge').text('Total LP: $' + Math.round(cogsAmt).toLocaleString());

            fitSummaryBadges();
        }

        function updateTemuAdsCounts() {
            return;
        }

        // eBay-style color functions
        const getPftColor = (value) => (window.MetricPctColors ? MetricPctColors.legacyPftClass(value) : 'red');

        const getRoiColor = (value) => (window.MetricPctColors ? MetricPctColors.legacyRoiClass(value) : 'red');

        let totalCampaignCountFromBackend = 0;
        let salesSummaryFromBackend = null;
        let adTotalsFromBackend = null; // authoritative Spend from temu2_campaign_reports
        let badgeAvgAds = null; // Ads % from badge — shown in ADS% column for all rows
        let currentCampaignPeriod = 'L30';

        /** CVR display: ≤3.5 keep 1 decimal; >3.5 round to whole number (e.g. 4%). */
        function formatCvrPct(val) {
            const n = parseFloat(val) || 0;
            return (n > 3.5 ? String(Math.round(n)) : n.toFixed(1)) + '%';
        }

        // Play/Pause parent navigation (like pricing-master-cvr)
        let fullDataset = [];
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        let suppressDataLoadedHandler = false;

        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu2-decrease-data",
            ajaxSorting: false,
            layout: "fitData",
            layoutColumnsOnNewData: true,
            columnDefaults: {
                hozAlign: "center",
                headerHozAlign: "center",
                resizable: true,
                minWidth: 64,
            },
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500, 1000, true],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_percent", dir: "asc"}
            ],
            rowFormatter: function(row) {
                const data = row.getData();
                const el = row.getElement();
                if (isTemu2ParentRow(data)) {
                    el.classList.add('temu2-parent-row');
                    el.style.setProperty('background-color', '#fffef2', 'important');
                } else {
                    el.classList.remove('temu2-parent-row');
                    el.style.removeProperty('background-color');
                }
            },
            ajaxResponse: function(url, params, response) {
                if (response && Array.isArray(response.data)) {
                    const periodFromResponse = (response.period || currentCampaignPeriod || 'L30').toUpperCase();
                    currentCampaignPeriod = periodFromResponse;
                    totalCampaignCountFromBackend = parseInt(response.total_campaign_count || 0, 10);
                    salesSummaryFromBackend = response.sales_summary || null;
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
                    frozen: true,
                    width: 54,
                    minWidth: 48,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 40px; height: 40px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false
                },
                {
                    title: "Parent",
                    field: "parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    frozen: true,
                    width: 130,
                    minWidth: 110,
                    hozAlign: "left",
                    headerHozAlign: "left",
                    tooltip: true,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        let value = cell.getValue() || '';
                        if (!value && isTemu2ParentRow(row)) {
                            value = String(row.sku || '').replace(/^PARENT\s+/i, '').trim();
                        }
                        if (String(value).toUpperCase().startsWith('PARENT ')) {
                            value = String(value).replace(/^PARENT\s+/i, '').trim();
                        }
                        if (!value) return '';
                        if (isTemu2ParentRow(row)) {
                            return `<span style="font-weight:700;color:#0d6efd;">${value}</span>`;
                        }
                        return value;
                    }
                },
                ParentExpand.columnDef(),
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    width: 180,
                    minWidth: 150,
                    hozAlign: "left",
                    headerHozAlign: "left",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        const row = cell.getRow().getData();
                        const isParent = isTemu2ParentRow(row);
                        const label = isParent
                            ? `<span style="font-weight:700;color:#0d6efd;">${sku}</span>`
                            : sku;
                        if (isParent) {
                            return label;
                        }
                        return `${label} <button type="button" class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price trend" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
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
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> S</a>`;
                        }
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> B</a>`;
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openTemu2EditLinksModal(cell.getRow());
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    width: 60,
                    minWidth: 55,
                    hozAlign: "center",
                    sorter: "number"
                },
                {
                    title: "Temu Stock",
                    field: "temu_stock",
                    width: 70,
                    minWidth: 65,
                    hozAlign: "center",
                    sorter: "number",
                    visible: true
                },
                {
                    title: "OVL30",
                    field: "ovl30",
                    width: 65,
                    minWidth: 60,
                    hozAlign: "center",
                    sorter: "number"
                },
                    {
                    title: "Dil%",
                    field: "dil_percent",
                    width: 60,
                    minWidth: 55,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const dil = parseFloat(cell.getValue()) || 0;
                        
                        let color = '';
                        if (dil < 16.66) color = '#a00211'; // red (includes 0)
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                        else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                        else color = '#e83e8c'; // pink (50 and above)
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "CVR 45",
                    field: "cvr_45",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60,
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
                    width: 100,
                    minWidth: 95,
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
                        const sku = rowData.sku || '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="cvr" title="View CVR% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>` : '';
                        return `<span style="color: ${color}; font-weight: 600;">${formatCvrPct(val)}</span>${arrowHtml} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    width: 80,
                    minWidth: 75,
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
                            return '<span style="color: #dc3545; font-weight: bold;" title="Not found in temu2_metrics (API)">M</span>';
                        }
                        return '';
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
                        
                        // IMPORTANT: Only show MAP if SKU exists in Temu (not missing)
                        // Same logic as eBay - check if item exists before showing MAP
                        if (missing === 'M' || !rowData['goods_id'] || rowData['goods_id'] === '') {
                            return ''; // Don't show MAP for missing items
                        }
                        
                        const temuStock = parseFloat(rowData['temu_stock']) || 0;
                        const inv = parseFloat(rowData['inventory']) || 0;

                        if (inv > 0) {
                            // Tolerance: |INV − stock| <= 3 units OR <= 3% of INV (matches amazon-tabulator-view)
                            if (temuInvWithinMapTolerance(inv, temuStock)) {
                                return '<span style="color: #28a745; font-weight: bold;" title="Within tolerance (3 units or 3%)">MP</span>';
                            }
                            const diff = inv - temuStock;
                            const sign = diff > 0 ? '+' : '';
                            return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                        }

                        return '';
                    }
                },
                {
                    title: "NRL/REQ",
                    field: "nr_req",
                    hozAlign: "center",
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
                    field: "o_clicks",
                    width: 85,
                    minWidth: 80,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SUM(product_clicks) from uploaded temu2_view_data files, matched by Goods ID from temu2_pricing. No Ads API fallback.",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        // Sheet-only Views: o_clicks = SUM(product_clicks) for temu2_pricing.goods_id
                        const value = parseInt(cell.getValue(), 10) || parseInt(row.product_clicks, 10) || 0;
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="views" title="View Views chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0000FF;"></span></button>` : '';
                        return `${value.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "T Clicks",
                    field: "t_clicks",
                    width: 90,
                    minWidth: 85,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row.sku || '';
                        const oClicks = parseInt(row.o_clicks, 10) || parseInt(row.product_clicks, 10) || 0;
                        const adClicks = parseInt(row.ad_clicks, 10) || 0;
                        const value = parseInt(cell.getValue(), 10);
                        const total = Number.isFinite(value) ? value : (oClicks + adClicks);
                        const chartBtn = sku
                            ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="t_clicks" title="Open T Clicks chart" style="border:none;background:none;cursor:pointer;padding:0 2px;line-height:1;vertical-align:middle;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6610f2;"></span></button>`
                            : '';
                        return `${total.toLocaleString()} ${chartBtn}`.trim();
                    }
                },
                {
                    title: "T Click Growth",
                    field: "t_clicks_growth",
                    width: 90,
                    minWidth: 85,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "T Click Growth % = ((T7÷7) ÷ (T30÷30) − 1) × 100. 0% = same daily pace; needs L7 + L30 clicks.",
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        if (raw === null || raw === undefined || raw === '') {
                            return '<span style="color:#999;">-</span>';
                        }
                        const n = parseFloat(raw);
                        if (!Number.isFinite(n)) return '<span style="color:#999;">-</span>';
                        const rounded = Math.round(n);
                        let color = '#6c757d';
                        let icon = 'fa-minus';
                        if (rounded > 0) { color = '#28a745'; icon = 'fa-arrow-up'; }
                        else if (rounded < 0) { color = '#dc3545'; icon = 'fa-arrow-down'; }
                        const sign = rounded > 0 ? '+' : '';
                        return `<span style="color:${color};font-weight:600;">${sign}${rounded}% <i class="fas ${icon}" style="font-size:11px;"></i></span>`;
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
                    width: 88,
                    minWidth: 88,
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
                        const temuDisplay = basePrice > 0 && typeof temu2FullPriceFromBase === 'function'
                            ? temu2FullPriceFromBase(basePrice)
                            : (parseFloat(rowData.temu_price_display || rowData.temu_price || 0) || 0);
                        const channelPrice = amzPrice > 0 ? amzPrice : temuDisplay;
                        const dot = temu2StdPrcChangeDotHtml(std, channelPrice);

                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price_display",
                    hozAlign: "center",
                    minWidth: 90,
                    sorter: "number",
                    headerTooltip: "Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const basePrice = parseFloat(rowData['base_price']) || 0;
                        if (basePrice === 0) return '$0.00';
                        const displayPrice = +temu2FullPriceFromBase(basePrice).toFixed(2);
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(rowData.temu_price || displayPrice, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(rowData.temu_price || displayPrice, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
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
                        const basePrice = parseFloat(cell.getRow().getData()['base_price']) || 0;
                        if (basePrice === 0) {
                            return '$0.00';
                        }
                        const temuRPrice = basePrice <= 26.99 ? basePrice + 2.99 : basePrice;
                        return `$${temuRPrice.toFixed(2)}`;
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
                        const sProfit = temu2SProfit(rowData);
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
                    // Reference column: Temu 1 listing price for the same SKU, served by the
                    // /temu2-decrease-data endpoint as `temu1_price` (server pre-applies the
                    // +$2.99 adjustment, same rule as the Temu Price column above). Read-only.
                    // SKUs with no Temu 1 listing show a dash so they're easy to spot.
                    title: "Temu 1 Price",
                    field: "temu1_price",
                    hozAlign: "center",
                    minWidth: 86,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (!value || isNaN(value) || value <= 0) {
                            return '<span style="color:#999;">-</span>';
                        }
                        return '$' + value.toFixed(2);
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
                        return `<span style="color: ${color}; font-weight: 600;">$${Math.round(value).toLocaleString()}</span>`;
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
                        const sku = rowData.sku || '';
                        const pft = typeof temu2PftDollars === 'function' ? temu2PftDollars(rowData) : null;
                        const lp = parseFloat(rowData.lp) || 0;
                        const value = (pft != null && lp > 0) ? (pft / lp) * 100 : (parseFloat(cell.getValue()) || 0);
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="roi_percent" title="View GROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #6f42c1;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    minWidth: 80,
                    headerTooltip: "GPRFT% = Gpft / Temu Price. Gpft = (Temu R Price × 0.95) − Temu Ship − LP",
                    sorter: function(a, b, aRow, bRow) {
                        const calc = (row) => {
                            const gpft = typeof temu2PftDollars === 'function' ? temu2PftDollars(row) : null;
                            const fullPrice = temu2FullPriceFromRow(row);
                            if (gpft == null || !(fullPrice > 0)) return 0;
                            return (gpft / fullPrice) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const gpft = typeof temu2PftDollars === 'function' ? temu2PftDollars(rowData) : null;
                        const fullPrice = temu2FullPriceFromRow(rowData);
                        const value = (gpft != null && fullPrice > 0) ? (gpft / fullPrice) * 100 : 0;
                        const colorClass = getPftColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="profit_percent" title="View GPRFT% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ff1493;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    visible: false,
                    headerTooltip: "ADS% = 2.2% on every row",
                    sorter: "number",
                    formatter: function(cell) {
                        const displayVal = typeof temuAdsPercentForNet === 'function' ? temuAdsPercentForNet() : 2.2;
                        const rowData = cell.getRow().getData();
                        const sku = (rowData && rowData.sku) ? rowData.sku : '';
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="ads_percent" title="View ADS% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ffc107;"></span></button>` : '';
                        return `<span style="color: #ff1493; font-weight: 600;">${displayVal.toFixed(1)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    headerTooltip: "NPFT% = NPFT / Temu Price. NPFT = Gpft − (Temu Price × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const npft = typeof temu2NpftDollars === 'function' ? temu2NpftDollars(rowData) : null;
                        const temuPrice = temu2FullPriceFromRow(rowData);
                        const value = (npft != null && temuPrice > 0) ? (npft / temuPrice) * 100 : 0;
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
                    visible: false,
                    headerTooltip: "NROI% = NPFT / LP. NPFT = Gpft − (Temu Price × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const npft = typeof temu2NpftDollars === 'function' ? temu2NpftDollars(rowData) : null;
                        const lp = parseFloat(rowData.lp) || 0;
                        const value = (npft != null && lp > 0) ? (npft / lp) * 100 : 0;
                        const colorClass = getRoiColor(value);
                        const dotBtn = sku ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="nroi_percent" title="View NROI% chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #17a2b8;"></span></button>` : '';
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span> ${dotBtn}`.trim();
                    }
                },
                {
                    title: "LMP",
                    field: "lmp",
                    hozAlign: "center",
                    minWidth: 88,
                    sorter: "number",
                    headerSort: true,
                    headerTooltip: "Temu Recovery (≤$27: Price×0.85+2.99; >$27: Price×0.85) — same as /pricing-master-cvr; raw LMP stays in the modal",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (window.ParentExpand && typeof ParentExpand.parentAvgLmpHtml === 'function') {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(row, {
                                dataset: typeof fullDataset !== 'undefined' ? fullDataset : (typeof allTableData !== 'undefined' ? allTableData : undefined),
                                field: 'lmp',
                                getValue: function(r) {
                                    const rawLowest = getTemu2RawLmp(r);
                                    const recovery = temuLmpRecovery(rawLowest);
                                    if (recovery != null && isFinite(recovery) && recovery > 0) return recovery;
                                    const v = parseFloat(r.lmp);
                                    return isFinite(v) && v > 0 ? v : null;
                                }
                            });
                            if (avgHtml !== null) return avgHtml;
                        }
                        const rawLowest = getTemu2RawLmp(row);
                        const recovery = temuLmpRecovery(rawLowest);
                        const displayVal = recovery != null ? recovery : (parseFloat(cell.getValue()) || null);
                        const display = displayVal != null
                            ? (displayVal % 1 === 0 ? displayVal.toLocaleString() : displayVal.toFixed(2))
                            : '-';
                        const count = (row.lmp_entries || []).length;
                        const rawTip = rawLowest != null ? (' from raw $' + Number(rawLowest).toFixed(2)) : '';
                        const title = count > 0
                            ? ('Temu Recovery $' + (displayVal != null ? Number(displayVal).toFixed(2) : '-') + rawTip + ' (' + count + ' entries) - click to edit')
                            : 'Click eye to add LMP';
                        return '<span class="lmp-display" title="' + title.replace(/"/g, '&quot;') + '">' + (display !== '-' ? display : '<span style="color: #999;">-</span>') + '</span> <button type="button" class="btn btn-sm btn-link p-0 lmp-eye-btn" data-sku="' + (row.sku || '').replace(/"/g, '&quot;') + '" title="' + title.replace(/"/g, '&quot;') + '"><i class="fas fa-info-circle text-info"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.lmp-eye-btn')) {
                            e.stopPropagation();
                            const row = cell.getRow().getData();
                            openLmpModal(row.sku, row.lmp_entries || []);
                        }
                    }
                },
                {
                    title: "Diff",
                    field: "lmp_diff_pct",
                    hozAlign: "center",
                    width: 84,
                    minWidth: 84,
                    headerTooltip: "S PRC vs LMP: (LMP − S PRC) / LMP. Green = S PRC below LMP, Red = S PRC above LMP.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemu2ParentRow === 'function' && isTemu2ParentRow(rowData)) return '';
                        const diff = typeof temu2LmpDiffPct === 'function' ? temu2LmpDiffPct(rowData) : null;
                        if (diff == null) return '<span style="color:#999;">—</span>';
                        const color = diff < 0 ? '#dc3545' : '#28a745';
                        const sign = diff > 0 ? '+' : '';
                        return '<span style="color:' + color + ';font-weight:600;">' + sign + diff.toFixed(1) + '%</span>';
                    }
                },
                {
                    title: "Match",
                    field: "lmp_match",
                    hozAlign: "center",
                    width: 58,
                    headerSort: false,
                    headerTooltip: "Match — Green = S PRC is LMP − $0.01. Red = not matched.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemu2ParentRow === 'function' && isTemu2ParentRow(rowData)) return '';
                        const status = typeof temu2MatchStatus === 'function' ? temu2MatchStatus(rowData) : null;
                        if (status === 'none' || !status) return '<span style="color:#adb5bd;" title="No LMP">—</span>';
                        const color = status === 'green' ? '#28a745' : '#dc3545';
                        return '<span style="color:' + color + ';font-weight:800;font-size:14px;">M</span>';
                    }
                },
                     {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    visible: false,
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
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    minWidth: 88,
                    editor: "input",
                    headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). S PRC ≥ LMP is capped at LMP and keeps a red triangle after push. Blue triangle = S PRC ≠ Price.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (typeof isTemu2ParentRow === 'function' && isTemu2ParentRow(rowData)) return '';
                        let value = parseFloat(cell.getValue() || 0);
                        if (typeof chPromoSpriceFromStdTPromo === 'function') {
                            const calc = chPromoSpriceFromStdTPromo(rowData);
                            if (calc > 0) value = calc;
                        }
                        const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(rowData, value) : null;
                        if (cap && cap.shown > 0) value = cap.shown;
                        const live = parseFloat(rowData.temu_price) || 0;
                        const lmp = cap ? cap.lmp : (parseFloat(rowData.lmp_price || rowData.lmp || rowData.LMP) || 0);
                        if (!(value > 0)) return '';
                        const formatted = '$' + value.toFixed(2);
                        const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                        const priceHtml = overLmp
                            ? `<span style="color:#dc3545;font-weight:600;">${formatted}</span>`
                            : formatted;
                        const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${redTri}${blueTri}</span>`;
                    }
                },
                {
                    title: "S Recovery",
                    field: "s_recovery",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "S Recovery = Sprice × 0.88",
                    formatter: function(cell) {
                        const sprice = parseFloat(cell.getRow().getData()['sprice']) || 0;
                        if (sprice <= 0) return '';
                        return temu2FormatMoney(temu2SRecovery(sprice));
                    }
                },
                {
                    title: "Queue",
                    field: "_push",
                    width: 55,
                    hozAlign: "center",
                    headerSort: false,
                    headerTooltip: "Push base = inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent) return '';
                        const sprice = window.SpriceLmpCap
                            ? SpriceLmpCap.prepare(rowData, parseFloat(rowData.sprice) || 0)
                            : (parseFloat(rowData.sprice) || 0);
                        const pushBase = temuPushBaseFromSprice(sprice);
                        const pushStatus = rowData.push_status || null;
                        if (sprice <= 0 || pushBase == null || pushBase <= 0) return '';

                        const sku = rowData.sku || '';
                        const goodsId = rowData.goods_id || '';
                        const skuId = rowData.sku_id || '';

                        if (pushStatus === 'pushing') {
                            return '<i class="fas fa-spinner fa-spin" style="color: #ffc107;" title="Pushing to Temu2..."></i>';
                        }
                        if (pushStatus === 'pushed') {
                            return '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Temu2"></i>';
                        }
                        if (pushStatus === 'error') {
                            return `<button type="button" class="temu2-push-single-btn" data-sku="${sku}" data-price="${pushBase}" data-goods-id="${goodsId}" data-sku-id="${skuId}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed — click to retry"><i class="fa-solid fa-x"></i></button>`;
                        }
                        return `<button type="button" class="temu2-push-single-btn" data-sku="${sku}" data-price="${pushBase}" data-goods-id="${goodsId}" data-sku-id="${skuId}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push base $${pushBase.toFixed(2)} to Temu2"><i class="fas fa-upload"></i></button>`;
                    }
                },
           
                {
                    title: "S Temu B Prc",
                    field: "stemu_price",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Push base = inverse of Temu Price: undo +$2.99 (if applied) then ÷ 1.1364. Matches Base Price when SPRICE = Temu Price.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const pushBase = temuPushBaseFromSprice(rowData['sprice']);
                        if (pushBase == null) return '';
                        return temu2FormatMoney(pushBase);
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
                        const lp = parseFloat(rowData['lp']) || 0;
                        const spft = typeof temu2SpftDollars === 'function' ? temu2SpftDollars(rowData) : null;
                        if (spft == null || !(lp > 0)) return '';
                        const sgroi = (spft / lp) * 100;
                        const colorClass = getRoiColor(sgroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sgroi)}%</span>`;
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
                        const lp = parseFloat(rowData['lp']) || 0;
                        const snpft = typeof temu2SnpftDollars === 'function' ? temu2SnpftDollars(rowData) : null;
                        if (snpft == null || !(lp > 0)) return '';
                        const snroi = (snpft / lp) * 100;
                        const colorClass = getRoiColor(snroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(snroi)}%</span>`;
                    }
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SGPRFT% = SPFT / S PRC. SPFT = (S R Price × 0.95) − Temu Ship − LP",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const spft = typeof temu2SpftDollars === 'function' ? temu2SpftDollars(rowData) : null;
                        if (!(sprice > 0) || spft == null) return '';
                        const sgprft = (spft / sprice) * 100;
                        const colorClass = getPftColor(sgprft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    }
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SPFT% = SNPFT / S PRC. SNPFT = SPFT − (S PRC × Ads%)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const snpft = typeof temu2SnpftDollars === 'function' ? temu2SnpftDollars(rowData) : null;
                        if (!(sprice > 0) || snpft == null) return '';
                        const spft = (snpft / sprice) * 100;
                        const colorClass = getPftColor(spft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    width: 75,
                    minWidth: 70,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return String(Math.round(value));
                    },
                    visible: true
                },
                {
                    title: "ACOS%",
                    field: "acos_ad",
                    width: 65,
                    minWidth: 60,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return `${Math.round(value)}%`;
                    },
                    visible: false
                },
                {
                    title: "Ad Clicks",
                    field: "ad_clicks",
                    width: 75,
                    minWidth: 70,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    },
                    visible: false
                },
                {
                    title: "Impressions",
                    field: "impressions",
                    width: 90,
                    minWidth: 85,
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue(), 10) || 0;
                        return v.toLocaleString();
                    }
                },
                {
                    title: "OUT ROAS",
                    field: "out_roas_l30",
                    width: 80,
                    minWidth: 75,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        // Use net_roas as OUT ROAS if out_roas_l30 is not available
                        const value = parseFloat(cell.getValue() || rowData.net_roas || 0);
                        return value.toFixed(2);
                    },
                    visible: false
                },
                {
                    title: "IN ROAS",
                    field: "in_roas_l30",
                    width: 75,
                    minWidth: 70,
                    hozAlign: "center",
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    formatter: function(cell) {
                        const cellValue = cell.getValue();
                        const value = (cellValue !== null && cellValue !== undefined) ? parseFloat(cellValue) : 0;
                        return value.toFixed(2);
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
                    visible: false
                },
                {
                    title: "Target",
                    field: "target",
                    width: 75,
                    minWidth: 70,
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
                    title: "Goods ID",
                    field: "goods_id",
                    hozAlign: "center",
                    sorter: "string",
                    width: 150,
                    minWidth: 140,
                    accessorDownload: function(value, data) {
                        if (data && data.goods_id_mismatch) {
                            const ids = Array.isArray(data.child_goods_ids) ? data.child_goods_ids.join(' | ') : '';
                            return ids ? ('\t' + ids) : 'MISMATCH';
                        }
                        const g = (data && data.goods_id != null && data.goods_id !== '') ? String(data.goods_id) : '';
                        // Leading tab forces Excel to treat as text (avoids scientific notation)
                        return g ? ('\t' + g) : '';
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (row.goods_id_mismatch) {
                            const ids = Array.isArray(row.child_goods_ids) ? row.child_goods_ids.join(', ') : '';
                            const tip = ids
                                ? ('Child Goods IDs do not match: ' + ids)
                                : 'Child Goods IDs do not match';
                            return `<i class="fas fa-exclamation-triangle" style="color:#dc3545;cursor:help;" title="${tip.replace(/"/g, '&quot;')}"></i>`;
                        }
                        const goodsId = (cell.getValue() || '').toString().trim();
                        if (!goodsId) return '';
                        return `${goodsId} <button type="button" class="btn btn-sm p-0 ms-1 copy-goods-id" data-goods-id="${goodsId}" title="Copy Goods ID" style="border:none;background:none;color:#6c757d;"><i class="fa fa-copy"></i></button>`;
                    }
                },
            ]
        });

        table.on('pageLoaded', function() {
            updateSelectAllCheckbox();
            temu2AutofitColumns();
        });

        const TEMU2_AUTOFIT_SKIP = {
            image_path: 1, links_column: 1, _select: 1, _push: 1, nr_req: 1, lmp_match: 1
        };
        const TEMU2_AUTOFIT_FLOOR = {
            s_profit: 88, temu_price: 86, temu1_price: 86, base_price: 92,
            roi_percent: 80, profit_percent: 80, lmp: 88, lmp_diff_pct: 84,
            STANDARD_PRICE: 88, sprice: 88, temu_price_display: 90
        };
        let temu2AutofitTimer = null;
        function temu2AutofitColumns() {
            clearTimeout(temu2AutofitTimer);
            temu2AutofitTimer = setTimeout(temu2AutofitColumnsNow, 80);
        }
        function temu2AutofitColumnsNow() {
            if (!table || typeof table.getColumns !== 'function') return;
            table.getColumns().forEach(function(col) {
                try {
                    if (!col.isVisible()) return;
                    const field = col.getField() || '';
                    if (TEMU2_AUTOFIT_SKIP[field]) return;
                    if (typeof col.setWidth === 'function') col.setWidth(true);
                    const measured = col.getWidth() || 0;
                    const def = col.getDefinition() || {};
                    const floor = TEMU2_AUTOFIT_FLOOR[field] || def.minWidth || 68;
                    const next = Math.max(measured + 10, floor);
                    if (next > measured) col.setWidth(Math.min(next, 280));
                } catch (e) { /* ignore */ }
            });
        }

        let temu2ResizeTimer = null;
        window.addEventListener('resize', function() {
            clearTimeout(temu2ResizeTimer);
            temu2ResizeTimer = setTimeout(function() {
                temu2AutofitColumns();
            }, 150);
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
                if (!field || !Object.prototype.hasOwnProperty.call(state, field)) return;
                if (state[field]) {
                    column.show();
                } else {
                    column.hide();
                }
            });
        }

        $('#sku-search, #parent-search').on('keyup', function() {
            applyFilters();
        });

        /** True for ProductMaster PARENT rows (used by All Rows / Parents / SKUs filter). */
        function isTemu2ParentRow(data) {
            if (!data) return false;
            if (data.is_parent === true || data.is_parent === 1 || data.is_parent === '1') return true;
            const sku = String(data.sku || '').trim().toUpperCase();
            return sku.indexOf('PARENT ') === 0 || sku === 'PARENT';
        }

        // Apply filters — same structure as /ebay-tabulator-view, Temu field mapping
        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            if (isPlayNavigationActive) {
                if (typeof showCurrentParentPlayView === 'function') showCurrentParentPlayView();
                return;
            }

            const parentFilter = $('#parent-filter').val() || 'parents';
            const inventoryFilter = $('#inventory-filter').val();
            const tl30Filter = $('#tl30-filter').val();
            const growthSignFilter = $('#growth-sign-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const groiFilter = $('#roi-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const cvrTrendFilter = $('#cvr-trend-filter').val();
            const spriceFilter = $('#sprice-filter').val();
            const spriceLmpFilter = $('#sprice-lmp-filter').val();
            const prcLmpFilter = $('#prc-lmp-filter').val();
            const lmpFilter = $('#lmp-filter').val();
            const dilFilter = $('#dil-filter').val() || 'all';
            const skuSearch = ($('#sku-search').val() || '').trim();
            const parentSearch = ($('#parent-search').val() || '').trim();
            // When showing All Rows / Parents, keep parent summary rows visible even if a data filter would drop them
            const parentRowsBypassDataFilters = (parentFilter === 'all' || parentFilter === 'parents');
            adsReqFilter = 'all';
            adsRunningFilter = 'all';

            table.clearFilter(true);

            // Row type: All Rows / Parents / SKUs (default Parents)
            if (parentFilter === 'parents') {
                table.addFilter(function(data) {
                    return isTemu2ParentRow(data);
                });
            } else if (parentFilter === 'skus') {
                table.addFilter(function(data) {
                    return !isTemu2ParentRow(data);
                });
            }

            if (skuSearch) {
                table.addFilter(function(data) {
                    return String(data.sku || '').toUpperCase().includes(skuSearch.toUpperCase());
                });
            }
            if (parentSearch) {
                table.addFilter(function(data) {
                    return String(data.parent || '').toUpperCase().includes(parentSearch.toUpperCase());
                });
            }

            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'more') return inv > 0;
                    if (inventoryFilter === 'zero') return inv === 0;
                    return true;
                });
            }

            if (tl30Filter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const l30 = parseInt(data.temu_l30, 10) || 0;
                    if (tl30Filter === 'more') return l30 > 0;
                    if (tl30Filter === 'zero') return l30 === 0;
                    return true;
                });
            }

            if (growthSignFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const l30 = parseFloat(data.temu_l30) || 0;
                    const l60 = parseFloat(data.temu_l60) || 0;
                    let growth = 0;
                    if (l60 === 0 && l30 > 0) growth = 100;
                    else if (l60 > 0) growth = ((l30 - l60) / l60) * 100;
                    growth = Math.round(growth);
                    if (growthSignFilter === 'negative') return growth < 0;
                    if (growthSignFilter === 'zero') return growth === 0;
                    if (growthSignFilter === 'positive') return growth > 0;
                    return true;
                });
            }

            if (nrlFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const nr = String(data.nr_req || 'REQ').toUpperCase();
                    const normalized = (nr === 'NR' || nr === 'NRL') ? 'NR' : nr;
                    return normalized === nrlFilter;
                });
            }

            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const price = parseFloat(data.temu_price) || 0;
                    const gpft = price > 0
                        ? ((price * TEMU_MARGIN - (parseFloat(data.lp) || 0) - (parseFloat(data.temu_ship) || 0)) / price) * 100
                        : 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40plus') return gpft >= 40;
                    return true;
                });
            }

            if (groiFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const groi = parseFloat(data.roi_percent) || 0;
                    if (groiFilter === 'lt40') return groi < 40;
                    if (groiFilter === '40-75') return groi >= 40 && groi < 75;
                    if (groiFilter === '75-125') return groi >= 75 && groi < 125;
                    if (groiFilter === 'gt125') return groi >= 125;
                    return true;
                });
            }

            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const cvrRounded = Math.round((parseFloat(data.cvr_percent) || 0) * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            if (cvrTrendFilter !== 'all') {
                const cvrTrendTol = 0.1;
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const cvr30 = parseFloat(data.cvr_30 || data.cvr_percent) || 0;
                    const cvr60 = parseFloat(data.cvr_60) || 0;
                    if (cvrTrendFilter === 'l60_gt_l30') return cvr60 > cvr30 + cvrTrendTol;
                    if (cvrTrendFilter === 'l30_gt_l60') return cvr30 > cvr60 + cvrTrendTol;
                    if (cvrTrendFilter === 'equal') return Math.abs(cvr30 - cvr60) <= cvrTrendTol;
                    return true;
                });
            }

            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const dil = parseFloat(data.dil_percent) || 0;
                    if (dilFilter === 'red') return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            if (spriceFilter === 'blank') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const spriceVal = data.sprice;
                    const sprice = parseFloat(spriceVal);
                    return spriceVal == null || spriceVal === '' || isNaN(sprice) || sprice <= 0;
                });
            }

            if (spriceLmpFilter === 'red') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const sprice = parseFloat(data.sprice) || 0;
                    const lmp = parseFloat(data.lmp) || 0;
                    if (window.SpriceLmpCap) return SpriceLmpCap.hasAlert(data, sprice);
                    return sprice > 0 && lmp > 0 && sprice + 0.0001 >= lmp;
                });
            }

            if (prcLmpFilter === 'red') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    const price = parseFloat(data.temu_price) || 0;
                    const lmp = parseFloat(data.lmp) || 0;
                    return price > 0 && lmp > 0 && price > lmp;
                });
            }

            if (lmpFilter === 'red') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    return (parseFloat(data.lmp) || 0) <= 0;
                });
            }

            const matchFilter = $('#matchFilterDropdown').data('color') || 'all';
            if (matchFilter && matchFilter !== 'all') {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data) && parentRowsBypassDataFilters) return true;
                    return temu2MatchFilterMatches(temu2MatchStatus(data), matchFilter);
                });
            }

            if (soldSpriceBlankFilterActive) {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data)) return false;
                    const temuL30 = parseInt(data.temu_l30, 10) || 0;
                    const inventory = parseInt(data.inventory, 10) || 0;
                    const spriceVal = data.sprice;
                    const spriceIsBlank = !spriceVal || spriceVal === '' || spriceVal === 0 || parseFloat(spriceVal) === 0;
                    return inventory > 0 && temuL30 > 0 && spriceIsBlank;
                });
            }

            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data)) return false;
                    return (parseInt(data.temu_l30, 10) || 0) === 0 && (parseFloat(data.inventory) || 0) > 0;
                });
            } else if (moreSoldFilterActive) {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data)) return false;
                    return (parseInt(data.temu_l30, 10) || 0) > 0 && (parseFloat(data.inventory) || 0) > 0;
                });
            }

            if (missingLFilterActive || missingBadgeFilterActive) {
                table.addFilter(function(data) {
                    if (isTemu2ParentRow(data)) return false;
                    const inv = parseFloat(data.inventory) || 0;
                    const nrReq = String(data.nr_req || 'REQ').toUpperCase();
                    return data.missing === 'M' && inv > 0 && nrReq !== 'NR' && nrReq !== 'NRL';
                });
            }

            if (mapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.inventory) || 0;
                    const missing = data.missing;
                    const nrReq = String(data.nr_req || 'REQ').toUpperCase();
                    const price = parseFloat(data.temu_price) || 0;
                    const temuStock = parseFloat(data.temu_stock) || 0;
                    if (inv <= 0 || nrReq !== 'REQ' || missing === 'M' || price <= 0 || temuStock <= 0) return false;
                    return temuInvWithinMapTolerance(inv, temuStock);
                });
            }

            if (missingMFilterActive || notMapBadgeFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.inventory) || 0;
                    const missing = data.missing;
                    const nrReq = String(data.nr_req || 'REQ').toUpperCase();
                    const price = parseFloat(data.temu_price) || 0;
                    const temuStock = parseFloat(data.temu_stock) || 0;
                    if (inv <= 0 || nrReq !== 'REQ' || missing === 'M' || price <= 0 || temuStock <= 0) return false;
                    return !temuInvWithinMapTolerance(inv, temuStock);
                });
            }
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'temu_price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'temu_price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return temu2HasBlueTriangle(data);
                });
            }

            updateSummary();
            updateSelectAllCheckbox();

            if (skuSearch || parentSearch) {
                const resultCount = table.getData('active').length;
                const q = skuSearch || parentSearch;
                if (resultCount === 0) {
                    $('#search-result-info').html('<i class="fa fa-exclamation-triangle text-warning"></i> No results for "' + q + '".').show();
                } else {
                    $('#search-result-info').html('Found ' + resultCount + ' result(s)').show();
                }
            } else {
                $('#search-result-info').hide();
            }

            try {
                table.getColumn('lmp').show();
            } catch (e) {}
            try {
                if (missingMFilterActive || notMapBadgeFilterActive) table.getColumn('MAP').show();
                else table.getColumn('MAP').hide();
            } catch (e) {}
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#temu2-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#temu2-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        $('#temu2-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
            }
            applyFilters();
        });

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
                if (isTemu2ParentRow(row)) return;
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
            const displayData = fullDataset.filter(row =>
                !isTemu2ParentRow(row) && getRowGroupKey(row) === currentGroupKey
            );
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
        function openLmpModal(sku, entries) {
            lmpModalSku = sku || '';
            $('#lmpModalSku').text(lmpModalSku);
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
            const tbody = $('#lmpEntriesContainer');
            tbody.empty();
            const list = Array.isArray(entries) && entries.length > 0 ? entries : [];
            list.forEach(function(entry) {
                appendLmpTableRow(
                    tbody,
                    entry.price !== undefined && entry.price !== null ? entry.price : '',
                    entry.delivery !== undefined && entry.delivery !== null ? entry.delivery : '',
                    entry.link || ''
                );
            });
            updateLmpLowestHighlight();
            $('#lmpModal').modal('show');
        }
        function getTemu2LmpEffectivePrice(tr) {
            const val = $(tr).find('.lmp-price').val();
            const num = val !== '' && val != null ? parseFloat(val) : NaN;
            if (isNaN(num)) return null;
            const dVal = $(tr).find('.lmp-delivery').val();
            let delivery = dVal !== '' && dVal != null ? parseFloat(dVal) : 0;
            if (isNaN(delivery) || delivery < 0) delivery = 0;
            // Default +$2.99 when Price < $27
            if (delivery <= 0 && num < 27) delivery = 2.99;
            return num + delivery;
        }
        function appendLmpTableRow(tbody, price, delivery, link) {
            const tr = $('<tr class="lmp-entry-row">' +
                '<td class="lmp-num text-center align-middle"></td>' +
                '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="lmp-lowest-badge"></span></td>' +
                '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-delivery border-0 bg-transparent" style="max-width:90px" placeholder="0.00" title="Added to Price for LMP"></td>' +
                '<td class="align-middle text-center"><span class="lmp-price-d text-muted">—</span></td>' +
                '<td class="align-middle"><input type="text" class="form-control form-control-sm lmp-link d-inline-block me-1" style="max-width:200px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary lmp-open-link" target="_blank" rel="noopener" title="Open link"><i class="fas fa-external-link-alt"></i></a></td>' +
                '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger lmp-remove-row" title="Remove"><i class="fas fa-trash-alt"></i></button></td></tr>');
            tr.find('.lmp-price').val(price !== '' && price != null ? price : '');
            tr.find('.lmp-delivery').val(delivery !== '' && delivery != null ? delivery : '');
            tr.find('.lmp-link').val(link || '');
            tbody.append(tr);
            updateTemu2LmpPriceD(tr);
            tr.find('.lmp-remove-row').on('click', function(e) {
                e.preventDefault();
                tr.remove();
                renumberLmpRows();
                updateLmpLowestHighlight();
            });
            tr.find('.lmp-price, .lmp-delivery, .lmp-link').on('input', function() {
                updateTemu2LmpPriceD(tr);
                updateLmpLowestHighlight();
            });
            tr.find('.lmp-open-link').on('click', function(e) {
                e.preventDefault();
                const href = (tr.find('.lmp-link').val() || '').trim();
                if (href && (href.startsWith('http://') || href.startsWith('https://'))) window.open(href, '_blank');
            });
            renumberLmpRows();
        }
        function updateTemu2LmpPriceD(tr) {
            const $el = $(tr).find('.lmp-price-d');
            if (!$el.length) return;
            const total = getTemu2LmpEffectivePrice(tr);
            if (total === null) {
                $el.text('—').addClass('text-muted');
            } else {
                $el.text('$' + Number(total).toFixed(2)).removeClass('text-muted');
            }
        }
        function renumberLmpRows() {
            $('#lmpEntriesContainer .lmp-entry-row').each(function(i) {
                $(this).find('.lmp-num').text(i + 1);
            });
        }
        function updateLmpLowestHighlight() {
            let minVal = null;
            let minTr = null;
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const tr = $(this);
                tr.removeClass('table-dark');
                tr.find('.lmp-lowest-badge').empty();
                updateTemu2LmpPriceD(tr);
                const num = getTemu2LmpEffectivePrice(tr);
                if (num !== null) {
                    if (minVal === null || num < minVal) { minVal = num; minTr = tr; }
                }
            });
            if (minTr && minVal !== null) {
                minTr.addClass('table-dark');
                minTr.find('.lmp-lowest-badge').html(' <span class="badge bg-info">LOWEST</span>');
            }
        }
        $('#lmpAddRowBtn').on('click', function() {
            const price = $('#lmpNewPrice').val();
            const delivery = $('#lmpNewDelivery').val();
            const link = $('#lmpNewLink').val();
            if (!price && !link) {
                showToast('Enter Price or Link', 'warning');
                return;
            }
            appendLmpTableRow($('#lmpEntriesContainer'), price || '', delivery || '', link || '');
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
        });
        $('#lmpClearFormBtn').on('click', function() {
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
        });
        $('#lmpModalSaveBtn').on('click', function() {
            const entries = [];
            $('#lmpEntriesContainer .lmp-entry-row').each(function() {
                const price = $(this).find('.lmp-price').val();
                const delivery = $(this).find('.lmp-delivery').val();
                const link = $(this).find('.lmp-link').val();
                if (price || link || delivery) {
                    const deliveryNum = delivery !== '' && delivery != null ? parseFloat(delivery) : 0;
                    entries.push({
                        price: price ? parseFloat(price) : null,
                        delivery: (!isNaN(deliveryNum) && deliveryNum > 0) ? deliveryNum : 0,
                        link: link ? link.trim() : null
                    });
                }
            });
            if (entries.length === 0) {
                showToast('Add at least one price or link', 'warning');
                return;
            }
            if (!lmpModalSku) {
                showToast('Missing SKU — reopen the LMP modal', 'error');
                return;
            }
            $(this).prop('disabled', true);
            $.ajax({
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
                }),
                success: function(response) {
                    if (response && response.success) {
                        showToast(response.message || 'LMP saved successfully', 'success');
                        $('#lmpModal').modal('hide');
                        if (table) table.replaceData();
                    } else {
                        showToast((response && (response.message || response.error)) || 'Failed to save LMP', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                        || 'Failed to save LMP';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $('#lmpModalSaveBtn').prop('disabled', false);
                }
            });
        });

        $('#parent-filter, #inventory-filter, #tl30-filter, #growth-sign-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter, #sprice-lmp-filter, #prc-lmp-filter, #lmp-filter, #dil-filter').on('change', function() {
            applyFilters();
        });

        $(document).on('click', '.match-column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $item = $(this);
            const color = $item.data('color') || 'all';
            const dropdown = $item.closest('.dropdown');
            const button = dropdown.find('.dropdown-toggle');
            dropdown.find('.match-column-filter').removeClass('active');
            $item.addClass('active');
            button.data('color', color);
            const statusCircle = $item.find('.status-circle').clone();
            const matchLabels = { green: ' Green', red: ' Red', 'red-': ' Diff −', 'red+': ' Diff +', none: ' No LMP' };
            button.html('').append(statusCircle).append(matchLabels[color] || ' Match');
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
                        applyTemu2StandardPriceToLinkedRows(sku, saved, response.applied_skus);
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
                    url: '/temu2-pricing/update-price',
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
                const newSprice = parseFloat(cell.getValue());
                if (newSprice < 0) {
                    showToast('SPRICE cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                row.update({ sprice: newSprice });
                row.reformat();
                
                $.ajax({
                    url: '/temu2-pricing/save-sprice',
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

        // NR/REQ dropdown change handler (Amazon style)
        $(document).on('change', '.nr-select', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');

            if (!sku) {
                showToast('SKU missing — cannot save NR/REQ', 'error');
                return;
            }

            $.ajax({
                url: '/temu2-data-view/save-listing-fields',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    nr_req: value
                },
                success: function(response) {
                    if (response && (response.status === 'success' || response.success)) {
                        try {
                            if (typeof table !== 'undefined' && table) {
                                const rows = table.searchRows('sku', '=', sku);
                                if (rows.length) {
                                    rows[0].update({ nr_req: value });
                                }
                            }
                        } catch (e) { /* ignore */ }
                        showToast(response.message || 'NR/REQ updated successfully', 'success');
                    } else {
                        showToast('Unexpected response when saving NR/REQ', 'error');
                    }
                },
                error: function(xhr) {
                    let msg = 'Failed to update NR/REQ';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.errors) {
                            const err = xhr.responseJSON.errors;
                            msg = Object.keys(err).map(function(k) { return err[k].join(' '); }).join(' ');
                        }
                    } else if (xhr.status === 419) {
                        msg = 'Session expired — refresh the page and try again';
                    }
                    showToast(msg, 'error');
                }
            });
        });

        // ---- Edit B/S Links (double-click on Links cell) ----
        let temu2EditLinksRow = null;
        window.openTemu2EditLinksModal = function(row) {
            if (!row) return;
            temu2EditLinksRow = row;
            const d = row.getData();
            $('#temu2EditLinksSku').val(d.sku);
            $('#temu2EditLinksSkuDisplay').text(d.sku);
            $('#temu2EditSellerLink').val(d.seller_link || '');
            $('#temu2EditBuyerLink').val(d.buyer_link || '');
            $('#temu2EditLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('temu2EditLinksModal')).show();
        };

        $(document).on('click', '#temu2SaveLinksBtn', function() {
            const sku = $('#temu2EditLinksSku').val();
            const sellerLink = $('#temu2EditSellerLink').val().trim();
            const buyerLink = $('#temu2EditBuyerLink').val().trim();
            const $err = $('#temu2EditLinksError');
            $err.hide().text('');
            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '/temu2-decrease/save-links',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { sku: sku, seller_link: sellerLink, buyer_link: buyerLink },
                success: function(res) {
                    if (temu2EditLinksRow) {
                        temu2EditLinksRow.update({ seller_link: res.seller_link || '', buyer_link: res.buyer_link || '' })
                            .then(function() { temu2EditLinksRow.reformat(); })
                            .catch(function() { temu2EditLinksRow.reformat(); });
                    }
                    showToast(sku + ': links saved', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('temu2EditLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // Initialize iconClicked flag for IN ROAS
        window.iconClicked = false;

        /*
         * Column visibility — 4 groups (Basics / Pricing / Advertisement / Others)
         * with group-header checkboxes to select/deselect an entire group.
         * Persists via /tabulator-column-visibility (channel = 'temu2_decrease').
         */
        const COL_VIS_CATEGORY_KEYS = ['basics', 'pricing', 'advertisement', 'others'];
        const COL_VIS_CATEGORY_LABELS = {
            basics: 'Basics',
            pricing: 'Pricing',
            advertisement: 'Advertisement',
            others: 'Others'
        };

        function classifyTemu2Column(field, title) {
            const f = String(field || '');
            const t = String(title || field || '').replace(/<[^>]*>/g, '');
            const fl = f.toLowerCase();
            const tl = t.toLowerCase();
            const blob = fl + ' ' + tl;

            // Advertisement
            if (
                /^(spend|spend_l30|ad_sold_l30|acos_ad|ad_clicks|t_clicks|t_clicks_l7|t_clicks_growth|impressions|out_roas_l30|in_roas_l30|net_roas|target|ads_percent)$/i.test(f) ||
                /\b(spend|ad\s*sold|acos|ad\s*clicks|t\s*clicks?|t\s*click\s*growth|impressions|roas|target|ads\s*%)\b/i.test(tl)
            ) {
                return 'advertisement';
            }

            // Basics — identity / inventory / listing status (incl. Dil%)
            if (
                /^(image_path|parent|sku|links_column|goods_id|inventory|temu_stock|ovl30|dil_percent|temu_l30|missing|MAP|nr_req|nrp|o_clicks|product_clicks)$/i.test(f) ||
                /\b(image|parent|sku|links|goods|inv|stock|ovl|dil|temu\s*l\d+|t\s*l\d+|missing|map|nrl|req|views|o\s*clicks)\b/i.test(tl)
            ) {
                return 'basics';
            }

            // Pricing
            if (
                /^(cvr_percent|cvr_30|cvr_45|base_price|temu_price|temu_price_display|s_profit|temu1_price|temu1_base_price|profit|profit_percent|roi_percent|npft_percent|nroi_percent|lmp|sprice|s_recovery|stemu_price|sgprft_percent|spft_percent|sroi_percent|lp|temu_ship|prmt_pct|cpn_pct|zero_sold|cvr_up_dn|t_discounts|dsc|appr|push_prc)$/i.test(f) ||
                /\b(cvr|price|prc|gpft|gprft|npft|groi|nroi|prft|profit|lmp|s\s*prc|sgprft|spft|sroi|lp|ship|recovery|prmt|cpn|dsc|appr|push\s*prc)\b/i.test(tl)
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
                        if (alwaysHiddenColumns.indexOf(def.field) !== -1) return;

                        const rawTitle = def.title || def.field;
                        const title = String(rawTitle).replace(/<[^>]*>/g, '').trim() || def.field;
                        const cat = classifyTemu2Column(def.field, title);

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

        // Columns that should ALWAYS stay hidden, regardless of saved state.
        var alwaysHiddenColumns = ['cvr_45', 'profit'];
        function enforceAlwaysHiddenColumns() {
            alwaysHiddenColumns.forEach(function(col) {
                try { table.hideColumn(col); } catch (e) {}
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
                    if (savedVisibility && typeof savedVisibility === 'object') {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (def.field && savedVisibility.hasOwnProperty(def.field)) {
                                if (savedVisibility[def.field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                    }
                    // Keep Spend visible (display-only; not Temu ads feature set)
                    try { table.showColumn('spend'); } catch (e) {}
                    enforceAlwaysHiddenColumns();
                    temu2AutofitColumns();
                })
                .catch(err => console.error('Error applying column visibility:', err));
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
            try { table.showColumn('spend'); } catch (e) {}
            enforceAlwaysHiddenColumns();
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
            temu2AutofitColumns();

            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = temu2SkuFromCheckbox(this);
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
                    const sku = temu2SkuFromCheckbox(this);
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
                        if (alwaysHiddenColumns.indexOf(field) !== -1) return;
                        cb.checked = checked;
                        const col = table.getColumn(field);
                        if (!col) return;
                        if (checked) col.show();
                        else col.hide();
                    });
                    e.target.indeterminate = false;
                    enforceAlwaysHiddenColumns();
                    saveColumnVisibilityToServer();
                    temu2AutofitColumns();
                    return;
                }

                // Individual column checkbox
                const field = e.target.value;
                if (alwaysHiddenColumns.indexOf(field) !== -1) {
                    e.target.checked = false;
                    enforceAlwaysHiddenColumns();
                    return;
                }
                const col = table.getColumn(field);
                if (!col) return;
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                syncGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                saveColumnVisibilityToServer();
                temu2AutofitColumns();
            });
            // "Show All" — same as /ebay-tabulator-view
            colMenu.addEventListener("click", function(e) {
                var showAll = e.target.closest('#show-all-columns-btn');
                if (showAll) {
                    e.preventDefault();
                    e.stopPropagation();
                    table.getColumns().forEach(col => col.show());
                    enforceAlwaysHiddenColumns();
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                    temu2AutofitColumns();
                }
            });
        })();

        function updateCampaignPeriodUi() {
            const isL7 = currentCampaignPeriod === 'L7';

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
            $('#temu-total-ad-sold-badge').attr('title', isL7 ? 'Total L7 Ad Sold' : 'Total L30 Ad Sold');
        }

        function currentPeriodEndpoint() {
            return currentCampaignPeriod === 'L7' ? '/temu2-decrease-data-l7' : '/temu2-decrease-data';
        }

        // Export L30 / L7 from icon dropdown — loads period data if needed, then restores current view
        function exportPeriodCsv(period) {
            const isL7 = period === 'L7';
            const filename = isL7 ? 'temu2_decrease_data_l7.csv' : 'temu2_decrease_data_l30.csv';
            const endpoint = isL7 ? '/temu2-decrease-data-l7' : '/temu2-decrease-data';
            const $btn = $('#export-btn');
            const originalHtml = $btn.html();

            if (currentCampaignPeriod === period) {
                table.download("csv", filename);
                return;
            }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            const restoreEndpoint = currentPeriodEndpoint();
            suppressDataLoadedHandler = true;
            table.setData(endpoint).then(function() {
                applyFilters();
                table.download("csv", filename);
                suppressDataLoadedHandler = false;
                return table.setData(restoreEndpoint);
            }).then(function() {
                applyFilters();
                if (typeof showToast === 'function') {
                    showToast(period + ' export completed', 'success');
                }
            }).catch(function(err) {
                console.error('Export ' + period + ' error', err);
                suppressDataLoadedHandler = false;
                return table.setData(restoreEndpoint).then(function() {
                    applyFilters();
                }).then(function() {
                    if (typeof showToast === 'function') {
                        showToast('Failed to export ' + period + ' data', 'error');
                    }
                });
            }).finally(function() {
                $btn.prop('disabled', false).html(originalHtml);
            });
        }

        $('#export-l30-btn').on('click', function(e) {
            e.preventDefault();
            exportPeriodCsv('L30');
        });

        $('#export-l7-btn').on('click', function(e) {
            e.preventDefault();
            exportPeriodCsv('L7');
        });

        // Copy temu_data_view → temu2_data_view (one SKU or all)
        $('#sync-temu2-dataview-btn').on('click', function() {
            const oneSku = window.prompt(
                'Leave empty to sync ALL SKUs from temu_data_view → temu2_data_view.\nOr enter one SKU (e.g. CS 04 2W WoG 4PCS):',
                ''
            );
            if (oneSku === null) {
                return;
            }
            const sku = (oneSku || '').trim();
            const msg = sku
                ? 'Copy temu_data_view row for "' + sku + '" into temu2_data_view?'
                : 'Copy ALL rows from temu_data_view into temu2_data_view? Existing temu2_data_view rows for the same SKU will be overwritten.';
            if (!window.confirm(msg)) {
                return;
            }
            const $b = $(this);
            const html = $b.html();
            $b.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            const syncPayload = { _token: '{{ csrf_token() }}' };
            if (sku) {
                syncPayload.sku = sku;
            }
            $.ajax({
                url: '/temu2-sync-data-view-from-temu',
                method: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                data: syncPayload,
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || ('Synced ' + (res.synced || 0) + ' row(s)'), 'success');
                        if (typeof table !== 'undefined' && table) {
                            table.setData('/temu2-decrease-data').then(function() {
                                applyFilters();
                            });
                        }
                    } else {
                        showToast(res.message || 'Sync failed', 'error');
                    }
                },
                error: function(xhr) {
                    const m = xhr.responseJSON?.message || xhr.statusText || 'Sync failed';
                    showToast(m, 'error');
                },
                complete: function() {
                    $b.prop('disabled', false).html(html);
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

        // Temu 2 daily data upload → temu2_daily_data / temu2_daily_data_l60
        $('#startUploadBtn').on('click', function() {
            const fileInput = document.getElementById('dailyDataFile');
            const file = fileInput && fileInput.files[0];
            if (!file) {
                showToast('Please select a file to upload', 'error');
                return;
            }
            $('#uploadProgressContainer').show();
            $('#uploadResult').hide();
            $('#startUploadBtn').prop('disabled', true);
            const totalChunks = 5;
            const period = $('#dailyDataUploadPeriod').val() || 'L30';
            const uploadUrl = period === 'L60' ? '/temu2/upload-daily-data-l60-chunk' : '/temu2/upload-daily-data-chunk';
            const uploadId = (period === 'L60' ? 'temu2_l60_' : 'temu2_') + Date.now();
            let currentChunk = 0;
            let totalImported = 0;

            function uploadChunk() {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('chunk', currentChunk);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            totalImported += response.imported || 0;
                            const progress = response.progress || 0;
                            $('#uploadProgressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                            $('#uploadStatus').text('Processing chunk ' + (currentChunk + 1) + ' of ' + totalChunks + '...');
                            if (currentChunk < totalChunks - 1) {
                                currentChunk++;
                                setTimeout(uploadChunk, 500);
                            } else {
                                $('#uploadProgressBar').removeClass('progress-bar-animated').addClass('bg-success');
                                $('#uploadResult').removeClass('alert-danger').addClass('alert-success')
                                    .html('<i class="fa fa-check-circle me-2"></i>Upload completed! ' + totalImported + ' records imported.').show();
                                $('#startUploadBtn').prop('disabled', false);
                                showToast(period + ' upload completed (' + totalImported + ' rows)', 'success');
                                setTimeout(function() {
                                    $('#uploadDailyDataModal').modal('hide');
                                    resetTemu2DailyUploadForm();
                                    if (table) table.setData('/temu2-decrease-data');
                                }, 1200);
                            }
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    },
                    error: function(xhr) {
                        const errorMessage = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Upload failed. Please try again.';
                        $('#uploadProgressBar').removeClass('progress-bar-animated').addClass('bg-danger');
                        $('#uploadResult').removeClass('alert-success').addClass('alert-danger')
                            .html('<i class="fa fa-exclamation-circle me-2"></i>' + errorMessage).show();
                        $('#startUploadBtn').prop('disabled', false);
                        showToast(errorMessage, 'error');
                    }
                });
            }
            uploadChunk();
        });

        $('#uploadDailyDataModal').on('hidden.bs.modal', resetTemu2DailyUploadForm);

        function resetTemu2DailyUploadForm() {
            $('#dailyDataFile').val('');
            $('#uploadProgressContainer').hide();
            $('#uploadResult').hide();
            $('#uploadProgressBar').removeClass('bg-success bg-danger').addClass('progress-bar-animated').css('width', '0%').text('0%');
            $('#uploadStatus').text('');
            $('#startUploadBtn').prop('disabled', false);
        }

        updateCampaignPeriodUi();

        // --- Temu2 price push: SPRICE → base via inverse of Temu Price ---
        function pushTemu2PriceForRow(row, price) {
            const data = row.getData();
            const sku = data.sku;
            const goodsId = data.goods_id || '';
            const skuId = data.sku_id || '';
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
                    url: '/temu2/push-price',
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
                        const msg = (xhr.responseJSON && (xhr.responseJSON.message
                            || (xhr.responseJSON.errors && xhr.responseJSON.errors[0] && xhr.responseJSON.errors[0].message)))
                            || 'Push failed';
                        reject({ message: msg });
                    }
                });
            });
        }

        $(document).on('click', '.temu2-push-single-btn', function(e) {
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
            if (pushBase == null || pushBase <= 0) {
                showToast('Cannot push — invalid or negative S Temu B Prc', 'error');
                return;
            }
            if (!confirm(
                'Push Temu2 base $' + pushBase.toFixed(2)
                + ' (inverse of Temu Price from SPRICE $' + sprice.toFixed(2) + ')'
                + ' for SKU: ' + sku + '?'
            )) return;

            pushTemu2PriceForRow(row, sprice).then(function() {
                showToast('Price pushed to Temu2', 'success');
                if (typeof updateSummary === 'function') updateSummary();
            }).catch(function(err) {
                showToast((err && err.message) || 'Failed to push price', 'error');
            });
        });

        if (window.TemuViewDataUpload) {
            TemuViewDataUpload.init({
                formId: 'uploadViewDataForm',
                inputId: 'viewDataFile',
                listId: 'viewDataFileList',
                statusId: 'viewDataUploadStatus',
                onSuccess: function() {
                    if (table) table.setData('/temu2-decrease-data');
                }
            });
        }
        @if(session('success') || session('error') || $errors->any())
        try {
            var uploadViewModalEl = document.getElementById('uploadViewDataModal');
            if (uploadViewModalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(uploadViewModalEl).show();
            }
        } catch (e) {}
        @endif

        $('#startPricingUploadBtn').on('click', function() {
            const fileInput = document.getElementById('pricingFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            if (!file) {
                showToast('Choose a Temu 2 pricing file first', 'error');
                return;
            }
            const $btn = $(this);
            const $result = $('#pricingUploadResult');
            $result.hide().removeClass('alert-success alert-danger');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Uploading…');

            const fd = new FormData();
            fd.append('pricing_file', file);
            fd.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: '{{ route("temu2.pricing.upload") }}',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 180000,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                success: function(res) {
                    const msg = (res && res.message) || 'Pricing uploaded';
                    $result.addClass(res && res.success === false ? 'alert-danger' : 'alert-success')
                        .text(msg).show();
                    showToast(msg, res && res.success === false ? 'error' : 'success');
                    if (res && res.success !== false) {
                        setTimeout(function() { location.reload(); }, 900);
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'Temu 2 pricing upload failed';
                    $result.addClass('alert-danger').text(msg).show();
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Up Pricing');
                }
            });
        });

        $('#sync-temu2-api-pricing').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Sync Temu 2 listings/prices/stock from Open API into temu2_metrics?')) {
                return;
            }
            const $link = $(this);
            $link.addClass('disabled').css('pointer-events', 'none');
            showToast('Syncing Temu 2 from API…', 'info');
            $.ajax({
                url: '{{ route("temu2.sync.metrics") }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    showToast((res && res.message) || 'Temu 2 sync complete', res && res.success === false ? 'error' : 'success');
                    if (typeof table !== 'undefined' && table && typeof table.replaceData === 'function') {
                        // reload decrease data
                        location.reload();
                    } else {
                        location.reload();
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Temu 2 API sync failed';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $link.removeClass('disabled').css('pointer-events', '');
                }
            });
        });

        $('#push-temu2-price-btn').on('click', function() {
            if (!table) {
                showToast('Table not ready', 'error');
                return;
            }
            const items = [];
            table.getRows('active').forEach(function(row) {
                const d = row.getData();
                if (d.is_parent) return;
                const sprice = parseFloat(d.sprice) || 0;
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
                'Push Temu2 base for ' + items.length + ' SKU(s)?\n'
                + 'Base = inverse of Temu Price (÷ 1.1364, undo +$2.99 if applied)'
            )) return;

            const $btn = $('#push-temu2-price-btn');
            const btnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            let ok = 0, fail = 0, i = 0;

            function next() {
                if (i >= items.length) {
                    $btn.prop('disabled', false).html(btnHtml);
                    showToast('Temu2 push done: ' + ok + ' ok, ' + fail + ' failed', fail ? 'warning' : 'success');
                    if (typeof updateSummary === 'function') updateSummary();
                    return;
                }
                const item = items[i++];
                pushTemu2PriceForRow(item.row, item.price).then(function() {
                    ok++;
                    setTimeout(next, 250);
                }).catch(function() {
                    fail++;
                    setTimeout(next, 250);
                });
            }
            next();
        });
    });
</script>
@endsection
