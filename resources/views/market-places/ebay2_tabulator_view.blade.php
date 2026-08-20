@extends('layouts.vertical', ['title' => 'Ebay 2 - Analytics', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Toolbar: compact controls, wrap to next row if needed.
           NOTE: do NOT use overflow-x on this row — it clips Bootstrap dropdown menus
           (Columns, DIL%, etc.) so they wouldn't open. */
        .ebay2-toolbar-row {
            row-gap: 4px;
        }
        .ebay2-toolbar-row .dropdown-menu {
            font-size: 0.75rem;
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

        /* Image column hover preview (forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

        /* Parent expand icon — yellow play triangle (same as /price-increase P column) */
        .ebay2-parent-sku-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            cursor: pointer;
            vertical-align: middle;
            line-height: 0;
            transition: transform 0.2s ease, filter 0.2s ease, opacity 0.2s ease;
            filter: drop-shadow(0 1px 1px rgba(180, 110, 0, 0.35));
        }
        .ebay2-parent-sku-dot svg {
            width: 14px;
            height: 14px;
            display: block;
        }
        .ebay2-parent-sku-dot:hover {
            filter: drop-shadow(0 2px 3px rgba(180, 110, 0, 0.45));
            transform: scale(1.08);
        }
        .ebay2-parent-sku-dot.is-expanded {
            transform: rotate(90deg);
        }
        .ebay2-parent-sku-dot.is-expanded:hover {
            transform: rotate(90deg) scale(1.08);
        }
        .ebay2-parent-sku-dot.no-parent {
            cursor: default;
            opacity: 0.35;
            filter: grayscale(1) drop-shadow(none);
        }
        .ebay2-parent-sku-dot.no-parent:hover {
            transform: none;
            filter: grayscale(1) drop-shadow(none);
        }

        /* Sku Link LMP (mirrors /ebay-tabulator-view) */
        .linked-sku-badge-wrap { display: inline-flex; align-items: center; gap: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove { font-size: 0.55rem; opacity: 0.65; padding: 0; margin-left: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover { opacity: 1; }
        .sku-link-lmp-suggestion-item { cursor: pointer; }
        .sku-link-lmp-suggestion-item .form-check-input { pointer-events: none; }
        .sku-link-lmp-selected-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px; }
        .sku-link-lmp-selected-chip button { border: 0; background: transparent; padding: 0; line-height: 1; font-size: 14px; color: #64748b; }

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
        
        /* Vertical column headers — same 64px gap as Amazon */
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
        
        .tabulator .tabulator-header .tabulator-col {
            height: 64px !important;
        }

        /* Dense body rows — same spacing as Amazon */
        #ebay2-table .tabulator-row {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        #ebay2-table .tabulator-row .tabulator-cell {
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
        #ebay2-table .tabulator-row .tabulator-cell span,
        #ebay2-table .tabulator-row .tabulator-cell a,
        #ebay2-table .tabulator-row .tabulator-cell div,
        #ebay2-table .tabulator-row .tabulator-cell button,
        #ebay2-table .tabulator-row .tabulator-cell label,
        #ebay2-table .tabulator-row .tabulator-cell input:not([type="checkbox"]):not([type="radio"]),
        #ebay2-table .tabulator-row .tabulator-cell select,
        #ebay2-table .tabulator-row .tabulator-cell i {
            font-size: 13px !important;
        }
        #ebay2-table .tabulator-row .tabulator-cell img.hover-thumb {
            width: 28px !important;
            height: 28px !important;
            max-width: 28px !important;
            max-height: 28px !important;
            object-fit: cover !important;
            display: block !important;
            flex-shrink: 0 !important;
        }
        #ebay2-table .tabulator-row .tabulator-cell > div {
            flex-wrap: nowrap !important;
            max-width: 100%;
            overflow: hidden;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Custom pagination label (same as eBay 1) */
        .tabulator-paginator label {
            margin-right: 5px;
        }
        #ebay2-table .tabulator-footer {
            background: #e6e6e6;
        }

        /* Status circle indicators */
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

        /* Manual dropdown styling */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .manual-dropdown-container .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
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

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }

        /* Forecast NRP (REQ / 2BDC / LATER) — shared with Forecast Analysis */
        .nrp-dot-cell { min-height: 32px; min-width: 44px; }
        .nrp-dot-cell .nrp-status-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            border: 1px solid rgba(0,0,0,.12); flex-shrink: 0;
        }
        .nrp-dot-cell .nrp-nr-select {
            opacity: 0; cursor: pointer; font-size: 11px; padding: 0; border: 0; background: transparent;
        }
        .nrp-dot-cell .nrp-nr-select:focus { opacity: 1; outline: 1px solid #0d6efd; }

        /* Compact toolbar + badges — same density as /ebay-tabulator-view */
        .ebay2-toolbar-row {
            gap: 4px 6px !important;
            align-items: center !important;
        }
        .ebay2-toolbar-row .form-select,
        .ebay2-toolbar-row .form-control,
        .ebay2-toolbar-row .btn,
        .ebay2-toolbar-row .btn-sm,
        .ebay2-toolbar-row .dropdown > .btn {
            height: 26px !important;
            min-height: 26px !important;
            font-size: 0.75rem !important;
            padding: 0 0.4rem !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }
        .ebay2-toolbar-row .form-select {
            width: auto !important;
            max-width: 120px;
            padding-right: 1.15rem !important;
            padding-left: 0.35rem !important;
            background-position: right 0.28rem center !important;
        }
        .ebay2-toolbar-row .pricing-filter-item.border,
        .ebay2-toolbar-row .d-inline-flex.border {
            height: 26px !important;
            min-height: 26px !important;
            padding: 0 4px !important;
            gap: 3px !important;
            align-items: center !important;
        }
        .ebay2-toolbar-row .pricing-filter-item .form-label,
        .ebay2-toolbar-row .d-inline-flex .form-label {
            font-size: 0.72rem !important;
            margin-bottom: 0 !important;
        }
        .ebay2-toolbar-row #target-roi-input,
        .ebay2-toolbar-row #target-gpft-input {
            width: 52px !important;
            height: 24px !important;
            min-height: 24px !important;
            font-size: 0.75rem !important;
            padding: 0.1rem 0.25rem !important;
        }
        #summary-stats {
            order: -1;
            padding: 0.28rem 0.45rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.28rem !important;
        }
        #summary-stats .d-flex {
            gap: 4px !important;
            flex-wrap: wrap !important;
            align-items: center;
        }
        #summary-stats .badge {
            flex: 0 0 auto;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem !important;
            padding: 0.18rem 0.4rem !important;
            font-weight: 700 !important;
            line-height: 1.25 !important;
            border-radius: 0.28rem !important;
            white-space: nowrap;
        }
        .ebay2-toolbar-row .ms-2 { margin-left: 0 !important; }
        .ebay2-toolbar-row .p-1 { padding: 0 4px !important; }

        /* Metric history modals — full width (same as /ebay-tabulator-view) */
        #skuMetricsModal.modal {
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

        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'ebay2'])
    </style>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    @include('partials.lazy-chart-js')
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 2 - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-1 d-flex flex-column">
                <div class="d-flex align-items-center flex-wrap ebay2-toolbar-row">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 140px; display: inline-block;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 140px; display: inline-block;">

                    <select id="view-mode-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;"
                        title="ALL = Parent + SKU · Parents = PARENT rows only · SKU = child SKU rows only">
                        <option value="all">ALL</option>
                        <option value="parent" selected>Parents</option>
                        <option value="sku">SKU</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm"
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

                    {{-- Dil vs PRMT / CVR vs CPN — ebay2_* tables, not shared with eBay1 --}}
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'ebay2'])

                    {{-- Price (eBay Price) min–max range filter --}}
                    <div class="d-inline-flex align-items-center gap-1 pricing-filter-item"
                        id="price-range-filter"
                        title="Filter by Price (eBay Price) — leave blank to ignore Min or Max">
                        <span class="small fw-semibold text-nowrap mb-0">Price</span>
                        <input type="number" id="price-min-filter" class="form-control form-control-sm text-end"
                            placeholder="Min" step="0.01" min="0" style="width: 64px;"
                            title="Minimum Price (eBay Price)">
                        <span class="small text-muted mb-0">–</span>
                        <input type="number" id="price-max-filter" class="form-control form-control-sm text-end"
                            placeholder="Max" step="0.01" min="0" style="width: 64px;"
                            title="Maximum Price (eBay Price)">
                    </div>

                    <!-- DIL Filter (plain select — matches /amazon-tabulator-view dropdown UI) -->
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
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="ebay2-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Prc Mode
                    </button>

                    <button type="button" class="btn btn-sm btn-success pricing-filter-item" data-bs-toggle="modal" data-bs-target="#exportModal" title="Export">
                        <i class="fa fa-file-excel"></i>
                    </button>

                    {{-- Sbid Rule — eBay 2 View VS SBID (ebay2_sbid_slabs), same as /ebay2/campaign-ads --}}
                    <button type="button" class="btn btn-sm btn-outline-primary pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#sbidRuleModal"
                            title="eBay 2 Sbid Rule — For L7 Views that set the S Bid (Parents Only)">
                        <i class="fas fa-sliders-h me-1"></i>Sbid Rule <span id="sbid-rule-btn-count"></span>
                    </button>

                    {{-- Sbid (Views) — same as /ebay2/campaign-ads --}}
                    <button type="button" class="btn btn-sm pricing-filter-item"
                            style="border:1px solid #6610f2; color:#6610f2;"
                            data-bs-toggle="modal" data-bs-target="#sbidViewsRuleModal"
                            title="Configure Min/Max caps and the daily ±%/day step per L7 View colour for the S BID column">
                        <i class="fas fa-eye me-1"></i>Sbid
                    </button>


                    {{-- Target ROI% bulk control — back-solves SPRICE so SNROI (Amazon NROI formula) = Target. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target SNROI% — sets SPRICE so net SROI = Target (accounts for fees, shipping, and Ads%)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target SNROI% applied to all selected rows when you click 'Apply SPRICE'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE so SNROI equals Target for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (margin − Target GPFT%/100) on every selected row">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than the eBay2 take-home margin (typically < 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (margin − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger sold-filter-badge" data-filter="zero" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: <span id="zero-sold-count">0</span></span>
                        <span class="badge sold-filter-badge" data-filter="sold" style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;" title="Click to filter sold items">> 0 Sold: <span id="more-sold-count">0</span></span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-success d-none" id="total-pft-amt-badge" style="color: black; font-weight: bold;" aria-hidden="true">Total PFT: $0</span>
                        <span class="badge bg-primary" id="total-sales-amt-badge" style="color: black; font-weight: bold;"
                              title="L30 sales from real eBay 2 orders (tax-inclusive, excl. cancelled & fully-refunded) — same source as /ebay2/daily-sales.">Sales: ${{ number_format((float) ($ordersL30TotalSales ?? 0)) }}</span>
                        {{-- S Qty: L30 units from ebay2_order_items.quantity (period='l30').
                             Same source the /all-marketplace-master Qty column for the EbayTwo row uses,
                             so this page agrees with the master page and with the eBay 1 tabulator's S Qty
                             badge. Static — page filters do not narrow it. --}}
                        <span class="badge" id="qty-sold-badge"
                              style="background-color: #6f42c1; color: white; font-weight: bold;"
                              title="L30 units sold (Σ ebay2_order_items.quantity for period='l30'). Same value /ebay2/daily-sales shows.">Qty: {{ number_format((int) ($ordersL30TotalQty ?? 0)) }}</span>
                        <!-- Percentage Metrics -->
                        <span class="badge bg-info" id="avg-gpft-badge" style="color: black; font-weight: bold;"
                              title="GPFT% = Σ T PFT / Σ (qty × unit price) × 100 from real L30 orders — same source as /ebay2/daily-sales.">GPFT: {{ round((float) ($ordersL30Gpft ?? 0)) }}%</span>
                        <span class="badge bg-secondary" id="groi-percent-badge" style="color: white; font-weight: bold;"
                              title="GROI% = Σ T PFT / Σ COGS × 100 from real L30 orders — same source as /ebay2/daily-sales.">GROI: {{ round((float) ($ordersL30Groi ?? 0)) }}%</span>
                        <span class="badge" id="ads-percent-badge" style="background-color: #d63384; color: white; font-weight: bold;"
                              title="TACOS = eBay 2 channel Total Ad Spend (same source as /ebay2/campaign-ads) ÷ real-orders L30 Sales × 100.">Ads: {{ number_format((float) ($channelAdsPercent ?? 0), 1) }}%</span>
                        <span class="badge" id="npft-percent-badge" style="background-color: #0f766e; color: white; font-weight: bold;"
                              title="NPFT% = GPFT% − Ads% (net profit margin after ad spend).">NPFT: {{ round((float) ($ordersL30Gpft ?? 0) - (float) ($channelAdsPercent ?? 0)) }}%</span>
                        <span class="badge" id="nroi-percent-badge" style="background-color: #6f42c1; color: white; font-weight: bold;"
                              title="NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same as Amz (do not cut Ads% from GROI%).">NROI: {{ round((float) ($ordersL30Nroi ?? 0)) }}%</span>

                        <!-- eBay Metrics -->
                        <span class="badge bg-warning" id="avg-price-badge" style="color: black; font-weight: bold;">Prc: $0.00</span>
                        <span class="badge bg-danger" id="avg-cvr-badge"
                              style="color: white; font-weight: bold;"
                              title="CVR = (S Qty / Σ Views) × 100. Numerator is the orders-API L30 units (same value the S Qty badge shows). Denominator is the sum of 'views' across rows with E Stock > 0.">CVR: 0%</span>
                        <span class="badge bg-info" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge fs-6 p-2" id="ebay2-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge fs-6 p-2" id="ebay2-red-triangle-badge"
                            style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
                            title="Red triangle: S PRC &gt; LMP. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge" id="avg-l7-views-badge" style="background-color: #6610f2; color: white; font-weight: bold;" title="Average L7 views across rows with E Stock &gt; 0 — drives L7 View colours and Sbid (Views)">L7: 0</span>
                        
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="ebay2-discount-type-block" class="d-flex align-items-center gap-2">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                    </div>
                </div>
                <div id="ebay2-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column; min-height: 0;">
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 4px 8px; background: #fff; border-bottom: 1px solid #e5e7eb;">
                        <span id="custom-pagination-counter"
                            style="font-size: 13px; color: #555; white-space: nowrap;"></span>
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay2-table" style="flex: 1; min-height: 0;"></div>
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
                        <i class="fa fa-shopping-cart"></i> eBay2 Competitors for SKU: <span id="lmpSku"></span>
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

    <!-- SKU Metrics Chart Modal (same format as /ebay-tabulator-view; dates = California / Pacific) -->
    <div class="modal fade p-0" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="skuChartModalTitle">eBay 2 - <span id="modalSkuName"></span> - Metrics</span> <span id="skuChartModalSuffix">(Rolling L30 · PT)</span>
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
                <div class="modal-body p-2">
                    <div id="skuChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
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

    <!-- Export Column Selection Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Columns to Export</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="select-all-export-columns">
                            <i class="fa fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="deselect-all-export-columns">
                            <i class="fa fa-square"></i> Deselect All
                        </button>
                    </div>
                    <div id="export-columns-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <!-- Columns will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirm-export-btn">
                        <i class="fa fa-file-excel"></i> Export Selected Columns
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import eBay Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv" required>
                            <div class="form-text">Upload a CSV file with columns: sku, rating (0-5)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fa fa-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="ebay2EditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="ebay2EditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="ebay2SellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="ebay2BuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ebay2SaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sku Link LMP Modal (same as /ebay-tabulator-view; shared endpoints/table) --}}
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skuLinkLmpModalLabel">Sku Link LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Link one or more SKUs to <strong id="sku-link-lmp-source"></strong>. All linked SKUs will show each other's LMP.</p>
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
                        <i class="fas fa-link"></i> <span id="sku-link-lmp-save-btn-label">Link SKU(s)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sbid Rule Modal — eBay 2 View VS SBID (ebay_sbid_rules.key = ebay2_sbid_slabs). --}}
    <div class="modal fade" id="sbidRuleModal" tabindex="-1" aria-labelledby="sbidRuleModalLabel" aria-hidden="true">
        <style>
            #sbidRuleModal .modal-dialog { max-width: 98vw; width: 98vw; margin: 0.5rem auto; }
            #sbid-slab-rule-table thead th { background-color: #fffef2 !important; color: #000 !important; }
            #sbidRuleModal input[type=number]::-webkit-inner-spin-button,
            #sbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            #sbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
            #sbidRuleModal .form-control, #sbidRuleModal .form-select { border-radius: 0.6rem; }
        </style>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="sbidRuleModalLabel">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>View VS SBID
                        <span class="badge bg-primary ms-2" style="font-size:11px;">eBay 2 · Parents Only</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <label for="sbid-es-bid-input" class="form-label mb-0 small fw-semibold">ES Bid (%)</label>
                        <input type="number" id="sbid-es-bid-input" step="0.1" min="0"
                               class="form-control form-control-sm text-end fw-semibold" style="width:88px;"
                               placeholder="—" title="Editable. Used only when eBay L30 (EL30) is 0. Leave blank to use each parent row's ES BID.">
                        <span class="small text-muted">Only for EL30 = 0.
                            <span id="sbid-es-bid-count" class="fw-semibold"></span>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="sbid-slab-rule-table" style="min-width: 520px;">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" style="width:34px;" class="text-center align-middle">#</th>
                                    <th colspan="2" class="text-center">For L7 Views</th>
                                    <th rowspan="2" style="width:72px;" class="align-middle text-center"
                                        title="Parent rows whose L7 Views fall in this slab (eBay 2 is Parents Only)">Count</th>
                                    <th rowspan="2" style="width:100px;" class="align-middle text-center">S Bid (%)</th>
                                    <th rowspan="2" style="width:44px;" class="align-middle"></th>
                                </tr>
                                <tr>
                                    <th class="text-center small text-muted">Min</th><th class="text-center small text-muted">Max</th>
                                </tr>
                            </thead>
                            <tbody id="sbid-slab-rules-body">
                                {{-- filled by JS --}}
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-primary mb-2" id="sbid-slab-add-rule-btn">
                        <i class="fas fa-plus me-1"></i>Add rule / slab
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="sbid-slab-rule-err"></p>
                </div>
                <div class="modal-footer py-2 d-flex justify-content-between">
                    <span class="small text-muted" id="sbid-slab-rule-status"></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-sm btn-success" id="sbid-slab-apply-btn"
                                title="Autopush is on. Changing a slab or the 0-sold (ES Bid) value saves the rule and pushes the new S Bid to eBay.">
                            <i class="fas fa-bolt me-1"></i>Autopush
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="sbid-slab-rule-save-btn">
                            <i class="fas fa-save me-1"></i>Save Rule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sbid (Views) Modal — shared with /ebay2/campaign-ads (ebay_sbid_rules.key = ebay2_sbid_views). --}}
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
                        Same rule as <code>/ebay2/campaign-ads</code>. S Bid Autopush runs when a slab or 0-sold (ES Bid) value changes.
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

    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'ebay2'])

