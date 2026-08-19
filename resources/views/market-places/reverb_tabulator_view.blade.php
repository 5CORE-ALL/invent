@extends('layouts.vertical', ['title' => 'Reverb - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
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

        #summary-stats {
            overflow-x: auto;
            overflow-y: hidden;
        }

        #summary-stats .summary-badges-row {
            flex-wrap: nowrap !important;
            white-space: nowrap;
            gap: 0.35rem !important;
            min-width: max-content;
        }

        #summary-stats .badge {
            font-size: 0.8rem !important;
            padding: 0.28rem 0.45rem !important;
            line-height: 1.2;
        }

        #summary-stats .badge.active-filter {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.85), 0 0 0 5px currentColor;
        }

        /* Column visibility — 4 groups (Basic / Pricing / Advertisement / Other) */
        #column-dropdown-menu.show,
        #column-dropdown-menu.column-dropdown-multicol {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
            column-count: unset;
        }
        #column-dropdown-menu > li.col-vis-full,
        #column-dropdown-menu > li.column-dropdown-span-all {
            list-style: none;
            column-span: all;
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
        #column-dropdown-menu .col-vis-item:active {
            cursor: grabbing;
        }
        #column-dropdown-menu .col-vis-item.col-vis-dragging {
            opacity: 0.45;
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
        @media (max-width: 768px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* ========== STATUS INDICATORS ========== */
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

        .status-circle.blue {
            background-color: #3591dc;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        /* ========== DROPDOWN STYLING ========== */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }

        .dropdown-item:hover {
            color: #1e2125;
            background-color: #e9ecef;
        }

        /* Sku Link LMP badges (same as amazon-tabulator-view) */
        .linked-sku-badge-wrap .sku-link-lmp-remove {
            font-size: 0.55rem;
            margin-left: 4px;
            opacity: 0.7;
        }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover {
            opacity: 1;
        }
        .sku-link-lmp-suggestion-item {
            cursor: pointer;
        }
        .sku-link-lmp-suggestion-item .form-check-input {
            margin-top: 0.2rem;
        }
        .sku-link-lmp-selected-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #e7f1ff;
            border: 1px solid #b6d4fe;
            border-radius: 999px;
            padding: 2px 8px;
            margin: 0 4px 4px 0;
            font-size: 12px;
        }
        .sku-link-lmp-selected-chip button {
            border: 0;
            background: transparent;
            line-height: 1;
            padding: 0 2px;
            cursor: pointer;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'reverb'])
        .sprice-lmp-alert {
            color: #dc3545;
            font-size: 11px;
            line-height: 1;
            margin-left: 4px;
            cursor: help;
        }
        #reverb-apply-std-price-btn {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        #reverb-apply-std-price-btn:hover,
        #reverb-apply-std-price-btn:focus {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #reverb-apply-std-price-btn:disabled { opacity: 0.65; }
        #reverb-apply-prmt-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #reverb-apply-prmt-btn:hover,
        #reverb-apply-prmt-btn:focus {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }
        #reverb-apply-prmt-btn:disabled { opacity: 0.65; }
        #reverb-apply-bump-btn {
            background: #fd7e14;
            border-color: #fd7e14;
            color: #fff;
        }
        #reverb-apply-bump-btn:hover,
        #reverb-apply-bump-btn:focus {
            background: #e8590c;
            border-color: #d9480f;
            color: #fff;
        }
        #reverb-apply-bump-btn:disabled { opacity: 0.65; }
        .reverb-push-prmt-btn .fa-spinner,
        .reverb-push-bump-btn .fa-spinner,
        .reverb-push-std-btn .fa-spinner {
            display: inline-block !important;
            animation: ch-promo-spin 0.75s linear infinite !important;
        }
        .tabulator-row .tabulator-cell[tabulator-field="push_std_prc"],
        .tabulator-row .tabulator-cell[tabulator-field="push_bump"] {
            padding: 2px 4px !important;
        }
        /* Dense body rows — same 36px as Amazon / eBay tabulator */
        #reverb-table .tabulator-row {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        #reverb-table .tabulator-row .tabulator-cell {
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
        #reverb-table .tabulator-row .tabulator-cell span,
        #reverb-table .tabulator-row .tabulator-cell a,
        #reverb-table .tabulator-row .tabulator-cell div,
        #reverb-table .tabulator-row .tabulator-cell button,
        #reverb-table .tabulator-row .tabulator-cell label,
        #reverb-table .tabulator-row .tabulator-cell input:not([type="checkbox"]):not([type="radio"]),
        #reverb-table .tabulator-row .tabulator-cell select,
        #reverb-table .tabulator-row .tabulator-cell i {
            font-size: 13px !important;
            line-height: 1.2 !important;
        }
        #reverb-table .tabulator-row .tabulator-cell img.hover-thumb {
            width: 28px !important;
            height: 28px !important;
            max-width: 28px !important;
            max-height: 28px !important;
            object-fit: cover !important;
            display: block !important;
            flex-shrink: 0 !important;
        }
        #reverb-table .tabulator-row .tabulator-cell > div {
            flex-wrap: nowrap !important;
            max-width: 100%;
            overflow: hidden;
        }
        #reverb-table .reverb-push-std-btn,
        #reverb-table .reverb-push-prmt-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
            padding: 0 !important;
            height: 18px !important;
            min-height: 0 !important;
            max-height: 18px !important;
        }
        /* LMP Competitors – right-side drawer, full height, 40% width */
        #lmpModal {
            z-index: 1065 !important;
        }
        #lmpModal .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            margin: 0;
            width: 40%;
            max-width: 40%;
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
        #lmpModal .reverb-lmp-add-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 8px;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-img {
            flex: 0 0 48px;
            width: 48px;
        }
        /* Views vs Bump — right-side drawer, full height, same modal-md width */
        #reverbDilVsSBumpModal {
            z-index: 1066 !important;
        }
        #reverbDilVsSBumpModal .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            margin: 0;
            width: 500px;
            max-width: 500px;
            height: 100vh;
            max-height: 100vh;
            transform: none;
        }
        #reverbDilVsSBumpModal.fade .modal-dialog {
            transform: translateX(100%);
            transition: transform 0.25s ease-out;
        }
        #reverbDilVsSBumpModal.show .modal-dialog {
            transform: translateX(0);
        }
        #reverbDilVsSBumpModal .modal-content {
            height: 100%;
            max-height: 100vh;
            border-radius: 0;
            border: none;
            border-left: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
        }
        #reverbDilVsSBumpModal .modal-header,
        #reverbDilVsSBumpModal .modal-footer {
            flex-shrink: 0;
        }
        #reverbDilVsSBumpModal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
        }
        #lmpModal #lmpSkuImage {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            display: block;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-std {
            flex: 0 0 5.5rem;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-id {
            flex: 0 0 8.5rem;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-price,
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-ship {
            flex: 0 0 5.5rem;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-link,
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-title {
            flex: 1 1 0;
            min-width: 110px;
        }
        #lmpModal .reverb-lmp-add-row > .reverb-lmp-add-btn {
            flex: 0 0 auto;
        }
        #lmpModal .reverb-lmp-add-row .form-label {
            font-size: 12px;
            margin-bottom: 2px;
            white-space: nowrap;
        }
        #lmpModal .reverb-lmp-add-row .form-control,
        #lmpModal .reverb-lmp-add-row .btn {
            font-size: 13px;
        }
        #lmpModal .lmp-modal-sp-box {
            display: none !important;
        }
        #lmpModal .lmp-sp-col-th,
        #lmpModal .lmp-sp-cell {
            display: none !important;
        }
        #lmpModal .reverb-lmp-ours-row,
        #lmpModal .reverb-lmp-ours-row > td {
            background-color: #dbeafe !important;
            color: #1e3a8a;
            --bs-table-bg-type: #dbeafe;
            --bs-table-striped-bg: #dbeafe;
            --bs-table-hover-bg: #bfdbfe;
            font-weight: 600;
        }
        #lmpModal .reverb-lmp-ours-row:hover > td {
            background-color: #bfdbfe !important;
        }
        #lmpModal .reverb-lmp-ours-row .reverb-lmp-ours-price {
            font-size: 15px;
            font-weight: 700;
        }

        /* Metric history modal — same Graph UI as Amazon / eBay / Temu */
        #skuMetricsModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #skuMetricsModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #skuMetricsModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        #reverb-s-bump-menu-btn {
            background: #fd7e14;
            border-color: #fd7e14;
            color: #fff;
        }
        #reverb-s-bump-menu-btn:hover,
        #reverb-s-bump-menu-btn:focus,
        #reverb-s-bump-menu-btn.show {
            background: #e8590c;
            border-color: #d9480f;
            color: #fff;
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
        'page_title' => 'Reverb - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <!-- Summary Stats -->
                <div id="summary-stats" class="mb-2 p-2 bg-light rounded">
                    <div class="d-flex flex-nowrap align-items-center gap-1 summary-badges-row">
                        <span class="badge flex-shrink-0" id="rd-sum-qty-amount-badge" style="background-color: #5dade2; color: #111; font-weight: bold;" title="Sales from full reverb_daily_data table: SUM(quantity × amount), rounded to whole dollars">Sales: $0</span>
                        <span class="badge bg-dark flex-shrink-0" id="rd-daily-overview-badge" style="font-weight: bold;" title="Total units: SUM(quantity) across all reverb_daily_data order rows">Orders: —</span>
                        <span class="badge bg-info flex-shrink-0" id="gpft-list-badge" style="color: black; font-weight: bold;" title="Weighted GPFT% = Σ[sold_qty×(RV Price×take%−LP−Ship)] ÷ Σ(sold_qty×RV Price) — same method as /temu-decrease, using normal ship">GPFT: 0%</span>
                        <span class="badge flex-shrink-0" id="rd-ads-percent-badge" style="background-color: #fd7e14; color: white; font-weight: bold;" title="Reverb Ads% (Bump fees ÷ L30 Sales) — from /all-marketplace-master (same source Amz Ads badge uses)">Ads: {{ isset($reverbAdsPercent) ? round((float) $reverbAdsPercent, 1) . '%' : 'N/A' }}</span>
                        <span class="badge bg-info flex-shrink-0" id="npft-badge" style="color: black; font-weight: bold;" title="PFT% = GPFT% − Ads% (same as /amazon-tabulator-view)">PFT: 0%</span>
                        <span class="badge flex-shrink-0" id="groi-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="Weighted GROI% = Σ[sold_qty×(RV Price×take%−LP−Ship)] ÷ Σ(sold_qty×LP) — same method as /temu-decrease, using normal ship">GROI: 0%</span>
                        <span class="badge flex-shrink-0" id="nroi-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="NROI% = (Total PFT − Ad Spend) ÷ COGS × 100; Ad Spend = Ads% × Sales (same as /amazon-tabulator-view)">NROI: 0%</span>
                        <span class="badge flex-shrink-0" id="total-views-badge" style="background-color: #0d6efd; color: white; font-weight: bold;" title="Sum of bump impressions for currently filtered rows (Reverb GET /listings/{id}/bump → bump_v2_stats.impressions)">Views: 0</span>
                        <span class="badge flex-shrink-0" id="avg-views-badge" style="background-color: #0dcaf0; color: #111; font-weight: bold;" title="Average bump impressions per SKU for currently filtered rows (Σ Views ÷ SKU count)">Avg Views: 0</span>
                        <span class="badge flex-shrink-0" id="avg-cvr-badge" style="background-color: #20c997; color: #000; font-weight: bold;" title="Overall CVR = Σ(RV L30) ÷ Σ(Views) × 100 — same Amz formula as A_L30 ÷ Sess30">CVR: 0%</span>
                        <span class="badge flex-shrink-0" id="rd-qty-sum-badge" style="background-color: #17a2b8; color: white; font-weight: bold;" title="Sum of RD Qty column (reverb_daily_qty) for currently filtered rows">RD Qty: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="SKUs with RV L30 = 0 (same as Amz 0 Sold on A_L30)">0 Sold: 0</span>
                        <span class="badge flex-shrink-0" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="SKUs with RV L30 &gt; 0 (same as Amz Sold &gt;0 on A_L30)">&gt; 0 Sold: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amz">&lt; Amz: 0</span>
                        <span class="badge flex-shrink-0" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amz">&gt; Amz: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing listings (REQ + INV&gt;0 + RV Price = 0)">M L: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="inv-r-stock-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter stock mismatch (REQ + INV&gt;0 + |INV − R Stock| &gt; 3)">N Map: 0</span>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <select id="inventory-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 110px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="reverb-stock-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 110px;">
                        <option value="all">R Stock</option>
                        <option value="zero">0 R Stock</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm flex-shrink-0"
                        style="width: 110px;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex gap-1 flex-shrink-0">
                        <select id="gpft-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + /doba + /shopify-b2c + /macys
                         + /purchasing-power + /wayfair). Backed by `reverb_daily_qty`:
                           all  → no filter
                           sold → RV L30 > 0  (same as Amz A_L30 Sold filter)
                           zero → RV L30 = 0
                         Single source of truth. The #zero-sold-count-badge / #more-sold-count-badge
                         click handlers (and the ?badge=zero_sold|more_sold URL deep-link) all
                         drive this dropdown value, so badges + dropdown + URL stay in sync. --}}
                    <select id="sold-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;"
                            title="Filter by RV L30 sold quantity (same role as Amz A_L30 Sold filter)">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm flex-shrink-0" style="width: 120px;"
                            title="Filter by price push status (same as Amz)">
                        <option value="all">Status</option>
                        <option value="not-pushed">Not Pushed</option>
                        <option value="pushed">Pushed</option>
                        <option value="applied">Applied</option>
                        <option value="error">Error</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <!-- DIL Filter (Walmart-style dropdown) -->
                    <div class="dropdown manual-dropdown-container flex-shrink-0">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25&ndash;50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block flex-shrink-0">
                        <button class="btn btn-sm btn-secondary" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu column-dropdown-multicol" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                            <li class="dropdown-item column-dropdown-span-all">
                                <a class="fw-bold" href="#" id="show-all-columns-btn" style="text-decoration: none; color: inherit;">
                                    <i class="fa fa-eye"></i> Show All Columns</a>
                            </li>
                            <li class="dropdown-item column-dropdown-span-all">
                                <a class="fw-bold" href="#" id="show-default-columns-btn" style="text-decoration: none; color: inherit;">
                                    <i class="fa fa-undo"></i> Show Default Columns</a>
                            </li>
                            <li class="column-dropdown-span-all"><hr class="dropdown-divider"></li>
                            <!-- Column toggles populated by JavaScript below this divider -->
                        </ul>
                    </div>

                    <button id="export-btn" class="btn btn-sm btn-info flex-shrink-0" title="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>

                    <button id="bulk-mode-btn" class="btn btn-sm btn-primary flex-shrink-0 text-nowrap" title="Toggle bulk price editing — reveal checkboxes, then choose Decrease / Increase / Same Price">
                        <i class="fas fa-sliders-h"></i> Bulk Mode
                    </button>
                    <button type="button" id="clear-sprice-toolbar-btn" class="btn btn-sm btn-danger flex-shrink-0 text-nowrap clear-sprice-btn"
                        title="Clear SPRICE for selected SKUs (turn on Bulk Mode to select)">
                        <i class="fas fa-eraser"></i> Clear SPRICE
                    </button>

                    {{-- Amazon-style: selection count + Bulk Push Prices (visible when SKUs selected) --}}
                    <span class="badge bg-primary fs-6 p-2 flex-shrink-0" id="reverb-selected-rows-count" style="display: none;">
                        0 selected
                    </span>
                    <div class="dropdown d-inline-block flex-shrink-0" id="reverb-bulk-actions-container" style="display: none;">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button"
                            id="reverbBulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Bulk push SPRICE to Reverb">
                            <i class="fas fa-upload"></i> Bulk Push
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="reverbBulkActionsDropdown" style="min-width: 220px;">
                            <li class="px-3 py-2">
                                <div style="font-weight: 600; margin-bottom: 8px; color: #495057;">
                                    <i class="fas fa-upload"></i> Bulk Push Prices
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="reverb" id="bulkPushReverb" checked disabled>
                                    <label class="form-check-label" for="bulkPushReverb" style="color: #e85d04; font-weight: 500;">
                                        Reverb
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-primary w-100" id="execute-bulk-push-reverb" type="button">
                                    <i class="fas fa-paper-plane"></i> Push Selected
                                </button>
                            </li>
                        </ul>
                    </div>

                    {{-- Dil vs PRMT / CVR vs CPN / > 0 Sprice Vs Dil Rule --}}
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'reverb'])

                    <div class="btn-group flex-shrink-0">
                        <button type="button" class="btn btn-sm" id="reverb-zero-sold-prc-rule-btn"
                            title="Apply 0 Sold Dil% → Target GROI% → S PRC on selected (or visible) 0 Sold rows">
                            <i class="fas fa-sliders-h"></i> 0 Sold Prc Rule
                        </button>
                        <button type="button" class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Edit Dil% → Target GROI% rules">
                            <span class="visually-hidden">0 Sold Prc options</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" id="reverb-zero-sold-prc-rules-modal-btn">
                                    <i class="fas fa-sliders-h me-1"></i> Dil vs Target GROI…
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="reverb-zero-sold-prc-apply-now-btn">
                                    <i class="fas fa-magic me-1"></i> Apply 0 Sold Prc
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="btn-group flex-shrink-0">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="reverb-s-bump-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Sold vs Bump model — suggest S Bump% from RV L30 sold">
                            <i class="fas fa-sliders-h"></i> S Bump
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="reverb-s-bump-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="reverb-dil-vs-s-bump-btn">
                                    <i class="fas fa-sliders-h me-1" style="color:#fd7e14;"></i> Sold vs Bump…
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="reverb-apply-s-bump-btn">
                                    <i class="fas fa-magic me-1" style="color:#fd7e14;"></i> Apply S Bump
                                </a>
                            </li>
                        </ul>
                    </div>

                    <button type="button" class="btn btn-sm flex-shrink-0" id="reverb-apply-std-price-btn"
                        title="Queue Std Prc to the live Reverb listing via API. Selected SKUs if checked; otherwise all visible with Std &gt; 0 that changed since last push.">
                        <i class="fas fa-upload"></i> Apply Std Price
                    </button>
                    <button type="button" class="btn btn-sm flex-shrink-0" id="reverb-apply-prmt-btn"
                        title="Queue Reverb Drop the Price By at PRMT%. Listing / Std price is not changed. Selected SKUs if checked; otherwise all visible whose % changed since last push.">
                        <i class="fas fa-percent"></i> Apply Prmt%
                    </button>
                    <button type="button" class="btn btn-sm flex-shrink-0" id="reverb-apply-bump-btn"
                        title="Queue Reverb Bump bid at S Bump%. Selected SKUs if checked; otherwise all visible whose S Bump% differs from live Bump%.">
                        <i class="fas fa-upload"></i> Apply Bump
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = row.percentage, default 0.85) --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light flex-shrink-0"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            &#127919; ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 60px;"
                            title="Target ROI% applied to all selected rows when you click Apply">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light flex-shrink-0"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (margin − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            &#127919; GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 60px;"
                            title="Target GPFT% applied to all selected rows when you click Apply. Must be less than the Reverb take-home margin (typically < 85%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (margin − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    <input type="text" id="parent-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search Parent..." style="max-width: 160px;">
                    <input type="text" id="sku-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search SKU..." style="max-width: 160px;">
                </div>

            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) - ->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <select id="bulk-op-select" class="form-select form-select-sm" style="width: 150px;" title="Choose how the entered value is applied to selected SKUs">
                            <option value="decrease">&#8595; Decrease</option>
                            <option value="increase">&#8593; Increase</option>
                            <option value="same">&#61; Same Price</option>
                        </select>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-copy"></i> Sugg Amz Prc
                        </button>
                        <button id="clear-sprice-btn" type="button" class="btn btn-danger btn-sm clear-sprice-btn">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                        <button id="bulk-push-reverb-btn" class="btn btn-warning btn-sm" title="Bulk push SPRICE to Reverb for selected SKUs">
                            <i class="fas fa-upload"></i> Bulk Push Prices
                        </button>
                    </div>
                </div>
                <div id="reverb-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body -->
                    <div id="reverb-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="reverbEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reverbEditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="reverbEditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="reverbEditSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="reverbEditSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="reverbEditBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="reverbEditBuyerLink" placeholder="https://...">
                    </div>
                    <div id="reverbEditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="reverbSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal – right-side drawer -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Reverb Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="reverb-lmp-add-row">
                                    <div class="reverb-lmp-add-img">
                                        <img id="lmpSkuImage" src="" alt="SKU" title="SKU image">
                                    </div>
                                    <div class="reverb-lmp-add-std">
                                        <label class="form-label" for="reverbLmpStdPrc">Std Prc</label>
                                        <input type="number" class="form-control form-control-sm text-end fw-bold lmp-modal-sp-input"
                                            id="reverbLmpStdPrc" step="0.01" min="0.01" placeholder="0.00" title="Std Prc">
                                    </div>
                                    <div class="reverb-lmp-add-id">
                                        <label class="form-label">Reverb Item ID *</label>
                                        <input type="text" class="form-control form-control-sm" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., 67894128">
                                    </div>
                                    <div class="reverb-lmp-add-price">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control form-control-sm" id="addCompPrice" name="price"
                                            step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="reverb-lmp-add-ship">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control form-control-sm" id="addCompShipping"
                                            name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="reverb-lmp-add-link">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control form-control-sm" id="addCompLink" name="product_link"
                                            placeholder="https://reverb.com/item/...">
                                    </div>
                                    <div class="reverb-lmp-add-title">
                                        <label class="form-label">Product Title</label>
                                        <input type="text" class="form-control form-control-sm" id="addCompTitle"
                                            name="product_title" placeholder="Product title">
                                    </div>
                                    <div class="reverb-lmp-add-btn">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-sm text-nowrap">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
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

    <!-- Sku Link LMP Modal -->
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skuLinkLmpModalLabel">Sku Link LMP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Link one or more SKUs to <strong id="sku-link-lmp-source"></strong>. All linked SKUs will share LMP competitors.</p>
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
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'reverb'])

    <div class="modal fade" id="reverbZeroSoldPrcModal" tabindex="-1" aria-labelledby="reverbZeroSoldPrcModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="reverbZeroSoldPrcModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> 0 Sold Prc Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Rules for <strong>0 Sold</strong> only (<strong>RV L30 = 0</strong>), by Dil%
                        (OV L30 ÷ INV). Last column is <strong>Target GROI%</strong>.
                        <strong>Apply</strong> sets <strong>S PRC</strong> so SROI matches that GROI:
                        <code>S PRC = (LP × (1 + GROI%/100) + Ship) / margin</code>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="reverb-zero-sold-prc-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil%</th>
                                    <th style="width:45%;" class="text-end">Target GROI%</th>
                                </tr>
                            </thead>
                            <tbody id="reverb-zero-sold-prc-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="reverb-zero-sold-prc-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="reverb-zero-sold-prc-apply-btn"
                        title="Save Dil→GROI rules, then suggest S PRC on 0 Sold rows — selected if checked, otherwise all visible">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reverbDilVsSBumpModal" tabindex="-1" aria-labelledby="reverbDilVsSBumpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="reverbDilVsSBumpModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Sold vs Bump
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map <strong>Sold</strong> (RV L30) slabs to <strong>S Bump%</strong> (10 levels: 0, 1, …, &gt; 10).
                        <strong>Apply</strong> saves the rules and fills <strong>S Bump%</strong> from each row’s Sold.
                        If <strong>INV = 0</strong>, S Bump% is forced to <strong>0</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="reverb-dil-s-bump-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Sold</th>
                                    <th style="width:45%;" class="text-end">S Bump%</th>
                                </tr>
                            </thead>
                            <tbody id="reverb-dil-s-bump-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="reverb-dil-s-bump-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="reverb-dil-s-bump-apply-btn"
                        title="Save Sold→Bump rules, then fill S Bump% — selected rows if checked, otherwise all visible">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal (same Graph UI as Amazon / eBay / Temu) -->
    <div class="modal fade p-0" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Reverb - <span id="modalSkuName"></span> - <span id="skuChartRefLabel">Views</span> <span id="skuChartModalSuffix">(Rolling L30)</span></span>
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
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="skuChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="skuMetricsChart"></canvas>
                        </div>
                        <div id="skuChartRefPanel" style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0; flex-wrap: nowrap; overflow-x: auto;">
                            <div class="sku-ref-col" data-metric="0" style="min-width: 62px; text-align: center; padding: 4px 4px;">
                                <div style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;"><span id="skuChartRefDot" class="sku-col-dot" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0000FF; flex-shrink: 0;"></span><span id="skuChartRefLabelOnly">Views</span></div>
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
@endsection

