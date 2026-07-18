@extends('layouts.vertical', ['title' => 'Shein Pricing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-tableholder { scrollbar-width: thin; scrollbar-color: #c1c1c1 transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar { width: 8px; height: 8px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-track { background: transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        /* ── Parent row – identical to amazon_tabulator_view ── */
        .tabulator-row.ae-parent-row,
        .tabulator-row.ae-parent-row .tabulator-cell {
            background-color: #bde0ff !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-row.ae-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
            color: #1e3a5f;
        }
        .tabulator-row.ae-parent-row:hover,
        .tabulator-row.ae-parent-row:hover .tabulator-cell {
            background-color: #93c5fd !important;
        }

        /* Custom pagination label (match ebay-tabulator-view) */
        .tabulator-paginator label {
            margin-right: 5px;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* Sku Link LMP (mirrors /ebay-tabulator-view) */
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

        /* Toolbar: compact controls, wrap to next row (matches /ebay2-tabulator-view).
           Do NOT use overflow-x — it clips Bootstrap dropdown menus (Columns).
           z-index: table is a later sibling so it paints over the toolbar unless
           the toolbar creates a higher stacking context (Columns / Sample menus). */
        .shein-toolbar-row {
            row-gap: 4px;
            position: relative;
            z-index: 1055;
        }
        .shein-toolbar-row > .form-select,
        .shein-toolbar-row .form-select.pricing-filter-item,
        .shein-toolbar-row > .form-control,
        .shein-toolbar-row > .btn,
        .shein-toolbar-row > .dropdown > .btn,
        .shein-toolbar-row > .btn-group > .btn {
            padding: 3px 10px;
            font-size: 0.8125rem;
            line-height: 1.3;
            min-height: 30px;
        }
        .shein-toolbar-row .form-select {
            padding-right: 24px;
            background-position: right 6px center;
            width: auto;
            display: inline-block;
        }
        .shein-toolbar-row .dropdown,
        .shein-toolbar-row .btn-group {
            position: relative;
            z-index: 1056;
        }
        .shein-toolbar-row .dropdown-menu {
            font-size: 0.8125rem;
            z-index: 1060 !important;
        }
        #shein-pricing-table {
            position: relative;
            z-index: 1;
        }
        .shein-toolbar-row .pricing-filter-item .form-control {
            padding: 3px 8px;
            font-size: 0.8125rem;
            min-height: 30px;
        }
        .shein-toolbar-row .pricing-filter-item .btn {
            padding: 3px 8px;
            min-height: 30px;
        }

        /* Badges above the filter controls (matches /ebay2-tabulator-view) */
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #summary-stats .shein-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        #summary-stats .shein-summary-badge-row > .badge {
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
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shein Pricing',
        'sub_title'  => 'Separate pricing page (sales page unchanged — Shein)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2 d-flex flex-column">

                    {{-- Filter toolbar (layout / compact UI matches /ebay2-tabulator-view) --}}
                    <div class="d-flex align-items-center flex-wrap gap-2 shein-toolbar-row mb-1">
                        <input type="text" id="pricing-sku-search" class="form-control form-control-sm"
                            placeholder="Search SKU..." style="width: 160px; display: inline-block;">
                        <input type="text" id="pricing-parent-search" class="form-control form-control-sm"
                            placeholder="Search Parent..." style="width: 160px; display: inline-block;">

                        <select id="ae-inv-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">INV</option>
                            <option value="zero">0 INV</option>
                            <option value="more" selected>INV &gt; 0</option>
                        </select>

                        <select id="ae-stock-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">Sh Stock</option>
                            <option value="zero">0 Sh Stock</option>
                            <option value="more">Sh Stock &gt; 0</option>
                        </select>

                        <select id="ae-al30-filter" class="form-select form-select-sm pricing-filter-item"
                            title="Excludes 0 inventory items">
                            <option value="all">Sh L30</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>

                        <select id="ae-nrl-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">Status</option>
                            <option value="REQ" selected>REQ Only</option>
                            <option value="NR">NR Only</option>
                        </select>

                        <select id="ae-gpft-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40plus">Above 40%</option>
                        </select>

                        <select id="ae-roi-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>

                        <select id="ae-map-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">MAP</option>
                            <option value="map">MP only</option>
                            <option value="nmap">N MP only</option>
                        </select>

                        <select id="ae-sprice-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">SPRICE</option>
                            <option value="blank">Blank SPRICE only</option>
                        </select>

                        {{-- DIL Filter (plain select — matches /ebay2-tabulator-view) --}}
                        <select id="ae-dil-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">DIL%</option>
                            <option value="red">Red &lt;25%</option>
                            <option value="green">Green 25-50%</option>
                            <option value="pink">Pink 50%+</option>
                        </select>

                        <select id="ae-row-type-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all" selected>All Rows</option>
                            <option value="skus">SKUs</option>
                        </select>

                        <div class="dropdown d-inline-block pricing-filter-item">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="ae-column-visibility-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Columns">
                                <i class="fa fa-eye"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="ae-column-visibility-dropdown"
                                id="ae-column-dropdown-menu" style="max-height: 400px; overflow-y: auto;">
                            </ul>
                        </div>

                        <button id="ae-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Cycle: Off → Decrease → Increase">
                            <i class="fas fa-exchange-alt"></i> Price %
                        </button>

                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export">
                            <i class="fas fa-file-excel"></i>
                        </button>

                        {{-- Target ROI% (compact — same UX as /ebay2-tabulator-view) --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                            id="ae-target-roi-controls"
                            title="Target ROI% — sets SPRICE = (LP × (1 + Target ROI%/100) + Ship) / margin on every selected row">
                            <label for="ae-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                            </label>
                            <input type="number" id="ae-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target ROI% applied to all selected rows when you click Apply">
                            <button id="ae-apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                                title="Compute & save SPRICE for every selected row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        {{-- Target GPFT% (compact — same UX as /ebay2-tabulator-view) --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                            id="ae-target-gpft-controls"
                            title="Target GPFT% — sets SPRICE = (LP + Ship) / (margin − Target GPFT%/100) on every selected row">
                            <label for="ae-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                            </label>
                            <input type="number" id="ae-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target GPFT% applied to all selected rows when you click Apply">
                            <button id="ae-apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                                title="Compute & save SPRICE for every selected row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary pricing-filter-item">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <div class="btn-group pricing-filter-item">
                            <button type="button" class="btn btn-sm btn-warning dropdown-toggle" data-bs-toggle="dropdown"
                                aria-expanded="false" title="Sample / Upload Price">
                                <i class="fas fa-file-import"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="{{ route('shein.pricing.sample') }}">
                                        <i class="fas fa-download text-info"></i> Sample CSV
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#uploadPriceSheetModal">
                                        <i class="fas fa-upload text-warning"></i> Upload Price
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="btn-group align-items-center pricing-filter-item" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-sm btn-light" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-sm btn-primary" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-sm btn-warning" style="display: none;" title="Stop navigation and show all">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-sm btn-light" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Discount input (shown when Price % is active) --}}
                    <div id="ae-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <span id="ae-selected-skus-count" class="fw-bold text-secondary"></span>
                            <select id="ae-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            <input type="number" id="ae-discount-input" class="form-control form-control-sm"
                                placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="ae-apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                            <button id="ae-clear-sprice-btn" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    {{-- Summary badges above filters via CSS order (matches /ebay2-tabulator-view: no hover chart) --}}
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                        <div class="shein-summary-badge-row">
                            <span class="badge bg-danger fs-6 p-2" id="ae-zero-sold-badge" style="font-weight:700;cursor:pointer;" title="Click to filter 0 sold items">0 Sold: 0</span>
                            <span class="badge fs-6 p-2" id="ae-more-sold-badge" style="font-weight:700;cursor:pointer;background:#b6e0fe;color:#0f172a;" title="Click to filter sold items">&gt; 0 Sold: 0</span>
                            <span class="badge bg-primary fs-6 p-2" id="ae-total-sales-badge" style="font-weight:700;color:#111;" title="Same as /shein-tabulator: Σ (product_price × qty) from uploaded orders">Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2" id="ae-total-al30-badge" style="font-weight:700;color:#111;" title="Same as /shein-tabulator Total Quantity">Qty: 0</span>
                            <span class="badge bg-info fs-6 p-2" id="ae-avg-gpft-badge" style="font-weight:700;color:#111;" title="Same as /shein-tabulator PFT%: Σ PFT / Σ Sales (sold product_price)">GPFT: 0%</span>
                            <span class="badge bg-secondary fs-6 p-2" id="ae-avg-roi-badge" style="font-weight:700;color:#fff;" title="Same as /shein-tabulator ROI%: Σ PFT / Σ (LP × qty)">GROI: 0%</span>
                            <span class="badge bg-success fs-6 p-2 d-none" id="ae-total-pft-badge" style="font-weight:700;color:#111;" aria-hidden="true">PFT: $0</span>
                            <span class="badge bg-secondary fs-6 p-2" id="ae-total-sku-badge" style="font-weight:700;">SKU: 0</span>
                            <span class="badge bg-danger fs-6 p-2" id="ae-missing-badge" style="font-weight:700;cursor:pointer;" title="Click to filter Missing L">M L: 0</span>
                            <span class="badge fs-6 p-2 d-none" id="ae-map-badge" style="font-weight:700;cursor:pointer;background:#198754;color:#fff;" title="Click to filter Map rows" aria-hidden="true">Map: 0</span>
                            <span class="badge fs-6 p-2" id="ae-nmap-badge" style="font-weight:700;cursor:pointer;background:#a71d2a;color:#fff;" title="Click to filter N Map rows">N Map: 0</span>
                            <span class="badge bg-warning fs-6 p-2 d-none" id="ae-avg-dil-badge" style="font-weight:700;color:#111;" aria-hidden="true">DIL%: 0%</span>
                        </div>
                    </div>

                    <div id="shein-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge trend modal — eBay 3 style: wide top-aligned dialog, line chart + stats only --}}
    <div class="modal fade" id="aeBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl shadow-none modal-dialog-scrollable" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="aeBadgeChartTitle">Shein — Summary (Daily snapshot)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2 me-2">
                        <select id="aeBadgeChartRange" class="form-select form-select-sm bg-white"
                            style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="aeBadgeLineWrap" style="display:none;height:38vh;flex-direction:row;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="aeBadgeLineCanvas"></canvas>
                        </div>
                        <div id="aeBadgeStatPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="aeBadgeHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="aeBadgeMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="aeBadgeLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="aeBadgeLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="aeBadgeNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadPriceSheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Pricing Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="priceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted">Headers: sku, price, stock</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="uploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="sheinEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="sheinEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="sheinSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="sheinBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sheinSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal (same as Amazon page) -->
    <div class="modal fade" id="sheinLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Competitors for SKU: <span id="sheinLmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add / Edit Competitor LMP -->
                    <div class="card mb-3 border-success" id="sheinLmpFormCard">
                        <div class="card-header bg-success text-white py-2" id="sheinLmpFormHeader">
                            <strong><i class="fa fa-plus-circle" id="sheinLmpFormHeaderIcon"></i> <span id="sheinLmpFormHeaderText">Add Competitor LMP</span></strong>
                            <span class="float-end small" id="sheinLmpFormHeaderHint">Max 4 per SKU</span>
                        </div>
                        <div class="card-body py-2">
                            <form id="sheinAddLmpForm" class="row g-2 align-items-end">
                                <input type="hidden" id="sheinEditLmpSlot" value="">
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small"><strong>SKU</strong></label>
                                    <input type="text" class="form-control form-control-sm" id="sheinAddLmpSku" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="sheinAddLmpPrice" placeholder="9.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-1 small"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control form-control-sm" id="sheinAddLmpLink" placeholder="https://us.shein.com/...">
                                </div>
                                <div class="col-md-3 d-flex gap-1">
                                    <button type="submit" class="btn btn-success btn-sm flex-grow-1" id="sheinAddLmpBtn">
                                        <i class="fa fa-plus"></i> <span id="sheinAddLmpBtnText">Add</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="sheinCancelEditLmpBtn" title="Cancel edit">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="sheinLmpDataList"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sku Link LMP Modal (same as /ebay-tabulator-view; shared sku.link.lmp.* routes) --}}
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
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let table = null;
        let summaryDataCache = [];
        // Sales / GPFT / GROI from /shein-tabulator upload (product_price × qty) — not special_offer
        let salesPageTotals = null;

        // Badge-click filter flags (identical to TikTok pattern)
        let aeMissingActive  = false;
        let aeMapActive      = false;
        let aeNMapActive     = false;
        let aeZeroSoldActive = false;
        let aeMoreSoldActive = false;

        function aeApplyBadgeFilterFromUrl() {
            const badge = (new URLSearchParams(window.location.search).get('badge') || '').toLowerCase();
            if (!badge || !table) return;
            aeMissingActive = aeMapActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
            if (badge === 'missing') aeMissingActive = true;
            else if (badge === 'map') aeMapActive = true;
            else if (badge === 'nmap') aeNMapActive = true;
            else if (badge === 'zero_sold') aeZeroSoldActive = true;
            else if (badge === 'more_sold') aeMoreSoldActive = true;
            else return;
            applyFilters();
        }

        // Price Mode (mirrors TikTok exactly)
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let selectedSkus = new Set();

        function roundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01;
        }

        /** When clamped SPRICE is under $20, use cents only; at $20+ apply .99 retail rounding. */
        function finalizeSprice(price) {
            const clamped = Math.max(0.99, parseFloat(price) || 0);
            if (clamped < 20) {
                return Math.round(clamped * 100) / 100;
            }
            return roundToRetailPrice(clamped);
        }

        function syncPriceModeUi() {
            const $btn = $('#ae-price-mode-btn');
            // Select column stays visible at all times (ebay-tabulator-view pattern);
            // we only toggle the Price Mode button label / selection state here.
            if (decreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                return;
            }
            if (increaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                return;
            }
            $btn.removeClass('btn-danger btn-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price %');
            selectedSkus.clear();
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const cnt = selectedSkus.size;
            $('#ae-selected-skus-count').text(`${cnt} SKU${cnt !== 1 ? 's' : ''} selected`);
            $('#ae-discount-container').toggle(cnt > 0 && (decreaseModeActive || increaseModeActive));
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("shein.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates: updates },
                success: function(res) {
                    if (res.success) console.log('AE SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('AE SPRICE save error:', xhr.responseJSON);
                }
            });
        }

        function applyAeDiscount() {
            const discountType = $('#ae-discount-type').val();
            const discountVal  = parseFloat($('#ae-discount-input').val());
            if (isNaN(discountVal) || discountVal === 0 || selectedSkus.size === 0) return;

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row     = rows[0];
                const rowData = row.getData();
                // Base on Sp. Price (special_offer) first — same as GPFT/calc_price on server.
                // Using original_price first made "decrease" raise SPRICE when original ≫ listing.
                const currentPrice = parseFloat(rowData.special_offer) || parseFloat(rowData.original_price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = increaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = increaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = finalizeSprice(newSprice);

                const margin = parseFloat(rowData._margin) || 1;
                const lp     = parseFloat(rowData.lp)   || 0;
                const ship   = parseFloat(rowData.ship)  || 0;
                // Same formulas as GPFT / GROI
                const sgpft  = newSprice > 0 ? Math.round(((newSprice * margin - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const sroi   = lp > 0        ? Math.round(((newSprice * margin - lp - ship)  / lp)       * 100 * 100) / 100 : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            $('#ae-discount-input').val('');
        }

        function clearSpriceForSelected() {
            if (!selectedSkus.size) return;
            if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;
            const updates = [];
            table.getRows().forEach(row => {
                const d = row.getData();
                if (selectedSkus.has(d.sku) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveSpriceUpdates(updates);
        }

        function money(value) {
            return `$${(parseFloat(value) || 0).toFixed(2)}`;
        }

        /** INV vs Shein stock = Map if diff ≤ 3 OR ≤ 3% of INV (same as amazon_tabulator_view). */
        function sheinInvWithinMapTolerance(inv, sheinStock) {
            const invNum = parseFloat(inv) || 0;
            const shNum = parseFloat(sheinStock) || 0;
            if (invNum <= 0) return true;
            const diff = Math.abs(invNum - shNum);
            if (diff <= 3 + 1e-9) return true;
            return diff <= invNum * 0.03 + 1e-9;
        }

        /** NR/REQ status — prefer editable nr_req (shein_listing_statuses), fall back to meta NR. Same source eBay uses. */
        function sheinNrReq(row) {
            return ((row && (row.nr_req || row.NR)) || '').toString().trim().toUpperCase();
        }

        /**
         * Missing L — same logic as eBay isEbayMissingL:
         * not listed (no Shein price / special offer), NR/REQ === 'REQ', INV > 0, not a parent row.
         */
        function sheinRowIsMissingL(row) {
            if (!row || row.is_parent) return false;
            const inv = parseFloat(row.inv) || 0;
            const nr = sheinNrReq(row);
            const isMissingShein = !!row.is_missing_shein || (String(row.missing || '').trim().toUpperCase() === 'M');
            const price = parseFloat(row.special_offer) || 0;
            return inv > 0 && nr === 'REQ' && (isMissingShein || price <= 0);
        }

        /** MAP / N MP helpers — same structure as /ebay2-tabulator-view (both sides need stock). */
        function sheinRowIsListedForMap(row) {
            if (!row || row.is_parent) return false;
            const inv = parseFloat(row.inv) || 0;
            if (inv <= 0) return false;
            if (sheinNrReq(row) !== 'REQ') return false;
            if (row.is_missing_shein || (parseFloat(row.special_offer) || 0) <= 0) return false;
            return (parseFloat(row.shein_stock) || 0) > 0;
        }
        function sheinRowIsMap(row) {
            if (!sheinRowIsListedForMap(row)) return false;
            return sheinInvWithinMapTolerance(row.inv, row.shein_stock);
        }
        function sheinRowIsNMap(row) {
            if (!sheinRowIsListedForMap(row)) return false;
            return !sheinInvWithinMapTolerance(row.inv, row.shein_stock);
        }

        // ── applyFilters (mirrors TikTok applyFilters) ────────────────
        // Play / Pause parent navigation state
        let shPlayUniqueParents = [];
        let isShPlayActive = false;
        let currentShPlayParentIndex = -1;

        function normalizeShParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildShUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizeShParentKey(r.parent);
                if (p && !seen[p]) { seen[p] = true; list.push(p); }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updateShPlayButtonStates() {
            $('#play-backward').prop('disabled', !isShPlayActive || currentShPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isShPlayActive || currentShPlayParentIndex >= shPlayUniqueParents.length - 1);
        }
        function startShPlay() {
            shPlayUniqueParents = buildShUniqueParents();
            if (shPlayUniqueParents.length === 0) return;
            isShPlayActive = true;
            currentShPlayParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateShPlayButtonStates();
        }
        function stopShPlay() {
            isShPlayActive = false;
            currentShPlayParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyFilters();
            updateShPlayButtonStates();
        }
        function nextShParent() {
            if (!isShPlayActive || currentShPlayParentIndex >= shPlayUniqueParents.length - 1) return;
            currentShPlayParentIndex++;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateShPlayButtonStates();
        }
        function previousShParent() {
            if (!isShPlayActive || currentShPlayParentIndex <= 0) return;
            currentShPlayParentIndex--;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateShPlayButtonStates();
        }
        $('#play-auto').on('click', startShPlay);
        $('#play-pause').on('click', stopShPlay);
        $('#play-forward').on('click', nextShParent);
        $('#play-backward').on('click', previousShParent);

        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            // Play navigation: only show current parent's group
            if (isShPlayActive && shPlayUniqueParents.length > 0 && currentShPlayParentIndex >= 0) {
                const currentKey = shPlayUniqueParents[currentShPlayParentIndex];
                if (currentKey) {
                    table.addFilter(function(d) {
                        const p = normalizeShParentKey(d.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                }
                return;
            }

            const skuSearch  = ($('#pricing-sku-search').val() || '').toLowerCase().trim();
            const parentSearch = ($('#pricing-parent-search').val() || '').toLowerCase().trim();
            const rowType    = $('#ae-row-type-filter').val();
            const invFilter  = $('#ae-inv-filter').val();
            const stockFilter= $('#ae-stock-filter').val();
            const gpftFilter = $('#ae-gpft-filter').val();
            const roiFilter  = $('#ae-roi-filter').val();
            const al30Filter = $('#ae-al30-filter').val();
            const mapFilter  = $('#ae-map-filter').val();
            const nrlFilter  = $('#ae-nrl-filter').val() || 'all';
            const spriceFilter = $('#ae-sprice-filter').val() || 'all';
            const dilColor   = $('#ae-dil-filter').val() || 'all';

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }
            if (parentSearch) {
                table.addFilter(d => String(d.parent || '').toLowerCase().includes(parentSearch));
            }

            // Row type filter (All / Parents / SKUs) – same as Amazon
            if (rowType === 'parents') {
                table.addFilter(d => d.is_parent === true);
            } else if (rowType === 'skus') {
                table.addFilter(d => !d.is_parent);
            }

            // Inventory filter
            if (invFilter === 'zero') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }

            // Shein Stock filter
            if (stockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.shein_stock, 10) || 0) === 0);
            } else if (stockFilter === 'more') {
                table.addFilter(d => (parseInt(d.shein_stock, 10) || 0) > 0);
            }

            // Status NR/REQ (matches /ebay2-tabulator-view)
            if (nrlFilter === 'REQ' || nrlFilter === 'NR') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    return sheinNrReq(d) === nrlFilter;
                });
            }

            // GPFT filter — slabs match ebay-tabulator-view
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10')     return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20')    return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30')    return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40')    return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40plus')   return gpft >= 40;
                    return true;
                });
            }

            // ROI% filter
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40')    return roi < 40;
                    if (roiFilter === '40-75')   return roi >= 40 && roi < 75;
                    if (roiFilter === '75-125')  return roi >= 75 && roi < 125;
                    if (roiFilter === 'gt125')   return roi >= 125;
                    return true;
                });
            }

            // AL30 filter (excludes 0 inventory rows, same as TikTok T L30)
            if (al30Filter !== 'all') {
                table.addFilter(function(d) {
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const al30 = parseFloat(d.al30) || 0;
                    if (al30Filter === '0')      return al30 === 0;
                    if (al30Filter === '0-10')   return al30 > 0 && al30 <= 10;
                    if (al30Filter === '10plus') return al30 > 10;
                    return true;
                });
            }

            // Map filter (same rows as MAP column / ebay2)
            if (mapFilter === 'map') {
                table.addFilter(d => sheinRowIsMap(d));
            } else if (mapFilter === 'nmap') {
                table.addFilter(d => sheinRowIsNMap(d));
            }

            // Blank SPRICE only (matches /ebay2-tabulator-view)
            if (spriceFilter === 'blank') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const sp = d.sprice;
                    return sp === null || sp === undefined || sp === '' || parseFloat(sp) === 0 || isNaN(parseFloat(sp));
                });
            }

            // DIL% filter — slabs match /ebay2-tabulator-view: red <25, green 25-50, pink 50+
            if (dilColor !== 'all') {
                table.addFilter(function(d) {
                    const inv   = parseFloat(d.inv)    || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil   = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilColor === 'red')   return dil < 25;
                    if (dilColor === 'green') return dil >= 25 && dil < 50;
                    if (dilColor === 'pink')  return dil >= 50;
                    return true;
                });
            }

            // Badge-click filters
            if (aeMissingActive) {
                table.addFilter(d => sheinRowIsMissingL(d));
            }
            if (aeMapActive) {
                table.addFilter(d => sheinRowIsMap(d));
            }
            if (aeNMapActive) {
                table.addFilter(d => sheinRowIsNMap(d));
            }
            if (aeZeroSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            if (aeMoreSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === "object") {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            return [];
        }

        function updateSummary(rowsInput = null) {
            // Always use the full loaded dataset (summaryDataCache) — same source the
            // daily chart snapshot is saved from. Filtered table views must not change
            // Sales / GPFT / GROI / etc. or badges diverge from the graph.
            let rows = normalizeRows(summaryDataCache);
            if (!rows.length) {
                rows = normalizeRows(rowsInput);
            }
            if (!rows.length && table && typeof table.getData === "function") {
                rows = normalizeRows(table.getData());
            }
            if (!rows.length) return;

            let missingCount = 0, mapCount = 0, nmapCount = 0;
            let zeroSold = 0, moreSold = 0;
            let dilSum = 0, dilCount = 0;

            const childCount = rows.filter(r => !r.is_parent).length;

            rows.forEach(row => {
                if (row.is_parent) return;
                const al30   = parseFloat(row.al30)   || 0;
                const inv    = parseFloat(row.inv)    || 0;
                const ovL30  = parseFloat(row.ov_l30) || 0;
                const isMissingL = sheinRowIsMissingL(row);

                if (al30 === 0) zeroSold++; else moreSold++;
                if (inv > 0) { dilSum += (ovL30 / inv) * 100; dilCount++; }

                if (isMissingL) {
                    missingCount++;
                } else if (sheinRowIsMap(row)) {
                    mapCount++;
                } else if (sheinRowIsNMap(row)) {
                    nmapCount++;
                }
            });

            // Sales / Qty / GPFT / GROI — identical to /shein-tabulator (uploaded order product_price)
            const sp = salesPageTotals || {};
            const totalSales = parseFloat(sp.total_sales) || 0;
            const totalPft   = parseFloat(sp.total_pft) || 0;
            const totalQty   = parseInt(sp.total_quantity, 10) || 0;
            const avgGpft    = parseFloat(sp.pft_percentage);
            const avgRoi     = parseFloat(sp.roi_percentage);
            const hasSalesTotals = salesPageTotals != null;
            const avgDil  = dilCount > 0 ? dilSum / dilCount : 0;

            $('#ae-total-sku-badge').text(`SKU: ${childCount.toLocaleString()}`);
            $('#ae-total-sales-badge').text(hasSalesTotals && totalSales > 0 ? `Sales: $${Math.round(totalSales).toLocaleString()}` : (hasSalesTotals ? 'Sales: $0' : 'Sales: –'));
            $('#ae-total-pft-badge').text(hasSalesTotals ? `PFT: $${Math.round(totalPft).toLocaleString()}` : 'PFT: –');
            $('#ae-total-al30-badge').text(hasSalesTotals ? `Qty: ${totalQty.toLocaleString()}` : 'Qty: –');
            $('#ae-avg-gpft-badge').text(hasSalesTotals && Number.isFinite(avgGpft) ? `GPFT: ${Math.round(avgGpft)}%` : 'GPFT: –');
            $('#ae-missing-badge').text(`M L: ${missingCount.toLocaleString()}`);
            $('#ae-map-badge').text(`Map: ${mapCount.toLocaleString()}`);
            $('#ae-nmap-badge').text(`N Map: ${nmapCount.toLocaleString()}`);
            $('#ae-zero-sold-badge').text(`0 Sold: ${zeroSold.toLocaleString()}`);
            $('#ae-more-sold-badge').text(`> 0 Sold: ${moreSold.toLocaleString()}`);
            $('#ae-avg-dil-badge').text(dilCount > 0 ? `DIL%: ${avgDil.toFixed(1)}%` : 'DIL%: –');
            if ($('#ae-avg-roi-badge').length) {
                $('#ae-avg-roi-badge').text(hasSalesTotals && Number.isFinite(avgRoi) ? `GROI: ${Math.round(avgRoi)}%` : 'GROI: –');
            }
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
            if (row.is_parent) return '';
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
            if (row.is_parent) return '';
            const rowSku = rowSkuForLinkLmp(row);
            if (!rowSku) return '';
            return `<div class="d-flex align-items-center justify-content-center py-1">
                <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn" title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}"><i class="fas fa-plus"></i></button>
            </div>`;
        }

        function applyAffectedLinkedSkuRows(affected) {
            if (!table || !Array.isArray(affected)) return;
            // Re-fetch so LMP recomputes across the linked group (same as ebay-tabulator-view)
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

        $(document).ready(function() {
            initSkuLinkLmpModal();
            table = new Tabulator("#shein-pricing-table", {
                ajaxURL: "/shein/pricing-data",
                ajaxResponse: function(url, params, response) {
                    // New shape: { data: rows[], sales_page: {...} } — same Sales/GPFT/GROI as /shein-tabulator
                    let rows = response;
                    if (response && !Array.isArray(response) && Array.isArray(response.data)) {
                        salesPageTotals = response.sales_page || null;
                        rows = response.data;
                    } else if (Array.isArray(response)) {
                        salesPageTotals = null;
                        rows = response;
                    }
                    // Hide parent rows — drop them from the dataset entirely
                    rows = Array.isArray(rows) ?
                        rows.filter(r => !(r && r.is_parent === true)) : rows;
                    summaryDataCache = normalizeRows(rows);
                    updateSummary(summaryDataCache);
                    setTimeout(aeApplyBadgeFilterFromUrl, 0);
                    return rows;
                },
                layout: "fitDataStretch",
                height: "calc(100vh - 260px)",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "SKU Count"
                        }
                    }
                },
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('ae-parent-row');
                    }
                },
                columns: [
                    // ── Select checkbox (always visible; same UX as ebay-tabulator-view) ──
                    {
                        title: "<input type='checkbox' id='ae-select-all'>",
                        field: "_ae_select",
                        hozAlign: "center",
                        headerSort: false,
                        frozen: true,
                        width: 38,
                        visible: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = d.sku;
                            const chk = selectedSkus.has(sku) ? 'checked' : '';
                            return `<input type='checkbox' class='ae-sku-chk' data-sku='${sku.replace(/'/g,"\\'")}' ${chk}>`;
                        }
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        width: 120,
                        frozen: true,
                        visible: false,
                        cssClass: "text-muted",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                        }
                    },
                    {
                        title: "Image",
                        field: "image",
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || !src) return '';
                            return `<img src="${src}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;"
                                onerror="this.style.display='none'">`;
                        }
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        minWidth: 200,
                        frozen: true,
                        headerFilter: "input",
                        cssClass: "fw-bold text-primary",
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return `<span style="color:#1e40af;font-size:13px;font-weight:700;">${val}</span>`;
                            }
                            const esc = val.replace(/&/g,'&amp;').replace(/</g,'&lt;');
                            return `<span class="fw-bold">${esc}</span>`;
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
                            if (d.is_parent) return '';
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
                            if (d.is_parent) return;
                            openSheinEditLinksModal(cell.getRow());
                        }
                    },
                    {
                        title: "NR",
                        field: "nr_req",
                        width: 60,
                        frozen: true,
                        hozAlign: "center",
                        headerSort: false,
                        tooltip: "Required (REQ) / Not Required (NR)",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = String(d.sku || '').replace(/"/g, '&quot;');
                            const value = cell.getValue() || 'REQ';
                            return '<select class="form-select form-select-sm shein-nr-dropdown" data-sku="' + sku + '" style="width:52px;border:1px solid #adb5bd;padding:2px;font-size:16px;text-align:center;cursor:pointer;" onclick="event.stopPropagation();">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>\uD83D\uDFE2</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>\uD83D\uDD34</option>' +
                                '</select>';
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        }
                    },
                    {
                        title: "Missing L",
                        field: "missing",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            if (sheinRowIsMissingL(d)) {
                                return '<span class="badge bg-danger">L</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "MAP",
                        field: "map",
                        hozAlign: "center",
                        width: 90,
                        headerTooltip: "MP when within map-issues tolerance (3 units, or rounded 3% for INV ≥ 100); N MP otherwise (listed rows with Shein Stock > 0).",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            // Same structure as /ebay2-tabulator-view MAP column.
                            if (sheinRowIsMap(d)) {
                                return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                            }
                            if (sheinRowIsNMap(d)) {
                                const inv = parseFloat(d.inv) || 0;
                                const sheinStock = parseFloat(d.shein_stock) || 0;
                                const signedDiff = Math.round(inv - sheinStock);
                                const sign = signedDiff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${signedDiff})</span>`;
                            }
                            return '';
                        }
                    },
                    {
                        title: "INV",
                        field: "inv",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return `<span style="font-weight:700;">${cell.getValue()}</span>`;
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: "Shein Stock",
                        field: "shein_stock",
                        sorter: "number",
                        hozAlign: "center",
                        width: 65,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return `<span style="font-weight:700;">${cell.getValue()}</span>`;
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "ov_l30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            return `<span style="font-weight:700;">${parseInt(cell.getValue(), 10) || 0}</span>`;
                        }
                    },
                    {
                        title: "Dil",
                        field: "dil_percent",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv   = parseFloat(row.inv)    || 0;
                            const ovL30 = parseFloat(row.ov_l30) || 0;
                            // INV=0 → 0% red (same as /ebay2-tabulator-view)
                            if (inv === 0) return '<span style="color:#a00211;font-weight:600;">0%</span>';
                            const dil = (ovL30 / inv) * 100;
                            let color;
                            // No yellow band — red absorbs former 16.66–25% (same as ebay-tabulator-view / Dil filter slabs)
                            if (dil < 25) color = '#a00211';
                            else if (dil < 50) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: "Sh L30",
                        field: "al30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return `<span style="font-weight:700;">${v}</span>`;
                        }
                    },
                    {
                        title: "Sp. Price",
                        field: "special_offer",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            if (v === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#e83e8c;font-weight:600;">${money(v)}</span>`;
                        }
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        sorter: "number",
                        hozAlign: "center",
                        width: 90,
                        tooltip: "Lowest market price (Shein competitors)",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const entries = Array.isArray(d.lmp_entries) ? d.lmp_entries : [];
                            const total = entries.length;
                            if (total === 0) return '<span style="color:#adb5bd;">N/A</span>';

                            const lowest = Math.min.apply(null, entries.map(e => parseFloat(e.price) || Infinity));
                            const myPrice = parseFloat(d.special_offer) || 0;
                            const priceColor = (myPrice > 0 && lowest < myPrice) ? '#dc3545' : '#28a745';

                            let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:2px;line-height:1.15;">';
                            html += '<span style="color:' + priceColor + ';font-weight:700;font-size:14px;">' + money(lowest) + '</span>';
                            html += '<a href="#" class="shein-view-lmp" data-sku="' + String(d.sku || '').replace(/"/g, '&quot;') + '"' + (d.lmp_entries ? ' data-lmp=\'' + JSON.stringify(d.lmp_entries).replace(/'/g, '&#39;') + '\'' : '') + ' style="color:#0d6efd;text-decoration:none;font-size:11px;"><i class="fa fa-eye"></i> View ' + total + '</a>';
                            html += '</div>';
                            return html;
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
                    },
                    {
                        title: "GPFT",
                        field: "gpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 &&  d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const r = Math.round(v);
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:${d.is_parent?'700':'600'};">${r}%</span>`;
                        }
                    },
                    {
                        title: "GROI",
                        field: "groi",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            // Color ranges matching the ROI% filter dropdown
                            let color;
                            if      (v < 40)  color = '#a00211';
                            else if (v < 75)  color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else              color = '#d63384';
                            const r = Math.round(v);
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
                        }
                    },
                    {
                        title: "Profit",
                        field: "profit",
                        visible: false,
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return `<span style="color:${color};font-weight:700;">${money(v)}</span>`;
                            }
                            return money(v);
                        }
                    },
                    {
                        title: "Sales",
                        field: "sales",
                        sorter: "number",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                return `<span style="font-weight:700;">${money(v)}</span>`;
                            }
                            return money(v);
                        }
                    },
                    // {
                    //     title: "Sh L30",
                    //     field: "al30",
                    //     sorter: "number",
                    //     hozAlign: "center",
                    //     formatter: function(cell) {
                    //         const d = cell.getRow().getData();
                    //         const v = parseInt(cell.getValue(), 10) || 0;
                    //         return `<span style="font-weight:${d.is_parent?'700':'400'};">${v}</span>`;
                    //     }
                    // },
                    {
                        title: "LP",
                        field: "lp",
                        sorter: "number",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: "Ship",
                        field: "ship",
                        sorter: "number",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: "Sprice",
                        field: "sprice",
                        sorter: "number",
                        hozAlign: "right",
                        editor: "number",
                        editorParams: { min: 0, step: 0.01 },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            return `<span style="font-weight:600;padding:2px 6px;border-radius:3px;">${money(v)}</span>`;
                        }
                    },
                    {
                        title: "SGPFT",
                        field: "sgpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            const r = Math.round(v);
                            // Same color coding as GPFT
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
                        }
                    },
                    {
                        title: "SGroi",
                        field: "sroi",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            const r = Math.round(v);
                            // Same color ranges as GROI
                            let color;
                            if      (v < 40)  color = '#a00211';
                            else if (v < 75)  color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else              color = '#d63384';
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
                        }
                    },
                ],
                dataLoaded: function(data) {
                    updateSummary(data);
                    // Honor the dropdown defaults on first load (e.g. INV "More than 0")
                    // so the table doesn't render every row before the user touches a filter.
                    applyFilters();
                },
                dataFiltered: function(filters, rows) {
                    updateSummary(rows);
                },
                dataProcessed: function() {
                    updateSummary();
                },
                renderComplete: function() {
                    updateSummary();
                }
            });

            $('#pricing-sku-search').on('input', function() { applyFilters(); });
            $('#pricing-parent-search').on('input', function() { applyFilters(); });
            $('#ae-row-type-filter').on('change', function() { applyFilters(); });
            $('#ae-inv-filter').on('change',    function() { applyFilters(); });
            $('#ae-stock-filter').on('change',  function() { applyFilters(); });
            $('#ae-gpft-filter').on('change',   function() { applyFilters(); });
            $('#ae-roi-filter').on('change',    function() { applyFilters(); });
            $('#ae-al30-filter').on('change',   function() { applyFilters(); });
            $('#ae-map-filter').on('change',    function() { applyFilters(); });
            $('#ae-nrl-filter').on('change',    function() { applyFilters(); });
            $('#ae-sprice-filter').on('change', function() { applyFilters(); });
            $('#ae-dil-filter').on('change',    function() { applyFilters(); });

            // ── Price % (Increase / Decrease) ─────────────────────
            $('#ae-price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive) {
                    decreaseModeActive = true; increaseModeActive = false;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = true;
                } else {
                    decreaseModeActive = false; increaseModeActive = false;
                }
                syncPriceModeUi();
            });

            $('#ae-discount-type').on('change', function() {
                $('#ae-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#ae-apply-discount-btn').on('click', function() { applyAeDiscount(); });
            $('#ae-discount-input').on('keypress', function(e) { if (e.which === 13) applyAeDiscount(); });
            $('#ae-clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

            // Select all checkbox
            $(document).on('change', '#ae-select-all', function() {
                const checked = $(this).prop('checked');
                const rows = table.getData('active').filter(d => !d.is_parent);
                rows.forEach(d => { if (checked) selectedSkus.add(d.sku); else selectedSkus.delete(d.sku); });
                $('.ae-sku-chk').prop('checked', checked);
                updateSelectedCount();
            });

            // Individual checkbox
            $(document).on('change', '.ae-sku-chk', function() {
                const sku = $(this).data('sku');
                if ($(this).prop('checked')) selectedSkus.add(sku); else selectedSkus.delete(sku);
                updateSelectedCount();
            });

            // SPRICE cell edited – save immediately, recalculate SGPFT + SROI with proper margin
            table.on('cellEdited', function(cell) {
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku    = d.sku;
                // Always store SPRICE to exactly 2 decimals (UI input may allow more digits).
                const rawSprice = parseFloat(cell.getValue()) || 0;
                const sprice = Math.round(rawSprice * 100) / 100;
                const margin = parseFloat(d._margin) || 1;
                const lp     = parseFloat(d.lp)   || 0;
                const ship   = parseFloat(d.ship)  || 0;
                // Same formulas as GPFT / GROI
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - ship - lp) / sprice) * 100 * 100) / 100 : 0;
                const sroi  = lp     > 0 ? Math.round(((sprice * margin - lp - ship)  / lp)    * 100 * 100) / 100 : 0;
                cell.getRow().update({ sprice: sprice, sgpft: sgpft, sroi: sroi });
                saveSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            /*
             * ============================================================================
             * Target ROI% / Target GPFT% bulk apply for SPRICE (mirrors ebay-tabulator-view)
             * ----------------------------------------------------------------------------
             * Pick rows (via Price Mode), type the target %, click Apply SPRICE → back-solve
             * a sale price that makes the on-page SROI / SGPFT column match the target after
             * Shein margin + shipping are paid out.
             *
             * Math (mirrors the backend's SGPFT / SROI formulas):
             *   SROI%  = ((sprice * margin - lp - ship) / lp) * 100
             *      -> sprice = (lp * (1 + roi%/100) + ship) / margin
             *
             *   SGPFT% = ((sprice * margin - ship - lp) / sprice) * 100
             *      -> sprice = (lp + ship) / (margin - gpft%/100)
             *      Constraint: (margin - gpft%/100) must be > 0.
             *
             * `margin` is the per-row take-home rate (row._margin) with a 1 fallback.
             * Saving goes through /shein/save-sprice exactly like an inline SPRICE edit.
             * Rounding is plain 2-decimal — no .99 retail snapping — because snapping
             * would shift the achieved SROI / SGPFT off the user-typed target (same as ebay2).
             * ============================================================================
             */
            function aeApplyTargetSpriceBatch(opts) {
                const $btn = opts.$btn;
                if (selectedSkus.size === 0) {
                    sheinLinksNotify('Please select at least one SKU first.', 'error');
                    return;
                }

                const rowsToProcess = [];
                const skipped = [];
                table.getRows().forEach(function(r) {
                    const rd = r.getData();
                    const sku = rd.sku;
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rd.is_parent) return;
                    const res = opts.computeSprice(rd);
                    if (!res || res.skipReason) {
                        if (res && res.skipReason) skipped.push({ sku: sku, reason: res.skipReason });
                        return;
                    }
                    let sprice = +Number(res.sprice).toFixed(2);
                    if (!isFinite(sprice) || sprice <= 0) return;
                    rowsToProcess.push({ row: r, sku: sku, sprice: sprice });
                });

                if (rowsToProcess.length === 0) {
                    if (skipped.length > 0) {
                        sheinLinksNotify('Cannot apply: ' + skipped[0].reason, 'error');
                    } else {
                        sheinLinksNotify('No selected rows have a usable LP > 0', 'error');
                    }
                    return;
                }

                let confirmMsg = `Compute & save SPRICE for ${rowsToProcess.length} selected SKU(s) using ${opts.label}?`;
                if (skipped.length > 0) {
                    confirmMsg += `\n\nNote: ${skipped.length} row(s) will be skipped (${skipped[0].reason}).`;
                }
                if (!confirm(confirmMsg)) return;

                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

                // Update rows client-side immediately (matches inline cellEdited handler)
                const updates = [];
                rowsToProcess.forEach(function(item) {
                    const rd = item.row.getData();
                    const margin = parseFloat(rd._margin) || 1;
                    const lp = parseFloat(rd.lp) || 0;
                    const ship = parseFloat(rd.ship) || 0;
                    const sprice = item.sprice;
                    const sgpft = sprice > 0 ? Math.round(((sprice * margin - ship - lp) / sprice) * 100 * 100) / 100 : 0;
                    const sroi = lp > 0 ? Math.round(((sprice * margin - lp - ship) / lp) * 100 * 100) / 100 : 0;
                    item.row.update({ sprice: sprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: item.sku, sprice: sprice });
                });

                $.ajax({
                    url: '{{ route("shein.pricing.save.sprice") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { updates: updates },
                    success: function(res) {
                        if (res && res.success) {
                            sheinLinksNotify(`SPRICE saved for ${updates.length} SKU(s) @ ${opts.label}`, 'success');
                        } else {
                            sheinLinksNotify('Failed to save SPRICE updates', 'error');
                        }
                    },
                    error: function() {
                        sheinLinksNotify('Error saving SPRICE updates', 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(opts.btnHtml);
                        // Wipe selection so the next batch starts clean.
                        selectedSkus.clear();
                        $('.ae-sku-chk').prop('checked', false);
                        $('#ae-select-all').prop('checked', false);
                        updateSelectedCount();
                    }
                });
            }

            // Target ROI%
            $('#ae-apply-target-roi-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#ae-target-roi-input').val();
                const targetRoiPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { sheinLinksNotify('Please enter a Target ROI%', 'error'); return; }
                if (!isFinite(targetRoiPct)) { sheinLinksNotify('Target ROI% must be a number', 'error'); return; }
                const roiMultiplier = 1 + (targetRoiPct / 100);
                aeApplyTargetSpriceBatch({
                    targetPct: targetRoiPct,
                    label: `Target ROI ${targetRoiPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.lp) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.ship) || 0;
                        const marginRaw = parseFloat(rd._margin);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 1;
                        return { sprice: (lp * roiMultiplier + ship) / margin };
                    }
                });
            });
            $('#ae-target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#ae-apply-target-roi-btn').click();
            });

            // Target GPFT%
            $('#ae-apply-target-gpft-btn').on('click', function() {
                const $btn = $(this);
                const raw = $('#ae-target-gpft-input').val();
                const targetGpftPct = parseFloat(String(raw).replace(',', '.'));
                if (raw === '' || raw == null) { sheinLinksNotify('Please enter a Target GPFT%', 'error'); return; }
                if (!isFinite(targetGpftPct)) { sheinLinksNotify('Target GPFT% must be a number', 'error'); return; }
                const targetFraction = targetGpftPct / 100;
                aeApplyTargetSpriceBatch({
                    targetPct: targetGpftPct,
                    label: `Target GPFT ${targetGpftPct}%`,
                    $btn: $btn,
                    btnHtml: '<i class="fas fa-calculator"></i> Apply SPRICE',
                    computeSprice: function(rd) {
                        const lp = parseFloat(rd.lp) || 0;
                        if (lp <= 0) return null;
                        const ship = parseFloat(rd.ship) || 0;
                        const marginRaw = parseFloat(rd._margin);
                        const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 1;
                        const denom = margin - targetFraction;
                        if (denom <= 0) {
                            return { skipReason: `Target GPFT% ${targetGpftPct}% ≥ Shein take-home margin (~${Math.round(margin * 100)}%)` };
                        }
                        return { sprice: (lp + ship) / denom };
                    }
                });
            });
            $('#ae-target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#ae-apply-target-gpft-btn').click();
            });

            /*
             * ============================================================================
             * Column visibility — same pattern as /ebay2-tabulator-view
             * Persists in `channel_tabulator_column_settings` via
             * /tabulator-column-visibility, channel = 'shein_pricing'.
             * ============================================================================
             */
            const AE_COLUMN_VIS_URL = '/tabulator-column-visibility';
            const AE_COLUMN_VIS_CHANNEL = 'shein_pricing';

            function aeBuildColumnDropdown() {
                const menu = document.getElementById('ae-column-dropdown-menu');
                if (!menu) return;
                menu.innerHTML = '';

                fetch(AE_COLUMN_VIS_URL + '?channel=' + encodeURIComponent(AE_COLUMN_VIS_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(savedVisibility => {
                        const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field || def.field === '_ae_select') return;

                            const title = (def.title || '').replace(/<[^>]*>/g, '').trim() || def.field;
                            const li = document.createElement('li');
                            const label = document.createElement('label');
                            label.style.display = 'block';
                            label.style.padding = '5px 10px';
                            label.style.cursor = 'pointer';

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.value = def.field;
                            // Prefer saved map; fall back to current column visibility (definition default)
                            checkbox.checked = map.hasOwnProperty(def.field)
                                ? (map[def.field] !== false)
                                : col.isVisible();
                            checkbox.style.marginRight = '8px';
                            checkbox.className = 'ae-column-toggle';

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    })
                    .catch(err => console.error('Error loading Shein column visibility:', err));
            }

            function aeSaveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field && def.field !== '_ae_select') {
                        visibility[def.field] = col.isVisible();
                    }
                });

                fetch(AE_COLUMN_VIS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        channel: AE_COLUMN_VIS_CHANNEL,
                        visibility: visibility
                    })
                }).catch(err => console.error('Error saving Shein column visibility:', err));
            }

            function aeApplyColumnVisibilityFromServer() {
                fetch(AE_COLUMN_VIS_URL + '?channel=' + encodeURIComponent(AE_COLUMN_VIS_CHANNEL), {
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
                            const def = col.getDefinition();
                            if (!def.field || def.field === '_ae_select') return;
                            // Only apply fields that were explicitly saved (same idea as ebay2 +
                            // restore true so default-hidden columns can be re-shown and stick).
                            if (!Object.prototype.hasOwnProperty.call(savedVisibility, def.field)) return;
                            if (savedVisibility[def.field]) {
                                col.show();
                            } else {
                                col.hide();
                            }
                        });
                    })
                    .catch(err => console.error('Error applying Shein column visibility:', err));
            }

            // Keep menu open while toggling checkboxes (multi-select)
            document.getElementById('ae-column-dropdown-menu').addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Toggle column from dropdown → save immediately (ebay2)
            document.getElementById('ae-column-dropdown-menu').addEventListener('change', function(e) {
                if (e.target.type === 'checkbox') {
                    const field = e.target.value;
                    const col = table.getColumn(field);
                    if (!col) return;
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    aeSaveColumnVisibilityToServer();
                }
            });

            table.on('tableBuilt', function() {
                aeApplyColumnVisibilityFromServer();
                aeBuildColumnDropdown();
            });

            // Badge click → table filter only (same as /ebay2-tabulator-view — no hover/click chart)
            $('#ae-missing-badge').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeMissingActive = !aeMissingActive;
                aeMapActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-map-badge').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeMapActive = !aeMapActive;
                aeMissingActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-nmap-badge').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeNMapActive = !aeNMapActive;
                aeMissingActive = aeMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-zero-sold-badge').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeZeroSoldActive = !aeZeroSoldActive;
                aeMoreSoldActive = aeMissingActive = aeMapActive = aeNMapActive = false;
                applyFilters();
            });
            $('#ae-more-sold-badge').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeMoreSoldActive = !aeMoreSoldActive;
                aeZeroSoldActive = aeMissingActive = aeMapActive = aeNMapActive = false;
                applyFilters();
            });

            $('#refresh-pricing-table').on('click', function() {
                table.setData("/shein/pricing-data");
            });

            $('#export-pricing-btn').on('click', function() {
                table.download("csv", "shein_pricing_data.csv");
            });

            $('#uploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('priceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }

                const $btn = $('#uploadPriceSheetBtn');
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $btn.prop('disabled', true);

                $.ajax({
                    url: '/shein/pricing-upload-price',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response && response.success === false) {
                            const errMsg = response.message || 'Price upload failed.';
                            if (window.toastr) toastr.error(errMsg);
                            else alert(errMsg);
                            return;
                        }
                        if (window.toastr) {
                            toastr.success((response && response.message) ? response.message : 'Price upload completed.');
                        } else {
                            alert((response && response.message) ? response.message : 'Price upload completed.');
                        }
                        $('#uploadPriceSheetModal').modal('hide');
                        $('#priceSheetFile').val('');
                        table.setData('/shein/pricing-data');
                    },
                    error: function(xhr) {
                        let message = 'Price upload failed.';
                        const j = xhr.responseJSON;
                        if (j) {
                            if (j.message) message = j.message;
                            else if (j.errors) {
                                message = Object.values(j.errors).flat().join(' ');
                            }
                        } else if (xhr.status === 419) {
                            message = 'Session expired. Refresh the page and try again.';
                        } else if (xhr.status === 0) {
                            message = 'Network error. Check your connection.';
                        }
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ── Badge trend chart (eBay 3 style: line, median line, point labels, hover ½s, Lifetime range) ──────
            let aeBadgeLineChart = null;
            let aeBadgeMetric = '';
            let aeBadgeDays = 30;
            let aeBadgeAjax = null;

            const aeDollarMetrics = ['total_sales', 'total_cogs', 'total_pft'];
            const aePercentMetrics = ['avg_gpft', 'avg_roi', 'avg_dil'];

            const aeBadgeLabels = {
                total_sales: 'Sales',
                total_pft: 'PFT',
                total_al30: 'Sh L30',
                avg_gpft: 'GPFT %',
                avg_roi: 'ROI %',
                avg_dil: 'DIL %',
                total_cogs: 'COGS',
                missing_count: 'Missing',
                map_count: 'Map',
                nmap_count: 'N Map',
                total_sku: 'SKU',
                zero_sold: '0 Sold',
                more_sold: '> 0 Sold',
            };

            function aeFormatChartVal(v) {
                const n = Number(v);
                if (aeDollarMetrics.includes(aeBadgeMetric)) {
                    const x = Number.isFinite(n) ? n : 0;
                    return '$' + Math.round(x).toLocaleString('en-US');
                }
                if (aePercentMetrics.includes(aeBadgeMetric)) {
                    const x = Number.isFinite(n) ? n : 0;
                    if (aeBadgeMetric === 'avg_dil') return x.toFixed(1) + '%';
                    return Math.round(x) + '%';
                }
                return Math.round(Number.isFinite(n) ? n : 0).toLocaleString('en-US');
            }

            function aeBadgeChartModalTitle() {
                const part = aeBadgeLabels[aeBadgeMetric] || aeBadgeMetric;
                return 'Shein — ' + part + ' (Daily snapshot)';
            }

            function aeOpenBadgeChartModal(metricKey) {
                aeBadgeMetric = metricKey;
                aeBadgeDays = 30;
                $('#aeBadgeChartRange').val('30');
                $('#aeBadgeChartTitle').text(aeBadgeChartModalTitle());
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeBadgeChartModal')).show();
                aeLoadChart();
            }

            // No hover/click chart on badges — match /ebay2-tabulator-view (filter badges click to filter only)

            function aeRenderLineChart(points) {
                if (!Array.isArray(points) || !points.length) return false;

                const labels = points.map(p => p.date);
                const values = points.map(p => Number(p.value) || 0);
                const sorted = [...values].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const dataMin = sorted[0];
                const dataMax = sorted[sorted.length - 1];

                $('#aeBadgeHighest').text(aeFormatChartVal(dataMax));
                $('#aeBadgeMedian').text(aeFormatChartVal(median));
                $('#aeBadgeLowest').text(aeFormatChartVal(dataMin));

                const lineCtx = document.getElementById('aeBadgeLineCanvas');
                if (!lineCtx || typeof Chart === 'undefined') return false;

                if (aeBadgeLineChart) aeBadgeLineChart.destroy();

                const range = dataMax - dataMin || 1;
                const pad = range * 0.1 || 1;
                const yMin = dataMin - pad;
                const yMax = dataMax + pad;

                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#198754' : '#6c757d');
                });

                const labelColors = values.map(function(v, i) {
                    if (i < 7) return '#6c757d';
                    return v < values[i - 7] ? '#dc3545' : (v > values[i - 7] ? '#198754' : '#6c757d');
                });

                const medianLinePlugin = {
                    id: 'aeSheinMedianLine',
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
                    id: 'aeSheinValueLabels',
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
                            const txt = aeFormatChartVal(dataset.data[i]);
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

                aeBadgeLineChart = new Chart(lineCtx.getContext('2d'), {
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
                                callbacks: {
                                    label: function(ctx) {
                                        const idx = ctx.dataIndex;
                                        const va = ctx.raw;
                                        const parts = [(aeBadgeLabels[aeBadgeMetric] || 'Value') + ': ' + aeFormatChartVal(va)];
                                        if (idx > 0) {
                                            const diff = va - values[idx - 1];
                                            parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + aeFormatChartVal(Math.abs(diff)));
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
                                ticks: { font: { size: 9 }, callback: function(v) { return aeFormatChartVal(v); } }
                            },
                            x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                        }
                    }
                });
                return true;
            }

            function aeLoadChart() {
                if (!aeBadgeMetric) return;
                if (aeBadgeAjax) aeBadgeAjax.abort();
                $('#aeBadgeNoData,#aeBadgeLineWrap').hide();
                $('#aeBadgeLoading').show();

                aeBadgeAjax = $.ajax({
                    url: '{{ route("shein.badge.chart") }}',
                    method: 'GET',
                    data: { metric: aeBadgeMetric, days: aeBadgeDays },
                    success: function(res) {
                        aeBadgeAjax = null;
                        $('#aeBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (aeRenderLineChart(pts)) {
                            $('#aeBadgeLineWrap').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                        } else {
                            $('#aeBadgeNoData').show();
                        }
                    },
                    error: function() {
                        aeBadgeAjax = null;
                        $('#aeBadgeLoading').hide();
                        $('#aeBadgeNoData').show();
                    }
                });
            }

            $(document).on('change', '#aeBadgeChartRange', function() {
                const raw = $(this).val();
                const d = raw === '0' ? 0 : (parseInt(raw, 10) || 30);
                if (d === aeBadgeDays) return;
                aeBadgeDays = d;
                $('#aeBadgeChartTitle').text(aeBadgeChartModalTitle());
                aeLoadChart();
            });
        });

        // ===== Edit Links (Buyer / Seller) =====
        function sheinLinksNotify(message, type) {
            if (window.toastr) {
                (type === 'error' ? toastr.error : toastr.success)(message);
                return;
            }
            let container = document.getElementById('sheinToastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'sheinToastContainer';
                container.style.cssText =
                    'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            const bg = type === 'error' ? '#dc3545' : '#198754';
            toast.style.cssText =
                'min-width:220px;max-width:340px;color:#fff;background:' + bg +
                ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            toast.textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(function() {
                toast.style.opacity = '1';
            });
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 2600);
        }

        let sheinEditLinksRow = null;

        function openSheinEditLinksModal(row) {
            sheinEditLinksRow = row;
            const d = row.getData();
            document.getElementById('sheinEditLinksSku').textContent = d.sku || '';
            document.getElementById('sheinSellerLinkInput').value = d['S Link'] || '';
            document.getElementById('sheinBuyerLinkInput').value = d['B Link'] || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sheinEditLinksModal')).show();
        }

        // ── LMP competitors modal (same as Amazon page) ──────────────────
        let sheinLmpCurrentSku = '';
        let sheinEditLmpSlot = null;

        function sheinResetLmpForm(keepSku) {
            sheinEditLmpSlot = null;
            document.getElementById('sheinEditLmpSlot').value = '';
            document.getElementById('sheinAddLmpSku').value = keepSku || sheinLmpCurrentSku || '';
            document.getElementById('sheinAddLmpPrice').value = '';
            document.getElementById('sheinAddLmpLink').value = '';
            $('#sheinLmpFormCard').removeClass('border-warning').addClass('border-success');
            $('#sheinLmpFormHeader').removeClass('bg-warning text-dark').addClass('bg-success text-white');
            $('#sheinLmpFormHeaderIcon').attr('class', 'fa fa-plus-circle');
            $('#sheinLmpFormHeaderText').text('Add Competitor LMP');
            $('#sheinLmpFormHeaderHint').text('Max 4 per SKU');
            $('#sheinAddLmpBtn').removeClass('btn-warning').addClass('btn-success');
            $('#sheinAddLmpBtn').find('i').attr('class', 'fa fa-plus');
            $('#sheinAddLmpBtnText').text('Add');
            $('#sheinCancelEditLmpBtn').addClass('d-none');
        }

        function sheinEnterEditLmpMode(item) {
            if (!item || !item.slot) return;
            sheinEditLmpSlot = item.slot;
            document.getElementById('sheinEditLmpSlot').value = item.slot;
            document.getElementById('sheinAddLmpSku').value = item.sku || sheinLmpCurrentSku || '';
            document.getElementById('sheinAddLmpPrice').value = item.price != null ? parseFloat(item.price) : '';
            document.getElementById('sheinAddLmpLink').value = item.link || '';
            $('#sheinLmpFormCard').removeClass('border-success').addClass('border-warning');
            $('#sheinLmpFormHeader').removeClass('bg-success text-white').addClass('bg-warning text-dark');
            $('#sheinLmpFormHeaderIcon').attr('class', 'fa fa-edit');
            $('#sheinLmpFormHeaderText').text('Edit Competitor LMP');
            $('#sheinLmpFormHeaderHint').text('Slot #' + item.slot);
            $('#sheinAddLmpBtn').removeClass('btn-success').addClass('btn-warning');
            $('#sheinAddLmpBtn').find('i').attr('class', 'fa fa-save');
            $('#sheinAddLmpBtnText').text('Update');
            $('#sheinCancelEditLmpBtn').removeClass('d-none');
            const formCard = document.getElementById('sheinLmpFormCard');
            if (formCard) formCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function renderSheinLmpList(entries) {
            entries = Array.isArray(entries) ? entries.filter(e => (parseFloat(e.price) || 0) > 0) : [];
            if (entries.length === 0) {
                $('#sheinLmpDataList').html('<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No competitors yet. Add your first one above!</div>');
                return;
            }
            const lowest = Math.min.apply(null, entries.map(e => parseFloat(e.price) || Infinity));

            let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm align-middle mb-0">';
            html += '<thead class="table-light"><tr>' +
                '<th style="width:40px;">#</th>' +
                '<th style="width:120px;">Price</th>' +
                '<th>Product Link</th>' +
                '<th style="width:60px;">Open</th>' +
                '<th style="width:100px;">Actions</th>' +
                '</tr></thead><tbody>';

            entries.forEach(function(e, i) {
                const price = parseFloat(e.price) || 0;
                const isLow = Math.abs(price - lowest) < 0.01;
                const rowClass = isLow ? 'table-success' : '';
                const priceBadge = isLow
                    ? '<span class="badge bg-success">' + money(price) + ' <i class="fa fa-trophy"></i></span>'
                    : '<strong>' + money(price) + '</strong>';
                const link = e.link || '';
                const sourceSku = String(e.source_sku || sheinLmpCurrentSku || '').replace(/"/g, '&quot;');
                const slot = e.slot || (i + 1);
                const linkText = link
                    ? '<a href="' + String(link).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" style="font-size:11px;word-break:break-all;">' + String(link).substring(0, 90) + (String(link).length > 90 ? '…' : '') + '</a>'
                    : '<span style="color:#999;">—</span>';
                const openBtn = link
                    ? '<a href="' + String(link).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-info" title="Open competitor"><i class="fa fa-external-link"></i></a>'
                    : '<span style="color:#999;">—</span>';
                const editBtn = '<button class="btn btn-sm btn-warning shein-edit-lmp" data-slot="' + slot + '" data-source-sku="' + sourceSku + '" data-price="' + String(price).replace(/"/g, '&quot;') + '" data-link="' + String(link).replace(/"/g, '&quot;') + '" title="Edit this LMP"><i class="fa fa-edit"></i></button>';
                const delBtn = '<button class="btn btn-sm btn-danger shein-del-lmp" data-slot="' + slot + '" data-source-sku="' + sourceSku + '" title="Remove this LMP"><i class="fa fa-trash"></i></button>';
                html += '<tr class="' + rowClass + '">' +
                    '<td class="text-center"><strong>' + (i + 1) + '</strong></td>' +
                    '<td>' + priceBadge + '</td>' +
                    '<td>' + linkText + '</td>' +
                    '<td class="text-center">' + openBtn + '</td>' +
                    '<td class="text-center text-nowrap">' + editBtn + ' ' + delBtn + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
            $('#sheinLmpDataList').html(html);
        }

        // After LMP add/delete, refresh the grid so linked-SKU merges stay correct.
        function sheinRefreshLmpAfterChange(sku) {
            if (!table) return;
            const currentSku = sku || sheinLmpCurrentSku;
            table.replaceData().then(function() {
                if (!currentSku) return;
                const match = table.getData().find(r => String(r.sku) === String(currentSku) && !r.is_parent);
                if (match) {
                    renderSheinLmpList(match.lmp_entries || []);
                }
            }).catch(function() {});
        }

        // Refresh the LMP cell of a given SKU row in the grid.
        function sheinUpdateLmpRow(sku, entries) {
            sheinRefreshLmpAfterChange(sku);
        }

        $(document).on('click', '.shein-view-lmp', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            let entries = [];
            // Prefer entries embedded on the link; fall back to the table row data.
            const raw = $(this).attr('data-lmp');
            if (raw) {
                try { entries = JSON.parse(raw); } catch (err) { entries = []; }
            }
            if ((!entries || entries.length === 0) && table) {
                const match = table.getData().find(r => String(r.sku) === String(sku) && !r.is_parent);
                if (match && Array.isArray(match.lmp_entries)) entries = match.lmp_entries;
            }
            sheinLmpCurrentSku = sku || '';
            document.getElementById('sheinLmpSku').textContent = sku || '';
            sheinResetLmpForm(sku || '');
            renderSheinLmpList(entries);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sheinLmpModal')).show();
        });

        // Add or update a competitor LMP.
        $(document).on('submit', '#sheinAddLmpForm', function(e) {
            e.preventDefault();
            const sku = document.getElementById('sheinAddLmpSku').value.trim();
            const price = document.getElementById('sheinAddLmpPrice').value;
            const link = document.getElementById('sheinAddLmpLink').value.trim();
            const editSlot = sheinEditLmpSlot || document.getElementById('sheinEditLmpSlot').value;
            const isEdit = !!editSlot;
            if (!sku) { sheinLinksNotify('SKU is missing', 'error'); return; }
            if (!(parseFloat(price) > 0)) { sheinLinksNotify('Enter a valid price', 'error'); return; }

            const $btn = $('#sheinAddLmpBtn');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (isEdit ? 'Updating...' : 'Adding...'));

            const payload = { sku: sku, price: price, link: link };
            if (isEdit) payload.slot = editSlot;

            $.ajax({
                url: isEdit ? '/shein/lmp/update' : '/shein/lmp/add',
                method: 'POST',
                data: payload,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res && res.success) {
                        sheinResetLmpForm(sheinLmpCurrentSku);
                        sheinRefreshLmpAfterChange(sheinLmpCurrentSku);
                        sheinLinksNotify(isEdit ? 'LMP updated' : 'LMP added', 'success');
                    } else {
                        sheinLinksNotify((res && res.message) || (isEdit ? 'Error updating LMP' : 'Error adding LMP'), 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || (isEdit ? 'Error updating LMP' : 'Error adding LMP');
                    sheinLinksNotify(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    if (sheinEditLmpSlot) {
                        $btn.html('<i class="fa fa-save"></i> <span id="sheinAddLmpBtnText">Update</span>');
                    } else {
                        $btn.html('<i class="fa fa-plus"></i> <span id="sheinAddLmpBtnText">Add</span>');
                    }
                }
            });
        });

        // Edit a competitor LMP — load into the form above.
        $(document).on('click', '.shein-edit-lmp', function() {
            const $btn = $(this);
            sheinEnterEditLmpMode({
                slot: $btn.data('slot'),
                sku: $btn.attr('data-source-sku') || $btn.data('source-sku') || sheinLmpCurrentSku,
                price: $btn.data('price'),
                link: $btn.attr('data-link') || '',
            });
        });

        $(document).on('click', '#sheinCancelEditLmpBtn', function() {
            sheinResetLmpForm(sheinLmpCurrentSku);
        });

        // Delete a competitor LMP slot.
        $(document).on('click', '.shein-del-lmp', function() {
            const slot = $(this).data('slot');
            const sku = $(this).data('source-sku') || sheinLmpCurrentSku;
            if (!sku || !slot) return;
            if (!confirm('Remove this LMP entry?')) return;

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: '/shein/lmp/delete',
                method: 'POST',
                data: { sku: sku, slot: slot },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(res) {
                    if (res && res.success) {
                        sheinResetLmpForm(sheinLmpCurrentSku);
                        sheinRefreshLmpAfterChange(sheinLmpCurrentSku);
                        sheinLinksNotify('LMP removed', 'success');
                    } else {
                        sheinLinksNotify((res && res.message) || 'Error removing LMP', 'error');
                        $btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Error removing LMP';
                    sheinLinksNotify(msg, 'error');
                    $btn.prop('disabled', false).html('<i class="fa fa-trash"></i>');
                }
            });
        });

        $(document).on('click', '#sheinSaveLinksBtn', function() {
            if (!sheinEditLinksRow) return;
            const d = sheinEditLinksRow.getData();
            const sku = d.sku || '';
            const sellerLink = document.getElementById('sheinSellerLinkInput').value.trim();
            const buyerLink = document.getElementById('sheinBuyerLinkInput').value.trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: "/shein/save-links",
                method: 'POST',
                data: {
                    sku: sku,
                    buyer_link: buyerLink,
                    seller_link: sellerLink
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    if (res && res.success) {
                        sheinEditLinksRow.update({
                            'S Link': res.seller_link || '',
                            'B Link': res.buyer_link || ''
                        }).then(function() {
                            sheinEditLinksRow.reformat();
                        }).catch(function() {
                            sheinEditLinksRow.reformat();
                        });
                        sheinLinksNotify('Links saved', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById(
                            'sheinEditLinksModal')).hide();
                    } else {
                        sheinLinksNotify((res && res.message) || 'Error saving links', 'error');
                    }
                },
                error: function(xhr) {
                    let msg = 'Error saving links';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    sheinLinksNotify(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        // NR / REQ dropdown — saves to shein_listing_statuses (same source as listing-shein)
        $(document).on('change', '.shein-nr-dropdown', function() {
            const $select = $(this);
            const sku = $select.data('sku');
            const value = $select.val();

            const rows = table ? table.searchRows('sku', '=', sku) : [];
            if (rows.length) {
                rows[0].update({ nr_req: value });
            }

            $.ajax({
                url: '/listing_shein/save-status',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    nr_req: value
                },
                success: function(res) {
                    if (res && res.status === 'success') {
                        sheinLinksNotify(value === 'REQ' ? 'REQ updated' : 'NR updated', 'success');
                    } else {
                        sheinLinksNotify('Failed to save NR/REQ', 'error');
                    }
                },
                error: function() {
                    sheinLinksNotify('Failed to save NR/REQ for ' + sku, 'error');
                }
            });
        });
    </script>
@endsection