@endsection

    @section('script-bottom')
    <script>
        // Cache bust: v2.1 - OPEN BOX items now included with base SKU lookup
        /** Stored in DB table channel_tabulator_column_settings (shared for all users). */
        const TABULATOR_COLUMN_CHANNEL = 'ebay2_tabulator';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'ebay2'])
        /** L30 units sold from ebay2_orders (period='l30'). Same value rendered into the
         *  S Qty badge and the eBay 2 row's Qty cell on /all-marketplace-master. Used by
         *  the CVR formula so the page CVR is computed against orders-API ground truth
         *  instead of the laggier ebay_2_metrics.ebay_l30 sum. */
        const ORDERS_L30_TOTAL_QTY = {{ (int) ($ordersL30TotalQty ?? 0) }};
        /** L30 Sales / GPFT% / GROI% from the same real orders /ebay2/daily-sales uses,
         *  so these badges agree with that page (fixed server values). */
        const ORDERS_L30_TOTAL_SALES = {{ (float) ($ordersL30TotalSales ?? 0) }};
        const ORDERS_L30_GPFT = {{ (float) ($ordersL30Gpft ?? 0) }};
        const ORDERS_L30_GROI = {{ (float) ($ordersL30Groi ?? 0) }};
        const ORDERS_L30_PFT = {{ (float) ($ordersL30Pft ?? 0) }};
        const ORDERS_L30_COGS = {{ (float) ($ordersL30Cogs ?? 0) }};
        const EBAY2_AD_SPEND = {{ (float) ($ebayAdSpend ?? 0) }};
        const ORDERS_L30_NROI = {{ (float) ($ordersL30Nroi ?? 0) }};
        const EBAY2_CHANNEL_ADS_PCT = {{ (float) ($channelAdsPercent ?? 0) }};
        /** Take-home from marketplace_percentages (EbayTwo). Used when a row has no percentage. */
        const EBAY2_TAKEHOME = {{ (float) ($ebayTakeHome ?? 1) }};

        /**
         * Net ROI — same shape as Amazon NROI / SNROI badge:
         *   (gross profit $ − ad spend $) / COGS × 100
         * where ad spend $ = price × Ads%/100 and COGS = LP.
         * @param {object} rowData
         * @param {string} priceKey  'eBay Price' for NROI, 'SPRICE' for SNROI
         */
        function ebay2ComputeNetRoi(rowData, priceKey) {
            if (!rowData) return null;
            const price = parseFloat(rowData[priceKey]);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : EBAY2_TAKEHOME;
            const adsFrac = (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) / 100;
            const grossPft = (price * margin) - ship - lp;
            const adSpend = price * adsFrac;
            return ((grossPft - adSpend) / lp) * 100;
        }
        /** S GPFT / S GROI / SNROI / SNPFT use S PRC (SPRICE). */
        function ebay2ComputeSgpftFromSprice(rowData) {
            if (!rowData) return null;
            const price = parseFloat(rowData.SPRICE);
            if (!isFinite(price) || price <= 0) return null;
            const lp = parseFloat(rowData.LP_productmaster) || 0;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : EBAY2_TAKEHOME;
            return ((price * margin - ship - lp) / price) * 100;
        }
        function ebay2ComputeSgroiFromSprice(rowData) {
            if (!rowData) return null;
            const price = parseFloat(rowData.SPRICE);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : EBAY2_TAKEHOME;
            return ((price * margin - lp - ship) / lp) * 100;
        }
        let skuMetricsChart = null;
        let currentSkuChartMetric = 'price'; // 'price' | 'cvr' | 'views' | 'l7_views'
        let currentSku = null;
        let skuChartFirstSeriesStats = null; // { values, median, dataMin, dataMax, valueFmt } for ref panel & plugins
        let table = null; // Global table reference

        /** Keep "Showing X–Y of Z rows" in sync with filtered/active set (same as eBay 1). */
        function ebay2UpdatePaginationCounter() {
            var $el = $('#custom-pagination-counter');
            if (!$el.length || !table) return;
            try {
                var totalRows = (typeof table.getDataCount === 'function')
                    ? table.getDataCount('active')
                    : ((table.getData('active') || []).length);
                var pageSize = table.getPageSize();
                var showAll = pageSize === true || pageSize === 'true'
                    || (typeof pageSize === 'number' && pageSize >= totalRows && totalRows > 0);
                if (totalRows === 0) {
                    $el.text('No rows');
                } else if (showAll) {
                    $el.text('Showing all ' + totalRows + ' rows');
                } else {
                    var currentPage = table.getPage() || 1;
                    var start = (currentPage - 1) * Number(pageSize) + 1;
                    var end = Math.min(currentPage * Number(pageSize), totalRows);
                    $el.text('Showing ' + start + '-' + end + ' of ' + totalRows + ' rows');
                }
            } catch (e) {
                /* ignore */
            }
        }

        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false;
        let selectedSkus = new Set(); // Track selected SKUs across all pages

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

        /** Sbid (Views) settings — shared with /ebay2/campaign-ads (ebay2_sbid_views). */
        function sbidViewsNum(key, fallback) {
            const v = parseFloat(localStorage.getItem(key));
            return isFinite(v) ? v : fallback;
        }
        function sbidViewsDir(key, fallback) {
            const v = localStorage.getItem(key);
            return (v === 'inc' || v === 'dec' || v === 'none') ? v : fallback;
        }
        let sbidViewsMinCap   = sbidViewsNum('ebay2_sbid_views_min_cap', 1);
        let sbidViewsMaxCap   = sbidViewsNum('ebay2_sbid_views_max_cap', 20);
        let sbidViewsPinkDir  = sbidViewsDir('ebay2_sbid_views_pink_dir', 'dec');
        let sbidViewsPinkStep = sbidViewsNum('ebay2_sbid_views_pink_step', 1);
        let sbidViewsGreenDir = sbidViewsDir('ebay2_sbid_views_green_dir', 'none');
        let sbidViewsGreenStep = sbidViewsNum('ebay2_sbid_views_green_step', 0);
        let sbidViewsRedDir   = sbidViewsDir('ebay2_sbid_views_red_dir', 'inc');
        let sbidViewsRedStep  = sbidViewsNum('ebay2_sbid_views_red_step', 1);
        let sbidViewsNoDecMaxEl30 = sbidViewsNum('ebay2_sbid_views_no_dec_max_el30', 0);

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

        // S Bid is driven by View VS SBID slabs (For L7 Views → S Bid).
        // eBay 2 is Parents Only — parent aggregated L7 Views / EL30.
        let currentSbidSlabRules = [];
        let currentSbidEsBid = null;

        function sbidSlabInRange(val, min, max) {
            if (min !== null && min !== undefined && min !== '' && val < parseFloat(min)) return false;
            if (max !== null && max !== undefined && max !== '' && val > parseFloat(max)) return false;
            return true;
        }

        function getCombinedSbid(rowData) {
            const el30 = parseFloat(rowData['eBay L30']) || 0;
            if (el30 <= 0) {
                const override = parseFloat(currentSbidEsBid);
                const esBid = (isFinite(override) && override > 0)
                    ? override
                    : (parseFloat(rowData.ca_suggested_bid) || 0);
                if (isFinite(esBid) && esBid > 0) {
                    return { bid: esBid, color: '#0dcaf0', skip: false, via: 'es_bid' };
                }
                return { bid: 0, color: '#6c757d', skip: true, via: 'es_bid' };
            }

            const l7Views = parseFloat(rowData.l7_views) || 0;
            const rules = currentSbidSlabRules || [];
            for (let i = 0; i < rules.length; i++) {
                const r = rules[i];
                if (sbidSlabInRange(l7Views, r.l7_views_min, r.l7_views_max)) {
                    const bid = parseFloat(r.sbid);
                    if (isFinite(bid) && bid > 0) {
                        return { bid: bid, color: '#0d6efd', skip: false };
                    }
                    return { bid: 0, color: '#6c757d', skip: true };
                }
            }
            return { bid: 0, color: '#6c757d', skip: true };
        }

        // ── Sku Link LMP (mirrors /ebay-tabulator-view; shared sku.link.lmp.* routes) ──
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
            if (row.is_parent_summary || (row.Parent && String(row.Parent).toUpperCase().startsWith('PARENT'))) return '';
            const rowSku = rowSkuForLinkLmp(row);
            let skus = row.linked_lmp_skus || [];
            if (typeof skus === 'string') { try { skus = JSON.parse(skus) || []; } catch (e) { skus = []; } }
            if (!Array.isArray(skus)) skus = [];
            if (!skus.length && rowSku) skus = [rowSku];
            const seen = new Set();
            skus = skus.filter(function (sku) {
                const norm = String(sku || '').trim().toUpperCase();
                if (!norm || seen.has(norm)) return false;
                seen.add(norm); return true;
            });
            const badges = skus.length ? skus.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const removeBtn = isSelf ? '' : `<button type="button" class="btn-close sku-link-lmp-remove" data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1"><span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}</span>`;
            }).join('') : '<span class="text-muted fst-italic">No SKUs</span>';
            return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
        }

        function linkedLmpSkuAddFormatter(cell) {
            const row = cell.getRow().getData();
            if (row.is_parent_summary || (row.Parent && String(row.Parent).toUpperCase().startsWith('PARENT'))) return '';
            const rowSku = rowSkuForLinkLmp(row);
            if (!rowSku) return '';
            return `<div class="d-flex align-items-center justify-content-center py-1">
                <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn" title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}"><i class="fas fa-plus"></i></button>
            </div>`;
        }

        function applyAffectedLinkedSkuRows(affected) {
            if (!table || !Array.isArray(affected)) return;
            const bySku = {};
            affected.forEach(function (item) { if (item?.sku) bySku[item.sku] = item.linked_lmp_skus || []; });
            table.getRows().forEach(function (row) {
                const data = row.getData();
                const sku = rowSkuForLinkLmp(data);
                if (!Object.prototype.hasOwnProperty.call(bySku, sku)) return;
                row.update({ linked_lmp_skus: bySku[sku] });
            });
            // Re-fetch /ebay2-data so LMP recomputes across the linked group
            table.replaceData('/ebay2-data?_=' + Date.now());
        }

        function removeLinkedSkuFromRow(rowData, linkedSku) {
            const sku = rowSkuForLinkLmp(rowData);
            const target = String(linkedSku || '').trim();
            if (!sku || !target) return;
            if (!confirm(`Remove LMP link between "${sku}" and "${target}"?`)) return;
            fetch(linkedSkuRemoveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ sku: sku, linked_sku: target }),
            }).then(r => r.json()).then(function (response) {
                if (!response.success) throw new Error(response.message || 'Could not remove linked SKU.');
                applyAffectedLinkedSkuRows(response.affected);
            }).catch(function (err) { alert(err.message || 'Could not remove linked SKU.'); });
        }

        function updateLinkedSkuSelectedSummary() {
            const wrap = document.getElementById('sku-link-lmp-selected-wrap');
            const listEl = document.getElementById('sku-link-lmp-selected-skus');
            const countEl = document.getElementById('sku-link-lmp-selected-count');
            const saveLabel = document.getElementById('sku-link-lmp-save-btn-label');
            const selected = Array.from(linkedSkuModalSelectedSkus);
            if (countEl) countEl.textContent = String(selected.length);
            if (saveLabel) saveLabel.textContent = selected.length > 1 ? 'Link ' + selected.length + ' SKUs' : 'Link SKU(s)';
            if (!wrap || !listEl) return;
            if (!selected.length) { wrap.classList.add('d-none'); listEl.innerHTML = ''; return; }
            wrap.classList.remove('d-none');
            listEl.innerHTML = selected.map(function (sku) {
                return `<span class="sku-link-lmp-selected-chip">${escapeHtml(sku)}<button type="button" class="sku-link-lmp-selected-remove" data-sku="${escapeHtmlAttr(sku)}" title="Remove">&times;</button></span>`;
            }).join('');
        }

        function renderLinkedSkuSuggestions(term) {
            const wrap = document.getElementById('sku-link-lmp-suggestions');
            if (!wrap) return;
            const query = String(term || '').trim();
            if (!query) { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
            clearTimeout(linkedSkuSuggestionTimer);
            linkedSkuSuggestionTimer = setTimeout(function () {
                const requestId = ++linkedSkuSuggestionRequestId;
                fetch(`${filteredSkusUrl}?sku=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json()).then(function (response) {
                    if (requestId !== linkedSkuSuggestionRequestId) return;
                    if (!response.success) throw new Error(response.message || 'Could not search SKUs.');
                    const currentSku = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();
                    const existing = new Set((Array.isArray(linkedSkuModalRow?.linked_lmp_skus) ? linkedSkuModalRow.linked_lmp_skus : []).map(s => String(s || '').trim().toUpperCase()));
                    const matches = (Array.isArray(response.skus) ? response.skus : []).map(s => String(s || '').trim())
                        .filter(function (sku) { const norm = sku.toUpperCase(); return sku && norm !== currentSku && !existing.has(norm); }).slice(0, 12);
                    if (!matches.length) { wrap.classList.add('d-none'); wrap.innerHTML = ''; return; }
                    wrap.classList.remove('d-none');
                    wrap.innerHTML = matches.map(function (sku) {
                        const checked = linkedSkuModalSelectedSkus.has(sku);
                        return `<label class="list-group-item list-group-item-action py-2 sku-link-lmp-suggestion-item d-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input sku-link-lmp-suggestion-cb" value="${escapeHtmlAttr(sku)}" ${checked ? 'checked' : ''}><span class="flex-grow-1">${escapeHtml(sku)}</span></label>`;
                    }).join('');
                }).catch(function () { if (requestId !== linkedSkuSuggestionRequestId) return; wrap.classList.add('d-none'); wrap.innerHTML = ''; });
            }, 200);
        }

        function getLinkedSkuModalSelections() {
            const selected = Array.from(linkedSkuModalSelectedSkus);
            const inputVal = String(document.getElementById('sku-link-lmp-input')?.value || '').trim();
            const sourceNorm = rowSkuForLinkLmp(linkedSkuModalRow).toUpperCase();
            if (inputVal && inputVal.toUpperCase() !== sourceNorm) {
                if (!selected.some(s => s.toUpperCase() === inputVal.toUpperCase())) selected.push(inputVal);
            }
            return selected;
        }

        function openLinkedSkuModal(rowData) {
            if (!linkedSkuModal || !rowSkuForLinkLmp(rowData)) return;
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
            if (!sourceSku) return;
            const toLink = getLinkedSkuModalSelections();
            if (!toLink.length) { alert('Select one or more SKUs from the list, or enter a SKU to link.'); return; }
            const allSkus = [sourceSku].concat(toLink);
            const uniqueSkus = []; const seen = new Set();
            allSkus.forEach(function (sku) { const norm = String(sku || '').trim().toUpperCase(); if (!norm || seen.has(norm)) return; seen.add(norm); uniqueSkus.push(String(sku).trim()); });
            if (uniqueSkus.length < 2) { alert('Select at least one SKU to link.'); return; }
            const btn = document.getElementById('sku-link-lmp-save-btn');
            const original = btn?.innerHTML || '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...'; }
            const isBulk = uniqueSkus.length > 2 || toLink.length > 1;
            const fetchPromise = isBulk
                ? fetch(linkedSkuBulkLinkUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify({ skus: uniqueSkus }) })
                : fetch(linkedSkuAddUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': skuLinkLmpCsrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: JSON.stringify({ sku: sourceSku, linked_sku: toLink[0] }) });
            fetchPromise.then(r => r.json()).then(function (response) {
                if (!response.success) throw new Error(response.message || 'Could not link SKU(s).');
                linkedSkuModalSelectedSkus = new Set();
                linkedSkuModal?.hide();
                applyAffectedLinkedSkuRows(response.affected);
            }).catch(function (err) { alert(err.message || 'Could not link SKU(s).'); })
            .finally(function () { if (btn) { btn.disabled = false; btn.innerHTML = original; } });
        }

        // Wire up the Sku Link LMP modal controls once the DOM is ready.
        $(function () {
            const modalEl = document.getElementById('skuLinkLmpModal');
            if (modalEl) linkedSkuModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            document.getElementById('sku-link-lmp-input')?.addEventListener('input', function () { renderLinkedSkuSuggestions(this.value); });
            document.getElementById('sku-link-lmp-suggestions')?.addEventListener('click', function (e) {
                const item = e.target.closest('.sku-link-lmp-suggestion-item'); if (!item) return;
                const cb = item.querySelector('.sku-link-lmp-suggestion-cb'); if (!cb || e.target === cb) return;
                cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            document.getElementById('sku-link-lmp-suggestions')?.addEventListener('change', function (e) {
                const cb = e.target.closest('.sku-link-lmp-suggestion-cb'); if (!cb) return;
                const sku = String(cb.value || '').trim(); if (!sku) return;
                if (cb.checked) linkedSkuModalSelectedSkus.add(sku); else linkedSkuModalSelectedSkus.delete(sku);
                updateLinkedSkuSelectedSummary();
            });
            document.getElementById('sku-link-lmp-selected-skus')?.addEventListener('click', function (e) {
                const btn = e.target.closest('.sku-link-lmp-selected-remove'); if (!btn) return;
                linkedSkuModalSelectedSkus.delete(String(btn.dataset.sku || '').trim());
                document.querySelectorAll('.sku-link-lmp-suggestion-cb').forEach(function (cb) { if (cb.value === btn.dataset.sku) cb.checked = false; });
                updateLinkedSkuSelectedSummary();
            });
            document.getElementById('sku-link-lmp-save-btn')?.addEventListener('click', function () { saveLinkedSkuFromModal(); });
        });

        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let blueTriangleFilterActive = false;
        let redTriangleFilterActive = false;

        function rowEbay2StockQty(data) {
            return parseFloat(data['E Stock'] || 0) || 0;
        }
        function isEbay2TabulatorParentRow(data) {
            if (!data) return false;
            if (data.is_parent_summary || data.is_parent_row) return true;
            const sku = String(data['(Child) sku'] || data.sku || '').toUpperCase();
            if (sku.includes('PARENT')) return true;
            const p = data.Parent;
            return !!(p && String(p).toUpperCase().startsWith('PARENT'));
        }
        function ebay2RowSpriceForAlert(data) {
            let sprice = parseFloat(data && data.SPRICE) || 0;
            if (typeof chPromoSpriceFromStdTPromo === 'function' && !isEbay2TabulatorParentRow(data)) {
                const calc = chPromoSpriceFromStdTPromo(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function ebay2HasBlueTriangle(data) {
            if (isEbay2TabulatorParentRow(data)) return false;
            const sprice = ebay2RowSpriceForAlert(data);
            const price = parseFloat(data['eBay Price']) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function ebay2HasRedTriangle(data) {
            if (isEbay2TabulatorParentRow(data)) return false;
            const sprice = ebay2RowSpriceForAlert(data);
            const lmp = parseFloat(data.lmp_price) || 0;
            return sprice > 0 && lmp > 0 && sprice > lmp;
        }
        function syncEbay2TriangleBadgeState() {
            $('#ebay2-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
            $('#ebay2-red-triangle-badge').css({
                outline: redTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: redTriangleFilterActive ? '2px' : ''
            });
        }
        function ebay2NormalizeParentKey(val) {
            return String(val || '').trim().replace(/^PARENT\s+/i, '').trim();
        }
        function ebay2ParentKeyFromRow(row) {
            if (!row) return '';
            const fromParent = ebay2NormalizeParentKey(row.Parent);
            const sku = String(row['(Child) sku'] || '').trim();
            if (sku.toUpperCase().includes('PARENT')) {
                return fromParent || ebay2NormalizeParentKey(sku);
            }
            return fromParent;
        }
        function ebay2YellowPlayTriangleSvg() {
            const uid = 'e2p' + Math.random().toString(36).slice(2, 9);
            return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
                '<defs>' +
                `<linearGradient id="${uid}g" x1="4" y1="2" x2="20" y2="22" gradientUnits="userSpaceOnUse">` +
                '<stop offset="0%" stop-color="#FFE566"/>' +
                '<stop offset="45%" stop-color="#FFC107"/>' +
                '<stop offset="100%" stop-color="#F59E0B"/>' +
                '</linearGradient>' +
                `<linearGradient id="${uid}s" x1="6" y1="3" x2="14" y2="14" gradientUnits="userSpaceOnUse">` +
                '<stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.75"/>' +
                '<stop offset="55%" stop-color="#FFFFFF" stop-opacity="0.12"/>' +
                '<stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>' +
                '</linearGradient>' +
                '</defs>' +
                `<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#${uid}g)"/>` +
                `<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="url(#${uid}s)"/>` +
                '<path d="M8.2 4.8c-.9-.55-2.05.1-2.05 1.15v12.1c0 1.05 1.15 1.7 2.05 1.15l10.2-6.05c.85-.5.85-1.8 0-2.3L8.2 4.8z" fill="none" stroke="#D97706" stroke-opacity="0.35" stroke-width="0.8"/>' +
                '</svg>';
        }
        // Toast notification function
        function showToast(a, b) {
            let type, message;
            if (['success', 'error', 'info', 'warning'].indexOf(String(a)) !== -1 && typeof b === 'string') {
                type = a;
                message = b;
            } else {
                message = a;
                type = b || 'info';
            }
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} border-0`;
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

        function escAttr(s) {
            if (s == null) return '';
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /** Std Prc vs Amz price (fallback eBay Price): reduce / increase → red / green. Hold (match) = no yellow dot. */
        function ebayStdPrcChangeDotMeta(stdPrc, comparePrice) {
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

        function ebayStdPrcChangeDotHtml(stdPrc, comparePrice, sku) {
            const meta = ebayStdPrcChangeDotMeta(stdPrc, comparePrice);
            if (!meta) return '';
            const tip = meta.title + ' — Std Prc (shared with Amazon)';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + escAttr(tip) + '"></span>';
        }

        /** Apply STANDARD_PRICE to a SKU row + all Sku Link LMP siblings in the grid */
        function applyEbayStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
                if (!d || d.is_parent_summary || d.is_parent_row) return;
                const rowSku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
                if (!rowSku) return;
                const rowKey = rowSku.toUpperCase();
                const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
                const inGroup = appliedSet.has(rowKey)
                    || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                    || (target && rowKey === target);
                if (!inGroup) return;
                r.update({ STANDARD_PRICE: std });
                if (typeof applyChannelSpriceFromStdChange === 'function') {
                    applyChannelSpriceFromStdChange(r, { persist: true, skip_push: false });
                }
                if (rowKey === target) primaryRow = r;
            });
            return primaryRow;
        }

        // Shared LMP modal SP box (lmp-modal-sp.js) → keep grid Std Prc in sync
        document.addEventListener('lmp-modal-sp-saved', function(e) {
            const detail = (e && e.detail) || {};
            const sku = detail.sku;
            const saved = parseFloat(detail.standard_price);
            if (!sku || !isFinite(saved) || saved <= 0) return;
            applyEbayStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
        });

        // Format helper for SKU chart Price series (matches /ebay-tabulator-view)
        function skuChartFmtVal(v) {
            return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        }

        // SKU-specific chart (layout/plugins match /ebay-tabulator-view: ref panel, median line, value labels)
        function initSkuMetricsChart() {
            const canvas = document.getElementById('skuMetricsChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }
            const ctx = canvas.getContext('2d');

            const medianLinePlugin = {
                id: 'skuMedianLine',
                afterDraw(chart) {
                    if (!skuChartFirstSeriesStats || skuChartFirstSeriesStats.median === undefined) return;
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    const c = chart.ctx;
                    const yPixel = yScale.getPixelForValue(skuChartFirstSeriesStats.median);
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
                id: 'skuValueLabels',
                afterDatasetsDraw(chart) {
                    if (!chart.data.datasets.length) return;
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    const valueFmt = (skuChartFirstSeriesStats && skuChartFirstSeriesStats.valueFmt) ? skuChartFirstSeriesStats.valueFmt : skuChartFmtVal;
                    const angle = -40 * Math.PI / 180; // diagonal so labels don't collide
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        if (val == null || !point) return;
                        const txt = String(valueFmt(val));
                        c.save();
                        c.font = 'bold 11px Inter, system-ui, sans-serif';
                        c.fillStyle = '#000000';
                        c.strokeStyle = 'rgba(255,255,255,0.95)';
                        c.lineWidth = 3;
                        c.lineJoin = 'round';
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.translate(point.x + 2, point.y - 10);
                        c.rotate(angle);
                        c.strokeText(txt, 0, 0);
                        c.fillText(txt, 0, 0);
                        c.restore();
                    });
                }
            };

            skuMetricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
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
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 36, left: 4, right: 14, bottom: 2 } },
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
                            ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                font: { size: 9 },
                                callback: function(v) {
                                    return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
                                }
                            }
                        }
                    }
                }
            });
        }

        function loadSkuMetricsData(sku, days = 30) {
            if (typeof Chart === 'undefined') {
                if (typeof loadChartJs === 'function') {
                    loadChartJs().then(function() { loadSkuMetricsData(sku, days); });
                }
                return;
            }
            if (!skuMetricsChart) initSkuMetricsChart();
            if (!skuMetricsChart) return;
            $('#skuChartLoading').show();
            $('#skuChartContainer').hide();
            $('#chart-no-data-message').hide();
            const daysNum = days === 0 || days === '0' ? 0 : (parseInt(days, 10) || 30);
            fetch(`/ebay2-metrics-history?days=${daysNum}&sku=${encodeURIComponent(sku)}`)
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
                    function clearRefPanel() {
                        skuChartFirstSeriesStats = null;
                        ['skuCol0High', 'skuCol0Med', 'skuCol0Low'].forEach(function(id) {
                            const el = document.getElementById(id);
                            if (el) el.textContent = '-';
                        });
                    }

                    if (!data || data.length === 0) {
                        clearRefPanel();
                        skuMetricsChart.data.labels = [];
                        skuMetricsChart.data.datasets.forEach(dataset => { dataset.data = []; });
                        skuMetricsChart.update('active');
                        $('#chart-no-data-message').show();
                        return;
                    }

                    const labels = data.map(d => d.date_formatted || d.date || '');
                    const isCvr = currentSkuChartMetric === 'cvr';
                    const isViews = currentSkuChartMetric === 'views';
                    const isL7 = currentSkuChartMetric === 'l7_views';
                    const intFmt = v => Math.round(Number(v) || 0).toLocaleString('en-US');
                    const cvrFmt = v => (Number(v) === v ? Number(v).toFixed(1) : v) + '%';
                    const values = isCvr
                        ? data.map(d => Number(d.cvr_percent) || 0)
                        : isViews
                            ? data.map(d => Number(d.views) || 0)
                            : isL7
                                ? data.map(d => Number(d.l7_views) || 0)
                                : data.map(d => Number(d.price) || 0);

                    const refLabels = { cvr: 'CVR%', views: 'L30 View', l7_views: 'L7 View' };
                    const refLabelText = refLabels[currentSkuChartMetric] || 'Price';
                    const refColors = { cvr: '#008000', views: '#0000FF', l7_views: '#0dcaf0' };
                    const bgColors = { cvr: 'rgba(0, 128, 0, 0.1)', views: 'rgba(0, 0, 255, 0.1)', l7_views: 'rgba(13, 202, 240, 0.1)' };
                    const refDotEl = document.getElementById('skuChartRefDot');
                    const refLabelEl = document.getElementById('skuChartRefLabel');
                    if (refLabelEl) refLabelEl.textContent = refLabelText;
                    if (refDotEl) refDotEl.style.background = refColors[currentSkuChartMetric] || '#adb5bd';

                    skuMetricsChart.data.labels = labels;
                    skuMetricsChart.data.datasets[0].data = values;
                    skuMetricsChart.data.datasets[0].label = refLabelText + (currentSkuChartMetric === 'price' ? ' (USD)' : '');
                    skuMetricsChart.data.datasets[0].borderColor = refColors[currentSkuChartMetric] || '#adb5bd';
                    skuMetricsChart.data.datasets[0].backgroundColor = bgColors[currentSkuChartMetric] || 'rgba(108,117,125,0.08)';

                    const refFmt = isCvr ? cvrFmt : (isViews || isL7) ? intFmt : skuChartFmtVal;
                    if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y) {
                        if (isCvr) skuMetricsChart.options.scales.y.ticks.callback = function(v) { return Number(v).toFixed(0) + '%'; };
                        else if (isViews || isL7) skuMetricsChart.options.scales.y.ticks.callback = function(v) { return Math.round(v).toLocaleString('en-US'); };
                        else skuMetricsChart.options.scales.y.ticks.callback = function(v) { return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US')); };
                    }
                    if (skuMetricsChart.options.plugins && skuMetricsChart.options.plugins.tooltip && skuMetricsChart.options.plugins.tooltip.callbacks) {
                        if (isCvr) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'CVR%: ' + (context.parsed.y != null ? (Number(context.parsed.y).toFixed(1) + '%') : '-'); };
                        else if (isViews) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'L30 View: ' + (context.parsed.y != null ? intFmt(context.parsed.y) : '-'); };
                        else if (isL7) skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'L7 View: ' + (context.parsed.y != null ? intFmt(context.parsed.y) : '-'); };
                        else skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) { return 'Price: ' + skuChartFmtVal(context.parsed.y || 0); };
                    }

                    const s0 = statsForArr(values);
                    setSkuRefCol(0, s0.max, s0.median, s0.min, refFmt);

                    const refRed = '#dc3545';
                    const refGray = '#6c757d';
                    const dotColors = values.map((v, i) => {
                        if (i === 0) return refGray;
                        return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                    });
                    skuChartFirstSeriesStats = { values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors, valueFmt: refFmt };
                    skuMetricsChart.data.datasets[0].pointBackgroundColor = dotColors;
                    skuMetricsChart.data.datasets[0].pointBorderColor = dotColors;
                    skuMetricsChart.data.datasets[0].pointBorderWidth = 1.5;

                    $('#skuChartContainer').show();
                    skuMetricsChart.update('active');
                })
                .catch(error => {
                    $('#skuChartLoading').hide();
                    skuChartFirstSeriesStats = null;
                    ['skuCol0High', 'skuCol0Med', 'skuCol0Low'].forEach(function(id) {
                        const el = document.getElementById(id);
                        if (el) el.textContent = '-';
                    });
                    $('#chart-no-data-message').show();
                    console.error('Error loading SKU metrics data:', error);
                });
        }

        $(document).ready(function() {
            const lmpModalEl = document.getElementById('lmpModal');
            if (lmpModalEl) {
                lmpModalEl.addEventListener('hidden.bs.modal', cleanupLmpModalBackdrop);
            }

            // Sbid (Views) modal — shared settings with /ebay2/campaign-ads
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
            $.get(@json(url('/ebay2/campaign-ads/sbid-views-rule')), function(s) {
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
                    url: @json(url('/ebay2/campaign-ads/sbid-views-rule')),
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

            // ════════════════════════════════════════════════════════════════
            // Sbid Rule modal — eBay 2 View VS SBID (ebay2_sbid_slabs).
            // Parents Only: Count + Push use parent rows (aggregated L7 / EL30).
            // ════════════════════════════════════════════════════════════════
            (function() {
                const getUrl  = @json(url('/ebay2/campaign-ads/sbid-slab-rule'));
                const saveUrl = @json(url('/ebay2/campaign-ads/sbid-slab-rule'));
                const applyUrl = @json(url('/ebay2/campaign-ads/push-sbid-slabs'));

                function numAttr(v) {
                    return (v === null || v === undefined || v === '' || isNaN(v)) ? '' : v;
                }

                function autofillSbidSlabMins(rules) {
                    if (!rules || !rules.length) return;
                    const firstMin = parseFloat(rules[0].l7_views_min);
                    const firstMax = parseFloat(rules[0].l7_views_max);
                    const diff = (isFinite(firstMin) && isFinite(firstMax)) ? (firstMax - firstMin) : null;
                    for (let i = 1; i < rules.length; i++) {
                        const prevMax = rules[i - 1].l7_views_max;
                        if (prevMax === null || prevMax === undefined || prevMax === '' || isNaN(prevMax)) break;
                        const prev = parseFloat(prevMax);
                        rules[i].l7_views_min = prev + 1;
                        if (diff !== null && diff > 0) {
                            rules[i].l7_views_max = prev + diff;
                        }
                    }
                }

                function rangeInputs(rule, key, idx) {
                    const locked = (idx > 0 && key === 'l7_views')
                        ? ' readonly tabindex="-1" style="background:#f8f9fa;"'
                        : '';
                    const minTitle = idx > 0 ? ' title="Auto: previous Max + 1"' : '';
                    const maxTitle = idx > 0 ? ' title="Auto: same difference as Rule 1"' : ' title="Sets the difference for all following slabs"';
                    return `
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                                   value="${numAttr(rule[key + '_min'])}" data-field="${key}_min"
                                   onchange="window.sbidSlabUpdate(this)" placeholder="—"${locked}${minTitle}></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                                   value="${numAttr(rule[key + '_max'])}" data-field="${key}_max"
                                   onchange="window.sbidSlabUpdate(this)" placeholder="—"${locked}${maxTitle}></td>`;
                }

                function ebay2RowEl30(d) {
                    if (!d) return 0;
                    return parseFloat(d['eBay L30'] != null ? d['eBay L30'] : (d.ebay_l30 != null ? d.ebay_l30 : d.EL30)) || 0;
                }
                function ebay2RowL7(d) {
                    if (!d) return 0;
                    return parseFloat(d.l7_views != null ? d.l7_views : d['L7 Views']) || 0;
                }
                function getAllEbay2CountRows() {
                    try {
                        if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) {
                            return allTableData;
                        }
                    } catch (e) { /* allTableData may still be in TDZ during first paint */ }
                    if (typeof table === 'undefined' || !table) return [];
                    try {
                        const fromRows = (typeof table.getRows === 'function')
                            ? (table.getRows('active') || []).map(function(r) {
                                return (r && typeof r.getData === 'function') ? r.getData() : r;
                            })
                            : [];
                        if (fromRows.length) return fromRows;
                        return table.getData('active') || table.getData() || [];
                    } catch (e) {
                        return [];
                    }
                }
                function isEbay2CountParent(d) {
                    return typeof isEbay2TabulatorParentRow === 'function'
                        ? isEbay2TabulatorParentRow(d)
                        : !!(d && (d.is_parent_row || d.is_parent_summary));
                }
                function getSbidSlabCountRows() {
                    const all = getAllEbay2CountRows();
                    const parents = all.filter(isEbay2CountParent);
                    const skus = all.filter(function(d) { return !isEbay2CountParent(d); });
                    const parentsWithSold = parents.filter(function(d) { return ebay2RowEl30(d) > 0; });
                    // Parents Only when family EL30 is present; otherwise listing SKUs
                    // (parent aggregates can be 0 while child L7 / EL30 are not).
                    if (parentsWithSold.length) return parents;
                    if (skus.length) return skus;
                    return parents.length ? parents : all;
                }

                function tallySbidSlabRows(rules, rowList) {
                    const counts = rules.map(function() { return 0; });
                    let esCount = 0;
                    (rowList || []).forEach(function(d) {
                        const el30 = ebay2RowEl30(d);
                        if (el30 <= 0) {
                            esCount++;
                            return;
                        }
                        const l7 = ebay2RowL7(d);
                        for (let i = 0; i < rules.length; i++) {
                            const r = rules[i];
                            if (sbidSlabInRange(l7, r.l7_views_min, r.l7_views_max)) {
                                counts[i]++;
                                break;
                            }
                        }
                    });
                    return { counts: counts, esCount: esCount };
                }

                function countRowsBySlab(rules) {
                    let result = tallySbidSlabRows(rules, getSbidSlabCountRows());
                    let slabTotal = result.counts.reduce(function(a, b) { return a + b; }, 0);
                    if (slabTotal === 0) {
                        const skus = getAllEbay2CountRows().filter(function(d) {
                            return !isEbay2CountParent(d);
                        });
                        if (skus.length) {
                            result = tallySbidSlabRows(rules, skus);
                            slabTotal = result.counts.reduce(function(a, b) { return a + b; }, 0);
                        }
                    }
                    const esCountEl = document.getElementById('sbid-es-bid-count');
                    if (esCountEl) esCountEl.textContent = result.esCount ? '(' + result.esCount + ' SKUs)' : '';
                    const btnCount = document.getElementById('sbid-rule-btn-count');
                    if (btnCount) {
                        const shown = slabTotal + (result.esCount || 0);
                        btnCount.textContent = shown ? '(' + shown.toLocaleString() + ')' : '';
                    }
                    return result.counts;
                }

                function renderSbidSlabRules(rules) {
                    const tbody = document.getElementById('sbid-slab-rules-body');
                    if (!tbody) return;
                    tbody.innerHTML = '';
                    if (!rules.length) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted small py-3">
                            No rules yet — click <strong>Add rule / slab</strong> to create one.</td></tr>`;
                        return;
                    }
                    autofillSbidSlabMins(rules);
                    const slabCounts = countRowsBySlab(rules);
                    rules.forEach(function(rule, i) {
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-idx', i);
                        const count = slabCounts[i] || 0;
                        tr.innerHTML = `
                            <td class="text-center text-muted small">${i + 1}</td>
                            ${rangeInputs(rule, 'l7_views', i)}
                            <td class="text-center fw-semibold" title="Parent rows in this slab">${count}</td>
                            <td><input type="number" step="0.1" min="0" class="form-control form-control-sm text-end fw-semibold"
                                       value="${numAttr(rule.sbid)}" data-field="sbid"
                                       ${i === 0 ? 'title="Changing this sets following rows to −1 each, minimum 2%"' : ''}
                                       onchange="window.sbidSlabUpdate(this)"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                        onclick="window.sbidSlabRemove(${i})" title="Remove rule">&times;</button>
                            </td>`;
                        tbody.appendChild(tr);
                    });
                }

                function readEsBidInput() {
                    const el = document.getElementById('sbid-es-bid-input');
                    if (!el || el.value === '') return null;
                    const n = parseFloat(el.value);
                    return (isFinite(n) && n > 0) ? n : null;
                }

                function writeEsBidInput(val) {
                    const el = document.getElementById('sbid-es-bid-input');
                    if (!el) return;
                    el.value = (val === null || val === undefined || val === '' || isNaN(val)) ? '' : val;
                }

                function cascadeSbidFromFirstRow(rules) {
                    if (!rules || !rules.length) return;
                    const first = parseFloat(rules[0].sbid);
                    if (!isFinite(first)) return;
                    for (let i = 1; i < rules.length; i++) {
                        rules[i].sbid = Math.max(2, first - i);
                    }
                }

                window.sbidSlabUpdate = function(el) {
                    const tr = el.closest('tr');
                    const idx = parseInt(tr.getAttribute('data-idx'), 10);
                    const field = el.dataset.field;
                    if (!currentSbidSlabRules[idx]) return;
                    currentSbidSlabRules[idx][field] = (el.value === '' ? null : parseFloat(el.value));
                    if (field === 'sbid' && idx === 0) {
                        cascadeSbidFromFirstRow(currentSbidSlabRules);
                        renderSbidSlabRules(currentSbidSlabRules);
                        if (table) table.redraw(true);
                        scheduleEbay2SbidAutopush();
                        return;
                    }
                    if (field === 'l7_views_min' || field === 'l7_views_max') {
                        renderSbidSlabRules(currentSbidSlabRules);
                    }
                    if (table) table.redraw(true);
                    scheduleEbay2SbidAutopush();
                };

                window.sbidSlabRemove = function(idx) {
                    currentSbidSlabRules.splice(idx, 1);
                    renderSbidSlabRules(currentSbidSlabRules);
                    if (table) table.redraw(true);
                    scheduleEbay2SbidAutopush();
                };

                $(document).on('click', '#sbid-slab-add-rule-btn', function() {
                    currentSbidSlabRules.push({
                        l7_views_min: null, l7_views_max: null, sbid: 2.1
                    });
                    cascadeSbidFromFirstRow(currentSbidSlabRules);
                    renderSbidSlabRules(currentSbidSlabRules);
                    scheduleEbay2SbidAutopush();
                });

                function loadSbidSlabRules() {
                    $.ajax({
                        url: getUrl,
                        method: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            currentSbidSlabRules = (data && Array.isArray(data.rules)) ? data.rules : [];
                            currentSbidEsBid = (data && data.es_bid != null && data.es_bid !== '') ? parseFloat(data.es_bid) : null;
                            if (!isFinite(currentSbidEsBid) || currentSbidEsBid <= 0) currentSbidEsBid = null;
                            writeEsBidInput(currentSbidEsBid);
                            renderSbidSlabRules(currentSbidSlabRules);
                            if (table) table.redraw(true);
                        },
                        error: function(xhr) {
                            console.error('[Sbid Rule] load failed', xhr.status, xhr.responseText);
                        }
                    });
                }

                let ebay2SbidAutopushTimer = null;
                let ebay2SbidAutopushBusy = false;

                function setEbay2AutopushLabel(html) {
                    const btn = document.getElementById('sbid-slab-apply-btn');
                    if (btn) btn.innerHTML = html;
                }

                function collectEbay2AutopushSkus() {
                    const skus = [];
                    getSbidSlabCountRows().forEach(function(rd) {
                        const sku = rd['(Child) sku'] || rd.sku;
                        if (!sku) return;
                        const res = getCombinedSbid(rd);
                        if (res && !res.skip && res.bid > 0) skus.push(sku);
                    });
                    return skus;
                }

                function autoPushEbay2Sbid() {
                    const statusEl = document.getElementById('sbid-slab-rule-status');
                    const errEl = document.getElementById('sbid-slab-rule-err');
                    if (errEl) errEl.classList.add('d-none');
                    if (!currentSbidSlabRules.length) return;
                    const skus = collectEbay2AutopushSkus();
                    if (!skus.length) {
                        if (statusEl) statusEl.textContent = 'Autopush: no listings match a slab or 0-sold ES Bid.';
                        return;
                    }
                    if (ebay2SbidAutopushBusy) return;
                    ebay2SbidAutopushBusy = true;
                    setEbay2AutopushLabel('<i class="fas fa-spinner fa-spin me-1"></i>Pushing…');
                    if (statusEl) statusEl.textContent = 'Autopush: ' + skus.length + ' listing(s)…';
                    $.ajax({
                        url: applyUrl,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        contentType: 'application/json',
                        data: JSON.stringify({ skus: skus }),
                        success: function(resp) {
                            ebay2SbidAutopushBusy = false;
                            setEbay2AutopushLabel('<i class="fas fa-bolt me-1"></i>Autopush');
                            const s = resp.success || 0, f = resp.failed || 0, sk = resp.skipped || 0;
                            if (statusEl) statusEl.textContent = 'Autopush: ' + s + ' pushed · ' + f + ' failed · ' + sk + ' skipped';
                            if (typeof showToast === 'function') {
                                if (f === 0) showToast('Autopush: S Bid sent for ' + s + ' listing(s)', 'success');
                                else showToast('Autopush: ' + s + ' pushed, ' + f + ' failed', 'error');
                            }
                        },
                        error: function(xhr) {
                            ebay2SbidAutopushBusy = false;
                            setEbay2AutopushLabel('<i class="fas fa-bolt me-1"></i>Autopush');
                            const msg = (xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText || 'Autopush failed';
                            if (errEl) {
                                errEl.textContent = msg;
                                errEl.classList.remove('d-none');
                            }
                        }
                    });
                }

                function applySavedEbay2SbidRule(resp) {
                    if (resp && resp.rule && Array.isArray(resp.rule.rules)) currentSbidSlabRules = resp.rule.rules;
                    if (resp && resp.rule && resp.rule.es_bid != null) {
                        currentSbidEsBid = parseFloat(resp.rule.es_bid);
                        if (!isFinite(currentSbidEsBid) || currentSbidEsBid <= 0) currentSbidEsBid = null;
                    } else {
                        currentSbidEsBid = readEsBidInput();
                    }
                    writeEsBidInput(currentSbidEsBid);
                    if (table) table.redraw(true);
                }

                function saveEbay2SbidRules(thenPush) {
                    const errEl = document.getElementById('sbid-slab-rule-err');
                    if (errEl) errEl.classList.add('d-none');
                    const btn = document.getElementById('sbid-slab-rule-save-btn');
                    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';
                    }
                    $.ajax({
                        url: saveUrl,
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        contentType: 'application/json',
                        data: JSON.stringify({
                            rules: (currentSbidSlabRules || []).map(function(r) {
                                return {
                                    label: r.label || '',
                                    l7_views_min: r.l7_views_min,
                                    l7_views_max: r.l7_views_max,
                                    sbid: r.sbid
                                };
                            }),
                            es_bid: readEsBidInput(),
                            _token: csrf
                        }),
                        success: function(resp) {
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                                setTimeout(function() { btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule'; }, 1200);
                            }
                            applySavedEbay2SbidRule(resp);
                            if (thenPush) autoPushEbay2Sbid();
                        },
                        error: function(xhr) {
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                            }
                            const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                                || xhr.responseText
                                || ('HTTP ' + xhr.status);
                            if (errEl) {
                                errEl.textContent = 'Error: ' + msg;
                                errEl.classList.remove('d-none');
                            }
                        }
                    });
                }

                function scheduleEbay2SbidAutopush() {
                    clearTimeout(ebay2SbidAutopushTimer);
                    ebay2SbidAutopushTimer = setTimeout(function() {
                        saveEbay2SbidRules(true);
                    }, 800);
                }

                $(document).on('input change', '#sbid-es-bid-input', function() {
                    currentSbidEsBid = readEsBidInput();
                    if (table) table.redraw(true);
                    scheduleEbay2SbidAutopush();
                });

                const sbidModalEl = document.getElementById('sbidRuleModal');
                if (sbidModalEl) {
                    sbidModalEl.addEventListener('show.bs.modal', function() {
                        writeEsBidInput(currentSbidEsBid);
                        renderSbidSlabRules(currentSbidSlabRules);
                    });
                }

                $('#sbid-slab-rule-save-btn').on('click', function() {
                    saveEbay2SbidRules(true);
                });

                $(document).on('ebay2-tabulator-data-loaded', function() {
                    if (currentSbidSlabRules && currentSbidSlabRules.length) {
                        renderSbidSlabRules(currentSbidSlabRules);
                    }
                });

                loadSbidSlabRules();
            })();

            // ---- Edit Links (Buyer / Seller) ----
            function ebay2LinksNotify(msg, type) {
                type = type || 'info';
                var bg = type === 'success' ? 'bg-success' : (type === 'error' || type === 'danger' ? 'bg-danger' : 'bg-info');
                var $c = $('.toast-container');
                if (!$c.length) {
                    $c = $('<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1090;"></div>').appendTo('body');
                }
                var $t = $('<div class="toast align-items-center text-white ' + bg + ' border-0" role="alert"><div class="d-flex"><div class="toast-body">' + msg + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
                $c.append($t);
                var bsT = new bootstrap.Toast($t[0]);
                bsT.show();
                setTimeout(function() { $t.remove(); }, 5000);
            }

            let ebay2EditLinksRow = null;
            window.openEbay2EditLinksModal = function(row) {
                ebay2EditLinksRow = row;
                const d = row.getData();
                $('#ebay2EditLinksSku').text(d['(Child) sku'] || '');
                $('#ebay2SellerLinkInput').val(d['S Link'] || '');
                $('#ebay2BuyerLinkInput').val(d['B Link'] || '');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay2EditLinksModal')).show();
            };

            $('#ebay2SaveLinksBtn').on('click', function() {
                if (!ebay2EditLinksRow) return;
                const sku = ebay2EditLinksRow.getData()['(Child) sku'];
                const sellerLink = $('#ebay2SellerLinkInput').val().trim();
                const buyerLink = $('#ebay2BuyerLinkInput').val().trim();
                const $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');
                $.ajax({
                    url: '/ebay2/save-links',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        seller_link: sellerLink,
                        buyer_link: buyerLink
                    },
                    success: function(res) {
                        if (res && res.success) {
                            ebay2EditLinksRow.update({
                                'S Link': res.seller_link || '',
                                'B Link': res.buyer_link || ''
                            }).then(function() {
                                ebay2EditLinksRow.reformat();
                            }).catch(function() {
                                ebay2EditLinksRow.reformat();
                            });
                            ebay2LinksNotify('Links saved successfully', 'success');
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('ebay2EditLinksModal')).hide();
                        } else {
                            ebay2LinksNotify((res && res.message) || 'Failed to save links', 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                        ebay2LinksNotify(msg, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Save');
                    }
                });
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

            function syncEbay2DiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#ebay2-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('eBay price:');
                    $inp.attr('placeholder', 'Each row — click Apply');
                    $inp.prop('disabled', true);
                    $inp.removeAttr('max');
                    $inp.val('');
                } else {
                    $('#ebay2-discount-type-block').removeClass('d-none');
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

            function syncEbay2PriceModeUi() {
                const $btn = $('#ebay2-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncEbay2DiscountBarForMode();
                if (selectColumn) selectColumn.show();
                if (decreaseModeActive) {
                    $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                    return;
                }
                $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                    .html('<i class="fas fa-exchange-alt"></i> Prc Mode');
                $('#discount-input-container').hide();
                updateSelectedCount();
                updateSelectAllCheckbox();
            }

            $('#ebay2-price-mode-btn').on('click', function() {
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
                syncEbay2PriceModeUi();
            });

            // Select all checkbox handler (matching Amazon approach)
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
                
                // Add or remove all filtered SKUs from the selected set
                filteredData.forEach(row => {
                    const sku = row['(Child) sku'];
                    if (sku) {
                        if (isChecked) {
                            selectedSkus.add(sku);
                        } else {
                            selectedSkus.delete(sku);
                        }
                    }
                });
                
                // Update all visible checkboxes
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                
                updateSelectedCount();
            });

            // Individual checkbox handler
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

            // Apply discount button
            $('#apply-discount-btn').on('click', function() {
                applyDiscount();
            });

            // Apply discount on Enter key
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) {
                    applyDiscount();
                }
            });

            /*
             * Target ROI% / Target GPFT% bulk apply (eBay2, margin = row.percentage or EbayTwo table)
             * -----------------------------------------------------------------------------
             * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
             * target. eBay2's server-side SGPFT formula (EbayTwoController::saveSpriceToDatabase
             * line 1165) includes shipping:
             *     SGPFT% = ((sprice * margin − ship − lp) / sprice) * 100
             *     SROI%  = ((sprice * margin − ship − lp) / lp)     * 100   (same shape used elsewhere)
             *   → sprice = (lp * (1 + ROI%/100)  + ship) / margin
             *   → sprice = (lp + ship) / (margin − GPFT%/100)
             * Each save goes through the existing saveSpriceWithRetry() Promise pipeline so
             * SPRICE_STATUS (processing → saved / error) and the server-recomputed
             * SGPFT / SPFT / SROI values stay in sync exactly like Decrease / Increase / Same Price.
             * Rounding is plain 2-decimal — no .99 / .49 retail snapping — because snapping
             * would shift the achieved SROI / SGPFT off the user-typed target.
             * Ship = normal ProductMaster ship (same as eBay 1) via Ship_productmaster.
             */
            function ebay2ApplyTargetBackSolve(computeFn, labelPrefix) {
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
                    const ship = parseFloat(row['Ship_productmaster'] ?? row['ebay2_ship']) || 0;
                    const marginRaw = parseFloat(row['percentage']);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : EBAY2_TAKEHOME;

                    const computed = computeFn(lp, ship, margin);
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
                        showToast(`${labelPrefix} too high — must be less than each row's take-home margin (typically < 85%).`, 'error');
                    } else {
                        showToast('No selected rows have a usable LP > 0', 'warning');
                    }
                    return;
                }

                let okCount = 0;
                let errCount = 0;
                const total = tasks.length;

                tasks.forEach(t => {
                    saveSpriceWithRetry(t.sku, t.newSprice, t.tableRow)
                        .then(() => {
                            okCount++;
                            if (okCount + errCount === total) {
                                let note = '';
                                if (skippedNoLp > 0)       note += ` (${skippedNoLp} skipped — no LP)`;
                                if (skippedHigh.length)    note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
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
                                if (skippedNoLp > 0)       note += ` (${skippedNoLp} skipped — no LP)`;
                                if (skippedHigh.length)    note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
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

                // Target displayed SNROI (Amazon NROI shape), not gross SGROI:
                //   ((sprice×margin − ship − lp) − sprice×Ads%/100) / lp × 100 = Target
                //   -> sprice = (lp × (1 + Target/100) + ship) / (margin − Ads%/100)
                const adsFrac = (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) / 100;
                const roiMultiplier = 1 + (targetRoiPct / 100);
                ebay2ApplyTargetBackSolve(function (lp, ship, margin) {
                    const netMargin = margin - adsFrac;
                    if (netMargin <= 0) return null;
                    return (lp * roiMultiplier + ship) / netMargin;
                }, `Target SNROI ${targetRoiPct}%`);
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

                const targetFraction = targetGpftPct / 100;
                ebay2ApplyTargetBackSolve(function (lp, ship, margin) {
                    const denom = margin - targetFraction;
                    if (denom <= 0) return null; // signals "target ≥ margin" skip
                    return (lp + ship) / denom;
                }, `Target GPFT ${targetGpftPct}%`);
            });

            $('#target-roi-input').on('keypress', function (e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });
            $('#target-gpft-input').on('keypress', function (e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Badge filter click handlers - Work together with other filters
            $('.sold-filter-badge[data-filter="zero"], #zero-sold-count-badge').on('click', function() {
                zeroSoldFilterActive = !zeroSoldFilterActive;
                moreSoldFilterActive = false;
                applyFilters();
            });

            $('.sold-filter-badge[data-filter="sold"]').on('click', function() {
                moreSoldFilterActive = !moreSoldFilterActive;
                zeroSoldFilterActive = false;
                applyFilters();
            });
            $('#ebay2-blue-triangle-badge').on('click', function() {
                blueTriangleFilterActive = !blueTriangleFilterActive;
                if (blueTriangleFilterActive) redTriangleFilterActive = false;
                applyFilters();
            });
            $('#ebay2-red-triangle-badge').on('click', function() {
                redTriangleFilterActive = !redTriangleFilterActive;
                if (redTriangleFilterActive) blueTriangleFilterActive = false;
                applyFilters();
            });

            // Chart days filter
            $('#chart-days-filter').on('change', function() {
                const days = $(this).val();
                loadMetricsData(days);
            });

            // SKU chart days filter (same as /ebay-tabulator-view)
            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                const daysNum = parseInt(days, 10);
                const rangeLabel = daysNum === 0 ? 'Lifetime' : 'L' + daysNum;
                const metricLabels = { cvr: 'CVR%', views: 'L30 View', l7_views: 'L7 View' };
                const metricLabel = metricLabels[currentSkuChartMetric] || 'Price';
                $('#skuChartModalSuffix').text(metricLabel + ' (Rolling ' + rangeLabel + ' · PT)');
                if (currentSku) loadSkuMetricsData(currentSku, daysNum || 0);
            });

            // Update selected count display
            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#discount-input-container').toggle(count > 0);
            }

            // Update select all checkbox state (matching Amazon approach)
            function updateSelectAllCheckbox() {
                if (!table) return;
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
                
                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }
                
                // Get all filtered SKUs
                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
                
                // Check if all filtered SKUs are selected
                const allFilteredSelected = filteredSkus.size > 0 && 
                    Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
                
                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }


            // Retry function for saving SPRICE (only 1 retry for eBay)
            function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
                return new Promise((resolve, reject) => {
                    // Update status to processing
                    if (row) {
                        row.update({ SPRICE_STATUS: 'processing' });
                    }
                    
                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            sku: sku,
                            sprice: sprice,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            // Update calculated fields instantly
                            if (row) {
                                row.update({
                                    SPRICE: sprice,
                                    SPFT: response.spft_percent,
                                    SROI: response.sroi_percent,
                                    SGPFT: response.sgpft_percent,
                                    SPRICE_STATUS: (parseFloat(sprice) > 0) ? 'queued' : 'saved'
                                });
                                // Re-render the row so the Accept button's data-price
                                // reflects the NEW SPRICE (otherwise push uses the old value).
                                row.reformat();
                            }
                            if (typeof enqueueChannelPushSpriceAfterSave === 'function') {
                                enqueueChannelPushSpriceAfterSave(sku, sprice, row);
                            }
                            resolve(response);
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                            console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`, errorMsg);
                            
                            // Only retry once (retryCount < 1)
                            if (retryCount < 1) {
                                console.log(`Retrying SKU ${sku} in 2 seconds...`);
                                setTimeout(() => {
                                    saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                        .then(resolve)
                                        .catch(reject);
                                }, 2000);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                // Update status to error
                                if (row) {
                                    row.update({ SPRICE_STATUS: 'error' });
                                }
                                reject({ error: true, xhr: xhr });
                            }
                        }
                    });
                });
            }


            // Apply discount to selected SKUs
            function applyDiscount() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                    return;
                }

                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                const discountValue = parseFloat($('#discount-percentage-input').val());
                const discountType = $('#discount-type-select').val();
                if (!samePriceModeActive) {
                    if (isNaN(discountValue) || discountValue <= 0) {
                        showToast('Please enter a valid discount value', 'error');
                        return;
                    }
                }

                const allData = table.getData('all');
                let updatedCount = 0;
                let errorCount = 0;
                const totalSkus = selectedSkus.size;
                const isIncrease = increaseModeActive;
                const appliedAsSamePrice = samePriceModeActive;

                allData.forEach(row => {
                    const isParent = row.Parent && row.Parent.startsWith('PARENT');
                    if (isParent) return;

                    const sku = row['(Child) sku'];
                    if (selectedSkus.has(sku)) {
                        let newSPrice;
                        if (samePriceModeActive) {
                            const p = parseFloat(row['eBay Price']);
                            newSPrice = isNaN(p) ? 0 : p;
                        } else {
                            const currentPrice = parseFloat(row['eBay Price']) || 0;
                            if (currentPrice <= 0) return;

                            if (discountType === 'percentage') {
                                if (isIncrease) {
                                    newSPrice = currentPrice * (1 + discountValue / 100);
                                } else {
                                    newSPrice = currentPrice * (1 - discountValue / 100);
                                }
                            } else {
                                if (isIncrease) {
                                    newSPrice = currentPrice + discountValue;
                                } else {
                                    newSPrice = currentPrice - discountValue;
                                }
                            }
                            newSPrice = Math.max(0.01, newSPrice);
                        }

                        const originalSPrice = parseFloat(row['SPRICE']) || 0;

                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['(Child) sku'] === sku;
                        });

                        if (tableRow) {
                            tableRow.update({
                                SPRICE: newSPrice,
                                SPRICE_STATUS: 'processing'
                            });
                        }

                        saveSpriceWithRetry(sku, newSPrice, tableRow)
                            .then((response) => {
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
                                                ? `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed`
                                                : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                            'error'
                                        );
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ SPRICE: originalSPrice });
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(
                                        appliedAsSamePrice
                                            ? `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed`
                                            : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                        'error'
                                    );
                                }
                            });
                    }
                });
            }

            // Event delegation for eye button clicks (add to SKU column formatter)
            let allTableData = []; // Store all unfiltered data
            let ebay2ExpandedParent = null; // parent key when triangle expand is active
            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'Parent',
                    skuField: '(Child) sku',
                    isParentRow: isEbay2TabulatorParentRow,
                    parentKeyFromRow: ebay2ParentKeyFromRow,
                    getTable: function() { return table; },
                    getDataset: function() { return allTableData; },
                });
            }

            function ebay2ShowExpandedParent(parentKey) {
                const key = ebay2NormalizeParentKey(parentKey);
                if (!key || !table || !allTableData.length) return;
                const keyU = key.toUpperCase();
                const parentRow = allTableData.find(function(r) {
                    return isEbay2TabulatorParentRow(r) && ebay2ParentKeyFromRow(r).toUpperCase() === keyU;
                });
                const childRows = allTableData.filter(function(r) {
                    if (isEbay2TabulatorParentRow(r)) return false;
                    return ebay2NormalizeParentKey(r.Parent).toUpperCase() === keyU;
                });
                const displayData = childRows.slice();
                if (parentRow) {
                    parentRow._expanded = true;
                    displayData.push(parentRow);
                }
                table.clearFilter(true);
                table.clearSort();
                table.setData(displayData).then(function() {
                    updateCalcValues();
                    updateSummary();
                });
            }

            function ebay2CollapseExpandedParent() {
                ebay2ExpandedParent = null;
                if (allTableData && allTableData.length) {
                    allTableData.forEach(function(r) { if (r) r._expanded = false; });
                }
                applyFilters();
            }

            function ebay2EscHtmlAttr(val) {
                if (val == null || val === '') return '';
                return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function ebay2UpdateForecastNrp(data, onSuccess, onFail) {
                onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
                onFail = typeof onFail === 'function' ? onFail : function() {};
                $.post('{{ route("update.forecast.data") }}', {
                    sku: data.sku,
                    parent: data.parent != null ? String(data.parent) : '',
                    column: 'NR',
                    value: data.value,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(function(res) {
                    if (res.success) {
                        onSuccess();
                    } else {
                        console.warn('NRP not saved:', res.message);
                        onFail();
                    }
                }).fail(function(err) {
                    console.error('NRP save failed:', err);
                    if (typeof showToast === 'function') showToast('Error saving NRP.', 'error');
                    onFail();
                });
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
            
            table = new Tabulator("#ebay2-table", {
                ajaxURL: "/ebay2-data",
                ajaxResponse: function(url, params, response) {
                    // Extract the data array from the response object
                    allTableData = response.data || []; // Store unfiltered data
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    console.log('API Response - Total rows:', allTableData.length);
                    
                    // Calculate total L30 for verification
                    let totalL30 = 0;
                    let parentCount = 0;
                    allTableData.forEach(row => {
                        const sku = row['(Child) sku'] || '';
                        if (sku.toUpperCase().includes('PARENT')) {
                            parentCount++;
                        } else {
                            totalL30 += parseFloat(row['eBay L30'] || 0);
                        }
                    });
                    console.log('Total eBay L30 from API:', totalL30, '(excluding', parentCount, 'PARENT rows)');
                    
                    return response.data || [];
                },
                ajaxSorting: false,
                layout: "fitDataStretch",
                rowHeight: 36,
                height: "100%",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500, 1000, true], // true = All (same as eBay 1)
                paginationCounter: function() {
                    if (typeof ebay2UpdatePaginationCounter === 'function') ebay2UpdatePaginationCounter();
                    return '';
                },
                columnCalcs: "both",
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "SKU Count",
                            "first": "First",
                            "first_title": "First Page",
                            "last": "Last",
                            "last_title": "Last Page",
                            "prev": "Prev",
                            "prev_title": "Prev Page",
                            "next": "Next",
                            "next_title": "Next Page"
                        }
                    }
                },
                initialSort: [{
                    column: "SCVR",
                    dir: "asc"
                }],
                rowFormatter: function(row) {
                    const el = row.getElement();
                    const d = row.getData();
                    if (isEbay2TabulatorParentRow(d) || (window.isPmParentRowData && window.isPmParentRowData(d))) {
                        el.classList.add('parent-row');
                        el.classList.add('pm-parent-row');
                    } else {
                        el.classList.remove('parent-row');
                        el.classList.remove('pm-parent-row');
                    }
                },
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: false
                    },
                    {
                        title: "P",
                        field: "_parent_expand",
                        headerSort: false,
                        hozAlign: "center",
                        frozen: true,
                        width: 36,
                        minWidth: 36,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const playIcon = ebay2YellowPlayTriangleSvg();
                            if (!isEbay2TabulatorParentRow(rowData)) {
                                return '<span class="ebay2-parent-sku-dot no-parent" title="">' + playIcon + '</span>';
                            }
                            const parentKey = ebay2ParentKeyFromRow(rowData);
                            if (!parentKey) {
                                return '<span class="ebay2-parent-sku-dot no-parent" title="No parent key">' + playIcon + '</span>';
                            }
                            const parentEsc = String(parentKey).replace(/"/g, '&quot;');
                            const isExpanded = (ebay2ExpandedParent &&
                                ebay2NormalizeParentKey(ebay2ExpandedParent).toUpperCase() === parentKey.toUpperCase())
                                || rowData._expanded === true;
                            const expandedCls = isExpanded ? ' is-expanded' : '';
                            return `<span class="ebay2-parent-sku-dot ebay2-parent-expand-btn${expandedCls}"
                                        data-parent="${parentEsc}"
                                        title="Show all SKUs for parent: ${parentEsc}">${playIcon}</span>`;
                        }
                    },

                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: true,
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
                        width: 80
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold",
                        // Full SKU tooltip on hover — explicit so it works even when the SKU text
                        // is truncated by the narrower column width.
                        tooltip: function(e, cell) {
                            return cell.getValue() || '';
                        },
                        frozen: true,
                        width: 175,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();
                            
                            // Ratings display with star icon (like FBA/Amazon format)
                            const ratingDisplay = (rowData.rating && rowData.rating > 0) 
                                ? ` <i class="fa fa-star" style="color: orange;"></i> ${rowData.rating}` 
                                : '';
                            
                            // Truncate the SKU text with ellipsis when it exceeds the narrower column
                            // width; full text remains visible via the column's tooltip on hover.
                            let html = `<span style="display: inline-block; max-width: 105px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;" title="${sku}">${sku}${ratingDisplay}</span>`;
                            
                            // Copy button
                            html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 6px; font-size: 14px; vertical-align: middle;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                            
                            return html;
                        }
                    },
                    // {
                    //     title: "Ratings",
                    //     field: "rating",
                    //     hozAlign: "center",
                    //     editor: "input",
                    //     tooltip: "Enter rating between 0 and 5",
                    //     width: 80
                    // },
                    {
                        title: "Links",
                        field: "links_column",
                        frozen: true,
                        width: 55,
                        visible: true,
                        hozAlign: "center",
                        headerSort: false,
                        tooltip: "Double-click to add / edit links",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['B Link'] || '';
                            const sellerLink = rowData['S Link'] || '';

                            let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                            if (sellerLink) {
                                html += `<a href="${sellerLink}" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>`;
                            }
                            if (buyerLink) {
                                html += `<a href="${buyerLink}" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>`;
                            }
                            if (!sellerLink && !buyerLink) {
                                html += '<span class="text-muted" style="font-size:12px;">-</span>';
                            }
                            html += '</div>';
                            return html;
                        },
                        cellDblClick: function(e, cell) {
                            openEbay2EditLinksModal(cell.getRow());
                        }
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 60,
                        sorter: "number",
                        headerTooltip: "INV vs prior day (PT): green = up, red = down, gray = same / no prior.",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            const yesterday = parseFloat(rowData.inv_yesterday);
                            const hasYesterday = isFinite(yesterday);
                            let dotColor = '#6c757d';
                            let dotTip = hasYesterday
                                ? ('Same as prior day (' + Math.round(yesterday) + ')')
                                : 'No prior-day INV yet';
                            if (hasYesterday) {
                                if (value > yesterday) {
                                    dotColor = '#28a745';
                                    dotTip = 'Up vs prior day (' + Math.round(yesterday) + ')';
                                } else if (value < yesterday) {
                                    dotColor = '#a00211';
                                    dotTip = 'Down vs prior day (' + Math.round(yesterday) + ')';
                                }
                            }
                            const dot = !isParent
                                ? `<span title="${dotTip}" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor}; margin-left: 3px; vertical-align: middle;"></span>`
                                : '';
                            return `<span style="white-space: nowrap; display: inline-flex; align-items: center;">${Math.round(value)}${dot}</span>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 65,
                        sorter: "number",
                        headerTooltip: "OV L30 vs prior day (PT): green = up, red = down, gray = same / no prior.",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            const yesterday = parseFloat(rowData.l30_yesterday);
                            const hasYesterday = isFinite(yesterday);
                            let dotColor = '#6c757d';
                            let dotTip = hasYesterday
                                ? ('Same as prior day (' + Math.round(yesterday) + ')')
                                : 'No prior-day OV L30 yet';
                            if (hasYesterday) {
                                if (value > yesterday) {
                                    dotColor = '#28a745';
                                    dotTip = 'Up vs prior day (' + Math.round(yesterday) + ')';
                                } else if (value < yesterday) {
                                    dotColor = '#a00211';
                                    dotTip = 'Down vs prior day (' + Math.round(yesterday) + ')';
                                }
                            }
                            const dot = !isParent
                                ? `<span title="${dotTip}" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor}; margin-left: 3px; vertical-align: middle;"></span>`
                                : '';
                            return `<span style="white-space: nowrap; display: inline-flex; align-items: center;">${Math.round(value)}${dot}</span>`;
                        }
                    },

                     {
                        title: "Dil",
                        field: "E Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Listing Dil (Σ OV L30 ÷ Σ INV by variation) — same value Dil vs PRMT uses.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const dil = (typeof chPromoListingDil === 'function')
                                ? chPromoListingDil(rowData)
                                : (function() {
                                    const INV = parseFloat(rowData.INV) || 0;
                                    const OVL30 = parseFloat(rowData['L30']) || 0;
                                    return INV === 0 ? 0 : (OVL30 / INV) * 100;
                                })();

                            if (!(dil > 0)) return '<span style="color: #a00211; font-weight: 600;">0%</span>';

                            let color = '';
                            if (dil < 16.66) color = '#a00211';
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                            else if (dil >= 25 && dil < 50) color = '#28a745';
                            else color = '#e83e8c';

                            return `<span style="color: ${color}; font-weight: 600;" title="Listing Dil — same as Dil vs PRMT">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        formatter: function(cell) {
                            var sku = cell.getRow().getData()['(Child) sku'];
                            var value = cell.getValue() || 'REQ';
                            return `<select class="form-select form-select-sm kw-nrl-dropdown" 
                                        data-sku="${sku}" data-field="NRL"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                                    </select>`;
                        },
                        cellClick: function(e, cell) { e.stopPropagation(); },
                        width: 70
                    },
                    {
                        title: "E L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 30,
                        sorter: "number"
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
                        title: "E Stock",
                        field: "E Stock",
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
                    // {
                    //     title: "eBay L60",
                    //     field: "eBay L60",
                    //     hozAlign: "center",
                    //     width: 100,
                    //     visible: false
                    // },
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
                        width: 60,
                        visible: false
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
                            // ≤3.5 keep 1 decimal; >3.5 round to whole number
                            const fmtCvr = (n) => (n > 3.5 ? String(Math.round(n)) : n.toFixed(1)) + '%';
                            let arrowHtml = '';
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            if (!isParent) {
                                let arrowColor = '#6c757d';
                                let arrowIcon = 'fa-minus';
                                if (val > cvr60 + tol) {
                                    // CVR 30 > CVR 60 (improving)
                                    arrowColor = '#28a745';
                                    arrowIcon = 'fa-arrow-up';
                                } else if (val < cvr60 - tol) {
                                    // CVR 60 > CVR 30 (declining)
                                    arrowColor = '#a00211';
                                    arrowIcon = 'fa-arrow-down';
                                }
                                arrowHtml =
                                    ` <span title="CVR 30 vs CVR 60: ${fmtCvr(cvr60)}" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                            }
                            const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' :
                                (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            const sku = rowData['(Child) sku'] || '';
                            // Click the % value to open CVR chart (same as /ebay-tabulator-view)
                            const valueHtml = (sku && !isParent)
                                ? `<span class="view-sku-chart" data-sku="${sku}" data-metric="cvr" title="View CVR chart" style="color: ${color}; font-weight: 600; cursor: pointer;">${fmtCvr(val)}</span>`
                                : `<span style="color: ${color}; font-weight: 600;">${fmtCvr(val)}</span>`;
                            return `<span style="white-space: nowrap; display: inline-flex; align-items: center; gap: 2px;">${valueHtml}${arrowHtml}</span>`;
                        },
                        width: 65
                    },

                    {
                        title: "View",
                        field: "views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            let color = '';
                            
                            // getViewColor logic from inc/dec page
                            if (value >= 30) color = '#28a745'; // green
                            else color = '#a00211'; // red
                            
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
                            const rowData = cell.getRow().getData();
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith('PARENT');
                            
                            // Don't show dropdown for parent rows
                            // if (isParent) {
                            //     return '';
                            // }
                            
                            // Get value and handle null/undefined/empty cases
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value.trim() === '') {
                                value = 'REQ';
                            }
                            
                            let bgColor = '#f8f9fa';
                            let textColor = '#000';
                            
                            if (value === 'REQ') {
                                bgColor = '#28a745';
                                textColor = 'white';
                            } else if (value === 'NR') {
                                bgColor = '#dc3545';
                                textColor = 'white';
                            }
                            
                            return `<select class="form-select form-select-sm nr-req-dropdown" 
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
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs Amz price.",
                        editor: "input",
                        width: 70,
                        sorter: "number",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent_summary || d.is_parent_row) return false;
                            const sku = String(d['(Child) sku'] || '');
                            return sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary || rowData.is_parent_row) return '';
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const sku = rowData['(Child) sku'] || '';
                            const amzPrice = parseFloat(rowData['A Price']) || 0;
                            const ebayPrice = parseFloat(rowData['eBay Price']) || 0;
                            const comparePrice = amzPrice > 0 ? amzPrice : ebayPrice;
                            const dot = ebayStdPrcChangeDotHtml(std, comparePrice, sku);
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + ('$' + std.toFixed(2)) + '</span>';
                        }
                    },

                    {
                        title: "Price",
                        field: "eBay Price",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "Price vs yesterday (PT): green = up, red = down, gray = same / no yesterday. Red triangle = Price > LMP. Click price or dot for chart.",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const rowData = cell.getRow().getData();
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);
                            const sku = rowData['(Child) sku'] || '';
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            const overLmp = lmpPrice > 0 && value > lmpPrice;
                            const redTri = overLmp
                                ? '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="Price $'
                                    + value.toFixed(2) + ' &gt; LMP $' + lmpPrice.toFixed(2) + '"></i>'
                                : '';
                            const yesterday = parseFloat(rowData.price_yesterday);
                            const hasYesterday = isFinite(yesterday) && yesterday > 0;

                            // Green if price > yesterday, red if <, gray otherwise (same / missing)
                            let dotColor = '#6c757d';
                            let dotTip = hasYesterday
                                ? ('Same as yesterday ($' + yesterday.toFixed(2) + ')')
                                : 'No yesterday price yet';
                            if (hasYesterday && value > 0) {
                                if (value > yesterday) {
                                    dotColor = '#28a745';
                                    dotTip = 'Up vs yesterday ($' + yesterday.toFixed(2) + ')';
                                } else if (value < yesterday) {
                                    dotColor = '#a00211';
                                    dotTip = 'Down vs yesterday ($' + yesterday.toFixed(2) + ')';
                                }
                            }
                            const dotBtn = (sku && !isParent)
                                ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="price" title="${dotTip} — click for Price chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>`
                                : '';

                            if (value === 0) {
                                if (sku && !isParent) {
                                    return `<span style="white-space: nowrap; display: inline-flex; align-items: center; gap: 2px;"><span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: #a00211; font-weight: 600; cursor: pointer;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>${dotBtn}</span>`;
                                }
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }

                            const priceFormatted = '$' + value.toFixed(2);
                            const priceColor = overLmp ? '#dc3545' : 'inherit';
                            const priceWeight = overLmp ? '600' : 'normal';
                            if (sku && !isParent) {
                                return `<span style="white-space: nowrap; display: inline-flex; align-items: center; gap: 2px;"><span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: ${priceColor}; font-weight: ${priceWeight}; cursor: pointer;">${priceFormatted}${redTri}</span>${dotBtn}</span>`;
                            }
                            if (overLmp) {
                                return `<span style="color: #dc3545; font-weight: 600;">${priceFormatted}${redTri}</span>`;
                            }
                            return priceFormatted;
                        },
                        width: 80
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
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
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
                            const aNet = ebay2ComputeNetRoi(aRow.getData(), 'eBay Price');
                            const bNet = ebay2ComputeNetRoi(bRow.getData(), 'eBay Price');
                            return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                                 - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                        },
                        formatter: function(cell) {
                            const percent = ebay2ComputeNetRoi(cell.getRow().getData(), 'eBay Price');
                            if (percent === null || !isFinite(percent)) return '';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0, n = 0;
                            data.forEach(r => {
                                const v = ebay2ComputeNetRoi(r, 'eBay Price');
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
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },


                     {
                        title: "NPFT",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const ads = (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) : 0;
                            return ((parseFloat(aRow.getData()['GPFT%'] || 0) - ads) - (parseFloat(bRow.getData()['GPFT%'] || 0) - ads));
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const ads = (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) : 0;
                            // NPFT% = GPFT% − Ads% (channel TACOS)
                            const percent = (parseFloat(rowData['GPFT%'] || 0)) - ads;
                            const _st = (window.MetricPctColors && MetricPctColors.styleFor('npft', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        bottomCalc: function(values, data) {
                            const ads = (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0) : 0;
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
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (window.ParentExpand) {
                                const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, { dataset: allTableData });
                                if (avgHtml !== null) return avgHtml;
                            }
                            const lmpPrice = cell.getValue();
                            const sku = rowData['(Child) sku'];
                            const totalCompetitors = rowData.lmp_entries_total || 0;
                            const currentPrice = parseFloat(rowData['eBay Price'] || 0);
                            const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                            const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));
                            const skuAttr = escapeHtmlAttr(sku || '');
                            const countHtml = totalCompetitors > 0
                                ? ` <span style="color:#007bff;font-weight:500;font-size:12px;">(${totalCompetitors})</span>`
                                : '';

                            // Compact like eBay 1: $34.50 (9) — click opens competitors
                            if (lmpPrice) {
                                const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                                return `<a href="#" class="view-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color: inherit; text-decoration: none; cursor: pointer; white-space: nowrap;"
                                    title="Open LMP competitors">
                                    <span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">$${parseFloat(lmpPrice).toFixed(2)}</span>${countHtml}
                                </a>`;
                            }

                            if (totalCompetitors > 0) {
                                return `<a href="#" class="view-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px;"
                                    title="Open LMP competitors">(${totalCompetitors})</a>`;
                            }

                            return `<a href="#" class="view-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px;"
                                title="Add LMP competitors">—</a>`;
                        },
                        width: 78
                    },
                    {
                        title: "Sku Link LMP",
                        field: "linked_lmp_skus",
                        hozAlign: "left",
                        headerHozAlign: "center",
                        width: 200,
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
                        width: 50,
                        headerSort: false,
                        formatter: linkedLmpSkuAddFormatter,
                        cellClick: function (e, cell) {
                            if (e.target.closest('.sku-link-lmp-add-btn')) {
                                e.preventDefault();
                                e.stopPropagation();
                                openLinkedSkuModal(cell.getRow().getData());
                            }
                        },
                    },
                    // PRMT % / CPN % — ebay2_dil_vs_prmt / ebay2_cvr_vs_cpn (independent of eBay1)
                    ...(typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : []),
                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editable: false,
                        headerTooltip: "S PRC = Std × (1 − (PRMT% + CPN%)/100). Read-only. Blue triangle = S PRC ≠ Price. Red triangle / red text = S PRC > LMP.",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                            let sprice = isNaN(spriceNum) ? 0 : spriceNum;
                            const isParent = rowData.is_parent_summary
                                || rowData.is_parent_row
                                || (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT'));
                            if (typeof chPromoSpriceFromStdTPromo === 'function' && !isParent) {
                                const calc = chPromoSpriceFromStdTPromo(rowData);
                                if (calc > 0) sprice = calc;
                            }
                            if (!(sprice > 0)) {
                                return '';
                            }

                            const formattedValue = '$' + Number(sprice).toFixed(2);
                            const lmp = parseFloat(rowData.lmp_price) || 0;
                            const ebayPrice = parseFloat(rowData['eBay Price']) || 0;
                            const differsFromPrice = ebayPrice > 0
                                && Math.round(sprice * 100) !== Math.round(ebayPrice * 100);
                            const overLmp = lmp > 0 && sprice > lmp;
                            const blueTri = differsFromPrice
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + Number(sprice).toFixed(2) + ' ≠ Price $' + ebayPrice.toFixed(2) + '"></i>'
                                : '';
                            const redTri = overLmp
                                ? '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + Number(sprice).toFixed(2) + ' &gt; LMP $' + lmp.toFixed(2) + '"></i>'
                                : '';

                            let priceHtml = formattedValue;
                            if (overLmp) {
                                priceHtml = '<span style="color:#dc3545;font-weight:600;">' + formattedValue + '</span>';
                            } else if (hasCustomSprice === false) {
                                priceHtml = '<span style="color:#0d6efd;font-weight:500;">' + formattedValue + '</span>';
                            }

                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                                + priceHtml + blueTri + redTri + '</span>';
                        },
                        width: 92
                    },

                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        hozAlign: "center",
                        headerTooltip: "S GPFT from S PRC (SPRICE), eBay 1 take-home formula.",
                        formatter: function(cell) {
                            const percent = ebay2ComputeSgpftFromSprice(cell.getRow().getData());
                            if (percent === null || !isFinite(percent)) return '';

                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "S GROI",
                        field: "SGROI",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "S GROI from S PRC (SPRICE), eBay 1 take-home formula.",
                        formatter: function(cell) {
                            const percent = ebay2ComputeSgroiFromSprice(cell.getRow().getData());
                            if (percent === null || !isFinite(percent)) return '';

                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "SNROI",
                        field: "SROI",
                        hozAlign: "center",
                        headerTooltip: "SNROI from S PRC using eBay 2 Ads%.",
                        sorter: function(a, b, aRow, bRow) {
                            const aNet = ebay2ComputeNetRoi(aRow.getData(), 'SPRICE');
                            const bNet = ebay2ComputeNetRoi(bRow.getData(), 'SPRICE');
                            return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                                 - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                        },
                        formatter: function(cell) {
                            const percent = ebay2ComputeNetRoi(cell.getRow().getData(), 'SPRICE');
                            if (percent === null || !isFinite(percent)) return '';

                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80
                    },
                    {
                        title: "SNPFT",
                        field: "SPFT",
                        hozAlign: "center",
                        sorter: "number",
                        headerTooltip: "SNPFT = S GPFT − eBay 2 Ads%, with S GPFT from S PRC.",
                        formatter: function(cell) {
                            const sgpft = ebay2ComputeSgpftFromSprice(cell.getRow().getData());
                            if (sgpft === null || !isFinite(sgpft)) return '';
                            const ads = parseFloat(EBAY2_CHANNEL_ADS_PCT) || 0;
                            const percent = sgpft - ads;

                            const _st = (window.MetricPctColors && MetricPctColors.styleFor('npft', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 80,
                        visible: false
                    },


                    {
                        title: "NRP",
                        field: "nrp",
                        hozAlign: "center",
                        sorter: "string",
                        headerSort: true,
                        width: 56,
                        minWidth: 52,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && String(rowData.Parent).startsWith('PARENT');
                            if (isParent) {
                                return '<span style="color: #999;">-</span>';
                            }
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') {
                                value = rowData.nrp;
                            }
                            if (value === null || value === undefined) {
                                value = '';
                            } else {
                                value = String(value).trim().toUpperCase();
                            }
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') {
                                value = 'REQ';
                            }
                            const sku = String(rowData['(Child) sku'] || '');
                            const parent = rowData.Parent != null ? String(rowData.Parent) : '';
                            let dotColor = '#22c55e';
                            let tip = 'REQ';
                            if (value === 'NR') {
                                dotColor = '#dc3545';
                                tip = '2BDC';
                            } else if (value === 'LATER') {
                                dotColor = '#facc15';
                                tip = 'LATER';
                            }
                            const skuAttr = ebay2EscHtmlAttr(sku);
                            const parentAttr = ebay2EscHtmlAttr(parent);
                            return (
                                '<div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="' +
                                ebay2EscHtmlAttr(tip + ' (click to change)') + '">' +
                                '<span class="nrp-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>' +
                                '<select class="form-select form-select-sm nrp-nr-select position-absolute top-0 start-0 w-100 h-100" ' +
                                'data-sku="' + skuAttr + '" data-parent="' + parentAttr + '" ' +
                                'aria-label="NRP: ' + ebay2EscHtmlAttr(tip) + '">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>REQ</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>2BDC</option>' +
                                '<option value="LATER"' + (value === 'LATER' ? ' selected' : '') + '>LATER</option>' +
                                '</select></div>'
                            );
                        },
                        cellClick: function(e, cell) { e.stopPropagation(); }
                    },
                  
                    // {
                    //     title: "Listed",
                    //     field: "Listed",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },
                    // {
                    //     title: "Live",
                    //     field: "Live",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },

                    // === Campaign-Ads columns (ES BID / C BID / PROMOTE) ===
                    // Same source & formatters as /ebay2/campaign-ads. SKU-wise via listing_id; rows
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
                        field: "ca_suggested_bid",
                        hozAlign: "center",
                        width: 90,
                        headerTooltip: "EL30 = 0 → ES Bid. Otherwise Sbid Rule slabs (For L7 Views). Parents Only — uses parent aggregated L7 Views / EL30.",
                        sorter: function(a, b, aRow, bRow) {
                            return getCombinedSbid(aRow.getData()).bid - getCombinedSbid(bRow.getData()).bid;
                        },
                        formatter: function(cell) {
                            const res = getCombinedSbid(cell.getRow().getData());
                            if (res.skip) {
                                const tip = res.via === 'es_bid' ? 'EL30 is 0 but no ES Bid' : 'No matching Sbid Rule slab';
                                return `<span class="text-muted" title="${tip}" style="font-size:11px;">—</span>`;
                            }
                            const color = res.via === 'es_bid' ? '#0dcaf0' : (res.bid > EBAY2_CHANNEL_ADS_PCT ? '#a00211' : '#28a745');
                            const tip = res.via === 'es_bid' ? 'ES Bid (EL30 = 0)' : 'Sbid Rule slab';
                            return `<span title="${tip}" style="color:${color}; font-weight:700;">${Math.round(res.bid)}%</span>`;
                        }
                    },
                    {
                        title: "PROMOTE",
                        field: "ca_promote_with_ad",
                        hozAlign: "center",
                        headerTooltip: "eBay Promotion eligibility status (from /ebay2/campaign-ads)",
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
                    }
                ]
            });

            $(document).on('change', '#ebay2-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.searchRows('(Child) sku', '=', sku);
                const row = rows && rows.length ? rows[0] : null;
                const prevRaw = row ? String(row.getData().nrp ?? '').trim().toUpperCase() : '';
                const prevSelect = (prevRaw === 'NR' || prevRaw === 'LATER') ? prevRaw : 'REQ';
                ebay2UpdateForecastNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {
                        if (row) {
                            row.update({ nrp: newValue }, true);
                            const nrCell = row.getCells().find(function(c) { return c.getField() === 'nrp'; });
                            if (nrCell) nrCell.reformat();
                        }
                        if (typeof showToast === 'function') showToast('NRP saved', 'success');
                    },
                    function() {
                        $el.val(prevSelect);
                    }
                );
            });

            // SKU Search functionality
            $('#sku-search, #parent-search').on('keyup', function() {
                table.setFilter([
                    { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                    { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
                ]);
            });

            // NR/REQ dropdown change handler
            $(document).on('change', '.nr-req-dropdown', function() {
                const $select = $(this);
                const value = $select.val();
                
                // Find the row and get SKU
                const $cell = $select.closest('.tabulator-cell');
                const row = table.getRow($cell.closest('.tabulator-row')[0]);
                
                if (!row) {
                    console.error('Could not find row');
                    return;
                }
                
                const sku = row.getData()['(Child) sku'];
                
                // Update the row data
                row.update({nr_req: value});
                
                // Save to database using listing_ebaytwo endpoint (saves to ebay_two_listing_status table)
                $.ajax({
                    url: '/listing_ebaytwo/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('NR/REQ saved successfully for', sku, 'value:', value);
                            const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ? 'NR updated' : 'Status cleared');
                            showToast('success', message);
                        } else {
                            showToast('error', response.message || 'Failed to save status');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr.responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
                    }
                });
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                // Std Prc — shared amazon_data_view.STANDARD_PRICE (same as /amazon-tabulator-view)
                if (field === 'STANDARD_PRICE') {
                    const sku = data['(Child) sku'];
                    const std = parseFloat(value);
                    if (!sku || !isFinite(std) || std <= 0) {
                        row.update({ STANDARD_PRICE: null });
                        return;
                    }
                    row.update({ STANDARD_PRICE: std });
                    if (typeof applyChannelSpriceFromStdChange === 'function') {
                        applyChannelSpriceFromStdChange(row, { persist: false });
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
                            applyEbayStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            showToast(n > 1
                                ? ('Std Prc saved for ' + n + ' linked SKUs')
                                : 'Std Prc saved', 'success');
                        },
                        error: function() {
                            showToast('Failed to save Std Prc', 'error');
                        }
                    });
                    return;
                }

                // Validate and save ratings field (must be between 0 and 5)
                if (field === 'rating') {
                    var numValue = parseFloat(value);
                    if (isNaN(numValue) || numValue < 0 || numValue > 5) {
                        alert('Ratings must be a number between 0 and 5');
                        cell.setValue(data.rating || 0); // Revert to original value
                        return;
                    }
                    
                    // Save rating to database
                    $.ajax({
                        url: '/update-ebay-rating',
                        method: 'POST',
                        data: {
                            sku: data['(Child) sku'],
                            rating: numValue,
                            _token: $('meta[name=\"csrf-token\"]').attr('content')
                        },
                        success: function(response) {
                            console.log('Rating saved successfully');
                            showToast('success', 'Rating updated successfully');
                            // Update the row data
                            row.update({rating: numValue});
                        },
                        error: function(xhr) {
                            console.error('Error saving rating:', xhr.responseText);
                            showToast('error', 'Error saving rating');
                            cell.setValue(data.rating || 0); // Revert on error
                        }
                    });
                    return;
                }

                if (field === 'SPRICE') {
                    // Save SPRICE and recalculate SPFT, SROI
                    const row = cell.getRow();
                    row.update({ SPRICE_STATUS: 'processing' });
                    
                    saveSpriceWithRetry(data['(Child) sku'], value, row)
                        .then((response) => {
                            showToast('success', 'S PRC saved — eBay 2 push queued (page close OK)');
                        })
                        .catch((error) => {
                            showToast('error', 'Failed to save SPRICE');
                        });
                } else if (field === 'Listed' || field === 'Live') {
                    // Save Listed/Live status
                    $.ajax({
                        url: '/update-listed-live-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            field: field,
                            value: value
                        },
                        success: function(response) {
                            showToast('success', field + ' status updated successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to update ' + field + ' status');
                        }
                    });
                }
            });

            // Apply filters
            function applyFilters() {
                // Leaving expand mode whenever filters re-run
                if (ebay2ExpandedParent) {
                    ebay2ExpandedParent = null;
                    if (allTableData && allTableData.length) {
                        allTableData.forEach(function(r) { if (r) r._expanded = false; });
                    }
                }

                const viewModeFilter = $('#view-mode-filter').val() || 'parent';
                const inventoryFilter = $('#inventory-filter').val();
                const el30Filter = $('#el30-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const roiFilter = $('#roi-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const cvrTrendFilter = $('#cvr-trend-filter').val();
                const spriceFilter = $('#sprice-filter').val();
                const dilFilter = $('#dil-filter').val() || 'all';
                const priceMin = parseFloat($('#price-min-filter').val());
                const priceMax = parseFloat($('#price-max-filter').val());

                function runEbay2Filters() {
                table.clearFilter(true);

                // View mode: ALL (Parent + SKU) · Parents · SKU
                if (viewModeFilter === 'parent') {
                    table.addFilter(function(data) {
                        return isEbay2TabulatorParentRow(data);
                    });
                } else if (viewModeFilter === 'sku') {
                    table.addFilter(function(data) {
                        return !isEbay2TabulatorParentRow(data);
                    });
                }

                // INV filter — same as /ebay-tabulator-view (Shopify INV, not eBay Stock)
                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV'] || 0) || 0) === 0;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV'] || 0) || 0) > 0;
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

                if (nrlFilter !== 'all') {
                    table.addFilter(function(data) {
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
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;
                        
                        // GPFT% is stored as a number, not a string with %
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
                        const roiVal = parseFloat(data['ROI%']) || 0;
                        if (roiFilter === 'lt40') return roiVal < 40;
                        if (roiFilter === '40-75') return roiVal >= 40 && roiVal < 75;
                        if (roiFilter === '75-125') return roiVal >= 75 && roiVal < 125;
                        if (roiFilter === 'gt125') return roiVal >= 125;
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    const slabs = { low: 3.5, mid: 7, high: 13, yellow_start: 0.01, pink_after: 13.01 };
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
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                // Price (Prc / eBay Price) min–max
                if (!isNaN(priceMin) || !isNaN(priceMax)) {
                    table.addFilter(function(data) {
                        const price = parseFloat(data['eBay Price'] || 0) || 0;
                        if (!isNaN(priceMin) && price < priceMin) return false;
                        if (!isNaN(priceMax) && price > priceMax) return false;
                        return true;
                    });
                }

                // Badge Filters (only E Stock > 0)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = parseFloat(data['E Stock'] || 0) || 0;
                        return ebayL30 === 0 && estock > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = parseFloat(data['E Stock'] || 0) || 0;
                        return ebayL30 > 0 && estock > 0;
                    });
                }

                if (blueTriangleFilterActive) {
                    table.addFilter(function(data) {
                        return ebay2HasBlueTriangle(data);
                    });
                }
                if (redTriangleFilterActive) {
                    table.addFilter(function(data) {
                        return ebay2HasRedTriangle(data);
                    });
                }

                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const dil = (typeof chPromoListingDil === 'function')
                            ? chPromoListingDil(data)
                            : (function() {
                                const INV = parseFloat(data['INV'] || 0);
                                const OVL30 = parseFloat(data['L30'] || 0);
                                return INV === 0 ? 0 : (OVL30 / INV) * 100;
                            })();

                        if (dilFilter === 'red') return dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                updateCalcValues();
                updateSummary();
                // Update select-all + pagination counter after filter is applied (same as eBay 1)
                setTimeout(function() {
                    if (typeof ebay2UpdatePaginationCounter === 'function') ebay2UpdatePaginationCounter();
                    updateSelectAllCheckbox();
                }, 100);
                } // end runEbay2Filters

                // Restore full dataset after parent-expand (setData replaced it with a subset)
                if (allTableData && allTableData.length && table.getDataCount() !== allTableData.length) {
                    table.setData(allTableData).then(runEbay2Filters);
                } else {
                    runEbay2Filters();
                }
            }

            $(document).on('click', '.ebay2-parent-expand-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parentKey = String($(this).data('parent') || '').trim();
                if (!parentKey) return;
                if (ebay2ExpandedParent &&
                    ebay2NormalizeParentKey(ebay2ExpandedParent).toUpperCase() === ebay2NormalizeParentKey(parentKey).toUpperCase()) {
                    ebay2CollapseExpandedParent();
                    return;
                }
                ebay2ExpandedParent = parentKey;
                ebay2ShowExpandedParent(parentKey);
            });

            $('#view-mode-filter, #inventory-filter, #el30-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter, #dil-filter').on('change', function() {
                applyFilters();
            });

            $('#growth-sign-filter').on('change', function() {
                applyFilters();
            });

            // Price (Prc) min–max — apply on change / Enter; light debounce while typing
            let priceRangeFilterTimer = null;
            $('#price-min-filter, #price-max-filter').on('change', function() {
                applyFilters();
            }).on('input', function() {
                clearTimeout(priceRangeFilterTimer);
                priceRangeFilterTimer = setTimeout(function() { applyFilters(); }, 350);
            }).on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(priceRangeFilterTimer);
                    applyFilters();
                }
            });

            // No-op kept for backward compatibility with other call sites.
            function applySectionColumnVisibility(_sectionVal) {
                if (table && table.redraw) table.redraw(true);
            }
            
            // Update PFT% and ROI% calc values
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let sumLp = 0;
                
                data.forEach(row => {
                    const profit = parseFloat(row['Total_pft']) || 0;
                    const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                    // Only add if both values are > 0 (matching inc/dec page logic)
                    if (profit > 0 && salesL30 > 0) {
                        totalProfit += profit;
                        totalSales += salesL30;
                    }
                    sumLp += parseFloat(row['LP_productmaster']) || 0;
                });
                
                
            }

            // Update summary badges - use filtered data for accurate counts
            function updateSummary() {
                // Use active (filtered) data for all counts to match what's actually visible
                const data = table.getData('active');
                
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaL30 = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;

                data.forEach(row => {
                    const estock = parseFloat(row['E Stock'] || 0) || 0;
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);
                    
                    if (estock > 0) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaL30 += ebayL30;

                        // Count sold
                        if (ebayL30 === 0) zeroSoldCount++;
                        else moreSoldCount++;
                    }
                });

                // Calculate weighted average price
                let totalWeightedPrice = 0;
                let totalL30 = 0;
                data.forEach(row => {
                    if (parseFloat(row['E Stock'] || 0) > 0) {
                        const price = parseFloat(row['eBay Price'] || 0);
                        const l30 = parseFloat(row['eBay L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;

                // Calculate views, CVR, and average L7 views (E Stock > 0)
                let totalViews = 0;
                let totalL7Views = 0;
                let l7Count = 0;
                data.forEach(row => {
                    if (parseFloat(row['E Stock'] || 0) > 0) {
                        totalViews += parseFloat(row.views || 0);
                        totalL7Views += parseFloat(row.l7_views || 0);
                        l7Count++;
                    }
                });
                // CVR = (orders-API L30 units / Σ views) × 100. Numerator is the same
                // fixed value the S Qty badge shows (Σ ebay2_order_items.quantity for
                // period='l30') — orders-API ground truth, same source the master
                // page's Qty cell uses. Previously this used the per-row eBay L30 sum
                // from ebay_2_metrics, which lags the Orders API. Denominator stays
                // the page sum of 'views' across rows with E Stock > 0.
                const avgCVR = totalViews > 0 ? (ORDERS_L30_TOTAL_QTY / totalViews * 100) : 0;
                const avgL7Views = l7Count > 0 ? (totalL7Views / l7Count) : 0;
                const prevAvgL7Views = avgL7ViewsGlobal;
                avgL7ViewsGlobal = avgL7Views;

                // Update all badges
                $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
                $('#more-sold-count').text(moreSoldCount.toLocaleString());

                $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
                // Sales / GPFT% / GROI% are fixed server values from the same real L30 orders
                // /ebay2/daily-sales uses, so this page agrees with that page (the per-SKU
                // datasheet is tax-excluded, lags the Orders API, and only counts filtered rows).
                
                $('#total-sales-amt-badge').text('Sales: $' + Math.round(ORDERS_L30_TOTAL_SALES).toLocaleString());
                $('#avg-gpft-badge').text('GPFT: ' + Math.round(ORDERS_L30_GPFT) + '%');
                $('#groi-percent-badge').text('GROI: ' + Math.round(ORDERS_L30_GROI) + '%');
                // NPFT% = GPFT% − Ads%. NROI% = (GPFT$ − Ad Spend) / COGS × 100 (Amazon formula).
                $('#npft-percent-badge').text('NPFT: ' + Math.round(ORDERS_L30_GPFT - EBAY2_CHANNEL_ADS_PCT) + '%');
                const nroiBadge = (ORDERS_L30_COGS > 0)
                    ? ((ORDERS_L30_PFT - EBAY2_AD_SPEND) / ORDERS_L30_COGS) * 100
                    : ORDERS_L30_NROI;
                $('#nroi-percent-badge').text('NROI: ' + Math.round(nroiBadge) + '%');

                
                $('#avg-price-badge').text('Prc: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(2) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#avg-l7-views-badge').text('L7: ' + avgL7Views.toFixed(1));

                let blueTriangleCount = 0;
                let redTriangleCount = 0;
                const triangleRows = (allTableData && allTableData.length) ? allTableData : table.getData();
                triangleRows.forEach(function(row) {
                    if (ebay2HasBlueTriangle(row)) blueTriangleCount++;
                    if (ebay2HasRedTriangle(row)) redTriangleCount++;
                });
                $('#ebay2-blue-triangle-badge').html(
                    '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
                );
                $('#ebay2-red-triangle-badge').html(
                    '<i class="fas fa-exclamation-triangle"></i> ' + redTriangleCount.toLocaleString()
                );
                syncEbay2TriangleBadgeState();

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

            function classifyEbay2Column(field, title) {
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
                    /^(eBay Price|STANDARD_PRICE|GPFT%|PFT %|ROI%|NROI|lmp_price|linked_lmp_skus|linked_lmp_sku_add|SPRICE|SGPFT|SPFT|SGROI|SROI|E Dil%|SCVR|CVR_45|CVR_60|prmt_pct|cpn_pct|dsc|appr|push_prc)$/i.test(f) ||
                    /\b(prc|price|std\s*prc|gpft|npft|groi|nroi|lmp|t\s*prc|target|s\s*prc|s\s*gpft|s\s*pft|s\s*groi|sroi|dil|cvr|prmt|cpn|t\s*promo|dsc|appr|push\s*prc)\b/i.test(tl) ||
                    /^\+$/i.test(t)
                ) {
                    return 'pricing';
                }

                if (
                    /^(image_path|Parent|\(Child\) sku|INV|L30|rating|links_column|E Stock|nr_req|nrp|NRL|NR|eBay L30|eBay L45|eBay L60|growth_percent)$/i.test(f) ||
                    /\b(image|parent|sku|inv|ov\s*l30|links|rating|stock|nr\/req|nrp|nrl|nra|growth|e\s*l\d+)\b/i.test(tl)
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
                            if (!def.field) return;
                            if (def.field === '_parent_expand' || def.field === '_select') return;

                            const rawTitle = def.title || def.field;
                            const title = String(rawTitle).replace(/<[^>]*>/g, '').trim() || def.field;
                            const cat = classifyEbay2Column(def.field, title);

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
                            if (!def.field || def.field === '_parent_expand' || def.field === '_select') return;
                            if (savedVisibility[def.field] === false) {
                                col.hide();
                            }
                        });
                    });
            }

            // Wait for table to be built
            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
                
            });

            table.on('dataLoaded', function() {
                if (typeof chPromoInvalidateListingDilCache === 'function') chPromoInvalidateListingDilCache();
                updateCalcValues();
                updateSummary();
                $(document).trigger('ebay2-tabulator-data-loaded');
                // Refresh checkboxes to reflect selectedSkus set (matching Amazon approach)
                setTimeout(function() {
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
            });

            // Also initialize tooltips when table is rendered (matching Amazon approach)
            table.on('renderComplete', function() {
                if (typeof ebay2UpdatePaginationCounter === 'function') ebay2UpdatePaginationCounter();
                setTimeout(function() {
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
            });

            table.on('pageLoaded', function() {
                if (typeof ebay2UpdatePaginationCounter === 'function') ebay2UpdatePaginationCounter();
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            });

            // Toggle column from dropdown (group header + individual)
            (function() {
                var colMenu = document.getElementById("column-dropdown-menu");
                if (!colMenu) return;
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


            // Toggle functionality removed - only PMT Spend L30 shown now
            document.addEventListener("click", function(e) {
                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn")) {
                    const sku = e.target.getAttribute("data-sku");
                    
                    // Copy to clipboard
                    navigator.clipboard.writeText(sku).then(function() {
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    }).catch(function(err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = sku;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    });
                }

                // View SKU chart (Price or CVR from column / SKU info icon) — same as /ebay-tabulator-view
                if (e.target.closest('.view-sku-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const el = e.target.closest('.view-sku-chart');
                    const sku = el.getAttribute('data-sku');
                    currentSkuChartMetric = (el.getAttribute('data-metric') || 'price');
                    currentSku = sku;
                    $('#modalSkuName').text(sku);
                    $('#sku-chart-days-filter').val('30');
                    const metricLabels = { cvr: 'CVR%', views: 'L30 View', l7_views: 'L7 View' };
                    const metricLabel = metricLabels[currentSkuChartMetric] || 'Price';
                    $('#skuChartModalSuffix').text(metricLabel + ' (Rolling L30 · PT)');
                    $('#skuChartLoading').show();
                    $('#skuChartContainer').hide();
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 30);
                    $('#skuMetricsModal').modal('show');
                }
            });

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            }

            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                '(Child) sku': 'SKU',
                'INV': 'INV',
                'L30': 'L30',
                'E Dil%': 'Dil%',
                'eBay L30': 'eBay L30',
                'eBay L60': 'eBay L60',
                'growth_percent': 'Growth',
                'eBay Price': 'eBay Price',
                'lmp_price': 'LMP',
                'linked_lmp_skus': 'Sku Link LMP',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'ROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'E Stock': 'E Stock',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SNPFT',
                'SGROI': 'S GROI',
                'SROI': 'SNROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'SCVR',
                'ebay2_ship': 'eBay2 Ship',
                'LP_productmaster': 'LP',
                'ca_suggested_bid': 'ES BID',
                'ca_bid_percentage': 'C BID',
                'ca_promote_with_ad': 'PROMOTE'
            };

            // Build export columns list
            function buildExportColumnsList() {
                const container = document.getElementById('export-columns-list');
                container.innerHTML = '';
                
                const columns = table.getColumns().filter(col => {
                    const field = col.getField();
                    return field && exportColumnMapping[field] && field !== '_select';
                });

                columns.forEach(col => {
                    const field = col.getField();
                    const displayName = exportColumnMapping[field];
                    
                    const div = document.createElement('div');
                    div.className = 'form-check mb-2';
                    div.innerHTML = `
                        <input class="form-check-input export-column-checkbox" type="checkbox" 
                               value="${field}" id="export-col-${field}" checked>
                        <label class="form-check-label" for="export-col-${field}">
                            ${displayName}
                        </label>
                    `;
                    container.appendChild(div);
                });
            }

            // Select all export columns
            $('#select-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', true);
            });

            // Deselect all export columns
            $('#deselect-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', false);
            });

            // Confirm export
            $('#confirm-export-btn').on('click', function() {
                const selectedColumns = [];
                $('.export-column-checkbox:checked').each(function() {
                    selectedColumns.push($(this).val());
                });

                if (selectedColumns.length === 0) {
                    showToast('error', 'Please select at least one column to export');
                    return;
                }

                // Build export URL with selected columns
                const columnsParam = encodeURIComponent(JSON.stringify(selectedColumns));
                const exportUrl = `/export-ebay2-pricing-data?columns=${columnsParam}`;
                
                // Close modal and trigger download
                $('#exportModal').modal('hide');
                window.location.href = exportUrl;
            });

            // When export modal is shown, build the columns list
            $('#exportModal').on('show.bs.modal', function() {
                if (table) {
                    buildExportColumnsList();
                }
            });

            // Import Ratings Modal Handler
            $('#importForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#csvFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a CSV file');
                    return;
                }

                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');

                const uploadBtn = $('#uploadBtn');
                uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/import-ebay-ratings',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success || 'Ratings imported successfully');
                        
                        // Reload table data
                        setTimeout(() => {
                            table.setData('/ebay2-data?_=' + Date.now());
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        const errorMsg = xhr.responseJSON?.error || 'Failed to import ratings';
                        showToast('error', errorMsg);
                    }
                });
            });
        });

        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null,
            linkedLmpSkus: []
        };

        function refreshEbay2TableData() {
            if (typeof table !== 'undefined' && table) {
                // Preserve the horizontal scroll position so the table doesn't
                // "slide" back to the left when data is reloaded (e.g. after a push).
                const holder = table.element ? table.element.querySelector('.tabulator-tableHolder') : null;
                const scrollLeft = holder ? holder.scrollLeft : 0;

                table.replaceData('/ebay2-data?_=' + Date.now()).then(function() {
                    const h = table.element ? table.element.querySelector('.tabulator-tableHolder') : null;
                    if (h) h.scrollLeft = scrollLeft;
                }).catch(function() {});
            }
        }

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

        function loadEbayCompetitorsModal(sku, linkedLmpSkus) {
            $('#lmpSku').text(sku);
            
            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');

            currentLmpData.sku = sku;
            currentLmpData.linkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
            
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
            
            // Fetch LMP data (merged across Sku Link LMP group — same as LMP column)
            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                traditional: true,
                data: {
                    sku: sku,
                    linked_lmp_skus: currentLmpData.linkedLmpSkus
                },
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
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${item.title || 'N/A'}
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-ebay-lmp-btn" 
                                    data-id="${item.id}" 
                                    data-item-id="${item.item_id}" 
                                    data-price="${item.total_price}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
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
            let linkedSkus = $(this).data('linked-skus') || [];
            if (typeof linkedSkus === 'string') {
                try { linkedSkus = JSON.parse(linkedSkus) || []; } catch (err) { linkedSkus = []; }
            }
            if (!Array.isArray(linkedSkus) || !linkedSkus.length) {
                if (table && table.getRows) {
                    const r = table.getRows().find(row => row.getData()['(Child) sku'] === sku);
                    const fromRow = r ? r.getData().linked_lmp_skus : null;
                    if (Array.isArray(fromRow)) linkedSkus = fromRow;
                }
            }
            loadEbayCompetitorsModal(sku, linkedSkus);
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
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
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
                        
                        // Clear form
                        $('#addCompItemId').val('');
                        $('#addCompPrice').val('');
                        $('#addCompShipping').val('');
                        $('#addCompLink').val('');
                        $('#addCompTitle').val('');
                        
                        // Reload competitors list
                        const sku = $('#addCompSku').val();
                        loadEbayCompetitorsModal(sku, currentLmpData.linkedLmpSkus);
                        
                        // Reload main table data
                        refreshEbay2TableData();
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to add competitor';
                    showToast(errorMsg, 'error');
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
            
            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) {
                return;
            }
            
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/ebay-lmp-delete',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        
                        // Reload competitors list
                        const sku = currentLmpData.sku;
                        loadEbayCompetitorsModal(sku, currentLmpData.linkedLmpSkus);
                        
                        // Reload main table data
                        refreshEbay2TableData();
                    } else {
                        showToast(response.error || 'Failed to delete competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to delete competitor';
                    showToast(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Tooltip functions for eBay2 links
        function showEbay2Tooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        }

        function hideEbay2Tooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        }

    </script>
@endsection