@section('script-bottom')
<script>
    /** Shared column visibility — same /tabulator-column-visibility endpoint as Amazon (channel_tabulator_column_settings). */
    const TABULATOR_COLUMN_CHANNEL = 'reverb_tabulator';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    const REVERB_DAILY_TOTALS_URL = @json(url('reverb-daily-data-totals-json'));
    // Columns that stay hidden even when "Show All Columns" is used.
    const adsOnlyColumnFields = ['Parent', 'Missing_Ad', 'RE_BID'];
    const adsAlwaysVisibleFields = ['Bump', 'API_REC_BID'];
    // Designed default view (column defs with visible:false). Used by Show Default Columns.
    const reverbDefaultHiddenFields = {
        Parent: 1,
        Missing_Ad: 1,
        RE_BID: 1,
        'A Price': 1,
        Profit: 1,
        'Sales L30': 1,
        LP_productmaster: 1,
        Ship_productmaster: 1,
        _select: 1,
    };
    let table = null;
    let allTableData = []; // Full dataset for ParentExpand
    // Reverb channel Ads% (TACOS) — same stored value as /all-marketplace-master (Amazon pattern).
    // Used for PFT% = GPFT% − Ads%, SNPFT = SGPFT − Ads%, and NROI/SNROI.
    const REVERB_CHANNEL_ADS_PCT = {{ isset($reverbAdsPercent) ? (float) $reverbAdsPercent : 0 }};
    let reverbAdsPct = REVERB_CHANNEL_ADS_PCT;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'reverb'])

    function reverbPrmtPctOf(d) {
        const n = parseFloat(d && (d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied));
        return (isFinite(n) && n > 0) ? n : 0;
    }
    function reverbCpnPctOf(d) {
        const n = parseFloat(d && (d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied));
        return (isFinite(n) && n > 0) ? n : 0;
    }
    /** Sale price from channel-pef-promo: Std × (1 − (PRMT% + CPN%)/100). */
    function reverbPrmtSalePrice(d) {
        if (typeof chPromoTemuSpriceFromStdPrmtCpn === 'function') {
            return chPromoTemuSpriceFromStdPrmtCpn(d) || 0;
        }
        const std = parseFloat(d && d.STANDARD_PRICE);
        if (!(isFinite(std) && std > 0)) return 0;
        const total = Math.min(99.99, reverbPrmtPctOf(d) + reverbCpnPctOf(d));
        return +(std * (1 - (total / 100))).toFixed(2);
    }
    function reverbParseBumpPct(val) {
        if (val === null || val === undefined || val === '') return 0;
        const n = parseFloat(String(val).replace(/%/g, ''));
        return isFinite(n) && n >= 0 ? n : 0;
    }
    function reverbSBumpPctOf(d) {
        return reverbParseBumpPct(d && d.RE_BID);
    }
    function reverbLiveBumpPctOf(d) {
        return reverbParseBumpPct(d && d.Bump);
    }
    function reverbPushBumpColumnDef() {
        return {
            title: 'Push B%',
            field: 'push_bump',
            hozAlign: 'center',
            vertAlign: 'middle',
            headerSort: false,
            width: 52,
            headerTooltip: 'Push S Bump% to the live Reverb Bump bid. Click header to bulk selected (or visible) SKUs.',
            titleFormatter: function() {
                return '<button type="button" class="btn btn-sm p-0 reverb-push-bump-header-btn" '
                    + 'title="Queue Bump bid for selected SKUs whose S Bump% changed" '
                    + 'style="border:none;background:none;cursor:pointer;color:#000;'
                    + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                    + 'Push B%</button>';
            },
            headerClick: function(e) {
                if (e.target.closest('.reverb-push-bump-header-btn')) {
                    e.stopPropagation();
                    e.preventDefault();
                    if (typeof queueReverbPushBump === 'function') queueReverbPushBump();
                    return false;
                }
            },
            formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const sku = String(d['(Child) sku'] || d.sku || '').trim();
                if (!sku || String(sku).toUpperCase().indexOf('PARENT') !== -1 || d.is_parent_summary) {
                    return '';
                }
                const bump = reverbSBumpPctOf(d);
                const live = reverbLiveBumpPctOf(d);
                const status = String(d.PUSH_BUMP_STATUS || cell.getValue() || '');
                const last = parseFloat(d.PUSH_BUMP_VALUE);
                const lastOk = isFinite(last) && last >= 0;
                const needs = status === 'error' || (lastOk
                    ? Number(last).toFixed(1) !== Number(bump).toFixed(1)
                    : Number(live).toFixed(1) !== Number(bump).toFixed(1));
                let icon = '<i class="fas fa-upload"></i>';
                let color = '#fd7e14';
                let tip = bump > 0
                    ? ('Push Bump ' + bump.toFixed(0) + '% to Reverb')
                    : 'Remove Bump on Reverb';
                if (status === 'processing') {
                    icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                    color = '#ffc107';
                    tip = 'Applying Bump bid…';
                } else if (status === 'error') {
                    icon = '<i class="fa-solid fa-xmark"></i>';
                    color = '#dc3545';
                    tip = 'Last Bump push failed — click to retry';
                } else if (!needs) {
                    icon = '<i class="fa-solid fa-check-double"></i>';
                    color = '#28a745';
                    tip = 'Already pushed Bump ' + (lastOk ? Number(last).toFixed(0) : live.toFixed(0)) + '% — click to push again';
                } else if (lastOk || live > 0) {
                    const from = lastOk ? Number(last).toFixed(0) : live.toFixed(0);
                    tip = 'Bump changed ' + from + '% → ' + bump.toFixed(0) + '% — click to update';
                }
                return '<button type="button" class="btn btn-sm p-0 reverb-push-bump-btn" '
                    + 'data-sku="' + sku.replace(/"/g, '&quot;') + '" '
                    + 'data-bump="' + bump.toFixed(0) + '" '
                    + 'title="' + tip.replace(/"/g, '&quot;') + '" '
                    + 'style="border:none;background:none;cursor:pointer;color:' + color
                    + ';padding:0;line-height:1;vertical-align:middle;">'
                    + icon + '</button>';
            },
            cellClick: function(e, cell) {
                const btn = e.target.closest('.reverb-push-bump-btn');
                if (!btn) return;
                e.stopPropagation();
                e.preventDefault();
                if (btn.disabled) return false;
                const d = cell.getRow().getData() || {};
                if (String(d.PUSH_BUMP_STATUS || '') === 'processing') return false;
                if (typeof queueReverbPushBump === 'function') {
                    const sku = String(d['(Child) sku'] || '').trim();
                    const selected = (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.size > 1
                        && selectedSkus.has(sku));
                    queueReverbPushBump(selected ? null : cell.getRow());
                }
                return false;
            }
        };
    }
    function reverbChannelPromoColumns() {
        const cols = typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [];
        if (typeof channelPromoSprcCpnColumn === 'function') {
            cols.push(channelPromoSprcCpnColumn());
        }
        // S PRC already has its own Push column — do not show Push % / push_prmt.
        return cols.filter(function(c) { return !c || c.field !== 'push_prmt'; });
    }

    /** Take-home margin factor (Reverb ~0.85). */
    function reverbTakeRate(rowData) {
        const pct = parseFloat(rowData && rowData.percentage);
        return (isFinite(pct) && pct > 0 && pct <= 1) ? pct : 0.85;
    }

    /**
     * Same calculate-data as GPFT / GROI / NPFT / NROI for any price (RV Price or SPRICE).
     *   GPFT% = (price × margin − LP − Ship) / price × 100
     *   GROI% = (price × margin − LP − Ship) / LP × 100
     *   NPFT% = GPFT% − Ads%
     *   NROI% = (gross$ − price × Ads%) / LP × 100
     */
    function reverbComputePriceMetrics(price, rowData) {
        const p = parseFloat(price);
        const lpRaw = rowData && (rowData.LP_productmaster != null && rowData.LP_productmaster !== ''
            ? rowData.LP_productmaster : rowData.LP);
        const shipRaw = rowData && (rowData.Ship_productmaster != null && rowData.Ship_productmaster !== ''
            ? rowData.Ship_productmaster : rowData.Ship);
        const lp = parseFloat(lpRaw);
        const ship = parseFloat(shipRaw) || 0;
        const margin = reverbTakeRate(rowData);
        const adsPct = parseFloat(REVERB_CHANNEL_ADS_PCT) || 0;
        if (!isFinite(p) || p <= 0) {
            return { gpft: null, groi: null, npft: null, nroi: null };
        }
        const cogs = (isFinite(lp) ? lp : 0);
        const grossPft = (p * margin) - ship - cogs;
        const gpft = (grossPft / p) * 100;
        // PFT% / SNPFT = rounded GPFT% − Ads% (same as the PFT % column)
        const npft = Math.round(gpft) - adsPct;
        let groi = null;
        let nroi = null;
        if (isFinite(lp) && lp > 0) {
            groi = (grossPft / lp) * 100;
            nroi = ((grossPft - (p * adsPct / 100)) / lp) * 100;
        }
        return { gpft: gpft, groi: groi, npft: npft, nroi: nroi };
    }

    function reverbSpricePushSku(d) {
        return String((d && (d['(Child) sku'] || d.sku)) || '').trim();
    }
    function reverbSpricePushIsChild(d) {
        const sku = reverbSpricePushSku(d);
        return !!(sku && String(sku).toUpperCase().indexOf('PARENT') === -1
            && d && !d.is_parent_summary && !d.is_parent);
    }
    /** True when SPRICE should be sent to Reverb (not already pushed at this price). */
    function reverbSpriceNeedsPush(d) {
        const sprice = parseFloat(d && d.SPRICE);
        if (!(sprice > 0)) return false;
        const status = String((d && d.SPRICE_STATUS) || '');
        if (status === 'processing') return false;
        if (status === 'error') return true;
        const last = parseFloat(d && d.SPRICE_PUSHED_VALUE);
        const lastOk = isFinite(last) && last > 0;
        if (lastOk && last.toFixed(2) === sprice.toFixed(2) && (status === 'pushed' || status === 'applied')) {
            return false;
        }
        if (!lastOk && status === 'pushed') return false;
        return true;
    }

    function reverbSpriceMetricPatch(sprice, rowData) {
        const m = reverbComputePriceMetrics(sprice, rowData);
        const rnd = function(v) { return (v == null || !isFinite(v)) ? 0 : Math.round(v); };
        return {
            SGPFT: rnd(m.gpft),
            SPFT: rnd(m.npft),
            SNPFT: rnd(m.npft),
            SROI: rnd(m.groi),
            SNROI: rnd(m.nroi),
        };
    }

    function reverbComputeSgpft(rowData) {
        const m = reverbComputePriceMetrics(rowData && rowData.SPRICE, rowData);
        return m.gpft;
    }
    function reverbComputeSroi(rowData) {
        const m = reverbComputePriceMetrics(rowData && rowData.SPRICE, rowData);
        return m.groi;
    }
    function reverbComputeSnpft(rowData) {
        const m = reverbComputePriceMetrics(rowData && rowData.SPRICE, rowData);
        return m.npft;
    }

    /**
     * Net SNROI — same shape as NROI:
     *   (gross profit $ − ad spend $) / COGS × 100
     */
    function reverbComputeNetSroi(rowData) {
        const m = reverbComputePriceMetrics(rowData && rowData.SPRICE, rowData);
        return m.nroi;
    }

    /** Net NROI on live RV Price (same calculate-data as SNROI). */
    function reverbComputeNetRoi(rowData) {
        const m = reverbComputePriceMetrics(rowData && rowData['RV Price'], rowData);
        return m.nroi;
    }

    /**
     * Dil color — Red <25, Green 25–50, Pink 50%+ (OV L30 ÷ INV).
     */
    function reverbDilColorBand(d) {
        const inv = parseFloat(d && d.INV) || 0;
        const ovL30 = parseFloat(d && d.L30) || 0;
        const dil = inv === 0 ? 0 : (ovL30 / inv) * 100;
        if (dil < 25) return 'red';
        if (dil < 50) return 'green';
        return 'pink';
    }
    function reverbDilColorHex(band) {
        if (band === 'red') return '#a00211';
        if (band === 'green') return '#28a745';
        if (band === 'pink') return '#e83e8c';
        return '#6c757d';
    }
    /** PFT / SNPFT color via MetricPctColors (GPFT bands by default; pass field for NPFT). */
    function reverbPftColor(percent, field) {
        if (window.MetricPctColors) {
            return MetricPctColors.colorForField(field || 'GPFT%', percent) || '#dc3545';
        }
        return '#dc3545';
    }

    /** ROI / SNROI / Sroi color via MetricPctColors. */
    function reverbRoiColor(percent, field) {
        if (window.MetricPctColors) {
            return MetricPctColors.colorForField(field || 'GROI%', percent) || '#dc3545';
        }
        return '#dc3545';
    }

    function applyReverbStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            if (!d || d.is_parent_summary || d.is_parent) return;
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

    document.addEventListener('lmp-modal-sp-saved', function(e) {
        const detail = (e && e.detail) || {};
        const sku = detail.sku;
        const saved = parseFloat(detail.standard_price);
        if (!sku || !isFinite(saved) || saved <= 0) return;
        applyReverbStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
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

    // Bulk mode active state (single merged button).
    let bulkModeActive = false;

    // Reflect the chosen operation (decrease / increase / same) onto the legacy
    // mode flags so applyDiscount() and Target ROI/GPFT keep working unchanged.
    function applyBulkOpSelection() {
        const op = $('#bulk-op-select').val();
        decreaseModeActive = bulkModeActive && op === 'decrease';
        increaseModeActive = bulkModeActive && op === 'increase';
        samePriceModeActive = bulkModeActive && op === 'same';
        syncDiscountInputUi();
    }

    function resetBulkModeBtn() {
        $('#bulk-mode-btn').removeClass('btn-danger').addClass('btn-primary')
            .html('<i class="fas fa-sliders-h"></i> Bulk Mode');
    }

    // Swap the discount-input panel between %/$ and Same Price modes.
    function syncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (samePriceModeActive) {
            $('#discount-type-select-wrap').hide();
            $('#discount-input-label').removeClass('d-none');
            $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
            $('#apply-discount-btn').text('Apply Same Price');
        } else {
            $('#discount-type-select-wrap').show();
            $('#discount-input-label').addClass('d-none');
            const t = $('#discount-type-select').val();
            $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
            $('#apply-discount-btn').text('Apply');
        }
    }

    let skuMetricsChart = null;
    let currentSku = null;
    let currentSkuChartMetric = 'views';
    let skuChartFirstSeriesStats = null;

    function reverbChartFmtVal(v) {
        if (currentSkuChartMetric === 'price') {
            return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
        }
        if (currentSkuChartMetric === 'cvr') {
            return (Number(v) === v ? Number(v).toFixed(1) : v) + '%';
        }
        return Math.round(Number(v) || 0).toLocaleString('en-US');
    }

    function initSkuMetricsChart() {
        const canvas = document.getElementById('skuMetricsChart');
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');

        const medianLinePlugin = {
            id: 'reverbMedianLine',
            afterDraw(chart) {
                if (!skuChartFirstSeriesStats || skuChartFirstSeriesStats.median === undefined) return;
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const cctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(skuChartFirstSeriesStats.median);
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
            id: 'reverbValueLabels',
            afterDatasetsDraw(chart) {
                if (!chart.data.datasets.length) return;
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const cctx = chart.ctx;
                cctx.save();
                cctx.font = 'bold 7px Inter, system-ui, sans-serif';
                cctx.textAlign = 'center';
                cctx.textBaseline = 'bottom';
                const valueFmt = (skuChartFirstSeriesStats && skuChartFirstSeriesStats.valueFmt) ? skuChartFirstSeriesStats.valueFmt : reverbChartFmtVal;
                const labelColors = skuChartFirstSeriesStats && skuChartFirstSeriesStats.labelColors ? skuChartFirstSeriesStats.labelColors : [];
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
                    label: 'Views',
                    data: [],
                    borderColor: '#0000FF',
                    backgroundColor: 'rgba(0,0,255,0.1)',
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
                                if (currentSkuChartMetric === 'cvr') return 'CVR%: ' + Number(v).toFixed(1) + '%';
                                return 'Views: ' + Math.round(v).toLocaleString('en-US');
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
                            if (currentSkuChartMetric === 'cvr') return v.toFixed(0) + '%';
                            return Math.round(v).toLocaleString('en-US');
                        } }
                    }
                }
            }
        });
    }

    function loadSkuMetricsData(sku, days = 30, metricOverride) {
        const chartMetric = metricOverride != null ? metricOverride : (currentSkuChartMetric || 'views');
        $('#skuChartLoading').show();
        $('#skuChartContainer').hide();
        $('#chart-no-data-message').hide();
        const daysNum = days === 0 || days === '0' ? 0 : (parseInt(days, 10) || 30);
        fetch(`/reverb-metrics-history?days=${daysNum}&sku=${encodeURIComponent(sku)}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                $('#skuChartLoading').hide();
                if (!skuMetricsChart) return;
                function setSkuRefCol(high, med, low, fmt) {
                    const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
                    const hEl = document.getElementById('skuCol0High');
                    const mEl = document.getElementById('skuCol0Med');
                    const lEl = document.getElementById('skuCol0Low');
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
                    skuMetricsChart.data.datasets[0].data = [];
                    skuMetricsChart.update('active');
                    $('#skuChartContainer').hide();
                    $('#chart-no-data-message').show();
                    return;
                }
                $('#chart-no-data-message').hide();
                $('#skuChartContainer').show();
                const labels = data.map(d => d.date_formatted || d.date || '');
                const metric = chartMetric;
                const isCvr = metric === 'cvr';
                const isViews = metric === 'views';
                const values = isCvr
                    ? data.map(d => Number(d.cvr_percent) || 0)
                    : isViews
                        ? data.map(d => Number(d.views) || 0)
                        : data.map(d => Number(d.price) || 0);
                const metricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%' };
                const metricColors = { price: '#adb5bd', views: '#0000FF', cvr: '#008000' };
                const bgColors = { price: 'rgba(108,117,125,0.08)', views: 'rgba(0,0,255,0.1)', cvr: 'rgba(0,128,0,0.1)' };
                const labelText = metricLabels[metric] || 'Views';
                const color = metricColors[metric] || '#0000FF';
                const refLabelEl = document.getElementById('skuChartRefLabel');
                const refLabelOnlyEl = document.getElementById('skuChartRefLabelOnly');
                const refDotEl = document.getElementById('skuChartRefDot');
                if (refLabelEl) refLabelEl.textContent = labelText;
                if (refLabelOnlyEl) refLabelOnlyEl.textContent = labelText;
                if (refDotEl) refDotEl.style.background = color;
                const cvrFmt = v => (Number(v) === v ? Number(v).toFixed(1) : v) + '%';
                const intFmt = v => Math.round(Number(v) || 0).toLocaleString('en-US');
                const refFmt = isCvr ? cvrFmt : isViews ? intFmt : reverbChartFmtVal;
                skuMetricsChart.data.labels = labels;
                skuMetricsChart.data.datasets[0].data = values;
                skuMetricsChart.data.datasets[0].label = labelText;
                skuMetricsChart.data.datasets[0].borderColor = color;
                skuMetricsChart.data.datasets[0].backgroundColor = bgColors[metric] || 'rgba(0,0,255,0.1)';
                if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y && skuMetricsChart.options.scales.y.ticks) {
                    skuMetricsChart.options.scales.y.ticks.callback = function(v) {
                        if (metric === 'price') return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v));
                        if (metric === 'cvr') return v.toFixed(0) + '%';
                        return Math.round(v).toLocaleString('en-US');
                    };
                }
                const s0 = statsForArr(values);
                setSkuRefCol(s0.max, s0.median, s0.min, refFmt);
                const refRed = '#dc3545';
                const refGray = '#6c757d';
                const refGreen = '#198754';
                const dotColors = values.map((v, i) => {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                });
                const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);
                skuChartFirstSeriesStats = { values, median: s0.median, dataMin: s0.min, dataMax: s0.max, dotColors, labelColors, valueFmt: refFmt };
                skuMetricsChart.data.datasets[0].pointBackgroundColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderColor = dotColors;
                skuMetricsChart.data.datasets[0].pointBorderWidth = 1.5;
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
                $('#skuChartContainer').hide();
                $('#chart-no-data-message').show();
                console.error('Error loading Reverb SKU metrics:', error);
            });
    }

    $(document).ready(function() {
        try { initSkuMetricsChart(); } catch (e) { console.error('Reverb: SKU chart init failed', e); }

        $('#sku-chart-days-filter').on('change', function() {
            const daysNum = parseInt($(this).val(), 10);
            const rangeLabel = daysNum === 0 ? 'Lifetime' : 'L' + daysNum;
            const metricLabels = { cvr: 'CVR%', views: 'Views', price: 'Price' };
            const metricLabel = metricLabels[currentSkuChartMetric] || 'Views';
            $('#skuChartModalSuffix').text('(Rolling ' + rangeLabel + ')');
            if (currentSku) loadSkuMetricsData(currentSku, daysNum || 0, currentSkuChartMetric);
        });

        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });
        $('#bulk-op-select').on('change', function() { applyBulkOpSelection(); });

        // Bulk Price Mode Toggle — reveals checkboxes; operation chosen via #bulk-op-select.
        $('#bulk-mode-btn').on('click', function() {
            bulkModeActive = !bulkModeActive;
            const selectColumn = table.getColumn('_select');

            if (bulkModeActive) {
                $(this).removeClass('btn-primary').addClass('btn-danger')
                    .html('<i class="fas fa-sliders-h"></i> Bulk Mode ON');
                selectColumn.show();
                $('#discount-input-container').show();
                applyBulkOpSelection();
            } else {
                resetBulkModeBtn();
                selectColumn.hide();
                selectedSkus.clear();
                updateSelectedCount();
                applyBulkOpSelection();
            }
            syncDiscountInputUi();
        });

        // Select all checkbox handler
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
            
            filteredData.forEach(row => {
                if (isChecked) {
                    selectedSkus.add(row['(Child) sku']);
                } else {
                    selectedSkus.delete(row['(Child) sku']);
                }
            });
            
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
         * Target ROI% bulk apply (Reverb, margin = row.percentage || 0.85)
         * ----------------------------------------------------------------
         * For every selected row with a usable LP, back-solve the sale price so
         * the resulting SROI column matches Target ROI%:
         *     SROI = ((sprice * margin − ship − lp) / lp) * 100
         *   → sprice = (lp * (1 + ROI%/100) + ship) / margin
         * Optimistic SGPFT/SPFT/SROI are written client-side, then the existing
         * bulk /reverb-save-sprice endpoint reconciles them server-side.
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
                showToast('Please select at least one SKU first (turn on Bulk Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const marginRaw = parseFloat(rowData['percentage']);
                const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                const candidate = (lp * roiMultiplier + ship) / margin;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                row.update(Object.assign({
                    SPRICE: newSprice,
                    has_custom_sprice: true
                }, reverbSpriceMetricPatch(newSprice, rowData)));
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        /*
         * Target GPFT% bulk apply (Reverb)
         * --------------------------------
         * Back-solves so SGPFT = Target GPFT%:
         *     SGPFT = ((sprice * margin − ship − lp) / sprice) * 100
         *   → sprice = (lp + ship) / (margin − GPFT%/100)
         * Constraint: (margin − target/100) must be > 0 (target < margin*100).
         */
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
                showToast('Please select at least one SKU first (turn on Bulk Price Mode to reveal checkboxes)', 'error');
                return;
            }

            const targetFraction = targetGpftPct / 100;
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;
            const skippedHighGpft = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const marginRaw = parseFloat(rowData['percentage']);
                const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.85;
                const denom = margin - targetFraction;
                if (denom <= 0) { skippedHighGpft.push(sku); return; }
                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                row.update(Object.assign({
                    SPRICE: newSprice,
                    has_custom_sprice: true
                }, reverbSpriceMetricPatch(newSprice, rowData)));
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                if (skippedHighGpft.length > 0) {
                    showToast(`Target GPFT% ${targetGpftPct}% is too high — must be less than each row's take-home margin (typically < 85%).`, 'error');
                } else {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                }
                return;
            }

            saveSpriceUpdates(updates);
            let note = '';
            if (skippedNoLp > 0)        note += ` (${skippedNoLp} skipped — no LP)`;
            if (skippedHighGpft.length) note += ` (${skippedHighGpft.length} skipped — target ≥ margin)`;
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Sugg Amz Prc button
        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        // Clear SPRICE — toolbar + bulk-bar buttons
        $(document).on('click', '.clear-sprice-btn', function() {
            clearSpriceForSelected();
        });

        // Sold badges just toggle the #sold-filter dropdown so the dropdown stays the
        // single source of truth for the Sold filter (mirrors Amazon tabulator behavior).
        // Clicking the same badge twice clears the filter (toggle semantics preserved).
        $('#zero-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#more-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            applyFilters();
        });

        // < Amz badge click handler - filter prices less than Amazon
        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function() {
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // > Amz badge click handler - filter prices greater than Amazon
        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function() {
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // Missing / Map / N Map badge filters (also opened from all-marketplace-master ?badge=)
        let missingFilterActive = false;
        let mapFilterActive = false;
        let invRStockFilterActive = false;

        function clearReverbBadgeFilters() {
            missingFilterActive = mapFilterActive = invRStockFilterActive = false;
            // Sold filter lives on the #sold-filter dropdown now — reset it here too so
            // this helper still fully clears any active Sold-style filter.
            $('#sold-filter').val('all');
        }

        function syncReverbBadgeFilterStyles() {
            $('#missing-count-badge').toggleClass('active-filter', missingFilterActive);
            $('#map-count-badge').toggleClass('active-filter', mapFilterActive);
            $('#inv-r-stock-badge').toggleClass('active-filter', invRStockFilterActive);
        }

        // Columns hidden while the "Missing L" badge filter is active
        const missingHiddenColumnFields = [
            'RV Price',
            'GPFT%', 'ROI%', 'NPFT', 'NROI', 'SPRICE', 'SGPFT', 'SROI', 'SNPFT', 'SNROI',
            'prmt_pct', 'cpn_pct', 'sprc_cpn', 'push_std_prc',
            'RV L30', 'reverb_daily_qty', 'reverb_daily_qty_x_subtotal', 'reverb_daily_qty_x_amount', 'R Stock',
            'Views', 'CVR',
            'L30', 'RV Dil%', 'Profit', 'Sales L30', 'LP_productmaster', 'Ship_productmaster'
        ];

        // Remember each column's visibility before the filter hid it, so we can restore it
        let missingColumnPrevVisibility = null;

        function applyMissingColumnVisibility() {
            if (!table) return;
            if (missingFilterActive) {
                if (!missingColumnPrevVisibility) {
                    missingColumnPrevVisibility = {};
                    missingHiddenColumnFields.forEach(function(field) {
                        const col = table.getColumn(field);
                        if (col) missingColumnPrevVisibility[field] = col.isVisible();
                    });
                }
                missingHiddenColumnFields.forEach(function(field) {
                    const col = table.getColumn(field);
                    if (col) col.hide();
                });
            } else if (missingColumnPrevVisibility) {
                missingHiddenColumnFields.forEach(function(field) {
                    const col = table.getColumn(field);
                    if (!col) return;
                    if (missingColumnPrevVisibility[field]) col.show();
                    else col.hide();
                });
                missingColumnPrevVisibility = null;
            }
            buildColumnDropdown();
        }

        function applyReverbUrlBadgeFilter() {
            const badge = (new URLSearchParams(window.location.search).get('badge') || '').toLowerCase();
            if (badge && table) {
                clearReverbBadgeFilters();
                if (badge === 'missing') missingFilterActive = true;
                else if (badge === 'map') mapFilterActive = true;
                else if (badge === 'nmap') invRStockFilterActive = true;
                else if (badge === 'zero_sold') $('#sold-filter').val('zero');
                else if (badge === 'more_sold') $('#sold-filter').val('sold');
                syncReverbBadgeFilterStyles();
                applyMissingColumnVisibility();
            }
            applyFilters();
        }

        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = invRStockFilterActive = false;
            syncReverbBadgeFilterStyles();
            applyMissingColumnVisibility();
            applyFilters();
        });

        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = invRStockFilterActive = false;
            syncReverbBadgeFilterStyles();
            applyFilters();
        });

        $('#inv-r-stock-badge').on('click', function() {
            invRStockFilterActive = !invRStockFilterActive;
            missingFilterActive = mapFilterActive = false;
            syncReverbBadgeFilterStyles();
            applyFilters();
        });

        // ========== MANUAL DROPDOWN FUNCTIONALITY (Walmart-style) ==========
        // Initialize dropdown functionality
        $(document).on('click', '.manual-dropdown-container .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            
            // Close other dropdowns
            $('.manual-dropdown-container').not(container).removeClass('show');
            
            // Toggle current dropdown
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const column = $item.data('column');
            const color = $item.data('color');
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            
            // Update active state
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            // Update button text and icon
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            // Close dropdown
            container.removeClass('show');
            
            // Apply filters
            applyFilters();
        });

        // Close dropdowns when clicking outside
        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        // Update selected count display
        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            // Keep the bulk panel visible whenever Bulk Price Mode is on (even with 0 selected).
            $('#discount-input-container').toggle(bulkModeActive || count > 0);
            // Amazon-style toolbar: show count + Bulk Push when any SKU is selected
            if (count > 0) {
                $('#reverb-selected-rows-count').text(count + ' selected').show();
                $('#reverb-bulk-actions-container').show();
            } else {
                $('#reverb-selected-rows-count').hide();
                $('#reverb-bulk-actions-container').hide();
            }
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
                [...filteredSkus].every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        // Custom price rounding function to round to .99 endings
        function roundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            // Round to the nearest dollar and subtract 0.01 to make it .99
            const roundedDollar = Math.ceil(price);
            return roundedDollar - 0.01;
        }

        // Apply discount / same-price to selected SKUs (based on RV Price for %/$).
        function applyDiscount() {
            const discountType = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Bulk Price Mode first', 'error');
                return;
            }
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            let updatedCount = 0;
            const updates = []; // Store updates for backend saving

            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);

                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const currentPrice = parseFloat(rowData['RV Price']) || 0;

                    // Same Price mode applies even when RV Price is empty;
                    // %/$ modes still require a positive RV Price to compute against.
                    if (samePriceModeActive || currentPrice > 0) {
                        let newSprice;

                        if (samePriceModeActive) {
                            newSprice = Math.max(0.99, discountValue);
                        } else if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSprice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSprice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSprice = currentPrice + discountValue;
                            } else {
                                newSprice = currentPrice - discountValue;
                            }
                        }

                        // Apply retail price rounding (round to .99 endings)
                        newSprice = roundToRetailPrice(newSprice);

                        // Ensure minimum price
                        newSprice = Math.max(0.99, newSprice);

                        row.update(Object.assign({
                            SPRICE: newSprice,
                            has_custom_sprice: true
                        }, reverbSpriceMetricPatch(newSprice, rowData)));

                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: newSprice
                        });

                        updatedCount++;
                    }
                }
            });

            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            const action = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Discount');
            const suffix = samePriceModeActive ? '' : ' based on RV Price';
            showToast(`${action} applied to ${updatedCount} SKU(s)${suffix}`, 'success');
            $('#discount-percentage-input').val('');
        }

        // Apply Amazon suggested price
        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = []; // Store updates for backend saving

            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['A Price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        row.update(Object.assign({
                            SPRICE: amazonPrice,
                            has_custom_sprice: true
                        }, reverbSpriceMetricPatch(amazonPrice, rowData)));
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }
            
            let message = `Amz price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amz price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
        }

        const REVERB_DIL_S_BUMP_DEFAULTS = [
            { key: '0', label: '0', bump: 10 },
            { key: '1', label: '1', bump: 9 },
            { key: '2', label: '2', bump: 8 },
            { key: '3', label: '3', bump: 7 },
            { key: '4', label: '4', bump: 6 },
            { key: '5', label: '5', bump: 5 },
            { key: '6', label: '6', bump: 4 },
            { key: '7', label: '7', bump: 3 },
            { key: '8-10', label: '8–10', bump: 2 },
            { key: 'gt-10', label: '> 10', bump: 0 },
        ];
        let reverbDilSBumpRules = REVERB_DIL_S_BUMP_DEFAULTS.map(function(r) { return Object.assign({}, r); });

        function reverbFormatSBump(val) {
            if (val === null || val === undefined || val === '') return '';
            const raw = String(val).trim();
            if (raw === '') return '';
            const n = parseFloat(raw.replace(/%/g, ''));
            if (!isFinite(n)) return raw;
            return String(Math.round(n)) + '%';
        }
        function reverbSoldSlabKey(sold) {
            const n = Math.round(Number(sold));
            if (!isFinite(n) || n <= 0) return '0';
            if (n > 10) return 'gt-10';
            if (n >= 8) return '8-10';
            return String(n);
        }
        function reverbBumpForSold(sold) {
            const key = reverbSoldSlabKey(sold);
            const rule = reverbDilSBumpRules.find(function(r) { return r.key === key; });
            const n = rule ? Number(rule.bump) : 0;
            return isFinite(n) && n >= 0 ? n : 0;
        }
        function renderReverbDilSBumpModalTable() {
            const $tb = $('#reverb-dil-s-bump-tbody').empty();
            reverbDilSBumpRules.forEach(function(r, idx) {
                const bump = isFinite(Number(r.bump)) ? Number(r.bump) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm reverb-dil-s-bump-input" '
                    + 'min="0" step="0.1" value="' + bump + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readReverbDilSBumpRulesFromModal() {
            $('#reverb-dil-s-bump-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.reverb-dil-s-bump-input').val());
                const rule = reverbDilSBumpRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.bump = (isFinite(val) && val >= 0) ? val : 0;
            });
            return reverbDilSBumpRules.map(function(r) {
                return { key: r.key, label: r.label, bump: Number(r.bump) || 0 };
            });
        }
        async function loadReverbDilSBumpRules() {
            $('#reverb-dil-s-bump-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/channel-promo-pricing/reverb/dil-bump',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    reverbDilSBumpRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderReverbDilSBumpModalTable();
                $('#reverb-dil-s-bump-status').text(res && res.is_default
                    ? 'Using first-time defaults. Apply to save & fill S Bump%.'
                    : 'Saved Sold vs Bump rules loaded.');
            } catch (e) {
                renderReverbDilSBumpModalTable();
                $('#reverb-dil-s-bump-status').text('Could not load saved rules — showing defaults.');
            }
        }
        async function saveReverbDilSBumpRules() {
            const rules = readReverbDilSBumpRulesFromModal();
            await $.ajax({
                url: '/channel-promo-pricing/reverb/dil-bump',
                method: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { rules: rules, _token: csrfToken() },
            });
            reverbDilSBumpRules = rules.map(function(r) { return Object.assign({}, r); });
        }
        function collectReverbSBumpTargets() {
            if (typeof collectChPromoSelectedRows === 'function') {
                const selected = collectChPromoSelectedRows();
                if (selected.length) return { targets: selected, label: 'selected' };
            }
            if (typeof collectChPromoVisibleRows === 'function') {
                return { targets: collectChPromoVisibleRows(), label: 'all visible' };
            }
            if (!table) return { targets: [], label: 'visible' };
            const rows = table.getRows('active') || [];
            const targets = [];
            rows.forEach(function(row) {
                const d = row.getData() || {};
                const sku = String(d['(Child) sku'] || '').trim();
                if (!sku || String(sku).toUpperCase().indexOf('PARENT') !== -1 || d.is_parent_summary || d.is_parent) return;
                targets.push({ row: row, d: d });
            });
            return { targets: targets, label: 'all visible' };
        }
        async function applyReverbSBumpToTargets(targets, label) {
            if (!targets.length) {
                showToast('No rows to apply S Bump', 'error');
                return;
            }
            const updates = [];
            let filled = 0;
            for (let i = 0; i < targets.length; i++) {
                const job = targets[i];
                const d = job.d || (job.row && job.row.getData()) || {};
                const sku = String(d['(Child) sku'] || '').trim();
                if (!sku) continue;
                const inv = (typeof chPromoInv === 'function') ? chPromoInv(d) : (parseFloat(d.INV) || 0);
                const sold = parseFloat(d['RV L30']) || 0;
                const bump = inv === 0 ? 0 : reverbBumpForSold(sold);
                const value = reverbFormatSBump(bump);
                try {
                    await Promise.resolve(job.row.update({ RE_BID: value }));
                    if (typeof job.row.reformat === 'function') job.row.reformat();
                } catch (e) { /* ignore */ }
                updates.push({ sku: sku, recommended_bid: value || null });
                filled++;
            }
            if (updates.length) {
                await $.ajax({
                    url: '/reverb-save-recommended-bids',
                    method: 'POST',
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                    contentType: 'application/json',
                    data: JSON.stringify({ updates: updates }),
                });
            }
            showToast('Sold vs Bump (' + label + '): S Bump% → ' + filled + ' row(s)', 'success');
        }
        async function saveAndApplyReverbSBump() {
            if (!$('#reverb-dil-s-bump-tbody tr').length) {
                await loadReverbDilSBumpRules();
            }
            const collected = collectReverbSBumpTargets();
            let targets = collected.targets;
            let label = collected.label;
            if (!targets.length) {
                showToast('No rows to apply S Bump', 'error');
                return;
            }
            if (label === 'all visible') {
                if (!confirm('No rows selected — save rules and apply Sold→Bump to all ' + targets.length + ' visible row(s)?')) {
                    return;
                }
            }
            const $btn = $('#reverb-dil-s-bump-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveReverbDilSBumpRules();
                await applyReverbSBumpToTargets(targets, label);
                $('#reverbDilVsSBumpModal').modal('hide');
            } catch (xhr) {
                showToast('S Bump apply failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'), 'error');
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        const REVERB_ZERO_SOLD_PRC_DEFAULTS = [
            { key: '0-10', label: '0–10%', groi: 40 },
            { key: '10-20', label: '10–20%', groi: 35 },
            { key: '20-30', label: '20–30%', groi: 30 },
            { key: '30-40', label: '30–40%', groi: 25 },
            { key: '40-50', label: '40–50%', groi: 20 },
            { key: '50-60', label: '50–60%', groi: 15 },
            { key: '60-70', label: '60–70%', groi: 12 },
            { key: '70-80', label: '70–80%', groi: 10 },
            { key: '80-90', label: '80–90%', groi: 8 },
            { key: '90-100', label: '90–100%', groi: 5 },
            { key: 'gt-100', label: '> 100%', groi: 0 },
        ];
        let reverbZeroSoldPrcRules = REVERB_ZERO_SOLD_PRC_DEFAULTS.map(function(r) { return Object.assign({}, r); });

        function reverbRowLp(d) {
            const lp = parseFloat(d && (d.LP_productmaster != null ? d.LP_productmaster : d.LP));
            return (isFinite(lp) && lp > 0) ? lp : 0;
        }
        function reverbRowInv(d) {
            if (typeof chPromoInv === 'function') return chPromoInv(d);
            return parseFloat(d && d.INV) || 0;
        }
        function reverbRowSold(d) {
            if (typeof chPromoReverbSoldQty === 'function') return chPromoReverbSoldQty(d);
            return parseFloat(d && d['RV L30']) || 0;
        }
        function reverbDilPct(d) {
            // Same as the Dil column: OV L30 ÷ INV × 100 (do not use stored RV Dil% ratio).
            const inv = reverbRowInv(d);
            const ovL30 = parseFloat(d && (d.L30 != null ? d.L30 : d['L30'])) || 0;
            if (inv <= 0) return 0;
            return (ovL30 / inv) * 100;
        }
        function reverbZeroSoldDilSlabKey(dil) {
            const n = Number(dil);
            if (!isFinite(n) || n < 0) return '0-10';
            if (n > 100) return 'gt-100';
            const bucket = Math.min(9, Math.floor(n / 10));
            const lo = bucket * 10;
            return lo + '-' + (lo + 10);
        }
        function reverbGroiForZeroSoldDil(dil) {
            const key = reverbZeroSoldDilSlabKey(dil);
            const rule = reverbZeroSoldPrcRules.find(function(r) { return r.key === key; });
            const n = rule ? Number(rule.groi) : 0;
            return isFinite(n) ? n : 0;
        }
        function reverbSpriceFromTargetGroi(rowData, groiPct) {
            const lp = reverbRowLp(rowData);
            if (lp <= 0) return 0;
            const ship = parseFloat(rowData && (rowData.Ship_productmaster != null && rowData.Ship_productmaster !== ''
                ? rowData.Ship_productmaster : rowData.Ship)) || 0;
            const margin = reverbTakeRate(rowData);
            if (!(margin > 0)) return 0;
            const groi = isFinite(Number(groiPct)) ? Number(groiPct) : 0;
            const targetRound = Math.round(groi);
            let price = (lp * (1 + groi / 100) + ship) / margin;
            if (!(isFinite(price) && price > 0)) return 0;
            price = Math.round(price * 100) / 100;
            const metricRow = Object.assign({}, rowData, {
                LP_productmaster: lp,
                Ship_productmaster: ship
            });
            const shownGroi = function(p) {
                const m = reverbComputePriceMetrics(p, metricRow);
                return (m.groi == null || !isFinite(m.groi)) ? null : Math.round(m.groi);
            };
            if (shownGroi(price) === targetRound) return price;
            for (let delta = 1; delta <= 20; delta++) {
                for (let sign = 1; sign >= -1; sign -= 2) {
                    const p = Math.round((price + sign * delta * 0.01) * 100) / 100;
                    if (p <= 0) continue;
                    if (shownGroi(p) === targetRound) return p;
                }
            }
            return price;
        }
        function reverbZeroSoldPrcSroiTitle(d, currentGroi) {
            const sold = reverbRowSold(d);
            const inv = reverbRowInv(d);
            const lp = reverbRowLp(d);
            const dil = reverbDilPct(d);
            const target = reverbGroiForZeroSoldDil(dil);
            const sprice = parseFloat(d && d.SPRICE) || 0;
            const rulePrice = reverbSpriceFromTargetGroi(d, target);
            const shown = (currentGroi == null || !isFinite(currentGroi)) ? null : Math.round(currentGroi);
            const prmt = Math.max(0, Number(d && (d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied)) || 0);
            if (sold > 0) {
                return 'SGROI is from SPRICE. 0 Sold Prc Rule does not apply (RV L30 > 0).';
            }
            if (!(inv > 0) || !(lp > 0)) {
                return '0 Sold Prc Rule needs RV L30 = 0, INV > 0, and LP > 0.';
            }
            if (shown === Math.round(target)) {
                return '0 Sold Prc Rule: Dil ' + dil.toFixed(1) + '% → Target SGROI ' + Math.round(target) + '%.';
            }
            return '0 Sold Prc Rule: Dil ' + dil.toFixed(1) + '% (0–10% slab → ' + Math.round(target)
                + '% SGROI) needs SPRICE $' + rulePrice.toFixed(2)
                + '. Current ' + shown + '% is from SPRICE $' + sprice.toFixed(2)
                + (prmt > 0 ? (' = Std × (1 − ' + prmt + '% PRMT)') : ' (PRMT/Std discount)')
                + ', not the GROI price. Click 0 Sold Prc Rule to apply.';
        }
        function renderReverbZeroSoldPrcModalTable() {
            const $tb = $('#reverb-zero-sold-prc-tbody').empty();
            reverbZeroSoldPrcRules.forEach(function(r, idx) {
                const groi = isFinite(Number(r.groi)) ? Number(r.groi) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm reverb-zero-sold-groi-input" '
                    + 'step="0.1" value="' + groi + '" data-idx="' + idx + '" title="Target GROI% for this Dil slab">'
                    + '</td></tr>'
                );
            });
        }
        function readReverbZeroSoldPrcRulesFromModal() {
            $('#reverb-zero-sold-prc-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.reverb-zero-sold-groi-input').val());
                const rule = reverbZeroSoldPrcRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.groi = isFinite(val) ? val : 0;
            });
            return reverbZeroSoldPrcRules.map(function(r) {
                return { key: r.key, label: r.label, groi: Number(r.groi) || 0 };
            });
        }
        async function loadReverbZeroSoldPrcRules() {
            $('#reverb-zero-sold-prc-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: '/channel-promo-pricing/reverb/zero-sold-prc',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    reverbZeroSoldPrcRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderReverbZeroSoldPrcModalTable();
                $('#reverb-zero-sold-prc-status').text(res && res.is_default
                    ? 'Using first-time defaults. Apply to save & suggest S PRC on 0 Sold rows.'
                    : 'Saved 0 Sold Prc rules loaded.');
            } catch (e) {
                renderReverbZeroSoldPrcModalTable();
                $('#reverb-zero-sold-prc-status').text('Could not load saved rules — showing defaults.');
            }
        }
        async function saveReverbZeroSoldPrcRules() {
            const rules = readReverbZeroSoldPrcRulesFromModal();
            await $.ajax({
                url: '/channel-promo-pricing/reverb/zero-sold-prc',
                method: 'POST',
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { rules: rules, _token: csrfToken() },
            });
            reverbZeroSoldPrcRules = rules.map(function(r) { return Object.assign({}, r); });
        }
        function collectReverbZeroSoldPrcTargets() {
            const collected = collectReverbSBumpTargets();
            const zeroSold = collected.targets.filter(function(job) {
                const d = (job.row && job.row.getData()) || job.d || {};
                const sold = reverbRowSold(d);
                const inv = reverbRowInv(d);
                const lp = reverbRowLp(d);
                return sold <= 0 && inv > 0 && lp > 0;
            });
            return { targets: zeroSold, label: collected.label, selectedCount: collected.targets.length };
        }
        function applyReverbZeroSoldPrcToTargets(targets, label) {
            if (!targets.length) {
                showToast('No 0 Sold rows (RV L30 = 0, INV > 0, LP > 0) to price', 'error');
                return 0;
            }
            const updates = [];
            let filled = 0;
            let skipped = 0;
            targets.forEach(function(job) {
                const d = (job.row && job.row.getData()) || job.d || {};
                const sku = String(d['(Child) sku'] || d.sku || '').trim();
                if (!sku) {
                    skipped++;
                    return;
                }
                const groi = reverbGroiForZeroSoldDil(reverbDilPct(d));
                const newSprice = reverbSpriceFromTargetGroi(d, groi);
                if (!isFinite(newSprice) || newSprice <= 0) {
                    skipped++;
                    return;
                }
                try {
                    if (job.row) {
                        job.row.update(Object.assign({
                            SPRICE: newSprice,
                            has_custom_sprice: true,
                            SPRICE_STATUS: 'applied',
                            SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString(),
                            ZERO_SOLD_PRC_APPLIED: true,
                            ZERO_SOLD_PRC_GROI: groi
                        }, reverbSpriceMetricPatch(newSprice, d)));
                        if (typeof job.row.reformat === 'function') job.row.reformat();
                    }
                } catch (e) { /* ignore */ }
                updates.push({ sku: sku, sprice: newSprice, status: 'applied', zero_sold_prc: 1, groi: groi });
                filled++;
            });
            if (updates.length) {
                saveSpriceUpdates(updates);
            }
            const note = skipped > 0 ? ' (' + skipped + ' skipped)' : '';
            showToast('0 Sold Prc Rule (' + label + '): S PRC from Dil→Target SGROI → ' + filled + ' row(s)' + note, filled ? 'success' : 'warning');
            return filled;
        }
        async function saveAndApplyReverbZeroSoldPrc(opts) {
            opts = opts || {};
            const $toolbar = $('#reverb-zero-sold-prc-rule-btn');
            const $modalBtn = $('#reverb-zero-sold-prc-apply-btn');
            const $busy = opts.fromToolbar ? $toolbar : $modalBtn;
            const html = $busy.html();
            $busy.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                if (opts.fromToolbar) {
                    await loadReverbZeroSoldPrcRules();
                } else {
                    if (!$('#reverb-zero-sold-prc-tbody tr').length) {
                        await loadReverbZeroSoldPrcRules();
                    }
                    await saveReverbZeroSoldPrcRules();
                }
                const collected = collectReverbZeroSoldPrcTargets();
                const targets = collected.targets;
                const label = collected.label;
                if (!targets.length) {
                    if (collected.selectedCount > 0) {
                        showToast('Selected rows are not 0 Sold (need RV L30 = 0, INV > 0, LP > 0)', 'error');
                    } else {
                        showToast('No 0 Sold rows (RV L30 = 0, INV > 0, LP > 0) to price', 'error');
                    }
                    return;
                }
                if (label === 'all visible' && !opts.skipConfirm) {
                    if (!confirm('No rows selected — apply 0 Sold Prc rules to all ' + targets.length + ' visible 0 Sold row(s)?')) {
                        return;
                    }
                }
                applyReverbZeroSoldPrcToTargets(targets, label);
                if (!opts.fromToolbar) {
                    $('#reverbZeroSoldPrcModal').modal('hide');
                }
            } catch (xhr) {
                showToast('0 Sold Prc apply failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'), 'error');
            } finally {
                $busy.prop('disabled', false).html(html);
            }
        }

        // Save recommended bid (RE BID) to database
        function saveRecommendedBid(sku, recommendedBid) {
            $.ajax({
                url: '/reverb-save-recommended-bid',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify({
                    sku: sku,
                    recommended_bid: recommendedBid || null
                }),
                contentType: 'application/json',
                success: function() {
                    showToast('Recommended bid saved for ' + sku, 'success');
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save recommended bid';
                    showToast(msg, 'error');
                }
            });
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '/reverb-save-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        console.log('SPRICE updates saved successfully:', response.updated, 'records');
                        // Show subtle success notification
                        if (response.errors && response.errors.length > 0) {
                            console.warn('Some updates had errors:', response.errors);
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error saving SPRICE updates:', xhr);
                    let errorMessage = 'Error saving SPRICE updates to database';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ': ' + xhr.responseJSON.error;
                    }
                    showToast(errorMessage, 'error');
                }
            });
        }

        // Clear SPRICE for selected SKUs (dedicated endpoint — unsets keys, never stores 0)
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first (turn on Bulk Mode)', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            const updates = [];
            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                if (sku && selectedSkus.has(sku)) {
                    updates.push({ sku: sku });
                }
            });

            if (updates.length === 0) {
                showToast('No SPRICE values to clear for selected SKUs', 'warning');
                return;
            }

            $('.clear-sprice-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');

            $.ajax({
                url: '/reverb-clear-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: { updates: updates },
                success: function(response) {
                    table.getRows().forEach(row => {
                        const rowData = row.getData();
                        const sku = rowData['(Child) sku'];
                        if (sku && selectedSkus.has(sku)) {
                            row.update(Object.assign({
                                SPRICE: 0,
                                has_custom_sprice: false,
                                SPRICE_STATUS: null,
                                SPRICE_STATUS_UPDATED_AT: null,
                                SPRICE_PUSHED_VALUE: null,
                                SPRICE_PUSHED_BY: null,
                                ZERO_SOLD_PRC_APPLIED: false,
                                ZERO_SOLD_PRC_GROI: null
                            }, reverbSpriceMetricPatch(0, rowData)));
                        }
                    });
                    const n = (response && response.cleared_count != null) ? response.cleared_count : updates.length;
                    showToast((response && response.message) ? response.message : `SPRICE cleared for ${n} SKU(s)`, 'success');
                },
                error: function(xhr) {
                    console.error('Failed to clear SPRICE:', xhr.status, xhr.responseJSON || xhr.responseText);
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                        ? (xhr.responseJSON.error || xhr.responseJSON.message)
                        : 'Failed to clear SPRICE data';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $('.clear-sprice-btn').prop('disabled', false).html('<i class="fas fa-eraser"></i> Clear SPRICE');
                }
            });
        }

        // SAVE SPRICE to database with retry
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            const maxRetries = 3;
            
            $.ajax({
                url: '/reverb-save-sprice',
                method: 'POST',
                data: {
                    sku: sku,
                    sprice: sprice,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`SPRICE saved for ${sku}`, 'success');
                    const savedPatch = {};
                    if (response.spft_percent !== undefined) {
                        savedPatch.SPFT = response.spft_percent;
                        savedPatch.SNPFT = response.spft_percent;
                    }
                    if (response.sroi_percent !== undefined) savedPatch.SROI = response.sroi_percent;
                    if (response.sgpft_percent !== undefined) savedPatch.SGPFT = response.sgpft_percent;
                    if (response.snroi_percent !== undefined) savedPatch.SNROI = response.snroi_percent;
                    if (Object.keys(savedPatch).length) row.update(savedPatch);
                },
                error: function(xhr) {
                    if (retryCount < maxRetries) {
                        setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1), 2000);
                    } else {
                        showToast(`Failed to save SPRICE for ${sku}`, 'error');
                    }
                }
            });
        }

        // ========== LMP + Sku Link LMP (same as amazon/ebay tabulator) ==========
        let currentLmpData = { sku: null, competitors: [], lowestPrice: null, linkedLmpSkus: [] };
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

        function escAttr(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function escapeHtmlAttr(text) {
            return escapeHtml(text).replace(/"/g, '&quot;');
        }

        function rowSkuForLinkLmp(rowData) {
            return String(rowData?.['(Child) sku'] || rowData?.sku || '').trim();
        }

        function linkedLmpSkuFormatter(cell) {
            const row = cell.getRow().getData();
            const rowSku = rowSkuForLinkLmp(row);
            let skus = row.linked_lmp_skus || [];
            if (typeof skus === 'string') {
                try { skus = JSON.parse(skus) || []; } catch (e) { skus = []; }
            }
            if (!Array.isArray(skus)) skus = [];
            if (!skus.length && rowSku) skus = [rowSku];

            const seen = new Set();
            skus = skus.filter(function (sku) {
                const norm = String(sku || '').trim().toUpperCase();
                if (!norm || seen.has(norm)) return false;
                seen.add(norm);
                return true;
            });

            const badges = skus.length
                ? skus.map(function (sku) {
                    const skuText = String(sku || '').trim();
                    const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                    const removeBtn = isSelf
                        ? ''
                        : `<button type="button" class="btn-close sku-link-lmp-remove"
                            data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link"></button>`;
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
            if (!rowSku) return '';
            return `<div class="d-flex align-items-center justify-content-center py-1">
                <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn"
                    title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}">
                    <i class="mdi mdi-plus"></i> +
                </button>
            </div>`;
        }

        function applyAffectedLinkedSkuRows(affected) {
            if (!table || !Array.isArray(affected)) return;
            const bySku = {};
            affected.forEach(function (item) {
                if (item?.sku) bySku[item.sku] = item.linked_lmp_skus || [];
            });
            table.getRows().forEach(function (row) {
                const data = row.getData();
                const sku = rowSkuForLinkLmp(data);
                if (Object.prototype.hasOwnProperty.call(bySku, sku)) {
                    row.update({ linked_lmp_skus: bySku[sku] });
                }
            });
            table.replaceData();
        }

        function removeLinkedSkuFromRow(rowData, linkedSku) {
            const sku = rowSkuForLinkLmp(rowData);
            const target = String(linkedSku || '').trim();
            if (!sku || !target) return;
            if (!confirm(`Remove LMP link between "${sku}" and "${target}"?`)) return;

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
                if (!response.success) throw new Error(response.message || 'Could not remove linked SKU.');
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
            if (countEl) countEl.textContent = String(selected.length);
            if (saveLabel) {
                saveLabel.textContent = selected.length > 1 ? ('Link ' + selected.length + ' SKUs') : 'Link SKU(s)';
            }
            if (!wrap || !listEl) return;
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
            if (!wrap) return;
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
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function (res) { return res.json(); })
                .then(function (response) {
                    if (requestId !== linkedSkuSuggestionRequestId) return;
                    if (!response.success) throw new Error(response.message || 'Could not search SKUs.');
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
                    if (requestId !== linkedSkuSuggestionRequestId) return;
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
                const already = selected.some(function (sku) { return sku.toUpperCase() === inputVal.toUpperCase(); });
                if (!already) selected.push(inputVal);
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
            if (!toLink.length) {
                alert('Select one or more SKUs from the list, or enter a SKU to link.');
                return;
            }
            const allSkus = [sourceSku].concat(toLink);
            const uniqueSkus = [];
            const seen = new Set();
            allSkus.forEach(function (sku) {
                const norm = String(sku || '').trim().toUpperCase();
                if (!norm || seen.has(norm)) return;
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
                    body: JSON.stringify({ sku: sourceSku, linked_sku: toLink[0] }),
                });

            fetchPromise
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (!response.success) throw new Error(response.message || 'Could not link SKU(s).');
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

        function reverbSkuRowData(sku) {
            const key = String(sku || '').trim().toUpperCase();
            if (!key) return null;
            const match = function(d) {
                if (!d) return false;
                return String(d['(Child) sku'] || d.sku || d.SKU || '').trim().toUpperCase() === key;
            };
            if (typeof table !== 'undefined' && table && table.getRows) {
                const rows = (function() {
                    try { return table.getRows('all') || []; } catch (e) { return table.getRows() || []; }
                })();
                for (let i = 0; i < rows.length; i++) {
                    const d = rows[i].getData && rows[i].getData();
                    if (match(d)) return d;
                }
            }
            if (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) {
                for (let i = 0; i < allTableData.length; i++) {
                    if (match(allTableData[i])) return allTableData[i];
                }
            }
            return null;
        }
        function reverbSkuImageUrl(sku) {
            const d = reverbSkuRowData(sku);
            return d ? String(d.image_path || d.Image || d.image || '').trim() : '';
        }
        function setReverbLmpSkuImage(sku) {
            const url = reverbSkuImageUrl(sku);
            const img = document.getElementById('lmpSkuImage');
            if (!img) return;
            img.src = url || '';
            img.style.visibility = url ? 'visible' : 'hidden';
            img.alt = sku ? ('SKU ' + sku) : 'SKU';
        }
        function loadReverbCompetitorsModal(sku, linkedLmpSkus) {
            $('#lmpSku').text(sku);
            $('#addCompSku').val(sku);
            setReverbLmpSkuImage(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');
            currentLmpData.sku = sku;
            currentLmpData.linkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
            $('#lmpModal').modal('show');
            $('#lmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `);
            $.ajax({
                url: '/reverb-lmp-data',
                method: 'GET',
                traditional: true,
                data: { sku: sku, linked_lmp_skus: currentLmpData.linkedLmpSkus },
                success: function(response) {
                    const comps = (response && response.success && Array.isArray(response.competitors))
                        ? response.competitors : [];
                    currentLmpData.competitors = comps;
                    currentLmpData.lowestPrice = response && response.lowest_price;
                    renderReverbCompetitorsList(comps, currentLmpData.lowestPrice);
                },
                error: function() {
                    $('#lmpDataList').html(`
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-triangle"></i> Could not load competitors. Please try again.
                        </div>
                    `);
                }
            });
        }

        function reverbOurProductRowHtml(d) {
            if (!d) return '';
            const sku = String(d['(Child) sku'] || d.sku || currentLmpData.sku || '').trim();
            const price = parseFloat(d['RV Price'] || d.price || 0) || 0;
            const img = String(d.image_path || d.Image || d.image || '').trim();
            const link = String(d['B Link'] || d['S Link'] || '').trim();
            const imageCell = img
                ? `<img src="${escAttr(img)}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                : '<span class="text-muted">—</span>';
            const priceHtml = price > 0
                ? ('<span class="reverb-lmp-ours-price">$' + price.toFixed(2) + '</span>'
                    + ' <span class="badge bg-primary">5 CORE</span>')
                : '<span class="text-muted">—</span> <span class="badge bg-primary">5 CORE</span>';
            const actionHtml = link
                ? ('<a href="' + escAttr(link) + '" target="_blank" class="btn btn-sm btn-info" title="Open our listing">'
                    + '<i class="fa fa-external-link"></i></a>')
                : '<span class="text-muted small">—</span>';
            return '<tr class="reverb-lmp-ours-row">'
                + '<td>' + imageCell + '</td>'
                + '<td>' + priceHtml + '</td>'
                + '<td class="text-muted">—</td>'
                + '<td>' + (price > 0 ? ('<strong>$' + price.toFixed(2) + '</strong>') : '<span class="text-muted">—</span>') + '</td>'
                + '<td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                + escAttr(sku || 'Our product') + '</td>'
                + '<td>' + actionHtml + '</td>'
                + '</tr>';
        }
        function renderReverbCompetitorsList(competitors, lowestPrice) {
            const list = Array.isArray(competitors) ? competitors.slice() : [];
            list.sort(function(a, b) {
                const pa = parseFloat(a && a.total_price);
                const pb = parseFloat(b && b.total_price);
                const aOk = isFinite(pa);
                const bOk = isFinite(pb);
                if (!aOk && !bOk) return 0;
                if (!aOk) return 1;
                if (!bOk) return -1;
                return pa - pb;
            });
            const ours = reverbSkuRowData(currentLmpData.sku);
            const ourPrice = ours ? (parseFloat(ours['RV Price'] || ours.price || 0) || 0) : 0;
            const ourHtml = reverbOurProductRowHtml(ours || { '(Child) sku': currentLmpData.sku });

            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `<thead class="table-dark"><tr>
                <th>Image</th><th>Price</th><th>Shipping</th><th>Total</th><th>Title</th><th>Actions</th>
            </tr></thead><tbody>`;

            let inserted = false;
            list.forEach(function(item) {
                const total = parseFloat(item.total_price);
                if (!inserted && ourPrice > 0 && isFinite(total) && total >= ourPrice) {
                    html += ourHtml;
                    inserted = true;
                }
                const isLowest = lowestPrice != null && isFinite(total)
                    && Math.abs(total - parseFloat(lowestPrice)) < 0.01;
                const rowClass = isLowest ? 'table-success' : '';
                const badge = isLowest ? '<span class="badge bg-success ms-2">Lowest</span>' : '';
                const productLink = item.link || `https://reverb.com/item/${item.item_id}`;
                const imageCell = item.image
                    ? `<img src="${escAttr(item.image)}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                    : '<span class="text-muted">—</span>';
                html += `<tr class="${rowClass}">
                    <td>${imageCell}</td>
                    <td>$${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td>${parseFloat(item.shipping_cost || 0) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                    <td><strong>$${parseFloat(item.total_price || 0).toFixed(2)}</strong> ${badge}</td>
                    <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escAttr(item.title || 'N/A')}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="${escAttr(productLink)}" target="_blank" class="btn btn-sm btn-info" title="View on Reverb"><i class="fa fa-external-link"></i></a>
                            <button class="btn btn-sm btn-danger delete-reverb-lmp-btn"
                                data-id="${item.id}" data-item-id="${escAttr(item.item_id)}" data-price="${item.total_price}" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            if (!inserted) html += ourHtml;
            if (!list.length && !ourHtml) {
                html += '<tr><td colspan="6" class="text-muted text-center">No competitors found yet. Add your first competitor above!</td></tr>';
            }
            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }

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
            loadReverbCompetitorsModal(sku, linkedSkus);
        });

        $('#addCompetitorForm').on('submit', function(e) {
            e.preventDefault();
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
            $.ajax({
                url: '/reverb-lmp-add',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
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
                        $('#addCompItemId').val('');
                        $('#addCompPrice').val('');
                        $('#addCompShipping').val('');
                        $('#addCompLink').val('');
                        $('#addCompTitle').val('');
                        loadReverbCompetitorsModal($('#addCompSku').val(), currentLmpData.linkedLmpSkus);
                        if (table) table.replaceData();
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

        $(document).on('click', '.delete-reverb-lmp-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const itemId = $btn.data('item-id');
            const price = $btn.data('price');
            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) return;
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: '/reverb-lmp-delete',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        loadReverbCompetitorsModal(currentLmpData.sku, currentLmpData.linkedLmpSkus);
                        if (table) table.replaceData();
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

        const skuLinkLmpModalEl = document.getElementById('skuLinkLmpModal');
        if (skuLinkLmpModalEl) {
            linkedSkuModal = bootstrap.Modal.getOrCreateInstance(skuLinkLmpModalEl);
        }
        document.getElementById('sku-link-lmp-input')?.addEventListener('input', function () {
            renderLinkedSkuSuggestions(this.value);
        });
        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('click', function (e) {
            const item = e.target.closest('.sku-link-lmp-suggestion-item');
            if (!item) return;
            const cb = item.querySelector('.sku-link-lmp-suggestion-cb');
            if (!cb || e.target === cb) return;
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.getElementById('sku-link-lmp-suggestions')?.addEventListener('change', function (e) {
            const cb = e.target.closest('.sku-link-lmp-suggestion-cb');
            if (!cb) return;
            const sku = String(cb.value || '').trim();
            if (!sku) return;
            if (cb.checked) linkedSkuModalSelectedSkus.add(sku);
            else linkedSkuModalSelectedSkus.delete(sku);
            updateLinkedSkuSelectedSummary();
        });
        document.getElementById('sku-link-lmp-selected-skus')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sku-link-lmp-selected-remove');
            if (!btn) return;
            linkedSkuModalSelectedSkus.delete(String(btn.dataset.sku || '').trim());
            document.querySelectorAll('.sku-link-lmp-suggestion-cb').forEach(function (cb) {
                if (cb.value === btn.dataset.sku) cb.checked = false;
            });
            updateLinkedSkuSelectedSummary();
        });
        document.getElementById('sku-link-lmp-save-btn')?.addEventListener('click', function () {
            saveLinkedSkuFromModal();
        });

        // Initialize Tabulator
        table = new Tabulator("#reverb-table", {
            ajaxURL: "/reverb-data-json",
            ajaxSorting: false,
            ajaxResponse: function(url, params, response) {
                if (response && response.map_miss_summary) {
                    applyMapMissSummary(response.map_miss_summary);
                }
                if (response && Array.isArray(response.data)) {
                    allTableData = response.data;
                    if (window.ParentExpand) ParentExpand.captureDataset(response.data);
                    return response.data;
                }
                if (Array.isArray(response)) {
                    allTableData = response;
                    if (window.ParentExpand) ParentExpand.captureDataset(response);
                }
                return response;
            },
            layout: "fitDataStretch",
            rowHeight: 36,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{
                column: "RV L30",
                dir: "desc"
            }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "#fffef2";
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
                    visible: false
                },
                ParentExpand.columnDef(),
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" class="hover-thumb" style="width: 28px; height: 28px; object-fit: cover;">`;
                        }
                        return '';
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
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
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
                    field: "links_column",
                    frozen: true,
                    width: 55,
                    hozAlign: "center",
                    visible: true,
                    tooltip: "Double-click to add / edit links",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData['B Link'] || '';
                        const sellerLink = rowData['S Link'] || '';
                        
                        let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';
                        
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
                            html += '<span class="text-muted" style="font-size: 12px;">-</span>';
                        }
                        
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openReverbEditLinksModal(cell.getRow());
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
                    field: "RV Dil%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;
                        const dil = INV === 0 ? 0 : (OVL30 / INV) * 100;
                        const band = reverbDilColorBand(rowData);
                        const color = reverbDilColorHex(band);
                        if (INV === 0) {
                            return '<span style="color: #6c757d;" title="INV = 0">0%</span>';
                        }
                        return `<span style="color: ${color}; font-weight: 600;" title="Dil = OV L30 ÷ INV">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "RV L30",
                    field: "RV L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "RD Qty",
                    field: "reverb_daily_qty",
                    hozAlign: "center",
                    width: 72,
                    sorter: "number",
                    headerTooltip: "Σ quantity from reverb_daily_data (orders API) for this SKU"
                },
                {
                    title: "RD Σ(qty×subtotal)",
                    field: "reverb_daily_qty_x_subtotal",
                    hozAlign: "center",
                    width: 110,
                    sorter: "number",
                    formatter: "money",
                    formatterParams: { precision: 2, symbol: "$" },
                    headerTooltip: "Σ quantity × product_subtotal from reverb_daily_data"
                },
                {
                    title: "RD Σ(qty×amount)",
                    field: "reverb_daily_qty_x_amount",
                    hozAlign: "center",
                    width: 118,
                    sorter: "number",
                    formatter: "money",
                    formatterParams: { precision: 2, symbol: "$" },
                    headerTooltip: "Σ quantity × amount (order total field) from reverb_daily_data"
                },
                {
                    title: "R Stock",
                    field: "R Stock",
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
                    title: "Missing Ad",
                    field: "Missing_Ad",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        const bump = cell.getRow().getData().Bump;
                        const hasBump = bump !== null && bump !== undefined && String(bump).trim() !== '';
                        if (hasBump) {
                            return '<span class="status-circle green" title="Has Bump Bid"></span>';
                        }
                        return '<span class="status-circle red" title="Missing Ad"></span>';
                    },
                    headerSort: false
                },
                {
                    title: "Bump Bid",
                    field: "Bump",
                    headerTooltip: "Live Reverb bump bid from GET /listings/{id}/bump (current_bid).",
                    hozAlign: "center",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '<span class="text-muted">-</span>';
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "Recommended Bid",
                    field: "API_REC_BID",
                    headerTooltip: "Reverb recommended bump bid from the listing bump API. Green when it matches live Bump Bid.",
                    hozAlign: "center",
                    width: 110,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '<span class="text-muted">-</span>';
                        const rec = reverbParseBumpPct(value);
                        const live = reverbParseBumpPct(cell.getRow().getData().Bump);
                        const match = rec > 0 && Math.abs(rec - live) < 0.05;
                        const color = match ? '#198754' : '#dc3545';
                        return `<span style="font-weight: 600; color: ${color};">${value}</span>`;
                    }
                },
                {
                    title: "S Bump%",
                    field: "RE_BID",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    editor: "input",
                    editorParams: { placeholder: "e.g. 5%" },
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const shown = reverbFormatSBump(value);
                        if (!shown) return '<span class="text-muted">-</span>';
                        return `<span style="font-weight: 600;">${shown}</span>`;
                    }
                },
                reverbPushBumpColumnDef(),
                {
                    title: "Views",
                    field: "Views",
                    headerTooltip: "Bump impressions from Reverb GET /listings/{id}/bump (bump_v2_stats.impressions). Never-bumped listings = 0.",
                    hozAlign: "center",
                    width: 62,
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const sku = row['(Child) sku'] || '';
                        const isParent = !!(row.is_parent_summary || row.is_parent || (sku && String(sku).toUpperCase().indexOf('PARENT') !== -1));
                        const views = Math.round(parseFloat(cell.getValue()) || 0);
                        const dotBtn = (sku && !isParent)
                            ? `<button type="button" class="btn btn-sm p-0 view-sku-chart align-middle" data-sku="${escAttr(sku)}" data-metric="views" title="View Views chart" style="border: none; background: none; cursor: pointer; padding: 0 2px; line-height: 1; vertical-align: middle;"><span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #0000FF;"></span></button>`
                            : '';
                        return `${views.toLocaleString()} ${dotBtn}`.trim();
                    }
                },
                {
                    title: "CVR%",
                    field: "CVR",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Amazon formula: units ÷ sessions × 100 (RV L30 ÷ Views)
                        const rowData = cell.getRow().getData();
                        const l30 = parseFloat(rowData['RV L30']) || 0;
                        const views = parseFloat(rowData['Views']) || 0;

                        if (views === 0) {
                            return '<span style="color: #a00211; font-weight: 600;">0%</span>';
                        }

                        const cvr = (l30 / views) * 100;
                        let color = '';
                        if (cvr <= 4) color = '#a00211';
                        else if (cvr > 4 && cvr <= 7) color = '#ffc107';
                        else if (cvr > 7 && cvr <= 13) color = '#28a745';
                        else color = '#e83e8c';

                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(cvr)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || value.trim() === '') {
                            value = 'REQ';
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
                    headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings.",
                    editor: "input",
                    width: 70,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent_summary || d.is_parent) return false;
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary || rowData.is_parent) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        return '$' + std.toFixed(2);
                    }
                },
                {
                    title: "Push Std",
                    field: "push_std_prc",
                    hozAlign: "center",
                    vertAlign: "middle",
                    headerSort: false,
                    width: 55,
                    headerTooltip: "Push Std Prc to the live Reverb listing via API. Only SKUs whose Std changed since the last push are queued. Click the header to bulk selected (or visible) SKUs.",
                    titleFormatter: function() {
                        return '<button type="button" class="btn btn-sm p-0 reverb-push-std-header-btn" '
                            + 'title="Queue Push Std for selected SKUs whose Std changed since last push" '
                            + 'style="border:none;background:none;cursor:pointer;color:#000;'
                            + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                            + 'Push Std</button>';
                    },
                    headerClick: function(e) {
                        if (e.target.closest('.reverb-push-std-header-btn')) {
                            e.stopPropagation();
                            e.preventDefault();
                            if (typeof queueReverbPushStd === 'function') queueReverbPushStd();
                            return false;
                        }
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const sku = String(d['(Child) sku'] || d.sku || '').trim();
                        if (!sku || String(sku).toUpperCase().indexOf('PARENT') !== -1 || d.is_parent_summary) {
                            return '';
                        }
                        const std = parseFloat(d.STANDARD_PRICE);
                        if (!(std > 0)) {
                            return '<span style="color:#adb5bd;" title="Std Prc required">—</span>';
                        }
                        const status = String(d.PUSH_STD_PRC_STATUS || cell.getValue() || '');
                        const last = parseFloat(d.PUSH_STD_PRC_VALUE);
                        const lastOk = isFinite(last) && last > 0;
                        const needs = status === 'error' || !lastOk || last.toFixed(2) !== std.toFixed(2);
                        let icon = '<i class="fas fa-upload"></i>';
                        let color = '#FF9900';
                        let tip = 'Push Std $' + std.toFixed(2) + ' to Reverb';
                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                            color = '#ffc107';
                            tip = 'Pushing Std to Reverb…';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-xmark"></i>';
                            color = '#dc3545';
                            tip = 'Last Push Std failed — click to retry';
                        } else if (!needs) {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            tip = 'Already pushed $' + last.toFixed(2) + ' — click to push again';
                        } else if (lastOk) {
                            tip = 'Std changed $' + last.toFixed(2) + ' → $' + std.toFixed(2)
                                + ' — click to push to Reverb';
                        }
                        return '<button type="button" class="btn btn-sm p-0 reverb-push-std-btn" '
                            + 'data-sku="' + sku.replace(/"/g, '&quot;') + '" '
                            + 'data-price="' + std.toFixed(2) + '" '
                            + 'title="' + tip.replace(/"/g, '&quot;') + '" '
                            + 'style="border:none;background:none;cursor:pointer;color:' + color
                            + ';padding:0;line-height:1;vertical-align:middle;">'
                            + icon + '</button>';
                    },
                    cellClick: function(e, cell) {
                        const btn = e.target.closest('.reverb-push-std-btn');
                        if (!btn) return;
                        e.stopPropagation();
                        e.preventDefault();
                        if (btn.disabled) return false;
                        const d = cell.getRow().getData() || {};
                        if (String(d.PUSH_STD_PRC_STATUS || '') === 'processing') return false;
                        if (typeof queueReverbPushStd === 'function') {
                            const sku = String(d['(Child) sku'] || '').trim();
                            const selected = (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.size > 1
                                && selectedSkus.has(sku));
                            queueReverbPushStd(selected ? null : cell.getRow());
                        }
                        return false;
                    }
                },
                {
                    title: "Price",
                    field: "RV Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const amazonPrice = parseFloat(rowData['A Price']) || 0;
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        // Show red if RV Price is less than Amazon Price
                        if (amazonPrice > 0 && value < amazonPrice) {
                            return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        // Show green if RV Price is greater than Amazon Price
                        if (amazonPrice > 0 && value > amazonPrice) {
                            return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "A Prc",
                    field: "A Price",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (window.ParentExpand) {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, {
                                dataset: typeof allTableData !== 'undefined' ? allTableData : undefined
                            });
                            if (avgHtml !== null) return avgHtml;
                        }
                        const lmpPrice = cell.getValue();
                        const sku = rowData['(Child) sku'] || '';
                        const totalCompetitors = rowData.lmp_entries_total || 0;
                        const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                        const linkedSkusAttr = escAttr(JSON.stringify(linkedSkus));
                        const rvPrice = parseFloat(rowData['RV Price']) || 0;

                        if (!lmpPrice && totalCompetitors === 0) {
                            return `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #999; text-decoration: none; cursor: pointer; font-size: 12px;" title="Add competitors">N/A</a>`;
                        }

                        if (lmpPrice) {
                            const finalPrice = parseFloat(lmpPrice) || 0;
                            const priceColor = (rvPrice > 0 && finalPrice < rvPrice) ? '#dc3545' : '#28a745';
                            let html = `<span style="color: ${priceColor}; font-weight: 600;">$${finalPrice.toFixed(2)}</span>`;
                            if (totalCompetitors > 0) {
                                html += ` <a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                    title="View ${totalCompetitors} competitor${totalCompetitors === 1 ? '' : 's'}"
                                    style="color: #007bff; text-decoration: none; cursor: pointer; font-weight: 600;">(${totalCompetitors})</a>`;
                            }
                            return html;
                        }

                        return `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                            title="View ${totalCompetitors} competitor${totalCompetitors === 1 ? '' : 's'}"
                            style="color: #007bff; text-decoration: none; cursor: pointer; font-weight: 600;">(${totalCompetitors})</a>`;
                    },
                    width: 100
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
                    title: "ROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '';
                        const percent = parseFloat(value);
                        if (isNaN(percent)) return '';
                        // Same color bands as /amazon-tabulator-view GROI% / SNROI
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GROI%', percent)) || ('color:'+reverbRoiColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "GPFT%",
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
                    title: "PFT %",
                    field: "NPFT",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const ads = parseFloat(REVERB_CHANNEL_ADS_PCT) || 0;
                        return ((parseFloat(aRow.getData()['GPFT%'] || 0) - ads) - (parseFloat(bRow.getData()['GPFT%'] || 0) - ads));
                    },
                    formatter: function(cell) {
                        // Amazon-style: PFT% = GPFT% − Ads%
                        const raw = cell.getRow().getData()['GPFT%'];
                        if (raw === null || raw === undefined || raw === '') return '';
                        const gpft = parseFloat(raw);
                        if (isNaN(gpft)) return '';
                        const percent = gpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0);
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GPFT%', percent)) || ('color:'+reverbPftColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "NROI",
                    field: "NROI",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aNet = reverbComputeNetRoi(aRow.getData());
                        const bNet = reverbComputeNetRoi(bRow.getData());
                        return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                             - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                    },
                    formatter: function(cell) {
                        // Amazon-style: (gross PFT$ − Ads%×Price) / LP × 100
                        const percent = reverbComputeNetRoi(cell.getRow().getData());
                        if (percent === null || !isFinite(percent)) return '';
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GROI%', percent)) || ('color:'+reverbRoiColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Profit",
                    field: "Profit",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = value >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "Sales",
                    field: "Sales L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 80
                },
                {
                    title: "LP",
                    field: "LP_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                {
                    title: "Ship",
                    field: "Ship_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                 {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                ...(typeof reverbChannelPromoColumns === 'function' ? reverbChannelPromoColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: "SPRICE",
                    field: "SPRICE",
                    hozAlign: "center",
                    headerTooltip: "Suggested price. Red triangle when SPRICE > LMP.",
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        const lmp = parseFloat(rowData.lmp_price) || 0;
                        const overLmp = value > 0 && lmp > 0 && value > lmp;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';

                        const alertHtml = overLmp
                            ? `<i class="fas fa-exclamation-triangle sprice-lmp-alert" title="SPRICE $${value.toFixed(2)} &gt; LMP $${lmp.toFixed(2)}"></i>`
                            : '';
                        const priceColor = overLmp ? 'color:#dc3545;' : '';
                        
                        return `<span style="font-weight: 600; ${priceColor} ${bgColor} padding: 2px 6px; border-radius: 3px; display:inline-flex; align-items:center; justify-content:center;">$${value.toFixed(2)}${alertHtml}</span>`;
                    },
                    width: 96
                },
                {
                    title: "Sroi",
                    field: "SROI",
                    hozAlign: "center",
                    headerTooltip: "SGROI from SPRICE. 0 Sold Prc Rule Dil 0–10% → 40% SGROI (hover a cell for the row reason).",
                    sorter: function(a, b, aRow, bRow) {
                        const aVal = reverbComputeSroi(aRow.getData());
                        const bVal = reverbComputeSroi(bRow.getData());
                        return ((aVal == null || !isFinite(aVal)) ? 0 : aVal)
                             - ((bVal == null || !isFinite(bVal)) ? 0 : bVal);
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const percent = reverbComputeSroi(d);
                        if (percent === null || !isFinite(percent)) return '';
                        const tip = (typeof reverbZeroSoldPrcSroiTitle === 'function')
                            ? reverbZeroSoldPrcSroiTitle(d, percent)
                            : '';
                        const title = tip ? (' title="' + escapeHtmlAttr(tip) + '"') : '';
                        return `<span${title} style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GROI%', percent)) || ('color:'+reverbRoiColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SGPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aVal = reverbComputeSgpft(aRow.getData());
                        const bVal = reverbComputeSgpft(bRow.getData());
                        return ((aVal == null || !isFinite(aVal)) ? 0 : aVal)
                             - ((bVal == null || !isFinite(bVal)) ? 0 : bVal);
                    },
                    formatter: function(cell) {
                        // Same calculate-data as GPFT%, using SPRICE
                        const percent = reverbComputeSgpft(cell.getRow().getData());
                        if (percent === null || !isFinite(percent)) return '';
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                    },
                    width: 50
                },
                {
                    title: "SNPFT",
                    field: "SNPFT",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aVal = reverbComputeSnpft(aRow.getData());
                        const bVal = reverbComputeSnpft(bRow.getData());
                        return ((aVal == null || !isFinite(aVal)) ? 0 : aVal)
                             - ((bVal == null || !isFinite(bVal)) ? 0 : bVal);
                    },
                    formatter: function(cell) {
                        // Same calculate-data as PFT% / NPFT: SGPFT − Ads%
                        const percent = reverbComputeSnpft(cell.getRow().getData());
                        if (percent === null || !isFinite(percent)) return '';
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GPFT%', percent)) || ('color:'+reverbPftColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNROI",
                    field: "SNROI",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aNet = reverbComputeNetSroi(aRow.getData());
                        const bNet = reverbComputeNetSroi(bRow.getData());
                        return ((aNet == null || !isFinite(aNet)) ? 0 : aNet)
                             - ((bNet == null || !isFinite(bNet)) ? 0 : bNet);
                    },
                    formatter: function(cell) {
                        // Amazon-style: (gross $ − Ads%×SPRICE) / LP × 100
                        const percent = reverbComputeNetSroi(cell.getRow().getData());
                        if (percent === null || !isFinite(percent)) return '';
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GROI%', percent)) || ('color:'+reverbRoiColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Push",
                    field: "push_price",
                    hozAlign: "center",
                    vertAlign: "middle",
                    headerSort: false,
                    width: 55,
                    headerTooltip: "Push SPRICE to Reverb. Blank when already pushed. Click header to push all visible rows that still need it.",
                    titleFormatter: function() {
                        return '<button type="button" class="btn btn-sm p-0 reverb-push-sprice-header-btn" '
                            + 'title="Push SPRICE for all visible rows that are not already pushed" '
                            + 'style="border:none;background:none;cursor:pointer;color:#000;'
                            + 'font-weight:700;font-size:11px;line-height:1.15;padding:0;">'
                            + 'Push</button>';
                    },
                    headerClick: function(e) {
                        if (e.target.closest('.reverb-push-sprice-header-btn')) {
                            e.stopPropagation();
                            e.preventDefault();
                            if (typeof queueReverbPushSprice === 'function') queueReverbPushSprice();
                            return false;
                        }
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData() || {};
                        const sku = reverbSpricePushSku(rowData);
                        if (!reverbSpricePushIsChild(rowData)) return '';
                        const sprice = parseFloat(rowData.SPRICE || 0);
                        if (!(sprice > 0)) return '';
                        const status = String(rowData.SPRICE_STATUS || '');
                        const pushedValue = rowData.SPRICE_PUSHED_VALUE;
                        const updatedAt = rowData.SPRICE_STATUS_UPDATED_AT;
                        const pushedBy = rowData.SPRICE_PUSHED_BY;

                        if (status === 'processing') {
                            return '<button type="button" class="btn btn-sm p-0 reverb-push-price-btn" disabled '
                                + 'title="Price pushing in progress…" '
                                + 'style="border:none;background:none;color:#ffc107;padding:0;cursor:default;">'
                                + '<i class="fas fa-spinner fa-spin"></i></button>';
                        }
                        if (status === 'error') {
                            const tip = 'Last push failed — click to retry $' + sprice.toFixed(2);
                            return '<button type="button" class="btn btn-sm p-0 reverb-push-price-btn" '
                                + 'data-sku="' + sku.replace(/"/g, '&quot;') + '" '
                                + 'data-price="' + sprice + '" data-status="error" '
                                + 'title="' + tip.replace(/"/g, '&quot;') + '" '
                                + 'style="border:none;background:none;color:#dc3545;padding:0;cursor:pointer;">'
                                + '<i class="fa-solid fa-xmark"></i></button>';
                        }
                        if (!reverbSpriceNeedsPush(rowData)) {
                            return '';
                        }

                        let titleText = 'Push $' + sprice.toFixed(2) + ' to Reverb';
                        if (pushedValue !== null && pushedValue !== undefined && parseFloat(pushedValue) > 0) {
                            titleText += ' | Last: $' + parseFloat(pushedValue).toFixed(2);
                        }
                        if (updatedAt) titleText += ' | ' + updatedAt;
                        if (pushedBy) titleText += ' | by ' + pushedBy;

                        return '<button type="button" class="btn btn-sm p-0 reverb-push-price-btn" '
                            + 'data-sku="' + sku.replace(/"/g, '&quot;') + '" '
                            + 'data-price="' + sprice + '" data-status="' + (status || '') + '" '
                            + 'title="' + titleText.replace(/"/g, '&quot;') + '" '
                            + 'style="border:none;background:none;color:#fd7e14;padding:0;cursor:pointer;">'
                            + '<i class="fas fa-upload"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        const btn = e.target.closest('.reverb-push-price-btn');
                        if (!btn || btn.disabled) return;
                        e.stopPropagation();
                        e.preventDefault();
                        const d = cell.getRow().getData() || {};
                        if (String(d.SPRICE_STATUS || '') === 'processing') return false;
                        if (typeof queueReverbPushSprice === 'function') {
                            queueReverbPushSprice(cell.getRow());
                        }
                        return false;
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
                    if (typeof updateCalcValues === 'function') updateCalcValues();
                },
                onCollapse: () => {
                    if (typeof applyFilters === 'function') applyFilters();
                },
            });
            ParentExpand.bind();
        }

        // SKU Search functionality
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const $rowEl = $cell.closest('.tabulator-row');
            const row = table.getRow($rowEl[0]); // Pass DOM element, not jQuery object
            const rowData = row.getData();
            const sku = rowData['(Child) sku'];
            const newValue = $(this).val();
            
            $.ajax({
                url: '{{ url("/reverb-update-listed-live") }}',
                method: 'POST',
                data: {
                    sku: sku,
                    nr_req: newValue,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`${sku}: Status updated to ${newValue}`, 'success');
                    row.update({ nr_req: newValue });
                },
                error: function(xhr) {
                    showToast(`Failed to update status for ${sku}`, 'error');
                }
            });
        });

        // ---- Edit B/S Links (double-click on Links cell) ----
        let reverbEditLinksRow = null;
        function openReverbEditLinksModal(row) {
            if (!row) return;
            reverbEditLinksRow = row;
            const d = row.getData();
            $('#reverbEditLinksSku').val(d['(Child) sku']);
            $('#reverbEditLinksSkuDisplay').text(d['(Child) sku']);
            $('#reverbEditSellerLink').val(d['S Link'] || '');
            $('#reverbEditBuyerLink').val(d['B Link'] || '');
            $('#reverbEditLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('reverbEditLinksModal')).show();
        }

        $(document).on('click', '#reverbSaveLinksBtn', function() {
            const sku = $('#reverbEditLinksSku').val();
            const sellerLink = $('#reverbEditSellerLink').val().trim();
            const buyerLink = $('#reverbEditBuyerLink').val().trim();
            const $err = $('#reverbEditLinksError');
            $err.hide().text('');
            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ url("/reverb-save-links") }}',
                method: 'POST',
                data: { sku: sku, seller_link: sellerLink, buyer_link: buyerLink, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (reverbEditLinksRow) {
                        reverbEditLinksRow.update({ 'S Link': res.seller_link || '', 'B Link': res.buyer_link || '' })
                            .then(function() { reverbEditLinksRow.reformat(); })
                            .catch(function() { reverbEditLinksRow.reformat(); });
                    }
                    showToast(`${sku}: links saved`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('reverbEditLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        // SPRICE cell edited - save to database
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const data = row.getData();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                const sku = data['(Child) sku'] || data.sku || data.SKU;
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
                        applyReverbStandardPriceToLinkedRows(sku, saved, response.applied_skus);
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

            if (field === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                row.update(Object.assign({
                    has_custom_sprice: true
                }, reverbSpriceMetricPatch(newSprice, rowData)));
                
                // Save to database
                saveSpriceWithRetry(sku, newSprice, row);
            }
            if (cell.getField() === 'RE_BID') {
                const row = cell.getRow();
                const sku = row.getData()['(Child) sku'];
                const value = cell.getValue();
                const recommendedBid = value === null || value === undefined ? '' : String(value).trim();
                saveRecommendedBid(sku, recommendedBid);
            }
        });

        // Copy SKU button handler
        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        $('#reverb-zero-sold-prc-rule-btn').on('click', function(e) {
            e.preventDefault();
            saveAndApplyReverbZeroSoldPrc({ fromToolbar: true });
        });
        $('#reverb-zero-sold-prc-apply-now-btn').on('click', function(e) {
            e.preventDefault();
            saveAndApplyReverbZeroSoldPrc({ fromToolbar: true });
        });
        $('#reverb-zero-sold-prc-rules-modal-btn').on('click', function(e) {
            e.preventDefault();
            loadReverbZeroSoldPrcRules();
            $('#reverbZeroSoldPrcModal').modal('show');
        });
        $('#reverb-zero-sold-prc-apply-btn').on('click', function() {
            saveAndApplyReverbZeroSoldPrc();
        });

        $('#reverb-dil-vs-s-bump-btn').on('click', function(e) {
            e.preventDefault();
            loadReverbDilSBumpRules();
            $('#reverbDilVsSBumpModal').modal('show');
        });
        $('#reverb-dil-s-bump-apply-btn').on('click', function() {
            saveAndApplyReverbSBump();
        });
        $('#reverb-apply-s-bump-btn').on('click', function(e) {
            e.preventDefault();
            saveAndApplyReverbSBump();
        });

        $(document).on('click', '.view-sku-chart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const el = e.target.closest ? e.target.closest('.view-sku-chart') : this;
            const sku = el.getAttribute('data-sku') || $(el).data('sku');
            if (!sku) return;
            currentSkuChartMetric = el.getAttribute('data-metric') || 'views';
            currentSku = sku;
            $('#modalSkuName').text(sku);
            const metricLabels = { price: 'Price', views: 'Views', cvr: 'CVR%' };
            $('#skuChartRefLabel').text(metricLabels[currentSkuChartMetric] || 'Views');
            $('#skuChartModalSuffix').text('(Rolling L30)');
            $('#sku-chart-days-filter').val('30');
            $('#chart-no-data-message').hide();
            loadSkuMetricsData(sku, 30, currentSkuChartMetric);
            $('#skuMetricsModal').modal('show');
        });

        // Push one SKU SPRICE to Reverb (Amazon-style icon click — no confirm)
        function pushReverbPriceForRow(row, sku, price) {
            return new Promise(function(resolve) {
                row.update({ SPRICE_STATUS: 'processing' })
                    .then(function() { return row.reformat(); })
                    .catch(function() { try { row.reformat(); } catch (e) {} });

                $.ajax({
                    url: '/cvr-master-push-price',
                    method: 'POST',
                    data: {
                        sku: sku,
                        price: price,
                        marketplace: 'reverb',
                        _token: csrfToken()
                    },
                    success: function(response) {
                        if (response && response.success) {
                            row.update({
                                SPRICE_STATUS: 'pushed',
                                SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString(),
                                SPRICE_PUSHED_VALUE: price
                            }).then(function() { row.reformat(); }).catch(function() { row.reformat(); });
                            resolve({ ok: true, sku: sku, message: response.message || 'Pushed' });
                        } else {
                            row.update({
                                SPRICE_STATUS: 'error',
                                SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString()
                            }).then(function() { row.reformat(); }).catch(function() { row.reformat(); });
                            resolve({
                                ok: false,
                                sku: sku,
                                message: (response && response.message) ? response.message : 'Failed'
                            });
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Failed to push price to Reverb';
                        row.update({
                            SPRICE_STATUS: 'error',
                            SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString()
                        }).then(function() { row.reformat(); }).catch(function() { row.reformat(); });
                        resolve({ ok: false, sku: sku, message: msg });
                    }
                });
            });
        }

        function collectReverbSpricePushTargets(singleRow) {
            if (singleRow && typeof singleRow.getData === 'function') {
                const d = singleRow.getData() || {};
                return reverbSpricePushIsChild(d) ? [{ row: singleRow, d: d }] : [];
            }
            if (typeof collectChPromoVisibleRows === 'function') {
                return collectChPromoVisibleRows();
            }
            if (!table) return [];
            const out = [];
            (table.getRows('active') || []).forEach(function(row) {
                const d = row.getData() || {};
                if (reverbSpricePushIsChild(d)) out.push({ row: row, d: d });
            });
            return out;
        }

        async function queueReverbPushSprice(singleRow) {
            const all = collectReverbSpricePushTargets(singleRow || null);
            const forceOne = !!singleRow;
            const jobs = [];
            all.forEach(function(t) {
                const d = (t.row && t.row.getData()) || t.d || {};
                const sku = reverbSpricePushSku(d);
                const price = parseFloat(d.SPRICE || 0);
                if (!sku || !(price > 0)) return;
                if (!forceOne && !reverbSpriceNeedsPush(d)) return;
                if (String(d.SPRICE_STATUS || '') === 'processing') return;
                jobs.push({ row: t.row, sku: sku, price: price });
            });
            if (!jobs.length) {
                showToast(singleRow
                    ? 'Set a valid SPRICE (> 0) before pushing'
                    : 'No visible rows need a price push (already pushed or no SPRICE)', 'info');
                return;
            }
            if (!singleRow && !confirm('Push ' + jobs.length + ' price(s) to Reverb?')) {
                return;
            }
            let okCount = 0;
            let failCount = 0;
            const concurrency = 5;
            let idx = 0;
            async function runNext() {
                if (idx >= jobs.length) return;
                const job = jobs[idx++];
                const result = await pushReverbPriceForRow(job.row, job.sku, job.price);
                if (result.ok) okCount++; else failCount++;
                await runNext();
            }
            await Promise.all(Array.from({ length: Math.min(concurrency, jobs.length) }, function() { return runNext(); }));
            if (singleRow) {
                const resultOk = failCount === 0;
                showToast(resultOk
                    ? (jobs[0] && ('Price pushed to Reverb for ' + jobs[0].sku))
                    : ('Failed to push ' + (jobs[0] && jobs[0].sku)), resultOk ? 'success' : 'error');
            } else if (failCount === 0) {
                showToast('Pushed ' + okCount + ' price(s) to Reverb', 'success');
            } else {
                showToast('Pushed ' + okCount + ', failed ' + failCount, failCount === jobs.length ? 'error' : 'warning');
            }
            if (typeof updateSummary === 'function') updateSummary();
        }
        window.queueReverbPushSprice = queueReverbPushSprice;

        // Bulk Push Prices for selected SKUs (Amazon-style — toolbar + Bulk Mode bar)
        async function executeBulkPushReverb($triggerBtn) {
            if (selectedSkus.size === 0) {
                showToast('Select at least one SKU first (turn on Bulk Mode)', 'error');
                return;
            }

            const jobs = [];
            selectedSkus.forEach(function(sku) {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const price = parseFloat(row.getData().SPRICE || 0);
                if (!price || price <= 0) return;
                jobs.push({ row: row, sku: sku, price: price });
            });

            if (jobs.length === 0) {
                showToast('No selected SKUs have SPRICE > 0 to push', 'warning');
                return;
            }

            if (!confirm('Push ' + jobs.length + ' price(s) to Reverb?')) {
                return;
            }

            const $btn = ($triggerBtn && $triggerBtn.length) ? $triggerBtn : $('#bulk-push-reverb-btn');
            const $dropdownBtn = $('#reverbBulkActionsDropdown');
            const originalBtnHtml = $btn.html();
            const originalDropHtml = $dropdownBtn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
            $dropdownBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
            $('#execute-bulk-push-reverb').prop('disabled', true);

            let okCount = 0;
            let failCount = 0;
            const concurrency = 5;
            let idx = 0;
            async function runNext() {
                if (idx >= jobs.length) return;
                const job = jobs[idx++];
                const result = await pushReverbPriceForRow(job.row, job.sku, job.price);
                if (result.ok) okCount++; else failCount++;
                await runNext();
            }
            await Promise.all(Array.from({ length: Math.min(concurrency, jobs.length) }, function() { return runNext(); }));

            $btn.prop('disabled', false).html(originalBtnHtml || '<i class="fas fa-upload"></i> Bulk Push Prices');
            $dropdownBtn.prop('disabled', false).html(originalDropHtml || '<i class="fas fa-upload"></i> Bulk Push');
            $('#execute-bulk-push-reverb').prop('disabled', false);

            if (failCount === 0) {
                showToast('Pushed ' + okCount + ' price(s) to Reverb', 'success');
            } else {
                showToast('Pushed ' + okCount + ', failed ' + failCount, failCount === jobs.length ? 'error' : 'warning');
            }
            updateSummary();
        }

        $('#bulk-push-reverb-btn').on('click', function() {
            executeBulkPushReverb($(this));
        });
        $(document).on('click', '#execute-bulk-push-reverb', function(e) {
            e.preventDefault();
            e.stopPropagation();
            executeBulkPushReverb($(this));
        });

        // Apply filters
        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function() {
                    applyFilters();
                });
                return;
            }
            const inventoryFilter = $('#inventory-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

            // Clear all filters first
            table.clearFilter();

            // Inventory filter
            if (inventoryFilter === 'zero') {
                table.addFilter("INV", "=", 0);
            } else if (inventoryFilter === 'more') {
                table.addFilter("INV", ">", 0);
            }

            // NRL filter
            if (nrlFilter === 'REQ') {
                table.addFilter("nr_req", "=", "REQ");
            } else if (nrlFilter === 'NR') {
                table.addFilter("nr_req", "=", "NR");
            }

            // Reverb Stock filter
            const reverbStockFilter = $('#reverb-stock-filter').val();
            if (reverbStockFilter === 'zero') {
                table.addFilter("R Stock", "=", 0);
            } else if (reverbStockFilter === 'more') {
                table.addFilter("R Stock", ">", 0);
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                if (gpftFilter === 'negative') {
                    table.addFilter("GPFT%", "<", 0);
                } else if (gpftFilter === '50plus') {
                    table.addFilter("GPFT%", ">=", 50);
                } else {
                    const [min, max] = gpftFilter.split('-').map(Number);
                    table.addFilter("GPFT%", ">=", min);
                    table.addFilter("GPFT%", "<", max);
                }
            }

            // ROI filter
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

            // CVR filter — Amazon formula: RV L30 ÷ Views × 100
            const cvrFilter = $('#cvr-filter').val();
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const wl30 = parseFloat(data['RV L30']) || 0;
                    const views = parseFloat(data['Views']) || 0;
                    const cvrPercent = views > 0 ? (wl30 / views) * 100 : 0;

                    if (cvrFilter === '0-0') return cvrPercent === 0;
                    if (cvrFilter === '0-3') return cvrPercent > 0 && cvrPercent <= 3;
                    if (cvrFilter === '3-7') return cvrPercent > 3 && cvrPercent <= 7;
                    if (cvrFilter === '7-13') return cvrPercent > 7 && cvrPercent <= 13;
                    if (cvrFilter === '13plus') return cvrPercent > 13;
                    return true;
                });
            }

            // DIL filter — Red / Green / Pink
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    return reverbDilColorBand(data) === dilFilter;
                });
            }

            // Sold filter (RV L30) — same as Amazon Sold filter on A_L30.
            // Badge clicks and ?badge=zero_sold|more_sold URL deep-link both write into
            // this dropdown, so there is exactly one source of truth.
            const soldFilter = $('#sold-filter').val();
            if (soldFilter !== 'all') {
                table.addFilter(function(data) {
                    const rvL30 = parseFloat(data['RV L30']) || 0;
                    if (soldFilter === 'zero') return rvL30 === 0;
                    if (soldFilter === 'sold') return rvL30 > 0;
                    return true;
                });
            }

            // Status filter — same as Amazon SPRICE push status
            const statusFilter = $('#status-filter').val();
            if (statusFilter !== 'all') {
                table.addFilter(function(data) {
                    const status = data.SPRICE_STATUS || null;
                    if (statusFilter === 'not-pushed') {
                        return status !== 'pushed';
                    }
                    if (statusFilter === 'pushed') return status === 'pushed';
                    if (statusFilter === 'applied') return status === 'applied';
                    if (statusFilter === 'error') return status === 'error';
                    return true;
                });
            }

            // < Amz filter - show prices less than Amazon price
            if (lessAmzFilterActive) {
                table.addFilter(function(data) {
                    const rvPrice = parseFloat(data['RV Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && rvPrice > 0 && rvPrice < amazonPrice;
                });
            }

            // > Amz filter - show prices greater than Amazon price
            if (moreAmzFilterActive) {
                table.addFilter(function(data) {
                    const rvPrice = parseFloat(data['RV Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && rvPrice > 0 && rvPrice > amazonPrice;
                });
            }

            // Missing filter - show SKUs missing in Reverb (REQ items with INV > 0 only)
            if (missingFilterActive) {
                table.addFilter(function(data) {
                    const missing = data['Missing'] || '';
                    const inv = parseFloat(data['INV']) || 0;
                    const nrReq = data['nr_req'] || 'REQ';
                    return missing === 'M' && nrReq === 'REQ' && inv > 0;
                });
            }

            // Map filter — listed SKUs with INV matched to R Stock (|INV − R Stock| ≤ 3)
            if (mapFilterActive) {
                table.addFilter(function(data) {
                    const mapValue = data['MAP'] || '';
                    const inv = parseFloat(data['INV']) || 0;
                    const nrReq = data['nr_req'] || 'REQ';
                    const isMissing = (data['Missing'] || '') === 'M';
                    return mapValue === 'Map' && nrReq === 'REQ' && inv > 0 && !isMissing;
                });
            }

            // N Map filter - show SKUs where stocks don't match (REQ items with INV > 0 and NOT Missing)
            if (invRStockFilterActive) {
                table.addFilter(function(data) {
                    const mapValue = data['MAP'] || '';
                    const inv = parseFloat(data['INV']) || 0;
                    const nrReq = data['nr_req'] || 'REQ';
                    const isMissing = (data['Missing'] || '') === 'M';
                    return mapValue.includes('N Map|') && nrReq === 'REQ' && inv > 0 && !isMissing;
                });
            }

            updateSummary();
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #reverb-stock-filter, #sold-filter, #status-filter').on('change', function() {
            applyFilters();
        });

        /** Full reverb_daily_data table totals for Sales/Orders badges (Ads% stays SSR like Amazon). */
        function loadReverbDailyTotalsBadges() {
            $.getJSON(REVERB_DAILY_TOTALS_URL)
                .done(function(d) {
                    if (!d || d.error) {
                        return;
                    }
                    const totalSales = parseFloat(d.sum_quantity_x_amount) || 0;
                    $('#rd-sum-qty-amount-badge').text(
                        'Sales: $' + Math.round(totalSales).toLocaleString()
                    );
                    $('#rd-daily-overview-badge').text('Orders: ' + (d.sum_quantity || 0));
                    // Ads% is channel-master SSR (REVERB_CHANNEL_ADS_PCT) — same pattern as Amazon.
                    // Keep badge in sync; do not overwrite with a different live recomputation.
                    reverbAdsPct = parseFloat(REVERB_CHANNEL_ADS_PCT) || 0;
                    $('#rd-ads-percent-badge').text('Ads: ' + reverbAdsPct.toFixed(1) + '%');
                    updateSummary();
                })
                .fail(function(xhr) {
                    console.warn('reverb-daily-data-totals-json failed', xhr && xhr.status);
                });
        }

        // Full table rows (ignore Tabulator filters — used when server summary unavailable)
        function getSummaryRows() {
            if (!table) return [];
            const rows = table.getRows();
            const data = (rows && rows.length)
                ? rows.map(r => r.getData())
                : (table.getData() || []);
            return data.filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
        }

        // Filtered rows for GPFT / sold / Amz badges
        function getFilteredSummaryRows() {
            if (!table) return [];
            const rows = table.getRows('active');
            const data = (rows && rows.length)
                ? rows.map(r => r.getData())
                : (table.getData('active') || []);
            return data.filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
        }

        // Server counts for Missing L / Map / N Map (matches all-marketplace-master)
        function applyMapMissSummary(summary) {
            if (!summary) return;
            $('#missing-count-badge').text('M L: ' + (parseInt(summary.miss, 10) || 0).toLocaleString());
            $('#map-count-badge').text('Map: ' + (parseInt(summary.map, 10) || 0).toLocaleString());
            $('#inv-r-stock-badge').text('N Map: ' + (parseInt(summary.nmap, 10) || 0).toLocaleString());
        }

        // Update summary badges
        function updateSummary() {
            const data = getFilteredSummaryRows();

            let totalGpft = 0, totalRoi = 0;
            let zeroSoldCount = 0, moreSoldCount = 0;
            let lessAmzCount = 0, moreAmzCount = 0;
            let totalRdQty = 0;
            let totalRvL30 = 0;
            let totalViewsRaw = 0;
            // Sold-quantity-weighted totals (same method as /temu-decrease, using normal ship)
            let totalRevenueQtyPrice = 0; // Σ(sold_qty × RV Price)
            let totalProfitLive = 0;      // Σ(sold_qty × (RV Price × take% − LP − Ship))
            let totalLpSold = 0;          // Σ(sold_qty × LP)  → GROI denominator

            data.forEach(row => {
                totalGpft += parseFloat(row['GPFT%']) || 0;
                totalRoi += parseFloat(row['ROI%']) || 0;
                totalRvL30 += parseFloat(row['RV L30']) || 0;
                totalViewsRaw += parseFloat(row['Views']) || 0;

                const rdQty = parseInt(row.reverb_daily_qty, 10) || 0;
                const rvL30 = parseFloat(row['RV L30']) || 0;
                const lp = parseFloat(row['LP_productmaster']) || 0;
                const ship = parseFloat(row['Ship_productmaster']) || 0; // normal ship
                const rvPrice = parseFloat(row['RV Price']) || 0;
                const pct = parseFloat(row.percentage);
                const takeRate = !isNaN(pct) && pct > 0 && pct <= 1 ? pct : 0.85;

                totalRdQty += rdQty;

                // Weighted profit/sales use RV L30 (Amazon uses A_L30)
                if (rvL30 > 0 && rvPrice > 0) {
                    totalProfitLive += rvL30 * (rvPrice * takeRate - lp - ship);
                    totalRevenueQtyPrice += rvL30 * rvPrice;
                    totalLpSold += rvL30 * lp;
                }

                // Sold badges count by RV L30 (matches Sold filter + Amazon A_L30)
                if (rvL30 === 0) {
                    zeroSoldCount++;
                } else {
                    moreSoldCount++;
                }
                
                // Compare RV Price with Amazon Price (must match filter logic exactly)
                const amzPrice = parseFloat(row['A Price']) || 0;
                
                // Count for < Amz
                if (amzPrice > 0 && rvPrice > 0 && rvPrice < amzPrice) {
                    lessAmzCount++;
                }
                
                // Count for > Amz
                if (amzPrice > 0 && rvPrice > 0 && rvPrice > amzPrice) {
                    moreAmzCount++;
                }
            });

            const avgGpftListing = data.length > 0 ? totalGpft / data.length : 0;
            const avgRoiListing = data.length > 0 ? totalRoi / data.length : 0;

            // GPFT% = Total Profit ÷ Total Revenue (weighted); fallback to simple avg GPFT%
            const gpftPct = totalRevenueQtyPrice > 0
                ? (totalProfitLive / totalRevenueQtyPrice) * 100
                : avgGpftListing;
            // GROI% = Total Profit ÷ Total LP (weighted); fallback to simple avg ROI%
            const groiPct = totalLpSold > 0
                ? (totalProfitLive / totalLpSold) * 100
                : avgRoiListing;

            $('#gpft-list-badge').text(`GPFT: ${Math.round(gpftPct)}%`);
            $('#groi-badge').text(`GROI: ${Math.round(groiPct)}%`);
            // Amazon-style: PFT = GPFT − Ads%; NROI = (PFT$ − Ads%×Sales) / COGS × 100
            const adsPct = parseFloat(REVERB_CHANNEL_ADS_PCT) || 0;
            const pftPct = gpftPct - adsPct;
            const adSpendEst = (adsPct / 100) * totalRevenueQtyPrice;
            const nroiPct = totalLpSold > 0
                ? ((totalProfitLive - adSpendEst) / totalLpSold) * 100
                : (groiPct - adsPct);
            $('#rd-ads-percent-badge').text('Ads: ' + adsPct.toFixed(1) + '%');
            $('#npft-badge').text(`PFT: ${Math.round(pftPct)}%`);
            $('#nroi-badge').text(`NROI: ${Math.round(nroiPct)}%`);
            // Amazon formula: Σ units ÷ Σ views × 100 (no Views÷10)
            const overallCvr = totalViewsRaw > 0 ? (totalRvL30 / totalViewsRaw) * 100 : 0;
            const avgViews = data.length > 0 ? totalViewsRaw / data.length : 0;
            $('#total-views-badge').text(`Views: ${Math.round(totalViewsRaw).toLocaleString()}`);
            $('#avg-views-badge').text(`Avg Views: ${Math.round(avgViews).toLocaleString()}`);
            $('#avg-cvr-badge').text(`CVR: ${overallCvr.toFixed(1)}%`);
            $('#rd-qty-sum-badge').text(`RD Qty: ${totalRdQty.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            $('#more-sold-count-badge').text(`> 0 Sold: ${moreSoldCount}`);
            $('#less-amz-badge').text(`< Amz: ${lessAmzCount}`);
            $('#more-amz-badge').text(`> Amz: ${moreAmzCount}`);
        }

        function csrfToken() {
            return ($('meta[name="csrf-token"]').attr('content'))
                || (document.querySelector('meta[name="csrf-token"]') || {}).content
                || '';
        }

        // ==================== Apply Std Price / Push Std queue ====================
        const REVERB_PUSH_STD_QUEUE_URL = '/reverb-push-std';
        let reverbPushStdPollTimer = null;
        let reverbPushStdLastTasks = [];

        function reverbPushStdSku(d) {
            return String((d && (d['(Child) sku'] || d.sku)) || '').trim();
        }
        function reverbPushStdSkuKey(sku) {
            return String(sku || '').trim().toUpperCase();
        }
        function reverbPushStdCurrent(d) {
            const n = parseFloat(d && d.STANDARD_PRICE);
            return (isFinite(n) && n > 0) ? +n.toFixed(2) : 0;
        }
        function reverbPushStdLast(d) {
            const n = parseFloat(d && d.PUSH_STD_PRC_VALUE);
            return (isFinite(n) && n > 0) ? +n.toFixed(2) : 0;
        }
        function reverbPushStdNeedsPush(d) {
            const std = reverbPushStdCurrent(d);
            if (!(std > 0)) return false;
            if (String((d && d.PUSH_STD_PRC_STATUS) || '') === 'error') return true;
            const last = reverbPushStdLast(d);
            if (!(last > 0)) return true;
            return last.toFixed(2) !== std.toFixed(2);
        }
        function reverbPushStdIsChild(d) {
            const sku = reverbPushStdSku(d);
            return !!(d && !d.is_parent_summary && sku && sku.toUpperCase().indexOf('PARENT') === -1);
        }
        function reverbEachTableRow(fn) {
            if (typeof chPromoEachTableRow === 'function') {
                chPromoEachTableRow(fn);
                return;
            }
            if (!table || typeof fn !== 'function') return;
            let rows = [];
            try { rows = table.getRows('all') || []; } catch (e) { rows = []; }
            if (!rows.length) {
                try { rows = table.getRows() || []; } catch (e) { rows = []; }
            }
            rows.forEach(function(row) {
                try { fn(row, row.getData() || {}); } catch (e) { /* ignore */ }
            });
        }
        function reverbSyncRowInDataset(sku, patch) {
            const key = reverbPushStdSkuKey(sku);
            if (!key || !patch) return;
            if (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) {
                allTableData.forEach(function(row) {
                    if (reverbPushStdSkuKey(reverbPushStdSku(row)) === key) {
                        Object.assign(row, patch);
                    }
                });
            }
        }
        function reverbPaintPushStdIcon(row) {
            if (!row) return;
            try {
                const cell = row.getCell && row.getCell('push_std_prc');
                if (cell && typeof cell.reformat === 'function') cell.reformat();
            } catch (e) { /* ignore */ }
            try {
                const d = row.getData() || {};
                const el = (row.getElement && row.getElement()) || null;
                const btn = el && el.querySelector && el.querySelector('.reverb-push-std-btn');
                if (!btn) return;
                const status = String(d.PUSH_STD_PRC_STATUS || d.push_std_prc || '');
                const std = reverbPushStdCurrent(d);
                const last = reverbPushStdLast(d);
                if (status === 'processing') {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                    btn.style.color = '#ffc107';
                    btn.title = 'Pushing Std to Reverb…';
                } else if (status === 'error') {
                    btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    btn.style.color = '#dc3545';
                    btn.title = 'Last Push Std failed — click to retry';
                } else if (status === 'pushed' && last > 0 && std > 0 && last.toFixed(2) === std.toFixed(2)) {
                    btn.innerHTML = '<i class="fa-solid fa-check-double"></i>';
                    btn.style.color = '#28a745';
                    btn.title = 'Already pushed $' + last.toFixed(2);
                }
            } catch (e) { /* ignore */ }
        }
        function reverbPushStdUpdateRow(row, patch) {
            if (!row || !patch) return;
            const sku = reverbPushStdSku(row.getData() || {});
            reverbSyncRowInDataset(sku, patch);
            const paint = function() { reverbPaintPushStdIcon(row); };
            try {
                const p = row.update(patch);
                if (p && typeof p.then === 'function') p.then(paint).catch(paint);
                else paint();
            } catch (e) {
                paint();
            }
        }
        function reverbPushStdRefreshCell(row) {
            reverbPaintPushStdIcon(row);
        }
        function reverbPushStdCollectTargets(singleRow) {
            if (singleRow) {
                const d = singleRow.getData() || {};
                return reverbPushStdIsChild(d) ? [{ row: singleRow, d: d }] : [];
            }
            if (typeof collectChPromoSelectedRows === 'function') {
                const selected = collectChPromoSelectedRows();
                if (selected.length) return selected;
            }
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.size) {
                const keys = new Set();
                selectedSkus.forEach(function(s) { keys.add(reverbPushStdSkuKey(s)); });
                return (table.getRows() || []).filter(function(row) {
                    const d = row.getData() || {};
                    return reverbPushStdIsChild(d) && keys.has(reverbPushStdSkuKey(reverbPushStdSku(d)));
                }).map(function(row) { return { row: row, d: row.getData() }; });
            }
            if (typeof collectChPromoVisibleRows === 'function') {
                return collectChPromoVisibleRows();
            }
            return (table.getRows('active') || []).filter(function(row) {
                return reverbPushStdIsChild(row.getData());
            }).map(function(row) { return { row: row, d: row.getData() }; });
        }
        function applyReverbPushStdTaskStatuses(tasks) {
            if (!table || !Array.isArray(tasks)) return;
            reverbPushStdLastTasks = tasks;
            const bySku = {};
            tasks.forEach(function(t) {
                const k = reverbPushStdSkuKey(t && t.sku);
                if (k) bySku[k] = t;
            });
            reverbEachTableRow(function(row, d) {
                const t = bySku[reverbPushStdSkuKey(reverbPushStdSku(d))];
                if (!t) return;
                const st = String(t.status || '');
                const std = parseFloat(t.std);
                const patch = {};
                if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    patch.PUSH_STD_PRC_STATUS = 'processing';
                    patch.push_std_prc = 'processing';
                } else if (st === 'ok') {
                    patch.PUSH_STD_PRC_STATUS = 'pushed';
                    patch.push_std_prc = 'pushed';
                    if (isFinite(std) && std > 0) {
                        patch.PUSH_STD_PRC_VALUE = std;
                        patch['RV Price'] = std;
                    }
                } else if (st === 'failed') {
                    patch.PUSH_STD_PRC_STATUS = 'error';
                    patch.push_std_prc = 'error';
                }
                if (Object.keys(patch).length) reverbPushStdUpdateRow(row, patch);
            });
        }
        function paintReverbPushStdProgress(resp) {
            if (typeof setChPromoPushPrcProgress !== 'function') return;
            const active = !!(resp && resp.active);
            const total = Number(resp && resp.total) || 0;
            const done = Number(resp && resp.done_count) || 0;
            const ok = Number(resp && resp.ok_count) || 0;
            const fail = Number(resp && resp.fail_count) || 0;
            const pct = Number(resp && resp.pct) || 0;
            const sku = resp && resp.job && resp.job.current_sku;
            setChPromoPushPrcProgress({
                active: active,
                done: done,
                total: total,
                ok: ok,
                fail: fail,
                pct: pct,
                cancelable: active,
                title: active ? 'Pushing Std' : (fail && !ok ? 'Push Std failed' : 'Pushed Std'),
                msg: (resp && resp.message) || (sku ? ('Std · ' + sku) : ''),
            });
        }
        function stopReverbPushStdPoll() {
            if (reverbPushStdPollTimer) {
                clearInterval(reverbPushStdPollTimer);
                reverbPushStdPollTimer = null;
            }
        }
        function startReverbPushStdPoll() {
            stopReverbPushStdPoll();
            const tick = function() {
                $.ajax({
                    url: REVERB_PUSH_STD_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && Array.isArray(resp.tasks)) applyReverbPushStdTaskStatuses(resp.tasks);
                    paintReverbPushStdProgress(resp);
                    if (!(resp && resp.active)) stopReverbPushStdPoll();
                }).fail(function() { /* keep polling */ });
            };
            tick();
            reverbPushStdPollTimer = setInterval(tick, 1500);
        }
        function queueReverbPushStd(singleRow) {
            if (!table) {
                showToast('Load data first', 'error');
                return;
            }
            const all = reverbPushStdCollectTargets(singleRow || null);
            const forceOne = !!singleRow;
            const targets = all.filter(function(t) {
                return reverbPushStdCurrent(t.d) > 0 && (forceOne || reverbPushStdNeedsPush(t.d));
            });
            const skipped = all.length - targets.length;
            if (!targets.length) {
                showToast(skipped
                    ? ('No Std Prc changes since last push (' + skipped + ' unchanged)')
                    : 'No SKUs with Std Prc to push', 'info');
                return;
            }
            const scope = singleRow ? 'this SKU' : (targets.length + ' SKU(s)');
            if (!confirm('Apply Std Price to Reverb for ' + scope
                + (skipped && !singleRow ? (' (' + skipped + ' unchanged skipped)') : '') + '?')) {
                return;
            }
            const items = targets.map(function(t) {
                return { sku: reverbPushStdSku(t.d), std: reverbPushStdCurrent(t.d) };
            });
            targets.forEach(function(t) {
                reverbPushStdUpdateRow(t.row, { PUSH_STD_PRC_STATUS: 'processing', push_std_prc: 'processing' });
            });
            const $btn = $('#reverb-apply-std-price-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Queuing…');
            $.ajax({
                url: REVERB_PUSH_STD_QUEUE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { items: items, _token: csrfToken() },
            }).done(function(resp) {
                if (resp && Array.isArray(resp.tasks)) applyReverbPushStdTaskStatuses(resp.tasks);
                paintReverbPushStdProgress(resp);
                showToast((resp && resp.message) || 'Push Std queued', 'success');
                startReverbPushStdPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to queue Push Std';
                showToast(msg, 'error');
                targets.forEach(function(t) {
                    reverbPushStdUpdateRow(t.row, { PUSH_STD_PRC_STATUS: 'error', push_std_prc: 'error' });
                });
            }).always(function() {
                $btn.prop('disabled', false).html(html);
            });
        }
        window.queueReverbPushStd = queueReverbPushStd;

        $('#reverb-apply-std-price-btn').on('click', function(e) {
            e.preventDefault();
            queueReverbPushStd();
        });
        $('#ch-promo-push-prc-cancel-btn').on('click.reverbstd', function(e) {
            if (!reverbPushStdPollTimer) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            $.ajax({
                url: REVERB_PUSH_STD_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { _token: csrfToken() },
            }).done(function(resp) {
                stopReverbPushStdPoll();
                if (resp && Array.isArray(resp.tasks)) applyReverbPushStdTaskStatuses(resp.tasks);
                paintReverbPushStdProgress(Object.assign({}, resp, { active: false }));
                showToast((resp && resp.message) || 'Push Std cancelled', 'info');
            });
        });
        $.ajax({
            url: REVERB_PUSH_STD_QUEUE_URL + '/status',
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            timeout: 15000,
        }).done(function(resp) {
            if (resp && Array.isArray(resp.tasks) && resp.tasks.length) {
                applyReverbPushStdTaskStatuses(resp.tasks);
            }
            if (resp && resp.active) startReverbPushStdPoll();
        });
        if (table && table.on) {
            table.on('dataLoaded', function() {
                if (reverbPushStdLastTasks && reverbPushStdLastTasks.length) {
                    applyReverbPushStdTaskStatuses(reverbPushStdLastTasks);
                }
                if (reverbPushPrmtLastTasks && reverbPushPrmtLastTasks.length) {
                    applyReverbPushPrmtTaskStatuses(reverbPushPrmtLastTasks);
                }
            });
            table.on('renderComplete', function() {
                reverbEachTableRow(function(row, d) {
                    const st = String(d.PUSH_STD_PRC_STATUS || d.push_std_prc || '');
                    if (st === 'processing' || st === 'pushed' || st === 'error') {
                        reverbPaintPushStdIcon(row);
                    }
                    if (typeof reverbPaintPushPrmtIcon === 'function') {
                        const pst = String(d.PUSH_PRC_STATUS || d.push_prmt || '');
                        if (pst === 'processing' || pst === 'pushed' || pst === 'error') {
                            reverbPaintPushPrmtIcon(row);
                        }
                    }
                });
            });
        }

        // ==================== Apply Prmt% / Push % queue ====================
        const REVERB_PUSH_PRMT_QUEUE_URL = '/reverb-push-prmt';
        let reverbPushPrmtPollTimer = null;
        let reverbPushPrmtLastTasks = [];

        function reverbPushPrmtNeedsPush(d) {
            const prmt = reverbPrmtPctOf(d);
            if (String((d && d.PUSH_PRC_STATUS) || '') === 'error') return true;
            const last = parseFloat(d && d.PUSH_PRC_VALUE);
            if (!isFinite(last) || last < 0) return prmt > 0;
            return Number(last).toFixed(1) !== Number(prmt).toFixed(1);
        }
        function reverbPaintPushPrmtIcon(row) {
            if (!row) return;
            try {
                const cell = row.getCell && row.getCell('push_prmt');
                if (cell && typeof cell.reformat === 'function') cell.reformat();
            } catch (e) { /* ignore */ }
            try {
                const d = row.getData() || {};
                const el = (row.getElement && row.getElement()) || null;
                const btn = el && el.querySelector && el.querySelector('.reverb-push-prmt-btn');
                if (!btn) return;
                const status = String(d.PUSH_PRC_STATUS || d.push_prmt || '');
                const prmt = reverbPrmtPctOf(d);
                const last = parseFloat(d.PUSH_PRC_VALUE);
                const lastOk = isFinite(last) && last >= 0;
                if (status === 'processing') {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>';
                    btn.style.color = '#ffc107';
                    btn.title = 'Applying Drop the Price By…';
                } else if (status === 'error') {
                    btn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    btn.style.color = '#dc3545';
                    btn.title = 'Last Drop the Price By failed — click to retry';
                } else if (status === 'pushed' && lastOk && Number(last).toFixed(1) === Number(prmt).toFixed(1)) {
                    btn.innerHTML = '<i class="fa-solid fa-check-double"></i>';
                    btn.style.color = '#28a745';
                    btn.title = 'Already pushed Drop the Price By ' + Number(last).toFixed(0) + '%';
                }
            } catch (e) { /* ignore */ }
        }
        function reverbPushPrmtUpdateRow(row, patch) {
            if (!row || !patch) return;
            const sku = reverbPushStdSku(row.getData() || {});
            reverbSyncRowInDataset(sku, patch);
            const paint = function() { reverbPaintPushPrmtIcon(row); };
            try {
                const p = row.update(patch);
                if (p && typeof p.then === 'function') p.then(paint).catch(paint);
                else paint();
            } catch (e) {
                paint();
            }
        }
        function reverbPushPrmtRefreshCell(row) {
            reverbPaintPushPrmtIcon(row);
        }
        function applyReverbPushPrmtTaskStatuses(tasks) {
            if (!table || !Array.isArray(tasks)) return;
            reverbPushPrmtLastTasks = tasks;
            const bySku = {};
            tasks.forEach(function(t) {
                const k = reverbPushStdSkuKey(t && t.sku);
                if (k) bySku[k] = t;
            });
            reverbEachTableRow(function(row, d) {
                const t = bySku[reverbPushStdSkuKey(reverbPushStdSku(d))];
                if (!t) return;
                const st = String(t.status || '');
                const prmtVal = parseFloat(t.prmt != null ? t.prmt : t.price);
                const patch = {};
                if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    patch.PUSH_PRC_STATUS = 'processing';
                    patch.push_prmt = 'processing';
                } else if (st === 'ok') {
                    patch.PUSH_PRC_STATUS = 'pushed';
                    patch.push_prmt = 'pushed';
                    if (isFinite(prmtVal) && prmtVal >= 0) {
                        patch.PUSH_PRC_VALUE = prmtVal;
                    }
                    if (t.prmt != null) patch.prmt_pct = String(t.prmt);
                } else if (st === 'failed') {
                    patch.PUSH_PRC_STATUS = 'error';
                    patch.push_prmt = 'error';
                }
                if (Object.keys(patch).length) reverbPushPrmtUpdateRow(row, patch);
            });
        }
        function paintReverbPushPrmtProgress(resp) {
            if (typeof setChPromoPushPrcProgress !== 'function') return;
            const active = !!(resp && resp.active);
            const total = Number(resp && resp.total) || 0;
            const done = Number(resp && resp.done_count) || 0;
            const ok = Number(resp && resp.ok_count) || 0;
            const fail = Number(resp && resp.fail_count) || 0;
            const pct = Number(resp && resp.pct) || 0;
            const sku = resp && resp.job && resp.job.current_sku;
            setChPromoPushPrcProgress({
                active: active,
                done: done,
                total: total,
                ok: ok,
                fail: fail,
                pct: pct,
                cancelable: active,
                title: active ? 'Applying Prmt%' : (fail && !ok ? 'Prmt% failed' : 'Prmt% applied'),
                msg: (resp && resp.message) || (sku ? ('Prmt% · ' + sku) : ''),
            });
        }
        function stopReverbPushPrmtPoll() {
            if (reverbPushPrmtPollTimer) {
                clearInterval(reverbPushPrmtPollTimer);
                reverbPushPrmtPollTimer = null;
            }
        }
        function startReverbPushPrmtPoll() {
            stopReverbPushPrmtPoll();
            const tick = function() {
                $.ajax({
                    url: REVERB_PUSH_PRMT_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && Array.isArray(resp.tasks)) applyReverbPushPrmtTaskStatuses(resp.tasks);
                    paintReverbPushPrmtProgress(resp);
                    if (!(resp && resp.active)) stopReverbPushPrmtPoll();
                }).fail(function() { /* keep polling */ });
            };
            tick();
            reverbPushPrmtPollTimer = setInterval(tick, 1500);
        }
        function queueReverbPushPrmt(singleRow) {
            if (!table) {
                showToast('Load data first', 'error');
                return;
            }
            const all = reverbPushStdCollectTargets(singleRow || null);
            const forceOne = !!singleRow;
            const targets = all.filter(function(t) {
                return forceOne || reverbPushPrmtNeedsPush(t.d);
            });
            const skipped = all.length - targets.length;
            if (!targets.length) {
                showToast(skipped
                    ? ('No PRMT% Drop the Price By changes since last push (' + skipped + ' unchanged)')
                    : 'No SKUs to apply Prmt%', 'info');
                return;
            }
            const scope = singleRow ? 'this SKU' : (targets.length + ' SKU(s)');
            if (!confirm('Apply Reverb Drop the Price By at PRMT% for ' + scope
                + '?\n\nListing / Std price will not change.'
                + (skipped && !singleRow ? ('\n(' + skipped + ' unchanged skipped)') : ''))) {
                return;
            }
            const items = targets.map(function(t) {
                return {
                    sku: reverbPushStdSku(t.d),
                    std: reverbPushStdCurrent(t.d),
                    prmt: reverbPrmtPctOf(t.d)
                };
            });
            targets.forEach(function(t) {
                try {
                    reverbPushPrmtUpdateRow(t.row, { PUSH_PRC_STATUS: 'processing', push_prmt: 'processing' });
                } catch (e) { /* ignore */ }
                reverbPushPrmtRefreshCell(t.row);
            });
            const $btn = $('#reverb-apply-prmt-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Queuing…');
            $.ajax({
                url: REVERB_PUSH_PRMT_QUEUE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { items: items, _token: csrfToken() },
            }).done(function(resp) {
                if (resp && Array.isArray(resp.tasks)) applyReverbPushPrmtTaskStatuses(resp.tasks);
                paintReverbPushPrmtProgress(resp);
                showToast((resp && resp.message) || 'Push Prmt% queued', 'success');
                startReverbPushPrmtPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to queue Apply Prmt%';
                showToast(msg, 'error');
                targets.forEach(function(t) {
                    reverbPushPrmtUpdateRow(t.row, { PUSH_PRC_STATUS: 'error', push_prmt: 'error' });
                });
            }).always(function() {
                $btn.prop('disabled', false).html(html);
            });
        }
        window.queueReverbPushPrmt = queueReverbPushPrmt;

        $('#reverb-apply-prmt-btn').on('click', function(e) {
            e.preventDefault();
            queueReverbPushPrmt();
        });

        // ==================== Apply Bump / Push B% queue ====================
        const REVERB_PUSH_BUMP_QUEUE_URL = '/reverb-push-bump';
        let reverbPushBumpPollTimer = null;

        function reverbPushBumpNeedsPush(d) {
            const bump = reverbSBumpPctOf(d);
            if (String((d && d.PUSH_BUMP_STATUS) || '') === 'error') return true;
            const last = parseFloat(d && d.PUSH_BUMP_VALUE);
            if (isFinite(last) && last >= 0) {
                return Number(last).toFixed(1) !== Number(bump).toFixed(1);
            }
            const live = reverbLiveBumpPctOf(d);
            if (Number(live).toFixed(1) !== Number(bump).toFixed(1)) return true;
            return bump > 0 && !isFinite(last);
        }
        function reverbPaintPushBumpIcon(row) {
            if (!row) return;
            try {
                const cell = row.getCell && row.getCell('push_bump');
                if (cell && typeof cell.reformat === 'function') cell.reformat();
            } catch (e) { /* ignore */ }
        }
        function reverbPushBumpUpdateRow(row, patch) {
            if (!row || !patch) return;
            const sku = reverbPushStdSku(row.getData() || {});
            reverbSyncRowInDataset(sku, patch);
            const paint = function() { reverbPaintPushBumpIcon(row); };
            try {
                const p = row.update(patch);
                if (p && typeof p.then === 'function') p.then(paint).catch(paint);
                else paint();
            } catch (e) {
                paint();
            }
        }
        function applyReverbPushBumpTaskStatuses(tasks) {
            if (!table || !Array.isArray(tasks)) return;
            const bySku = {};
            tasks.forEach(function(t) {
                const k = reverbPushStdSkuKey(t && t.sku);
                if (k) bySku[k] = t;
            });
            reverbEachTableRow(function(row, d) {
                const t = bySku[reverbPushStdSkuKey(reverbPushStdSku(d))];
                if (!t) return;
                const st = String(t.status || '');
                const bumpVal = parseFloat(t.bump);
                const patch = {};
                if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    patch.PUSH_BUMP_STATUS = 'processing';
                    patch.push_bump = 'processing';
                } else if (st === 'ok') {
                    patch.PUSH_BUMP_STATUS = 'pushed';
                    patch.push_bump = 'pushed';
                    if (isFinite(bumpVal) && bumpVal >= 0) {
                        patch.PUSH_BUMP_VALUE = bumpVal;
                        patch.Bump = (typeof reverbFormatSBump === 'function')
                            ? reverbFormatSBump(bumpVal)
                            : (String(Math.round(bumpVal)) + '%');
                    }
                } else if (st === 'failed') {
                    patch.PUSH_BUMP_STATUS = 'error';
                    patch.push_bump = 'error';
                }
                if (Object.keys(patch).length) reverbPushBumpUpdateRow(row, patch);
            });
        }
        function paintReverbPushBumpProgress(resp) {
            if (typeof setChPromoPushPrcProgress !== 'function') return;
            const active = !!(resp && resp.active);
            const total = Number(resp && resp.total) || 0;
            const done = Number(resp && resp.done_count) || 0;
            const ok = Number(resp && resp.ok_count) || 0;
            const fail = Number(resp && resp.fail_count) || 0;
            const pct = Number(resp && resp.pct) || 0;
            const sku = resp && resp.job && resp.job.current_sku;
            setChPromoPushPrcProgress({
                active: active,
                done: done,
                total: total,
                ok: ok,
                fail: fail,
                pct: pct,
                cancelable: active,
                title: active ? 'Applying Bump%' : (fail && !ok ? 'Bump% failed' : 'Bump% applied'),
                msg: (resp && resp.message) || (sku ? ('Bump% · ' + sku) : ''),
            });
        }
        function stopReverbPushBumpPoll() {
            if (reverbPushBumpPollTimer) {
                clearInterval(reverbPushBumpPollTimer);
                reverbPushBumpPollTimer = null;
            }
        }
        function startReverbPushBumpPoll() {
            stopReverbPushBumpPoll();
            const tick = function() {
                $.ajax({
                    url: REVERB_PUSH_BUMP_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && Array.isArray(resp.tasks)) applyReverbPushBumpTaskStatuses(resp.tasks);
                    paintReverbPushBumpProgress(resp);
                    if (!(resp && resp.active)) stopReverbPushBumpPoll();
                }).fail(function() { /* keep polling */ });
            };
            tick();
            reverbPushBumpPollTimer = setInterval(tick, 1500);
        }
        function queueReverbPushBump(singleRow) {
            if (!table) {
                showToast('Load data first', 'error');
                return;
            }
            const all = reverbPushStdCollectTargets(singleRow || null);
            const forceOne = !!singleRow;
            const targets = all.filter(function(t) {
                return forceOne || reverbPushBumpNeedsPush(t.d);
            });
            const skipped = all.length - targets.length;
            if (!targets.length) {
                showToast(skipped
                    ? ('No S Bump% changes since last push (' + skipped + ' unchanged)')
                    : 'No SKUs to apply Bump', 'info');
                return;
            }
            const scope = singleRow ? 'this SKU' : (targets.length + ' SKU(s)');
            if (!confirm('Push Reverb Bump bid at S Bump% for ' + scope + '?'
                + (skipped && !singleRow ? ('\n(' + skipped + ' unchanged skipped)') : ''))) {
                return;
            }
            const items = targets.map(function(t) {
                return {
                    sku: reverbPushStdSku(t.d),
                    bump: reverbSBumpPctOf(t.d)
                };
            });
            targets.forEach(function(t) {
                try {
                    reverbPushBumpUpdateRow(t.row, { PUSH_BUMP_STATUS: 'processing', push_bump: 'processing' });
                } catch (e) { /* ignore */ }
            });
            const $btn = $('#reverb-apply-bump-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Queuing…');
            $.ajax({
                url: REVERB_PUSH_BUMP_QUEUE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { items: items, _token: csrfToken() },
            }).done(function(resp) {
                if (resp && Array.isArray(resp.tasks)) applyReverbPushBumpTaskStatuses(resp.tasks);
                paintReverbPushBumpProgress(resp);
                showToast((resp && resp.message) || 'Push B% queued', 'success');
                startReverbPushBumpPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to queue Apply Bump';
                showToast(msg, 'error');
                targets.forEach(function(t) {
                    reverbPushBumpUpdateRow(t.row, { PUSH_BUMP_STATUS: 'error', push_bump: 'error' });
                });
            }).always(function() {
                $btn.prop('disabled', false).html(html);
            });
        }
        window.queueReverbPushBump = queueReverbPushBump;

        $('#reverb-apply-bump-btn').on('click', function(e) {
            e.preventDefault();
            queueReverbPushBump();
        });
        $('#ch-promo-push-prc-cancel-btn').on('click.reverbbump', function(e) {
            if (!reverbPushBumpPollTimer) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            $.ajax({
                url: REVERB_PUSH_BUMP_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { _token: csrfToken() },
            }).done(function(resp) {
                stopReverbPushBumpPoll();
                if (resp && Array.isArray(resp.tasks)) applyReverbPushBumpTaskStatuses(resp.tasks);
                paintReverbPushBumpProgress(Object.assign({}, resp, { active: false }));
                showToast((resp && resp.message) || 'Push B% cancelled', 'info');
            });
        });
        $.ajax({
            url: REVERB_PUSH_BUMP_QUEUE_URL + '/status',
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            timeout: 15000,
        }).done(function(resp) {
            if (resp && Array.isArray(resp.tasks) && resp.tasks.length) {
                applyReverbPushBumpTaskStatuses(resp.tasks);
            }
            if (resp && resp.active) startReverbPushBumpPoll();
        });
        $('#ch-promo-push-prc-cancel-btn').on('click.reverbprmt', function(e) {
            if (!reverbPushPrmtPollTimer) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            $.ajax({
                url: REVERB_PUSH_PRMT_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                data: { _token: csrfToken() },
            }).done(function(resp) {
                stopReverbPushPrmtPoll();
                if (resp && Array.isArray(resp.tasks)) applyReverbPushPrmtTaskStatuses(resp.tasks);
                paintReverbPushPrmtProgress(Object.assign({}, resp, { active: false }));
                showToast((resp && resp.message) || 'Push Prmt% cancelled', 'info');
            });
        });
        $.ajax({
            url: REVERB_PUSH_PRMT_QUEUE_URL + '/status',
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            timeout: 15000,
        }).done(function(resp) {
            if (resp && Array.isArray(resp.tasks) && resp.tasks.length) {
                applyReverbPushPrmtTaskStatuses(resp.tasks);
            }
            if (resp && resp.active) startReverbPushPrmtPoll();
        });

        const COL_VIS_CATEGORY_KEYS = ['basic', 'pricing', 'advertisement', 'other'];
        const COL_VIS_CATEGORY_LABELS = {
            basic: 'Basic',
            pricing: 'Pricing',
            advertisement: 'Advertisement',
            other: 'Other'
        };
        const COL_VIS_BASIC = {
            Parent: 1, image_path: 1, '(Child) sku': 1, links_column: 1, INV: 1, L30: 1,
            'RV Dil%': 1, 'RV L30': 1, reverb_daily_qty: 1, reverb_daily_qty_x_subtotal: 1,
            reverb_daily_qty_x_amount: 1, 'R Stock': 1, Views: 1, CVR: 1, nr_req: 1
        };
        const COL_VIS_PRICING = {
            STANDARD_PRICE: 1, push_std_prc: 1, 'RV Price': 1, 'A Price': 1, lmp_price: 1,
            linked_lmp_skus: 1, linked_lmp_sku_add: 1, 'ROI%': 1, 'GPFT%': 1, NPFT: 1, NROI: 1,
            Profit: 1, 'Sales L30': 1, LP_productmaster: 1, Ship_productmaster: 1, prmt_pct: 1,
            cpn_pct: 1, sprc_cpn: 1, SPRICE: 1, SROI: 1, SGPFT: 1, SNPFT: 1, SNROI: 1, push_price: 1
        };
        const COL_VIS_ADS = {
            Missing_Ad: 1, Bump: 1, API_REC_BID: 1, RE_BID: 1, push_bump: 1
        };
        function reverbColVisPlainTitle(def) {
            const field = def && def.field ? String(def.field) : '';
            if (field === 'push_std_prc') return 'Push Std';
            if (field === 'push_bump') return 'Push B%';
            if (field === 'prmt_pct') return 'PRMT %';
            if (field === 'cpn_pct') return 'CPN %';
            if (field === 'sprc_cpn') return 'Sprc CPN';
            if (field === 'push_price') return 'Push';
            const raw = (def && def.title != null) ? def.title : field;
            const t = String(raw).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            return t || field;
        }
        function classifyReverbColumn(field, title) {
            const f = String(field || '');
            const t = String(title || '').toLowerCase();
            if (COL_VIS_ADS[f] || /\b(ads?|bump|bid|campaign)\b/i.test(t)) return 'advertisement';
            if (COL_VIS_PRICING[f] || /\b(price|prc|lmp|roi|gpft|pft|nroi|sprice|sroi|sgpft|snpft|snroi|prmt|cpn|ship|profit|sales|push)\b/i.test(t)) {
                return 'pricing';
            }
            if (COL_VIS_BASIC[f] || t === 'p' || /\b(parent|image|sku|inv|dil|views?|cvr|nr\/?req|map|links?|stock)\b/i.test(t)) {
                return 'basic';
            }
            return 'other';
        }
        const COL_VIS_CATEGORY_STORAGE_KEY = 'reverb_tabulator_column_categories_v1';
        function colVisItemKey(field, title) {
            return String(field || '') + '||' + String(title || field || '');
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
            return classifyReverbColumn(field, title);
        }
        function syncReverbGroupHeaderCheckbox(groupEl) {
            if (!groupEl) return;
            const headerCb = groupEl.querySelector('.col-vis-group-toggle');
            const itemCbs = groupEl.querySelectorAll('.col-vis-item input[type="checkbox"]');
            if (!headerCb || !itemCbs.length) return;
            let checked = 0;
            itemCbs.forEach(function(cb) { if (cb.checked) checked++; });
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }
        /**
         * Build Columns dropdown — Basic / Pricing / Advertisement / Other.
         * Header checkbox selects / deselects the whole group.
         */
        function buildColumnDropdown(savedVisibility) {
            const menu = document.getElementById('column-dropdown-menu');
            if (!menu || !table) return;

            const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
            menu.innerHTML = '';

            const showAllLi = document.createElement('li');
            showAllLi.className = 'dropdown-item column-dropdown-span-all';
            showAllLi.innerHTML = '<a class="fw-bold" href="#" id="show-all-columns-btn" style="text-decoration: none; color: inherit;">'
                + '<i class="fa fa-eye"></i> Show All Columns</a>';
            menu.appendChild(showAllLi);

            const showDefaultLi = document.createElement('li');
            showDefaultLi.className = 'dropdown-item column-dropdown-span-all';
            showDefaultLi.innerHTML = '<a class="fw-bold" href="#" id="show-default-columns-btn" style="text-decoration: none; color: inherit;">'
                + '<i class="fa fa-undo"></i> Show Default Columns</a>';
            menu.appendChild(showDefaultLi);

            const divider = document.createElement('li');
            divider.className = 'column-dropdown-span-all';
            divider.innerHTML = '<hr class="dropdown-divider">';
            menu.appendChild(divider);

            const groupsLi = document.createElement('li');
            groupsLi.className = 'col-vis-full';
            const groupsWrap = document.createElement('div');
            groupsWrap.className = 'col-vis-groups';

            const lists = {};
            const groupEls = {};
            const overrides = loadColumnCategoryOverrides();
            COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                const group = document.createElement('div');
                group.className = 'col-vis-group';
                group.dataset.category = cat;

                const titleEl = document.createElement('label');
                titleEl.className = 'col-vis-group-title';
                const groupCb = document.createElement('input');
                groupCb.type = 'checkbox';
                groupCb.className = 'col-vis-group-toggle';
                groupCb.dataset.group = cat;
                groupCb.title = 'Select / deselect all in ' + COL_VIS_CATEGORY_LABELS[cat];
                titleEl.appendChild(groupCb);
                titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                group.appendChild(titleEl);

                const list = document.createElement('ul');
                list.className = 'col-vis-group-list';
                list.dataset.category = cat;
                group.appendChild(list);
                groupsWrap.appendChild(group);
                lists[cat] = list;
                groupEls[cat] = group;

                [group, list].forEach(function(zone) {
                    zone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.add('col-vis-drop-over');
                        e.dataTransfer.dropEffect = 'move';
                    });
                    zone.addEventListener('dragleave', function(e) {
                        if (!group.contains(e.relatedTarget)) {
                            group.classList.remove('col-vis-drop-over');
                        }
                    });
                    zone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        group.classList.remove('col-vis-drop-over');
                        const itemKey = e.dataTransfer.getData('text/col-vis-key');
                        if (!itemKey) return;
                        const next = loadColumnCategoryOverrides();
                        next[itemKey] = cat;
                        saveColumnCategoryOverrides(next);
                        buildColumnDropdown();
                    });
                });
            });

            table.getColumns().forEach(function(col) {
                const def = col.getDefinition();
                const field = def.field;
                if (!field || field === '_select' || field === 'push_prmt') return;
                const title = reverbColVisPlainTitle(def);
                if (!title) return;
                const itemKey = colVisItemKey(field, title);
                const cat = resolveColumnCategory(field, title, overrides);
                const isVisible = map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible();

                const li = document.createElement('li');
                li.className = 'col-vis-item';
                li.draggable = true;
                li.dataset.itemKey = itemKey;
                li.dataset.field = field;
                li.dataset.group = cat;

                li.addEventListener('dragstart', function(e) {
                    e.stopPropagation();
                    li.classList.add('col-vis-dragging');
                    e.dataTransfer.setData('text/col-vis-key', itemKey);
                    e.dataTransfer.effectAllowed = 'move';
                });
                li.addEventListener('dragend', function() {
                    li.classList.remove('col-vis-dragging');
                    menu.querySelectorAll('.col-vis-drop-over').forEach(function(el) {
                        el.classList.remove('col-vis-drop-over');
                    });
                });

                const label = document.createElement('label');
                const checkbox = document.createElement('input');
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
                lists[cat].appendChild(li);
            });

            COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                syncReverbGroupHeaderCheckbox(groupEls[cat]);
            });

            groupsLi.appendChild(groupsWrap);
            menu.appendChild(groupsLi);
        }

        /** Persist visibility to channel_tabulator_column_settings (shared — same as Amazon). */
        function saveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getDefinition().field;
                if (field && field !== '_select') {
                    visibility[field] = col.isVisible();
                }
            });

            fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility
                })
            }).catch(err => console.error('Error saving column visibility:', err));
        }

        /** Load + apply saved visibility, then rebuild dropdown (Amazon tableBuilt flow). */
        function applyColumnVisibilityFromServer() {
            return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                }
            })
                .then(res => res.json())
                .then(savedVisibility => {
                    const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                    if (Object.keys(map).length > 0) {
                        table.getColumns().forEach(col => {
                            const field = col.getDefinition().field;
                            if (field && map.hasOwnProperty(field)) {
                                if (map[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                    }
                    // Parent + S Bump stay hidden (not part of normal pricing view).
                    adsOnlyColumnFields.forEach(function(field) {
                        const col = table.getColumn(field);
                        if (col) col.hide();
                    });
                    adsAlwaysVisibleFields.forEach(function(field) {
                        const col = table.getColumn(field);
                        if (col) col.show();
                        map[field] = true;
                    });
                    const aPrcCol = table.getColumn('A Price');
                    if (aPrcCol) aPrcCol.hide();
                    try {
                        if (table.getColumn('push_prmt')) table.deleteColumn('push_prmt');
                    } catch (e) { /* already gone */ }
                    buildColumnDropdown(map);
                })
                .catch(err => {
                    console.error('Error applying column visibility:', err);
                    buildColumnDropdown();
                });
        }

        // Wait for table to be built — apply saved columns first (same as Amazon).
        table.on('tableBuilt', function() {
            try {
                if (table.getColumn('push_prmt')) table.deleteColumn('push_prmt');
            } catch (e) { /* already gone */ }
            applyColumnVisibilityFromServer();
            loadReverbDailyTotalsBadges();
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyReverbUrlBadgeFilter();
                updateSummary();
                loadReverbDailyTotalsBadges();
            }, 100);
        });

        // Badges only — Ads% is SSR (Amazon pattern); do not refetch Ads on every paint
        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
            }, 100);
        });

        // Toggle column / group from dropdown — save immediately (Amazon pattern).
        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox') return;
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
            const field = e.target.getAttribute('data-field') || e.target.dataset.field;
            if (!field) return;
            const col = table.getColumn(field);
            if (!col) return;
            if (e.target.checked) {
                col.show();
            } else {
                col.hide();
            }
            syncReverbGroupHeaderCheckbox(e.target.closest('.col-vis-group'));
            saveColumnVisibilityToServer();
        });
        document.getElementById('column-dropdown-menu').addEventListener('click', function(e) {
            if (e.target.closest('label') || e.target.type === 'checkbox') {
                e.stopPropagation();
            }
        });

        function applyReverbDefaultColumnVisibility() {
            if (!table) return;
            table.getColumns().forEach(function(col) {
                const field = col.getDefinition().field;
                if (!field) return;
                if (field === '_select' || reverbDefaultHiddenFields[field] || adsOnlyColumnFields.indexOf(field) !== -1) {
                    col.hide();
                    return;
                }
                col.show();
            });
        }

        // Show All Columns (ads-only columns stay hidden).
        $('#column-dropdown-menu').on('click', '#show-all-columns-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            table.getColumns().forEach(col => {
                const field = col.getDefinition().field;
                if (!field || field === '_select') return;
                if (adsOnlyColumnFields.indexOf(field) !== -1) {
                    col.hide();
                } else {
                    col.show();
                }
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Restore designed defaults (Push % gone; ads / A Prc / LP / Ship hidden).
        $('#column-dropdown-menu').on('click', '#show-default-columns-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            applyReverbDefaultColumnVisibility();
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export CSV button
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible() && col.getField() !== '_select');
            
            // Get headers
            const headers = visibleColumns.map(col => {
                let title = col.getDefinition().title || col.getField();
                // Remove HTML tags from header
                return title.replace(/<[^>]*>/g, '');
            });
            exportData.push(headers);
            
            // Get filtered data (all visible rows)
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    // Clean up values
                    if (value === null || value === undefined) {
                        value = '';
                    } else if (typeof value === 'number') {
                        value = parseFloat(value.toFixed(2));
                    } else if (typeof value === 'string') {
                        // Remove HTML tags
                        value = value.replace(/<[^>]*>/g, '').trim();
                    }
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            // Create CSV
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'reverb_pricing_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection

