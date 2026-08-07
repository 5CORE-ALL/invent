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

        /* Column visibility dropdown — 4 columns */
        .column-dropdown-multicol {
            min-width: 560px;
            padding: 6px 4px;
            column-count: 4;
            column-gap: 8px;
            max-height: 420px;
            overflow-y: auto;
        }
        .column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        .column-dropdown-multicol > li.column-dropdown-span-all {
            column-span: all;
            -webkit-column-span: all;
        }
        .column-dropdown-multicol .dropdown-item {
            padding: 3px 10px;
            white-space: nowrap;
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
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
                        <span class="badge flex-shrink-0" id="rd-ads-percent-badge" style="background-color: #fd7e14; color: white; font-weight: bold;" title="Reverb Ads% (Bump fees ÷ L30 Sales) — from /all-marketplace-master (same source Amazon Ads badge uses)">Ads: {{ isset($reverbAdsPercent) ? round((float) $reverbAdsPercent, 1) . '%' : 'N/A' }}</span>
                        <span class="badge bg-info flex-shrink-0" id="npft-badge" style="color: black; font-weight: bold;" title="PFT% = GPFT% − Ads% (same as /amazon-tabulator-view)">PFT: 0%</span>
                        <span class="badge flex-shrink-0" id="groi-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="Weighted GROI% = Σ[sold_qty×(RV Price×take%−LP−Ship)] ÷ Σ(sold_qty×LP) — same method as /temu-decrease, using normal ship">GROI: 0%</span>
                        <span class="badge flex-shrink-0" id="nroi-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="NROI% = (Total PFT − Ad Spend) ÷ COGS × 100; Ad Spend = Ads% × Sales (same as /amazon-tabulator-view)">NROI: 0%</span>
                        <span class="badge flex-shrink-0" id="total-views-badge" style="background-color: #0d6efd; color: white; font-weight: bold;" title="Sum of Views for currently filtered rows (same as Amazon Sess30 — raw, not ÷10)">Views: 0</span>
                        <span class="badge flex-shrink-0" id="avg-cvr-badge" style="background-color: #20c997; color: #000; font-weight: bold;" title="Overall CVR = Σ(RV L30) ÷ Σ(Views) × 100 — same Amazon formula as A_L30 ÷ Sess30">CVR: 0%</span>
                        <span class="badge flex-shrink-0" id="rd-qty-sum-badge" style="background-color: #17a2b8; color: white; font-weight: bold;" title="Sum of RD Qty column (reverb_daily_qty) for currently filtered rows">RD Qty: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="SKUs with RV L30 = 0 (same as Amazon 0 Sold on A_L30)">0 Sold: 0</span>
                        <span class="badge flex-shrink-0" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="SKUs with RV L30 &gt; 0 (same as Amazon Sold &gt;0 on A_L30)">&gt; 0 Sold: 0</span>
                        <span class="badge bg-danger flex-shrink-0" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amazon">&lt; Amz: 0</span>
                        <span class="badge flex-shrink-0" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amazon">&gt; Amz: 0</span>
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
                           sold → RV L30 > 0  (same as Amazon A_L30 Sold filter)
                           zero → RV L30 = 0
                         Single source of truth. The #zero-sold-count-badge / #more-sold-count-badge
                         click handlers (and the ?badge=zero_sold|more_sold URL deep-link) all
                         drive this dropdown value, so badges + dropdown + URL stay in sync. --}}
                    <select id="sold-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;"
                            title="Filter by RV L30 sold quantity (same role as Amazon A_L30 Sold filter)">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm flex-shrink-0" style="width: 120px;"
                            title="Filter by price push status (same as Amazon)">
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

                    <input type="text" id="sku-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search SKU..." style="max-width: 160px;">
                    <input type="text" id="parent-search" class="form-control form-control-sm flex-shrink-0" placeholder="Search Parent..." style="max-width: 160px;">
                </div>

            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
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
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
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

    <!-- LMP Competitors Modal (same pattern as ebay/amazon tabulator) -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Reverb Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fa fa-plus-circle"></i> Add New Competitor</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Reverb Item ID *</label>
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., 67894128">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price"
                                            step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping"
                                            name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link"
                                            placeholder="https://reverb.com/item/...">
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
                                        <input type="text" class="form-control" id="addCompTitle"
                                            name="product_title" placeholder="Product title">
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
@endsection

