@extends('layouts.vertical', ['title' => 'Newegg Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        .editable-cell {
            cursor: pointer;
        }
        .ne-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: zoom-in;
        }
        #ne-img-preview {
            position: fixed;
            display: none;
            z-index: 99999;
            pointer-events: none;
            border: 2px solid #0d6efd;
            border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
            background: #fff;
            padding: 3px;
        }
        #ne-img-preview img {
            display: block;
            max-width: 320px;
            max-height: 320px;
            object-fit: contain;
        }

        /* Sku Link LMP (same as TikTok / Shein) */
        .linked-sku-badge-wrap { display: inline-flex; align-items: center; gap: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove { font-size: 0.55rem; opacity: 0.65; padding: 0; margin-left: 2px; }
        .linked-sku-badge-wrap .sku-link-lmp-remove:hover { opacity: 1; }
        .sku-link-lmp-selected-chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 999px; background: #f1f5f9;
            border: 1px solid #e2e8f0; font-size: 12px; margin: 2px;
        }
        .sku-link-lmp-selected-chip button {
            border: 0; background: transparent; padding: 0; line-height: 1; font-size: 14px; color: #64748b;
        }

        /* Summary badges — equal-width slab (same as /tiktok-pricing / ebay2) */
        #summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
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
        .ne-toolbar-row {
            position: relative;
            z-index: 5;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Newegg Pricing',
        'sub_title' => 'Newegg Pricing & Inventory',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3 d-flex flex-column">
                {{-- Summary badges above filters (same slab layout as /tiktok-pricing) --}}
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                            style="color: black; font-weight: bold;" title="Σ (Price × L30)">Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                            style="color: black; font-weight: bold;" title="Overall PFT% = Σ profit / Σ sales">GPFT: 0%</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge"
                            style="color: black; font-weight: bold;">L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;" title="Click to filter sold items">&gt; 0 Sold: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge"
                            style="color: black; font-weight: bold;" title="Overall ROI% = Σ profit / Σ COGS">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="ne-missing-badge"
                            style="color: white; font-weight: bold; cursor: pointer;" title="Not listed on Newegg, REQ, INV &gt; 0 — click to filter">Missing L: 0</span>
                        <span class="badge fs-6 p-2" id="ne-map-badge"
                            style="background-color: #198754; color: white; font-weight: bold; cursor: pointer;" title="Listed, REQ, INV ≈ Newegg stock — click to filter">Map: 0</span>
                        <span class="badge fs-6 p-2" id="ne-nmap-badge"
                            style="background-color: #a71d2a; color: white; font-weight: bold; cursor: pointer;" title="Listed, REQ, INV ≠ Newegg stock — click to filter">N Map: 0</span>
                    </div>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2 ne-toolbar-row mb-1">
                    <input type="text" id="sku-search" class="form-control form-control-sm flex-shrink-0"
                        placeholder="Search SKU..." style="width: 150px;">

                    <select id="inventory-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all" selected>All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="n-stock-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;"
                        title="Newegg listing stock (N INV)">
                        <option value="all">N Stock</option>
                        <option value="zero">0 N Stock</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="l30-filter" class="form-select form-select-sm flex-shrink-0" style="width: 90px;"
                        title="Excludes 0 inventory items">
                        <option value="all">N L30</option>
                        <option value="0">0</option>
                        <option value="0-10">0-10</option>
                        <option value="10+">10+</option>
                    </select>

                    <select id="nr-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">Status</option>
                        <option value="REQ">REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;"
                        title="Newegg listing status">
                        <option value="all">All Listings</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>

                    {{-- GPFT/PFT slabs — same cutoffs as /ebay pricing --}}
                    <select id="pft-filter" class="form-select form-select-sm flex-shrink-0" style="width: 110px;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40plus">Above 40%</option>
                    </select>

                    {{-- ROI slabs — same cutoffs as /ebay pricing --}}
                    <select id="roi-filter" class="form-select form-select-sm flex-shrink-0" style="width: 100px;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    {{-- DIL Filter (plain select — matches /ebay pricing) --}}
                    <select id="dil-filter" class="form-select form-select-sm flex-shrink-0" style="width: 120px;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <div class="dropdown d-inline-block flex-shrink-0">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary flex-shrink-0" title="Show All Columns">
                        <i class="fa fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-success flex-shrink-0" id="export-btn" title="Export">
                        <i class="fa fa-file-excel"></i>
                    </button>

                    <div class="dropdown d-inline-block flex-shrink-0" id="sprice-mode-dropdown">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="sprice-mode-btn" data-bs-toggle="dropdown" aria-expanded="false"
                            title="SPRICE bulk mode: Decrease, Increase, or Same Price">
                            <i class="fas fa-sliders-h"></i> Price Mode
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="sprice-mode-btn">
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="">
                                <i class="fas fa-times text-muted me-1"></i> Off</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="decrease">
                                <i class="fas fa-arrow-down text-warning me-1"></i> Decrease</a></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="increase">
                                <i class="fas fa-arrow-up text-success me-1"></i> Increase</a></li>
                            <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="same"
                                title="Apply ONE price to every selected SKU">
                                <i class="fas fa-equals text-info me-1"></i> Same Price</a></li>
                        </ul>
                    </div>
                    <button id="push-all-sprice-btn" class="btn btn-sm btn-dark flex-shrink-0"
                        title="Push every currently-visible row that has a SPRICE live to Newegg (chunked)">
                        <i class="fas fa-cloud-upload-alt"></i> Push All
                    </button>

                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light flex-shrink-0"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC so SROI equals the target">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">ROI%:</label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button" title="Apply Target ROI% to selected SKUs">
                            <i class="fas fa-bullseye"></i>
                        </button>
                    </div>

                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light flex-shrink-0"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC so SPFT equals the target">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">GPFT%:</label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button" title="Apply Target GPFT% to selected SKUs">
                            <i class="fas fa-bullseye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount / Same Price input (shown when at least one SKU is selected) -->
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
                        <input type="number" id="discount-percentage-input"
                            class="form-control form-control-sm" placeholder="Enter %" step="0.01"
                            style="width: 140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                        <button id="push-newegg-btn" class="btn btn-dark btn-sm"
                            title="Push each selected SKU's SPRICE (or Price if no SPRICE) live to Newegg">
                            <i class="fas fa-cloud-upload-alt"></i> Push to Newegg
                        </button>
                    </div>
                </div>
                <div id="newegg-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="newegg-pricing-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating image preview -->
    <div id="ne-img-preview"><img src="" alt="preview"></div>

    <!-- Buyer / Seller link modal -->
    <div class="modal fade" id="bsLinkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buyer / Seller Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bs-sku">
                    <div class="mb-2"><small class="text-muted">SKU: <span id="bs-sku-label" class="fw-bold"></span></small></div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link</label>
                        <input type="url" class="form-control" id="bs-buyer-link" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link</label>
                        <input type="url" class="form-control" id="bs-seller-link" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bs-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Manual LMP modal (mirrors TikTok competitors modal) --}}
    <div class="modal fade" id="neLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:#F06C00;color:#fff;">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Newegg Competitors for SKU: <span id="neLmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-success" id="neCompFormCard">
                        <div class="card-header bg-success text-white" id="neCompFormHeader">
                            <strong><i class="fa fa-plus-circle" id="neCompFormHeaderIcon"></i> <span id="neCompFormHeaderText">Add New Competitor</span></strong>
                        </div>
                        <div class="card-body">
                            <form id="neAddCompetitorForm" class="row g-3">
                                <input type="hidden" id="neEditCompId" value="">
                                <div class="col-md-2">
                                    <label class="form-label"><strong>SKU</strong></label>
                                    <input type="text" class="form-control" id="neAddCompSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Item #</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="neAddCompProductId" placeholder="N82E168..." required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="neAddCompPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><strong>Ship</strong></label>
                                    <input type="number" class="form-control" id="neAddCompShip" placeholder="0.00" step="0.01" min="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><strong>Product Title</strong></label>
                                    <input type="text" class="form-control" id="neAddCompTitle" placeholder="Optional">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="neAddCompLink" placeholder="https://www.newegg.com/...">
                                </div>
                                <div class="col-md-1 d-flex align-items-end flex-wrap gap-1">
                                    <button type="submit" class="btn btn-success" id="neCompSubmitBtn" style="background:#F06C00;border-color:#F06C00;">
                                        <i class="fa fa-plus"></i> <span id="neCompSubmitBtnText">Add</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="neCompClearBtn">
                                        <i class="fa fa-undo"></i> Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="neLmpDataList">
                        <div class="text-center py-5 text-muted">Open a SKU to load competitors.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sku Link LMP Modal (shared sku.link.lmp.* routes) --}}
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
    <script>
        let table = null;
        let decreaseModeActive  = false;
        let increaseModeActive  = false;
        let samePriceModeActive = false;
        let selectedSkus        = new Set();

        // Filters — same slabs as /tiktok-pricing
        let inventoryFilter = 'all';
        let nStockFilter = 'all';   // 'all' | 'zero' | 'more'  (Newegg listing stock)
        let l30Filter    = 'all';   // 'all' | '0' | '0-10' | '10+'
        let nrFilter     = 'all';   // 'all' | 'REQ' | 'NR'
        let statusFilter = 'all';   // 'all' | 'Active' | 'Inactive'
        let pftFilter    = 'all';   // 'all' | 'negative' | '0-10'…'30-40' | '40plus'
        let roiFilter    = 'all';   // 'all' | 'lt40' | '40-75' | '75-125' | 'gt125'
        let dilFilter    = 'all';   // 'all' | 'red' | 'green' | 'pink'

        // Range helper for numeric bucket filters.
        function inRange(n, lo, hi) { return n >= lo && n < hi; }

        // GPFT%/PFT% slabs — same as /ebay #gpft-filter
        function pftMatches(pct, bucket) {
            if (bucket === 'all') return true;
            const n = parseFloat(pct);
            if (isNaN(n)) return false;
            switch (bucket) {
                case 'negative': return n < 0;
                case '0-10':     return inRange(n, 0, 10);
                case '10-20':    return inRange(n, 10, 20);
                case '20-30':    return inRange(n, 20, 30);
                case '30-40':    return inRange(n, 30, 40);
                case '40plus':   return n >= 40;
                default:         return true;
            }
        }

        // ROI% slabs — same as /ebay #roi-filter (125% lands in 125%+)
        function roiMatches(pct, bucket) {
            if (bucket === 'all') return true;
            const n = parseFloat(pct);
            if (isNaN(n)) return false;
            switch (bucket) {
                case 'lt40':    return n < 40;
                case '40-75':   return inRange(n, 40, 75);
                case '75-125':  return inRange(n, 75, 125);
                case 'gt125':   return n >= 125;
                default:        return true;
            }
        }

        // N L30 slabs — same as /tiktok-pricing #tl30-filter (excludes 0 INV)
        function l30Matches(l30, inv, bucket) {
            if (bucket === 'all') return true;
            const invVal = parseInt(inv) || 0;
            if (invVal <= 0) return false;
            const n = parseInt(l30) || 0;
            switch (bucket) {
                case '0':    return n === 0;
                case '0-10': return n > 0 && n <= 10;
                case '10+':  return n > 10;
                default:     return true;
            }
        }

        // DIL% color buckets — same thresholds as dilFormatter() / ebay (no yellow).
        function dilMatches(pct, color) {
            if (color === 'all') return true;
            const n = parseFloat(pct) || 0;
            switch (color) {
                case 'red':   return n < 25;          // absorbs former yellow band
                case 'green': return n >= 25 && n < 50;
                case 'pink':  return n >= 50;
                default:      return true;
            }
        }

        // Bootstrap-toast helper — same UX as reverb-pricing.
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                console[type === 'error' ? 'error' : 'log'](message);
                return;
            }
            const toast = document.createElement('div');
            const bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
            toast.className = `toast align-items-center text-white bg-${bg} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>`;
            toastContainer.appendChild(toast);
            new bootstrap.Toast(toast).show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        // Round to retail .99 endings (Reverb's rule: only above $20.99).
        function roundToRetailPrice(price) {
            if (price < 20.99) return +price.toFixed(2);
            return Math.ceil(price) - 0.01;
        }

        function moneyCol(title, field, visible = true) {
            return {
                title, field, visible,
                hozAlign: "right", sorter: "number",
                formatter: "money",
                formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
            };
        }

        // DIL% = sell-through (OVL30 / INV). Same buckets as ebay (red absorbs former yellow).
        function dilFormatter(cell) {
            const v = cell.getValue();
            if (v === null || v === undefined) return '<span style="color:#a00211;font-weight:bold;">0%</span>';
            const n = parseFloat(v);
            let color = '#a00211';
            if (n < 25) color = '#a00211';
            else if (n < 50) color = '#28a745';
            else color = '#e83e8c';
            return `<span style="color:${color}; font-weight:bold;">${n.toFixed(0)}%</span>`;
        }

        // ── Missing-listing / mapping state + helpers (same rules as map-issues) ──
        let neMissingActive = false, neMapActive = false, neNMapActive = false;
        let neZeroSoldActive = false, neMoreSoldActive = false;

        function neNr(row) {
            return String((row && row.nr) || 'REQ').trim().toUpperCase();
        }

        // INV vs Newegg stock = Map when diff ≤ 3 units (when 3% of INV < 3) else within rounded 3%.
        function neWithinMapTolerance(inv, neStock) {
            const i = parseFloat(inv) || 0;
            const s = parseFloat(neStock) || 0;
            if (i <= 0) return true;
            const diff = Math.abs(i - s);
            if (i * 0.03 < 3) return diff <= 3;
            return Math.round((diff / i) * 100) <= 3;
        }

        // Missing L — not listed on Newegg, REQ, INV > 0.
        function neRowMissingL(row) {
            if (!row) return false;
            const inv = parseFloat(row.inv) || 0;
            return !row.on_newegg && neNr(row) === 'REQ' && inv > 0;
        }

        // Map status — listed, REQ, INV > 0, Newegg stock > 0. Returns 'map' | 'nmap' | ''.
        function neMapStatus(row) {
            if (!row || !row.on_newegg) return '';
            const inv = parseFloat(row.inv) || 0;
            const neStock = parseFloat(row.available_quantity) || 0;
            if (neNr(row) !== 'REQ' || inv <= 0 || neStock <= 0) return '';
            return neWithinMapTolerance(inv, neStock) ? 'map' : 'nmap';
        }

        // ── Sku Link LMP (shared sku.link.lmp.* routes — same as TikTok / Shein) ──
        const linkedSkuAddUrl = @json(route('sku.link.lmp.linked-skus.add'));
        const linkedSkuBulkLinkUrl = @json(route('sku.link.lmp.linked-skus.bulk-link'));
        const linkedSkuRemoveUrl = @json(route('sku.link.lmp.linked-skus.remove'));
        const filteredSkusUrl = @json(route('sku.link.lmp.filtered-skus'));
        const skuLinkLmpCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

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
        function neEscAttr(text) { return escapeHtmlAttr(text); }

        function linkedLmpSkuFormatter(cell) {
            const row = cell.getRow().getData();
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
            const rowSku = rowSkuForLinkLmp(row);
            if (!rowSku) return '';
            return `<div class="d-flex align-items-center justify-content-center py-1">
                <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-add-btn" title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}"><i class="fas fa-plus"></i></button>
            </div>`;
        }

        function applyAffectedLinkedSkuRows() {
            if (table) table.replaceData();
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
                applyAffectedLinkedSkuRows();
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
                applyAffectedLinkedSkuRows();
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

        // ── Manual LMP competitors modal ──
        let neCurrentLmpSku = '';
        let neCurrentLinkedLmpSkus = [];
        let neEditCompetitorId = null;

        function neResetCompetitorForm(keepSku) {
            neEditCompetitorId = null;
            $('#neEditCompId').val('');
            $('#neAddCompSku').val(keepSku || neCurrentLmpSku || '');
            $('#neAddCompProductId').val('');
            $('#neAddCompPrice').val('');
            $('#neAddCompShip').val('');
            $('#neAddCompTitle').val('');
            $('#neAddCompLink').val('');
            $('#neCompFormHeaderText').text('Add New Competitor');
            $('#neCompFormHeaderIcon').attr('class', 'fa fa-plus-circle');
            $('#neCompFormHeader').removeClass('bg-warning text-dark').addClass('bg-success text-white');
            $('#neCompFormCard').removeClass('border-warning').addClass('border-success');
            $('#neCompSubmitBtnText').text('Add');
            $('#neCompSubmitBtn').find('i').attr('class', 'fa fa-plus');
            $('#neCompSubmitBtn').css({ background: '#F06C00', borderColor: '#F06C00', color: '#fff' });
        }

        function neEnterEditCompetitorMode(item) {
            if (!item || !item.id) return;
            neEditCompetitorId = item.id;
            $('#neEditCompId').val(item.id);
            $('#neAddCompSku').val(item.sku || neCurrentLmpSku || '');
            $('#neAddCompProductId').val(item.product_id || '');
            $('#neAddCompPrice').val(item.price != null ? parseFloat(item.price) : '');
            $('#neAddCompShip').val(item.shipping_cost != null ? parseFloat(item.shipping_cost) : 0);
            $('#neAddCompTitle').val(item.product_title || item.title || '');
            $('#neAddCompLink').val(item.product_link || item.link || '');
            $('#neCompFormHeaderText').text('Edit Competitor');
            $('#neCompFormHeaderIcon').attr('class', 'fa fa-edit');
            $('#neCompFormHeader').removeClass('bg-success text-white').addClass('bg-warning text-dark');
            $('#neCompFormCard').removeClass('border-success').addClass('border-warning');
            $('#neCompSubmitBtnText').text('Update');
            $('#neCompSubmitBtn').find('i').attr('class', 'fa fa-save');
            $('#neCompSubmitBtn').css({ background: '#ffc107', borderColor: '#ffc107', color: '#212529' });
            const formCard = document.getElementById('neCompFormCard');
            if (formCard) formCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function neLoadCompetitorsModal(sku, linkedLmpSkus) {
            neCurrentLmpSku = sku;
            neCurrentLinkedLmpSkus = Array.isArray(linkedLmpSkus) ? linkedLmpSkus : [];
            $('#neLmpSku').text(sku);
            neResetCompetitorForm(sku);

            const modalEl = document.getElementById('neLmpModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            $('#neLmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border" role="status" style="color:#F06C00;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `);

            const query = { sku: sku };
            (neCurrentLinkedLmpSkus || []).forEach(function (linkedSku, idx) {
                query['linked_lmp_skus[' + idx + ']'] = linkedSku;
            });
            $.ajax({
                url: '{{ route('newegg.competitors.get') }}',
                method: 'GET',
                data: query,
                success: function(response) {
                    neRenderCompetitorsList(response.success ? response.competitors : [], response.lowest_price || null);
                },
                error: function() {
                    neRenderCompetitorsList([], null);
                }
            });
        }

        function neRenderCompetitorsList(competitors, lowestPrice) {
            if (!competitors || competitors.length === 0) {
                $('#neLmpDataList').html(`
                    <div class="alert alert-info mb-0">
                        <i class="fa fa-info-circle"></i> No competitors found for this SKU. Add your first one above.
                    </div>
                `);
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm align-middle">';
            html += `
                <thead class="table-light">
                    <tr>
                        <th style="width:30px;">#</th>
                        <th style="width:140px;">Item #</th>
                        <th>Title</th>
                        <th>Seller</th>
                        <th style="width:80px;">Price</th>
                        <th style="width:70px;">Ship</th>
                        <th style="width:60px;">Link</th>
                        <th style="width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;

            competitors.forEach(function(item, index) {
                const basePrice = parseFloat(item.price) || 0;
                const shipCost = parseFloat(item.shipping_cost) || 0;
                const landedPrice = basePrice + shipCost;
                const isLowest = lowestPrice && Math.abs(landedPrice - parseFloat(lowestPrice)) < 0.01;
                const rowClass = isLowest ? 'table-success' : '';
                const priceFormatted = '$' + basePrice.toFixed(2);
                const priceBadge = isLowest
                    ? `<span class="badge bg-success">${priceFormatted} <i class="fa fa-trophy"></i></span>`
                    : `<strong>${priceFormatted}</strong>`;
                const shipHtml = shipCost === 0
                    ? '<span class="badge bg-info">FREE</span>'
                    : '$' + shipCost.toFixed(2);

                const productLink = item.link || item.product_link || '#';
                const title = item.title || item.product_title || 'N/A';
                const seller = item.seller_name || '—';

                html += `
                    <tr class="${rowClass}">
                        <td class="text-center"><strong>${index + 1}</strong></td>
                        <td><span class="text-primary" style="font-weight:600;font-size:11px;font-family:monospace;">${neEscAttr(item.product_id || 'N/A')}</span></td>
                        <td style="font-size:11px;" title="${neEscAttr(title)}">${neEscAttr(String(title).substring(0, 80))}${String(title).length > 80 ? '…' : ''}</td>
                        <td style="font-size:11px;">${neEscAttr(seller)}</td>
                        <td>${priceBadge}</td>
                        <td class="text-center">${shipHtml}</td>
                        <td class="text-center">
                            <a href="${neEscAttr(productLink)}" target="_blank" class="btn btn-sm btn-info" title="Open on Newegg">
                                <i class="fa fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn btn-sm btn-warning ne-edit-lmp-btn"
                                data-id="${item.id}"
                                data-sku="${neEscAttr(item.sku || '')}"
                                data-product-id="${neEscAttr(item.product_id || '')}"
                                data-price="${neEscAttr(basePrice)}"
                                data-shipping="${neEscAttr(shipCost)}"
                                data-title="${neEscAttr(title === 'N/A' ? '' : title)}"
                                data-link="${neEscAttr(productLink === '#' ? '' : productLink)}"
                                title="Edit this competitor">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger ne-delete-lmp-btn"
                                data-id="${item.id}"
                                title="Delete this competitor">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            $('#neLmpDataList').html(html);
        }

        $(document).ready(function() {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
            initSkuLinkLmpModal();

            table = new Tabulator("#newegg-pricing-table", {
                ajaxURL: "{{ route('newegg.pricing.data') }}",
                ajaxSorting: false,
                layout: "fitData",
                responsiveLayout: false,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: "rows",
                placeholder: "No Data Available",
                ajaxResponse: function(url, params, response) {
                    return Array.isArray(response) ? response : (response.data || []);
                },
                initialSort: [{ column: "l30", dir: "desc" }],
                columns: [
                    {
                        title: `<input type="checkbox" id="select-all-checkbox">`,
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        frozen: true,
                        visible: false,
                        width: 50,
                        formatter: function(cell) {
                            const sku = cell.getRow().getData().sku;
                            if (!sku) return '';
                            const isChecked = selectedSkus.has(sku);
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked ? 'checked' : ''}>`;
                        }
                    },
                    {
                        title: "Image", field: "image", hozAlign: "center", headerSort: false, frozen: true,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '';
                            return `<img src="${v}" class="ne-thumb" alt="img" loading="lazy">`;
                        }
                    },
                    { title: "SKU", field: "sku", frozen: true, headerFilter: "input", headerFilterPlaceholder: "Search SKU...", cssClass: "text-primary fw-bold" },
                    {
                        title: "B/S", field: "bs", hozAlign: "center", headerSort: false, frozen: true,
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const parts = [];
                            if (d.buyer_link) {
                                parts.push(`<a href="${d.buyer_link}" target="_blank" title="Buyer link" style="font-weight:bold;color:#0d6efd;text-decoration:none;">B</a>`);
                            }
                            if (d.seller_link) {
                                parts.push(`<a href="${d.seller_link}" target="_blank" title="Seller link" style="font-weight:bold;color:#198754;text-decoration:none;">S</a>`);
                            }
                            return parts.join(' / ');
                        },
                        cellClick: function(e, cell) {
                            if (e.target && e.target.tagName === 'A') return; // let links open
                            openBsModal(cell.getRow().getData());
                        }
                    },
                    { title: "Title", field: "title", visible: false, tooltip: true },
                    { title: "INV", field: "inv", hozAlign: "center", sorter: "number" },
                    { title: "N INV", field: "available_quantity", hozAlign: "center", sorter: "number" },
                    { title: "OVL30", field: "ovl30", hozAlign: "center", sorter: "number" },
                    { title: "DIL %", field: "dil", hozAlign: "center", sorter: "number", formatter: dilFormatter },
                    moneyCol("Price", "price"),
                    {
                        title: "A Prc", field: "a_price", hozAlign: "right", sorter: "number",
                        tooltip: "Amazon live selling price (from amazon_datasheet)",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<span style="color:#bbb;">—</span>';
                            const n = parseFloat(v) || 0;
                            if (n <= 0) return '<span style="color:#bbb;">—</span>';
                            const row = cell.getRow().getData();
                            const ne  = parseFloat(row.price) || 0;
                            // Color compared to Newegg price: green = Newegg is cheaper, red = Newegg is more expensive.
                            let color = '#212529';
                            if (ne > 0 && Math.abs(ne - n) > 0.01) {
                                color = ne < n ? '#28a745' : '#dc3545';
                            }
                            return `<span style="color:${color};font-weight:600;">$${n.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        tooltip: "Lowest Newegg competitor price (manual LMP)",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (window.ParentExpand) {
                                const avgHtml = ParentExpand.parentAvgLmpHtml(rowData, {
                                    dataset: typeof allTableData !== 'undefined' ? allTableData : undefined,
                                    field: 'lmp_price'
                                });
                                if (avgHtml !== null) return avgHtml;
                            }
                            const lmpPrice = parseFloat(cell.getValue() || 0);
                            const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
                            const sku = rowData.sku || '';
                            const skuAttr = String(sku).replace(/"/g, '&quot;');
                            const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
                            const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));

                            if (!lmpPrice && totalCompetitors === 0) {
                                return `<a href="#" class="view-ne-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color:#6c757d;text-decoration:none;font-size:11px;cursor:pointer;"
                                    title="No competitors — click to add one">
                                    <i class="fa fa-plus-circle"></i> Add
                                </a>`;
                            }

                            const currentPrice = parseFloat(rowData.price || 0);
                            const priceColor = (lmpPrice > 0 && lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                            const lmpBase = parseFloat(rowData.lmp_base_price || 0) || lmpPrice;
                            const lmpShip = parseFloat(rowData.lmp_shipping || 0) || 0;
                            const shipTip = lmpShip > 0
                                ? ` title="$${lmpBase.toFixed(2)} + $${lmpShip.toFixed(2)} ship"`
                                : '';

                            let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:2px;line-height:1.1;">';
                            if (lmpPrice) {
                                html += `<span style="color:${priceColor};font-weight:700;font-size:14px;"${shipTip}>$${lmpPrice.toFixed(2)}</span>`;
                            }
                            if (totalCompetitors > 0) {
                                html += `<a href="#" class="view-ne-lmp-competitors" data-sku="${skuAttr}" data-linked-skus="${linkedSkusAttr}"
                                    style="color:#F06C00;text-decoration:none;font-size:11px;cursor:pointer;">
                                    <i class="fa fa-eye"></i> View ${totalCompetitors}
                                </a>`;
                            }
                            html += '</div>';
                            return html;
                        }
                    },
                    {
                        title: "Diff",
                        field: "lmp_diff_pct",
                        hozAlign: "center",
                        width: 70,
                        headerSortStartingDir: "desc",
                        sorter: function(a, b, aRow, bRow) {
                            const calc = function(rd) {
                                const lmp = parseFloat(rd.lmp_price || 0);
                                const price = parseFloat(rd.price || 0);
                                if (!lmp || lmp <= 0) return -Infinity;
                                return ((lmp - price) / lmp) * 100;
                            };
                            return calc(aRow.getData()) - calc(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const lmp = parseFloat(rowData.lmp_price || 0);
                            const price = parseFloat(rowData.price || 0);
                            if (!lmp || lmp <= 0) return '<span style="color:#999;">N/A</span>';
                            const diff = ((lmp - price) / lmp) * 100;
                            const color = diff < 0 ? '#dc3545' : '#28a745';
                            return `<span style="color:${color};font-weight:600;">${diff.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "Sku Link LMP",
                        field: "linked_lmp_skus",
                        hozAlign: "left",
                        headerHozAlign: "center",
                        width: 200,
                        headerSort: false,
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
                        formatter: linkedLmpSkuAddFormatter,
                        cellClick: function(e, cell) {
                            if (e.target.closest('.sku-link-lmp-add-btn')) {
                                e.preventDefault();
                                e.stopPropagation();
                                openLinkedSkuModal(cell.getRow().getData());
                            }
                        },
                    },
                    { title: "L30", field: "l30", hozAlign: "center", sorter: "number",
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue()) || 0;
                            return v > 0 ? `<span style="color:#28a745;font-weight:bold;">${v}</span>` : '0';
                        }
                    },
                    {
                        title: "Pft %", field: "pft_pct", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            // Same GPFT color bands as /ebay
                            let color = '#e83e8c';
                            if (n < 10) color = '#a00211';
                            else if (n < 20) color = '#3591dc';
                            else if (n < 30) color = '#ffc107';
                            else if (n < 50) color = '#28a745';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "ROI %", field: "roi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            // Same ROI color bands as /ebay
                            let color = '#d63384';
                            if (n < 40) color = '#a00211';
                            else if (n < 75) color = '#ffc107';
                            else if (n < 125) color = '#28a745';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "SPrice", field: "sprice", hozAlign: "right", sorter: "number",
                        editor: "number", editorParams: { min: 0, step: 0.01 },
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<span style="color:#bbb;">—</span>';
                            return '$' + (parseFloat(v) || 0).toFixed(2);
                        }
                    },
                    {
                        title: "Missing L", field: "missing_l", hozAlign: "center", headerSort: false,
                        formatter: function(cell) {
                            return neRowMissingL(cell.getRow().getData())
                                ? '<span style="color:#c0392b;font-weight:bold;">Missing L</span>'
                                : '';
                        }
                    },
                    {
                        title: "Map", field: "map_status", hozAlign: "center", headerSort: false,
                        formatter: function(cell) {
                            const st = neMapStatus(cell.getRow().getData());
                            if (st === 'map') return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            if (st === 'nmap') return '<span style="color:#dc3545;font-weight:bold;">N Map</span>';
                            return '';
                        }
                    },
                    {
                        title: "SPft %", field: "spft", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            // Same GPFT color bands as /ebay
                            let color = '#e83e8c';
                            if (n < 10) color = '#a00211';
                            else if (n < 20) color = '#3591dc';
                            else if (n < 30) color = '#ffc107';
                            else if (n < 50) color = '#28a745';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "SROI %", field: "sroi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            // Same ROI color bands as /ebay
                            let color = '#d63384';
                            if (n < 40) color = '#a00211';
                            else if (n < 75) color = '#ffc107';
                            else if (n < 125) color = '#28a745';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "NR/REQ", field: "nr", hozAlign: "center",
                        headerSort: false, cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue() || 'REQ';
                            const color = v === 'NR' ? '#dc3545' : '#28a745';
                            return `<span title="Click to toggle" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${color};"></span>`;
                        },
                        cellClick: function(e, cell) {
                            const row = cell.getRow();
                            const data = row.getData();
                            const next = (data.nr === 'NR') ? 'REQ' : 'NR';
                            row.update({ nr: next });
                            fetch("{{ route('newegg.pricing.save.nr') }}", {
                                method: "POST",
                                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                                body: JSON.stringify({ sku: data.sku, nr: next })
                            })
                            .then(r => r.json())
                            .then(res => { if (!res.success) alert(res.error || "Failed to save NR"); })
                            .catch(() => alert("Failed to save NR"));
                        }
                    },
                    {
                        title: "Status", field: "status", hozAlign: "center",
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            if (!v) return '';
                            const isActive = v === 'Active';
                            const color = isActive ? '#28a745' : '#dc3545';
                            const letter = isActive ? 'A' : 'I';
                            return `<span title="${v}" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:${color};color:#fff;font-weight:bold;font-size:12px;">${letter}</span>`;
                        }
                    },
                    moneyCol("LP", "lp", false),
                    moneyCol("Ship", "ship", false),
                    { title: "Currency", field: "currency", visible: false }
                ]
            });

            // Floating image preview on thumbnail hover.
            const imgPreview = document.getElementById('ne-img-preview');
            const imgPreviewImg = imgPreview ? imgPreview.querySelector('img') : null;
            const tableEl = document.getElementById('newegg-pricing-table');

            function positionPreview(e) {
                const pad = 16;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                const w = imgPreview.offsetWidth || 326;
                const h = imgPreview.offsetHeight || 326;
                if (x + w > window.innerWidth) x = e.clientX - w - pad;
                if (y + h > window.innerHeight) y = window.innerHeight - h - pad;
                if (y < 0) y = pad;
                imgPreview.style.left = x + 'px';
                imgPreview.style.top = y + 'px';
            }

            if (tableEl && imgPreview && imgPreviewImg) {
                tableEl.addEventListener('mouseover', function(e) {
                    const thumb = e.target.closest('.ne-thumb');
                    if (!thumb) return;
                    imgPreviewImg.src = thumb.getAttribute('src');
                    imgPreview.style.display = 'block';
                    positionPreview(e);
                });
                tableEl.addEventListener('mousemove', function(e) {
                    if (imgPreview.style.display === 'block') positionPreview(e);
                });
                tableEl.addEventListener('mouseout', function(e) {
                    if (e.target.closest('.ne-thumb')) imgPreview.style.display = 'none';
                });
            }

            // Save SPRICE / NR on edit.
            table.on("cellEdited", function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();

                if (field === "sprice") {
                    fetch("{{ route('newegg.pricing.save.sprice') }}", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                        body: JSON.stringify({ sku: data.sku, sprice: cell.getValue() })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            row.update({ spft: res.spft, sroi: res.sroi, sprice: res.sprice });
                        } else {
                            alert(res.error || "Failed to save SPrice");
                        }
                    })
                    .catch(() => alert("Failed to save SPrice"));
                }
            });

            // Open Buyer/Seller link modal by clicking the B/S cell.
            let bsModal = null;
            function openBsModal(d) {
                d = d || {};
                document.getElementById('bs-sku').value = d.sku || '';
                document.getElementById('bs-sku-label').textContent = d.sku || '';
                document.getElementById('bs-buyer-link').value = d.buyer_link || '';
                document.getElementById('bs-seller-link').value = d.seller_link || '';
                if (!bsModal) bsModal = new bootstrap.Modal(document.getElementById('bsLinkModal'));
                bsModal.show();
            }

            document.getElementById('bs-save-btn').addEventListener('click', function() {
                const sku = document.getElementById('bs-sku').value;
                const buyer = document.getElementById('bs-buyer-link').value.trim();
                const seller = document.getElementById('bs-seller-link').value.trim();
                fetch("{{ route('newegg.pricing.save.links') }}", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                    body: JSON.stringify({ sku: sku, buyer_link: buyer, seller_link: seller })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const rows = table.searchRows('sku', '=', sku);
                        if (rows.length) {
                            rows[0].update({ buyer_link: res.buyer_link, seller_link: res.seller_link })
                                .then(() => rows[0].reformat());
                        }
                        if (bsModal) bsModal.hide();
                    } else {
                        alert(res.error || "Failed to save links");
                    }
                })
                .catch(() => alert("Failed to save links"));
            });

            // Combined filter: SKU/Title search + INV / N Stock / NR / Status / PFT / ROI / DIL
            // dropdowns + active Missing L / Map / N Map badge.
            function applyNeFilters() {
                const search = ($('#sku-search').val() || '').trim().toLowerCase();
                table.setFilter(function(row) {
                    if (search) {
                        const sku = String(row.sku || '').toLowerCase();
                        const title = String(row.title || '').toLowerCase();
                        if (sku.indexOf(search) === -1 && title.indexOf(search) === -1) return false;
                    }

                    // INV (Shopify inventory)
                    const invVal = parseInt(row.inv) || 0;
                    if (inventoryFilter === 'zero' && invVal !== 0) return false;
                    if (inventoryFilter === 'more' && invVal <= 0) return false;

                    // N Stock (Newegg listing stock)
                    const nStock = parseInt(row.available_quantity) || 0;
                    if (nStockFilter === 'zero' && nStock !== 0) return false;
                    if (nStockFilter === 'more' && nStock <= 0) return false;

                    // N L30 slabs (same as TikTok T L30 — excludes 0 INV)
                    if (!l30Matches(row.l30, row.inv, l30Filter)) return false;

                    // NR / REQ flag
                    if (nrFilter !== 'all') {
                        const nr = String(row.nr || 'REQ').toUpperCase();
                        if (nrFilter === 'REQ' && nr !== 'REQ') return false;
                        if (nrFilter === 'NR'  && nr !== 'NR')  return false;
                    }

                    // Listing status
                    if (statusFilter !== 'all') {
                        const st = String(row.status || '');
                        if (st !== statusFilter) return false;
                    }

                    // GPFT / ROI / DIL bucket filters
                    if (!pftMatches(row.pft_pct, pftFilter)) return false;
                    if (!roiMatches(row.roi,     roiFilter)) return false;
                    if (!dilMatches(row.dil,     dilFilter)) return false;

                    // Sold badge filters
                    const l30Val = parseInt(row.l30) || 0;
                    if (neZeroSoldActive && l30Val !== 0) return false;
                    if (neMoreSoldActive && l30Val <= 0) return false;

                    // Missing / Map / N Map badge filters
                    if (neMissingActive && !neRowMissingL(row)) return false;
                    if (neMapActive && neMapStatus(row) !== 'map') return false;
                    if (neNMapActive && neMapStatus(row) !== 'nmap') return false;

                    return true;
                });
                updateBadgeStyles();
                setTimeout(updateSummary, 100);
            }

            function setNeBadgeActive($el, active) {
                $el.toggleClass('border border-3 border-dark', !!active);
                if (active) {
                    $el.css('box-shadow', '0 0 0 2px rgba(0,0,0,0.25)');
                } else {
                    $el.css('box-shadow', '');
                }
            }

            function updateBadgeStyles() {
                setNeBadgeActive($('#zero-sold-count-badge'), neZeroSoldActive);
                setNeBadgeActive($('#more-sold-count-badge'), neMoreSoldActive);
                setNeBadgeActive($('#ne-missing-badge'), neMissingActive);
                setNeBadgeActive($('#ne-map-badge'), neMapActive);
                setNeBadgeActive($('#ne-nmap-badge'), neNMapActive);
            }

            function neOnSummaryFilterBadgeClick(type) {
                if (type === 'zero-sold') {
                    neZeroSoldActive = !neZeroSoldActive;
                    neMoreSoldActive = neMissingActive = neMapActive = neNMapActive = false;
                } else if (type === 'more-sold') {
                    neMoreSoldActive = !neMoreSoldActive;
                    neZeroSoldActive = neMissingActive = neMapActive = neNMapActive = false;
                } else if (type === 'missing') {
                    neMissingActive = !neMissingActive;
                    neZeroSoldActive = neMoreSoldActive = neMapActive = neNMapActive = false;
                } else if (type === 'map') {
                    neMapActive = !neMapActive;
                    neZeroSoldActive = neMoreSoldActive = neMissingActive = neNMapActive = false;
                } else if (type === 'nmap') {
                    neNMapActive = !neNMapActive;
                    neZeroSoldActive = neMoreSoldActive = neMissingActive = neMapActive = false;
                }
                applyNeFilters();
            }

            $('#sku-search').on('keyup', applyNeFilters);

            $('#zero-sold-count-badge').on('click', function() { neOnSummaryFilterBadgeClick('zero-sold'); });
            $('#more-sold-count-badge').on('click', function() { neOnSummaryFilterBadgeClick('more-sold'); });
            $('#ne-missing-badge').on('click', function() { neOnSummaryFilterBadgeClick('missing'); });
            $('#ne-map-badge').on('click', function() { neOnSummaryFilterBadgeClick('map'); });
            $('#ne-nmap-badge').on('click', function() { neOnSummaryFilterBadgeClick('nmap'); });

            // ── Toolbar filter wiring ──────────────────────────────────────────
            $('#inventory-filter').on('change', function() { inventoryFilter = $(this).val(); applyNeFilters(); });
            $('#n-stock-filter')  .on('change', function() { nStockFilter    = $(this).val(); applyNeFilters(); });
            $('#l30-filter')      .on('change', function() { l30Filter       = $(this).val(); applyNeFilters(); });
            $('#nr-filter')       .on('change', function() { nrFilter        = $(this).val(); applyNeFilters(); });
            $('#status-filter')   .on('change', function() { statusFilter    = $(this).val(); applyNeFilters(); });
            $('#pft-filter')      .on('change', function() { pftFilter       = $(this).val(); applyNeFilters(); });
            $('#roi-filter')      .on('change', function() { roiFilter       = $(this).val(); applyNeFilters(); });
            $('#dil-filter')      .on('change', function() { dilFilter       = $(this).val(); applyNeFilters(); });

            // ── SPRICE bulk tools (single Price Mode dropdown) ────────────────
            function syncSpriceModeBtn() {
                const $btn = $('#sprice-mode-btn');
                $btn.removeClass('btn-secondary btn-warning btn-success btn-info btn-danger');
                $('.sprice-mode-item').removeClass('active');

                if (decreaseModeActive) {
                    $btn.addClass('btn-warning')
                        .html('<i class="fas fa-arrow-down"></i> Decrease');
                    $('.sprice-mode-item[data-mode="decrease"]').addClass('active');
                } else if (increaseModeActive) {
                    $btn.addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase');
                    $('.sprice-mode-item[data-mode="increase"]').addClass('active');
                } else if (samePriceModeActive) {
                    $btn.addClass('btn-info')
                        .html('<i class="fas fa-equals"></i> Same Price');
                    $('.sprice-mode-item[data-mode="same"]').addClass('active');
                } else {
                    $btn.addClass('btn-secondary')
                        .html('<i class="fas fa-sliders-h"></i> Price Mode');
                    $('.sprice-mode-item[data-mode=""]').addClass('active');
                }
            }

            // Swap the input panel between %/$ entry (Increase/Decrease) and a flat $ price (Same Price).
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

            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#discount-input-container').toggle(count > 0);
            }

            function updateSelectAllCheckbox() {
                if (!table) return;
                const visible = table.getData('active').filter(r => r.sku);
                if (visible.length === 0) { $('#select-all-checkbox').prop('checked', false); return; }
                const allSelected = visible.every(r => selectedSkus.has(r.sku));
                $('#select-all-checkbox').prop('checked', allSelected);
            }

            function enterMode(which) {
                // Selecting the already-active mode (or Off) turns mode off.
                const turningOff = !which
                    || (which === 'decrease' && decreaseModeActive)
                    || (which === 'increase' && increaseModeActive)
                    || (which === 'same' && samePriceModeActive);

                decreaseModeActive  = !turningOff && which === 'decrease';
                increaseModeActive  = !turningOff && which === 'increase';
                samePriceModeActive = !turningOff && which === 'same';

                const anyOn = decreaseModeActive || increaseModeActive || samePriceModeActive;
                syncSpriceModeBtn();

                const selCol = table.getColumn('_select');
                if (selCol) {
                    if (anyOn) {
                        selCol.show();
                    } else {
                        selCol.hide();
                        selectedSkus.clear();
                        updateSelectedCount();
                    }
                }
                syncDiscountInputUi();
                table.redraw(true);
            }

            $(document).on('click', '.sprice-mode-item', function(e) {
                e.preventDefault();
                enterMode($(this).data('mode') || '');
            });
            $('#push-all-sprice-btn').on('click', pushAllSpriceVisible);
            $('#discount-type-select').on('change', syncDiscountInputUi);

            // Header "select all" — selects every currently-visible SKU.
            $(document).on('change', '#select-all-checkbox', function() {
                const checked = $(this).prop('checked');
                table.getData('active').forEach(r => {
                    if (!r.sku) return;
                    if (checked) selectedSkus.add(r.sku); else selectedSkus.delete(r.sku);
                });
                table.redraw(true);
                updateSelectedCount();
            });

            // Per-row checkbox.
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                if ($(this).prop('checked')) selectedSkus.add(sku); else selectedSkus.delete(sku);
                updateSelectedCount();
                updateSelectAllCheckbox();
            });

            // Apply on click / Enter.
            $('#apply-discount-btn').on('click', applyDiscount);
            $('#discount-percentage-input').on('keypress', function(e) { if (e.which === 13) applyDiscount(); });
            $('#clear-sprice-btn').on('click', clearSpriceForSelected);
            $('#push-newegg-btn').on('click', pushSelectedToNewegg);

            // --- Target ROI% / Target GPFT% bulk apply --------------------------------
            // Back-solves SPRICE, then nudges ±$0.01 so SROI/SPFT match the target after
            // 2-decimal rounding. Server recomputes from ProductMaster LP/Ship with the
            // same nudge so every selected row lands on the same SROI (no 25/26/27 drift).

            function neAchievedRoi(sprice, lp, ship, factor) {
                const profit = (sprice * factor) - lp - ship;
                return lp > 0 ? Math.round((profit / lp) * 100) : 0;
            }
            function neAchievedSpft(sprice, lp, ship, factor) {
                if (!(sprice > 0)) return 0;
                const profit = (sprice * factor) - lp - ship;
                return Math.round((profit / sprice) * 100 * 10) / 10;
            }

            /** Back-solve + nudge SPRICE so integer SROI equals target. */
            function neSpriceForTargetRoi(lp, ship, factor, targetRoiPct) {
                if (!(lp > 0) || !(factor > 0)) return null;
                const target = Math.round(targetRoiPct);
                let sprice = +((lp * (1 + targetRoiPct / 100) + ship) / factor).toFixed(2);
                if (!(sprice > 0)) return null;
                let roi = neAchievedRoi(sprice, lp, ship, factor);
                let guard = 0;
                while (roi < target && guard < 5000) {
                    sprice = +(sprice + 0.01).toFixed(2);
                    roi = neAchievedRoi(sprice, lp, ship, factor);
                    guard++;
                }
                while (roi > target && sprice > 0.01 && guard < 5000) {
                    sprice = +(sprice - 0.01).toFixed(2);
                    roi = neAchievedRoi(sprice, lp, ship, factor);
                    guard++;
                }
                return sprice;
            }

            /** Back-solve + nudge SPRICE so SPFT (1 decimal) equals target. */
            function neSpriceForTargetGpft(lp, ship, factor, targetGpftPct) {
                if (!(lp > 0) || !(factor > 0)) return null;
                const denom = factor - (targetGpftPct / 100);
                if (!(denom > 0)) return null;
                const target = Math.round(targetGpftPct * 10) / 10;
                let sprice = +((lp + ship) / denom).toFixed(2);
                if (!(sprice > 0)) return null;
                let gpft = neAchievedSpft(sprice, lp, ship, factor);
                let guard = 0;
                while (gpft < target - 0.05 && guard < 5000) {
                    sprice = +(sprice + 0.01).toFixed(2);
                    gpft = neAchievedSpft(sprice, lp, ship, factor);
                    guard++;
                }
                while (gpft > target + 0.05 && sprice > 0.01 && guard < 5000) {
                    sprice = +(sprice - 0.01).toFixed(2);
                    gpft = neAchievedSpft(sprice, lp, ship, factor);
                    guard++;
                }
                return sprice;
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
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU first (choose a Price Mode to reveal checkboxes)', 'error');
                    return;
                }

                const targetSroi = Math.round(targetRoiPct);
                const updates = [];
                let updatedCount = 0;
                let skippedNoLp = 0;

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const row = rows[0];
                    const d   = row.getData();
                    const lp  = parseFloat(d.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const ship   = parseFloat(d.ship)   || 0;
                    const factor = parseFloat(d.factor) || 0.80;
                    const newSprice = neSpriceForTargetRoi(lp, ship, factor, targetRoiPct);
                    if (newSprice == null || !(newSprice > 0)) return;

                    const profit = (newSprice * factor) - lp - ship;
                    const spft = newSprice > 0 ? Math.round((profit / newSprice) * 100 * 10) / 10 : 0;

                    // Show the exact target SROI on every row (server will persist the same).
                    row.update({ sprice: newSprice, spft: spft, sroi: targetSroi });
                    updates.push({ sku: sku, target_roi: targetRoiPct });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                    return;
                }

                saveSpriceUpdates(updates);
                const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
                showToast(`Target ROI ${targetSroi}% applied to ${updatedCount} SKU(s)${note}`, 'success');
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
                    showToast('Please select at least one SKU first (choose a Price Mode to reveal checkboxes)', 'error');
                    return;
                }

                const targetSpft = Math.round(targetGpftPct * 10) / 10;
                const updates = [];
                let updatedCount = 0;
                let skippedNoLp = 0;
                const skippedHighGpft = [];

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const row = rows[0];
                    const d   = row.getData();
                    const lp  = parseFloat(d.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const ship   = parseFloat(d.ship)   || 0;
                    const factor = parseFloat(d.factor) || 0.80;
                    if (factor - (targetGpftPct / 100) <= 0) { skippedHighGpft.push(sku); return; }
                    const newSprice = neSpriceForTargetGpft(lp, ship, factor, targetGpftPct);
                    if (newSprice == null || !(newSprice > 0)) return;

                    const profit = (newSprice * factor) - lp - ship;
                    const sroi = lp > 0 ? Math.round((profit / lp) * 100) : 0;

                    row.update({ sprice: newSprice, spft: targetSpft, sroi: sroi });
                    updates.push({ sku: sku, target_gpft: targetGpftPct });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    if (skippedHighGpft.length > 0) {
                        showToast(`Target GPFT% ${targetGpftPct}% is too high — must be less than each row's take-home factor (e.g. < 80%).`, 'error');
                    } else {
                        showToast('No selected rows have a usable LP > 0', 'warning');
                    }
                    return;
                }

                saveSpriceUpdates(updates);
                let note = '';
                if (skippedNoLp > 0)        note += ` (${skippedNoLp} skipped — no LP)`;
                if (skippedHighGpft.length) note += ` (${skippedHighGpft.length} skipped — target ≥ factor)`;
                showToast(`Target GPFT ${targetSpft}% applied to ${updatedCount} SKU(s)${note}`, 'success');
            });

            $('#target-roi-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-roi-btn').click();
            });
            $('#target-gpft-input').on('keypress', function(e) {
                if (e.which === 13) $('#apply-target-gpft-btn').click();
            });

            // Compute and apply Increase / Decrease / Same Price to the selected SKUs.
            function applyDiscount() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Choose a Price Mode first (Decrease / Increase / Same Price)', 'error');
                    return;
                }
                const discountType  = $('#discount-type-select').val();
                const discountValue = parseFloat($('#discount-percentage-input').val());
                if (isNaN(discountValue) || discountValue <= 0) {
                    showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
                    return;
                }
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                let updatedCount = 0;
                const updates = [];

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const row = rows[0];
                    const d   = row.getData();
                    const currentPrice = parseFloat(d.price) || 0;

                    // %/$ modes need a positive Newegg price to compute against;
                    // Same Price mode works regardless of current Newegg price.
                    if (!samePriceModeActive && !(currentPrice > 0)) return;

                    let newSprice;
                    if (samePriceModeActive) {
                        newSprice = discountValue;
                    } else if (discountType === 'percentage') {
                        newSprice = increaseModeActive
                            ? currentPrice * (1 + discountValue / 100)
                            : currentPrice * (1 - discountValue / 100);
                    } else {
                        newSprice = increaseModeActive
                            ? currentPrice + discountValue
                            : currentPrice - discountValue;
                    }
                    newSprice = Math.max(0.99, roundToRetailPrice(newSprice));

                    // Optimistic SPFT / SROI using the row's server-provided factor (~0.80 by default).
                    const factor = parseFloat(d.factor) || 0.80;
                    const lp     = parseFloat(d.lp)     || 0;
                    const ship   = parseFloat(d.ship)   || 0;
                    const profit = (newSprice * factor) - lp - ship;
                    const spft   = newSprice > 0 ? Math.round((profit / newSprice) * 100 * 10) / 10 : 0;
                    const sroi   = lp > 0 ? Math.round((profit / lp) * 100) : 0;

                    row.update({ sprice: newSprice, spft: spft, sroi: sroi });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length > 0) saveSpriceUpdates(updates);

                const action = samePriceModeActive ? 'Same Price'
                    : (increaseModeActive ? 'Increase' : 'Decrease');
                const suffix = samePriceModeActive ? '' : ' based on Newegg Price';
                showToast(`${action} applied to ${updatedCount} SKU(s)${suffix}`, 'success');
                $('#discount-percentage-input').val('');
            }

            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) { showToast('Please select SKUs first', 'error'); return; }
                if (!confirm(`Clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) return;

                const updates = [];
                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    rows[0].update({ sprice: null, spft: null, sroi: null });
                    updates.push({ sku: sku, sprice: null });
                });
                if (updates.length > 0) saveSpriceUpdates(updates);
                showToast(`SPRICE cleared for ${updates.length} SKU(s)`, 'success');
            }

            // Newegg's price update endpoint accepts many items per PUT but very
            // large bodies can be rejected by the edge — chunk client-side.
            const PUSH_CHUNK_SIZE = 100;

            // Shared push pipeline: chunks updates, calls /newegg-pricing-push for each
            // chunk sequentially, reconciles per-row Price cells, and summarises in one toast.
            function pushUpdatesInChunks(updates, $btn) {
                if (!updates || updates.length === 0) {
                    showToast('Nothing to push', 'error');
                    return;
                }

                const origHtml = $btn ? $btn.html() : null;
                if ($btn) $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing 0/' + updates.length + '...');

                const chunks = [];
                for (let i = 0; i < updates.length; i += PUSH_CHUNK_SIZE) {
                    chunks.push(updates.slice(i, i + PUSH_CHUNK_SIZE));
                }

                let totalPushed = 0;
                let totalFailed = 0;
                const allFails  = [];
                let done = 0;

                function next(idx) {
                    if (idx >= chunks.length) {
                        if ($btn) $btn.prop('disabled', false).html(origHtml);
                        const msgType = totalFailed > 0 ? (totalPushed > 0 ? 'warning' : 'error') : 'success';
                        showToast(`Newegg push complete: ${totalPushed} ok, ${totalFailed} failed`, msgType);
                        if (allFails.length) {
                            console.warn('Newegg push failures:', allFails);
                            const sample = allFails.slice(0, 3).map(f => `• ${f.sku}: ${f.error}`).join('\n');
                            const more   = allFails.length > 3 ? `\n…and ${allFails.length - 3} more (see console)` : '';
                            showToast(`Failed:\n${sample}${more}`, 'error');
                        }
                        return;
                    }

                    $.ajax({
                        url: "{{ route('newegg.pricing.push') }}",
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        data: { updates: chunks[idx] },
                        success: function(res) {
                            totalPushed += (res.pushed || 0);
                            totalFailed += (res.failed || 0);
                            (res.results || []).filter(r => r.success).forEach(r => {
                                const rows = table.searchRows('sku', '=', r.sku);
                                if (rows.length) rows[0].update({ price: r.price });
                            });
                            (res.results || []).filter(r => !r.success).forEach(r => allFails.push(r));
                        },
                        error: function(xhr) {
                            const r = xhr.responseJSON || {};
                            // Whole chunk failed (e.g. Cloudflare 502). Count each row as failed.
                            chunks[idx].forEach(u => allFails.push({
                                sku: u.sku, error: (r.error || `HTTP ${xhr.status}`)
                            }));
                            totalFailed += chunks[idx].length;
                        },
                        complete: function() {
                            done++;
                            if ($btn) $btn.html(`<i class="fas fa-spinner fa-spin"></i> Pushing ${done * PUSH_CHUNK_SIZE > updates.length ? updates.length : done * PUSH_CHUNK_SIZE}/${updates.length}...`);
                            next(idx + 1);
                        }
                    });
                }

                next(0);
            }

            // Live-push each SELECTED SKU's SPRICE (or current Newegg price as fallback).
            function pushSelectedToNewegg() {
                if (selectedSkus.size === 0) { showToast('Please select SKUs first', 'error'); return; }

                const updates = [];
                const skipped = [];
                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const d = rows[0].getData();
                    const price = parseFloat(d.sprice) > 0 ? parseFloat(d.sprice)
                                : (parseFloat(d.price) > 0 ? parseFloat(d.price) : 0);
                    if (price <= 0) { skipped.push(sku); return; }
                    updates.push({ sku: sku, price: +price.toFixed(2) });
                });

                if (updates.length === 0) {
                    showToast('No selected SKU has a positive SPRICE or Price to push', 'error');
                    return;
                }

                const summary = `Push ${updates.length} price${updates.length !== 1 ? 's' : ''} live to Newegg?`
                    + (skipped.length ? `\n(${skipped.length} skipped — no SPRICE/Price)` : '');
                if (!confirm(summary)) return;
                pushUpdatesInChunks(updates, $('#push-newegg-btn'));
            }

            // Live-push EVERY currently-visible row that has a SPRICE > 0.
            // Honours all active filters (INV, PFT, ROI, DIL, NR, Status, badges, search).
            function pushAllSpriceVisible() {
                const visible = table.getData('active'); // active = post-filter, post-sort
                const updates = [];
                visible.forEach(d => {
                    if (!d.sku) return;
                    const sp = parseFloat(d.sprice);
                    if (!(sp > 0)) return;
                    updates.push({ sku: d.sku, price: +sp.toFixed(2) });
                });

                if (updates.length === 0) {
                    showToast('No visible row has a SPRICE to push', 'error');
                    return;
                }
                if (!confirm(`Push SPRICE for ${updates.length} visible SKU${updates.length !== 1 ? 's' : ''} live to Newegg?`)) return;
                pushUpdatesInChunks(updates, $('#push-all-sprice-btn'));
            }

            // Bulk save through one HTTP request (mirrors reverb-save-sprice pattern).
            function saveSpriceUpdates(updates) {
                $.ajax({
                    url: "{{ route('newegg.pricing.save.sprice.bulk') }}",
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: { updates: updates },
                    success: function(res) {
                        if (!res.success) {
                            showToast(res.error || 'Failed to save SPRICE updates', 'error');
                            return;
                        }
                        // Reconcile each row with the server's authoritative SPFT/SROI/SPRICE.
                        (res.results || []).forEach(r => {
                            const rows = table.searchRows('sku', '=', r.sku);
                            if (rows.length) {
                                rows[0].update({
                                    sprice: r.sprice,
                                    spft:   r.spft,
                                    sroi:   r.sroi,
                                });
                            }
                        });
                        if (res.errors && res.errors.length) {
                            console.warn('Newegg SPRICE bulk save partial errors:', res.errors);
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Error saving SPRICE updates';
                        showToast(msg, 'error');
                    }
                });
            }

            function updateSummary() {
                const data = table.getData("active");
                let totalL30 = 0;
                let totalPftAmt = 0, totalSalesAmt = 0, totalCogsAmt = 0;

                data.forEach(row => {
                    if (!row.sku) return;
                    const l30 = parseInt(row.l30) || 0;
                    totalL30 += l30;
                    const price = parseFloat(row.price);
                    if (!isNaN(price) && price > 0) {
                        const pftEach = parseFloat(row.pft) || 0;
                        const lp = parseFloat(row.lp) || 0;
                        totalPftAmt += pftEach * l30;
                        totalSalesAmt += price * l30;
                        totalCogsAmt += lp * l30;
                    }
                });

                const pftPct = totalSalesAmt > 0 ? (totalPftAmt / totalSalesAmt) * 100 : 0;
                const roiPct = totalCogsAmt > 0 ? (totalPftAmt / totalCogsAmt) * 100 : 0;

                $('#total-sales-amt-badge').text('Sales: $' + totalSalesAmt.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }));
                $('#avg-gpft-badge').text('GPFT: ' + Math.round(pftPct) + '%');
                $('#total-l30-badge').text('L30: ' + totalL30.toLocaleString());
                $('#roi-percent-badge').text('ROI%: ' + Math.round(roiPct) + '%');

                // Sold / Missing / Map counted over full dataset (stable regardless of active filter).
                let zeroSold = 0, moreSold = 0, missingCount = 0, mapCount = 0, nmapCount = 0;
                table.getData().forEach(row => {
                    if (!row.sku) return;
                    const l30 = parseInt(row.l30) || 0;
                    if (l30 === 0) zeroSold++;
                    else moreSold++;

                    if (neRowMissingL(row)) {
                        missingCount++;
                    } else {
                        const st = neMapStatus(row);
                        if (st === 'map') mapCount++;
                        else if (st === 'nmap') nmapCount++;
                    }
                });
                $('#zero-sold-count-badge').text('0 Sold: ' + zeroSold.toLocaleString());
                $('#more-sold-count-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
                $('#ne-missing-badge').text('Missing L: ' + missingCount.toLocaleString());
                $('#ne-map-badge').text('Map: ' + mapCount.toLocaleString());
                $('#ne-nmap-badge').text('N Map: ' + nmapCount.toLocaleString());
            }

            const COL_URL = '/newegg-pricing-column-visibility';

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            // Internal toolbar columns (selection checkbox) are not user-toggleable.
                            if (def.field === '_select') return;
                            const li = document.createElement("li");
                            const label = document.createElement("label");
                            label.style.cssText = "display:block;padding:5px 10px;cursor:pointer;";
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = def.field;
                            checkbox.checked = savedVisibility[def.field] !== false;
                            checkbox.style.marginRight = "8px";
                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(def.title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    });
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    // _select is controlled by the toolbar mode buttons, not user preference.
                    if (!def.field || def.field === '_select') return;
                    visibility[def.field] = col.isVisible();
                });
                fetch(COL_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ visibility })
                });
            }

            function applyColumnVisibilityFromServer() {
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            if (def.field === '_select') return; // toolbar-controlled
                            if (savedVisibility[def.field] === false) col.hide();
                        });
                    });
            }

            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyNeFilters();
            });
            table.on('dataLoaded', updateSummary);
            table.on('dataProcessed', updateSummary);
            table.on('dataFiltered', function() { updateSummary(); updateSelectAllCheckbox(); });
            table.on('renderComplete', updateSelectAllCheckbox);

            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const col = table.getColumn(e.target.value);
                    if (e.target.checked) col.show(); else col.hide();
                    saveColumnVisibilityToServer();
                }
            });

            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field === '_select') return; // toolbar-controlled, leave hidden
                    col.show();
                });
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            $('#export-btn').on('click', function() {
                table.download("csv", "newegg_pricing.csv");
            });

            // LMP column: View N / + Add
            $(document).on('click', '.view-ne-lmp-competitors', function(e) {
                e.preventDefault();
                const sku = $(this).attr('data-sku') || $(this).data('sku');
                if (!sku) return;
                let linkedSkus = [];
                const rawLinked = $(this).attr('data-linked-skus');
                if (rawLinked) {
                    try { linkedSkus = JSON.parse(rawLinked) || []; } catch (err) { linkedSkus = []; }
                }
                if (!Array.isArray(linkedSkus)) linkedSkus = [];
                neLoadCompetitorsModal(sku, linkedSkus);
            });

            $('#neAddCompetitorForm').on('submit', function(e) {
                e.preventDefault();
                const editId = neEditCompetitorId || $('#neEditCompId').val();
                const isEdit = !!editId;
                const payload = {
                    product_id: $('#neAddCompProductId').val().trim(),
                    price: parseFloat($('#neAddCompPrice').val()) || 0,
                    shipping_cost: parseFloat($('#neAddCompShip').val()) || 0,
                    product_title: $('#neAddCompTitle').val().trim() || null,
                    product_link: $('#neAddCompLink').val().trim() || null,
                    marketplace: 'newegg',
                    _token: '{{ csrf_token() }}',
                };
                if (!isEdit) {
                    payload.sku = $('#neAddCompSku').val();
                } else {
                    payload.id = editId;
                }
                if (!payload.product_id || !payload.price) {
                    alert('Item # and Price are required.');
                    return;
                }
                $.ajax({
                    url: isEdit
                        ? '{{ route('newegg.competitors.update') }}'
                        : '{{ route('newegg.competitors.add') }}',
                    method: 'POST',
                    data: payload,
                    success: function(resp) {
                        if (resp.success) {
                            neResetCompetitorForm(neCurrentLmpSku);
                            neLoadCompetitorsModal(neCurrentLmpSku, neCurrentLinkedLmpSkus);
                            if (table) table.replaceData();
                        } else {
                            alert(resp.error || (isEdit ? 'Failed to update competitor' : 'Failed to add competitor'));
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message))
                            || (isEdit ? 'Failed to update competitor' : 'Failed to add competitor');
                        alert(msg);
                    }
                });
            });

            $('#neCompClearBtn').on('click', function() {
                neResetCompetitorForm(neCurrentLmpSku);
            });

            $(document).on('click', '.ne-edit-lmp-btn', function() {
                const $btn = $(this);
                neEnterEditCompetitorMode({
                    id: $btn.data('id'),
                    sku: $btn.attr('data-sku') || $btn.data('sku') || neCurrentLmpSku,
                    product_id: $btn.attr('data-product-id') || $btn.data('product-id') || '',
                    price: $btn.data('price'),
                    shipping_cost: $btn.data('shipping'),
                    product_title: $btn.attr('data-title') || '',
                    product_link: $btn.attr('data-link') || '',
                });
            });

            $(document).on('click', '.ne-delete-lmp-btn', function() {
                const id = $(this).data('id');
                if (!id) return;
                if (!confirm('Delete this competitor mapping?')) return;
                $.ajax({
                    url: '{{ route('newegg.competitors.delete') }}',
                    method: 'POST',
                    data: { id: id, _token: '{{ csrf_token() }}' },
                    success: function(resp) {
                        if (resp.success) {
                            neResetCompetitorForm(neCurrentLmpSku);
                            neLoadCompetitorsModal(neCurrentLmpSku, neCurrentLinkedLmpSkus);
                            if (table) table.replaceData();
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
