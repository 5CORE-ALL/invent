@extends('layouts.vertical', ['title' => $tiktokPageTitle ?? 'TikTok 1 Shop - Analytics', 'sidenav' => 'condensed'])
@php
    $tiktokPromoChannel = ((str_contains($tiktokPageTitle ?? '', 'TikTok 2') || (($tiktokPricingClientConfig['summaryChannel'] ?? '') === 'tiktok2')) ? 'tiktok2' : 'tiktok');
@endphp

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

        /* Sku Link LMP (mirrors /shein-pricing) */
        .linked-sku-badge-wrap { display: inline-flex; align-items: center; gap: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove { font-size: 0.55rem; opacity: 0.65; padding: 0; margin-left: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover { opacity: 1; }
        .sku-link-lmp-suggestion-item { cursor: pointer; }
        .sku-link-lmp-suggestion-item .form-check-input { pointer-events: none; }
        .sku-link-lmp-selected-chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 999px; background: #f1f5f9;
            border: 1px solid #e2e8f0; font-size: 12px;
        }
        .sku-link-lmp-selected-chip button {
            border: 0; background: transparent; padding: 0; line-height: 1;
            font-size: 14px; color: #64748b;
        }

        /* Parent summary rows — same cream + height as /amazon-tabulator-view */
        .tabulator-row.parent-row,
        .tabulator-row.tt-parent-row {
            background-color: #fffef2 !important;
            font-weight: bold !important;
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        .tabulator-row.parent-row .tabulator-cell,
        .tabulator-row.tt-parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            overflow: hidden !important;
            vertical-align: middle !important;
        }
        .tabulator-row.parent-row .tabulator-cell[tabulator-field="(Child) sku"],
        .tabulator-row.tt-parent-row .tabulator-cell[tabulator-field="(Child) sku"] {
            color: #212529 !important;
        }

        /* Column visibility dropdown — 4 category panels (basics · pricing · advt · other) */
        #column-dropdown-menu.show {
            display: block;
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            padding: 0.4rem 0.5rem 0.55rem;
            max-height: 420px;
            overflow-y: auto;
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
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
        }
        #column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 300px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #column-dropdown-menu .col-vis-item {
            list-style: none;
            margin: 0;
            padding: 0;
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
        #column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            #column-dropdown-menu .col-vis-groups {
                grid-template-columns: repeat(2, minmax(120px, 1fr));
            }
        }

        /* Toolbar: above summary/table for dropdowns, but BELOW the desktop
           sidebar drawer (z-index 1045) and its backdrop (1040). A prior
           z-index of 1055 made CSV / PRc / Columns paint through the open menu. */
        .tt-toolbar-row {
            position: relative;
            z-index: 20;
            row-gap: 4px;
        }
        .tt-toolbar-row .dropdown,
        .tt-toolbar-row .btn-group,
        .tt-toolbar-row .manual-dropdown-container {
            position: relative;
            z-index: 21;
        }
        .tt-toolbar-row .dropdown-menu {
            z-index: 30 !important;
        }
        #summary-stats,
        #utilized-count-section {
            position: relative;
            z-index: 1;
        }
        #tiktok-table-wrapper,
        #tiktok-table {
            position: relative;
            z-index: 1;
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
            z-index: 1060;
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

        /* NRP (REQ / NR) — stored in tiktok_shop_data_views / tiktok_two_shop_data_views value.NRP */

        /* Summary badges — wrap to content width so labels never overflow */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem 0.45rem;
            width: 100%;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 0 0 auto;
            min-width: max-content;
            max-width: none;
            font-size: 0.8rem !important;
            line-height: 1.25 !important;
            padding: 0.35rem 0.65rem !important;
            font-weight: 700;
            box-sizing: border-box;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
        }
        #summary-stats .ebay2-summary-badge-row > .badge.tt-badge-chart::after {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.55;
            flex-shrink: 0;
        }

        /* Metric history modals — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #ttBadgeChartModal.modal,
        #skuMetricsModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #ttBadgeChartModal .modal-dialog,
        #skuMetricsModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #ttBadgeChartModal .modal-content,
        #skuMetricsModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => $tiktokPromoChannel])
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'css', 'lmpIgnoreModal' => '#ttLmpModal'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $tiktokPageTitle ?? 'TikTok 1 Shop - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                {{-- <h4>TikTok Analytics</h4> --}}

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="d-flex align-items-center flex-wrap gap-2 tt-toolbar-row">
                    <input type="text" id="parent-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search Parent..." style="width: 150px;">
                    <input type="text" id="sku-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search SKU..." style="width: 150px;">

                    <select id="row-type-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">All Rows</option>
                        <option value="parent">Parent Rows</option>
                        <option value="sku" selected>SKU Rows</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all" selected>All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="tiktok-stock-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">TT Stock</option>
                        <option value="zero">0 TT Stock</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;" title="CVR = TT L30 ÷ T views">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40plus">Above 40%</option>
                    </select>
                    <select id="cvr-filter" class="form-select form-select-sm flex-shrink-0" style="width: 100px;" title="CVR = TT L30 ÷ T views">
                        <option value="all">CVR %</option>
                        <option value="0-0">0%</option>
                        <option value="0-3">0-3%</option>
                        <option value="3-7">3-7%</option>
                        <option value="7-13">7-13%</option>
                        <option value="13plus">13%+</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm flex-shrink-0" style="width: 120px;"
                        title="GROI standard: Red &lt;60%, Gray 60–90%, Green ≥90%">
                        <option value="all">ROI %</option>
                        <option value="red">Red &lt;60%</option>
                        <option value="gray">Gray 60–90%</option>
                        <option value="green">Green ≥90%</option>
                    </select>

                    <select id="ad-click-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">Ad Click</option>
                        <option value="zero">0 Clicks</option>
                        <option value="has">Has Clicks</option>
                    </select>

                    <select id="tl30-filter" class="form-select form-select-sm flex-shrink-0" style="width: 90px;"
                        title="Filter by TT L30 (excludes 0 inventory items)">
                        <option value="all">T L30</option>
                        <option value="0">0</option>
                        <option value="more">&gt;0</option>
                    </select>

                    <!-- DIL Filter — same options/thresholds as amazon-tabulator-view -->
                    <select id="dil-filter" class="form-select form-select-sm flex-shrink-0" style="width: auto;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block flex-shrink-0">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                        </ul>
                    </div>

                    @if (!empty($tiktokPricingClientConfig['syncFromApi'] ?? null))
                    <button type="button" id="tt2-sync-api-btn" class="btn btn-sm btn-success"
                        title="Fetch products + orders from TikTok Shop 2 API">
                        <i class="fas fa-cloud-download-alt"></i> Sync API
                    </button>
                    @endif

                    {{-- Export only — TikTok 1 & 2 are API-only (no sheet upload). --}}
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="dropdown"
                            aria-expanded="false" title="Export CSV">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#" id="export-btn">
                                    <i class="fas fa-file-excel text-success"></i> Export CSV
                                </a>
                            </li>
                        </ul>
                    </div>

                    <button id="price-mode-btn" class="btn btn-sm btn-warning"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        PRc
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => $tiktokPromoChannel])

                    {{-- Target ROI% bulk control — back-solves SPRICE so SROI = Target ROI%. --}}
                    {{-- Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin (TikTok take-home) --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="tt-target-roi-controls"
                        title="Target ROI% — sets SPRICE = (LP × (1 + Target ROI%/100) + Ship) / margin on every selected row (back-solves so SROI column equals the target)">
                        <label for="tt-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            ROI%:
                        </label>
                        <input type="number" id="tt-target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply'">
                        <button id="tt-apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save SPRICE = (LP × (1 + Target ROI%/100) + Ship) / margin for every selected row">
                            <i class="fas fa-bullseye"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves SPRICE so SGPFT = Target GPFT%. --}}
                    {{-- Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="tt-target-gpft-controls"
                        title="Target GPFT% — sets SPRICE = (LP + Ship) / (margin − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="tt-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            GPFT%:
                        </label>
                        <input type="number" id="tt-target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click Apply. Must be less than the TikTok take-home margin.">
                        <button id="tt-apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                            title="Compute & save SPRICE = (LP + Ship) / (margin − Target GPFT%/100) for every selected row">
                            <i class="fas fa-bullseye"></i>
                        </button>
                    </div>

                    <span class="badge bg-dark fs-6 p-2 text-nowrap" id="tt-rows-count-badge"
                        style="color: white; font-weight: bold;"
                        title="Number of rows currently shown after filters">Rows: 0</span>
                    <span class="badge bg-primary fs-6 p-2 text-nowrap" id="tt-selected-row-badge"
                        title="Number of selected rows">Sel: 0</span>

                    {{-- Bulk push SPRICE to TikTok Shop (visible when SKUs selected) --}}
                    <div class="dropdown d-inline-block flex-shrink-0" id="tt-bulk-actions-container" style="display: none;">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button"
                            id="ttBulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Bulk push SPRICE to TikTok">
                            <i class="fas fa-upload"></i> Bulk Push
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ttBulkActionsDropdown" style="min-width: 220px;">
                            <li class="px-3 py-2">
                                <div style="font-weight: 600; margin-bottom: 8px; color: #495057;">
                                    <i class="fas fa-upload"></i> Bulk Push Prices
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="tiktok" id="bulkPushTiktok" checked disabled>
                                    <label class="form-check-label" for="bulkPushTiktok" id="bulkPushTiktokLabel"
                                        style="color: #fe2c55; font-weight: 500;">
                                        TikTok
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-primary w-100" id="execute-bulk-push-tiktok" type="button">
                                    <i class="fas fa-paper-plane"></i> Push Selected
                                </button>
                            </li>
                        </ul>
                    </div>

                </div>

                <!-- Ads/Utilized Count Section (shown when Show Ads Columns is on) -->
                <div id="utilized-count-section" class="mt-2 p-3 bg-light rounded border d-none">
                    <h6 class="mb-2"><i class="fa-solid fa-chart-line me-1"></i>Ads / Utilized Stats</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-sku-count" data-ads-filter="all"
                            style="color: black; font-weight: bold; background-color: #adb5bd; cursor: pointer;"
                            title="Click to show all">Total SKU: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-campaign-count"
                            data-ads-filter="campaign"
                            style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;"
                            title="Click to filter: has campaign">Campaign: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="ad-sku-count" data-ads-filter="ad-sku"
                            style="color: black; font-weight: bold; background-color: #b8d4a8; cursor: pointer;"
                            title="Click to filter: SKU active in ads with &gt;0 inventory">Ad SKU: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="missing-campaign-count"
                            data-ads-filter="missing"
                            style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;"
                            title="Click to filter: missing ad (no campaign, INV&gt;0, not NRA)">Missing Ad: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="nra-missing-count"
                            data-ads-filter="nra-missing"
                            style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;"
                            title="Click to filter: NRA missing">NRA MISSING: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="zero-inv-count" data-ads-filter="zero-inv"
                            style="color: black; font-weight: bold; background-color: #ffda6a; cursor: pointer;"
                            title="Click to filter: zero inventory">Zero INV: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="nra-count" data-ads-filter="nra"
                            style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;"
                            title="Click to filter: NRA">NRA: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="ra-count" data-ads-filter="ra"
                            style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;"
                            title="Click to filter: RA">RA: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-spend-l30-badge"
                            data-ads-filter="total-spend-l30"
                            style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;"
                            title="Click to filter: has L30 spend">L30 Spend: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-spend-l7-badge"
                            data-ads-filter="total-spend-l7"
                            style="color: black; font-weight: bold; background-color: #b8cfe5; cursor: pointer;"
                            title="Click to filter: has L7 spend">L7 Spend: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-budget-badge" data-ads-filter="budget"
                            style="color: black; font-weight: bold; background-color: #ced4da; cursor: pointer;"
                            title="Click to filter: has budget">Budget: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-ad-sales-badge"
                            data-ads-filter="ad-sales"
                            style="color: black; font-weight: bold; background-color: #9eeaf9; cursor: pointer;"
                            title="Click to filter: has ad sales">Ad Sales: $0</span>
                        <span class="badge fs-6 p-2" id="total-ad-sold-badge"
                            style="color: black; font-weight: bold; background-color: #f8b4d9;"
                            title="Total L30 Ad Sold">Total L30 Ad Sold: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-ad-clicks-badge"
                            data-ads-filter="ad-clicks"
                            style="color: black; font-weight: bold; background-color: #a5d6e8; cursor: pointer;"
                            title="Click to filter: has ad clicks">Ad Clicks: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="avg-clicks-badge"
                            style="color: black; font-weight: bold; background-color: #b8d4e3;"
                            title="Avg Clicks = Total Clicks / Total Ad SKU">Avg Clicks: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="avg-acos-badge" data-ads-filter="avg-acos"
                            style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;"
                            title="Click to filter: has spend/sales">Avg ACOS: 0%</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="roas-badge" data-ads-filter="roas"
                            style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;"
                            title="Click to filter: has spend/sales">ROAS: 0.00</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                        <label class="form-label mb-0 me-1 text-nowrap" style="font-size: 0.8rem;"><i
                                class="fa-solid fa-upload me-1"></i>Campaign:</label>
                        <input type="file" id="l7-upload-file" accept=".xlsx,.xls,.csv"
                            class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="l7-upload-btn" class="btn btn-sm btn-primary"
                            title="Upload L7 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L7
                        </button>
                        <input type="file" id="l30-upload-file" accept=".xlsx,.xls,.csv"
                            class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="l30-upload-btn" class="btn btn-sm btn-primary"
                            title="Upload L30 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L30
                        </button>
                        <input type="file" id="l1-upload-file" accept=".xlsx,.xls,.csv"
                            class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="l1-upload-btn" class="btn btn-sm btn-primary"
                            title="Upload L1 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L1
                        </button>
                        <span id="upload-status-container" class="ms-2" style="font-size: 0.7rem;"></span>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-primary fs-6 p-2 tt-badge-chart" data-metric="total_sales"
                            id="total-sales-amt-badge" style="color: black; font-weight: bold; cursor: pointer;"
                            title="Click for daily trend">Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2 tt-badge-chart" data-metric="avg_gpft" id="avg-gpft-badge"
                            style="color: black; font-weight: bold; cursor: pointer;" title="Click for daily trend">GPFT:
                            0%</span>
                        <span class="badge bg-success fs-6 p-2 tt-badge-chart" data-metric="total_l30"
                            id="total-l30-badge" style="color: black; font-weight: bold; cursor: pointer;"
                            title="Click for daily trend">L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" data-metric="zero_sold_count"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" data-metric="sold_count"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                            title="Click to filter">&gt; 0 Sold: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="avg_roi"
                            id="roi-percent-badge" style="background-color:#6c757d;color:#fff;font-weight:bold;cursor:pointer;"
                            title="ROI% = Σ PFT ÷ Σ COGS. Red &lt;60%, gray 60–90%, green ≥90% (GROI standard). Click for daily trend.">ROI%: 0%</span>
                        <span class="badge fs-6 p-2" id="tiktok-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        @include('partials.price-gt-lmp-badge', [
                            'pglBadgeId' => 'tiktok-price-gt-lmp-badge',
                            'pglChannelKey' => (str_contains($tiktokPageTitle ?? '', 'TikTok 2') || (($tiktokPricingClientConfig['summaryChannel'] ?? '') === 'tiktok2')) ? 'tiktok2' : 'tiktok',
                            'pglPriceField' => 'TT Price'
                        ])
                        @include('partials.price-lt80-lmp-badge', [
                            'pltBadgeId' => 'tiktok-price-lt80-lmp-badge',
                            'pltChannelKey' => (str_contains($tiktokPageTitle ?? '', 'TikTok 2') || (($tiktokPricingClientConfig['summaryChannel'] ?? '') === 'tiktok2')) ? 'tiktok2' : 'tiktok',
                            'pltPriceField' => 'TT Price'
                        ])
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_spend_30"
                            id="tt-spend-30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #9ec5fe;"
                            title="Sum of Spend 30 from tiktok_campaign_reports L30. Click for daily trend.">Spend 30: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_spend_1"
                            id="tt-spend-1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #b8cfe5;"
                            title="Sum of Spend 1 (L1, else L7) from tiktok_campaign_reports. Click for daily trend.">Spend 1: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_ads_views_30"
                            id="tt-ads-views-30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #cfe2ff;"
                            title="Sum of adsViews 30 (L30 impressions). Click for daily trend.">adsViews 30: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_ads_clicks_30"
                            id="tt-ads-clicks-30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #a5d6e8;"
                            title="Sum of ads Clicks 30 (L30 clicks). Click for daily trend.">ads Clicks 30: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_ads_views_1"
                            id="tt-ads-views-1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #d7e3fc;"
                            title="Sum of ads view1 (L1, else L7 impressions). Click for daily trend.">ads view1: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_ads_clicks_1"
                            id="tt-ads-clicks-1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #c5e4f3;"
                            title="Sum of ads clicks 1 (L1, else L7 clicks). Click for daily trend.">ads clicks 1: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="ads_cvr_30"
                            id="tt-ads-cvr-30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #ffe69c;"
                            title="ads CVR 30 = L30 ad sold / L30 clicks. Click for daily trend.">ads CVR 30: 0%</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="ads_roas"
                            id="tt-ads-roas-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #a3cfbb;"
                            title="ROAS = L30 ad revenue / L30 spend. Click for daily trend.">ROAS: 0.00</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="avg_target_roas"
                            id="tt-target-roas-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #cfe2ff;"
                            title="Average target ROAS (in_roas). Click for daily trend.">Target ROAS: 0.00</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="ads_acos_pct"
                            id="tt-ads-acos-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #f8d7da;"
                            title="ACOS% = L30 spend / L30 ad revenue. Click for daily trend.">Acos%: 0%</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_ad_sold_l30"
                            id="tt-gmv-ad-sold-l30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #cfe2ff;"
                            title="GMV Ad sold L30 from tiktok_gmv_ads (matched by SKU). Click for daily trend.">GMV Ad sold L30: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_ad_sold_l1"
                            id="tt-gmv-ad-sold-l1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #d7e3fc;"
                            title="GMV Ad sold L1 from tiktok_gmv_ads. Click for daily trend.">GMV Ad sold L1: 0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_ad_sales_l30"
                            id="tt-gmv-ad-sales-l30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #9ec5fe;"
                            title="GMV Ad sales L30 from tiktok_gmv_ads (matched by SKU). Click for daily trend.">GMV Ad sales L30: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_ad_sales_l1"
                            id="tt-gmv-ad-sales-l1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #b8cfe5;"
                            title="GMV Ad sales L1 from tiktok_gmv_ads. Click for daily trend.">GMV Ad sales L1: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_spend_l30"
                            id="tt-gmv-spend-l30-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #a5d6e8;"
                            title="GMV Spend L30 from tiktok_gmv_ads (matched by SKU). Click for daily trend.">GMV Spend L30: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_spend_l1"
                            id="tt-gmv-spend-l1-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #c5e4f3;"
                            title="GMV Spend L1 from tiktok_gmv_ads. Click for daily trend.">GMV Spend L1: $0</span>
                        <span class="badge fs-6 p-2 tt-badge-chart" data-metric="total_gmv_budget"
                            id="tt-gmv-budget-badge"
                            style="color: black; font-weight: bold; cursor: pointer; background-color: #ffe69c;"
                            title="GMV Budget from tiktok_gmv_ads (matched by SKU). Click for daily trend.">GMV Budget: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
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
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                        <button id="bulk-push-tiktok-btn" class="btn btn-warning btn-sm" type="button"
                            title="Push SPRICE for selected SKUs to TikTok Shop">
                            <i class="fas fa-paper-plane"></i> Push Selected
                        </button>
                    </div>
                </div>
                <div id="tiktok-table-wrapper"
                    style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body -->
                    <div id="tiktok-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge Trend Modal (top-aligned like eBay 3 / Faire badge charts — not vertically centered) -->
    <div class="modal fade p-0" id="ttBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;" id="ttBadgeChartModalTitle">TikTok - Badge Trend</h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="ttBadgeChartRangeSelect" class="form-select form-select-sm bg-white"
                            style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                <div class="modal-body pt-2 pb-2">
                    <div id="ttBadgeChartContainer" style="height: 38vh; display: none; flex-direction: row; align-items: stretch;">
                        <div style="flex: 1; min-width: 0;">
                            <canvas id="ttBadgeChartCanvas"></canvas>
                        </div>
                        <div
                            style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="ttBadgeChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-
                                </div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="ttBadgeChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="ttBadgeChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="ttBadgeChartLoading" class="text-center py-3" style="display: none;">
                        <span class="spinner-border spinner-border-sm me-2"></span>Loading chart...
                    </div>
                    <div id="ttBadgeChartNoData" class="text-center py-3 text-muted" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2 d-block"></i>
                        No daily snapshots yet. Open this page on separate days to build history (saved automatically).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-SKU Price chart (same pattern as /ebay-tabulator-view #skuMetricsModal) -->
    <div class="modal fade p-0" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="skuChartModalTitle">TikTok - <span id="modalSkuName"></span> - Metrics</span>
                        <span id="skuChartModalSuffix">(Rolling L30 · PT)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="sku-chart-days-filter" class="form-select form-select-sm bg-white"
                            style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;"
                            data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="skuChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="skuMetricsChart"></canvas>
                        </div>
                        <div id="skuChartRefPanel"
                            style="display: flex; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0; min-width: 0; flex-wrap: nowrap; overflow-x: auto;">
                            <div class="sku-ref-col" data-metric="0"
                                style="min-width: 62px; text-align: center; padding: 4px 4px;">
                                <div
                                    style="font-size: 7px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                    <span id="skuChartRefDot" class="sku-col-dot"
                                        style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; flex-shrink: 0;"></span>
                                    <span id="skuChartRefLabel">Price</span>
                                </div>
                                <div style="font-size: 6px; font-weight: 700; color: #dc3545;">High</div>
                                <div id="skuCol0High" style="font-size: 10px; font-weight: 700; color: #dc3545;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #6c757d;">Med</div>
                                <div id="skuCol0Med" style="font-size: 10px; font-weight: 700; color: #6c757d;">-</div>
                                <div style="font-size: 6px; font-weight: 700; color: #198754;">Low</div>
                                <div id="skuCol0Low" style="font-size: 10px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="skuChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="chart-no-data-message" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No historical data available for this SKU. Data will appear after
                            the page loads on separate days or after running <code>tiktok:collect-metrics</code>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="tiktokEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="tiktokEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="tiktokSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="tiktokBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="tiktokSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- LMP Competitors Modal (parallel to Amazon Tabulator's lmpModal) --}}
    <div class="modal fade" id="ttLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#ff0050;color:#fff;">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> TikTok Competitors for SKU: <span id="ttLmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-success" id="ttCompFormCard">
                        <div class="card-header bg-success text-white" id="ttCompFormHeader">
                            <strong><i class="fa fa-plus-circle" id="ttCompFormHeaderIcon"></i> <span id="ttCompFormHeaderText">Add New Competitor</span></strong>
                        </div>
                        <div class="card-body">
                            <form id="ttAddCompetitorForm" class="row g-3">
                                <input type="hidden" id="ttEditCompId" value="">
                                <div class="col-md-2">
                                    <label class="form-label"><strong>SKU</strong></label>
                                    <input type="text" class="form-control" id="ttAddCompSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Product ID</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ttAddCompProductId" placeholder="1731826094240797243" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="ttAddCompPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><strong>Ship</strong></label>
                                    <input type="number" class="form-control" id="ttAddCompShip" placeholder="0.00" step="0.01" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Product Title</strong></label>
                                    <input type="text" class="form-control" id="ttAddCompTitle" placeholder="Optional">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="text" class="form-control" id="ttAddCompLink" placeholder="https://www.tiktok.com/shop/pdp/...">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><strong>Region</strong></label>
                                    <select class="form-select" id="ttAddCompRegion">
                                        <option value="US" selected>US</option>
                                        <option value="GB">GB</option>
                                        <option value="MY">MY</option>
                                        <option value="PH">PH</option>
                                        <option value="TH">TH</option>
                                        <option value="VN">VN</option>
                                        <option value="ID">ID</option>
                                        <option value="SG">SG</option>
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex align-items-end flex-wrap gap-1">
                                    <button type="submit" class="btn btn-success" id="ttCompSubmitBtn" style="background:#ff0050;border-color:#ff0050;">
                                        <i class="fa fa-plus"></i> <span id="ttCompSubmitBtnText">Add</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="ttCompClearBtn">
                                        <i class="fa fa-undo"></i> Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="ttLmpDataList">
                        <div class="text-center py-5">
                            <div class="spinner-border" role="status" style="color:#ff0050;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sku Link LMP Modal (same as /shein-pricing; shared sku.link.lmp.* routes) --}}
    <div class="modal fade" id="skuLinkLmpModal" tabindex="-1" aria-labelledby="skuLinkLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
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
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => $tiktokPromoChannel])

@endsection

@php
    $ttpCfg = array_merge(
        [
            'dataJson' => '/tiktok-data-json',
            'badgeChart' => '/tiktok-badge-chart-data',
            'metricsHistory' => '/tiktok-metrics-history',
            'saveSprice' => '/tiktok-save-sprice',
            'updateSpriceStatus' => '/tiktok-update-sprice-status',
            'saveNrp' => route('tiktok.save.nrp'),
            'saveLinks' => '/tiktok-save-links',
            // Shared DB-backed column visibility (same endpoint ebay-tabulator-view uses).
            'columnGet' => '/tabulator-column-visibility',
            'columnSet' => '/tabulator-column-visibility',
            'columnChannel' => 'tiktok_pricing',
            'distinctCampaign' => '/tiktok-distinct-campaign-count',
            'summaryChannel' => 'tiktok',
        ],
        $tiktokPricingClientConfig ?? [],
    );
@endphp

@section('script-bottom')
    <script>
        const TTP_CFG = @json($ttpCfg);
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => $tiktokPromoChannel])
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'script'])
        const DEFAULT_TIKTOK_MARGIN_PERCENT = Number(@json($tiktokPercentage ?? 80));
        const DEFAULT_TIKTOK_MARGIN_FACTOR = DEFAULT_TIKTOK_MARGIN_PERCENT / 100;
        // Ads section columns: hidden by default, only show when "Show Ads Columns" btn is clicked
        const ADS_ONLY_COLUMN_FIELDS = ['NR', 'ad_cvr_pct', 'ads_price', 'budget', 'spend', 'ad_sold',
            'ad_clicks', 'acos', 'status', 'campaign_name'
        ];
        // "TT Ship" is a duplicate of Normal Ship. Never show BB Ship.
        const ALWAYS_HIDDEN_COLUMNS = ['out_roas', 'in_roas', 'T Profit', 'TT Ship'];
        let table = null;
        let allTableData = [];
        let blueTriangleFilterActive = false;
        let totalDistinctCampaigns = 0; // from API: COUNT(DISTINCT campaign_name) in tiktok_campaign_reports
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let samePriceModeActive = false;
        let selectedSkus = new Set();

        // Per-SKU Price chart (ebay-tabulator-view pattern)
        let skuMetricsChart = null;
        let skuChartFirstSeriesStats = null;
        let currentSku = null;
        let currentSkuChartMetric = 'price';

        // ── Sku Link LMP (mirrors /shein-pricing; shared sku.link.lmp.* routes) ──
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
            const isParent = ttIsParentRow(row);
            if (isParent) return '';
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
            const isParent = ttIsParentRow(row);
            if (isParent) return '';
            const rowSku = rowSkuForLinkLmp(row);
            if (!rowSku) return '';
            return `<div class="d-flex align-items-center justify-content-center py-1">
                <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn" title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}"><i class="fas fa-plus"></i></button>
            </div>`;
        }

        function applyAffectedLinkedSkuRows(affected) {
            if (!table || !Array.isArray(affected)) return;
            table.replaceData();
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

        function initSkuLinkLmpModal() {
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
                document.querySelectorAll('.sku-link-lmp-suggestion-cb').forEach(function (cb) {
                    if (cb.value === btn.dataset.sku) cb.checked = false;
                });
                updateLinkedSkuSelectedSummary();
            });
            document.getElementById('sku-link-lmp-save-btn')?.addEventListener('click', function () { saveLinkedSkuFromModal(); });
        }

        /** Std Prc vs channel price: reduce / hold / increase → red / yellow / green. */
        function ttStdPrcChangeDotMeta(stdPrc, comparePrice) {
            const sp = parseFloat(stdPrc);
            const ap = parseFloat(comparePrice);
            if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
            const sp2 = sp.toFixed(2);
            const ap2 = ap.toFixed(2);
            if (parseFloat(sp2) < parseFloat(ap2)) {
                return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs channel price' };
            }
            if (parseFloat(sp2) > parseFloat(ap2)) {
                return { kind: 'increase', color: '#28a745', title: 'Increase vs channel price' };
            }
            return null;
        }

        function ttStdPrcChangeDotHtml(stdPrc, comparePrice) {
            const meta = ttStdPrcChangeDotMeta(stdPrc, comparePrice);
            if (!meta) return '';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
        }

        function applyTtStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            applyTtStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) return;

            const toast = document.createElement('div');
            toast.className =
                `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
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

        function getRowMarginFactor(rowData) {
            const rowMarginFactor = Number(rowData?.percentage);
            return Number.isFinite(rowMarginFactor) && rowMarginFactor > 0 ?
                rowMarginFactor :
                DEFAULT_TIKTOK_MARGIN_FACTOR;
        }

        /** GROI standard as 3 colors: red <60, gray 60-90, green >=90 (yellow->gray, pink->green). */
        function ttRoiVisualBand(v) {
            if (window.MetricPctColors) {
                const band = MetricPctColors.groiBand(v);
                if (band === 'red') return 'red';
                if (band === 'green' || band === 'pink') return 'green';
                return 'gray';
            }
            const n = parseFloat(v);
            if (!isFinite(n)) return 'gray';
            if (n < 60) return 'red';
            if (n < 90) return 'gray';
            return 'green';
        }
        function ttRoiBandColors(band) {
            if (band === 'red') return { bg: '#dc3545', fg: '#fff', text: '#dc3545' };
            if (band === 'green') return { bg: '#28a745', fg: '#fff', text: '#28a745' };
            return { bg: '#6c757d', fg: '#fff', text: '#6c757d' };
        }
        function applyTtRoiBadgeStyle(avgRoi, hasCogs) {
            const $el = $('#roi-percent-badge');
            if (!$el.length) return;
            const band = (hasCogs && isFinite(avgRoi)) ? ttRoiVisualBand(avgRoi) : 'gray';
            const c = ttRoiBandColors(band);
            $el.css({ backgroundColor: c.bg, color: c.fg }).removeClass('bg-secondary bg-danger bg-success');
        }

        function ttIsParentRow(data) {
            if (!data) return false;
            if (data.is_parent_summary === true || data.is_parent === true || data.is_parent_row === true) return true;
            const sku = String(data['(Child) sku'] || data.Child_sku || data.sku || '');
            const parent = String(data.Parent || data.parent || '');
            return sku.toUpperCase().indexOf('PARENT ') === 0 || parent.toUpperCase().indexOf('PARENT ') === 0;
        }
        function ttListingViews(data) {
            if (!data) return 0;
            const stored = parseFloat(data.t_views);
            if (Number.isFinite(stored) && stored > 0) return stored;
            const video = parseInt(data.video_views, 10) || parseInt(data.views, 10) || 0;
            return video
                + (parseInt(data.ads_views, 10) || 0)
                + (parseInt(data.affl_views, 10) || 0);
        }
        function ttListingCvr(data) {
            if (!data) return 0;
            const raw = (data.cvr != null && data.cvr !== '' && data.cvr !== '-')
                ? data.cvr
                : data['CVR%'];
            const stored = parseFloat(raw);
            if (Number.isFinite(stored) && raw !== '' && raw !== '-') {
                return stored;
            }
            const views = ttListingViews(data);
            const sold = parseFloat(data['TT L30']) || 0;
            return views > 0 ? (sold / views) * 100 : 0;
        }
        function ttLivePrice(data) {
            return parseFloat(data && (data['TT Price'] != null ? data['TT Price'] : data.Price)) || 0;
        }
        function ttRowSpriceForAlert(data) {
            let sprice = parseFloat(data && data.SPRICE) || 0;
            if (typeof chPromoLiveSprice === 'function' && !ttIsParentRow(data)) {
                const calc = chPromoLiveSprice(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function ttHasBlueTriangle(data) {
            if (ttIsParentRow(data)) return false;
            const sprice = ttRowSpriceForAlert(data);
            const price = ttLivePrice(data);
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncTtTriangleBadgeState() {
            $('#tiktok-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

        // ---- Edit Links (Buyer / Seller) ----
        function ttLinksNotify(msg, type) {
            if (window.toastr) {
                if (type === 'error' || type === 'danger') toastr.error(msg);
                else if (type === 'warning') toastr.warning(msg);
                else toastr.success(msg);
                return;
            }
            let c = document.getElementById('ttLinksToastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'ttLinksToastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(c);
            }
            const t = document.createElement('div');
            const bg = (type === 'error' || type === 'danger') ? '#dc3545' : (type === 'warning' ? '#fd7e14' : '#198754');
            t.style.cssText = 'min-width:220px;max-width:340px;color:#fff;background:' + bg + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            t.textContent = msg;
            c.appendChild(t);
            requestAnimationFrame(function() { t.style.opacity = '1'; });
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2600);
        }
        let tiktokEditLinksRow = null;
        window.openTiktokEditLinksModal = function(row) {
            tiktokEditLinksRow = row;
            const d = row.getData();
            $('#tiktokEditLinksSku').text(d['(Child) sku'] || '');
            $('#tiktokSellerLinkInput').val(d['S Link'] || '');
            $('#tiktokBuyerLinkInput').val(d['B Link'] || '');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('tiktokEditLinksModal')).show();
        };
        $(document).on('click', '#tiktokSaveLinksBtn', function() {
            if (!tiktokEditLinksRow) return;
            const sku = tiktokEditLinksRow.getData()['(Child) sku'];
            const sellerLink = $('#tiktokSellerLinkInput').val().trim();
            const buyerLink = $('#tiktokBuyerLinkInput').val().trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: (typeof TTP_CFG !== 'undefined' && TTP_CFG.saveLinks) ? TTP_CFG.saveLinks : '/tiktok-save-links',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sku: sku,
                    seller_link: sellerLink,
                    buyer_link: buyerLink
                },
                success: function(res) {
                    if (res && res.success) {
                        tiktokEditLinksRow.update({
                            'S Link': res.seller_link || '',
                            'B Link': res.buyer_link || ''
                        }).then(function() {
                            tiktokEditLinksRow.reformat();
                        }).catch(function() {
                            tiktokEditLinksRow.reformat();
                        });
                        ttLinksNotify('Links saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('tiktokEditLinksModal')).hide();
                    } else {
                        ttLinksNotify((res && res.message) || 'Failed to save links', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                    ttLinksNotify(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $(document).ready(function() {
            initSkuLinkLmpModal();
            let ttAllSkuRows = [];
            let ttBadgeChartInstance = null;
            let ttBadgeChartDays = 30;
            let ttBadgeChartMetricKey = '';
            let ttBadgeChartAjax = null;
            const ttBadgeDollarMetrics = ['total_sales', 'avg_price', 'total_pft', 'total_cogs', 'total_spend_30', 'total_spend_1', 'total_gmv_ad_sales_l30', 'total_gmv_ad_sales_l1', 'total_gmv_spend_l30', 'total_gmv_spend_l1', 'total_gmv_budget'];
            const ttBadgePercentMetrics = ['avg_gpft', 'avg_roi', 'avg_dil', 'ads_cvr_30', 'ads_acos_pct'];
            const ttBadgeRoasMetrics = ['ads_roas', 'avg_target_roas'];
            const ttBadgeMetricLabels = {
                total_sales: 'Sales',
                total_pft: 'Profit',
                avg_gpft: 'GPFT',
                avg_price: 'Price',
                total_l30: 'L30',
                avg_roi: 'ROI%',
                avg_dil: 'Avg DIL%',
                total_cogs: 'COGS',
                zero_sold_count: '0 Sold',
                sold_count: '> 0 Sold',
                total_spend_30: 'Spend 30',
                total_spend_1: 'Spend 1',
                total_ads_views_30: 'adsViews 30',
                total_ads_clicks_30: 'ads Clicks 30',
                total_ads_views_1: 'ads view1',
                total_ads_clicks_1: 'ads clicks 1',
                ads_cvr_30: 'ads CVR 30',
                ads_roas: 'ROAS',
                avg_target_roas: 'Target ROAS',
                ads_acos_pct: 'Acos%',
                total_gmv_ad_sold_l30: 'GMV Ad sold L30',
                total_gmv_ad_sold_l1: 'GMV Ad sold L1',
                total_gmv_ad_sales_l30: 'GMV Ad sales L30',
                total_gmv_ad_sales_l1: 'GMV Ad sales L1',
                total_gmv_spend_l30: 'GMV Spend L30',
                total_gmv_spend_l1: 'GMV Spend L1',
                total_gmv_budget: 'GMV Budget',
            };

            function ttFormatChartValue(v) { 
                const num = Number(v) || 0;
                if (ttBadgeDollarMetrics.includes(ttBadgeChartMetricKey)) return '$' + Math.round(num)
                    .toLocaleString('en-US');
                if (ttBadgePercentMetrics.includes(ttBadgeChartMetricKey)) return num.toFixed(1) + '%';
                if (ttBadgeRoasMetrics.includes(ttBadgeChartMetricKey)) return num.toFixed(2);
                return Math.round(num).toLocaleString('en-US');
            }

            function ttBadgeChartBrand() {
                return (TTP_CFG.summaryChannel === 'tiktok2') ? 'TikTok 2' : 'TikTok';
            }

            function ttBadgeChartModalTitle() {
                const label = ttBadgeMetricLabels[ttBadgeChartMetricKey] || ttBadgeChartMetricKey;
                return `${ttBadgeChartBrand()} — ${label} (Daily snapshot)`;
            }

            function openTtBadgeChartModal(metricKey, opts) {
                const resetDays = !(opts && opts.keepRange);
                ttBadgeChartMetricKey = metricKey;
                if (resetDays) {
                    ttBadgeChartDays = 30;
                    $('#ttBadgeChartRangeSelect').val('30');
                }
                $('#ttBadgeChartModalTitle').text(ttBadgeChartModalTitle());
                bootstrap.Modal.getOrCreateInstance(document.getElementById('ttBadgeChartModal')).show();
                loadTtBadgeChart();
            }

            function renderTtBadgeChart(points) {
                if (!Array.isArray(points) || !points.length) return false;
                const labels = points.map(p => p.date);
                const values = points.map(p => Number(p.value) || 0);
                const sorted = [...values].sort((a, b) => a - b);
                const min = sorted[0];
                const max = sorted[sorted.length - 1];
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                $('#ttBadgeChartHighest').text(ttFormatChartValue(max));
                $('#ttBadgeChartMedian').text(ttFormatChartValue(median));
                $('#ttBadgeChartLowest').text(ttFormatChartValue(min));

                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#28a745' : '#6c757d');
                });
                const pointLabelColors = values.map(function(v) {
                    return v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d';
                });

                const medianLinePlugin = {
                    id: 'ttBadgeMedianLine',
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
                    id: 'ttBadgeValueLabels',
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
                            const val = dataset.data[i];
                            const txt = ttFormatChartValue(val);
                            const offsetY = (i % 2 === 0) ? -8 : -16;
                            const py = point.y + offsetY;
                            c.lineJoin = 'round';
                            c.lineWidth = 3;
                            c.strokeStyle = 'rgba(255,255,255,0.92)';
                            c.strokeText(txt, point.x, py);
                            c.fillStyle = pointLabelColors[i] || '#0f172a';
                            c.fillText(txt, point.x, py);
                        });
                        c.restore();
                    }
                };

                const canvas = document.getElementById('ttBadgeChartCanvas');
                if (!canvas || typeof Chart === 'undefined') return false;
                if (ttBadgeChartInstance) ttBadgeChartInstance.destroy();

                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const range = dataMax - dataMin || 1;
                const yMin = Math.max(0, dataMin - range * 0.1);
                const yMax = dataMax + range * 0.1;

                ttBadgeChartInstance = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: ttBadgeMetricLabels[ttBadgeChartMetricKey] ||
                                ttBadgeChartMetricKey,
                            data: values,
                            borderColor: '#06b6d4',
                            backgroundColor: 'rgba(6, 182, 212, 0.12)',
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2,
                            tension: 0.25,
                            fill: true
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 22, left: 2, right: 2, bottom: 2 } },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: {
                                    callback: function(value) {
                                        return ttFormatChartValue(value);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 30,
                                    font: { size: 8 }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return (ttBadgeMetricLabels[ttBadgeChartMetricKey] || 'Value') +
                                            ': ' + ttFormatChartValue(ctx.parsed.y);
                                    }
                                }
                            }
                        }
                    }
                });
                return true;
            }

            function loadTtBadgeChart() {
                if (!ttBadgeChartMetricKey) return;
                if (ttBadgeChartAjax) ttBadgeChartAjax.abort();
                $('#ttBadgeChartNoData').hide();
                $('#ttBadgeChartContainer').hide();
                $('#ttBadgeChartLoading').show();

                ttBadgeChartAjax = $.ajax({
                    url: TTP_CFG.badgeChart,
                    method: 'GET',
                    data: {
                        metric: ttBadgeChartMetricKey,
                        days: ttBadgeChartDays,
                        channel: TTP_CFG.summaryChannel
                    },
                    success: function(res) {
                        ttBadgeChartAjax = null;
                        $('#ttBadgeChartLoading').hide();
                        const points = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (renderTtBadgeChart(points)) {
                            $('#ttBadgeChartContainer').css({
                                display: 'flex',
                                flexDirection: 'row',
                                alignItems: 'stretch'
                            }).show();
                        } else {
                            $('#ttBadgeChartNoData').show();
                        }
                    },
                    error: function() {
                        ttBadgeChartAjax = null;
                        $('#ttBadgeChartLoading').hide();
                        $('#ttBadgeChartNoData').show();
                    }
                });
            }

            $(document).on('click', '.tt-badge-chart', function(e) {
                e.stopPropagation();
                const m = $(this).data('metric');
                if (m) {
                    openTtBadgeChartModal(m, { keepRange: false });
                }
            });

            $(document).on('change', '#ttBadgeChartRangeSelect', function() {
                const raw = $(this).val();
                const days = raw === '0' ? 0 : (parseInt(raw, 10) || 30);
                if (days === ttBadgeChartDays) return;
                ttBadgeChartDays = days;
                $('#ttBadgeChartModalTitle').text(ttBadgeChartModalTitle());
                loadTtBadgeChart();
            });

            // ── Per-SKU Price chart (same UX as /ebay-tabulator-view) ──
            function skuChartFmtVal(v) {
                return '$' + (Number(v) === v && v % 1 !== 0 ? v.toFixed(2) : Math.round(v).toLocaleString('en-US'));
            }

            function initSkuMetricsChart() {
                const canvas = document.getElementById('skuMetricsChart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                if (skuMetricsChart) {
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
                        const valueFmt = (skuChartFirstSeriesStats && skuChartFirstSeriesStats.valueFmt)
                            ? skuChartFirstSeriesStats.valueFmt
                            : skuChartFmtVal;
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
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 30,
                                    font: { size: 8 }
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                ticks: {
                                    font: { size: 9 },
                                    callback: function(v) {
                                        return '$' + (Number(v) === v && v % 1 !== 0
                                            ? v.toFixed(2)
                                            : Math.round(v).toLocaleString('en-US'));
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
                const historyUrl = (TTP_CFG && TTP_CFG.metricsHistory)
                    ? TTP_CFG.metricsHistory
                    : '/tiktok-metrics-history';
                fetch(`${historyUrl}?days=${daysNum}&sku=${encodeURIComponent(sku)}`)
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
                            if (hEl) {
                                hEl.textContent = fmt(high);
                                hEl.style.color = high === 0 ? refGreen : high > 0 ? refRed : refGray;
                            }
                            if (mEl) {
                                mEl.textContent = fmt(med);
                                mEl.style.color = med === 0 ? refGreen : med > 0 ? refRed : refGray;
                            }
                            if (lEl) {
                                lEl.textContent = fmt(low);
                                lEl.style.color = low === 0 ? refGreen : low > 0 ? refRed : refGray;
                            }
                        }
                        function statsForArr(arr) {
                            const valid = arr.filter(v => v != null && !isNaN(v));
                            if (valid.length === 0) return { min: 0, max: 0, median: 0 };
                            const min = Math.min(...valid);
                            const max = Math.max(...valid);
                            const sorted = [...valid].sort((a, b) => a - b);
                            const mid = Math.floor(sorted.length / 2);
                            const median = sorted.length % 2 !== 0
                                ? sorted[mid]
                                : (sorted[mid - 1] + sorted[mid]) / 2;
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
                        const values = data.map(d => Number(d.price) || 0);
                        const refDotEl = document.getElementById('skuChartRefDot');
                        const refLabelEl = document.getElementById('skuChartRefLabel');
                        if (refLabelEl) refLabelEl.textContent = 'Price';
                        if (refDotEl) refDotEl.style.background = '#adb5bd';

                        skuMetricsChart.data.labels = labels;
                        skuMetricsChart.data.datasets[0].data = values;
                        skuMetricsChart.data.datasets[0].label = 'Price (USD)';
                        skuMetricsChart.data.datasets[0].borderColor = '#adb5bd';
                        skuMetricsChart.data.datasets[0].backgroundColor = 'rgba(108,117,125,0.08)';

                        if (skuMetricsChart.options.scales && skuMetricsChart.options.scales.y) {
                            skuMetricsChart.options.scales.y.ticks.callback = function(v) {
                                return '$' + (Number(v) === v && v % 1 !== 0
                                    ? v.toFixed(2)
                                    : Math.round(v).toLocaleString('en-US'));
                            };
                        }
                        if (skuMetricsChart.options.plugins && skuMetricsChart.options.plugins.tooltip &&
                            skuMetricsChart.options.plugins.tooltip.callbacks) {
                            skuMetricsChart.options.plugins.tooltip.callbacks.label = function(context) {
                                return 'Price: ' + skuChartFmtVal(context.parsed.y || 0);
                            };
                        }

                        const s0 = statsForArr(values);
                        setSkuRefCol(0, s0.max, s0.median, s0.min, skuChartFmtVal);

                        const refRed = '#dc3545';
                        const refGray = '#6c757d';
                        const dotColors = values.map((v, i) => {
                            if (i === 0) return refGray;
                            return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
                        });
                        skuChartFirstSeriesStats = {
                            values,
                            median: s0.median,
                            dataMin: s0.min,
                            dataMax: s0.max,
                            dotColors,
                            valueFmt: skuChartFmtVal
                        };
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

            initSkuMetricsChart();

            $(document).on('click', '.view-sku-chart', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const el = this;
                const sku = el.getAttribute('data-sku');
                if (!sku) return;
                currentSkuChartMetric = el.getAttribute('data-metric') || 'price';
                currentSku = sku;
                const channelLabel = (TTP_CFG.summaryChannel === 'tiktok2') ? 'TikTok 2' : 'TikTok';
                $('#skuChartModalTitle').html(
                    channelLabel + ' - <span id="modalSkuName">' + $('<div>').text(sku).html() + '</span> - Metrics'
                );
                $('#sku-chart-days-filter').val('30');
                $('#skuChartModalSuffix').text('Price (Rolling L30 · PT)');
                $('#skuChartLoading').show();
                $('#skuChartContainer').hide();
                $('#chart-no-data-message').hide();
                if (!skuMetricsChart) initSkuMetricsChart();
                loadSkuMetricsData(sku, 30);
                const modalEl = document.getElementById('skuMetricsModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    $('#skuMetricsModal').modal('show');
                }
            });

            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                const daysNum = parseInt(days, 10);
                const rangeLabel = daysNum === 0 ? 'Lifetime' : 'L' + daysNum;
                $('#skuChartModalSuffix').text('Price (Rolling ' + rangeLabel + ' · PT)');
                if (currentSku) loadSkuMetricsData(currentSku, daysNum || 0);
            });

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

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

            // Single Price Mode cycle: Off → Decrease → Increase → Same Price → Off
            // Select column stays visible at all times (ebay-tabulator-view pattern);
            // Price Mode only swaps the button label / discount panel state.
            function syncPriceModeUi() {
                const $btn = $('#price-mode-btn');
                if (decreaseModeActive) {
                    $btn.removeClass('btn-warning btn-secondary btn-primary btn-info').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i>')
                        .attr('title', 'Decrease ON — click to cycle');
                    syncDiscountInputUi();
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-warning btn-secondary btn-danger btn-info').addClass('btn-primary')
                        .html('<i class="fas fa-arrow-up"></i>')
                        .attr('title', 'Increase ON — click to cycle');
                    syncDiscountInputUi();
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-warning btn-secondary btn-danger btn-primary').addClass('btn-info')
                        .html('<i class="fas fa-equals"></i>')
                        .attr('title', 'Same Price ON — click to cycle');
                    syncDiscountInputUi();
                    return;
                }
                $btn.removeClass('btn-danger btn-primary btn-info btn-secondary').addClass('btn-warning')
                    .html('PRc');
                selectedSkus.clear();
                updateSelectedCount();
                syncDiscountInputUi();
            }

            $('#price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    decreaseModeActive = true;  increaseModeActive = false; samePriceModeActive = false;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = true;  samePriceModeActive = false;
                } else if (increaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = true;
                } else {
                    decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = false;
                }
                syncPriceModeUi();
            });

            // Toggle Utilized Columns - Show only columns that match tiktok/utilized page (like temu-decrease Show Ads Columns)
            let utilizedColumnsVisible = false;
            let originalColumnVisibilityUtilized = {};
            const utilizedColumnFields = ['(Child) sku', 'INV', 'L30', 'TT Dil%', 'TT L30', 'cvr', 'NR',
                'variation_req', 'video_req', 'video_uploaded', 'nrp', 'ad_cvr_pct', 'ads_price', 'budget', 'spend',
                'ad_sold', 'ad_clicks', 'acos', 'status', 'campaign_name'
            ];

            $('#toggle-utilized-columns-btn').on('click', function() {
                utilizedColumnsVisible = !utilizedColumnsVisible;

                if (utilizedColumnsVisible) {
                    table.getColumns().forEach(function(column) {
                        const field = column.getField();
                        if (field) {
                            originalColumnVisibilityUtilized[field] = column.isVisible();
                        }
                    });
                    table.getColumns().forEach(function(column) {
                        const field = column.getField();
                        if (field && !utilizedColumnFields.includes(field)) {
                            column.hide();
                        } else if (field && utilizedColumnFields.includes(field)) {
                            column
                        .show(); // show by iterating so hidden columns (e.g. ads_price) are found
                        }
                    });
                    $(this).html('<i class="fa fa-filter"></i> Show All Columns');
                    $(this).removeClass('btn-secondary btn-primary').addClass('btn-danger');
                    $('#utilized-count-section').removeClass('d-none');
                    $('#summary-stats').addClass('d-none');
                    updateUtilizedCounts();
                } else {
                    // Restore by iterating stored keys (getColumns() may only return visible columns when some are hidden)
                    Object.keys(originalColumnVisibilityUtilized).forEach(function(field) {
                        try {
                            const column = table.getColumn(field);
                            if (column) {
                                if (originalColumnVisibilityUtilized[field]) {
                                    column.show();
                                } else {
                                    column.hide();
                                }
                            }
                        } catch (e) {
                            console.log('Restore column not found: ' + field);
                        }
                    });
                    $(this).html('<i class="fa fa-filter"></i> Show Ads Columns');
                    $(this).removeClass('btn-danger btn-primary').addClass('btn-secondary');
                    $('#utilized-count-section').addClass('d-none');
                    $('#summary-stats').removeClass('d-none');
                    adsBadgeFilter = null;
                    $('#utilized-count-section .ads-section-badge').removeClass(
                        'border border-3 border-dark');
                    applyFilters();
                }
            });

            // Select all checkbox handler
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                const filteredData = table.getData('active').filter(row => !ttIsParentRow(row));

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

            // Clear SPRICE button
            $('#clear-sprice-btn').on('click', function() {
                clearSpriceForSelected();
            });

            /*
             * ============================================================================
             * Target ROI% / Target GPFT% bulk apply for SPRICE (mirrors ebay-tabulator-view)
             * ----------------------------------------------------------------------------
             * Pick rows (via Price Mode), type the target %, click Apply SPRICE → back-solve
             * a sale price that makes the on-page SROI / SGPFT column match the target after
             * TikTok margin + shipping are paid out.
             *
             * Math (mirrors the backend's SGPFT / SROI formulas):
             *   SROI%  = ((sprice * margin - lp - ship) / lp) * 100
             *      -> sprice = (lp * (1 + roi%/100) + ship) / margin
             *
             *   SGPFT% = ((sprice * margin - ship - lp) / sprice) * 100
             *      -> sprice = (lp + ship) / (margin - gpft%/100)
             *      Constraint: (margin - gpft%/100) must be > 0.
             *
             * `margin` comes from rowData.percentage with a DEFAULT_TIKTOK_MARGIN_FACTOR fallback.
             * Saving goes through TTP_CFG.saveSprice exactly like an inline SPRICE edit.
             * ============================================================================
             */
            function ttApplyTargetSpriceBatch(opts) {
                const $btn = opts.$btn;
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU first.', 'error');
                    return;
                }

                const rowsToProcess = [];
                const skipped = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (ttIsParentRow(rd)) return;
                    const res = opts.computeSprice(rd);
                    if (!res || res.skipReason) {
                        if (res && res.skipReason) skipped.push({ sku: sku, reason: res.skipReason });
                        return;
                    }
                    let sprice = +Number(res.sprice).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    sprice = Math.max(0.99, roundToRetailPrice(sprice));
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    if (skipped.length > 0) {
                        showToast('Cannot apply: ' + skipped[0].reason, 'error');
                    } else {
                        showToast('No selected rows have a usable LP > 0', 'error');
                    }
                    return;
                }

                let confirmMsg = `Compute & save SPRICE for ${rowsToProcess.length} selected SKU(s) using ${opts.label}?`;
                if (skipped.length > 0) {
                    confirmMsg += `\n\nNote: ${skipped.length} row(s) will be skipped (${skipped[0].reason}).`;
                }
                if (!confirm(confirmMsg)) return;

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                // Update rows client-side immediately (mirrors applyDiscount handler)
                const updates = [];
                rowsToProcess.forEach(function(item) {
                    const rd = item.row.getData();
                    const percentage = getRowMarginFactor(rd);
                    const lp = parseFloat(rd['LP_productmaster']) || 0;
                    const ship = parseFloat(rd['Ship_productmaster']) || 0;
                    const sprice = item.sprice;
                    const sgpft = sprice > 0 ? Math.round(((sprice * percentage - ship - lp) / sprice) * 100 * 100) / 100 : 0;
                    const sroi = lp > 0 ? Math.round(((sprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                    item.row.update({
                        SPRICE: sprice,
                        SGPFT: sgpft,
                        SPFT: sgpft,
                        SROI: sroi,
                        has_custom_sprice: true
                    });
                    updates.push({ sku: item.sku, sprice: sprice });
                });

                saveSpriceUpdates(updates, {
                    success: function(res) {
                        if (res && res.success) {
                            showToast(`SPRICE saved for ${updates.length} SKU(s) @ ${opts.label}`, 'success');
                        } else {
                            showToast('Failed to save SPRICE updates', 'error');
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(opts.btnHtml);
                        selectedSkus.clear();
                        $('.sku-select-checkbox').prop('checked', false);
                        $('#select-all-checkbox').prop('checked', false);
                        updateSelectedCount();
                    }
                });
            }

            // Target ROI%
            $('#tt-apply-target-roi-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#tt-target-roi-input').val();
                const targetRoiPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('Please enter a Target ROI%', 'error'); return; }
                if (!isFinite(targetRoiPct)) { showToast('Target ROI% must be a number', 'error'); return; }
                const roiMultiplier = 1 + (targetRoiPct / 100);
                ttApplyTargetSpriceBatch({
                    targetPct: targetRoiPct,
                    label: `Target ROI ${targetRoiPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-bullseye"></i>',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd['LP_productmaster']) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd['Ship_productmaster']) || 0;
                        const margin = getRowMarginFactor(rd);
                        return { sprice: (lp * roiMultiplier + ship) / margin };
                    }
                });
            });
            $('#tt-target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#tt-apply-target-roi-btn').click();
            });

            // Target GPFT%
            $('#tt-apply-target-gpft-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#tt-target-gpft-input').val();
                const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { showToast('Please enter a Target GPFT%', 'error'); return; }
                if (!isFinite(targetGpftPct)) { showToast('Target GPFT% must be a number', 'error'); return; }
                const targetFraction = targetGpftPct / 100;
                ttApplyTargetSpriceBatch({
                    targetPct: targetGpftPct,
                    label: `Target GPFT ${targetGpftPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-bullseye"></i>',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd['LP_productmaster']) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd['Ship_productmaster']) || 0;
                        const margin = getRowMarginFactor(rd);
                        const denom = margin - targetFraction;
                        if (denom <= 0) {
                            return { skipReason: `Target GPFT% ${targetGpftPct}% ≥ TikTok take-home margin (~${Math.round(margin * 100)}%)` };
                        }
                        return { sprice: (lp + ship) / denom };
                    }
                });
            });
            $('#tt-target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#tt-apply-target-gpft-btn').click();
            });

            let zeroSoldFilterActive = false;
            let moreSoldFilterActive = false;
            let priceGtLmpFilterActive = false;
            let priceLt80LmpFilterActive = false;
            let adsBadgeFilter = null;

            function ttDismissBadgeChartModal() {
                const modalEl = document.getElementById('ttBadgeChartModal');
                if (!modalEl) return;
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }

            function updateTtSummaryBadgeStyles() {
                const badges = [{
                    active: zeroSoldFilterActive,
                    sel: '#zero-sold-count-badge',
                    glow: 'rgba(220, 53, 69, 0.8)'
                }, {
                    active: moreSoldFilterActive,
                    sel: '#more-sold-count-badge',
                    glow: 'rgba(14, 165, 233, 0.75)'
                }];
                badges.forEach(function(b) {
                    const $el = $(b.sel);
                    if (!$el.length) return;
                    if (b.active) {
                        $el.css('opacity', '1').css('box-shadow', '0 0 10px ' + b.glow).addClass(
                            'border border-3 border-dark');
                    } else {
                        $el.css('opacity', '').css('box-shadow', 'none').removeClass(
                            'border border-3 border-dark');
                    }
                });
            }

            function ttClearSummaryBadgeFilters() {
                zeroSoldFilterActive = false;
                moreSoldFilterActive = false;
            }

            function ttOnSummaryFilterBadgeClick(type) {
                ttDismissBadgeChartModal();
                if (type === 'zero-sold') {
                    zeroSoldFilterActive = !zeroSoldFilterActive;
                    moreSoldFilterActive = false;
                } else if (type === 'more-sold') {
                    moreSoldFilterActive = !moreSoldFilterActive;
                    zeroSoldFilterActive = false;
                }
                adsBadgeFilter = null;
                $('#utilized-count-section .ads-section-badge').removeClass('border border-3 border-dark');
                applyFilters();
                updateTtSummaryBadgeStyles();
            }

            $('#zero-sold-count-badge').on('click', function(e) {
                e.stopPropagation();
                ttOnSummaryFilterBadgeClick('zero-sold');
            });
            $('#more-sold-count-badge').on('click', function(e) {
                e.stopPropagation();
                ttOnSummaryFilterBadgeClick('more-sold');
            });

            // Ads section badge filter (like tiktok utilized page) - toggle on click
            $(document).on('click', '.ads-section-badge', function() {
                const filter = $(this).data('ads-filter');
                adsBadgeFilter = (adsBadgeFilter === filter) ? null : filter;
                $('#utilized-count-section .ads-section-badge').removeClass('border border-3 border-dark');
                if (adsBadgeFilter) {
                    $('#utilized-count-section .ads-section-badge[data-ads-filter="' + adsBadgeFilter +
                        '"]').addClass('border border-3 border-dark');
                }
                ttClearSummaryBadgeFilters();
                applyFilters();
                updateTtSummaryBadgeStyles();
                if (typeof updateUtilizedCounts === 'function') updateUtilizedCounts();
            });

            // ========== MANUAL DROPDOWN FUNCTIONALITY ==========
            // Update selected count display
            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#tt-selected-row-badge').text(`Sel: ${count}`);
                $('#discount-input-container').toggle(count > 0);
                $('#tt-bulk-actions-container').toggle(count > 0);
                const channelLabel = (TTP_CFG.summaryChannel === 'tiktok2') ? 'TikTok 2' : 'TikTok';
                $('#bulkPushTiktokLabel').text(channelLabel);
            }

            // Visible (filtered) SKU row count — same as eBay / Amazon rows-count badge
            function updateRowsCountBadge() {
                if (!table) {
                    $('#tt-rows-count-badge').text('Rows: 0');
                    return;
                }
                const visibleRowCount = table.getData('active').filter(row => !ttIsParentRow(row)).length;
                $('#tt-rows-count-badge').text('Rows: ' + visibleRowCount.toLocaleString());
            }

            // Update select all checkbox state
            function updateSelectAllCheckbox() {
                if (!table) return;

                const filteredData = table.getData('active').filter(row => !ttIsParentRow(row));

                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }

                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
                const allFilteredSelected = filteredSkus.size > 0 && [...filteredSkus].every(sku => selectedSkus
                    .has(sku));

                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }

            // Custom price rounding function
            function roundToRetailPrice(price) {
                // Don't round if price is below 20.99
                if (price < 20.99) {
                    return price;
                }
                const roundedDollar = Math.ceil(price);
                return roundedDollar - 0.01;
            }

            // Apply discount to selected SKUs
            function applyDiscount() {
                const discountType = $('#discount-type-select').val();
                const discountValue = parseFloat($('#discount-percentage-input').val());

                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
                    return;
                }
                if (isNaN(discountValue) || discountValue <= 0) {
                    showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid discount value', 'error');
                    return;
                }

                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                let updatedCount = 0;
                const updates = [];

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows("(Child) sku", "=", sku);

                    if (rows.length > 0) {
                        const row = rows[0];
                        const rowData = row.getData();
                        const currentPrice = parseFloat(rowData['TT Price']) || 0;

                        // Same Price applies even when TT Price is empty;
                        // Decrease / Increase still need a positive TT Price to compute.
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

                            newSprice = roundToRetailPrice(newSprice);
                            newSprice = Math.max(0.99, newSprice);

                            const percentage = getRowMarginFactor(rowData);
                            const lp = rowData['LP_productmaster'] || 0;
                            const ship = rowData['Ship_productmaster'] || 0;

                            const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) /
                                newSprice) * 100 * 100) / 100 : 0;
                            const spft = sgpft;
                            const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) *
                                100 * 100) / 100 : 0;

                            row.update({
                                SPRICE: newSprice,
                                SGPFT: sgpft,
                                SPFT: spft,
                                SROI: sroi,
                                has_custom_sprice: true
                            });

                            updates.push({
                                sku: sku,
                                sprice: newSprice
                            });

                            updatedCount++;
                        }
                    }
                });

                if (updates.length > 0) {
                    saveSpriceUpdates(updates);
                }

                const action = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Discount');
                const suffix = samePriceModeActive ? '' : ' based on TT Price';
                showToast(`${action} applied to ${updatedCount} SKU(s)${suffix}`, 'success');
                $('#discount-percentage-input').val('');
            }

            // Clear SPRICE for selected SKUs
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

                    if (selectedSkus.has(sku)) {
                        row.update({
                            SPRICE: 0,
                            SGPFT: 0,
                            SPFT: 0,
                            SROI: 0
                        });

                        updates.push({
                            sku: sku,
                            sprice: 0
                        });

                        clearedCount++;
                    }
                });

                if (updates.length > 0) {
                    saveSpriceUpdates(updates);
                }

                showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
            }

            // Save SPRICE updates to backend
            function ttEscHtmlAttr(val) {
                if (val == null || val === '') return '';
                return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            /** NRP save: same client pattern as eBay tabulator KW handlers (application/json + X-CSRF-TOKEN + { sku, field, value }). */
            function ttSaveNrp(data, onSuccess, onFail) {
                onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
                onFail = typeof onFail === 'function' ? onFail : function() {};
                const saveUrl = TTP_CFG.saveNrp;
                if (!saveUrl) {
                    console.error('TTP_CFG.saveNrp is not configured');
                    showToast('NRP save URL missing.', 'error');
                    onFail();
                    return;
                }
                $.ajax({
                    url: saveUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: JSON.stringify({
                        sku: data.sku,
                        field: 'NRP',
                        value: data.value
                    }),
                    success: function(res) {
                        if (res && res.success) {
                            onSuccess();
                        } else {
                            console.warn('NRP not saved:', (res && (res.message || res.error)) || 'unknown');
                            onFail();
                        }
                    },
                    error: function(xhr) {
                        console.error('NRP save failed:', xhr);
                        const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ?
                            xhr.responseJSON.message || xhr.responseJSON.error : 'Error saving NRP.';
                        showToast(msg, 'error');
                        onFail();
                    }
                });
            }

            function saveSpriceUpdates(updates, opts) {
                opts = opts || {};
                if (!updates || !updates.length) return;
                if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
                    chPromoBatchClearThenSave(updates, function(next) {
                        saveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
                    }, {
                        wipeFn: function(zeros) {
                            return $.ajax({
                                url: TTP_CFG.saveSprice,
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: { updates: zeros }
                            });
                        }
                    });
                    return;
                }
                $.ajax({
                    url: TTP_CFG.saveSprice,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        updates: updates
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('SPRICE updates saved successfully:', response.updated,
                                'records');
                            if (response.errors && response.errors.length > 0) {
                                console.warn('Some updates had errors:', response.errors);
                            }
                        }
                        if (typeof opts.success === 'function') opts.success(response);
                    },
                    error: function(xhr) {
                        console.error('Error saving SPRICE updates:', xhr);
                        let errorMessage = 'Error saving SPRICE updates to database';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMessage += ': ' + xhr.responseJSON.error;
                        }
                        showToast(errorMessage, 'error');
                        if (typeof opts.error === 'function') opts.error(xhr);
                    },
                    complete: function() {
                        if (typeof opts.complete === 'function') opts.complete();
                    }
                });
            }

            // Initialize Tabulator
            table = new Tabulator("#tiktok-table", {
                ajaxURL: TTP_CFG.dataJson,
                ajaxResponse: function(url, params, response) {
                    var data = Array.isArray(response) ? response : (response && response.data);
                    if (data && Array.isArray(data)) {
                        data.forEach(function(row) {
                            if (row.is_parent_summary || row.is_parent
                                || (row.Parent && String(row.Parent).startsWith('PARENT '))
                                || (row['(Child) sku'] && String(row['(Child) sku']).startsWith('PARENT '))) {
                                row.is_parent = true;
                                row.is_parent_summary = true;
                                if (!row['(Child) sku'] || row['(Child) sku'] === '') {
                                    row['(Child) sku'] = row.Parent && String(row.Parent).startsWith('PARENT ')
                                        ? row.Parent
                                        : ('PARENT ' + String(row.Parent || '').trim());
                                }
                                if (!row.Child_sku || row.Child_sku === '') row.Child_sku = row['(Child) sku'];
                                if (row.Parent && String(row.Parent).startsWith('PARENT ')) {
                                    row.Parent = String(row.Parent).slice(7).trim();
                                    row.parent = row.Parent;
                                }
                            }
                            const tViews = (parseInt(row.video_views, 10) || parseInt(row.views, 10) || 0)
                                + (parseInt(row.ads_views, 10) || 0)
                                + (parseInt(row.affl_views, 10) || 0);
                            if (row.t_views == null || row.t_views === '' || row.t_views === '-'
                                || (parseFloat(row.t_views) || 0) <= 0) {
                                row.t_views = tViews;
                            }
                            if (row['CVR%'] == null || row['CVR%'] === '' || row['CVR%'] === '-') {
                                const sold = parseFloat(row['TT L30']) || 0;
                                const views = parseFloat(row.t_views) || tViews;
                                row['CVR%'] = views > 0 ? Math.round((sold / views) * 10000) / 100 : 0;
                            }
                            if (row.cvr == null || row.cvr === '' || row.cvr === '-') {
                                row.cvr = row['CVR%'];
                            }
                        });
                        allTableData = data;
                        if (window.ParentExpand) ParentExpand.captureDataset(data);
                        return data;
                    }
                    return response;
                },
                ajaxSorting: false,
                layout: "fitDataStretch",
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
                // No initialSort so backend order is preserved: children then PARENT row after each group (parent SKU visible)
                initialSort: [],
                rowFormatter: function(row) {
                    const d = row.getData();
                    const el = row.getElement();
                    if (ttIsParentRow(d)) {
                        el.style.backgroundColor = '#fffef2';
                        el.style.fontWeight = 'bold';
                        el.style.minHeight = '48px';
                        el.classList.add('parent-row');
                        el.classList.add('tt-parent-row');
                    } else {
                        el.style.backgroundColor = '';
                        el.style.fontWeight = '';
                        el.style.minHeight = '';
                        el.classList.remove('parent-row');
                        el.classList.remove('tt-parent-row');
                    }
                },
                columns: [{
                        title: "<input type='checkbox' id='select-all-checkbox'>",
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        width: 40,
                        frozen: true,
                        visible: true,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (ttIsParentRow(rowData)) return '';
                            const sku = rowData['(Child) sku'] || '';
                            const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                            return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                        }
                    },
                    {
                        title: "Image",
                        field: "image_path",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (ttIsParentRow(rowData) && (!cell.getValue() || cell.getValue() === '-'))
                                return '';
                            const value = cell.getValue();
                            if (value && value !== '-') {
                                const esc = (v) => String(v).replace(/"/g, '&quot;').replace(/</g,
                                    '&lt;');
                                const imgSize = ttIsParentRow(rowData) ? 28 : 50;
                                return `<img src="${esc(value)}" alt="Product" style="width: ${imgSize}px; height: ${imgSize}px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='inline');"><span style="display:none; font-size:10px; color:#999;">No image</span>`;
                            }
                            return '';
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
                            const row = cell.getRow().getData();
                            let s = String(row.Parent != null ? row.Parent : (row.parent || '')).trim();
                            if (s.toUpperCase().indexOf('PARENT ') === 0) s = s.slice(7).trim();
                            if (!s && row['(Child) sku']) {
                                const sku = String(row['(Child) sku']).trim();
                                if (sku.toUpperCase().indexOf('PARENT ') === 0) s = sku.slice(7).trim();
                            }
                            return s || '—';
                        }
                    },
                    (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, frozen: true, headerSort: false }),
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold",
                        tooltip: true,
                        width: 250,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const rowData = row.getData();
                            const cellVal = cell.getValue();
                            const isParentRow = ttIsParentRow(rowData)
                                || (cellVal && String(cellVal).startsWith('PARENT '));
                            const safe = (s) => (s == null ? '' : String(s)).replace(/</g, '&lt;')
                                .replace(/"/g, '&quot;');
                            if (isParentRow) {
                                let text = String(cellVal || rowData['(Child) sku'] || rowData.Child_sku || '');
                                if (text.toUpperCase().indexOf('PARENT ') !== 0) {
                                    const name = String(rowData.Parent || rowData.parent || '').trim();
                                    text = name ? ('PARENT ' + name) : 'PARENT';
                                }
                                return '<span style="font-weight:bold;color:#212529;">' + safe(text) + '</span>';
                            }
                            const sku = cellVal ?? rowData['(Child) sku'] ?? rowData['Child_sku'] ??
                                '';
                            const displaySku = safe(sku);
                            return '<span class="fw-bold">' + displaySku +
                                '</span> <i class="fa fa-copy text-secondary copy-sku-btn" style="cursor:pointer;margin-left:8px;font-size:14px;" data-sku="' +
                                safe(sku) + '" title="Copy SKU"></i>';
                        }
                    },
                    {
                        title: "Links",
                        field: "links_column",
                        width: 55,
                        frozen: true,
                        hozAlign: "center",
                        headerSort: false,
                        tooltip: "Double-click to add / edit links",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const isParent = ttIsParentRow(d);
                            if (isParent) return '';
                            const b = d['B Link'] || '';
                            const s = d['S Link'] || '';
                            let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                            if (s) {
                                html += '<a href="' + String(s).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                            }
                            if (b) {
                                html += '<a href="' + String(b).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                            }
                            if (!s && !b) {
                                html += '<span class="text-muted" style="font-size:12px;">-</span>';
                            }
                            html += '</div>';
                            return html;
                        },
                        cellDblClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            const isParent = ttIsParentRow(d);
                            if (isParent) return;
                            openTiktokEditLinksModal(cell.getRow());
                        }
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
                        field: "TT Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const cellVal = cell.getValue();
                            if (isParent && (cellVal === null || cellVal === undefined ||
                                    cellVal === '' || cellVal === '-'))
                            return '<span style="color:#6c757d;">-</span>';
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;
                            const dilFromCell = parseFloat(cellVal);
                            const dil = (isParent && !isNaN(dilFromCell)) ? dilFromCell : (INV ===
                                0 ? 0 : (OVL30 / INV) * 100);
                            if (isParent && INV === 0 && (cellVal === null || cellVal ===
                                    undefined || cellVal === ''))
                            return '<span style="color:#6c757d;">-</span>';
                            if (INV === 0 && !isParent)
                            return '<span style="color: #6c757d;">0%</span>';
                            let color = '';
                            // Same bands as DIL filter / amazon filter: red <25, green 25-50, pink 50+
                            if (dil < 25) color = '#a00211';
                            else if (dil >= 25 && dil < 50) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "TT L30",
                        field: "TT L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === null || raw === undefined || raw === '' ||
                                    raw === '-')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
                            return `<span style="font-weight: 700;">${value}</span>`;
                        }
                    },
                    {
                        title: "CVR",
                        field: "cvr",
                        hozAlign: "center",
                        sorter: "number",
                        width: 55,
                        headerTooltip: "CVR = TT L30 ÷ T views (video + ads + affl)",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const views = ttListingViews(rowData);
                            const cvr = ttListingCvr(rowData);
                            if (isParent && views === 0 && (cell.getValue() === null ||
                                    cell.getValue() === undefined || cell.getValue() === '' ||
                                    cell.getValue() === '-')) {
                                return '<span style="color:#6c757d;">-</span>';
                            }
                            let color = '#a00211';
                            if (views <= 0) color = '#6c757d';
                            else if (cvr > 3 && cvr <= 7) color = '#ffc107';
                            else if (cvr > 7 && cvr <= 13) color = '#28a745';
                            else if (cvr > 13) color = '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(cvr)}%</span>`;
                        }
                    },
                    {
                        title: "TT Stock",
                        field: "TT Stock",
                        hozAlign: "center",
                        width: 60,
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const raw = cell.getValue();
                            if (ttIsParentRow(rowData) && (
                                    raw === '-' || raw === null || raw === undefined))
                            return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (value === 0) {
                                return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                            }
                            return `<span style="font-weight: 600;">${value}</span>`;
                        }
                    },
                    ...(TTP_CFG.summaryChannel === 'tiktok2' ? [] : [{
                        // Duplicate of Ship_productmaster — kept for data compat, always hidden.
                        title: "TT Ship",
                        field: "TT Ship",
                        hozAlign: "center",
                        sorter: "number",
                        width: 70,
                        visible: false,
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === '-' || raw === null || raw === undefined ||
                                    raw === '')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
                            return `$${value.toFixed(2)}`;
                        }
                    }]),
                    {
                        title: "NRA",
                        field: "NR",
                        hozAlign: "center",
                        width: 70,
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData()['(Child) sku'];
                            const value = (cell.getValue()?.trim()) || 'RA';
                            return `
                            <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-field="NR"
                                style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                            </select>
                        `;
                        }
                    },
                    {
                        title: "Ads CVR (clk)",
                        field: "ad_cvr_pct",
                        hozAlign: "right",
                        width: 110,
                        visible: false,
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const value = cell.getValue();
                            const isParent = ttIsParentRow(rowData);
                            if (value === null || value === undefined || value === '' || value ===
                                '-') return '<span style="color:#6c757d;">-</span>';
                            const pct = parseFloat(value);
                            if (isNaN(pct)) return '<span style="color:#6c757d;">-</span>';
                            return '<span style="font-weight:600;">' + pct.toFixed(2) + '%</span>';
                        }
                    },
                    {
                        title: "Price",
                        field: "ads_price",
                        hozAlign: "right",
                        width: 80,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value > 0 ? '$' + value.toFixed(2) : (value === 0 ?
                                '<span style="color:#999;">0</span>' : '-');
                        }
                    },
                    {
                        title: "Budget",
                        field: "budget",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '')
                            return '<span style="color:#999;">-</span>';
                            return '$' + parseFloat(value).toFixed(2);
                        }
                    },
                    {
                        title: "Spend",
                        field: "spend",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        }
                    },
                    {
                        title: "Ad Sold",
                        field: "ad_sold",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "Ad Clicks",
                        field: "ad_clicks",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        }
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return Math.round(value) + '%';
                        }
                    },
                    {
                        title: "Out ROAS",
                        field: "out_roas",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        }
                    },
                    {
                        title: "In ROAS",
                        field: "in_roas",
                        hozAlign: "right",
                        width: 100,
                        visible: false,
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        }
                    },
                    {
                        title: "Status",
                        field: "status",
                        hozAlign: "center",
                        width: 130,
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData()['(Child) sku'];
                            const value = cell.getValue() || 'Not Created';
                            const colors = {
                                "Active": "#10b981",
                                "Inactive": "#ef4444",
                                "Not Created": "#eab308"
                            };
                            const selectedColor = colors[value] || "#6b7280";
                            return `
                            <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-field="status"
                                style="width: 120px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: 0.875rem; color: ${selectedColor}; font-weight: 500;">
                                <option value="Active" ${value === 'Active' ? 'selected' : ''} style="color: #10b981;">Active</option>
                                <option value="Inactive" ${value === 'Inactive' ? 'selected' : ''} style="color: #ef4444;">Inactive</option>
                                <option value="Not Created" ${value === 'Not Created' ? 'selected' : ''} style="color: #eab308;">Not Created</option>
                            </select>
                        `;
                        }
                    },
                    {
                        title: "Campaign",
                        field: "campaign_name",
                        headerSort: false,
                        width: 200,
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === '')
                        return '<span style="color: #999;">-</span>';
                            return value;
                        }
                    },
                    {
                        title: "Video Views",
                        field: "video_views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const value = parseInt(cell.getValue(), 10) || 0;
                            if (isParent && !cell.getValue())
                            return '<span style="color:#6c757d;">-</span>';
                            return value.toLocaleString();
                        },
                        width: 95
                    },
                    {
                        title: "Ads Views",
                        field: "ads_views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const value = parseInt(cell.getValue(), 10) || 0;
                            if (isParent && !cell.getValue())
                            return '<span style="color:#6c757d;">-</span>';
                            return value.toLocaleString();
                        },
                        width: 90
                    },
                    {
                        title: "Affl Views",
                        field: "affl_views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const value = parseInt(cell.getValue(), 10) || 0;
                            if (isParent && !cell.getValue())
                            return '<span style="color:#6c757d;">-</span>';
                            return value.toLocaleString();
                        },
                        width: 90
                    },
                    {
                        title: "T views",
                        field: "t_views",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            const aTotal = (parseInt(aData.video_views, 10) || 0) + (parseInt(aData
                                .ads_views, 10) || 0) + (parseInt(aData.affl_views, 10) || 0);
                            const bTotal = (parseInt(bData.video_views, 10) || 0) + (parseInt(bData
                                .ads_views, 10) || 0) + (parseInt(bData.affl_views, 10) || 0);
                            return aTotal - bTotal;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const totalViews = ttListingViews(rowData);
                            if (isParent && totalViews === 0)
                            return '<span style="color:#6c757d;">-</span>';
                            return totalViews.toLocaleString();
                        },
                        width: 85
                    },
                    {
                        title: "Spend 30",
                        field: "spend_30",
                        hozAlign: "right",
                        sorter: "number",
                        width: 100,
                        headerTooltip: "L30 Cost from tiktok_campaign_reports (same as /tiktok-1-ads-raw-data).",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "Spend 1",
                        field: "spend_1",
                        hozAlign: "right",
                        sorter: "number",
                        width: 90,
                        headerTooltip: "L1 Cost from tiktok_campaign_reports, or L7 if no L1 row.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "adsViews 30",
                        field: "ads_views_30",
                        hozAlign: "right",
                        sorter: "number",
                        width: 110,
                        headerTooltip: "L30 Product ad impressions from tiktok_campaign_reports.",
                        formatter: function(cell) {
                            return (parseInt(cell.getValue(), 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "ads Clicks 30",
                        field: "ads_clicks_30",
                        hozAlign: "right",
                        sorter: "number",
                        width: 120,
                        headerTooltip: "L30 Product ad clicks from tiktok_campaign_reports.",
                        formatter: function(cell) {
                            return (parseInt(cell.getValue(), 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "ads view1",
                        field: "ads_views_1",
                        hozAlign: "right",
                        sorter: "number",
                        width: 100,
                        headerTooltip: "L1 Product ad impressions, or L7 if no L1 row.",
                        formatter: function(cell) {
                            return (parseInt(cell.getValue(), 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "ads clicks 1",
                        field: "ads_clicks_1",
                        hozAlign: "right",
                        sorter: "number",
                        width: 110,
                        headerTooltip: "L1 Product ad clicks, or L7 if no L1 row.",
                        formatter: function(cell) {
                            return (parseInt(cell.getValue(), 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "ads CVR 30",
                        field: "ads_cvr_30",
                        hozAlign: "right",
                        sorter: "number",
                        width: 110,
                        headerTooltip: "L30 ad sold / L30 clicks from tiktok_campaign_reports.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (v === null || v === undefined || isNaN(v)) return '<span style="color:#6c757d;">-</span>';
                            return v.toFixed(2) + '%';
                        }
                    },
                    {
                        title: "ROAS",
                        field: "ads_roas",
                        hozAlign: "right",
                        sorter: "number",
                        width: 80,
                        headerTooltip: "L30 ad revenue / L30 spend from tiktok_campaign_reports (roi fallback).",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toFixed(2);
                        }
                    },
                    {
                        title: "Target ROAS",
                        field: "target_roas",
                        hozAlign: "right",
                        sorter: "number",
                        width: 110,
                        editor: "number",
                        editorParams: { min: 0, step: 0.01 },
                        headerTooltip: "Target ROAS (in_roas) from tiktok_campaign_reports. Editable.",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (ttIsParentRow(d)) return false;
                            const sku = String(d['(Child) sku'] || d.sku || '');
                            return !!sku;
                        },
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toFixed(2);
                        }
                    },
                    {
                        title: "Acos%",
                        field: "ads_acos_pct",
                        hozAlign: "right",
                        sorter: "number",
                        width: 90,
                        headerTooltip: "L30 spend / L30 ad revenue from tiktok_campaign_reports.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toFixed(2) + '%';
                        }
                    },
                    {
                        title: "GMV Ad sold L30",
                        field: "gmv_ad_sold_l30",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 90,
                        headerTooltip: "Ad sold L30 from tiktok_gmv_ads, matched by SKU (latest upload batch).",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const raw = cell.getValue();
                            if (isParent && (raw === '-' || raw === null || raw === undefined)) {
                                return '<span style="color:#6c757d;">-</span>';
                            }
                            return (parseInt(raw, 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "GMV Ad sold L1",
                        field: "gmv_ad_sold_l1",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 80,
                        headerTooltip: "Ad sold L1 from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            return (parseInt(cell.getValue(), 10) || 0).toLocaleString();
                        }
                    },
                    {
                        title: "GMV Ad sales L30",
                        field: "gmv_ad_sales_l30",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 95,
                        headerTooltip: "Ad sales L30 from tiktok_gmv_ads, matched by SKU (latest upload batch).",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "GMV Ad sales L1",
                        field: "gmv_ad_sales_l1",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 90,
                        headerTooltip: "Ad sales L1 from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "GMV Spend L30",
                        field: "gmv_spend_l30",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 90,
                        headerTooltip: "Spend L30 from tiktok_gmv_ads, matched by SKU (latest upload batch).",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "GMV Spend L1",
                        field: "gmv_spend_l1",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 80,
                        headerTooltip: "Spend L1 from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue() || 0);
                            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "GMV Budget",
                        field: "gmv_budget",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        width: 80,
                        headerTooltip: "Budget from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || raw === '-') {
                                return '';
                            }
                            const v = parseFloat(raw);
                            if (isNaN(v)) return '';
                            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    },
                    {
                        title: "GMV Status",
                        field: "gmv_status",
                        hozAlign: "center",
                        headerSort: true,
                        width: 80,
                        headerTooltip: "Status from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || raw === '-') {
                                return '';
                            }
                            return String(raw);
                        }
                    },
                    {
                        title: "GMV Approval",
                        field: "gmv_approval",
                        hozAlign: "center",
                        headerSort: true,
                        width: 90,
                        headerTooltip: "Approval from tiktok_gmv_ads, matched by SKU.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || raw === '-') {
                                return '';
                            }
                            return String(raw);
                        }
                    },
                    {
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs TT Price.",
                        editor: "input",
                        width: 70,
                        sorter: "number",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (ttIsParentRow(d)) return false;
                            const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                            return !!sku;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (ttIsParentRow(rowData)) return '';
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const channelPrice = parseFloat(rowData['TT Price'] || rowData.price || 0) || 0;
                            const dot = ttStdPrcChangeDotHtml(std, channelPrice);

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + ('$' + std.toFixed(2)) + '</span>';
                        }
                    },
                    {
                        title: "Price",
                        field: "TT Price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'] || '';
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === null || raw === undefined || raw === '' ||
                                    raw === '-')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);
                            const priceColor = (lmpPrice > 0 && value > lmpPrice) ? '#dc3545' : 'inherit';
                            const priceWeight = (lmpPrice > 0 && value > lmpPrice) ? '700' : '700';

                            if (value === 0) {
                                if (sku && !isParent) {
                                    return `<span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: #a00211; font-weight: 700; cursor: pointer;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                                }
                                return `<span style="color: #a00211; font-weight: 700;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }

                            const priceFormatted = '$' + value.toFixed(2);
                            const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(value, lmpPrice) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(value, lmpPrice) : '');
                            if (sku && !isParent) {
                                return `<span class="view-sku-chart" data-sku="${sku}" data-metric="price" title="View Price chart" style="color: ${priceColor}; font-weight: ${priceWeight}; cursor: pointer;">${priceFormatted}</span>${lmpTri}${purpleTri}`;
                            }
                            if (lmpPrice > 0 && value > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 700;">${priceFormatted}</span>${lmpTri}${purpleTri}`;
                            }
                            return `<span style="font-weight: 700;">${priceFormatted}</span>${lmpTri}${purpleTri}`;
                        },
                        width: 70
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
                        width: 80,
                        visible: true,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (window.ParentExpand) {
                                const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, {
                                    dataset: typeof allTableData !== 'undefined' ? allTableData : undefined
                                });
                                if (avgHtml !== null) return avgHtml;
                            }
                            const isParent = ttIsParentRow(rowData);
                            if (isParent) return '';

                            const lmpPrice = parseFloat(cell.getValue() || 0);
                            const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
                            const sku = rowData['(Child) sku'] || '';
                            const skuAttr = String(sku).replace(/"/g, '&quot;');
                            const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                            const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));

                            // Same compact UI as /amazon-tabulator-view: $7.47 (1) or N/A
                            if (!lmpPrice && totalCompetitors === 0) {
                                return `<a href="#" class="view-tt-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color:#999;text-decoration:none;cursor:pointer;font-weight:600;"
                                    title="No competitors — click to add">N/A</a>`;
                            }

                            const currentPrice = parseFloat(rowData['TT Price'] || 0);
                            const priceColor = (lmpPrice > 0 && lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                            const lmpBase = parseFloat(rowData.lmp_base_price || 0) || lmpPrice;
                            const lmpShip = parseFloat(rowData.lmp_shipping || 0) || 0;
                            const shipTip = lmpShip > 0
                                ? ('$' + lmpBase.toFixed(2) + ' + $' + lmpShip.toFixed(2) + ' ship')
                                : '';

                            if (lmpPrice) {
                                let html = '<span style="color:' + priceColor + ';font-weight:600;"'
                                    + (shipTip ? (' title="' + escapeHtmlAttr(shipTip) + '"') : '') + '>'
                                    + '$' + lmpPrice.toFixed(2);
                                if (totalCompetitors > 0) {
                                    html += ' <a href="#" class="view-tt-lmp-competitors" data-sku="' + skuAttr
                                        + '" data-linked-skus="' + linkedSkusAttr + '"'
                                        + ' title="View ' + totalCompetitors + ' competitor'
                                        + (totalCompetitors === 1 ? '' : 's') + '"'
                                        + ' style="color:#007bff;text-decoration:none;cursor:pointer;font-weight:600;">'
                                        + '(' + totalCompetitors + ')</a>';
                                }
                                html += '</span>';
                                return html;
                            }

                            if (totalCompetitors > 0) {
                                return '<a href="#" class="view-tt-lmp-competitors" data-sku="' + skuAttr
                                    + '" data-linked-skus="' + linkedSkusAttr + '"'
                                    + ' title="View ' + totalCompetitors + ' competitor'
                                    + (totalCompetitors === 1 ? '' : 's') + '"'
                                    + ' style="color:#007bff;text-decoration:none;cursor:pointer;font-weight:600;">'
                                    + '(' + totalCompetitors + ')</a>';
                            }

                            return `<a href="#" class="view-tt-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                style="color:#999;text-decoration:none;cursor:pointer;font-weight:600;"
                                title="No competitors — click to add">N/A</a>`;
                        }
                    },
                    {
                        title: "Diff",
                        field: "lmp_diff_pct",
                        hozAlign: "center",
                        width: 70,
                        visible: true,
                        headerSortStartingDir: "desc",
                        sorter: function(a, b, aRow, bRow) {
                            const calc = function(rd) {
                                const lmp = parseFloat(rd.lmp_price || 0);
                                const price = parseFloat(rd['TT Price'] || 0);
                                if (!lmp || lmp <= 0) return -Infinity;
                                return ((lmp - price) / lmp) * 100;
                            };
                            return calc(aRow.getData()) - calc(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent) return '';
                            const lmp = parseFloat(rowData.lmp_price || 0);
                            const price = parseFloat(rowData['TT Price'] || 0);
                            if (!lmp || lmp <= 0) return '<span style="color:#999;">N/A</span>';
                            const diff = ((lmp - price) / lmp) * 100;
                            const color = diff < 0 ? '#dc3545' : '#28a745';
                            return `<span style="color:${color};font-weight:600;">${diff.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "GPFT%",
                        field: "GPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (value === null || value === undefined || value === '' || value ===
                                '-') return isParent ? '<span style="color:#6c757d;">-</span>' : '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return isParent ?
                                '<span style="color:#6c757d;">-</span>' : '';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },
                    {
                        title: "TACOS",
                        field: "TACOS%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (value === null || value === undefined || value === '' || value ===
                                '-') return isParent ? '<span style="color:#6c757d;">-</span>' : '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return isParent ?
                                '<span style="color:#6c757d;">-</span>' : '';
                            let color = percent >= 40 ? '#a00211' : percent >= 20 ? '#ffc107' :
                                percent >= 10 ? '#3591dc' : '#28a745';
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 55
                    },
                    {
                        title: "PFT%",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (value === null || value === undefined || value === '' || value ===
                                '-') return isParent ? '<span style="color:#6c757d;">-</span>' : '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return isParent ?
                                '<span style="color:#6c757d;">-</span>' : '';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },
                    {
                        title: "ROI%",
                        field: "ROI%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (value === null || value === undefined || value === '' || value ===
                                '-') return isParent ? '<span style="color:#6c757d;">-</span>' : '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return isParent ?
                                '<span style="color:#6c757d;">-</span>' : '';
                            const c = ttRoiBandColors(ttRoiVisualBand(percent));
                            return `<span style="color:${c.text};font-weight:700;">${percent.toFixed(0)}%</span>`;
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
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === null || raw === undefined || raw === '' ||
                                    raw === '-')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
                            let color = value >= 0 ? '#28a745' : '#a00211';
                            return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        },
                        width: 70
                    },
                    {
                        title: "T Profit",
                        field: "T Profit",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            const aProfit = parseFloat(aData.Profit || 0);
                            const bProfit = parseFloat(bData.Profit || 0);
                            const aTtl30 = parseFloat(aData['TT L30'] || 0);
                            const bTtl30 = parseFloat(bData['TT L30'] || 0);
                            return (aTtl30 * aProfit) - (bTtl30 * bProfit);
                        },
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            const profit = parseFloat(rowData.Profit || 0);
                            const ttl30 = parseFloat(rowData['TT L30'] || 0);
                            const value = ttl30 * profit;
                            if (isParent && !Number.isFinite(value))
                            return '<span style="color:#6c757d;">-</span>';
                            const color = value >= 0 ? '#28a745' : '#a00211';
                            return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        },
                        width: 85
                    },
                    {
                        title: "Sales",
                        field: "Sales L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === null || raw === undefined || raw === '' ||
                                    raw === '-')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
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
                        // Shipping Master "Ship" (normal ship), never BB Ship / ship_bb.
                        title: "Ship",
                        field: "Ship_productmaster",
                        headerTooltip: "Normal Ship from Shipping Master (not BB Ship)",
                        hozAlign: "center",
                        sorter: "number",
                        visible: true,
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent && (raw === '-' || raw === null || raw === undefined ||
                                    raw === '')) return '<span style="color:#6c757d;">-</span>';
                            const value = parseFloat(raw || 0);
                            if (isParent && isNaN(value))
                            return '<span style="color:#6c757d;">-</span>';
                            return `$${value.toFixed(2)}`;
                        },
                        width: 70
                    },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: "SPRICE",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        sorter: "number",
                        headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). S PRC ≥ LMP is capped at LMP and keeps a red triangle after push. Blue triangle = S PRC ≠ Price.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (ttIsParentRow(rowData)) return '';
                            let value = parseFloat(cell.getValue() || 0);
                            if (typeof chPromoLiveSprice === 'function') {
                                const calc = chPromoLiveSprice(rowData);
                                if (calc > 0) value = calc;
                            }
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(rowData, value) : null;
                            if (cap && cap.shown > 0) value = cap.shown;
                            const hasCustom = rowData.has_custom_sprice;
                            const status = rowData.SPRICE_STATUS;
                            const live = ttLivePrice(rowData);
                            const lmp = cap ? cap.lmp : (parseFloat(rowData.lmp_price) || 0);

                            let bgColor = '';
                            if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                            else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                            else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                            else if (hasCustom) bgColor = 'background-color: #e7f1ff;';

                            if (!(value > 0)) return '';
                            const formatted = '$' + value.toFixed(2);
                            const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                            const priceHtml = overLmp
                                ? `<span style="color:#dc3545;font-weight:600;${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`
                                : `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">${formatted}</span>`;
                            const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${redTri}${blueTri}</span>`;
                        },
                        width: 92
                    },
                    {
                        title: "Push",
                        field: "push_price",
                        hozAlign: "center",
                        headerSort: false,
                        width: 55,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = ttIsParentRow(rowData);
                            if (isParent) return '<span style="color:#6c757d;">-</span>';

                            const sku = rowData['(Child) sku'];
                            const sprice = window.SpriceLmpCap
                                ? SpriceLmpCap.prepare(rowData, parseFloat(rowData.SPRICE || 0))
                                : (parseFloat(rowData.SPRICE || 0));
                            const status = rowData.SPRICE_STATUS || null;
                            const pushedValue = rowData.SPRICE_PUSHED_VALUE;
                            const updatedAt = rowData.SPRICE_STATUS_UPDATED_AT;
                            const pushedBy = rowData.SPRICE_PUSHED_BY;
                            const channelLabel = (TTP_CFG.summaryChannel === 'tiktok2') ? 'TikTok 2' : 'TikTok';

                            if (!sku || !sprice || sprice <= 0) {
                                return '<span style="color:#999;">N/A</span>';
                            }

                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745';
                            let titleText = `Push $${sprice.toFixed(2)} to ${channelLabel}`;

                            if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107';
                                titleText = 'Price pushing in progress...';
                            } else if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745';
                                titleText = `Price pushed to ${channelLabel} (Double-click to mark as Applied)`;
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745';
                                titleText = `Price applied on ${channelLabel}`;
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545';
                                titleText = `Error pushing price to ${channelLabel} — click to retry`;
                            }

                            const tipParts = [titleText];
                            if (pushedValue !== null && pushedValue !== undefined) {
                                tipParts.push(`Last: $${parseFloat(pushedValue).toFixed(2)}`);
                            }
                            if (updatedAt) tipParts.push(updatedAt);
                            if (pushedBy) tipParts.push(`by ${pushedBy}`);

                            return `<button type="button" class="btn btn-sm tiktok-push-price-btn btn-circle"
                                data-sku="${String(sku).replace(/"/g, '&quot;')}"
                                data-price="${sprice}"
                                data-status="${status || ''}"
                                title="${tipParts.join(' | ').replace(/"/g, '&quot;')}"
                                style="border:none;background:none;color:${iconColor};padding:0;cursor:pointer;font-size:16px;">
                                ${icon}
                            </button>`;
                        }
                    },
                    {
                        title: "SROI%",
                        field: "SROI",
                        hozAlign: "center",
                        sorter: "number",
                        width: 50,
                        minWidth: 50,
                        maxWidth: 50,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value === '-') {
                                return '<span style="color:#6c757d;">-</span>';
                            }
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '<span style="color:#6c757d;">-</span>';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        }
                    },
                    {
                        title: "SGPFT%",
                        field: "SGPFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value === '-') {
                                return '<span style="color:#6c757d;">-</span>';
                            }
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '<span style="color:#6c757d;">-</span>';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },
                    {
                        title: "SPFT%",
                        field: "SPFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value === '-') {
                                return '<span style="color:#6c757d;">-</span>';
                            }
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '<span style="color:#6c757d;">-</span>';
                            const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                        },
                        width: 50
                    },
                    {
                        title: "Variation Req",
                        field: "variation_req",
                        hozAlign: "center",
                        width: 120,
                        minWidth: 120,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const rowData = row.getData();
                            if (ttIsParentRow(rowData))
                                return '<span style="color:#6c757d;">-</span>';
                            const sku = rowData['(Child) sku'];
                            const value = (cell.getValue()?.trim()) || 'Not Req';
                            const isReq = value === 'Req';
                            const textColor = isReq ? '#28a745' : '#dc3545';
                            return `
                            <select class="form-select form-select-sm editable-select variation-req-select" data-sku="${sku}" data-field="variation_req"
                                style="width: 100%; min-width: 90px; border: 1px solid #dee2e6; padding: 2px 4px; font-size: 12px; font-weight: 600; color: ${textColor};">
                                <option value="Req" ${value === 'Req' ? 'selected' : ''} style="color: #28a745;">Req</option>
                                <option value="Not Req" ${value === 'Not Req' ? 'selected' : ''} style="color: #dc3545;">Not Req</option>
                            </select>
                        `;
                        }
                    },
                    {
                        title: "Video Req",
                        field: "video_req",
                        hozAlign: "center",
                        width: 120,
                        minWidth: 120,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const rowData = row.getData();
                            if (ttIsParentRow(rowData))
                                return '<span style="color:#6c757d;">-</span>';
                            const sku = rowData['(Child) sku'];
                            const value = (cell.getValue()?.trim()) || 'Not Req';
                            const isReq = value === 'Req';
                            const textColor = isReq ? '#28a745' : '#dc3545';
                            return `
                            <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-field="video_req"
                                style="width: 100%; min-width: 90px; border: 1px solid #dee2e6; padding: 2px 4px; font-size: 12px; font-weight: 600; color: ${textColor};">
                                <option value="Req" ${value === 'Req' ? 'selected' : ''} style="color: #28a745;">Req</option>
                                <option value="Not Req" ${value === 'Not Req' ? 'selected' : ''} style="color: #dc3545;">Not Req</option>
                            </select>
                        `;
                        }
                    },
                    {
                        title: "Video Uploaded",
                        field: "video_uploaded",
                        hozAlign: "center",
                        width: 110,
                        minWidth: 110,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const rowData = row.getData();
                            if (ttIsParentRow(rowData))
                                return '<span style="color:#6c757d;">-</span>';
                            const sku = rowData['(Child) sku'];
                            const val = cell.getValue();
                            const checked = val === 1 || val === '1' || val === true;
                            return '<input type="checkbox" class="form-check-input video-uploaded-checkbox" data-sku="' +
                                (sku || '').replace(/"/g, '&quot;') +
                                '" data-field="video_uploaded" ' + (checked ? 'checked' : '') + '>';
                        }
                    },
                    {
                        title: "NR/Req",
                        field: "nrp",
                        hozAlign: "center",
                        width: 56,
                        minWidth: 52,
                        headerSort: true,
                        accessor: function(value, data) {
                            const val = data && data.nrp != null ? data.nrp : value;
                            if (val === null || val === undefined) return '';
                            return String(val).trim().toUpperCase();
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (ttIsParentRow(rowData)) {
                                return '<span style="color:#6c757d;">-</span>';
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
                            if (!value || value === '' || (value !== 'NR' && value !== 'REQ')) {
                                value = 'REQ';
                            }
                            const sku = String(rowData['(Child) sku'] || '');
                            const parent = rowData.Parent != null ? String(rowData.Parent) : '';
                            const tip = value === 'NR' ? 'NR' : 'REQ';
                            const skuAttr = ttEscHtmlAttr(sku);
                            const parentAttr = ttEscHtmlAttr(parent);
                            const nrVal = (value === 'NR') ? 'NR' : 'REQ';
                            return (
                                '<select class="form-select form-select-sm nrp-nr-select" ' +
                                'data-sku="' + skuAttr + '" data-parent="' + parentAttr + '" ' +
                                'style="width:50px;border:1px solid gray;padding:2px;font-size:20px;text-align:center;" ' +
                                'aria-label="NRP: ' + ttEscHtmlAttr(tip) + '">' +
                                '<option value="REQ"' + (nrVal === 'REQ' ? ' selected' : '') + '>🟢</option>' +
                                '<option value="NR"' + (nrVal === 'NR' ? ' selected' : '') + '>🔴</option>' +
                                '</select>'
                            );
                        }
                    },
                    {
                        title: "Sku Link LMP",
                        field: "linked_lmp_skus",
                        hozAlign: "left",
                        headerHozAlign: "center",
                        width: 220,
                        visible: true,
                        headerSort: false,
                        cssClass: "linked-sku-col",
                        formatter: linkedLmpSkuFormatter,
                        cellClick: function(e, cell) {
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
                        visible: true,
                        headerSort: false,
                        cssClass: "linked-sku-add-col",
                        formatter: linkedLmpSkuAddFormatter,
                        cellClick: function(e, cell) {
                            if (e.target.closest('.sku-link-lmp-add-btn')) {
                                e.preventDefault();
                                e.stopPropagation();
                                openLinkedSkuModal(cell.getRow().getData());
                            }
                        },
                    }
                ]
            });

            $(document).on('change', '#tiktok-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.searchRows('(Child) sku', '=', sku);
                const row = rows && rows.length ? rows[0] : null;
                const prevRaw = row ? String(row.getData().nrp ?? '').trim().toUpperCase() : '';
                const prevSelect = prevRaw === 'NR' ? 'NR' : 'REQ';
                const prevNrp = row ? row.getData().nrp : undefined;
                // Same as eBay NR/REQ: update Tabulator row immediately, then persist (revert row + select on failure).
                if (row) {
                    row.update({ nrp: newValue }, true);
                    const nrCell = row.getCells().find(function(c) { return c.getField() === 'nrp'; });
                    if (nrCell) nrCell.reformat();
                }
                ttSaveNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {
                        showToast('NRP saved', 'success');
                    },
                    function() {
                        $el.val(prevSelect);
                        if (row) {
                            row.update({ nrp: prevNrp }, true);
                            const nrCell = row.getCells().find(function(c) { return c.getField() === 'nrp'; });
                            if (nrCell) nrCell.reformat();
                        }
                    }
                );
            });

            // SKU Search: run applyFilters() so Ad Click and other filters stay applied (missing campaign stays hidden when Ad Click filter is on)
            $('#sku-search, #parent-search').on('keyup', function() {
                applyFilters();
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
                            applyTtStandardPriceToLinkedRows(sku, saved, response.applied_skus);
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
                    // Always store SPRICE to exactly 2 decimals (UI input may allow more digits).
                    const rawSprice = parseFloat(cell.getValue()) || 0;
                    const newSprice = window.SpriceLmpCap
                        ? SpriceLmpCap.prepare(rowData, Math.round(rawSprice * 100) / 100)
                        : (Math.round(rawSprice * 100) / 100);

                    const percentage = getRowMarginFactor(rowData);
                    const lp = rowData['LP_productmaster'] || 0;
                    const ship = rowData['Ship_productmaster'] || 0;

                    const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) /
                        newSprice) * 100 * 100) / 100 : 0;
                    const spft = sgpft;
                    const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 *
                        100) / 100 : 0;

                    row.update({
                        SPRICE: newSprice,
                        SGPFT: sgpft,
                        SPFT: spft,
                        SROI: sroi,
                        has_custom_sprice: true
                    });

                    saveSpriceUpdates([{
                        sku: sku,
                        sprice: newSprice
                    }]);
                } else if (field === 'in_roas' || field === 'target_roas') {
                    const row = cell.getRow();
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    const value = parseFloat(cell.getValue() || 0);
                    const oldValue = parseFloat(rowData[field] || rowData.in_roas || 0);
                    $.ajax({
                        url: '{{ route('tiktok.utilized.update') }}',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: JSON.stringify({
                            sku: sku,
                            field: 'in_roas',
                            value: value,
                            channel: TTP_CFG.summaryChannel
                        }),
                        success: function(response) {
                            if (response && response.success) {
                                showToast('Target ROAS updated', 'success');
                            }
                        },
                        error: function(xhr, status, error) {
                            cell.setValue(oldValue);
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr
                                .responseJSON.message : ('Failed to update Target ROAS: ' + (xhr
                                    .statusText || error));
                            showToast(msg, 'error');
                        }
                    });
                } else if (field === 'budget') {
                    const row = cell.getRow();
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    const rawVal = cell.getValue();
                    const value = rawVal === '' || rawVal === null || rawVal === undefined ? null :
                        parseFloat(rawVal);
                    const oldValue = rowData.budget;
                    $.ajax({
                        url: '{{ route('tiktok.utilized.update') }}',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: JSON.stringify({
                            sku: sku,
                            field: 'budget',
                            value: value,
                            channel: TTP_CFG.summaryChannel
                        }),
                        success: function(response) {
                            if (response && response.success) {
                                showToast('Budget updated', 'success');
                            }
                        },
                        error: function(xhr, status, error) {
                            cell.setValue(oldValue);
                            const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr
                                .responseJSON.message : ('Failed to update Budget: ' + (xhr
                                    .statusText || error));
                            showToast(msg, 'error');
                        }
                    });
                }
            });

            // NRA and Status editable selects (utilized columns) - save to tiktok.utilized.update
            $(document).on('change', '.editable-select', function() {
                const sku = $(this).data('sku');
                const field = $(this).data('field');
                const value = $(this).val();
                if (!sku || !field) return;
                const rows = table.searchRows("(Child) sku", "=", sku);
                const row = rows && rows.length ? rows[0] : null;
                let oldValue = null;
                if (row) {
                    const rowData = row.getData();
                    oldValue = rowData[field];
                    rowData[field] = value;
                    row.update(rowData);
                }
                const $select = $(this);
                $.ajax({
                    url: '{{ route('tiktok.utilized.update') }}',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value,
                        channel: TTP_CFG.summaryChannel
                    }),
                    success: function(response) {
                        if (response && response.success) {
                            showToast(field === 'NR' ? 'NRA updated' : (field ===
                                'variation_req' ? 'Variation Req updated' : (field ===
                                    'video_req' ? 'Video Req updated' : 'Status updated'
                                    )), 'success');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (row && oldValue !== null) {
                            const rowData = row.getData();
                            rowData[field] = oldValue;
                            row.update(rowData);
                            $select.val(oldValue);
                        }
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr
                            .responseJSON.message : ('Failed to update: ' + (xhr.statusText ||
                                error));
                        showToast(msg, 'error');
                    }
                });
            });

            // Video Uploaded checkbox - save to tiktok.utilized.update
            $(document).on('change', '.video-uploaded-checkbox', function() {
                const sku = $(this).data('sku');
                const field = $(this).data('field');
                const value = $(this).prop('checked') ? '1' : '0';
                if (!sku || !field) return;
                const rows = table.searchRows("(Child) sku", "=", sku);
                const row = rows && rows.length ? rows[0] : null;
                const oldValue = row ? row.getData()[field] : null;
                if (row) {
                    const rowData = row.getData();
                    rowData[field] = value === '1' ? 1 : 0;
                    row.update(rowData);
                }
                const $cb = $(this);
                $.ajax({
                    url: '{{ route('tiktok.utilized.update') }}',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value,
                        channel: TTP_CFG.summaryChannel
                    }),
                    success: function(response) {
                        if (response && response.success) {
                            showToast('Video Uploaded updated', 'success');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (row && oldValue !== null && oldValue !== undefined) {
                            const rowData = row.getData();
                            rowData[field] = oldValue;
                            row.update(rowData);
                            $cb.prop('checked', oldValue === 1 || oldValue === '1');
                        }
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr
                            .responseJSON.message : ('Failed to update: ' + (xhr.statusText ||
                                error));
                        showToast(msg, 'error');
                    }
                });
            });

            // ========== TikTok price push (Reverb/Amazon pattern) ==========
            function ttPushMarketplace() {
                return (TTP_CFG.summaryChannel === 'tiktok2') ? 'tiktok2' : 'tiktok';
            }

            function ttChannelLabel() {
                return (TTP_CFG.summaryChannel === 'tiktok2') ? 'TikTok 2' : 'TikTok';
            }

            function pushTikTokPriceForRow(row, sku, price) {
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
                            marketplace: ttPushMarketplace(),
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response && response.success) {
                                row.update({
                                    SPRICE_STATUS: 'pushed',
                                    SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString(),
                                    SPRICE_PUSHED_VALUE: price,
                                    has_custom_sprice: true
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
                                : ('Failed to push price to ' + ttChannelLabel());
                            row.update({
                                SPRICE_STATUS: 'error',
                                SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString()
                            }).then(function() { row.reformat(); }).catch(function() { row.reformat(); });
                            resolve({ ok: false, sku: sku, message: msg });
                        }
                    });
                });
            }

            let tiktokPushClickTimer = null;
            $(document).on('click', '.tiktok-push-price-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                const currentStatus = $btn.attr('data-status') || '';
                const sku = $btn.attr('data-sku') || $btn.data('sku');

                // Double-click → mark Applied
                if (e.originalEvent && e.originalEvent.detail === 2) {
                    if (tiktokPushClickTimer) {
                        clearTimeout(tiktokPushClickTimer);
                        tiktokPushClickTimer = null;
                    }
                    if (currentStatus !== 'pushed' || !sku) return;

                    const statusUrl = TTP_CFG.updateSpriceStatus || '/tiktok-update-sprice-status';
                    $.ajax({
                        url: statusUrl,
                        method: 'POST',
                        data: { sku: sku, status: 'applied', _token: $('meta[name="csrf-token"]').attr('content') },
                        success: function(response) {
                            if (response && response.success) {
                                const $rowEl = $btn.closest('.tabulator-row');
                                const row = table.getRow($rowEl[0]);
                                if (row) {
                                    row.update({
                                        SPRICE_STATUS: 'applied',
                                        SPRICE_STATUS_UPDATED_AT: new Date().toLocaleString()
                                    }).then(function() { row.reformat(); }).catch(function() { row.reformat(); });
                                }
                                showToast('Status updated to Applied', 'success');
                            }
                        },
                        error: function() {
                            showToast('Failed to update status', 'error');
                        }
                    });
                    return;
                }

                if (currentStatus === 'processing' || $btn.prop('disabled')) return;

                if (tiktokPushClickTimer) clearTimeout(tiktokPushClickTimer);
                tiktokPushClickTimer = setTimeout(function() {
                    tiktokPushClickTimer = null;
                    const $rowEl = $btn.closest('.tabulator-row');
                    const row = table.getRow($rowEl[0]);
                    if (!row) return;
                    const price = parseFloat(row.getData().SPRICE || $btn.attr('data-price') || 0);

                    if (!sku || !price || price <= 0) {
                        showToast('Set a valid SPRICE (> 0) before pushing', 'error');
                        return;
                    }

                    $btn.prop('disabled', true);
                    pushTikTokPriceForRow(row, sku, price).then(function(result) {
                        $btn.prop('disabled', false);
                        if (result.ok) {
                            showToast(result.message || `Price pushed to ${ttChannelLabel()} for ${sku}`, 'success');
                        } else {
                            showToast(result.message || `Failed to push ${sku}`, 'error');
                        }
                    });
                }, 280);
            });

            async function executeBulkPushTikTok($triggerBtn) {
                if (selectedSkus.size === 0) {
                    showToast('Select at least one SKU first (turn on PRc / Bulk Mode)', 'error');
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

                if (!confirm('Push ' + jobs.length + ' price(s) to ' + ttChannelLabel() + '?')) {
                    return;
                }

                const $btn = ($triggerBtn && $triggerBtn.length) ? $triggerBtn : $('#bulk-push-tiktok-btn');
                const $dropdownBtn = $('#ttBulkActionsDropdown');
                const originalBtnHtml = $btn.html();
                const originalDropHtml = $dropdownBtn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
                $dropdownBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
                $('#execute-bulk-push-tiktok').prop('disabled', true);

                let okCount = 0;
                let failCount = 0;
                const concurrency = 5;
                let idx = 0;
                async function runNext() {
                    if (idx >= jobs.length) return;
                    const job = jobs[idx++];
                    const result = await pushTikTokPriceForRow(job.row, job.sku, job.price);
                    if (result.ok) okCount++; else failCount++;
                    await runNext();
                }
                await Promise.all(Array.from({ length: Math.min(concurrency, jobs.length) }, function() { return runNext(); }));

                $btn.prop('disabled', false).html(originalBtnHtml || '<i class="fas fa-paper-plane"></i> Push Selected');
                $dropdownBtn.prop('disabled', false).html(originalDropHtml || '<i class="fas fa-upload"></i> Bulk Push');
                $('#execute-bulk-push-tiktok').prop('disabled', false);

                if (failCount === 0) {
                    showToast('Pushed ' + okCount + ' price(s) to ' + ttChannelLabel(), 'success');
                } else {
                    showToast('Pushed ' + okCount + ', failed ' + failCount, failCount === jobs.length ? 'error' : 'warning');
                }
            }

            $('#bulk-push-tiktok-btn').on('click', function() {
                executeBulkPushTikTok($(this));
            });
            $(document).on('click', '#execute-bulk-push-tiktok', function(e) {
                e.preventDefault();
                e.stopPropagation();
                executeBulkPushTikTok($(this));
            });

            // L7 / L30 Upload: button triggers file input, then upload on file select
            function doUploadReport(fileInput, reportRange, statusContainerId) {
                const file = fileInput.files[0];
                if (!file) return;
                const $status = $('#' + statusContainerId);
                $status.removeClass('d-none').html('<span class="text-primary">Uploading...</span>');
                const formData = new FormData();
                formData.append('file', file);
                formData.append('report_range', reportRange);
                $.ajax({
                    url: '{{ route('tiktok.utilized.upload') }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response && response.success) {
                            showToast(response.message || 'Upload successful', 'success');
                            if (table) table.replaceData();
                            $status.html('<span class="text-success">' + (response.message || 'Done') +
                                '</span>');
                        } else {
                            showToast(response.message || 'Upload failed', 'error');
                            $status.html('<span class="text-danger">' + (response.message || 'Failed') +
                                '</span>');
                        }
                        setTimeout(function() {
                            $status.addClass('d-none').html('');
                        }, 4000);
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON
                            .message : 'Upload failed';
                        showToast(msg, 'error');
                        $status.html('<span class="text-danger">' + msg + '</span>');
                        setTimeout(function() {
                            $status.addClass('d-none').html('');
                        }, 4000);
                    }
                });
                fileInput.value = '';
            }
            $('#l7-upload-btn').on('click', function() {
                $('#l7-upload-file').off('change').on('change', function() {
                    doUploadReport(this, 'L7', 'upload-status-container');
                }).trigger('click');
            });
            $('#l30-upload-btn').on('click', function() {
                $('#l30-upload-file').off('change').on('change', function() {
                    doUploadReport(this, 'L30', 'upload-status-container');
                }).trigger('click');
            });
            $('#l1-upload-btn').on('click', function() {
                $('#l1-upload-file').off('change').on('change', function() {
                    doUploadReport(this, 'L1', 'upload-status-container');
                }).trigger('click');
            });

            $('#tt2-sync-api-btn').on('click', function() {
                if (!TTP_CFG.syncFromApi) return;
                const $btn = $(this);
                if (!confirm('Fetch TikTok 2 products + orders from the Shop API now?')) return;
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing…');
                $.ajax({
                    url: TTP_CFG.syncFromApi,
                    method: 'POST',
                    data: {
                        products: 1,
                        orders: 1,
                        days: 60,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        alert(res.message || 'TikTok 2 sync completed.');
                        if (table) table.setData();
                    },
                    error: function(xhr) {
                        const res = xhr.responseJSON || {};
                        const msg = res.message || 'TikTok 2 sync failed.';
                        if (res.connect_url) {
                            if (confirm(msg + '\n\nOpen Connect page now?')) {
                                window.open(res.connect_url, '_blank');
                            }
                        } else {
                            alert(msg);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt"></i> Sync API');
                    }
                });
            });

            // Copy SKU button handler
            $(document).on('click', '.copy-sku-btn', function(e) {
                e.stopPropagation();
                const sku = $(this).data('sku');
                navigator.clipboard.writeText(sku).then(() => {
                    showToast(`Copied: ${sku}`, 'success');
                });
            });

            // Helper: parent summary rows must never be hidden by filters
            function isParentRow(data) {
                return ttIsParentRow(data);
            }

            function ttRefreshAllSkuRows() {
                if (!table) {
                    ttAllSkuRows = [];
                    return;
                }
                ttAllSkuRows = table.getData('all').filter(function(row) {
                    return !ttIsParentRow(row);
                });
            }

            // Apply filters
            function applyFilters() {
                if (window.ParentExpand && ParentExpand.isExpanded()) {
                    ParentExpand.beforeFilters(function(){ applyFilters(); });
                    return;
                }
                const rowTypeFilter = $('#row-type-filter').val();
                const inventoryFilter = $('#inventory-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const roiFilter = $('#roi-filter').val();
                const adClickFilter = $('#ad-click-filter').val();
                const dilFilter = $('#dil-filter').val() || 'all';
                // Same as /amazon-tabulator-view: All / Parent keep summary rows; SKUs hides them.
                const parentRowsBypassDataFilters = (rowTypeFilter === 'parent' || rowTypeFilter === 'all');

                table.clearFilter();

                // Row type filter
                if (rowTypeFilter === 'parent') {
                    table.addFilter(function(data) {
                        return isParentRow(data);
                    });
                } else if (rowTypeFilter === 'sku') {
                    table.addFilter(function(data) {
                        return !isParentRow(data);
                    });
                }

                // Inventory filter
                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data.INV) === 0;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data.INV) > 0;
                    });
                }

                // TikTok Stock filter (parent rows always visible)
                const tiktokStockFilter = $('#tiktok-stock-filter').val();
                if (tiktokStockFilter === 'zero') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data['TT Stock']) === 0;
                    });
                } else if (tiktokStockFilter === 'more') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data['TT Stock']) > 0;
                    });
                }

                // GPFT filter (parent rows always visible) — slabs match ebay-tabulator-view
                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const gpft = parseFloat(data['GPFT%']) || 0;
                        if (gpftFilter === 'negative') return gpft < 0;
                        if (gpftFilter === '0-10')     return gpft >= 0 && gpft < 10;
                        if (gpftFilter === '10-20')    return gpft >= 10 && gpft < 20;
                        if (gpftFilter === '20-30')    return gpft >= 20 && gpft < 30;
                        if (gpftFilter === '30-40')    return gpft >= 30 && gpft < 40;
                        if (gpftFilter === '40plus')   return gpft >= 40;
                        return true;
                    });
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const cvrPercent = ttListingCvr(data);
                        const cvrRounded = Math.round(cvrPercent * 100) / 100;
                        if (cvrFilter === '0-0') return cvrRounded === 0;
                        if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                        if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                        if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                        if (cvrFilter === '13plus') return cvrRounded > 13;
                        return true;
                    });
                }

                // ROI % filter (parent rows always visible)
                if (roiFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const roi = parseFloat(data['ROI%']);
                        if (isNaN(roi)) return false;
                        if (roiFilter === 'red' || roiFilter === 'lt40') return roi < 60;
                        if (roiFilter === 'gray' || roiFilter === '40-75') return roi >= 60 && roi < 90;
                        if (roiFilter === 'green' || roiFilter === '75-125' || roiFilter === 'gt125') return roi >= 90;
                        return true;
                    });
                }

                // Ad Click filter (parent rows always visible)
                if (adClickFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const hasCampaign = data.hasCampaign === true || data.hasCampaign === 'true' || data
                            .hasCampaign === 1;
                        const clicks = parseInt(data.ad_clicks, 10) || 0;
                        if (!hasCampaign) return false;
                        if (adClickFilter === 'zero') return clicks === 0;
                        if (adClickFilter === 'has') return clicks > 0;
                        return true;
                    });
                }

                // T L30 filter: 0 / >0 (parent rows always visible; excludes 0 inventory for child rows)
                const tl30Filter = $('#tl30-filter').val();
                if (tl30Filter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const inv = parseFloat(data.INV) || 0;
                        if (inv <= 0) return false;
                        const ttL30 = parseFloat(data['TT L30']) || 0;
                        if (tl30Filter === '0') return ttL30 === 0;
                        if (tl30Filter === 'more') return ttL30 > 0;
                        return true;
                    });
                }

                // DIL filter — same as amazon-tabulator-view (L30 / INV * 100)
                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        const inv = parseFloat(data['INV']) || 0;
                        const l30 = parseFloat(data['L30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                        if (dilFilter === 'red') return dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                // 0 Sold filter (parent rows always visible)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data['TT L30']) === 0;
                    });
                }

                // > 0 Sold filter (parent rows always visible)
                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        if (isParentRow(data)) return parentRowsBypassDataFilters;
                        return parseFloat(data['TT L30']) > 0;
                    });
                }

                if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                    table.addFilter(function(data) {
                        return PriceGtLmpBadge.hasRedTriangle(data, 'TT Price');
                    });
                }
                if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                    table.addFilter(function(data) {
                        return PriceLt80LmpBadge.hasPurpleTriangle(data, 'TT Price');
                    });
                }
                if (blueTriangleFilterActive) {
                    table.addFilter(function(data) {
                        return ttHasBlueTriangle(data);
                    });
                }

                // Ads section badge filter (parent rows always visible)
                if (typeof utilizedColumnsVisible !== 'undefined' && utilizedColumnsVisible && adsBadgeFilter) {
                    switch (adsBadgeFilter) {
                        case 'all':
                            break;
                        case 'campaign':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const hasCampaign = data.hasCampaign === true || data.hasCampaign ===
                                    'true' || data.hasCampaign === 1;
                                return hasCampaign;
                            });
                            break;
                        case 'ad-sku':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const hasCampaign = data.hasCampaign === true || data.hasCampaign ===
                                    'true' || data.hasCampaign === 1;
                                const inv = parseFloat(data.INV) || 0;
                                return hasCampaign && inv > 0;
                            });
                            break;
                        case 'missing':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const hasCampaign = data.hasCampaign === true || data.hasCampaign ===
                                    'true' || data.hasCampaign === 1;
                                const nr = (data.NR || '').trim();
                                const inv = parseFloat(data.INV) || 0;
                                return !hasCampaign && inv > 0 && nr !== 'NRA';
                            });
                            break;
                        case 'nra-missing':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const hasCampaign = data.hasCampaign === true || data.hasCampaign ===
                                    'true' || data.hasCampaign === 1;
                                const nr = (data.NR || '').trim();
                                return !hasCampaign && nr === 'NRA';
                            });
                            break;
                        case 'zero-inv':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                return parseFloat(data.INV) <= 0;
                            });
                            break;
                        case 'nra':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                return (data.NR || '').trim() === 'NRA';
                            });
                            break;
                        case 'ra':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                return (data.NR || '').trim() === 'RA';
                            });
                            break;
                        case 'total-spend':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const spend = parseFloat(data.spend) || 0;
                                return spend > 0;
                            });
                            break;
                        case 'total-spend-l30':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const spendL30 = parseFloat(data.spend_l30) || 0;
                                return spendL30 > 0;
                            });
                            break;
                        case 'total-spend-l7':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const spendL7 = parseFloat(data.spend_l7) || 0;
                                return spendL7 > 0;
                            });
                            break;
                        case 'budget':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const b = data.budget;
                                return b !== null && b !== undefined && b !== '' && (parseFloat(b) || 0) >
                                0;
                            });
                            break;
                        case 'ad-clicks':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const clicks = parseInt(data.ad_clicks, 10) || 0;
                                return clicks > 0;
                            });
                            break;
                        case 'ad-sales':
                        case 'avg-acos':
                        case 'roas':
                            table.addFilter(function(data) {
                                if (isParentRow(data)) return parentRowsBypassDataFilters;
                                const spend = parseFloat(data.spend) || 0;
                                const outRoas = parseFloat(data.out_roas) || 0;
                                return spend > 0 && outRoas > 0;
                            });
                            break;
                    }
                }

                // SKU search: show only rows where (Child) sku or Parent contains the term (parent rows must match too)
                const skuSearchVal = $('#sku-search').val();
                if (skuSearchVal && skuSearchVal.trim() !== '') {
                    const term = skuSearchVal.trim().toLowerCase();
                    table.addFilter(function(data) {
                        const sku = (data['(Child) sku'] || '').toString().toLowerCase();
                        const parent = (data.Parent || '').toString().toLowerCase();
                        const matchSku = sku.indexOf(term) !== -1;
                        const matchParent = parent.indexOf(term) !== -1;
                        if (isParentRow(data)) return matchSku || matchParent;
                        return matchSku;
                    });
                }

                const parentSearchVal = $('#parent-search').val();
                if (parentSearchVal && parentSearchVal.trim() !== '') {
                    const pTerm = parentSearchVal.trim().toLowerCase();
                    table.addFilter(function(data) {
                        return (data.Parent || '').toString().toLowerCase().indexOf(pTerm) !== -1;
                    });
                }

                updateSummary();
                updateTtSummaryBadgeStyles();
            }

            if (window.PriceGtLmpBadge) {
                PriceGtLmpBadge.bind({
                    badge: '#tiktok-price-gt-lmp-badge',
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
                    badge: '#tiktok-price-lt80-lmp-badge',
                    getActive: function() { return priceLt80LmpFilterActive; },
                    onToggle: function(on) {
                        priceLt80LmpFilterActive = on;
                        if (on) blueTriangleFilterActive = false;
                        applyFilters();
                    }
                });
            }
            $('#tiktok-blue-triangle-badge').on('click', function() {
                blueTriangleFilterActive = !blueTriangleFilterActive;
                if (blueTriangleFilterActive) {
                    priceGtLmpFilterActive = false;
                    priceLt80LmpFilterActive = false;
                }
                applyFilters();
            });

            $('#row-type-filter, #inventory-filter, #gpft-filter, #cvr-filter, #roi-filter, #tiktok-stock-filter, #ad-click-filter, #tl30-filter, #dil-filter')
                .on('change', function() {
                    applyFilters();
                });

            // Update summary badges
            // GPFT / ROI use L30-weighted totals (same as Tiendamia / Amazon / Newegg):
            //   GPFT% = totalPft / totalSales * 100
            //   ROI%  = totalPft / totalCogs  * 100
            // (not a simple average of per-SKU GPFT%/ROI%, which can show +PFT with −ROI)
            function updateSummary() {
                const data = table.getData('active').filter(row => !ttIsParentRow(row));
                const badgeRows = (ttAllSkuRows && ttAllSkuRows.length) ? ttAllSkuRows : table.getData('all')
                    .filter(row => !ttIsParentRow(row));

                let totalSales = 0,
                    totalPft = 0,
                    totalCogs = 0,
                    totalPrice = 0,
                    priceCount = 0;
                let totalInv = 0,
                    totalL30 = 0,
                    zeroSoldCount = 0,
                    moreSoldCount = 0;

                data.forEach(row => {
                    const l30 = parseFloat(row['TT L30']) || 0;
                    const profit = parseFloat(row['Profit']) || 0;
                    const lp = parseFloat(row['LP_productmaster']) || 0;
                    const sales = parseFloat(row['Sales L30']) || 0;
                    totalSales += sales;
                    totalPft += l30 * profit;
                    totalCogs += l30 * lp;

                    const price = parseFloat(row['TT Price']) || 0;
                    if (price > 0) {
                        totalPrice += price;
                        priceCount++;
                    }

                    totalInv += parseFloat(row.INV) || 0;
                    totalL30 += l30;
                });

                badgeRows.forEach(row => {
                    const l30 = parseFloat(row['TT L30']) || 0;
                    if (l30 === 0) {
                        zeroSoldCount++;
                    } else {
                        moreSoldCount++;
                    }
                });

                const avgGpft = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
                const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
                const avgRoi = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                updateRowsCountBadge();
                $('#total-sales-amt-badge').text(`Sales: $${Math.round(totalSales).toLocaleString()}`);
                $('#avg-gpft-badge').text(`GPFT: ${Math.round(avgGpft)}%`);
                $('#total-l30-badge').text(`L30: ${totalL30.toLocaleString()}`);
                $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
                $('#more-sold-count-badge').text(`> 0 Sold: ${moreSoldCount}`);
                $('#roi-percent-badge').text(`ROI%: ${Math.round(avgRoi)}%`);
                applyTtRoiBadgeStyle(avgRoi, totalCogs > 0);
                if (window.PriceGtLmpBadge && table) {
                    const ttChannel = (TTP_CFG && TTP_CFG.summaryChannel === 'tiktok2') ? 'tiktok2' : 'tiktok';
                    PriceGtLmpBadge.update('#tiktok-price-gt-lmp-badge', table.getData(), ttChannel, 'TT Price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#tiktok-price-lt80-lmp-badge', table.getData(), ttChannel, 'TT Price');
                }
                }
                let blueTriangleCount = 0;
                (table ? table.getData() : []).forEach(function(row) {
                    if (ttHasBlueTriangle(row)) blueTriangleCount++;
                });
                $('#tiktok-blue-triangle-badge').html(
                    '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
                );
                if (typeof syncTtTriangleBadgeState === 'function') syncTtTriangleBadgeState();

                let sumSpend30 = 0,
                    sumSpend1 = 0,
                    sumAdsViews30 = 0,
                    sumAdsClicks30 = 0,
                    sumAdsViews1 = 0,
                    sumAdsClicks1 = 0,
                    sumAdsSold30 = 0;
                data.forEach(row => {
                    sumSpend30 += parseFloat(row.spend_30) || 0;
                    sumSpend1 += parseFloat(row.spend_1) || 0;
                    sumAdsViews30 += parseInt(row.ads_views_30, 10) || 0;
                    sumAdsClicks30 += parseInt(row.ads_clicks_30, 10) || 0;
                    sumAdsViews1 += parseInt(row.ads_views_1, 10) || 0;
                    sumAdsClicks1 += parseInt(row.ads_clicks_1, 10) || 0;
                    sumAdsSold30 += parseInt(row.ads_sold_30, 10) || 0;
                });
                const adsCvr30 = sumAdsClicks30 > 0 ? (sumAdsSold30 / sumAdsClicks30) * 100 : 0;
                $('#tt-spend-30-badge').text('Spend 30: $' + Math.round(sumSpend30).toLocaleString());
                $('#tt-spend-1-badge').text('Spend 1: $' + Math.round(sumSpend1).toLocaleString());
                $('#tt-ads-views-30-badge').text('adsViews 30: ' + sumAdsViews30.toLocaleString());
                $('#tt-ads-clicks-30-badge').text('ads Clicks 30: ' + sumAdsClicks30.toLocaleString());
                $('#tt-ads-views-1-badge').text('ads view1: ' + sumAdsViews1.toLocaleString());
                $('#tt-ads-clicks-1-badge').text('ads clicks 1: ' + sumAdsClicks1.toLocaleString());
                $('#tt-ads-cvr-30-badge').text('ads CVR 30: ' + adsCvr30.toFixed(2) + '%');

                let sumAdsRevenue30 = 0,
                    sumTargetRoas = 0,
                    targetRoasCount = 0;
                data.forEach(row => {
                    sumAdsRevenue30 += parseFloat(row.ads_revenue_30) || 0;
                    const tRoas = parseFloat(row.target_roas) || 0;
                    if (tRoas > 0) {
                        sumTargetRoas += tRoas;
                        targetRoasCount++;
                    }
                });
                const adsRoas = sumSpend30 > 0 ? sumAdsRevenue30 / sumSpend30 : 0;
                const adsAcos = sumAdsRevenue30 > 0 ? (sumSpend30 / sumAdsRevenue30) * 100 : 0;
                const avgTargetRoas = targetRoasCount > 0 ? sumTargetRoas / targetRoasCount : 0;
                $('#tt-ads-roas-badge').text('ROAS: ' + adsRoas.toFixed(2));
                $('#tt-target-roas-badge').text('Target ROAS: ' + avgTargetRoas.toFixed(2));
                $('#tt-ads-acos-badge').text('Acos%: ' + adsAcos.toFixed(2) + '%');

                let sumGmvAdSoldL30 = 0,
                    sumGmvAdSoldL1 = 0,
                    sumGmvAdSalesL30 = 0,
                    sumGmvAdSalesL1 = 0,
                    sumGmvSpendL30 = 0,
                    sumGmvSpendL1 = 0,
                    sumGmvBudget = 0;
                data.forEach(row => {
                    sumGmvAdSoldL30 += parseInt(row.gmv_ad_sold_l30, 10) || 0;
                    sumGmvAdSoldL1 += parseInt(row.gmv_ad_sold_l1, 10) || 0;
                    sumGmvAdSalesL30 += parseFloat(row.gmv_ad_sales_l30) || 0;
                    sumGmvAdSalesL1 += parseFloat(row.gmv_ad_sales_l1) || 0;
                    sumGmvSpendL30 += parseFloat(row.gmv_spend_l30) || 0;
                    sumGmvSpendL1 += parseFloat(row.gmv_spend_l1) || 0;
                    sumGmvBudget += parseFloat(row.gmv_budget) || 0;
                });
                $('#tt-gmv-ad-sold-l30-badge').text('GMV Ad sold L30: ' + sumGmvAdSoldL30.toLocaleString());
                $('#tt-gmv-ad-sold-l1-badge').text('GMV Ad sold L1: ' + sumGmvAdSoldL1.toLocaleString());
                $('#tt-gmv-ad-sales-l30-badge').text('GMV Ad sales L30: $' + Math.round(sumGmvAdSalesL30).toLocaleString());
                $('#tt-gmv-ad-sales-l1-badge').text('GMV Ad sales L1: $' + Math.round(sumGmvAdSalesL1).toLocaleString());
                $('#tt-gmv-spend-l30-badge').text('GMV Spend L30: $' + Math.round(sumGmvSpendL30).toLocaleString());
                $('#tt-gmv-spend-l1-badge').text('GMV Spend L1: $' + Math.round(sumGmvSpendL1).toLocaleString());
                $('#tt-gmv-budget-badge').text('GMV Budget: $' + Math.round(sumGmvBudget).toLocaleString());
            }

            // Update Ads/Utilized count section (from table data: campaign, NR, spend, etc.)
            function updateUtilizedCounts() {
                if (!table) return;
                const data = table.getData('all').filter(row => {
                    const sku = row['(Child) sku'] || '';
                    return sku && !ttIsParentRow(row);
                });
                const processedSkus = new Set();
                const zeroInvSkus = new Set();
                const adSkuSet = new Set(); // SKU active in ads (hasCampaign) with >0 inventory
                let validSkuCount = 0,
                    missingCount = 0,
                    nraMissingCount = 0,
                    nraCount = 0;
                let totalSpend = 0,
                    totalSpendL30 = 0,
                    totalSpendL7 = 0,
                    totalAdSales = 0,
                    totalBudget = 0,
                    totalAdClicks = 0,
                    totalAdSold = 0;

                data.forEach(row => {
                    const sku = row['(Child) sku'] || '';
                    if (!sku) return;
                    const hasCampaign = row.hasCampaign === true || row.hasCampaign === 'true' || row
                        .hasCampaign === 1;
                    const nr = (row.NR || '').trim();
                    const inv = parseFloat(row.INV) || 0;

                    if (!processedSkus.has(sku)) {
                        processedSkus.add(sku);
                        validSkuCount++;
                        if (nr === 'NRA') nraCount++;
                    }
                    if (hasCampaign && inv > 0) adSkuSet.add(sku);
                    if (inv <= 0) zeroInvSkus.add(sku);
                    if (!hasCampaign) {
                        if (nr === 'NRA') {
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
                    totalSpend += parseFloat(row.spend) || 0;
                    totalSpendL30 += parseFloat(row.spend_l30) || 0;
                    totalSpendL7 += parseFloat(row.spend_l7) || 0;
                    totalBudget += parseFloat(row.budget) || 0;
                    totalAdClicks += parseInt(row.ad_clicks, 10) || 0;
                    totalAdSold += parseInt(row.ad_sold, 10) || 0;
                    const outRoas = parseFloat(row.out_roas) || 0;
                    const spend = parseFloat(row.spend) || 0;
                    if (outRoas > 0 && spend > 0) totalAdSales += spend * outRoas;
                });
                const zeroInvCount = zeroInvSkus.size;

                const raCount = Math.max(0, validSkuCount - nraCount);
                const avgAcos = totalAdSales > 0 ? (totalSpend / totalAdSales) * 100 : 0;
                const roas = totalSpend > 0 ? totalAdSales / totalSpend : 0;
                const avgClicks = adSkuSet.size > 0 ? totalAdClicks / adSkuSet.size : 0;

                $('#total-sku-count').text('Total SKU: ' + validSkuCount);
                $('#total-campaign-count').text('Campaign: ' + totalDistinctCampaigns);
                $('#ad-sku-count').text('Ad SKU: ' + adSkuSet.size);
                $('#missing-campaign-count').text('Missing Ad: ' + missingCount);
                $('#nra-missing-count').text('NRA MISSING: ' + nraMissingCount);
                $('#zero-inv-count').text('Zero INV: ' + zeroInvCount);
                $('#nra-count').text('NRA: ' + nraCount);
                $('#ra-count').text('RA: ' + raCount);
                $('#total-spend-l30-badge').text('L30 Spend: $' + Math.round(totalSpendL30).toLocaleString());
                $('#total-spend-l7-badge').text('L7 Spend: $' + Math.round(totalSpendL7).toLocaleString());
                $('#total-budget-badge').text('Budget: $' + totalBudget.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }));
                $('#total-ad-sales-badge').text('Ad Sales: $' + Math.round(totalAdSales).toLocaleString());
                $('#total-ad-sold-badge').text('Total L30 Ad Sold: ' + totalAdSold.toLocaleString());
                $('#total-ad-clicks-badge').text('Ad Clicks: ' + totalAdClicks.toLocaleString());
                $('#avg-clicks-badge').text('Avg Clicks: ' + Math.round(avgClicks).toLocaleString());
                $('#avg-acos-badge').text('Avg ACOS: ' + Math.round(avgAcos) + '%');
                $('#roas-badge').text('ROAS: ' + roas.toFixed(2));
            }

            // Build Column Visibility Dropdown — 4 groups: basics · pricing · advt · other
            const COL_VIS_CATEGORY_KEYS = ['basics', 'pricing', 'advt', 'other'];
            const COL_VIS_CATEGORY_LABELS = {
                basics: 'basics',
                pricing: 'pricing',
                advt: 'advt',
                other: 'other'
            };

            function classifyTtColumn(field, title) {
                const f = String(field || '');
                const t = String(title || field || '').replace(/<[^>]*>/g, '').trim();
                const tl = t.toLowerCase();

                // advt
                if (
                    /^(ad_cvr_pct|ads_cvr_30|ads_roas|target_roas|ads_acos_pct|ads_price|budget|spend|spend_30|spend_1|ad_sold|ad_clicks|ads_clicks_30|ads_clicks_1|ads_views_30|ads_views_1|acos|status|campaign_name|video_views|ads_views|affl_views|TACOS%|gmv_ad_sold_l30|gmv_ad_sold_l1|gmv_ad_sales_l30|gmv_ad_sales_l1|gmv_spend_l30|gmv_spend_l1|gmv_budget|gmv_status|gmv_approval)$/i.test(f) ||
                    /\b(ads?\s*cvr|target\s*roas|^roas$|budget|spend|ad\s*sold|ad\s*clicks|ads\s*clicks|adsviews|ads\s*view|acos|campaign|video\s*views|ads\s*views|affl\s*views|tacos|gmv)\b/i.test(tl) ||
                    /^status$/i.test(tl) ||
                    /^price$/i.test(tl) // ads Price column
                ) {
                    return 'advt';
                }

                // basics — product / inventory / listing (Ship checkbox lives here)
                if (
                    /^(image_path|Parent|\(Child\) sku|links_column|INV|L30|TT Dil%|TT L30|TT Stock|TT Ship|Ship_productmaster|NR|variation_req|video_req|video_uploaded|nrp|CVR%|cvr|t_views)$/i.test(f) ||
                    /\b(image|parent|sku|links|inv|ov\s*l30|^dil$|tt\s*l30|tt\s*stock|tt\s*1?\s*ship|bb\s*ship|^ship$|nra|variation|video\s*req|video\s*uploaded|nr\/?req|^cvr%?$|^t\s*views$)\b/i.test(tl)
                ) {
                    return 'basics';
                }

                // pricing
                if (
                    /^(TT Price|lmp_price|lmp_diff_pct|GPFT%|PFT %|ROI%|Profit|T Profit|Sales L30|LP_productmaster|SPRICE|SGPFT|SPFT|SROI|linked_lmp_skus|linked_lmp_sku_add)$/i.test(f) ||
                    /\b(prc|lmp|^diff$|gpft|pft|roi|profit|sales|^lp$|sprice|sgpft|spft|sroi|sku\s*link)\b/i.test(tl) ||
                    /^\+$/.test(t)
                ) {
                    return 'pricing';
                }

                return 'other';
            }

            function buildColumnDropdown() {
                const menu = document.getElementById('column-dropdown-menu');
                if (!menu || !table) return;
                menu.innerHTML = '';

                const groupsLi = document.createElement('li');
                groupsLi.className = 'col-vis-full';
                const groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';

                const lists = {};
                COL_VIS_CATEGORY_KEYS.forEach(function(cat) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    const titleEl = document.createElement('div');
                    titleEl.className = 'col-vis-group-title';
                    titleEl.textContent = COL_VIS_CATEGORY_LABELS[cat];
                    group.appendChild(titleEl);
                    const list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                });

                table.getColumns().forEach(function(col) {
                    const field = col.getField();
                    const title = col.getDefinition().title;
                    if (!field || field === '_select' || !title) return;
                    if (ALWAYS_HIDDEN_COLUMNS.includes(field)) return;

                    const cleanTitle = String(title).replace(/<[^>]*>/g, '').trim();
                    if (!cleanTitle) return;

                    const cat = classifyTtColumn(field, cleanTitle);
                    const li = document.createElement('li');
                    li.className = 'col-vis-item';
                    const isVisible = col.isVisible();
                    li.innerHTML =
                        '<label><input type="checkbox" class="column-toggle" data-field="' +
                        field.replace(/"/g, '&quot;') + '"' +
                        (isVisible ? ' checked' : '') + '> ' +
                        cleanTitle.replace(/</g, '&lt;') + '</label>';
                    lists[cat].appendChild(li);
                });

                groupsLi.appendChild(groupsWrap);
                menu.appendChild(groupsLi);
            }

            /*
             * Column visibility is persisted through the shared DB-backed
             * /tabulator-column-visibility endpoint (same one ebay-tabulator-view uses),
             * so booleans round-trip cleanly. Channel is unique per shop:
             *   - tiktok_pricing  (TikTok 1)
             *   - tiktok2_pricing (TikTok 2)
             */
            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && field !== '_select') {
                        // Never persist ads columns or Parent as visible
                        visibility[field] = (ADS_ONLY_COLUMN_FIELDS.includes(field) || ALWAYS_HIDDEN_COLUMNS
                            .includes(field)) ? false : col.isVisible();
                    }
                });
                // Mark ship-column migration done so later toggles are honored.
                visibility.ship_col_migrated = true;
                visibility.lmp_col_migrated = true;
                visibility.cvr_col_migrated = true;
                visibility.cvr_basics_migrated = true;
                visibility.t_views_basics_migrated = true;

                fetch(TTP_CFG.columnSet, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        channel: TTP_CFG.columnChannel || ('tiktok_pricing'),
                        visibility: visibility
                    })
                }).catch(err => console.error('Error saving TikTok column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                const channel = TTP_CFG.columnChannel || 'tiktok_pricing';
                fetch(TTP_CFG.columnGet + '?channel=' + encodeURIComponent(channel), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(r => r.json())
                    .then(visibility => {
                        if (visibility && typeof visibility === 'object' && Object.keys(visibility).length > 0) {
                            Object.keys(visibility).forEach(field => {
                                if (field === '_select' || ADS_ONLY_COLUMN_FIELDS.includes(field) ||
                                    ALWAYS_HIDDEN_COLUMNS.includes(field))
                            return; // never hide checkbox; never show ads/always-hidden from server
                                const col = table.getColumn(field);
                                if (col) {
                                    // Server stores real booleans via boolean validation,
                                    // but coerce defensively in case of legacy string values.
                                    const v = visibility[field];
                                    const visible = v === true || v === 1 || v === '1' || v === 'true';
                                    if (visible) col.show(); else col.hide();
                                }
                            });
                        }
                        // Force ads columns and Parent hidden by default
                        [...ADS_ONLY_COLUMN_FIELDS, ...ALWAYS_HIDDEN_COLUMNS].forEach(field => {
                            try {
                                const col = table.getColumn(field);
                                if (col) col.hide();
                            } catch (e) {}
                        });
                        // Ship column + Columns checkbox (both TikTok pages).
                        // One-time migrate: legacy prefs hid Ship_productmaster as a duplicate
                        // or kept the old "BB Ship" title.
                        try {
                            const shipCol = table.getColumn('Ship_productmaster');
                            if (shipCol) {
                                shipCol.updateDefinition({
                                    title: 'Ship',
                                    headerTooltip: 'Normal Ship from Shipping Master (not BB Ship)'
                                });
                                const v = visibility && typeof visibility === 'object' ? visibility : {};
                                const migrated = v.ship_col_migrated === true || v.ship_col_migrated === 1 ||
                                    v.ship_col_migrated === '1' || v.ship_col_migrated === 'true';
                                const shipOn = v['Ship_productmaster'] === true || v['Ship_productmaster'] === 1 ||
                                    v['Ship_productmaster'] === '1' || v['Ship_productmaster'] === 'true';
                                if (!migrated || shipOn) {
                                    shipCol.show();
                                }
                            }
                        } catch (e) {}
                        // LMP suite (LMP / Diff / Sku Link) — same on TT1 and TT2.
                        // One-time migrate: tiktok2_pricing prefs previously hid lmp_price.
                        try {
                            const v = visibility && typeof visibility === 'object' ? visibility : {};
                            const lmpMigrated = v.lmp_col_migrated === true || v.lmp_col_migrated === 1 ||
                                v.lmp_col_migrated === '1' || v.lmp_col_migrated === 'true';
                            ['lmp_price', 'lmp_diff_pct', 'linked_lmp_skus', 'linked_lmp_sku_add'].forEach(function(field) {
                                const col = table.getColumn(field);
                                if (!col) return;
                                const on = v[field] === true || v[field] === 1 || v[field] === '1' || v[field] === 'true';
                                if (!lmpMigrated || on || v[field] === undefined) {
                                    col.show();
                                }
                            });
                        } catch (e) {}
                        // Listing CVR (TT L30 ÷ T views) — force into the Dil / TT L30 cluster.
                        try {
                            const v = visibility && typeof visibility === 'object' ? visibility : {};
                            const cvrMigrated = v.cvr_basics_migrated === true || v.cvr_basics_migrated === 1 ||
                                v.cvr_basics_migrated === '1' || v.cvr_basics_migrated === 'true';
                            const cvrCol = table.getColumn('cvr') || table.getColumn('CVR%');
                            if (cvrCol) {
                                const on = v.cvr === true || v.cvr === 1 || v.cvr === '1' || v.cvr === 'true' ||
                                    v['CVR%'] === true || v['CVR%'] === 1 || v['CVR%'] === '1' ||
                                    v['CVR%'] === 'true';
                                if (!cvrMigrated || on || v.cvr === undefined) {
                                    cvrCol.show();
                                }
                            }
                        } catch (e) {}
                        // T Views (video + ads + affl) — same listing cluster as CVR.
                        try {
                            const v = visibility && typeof visibility === 'object' ? visibility : {};
                            const viewsMigrated = v.t_views_basics_migrated === true || v.t_views_basics_migrated === 1 ||
                                v.t_views_basics_migrated === '1' || v.t_views_basics_migrated === 'true';
                            const viewsCol = table.getColumn('t_views');
                            if (viewsCol) {
                                const on = v.t_views === true || v.t_views === 1 || v.t_views === '1' || v.t_views === 'true';
                                if (!viewsMigrated || on || v.t_views === undefined) {
                                    viewsCol.show();
                                }
                            }
                        } catch (e) {}
                        // Checkbox column always first & visible
                        try {
                            const selectCol = table.getColumn('_select');
                            if (selectCol) selectCol.show();
                        } catch (e) {}
                        buildColumnDropdown();
                    })
                    .catch(err => console.error('Error loading TikTok column visibility:', err));
            }

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'Parent',
                    skuField: '(Child) sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    isParentRow: ttIsParentRow,
                    onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                    onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
                });
                ParentExpand.bind();
            }

            // Wait for table to be built
            table.on('tableBuilt', function() {
                buildColumnDropdown();
                applyColumnVisibilityFromServer();
            });

            table.on('dataLoaded', function(data) {
                if (Array.isArray(data) && data.length) {
                    // ParentExpand.expand() replaces the table data with a single parent's
                    // children, which fires dataLoaded again. Without this guard allTableData would
                    // be overwritten by that subset and later filters would run against only
                    // those rows, leaving the table stuck on the expanded group.
                    if (window.ParentExpand && ParentExpand.isExpanded()) return;
                    allTableData = data;
                    if (window.ParentExpand) ParentExpand.captureDataset(data);
                }
                function afterLoad() {
                    setTimeout(function() {
                        ttRefreshAllSkuRows();
                        applyFilters();
                        updateSummary();
                        updateUtilizedCounts();
                        updateTtSummaryBadgeStyles();
                    }, 100);
                }
                fetch(TTP_CFG.distinctCampaign)
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(res) {
                        if (res && typeof res.totalDistinctCampaigns !== 'undefined') {
                            totalDistinctCampaigns = parseInt(res.totalDistinctCampaigns, 10) || 0;
                        }
                        afterLoad();
                    })
                    .catch(function() {
                        afterLoad();
                    });
            });

            table.on('renderComplete', function() {
                setTimeout(function() {
                    updateSummary();
                    updateUtilizedCounts();
                }, 100);
            });

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.classList.contains('column-toggle')) {
                    const field = e.target.dataset.field;
                    const col = table.getColumn(field);
                    if (col) {
                        if (e.target.checked) {
                            col.show();
                        } else {
                            col.hide();
                        }
                        saveColumnVisibilityToServer();
                    }
                }
            });

            // Export CSV button (from CSV dropdown)
            $('#export-btn').on('click', function(e) {
                e.preventDefault();
                const exportData = [];
                const visibleColumns = table.getColumns().filter(col => col.isVisible() && col
                .getField() !== '_select');

                const headers = visibleColumns.map(col => {
                    let title = col.getDefinition().title || col.getField();
                    return title.replace(/<[^>]*>/g, '');
                });
                exportData.push(headers);

                const data = table.getData("active");
                data.forEach(row => {
                    const rowData = [];
                    visibleColumns.forEach(col => {
                        const field = col.getField();
                        let value = row[field];

                        if (value === null || value === undefined) {
                            value = '';
                        } else if (typeof value === 'number') {
                            value = parseFloat(value.toFixed(2));
                        } else if (typeof value === 'string') {
                            value = value.replace(/<[^>]*>/g, '').trim();
                        }
                        rowData.push(value);
                    });
                    exportData.push(rowData);
                });

                let csv = '';
                exportData.forEach(row => {
                    csv += row.map(cell => {
                        if (typeof cell === 'string' && (cell.includes(',') || cell
                                .includes('"') || cell.includes('\n'))) {
                            return '"' + cell.replace(/"/g, '""') + '"';
                        }
                        return cell;
                    }).join(',') + '\n';
                });

                const blob = new Blob([csv], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'tiktok_pricing_export_' + new Date().toISOString().slice(0,
                    10) + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showToast('Export downloaded successfully!', 'success');
            });

            // ─────────────────────────────────────────────────────────────
            //  LMP Modal: open / fetch / render / add / delete
            // ─────────────────────────────────────────────────────────────
            let ttCurrentLmpSku = null;
            let ttCurrentLinkedLmpSkus = [];
            let ttEditCompetitorId = null;

            function ttEscAttr(value) {
                if (value == null) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function ttResetCompetitorForm(keepSku) {
                ttEditCompetitorId = null;
                $('#ttEditCompId').val('');
                if (!keepSku) {
                    $('#ttAddCompSku').val(ttCurrentLmpSku || '');
                } else {
                    $('#ttAddCompSku').val(keepSku);
                }
                $('#ttAddCompProductId').val('');
                $('#ttAddCompPrice').val('');
                $('#ttAddCompShip').val('');
                $('#ttAddCompTitle').val('');
                $('#ttAddCompLink').val('');
                $('#ttAddCompRegion').val('US');
                $('#ttCompFormHeaderText').text('Add New Competitor');
                $('#ttCompFormHeaderIcon').attr('class', 'fa fa-plus-circle');
                $('#ttCompFormHeader').removeClass('bg-warning text-dark').addClass('bg-success text-white');
                $('#ttCompFormCard').removeClass('border-warning').addClass('border-success');
                $('#ttCompSubmitBtnText').text('Add');
                $('#ttCompSubmitBtn').find('i').attr('class', 'fa fa-plus');
                $('#ttCompSubmitBtn').css({ background: '#ff0050', borderColor: '#ff0050' });
            }

            function ttEnterEditCompetitorMode(item) {
                if (!item || !item.id) return;
                ttEditCompetitorId = item.id;
                $('#ttEditCompId').val(item.id);
                $('#ttAddCompSku').val(item.sku || ttCurrentLmpSku || '');
                $('#ttAddCompProductId').val(item.product_id || '');
                $('#ttAddCompPrice').val(item.price != null ? parseFloat(item.price) : '');
                $('#ttAddCompShip').val(item.shipping_cost != null ? parseFloat(item.shipping_cost) : 0);
                $('#ttAddCompTitle').val(item.product_title || item.title || '');
                $('#ttAddCompLink').val(item.product_link || item.link || '');
                $('#ttAddCompRegion').val(item.region || 'US');
                $('#ttCompFormHeaderText').text('Edit Competitor');
                $('#ttCompFormHeaderIcon').attr('class', 'fa fa-edit');
                $('#ttCompFormHeader').removeClass('bg-success text-white').addClass('bg-warning text-dark');
                $('#ttCompFormCard').removeClass('border-success').addClass('border-warning');
                $('#ttCompSubmitBtnText').text('Update');
                $('#ttCompSubmitBtn').find('i').attr('class', 'fa fa-save');
                $('#ttCompSubmitBtn').css({ background: '#ffc107', borderColor: '#ffc107', color: '#212529' });
                const formCard = document.getElementById('ttCompFormCard');
                if (formCard) formCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function ttLoadCompetitorsModal(sku, linkedLmpSkus) {
                ttCurrentLmpSku = sku;
                ttCurrentLinkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
                $('#ttLmpSku').text(sku);
                ttResetCompetitorForm(sku);

                const modalEl = document.getElementById('ttLmpModal');
                bootstrap.Modal.getOrCreateInstance(modalEl).show();

                $('#ttLmpDataList').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border" role="status" style="color:#ff0050;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading competitors...</p>
                    </div>
                `);

                // Fetch competitors merged across Sku Link LMP group (same as outer LMP column).
                // Use linked_lmp_skus[] so PHP receives a real array (traditional:true collapses it).
                const query = { sku: sku };
                (ttCurrentLinkedLmpSkus || []).forEach(function (linkedSku, idx) {
                    query['linked_lmp_skus[' + idx + ']'] = linkedSku;
                });
                $.ajax({
                    url: '/tiktok/competitors',
                    method: 'GET',
                    data: query,
                    success: function(response) {
                        if (response.success) {
                            ttRenderCompetitorsList(response.competitors, response.lowest_price);
                            ttPatchLmpOnTable(
                                response.lowest_price != null ? parseFloat(response.lowest_price) : null,
                                parseInt(response.total_count, 10) || 0
                            );
                        } else {
                            ttRenderCompetitorsList([], null);
                            ttPatchLmpOnTable(null, 0);
                        }
                    },
                    error: function() {
                        ttRenderCompetitorsList([], null);
                    }
                });
            }

            function ttRenderCompetitorsList(competitors, lowestPrice) {
                if (!competitors || competitors.length === 0) {
                    $('#ttLmpDataList').html(`
                        <div class="alert alert-info mb-0">
                            <i class="fa fa-info-circle"></i> No competitors found for this SKU. Add your first one above, or use
                            <a href="/repricer/tiktok-search" target="_blank" class="alert-link">/repricer/tiktok-search</a> to discover and bulk-assign.
                        </div>
                    `);
                    return;
                }

                window.ttCurrentLmpList = competitors;
                let l1Price = (window.LmpIgnore && LmpIgnore.l1) ? LmpIgnore.l1(competitors) : null;
                if (l1Price === null && lowestPrice != null) l1Price = parseFloat(lowestPrice);

                let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm align-middle">';
                html += `
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;">#</th>
                            <th style="width:60px;">Image</th>
                            <th style="width:140px;">Product ID</th>
                            <th style="width:220px;">Title</th>
                            <th>Seller</th>
                            <th style="width:80px;">Price</th>
                            <th style="width:70px;">Ship</th>
                            <th style="width:80px;">Range</th>
                            <th style="width:70px;">Rating</th>
                            <th style="width:80px;">Reviews</th>
                            <th style="width:80px;">Sold</th>
                            <th style="width:60px;">Region</th>
                            <th style="width:60px;">Link</th>
                            ${LmpIgnore.header()}
                            <th style="width:90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                `;

                competitors.forEach(function(item, index) {
                    const basePrice = parseFloat(item.price) || 0;
                    const shipCost = parseFloat(item.shipping_cost) || 0;
                    const landedPrice = basePrice + shipCost;
                    const ignored = !!item.ignored;
                    const isLowest = !ignored && l1Price && Math.abs(landedPrice - l1Price) < 0.01;
                    const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '');
                    const priceFormatted = '$' + basePrice.toFixed(2);
                    const priceBadge = ignored
                        ? `<strong>${priceFormatted}</strong> <span class="badge bg-secondary">Ignored</span>`
                        : (isLowest
                            ? `<span class="badge bg-success">${priceFormatted} <i class="fa fa-trophy"></i></span>`
                            : `<strong>${priceFormatted}</strong>`);
                    const shipHtml = shipCost === 0
                        ? '<span class="badge bg-info">FREE</span>'
                        : '$' + shipCost.toFixed(2);

                    const productLink = item.link || item.product_link || '#';
                    const title = item.title || item.product_title || 'N/A';
                    const seller = item.seller_name || (item.brand_name || '—');
                    const imageUrl = item.image || '';
                    const imageHtml = imageUrl
                        ? `<img src="${ttEscAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" />`
                        : '<span style="color:#999;">—</span>';

                    let rangeHtml = '<span style="color:#999;">—</span>';
                    if (item.min_price && item.max_price && item.min_price !== item.max_price) {
                        rangeHtml = `<small style="color:#666;">$${parseFloat(item.min_price).toFixed(2)}<br>– $${parseFloat(item.max_price).toFixed(2)}</small>`;
                    }
                    const rating = item.rating
                        ? `<span style="color:#ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>`
                        : '<span style="color:#999;">—</span>';
                    const reviews = item.reviews
                        ? `<span>${parseInt(item.reviews).toLocaleString()}</span>`
                        : '<span style="color:#999;">—</span>';
                    const sold = item.sold_count
                        ? `<span style="color:#00796B;font-weight:600;">${parseInt(item.sold_count).toLocaleString()}</span>`
                        : '<span style="color:#999;">—</span>';

                    html += `
                        <tr class="${rowClass}">
                            <td class="text-center"><strong>${index + 1}</strong></td>
                            <td class="text-center">${imageHtml}</td>
                            <td><span class="text-primary" style="font-weight:600;font-size:11px;font-family:monospace;">${ttEscAttr(item.product_id || 'N/A')}</span></td>
                            <td style="font-size:11px;" title="${ttEscAttr(title)}">${ttEscAttr(String(title).substring(0, 80))}${String(title).length > 80 ? '…' : ''}</td>
                            <td style="font-size:11px;">${ttEscAttr(seller)}</td>
                            <td>${priceBadge}</td>
                            <td class="text-center">${shipHtml}</td>
                            <td class="text-center">${rangeHtml}</td>
                            <td class="text-center">${rating}</td>
                            <td class="text-center">${reviews}</td>
                            <td class="text-center">${sold}</td>
                            <td class="text-center"><span class="badge bg-secondary">${ttEscAttr(item.region || 'US')}</span></td>
                            <td class="text-center">
                                <a href="${ttEscAttr(productLink)}" target="_blank" class="btn btn-sm btn-info" title="Open on TikTok Shop">
                                    <i class="fa fa-external-link-alt"></i>
                                </a>
                            </td>
                            <td class="text-center align-middle">${LmpIgnore.checkbox(item, 'tiktok', ttCurrentLmpSku || item.sku || '')}</td>
                            <td class="text-center text-nowrap">
                                <button type="button" class="btn btn-sm btn-warning tt-edit-lmp-btn"
                                    data-id="${item.id}"
                                    data-sku="${ttEscAttr(item.sku || '')}"
                                    data-product-id="${ttEscAttr(item.product_id || '')}"
                                    data-price="${ttEscAttr(basePrice)}"
                                    data-shipping="${ttEscAttr(shipCost)}"
                                    data-title="${ttEscAttr(title === 'N/A' ? '' : title)}"
                                    data-link="${ttEscAttr(productLink === '#' ? '' : productLink)}"
                                    data-region="${ttEscAttr(item.region || 'US')}"
                                    title="Edit this competitor">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger tt-delete-lmp-btn"
                                    data-id="${item.id}"
                                    data-product-id="${ttEscAttr(item.product_id)}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += '</tbody></table></div>';
                $('#ttLmpDataList').html(html);
            }
            LmpIgnore.bind({
                modal: '#ttLmpModal',
                marketplace: 'tiktok',
                sku: function() { return ttCurrentLmpSku || ''; },
                onToggled: function(id, ignored) {
                    (window.ttCurrentLmpList || []).forEach(function(c) {
                        if (String(c.id) === String(id)) c.ignored = ignored;
                    });
                    const l1 = LmpIgnore.l1(window.ttCurrentLmpList || []);
                    ttRenderCompetitorsList(window.ttCurrentLmpList || [], l1);
                    if (typeof ttPatchLmpOnTable === 'function') {
                        ttPatchLmpOnTable(l1, (window.ttCurrentLmpList || []).length);
                    }
                }
            });

            // "$price (N)" / "N/A" trigger inside the LMP column
            $(document).on('click', '.view-tt-lmp-competitors', function(e) {
                e.preventDefault();
                const sku = $(this).attr('data-sku') || $(this).data('sku');
                if (!sku) return;
                let linkedSkus = [];
                const rawLinked = $(this).attr('data-linked-skus');
                if (rawLinked) {
                    try { linkedSkus = JSON.parse(rawLinked) || []; } catch (err) { linkedSkus = []; }
                } else {
                    linkedSkus = $(this).data('linked-skus') || [];
                    if (typeof linkedSkus === 'string') {
                        try { linkedSkus = JSON.parse(linkedSkus) || []; } catch (err) { linkedSkus = []; }
                    }
                }
                if (!Array.isArray(linkedSkus)) {
                    linkedSkus = [];
                }
                ttLoadCompetitorsModal(sku, linkedSkus);
            });

            function ttExtractProductIdFromLink(link) {
                const m = String(link || '').match(/\/(?:pdp|product)\/(?:[^\/?]+\/)?(\d{8,})/i)
                    || String(link || '').match(/[?&]product_id=(\d{8,})/i)
                    || String(link || '').match(/(\d{15,})/);
                return m ? m[1] : '';
            }

            function ttPatchLmpOnTable(lowestPrice, totalCount) {
                if (typeof table === 'undefined' || !table) return;
                const group = new Set((ttCurrentLinkedLmpSkus || []).map(function(s) {
                    return String(s || '').trim().toUpperCase();
                }));
                if (ttCurrentLmpSku) group.add(String(ttCurrentLmpSku).trim().toUpperCase());
                table.getRows().forEach(function(r) {
                    const d = r.getData();
                    if (!d || d.is_parent || d.is_parent_summary) return;
                    const sku = String(d['(Child) sku'] || d.sku || '').trim().toUpperCase();
                    if (!sku || !group.has(sku)) return;
                    r.update({
                        lmp_price: lowestPrice,
                        lmp_entries_total: totalCount,
                    });
                });
            }

            // Add / Update Competitor form
            $('#ttAddCompetitorForm').on('submit', function(e) {
                e.preventDefault();
                const editId = ttEditCompetitorId || $('#ttEditCompId').val();
                const isEdit = !!editId;
                let productId = $('#ttAddCompProductId').val().trim();
                const productLink = $('#ttAddCompLink').val().trim() || null;
                if (!productId && productLink) {
                    productId = ttExtractProductIdFromLink(productLink);
                    if (productId) $('#ttAddCompProductId').val(productId);
                }
                const payload = {
                    product_id: productId,
                    price: parseFloat($('#ttAddCompPrice').val()) || 0,
                    shipping_cost: parseFloat($('#ttAddCompShip').val()) || 0,
                    product_title: $('#ttAddCompTitle').val().trim() || null,
                    product_link: productLink,
                    region: $('#ttAddCompRegion').val() || 'US',
                    marketplace: 'tiktok',
                    _token: '{{ csrf_token() }}',
                };
                if (!isEdit) {
                    payload.sku = $('#ttAddCompSku').val();
                } else {
                    payload.id = editId;
                }
                if (!payload.product_id || !payload.price) {
                    alert('Product ID and Price are required.');
                    return;
                }
                const $btn = $('#ttCompSubmitBtn');
                $btn.prop('disabled', true);
                $.ajax({
                    url: isEdit ? '/tiktok/competitors/update' : '/tiktok/competitors',
                    method: 'POST',
                    data: payload,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(resp) {
                        if (resp.success) {
                            ttResetCompetitorForm(ttCurrentLmpSku);
                            ttLoadCompetitorsModal(ttCurrentLmpSku, ttCurrentLinkedLmpSkus);
                        } else {
                            alert(resp.error || (isEdit ? 'Failed to update competitor' : 'Failed to add competitor'));
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || (isEdit ? 'Failed to update competitor' : 'Failed to add competitor');
                        alert(msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            $('#ttCompClearBtn').on('click', function() {
                ttResetCompetitorForm(ttCurrentLmpSku);
            });

            // Edit competitor — load row into the form above
            $(document).on('click', '.tt-edit-lmp-btn', function() {
                const $btn = $(this);
                ttEnterEditCompetitorMode({
                    id: $btn.data('id'),
                    sku: $btn.attr('data-sku') || $btn.data('sku') || ttCurrentLmpSku,
                    product_id: $btn.attr('data-product-id') || $btn.data('product-id') || '',
                    price: $btn.data('price'),
                    shipping_cost: $btn.data('shipping'),
                    product_title: $btn.attr('data-title') || '',
                    product_link: $btn.attr('data-link') || '',
                    region: $btn.attr('data-region') || 'US',
                });
            });

            // Delete competitor
            $(document).on('click', '.tt-delete-lmp-btn', function() {
                const id = $(this).data('id');
                if (!id) return;
                if (!confirm('Delete this competitor mapping?')) return;
                $.ajax({
                    url: '/tiktok/competitors/delete',
                    method: 'POST',
                    data: { id: id, _token: '{{ csrf_token() }}' },
                    success: function(resp) {
                        if (resp.success) {
                            ttResetCompetitorForm(ttCurrentLmpSku);
                            ttLoadCompetitorsModal(ttCurrentLmpSku, ttCurrentLinkedLmpSkus);
                            if (typeof table !== 'undefined' && table) table.replaceData();
                        } else {
                            alert(resp.error || 'Failed to delete');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'Failed to delete';
                        alert(msg);
                    }
                });
            });
        });
    </script>
@endsection