@section('script-bottom')
<script>
    /** Shared column visibility — same /tabulator-column-visibility endpoint as Amazon (channel_tabulator_column_settings). */
    const TABULATOR_COLUMN_CHANNEL = 'reverb_tabulator';
    const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
    const REVERB_DAILY_TOTALS_URL = @json(url('reverb-daily-data-totals-json'));
    // Columns that stay hidden even when "Show All Columns" is used.
    const adsOnlyColumnFields = ['Parent', 'Missing_Ad', 'bump_req', 'Bump', 'RE_BID'];
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

    /** Take-home margin factor (Reverb ~0.85). */
    function reverbTakeRate(rowData) {
        const pct = parseFloat(rowData && rowData.percentage);
        return (isFinite(pct) && pct > 0 && pct <= 1) ? pct : 0.85;
    }

    /**
     * Net SNROI — same shape as Amazon amazonComputeNetSroi / NROI badge:
     *   (gross profit $ − ad spend $) / COGS × 100
     * where ad spend $ = SPRICE × Ads%/100 and COGS = LP.
     */
    function reverbComputeNetSroi(rowData) {
        if (!rowData) return null;
        const sprice = parseFloat(rowData.SPRICE);
        const lp = parseFloat(rowData['LP_productmaster']);
        if (!isFinite(sprice) || sprice <= 0 || !isFinite(lp) || lp <= 0) return null;
        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
        const margin = reverbTakeRate(rowData);
        const adsFrac = (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0) / 100;
        const grossPft = (sprice * margin) - ship - lp;
        const adSpend = sprice * adsFrac;
        return ((grossPft - adSpend) / lp) * 100;
    }

    /** Net NROI on live RV Price (Amazon NROI column shape). */
    function reverbComputeNetRoi(rowData) {
        if (!rowData) return null;
        const price = parseFloat(rowData['RV Price']);
        const lp = parseFloat(rowData['LP_productmaster']);
        if (!isFinite(price) || price <= 0 || !isFinite(lp) || lp <= 0) return null;
        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
        const margin = reverbTakeRate(rowData);
        const adsFrac = (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0) / 100;
        const grossPft = (price * margin) - ship - lp;
        const adSpend = price * adsFrac;
        return ((grossPft - adSpend) / lp) * 100;
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

    $(document).ready(function() {
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

                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft = Math.round((sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0)) * 100) / 100;
                const sroi  = lp > 0 ? Math.round(((newSprice * margin - lp - ship) / lp) * 100 * 100) / 100 : 0;

                row.update({
                    SPRICE: newSprice,
                    SGPFT: sgpft,
                    SPFT: spft,
                    SROI: sroi,
                    has_custom_sprice: true
                });
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

                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft = Math.round((sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0)) * 100) / 100;
                const sroi  = lp > 0 ? Math.round(((newSprice * margin - lp - ship) / lp) * 100 * 100) / 100 : 0;

                row.update({
                    SPRICE: newSprice,
                    SGPFT: sgpft,
                    SPFT: spft,
                    SROI: sroi,
                    has_custom_sprice: true
                });
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

        // Clear SPRICE button
        $('#clear-sprice-btn').on('click', function() {
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
            'RV L30', 'reverb_daily_qty', 'reverb_daily_qty_x_subtotal', 'reverb_daily_qty_x_amount', 'R Stock',
            'Views', 'CVR',
            'L30', 'RV Dil%', 'MAP', 'Profit', 'Sales L30', 'LP_productmaster', 'Ship_productmaster'
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

                        // Calculate SGPFT, SPFT, SROI
                        const percentage = rowData['percentage'] || 0.85;
                        const lp = rowData['LP_productmaster'] || 0;
                        const ship = rowData['Ship_productmaster'] || 0;

                        const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                        const spft = Math.round((sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0)) * 100) / 100;
                        const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;

                        // Update SPRICE and calculated values in table
                        row.update({
                            SPRICE: newSprice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi,
                            has_custom_sprice: true
                        });

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
                        // Calculate SGPFT, SPFT, SROI
                        const percentage = rowData['percentage'] || 0.85;
                        const lp = rowData['LP_productmaster'] || 0;
                        const ship = rowData['Ship_productmaster'] || 0;
                        
                        const sgpft = amazonPrice > 0 ? Math.round(((amazonPrice * percentage - ship - lp) / amazonPrice) * 100 * 100) / 100 : 0;
                        const spft = Math.round((sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0)) * 100) / 100;
                        const sroi = lp > 0 ? Math.round(((amazonPrice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                        
                        // Update the row with SPRICE and calculated values
                        row.update({
                            SPRICE: amazonPrice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi,
                            has_custom_sprice: true
                        });
                        
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
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
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

            // Get all rows and filter by selected SKUs
            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                
                if (selectedSkus.has(sku)) {
                    // Clear SPRICE in table
                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0
                    });
                    
                    // Store update for backend saving
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    
                    clearedCount++;
                }
            });

            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
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
                    if (response.spft_percent !== undefined) {
                        row.update({ SPFT: response.spft_percent });
                    }
                    if (response.sroi_percent !== undefined) {
                        row.update({ SROI: response.sroi_percent });
                    }
                    if (response.sgpft_percent !== undefined) {
                        row.update({ SGPFT: response.sgpft_percent });
                    }
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

        function loadReverbCompetitorsModal(sku, linkedLmpSkus) {
            $('#lmpSku').text(sku);
            $('#addCompSku').val(sku);
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
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;
                        renderReverbCompetitorsList(response.competitors, response.lowest_price);
                    } else {
                        $('#lmpDataList').html(`
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                            </div>
                        `);
                    }
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

        function renderReverbCompetitorsList(competitors, lowestPrice) {
            if (!competitors || competitors.length === 0) {
                $('#lmpDataList').html(`
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> No competitors found for this SKU</div>
                `);
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `<thead class="table-dark"><tr>
                <th>Image</th><th>Item ID</th><th>Price</th><th>Shipping</th><th>Total</th><th>Title</th><th>Actions</th>
            </tr></thead><tbody>`;
            competitors.forEach(function(item) {
                const isLowest = Math.abs(parseFloat(item.total_price) - parseFloat(lowestPrice)) < 0.01;
                const rowClass = isLowest ? 'table-success' : '';
                const badge = isLowest ? '<span class="badge bg-success ms-2">Lowest</span>' : '';
                const productLink = item.link || `https://reverb.com/item/${item.item_id}`;
                const imageCell = item.image
                    ? `<img src="${escAttr(item.image)}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                    : '<span class="text-muted">—</span>';
                html += `<tr class="${rowClass}">
                    <td>${imageCell}</td>
                    <td><span class="text-primary" style="font-weight:600;font-size:11px;">${escAttr(item.item_id || 'N/A')}</span></td>
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
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
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
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';
                        
                        if (dil < 25) color = '#a00211';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
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
                    title: "Bump Req",
                    field: "bump_req",
                    hozAlign: "center",
                    headerSort: false,
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || String(value).trim() === '') {
                            value = 'REQ';
                        }
                        return `<select class="form-select form-select-sm bump-req-dropdown" 
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "Bump%",
                    field: "Bump",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '<span class="text-muted">-</span>';
                        return `<span style="font-weight: 600;">${value}</span>`;
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
                        if (value === null || value === undefined || value === '') return '<span class="text-muted">-</span>';
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "Missing L",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
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
                        const value = cell.getValue();
                        
                        if (!value) return '';
                        
                        if (value === 'Map') {
                            return '<span style="color: #28a745; font-weight: bold; background-color: #d4edda; padding: 2px 6px; border-radius: 3px;">MAP</span>';
                        } else if (value.includes('N Map|')) {
                            const diff = value.split('|')[1];
                            return `<span style="color: #dc3545; font-weight: bold; background-color: #f8d7da; padding: 2px 6px; border-radius: 3px;">N MP (${diff})</span>`;
                        }
                        return '';
                    }
                },
                {
                    title: "Views",
                    field: "Views",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        // Raw views (Amazon Sess30 style) — no ÷10
                        const views = parseFloat(cell.getValue()) || 0;
                        return Math.round(views).toLocaleString();
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

                        let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';

                        if (!lmpPrice && totalCompetitors === 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #999; text-decoration: none; cursor: pointer; font-size: 12px;" title="Add competitors">N/A</a>`;
                        } else if (lmpPrice) {
                            const finalPrice = parseFloat(lmpPrice) || 0;
                            const priceColor = (rvPrice > 0 && finalPrice < rvPrice) ? '#dc3545' : '#28a745';
                            html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">$${finalPrice.toFixed(2)}</span>`;
                        }

                        if (totalCompetitors > 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                                <i class="fa fa-eye"></i> View ${totalCompetitors}
                            </a>`;
                        } else if (lmpPrice) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                                <i class="fa fa-plus"></i> Add
                            </a>`;
                        }

                        html += '</div>';
                        return html;
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
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';
                        
                        return `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">$${value.toFixed(2)}</span>`;
                    },
                    width: 80
                },
                {
                    title: "SGPFT",
                    field: "SGPFT",
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
                    title: "Sroi",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Gross SROI (Amazon "Sroi" / SGROI) — Ads% not cut here
                        const row = cell.getRow().getData();
                        const sprice = parseFloat(row.SPRICE) || 0;
                        if (sprice <= 0) return '';
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '';
                        const percent = parseFloat(value);
                        if (isNaN(percent)) return '';
                        return `<span style="${(window.MetricPctColors && MetricPctColors.styleForField((cell.getField&&cell.getField())||'GROI%', percent)) || ('color:'+reverbRoiColor(percent)+';font-weight:600;')}">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SNPFT",
                    field: "SNPFT",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const ads = parseFloat(REVERB_CHANNEL_ADS_PCT) || 0;
                        const aVal = parseFloat(aRow.getData().SGPFT);
                        const bVal = parseFloat(bRow.getData().SGPFT);
                        const aSpft = isNaN(aVal) ? 0 : (aVal - ads);
                        const bSpft = isNaN(bVal) ? 0 : (bVal - ads);
                        return aSpft - bSpft;
                    },
                    formatter: function(cell) {
                        // Amazon-style: SNPFT = SGPFT − Ads% (blank when no SPRICE)
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData.SPRICE) || 0;
                        const rawGpft = rowData.SGPFT;
                        if (sprice <= 0 || rawGpft === null || rawGpft === undefined || rawGpft === '') return '';
                        const sgpft = parseFloat(rawGpft);
                        if (isNaN(sgpft)) return '';
                        const percent = sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0);
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
                    headerSort: false,
                    width: 55,
                    // Amazon/eBay icon states: ✓ ready, ✓✓ pushed/applied, ✕ error
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const sprice = parseFloat(rowData.SPRICE || 0);
                        const status = rowData.SPRICE_STATUS || null;
                        const pushedValue = rowData.SPRICE_PUSHED_VALUE;
                        const updatedAt = rowData.SPRICE_STATUS_UPDATED_AT;
                        const pushedBy = rowData.SPRICE_PUSHED_BY;

                        if (!sku || !sprice || sprice <= 0) {
                            return '<span style="color:#999;">N/A</span>';
                        }

                        let icon = '<i class="fas fa-check"></i>';
                        let iconColor = '#28a745';
                        let titleText = `Push $${sprice.toFixed(2)} to Reverb`;

                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            iconColor = '#ffc107';
                            titleText = 'Price pushing in progress...';
                        } else if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price pushed to Reverb (Double-click to mark as Applied)';
                        } else if (status === 'applied') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price applied to Reverb';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-x"></i>';
                            iconColor = '#dc3545';
                            titleText = 'Error pushing price to Reverb — click to retry';
                        }

                        const tipParts = [titleText];
                        if (pushedValue !== null && pushedValue !== undefined) {
                            tipParts.push(`Last: $${parseFloat(pushedValue).toFixed(2)}`);
                        }
                        if (updatedAt) tipParts.push(updatedAt);
                        if (pushedBy) tipParts.push(`by ${pushedBy}`);

                        return `<button type="button" class="btn btn-sm reverb-push-price-btn btn-circle"
                            data-sku="${String(sku).replace(/"/g, '&quot;')}"
                            data-price="${sprice}"
                            data-status="${status || ''}"
                            title="${tipParts.join(' | ').replace(/"/g, '&quot;')}"
                            style="border:none;background:none;color:${iconColor};padding:0;cursor:pointer;font-size:16px;">
                            ${icon}
                        </button>`;
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

        // Bump Req dropdown change handler (like NRA)
        $(document).on('change', '.bump-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const $rowEl = $cell.closest('.tabulator-row');
            const row = table.getRow($rowEl[0]);
            const rowData = row.getData();
            const sku = rowData['(Child) sku'];
            const newValue = $(this).val();
            $.ajax({
                url: '{{ url("/reverb-save-bump-req") }}',
                method: 'POST',
                data: {
                    sku: sku,
                    bump_req: newValue,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`${sku}: Bump Req updated to ${newValue}`, 'success');
                    row.update({ bump_req: newValue });
                },
                error: function(xhr) {
                    showToast(`Failed to update Bump Req for ${sku}`, 'error');
                }
            });
        });

        // SPRICE cell edited - save to database
        table.on('cellEdited', function(cell) {
            if (cell.getField() === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                // Recalculate SGPFT, SPFT, SROI
                const percentage = rowData['percentage'] || 0.85;
                const lp = rowData['LP_productmaster'] || 0;
                const ship = rowData['Ship_productmaster'] || 0;
                
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft = Math.round((sgpft - (parseFloat(REVERB_CHANNEL_ADS_PCT) || 0)) * 100) / 100;
                const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                
                row.update({
                    SGPFT: sgpft,
                    SPFT: spft,
                    SROI: sroi,
                    has_custom_sprice: true
                });
                
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

        // Single-row push: ✓ / ✓✓ / ✕  (Amazon/eBay pattern)
        // Short delay so dblclick (pushed → applied) does not also fire a re-push.
        let reverbPushClickTimer = null;
        $(document).on('click', '.reverb-push-price-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const currentStatus = $btn.attr('data-status') || '';
            const sku = $btn.attr('data-sku') || $btn.data('sku');

            // Double-click → mark Applied (Amazon/eBay)
            if (e.originalEvent && e.originalEvent.detail === 2) {
                if (reverbPushClickTimer) {
                    clearTimeout(reverbPushClickTimer);
                    reverbPushClickTimer = null;
                }
                if (currentStatus !== 'pushed' || !sku) return;

                $.ajax({
                    url: '/reverb-update-sprice-status',
                    method: 'POST',
                    data: { sku: sku, status: 'applied', _token: csrfToken() },
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

            if (reverbPushClickTimer) clearTimeout(reverbPushClickTimer);
            reverbPushClickTimer = setTimeout(function() {
                reverbPushClickTimer = null;
                const $rowEl = $btn.closest('.tabulator-row');
                const row = table.getRow($rowEl[0]);
                if (!row) return;
                const price = parseFloat(row.getData().SPRICE || $btn.attr('data-price') || 0);

                if (!sku || !price || price <= 0) {
                    showToast('Set a valid SPRICE (> 0) before pushing', 'error');
                    return;
                }

                $btn.prop('disabled', true);
                pushReverbPriceForRow(row, sku, price).then(function(result) {
                    $btn.prop('disabled', false);
                    if (result.ok) {
                        showToast(result.message || `Price pushed to Reverb for ${sku}`, 'success');
                    } else {
                        showToast(result.message || `Failed to push ${sku}`, 'error');
                    }
                });
            }, 280);
        });

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

            // DIL filter (calculated as L30 / INV * 100)
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
            $('#total-views-badge').text(`Views: ${Math.round(totalViewsRaw).toLocaleString()}`);
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

        /**
         * Build Columns dropdown (4-col). Same as Amazon/Shopify B2C:
         * checkbox state prefers saved server map, else current column visibility.
         */
        function buildColumnDropdown(savedVisibility) {
            const menu = document.getElementById('column-dropdown-menu');
            if (!menu || !table) return;

            const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
            let html = `<li class="dropdown-item column-dropdown-span-all">
                            <a class="fw-bold" href="#" id="show-all-columns-btn" style="text-decoration: none; color: inherit;">
                                <i class="fa fa-eye"></i> Show All Columns</a>
                        </li>
                        <li class="column-dropdown-span-all"><hr class="dropdown-divider"></li>`;

            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                const field = def.field;
                const title = def.title;
                if (!field || field === '_select' || !title) return;

                const isVisible = map.hasOwnProperty(field) ? (map[field] !== false) : col.isVisible();
                const label = String(title).replace(/<[^>]*>/g, '');
                html += `<li class="dropdown-item">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                        ${label}
                    </label>
                </li>`;
            });

            menu.innerHTML = html;
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
                    // Parent + ads-only columns stay hidden (not part of normal pricing view).
                    adsOnlyColumnFields.forEach(function(field) {
                        const col = table.getColumn(field);
                        if (col) col.hide();
                    });
                    buildColumnDropdown(map);
                })
                .catch(err => {
                    console.error('Error applying column visibility:', err);
                    buildColumnDropdown();
                });
        }

        // Wait for table to be built — apply saved columns first (same as Amazon).
        table.on('tableBuilt', function() {
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

        // Toggle column from dropdown — save immediately (Amazon pattern).
        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox') return;
            const field = e.target.getAttribute('data-field') || e.target.dataset.field;
            if (!field) return;
            const col = table.getColumn(field);
            if (!col) return;
            if (e.target.checked) {
                col.show();
            } else {
                col.hide();
            }
            saveColumnVisibilityToServer();
        });

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

