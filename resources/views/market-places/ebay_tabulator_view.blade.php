@extends('layouts.vertical', ['title' => 'Ebay - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Filter UI — matches /amazon-tabulator-view: compact dropdowns, badges on top */
        #ebay-filter-bar .form-select {
            width: auto !important;
            max-width: 140px;
            padding-right: 1.35rem !important;
            padding-left: 0.5rem !important;
            background-position: right 0.35rem center !important;
        }
        /* Give room between items without inflating control height */
        #ebay-filter-bar { gap: 8px 10px !important; }
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #summary-stats .ebay2-summary-badge-row,
        #summary-stats .d-flex { gap: 8px !important; }

        /* Sku Link LMP (mirrors /amazon-tabulator-view) */
        .linked-sku-badge-wrap { display: inline-flex; align-items: center; gap: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove { font-size: 0.55rem; opacity: 0.65; padding: 0; margin-left: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover { opacity: 1; }
        .sku-link-lmp-suggestion-item { cursor: pointer; }
        .sku-link-lmp-suggestion-item .form-check-input { pointer-events: none; }
        .sku-link-lmp-selected-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 12px; }
        .sku-link-lmp-selected-chip button { border: 0; background: transparent; padding: 0; line-height: 1; font-size: 14px; color: #64748b; }

        /* LMP Competitors – right-side drawer (same pattern as Master Analytics / pricing_master) */
        #lmpModal {
            z-index: 1065 !important;
        }
        #lmpModal .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            margin: 0;
            width: 42vw;
            max-width: 640px;
            min-width: 380px;
            height: 100vh;
            max-height: 100vh;
            transform: none;
        }
        #lmpModal.fade .modal-dialog {
            transform: translateX(100%);
            transition: transform 0.25s ease-out;
        }
        #lmpModal.show .modal-dialog {
            transform: translateX(0);
        }
        #lmpModal .modal-content {
            height: 100%;
            max-height: 100vh;
            border-radius: 0;
            border: none;
            border-left: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
        }
        #lmpModal .modal-header {
            flex-shrink: 0;
            padding: 0.6rem 0.85rem;
        }
        #lmpModal .modal-title {
            font-size: 0.95rem;
            line-height: 1.3;
        }
        #lmpModal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding: 0.6rem;
        }
        #lmpModal .table {
            font-size: 11px;
            margin-bottom: 0;
        }
        #lmpModal .table th,
        #lmpModal .table td {
            padding: 0.3rem 0.35rem;
            vertical-align: middle;
        }
        #lmpModal #lmpStats .badge {
            font-size: 0.75rem !important;
            padding: 0.35rem 0.5rem !important;
        }
        #lmpModal .card-header h6 {
            font-size: 0.85rem;
        }

        /* LMP ignore (same as Temu): dim ignored competitors; they don't count toward L1 */
        #lmpModal tr.lmp-ignored-row {
            opacity: 0.55;
            background: #f1f3f5 !important;
        }
        #lmpModal tr.lmp-ignored-row td {
            text-decoration: line-through;
            text-decoration-color: #adb5bd;
        }
        #lmpModal tr.lmp-ignored-row td:last-child,
        #lmpModal tr.lmp-ignored-row .lmp-ignore-cb {
            text-decoration: none;
        }
        #lmpModal .lmp-ignore-cb {
            cursor: pointer;
            width: 1.1em;
            height: 1.1em;
        }

        /* Blue 5 Core reference row — our listing sorted by price among competitors */
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
            font-size: 14px;
            font-weight: 700;
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

        /* Custom pagination label (match ebay2-tabulator-view) */
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

        /* Parent row light blue background */
        .tabulator-row.ebay-parent-row,
        .tabulator-row.ebay-parent-row .tabulator-cell {
            background-color: #b3e5fc !important;
        }

        /* Play / Pause parent navigation (same as product-master) */
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

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Column visibility dropdown: 4 category panels (only when open) */
        #column-dropdown-menu.show {
            display: block;
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
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

        #column-dropdown-menu .col-vis-group-title.col-vis-group-empty {
            opacity: 0.55;
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
            cursor: grab;
        }

        #column-dropdown-menu .col-vis-item:active {
            cursor: grabbing;
        }

        #column-dropdown-menu .col-vis-item.col-vis-dragging {
            opacity: 0.45;
        }

        #column-dropdown-menu .col-vis-item > label {
            display: block;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }

        #column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }

        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Status circle for DIL filter */
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

        .status-circle.blue {
            background-color: #0d6efd;
        }

        /* Summary badges: single row, left→right; JS shrinks the font to fit if needed */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: 0.3rem;
            width: 100%;
            overflow: hidden;
        }

        /* Image column hover preview (same pattern as forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

        #summary-stats .ebay2-summary-badge-row>.badge {
            flex: 0 0 auto;
            font-size: var(--summary-badge-fs, 0.6rem);
            padding: 0.25rem 0.45rem;
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 5px 0;
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, .175);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .manual-dropdown-container .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay - Analytics',
        'sub_title' => 'Tabulator view — pricing, ads, and inventory',
    ])
    <div class="ebay-tabulator-page">
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex flex-column">
                <div class="d-flex align-items-center flex-wrap gap-2" id="ebay-filter-bar">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 180px; display: inline-block;">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 180px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more">INV &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
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
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR trend</option>
                        <option value="l60_gt_l30">CVR 60 &gt; CVR 30</option>
                        <option value="l30_gt_l60">CVR 30 &gt; CVR 60</option>
                        <option value="equal">CVR 60 = CVR 30</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">SPRICE</option>
                        <option value="blank">Blank SPRICE only</option>
                    </select>

                    {{-- Sprice/LMP filter — "Red" shows only rows where SPRICE is displayed in red (SPRICE > LMP). --}}
                    <select id="sprice-lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="Sprice/LMP: Red shows only rows where SPRICE is above LMP (red SPRICE text)">
                        <option value="all">Sprice/LMP</option>
                        <option value="red">Red (SPRICE &gt; LMP)</option>
                    </select>

                    {{-- Prc/LMP filter — "Red" shows only rows where the eBay Price is displayed in red (Price > LMP). --}}
                    <select id="prc-lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="Prc/LMP: Red shows only rows where eBay Price is above LMP (red price text)">
                        <option value="all">Prc/LMP</option>
                        <option value="red">Red (Price &gt; LMP)</option>
                    </select>
                    {{-- LMP× factor — Apply SPRICE = LMP × factor; gear opens modal (saved in ebay_sbid_rules) --}}
                    <div class="d-inline-flex align-items-center gap-1 pricing-filter-item p-1 border rounded"
                        id="lmp-mult-controls"
                        style="background: #ffc107;"
                        title="Set SPRICE = LMP × factor for selected rows (or all visible Price &gt; LMP). Click gear to edit factor (shared setting).">
                        <button type="button" id="apply-lmp98-sprice-btn"
                            class="btn btn-sm btn-warning border-0 py-0 px-2 fw-bold text-dark"
                            style="background: transparent;">
                            <i class="fas fa-percentage"></i> <span id="lmp-mult-btn-label">LMP×0.98</span>
                        </button>
                        <button type="button" id="open-lmp-mult-modal-btn"
                            class="btn btn-sm border-0 py-0 px-1 text-dark"
                            style="background: transparent;"
                            data-bs-toggle="modal" data-bs-target="#lmpMultRuleModal"
                            title="Edit LMP × factor (saved for everyone)">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>

                    {{-- LMP filter — "Red" shows only rows that have no LMP value. --}}
                    <select id="lmp-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="LMP: Red shows only rows that have no LMP value">
                        <option value="all">LMP</option>
                        <option value="red">Red (No LMP)</option>
                    </select>

                    {{-- Target ROI% bulk control — back-solves SPRICE so SNROI (Amazon NROI formula) = Target. --}}
                    {{-- Formula: sprice = (LP × (1 + Target/100) + Ship) / (margin − Ads%) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-roi-controls"
                        title="Target SNROI% — sets SPRICE so net SROI = Target (same Amazon NROI formula: accounts for fees, shipping, and Ads%)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target SNROI% applied to all selected rows when you click 'Apply SPRICE'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE = (LP \u00d7 (1 + Target/100) + Ship) / (margin \u2212 Ads%) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves SPRICE for selected rows so SGPFT = Target GPFT%. --}}
                    {{-- Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets SPRICE = (LP + Ship) / (margin − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply SPRICE'. Must be less than the eBay take-home margin (e.g. < 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save SPRICE = (LP + Ship) / (margin \u2212 Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target Price ($) — set SPRICE to an absolute dollar amount for selected SKUs. Table column shows LMP × factor. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                        id="target-price-controls"
                        title="Target Price ($) — sets SPRICE to this dollar amount on every selected row. The T Prc column shows LMP × the LMP× factor.">
                        <label for="target-price-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <span style="font-size:1em;" aria-hidden="true">🎯</span> Target $:
                        </label>
                        <input type="number" id="target-price-input" class="form-control form-control-sm text-end"
                            placeholder="0.00" step="0.01" min="0.01" style="width: 72px;"
                            title="Absolute target price applied to all selected rows as SPRICE">
                        <button id="apply-target-price-btn" class="btn btn-sm btn-success" type="button"
                            title="Save SPRICE = Target $ for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <!-- DIL Filter (plain select — matches /amazon-tabulator-view dropdown UI) -->
                    <select id="dil-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <!-- L7 Views colour band filter (same bands as L7 View column) -->
                    <select id="l7-views-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="L7 Views vs avg: Red &lt; avg, Green avg–2× avg, Pink ≥ 2× avg">
                        <option value="all">L7 Views</option>
                        <option value="red">Red</option>
                        <option value="green">Green</option>
                        <option value="pink">Pink</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 420px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>

                    <button id="ebay-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    {{-- Export / Import actions merged into one dropdown (matches /amazon-tabulator-view) --}}
                    <div class="btn-group pricing-filter-item">
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown"
                            aria-expanded="false" title="Export / Import">
                            <i class="fas fa-file-export"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportModal"><i class="fa fa-file-excel text-success"></i> Export</a></li>
                            <li><a class="dropdown-item" href="#" id="export-lmp-btn"><i class="fas fa-file-export text-warning"></i> Export LMP</a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-upload text-primary"></i> Import Ratings</a></li>
                            <li><a class="dropdown-item" href="{{ url('/ebay-ratings-sample') }}"><i class="fas fa-download text-info"></i> Sample CSV</a></li>
                        </ul>
                    </div>

                    {{-- Dil Rule button — same shared editor as /ebay/campaign-ads
                         (ebay_sbid_rules.key = ebay1_dil). --}}
                    <button type="button" class="btn btn-sm btn-outline-danger pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#dilRuleModal"
                            title="Configure DIL% color bands (shared with /ebay/campaign-ads)">
                        <i class="fas fa-tint me-1"></i>Dil Rule
                    </button>

                    {{-- Sprice Rule button — opens the multi-rule editor that auto-populates the
                         SPRICE column from Dil / El30 / CVR / Groi / LMP conditions. --}}
                    <button type="button" class="btn btn-sm btn-outline-success pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#spriceRuleModal"
                            title="Build rules on Dil / El30 / CVR / Groi / LMP that auto-calculate the SPRICE column">
                        <i class="fas fa-magic me-1"></i>Sprice Rule
                    </button>

                    {{-- Sbid Rule button — multi-rule editor that decides the S Bid column
                         from CVR / Dil / Esold / Views L30 conditions. --}}
                    <button type="button" class="btn btn-sm btn-outline-primary pricing-filter-item"
                            data-bs-toggle="modal" data-bs-target="#sbidRuleModal"
                            title="Build rules on For L7 Views / CVR that set the S Bid column">
                        <i class="fas fa-sliders-h me-1"></i>Sbid Rule
                    </button>
                </div>

                <!-- Summary Stats (layout matches Ebay 2 Analytics summary row) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <!-- Filtered rows count -->
                        <span class="badge bg-dark fs-6 p-2" id="rows-count-badge"
                            style="color: white; font-weight: bold;"
                            title="Number of rows currently shown after filters">Rows: 0</span>

                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter 0 sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                            title="Click to filter items with sales (INV>0)">> 0 Sold: 0</span>

                        <!-- Financial Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                            style="color: black; font-weight: bold;"
                            title="L30 sales from real eBay orders (tax-inclusive, excl. cancelled & fully-refunded) — same source as /ebay/daily-sales.">Sales: ${{ number_format((float) ($ordersL30TotalSales ?? 0)) }}</span>
                        
                        <span class="badge fs-6 p-2" id="qty-sold-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="L30 units sold (Σ ebay_order_items.quantity for period='l30'). Same value /ebay/daily-sales shows.">Qty: {{ number_format((int) ($ordersL30TotalQty ?? 0)) }}</span>
                        <span class="badge fs-6 p-2" id="ebay1-shopify-sales-badge"
                            style="background-color: #0f766e; color: white; font-weight: bold; display: none;"
                            title="eBay1 sales from Shopify raw data (L30, excludes cancelled)">EShp: $0</span>

                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                            style="color: black; font-weight: bold;"
                            title="GPFT% = Σ T PFT / Σ (qty × unit price) × 100 from real L30 orders — same source as /ebay/daily-sales.">GPFT: {{ round((float) ($ordersL30Gpft ?? 0)) }}%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge"
                            style="color: white; font-weight: bold;"
                            title="GROI% = Σ T PFT / Σ COGS × 100 from real L30 orders — same source as /ebay/daily-sales.">GROI: {{ round((float) ($ordersL30Groi ?? 0)) }}%</span>
                        <span class="badge fs-6 p-2" id="ads-percent-badge"
                            style="background-color: #d63384; color: white; font-weight: bold;"
                            title="eBay channel Ads% (Total Ad Spend / L30 Sales × 100) — same as the Ads % column">Ads {{ round((float) ($channelAdsPercent ?? 0)) }}%</span>
                        <span class="badge fs-6 p-2" id="npft-percent-badge"
                            style="background-color: #0f766e; color: white; font-weight: bold;"
                            title="NPFT% = GPFT% − Ads% (net profit margin after ad spend).">NPFT: {{ round((float) ($ordersL30Gpft ?? 0) - (float) ($channelAdsPercent ?? 0)) }}%</span>
                        <span class="badge fs-6 p-2" id="nroi-percent-badge"
                            style="background-color: #6f42c1; color: white; font-weight: bold;"
                            title="NROI% = (GPFT$ − Ad Spend) / COGS × 100 — same as Amazon (do not cut Ads% from GROI%).">NROI: {{ round((float) ($ordersL30Nroi ?? 0)) }}%</span>

                        <!-- eBay Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge"
                            style="color: white; font-weight: bold;"
                            title="CVR = (S Qty / Σ Views) × 100. Numerator is the orders-API L30 units (same value the S Qty badge shows, same source /ebay/daily-sales uses). Denominator is the sum of 'views' across rows with E Stock > 0.">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge"
                            style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge fs-6 p-2 ebay1-badge-chart" id="avg-l30-views-badge"
                            data-metric="avg_l30_view"
                            style="background-color: #20c997; color: black; font-weight: bold; cursor: pointer;"
                            title="A L30 View = Σ L30 Views / 30 (rounded). Click for daily history.">A L30 View: 0</span>
                        <span class="badge fs-6 p-2 ebay1-badge-chart" id="avg-l7-views-badge"
                            data-metric="avg_l7_views"
                            style="background-color: #0dcaf0; color: black; font-weight: bold; cursor: pointer;"
                            title="Avg L7 — Average L7 views across rows with E Stock > 0 (rounded). Click for daily history.">L7: 0</span>

                        <!-- Badge Filters -->
                        <span class="badge bg-secondary fs-6 p-2" id="missing-l-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing L (INV>0, not listed on eBay, REQ)">M L: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="missing-m-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter Missing M (listed, INV>0, REQ, INV vs eBay Stock mismatch)">M M: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="ebay-discount-type-block" class="d-flex align-items-center gap-2">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- View / Parent / SKU + search + row counter (toolbar matches ebay2-tabulator-view) -->
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e7eb;">
                        <div class="d-flex align-items-center gap-1">
                            <label for="view-type-filter" class="form-label mb-0 text-nowrap small"
                                style="font-size: 13px;">View:</label>
                            <select id="view-type-filter" class="form-select form-select-sm"
                                style="width: 100px; font-size: 13px;">
                                <option value="all">All</option>
                                <option value="parent">Parent</option>
                                {{-- Default selection: hide parent summary rows on initial load.
                                     Filter logic (applyFilters) already drops parent rows
                                     when this value is 'sku', so nothing else needs to change. --}}
                                <option value="sku" selected>SKU</option>
                            </select>
                        </div>
                        <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-light rounded-circle"
                                title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-light rounded-circle"
                                title="Show all products" style="display: none;">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-light rounded-circle"
                                title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-light rounded-circle"
                                title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <span id="custom-pagination-counter"
                            style="font-size: 13px; color: #555; white-space: nowrap; margin-left: 16px; display: none;"></span>
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- LMP Competitors Modal – right-side drawer -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-shopping-cart me-1"></i> LMP: <span id="lmpSku"></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" id="lmpPullApiBtn" class="btn btn-sm btn-light"
                            title="Pull live prices for this SKU from the LMP / eBay API (SerpApi)">
                            <i class="fas fa-cloud-download-alt"></i> Pull
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <!-- SKU metrics (CVR / Price / Views / Sold) for context -->
                    <div id="lmpStats" class="d-flex flex-wrap gap-1 mb-2"></div>

                    <!-- Add New Competitor Form -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white py-2">
                            <h6 class="mb-0"><i class="fa fa-plus-circle"></i> Add New Competitor</h6>
                        </div>
                        <div class="card-body py-2">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small mb-0">eBay Item ID</label>
                                        <input type="text" class="form-control form-control-sm" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Price</label>
                                        <input type="number" class="form-control form-control-sm" id="addCompPrice" name="price"
                                            step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-8">
                                        <label class="form-label small mb-0">Product Link</label>
                                        <input type="url" class="form-control form-control-sm" id="addCompLink" name="product_link"
                                            placeholder="https://ebay.com/itm/...">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-sm w-100">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
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

    <!-- SKU Metrics Chart Modal (format matches all-marketplace-master; dates = California / Pacific) -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="skuChartModalTitle">eBay - <span id="modalSkuName"></span> - Metrics</span> <span id="skuChartModalSuffix">(Rolling L30 · PT)</span>
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

    <!-- eBay 1 summary badge daily history (same layout as eBay 3 badge charts) -->
    <div class="modal fade" id="ebay1MetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2 bg-dark text-white">
                    <h6 class="modal-title mb-0">
                        <span id="ebay1ChartModalTitle">eBay 1 — Metric trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="ebay1ChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                    <div id="ebay1ChartContainer" style="height: 22vh; display: none; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="ebay1MetricChart"></canvas>
                        </div>
                        <div id="ebay1ChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="ebay1ChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="ebay1ChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="ebay1ChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="ebay1ChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="ebay1ChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0" id="ebay1ChartNoDataMsg">No daily snapshots yet. Open this page on separate days to build history (auto-saved from summary).</p>
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
                    <div id="export-columns-list"
                        style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
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
                            <input type="file" class="form-control" id="csvFile" name="file"
                                accept=".csv" required>
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

    {{-- Dilution Rule Modal — mirrors /ebay/campaign-ads; same endpoints (/ebay/campaign-ads/dil-rule). --}}
    <div class="modal fade" id="dilRuleModal" tabindex="-1" aria-labelledby="dilRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="dilRuleModalLabel">
                        <i class="fas fa-tint me-2 text-danger"></i>eBay Dilution Rule &mdash; DIL % &rarr; Color
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Bands evaluated <strong>top to bottom</strong> &mdash; first match wins.
                        <code>DIL = (L30 sold / Inventory) &times; 100</code>. Each band sets a color and a bid.
                    </p>

                    <table class="table table-sm table-bordered align-middle" id="dil-rule-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th>Color</th>
                                <th>DIL &le; (%)</th>
                                <th>Bid (%)</th>
                            </tr>
                        </thead>
                        <tbody id="dil-bands-body">
                            {{-- filled by JS --}}
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-sm btn-outline-primary py-0 mb-2" id="dil-add-band-btn">
                        <i class="fas fa-plus me-1"></i>Add band
                    </button>

                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Set DIL Max to <code>9999</code> for the last band (catches everything above the previous threshold).
                        <strong>Push logic:</strong> if a listing's SCVR <em>or</em> DIL lands in its <strong>Pink (catch-all)</strong>
                        band, the Pink bid is pushed; otherwise the SCVR rule's bid is used. Changes are shared with
                        <code>/ebay/campaign-ads</code>.
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="dil-rule-err"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="dil-rule-save-btn">
                        <i class="fas fa-save me-1"></i>Save Rule
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         Sprice Rule Modal — build multiple rules that auto-populate the SPRICE
         column. Each rule is a horizontal row of min/max ranges on 5 factors
         (Dil, El30, CVR, Groi, LMP) plus a method + value. Rules are evaluated
         top to bottom; the first rule whose ranges all match a row wins.
         Storage: ebay_sbid_rules.key = 'ebay1_sprice' (via /ebay-one/sprice-rule).
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="spriceRuleModal" tabindex="-1" aria-labelledby="spriceRuleModalLabel" aria-hidden="true">
        <style>
            #spriceRuleModal .modal-dialog { max-width: 98vw; width: 98vw; margin: 0.5rem auto; }
            #sprice-rule-table thead th { background-color: #fff9c4 !important; color: #000 !important; }
            /* Hide number-input spinner arrows */
            #spriceRuleModal input[type=number]::-webkit-inner-spin-button,
            #spriceRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            #spriceRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
            /* Rounded inputs */
            #spriceRuleModal .form-control, #spriceRuleModal .form-select { border-radius: 0.6rem; }
        </style>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="spriceRuleModalLabel">
                        <i class="fas fa-magic me-2 text-success"></i>Sprice Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="sprice-rule-table" style="min-width: 640px;">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" style="width:34px;" class="text-center align-middle">#</th>
                                    <th rowspan="2" style="min-width:110px;" class="align-middle">CVR</th>
                                    <th colspan="2" class="text-center">CVR %</th>
                                    <th colspan="2" class="text-center">Dil %</th>
                                    <th rowspan="2" style="min-width:120px;" class="align-middle text-center">Method</th>
                                    <th rowspan="2" style="width:90px;" class="align-middle text-center">Value</th>
                                    <th rowspan="2" style="width:44px;" class="align-middle"></th>
                                </tr>
                                <tr>
                                    <th class="text-center small text-muted">Min</th><th class="text-center small text-muted">Max</th>
                                    <th class="text-center small text-muted">Min</th><th class="text-center small text-muted">Max</th>
                                </tr>
                            </thead>
                            <tbody id="sprice-rules-body">
                                {{-- filled by JS --}}
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-primary mb-2" id="sprice-add-rule-btn">
                        <i class="fas fa-plus me-1"></i>Add rule / slab
                    </button>

                    <p class="small text-danger mb-0 mt-2 d-none" id="sprice-rule-err"></p>
                </div>
                <div class="modal-footer py-2 d-flex justify-content-between">
                    <span class="small text-muted" id="sprice-rule-status"></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-sm btn-success" id="sprice-rule-apply-btn">
                            <i class="fas fa-bolt me-1"></i>Apply to Visible Rows
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="sprice-rule-save-btn">
                            <i class="fas fa-save me-1"></i>Save Rule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         Sbid Rule Modal — build multiple rules that decide the S Bid column.
         Each rule is a horizontal row of min/max ranges on 4 factors
         (CVR, Dil, Esold, Views L30) plus the S Bid to apply. Rules are evaluated
         top to bottom; the first rule whose ranges all match a row wins.
         Storage: ebay_sbid_rules.key = 'ebay1_sbid_slabs' (via /ebay-one/sbid-slab-rule).
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="sbidRuleModal" tabindex="-1" aria-labelledby="sbidRuleModalLabel" aria-hidden="true">
        <style>
            #sbidRuleModal .modal-dialog { max-width: 98vw; width: 98vw; margin: 0.5rem auto; }
            #sbid-slab-rule-table thead th { background-color: #fff9c4 !important; color: #000 !important; }
            /* Hide number-input spinner arrows */
            #sbidRuleModal input[type=number]::-webkit-inner-spin-button,
            #sbidRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
            #sbidRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
            /* Rounded inputs */
            #sbidRuleModal .form-control, #sbidRuleModal .form-select { border-radius: 0.6rem; }
        </style>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="sbidRuleModalLabel">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Sbid Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="sbid-slab-rule-table" style="min-width: 720px;">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" style="width:34px;" class="text-center align-middle">#</th>
                                    <th rowspan="2" style="min-width:110px;" class="align-middle">Label</th>
                                    <th colspan="2" class="text-center">For L7 Views</th>
                                    <th colspan="2" class="text-center">CVR %</th>
                                    <th rowspan="2" style="width:100px;" class="align-middle text-center">S Bid (%)</th>
                                    <th rowspan="2" style="width:44px;" class="align-middle"></th>
                                </tr>
                                <tr>
                                    <th class="text-center small text-muted">Min</th><th class="text-center small text-muted">Max</th>
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

                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Rules are evaluated <strong>top to bottom</strong> — the first rule where all filled
                        ranges match a row sets that row's <strong>S Bid</strong>. Leave a Min/Max blank to ignore it.
                        <code>CVR = (eBay L30 / Views) &times; 100</code>, <code>For L7 Views = L7 View</code>.
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="sbid-slab-rule-err"></p>
                </div>
                <div class="modal-footer py-2 d-flex justify-content-between">
                    <span class="small text-muted" id="sbid-slab-rule-status"></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-sm btn-success" id="sbid-slab-apply-btn"
                                title="Push each visible row's computed S Bid to its eBay campaign">
                            <i class="fas fa-bolt me-1"></i>Push to Ebay
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="sbid-slab-rule-save-btn">
                            <i class="fas fa-save me-1"></i>Save Rule
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- LMP × factor Modal — shared setting (ebay_sbid_rules.key = ebay1_lmp_mult) --}}
    <div class="modal fade" id="lmpMultRuleModal" tabindex="-1" aria-labelledby="lmpMultRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#ffc107;">
                    <h5 class="modal-title text-dark" id="lmpMultRuleModalLabel">
                        <i class="fas fa-percentage me-2"></i>LMP × Factor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3 mb-md-2">
                        Used for <strong>SPRICE = LMP × factor</strong> (Apply button) and the
                        <strong>T Prc</strong> column. Saved for all users.
                    </p>
                    <label class="form-label fw-bold" for="lmp-mult-modal-input">Multiplier</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">LMP ×</span>
                        <input type="number" id="lmp-mult-modal-input" class="form-control text-end fw-bold"
                            value="0.98" step="0.01" min="0.01" max="2"
                            title="e.g. 0.98 = 2% under LMP">
                    </div>
                    <div class="form-text">Default <code>0.98</code>. Allowed range: 0.01 – 2.00</div>
                    <div id="lmp-mult-modal-status" class="small mt-2 text-muted"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="lmp-mult-save-btn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sku Link LMP Modal (same as /amazon-tabulator-view; shared endpoints/table) --}}
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
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "ebay_tabulator_column_visibility";
        /** Stored in DB table channel_tabulator_column_settings (shared across all users — same pattern as ebay2/ebay3/mfrg/amazon tabulators). */
        const TABULATOR_COLUMN_CHANNEL = 'ebay1_tabulator';
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        /** L30 units sold from ebay_orders (period='l30'). Same value rendered into the
         *  S Qty badge and the same number /ebay/daily-sales shows. Used by the CVR
         *  formula so the page CVR is computed against orders-API ground truth instead
         *  of the laggier ebay_metrics.ebay_l30 sum. */
        const ORDERS_L30_TOTAL_QTY = {{ (int) ($ordersL30TotalQty ?? 0) }};
        /** L30 sales from ebay_orders (period='l30', tax-inclusive, excl. cancelled &
         *  fully-refunded). Same value /ebay/daily-sales shows. Rendered as the Sales
         *  badge so this page agrees with that page instead of the per-SKU datasheet. */
        const ORDERS_L30_TOTAL_SALES = {{ (float) ($ordersL30TotalSales ?? 0) }};
        /** L30 GPFT% / GROI% from the same real orders /ebay/daily-sales uses, so these
         *  badges agree with that page (profit uses ProductMaster LP/ship per order item,
         *  which the per-SKU datasheet can't reproduce for the real-orders basis). */
        const ORDERS_L30_GPFT = {{ (float) ($ordersL30Gpft ?? 0) }};
        const ORDERS_L30_GROI = {{ (float) ($ordersL30Groi ?? 0) }};
        const ORDERS_L30_PFT = {{ (float) ($ordersL30Pft ?? 0) }};
        const ORDERS_L30_COGS = {{ (float) ($ordersL30Cogs ?? 0) }};
        const EBAY_AD_SPEND = {{ (float) ($ebayAdSpend ?? 0) }};
        const ORDERS_L30_NROI = {{ (float) ($ordersL30Nroi ?? 0) }};
        /** eBay channel-level Ads% (TACOS) — Total Ad Spend / L30 Sales × 100.
         *  Same value shown for eBay on /all-marketplace-master (per-SKU spend isn't
         *  available in this page's data, so the Ads % column shows the channel figure). */
        const EBAY_CHANNEL_ADS_PCT = {{ (float) ($channelAdsPercent ?? 0) }};

        /**
         * Net ROI — same shape as Amazon NROI / SNROI badge:
         *   (gross profit $ − ad spend $) / COGS × 100
         * where ad spend $ = price × Ads%/100 and COGS = LP.
         * @param {object} rowData
         * @param {string} priceKey  'eBay Price' for NROI, 'SPRICE' for SNROI
         */
        function ebayComputeNetRoi(rowData, priceKey) {
            if (!rowData) return null;
            const price = parseFloat(rowData[priceKey]);
            const lp = parseFloat(rowData.LP_productmaster);
            if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
            const ship = parseFloat(rowData.Ship_productmaster) || 0;
            const marginRaw = parseFloat(rowData.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
            const adsFrac = (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) / 100;
            const grossPft = (price * margin) - ship - lp;
            const adSpend = price * adsFrac;
            return ((grossPft - adSpend) / lp) * 100;
        }
        /** App base path (XAMPP subdir / public): root-relative "/ebay-data-json" would 404 */
        const EBAY_DATA_JSON_URL = @json(url('/ebay-data-json'));
        let skuMetricsChart = null;
        let skuChartFirstSeriesStats = null; // { values, median, dataMin, dataMax, valueFmt } for ref panel & plugins
        let currentSkuChartMetric = 'price'; // 'price' | 'cvr' | 'views' | 'l7_views'
        let currentSku = null;
        let table = null; // Global table reference
        /** Average L7 views (rows with E Stock > 0) — drives the L7 View column colours
         *  and the "Avg L7" badge. Recomputed in updateSummary(). */
        let avgL7ViewsGlobal = 0;

        /** Daily snapshot badge chart (amazon_channel_summary_data, channel=ebay) */
        const ebay1BadgeMetricLabels = {
            avg_l30_view: 'A L30 View',
            avg_l7_views: 'L7',
            total_views: 'Views',
        };
        let ebay1ChartInstance = null;
        let ebay1ChartAjax = null;
        let ebay1ChartDays = 30;
        let ebay1ChartMetricKey = '';
        /** 'badge' = channel summary series; 'sku' = per-SKU L30/L7 views history */
        let ebay1ChartMode = 'badge';
        let ebay1ChartSku = '';
        const ebay1SkuViewMetricLabels = {
            views: 'L30 View',
            l7_views: 'L7 View',
        };

        function ebay1FmtChartVal(v) {
            return Math.round(Number(v)).toLocaleString('en-US');
        }

        function openEbay1ChartModal() {
            const modalEl = document.getElementById('ebay1MetricChartModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                $(modalEl).modal('show');
            }
        }

        function showEbay1MetricChart(metricKey) {
            ebay1ChartMode = 'badge';
            ebay1ChartSku = '';
            ebay1ChartMetricKey = metricKey;
            ebay1ChartDays = 30;
            $('#ebay1ChartRangeSelect').val('30');
            const label = ebay1BadgeMetricLabels[metricKey] || metricKey;
            $('#ebay1ChartModalTitle').text('eBay 1 — ' + label + ' (Daily snapshot)');
            openEbay1ChartModal();
            loadEbay1MetricChart();
        }

        /** Per-SKU L30 View / L7 View history — same chart style as A L30 View badge. */
        function showSkuViewsChart(sku, metricKey) {
            if (!sku) return;
            ebay1ChartMode = 'sku';
            ebay1ChartSku = sku;
            ebay1ChartMetricKey = (metricKey === 'l7_views') ? 'l7_views' : 'views';
            ebay1ChartDays = 30;
            $('#ebay1ChartRangeSelect').val('30');
            const label = ebay1SkuViewMetricLabels[ebay1ChartMetricKey] || ebay1ChartMetricKey;
            $('#ebay1ChartModalTitle').text('eBay 1 — ' + label + ' — ' + sku + ' (Daily snapshot)');
            openEbay1ChartModal();
            loadEbay1MetricChart();
        }

        function loadEbay1MetricChart() {
            if (ebay1ChartAjax) {
                ebay1ChartAjax.abort();
            }
            $('#ebay1ChartNoData').hide();
            $('#ebay1ChartContainer').hide();
            $('#ebay1ChartLoading').show();

            if (ebay1ChartMode === 'sku') {
                const days = ebay1ChartDays > 0 ? ebay1ChartDays : 90;
                $('#ebay1ChartNoDataMsg').text('No historical data for this SKU yet. Data appears after the metrics collection job runs.');
                ebay1ChartAjax = $.ajax({
                    url: '/ebay-metrics-history',
                    method: 'GET',
                    data: { sku: ebay1ChartSku, days: days },
                    success: function(resp) {
                        ebay1ChartAjax = null;
                        $('#ebay1ChartLoading').hide();
                        const rows = Array.isArray(resp) ? resp : (resp && resp.data ? resp.data : []);
                        const field = ebay1ChartMetricKey === 'l7_views' ? 'l7_views' : 'views';
                        const mapped = (rows || []).map(function(d) {
                            return {
                                date: d.date_formatted || d.date || '',
                                value: parseFloat(d[field]) || 0,
                            };
                        }).filter(function(d) { return d.date !== ''; });
                        if (mapped.length > 0) {
                            $('#ebay1ChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                            renderEbay1MetricChart(mapped);
                        } else {
                            $('#ebay1ChartNoData').show();
                        }
                    },
                    error: function(xhr, status) {
                        ebay1ChartAjax = null;
                        if (status === 'abort') return;
                        $('#ebay1ChartLoading').hide();
                        $('#ebay1ChartNoData').show();
                    }
                });
                return;
            }

            $('#ebay1ChartNoDataMsg').text('No daily snapshots yet. Open this page on separate days to build history (auto-saved from summary).');
            ebay1ChartAjax = $.ajax({
                url: '/ebay-badge-chart-data',
                method: 'GET',
                data: { metric: ebay1ChartMetricKey, days: ebay1ChartDays },
                success: function(resp) {
                    ebay1ChartAjax = null;
                    $('#ebay1ChartLoading').hide();
                    if (resp.success && resp.data && resp.data.length > 0) {
                        $('#ebay1ChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                        renderEbay1MetricChart(resp.data);
                    } else {
                        $('#ebay1ChartNoData').show();
                    }
                },
                error: function(xhr, status) {
                    ebay1ChartAjax = null;
                    if (status === 'abort') {
                        return;
                    }
                    $('#ebay1ChartLoading').hide();
                    $('#ebay1ChartNoData').show();
                }
            });
        }

        function renderEbay1MetricChart(data) {
            const ctx = document.getElementById('ebay1MetricChart').getContext('2d');
            if (ebay1ChartInstance) {
                ebay1ChartInstance.destroy();
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

            document.getElementById('ebay1ChartHighest').textContent = ebay1FmtChartVal(dataMax);
            document.getElementById('ebay1ChartMedian').textContent = ebay1FmtChartVal(median);
            document.getElementById('ebay1ChartLowest').textContent = ebay1FmtChartVal(dataMin);

            const dotColors = values.map(function(v, i) {
                if (i === 0) return '#6c757d';
                return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#198754' : '#6c757d');
            });
            const labelColors = values.map(function(v, i) {
                if (i < 7) return '#6c757d';
                return v < values[i - 7] ? '#dc3545' : (v > values[i - 7] ? '#198754' : '#6c757d');
            });

            const medianLinePlugin = {
                id: 'ebay1MedianLine',
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
                id: 'ebay1ValueLabels',
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
                        const txt = ebay1FmtChartVal(dataset.data[i]);
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

            ebay1ChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: 'rgba(32, 201, 151, 0.08)',
                        borderColor: '#20c997',
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
                                    const parts = ['Value: ' + ebay1FmtChartVal(context.raw)];
                                    if (idx > 0) {
                                        const diff = context.raw - values[idx - 1];
                                        parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + ebay1FmtChartVal(Math.abs(diff)));
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
                            ticks: { font: { size: 9 }, callback: function(v) { return ebay1FmtChartVal(v); } }
                        },
                        x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                    }
                }
            });
        }

        /** L7 daily pace vs L30 daily pace — green = accelerating, red = slowing. */
        function viewsPaceVariation(rowData) {
            const l30 = parseFloat(rowData.views) || 0;
            const l7 = parseFloat(rowData.l7_views) || 0;
            const l30Pace = l30 / 30;
            const l7Pace = l7 / 7;
            const tol = Math.max(0.05, l30Pace * 0.05);
            if (l7Pace > l30Pace + tol) {
                return { color: '#28a745', label: 'up', icon: 'fa-arrow-up', title: 'L7 pace > L30 pace (views accelerating)' };
            }
            if (l7Pace < l30Pace - tol) {
                return { color: '#a00211', label: 'down', icon: 'fa-arrow-down', title: 'L7 pace < L30 pace (views slowing)' };
            }
            return { color: '#6c757d', label: 'flat', icon: 'fa-minus', title: 'L7 pace ≈ L30 pace (steady)' };
        }

        function viewsHistoryArrowBtn(sku, isParent, variation, metric) {
            if (!sku || isParent) return '';
            const m = (metric === 'l7_views') ? 'l7_views' : 'views';
            const label = m === 'l7_views' ? 'L7 View' : 'L30 View';
            // Same Rolling L30 chart as Prc (view-sku-chart → skuMetricsModal)
            return `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="${m}" title="${variation.title} — click for ${label} history" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><i class="fas ${variation.icon}" style="color: ${variation.color}; font-size: 12px;"></i></button>`;
        }

        /** L7 View colour band for a value, relative to the current average:
         *  red   = below avg, green = avg..2x avg, pink = 2x avg and above.
         *  Returned as {key, color}; used by L7 View colour bands / filters. */
        function l7ViewBand(value) {
            const v = parseFloat(value) || 0;
            const avg = parseFloat(avgL7ViewsGlobal) || 0;
            if (avg <= 0) {
                // No avg yet — treat zeros / empty as below-avg red
                return v <= 0 ? { key: 'red', color: '#a00211' } : { key: '', color: '' };
            }
            if (v < avg) return { key: 'red', color: '#a00211' };
            if (v < avg * 2) return { key: 'green', color: '#28a745' };
            return { key: 'pink', color: '#d63384' };
        }

        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false;
        let selectedSkus = new Set(); // Track selected SKUs across all pages
        /** Shared with /ebay/campaign-ads SBID Rule (ebay_sbid_rules.key = ebay1). */
        let currentSbidRule = { bands: [] };

        function esBidResult(esBidRaw) {
            if (!isFinite(esBidRaw) || esBidRaw <= 0) {
                return { bid: 0, color: '#6c757d', skip: true };
            }
            return { bid: esBidRaw, color: '#0dcaf0', skip: false };
        }

        function resolveSbidBandBid(band, ctx) {
            // Band flagged to use the row's ES Bid (raw eBay suggested_bid).
            if (band.use_es_bid) {
                return esBidResult(parseFloat(ctx.es_bid));
            }
            return { bid: parseFloat(band.bid), color: band.color || '#333', skip: false };
        }

        function getSbidFromRule(scvr, rowData) {
            const s = parseFloat(scvr);
            const safeScvr = (!isFinite(s) || s < 0) ? 0 : s;
            const bands = currentSbidRule.bands || [];
            const ctx = {
                scvr: safeScvr,
                ebay_price: parseFloat(rowData['eBay Price']) || 0,
                ebay_l30: parseFloat(rowData['eBay L30']) || 0,
                views: parseFloat(rowData.views) || 0,
                es_bid: parseFloat(rowData.ca_suggested_bid) || 0,
            };
            // First band whose [scvr_min, scvr_max] range contains the SCVR wins.
            for (let i = 0; i < bands.length; i++) {
                const min = parseFloat(bands[i].scvr_min);
                const max = parseFloat(bands[i].scvr_max);
                const lo = isFinite(min) ? min : 0;
                const hi = isFinite(max) ? max : 9999;
                if (safeScvr >= lo && safeScvr <= hi) {
                    return resolveSbidBandBid(bands[i], ctx);
                }
            }
            const last = bands[bands.length - 1] || { bid: 2.1, color: '#e83e8c' };
            return resolveSbidBandBid(last, ctx);
        }

        // ── Sku Link LMP (mirrors /amazon-tabulator-view; shared sku.link.lmp.* routes) ──
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
            table.replaceData(); // re-fetch /ebay-data-json so LMP recomputes across the group
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

        // S Bid is driven by the Sbid Rule slabs (CVR / Dil / Esold / Views L30 → S Bid).
        // Populated from /ebay-one/sbid-slab-rule; see the Sbid Rule modal.
        let currentSbidSlabRules = [];

        function sbidSlabInRange(val, min, max) {
            if (min !== null && min !== undefined && min !== '' && val < parseFloat(min)) return false;
            if (max !== null && max !== undefined && max !== '' && val > parseFloat(max)) return false;
            return true;
        }

        function getCombinedSbid(rowData) {
            const esold = parseFloat(rowData['eBay L30']) || 0;
            const views = parseFloat(rowData.views) || 0;
            const l7Views = parseFloat(rowData.l7_views) || 0;
            const inv = parseFloat(rowData.INV) || 0;
            const ovl30 = parseFloat(rowData['L30']) || 0;
            const cvr = views > 0 ? (esold / views) * 100 : 0;
            const dil = inv > 0 ? (ovl30 / inv) * 100 : 0;

            const rules = currentSbidSlabRules || [];
            for (let i = 0; i < rules.length; i++) {
                const r = rules[i];
                if (sbidSlabInRange(cvr, r.cvr_min, r.cvr_max)
                    && sbidSlabInRange(l7Views, r.l7_views_min, r.l7_views_max)) {
                    const bid = parseFloat(r.sbid);
                    if (isFinite(bid) && bid > 0) {
                        return { bid: bid, color: '#0d6efd', skip: false };
                    }
                    return { bid: 0, color: '#6c757d', skip: true };
                }
            }
            return { bid: 0, color: '#6c757d', skip: true };
        }

        /**
         * Child SKUs on the current pagination page only (respects filters + SKU Count).
         * Never return all filtered rows: always slice [start, start + pageSize) over the active/filtered set.
         */
        function ebayCurrentPageChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            const notParent = function(d) {
                return !isParent(d);
            };
            var page = 1;
            var pageSize = 100;
            try {
                page = table.getPage();
                pageSize = table.getPageSize();
            } catch (e) {
                /* ignore */ }
            if (page < 1) page = 1;
            const start = Math.max(0, (page - 1) * pageSize);
            const end = start + pageSize;

            var totalActive = null;
            try {
                if (typeof table.getDataCount === 'function') {
                    totalActive = table.getDataCount('active');
                }
            } catch (e) {
                /* ignore */ }

            var activeData = [];
            try {
                activeData = table.getData('active') || [];
            } catch (e) {
                /* ignore */ }

            // Full filtered dataset in memory → paginate (works with filters)
            if (activeData.length > 0) {
                var fullActiveSet = totalActive == null || activeData.length === totalActive;
                var longEnough = activeData.length >= end;
                if (fullActiveSet || longEnough) {
                    return activeData.slice(start, end).filter(notParent);
                }
                if (activeData.length <= pageSize && start === 0) {
                    return activeData.filter(notParent);
                }
            }

            try {
                var activeRows = table.getRows('active') || [];
                if (activeRows.length > 0) {
                    if (totalActive == null || activeRows.length === totalActive || activeRows.length >= end) {
                        return activeRows.slice(start, end).map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                    if (activeRows.length <= pageSize && start === 0) {
                        return activeRows.map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                }
            } catch (e2) {
                /* ignore */ }

            return [];
        }

        // Play / Pause parent navigation (same as product-master)
        let productUniqueParents = [];
        let isProductNavigationActive = false;
        let currentProductParentIndex = -1;

        function ebayParentKey(p) {
            var s = (p || '').toString().trim();
            if (s.toUpperCase().startsWith('PARENT')) return s.replace(/^PARENT\s+/i, '').trim();
            return s;
        }

        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingLFilterActive = false;
        let missingMFilterActive = false;

        /**
         * When any narrowing filter/search is on, header "select all" should include every filtered row (all pages).
         * Default table state (E Stock &gt; 0, REQ only, etc.) = current page only.
         */
        function ebaySelectAllUsesFullFilteredSet() {
            if (typeof isProductNavigationActive !== 'undefined' && isProductNavigationActive) return true;
            if (($('#view-type-filter').val() || 'all') !== 'all') return true;
            if (($('#inventory-filter').val() || 'all') !== 'all') return true;
            if (($('#el30-filter').val() || 'all') !== 'all') return true;
            if (($('#nrl-filter').val() || 'REQ') !== 'REQ') return true;
            if (($('#gpft-filter').val() || 'all') !== 'all') return true;
            if (($('#roi-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-trend-filter').val() || 'all') !== 'all') return true;
            if (($('#sprice-filter').val() || 'all') !== 'all') return true;
            if (($('#sprice-lmp-filter').val() || 'all') !== 'all') return true;
            if (($('#prc-lmp-filter').val() || 'all') !== 'all') return true;
            if (($('#lmp-filter').val() || 'all') !== 'all') return true;
            if (($('#growth-sign-filter').val() || 'all') !== 'all') return true;
            var dil = $('#dil-filter').val() || 'all';
            if (dil !== 'all') return true;
            var l7v = $('#l7-views-filter').val() || 'all';
            if (l7v !== 'all') return true;
            if (zeroSoldFilterActive || moreSoldFilterActive || missingLFilterActive || missingMFilterActive) return true;

            return false;
        }

        /** All filtered child rows (every page), excluding parent summary rows. */
        function ebayAllFilteredChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            try {
                return (table.getData('active') || []).filter(function(d) {
                    return !isParent(d);
                });
            } catch (e) {
                return [];
            }
        }

        function ebayRowsForHeaderSelectAll() {
            return ebaySelectAllUsesFullFilteredSet() ?
                ebayAllFilteredChildRowsForSelection() :
                ebayCurrentPageChildRowsForSelection();
        }

        // Single toast: accepts showToast(message, type) or showToast(type, message)
        function escAttr(s) {
            if (s == null) return '';
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /** POST to forecast_analysis via same endpoint as Forecast Analysis (column NR → nr). */
        function ebayUpdateForecastNrp(data, onSuccess, onFail) {
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
                if (typeof showToast === 'function') showToast('error', 'Error saving NRP.');
                onFail();
            });
        }

        function showToast(a, b) {
            var type, message;
            if (['success', 'error', 'info', 'warning'].indexOf(String(a)) !== -1 && typeof b === 'string') {
                type = a;
                message = b;
            } else {
                message = a;
                type = b || 'info';
            }
            var container = document.querySelector('.toast-container');
            if (!container) return;
            var bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' :
                'info'));
            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-' + bg + ' border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '') +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            container.appendChild(toast);
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                new bootstrap.Toast(toast).show();
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            } else {
                toast.classList.add('show');
                toast.style.position = 'fixed';
                toast.style.top = '1rem';
                toast.style.right = '1rem';
                toast.style.zIndex = '10800';
                setTimeout(function() {
                    toast.remove();
                }, 5000);
            }
        }

        // Format helper for SKU chart Price series (matches all-marketplace-master)
        function skuChartFmtVal(v) {
            return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        }

        // SKU-specific chart (layout/plugins match all-marketplace-master: ref panel, median line, value labels)
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
                    c.save();
                    c.font = 'bold 6px Inter, system-ui, sans-serif';
                    c.textAlign = 'center';
                    c.textBaseline = 'bottom';
                    const seriesColor = dataset.borderColor || '#6c757d';
                    const valueFmt = (skuChartFirstSeriesStats && skuChartFirstSeriesStats.valueFmt) ? skuChartFirstSeriesStats.valueFmt : skuChartFmtVal;
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        if (val == null || !point) return;
                        const offsetY = (i % 2 === 0) ? -6 : -10;
                        c.fillStyle = seriesColor;
                        c.fillText(valueFmt(val), point.x, point.y + offsetY);
                    });
                    c.restore();
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
            $('#skuChartLoading').show();
            $('#skuChartContainer').hide();
            $('#chart-no-data-message').hide();
            const daysNum = days === 0 || days === '0' ? 0 : (parseInt(days, 10) || 30);
            fetch(`/ebay-metrics-history?days=${daysNum}&sku=${encodeURIComponent(sku)}`)
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

            // Initialize SKU-specific chart
            initSkuMetricsChart();

            // A L30 View (and other ebay1 summary badges) — click opens daily history chart
            $(document).on('click', '.ebay1-badge-chart', function(e) {
                e.stopPropagation();
                const m = $(this).data('metric');
                if (m) {
                    showEbay1MetricChart(m);
                }
            });
            $('#ebay1ChartRangeSelect').on('change', function() {
                ebay1ChartDays = parseInt($(this).val(), 10) || 0;
                loadEbay1MetricChart();
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

            function syncEbayDiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#ebay-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('Same price:');
                    $inp.attr('placeholder', 'Enter price for all selected');
                    $inp.prop('disabled', false);
                    $inp.removeAttr('max');
                } else {
                    $('#ebay-discount-type-block').removeClass('d-none');
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

            function syncEbayPriceModeUi() {
                if (!table || !table.getColumn) {
                    return;
                }
                const $btn = $('#ebay-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncEbayDiscountBarForMode();
                if (decreaseModeActive) {
                    $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                    selectColumn.show();
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                    selectColumn.show();
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                    selectColumn.show();
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

            $('#ebay-price-mode-btn').on('click', function() {
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
                syncEbayPriceModeUi();
            });

            // Select all checkbox handler (matching Amazon approach)
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');

                // With filters/search: all matching rows (all pages). Default state: current page only.
                const filteredData = ebayRowsForHeaderSelectAll();

                // Add or remove those SKUs from the selected set
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

           
            function ebayApplyTargetSpriceBatch(opts) {
                // opts: { targetPct, computeSprice(rd) -> {sprice, skipReason?}, label, $btn, btnHtml }
                const $btn = opts.$btn;
                if (typeof selectedSkus === 'undefined' || selectedSkus.size === 0) {
                    showToast('error', 'Please select at least one SKU');
                    return;
                }

                const rowsToProcess = [];
                const skipped = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rd.is_parent_summary || rd.is_parent_row) return;
                    const res = opts.computeSprice(rd);
                    if (!res || res.skipReason) {
                        if (res && res.skipReason) skipped.push({ sku: sku, reason: res.skipReason });
                        return;
                    }
                    const sprice = +Number(res.sprice).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    if (skipped.length > 0) {
                        showToast('error', `Cannot apply: ${skipped[0].reason}`);
                    } else {
                        showToast('warning', 'No selected rows have a usable LP > 0');
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
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { sku: item.sku, sprice: item.sprice },
                        success: function(response) {
                            successCount++;
                            // Field names match the existing eBay saveSpriceWithRetry update payload.
                            const updateData = {
                                SPRICE: item.sprice,
                                SPFT: response.spft_percent != null ? response.spft_percent : 0,
                                SROI: response.sroi_percent != null ? response.sroi_percent : 0,
                                SGROI: response.sgroi_percent != null ? response.sgroi_percent : 0,
                                SGPFT: response.sgpft_percent != null ? response.sgpft_percent : 0,
                                SPRICE_STATUS: 'saved',
                                has_custom_sprice: true
                            };
                            item.row.update(updateData);
                            item.row.reformat();
                        },
                        error: function() { errorCount++; },
                        complete: function() {
                            if (successCount + errorCount === total) {
                                $btn.prop('disabled', false).html(opts.btnHtml);
                                if (errorCount === 0) {
                                    showToast('success', `SPRICE saved for ${successCount} SKU(s) @ ${opts.label}`);
                                } else {
                                    showToast('error', `Saved ${successCount} of ${total} (${errorCount} failed)`);
                                }
                                // Wipe selection so the next batch starts clean.
                                selectedSkus.clear();
                                $('.sku-select-checkbox').prop('checked', false);
                                $('#select-all-checkbox').prop('checked', false);
                                if (typeof updateSelectedCount === 'function') {
                                    updateSelectedCount();
                                } else if (typeof updateSelectionUI === 'function') {
                                    updateSelectionUI();
                                }
                            }
                        }
                    });
                });
            }

            // Target ROI% — targets displayed SNROI (Amazon NROI shape), not gross SGROI:
            //   ((sprice×margin − ship − lp) − sprice×Ads%/100) / lp × 100 = Target
            //   -> sprice = (lp × (1 + Target/100) + ship) / (margin − Ads%/100)
            $('#apply-target-roi-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#target-roi-input').val();
                const targetRoiPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('error', 'Please enter a Target ROI%'); return; }
                if (!isFinite(targetRoiPct)) { showToast('error', 'Target ROI% must be a number'); return; }
                const adsFrac = (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) / 100;
                const roiMultiplier = 1 + (targetRoiPct / 100);
                ebayApplyTargetSpriceBatch({
                    targetPct: targetRoiPct,
                    label: `Target SNROI ${targetRoiPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.LP_productmaster) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.Ship_productmaster) || 0;
                        const marginRaw = parseFloat(rd.percentage);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                        const netMargin = margin - adsFrac;
                        if (netMargin <= 0) {
                            return { skipReason: `Ads% ≥ eBay take-home margin (~${Math.round(margin * 100)}%)` };
                        }
                        return { sprice: (lp * roiMultiplier + ship) / netMargin };
                    }
                });
            });
            $('#target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });

            // Target GPFT%
            $('#apply-target-gpft-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#target-gpft-input').val();
                const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('error', 'Please enter a Target GPFT%'); return; }
                if (!isFinite(targetGpftPct)) { showToast('error', 'Target GPFT% must be a number'); return; }
                const targetFraction = targetGpftPct / 100;
                ebayApplyTargetSpriceBatch({
                    targetPct: targetGpftPct,
                    label: `Target GPFT ${targetGpftPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.LP_productmaster) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.Ship_productmaster) || 0;
                        const marginRaw = parseFloat(rd.percentage);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                        const denom = margin - targetFraction;
                        if (denom <= 0) {
                            return { skipReason: `Target GPFT% ${targetGpftPct}% \u2265 eBay take-home margin (~${Math.round(margin * 100)}%)` };
                        }
                        return { sprice: (lp + ship) / denom };
                    }
                });
            });
            $('#target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Target Price ($) — sets SPRICE to the entered absolute dollar amount for selected SKUs
            $('#apply-target-price-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#target-price-input').val();
                const targetPrice = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('error', 'Please enter a Target Price'); return; }
                if (!isFinite(targetPrice) || targetPrice <= 0) {
                    showToast('error', 'Target Price must be a number greater than 0');
                    return;
                }
                ebayApplyTargetSpriceBatch({
                    targetPct: targetPrice,
                    label: `Target Price $${targetPrice.toFixed(2)}`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i>',
                    computeSprice: function() {
                        return { sprice: targetPrice };
                    }
                });
            });
            $('#target-price-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-price-btn').click();
            });

            // LMP × factor — shared via /ebay-one/lmp-mult-rule (ebay_sbid_rules). Edited in modal.
            // Apply button: SPRICE = LMP × factor (selected, or visible Price > LMP).
            let lmpMultValue = 0.98;
            const LMP_MULT_GET_URL = @json(url('/ebay-one/lmp-mult-rule'));
            const LMP_MULT_SAVE_URL = @json(url('/ebay-one/lmp-mult-rule'));

            function getLmpMult() {
                const n = parseFloat(lmpMultValue);
                if (isFinite(n) && n > 0 && n <= 2) return n;
                return 0.98;
            }
            window.getLmpMult = getLmpMult;

            function formatLmpMult(v) {
                const n = Number(v);
                if (!isFinite(n)) return '0.98';
                return String(+n.toFixed(4));
            }

            function refreshTargetPriceColumn() {
                if (typeof table === 'undefined' || !table || !table.getRows) return;
                try {
                    table.getRows().forEach(function(row) {
                        const cell = row.getCell('target_price');
                        if (cell && typeof cell.reformat === 'function') cell.reformat();
                    });
                } catch (e) { /* ignore */ }
            }

            function refreshLmpMultUi() {
                const m = getLmpMult();
                const label = 'LMP×' + formatLmpMult(m);
                $('#lmp-mult-btn-label').text(label);
                $('#lmp-mult-modal-input').val(formatLmpMult(m));
                $('#apply-lmp98-sprice-btn').attr('title',
                    'Set SPRICE = LMP × ' + formatLmpMult(m) + ' for selected rows (or all visible Price > LMP rows if none selected)');
                if (typeof window.updateSpriceLmpMultLabels === 'function') {
                    window.updateSpriceLmpMultLabels(m);
                }
                refreshTargetPriceColumn();
            }

            function loadLmpMultRule() {
                $.ajax({
                    url: LMP_MULT_GET_URL,
                    method: 'GET',
                    success: function(resp) {
                        const m = parseFloat(resp && resp.mult);
                        if (isFinite(m) && m > 0 && m <= 2) {
                            lmpMultValue = m;
                        }
                        refreshLmpMultUi();
                    },
                    error: function() {
                        refreshLmpMultUi();
                    }
                });
            }

            function saveLmpMultRuleFromModal() {
                const raw = parseFloat(String($('#lmp-mult-modal-input').val()).replace(',', '.'));
                if (!isFinite(raw) || raw <= 0 || raw > 2) {
                    $('#lmp-mult-modal-status').removeClass('text-success').addClass('text-danger')
                        .text('Enter a multiplier between 0.01 and 2.00');
                    return;
                }
                const $btn = $('#lmp-mult-save-btn');
                const btnHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $('#lmp-mult-modal-status').removeClass('text-danger text-success').addClass('text-muted').text('Saving...');

                $.ajax({
                    url: LMP_MULT_SAVE_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { mult: raw },
                    success: function(resp) {
                        const m = parseFloat(resp && resp.rule && resp.rule.mult);
                        lmpMultValue = (isFinite(m) && m > 0 && m <= 2) ? m : raw;
                        refreshLmpMultUi();
                        $('#lmp-mult-modal-status').removeClass('text-danger text-muted').addClass('text-success')
                            .text('Saved LMP × ' + formatLmpMult(lmpMultValue));
                        showToast('success', 'LMP × factor saved: ' + formatLmpMult(lmpMultValue));
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to save LMP × factor';
                        $('#lmp-mult-modal-status').removeClass('text-success text-muted').addClass('text-danger').text(msg);
                        showToast('error', msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(btnHtml);
                    }
                });
            }

            loadLmpMultRule();
            $('#lmpMultRuleModal').on('show.bs.modal', function() {
                $('#lmp-mult-modal-input').val(formatLmpMult(getLmpMult()));
                $('#lmp-mult-modal-status').removeClass('text-danger text-success').addClass('text-muted').text('');
            });
            $('#lmp-mult-save-btn').on('click', saveLmpMultRuleFromModal);
            $('#lmp-mult-modal-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    saveLmpMultRuleFromModal();
                }
            });

            $('#apply-lmp98-sprice-btn').on('click', function() {
                const $btn = $(this);
                const mult = getLmpMult();
                const multLabel = formatLmpMult(mult);
                const btnHtml = '<i class="fas fa-percentage"></i> <span id="lmp-mult-btn-label">LMP×' + multLabel + '</span>';
                if (!table) {
                    showToast('error', 'Table not ready');
                    return;
                }

                const useSelection = typeof selectedSkus !== 'undefined' && selectedSkus.size > 0;
                const rowsToProcess = [];
                const seen = new Set();

                table.getRows('active').forEach(function(r) {
                    const rd = r.getData();
                    if (!rd || rd.is_parent_summary || rd.is_parent_row) return;
                    const sku = rd['(Child) sku'];
                    if (!sku || seen.has(sku)) return;
                    if (useSelection && !selectedSkus.has(sku)) return;

                    const lmp = parseFloat(rd.lmp_price) || 0;
                    if (lmp <= 0) return;

                    if (!useSelection) {
                        const price = parseFloat(rd['eBay Price']) || 0;
                        if (!(price > lmp)) return; // only red Price > LMP when nothing selected
                    }

                    const sprice = +Number(lmp * mult).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    seen.add(sku);
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice, lmp: lmp });
                });

                if (rowsToProcess.length === 0) {
                    showToast('warning', useSelection
                        ? 'No selected rows have an LMP > 0'
                        : 'No visible rows with Price > LMP and LMP > 0. Select SKUs or filter Red (Price > LMP).');
                    return;
                }

                const scope = useSelection ? 'selected' : 'visible Price > LMP';
                if (!confirm(`Set SPRICE = LMP × ${multLabel} for ${rowsToProcess.length} ${scope} SKU(s)?`)) {
                    return;
                }

                let successCount = 0;
                let errorCount = 0;
                const total = rowsToProcess.length;
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                rowsToProcess.forEach(function(item) {
                    $.ajax({
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { sku: item.sku, sprice: item.sprice },
                        success: function(response) {
                            successCount++;
                            item.row.update({
                                SPRICE: item.sprice,
                                SPFT: response.spft_percent != null ? response.spft_percent : 0,
                                SROI: response.sroi_percent != null ? response.sroi_percent : 0,
                                SGROI: response.sgroi_percent != null ? response.sgroi_percent : 0,
                                SGPFT: response.sgpft_percent != null ? response.sgpft_percent : 0,
                                SPRICE_STATUS: 'saved',
                                has_custom_sprice: true
                            });
                            item.row.reformat();
                        },
                        error: function() { errorCount++; },
                        complete: function() {
                            if (successCount + errorCount !== total) return;
                            $btn.prop('disabled', false).html(btnHtml);
                            refreshLmpMultUi();
                            if (errorCount === 0) {
                                showToast('success', `SPRICE = LMP × ${multLabel} saved for ${successCount} SKU(s)`);
                            } else {
                                showToast('error', `Saved ${successCount} of ${total} (${errorCount} failed)`);
                            }
                            if (useSelection) {
                                selectedSkus.clear();
                                $('.sku-select-checkbox').prop('checked', false);
                                $('#select-all-checkbox').prop('checked', false);
                                if (typeof updateSelectedCount === 'function') updateSelectedCount();
                            }
                        }
                    });
                });
            });

            // Badge click handlers for filtering
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
                $(this).toggleClass('bg-secondary', !missingLFilterActive)
                       .toggleClass('bg-danger', missingLFilterActive);
                applyFilters();
            });

            $('#missing-m-count-badge').on('click', function() {
                missingMFilterActive = !missingMFilterActive;
                $(this).toggleClass('bg-secondary', !missingMFilterActive)
                       .toggleClass('bg-danger', missingMFilterActive);
                applyFilters();
            });

            // Clear SPRICE button handler (in selection container)
            $('#clear-sprice-selected-btn').on('click', function() {
                if (confirm('Are you sure you want to clear SPRICE for selected SKUs?')) {
                    clearSpriceForSelected();
                }
            });

            // Apply All button handler
            $(document).on('click', '#apply-all-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.applyAllSelectedPrices();
            });

            // SKU chart days filter (Rolling Lx · PT — same pattern as all-marketplace-master)
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
                $('#discount-input-container').toggle(count > 0 || decreaseModeActive || increaseModeActive ||
                    samePriceModeActive);
            }

            // Update select all checkbox state (matching Amazon approach)
            function updateSelectAllCheckbox() {
                if (!table) return;

                const filteredData = ebayRowsForHeaderSelectAll();

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

            // Background retry storage key
            const BACKGROUND_RETRY_KEY = 'ebay_failed_price_pushes';

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

                        // Skip if account is restricted (check status in table if available)
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

            // Retry function for saving SPRICE (only 1 retry for eBay)
            function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
                return new Promise((resolve, reject) => {
                    // Update status to processing
                    if (row) {
                        row.update({
                            SPRICE_STATUS: 'processing'
                        });
                    }

                    $.ajax({
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: sprice
                        },
                        success: function(response) {
                            // Re-find row by SKU so we update the current row (avoids blank S PRC if table redrew)
                            let targetRow = row;
                            if (table && table.getRows) {
                                table.getRows().forEach(function(r) {
                                    if (r.getData()['(Child) sku'] === sku) targetRow =
                                        r;
                                });
                            }
                            const numSprice = typeof sprice === 'number' && !isNaN(sprice) ?
                                sprice : parseFloat(sprice);
                            if (targetRow) {
                                targetRow.update({
                                    SPRICE: numSprice,
                                    SPFT: response.spft_percent != null ? response
                                        .spft_percent : 0,
                                    SROI: response.sroi_percent != null ? response
                                        .sroi_percent : 0,
                                    SGROI: response.sgroi_percent != null ? response
                                        .sgroi_percent : 0,
                                    SGPFT: response.sgpft_percent != null ? response
                                        .sgpft_percent : 0,
                                    SPRICE_STATUS: numSprice > 0 ? 'saved' : null,
                                    has_custom_sprice: numSprice > 0
                                });
                                targetRow.reformat();
                            }
                            resolve(response);
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.error || xhr.responseText ||
                                'Failed to save SPRICE';
                            console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`,
                                errorMsg);

                            // Only retry once (retryCount < 1)
                            if (retryCount < 1) {
                                console.log(`Retrying SKU ${sku} in 2 seconds...`);
                                setTimeout(() => {
                                    saveSpriceWithRetry(sku, sprice, row, retryCount +
                                            1)
                                        .then(resolve)
                                        .catch(reject);
                                }, 2000);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                // Update status to error
                                if (row) {
                                    row.update({
                                        SPRICE_STATUS: 'error'
                                    });
                                }
                                reject({
                                    error: true,
                                    xhr: xhr
                                });
                            }
                        }
                    });
                });
            }

            // Apply price with retry logic (for pushing to eBay)
            async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
                const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
                const row = cell ? cell.getRow() : null;
                const rowData = row ? row.getData() : null;

                // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
                if (isBackgroundRetry) {
                    try {
                        const response = await $.ajax({
                            url: '/push-ebay-price-tabulator',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                price: price
                            }
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
                // Set initial loading state (only if cell exists)
                if (retries === 0 && cell && $btn && row) {
                    $btn.prop('disabled', true);
                    $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                    $btn.attr('style',
                    'border: none; background: none; color: #ffc107; padding: 0;'); // Yellow text, no background
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'processing';
                        row.update(rowData);
                    }
                }

                try {
                    const response = await $.ajax({
                        url: '/push-ebay-price-tabulator',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            price: price
                        }
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
                        $btn.attr('style',
                        'border: none; background: none; color: #28a745; padding: 0;'); // Green text, no background
                    }

                    if (!isBackgroundRetry) {
                        showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                    }

                    return true;
                } catch (xhr) {
                    const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr
                        .responseJSON?.message || 'Failed to apply price';
                    const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                    console.error(`Attempt ${retries + 1} for SKU ${sku} failed:`, errorMsg);

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
                            $btn.attr('style',
                            'border: none; background: none; color: #ff6b00; padding: 0;'); // Orange text for restriction
                            $btn.attr('title', 'Account restricted - cannot update price');
                        }

                        showToast(
                            `Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`,
                            'error');
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
                            $btn.attr('style',
                            'border: none; background: none; color: #dc3545; padding: 0;'); // Red text, no background
                        }

                        // Save for background retry (only if not already a background retry)
                        saveFailedSkuForRetry(sku, price, 0);
                        showToast(
                            `Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`,
                            'error');

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
                            url: '/push-ebay-price-tabulator',
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
                                    const errorMsg = response.errors[0].message ||
                                        'Unknown error';
                                    const errorCode = response.errors[0].code || '';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                        errorMsg, 'Code:', errorCode);

                                    if (attempt < maxRetries) {
                                        console.log(
                                            `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                            );
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({
                                            error: true,
                                            response: response
                                        });
                                    }
                                } else {
                                    console.log(
                                        `Successfully pushed price for SKU ${sku} on attempt ${attempt}`
                                        );
                                    resolve({
                                        success: true,
                                        response: response
                                    });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr
                                    .responseJSON?.error || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                    errorMsg);

                                if (attempt < maxRetries) {
                                    console.log(
                                        `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                        );
                                    setTimeout(attemptApply, delay);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku}`);
                                    reject({
                                        error: true,
                                        xhr: xhr
                                    });
                                }
                            }
                        });
                    }

                    attemptApply();
                });
            }

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
                            skusToProcess.push({
                                sku: sku,
                                price: sprice
                            });
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
                            showToast(
                                `Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`,
                                'success');

                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.html(originalHtml);
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast(
                                `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`,
                                'error');
                        }
                        return;
                    }

                    const {
                        sku,
                        price
                    } = skusToProcess[currentIndex];

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
                                $btnInCell.attr('style',
                                    'border: none; background: none; color: #ffc107; padding: 0;');
                            }
                        }
                    }

                    // First save to database (like SPRICE edit does), then push to eBay
                    console.log(
                        `Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`
                        );

                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            sku: sku,
                            sprice: price,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(saveResponse) {
                            console.log(`SKU ${sku}: Database save successful`, saveResponse);
                            if (saveResponse.error) {
                                console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse
                                    .error);
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
                                            $btnInCell.attr('style',
                                                'border: none; background: none; color: #dc3545; padding: 0;'
                                                );
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

                            // After saving, push to eBay using retry function
                            console.log(`SKU ${sku}: Starting eBay price push...`);
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
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                    '<i class="fa-solid fa-check-double"></i>'
                                                    );
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #28a745; padding: 0;'
                                                    );
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
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                '<i class="fa-solid fa-x"></i>');
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #dc3545; padding: 0;'
                                                    );
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
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr
                                .responseJSON || xhr.responseText);
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
                                        $btnInCell.attr('style',
                                            'border: none; background: none; color: #dc3545; padding: 0;'
                                            );
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

            // Apply discount to selected SKUs (same flow as Amazon: validate, round .99/.49, re-find row on save)
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

                if (rawInput === '' || rawInput == null) {
                    showToast(samePriceModeActive ? 'Please enter a price' : 'Please enter a value (% or $)', 'error');
                    return;
                }
                if (samePriceModeActive) {
                    if (isNaN(inputValue) || inputValue <= 0) {
                        showToast('Please enter a valid positive price', 'error');
                        return;
                    }
                } else {
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
                    if (selectedSkus.has(sku)) {
                        const originalPrice = parseFloat(row['eBay Price']) || 0;
                        // Same Price mode applies a flat entered price to every selected row,
                        // so it doesn't need an existing eBay price; other modes do.
                        if (!samePriceModeActive && originalPrice <= 0) {
                            return;
                        }

                        let newPriceNum;
                        if (samePriceModeActive) {
                            // Apply the exact price the user typed to all selected rows.
                            newPriceNum = parseFloat(inputValue.toFixed(2));
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
                            tableRow.update({
                                SPRICE: newPriceNum,
                                SPRICE_STATUS: 'processing'
                            });
                        }

                        saveSpriceWithRetry(sku, newPriceNum, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE set to $${inputValue.toFixed(2)} for ${updatedCount} SKU(s)` :
                                            `Discount applied to ${updatedCount} SKU(s)`,
                                            'success'
                                        );
                                    } else {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                            `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                            'error'
                                        );
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) tableRow.update({
                                    SPRICE: originalSPrice
                                });
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(
                                        appliedAsSamePrice ?
                                        `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                        `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                        'error'
                                    );
                                }
                            });
                    }
                });
            }

            // Clear SPRICE for selected SKUs (same method as Amazon: batch POST to clear endpoint, then update table)
            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first', 'error');
                    return;
                }

                if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                    return;
                }

                let clearedCount = 0;
                const updates = [];

                table.getRows().forEach(row => {
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT')) return;

                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SGROI: 0,
                        SROI: 0,
                        SPRICE_STATUS: null,
                        has_custom_sprice: false
                    });
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    clearedCount++;
                });

                if (updates.length > 0) {
                    $.ajax({
                        url: '/ebay-clear-sprice',
                        method: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json'
                        },
                        data: JSON.stringify({
                            updates: updates
                        }),
                        success: function(response) {
                            showToast(response.message || `SPRICE cleared for ${clearedCount} SKU(s)`,
                                'success');
                        },
                        error: function(xhr) {
                            console.error('Failed to clear SPRICE:', xhr.status, xhr.responseJSON || xhr
                                .responseText);
                            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON
                                .error : 'Failed to clear SPRICE data';
                            showToast(msg, 'error');
                        }
                    });
                } else {
                    showToast('warning', 'No SPRICE values to clear for selected SKUs');
                }
            }

            // Build parent list from table (same logic as dropdown in dataLoaded) - call when needed so Play always has list
            function buildProductUniqueParentsFromTable() {
                if (typeof table === 'undefined' || !table) return [];
                var allRows = table.getData('all') || [];
                var seen = {};
                var list = [];
                allRows.forEach(function(r) {
                    var p = (r.Parent || '').toString().trim();
                    if (p && !String(p).toUpperCase().startsWith('PARENT') && !seen[p]) {
                        seen[p] = true;
                        list.push(p);
                    }
                });
                list.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                return list;
            }

            // Play / Pause parent navigation (same as product-master) - productUniqueParents set in dataLoaded or on first Play
            function initProductPlaybackControls() {
                if (typeof table === 'undefined' || !table) return;
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }

                // Use event delegation so clicks work even if buttons are re-rendered (same as product-master behavior)
                $(document).off('click.ebayplay', '#play-forward').on('click.ebayplay', '#play-forward',
                    productNextParent);
                $(document).off('click.ebayplay', '#play-backward').on('click.ebayplay', '#play-backward',
                    productPreviousParent);
                $(document).off('click.ebayplay', '#play-pause').on('click.ebayplay', '#play-pause',
                    productStopNavigation);
                $(document).off('click.ebayplay', '#play-auto').on('click.ebayplay', '#play-auto',
                    productStartNavigation);

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
                $('#play-forward').prop('disabled', !isProductNavigationActive || currentProductParentIndex >=
                    productUniqueParents.length - 1);
                $('#play-auto').attr('title', isProductNavigationActive ? 'Show all products' :
                    'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', 'Next parent');
                $('#play-backward').attr('title', 'Previous parent');
                if (isProductNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            // Image hover preview (forecast.analysis pattern)
            let ebayMpImagePreviewHideTimer = null;
            let ebayMpImagePreviewEl = null;

            function ebayMpRemoveImagePreview() {
                if (ebayMpImagePreviewHideTimer) {
                    clearTimeout(ebayMpImagePreviewHideTimer);
                    ebayMpImagePreviewHideTimer = null;
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
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
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
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

            // Event delegation for eye button clicks (add to SKU column formatter)
            table = new Tabulator("#ebay-table", {
                ajaxURL: EBAY_DATA_JSON_URL,
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    var start = currentRow;
                    var end = Math.min(currentRow + pageSize - 1, totalRows);
                    var text = totalRows > 0
                        ? "Showing " + start + "-" + end + " of " + totalRows + " rows"
                        : "Showing 0 of 0 rows";
                    $('#custom-pagination-counter').text(text);
                    return "";
                },
                columnCalcs: "both",
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
                    },
                    {
                        column: "_parent_sort",
                        dir: "asc"
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    const isParent = data.Parent && String(data.Parent).toUpperCase().startsWith(
                        'PARENT');
                    const el = row.getElement();
                    if (isParent) {
                        el.classList.add('ebay-parent-row');
                        el.style.setProperty('background-color', '#b3e5fc', 'important');
                    } else {
                        el.classList.remove('ebay-parent-row');
                    }
                },
                columns: [{
                        title: "",
                        field: "_parent_sort",
                        visible: false,
                        width: 0
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
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="No extra filter: this page only. If filter/search is on: all matching rows (all pages).">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            const isSelected = sku ? selectedSkus.has(sku) : false;
                            if (isParent) {
                                return '<input type="checkbox" class="sku-select-checkbox" data-sku="' +
                                    (sku || '') +
                                    '" disabled style="cursor: not-allowed; opacity: 0.6;">';
                            }
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku || ''}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
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
                                return '<img src="' + u + '" data-full="' + u +
                                    '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                            }
                            return '';
                        },
                        cellMouseOver: function(e, cell) {
                            const img = cell.getElement().querySelector('.hover-thumb');
                            if (!img) return;
                            ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute(
                                'data-full'));
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
                            if (related && typeof related.closest === 'function' && related.closest(
                                    '#image-hover-preview')) {
                                ebayMpCancelImagePreviewHide();
                                return;
                            }
                            ebayMpScheduleImagePreviewHide();
                        },
                        headerSort: false,
                        width: 80
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            if (String(value).toUpperCase().startsWith('PARENT ')) {
                                return String(value).replace(/^PARENT\s+/i, '').trim();
                            }
                            return value;
                        }
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold",
                        tooltip: true,
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let sku = cell.getValue();
                            if (!sku && rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT')) {
                                sku = rowData.Parent;
                            }
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            let html =
                                `<span class="${isParent ? 'fw-bold text-primary' : ''}">${sku || ''}</span>`;
                            if (sku) {
                                html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                            }
                            return html;
                        }
                    },
                    {
                        title: "Ratings",
                        field: "rating",
                        hozAlign: "center",
                        editor: "input",
                        tooltip: "Enter rating between 0 and 5",
                        width: 80,
                        visible: false
                    },
                    {
                        title: "Links",
                        field: "links_column",
                        frozen: true,
                        width: 55,
                        hozAlign: "center",
                        visible: true,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['B Link'] || '';
                            const sellerLink = rowData['S Link'] || '';

                            let html =
                                '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';

                            if (sellerLink) {
                                html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> S
                                </a>`;
                            }

                            if (buyerLink) {
                                html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> B
                                </a>`;
                            }

                            if (!sellerLink && !buyerLink) {
                                html +=
                                '<span class="text-muted" style="font-size: 12px;">-</span>';
                            }

                            html += '</div>';
                            return html;
                        },
                        headerSort: false
                    },

                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
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

                            // Color logic from inc/dec page - getDilColor
                            if (dil < 25) color = '#a00211'; // red (absorbs former yellow band)
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "CVR 60",
                        field: "CVR_60",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue()) || 0;
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                        },
                        width: 60
                    },
                    {
                        title: "CVR 45",
                        field: "CVR_45",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const val = parseFloat(cell.getValue()) || 0;
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                        },
                        width: 60
                    },
                    {
                        title: "CVR 30",
                        field: "SCVR",
                        hozAlign: "center",
                        // No fixed width — fitDataStretch sizes to value + arrow + chart dot
                        minWidth: 88,
                        resizable: true,
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
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
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
                                arrowHtml =
                                    ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                            }
                            const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' :
                                (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            const sku = rowData['(Child) sku'] || '';
                            const dotBtn = (sku && !isParent) ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${sku}" data-metric="cvr" title="View CVR chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${dotColor};"></span></button>` : '';
                            return `<span style="color: ${color}; font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; gap: 2px;">${val.toFixed(1)}%${arrowHtml}${dotBtn ? ' ' + dotBtn : ''}</span>`;
                        },
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
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var sku = rowData['(Child) sku'];
                            var nrlValue = rowData.NRL || 'REQ';
                            var defaultValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                            var value = (cell.getValue() || '').trim() || defaultValue;
                            return `<select class="form-select form-select-sm kw-nra-dropdown" 
                                        data-sku="${sku}" data-field="NR"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                    </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                    {
                        title: "E L60",
                        field: "eBay L60",
                        visible: false,
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
                        }
                    },
                    {
                        title: "E L45",
                        field: "eBay L45",
                        visible: false,
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
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
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
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
                        title: "Missing L",
                        field: "Missing",
                        hozAlign: "center",
                        width: 70,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith(
                                    'PARENT')) {
                                return '';
                            }
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
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
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '';
                            }
                            const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                            const inv = parseFloat(rowData['INV']) || 0;
                            // Same as /map-issues: both sides must have stock to be Map / N Map.
                            if (inv > 0 && ebayStock > 0) {
                                // Mapped (green) when within /map-issues tolerance (3 units or rounded 3%)
                                if (ebayInvWithinMapTolerance(inv, ebayStock)) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                }
                                const diff = inv - ebayStock;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                            return '';
                        }
                    },

                    {
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith(
                                'PARENT');

                            // Don't show dropdown for parent rows
                            // if (isParent) {
                            //     return '';
                            // }

                            // Get value and handle null/undefined/empty cases
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value
                                .trim() === '') {
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
                        title: "NRP",
                        field: "nrp",
                        hozAlign: "center",
                        sorter: "string",
                        headerSort: true,
                        width: 56,
                        minWidth: 52,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) {
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
                            return (
                                '<div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="' +
                                escAttr(tip + ' (click to change)') + '">' +
                                '<span class="nrp-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>' +
                                '<select class="form-select form-select-sm nrp-nr-select position-absolute top-0 start-0 w-100 h-100" ' +
                                'data-sku="' + escAttr(sku) + '" data-parent="' + escAttr(parent) + '" ' +
                                'aria-label="NRP: ' + escAttr(tip) + '">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>REQ</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>2BDC</option>' +
                                '<option value="LATER"' + (value === 'LATER' ? ' selected' : '') + '>LATER</option>' +
                                '</select></div>'
                            );
                        },
                        cellClick: function(e, cell) { e.stopPropagation(); }
                    },

                    {
                        title: "Prc",
                        field: "eBay Price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const rowData = cell.getRow().getData();
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);
                            const sku = rowData['(Child) sku'] || '';
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');

                            if (value === 0) {
                                if (sku && !isParent) {
                                    return `<span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: #a00211; font-weight: 600; cursor: pointer;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                                }
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }

                            const priceFormatted = '$' + value.toFixed(2);
                            const priceColor = (lmpPrice > 0 && value > lmpPrice) ? '#dc3545' : 'inherit';
                            const priceWeight = (lmpPrice > 0 && value > lmpPrice) ? '600' : 'normal';
                            if (sku && !isParent) {
                                return `<span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: ${priceColor}; font-weight: ${priceWeight}; cursor: pointer;">${priceFormatted}</span>`;
                            }
                            if (lmpPrice > 0 && value > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 600;">${priceFormatted}</span>`;
                            }
                            return priceFormatted;
                        },
                        width: 70
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

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 50
                    },


                    {
                        title: "NPFT",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const ads = (typeof EBAY_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) : 0;
                            return ((parseFloat(aRow.getData()['GPFT%'] || 0) - ads) - (parseFloat(bRow.getData()['GPFT%'] || 0) - ads));
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const ads = (typeof EBAY_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) : 0;
                            // NPFT% = GPFT% − Ads% (channel TACOS)
                            const percent = (parseFloat(rowData['GPFT%'] || 0)) - ads;
                            let color = '';

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: function(values, data) {
                            const ads = (typeof EBAY_CHANNEL_ADS_PCT !== 'undefined') ? (parseFloat(EBAY_CHANNEL_ADS_PCT) || 0) : 0;
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
                        title: "GROI%",
                        field: "ROI%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            let color = '';

                            // getRoiColor logic from inc/dec page
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

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
                            const aNet = ebayComputeNetRoi(aRow.getData(), 'eBay Price');
                            const bNet = ebayComputeNetRoi(bRow.getData(), 'eBay Price');
                            return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                                 - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                        },
                        formatter: function(cell) {
                            const percent = ebayComputeNetRoi(cell.getRow().getData(), 'eBay Price');
                            if (percent === null || !isFinite(percent)) return '';
                            let color = '';

                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let sum = 0, n = 0;
                            data.forEach(r => {
                                const v = ebayComputeNetRoi(r, 'eBay Price');
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
                        // Channel-level eBay Ads% (TACOS). Same value for every row —
                        // per-SKU ad spend isn't in this page's data; mirrors the eBay
                        // Ads % shown on /all-marketplace-master.
                        title: "Ads %",
                        field: "_ads_pct",
                        hozAlign: "center",
                        headerSort: false,
                        headerTooltip: "eBay channel Ads% (Total Ad Spend / L30 Sales × 100). Channel-level — same value on /all-marketplace-master.",
                        formatter: function() {
                            const pct = parseFloat(EBAY_CHANNEL_ADS_PCT) || 0;
                            let color = pct < 5 ? '#28a745' : (pct <= 10 ? '#ffc107' : '#a00211');
                            return `<span style="color: ${color}; font-weight: 600;">${pct.toFixed(1)}%</span>`;
                        },
                        width: 65
                    },


                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const lmpPrice = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const totalCompetitors = rowData.lmp_entries_total || 0;
                            const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                            const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));
                            const skuAttr = escapeHtmlAttr(sku || '');
                            const countHtml = totalCompetitors > 0
                                ? ` <span style="color:#007bff;font-weight:500;font-size:12px;">(${totalCompetitors})</span>`
                                : '';

                            // Compact: $34.50 (9) — click opens competitors drawer
                            if (lmpPrice) {
                                return `<a href="#" class="view-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color: inherit; text-decoration: none; cursor: pointer; white-space: nowrap;"
                                    title="Open LMP competitors">
                                    <span style="font-weight: 600; font-size: 14px;">$${parseFloat(lmpPrice).toFixed(2)}</span>${countHtml}
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
                        title: "T Prc",
                        field: "target_price",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const mult = (typeof getLmpMult === 'function') ? getLmpMult() : 0.98;
                            const av = (parseFloat(aRow.getData().lmp_price) || 0) * mult;
                            const bv = (parseFloat(bRow.getData().lmp_price) || 0) * mult;
                            return av - bv;
                        },
                        headerTooltip: "Target Price = LMP × LMP× factor (same factor as the yellow LMP× control). Empty when no LMP.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const lmp = parseFloat(rowData.lmp_price) || 0;
                            if (lmp <= 0) return '<span class="text-muted">—</span>';
                            const mult = (typeof getLmpMult === 'function') ? getLmpMult() : 0.98;
                            const tp = +Number(lmp * mult).toFixed(2);
                            if (!isFinite(tp) || tp <= 0) return '<span class="text-muted">—</span>';
                            const price = parseFloat(rowData['eBay Price']) || 0;
                            // Red if our live price is still above this target
                            const color = (price > 0 && price > tp) ? '#dc3545' : '#0d6efd';
                            return `<span style="color:${color};font-weight:600;" title="LMP $${lmp.toFixed(2)} × ${mult}">$${tp.toFixed(2)}</span>`;
                        },
                        width: 72
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
                            const spriceNum = (value != null && value !== '') ? parseFloat(value) :
                                NaN;
                            const sprice = isNaN(spriceNum) ? 0 : spriceNum;

                            // Blank only when SPRICE is missing or zero (no override)
                            if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0)
                                return '';

                            // Always show SPRICE when it has a value — even if it equals the eBay price.

                            const formattedValue = `$${Number(sprice).toFixed(2)}`;
                            // If SPRICE is above the LMP (lowest market price), flag it in red.
                            const lmp = parseFloat(rowData.lmp_price) || 0;
                            if (lmp > 0 && sprice > lmp) {
                                return `<span style="color: #dc3545; font-weight: 600;">${formattedValue}</span>`;
                            }
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
                                <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                    <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                </button>
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');

                            // if (isParent) return '';

                            const sku = rowData['(Child) sku'];
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            const status = rowData.SPRICE_STATUS || null;

                            if (!sprice || sprice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745'; // Green for ready
                            let titleText = 'Apply Price to eBay';

                            if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107'; // Yellow text
                                titleText = 'Price pushing in progress...';
                            } else if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText =
                                'Price pushed to eBay (Double-click to mark as Applied)';
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'Price applied to eBay (Double-click to change)';
                            } else if (status === 'saved') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'SPRICE saved (Click to push to eBay)';
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545'; // Red text
                                titleText = 'Error applying price to eBay';
                            } else if (status === 'account_restricted') {
                                icon = '<i class="fa-solid fa-ban"></i>';
                                iconColor = '#ff6b00'; // Orange text
                                titleText =
                                    'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
                            }

                            // Show only icon with color, no background
                            return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                                ${icon}
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            const $target = $(e.target);

                            // Handle double-click to change status from 'pushed' to 'applied'
                            if (e.originalEvent && e.originalEvent.detail === 2) {
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const currentStatus = $btn.attr('data-status') || '';

                                if (currentStatus === 'pushed') {
                                    const sku = $btn.attr('data-sku') || $btn.data('sku');
                                    $.ajax({
                                        url: '/update-ebay-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: {
                                            sku: sku,
                                            status: 'applied'
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                table.replaceData();
                                                showToast('Status updated to Applied',
                                                    'success');
                                            }
                                        }
                                    });
                                }
                                return;
                            }

                            if ($target.hasClass('apply-price-btn') || $target.closest(
                                    '.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data(
                                    'price'));
                                const currentStatus = $btn.attr('data-status') || '';

                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('Invalid SKU or price', 'error');
                                    return;
                                }

                                // If status is 'saved' or null, first save SPRICE, then push to eBay
                                if (currentStatus === 'saved' || !currentStatus) {
                                    const row = cell.getRow();
                                    row.update({
                                        SPRICE_STATUS: 'processing'
                                    });

                                    saveSpriceWithRetry(sku, price, row)
                                        .then((response) => {
                                            // After saving, push to eBay
                                            applyPriceWithRetry(sku, price, cell, 0);
                                        })
                                        .catch((error) => {
                                            row.update({
                                                SPRICE_STATUS: 'error'
                                            });
                                            showToast('Failed to save SPRICE', 'error');
                                        });
                                } else {
                                    // If already saved, just push to eBay
                                    applyPriceWithRetry(sku, price, cell, 0);
                                }
                            }
                        }
                    },

                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        visible: false,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "SNPFT",
                        field: "SPFT",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            // SNPFT = S GPFT − Ads% (net of channel ad spend).
                            const rawGpft = rowData.SGPFT;
                            if (rawGpft === null || rawGpft === undefined || rawGpft === '') return '';
                            const sgpft = parseFloat(rawGpft);
                            if (isNaN(sgpft)) return '';
                            const ads = parseFloat(EBAY_CHANNEL_ADS_PCT) || 0;
                            const percent = sgpft - ads;

                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S GROI",
                        field: "SGROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as GROI% / ROI% color logic
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

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
                            const aNet = ebayComputeNetRoi(aRow.getData(), 'SPRICE');
                            const bNet = ebayComputeNetRoi(bRow.getData(), 'SPRICE');
                            return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                                 - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                        },
                        formatter: function(cell) {
                            const percent = ebayComputeNetRoi(cell.getRow().getData(), 'SPRICE');
                            if (percent === null || !isFinite(percent)) return '';

                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 40) color = '#a00211'; // red
                            else if (percent < 75) color = '#ffc107'; // yellow
                            else if (percent < 125) color = '#28a745'; // green
                            else color = '#d63384'; // magenta

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },

                    // === Campaign-Ads columns (ES BID / C BID / PROMOTE) ===
                    // Same source & formatters as /ebay/campaign-ads. SKU-wise via listing_id; rows
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
                            return `<span class="text-info fw-semibold">${Math.round(v)}%</span>`;
                        }
                    },
                    {
                        title: "L30 View",
                        field: "views",
                        hozAlign: "center",
                        sorter: "number",
                        width: 72,
                        headerTooltip: "L30 views. Click value for Rolling L30 history (same as Prc). Arrow: L7 pace vs L30 pace.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = parseFloat(cell.getValue() || 0);
                            const color = value >= 30 ? '#28a745' : '#a00211';
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            const sku = rowData['(Child) sku'] || '';
                            const variation = viewsPaceVariation(rowData);
                            const arrowBtn = viewsHistoryArrowBtn(sku, isParent, variation, 'views');
                            const num = Math.round(value);
                            // Same history entry as Prc column → Rolling L30 skuMetricsModal
                            if (sku && !isParent) {
                                return `<span class="view-sku-chart" data-sku="${sku}" data-metric="views" title="View L30 View chart" style="color: ${color}; font-weight: 600; cursor: pointer; white-space: nowrap;">${num}</span> ${arrowBtn}`.trim();
                            }
                            return `<span style="color: ${color}; font-weight: 600;">${num}</span> ${arrowBtn}`.trim();
                        }
                    },
                    {
                        title: "L7 View",
                        field: "l7_views",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        headerTooltip: "L7 views. RED text = below avg L7. Green = avg–2× avg. Pink = ≥ 2× avg. Arrow = L7 vs L30 pace; click for L7 history.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = Number(cell.getValue());
                            const l7Val = Number.isFinite(value) ? value : 0;
                            const avg = Number(avgL7ViewsGlobal) || 0;
                            // Below channel avg L7 → always RED text (e.g. L7=2 vs avg≈15)
                            let textColor = '#212529';
                            if (avg > 0 && l7Val < avg) {
                                textColor = '#a00211';
                            } else if (avg > 0 && l7Val < avg * 2) {
                                textColor = '#28a745';
                            } else if (avg > 0) {
                                textColor = '#d63384';
                            } else if (l7Val <= 0) {
                                textColor = '#a00211';
                            }
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                            const sku = rowData['(Child) sku'] || '';
                            const variation = viewsPaceVariation(rowData);
                            const arrowBtn = viewsHistoryArrowBtn(sku, isParent, variation, 'l7_views');
                            const tip = avg > 0
                                ? (l7Val < avg ? 'Below avg L7 (' + avg.toFixed(1) + ')' : 'Avg L7 ' + avg.toFixed(1))
                                : 'L7 views';
                            return `<span title="${tip}" style="color: ${textColor} !important; font-weight: 600;">${Math.round(l7Val).toLocaleString()}</span> ${arrowBtn}`.trim();
                        }
                    },
                    {
                        title: "L7 %",
                        field: "l7_views_chg_pct",
                        hozAlign: "center",
                        sorter: "number",
                        width: 72,
                        headerTooltip: "% increase / decrease of L7 Views vs the previous same period (days 8–14). Green = up, red = down. NEW = prior period was 0.",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const cur = parseFloat(row.l7_views) || 0;
                            const prevRaw = row.l7_views_prev;
                            const pct = cell.getValue();
                            // No prior snapshot yet (history < ~7 days of l7_views) → —
                            if (prevRaw === null || prevRaw === undefined || prevRaw === '') {
                                return '<span class="text-muted" title="No prior-period L7 snapshot yet">—</span>';
                            }
                            const prev = parseFloat(prevRaw) || 0;
                            if (prev <= 0 && cur > 0) {
                                return `<span title="Prior period had 0 views" style="color:#28a745; font-weight:700;">NEW</span>`;
                            }
                            if (pct === null || pct === undefined || pct === '' || !isFinite(parseFloat(pct))) {
                                return '<span class="text-muted">—</span>';
                            }
                            const v = parseFloat(pct);
                            const color = v > 0 ? '#28a745' : (v < 0 ? '#a00211' : '#6c757d');
                            const sign = v > 0 ? '+' : '';
                            const tip = `L7 ${Math.round(cur).toLocaleString()} vs prior ${Math.round(prev).toLocaleString()} (same period, days 8–14)`;
                            return `<span title="${tip}" style="color:${color}; font-weight:700;">${sign}${v.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "C BID",
                        field: "ca_bid_percentage",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        headerTooltip: "Red if C BID > Ads% badge, otherwise green.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span class="text-muted">—</span>';
                            const color = v > EBAY_CHANNEL_ADS_PCT ? '#a00211' : '#28a745';
                            return `<span style="color:${color}; font-weight:600;">${Math.round(v)}%</span>`;
                        }
                    },
                    {
                        title: "S BID",
                        field: "ca_suggested_bid",
                        hozAlign: "center",
                        width: 90,
                        headerTooltip: "S Bid from Sbid Rule slabs (For L7 Views / CVR). Red if S BID > Ads% badge, otherwise green.",
                        sorter: function(a, b, aRow, bRow) {
                            return getCombinedSbid(aRow.getData()).bid - getCombinedSbid(bRow.getData()).bid;
                        },
                        formatter: function(cell) {
                            const res = getCombinedSbid(cell.getRow().getData());
                            if (res.skip) {
                                return '<span class="text-muted" title="No matching Sbid Rule slab" style="font-size:11px;">—</span>';
                            }
                            const color = res.bid > EBAY_CHANNEL_ADS_PCT ? '#a00211' : '#28a745';
                            return `<span style="color:${color}; font-weight:700;">${Math.round(res.bid)}%</span>`;
                        }
                    },
                    {
                        title: "PROMOTE",
                        field: "ca_promote_with_ad",
                        hozAlign: "center",
                        headerTooltip: "eBay Promotion eligibility status (from /ebay/campaign-ads)",
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

            $(document).on('change', '#ebay-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.searchRows('(Child) sku', '=', sku);
                const row = rows && rows.length ? rows[0] : null;
                const prevRaw = row ? String(row.getData().nrp ?? '').trim().toUpperCase() : '';
                const prevSelect = (prevRaw === 'NR' || prevRaw === 'LATER') ? prevRaw : 'REQ';
                ebayUpdateForecastNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {
                        if (row) {
                            row.update({ nrp: newValue }, true);
                            const nrCell = row.getCells().find(function(c) { return c.getField() === 'nrp'; });
                            if (nrCell) nrCell.reformat();
                        }
                        if (typeof showToast === 'function') showToast('success', 'NRP saved');
                    },
                    function() {
                        $el.val(prevSelect);
                    }
                );
            });

            // Parent & SKU Search functionality
            $('#parent-search, #sku-search').on('keyup', function() {
                table.setFilter([
                    { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' },
                    { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' }
                ]);
                setTimeout(function() {
                    if (typeof updateSelectAllCheckbox === 'function') updateSelectAllCheckbox();
                }, 50);
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
                row.update({
                    nr_req: value
                });

                // Save to database using listing_ebay endpoint
                $.ajax({
                    url: '/listing_ebay/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('NR/REQ saved successfully for', sku, 'value:', value);
                            const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ?
                                'NR updated' : 'Status cleared');
                            showToast('success', message);
                        } else {
                            showToast('error', response.message || 'Failed to save status');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr
                            .responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
                    }
                });
            });

            // NRL listing-status dropdown change handler
            $(document).on('change', '.kw-nrl-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRL updated');
                            // Update row data
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NRL: value
                                });
                                // If NRL set to NRL, auto-set NRA to NRA
                                if (value === 'NRL') {
                                    rows[0].update({
                                        NR: 'NRA'
                                    });
                                    $.ajax({
                                        url: '/update-ebay-nr-data',
                                        method: 'POST',
                                        contentType: 'application/json',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: JSON.stringify({
                                            sku: sku,
                                            field: 'NR',
                                            value: 'NRA'
                                        })
                                    });
                                }
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRL');
                    }
                });
            });

            // NRA listing-status dropdown change handler
            $(document).on('change', '.kw-nra-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRA updated');
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NR: value
                                });
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRA');
                    }
                });
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

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
                            row.update({
                                rating: numValue
                            });
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
                    row.update({
                        SPRICE_STATUS: 'processing'
                    });

                    saveSpriceWithRetry(data['(Child) sku'], value, row)
                        .then((response) => {
                            showToast('SPRICE saved successfully', 'success');
                        })
                        .catch((error) => {
                            showToast('Failed to save SPRICE', 'error');
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

            /**
             * Inventory mapping tolerance — same rule as /map-issues:
             * when 3% of INV is below 3 units, require an absolute gap > 3 units to be a mismatch;
             * otherwise apply the rounded 3% rule. Mapped (green) when within tolerance.
             */
            function ebayInvWithinMapTolerance(inv, stock) {
                const invNum = parseFloat(inv) || 0;
                const stockNum = parseFloat(stock) || 0;
                if (invNum <= 0) {
                    return true;
                }
                const diff = Math.abs(invNum - stockNum);
                let isNotMap;
                if (invNum * 0.03 < 3) {
                    isNotMap = diff > 3;
                } else {
                    isNotMap = Math.round((diff / invNum) * 100) > 3;
                }
                return !isNotMap;
            }

            /**
             * Missing M (eBay) — same logic as /map-issues N Map (mapping mismatch):
             * listed (has eBay item_id), REQ, INV > 0, eBay Stock > 0, and INV vs eBay Stock is OUTSIDE the map tolerance.
             */
            function isEbayMissingM(data) {
                var d = data || {};
                if (d.is_parent_summary === true) return false;
                var parent = d['Parent'];
                if (parent && String(parent).toUpperCase().startsWith('PARENT')) return false;
                var itemId = d['eBay_item_id'];
                if (!itemId || itemId === null || itemId === '') return false; // not listed -> handled by Missing L
                // REQ only — match the default "REQ Only" view (nr_req can also be NRL / LATER / NR).
                var nr = (d['nr_req'] || '').toString().trim().toUpperCase();
                if (nr !== 'REQ') return false;
                var inv = parseFloat(d['INV'] || 0) || 0;
                if (inv <= 0) return false;
                var ebayStock = parseFloat(d['eBay Stock'] || 0) || 0;
                if (ebayStock <= 0) return false; // same as /map-issues: both sides must have stock
                return !ebayInvWithinMapTolerance(inv, ebayStock);
            }

            /** eBay listing qty: API uses `eBay Stock` (column field); legacy code used `E Stock`. */
            function rowEbayStockQty(data) {
                var d = data || {};
                var v = d['eBay Stock'];
                if (v === undefined || v === null || v === '') v = d['E Stock'];
                return parseFloat(v || 0) || 0;
            }

            /**
             * Missing L (eBay) — same logic as amazon-tabulator-view Missing L:
             * row is NOT listed (no eBay item_id), NR/REQ is not 'NR', INV > 0, and not a parent row.
             */
            function isEbayMissingL(data) {
                var d = data || {};
                if (d.is_parent_summary === true) return false;
                var parent = d['Parent'];
                if (parent && String(parent).toUpperCase().startsWith('PARENT')) return false;
                var itemId = d['eBay_item_id'];
                var notListed = (!itemId || itemId === null || itemId === '');
                // REQ only — match the default "REQ Only" view (nr_req can also be NRL / LATER / NR).
                var nr = (d['nr_req'] || '').toString().trim().toUpperCase();
                var inv = parseFloat(d['INV'] || 0) || 0;
                return notListed && nr === 'REQ' && inv > 0;
            }

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const el30Filter = $('#el30-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const roiFilter = $('#roi-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const cvrTrendFilter = $('#cvr-trend-filter').val();
                const spriceFilter = $('#sprice-filter').val();
                const spriceLmpFilter = $('#sprice-lmp-filter').val();
                const prcLmpFilter = $('#prc-lmp-filter').val();
                const lmpFilter = $('#lmp-filter').val();
                const dilFilter = $('#dil-filter').val() || 'all';
                const l7ViewsFilter = $('#l7-views-filter').val() || 'all';
                const viewTypeFilter = $('#view-type-filter').val() || 'all';

                table.clearFilter(true);

                // When Play is active: show only current parent group (child SKUs + parent summary row, like product-master photo)
                // Skip View so we always show both children and parent row for that group
                if (!isProductNavigationActive) {
                    // View type: All | Parent | SKU (parent = only parent rows; sku = only child SKU rows)
                    if (viewTypeFilter === 'parent') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !!isParent;
                        });
                    } else if (viewTypeFilter === 'sku') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !isParent;
                        });
                    }
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV']) || 0) === 0;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        return (parseFloat(data['INV']) || 0) > 0;
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
                    table.addFilter(function(data) {
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;
                        // Extract CVR from SCVR field
                        const scvrValue = parseFloat(data['SCVR'] || 0);
                        const views = parseFloat(data.views || 0);
                        const l30 = parseFloat(data['eBay L30'] || 0);
                        const cvr = views > 0 ? (l30 / views) * 100 : 0;

                        // Round to 2 decimal places to avoid floating point precision issues
                        const cvrRounded = Math.round(cvr * 100) / 100;

                        if (cvrFilter === '0-0') return cvrRounded === 0;
                        if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                        if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                        if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                        if (cvrFilter === '13plus') return cvrRounded > 13;
                        return true;
                    });
                }

                // CVR trend filter: CVR 60 vs CVR 30 (same as Amazon)
                if (cvrTrendFilter !== 'all') {
                    const cvrTrendTol = 0.1;
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
                        const cvr30 = parseFloat(data['SCVR'] || 0);
                        const cvr60 = parseFloat(data['CVR_60'] || 0);
                        if (cvrTrendFilter === 'l60_gt_l30') return cvr60 > cvr30 + cvrTrendTol;
                        if (cvrTrendFilter === 'l30_gt_l60') return cvr30 > cvr60 + cvrTrendTol;
                        if (cvrTrendFilter === 'equal') return Math.abs(cvr60 - cvr30) <= cvrTrendTol;
                        return true;
                    });
                }

                if (spriceFilter === 'blank') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                // Sprice/LMP: "Red" keeps only rows where SPRICE is shown in red (SPRICE > LMP).
                if (spriceLmpFilter === 'red') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                            return true;
                        const sprice = parseFloat(data.SPRICE) || 0;
                        const lmp = parseFloat(data.lmp_price) || 0;
                        return sprice > 0 && lmp > 0 && sprice > lmp;
                    });
                }

                // Prc/LMP: "Red" keeps only rows where the eBay Price is shown in red (Price > LMP).
                if (prcLmpFilter === 'red') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                            return true;
                        const price = parseFloat(data['eBay Price']) || 0;
                        const lmp = parseFloat(data.lmp_price) || 0;
                        return price > 0 && lmp > 0 && price > lmp;
                    });
                }

                // LMP: "Red" keeps only rows that have no LMP value.
                if (lmpFilter === 'red') {
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                            return true;
                        const lmp = parseFloat(data.lmp_price) || 0;
                        return lmp <= 0;
                    });
                }

                // DIL filter
                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const l30 = parseFloat(data['L30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                        if (dilFilter === 'red') return dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                // L7 Views colour band (same as L7 View column / Sbid Views)
                if (l7ViewsFilter !== 'all') {
                    table.addFilter(function(data) {
                        return l7ViewBand(data.l7_views).key === l7ViewsFilter;
                    });
                }

                // Badge Filters (E Stock > 0 — aligned with E Stock filter)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 === 0 && estock > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 > 0 && estock > 0;
                    });
                }

                if (missingLFilterActive) {
                    table.addFilter(function(data) {
                        return isEbayMissingL(data);
                    });
                }

                if (missingMFilterActive) {
                    table.addFilter(function(data) {
                        return isEbayMissingM(data);
                    });
                }

                // Play / Pause: show only current parent group (child SKUs + parent summary row, like product-master photo)
                if (isProductNavigationActive && productUniqueParents.length > 0 && currentProductParentIndex >=
                    0) {
                    var currentKey = productUniqueParents[currentProductParentIndex];
                    if (currentKey) {
                        table.addFilter(function(data) {
                            var p = (data.Parent || '').toString().trim();
                            return p === currentKey || p === ('PARENT ' + currentKey);
                        });
                    }
                }

                // Update range filter badge
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Update select all checkbox after filter is applied (matching Amazon approach)
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#view-type-filter, #inventory-filter, #el30-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter, #sprice-lmp-filter, #prc-lmp-filter, #lmp-filter, #dil-filter, #l7-views-filter')
                .on('change', function() {
                    applyFilters();
                });

            $('#growth-sign-filter').on('change', function() {
                applyFilters();
            });

            // Columns that should ALWAYS stay hidden, regardless of saved state.
            var alwaysHiddenColumns = ['CVR_60', 'CVR_45', 'eBay L60', 'eBay L45'];
            function enforceAlwaysHiddenColumns() {
                alwaysHiddenColumns.forEach(function(col) {
                    try { table.hideColumn(col); } catch (e) {}
                });
            }

            // No-op kept for compatibility with existing callers.
            function applySectionColumnVisibility(_sectionVal) {
                enforceAlwaysHiddenColumns();
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

                // PFT% and ROI% calculations removed - display elements removed
                // const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // const avgRoi = sumLp > 0 ? (totalProfit / sumLp) * 100 : 0;
            }

            // Update summary badges - use ALL data (not filtered) to match KW/PMP ads pages
            function updateSummary() {
                if (!table) return;
                // Use getData("all") to get ALL data without filters
                const allData = table.getData("all");
                const filteredData = table.getData("active");

                // Filtered data metrics (for other badges)
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;
                filteredData.forEach(row => {
                    const estock = rowEbayStockQty(row);
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);
                    const isParent = row['Parent'] && String(row['Parent']).toUpperCase().startsWith('PARENT');

                    // Financial totals: include ALL sold items (even out-of-stock) so Sales reflects
                    // true eBay sales. Exclude parent summary rows to avoid double counting.
                    if (!isParent) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaL30 += ebayL30;
                    }

                    if (estock > 0) {
                        // Count 0 Sold and > 0 Sold (only E Stock > 0)
                        if (ebayL30 === 0) {
                            zeroSoldCount++;
                        } else {
                            moreSoldCount++;
                        }

                        const dil = parseFloat(row['E Dil%'] || 0);
                        if (!isNaN(dil)) {
                            totalDilPercent += dil;
                            dilCount++;
                        }
                    }
                });

                let totalViews = 0;
                let totalL7Views = 0;
                let l7ViewsCount = 0;
                // Channel-wide avg (all loaded child rows, E Stock > 0) — drives L7 text colour.
                // Do NOT use filtered/parent rows: filters were pulling avg down so low L7
                // values (e.g. 2) incorrectly rendered green instead of red.
                allData.forEach(row => {
                    const isParent = row.is_parent_summary === true ||
                        (row['Parent'] && String(row['Parent']).toUpperCase().startsWith('PARENT'));
                    if (isParent) return;
                    if (rowEbayStockQty(row) > 0) {
                        totalViews += parseFloat(row.views || 0);
                        totalL7Views += parseFloat(row.l7_views || 0);
                        l7ViewsCount++;
                    }
                });
                const avgL7Views = l7ViewsCount > 0 ? (totalL7Views / l7ViewsCount) : 0;
              
                const prevAvgL7Views = avgL7ViewsGlobal;
                avgL7ViewsGlobal = avgL7Views;
                
                const avgCVR = totalViews > 0 ? (ORDERS_L30_TOTAL_QTY / totalViews * 100) : 0;

                $('#total-sales-amt-badge').text('Sales: $' + Math.round(ORDERS_L30_TOTAL_SALES).toLocaleString());
                $('#avg-gpft-badge').text('GPFT: ' + Math.round(ORDERS_L30_GPFT) + '%');
                $('#groi-percent-badge').text('GROI: ' + Math.round(ORDERS_L30_GROI) + '%');
                // NPFT% = GPFT% − Ads%. NROI% = (GPFT$ − Ad Spend) / COGS × 100 (Amazon formula).
                $('#npft-percent-badge').text('NPFT: ' + Math.round(ORDERS_L30_GPFT - EBAY_CHANNEL_ADS_PCT) + '%');
                const nroiBadge = (ORDERS_L30_COGS > 0)
                    ? ((ORDERS_L30_PFT - EBAY_AD_SPEND) / ORDERS_L30_COGS) * 100
                    : ORDERS_L30_NROI;
                $('#nroi-percent-badge').text('NROI: ' + Math.round(nroiBadge) + '%');

                $('#avg-cvr-badge').text('CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#avg-l30-views-badge').text('A L30 View: ' + Math.round(totalViews / 30).toLocaleString());
                $('#avg-l7-views-badge').text('L7: ' + Math.round(avgL7Views).toLocaleString());
                // Always reformat L7 cells so below-avg values show RED (not stale green HTML).
                if (table) {
                    try {
                        table.getRows('active').forEach(function(row) {
                            const cell = row.getCell('l7_views');
                            if (cell && typeof cell.reformat === 'function') cell.reformat();
                        });
                    } catch (e) {
                        if (Math.abs(prevAvgL7Views - avgL7Views) > 0.0001) {
                            table.redraw(true);
                        }
                    }
                }

                // Count of rows currently shown after filters (exclude parent summary rows)
                const visibleRowCount = filteredData.filter(row =>
                    !(row['Parent'] && String(row['Parent']).toUpperCase().startsWith('PARENT'))
                ).length;
                $('#rows-count-badge').text('Rows: ' + visibleRowCount.toLocaleString());

                $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
                $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());

                let missingLCount = 0;
                let missingMCount = 0;
                allData.forEach(row => {
                    if (isEbayMissingL(row)) missingLCount++;
                    if (isEbayMissingM(row)) missingMCount++;
                });
                $('#missing-l-count-badge').text('M L: ' + missingLCount.toLocaleString());
                $('#missing-m-count-badge').text('M M: ' + missingMCount.toLocaleString());

                fitSummaryBadges();
            }

            /*
             * Keep all summary badges on ONE row (left → right). Shrink the shared badge
             * font-size only if the row would otherwise overflow, down to a readable minimum.
             */
            function fitSummaryBadges() {
                const row = document.querySelector('#summary-stats .ebay2-summary-badge-row');
                if (!row) return;
                const MAX_FS = 0.7;   // rem — preferred size
                const MIN_FS = 0.4;   // rem — smallest allowed
                let fs = MAX_FS;
                row.style.setProperty('--summary-badge-fs', fs + 'rem');
                // Reduce until the content fits within the available width (or hit the min).
                let guard = 0;
                while (row.scrollWidth > row.clientWidth && fs > MIN_FS && guard < 40) {
                    fs = Math.max(MIN_FS, fs - 0.02);
                    row.style.setProperty('--summary-badge-fs', fs + 'rem');
                    guard++;
                }
            }

            // Re-fit the single-row badges when the window is resized (debounced).
            let _fitBadgesTimer = null;
            $(window).on('resize', function() {
                clearTimeout(_fitBadgesTimer);
                _fitBadgesTimer = setTimeout(fitSummaryBadges, 150);
            });

            /*
             * Column visibility (every column for this page) persists in the shared DB table
             * `channel_tabulator_column_settings` under channel = 'ebay1_tabulator'. We hit the
             * same /tabulator-column-visibility endpoint used by the ebay2 / ebay3 / mfrg /
             * amazon tabulators so a single row owns the show/hide map for everyone on this view.
             *
             * Category placement (General / Pricing / Advertisement / Others) is classified by
             * defaults below and can be overridden via drag-and-drop (stored in localStorage).
             */
            const COL_VIS_CATEGORY_KEYS = ['general', 'pricing', 'advertisement', 'others'];
            const COL_VIS_CATEGORY_LABELS = {
                general: 'General',
                pricing: 'Pricing',
                advertisement: 'Advertisement',
                others: 'Others'
            };
            const COL_VIS_CATEGORY_STORAGE_KEY = 'ebay1_tabulator_column_categories_v1';

            function colVisItemKey(field, title) {
                return String(field || '') + '||' + String(title || field || '');
            }

            /** Default AI-style classification from field / title. */
            function classifyColumnDefault(field, title) {
                const f = String(field || '');
                const t = String(title || field || '');
                const fl = f.toLowerCase();
                const tl = t.toLowerCase();
                const blob = fl + ' ' + tl;

                // Advertisement first (views / bids / ads / promote)
                if (
                    /^(views|l7_views|l7_views_chg_pct|l7_views_prev|_ads_pct|ca_bid_percentage|ca_suggested_bid|ca_promote_with_ad)$/i.test(f) ||
                    /\b(ads\s*%|es\s*bid|c\s*bid|s\s*bid|promote|l30\s*view|l7\s*view)\b/i.test(t) ||
                    /\b(bid|promote|ads)\b/i.test(blob)
                ) {
                    return 'advertisement';
                }

                // Pricing
                if (
                    /^(eBay Price|GPFT%|PFT %|ROI%|NROI|lmp_price|target_price|linked_lmp_skus|linked_lmp_sku_add|SPRICE|_accept|SGPFT|SPFT|SGROI|SROI|E Dil%|SCVR|CVR_45|CVR_60)$/i.test(f) ||
                    /\b(prc|price|gpft|npft|groi|nroi|lmp|t\s*prc|target|s\s*prc|s\s*gpft|s\s*pft|s\s*groi|sroi|dil|cvr)\b/i.test(tl) ||
                    /^(_accept|\+)$/i.test(t)
                ) {
                    return 'pricing';
                }

                // General product / inventory / listing
                if (
                    /^(image_path|Parent|\(Child\) sku|INV|L30|rating|links_column|eBay Stock|Missing|MAP|nr_req|nrp|NRL|NR|eBay L30|eBay L45|eBay L60|growth_percent|_select|_parent_sort)$/i.test(f) ||
                    /\b(image|parent|sku|inv|ov\s*l30|links|rating|stock|missing|map|nr\/req|nrp|nrl|nra|growth|e\s*l\d+)\b/i.test(tl)
                ) {
                    return 'general';
                }

                return 'others';
            }

            function loadColumnCategoryOverrides() {
                try {
                    const raw = localStorage.getItem(COL_VIS_CATEGORY_STORAGE_KEY);
                    const parsed = raw ? JSON.parse(raw) : {};
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function saveColumnCategoryOverrides(map) {
                try {
                    localStorage.setItem(COL_VIS_CATEGORY_STORAGE_KEY, JSON.stringify(map || {}));
                } catch (e) {}
            }

            function resolveColumnCategory(field, title, overrides) {
                const key = colVisItemKey(field, title);
                const o = overrides && overrides[key];
                if (o && COL_VIS_CATEGORY_KEYS.indexOf(o) !== -1) return o;
                return classifyColumnDefault(field, title);
            }

            /** Sync category header checkbox: checked / indeterminate / unchecked from its items. */
            function syncColVisGroupHeaderCheckbox(groupEl) {
                if (!groupEl) return;
                const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
                if (!headerCb) return;
                if (!itemCbs.length) {
                    headerCb.checked = false;
                    headerCb.indeterminate = false;
                    headerCb.disabled = true;
                    const titleEl = headerCb.closest('.col-vis-group-title');
                    if (titleEl) titleEl.classList.add('col-vis-group-empty');
                    return;
                }
                headerCb.disabled = false;
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
                        const overrides = loadColumnCategoryOverrides();

                        const showAllLi = document.createElement("li");
                        showAllLi.className = "col-vis-full";
                        showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="show-all-columns-btn"><i class="fa fa-eye"></i> Show All</a>';
                        menu.appendChild(showAllLi);

                        const hintLi = document.createElement("li");
                        hintLi.className = "col-vis-full";
                        hintLi.innerHTML = '<div class="px-2 pb-1 text-muted" style="font-size:0.7rem;">Drag columns between General · Pricing · Advertisement · Others. Use header checkbox to select / deselect a group.</div>';
                        menu.appendChild(hintLi);

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

                            // Drop onto group / list
                            [group, list].forEach(function(zone) {
                                zone.addEventListener("dragover", function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    group.classList.add("col-vis-drop-over");
                                    e.dataTransfer.dropEffect = "move";
                                });
                                zone.addEventListener("dragleave", function(e) {
                                    if (!group.contains(e.relatedTarget)) {
                                        group.classList.remove("col-vis-drop-over");
                                    }
                                });
                                zone.addEventListener("drop", function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    group.classList.remove("col-vis-drop-over");
                                    const itemKey = e.dataTransfer.getData("text/col-vis-key");
                                    if (!itemKey) return;
                                    const next = loadColumnCategoryOverrides();
                                    next[itemKey] = cat;
                                    saveColumnCategoryOverrides(next);
                                    buildColumnDropdown();
                                });
                            });
                        });

                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;

                            const title = def.title || def.field;
                            const itemKey = colVisItemKey(def.field, title);
                            const cat = resolveColumnCategory(def.field, title, overrides);

                            const li = document.createElement("li");
                            li.className = "col-vis-item";
                            li.draggable = true;
                            li.dataset.itemKey = itemKey;
                            li.dataset.field = def.field;
                            li.dataset.group = cat;

                            li.addEventListener("dragstart", function(e) {
                                e.stopPropagation();
                                li.classList.add("col-vis-dragging");
                                e.dataTransfer.setData("text/col-vis-key", itemKey);
                                e.dataTransfer.effectAllowed = "move";
                            });
                            li.addEventListener("dragend", function() {
                                li.classList.remove("col-vis-dragging");
                                menu.querySelectorAll(".col-vis-drop-over").forEach(function(el) {
                                    el.classList.remove("col-vis-drop-over");
                                });
                            });

                            const label = document.createElement("label");
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.className = "col-vis-field-toggle";
                            checkbox.value = def.field;
                            checkbox.dataset.group = cat;
                            checkbox.checked = map.hasOwnProperty(def.field) ? (map[def.field] !== false) : col.isVisible();
                            checkbox.style.marginRight = "6px";

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(title));
                            label.title = title + " (drag to move category)";
                            li.appendChild(label);
                            lists[cat].appendChild(li);
                        });

                        COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                            syncColVisGroupHeaderCheckbox(groupEls[cat]);
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
                        enforceAlwaysHiddenColumns();
                    })
                    .catch(err => console.error('Error applying column visibility:', err));
            }

            // Wait for table to be built
            // eBay1 sales (from shopify_raw_orders, L30, excludes cancelled / other eBay stores)
            function loadEbay1ShopifySales() {
                fetch('{{ route('shopify-raw-data.ebay1-sales') }}', {
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    })
                    .then(r => r.json())
                    .then(d => {
                        const sales = parseFloat(d.sales || 0);
                        $('#ebay1-shopify-sales-badge')
                            .text('EShp: $' + Math.round(sales).toLocaleString())
                            .attr('title', 'eBay1 sales from Shopify raw data ' +
                                (d.date_from || '') + ' to ' + (d.date_to || '') +
                                ' (excludes cancelled). Orders: ' + (d.orders || 0) + ', Qty: ' + (d.qty || 0));
                    })
                    .catch(() => {});
            }

            // Re-apply filters after any sort so hidden parent (summary) rows never
            // reappear when sorting by a column (View = SKU keeps them hidden).
            let ebaySortReapplyGuard = false;
            table.on('dataSorted', function() {
                if (ebaySortReapplyGuard) return;
                ebaySortReapplyGuard = true;
                applyFilters();
                setTimeout(function() { ebaySortReapplyGuard = false; }, 0);
            });

            table.on('tableBuilt', function() {
                applySectionColumnVisibility('all');
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
                loadEbay1ShopifySales();

                // Set up periodic background retry check (every 30 seconds)
                setInterval(() => {
                    backgroundRetryFailedSkus();
                }, 30000);
            });

            table.on('dataLoaded', function() {
                // Build the unique parent list for Play/Next/Previous navigation.
                var allRows = table.getData('all') || [];
                var parents = [];
                var seenParent = {};
                allRows.forEach(function(r) {
                    var p = r.Parent || '';
                    if (p && String(p).trim() !== '' && !String(p).toUpperCase().startsWith(
                            'PARENT') && !seenParent[p]) {
                        seenParent[p] = true;
                        parents.push(p);
                    }
                });
                parents.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                // Use same parent list for Play/Next/Previous (single parent SKUs like product-master)
                productUniqueParents = parents.slice(0);
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Refresh checkboxes to reflect selectedSkus set (matching Amazon approach)
                setTimeout(function() {
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
                // Redraw so rowFormatter runs and parent rows get light blue background
                setTimeout(function() {
                    table.redraw(true);
                }, 50);
                // Play / Pause parent navigation (same as product-master)
                initProductPlaybackControls();
            });

            // Also initialize tooltips when table is rendered (matching Amazon approach)
            table.on('renderComplete', function() {
                setTimeout(function() {
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
            });

            // Toggle column from dropdown — save visibility, then close the menu
            (function() {
                var colMenu = document.getElementById("column-dropdown-menu");
                function closeColumnDropdown() {
                    var toggleBtn = document.getElementById('columnVisibilityDropdown');
                    if (!toggleBtn) return;
                    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                        var dd = bootstrap.Dropdown.getInstance(toggleBtn) ||
                            bootstrap.Dropdown.getOrCreateInstance(toggleBtn);
                        dd.hide();
                    } else if (window.jQuery) {
                        $(toggleBtn).dropdown('hide');
                    }
                }
                if (colMenu) {
                    colMenu.addEventListener("change", function(e) {
                        if (e.target.type !== 'checkbox') return;

                        // Category header: select / deselect all columns in that group
                        if (e.target.classList.contains('col-vis-group-toggle')) {
                            const checked = e.target.checked;
                            const groupEl = e.target.closest('.col-vis-group');
                            const itemCbs = groupEl
                                ? groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]')
                                : [];
                            itemCbs.forEach(function(cb) {
                                const field = cb.value;
                                cb.checked = checked;
                                const col = table.getColumn(field);
                                if (!col) return;
                                if (checked) col.show();
                                else col.hide();
                            });
                            e.target.indeterminate = false;
                            if (typeof enforceAlwaysHiddenColumns === 'function') {
                                enforceAlwaysHiddenColumns();
                            }
                            saveColumnVisibilityToServer();
                            return; // keep menu open for bulk edits
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
                        syncColVisGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
                        saveColumnVisibilityToServer();
                        closeColumnDropdown();
                    });
                    // "Show All" — show every column, save, then close
                    colMenu.addEventListener("click", function(e) {
                        var showAll = e.target.closest('#show-all-columns-btn');
                        if (showAll) {
                            e.preventDefault();
                            table.getColumns().forEach(col => col.show());
                            buildColumnDropdown();
                            saveColumnVisibilityToServer();
                            closeColumnDropdown();
                        }
                    });
                }
            })();

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

                // L30 View / L7 View arrow — daily snapshot chart (same style as A L30 View badge)
                if (e.target.closest('.view-sku-views-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const btn = e.target.closest('.view-sku-views-chart');
                    showSkuViewsChart(btn.getAttribute('data-sku'), btn.getAttribute('data-metric') || 'views');
                    return;
                }

                // View SKU chart (Price or CVR from column dot / SKU info icon) — all-marketplace-master style
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
            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                '(Child) sku': 'SKU',
                'INV': 'INV',
                'L30': 'L30',
                'E Dil%': 'Dil%',
                'eBay L30': 'eBay L30',
                'eBay L45': 'eBay L45',
                'eBay L60': 'eBay L60',
                'growth_percent': 'Growth',
                'eBay Stock': 'eBay Stock',
                'Missing': 'Missing',
                'MAP': 'MAP',
                'eBay Price': 'eBay Price',
                'lmp_price': 'LMP',
                'target_price': 'Target Price',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'GROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'l7_views': 'L7 Views',
                'l7_views_chg_pct': 'L7 %',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SPFT',
                'SGROI': 'SGROI',
                'SROI': 'SROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'CVR 30',
                'CVR_45': 'CVR 45',
                'CVR_60': 'CVR 60',
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
                    return field && exportColumnMapping[field] && field !== '_select' && field !==
                    '_accept';
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
                const exportUrl = `/ebay-export?columns=${columnsParam}`;

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
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success ||
                            'Ratings imported successfully');

                        // Reload table data
                        setTimeout(() => {
                            table.setData(EBAY_DATA_JSON_URL);
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
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

        function loadEbayCompetitorsModal(sku, linkedLmpSkus, options) {
            options = options || {};
            const refreshFromApi = !!options.refresh;
            $('#lmpSku').text(sku);

            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompLink').val('');

            currentLmpData.sku = sku;
            currentLmpData.linkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];

            openLmpModal();

            // Show loading state
            $('#lmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">${refreshFromApi ? 'Pulling live prices from LMP API...' : 'Loading competitors...'}</p>
                </div>
            `);

            // Fetch LMP data (merged across Sku Link LMP group — same as LMP column).
            // Pass refresh=1 to live-fetch each competitor via EbayLivePriceFetcher / SerpApi.
            const reqData = {
                sku: sku,
                linked_lmp_skus: currentLmpData.linkedLmpSkus
            };
            if (refreshFromApi) {
                reqData.refresh = 1;
            }

            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                traditional: true,
                data: reqData,
                // Live SerpApi refresh can take ~2s per competitor
                timeout: refreshFromApi ? 300000 : 60000,
                success: function(response) {
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.sku = sku;
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;

                        renderEbayCompetitorsList(response.competitors, response.lowest_price);

                        if (refreshFromApi) {
                            showToast('Pulled live LMP prices for ' + sku, 'success');
                            // Patch this row's LMP immediately, then refresh table from server
                            if (typeof table !== 'undefined' && table && table.getRows) {
                                const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                                if (row && response.lowest_price != null) {
                                    row.update({ lmp_price: response.lowest_price });
                                }
                            }
                            renderLmpModalStats(sku);
                            if (typeof table !== 'undefined' && table && table.replaceData) {
                                table.replaceData();
                            }
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
                    const apiMsg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || '';
                    // Distinct message: an AJAX failure is NOT the same as "really empty".
                    $('#lmpDataList').html(`
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i>
                            Could not load competitors. Please close this dialog and try again.
                            ${apiMsg ? `<div class="small mt-1">${$('<div>').text(apiMsg).html()}</div>` : ''}
                        </div>
                    `);
                    if (refreshFromApi) {
                        showToast(apiMsg || 'Failed to pull LMP API data', 'error');
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

        // Pull live competitor prices for the open SKU from LMP / eBay API
        $(document).on('click', '#lmpPullApiBtn', function() {
            const sku = currentLmpData.sku || $('#lmpSku').text().trim();
            if (!sku) {
                showToast('No SKU selected', 'error');
                return;
            }
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Pulling...');
            loadEbayCompetitorsModal(sku, currentLmpData.linkedLmpSkus || [], { refresh: true });
        });

        /** Our eBay price / listing for the blue 5 Core reference row in LMP table */
        function getLmpOurListing(sku) {
            let rowData = null;
            if (typeof table !== 'undefined' && table && table.getRows) {
                const r = table.getRows().find(row => row.getData()['(Child) sku'] === sku);
                if (r) rowData = r.getData();
            }
            if (!rowData) {
                return { price: 0, itemId: '', image: '', title: '' };
            }
            const itemId = rowData.eBay_item_id ? String(rowData.eBay_item_id) : '';
            return {
                price: parseFloat(rowData['eBay Price']) || 0,
                itemId: itemId,
                image: rowData.image_path || rowData.Image || rowData.image || '',
                title: '5 Core — ' + (sku || rowData['(Child) sku'] || ''),
                link: itemId ? ('https://www.ebay.com/itm/' + itemId) : ''
            };
        }

        function buildLmpFiveCoreRowHtml(our) {
            const price = parseFloat(our.price) || 0;
            if (price <= 0) return '';
            const imageCell = our.image
                ? `<img src="${our.image}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                : '<span class="text-muted"><i class="fas fa-store"></i></span>';
            const linkBtn = our.link
                ? `<a href="${our.link}" target="_blank" class="btn btn-sm btn-primary" title="Open our eBay listing"><i class="fa fa-external-link"></i></a>`
                : '<span class="text-muted small">—</span>';
            return `
                <tr class="lmp-five-core-row" title="Our 5 Core listing — sorted by price to show market level">
                    <td>${imageCell}</td>
                    <td><span class="lmp-five-core-price">$${price.toFixed(2)}</span></td>
                    <td><span class="badge bg-primary">Ours</span></td>
                    <td>
                        <strong class="lmp-five-core-price">$${price.toFixed(2)}</strong>
                        <span class="badge bg-primary ms-1">5 CORE</span>
                    </td>
                    <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${our.title || '5 Core (our listing)'}
                    </td>
                    <td class="text-center text-muted small">—</td>
                    <td>${linkBtn}</td>
                </tr>
            `;
        }

        // Render Competitors List Function (Ignore = same as Temu: excluded from L1)
        // Always inserts a blue 5 Core row at our price position (same idea as Temu LMP).
        function renderEbayCompetitorsList(competitors, lowestPrice) {
            competitors = Array.isArray(competitors) ? competitors : [];
            const our = getLmpOurListing(currentLmpData.sku);

            if (competitors.length === 0 && !(our.price > 0)) {
                $('#lmpDataList').html(`
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No competitors found for this SKU
                    </div>
                `);
                return;
            }

            // L1 among non-ignored only (matches backend) — 5 Core row is never L1
            let l1Price = null;
            competitors.forEach(function(item) {
                if (item.ignored) return;
                const tp = parseFloat(item.total_price) || 0;
                if (tp > 0 && (l1Price === null || tp < l1Price)) l1Price = tp;
            });
            if (l1Price === null && lowestPrice != null) {
                l1Price = parseFloat(lowestPrice);
            }

            const skuEsc = String(currentLmpData.sku || '').replace(/"/g, '&quot;');
            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `
                <thead class="table-dark">
                    <tr>
                        <th>Image</th>
                        <th>Price</th>
                        <th>Shipping</th>
                        <th>Total</th>
                        <th>Title</th>
                        <th class="text-center" title="Ignore for L1 (same as Temu)">Ignore</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;

            // Build competitor rows with sort key, then insert 5 Core at our price level
            const rows = competitors.map(function(item) {
                const ignored = !!item.ignored;
                const total = parseFloat(item.total_price) || 0;
                const isLowest = !ignored && l1Price !== null && Math.abs(total - l1Price) < 0.005;
                const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '');
                const badge = isLowest ? '<span class="badge bg-success ms-2">L1</span>' : (ignored ? '<span class="badge bg-secondary ms-2">Ignored</span>' : '');
                const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
                const imageCell = item.image
                    ? `<img src="${item.image}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                    : '<span class="text-muted">—</span>';
                const ignoreCb = `<input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"`
                    + (ignored ? ' checked' : '')
                    + ` data-id="${item.id}" data-marketplace="ebay" data-sku="${skuEsc}">`;

                const rowHtml = `
                    <tr class="${rowClass}">
                        <td>${imageCell}</td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                        <td><strong>$${total.toFixed(2)}</strong> ${badge}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${item.title || 'N/A'}
                        </td>
                        <td class="text-center align-middle">${ignoreCb}</td>
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
                return { total: total, html: rowHtml };
            });

            // Competitors already sorted by total from API; keep that order
            const fiveCoreHtml = buildLmpFiveCoreRowHtml(our);
            let fiveCoreInserted = false;
            rows.forEach(function(row) {
                if (!fiveCoreInserted && fiveCoreHtml && our.price > 0 && row.total >= our.price) {
                    html += fiveCoreHtml;
                    fiveCoreInserted = true;
                }
                html += row.html;
            });
            if (!fiveCoreInserted && fiveCoreHtml) {
                html += fiveCoreHtml;
            }

            html += '</tbody></table></div>';
            if (l1Price !== null) {
                html = `<div class="mb-2 small text-muted">L1 (lowest non-ignored): <strong>$${Number(l1Price).toFixed(2)}</strong></div>` + html;
            }
            $('#lmpDataList').html(html);
        }

        // Toggle LMP ignore (same endpoint / behavior as Temu & CVR master)
        $(document).on('change', '#lmpModal .lmp-ignore-cb', function() {
            const $cb = $(this);
            const id = $cb.attr('data-id') || $cb.data('id');
            const marketplace = ($cb.attr('data-marketplace') || $cb.data('marketplace') || 'ebay').toLowerCase();
            const sku = $cb.attr('data-sku') || $cb.data('sku') || currentLmpData.sku || '';
            const ignored = $cb.is(':checked');
            $cb.prop('disabled', true);

            $.ajax({
                url: '/cvr-master-lmp-ignore',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id, marketplace: marketplace, sku: sku, ignored: ignored ? 1 : 0 },
                success: function(res) {
                    $cb.prop('disabled', false);
                    if (res && res.success) {
                        (currentLmpData.competitors || []).forEach(function(c) {
                            if (String(c.id) === String(id)) c.ignored = ignored;
                        });
                        let l1 = null;
                        (currentLmpData.competitors || []).forEach(function(c) {
                            if (c.ignored) return;
                            const tp = parseFloat(c.total_price) || 0;
                            if (tp > 0 && (l1 === null || tp < l1)) l1 = tp;
                        });
                        currentLmpData.lowestPrice = l1;
                        renderEbayCompetitorsList(currentLmpData.competitors, l1);
                        showToast(res.message || (ignored ? 'Ignored for L1' : 'Included in L1'), 'success');
                        if (table && typeof table.replaceData === 'function') {
                            table.replaceData();
                        }
                    } else {
                        $cb.prop('checked', !ignored);
                        showToast((res && res.error) || 'Failed to update ignore', 'error');
                    }
                },
                error: function(xhr) {
                    $cb.prop('disabled', false);
                    $cb.prop('checked', !ignored);
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Failed to update ignore';
                    showToast(msg, 'error');
                }
            });
        });

        // View Competitors Modal Event Listener
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            let linkedSkus = $(this).data('linked-skus') || [];
            if (typeof linkedSkus === 'string') {
                try { linkedSkus = JSON.parse(linkedSkus) || []; } catch (err) { linkedSkus = []; }
            }
            if (!Array.isArray(linkedSkus) || !linkedSkus.length) {
                // Fallback: pull linked group from the tabulator row if attribute missing
                if (table && table.getRows) {
                    const r = table.getRows().find(row => row.getData()['(Child) sku'] === sku);
                    const fromRow = r ? r.getData().linked_lmp_skus : null;
                    if (Array.isArray(fromRow)) linkedSkus = fromRow;
                }
            }
            renderLmpModalStats(sku);
            loadEbayCompetitorsModal(sku, linkedSkus);
        });

        // Show the SKU's CVR / Price / Views / Sold in the LMP competitors modal.
        function renderLmpModalStats(sku) {
            const el = document.getElementById('lmpStats');
            if (!el) return;
            let rowData = null;
            if (table && table.getRows) {
                const r = table.getRows().find(row => row.getData()['(Child) sku'] === sku);
                if (r) rowData = r.getData();
            }
            if (!rowData) { el.innerHTML = ''; return; }
            const cvr = parseFloat(rowData.SCVR) || 0;
            const price = parseFloat(rowData['eBay Price']) || 0;
            const views = parseFloat(rowData.views) || 0;
            const sold = parseFloat(rowData['eBay L30']) || 0;
            const lmp = parseFloat(rowData.lmp_price) || 0;
            const badge = (label, value, bg) =>
                `<span class="badge fs-6 p-2" style="background:${bg};color:#fff;font-weight:600;">${label}: ${value}</span>`;
            let html =
                badge('CVR', cvr.toFixed(1) + '%', '#dc3545') +
                badge('Price', '$' + price.toFixed(2), '#ffc107').replace('color:#fff', 'color:#000') +
                badge('Views', Math.round(views).toLocaleString(), '#17a2b8') +
                badge('Sold', Math.round(sold).toLocaleString(), '#6f42c1');

            // % difference of our price vs the lowest competitor price (LMP).
            // Negative = we're cheaper (green), positive = we're higher (red).
            if (price > 0 && lmp > 0) {
                const diffPct = ((price - lmp) / lmp) * 100;
                const sign = diffPct > 0 ? '+' : '';
                const bg = diffPct > 0 ? '#a00211' : '#28a745';
                const tip = diffPct > 0 ? 'higher than' : 'lower than';
                html += `<span class="badge fs-6 p-2" style="background:${bg};color:#fff;font-weight:600;"
                    title="Our price is ${Math.abs(diffPct).toFixed(1)}% ${tip} the lowest competitor (LMP $${lmp.toFixed(2)})">vs LMP: ${sign}${diffPct.toFixed(1)}%</span>`;
            }
            el.innerHTML = html;
        }

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
                    shipping_cost: 0,
                    product_link: $('#addCompLink').val()
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor added successfully', 'success');

                        // Clear form
                        $('#addCompItemId').val('');
                        $('#addCompPrice').val('');
                        $('#addCompLink').val('');

                        // Reload competitors list
                        const sku = $('#addCompSku').val();
                        loadEbayCompetitorsModal(sku, currentLmpData.linkedLmpSkus);

                        // Reload main table data
                        table.replaceData();
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
                data: {
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');

                        // Reload competitors list
                        const sku = currentLmpData.sku;
                        loadEbayCompetitorsModal(sku, currentLmpData.linkedLmpSkus);

                        // Reload main table data
                        table.replaceData();
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


        // Tooltip functions for eBay links
        function showEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        }

        function hideEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        }

        // Export LMP — flatten all competitor entries for every SKU into one CSV
        $('#export-lmp-btn').on('click', function() {
            if (!table) {
                alert('Table not loaded');
                return;
            }

            const allRows = table.getData();
            const lmpRows = [];

            allRows.forEach(function(row) {
                if (row.is_parent_summary) return;
                const sku = row['(Child) sku'] || '';
                const currentPrice = row['eBay Price'] || '';
                const entries = row.lmp_entries || [];

                if (entries.length === 0) {
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
            a.download = 'eBay_LMP_Export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showToast('success', 'Exported LMP data for ' + lmpRows.length + ' competitor rows');
        });

        // ════════════════════════════════════════════════════════════════════
        // SBID Rule modal (shared with /ebay/campaign-ads; same endpoints)
        // Edits SCVR band lookup + dynamic sub-rules for the S BID column.
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const ruleGetUrl  = '/ebay/campaign-ads/rule';
            const ruleSaveUrl = '/ebay/campaign-ads/rule';

            // Default dynamic CVR bands (editable Min/Max). 0% band uses each row's ES Bid.
            function defaultSbidBands() {
                return [
                    { scvr_min: 0,     scvr_max: 0,    use_es_bid: true, bid: 0 },
                    { scvr_min: 0.01,  scvr_max: 3,    bid: 10.1 },
                    { scvr_min: 3.01,  scvr_max: 7,    bid: 8.1 },
                    { scvr_min: 7.01,  scvr_max: 13,   bid: 5.1 },
                    { scvr_min: 13.01, scvr_max: 9999, bid: 5.1 },
                ];
            }

            // Ensure every band has an explicit scvr_min / scvr_max. Legacy bands stored
            // with only scvr_max get a min derived from the previous band's max (+0.01).
            function normalizeSbidBands(bands) {
                let arr = Array.isArray(bands) ? bands.slice() : [];
                if (!arr.length) return defaultSbidBands();
                let prevMax = null;
                arr.forEach(function(b, i) {
                    if (b.scvr_max == null || b.scvr_max === '') b.scvr_max = 9999;
                    if (b.scvr_min == null || b.scvr_min === '') {
                        b.scvr_min = (i === 0 || prevMax == null)
                            ? 0
                            : +(parseFloat(prevMax) + 0.01).toFixed(2);
                    }
                    // A 0–0 band always means "use the row's ES Bid".
                    if (parseFloat(b.scvr_min) === 0 && parseFloat(b.scvr_max) === 0) b.use_es_bid = true;
                    prevMax = parseFloat(b.scvr_max);
                });
                return arr;
            }

            // SBID Rule editor removed from this page. The S Bid column still reads the
            // rule saved on /ebay/campaign-ads: fetch it once and normalize the bands so
            // getCombinedSbid/getSbidFromRule can resolve each row's bid.
            function loadRule() {
                $.ajax({
                    url: ruleGetUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentSbidRule = data || { bands: [] };
                        if (!Array.isArray(currentSbidRule.bands)) currentSbidRule.bands = [];
                        currentSbidRule.bands = normalizeSbidBands(currentSbidRule.bands);
                        if (table) table.redraw(true);
                    },
                    error: function(xhr) {
                        // eslint-disable-next-line no-console
                        console.error('[SBID Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            if (document.readyState !== 'loading') {
                loadRule();
            } else {
                document.addEventListener('DOMContentLoaded', loadRule);
            }
        })();

        // ════════════════════════════════════════════════════════════════════
        // Dil Rule modal (shared with /ebay/campaign-ads; same endpoints)
        // Edits DIL% color bands. Storage: ebay_sbid_rules.key = 'ebay1_dil'.
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const dilGetUrl  = '/ebay/campaign-ads/dil-rule';
            const dilSaveUrl = '/ebay/campaign-ads/dil-rule';
            let currentDilRule = { bands: [] };

            function renderDilBands(bands) {
                const tbody = document.getElementById('dil-bands-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                (bands || []).forEach(function(band, i) {
                    const isLast = (parseFloat(band.dil_max) >= 9999);
                    tbody.innerHTML += `
                    <tr>
                        <td class="text-center text-muted small">${i + 1}</td>
                        <td><input type="text" class="form-control form-control-sm" value="${band.label || ''}"
                                   data-idx="${i}" data-field="label" onchange="window.dilRuleUpdateBand(this)"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color form-control-sm" style="width:40px;height:31px;"
                                       value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color"
                                       onchange="window.dilRuleUpdateBand(this)">
                                <span class="badge" style="background:${band.color || '#6c757d'};">${band.label || ''}</span>
                            </div>
                        </td>
                        <td>
                            ${isLast
                                ? '<span class="text-muted small">∞ (catch-all)</span><input type="hidden" value="9999" data-idx="' + i + '" data-field="dil_max">'
                                : `<input type="number" step="0.01" min="0" class="form-control form-control-sm" value="${band.dil_max}"
                                          data-idx="${i}" data-field="dil_max" onchange="window.dilRuleUpdateBand(this)">`
                            }
                        </td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm fw-semibold"
                                       value="${band.bid != null ? band.bid : ''}" data-idx="${i}" data-field="bid"
                                       style="color:${band.color || '#333'}; font-weight:600;"
                                       onchange="window.dilRuleUpdateBand(this)">
                                <span class="input-group-text">%</span>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                        onclick="window.dilRuleRemoveBand(${i})" title="Remove band">&times;</button>
                            </div>
                        </td>
                    </tr>`;
                });
            }

            // Inline-handler globals (prefixed to avoid clashing with anything else on this page).
            window.dilRuleUpdateBand = function(el) {
                const idx   = parseInt(el.dataset.idx);
                const field = el.dataset.field;
                if (!currentDilRule.bands[idx]) return;
                currentDilRule.bands[idx][field] = (field === 'dil_max' || field === 'bid')
                    ? parseFloat(el.value) : el.value;
                if (field === 'color') {
                    const badge = el.closest('tr').querySelector('.badge');
                    if (badge) badge.style.background = el.value;
                }
            };

            window.dilRuleRemoveBand = function(idx) {
                currentDilRule.bands.splice(idx, 1);
                renderDilBands(currentDilRule.bands);
            };

            // Add-band button
            $(document).on('click', '#dil-add-band-btn', function() {
                const bands = currentDilRule.bands;
                const lastIsCatch = bands.length && parseFloat(bands[bands.length - 1].dil_max) >= 9999;
                const newBand = { dil_max: 0, bid: 2.1, label: 'New', color: '#6c757d' };
                if (lastIsCatch) bands.splice(bands.length - 1, 0, newBand);
                else bands.push(newBand);
                renderDilBands(bands);
            });

            function loadDilRule() {
                $.ajax({
                    url: dilGetUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentDilRule = data || { bands: [] };
                        if (!Array.isArray(currentDilRule.bands)) currentDilRule.bands = [];
                        renderDilBands(currentDilRule.bands);
                    },
                    error: function(xhr) {
                        const errEl = document.getElementById('dil-rule-err');
                        if (errEl) {
                            errEl.textContent = 'Could not load Dil Rule (HTTP ' + xhr.status + '). Check console / network tab.';
                            errEl.classList.remove('d-none');
                        }
                        // eslint-disable-next-line no-console
                        console.error('[Dil Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            // Prime on init
            if (document.getElementById('dilRuleModal')) {
                loadDilRule();
            } else {
                document.addEventListener('DOMContentLoaded', loadDilRule);
            }

            // Reload on each modal open
            const dilModalEl = document.getElementById('dilRuleModal');
            if (dilModalEl) {
                dilModalEl.addEventListener('show.bs.modal', loadDilRule);
            }

            // Save
            $('#dil-rule-save-btn').on('click', function() {
                const errEl = document.getElementById('dil-rule-err');
                errEl.classList.add('d-none');
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

                $.ajax({
                    url: dilSaveUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ bands: currentDilRule.bands || [] }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                        currentDilRule = resp.rule || currentDilRule;
                        if (typeof showToast === 'function') showToast('success', 'Dil Rule saved');
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                            const modal = bootstrap.Modal.getInstance(document.getElementById('dilRuleModal'));
                            if (modal) modal.hide();
                        }, 1000);
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                        errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                        errEl.classList.remove('d-none');
                    }
                });
            });
        })();

        // ════════════════════════════════════════════════════════════════════
        // Sprice Rule modal — build multiple rules on Dil / El30 / CVR / Groi /
        // LMP that auto-populate the SPRICE column.
        // Storage: ebay_sbid_rules.key = 'ebay1_sprice' (via /ebay-one/sprice-rule).
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const spriceGetUrl  = @json(url('/ebay-one/sprice-rule'));
            const spriceSaveUrl = @json(url('/ebay-one/sprice-rule'));
            const spriceApplyUrl = @json(url('/ebay-one/save-sprice'));
            let currentSpriceRules = [];

            function currentLmpMultForRules() {
                if (typeof getLmpMult === 'function') return getLmpMult();
                try {
                    const stored = parseFloat(localStorage.getItem('ebay1_lmp_mult'));
                    if (isFinite(stored) && stored > 0 && stored <= 2) return stored;
                } catch (e) { /* ignore */ }
                return 0.98;
            }
            function formatLmpMultForRules(v) {
                const n = Number(v);
                return isFinite(n) ? String(+n.toFixed(4)) : '0.98';
            }
            function getSpriceMethods() {
                const m = formatLmpMultForRules(currentLmpMultForRules());
                return [
                    { v: 'groi',       label: 'GROI% target' },
                    { v: 'groi_lmp98', label: 'GROI% (cap LMP×' + m + ')' },
                ];
            }

            window.updateSpriceLmpMultLabels = function(mult) {
                const m = formatLmpMultForRules(mult != null ? mult : currentLmpMultForRules());
                const label = 'GROI% (cap LMP×' + m + ')';
                $('select[data-field="method"] option[value="groi_lmp98"]').text(label);
            };

            function numAttr(v) {
                return (v === null || v === undefined || v === '' || isNaN(v)) ? '' : v;
            }

            function methodOptions(selected) {
                return getSpriceMethods().map(function(m) {
                    return `<option value="${m.v}" ${m.v === selected ? 'selected' : ''}>${m.label}</option>`;
                }).join('');
            }

            function rangeInputs(rule, key) {
                return `
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                               value="${numAttr(rule[key + '_min'])}" data-field="${key}_min"
                               onchange="window.spriceRuleUpdate(this)" placeholder="—"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                               value="${numAttr(rule[key + '_max'])}" data-field="${key}_max"
                               onchange="window.spriceRuleUpdate(this)" placeholder="—"></td>`;
            }

            function renderSpriceRules(rules) {
                const tbody = document.getElementById('sprice-rules-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!rules.length) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted small py-3">
                        No rules yet — click <strong>Add rule</strong> to create one.</td></tr>`;
                    return;
                }
                rules.forEach(function(rule, i) {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-idx', i);
                    tr.innerHTML = `
                        <td class="text-center text-muted small">${i + 1}</td>
                        <td><input type="text" class="form-control form-control-sm" value="${(rule.label || '').replace(/"/g, '&quot;')}"
                                   data-field="label" onchange="window.spriceRuleUpdate(this)" placeholder="Rule ${i + 1}"></td>
                        ${rangeInputs(rule, 'cvr')}
                        ${rangeInputs(rule, 'dil')}
                        <td><select class="form-select form-select-sm" data-field="method"
                                    onchange="window.spriceRuleUpdate(this)">${methodOptions(rule.method || 'groi')}</select></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                                   value="${numAttr(rule.value)}" data-field="value"
                                   onchange="window.spriceRuleUpdate(this)"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                    onclick="window.spriceRuleRemove(${i})" title="Remove rule">&times;</button>
                        </td>`;
                    tbody.appendChild(tr);
                });
            }

            // Each <input>/<select> carries data-field; the row carries data-idx.
            window.spriceRuleUpdate = function(el) {
                const tr = el.closest('tr');
                const idx = parseInt(tr.getAttribute('data-idx'), 10);
                const field = el.dataset.field;
                if (!currentSpriceRules[idx]) return;
                if (field === 'label' || field === 'method') {
                    currentSpriceRules[idx][field] = el.value;
                } else {
                    currentSpriceRules[idx][field] = (el.value === '' ? null : parseFloat(el.value));
                }
            };

            window.spriceRuleRemove = function(idx) {
                currentSpriceRules.splice(idx, 1);
                renderSpriceRules(currentSpriceRules);
            };

            $(document).on('click', '#sprice-add-rule-btn', function() {
                // Ladder the new slab's CVR Min from the previous slab's CVR Max (+0.01).
                const prev = currentSpriceRules[currentSpriceRules.length - 1];
                const prevMax = prev ? parseFloat(prev.cvr_max) : NaN;
                const nextMin = isFinite(prevMax) ? +(prevMax + 0.01).toFixed(2) : null;
                currentSpriceRules.push({
                    label: '', dil_min: null, dil_max: null, el30_min: null, el30_max: null,
                    cvr_min: nextMin, cvr_max: null, groi_min: null, groi_max: null,
                    lmp_min: null, lmp_max: null, method: 'groi', value: 30
                });
                renderSpriceRules(currentSpriceRules);
            });

            function loadSpriceRules() {
                $.ajax({
                    url: spriceGetUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentSpriceRules = (data && Array.isArray(data.rules)) ? data.rules : [];
                        renderSpriceRules(currentSpriceRules);
                    },
                    error: function(xhr) {
                        const errEl = document.getElementById('sprice-rule-err');
                        if (errEl) {
                            errEl.textContent = 'Could not load Sprice Rule (HTTP ' + xhr.status + ').';
                            errEl.classList.remove('d-none');
                        }
                        console.error('[Sprice Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            const spriceModalEl = document.getElementById('spriceRuleModal');
            if (spriceModalEl) {
                spriceModalEl.addEventListener('show.bs.modal', loadSpriceRules);
            }

            // Save rules to DB (does not touch any row's SPRICE).
            $('#sprice-rule-save-btn').on('click', function() {
                const errEl = document.getElementById('sprice-rule-err');
                errEl.classList.add('d-none');
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

                $.ajax({
                    url: spriceSaveUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ rules: currentSpriceRules || [] }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                        if (resp.rule && Array.isArray(resp.rule.rules)) currentSpriceRules = resp.rule.rules;
                        if (typeof showToast === 'function') showToast('success', 'Sprice Rule saved');
                        setTimeout(() => { btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule'; }, 1200);
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                        errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                        errEl.classList.remove('d-none');
                    }
                });
            });

            // ── Rule evaluation helpers ──────────────────────────────────────
            function inRange(val, min, max) {
                if (min !== null && min !== undefined && min !== '' && val < parseFloat(min)) return false;
                if (max !== null && max !== undefined && max !== '' && val > parseFloat(max)) return false;
                return true;
            }

            function factorsOf(rd) {
                const inv = parseFloat(rd.INV) || 0;
                const ovl30 = parseFloat(rd['L30']) || 0;
                return {
                    dil: inv > 0 ? (ovl30 / inv) * 100 : 0,
                    el30: parseFloat(rd['eBay L30']) || 0,
                    cvr: parseFloat(rd.SCVR) || 0,
                    groi: parseFloat(rd['ROI%']) || 0,
                    lmp: parseFloat(rd.lmp_price) || 0
                };
            }

            function ruleMatches(rule, f) {
                // Only CVR and Dil are used as conditions.
                return inRange(f.cvr, rule.cvr_min, rule.cvr_max)
                    && inRange(f.dil, rule.dil_min, rule.dil_max);
            }

            // Returns { sprice } or { skip:'reason' }.
            function spriceFromRule(rule, rd) {
                const v = parseFloat(rule.value);
                const lp = parseFloat(rd.LP_productmaster) || 0;
                const ship = parseFloat(rd.Ship_productmaster) || 0;
                const marginRaw = parseFloat(rd.percentage);
                const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;

                switch (rule.method) {
                    case 'fixed':
                        return isFinite(v) && v > 0 ? { sprice: v } : { skip: 'invalid fixed value' };
                    case 'lmp': {
                        const lmp = parseFloat(rd.lmp_price) || 0;
                        if (lmp <= 0) return { skip: 'no LMP' };
                        return { sprice: lmp * (1 + (isFinite(v) ? v : 0) / 100) };
                    }
                    case 'gpft': {
                        if (lp <= 0) return { skip: 'no LP' };
                        const denom = margin - (v / 100);
                        if (denom <= 0) return { skip: 'GPFT% ≥ margin' };
                        return { sprice: (lp + ship) / denom };
                    }
                    case 'groi_lmp98': {
                        // GROI% target, but if the computed SPRICE is above LMP, cap at LMP × dynamic factor.
                        if (lp <= 0) return { skip: 'no LP' };
                        const groiSprice = (lp * (1 + v / 100) + ship) / margin;
                        const lmp = parseFloat(rd.lmp_price) || 0;
                        const lmpCap = currentLmpMultForRules();
                        if (lmp > 0 && groiSprice > lmp) {
                            return { sprice: lmp * lmpCap };
                        }
                        return { sprice: groiSprice };
                    }
                    case 'groi':
                    default: {
                        if (lp <= 0) return { skip: 'no LP' };
                        return { sprice: (lp * (1 + v / 100) + ship) / margin };
                    }
                }
            }

            // Apply rules to every row currently visible in the table (post-filter).
            $('#sprice-rule-apply-btn').on('click', function() {
                const btn = this;
                const statusEl = document.getElementById('sprice-rule-status');
                const errEl = document.getElementById('sprice-rule-err');
                errEl.classList.add('d-none');

                if (!currentSpriceRules.length) {
                    errEl.textContent = 'Add at least one rule before applying.';
                    errEl.classList.remove('d-none');
                    return;
                }
                if (typeof table === 'undefined' || !table) {
                    errEl.textContent = 'Table not ready yet.';
                    errEl.classList.remove('d-none');
                    return;
                }

                // "active" rows = rows that pass all current filters (i.e. what's visible).
                const rows = table.getRows('active');
                const toProcess = [];
                let noMatch = 0, skipped = 0;

                rows.forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!sku) return;
                    if (rd.is_parent_summary || rd.is_parent_row) return;
                    if (rd.Parent && String(rd.Parent).toUpperCase().startsWith('PARENT')) return;

                    const f = factorsOf(rd);
                    const matched = currentSpriceRules.find(function(rule) { return ruleMatches(rule, f); });
                    if (!matched) { noMatch++; return; }

                    const res = spriceFromRule(matched, rd);
                    if (res.skip) { skipped++; return; }
                    const sprice = +Number(res.sprice).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) { skipped++; return; }
                    toProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (!toProcess.length) {
                    errEl.textContent = `No rows matched a rule with a valid price (matched-but-skipped: ${skipped}, unmatched: ${noMatch}).`;
                    errEl.classList.remove('d-none');
                    return;
                }

                if (!confirm(`Auto-populate SPRICE for ${toProcess.length} visible row(s)?\n\nUnmatched rows left unchanged: ${noMatch}. Matched-but-skipped: ${skipped}.`)) {
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Applying…';
                let done = 0, ok = 0, fail = 0;
                const total = toProcess.length;

                toProcess.forEach(function(item) {
                    $.ajax({
                        url: spriceApplyUrl,
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { sku: item.sku, sprice: item.sprice },
                        success: function(response) {
                            ok++;
                            item.row.update({
                                SPRICE: item.sprice,
                                SPFT: response.spft_percent != null ? response.spft_percent : 0,
                                SROI: response.sroi_percent != null ? response.sroi_percent : 0,
                                SGROI: response.sgroi_percent != null ? response.sgroi_percent : 0,
                                SGPFT: response.sgpft_percent != null ? response.sgpft_percent : 0,
                                SPRICE_STATUS: 'saved',
                                has_custom_sprice: true
                            });
                            item.row.reformat();
                        },
                        error: function() { fail++; },
                        complete: function() {
                            done++;
                            if (statusEl) statusEl.textContent = `Applying… ${done}/${total}`;
                            if (done === total) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Apply to Visible Rows';
                                if (statusEl) statusEl.textContent = `Done: ${ok} saved, ${fail} failed, ${noMatch} unmatched.`;
                                if (typeof showToast === 'function') {
                                    if (fail === 0) showToast('success', `SPRICE set on ${ok} row(s) from rules`);
                                    else showToast('error', `Saved ${ok}/${total} (${fail} failed)`);
                                }
                            }
                        }
                    });
                });
            });

            // Prime on init so the first modal open is instant.
            if (document.getElementById('spriceRuleModal')) {
                loadSpriceRules();
            } else {
                document.addEventListener('DOMContentLoaded', loadSpriceRules);
            }
        })();

        // ════════════════════════════════════════════════════════════════════
        // Sbid Rule modal — build multiple rules on CVR / Dil / Esold / Views L30
        // that decide the S Bid column. Storage: ebay_sbid_rules.key = 'ebay1_sbid_slabs'.
        // ════════════════════════════════════════════════════════════════════
        (function() {
            const getUrl  = @json(url('/ebay-one/sbid-slab-rule'));
            const saveUrl = @json(url('/ebay-one/sbid-slab-rule'));

            function numAttr(v) {
                return (v === null || v === undefined || v === '' || isNaN(v)) ? '' : v;
            }

            function rangeInputs(rule, key) {
                return `
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                               value="${numAttr(rule[key + '_min'])}" data-field="${key}_min"
                               onchange="window.sbidSlabUpdate(this)" placeholder="—"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm text-end"
                               value="${numAttr(rule[key + '_max'])}" data-field="${key}_max"
                               onchange="window.sbidSlabUpdate(this)" placeholder="—"></td>`;
            }

            function renderSbidSlabRules(rules) {
                const tbody = document.getElementById('sbid-slab-rules-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!rules.length) {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted small py-3">
                        No rules yet — click <strong>Add rule / slab</strong> to create one.</td></tr>`;
                    return;
                }
                rules.forEach(function(rule, i) {
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-idx', i);
                    tr.innerHTML = `
                        <td class="text-center text-muted small">${i + 1}</td>
                        <td><input type="text" class="form-control form-control-sm" value="${(rule.label || '').replace(/"/g, '&quot;')}"
                                   data-field="label" onchange="window.sbidSlabUpdate(this)" placeholder="Rule ${i + 1}"></td>
                        ${rangeInputs(rule, 'l7_views')}
                        ${rangeInputs(rule, 'cvr')}
                        <td><input type="number" step="0.1" min="0" class="form-control form-control-sm text-end fw-semibold"
                                   value="${numAttr(rule.sbid)}" data-field="sbid"
                                   onchange="window.sbidSlabUpdate(this)"></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                    onclick="window.sbidSlabRemove(${i})" title="Remove rule">&times;</button>
                        </td>`;
                    tbody.appendChild(tr);
                });
            }

            window.sbidSlabUpdate = function(el) {
                const tr = el.closest('tr');
                const idx = parseInt(tr.getAttribute('data-idx'), 10);
                const field = el.dataset.field;
                if (!currentSbidSlabRules[idx]) return;
                if (field === 'label') {
                    currentSbidSlabRules[idx][field] = el.value;
                } else {
                    currentSbidSlabRules[idx][field] = (el.value === '' ? null : parseFloat(el.value));
                }
            };

            window.sbidSlabRemove = function(idx) {
                currentSbidSlabRules.splice(idx, 1);
                renderSbidSlabRules(currentSbidSlabRules);
            };

            $(document).on('click', '#sbid-slab-add-rule-btn', function() {
                currentSbidSlabRules.push({
                    label: '', cvr_min: null, cvr_max: null,
                    l7_views_min: null, l7_views_max: null, sbid: 2.1
                });
                renderSbidSlabRules(currentSbidSlabRules);
            });

            function loadSbidSlabRules() {
                $.ajax({
                    url: getUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        currentSbidSlabRules = (data && Array.isArray(data.rules)) ? data.rules : [];
                        renderSbidSlabRules(currentSbidSlabRules);
                        if (table) table.redraw(true);
                    },
                    error: function(xhr) {
                        console.error('[Sbid Rule] load failed', xhr.status, xhr.responseText);
                    }
                });
            }

            const sbidModalEl = document.getElementById('sbidRuleModal');
            if (sbidModalEl) {
                sbidModalEl.addEventListener('show.bs.modal', function() {
                    renderSbidSlabRules(currentSbidSlabRules);
                });
            }

            $('#sbid-slab-rule-save-btn').on('click', function() {
                const errEl = document.getElementById('sbid-slab-rule-err');
                errEl.classList.add('d-none');
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

                const csrf = $('meta[name="csrf-token"]').attr('content') || '';
                $.ajax({
                    url: saveUrl,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({ rules: currentSbidSlabRules || [], _token: csrf }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
                        if (resp.rule && Array.isArray(resp.rule.rules)) currentSbidSlabRules = resp.rule.rules;
                        if (table) table.redraw(true);
                        if (typeof showToast === 'function') showToast('success', 'Sbid Rule saved');
                        setTimeout(() => { btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule'; }, 1200);
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-save me-1"></i>Save Rule';
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || xhr.responseText
                            || ('HTTP ' + xhr.status);
                        errEl.textContent = 'Error: ' + msg;
                        errEl.classList.remove('d-none');
                    }
                });
            });

            // Apply to Visible Rows — push each visible row's computed S Bid to eBay.
            const applyUrl = @json(url('/ebay/campaign-ads/push-sbid-slabs'));
            $('#sbid-slab-apply-btn').on('click', function() {
                const btn = this;
                const statusEl = document.getElementById('sbid-slab-rule-status');
                const errEl = document.getElementById('sbid-slab-rule-err');
                errEl.classList.add('d-none');

                if (!currentSbidSlabRules.length) {
                    errEl.textContent = 'Add at least one rule before applying.';
                    errEl.classList.remove('d-none');
                    return;
                }
                if (typeof table === 'undefined' || !table) {
                    errEl.textContent = 'Table not ready yet.';
                    errEl.classList.remove('d-none');
                    return;
                }

                // Collect visible (filtered) rows with a valid Sbid Rule slab bid.
                const rows = table.getRows('active');
                const skus = [];
                rows.forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!sku) return;
                    if (rd.is_parent_summary || rd.is_parent_row) return;
                    if (rd.Parent && String(rd.Parent).toUpperCase().startsWith('PARENT')) return;
                    const res = getCombinedSbid(rd);
                    if (res && !res.skip && res.bid > 0) skus.push(sku);
                });

                if (!skus.length) {
                    errEl.textContent = 'No visible rows match a slab with a valid S Bid.';
                    errEl.classList.remove('d-none');
                    return;
                }

                if (!confirm(`Push S Bid to eBay for ${skus.length} visible SKU(s)?`)) return;

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Applying…';
                if (statusEl) statusEl.textContent = `Pushing ${skus.length} SKU(s)…`;

                $.ajax({
                    url: applyUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ skus: skus, avg_l7_views: avgL7ViewsGlobal }),
                    success: function(resp) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Push to Ebay';
                        const s = resp.success || 0, f = resp.failed || 0, sk = resp.skipped || 0;
                        if (statusEl) statusEl.textContent = `Pushed: ${s} · Failed: ${f} · Skipped: ${sk}`;
                        if (typeof showToast === 'function') {
                            if (f === 0) showToast('success', `S Bid pushed to eBay for ${s} SKU(s)`);
                            else showToast('error', `Pushed ${s}, ${f} failed, ${sk} skipped`);
                        }
                    },
                    error: function(xhr) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-bolt me-1"></i>Push to Ebay';
                        errEl.textContent = 'Error: ' + ((xhr.responseJSON && xhr.responseJSON.error) || xhr.responseText);
                        errEl.classList.remove('d-none');
                    }
                });
            });

            // Prime on init so the S Bid column resolves immediately.
            if (document.readyState !== 'loading') {
                loadSbidSlabRules();
            } else {
                document.addEventListener('DOMContentLoaded', loadSbidSlabRules);
            }
        })();

    </script>
@endsection
